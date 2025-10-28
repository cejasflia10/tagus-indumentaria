<?php $title='¡Gracias!'; include __DIR__.'/../views/partials/header.php'; require_once __DIR__.'/../../app/helpers.php'; ?>
<h1>🎉 ¡Gracias por tu compra!</h1>
<p>Tu pedido #<?= (int)($_GET['id']??0) ?> fue recibido. En breve te contactamos para coordinar la entrega.</p>
<a class="btn" href="/shop">Seguir comprando</a>
<?php include __DIR__.'/../views/partials/footer.php'; ?>