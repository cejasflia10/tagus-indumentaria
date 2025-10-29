# PHP 8.2 CLI
FROM php:8.2-cli

# Paquetes necesarios para compilar/extensiones
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libzip-dev \
    libpng-dev libjpeg-dev libfreetype6-dev \
    unzip \
 && docker-php-ext-install mysqli pdo pdo_mysql curl \
 && docker-php-ext-enable mysqli pdo_mysql curl \
 && rm -rf /var/lib/apt/lists/*

# Carpeta de trabajo
WORKDIR /app

# Copiar el proyecto
COPY . /app

# Puerto por defecto local (Render usa $PORT)
EXPOSE 10000

# Iniciar servidor PHP embebido sirviendo /public y escuchando en $PORT
CMD ["sh", "-lc", "php -S 0.0.0.0:${PORT:-10000} -t public"]
