<?php
/**
 * Сайдбар личного кабинета.
 *
 * @package MajorService77
 */

defined( 'ABSPATH' ) || exit;

$mjr_icons = array(
	'dashboard'     => 'user',
	'active-orders' => 'clipboard',
	'history'       => 'bag',
);
?>
<nav class="account-nav" aria-label="Меню кабинета">
	<ul class="account-nav__list">
		<?php foreach ( wc_get_account_menu_items() as $endpoint => $label ) :
			if ( 'customer-logout' === $endpoint ) {
				continue;
			}
			?>
			<li class="account-nav__item <?php echo esc_attr( wc_get_account_menu_item_classes( $endpoint ) ); ?>">
				<a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>">
					<?php echo mjr_icon( $mjr_icons[ $endpoint ] ?? 'user', 18 ); ?>
					<span><?php echo esc_html( $label ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
	<a class="account-logout" href="<?php echo esc_url( wc_logout_url() ); ?>">
		<?php echo mjr_icon( 'logout', 18 ); ?><span>Выход</span>
	</a>
</nav>
