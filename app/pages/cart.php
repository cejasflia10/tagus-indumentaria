<?php
$title='Carrito'; include __DIR__.'/../views/partials/header.php'; require_once __DIR__.'/../../app/config.php'; require_once __DIR__.'/../../app/helpers.php';
$cart = $_SESSION['cart'] ?? [];
function item($db,$id,$qty){
if (str_starts_with((string)$id,'prod:')){
$pid=(int)substr($id,5); $q=$db->query("SELECT p.id,p.titulo,p.precio,(SELECT url FROM ind_imagenes i WHERE i.producto_id=p.id AND i.is_primary=1 LIMIT 1) AS foto FROM ind_productos p WHERE p.id=".$pid);
if($q && $r=$q->fetch_assoc()) return ['id'=>$id,'pid'=>$pid,'vid'=>null,'titulo'=>$r['titulo'],'precio'=>(float)$r['precio'],'talle'=>null,'color'=>null,'qty'=>$qty,'foto'=>$r['foto']];
} else {
$vid=(int)$id; $q=$db->query("SELECT v.id,v.talle,v.color,p.id AS pid,p.titulo,p.precio,(SELECT url FROM ind_imagenes i WHERE i.producto_id=p.id AND i.is_primary=1 LIMIT 1) AS foto FROM ind_variantes v INNER JOIN ind_productos p ON p.id=v.producto_id WHERE v.id=".$vid);
if($q && $r=$q->fetch_assoc()) return ['id'=>$id,'pid'=>(int)$r['pid'],'vid'=>$vid,'titulo'=>$r['titulo'],'precio'=>(float)$r['precio'],'talle'=>$r['talle'],'color'=>$r['color'],'qty'=>$qty,'foto'=>$r['foto']];
}
return null;
}
$items=[]; $total=0.0; foreach($cart as $id=>$qty){ $it=item($conexion,$id,$qty); if($it){ $items[]=$it; $total+=$it['precio']*$it['qty']; } }
?>
<h1>🛒 Carrito</h1>
<?php if(!$items): ?>
<p>Tu carrito está vacío. <a class="btn" href="/shop">Ir a la tienda</a></p>
<?php else: ?>
<?php foreach($items as $it): ?>
<div class="row">
<img src="<?=h($it['foto']?:'/assets/placeholder.jpg')?>" alt="foto"/>
<div class="grow">
<strong><?=h($it['titulo'])?></strong>
<div class="muted"><?=h(trim(($it['talle']?:'').(($it['talle']&&$it['color'])?' / ':'').($it['color']?:'')))?></div>
</div>
<input type="number" min="0" value="<?= (int)$it['qty'] ?>" onchange="setQty('<?=h($it['id'])?>',this.value)" />
<div class="price">$ <?=money($it['precio']*$it['qty'])?></div>
</div>
<?php endforeach; ?>
<div class="total">
<button class="btn ghost" onclick="clearCart()">Vaciar</button>
<div class="sum">Total: $ <?=money($total)?></div>
</div>
<h3>Datos de entrega y pago</h3>
<form method="post" action="/checkout">
<div class="grid2">
<input name="nombre" required placeholder="Nombre y Apellido" />
<input name="telefono" required placeholder="Teléfono" />
<input name="email" type="email" placeholder="Email (opcional)" />
<input name="cp" placeholder="Código Postal" />
<input name="direccion" class="span2" required placeholder="Dirección (calle y número)" />
<input name="ciudad" placeholder="Ciudad" />
<input name="provincia" placeholder="Provincia" />
<select name="metodo_pago" class="span2" required>
<option value="efectivo">Efectivo</option>
<option value="transferencia">Transferencia</option>
<option value="contraentrega">Contraentrega</option>
</select>
</div>
<button class="btn" type="submit">Finalizar compra</button>
</form>
<?php endif; ?>
<script>
async function setQty(id,qty){const fd=new FormData();fd.append('action','set');fd.append('variante_id',id);fd.append('qty',qty);await fetch('/cart_api.php',{method:'POST',body:fd});location.reload();}
async function clearCart(){const fd=new FormData();fd.append('action','clear');await fetch('/cart_api.php',{method:'POST',body:fd});location.reload();}
</script>
<?php include __DIR__.'/../views/partials/footer.php'; ?>