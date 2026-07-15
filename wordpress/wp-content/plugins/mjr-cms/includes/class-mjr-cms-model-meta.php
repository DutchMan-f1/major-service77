<?php
/**
 * Фото модели авто в экране редактирования термина car_model.
 * Описание модели редактируется штатным полем «Описание» термина.
 *
 * @package MJR_CMS
 */

defined( 'ABSPATH' ) || exit;

class MJR_CMS_Model_Meta {

	const META = '_mjr_model_photo';

	public static function init() {
		add_action( 'car_model_add_form_fields', array( __CLASS__, 'add_field' ) );
		add_action( 'car_model_edit_form_fields', array( __CLASS__, 'edit_field' ), 10, 2 );
		add_action( 'created_car_model', array( __CLASS__, 'save' ) );
		add_action( 'edited_car_model', array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function assets( $hook ) {
		if ( 'edit-tags.php' !== $hook && 'term.php' !== $hook ) {
			return;
		}
		if ( ! isset( $_GET['taxonomy'] ) || 'car_model' !== $_GET['taxonomy'] ) {
			return;
		}
		wp_enqueue_media();
		add_action( 'admin_print_footer_scripts', array( __CLASS__, 'js' ) );
	}

	public static function add_field() {
		?>
		<div class="form-field">
			<label>Фото автомобиля</label>
			<div class="mjr-model-media">
				<div class="mjr-model-media__preview"></div>
				<input type="hidden" name="mjr_model_photo" value="">
				<button type="button" class="button mjr-model-media__pick">Выбрать фото</button>
				<button type="button" class="button-link mjr-model-media__clear" style="display:none">Убрать</button>
			</div>
			<p>Показывается в блоке описания модели. Пусто — используется изображение из темы.</p>
		</div>
		<?php
	}

	public static function edit_field( $term ) {
		$id  = absint( get_term_meta( $term->term_id, self::META, true ) );
		$src = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
		?>
		<tr class="form-field">
			<th scope="row"><label>Фото автомобиля</label></th>
			<td>
				<div class="mjr-model-media">
					<div class="mjr-model-media__preview">
						<?php if ( $src ) : ?>
							<img src="<?php echo esc_url( $src ); ?>" style="max-height:110px;border-radius:8px">
						<?php endif; ?>
					</div>
					<input type="hidden" name="mjr_model_photo" value="<?php echo esc_attr( $id ); ?>">
					<button type="button" class="button mjr-model-media__pick">Выбрать фото</button>
					<button type="button" class="button-link mjr-model-media__clear" <?php echo $id ? '' : 'style="display:none"'; ?>>Убрать</button>
				</div>
				<p class="description">Показывается в блоке описания модели. Пусто — используется изображение из темы.</p>
			</td>
		</tr>
		<?php
	}

	public static function save( $term_id ) {
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}
		if ( ! isset( $_POST['mjr_model_photo'] ) ) {
			return;
		}
		$id = absint( $_POST['mjr_model_photo'] );
		if ( $id ) {
			update_term_meta( $term_id, self::META, $id );
		} else {
			delete_term_meta( $term_id, self::META );
		}
	}

	public static function js() {
		?>
		<script>
		( function ( $ ) {
			$( document ).on( 'click', '.mjr-model-media__pick', function ( e ) {
				e.preventDefault();
				var box = $( this ).closest( '.mjr-model-media' );
				var frame = wp.media( { title: 'Фото автомобиля', button: { text: 'Использовать' }, multiple: false } );
				frame.on( 'select', function () {
					var a = frame.state().get( 'selection' ).first().toJSON();
					box.find( 'input[type=hidden]' ).val( a.id );
					var url = ( a.sizes && a.sizes.medium ) ? a.sizes.medium.url : a.url;
					box.find( '.mjr-model-media__preview' ).html( '<img src="' + url + '" style="max-height:110px;border-radius:8px">' );
					box.find( '.mjr-model-media__clear' ).show();
				} );
				frame.open();
			} );
			$( document ).on( 'click', '.mjr-model-media__clear', function ( e ) {
				e.preventDefault();
				var box = $( this ).closest( '.mjr-model-media' );
				box.find( 'input[type=hidden]' ).val( '' );
				box.find( '.mjr-model-media__preview' ).empty();
				$( this ).hide();
			} );
		} )( jQuery );
		</script>
		<?php
	}
}
