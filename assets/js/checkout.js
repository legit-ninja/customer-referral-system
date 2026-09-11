/* global jQuery, window, document */

/**
 * InterSoccer Checkout Points Redemption & Referral Code Handler
 * 
 * Tess TC-REDEEM Stable Selectors (from main - DO NOT RENAME IDs):
 * 
 * TC-REDEEM-01 (checkout-only redeem):
 * - Root wrapper: .intersoccer-points-redemption-wrapper (via woocommerce_review_order_before_payment)
 * - Inner container: .intersoccer-points-redemption
 * - Enable toggle: #intersoccer_use_points
 * - Details panel: .intersoccer-points-redemption .points-details
 * - Available balance: .points-available [data-field="points_balance"]
 * - Apply all CTA: .apply-all-points
 * - Custom input: #intersoccer_points_to_redeem [data-field="points_to_redeem"]
 * 
 * TC-REDEEM-02 (applied discount confirmation):
 * - Confirmation UI: .applied-amount / #intersoccer-points-applied [data-field="points_applied_confirm"]
 * - Confirmation text: .applied-text
 * - Order summary fee: "Referral Credits Discount" (from apply_points_discount_as_fee)
 * - AJAX: action=update_points_session
 * - Order meta: _intersoccer_points_redeemed
 * 
 * TC-REDEEM-03 (zero balance):
 * - When balance is 0, .intersoccer-points-redemption-wrapper is ABSENT (not rendered)
 * 
 * TC-REDEEM-04 (combo with referral):
 * - .intersoccer-referral-code-wrapper
 * - #intersoccer_referral_code
 * - #apply_referral_code
 * - #referral_code_message
 * 
 * DO NOT use legacy: #credit-slider, #apply-max-credits
 */

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

        // TC-REDEEM-04: Referral code selectors (existing IDs - DO NOT RENAME)
        const $referralInput = $('#intersoccer_referral_code');
        const $referralButton = $('#apply_referral_code');
        const $referralMessage = $('#referral_code_message');

        // TC-REDEEM-01/02: Points UI selectors (existing IDs/classes - DO NOT RENAME)
        const $pointsToggle = $('#intersoccer_use_points');
        const $pointsInput = $('#intersoccer_points_to_redeem');
        const $pointsPanel = $('.intersoccer-points-redemption .points-details');
        const $confirmation = $('.intersoccer-points-redemption .applied-amount');
        const $confirmationText = $('.intersoccer-points-redemption .applied-text');
        const $applyAllBtn = $('.apply-all-points');

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
                        if (!isNaN(discountValue) && discountValue > 0) {
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

        /**
         * Apply points amount and show confirmation
         * TC-REDEEM-02: discount confirmation in order summary
         * Uses .applied-amount / .applied-text selectors (existing from main)
         * Uses classes for visibility (Lane pattern-lock: prefer classes over inline)
         * AJAX: action=update_points_session
         */
        function applyPointsAmount(pointsAmount) {
            const availablePoints = parseInt(config.available_points, 10) || 0;
            let amount = parseInt(pointsAmount, 10) || 0;

            // Clamp to valid range
            if (amount < 0) amount = 0;
            if (amount > availablePoints) amount = availablePoints;

            // Get credit value (CHF per point) from input data attribute or default to 1.00
            const creditValue = parseFloat($pointsInput.data('credit-value')) || 1.00;
            const discountAmount = (amount * creditValue).toFixed(2);

            // Update input value
            $pointsInput.val(amount);

            // Update confirmation display (TC-REDEEM-02)
            // Uses .applied-amount + .applied-text (existing selectors)
            // Lane pattern: muted text + strong amount (green), not purple
            if (amount > 0) {
                $confirmationText.text(amount + ' pts = CHF ' + discountAmount + ' discount');
                $confirmation
                    .removeClass('applied-amount--hidden')
                    .addClass('applied-amount--visible');
            } else {
                $confirmation
                    .removeClass('applied-amount--visible')
                    .addClass('applied-amount--hidden');
            }

            // Send to server to update session (AJAX: action=update_points_session)
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
                    // Trigger WooCommerce checkout update to show discount in order summary
                    // Fee shows as "Referral Credits Discount" via apply_points_discount_as_fee
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

        // Referral code apply button click
        $(document).off('click', '#apply_referral_code').on('click', '#apply_referral_code', function (event) {
            event.preventDefault();
            applyReferralCode();
        });

        // Referral code change button click
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

        // Auto-apply referral code if requested (from cookie/session attribution)
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

        // Points redemption toggle (TC-REDEEM-01: checkout-only interaction)
        // Selector: #intersoccer_use_points (existing ID - DO NOT RENAME)
        // Panel: .intersoccer-points-redemption .points-details
        // Uses classes for visibility (Lane pattern-lock: prefer classes over inline)
        $(document).off('change', '#intersoccer_use_points').on('change', '#intersoccer_use_points', function () {
            const $panel = $(this).closest('.intersoccer-points-redemption').find('.points-details');
            if ($(this).is(':checked')) {
                $panel
                    .removeClass('points-details--hidden')
                    .addClass('points-details--visible');
            } else {
                $panel
                    .removeClass('points-details--visible')
                    .addClass('points-details--hidden');
                // Clear points when unchecked
                applyPointsAmount(0);
            }
        });

        // Apply all points button
        // Selector: .apply-all-points (existing class - DO NOT RENAME)
        $(document).off('click', '.apply-all-points').on('click', '.apply-all-points', function (e) {
            e.preventDefault();
            const maxPoints = parseInt($(this).data('max-points'), 10) || parseInt(config.available_points, 10) || 0;
            applyPointsAmount(maxPoints);
        });

        // Custom points input change
        $(document).off('input', '#intersoccer_points_to_redeem').on('input', '#intersoccer_points_to_redeem', function () {
            applyPointsAmount($(this).val());
        });
    }

    $(document).ready(function () {
        initCheckoutHandlers();
    });

    // Re-initialize after WooCommerce updates checkout
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

