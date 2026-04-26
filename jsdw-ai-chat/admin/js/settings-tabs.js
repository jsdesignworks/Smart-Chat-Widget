(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var root = document.querySelector('.jsdw-settings-page');
		if (!root) {
			return;
		}
		var tabs = root.querySelectorAll('.jsdw-settings-tab');
		var panels = root.querySelectorAll('.jsdw-settings-tab-panel');
		if (!tabs.length || !panels.length) {
			return;
		}

		function show(tab) {
			tabs.forEach(function (btn) {
				var on = btn.getAttribute('data-tab') === tab;
				btn.classList.toggle('is-active', on);
				btn.setAttribute('aria-selected', on ? 'true' : 'false');
			});
			panels.forEach(function (p) {
				var on = p.getAttribute('data-tab') === tab;
				if (on) {
					p.removeAttribute('hidden');
					p.classList.add('is-active');
				} else {
					p.setAttribute('hidden', 'hidden');
					p.classList.remove('is-active');
				}
			});
		}

		tabs.forEach(function (btn) {
			btn.addEventListener('click', function () {
				var t = btn.getAttribute('data-tab');
				if (t) {
					show(t);
				}
			});
		});
	});
})();
