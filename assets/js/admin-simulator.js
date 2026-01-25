(function($) {
    'use strict';

    $(document).ready(function() {
        // Ensure admin config is available
        window.intersoccer_admin = window.intersoccer_admin || {};
        if (typeof ajaxurl === 'undefined') {
            // WordPress typically defines ajaxurl in admin, but keep a safe fallback
            var ajaxurl = window.intersoccer_admin.ajax_url || '';
        }

        jQuery(document).ready(function($) {
                    // Define ajaxurl if not already defined (WordPress admin)
                    if (typeof ajaxurl === 'undefined') {
                        var ajaxurl = (window.intersoccer_admin && window.intersoccer_admin.ajax_url) ? window.intersoccer_admin.ajax_url : '';
                    }

                    // Sales & Marketing Revenue Simulator
                    let selectedOrderId = null;
                    let simulatorChart = null;
                    let simulatorComparisonChart = null;
                    let simulatorROIChart = null;
                    let simulatorSensitivityChart = null;
                    let simulatorReferralsChart = null;
                    let simulatorCommissionsChart = null;

                    // Tab switching
                    $(document).on('click', '.simulator-tabs .nav-tab', function(e) {
                        e.preventDefault();
                        const tab = $(this).data('tab');
                        console.log('Switching to tab:', tab);
                        $('.simulator-tabs .nav-tab').removeClass('nav-tab-active');
                        $(this).addClass('nav-tab-active');
                        $('.simulator-tab-content').hide();
                        $('#simulator-tab-' + tab).show();
                    });

                    // Show first tab by default
                    $('.simulator-tabs .nav-tab:first').addClass('nav-tab-active');
                    $('.simulator-tab-content').hide();
                    $('#simulator-tab-points').show();

                    // Auto-calculate total referral rate
                    $(document).on('input', '#simulator-customer-referral-rate, #simulator-coach-referral-rate', function() {
                        const customerRate = parseFloat($('#simulator-customer-referral-rate').val()) || 0;
                        const coachRate = parseFloat($('#simulator-coach-referral-rate').val()) || 0;
                        const totalRate = Math.min(100, customerRate + coachRate);
                        $('#simulator-referral-rate').val(totalRate.toFixed(1));
                    });

                    // Toggle growth percentage field visibility
                    $(document).on('change', '#simulator-enable-growth', function() {
                        if ($(this).is(':checked')) {
                            $('#simulator-growth-percentage-row').show();
                        } else {
                            $('#simulator-growth-percentage-row').hide();
                        }
                    });

                    // Load recommendations
                    $(document).on('click', '#simulator-load-recommendations', function(e) {
                        e.preventDefault();
                        const dateFrom = $('#simulator-date-from').val();
                        const dateTo = $('#simulator-date-to').val();

                        if (!dateFrom || !dateTo) {
                            alert('Please select both start and end dates first');
                            return;
                        }

                        const $btn = $(this);
                        const $spinner = $('<span class="spinner is-active" style="float: none; margin-left: 10px;"></span>');
                        $btn.after($spinner);
                        $btn.prop('disabled', true);

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'intersoccer_get_recommendations',
                                nonce: (window.intersoccer_admin && window.intersoccer_admin.simulator_nonce) ? window.intersoccer_admin.simulator_nonce : '',
                                date_from: dateFrom,
                                date_to: dateTo
                            },
                            dataType: 'json',
                            success: function(response) {
                                $spinner.remove();
                                $btn.prop('disabled', false);

                                if (response.success) {
                                    let html = '<h4>Recommendations Based on Historical Data</h4>';
                                    html += '<div style="background: #fff; padding: 15px; border-radius: 4px; margin-top: 10px;">';

                                    if (response.data.recommendations && response.data.recommendations.length > 0) {
                                        response.data.recommendations.forEach(function(rec) {
                                            html += '<div style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-left: 4px solid #2563eb; border-radius: 4px;">';
                                            html += '<strong>' + rec.title + '</strong><br>';
                                            html += '<span style="color: #666;">Current: ' + rec.current + ' | Recommended: ' + rec.recommended + '</span><br>';
                                            html += '<small style="color: #888;">' + rec.reason + '</small>';
                                            html += '</div>';
                                        });
                                    } else {
                                        html += '<p>No specific recommendations. Your current settings appear to be well-optimized.</p>';
                                    }

                                    html += '<hr style="margin: 15px 0;">';
                                    html += '<h5>Historical Data Summary</h5>';
                                    html += '<table class="widefat" style="margin-top: 10px;">';
                                    html += '<tr><th style="width: 200px;">Total Orders</th><td>' + response.data.historical.total_orders + '</td></tr>';
                                    html += '<tr><th>Total Referrals</th><td>' + response.data.historical.total_referrals + '</td></tr>';
                                    html += '<tr><th>Actual Referral Rate</th><td>' + response.data.historical.actual_referral_rate.toFixed(1) + '%</td></tr>';
                                    html += '<tr><th>Coach Referral Rate</th><td>' + response.data.historical.coach_referral_rate.toFixed(1) + '%</td></tr>';
                                    html += '<tr><th>Customer Referral Rate</th><td>' + response.data.historical.customer_referral_rate.toFixed(1) + '%</td></tr>';
                                    html += '<tr><th>Average Commission</th><td>CHF ' + response.data.historical.avg_commission.toFixed(2) + '</td></tr>';
                                    html += '<tr><th>Average Order Value</th><td>CHF ' + response.data.historical.avg_order_value.toFixed(2) + '</td></tr>';
                                    html += '<tr><th>Active Coaches</th><td>' + response.data.historical.coach_count + '</td></tr>';
                                    html += '</table>';

                                    // Auto-populate fields with historical data
                                    if (response.data.historical.customer_referral_rate > 0) {
                                        $('#simulator-customer-referral-rate').val(response.data.historical.customer_referral_rate.toFixed(1));
                                    }
                                    if (response.data.historical.coach_referral_rate > 0) {
                                        $('#simulator-coach-referral-rate').val(response.data.historical.coach_referral_rate.toFixed(1));
                                    }
                                    // Trigger change to update total
                                    $('#simulator-customer-referral-rate').trigger('input');

                                    html += '</div>';
                                    $('#simulator-recommendations').html(html).show();
                                } else {
                                    alert(response.data.message || 'Error loading recommendations');
                                }
                            },
                            error: function(xhr, status, error) {
                                $spinner.remove();
                                $btn.prop('disabled', false);
                                console.error('Recommendations error:', status, error, xhr.responseText);
                                alert('Error loading recommendations');
                            }
                        });
                    });

                    // Points allocation mode toggle
                    $(document).on('change', 'input[name="simulator-points-mode"]', function() {
                        if ($(this).val() === 'percentage') {
                            $('#simulator-percentage-rate-row').show();
                        } else {
                            $('#simulator-percentage-rate-row').hide();
                        }
                    });

                    // Commission tier management
                    $(document).on('click', '.simulator-add-tier', function() {
                        const role = $(this).data('role');
                        const $container = $('.simulator-commission-tiers[data-role="' + role + '"]');
                        const tierCount = $container.find('.simulator-tier-row').length;
                        const lastTier = $container.find('.simulator-tier-row').last();
                        const lastMax = parseInt(lastTier.find('.simulator-tier-max').val()) || 999999;
                        const lastRate = parseFloat(lastTier.find('.simulator-tier-rate').val()) || 10;

                        const newMin = lastMax === 999999 ? lastMax : lastMax + 1;
                        const html = '<div class="simulator-tier-row" style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; padding: 8px; background: #fff; border-radius: 3px;">' +
                            '<input type="number" class="simulator-tier-min" value="' + newMin + '" min="1" style="width: 70px;" placeholder="Min">' +
                            '<span>to</span>' +
                            '<input type="number" class="simulator-tier-max" value="" min="1" style="width: 70px;" placeholder="Max">' +
                            '<span>customers:</span>' +
                            '<input type="number" class="simulator-tier-rate" value="' + lastRate + '" min="0" max="100" step="0.1" style="width: 70px;" placeholder="%">' +
                            '<span>%</span>' +
                            '<button type="button" class="button button-small simulator-remove-tier" style="margin-left: auto;">Remove</button>' +
                            '</div>';
                        $container.append(html);
                    });

                    $(document).on('click', '.simulator-remove-tier', function() {
                        if ($(this).closest('.simulator-commission-tiers').find('.simulator-tier-row').length > 1) {
                            $(this).closest('.simulator-tier-row').remove();
                        } else {
                            alert('At least one tier is required');
                        }
                    });

                    // Scenario Management
                    function loadScenarios() {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'intersoccer_list_simulator_scenarios',
                                nonce: (window.intersoccer_admin && window.intersoccer_admin.simulator_nonce) ? window.intersoccer_admin.simulator_nonce : ''
                            },
                            dataType: 'json',
                            success: function(response) {
                                console.log('Load scenarios response:', response);
                                if (response.success) {
                                    const $select = $('#simulator-load-scenario');
                                    $select.find('option:not(:first)').remove();
                                    response.data.scenarios.forEach(function(scenario) {
                                        $select.append('<option value="' + scenario.id + '">' + scenario.name + ' (' + scenario.date + ')</option>');
                                    });
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Load scenarios error:', status, error, xhr.responseText);
                            }
                        });
                    }

                    // Load scenarios on page load
                    loadScenarios();

                    // Load current settings
                    $(document).on('click', '#simulator-load-current-settings', function(e) {
                        e.preventDefault();
                        console.log('Load Current Settings clicked');
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'intersoccer_load_current_settings',
                                nonce: (window.intersoccer_admin && window.intersoccer_admin.simulator_nonce) ? window.intersoccer_admin.simulator_nonce : ''
                            },
                            dataType: 'json',
                            success: function(response) {
                                console.log('Load current settings response:', response);
                                if (response.success) {
                                    loadScenarioIntoForm(response.data.settings);
                                } else {
                                    alert(response.data.message || 'Error loading settings');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Load settings error:', status, error, xhr.responseText);
                                alert('Error loading settings');
                            }
                        });
                    });

                    // Load scenario template
                    $(document).on('change', '#simulator-scenario-template', function() {
                        const template = $(this).val();
                        if (!template) return;
                        console.log('Loading template:', template);

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'intersoccer_load_scenario_template',
                                nonce: (window.intersoccer_admin && window.intersoccer_admin.simulator_nonce) ? window.intersoccer_admin.simulator_nonce : '',
                                template: template
                            },
                            dataType: 'json',
                            success: function(response) {
                                console.log('Template response:', response);
                                if (response.success) {
                                    loadScenarioIntoForm(response.data.settings);
                                } else {
                                    alert(response.data.message || 'Error loading template');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Template error:', status, error, xhr.responseText);
                                alert('Error loading template');
                            }
                        });
                    });

                    // Load saved scenario
                    $(document).on('change', '#simulator-load-scenario', function() {
                        const scenarioId = $(this).val();
                        if (!scenarioId) return;
                        console.log('Loading scenario:', scenarioId);

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'intersoccer_load_simulator_scenario',
                                nonce: (window.intersoccer_admin && window.intersoccer_admin.simulator_nonce) ? window.intersoccer_admin.simulator_nonce : '',
                                scenario_id: scenarioId
                            },
                            dataType: 'json',
                            success: function(response) {
                                console.log('Load scenario response:', response);
                                if (response.success) {
                                    loadScenarioIntoForm(response.data.settings);
                                    $('#simulator-scenario-name').val(response.data.name);
                                    $('#simulator-delete-scenario').data('scenario-id', scenarioId).show();
                                } else {
                                    alert(response.data.message || 'Error loading scenario');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Load scenario error:', status, error, xhr.responseText);
                                alert('Error loading scenario');
                            }
                        });
                    });

                    // Save scenario
                    $(document).on('click', '#simulator-save-scenario', function(e) {
                        e.preventDefault();
                        console.log('Save Scenario clicked');
                        const scenarioName = $('#simulator-scenario-name').val().trim();
                        if (!scenarioName) {
                            alert('Please enter a scenario name');
                            return;
                        }

                        const settings = collectSimulatorSettings();
                        console.log('Saving scenario:', scenarioName, settings);
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'intersoccer_save_simulator_scenario',
                                nonce: (window.intersoccer_admin && window.intersoccer_admin.simulator_nonce) ? window.intersoccer_admin.simulator_nonce : '',
                                name: scenarioName,
                                settings: JSON.stringify(settings)
                            },
                            dataType: 'json',
                            success: function(response) {
                                console.log('Save scenario response:', response);
                                if (response.success) {
                                    alert('Scenario saved successfully');
                                    loadScenarios();
                                } else {
                                    alert(response.data.message || 'Error saving scenario');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Save scenario error:', status, error, xhr.responseText);
                                alert('Error saving scenario: ' + (xhr.responseJSON?.data?.message || error));
                            }
                        });
                    });

                    // Delete scenario
                    $(document).on('click', '#simulator-delete-scenario', function(e) {
                        e.preventDefault();
                        const scenarioId = $(this).data('scenario-id');
                        if (!scenarioId || !confirm('Are you sure you want to delete this scenario?')) {
                            return;
                        }

                        console.log('Deleting scenario:', scenarioId);
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'intersoccer_delete_simulator_scenario',
                                nonce: (window.intersoccer_admin && window.intersoccer_admin.simulator_nonce) ? window.intersoccer_admin.simulator_nonce : '',
                                scenario_id: scenarioId
                            },
                            dataType: 'json',
                            success: function(response) {
                                console.log('Delete scenario response:', response);
                                if (response.success) {
                                    alert('Scenario deleted successfully');
                                    $('#simulator-load-scenario').val('');
                                    $('#simulator-delete-scenario').hide();
                                    loadScenarios();
                                } else {
                                    alert(response.data.message || 'Error deleting scenario');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Delete scenario error:', status, error, xhr.responseText);
                                alert('Error deleting scenario');
                            }
                        });
                    });

                    // Helper function to collect all simulator settings
                    function collectSimulatorSettings() {
                        const settings = {
                            date_from: $('#simulator-date-from').val(),
                            date_to: $('#simulator-date-to').val(),
                            points_mode: $('input[name="simulator-points-mode"]:checked').val(),
                            percentage_rate: parseFloat($('#simulator-percentage-rate').val()) || 0,
                            points_rate_purchase: parseInt($('#simulator-points-rate-purchase').val()) || 10,
                            points_rate_referral: parseInt($('#simulator-points-rate-referral').val()) || 10,
                            points_rate_first_time: parseInt($('#simulator-points-rate-first-time').val()) || 10,
                            points_value: parseFloat($('#simulator-points-value').val()) || 1,
                            customer_referral_rate: parseFloat($('#simulator-customer-referral-rate').val()) || 10,
                            coach_referral_rate: parseFloat($('#simulator-coach-referral-rate').val()) || 5,
                            referral_rate: parseFloat($('#simulator-referral-rate').val()) || 15,
                            first_time_discount: parseFloat($('#simulator-first-time-discount').val()) || 10,
                            dist_coach: parseInt($('#simulator-dist-coach').val()) || 60,
                            dist_partner: parseInt($('#simulator-dist-partner').val()) || 25,
                            dist_influencer: parseInt($('#simulator-dist-influencer').val()) || 15,
                            project_months: parseInt($('#simulator-project-months').val()) || 0,
                            revenue_growth: parseFloat($('#simulator-revenue-growth').val()) || 5,
                            referral_adoption_start: parseFloat($('#simulator-referral-adoption-start').val()) || 15,
                            referral_adoption_end: parseFloat($('#simulator-referral-adoption-end').val()) || 30,
                            enable_growth: $('#simulator-enable-growth').is(':checked'),
                            growth_percentage: parseFloat($('#simulator-growth-percentage').val()) || 0,
                            commission_tiers: {}
                        };

                        // Collect commission tiers for each role
                        ['coach', 'partner', 'social_influencer'].forEach(function(role) {
                            const tiers = [];
                            $('.simulator-commission-tiers[data-role="' + role + '"] .simulator-tier-row').each(function() {
                                const min = parseInt($(this).find('.simulator-tier-min').val()) || 1;
                                const max = $(this).find('.simulator-tier-max').val();
                                const rate = parseFloat($(this).find('.simulator-tier-rate').val()) || 0;
                                tiers.push({
                                    min_customers: min,
                                    max_customers: max === '' ? 999999 : parseInt(max),
                                    rate: rate
                                });
                            });
                            settings.commission_tiers[role] = tiers;
                        });

                        return settings;
                    }

                    // Helper function to load settings into form
                    function loadScenarioIntoForm(settings) {
                        if (settings.date_from) $('#simulator-date-from').val(settings.date_from);
                        if (settings.date_to) $('#simulator-date-to').val(settings.date_to);
                        if (settings.points_mode) $('input[name="simulator-points-mode"][value="' + settings.points_mode + '"]').prop('checked', true).trigger('change');
                        if (settings.percentage_rate !== undefined) $('#simulator-percentage-rate').val(settings.percentage_rate);
                        if (settings.points_rate_purchase) $('#simulator-points-rate-purchase').val(settings.points_rate_purchase);
                        if (settings.points_rate_referral) $('#simulator-points-rate-referral').val(settings.points_rate_referral);
                        if (settings.points_rate_first_time) $('#simulator-points-rate-first-time').val(settings.points_rate_first_time);
                        if (settings.points_value) $('#simulator-points-value').val(settings.points_value);
                        if (settings.referral_rate !== undefined) $('#simulator-referral-rate').val(settings.referral_rate);
                        if (settings.first_time_discount !== undefined) $('#simulator-first-time-discount').val(settings.first_time_discount);
                        if (settings.dist_coach !== undefined) $('#simulator-dist-coach').val(settings.dist_coach);
                        if (settings.dist_partner !== undefined) $('#simulator-dist-partner').val(settings.dist_partner);
                        if (settings.dist_influencer !== undefined) $('#simulator-dist-influencer').val(settings.dist_influencer);
                        if (settings.project_months !== undefined) $('#simulator-project-months').val(settings.project_months);
                        if (settings.revenue_growth !== undefined) $('#simulator-revenue-growth').val(settings.revenue_growth);
                        if (settings.referral_adoption_start !== undefined) $('#simulator-referral-adoption-start').val(settings.referral_adoption_start);
                        if (settings.referral_adoption_end !== undefined) $('#simulator-referral-adoption-end').val(settings.referral_adoption_end);
                        if (settings.enable_growth !== undefined) {
                            $('#simulator-enable-growth').prop('checked', settings.enable_growth).trigger('change');
                        }
                        if (settings.growth_percentage !== undefined) $('#simulator-growth-percentage').val(settings.growth_percentage);

                        // Load commission tiers
                        if (settings.commission_tiers) {
                            ['coach', 'partner', 'social_influencer'].forEach(function(role) {
                                if (settings.commission_tiers[role]) {
                                    const $container = $('.simulator-commission-tiers[data-role="' + role + '"]');
                                    $container.find('.simulator-tier-row').remove();
                                    settings.commission_tiers[role].forEach(function(tier) {
                                        const maxVal = tier.max_customers >= 999999 ? '' : tier.max_customers;
                                        const html = '<div class="simulator-tier-row" style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px; padding: 8px; background: #fff; border-radius: 3px;">' +
                                            '<input type="number" class="simulator-tier-min" value="' + tier.min_customers + '" min="1" style="width: 70px;" placeholder="Min">' +
                                            '<span>to</span>' +
                                            '<input type="number" class="simulator-tier-max" value="' + maxVal + '" min="1" style="width: 70px;" placeholder="Max">' +
                                            '<span>customers:</span>' +
                                            '<input type="number" class="simulator-tier-rate" value="' + tier.rate + '" min="0" max="100" step="0.1" style="width: 70px;" placeholder="%">' +
                                            '<span>%</span>' +
                                            '<button type="button" class="button button-small simulator-remove-tier" style="margin-left: auto;">Remove</button>' +
                                            '</div>';
                                        $container.append(html);
                                    });
                                }
                            });
                        }
                    }

                    // Run simulation
                    $(document).on('click', '#simulator-run', function(e) {
                        e.preventDefault();
                        console.log('Run Simulation clicked');

                        const dateFrom = $('#simulator-date-from').val();
                        const dateTo = $('#simulator-date-to').val();
                        if (!dateFrom || !dateTo) {
                            alert('Please select both start and end dates');
                            return;
                        }
                        if (dateFrom > dateTo) {
                            alert('Start date must be before end date');
                            return;
                        }

                        const $spinner = $('#simulator-spinner');
                        const $results = $('#simulator-results');
                        const $resultsContent = $('#simulator-results-content');

                        $spinner.addClass('is-active');
                        $results.hide();

                        const settings = collectSimulatorSettings();
                        console.log('Settings collected:', settings);
                        const compareMode = $('#simulator-compare-mode').is(':checked');

                        const ajaxData = {
                            action: 'intersoccer_run_referral_simulation',
                            nonce: (window.intersoccer_admin && window.intersoccer_admin.simulator_nonce) ? window.intersoccer_admin.simulator_nonce : '',
                            mode: 'date-range',
                            date_from: dateFrom,
                            date_to: dateTo,
                            settings: JSON.stringify(settings),
                            compare_mode: compareMode ? 1 : 0
                        };

                        console.log('Sending AJAX request:', ajaxData);
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: ajaxData,
                            dataType: 'json',
                            success: function(response) {
                                console.log('AJAX success:', response);
                                $spinner.removeClass('is-active');
                                if (response.success) {
                                    $resultsContent.html(response.data.html);
                                    $results.show();

                                    // Show executive summary if available
                                    if (response.data.executive_summary) {
                                        $('#simulator-executive-summary-content').html(response.data.executive_summary);
                                        $('#simulator-executive-summary').show();
                                    } else {
                                        $('#simulator-executive-summary').hide();
                                    }

                                    // Show comparison view if in compare mode
                                    if (compareMode && response.data.comparison) {
                                        $('#simulator-comparison-content').html(response.data.comparison);
                                        $('#simulator-comparison-view').show();
                                    } else {
                                        $('#simulator-comparison-view').hide();
                                    }

                                    // Initialize charts if data is available
                                    if (response.data.chart_data && typeof Chart !== 'undefined') {
                                        initializeSimulatorChart(response.data.chart_data);
                                    } else {
                                        $('#simulator-chart-container').hide();
                                    }

                                    if (response.data.comparison_chart_data) {
                                        initializeComparisonChart(response.data.comparison_chart_data);
                                    }

                                    if (response.data.roi_chart_data) {
                                        initializeROIChart(response.data.roi_chart_data);
                                    }

                                    if (response.data.sensitivity_data) {
                                        initializeSensitivityChart(response.data.sensitivity_data);
                                    }

                                    // Initialize referrals and commissions charts
                                    if (response.data.chart_data && response.data.chart_data.referrals) {
                                        initializeReferralsChart(response.data.chart_data.referrals);
                                    }
                                    if (response.data.chart_data && response.data.chart_data.commissions_by_role) {
                                        initializeCommissionsChart(response.data.chart_data.commissions_by_role);
                                    }

                                    // Scroll to results
                                    $('html, body').animate({
                                        scrollTop: $('#simulator-results').offset().top - 100
                                    }, 500);
                                } else {
                                    alert(response.data.message || 'Error running simulation');
                                }
                            },
                            error: function(xhr, status, error) {
                                $spinner.removeClass('is-active');
                                console.error('Simulation error:', status, error, xhr.responseText);
                                alert('Error running simulation: ' + (xhr.responseJSON?.data?.message || error));
                            }
                        });
                    });

                    // Run sensitivity analysis
                    $(document).on('click', '#simulator-run-sensitivity', function(e) {
                        e.preventDefault();
                        console.log('Run Sensitivity Analysis clicked');

                        const dateFrom = $('#simulator-date-from').val();
                        const dateTo = $('#simulator-date-to').val();
                        if (!dateFrom || !dateTo) {
                            alert('Please select both start and end dates');
                            return;
                        }
                        if (dateFrom > dateTo) {
                            alert('Start date must be before end date');
                            return;
                        }

                        const $spinner = $('#simulator-spinner');
                        const $sensitivityContainer = $('#simulator-sensitivity-chart-container');

                        $spinner.addClass('is-active');
                        $sensitivityContainer.hide();

                        const settings = collectSimulatorSettings();
                        console.log('Sensitivity analysis settings:', settings);

                        const ajaxData = {
                            action: 'intersoccer_run_sensitivity_analysis',
                            nonce: (window.intersoccer_admin && window.intersoccer_admin.simulator_nonce) ? window.intersoccer_admin.simulator_nonce : '',
                            date_from: dateFrom,
                            date_to: dateTo,
                            settings: JSON.stringify(settings)
                        };

                        console.log('Sending sensitivity analysis AJAX request:', ajaxData);
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: ajaxData,
                            dataType: 'json',
                            success: function(response) {
                                console.log('Sensitivity analysis AJAX success:', response);
                                $spinner.removeClass('is-active');
                                if (response.success) {
                                    if (response.data.sensitivity_data && typeof Chart !== 'undefined') {
                                        initializeSensitivityChart(response.data.sensitivity_data);
                                        $sensitivityContainer.show();

                                        // Scroll to sensitivity chart
                                        $('html, body').animate({
                                            scrollTop: $sensitivityContainer.offset().top - 100
                                        }, 500);
                                    } else {
                                        alert('No sensitivity data returned');
                                    }
                                } else {
                                    alert(response.data.message || 'Error running sensitivity analysis');
                                }
                            },
                            error: function(xhr, status, error) {
                                $spinner.removeClass('is-active');
                                console.error('Sensitivity analysis error:', status, error, xhr.responseText);
                                alert('Error running sensitivity analysis: ' + (xhr.responseJSON?.data?.message || error));
                            }
                        });
                    });

                    // Chart initialization functions
                    function initializeSimulatorChart(chartData) {
                        const canvas = document.getElementById('simulatorFinancialChart');
                        if (!canvas) return;

                        // Destroy existing chart if it exists
                        if (simulatorChart) {
                            simulatorChart.destroy();
                        }

                        const ctx = canvas.getContext('2d');
                        $('#simulator-chart-container').show();

                        // Determine if we have projections
                        const hasProjections = chartData.is_historical && chartData.is_historical.some(h => !h);
                        const lastHistoricalIndex = chartData.last_historical_index !== undefined ? chartData.last_historical_index : -1;

                        simulatorChart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: chartData.labels,
                                datasets: [{
                                    label: chartData.revenue_label || 'Revenue',
                                    data: chartData.revenue,
                                    backgroundColor: function(context) {
                                        if (hasProjections && context.dataIndex > lastHistoricalIndex) {
                                            return 'rgba(243, 156, 18, 0.5)'; // Lighter for projections
                                        }
                                        return '#f39c12';
                                    },
                                    borderColor: function(context) {
                                        if (hasProjections && context.dataIndex > lastHistoricalIndex) {
                                            return 'rgba(230, 126, 34, 0.7)';
                                        }
                                        return '#e67e22';
                                    },
                                    borderWidth: 1,
                                    borderDash: function(context) {
                                        if (hasProjections && context.dataIndex > lastHistoricalIndex) {
                                            return [5, 5]; // Dashed for projections
                                        }
                                        return [];
                                    }
                                }, {
                                    label: chartData.costs_label || 'Costs',
                                    data: chartData.costs,
                                    backgroundColor: function(context) {
                                        if (hasProjections && context.dataIndex > lastHistoricalIndex) {
                                            return 'rgba(155, 89, 182, 0.5)';
                                        }
                                        return '#9b59b6';
                                    },
                                    borderColor: function(context) {
                                        if (hasProjections && context.dataIndex > lastHistoricalIndex) {
                                            return 'rgba(142, 68, 173, 0.7)';
                                        }
                                        return '#8e44ad';
                                    },
                                    borderWidth: 1,
                                    borderDash: function(context) {
                                        if (hasProjections && context.dataIndex > lastHistoricalIndex) {
                                            return [5, 5];
                                        }
                                        return [];
                                    }
                                }, {
                                    label: chartData.profit_label || 'Net Profit/Loss',
                                    data: chartData.profit,
                                    backgroundColor: function(context) {
                                        const value = context.parsed.y;
                                        const isProjection = hasProjections && context.dataIndex > lastHistoricalIndex;
                                        if (value >= 0) {
                                            return isProjection ? 'rgba(39, 174, 96, 0.5)' : '#27ae60';
                                        }
                                        return isProjection ? 'rgba(231, 76, 60, 0.5)' : '#e74c3c';
                                    },
                                    borderColor: function(context) {
                                        const value = context.parsed.y;
                                        const isProjection = hasProjections && context.dataIndex > lastHistoricalIndex;
                                        if (value >= 0) {
                                            return isProjection ? 'rgba(34, 153, 84, 0.7)' : '#229954';
                                        }
                                        return isProjection ? 'rgba(192, 57, 43, 0.7)' : '#c0392b';
                                    },
                                    borderWidth: 1,
                                    borderDash: function(context) {
                                        if (hasProjections && context.dataIndex > lastHistoricalIndex) {
                                            return [5, 5];
                                        }
                                        return [];
                                    }
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        display: true,
                                        position: 'top'
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                const isProjection = hasProjections && context.dataIndex > lastHistoricalIndex;
                                                const suffix = isProjection ? ' (Projected)' : '';
                                                return context.dataset.label + ': CHF ' + context.parsed.y.toLocaleString('en-US', {
                                                    minimumFractionDigits: 2,
                                                    maximumFractionDigits: 2
                                                }) + suffix;
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        display: true,
                                        title: {
                                            display: true,
                                            text: 'Month'
                                        }
                                    },
                                    y: {
                                        display: true,
                                        title: {
                                            display: true,
                                            text: 'Amount (CHF)'
                                        },
                                        beginAtZero: true,
                                        ticks: {
                                            callback: function(value) {
                                                return 'CHF ' + value.toLocaleString('en-US', {
                                                    minimumFractionDigits: 0,
                                                    maximumFractionDigits: 0
                                                });
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    }

                    function initializeComparisonChart(chartData) {
                        const canvas = document.getElementById('simulatorComparisonChart');
                        if (!canvas || !chartData || !chartData.scenarios) return;

                        if (simulatorComparisonChart) {
                            simulatorComparisonChart.destroy();
                        }

                        const ctx = canvas.getContext('2d');
                        $('#simulator-comparison-chart-container').show();

                        const colors = ['#3498db', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6'];
                        const datasets = chartData.scenarios.map(function(scenario, index) {
                            return {
                                label: scenario.name,
                                data: scenario.profit || [],
                                backgroundColor: colors[index % colors.length],
                                borderColor: colors[index % colors.length],
                                borderWidth: 2,
                                fill: false
                            };
                        });

                        simulatorComparisonChart = new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: chartData.labels || [],
                                datasets: datasets
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: true, position: 'top' },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                return context.dataset.label + ': CHF ' + context.parsed.y.toLocaleString('en-US', {
                                                    minimumFractionDigits: 2,
                                                    maximumFractionDigits: 2
                                                });
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    x: { display: true, title: { display: true, text: 'Month' } },
                                    y: {
                                        display: true,
                                        title: { display: true, text: 'Net Profit/Loss (CHF)' },
                                        beginAtZero: false,
                                        ticks: {
                                            callback: function(value) {
                                                return 'CHF ' + value.toLocaleString('en-US', {
                                                    minimumFractionDigits: 0,
                                                    maximumFractionDigits: 0
                                                });
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    }

                    function initializeROIChart(chartData) {
                        const canvas = document.getElementById('simulatorROIChart');
                        if (!canvas) return;

                        if (simulatorROIChart) {
                            simulatorROIChart.destroy();
                        }

                        const ctx = canvas.getContext('2d');
                        $('#simulator-roi-chart-container').show();

                        simulatorROIChart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: chartData.labels,
                                datasets: [{
                                    label: 'Costs',
                                    data: chartData.costs,
                                    backgroundColor: '#e74c3c',
                                    borderColor: '#c0392b',
                                    borderWidth: 1
                                }, {
                                    label: 'Revenue Impact',
                                    data: chartData.revenue_impact,
                                    backgroundColor: '#2ecc71',
                                    borderColor: '#27ae60',
                                    borderWidth: 1
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: true, position: 'top' },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                return context.dataset.label + ': CHF ' + context.parsed.y.toLocaleString('en-US', {
                                                    minimumFractionDigits: 2,
                                                    maximumFractionDigits: 2
                                                });
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    x: { display: true, title: { display: true, text: 'Scenario' } },
                                    y: {
                                        display: true,
                                        title: { display: true, text: 'Amount (CHF)' },
                                        beginAtZero: true,
                                        ticks: {
                                            callback: function(value) {
                                                return 'CHF ' + value.toLocaleString('en-US', {
                                                    minimumFractionDigits: 0,
                                                    maximumFractionDigits: 0
                                                });
                                            }
                                        }
                                    }
                                }
                            }
                        });
                    }

                    function initializeReferralsChart(referralsData) {
                        const canvas = document.getElementById('simulatorReferralsChart');
                        if (!canvas || !referralsData) return;

                        if (simulatorReferralsChart) {
                            simulatorReferralsChart.destroy();
                        }

                        const ctx = canvas.getContext('2d');
                        $('#simulator-referrals-chart-container').show();

                        // If we have monthly data, show line chart; otherwise show doughnut
                        if (referralsData.labels && referralsData.customer && referralsData.coach) {
                            // Monthly line chart
                            simulatorReferralsChart = new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: referralsData.labels,
                                    datasets: [{
                                        label: 'Customer Referrals',
                                        data: referralsData.customer,
                                        borderColor: '#3498db',
                                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                                        borderWidth: 2,
                                        fill: true,
                                        tension: 0.4
                                    }, {
                                        label: 'Coach Referrals',
                                        data: referralsData.coach,
                                        borderColor: '#e74c3c',
                                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                                        borderWidth: 2,
                                        fill: true,
                                        tension: 0.4
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            display: true,
                                            position: 'top'
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    return context.dataset.label + ': ' + context.parsed.y;
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            title: {
                                                display: true,
                                                text: 'Number of Referrals'
                                            }
                                        },
                                        x: {
                                            title: {
                                                display: true,
                                                text: 'Month'
                                            }
                                        }
                                    }
                                }
                            });
                        } else {
                            // Fallback to doughnut chart for totals
                            simulatorReferralsChart = new Chart(ctx, {
                                type: 'doughnut',
                                data: {
                                    labels: [
                                        'Customer Referrals',
                                        'Coach Referrals'
                                    ],
                                    datasets: [{
                                        data: [referralsData.total_customer || referralsData.customer || 0, referralsData.total_coach || referralsData.coach || 0],
                                        backgroundColor: ['#3498db', '#e74c3c'],
                                        borderColor: ['#2980b9', '#c0392b'],
                                        borderWidth: 2
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            display: true,
                                            position: 'bottom'
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    const total = referralsData.total || 1;
                                                    const percentage = ((context.parsed / total) * 100).toFixed(1);
                                                    return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                        }
                    }

                    function initializeCommissionsChart(commissionsData) {
                        const canvas = document.getElementById('simulatorCommissionsChart');
                        if (!canvas || !commissionsData) return;

                        if (simulatorCommissionsChart) {
                            simulatorCommissionsChart.destroy();
                        }

                        const ctx = canvas.getContext('2d');
                        $('#simulator-commissions-chart-container').show();

                        // If we have monthly data, show line chart; otherwise show bar chart
                        if (commissionsData.labels && commissionsData.coach && commissionsData.partner) {
                            // Monthly line chart
                            const datasets = [];
                            const colors = [
                                { border: '#16a34a', fill: 'rgba(22, 163, 74, 0.1)' }, // Coach - green
                                { border: '#dc2626', fill: 'rgba(220, 38, 38, 0.1)' }, // Partner - red
                                { border: '#9333ea', fill: 'rgba(147, 51, 234, 0.1)' }  // Influencer - purple
                            ];

                            if (commissionsData.coach && commissionsData.coach.length > 0) {
                                datasets.push({
                                    label: 'Coach',
                                    data: commissionsData.coach,
                                    borderColor: colors[0].border,
                                    backgroundColor: colors[0].fill,
                                    borderWidth: 2,
                                    fill: true,
                                    tension: 0.4
                                });
                            }
                            if (commissionsData.partner && commissionsData.partner.length > 0) {
                                datasets.push({
                                    label: 'Partner',
                                    data: commissionsData.partner,
                                    borderColor: colors[1].border,
                                    backgroundColor: colors[1].fill,
                                    borderWidth: 2,
                                    fill: true,
                                    tension: 0.4
                                });
                            }
                            if (commissionsData.social_influencer && commissionsData.social_influencer.length > 0) {
                                datasets.push({
                                    label: 'Social Influencer',
                                    data: commissionsData.social_influencer,
                                    borderColor: colors[2].border,
                                    backgroundColor: colors[2].fill,
                                    borderWidth: 2,
                                    fill: true,
                                    tension: 0.4
                                });
                            }

                            if (datasets.length === 0) return;

                            simulatorCommissionsChart = new Chart(ctx, {
                                type: 'line',
                                data: {
                                    labels: commissionsData.labels,
                                    datasets: datasets
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            display: true,
                                            position: 'top'
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    return context.dataset.label + ': CHF ' + context.parsed.y.toLocaleString('en-US', {
                                                        minimumFractionDigits: 2,
                                                        maximumFractionDigits: 2
                                                    });
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            title: {
                                                display: true,
                                                text: 'Commissions (CHF)'
                                            },
                                            ticks: {
                                                callback: function(value) {
                                                    return 'CHF ' + value.toLocaleString('en-US', {
                                                        minimumFractionDigits: 0,
                                                        maximumFractionDigits: 0
                                                    });
                                                }
                                            }
                                        },
                                        x: {
                                            title: {
                                                display: true,
                                                text: 'Month'
                                            }
                                        }
                                    }
                                }
                            });
                        } else {
                            // Fallback to bar chart for totals
                            const labels = [];
                            const data = [];
                            const colors = ['#16a34a', '#dc2626', '#9333ea'];

                            if ((commissionsData.total_coach || commissionsData.coach) > 0) {
                                labels.push('Coach');
                                data.push(commissionsData.total_coach || commissionsData.coach || 0);
                            }
                            if ((commissionsData.total_partner || commissionsData.partner) > 0) {
                                labels.push('Partner');
                                data.push(commissionsData.total_partner || commissionsData.partner || 0);
                            }
                            if ((commissionsData.total_social_influencer || commissionsData.social_influencer) > 0) {
                                labels.push('Social Influencer');
                                data.push(commissionsData.total_social_influencer || commissionsData.social_influencer || 0);
                            }

                            if (data.length === 0) return;

                            simulatorCommissionsChart = new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        label: 'Commissions (CHF)',
                                        data: data,
                                        backgroundColor: colors.slice(0, data.length),
                                        borderColor: colors.slice(0, data.length),
                                        borderWidth: 1
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false,
                                    plugins: {
                                        legend: {
                                            display: false
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    return 'CHF ' + context.parsed.y.toLocaleString('en-US', {
                                                        minimumFractionDigits: 2,
                                                        maximumFractionDigits: 2
                                                    });
                                                }
                                            }
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            ticks: {
                                                callback: function(value) {
                                                    return 'CHF ' + value.toLocaleString('en-US', {
                                                        minimumFractionDigits: 0,
                                                        maximumFractionDigits: 0
                                                    });
                                                }
                                            }
                                        }
                                    }
                                }
                            });
                        }
                    }

                    function initializeSensitivityChart(sensitivityData) {
                        const canvas = document.getElementById('simulatorSensitivityChart');
                        if (!canvas || !sensitivityData) return;

                        if (simulatorSensitivityChart) {
                            simulatorSensitivityChart.destroy();
                        }

                        const ctx = canvas.getContext('2d');
                        $('#simulator-sensitivity-chart-container').show();

                        // Create horizontal bar chart showing impact of each variable
                        const labels = sensitivityData.labels || [];
                        const impacts = sensitivityData.impacts || [];

                        // Color bars based on positive/negative impact
                        const backgroundColors = impacts.map(impact => {
                            return impact >= 0 ? 'rgba(46, 204, 113, 0.7)' : 'rgba(231, 76, 60, 0.7)';
                        });
                        const borderColors = impacts.map(impact => {
                            return impact >= 0 ? 'rgba(39, 174, 96, 1)' : 'rgba(192, 57, 43, 1)';
                        });

                        simulatorSensitivityChart = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Impact on Net Profit (%)',
                                    data: impacts,
                                    backgroundColor: backgroundColors,
                                    borderColor: borderColors,
                                    borderWidth: 2
                                }]
                            },
                            options: {
                                indexAxis: 'y', // Horizontal bar chart
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        display: false
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                const value = context.parsed.x;
                                                const sign = value >= 0 ? '+' : '';
                                                return sign + value.toFixed(2) + '%';
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        beginAtZero: true,
                                        title: {
                                            display: true,
                                            text: 'Impact on Net Profit (%)'
                                        },
                                        ticks: {
                                            callback: function(value) {
                                                return value + '%';
                                            }
                                        }
                                    },
                                    y: {
                                        title: {
                                            display: true,
                                            text: 'Variable'
                                        }
                                    }
                                }
                            }
                        });
                    }

                    // Export handlers
                    $(document).on('click', '#simulator-export-excel', function(e) {
                        e.preventDefault();
                        console.log('Export to Excel clicked');
                        const settings = collectSimulatorSettings();
                        window.location.href = ajaxurl + '?action=intersoccer_export_simulator_excel&nonce=' + encodeURIComponent((window.intersoccer_admin && window.intersoccer_admin.simulator_nonce) ? window.intersoccer_admin.simulator_nonce : '') + '&settings=' + encodeURIComponent(JSON.stringify(settings));
                    });

                    $(document).on('click', '#simulator-export-pdf', function(e) {
                        e.preventDefault();
                        console.log('Export to PDF clicked');
                        const settings = collectSimulatorSettings();
                        window.location.href = ajaxurl + '?action=intersoccer_export_simulator_pdf&nonce=' + encodeURIComponent((window.intersoccer_admin && window.intersoccer_admin.simulator_nonce) ? window.intersoccer_admin.simulator_nonce : '') + '&settings=' + encodeURIComponent(JSON.stringify(settings));
                    });
                });

    });
})(jQuery);
