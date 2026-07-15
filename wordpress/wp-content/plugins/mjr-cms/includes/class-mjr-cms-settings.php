<?php
/**
 * Панель «Управление сайтом»: баннер, подвал, вкладки товара.
 *
 * @package MJR_CMS
 */

defined( 'ABSPATH' ) || exit;

class MJR_CMS_Settings {

	const CAP   = 'manage_mjr_cms';
	const GROUP = 'mjr_cms_group';
	const PAGE  = 'mjr-cms';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );

		// Разрешаем сохранять настройки пользователю с нашей capability (иначе options.php требует manage_options).
		add_filter( 'option_page_capability_' . self::GROUP, function () {
			return self::CAP;
		} );
	}

	public static function menu() {
		add_menu_page(
			'Управление сайтом',
			'Управление сайтом',
			self::CAP,
			self::PAGE,
			array( __CLASS__, 'render' ),
			'dashicons-admin-customizer',
			3
		);
		add_submenu_page( self::PAGE, 'Контент сайта', 'Контент сайта', self::CAP, self::PAGE, array( __CLASS__, 'render' ) );

		// Быстрый доступ к моделям авто (фото + описание).
		if ( taxonomy_exists( 'car_model' ) ) {
			add_submenu_page(
				self::PAGE,
				'Модели авто',
				'Модели авто',
				'manage_categories',
				'edit-tags.php?taxonomy=car_model&post_type=vehicle'
			);
		}
	}

	public static function register() {
		register_setting( self::GROUP, 'mjr_cms', array(
			'type'              => 'array',
			'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			'default'           => array(),
		) );
	}

	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = get_option( 'mjr_cms', array() );
		$out   = is_array( $out ) ? $out : array();

		$text = array( 'banner_title', 'banner_btn_text', 'banner_btn_link', 'footer_org', 'footer_ogrn', 'footer_email' );
		foreach ( $text as $k ) {
			if ( isset( $input[ $k ] ) ) {
				$out[ $k ] = sanitize_text_field( $input[ $k ] );
			}
		}

		if ( isset( $input['footer_address'] ) ) {
			$out['footer_address'] = sanitize_textarea_field( $input['footer_address'] );
		}

		$out['banner_image'] = isset( $input['banner_image'] ) ? absint( $input['banner_image'] ) : 0;

		foreach ( array( 'tab_delivery', 'tab_payment', 'tab_warranty' ) as $k ) {
			if ( isset( $input[ $k ] ) ) {
				$out[ $k ] = wp_kses_post( wp_unslash( $input[ $k ] ) );
			}
		}

		return $out;
	}

	public static function assets( $hook ) {
		if ( 'toplevel_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_media();
	}

	private static function val( $key ) {
		$o = get_option( 'mjr_cms', array() );
		return is_array( $o ) && isset( $o[ $key ] ) ? $o[ $key ] : '';
	}

	private static function field_text( $key, $placeholder = '' ) {
		printf(
			'<input type="text" name="mjr_cms[%1$s]" value="%2$s" placeholder="%3$s" class="regular-text">',
			esc_attr( $key ),
			esc_attr( self::val( $key ) ),
			esc_attr( $placeholder )
		);
	}

	public static function render() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$img_id  = absint( self::val( 'banner_image' ) );
		$img_src = $img_id ? wp_get_attachment_image_url( $img_id, 'medium' ) : '';
		?>
		<div class="wrap">
			<h1>Управление сайтом</h1>
			<p class="description">Здесь меняется контент без правки кода. Пустое поле — показывается текст по умолчанию.</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<h2 class="title">Баннер «Не нашли запчасть?»</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label>Заголовок</label></th>
						<td><?php self::field_text( 'banner_title', 'НЕ НАШЛИ НУЖНУЮ ЗАПЧАСТЬ?' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><label>Текст кнопки</label></th>
						<td><?php self::field_text( 'banner_btn_text', 'Поможем подобрать' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><label>Ссылка кнопки</label></th>
						<td>
							<?php self::field_text( 'banner_btn_link', 'tel:+78432392013' ); ?>
							<p class="description">Например <code>tel:+78432392013</code> или <code>https://…</code></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label>Изображение</label></th>
						<td>
							<div class="mjr-media" data-target="banner_image">
								<div class="mjr-media__preview">
									<?php if ( $img_src ) : ?>
										<img src="<?php echo esc_url( $img_src ); ?>" style="max-height:90px;border-radius:8px">
									<?php endif; ?>
								</div>
								<input type="hidden" name="mjr_cms[banner_image]" value="<?php echo esc_attr( $img_id ); ?>">
								<button type="button" class="button mjr-media__pick">Выбрать изображение</button>
								<button type="button" class="button-link mjr-media__clear" <?php echo $img_id ? '' : 'style="display:none"'; ?>>Убрать</button>
							</div>
							<p class="description">Пусто — используется картинка из темы.</p>
						</td>
					</tr>
				</table>

				<h2 class="title">Подвал сайта</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label>Организация</label></th>
						<td><?php self::field_text( 'footer_org', 'ООО «Мейджор Сервис»' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><label>ОГРН</label></th>
						<td><?php self::field_text( 'footer_ogrn', 'ОГРН: 1167746714156' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><label>Адрес</label></th>
						<td>
							<textarea name="mjr_cms[footer_address]" rows="2" class="large-text" placeholder="Адрес: 115035, город Москва,&#10;Садовническая ул, д. 82 стр. 2"><?php echo esc_textarea( self::val( 'footer_address' ) ); ?></textarea>
							<p class="description">Каждая строка — с новой строки.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label>E-mail</label></th>
						<td><?php self::field_text( 'footer_email', 'major@major.ru' ); ?></td>
					</tr>
				</table>

				<h2 class="title">Вкладки в карточке товара</h2>
				<p class="description">Единый текст для всех товаров. «Описание» и «Аналоги» формируются автоматически.</p>
				<?php
				$editors = array(
					'tab_delivery' => 'Доставка',
					'tab_payment'  => 'Оплата',
					'tab_warranty' => 'Гарантия',
				);
				foreach ( $editors as $key => $label ) {
					echo '<h3>' . esc_html( $label ) . '</h3>';
					wp_editor(
						self::val( $key ),
						'mjr_cms_' . $key,
						array(
							'textarea_name' => 'mjr_cms[' . $key . ']',
							'textarea_rows' => 7,
							'media_buttons' => false,
							'teeny'         => true,
						)
					);
				}
				?>

				<?php submit_button( 'Сохранить' ); ?>
			</form>
		</div>

		<script>
		( function ( $ ) {
			$( '.mjr-media' ).each( function () {
				var box = $( this ), frame;
				box.find( '.mjr-media__pick' ).on( 'click', function ( e ) {
					e.preventDefault();
					if ( frame ) { frame.open(); return; }
					frame = wp.media( { title: 'Выбор изображения', button: { text: 'Использовать' }, multiple: false } );
					frame.on( 'select', function () {
						var a = frame.state().get( 'selection' ).first().toJSON();
						box.find( 'input[type=hidden]' ).val( a.id );
						var url = ( a.sizes && a.sizes.medium ) ? a.sizes.medium.url : a.url;
						box.find( '.mjr-media__preview' ).html( '<img src="' + url + '" style="max-height:90px;border-radius:8px">' );
						box.find( '.mjr-media__clear' ).show();
					} );
					frame.open();
				} );
				box.find( '.mjr-media__clear' ).on( 'click', function ( e ) {
					e.preventDefault();
					box.find( 'input[type=hidden]' ).val( '' );
					box.find( '.mjr-media__preview' ).empty();
					$( this ).hide();
				} );
			} );
		} )( jQuery );
		</script>
		<?php
	}
}
