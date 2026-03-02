/* global jQuery, window, document */

(function ($) {
    'use strict';

    function getConfig() {
        return (typeof window.intersoccer_checkout !== 'undefined' && window.intersoccer_checkout)
            ? window.intersoccer_checkout
            : null;
    }

    function initCheckoutHandlers() {
        const config = getConfig();
        if (!config) {
            return;
        }

        const $referralInput = $('#intersoccer_referral_code');
        const $referralButton = $('#apply_referral_code');
        const $referralMessage = $('#referral_code_message');

        function applyReferralCode() {
            const referralCode = ($referralInput.val() || '').trim();
            const $button = $referralButton;
            const $message = $referralMessage;

            if (!referralCode) {
                $message.removeClass('success').addClass('error').html(config.i18n.please_enter_referral_code).show();
                return;
            }

            $('input#coach_referral_code').val(referralCode);

            $button.prop('disabled', true).text(config.i18n.applying);

            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'apply_referral_code',
                    referral_code: referralCode,
                    nonce: config.nonce
                }
            }).done(function (response) {
                if (response && response.success) {
                    let appliedMessage = response.data && response.data.message ? response.data.message : '';
                    if (response.data && typeof response.data.discount_amount !== 'undefined') {
                        const discountValue = parseFloat(response.data.discount_amount);
                        if (!isNaN(discountValue)) {
                            appliedMessage += ' ' + config.i18n.discount_label + ' CHF ' + discountValue.toFixed(2);
                        }
                    }

                    $message.removeClass('error').addClass('success').html(appliedMessage).show();
                    $referralInput.prop('disabled', true).data('code-applied', 'yes');
                    $button.prop('disabled', true).text(config.i18n.applied).data('auto-apply', 'no');
                    $message.attr('data-applied', 'yes');
                    $('input#coach_referral_code').val(referralCode);
                    $(document.body).trigger('update_checkout');
                } else {
                    const msg = response && response.data && response.data.message
                        ? response.data.message
                        : config.i18n.error_applying_referral_code;
                    $message.removeClass('success').addClass('error').html(msg).show();
                    $button.prop('disabled', false).text(config.i18n.apply_code);
                }
            }).fail(function () {
                $message.removeClass('success').addClass('error').html(config.i18n.error_applying_referral_code).show();
                $button.prop('disabled', false).text(config.i18n.apply_code);
            });
        }

        function applyPointsAmount(pointsAmount) {
            const availablePoints = parseInt(config.available_points, 10) || 0;
            let amount = parseInt(pointsAmount, 10) || 0;

            if (amount < 0) amount = 0;
            if (amount > availablePoints) amount = availablePoints;

            $('#intersoccer_points_to_redeem').val(amount);

            const $appliedAmount = $('.applied-amount');
            const $appliedText = $('.applied-text');

            if (amount > 0) {
                $appliedText.text(config.i18n.applied + ' ' + amount + ' ' + config.i18n.points_discount);
                $appliedAmount.show();
            } else {
                $appliedAmount.hide();
            }

            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'update_points_session',
                    points_to_redeem: amount,
                    nonce: config.nonce
                }
            }).done(function (response) {
                if (response && response.success) {
                    $(document.body).trigger('update_checkout');
                }
            });
        }

        // Referral code state on load
        if ($referralInput.length && $referralButton.length) {
            const isApplied = $referralInput.data('code-applied') === 'yes' || ($referralMessage.data('applied') === 'yes');

            if (isApplied) {
                $referralInput.prop('disabled', true);
                $referralButton.prop('disabled', true).text(config.i18n.applied);
                if ($referralMessage.length) {
                    $referralMessage.removeClass('error').addClass('success');
                    const statusMessage = $referralMessage.data('statusMessage');
                    if (statusMessage) {
                        $referralMessage.html(statusMessage).show();
                    } else {
                        $referralMessage.show();
                    }
                }
            } else {
                const statusMessagePrefill = $referralMessage.data('statusMessage');
                if (statusMessagePrefill) {
                    $referralMessage.removeClass('error').addClass('success').html(statusMessagePrefill).show();
                }
            }
        }

        $(document).off('click', '#apply_referral_code').on('click', '#apply_referral_code', function (event) {
            event.preventDefault();
            applyReferralCode();
        });

        $(document).off('click', '#change_referral_code').on('click', '#change_referral_code', function (event) {
            event.preventDefault();
            const $changeBtn = $(this);
            $changeBtn.prop('disabled', true).text(config.i18n.clearing || 'Clearing\u2026');

            $.ajax({
                url: config.ajax_url,
                type: 'POST',
                data: {
                    action: 'intersoccer_clear_referral_code',
                    nonce: config.nonce
                }
            }).done(function (response) {
                if (response && response.success) {
                    $referralInput.val('').prop('disabled', false).data('code-applied', 'no');
                    $referralButton.prop('disabled', false).text(config.i18n.apply_code);
                    $referralMessage.removeClass('success error').hide().removeAttr('data-applied');
                    $changeBtn.remove();
                    $(document.body).trigger('update_checkout');
                } else {
                    const msg = response && response.data && response.data.message
                        ? response.data.message : 'Error clearing referral code.';
                    $changeBtn.prop('disabled', false).text(config.i18n.change_code || 'Change Code');
                    window.alert(msg);
                }
            }).fail(function () {
                $changeBtn.prop('disabled', false).text(config.i18n.change_code || 'Change Code');
                window.alert('Network error. Please try again.');
            });
        });

        // Auto-apply referral code if requested
        if ($referralInput.length && $referralButton.length) {
            const shouldAutoApply = $referralButton.data('auto-apply') === 'yes';
            const existingCode = ($referralInput.val() || '').trim();
            const alreadyTriggered = $referralButton.data('autoTriggered');
            const isAlreadyApplied = $referralInput.data('code-applied') === 'yes';

            if (!isAlreadyApplied && shouldAutoApply && existingCode && !alreadyTriggered) {
                $referralButton.data('autoTriggered', true);
                applyReferralCode();
            }
        }

        // Points interactions
        $(document).off('change', '#intersoccer_use_points').on('change', '#intersoccer_use_points', function () {
            const $pointsDetails = $(this).closest('.intersoccer-points-redemption').find('.points-details');
            if ($(this).is(':checked')) {
                $pointsDetails.slideDown();
            } else {
                $pointsDetails.slideUp();
                applyPointsAmount(0);
            }
        });

        $(document).off('click', '.apply-all-points').on('click', '.apply-all-points', function () {
            applyPointsAmount(config.available_points);
        });

        $(document).off('input', '#intersoccer_points_to_redeem').on('input', '#intersoccer_points_to_redeem', function () {
            applyPointsAmount($(this).val());
        });
    }

    $(document).ready(function () {
        initCheckoutHandlers();
    });

    $(document.body).on('updated_checkout', function () {
        initCheckoutHandlers();
    });

    // Legacy credits slider (if enabled/used)
    $(document).on('input', '#credit-slider', function() {
        $('#credit-display').text(this.value + ' CHF');
    });

    $(document).on('click', '#apply-max-credits', function() {
        const $slider = $('#credit-slider');
        const max = $slider.attr('max');
        if (typeof max !== 'undefined') {
            $slider.val(max).trigger('input');
        }
    });

})(jQuery);

