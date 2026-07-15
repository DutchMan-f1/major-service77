<?php
/**
 * Страница марки авто: сетка моделей.
 *
 * @package MajorService77
 */

defined( 'ABSPATH' ) || exit;

get_header();

$make      = get_queried_object();
$make_name = $make ? $make->name : '';
$make_slug = $make ? $make->slug : '';

// Модели этой марки (через записи vehicle).
$vehicle_ids = get_posts( array(
	'post_type'      => 'vehicle',
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'tax_query'      => array( array( 'taxonomy' => 'car_make', 'field' => 'term_id', 'terms' => $make->term_id ) ),
) );
$models = $vehicle_ids ? wp_get_object_terms( $vehicle_ids, 'car_model' ) : array();
if ( ! is_wp_error( $models ) && $models ) {
	usort( $models, function ( $a, $b ) { return strcmp( $a->name, $b->name ); } );
} else {
	$models = array();
}

$brand_logo = mjr_find_asset( "assets/brands/{$make_slug}" );
?>

<div class="container catalog">

	<nav class="breadcrumbs" aria-label="Хлебные крошки">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Главная</a>
		<span class="sep">/</span>
		<span class="is-current"><?php echo esc_html( $make_name ); ?></span>
	</nav>

	<div class="catalog-head">
		<a class="back-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>" onclick="if(document.referrer){history.back();return false;}">
			<?php echo mjr_icon( 'arrow-left', 18 ); ?><span>Назад</span>
		</a>
		<h1 class="catalog-title">
			<?php if ( $brand_logo ) : ?>
				<img class="catalog-title__logo" src="<?php echo esc_url( $brand_logo ); ?>" alt="" height="40">
			<?php endif; ?>
			<span>АВТОМОБИЛИ <?php echo esc_html( mb_strtoupper( $make_name, 'UTF-8' ) ); ?></span>
		</h1>
	</div>

	<?php if ( $models ) : ?>
		<div class="models-grid">
			<?php foreach ( $models as $m ) :
				$full = trim( $make_name . ' ' . $m->name );
				?>
				<a class="model-card" href="<?php echo esc_url( get_term_link( $m ) ); ?>">
					<span class="model-card__img"><?php echo mjr_model_image( $make_slug . '-' . $m->slug, $m->term_id ); ?></span>
					<span class="model-card__name"><?php echo esc_html( $full ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<p class="catalog-empty">Модели для этой марки пока не добавлены.</p>
	<?php endif; ?>

</div>

<?php
get_footer();
