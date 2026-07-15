<?php
/**
 * Установка: таблицы БД, дефолтные настройки, регистрация эндпоинтов, расписание cron.
 *
 * @package AutoFitment
 */

defined( 'ABSPATH' ) || exit;

class AF_Install {

	/**
	 * Активация плагина.
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_settings();

		// Регистрируем типы записей и эндпоинт «Гараж», затем сбрасываем правила ЧПУ.
		AF_Post_Types::register_post_type();
		AF_Post_Types::register_taxonomies();
		AF_Garage::register_endpoint();
		flush_rewrite_rules();

		// Запускаем регулярную синхронизацию каждые 30 минут.
		AF_Cron::schedule();
	}

	/**
	 * Деактивация плагина.
	 */
	public static function deactivate() {
		AF_Cron::unschedule();
		flush_rewrite_rules();
		// Таблицы и настройки НЕ удаляем — данные фитмента и гаражей должны пережить деактивацию.
	}

	/**
	 * Создание кастомных таблиц через dbDelta.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$fitment         = af_table( 'part_fitment' );
		$garage          = af_table( 'user_garage' );

		// Связь товар <-> совместимый автомобиль (many-to-many).
		$sql_fitment = "CREATE TABLE {$fitment} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			vehicle_id BIGINT UNSIGNED NOT NULL,
			note VARCHAR(255) NULL DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_product_vehicle (product_id, vehicle_id),
			KEY idx_product (product_id),
			KEY idx_vehicle (vehicle_id)
		) {$charset_collate};";

		// «Гараж» пользователя — сохранённые автомобили.
		$sql_garage = "CREATE TABLE {$garage} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL,
			vehicle_id BIGINT UNSIGNED NOT NULL,
			label VARCHAR(191) NULL DEFAULT NULL,
			is_primary TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY uq_user_vehicle (user_id, vehicle_id),
			KEY idx_user (user_id)
		) {$charset_collate};";

		dbDelta( $sql_fitment );
		dbDelta( $sql_garage );

		update_option( 'af_db_version', AF_VERSION );
	}

	/**
	 * Дефолтные настройки синхронизации baz-on.
	 * Маппинг колонок подобран под типовой прайс автозапчастей и настраивается в админке.
	 */
	public static function set_default_settings() {
		if ( get_option( AF_OPTION_SETTINGS ) ) {
			return;
		}
		// Дефолты под реальную схему фида baz-on/majorzap (21 колонка).
		update_option( AF_OPTION_SETTINGS, AF_Bazon_Importer::default_settings() );
	}
}
