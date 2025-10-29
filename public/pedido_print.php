<?php
// public/pedido_print.php — Comprobante imprimible A4
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__.'/../app/config.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function n2($v){ return number_format((float)$v, 2, ',', '.'); }

$id = (int)($_GET['id'] ?? 0);
if ($id<=0) { http_response_code(400); exit('Falta id'); }

$ped = $conexion->query("SELECT * FROM ind_pedidos WHERE id={$id} LIMIT 1");
if (!$ped || !$ped->num_rows) { http_response_code(404); exit('Pedido no encontrado'); }
$p = $ped->fetch_assoc();

$items = $conexion->query("SELECT titulo,color,talle,cantidad,precio_unit,total FROM ind_pedido_items WHERE pedido_id={$id} ORDER BY id");

$scriptDir = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$BASE = preg_replace('#/public$#', '', $scriptDir);
if ($BASE === '') $BASE = '/';
$logo = rtrim($BASE, '/').'/public/assets/logo.png';

// Alias mostrado si fue transferencia
$alias_line = ($p['pago']==='transferencia' && !empty($p['alias_mostrado'])) ? 'ALIAS: '.$p['alias_mostrado'] : null;

?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Pedido #<?= (int)$p['id'] ?> — Comprobante</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    @media print { .no-print{display:none} }
    body{font-family: Arial, system-ui, -apple-system, Segoe UI, Roboto, sans-serif; color:#111; margin:0; background:#fff}
    .sheet{max-width:800px;margin:0 auto;padding:24px}
    .row{display:flex;gap:16px;align-items:center}
    .between{justify-content:space-between}
    .muted{color:#6b7280}
    h1{font-size:22px;margin:0}
    table{width:100%;border-collapse:collapse;margin-top:12px}
    th,td{border-bottom:1px solid #e5e7eb;padding:10px;text-align:left}
    th{background:#fafafa}
    .tot{font-weight:700}
    .btn{display:inline-block;padding:10px 14px;border:1px solid #e5e7eb;border-radius:10px;text-decoration:none;color:#111;margin-right:8px}
    .logo{height:60px}
    @media (max-width:600px){
      .sheet{padding:14px}
      .logo{height:46px}
    }
  </style>
</head>
<body>
  <div class="sheet">
    <div class="row between">
      <div class="row" style="gap:10px">
        <img class="logo" src="<?=h($logo)?>" alt="TAGUS">
        <div>
          <h1>Comprobante de Pedido</h1>
          <div class="muted">#<?= (int)$p['id'] ?> · <?= h($p['created_at']) ?></div>
        </div>
      </div>
      <div class="no-print">
        <a class="btn" href="javascript:window.print()">🖨️ Imprimir</a>
        <a class="btn" href="pedidos.php">Volver</a>
      </div>
    </div>

    <div style="margin-top:16px">
      <strong>Cliente:</strong> <?= h($p['nombre']) ?> — <strong>Tel:</strong> <?= h($p['tel']) ?><br>
      <strong>Envío:</strong> <?= h($p['envio']) ?><?= $p['direccion'] ? ' — '.h($p['direccion']) : '' ?><br>
      <strong>Pago:</strong> <?= h($p['pago']) ?><?= $alias_line ? ' — <strong>'.h($alias_line).'</strong>' : '' ?>
    </div>

    <?php if (!empty($p['obs'])): ?>
      <div style="margin-top:10px"><strong>Observaciones:</strong> <?= h($p['obs']) ?></div>
    <?php endif; ?>

    <table>
      <thead>
        <tr><th>Producto</th><th>Variante</th><th>Cant.</th><th>Precio</th><th>Total</th></tr>
      </thead>
      <tbody>
        <?php $s=0.0; if ($items && $items->num_rows): while($i=$items->fetch_assoc()): $s += (float)$i['total']; ?>
          <tr>
            <td><?= h($i['titulo']) ?></td>
            <td><?= h(($i['color']?:'').' '.($i['talle']?:'')) ?></td>
            <td><?= (int)$i['cantidad'] ?></td>
            <td>$<?= n2($i['precio_unit']) ?></td>
            <td>$<?= n2($i['total']) ?></td>
          </tr>
        <?php endwhile; endif; ?>
        <tr>
          <td colspan="4" class="tot" style="text-align:right">Total</td>
          <td class="tot">$<?= n2($s) ?></td>
        </tr>
      </tbody>
    </table>

    <div class="muted" style="margin-top:12px">
      Estado actual: <strong><?= h($p['estado']) ?></strong>
    </div>
  </div>
</body>
</html>
