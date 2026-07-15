<?php
/**
 * Шаблон страницы (в т.ч. корзина/оформление через шорткоды WooCommerce).
 *
 * @package MajorService77
 */

defined( 'ABSPATH' ) || exit;

get_header();

$is_wc = function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() );
?>
<div class="container <?php echo $is_wc ? 'wc-page' : 'page-body'; ?>">
	<?php
	while ( have_posts() ) :
		the_post();
		if ( ! $is_wc ) {
			echo '<h1 class="page-title">' . esc_html( get_the_title() ) . '</h1>';
		}
		the_content();
	endwhile;
	?>
</div>
<?php
get_footer();
