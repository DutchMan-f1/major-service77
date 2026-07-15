<?php
/**
 * Профиль пользователя.
 *
 * @package MajorService77
 */

defined( 'ABSPATH' ) || exit;

$user     = wp_get_current_user();
$uid      = $user->ID;
$mid      = get_user_meta( $uid, '_af_middle_name', true );
$phone    = get_user_meta( $uid, 'billing_phone', true );
$postcode = get_user_meta( $uid, 'billing_postcode', true );
$city     = get_user_meta( $uid, 'billing_city', true );
$addr     = get_user_meta( $uid, 'billing_address_1', true );

$edit_acc  = wc_get_account_endpoint_url( 'edit-account' );
$edit_addr = wc_get_endpoint_url( 'edit-address', 'billing', wc_get_page_permalink( 'myaccount' ) );
$del_url   = wp_nonce_url( add_query_arg( 'mjr_delete_account', '1', wc_get_page_permalink( 'myaccount' ) ), 'mjr_delete_account' );

$personal = array(
	array( 'Имя', $user->first_name, $edit_acc ),
	array( 'Фамилия', $user->last_name, $edit_acc ),
	array( 'Отчество', $mid, $edit_acc ),
	array( 'Email', $user->user_email ? mjr_mask_email( $user->user_email ) : '', $edit_acc ),
	array( 'Телефон', $phone, $edit_addr ),
	array( 'Пароль', '••••••••', $edit_acc ),
);
$address = array(
	array( 'Индекс', $postcode, $edit_addr ),
	array( 'Город', $city, $edit_addr ),
	array( 'Адрес', $addr, $edit_addr ),
);

$row = function ( $f ) {
	echo '<div class="pf"><span class="pf-label">' . esc_html( $f[0] ) . '</span>';
	echo '<span class="pf-val">' . esc_html( $f[1] ? $f[1] : '—' ) . '</span>';
	echo '<a class="pf-edit" href="' . esc_url( $f[2] ) . '">Изменить</a></div>';
};
?>
<div class="account-card profile">
	<div class="profile-head">
		<h3 class="account-card__title"><?php echo mjr_icon( 'user', 20 ); ?> Профиль</h3>
		<a class="profile-delete" href="<?php echo esc_url( $del_url ); ?>" onclick="return confirm('Удалить профиль без возможности восстановления?');">
			<?php echo mjr_icon( 'x', 16 ); ?><span>Удалить профиль</span>
		</a>
	</div>

	<h4 class="profile-section">Основные данные</h4>
	<div class="profile-grid">
		<?php foreach ( $personal as $f ) { $row( $f ); } ?>
	</div>

	<div class="profile-divider"></div>

	<h4 class="profile-section">Адрес для доставки</h4>
	<div class="profile-grid">
		<?php foreach ( $address as $f ) { $row( $f ); } ?>
	</div>
</div>
