<?php
// app/helpers.php

if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('money')) {
  function money($n){ $n = is_numeric($n) ? (float)$n : 0.0; return number_format($n, 2, ',', '.'); }
}

/* Incluye vistas desde /app/views */
if (!function_exists('view')) {
  function view(string $relativePath): void {
    if (!defined('VIEWS_PATH')) {
      // Por si aún no definieron VIEWS_PATH en bootstrap
      define('VIEWS_PATH', __DIR__ . '/views');
    }
    $file = VIEWS_PATH . '/' . ltrim($relativePath, '/');
    if (!is_file($file)) {
      http_response_code(500);
      exit('❌ view() no encontró: ' . h($file));
    }
    include $file;
  }
}

/* Base URL del proyecto */
if (!function_exists('url')) {
  function url(string $path=''): string {
    // Si PROJECT_BASE está definido (bootstrap), usarlo; si no, inferir desde SCRIPT_NAME
    $base = defined('PROJECT_BASE')
      ? PROJECT_BASE
      : (function () {
          $script = $_SERVER['SCRIPT_NAME'] ?? '/';
          // Intenta obtener "/TAGUS" como base
          if (preg_match('#^/([^/]+)/#', $script, $m)) return '/'.$m[1];
          return '';
        })();
    return rtrim($base, '/') . '/' . ltrim($path, '/');
  }
}

/* ✅ Assets: ahora apuntan a /public/assets/ */
if (!function_exists('asset')) {
  function asset(string $path=''): string {
    // Genera /TAGUS/public/assets/<archivo>
    return url('public/assets/' . ltrim($path, '/'));
  }
}
