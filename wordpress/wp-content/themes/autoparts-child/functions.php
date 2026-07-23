<?php
/**
 * Тема MAJOR Service77 — авто-магазин запчастей.
 *
 * @package MajorService77
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'MJR_VER' ) ) {
	define( 'MJR_VER', '0.7.2' );
}

/**
 * Поддержка возможностей темы.
 */
add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );

	// WooCommerce.
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	register_nav_menus( array(
		'primary' => 'Главное меню',
		'catalog' => 'Меню каталога',
	) );
} );

/**
 * Стили и шрифты.
 */
add_action( 'wp_enqueue_scripts', function () {
	// Montserrat (заголовки) + Manrope (интерфейс/текст) с кириллицей.
	wp_enqueue_style(
		'mjr-fonts',
		'https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Montserrat:wght@600;700;800;900&display=swap',
		array(),
		null
	);
	wp_enqueue_style( 'mjr-theme', get_theme_file_uri( 'assets/css/theme.css' ), array( 'mjr-fonts' ), MJR_VER );
	wp_enqueue_style( 'mjr-style', get_stylesheet_uri(), array( 'mjr-theme' ), MJR_VER );

	wp_enqueue_script( 'mjr-theme', get_theme_file_uri( 'assets/js/theme.js' ), array(), MJR_VER, true );
}, 20 );

/**
 * Добавляем preconnect к Google Fonts.
 */
add_filter( 'wp_resource_hints', function ( $hints, $relation ) {
	if ( 'preconnect' === $relation ) {
		$hints[] = 'https://fonts.gstatic.com';
	}
	return $hints;
}, 10, 2 );

/* ---------------- WooCommerce: обёртки контента ---------------- */

remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

add_action( 'woocommerce_before_main_content', function () {
	echo '<div class="container woo-container"><div class="woo-content">';
}, 10 );
add_action( 'woocommerce_after_main_content', function () {
	echo '</div></div>';
}, 10 );

// 5 карточек в ряд, 20 товаров на страницу.
add_filter( 'loop_shop_columns', function () { return 5; } );
add_filter( 'loop_shop_per_page', function () { return 20; } );

// Живой счётчик корзины в шапке (обновляется после AJAX «в корзину»).
add_filter( 'woocommerce_add_to_cart_fragments', function ( $fragments ) {
	$count = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
	$html  = '<span class="cart-count-frag">';
	if ( $count ) {
		$html .= '<span class="cart-count">' . (int) $count . '</span>';
	}
	$html .= '</span>';
	$fragments['.cart-count-frag'] = $html;
	return $fragments;
} );

/* ---------------- Оформление заказа ---------------- */

add_filter( 'woocommerce_order_button_text', function () { return 'Перейти к оплате'; } );

/**
 * Фото товаров baz-on — отдаём напрямую с их CDN (метаполе _af_source_url),
 * а не из локальных uploads. Так фото работают на хостинге/Railway без заливки
 * гигабайтов картинок. Затрагивает только импортированные вложения.
 */
add_filter( 'wp_get_attachment_image_attributes', function ( $attr, $attachment ) {
	$src = $attachment ? get_post_meta( $attachment->ID, '_af_source_url', true ) : '';
	if ( $src ) {
		$attr['src'] = $src;
		unset( $attr['srcset'], $attr['sizes'] );
	}
	return $attr;
}, 20, 2 );

add_filter( 'wp_get_attachment_image_src', function ( $image, $attachment_id ) {
	$src = get_post_meta( $attachment_id, '_af_source_url', true );
	if ( $src && is_array( $image ) ) {
		$image[0] = $src;
	}
	return $image;
}, 20, 2 );

add_filter( 'wp_get_attachment_url', function ( $url, $attachment_id ) {
	$src = get_post_meta( $attachment_id, '_af_source_url', true );
	return $src ? $src : $url;
}, 20, 2 );

// Гость не оформляет заказ напрямую: со страницы оформления возвращаем в корзину,
// где кнопка «Оформить заказ» открывает регистрацию (после неё — снова корзина).
add_action( 'template_redirect', function () {
	if ( ! function_exists( 'is_checkout' ) || is_user_logged_in() ) {
		return;
	}
	if ( is_checkout() && ! is_wc_endpoint_url() && function_exists( 'WC' ) && WC()->cart && ! WC()->cart->is_empty() ) {
		wp_safe_redirect( wc_get_cart_url() );
		exit;
	}
} );

// Купоны не используются — убирает тумблер «У вас есть купон?» на checkout и поле в корзине.
add_filter( 'woocommerce_coupons_enabled', '__return_false' );

/* ---------------- Личный кабинет ---------------- */

// Кастомные эндпоинты ЛК.
add_action( 'init', function () {
	add_rewrite_endpoint( 'active-orders', EP_ROOT | EP_PAGES );
	add_rewrite_endpoint( 'history', EP_ROOT | EP_PAGES );
} );

// Триггер синхронизации baz-on для «крона» контейнера. Доступен ТОЛЬКО с localhost
// (контейнер сам себя дёргает), снаружи не сработает.
add_action( 'init', function () {
	if ( empty( $_GET['mjr_cron'] ) ) {
		return;
	}
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
	if ( ! in_array( $ip, array( '127.0.0.1', '::1' ), true ) ) {
		return;
	}
	if ( defined( 'AF_CRON_HOOK' ) ) {
		do_action( AF_CRON_HOOK ); // AF_Cron::run() -> baz-on run_sync()
	}
	exit( 'sync ok' );
}, 5 );
add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'active-orders';
	$vars[] = 'history';
	return $vars;
} );

// Пункты меню ЛК как на макете.
add_filter( 'woocommerce_account_menu_items', function () {
	return array(
		'dashboard'       => 'Профиль',
		'active-orders'   => 'Заказы в работе',
		'history'         => 'История покупок',
		'customer-logout' => 'Выход',
	);
}, 100 );

add_action( 'woocommerce_account_active-orders_endpoint', 'mjr_account_active_orders' );
add_action( 'woocommerce_account_history_endpoint', 'mjr_account_history' );

// Отчество в форме редактирования аккаунта.
add_action( 'woocommerce_edit_account_form', function () {
	$mid = get_user_meta( get_current_user_id(), '_af_middle_name', true );
	?>
	<p class="woocommerce-form-row form-row form-row-wide">
		<label for="mjr_middle_name">Отчество</label>
		<input type="text" class="input-text" name="mjr_middle_name" id="mjr_middle_name" value="<?php echo esc_attr( $mid ); ?>">
	</p>
	<?php
} );
add_action( 'woocommerce_save_account_details', function ( $user_id ) {
	if ( isset( $_POST['mjr_middle_name'] ) ) {
		update_user_meta( $user_id, '_af_middle_name', sanitize_text_field( wp_unslash( $_POST['mjr_middle_name'] ) ) );
	}
} );

// Маскировка email: mo*****@mail.com
function mjr_mask_email( $email ) {
	$p = explode( '@', $email );
	if ( count( $p ) !== 2 ) {
		return $email;
	}
	$vis = mb_substr( $p[0], 0, 2 );
	return $vis . str_repeat( '*', max( 3, mb_strlen( $p[0] ) - 2 ) ) . '@' . $p[1];
}

// Удаление своего профиля (самообслуживание, с подтверждением и nonce).
add_action( 'template_redirect', function () {
	if ( ! is_user_logged_in() || empty( $_GET['mjr_delete_account'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'mjr_delete_account' ) ) {
		return;
	}
	$uid = get_current_user_id();
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_logout();
	wp_delete_user( $uid );
	wp_safe_redirect( home_url( '/' ) );
	exit;
} );

// Заказы «в работе» — не завершённые, с трекером статусов.
function mjr_account_active_orders() {
	$orders = wc_get_orders( array(
		'customer' => get_current_user_id(),
		'status'   => array( 'wc-pending', 'wc-on-hold', 'wc-processing' ),
		'limit'    => 10,
		'orderby'  => 'date',
		'order'    => 'DESC',
	) );
	if ( ! $orders ) {
		echo '<div class="account-card"><p class="account-empty">Активных заказов нет.</p></div>';
		return;
	}
	foreach ( $orders as $order ) {
		mjr_render_order_tracker( $order );
	}
}

function mjr_render_order_tracker( $order ) {
	$status   = $order->get_status();
	$carrier  = $order->get_meta( '_mjr_carrier' );
	$tracking = $order->get_meta( '_mjr_tracking' );
	$paynote  = $order->get_meta( '_mjr_pay_note' );
	$date     = $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd.m.Y H:i' ) : '';
	$packed   = in_array( $status, array( 'processing', 'completed' ), true ) || $tracking;
	$shipped  = (bool) $tracking || 'completed' === $status;
	$received = 'completed' === $status;
	$cls = function ( $done ) { return $done ? 'is-done' : ''; };
	?>
	<div class="account-card order-track">
		<div class="order-track__head">
			<h3 class="account-card__title"><?php echo mjr_icon( 'clipboard', 20 ); ?> Заказ №<?php echo esc_html( $order->get_order_number() ); ?></h3>
			<span class="order-track__date"><?php echo esc_html( $date ); ?></span>
		</div>
		<ul class="track-steps">
			<li class="is-done"><span class="track-check"><?php echo mjr_icon( 'check', 13 ); ?></span><span class="track-text">Заказ оформлен</span></li>
			<li class="<?php echo $cls( $packed ); ?>">
				<span class="track-check"><?php echo mjr_icon( 'check', 13 ); ?></span>
				<span class="track-text">Заказ собран</span>
				<?php if ( $packed ) : ?>
					<div class="track-thumbs">
						<?php foreach ( $order->get_items() as $it ) : $p = $it->get_product(); if ( $p && $p->get_image_id() ) : ?>
							<span class="track-thumb"><?php echo $p->get_image( 'woocommerce_thumbnail' ); ?></span>
						<?php endif; endforeach; ?>
					</div>
				<?php endif; ?>
			</li>
			<li class="<?php echo $cls( $shipped ); ?>">
				<span class="track-check"><?php echo mjr_icon( 'check', 13 ); ?></span>
				<span class="track-text">Заказ отправлен</span>
				<?php if ( $shipped && ( $carrier || $tracking ) ) : ?>
					<div class="track-note"><?php echo esc_html( trim( $carrier . ( $paynote ? ', ' . $paynote : '' ) ) ); if ( $tracking ) : ?>. Номер отслеживания: №<?php echo esc_html( $tracking ); endif; ?></div>
				<?php endif; ?>
			</li>
			<li class="<?php echo $cls( $received ); ?>"><span class="track-check"><?php echo mjr_icon( 'check', 13 ); ?></span><span class="track-text">Заказ получен</span></li>
			<?php if ( $received ) : ?>
				<li class="is-done">
					<span class="track-check"><?php echo mjr_icon( 'check', 13 ); ?></span>
					<span class="track-text">Напишите отзыв</span>
					<div class="track-reviews">
						<a class="track-review" href="#" target="_blank" rel="noopener">Авито</a>
						<a class="track-review" href="#" target="_blank" rel="noopener">Яндекс карты</a>
						<a class="track-review track-review--green" href="#" target="_blank" rel="noopener">2ГИС</a>
					</div>
				</li>
			<?php endif; ?>
		</ul>
	</div>
	<?php
}

// История покупок — все заказы списком.
function mjr_account_history() {
	$orders = wc_get_orders( array(
		'customer' => get_current_user_id(),
		'limit'    => 20,
		'orderby'  => 'date',
		'order'    => 'DESC',
	) );
	echo '<div class="account-card">';
	echo '<h3 class="account-card__title">' . mjr_icon( 'bag', 20 ) . ' История покупок</h3>';
	if ( ! $orders ) {
		echo '<p class="account-empty">Покупок пока нет.</p></div>';
		return;
	}
	foreach ( $orders as $order ) {
		$date = $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd.m.Y H:i' ) : '';
		?>
		<div class="history-order">
			<div class="history-order__head">
				<span class="history-order__num"><?php echo mjr_icon( 'clipboard', 18 ); ?> Заказ №<?php echo esc_html( $order->get_order_number() ); ?></span>
				<span class="history-order__date"><?php echo esc_html( $date ); ?></span>
			</div>
			<div class="history-order__items">
				<?php foreach ( $order->get_items() as $it ) :
					$p     = $it->get_product();
					$brand = $p ? get_post_meta( $p->get_id(), '_af_brand', true ) : '';
					$sku   = $p ? $p->get_sku() : '';
					?>
					<div class="history-item">
						<span class="history-item__name"><?php echo esc_html( $it->get_name() ); echo $brand ? ' / ' . esc_html( $brand ) : ''; echo $sku ? ' / ' . esc_html( $sku ) : ''; ?></span>
						<span class="history-item__qty"><?php echo (int) $it->get_quantity(); ?> шт.</span>
						<span class="history-item__sum"><?php echo wp_kses_post( wc_price( $it->get_total() ) ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="history-order__total">Итого <?php echo wp_kses_post( wc_price( $order->get_total() ) ); ?></div>
		</div>
		<?php
	}
	echo '</div>';
}

// Поля checkout: только нужные, с плейсхолдерами, сгруппированы (контакты + адрес).
add_filter( 'woocommerce_checkout_fields', function ( $fields ) {
	$billing = array(
		'billing_last_name'   => array( 'placeholder' => 'Фамилия',  'required' => true,  'priority' => 10, 'class' => array( 'mjr-col-3' ) ),
		'billing_first_name'  => array( 'placeholder' => 'Имя',      'required' => true,  'priority' => 20, 'class' => array( 'mjr-col-3' ) ),
		'billing_middle_name' => array( 'placeholder' => 'Отчество', 'required' => false, 'priority' => 30, 'class' => array( 'mjr-col-3' ) ),
		'billing_phone'       => array( 'placeholder' => '+7 (___) ___-__-__', 'required' => true, 'type' => 'tel', 'priority' => 40, 'class' => array( 'mjr-col-2' ) ),
		'billing_email'       => array( 'placeholder' => 'Email',    'required' => true, 'type' => 'email', 'priority' => 50, 'class' => array( 'mjr-col-2' ) ),
		'billing_postcode'    => array( 'placeholder' => 'Индекс',   'required' => false, 'priority' => 60, 'class' => array( 'mjr-col-2' ) ),
		'billing_city'        => array( 'placeholder' => 'Город',    'required' => false, 'priority' => 70, 'class' => array( 'mjr-col-2' ) ),
		'billing_address_1'   => array( 'placeholder' => 'Адрес',    'required' => true,  'priority' => 80, 'class' => array( 'mjr-col-1' ) ),
	);
	foreach ( $billing as &$f ) {
		$f['label'] = '';
	}
	$fields['billing'] = $billing;
	unset( $fields['order']['order_comments'] );
	return $fields;
}, 20 );

// Отчество: подставить из профиля и сохранить в заказ.
add_filter( 'woocommerce_checkout_get_value', function ( $value, $input ) {
	if ( 'billing_middle_name' === $input && is_user_logged_in() ) {
		$m = get_user_meta( get_current_user_id(), '_af_middle_name', true );
		if ( $m ) { return $m; }
	}
	return $value;
}, 10, 2 );
add_action( 'woocommerce_checkout_update_order_meta', function ( $order_id ) {
	if ( ! empty( $_POST['billing_middle_name'] ) ) {
		update_post_meta( $order_id, '_billing_middle_name', sanitize_text_field( wp_unslash( $_POST['billing_middle_name'] ) ) );
	}
} );

/**
 * Корень слова для мягкого поиска по подкатегории («Фильтры» -> «фильт»).
 */
function mjr_search_root( $s ) {
	$s = trim( (string) $s );
	if ( '' === $s ) {
		return '';
	}
	$first = preg_split( '/[\s,\/]+/u', $s )[0];
	$first = mb_strtolower( $first, 'UTF-8' );
	$len   = mb_strlen( $first, 'UTF-8' );
	return $len <= 3 ? $first : mb_substr( $first, 0, $len - 1, 'UTF-8' );
}

/**
 * Эффективно выбранные бренды: явный выбор пользователя, иначе (при свежем переходе
 * из модели) — по умолчанию марка выбранного авто. Флаг af_filtered позволяет снимать галочку.
 */
function mjr_effective_brands() {
	if ( isset( $_GET['af_brand'] ) && is_array( $_GET['af_brand'] ) ) {
		return array_map( 'sanitize_text_field', wp_unslash( $_GET['af_brand'] ) );
	}
	if ( ! empty( $_GET['af_model'] ) && empty( $_GET['af_filtered'] ) ) {
		$model = get_term_by( 'slug', sanitize_title( wp_unslash( $_GET['af_model'] ) ), 'car_model' );
		if ( $model && ! is_wp_error( $model ) ) {
			$mvids = get_posts( array( 'post_type' => 'vehicle', 'posts_per_page' => 1, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => 'car_model', 'field' => 'term_id', 'terms' => $model->term_id ) ) ) );
			$mk    = $mvids ? wp_get_object_terms( $mvids, 'car_make' ) : array();
			if ( $mk && ! is_wp_error( $mk ) && in_array( $mk[0]->name, mjr_product_brands(), true ) ) {
				return array( $mk[0]->name );
			}
		}
	}
	return array();
}

/**
 * Есть ли товар уже в корзине.
 */
function mjr_in_cart( $product_id ) {
	if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
		return false;
	}
	foreach ( WC()->cart->get_cart() as $item ) {
		if ( (int) $item['product_id'] === (int) $product_id || (int) $item['variation_id'] === (int) $product_id ) {
			return true;
		}
	}
	return false;
}

/**
 * Аналоги товара: товары той же категории (product_cat), иначе — по корню названия.
 */
function mjr_get_analogs( $product, $limit = 10 ) {
	if ( ! $product ) {
		return array();
	}
	$self = $product->get_id();
	$ids  = array();

	$cat_ids = wc_get_product_term_ids( $self, 'product_cat' );
	if ( $cat_ids ) {
		$ids = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'post__not_in'   => array( $self ),
			'fields'         => 'ids',
			'tax_query'      => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $cat_ids ) ),
		) );
	}

	if ( ! $ids ) {
		$root = mjr_search_root( $product->get_name() );
		if ( '' !== $root ) {
			global $wpdb;
			$ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' AND ID != %d AND post_title LIKE %s LIMIT %d",
				$self, '%' . $wpdb->esc_like( $root ) . '%', $limit
			) );
		}
	}

	$out = array();
	foreach ( (array) $ids as $id ) {
		$p = wc_get_product( $id );
		if ( $p && $p->is_visible() ) {
			$out[] = $p;
		}
	}
	return $out;
}

/**
 * Фильтр каталога: по совместимости с авто (фитмент) и по бренду.
 */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() ) {
		return;
	}
	$is_list = ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) )
		|| ( $q->is_search() && 'product' === $q->get( 'post_type' ) );
	if ( ! $is_list ) {
		return;
	}

	// Скоуп по модели: товары, совместимые с выбранным авто (через фитмент).
	if ( ! empty( $_GET['af_model'] ) ) {
		$model = get_term_by( 'slug', sanitize_title( wp_unslash( $_GET['af_model'] ) ), 'car_model' );
		if ( $model && ! is_wp_error( $model ) ) {
			global $wpdb;
			$vids = get_posts( array( 'post_type' => 'vehicle', 'posts_per_page' => -1, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => 'car_model', 'field' => 'term_id', 'terms' => $model->term_id ) ) ) );
			$pids = array();
			if ( $vids ) {
				$table = $wpdb->prefix . 'part_fitment';
				$pids  = array_map( 'intval', $wpdb->get_col( 'SELECT DISTINCT product_id FROM ' . $table . ' WHERE vehicle_id IN (' . implode( ',', array_map( 'intval', $vids ) ) . ')' ) );
			}

			// Мягкое сужение по подкатегории: если корень слова находит товары — сужаем, иначе показываем все по модели.
			if ( $pids && ! empty( $_GET['s'] ) ) {
				$root = mjr_search_root( sanitize_text_field( wp_unslash( $_GET['s'] ) ) );
				if ( '' !== $root ) {
					$narrow = $wpdb->get_col( $wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish' AND ID IN (" . implode( ',', $pids ) . ") AND post_title LIKE %s",
						'%' . $wpdb->esc_like( $root ) . '%'
					) );
					// Строго по подкатегории: показываем только совпадения (без отката к «всем»).
					$pids = array_map( 'intval', $narrow );
				}
			}

			$q->set( 'post__in', $pids ? $pids : array( 0 ) );
			$q->set( 's', '' ); // фильтруем по фитменту, а не по тексту
		}
	}

	// Фильтр по бренду детали (с авто-подстановкой марки при переходе из модели).
	$brands = mjr_effective_brands();
	if ( $brands ) {
		$mq   = (array) $q->get( 'meta_query' );
		$mq[] = array( 'key' => '_af_brand', 'value' => $brands, 'compare' => 'IN' );
		$q->set( 'meta_query', $mq );
	}
} );

/**
 * Поиск товаров не только по названию, но и по артикулу (_sku),
 * партномеру (_af_oem) и кросс-номерам (_af_cross). Работает и для режима
 * «По артикулу», и для «По VIN» (если введён номер детали).
 */
add_filter( 'posts_search', function ( $search, $q ) {
	if ( is_admin() || ! $q->is_main_query() || ! $q->is_search() ) {
		return $search;
	}
	if ( 'product' !== $q->get( 'post_type' ) ) {
		return $search;
	}
	$term = trim( (string) $q->get( 's' ) );
	if ( '' === $term ) {
		return $search;
	}
	global $wpdb;
	$like = '%' . $wpdb->esc_like( $term ) . '%';
	// Артикул и партномер — точное совпадение (иначе короткий номер ловит лишние товары как подстроку).
	$ids  = $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT post_id FROM {$wpdb->postmeta}
		 WHERE meta_key IN ('_sku','_af_oem') AND meta_value = %s",
		$term
	) );
	$clause = $wpdb->prepare( "{$wpdb->posts}.post_title LIKE %s", $like );
	if ( $ids ) {
		$clause .= ' OR ' . $wpdb->posts . '.ID IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')';
	}
	return " AND ( {$clause} ) ";
}, 10, 2 );

/**
 * Список брендов, встречающихся у товаров (для фильтра).
 */
function mjr_product_brands() {
	global $wpdb;
	$rows = $wpdb->get_col(
		"SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
		 WHERE meta_key = '_af_brand' AND meta_value <> '' ORDER BY meta_value ASC LIMIT 100"
	);
	return $rows ? $rows : array();
}

/**
 * Оригинал или аналог: оригинал, если бренд детали совпадает с маркой авто.
 */
function mjr_is_original( $product ) {
	$brand = get_post_meta( $product->get_id(), '_af_brand', true );
	if ( '' === $brand ) {
		return true;
	}
	static $makes = null;
	if ( null === $makes ) {
		$makes = get_terms( array( 'taxonomy' => 'car_make', 'fields' => 'names', 'hide_empty' => false ) );
		$makes = is_wp_error( $makes ) ? array() : array_map( 'mb_strtolower', $makes );
	}
	return in_array( mb_strtolower( $brand ), $makes, true );
}

/**
 * Картинка товара с фолбэком на ч/б плейсхолдер детали.
 */
function mjr_product_image( $product ) {
	if ( $product->get_image_id() ) {
		return $product->get_image( 'woocommerce_thumbnail' );
	}
	return mjr_subcat_image( '' ); // силуэт детали
}

/**
 * Варианты сортировки каталога (WooCommerce orderby).
 */
function mjr_catalog_sort_options() {
	return array(
		'price'      => 'Сначала дешевле',
		'price-desc' => 'Сначала дороже',
		'date'       => 'Сначала новые',
		'title'      => 'По названию',
	);
}

/**
 * Рендер страницы каталога (шапка + сайдбар с фильтрами + сетка товаров).
 * Используется в archive-product.php и search.php.
 */
function mjr_render_catalog( $title ) {
	$brands  = mjr_product_brands();
	$sel     = mjr_effective_brands();
	$orderby = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : '';
	$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

	// Хлебные крошки: при переходе из подкатегории восстанавливаем путь марка/модель/категория.
	$af_model_slug = isset( $_GET['af_model'] ) ? sanitize_title( wp_unslash( $_GET['af_model'] ) ) : '';
	$af_cat        = isset( $_GET['af_cat'] ) ? sanitize_key( $_GET['af_cat'] ) : '';
	$crumbs        = array( array( 'label' => 'Главная', 'url' => home_url( '/' ) ) );
	$model_term    = $af_model_slug ? get_term_by( 'slug', $af_model_slug, 'car_model' ) : false;

	if ( $model_term && ! is_wp_error( $model_term ) ) {
		$mvids = get_posts( array( 'post_type' => 'vehicle', 'posts_per_page' => 1, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => 'car_model', 'field' => 'term_id', 'terms' => $model_term->term_id ) ) ) );
		$mk    = $mvids ? wp_get_object_terms( $mvids, 'car_make' ) : array();
		$make  = ( $mk && ! is_wp_error( $mk ) ) ? $mk[0] : null;
		if ( $make ) {
			$crumbs[] = array( 'label' => $make->name, 'url' => get_term_link( $make ) );
		}
		$crumbs[] = array( 'label' => trim( ( $make ? $make->name . ' ' : '' ) . $model_term->name ), 'url' => get_term_link( $model_term ) );
		$cats = mjr_part_categories();
		if ( $af_cat && isset( $cats[ $af_cat ] ) ) {
			$crumbs[] = array( 'label' => $cats[ $af_cat ]['name'], 'url' => add_query_arg( 'cat', $af_cat, get_term_link( $model_term ) ) );
		}
		$crumbs[] = array( 'label' => $title, 'url' => '' );
	} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
		$crumbs[] = array( 'label' => 'Каталог', 'url' => '' );
	} else {
		$crumbs[] = array( 'label' => 'Каталог', 'url' => wc_get_page_permalink( 'shop' ) );
		$crumbs[] = array( 'label' => $title, 'url' => '' );
	}
	?>
	<div class="container catalog shop">
		<nav class="breadcrumbs" aria-label="Хлебные крошки">
			<?php
			$last = count( $crumbs ) - 1;
			foreach ( $crumbs as $ci => $c ) {
				if ( $ci > 0 ) { echo '<span class="sep">/</span>'; }
				if ( ! empty( $c['url'] ) && $ci !== $last ) {
					echo '<a href="' . esc_url( $c['url'] ) . '">' . esc_html( $c['label'] ) . '</a>';
				} else {
					echo '<span class="is-current">' . esc_html( $c['label'] ) . '</span>';
				}
			}
			?>
		</nav>

		<div class="catalog-head">
			<a class="back-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>" onclick="if(document.referrer){history.back();return false;}">
				<?php echo mjr_icon( 'arrow-left', 18 ); ?><span>Назад</span>
			</a>
			<h1 class="shop-title"><?php echo esc_html( mb_strtoupper( $title, 'UTF-8' ) ); ?></h1>
		</div>

		<div class="shop-layout">
			<aside class="shop-sidebar">
				<div class="shop-sort">
					<select class="shop-sort__select" onchange="location=this.value">
						<?php foreach ( mjr_catalog_sort_options() as $val => $label ) :
							$url = esc_url( add_query_arg( 'orderby', $val ) ); ?>
							<option value="<?php echo $url; ?>" <?php selected( $orderby, $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<?php if ( $brands ) : ?>
					<form class="shop-filter" method="get">
						<input type="hidden" name="af_filtered" value="1">
						<?php if ( '' !== $search ) : ?><input type="hidden" name="s" value="<?php echo esc_attr( $search ); ?>"><input type="hidden" name="post_type" value="product"><?php endif; ?>
						<?php if ( '' !== $orderby ) : ?><input type="hidden" name="orderby" value="<?php echo esc_attr( $orderby ); ?>"><?php endif; ?>
						<?php if ( '' !== $af_model_slug ) : ?><input type="hidden" name="af_model" value="<?php echo esc_attr( $af_model_slug ); ?>"><?php endif; ?>
						<?php if ( '' !== $af_cat ) : ?><input type="hidden" name="af_cat" value="<?php echo esc_attr( $af_cat ); ?>"><?php endif; ?>
						<h4 class="shop-filter__title">Бренд</h4>
						<div class="shop-filter__list">
							<?php foreach ( $brands as $b ) : ?>
								<label class="shop-filter__row">
									<input type="checkbox" name="af_brand[]" value="<?php echo esc_attr( $b ); ?>" <?php checked( in_array( $b, $sel, true ) ); ?> onchange="this.form.submit()">
									<span><?php echo esc_html( $b ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</form>
				<?php endif; ?>
			</aside>

			<div class="shop-main">
				<?php if ( have_posts() ) : ?>
					<ul class="products">
						<?php while ( have_posts() ) : the_post(); wc_get_template_part( 'content', 'product' ); endwhile; ?>
					</ul>
					<?php the_posts_pagination( array( 'mid_size' => 2, 'prev_text' => '←', 'next_text' => '→' ) ); ?>
				<?php else : ?>
					<p class="catalog-empty">По вашему запросу товары не найдены.</p>
				<?php endif; ?>
			</div>
		</div>
	</div>
	<?php
}

/* ---------------- Хелперы ---------------- */

/**
 * Список марок для главной (порядок как в макете). slug используется для ссылки.
 */
function mjr_brands() {
	return array(
		array( 'Cherry', 'chery' ),
		array( 'Haval', 'haval' ),
		array( 'Exeed', 'exeed' ),
		array( 'Omoda', 'omoda' ),
		array( 'Kaiyi', 'kaiyi' ),
		array( 'Jetour', 'jetour' ),
		array( 'Tenet', 'tenet' ),
		array( 'Soueast', 'soueast' ),
		array( 'Great Wall', 'great-wall' ),
		array( 'Tank', 'tank' ),
		array( 'Belgee', 'belgee' ),
		array( 'Vortex', 'vortex' ),
		array( 'Lifan', 'lifan' ),
		array( 'Brilliance', 'brilliance' ),
		array( 'Zotye', 'zotye' ),
		array( 'Gac', 'gac' ),
		array( 'Livan', 'livan' ),
		array( 'Voyah', 'voyah' ),
		array( 'Lixiang', 'lixiang' ),
		array( 'Москвич', 'moskvich' ),
	);
}

/**
 * Ссылка каталога по марке: если есть терм car_make — на его архив, иначе поиск по товарам.
 */
function mjr_brand_url( $name, $slug ) {
	$term = get_term_by( 'slug', $slug, 'car_make' );
	if ( ! $term ) {
		$term = get_term_by( 'name', $name, 'car_make' );
	}
	if ( $term && ! is_wp_error( $term ) ) {
		return get_term_link( $term );
	}
	return home_url( '/?s=' . rawurlencode( $name ) . '&post_type=product' );
}

/**
 * Найти файл ассета по базовому пути без расширения (svg|png|webp|jpg). Вернёт URL или ''.
 */
function mjr_find_asset( $rel_no_ext ) {
	foreach ( array( 'svg', 'png', 'webp', 'jpg', 'jpeg' ) as $ext ) {
		if ( file_exists( get_theme_file_path( "{$rel_no_ext}.{$ext}" ) ) ) {
			return get_theme_file_uri( "{$rel_no_ext}.{$ext}" );
		}
	}
	return '';
}

/**
 * Логотип. Положите файл assets/img/logo.(svg|png) — подхватится картинкой,
 * иначе выводится текстовый логотип. Для футера — assets/img/logo-footer.*
 */
function mjr_logo( $muted = false ) {
	$url = $muted ? mjr_find_asset( 'assets/img/logo-footer' ) : '';
	if ( ! $url ) {
		$url = mjr_find_asset( 'assets/img/logo' );
	}
	if ( $url ) {
		return '<img class="brand__img" src="' . esc_url( $url ) . '" alt="MAJOR Service77">';
	}
	return '<span class="brand__name">MA<span class="brand__j">J</span>OR</span><span class="brand__sub">SERVICE<b>77</b></span>';
}

/**
 * Эмблема марки для чипа: файл assets/brands/{slug}.(svg|png|webp) или ч/б плейсхолдер.
 */
function mjr_brand_emblem( $slug = '' ) {
	$url = $slug ? mjr_find_asset( "assets/brands/{$slug}" ) : '';
	if ( $url ) {
		return '<img class="chip-logo chip-logo--img" src="' . esc_url( $url ) . '" alt="" width="32" height="32" loading="lazy">';
	}
	return '<span class="chip-logo" aria-hidden="true">'
		. '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5">'
		. '<circle cx="12" cy="12" r="9"/><path d="M6 14c2.5-4.5 9.5-4.5 12 0" stroke-linecap="round"/><circle cx="12" cy="9.5" r="1.4" fill="currentColor" stroke="none"/>'
		. '</svg></span>';
}

/**
 * Инлайновые иконки (Lucide-style).
 */
function mjr_icon( $name, $size = 20 ) {
	// Файл-переопределение: assets/icons/{name}.svg (инлайнится ради currentColor).
	$file = get_theme_file_path( "assets/icons/{$name}.svg" );
	if ( file_exists( $file ) ) {
		return '<span class="ic ic-' . esc_attr( $name ) . '" style="width:' . (int) $size . 'px;height:' . (int) $size . 'px">' . file_get_contents( $file ) . '</span>';
	}
	$paths = array(
		'search'   => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
		'mail'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		'telegram' => '<path d="M22 3 2 10.5l6 2.2M22 3l-3 17-8.5-6.3M22 3 8 12.7M8 12.7V19l3.5-4.7"/>',
		'phone'    => '<path d="M6.62 10.79a15.5 15.5 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24 11.4 11.4 0 0 0 3.57.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1.02l-2.2 2.2Z" fill="currentColor" stroke="none"/>',
		'bag'        => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
		'user'       => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		'arrow-left' => '<path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>',
		'check'      => '<path d="M20 6 9 17l-5-5"/>',
		'clipboard'  => '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>',
		'logout'     => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
		'x'          => '<path d="M18 6 6 18M6 6l12 12"/>',
	);
	$d = $paths[ $name ] ?? '';
	return '<svg class="ic ic-' . esc_attr( $name ) . '" viewBox="0 0 24 24" width="' . (int) $size . '" height="' . (int) $size . '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
}

/**
 * Изображение модели авто: файл assets/models/{slug}.(jpg|png|webp|svg),
 * иначе нейтральный ч/б силуэт машины.
 */
function mjr_model_image( $slug = '', $term_id = 0 ) {
	// Приоритет — загруженное через CMS фото модели (метаполе термина).
	if ( $term_id ) {
		$att = (int) get_term_meta( $term_id, '_mjr_model_photo', true );
		if ( $att ) {
			$html = wp_get_attachment_image( $att, 'large', false, array( 'alt' => '', 'loading' => 'lazy' ) );
			if ( $html ) {
				return $html;
			}
		}
	}
	$url = $slug ? mjr_find_asset( "assets/models/{$slug}" ) : '';
	if ( $url ) {
		return '<img src="' . esc_url( $url ) . '" alt="" loading="lazy">';
	}
	return '<svg class="car-ph" viewBox="0 0 160 84" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
		. '<path d="M14 60c-1-9 2-14 12-15l16-16c4-4 9-6 15-6h30c9 0 14 3 20 9l12 12c11 1 17 6 17 16" stroke="#C6CACF" stroke-width="3" stroke-linecap="round" fill="#EEF0F2"/>'
		. '<path d="M12 60h136" stroke="#C6CACF" stroke-width="3" stroke-linecap="round"/>'
		. '<circle cx="50" cy="61" r="11" fill="#fff" stroke="#C6CACF" stroke-width="3"/>'
		. '<circle cx="116" cy="61" r="11" fill="#fff" stroke="#C6CACF" stroke-width="3"/>'
		. '</svg>';
}

/**
 * Стандартное дерево категорий запчастей (одинаковое для всех моделей).
 * key => [ name, icon, subs[] ]
 */
function mjr_part_categories() {
	return array(
		'engine' => array(
			'name' => 'Двигатель',
			'subs' => array( 'Фильтры', 'Зажигание', 'ГРМ', 'Охлаждение, отопление, вентиляция', 'Блок цилиндров', 'Головка блока цилиндров', 'Ремни, ролики', 'Питание, воздухозабор, турбокомпрессоры', 'Сальники, прокладки', 'Топливная система', 'Глушитель', 'Опоры, Кронштейны' ),
		),
		'body' => array(
			'name' => 'Кузов',
			'subs' => array( 'Бампера, Решётка радиатора, Усилители, Эмблемы', 'Брызговики, Подкрылки, Пыльники ДВС, Пороги', 'Двери, Кожух запасного колеса', 'Охлаждение, отопление, вентиляция', 'Капот, Крышка багажника, Петли', 'Крылья', 'Панели', 'Салон', 'Стёкла', 'Ручки, троса двери, замки', 'Опоры, Кронштейны' ),
		),
		'chassis' => array(
			'name' => 'Ходовая часть',
			'subs' => array( 'Рулевое управление', 'Тормоза задние', 'Тормоза передние', 'Подвеска передняя', 'Приводы колёс, ШРУСы, Карданы', 'Шины, Диски, Колпаки', 'Амортизаторы, Пружины, Опоры и подшипники амортизатора', 'Подвеска задняя' ),
		),
		'electric' => array(
			'name' => 'Электрика',
			'subs' => array( 'Датчики', 'Стеклоочистители', 'Фары', 'Генератор, Стартер', 'Зеркала', 'Замки, Ключи', 'Панели приборов', 'Переключатели', 'Фонари', 'Реле, предохранители, блоки', 'Стеклоподъёмники' ),
		),
		'transmission' => array(
			'name' => 'Трансмиссия',
			'subs' => array( 'Коробка переключения передач', 'Механизм переключения передач', 'Сцепление', 'Сальники КПП, Раздатки, Редукторов' ),
		),
		'ac' => array(
			'name' => 'Кондиционер',
			'subs' => array( 'Радиатор, вентилятор', 'Трубки', 'Компрессор кондиционера' ),
		),
	);
}

/**
 * Иконка категории: файл assets/cats/{key}.(svg|png) или инлайновый ч/б знак.
 */
function mjr_cat_icon( $key, $size = 46 ) {
	$url = mjr_find_asset( "assets/cats/{$key}" );
	if ( $url ) {
		return '<img class="cat-ic-img" src="' . esc_url( $url ) . '" alt="" width="' . (int) $size . '" height="' . (int) $size . '">';
	}
	$paths = array(
		'engine'       => '<rect x="4" y="9" width="12" height="9" rx="1.5"/><path d="M16 11h3l1 2v2h-4"/><path d="M7 9V6h5v3"/><path d="M9 6V4h3"/><circle cx="9.5" cy="13.5" r="1.6"/>',
		'body'         => '<path d="M3 15l2-6c.4-1.2 1.5-2 2.8-2h8.4c1.3 0 2.4.8 2.8 2l2 6"/><path d="M2 15h20v3H2z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/>',
		'chassis'      => '<circle cx="7" cy="16" r="4"/><path d="M14 4v3l-3 2 3 2-3 2 3 2v3"/><path d="M11 9h9M20 6v6"/>',
		'electric'     => '<path d="M13 2 4 14h6l-1 8 9-12h-6l1-8z"/>',
		'transmission' => '<circle cx="12" cy="12" r="8"/><path d="M12 4v16M12 12h8M12 12l5.7-5.7"/><circle cx="12" cy="12" r="2" fill="currentColor" stroke="none"/>',
		'ac'           => '<path d="M12 2v20M4.5 7l15 10M19.5 7l-15 10"/><path d="M12 2l2 2-2 2-2-2 2-2zM12 22l2-2-2-2-2 2 2 2z"/>',
	);
	$d = $paths[ $key ] ?? '<rect x="4" y="4" width="16" height="16" rx="3"/>';
	return '<svg class="cat-ic" viewBox="0 0 24 24" width="' . (int) $size . '" height="' . (int) $size . '" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg>';
}

/**
 * Картинка подкатегории: файл assets/subcats/{slug}.(png|jpg|webp|svg) или ч/б плейсхолдер детали.
 */
function mjr_subcat_image( $slug ) {
	$url = mjr_find_asset( "assets/subcats/{$slug}" );
	if ( $url ) {
		return '<img src="' . esc_url( $url ) . '" alt="" loading="lazy">';
	}
	return '<svg class="part-ph" viewBox="0 0 48 48" fill="none" stroke="#B9BDC4" stroke-width="2" aria-hidden="true">'
		. '<circle cx="24" cy="24" r="9"/><circle cx="24" cy="24" r="3.5"/>'
		. '<path d="M24 8v5M24 35v5M8 24h5M35 24h5M13 13l3.5 3.5M31.5 31.5 35 35M35 13l-3.5 3.5M16.5 31.5 13 35"/></svg>';
}

/**
 * Значение контентного поля из CMS-плагина mjr-cms.
 * Плагин выключен или поле пустое → возвращается $default (текст по умолчанию из темы).
 *
 * @param string $key
 * @param mixed  $default
 * @return mixed
 */
function mjr_content( $key, $default = '' ) {
	if ( function_exists( 'mjr_cms' ) ) {
		$v = mjr_cms( $key, null );
		if ( null !== $v && '' !== $v ) {
			return $v;
		}
	}
	return $default;
}

/**
 * Баннер «Не нашли нужную запчасть?» — переиспользуется на страницах каталога.
 * Тексты, картинка и ссылка редактируются в CMS (Управление сайтом → Баннер).
 */
function mjr_help_banner() {
	$title    = mjr_content( 'banner_title', 'НЕ НАШЛИ НУЖНУЮ ЗАПЧАСТЬ?' );
	$btn_text = mjr_content( 'banner_btn_text', 'Поможем подобрать' );
	$btn_link = mjr_content( 'banner_btn_link', 'tel:+78432392013' );
	$img_id   = (int) mjr_content( 'banner_image', 0 );
	$q_img    = $img_id ? wp_get_attachment_image_url( $img_id, 'medium' ) : mjr_find_asset( 'assets/img/help-question' );
	ob_start(); ?>
	<div class="help-banner">
		<span class="help-banner__title"><?php echo esc_html( $title ); ?></span>
		<?php if ( $q_img ) : ?>
			<img class="help-banner__img" src="<?php echo esc_url( $q_img ); ?>" alt="" aria-hidden="true">
		<?php else : ?>
			<span class="help-banner__q" aria-hidden="true">?</span>
		<?php endif; ?>
		<a class="help-banner__btn" href="<?php echo esc_url( $btn_link ); ?>">
			<?php echo mjr_icon( 'search', 18 ); ?><span><?php echo esc_html( $btn_text ); ?></span>
		</a>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Форма поиска с переключателем «По артикулу / По VIN».
 *
 * @param string $variant header|hero
 */
function mjr_search_form( $variant = 'hero' ) {
	$placeholder = 'hero' === $variant ? 'Поиск детали' : 'Поиск по сайту';
	ob_start(); ?>
	<form class="mjr-search mjr-search--<?php echo esc_attr( $variant ); ?>" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
		<input type="hidden" name="post_type" value="product">
		<input type="hidden" name="search_mode" value="vin" class="js-mode">
		<input type="search" class="mjr-search__input" name="s" placeholder="<?php echo esc_attr( $placeholder ); ?>"
		       value="<?php echo esc_attr( get_search_query() ); ?>" aria-label="<?php echo esc_attr( $placeholder ); ?>">
		<div class="seg" role="group" aria-label="Режим поиска">
			<button type="button" class="seg__btn js-seg" data-mode="article">По артикулу</button>
			<button type="button" class="seg__btn is-active js-seg" data-mode="vin">По VIN</button>
		</div>
		<button type="submit" class="mjr-search__submit" aria-label="Найти">
			<?php echo mjr_icon( 'search', 20 ); ?>
			<?php if ( 'hero' === $variant ) : ?><span>Найти</span><?php endif; ?>
		</button>
	</form>
	<?php
	return ob_get_clean();
}
