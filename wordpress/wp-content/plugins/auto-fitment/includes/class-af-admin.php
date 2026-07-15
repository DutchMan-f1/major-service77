<?php
/**
 * Админ-страница: настройки синхронизации baz-on, маппинг колонок, ручной запуск, лог.
 *
 * @package AutoFitment
 */

defined( 'ABSPATH' ) || exit;

class AF_Admin {

	const PAGE = 'af-bazon';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_post' ) );
	}

	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=vehicle',
			'Синхронизация baz-on',
			'Синхронизация baz-on',
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Сохранение настроек и обработка кнопок.
	 */
	public static function handle_post() {
		if ( empty( $_POST['af_nonce'] ) || ! wp_verify_nonce( $_POST['af_nonce'], 'af_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Ручной запуск.
		if ( isset( $_POST['af_sync_now'] ) ) {
			( new AF_Bazon_Importer() )->force_full_resync();
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-success is-dismissible"><p>Синхронизация запущена. Обновите страницу через минуту, чтобы увидеть прогресс.</p></div>';
			} );
			return;
		}

		// Сохранение настроек.
		$s = wp_parse_args( get_option( AF_OPTION_SETTINGS, array() ), array( 'map' => array() ) );

		$s['feed_url']           = esc_url_raw( trim( wp_unslash( $_POST['feed_url'] ?? '' ) ) );
		$s['delimiter']          = substr( (string) ( $_POST['delimiter'] ?? ';' ), 0, 1 );
		$s['encoding']           = sanitize_text_field( $_POST['encoding'] ?? 'auto' );
		$s['has_header']         = empty( $_POST['has_header'] ) ? 0 : 1;
		$s['batch_size']         = max( 20, absint( $_POST['batch_size'] ?? 150 ) );
		$s['time_budget']        = max( 5, absint( $_POST['time_budget'] ?? 20 ) );
		$s['create_missing']     = empty( $_POST['create_missing'] ) ? 0 : 1;
		$s['update_price']       = empty( $_POST['update_price'] ) ? 0 : 1;
		$s['update_description'] = empty( $_POST['update_description'] ) ? 0 : 1;
		$s['update_name']        = empty( $_POST['update_name'] ) ? 0 : 1;
		$s['disable_absent']     = empty( $_POST['disable_absent'] ) ? 0 : 1;
		$s['build_fitment']      = empty( $_POST['build_fitment'] ) ? 0 : 1;
		$s['import_images']      = empty( $_POST['import_images'] ) ? 0 : 1;
		$s['category_source']    = in_array( $_POST['category_source'] ?? 'name', array( 'none', 'name', 'make' ), true ) ? $_POST['category_source'] : 'name';
		$s['price_markup']       = (float) str_replace( ',', '.', $_POST['price_markup'] ?? 0 );

		$fields = array_keys( AF_Bazon_Importer::default_settings()['map'] );
		foreach ( $fields as $f ) {
			$s['map'][ $f ] = sanitize_text_field( $_POST['map'][ $f ] ?? '' );
		}

		update_option( AF_OPTION_SETTINGS, $s );
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-success is-dismissible"><p>Настройки сохранены.</p></div>';
		} );
	}

	public static function render() {
		$s      = wp_parse_args( get_option( AF_OPTION_SETTINGS, array() ), array( 'map' => array() ) );
		$status = AF_Logger::get_status();
		$next   = wp_next_scheduled( AF_CRON_HOOK );
		?>
		<div class="wrap">
			<h1>Синхронизация каталога baz-on</h1>

			<div class="notice notice-info inline" style="padding:12px;">
				<strong>Статус:</strong>
				<?php if ( ! empty( $status['running'] ) ) : ?>
					<span style="color:#b26a00;">выполняется…</span>
				<?php else : ?>
					<span style="color:#1a7f37;">ожидание</span>
				<?php endif; ?>
				&nbsp;|&nbsp; Обработано: <?php echo (int) ( $status['processed'] ?? 0 ); ?>,
				создано: <?php echo (int) ( $status['created'] ?? 0 ); ?>,
				обновлено: <?php echo (int) ( $status['updated'] ?? 0 ); ?>,
				пропущено: <?php echo (int) ( $status['skipped'] ?? 0 ); ?>
				<br>
				Последний запуск: <?php echo esc_html( $status['finished'] ?? $status['started'] ?? '—' ); ?>
				&nbsp;|&nbsp; Следующий по расписанию: <?php echo $next ? esc_html( wp_date( 'H:i:s d.m.Y', $next ) ) : 'не запланирован'; ?>
				<?php if ( ! empty( $status['last_error'] ) ) : ?>
					<br><span style="color:#d63638;">Ошибка: <?php echo esc_html( $status['last_error'] ); ?></span>
				<?php endif; ?>
			</div>

			<form method="post">
				<?php wp_nonce_field( 'af_save', 'af_nonce' ); ?>

				<h2 class="title">Источник</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="feed_url">Ссылка на CSV baz-on</label></th>
						<td>
							<input name="feed_url" id="feed_url" type="url" class="regular-text code"
							       value="<?php echo esc_attr( $s['feed_url'] ?? '' ); ?>"
							       placeholder="https://baz-on.ru/export/price.csv" style="width:520px;">
							<p class="description">Прямая ссылка на прайс. Обновляется поставщиком раз в 30 минут — WP-Cron забирает её по расписанию.</p>
						</td>
					</tr>
					<tr>
						<th>Разделитель</th>
						<td><input name="delimiter" type="text" size="2" value="<?php echo esc_attr( $s['delimiter'] ?? ';' ); ?>"> <span class="description">обычно <code>;</code></span></td>
					</tr>
					<tr>
						<th>Кодировка</th>
						<td>
							<select name="encoding">
								<?php foreach ( array( 'auto' => 'Авто-определение', 'UTF-8' => 'UTF-8', 'Windows-1251' => 'Windows-1251', 'CP866' => 'CP866' ) as $k => $label ) : ?>
									<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['encoding'] ?? 'auto', $k ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
							<span class="description">Российские прайсы часто в Windows-1251.</span>
						</td>
					</tr>
					<tr>
						<th>Первая строка — заголовки</th>
						<td><label><input type="checkbox" name="has_header" value="1" <?php checked( $s['has_header'] ?? 1, 1 ); ?>> да</label></td>
					</tr>
				</table>

				<h2 class="title">Маппинг колонок</h2>
				<p class="description">Укажите <strong>название колонки</strong> из CSV (при заголовках) или <strong>номер колонки с 0</strong> (без заголовков). Пустое поле — не импортировать.</p>
				<table class="form-table" role="presentation">
					<?php
					$labels = array(
						'sku'         => 'Артикул (SKU) — обязательно',
						'name'        => 'Наименование',
						'price'       => 'Цена',
						'brand'       => 'Производитель / бренд',
						'condition'   => 'Новый/БУ',
						'description' => 'Описание (комментарий)',
						'image'       => 'Фото (URL через запятую)',
						'warehouse'   => 'Склад / город',
						'make'        => 'Марка (для каталога авто)',
						'model'       => 'Модель (для каталога авто)',
						'generation'  => 'Кузов / поколение',
						'engine'      => 'Двигатель',
						'year'        => 'Год',
						'oem'         => 'Номер производителя (OEM)',
						'cross'       => 'Кросс-номера',
						'color'       => 'Цвет',
						'marking'     => 'Маркировка',
						'donor'       => 'Донор',
						'pos_fb'      => 'Перед/Зад (F/B)',
						'pos_lr'      => 'Лев/Прав (L/R)',
						'pos_ud'      => 'Верх/Низ (U/D)',
					);
					foreach ( $labels as $f => $label ) : ?>
						<tr>
							<th><label><?php echo esc_html( $label ); ?></label></th>
							<td><input name="map[<?php echo esc_attr( $f ); ?>]" type="text" class="regular-text"
							           value="<?php echo esc_attr( $s['map'][ $f ] ?? '' ); ?>"></td>
						</tr>
					<?php endforeach; ?>
				</table>

				<h2 class="title">Поведение импорта</h2>
				<table class="form-table" role="presentation">
					<tr><th>Создавать новые товары</th><td><label><input type="checkbox" name="create_missing" value="1" <?php checked( $s['create_missing'] ?? 1, 1 ); ?>> да</label></td></tr>
					<tr><th>Обновлять цену</th><td><label><input type="checkbox" name="update_price" value="1" <?php checked( $s['update_price'] ?? 1, 1 ); ?>> да</label></td></tr>
					<tr><th>Обновлять название</th><td><label><input type="checkbox" name="update_name" value="1" <?php checked( $s['update_name'] ?? 1, 1 ); ?>> да</label></td></tr>
					<tr><th>Обновлять описание</th><td><label><input type="checkbox" name="update_description" value="1" <?php checked( $s['update_description'] ?? 1, 1 ); ?>> да</label></td></tr>
					<tr><th>Строить каталог авто + фитмент</th><td><label><input type="checkbox" name="build_fitment" value="1" <?php checked( $s['build_fitment'] ?? 1, 1 ); ?>> да (из колонок Марка/Модель/Кузов/Двигатель)</label></td></tr>
					<tr><th>Импортировать фото</th><td><label><input type="checkbox" name="import_images" value="1" <?php checked( $s['import_images'] ?? 1, 1 ); ?>> да <span class="description">(медленно на первом прогоне; далее только изменённые)</span></label></td></tr>
					<tr><th>Снимать с наличия отсутствующие в фиде</th><td><label><input type="checkbox" name="disable_absent" value="1" <?php checked( $s['disable_absent'] ?? 1, 1 ); ?>> да</label></td></tr>
					<tr><th>Категория товара из</th><td>
						<select name="category_source">
							<?php foreach ( array( 'name' => 'Названия детали', 'make' => 'Марки авто', 'none' => 'Не создавать' ) as $k => $label ) : ?>
								<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $s['category_source'] ?? 'name', $k ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td></tr>
					<tr><th>Наценка, %</th><td><input name="price_markup" type="text" size="6" value="<?php echo esc_attr( $s['price_markup'] ?? 0 ); ?>"> <span class="description">поверх цены поставщика</span></td></tr>
					<tr><th>Строк за проход</th><td><input name="batch_size" type="number" min="20" value="<?php echo esc_attr( $s['batch_size'] ?? 150 ); ?>"></td></tr>
					<tr><th>Бюджет времени, сек</th><td><input name="time_budget" type="number" min="5" value="<?php echo esc_attr( $s['time_budget'] ?? 20 ); ?>"></td></tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary">Сохранить настройки</button>
					<button type="submit" name="af_sync_now" value="1" class="button">Синхронизировать сейчас</button>
				</p>
			</form>

			<h2>Лог (последние строки)</h2>
			<textarea readonly rows="16" style="width:100%;font-family:monospace;font-size:12px;"><?php echo esc_textarea( AF_Logger::tail( 120 ) ); ?></textarea>
		</div>
		<?php
	}
}
