<?php
/**
 * Планировщик: запуск синхронизации baz-on каждые 30 минут.
 *
 * @package AutoFitment
 */

defined( 'ABSPATH' ) || exit;

class AF_Cron {

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_interval' ) );
		add_action( AF_CRON_HOOK, array( __CLASS__, 'run' ) );

		// Гарантируем, что событие запланировано (на случай, если плагин обновляли без реактивации).
		add_action( 'init', array( __CLASS__, 'maybe_schedule' ) );
	}

	/**
	 * Кастомный интервал «каждые 30 минут».
	 */
	public static function add_interval( $schedules ) {
		$schedules[ AF_CRON_INTERVAL ] = array(
			'interval' => 30 * MINUTE_IN_SECONDS,
			'display'  => 'Каждые 30 минут (baz-on)',
		);
		return $schedules;
	}

	public static function schedule() {
		if ( ! wp_next_scheduled( AF_CRON_HOOK ) ) {
			wp_schedule_event( time() + 60, AF_CRON_INTERVAL, AF_CRON_HOOK );
		}
	}

	public static function maybe_schedule() {
		self::schedule();
	}

	public static function unschedule() {
		$timestamp = wp_next_scheduled( AF_CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, AF_CRON_HOOK );
		}
		wp_clear_scheduled_hook( AF_CRON_HOOK );
	}

	/**
	 * Обработчик события cron.
	 */
	public static function run() {
		$importer = new AF_Bazon_Importer();
		$importer->run_sync();
	}
}
