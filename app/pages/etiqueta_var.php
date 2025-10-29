<?php
// public/etiqueta_var.php — Etiqueta PNG con QR + precio + datos de variante
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../app/config.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500); exit('Sin BD');
}
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function n2($v){ return number_format((float)$v, 2, ',', '.'); }

/* Params */
$pid = (int)($_GET['pid'] ?? 0);
$vid = (int)($_GET['vid'] ?? 0);
if ($pid<=0 || $vid<=0) { http_response_code(400); exit('Faltan parámetros pid/vid'); }

/* Datos producto + variante */
$pq = $conexion->query("SELECT id,titulo,precio,categoria FROM ind_productos WHERE id={$pid} LIMIT 1");
$vq = $conexion->query("SELECT id,talle,color,medidas,stock FROM ind_variantes WHERE id={$vid} AND producto_id={$pid} LIMIT 1");
if (!$pq || !$vq || !$pq->num_rows || !$vq->num_rows) { http_response_code(404); exit('Producto/Variante no encontrado'); }
$prod = $pq->fetch_assoc();
$var  = $vq->fetch_assoc();

/* URL de venta por QR (escaneo para descontar stock y registrar) */
$scriptDir = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$BASE = preg_replace('#/public$#', '', $scriptDir); if ($BASE === '') $BASE = '/';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$sellUrl = $scheme.$host. rtrim($BASE,'/') . '/app/pages/venta_qr.php?pid='.$pid.'&vid='.$vid.'&sell=1';

/* Descargar QR (Google Chart) */
$qrSize = 380; // nítido para imprimir
$qrUrl = "https://chart.googleapis.com/chart?cht=qr&chs={$qrSize}x{$qrSize}&chld=L|0&chl=".rawurlencode($sellUrl);
$qrData = @file_get_contents($qrUrl);
$qrImg  = $qrData ? @imagecreatefromstring($qrData) : null;

/* Crear lienzo */
$W = 800; $H = 600; // horizontal
$im = imagecreatetruecolor($W, $H);
$white = imagecolorallocate($im, 255,255,255);
$black = imagecolorallocate($im, 0,0,0);
$gray  = imagecolorallocate($im, 80,80,80);
$blue  = imagecolorallocate($im, 13,110,253);
imagefilledrectangle($im, 0, 0, $W, $H, $white);

/* Margenes */
$pad = 28;

/* Título (límites de 40–48 char aprox.) usando fuentes GD internas */
$title = (string)($prod['titulo'] ?? '');
$price = '$ '.n2($prod['precio'] ?? 0);
$talle = trim((string)($var['talle'] ?? ''));
$color = trim((string)($var['color'] ?? ''));
$med   = trim((string)($var['medidas'] ?? ''));
$line1 = $title;
$line2 = ($talle!=='' ? "Talle: $talle" : "Talle: —") . "   ·   " . ($color!=='' ? "Color: $color" : "Color: —");
$line3 = ($med!=='' ? "Medidas: $med" : "Medidas: —");
$line4 = "ID Var: ".$vid."   ·   Stock: ".(int)($var['stock'] ?? 0);

/* Bloque izquierdo: texto */
$y = $pad;
imagestring($im, 5, $pad, $y,    $line1, $black); $y += 26;
imagestring($im, 3, $pad, $y,    $line2, $gray);  $y += 20;
imagestring($im, 3, $pad, $y,    $line3, $gray);  $y += 20;
imagestring($im, 3, $pad, $y,    $line4, $gray);  $y += 28;

/* Precio grande */
$priceFont = 5;
$priceW = imagefontwidth($priceFont) * strlen($price);
$priceH = imagefontheight($priceFont);
$bx = $pad; $by = $y + 6;
imagestring($im, 5, $bx, $by, $price, $blue);
$y += $priceH + 18;

/* Bloque derecho: QR */
if ($qrImg) {
  $qrW = imagesx($qrImg); $qrH = imagesy($qrImg);
  // Centrado vertical
  $dstW = 380; $dstH = 380;
  $dstX = $W - $pad - $dstW;
  $dstY = ($H - $dstH) / 2;
  imagecopyresampled($im, $qrImg, $dstX, (int)$dstY, 0, 0, $dstW, $dstH, $qrW, $qrH);
  imagedestroy($qrImg);
}

/* Pie: URL corta visible */
$disp = (strlen($sellUrl) > 64) ? substr($sellUrl,0,64).'…' : $sellUrl;
imagestring($im, 2, $pad, $H - $pad - 14, $disp, $gray);

/* Output PNG */
header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
imagepng($im);
imagedestroy($im);
