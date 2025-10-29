<?php
// public/tienda.php — Catálogo público con variantes (talle/color) + modal galería
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/partials/public_header.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
@$conexion->set_charset('utf8mb4');

/* ===== Endpoint modal JSON (galería e info detallada) ===== */
if (isset($_GET['modal']) && (int)($_GET['id'] ?? 0) > 0) {
  header('Content-Type: application/json; charset=utf-8');
  $id = (int)$_GET['id'];

  $p = $conexion->query("SELECT id,titulo,descripcion,precio FROM ind_productos WHERE id={$id} LIMIT 1");
  if (!$p || !$p->num_rows) { echo json_encode(['ok'=>false]); exit; }
  $prod = $p->fetch_assoc();

  // Imágenes del producto
  $imgs = [];
  $ri = $conexion->query("SELECT url FROM ind_imagenes WHERE producto_id={$id} ORDER BY is_primary DESC, id ASC");
  if ($ri && $ri->num_rows) { while($r=$ri->fetch_assoc()) $imgs[] = img_url($r['url']); }

  // Variantes
  $vars = [];
  $rv = $conexion->query("SELECT talle,color,stock,medidas FROM ind_variantes WHERE producto_id={$id} ORDER BY talle,color");
  if ($rv && $rv->num_rows) { while($r=$rv->fetch_assoc()) $vars[] = $r; }

  echo json_encode([
    'ok'    => true,
    'id'    => (int)$prod['id'],
    'titulo'=> $prod['titulo'] ?? '',
    'descripcion'=> $prod['descripcion'] ?? '',
    'precio'=> (float)($prod['precio'] ?? 0),
    'imgs'  => $imgs,
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
$noimg          = '/img/no-image.png';

$proto     = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$tiendaUrl = $proto . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim($BASE, '/') . '/public/tienda.php';
$shareTxt  = 'Mirá el catálogo de TAGUS';
?>
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
</style>

<div class="container">
  <div class="card">
    <div class="card-header">Tienda</div>
    <div class="card-body">
      <div class="sharebar">
        <button class="btn btn-primary" onclick="shareNative('<?=h($tiendaUrl)?>','TAGUS — Tienda','<?=h($shareTxt)?>')">🔗 Compartir</button>
        <a class="btn btn-muted" href="https://wa.me/?text=<?=urlencode($shareTxt.' '.$tiendaUrl)?>" target="_blank" rel="noopener">🟢 WhatsApp</a>
        <button class="btn btn-muted" onclick="copyLink('<?=h($tiendaUrl)?>', this)">📋 Copiar link</button>
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
          $pid = (int)$p['id'];
          $thumb = img_url($p['foto_url'] ?? '');
          $talles = trim($p['talles'] ?? '');
          $colores= trim($p['colores'] ?? '');
        ?>
        <div class="prod-card" onclick="openModalById(<?= $pid ?>)">
          <div class="ratio-box"><img src="<?=h($thumb)?>" alt="<?=h($p['titulo'])?>" loading="lazy" onerror="this.src='<?=h($noimg)?>'"></div>
          <div class="prod-body">
            <div class="prod-title"><?=h($p['titulo'])?></div>
            <div class="prod-price">$ <?= number_format((float)$p['precio'], 2, ',', '.') ?></div>
            <?php if($talles): ?><div class="pill">Talles: <?=h($talles)?></div><?php endif; ?>
            <?php if($colores): ?><div class="pill">Colores: <?=h($colores)?></div><?php endif; ?>
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
            <div id="mVars" style="margin-top:8px;font-size:.9rem"></div>
            <div class="mt-2" id="mActions"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
async function shareNative(url,title,text){
  if(navigator.share){try{await navigator.share({title,text,url});return;}catch(e){}}
  copyLink(url);
}
function copyLink(url,el){
  if(navigator.clipboard){navigator.clipboard.writeText(url).then(()=>{if(el){el.textContent='✅ Copiado';setTimeout(()=>el.textContent='📋 Copiar link',1500);}});}
}

(function(){
  const modal=document.getElementById('prodModal');
  const mTitle=document.getElementById('mTitle');
  const mMain=document.getElementById('mMain');
  const mThumbs=document.getElementById('mThumbs');
  const mPrice=document.getElementById('mPrice');
  const mDesc=document.getElementById('mDesc');
  const mVars=document.getElementById('mVars');
  const mActions=document.getElementById('mActions');
  const mClose=document.getElementById('mClose');

  function openModal(){modal.classList.add('open');modal.style.display='flex';}
  function closeModal(){modal.classList.remove('open');modal.style.display='none';}
  mClose.addEventListener('click',closeModal);
  modal.addEventListener('click',e=>{if(e.target===modal)closeModal();});

  window.openModalById=async function(id){
    const url=new URL(window.location.href);
    url.searchParams.set('modal','1');url.searchParams.set('id',id);
    const r=await fetch(url.toString(),{cache:'no-store'});if(!r.ok)return;
    const j=await r.json();if(!j.ok)return;

    mTitle.textContent=j.titulo||'Producto';
    mPrice.textContent='$ '+Number(j.precio||0).toLocaleString('es-AR',{minimumFractionDigits:2});
    mDesc.textContent=j.descripcion||'';
    mVars.innerHTML='';
    if(Array.isArray(j.vars)&&j.vars.length){
      let html='<div><b>Talles:</b> '+[...new Set(j.vars.map(v=>v.talle).filter(Boolean))].join(', ')+'</div>';
      html+='<div><b>Colores:</b> '+[...new Set(j.vars.map(v=>v.color).filter(Boolean))].join(', ')+'</div>';
      mVars.innerHTML=html;
    }
    mActions.innerHTML='<a class="btn btn-primary" href="ver_producto.php?id='+j.id+'">Elegir variante y comprar</a>';

    mThumbs.innerHTML='';
    const imgs=(j.imgs&&j.imgs.length)?j.imgs:['<?=h($noimg)?>'];
    mMain.src=imgs[0];
    imgs.forEach((u,idx)=>{
      const im=document.createElement('img');
      im.src=u;if(idx===0)im.classList.add('active');
      im.onclick=()=>{mMain.src=u;mThumbs.querySelectorAll('img').forEach(x=>x.classList.remove('active'));im.classList.add('active');};
      mThumbs.appendChild(im);
    });
    openModal();
  };
})();
</script>
