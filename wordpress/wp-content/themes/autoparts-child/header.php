<?php
/**
 * Шапка сайта.
 *
 * @package MajorService77
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
	<div class="container site-header__inner">

		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="MAJOR Service77 — главная"><?php echo mjr_logo(); ?></a>

		<?php echo mjr_search_form( 'header' ); ?>

		<div class="header-tools">
			<a class="header-phone" href="tel:+78432392013">8 (843) 239-20-13</a>

			<div class="socials">
				<a class="socials__btn" href="mailto:major@major.ru" aria-label="Написать на почту"><?php echo mjr_icon( 'mail', 18 ); ?></a>
				<a class="socials__btn" href="https://t.me/" target="_blank" rel="noopener" aria-label="Telegram"><?php echo mjr_icon( 'telegram', 18 ); ?></a>
				<a class="socials__btn" href="tel:+78432392013" aria-label="Позвонить"><?php echo mjr_icon( 'phone', 18 ); ?></a>
			</div>

			<?php if ( is_user_logged_in() ) : ?>
				<a class="btn btn--login" href="<?php echo esc_url( function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'dashboard' ) : home_url( '/my-account/' ) ); ?>">Кабинет</a>
			<?php else : ?>
				<button type="button" class="btn btn--login af-open-auth" data-tab="login">Вход</button>
			<?php endif; ?>

			<?php $mjr_cart_count = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0; ?>
			<a class="btn btn--cart<?php echo $mjr_cart_count ? '' : ' is-empty'; ?>" href="<?php echo esc_url( function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ) ); ?>" aria-label="Корзина">
				<?php echo mjr_icon( 'bag', 20 ); ?>
				<span class="cart-count-frag"><?php
					if ( $mjr_cart_count ) {
						echo '<span class="cart-count">' . (int) $mjr_cart_count . '</span>';
					}
				?></span>
			</a>
		</div>

	</div>
</header>

<main id="main" class="site-main">
