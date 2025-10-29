<?php
// public/ver_producto.php — Detalle público con galería (múltiples imágenes) + lightbox + agregar al carrito
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/partials/public_header.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
@$conexion->set_charset('utf8mb4');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('Producto inválido'); }

/* Producto + imágenes */
$pq = $conexion->query("
  SELECT p.id, p.titulo, p.descripcion, p.precio
  FROM ind_productos p WHERE p.id={$id} LIMIT 1
");
if (!$pq || !$pq->num_rows) { http_response_code(404); exit('Producto no encontrado'); }
$prod = $pq->fetch_assoc();

$imgs = [];
$ri = $conexion->query("SELECT url FROM ind_imagenes WHERE producto_id={$id} ORDER BY is_primary DESC, id ASC");
if ($ri && $ri->num_rows) while($row=$ri->fetch_assoc()) $imgs[] = $row['url'];

$scriptDir = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$BASE = preg_replace('#/public$#', '', $scriptDir); if ($BASE==='') $BASE='/';
$noimg = rtrim($BASE, '/').'/public/assets/noimg.png';

/* Variantes con stock */
$vars = $conexion->query("SELECT id, talle, color, stock FROM ind_variantes WHERE producto_id={$id} AND stock>0 ORDER BY color, talle");

/* Compartir */
$proto   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https://' : 'http://';
$prodUrl = $proto.($_SERVER['HTTP_HOST'] ?? 'localhost').rtrim($BASE,'/').'/public/ver_producto.php?id='.(int)$prod['id'];
$shareTxt = 'Mirá este producto en TAGUS: '.($prod['titulo'] ?? '');
?>
<div class="container">
  <div class="card">
    <div class="card-body">
      <div class="grid cols-2">
        <div>
          <!-- Imagen principal -->
          <div class="gallery-main ratio-box">
            <img id="gMain" src="<?=h($imgs[0] ?? $noimg)?>" alt="<?=h($prod['titulo'])?>" style="cursor:zoom-in">
          </div>
          <!-- Thumbnails -->
          <?php if (count($imgs)>1): ?>
            <div class="gallery-thumbs">
              <?php foreach($imgs as $i=>$u): ?>
                <img src="<?=h($u)?>" data-src="<?=h($u)?>" class="<?=$i===0?'active':''?>" alt="thumb">
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <div>
          <h1><?= htmlspecialchars($prod['titulo'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?></h1>

          <!-- Sharebar -->
          <div class="sharebar" style="margin:8px 0 12px">
            <button class="btn btn-primary" type="button"
                    onclick="shareNative('<?=h($prodUrl)?>','<?=h($prod['titulo'])?>','<?=h($shareTxt)?>')">🔗 Compartir</button>
            <a class="btn btn-muted" href="https://wa.me/?text=<?=urlencode($shareTxt.' '.$prodUrl)?>" target="_blank" rel="noopener">🟢 WhatsApp</a>
            <button class="btn btn-muted" type="button" onclick="copyLink('<?=h($prodUrl)?>', this)">📋 Copiar link</button>
          </div>

          <p class="muted" style="font-size:18px;margin:.2rem 0 1rem">$ <?= number_format((float)$prod['precio'],2,',','.') ?></p>
          <?php if (!empty($prod['descripcion'])): ?>
            <p><?= nl2br(htmlspecialchars($prod['descripcion'], ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8')) ?></p>
          <?php endif; ?>

          <form method="post" action="carrito.php" class="mt-3">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="producto_id" value="<?=$id?>">
            <label>Variante</label>
            <select class="input" name="variante_id" required>
              <option value="">Elegí talle/color</option>
              <?php if ($vars && $vars->num_rows): while($v=$vars->fetch_assoc()): ?>
                <option value="<?=$v['id']?>"><?=htmlspecialchars(($v['color']?:'Color').' - '.($v['talle']?:'Talle'))?> (<?=$v['stock']?> disp.)</option>
              <?php endwhile; else: ?>
                <option disabled>No hay stock</option>
              <?php endif; ?>
            </select>
            <div class="row cols-2">
              <div>
                <label>Cantidad</label>
                <input class="input" type="number" min="1" value="1" name="cantidad" inputmode="numeric" required>
              </div>
              <div style="align-self:end">
                <button class="btn btn-primary" type="submit">Agregar al carrito</button>
              </div>
            </div>
          </form>

          <div class="mt-3">
            <a class="btn btn-muted" href="tienda.php">Volver a la tienda</a>
            <a class="btn btn-primary" href="carrito.php">Ir al carrito</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Lightbox -->
<div class="lb" id="lb">
  <span class="close" id="lbClose">✕</span>
  <img id="lbImg" src="" alt="">
</div>

<script>
const gMain = document.getElementById('gMain');
const thumbs = document.querySelectorAll('.gallery-thumbs img');
const lb = document.getElementById('lb'); const lbImg = document.getElementById('lbImg'); const lbClose = document.getElementById('lbClose');

if (thumbs.length){
  thumbs.forEach(t=>{
    t.addEventListener('click', ()=>{
      thumbs.forEach(x=>x.classList.remove('active'));
      t.classList.add('active');
      gMain.src = t.dataset.src;
    });
  });
}
gMain.addEventListener('click', ()=>{
  lbImg.src = gMain.src;
  lb.classList.add('open');
});
lbClose.addEventListener('click', ()=> lb.classList.remove('open'));
lb.addEventListener('click', (e)=>{ if (e.target===lb) lb.classList.remove('open'); });

// helpers de compartir si no están cargados aún (reutiliza los mismos nombres)
if (typeof shareNative!=='function'){
  window.shareNative = async function(url,title,text){
    if (navigator.share){ try{ await navigator.share({title,text,url}); return; }catch(e){} }
    copyLink(url);
  }
}
if (typeof copyLink!=='function'){
  window.copyLink = function(url, el){
    if (navigator.clipboard && navigator.clipboard.writeText){
      navigator.clipboard.writeText(url).then(()=>{ if(el){ el.textContent='✅ Copiado'; setTimeout(()=>el.textContent='📋 Copiar link',1500);} });
    } else {
      const ta=document.createElement('textarea'); ta.value=url; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
      if(el){ el.textContent='✅ Copiado'; setTimeout(()=>el.textContent='📋 Copiar link',1500); }
    }
  }
}
</script>
