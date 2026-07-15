<?php
/**
 * Результаты поиска. Поиск по товарам показывает каталог, остальное — обычный список.
 *
 * @package MajorService77
 */

defined( 'ABSPATH' ) || exit;

get_header();

$is_product_search = ( 'product' === get_query_var( 'post_type' ) ) && function_exists( 'wc_get_product' );

if ( $is_product_search ) {
	$q = get_search_query();
	mjr_render_catalog( $q !== '' ? $q : 'Каталог' );
} else {
	?>
	<div class="container page-body">
		<h1 class="page-title">Поиск: <?php echo esc_html( get_search_query() ); ?></h1>
		<?php if ( have_posts() ) : ?>
			<?php while ( have_posts() ) : the_post(); ?>
				<article <?php post_class( 'entry' ); ?>>
					<h2 class="entry__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<div class="entry__content"><?php the_excerpt(); ?></div>
				</article>
			<?php endwhile; ?>
			<?php the_posts_pagination(); ?>
		<?php else : ?>
			<p>Ничего не найдено.</p>
		<?php endif; ?>
	</div>
	<?php
}

get_footer();
