<?php
/**
 * Корзина — кастомный шаблон.
 *
 * @package MajorService77
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' );
?>

<h1 class="cart-title">КОРЗИНА</h1>

<?php if ( WC()->cart->is_empty() ) : ?>

	<p class="cart-empty">Ваша корзина пуста.</p>
	<a class="btn-checkout" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">Перейти в каталог</a>

<?php else : ?>

	<form class="cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
		<div class="cart-table-wrap">
			<table class="cart-table">
				<thead>
					<tr>
						<th class="col-name">Наименование</th>
						<th>Артикул</th>
						<th>Бренд</th>
						<th>Количество</th>
						<th>Стоимость еденицы</th>
						<th>Стоимость</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( WC()->cart->get_cart() as $key => $item ) :
						$product = $item['data'];
						if ( ! $product || ! $product->exists() || $item['quantity'] <= 0 ) {
							continue;
						}
						$pid       = $product->get_id();
						$brand     = get_post_meta( $pid, '_af_brand', true );
						$permalink = $product->is_visible() ? $product->get_permalink( $item ) : '';
						?>
						<tr class="cart-row">
							<td class="col-name" data-title="Наименование">
								<span class="cart-thumb"><?php echo $product->get_image( 'woocommerce_thumbnail' ); ?></span>
								<a class="cart-pname" href="<?php echo esc_url( $permalink ); ?>">
									<?php
									echo esc_html( $product->get_name() );
									echo $brand ? ' / ' . esc_html( $brand ) : '';
									echo $product->get_sku() ? ' / ' . esc_html( $product->get_sku() ) : '';
									?>
								</a>
							</td>
							<td data-title="Артикул"><?php echo esc_html( $product->get_sku() ); ?></td>
							<td data-title="Бренд"><?php echo esc_html( $brand ); ?></td>
							<td data-title="Количество">
								<div class="qty-stepper">
									<button type="button" class="qty-btn qty-minus" aria-label="Уменьшить">−</button>
									<input type="number" class="qty-input" name="cart[<?php echo esc_attr( $key ); ?>][qty]"
									       value="<?php echo esc_attr( $item['quantity'] ); ?>" min="1" inputmode="numeric" aria-label="Количество">
									<button type="button" class="qty-btn qty-plus" aria-label="Увеличить">+</button>
								</div>
							</td>
							<td class="col-unit" data-title="Стоимость еденицы"><?php echo wp_kses_post( wc_price( wc_get_price_to_display( $product ) ) ); ?></td>
							<td class="col-line" data-title="Стоимость"><?php echo wp_kses_post( WC()->cart->get_product_subtotal( $product, $item['quantity'] ) ); ?></td>
							<td class="col-remove">
								<a class="cart-remove" href="<?php echo esc_url( wc_get_cart_remove_url( $key ) ); ?>" aria-label="Удалить">×</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php wp_nonce_field( 'woocommerce-cart' ); ?>
		<button type="submit" class="cart-update-hidden" name="update_cart" value="1" tabindex="-1" aria-hidden="true">Обновить корзину</button>

		<div class="cart-footer">
			<?php if ( is_user_logged_in() ) : ?>
				<a class="btn-checkout" href="<?php echo esc_url( wc_get_checkout_url() ); ?>">
					<span>Оформить заказ</span><?php echo mjr_icon( 'bag', 18 ); ?>
				</a>
			<?php else : ?>
				<button type="button" class="btn-checkout af-open-auth" data-tab="register"
				        data-redirect="<?php echo esc_url( wc_get_cart_url() ); ?>">
					<span>Оформить заказ</span><?php echo mjr_icon( 'bag', 18 ); ?>
				</button>
			<?php endif; ?>
			<div class="cart-delivery">Стоимость доставки<br>рассчитывается отдельно</div>
			<div class="cart-total">
				<span class="cart-total__label">Итого:</span>
				<span class="cart-total__val"><?php echo wp_kses_post( wc_price( WC()->cart->get_cart_contents_total() ) ); ?></span>
			</div>
		</div>
	</form>

<?php endif; ?>

<?php do_action( 'woocommerce_after_cart' ); ?>
