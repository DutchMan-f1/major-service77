<?php
/**
 * Plugin Name: MAJOR SERVICE77 — ЮKassa
 * Description: Платёжный шлюз ЮKassa (YooKassa) для WooCommerce: оплата картой/СБП с редиректом, вебхуки с серверной проверкой статуса, возвраты и чеки 54-ФЗ.
 * Version:     1.0.0
 * Author:      MAJOR SERVICE77
 * Text Domain: mjr-yookassa
 * Requires PHP: 7.4
 *
 * @package MJR_YooKassa
 */

defined( 'ABSPATH' ) || exit;

define( 'MJR_YK_VERSION', '1.0.0' );
define( 'MJR_YK_FILE', __FILE__ );

/**
 * Совместимость с HPOS (хранение заказов в своих таблицах).
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', MJR_YK_FILE, true );
		}
	}
);

/**
 * Если WooCommerce не активен — предупреждаем и не грузимся.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-error"><p><b>MAJOR SERVICE77 — ЮKassa</b>: требуется активный WooCommerce.</p></div>';
				}
			);
			return;
		}

		require_once __DIR__ . '/includes/class-mjr-yookassa-api.php';
		require_once __DIR__ . '/includes/class-mjr-yookassa-gateway.php';

		add_filter(
			'woocommerce_payment_gateways',
			function ( $gateways ) {
				$gateways[] = 'WC_Gateway_MJR_YooKassa';
				return $gateways;
			}
		);

		// На оформлении оплата должна быть только картой через ЮKassa.
		// - Всегда убираем «Перевод на карту» (bacs) и чеки (cheque).
		// - Если ЮKassa настроена и доступна — оставляем ТОЛЬКО её (и убираем заглушку cod).
		// - Пока ЮKassa не настроена — оставляем cod как единственный вариант,
		//   чтобы оформление не осталось совсем без способа оплаты.
		add_filter(
			'woocommerce_available_payment_gateways',
			function ( $gateways ) {
				if ( is_admin() || ! is_array( $gateways ) ) {
					return $gateways;
				}
				unset( $gateways['bacs'], $gateways['cheque'] );
				if ( isset( $gateways['mjr_yookassa'] ) ) {
					return array( 'mjr_yookassa' => $gateways['mjr_yookassa'] );
				}
				return $gateways;
			}
		);
	},
	11
);

/**
 * Ссылка «Настроить» на странице плагинов.
 */
add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		$url  = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=mjr_yookassa' );
		$link = '<a href="' . esc_url( $url ) . '">Настроить</a>';
		array_unshift( $links, $link );
		return $links;
	}
);
