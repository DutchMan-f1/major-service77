<?php
/**
 * Роутер для встроенного PHP-сервера (php -S) под WordPress с ЧПУ.
 * Отдаёт существующие файлы напрямую, остальное направляет в index.php.
 */
$uri  = urldecode( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );
$file = __DIR__ . $uri;

// Реальный статический файл (css/js/img и т.п.) — отдать как есть.
if ( $uri !== '/' && file_exists( $file ) && ! is_dir( $file ) ) {
	return false;
}

// wp-admin / wp-login и другие php-точки входа.
if ( preg_match( '#^/(wp-admin|wp-includes|wp-content)/.*\.php$#', $uri ) && file_exists( $file ) ) {
	require $file;
	return true;
}
if ( preg_match( '#^/wp-(login|cron|comments-post|signup|activate|trackback|links-opml|mail)\.php$#', $uri ) && file_exists( $file ) ) {
	require $file;
	return true;
}
if ( '/wp-admin/' === $uri || '/wp-admin' === $uri ) {
	require __DIR__ . '/wp-admin/index.php';
	return true;
}

require __DIR__ . '/index.php';
