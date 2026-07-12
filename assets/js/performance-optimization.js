/**
 * Performance Optimization Script
 * 
 * Handles lazy loading, debouncing, and other performance optimizations
 */

(function($) {
    'use strict';

    // Performance optimization object
    const PerformanceOptimizer = {
        
        // Debounce function for search inputs
        debounce: function(func, wait, immediate) {
            let timeout;
            return function() {
                const context = this;
                const args = arguments;
                const later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        },

        // Throttle function for scroll events
        throttle: function(func, limit) {
            let inThrottle;
            return function() {
                const args = arguments;
                const context = this;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(() => inThrottle = false, limit);
                }
            };
        },

        // Lazy load images
        lazyLoadImages: function() {
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    });
                });

                document.querySelectorAll('img[data-src]').forEach(img => {
                    imageObserver.observe(img);
                });
            }
        },

        // Optimize AJAX requests
        optimizeAjax: function() {
            // Cache AJAX responses
            const ajaxCache = new Map();
            
            // Override jQuery AJAX to add caching
            const originalAjax = $.ajax;
            $.ajax = function(options) {
                // Only cache GET requests
                if (options.type === 'GET' && options.cache !== false) {
                    const cacheKey = JSON.stringify(options);
                    
                    if (ajaxCache.has(cacheKey)) {
                        const cachedResponse = ajaxCache.get(cacheKey);
                        if (Date.now() - cachedResponse.timestamp < 300000) { // 5 minutes
                            if (options.success) {
                                options.success(cachedResponse.data);
                            }
                            return;
                        }
                    }
                    
                    // Store original success callback
                    const originalSuccess = options.success;
                    options.success = function(data) {
                        // Cache the response
                        ajaxCache.set(cacheKey, {
                            data: data,
                            timestamp: Date.now()
                        });
                        
                        // Call original success callback
                        if (originalSuccess) {
                            originalSuccess(data);
                        }
                    };
                }
                
                return originalAjax.call(this, options);
            };
        },

        // Optimize form submissions
        optimizeForms: function() {
            // Debounce search inputs
            $('input[type="search"], input[data-search]').on('input', this.debounce(function() {
                const $this = $(this);
                const searchValue = $this.val();
                
                // Trigger search with debounce
                $this.trigger('search:debounced', [searchValue]);
            }, 300));

            // Prevent double form submissions
            $('form').on('submit', function() {
                const $form = $(this);
                const $submitBtn = $form.find('button[type="submit"], input[type="submit"]');
                
                if ($form.data('submitting')) {
                    return false;
                }
                
                $form.data('submitting', true);
                $submitBtn.prop('disabled', true);
                
                // Re-enable after 3 seconds
                setTimeout(() => {
                    $form.data('submitting', false);
                    $submitBtn.prop('disabled', false);
                }, 3000);
            });
        },

        // Optimize scroll events
        optimizeScroll: function() {
            let scrollTimeout;
            
            $(window).on('scroll', this.throttle(function() {
                // Handle scroll events with throttle
                $(document).trigger('scroll:optimized');
                
                // Clear timeout for scroll end detection
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(() => {
                    $(document).trigger('scroll:end');
                }, 150);
            }, 100));
        },

        // Optimize resize events
        optimizeResize: function() {
            $(window).on('resize', this.debounce(function() {
                $(document).trigger('resize:optimized');
            }, 250));
        },

        // Preload critical resources
        preloadResources: function() {
            // Preload critical CSS
            const criticalCSS = [
                'assets/css/webinocrm-styles.css',
                'assets/css/dark-mode.css'
            ];
            
            criticalCSS.forEach(css => {
                const link = document.createElement('link');
                link.rel = 'preload';
                link.href = webinocrm_ajax_obj.plugin_url + css;
                link.as = 'style';
                link.onload = function() {
                    this.rel = 'stylesheet';
                };
                document.head.appendChild(link);
            });
        },

        // Optimize animations
        optimizeAnimations: function() {
            // Reduce motion for users who prefer it
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                document.documentElement.style.setProperty('--animation-duration', '0.01ms');
                document.documentElement.style.setProperty('--animation-iteration-count', '1');
            }
        },

        // Initialize performance optimizations
        init: function() {
            this.lazyLoadImages();
            this.optimizeAjax();
            this.optimizeForms();
            this.optimizeScroll();
            this.optimizeResize();
            this.preloadResources();
            this.optimizeAnimations();
            
            console.log('🚀 Performance optimizations loaded');
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        PerformanceOptimizer.init();
    });

    // Expose to global scope for external use
    window.WebinoCRM_Performance = PerformanceOptimizer;

})(jQuery);