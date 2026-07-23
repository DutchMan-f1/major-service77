<?php
/**
 * Фронтенд: модалки входа/регистрации (AJAX) и каскадный подбор по авто.
 *
 * Шорткоды:
 *   [af_vehicle_selector]  — селектор марка → модель → модификация
 *   [af_auth_buttons]      — кнопки «Войти / Регистрация» (открывают модалку)
 *
 * @package AutoFitment
 */

defined( 'ABSPATH' ) || exit;

class AF_Frontend {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );

		add_shortcode( 'af_vehicle_selector', array( __CLASS__, 'sc_vehicle_selector' ) );
		add_shortcode( 'af_auth_buttons', array( __CLASS__, 'sc_auth_buttons' ) );

		// AJAX: подбор авто.
		add_action( 'wp_ajax_af_get_models', array( __CLASS__, 'ajax_get_models' ) );
		add_action( 'wp_ajax_nopriv_af_get_models', array( __CLASS__, 'ajax_get_models' ) );
		add_action( 'wp_ajax_af_get_vehicles', array( __CLASS__, 'ajax_get_vehicles' ) );
		add_action( 'wp_ajax_nopriv_af_get_vehicles', array( __CLASS__, 'ajax_get_vehicles' ) );

		// AJAX: авторизация.
		add_action( 'wp_ajax_nopriv_af_login', array( __CLASS__, 'ajax_login' ) );
		add_action( 'wp_ajax_nopriv_af_register', array( __CLASS__, 'ajax_register' ) );
		add_action( 'wp_ajax_nopriv_af_recover', array( __CLASS__, 'ajax_recover' ) );

		// Разметка модалки в подвале.
		add_action( 'wp_footer', array( __CLASS__, 'auth_modal_markup' ) );
	}

	public static function assets() {
		wp_enqueue_style( 'af-frontend', AF_PLUGIN_URL . 'assets/css/frontend.css', array(), AF_VERSION );
		wp_enqueue_script( 'af-frontend', AF_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), AF_VERSION, true );
		wp_localize_script( 'af-frontend', 'AF', array(
			'ajaxurl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'af_front' ),
			'shop_url' => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' ),
			'cart_url' => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ),
			'i18n'     => array(
				'choose' => '— выберите —',
				'error'  => 'Произошла ошибка. Попробуйте ещё раз.',
			),
		) );
	}

	/* ---------------- Шорткоды ---------------- */

	public static function sc_vehicle_selector( $atts ) {
		$makes = get_terms( array( 'taxonomy' => 'car_make', 'hide_empty' => true ) );
		ob_start(); ?>
		<div class="af-selector" data-af-selector>
			<select class="af-make">
				<option value="">Марка</option>
				<?php foreach ( (array) $makes as $m ) : ?>
					<option value="<?php echo (int) $m->term_id; ?>"><?php echo esc_html( $m->name ); ?></option>
				<?php endforeach; ?>
			</select>
			<select class="af-model" disabled><option value="">Модель</option></select>
			<select class="af-vehicle" disabled><option value="">Модификация</option></select>
			<button type="button" class="af-apply button" disabled>Подобрать запчасти</button>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function sc_auth_buttons( $atts ) {
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			$acc  = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'dashboard' ) : home_url( '/my-account/' );
			return '<span class="af-user">Привет, ' . esc_html( $user->display_name ) . '</span> '
				. '<a class="af-account" href="' . esc_url( $acc ) . '">Кабинет</a> '
				. '<a class="af-logout" href="' . esc_url( wp_logout_url( home_url() ) ) . '">Выйти</a>';
		}
		return '<button type="button" class="af-open-auth button" data-tab="login">Войти</button> '
			. '<button type="button" class="af-open-auth button" data-tab="register">Регистрация</button>';
	}

	/* ---------------- AJAX: подбор авто ---------------- */

	/**
	 * Модели, у которых есть авто указанной марки.
	 */
	public static function ajax_get_models() {
		check_ajax_referer( 'af_front', 'nonce' );
		$make_id = absint( $_POST['make'] ?? 0 );
		if ( ! $make_id ) {
			wp_send_json_error();
		}

		$vehicle_ids = get_posts( array(
			'post_type'      => 'vehicle',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => array( array( 'taxonomy' => 'car_make', 'field' => 'term_id', 'terms' => $make_id ) ),
		) );

		$models = self::collect_terms( $vehicle_ids, 'car_model' );
		wp_send_json_success( $models );
	}

	/**
	 * Конкретные модификации (записи vehicle) для марки + модели.
	 */
	public static function ajax_get_vehicles() {
		check_ajax_referer( 'af_front', 'nonce' );
		$make_id  = absint( $_POST['make'] ?? 0 );
		$model_id = absint( $_POST['model'] ?? 0 );
		if ( ! $make_id || ! $model_id ) {
			wp_send_json_error();
		}

		$posts = get_posts( array(
			'post_type'      => 'vehicle',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'tax_query'      => array(
				'relation' => 'AND',
				array( 'taxonomy' => 'car_make', 'field' => 'term_id', 'terms' => $make_id ),
				array( 'taxonomy' => 'car_model', 'field' => 'term_id', 'terms' => $model_id ),
			),
		) );

		$out = array();
		foreach ( $posts as $p ) {
			$out[] = array( 'id' => $p->ID, 'name' => get_the_title( $p ) );
		}
		wp_send_json_success( $out );
	}

	/**
	 * Собрать уникальные термы таксономии по набору записей.
	 */
	protected static function collect_terms( $post_ids, $taxonomy ) {
		if ( empty( $post_ids ) ) {
			return array();
		}
		$terms = wp_get_object_terms( $post_ids, $taxonomy );
		$out   = array();
		foreach ( (array) $terms as $t ) {
			$out[ $t->term_id ] = array( 'id' => $t->term_id, 'name' => $t->name );
		}
		$out = array_values( $out );
		usort( $out, function ( $a, $b ) { return strcmp( $a['name'], $b['name'] ); } );
		return $out;
	}

	/* ---------------- AJAX: авторизация ---------------- */

	/**
	 * Лимит попыток по IP: защита от перебора пароля и массовых регистраций.
	 */
	protected static function throttle( $bucket, $max = 15 ) {
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '';
		$key = 'af_rl_' . $bucket . '_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= $max ) {
			wp_send_json_error( array( 'message' => 'Слишком много попыток. Попробуйте позже.' ) );
		}
		set_transient( $key, $n + 1, HOUR_IN_SECONDS );
	}

	public static function ajax_login() {
		check_ajax_referer( 'af_front', 'nonce' );
		self::throttle( 'login', 15 );

		$creds = array(
			'user_login'    => sanitize_text_field( wp_unslash( $_POST['login'] ?? '' ) ),
			'user_password' => (string) ( $_POST['password'] ?? '' ),
			'remember'      => ! empty( $_POST['remember'] ),
		);

		if ( '' === $creds['user_login'] || '' === $creds['user_password'] ) {
			wp_send_json_error( array( 'message' => 'Введите логин и пароль.' ) );
		}

		$user = wp_signon( $creds, is_ssl() );
		if ( is_wp_error( $user ) ) {
			wp_send_json_error( array( 'message' => 'Неверный логин или пароль.' ) );
		}

		wp_send_json_success( array( 'message' => 'Вход выполнен.', 'redirect' => wc_get_page_permalink( 'myaccount' ) ) );
	}

	public static function ajax_register() {
		check_ajax_referer( 'af_front', 'nonce' );
		self::throttle( 'register', 10 );

		$p    = wp_unslash( $_POST );
		$type = ( isset( $p['account_type'] ) && 'ur' === $p['account_type'] ) ? 'ur' : 'fiz';

		$field = function ( $key ) use ( $p ) {
			return isset( $p[ $key ] ) ? sanitize_text_field( $p[ $key ] ) : '';
		};

		$email  = sanitize_email( $p['email'] ?? '' );
		$pass   = (string) ( $_POST['password'] ?? '' );
		$pass2  = (string) ( $_POST['password2'] ?? '' );
		$first  = $field( 'first_name' );
		$last   = $field( 'last_name' );
		$middle = $field( 'middle_name' );
		$phone  = $field( 'phone' );

		// Валидация.
		if ( empty( $p['agree'] ) ) {
			wp_send_json_error( array( 'message' => 'Необходимо принять условия и политику конфиденциальности.' ) );
		}
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => 'Укажите корректный e-mail.' ) );
		}
		if ( email_exists( $email ) ) {
			wp_send_json_error( array( 'message' => 'Пользователь с таким e-mail уже зарегистрирован.' ) );
		}
		if ( strlen( $pass ) < 6 ) {
			wp_send_json_error( array( 'message' => 'Пароль должен быть не короче 6 символов.' ) );
		}
		if ( $pass !== $pass2 ) {
			wp_send_json_error( array( 'message' => 'Пароли не совпадают.' ) );
		}

		$company = $inn = $kpp = '';
		if ( 'ur' === $type ) {
			$company = $field( 'company' );
			$inn     = $field( 'inn' );
			$kpp     = $field( 'kpp' );
			if ( '' === $company ) {
				wp_send_json_error( array( 'message' => 'Укажите наименование организации.' ) );
			}
			if ( ! preg_match( '/^\d{10}(\d{2})?$/', $inn ) ) {
				wp_send_json_error( array( 'message' => 'ИНН должен содержать 10 (или 12) цифр.' ) );
			}
		}

		// Создание покупателя.
		if ( function_exists( 'wc_create_new_customer' ) ) {
			$user_id = wc_create_new_customer( $email, '', $pass, array(
				'first_name' => $first,
				'last_name'  => $last,
			) );
		} else {
			$user_id = wp_create_user( $email, $pass, $email );
		}
		if ( is_wp_error( $user_id ) ) {
			wp_send_json_error( array( 'message' => $user_id->get_error_message() ) );
		}

		// Профиль и адрес доставки.
		if ( class_exists( 'WC_Customer' ) ) {
			$customer = new WC_Customer( $user_id );
			$customer->set_first_name( $first );
			$customer->set_last_name( $last );
			$customer->set_billing_first_name( $first );
			$customer->set_billing_last_name( $last );
			$customer->set_billing_email( $email );
			$customer->set_billing_phone( $phone );
			$customer->set_billing_postcode( $field( 'postcode' ) );
			$customer->set_billing_city( $field( 'city' ) );
			$customer->set_billing_address_1( $field( 'address' ) );
			if ( 'ur' === $type ) {
				$customer->set_billing_company( $company );
			}
			$customer->save();
		}

		// Доп. поля.
		update_user_meta( $user_id, '_af_account_type', $type );
		if ( '' !== $middle ) {
			update_user_meta( $user_id, '_af_middle_name', $middle );
		}
		if ( 'ur' === $type ) {
			update_user_meta( $user_id, '_af_company', $company );
			update_user_meta( $user_id, '_af_inn', $inn );
			update_user_meta( $user_id, '_af_kpp', $kpp );
		}

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );

		wp_send_json_success( array(
			'message'  => 'Регистрация успешна.',
			'redirect' => wc_get_page_permalink( 'myaccount' ),
		) );
	}

	/**
	 * AJAX: восстановление пароля — отправка ссылки на сброс.
	 */
	public static function ajax_recover() {
		check_ajax_referer( 'af_front', 'nonce' );

		$login = sanitize_text_field( wp_unslash( $_POST['login'] ?? '' ) );
		if ( '' === $login ) {
			wp_send_json_error( array( 'message' => 'Введите e-mail.' ) );
		}

		$result = retrieve_password( $login );
		if ( is_wp_error( $result ) ) {
			// Не раскрываем, существует ли аккаунт.
			wp_send_json_success( array( 'message' => 'Если такой аккаунт существует, письмо со ссылкой отправлено.' ) );
		}

		wp_send_json_success( array( 'message' => 'Письмо со ссылкой для сброса пароля отправлено на почту.' ) );
	}

	/* ---------------- Разметка модалки ---------------- */

	public static function auth_modal_markup() {
		if ( is_user_logged_in() ) {
			return;
		}
		$policy = get_privacy_policy_url() ? get_privacy_policy_url() : '#';
		?>
		<div class="af-modal-overlay" id="af-auth-modal" hidden>
			<div class="af-modal is-login" role="dialog" aria-modal="true" aria-label="Авторизация">
				<button type="button" class="af-modal-close" aria-label="Закрыть">
					<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
				</button>

				<!-- ЭКРАН: ВХОД -->
				<div class="af-screen" data-screen="login">
					<div class="af-modal-head">
						<span class="af-head-ic"><?php echo self::icon_login(); ?></span>
						<h3 class="af-modal-title">Вход</h3>
					</div>
					<form class="af-form" data-form="login" novalidate>
						<div class="af-msg" hidden></div>
						<input class="af-input" type="text" name="login" placeholder="Email" autocomplete="username" required>
						<input class="af-input" type="password" name="password" placeholder="Пароль" autocomplete="current-password" required>
						<div class="af-btn-row">
							<button type="submit" class="af-btn af-btn--primary">Войти</button>
							<button type="button" class="af-btn af-btn--ghost" data-goto="register">Регистрация</button>
						</div>
						<button type="button" class="af-link" data-goto="recover">Восстановить пароль</button>
					</form>
				</div>

				<!-- ЭКРАН: РЕГИСТРАЦИЯ -->
				<div class="af-screen" data-screen="register" hidden>
					<div class="af-modal-head af-modal-head--reg">
						<div class="af-head-l">
							<span class="af-head-ic"><?php echo self::icon_register(); ?></span>
							<h3 class="af-modal-title">Регистрация</h3>
						</div>
						<div class="af-type" role="group" aria-label="Тип аккаунта">
							<button type="button" class="af-type__btn is-active" data-type="fiz">Физлицо</button>
							<button type="button" class="af-type__btn" data-type="ur">Юрлицо</button>
						</div>
					</div>

					<form class="af-form" data-form="register" novalidate>
						<div class="af-msg" hidden></div>
						<input type="hidden" name="account_type" value="fiz" class="af-account-type">

						<!-- Физлицо: контактные данные -->
						<div class="af-part af-part--fiz">
							<h4 class="af-section-title">Ваши контактные данные</h4>
							<div class="af-grid">
								<input class="af-input" type="text" name="last_name" placeholder="Фамилия">
								<input class="af-input" type="text" name="first_name" placeholder="Имя">
								<input class="af-input" type="text" name="middle_name" placeholder="Отчество">
								<input class="af-input" type="email" name="email" placeholder="Email">
								<input class="af-input" type="tel" name="phone" placeholder="+7 (___) ___-__-__">
							</div>
						</div>

						<!-- Юрлицо: данные организации + контактное лицо -->
						<div class="af-part af-part--ur" hidden>
							<h4 class="af-section-title">Данные организации</h4>
							<div class="af-grid">
								<input class="af-input full" type="text" name="company" placeholder="Наименование организации" disabled>
								<input class="af-input" type="text" name="inn" placeholder="ИНН" inputmode="numeric" disabled>
								<input class="af-input" type="text" name="kpp" placeholder="КПП" inputmode="numeric" disabled>
							</div>
							<h4 class="af-section-title">Контактное лицо</h4>
							<div class="af-grid">
								<input class="af-input" type="text" name="last_name" placeholder="Фамилия" disabled>
								<input class="af-input" type="text" name="first_name" placeholder="Имя" disabled>
								<input class="af-input" type="email" name="email" placeholder="Email" disabled>
								<input class="af-input" type="tel" name="phone" placeholder="+7 (___) ___-__-__" disabled>
							</div>
						</div>

						<!-- Адрес доставки (общий) -->
						<h4 class="af-section-title">Ваш адрес для доставки</h4>
						<div class="af-grid">
							<input class="af-input" type="text" name="postcode" placeholder="Индекс" inputmode="numeric">
							<input class="af-input" type="text" name="city" placeholder="Город">
							<input class="af-input full" type="text" name="address" placeholder="Адрес">
						</div>

						<!-- Логин и пароль (общий) -->
						<h4 class="af-section-title">Логин и пароль</h4>
						<div class="af-grid">
							<input class="af-input" type="password" name="password" placeholder="Пароль" autocomplete="new-password">
							<input class="af-input" type="password" name="password2" placeholder="Повторите пароль" autocomplete="new-password">
						</div>
						<div class="af-pass-hint" hidden></div>

						<label class="af-agree">
							<input type="checkbox" name="agree" value="1">
							<span>Я соглашаюсь с тем, что прочитал и принимаю <a href="<?php echo esc_url( $policy ); ?>" target="_blank" rel="noopener">Политику конфиденциальности</a>, а также даю согласие на использование предоставленной информации компанией «Мейджор Сервис» для связи со мной.</span>
						</label>

						<div class="af-submit-row">
							<button type="submit" class="af-btn af-btn--primary af-btn--wide">Зарегистрироваться</button>
							<span class="af-alt">Уже зарегистрированы? <a href="#" data-goto="login">Войти</a></span>
						</div>
					</form>
				</div>

				<!-- ЭКРАН: ВОССТАНОВЛЕНИЕ -->
				<div class="af-screen" data-screen="recover" hidden>
					<div class="af-modal-head">
						<span class="af-head-ic"><?php echo self::icon_login(); ?></span>
						<h3 class="af-modal-title">Восстановление пароля</h3>
					</div>
					<form class="af-form" data-form="recover" novalidate>
						<div class="af-msg" hidden></div>
						<input class="af-input" type="text" name="login" placeholder="Email" autocomplete="username" required>
						<div class="af-btn-row">
							<button type="submit" class="af-btn af-btn--primary">Отправить ссылку</button>
							<button type="button" class="af-btn af-btn--ghost" data-goto="login">Назад</button>
						</div>
					</form>
				</div>

			</div>
		</div>
		<?php
	}

	protected static function icon_login() {
		return '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/></svg>';
	}

	protected static function icon_register() {
		return '<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>';
	}
}
