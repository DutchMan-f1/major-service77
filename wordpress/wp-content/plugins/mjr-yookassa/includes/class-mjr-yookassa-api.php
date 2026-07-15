<?php
/**
 * Тонкий клиент REST API ЮKassa (v3).
 *
 * @package MJR_YooKassa
 */

defined( 'ABSPATH' ) || exit;

class MJR_YooKassa_API {

	const BASE = 'https://api.yookassa.ru/v3';

	/** @var string */
	private $shop_id;

	/** @var string */
	private $secret_key;

	/** @var WC_Logger_Interface|null */
	private $logger;

	public function __construct( $shop_id, $secret_key, $logger = null ) {
		$this->shop_id    = trim( (string) $shop_id );
		$this->secret_key = trim( (string) $secret_key );
		$this->logger     = $logger;
	}

	/**
	 * Создать платёж. Возвращает объект платежа (в т.ч. confirmation.confirmation_url).
	 *
	 * @param array $payload Тело запроса.
	 * @return array|WP_Error
	 */
	public function create_payment( array $payload ) {
		return $this->request( 'POST', '/payments', $payload, wp_generate_uuid4() );
	}

	/**
	 * Получить платёж по id (для серверной проверки статуса из вебхука).
	 *
	 * @param string $payment_id
	 * @return array|WP_Error
	 */
	public function get_payment( $payment_id ) {
		return $this->request( 'GET', '/payments/' . rawurlencode( $payment_id ) );
	}

	/**
	 * Создать возврат.
	 *
	 * @param array $payload
	 * @return array|WP_Error
	 */
	public function create_refund( array $payload ) {
		return $this->request( 'POST', '/refunds', $payload, wp_generate_uuid4() );
	}

	/**
	 * Базовый HTTP-запрос с Basic-авторизацией и Idempotence-Key.
	 *
	 * @param string      $method
	 * @param string      $path
	 * @param array|null  $body
	 * @param string|null $idempotence_key
	 * @return array|WP_Error
	 */
	private function request( $method, $path, $body = null, $idempotence_key = null ) {
		if ( '' === $this->shop_id || '' === $this->secret_key ) {
			return new WP_Error( 'yk_no_credentials', 'Не заданы shopId / секретный ключ ЮKassa.' );
		}

		$args = array(
			'method'  => $method,
			'timeout' => 45,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->shop_id . ':' . $this->secret_key ),
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
		);
		if ( $idempotence_key ) {
			$args['headers']['Idempotence-Key'] = $idempotence_key;
		}
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$this->log( "→ {$method} {$path} " . ( null !== $body ? wp_json_encode( $body ) : '' ) );

		$response = wp_remote_request( self::BASE . $path, $args );

		if ( is_wp_error( $response ) ) {
			$this->log( '✗ transport: ' . $response->get_error_message(), 'error' );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$json = json_decode( $raw, true );

		$this->log( "← HTTP {$code} {$raw}" );

		if ( $code < 200 || $code >= 300 ) {
			$msg = is_array( $json ) && ! empty( $json['description'] )
				? $json['description']
				: 'Ошибка ЮKassa (HTTP ' . $code . ').';
			return new WP_Error( 'yk_http_' . $code, $msg, $json );
		}

		if ( ! is_array( $json ) ) {
			return new WP_Error( 'yk_bad_json', 'Некорректный ответ ЮKassa.' );
		}

		return $json;
	}

	private function log( $message, $level = 'debug' ) {
		if ( $this->logger ) {
			$this->logger->log( $level, $message, array( 'source' => 'mjr-yookassa' ) );
		}
	}
}
