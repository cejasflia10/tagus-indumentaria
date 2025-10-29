<?php
// index.php — Inicio seguro (solo enlaces a páginas que EXISTEN + acceso a QR)
// ---------------------------------------------------------------------------------
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

// Config (si define $conexion/h(), genial; si no, defino h() abajo)
require_once __DIR__ . '/app/config.php';

// Helper h() si no existe
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

/* ===== BASE URL para armar hrefs ===== */
$scriptDir = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$BASE = preg_replace('#/public$#', '', $scriptDir);
if ($BASE === '') $BASE = '/';
$u = fn(string $p) => rtrim($BASE,'/').'/'.ltrim($p,'/');

/* ===== Rutas de FS para verificar existencia ===== */
$FS_ROOT   = __DIR__;
$FS_PUBLIC = $FS_ROOT . '/public';
$FS_PAGES  = $FS_ROOT . '/app/pages';

/* ===== Items del Home (label, url, fs_path, icon) =====
   Agregué:
   - Escanear venta (QR): public/scan_venta.php
   - Venta por QR (directo): app/pages/venta_qr.php
*/
$items = [
  ['Tienda',              $u('public/tienda.php'),            $FS_PUBLIC.'/tienda.php',            '🛍️'],
  ['Carrito',             $u('public/carrito.php'),           $FS_PUBLIC.'/carrito.php',           '🧺'],
  ['Crear producto / QR', $u('public/crear_producto.php'),     $FS_PUBLIC.'/crear_producto.php',    '➕'],
  ['Admin indumentaria',  $u('app/pages/admin_indum.php'),     $FS_PAGES .'/admin_indum.php',       '🧵'],
  ['Stock',               $u('public/stock.php'),              $FS_PUBLIC.'/stock.php',             '📦'],
  ['Ventas',              $u('public/ventas.php'),             $FS_PUBLIC.'/ventas.php',            '💳'],
  ['Pedidos',             $u('public/pedidos.php'),            $FS_PUBLIC.'/pedidos.php',           '📬'],
  ['Contable',            $u('public/contable.php'),           $FS_PUBLIC.'/contable.php',          '📒'],
  ['Ajustes',             $u('public/ajustes_tagus.php'),      $FS_PUBLIC.'/ajustes_tagus.php',     '⚙️'],

  // === NUEVOS: QR ===
  ['Escanear venta (QR)', $u('public/scan_venta.php'),         $FS_PUBLIC.'/scan_venta.php',        '📷'],
  ['Venta por QR',        $u('app/pages/venta_qr.php') . '?pid=1&vid=1&sell=1', $FS_PAGES .'/venta_qr.php', '🧾'],
];

/* ===== Navbar (tema blanco) ===== */
require_once __DIR__ . '/public/partials/menu.php';
?>
<style>
  .home-wrap{max-width:1100px;margin:18px auto;padding:0 16px}
  .grid-cards{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
  @media(min-width:760px){.grid-cards{grid-template-columns:repeat(3,1fr)}}
  @media(min-width:1040px){.grid-cards{grid-template-columns:repeat(4,1fr)}}
  .card-link{
    display:flex;align-items:center;gap:10px;padding:16px;border:1px solid var(--border);
    border-radius:14px;background:#fff;box-shadow:var(--shadow);text-decoration:none
  }
  .card-link strong{color:#111 !important}
  .card-link:hover{background:#f8fafc}
  .card-disabled{opacity:.45;pointer-events:none}
  .muted{color:#6b7280}
</style>

<div class="home-wrap">
  <h1>Inicio</h1>
  <p class="muted" style="margin:6px 0 16px">Accesos rápidos a los módulos disponibles.</p>

  <div class="grid-cards">
    <?php foreach ($items as [$label,$href,$fs,$icon]): ?>
      <?php $exists = is_file($fs); ?>
      <a class="card-link <?= $exists ? '' : 'card-disabled' ?>" href="<?= h($href) ?>">
        <span style="font-size:22px;line-height:1"><?= $icon ?></span>
        <div>
          <strong><?= h($label) ?></strong>
          <div class="muted" style="font-size:.9rem">
            <?= $exists ? 'Abrir' : 'Archivo no encontrado: '.h(str_replace($FS_ROOT.'/','',$fs)) ?>
          </div>
        </div>
      </a>
    <?php endforeach; ?>
  </div>

  <div style="margin-top:18px" class="muted">
    Si algún botón aparece deshabilitado, creá el archivo indicado en <code>/public</code> o <code>/app/pages</code>.
  </div>
</div>
