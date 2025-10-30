<?php
// public/tienda.php — Catálogo público con variantes (talle/color) + modal galería + lightbox (fix thumbs)
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/partials/public_header.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
@$conexion->set_charset('utf8mb4');

/* ==== Helpers ==== */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('img_url')) {
  // Devuelve imagen recortada cover si es Cloudinary, o la URL tal cual.
  function img_url(?string $url, int $w=520, int $h=620): string {
    $u = trim((string)($url ?? ''));
    if ($u === '') return '';
    if (strpos($u, 'res.cloudinary.com') !== false && strpos($u, '/upload/') !== false) {
      [$a, $b] = explode('/upload/', $u, 2);
      return $a.'/upload/f_auto,q_auto,c_fill,w_'.$w.',h_'.$h.'/'.$b;
    }
    return $u;
  }
}
if (!function_exists('thumb_url')) {
  // Miniatura cuadrada (cover) para grilla y thumbs del modal
  function thumb_url(?string $url, int $size=160): string {
    $u = trim((string)($url ?? ''));
    if ($u === '') return '';
    if (strpos($u, 'res.cloudinary.com') !== false && strpos($u, '/upload/') !== false) {
      [$a, $b] = explode('/upload/', $u, 2);
      return $a.'/upload/f_auto,q_auto,c_fill,w_'.$size.',h_'.$size.'/'.$b;
    }
    return $u;
  }
}

/* ===== Endpoint modal JSON (galería e info detallada) ===== */
if (isset($_GET['modal']) && (int)($_GET['id'] ?? 0) > 0) {
  header('Content-Type: application/json; charset=utf-8');
  $id = (int)$_GET['id'];

  $p = $conexion->query("SELECT id,titulo,descripcion,precio FROM ind_productos WHERE id={$id} LIMIT 1");
  if (!$p || !$p->num_rows) { echo json_encode(['ok'=>false]); exit; }
  $prod = $p->fetch_assoc();

  // Imágenes del producto (en formato {full, mini})
  $imgs = [];
  $ri = $conexion->query("SELECT url FROM ind_imagenes WHERE producto_id={$id} ORDER BY is_primary DESC, id ASC");
  if ($ri && $ri->num_rows) {
    while($r = $ri->fetch_assoc()){
      $full = img_url($r['url'], 900, 1100);
      $mini = thumb_url($r['url'], 160);
      $imgs[] = ['full'=>$full ?: '', 'mini'=>$mini ?: $full];
    }
  }

  // Variantes
  $vars = [];
  $rv = $conexion->query("SELECT talle,color,stock,medidas FROM ind_variantes WHERE producto_id={$id} ORDER BY talle,color");
  if ($rv && $rv->num_rows) { while($r=$rv->fetch_assoc()) $vars[] = $r; }

  echo json_encode([
    'ok'    => true,
    'id'    => (int)$prod['id'],
    'titulo'=> (string)($prod['titulo'] ?? ''),
    'descripcion'=> (string)($prod['descripcion'] ?? ''),
    'precio'=> (float)($prod['precio'] ?? 0),
    'imgs'  => $imgs, // puede venir vacío; el front hace fallback
    'vars'  => $vars
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ===== Búsqueda ===== */
$q = trim($_GET['q'] ?? '');
$like = '%'.$conexion->real_escape_string($q).'%';

$sql = "
  SELECT p.id, p.titulo, p.precio,
         (SELECT url FROM ind_imagenes WHERE producto_id=p.id ORDER BY is_primary DESC, id ASC LIMIT 1) AS foto_url,
         GROUP_CONCAT(DISTINCT v.talle ORDER BY v.talle SEPARATOR ', ') AS talles,
         GROUP_CONCAT(DISTINCT v.color ORDER BY v.color SEPARATOR ', ') AS colores
  FROM ind_productos p
  LEFT JOIN ind_variantes v ON v.producto_id = p.id
  ".($q !== '' ? "WHERE p.titulo LIKE '{$like}'" : '')."
  GROUP BY p.id
  ORDER BY p.id DESC
  LIMIT 100
";
$prods = $conexion->query($sql);

/* ===== URLs base ===== */
$scriptDir = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$BASE = preg_replace('#/public$#', '', $scriptDir);
if ($BASE === '') $BASE = '/';
$hrefMisPedidos = rtrim($BASE, '/').'/public/mis_pedidos.php';
$noimg          = rtrim($BASE, '/').'/public/assets/noimg.png';

$proto     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$tiendaUrl = $proto . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim($BASE, '/') . '/public/tienda.php';
$shareTxt  = 'Mirá el catálogo de TAGUS';
?>
<style>
  .catalog-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-top:12px}
  @media(min-width:640px){.catalog-grid{grid-template-columns:repeat(3,1fr)}}
  @media(min-width:900px){.catalog-grid{grid-template-columns:repeat(4,1fr)}}
  @media(min-width:1200px){.catalog-grid{grid-template-columns:repeat(5,1fr)}}
  .prod-card{cursor:pointer;border-radius:14px;box-shadow:0 1px 5px rgba(0,0,0,.06);transition:transform .12s, box-shadow .12s;background:#fff}
  .prod-card:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(0,0,0,.08)}
  .prod-body{padding:8px 10px 10px}
  .ratio-box{position:relative;width:100%;padding-top:120%;overflow:hidden;border-radius:14px 14px 0 0;background:#f6f7f8}
  .ratio-box>img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
  .prod-title{font-weight:700;font-size:.95rem;line-height:1.1;margin-top:6px;min-height:2.2em;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
  .prod-price{color:#6b7280;font-size:.9rem;margin-top:2px}
  .pill{display:inline-block;font-size:.72rem;padding:.15rem .45rem;border-radius:999px;background:#f3f4f6;color:#374151}

  /* ===== Modal ===== */
  .modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.85);z-index:2147483647}
  .modal.open{display:flex}
  .modal-content{width:min(980px,95vw);max-height:92vh;background:#fff;border-radius:16px;overflow:hidden;display:grid;grid-template-rows:auto 1fr}
  .modal-header{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-bottom:1px solid #eee}
  .modal-title{font-weight:700}
  .modal-close{cursor:pointer;font-size:20px;background:#f3f4f6;border:none;border-radius:8px;padding:4px 10px}
  .modal-body{padding:12px;overflow:auto}
  .m-gallery{display:grid;grid-template-columns:1.2fr .8fr;gap:12px}
  @media(max-width:780px){.m-gallery{grid-template-columns:1fr}}
  .g-main{border-radius:12px;overflow:hidden;background:#111;display:flex;align-items:center;justify-content:center}
  .g-main img{max-width:100%;max-height:min(74vh,640px);object-fit:contain;cursor:zoom-in}
  .g-thumbs{display:flex;gap:8px;flex-wrap:wrap;min-height:80px}
  .g-thumbs img{width:74px;height:74px;object-fit:cover;border-radius:10px;cursor:pointer;border:2px solid transparent;opacity:.95}
  .g-thumbs img.active{border-color:#0d6efd;opacity:1}

  /* Lightbox fullscreen */
  .lb{position:fixed;inset:0;background:rgba(0,0,0,.92);display:none;align-items:center;justify-content:center;z-index:2147483648}
  .lb.open{display:flex}
  .lb img{max-width:96vw;max-height:92vh;border-radius:12px}
  .lb .close{position:absolute;top:14px;right:14px;font-size:26px;color:#fff;cursor:pointer}
</style>

<div class="container">
  <div class="card">
    <div class="card-header">Tienda</div>
    <div class="card-body">
      <div class="sharebar" style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-primary" type="button"
                onclick="shareNative('<?=h($tiendaUrl)?>','TAGUS — Tienda','<?=h($shareTxt)?>')">🔗 Compartir</button>
        <a class="btn btn-muted" href="https://wa.me/?text=<?=urlencode($shareTxt.' '.$tiendaUrl)?>" target="_blank" rel="noopener">🟢 WhatsApp</a>
        <button class="btn btn-muted" type="button" onclick="copyLink('<?=h($tiendaUrl)?>', this)">📋 Copiar link</button>
      </div>

      <form method="get" class="row cols-2 mt-3">
        <div>
          <label>Buscar</label>
          <input class="input" type="text" name="q" value="<?=h($q)?>" placeholder="Producto...">
        </div>
        <div style="align-self:end;display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn btn-primary" type="submit">Buscar</button>
          <a class="btn btn-muted" href="?">Limpiar</a>
          <a class="btn btn-muted" href="<?=h($hrefMisPedidos)?>">📦 Mis pedidos</a>
        </div>
      </form>

      <div class="catalog-grid">
        <?php if ($prods && $prods->num_rows): while($p = $prods->fetch_assoc()):
          $pid    = (int)$p['id'];
          $thumb0 = $p['foto_url'] ? img_url($p['foto_url']) : '';
          $thumb  = $thumb0 !== '' ? $thumb0 : $noimg;
          $talles = trim($p['talles'] ?? '');
          $colores= trim($p['colores'] ?? '');
        ?>
        <div class="prod-card">
          <button type="button" onclick="openModalById(<?= $pid ?>)" style="all:unset;cursor:pointer;display:block">
            <div class="ratio-box">
              <img src="<?=h($thumb)?>" alt="<?=h($p['titulo'])?>" loading="lazy"
                   onerror="this.src='<?=h($noimg)?>'">
            </div>
          </button>
          <div class="prod-body">
            <div class="prod-title"><?=h($p['titulo'])?></div>
            <div class="prod-price">$ <?= number_format((float)$p['precio'], 2, ',', '.') ?></div>
            <?php if($talles): ?><div class="pill">Talles: <?=h($talles)?></div><?php endif; ?>
            <?php if($colores): ?><div class="pill">Colores: <?=h($colores)?></div><?php endif; ?>
            <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">
              <button class="btn btn-muted" type="button" onclick="openModalById(<?= $pid ?>)">Ver fotos</button>
              <a class="btn btn-primary" href="ver_producto.php?id=<?=$pid?>">Comprar</a>
            </div>
          </div>
        </div>
        <?php endwhile; else: ?>
          <div class="muted">No hay productos.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Modal Galería -->
<div class="modal" id="prodModal" aria-hidden="true">
  <div class="modal-content" onclick="event.stopPropagation()">
    <div class="modal-header">
      <div class="modal-title" id="mTitle">Producto</div>
      <button class="modal-close" id="mClose" type="button">✕</button>
    </div>
    <div class="modal-body">
      <div class="m-gallery">
        <div class="g-main"><img id="mMain" src="" alt=""></div>
        <div>
          <div class="g-thumbs" id="mThumbs"></div>
          <div style="margin-top:12px">
            <div style="font-weight:700;font-size:1.05rem" id="mPrice"></div>
            <div class="muted" id="mDesc" style="margin-top:6px"></div>
            <div id="mVars" style="margin-top:8px;font-size:.9rem"></div>
            <div class="mt-2" id="mActions"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Lightbox fullscreen -->
<div class="lb" id="lb">
  <span class="close" id="lbClose">✕</span>
  <img id="lbImg" src="" alt="">
</div>

<script>
async function shareNative(url,title,text){
  if (navigator.share){ try{ await navigator.share({title,text,url}); return; }catch(e){} }
  copyLink(url);
}
function copyLink(url, el){
  if (navigator.clipboard && navigator.clipboard.writeText){
    navigator.clipboard.writeText(url).then(()=>{ if(el){ el.textContent='✅ Copiado'; setTimeout(()=>el.textContent='📋 Copiar link',1500); } });
  }
}

(function(){
  const modal   = document.getElementById('prodModal');
  const mTitle  = document.getElementById('mTitle');
  const mMain   = document.getElementById('mMain');
  const mThumbs = document.getElementById('mThumbs');
  const mPrice  = document.getElementById('mPrice');
  const mDesc   = document.getElementById('mDesc');
  const mVars   = document.getElementById('mVars');
  const mActions= document.getElementById('mActions');
  const mClose  = document.getElementById('mClose');

  const lb      = document.getElementById('lb');
  const lbImg   = document.getElementById('lbImg');
  const lbClose = document.getElementById('lbClose');

  function openModal(){ modal.classList.add('open'); modal.style.display='flex'; modal.setAttribute('aria-hidden','false'); }
  function closeModal(){ modal.classList.remove('open'); modal.style.display='none'; modal.setAttribute('aria-hidden','true'); }
  mClose.addEventListener('click', closeModal);
  modal.addEventListener('click', (e)=>{ if(e.target===modal) closeModal(); });
  document.addEventListener('keydown', (e)=>{ if(e.key==='Escape') { if (lb.classList.contains('open')) closeLb(); else closeModal(); } });

  function openLb(src){ lbImg.src = src; lb.classList.add('open'); }
  function closeLb(){ lb.classList.remove('open'); lbImg.src=''; }
  lb.addEventListener('click', (e)=>{ if(e.target===lb || e.target===lbClose) closeLb(); });
  lbClose.addEventListener('click', closeLb);

  // Click en la imagen principal => lightbox
  mMain.addEventListener('click', ()=>{ if (mMain.src) openLb(mMain.src); });

  // Exponer global para las tarjetas/botones
  window.openModalById = async function(id){
    try{
      const url = new URL(window.location.href);
      url.searchParams.set('modal','1');
      url.searchParams.set('id', id);
      const r = await fetch(url.toString(), {cache:'no-store'});
      if (!r.ok) return;
      const j = await r.json();
      if (!j.ok) return;

      mTitle.textContent = j.titulo || 'Producto';
      mPrice.textContent = '$ ' + (Number(j.precio||0).toLocaleString('es-AR', {minimumFractionDigits:2, maximumFractionDigits:2}));
      mDesc.textContent  = (j.descripcion||'').trim();

      // variantes -> talles/colores
      mVars.innerHTML = '';
      if (Array.isArray(j.vars) && j.vars.length){
        const talles  = [...new Set(j.vars.map(v=>v.talle).filter(Boolean))].join(', ');
        const colores = [...new Set(j.vars.map(v=>v.color).filter(Boolean))].join(', ');
        let html = '';
        if (talles)  html += '<div><b>Talles:</b> '+talles+'</div>';
        if (colores) html += '<div><b>Colores:</b> '+colores+'</div>';
        mVars.innerHTML = html;
      }

      // acciones (link real a comprar)
      mActions.innerHTML = '<a class="btn btn-primary" href="ver_producto.php?id='+j.id+'">Elegir variante y comprar</a>';

      // galería — acepta formato {full,mini} o strings (compat)
      mThumbs.innerHTML = '';
      let imgs = [];
      if (Array.isArray(j.imgs) && j.imgs.length){
        if (typeof j.imgs[0] === 'string'){
          imgs = j.imgs.map(u => ({full:u, mini:u}));
        } else {
          imgs = j.imgs.map(o => ({full:o.full || o.mini, mini:o.mini || o.full}));
        }
      } else {
        const noimg = '<?=h($noimg)?>';
        imgs = [{full:noimg, mini:noimg}];
      }

      mMain.src = imgs[0].full;

      imgs.forEach((o,idx)=>{
        const im = document.createElement('img');
        im.src = o.mini || o.full;
        if (idx===0) im.classList.add('active');
        im.addEventListener('click', ()=>{
          mMain.src = o.full || o.mini;
          [...mThumbs.querySelectorAll('img')].forEach(x=>x.classList.remove('active'));
          im.classList.add('active');
        });
        mThumbs.appendChild(im);
      });

      openModal();
    }catch(e){
      // Si algo falla, ir al detalle
      window.location.href = 'ver_producto.php?id='+id;
    }
  };
})();
</script>
