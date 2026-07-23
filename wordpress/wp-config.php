<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'lL?g.+~zaKU%Ccz5!}4uutL7@/a)MWV+iYYwCEmJgVnj%9TW$TIMwDm*E@&|:qQw' );
define( 'SECURE_AUTH_KEY',   'aNl_zaP7)$-y9R]m_g^zgEMsLPKj*gv.eYea&W6DwpuYFUn&fVz2^iz9ZoPzc%I]' );
define( 'LOGGED_IN_KEY',     'c~3F!|jC8qz,-FzN8z6@.$Iu+d`_zPuJ%@Nm+o&d%L(zc:%(!DLy=E_p;w!6q$;|' );
define( 'NONCE_KEY',         '.F%HkttX,7VP@6dde~sy_xZ!c+6Ay92|OhL[{$NP9Lc@pWu4a9$_{?~m=.!*BEU#' );
define( 'AUTH_SALT',         'K)MG1vd3(*d!p`SX|OA9J.QjT(M-l@]*0!Y4[!L*Y.YE,*BMB(!cR4P0AJF<BYi^' );
define( 'SECURE_AUTH_SALT',  '_8-l?`5@4<I$zQ3hH~M(gi*m#`)?pf1s*|/9hnUm<k3#5k4V<&)#,F~R[/CJ(%SX' );
define( 'LOGGED_IN_SALT',    'VoM?;N<<Y|0#x@^JBPJ< Z=qaho2>c2}5.+TIV_XeV(?su}n(aaCd#aI1![tR0-0' );
define( 'NONCE_SALT',        't&9D&B50*t[~1m1zvz<!pf!pw^zMJ{&gOci#p,5)fAOr{:Ph99Pyw+h`-5>-F$OH' );
define( 'WP_CACHE_KEY_SALT', 'jW0QEhH7631ogh&4FKnBm(T]mcU~mh{CECe,.9%7+fpkX:]N^%V0RV%Q^<BBw*s/' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/*
 * Путь к базе SQLite.
 * Если задана переменная окружения DB_DIR (напр. постоянный диск Railway
 * /data), база берётся оттуда и переживает пересборки контейнера.
 * Если переменной нет — используется путь по умолчанию (локальная разработка).
 */
$mjr_db_dir = getenv( 'DB_DIR' );
if ( $mjr_db_dir && ! defined( 'DB_DIR' ) ) {
	define( 'DB_DIR', $mjr_db_dir );
}


/* Add any custom values between this line and the "stop editing" line. */

/* --- Адрес сайта: ТОЛЬКО из доверенного списка хостов (защита от подмены Host-заголовка:
   иначе можно отравить ссылки сброса пароля). Новый домен — добавляй в $mjr_allowed_hosts. --- */
$mjr_allowed_hosts = array(
	'major-service77-production-ab5b.up.railway.app',
	'localhost',
	'localhost:8080',
	'127.0.0.1',
	'127.0.0.1:8080',
);
$mjr_host = ( ! empty( $_SERVER['HTTP_HOST'] ) && in_array( $_SERVER['HTTP_HOST'], $mjr_allowed_hosts, true ) )
	? $_SERVER['HTTP_HOST']
	: $mjr_allowed_hosts[0];
$mjr_https = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] )
	|| ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO'] );
if ( $mjr_https ) {
	$_SERVER['HTTPS'] = 'on'; // корректный HTTPS за прокси/CDN хостинга
}
$mjr_scheme = $mjr_https ? 'https' : 'http';
define( 'WP_HOME', $mjr_scheme . '://' . $mjr_host );
define( 'WP_SITEURL', $mjr_scheme . '://' . $mjr_host );

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'DISABLE_WP_CRON', true );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
