<?php
/**
 * Страница товара — кастомный шаблон.
 *
 * @package MajorService77
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	$product = wc_get_product( get_the_ID() );
	if ( ! $product ) {
		continue;
	}
	$id = $product->get_id();

	// Изображения: главное + галерея.
	$img_ids = array_values( array_filter( array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) ) );

	// Атрибуты.
	$oem    = get_post_meta( $id, '_af_oem', true );
	$brand  = get_post_meta( $id, '_af_brand', true );
	$partno = $oem ? $oem : $product->get_sku();

	// Марка/модель из фитмента.
	$make = '';
	$model = '';
	$make_url = '';
	$model_url = '';
	if ( class_exists( 'AF_Fitment' ) ) {
		$vids = AF_Fitment::vehicle_ids_for_product( $id );
		if ( $vids ) {
			$mk = wp_get_object_terms( $vids[0], 'car_make' );
			if ( $mk && ! is_wp_error( $mk ) ) {
				$make     = $mk[0]->name;
				$make_url = get_term_link( $mk[0] );
			}
			$md = wp_get_object_terms( $vids[0], 'car_model' );
			if ( $md && ! is_wp_error( $md ) ) {
				$model     = trim( $make . ' ' . $md[0]->name );
				$model_url = get_term_link( $md[0] );
			}
		}
	}
	if ( '' === $make ) {
		$make = $brand;
	}
	?>

	<div class="container catalog product-page">

		<div class="product-topbar">
			<a class="back-btn" href="<?php echo esc_url( $make_url ? $make_url : home_url( '/' ) ); ?>" onclick="if(document.referrer){history.back();return false;}">
				<?php echo mjr_icon( 'arrow-left', 18 ); ?><span>Назад</span>
			</a>
			<nav class="breadcrumbs breadcrumbs--inline" aria-label="Хлебные крошки">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a>
				<?php if ( $make ) : ?><span class="sep">/</span><a href="<?php echo esc_url( $make_url ); ?>"><?php echo esc_html( $make ); ?></a><?php endif; ?>
				<?php if ( $model ) : ?><span class="sep">/</span><a href="<?php echo esc_url( $model_url ); ?>"><?php echo esc_html( $model ); ?></a><?php endif; ?>
				<span class="sep">/</span><span class="is-current"><?php echo esc_html( $product->get_name() ); ?></span>
			</nav>
		</div>

		<div class="product-top">

			<div class="product-gallery">
				<?php if ( count( $img_ids ) > 1 ) : ?>
					<div class="product-thumbs-wrap" data-thumbs>
						<button type="button" class="thumbs-arrow thumbs-arrow--up" aria-label="Прокрутить вверх">
							<svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m4 10 4-4 4 4"/></svg>
						</button>
						<div class="product-thumbs">
							<?php foreach ( $img_ids as $i => $att ) : ?>
								<button type="button" class="product-thumb <?php echo 0 === $i ? 'is-active' : ''; ?>"
								        data-full="<?php echo esc_url( wp_get_attachment_image_url( $att, 'large' ) ); ?>">
									<?php echo wp_get_attachment_image( $att, 'woocommerce_thumbnail' ); ?>
								</button>
							<?php endforeach; ?>
						</div>
						<button type="button" class="thumbs-arrow thumbs-arrow--down" aria-label="Прокрутить вниз">
							<svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m4 6 4 4 4-4"/></svg>
						</button>
					</div>
				<?php endif; ?>
				<div class="product-main-img">
					<?php
					if ( $img_ids ) {
						echo '<img id="product-main-image" src="' . esc_url( wp_get_attachment_image_url( $img_ids[0], 'large' ) ) . '" alt="' . esc_attr( $product->get_name() ) . '">';
					} else {
						echo mjr_subcat_image( '' );
					}
					?>
				</div>
			</div>

			<div class="product-info">
				<span class="product-artikul">Артикул: <?php echo esc_html( $product->get_sku() ); ?></span>
				<h1 class="product-title"><?php echo esc_html( trim( $product->get_name() . ( $model ? ' ' . $model : '' ) ) ); ?></h1>
				<dl class="product-specs">
					<?php if ( $make ) : ?><div class="product-specs__row"><dt>Марка:</dt><dd><?php echo esc_html( $make ); ?></dd></div><?php endif; ?>
					<?php if ( $model ) : ?><div class="product-specs__row"><dt>Модель:</dt><dd><?php echo esc_html( $model ); ?></dd></div><?php endif; ?>
					<?php if ( $partno ) : ?><div class="product-specs__row"><dt>Партномер:</dt><dd><?php echo esc_html( $partno ); ?></dd></div><?php endif; ?>
					<div class="product-specs__row"><dt>Страна-изготовитель:</dt><dd>Китай</dd></div>
				</dl>
			</div>

			<aside class="product-buy">
				<span class="product-buy__label">Стоимость:</span>
				<div class="product-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
				<span class="product-delivery">Доставка от 5 дней</span>
				<?php if ( mjr_in_cart( $id ) ) : ?>
					<a class="btn-buy is-added" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
						<?php echo mjr_icon( 'check', 18 ); ?><span>В корзине</span>
					</a>
				<?php else : ?>
					<a class="btn-buy add_to_cart_button ajax_add_to_cart" href="<?php echo esc_url( $product->add_to_cart_url() ); ?>" data-quantity="1" data-product_id="<?php echo esc_attr( $id ); ?>" rel="nofollow">
						<?php echo mjr_icon( 'bag', 18 ); ?><span>В корзину</span>
					</a>
				<?php endif; ?>
				<a class="btn-analog" href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'product', 's' => $partno ), home_url( '/' ) ) ); ?>">Показать аналоги</a>
			</aside>

		</div>

		<div class="product-tabs" data-tabs>
			<nav class="tabs-nav" role="tablist">
				<button type="button" class="tab-btn is-active" data-tab="desc">Описание</button>
				<button type="button" class="tab-btn" data-tab="delivery">Доставка</button>
				<button type="button" class="tab-btn" data-tab="payment">Оплата</button>
				<button type="button" class="tab-btn" data-tab="warranty">Гарантия</button>
				<button type="button" class="tab-btn" data-tab="analogs">Аналоги</button>
			</nav>
			<div class="tabs-body">
				<div class="tab-pane" data-pane="desc">
					<h2 class="tab-title">Описание</h2>
					<div class="tab-text">
						<?php
						$desc = $product->get_description();
						echo $desc ? wp_kses_post( wpautop( $desc ) ) : '<p>Описание товара уточняется.</p>';
						?>
					</div>
				</div>
				<div class="tab-pane" data-pane="delivery" hidden>
					<h2 class="tab-title">Доставка</h2>
					<div class="tab-text">
						<?php $mjr_tab = mjr_content( 'tab_delivery', '' ); if ( '' !== $mjr_tab ) { echo wp_kses_post( $mjr_tab ); } else { ?>
						<p>У нас есть несколько способов доставки товара в города РФ и СНГ:</p>
						<p>Отправляем транспортными компаниями. Доставка до терминала отправки ТК Деловые Линии, ТК GTD, ТК Энергия, ТК СДЭК бесплатно!!! до других транспортных компаний — 300 рублей. Для отправки транспортными компаниями необходимо заранее оплатить счёт, который Вам вышлют после подтверждения заказа.</p>
						<p>Отправка ПОЧТОЙ РОССИИ. Для отправки необходимо заранее оплатить счёт, который Вам вышлют после подтверждения заказа.</p>
						<p>Самовывоз со склада</p>
						<p>г. Москва, 2-я Вольская ул., 34 стр. 285. ПН–ПТ с 9.00 — 19.00, СБ с 10.00 — 16.00, ВС — выходной.</p>
						<?php } ?>
					</div>
				</div>
				<div class="tab-pane" data-pane="payment" hidden>
					<h2 class="tab-title">Оплата</h2>
					<div class="tab-text">
						<?php $mjr_tab = mjr_content( 'tab_payment', '' ); if ( '' !== $mjr_tab ) { echo wp_kses_post( $mjr_tab ); } else { ?>
						<p>Оплата банковской картой онлайн, по счёту для юридических лиц, наличными при самовывозе или наложенным платежом.</p>
						<p>Для юрлиц предоставляем полный пакет документов.</p>
						<?php } ?>
					</div>
				</div>
				<div class="tab-pane" data-pane="warranty" hidden>
					<h2 class="tab-title">Гарантия</h2>
					<div class="tab-text">
						<?php $mjr_tab = mjr_content( 'tab_warranty', '' ); if ( '' !== $mjr_tab ) { echo wp_kses_post( $mjr_tab ); } else { ?>
						<p>Возврат товара возможен в соответствии с законами «О защите прав потребителей» и «Правилами продажи товаров дистанционным способом». Товар должен быть возвращён в исходном состоянии, с неповреждённой упаковкой и без следов использования. Необходимо наличие чека или другого документа, подтверждающего покупку.</p>
						<p>Внимание: Возврат автомобильных стёкол, изготовленных по заказу, осуществляется только при согласовании с заводом, с удержанием 25% от суммы заказа. Транспортные расходы оплачиваются отдельно.</p>
						<p>При возврате товаров ненадлежащего качества проводится проверка состояния, которая может занять до 20 дней. Если товар не соответствует заявленным характеристикам, его можно заменить или вернуть деньги в течение 30 дней.</p>
						<p>Покупатель может отказаться от товара надлежащего качества, заказанного дистанционно, в течение 7 дней после получения. Возврат средств за отмену заказа производится с вычетом комиссии 10–20%. Гарантийные обязательства не распространяются на товары с повреждениями или следами эксплуатации.</p>
						<?php } ?>
					</div>
				</div>
				<div class="tab-pane" data-pane="analogs" hidden>
					<h2 class="tab-title">Аналоги</h2>
					<?php
					$analogs = function_exists( 'mjr_get_analogs' ) ? mjr_get_analogs( $product, 10 ) : array();
					if ( $analogs ) :
						?>
						<ul class="products analogs-grid">
							<?php
							foreach ( $analogs as $ap ) {
								$GLOBALS['post'] = get_post( $ap->get_id() );
								setup_postdata( $GLOBALS['post'] );
								$GLOBALS['product'] = $ap;
								wc_get_template_part( 'content', 'product' );
							}
							wp_reset_postdata();
							$GLOBALS['post']    = get_post( $id );
							$GLOBALS['product'] = $product;
							?>
						</ul>
					<?php else : ?>
						<div class="tab-text"><p>Аналоги не найдены.</p></div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<?php echo mjr_help_banner(); ?>

	</div>

	<?php
endwhile;

get_footer();
