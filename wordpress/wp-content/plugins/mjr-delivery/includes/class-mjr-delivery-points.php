<?php
/**
 * Загрузка пунктов выдачи по перевозчикам.
 *
 * Каждый метод возвращает массив точек одного формата:
 *   [ 'id','name','address','lat','lng' ]
 *
 * Реальные вызовы требуют доступов перевозчика (см. настройки). Пока доступов
 * нет и включён демо-режим — отдаём примерные точки, чтобы витрина работала.
 *
 * @package MJR_Delivery
 */

defined( 'ABSPATH' ) || exit;

class MJR_Delivery_Points {

	public static function init() {
		add_action( 'wp_ajax_mjr_delivery_points', array( __CLASS__, 'ajax' ) );
		add_action( 'wp_ajax_nopriv_mjr_delivery_points', array( __CLASS__, 'ajax' ) );
		add_action( 'wp_ajax_mjr_delivery_cost', array( __CLASS__, 'ajax_cost' ) );
		add_action( 'wp_ajax_nopriv_mjr_delivery_cost', array( __CLASS__, 'ajax_cost' ) );
	}

	/* =========================================================
	 *  Расчёт стоимости доставки (пока — Деловые Линии)
	 * ======================================================= */
	public static function ajax_cost() {
		check_ajax_referer( 'mjr_delivery', 'nonce' );

		$carrier = isset( $_POST['carrier'] ) ? sanitize_text_field( wp_unslash( $_POST['carrier'] ) ) : '';
		$city    = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
		$city    = $city ? $city : 'Казань';
		$addr    = isset( $_POST['addr'] ) ? sanitize_text_field( wp_unslash( $_POST['addr'] ) ) : '';

		// Расчёт цены сейчас реализован для Деловых Линий; остальные — пока без цены.
		if ( 'dellin' !== $carrier ) {
			wp_send_json_success( array( 'prices' => array() ) );
		}

		$s = MJR_Delivery::settings();
		if ( '' === $s['dellin_appkey'] || '' === $s['dellin_terminal'] ) {
			wp_send_json_success( array( 'prices' => array(), 'note' => 'Не задан appkey или терминал-отправитель ДЛ.' ) );
		}

		// Вес/объём: baz-on их не передаёт → берём средние из настроек × число позиций.
		$count = ( function_exists( 'WC' ) && WC()->cart ) ? (int) WC()->cart->get_cart_contents_count() : 1;
		$count = max( 1, $count );
		$weight = max( 0.5, $count * (float) $s['dellin_item_w'] );
		$volume = max( 0.01, $count * (float) $s['dellin_item_v'] );

		$search = $addr ? ( $city . ', ' . $addr ) : '';
		$ckey   = 'mjr_dl_cost_' . md5( $city . '|' . $search . '|' . $s['dellin_terminal'] . '|' . $weight . '|' . $volume );
		$cached = get_transient( $ckey );
		if ( is_array( $cached ) ) {
			wp_send_json_success( array( 'prices' => $cached, 'city' => $city, 'cached' => true ) );
		}

		$api   = new MJR_Dellin_API( $s['dellin_appkey'], $s['dellin_login'], $s['dellin_password'] );
		$kladr = $api->city_code( $city );
		if ( is_wp_error( $kladr ) ) {
			wp_send_json_success( array( 'prices' => array(), 'note' => $kladr->get_error_message() ) );
		}

		$prices = array();
		// Самовывоз до терминала — считается по городу.
		$rt = $api->calculate( $s['dellin_terminal'], $kladr, $weight, $volume, '', 'terminal' );
		if ( ! is_wp_error( $rt ) && isset( $rt['price'] ) && is_numeric( $rt['price'] ) ) {
			$prices['terminal'] = (int) round( (float) $rt['price'] );
		}
		// Курьером до адреса — только если клиент указал улицу/дом (нужна полная строка).
		if ( '' !== $search ) {
			$ra = $api->calculate( $s['dellin_terminal'], $kladr, $weight, $volume, '', 'address', $search );
			if ( ! is_wp_error( $ra ) && isset( $ra['price'] ) && is_numeric( $ra['price'] ) ) {
				$prices['address'] = (int) round( (float) $ra['price'] );
			}
		}
		if ( $prices ) {
			set_transient( $ckey, $prices, HOUR_IN_SECONDS );
		}
		wp_send_json_success( array( 'prices' => $prices, 'city' => $city ) );
	}

	public static function ajax() {
		check_ajax_referer( 'mjr_delivery', 'nonce' );

		$carrier = isset( $_POST['carrier'] ) ? sanitize_text_field( wp_unslash( $_POST['carrier'] ) ) : '';
		$city    = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
		$lat     = isset( $_POST['lat'] ) ? (float) $_POST['lat'] : 0;
		$lng     = isset( $_POST['lng'] ) ? (float) $_POST['lng'] : 0;

		// Перемещение карты: определяем город по координатам центра.
		// 1) Точный город через геокодер Яндекса (если задан ключ);
		// 2) иначе — ближайший терминал Деловых Линий (нужен appkey ДЛ).
		if ( $lat && $lng ) {
			$s        = MJR_Delivery::settings();
			$resolved = '';
			if ( '' !== $s['ymaps_geocoder_key'] ) {
				$resolved = self::yandex_city( $lat, $lng, $s['ymaps_geocoder_key'] );
			}
			if ( '' === $resolved && '' !== $s['dellin_appkey'] ) {
				$api = new MJR_Dellin_API( $s['dellin_appkey'] );
				$nc  = $api->nearest_city( $lat, $lng );
				if ( ! is_wp_error( $nc ) && $nc ) {
					$resolved = $nc;
				}
			}
			if ( '' !== $resolved ) {
				$city = $resolved;
			}
		}
		$city = $city ? $city : 'Казань';

		$carriers = MJR_Delivery::carriers();
		if ( ! isset( $carriers[ $carrier ] ) ) {
			wp_send_json_error( array( 'message' => 'Неизвестный перевозчик.' ) );
		}

		$points = array();
		$live   = false;

		switch ( $carrier ) {
			case 'cdek':
				$points = self::cdek( $city );
				break;
			case 'dellin':
				$points = self::dellin( $city );
				break;
			case 'yandex':
				$points = self::yandex( $city );
				break;
			case 'fivepost':
				$points = self::fivepost( $city );
				break;
			case 'pochta':
				$points = self::pochta( $city );
				break;
		}

		if ( is_wp_error( $points ) ) {
			$points = array();
		}
		if ( ! empty( $points ) ) {
			$live = true;
		}

		// Если перевозчик уже настроен (есть ключи) — показываем только реальные точки,
		// без демо: пусто → честное сообщение «нет ПВЗ в этом городе».
		if ( empty( $points ) ) {
			$s = MJR_Delivery::settings();
			$real_configured = ( 'dellin' === $carrier && '' !== $s['dellin_appkey'] )
				|| ( 'cdek' === $carrier && '' !== $s['cdek_account'] );
			if ( $real_configured ) {
				wp_send_json_error( array(
					'message' => 'В городе «' . $city . '» нет пунктов выдачи «' . $carriers[ $carrier ]['label'] . '». Выберите доставку до адреса или другой перевозчик.',
				) );
			} elseif ( 'yes' === $s['demo'] ) {
				$points = self::demo( $carrier, $city );
			} else {
				wp_send_json_error( array( 'message' => 'Пункты не найдены. Проверьте API-доступы перевозчика в настройках.' ) );
			}
		}

		wp_send_json_success( array(
			'points' => array_values( $points ),
			'city'   => $city,
			'live'   => $live,
		) );
	}

	/* =========================================================
	 *  СДЭК — реальный клиент (API v2)
	 * ======================================================= */
	private static function cdek( $city ) {
		$s = MJR_Delivery::settings();
		if ( '' === $s['cdek_account'] || '' === $s['cdek_secret'] ) {
			return array();
		}

		$token = self::cdek_token( $s['cdek_account'], $s['cdek_secret'] );
		if ( is_wp_error( $token ) || ! $token ) {
			return array();
		}

		// Код города по названию.
		$city_resp = wp_remote_get(
			'https://api.cdek.ru/v2/location/cities?' . http_build_query( array( 'city' => $city, 'size' => 1 ) ),
			array( 'timeout' => 25, 'headers' => array( 'Authorization' => 'Bearer ' . $token ) )
		);
		if ( is_wp_error( $city_resp ) ) {
			return array();
		}
		$cities = json_decode( wp_remote_retrieve_body( $city_resp ), true );
		if ( empty( $cities[0]['code'] ) ) {
			return array();
		}
		$city_code = (int) $cities[0]['code'];

		$resp = wp_remote_get(
			'https://api.cdek.ru/v2/deliverypoints?' . http_build_query( array( 'city_code' => $city_code, 'type' => 'ALL' ) ),
			array( 'timeout' => 30, 'headers' => array( 'Authorization' => 'Bearer ' . $token ) )
		);
		if ( is_wp_error( $resp ) ) {
			return array();
		}
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( ! is_array( $data ) ) {
			return array();
		}

		$out = array();
		foreach ( array_slice( $data, 0, 200 ) as $p ) {
			if ( empty( $p['location'] ) ) {
				continue;
			}
			$out[] = array(
				'id'      => (string) ( $p['code'] ?? '' ),
				'name'    => $p['name'] ?? 'Пункт СДЭК',
				'address' => $p['location']['address_full'] ?? ( $p['location']['address'] ?? '' ),
				'lat'     => (float) ( $p['location']['latitude'] ?? 0 ),
				'lng'     => (float) ( $p['location']['longitude'] ?? 0 ),
			);
		}
		return $out;
	}

	private static function cdek_token( $account, $secret ) {
		$cached = get_transient( 'mjr_cdek_token' );
		if ( $cached ) {
			return $cached;
		}
		$resp = wp_remote_post( 'https://api.cdek.ru/v2/oauth/token', array(
			'timeout' => 25,
			'body'    => array(
				'grant_type'    => 'client_credentials',
				'client_id'     => $account,
				'client_secret' => $secret,
			),
		) );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$json = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $json['access_token'] ) ) {
			return new WP_Error( 'cdek_auth', 'Не удалось авторизоваться в СДЭК.' );
		}
		$ttl = isset( $json['expires_in'] ) ? max( 60, (int) $json['expires_in'] - 60 ) : 3000;
		set_transient( 'mjr_cdek_token', $json['access_token'], $ttl );
		return $json['access_token'];
	}

	/* =========================================================
	 *  Деловые Линии — терминалы города как пункты выдачи
	 * ======================================================= */
	private static function dellin( $city ) {
		$s = MJR_Delivery::settings();
		if ( '' === $s['dellin_appkey'] ) {
			return array();
		}
		$api = new MJR_Dellin_API( $s['dellin_appkey'] );
		$pts = $api->terminals_in_city( $city );
		return is_wp_error( $pts ) ? array() : $pts;
	}

	/**
	 * Обратный геокодинг через HTTP «API Геокодера» Яндекса: координаты → город.
	 * Ключ отдельный от JavaScript API. Возвращает '' при любой ошибке.
	 */
	private static function yandex_city( $lat, $lng, $key ) {
		$url = 'https://geocode-maps.yandex.ru/1.x/?' . http_build_query( array(
			'apikey'  => $key,
			'geocode' => $lng . ',' . $lat, // Яндекс ждёт долготу,широту
			'kind'    => 'locality',
			'format'  => 'json',
			'results' => 1,
			'lang'    => 'ru_RU',
		) );
		$resp = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $resp ) ) {
			return '';
		}
		$data    = json_decode( wp_remote_retrieve_body( $resp ), true );
		$members = $data['response']['GeoObjectCollection']['featureMember'] ?? array();
		if ( empty( $members[0]['GeoObject']['name'] ) ) {
			return '';
		}
		return (string) $members[0]['GeoObject']['name'];
	}

	/* =========================================================
	 *  Яндекс Доставка / 5Post / Почта — точки входа под доступы.
	 *  Реализуются по документации перевозчика после получения ключей;
	 *  сейчас без ключей возвращают пусто → сработает демо-фолбэк.
	 * ======================================================= */
	private static function yandex( $city ) {
		$s = MJR_Delivery::settings();
		if ( '' === $s['yandex_token'] ) {
			return array();
		}
		// TODO: Яндекс Доставка API — pickup-points по городу с $s['yandex_token'].
		return array();
	}

	private static function fivepost( $city ) {
		$s = MJR_Delivery::settings();
		if ( '' === $s['fivepost_apikey'] ) {
			return array();
		}
		// TODO: 5Post API — /api/v1/pickuppoints (Bearer $s['fivepost_apikey']).
		return array();
	}

	private static function pochta( $city ) {
		$s = MJR_Delivery::settings();
		if ( '' === $s['pochta_token'] || '' === $s['pochta_key'] ) {
			return array();
		}
		// TODO: Почта России API — отделения по городу (AccessToken + Basic).
		return array();
	}

	/* =========================================================
	 *  Демо-данные (примерные пункты, помечены как демо)
	 * ======================================================= */
	private static function demo( $carrier, $city ) {
		$base = array( 55.796, 49.108 ); // Казань, центр
		$spots = array(
			array( 'ул. Баумана, 44', 0.004, -0.010 ),
			array( 'пр. Победы, 91', -0.020, 0.030 ),
			array( 'ул. Кремлёвская, 12', 0.001, -0.002 ),
			array( 'ул. Декабристов, 133', 0.028, -0.004 ),
			array( 'ул. Павлюхина, 91', -0.010, 0.002 ),
			array( 'пр. Ямашева, 46', 0.030, 0.020 ),
		);
		$names = array(
			'cdek'     => 'Пункт выдачи СДЭК',
			'yandex'   => 'Пункт выдачи Яндекс',
			'fivepost' => 'Постамат 5Post',
			'pochta'   => 'Отделение Почты России',
		);
		$out = array();
		foreach ( $spots as $i => $sp ) {
			$out[] = array(
				'id'      => $carrier . '-demo-' . ( $i + 1 ),
				'name'    => ( $names[ $carrier ] ?? 'Пункт выдачи' ) . ' (демо)',
				'address' => 'г. ' . $city . ', ' . $sp[0],
				'lat'     => $base[0] + $sp[1],
				'lng'     => $base[1] + $sp[2],
			);
		}
		return $out;
	}
}
