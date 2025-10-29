<?php if (!isset($title)) $title = 'TAGUS'; ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#0f1115">
  <title><?= h($title) ?> — TAGUS</title>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('style.css') ?>">
  <style>
    /* pequeñas mejoras de layout para el home */
    .topbar{position:sticky;top:0;z-index:50;background:rgba(15,17,21,.85);backdrop-filter:blur(6px);border-bottom:1px solid var(--border)}
    .topbar .bar{display:flex;align-items:center;justify-content:space-between;padding:12px 0}
    .brand{display:flex;align-items:center;gap:.6rem;font-weight:800}
    .brand .dot{width:10px;height:10px;border-radius:50%;background:linear-gradient(135deg,var(--brand),var(--brand-2))}
    nav a{padding:.55rem .75rem;border-radius:10px;border:1px solid transparent;color:var(--muted)}
    nav a:hover{border-color:var(--border);color:var(--text);background:rgba(255,255,255,.03)}
    nav .pill{border:1px solid var(--border);color:var(--text)}
    main.container{width:min(1100px,92vw);margin-inline:auto;padding-bottom:24px}
  </style>
</head>
<body>
<header class="topbar">
  <div class="container bar">
    <div class="brand">
      <span class="dot"></span>
      <a href="<?= url('') ?>">TAGUS</a>
      <span style="opacity:.6;font-weight:600">/ <?= h($title) ?></span>
    </div>
    <nav>
      <a href="<?= url('') ?>">Inicio</a>
      <a href="<?= url('app/pages/shop.php') ?>">Tienda</a>
      <a href="<?= url('app/pages/admin_indum.php') ?>">Administrar</a>
      <a class="pill" href="<?= url('cart') ?>">🛒 Carrito</a>
    </nav>
  </div>
</header>
<main class="container">
