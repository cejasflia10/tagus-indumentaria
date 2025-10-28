<?php
$title='Admin — TAGUS'; include __DIR__.'/../views/partials/header.php'; require_once __DIR__.'/../../app/config.php'; require_once __DIR__.'/../../app/helpers.php';
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['crear_producto'])){
$titulo=trim($_POST['titulo']??''); $precio=(float)($_POST['precio']??0); $categoria=trim($_POST['categoria']??''); $marca=trim($_POST['marca']??''); $descripcion=trim($_POST['descripcion']??'');
if($titulo!==''&&$precio>=0){ $q=$conexion->prepare("INSERT INTO ind_productos (titulo,descripcion,precio,categoria,marca) VALUES (?,?,?,?,?)"); $q->bind_param('ssdss',$titulo,$descripcion,$precio,$categoria,$marca); $q->execute(); $prod_id=$q->insert_id; $q->close();
// variantes
$talles=$_POST['var_talle']??[]; $colores=$_POST['var_color']??[]; $stocks=$_POST['var_stock']??[];
for($i=0;$i<count($talles);$i++){ $t=trim($talles[$i]??''); $c=trim($colores[$i]??''); $s=(int)($stocks[$i]??0); if($t!==''||$c!==''){ $qq=$conexion->prepare("INSERT INTO ind_variantes (producto_id,talle,color,stock) VALUES (?,?,?,?)"); $qq->bind_param('isss',$prod_id,$t,$c,$s); $qq->execute(); $qq->close(); } }
// imágenes múltiples
if(!empty($_FILES['fotos']['name'][0])){ $f=$_FILES['fotos']; for($i=0;$i<count($f['name']);$i++){ if($f['error'][$i]===UPLOAD_ERR_OK){ $file=['name'=>$f['name'][$i],'type'=>$f['type'][$i],'tmp_name'=>$f['tmp_name'][$i],'error'=>$f['error'][$i],'size'=>$f['size'][$i]]; $url=cloud_upload_or_local($file); if($url){ $pri=($i===0)?1:0; $qi=$conexion->prepare("INSERT INTO ind_imagenes (producto_id,url,is_primary) VALUES (?,?,?)"); $qi->bind_param('isi',$prod_id,$url,$pri); $qi->execute(); $qi->close(); } } } }
$msg='✅ Producto creado';
} else { $msg='⚠️ Título y precio obligatorios'; }
}
$prods=$conexion->query("SELECT p.*, (SELECT url FROM ind_imagenes i WHERE i.producto_id=p.id AND i.is_primary=1 LIMIT 1) AS foto FROM ind_productos p ORDER BY p.id DESC");
?>
<h1>Admin — Subir productos</h1>
<?php if($msg) echo '<p>'.$msg.'</p>'; ?>
<div class="card p16">
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="crear_producto" value="1" />
<div class="grid2">
<input name="titulo" required placeholder="Remera Tagus" />
<input name="precio" required type="number" step="0.01" min="0" placeholder="Precio" />
<input name="categoria" placeholder="Remeras" />
<input name="marca" placeholder="TAGUS" />
<textarea name="descripcion" class="span2" rows="3" placeholder="Tela premium, impresión logo a color…"></textarea>
<div class="span2">
<label>Fotos (múltiples)</label>
<input type="file" name="fotos[]" accept="image/*" capture="environment" multiple />
<small class="muted">La primera es la portada.</small>
</div>
</div>
<h3>Variantes</h3>
<div id="vars" class="grid3"></div>
<button class="btn" type="button" onclick="addVar()">+ Variante</button>
<button class="btn" type="submit">Crear producto</button>
</form>
</div>
<h3>Productos</h3>
<?php while($p=$prods->fetch_assoc()): ?>
<div class="row">
<img src="<?=h($p['foto']?:'/assets/placeholder.jpg')?>" alt="thumb"/>
<div class="grow"><strong><?=h($p['titulo'])?></strong><div class="muted">$ <?=money($p['precio'])?> · <?=h($p['categoria'])?></div></div>
<a class="btn ghost" href="/shop" target="_blank">Ver</a>
</div>
<?php endwhile; ?>
<script>
function addVar(){const c=document.getElementById('vars');const w=document.createElement('div');w.className='grid3';w.innerHTML=`<input name="var_talle[]" placeholder="Talle (S/M/L/XL)"><input name="var_color[]" placeholder="Color"><input name="var_stock[]" type="number" min="0" placeholder="Stock">`;c.appendChild(w);}addVar();
</script>
<?php include __DIR__.'/../views/partials/footer.php'; ?>