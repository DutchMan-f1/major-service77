<?php
/**
 * Личный кабинет — обёртка (заголовок + сайдбар + контент).
 *
 * @package MajorService77
 */

defined( 'ABSPATH' ) || exit;
?>
<h1 class="account-title">ЛИЧНЫЙ КАБИНЕТ</h1>

<div class="account-layout">
	<?php do_action( 'woocommerce_account_navigation' ); ?>
	<div class="account-content">
		<?php do_action( 'woocommerce_account_content' ); ?>
	</div>
</div>
