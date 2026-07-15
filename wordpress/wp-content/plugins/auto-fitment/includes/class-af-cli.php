<?php
/**
 * WP-CLI команды для синхронизации baz-on.
 *
 * Примеры:
 *   wp af sync              — запустить/продолжить синхронизацию (один проход)
 *   wp af sync --full       — полный ресинк с нуля (скачать фид заново)
 *   wp af sync --loop       — гонять проходы до завершения (для ручного/крон-запуска в консоли)
 *   wp af status            — показать статус последней синхронизации
 *
 * @package AutoFitment
 */

defined( 'ABSPATH' ) || exit;

class AF_CLI {

	/**
	 * Запуск синхронизации.
	 *
	 * ## OPTIONS
	 *
	 * [--full]
	 * : Полный ресинк — скачать фид заново.
	 *
	 * [--loop]
	 * : Выполнять проходы подряд до завершения.
	 */
	public function sync( $args, $assoc ) {
		$importer = new AF_Bazon_Importer();

		if ( isset( $assoc['full'] ) ) {
			delete_option( AF_Bazon_Importer::STATE_KEY );
			delete_transient( AF_Bazon_Importer::LOCK_KEY );
			WP_CLI::log( 'Полный ресинк: состояние сброшено.' );
		}

		if ( isset( $assoc['loop'] ) ) {
			$i = 0;
			do {
				$importer->run_sync();
				$state = get_option( AF_Bazon_Importer::STATE_KEY, array() );
				$i++;
				WP_CLI::log( "Проход #{$i} завершён." );
			} while ( ! empty( $state['active'] ) && $i < 500 );
			WP_CLI::success( 'Синхронизация завершена.' );
			return;
		}

		$importer->run_sync();
		WP_CLI::success( 'Проход выполнен.' );
	}

	/**
	 * Статус синхронизации.
	 */
	public function status() {
		$status = AF_Logger::get_status();
		WP_CLI::log( print_r( $status, true ) );
	}
}

WP_CLI::add_command( 'af', 'AF_CLI' );
