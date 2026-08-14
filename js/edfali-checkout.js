/* Edfali AJAX Checkout JavaScript Controller */

(function($) {
    'use strict';

    var currentOrderId = 0;

    $(document).ready(function() {
        initModalMarkup();
        bindEvents();
    });

    function initModalMarkup() {
        if ($('#edfali-otp-modal').length === 0) {
            var modalHtml = '' +
                '<div id="edfali-otp-modal" class="edfali-modal-overlay">' +
                    '<div class="edfali-modal-card">' +
                        '<div class="edfali-modal-icon-wrapper">⚡</div>' +
                        '<h3 class="edfali-modal-title">تأكيد الدفع عبر إدفع لي</h3>' +
                        '<p class="edfali-modal-desc">' +
                            'أدخل رمز التحقق (SMS Pin) المكون من 4 أرقام الذي تم إرساله لهاتفك:<br/>' +
                            '<span class="edfali-phone-highlight" id="edfali-target-phone"></span>' +
                        '</p>' +
                        '<div class="edfali-error-msg" id="edfali-modal-error"></div>' +
                        '<div class="edfali-otp-inputs">' +
                            '<input type="tel" class="edfali-otp-digit" maxlength="1" data-index="0" autofocus />' +
                            '<input type="tel" class="edfali-otp-digit" maxlength="1" data-index="1" />' +
                            '<input type="tel" class="edfali-otp-digit" maxlength="1" data-index="2" />' +
                            '<input type="tel" class="edfali-otp-digit" maxlength="1" data-index="3" />' +
                        '</div>' +
                        '<button type="button" id="edfali-btn-submit-otp" class="edfali-btn-confirm">' +
                            '<span>تأكيد وسحب المبلغ 💳</span>' +
                        '</button>' +
                        '<button type="button" id="edfali-btn-cancel-modal" class="edfali-btn-cancel">' +
                            'إلغاء العملية ✕' +
                        '</button>' +
                    '</div>' +
                '</div>';
            $('body').append(modalHtml);
        }
    }

    function bindEvents() {
        // Intercept WooCommerce Checkout AJAX Response for Edfali
        $(document).ajaxComplete(function(event, xhr, settings) {
            if (settings.url.indexOf('wc-ajax=checkout') !== -1 || (settings.data && typeof settings.data === 'string' && settings.data.indexOf('action=woocommerce_checkout') !== -1)) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data && data.result === 'success' && data.edfali_ajax) {
                        currentOrderId = data.order_id;
                        $('#edfali-target-phone').text(data.phone);
                        showOtpModal();
                    }
                } catch (e) {
                    // Not JSON or standard redirect
                }
            }
        });

        // OTP inputs auto-tabbing
        $(document).on('keyup', '.edfali-otp-digit', function(e) {
            var $this = $(this);
            var val = $this.val().replace(/\D/g, '');
            $this.val(val);

            if (val.length === 1) {
                var nextIndex = parseInt($this.data('index'), 10) + 1;
                var $next = $('.edfali-otp-digit[data-index="' + nextIndex + '"]');
                if ($next.length) {
                    $next.focus();
                } else {
                    $('#edfali-btn-submit-otp').focus();
                }
            } else if (e.key === 'Backspace' || e.keyCode === 8) {
                var prevIndex = parseInt($this.data('index'), 10) - 1;
                var $prev = $('.edfali-otp-digit[data-index="' + prevIndex + '"]');
                if ($prev.length) {
                    $prev.focus();
                }
            }
        });

        // Paste 4 digits support
        $(document).on('paste', '.edfali-otp-digit', function(e) {
            var clipboardData = e.originalEvent.clipboardData || window.clipboardData;
            var pastedData = clipboardData.getData('text').replace(/\D/g, '');
            if (pastedData.length >= 4) {
                e.preventDefault();
                for (var i = 0; i < 4; i++) {
                    $('.edfali-otp-digit[data-index="' + i + '"]').val(pastedData[i]);
                }
                $('#edfali-btn-submit-otp').focus();
            }
        });

        // Submit OTP Button
        $(document).on('click', '#edfali-btn-submit-otp', function(e) {
            e.preventDefault();
            submitOtpCode();
        });

        // Cancel modal
        $(document).on('click', '#edfali-btn-cancel-modal', function(e) {
            e.preventDefault();
            hideOtpModal();
        });
    }

    function showOtpModal() {
        $('#edfali-modal-error').hide().text('');
        $('.edfali-otp-digit').val('');
        $('#edfali-btn-submit-otp').prop('disabled', false).html('<span>تأكيد وسحب المبلغ 💳</span>');
        $('#edfali-otp-modal').addClass('active');
        setTimeout(function() {
            $('.edfali-otp-digit[data-index="0"]').focus();
        }, 300);
    }

    function hideOtpModal() {
        $('#edfali-otp-modal').removeClass('active');
        if ($.fn.unblock) {
            $('form.checkout').unblock();
        }
    }

    function submitOtpCode() {
        var code = '';
        $('.edfali-otp-digit').each(function() {
            code += $(this).val();
        });

        if (code.length < 4) {
            showModalError(wc_edfali_params.messages.enter_otp);
            return;
        }

        var $btn = $('#edfali-btn-submit-otp');
        $btn.prop('disabled', true).html('<span class="edfali-spinner"></span> <span style="color:#ffffff; font-weight:800;">جاري تأكيد الخصم...</span>');
        $('#edfali-modal-error').hide();

        $.ajax({
            type: 'POST',
            url: wc_edfali_params.ajax_url,
            dataType: 'json',
            data: {
                action: 'wc_edfali_confirm_otp',
                nonce: wc_edfali_params.nonce,
                order_id: currentOrderId,
                otp_code: code
            },
            success: function(res) {
                if (res && res.success) {
                    $btn.html('<span>✅ تم الدفع بنجاح! جاري التوجيه...</span>');
                    setTimeout(function() {
                        window.location.href = res.data.redirect_url;
                    }, 800);
                } else {
                    $btn.prop('disabled', false).html('<span>تأكيد وسحب المبلغ 💳</span>');
                    showModalError(res && res.data && res.data.message ? res.data.message : 'فشل تأكيد الرمز.');
                }
            },
            error: function() {
                $btn.prop('disabled', false).html('<span>تأكيد وسحب المبلغ 💳</span>');
                showModalError(wc_edfali_params.messages.server_error);
            }
        });
    }

    function showModalError(msg) {
        $('#edfali-modal-error').text(msg).fadeIn();
    }

})(jQuery);
