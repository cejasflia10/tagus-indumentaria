<?php
// public/stock.php — Existencias por variante con ajuste +/-
// Usa $conexion desde app/config.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/partials/menu.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* === Ajuste de stock === */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $variante_id = (int)($_POST['variante_id'] ?? 0);
  $delta       = (int)($_POST['delta'] ?? 0);
  if ($variante_id && $delta) {
    $stmt = $conexion->prepare("UPDATE ind_variantes SET stock = GREATEST(0, stock + ?) WHERE id = ?");
    $stmt->bind_param('ii', $delta, $variante_id);
    $stmt->execute();
  }
  header('Location: '.$_SERVER['REQUEST_URI']);
  exit;
}

/* === Búsqueda simple === */
$q = trim($_GET['q'] ?? '');
$like = '%'.$conexion->real_escape_string($q).'%';
$sql = "
  SELECT v.id as variante_id, v.stock, v.talle, v.color, v.medidas,
         p.id as producto_id, p.titulo, p.precio
  FROM ind_variantes v
  JOIN ind_productos p ON p.id = v.producto_id
  ".($q !== '' ? "WHERE p.titulo LIKE '{$like}' OR v.color LIKE '{$like}' OR v.talle LIKE '{$like}'" : '')."
  ORDER BY p.titulo ASC, v.color ASC, v.talle ASC
  LIMIT 500
";
$res = $conexion->query($sql);
?>
<div class="container">
  <div class="card">
    <div class="card-header">Stock</div>
    <div class="card-body">
      <form method="get" class="row cols-2">
        <div>
          <label>Buscar</label>
          <input class="input" type="text" name="q" value="<?= h($q) ?>" placeholder="Producto, color o talle">
        </div>
        <div style="align-self:end">
          <button class="btn btn-primary" type="submit">Buscar</button>
          <a class="btn btn-muted" href="?">Limpiar</a>
        </div>
      </form>

      <div class="table-wrap mt-3">
        <table class="table">
          <thead>
            <tr>
              <th>Producto</th>
              <th>Talle</th>
              <th>Color</th>
              <th>Medidas</th>
              <th>Precio</th>
              <th>Stock</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($res && $res->num_rows): ?>
              <?php while($r = $res->fetch_assoc()): ?>
                <tr>
                  <td><?= h($r['titulo']) ?></td>
                  <td><?= h($r['talle'] ?: '—') ?></td>
                  <td><?= h($r['color'] ?: '—') ?></td>
                  <td><?= h($r['medidas'] ?: '—') ?></td>
                  <td>$<?= number_format((float)$r['precio'], 2, ',', '.') ?></td>
                  <td><strong><?= (int)$r['stock'] ?></strong></td>
                  <td>
                    <form method="post" style="display:inline">
                      <input type="hidden" name="variante_id" value="<?= (int)$r['variante_id'] ?>">
                      <input type="hidden" name="delta" value="-1">
                      <button class="btn btn-muted" type="submit">−1</button>
                    </form>
                    <form method="post" style="display:inline">
                      <input type="hidden" name="variante_id" value="<?= (int)$r['variante_id'] ?>">
                      <input type="hidden" name="delta" value="1">
                      <button class="btn btn-primary" type="submit">+1</button>
                    </form>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="7" class="muted">No hay variantes para mostrar.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
