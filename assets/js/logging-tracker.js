/**
 * WebinoCRM Logging Tracker - Intercepts console, button clicks, form submit, AJAX
 */
(function() {
    'use strict';

    function initLoggingTracker() {
        if (typeof jQuery === 'undefined') {
            setTimeout(initLoggingTracker, 50);
            return;
        }
        var $ = jQuery;
        var ajaxObj = typeof webinocrm_ajax_obj !== 'undefined' ? webinocrm_ajax_obj : { ajax_url: '', nonce: '' };
        var ajaxUrl = ajaxObj.ajax_url || (typeof wp !== 'undefined' && wp.ajax && wp.ajax.settings && wp.ajax.settings.url ? wp.ajax.settings.url : '');
        if (!ajaxUrl) ajaxUrl = '/wp-admin/admin-ajax.php';
        var nonce = ajaxObj.nonce || '';

        var loggingSettings = typeof webinocrm_logging_settings !== 'undefined' ? webinocrm_logging_settings : {
            enable_logging_system: true,
            log_console_messages: true,
            enable_user_logging: true,
            log_button_clicks: true,
            log_form_submissions: true,
            log_ajax_calls: true,
            log_page_views: false
        };

        var originalLog = console.log;
        var originalError = console.error;
        var originalWarn = console.warn;

        console.log = function() {
            originalLog.apply(console, arguments);
            if (loggingSettings.enable_logging_system && loggingSettings.log_console_messages) {
                logToSystem('console', 'info', Array.from(arguments).join(' '));
            }
        };
        console.error = function() {
            originalError.apply(console, arguments);
            if (loggingSettings.enable_logging_system && loggingSettings.log_console_messages) {
                logToSystem('error', 'error', Array.from(arguments).join(' '));
            }
        };
        console.warn = function() {
            originalWarn.apply(console, arguments);
            if (loggingSettings.enable_logging_system && loggingSettings.log_console_messages) {
                logToSystem('debug', 'warning', Array.from(arguments).join(' '));
            }
        };

        var originalAjaxForLogging = null;

        function logToSystem(logType, severity, message, context) {
            if (!ajaxUrl) return;
            var ajaxFn = originalAjaxForLogging || $.ajax;
            ajaxFn({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'webinocrm_log_console',
                    log_type: logType,
                    severity: severity,
                    message: String(message).substring(0, 5000),
                    context: context ? JSON.stringify(context) : null,
                    security: nonce
                },
                error: function() {}
            });
        }

        $(document).on('click', 'button, .btn, a.btn, input[type="submit"], input[type="button"]', function() {
            if (!loggingSettings.enable_user_logging || !loggingSettings.log_button_clicks) return;
            var $btn = $(this);
            var buttonId = $btn.attr('id') || $btn.data('id') || '';
            var buttonText = ($btn.text() || '').trim().substring(0, 200) || $btn.attr('title') || $btn.attr('aria-label') || '';
            var buttonClass = $btn.attr('class') || '';
            var action = $btn.data('action') || '';
            if ($btn.is('a') && !$btn.hasClass('btn')) return;
            logUserAction('button_click', 'Button clicked: ' + (buttonText || buttonId || buttonClass), null, null, {
                button_id: buttonId,
                button_text: buttonText,
                button_class: buttonClass,
                action: action,
                page_url: window.location.href
            });
        });

        $(document).on('submit', 'form', function() {
            if (!loggingSettings.enable_user_logging || !loggingSettings.log_form_submissions) return;
            var $form = $(this);
            var formId = $form.attr('id') || '';
            var formAction = $form.attr('action') || '';
            logUserAction('form_submit', 'Form submitted: ' + (formId || formAction), null, null, {
                form_id: formId,
                form_action: formAction,
                page_url: window.location.href
            });
        });

        var originalAjax = $.ajax;
        originalAjaxForLogging = originalAjax;
        $.ajax = function(options) {
            var isLoggingAction = false;
            if (options.data) {
                if (options.data instanceof FormData) {
                    isLoggingAction = options.data.get && (options.data.get('action') === 'webinocrm_log_user_action' || options.data.get('action') === 'webinocrm_log_console');
                } else if (typeof options.data === 'object') {
                    isLoggingAction = (options.data.action === 'webinocrm_log_user_action' || options.data.action === 'webinocrm_log_console');
                } else if (typeof options.data === 'string') {
                    isLoggingAction = options.data.indexOf('webinocrm_log_user_action') !== -1 || options.data.indexOf('webinocrm_log_console') !== -1;
                }
            }
            if (!isLoggingAction && loggingSettings.enable_user_logging && loggingSettings.log_ajax_calls && options.url && options.url.indexOf('admin-ajax.php') !== -1) {
                var action = '';
                if (options.data) {
                    if (options.data instanceof FormData && options.data.get) {
                        action = options.data.get('action') || '';
                    } else if (typeof options.data === 'object') {
                        action = options.data.action || '';
                    } else if (typeof options.data === 'string') {
                        var match = options.data.match(/action=([^&]+)/);
                        action = match ? decodeURIComponent(match[1].replace(/\+/g, ' ')) : '';
                    }
                }
                logUserAction('ajax_call', 'AJAX call: ' + action, null, null, { action: action, url: options.url, page_url: window.location.href });
            }
            return originalAjax.apply(this, arguments);
        };

        function logUserAction(actionType, description, targetType, targetId, metadata) {
            if (!ajaxUrl) return;
            if (!loggingSettings.enable_user_logging) return;
            originalAjax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'webinocrm_log_user_action',
                    action_type: actionType,
                    description: String(description).substring(0, 500),
                    target_type: targetType,
                    target_id: targetId || '',
                    metadata: metadata ? JSON.stringify(metadata) : null,
                    security: nonce
                },
                error: function() {}
            });
        }

        if (loggingSettings.enable_user_logging && loggingSettings.log_page_views && window.location.pathname.indexOf('/dashboard') !== -1) {
            var pageName = window.location.pathname.split('/').filter(Boolean).pop() || 'home';
            logUserAction('page_view', 'Viewed page: ' + pageName, null, null, { page: pageName, full_url: window.location.href });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLoggingTracker);
    } else {
        initLoggingTracker();
    }
})();
