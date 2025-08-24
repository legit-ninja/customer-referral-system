// assets/js/admin-dashboard.js

jQuery(document).ready(function($) {
    
    // Initialize Chart.js performance chart
    initializePerformanceChart();
    
    // Handle demo data population
    $('#populate-demo-data').on('click', function(e) {
        e.preventDefault();
        populateDemoData();
    });
    
    // Handle demo data clearing
    $('#clear-demo-data').on('click', function(e) {
        e.preventDefault();
        clearDemoData();
    });
    
    // Handle export data
    $('#export-data').on('click', function(e) {
        e.preventDefault();
        exportData();
    });
    
    // Handle ROI export
    $('#export-roi').on('click', function(e) {
        e.preventDefault();
        exportROIReport();
    });
    
    // Coach detail modals
    $('.view-details').on('click', function(e) {
        e.preventDefault();
        var coachId = $(this).data('coach-id');
        showCoachDetails(coachId);
    });
    
    // Send message functionality
    $('.send-message').on('click', function(e) {
        e.preventDefault();
        var coachId = $(this).data('coach-id');
        showMessageModal(coachId);
    });
    
    // Auto-refresh dashboard data every 5 minutes
    setInterval(refreshDashboardData, 300000);
    
    function initializePerformanceChart() {
        var ctx = document.getElementById('performanceChart');
        if (!ctx) return;
        
        // Chart.js fallback for missing data
        if (!performanceData || !performanceData.length) {
            performanceData = [
                {month: 'No Data Available', referrals: 0, commissions: 0}
            ];
        }
        
        var chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: performanceData.map(item => item.month),
                datasets: [{
                    label: 'Referrals',
                    data: performanceData.map(item => item.referrals),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#667eea',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }, {
                    label: 'Commissions (CHF)',
                    data: performanceData.map(item => item.commissions),
                    borderColor: '#764ba2',
                    backgroundColor: 'rgba(118, 75, 162, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#764ba2',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            font: {
                                size: 14,
                                weight: '500'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        borderColor: '#667eea',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: true
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 12,
                                weight: '500'
                            },
                            color: '#7f8c8d'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)',
                            drawBorder: false
                        },
                        ticks: {
                            font: {
                                size: 12,
                                weight: '500'
                            },
                            color: '#7f8c8d'
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                animation: {
                    duration: 1000,
                    easing: 'easeOutQuart'
                }
            }
        });
    }
    
    function populateDemoData() {
        var $button = $('#populate-demo-data');
        var originalText = $button.html();
        
        $button.html('<span class="dashicons dashicons-update spin"></span> Creating Demo Data...').prop('disabled', true);
        
        $.ajax({
            url: intersoccer_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'populate_demo_data',
                nonce: intersoccer_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('success', response.data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showNotification('error', response.data.message || 'Failed to create demo data');
                }
            },
            error: function() {
                showNotification('error', 'Ajax request failed');
            },
            complete: function() {
                $button.html(originalText).prop('disabled', false);
            }
        });
    }
    
    function clearDemoData() {
        if (!confirm('Are you sure you want to clear all demo data? This action cannot be undone.')) {
            return;
        }
        
        var $button = $('#clear-demo-data');
        var originalText = $button.html();
        
        $button.html('<span class="dashicons dashicons-update spin"></span> Clearing Data...').prop('disabled', true);
        
        $.ajax({
            url: intersoccer_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'clear_demo_data',
                nonce: intersoccer_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification('success', response.data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    showNotification('error', response.data.message || 'Failed to clear demo data');
                }
            },
            error: function() {
                showNotification('error', 'Ajax request failed');
            },
            complete: function() {
                $button.html(originalText).prop('disabled', false);
            }
        });
    }
    
    function exportData() {
        showNotification('info', 'Preparing data export...');
        
        // Create CSV data
        var csvData = 'Coach Name,Email,Referrals,Total Commission,Credits\n';
        
        $('.coach-card').each(function() {
            var name = $(this).find('h3').text();
            var email = $(this).find('p').first().text();
            var referrals = $(this).find('.stat .number').eq(0).text();
            var commission = $(this).find('.stat .number').eq(1).text();
            var credits = $(this).find('.stat .number').eq(2).text();
            
            csvData += name + ',' + email + ',' + referrals + ',' + commission + ',' + credits + '\n';
        });
        
        // Download CSV
        var blob = new Blob([csvData], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        var url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', 'intersoccer_coaches_' + new Date().toISOString().split('T')[0] + '.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        showNotification('success', 'Data exported successfully!');
    }
    
    function exportROIReport() {
        showNotification('info', 'Preparing ROI report...');
        
        // Create form for ROI export
        var form = $('<form>', {
            'method': 'POST',
            'action': intersoccer_ajax.ajax_url,
            'style': 'display: none;'
        });
        
        form.append($('<input>', {
            'type': 'hidden',
            'name': 'action',
            'value': 'export_roi_report'
        }));
        
        form.append($('<input>', {
            'type': 'hidden',
            'name': 'nonce',
            'value': intersoccer_ajax.nonce
        }));
        
        $('body').append(form);
        form.submit();
        form.remove();
        
        setTimeout(function() {
            showNotification('success', 'ROI report exported successfully!');
        }, 1000);
    }
    
    function showCoachDetails(coachId) {
        // Create modal HTML
        var modalHtml = `
            <div id="coach-details-modal" class="modal-overlay">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Coach Details</h2>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="loading-placeholder">
                            <div class="spinner"></div>
                            <p>Loading coach details...</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        
        // Load coach data via AJAX (placeholder for now)
        setTimeout(function() {
            $('#coach-details-modal .modal-body').html(`
                <div class="coach-detail-content">
                    <div class="coach-avatar-large">
                        <img src="https://www.gravatar.com/avatar/placeholder?s=80&d=mp" alt="Coach Avatar">
                    </div>
                    <div class="coach-metrics">
                        <div class="metric">
                            <span class="label">Total Referrals:</span>
                            <span class="value">15</span>
                        </div>
                        <div class="metric">
                            <span class="label">This Month:</span>
                            <span class="value">3</span>
                        </div>
                        <div class="metric">
                            <span class="label">Conversion Rate:</span>
                            <span class="value">12.5%</span>
                        </div>
                        <div class="metric">
                            <span class="label">Total Earnings:</span>
                            <span class="value">1,250 CHF</span>
                        </div>
                    </div>
                    <div class="coach-actions-modal">
                        <button class="button button-primary">Send Message</button>
                        <button class="button">View Full Report</button>
                    </div>
                </div>
            `);
        }, 1000);
    }
    
    function showMessageModal(coachId) {
        var modalHtml = `
            <div id="message-modal" class="modal-overlay">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Send Message to Coach</h2>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="coach-message-form">
                            <div class="form-group">
                                <label for="message-subject">Subject:</label>
                                <input type="text" id="message-subject" name="subject" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="message-content">Message:</label>
                                <textarea id="message-content" name="content" class="form-control" rows="5" required></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="button button-primary">Send Message</button>
                                <button type="button" class="button modal-close">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
    }
    
    // Modal close functionality
    $(document).on('click', '.modal-close, .modal-overlay', function(e) {
        if (e.target === this) {
            $('.modal-overlay').remove();
        }
    });
    
    // Prevent modal content clicks from closing modal
    $(document).on('click', '.modal-content', function(e) {
        e.stopPropagation();
    });
    
    function refreshDashboardData() {
        // Placeholder for auto-refresh functionality
        console.log('Refreshing dashboard data...');
    }
    
    function showNotification(type, message) {
        var notificationClass = type === 'success' ? 'notice-success' : 
                               type === 'error' ? 'notice-error' : 'notice-info';
        
        var notification = $('<div class="' + notificationClass + '">' + message + '</div>');
        
        $('.intersoccer-admin').prepend(notification);
        
        setTimeout(function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Add some CSS for modals and animations
    var modalStyles = `
        <style>
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            max-height: 80%;
            overflow-y: auto;
            animation: slideIn 0.3s ease;
        }
        
        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e1e5e9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            color: #2c3e50;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #7f8c8d;
            line-height: 1;
        }
        
        .modal-close:hover {
            color: #2c3e50;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .loading-placeholder {
            text-align: center;
            padding: 40px;
        }
        
        .spinner {
            width: 30px;
            height: 30px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        .coach-detail-content {
            text-align: center;
        }
        
        .coach-avatar-large img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid #667eea;
            margin-bottom: 20px;
        }
        
        .coach-metrics {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .metric {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .metric .label {
            display: block;
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        
        .metric .value {
            display: block;
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .coach-actions-modal {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e1e5e9;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .form-control:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .spin {
            animation: spin 1s linear infinite;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .notice-info {
            background: #cce5ff;
            color: #004085;
            border-left: 4px solid #007bff;
            padding: 12px 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        </style>
    `;
    
    $('head').append(modalStyles);
});