<?php require_once __DIR__.'/../../../app/helpers.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= h($title ?? 'TAGUS INDUMENTARIA') ?></title>
<link rel="stylesheet" href="/assets/style.css?v=1" />
<link rel="icon" href="/assets/logo_tagus.svg" type="image/svg+xml" />
</head>
<body>
<header class="nav">
<div class="container nav__inner">
<a class="brand" href="/"> <img src="/assets/logo_tagus.svg" alt="Tagus"/> <span>TAGUS</span> </a>
<nav class="nav__links">
<a href="/shop">Tienda</a>
<a href="/cart">🛒</a>
<a href="/admin" class="admin">Admin</a>
</nav>
</div>
</header>
<main class="container">