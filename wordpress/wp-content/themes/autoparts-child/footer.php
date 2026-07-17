<?php
/**
 * Подвал сайта.
 *
 * @package MajorService77
 */

defined( 'ABSPATH' ) || exit;
?>
</main><!-- .site-main -->

<footer class="site-footer">
	<div class="container site-footer__inner">
		<a class="brand brand--muted" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="MAJOR Service77"><?php echo mjr_logo( true ); ?></a>

		<div class="footer-col">
			<div><?php echo esc_html( mjr_content( 'footer_org', 'ООО «Мейджор Сервис»' ) ); ?></div>
			<div><?php echo esc_html( mjr_content( 'footer_ogrn', 'ОГРН: 1167746714156' ) ); ?></div>
		</div>

		<div class="footer-col">
			<?php echo nl2br( esc_html( mjr_content( 'footer_address', "Адрес: 115035, город Москва,\nСадовническая ул, д. 82 стр. 2" ) ) ); ?>
		</div>

		<div class="footer-col footer-col--links">
			<?php $mjr_email = mjr_content( 'footer_email', 'major@major.ru' ); ?>
			<a href="mailto:<?php echo esc_attr( antispambot( $mjr_email ) ); ?>"><?php echo esc_html( $mjr_email ); ?></a>
			<?php
			$mjr_legal = array(
				'politika-konfidencialnosti'   => 'Политика конфиденциальности',
				'obrabotka-personalnyh-dannyh' => 'Обработка персональных данных',
				'oferta'                       => 'Публичная оферта',
			);
			foreach ( $mjr_legal as $mjr_slug => $mjr_label ) :
				$mjr_pg = get_page_by_path( $mjr_slug );
				if ( $mjr_pg ) :
					?>
					<a href="<?php echo esc_url( get_permalink( $mjr_pg ) ); ?>"><?php echo esc_html( $mjr_label ); ?></a>
				<?php endif; endforeach; ?>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
