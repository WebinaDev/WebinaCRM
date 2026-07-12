/**
 * Forms Enhancement Script
 * Adds functionality to all forms across the plugin
 * @package WebinoCRM
 */

jQuery(document).ready(function($) {
    
    /**
     * Initialize Jalali Date Pickers
     */
    if (typeof $.fn.persianDatepicker !== 'undefined') {
        $('.webino-jalali-date-picker, input[type="text"][name*="date"]').each(function() {
            if (!$(this).hasClass('webino-datepicker-initialized')) {
                $(this).persianDatepicker({
                    format: 'YYYY/MM/DD',
                    autoClose: true,
                    calendar: {
                        persian: {
                            locale: 'fa'
                        }
                    },
                    navigator: {
                        enabled: true
                    },
                    toolbox: {
                        enabled: true,
                        calendarSwitch: {
                            enabled: false
                        }
                    }
                });
                $(this).addClass('webino-datepicker-initialized');
            }
        });
    }

    /**
     * Initialize File Upload Previews
     */
    $('input[type="file"]').on('change', function() {
        const file = this.files[0];
        const $input = $(this);
        const $preview = $input.siblings('.file-preview');
        
        if (file) {
            const fileName = file.name;
            const fileSize = (file.size / 1024 / 1024).toFixed(2); // MB
            
            // Show file info
            let fileInfo = `<div class="alert alert-info mt-2">
                <i class="ri-file-line me-2"></i>
                <strong>${fileName}</strong> (${fileSize} MB)
            </div>`;
            
            if ($preview.length) {
                $preview.html(fileInfo);
            } else {
                $input.after(fileInfo);
            }
            
            // Image preview
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const imgPreview = `<div class="image-preview mt-2">
                        <img src="${e.target.result}" style="max-width: 200px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    </div>`;
                    $input.siblings('.file-preview, .alert').last().after(imgPreview);
                };
                reader.readAsDataURL(file);
            }
        }
    });

    /**
     * Form Validation Enhancement
     */
    $('form.webino-form, form.webino-ajax-form').each(function() {
        const $form = $(this);
        
        // Add required field indicators
        $form.find('input[required], select[required], textarea[required]').each(function() {
            const $field = $(this);
            const $label = $('label[for="' + $field.attr('id') + '"]');
            
            if ($label.length && !$label.find('.required-indicator').length) {
                $label.append(' <span class="required-indicator text-danger">*</span>');
            }
        });
        
        // Real-time validation
        $form.find('input, select, textarea').on('blur', function() {
            const $field = $(this);
            
            if ($field.attr('required') && !$field.val()) {
                $field.addClass('is-invalid');
                
                if (!$field.siblings('.invalid-feedback').length) {
                    $field.after('<div class="invalid-feedback d-block">این فیلد الزامی است.</div>');
                }
            } else {
                $field.removeClass('is-invalid');
                $field.siblings('.invalid-feedback').remove();
            }
        });
    });

    /**
     * Number Formatting for Price Fields
     */
    $('.item-price, .item-discount, #total_amount, input[name*="amount"]').on('blur', function() {
        let value = $(this).val().replace(/,/g, '');
        if (!isNaN(value) && value !== '') {
            $(this).val(Number(value).toLocaleString('en-US'));
        }
    });

    /**
     * Auto-save Draft (for long forms)
     */
    let autoSaveTimer;
    $('.webino-ajax-form').on('input', 'input, textarea, select', function() {
        const $form = $(this).closest('form');
        const formId = $form.attr('id');
        
        if (!formId) return;
        
        clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(function() {
            // Save to localStorage
            const formData = {};
            $form.find('input, textarea, select').each(function() {
                const $field = $(this);
                const name = $field.attr('name');
                if (name && name !== 'security') {
                    formData[name] = $field.val();
                }
            });
            
            localStorage.setItem('webino_draft_' + formId, JSON.stringify(formData));
            
            // Show saved indicator
            if (!$('.autosave-indicator').length) {
                $form.prepend('<div class="autosave-indicator alert alert-success alert-dismissible fade show"><i class="ri-save-line me-2"></i>پیش‌نویس ذخیره شد<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
            }
            
            setTimeout(function() {
                $('.autosave-indicator').fadeOut(function() { $(this).remove(); });
            }, 2000);
        }, 3000);
    });

    /**
     * Restore Draft on Page Load
     */
    $('.webino-ajax-form').each(function() {
        const $form = $(this);
        const formId = $form.attr('id');
        
        if (!formId) return;
        
        const savedData = localStorage.getItem('webino_draft_' + formId);
        if (savedData) {
            const confirmation = confirm('یک پیش‌نویس ذخیره شده برای این فرم وجود دارد. می‌خواهید بازیابی کنید؟');
            
            if (confirmation) {
                const formData = JSON.parse(savedData);
                
                Object.keys(formData).forEach(function(name) {
                    const $field = $form.find('[name="' + name + '"]');
                    if ($field.length) {
                        $field.val(formData[name]);
                    }
                });
                
                Swal.fire({
                    icon: 'success',
                    title: 'بازیابی شد',
                    text: 'پیش‌نویس فرم بازیابی شد',
                    showConfirmButton: false,
                    timer: 1500
                });
            } else {
                localStorage.removeItem('webino_draft_' + formId);
            }
        }
    });

    /**
     * Clear Draft on Successful Submit
     */
    $(document).on('submit', '.webino-ajax-form', function() {
        const formId = $(this).attr('id');
        if (formId) {
            localStorage.removeItem('webino_draft_' + formId);
        }
    });

    /**
     * Confirm Before Delete
     */
    $('.delete-btn, .webino-delete-btn, [data-action*="delete"]').on('click', function(e) {
        if (!$(this).data('confirmed')) {
            e.preventDefault();
            const $btn = $(this);
            
            Swal.fire({
                title: 'آیا مطمئن هستید؟',
                text: 'این عملیات غیرقابل بازگشت است!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'بله، حذف کن',
                cancelButtonText: 'انصراف'
            }).then((result) => {
                if (result.isConfirmed) {
                    $btn.data('confirmed', true);
                    $btn.trigger('click');
                }
            });
        }
    });

    /**
     * Loading State for Buttons
     * Skip buttons with data-webino-skip-global-handler attribute
     */
    $(document).on('click', '.btn[type="submit"], button[type="submit"]', function() {
        const $btn = $(this);
        
        // Skip buttons that have the skip flag (for custom AJAX handlers)
        if ($btn.data('webino-skip-global-handler') || $btn.attr('data-webino-skip-global-handler')) {
            return;
        }
        
        if (!$btn.data('loading')) {
            $btn.data('loading', true);
            $btn.data('original-html', $btn.html());
            
            $btn.html('<span class="spinner-border spinner-border-sm me-2" role="status"></span>در حال ارسال...');
            $btn.prop('disabled', true);
            
            // Reset after 10 seconds (failsafe)
            setTimeout(function() {
                if ($btn.data('loading')) {
                    $btn.html($btn.data('original-html'));
                    $btn.prop('disabled', false);
                    $btn.data('loading', false);
                }
            }, 10000);
        }
    });

    /**
     * Reset Button State After AJAX
     */
    $(document).ajaxComplete(function() {
        $('.btn[type="submit"], button[type="submit"]').each(function() {
            const $btn = $(this);
            if ($btn.data('loading')) {
                $btn.html($btn.data('original-html'));
                $btn.prop('disabled', false);
                $btn.data('loading', false);
            }
        });
    });

    /**
     * Textarea Auto-resize
     */
    $('textarea').on('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });

    /**
     * Copy to Clipboard
     */
    $('.copy-to-clipboard').on('click', function(e) {
        e.preventDefault();
        const text = $(this).data('text');
        
        navigator.clipboard.writeText(text).then(function() {
            Swal.fire({
                icon: 'success',
                title: 'کپی شد!',
                showConfirmButton: false,
                timer: 1000
            });
        });
    });

    /**
     * Tooltips Initialization
     */
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    /**
     * Popovers Initialization
     */
    if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
        const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });
    }

    /**
     * Smooth Scroll to Error
     */
    if ($('.is-invalid').length || $('.error').length) {
        $('html, body').animate({
            scrollTop: $('.is-invalid, .error').first().offset().top - 100
        }, 500);
    }
});

