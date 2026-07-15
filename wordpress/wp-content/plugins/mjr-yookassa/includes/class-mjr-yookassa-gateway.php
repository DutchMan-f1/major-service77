<?php
/**
 * Платёжный шлюз ЮKassa для WooCommerce.
 *
 * Схема: клиент оформляет заказ → создаём платёж в ЮKassa → редиректим на
 * confirmation_url → после оплаты ЮKassa шлёт вебхук → сервер повторно
 * запрашивает статус платежа в API и переводит заказ в «оплачен».
 *
 * @package MJR_YooKassa
 */

defined( 'ABSPATH' ) || exit;

class WC_Gateway_MJR_YooKassa extends WC_Payment_Gateway {

	/** @var string */
	public $shop_id;

	/** @var string */
	public $secret_key;

	/** @var string yes|no */
	public $send_receipt;

	/** @var string код ставки НДС для чека 54-ФЗ */
	public $vat_code;

	/** @var string признак предмета расчёта */
	public $payment_subject;

	/** @var string признак способа расчёта */
	public $payment_mode;

	/** @var string yes|no */
	public $debug;

	/** @var WC_Logger_Interface|null */
	private static $log = null;

	public function __construct() {
		$this->id                 = 'mjr_yookassa';
		$this->method_title       = 'ЮKassa';
		$this->method_description = 'Приём онлайн-платежей через ЮKassa (банковские карты, СБП, кошелёк). Оплата на защищённой странице ЮKassa с возвратом на сайт.';
		$this->has_fields         = false;
		$this->icon               = apply_filters( 'mjr_yookassa_icon', '' );
		$this->supports           = array( 'products', 'refunds' );

		$this->init_form_fields();
		$this->init_settings();

		$this->enabled         = $this->get_option( 'enabled' );
		$this->title           = $this->get_option( 'title' );
		$this->description     = $this->get_option( 'description' );
		$this->shop_id         = $this->get_option( 'shop_id' );
		$this->secret_key      = $this->get_option( 'secret_key' );
		$this->send_receipt    = $this->get_option( 'send_receipt' );
		$this->vat_code        = $this->get_option( 'vat_code' );
		$this->payment_subject = $this->get_option( 'payment_subject' );
		$this->payment_mode    = $this->get_option( 'payment_mode' );
		$this->debug           = $this->get_option( 'debug' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// Вебхук ЮKassa: {site}/?wc-api=mjr_yookassa
		add_action( 'woocommerce_api_' . $this->id, array( $this, 'handle_webhook' ) );
	}

	/**
	 * URL для вставки в настройки уведомлений (HTTP-notifications) личного кабинета ЮKassa.
	 */
	public function webhook_url() {
		return WC()->api_request_url( $this->id );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'         => array(
				'title'   => 'Включить',
				'type'    => 'checkbox',
				'label'   => 'Включить оплату через ЮKassa',
				'default' => 'no',
			),
			'title'           => array(
				'title'       => 'Название',
				'type'        => 'text',
				'description' => 'Название способа оплаты, которое видит покупатель.',
				'default'     => 'Банковская карта, СБП (ЮKassa)',
				'desc_tip'    => true,
			),
			'description'     => array(
				'title'       => 'Описание',
				'type'        => 'textarea',
				'description' => 'Текст под названием способа оплаты на странице оформления.',
				'default'     => 'Оплата картой или через СБП на защищённой странице ЮKassa.',
			),
			'credentials'     => array(
				'title'       => 'Данные магазина',
				'type'        => 'title',
				'description' => 'shopId и секретный ключ из личного кабинета ЮKassa (Настройки → API). Для тестов используйте тестовый магазин и его ключ.',
			),
			'shop_id'         => array(
				'title'   => 'shopId',
				'type'    => 'text',
				'default' => '',
			),
			'secret_key'      => array(
				'title'       => 'Секретный ключ',
				'type'        => 'password',
				'default'     => '',
				'description' => 'Хранится в БД сайта. Никогда не публикуйте его.',
				'desc_tip'    => true,
			),
			'webhook'         => array(
				'title'       => 'URL для уведомлений',
				'type'        => 'title',
				'description' => 'Скопируйте этот адрес в ЛК ЮKassa → Интеграция → HTTP-уведомления, события: <code>payment.succeeded</code>, <code>payment.canceled</code>, <code>refund.succeeded</code>:<br><code>' . esc_html( $this->webhook_url() ) . '</code>',
			),
			'receipt'         => array(
				'title'       => 'Фискализация (54-ФЗ)',
				'type'        => 'title',
				'description' => 'Если ЮKassa формирует чеки за вас — включите передачу чека и укажите ставку НДС по умолчанию.',
			),
			'send_receipt'    => array(
				'title'   => 'Передавать чек',
				'type'    => 'checkbox',
				'label'   => 'Отправлять данные чека в ЮKassa (54-ФЗ)',
				'default' => 'yes',
			),
			'vat_code'        => array(
				'title'       => 'Ставка НДС по умолчанию',
				'type'        => 'select',
				'default'     => '1',
				'options'     => array(
					'1' => 'Без НДС',
					'2' => 'НДС 0%',
					'3' => 'НДС 10%',
					'4' => 'НДС 20%',
					'5' => 'НДС 10/110',
					'6' => 'НДС 20/120',
				),
				'description' => 'Применяется ко всем позициям чека.',
				'desc_tip'    => true,
			),
			'payment_subject' => array(
				'title'   => 'Признак предмета расчёта',
				'type'    => 'select',
				'default' => 'commodity',
				'options' => array(
					'commodity' => 'Товар',
					'service'   => 'Услуга',
					'payment'   => 'Платёж',
				),
			),
			'payment_mode'    => array(
				'title'   => 'Признак способа расчёта',
				'type'    => 'select',
				'default' => 'full_payment',
				'options' => array(
					'full_payment'      => 'Полный расчёт',
					'full_prepayment'   => 'Полная предоплата',
					'advance'           => 'Аванс',
				),
			),
			'advanced'        => array(
				'title' => 'Отладка',
				'type'  => 'title',
			),
			'debug'           => array(
				'title'       => 'Логирование',
				'type'        => 'checkbox',
				'label'       => 'Писать запросы/ответы в лог WooCommerce',
				'default'     => 'no',
				'description' => 'WooCommerce → Статус → Журналы, источник <code>mjr-yookassa</code>.',
			),
		);
	}

	/**
	 * Предупреждения в админке о неполной настройке.
	 */
	public function admin_options() {
		echo '<h2>ЮKassa</h2>';
		if ( 'RUB' !== get_woocommerce_currency() ) {
			echo '<div class="notice notice-warning inline"><p>Валюта магазина должна быть российский рубль (RUB). Текущая: <b>' . esc_html( get_woocommerce_currency() ) . '</b>.</p></div>';
		}
		if ( 'yes' === $this->enabled && ( '' === $this->shop_id || '' === $this->secret_key ) ) {
			echo '<div class="notice notice-error inline"><p>Укажите shopId и секретный ключ — без них оплата работать не будет.</p></div>';
		}
		echo '<table class="form-table">';
		$this->generate_settings_html();
		echo '</table>';
	}

	/**
	 * Создаём платёж и отдаём редирект на страницу оплаты ЮKassa.
	 *
	 * @param int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return array( 'result' => 'failure' );
		}

		$api = new MJR_YooKassa_API( $this->shop_id, $this->secret_key, $this->logger() );

		$payload = array(
			'amount'       => array(
				'value'    => $this->fmt( $order->get_total() ),
				'currency' => $order->get_currency(),
			),
			'capture'      => true,
			'confirmation' => array(
				'type'       => 'redirect',
				'return_url' => $this->get_return_url( $order ),
			),
			'description'  => $this->build_description( $order ),
			'metadata'     => array(
				'order_id'  => (string) $order_id,
				'order_key' => $order->get_order_key(),
			),
		);

		if ( 'yes' === $this->send_receipt ) {
			$receipt = $this->build_receipt( $order );
			if ( $receipt ) {
				$payload['receipt'] = $receipt;
			}
		}

		$result = $api->create_payment( $payload );

		if ( is_wp_error( $result ) ) {
			$note = 'Не удалось создать платёж ЮKassa: ' . $result->get_error_message();
			$order->add_order_note( $note );
			wc_add_notice( 'Оплата временно недоступна. Попробуйте ещё раз или выберите другой способ.', 'error' );
			return array( 'result' => 'failure' );
		}

		$order->update_meta_data( '_mjr_yk_payment_id', $result['id'] );
		$order->save();
		$order->update_status( 'pending', 'Ожидает оплаты через ЮKassa (платёж ' . $result['id'] . ').' );

		$confirmation_url = isset( $result['confirmation']['confirmation_url'] ) ? $result['confirmation']['confirmation_url'] : '';
		if ( ! $confirmation_url ) {
			wc_add_notice( 'ЮKassa не вернула ссылку на оплату.', 'error' );
			return array( 'result' => 'failure' );
		}

		if ( function_exists( 'WC' ) && WC()->cart ) {
			WC()->cart->empty_cart();
		}

		return array(
			'result'   => 'success',
			'redirect' => $confirmation_url,
		);
	}

	/**
	 * Обработчик HTTP-уведомлений ЮKassa. Доверяем только статусу, полученному
	 * повторным запросом к API (тело уведомления не является доказательством оплаты).
	 */
	public function handle_webhook() {
		$body = file_get_contents( 'php://input' );
		$data = json_decode( $body, true );

		$this->log( 'webhook: ' . $body );

		$payment_id = '';
		if ( is_array( $data ) && ! empty( $data['object']['id'] ) ) {
			$payment_id = sanitize_text_field( $data['object']['id'] );
		}

		if ( '' === $payment_id ) {
			status_header( 400 );
			exit( 'no payment id' );
		}

		$api     = new MJR_YooKassa_API( $this->shop_id, $this->secret_key, $this->logger() );
		$payment = $api->get_payment( $payment_id );

		if ( is_wp_error( $payment ) ) {
			// 500 — ЮKassa повторит уведомление позже.
			status_header( 500 );
			exit( 'verify failed' );
		}

		$order_id = isset( $payment['metadata']['order_id'] ) ? absint( $payment['metadata']['order_id'] ) : 0;
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order ) {
			// Заказ не найден — подтверждаем приём, чтобы ЮKassa не долбила повторами.
			status_header( 200 );
			exit( 'order not found' );
		}

		$status = isset( $payment['status'] ) ? $payment['status'] : '';

		switch ( $status ) {
			case 'succeeded':
				if ( ! $order->is_paid() ) {
					$order->payment_complete( $payment_id );
					$order->add_order_note( 'Оплачено через ЮKassa. Платёж: ' . $payment_id );
				}
				break;

			case 'canceled':
				if ( ! $order->has_status( array( 'cancelled', 'refunded' ) ) ) {
					$order->update_status( 'cancelled', 'Платёж ЮKassa отменён.' );
				}
				break;

			case 'waiting_for_capture':
				$order->update_status( 'on-hold', 'Платёж ЮKassa ожидает подтверждения (capture).' );
				break;
		}

		status_header( 200 );
		exit( 'OK' );
	}

	/**
	 * Возврат средств из админки заказа.
	 *
	 * @param int    $order_id
	 * @param float  $amount
	 * @param string $reason
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'yk_refund', 'Заказ не найден.' );
		}
		$payment_id = $order->get_meta( '_mjr_yk_payment_id' );
		if ( ! $payment_id ) {
			return new WP_Error( 'yk_refund', 'Не найден идентификатор платежа ЮKassa для заказа.' );
		}

		$api = new MJR_YooKassa_API( $this->shop_id, $this->secret_key, $this->logger() );

		$payload = array(
			'payment_id' => $payment_id,
			'amount'     => array(
				'value'    => $this->fmt( null === $amount ? $order->get_total() : $amount ),
				'currency' => $order->get_currency(),
			),
		);
		if ( $reason ) {
			$payload['description'] = mb_substr( $reason, 0, 128 );
		}

		$result = $api->create_refund( $payload );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$order->add_order_note( 'Возврат ЮKassa на сумму ' . $this->fmt( $amount ) . ' ' . $order->get_currency() . '. Возврат: ' . $result['id'] );
		return true;
	}

	/* ---------------- helpers ---------------- */

	private function build_description( $order ) {
		$desc = sprintf( 'Заказ №%s на сайте %s', $order->get_order_number(), wp_parse_url( home_url(), PHP_URL_HOST ) );
		return mb_substr( $desc, 0, 128 );
	}

	/**
	 * Формируем чек 54-ФЗ: позиции заказа + доставка.
	 *
	 * @param WC_Order $order
	 * @return array|null
	 */
	private function build_receipt( $order ) {
		$customer = array();
		$email    = $order->get_billing_email();
		$phone    = preg_replace( '/[^\d]/', '', (string) $order->get_billing_phone() );
		if ( $email ) {
			$customer['email'] = $email;
		}
		if ( $phone ) {
			$customer['phone'] = $phone;
		}
		if ( ! $customer ) {
			// Без контакта покупателя чек не примут — тогда лучше не слать receipt.
			return null;
		}

		$items = array();

		foreach ( $order->get_items() as $item ) {
			$qty   = max( 1, (int) $item->get_quantity() );
			$total = (float) $order->get_line_total( $item, true, true ); // с налогами
			$price = $qty > 0 ? $total / $qty : $total;

			$items[] = array(
				'description'     => mb_substr( wp_strip_all_tags( $item->get_name() ), 0, 128 ),
				'quantity'        => (string) $qty,
				'amount'          => array(
					'value'    => $this->fmt( $price ),
					'currency' => $order->get_currency(),
				),
				'vat_code'        => (int) $this->vat_code,
				'payment_subject' => $this->payment_subject,
				'payment_mode'    => $this->payment_mode,
			);
		}

		$shipping = (float) $order->get_shipping_total() + (float) $order->get_shipping_tax();
		if ( $shipping > 0 ) {
			$items[] = array(
				'description'     => 'Доставка',
				'quantity'        => '1',
				'amount'          => array(
					'value'    => $this->fmt( $shipping ),
					'currency' => $order->get_currency(),
				),
				'vat_code'        => (int) $this->vat_code,
				'payment_subject' => 'service',
				'payment_mode'    => $this->payment_mode,
			);
		}

		if ( ! $items ) {
			return null;
		}

		return array(
			'customer' => $customer,
			'items'    => $items,
		);
	}

	private function fmt( $amount ) {
		return number_format( (float) $amount, 2, '.', '' );
	}

	private function logger() {
		if ( 'yes' !== $this->debug ) {
			return null;
		}
		if ( null === self::$log && function_exists( 'wc_get_logger' ) ) {
			self::$log = wc_get_logger();
		}
		return self::$log;
	}

	private function log( $message ) {
		$l = $this->logger();
		if ( $l ) {
			$l->log( 'debug', $message, array( 'source' => 'mjr-yookassa' ) );
		}
	}
}
