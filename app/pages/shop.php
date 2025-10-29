<?php
/* shop.php — Tienda (UI mejorada, sin cambiar nombres/paths) */
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

if (!isset($conexion) || !($conexion instanceof mysqli) || $conexion->connect_errno) {
  http_response_code(500);
  exit('❌ Sin conexión a BD. Revisá app/config.php');
}

$title = 'Tienda';

/* ===== Filtros ===== */
$cat = trim((string)($_GET['cat'] ?? ''));
$q   = trim((string)($_GET['q'] ?? ''));

/* ===== Productos ===== */
$sql = "SELECT p.*,
  COALESCE(
    (SELECT url FROM ind_imagenes i WHERE i.producto_id = p.id AND i.is_primary = 1 LIMIT 1),
    (SELECT url FROM ind_imagenes i2 WHERE i2.producto_id = p.id LIMIT 1)
  ) AS foto
FROM ind_productos p
WHERE p.activo = 1";

$types = '';
$args  = [];

if ($cat !== '') { $sql .= " AND p.categoria = ?"; $types.='s'; $args[]=$cat; }
if ($q   !== '') { $sql .= " AND (p.titulo LIKE CONCAT('%',?,'%') OR p.descripcion LIKE CONCAT('%',?,'%'))"; $types.='ss'; $args[]=$q; $args[]=$q; }

$sql .= " ORDER BY p.id DESC";
$stmt = $conexion->prepare($sql);
$prods = [];
if ($stmt) {
  if ($types) { $stmt->bind_param($types, ...$args); }
  $stmt->execute();
  $res = $stmt->get_result();
  $prods = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  $stmt->close();
}

/* Variantes */
function get_variantes(mysqli $db, int $producto_id): array {
  $st = $db->prepare("SELECT * FROM ind_variantes WHERE producto_id=? ORDER BY talle,color");
  if (!$st) return [];
  $st->bind_param('i', $producto_id);
  $st->execute();
  $r  = $st->get_result();
  $out = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
  $st->close();
  return $out;
}

/* Categorías */
$cats = [];
$cr = $conexion->query("SELECT DISTINCT categoria FROM ind_productos WHERE categoria IS NOT NULL AND categoria<>'' ORDER BY categoria");
if ($cr) { while ($row = $cr->fetch_row()) { $cats[] = $row[0]; } }

view('partials/header.php');
?>

<form class="toolbar" method="get" style="margin-top:1rem">
  <input class="input" name="q" placeholder="Buscar…" value="<?= h($q) ?>">
  <select class="select" name="cat">
    <option value="">Todas</option>
    <?php foreach ($cats as $c): ?>
      <option value="<?= h($c) ?>" <?= $cat === $c ? 'selected' : '' ?>><?= h($c) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn" type="submit">Buscar</button>
  <a class="btn ghost" href="<?= url('cart') ?>">🛒 Carrito</a>
</form>

<div class="grid" style="margin-top:1rem">
<?php foreach($prods as $p):
  $vars = get_variantes($conexion,(int)$p['id']);
  $foto = $p['foto'] ?: asset('placeholder.jpg');
?>
  <article class="card">
    <div class="card__img">
      <img src="<?= h($foto) ?>" alt="<?= h($p['titulo']) ?>">
    </div>
    <div class="card__body">
      <h3 style="margin:0"><?= h($p['titulo']) ?></h3>
      <div class="price">$ <?= money($p['precio']) ?></div>
      <?php if(!empty($p['descripcion'])): ?>
        <p style="color:var(--muted);margin:.2rem 0 0 0"><?= nl2br(h($p['descripcion'])) ?></p>
      <?php endif; ?>

      <?php if($vars): ?>
        <div class="row" style="margin-top:.6rem">
          <?php foreach($vars as $v):
            $agot = ((int)$v['stock']<=0);
            $label = trim(($v['talle'] ?? '') . ((($v['talle'] ?? '') && ($v['color'] ?? '')) ? ' / ' : '') . ($v['color'] ?? ''));
            if ($label === '') $label = 'Única';
          ?>
            <button class="pill" onclick="return addCartVar(<?= (int)$v['id'] ?>);" <?= $agot?'disabled':'' ?>>
              <?= h($label) ?><?= $agot?' · sin stock':'' ?>
            </button>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="row" style="margin-top:.8rem">
          <button class="btn" onclick="return addCartProd(<?= (int)$p['id'] ?>);">Agregar</button>
        </div>
      <?php endif; ?>
    </div>
  </article>
<?php endforeach; ?>
</div>

<script>
const BASE = "<?= rtrim(url(''), '/') ?>";

async function addCartVar(varianteId){
  const fd = new FormData();
  fd.append('action','add');
  fd.append('variante_id',varianteId);
  try{
    const r = await fetch(BASE + '/cart_api.php',{method:'POST',body:fd});
    const j = await r.json().catch(()=> ({}));
    alert(j.msg || 'Agregado al carrito');
  }catch(e){ alert('No se pudo agregar.'); }
  return false;
}

async function addCartProd(productoId){
  const fd = new FormData();
  fd.append('action','add');
  fd.append('producto_id',productoId);
  fd.append('variante_id','prod:'+productoId); // compat
  try{
    const r = await fetch(BASE + '/cart_api.php',{method:'POST',body:fd});
    const j = await r.json().catch(()=> ({}));
    alert(j.msg || 'Agregado al carrito');
  }catch(e){ alert('No se pudo agregar.'); }
  return false;
}
</script>

<?php view('partials/footer.php'); ?>
