(function () {
	'use strict';

	if (typeof webinaPortfolio === 'undefined') {
		return;
	}

	var form = document.querySelector('.webina-portfolio-filter');
	var grid = document.querySelector('.webina-portfolio-grid');
	if (!form || !grid) {
		return;
	}

	form.addEventListener('change', function () {
		var fd = new FormData(form);
		fd.append('action', 'webina_portfolio_filter');
		fd.append('nonce', webinaPortfolio.nonce);

		fetch(webinaPortfolio.ajaxUrl, {
			method: 'POST',
			body: fd,
			credentials: 'same-origin',
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (data) {
				if (data.success && data.data && data.data.html) {
					grid.innerHTML = data.data.html;
				}
			});
	});
})();
