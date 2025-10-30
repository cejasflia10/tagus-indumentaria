<?php
// app/config.php — Conexión robusta + Cloudinary + tablas base + medidas en variantes
declare(strict_types=1);

/* Helper mínimo si helpers.php no está aún */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
}

date_default_timezone_set('America/Argentina/Buenos_Aires');
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }

/* Detectar entorno */
$hostHttp = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$is_local = ($hostHttp === 'localhost' || $hostHttp === '127.0.0.1');

/* MySQL */
if ($is_local) {
  if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
  if (!defined('DB_USER')) define('DB_USER', 'root');
  if (!defined('DB_PASS')) define('DB_PASS', '');
  if (!defined('DB_NAME')) define('DB_NAME', 'tagus_db');
  if (!defined('DB_PORT')) define('DB_PORT', 3306);
} else {
  if (!defined('DB_HOST')) define('DB_HOST', 'mysql-daniel24533.alwaysdata.net');
  if (!defined('DB_USER')) define('DB_USER', '435000_tagus_db');
  if (!defined('DB_PASS')) define('DB_PASS', 'Catalina160gus');
  if (!defined('DB_NAME')) define('DB_NAME', 'daniel24533_tagus_db');
  if (!defined('DB_PORT')) define('DB_PORT', 3306);
}

if (!extension_loaded('mysqli')) {
  http_response_code(500);
  exit('❌ PHP sin extensión mysqli. Activala en php.ini (extension=mysqli) y reiniciá Apache.');
}

/* Conectar */
$conexion = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
if ($conexion && !$conexion->connect_errno) {
  @$conexion->set_charset('utf8mb4');
} else {
  $first_errno = $conexion ? $conexion->connect_errno : 2002;
  $first_error = $conexion ? $conexion->connect_error : 'No se pudo conectar al servidor MySQL';

  $server = @new mysqli(DB_HOST, DB_USER, DB_PASS, '', DB_PORT);
  if ($server->connect_errno) {
    http_response_code(500);
    exit('❌ No se pudo conectar al servidor MySQL: '.h($server->connect_errno.' — '.$server->connect_error));
  }
  if ($first_errno === 1049 && $is_local) {
    $dbName = $server->real_escape_string(DB_NAME);
    $server->query("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
  }
  $conexion = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
  if ($conexion->connect_errno) {
    http_response_code(500);
    exit('❌ No se pudo conectar a la base '.h(DB_NAME).': '.h($conexion->connect_errno.' — '.$conexion->connect_error).' (orig: '.$first_errno.')');
  }
  @$conexion->set_charset('utf8mb4');
}

/* Cloudinary (Cloudy) — (se dejan tal cual) */
if (!defined('CLOUD_ENABLED'))       define('CLOUD_ENABLED', true);
if (!defined('CLOUD_NAME'))          define('CLOUD_NAME', 'ddfugds9b');
if (!defined('CLOUD_API_KEY'))       define('CLOUD_API_KEY', '657814174747186');
if (!defined('CLOUD_API_SECRET'))    define('CLOUD_API_SECRET', 'TKo5BRiKCEjxSLFzn2DLbz_ji4c');
if (!defined('CLOUD_FOLDER'))        define('CLOUD_FOLDER', 'Root');

/* ===== Utilidad: existe la tabla ===== */
function table_exists(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $res = $db->query("SHOW TABLES LIKE '{$t}'");
  return ($res && $res->num_rows > 0);
}

/* ===== Tablas mínimas ===== */
if (!table_exists($conexion, 'ind_productos')) {
  $conexion->query("
    CREATE TABLE ind_productos (
      id INT AUTO_INCREMENT PRIMARY KEY,
      titulo VARCHAR(255) NOT NULL,
      descripcion TEXT NULL,
      precio DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      categoria VARCHAR(100) NULL,
      activo TINYINT(1) NOT NULL DEFAULT 1,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
}

if (!table_exists($conexion, 'ind_imagenes')) {
  $conexion->query("
    CREATE TABLE ind_imagenes (
      id INT AUTO_INCREMENT PRIMARY KEY,
      producto_id INT NOT NULL,
      url VARCHAR(500) NOT NULL,
      is_primary TINYINT(1) NOT NULL DEFAULT 0,
      FOREIGN KEY (producto_id) REFERENCES ind_productos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
}

if (!table_exists($conexion, 'ind_variantes')) {
  $conexion->query("
    CREATE TABLE ind_variantes (
      id INT AUTO_INCREMENT PRIMARY KEY,
      producto_id INT NOT NULL,
      talle VARCHAR(50) NULL,
      color VARCHAR(50) NULL,
      medidas VARCHAR(120) NULL,
      stock INT NOT NULL DEFAULT 0,
      FOREIGN KEY (producto_id) REFERENCES ind_productos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
} else {
  // asegurar columna medidas
  $colres = $conexion->query("SHOW COLUMNS FROM ind_variantes LIKE 'medidas'");
  if (!$colres || $colres->num_rows === 0) {
    $conexion->query("ALTER TABLE ind_variantes ADD COLUMN medidas VARCHAR(120) NULL AFTER color");
  }
}

/* Ventas por QR */
if (!table_exists($conexion, 'ind_ventas')) {
  $conexion->query("
    CREATE TABLE ind_ventas (
      id INT AUTO_INCREMENT PRIMARY KEY,
      producto_id INT NOT NULL,
      variante_id INT NULL,
      cantidad INT NOT NULL DEFAULT 1,
      precio_unit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (producto_id) REFERENCES ind_productos(id) ON DELETE CASCADE,
      FOREIGN KEY (variante_id) REFERENCES ind_variantes(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
}

/* === Pedidos (cabecera + items) === */
if (!table_exists($conexion, 'ind_pedidos')) {
  $conexion->query("
    CREATE TABLE ind_pedidos (
      id INT AUTO_INCREMENT PRIMARY KEY,
      nombre VARCHAR(150) NOT NULL,
      tel VARCHAR(40) NOT NULL,
      envio ENUM('retiro','domicilio') NOT NULL DEFAULT 'retiro',
      direccion VARCHAR(255) NULL,
      pago ENUM('efectivo','transferencia') NOT NULL DEFAULT 'efectivo',
      alias_mostrado VARCHAR(150) NULL,
      obs VARCHAR(255) NULL,
      total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      estado ENUM('pendiente','pagado','enviado','entregado','cancelado') NOT NULL DEFAULT 'pendiente',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
}

if (!table_exists($conexion, 'ind_pedido_items')) {
  $conexion->query("
    CREATE TABLE ind_pedido_items (
      id INT AUTO_INCREMENT PRIMARY KEY,
      pedido_id INT NOT NULL,
      producto_id INT NOT NULL,
      variante_id INT NULL,
      titulo VARCHAR(255) NOT NULL,
      color VARCHAR(50) NULL,
      talle VARCHAR(50) NULL,
      precio_unit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      cantidad INT NOT NULL DEFAULT 1,
      total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      FOREIGN KEY (pedido_id) REFERENCES ind_pedidos(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
}

/* === Contabilidad: gastos simples === */
if (!table_exists($conexion, 'cont_gastos')) {
  $conexion->query("
    CREATE TABLE cont_gastos (
      id INT AUTO_INCREMENT PRIMARY KEY,
      fecha DATE NOT NULL,
      categoria VARCHAR(100) NULL,
      concepto VARCHAR(255) NOT NULL,
      medio_pago VARCHAR(50) NULL,
      monto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
      nota TEXT NULL,
      voucher_url VARCHAR(500) NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
}

/* === Ajustes simples (clave/valor) === */
if (!table_exists($conexion, 'ind_ajustes')) {
  $conexion->query("
    CREATE TABLE ind_ajustes (
      k VARCHAR(100) PRIMARY KEY,
      v TEXT NULL,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");
}

/* Helpers ajustes */
function set_ajuste(mysqli $db, string $k, ?string $v): bool {
  $kq = $db->real_escape_string($k);
  $vq = $v===null ? 'NULL' : "'".$db->real_escape_string($v)."'";
  return (bool)$db->query("INSERT INTO ind_ajustes (k, v) VALUES ('{$kq}', {$vq})
                           ON DUPLICATE KEY UPDATE v=VALUES(v)");
}
function get_ajuste(mysqli $db, string $k, ?string $def=null): ?string {
  $kq = $db->real_escape_string($k);
  $r = $db->query("SELECT v FROM ind_ajustes WHERE k='{$kq}' LIMIT 1");
  if ($r && $r->num_rows){ $x = $r->fetch_assoc()['v']; return ($x===null||$x==='') ? $def : $x; }
  return $def;
}

/* ================== Helpers de IMAGEN ================== */

/** Placeholder local (asegurate de tener /img/no-image.png) */
function img_fallback(): string {
  return '/img/no-image.png';
}

/**
 * Normaliza la URL de imagen:
 * - http/https: si es Cloudinary e incluye /upload/, inyecta transformación válida.
 * - public_id Cloudinary (sin http): arma URL completa con transformación.
 * - ruta local: la devuelve relativa.
 */
function img_url(string $raw, int $w = 480, int $h = 480, bool $crop = true): string {
  $raw = trim($raw);
  if ($raw === '') return img_fallback();

  // Transformación correcta para Cloudinary (con subrayados)
  $transf = $crop
    ? "c_fill,g_auto,w_{$w},h_{$h},q_auto,f_auto"
    : "g_auto,w_{$w},q_auto,f_auto";

  // 1) URL absoluta
  if (preg_match('~^https?://~i', $raw)) {
    // Si es Cloudinary y trae /upload/, inyectar la transformación
    if (strpos($raw, 'res.cloudinary.com') !== false && preg_match('~/(image|video)/upload/~', $raw)) {
      // Soporta /upload/, /upload/v123/, /upload/c_scale,w_900/, etc.
      return preg_replace(
        '~/(image|video)/upload/(?:[^/]+/)?~',
        '/$1/upload/' . $transf . '/',
        $raw,
        1
      );
    }
    // Otras URLs absolutas: devolver tal cual
    return $raw;
  }

  // 2) public_id de Cloudinary (no empieza con ./ ni /)
  if (defined('CLOUD_ENABLED') && CLOUD_ENABLED && defined('CLOUD_NAME') && CLOUD_NAME
      && !preg_match('~^[./]~', $raw)) {
    $pid = str_replace('%2F','/', rawurlencode($raw)); // respetar subcarpetas del public_id
    return "https://res.cloudinary.com/" . CLOUD_NAME . "/image/upload/{$transf}/{$pid}";
  }

  // 3) Ruta local (relativa)
  return '/' . ltrim($raw, '/');
}
