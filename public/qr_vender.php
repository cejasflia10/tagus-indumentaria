<?php
// public/qr_vender.php — Vender 1 de una variante al escanear QR
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../app/config.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
@$conexion->set_charset('utf8mb4');

// Clave HMAC (usá la misma al generar el QR)
$QR_SECRET = 'tagus-qr-2025';

$vid = (int)($_GET['vid'] ?? 0);
$qty = max(1, (int)($_GET['n'] ?? 1));
$sig = (string)($_GET['sig'] ?? '');

if ($vid<=0) { http_response_code(400); exit('Variante inválida'); }
if ($sig !== hash_hmac('sha256', (string)$vid, $QR_SECRET)) { http_response_code(403); exit('QR inválido'); }

$q = $conexion->query("
  SELECT v.id AS variante_id, v.producto_id, v.stock, v.talle, v.color,
         p.titulo, p.precio
  FROM ind_variantes v
  INNER JOIN ind_productos p ON p.id=v.producto_id
  WHERE v.id={$vid}
  LIMIT 1
");
if (!$q || !$q->num_rows) { http_response_code(404); exit('No existe la variante'); }
$V = $q->fetch_assoc();
if ((int)$V['stock'] < $qty) { http_response_code(409); exit('Sin stock'); }

$conexion->begin_transaction();
try{
  $st1 = $conexion->prepare("UPDATE ind_variantes SET stock=stock-? WHERE id=?");
  $st1->bind_param('ii', $qty, $vid);
  $st1->execute();

  $precio_unit = (float)$V['precio'];
  $total = $precio_unit * $qty;

  $st2 = $conexion->prepare("INSERT INTO ind_ventas (producto_id,variante_id,cantidad,precio_unit,total) VALUES (?,?,?,?,?)");
  $st2->bind_param('iiidd', $V['producto_id'], $vid, $qty, $precio_unit, $total);
  $st2->execute();

  $conexion->commit();
}catch(Throwable $e){
  $conexion->rollback();
  http_response_code(500); exit('Error al registrar la venta');
}
?>
<!doctype html>
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Venta registrada</title>
<div style="font-family:ui-sans-serif,system-ui;max-width:520px;margin:18px auto;padding:16px;border:1px solid #eee;border-radius:12px">
  <h2 style="margin:0 0 8px">✅ Venta registrada</h2>
  <div><b>Producto:</b> <?=h($V['titulo'])?></div>
  <div><b>Variante:</b> <?=h(trim(($V['talle']?:'').' '.$V['color']))?></div>
  <div><b>Cantidad:</b> <?=$qty?></div>
  <div><b>Total:</b> $ <?= number_format((float)$precio_unit*$qty,2,',','.') ?></div>
  <hr><a href="javascript:history.back()">⬅ Volver</a>
</div>
