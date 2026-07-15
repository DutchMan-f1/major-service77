<?php
/**
 * Plugin Name: MAJOR SERVICE77 — Доставка (карты пунктов)
 * Description: Красивый выбор способа доставки на оформлении: СДЭК, Яндекс Доставка, 5Post, Почта России. Карта пунктов выдачи (Яндекс.Карты), выбор пункта или доставка до адреса, сохранение в заказ. API-доступы подключаются в настройках.
 * Version:     1.0.0
 * Author:      MAJOR SERVICE77
 * Requires PHP: 7.4
 *
 * @package MJR_Delivery
 */

defined( 'ABSPATH' ) || exit;

define( 'MJR_DELIVERY_VER', '1.0.0' );
define( 'MJR_DELIVERY_FILE', __FILE__ );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', MJR_DELIVERY_FILE, true );
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-error"><p><b>MAJOR SERVICE77 — Доставка</b>: требуется активный WooCommerce.</p></div>';
			} );
			return;
		}
		require_once __DIR__ . '/includes/class-mjr-delivery.php';
		require_once __DIR__ . '/includes/class-mjr-delivery-points.php';
		MJR_Delivery::init();
		MJR_Delivery_Points::init();
	},
	11
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		$url = admin_url( 'admin.php?page=mjr-delivery' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">Настройки</a>' );
		return $links;
	}
);
