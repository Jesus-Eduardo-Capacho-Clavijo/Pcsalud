FROM php:8.2-fpm

# Dependencias del sistema y extensiones PHP necesarias para PDO MySQL
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    && docker-php-ext-install pdo pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Copiamos la configuracion de nginx y el codigo de la app
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY . /var/www/html

# Permisos basicos para runtime
RUN mkdir -p /run/php /var/log/nginx \
    && chown -R www-data:www-data /var/www/html /run/php /var/log/nginx

WORKDIR /var/www/html

# Railway suele trabajar bien con 8080
EXPOSE 8080

# Inicia PHP-FPM en background y deja nginx en foreground
CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
