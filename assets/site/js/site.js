(function () {
	'use strict';

	document.documentElement.setAttribute('dir', 'rtl');

	var cfg = window.webinaSite || {};
	var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	function initMegaMenu() {
		var header = document.getElementById('webina-mega-header');
		if (!header) return;

		var triggers = header.querySelectorAll('.webina-mega-trigger');
		var panels = header.querySelectorAll('.webina-mega-panel');
		var toggle = header.querySelector('.webina-mega-header__toggle');
		var nav = header.querySelector('.webina-mega-header__nav');
		var desktop = window.matchMedia('(min-width: 641px)');

		function closeAll() {
			triggers.forEach(function (t) {
				t.setAttribute('aria-expanded', 'false');
			});
			panels.forEach(function (p) {
				p.hidden = true;
			});
		}

		function openPanel(id, trigger) {
			var panel = header.querySelector('[data-mega-panel="' + id + '"]');
			if (!panel) return;
			closeAll();
			panel.hidden = false;
			if (trigger) trigger.setAttribute('aria-expanded', 'true');
		}

		triggers.forEach(function (trigger) {
			var id = trigger.getAttribute('data-mega');
			trigger.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopPropagation();
				var panel = header.querySelector('[data-mega-panel="' + id + '"]');
				if (!panel) return;
				if (!panel.hidden) {
					closeAll();
				} else {
					openPanel(id, trigger);
				}
			});
			trigger.addEventListener('mouseenter', function () {
				if (desktop.matches) openPanel(id, trigger);
			});
		});

		panels.forEach(function (panel) {
			panel.addEventListener('mouseleave', function () {
				if (desktop.matches) closeAll();
			});
		});

		document.addEventListener('click', function (e) {
			if (!header.contains(e.target)) closeAll();
		});

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') closeAll();
		});

		if (toggle && nav) {
			toggle.addEventListener('click', function () {
				var open = nav.classList.toggle('is-open');
				toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			});
		}
	}

	function initHeaderScroll() {
		var header = document.getElementById('webina-mega-header');
		if (!header) return;
		var ticking = false;
		function onScroll() {
			if (ticking) return;
			ticking = true;
			window.requestAnimationFrame(function () {
				header.classList.toggle('is-scrolled', window.scrollY > 24);
				ticking = false;
			});
		}
		window.addEventListener('scroll', onScroll, { passive: true });
		onScroll();
	}

	function animateCount(el, end, suffix, duration) {
		var start = 0;
		var t0 = null;
		duration = duration || 1200;
		function frame(ts) {
			if (!t0) t0 = ts;
			var p = Math.min(1, (ts - t0) / duration);
			var eased = 1 - Math.pow(1 - p, 3);
			var val = Math.round(start + (end - start) * eased);
			el.textContent = suffix ? val + suffix : String(val);
			if (p < 1) window.requestAnimationFrame(frame);
		}
		if (reduceMotion) {
			el.textContent = suffix ? end + suffix : String(end);
			return;
		}
		window.requestAnimationFrame(frame);
	}

	function initStatsCounter() {
		var items = document.querySelectorAll(
			'.webina-stats__item strong[data-count], .webina-split-service__stat strong[data-count], .wdm-counter__ring strong[data-count], .wdm-bottom-stat strong[data-count]'
		);
		if (!items.length) return;

		function runEl(el) {
			if (el.dataset.counted === '1') return;
			var target = el.getAttribute('data-count') || '';
			if (!/^\d+$/.test(target)) return;
			el.dataset.counted = '1';
			var end = parseInt(target, 10);
			var suffix = el.getAttribute('data-suffix') || '';
			animateCount(el, end, suffix, 1400);

			var ring = el.closest('[data-wdm-ring]');
			if (ring) {
				var pct = parseFloat(ring.getAttribute('data-wdm-ring') || '0');
				ring.style.setProperty('--wdm-ring', String(pct));
				ring.classList.add('is-animated');
			}
		}

		if (!('IntersectionObserver' in window)) {
			items.forEach(runEl);
			return;
		}

		var io = new IntersectionObserver(
			function (entries) {
				entries.forEach(function (entry) {
					if (!entry.isIntersecting) return;
					var strong = entry.target.matches('strong[data-count]')
						? entry.target
						: entry.target.querySelector('strong[data-count]');
					if (strong) runEl(strong);
					io.unobserve(entry.target);
				});
			},
			{ threshold: 0.35 }
		);

		items.forEach(function (el) {
			io.observe(el.closest('.wdm-counter, .wdm-bottom-stat, .webina-stats__item, .webina-split-service__stat') || el);
		});
	}

	function initReveal() {
		var nodes = document.querySelectorAll('[data-wdm-reveal]');
		if (!nodes.length) return;
		if (reduceMotion) {
			nodes.forEach(function (n) {
				n.classList.add('is-revealed');
			});
			return;
		}
		if (!('IntersectionObserver' in window)) {
			nodes.forEach(function (n) {
				n.classList.add('is-revealed');
			});
			return;
		}
		var io = new IntersectionObserver(
			function (entries) {
				entries.forEach(function (entry) {
					if (!entry.isIntersecting) return;
					entry.target.classList.add('is-revealed');
					io.unobserve(entry.target);
				});
			},
			{ threshold: 0.12, rootMargin: '0px 0px -8% 0px' }
		);
		nodes.forEach(function (n) {
			io.observe(n);
		});
	}

	function initParallax() {
		if (reduceMotion) return;
		var layers = document.querySelectorAll('[data-wdm-parallax]');
		if (!layers.length) return;
		var ticking = false;
		function update() {
			var vh = window.innerHeight;
			layers.forEach(function (el) {
				var speed = parseFloat(el.getAttribute('data-wdm-parallax') || '0.1');
				var rect = el.getBoundingClientRect();
				var mid = rect.top + rect.height / 2;
				var offset = (mid - vh / 2) * speed;
				el.style.transform = 'translate3d(0,' + offset.toFixed(2) + 'px,0)';
			});
			ticking = false;
		}
		function onScroll() {
			if (ticking) return;
			ticking = true;
			window.requestAnimationFrame(update);
		}
		window.addEventListener('scroll', onScroll, { passive: true });
		window.addEventListener('resize', onScroll, { passive: true });
		update();
	}

	function submitLeadForm(form) {
		if (!cfg.ajaxUrl || !cfg.leadNonce) return;

		var btn = form.querySelector('button[type="submit"]');
		var status = form.querySelector('.webina-lead-form__status');
		if (btn) btn.disabled = true;
		if (status) {
			status.textContent = '';
			status.classList.remove('is-error', 'is-ok');
		}

		var body = new FormData();
		body.append('action', cfg.leadAction || 'webinocrm_site_lead');
		body.append('nonce', cfg.leadNonce);
		body.append('name', (form.querySelector('[name="webina_name"]') || {}).value || '');
		body.append('phone', (form.querySelector('[name="webina_phone"]') || {}).value || '');
		body.append('message', (form.querySelector('[name="webina_message"]') || {}).value || '');
		body.append('service', (form.querySelector('[name="webina_service"]') || {}).value || '');
		body.append('page_url', window.location.href);

		fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
			.then(function (r) {
				return r.json();
			})
			.then(function (data) {
				if (status) {
					status.textContent = (data.data && data.data.message) || data.message || 'ارسال شد';
					status.classList.toggle('is-error', !data.success);
					status.classList.toggle('is-ok', !!data.success);
				}
				if (data.success) form.reset();
			})
			.catch(function () {
				if (status) {
					status.textContent = 'خطا در ارسال. دوباره تلاش کنید.';
					status.classList.add('is-error');
				}
			})
			.finally(function () {
				if (btn) btn.disabled = false;
			});
	}

	function initLeadForms() {
		document.querySelectorAll('.webina-lead-form, [data-webina-lead-form]').forEach(function (form) {
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				submitLeadForm(form);
			});
		});
	}

	function initCareerForms() {
		document.querySelectorAll('[data-webina-career-form]').forEach(function (form) {
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				if (!cfg.ajaxUrl || !cfg.leadNonce) return;
				var btn = form.querySelector('button[type="submit"]');
				var status = form.querySelector('.webina-lead-form__status');
				if (btn) btn.disabled = true;
				var body = new FormData();
				body.append('action', cfg.careerAction || 'webinocrm_site_career');
				body.append('nonce', cfg.leadNonce);
				body.append('name', (form.querySelector('[name="webina_name"]') || {}).value || '');
				body.append('phone', (form.querySelector('[name="webina_phone"]') || {}).value || '');
				body.append('city', (form.querySelector('[name="webina_city"]') || {}).value || '');
				body.append('work_type', (form.querySelector('[name="webina_work_type"]') || {}).value || '');
				body.append('field', (form.querySelector('[name="webina_field"]') || {}).value || '');
				body.append('experience', (form.querySelector('[name="webina_experience"]') || {}).value || '');
				body.append('level', (form.querySelector('[name="webina_level"]') || {}).value || '');
				body.append('resume_link', (form.querySelector('[name="webina_resume_link"]') || {}).value || '');
				fetch(cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' })
					.then(function (r) {
						return r.json();
					})
					.then(function (data) {
						if (status) {
							status.textContent = (data.data && data.data.message) || data.message || 'ارسال شد';
							status.classList.toggle('is-error', !data.success);
							status.classList.toggle('is-ok', !!data.success);
						}
						if (data.success) form.reset();
					})
					.catch(function () {
						if (status) {
							status.textContent = 'خطا در ارسال. دوباره تلاش کنید.';
							status.classList.add('is-error');
						}
					})
					.finally(function () {
						if (btn) btn.disabled = false;
					});
			});
		});
	}

	function initPopup() {
		var popup = document.getElementById('webina-moshavere-popup');
		if (!popup) return;

		function openPopup(e) {
			if (e) e.preventDefault();
			popup.hidden = false;
			requestAnimationFrame(function () {
				popup.classList.add('is-open');
			});
			popup.setAttribute('aria-hidden', 'false');
			document.body.style.overflow = 'hidden';
		}

		function closePopup() {
			popup.classList.remove('is-open');
			setTimeout(function () {
				if (!popup.classList.contains('is-open')) popup.hidden = true;
			}, 220);
			popup.setAttribute('aria-hidden', 'true');
			document.body.style.overflow = '';
		}

		document.querySelectorAll('[data-webina-popup-open], a[href="#moshavere-popup"]').forEach(function (el) {
			el.addEventListener('click', openPopup);
		});

		popup.querySelectorAll('[data-webina-popup-close]').forEach(function (el) {
			el.addEventListener('click', closePopup);
		});

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && popup.classList.contains('is-open')) closePopup();
		});

		window.webinaOpenConsultPopup = openPopup;
	}

	function initFab() {
		var fab = document.getElementById('webina-fab');
		if (!fab) return;
		var toggle = fab.querySelector('.webina-fab__toggle');
		var panel = fab.querySelector('.webina-fab__panel');
		if (!toggle || !panel) return;

		toggle.addEventListener('click', function () {
			var open = fab.classList.toggle('is-open');
			panel.hidden = !open;
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
		});

		document.addEventListener('click', function (e) {
			if (!fab.contains(e.target)) {
				fab.classList.remove('is-open');
				panel.hidden = true;
				toggle.setAttribute('aria-expanded', 'false');
			}
		});
	}

	function initMarquees() {
		document.querySelectorAll('.webina-client-marquee, .webina-logo-marquee').forEach(function (root) {
			var track = root.querySelector('.webina-client-marquee__track, .webina-logo-marquee__track');
			if (!track || track.dataset.cloned === '1') return;
			track.innerHTML = track.innerHTML + track.innerHTML;
			track.dataset.cloned = '1';
		});
	}

	function initHomeCarousels() {
		document.querySelectorAll('[data-wdm-carousel]').forEach(function (root) {
			var track = root.querySelector('.wdm-carousel__track');
			if (!track) return;

			var dragging = false;
			var startX = 0;
			var scrollLeft = 0;
			var paused = false;
			var autoMs = parseInt(root.getAttribute('data-wdm-autoplay') || '0', 10);

			track.addEventListener('mousedown', function (e) {
				dragging = true;
				paused = true;
				startX = e.pageX - track.offsetLeft;
				scrollLeft = track.scrollLeft;
				track.style.cursor = 'grabbing';
			});
			window.addEventListener('mouseup', function () {
				dragging = false;
				track.style.cursor = '';
				setTimeout(function () {
					paused = false;
				}, 800);
			});
			track.addEventListener('mousemove', function (e) {
				if (!dragging) return;
				e.preventDefault();
				var x = e.pageX - track.offsetLeft;
				track.scrollLeft = scrollLeft - (x - startX);
			});

			root.addEventListener('mouseenter', function () {
				paused = true;
			});
			root.addEventListener('mouseleave', function () {
				paused = false;
			});

			if (!autoMs || reduceMotion) return;

			setInterval(function () {
				if (paused || document.hidden) return;
				var step = Math.max(160, Math.floor(track.clientWidth * 0.45));
				var max = track.scrollWidth - track.clientWidth;
				if (max <= 0) return;
				if (track.scrollLeft + step >= max - 4) {
					track.scrollTo({ left: 0, behavior: 'smooth' });
				} else {
					track.scrollBy({ left: step, behavior: 'smooth' });
				}
			}, autoMs);
		});
	}

	function boot() {
		initMegaMenu();
		initHeaderScroll();
		initReveal();
		initParallax();
		initStatsCounter();
		initLeadForms();
		initCareerForms();
		initPopup();
		initFab();
		initMarquees();
		initHomeCarousels();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
