/**
 * Dark Mode Handler for WebinoCRM
 * Manages dark/light theme switching with localStorage persistence
 */

(function($) {
    'use strict';

    // Legacy bridge: keep API surface but delegate all theme state
    // to WebinoThemeSwitcher (single canonical source).
    const DarkModeBridge = {
        getCurrentTheme() {
            if (window.WebinoThemeSwitcher && typeof window.WebinoThemeSwitcher.getCurrentTheme === 'function') {
                return window.WebinoThemeSwitcher.getCurrentTheme();
            }
            return document.documentElement.getAttribute('data-theme') || 'light';
        },
        setTheme(theme) {
            if (window.WebinoThemeSwitcher && typeof window.WebinoThemeSwitcher.applyTheme === 'function') {
                window.WebinoThemeSwitcher.applyTheme(theme);
            }
        },
        toggleTheme() {
            if (window.WebinoThemeSwitcher && typeof window.WebinoThemeSwitcher.toggleTheme === 'function') {
                window.WebinoThemeSwitcher.toggleTheme();
            }
        }
    };

    $(document).ready(function() {
        window.WebinoDarkMode = DarkModeBridge;
    });

    $.fn.webinoDarkMode = function(action, ...args) {
        if (window.WebinoDarkMode && typeof action === 'string' && typeof window.WebinoDarkMode[action] === 'function') {
            return window.WebinoDarkMode[action](...args);
        }
        return this;
    };
})(jQuery);

