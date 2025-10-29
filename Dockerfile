# Dockerfile — PHP 8.2 + Apache (index.php en la raíz)

FROM php:8.2-apache

# Extensiones y mods necesarios
RUN apt-get update && apt-get install -y \
      libpng-dev libjpeg-dev libfreetype6-dev libzip-dev unzip \
      libcurl4-openssl-dev \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install mysqli pdo pdo_mysql gd zip curl \
  && a2enmod rewrite headers expires \
  && rm -rf /var/lib/apt/lists/*

# Copiar el proyecto a la raíz de Apache
WORKDIR /var/www/html
COPY . /var/www/html

# Permisos (opcional)
RUN chown -R www-data:www-data /var/www/html

# Configurar VirtualHost para RAÍZ y permitir .htaccess
RUN sed -ri 's#DocumentRoot /var/www/html#DocumentRoot /var/www/html#g' /etc/apache2/sites-available/000-default.conf \
 && awk '1; /DocumentRoot/ {print "    <Directory /var/www/html>\n        Options Indexes FollowSymLinks\n        AllowOverride All\n        Require all granted\n    </Directory>"}' /etc/apache2/sites-available/000-default.conf > /tmp/vh && mv /tmp/vh /etc/apache2/sites-available/000-default.conf

# Hacer que Apache escuche en $PORT (útil para Render)
ENV APACHE_LISTEN_PORT=10000
RUN sed -ri "s/Listen 80/Listen \${APACHE_LISTEN_PORT}/" /etc/apache2/ports.conf \
 && sed -ri "s#<VirtualHost \*:80>#<VirtualHost *:\${APACHE_LISTEN_PORT}>#" /etc/apache2/sites-available/000-default.conf

EXPOSE 10000

CMD ["apache2-foreground"]
