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
		$r = $this->request( '/v1/public/kladr.json', array( 'q' => $query, 'limit' => 1 ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		// Ответ — массив городов; у первого поле code (25-значный KLADR).
		if ( ! empty( $r[0]['code'] ) ) {
			return $r[0]['code'];
		}
		return new WP_Error( 'dl_city', 'Город не найден: ' . $query );
	}

	/** Список терминалов Деловых Линий (для выбора пункта выдачи). */
	public function terminals() {
		return $this->request( '/v1/public/terminals.json', array() );
	}

	/**
	 * Терминалы ДЛ в конкретном городе (координаты для карты). Полный справочник
	 * (~330 КБ) кэшируется в транзиенте на сутки, чтобы не тянуть его на каждый ввод.
	 *
	 * @param string $city_name город, напр. «Казань»
	 * @return array|WP_Error список точек [id,name,address,lat,lng]
	 */
	public function terminals_in_city( $city_name ) {
		$all = get_transient( 'mjr_dellin_terminals' );
		if ( ! is_array( $all ) ) {
			$r = $this->terminals();
			if ( is_wp_error( $r ) ) {
				return $r;
			}
			$all = isset( $r['city'] ) && is_array( $r['city'] ) ? $r['city'] : array();
			set_transient( 'mjr_dellin_terminals', $all, DAY_IN_SECONDS );
		}
		$needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( trim( (string) $city_name ) ) : strtolower( trim( (string) $city_name ) );
		$out    = array();
		foreach ( $all as $city ) {
			$name = (string) ( $city['name'] ?? '' );
			$hay  = function_exists( 'mb_strtolower' ) ? mb_strtolower( $name ) : strtolower( $name );
			if ( '' !== $needle && false === strpos( $hay, $needle ) ) {
				continue;
			}
			$terms = $city['terminals']['terminal'] ?? ( $city['terminals'] ?? array() );
			foreach ( $terms as $t ) {
				$out[] = array(
					'id'      => (string) ( $t['id'] ?? '' ),
					'name'    => $t['name'] ?? 'Терминал ДЛ',
					'address' => $t['address'] ?? ( $t['fullAddress'] ?? '' ),
					'lat'     => (float) ( $t['latitude'] ?? 0 ),
					'lng'     => (float) ( $t['longitude'] ?? 0 ),
				);
			}
		}
		return $out;
	}

	/** Авторизация по логину/паролю ЛК → sessionID (нужен для создания заявок). */
	public function auth() {
		if ( '' === $this->login || '' === $this->password ) {
			return new WP_Error( 'dl_no_login', 'Не заданы логин/пароль ЛК Деловых Линий.' );
		}
		$r = $this->request( '/v3/auth/login.json', array( 'login' => $this->login, 'password' => $this->password ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return $r['data']['sessionID'] ?? new WP_Error( 'dl_auth', 'Не удалось авторизоваться в Деловых Линиях.' );
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
	public function calculate( $from_terminal_id, $to_kladr, $weight_kg, $volume_m3, $produce_date = '', $arrival_variant = 'terminal', $arrival_addr = '' ) {
		if ( '' === $produce_date ) {
			// Ближайший будний день с завтрашнего (терминал не принимает сб/вс).
			$ts = time() + DAY_IN_SECONDS;
			while ( (int) gmdate( 'N', $ts ) >= 6 ) {
				$ts += DAY_IN_SECONDS;
			}
			$produce_date = gmdate( 'Y-m-d', $ts );
		}
		$side = round( max( 0.1, pow( max( 0.001, (float) $volume_m3 ), 1 / 3 ) ), 2 );
		// Прибытие: 'terminal' — самовывоз с терминала ДЛ в городе клиента;
		// 'address' — курьер до двери (нужна полная строка адреса: город, улица, дом).
		$arrival = ( 'address' === $arrival_variant )
			? array( 'variant' => 'address', 'address' => array( 'search' => (string) $arrival_addr ) )
			: array( 'variant' => 'terminal', 'city' => $to_kladr );
		$body = array(
			'delivery' => array(
				'deliveryType' => array( 'type' => 'auto' ),
				// Отправитель — конкретный терминал ДЛ (город недостаточен); получатель — город (KLADR).
				'derival'      => array( 'produceDate' => $produce_date, 'variant' => 'terminal', 'terminalID' => (int) $from_terminal_id ),
				'arrival'      => $arrival,
			),
			'cargo'    => array(
				// Калькулятор ДЛ требует габариты места, не только объём.
				// Выводим сторону условного куба из общего объёма.
				'quantity'    => 1,
				'length'      => $side,
				'width'       => $side,
				'height'      => $side,
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
