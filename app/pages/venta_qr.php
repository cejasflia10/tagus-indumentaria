<?php
/* ============================================================
   venta_qr.php — Vista de venta rápida por QR (Tagus Indumentaria)
   ============================================================ */
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// ✅ Rutas correctas
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../../public/partials/menu.php';

@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }

// --- Variables principales
$pid = (int)($_GET['pid'] ?? 0);
$vid = (int)($_GET['vid'] ?? 0);
$sell = isset($_GET['sell']);
$msg = '';

if ($pid <= 0 || $vid <= 0) {
  http_response_code(400);
  exit('❌ Parámetros inválidos');
}

$q = $conexion->prepare("SELECT v.id, v.stock, p.titulo, v.talle, v.color, v.medidas 
                         FROM ind_variantes v 
                         JOIN ind_productos p ON p.id=v.producto_id 
                         WHERE v.id=? AND v.producto_id=? LIMIT 1");
$q->bind_param('ii', $vid, $pid);
$q->execute();
$r = $q->get_result();
if (!$r || !$r->num_rows) {
  exit('❌ Variante no encontrada.');
}
$v = $r->fetch_assoc();

if ($sell) {
  if ((int)$v['stock'] > 0) {
    $conexion->query("UPDATE ind_variantes SET stock=GREATEST(0,stock-1) WHERE id={$vid}");
    $msg = "✅ Venta registrada. Stock actualizado.";
  } else {
    $msg = "⚠️ Sin stock disponible.";
  }
}

?>
<main class="container" style="padding:1rem;text-align:center">
  <h2><?= h($v['titulo']) ?></h2>
  <p><b>Talle:</b> <?= h($v['talle']) ?> — <b>Color:</b> <?= h($v['color']) ?></p>
  <p><b>Medidas:</b> <?= h($v['medidas']) ?></p>
  <p><b>Stock actual:</b> <?= (int)$v['stock'] ?></p>
  <hr>
  <h3><?= h($msg ?: 'Escaneo de QR válido') ?></h3>
  <a href="../../pages/admin_indum.php" class="btn">Volver al panel</a>
</main>
