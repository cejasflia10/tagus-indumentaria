<?php
/* public_header.php — Header público (solo Tienda / Carrito / Mis pedidos) */
/* NO declaramos h() acá: ya viene desde app/config.php */

$scriptDir = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$BASE = preg_replace('#/public$#', '', $scriptDir);
if ($BASE === '') $BASE = '/';

$hrefTienda   = rtrim($BASE, '/').'/public/tienda.php';
$hrefCarrito  = rtrim($BASE, '/').'/public/carrito.php';
$hrefPedidosC = rtrim($BASE, '/').'/public/mis_pedidos.php';

$assetCss  = rtrim($BASE, '/').'/public/assets/style.css';
$assetLogo = rtrim($BASE, '/').'/public/assets/logo.png';
$current   = basename($_SERVER['PHP_SELF'] ?? '');
function active_pub($file){ return basename($_SERVER['PHP_SELF'] ?? '') === $file ? 'active' : ''; }
?>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<link rel="stylesheet" href="<?= h($assetCss) ?>">

<div class="navbar">
  <div class="navbar-inner">
    <a class="brand" href="<?= h($hrefTienda) ?>" aria-label="Inicio Tienda">
      <img src="<?= h($assetLogo) ?>" alt="TAGUS"><span>TAGUS</span>
    </a>
    <nav class="nav" role="navigation" aria-label="Tienda">
      <a class="<?=active_pub('tienda.php')?>"       href="<?= h($hrefTienda) ?>">Tienda</a>
      <a class="<?=active_pub('carrito.php')?>"      href="<?= h($hrefCarrito) ?>">Carrito</a>
      <a class="<?=active_pub('mis_pedidos.php')?>"  href="<?= h($hrefPedidosC) ?>">Mis pedidos</a>
    </nav>
  </div>
</div>
