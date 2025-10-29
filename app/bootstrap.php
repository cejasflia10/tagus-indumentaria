<?php
// app/bootstrap.php
declare(strict_types=1);

// Detecta base "/TAGUS" de forma robusta
$script = $_SERVER['SCRIPT_NAME'] ?? '/';
if (preg_match('#^/([^/]+)/#', $script, $m)) {
  define('PROJECT_BASE', '/'.$m[1]);
} else {
  define('PROJECT_BASE', '');
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
