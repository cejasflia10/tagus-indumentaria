<?php
$title='Tienda'; include __DIR__.'/../views/partials/header.php';
require_once __DIR__.'/../../app/config.php';


$cat = trim($_GET['cat'] ?? '');
$q = trim($_GET['q'] ?? '');


$sql = "SELECT p.*, COALESCE((SELECT url FROM ind_imagenes i WHERE i.producto_id=p.id AND i.is_primary=1 LIMIT 1), (SELECT url FROM ind_imagenes i2 WHERE i2.producto_id=p.id LIMIT 1)) AS foto
FROM ind_productos p WHERE p.activo=1";
$types=''; $args=[];
if ($cat!==''){ $sql.=" AND p.categoria=?"; $types.='s'; $args[]=$cat; }
if ($q!==''){ $sql.=" AND (p.titulo LIKE CONCAT('%',?,'%') OR p.descripcion LIKE CONCAT('%',?,'%'))"; $types.='ss'; $args[]=$q; $args[]=$q; }
$sql .= " ORDER BY p.id DESC";
$stmt=$conexion->prepare($sql); if($types) $stmt->bind_param($types, ...$args); $stmt->execute(); $res=$stmt->get_result(); $prods=$res->fetch_all(MYSQLI_ASSOC); $stmt->close();


function vars_de($db,$pid){ $r=$db->query("SELECT * FROM ind_variantes WHERE producto_id=".(int)$pid." ORDER BY talle,color"); return $r? $r->fetch_all(MYSQLI_ASSOC):[]; }
?>
<form class="bar" method="get">
<input name="q" placeholder="Buscar…" value="<?=h($q)?>" />
<select name="cat">
<option value="">Todas</option>
<?php $cats=$conexion->query("SELECT DISTINCT categoria FROM ind_productos WHERE categoria IS NOT NULL AND categoria<>'' ORDER BY categoria"); while($c=$cats->fetch_row()){ $sel=($cat===$c[0])?'selected':''; echo '<option '.$sel.'>'.h($c[0]).'</option>'; } ?>
</select>
<button class="btn">Buscar</button>
<a class="btn ghost" href="/cart">🛒 Carrito</a>
</form>
<div class="grid">
<?php foreach($prods as $p): $vars=vars_de($conexion,$p['id']); ?>
<article class="card">
<div class="card__img"><img src="<?=h($p['foto'] ?: '/assets/placeholder.jpg')?>" alt="<?=h($p['titulo'])?>"></div>
<div class="card__body">
<h3><?=h($p['titulo'])?></h3>
<div class="price">$ <?=money($p['precio'])?></div>
<p class="desc"><?=nl2br(h($p['descripcion']))?></p>
<?php if($vars): ?>
<div class="variants">
<?php foreach($vars as $v): $agot=((int)$v['stock']<=0); ?>
<button class="pill" onclick="addCart(<?= (int)$v['id'] ?>);return false;" <?= $agot?'disabled':'' ?>>
<?= h(trim(($v['talle']?:'').(($v['talle']&&$v['color'])?' / ':'').($v['color']?:'')) ?: 'Única') ?><?= $agot?' · sin stock':'' ?>
</button>
<?php endforeach; ?>
</div>
<?php else: ?>
<button class="btn" onclick="addCart('prod:<?= (int)$p['id'] ?>');return false;">Agregar</button>
<?php endif; ?>
</div>
</article>
<?php endforeach; ?>
</div>
<script>
async function addCart(id){const fd=new FormData();fd.append('action','add');fd.append('variante_id',id);const r=await fetch('/cart_api.php',{method:'POST',body:fd});const j=await r.json();alert(j.msg||'Agregado');}
</script>
<?php include __DIR__.'/../views/partials/footer.php'; ?>