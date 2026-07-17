# WordPress (SQLite) на nginx + PHP-FPM 8.3 — без Apache, значит без MPM-ошибки.
FROM php:8.3-fpm

# nginx, envsubst (gettext-base) и системные библиотеки для PHP-расширений.
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx gettext-base \
        libsqlite3-dev \
        libpng-dev libjpeg-dev libfreetype6-dev \
        libzip-dev libonig-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_sqlite gd mbstring zip exif intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Параметры PHP под магазин/загрузки.
RUN { \
        echo 'upload_max_filesize=64M'; \
        echo 'post_max_size=64M'; \
        echo 'memory_limit=256M'; \
        echo 'max_execution_time=120'; \
    } > /usr/local/etc/php/conf.d/wp.ini

# Шаблон конфига nginx (порт подставится из $PORT при старте).
COPY nginx-wp.conf.template /etc/nginx/wp.conf.template

# Файлы сайта.
COPY wordpress/ /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# Railway передаёт порт в $PORT. Подставляем его в конфиг nginx,
# запускаем PHP-FPM в фоне и nginx на переднем плане.
CMD ["sh", "-c", "envsubst '${PORT}' < /etc/nginx/wp.conf.template > /etc/nginx/sites-enabled/default && php-fpm -D && nginx -g 'daemon off;'"]
