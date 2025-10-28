<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/../app/config.php'; header('Content-Type: application/json');
if (!isset($_SESSION['cart'])) $_SESSION['cart']=[];
$action=$_POST['action']??'';
function item_price($db,$id){
if (str_starts_with((string)$id,'prod:')){ $pid=(int)substr($id,5); $q=$db->query("SELECT precio FROM ind_productos WHERE id=".$pid); if($q&&$r=$q->fetch_row()) return (float)$r[0]; }
else { $vid=(int)$id; $q=$db->query("SELECT p.precio FROM ind_variantes v INNER JOIN ind_productos p ON p.id=v.producto_id WHERE v.id=".$vid); if($q&&$r=$q->fetch_row()) return (float)$r[0]; }
return 0.0;
}
switch($action){
case 'add': $id=$_POST['variante_id']??''; $qty=max(1,(int)($_POST['qty']??1)); $price=item_price($conexion,$id); if($price<=0){echo json_encode(['ok'=>false,'msg'=>'Producto no disponible']);break;} $_SESSION['cart'][$id]=($_SESSION['cart'][$id]??0)+$qty; echo json_encode(['ok'=>true,'msg'=>'Producto agregado','count'=>array_sum($_SESSION['cart'])]); break;
case 'set': $id=$_POST['variante_id']??''; $qty=max(0,(int)($_POST['qty']??0)); if($qty<=0) unset($_SESSION['cart'][$id]); else $_SESSION['cart'][$id]=$qty; echo json_encode(['ok'=>true]); break;
case 'clear': $_SESSION['cart']=[]; echo json_encode(['ok'=>true]); break;
default: echo json_encode(['ok'=>true,'cart'=>$_SESSION['cart']]);
}