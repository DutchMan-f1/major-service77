<?php
/**
 * Plugin Name: MAJOR SERVICE77 — CMS
 * Description: Управление контентом сайта без правки кода: баннер «Не нашли запчасть», подвал, вкладки товара (Доставка/Оплата/Гарантия), фото и описание моделей авто. Плюс ограниченная роль «Контент-менеджер».
 * Version:     1.0.0
 * Author:      MAJOR SERVICE77
 * Text Domain: mjr-cms
 * Requires PHP: 7.4
 *
 * @package MJR_CMS
 */

defined( 'ABSPATH' ) || exit;

define( 'MJR_CMS_VER', '1.0.0' );
define( 'MJR_CMS_FILE', __FILE__ );
define( 'MJR_CMS_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Значение контентного поля CMS. Пусто/не задано → возвращается $default
 * (в шаблонах темы туда передаётся текст по умолчанию).
 *
 * @param string $key
 * @param mixed  $default
 * @return mixed
 */
function mjr_cms( $key, $default = '' ) {
	$o = get_option( 'mjr_cms', array() );
	if ( is_array( $o ) && isset( $o[ $key ] ) && '' !== $o[ $key ] && null !== $o[ $key ] ) {
		return $o[ $key ];
	}
	return $default;
}

require_once MJR_CMS_DIR . 'includes/class-mjr-cms-settings.php';
require_once MJR_CMS_DIR . 'includes/class-mjr-cms-model-meta.php';
require_once MJR_CMS_DIR . 'includes/class-mjr-cms-role.php';

add_action( 'plugins_loaded', function () {
	MJR_CMS_Settings::init();
	MJR_CMS_Model_Meta::init();
	MJR_CMS_Role::init();
} );

register_activation_hook( __FILE__, array( 'MJR_CMS_Role', 'add_role' ) );
register_deactivation_hook( __FILE__, array( 'MJR_CMS_Role', 'remove_role' ) );

// Ссылка «Настроить» на странице плагинов.
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=mjr-cms' ) ) . '">Открыть</a>' );
	return $links;
} );
