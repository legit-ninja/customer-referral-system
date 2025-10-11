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
    });

    /**
     * Initialize all dashboard components
     */
    function initializeDashboard() {
        if (typeof intersoccerChartData !== 'undefined') {
            initializeCharts();
        }

        // Initialize demo data handlers
        initializeDemoDataHandlers();
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

        charts.referralTrends = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Referrals',
                    data: data.referrals,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Completed',
                    data: data.completed,
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
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
                        title: {
                            display: true,
                            text: 'Count'
                        },
                        beginAtZero: true
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

    /**
     * Initialize demo data handlers
     */
    function initializeDemoDataHandlers() {
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

        $('#credit-reconciliation').on('click', function(e) {
            e.preventDefault();

            const $button = $(this);
            const originalText = $button.html();

            $button.html('<span class="dashicons dashicons-update spin"></span> Reconciling...').prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'intersoccer_credit_reconciliation',
                    nonce: intersoccer_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert('Credit reconciliation completed successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data);
                        $button.html(originalText).prop('disabled', false);
                    }
                },
                error: function() {
                    alert('An error occurred during credit reconciliation.');
                    $button.html(originalText).prop('disabled', false);
                }
            });
        });
    }

    /**
     * Bind general event handlers
     */
    function bindEvents() {
        // Add any additional event bindings here
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