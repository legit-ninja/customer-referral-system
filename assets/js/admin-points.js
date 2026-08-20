/* global jQuery, intersoccer_admin */

jQuery(document).ready(function($) {
    let currentPage = 1;
    let currentFilter = 'all';
    let currentSearch = '';

    // Load initial data
    loadPointsUsers();

    // Refresh button
    $('#refresh-points-table').on('click', function() {
        loadPointsUsers();
    });

    // Filter change
    $('#points-filter').on('change', function() {
        currentFilter = $(this).val();
        currentPage = 1;
        loadPointsUsers();
    });

    // Search input
    let searchTimeout;
    $('#points-search').on('input', function() {
        clearTimeout(searchTimeout);
        currentSearch = $(this).val();
        searchTimeout = setTimeout(function() {
            currentPage = 1;
            loadPointsUsers();
        }, 500);
    });

    // Clear filters
    $('#clear-filters').on('click', function() {
        $('#points-filter').val('all');
        $('#points-search').val('');
        currentFilter = 'all';
        currentSearch = '';
        currentPage = 1;
        loadPointsUsers();
    });

    // Export report
    $('#export-points-report').on('click', function() {
        window.open(intersoccer_admin.ajax_url + '?action=export_points_report&nonce=' + intersoccer_admin.nonce, '_blank');
    });

    function loadPointsUsers() {
        $('#points-table-body').html('<tr><td colspan="7" style="text-align: center; padding: 40px;"><div class="spinner is-active" style="float: none; margin: 0 auto;"></div><p>Loading customer points data...</p></td></tr>');

        $.ajax({
            url: intersoccer_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'get_points_users',
                filter: currentFilter,
                search: currentSearch,
                page: currentPage,
                nonce: intersoccer_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderPointsTable(response.data.users);
                    $('#total-customers').text(response.data.total);
                } else {
                    $('#points-table-body').html('<tr><td colspan="7" style="text-align: center; color: #e74c3c;">Error loading data: ' + response.data.message + '</td></tr>');
                }
            },
            error: function() {
                $('#points-table-body').html('<tr><td colspan="7" style="text-align: center; color: #e74c3c;">Error loading customer points data</td></tr>');
            }
        });
    }

    function renderPointsTable(users) {
        if (!users || users.length === 0) {
            $('#points-table-body').html('<tr><td colspan="7" style="text-align: center; padding: 40px;">No customers found matching your criteria.</td></tr>');
            return;
        }

        let html = '';
        users.forEach(function(user) {
            const pointsClass = user.current_points > 0 ? 'points-positive' :
                user.current_points < 0 ? 'points-negative' : 'points-zero';

            html += '<tr>';
            html += '<td><strong>' + user.display_name + '</strong></td>';
            html += '<td>' + user.user_email + '</td>';
            html += '<td style="text-align: center;"><span class="points-amount ' + pointsClass + '">' + formatPoints(user.current_points) + '</span></td>';
            html += '<td style="text-align: center;"><span class="points-amount points-positive">' + formatPoints(user.total_earned) + '</span></td>';
            html += '<td style="text-align: center;"><span class="points-amount points-negative">' + formatPoints(Math.abs(user.total_spent)) + '</span></td>';
            html += '<td>' + (user.last_activity ? formatDate(user.last_activity) : 'Never') + '</td>';
            html += '<td>';
            html += '<div class="points-actions">';
            html += '<button class="button points-action-btn adjust-points" data-user-id="' + user.ID + '" data-user-name="' + user.display_name + '">Adjust</button>';
            html += '<button class="button points-action-btn view-history" data-user-id="' + user.ID + '" data-user-name="' + user.display_name + '">History</button>';
            html += '</div>';
            html += '</td>';
            html += '</tr>';
        });

        $('#points-table-body').html(html);
    }

    function formatPoints(points) {
        return parseFloat(points).toFixed(2);
    }

    function formatDate(dateString) {
        if (!dateString) return 'Never';
        const date = new Date(dateString);
        return date.toLocaleDateString();
    }

    // Points adjustment modal
    $(document).on('click', '.adjust-points', function() {
        const userId = $(this).data('user-id');
        const userName = $(this).data('user-name');

        $('#customer-info').html('<p><strong>Customer:</strong> ' + userName + '</p>');
        $('#points-adjustment-form').data('user-id', userId);
        $('#points-adjustment-modal').show();
    });

    $(document).on('click', '.modal-close', function() {
        $(this).closest('.intersoccer-modal').hide();
    });

    $(document).on('click', '.intersoccer-modal', function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });

    $('#points-adjustment-form').on('submit', function(e) {
        e.preventDefault();

        const userId = $(this).data('user-id');
        const formData = $(this).serializeArray();
        formData.push({name: 'user_id', value: userId});
        formData.push({name: 'nonce', value: intersoccer_admin.nonce});

        $.ajax({
            url: intersoccer_admin.ajax_url,
            type: 'POST',
            data: formData.concat([{name: 'action', value: 'adjust_user_points'}]),
            success: function(response) {
                if (response.success) {
                    $('#points-adjustment-modal').hide();
                    loadPointsUsers();
                    alert('Points adjusted successfully!');
                } else {
                    alert('Error adjusting points: ' + response.data.message);
                }
            },
            error: function() {
                alert('Error adjusting points');
            }
        });
    });

    function escapeHtml(value) {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatSignedPoints(points) {
        const amount = parseInt(points, 10) || 0;
        if (amount > 0) {
            return '+' + amount;
        }
        return String(amount);
    }

    function renderHistoryTable(transactions) {
        if (!transactions || transactions.length === 0) {
            return '<p>No points transactions found for this customer.</p>';
        }

        let html = '<table class="wp-list-table widefat fixed striped">';
        html += '<thead><tr>';
        html += '<th>Date</th><th>Type</th><th>Amount</th><th>Balance</th><th>Order</th><th>Description</th>';
        html += '</tr></thead><tbody>';

        transactions.forEach(function(row) {
            const amount = parseInt(row.points_amount, 10) || 0;
            const amountClass = amount >= 0 ? 'points-history-amount-positive' : 'points-history-amount-negative';
            let orderCell = '—';
            if (row.order_id) {
                if (row.order_url) {
                    orderCell = '<a href="' + escapeHtml(row.order_url) + '" target="_blank" rel="noopener noreferrer">#' + escapeHtml(row.order_id) + '</a>';
                } else {
                    orderCell = '#' + escapeHtml(row.order_id);
                }
            }

            html += '<tr>';
            html += '<td>' + escapeHtml(row.created_at || '') + '</td>';
            html += '<td>' + escapeHtml(row.transaction_type || '') + '</td>';
            html += '<td class="' + amountClass + '">' + escapeHtml(formatSignedPoints(amount)) + '</td>';
            html += '<td>' + escapeHtml(row.points_balance) + '</td>';
            html += '<td>' + orderCell + '</td>';
            html += '<td>' + escapeHtml(row.description || '') + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table>';
        return html;
    }

    $(document).on('click', '.view-history', function() {
        const userId = parseInt($(this).data('user-id'), 10);
        const userName = $(this).data('user-name') || '';

        $('#points-history-customer').html(
            '<p><strong>Customer:</strong> ' + escapeHtml(userName) + '</p>' +
            '<div class="points-history-summary">' +
            '<span><strong>Redeemable:</strong> <span id="points-history-redeemable">—</span></span>' +
            '<span><strong>Last ledger running:</strong> <span id="points-history-ledger">—</span></span>' +
            '</div>'
        );
        $('#points-history-banner').hide().empty();
        $('#points-history-table-wrap').html('<p class="points-history-loading">Loading history...</p>');
        $('#points-history-modal').show();

        if (!userId) {
            $('#points-history-table-wrap').html('<p>Unable to load history: missing customer ID.</p>');
            return;
        }

        $.ajax({
            url: intersoccer_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'get_points_history',
                customer_id: userId,
                limit: 100,
                nonce: intersoccer_admin.nonce
            },
            success: function(response) {
                if (!response.success) {
                    const message = (response.data && response.data.message) ? response.data.message : 'Unable to load history';
                    $('#points-history-table-wrap').html('<p>' + escapeHtml(message) + '</p>');
                    return;
                }

                const data = response.data || {};
                const redeemable = data.redeemable_balance;
                const ledger = data.ledger_running_balance;
                $('#points-history-redeemable').text(redeemable === undefined || redeemable === null ? '—' : redeemable);
                $('#points-history-ledger').text(ledger === undefined || ledger === null ? '—' : ledger);

                if (data.balance_mismatch) {
                    $('#points-history-banner')
                        .text('Redeemable balance does not match the last journal running total. Treat Current Points as source of truth; this table is the journal.')
                        .show();
                }

                $('#points-history-table-wrap').html(renderHistoryTable(data.transactions));
            },
            error: function() {
                $('#points-history-table-wrap').html('<p>Error loading points history.</p>');
            }
        });
    });
});

