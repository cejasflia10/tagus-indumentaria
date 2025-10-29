<?php
require_once __DIR__ . '/../app/config.php';
header('Content-Type: text/plain; charset=utf-8');
echo "OK MySQL\n";
$r = $conexion->query("SHOW TABLES");
while($row = $r->fetch_row()){ echo " - {$row[0]}\n"; }
