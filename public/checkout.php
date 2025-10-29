<?php
// public/checkout.php — Público: crea ind_pedidos + items, descuenta stock e inserta en ind_ventas
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__.'/../app/config.php';
require_once __DIR__.'/partials/public_header.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
@$conexion->set_charset('utf8mb4');
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }

$cart = $_SESSION['cart'] ?? [];
if (!$cart){ header('Location: carrito.php'); exit; }

// 👉 Alias de transferencia mostrado al cliente (cámbialo aquí)
// Alias tomado de ajustes
$ALIAS = get_ajuste($conexion, 'alias_transferencia', '');

$msg=null; $ok=false; $pedido_id=null; $tel_guardado='';
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $nombre   = trim($_POST['nombre'] ?? '');
  $tel      = trim($_POST['tel'] ?? '');
  $envio    = $_POST['envio'] ?? 'retiro'; // retiro | domicilio
  $direccion= trim($_POST['direccion'] ?? '');
  $pago     = $_POST['pago'] ?? 'efectivo'; // efectivo | transferencia
  $obs      = trim($_POST['obs'] ?? '');

  if ($nombre===''){ $msg='Completá tu nombre.'; }
  elseif ($tel===''){ $msg='Completá tu teléfono.'; }
  elseif ($envio==='domicilio' && $direccion===''){ $msg='Indicá la dirección.'; }
  else {
    $conexion->begin_transaction();
    try{
      // Cabecera
      $alias_mostrado = ($pago==='transferencia') ? $ALIAS : null;
      $stmt = $conexion->prepare("INSERT INTO ind_pedidos (nombre, tel, envio, direccion, pago, alias_mostrado, obs, total, estado) VALUES (?,?,?,?,?,?,?,0.00,'pendiente')");
      $stmt->bind_param('sssssss', $nombre, $tel, $envio, $direccion, $pago, $alias_mostrado, $obs);
      if (!$stmt->execute()) throw new Exception('No se pudo crear el pedido.');
      $pedido_id = $stmt->insert_id;

      $totalPedido = 0.0;

      foreach($cart as $it){
        $vid = (int)$it['variante_id'];
        $qty = (int)$it['qty'];
        if ($qty<=0) continue;

        // Lock y datos actuales
        $r = $conexion->query("SELECT v.stock, v.talle, v.color, p.id AS producto_id, p.titulo, p.precio
                               FROM ind_variantes v JOIN ind_productos p ON p.id=v.producto_id
                               WHERE v.id={$vid} FOR UPDATE");
        if (!$r || !$r->num_rows) throw new Exception('Variante no encontrada.');
        $row = $r->fetch_assoc();
        if ((int)$row['stock'] < $qty) throw new Exception('Stock insuficiente para alguna variante.');

        $precio = (float)$row['precio'];
        $ppid   = (int)$row['producto_id'];
        $titulo = (string)$row['titulo'];
        $talle  = (string)($row['talle'] ?? '');
        $color  = (string)($row['color'] ?? '');

        // Item
        $totItem = $precio * $qty;
        $totalPedido += $totItem;
        $stmtIt = $conexion->prepare("INSERT INTO ind_pedido_items (pedido_id, producto_id, variante_id, titulo, color, talle, precio_unit, cantidad, total) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmtIt->bind_param('iiisssidd', $pedido_id, $ppid, $vid, $titulo, $color, $talle, $precio, $qty, $totItem);
        if (!$stmtIt->execute()) throw new Exception('No se pudo guardar un item.');

        // Histórico simple
        $stmtV = $conexion->prepare("INSERT INTO ind_ventas (producto_id, variante_id, cantidad, precio_unit, total) VALUES (?,?,?,?,?)");
        $stmtV->bind_param('iiidd', $ppid, $vid, $qty, $precio, $totItem);
        if (!$stmtV->execute()) throw new Exception('No se pudo registrar la venta.');

        // Descontar stock
        $stmtS = $conexion->prepare("UPDATE ind_variantes SET stock = stock - ? WHERE id=?");
        $stmtS->bind_param('ii', $qty, $vid);
        if (!$stmtS->execute()) throw new Exception('No se pudo descontar stock.');
      }

      // Total
      $stmtU = $conexion->prepare("UPDATE ind_pedidos SET total=? WHERE id=?");
      $stmtU->bind_param('di', $totalPedido, $pedido_id);
      if (!$stmtU->execute()) throw new Exception('No se pudo cerrar el pedido.');

      $conexion->commit();
      $ok = true;
      $tel_guardado = $tel;
      $_SESSION['cart'] = []; // Vaciar carrito
    } catch (Throwable $e){
      $conexion->rollback();
      $msg = 'Error: '.$e->getMessage();
    }
  }
}
?>
<div class="container">
  <div class="card">
    <div class="card-header">Checkout</div>
    <div class="card-body">
      <?php if ($ok): ?>
        <div class="card" style="border-color:#d1fae5;margin-bottom:10px"><div class="card-body">
          <strong>✅ Pedido #<?= (int)$pedido_id ?> registrado.</strong>
          <?php if (($pago ?? '')==='transferencia'): ?>
            <p class="mt-2">Transferí al <strong>ALIAS: <?=h($ALIAS)?></strong> y enviá el comprobante por WhatsApp.</p>
          <?php else: ?>
            <p class="mt-2">Pagás en <strong>efectivo</strong> al retirar o al recibir.</p>
          <?php endif; ?>
          <p class="mt-2"><a class="btn btn-muted" href="mis_pedidos.php?tel=<?= urlencode($tel_guardado) ?>">📦 Ver mis pedidos</a></p>
        </div></div>
        <a class="btn btn-primary" href="tienda.php">Volver a la Tienda</a>
      <?php else: ?>
        <?php if ($msg): ?><div class="card" style="border-color:#fee2e2;margin-bottom:10px"><div class="card-body">❌ <?=h($msg)?></div></div><?php endif; ?>

        <form method="post" class="row cols-2">
          <div><label>Nombre y Apellido</label><input class="input" type="text" name="nombre" required></div>
          <div><label>Teléfono</label><input class="input" type="tel" name="tel" inputmode="tel" placeholder="+54..." required></div>

          <div>
            <label>Envío</label>
            <select class="input" name="envio" id="selEnvio">
              <option value="retiro">Retiro en local</option>
              <option value="domicilio">Envío a domicilio</option>
            </select>
          </div>
          <div id="dirBox" style="display:none">
            <label>Dirección</label>
            <input class="input" type="text" name="direccion" id="inpDir" placeholder="Calle, nro, ciudad">
          </div>

          <div>
            <label>Medio de pago</label>
            <select class="input" name="pago" id="selPago">
              <option value="efectivo">Efectivo</option>
              <option value="transferencia">Transferencia</option>
            </select>
          </div>
          <div id="aliasBox" style="display:none">
            <label>Alias para transferir</label>
            <input class="input" type="text" value="<?=$ALIAS?>" readonly>
          </div>

          <div style="grid-column:1/-1">
            <label>Observaciones</label>
            <textarea class="input" name="obs" rows="2" placeholder="Color específico, horario, etc."></textarea>
          </div>

          <div class="mt-3" style="grid-column:1/-1">
            <button class="btn btn-primary" type="submit">Confirmar pedido</button>
            <a class="btn btn-muted" href="carrito.php">Volver al carrito</a>
          </div>
        </form>

        <script>
        const selEnvio = document.getElementById('selEnvio');
        const dirBox   = document.getElementById('dirBox');
        const inpDir   = document.getElementById('inpDir');
        const selPago  = document.getElementById('selPago');
        const aliasBox = document.getElementById('aliasBox');
        function sync(){
          const e = selEnvio.value === 'domicilio';
          dirBox.style.display = e ? '' : 'none';
          if (inpDir) inpDir.required = e;
          aliasBox.style.display = selPago.value === 'transferencia' ? '' : 'none';
        }
        selEnvio.addEventListener('change', sync);
        selPago.addEventListener('change', sync);
        sync();
        </script>
      <?php endif; ?>
    </div>
  </div>
</div>
