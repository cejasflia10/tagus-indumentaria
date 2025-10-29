<?php
/* =========================================================================
   public/contable.php — Gastos + resumen diario/mensual + export CSV (mobile-first)
   Depende de:
     - ind_ventas (ventas)
     - cont_gastos (gastos)
     - Cloudinary (usa constantes de app/config.php)
   ========================================================================== */
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/partials/menu.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function n2($v){ return number_format((float)$v, 2, ',', '.'); }

/* ===== Subida Cloudinary (voucher) con firma ===== */
function cloudinary_upload(array $file, string $folder = ''): array {
  if (!defined('CLOUD_ENABLED') || !CLOUD_ENABLED)  return [false, 'Cloudinary deshabilitado', null];
  foreach (['CLOUD_NAME','CLOUD_API_KEY','CLOUD_API_SECRET'] as $c) {
    if (!defined($c) || constant($c)==='') return [false, "Falta $c", null];
  }
  if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return [false,'Archivo inválido',null];

  $endpoint  = "https://api.cloudinary.com/v1_1/".rawurlencode(CLOUD_NAME)."/image/upload";
  $timestamp = time();
  $params_to_sign = ['folder'=>$folder, 'timestamp'=>$timestamp];
  $pairs=[]; foreach($params_to_sign as $k=>$v){ if($v!==''&&$v!==null) $pairs[]="$k=$v"; }
  sort($pairs,SORT_STRING);
  $signature = sha1(implode('&',$pairs).CLOUD_API_SECRET);

  $cfile = new CURLFile($file['tmp_name'], $file['type']??'application/octet-stream', $file['name']??'file');
  $post  = ['file'=>$cfile,'api_key'=>CLOUD_API_KEY,'timestamp'=>$timestamp,'signature'=>$signature,'folder'=>$folder];

  $ch = curl_init($endpoint);
  curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post,CURLOPT_TIMEOUT=>45]);
  $res = curl_exec($ch);
  if($res===false) return [false,'HTTP: '.curl_error($ch),null];
  $code = curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  $json = json_decode($res,true);
  if($code>=200 && $code<300 && isset($json['secure_url'])) return [true,null,$json['secure_url']];
  return [false,'Respuesta inválida', $json];
}

/* ===== Cargar gasto ===== */
$msg=null; $ok=false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['__form'] ?? '') === 'gasto') {
  $fecha     = trim($_POST['fecha'] ?? date('Y-m-d'));
  $categoria = trim($_POST['categoria'] ?? '');
  $concepto  = trim($_POST['concepto'] ?? '');
  $medio     = trim($_POST['medio_pago'] ?? 'Efectivo');
  $monto     = (float)($_POST['monto'] ?? 0);
  $nota      = trim($_POST['nota'] ?? '');
  $voucher   = null;

  if ($concepto === '' || $monto <= 0) {
    $msg = 'Ingresá concepto y monto válido.';
  } else {
    if (!empty($_FILES['voucher']['name'])) {
      $folder = defined('CLOUD_FOLDER') ? (CLOUD_FOLDER.'/gastos') : 'tagus_indumentaria/gastos';
      [$okUp, $errUp, $url] = cloudinary_upload($_FILES['voucher'], $folder);
      if ($okUp) $voucher = $url; else $msg = 'Gasto cargado sin voucher: '.(is_string($errUp)?$errUp:json_encode($errUp));
    }
    $stmt = $conexion->prepare("INSERT INTO cont_gastos (fecha, categoria, concepto, medio_pago, monto, nota, voucher_url) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param('ssssiss', $fecha, $categoria, $concepto, $medio, $monto, $nota, $voucher);
    if (!$stmt->execute()) { $msg = 'Error al guardar gasto: '.$conexion->error; }
    else { $ok=true; if(!$msg) $msg='✅ Gasto registrado.'; }
  }
}

/* ===== Filtros ===== */
$hoy = date('Y-m-d');
$primer_dia_mes = date('Y-m-01');
$ultimo_dia_mes = date('Y-m-t');

$from = $_GET['from'] ?? $primer_dia_mes;
$to   = $_GET['to']   ?? $ultimo_dia_mes;

/* ===== Resúmenes ===== */
// Ventas del día
$r = $conexion->query("SELECT COALESCE(SUM(total),0) AS s FROM ind_ventas WHERE DATE(created_at)=CURDATE()");
$ventas_hoy = (float)($r? $r->fetch_assoc()['s'] : 0);

// Gastos del día
$r = $conexion->query("SELECT COALESCE(SUM(monto),0) AS s FROM cont_gastos WHERE fecha=CURDATE()");
$gastos_hoy = (float)($r? $r->fetch_assoc()['s'] : 0);

// Ventas del mes
$r = $conexion->query("SELECT COALESCE(SUM(total),0) AS s FROM ind_ventas WHERE DATE_FORMAT(created_at,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')");
$ventas_mes = (float)($r? $r->fetch_assoc()['s'] : 0);

// Gastos del mes
$r = $conexion->query("SELECT COALESCE(SUM(monto),0) AS s FROM cont_gastos WHERE DATE_FORMAT(fecha,'%Y-%m')=DATE_FORMAT(CURDATE(),'%Y-%m')");
$gastos_mes = (float)($r? $r->fetch_assoc()['s'] : 0);

/* ===== Listado por rango ===== */
$from_sql = $conexion->real_escape_string($from);
$to_sql   = $conexion->real_escape_string($to);
$list = $conexion->query("
  SELECT id, fecha, categoria, concepto, medio_pago, monto, voucher_url, nota
  FROM cont_gastos
  WHERE fecha BETWEEN '{$from_sql}' AND '{$to_sql}'
  ORDER BY fecha DESC, id DESC
");

/* ===== Export CSV ===== */
if (isset($_GET['export']) && $_GET['export']==='csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="gastos_'.$from.'_'.$to.'.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['id','fecha','categoria','concepto','medio_pago','monto','voucher_url','nota']);
  if ($list && $list->num_rows) {
    while($row = $list->fetch_assoc()){
      fputcsv($out, [$row['id'],$row['fecha'],$row['categoria'],$row['concepto'],$row['medio_pago'],$row['monto'],$row['voucher_url'],$row['nota']]);
    }
  }
  fclose($out); exit;
}
?>
<div class="container">
  <div class="card">
    <div class="card-header">Contable — Resumen</div>
    <div class="card-body">
      <div class="grid cols-2">
        <div class="card">
          <div class="card-header">Hoy</div>
          <div class="card-body">
            <p>🟢 Ventas del día: <strong>$<?= n2($ventas_hoy) ?></strong></p>
            <p>🔴 Gastos del día: <strong>$<?= n2($gastos_hoy) ?></strong></p>
            <p>🧮 Saldo del día: <strong>$<?= n2($ventas_hoy - $gastos_hoy) ?></strong></p>
          </div>
        </div>
        <div class="card">
          <div class="card-header">Mes actual</div>
          <div class="card-body">
            <p>🟢 Ventas del mes: <strong>$<?= n2($ventas_mes) ?></strong></p>
            <p>🔴 Gastos del mes: <strong>$<?= n2($gastos_mes) ?></strong></p>
            <p>🧮 Resultado mes: <strong>$<?= n2($ventas_mes - $gastos_mes) ?></strong></p>
          </div>
        </div>
      </div>

      <h3 class="mt-4">Cargar gasto</h3>
      <?php if ($msg): ?>
        <div class="card" style="border-color: <?= $ok ? '#d1fae5' : '#fee2e2' ?>; margin-bottom:10px">
          <div class="card-body"><?= h($msg) ?></div>
        </div>
      <?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="row cols-2">
        <input type="hidden" name="__form" value="gasto">
        <div>
          <label>Fecha</label>
          <input class="input" type="date" name="fecha" value="<?= h($hoy) ?>" required>
        </div>
        <div>
          <label>Monto</label>
          <input class="input" type="number" step="0.01" min="0" name="monto" required inputmode="decimal" enterkeyhint="done">
        </div>
        <div>
          <label>Categoría</label>
          <input class="input" type="text" name="categoria" placeholder="Insumos, Envíos, Servicios">
        </div>
        <div>
          <label>Medio de pago</label>
          <select class="input" name="medio_pago">
            <option>Efectivo</option>
            <option>Transferencia</option>
            <option>Tarjeta</option>
            <option>Otro</option>
          </select>
        </div>
        <div class="row cols-2" style="grid-column:1/-1">
          <div>
            <label>Concepto</label>
            <input class="input" type="text" name="concepto" placeholder="Compra de tela, bolsas, etc." required>
          </div>
          <div>
            <label>Voucher / Foto (opcional)</label>
            <input class="input" type="file" name="voucher" accept="image/*" capture="environment">
          </div>
        </div>
        <div style="grid-column:1/-1">
          <label>Nota (opcional)</label>
          <textarea class="input" name="nota" rows="2"></textarea>
        </div>
        <div class="mt-3" style="grid-column:1/-1">
          <button class="btn btn-primary" type="submit">Guardar gasto</button>
        </div>
      </form>

      <h3 class="mt-4">Gastos por rango</h3>
      <form method="get" class="row cols-2">
        <div>
          <label>Desde</label>
          <input class="input" type="date" name="from" value="<?= h($from) ?>">
        </div>
        <div>
          <label>Hasta</label>
          <input class="input" type="date" name="to" value="<?= h($to) ?>">
        </div>
        <div style="align-self:end">
          <button class="btn btn-primary" type="submit">Filtrar</button>
          <a class="btn btn-muted" href="?">Limpiar</a>
          <a class="btn btn-muted" href="?from=<?= h($from) ?>&to=<?= h($to) ?>&export=csv">Exportar CSV</a>
        </div>
      </form>

      <div class="table-wrap mt-3">
        <table class="table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Categoría</th>
              <th>Concepto</th>
              <th>Medio</th>
              <th>Monto</th>
              <th>Comprobante</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($list && $list->num_rows): ?>
              <?php while($g = $list->fetch_assoc()): ?>
                <tr>
                  <td><?= h($g['fecha']) ?></td>
                  <td><?= h($g['categoria'] ?: '—') ?></td>
                  <td><?= h($g['concepto']) ?></td>
                  <td><?= h($g['medio_pago'] ?: '—') ?></td>
                  <td><strong>$<?= n2($g['monto']) ?></strong></td>
                  <td>
                    <?php if ($g['voucher_url']): ?>
                      <a class="btn btn-muted" href="<?= h($g['voucher_url']) ?>" target="_blank" rel="noopener">Ver</a>
                    <?php else: ?>—<?php endif; ?>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="6" class="muted">No hay gastos en el rango.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>
  </div>
</div>
