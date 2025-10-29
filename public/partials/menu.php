<?php

/* partials/menu.php — Navbar blanco (auto BASE_URL) */
$current = basename($_SERVER['PHP_SELF'] ?? '');
$scriptDir = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$BASE = preg_replace('#/public$#', '', $scriptDir);
if ($BASE === '') $BASE = '/';

/* ... autodetección BASE igual que ya tenés ... */
$hrefIndex   = rtrim($BASE, '/').'/index.php';
$hrefTienda  = rtrim($BASE, '/').'/public/tienda.php';
$hrefCarrito = rtrim($BASE, '/').'/public/carrito.php';
$hrefCrear   = rtrim($BASE, '/').'/public/crear_producto.php';
$hrefVentas  = rtrim($BASE, '/').'/public/ventas.php';
$hrefStock   = rtrim($BASE, '/').'/public/stock.php';
$hrefContab  = rtrim($BASE, '/').'/public/contable.php';
$hrefSalir   = rtrim($BASE, '/').'/public/salir.php';
$hrefPedidos = rtrim($BASE, '/').'/public/pedidos.php';
$hrefAjustes = rtrim($BASE, '/').'/public/ajustes_tagus.php';

$assetCss    = rtrim($BASE, '/').'/public/assets/style.css';
$assetLogo   = rtrim($BASE, '/').'/public/assets/logo.png'; // tu logo.png

function active($file){ return basename($_SERVER['PHP_SELF'] ?? '') === $file ? 'active' : ''; }
?>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<link rel="stylesheet" href="<?= htmlspecialchars($assetCss) ?>">

<div class="navbar">
  <div class="navbar-inner">
    <a class="brand" href="<?= htmlspecialchars($hrefIndex) ?>" aria-label="Inicio">
      <img src="<?= htmlspecialchars($assetLogo) ?>" alt="TAGUS"><span></span>
    </a>

<nav class="nav" role="navigation" aria-label="Principal">
  <a class="<?=active('index.php')?>"           href="<?=h($hrefIndex)?>">Inicio</a>
  <a class="<?=active('tienda.php')?>"          href="<?=h($hrefTienda)?>">Tienda</a>
  <a class="<?=active('carrito.php')?>"         href="<?=h($hrefCarrito)?>">Carrito</a>
  <a class="<?=active('crear_producto.php')?>"  href="<?=h($hrefCrear)?>">Crear producto / QR</a>
  <a class="<?=active('ventas.php')?>"          href="<?=h($hrefVentas)?>">Ventas</a>
  <a class="<?=active('stock.php')?>"           href="<?=h($hrefStock)?>">Stock</a>
  <a class="<?=active('contable.php')?>"        href="<?=h($hrefContab)?>">Contable</a>
  <a class="<?=active('pedidos.php')?>"        href="<?=h($hrefPedidos)?>">Pedidos</a>
<a class="<?=active('ajustes_tagus.php')?>" href="<?= h($hrefAjustes) ?>">Ajustes</a>

  <a href="<?=h($hrefSalir)?>">Salir</a>
</nav>
  </div>
</div>
