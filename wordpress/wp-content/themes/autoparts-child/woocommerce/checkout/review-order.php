<?php
/**
 * «Обзор заказа» — оставляем только способы оплаты и кнопку оформления
 * (список товаров и итог показаны в сайдбаре/корзине).
 *
 * @package MajorService77
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_ajax() ) {
	do_action( 'woocommerce_review_order_before_payment' );
}

woocommerce_checkout_payment();

if ( ! is_ajax() ) {
	do_action( 'woocommerce_review_order_after_payment' );
}
