/**
 * Theme Switcher JavaScript (legacy PHP dashboard pages)
 * Synced with React dashboard: supports light / dark / system in localStorage.
 *
 * @package WebinoCRM
 * @since 2.1.0
 */

(function ($) {
	'use strict';

	const WebinoThemeSwitcher = {
		storageKey: 'webino-theme',

		resolveTheme: function (theme) {
			if (theme === 'dark') return 'dark';
			if (theme === 'light') return 'light';
			if (theme === 'system') {
				if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
					return 'dark';
				}
				return 'light';
			}
			if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
				return 'dark';
			}
			return 'light';
		},

		applyTheme: function (mode) {
			const storedMode =
				mode === 'system' || mode === 'light' || mode === 'dark'
					? mode
					: mode === 'dark'
						? 'dark'
						: 'light';
			const resolved = this.resolveTheme(storedMode);
			const htmlEl = document.documentElement;
			const $html = $(htmlEl);

			$html.attr('data-theme', storedMode);
			$html.attr('data-theme-mode', resolved);
			$html.attr('data-header-styles', resolved);
			$html.attr('data-menu-styles', resolved === 'dark' ? 'dark' : 'light');
			htmlEl.classList.toggle('dark', resolved === 'dark');

			try {
				localStorage.setItem(this.storageKey, storedMode);
				localStorage.removeItem('xintradarktheme');
				localStorage.setItem('xintraMenu', resolved === 'dark' ? 'dark' : 'light');
				localStorage.setItem('xintraHeader', resolved);
			} catch (e) {
				// ignore
			}

			$(document).trigger('webinocrm:theme-changed', [resolved, storedMode]);
		},

		init: function () {
			this.applySavedTheme();
			this.setupThemeToggle();
		},

		applySavedTheme: function () {
			let storedTheme = null;
			try {
				storedTheme = localStorage.getItem(this.storageKey);
				if (!storedTheme && localStorage.getItem('xintradarktheme') === 'true') {
					storedTheme = 'dark';
				}
			} catch (e) {
				// ignore
			}

			const fallback =
				document.documentElement.getAttribute('data-theme') ||
				document.documentElement.getAttribute('data-theme-mode') ||
				'light';
			this.applyTheme(storedTheme || fallback);
		},

		setupThemeToggle: function () {
			$(document).on('click', '.layout-setting', function (e) {
				e.preventDefault();
				e.stopPropagation();
				WebinoThemeSwitcher.toggleTheme();
			});
		},

		toggleTheme: function () {
			let stored = 'light';
			try {
				stored = localStorage.getItem(this.storageKey) || 'light';
			} catch (e) {
				// ignore
			}
			const resolved = this.resolveTheme(stored);
			this.applyTheme(resolved === 'dark' ? 'light' : 'dark');
		},

		getCurrentTheme: function () {
			try {
				return localStorage.getItem(this.storageKey) || 'light';
			} catch (e) {
				return 'light';
			}
		},
	};

	$(document).ready(function () {
		WebinoThemeSwitcher.init();
	});

	window.WebinoThemeSwitcher = WebinoThemeSwitcher;
})(jQuery);
