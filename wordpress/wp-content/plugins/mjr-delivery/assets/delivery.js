/* Выбор способа доставки: аккордеон + карта пунктов + режим «пункт/адрес». */
(function () {
	'use strict';

	var cfg = window.MJRDelivery || {};
	var section = document.querySelector('.mjr-delivery');
	if (!section) { return; }

	var modeInput  = document.getElementById('mjr_delivery_mode');
	var pointInput = document.getElementById('mjr_delivery_point');
	var pointAddr  = document.getElementById('mjr_delivery_point_addr');
	var radios     = Array.prototype.slice.call(section.querySelectorAll('input[name="mjr_delivery_carrier"]'));

	var ymState = { loading: false, ready: false };
	var loaded  = {};   // carrier -> true, если пункты уже загружены
	var maps    = {};   // carrier -> { map, coll }

	/* ---------- helpers ---------- */
	function currentCarrier() {
		var r = radios.filter(function (x) { return x.checked; })[0];
		return r ? r.value : '';
	}
	function currentMode() {
		var b = document.querySelector('.dlv-mode.is-active');
		return b ? b.getAttribute('data-mode') : 'pickup';
	}
	function cityValue() {
		var el = document.getElementById('billing_city');
		return el && el.value ? el.value.trim() : '';
	}

	/* ---------- режим: пункт выдачи / адрес ---------- */
	function applyMode() {
		var mode = currentMode();
		modeInput.value = mode;
		document.body.classList.toggle('dlv-is-address', mode === 'address');

		var addrBlock = document.querySelector('.dlv-address-fields');
		if (addrBlock) { addrBlock.hidden = (mode !== 'address'); }

		if (mode === 'address') {
			closeAll();
			pointInput.value = '';
			pointAddr.value = '';
		} else {
			openCarrier(currentCarrier());
		}
	}

	document.querySelectorAll('.dlv-mode').forEach(function (btn) {
		btn.addEventListener('click', function () {
			document.querySelectorAll('.dlv-mode').forEach(function (b) {
				b.classList.remove('is-active');
				b.setAttribute('aria-selected', 'false');
			});
			btn.classList.add('is-active');
			btn.setAttribute('aria-selected', 'true');
			applyMode();
		});
	});

	/* ---------- аккордеон перевозчиков ---------- */
	function closeAll() {
		section.querySelectorAll('.dlv-card').forEach(function (c) { c.classList.remove('is-open'); });
	}
	function openCarrier(carrier) {
		if (!carrier || currentMode() !== 'pickup') { return; }
		closeAll();
		var card = section.querySelector('.dlv-card[data-carrier="' + carrier + '"]');
		if (!card) { return; }
		card.classList.add('is-open');
		loadPoints(carrier);
	}

	radios.forEach(function (r) {
		r.addEventListener('change', function () {
			// сброс выбранного пункта при смене перевозчика
			pointInput.value = '';
			pointAddr.value = '';
			openCarrier(r.value);
		});
	});

	/* ---------- загрузка пунктов ---------- */
	function loadPoints(carrier, force) {
		var listEl = section.querySelector('.dlv-points[data-carrier="' + carrier + '"]');
		if (!listEl) { return; }
		if (loaded[carrier] && !force) { return; }

		listEl.innerHTML = '<div class="dlv-points__hint">Загрузка пунктов…</div>';

		var body = new URLSearchParams();
		body.append('action', 'mjr_delivery_points');
		body.append('nonce', cfg.nonce || '');
		body.append('carrier', carrier);
		body.append('city', cityValue());

		fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (!res || !res.success) {
					listEl.innerHTML = '<div class="dlv-points__hint">' +
						((res && res.data && res.data.message) || 'Не удалось загрузить пункты.') + '</div>';
					return;
				}
				loaded[carrier] = true;
				renderPoints(carrier, res.data.points || []);
			})
			.catch(function () {
				listEl.innerHTML = '<div class="dlv-points__hint">Ошибка загрузки пунктов.</div>';
			});
	}

	function renderPoints(carrier, points) {
		var listEl = section.querySelector('.dlv-points[data-carrier="' + carrier + '"]');
		if (!listEl) { return; }
		if (!points.length) {
			listEl.innerHTML = '<div class="dlv-points__hint">В этом городе пунктов не найдено.</div>';
			return;
		}

		var html = '';
		points.forEach(function (p, i) {
			html += '<button type="button" class="dlv-point" data-i="' + i + '">' +
				'<span class="dlv-point__name">' + esc(p.name) + '</span>' +
				'<span class="dlv-point__addr">' + esc(p.address) + '</span>' +
				'</button>';
		});
		listEl.innerHTML = html;

		listEl.querySelectorAll('.dlv-point').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var p = points[parseInt(btn.getAttribute('data-i'), 10)];
				selectPoint(carrier, p, btn);
			});
		});

		initMap(carrier, points);
	}

	function selectPoint(carrier, p, btn) {
		var listEl = section.querySelector('.dlv-points[data-carrier="' + carrier + '"]');
		if (listEl) {
			listEl.querySelectorAll('.dlv-point').forEach(function (b) { b.classList.remove('is-selected'); });
		}
		if (btn) { btn.classList.add('is-selected'); }
		pointInput.value = p.id || '';
		pointAddr.value = (p.name ? p.name + ', ' : '') + (p.address || '');

		// Кладём адрес пункта в поле WooCommerce, чтобы прошла валидация и заказ знал точку.
		var addrField = document.getElementById('billing_address_1');
		if (addrField) { addrField.value = pointAddr.value; }
		var cityField = document.getElementById('billing_city');
		if (cityField && !cityField.value && p.address) {
			var mCity = p.address.match(/г\.?\s*([А-ЯЁ][а-яё-]+)/);
			if (mCity) { cityField.value = mCity[1]; }
		}

		var m = maps[carrier];
		if (m && m.map && p.lat) {
			m.map.setCenter([p.lat, p.lng], 15, { duration: 250 });
		}
	}

	/* ---------- Яндекс.Карты ---------- */
	function ensureYmaps(cb) {
		if (window.ymaps && ymState.ready) { cb(); return; }
		if (!cfg.ymapsKey) { cb(); return; }               // нет ключа — работаем списком
		if (ymState.loading) {
			var t = setInterval(function () { if (ymState.ready) { clearInterval(t); cb(); } }, 200);
			return;
		}
		ymState.loading = true;
		var s = document.createElement('script');
		s.src = 'https://api-maps.yandex.ru/2.1/?apikey=' + encodeURIComponent(cfg.ymapsKey) + '&lang=ru_RU';
		s.onload = function () { window.ymaps.ready(function () { ymState.ready = true; cb(); }); };
		s.onerror = function () { ymState.loading = false; cb(); };
		document.head.appendChild(s);
	}

	function initMap(carrier, points) {
		if (!cfg.ymapsKey) { return; }
		var mapEl = section.querySelector('.dlv-map[data-carrier="' + carrier + '"]');
		if (!mapEl) { return; }

		ensureYmaps(function () {
			if (!ymState.ready) { return; }
			var center = points[0] && points[0].lat ? [points[0].lat, points[0].lng] : [55.796, 49.108];

			if (!maps[carrier]) {
				var map = new window.ymaps.Map(mapEl, {
					center: center, zoom: 11, controls: ['zoomControl', 'geolocationControl']
				}, { suppressMapOpenBlock: true });
				var coll = new window.ymaps.GeoObjectCollection();
				map.geoObjects.add(coll);
				maps[carrier] = { map: map, coll: coll };
			}
			var m = maps[carrier];
			m.coll.removeAll();

			points.forEach(function (p) {
				if (!p.lat) { return; }
				var pm = new window.ymaps.Placemark([p.lat, p.lng], {
					balloonContentHeader: esc(p.name),
					balloonContentBody: esc(p.address),
					hintContent: esc(p.address)
				}, { preset: 'islands#redDotIcon' });
				pm.events.add('click', function () {
					var btn = section.querySelector('.dlv-points[data-carrier="' + carrier + '"] .dlv-point[data-i="' + points.indexOf(p) + '"]');
					selectPoint(carrier, p, btn);
				});
				m.coll.add(pm);
			});

			// карта в скрытой панели инициализируется с нулевым размером — чиним
			setTimeout(function () { m.map.container.fitToViewport(); }, 60);
		});
	}

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
		});
	}

	/* ---------- перезагрузка пунктов при смене города ---------- */
	var cityEl = document.getElementById('billing_city');
	if (cityEl) {
		var deb;
		cityEl.addEventListener('input', function () {
			clearTimeout(deb);
			deb = setTimeout(function () {
				loaded = {};
				if (currentMode() === 'pickup') { loadPoints(currentCarrier(), true); }
			}, 700);
		});
	}

	/* ---------- старт ---------- */
	applyMode();
})();
