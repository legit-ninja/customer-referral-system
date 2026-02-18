/**
 * InterSoccer Referral System Admin Dashboard JavaScript
 * Handles chart initialization and interactive dashboard features
 */

(function($) {
    'use strict';

    // Chart instances for cleanup
    let charts = {};

    // Initialize dashboard when document is ready
    $(document).ready(function() {
        initializeDashboard();
        bindEvents();
        initCoachEventAssignmentsTabs();
        initCoachSearchFilter();
        initCoachImportModal();
        initCoachCardActions();
        initCoachEventsAdmin();
        initCoachAssignmentsAdmin();
    });

    /**
     * Initialize all dashboard components
     */
    function initializeDashboard() {
        if (typeof intersoccerChartData !== 'undefined') {
            initializeCharts();
        }

    }

    /**
     * Initialize all Chart.js charts
     */
    function initializeCharts() {
        // Referral Trends Chart
        if ($('#referralTrendsChart').length && intersoccerChartData.referral_trends) {
            initializeReferralTrendsChart();
        }

        // Financial Performance Chart
        if ($('#financialChart').length && intersoccerChartData.financial_performance) {
            initializeFinancialChart();
        }

        // Coach Performance Chart
        if ($('#coachPerformanceChart').length && intersoccerChartData.coach_performance) {
            initializeCoachPerformanceChart();
        }

        // Credit Distribution Chart
        if ($('#creditDistributionChart').length && intersoccerChartData.credit_distribution) {
            initializeCreditDistributionChart();
        }

        // Redemption Activity Chart
        if ($('#redemptionActivityChart').length && intersoccerChartData.redemption_activity) {
            initializeRedemptionActivityChart();
        }
    }

    /**
     * Initialize Referral Trends Chart
     */
    function initializeReferralTrendsChart() {
        const ctx = document.getElementById('referralTrendsChart').getContext('2d');
        const data = intersoccerChartData.referral_trends;

        const datasets = [
            {
                label: 'Referrals',
                data: data.referrals,
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'Completed',
                data: data.completed,
                borderColor: '#27ae60',
                backgroundColor: 'rgba(39, 174, 96, 0.1)',
                tension: 0.4,
                fill: true
            }
        ];
        if (data.points_earned && data.points_earned.length) {
            datasets.push({
                label: 'Points from purchases',
                data: data.points_earned,
                borderColor: '#e67e22',
                backgroundColor: 'rgba(230, 126, 34, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'yPoints'
            });
        }
        charts.referralTrends = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
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
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y;
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
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Count'
                        },
                        beginAtZero: true
                    },
                    yPoints: {
                        display: !!data.points_earned && data.points_earned.length > 0,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Points'
                        },
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });
    }

    /**
     * Initialize Financial Performance Chart
     */
    function initializeFinancialChart() {
        const ctx = document.getElementById('financialChart').getContext('2d');
        const data = intersoccerChartData.financial_performance;

        charts.financial = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Commission Revenue',
                    data: data.revenue,
                    backgroundColor: '#f39c12',
                    borderColor: '#e67e22',
                    borderWidth: 1
                }, {
                    label: 'Redemption Costs',
                    data: data.costs,
                    backgroundColor: '#9b59b6',
                    borderColor: '#8e44ad',
                    borderWidth: 1
                }, {
                    label: 'Net Profit/Loss',
                    data: data.profit,
                    backgroundColor: function(context) {
                        const value = context.parsed.y;
                        return value >= 0 ? '#27ae60' : '#e74c3c';
                    },
                    borderColor: function(context) {
                        const value = context.parsed.y;
                        return value >= 0 ? '#229954' : '#c0392b';
                    },
                    borderWidth: 1
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
                                return context.dataset.label + ': CHF ' + context.parsed.y.toLocaleString();
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
                                return 'CHF ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Initialize Coach Performance Chart
     */
    function initializeCoachPerformanceChart() {
        const ctx = document.getElementById('coachPerformanceChart').getContext('2d');
        const data = intersoccerChartData.coach_performance;

        charts.coachPerformance = new Chart(ctx, {
            type: 'horizontalBar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Referrals',
                    data: data.referrals,
                    backgroundColor: '#3498db',
                    borderColor: '#2980b9',
                    borderWidth: 1
                }, {
                    label: 'Commission (CHF)',
                    data: data.commissions,
                    backgroundColor: '#27ae60',
                    borderColor: '#229954',
                    borderWidth: 1
                }]
            },
            options: {
                indexAxis: 'y',
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
                                if (context.datasetIndex === 0) {
                                    return context.dataset.label + ': ' + context.parsed.x;
                                } else {
                                    return context.dataset.label + ': CHF ' + context.parsed.x.toLocaleString();
                                }
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Value'
                        },
                        beginAtZero: true
                    },
                    y: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Coach'
                        }
                    }
                }
            }
        });
    }

    /**
     * Initialize Credit Distribution Chart
     */
    function initializeCreditDistributionChart() {
        const ctx = document.getElementById('creditDistributionChart').getContext('2d');
        const data = intersoccerChartData.credit_distribution;

        charts.creditDistribution = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.values,
                    backgroundColor: [
                        '#3498db',
                        '#27ae60',
                        '#f39c12',
                        '#9b59b6',
                        '#e74c3c',
                        '#1abc9c'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': CHF ' + context.parsed.toLocaleString() + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Initialize Redemption Activity Chart
     */
    function initializeRedemptionActivityChart() {
        const ctx = document.getElementById('redemptionActivityChart').getContext('2d');
        const data = intersoccerChartData.redemption_activity;

        charts.redemptionActivity = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Credits Earned',
                    data: data.earned,
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Credits Redeemed',
                    data: data.redeemed,
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    tension: 0.4,
                    fill: true
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
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': CHF ' + context.parsed.y.toLocaleString();
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
                                return 'CHF ' + value.toLocaleString();
                            }
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });
    }

    function old_initializeDemoDataHandlers() {
        $('#populate-demo-data').on('click', function(e) {
            e.preventDefault();

            if (!confirm('This will populate the database with demo data. Continue?')) {
                return;
            }

            const $button = $(this);
            const originalText = $button.html();

            $button.html('<span class="dashicons dashicons-update spin"></span> Populating...').prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'intersoccer_populate_demo_data',
                    nonce: intersoccer_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Demo data populated successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                        $button.html(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    alert('An error occurred while populating demo data.');
                    $button.html(originalText).prop('disabled', false);
                }
            });
        });

        $('#clear-demo-data').on('click', function(e) {
            e.preventDefault();

            if (!confirm('This will clear all demo data. Continue?')) {
                return;
            }

            const $button = $(this);
            const originalText = $button.html();

            $button.html('<span class="dashicons dashicons-update spin"></span> Clearing...').prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'intersoccer_clear_demo_data',
                    nonce: intersoccer_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Demo data cleared successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                        $button.html(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    alert('An error occurred while clearing demo data.');
                    $button.html(originalText).prop('disabled', false);
                }
            });
        });

        $('#export-data').on('click', function(e) {
            e.preventDefault();

            const $button = $(this);
            const originalText = $button.html();

            $button.html('<span class="dashicons dashicons-download"></span> Exporting...').prop('disabled', true);

            // Create a temporary link to download the export
            const exportUrl = ajaxurl + '?action=intersoccer_export_data&nonce=' + intersoccer_admin.nonce;
            const link = document.createElement('a');
            link.href = exportUrl;
            link.download = 'intersoccer-referral-data-' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            $button.html(originalText).prop('disabled', false);
        });

        $(document).on('click', '.intersoccer-eligibility-override', function(e) {
            e.preventDefault();

            const $button = $(this);
            if ($button.prop('disabled')) {
                return;
            }

            const referralId = $button.data('referralId');
            const orderId = $button.data('orderId');
            const targetStatus = $button.data('targetStatus');

            if (!referralId || !orderId || !targetStatus) {
                alert('Unable to determine referral eligibility context.');
                return;
            }

            const message = targetStatus === 'eligible'
                ? 'Mark this referral as eligible? This will allow rewards to proceed when the order is completed.'
                : 'Mark this referral as ineligible? This prevents rewards from being issued.';

            if (!confirm(message)) {
                return;
            }

            let note = '';
            if (targetStatus === 'ineligible') {
                note = window.prompt('Optional note for audit log (leave blank to skip):', '');
                if (note === null) {
                    return;
                }
            }

            $button.prop('disabled', true).addClass('loading');

            $.ajax({
                url: intersoccer_admin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'intersoccer_update_referral_eligibility',
                    nonce: intersoccer_admin.nonce,
                    referral_id: referralId,
                    order_id: orderId,
                    target_status: targetStatus,
                    note: note
                }
            }).done(function(response) {
                if (response && response.success && response.data) {
                    const $container = $button.closest('.eligibility-status');
                    if (response.data.html) {
                        $container.replaceWith(response.data.html);
                    }

                    if (response.data.referral_status) {
                        const $row = $button.closest('tr');
                        const $statusBadge = $row.find('.status-badge');
                        if ($statusBadge.length) {
                            $statusBadge
                                .text(response.data.referral_status_label || response.data.referral_status)
                                .removeClass('pending ineligible eligible completed')
                                .addClass(response.data.referral_status);
                        }
                    }
                } else if (response && response.data && response.data.message) {
                    alert(response.data.message);
                } else {
                    alert('Unable to update referral eligibility. Please try again.');
                }
            }).fail(function() {
                alert('An unexpected error occurred while updating eligibility.');
            }).always(function() {
                $button.prop('disabled', false).removeClass('loading');
            });
        });
    }

    /**
     * Bind general event handlers
     */
    function bindEvents() {
    }

    /**
     * Coach Event Assignments page tabs.
     */
    function initCoachEventAssignmentsTabs() {
        const tabs = document.querySelectorAll('.coach-event-tabs .nav-tab');
        if (!tabs || tabs.length === 0) {
            return;
        }

        const panels = document.querySelectorAll('.coach-event-panel');

        tabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = tab.getAttribute('data-target');

                tabs.forEach(t => t.classList.remove('nav-tab-active'));
                panels.forEach(panel => panel.classList.remove('active'));

                tab.classList.add('nav-tab-active');
                const panel = document.getElementById(targetId);
                if (panel) {
                    panel.classList.add('active');
                }
            });
        });
    }

    /**
     * Coaches page: filter coach cards based on the search input.
     */
    function initCoachSearchFilter() {
        const searchInput = document.getElementById('coach-search-input');
        const statusEl = document.querySelector('.coaches-search-status');
        const cards = Array.from(document.querySelectorAll('.coach-card'));

        if (!searchInput || cards.length === 0) {
            return;
        }

        function updateStatus(filteredCount) {
            if (!statusEl) {
                return;
            }
            if (searchInput.value.trim() === '') {
                statusEl.textContent = '';
            } else if (filteredCount === 0) {
                statusEl.textContent = 'No coaches match your search.';
            } else {
                statusEl.textContent = `${filteredCount} coach${filteredCount === 1 ? '' : 'es'} found.`;
            }
        }

        function filterCoaches() {
            const term = searchInput.value.trim().toLowerCase();
            let visible = 0;

            cards.forEach(card => {
                const haystack = (card.dataset.search || '').toLowerCase();
                const matches = term === '' || haystack.includes(term);
                card.style.display = matches ? '' : 'none';
                if (matches) {
                    visible++;
                }
            });

            updateStatus(visible);
        }

        searchInput.addEventListener('input', filterCoaches);
    }

    /**
     * Coaches page: CSV import modal + form submission.
     */
    function initCoachImportModal() {
        if (typeof intersoccer_admin === 'undefined') {
            return;
        }

        const $modal = $('#coach-import-modal');
        const $open = $('#import-coaches-btn');
        const $cancel = $('#cancel-import-btn');
        const $form = $('#coach-import-form-modal');

        if ($open.length === 0 || $modal.length === 0 || $form.length === 0) {
            return;
        }

        $open.on('click', function() {
            $modal.show();
        });

        $cancel.on('click', function() {
            $modal.hide();
            $form[0].reset();
            $('#import-status-modal').hide();
            $('#import-results-modal').hide();
        });

        $form.on('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const $submitBtn = $('#import-submit-btn-modal');
            const originalText = $submitBtn.html();
            const $importStatus = $('#import-status-modal');
            const $importResults = $('#import-results-modal');
            const $progressFill = $('#progress-fill-modal');
            const $progressText = $('#progress-text-modal');

            formData.append('action', 'import_coaches_from_csv');

            $importResults.hide();
            $progressFill.css('width', '0%');
            $progressText.text('Preparing import...');
            $submitBtn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Importing...');
            $importStatus.show();

            $.ajax({
                url: intersoccer_admin.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false
            }).done(function(response) {
                $progressFill.css('width', '100%');
                $progressText.text('Import completed!');
                $submitBtn.prop('disabled', false).html(originalText);

                if (response && response.success) {
                    let resultsHtml = '<h4>Import Results</h4>';
                    resultsHtml += '<p><strong>Created:</strong> ' + (response.data.created ? response.data.created.length : 0) + '</p>';
                    resultsHtml += '<p><strong>Updated:</strong> ' + (response.data.updated ? response.data.updated.length : 0) + '</p>';
                    resultsHtml += '<p><strong>Skipped:</strong> ' + (response.data.skipped ? response.data.skipped.length : 0) + '</p>';
                    resultsHtml += '<p><strong>Errors:</strong> ' + (response.data.errors ? response.data.errors.length : 0) + '</p>';

                    if (response.data.errors && response.data.errors.length > 0) {
                        resultsHtml += '<h5>Errors:</h5><ul>';
                        response.data.errors.forEach(function(error) {
                            resultsHtml += '<li>' + error + '</li>';
                        });
                        resultsHtml += '</ul>';
                    }

                    $('#import-summary-content-modal').html(resultsHtml);
                    $importResults.show();

                    // Reload page after 3 seconds if successful (no errors)
                    if (response.data.errors && response.data.errors.length === 0) {
                        setTimeout(function() {
                            window.location.reload();
                        }, 3000);
                    }
                } else {
                    window.alert('Import failed: ' + ((response && response.data) ? response.data : 'Unknown error'));
                }
            }).fail(function(xhr, status, error) {
                $importStatus.hide();
                $submitBtn.prop('disabled', false).html(originalText);
                const details = xhr && xhr.responseText ? '\n' + xhr.responseText.substring(0, 200) : '';
                window.alert('AJAX Error: ' + error + details);
            });
        });
    }

    /**
     * Coaches page: per-card actions.
     */
    function initCoachCardActions() {
        if (typeof intersoccer_admin === 'undefined') {
            return;
        }

        initCoachBulkActions();
        initCoachReferralCodeSend();

        $(document).on('click', '.edit-coach', function() {
            const coachId = $(this).data('coach-id');
            if (!coachId) return;
            window.location.href = 'user-edit.php?user_id=' + coachId;
        });

        $(document).on('click', '.message-coach', function() {
            const coachId = $(this).data('coach-id');
            window.alert('Message functionality coming soon for coach ID: ' + coachId);
        });

        $(document).on('click', '.deactivate-coach', function() {
            const coachId = $(this).data('coach-id');
            if (!coachId) return;

            if (!window.confirm('Are you sure you want to deactivate this coach?')) {
                return;
            }

            $.ajax({
                url: intersoccer_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'deactivate_coach',
                    coach_id: coachId,
                    nonce: intersoccer_admin.nonce
                }
            }).done(function(response) {
                if (response && response.success) {
                    window.location.reload();
                } else {
                    const msg = response && response.data && response.data.message ? response.data.message : 'Unknown error';
                    window.alert('Error deactivating coach: ' + msg);
                }
            });
        });

        $(document).on('click', '#add-new-coach-link', function(e) {
            e.preventDefault();
            window.location.href = 'user-new.php';
        });
    }

    /**
     * Coaches page: show/hide bulk actions when checkboxes change.
     */
    function initCoachBulkActions() {
        const $bulkBar = $('.coach-bulk-actions');
        const $checkboxes = $('.coach-checkbox');
        const $sendSelected = $('#send-referral-selected');

        function toggleBulkBar() {
            const checked = $checkboxes.filter(':checked').length;
            $bulkBar.toggle(checked > 0);
        }

        $checkboxes.on('change', toggleBulkBar);
        toggleBulkBar();

        $sendSelected.on('click', function() {
            const ids = $checkboxes.filter(':checked').map(function() { return parseInt($(this).val(), 10); }).get();
            if (ids.length === 0) {
                window.alert('Please select at least one coach.');
                return;
            }
            sendReferralCodes({ coach_ids: ids }, $(this));
        });

        $('#send-referral-all').on('click', function() {
            if (!window.confirm('Send referral code email to all coaches?')) return;
            sendReferralCodes({ send_all: '1' }, $(this));
        });
    }

    /**
     * Coaches page: send referral code (single coach or bulk via AJAX).
     */
    function initCoachReferralCodeSend() {
        $(document).on('click', '.send-referral-code', function() {
            const coachId = $(this).data('coach-id');
            if (!coachId) return;
            sendReferralCodes({ coach_id: coachId }, $(this));
        });
    }

    /**
     * AJAX helper: send referral codes to coaches.
     * @param {Object} payload - { coach_id, coach_ids, or send_all }
     * @param {jQuery} $trigger - Optional button that triggered the action (to show loading state)
     */
    function sendReferralCodes(payload, $trigger) {
        const data = $.extend({
            action: 'send_referral_code',
            nonce: intersoccer_admin.nonce
        }, payload);

        const $buttons = $trigger ? $trigger : $('.send-referral-code, #send-referral-selected, #send-referral-all');
        const originalStates = [];
        $buttons.each(function() {
            const $b = $(this);
            originalStates.push({ $el: $b, html: $b.html(), disabled: $b.prop('disabled') });
            $b.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span>');
        });

        $.ajax({
            url: intersoccer_admin.ajax_url,
            type: 'POST',
            data: data
        }).done(function(response) {
            if (response && response.success) {
                const msg = response.data && response.data.message ? response.data.message : 'Referral code(s) sent.';
                if (response.data && response.data.results && response.data.results.length > 1) {
                    const failed = response.data.results.filter(function(r) { return !r.success; });
                    if (failed.length > 0) {
                        const details = failed.map(function(r) { return r.message; }).join('\n');
                        window.alert(msg + '\n\nFailures:\n' + details);
                    } else {
                        window.alert(msg);
                    }
                } else {
                    window.alert(msg);
                }
                $('.coach-checkbox').prop('checked', false);
                $('.coach-bulk-actions').hide();
            } else {
                const err = response && response.data && response.data.message ? response.data.message : 'An error occurred.';
                window.alert('Error: ' + err);
            }
        }).fail(function(xhr) {
            const err = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                ? xhr.responseJSON.data.message : 'Request failed. Please try again.';
            window.alert('Error: ' + err);
        }).always(function() {
            originalStates.forEach(function(s) {
                s.$el.prop('disabled', s.disabled).html(s.html);
            });
        });
    }

    /**
     * Coach Events admin UI.
     */
    function initCoachEventsAdmin() {
        if ($('#coach-events-form').length === 0) {
            return;
        }

        const nonce = ($('#coach-events-form input[name="nonce"]').val() || '').toString();
        const actions = {
            list: 'intersoccer_get_coach_events',
            save: 'intersoccer_save_coach_event',
            delete: 'intersoccer_delete_coach_event',
            status: 'intersoccer_update_coach_event_status',
            search: 'intersoccer_search_events'
        };

        function t(key, fallback) {
            if (typeof intersoccer_admin !== 'undefined' && intersoccer_admin.i18n && intersoccer_admin.i18n[key]) {
                return intersoccer_admin.i18n[key];
            }
            return fallback;
        }

        function refreshAssignments() {
            $.post(ajaxurl, { action: actions.list, nonce: nonce })
                .done(function(response) {
                    if (response && response.success && response.data && response.data.html) {
                        $('#coach-events-list').html(response.data.html);
                    }
                });
        }

        $('#coach-events-form').on('submit', function(e) {
            e.preventDefault();
            const $form = $(this);
            const $spinner = $('#coach-events-spinner');

            if (!$('#event-id').val()) {
                window.alert(t('coach_events_select_event', 'Please select an event before saving.'));
                return;
            }

            $spinner.addClass('is-active');

            const payload = {
                action: actions.save,
                nonce: nonce,
                coach_id: $('#coach-id').val(),
                event_id: $('#event-id').val(),
                event_type: $('#event-type').val(),
                status: $('#event-status').val(),
                notes: $('#event-notes').val()
            };

            $.post(ajaxurl, payload)
                .done(function(response) {
                    if (response && response.success) {
                        $form[0].reset();
                        $('#event-search-results').empty();
                        refreshAssignments();
                    } else {
                        window.alert((response && response.data) ? response.data : t('coach_events_save_error', 'Error saving event'));
                    }
                })
                .fail(function() {
                    window.alert(t('coach_events_save_network_error', 'Network error while saving event'));
                })
                .always(function() {
                    $spinner.removeClass('is-active');
                });
        });

        $('#event-search-button').on('click', function() {
            const term = $('#event-search-input').val();
            if (!term || term.length < 2) {
                window.alert(t('coach_events_search_min_chars', 'Enter at least two characters to search.'));
                return;
            }

            $('#event-search-results').html('<p>' + t('coach_events_searching', 'Searching…') + '</p>');

            $.post(ajaxurl, { action: actions.search, nonce: nonce, term: term })
                .done(function(response) {
                    if (!response || !response.success || !response.data || !response.data.results) {
                        $('#event-search-results').html('<p>' + t('coach_events_no_events', 'No events found.') + '</p>');
                        return;
                    }

                    const results = response.data.results;
                    if (!results.length) {
                        $('#event-search-results').html('<p>' + t('coach_events_no_events', 'No events found.') + '</p>');
                        return;
                    }

                    const list = $('<div/>');
                    results.forEach(function(item) {
                        const element = $('<div class="event-search-result-item" tabindex="0" />');
                        element.text(item.title + ' (ID: ' + item.id + ')');
                        element.data('eventId', item.id);
                        element.data('eventType', item.type);
                        list.append(element);
                    });
                    $('#event-search-results').html(list);
                })
                .fail(function() {
                    $('#event-search-results').html('<p>' + t('coach_events_search_failed', 'Search failed. Please try again.') + '</p>');
                });
        });

        $('#event-search-results').on('click keypress', '.event-search-result-item', function(e) {
            if (e.type === 'click' || e.key === 'Enter') {
                const $item = $(this);
                $('#event-id').val($item.data('eventId'));
                $('#event-type').val($item.data('eventType'));
                $('#event-search-input').val($item.text());
                $('#event-search-results').empty();
            }
        });

        $('#coach-events-list').on('click', '.coach-event-delete', function(e) {
            e.preventDefault();
            if (!window.confirm(t('coach_events_remove_confirm', 'Remove this event assignment?'))) {
                return;
            }
            const assignmentId = $(this).data('id');
            $.post(ajaxurl, { action: actions.delete, nonce: nonce, assignment_id: assignmentId })
                .done(function(response) {
                    if (response && response.success) {
                        refreshAssignments();
                    } else {
                        window.alert((response && response.data) ? response.data : t('coach_events_remove_error', 'Error removing event'));
                    }
                });
        });

        $('#coach-events-list').on('click', '.coach-event-copy', function(e) {
            e.preventDefault();
            const link = $(this).data('link');
            if (!link) {
                return;
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(link)
                    .then(function() {
                        window.alert(t('coach_events_link_copied', 'Link copied to clipboard.'));
                    })
                    .catch(function() {
                        window.prompt(t('coach_events_press_ctrl_c', 'Press Ctrl+C to copy the link'), link);
                    });
            } else {
                window.prompt(t('coach_events_press_ctrl_c', 'Press Ctrl+C to copy the link'), link);
            }
        });

        $('#coach-events-list').on('change', '.coach-event-status-select', function() {
            const $select = $(this);
            const assignmentId = $select.data('id');
            $.post(ajaxurl, { action: actions.status, nonce: nonce, assignment_id: assignmentId, status: $select.val() })
                .done(function(response) {
                    if (!response || !response.success) {
                        window.alert((response && response.data) ? response.data : t('coach_events_status_error', 'Error updating status'));
                        refreshAssignments();
                    } else {
                        // Update status badge text without reload
                        $select.closest('tr').find('.coach-events-status').text($select.val()).attr('class', 'coach-events-status ' + $select.val());
                    }
                })
                .fail(function() {
                    window.alert(t('coach_events_status_network_error', 'Network error updating status'));
                    refreshAssignments();
                });
        });
    }

    /**
     * Coach Assignments admin UI.
     */
    function initCoachAssignmentsAdmin() {
        if ($('#coach-assignment-form').length === 0) {
            return;
        }

        const $form = $('#coach-assignment-form');

        // Handle form submission
        $form.on('submit', function(e) {
            e.preventDefault();

            const $submit = $form.find('#submit');
            const $spinner = $('#assignment-spinner');

            $submit.prop('disabled', true);
            $spinner.addClass('is-active');

            const formData = {
                action: 'save_coach_assignments',
                nonce: $form.find('#nonce').val(),
                coach_id: $('#coach_id').val(),
                venue: $('#venue').val(),
                assignment_type: $('#assignment_type').val(),
                canton: $('#canton').val()
            };

            $.post(ajaxurl, formData)
                .done(function(response) {
                    if (response && response.success) {
                        loadAssignments();
                        $form[0].reset();
                        window.alert('Assignment added successfully!');
                    } else {
                        window.alert('Error: ' + (response && response.data ? response.data : 'Unknown error'));
                    }
                })
                .fail(function() {
                    window.alert('Network error occurred.');
                })
                .always(function() {
                    $submit.prop('disabled', false);
                    $spinner.removeClass('is-active');
                });
        });

        // Load assignments list
        function loadAssignments() {
            $.post(ajaxurl, {
                action: 'get_coach_assignments',
                nonce: $form.find('#nonce').val()
            }).done(function(response) {
                if (response && response.success) {
                    $('#assignments-list').html(response.data);
                }
            });
        }

        // Delete assignment
        $('#assignments-list').on('click', '.delete-assignment', function(e) {
            e.preventDefault();

            if (!window.confirm('Are you sure you want to delete this assignment?')) {
                return;
            }

            const assignmentId = $(this).data('assignment-id');

            $.post(ajaxurl, {
                action: 'delete_coach_assignment',
                nonce: $form.find('#nonce').val(),
                assignment_id: assignmentId
            }).done(function(response) {
                if (response && response.success) {
                    loadAssignments();
                } else {
                    window.alert('Error deleting assignment: ' + (response && response.data ? response.data : 'Unknown error'));
                }
            });
        });
    }

    /**
     * Cleanup function for when the page is unloaded
     */
    $(window).on('beforeunload', function() {
        // Destroy all charts to prevent memory leaks
        Object.values(charts).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });
        charts = {};
    });

})(jQuery);