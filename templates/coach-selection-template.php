<?php
/**
 * Coach Selection Template
 * Template for customers to select and switch coach partners
 */
?>

<div class="intersoccer-coach-selection">
    <div class="coach-selection-header">
        <h3><?php _e('Choose Your Coach Partner', 'intersoccer-referral'); ?></h3>
        <p><?php _e('Select a coach to partner with. You\'ll earn commissions on their referrals and they\'ll support your soccer journey.', 'intersoccer-referral'); ?></p>
    </div>

    <?php if ($cooldown_end && strtotime($cooldown_end) > time()): ?>
        <div class="cooldown-notice warning-notice">
            <p><?php
                $remaining_hours = ceil((strtotime($cooldown_end) - time()) / 3600);
                printf(__('You recently changed coaches. You can select a new coach in %d hours.', 'intersoccer-referral'), $remaining_hours);
            ?></p>
        </div>
    <?php endif; ?>

    <?php if ($current_coach_id): ?>
        <div class="current-coach-section">
            <h4><?php _e('Your Current Coach Partner', 'intersoccer-referral'); ?></h4>
            <?php
            $coach = get_user_by('ID', $current_coach_id);
            $tier = intersoccer_get_coach_tier($current_coach_id);
            $partnership_start = get_user_meta($customer_id, 'intersoccer_partnership_start_date', true);
            ?>
            <div class="current-coach-card">
                <div class="coach-avatar">
                    <?php echo get_avatar($coach->ID, 60); ?>
                    <span class="coach-tier-badge <?php echo strtolower($tier); ?>"><?php echo $tier; ?></span>
                </div>
                <div class="coach-info">
                    <h5><?php echo esc_html($coach->display_name); ?></h5>
                    <p class="partnership-duration">
                        <?php printf(__('Partner since %s', 'intersoccer-referral'), date_i18n(get_option('date_format'), strtotime($partnership_start))); ?>
                    </p>
                    <div class="coach-benefits">
                        <small><?php _e('Benefits: 5% commission on your purchases supports this coach', 'intersoccer-referral'); ?></small>
                    </div>
                </div>
                <?php if (!$cooldown_end || strtotime($cooldown_end) <= time()): ?>
                    <button type="button" class="switch-coach-btn" data-coach-id="<?php echo $current_coach_id; ?>">
                        <?php _e('Switch Coach', 'intersoccer-referral'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="coach-search-section">
        <div class="search-controls">
            <input type="text" id="coach-search" placeholder="<?php _e('Search coaches by name...', 'intersoccer-referral'); ?>" class="coach-search-input">
            <select id="coach-filter" class="coach-filter-select">
                <option value="all"><?php _e('All Coaches', 'intersoccer-referral'); ?></option>
                <option value="youth"><?php _e('Youth Specialists', 'intersoccer-referral'); ?></option>
                <option value="advanced"><?php _e('Advanced Training', 'intersoccer-referral'); ?></option>
                <option value="top"><?php _e('Top Performers', 'intersoccer-referral'); ?></option>
            </select>
        </div>

        <div id="coaches-list" class="coaches-grid">
            <!-- Coaches will be loaded here via AJAX -->
            <div class="loading-coaches">
                <p><?php _e('Loading available coaches...', 'intersoccer-referral'); ?></p>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var coachSearchTimeout;

    function loadCoaches(search = '', filter = 'all') {
        $('#coaches-list').html('<div class="loading-coaches"><p><?php _e('Loading coaches...', 'intersoccer-referral'); ?></p></div>');

        $.ajax({
            url: intersoccer_dashboard.ajax_url,
            type: 'POST',
            data: {
                action: 'get_available_coaches',
                nonce: intersoccer_dashboard.nonce,
                search: search,
                filter: filter
            },
            success: function(response) {
                if (response.success) {
                    displayCoaches(response.data.coaches);
                } else {
                    $('#coaches-list').html('<div class="no-coaches"><p><?php _e('No coaches found.', 'intersoccer-referral'); ?></p></div>');
                }
            },
            error: function() {
                $('#coaches-list').html('<div class="error-coaches"><p><?php _e('Error loading coaches. Please try again.', 'intersoccer-referral'); ?></p></div>');
            }
        });
    }

    function displayCoaches(coaches) {
        if (!coaches || coaches.length === 0) {
            $('#coaches-list').html('<div class="no-coaches"><p><?php _e('No coaches found matching your criteria.', 'intersoccer-referral'); ?></p></div>');
            return;
        }

        var html = '<div class="coaches-grid-inner">';
        coaches.forEach(function(coach) {
            var benefitsHtml = coach.benefits.map(function(benefit) {
                return '<li>' + benefit + '</li>';
            }).join('');

            html += `
                <div class="coach-card" data-coach-id="${coach.id}">
                    <div class="coach-header">
                        <img src="${coach.avatar_url}" alt="${coach.name}" class="coach-avatar">
                        <div class="coach-basic-info">
                            <h4>${coach.name}</h4>
                            <span class="coach-specialty">${coach.specialty}</span>
                            <div class="coach-rating">
                                ${'★'.repeat(Math.floor(coach.rating))}${'☆'.repeat(5-Math.floor(coach.rating))}
                                <span class="rating-number">(${coach.rating})</span>
                            </div>
                        </div>
                        <span class="coach-tier-badge ${coach.tier.toLowerCase()}">${coach.tier}</span>
                    </div>
                    <div class="coach-stats">
                        <div class="stat-item">
                            <span class="stat-number">${coach.total_athletes}</span>
                            <span class="stat-label"><?php _e('Athletes', 'intersoccer-referral'); ?></span>
                        </div>
                    </div>
                    <div class="coach-benefits">
                        <h5><?php _e('Benefits', 'intersoccer-referral'); ?>:</h5>
                        <ul>${benefitsHtml}</ul>
                    </div>
                    <button type="button" class="select-coach-btn" data-coach-id="${coach.id}">
                        <?php _e('Select This Coach', 'intersoccer-referral'); ?>
                    </button>
                </div>
            `;
        });
        html += '</div>';
        $('#coaches-list').html(html);
    }

    // Load coaches on page load
    loadCoaches();

    // Search functionality
    $('#coach-search').on('input', function() {
        clearTimeout(coachSearchTimeout);
        var searchTerm = $(this).val();
        coachSearchTimeout = setTimeout(function() {
            loadCoaches(searchTerm, $('#coach-filter').val());
        }, 300);
    });

    // Filter functionality
    $('#coach-filter').on('change', function() {
        loadCoaches($('#coach-search').val(), $(this).val());
    });

    // Coach selection
    $(document).on('click', '.select-coach-btn', function() {
        var coachId = $(this).data('coach-id');
        var coachCard = $(this).closest('.coach-card');
        var coachName = coachCard.find('h4').text();

        if (confirm('<?php _e('Are you sure you want to select', 'intersoccer-referral'); ?> ' + coachName + ' <?php _e('as your coach partner?', 'intersoccer-referral'); ?>')) {
            selectCoach(coachId);
        }
    });

    // Switch coach
    $(document).on('click', '.switch-coach-btn', function() {
        if (confirm('<?php _e('Are you sure you want to switch coaches? You won\'t be able to change again for 7 days.', 'intersoccer-referral'); ?>')) {
            $('#coach-search').val('');
            $('#coach-filter').val('all');
            loadCoaches();
            $('.coach-selection-header').after('<div class="switch-notice info-notice"><p><?php _e('Select a new coach from the list below.', 'intersoccer-referral'); ?></p></div>');
        }
    });

    function selectCoach(coachId) {
        $.ajax({
            url: intersoccer_dashboard.ajax_url,
            type: 'POST',
            data: {
                action: 'select_coach_partner',
                nonce: intersoccer_dashboard.nonce,
                coach_id: coachId
            },
            success: function(response) {
                if (response.success) {
                    location.reload(); // Reload to show new coach
                } else {
                    alert(response.data.message || '<?php _e('Error selecting coach. Please try again.', 'intersoccer-referral'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('Error selecting coach. Please try again.', 'intersoccer-referral'); ?>');
            }
        });
    }
});
</script>

<style>
.intersoccer-coach-selection {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.coach-selection-header {
    text-align: center;
    margin-bottom: 30px;
}

.coach-selection-header h3 {
    color: #2c3e50;
    margin-bottom: 10px;
}

.coach-selection-header p {
    color: #7f8c8d;
    font-size: 16px;
}

.cooldown-notice, .switch-notice {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 5px;
    padding: 15px;
    margin-bottom: 20px;
    text-align: center;
}

.warning-notice {
    background: #f8d7da;
    border-color: #f5c6cb;
}

.info-notice {
    background: #d1ecf1;
    border-color: #bee5eb;
}

.current-coach-section {
    margin-bottom: 40px;
}

.current-coach-section h4 {
    color: #2c3e50;
    margin-bottom: 15px;
}

.current-coach-card {
    display: flex;
    align-items: center;
    background: white;
    border: 1px solid #e1e8ed;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.coach-avatar {
    position: relative;
    margin-right: 20px;
}

.coach-avatar img {
    border-radius: 50%;
    border: 3px solid #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.coach-tier-badge {
    position: absolute;
    bottom: -5px;
    right: -5px;
    background: #28a745;
    color: white;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}

.coach-tier-badge.gold { background: #ffc107; color: #212529; }
.coach-tier-badge.platinum { background: #6f42c1; }
.coach-tier-badge.silver { background: #6c757d; }

.coach-info h5 {
    margin: 0 0 5px 0;
    color: #2c3e50;
    font-size: 18px;
}

.partnership-duration {
    color: #7f8c8d;
    font-size: 14px;
    margin-bottom: 10px;
}

.coach-benefits small {
    color: #27ae60;
    font-style: italic;
}

.switch-coach-btn {
    margin-left: auto;
    background: #dc3545;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
}

.switch-coach-btn:hover {
    background: #c82333;
}

.coach-search-section {
    background: white;
    border-radius: 10px;
    padding: 25px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.1);
}

.search-controls {
    display: flex;
    gap: 15px;
    margin-bottom: 25px;
}

.coach-search-input, .coach-filter-select {
    flex: 1;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 16px;
}

.coach-filter-select {
    flex: 0 0 200px;
}

.coaches-grid {
    min-height: 200px;
}

.coaches-grid-inner {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.coach-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 20px;
    transition: transform 0.2s, box-shadow 0.2s;
}

.coach-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
}

.coach-header {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
}

.coach-header .coach-avatar {
    width: 60px;
    height: 60px;
    margin-right: 15px;
}

.coach-basic-info {
    flex: 1;
}

.coach-basic-info h4 {
    margin: 0 0 5px 0;
    color: #2c3e50;
}

.coach-specialty {
    color: #7f8c8d;
    font-size: 14px;
    margin-bottom: 5px;
}

.coach-rating {
    color: #ffc107;
    font-size: 14px;
}

.rating-number {
    color: #6c757d;
    margin-left: 5px;
}

.coach-card .coach-tier-badge {
    align-self: flex-start;
}

.coach-stats {
    display: flex;
    justify-content: center;
    margin-bottom: 15px;
}

.stat-item {
    text-align: center;
}

.stat-number {
    display: block;
    font-size: 24px;
    font-weight: bold;
    color: #2c3e50;
}

.stat-label {
    font-size: 12px;
    color: #7f8c8d;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.coach-benefits {
    margin-bottom: 20px;
}

.coach-benefits h5 {
    margin: 0 0 10px 0;
    color: #2c3e50;
    font-size: 14px;
}

.coach-benefits ul {
    margin: 0;
    padding-left: 20px;
}

.coach-benefits li {
    font-size: 13px;
    color: #27ae60;
    margin-bottom: 3px;
}

.select-coach-btn {
    width: 100%;
    background: #28a745;
    color: white;
    border: none;
    padding: 12px;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
    font-size: 16px;
    transition: background 0.2s;
}

.select-coach-btn:hover {
    background: #218838;
}

.loading-coaches, .no-coaches, .error-coaches {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}

@media (max-width: 768px) {
    .search-controls {
        flex-direction: column;
    }

    .coaches-grid-inner {
        grid-template-columns: 1fr;
    }

    .current-coach-card {
        flex-direction: column;
        text-align: center;
    }

    .switch-coach-btn {
        margin-left: 0;
        margin-top: 15px;
    }
}
</style>