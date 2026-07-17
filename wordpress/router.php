<?php
/**
 * Роутер для встроенного PHP-сервера (php -S) под WordPress с ЧПУ.
 * Отдаёт существующие файлы напрямую, остальное направляет в index.php.
 */
$uri  = urldecode( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );
$file = __DIR__ . $uri;

// Безопасность для публичного доступа (php -S не применяет .htaccess):
// закрываем базу SQLite, .ht* и служебные файлы от прямого скачивания.
if ( preg_match( '#^/wp-content/database(/|$)#i', $uri )
	|| preg_match( '#\.(sqlite|sqlite3|sql|log)$#i', $uri )
	|| preg_match( '#(^|/)\.(ht|git)#i', $uri )
	|| preg_match( '#^/wp-config\.php$#i', $uri ) ) {
	http_response_code( 403 );
	exit( 'Forbidden' );
}

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
