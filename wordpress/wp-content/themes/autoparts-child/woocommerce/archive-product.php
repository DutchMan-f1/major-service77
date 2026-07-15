<?php
/**
 * Архив товаров (магазин, категории) — кастомный каталог.
 *
 * @package MajorService77
 */

defined( 'ABSPATH' ) || exit;

get_header();

$s     = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$title = ( '' !== $s ) ? $s : ( is_shop() ? 'Каталог' : woocommerce_page_title( false ) );

mjr_render_catalog( $title );

get_footer();
