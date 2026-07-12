/**
 * WebinoCRM Login Page Scripts
 * Handles SMS OTP and password login
 */

(function($) {
    'use strict';

    let otpTimer = null;
    let otpTimeRemaining = 300; // 5 minutes in seconds

    $(document).ready(function() {
        initLoginPage();
    });

    function initLoginPage() {
        // Tab switching
        $('.webino-tab-btn').on('click', function() {
            const tab = $(this).data('tab');
            switchTab(tab);
        });

        // Send OTP
        $('#webino-send-otp-btn').on('click', function(e) {
            e.preventDefault();
            sendOTP();
        });

        // Verify OTP
        $('#webino-otp-form').on('submit', function(e) {
            e.preventDefault();
            verifyOTP();
        });

        // Resend OTP
        $('#webino-resend-otp-btn').on('click', function(e) {
            e.preventDefault();
            sendOTP();
        });

        // Change phone number
        $('#webino-change-phone-btn').on('click', function(e) {
            e.preventDefault();
            goToStep(1);
            $('#otp_code').val('');
            stopTimer();
        });

        // Set password
        $('#webino-set-password-btn').on('click', function(e) {
            e.preventDefault();
            setPassword();
        });

        // Back to OTP
        $('#webino-back-to-otp-btn').on('click', function(e) {
            e.preventDefault();
            goToStep(2);
        });

        // Password login
        $('#webino-password-form').on('submit', function(e) {
            e.preventDefault();
            console.log('Login form submitted'); // Debug
            loginWithPassword();
        });
        
        // Also add click handler for the button
        $('#webino-password-form button[type="submit"]').on('click', function(e) {
            e.preventDefault();
            console.log('Login button clicked'); // Debug
            loginWithPassword();
        });

        // Toggle password visibility
        $('.webino-toggle-password').on('click', function() {
            togglePasswordVisibility($(this));
        });

        // Auto-format phone number input
        $('#phone_number').on('input', function() {
            formatPhoneNumber($(this));
        });

        // Auto-format OTP input
        $('#otp_code').on('input', function() {
            formatOTPCode($(this));
            
            // Auto-verify if OTP is complete
            const otpCode = $(this).val().trim();
            
            if (otpCode && otpCode.length >= 4 && /^[0-9]+$/.test(otpCode)) {
                // Auto verify after a short delay
                setTimeout(function() {
                    verifyOTP();
                }, 1000);
            }
        });

        // Enter key handling for OTP input
        $('#otp_code').on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#webino-verify-otp-btn').click();
            }
        });
        
        // Paste event for OTP auto-detection
        $('#otp_code').on('paste', function(e) {
            setTimeout(function() {
                const pastedText = $('#otp_code').val().trim();
                
                if (pastedText) {
                    // Extract OTP from pasted text (like SMS content)
                    const otpCode = extractOTPFromText(pastedText);
                    if (otpCode && otpCode.length >= 4 && /^[0-9]+$/.test(otpCode)) {
                        $('#otp_code').val(otpCode);
                        // Auto verify if it looks like a valid OTP
                        setTimeout(function() {
                            verifyOTP();
                        }, 500);
                    }
                }
            }, 100);
        });
    }

    function switchTab(tab) {
        $('.webino-tab-btn').removeClass('active');
        $(`.webino-tab-btn[data-tab="${tab}"]`).addClass('active');

        $('.webino-login-form-container').hide();
        if (tab === 'sms') {
            $('#sms-login-form').show();
        } else {
            $('#password-login-form').show();
        }

        // Reset forms
        resetForms();
    }

    function sendOTP() {
        const $btn = $('#webino-send-otp-btn, #webino-resend-otp-btn');
        const phoneNumber = $('#phone_number').val().trim();

        if (!phoneNumber) {
            showError('لطفاً شماره موبایل خود را وارد کنید.');
            return;
        }

        if (!validatePhoneNumber(phoneNumber)) {
            showError('فرمت شماره موبایل صحیح نیست.');
            return;
        }

        // Disable button and show loading
        $btn.prop('disabled', true).addClass('loading');
        const originalText = $btn.html();
        $btn.html('<i class="fas fa-spinner fa-spin"></i> در حال ارسال...');

        $.ajax({
            url: webinocrm_ajax_obj.ajax_url,
            type: 'POST',
            data: {
                action: 'webino_send_login_otp',
                security: webinocrm_ajax_obj.nonce,
                phone_number: phoneNumber,
                auto_login: true
            },
            success: function(response) {
                if (response.success) {
                    // Immediately hide step 1 and show step 2
                    $('#step-phone').hide();
                    $('#step-otp').show();
                    
                    // Start timer
                    startTimer(response.data.expires_in || 300);
                    
                    // Focus on OTP input
                    $('#otp_code').focus();
                    
                    // Start clipboard monitoring
                    startClipboardMonitoring();
                } else {
                    showError(response.data.message || 'خطا در ارسال کد تایید');
                }
            },
            error: function() {
                showError('خطا در ارتباط با سرور. لطفاً مجدداً تلاش کنید.');
            },
            complete: function() {
                $btn.prop('disabled', false).removeClass('loading');
                $btn.html(originalText);
            }
        });
    }


    function verifyOTP() {
        const $btn = $('#webino-verify-otp-btn');
        const phoneNumber = $('#phone_number').val().trim();
        const otpCode = $('#otp_code').val().trim();

        if (!otpCode) {
            showError('لطفاً کد تایید را وارد کنید.');
            return;
        }

        if (otpCode.length !== 6) {
            showError('کد تایید باید 6 رقم باشد.');
            return;
        }

        // Disable button and show loading
        $btn.prop('disabled', true).addClass('loading');
        const originalText = $btn.html();
        $btn.html('<i class="fas fa-spinner fa-spin"></i> در حال ورود...');

        $.ajax({
            url: webinocrm_ajax_obj.ajax_url,
            type: 'POST',
            data: {
                action: 'webino_verify_login_otp',
                security: webinocrm_ajax_obj.nonce,
                phone_number: phoneNumber,
                otp_code: otpCode
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data.message);
                    stopTimer();
                    
                    // Check if user needs to set password
                    if (response.data.needs_password) {
                        goToStep(3);
                        $('#new_password').focus();
                    } else {
                        // Redirect after 1 second
                        setTimeout(function() {
                            window.location.href = response.data.redirect_url;
                        }, 1000);
                    }
                } else {
                    showError(response.data.message || 'کد تایید اشتباه است');
                    $('#otp_code').val('').focus();
                }
            },
            error: function() {
                showError('خطا در ارتباط با سرور. لطفاً مجدداً تلاش کنید.');
            },
            complete: function() {
                $btn.prop('disabled', false).removeClass('loading');
                $btn.html(originalText);
            }
        });
    }

    function loginWithPassword() {
        const $btn = $('#webino-password-form button[type="submit"]');
        const username = $('#username').val().trim();
        const password = $('#password').val();
        const remember = $('#remember').is(':checked');

        if (!username || !password) {
            showError('لطفاً تمام فیلدها را پر کنید.');
            return;
        }

        // Disable button and show loading
        $btn.prop('disabled', true).addClass('loading');
        const originalText = $btn.html();
        $btn.html('<i class="fas fa-spinner fa-spin"></i> در حال ورود...');

        $.ajax({
            url: webinocrm_ajax_obj.ajax_url,
            type: 'POST',
            data: {
                action: 'crm_login_with_password',
                security: webinocrm_ajax_obj.nonce,
                username: username,
                password: password,
                remember: remember
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data.message);
                    
                    // Redirect after 1 second
                    setTimeout(function() {
                        window.location.href = response.data.redirect_url;
                    }, 1000);
                } else {
                    showError(response.data.message || 'نام کاربری یا رمز عبور اشتباه است');
                }
            },
            error: function() {
                showError('خطا در ارتباط با سرور. لطفاً مجدداً تلاش کنید.');
            },
            complete: function() {
                $btn.prop('disabled', false).removeClass('loading');
                $btn.html(originalText);
            }
        });
    }

    function goToStep(step) {
        $('.otp-step').hide();
        
        if (step === 1) {
            $('#step-phone').show();
        } else if (step === 2) {
            $('#step-otp').show();
        } else if (step === 3) {
            $('#step-password').show();
        }
    }

    function startTimer(seconds) {
        otpTimeRemaining = seconds;
        updateTimerDisplay();
        
        if (otpTimer) {
            clearInterval(otpTimer);
        }

        otpTimer = setInterval(function() {
            otpTimeRemaining--;
            updateTimerDisplay();

            if (otpTimeRemaining <= 0) {
                stopTimer();
                showWarning('کد تایید منقضی شده است. لطفاً کد جدید درخواست کنید.');
                $('#webino-resend-otp-btn').show();
            }
        }, 1000);

        $('#webino-resend-otp-btn').hide();
    }

    function stopTimer() {
        if (otpTimer) {
            clearInterval(otpTimer);
            otpTimer = null;
        }
    }

    function updateTimerDisplay() {
        const minutes = Math.floor(otpTimeRemaining / 60);
        const seconds = otpTimeRemaining % 60;
        const display = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        
        // Convert to Persian numerals if needed
        const persianDisplay = convertToPersianNumerals(display);
        $('#webino-timer').text(persianDisplay);

        if (otpTimeRemaining <= 30) {
            $('#webino-timer').addClass('warning');
        } else {
            $('#webino-timer').removeClass('warning');
        }
    }

    function validatePhoneNumber(phone) {
        // Support both English and Persian numerals
        const pattern = /^(09|۰۹)[0-9۰-۹]{9}$/;
        return pattern.test(phone);
    }

    function formatPhoneNumber($input) {
        let value = $input.val();
        // Convert Persian/Arabic numerals to English
        value = convertToEnglishNumerals(value);
        // Remove non-numeric characters
        value = value.replace(/[^0-9]/g, '');
        // Limit to 11 digits
        value = value.substring(0, 11);
        $input.val(value);
    }

    function formatOTPCode($input) {
        let value = $input.val();
        // Convert Persian/Arabic numerals to English
        value = convertToEnglishNumerals(value);
        // Remove non-numeric characters
        value = value.replace(/[^0-9]/g, '');
        // Limit to 6 digits
        value = value.substring(0, 6);
        $input.val(value);
    }

    function togglePasswordVisibility($btn) {
        const targetId = $btn.data('target');
        const $input = $(`#${targetId}`);
        const $icon = $btn.find('i');

        if ($input.attr('type') === 'password') {
            $input.attr('type', 'text');
            $icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            $input.attr('type', 'password');
            $icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    }

    function setPassword() {
        const $btn = $('#webino-set-password-btn');
        const phoneNumber = $('#phone_number').val().trim();
        const password = $('#new_password').val();
        const confirmPassword = $('#confirm_password').val();

        if (!password || !confirmPassword) {
            showError('لطفاً تمام فیلدها را پر کنید.');
            return;
        }

        if (password !== confirmPassword) {
            showError('رمز عبور و تکرار آن یکسان نیست.');
            return;
        }

        if (password.length < 6) {
            showError('رمز عبور باید حداقل 6 کاراکتر باشد.');
            return;
        }

        // Disable button and show loading
        $btn.prop('disabled', true).addClass('loading');
        const originalText = $btn.html();
        $btn.html('<i class="fas fa-spinner fa-spin"></i> در حال تنظیم...');

        $.ajax({
            url: webinocrm_ajax_obj.ajax_url,
            type: 'POST',
            data: {
                action: 'webino_set_password',
                security: webinocrm_ajax_obj.nonce,
                phone_number: phoneNumber,
                password: password,
                confirm_password: confirmPassword
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data.message);
                    
                    // Redirect after 1 second
                    setTimeout(function() {
                        window.location.href = response.data.redirect_url;
                    }, 1000);
                } else {
                    showError(response.data.message || 'خطا در تنظیم رمز عبور');
                }
            },
            error: function() {
                showError('خطا در ارتباط با سرور. لطفاً مجدداً تلاش کنید.');
            },
            complete: function() {
                $btn.prop('disabled', false).removeClass('loading');
                $btn.html(originalText);
            }
        });
    }

    function resetForms() {
        $('#webino-otp-form')[0].reset();
        $('#webino-password-form')[0].reset();
        goToStep(1);
        stopTimer();
    }

    function convertToEnglishNumerals(str) {
        const persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        const arabicNumbers = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        
        for (let i = 0; i < 10; i++) {
            str = str.replace(new RegExp(persianNumbers[i], 'g'), i);
            str = str.replace(new RegExp(arabicNumbers[i], 'g'), i);
        }
        
        return str;
    }

    function convertToPersianNumerals(str) {
        const persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        return str.replace(/[0-9]/g, function(w) {
            return persianNumbers[+w];
        });
    }

    function showSuccess(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'موفق',
                text: message,
                confirmButtonText: 'باشه',
                timer: 3000
            });
        } else {
            alert(message);
        }
    }
    
    function showSuccessToast(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
        }
    }

    function showError(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'خطا',
                text: message,
                confirmButtonText: 'باشه'
            });
        } else {
            alert(message);
        }
    }

    function showWarning(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'توجه',
                text: message,
                confirmButtonText: 'باشه'
            });
        } else {
            alert(message);
        }
    }
    
    function extractOTPFromText(text) {
        // Remove extra whitespace and normalize
        text = text.replace(/\s+/g, ' ').trim();
        
        // Common OTP patterns in SMS
        const patterns = [
            // Pattern 1: "code: 951862" or "کد: 951862"
            /(?:code|کد)\s*:?\s*(\d{4,8})/i,
            
            // Pattern 2: "#951862" or "951862#"
            /#?(\d{4,8})#?/,
            
            // Pattern 3: "951862" (standalone 4-8 digits)
            /\b(\d{4,8})\b/,
            
            // Pattern 4: "کد تایید: 951862" or "کد ورود: 951862"
            /(?:کد\s*(?:تایید|ورود|یکبار\s*مصرف))\s*:?\s*(\d{4,8})/i,
            
            // Pattern 5: "verification code: 951862"
            /(?:verification\s*code|کد\s*تایید)\s*:?\s*(\d{4,8})/i
        ];
        
        for (let pattern of patterns) {
            const match = text.match(pattern);
            if (match && match[1]) {
                return match[1];
            }
        }
        
        // If no pattern matches, try to find any 4-8 digit number
        const fallbackMatch = text.match(/(\d{4,8})/);
        return fallbackMatch ? fallbackMatch[1] : null;
    }
    
    function startClipboardMonitoring() {
        let attempts = 0;
        const maxAttempts = 30; // 30 seconds
        
        const checkInterval = setInterval(function() {
            attempts++;
            
            // Check if user has pasted something
            const otpCode = $('#otp_code').val().trim();
            if (otpCode && otpCode.length >= 4 && /^[0-9]+$/.test(otpCode)) {
                clearInterval(checkInterval);
                // Auto verify the OTP
                setTimeout(function() {
                    verifyOTP();
                }, 500);
                return;
            }
            
            // Try to read from clipboard if available
            if (navigator.clipboard && navigator.clipboard.readText) {
                navigator.clipboard.readText().then(function(text) {
                    const extractedOTP = extractOTPFromText(text);
                    if (extractedOTP && extractedOTP.length >= 4 && /^[0-9]+$/.test(extractedOTP)) {
                        clearInterval(checkInterval);
                        $('#otp_code').val(extractedOTP);
                        // Auto verify the OTP
                        setTimeout(function() {
                            verifyOTP();
                        }, 500);
                    }
                }).catch(function(error) {
                    // Clipboard access denied or not available
                    console.log('Clipboard access error:', error);
                });
            }
            
            // Stop after max attempts
            if (attempts >= maxAttempts) {
                clearInterval(checkInterval);
            }
        }, 1000);
    }
    

})(jQuery);

