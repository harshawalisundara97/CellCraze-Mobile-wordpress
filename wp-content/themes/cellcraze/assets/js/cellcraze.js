/* CellCraze storefront — tiny progressive enhancements (no dependencies).
   Back-to-top control + dismissible announcement bar. Everything degrades
   gracefully: the page is fully usable with JS disabled. */
(function () {
	'use strict';

	var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* Back to top */
	var toTop = document.querySelector('.cc-to-top');
	if (toTop) {
		var toggle = function () {
			if (window.pageYOffset > 600) {
				toTop.classList.add('is-visible');
			} else {
				toTop.classList.remove('is-visible');
			}
		};
		window.addEventListener('scroll', toggle, { passive: true });
		toggle();
		toTop.addEventListener('click', function () {
			window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
		});
	}

	/* Dismissible announcement bar (remembered per browser) */
	var announce = document.querySelector('.cc-announce');
	if (announce) {
		var key = 'cc_announce_dismissed';
		var current = announce.getAttribute('data-key') || (announce.textContent || '').trim();
		try {
			if (window.localStorage && localStorage.getItem(key) === current) {
				announce.hidden = true;
			}
		} catch (e) {}
		var closeBtn = announce.querySelector('.cc-announce-close');
		if (closeBtn) {
			closeBtn.addEventListener('click', function () {
				announce.hidden = true;
				try {
					if (window.localStorage) {
						localStorage.setItem(key, current);
					}
				} catch (e) {}
			});
		}
	}

	/* Live product-search suggestions (progressive enhancement — Enter / the
	   submit button still navigate to the real WooCommerce search results). */
	if (typeof CellCrazeSearch !== 'undefined') {
		document.querySelectorAll('form.cc-search').forEach(function (form) {
			var input = form.querySelector('input[type="search"]');
			if (!input) { return; }

			var box = document.createElement('div');
			box.className = 'cc-suggest';
			box.setAttribute('role', 'listbox');
			box.hidden = true;
			form.appendChild(box);

			var items = [];
			var active = -1;
			var timer = null;
			var lastTerm = '';
			var controller = null;

			function close() { box.hidden = true; box.innerHTML = ''; items = []; active = -1; input.setAttribute('aria-expanded', 'false'); }
			function setActive(i) {
				if (!items.length) { return; }
				active = (i + items.length) % items.length;
				items.forEach(function (el, idx) { el.classList.toggle('is-active', idx === active); });
				items[active].scrollIntoView({ block: 'nearest' });
			}

			function render(data) {
				box.innerHTML = '';
				items = [];
				active = -1;
				var list = (data && data.results) || [];
				if (!list.length) {
					var empty = document.createElement('div');
					empty.className = 'cc-suggest-empty';
					empty.textContent = CellCrazeSearch.i18n.noResults;
					box.appendChild(empty);
					box.hidden = false;
					input.setAttribute('aria-expanded', 'true');
					return;
				}
				list.forEach(function (r) {
					var a = document.createElement('a');
					a.className = 'cc-suggest-item';
					a.href = r.url;
					a.setAttribute('role', 'option');
					a.innerHTML =
						'<span class="cc-suggest-thumb"><img src="' + encodeURI(r.img) + '" alt="" loading="lazy"></span>' +
						'<span class="cc-suggest-text"><span class="cc-suggest-name"></span>' +
						'<span class="cc-suggest-price">' + r.price + '</span></span>';
					a.querySelector('.cc-suggest-name').textContent = r.name;
					box.appendChild(a);
					items.push(a);
				});
				if (data.viewAll) {
					var all = document.createElement('a');
					all.className = 'cc-suggest-all';
					all.href = data.viewAll;
					all.textContent = CellCrazeSearch.i18n.viewAll;
					box.appendChild(all);
					items.push(all);
				}
				box.hidden = false;
				input.setAttribute('aria-expanded', 'true');
			}

			function fetchSuggest(term) {
				if (controller) { try { controller.abort(); } catch (e) {} }
				controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
				var url = CellCrazeSearch.ajaxUrl + '?action=cellcraze_search&nonce=' +
					encodeURIComponent(CellCrazeSearch.nonce) + '&q=' + encodeURIComponent(term);
				fetch(url, { signal: controller ? controller.signal : undefined, credentials: 'same-origin' })
					.then(function (r) { return r.json(); })
					.then(function (data) { if (input.value.trim() === term) { render(data); } })
					.catch(function () {});
			}

			input.setAttribute('autocomplete', 'off');
			input.setAttribute('aria-expanded', 'false');

			input.addEventListener('input', function () {
				var term = input.value.trim();
				if (term === lastTerm) { return; }
				lastTerm = term;
				clearTimeout(timer);
				if (term.length < 2) { close(); return; }
				timer = setTimeout(function () { fetchSuggest(term); }, 180);
			});

			input.addEventListener('keydown', function (e) {
				if (box.hidden) { return; }
				if (e.key === 'ArrowDown') { e.preventDefault(); setActive(active + 1); }
				else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(active - 1); }
				else if (e.key === 'Enter') { if (active > -1 && items[active]) { e.preventDefault(); window.location.href = items[active].href; } }
				else if (e.key === 'Escape') { close(); }
			});

			input.addEventListener('focus', function () { if (items.length && input.value.trim().length >= 2) { box.hidden = false; } });
			document.addEventListener('click', function (e) { if (!form.contains(e.target)) { close(); } });
		});
	}

	/* Live header cart count. Keeps the themed .cc-cart-count elements in sync
	   with WooCommerce after supported cart changes — AJAX add-to-cart (classic
	   fragments) and Cart/Checkout Block quantity + remove (Store API data
	   store). No polling; a single subscription guarded so it only renders when
	   the count actually changes. Fully progressive: with JS off, the
	   server-rendered count is correct on each page load. */
	(function () {
		function setCount(count) {
			var n = String(count);
			var els = document.querySelectorAll('.cc-cart-count');
			for (var i = 0; i < els.length; i++) {
				if (els[i].textContent !== n) { els[i].textContent = n; }
			}
		}

		// 1) Cart / Checkout Block changes via the Store API data store. The
		// wc/store/cart store loads after this script, so on block pages we
		// wait (bounded) for it to be ready, then subscribe exactly once.
		if (document.querySelector('.wp-block-woocommerce-cart, .wp-block-woocommerce-checkout')) {
			var tries = 0;
			(function waitForStore() {
				var store = (window.wp && wp.data && typeof wp.data.subscribe === 'function')
					? wp.data.select('wc/store/cart') : null;
				if (store && typeof store.getCartData === 'function') {
					var last = null;
					wp.data.subscribe(function () {
						var data = wp.data.select('wc/store/cart').getCartData();
						if (!data || typeof data.itemsCount !== 'number') { return; }
						if (data.itemsCount !== last) { last = data.itemsCount; setCount(data.itemsCount); }
					});
					return;
				}
				if (tries++ < 50) { setTimeout(waitForStore, 100); }
			})();
		}

		// 2) Classic AJAX add-to-cart / fragment refreshes (when enabled).
		if (window.jQuery) {
			jQuery(document.body).on('added_to_cart removed_from_cart wc_fragments_refreshed wc_fragments_loaded', function () {
				var el = document.querySelector('.cc-cart-count');
				if (el) { setCount(el.textContent.trim()); }
			});
		}
	})();
})();
