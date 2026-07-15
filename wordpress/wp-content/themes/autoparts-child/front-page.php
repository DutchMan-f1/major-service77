<?php
/**
 * Главная страница.
 *
 * @package MajorService77
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<section class="hero">
	<div class="container">
		<h1 class="hero__title">
			ЗАПЧАСТИ ДЛЯ АВТО ИЗ <span class="hero__accent">КИТАЯ</span>
			<span class="hero__flag" aria-hidden="true">
				<svg viewBox="0 0 30 20" width="34" height="23"><rect width="30" height="20" rx="2" fill="#DE2910"/><g fill="#FFDE00"><path d="M6 4l.9 2.7H9.8L7.5 8.4l.9 2.7L6 9.4 3.6 11.1l.9-2.7L2.2 6.7H5z"/><circle cx="12.5" cy="2.6" r=".9"/><circle cx="14.4" cy="4.4" r=".9"/><circle cx="14.4" cy="7" r=".9"/><circle cx="12.5" cy="8.8" r=".9"/></g></svg>
			</span>
		</h1>
		<p class="hero__sub">При подборе автозапчастей необходимо руководствоваться VIN номером автомобиля</p>

		<?php echo mjr_search_form( 'hero' ); ?>

		<div class="brands">
			<?php foreach ( mjr_brands() as $brand ) :
				list( $name, $slug ) = $brand; ?>
				<a class="chip" href="<?php echo esc_url( mjr_brand_url( $name, $slug ) ); ?>">
					<?php echo mjr_brand_emblem( $slug ); ?>
					<span class="chip__name"><?php echo esc_html( $name ); ?></span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php
get_footer();
