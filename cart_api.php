<?php
// cart_api.php — carrito mínimo en sesión (demo)
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/app/bootstrap.php'; // por si luego querés validar contra BD

$action = $_POST['action'] ?? '';
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];  // array de líneas

function ok($msg, $extra=[]){ echo json_encode(['ok'=>1,'msg'=>$msg]+$extra); exit; }
function bad($msg, $code=400){ http_response_code($code); echo json_encode(['ok'=>0,'msg'=>$msg]); exit; }

if ($action === 'add') {
  $variante_id = $_POST['variante_id'] ?? '';
  $producto_id = (int)($_POST['producto_id'] ?? 0);
  $qty         = (int)($_POST['cantidad'] ?? 1);
  if ($qty < 1) $qty = 1;

  $key = '';
  if ($variante_id !== '') {
    $key = 'var:' . $variante_id;              // puede ser numérico o "prod:ID"
  } elseif ($producto_id > 0) {
    $key = 'prod:' . $producto_id;
  } else {
    bad('Falta producto/variante');
  }

  if (!isset($_SESSION['cart'][$key])) {
    $_SESSION['cart'][$key] = ['key'=>$key, 'qty'=>0];
  }
  $_SESSION['cart'][$key]['qty'] += $qty;

  ok('Agregado al carrito', ['items'=>array_values($_SESSION['cart'])]);
}

if ($action === 'clear') {
  $_SESSION['cart'] = [];
  ok('Carrito vacío');
}

bad('Acción no soportada', 405);
