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


