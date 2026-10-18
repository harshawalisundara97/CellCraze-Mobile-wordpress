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
})();
