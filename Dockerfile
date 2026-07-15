# WordPress (SQLite) под Railway: PHP 8.3 + Apache.
FROM php:8.3-apache

# Системные библиотеки для PHP-расширений.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libsqlite3-dev \
        libpng-dev libjpeg-dev libfreetype6-dev \
        libzip-dev libonig-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_sqlite gd mbstring zip exif intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Ровно один MPM: mod_php требует prefork (иначе "More than one MPM loaded").
# ЧПУ-ссылки + чтение .htaccess.
RUN a2dismod mpm_event mpm_worker 2>/dev/null || true; \
    a2enmod mpm_prefork rewrite headers \
    && printf '<Directory /var/www/html/>\n\tAllowOverride All\n\tRequire all granted\n</Directory>\n' \
        > /etc/apache2/conf-available/wp-override.conf \
    && a2enconf wp-override \
    && echo 'ServerName localhost' >> /etc/apache2/apache2.conf

# Параметры PHP под магазин/загрузки.
RUN { \
        echo 'upload_max_filesize=64M'; \
        echo 'post_max_size=64M'; \
        echo 'memory_limit=256M'; \
        echo 'max_execution_time=120'; \
    } > /usr/local/etc/php/conf.d/wp.ini

# Файлы сайта (папка wordpress/ проекта → корень веб-сервера).
COPY wordpress/ /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# Railway передаёт порт в $PORT — подставляем его в конфиг Apache при старте.
CMD ["sh", "-c", "sed -ri \"s/^Listen 80$/Listen ${PORT:-80}/\" /etc/apache2/ports.conf && sed -ri \"s/:80>/:${PORT:-80}>/\" /etc/apache2/sites-available/000-default.conf && exec apache2-foreground"]
