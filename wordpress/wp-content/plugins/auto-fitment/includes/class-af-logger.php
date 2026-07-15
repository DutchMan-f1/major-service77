<?php
/**
 * Простой файловый логгер + сохранение последнего статуса синхронизации.
 *
 * @package AutoFitment
 */

defined( 'ABSPATH' ) || exit;

class AF_Logger {

	/**
	 * Путь к файлу лога в uploads/auto-fitment/.
	 */
	protected static function log_file() {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'auto-fitment';
		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
			// Защита директории от листинга.
			@file_put_contents( $dir . '/index.html', '' );
			@file_put_contents( $dir . '/.htaccess', "Deny from all\n" );
		}
		return $dir . '/sync.log';
	}

	/**
	 * Записать строку в лог.
	 *
	 * @param string $message
	 * @param string $level info|warning|error
	 */
	public static function log( $message, $level = 'info' ) {
		$line = sprintf( "[%s] %s: %s\n", current_time( 'mysql' ), strtoupper( $level ), $message );
		@file_put_contents( self::log_file(), $line, FILE_APPEND | LOCK_EX );

		// Ротация: если лог > 2 МБ, обрезаем до последних ~1000 строк.
		$file = self::log_file();
		if ( file_exists( $file ) && filesize( $file ) > 2 * MB_IN_BYTES ) {
			$lines = array_slice( file( $file ), -1000 );
			@file_put_contents( $file, implode( '', $lines ) );
		}
	}

	/**
	 * Последние N строк лога (для вывода в админке).
	 */
	public static function tail( $count = 100 ) {
		$file = self::log_file();
		if ( ! file_exists( $file ) ) {
			return '';
		}
		$lines = file( $file );
		return implode( '', array_slice( $lines, -$count ) );
	}

	/**
	 * Сохранить/обновить статус текущей или последней синхронизации.
	 */
	public static function set_status( array $data ) {
		$status = get_option( AF_OPTION_STATUS, array() );
		update_option( AF_OPTION_STATUS, array_merge( $status, $data ), false );
	}

	public static function get_status() {
		return get_option( AF_OPTION_STATUS, array() );
	}
}
