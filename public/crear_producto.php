<?php
// public/crear_producto.php — Crear producto + subir múltiples imágenes (Cloudinary)
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__.'/../app/config.php';
require_once __DIR__.'/partials/menu.php'; // admin
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

$msg=null; $ok=false; $pid=null;
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['step'] ?? '') === 'create') {
  $titulo = trim($_POST['titulo'] ?? '');
  $precio = (float)($_POST['precio'] ?? 0);
  $desc   = trim($_POST['descripcion'] ?? '');
  $cat    = trim($_POST['categoria'] ?? '');

  if ($titulo==='' || $precio<=0) {
    $msg = 'Completá título y precio.';
  } else {
    $st = $conexion->prepare("INSERT INTO ind_productos (titulo, descripcion, precio, categoria, activo) VALUES (?,?,?,?,1)");
    $st->bind_param('ssds', $titulo, $desc, $precio, $cat);
    if ($st->execute()) {
      $pid = $st->insert_id;
      $ok  = true;
      // Si se subieron fotos ya en este paso:
      if (!empty($_FILES['fotos']['name'][0]) && CLOUD_ENABLED) {
        $subidas = 0;
        foreach ($_FILES['fotos']['name'] as $i=>$name) {
          if (!is_uploaded_file($_FILES['fotos']['tmp_name'][$i])) continue;
          $tmpPath = $_FILES['fotos']['tmp_name'][$i];

          $cloudUrl = 'https://api.cloudinary.com/v1_1/'.rawurlencode(CLOUD_NAME).'/image/upload';
          $timestamp = time();
          $params = [
            'api_key'   => CLOUD_API_KEY,
            'timestamp' => $timestamp,
            'folder'    => CLOUD_FOLDER,
          ];
          ksort($params);
          $toSign = '';
          foreach ($params as $k=>$v) { if ($v!=='' && $v!==null) $toSign .= $k.'='.$v.'&'; }
          $toSign = rtrim($toSign,'&');
          $signature = sha1($toSign . CLOUD_API_SECRET);

          $postFields = $params;
          $postFields['signature'] = $signature;
          $postFields['file'] = new CURLFile($tmpPath, mime_content_type($tmpPath), $name);

          $ch = curl_init($cloudUrl);
          curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
          ]);
          $resp = curl_exec($ch);
          $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
          curl_close($ch);

          if ($resp !== false && $http < 400) {
            $j = json_decode($resp, true);
            $url = $j['secure_url'] ?? ($j['url'] ?? '');
            if ($url) {
              // primera foto = portada
              $is_primary = ($subidas===0 ? 1 : 0);
              $sti = $conexion->prepare("INSERT INTO ind_imagenes (producto_id, url, is_primary) VALUES (?,?,?)");
              $sti->bind_param('isi', $pid, $url, $is_primary);
              $sti->execute();
              $subidas++;
            }
          }
        }
      }
    } else {
      $msg = 'No se pudo crear el producto.';
    }
  }
}
?>
<div class="container">
  <div class="card">
    <div class="card-header">Crear producto</div>
    <div class="card-body">
      <?php if ($ok): ?>
        <div class="card" style="border-color:#d1fae5;margin-bottom:10px"><div class="card-body">
          ✅ Producto creado (#<?= (int)$pid ?>).
          <div class="mt-2" style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn btn-primary" href="imagenes_producto.php?pid=<?=$pid?>">Gestionar imágenes</a>
            <a class="btn btn-muted" href="tienda.php">Ver en Tienda pública</a>
            <a class="btn btn-muted" href="ver_producto.php?id=<?=$pid?>">Ver detalle público</a>
          </div>
        </div></div>
      <?php endif; ?>

      <?php if ($msg): ?>
        <div class="card" style="border-color:#fee2e2;margin-bottom:10px"><div class="card-body">❌ <?= h($msg) ?></div></div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" class="row cols-2">
        <input type="hidden" name="step" value="create">
        <div><label>Título</label><input class="input" type="text" name="titulo" required></div>
        <div><label>Precio</label><input class="input" type="number" step="0.01" name="precio" required></div>
        <div><label>Categoría</label><input class="input" type="text" name="categoria" placeholder="remera, buzo, etc."></div>
        <div style="grid-column:1/-1">
          <label>Descripción</label>
          <textarea class="input" name="descripcion" rows="3"></textarea>
        </div>
        <div style="grid-column:1/-1">
          <label>Fotos (múltiples, opcional)</label>
          <input class="input" type="file" name="fotos[]" accept="image/*" multiple>
          <small class="muted">La primera subido será portada. Podés cambiarla luego.</small>
        </div>

        <div class="mt-2" style="grid-column:1/-1">
          <button class="btn btn-primary" type="submit">Crear producto</button>
          <a class="btn btn-muted" href="stock.php">Volver</a>
        </div>
      </form>
    </div>
  </div>
</div>
