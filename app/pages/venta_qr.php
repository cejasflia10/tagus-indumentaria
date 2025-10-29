<?php
// app/pages/venta_qr.php — Confirmar y registrar venta desde QR (local o pedido)
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config.php';

// Helpers
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function n2($v){ return number_format((float)$v, 2, ',', '.'); }

// Base URL para armar links absolutos simples
$scriptDir = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$BASE = preg_replace('#/app/pages$#', '', $scriptDir);
if ($BASE === '') $BASE = '/';
$u = fn(string $p) => rtrim($BASE,'/').'/'.ltrim($p,'/');

// Traer producto + variante
$pid = (int)($_GET['pid'] ?? $_POST['pid'] ?? 0);
$vid = (int)($_GET['vid'] ?? $_POST['vid'] ?? 0);
if ($pid<=0 || $vid<=0) { http_response_code(400); exit('QR inválido'); }

$pq = $conexion->query("SELECT id, titulo, precio FROM ind_productos WHERE id={$pid} LIMIT 1");
if (!$pq || !$pq->num_rows) { http_response_code(404); exit('Producto no encontrado'); }
$prod = $pq->fetch_assoc();

$vq = $conexion->query("SELECT id, talle, color, medidas, stock FROM ind_variantes WHERE id={$vid} AND producto_id={$pid} LIMIT 1");
if (!$vq || !$vq->num_rows) { http_response_code(404); exit('Variante no encontrada'); }
$var = $vq->fetch_assoc();

$err = null; $ok = false; $venta_id = null; $pedido_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) ) {
  $cantidad = max(1, (int)($_POST['cantidad'] ?? 1));
  $canal    = ($_POST['canal'] ?? 'local'); // local | pedido
  $precio_u = (float)$prod['precio'];
  $total    = $precio_u * $cantidad;

  // Validar stock
  $stock_actual = (int)$var['stock'];
  if ($stock_actual < $cantidad) {
    $err = "Stock insuficiente. Disponible: {$stock_actual}";
  } else {
    $conexion->begin_transaction();
    try {
      // Si es pedido, creo cabecera + item
      if ($canal === 'pedido') {
        $nombre = trim((string)($_POST['nombre'] ?? 'Mostrador'));
        $tel    = trim((string)($_POST['tel'] ?? '-'));
        $envio  = in_array(($_POST['envio'] ?? 'retiro'), ['retiro','domicilio'], true) ? $_POST['envio'] : 'retiro';
        $dir    = trim((string)($_POST['direccion'] ?? ''));
        $pago   = in_array(($_POST['pago'] ?? 'efectivo'), ['efectivo','transferencia'], true) ? $_POST['pago'] : 'efectivo';
        $alias  = trim((string)($_POST['alias_mostrado'] ?? ''));
        $obs    = trim((string)($_POST['obs'] ?? ''));

        // Crear pedido
        $sp = $conexion->prepare("INSERT INTO ind_pedidos (nombre, tel, envio, direccion, pago, alias_mostrado, obs, total, estado) VALUES (?,?,?,?,?,?,?,?, 'pendiente')");
        $sp->bind_param('sssssssd', $nombre, $tel, $envio, $dir, $pago, $alias, $obs, $total);
        $sp->execute();
        $pedido_id = $sp->insert_id;
        $sp->close();

        // Agregar item
        $titulo = (string)$prod['titulo'];
        $color  = (string)($var['color'] ?? '');
        $talle  = (string)($var['talle'] ?? '');
        $si = $conexion->prepare("INSERT INTO ind_pedido_items (pedido_id, producto_id, variante_id, titulo, color, talle, precio_unit, cantidad, total)
                                  VALUES (?,?,?,?,?,?,?,?,?)");
        $tt = $total;
        $si->bind_param('iiisssidi', $pedido_id, $pid, $vid, $titulo, $color, $talle, $precio_u, $cantidad, $tt);
        $si->execute();
        $si->close();
      }

      // Registrar venta simple
      $sv = $conexion->prepare("INSERT INTO ind_ventas (producto_id, variante_id, cantidad, precio_unit, total) VALUES (?,?,?,?,?)");
      $sv->bind_param('iiidd', $pid, $vid, $cantidad, $precio_u, $total);
      $sv->execute();
      $venta_id = $sv->insert_id;
      $sv->close();

      // Descontar stock
      $conexion->query("UPDATE ind_variantes SET stock = GREATEST(0, stock - {$cantidad}) WHERE id={$vid} AND producto_id={$pid}");

      $conexion->commit();
      $ok = true;

      // Refrescar variante para mostrar stock nuevo
      $vq2 = $conexion->query("SELECT stock FROM ind_variantes WHERE id={$vid} LIMIT 1");
      if ($vq2 && $vq2->num_rows) { $var['stock'] = (int)$vq2->fetch_assoc()['stock']; }
    } catch (Throwable $e) {
      $conexion->rollback();
      $err = 'No se pudo completar la venta. '.h($e->getMessage());
    }
  }
}

$titulo_pagina = 'Venta por QR';
require_once $u('public/partials/menu.php');
?>
<style>
.wrap{max-width:860px;margin:16px auto;padding:0 14px}
.panel{border:1px solid #e5e7eb;border-radius:14px;background:#fff;box-shadow:0 6px 20px rgba(17,24,39,.06)}
.px{padding:12px 14px}
.header{font-weight:800;border-bottom:1px solid #e5e7eb}
.row{display:grid;gap:12px}
@media(min-width:760px){.row{grid-template-columns:1fr 1fr}}
.muted{color:#6b7280}
.label{font-weight:700;margin:.2rem 0}
.input,select,textarea{width:100%;padding:12px 14px;border:1px solid #e5e7eb;border-radius:10px;min-height:44px;font-size:16px}
.btn{display:inline-flex;gap:8px;align-items:center;justify-content:center;padding:12px 16px;border-radius:12px;border:1px solid transparent;cursor:pointer;font-weight:700;min-height:44px}
.btn-primary{background:#0d6efd;color:#fff}
.btn-muted{background:#f3f4f6}
.alert-ok{border-left:4px solid #10b981}
.alert-err{border-left:4px solid #ef4444}
.badge{display:inline-block;padding:.25rem .55rem;border-radius:999px;background:#f3f4f6}
</style>

<div class="wrap">
  <div class="panel">
    <div class="px header">Confirmar venta</div>
    <div class="px">

      <?php if ($ok): ?>
        <div class="panel alert-ok" style="margin-bottom:12px"><div class="px">
          ✅ Venta registrada (ID #<?= (int)$venta_id ?>) — Stock restante variante: <b><?= (int)$var['stock'] ?></b>
          <?php if ($pedido_id): ?>
            <div class="muted">Se creó el pedido #<?= (int)$pedido_id ?> con el item correspondiente.</div>
          <?php endif; ?>
        </div></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
          <a class="btn btn-primary" href="<?= h($u('app/pages/venta_qr.php').'?pid='.$pid.'&vid='.$vid.'&sell=1') ?>">Vender otra igual</a>
          <a class="btn btn-muted" href="<?= h($u('public/tienda.php')) ?>">Ir a Tienda</a>
          <a class="btn btn-muted" href="<?= h($u('public/stock.php')) ?>">Ver Stock</a>
        </div>
      <?php endif; ?>

      <?php if ($err): ?>
        <div class="panel alert-err" style="margin-bottom:12px"><div class="px">❌ <?= h($err) ?></div></div>
      <?php endif; ?>

      <div class="panel" style="margin-bottom:12px">
        <div class="px">
          <div><span class="badge">Producto</span> <b><?= h($prod['titulo']) ?></b></div>
          <div class="muted">Precio: $ <?= n2($prod['precio']) ?></div>
          <div class="muted">Variante: <?= h($var['talle'] ?: '—') ?><?= ($var['talle'] && $var['color']) ? ' / ' : '' ?><?= h($var['color'] ?: '—') ?></div>
          <?php if (!empty($var['medidas'])): ?><div class="muted">Medidas: <?= h($var['medidas']) ?></div><?php endif; ?>
          <div class="muted">Stock disponible: <b><?= (int)$var['stock'] ?></b></div>
        </div>
      </div>

      <form method="post" class="row">
        <input type="hidden" name="pid" value="<?= (int)$pid ?>">
        <input type="hidden" name="vid" value="<?= (int)$vid ?>">
        <input type="hidden" name="confirm" value="1">

        <div>
          <div class="label">Cantidad</div>
          <input class="input" type="number" name="cantidad" min="1" step="1" value="1" required>
        </div>

        <div>
          <div class="label">Canal</div>
          <select class="input" name="canal" id="selCanal" onchange="togglePedido(this.value)">
            <option value="local" selected>Venta en local</option>
            <option value="pedido">Venta por pedido</option>
          </select>
        </div>

        <!-- Datos pedido -->
        <div id="pedidoBox" style="grid-column:1/-1;display:none">
          <div class="panel"><div class="px">
            <div class="label">Datos del pedido</div>
            <div class="row" style="grid-template-columns:1fr 1fr">
              <div>
                <div class="label">Nombre</div>
                <input class="input" type="text" name="nombre" placeholder="Cliente">
              </div>
              <div>
                <div class="label">Teléfono</div>
                <input class="input" type="text" name="tel" placeholder="11-....">
              </div>
              <div>
                <div class="label">Envío</div>
                <select class="input" name="envio">
                  <option value="retiro" selected>Retiro</option>
                  <option value="domicilio">Domicilio</option>
                </select>
              </div>
              <div>
                <div class="label">Pago</div>
                <select class="input" name="pago">
                  <option value="efectivo" selected>Efectivo</option>
                  <option value="transferencia">Transferencia</option>
                </select>
              </div>
              <div style="grid-column:1/-1">
                <div class="label">Dirección (si domicilio)</div>
                <input class="input" type="text" name="direccion" placeholder="Calle 123, piso...">
              </div>
              <div>
                <div class="label">Alias mostrado</div>
                <input class="input" type="text" name="alias_mostrado" placeholder="Opcional">
              </div>
              <div>
                <div class="label">Obs</div>
                <input class="input" type="text" name="obs" placeholder="Notas del pedido">
              </div>
            </div>
          </div></div>
        </div>

        <div style="grid-column:1/-1;display:flex;gap:10px;flex-wrap:wrap">
          <button class="btn btn-primary" type="submit">✅ Confirmar venta</button>
          <a class="btn btn-muted" href="<?= h($u('public/tienda.php')) ?>">Cancelar</a>
        </div>
      </form>

      <div class="muted" style="margin-top:10px">
        Tip: imprimí <i>etiquetas QR por variante</i> desde la pantalla de **crear producto** o desde <b>Admin indumentaria</b>.
      </div>
    </div>
  </div>
</div>

<script>
function togglePedido(v){
  const box = document.getElementById('pedidoBox');
  box.style.display = (v === 'pedido') ? 'block' : 'none';
}
// Si vino con ?sell=1, preselecciono "local" y cantidad 1.
document.addEventListener('DOMContentLoaded', ()=>{
  const url = new URL(window.location.href);
  if (url.searchParams.get('sell') === '1'){
    togglePedido('local');
  }
});
</script>
