<?php
/**
 * Страница модели авто: обзор категорий запчастей, а по ?cat= — сетка подкатегорий.
 *
 * @package MajorService77
 */

defined( 'ABSPATH' ) || exit;

get_header();

$model      = get_queried_object();
$model_name = $model ? $model->name : '';
$model_slug = $model ? $model->slug : '';

// Марка модели (через любую запись vehicle).
$vids = get_posts( array(
	'post_type'      => 'vehicle',
	'posts_per_page' => 1,
	'fields'         => 'ids',
	'tax_query'      => array( array( 'taxonomy' => 'car_model', 'field' => 'term_id', 'terms' => $model->term_id ) ),
) );
$make = null;
if ( $vids ) {
	$mk = wp_get_object_terms( $vids, 'car_make' );
	if ( $mk && ! is_wp_error( $mk ) ) {
		$make = $mk[0];
	}
}
$make_name = $make ? $make->name : '';
$make_slug = $make ? $make->slug : '';
$make_url  = $make ? get_term_link( $make ) : home_url( '/' );

$full       = trim( $make_name . ' ' . $model_name );
$categories = mjr_part_categories();
$model_url  = get_term_link( $model );

// Текущая категория (?cat=engine) — если задана и валидна, показываем подкатегории.
$cat_key = isset( $_GET['cat'] ) ? sanitize_key( $_GET['cat'] ) : '';
$cat     = ( $cat_key && isset( $categories[ $cat_key ] ) ) ? $categories[ $cat_key ] : null;
?>

<div class="container catalog">

	<nav class="breadcrumbs" aria-label="Хлебные крошки">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a>
		<span class="sep">/</span>
		<a href="<?php echo esc_url( $make_url ); ?>"><?php echo esc_html( $make_name ); ?></a>
		<span class="sep">/</span>
		<?php if ( $cat ) : ?>
			<a href="<?php echo esc_url( $model_url ); ?>"><?php echo esc_html( $full ); ?></a>
			<span class="sep">/</span>
			<span class="is-current"><?php echo esc_html( $cat['name'] ); ?></span>
		<?php else : ?>
			<span class="is-current"><?php echo esc_html( $full ); ?></span>
		<?php endif; ?>
	</nav>

	<div class="catalog-head">
		<a class="back-btn" href="<?php echo esc_url( $cat ? $model_url : $make_url ); ?>" onclick="if(document.referrer){history.back();return false;}">
			<?php echo mjr_icon( 'arrow-left', 18 ); ?><span>Назад</span>
		</a>
		<h1 class="catalog-title">
			<?php if ( $cat ) : ?>
				<span class="catalog-title__catic"><?php echo mjr_cat_icon( $cat_key, 40 ); ?></span>
				<span><?php echo esc_html( mb_strtoupper( $cat['name'], 'UTF-8' ) ); ?></span>
			<?php else :
				$brand_logo = mjr_find_asset( "assets/brands/{$make_slug}" );
				if ( $brand_logo ) : ?>
					<img class="catalog-title__logo" src="<?php echo esc_url( $brand_logo ); ?>" alt="" height="40">
				<?php endif; ?>
				<span><?php echo esc_html( mb_strtoupper( $full, 'UTF-8' ) ); ?></span>
			<?php endif; ?>
		</h1>
	</div>

	<?php if ( $cat ) : /* ---- ВИД ПОДКАТЕГОРИЙ ---- */ ?>

		<div class="subcat-grid">
			<?php foreach ( $cat['subs'] as $i => $sub ) : ?>
				<a class="subcat-card" href="<?php echo esc_url( add_query_arg( array( 'post_type' => 'product', 's' => $sub, 'af_model' => $model_slug, 'af_cat' => $cat_key ), home_url( '/' ) ) ); ?>">
					<span class="subcat-card__img"><?php echo mjr_subcat_image( $cat_key . '-' . ( $i + 1 ) ); ?></span>
					<span class="subcat-card__name"><?php echo esc_html( $sub ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>

		<?php echo mjr_help_banner(); ?>

	<?php else : /* ---- ОБЗОР КАТЕГОРИЙ ---- */ ?>

		<div class="cat-grid">
			<?php foreach ( $categories as $key => $c ) :
				$cat_link = esc_url( add_query_arg( 'cat', $key, $model_url ) ); ?>
				<div class="cat-card">
					<a class="cat-card__head" href="<?php echo $cat_link; ?>">
						<span class="cat-card__ic"><?php echo mjr_cat_icon( $key, 46 ); ?></span>
						<span class="cat-card__title"><?php echo esc_html( mb_strtoupper( $c['name'], 'UTF-8' ) ); ?></span>
					</a>
					<ul class="cat-card__list">
						<?php foreach ( $c['subs'] as $sub ) :
							$sub_link = add_query_arg( array( 'post_type' => 'product', 's' => $sub, 'af_model' => $model_slug, 'af_cat' => $key ), home_url( '/' ) );
							?>
							<li><a href="<?php echo esc_url( $sub_link ); ?>"><?php echo esc_html( $sub ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>
		</div>

		<?php echo mjr_help_banner(); ?>

		<div class="model-desc">
			<div class="model-desc__img"><?php echo mjr_model_image( $make_slug . '-' . $model_slug, $model->term_id ); ?></div>
			<div class="model-desc__body">
				<h2 class="model-desc__title"><?php echo esc_html( mb_strtoupper( $full, 'UTF-8' ) ); ?></h2>
				<div class="model-desc__text">
					<?php
					$desc = term_description( $model );
					if ( $desc ) {
						echo wp_kses_post( $desc );
					} else {
						echo '<p>' . esc_html( $full ) . ' — подбор оригинальных и аналоговых запчастей по каталогу. Выберите категорию выше, чтобы найти нужную деталь для вашего автомобиля, либо воспользуйтесь поиском по артикулу или VIN.</p>';
					}
					?>
				</div>
			</div>
		</div>

	<?php endif; ?>

</div>

<?php
get_footer();
