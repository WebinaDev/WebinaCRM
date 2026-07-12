/**
 * Page Wrapper Component JavaScript
 * 
 * Handles dynamic margin adjustment based on sidebar state
 *
 * @package WebinoCRM
 * @since 2.1.0
 */

(function ($) {
	'use strict';

	/**
	 * Page Wrapper Component
	 */
	const WebinoPageWrapper = {
		/**
		 * Initialize
		 */
		init: function () {
			this.setupSidebarAdaptation();
			this.setupShareActions();
			this.setupFilterModal();
			
			// Initial call with delay to ensure DOM is ready
			setTimeout(() => {
				this.updateWrapperMargin();
			}, 100);
			
			// Also call after window load to ensure all styles are loaded
			$(window).on('load', () => {
				setTimeout(() => {
					this.updateWrapperMargin();
				}, 200);
			});
			
			// Listen for sidebar state changes
			$(document).on('sidebar:toggled', this.updateWrapperMargin.bind(this));
			
			// Listen for window resize
			$(window).on('resize', this.debounce(this.updateWrapperMargin.bind(this), 250));
			
			// Listen for theme mode changes (if sidebar width changes)
			$(document).on('theme:changed', this.updateWrapperMargin.bind(this));
		},

		/**
		 * Setup sidebar adaptation
		 */
		setupSidebarAdaptation: function () {
			const self = this;
			// Use MutationObserver to watch for sidebar state changes
			const observer = new MutationObserver((mutations) => {
				mutations.forEach((mutation) => {
					if (mutation.type === 'attributes' && mutation.attributeName === 'data-toggled') {
						self.updateWrapperMargin();
					}
				});
			});

			// Observe the html element for data-toggled changes
			const htmlElement = document.documentElement;
			if (htmlElement) {
				observer.observe(htmlElement, {
					attributes: true,
					attributeFilter: ['data-toggled']
				});
			}

			// Also observe the sidebar element for class changes
			const sidebar = document.getElementById('sidebar');
			if (sidebar) {
				observer.observe(sidebar, {
					attributes: true,
					attributeFilter: ['class']
				});
			}
		},

		/**
		 * Update wrapper margin and width based on sidebar state
		 */
		updateWrapperMargin: function () {
			const $wrapper = $('.webino-page-wrapper');
			if (!$wrapper.length) {
				return;
			}

			const html = document.documentElement;
			const toggled = html.getAttribute('data-toggled') || '';
			const isRTL = html.getAttribute('dir') === 'rtl';
			
			// Margin from sidebar (always 10px)
			const sidebarMargin = 10;
			// Margin from opposite edge (always 15px)
			const oppositeMargin = 15;
			
			// Check if sidebar is collapsed
			const isCollapsed = toggled.includes('close') || 
			                   toggled.includes('closed') ||
			                   toggled.includes('overlay-close');
			
			// Apply margins and width only on desktop
			if (window.innerWidth >= 992) {
				if (isRTL) {
					// RTL: sidebar on right, margin-right from sidebar, margin-left from left edge
					// margin-left MUST stay fixed at 15px - DO NOT CHANGE
					// Wrapper expands to RIGHT (sidebar side), LEFT stays fixed
					// Use setProperty with !important to override any conflicting styles
					$wrapper[0].style.setProperty('margin-left', oppositeMargin + 'px', 'important');
					$wrapper[0].style.setProperty('margin-right', sidebarMargin + 'px', 'important');
					$wrapper[0].style.setProperty('position', 'relative', 'important');
					$wrapper[0].style.setProperty('left', '0', 'important');
					$wrapper[0].style.setProperty('right', 'auto', 'important');
					// ONLY set width when collapsed - use viewport width to ensure proper expansion
					// RTL: sidebar on RIGHT, margin-left stays fixed, wrapper expands to RIGHT
					if (isCollapsed) {
						// IMPORTANT: app-content has margin-inline-start: 5rem when collapsed
						// In RTL, this means margin-right: 5rem
						// Wrapper is INSIDE app-content, so calculate width relative to app-content (100%), NOT viewport
						// CRITICAL: In RTL, wrapper must expand to the RIGHT (towards sidebar)
						// To make wrapper expand from RIGHT: margin-right: fixed (10px), margin-left: auto (pushes to right)
						// This ensures wrapper starts from the right and expands to the right (towards sidebar)
						$wrapper[0].style.setProperty('margin-right', sidebarMargin + 'px', 'important');
						$wrapper[0].style.setProperty('margin-left', 'auto', 'important'); // AUTO pushes to right
						$wrapper[0].style.setProperty('margin-inline-start', 'auto', 'important');
						$wrapper[0].style.setProperty('margin-inline-end', sidebarMargin + 'px', 'important');
						$wrapper[0].style.setProperty('width', 'calc(100% - ' + sidebarMargin + 'px)', 'important');
						$wrapper[0].style.setProperty('max-width', 'calc(100% - ' + sidebarMargin + 'px)', 'important');
						$wrapper[0].style.setProperty('position', 'relative', 'important');
						$wrapper[0].style.setProperty('left', 'auto', 'important');
						$wrapper[0].style.setProperty('right', 'auto', 'important');
						$wrapper[0].style.setProperty('box-sizing', 'border-box', 'important');
					} else {
						// Remove width, let CSS handle it for open state
						$wrapper[0].style.removeProperty('width');
						$wrapper[0].style.removeProperty('max-width');
						$wrapper[0].style.removeProperty('left');
						$wrapper[0].style.removeProperty('right');
						// Ensure margins are set with !important
						$wrapper[0].style.setProperty('margin-left', oppositeMargin + 'px', 'important');
						$wrapper[0].style.setProperty('margin-right', sidebarMargin + 'px', 'important');
					}
				} else {
					// LTR: sidebar on left, margin-left from sidebar, margin-right from right edge
					// margin-right MUST stay fixed at 15px - DO NOT CHANGE
					// Use setProperty with !important to override any conflicting styles
					$wrapper[0].style.setProperty('margin-right', oppositeMargin + 'px', 'important');
					$wrapper[0].style.setProperty('margin-left', sidebarMargin + 'px', 'important');
					$wrapper[0].style.setProperty('position', 'relative', 'important');
					$wrapper[0].style.setProperty('right', '0', 'important');
					$wrapper[0].style.setProperty('left', 'auto', 'important');
					// ONLY set width when collapsed - use viewport width to ensure proper expansion
					// LTR: sidebar on LEFT, margin-right stays fixed, wrapper expands to LEFT
					if (isCollapsed) {
						// Use CSS with !important to override any conflicting styles
						$wrapper[0].style.setProperty('width', 'calc(100vw - 5rem - 25px)', 'important');
						$wrapper[0].style.setProperty('max-width', 'calc(100vw - 5rem - 25px)', 'important');
						$wrapper[0].style.setProperty('left', 'auto', 'important');
						$wrapper[0].style.setProperty('right', 'auto', 'important');
						// Ensure margins are set with !important
						$wrapper[0].style.setProperty('margin-left', sidebarMargin + 'px', 'important');
						$wrapper[0].style.setProperty('margin-right', oppositeMargin + 'px', 'important');
					} else {
						// Remove width, let CSS handle it for open state
						$wrapper[0].style.removeProperty('width');
						$wrapper[0].style.removeProperty('max-width');
						$wrapper[0].style.removeProperty('left');
						$wrapper[0].style.removeProperty('right');
						// Ensure margins are set with !important
						$wrapper[0].style.setProperty('margin-left', sidebarMargin + 'px', 'important');
						$wrapper[0].style.setProperty('margin-right', oppositeMargin + 'px', 'important');
					}
				}
			} else {
				// Mobile: reset to default
				$wrapper.css({
					'margin-right': '15px',
					'margin-left': '15px',
					'width': '',
					'max-width': '',
					'position': 'relative',
					'left': 'auto',
					'right': 'auto'
				});
			}
		},

		/**
		 * Setup share actions
		 */
		setupShareActions: function () {
			// Copy link
			$(document).on('click', '#webino-share-link', function (e) {
				e.preventDefault();
				const url = window.location.href;
				
				// Use Clipboard API if available
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(url).then(function () {
						WebinoPageWrapper.showNotification('لینک با موفقیت کپی شد', 'success');
					}).catch(function () {
						WebinoPageWrapper.fallbackCopyTextToClipboard(url);
					});
				} else {
					WebinoPageWrapper.fallbackCopyTextToClipboard(url);
				}
			});

			// Export PDF
			$(document).on('click', '#webino-share-pdf', function (e) {
				e.preventDefault();
				WebinoPageWrapper.showNotification('در حال آماده‌سازی PDF...', 'info');
				// TODO: Implement PDF export functionality
				setTimeout(function () {
					WebinoPageWrapper.showNotification('قابلیت خروجی PDF به زودی اضافه می‌شود', 'info');
				}, 1000);
			});

			// Print
			$(document).on('click', '#webino-share-print', function (e) {
				e.preventDefault();
				window.print();
			});
		},

		/**
		 * Fallback copy to clipboard for older browsers
		 */
		fallbackCopyTextToClipboard: function (text) {
			const textArea = document.createElement('textarea');
			textArea.value = text;
			textArea.style.position = 'fixed';
			textArea.style.left = '-999999px';
			textArea.style.top = '-999999px';
			document.body.appendChild(textArea);
			textArea.focus();
			textArea.select();
			
			try {
				document.execCommand('copy');
				this.showNotification('لینک با موفقیت کپی شد', 'success');
			} catch (err) {
				this.showNotification('خطا در کپی کردن لینک', 'error');
			}
			
			document.body.removeChild(textArea);
		},

		/**
		 * Setup filter modal
		 */
		setupFilterModal: function () {
			// Filter modal is already set up in the template
			// This function can be extended to add dynamic filter content
			$(document).on('click', '#webino-apply-filters', function () {
				// TODO: Implement filter application logic
				const modal = bootstrap.Modal.getInstance(document.getElementById('webino-filter-modal'));
				if (modal) {
					modal.hide();
				}
				WebinoPageWrapper.showNotification('فیلترها اعمال شدند', 'success');
			});
		},

		/**
		 * Show notification
		 */
		showNotification: function (message, type) {
			type = type || 'info';
			
			// Use Toastify if available
			if (typeof Toastify !== 'undefined') {
				Toastify({
					text: message,
					duration: 3000,
					gravity: 'top',
					position: 'left',
					backgroundColor: type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8',
				}).showToast();
			} else {
				// Fallback to alert
				alert(message);
			}
		},

		/**
		 * Debounce function
		 */
		debounce: function (func, wait) {
			let timeout;
			return function executedFunction(...args) {
				const later = () => {
					clearTimeout(timeout);
					func(...args);
				};
				clearTimeout(timeout);
				timeout = setTimeout(later, wait);
			};
		}
	};

	/**
	 * Initialize on document ready
	 */
	$(document).ready(function () {
		WebinoPageWrapper.init();
	});

	// Also initialize after window load to ensure all styles and scripts are loaded
	$(window).on('load', function () {
		setTimeout(function () {
			WebinoPageWrapper.updateWrapperMargin();
		}, 300);
	});

})(jQuery);

