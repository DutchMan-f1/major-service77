<?php
/**
 * «Мой гараж» — вкладка личного кабинета WooCommerce с сохранёнными авто.
 *
 * @package AutoFitment
 */

defined( 'ABSPATH' ) || exit;

class AF_Garage {

	const ENDPOINT = 'garage';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_endpoint' ) );

		// Только если WooCommerce активен (иначе ЛК не существует).
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu_item' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'endpoint_content' ) );
		add_action( 'query_vars', array( __CLASS__, 'query_vars' ) );

		// Обработка добавления/удаления авто + выбора «активного».
		add_action( 'template_redirect', array( __CLASS__, 'handle_actions' ) );
	}

	public static function register_endpoint() {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	public static function query_vars( $vars ) {
		$vars[] = self::ENDPOINT;
		return $vars;
	}

	/**
	 * Пункт меню ЛК (после «Заказов»).
	 */
	public static function menu_item( $items ) {
		$new = array();
		foreach ( $items as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'orders' === $key ) {
				$new[ self::ENDPOINT ] = 'Мой гараж';
			}
		}
		if ( ! isset( $new[ self::ENDPOINT ] ) ) {
			$new[ self::ENDPOINT ] = 'Мой гараж';
		}
		return $new;
	}

	/**
	 * Содержимое вкладки «Мой гараж».
	 */
	public static function endpoint_content() {
		$user_id  = get_current_user_id();
		$vehicles = self::get_user_vehicles( $user_id );

		echo '<h3>Мой гараж</h3>';
		echo '<p>Сохранённые автомобили. Активный авто автоматически фильтрует каталог под совместимые запчасти.</p>';

		if ( $vehicles ) {
			echo '<table class="shop_table shop_table_responsive"><thead><tr>';
			echo '<th>Автомобиль</th><th>Статус</th><th>Действия</th></tr></thead><tbody>';
			foreach ( $vehicles as $row ) {
				$title = get_the_title( $row->vehicle_id );
				echo '<tr>';
				echo '<td>' . esc_html( $title ? $title : ( $row->label ? $row->label : '#' . $row->vehicle_id ) ) . '</td>';
				echo '<td>' . ( $row->is_primary ? '<strong>Активный</strong>' : '—' ) . '</td>';
				echo '<td>';
				if ( ! $row->is_primary ) {
					echo '<a href="' . esc_url( self::action_url( 'set_primary', $row->vehicle_id ) ) . '">Сделать активным</a> | ';
				}
				echo '<a href="' . esc_url( self::action_url( 'remove', $row->vehicle_id ) ) . '">Удалить</a>';
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p><em>Пока нет сохранённых автомобилей.</em></p>';
		}
	}

	/**
	 * URL действия с nonce.
	 */
	protected static function action_url( $action, $vehicle_id ) {
		return wp_nonce_url(
			add_query_arg( array( 'af_action' => $action, 'vehicle' => $vehicle_id ), wc_get_account_endpoint_url( self::ENDPOINT ) ),
			'af_garage_' . $action . '_' . $vehicle_id
		);
	}

	/**
	 * Обработка действий гаража.
	 */
	public static function handle_actions() {
		if ( ! is_user_logged_in() || empty( $_GET['af_action'] ) ) {
			return;
		}
		$action     = sanitize_key( $_GET['af_action'] );
		$vehicle_id = isset( $_GET['vehicle'] ) ? absint( $_GET['vehicle'] ) : 0;
		$user_id    = get_current_user_id();

		if ( ! $vehicle_id || ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'af_garage_' . $action . '_' . $vehicle_id ) ) {
			return;
		}

		switch ( $action ) {
			case 'add':
				self::add_vehicle( $user_id, $vehicle_id );
				break;
			case 'remove':
				self::remove_vehicle( $user_id, $vehicle_id );
				break;
			case 'set_primary':
				self::set_primary( $user_id, $vehicle_id );
				break;
		}

		if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
			wp_safe_redirect( wc_get_account_endpoint_url( self::ENDPOINT ) );
			exit;
		}
	}

	/* ---------- CRUD гаража ---------- */

	public static function add_vehicle( $user_id, $vehicle_id, $label = null ) {
		global $wpdb;
		$table   = af_table( 'user_garage' );
		$primary = self::get_primary_vehicle( $user_id ) ? 0 : 1; // первый авто — сразу активный
		return $wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$table} (user_id, vehicle_id, label, is_primary) VALUES (%d, %d, %s, %d)",
			$user_id, $vehicle_id, $label, $primary
		) );
	}

	public static function remove_vehicle( $user_id, $vehicle_id ) {
		global $wpdb;
		$table = af_table( 'user_garage' );
		return $wpdb->delete( $table, array( 'user_id' => $user_id, 'vehicle_id' => $vehicle_id ), array( '%d', '%d' ) );
	}

	public static function set_primary( $user_id, $vehicle_id ) {
		global $wpdb;
		$table = af_table( 'user_garage' );
		$wpdb->update( $table, array( 'is_primary' => 0 ), array( 'user_id' => $user_id ), array( '%d' ), array( '%d' ) );
		$wpdb->update( $table, array( 'is_primary' => 1 ), array( 'user_id' => $user_id, 'vehicle_id' => $vehicle_id ), array( '%d' ), array( '%d', '%d' ) );
	}

	public static function get_user_vehicles( $user_id ) {
		global $wpdb;
		$table = af_table( 'user_garage' );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE user_id = %d ORDER BY is_primary DESC, created_at DESC",
			$user_id
		) );
	}

	public static function get_primary_vehicle( $user_id ) {
		global $wpdb;
		$table = af_table( 'user_garage' );
		return $wpdb->get_var( $wpdb->prepare(
			"SELECT vehicle_id FROM {$table} WHERE user_id = %d AND is_primary = 1 LIMIT 1",
			$user_id
		) );
	}
}
