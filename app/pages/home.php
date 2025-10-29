<?php
// app/pages/home.php — Portada blanca con menú unificado + FAB móvil
declare(strict_types=1);
require_once __DIR__ . '/../../public/partials/menu.php';
?>
<div class="container">
  <div class="card">
    <div class="card-header">TAGUS — Indumentaria</div>
    <div class="card-body">
      <p class="muted">Fotos en Cloudinary y variantes (talle/color/medidas). QR por variante y venta rápida por escaneo.</p>

      <div class="mt-3">
        <a class="btn btn-primary" href="/TAGUS/public/crear_producto.php">➕ Crear producto / Generar QR</a>
        <a class="btn btn-muted" href="/TAGUS/public/ventas.php">📦 Ver ventas</a>
      </div>

      <h2 class="mt-4">Últimos productos</h2>
      <div class="muted mt-2">Aún no hay productos. Cargá desde “Crear producto / Generar QR”.</div>
    </div>
  </div>
</div>

<!-- FAB solo móvil -->
<a class="fab" href="/TAGUS/public/crear_producto.php" aria-label="Crear producto">
  ➕ <span>Crear</span>
</a>
