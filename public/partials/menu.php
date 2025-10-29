<?php
/* partials/menu.php — Navbar blanco (auto BASE_URL + Admin Indumentaria) */
if (session_status() === PHP_SESSION_NONE) session_start();

/* Helper mínimo */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

/* Página actual para marcar activo */
$current = basename($_SERVER['PHP_SELF'] ?? '');

/* === Cálculo BASE robusto ===
   - Si el script está en /public/... -> BASE = raíz del sitio
   - Si está en /app/...           -> BASE = raíz del sitio
   - En otros casos, deja dirname tal cual
*/
$scriptDir = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$BASE = preg_replace('#/(public|app)(/.*)?$#', '', $scriptDir);
if ($BASE === '') $BASE = '/';

/* Rutas absolutas desde la raíz detectada */
$hrefIndex        = rtrim($BASE, '/').'/index.php';
$hrefTienda       = rtrim($BASE, '/').'/public/tienda.php';
$hrefCarrito      = rtrim($BASE, '/').'/public/carrito.php';
$hrefCrear        = rtrim($BASE, '/').'/public/crear_producto.php';
$hrefVentas       = rtrim($BASE, '/').'/public/ventas.php';
$hrefStock        = rtrim($BASE, '/').'/public/stock.php';
$hrefContab       = rtrim($BASE, '/').'/public/contable.php';
$hrefPedidos      = rtrim($BASE, '/').'/public/pedidos.php';
$hrefAjustes      = rtrim($BASE, '/').'/public/ajustes_tagus.php';
$hrefSalir        = rtrim($BASE, '/').'/public/salir.php';

/* 🚀 Admin Indumentaria (panel completo bajo /app/pages) */
$hrefAdminIndum   = rtrim($BASE, '/').'/app/pages/admin_indum.php';

/* Assets SIEMPRE desde /public */
$assetCss         = rtrim($BASE, '/').'/public/assets/style.css';
$assetLogo        = rtrim($BASE, '/').'/public/assets/logo.png';

/* Activo */
function active(string $file){ return (basename($_SERVER['PHP_SELF'] ?? '') === $file) ? 'active' : ''; }

/* (Opcional) regla simple de admin; por defecto mostramos el link */
$is_admin = !empty($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
?>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<link rel="stylesheet" href="<?= h($assetCss) ?>">

<style>
/* Forzar menú en negro siempre (por si el CSS global no cargó) */
.navbar a, .navbar button { color:#000 !important; font-weight:700 !important; text-decoration:none; }
.navbar a:hover, .navbar button:hover { color:#000 !important; opacity:.85; }
.navbar a.active { color:#000 !important; background:#eaf2ff; border-radius:10px; }
.navbar{background:#fff !important;border-bottom:1px solid #e5e7eb;position:sticky;top:0;z-index:50}
.navbar-inner{display:flex;align-items:center;gap:12px;padding:10px 12px}
.brand{display:flex;align-items:center;gap:10px;font-weight:800}
.brand img{height:64px;width:auto;display:block;object-fit:contain;object-position:center}
@media (min-width:840px){ .brand img{height:80px} }
.nav{display:flex;gap:8px;flex:1 1 auto;overflow-x:auto;scrollbar-width:none;-ms-overflow-style:none}
.nav::-webkit-scrollbar{display:none}
.nav a{white-space:nowrap;padding:10px 12px;border:1px solid transparent;min-height:44px;display:inline-flex;align-items:center}
</style>

<div class="navbar">
  <div class="navbar-inner">
    <a class="brand" href="<?= h($hrefIndex) ?>" aria-label="Inicio">
      <img src="<?= h($assetLogo) ?>" alt="TAGUS"><span></span>
    </a>

    <nav class="nav" role="navigation" aria-label="Principal">
      <a class="<?= active('index.php')           ?>" href="<?= h($hrefIndex)   ?>">Inicio</a>
      <a class="<?= active('tienda.php')          ?>" href="<?= h($hrefTienda)  ?>">Tienda</a>
      <a class="<?= active('carrito.php')         ?>" href="<?= h($hrefCarrito) ?>">Carrito</a>
      <a class="<?= active('crear_producto.php')  ?>" href="<?= h($hrefCrear)   ?>">Crear producto / QR</a>
      <a class="<?= active('ventas.php')          ?>" href="<?= h($hrefVentas)  ?>">Ventas</a>
      <a class="<?= active('stock.php')           ?>" href="<?= h($hrefStock)   ?>">Stock</a>
      <a class="<?= active('contable.php')        ?>" href="<?= h($hrefContab)  ?>">Contable</a>
      <a class="<?= active('pedidos.php')         ?>" href="<?= h($hrefPedidos) ?>">Pedidos</a>
      <a class="<?= active('ajustes_tagus.php')   ?>" href="<?= h($hrefAjustes) ?>">Ajustes</a>

      <!-- Admin Indumentaria -->
      <a class="<?= active('admin_indum.php')     ?>" href="<?= h($hrefAdminIndum) ?>">🧵 Admin Indumentaria</a>

      <a href="<?= h($hrefSalir) ?>">Salir</a>
    </nav>
  </div>
</div>
