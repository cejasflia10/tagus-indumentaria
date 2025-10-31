<?php
/* ============================================================
   etiqueta_var.php — Etiqueta imprimible para variante
   ============================================================ */
declare(strict_types=1);

ini_set('display_errors','1');
error_reporting(E_ALL);

// ✅ rutas corregidas
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../public/partials/menu.php';

@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function qr_url(string $data, int $size=200): string {
  $chl = rawurlencode($data);
  return "https://chart.googleapis.com/chart?cht=qr&chs={$size}x{$size}&chld=L|0&chl={$chl}";
}

$pid = (int)($_GET['pid'] ?? 0);
$vid = (int)($_GET['vid'] ?? 0);

if ($pid <= 0 || $vid <= 0) {
  exit('❌ Falta producto o variante.');
}

$q = $conexion->prepare("SELECT v.id, v.talle, v.color, v.medidas, p.titulo, p.precio
                         FROM ind_variantes v 
                         JOIN ind_productos p ON p.id=v.producto_id
                         WHERE v.id=? AND v.producto_id=? LIMIT 1");
$q->bind_param('ii', $vid, $pid);
$q->execute();
$res = $q->get_result();
if (!$res || !$res->num_rows) exit('❌ Variante no encontrada.');
$v = $res->fetch_assoc();

$ventaUrl = "https://{$_SERVER['HTTP_HOST']}/app/pages/venta_qr.php?pid={$pid}&vid={$vid}&sell=1";
?>
<main style="padding:1rem;text-align:center">
  <h2><?= h($v['titulo']) ?></h2>
  <p><b>Talle:</b> <?= h($v['talle']) ?> — <b>Color:</b> <?= h($v['color']) ?></p>
  <p><b>Medidas:</b> <?= h($v['medidas']) ?></p>
  <h3>$<?= number_format((float)$v['precio'],2,',','.') ?></h3>
  <img src="<?= qr_url($ventaUrl, 200) ?>" alt="QR" style="border:1px solid #ccc;border-radius:8px">
  <p><small>Escaneá este QR para vender y descontar stock</small></p>
</main>
