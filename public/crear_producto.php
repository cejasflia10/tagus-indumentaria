<?php
// public/crear_producto.php — Crear producto + múltiples imágenes + variantes + link a etiquetas QR
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__.'/../app/config.php';
require_once __DIR__.'/partials/menu.php'; // menú admin
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function n2($v){ return number_format((float)$v, 2, ',', '.'); }

/* Helpers base URL para links */
$scriptDir = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$BASE = preg_replace('#/public$#', '', $scriptDir); if ($BASE === '') $BASE = '/';
function base_url_path(string $path): string {
  global $BASE; return rtrim($BASE,'/').'/'.ltrim($path,'/');
}

$msg=null; $ok=false; $pid=null;

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['step'] ?? '') === 'create') {
  // ===== Campos producto =====
  $titulo = trim($_POST['titulo'] ?? '');
  $precio = (float)($_POST['precio'] ?? 0);
  $desc   = trim($_POST['descripcion'] ?? '');
  $cat    = trim($_POST['categoria'] ?? '');

  if ($titulo==='' || $precio<=0) {
    $msg = 'Completá título y precio.';
  } else {
    // Crear producto
    $st = $conexion->prepare("INSERT INTO ind_productos (titulo, descripcion, precio, categoria, activo) VALUES (?,?,?,?,1)");
    if(!$st){ $msg = 'Error interno preparando alta de producto.'; }
    else{
      $st->bind_param('ssds', $titulo, $desc, $precio, $cat);
      if ($st->execute()) {
        $pid = $st->insert_id;
        $ok  = true;

        // ===== Variantes (talles/colores/medidas/stock)
        $talles   = $_POST['talle']    ?? [];
        $colores  = $_POST['color']    ?? [];
        $medidas  = $_POST['medidas']  ?? [];
        $stocks   = $_POST['stock']    ?? [];

        if (is_array($talles) && is_array($colores) && is_array($medidas) && is_array($stocks)) {
          $insVar = $conexion->prepare("INSERT INTO ind_variantes (producto_id, talle, color, medidas, stock) VALUES (?,?,?,?,?)");
          if ($insVar) {
            for ($i=0; $i < max(count($talles), count($colores), count($medidas), count($stocks)); $i++) {
              $vtalle   = trim((string)($talles[$i]  ?? ''));
              $vcolor   = trim((string)($colores[$i] ?? ''));
              $vmed     = trim((string)($medidas[$i] ?? ''));
              $vstock   = (int)($stocks[$i] ?? 0);
              if ($vtalle==='' && $vcolor==='' && $vmed==='' && $vstock===0) continue;
              $insVar->bind_param('isssi', $pid, $vtalle, $vcolor, $vmed, $vstock);
              $insVar->execute();
            }
          }
        }

        // ===== Imágenes múltiples (Cloudinary firmado simple)
        if (!empty($_FILES['fotos']['name'][0]) && CLOUD_ENABLED) {
          $subidas = 0;
          foreach ($_FILES['fotos']['name'] as $i=>$name) {
            if (!is_uploaded_file($_FILES['fotos']['tmp_name'][$i])) continue;
            $tmpPath = $_FILES['fotos']['tmp_name'][$i];

            $cloudUrl = 'https://api.cloudinary.com/v1_1/'.rawurlencode(CLOUD_NAME).'/image/upload';
            $timestamp = time();
            $params = ['api_key'=>CLOUD_API_KEY,'timestamp'=>$timestamp,'folder'=>CLOUD_FOLDER];
            ksort($params);
            $toSign = '';
            foreach ($params as $k=>$v) { if ($v!=='' && $v!==null) $toSign .= $k.'='.$v.'&'; }
            $toSign = rtrim($toSign,'&');
            $signature = sha1($toSign . CLOUD_API_SECRET);

            $postFields = $params;
            $postFields['signature'] = $signature;
            $postFields['file'] = new CURLFile($tmpPath, mime_content_type($tmpPath), $name);

            $ch = curl_init($cloudUrl);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$postFields]);
            $resp = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($resp !== false && $http < 400) {
              $j = json_decode($resp, true);
              $url = $j['secure_url'] ?? ($j['url'] ?? '');
              if ($url) {
                $is_primary = ($subidas===0 ? 1 : 0);
                $sti = $conexion->prepare("INSERT INTO ind_imagenes (producto_id, url, is_primary) VALUES (?,?,?)");
                if ($sti){ $sti->bind_param('isi', $pid, $url, $is_primary); $sti->execute(); }
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
}

/* Variantes recién creadas (para mostrar botones de etiqueta) */
$vars = [];
if ($ok && $pid) {
  $rv = $conexion->query("SELECT id, talle, color, medidas, stock FROM ind_variantes WHERE producto_id={$pid} ORDER BY color, talle, id");
  if ($rv) $vars = $rv->fetch_all(MYSQLI_ASSOC);
}
?>
<style>
  .variants-grid{display:grid;grid-template-columns:1.1fr 1.1fr 1.4fr .6fr .3fr;gap:8px;align-items:end}
  .variants-grid>div>label{font-size:.85rem;color:#374151}
  .variants-row{display:contents}
  .rm-btn{align-self:center}
  .muted{color:#6b7280;font-size:.9rem}
  .tiny{font-size:.8rem;color:#6b7280}
  @media(max-width:820px){
    .variants-grid{grid-template-columns:1fr 1fr}
    .variants-row{display:contents}
    .variants-grid .rm-btn{grid-column:1/-1}
  }
</style>

<div class="container">
  <div class="card">
    <div class="card-header">Crear producto</div>
    <div class="card-body">
      <?php if ($ok): ?>
        <div class="card" style="border-color:#d1fae5;margin-bottom:10px"><div class="card-body">
          ✅ Producto creado (#<?= (int)$pid ?>).
          <div class="mt-2" style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn btn-primary" href="imagenes_producto.php?pid=<?=$pid?>">Gestionar imágenes</a>
            <a class="btn btn-muted" href="tienda.php">Ver Tienda pública</a>
            <a class="btn btn-muted" href="ver_producto.php?id=<?=$pid?>">Ver detalle público</a>
          </div>

          <?php if ($vars): ?>
            <div class="card" style="margin-top:10px">
              <div class="card-header">Etiquetas QR por variante</div>
              <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap">
                <?php foreach ($vars as $v): 
                  $vid   = (int)$v['id'];
                  $label = trim(($v['talle']??'').' / '.($v['color']??''));
                  if ($label === '/' || $label === ' / ') $label = 'Única';
                  $etq   = base_url_path('public/etiqueta_var.php').'?pid='.$pid.'&vid='.$vid;
                ?>
                  <a class="btn btn-primary" href="<?= h($etq) ?>" target="_blank" title="Abrir etiqueta para imprimir">
                    🧾 Etiqueta: <?= h($label) ?>
                  </a>
                <?php endforeach; ?>
              </div>
              <div class="card-footer tiny">La etiqueta es una imagen PNG con QR (vende 1 y descuenta stock). Podés imprimir y pegar en la prenda.</div>
            </div>
          <?php endif; ?>
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
          <div class="tiny">La primera subida será portada. Luego podés reordenar en “Gestionar imágenes”.</div>
        </div>

        <!-- ===== Variantes ===== -->
        <div style="grid-column:1/-1;margin-top:14px">
          <label style="display:block;margin-bottom:6px">Variantes (talle/color/medidas/stock)</label>
          <div class="variants-grid" id="varsGrid">
            <!-- Cabecera -->
            <div><label>Talle</label></div>
            <div><label>Color</label></div>
            <div><label>Medidas</label></div>
            <div><label>Stock</label></div>
            <div></div>

            <!-- Fila inicial -->
            <div class="variants-row">
              <div><input class="input" type="text" name="talle[]" placeholder="M, L, XL..."></div>
              <div><input class="input" type="text" name="color[]" placeholder="negro, rojo..."></div>
              <div><input class="input" type="text" name="medidas[]" placeholder="pecho 50cm, largo 70cm..."></div>
              <div><input class="input" type="number" name="stock[]" min="0" value="0"></div>
              <div class="rm-btn"><button class="btn btn-muted" type="button" onclick="rmVarRow(this)">Quitar</button></div>
            </div>
          </div>

          <div class="mt-2">
            <button class="btn btn-primary" type="button" onclick="addVarRow()">+ Agregar variante</button>
            <span class="muted">Podés cargar tantas combinaciones de talle/color como necesites.</span>
          </div>
        </div>

        <div class="mt-2" style="grid-column:1/-1">
          <button class="btn btn-primary" type="submit">Crear producto</button>
          <a class="btn btn-muted" href="stock.php">Volver</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function addVarRow(){
  const grid = document.getElementById('varsGrid');
  const row = document.createElement('div');
  row.className = 'variants-row';
  row.innerHTML = `
    <div><input class="input" type="text" name="talle[]" placeholder="M, L, XL..."></div>
    <div><input class="input" type="text" name="color[]" placeholder="negro, rojo..."></div>
    <div><input class="input" type="text" name="medidas[]" placeholder="pecho 50cm, largo 70cm..."></div>
    <div><input class="input" type="number" name="stock[]" min="0" value="0"></div>
    <div class="rm-btn"><button class="btn btn-muted" type="button" onclick="rmVarRow(this)">Quitar</button></div>
  `;
  grid.appendChild(row);
}
function rmVarRow(btn){
  const row = btn.closest('.variants-row');
  if (!row) return;
  const grid = document.getElementById('varsGrid');
  const rows = grid.querySelectorAll('.variants-row');
  if (rows.length <= 1){
    row.querySelectorAll('input').forEach(i=>i.value='');
    return;
  }
  row.remove();
}
</script>
