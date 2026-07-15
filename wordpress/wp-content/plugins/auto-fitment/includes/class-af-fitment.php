<?php
/**
 * Фитмент: связь товар <-> автомобиль и фильтрация каталога по выбранному авто.
 *
 * @package AutoFitment
 */

defined( 'ABSPATH' ) || exit;

class AF_Fitment {

	public static function init() {
		// Фильтрация архива товаров по ?vehicle=ID (или по «primary» авто из гаража).
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_shop_by_vehicle' ) );
	}

	/**
	 * Привязать товар к автомобилю.
	 */
	public static function link( $product_id, $vehicle_id, $note = null ) {
		global $wpdb;
		$table = af_table( 'part_fitment' );
		return $wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$table} (product_id, vehicle_id, note) VALUES (%d, %d, %s)",
			$product_id, $vehicle_id, $note
		) );
	}

	/**
	 * Удалить связь.
	 */
	public static function unlink( $product_id, $vehicle_id ) {
		global $wpdb;
		$table = af_table( 'part_fitment' );
		return $wpdb->delete( $table, array(
			'product_id' => $product_id,
			'vehicle_id' => $vehicle_id,
		), array( '%d', '%d' ) );
	}

	/**
	 * ID товаров, совместимых с указанным авто.
	 *
	 * @return int[]
	 */
	public static function product_ids_for_vehicle( $vehicle_id ) {
		global $wpdb;
		$table = af_table( 'part_fitment' );
		$ids   = $wpdb->get_col( $wpdb->prepare(
			"SELECT product_id FROM {$table} WHERE vehicle_id = %d",
			$vehicle_id
		) );
		return array_map( 'intval', $ids );
	}

	/**
	 * ID автомобилей, к которым подходит товар.
	 *
	 * @return int[]
	 */
	public static function vehicle_ids_for_product( $product_id ) {
		global $wpdb;
		$table = af_table( 'part_fitment' );
		$ids   = $wpdb->get_col( $wpdb->prepare(
			"SELECT vehicle_id FROM {$table} WHERE product_id = %d",
			$product_id
		) );
		return array_map( 'intval', $ids );
	}

	/**
	 * Текущий выбранный авто: из GET-параметра, cookie или primary-авто гаража.
	 */
	public static function current_vehicle_id() {
		if ( isset( $_GET['vehicle'] ) ) {
			return absint( $_GET['vehicle'] );
		}
		if ( ! empty( $_COOKIE['af_vehicle'] ) ) {
			return absint( $_COOKIE['af_vehicle'] );
		}
		if ( is_user_logged_in() ) {
			$primary = AF_Garage::get_primary_vehicle( get_current_user_id() );
			if ( $primary ) {
				return (int) $primary;
			}
		}
		return 0;
	}

	/**
	 * Ограничить выдачу магазина товарами под выбранный авто.
	 */
	public static function filter_shop_by_vehicle( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		// Работаем только на витрине магазина/категорий.
		if ( ! ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) ) ) {
			return;
		}

		$vehicle_id = self::current_vehicle_id();
		if ( ! $vehicle_id ) {
			return;
		}

		$product_ids = self::product_ids_for_vehicle( $vehicle_id );
		// Пустой массив -> показываем «ничего не найдено» вместо всего каталога.
		$query->set( 'post__in', $product_ids ? $product_ids : array( 0 ) );
	}
}
