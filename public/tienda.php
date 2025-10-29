<?php
// public/tienda.php — Catálogo público compacto con tarjetas sombreadas + modal con galería (fix: click abre modal)
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/partials/public_header.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
@$conexion->set_charset('utf8mb4');

/* ===== Endpoint modal JSON (devuelve imágenes y datos para el modal) ===== */
if (isset($_GET['modal']) && (int)($_GET['id'] ?? 0) > 0) {
  header('Content-Type: application/json; charset=utf-8');
  $id = (int)$_GET['id'];
  $p  = $conexion->query("SELECT id,titulo,descripcion,precio FROM ind_productos WHERE id={$id} LIMIT 1");
  if (!$p || !$p->num_rows) { echo json_encode(['ok'=>false]); exit; }
  $prod = $p->fetch_assoc();
  $imgs = [];
  $ri = $conexion->query("SELECT url FROM ind_imagenes WHERE producto_id={$id} ORDER BY is_primary DESC, id ASC");
  if ($ri && $ri->num_rows) { while($r=$ri->fetch_assoc()) $imgs[] = $r['url']; }
  echo json_encode([
    'ok'=>true,
    'id'=>(int)$prod['id'],
    'titulo'=>$prod['titulo'] ?? '',
    'descripcion'=>$prod['descripcion'] ?? '',
    'precio'=> (float)($prod['precio'] ?? 0),
    'imgs'=>$imgs
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ===== Helpers ===== */
function cld_thumb(string $url, int $w=420, int $h=520): string {
  if ($url === '') return $url;
  if (strpos($url, 'res.cloudinary.com') !== false && strpos($url, '/upload/') !== false) {
    $parts = explode('/upload/', $url, 2);
    if (count($parts) === 2) {
      return $parts[0].'/upload/f_auto,q_auto,c_fill,w_'.$w.',h_'.$h.'/'.$parts[1];
    }
  }
  return $url;
}

/* ===== BASE y rutas ===== */
$scriptDir = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$BASE = preg_replace('#/public$#', '', $scriptDir);
if ($BASE === '') $BASE = '/';
$hrefMisPedidos = rtrim($BASE, '/').'/public/mis_pedidos.php';
$noimg          = rtrim($BASE, '/').'/public/assets/noimg.png';

/* Compartir */
$proto     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$tiendaUrl = $proto . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim($BASE, '/') . '/public/tienda.php';
$shareTxt  = 'Mirá el catálogo de TAGUS';

/* ===== Búsqueda ===== */
$q = trim($_GET['q'] ?? '');
$like = '%'.$conexion->real_escape_string($q).'%';
$sql = "
  SELECT p.id, p.titulo, p.precio,
         (SELECT url FROM ind_imagenes WHERE producto_id=p.id ORDER BY is_primary DESC, id ASC LIMIT 1) AS img
  FROM ind_productos p
  ".($q !== '' ? "WHERE p.titulo LIKE '{$like}'" : '')."
  ORDER BY p.id DESC
  LIMIT 80
";
$prods = $conexion->query($sql);
?>
<!-- === CSS compacto y sombreados === -->
<style>
  .catalog-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-top:12px}
  @media(min-width:640px){.catalog-grid{grid-template-columns:repeat(3,1fr)}}
  @media(min-width:900px){.catalog-grid{grid-template-columns:repeat(4,1fr)}}
  @media(min-width:1200px){.catalog-grid{grid-template-columns:repeat(5,1fr)}}

  .prod-card{cursor:pointer;border-radius:14px;box-shadow:0 1px 5px rgba(0,0,0,.06);transition:transform .12s ease, box-shadow .12s ease;background:#fff}
  .prod-card:hover{transform:translateY(-2px);box-shadow:0 6px 16px rgba(0,0,0,.08)}
  .prod-body{padding:8px 10px 10px}

  .ratio-box{position:relative;width:100%;padding-top:120%;overflow:hidden;border-radius:14px 14px 0 0;background:#f6f7f8}
  .ratio-box>img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}

  .prod-title{font-weight:700;font-size:.95rem;line-height:1.1;margin-top:6px;min-height:2.2em;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
  .prod-price{color:#6b7280;font-size:.9rem;margin-top:2px}

  .pill{display:inline-block;font-size:.72rem;padding:.15rem .45rem;border-radius:999px;background:#f3f4f6;color:#374151}

  /* Modal galería (lightbox) */
  .modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,.85);z-index:2147483647}
  .modal.open{display:flex}
  .modal-content{width:min(980px,95vw);max-height:92vh;background:#fff;border-radius:16px;overflow:hidden;display:grid;grid-template-columns:1fr;grid-template-rows:auto 1fr}
  .modal-header{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;border-bottom:1px solid #eee}
  .modal-title{font-weight:700}
  .modal-close{cursor:pointer;font-size:20px;background:#f3f4f6;border:none;border-radius:8px;padding:4px 10px}
  .modal-body{padding:12px;overflow:auto}

  .m-gallery{display:grid;grid-template-columns:1.2fr .8fr;gap:12px}
  @media(max-width:780px){.m-gallery{grid-template-columns:1fr}}
  .g-main{border-radius:12px;overflow:hidden;background:#111;display:flex;align-items:center;justify-content:center}
  .g-main img{max-width:100%;max-height:min(74vh,640px);object-fit:contain}
  .g-thumbs{display:flex;gap:8px;flex-wrap:wrap}
  .g-thumbs img{width:74px;height:74px;object-fit:cover;border-radius:10px;cursor:pointer;border:2px solid transparent;opacity:.95}
  .g-thumbs img.active{border-color:#0d6efd;opacity:1}
</style>

<div class="container">
  <div class="card">
    <div class="card-header">Tienda</div>
    <div class="card-body">

      <!-- Sharebar -->
      <div class="sharebar">
        <button class="btn btn-primary" type="button"
                onclick="shareNative('<?=h($tiendaUrl)?>','TAGUS — Tienda','<?=h($shareTxt)?>')">🔗 Compartir</button>
        <a class="btn btn-muted" href="https://wa.me/?text=<?=urlencode($shareTxt.' '.$tiendaUrl)?>" target="_blank" rel="noopener">🟢 WhatsApp</a>
        <button class="btn btn-muted" type="button" onclick="copyLink('<?=h($tiendaUrl)?>', this)">📋 Copiar link</button>
      </div>

      <!-- Buscador -->
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

      <!-- Grid compacta -->
      <div class="catalog-grid">
        <?php if ($prods && $prods->num_rows): while($p = $prods->fetch_assoc()): ?>
          <?php
            $pid = (int)$p['id'];
            $img = $p['img'] ?: $noimg;
            $thumb = cld_thumb($img, 420, 520);
          ?>
          <div class="prod-card" onclick="openModalById(<?= $pid ?>)" role="button" aria-label="Ver imágenes y comprar">
            <div class="ratio-box"><img src="<?=h($thumb)?>" alt="<?=h($p['titulo'])?>" loading="lazy"></div>
            <div class="prod-body">
              <div class="prod-title"><?=h($p['titulo'])?></div>
              <div class="prod-price">$ <?= number_format((float)$p['precio'], 2, ',', '.') ?></div>
              <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">
                <span class="pill">Ver fotos</span>
                <span class="pill">Comprar</span>
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
  <div class="modal-content">
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

            <!-- Acción: ir al detalle para elegir variante y agregar al carrito -->
            <div class="mt-2" id="mActions"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Helpers compartir -->
<script>
async function shareNative(url, title, text){
  if (navigator.share){ try{ await navigator.share({title,text,url}); return; }catch(e){} }
  copyLink(url);
}
function copyLink(url, el){
  if (navigator.clipboard && navigator.clipboard.writeText){
    navigator.clipboard.writeText(url).then(()=>{ if(el){ el.textContent='✅ Copiado'; setTimeout(()=>el.textContent='📋 Copiar link',1500); } });
  } else {
    const ta=document.createElement('textarea'); ta.value=url; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
    if(el){ el.textContent='✅ Copiado'; setTimeout(()=>el.textContent='📋 Copiar link',1500); }
  }
}
</script>

<!-- Lógica del modal (fix: función directa llamada por onclick) -->
<script>
(function(){
  const modal   = document.getElementById('prodModal');
  const mTitle  = document.getElementById('mTitle');
  const mMain   = document.getElementById('mMain');
  const mThumbs = document.getElementById('mThumbs');
  const mPrice  = document.getElementById('mPrice');
  const mDesc   = document.getElementById('mDesc');
  const mActions= document.getElementById('mActions');
  const mClose  = document.getElementById('mClose');

  function openModal(){ modal.classList.add('open'); modal.style.display='flex'; modal.setAttribute('aria-hidden','false'); }
  function closeModal(){ modal.classList.remove('open'); modal.style.display='none'; modal.setAttribute('aria-hidden','true'); }

  if (mClose) mClose.addEventListener('click', closeModal);
  modal.addEventListener('click', e=>{ if(e.target===modal) closeModal(); });
  document.addEventListener('keydown', e=>{ if(e.key==='Escape') closeModal(); });

  // Expongo global para onclick en tarjeta
  window.openModalById = async function(id){
    try{
      const url = new URL(window.location.href);
      url.searchParams.set('modal','1');
      url.searchParams.set('id', id);
      const r = await fetch(url.toString(), {cache:'no-store'});
      if (!r.ok) return;
      const j = await r.json();
      if (!j.ok) return;

      // Rellenar modal
      mTitle.textContent = j.titulo || 'Producto';
      mPrice.textContent = '$ ' + (Number(j.precio||0).toLocaleString('es-AR', {minimumFractionDigits:2, maximumFractionDigits:2}));
      mDesc.textContent  = (j.descripcion||'').trim();
      mActions.innerHTML = '<a class="btn btn-primary" href="ver_producto.php?id='+j.id+'">Elegir talle/color y comprar</a>';

      // Galería
      mThumbs.innerHTML = '';
      const imgs = Array.isArray(j.imgs) && j.imgs.length ? j.imgs : ['<?= htmlspecialchars($noimg, ENT_QUOTES|ENT_SUBSTITUTE, "UTF-8") ?>'];
      mMain.src = imgs[0];

      imgs.forEach((u,idx)=>{
        const im = document.createElement('img');
        im.src = u;
        if (idx===0) im.classList.add('active');
        im.addEventListener('click', ()=>{
          mMain.src = u;
          [...mThumbs.querySelectorAll('img')].forEach(x=>x.classList.remove('active'));
          im.classList.add('active');
        });
        mThumbs.appendChild(im);
      });

      openModal();
    }catch(e){
      // fallback: abrir detalle
      window.location.href = 'ver_producto.php?id='+id;
    }
  };
})();
</script>
