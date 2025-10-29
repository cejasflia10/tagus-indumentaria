<?php
// public/carrito.php — Carrito público en sesión + actualizar/eliminar
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__.'/../app/config.php';
require_once __DIR__.'/partials/public_header.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }

$_SESSION['cart'] = $_SESSION['cart'] ?? []; // cart[vid] = ['variante_id','producto_id','titulo','talle','color','precio','qty']

/* Acciones */
$action = $_POST['action'] ?? $_GET['action'] ?? '';
if ($action === 'add') {
  $variante_id = (int)($_POST['variante_id'] ?? 0);
  $cantidad    = max(1, (int)($_POST['cantidad'] ?? 1));

  $sql = "SELECT v.id as variante_id, v.stock, v.talle, v.color,
                 p.id as producto_id, p.titulo, p.precio
          FROM ind_variantes v
          JOIN ind_productos p ON p.id=v.producto_id
          WHERE v.id={$variante_id} LIMIT 1";
  $r = $conexion->query($sql);
  if ($r && $r->num_rows) {
    $row = $r->fetch_assoc();
    if ((int)$row['stock'] <= 0) {
      $_SESSION['flash'] = 'Sin stock disponible para esa variante.';
    } else {
      $key = (int)$row['variante_id'];
      if (!isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key] = [
          'variante_id'=>$key,
          'producto_id'=>(int)$row['producto_id'],
          'titulo'=>$row['titulo'],
          'talle'=>$row['talle'],
          'color'=>$row['color'],
          'precio'=>(float)$row['precio'],
          'qty'=>0,
        ];
      }
      $_SESSION['cart'][$key]['qty'] = min((int)$row['stock'], $_SESSION['cart'][$key]['qty'] + $cantidad);
      $_SESSION['flash'] = 'Producto agregado al carrito.';
    }
  } else {
    $_SESSION['flash'] = 'Variante inválida.';
  }
  header('Location: carrito.php'); exit;
}

if ($action === 'set') {
  $vid = (int)($_POST['variante_id'] ?? 0);
  $qty = max(0, (int)($_POST['qty'] ?? 0));
  if (isset($_SESSION['cart'][$vid])) {
    if ($qty === 0) unset($_SESSION['cart'][$vid]);
    else $_SESSION['cart'][$vid]['qty'] = $qty;
  }
  header('Location: carrito.php'); exit;
}

if ($action === 'clear') {
  $_SESSION['cart'] = [];
  header('Location: carrito.php'); exit;
}

$items = array_values($_SESSION['cart']);
$subtotal = 0.0; foreach($items as $it){ $subtotal += $it['precio'] * $it['qty']; }
?>
<div class="container">
  <div class="card">
    <div class="card-header">Carrito</div>
    <div class="card-body">
      <?php if (!empty($_SESSION['flash'])): ?>
        <div class="card" style="border-color:#d1fae5;margin-bottom:10px"><div class="card-body"><?=h($_SESSION['flash'])?></div></div>
        <?php $_SESSION['flash']=null; ?>
      <?php endif; ?>

      <?php if (!$items): ?>
        <div class="muted">Tu carrito está vacío.</div>
        <div class="mt-3"><a class="btn btn-primary" href="tienda.php">Ir a la Tienda</a></div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Producto</th><th>Variante</th><th>Precio</th><th>Cant.</th><th>Total</th><th></th></tr></thead>
            <tbody>
              <?php foreach($items as $it): $total = $it['precio']*$it['qty']; ?>
                <tr>
                  <td><?=h($it['titulo'])?></td>
                  <td><?=h(($it['color']?:'') . ' ' . ($it['talle']?:''))?></td>
                  <td>$<?=number_format($it['precio'],2,',','.')?></td>
                  <td>
                    <form method="post" action="carrito.php" style="display:inline-flex; gap:6px">
                      <input type="hidden" name="action" value="set">
                      <input type="hidden" name="variante_id" value="<?=$it['variante_id']?>">
                      <input class="input" style="max-width:90px" type="number" min="0" name="qty" value="<?=$it['qty']?>" inputmode="numeric">
                      <button class="btn btn-primary">OK</button>
                    </form>
                  </td>
                  <td>$<?=number_format($total,2,',','.')?></td>
                  <td>
                    <form method="post" action="carrito.php">
                      <input type="hidden" name="action" value="set">
                      <input type="hidden" name="variante_id" value="<?=$it['variante_id']?>">
                      <input type="hidden" name="qty" value="0">
                      <button class="btn btn-danger">Quitar</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <tr><td colspan="4" style="text-align:right"><strong>Subtotal</strong></td><td><strong>$<?=number_format($subtotal,2,',','.')?></strong></td><td></td></tr>
            </tbody>
          </table>
        </div>

        <div class="mt-3" style="display:flex; gap:8px; flex-wrap:wrap">
          <a class="btn btn-muted" href="tienda.php">Seguir comprando</a>
          <a class="btn btn-primary" href="checkout.php">Finalizar compra</a>
          <form method="post" action="carrito.php" style="display:inline">
            <input type="hidden" name="action" value="clear"><button class="btn btn-muted">Vaciar</button>
          <?php $ALIAS = get_ajuste($conexion, 'alias_transferencia', '');?>
<?php if ($ALIAS): ?>
  <div class="card" style="margin-top:12px;border-color:#e5e7eb">
    <div class="card-body">
      <strong>¿Vas a pagar por transferencia?</strong><br>
      El alias se mostrará en el próximo paso (Checkout). Alias actual: <code><?= h($ALIAS) ?></code>
    </div>
  </div>
<?php endif; ?>

        </form>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
