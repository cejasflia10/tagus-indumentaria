# Imagen mínima con PHP CLI
FROM php:8.2-cli

# Directorio de trabajo
WORKDIR /app

# Copiar el proyecto
COPY . /app

# Render inyecta $PORT; exponemos un puerto por defecto
EXPOSE 10000

# Iniciar el servidor embebido apuntando a /public
CMD ["sh", "-lc", "php -S 0.0.0.0:${PORT:-10000} -t public"]
