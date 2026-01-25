jQuery(document).ready(function($) {
    'use strict';

    console.log('Admin settings JS loaded');

    const admin = (typeof intersoccer_admin !== 'undefined')
        ? intersoccer_admin
        : { ajax_url: (typeof ajaxurl !== 'undefined' ? ajaxurl : ''), nonce: '' };

    // Coach CSV Import Handler (only bind when the form exists)
    if ($('#coach-import-form').length) {
        console.log('Coach import form found, attaching handler');

        $('#coach-import-form').on('submit', function(e) {
        console.log('Form submit event triggered');
        e.preventDefault();
        console.log('Default prevented, starting AJAX import...');

        const formData = new FormData(this);
        const submitBtn = $('#import-submit-btn');
        const originalText = submitBtn.html();
        const importStatus = $('#import-status');
        const importResults = $('#import-results');
        const progressFill = $('#progress-fill');
        const progressText = $('#progress-text');
        const clearBtn = $('#clear-import-results');

        // Check if localized admin config is defined
        if (!admin || !admin.ajax_url) {
            console.error('intersoccer_admin is not defined');
            alert('JavaScript error: intersoccer_admin not defined. Please refresh the page.');
            return false;
        }

        console.log('intersoccer_admin:', admin);

        // Reset UI
        importResults.hide();
        clearBtn.hide();
        progressFill.css('width', '0%');
        progressText.text('Preparing import...');

        // Disable form and show progress
        submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Importing...');
        importStatus.show();

        // Add the AJAX action to the form data
        formData.append('action', 'import_coaches_from_csv');
        console.log('FormData prepared with action');

        // Simulate progress updates
        let progress = 0;
        const progressInterval = setInterval(function() {
            progress += Math.random() * 15;
            if (progress > 90) progress = 90;
            progressFill.css('width', progress + '%');
            progressText.text('Processing coaches... ' + Math.round(progress) + '%');
        }, 500);

        // Submit the form via AJAX
        console.log('Sending AJAX request to:', admin.ajax_url);
        
        // Set a timeout to detect hanging requests
        const timeoutHandle = setTimeout(function() {
            clearInterval(progressInterval);
            importStatus.hide();
            submitBtn.prop('disabled', false).html(originalText);
            alert('Import timed out after 60 seconds. The server may still be processing. Please check if coaches were imported and try again if needed.');
        }, 60000);
        
        $.ajax({
            url: admin.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            timeout: 60000, // 60 second timeout
            success: function(response) {
                clearTimeout(timeoutHandle);
                console.log('AJAX success:', response);
                clearInterval(progressInterval);
                progressFill.css('width', '100%');
                progressText.text('Import completed!');

                setTimeout(function() {
                    importStatus.hide();
                    submitBtn.prop('disabled', false).html(originalText);

                    if (response.success) {
                        displayImportResults(response.data);
                        clearBtn.show();
                        // Refresh coach stats if the function exists (on Tools page)
                        if (typeof loadCoachStats === 'function') {
                            loadCoachStats();
                        }
                    } else {
                        displayImportError(response.data || 'Unknown error occurred');
                    }
                }, 1000);
            },
            error: function(xhr, status, error) {
                clearTimeout(timeoutHandle);
                console.log('AJAX error:', xhr, status, error);
                console.log('Response text:', xhr.responseText);
                clearInterval(progressInterval);
                importStatus.hide();
                submitBtn.prop('disabled', false).html(originalText);

                let errorMessage = 'Import failed. Please try again.';
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMessage = xhr.responseJSON.data;
                } else if (xhr.responseText) {
                    errorMessage = 'Server error: ' + xhr.responseText;
                }

                displayImportError(errorMessage);
            }
        });

        return false; // Extra prevention of form submission
    });

    console.log('Event handler attached to coach import form');

    // Clear import results
    $('#clear-import-results').on('click', function() {
        $('#import-results').hide();
        $(this).hide();
    });

    // Display import results
    function displayImportResults(data) {
        const resultsDiv = $('#import-summary-content');
        let html = '';

        if (data.created && data.created.length > 0) {
            html += '<div class="import-success">';
            html += '<h5>✅ Coaches Created (' + data.created.length + ')</h5>';
            html += '<ul>';
            data.created.forEach(function(coach) {
                html += '<li>' + coach.first_name + ' ' + coach.last_name + ' (' + coach.email + ')</li>';
            });
            html += '</ul></div>';
        }

        if (data.updated && data.updated.length > 0) {
            html += '<div class="import-success">';
            html += '<h5>🔄 Coaches Updated (' + data.updated.length + ')</h5>';
            html += '<ul>';
            data.updated.forEach(function(coach) {
                html += '<li>' + coach.first_name + ' ' + coach.last_name + ' (' + coach.email + ')</li>';
            });
            html += '</ul></div>';
        }

        if (data.errors && data.errors.length > 0) {
            html += '<div class="import-errors">';
            html += '<h5>⚠️ Errors (' + data.errors.length + ')</h5>';
            html += '<ul>';
            data.errors.forEach(function(error) {
                html += '<li>' + error + '</li>';
            });
            html += '</ul></div>';
        }

        if (data.skipped && data.skipped.length > 0) {
            html += '<div class="import-warnings">';
            html += '<h5>⏭️ Skipped (' + data.skipped.length + ')</h5>';
            html += '<ul>';
            data.skipped.forEach(function(coach) {
                html += '<li>' + coach.first_name + ' ' + coach.last_name + ' (' + coach.email + ') - ' + coach.reason + '</li>';
            });
            html += '</ul></div>';
        }

        resultsDiv.html(html);
        $('#import-results').show();
    }

    // Display import error
    function displayImportError(error) {
        const resultsDiv = $('#import-summary-content');
        resultsDiv.html('<div class="import-errors"><h5>❌ Import Failed</h5><p>' + error + '</p></div>');
        $('#import-results').show();
    }

    // File validation
    $('#coaches_csv').on('change', function() {
        const file = this.files[0];
        const maxSize = 10 * 1024 * 1024; // 10MB

        if (file) {
            if (file.size > maxSize) {
                alert('File size exceeds 10MB limit. Please choose a smaller file.');
                this.value = '';
                return;
            }

            if (!file.name.toLowerCase().endsWith('.csv')) {
                alert('Please select a CSV file.');
                this.value = '';
                return;
            }
        }
    });

    }

    // =========================================================================
    // Points configuration: live preview + save (migrated from class-admin-settings.php inline scripts)
    // =========================================================================
    if ($('#points-rates-form').length) {
        function getCreditValue() {
            const v = parseFloat($('input[name="intersoccer_credit_value"]').val());
            return Number.isFinite(v) && v > 0 ? v : 1;
        }

        function updatePreview($input) {
            const creditValue = getCreditValue();
            const rate = parseInt($input.val(), 10) || 10;
            const role = $input.attr('id').replace('rate_', '');
            const $preview = $('.preview-points[data-role="' + role + '"]');
            if ($preview.length) {
                const points = Math.floor(100 / Math.max(1, rate));
                $preview.text(points);
            }

            const $percentage = $('.rate-percentage[data-rate-input="' + $input.attr('id') + '"]');
            if ($percentage.length) {
                const percentage = (creditValue / Math.max(1, rate)) * 100;
                $percentage.text('(' + percentage.toFixed(1) + '%)');
            }
        }

        // Attach preview updates to all rate inputs
        $('input[id^="rate_"]').on('input', function() {
            updatePreview($(this));
        });

        // Handle form submission
        $('#points-rates-form').on('submit', function(e) {
            e.preventDefault();

            const $button = $('#save-points-rates');
            const $message = $('#rates-save-message');

            $button.prop('disabled', true).text('Saving...');

            // Collect form data
            const formData = {
                action: 'save_points_rates',
                nonce: admin.nonce,
                rate_customer_purchase: $('#rate_customer_purchase').val(),
                rate_customer_referral: $('#rate_customer_referral').val(),
                rate_first_time_customer: $('#rate_first_time_customer').val(),
                intersoccer_points_golive_date: $('#points-golive-date').val()
            };

            $.ajax({
                url: admin.ajax_url || (typeof ajaxurl !== 'undefined' ? ajaxurl : ''),
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response && response.success) {
                        $message.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>').show();
                        setTimeout(function() {
                            $message.fadeOut();
                        }, 3000);
                    } else {
                        $message.html('<div class="notice notice-error"><p>' + ((response && response.data && response.data.message) ? response.data.message : 'Error saving rates') + '</p></div>').show();
                    }
                },
                error: function() {
                    $message.html('<div class="notice notice-error"><p>Error saving rates. Please try again.</p></div>').show();
                },
                complete: function() {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Save Points Configuration & Rates');
                }
            });
        });

        // Handle reset button
        $('#reset-points-rates').on('click', function() {
            if (!confirm('Reset all customer rates to 10 CHF = 1 point?')) {
                return;
            }

            $('#rate_customer_purchase').val(10);
            $('#rate_customer_referral').val(10);
            $('#rate_first_time_customer').val(10);

            $('input[id^="rate_"]').each(function() {
                updatePreview($(this));
            });
        });
    }

    // =========================================================================
    // Commission tier management (migrated from class-admin-settings.php inline scripts)
    // =========================================================================
    if ($('#save-all-commission-tiers').length) {
        const roles = ['coach', 'partner', 'social_influencer'];
        const tierIndexes = {};

        roles.forEach(function(role) {
            tierIndexes[role] = $('.commission-tier-row[data-role="' + role + '"]').length;
        });

        function updateTierExample($input) {
            const $row = $input.closest('.commission-tier-row');
            const rate = parseFloat($input.val()) || 0;
            const exampleOrder = 500;
            const exampleCommission = (exampleOrder * rate) / 100;

            let $example = $row.find('.tier-example');
            if ($example.length === 0) {
                $example = $('<span class="tier-example" style="color: #666; font-size: 13px; margin-left: 10px;"></span>');
                $input.closest('.commission-tier-row').find('span:contains(\"%\")').after($example);
            }

            $example.text('(CHF ' + exampleCommission.toFixed(2) + ' on ' + exampleOrder.toLocaleString() + ' CHF order)');
        }

        $(document).on('input', '.tier-rate', function() {
            updateTierExample($(this));
        });

        $(document).on('click', '.add-commission-tier', function() {
            const role = $(this).data('role');
            const container = $('#' + role + '-commission-tiers-container');
            const tierIndex = tierIndexes[role] || 0;

            const tierRow = $(
                '<div class="commission-tier-row" data-role="' + role + '" data-tier-index="' + tierIndex + '" style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px; padding: 10px; background: #f9f9f9; border-radius: 4px;">' +
                '<input type="number" class="tier-min-customers" value="1" min="1" style="width: 80px;" placeholder="Min">' +
                '<span>to</span>' +
                '<input type="number" class="tier-max-customers" value="" min="1" style="width: 80px;" placeholder="Max or +">' +
                '<span>customers:</span>' +
                '<input type="number" class="tier-rate" value="10" min="0" max="100" step="0.1" style="width: 80px;" placeholder="%">' +
                '<span>%</span>' +
                '<span class="tier-example" style="color: #666; font-size: 13px; margin-left: 10px;">(CHF 50.00 on 500 CHF order)</span>' +
                '<button type="button" class="button button-small delete-tier" style="margin-left: auto;">Delete</button>' +
                '</div>'
            );

            container.append(tierRow);
            tierIndexes[role] = tierIndex + 1;
        });

        $(document).on('click', '.delete-tier', function() {
            const $row = $(this).closest('.commission-tier-row');
            const role = $row.data('role');
            const container = $('#' + role + '-commission-tiers-container');

            if (container.find('.commission-tier-row').length <= 1) {
                alert('You must have at least one tier.');
                return;
            }
            $row.remove();
        });

        $('#save-all-commission-tiers').on('click', function() {
            const $button = $(this);
            const allTiers = {};

            // Collect tiers for each role
            let isValid = true;
            roles.forEach(function(role) {
                const tiers = [];
                $('.commission-tier-row[data-role="' + role + '"]').each(function() {
                    const min = parseInt($(this).find('.tier-min-customers').val(), 10) || 1;
                    const maxInput = $(this).find('.tier-max-customers').val();
                    const max = maxInput === '' ? 999999 : parseInt(maxInput, 10);
                    const rate = parseFloat($(this).find('.tier-rate').val()) || 0;

                    if (min < 1 || rate < 0 || rate > 100) {
                        alert('Please enter valid values: Min >= 1, Rate between 0-100.');
                        isValid = false;
                        return false;
                    }

                    tiers.push({
                        min_customers: min,
                        max_customers: max,
                        rate: rate
                    });
                });

                if (!isValid) {
                    return;
                }

                if (tiers.length === 0) {
                    alert('Please add at least one tier for each role.');
                    isValid = false;
                    return;
                }

                allTiers[role] = tiers;
            });

            if (!isValid) {
                return;
            }

            $button.prop('disabled', true).text('Saving...');

            $.ajax({
                url: (typeof ajaxurl !== 'undefined' ? ajaxurl : admin.ajax_url),
                type: 'POST',
                data: {
                    action: 'save_commission_tiers',
                    nonce: admin.nonce,
                    tiers: JSON.stringify(allTiers)
                },
                success: function(response) {
                    if (response && response.success) {
                        roles.forEach(function(role) {
                            const $message = $('#' + role + '-commission-tiers-message');
                            $message.removeClass('notice-error').addClass('notice notice-success')
                                .html('<p>' + response.data.message + '</p>').show();
                            setTimeout(function() {
                                $message.fadeOut();
                            }, 3000);
                        });
                    } else {
                        roles.forEach(function(role) {
                            const $message = $('#' + role + '-commission-tiers-message');
                            $message.removeClass('notice-success').addClass('notice notice-error')
                                .html('<p>' + ((response && response.data && response.data.message) ? response.data.message : 'Error saving tiers.') + '</p>').show();
                        });
                    }
                },
                error: function() {
                    roles.forEach(function(role) {
                        const $message = $('#' + role + '-commission-tiers-message');
                        $message.removeClass('notice-success').addClass('notice notice-error')
                            .html('<p>Error saving tiers.</p>').show();
                    });
                },
                complete: function() {
                    $button.prop('disabled', false).text('Save All Commission Tiers');
                }
            });
        });
    }
});