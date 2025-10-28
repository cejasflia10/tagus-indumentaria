<?php
require_once __DIR__.'/../app/config.php';
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($uri === '/' || $uri==='') { require __DIR__.'/../app/pages/home.php'; exit; }
if ($uri === '/shop') { require __DIR__.'/../app/pages/shop.php'; exit; }
if ($uri === '/cart') { require __DIR__.'/../app/pages/cart.php'; exit; }
if ($uri === '/checkout' && $_SERVER['REQUEST_METHOD']==='POST') { require __DIR__.'/../app/pages/checkout.php'; exit; }
if ($uri === '/success') { require __DIR__.'/../app/pages/success.php'; exit; }
if ($uri === '/admin') { require __DIR__.'/../app/pages/admin.php'; exit; }
if ($uri === '/cart_api.php') { require __DIR__.'/cart_api.php'; exit; }
http_response_code(404); echo '404';