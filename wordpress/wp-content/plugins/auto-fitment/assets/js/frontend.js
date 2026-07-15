/* global AF, jQuery */
( function ( $ ) {
	'use strict';

	/* ================= Каскадный подбор по авто ================= */

	function fill( $select, items, placeholder ) {
		$select.prop( 'disabled', false ).empty();
		$select.append( $( '<option>' ).val( '' ).text( placeholder ) );
		items.forEach( function ( it ) {
			$select.append( $( '<option>' ).val( it.id ).text( it.name ) );
		} );
	}
	function reset( $select, placeholder ) {
		$select.prop( 'disabled', true ).empty().append( $( '<option>' ).val( '' ).text( placeholder ) );
	}

	$( document ).on( 'change', '[data-af-selector] .af-make', function () {
		var $root = $( this ).closest( '[data-af-selector]' ), make = $( this ).val();
		reset( $root.find( '.af-model' ), 'Модель' );
		reset( $root.find( '.af-vehicle' ), 'Модификация' );
		$root.find( '.af-apply' ).prop( 'disabled', true );
		if ( ! make ) { return; }
		$.post( AF.ajaxurl, { action: 'af_get_models', nonce: AF.nonce, make: make } ).done( function ( r ) {
			if ( r.success ) { fill( $root.find( '.af-model' ), r.data, 'Модель' ); }
		} );
	} );

	$( document ).on( 'change', '[data-af-selector] .af-model', function () {
		var $root = $( this ).closest( '[data-af-selector]' ), make = $root.find( '.af-make' ).val(), model = $( this ).val();
		reset( $root.find( '.af-vehicle' ), 'Модификация' );
		$root.find( '.af-apply' ).prop( 'disabled', true );
		if ( ! model ) { return; }
		$.post( AF.ajaxurl, { action: 'af_get_vehicles', nonce: AF.nonce, make: make, model: model } ).done( function ( r ) {
			if ( r.success ) { fill( $root.find( '.af-vehicle' ), r.data, 'Модификация' ); }
		} );
	} );

	$( document ).on( 'change', '[data-af-selector] .af-vehicle', function () {
		$( this ).closest( '[data-af-selector]' ).find( '.af-apply' ).prop( 'disabled', ! $( this ).val() );
	} );

	$( document ).on( 'click', '[data-af-selector] .af-apply', function () {
		var $root = $( this ).closest( '[data-af-selector]' ), vid = $root.find( '.af-vehicle' ).val();
		if ( ! vid ) { return; }
		document.cookie = 'af_vehicle=' + vid + ';path=/;max-age=' + ( 60 * 60 * 24 * 365 );
		window.location.href = AF.shop_url + ( AF.shop_url.indexOf( '?' ) > -1 ? '&' : '?' ) + 'vehicle=' + vid;
	} );

	/* ================= Модалка авторизации ================= */

	var $modal = null, $box = null;

	function widthClass( screen ) {
		return screen === 'register' ? 'is-register' : ( screen === 'recover' ? 'is-recover' : 'is-login' );
	}

	function goScreen( screen ) {
		$modal = $( '#af-auth-modal' );
		$box = $modal.find( '.af-modal' );
		$modal.find( '.af-screen' ).prop( 'hidden', true );
		$modal.find( '.af-screen[data-screen="' + screen + '"]' ).prop( 'hidden', false );
		$box.removeClass( 'is-login is-register is-recover' ).addClass( widthClass( screen ) );
		$modal.find( '.af-msg' ).prop( 'hidden', true ).removeClass( 'is-error is-ok' ).text( '' );
	}

	function openModal( screen ) {
		$modal = $( '#af-auth-modal' );
		if ( ! $modal.length ) { return; }
		$modal.prop( 'hidden', false );
		document.body.style.overflow = 'hidden';
		goScreen( screen || 'login' );
	}
	function closeModal() {
		if ( $modal ) { $modal.prop( 'hidden', true ); }
		document.body.style.overflow = '';
	}

	// Куда вернуть после входа/регистрации (напр. в корзину при оформлении гостем).
	var authRedirect = null;
	$( document ).on( 'click', '.af-open-auth', function ( e ) {
		e.preventDefault();
		authRedirect = $( this ).data( 'redirect' ) || null;
		openModal( $( this ).data( 'tab' ) );
	} );
	$( document ).on( 'click', '.af-modal-close', closeModal );
	$( document ).on( 'click', '.af-modal-overlay', function ( e ) {
		if ( e.target === this ) { closeModal(); }
	} );
	$( document ).on( 'keydown', function ( e ) {
		if ( e.key === 'Escape' && $modal && ! $modal.prop( 'hidden' ) ) { closeModal(); }
	} );

	// Переход между экранами.
	$( document ).on( 'click', '[data-goto]', function ( e ) {
		e.preventDefault();
		goScreen( $( this ).data( 'goto' ) );
	} );

	// Переключатель Физлицо / Юрлицо.
	$( document ).on( 'click', '.af-type__btn', function () {
		var type = $( this ).data( 'type' ), $form = $( this ).closest( '.af-screen' ).find( 'form' );
		$( this ).closest( '.af-type' ).find( '.af-type__btn' ).removeClass( 'is-active' );
		$( this ).addClass( 'is-active' );
		$form.find( '.af-account-type' ).val( type );

		var $fiz = $form.find( '.af-part--fiz' ), $ur = $form.find( '.af-part--ur' );
		if ( type === 'ur' ) {
			$fiz.prop( 'hidden', true ).find( 'input' ).prop( 'disabled', true );
			$ur.prop( 'hidden', false ).find( 'input' ).prop( 'disabled', false );
		} else {
			$ur.prop( 'hidden', true ).find( 'input' ).prop( 'disabled', true );
			$fiz.prop( 'hidden', false ).find( 'input' ).prop( 'disabled', false );
		}
	} );

	// Отправка форм (login / register / recover).
	$( document ).on( 'submit', '.af-form', function ( e ) {
		e.preventDefault();
		var $form = $( this ), type = $form.data( 'form' );
		var $msg = $form.find( '.af-msg' ).prop( 'hidden', true ).removeClass( 'is-error is-ok' );
		var $btn = $form.find( 'button[type="submit"]' ).prop( 'disabled', true );

		var data = {};
		$form.serializeArray().forEach( function ( f ) { data[ f.name ] = f.value; } );
		data.action = 'af_' + type;
		data.nonce = AF.nonce;

		$.post( AF.ajaxurl, data ).done( function ( r ) {
			if ( r.success ) {
				if ( type === 'recover' ) {
					$msg.text( ( r.data && r.data.message ) || 'Готово.' ).prop( 'hidden', false ).addClass( 'is-ok' );
				} else {
					window.location.href = authRedirect || ( r.data && r.data.redirect ) || window.location.href;
				}
			} else {
				$msg.text( ( r.data && r.data.message ) || AF.i18n.error ).prop( 'hidden', false ).addClass( 'is-error' );
			}
		} ).fail( function () {
			$msg.text( AF.i18n.error ).prop( 'hidden', false ).addClass( 'is-error' );
		} ).always( function () {
			$btn.prop( 'disabled', false );
		} );
	} );

	/* ================= Анимация и состояние «в корзину» ================= */

	function checkSvg( size ) {
		return '<svg viewBox="0 0 24 24" width="' + size + '" height="' + size + '" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
	}

	// Товар вылетает ИЗ КНОПКИ и летит в корзину.
	function flyFromButton( btnEl, imgEl ) {
		var cart = document.querySelector( '.btn--cart' );
		if ( ! cart || ! btnEl ) { return; }
		var b = btnEl.getBoundingClientRect();
		var t = cart.getBoundingClientRect();
		var size = 54;
		var fly = document.createElement( imgEl ? 'img' : 'div' );
		if ( imgEl ) { fly.src = imgEl.currentSrc || imgEl.src; }
		fly.className = 'af-fly';
		fly.style.left = ( b.left + b.width / 2 - size / 2 ) + 'px';
		fly.style.top = ( b.top + b.height / 2 - size / 2 ) + 'px';
		fly.style.width = size + 'px';
		fly.style.height = size + 'px';
		document.body.appendChild( fly );
		fly.getBoundingClientRect(); // reflow
		fly.style.left = ( t.left + t.width / 2 - 12 ) + 'px';
		fly.style.top = ( t.top + t.height / 2 - 12 ) + 'px';
		fly.style.width = '24px';
		fly.style.height = '24px';
		fly.style.opacity = '0.12';
		fly.style.transform = 'rotate(16deg)';
		setTimeout( function () {
			if ( fly.parentNode ) { fly.parentNode.removeChild( fly ); }
		}, 760 );
	}

	// Кнопка после добавления: меняет текст/цвет, больше не добавляет (ведёт в корзину).
	function markAdded( btn ) {
		if ( ! btn || btn.classList.contains( 'is-added' ) ) { return; }
		btn.classList.add( 'is-added' );
		btn.classList.remove( 'ajax_add_to_cart', 'add_to_cart_button' );
		if ( AF.cart_url ) { btn.setAttribute( 'href', AF.cart_url ); }
		if ( btn.classList.contains( 'btn-buy' ) ) {
			btn.innerHTML = checkSvg( 18 ) + '<span>В корзине</span>';
		} else if ( btn.classList.contains( 'prod-cart' ) ) {
			btn.innerHTML = checkSvg( 18 );
		}
	}

	$( document ).on( 'click', '.add_to_cart_button', function () {
		if ( this.classList.contains( 'is-added' ) ) { return; }
		var $btn = $( this ), img = null;
		if ( $btn.hasClass( 'btn-buy' ) ) { img = document.getElementById( 'product-main-image' ); }
		if ( ! img ) { img = $btn.closest( '.prod-card' ).find( '.prod-card__img img' ).get( 0 ); }
		if ( ! img ) { img = document.querySelector( '#product-main-image, .prod-card__img img' ); }
		flyFromButton( this, img );
	} );

	$( document.body ).on( 'added_to_cart', function ( e, fragments, cart_hash, $button ) {
		var cart = document.querySelector( '.btn--cart' );
		if ( cart ) {
			cart.classList.remove( 'af-cart-pulse' );
			void cart.offsetWidth;
			cart.classList.add( 'af-cart-pulse' );
		}
		if ( $button && $button.length ) { markAdded( $button.get( 0 ) ); }
	} );

} )( jQuery );
