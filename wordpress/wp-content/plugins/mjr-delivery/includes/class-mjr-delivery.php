<?php
/**
 * Доставка: реестр перевозчиков, настройки, вёрстка блока на оформлении,
 * сохранение выбора в заказ, отображение в админке и письмах.
 *
 * @package MJR_Delivery
 */

defined( 'ABSPATH' ) || exit;

class MJR_Delivery {

	const OPTION = 'mjr_delivery_settings';

	/** Перевозчики в порядке отображения. */
	public static function carriers() {
		return array(
			'cdek'     => array(
				'label' => 'СДЭК',
				'desc'  => 'Пункты выдачи и постаматы, курьер до двери',
				'color' => '#159A4E',
				'short' => 'СДЭК',
			),
			'dellin'   => array(
				'label' => 'Деловые Линии',
				'desc'  => 'Терминалы и адресная доставка по всей России',
				'color' => '#0B5FB0',
				'short' => 'ДЛ',
			),
			'yandex'   => array(
				'label' => 'Яндекс Доставка',
				'desc'  => 'Пункты выдачи и курьер по городу, быстрая доставка',
				'color' => '#FC3F1D',
				'short' => 'Я',
			),
			'fivepost' => array(
				'label' => '5Post',
				'desc'  => 'Постаматы и пункты в «Пятёрочке» и «Перекрёстке»',
				'color' => '#E4181B',
				'short' => '5',
			),
			'pochta'   => array(
				'label' => 'Почта России',
				'desc'  => 'Отделения и курьерская доставка по всей России',
				'color' => '#1E4FC2',
				'short' => 'ПР',
			),
		);
	}

	public static function settings() {
		$defaults = array(
			'ymaps_key'          => '',
			'ymaps_geocoder_key' => '', // ключ «API Геокодера» (координаты → город/адрес)
			'demo'             => 'yes',
			'cdek_enabled'     => 'yes',
			'cdek_account'     => '',
			'cdek_secret'      => '',
			'dellin_enabled'   => 'yes',
			'dellin_appkey'    => '',
			'dellin_login'     => '',
			'dellin_password'  => '',
			'dellin_terminal'  => '', // ID терминала-отправителя ДЛ (напр. 18 — Москва, Рябиновая 37)
			'dellin_item_w'    => '2',    // средний вес одной позиции, кг (baz-on вес не передаёт)
			'dellin_item_v'    => '0.01', // средний объём одной позиции, м³
			// Прочие перевозчики пока скрыты (можно включить в настройках).
			'yandex_enabled'   => 'no',
			'yandex_token'     => '',
			'fivepost_enabled' => 'no',
			'fivepost_apikey'  => '',
			'pochta_enabled'   => 'no',
			'pochta_token'     => '',
			'pochta_key'       => '',
		);
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	/**
	 * URL логотипа перевозчика, если файл лежит в assets/logos/{id}.(svg|png|webp|jpg).
	 * Иначе '' → покажем буквенный значок. Официальный логотип достаточно положить в папку.
	 */
	public static function carrier_logo_url( $id ) {
		$dir = plugin_dir_path( MJR_DELIVERY_FILE ) . 'assets/logos/';
		foreach ( array( 'svg', 'png', 'webp', 'jpg' ) as $ext ) {
			if ( file_exists( $dir . $id . '.' . $ext ) ) {
				return plugins_url( 'assets/logos/' . $id . '.' . $ext, MJR_DELIVERY_FILE );
			}
		}
		return '';
	}

	/** Включённые перевозчики. */
	public static function enabled_carriers() {
		$s   = self::settings();
		$out = array();
		foreach ( self::carriers() as $id => $c ) {
			if ( 'yes' === ( $s[ $id . '_enabled' ] ?? 'yes' ) ) {
				$out[ $id ] = $c;
			}
		}
		return $out;
	}

	/* =========================================================
	 *  Хуки
	 * ======================================================= */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );

		// Рендер блока доставки на оформлении (вызывается из шаблона темы).
		add_action( 'mjr_delivery_section', array( __CLASS__, 'render_section' ) );

		// Сохранение и валидация выбора.
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'save_to_order' ), 10, 2 );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate' ), 10, 2 );

		// Показ в админке заказа и письмах.
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( __CLASS__, 'admin_display' ) );
		add_action( 'woocommerce_email_after_order_table', array( __CLASS__, 'email_display' ), 10, 1 );
		add_filter( 'woocommerce_get_order_item_totals', array( __CLASS__, 'order_totals_row' ), 10, 2 );

		// Настройки в админке.
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'admin_save' ) );
	}

	public static function assets() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		$s = self::settings();
		wp_enqueue_script(
			'mjr-delivery',
			plugins_url( 'assets/delivery.js', MJR_DELIVERY_FILE ),
			array(),
			MJR_DELIVERY_VER,
			true
		);
		$carriers = array();
		foreach ( self::enabled_carriers() as $id => $c ) {
			$carriers[ $id ] = array( 'label' => $c['label'], 'color' => $c['color'] );
		}
		wp_localize_script( 'mjr-delivery', 'MJRDelivery', array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'mjr_delivery' ),
			'ymapsKey'  => $s['ymaps_key'],
			'carriers'  => $carriers,
		) );
	}

	/* =========================================================
	 *  Вёрстка блока на оформлении
	 * ======================================================= */
	public static function render_section() {
		$carriers = self::enabled_carriers();
		if ( ! $carriers ) {
			return;
		}
		$has_key = '' !== self::settings()['ymaps_key'];
		?>
		<section class="checkout-block mjr-delivery" aria-label="Способ доставки">
			<h3 class="checkout-section-title">Выберите способ доставки</h3>

			<div class="dlv-list">
				<?php $first = true; foreach ( $carriers as $id => $c ) : ?>
					<div class="dlv-card" data-carrier="<?php echo esc_attr( $id ); ?>">
						<label class="dlv-card__head">
							<input type="radio" name="mjr_delivery_carrier" value="<?php echo esc_attr( $id ); ?>" <?php checked( $first ); ?>>
							<span class="dlv-card__radio" aria-hidden="true"></span>
							<span class="dlv-card__body">
								<span class="dlv-card__title"><?php echo esc_html( $c['label'] ); ?></span>
								<span class="dlv-card__desc"><?php echo esc_html( $c['desc'] ); ?></span>
							</span>
							<?php $logo = self::carrier_logo_url( $id ); ?>
							<span class="dlv-card__logo<?php echo $logo ? ' dlv-card__logo--img' : ''; ?>" style="--dlv-c:<?php echo esc_attr( $c['color'] ); ?>">
								<?php if ( $logo ) : ?>
									<img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $c['label'] ); ?>">
								<?php else : echo esc_html( $c['short'] ); endif; ?>
							</span>
						</label>

						<div class="dlv-card__panel">
							<div class="dlv-panel-inner">
								<div class="dlv-search">
									<input type="text" class="dlv-search__input" placeholder="Введите город — например, Ростов-на-Дону" autocomplete="off">
									<button type="button" class="dlv-search__btn">Найти</button>
								</div>
								<div class="dlv-map<?php echo $has_key ? '' : ' is-nokey'; ?>" data-carrier="<?php echo esc_attr( $id ); ?>">
									<?php if ( ! $has_key ) : ?>
										<div class="dlv-map__ph">
											<span class="dlv-map__ph-ic" aria-hidden="true">🗺️</span>
											<span>Карта пунктов появится после подключения Яндекс.Карт.<br>Пункт можно выбрать в списке.</span>
										</div>
									<?php endif; ?>
								</div>
								<div class="dlv-points" data-carrier="<?php echo esc_attr( $id ); ?>" role="listbox" aria-label="Пункты выдачи">
									<div class="dlv-points__hint">Загрузка пунктов…</div>
								</div>
							</div>
						</div>
					</div>
					<?php $first = false; endforeach; ?>
			</div>

			<input type="hidden" name="mjr_delivery_mode" id="mjr_delivery_mode" value="pickup">
			<input type="hidden" name="mjr_delivery_point" id="mjr_delivery_point" value="">
			<input type="hidden" name="mjr_delivery_point_addr" id="mjr_delivery_point_addr" value="">
		</section>
		<?php
	}

	/** Тумблер «пункт выдачи / адрес» — рендерится из шаблона в блоке адреса. */
	public static function render_mode_toggle() {
		?>
		<div class="dlv-mode-toggle" role="tablist" aria-label="Куда доставить">
			<button type="button" class="dlv-mode is-active" data-mode="pickup" role="tab" aria-selected="true">До пункта выдачи</button>
			<button type="button" class="dlv-mode" data-mode="address" role="tab" aria-selected="false">До адреса</button>
		</div>
		<?php
	}

	/* =========================================================
	 *  Сохранение / валидация
	 * ======================================================= */
	public static function save_to_order( $order, $data ) {
		$carrier = isset( $_POST['mjr_delivery_carrier'] ) ? sanitize_text_field( wp_unslash( $_POST['mjr_delivery_carrier'] ) ) : '';
		$mode    = isset( $_POST['mjr_delivery_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mjr_delivery_mode'] ) ) : 'pickup';
		$point   = isset( $_POST['mjr_delivery_point'] ) ? sanitize_text_field( wp_unslash( $_POST['mjr_delivery_point'] ) ) : '';
		$addr    = isset( $_POST['mjr_delivery_point_addr'] ) ? sanitize_text_field( wp_unslash( $_POST['mjr_delivery_point_addr'] ) ) : '';

		$carriers = self::carriers();
		if ( $carrier && isset( $carriers[ $carrier ] ) ) {
			$order->update_meta_data( '_mjr_delivery_carrier', $carrier );
			$order->update_meta_data( '_mjr_delivery_carrier_label', $carriers[ $carrier ]['label'] );
			$order->update_meta_data( '_mjr_delivery_mode', 'address' === $mode ? 'address' : 'pickup' );
			if ( 'pickup' === $mode ) {
				$order->update_meta_data( '_mjr_delivery_point', $point );
				$order->update_meta_data( '_mjr_delivery_point_addr', $addr );
			}
		}
	}

	public static function validate( $data, $errors ) {
		$carrier = isset( $_POST['mjr_delivery_carrier'] ) ? sanitize_text_field( wp_unslash( $_POST['mjr_delivery_carrier'] ) ) : '';
		$mode    = isset( $_POST['mjr_delivery_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['mjr_delivery_mode'] ) ) : 'pickup';
		$point   = isset( $_POST['mjr_delivery_point'] ) ? sanitize_text_field( wp_unslash( $_POST['mjr_delivery_point'] ) ) : '';

		if ( ! $carrier ) {
			$errors->add( 'mjr_delivery', 'Выберите способ доставки.' );
			return;
		}
		if ( 'pickup' === $mode && '' === $point ) {
			$errors->add( 'mjr_delivery', 'Выберите пункт выдачи на карте или в списке (либо переключитесь на доставку до адреса).' );
		}
	}

	/* =========================================================
	 *  Отображение выбранной доставки
	 * ======================================================= */
	private static function summary_text( $order ) {
		$label = $order->get_meta( '_mjr_delivery_carrier_label' );
		if ( ! $label ) {
			return '';
		}
		$mode = $order->get_meta( '_mjr_delivery_mode' );
		if ( 'address' === $mode ) {
			return $label . ' — курьером на адрес';
		}
		$addr = $order->get_meta( '_mjr_delivery_point_addr' );
		return $label . ( $addr ? ' — пункт выдачи: ' . $addr : ' — пункт выдачи' );
	}

	public static function admin_display( $order ) {
		$txt = self::summary_text( $order );
		if ( $txt ) {
			echo '<p><strong>Доставка:</strong> ' . esc_html( $txt ) . '</p>';
		}
	}

	public static function email_display( $order ) {
		$txt = self::summary_text( $order );
		if ( $txt ) {
			echo '<p style="margin:0 0 16px"><strong>Доставка:</strong> ' . esc_html( $txt ) . '</p>';
		}
	}

	public static function order_totals_row( $rows, $order ) {
		$txt = self::summary_text( $order );
		if ( $txt ) {
			$rows['mjr_delivery'] = array(
				'label' => 'Доставка:',
				'value' => esc_html( $txt ),
			);
		}
		return $rows;
	}

	/* =========================================================
	 *  Страница настроек
	 * ======================================================= */
	public static function admin_menu() {
		add_submenu_page(
			'woocommerce',
			'Доставка (карты пунктов)',
			'Доставка (карты)',
			'manage_woocommerce',
			'mjr-delivery',
			array( __CLASS__, 'admin_page' )
		);
	}

	public static function admin_save() {
		if ( empty( $_POST['mjr_delivery_save'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) || ! check_admin_referer( 'mjr_delivery_save' ) ) {
			return;
		}
		$s   = self::settings();
		$new = array();
		foreach ( $s as $key => $default ) {
			if ( in_array( $key, array( 'demo', 'cdek_enabled', 'dellin_enabled', 'yandex_enabled', 'fivepost_enabled', 'pochta_enabled' ), true ) ) {
				$new[ $key ] = isset( $_POST[ 'f_' . $key ] ) ? 'yes' : 'no';
			} else {
				$new[ $key ] = isset( $_POST[ 'f_' . $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'f_' . $key ] ) ) : '';
			}
		}
		update_option( self::OPTION, $new );
		add_settings_error( 'mjr_delivery', 'saved', 'Настройки доставки сохранены.', 'updated' );
	}

	public static function admin_page() {
		$s = self::settings();
		$f = function ( $k ) use ( $s ) { return esc_attr( $s[ $k ] ?? '' ); };
		$c = function ( $k ) use ( $s ) { return checked( 'yes', $s[ $k ] ?? 'no', false ); };
		?>
		<div class="wrap">
			<h1>Доставка — карты пунктов выдачи</h1>
			<?php settings_errors( 'mjr_delivery' ); ?>
			<p>Подключите ключи, чтобы на оформлении показывались реальные карты и пункты выдачи. Без ключей блок работает в демо-режиме (примерные пункты).</p>
			<form method="post">
				<?php wp_nonce_field( 'mjr_delivery_save' ); ?>

				<h2 class="title">Яндекс.Карты</h2>
				<table class="form-table">
					<tr>
						<th><label for="f_ymaps_key">API-ключ JavaScript</label></th>
						<td>
							<input type="text" id="f_ymaps_key" name="f_ymaps_key" value="<?php echo $f( 'ymaps_key' ); ?>" class="regular-text" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
							<p class="description">Ключ «JavaScript API» — рисует карту. Без него карта не отрисуется (список пунктов будет работать).</p>
						</td>
					</tr>
					<tr>
						<th><label for="f_ymaps_geocoder_key">API-ключ Геокодера</label></th>
						<td>
							<input type="text" id="f_ymaps_geocoder_key" name="f_ymaps_geocoder_key" value="<?php echo $f( 'ymaps_geocoder_key' ); ?>" class="regular-text" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
							<p class="description">Ключ «API Геокодера» (отдельный от JavaScript API). Определяет точный город при перемещении карты. Без него город определяется по терминалам Деловых Линий.</p>
						</td>
					</tr>
					<tr>
						<th>Демо-режим</th>
						<td><label><input type="checkbox" name="f_demo" <?php echo $c( 'demo' ); ?>> Показывать примерные пункты, пока не подключены API перевозчиков</label></td>
					</tr>
				</table>

				<?php
				$rows = array(
					'cdek'     => array( 'СДЭК', array( 'cdek_account' => 'Client ID (account)', 'cdek_secret' => 'Client Secret' ) ),
					'dellin'   => array( 'Деловые Линии', array( 'dellin_appkey' => 'appkey (API-ключ, dev.dellin.ru)', 'dellin_login' => 'Логин ЛК — с +7, напр. +79255924002', 'dellin_password' => 'Пароль ЛК', 'dellin_terminal' => 'ID терминала-отправителя (напр. 18 — Москва)', 'dellin_item_w' => 'Средний вес позиции, кг (по умолч. 2)', 'dellin_item_v' => 'Средний объём позиции, м³ (по умолч. 0.01)' ) ),
					'yandex'   => array( 'Яндекс Доставка', array( 'yandex_token' => 'OAuth-токен' ) ),
					'fivepost' => array( '5Post', array( 'fivepost_apikey' => 'API-ключ (Bearer)' ) ),
					'pochta'   => array( 'Почта России', array( 'pochta_token' => 'Токен авторизации', 'pochta_key' => 'Ключ доступа (Basic)' ) ),
				);
				foreach ( $rows as $id => $row ) :
					?>
					<h2 class="title"><?php echo esc_html( $row[0] ); ?></h2>
					<table class="form-table">
						<tr>
							<th>Включён</th>
							<td><label><input type="checkbox" name="f_<?php echo esc_attr( $id ); ?>_enabled" <?php echo $c( $id . '_enabled' ); ?>> Показывать этот способ на оформлении</label></td>
						</tr>
						<?php foreach ( $row[1] as $key => $title ) : ?>
							<tr>
								<th><label for="f_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $title ); ?></label></th>
								<td><input type="text" id="f_<?php echo esc_attr( $key ); ?>" name="f_<?php echo esc_attr( $key ); ?>" value="<?php echo $f( $key ); ?>" class="regular-text"></td>
							</tr>
						<?php endforeach; ?>
					</table>
				<?php endforeach; ?>

				<p class="submit"><button type="submit" name="mjr_delivery_save" value="1" class="button button-primary">Сохранить</button></p>
			</form>
		</div>
		<?php
	}
}
