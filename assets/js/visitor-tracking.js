/**
 * WebinoCRM Visitor Tracking (frontend only – not dashboard/admin)
 * Sends page view to webinocrm_track_visit once per page load.
 */
(function () {
	'use strict';

	var config = window.webinocrm_visitor_tracking || {};
	var ajaxUrl = config.ajax_url || (typeof webinocrm_ajax_obj !== 'undefined' ? webinocrm_ajax_obj.ajax_url : '');
	if (!ajaxUrl) return;

	function track() {
		var pageUrl = window.location.href || '';
		if (pageUrl.indexOf('/dashboard') !== -1 || pageUrl.indexOf('/wp-admin') !== -1 || pageUrl.indexOf('wp-login') !== -1) {
			return;
		}
		var formData = new FormData();
		formData.append('action', 'webinocrm_track_visit');
		if (config.nonce) {
			formData.append('security', config.nonce);
		}
		formData.append('page_url', pageUrl);
		formData.append('page_title', document.title || '');
		formData.append('referrer', document.referrer || '');

		var xhr = new XMLHttpRequest();
		xhr.open('POST', ajaxUrl);
		xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
		xhr.onload = function () {
			try {
				var res = JSON.parse(xhr.responseText);
				if (res && res.success && res.data && res.data.tracked) {
					// tracked
				}
			} catch (e) {}
		};
		xhr.send(formData);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', track);
	} else {
		track();
	}
})();
