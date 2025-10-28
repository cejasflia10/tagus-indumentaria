<?php
// TAGUS INDUMENTARIA — Config
if (session_status() === PHP_SESSION_NONE) session_start();


// Conexión MySQL
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';
$DB_NAME = getenv('DB_NAME') ?: 'tagus_db';
$DB_PORT = (int)(getenv('DB_PORT') ?: 3306);


$conexion = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
if ($conexion->connect_errno) { http_response_code(500); die('BD error'); }
@$conexion->set_charset('utf8mb4');


// Cloudinary opcional (con fallback a local)
if (!defined('CLOUD_ENABLED')) define('CLOUD_ENABLED', true);
if (!defined('CLOUD_NAME')) define('CLOUD_NAME', getenv('CLOUDINARY_CLOUD_NAME') ?: '');
if (!defined('CLOUD_API_KEY')) define('CLOUD_API_KEY', getenv('CLOUDINARY_API_KEY') ?: '');
if (!defined('CLOUD_API_SECRET')) define('CLOUD_API_SECRET', getenv('CLOUDINARY_API_SECRET') ?: '');
if (!defined('CLOUD_UPLOAD_FOLDER'))define('CLOUD_UPLOAD_FOLDER', 'tagus/indumentaria');