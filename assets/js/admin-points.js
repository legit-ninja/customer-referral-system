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

    $('.modal-close').on('click', function() {
        $('#points-adjustment-modal').hide();
    });

    $(document).on('click', '#points-adjustment-modal', function(e) {
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

    // View history (placeholder for now)
    $(document).on('click', '.view-history', function() {
        const userName = $(this).data('user-name');
        alert('Points history for ' + userName + ' - Feature coming soon!');
    });
});

