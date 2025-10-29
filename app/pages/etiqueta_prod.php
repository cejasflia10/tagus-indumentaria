<?php
/* ============================================================
   app/pages/etiqueta_prod.php — Etiqueta imprimible con QR
   • Muestra foto, nombre, categoría, precio y variantes
   • QR apunta a venta_qr.php?pid=...
   • Botón para imprimir (A6 por defecto)
   ============================================================ */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/helpers.php';

if (!isset($conexion) || !($conexion instanceof mysqli) || $conexion->connect_errno) {
  http_response_code(500); exit('❌ Sin conexión a BD.');
}

$pid = (int)($_GET['pid'] ?? 0);
if ($pid <= 0) { http_response_code(400); exit('❌ Falta pid.'); }

/* Producto + imagen principal */
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

/* Variantes */
$vars = [];
$r = $conexion->query("SELECT * FROM ind_variantes WHERE producto_id={$pid} ORDER BY talle,color");
if ($r) { $vars = $r->fetch_all(MYSQLI_ASSOC); }

/* QR */
function qr_url(string $data, int $size=360): string {
  $chl = rawurlencode($data);
  return "https://chart.googleapis.com/chart?cht=qr&chs={$size}x{$size}&chld=L|0&chl={$chl}";
}
$sellUrl = url('app/pages/venta_qr.php').'?pid='.$pid;

/* UI */
$title = 'Etiqueta / QR';
view('partials/header.php');
?>
<style>
.etq { --bg:#121212; --panel:#1e1e1e; --border:#2a2a2a; --text:#f2f2f2; --muted:#b9b9b9; --gold:#d4af37; }
.etq .card{
  display:grid; grid-template-columns: 1fr 1fr; gap:12px;
  border:1px solid var(--border); border-radius:14px; padding:12px;
  background:linear-gradient(180deg,#1c1c1c,#161616);
  max-width:820px; margin:0 auto;
}
.etq .photo{background:#0c0c0c;border-radius:10px;overflow:hidden;display:flex;align-items:center;justify-content:center}
.etq .photo img{width:100%;height:100%;object-fit:cover}
.etq .info{display:flex;flex-direction:column;gap:.45rem}
.etq .title{font-weight:800;font-size:1.25rem;color:var(--text);margin:0}
.etq .muted{color:var(--muted)}
.etq .price{font-weight:900;color:var(--gold);font-size:1.2rem}
.etq .qrbox{display:flex;align-items:center;gap:.8rem;flex-wrap:wrap}
.etq .pills{display:flex;gap:.4rem;flex-wrap:wrap}
.etq .pill{padding:.35rem .6rem;border:1px solid var(--border);border-radius:999px;background:#151515;color:#fff;font-size:.88rem}
@media print{
  header.site, footer, .noprint { display:none !important; }
  main.container { max-width: 100% !important; padding: 0 !important; }
  .etq .card{ border:0; border-radius:0; background:#fff; color:#000; }
  .etq .price{ color:#000; }
}
</style>

<div class="etq">
  <div class="noprint" style="display:flex;justify-content:flex-end;margin:.5rem 0">
    <button class="btn" onclick="window.print()">🖨️ Imprimir</button>
  </div>

  <div class="card">
    <!-- Foto -->
    <div class="photo">
      <img src="<?= h($prod['foto'] ?: asset('placeholder.jpg')) ?>" alt="">
    </div>

    <!-- Datos + QR -->
    <div class="info">
      <p class="title"><?= h($prod['titulo']) ?></p>
      <div class="muted"><?= h($prod['categoria'] ?: '—') ?></div>
      <div class="price">$ <?= money($prod['precio']) ?></div>

      <?php if ($vars): ?>
        <div>
          <div class="muted" style="margin-bottom:.25rem">Talles/Colores</div>
          <div class="pills">
            <?php
              $labels=[];
              foreach ($vars as $v) {
                $lb = trim(($v['talle'] ?? '') . ((($v['talle'] ?? '') && ($v['color'] ?? '')) ? ' / ' : '') . ($v['color'] ?? ''));
                if ($lb==='') $lb='Única';
                $labels[$lb] = true;
              }
              foreach (array_keys($labels) as $lb): ?>
                <span class="pill"><?= h($lb) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="qrbox" style="margin-top:.5rem">
        <img src="<?= qr_url($sellUrl, 220) ?>" alt="QR" width="220" height="220" style="border-radius:8px;border:1px solid var(--border)">
        <div class="muted">Escaneá para vender o abrir la ficha:<br><small><?= h($sellUrl) ?></small></div>
      </div>
    </div>
  </div>
</div>

<?php view('partials/footer.php'); ?>
