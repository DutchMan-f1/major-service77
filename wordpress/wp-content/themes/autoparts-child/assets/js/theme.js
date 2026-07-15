/* Переключатель режима поиска «По артикулу / По VIN». */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.js-seg' );
		if ( ! btn ) { return; }
		e.preventDefault();

		var form = btn.closest( 'form' );
		if ( ! form ) { return; }

		form.querySelectorAll( '.js-seg' ).forEach( function ( b ) {
			b.classList.toggle( 'is-active', b === btn );
		} );

		var mode = form.querySelector( '.js-mode' );
		if ( mode ) { mode.value = btn.getAttribute( 'data-mode' ) || 'vin'; }
	} );

	/* Галерея товара: переключение миниатюр. */
	document.addEventListener( 'click', function ( e ) {
		var thumb = e.target.closest( '.product-thumb' );
		if ( ! thumb ) { return; }
		var main = document.getElementById( 'product-main-image' );
		var full = thumb.getAttribute( 'data-full' );
		if ( main && full ) { main.src = full; }
		thumb.closest( '.product-thumbs' ).querySelectorAll( '.product-thumb' ).forEach( function ( t ) {
			t.classList.toggle( 'is-active', t === thumb );
		} );
	} );

	/* Табы на странице товара. */
	document.addEventListener( 'click', function ( e ) {
		var tab = e.target.closest( '.tab-btn' );
		if ( ! tab ) { return; }
		var root = tab.closest( '[data-tabs]' );
		var name = tab.getAttribute( 'data-tab' );
		root.querySelectorAll( '.tab-btn' ).forEach( function ( b ) {
			b.classList.toggle( 'is-active', b === tab );
		} );
		root.querySelectorAll( '.tab-pane' ).forEach( function ( p ) {
			p.hidden = p.getAttribute( 'data-pane' ) !== name;
		} );
	} );

	/* Степпер количества в корзине: меняет значение и отправляет форму (обновление корзины). */
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.qty-btn' );
		if ( ! btn ) { return; }
		var stepper = btn.closest( '.qty-stepper' );
		var input = stepper.querySelector( '.qty-input' );
		var val = parseInt( input.value, 10 ) || 1;
		if ( btn.classList.contains( 'qty-plus' ) ) { val += 1; }
		else { val = Math.max( 1, val - 1 ); }
		input.value = val;

		var form = btn.closest( 'form.cart-form' );
		if ( form ) {
			var upd = form.querySelector( '[name="update_cart"]' );
			if ( upd ) { upd.disabled = false; upd.click(); }
			else { form.submit(); }
		}
	} );
	/* Стрелки прокрутки миниатюр товара (вертикально, без видимого скролла). */
	document.querySelectorAll( '[data-thumbs]' ).forEach( function ( wrap ) {
		var box = wrap.querySelector( '.product-thumbs' );
		var up = wrap.querySelector( '.thumbs-arrow--up' );
		var down = wrap.querySelector( '.thumbs-arrow--down' );
		if ( ! box || ! up || ! down ) { return; }
		var step = 96;
		function update() {
			var scrollable = box.scrollHeight > box.clientHeight + 1;
			up.hidden = ! scrollable;
			down.hidden = ! scrollable;
			if ( ! scrollable ) { return; }
			up.disabled = box.scrollTop <= 0;
			down.disabled = box.scrollTop + box.clientHeight >= box.scrollHeight - 1;
		}
		up.addEventListener( 'click', function () { box.scrollBy( { top: -step, behavior: 'smooth' } ); } );
		down.addEventListener( 'click', function () { box.scrollBy( { top: step, behavior: 'smooth' } ); } );
		box.addEventListener( 'scroll', update );
		window.addEventListener( 'resize', update );
		update();
	} );
} )();
