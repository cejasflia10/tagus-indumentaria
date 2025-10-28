# TAGUS INDUMENTARIA


1. Crear BD y ejecutar `/sql/tagus_schema.sql`.
2. Configurar variables en Render (DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT). Opcional Cloudinary (`CLOUDINARY_*`).
3. Deploy en Render (Web Service). **Start Command:** `php -S 0.0.0.0:10000 -t public`.
4. Abrir `/admin` para cargar productos, variantes e imágenes (desde el celular). La primera imagen es la portada.
5. Flujo cliente: `/shop` → agregar al carrito → `/cart` → completar datos → compra → descuenta stock → `/success`.