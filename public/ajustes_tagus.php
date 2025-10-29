<?php
// public/ajustes_tagus.php — Ajustes básicos: alias de transferencia
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__.'/../app/config.php';
require_once __DIR__.'/partials/menu.php'; // <-- menú admin
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
@$conexion->set_charset('utf8mb4');

$msg = null; $ok=false;
if ($_SERVER['REQUEST_METHOD']==='POST'){
  $alias = trim($_POST['alias_transferencia'] ?? '');
  if ($alias===''){ $msg='Ingresá un alias válido.'; }
  else {
    $ok = set_ajuste($conexion, 'alias_transferencia', $alias);
    $msg = $ok ? '✅ Alias guardado.' : 'No se pudo guardar.';
  }
}
$alias_actual = get_ajuste($conexion, 'alias_transferencia', '');
?>
<div class="container">
  <div class="card">
    <div class="card-header">Ajustes — TAGUS</div>
    <div class="card-body">
      <?php if ($msg): ?>
        <div class="card" style="border-color:<?= $ok ? '#d1fae5' : '#fee2e2' ?>;margin-bottom:10px"><div class="card-body"><?= htmlspecialchars($msg) ?></div></div>
      <?php endif; ?>
      <form method="post" class="row cols-2">
        <div style="grid-column:1/-1">
          <label>Alias de transferencia (CBU/CVU)</label>
          <input class="input" type="text" name="alias_transferencia" value="<?= htmlspecialchars($alias_actual) ?>" placeholder="EJ: TAGUS.INDUMENTARIA.ALIAS" required>
          <small class="muted">Este alias se mostrará en el Checkout cuando el cliente elija “Transferencia”.</small>
        </div>
        <div class="mt-3" style="grid-column:1/-1">
          <button class="btn btn-primary" type="submit">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>
