<?php
/**
 * Plugin Name:       Auto Fitment & Baz-on Sync
 * Plugin URI:        https://example.com/
 * Description:        Подбор запчастей по автомобилю (фитмент), «Гараж» пользователя и синхронизация каталога с сервисом baz-on (CSV каждые 30 минут).
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Arising
 * Text Domain:       auto-fitment
 * Domain Path:       /languages
 *
 * @package AutoFitment
 */

defined( 'ABSPATH' ) || exit;

define( 'AF_VERSION', '0.3.4' );
define( 'AF_PLUGIN_FILE', __FILE__ );
define( 'AF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AF_OPTION_SETTINGS', 'af_bazon_settings' );
define( 'AF_OPTION_STATUS', 'af_bazon_status' );
define( 'AF_CRON_HOOK', 'af_bazon_sync_event' );
define( 'AF_CRON_INTERVAL', 'af_every_30_minutes' );

/**
 * Таблицы БД. Реальные имена собираются с префиксом $wpdb в AF_Install.
 */
function af_table( $name ) {
	global $wpdb;
	return $wpdb->prefix . $name;
}

/**
 * Загрузка классов плагина.
 */
require_once AF_PLUGIN_DIR . 'includes/class-af-logger.php';
require_once AF_PLUGIN_DIR . 'includes/class-af-install.php';
require_once AF_PLUGIN_DIR . 'includes/class-af-post-types.php';
require_once AF_PLUGIN_DIR . 'includes/class-af-fitment.php';
require_once AF_PLUGIN_DIR . 'includes/class-af-garage.php';
require_once AF_PLUGIN_DIR . 'includes/class-af-frontend.php';
require_once AF_PLUGIN_DIR . 'includes/class-af-bazon-importer.php';
require_once AF_PLUGIN_DIR . 'includes/class-af-cron.php';

if ( is_admin() ) {
	require_once AF_PLUGIN_DIR . 'includes/class-af-admin.php';
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once AF_PLUGIN_DIR . 'includes/class-af-cli.php';
}

/**
 * Активация / деактивация.
 */
register_activation_hook( __FILE__, array( 'AF_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AF_Install', 'deactivate' ) );

/**
 * Инициализация после загрузки всех плагинов (нужно, чтобы дождаться WooCommerce).
 */
add_action( 'plugins_loaded', 'af_bootstrap' );
function af_bootstrap() {
	AF_Post_Types::init();
	AF_Fitment::init();
	AF_Garage::init();
	AF_Frontend::init();
	AF_Cron::init();

	if ( is_admin() ) {
		AF_Admin::init();
	}

	// Мягкое предупреждение, если WooCommerce не активен — часть функций (товары, ЛК) не работает.
	if ( is_admin() && ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-warning"><p><strong>Auto Fitment:</strong> для работы каталога, корзины и синхронизации baz-on требуется активный <strong>WooCommerce</strong>.</p></div>';
		} );
	}
}
