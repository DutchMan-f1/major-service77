<?php
/**
 * Оформление заказа — кастомный шаблон.
 *
 * @package MajorService77
 */

defined( 'ABSPATH' ) || exit;

$checkout = WC()->checkout();

do_action( 'woocommerce_before_checkout_form', $checkout );

$fields  = $checkout->get_checkout_fields( 'billing' );
$contact = array( 'billing_last_name', 'billing_first_name', 'billing_middle_name', 'billing_phone', 'billing_email' );
$address = array( 'billing_postcode', 'billing_city', 'billing_address_1' );

$render = function ( $key ) use ( $fields, $checkout ) {
	if ( isset( $fields[ $key ] ) ) {
		woocommerce_form_field( $key, $fields[ $key ], $checkout->get_value( $key ) );
	}
};
?>

<h1 class="checkout-title">ОФОРМЛЕНИЕ ЗАКАЗА</h1>

<form name="checkout" method="post" class="checkout woocommerce-checkout"
      action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data" aria-label="Оформление заказа">

	<div class="checkout-layout">
		<div class="checkout-left">
			<div id="customer_details">
				<input type="hidden" name="billing_country" value="RU">

				<section class="checkout-block">
					<h3 class="checkout-section-title">Ваши контактные данные</h3>
					<div class="checkout-grid">
						<?php foreach ( $contact as $k ) { $render( $k ); } ?>
					</div>
				</section>

				<?php do_action( 'mjr_delivery_section' ); ?>

				<section class="checkout-block">
					<h3 class="checkout-section-title">Ваш адрес для доставки</h3>
					<?php if ( class_exists( 'MJR_Delivery' ) ) { MJR_Delivery::render_mode_toggle(); } ?>
					<div class="checkout-grid dlv-address-fields" hidden>
						<?php foreach ( $address as $k ) { $render( $k ); } ?>
					</div>
				</section>
			</div>

			<section class="checkout-block checkout-pay">
				<h3 class="checkout-section-title">Выберите способ оплаты</h3>
				<div id="order_review" class="woocommerce-checkout-review-order">
					<?php woocommerce_order_review(); ?>
				</div>
			</section>
		</div>

		<aside class="checkout-summary">
			<span class="checkout-summary__label">Сумма к оплате:</span>
			<div class="checkout-summary__total"><?php echo wp_kses_post( wc_price( WC()->cart->get_total( 'edit' ) ) ); ?></div>
			<a class="btn-back-cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
				<?php echo mjr_icon( 'bag', 18 ); ?><span>Вернуться в корзину</span>
			</a>
		</aside>
	</div>

</form>

<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
