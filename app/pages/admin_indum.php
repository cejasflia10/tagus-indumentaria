<?php
/* ============================================================
   app/pages/admin_indum.php — Admin indumentaria + Cloudinary
   • Crear producto (con múltiples imágenes a Cloudinary)
   • Listar productos (con thumbs, QRs por variante)
   • Editar producto (título, precio, categoría, descripción, activo)
   • Gestionar variantes: agregar / actualizar / eliminar
   • Ajuste rápido de stock ±1
   • Eliminar producto (borra variantes e imágenes por FK)
   • Link directo a gestor de imágenes (public/imagenes_producto.php)
   ============================================================ */
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/helpers.php';

// ⚠️ Si tu menú admin vive en /public/partials/menu.php, usá esta ruta:
require_once dirname(__DIR__, 2) . '/public/partials/menu.php';
// Si en tu proyecto el menú está en otro lado, ajustá la ruta de arriba.

if (!isset($conexion) || !($conexion instanceof mysqli) || $conexion->connect_errno) {
  http_response_code(500);
  exit('❌ Sin conexión a BD. Revisá app/config.php');
}

$title = 'Administrar tienda';
view('partials/header.php');

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function n2($v){ return number_format((float)$v, 2, ',', '.'); }

/* Google Charts QR helper */
function qr_url(string $data, int $size=320): string {
  $chl = rawurlencode($data);
  return "https://chart.googleapis.com/chart?cht=qr&chs={$size}x{$size}&chld=L|0&chl={$chl}";
}

/* Cloudinary upload via cURL (archivo local -> URL) */
function cloud_upload_localfile(string $filepath, string $public_id): ?string {
  if (!defined('CLOUD_ENABLED') || !CLOUD_ENABLED) return null;
  if (!is_file($filepath)) return null;
  if (!defined('CLOUD_NAME') || !defined('CLOUD_API_KEY') || !defined('CLOUD_API_SECRET')) return null;

  $timestamp = time();
  $folder = defined('CLOUD_FOLDER') ? CLOUD_FOLDER : 'tagus_indumentaria';

  // Firmar
  $params = ['timestamp'=>$timestamp,'public_id'=>$public_id,'folder'=>$folder];
  ksort($params);
  $to_sign = '';
  foreach ($params as $k => $v) $to_sign .= "{$k}={$v}&";
  $signature = sha1(rtrim($to_sign,'&') . CLOUD_API_SECRET);

  $post = [
    'file'       => new CURLFile($filepath),
    'timestamp'  => $timestamp,
    'public_id'  => $public_id,
    'folder'     => $folder,
    'api_key'    => CLOUD_API_KEY,
    'signature'  => $signature,
  ];

  $ch = curl_init("https://api.cloudinary.com/v1_1/".CLOUD_NAME."/image/upload");
  curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post]);
  $resp = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
  if ($err || !$resp) return null;
  $json = json_decode($resp, true);
  return $json['secure_url'] ?? ($json['url'] ?? null);
}

/* ===== Acciones ===== */
$alert = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  /* Crear producto */
  if ($action === 'crear') {
    $titulo = trim((string)($_POST['titulo'] ?? ''));
    $desc   = trim((string)($_POST['descripcion'] ?? ''));
    $precio = (float)($_POST['precio'] ?? 0);
    $cat    = trim((string)($_POST['categoria'] ?? ''));
    $activo = isset($_POST['activo']) ? 1 : 0;
    $primary_idx = (int)($_POST['primary_idx'] ?? -1);

    if ($titulo === '') {
      $alert = '⚠️ El título es obligatorio.';
    } else {
      $st = $conexion->prepare("INSERT INTO ind_productos (titulo, descripcion, precio, categoria, activo) VALUES (?,?,?,?,?)");
      $st->bind_param('ssdsi', $titulo, $desc, $precio, $cat, $activo);
      $ok = $st->execute(); $pid = $ok ? (int)$st->insert_id : 0; $st->close();

      if ($ok && $pid > 0) {
        // Subir imágenes
        $urls = [];
        if (!empty($_FILES['imgs']) && is_array($_FILES['imgs']['name'])) {
          foreach (array_keys($_FILES['imgs']['name']) as $i) {
            if (($_FILES['imgs']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
              $tmp = $_FILES['imgs']['tmp_name'][$i];
              $name= pathinfo((string)$_FILES['imgs']['name'][$i], PATHINFO_FILENAME);
              $public_id = 'prod_'.$pid.'_'.($i+1).'_'.preg_replace('/[^A-Za-z0-9_\-]+/','_',$name);
              $url = cloud_upload_localfile($tmp, $public_id);
              if ($url) $urls[] = $url;
            }
          }
        }
        foreach ($urls as $i => $u) {
          $is_p = ($i === $primary_idx) ? 1 : 0;
          $si = $conexion->prepare("INSERT INTO ind_imagenes (producto_id, url, is_primary) VALUES (?,?,?)");
          $si->bind_param('isi', $pid, $u, $is_p); $si->execute(); $si->close();
        }
        // Asegurar una portada
        $r = $conexion->query("SELECT COUNT(*) c FROM ind_imagenes WHERE producto_id={$pid} AND is_primary=1");
        if ($r && ($row=$r->fetch_assoc()) && (int)$row['c']===0) {
          $conexion->query("UPDATE ind_imagenes SET is_primary=1 WHERE producto_id={$pid} ORDER BY id ASC LIMIT 1");
        }

        // Variantes
        $talles = $_POST['talle'] ?? [];
        $colores= $_POST['color'] ?? [];
        $meds   = $_POST['medidas'] ?? [];
        $stocks = $_POST['stock'] ?? [];
        if (is_array($talles) && is_array($colores) && is_array($stocks)) {
          foreach ($talles as $i => $t) {
            $t = trim((string)$t);
            $c = trim((string)($colores[$i] ?? ''));
            $m = trim((string)($meds[$i] ?? ''));
            $s = (int)($stocks[$i] ?? 0);
            if ($t==='' && $c==='' && $m==='' && $s===0) continue;
            $sv = $conexion->prepare("INSERT INTO ind_variantes (producto_id, talle, color, medidas, stock) VALUES (?,?,?,?,?)");
            $sv->bind_param('isssi', $pid, $t, $c, $m, $s);
            $sv->execute(); $sv->close();
          }
        }

        $alert = '✅ Producto creado, fotos a Cloudinary y variantes guardadas.';
      } else {
        $alert = '❌ No se pudo crear el producto.';
      }
    }
  }

  /* Editar producto (guardar cambios) */
  if ($action === 'upd_product') {
    $pid     = (int)($_POST['producto_id'] ?? 0);
    $titulo  = trim((string)($_POST['titulo'] ?? ''));
    $precio  = (float)($_POST['precio'] ?? 0);
    $cat     = trim((string)($_POST['categoria'] ?? ''));
    $desc    = trim((string)($_POST['descripcion'] ?? ''));
    $activo  = isset($_POST['activo']) ? 1 : 0;
    if ($pid>0 && $titulo!=='') {
      $st = $conexion->prepare("UPDATE ind_productos SET titulo=?, precio=?, categoria=?, descripcion=?, activo=? WHERE id=?");
      $st->bind_param('sdssii', $titulo, $precio, $cat, $desc, $activo, $pid);
      $st->execute(); $st->close();
      $alert = '✅ Producto actualizado.';
    }
  }

  /* Agregar variante a un producto ya existente */
  if ($action === 'add_variant') {
    $pid   = (int)($_POST['producto_id'] ?? 0);
    $talle = trim((string)($_POST['talle'] ?? ''));
    $color = trim((string)($_POST['color'] ?? ''));
    $med   = trim((string)($_POST['medidas'] ?? ''));
    $stock = max(0, (int)($_POST['stock'] ?? 0));
    if ($pid>0) {
      $sv = $conexion->prepare("INSERT INTO ind_variantes (producto_id, talle, color, medidas, stock) VALUES (?,?,?,?,?)");
      $sv->bind_param('isssi', $pid, $talle, $color, $med, $stock);
      $sv->execute(); $sv->close();
      $alert = '✅ Variante agregada.';
    }
  }

  /* Actualizar variante (talle/color/medidas/stock) */
  if ($action === 'upd_variant') {
    $vid   = (int)($_POST['variante_id'] ?? 0);
    $pid   = (int)($_POST['producto_id'] ?? 0);
    $talle = trim((string)($_POST['talle'] ?? ''));
    $color = trim((string)($_POST['color'] ?? ''));
    $med   = trim((string)($_POST['medidas'] ?? ''));
    $stock = max(0, (int)($_POST['stock'] ?? 0));
    if ($vid>0 && $pid>0) {
      $sv = $conexion->prepare("UPDATE ind_variantes SET talle=?, color=?, medidas=?, stock=? WHERE id=? AND producto_id=?");
      $sv->bind_param('sssiii', $talle, $color, $med, $stock, $vid, $pid);
      $sv->execute(); $sv->close();
      $alert = '✅ Variante actualizada.';
    }
  }

  /* Eliminar variante */
  if ($action === 'del_variant') {
    $vid = (int)($_POST['variante_id'] ?? 0);
    $pid = (int)($_POST['producto_id'] ?? 0);
    if ($vid>0 && $pid>0) {
      $sv = $conexion->prepare("DELETE FROM ind_variantes WHERE id=? AND producto_id=?");
      $sv->bind_param('ii', $vid, $pid);
      $sv->execute(); $sv->close();
      $alert = '🗑️ Variante eliminada.';
    }
  }

  /* Ajuste rápido de stock */
  if ($action === 'stock_plus' || $action === 'stock_minus') {
    $var_id = (int)($_POST['variante_id'] ?? 0);
    $delta  = ($action === 'stock_plus') ? +1 : -1;
    $conexion->query("UPDATE ind_variantes SET stock = GREATEST(0, stock + ($delta)) WHERE id={$var_id}");
    $alert = '✅ Stock actualizado.';
  }

  /* Eliminar producto completo */
  if ($action === 'eliminar_producto') {
    $pid = (int)($_POST['producto_id'] ?? 0);
    if ($pid>0) {
      $conexion->query("DELETE FROM ind_productos WHERE id={$pid}");
      $alert = '🗑️ Producto eliminado.';
    }
  }
}

/* ===== Data ===== */
function imgs_de(mysqli $db, int $pid): array {
  $r = $db->query("SELECT id, url, is_primary FROM ind_imagenes WHERE producto_id={$pid} ORDER BY is_primary DESC, id ASC");
  return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}
function vars_de(mysqli $db, int $pid): array {
  $r = $db->query("SELECT id, talle, color, medidas, stock FROM ind_variantes WHERE producto_id={$pid} ORDER BY color, talle, id");
  return $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
}
$productos = [];
$qr = $conexion->query("SELECT id, titulo, descripcion, precio, categoria, activo FROM ind_productos ORDER BY id DESC");
if ($qr) { $productos = $qr->fetch_all(MYSQLI_ASSOC); }

/* ===== Estilos mínimos (oscuro/compacto) ===== */
?>
<style>
.admin .panel{border:1px solid var(--border);border-radius:14px;padding:1rem;background:#0d1117}
.admin .grid{display:grid;gap:1rem;grid-template-columns:repeat(auto-fill,minmax(330px,1fr))}
.admin .thumbs{display:flex;gap:.5rem;flex-wrap:wrap}
.admin .thumbs img{width:72px;height:72px;object-fit:cover;border-radius:8px;border:1px solid var(--border);background:#0b0f14}
.admin .muted{color:var(--muted)}
.admin .row{display:flex;gap:.5rem;flex-wrap:wrap}
.admin .input,.admin .select,.admin textarea{padding:.6rem .8rem;border-radius:10px;border:1px solid var(--border);background:#0c0f15;color:var(--text)}
.admin label{display:block;margin:.4rem 0 .2rem}
.admin .hr{height:1px;background:var(--border);margin:1rem 0}
.admin .pill{display:inline-block;font-size:.75rem;padding:.15rem .5rem;border-radius:999px;background:#111827;color:#c7d2fe;border:1px solid #374151}
.admin details{border:1px dashed var(--border);border-radius:10px;padding:.5rem;background:#0c1016}
.admin summary{cursor:pointer;font-weight:700}
</style>

<div class="admin">
  <h1 style="margin:.5rem 0 1rem 0">Administrar tienda</h1>

  <?php if ($alert): ?>
    <div class="panel" style="border-left:4px solid #22d3ee"><?= h($alert) ?></div>
  <?php endif; ?>

  <!-- Crear -->
  <div class="panel">
    <h2 style="margin:0 0 .75rem 0">Nuevo producto</h2>

    <form method="post" enctype="multipart/form-data" class="stack">
      <input type="hidden" name="action" value="crear">

      <div class="row">
        <div style="flex:1;min-width:260px">
          <label>Título *</label>
          <input class="input" name="titulo" required>
        </div>
        <div>
          <label>Precio</label>
          <input class="input" type="number" step="0.01" name="precio" value="0">
        </div>
        <div>
          <label>Categoría</label>
          <input class="input" name="categoria" placeholder="Ej: Remeras">
        </div>
        <div style="display:flex;align-items:flex-end">
          <label style="display:flex;align-items:center;gap:.4rem">
            <input type="checkbox" name="activo" checked> Activo
          </label>
        </div>
      </div>

      <div>
        <label>Descripción</label>
        <textarea class="input" name="descripcion" rows="3" style="width:100%"></textarea>
      </div>

      <div class="hr"></div>

      <div>
        <label>Imágenes (Cloudinary) — podés subir varias</label>
        <input class="input" type="file" name="imgs[]" accept="image/*" multiple>
        <div class="row" style="align-items:center;margin-top:.5rem">
          <label>Índice principal</label>
          <input class="input" type="number" name="primary_idx" value="-1" style="width:90px" title="0 = primera imagen, -1 = auto">
        </div>
      </div>

      <div class="hr"></div>

      <div>
        <label>Variantes (talle / color / <b>medidas</b> / stock)</label>
        <div id="vars" class="stack"></div>
        <button type="button" class="btn" onclick="addVar()">+ Agregar variante</button>
      </div>

      <div class="hr"></div>

      <button class="btn primary" type="submit">Guardar producto</button>
    </form>
  </div>

  <!-- Listado -->
  <div class="section">
    <h2 style="margin:0 0 .75rem 0">Productos existentes</h2>
    <?php if (!$productos): ?>
      <p class="muted">Aún no hay productos cargados.</p>
    <?php else: ?>
      <div class="grid">
        <?php foreach ($productos as $p):
          $pid  = (int)$p['id'];
          $imgs = imgs_de($conexion,$pid);
          $vars = vars_de($conexion,$pid);
        ?>
          <article class="panel">
            <strong>#<?= $pid ?> — <?= h($p['titulo']) ?></strong>
            <div class="muted">Categoría: <?= h($p['categoria'] ?: '—') ?> · $ <?= n2($p['precio']) ?> · <?= ((int)$p['activo']===1 ? 'Activo ✅' : 'Inactivo ⛔') ?></div>

            <?php if ($imgs): ?>
              <div class="thumbs" style="margin-top:.5rem">
                <?php foreach ($imgs as $im): ?>
                  <img src="<?= h($im['url']) ?>" alt="">
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="muted" style="margin-top:.5rem">Sin imágenes</div>
            <?php endif; ?>

            <div class="row" style="gap:.5rem;margin-top:.6rem">
              <a class="btn" href="<?= h(url('public/imagenes_producto.php').'?pid='.$pid) ?>" target="_blank">🖼️ Gestionar imágenes</a>
              <form method="post" onsubmit="return confirm('¿Alternar activo/inactivo?');">
                <input type="hidden" name="action" value="upd_product">
                <input type="hidden" name="producto_id" value="<?= $pid ?>">
                <input type="hidden" name="titulo" value="<?= h($p['titulo']) ?>">
                <input type="hidden" name="precio" value="<?= h((string)$p['precio']) ?>">
                <input type="hidden" name="categoria" value="<?= h($p['categoria']) ?>">
                <input type="hidden" name="descripcion" value="<?= h($p['descripcion'] ?? '') ?>">
                <input type="hidden" name="activo" value="<?= ((int)$p['activo']===1)?'1':'' ?>">
                <button class="btn"><?= ((int)$p['activo']===1 ? '⛔ Desactivar' : '✅ Activar') ?></button>
              </form>
            </div>

            <!-- EDITAR PRODUCTO (colapsable) -->
            <details style="margin-top:.8rem">
              <summary>✏️ Editar datos del producto</summary>
              <form method="post" class="row" style="margin-top:.6rem">
                <input type="hidden" name="action" value="upd_product">
                <input type="hidden" name="producto_id" value="<?= $pid ?>">
                <div style="flex:1;min-width:240px">
                  <label>Título</label>
                  <input class="input" name="titulo" value="<?= h($p['titulo']) ?>" required>
                </div>
                <div>
                  <label>Precio</label>
                  <input class="input" type="number" step="0.01" name="precio" value="<?= h((string)$p['precio']) ?>" required>
                </div>
                <div>
                  <label>Categoría</label>
                  <input class="input" name="categoria" value="<?= h($p['categoria']) ?>">
                </div>
                <div style="display:flex;align-items:end">
                  <label style="display:flex;gap:8px;align-items:center;margin-bottom:6px">
                    <input type="checkbox" name="activo" value="1" <?= ((int)$p['activo']===1?'checked':'') ?>> Activo
                  </label>
                </div>
                <div style="width:100%">
                  <label>Descripción</label>
                  <textarea class="input" name="descripcion" rows="3"><?= h($p['descripcion'] ?? '') ?></textarea>
                </div>
                <div style="width:100%;margin-top:.4rem">
                  <button class="btn primary" type="submit">💾 Guardar cambios</button>
                </div>
              </form>
            </details>

            <!-- QRs por variante -->
            <?php if ($vars): ?>
              <div class="hr"></div>
              <div class="muted">QR por variante (escanea para vender 1 y descontar stock):</div>
              <div class="row" style="margin-top:.4rem">
                <?php foreach ($vars as $v):
                  $vid = (int)$v['id'];
                  $lb = trim(($v['talle'] ?? '') . ((($v['talle'] ?? '') && ($v['color'] ?? '')) ? ' / ' : '') . ($v['color'] ?? ''));
                  if ($lb==='') $lb='Única';
                  $sellUrl = url('app/pages/venta_qr.php').'?pid='.$pid.'&vid='.$vid.'&sell=1';
                ?>
                  <div style="border:1px solid var(--border);border-radius:10px;padding:.5rem">
                    <div style="font-weight:600"><?= h($lb) ?></div>
                    <?php if (!empty($v['medidas'])): ?>
                      <div class="muted">Medidas: <?= h($v['medidas']) ?></div>
                    <?php endif; ?>
                    <img src="<?= qr_url($sellUrl, 120) ?>" alt="QR" width="120" height="120" style="border-radius:8px;border:1px solid var(--border);margin:.35rem 0">
                    <div class="row">
                      <a class="btn" href="<?= url('app/pages/etiqueta_var.php').'?pid='.$pid.'&vid='.$vid ?>" target="_blank">Etiqueta</a>
                      <a class="btn" href="<?= $sellUrl ?>" target="_blank">Vender 1</a>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <div class="hr"></div>

            <!-- Variantes + stock + edición/eliminación -->
            <div class="muted">Variantes</div>
            <?php if ($vars): foreach ($vars as $v): ?>
              <form method="post" class="row" style="align-items:center;border:1px solid var(--border);border-radius:10px;padding:.35rem .5rem;margin-top:.4rem">
                <input type="hidden" name="producto_id" value="<?= $pid ?>">
                <input type="hidden" name="variante_id" value="<?= (int)$v['id'] ?>">

                <span class="pill">ID <?= (int)$v['id'] ?></span>
                <label>Talle</label><input class="input" name="talle"   value="<?= h($v['talle']) ?>"  style="min-width:90px">
                <label>Color</label><input class="input" name="color"   value="<?= h($v['color']) ?>"  style="min-width:100px">
                <label>Medidas</label><input class="input" name="medidas" value="<?= h($v['medidas']) ?>" style="min-width:160px">
                <label>Stock</label><input class="input" type="number" name="stock" value="<?= (int)$v['stock'] ?>" style="width:90px">

                <button class="btn" name="action" value="stock_minus" title="-1">−1</button>
                <button class="btn" name="action" value="stock_plus"  title="+1">+1</button>
                <button class="btn primary" name="action" value="upd_variant" title="Guardar">💾</button>
                <button class="btn" name="action" value="del_variant" title="Eliminar" onclick="return confirm('¿Eliminar variante #<?= (int)$v['id'] ?>?');">🗑️</button>
              </form>
            <?php endforeach; else: ?>
              <div class="muted" style="margin-top:.4rem">Este producto no tiene variantes.</div>
            <?php endif; ?>

            <!-- Agregar variante -->
            <form method="post" class="row" style="align-items:center;border:1px dashed var(--border);border-radius:10px;padding:.35rem .5rem;margin-top:.5rem">
              <input type="hidden" name="action" value="add_variant">
              <input type="hidden" name="producto_id" value="<?= $pid ?>">
              <strong style="margin-right:.5rem">+ Variante:</strong>
              <input class="input" name="talle"   placeholder="Talle"   style="min-width:90px">
              <input class="input" name="color"   placeholder="Color"   style="min-width:100px">
              <input class="input" name="medidas" placeholder="Medidas" style="min-width:160px">
              <input class="input" name="stock" type="number" min="0" value="0" style="width:90px">
              <button class="btn primary" type="submit">Agregar</button>
            </form>

            <div class="hr"></div>

            <!-- Eliminar producto -->
            <form method="post" onsubmit="return confirm('¿Eliminar producto #<?= $pid ?> y todo su contenido (imágenes y variantes)?');">
              <input type="hidden" name="producto_id" value="<?= $pid ?>">
              <button class="btn" name="action" value="eliminar_producto">🗑️ Eliminar producto</button>
            </form>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
function addVar(){
  const box = document.getElementById('vars');
  const row = document.createElement('div');
  row.className = 'row';
  row.innerHTML = `
    <input class="input" name="talle[]" placeholder="Talle (S/M/L)" style="min-width:90px">
    <input class="input" name="color[]" placeholder="Color (Negro)" style="min-width:110px">
    <input class="input" name="medidas[]" placeholder="Medidas (pecho 100cm)" style="min-width:180px">
    <input class="input" name="stock[]" type="number" placeholder="Stock" style="width:90px" value="0" min="0">
    <button type="button" class="btn" onclick="this.parentElement.remove()">✕</button>
  `;
  box.appendChild(row);
}
</script>

<?php view('partials/footer.php'); ?>
