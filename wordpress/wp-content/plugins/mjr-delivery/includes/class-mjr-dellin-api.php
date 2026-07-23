<?php
/**
 * Клиент API «Деловые Линии» (dev.dellin.ru).
 * Все запросы — POST JSON, обязательный параметр appkey.
 *
 * @package MJR_Delivery
 */

defined( 'ABSPATH' ) || exit;

class MJR_Dellin_API {

	const BASE = 'https://api.dellin.ru';

	/** @var string */
	private $appkey;
	/** @var string */
	private $login;
	/** @var string */
	private $password;

	public function __construct( $appkey, $login = '', $password = '' ) {
		$this->appkey   = trim( (string) $appkey );
		$this->login    = trim( (string) $login );
		$this->password = (string) $password;
	}

	public function has_key() {
		return '' !== $this->appkey;
	}

	/**
	 * Базовый запрос. Возвращает массив ответа или WP_Error.
	 *
	 * @param string $path
	 * @param array  $body
	 * @return array|WP_Error
	 */
	public function request( $path, array $body = array() ) {
		if ( '' === $this->appkey ) {
			return new WP_Error( 'dl_no_key', 'Не задан appkey Деловых Линий.' );
		}
		$body['appkey'] = $this->appkey;

		$resp = wp_remote_post( self::BASE . $path, array(
			'timeout' => 30,
			'headers' => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
		) );

		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$code = (int) wp_remote_retrieve_response_code( $resp );
		$json = json_decode( wp_remote_retrieve_body( $resp ), true );

		if ( $code < 200 || $code >= 300 ) {
			$msg = 'Деловые Линии: HTTP ' . $code;
			if ( is_array( $json ) && ! empty( $json['errors'] ) ) {
				$first = reset( $json['errors'] );
				if ( is_array( $first ) && ! empty( $first['title'] ) ) {
					$msg = $first['title'];
				} elseif ( is_string( $first ) ) {
					$msg = $first;
				}
			}
			return new WP_Error( 'dl_http_' . $code, $msg, $json );
		}
		return is_array( $json ) ? $json : array();
	}

	/**
	 * Поиск города/населённого пункта — возвращает KLADR-код (нужен калькулятору).
	 *
	 * @param string $query город, напр. «Казань»
	 * @return string|WP_Error KLADR-код или ошибка
	 */
	public function city_code( $query ) {
		$r = $this->request( '/v2/kladr.json', array( 'q' => $query, 'limit' => 1 ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		if ( ! empty( $r['cities'][0]['code'] ) ) {
			return $r['cities'][0]['code'];
		}
		return new WP_Error( 'dl_city', 'Город не найден: ' . $query );
	}

	/**
	 * Расчёт стоимости и срока доставки между городами (по KLADR-кодам).
	 *
	 * @param string $from_kladr KLADR отправителя (город склада)
	 * @param string $to_kladr   KLADR получателя
	 * @param float  $weight_kg  общий вес, кг
	 * @param float  $volume_m3  общий объём, м³
	 * @return array|WP_Error нормализованный результат {price, days} или ошибка
	 */
	public function calculate( $from_kladr, $to_kladr, $weight_kg, $volume_m3 ) {
		$body = array(
			'delivery' => array(
				'deliveryType' => array( 'type' => 'auto' ),
				'derival'      => array( 'variant' => 'terminal', 'city' => $from_kladr ),
				'arrival'      => array( 'variant' => 'terminal', 'city' => $to_kladr ),
			),
			'cargo'    => array(
				'quantity'    => 1,
				'totalWeight' => (float) $weight_kg,
				'totalVolume' => max( 0.01, (float) $volume_m3 ),
			),
		);
		$r = $this->request( '/v2/calculator.json', $body );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		// Нормализуем ключевые поля (структура ответа ДЛ довольно объёмная).
		$price = null;
		if ( isset( $r['data']['price'] ) ) {
			$price = $r['data']['price'];
		} elseif ( isset( $r['data']['orderPrice'] ) ) {
			$price = $r['data']['orderPrice'];
		}
		$days = $r['data']['orderDates']['giveout'] ?? ( $r['data']['deliveryTerm'] ?? null );
		return array( 'price' => $price, 'days' => $days, 'raw' => $r );
	}

	/**
	 * Пробный вызов для проверки appkey — поиск города «Москва».
	 *
	 * @return true|WP_Error
	 */
	public function test_key() {
		$r = $this->city_code( 'Москва' );
		return is_wp_error( $r ) ? $r : true;
	}
}
