<?php
/**
 * Базовый шаблон (список записей / фолбэк).
 *
 * @package MajorService77
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="container page-body">
	<?php
	if ( have_posts() ) :
		if ( ! is_front_page() && ( is_home() || is_archive() || is_search() ) ) {
			echo '<h1 class="page-title">' . esc_html( wp_get_document_title() ) . '</h1>';
		}
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'entry' ); ?>>
				<h2 class="entry__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
				<div class="entry__content"><?php the_excerpt(); ?></div>
			</article>
			<?php
		endwhile;
		the_posts_pagination();
	else :
		echo '<p>Ничего не найдено.</p>';
	endif;
	?>
</div>
<?php
get_footer();
