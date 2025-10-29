<?php
// public/mis_pedidos.php — Público: el cliente busca sus pedidos por teléfono
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__.'/../app/config.php';
require_once __DIR__.'/partials/public_header.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function n2($v){ return number_format((float)$v, 2, ',', '.'); }

$tel = trim($_GET['tel'] ?? '');
$ped = null;
if ($tel !== '') {
  $telSql = $conexion->real_escape_string($tel);
  $ped = $conexion->query("SELECT * FROM ind_pedidos WHERE tel LIKE '%{$telSql}%' ORDER BY id DESC LIMIT 50");
}
?>
<div class="container">
  <div class="card">
    <div class="card-header">Mis pedidos</div>
    <div class="card-body">
      <form method="get" class="row cols-2">
        <div><label>Teléfono</label><input class="input" type="tel" name="tel" value="<?=h($tel)?>" placeholder="Tu número"></div>
        <div style="align-self:end"><button class="btn btn-primary">Buscar</button> <a class="btn btn-muted" href="?">Limpiar</a></div>
      </form>

      <?php if ($tel !== ''): ?>
        <h3 class="mt-3">Resultados</h3>
        <?php if ($ped && $ped->num_rows): while($p=$ped->fetch_assoc()): ?>
          <div class="card" style="margin-top:10px">
            <div class="card-body">
              <div><strong>Pedido #<?= (int)$p['id'] ?></strong> — <?= h($p['created_at']) ?> — <strong>$<?= n2($p['total']) ?></strong></div>
              <div class="muted">Estado: <?= h($p['estado']) ?> — Envío: <?= h($p['envio']) ?> <?= $p['direccion']?('('.h($p['direccion']).')'):'' ?></div>
              <div class="mt-2">
                <?php
                  $items = $conexion->query("SELECT titulo,color,talle,cantidad,total FROM ind_pedido_items WHERE pedido_id=".(int)$p['id']." ORDER BY id");
                  if ($items && $items->num_rows) {
                    echo '<ul style="margin:0;padding-left:18px">';
                    while($i=$items->fetch_assoc()){
                      echo '<li>'.h($i['titulo']).' '.h($i['color']?:'').' '.h($i['talle']?:'').' x'.(int)$i['cantidad'].' — $'.n2($i['total']).'</li>';
                    }
                    echo '</ul>';
                  } else {
                    echo '<span class="muted">Sin items</span>';
                  }
                ?>
              </div>
            </div>
          </div>
        <?php endwhile; else: ?>
          <div class="muted mt-2">No encontramos pedidos para ese teléfono.</div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
