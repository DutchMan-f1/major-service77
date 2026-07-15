<?php
/**
 * Карточка товара в каталоге (196×320).
 *
 * @package MajorService77
 */

defined( 'ABSPATH' ) || exit;

global $product;
if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
	$product = wc_get_product( get_the_ID() );
}
if ( ! $product || ! $product->is_visible() ) {
	return;
}

$id   = $product->get_id();
$orig = mjr_is_original( $product );
$sku  = $product->get_sku();
$link = get_permalink( $id );
?>
<li <?php wc_product_class( 'prod-card', $product ); ?>>
	<span class="prod-badge <?php echo $orig ? 'is-orig' : 'is-analog'; ?>"><?php echo $orig ? 'Оригинал' : 'Аналог'; ?></span>

	<a class="prod-card__link" href="<?php echo esc_url( $link ); ?>">
		<span class="prod-card__img"><?php echo mjr_product_image( $product ); ?></span>
		<?php if ( $sku ) : ?><span class="prod-card__sku"><?php echo esc_html( $sku ); ?></span><?php endif; ?>
		<span class="prod-card__name"><?php echo esc_html( $product->get_name() ); ?></span>
		<span class="prod-card__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
	</a>

	<div class="prod-card__actions">
		<?php if ( function_exists( 'mjr_in_cart' ) && mjr_in_cart( $id ) ) : ?>
			<a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="prod-cart is-added" aria-label="В корзине">
				<?php echo mjr_icon( 'check', 18 ); ?>
			</a>
		<?php else : ?>
			<a href="<?php echo esc_url( $product->add_to_cart_url() ); ?>"
			   class="prod-cart <?php echo $product->supports( 'ajax_add_to_cart' ) && $product->is_purchasable() && $product->is_in_stock() ? 'add_to_cart_button ajax_add_to_cart' : ''; ?>"
			   data-quantity="1" data-product_id="<?php echo esc_attr( $id ); ?>"
			   data-product_sku="<?php echo esc_attr( $sku ); ?>" rel="nofollow" aria-label="В корзину">
				<?php echo mjr_icon( 'bag', 18 ); ?>
			</a>
		<?php endif; ?>
		<a class="prod-analog" href="<?php echo esc_url( $link ); ?>">Аналоги</a>
	</div>
</li>
