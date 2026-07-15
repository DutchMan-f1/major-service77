<?php
/**
 * Импортёр каталога baz-on (схема поставщика majorzap: 21 колонка, разборка/новые запчасти).
 *
 * Каждые 30 минут (WP-Cron) скачивает CSV, обновляет товары WooCommerce (цена, наличие,
 * описание, фото, атрибуты) и СТРОИТ каталог авто + фитмент прямо из строк фида
 * (Марка/Модель/Кузов/Двигатель). Потоковый парсинг батчами с самоперепланированием.
 *
 * @package AutoFitment
 */

defined( 'ABSPATH' ) || exit;

class AF_Bazon_Importer {

	const LOCK_KEY  = 'af_bazon_lock';
	const STATE_KEY = 'af_bazon_state';
	const LOCK_TTL  = 900;

	/** @var array настройки */
	protected $settings;

	/** @var array кэш «ключ авто -> vehicle_id» в рамках прогона */
	protected $vehicle_cache = array();

	public function __construct() {
		$this->settings = wp_parse_args( get_option( AF_OPTION_SETTINGS, array() ), self::default_settings() );
	}

	/**
	 * Дефолтные настройки под фид majorzap/baz-on.
	 */
	public static function default_settings() {
		return array(
			'feed_url'           => '',
			'delimiter'          => ';',
			'enclosure'          => '"',
			'encoding'           => 'Windows-1251',
			'has_header'         => 1,
			'batch_size'         => 150,
			'time_budget'        => 20,
			'create_missing'     => 1,
			'update_price'       => 1,
			'update_description' => 1,
			'update_name'        => 1,
			'disable_absent'     => 1,
			'build_fitment'      => 1,
			'import_images'      => 1,
			'category_source'    => 'name', // none|name|make
			'price_markup'       => 0,
			'map'                => array(
				'sku'         => 'Артикул',
				'name'        => 'Наименование',
				'donor'       => 'Донор',
				'make'        => 'Марка',
				'model'       => 'Модель',
				'year'        => 'Год',
				'generation'  => 'Кузов',
				'engine'      => 'Двигатель',
				'pos_fb'      => 'Перед/Зад (F/B)',
				'pos_lr'      => 'Лев/Прав (L/R)',
				'pos_ud'      => 'Верх/Низ (U/D)',
				'color'       => 'Цвет',
				'marking'     => 'Маркировка',
				'cross'       => 'Кросс-номера',
				'oem'         => 'Номер производителя',
				'brand'       => 'Производитель',
				'description' => 'Комментарий',
				'image'       => 'Фото',
				'condition'   => 'Новый/БУ (new/used/contract)',
				'price'       => 'Цена',
				'warehouse'   => 'Склад',
			),
		);
	}

	/* ==================== оркестрация прогона ==================== */

	public function run_sync() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			AF_Logger::log( 'WooCommerce не активен — синхронизация пропущена.', 'error' );
			return;
		}
		if ( empty( $this->settings['feed_url'] ) ) {
			AF_Logger::log( 'Не задана ссылка на CSV baz-on — синхронизация пропущена.', 'warning' );
			return;
		}
		if ( get_transient( self::LOCK_KEY ) ) {
			AF_Logger::log( 'Синхронизация уже выполняется (lock) — пропуск.', 'info' );
			return;
		}
		set_transient( self::LOCK_KEY, time(), self::LOCK_TTL );

		try {
			$state = get_option( self::STATE_KEY, array() );
			if ( empty( $state['active'] ) ) {
				$state = $this->start_new_run();
				if ( ! $state ) {
					delete_transient( self::LOCK_KEY );
					return;
				}
			}

			$done = $this->process_batch( $state );

			if ( $done ) {
				$this->finalize_run( $state );
				delete_option( self::STATE_KEY );
			} else {
				update_option( self::STATE_KEY, $state, false );
				wp_schedule_single_event( time() + 5, AF_CRON_HOOK );
			}
		} catch ( \Throwable $e ) {
			AF_Logger::log( 'Ошибка синхронизации: ' . $e->getMessage(), 'error' );
			AF_Logger::set_status( array( 'last_error' => $e->getMessage(), 'running' => false ) );
			delete_option( self::STATE_KEY );
		}

		delete_transient( self::LOCK_KEY );
	}

	protected function start_new_run() {
		$path = $this->download_feed();
		if ( ! $path ) {
			return false;
		}
		$encoding = $this->settings['encoding'];
		if ( 'auto' === $encoding ) {
			$encoding = $this->detect_encoding( $path );
		}

		$state = array(
			'active'    => true,
			'file'      => $path,
			'pos'       => 0,
			'header'    => null,
			'encoding'  => $encoding,
			'run_id'    => current_time( 'timestamp' ) . '-' . wp_generate_password( 6, false ),
			'processed' => 0,
			'created'   => 0,
			'updated'   => 0,
			'skipped'   => 0,
			'vehicles'  => 0,
			'started'   => current_time( 'mysql' ),
		);

		AF_Logger::log( "Старт синхронизации. Кодировка: {$encoding}. Файл: " . size_format( filesize( $path ) ), 'info' );
		AF_Logger::set_status( array( 'running' => true, 'started' => $state['started'], 'last_error' => '' ) );
		return $state;
	}

	protected function download_feed() {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'auto-fitment';
		wp_mkdir_p( $dir );
		$path = $dir . '/feed.csv';

		$response = wp_safe_remote_get( $this->settings['feed_url'], array(
			'timeout'  => 180,
			'stream'   => true,
			'filename' => $path,
		) );

		if ( is_wp_error( $response ) ) {
			AF_Logger::log( 'Ошибка загрузки фида: ' . $response->get_error_message(), 'error' );
			return false;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			AF_Logger::log( 'Фид вернул HTTP ' . wp_remote_retrieve_response_code( $response ), 'error' );
			return false;
		}
		if ( ! file_exists( $path ) || filesize( $path ) < 10 ) {
			AF_Logger::log( 'Скачанный фид пуст.', 'error' );
			return false;
		}
		AF_Logger::log( 'Фид загружен: ' . size_format( filesize( $path ) ), 'info' );
		return $path;
	}

	protected function process_batch( array &$state ) {
		$handle = fopen( $state['file'], 'r' );
		if ( ! $handle ) {
			throw new \RuntimeException( 'Не удалось открыть файл фида.' );
		}
		fseek( $handle, $state['pos'] );

		$delimiter = $this->settings['delimiter'];
		$enclosure = $this->settings['enclosure'];
		$batch     = (int) $this->settings['batch_size'];
		$deadline  = time() + (int) $this->settings['time_budget'];
		$count     = 0;

		if ( null === $state['header'] && $this->settings['has_header'] && 0 === $state['pos'] ) {
			$row             = fgetcsv( $handle, 0, $delimiter, $enclosure );
			$state['header'] = $this->build_header_map( $row, $state['encoding'] );
			$state['pos']    = ftell( $handle );
		}

		wp_defer_term_counting( true );
		wp_suspend_cache_invalidation( true );

		while ( ( $row = fgetcsv( $handle, 0, $delimiter, $enclosure ) ) !== false ) {
			if ( array( null ) === $row || ( 1 === count( $row ) && trim( (string) $row[0] ) === '' ) ) {
				$state['pos'] = ftell( $handle );
				continue;
			}

			$data   = $this->map_row( $row, $state );
			$result = $this->upsert_product( $data, $state );

			$state['processed']++;
			if ( 'created' === $result ) {
				$state['created']++;
			} elseif ( 'updated' === $result ) {
				$state['updated']++;
			} else {
				$state['skipped']++;
			}

			$state['pos'] = ftell( $handle );
			if ( ++$count >= $batch || time() >= $deadline ) {
				break;
			}
		}

		$eof = feof( $handle );
		fclose( $handle );

		wp_suspend_cache_invalidation( false );
		wp_defer_term_counting( false );

		AF_Logger::set_status( array(
			'running'   => ! $eof,
			'processed' => $state['processed'],
			'created'   => $state['created'],
			'updated'   => $state['updated'],
			'skipped'   => $state['skipped'],
			'vehicles'  => $state['vehicles'],
		) );

		return $eof;
	}

	protected function finalize_run( array $state ) {
		if ( ! empty( $this->settings['disable_absent'] ) ) {
			$this->disable_absent_products( $state['run_id'] );
		}
		$msg = sprintf(
			'Готово. Обработано: %d, создано: %d, обновлено: %d, пропущено: %d, авто в каталоге затронуто: %d.',
			$state['processed'], $state['created'], $state['updated'], $state['skipped'], $state['vehicles']
		);
		AF_Logger::log( $msg, 'info' );
		AF_Logger::set_status( array( 'running' => false, 'finished' => current_time( 'mysql' ), 'last_error' => '' ) );
	}

	protected function disable_absent_products( $run_id ) {
		global $wpdb;
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} m1 ON m1.post_id = p.ID AND m1.meta_key = '_af_bazon' AND m1.meta_value = '1'
			 LEFT JOIN {$wpdb->postmeta} m2 ON m2.post_id = p.ID AND m2.meta_key = '_af_run_id'
			 WHERE p.post_type = 'product' AND ( m2.meta_value IS NULL OR m2.meta_value != %s )",
			$run_id
		) );
		foreach ( $ids as $pid ) {
			$product = wc_get_product( $pid );
			if ( $product ) {
				$product->set_stock_status( 'outofstock' );
				$product->save();
			}
		}
		if ( $ids ) {
			AF_Logger::log( 'Снято с наличия (нет в фиде): ' . count( $ids ), 'info' );
		}
	}

	/* ==================== маппинг строки ==================== */

	protected function build_header_map( $header_row, $encoding ) {
		$positions = array();
		foreach ( (array) $header_row as $i => $v ) {
			$title = mb_strtolower( trim( $this->to_utf8( (string) $v, $encoding ) ) );
			$positions[ $title ] = $i;
		}
		return $positions;
	}

	protected function map_row( array $row, array $state ) {
		$out = array();
		foreach ( $this->settings['map'] as $field => $column ) {
			$value = '';
			if ( '' !== (string) $column ) {
				if ( is_array( $state['header'] ) ) {
					$idx = $state['header'][ mb_strtolower( trim( (string) $column ) ) ] ?? null;
					if ( null !== $idx && isset( $row[ $idx ] ) ) {
						$value = $row[ $idx ];
					}
				} elseif ( is_numeric( $column ) && isset( $row[ (int) $column ] ) ) {
					$value = $row[ (int) $column ];
				}
			}
			$out[ $field ] = trim( $this->to_utf8( (string) $value, $state['encoding'] ) );
		}
		return $out;
	}

	/* ==================== upsert товара ==================== */

	protected function upsert_product( array $d, array &$state ) {
		$sku = $d['sku'] ?? '';
		if ( '' === $sku ) {
			return 'skipped';
		}

		$product_id = wc_get_product_id_by_sku( $sku );
		$is_new     = false;

		if ( ! $product_id ) {
			if ( empty( $this->settings['create_missing'] ) ) {
				return 'skipped';
			}
			$product = new WC_Product_Simple();
			$product->set_sku( $sku );
			$product->set_status( 'publish' );
			$product->set_catalog_visibility( 'visible' );
			$is_new = true;
		} else {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				return 'skipped';
			}
		}

		if ( $is_new || ! empty( $this->settings['update_name'] ) ) {
			$product->set_name( $d['name'] !== '' ? $d['name'] : $sku );
		}
		if ( $is_new || ! empty( $this->settings['update_description'] ) ) {
			if ( '' !== $d['description'] ) {
				$product->set_description( wp_kses_post( wpautop( $d['description'] ) ) );
			}
			$short = $this->build_short_description( $d );
			if ( '' !== $short ) {
				$product->set_short_description( $short );
			}
		}

		// Цена.
		if ( $is_new || ! empty( $this->settings['update_price'] ) ) {
			$price = $this->parse_price( $d['price'] ?? '' );
			if ( $price > 0 ) {
				$markup = (float) $this->settings['price_markup'];
				if ( $markup > 0 ) {
					$price = round( $price * ( 1 + $markup / 100 ), 2 );
				}
				$product->set_regular_price( (string) $price );
			}
		}

		// Наличие: присутствие в фиде = в наличии (кол-во поставщик не даёт — «Склад» это город).
		$product->set_manage_stock( false );
		$product->set_stock_status( 'instock' );

		// Категория.
		$cat_id = $this->resolve_category( $d );
		if ( $cat_id ) {
			$product->set_category_ids( array( $cat_id ) );
		}

		$product->save();
		$pid = $product->get_id();

		// Метаданные каталога/поиска.
		update_post_meta( $pid, '_af_bazon', '1' );
		update_post_meta( $pid, '_af_run_id', $state['run_id'] );
		$this->save_meta( $pid, $d );

		// Фото.
		if ( ! empty( $this->settings['import_images'] ) && '' !== $d['image'] ) {
			$this->import_images( $product, $d['image'] );
		}

		// Каталог авто + фитмент.
		if ( ! empty( $this->settings['build_fitment'] ) && '' !== $d['make'] && '' !== $d['model'] ) {
			$vehicle_id = $this->get_or_create_vehicle( $d, $state );
			if ( $vehicle_id ) {
				AF_Fitment::link( $pid, $vehicle_id );
			}
		}

		return $is_new ? 'created' : 'updated';
	}

	/**
	 * Короткое описание — сводка характеристик (сторона/цвет/состояние/OEM).
	 */
	protected function build_short_description( array $d ) {
		$parts = array();
		$cond  = array( 'new' => 'Новая', 'used' => 'Б/У', 'contract' => 'Контрактная' );
		if ( ! empty( $d['condition'] ) && isset( $cond[ strtolower( $d['condition'] ) ] ) ) {
			$parts[] = 'Состояние: ' . $cond[ strtolower( $d['condition'] ) ];
		}
		$side = array_filter( array( $d['pos_fb'] ?? '', $d['pos_lr'] ?? '', $d['pos_ud'] ?? '' ) );
		if ( $side ) {
			$parts[] = 'Расположение: ' . implode( ' / ', $side );
		}
		if ( ! empty( $d['color'] ) ) {
			$parts[] = 'Цвет: ' . $d['color'];
		}
		if ( ! empty( $d['oem'] ) ) {
			$parts[] = 'OEM: ' . $d['oem'];
		}
		return $parts ? '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $parts ) ) . '</li></ul>' : '';
	}

	protected function save_meta( $pid, array $d ) {
		$meta = array(
			'_af_brand'     => $d['brand'] ?? '',
			'_af_condition' => $d['condition'] ?? '',
			'_af_oem'       => $d['oem'] ?? '',
			'_af_cross'     => $d['cross'] ?? '',
			'_af_color'     => $d['color'] ?? '',
			'_af_marking'   => $d['marking'] ?? '',
			'_af_engine'    => $d['engine'] ?? '',
			'_af_year'      => $d['year'] ?? '',
			'_af_warehouse' => $d['warehouse'] ?? '',
			'_af_donor'     => $d['donor'] ?? '',
			'_af_position'  => trim( implode( ' ', array_filter( array( $d['pos_fb'] ?? '', $d['pos_lr'] ?? '', $d['pos_ud'] ?? '' ) ) ) ),
		);
		foreach ( $meta as $k => $v ) {
			if ( '' !== $v ) {
				update_post_meta( $pid, $k, $v );
			}
		}
	}

	/**
	 * Категория товара по настройке (по названию детали или по марке).
	 */
	protected function resolve_category( array $d ) {
		$source = $this->settings['category_source'];
		if ( 'none' === $source ) {
			return 0;
		}
		$name = 'make' === $source ? $this->norm_case( $d['make'] ?? '' ) : ( $d['name'] ?? '' );
		if ( '' === $name ) {
			return 0;
		}
		return $this->get_or_create_term( $name, 'product_cat' );
	}

	/* ==================== каталог авто + фитмент ==================== */

	protected function get_or_create_vehicle( array $d, array &$state ) {
		$make = $this->norm_case( $d['make'] );
		$model = $this->norm_case( $d['model'] );
		$gen   = $d['generation'] ?? '';
		$eng   = $d['engine'] ?? '';

		$vkey = md5( mb_strtolower( $d['make'] . '|' . $d['model'] . '|' . $gen . '|' . $eng ) );
		if ( isset( $this->vehicle_cache[ $vkey ] ) ) {
			return $this->vehicle_cache[ $vkey ];
		}

		// Поиск существующего авто по ключу.
		$existing = get_posts( array(
			'post_type'      => 'vehicle',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_af_vkey',
			'meta_value'     => $vkey,
			'post_status'    => 'any',
		) );

		if ( $existing ) {
			$vehicle_id = (int) $existing[0];
		} else {
			$title = trim( "{$make} {$model} " . trim( "{$gen} {$eng}" ) );
			$vehicle_id = wp_insert_post( array(
				'post_type'   => 'vehicle',
				'post_status' => 'publish',
				'post_title'  => $title !== '' ? $title : "{$make} {$model}",
			) );
			if ( is_wp_error( $vehicle_id ) || ! $vehicle_id ) {
				return 0;
			}
			update_post_meta( $vehicle_id, '_af_vkey', $vkey );
			update_post_meta( $vehicle_id, '_af_engine', $eng );
			update_post_meta( $vehicle_id, '_af_year', $d['year'] ?? '' );

			// Таксономии марка/модель/поколение.
			$this->set_object_term( $vehicle_id, $make, 'car_make' );
			$this->set_object_term( $vehicle_id, $model, 'car_model' );
			if ( '' !== $gen ) {
				$this->set_object_term( $vehicle_id, $gen, 'car_generation' );
			}
			$state['vehicles']++;
		}

		$this->vehicle_cache[ $vkey ] = $vehicle_id;
		return $vehicle_id;
	}

	protected function set_object_term( $post_id, $term_name, $taxonomy ) {
		$term_id = $this->get_or_create_term( $term_name, $taxonomy );
		if ( $term_id ) {
			wp_set_object_terms( $post_id, array( $term_id ), $taxonomy, true );
		}
	}

	protected function get_or_create_term( $name, $taxonomy ) {
		$name = trim( $name );
		if ( '' === $name ) {
			return 0;
		}
		$term = get_term_by( 'name', $name, $taxonomy );
		if ( $term ) {
			return (int) $term->term_id;
		}
		$res = wp_insert_term( $name, $taxonomy );
		if ( is_wp_error( $res ) ) {
			// Возможна гонка/дубль — пробуем найти ещё раз.
			$term = get_term_by( 'name', $name, $taxonomy );
			return $term ? (int) $term->term_id : 0;
		}
		return (int) $res['term_id'];
	}

	/* ==================== фото ==================== */

	protected function import_images( $product, $raw_urls ) {
		$pid = $product->get_id();
		// Пропускаем, если набор фото не изменился с прошлой синхронизации.
		$hash = md5( $raw_urls );
		if ( get_post_meta( $pid, '_af_photos_hash', true ) === $hash && $product->get_image_id() ) {
			return;
		}

		$urls = array_filter( array_map( 'trim', preg_split( '/[,\s]+/', $raw_urls ) ) );
		$urls = array_slice( $urls, 0, 8 ); // разумный лимит
		if ( ! $urls ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_ids = array();
		foreach ( $urls as $url ) {
			if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
				continue;
			}
			$existing = $this->find_attachment_by_source( $url );
			if ( $existing ) {
				$attachment_ids[] = $existing;
				continue;
			}
			$att_id = media_sideload_image( $url, $pid, null, 'id' );
			if ( ! is_wp_error( $att_id ) ) {
				update_post_meta( $att_id, '_af_source_url', esc_url_raw( $url ) );
				$attachment_ids[] = $att_id;
			}
		}

		if ( $attachment_ids ) {
			$product->set_image_id( array_shift( $attachment_ids ) );
			if ( $attachment_ids ) {
				$product->set_gallery_image_ids( $attachment_ids );
			}
			$product->save();
			update_post_meta( $pid, '_af_photos_hash', $hash );
		}
	}

	protected function find_attachment_by_source( $url ) {
		global $wpdb;
		$id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_af_source_url' AND meta_value = %s LIMIT 1",
			esc_url_raw( $url )
		) );
		return $id ? (int) $id : 0;
	}

	/* ==================== утилиты ==================== */

	protected function parse_price( $raw ) {
		$raw = preg_replace( '/[^\d,\.]/u', '', (string) $raw );
		$raw = str_replace( ',', '.', $raw );
		if ( substr_count( $raw, '.' ) > 1 ) {
			$parts = explode( '.', $raw );
			$dec   = array_pop( $parts );
			$raw   = implode( '', $parts ) . '.' . $dec;
		}
		return (float) $raw;
	}

	/**
	 * Нормализация регистра марки/модели: «haval» -> «Haval», «h6» -> «H6».
	 */
	protected function norm_case( $value ) {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		return mb_convert_case( $value, MB_CASE_TITLE, 'UTF-8' );
	}

	protected function detect_encoding( $path ) {
		$sample = file_get_contents( $path, false, null, 0, 8192 );
		if ( false === $sample ) {
			return 'UTF-8';
		}
		$enc = mb_detect_encoding( $sample, array( 'UTF-8', 'Windows-1251', 'CP866' ), true );
		return $enc ? $enc : 'Windows-1251';
	}

	protected function to_utf8( $value, $encoding ) {
		if ( '' === $value || 'UTF-8' === $encoding ) {
			return $value;
		}
		return mb_convert_encoding( $value, 'UTF-8', $encoding );
	}

	public function force_full_resync() {
		delete_option( self::STATE_KEY );
		delete_transient( self::LOCK_KEY );
		wp_schedule_single_event( time() + 1, AF_CRON_HOOK );
		AF_Logger::log( 'Запрошена ручная синхронизация.', 'info' );
	}
}
