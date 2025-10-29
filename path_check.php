<?php
header('Content-Type:text/plain; charset=utf-8');
$root = 'C:\\xampp\\htdocs\\TAGUS';
$must = [
  "$root\\index.php",
  "$root\\app\\config.php",
  "$root\\app\\bootstrap.php",
  "$root\\app\\helpers.php",
  "$root\\app\\pages\\home.php",
  "$root\\app\\views\\partials\\header.php",
  "$root\\app\\views\\partials\\footer.php",
  "$root\\assets\\style.css",   // ✅ ahora chequea style.css
  "$root\\cart_api.php",
];
echo "Verificando estructura TAGUS\n=============================\n\n";
foreach ($must as $p) {
  echo (is_file($p) ? "✅ " : "❌ ") . $p . "\n";
}
echo "\nAbrí: http://localhost/TAGUS/\n";
