<?php
// public/ventas.php — Listado simple de ventas (últimas 100)
// Usa $conexion desde app/config.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/partials/menu.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

$sql = "
  SELECT v.id, v.created_at, v.cantidad, v.precio_unit, v.total,
         p.titulo,
         iv.talle, iv.color
  FROM ind_ventas v
  LEFT JOIN ind_productos p ON p.id = v.producto_id
  LEFT JOIN ind_variantes iv ON iv.id = v.variante_id
  ORDER BY v.id DESC
  LIMIT 100
";
$res = $conexion->query($sql);
?>
<div class="container">
  <div class="card">
    <div class="card-header">Pedidos / Ventas</div>
    <div class="card-body">
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>#</th>
              <th>Fecha</th>
              <th>Producto</th>
              <th>Talle</th>
              <th>Color</th>
              <th>Cant.</th>
              <th>Precio</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($res && $res->num_rows): ?>
              <?php while($row = $res->fetch_assoc()): ?>
                <tr>
                  <td><?= (int)$row['id'] ?></td>
                  <td><?= h($row['created_at']) ?></td>
                  <td><?= h($row['titulo'] ?? '—') ?></td>
                  <td><?= h($row['talle'] ?? '—') ?></td>
                  <td><?= h($row['color'] ?? '—') ?></td>
                  <td><?= (int)$row['cantidad'] ?></td>
                  <td>$<?= number_format((float)$row['precio_unit'], 2, ',', '.') ?></td>
                  <td>$<?= number_format((float)$row['total'], 2, ',', '.') ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="8" class="muted">Aún no hay ventas.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
