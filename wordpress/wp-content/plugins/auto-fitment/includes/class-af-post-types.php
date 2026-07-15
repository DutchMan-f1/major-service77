<?php
/**
 * Каталог автомобилей: CPT «vehicle» + таксономии марка/модель/поколение.
 *
 * @package AutoFitment
 */

defined( 'ABSPATH' ) || exit;

class AF_Post_Types {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_taxonomies' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_post_type' ), 6 );
	}

	/**
	 * CPT «vehicle» — одна запись = одна модификация автомобиля.
	 */
	public static function register_post_type() {
		$labels = array(
			'name'               => 'Автомобили',
			'singular_name'      => 'Автомобиль',
			'menu_name'          => 'Каталог авто',
			'add_new'            => 'Добавить',
			'add_new_item'       => 'Добавить автомобиль',
			'edit_item'          => 'Редактировать автомобиль',
			'new_item'           => 'Новый автомобиль',
			'view_item'          => 'Просмотр',
			'search_items'       => 'Искать авто',
			'not_found'          => 'Не найдено',
			'all_items'          => 'Все автомобили',
		);

		register_post_type( 'vehicle', array(
			'labels'             => $labels,
			'public'             => true,
			'show_in_rest'       => true,
			'has_archive'        => 'vehicles',
			'menu_icon'          => 'dashicons-car',
			'menu_position'      => 26,
			'supports'           => array( 'title', 'thumbnail', 'custom-fields' ),
			'rewrite'            => array( 'slug' => 'vehicles', 'with_front' => false ),
			'hierarchical'       => false,
		) );
	}

	/**
	 * Таксономии: марка -> модель -> поколение.
	 */
	public static function register_taxonomies() {
		$common = array(
			'public'            => true,
			'hierarchical'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
		);

		register_taxonomy( 'car_make', 'vehicle', array_merge( $common, array(
			'labels'   => self::tax_labels( 'Марка', 'Марки' ),
			'rewrite'  => array( 'slug' => 'make' ),
		) ) );

		register_taxonomy( 'car_model', 'vehicle', array_merge( $common, array(
			'labels'   => self::tax_labels( 'Модель', 'Модели' ),
			'rewrite'  => array( 'slug' => 'model' ),
		) ) );

		register_taxonomy( 'car_generation', 'vehicle', array_merge( $common, array(
			'labels'   => self::tax_labels( 'Поколение', 'Поколения' ),
			'rewrite'  => array( 'slug' => 'generation' ),
		) ) );
	}

	protected static function tax_labels( $single, $plural ) {
		return array(
			'name'          => $plural,
			'singular_name' => $single,
			'menu_name'     => $plural,
			'all_items'     => 'Все: ' . $plural,
			'edit_item'     => 'Изменить: ' . $single,
			'add_new_item'  => 'Добавить: ' . $single,
			'search_items'  => 'Искать: ' . $plural,
		);
	}
}
