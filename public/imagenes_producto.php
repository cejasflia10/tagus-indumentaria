<?php
// public/imagenes_producto.php — Admin: subir múltiples fotos (Cloudinary), marcar portada, eliminar (corregido)
// - Firma Cloudinary correcta (NO incluye api_key en la signature)
// - Validaciones de cURL / extensiones
// - Mensajes de error más claros
// - Sin redeclarar h(): ya viene desde app/config.php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/partials/menu.php'; // menú admin (no público)
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
@$conexion->set_charset('utf8mb4');

$pid = (int)($_GET['pid'] ?? 0);
if ($pid <= 0) { http_response_code(400); exit('Producto inválido'); }

// Traer producto
$rp = $conexion->query("SELECT id, titulo FROM ind_productos WHERE id={$pid} LIMIT 1");
if (!$rp || !$rp->num_rows) { http_response_code(404); exit('Producto no encontrado'); }
$prod = $rp->fetch_assoc();

// Acciones: marcar portada
if (($_POST['action'] ?? '') === 'cover') {
  $iid = (int)($_POST['id'] ?? 0);
  $conexion->begin_transaction();
  try {
    $conexion->query("UPDATE ind_imagenes SET is_primary=0 WHERE producto_id={$pid}");
    $st = $conexion->prepare("UPDATE ind_imagenes SET is_primary=1 WHERE id=? AND producto_id=?");
    $st->bind_param('ii', $iid, $pid);
    $st->execute();
    $conexion->commit();
  } catch (Throwable $e) {
    $conexion->rollback();
  }
  header('Location: imagenes_producto.php?pid='.$pid); exit;
}

// Acciones: eliminar imagen (solo BD; si querés, después sumamos delete en Cloudinary con public_id)
if (($_POST['action'] ?? '') === 'del') {
  $iid = (int)($_POST['id'] ?? 0);
  $st = $conexion->prepare("DELETE FROM ind_imagenes WHERE id=? AND producto_id=?");
  $st->bind_param('ii', $iid, $pid);
  $st->execute();
  header('Location: imagenes_producto.php?pid='.$pid); exit;
}

// Subida múltiple a Cloudinary
$err = null;
if (($_POST['action'] ?? '') === 'upload' && !empty($_FILES['fotos']['name'][0])) {
  if (!CLOUD_ENABLED) {
    $err = 'Cloudinary no está habilitado.';
  } elseif (!extension_loaded('curl')) {
    $err = 'PHP sin extensión cURL. Activala en php.ini (extension=curl) y reiniciá Apache.';
  } else {
    // Si el producto no tiene portada, la primera subida será portada
    $hasCover = false;
    $q = $conexion->query("SELECT COUNT(*) c FROM ind_imagenes WHERE producto_id={$pid} AND is_primary=1");
    if ($q && $q->num_rows) $hasCover = ((int)$q->fetch_assoc()['c'] > 0);

    $subidas = 0;

    foreach ($_FILES['fotos']['name'] as $i => $name) {
      // Validaciones básicas
      if (!isset($_FILES['fotos']['error'][$i]) || $_FILES['fotos']['error'][$i] !== UPLOAD_ERR_OK) continue;
      if (!is_uploaded_file($_FILES['fotos']['tmp_name'][$i])) continue;

      $tmpPath = $_FILES['fotos']['tmp_name'][$i];
      $mime = function_exists('mime_content_type') ? mime_content_type($tmpPath) : 'application/octet-stream';

      // Cloudinary Upload API (firma correcta: NO incluir api_key en la signature)
      $cloudUrl  = 'https://api.cloudinary.com/v1_1/'.rawurlencode(CLOUD_NAME).'/image/upload';
      $timestamp = time();

      // Solo parámetros que van en la signature (ordenados por key asc):
      // Cloudinary firma: sha1("folder=...&timestamp=...<+otros params firmables>"+API_SECRET)
      $signParams = [
        'folder'    => CLOUD_FOLDER,
        'timestamp' => (string)$timestamp,
      ];
      ksort($signParams);
      $toSign = [];
      foreach ($signParams as $k => $v) { if ($v !== '' && $v !== null) $toSign[] = $k.'='.$v; }
      $signature = sha1(implode('&', $toSign) . CLOUD_API_SECRET);

      // Campos POST (incluye api_key, pero api_key NO entra en la firma)
      $postFields = [
        'file'      => new CURLFile($tmpPath, $mime, $name),
        'api_key'   => CLOUD_API_KEY,
        'timestamp' => $timestamp,
        'folder'    => CLOUD_FOLDER,
        'signature' => $signature,
      ];

      $ch = curl_init($cloudUrl);
      curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postFields,
      ]);
      $resp = curl_exec($ch);
      if ($resp === false) {
        $err = 'Error cURL al subir a Cloudinary: '.curl_error($ch);
        curl_close($ch);
        break;
      }
      $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if ($http >= 400) {
        $err = 'Cloudinary respondió HTTP '.$http.'.';
        break;
      }

      $j = json_decode($resp, true);
      $url = $j['secure_url'] ?? ($j['url'] ?? '');
      if (!$url) {
        $err = 'No se obtuvo URL de la imagen subida.';
        break;
      }

      // Insertar en BD; si no hay portada, la primera subida la marca
      $is_primary = ($hasCover ? 0 : ($subidas === 0 ? 1 : 0));
      $st = $conexion->prepare("INSERT INTO ind_imagenes (producto_id, url, is_primary) VALUES (?,?,?)");
      $st->bind_param('isi', $pid, $url, $is_primary);
      $st->execute();
      $subidas++;
    }

    if (!$err) { header('Location: imagenes_producto.php?pid='.$pid); exit; }
  }
}

// Listar imágenes
$imgs = $conexion->query("SELECT id, url, is_primary FROM ind_imagenes WHERE producto_id={$pid} ORDER BY is_primary DESC, id DESC");
?>
<div class="container">
  <div class="card">
    <div class="card-header">Imágenes — <?= h($prod['titulo']) ?></div>
    <div class="card-body">

      <?php if ($err): ?>
        <div class="card" style="border-color:#fee2e2;margin-bottom:10px">
          <div class="card-body">❌ <?= h($err) ?></div>
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" class="row cols-2">
        <input type="hidden" name="action" value="upload">
        <div style="grid-column:1/-1">
          <label>Subir fotos (múltiples)</label>
          <input class="input" type="file" name="fotos[]" accept="image/*" multiple required>
          <small class="muted">
            Podés seleccionar varias a la vez (máx. según PHP <code>upload_max_filesize</code> y <code>post_max_size</code>).
            La primera será <strong>portada</strong> si el producto no tiene.
          </small>
        </div>
        <div class="mt-2" style="grid-column:1/-1">
          <button class="btn btn-primary" type="submit">Subir</button>
          <a class="btn btn-muted" href="crear_producto.php">Volver</a>
        </div>
      </form>

      <h3 class="mt-3">Galería</h3>
      <?php if ($imgs && $imgs->num_rows): ?>
        <div class="grid" style="grid-template-columns:repeat(4,1fr);gap:12px">
          <?php while ($im = $imgs->fetch_assoc()): ?>
            <div class="card">
              <div class="card-body">
                <div class="ratio-box"><img src="<?= h($im['url']) ?>" alt=""></div>
                <div class="mt-2" style="display:flex;gap:8px;flex-wrap:wrap">
                  <?php if ((int)$im['is_primary'] === 1): ?>
                    <span class="muted">✅ Portada</span>
                  <?php else: ?>
                    <form method="post">
                      <input type="hidden" name="action" value="cover">
                      <input type="hidden" name="id" value="<?= (int)$im['id'] ?>">
                      <button class="btn btn-primary">Marcar portada</button>
                    </form>
                  <?php endif; ?>
                  <form method="post" onsubmit="return confirm('¿Eliminar imagen?');">
                    <input type="hidden" name="action" value="del">
                    <input type="hidden" name="id" value="<?= (int)$im['id'] ?>">
                    <button class="btn btn-danger">Eliminar</button>
                  </form>
                </div>
              </div>
            </div>
          <?php endwhile; ?>
        </div>
      <?php else: ?>
        <div class="muted">Sin imágenes aún.</div>
      <?php endif; ?>
    </div>
  </div>
</div>
