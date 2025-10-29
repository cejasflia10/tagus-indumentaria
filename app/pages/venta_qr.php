<?php
/* ============================================================
   app/pages/venta_qr.php — Ficha de venta por QR (móvil/PC)
   - Soporta pid (producto) y vid (variante). Con sell=1, vende 1 y descuenta stock.
   ============================================================ */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/helpers.php';
require_once __DIR__.'/partials/menu.php'; 

if (!isset($conexion) || !($conexion instanceof mysqli) || $conexion->connect_errno) {
  http_response_code(500);
  exit('❌ Sin conexión a BD.');
}

$pid  = (int)($_GET['pid'] ?? 0);
$vid  = (int)($_GET['vid'] ?? 0);
$sell = (int)($_GET['sell'] ?? 0);

if ($pid <= 0) { http_response_code(400); exit('❌ Falta pid.'); }

/* Producto + img principal */
$st = $conexion->prepare("
  SELECT p.*,
  COALESCE(
    (SELECT url FROM ind_imagenes i WHERE i.producto_id=p.id AND i.is_primary=1 LIMIT 1),
    (SELECT url FROM ind_imagenes i2 WHERE i2.producto_id=p.id LIMIT 1)
  ) AS foto
  FROM ind_productos p WHERE p.id=? AND p.activo=1
");
$st->bind_param('i', $pid);
$st->execute(); $res = $st->get_result();
$prod = $res ? $res->fetch_assoc() : null; $st->close();
if (!$prod) { http_response_code(404); exit('❌ Producto no encontrado o inactivo.'); }

/* Variante (opcional) */
$var = null;
if ($vid > 0) {
  $rv = $conexion->query("SELECT * FROM ind_variantes WHERE id={$vid} AND producto_id={$pid} LIMIT 1");
  $var = $rv ? $rv->fetch_assoc() : null;
  if (!$var) { http_response_code(404); exit('❌ Variante inexistente para este producto.'); }
}

/* Auto-venta si sell=1 */
$msg = '';
if ($sell === 1) {
  $precio = (float)$prod['precio'];
  if ($var) {
    // transacción sencilla
    $conexion->begin_transaction();
    $ok1 = $conexion->query("UPDATE ind_variantes SET stock = stock - 1 WHERE id={$vid} AND producto_id={$pid} AND stock > 0");
    if ($ok1 && $conexion->affected_rows > 0) {
      $ok2 = $conexion->query("INSERT INTO ind_ventas (producto_id, variante_id, cantidad, precio_unit, total) VALUES ({$pid}, {$vid}, 1, {$precio}, {$precio})");
      if ($ok2) { $conexion->commit(); $msg = '✅ Venta registrada (1 unidad).'; }
      else { $conexion->rollback(); $msg='❌ No se pudo registrar la venta.'; }
    } else {
      $conexion->rollback();
      $msg = '⚠️ Sin stock disponible.';
    }
  } else {
    // sin variante (solo registra venta)
    $ok = $conexion->query("INSERT INTO ind_ventas (producto_id, variante_id, cantidad, precio_unit, total) VALUES ({$pid}, NULL, 1, {$precio}, {$precio})");
    $msg = $ok ? '✅ Venta registrada (1 unidad).' : '❌ No se pudo registrar la venta.';
  }
}

/* Para UI: variantes del producto */
$vars = [];
$r = $conexion->query("SELECT * FROM ind_variantes WHERE producto_id={$pid} ORDER BY talle,color");
if ($r) { $vars = $r->fetch_all(MYSQLI_ASSOC); }

/* UI */
$title = 'Venta QR';
view('partials/header.php');
?>
<style>
.vqr .card{border:1px solid var(--border);border-radius:14px;overflow:hidden;background:linear-gradient(180deg,#151924,#10141c);display:flex;flex-wrap:wrap}
.vqr .left{flex:0 0 300px;background:#0c0f15;display:flex;align-items:center;justify-content:center}
.vqr .left img{width:100%;height:100%;object-fit:cover}
.vqr .right{padding:1rem;display:flex;flex-direction:column;gap:.6rem;flex:1;min-width:260px}
.vqr .muted{color:var(--muted)}
.vqr .pill{padding:.35rem .6rem;border:1px solid var(--border);border-radius:999px;background:#151515;color:#fff;font-size:.88rem}
</style>

<div class="vqr">
  <div class="card">
    <div class="left">
      <img src="<?= h($prod['foto'] ?: asset('placeholder.jpg')) ?>" alt="">
    </div>
    <div class="right">
      <h2 style="margin:.2rem 0"><?= h($prod['titulo']) ?></h2>
      <div class="muted"><?= h($prod['categoria'] ?: '—') ?></div>
      <div class="price" style="font-size:1.3rem">$ <?= money($prod['precio']) ?></div>

      <?php if ($msg): ?>
        <div class="panel" style="border:1px solid var(--border);padding:.5rem .75rem;border-radius:10px"><?= h($msg) ?></div>
      <?php endif; ?>

      <?php if ($var): ?>
        <?php
          $lb = trim(($var['talle'] ?? '') . ((($var['talle'] ?? '') && ($var['color'] ?? '')) ? ' / ' : '') . ($var['color'] ?? ''));
          if ($lb==='') $lb='Única';
        ?>
        <div class="row" style="align-items:center;gap:.5rem">
          <span class="pill"><?= h($lb) ?></span>
          <?php if (!empty($var['medidas'])): ?><span class="pill">Medidas: <?= h($var['medidas']) ?></span><?php endif; ?>
          <span class="pill">Stock: <?= (int)$var['stock'] ?></span>
        </div>
        <div class="row" style="margin-top:.6rem">
          <a class="btn primary" href="<?= url('app/pages/venta_qr.php') . '?pid=' . $pid . '&vid=' . (int)$var['id'] . '&sell=1' ?>">Vender 1</a>
          <a class="btn" href="<?= url('app/pages/admin_indum.php') ?>">Volver</a>
        </div>
      <?php else: ?>
        <!-- Si el QR no trae vid, permitir elegir variante -->
        <?php if ($vars): ?>
          <form class="stack" method="get">
            <input type="hidden" name="pid" value="<?= $pid ?>">
            <label>Elegí variante</label>
            <select class="select" name="vid" required>
              <option value="">— Seleccionar —</option>
              <?php foreach ($vars as $v):
                $lb = trim(($v['talle'] ?? '') . ((($v['talle'] ?? '') && ($v['color'] ?? '')) ? ' / ' : '') . ($v['color'] ?? ''));
                if ($lb==='') $lb='Única';
                $stock=(int)$v['stock'];
                $med  = trim((string)$v['medidas'] ?? '');
              ?>
                <option value="<?= (int)$v['id'] ?>" <?= $stock<=0?'disabled':'' ?>>
                  <?= h($lb) ?> <?= $med? ' — '.h($med):'' ?> (stock: <?= $stock ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <div class="row">
              <button class="btn primary" name="sell" value="1">Vender 1</button>
              <a class="btn" href="<?= url('app/pages/admin_indum.php') ?>">Volver</a>
            </div>
          </form>
        <?php else: ?>
          <div class="row" style="margin-top:.6rem">
            <a class="btn primary" href="<?= url('app/pages/venta_qr.php') . '?pid=' . $pid . '&sell=1' ?>">Vender 1</a>
            <a class="btn" href="<?= url('app/pages/admin_indum.php') ?>">Volver</a>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php view('partials/footer.php'); ?>
