<?php
require_once __DIR__.'/../../app/config.php'; require_once __DIR__.'/../../app/helpers.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$cart=$_SESSION['cart']??[]; if(!$cart){ header('Location: /cart'); exit; }
$nombre=trim($_POST['nombre']??''); $tel=trim($_POST['telefono']??''); $email=trim($_POST['email']??''); $dir=trim($_POST['direccion']??''); $ciudad=trim($_POST['ciudad']??''); $prov=trim($_POST['provincia']??''); $cp=trim($_POST['cp']??''); $metodo=trim($_POST['metodo_pago']??'efectivo');
if($nombre===''||$tel===''||$dir===''){ die('Faltan datos'); }
$conexion->begin_transaction();
try{
$total=0.0; $items=[];
foreach($cart as $id=>$qty){ $qty=(int)$qty; if($qty<=0) continue;
if (str_starts_with((string)$id,'prod:')){ $pid=(int)substr($id,5); $q=$conexion->query("SELECT id,titulo,precio FROM ind_productos WHERE id=".$pid." FOR UPDATE"); $p=$q->fetch_assoc(); if(!$p) throw new Exception('Producto no encontrado'); $total+=$p['precio']*$qty; $items[]=['pid'=>$pid,'vid'=>null,'titulo'=>$p['titulo'],'talle'=>null,'color'=>null,'precio'=>$p['precio'],'cant'=>$qty]; }
else { $vid=(int)$id; $q=$conexion->query("SELECT v.id,v.stock,v.talle,v.color,p.id AS pid,p.titulo,p.precio FROM ind_variantes v INNER JOIN ind_productos p ON p.id=v.producto_id WHERE v.id=".$vid." FOR UPDATE"); $v=$q->fetch_assoc(); if(!$v) throw new Exception('Variante no encontrada'); if($v['stock']<$qty) throw new Exception('Sin stock para '.($v['talle'].' '.$v['color'])); $total+=$v['precio']*$qty; $items[]=['pid'=>$v['pid'],'vid'=>$vid,'titulo'=>$v['titulo'],'talle'=>$v['talle'],'color'=>$v['color'],'precio'=>$v['precio'],'cant'=>$qty]; }
}
$s=$conexion->prepare("INSERT INTO ind_pedidos (nombre,telefono,email,direccion,ciudad,provincia,cp,metodo_pago,estado,total) VALUES (?,?,?,?,?,?,?,?, 'pendiente', ?)");
$s->bind_param('ssssssssd',$nombre,$tel,$email,$dir,$ciudad,$prov,$cp,$metodo,$total); $s->execute(); $pid=$s->insert_id; $s->close();
foreach($items as $it){ $si=$conexion->prepare("INSERT INTO ind_pedido_items (pedido_id,producto_id,variante_id,titulo,talle,color,precio,cantidad) VALUES (?,?,?,?,?,?,?,?)"); $si->bind_param('iiisssdi',$pid,$it['pid'],$it['vid'],$it['titulo'],$it['talle'],$it['color'],$it['precio'],$it['cant']); $si->execute(); $si->close(); if($it['vid']){ $conexion->query("UPDATE ind_variantes SET stock=stock-".(int)$it['cant']." WHERE id=".(int)$it['vid']); } }
$conexion->commit(); $_SESSION['cart']=[]; header('Location: /success?id='.$pid); exit;
}catch(Throwable $e){ $conexion->rollback(); die('Error en checkout: '.$e->getMessage()); }