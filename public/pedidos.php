<?php
// public/pedidos.php — Admin: pedidos con autorefresh + alerta + acciones rápidas (Listo/Enviado) + WhatsApp
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/partials/menu.php'; // menú admin
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers ===== */
function n2($v){ return number_format((float)$v, 2, ',', '.'); }
function tel_digits(string $t): string { return preg_replace('/\D+/', '', $t) ?? ''; }
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

/* ===== Acciones (cambiar estado) ===== */
if (($_POST['action'] ?? '') === 'set_estado') {
  $id  = (int)($_POST['id'] ?? 0);
  $new = trim($_POST['estado'] ?? '');
  $allowed = ['pendiente','pagado','cancelado','listo','enviado'];
  if ($id > 0 && in_array($new, $allowed, true)) {
    $st = $conexion->prepare("UPDATE ind_pedidos SET estado=? WHERE id=?");
    $st->bind_param('si', $new, $id);
    $st->execute();
  }
  // Volver mismo lugar con filtros
  $qs = $_GET; $url = 'pedidos.php'.($qs?('?'.http_build_query($qs)):'');
  header('Location: '.$url); exit;
}

/* ===== Filtros ===== */
$tel    = trim($_GET['tel'] ?? '');
$estado = trim($_GET['estado'] ?? ''); // pendiente | pagado | cancelado | listo | enviado
$w = [];
if ($tel !== '') {
  $telSql = $conexion->real_escape_string($tel);
  $w[] = "tel LIKE '%{$telSql}%'";
}
if ($estado !== '') {
  $estSql = $conexion->real_escape_string($estado);
  $w[] = "estado = '{$estSql}'";
}
$where = $w ? ('WHERE '.implode(' AND ', $w)) : '';

/* ===== Endpoint de poll JSON (latest_id / total) ===== */
if (isset($_GET['poll']) && $_GET['poll'] === '1') {
  header('Content-Type: application/json; charset=utf-8');
  $latest_id = 0; $total_rows = 0;
  $q1 = $conexion->query("SELECT MAX(id) AS m FROM ind_pedidos {$where}");
  if ($q1 && $q1->num_rows) { $latest_id = (int)($q1->fetch_assoc()['m'] ?? 0); }
  $q2 = $conexion->query("SELECT COUNT(*) AS c FROM ind_pedidos {$where}");
  if ($q2 && $q2->num_rows) { $total_rows = (int)($q2->fetch_assoc()['c'] ?? 0); }
  echo json_encode(['latest_id'=>$latest_id, 'total'=>$total_rows], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ===== Paginación ===== */
$page = max(1, (int)($_GET['page'] ?? 1));
$pp   = 25;
$off  = ($page-1)*$pp;

$totalRows = 0;
$cnt = $conexion->query("SELECT COUNT(*) c FROM ind_pedidos {$where}");
if ($cnt && $cnt->num_rows) $totalRows = (int)$cnt->fetch_assoc()['c'];
$pages = max(1, (int)ceil($totalRows / $pp));

/* ===== Traer pedidos ===== */
$sql = "
  SELECT id, nombre, tel, envio, direccion, pago, alias_mostrado, total, estado, created_at
  FROM ind_pedidos
  {$where}
  ORDER BY id DESC
  LIMIT {$pp} OFFSET {$off}
";
$ped = $conexion->query($sql);

/* ID mayor de la página (para el poll) */
$currentMaxId = 0;
if ($ped && $ped->num_rows) {
  $ped->data_seek(0);
  $first = $ped->fetch_assoc();
  if ($first) $currentMaxId = (int)$first['id'];
  $ped->data_seek(0);
}
?>
<div class="container">
  <div class="card">
    <div class="card-header">Pedidos</div>
    <div class="card-body">

      <!-- Banner de nuevos pedidos -->
      <div id="newOrderBanner" class="card" style="display:none;border-color:#dbeafe;margin-bottom:10px">
        <div class="card-body" style="display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap">
          <div>🔔 <strong>Nuevo pedido recibido</strong> — hay actualizaciones disponibles.</div>
          <div style="display:flex;gap:8px">
            <button class="btn btn-primary" id="refreshNowBtn">Actualizar ahora</button>
            <button class="btn btn-muted" id="hideBannerBtn">Ocultar</button>
          </div>
        </div>
      </div>

      <!-- Filtros -->
      <form method="get" class="row cols-3">
        <div>
          <label>Teléfono</label>
          <input class="input" type="tel" name="tel" value="<?=h($tel)?>" placeholder="+54...">
        </div>
        <div>
          <label>Estado</label>
          <select class="input" name="estado">
            <option value="">(Todos)</option>
            <?php
              $opts = [
                'pendiente'=>'Pendiente',
                'pagado'=>'Pagado',
                'cancelado'=>'Cancelado',
                'listo'=>'Listo para retirar',
                'enviado'=>'Enviado'
              ];
              foreach ($opts as $k=>$lbl){
                $sel = ($estado===$k)?' selected':'';
                echo "<option value=\"".h($k)."\"{$sel}>".h($lbl)."</option>";
              }
            ?>
          </select>
        </div>
        <div style="align-self:end;display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn btn-primary" type="submit">Filtrar</button>
          <a class="btn btn-muted" href="pedidos.php">Limpiar</a>
        </div>
      </form>

      <div class="muted" style="margin-top:8px">
        <?= (int)$totalRows ?> resultado(s) • Página <?= (int)$page ?> de <?= (int)$pages ?>
      </div>

      <?php if ($ped && $ped->num_rows): ?>
        <?php while($p = $ped->fetch_assoc()):
          $pid   = (int)$p['id'];
          $nomb  = (string)$p['nombre'];
          $telC  = tel_digits((string)$p['tel']); // para wa.me
          $total = (float)$p['total'];
          $linkTienda = (defined('PUBLIC_BASE_URL') ? rtrim(PUBLIC_BASE_URL,'/') : '') . '/public/mis_pedidos.php?tel=' . rawurlencode($p['tel']);
          
          // Mensajes para WhatsApp
          $msgListo = "Hola {$nomb}, tu pedido #{$pid} está LISTO PARA RETIRAR. Total $ ".n2($total)."."
                    . ($p['envio']==='retiro' && !empty($p['direccion']) ? " Retiro en: {$p['direccion']}." : "")
                    . " Consultá tus pedidos: {$linkTienda}";
          $msgEnvio = "Hola {$nomb}, tu pedido #{$pid} ya SALIÓ A ENVÍO. Total $ ".n2($total)."."
                    . (!empty($p['direccion']) ? " Enviado a: {$p['direccion']}." : "")
                    . " Consultá tus pedidos: {$linkTienda}";
          $waListo  = $telC ? ("https://wa.me/{$telC}?text=".rawurlencode($msgListo)) : '';
          $waEnvio  = $telC ? ("https://wa.me/{$telC}?text=".rawurlencode($msgEnvio)) : '';
        ?>
          <div class="card" style="margin-top:12px">
            <div class="card-body">
              <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
                <div>
                  <strong>#<?= $pid ?></strong>
                  — <?= h($p['created_at']) ?>
                  — <strong>$<?= n2($total) ?></strong>
                  <div class="muted">
                    <?= h($nomb) ?> — <?= h($p['tel']) ?>
                  </div>
                </div>
                <div style="text-align:right">
                  <div><span class="muted">Estado:</span> <strong><?= h($p['estado']) ?></strong></div>
                  <div class="muted">
                    Envío: <?= h($p['envio']) ?>
                    <?php if ($p['direccion']): ?> — (<?= h($p['direccion']) ?>)<?php endif; ?>
                  </div>
                  <div class="muted">
                    Pago: <?= h($p['pago']) ?>
                    <?php if ($p['alias_mostrado']): ?> — Alias: <code><?= h($p['alias_mostrado']) ?></code><?php endif; ?>
                  </div>
                </div>
              </div>

              <?php
                $items = $conexion->query("
                  SELECT titulo, color, talle, cantidad, precio_unit, total
                  FROM ind_pedido_items
                  WHERE pedido_id={$pid}
                  ORDER BY id
                ");
              ?>
              <div class="table-wrap" style="margin-top:10px">
                <table class="table">
                  <thead><tr>
                    <th>Producto</th><th>Variante</th><th>Cant.</th><th>PU</th><th>Total</th>
                  </tr></thead>
                  <tbody>
                  <?php if ($items && $items->num_rows): while($it=$items->fetch_assoc()): ?>
                    <tr>
                      <td><?= h($it['titulo']) ?></td>
                      <td><?= h(($it['color']?:'').' '.($it['talle']?:'')) ?></td>
                      <td><?= (int)$it['cantidad'] ?></td>
                      <td>$<?= n2($it['precio_unit']) ?></td>
                      <td>$<?= n2($it['total']) ?></td>
                    </tr>
                  <?php endwhile; else: ?>
                    <tr><td colspan="5" class="muted">Sin items</td></tr>
                  <?php endif; ?>
                  </tbody>
                </table>
              </div>

              <!-- Acciones rápidas -->
              <div class="mt-3" style="display:flex;gap:8px;flex-wrap:wrap">
                <form method="post">
                  <input type="hidden" name="action" value="set_estado">
                  <input type="hidden" name="id" value="<?=$pid?>">
                  <input type="hidden" name="estado" value="listo">
                  <button class="btn btn-primary" title="Marcar como listo para retirar">✔️ Listo para retirar</button>
                </form>
                <?php if ($waListo): ?>
                  <a class="btn btn-muted" href="<?=h($waListo)?>" target="_blank" rel="noopener">🟢 WhatsApp (Listo)</a>
                <?php endif; ?>

                <form method="post">
                  <input type="hidden" name="action" value="set_estado">
                  <input type="hidden" name="id" value="<?=$pid?>">
                  <input type="hidden" name="estado" value="enviado">
                  <button class="btn btn-primary" title="Marcar como enviado">📦 Salió el envío</button>
                </form>
                <?php if ($waEnvio): ?>
                  <a class="btn btn-muted" href="<?=h($waEnvio)?>" target="_blank" rel="noopener">🟢 WhatsApp (Envío)</a>
                <?php endif; ?>

                <form method="post">
                  <input type="hidden" name="action" value="set_estado">
                  <input type="hidden" name="id" value="<?=$pid?>">
                  <input type="hidden" name="estado" value="pagado">
                  <button class="btn btn-muted" title="Marcar como pagado">💳 Pagado</button>
                </form>

                <form method="post" onsubmit="return confirm('¿Cancelar este pedido?');">
                  <input type="hidden" name="action" value="set_estado">
                  <input type="hidden" name="id" value="<?=$pid?>">
                  <input type="hidden" name="estado" value="cancelado">
                  <button class="btn btn-danger" title="Cancelar pedido">✖ Cancelar</button>
                </form>
              </div>

            </div>
          </div>
        <?php endwhile; ?>

        <!-- Paginador -->
        <?php if ($pages > 1): ?>
          <div style="margin-top:12px;display:flex;gap:6px;flex-wrap:wrap">
            <?php
              $qs = $_GET; // mantener filtros
              for($i=1;$i<=$pages;$i++){
                $qs['page']=$i;
                $url = 'pedidos.php?'.http_build_query($qs);
                $cls = 'btn '.($i===$page?'btn-primary':'btn-muted');
                echo '<a class="'.$cls.'" href="'.h($url).'">'.$i.'</a>';
              }
            ?>
          </div>
        <?php endif; ?>

      <?php else: ?>
        <div class="muted" style="margin-top:12px">No hay pedidos.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ===== Auto-refresh + Poll de nuevos pedidos ===== -->
<script>
(function(){
  const AUTO_RELOAD_MS = 30000; // 30s recarga total
  const POLL_MS        = 10000; // 10s chequeo latest_id

  const currentMaxId = <?= (int)$currentMaxId ?>;

  const params = new URLSearchParams(window.location.search);
  params.set('poll','1'); // endpoint JSON
  const pollUrl = window.location.pathname + '?' + params.toString();

  function beep(){
    try{
      const ctx = new (window.AudioContext || window.webkitAudioContext)();
      const o = ctx.createOscillator(), g = ctx.createGain();
      o.type = 'sine'; o.frequency.value = 880;
      o.connect(g); g.connect(ctx.destination);
      g.gain.setValueAtTime(0.001, ctx.currentTime);
      g.gain.exponentialRampToValueAtTime(0.2, ctx.currentTime+0.02);
      o.start();
      setTimeout(()=>{ g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime+0.12); o.stop(ctx.currentTime+0.14); }, 80);
    }catch(e){}
  }

  const banner = document.getElementById('newOrderBanner');
  const btnRefresh = document.getElementById('refreshNowBtn');
  const btnHide = document.getElementById('hideBannerBtn');
  if (btnRefresh) btnRefresh.addEventListener('click', ()=> window.location.reload());
  if (btnHide) btnHide.addEventListener('click', ()=> banner && (banner.style.display='none'));

  let seenMax = currentMaxId;
  setInterval(async ()=>{
    try{
      const r = await fetch(pollUrl, {cache:'no-store'});
      if (!r.ok) return;
      const j = await r.json();
      const latest = parseInt(j.latest_id||0, 10);
      if (latest > seenMax){
        seenMax = latest;
        if (banner) banner.style.display = '';
        beep();
      }
    }catch(e){}
  }, POLL_MS);

  setInterval(()=> window.location.reload(), AUTO_RELOAD_MS);
})();
</script>
