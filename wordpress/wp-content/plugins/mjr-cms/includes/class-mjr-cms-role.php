<?php
/**
 * Роль «Контент-менеджер»: видит только «Управление сайтом» и «Модели авто».
 * Всё остальное (плагины, тема, код, настройки, товары, пользователи) недоступно.
 *
 * @package MJR_CMS
 */

defined( 'ABSPATH' ) || exit;

class MJR_CMS_Role {

	const ROLE = 'mjr_content_manager';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'lock_menu' ), 999 );
		add_action( 'admin_init', array( __CLASS__, 'guard' ) );
		add_action( 'admin_init', array( __CLASS__, 'guard_ajax_terms' ) );
		add_action( 'wp_before_admin_bar_render', array( __CLASS__, 'trim_admin_bar' ) );
	}

	/**
	 * Контент-менеджер имеет manage_categories (нужно для терминов моделей авто),
	 * из-за чего через admin-ajax он мог бы править категории/метки блога.
	 * Разрешаем AJAX-действия с терминами ТОЛЬКО для таксономии car_model.
	 */
	public static function guard_ajax_terms() {
		if ( ! self::is_content_manager() || ! wp_doing_ajax() ) {
			return;
		}
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : '';
		if ( in_array( $action, array( 'add-tag', 'delete-tag', 'inline-save-tax', 'get-tagcloud' ), true ) ) {
			$tax = isset( $_REQUEST['taxonomy'] ) ? sanitize_key( $_REQUEST['taxonomy'] ) : '';
			if ( 'car_model' !== $tax ) {
				wp_die( -1, 403 );
			}
		}
	}

	/**
	 * Роль и capability. Вызывается при активации плагина.
	 */
	public static function add_role() {
		add_role(
			self::ROLE,
			'Контент-менеджер',
			array(
				'read'              => true,
				'upload_files'      => true,
				MJR_CMS_Settings::CAP => true,
				'manage_categories' => true, // редактирование терминов моделей авто
			)
		);

		// Администратору тоже даём доступ к панели.
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( MJR_CMS_Settings::CAP ) ) {
			$admin->add_cap( MJR_CMS_Settings::CAP );
		}
	}

	public static function remove_role() {
		remove_role( self::ROLE );
	}

	private static function is_content_manager() {
		$u = wp_get_current_user();
		if ( ! $u || ! $u->ID ) {
			return false;
		}
		$roles = (array) $u->roles;
		return in_array( self::ROLE, $roles, true ) && ! in_array( 'administrator', $roles, true );
	}

	/**
	 * Скрываем всё меню, кроме нашей панели и дашборда.
	 */
	public static function lock_menu() {
		if ( ! self::is_content_manager() ) {
			return;
		}
		global $menu;
		$allowed = array( 'mjr-cms', 'index.php' );
		$remove  = array();
		foreach ( (array) $menu as $item ) {
			if ( empty( $item[2] ) ) {
				continue;
			}
			if ( ! in_array( $item[2], $allowed, true ) ) {
				$remove[] = $item[2];
			}
		}
		foreach ( $remove as $slug ) {
			remove_menu_page( $slug );
		}
	}

	/**
	 * Запрет прямого доступа к «чужим» экранам админки.
	 */
	public static function guard() {
		if ( ! self::is_content_manager() || wp_doing_ajax() ) {
			return;
		}

		global $pagenow;

		// Разрешённые экраны.
		$allowed_pages = array( 'index.php', 'admin.php', 'options.php', 'media-upload.php', 'async-upload.php', 'upload.php', 'edit-tags.php', 'term.php', 'profile.php' );

		if ( ! in_array( $pagenow, $allowed_pages, true ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . MJR_CMS_Settings::PAGE ) );
			exit;
		}

		// admin.php — только наша страница.
		if ( 'admin.php' === $pagenow ) {
			$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
			if ( MJR_CMS_Settings::PAGE !== $page ) {
				wp_safe_redirect( admin_url( 'admin.php?page=' . MJR_CMS_Settings::PAGE ) );
				exit;
			}
		}

		// Таксономии — только модели авто.
		if ( in_array( $pagenow, array( 'edit-tags.php', 'term.php' ), true ) ) {
			$tax = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : '';
			if ( 'car_model' !== $tax ) {
				wp_safe_redirect( admin_url( 'admin.php?page=' . MJR_CMS_Settings::PAGE ) );
				exit;
			}
		}
	}

	public static function trim_admin_bar() {
		if ( ! self::is_content_manager() ) {
			return;
		}
		global $wp_admin_bar;
		foreach ( array( 'new-content', 'comments', 'wp-logo', 'updates' ) as $node ) {
			$wp_admin_bar->remove_node( $node );
		}
	}
}
