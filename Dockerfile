# WordPress (SQLite) на nginx + PHP-FPM 8.3 — без Apache, значит без MPM-ошибки.
FROM php:8.3-fpm

# nginx, envsubst (gettext-base) и системные библиотеки для PHP-расширений.
RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx gettext-base curl \
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
        echo 'display_errors=Off'; \
        echo 'log_errors=On'; \
    } > /usr/local/etc/php/conf.d/wp.ini

# PHP-FPM по умолчанию очищает окружение — разрешаем видеть переменные Railway
# (нужно для DB_DIR: путь к базе на постоянном диске).
RUN echo 'clear_env = no' >> /usr/local/etc/php-fpm.d/www.conf

# Шаблон конфига nginx (порт подставится из $PORT при старте).
COPY nginx-wp.conf.template /etc/nginx/wp.conf.template

# Файлы сайта.
COPY wordpress/ /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# Railway передаёт порт в $PORT. Подставляем его в конфиг nginx,
# запускаем PHP-FPM в фоне и nginx на переднем плане.
# nginx-конфиг с портом, PHP-FPM в фоне, «крон» синхронизации baz-on каждые 10 мин, nginx на переднем плане.
CMD ["sh", "-c", "if [ -n \"$DB_DIR\" ]; then mkdir -p \"$DB_DIR\"; if [ ! -f \"$DB_DIR/.ht.sqlite\" ] && [ -f /var/www/html/wp-content/database/.ht.sqlite ] && [ ! -L /var/www/html/wp-content/database/.ht.sqlite ]; then cp /var/www/html/wp-content/database/.ht.sqlite \"$DB_DIR/.ht.sqlite\"; fi; rm -f /var/www/html/wp-content/database/.ht.sqlite; ln -s \"$DB_DIR/.ht.sqlite\" /var/www/html/wp-content/database/.ht.sqlite; chown -R www-data:www-data \"$DB_DIR\"; chown -h www-data:www-data /var/www/html/wp-content/database/.ht.sqlite; fi && envsubst '${PORT}' < /etc/nginx/wp.conf.template > /etc/nginx/sites-enabled/default && php-fpm -D && ( while true; do sleep 600; curl -fsS -m 300 \"http://127.0.0.1:${PORT}/?mjr_cron=1\" >/dev/null 2>&1 || true; done & ) && nginx -g 'daemon off;'"]
