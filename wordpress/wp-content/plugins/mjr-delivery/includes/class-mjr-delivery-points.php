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
	}

	public static function ajax() {
		check_ajax_referer( 'mjr_delivery', 'nonce' );

		$carrier = isset( $_POST['carrier'] ) ? sanitize_text_field( wp_unslash( $_POST['carrier'] ) ) : '';
		$city    = isset( $_POST['city'] ) ? sanitize_text_field( wp_unslash( $_POST['city'] ) ) : '';
		$city    = $city ? $city : 'Казань';

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

		// Фолбэк: демо-точки, если реальных нет и демо включён.
		if ( empty( $points ) ) {
			$s = MJR_Delivery::settings();
			if ( 'yes' === $s['demo'] ) {
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
