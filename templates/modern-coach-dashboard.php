<?php
/**
 * Modern Coach Dashboard Template
 * A comprehensive, aesthetic, and user-friendly dashboard for coaches
 */

// Check if we're in admin context (data passed from admin dashboard) or frontend context
if (isset($coach_data) && is_array($coach_data)) {
    // Admin dashboard context - data passed from InterSoccer_Coach_Admin_Dashboard
    $user_id = $coach_data['user_id'];
    $user = get_userdata($user_id);
    $credits = $coach_data['credits'];
    $points_balance = $coach_data['points_balance'];
    $tier = $coach_data['tier'];
    $referral_link = $coach_data['referral_link'];
    $referral_code = $coach_data['referral_code'] ?? InterSoccer_Referral_Handler::get_coach_referral_code($user_id);
    $referral_count = $coach_data['total_referrals'];
    $recent_referrals = $coach_data['recent_referrals'];
    $chart_labels = $coach_data['chart_labels'];
    $chart_referrals = $coach_data['chart_referrals'];
    $chart_credits = $coach_data['chart_credits'];
    $coach_events = $coach_data['coach_events'] ?? [];
    $coach_events_nonce = $coach_data['coach_events_nonce'] ?? wp_create_nonce('intersoccer_coach_events_nonce');
    $coach_events_ajax_url = $coach_data['ajax_url'] ?? admin_url('admin-ajax.php');
    $is_admin = $coach_data['is_admin_context'] ?? false;
    $monthly_stats = $coach_data['monthly_stats'] ?? [
        'new_referrals' => 0,
        'conversion_rate' => 0,
        'conversion_trend' => 0,
        'points_earned_this_month' => 0,
    ];
    $top_performers = $coach_data['top_performers'] ?? [];
    $coach_rank = $coach_data['coach_rank'] ?? 1;
    $achievements = $coach_data['coach_achievements'] ?? [];
    $tier_progress = $coach_data['tier_progress'] ?? 0;
    $next_tier_requirements = $coach_data['next_tier_requirements'] ?? '';
    $linked_customers_count = $coach_data['linked_customers_count'] ?? 0;
} else {
    // Frontend dashboard context - get data from dashboard class
    $user_id = get_current_user_id();
    $user = get_userdata($user_id);
    $credits = (float) get_user_meta($user_id, 'intersoccer_credits', true);
    $points_balance = (float) get_user_meta($user_id, 'intersoccer_points_balance', true);
    $tier = intersoccer_get_coach_tier($user_id);
    $referral_link = InterSoccer_Referral_Handler::generate_coach_referral_link($user_id);
    $referral_code = InterSoccer_Referral_Handler::get_coach_referral_code($user_id);
    $referral_count = $this->get_coach_referral_count($user_id);
    $recent_referrals = $this->get_recent_referrals($user_id, 5);
    $monthly_stats = $this->get_monthly_stats($user_id);
    $top_performers = $this->get_top_performers();
    $coach_rank = $this->get_coach_rank($user_id);
    $achievements = $this->get_coach_achievements($user_id);
    $tier_progress = $this->get_tier_progress($tier, $referral_count);
    $next_tier_requirements = $this->get_next_tier_requirements($tier, $referral_count);
    $linked_customers_count = $this->get_linked_customers_count($user_id);
    $chart_labels = $this->get_chart_labels(30);
    $chart_referrals = $this->get_chart_data($user_id, 30, 'referrals');
    $chart_credits = $this->get_chart_data($user_id, 30, 'credits');
    $coach_events = class_exists('InterSoccer_Coach_Events_Manager') ? InterSoccer_Coach_Events_Manager::get_coach_events($user_id) : [];
    $coach_events_nonce = wp_create_nonce('intersoccer_coach_events_nonce');
    $coach_events_ajax_url = admin_url('admin-ajax.php');
    $is_admin = false;
}

// Generate QR code URL
$qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($referral_link);

// Theme preference
$theme = get_user_meta($user_id, 'intersoccer_dashboard_theme', true) ?: 'light';
?>

<?php if ($is_admin): ?>
<div class="wrap">
<?php endif; ?>
<div class="modern-coach-dashboard <?php echo $is_admin ? 'admin-context' : ''; ?>" data-theme="<?php echo esc_attr($theme); ?>">
    <!-- Header Section -->
    <div class="dashboard-header">
        <div class="header-content">
            <div class="welcome-section">
                <div class="welcome-avatar">
                    <?php echo get_avatar($user_id, 64); ?>
                    <div class="online-status online"></div>
                </div>
                <div class="welcome-text">
                    <h1>
                        <?php
                        printf(
                            esc_html__('Welcome back, %s! 👋', 'intersoccer-referral'),
                            esc_html($user->first_name ?: $user->display_name)
                        );
                        ?>
                    </h1>
                    <p class="welcome-subtitle">
                        <?php esc_html_e("Here's what's happening with your referral program today", 'intersoccer-referral'); ?>
                    </p>
                </div>
            </div>

            <div class="header-actions" id="tour-actions">
                <button class="action-btn primary" id="share-link-btn" data-tooltip="<?php echo esc_attr__('Share your referral link', 'intersoccer-referral'); ?>">
                    <i class="icon-share"></i>
                    <span><?php esc_html_e('Share Link', 'intersoccer-referral'); ?></span>
                </button>
                <button class="action-btn secondary" id="view-analytics-btn" data-tooltip="<?php echo esc_attr__('View detailed analytics', 'intersoccer-referral'); ?>">
                    <i class="icon-analytics"></i>
                    <span><?php esc_html_e('Analytics', 'intersoccer-referral'); ?></span>
                </button>
                <button class="action-btn secondary" id="theme-toggle" data-tooltip="<?php echo esc_attr__('Toggle theme', 'intersoccer-referral'); ?>">
                    <i class="icon-theme"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Stats Overview Cards -->
    <div class="stats-grid">
        <div class="stat-card credits-card" id="tour-credits" data-aos="fade-up" data-aos-delay="0">
            <div class="stat-icon">
                <i class="icon-credits"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" data-counter="<?php echo number_format($points_balance, 0); ?>">
                    <?php echo number_format($points_balance, 0); ?>
                </div>
                <div class="stat-label"><?php esc_html_e('Points Balance', 'intersoccer-referral'); ?></div>
                <div class="stat-change <?php echo $monthly_stats['points_earned_this_month'] > 0 ? 'positive' : ($monthly_stats['points_earned_this_month'] < 0 ? 'negative' : 'neutral'); ?>">
                    <?php if ($monthly_stats['points_earned_this_month'] > 0): ?>
                        <i class="icon-trend-up"></i>
                        <?php
                        printf(
                            esc_html__('+%s this month', 'intersoccer-referral'),
                            esc_html(number_format($monthly_stats['points_earned_this_month'], 0))
                        );
                        ?>
                    <?php elseif (isset($monthly_stats['points_earned_this_month']) && $monthly_stats['points_earned_this_month'] < 0): ?>
                        <i class="icon-trend-down"></i>
                        <?php
                        printf(
                            esc_html__('%s this month', 'intersoccer-referral'),
                            esc_html(number_format($monthly_stats['points_earned_this_month'], 0))
                        );
                        ?>
                    <?php else: ?>
                        <i class="icon-rank"></i>
                        <?php esc_html_e('No change this month', 'intersoccer-referral'); ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="stat-sparkline">
                <canvas id="credits-sparkline" width="60" height="30"></canvas>
            </div>
        </div>

        <div class="stat-card referrals-card" data-aos="fade-up" data-aos-delay="100">
            <div class="stat-icon">
                <i class="icon-referrals"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" data-counter="<?php echo $referral_count; ?>">
                    <?php echo $referral_count; ?>
                </div>
                <div class="stat-label"><?php esc_html_e('Total Referrals', 'intersoccer-referral'); ?></div>
                <div class="stat-change positive">
                    <i class="icon-trend-up"></i>
                    <?php
                    printf(
                        esc_html__('+%s this month', 'intersoccer-referral'),
                        esc_html($monthly_stats['new_referrals'])
                    );
                    ?>
                </div>
            </div>
            <div class="stat-sparkline">
                <canvas id="referrals-sparkline" width="60" height="30"></canvas>
            </div>
        </div>

        <div class="stat-card tier-card" id="tour-tier" data-aos="fade-up" data-aos-delay="200">
            <div class="stat-icon">
                <i class="icon-tier"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value tier-badge <?php echo strtolower($tier); ?>">
                    <?php echo esc_html($tier); ?>
                </div>
                <div class="stat-label"><?php esc_html_e('Current Tier', 'intersoccer-referral'); ?></div>
                <div class="stat-change neutral">
                    <i class="icon-rank"></i>
                    <?php
                    printf(
                        esc_html__('Rank #%s', 'intersoccer-referral'),
                        esc_html($coach_rank)
                    );
                    ?>
                </div>
            </div>
            <div class="tier-progress">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo esc_attr($tier_progress); ?>%"></div>
                </div>
                <div class="progress-text">
                    <?php echo esc_html($next_tier_requirements); ?>
                </div>
            </div>
        </div>

        <div class="stat-card conversion-card" data-aos="fade-up" data-aos-delay="300">
            <div class="stat-icon">
                <i class="icon-conversion"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value">
                    <?php echo esc_html($monthly_stats['conversion_rate']); ?>%
                </div>
                <div class="stat-label"><?php esc_html_e('Conversion Rate', 'intersoccer-referral'); ?></div>
                <div class="stat-change <?php echo $monthly_stats['conversion_trend'] > 0 ? 'positive' : 'negative'; ?>">
                    <i class="icon-trend-<?php echo $monthly_stats['conversion_trend'] > 0 ? 'up' : 'down'; ?>"></i>
                    <?php
                    printf(
                        esc_html__('%s%% vs last month', 'intersoccer-referral'),
                        esc_html(abs($monthly_stats['conversion_trend']))
                    );
                    ?>
                </div>
            </div>
            <div class="conversion-chart">
                <canvas id="conversion-chart" width="60" height="30"></canvas>
            </div>
        </div>

        <div class="stat-card customers-card" data-aos="fade-up" data-aos-delay="400">
            <div class="stat-icon">
                <i class="icon-customers"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" data-counter="<?php echo esc_attr($linked_customers_count); ?>">
                    <?php echo esc_html($linked_customers_count); ?>
                </div>
                <div class="stat-label"><?php esc_html_e('Linked Customers', 'intersoccer-referral'); ?></div>
                <div class="stat-change positive">
                    <i class="icon-trend-up"></i>
                    <?php esc_html_e('Ongoing earnings', 'intersoccer-referral'); ?>
                </div>
            </div>
            <div class="customer-chart">
                <canvas id="customers-chart" width="60" height="30"></canvas>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="dashboard-grid">
        <!-- Referral Link Section -->
        <div class="dashboard-card referral-link-card" data-aos="fade-right">
            <div class="card-header">
                <h3><i class="icon-link"></i> <?php esc_html_e('Your Referral Link', 'intersoccer-referral'); ?></h3>
                <div class="card-actions">
                    <button class="btn-icon" id="copy-link" data-tooltip="<?php echo esc_attr__('Copy to clipboard', 'intersoccer-referral'); ?>">
                        <i class="icon-copy"></i>
                    </button>
                    <button class="btn-icon" id="show-qr" data-tooltip="<?php echo esc_attr__('Show QR code', 'intersoccer-referral'); ?>">
                        <i class="icon-qr"></i>
                    </button>
                </div>
            </div>

            <div class="referral-link-container">
                <input type="text" id="referral-link-input" value="<?php echo esc_attr($referral_link); ?>" readonly>
                <div class="link-actions">
                    <button class="btn-primary" id="copy-link-text"><?php esc_html_e('Copy Link', 'intersoccer-referral'); ?></button>
                    <button class="btn-secondary" id="customize-link"><?php esc_html_e('Customize', 'intersoccer-referral'); ?></button>
                </div>
            </div>

            <div class="referral-code-container" data-aos="fade-up">
                <div class="referral-code-header">
                    <span class="code-icon" aria-hidden="true">🏷️</span>
                    <div>
                        <h4><?php esc_html_e('Share Your Referral Code', 'intersoccer-referral'); ?></h4>
                        <p class="code-subtitle"><?php esc_html_e('Customers can enter this code directly at checkout.', 'intersoccer-referral'); ?></p>
                    </div>
                </div>
                <div class="referral-code-body">
                    <span class="code-value" id="referral-code-value"><?php echo esc_html($referral_code); ?></span>
                    <button class="btn-tertiary" id="copy-code"><?php esc_html_e('Copy Code', 'intersoccer-referral'); ?></button>
                </div>
            </div>

            <!-- QR Code Modal -->
            <div id="qr-modal" class="modal">
                <div class="modal-content qr-modal-content">
                    <div class="modal-header">
                        <h3><?php esc_html_e('QR Code for Your Referral Link', 'intersoccer-referral'); ?></h3>
                        <button class="modal-close" type="button" aria-label="<?php echo esc_attr__('Close modal', 'intersoccer-referral'); ?>">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="qr-code-container">
                            <img src="<?php echo esc_attr($qr_code_url); ?>" alt="<?php echo esc_attr__('Referral Link QR Code', 'intersoccer-referral'); ?>">
                            <p><?php esc_html_e('Scan this QR code to access your referral link.', 'intersoccer-referral'); ?></p>
                        </div>
                        <div class="qr-actions">
                            <button class="btn-primary" id="download-qr" type="button"><?php esc_html_e('Download QR Code', 'intersoccer-referral'); ?></button>
                            <button class="btn-secondary" id="share-qr" type="button"><?php esc_html_e('Share QR Code', 'intersoccer-referral'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Coach Event Participation -->
        <div class="dashboard-card coach-events-card" data-aos="fade-left">
            <div class="card-header">
                <h3><i class="icon-calendar"></i> <?php esc_html_e('Event Participation', 'intersoccer-referral'); ?></h3>
                <?php if (!$is_admin): ?>
                <div class="card-actions">
                    <button class="btn-icon" id="coach-events-refresh" data-tooltip="<?php esc_attr_e('Refresh events', 'intersoccer-referral'); ?>">
                        <i class="icon-refresh"></i>
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <div class="coach-events-body" id="coach-events-body">
                <?php if (!empty($coach_events)): ?>
                    <ul class="coach-events-list">
                        <?php foreach ($coach_events as $event): ?>
                            <li class="coach-event-item" data-assignment-id="<?php echo esc_attr($event->id); ?>">
                                <div class="event-title">
                                    <?php if (!empty($event->event_permalink)): ?>
                                        <a href="<?php echo esc_url($event->event_permalink); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo esc_html($event->event_title); ?>
                                        </a>
                                    <?php else: ?>
                                        <?php echo esc_html($event->event_title); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="event-meta">
                                    <span class="event-status status-<?php echo esc_attr($event->status); ?>"><?php echo esc_html(ucfirst($event->status)); ?></span>
                                    <span class="event-source">• <?php echo esc_html(ucfirst($event->source)); ?></span>
                                    <?php if (!empty($event->assigned_at)): ?>
                                        <span class="event-date">• <?php echo esc_html(mysql2date(get_option('date_format'), $event->assigned_at)); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php if (!empty($event->event_share_link)): ?>
                                <div class="event-share">
                                    <input type="text" class="coach-event-share-input" value="<?php echo esc_attr($event->event_share_link); ?>" readonly>
                                    <button class="btn-tertiary coach-event-copy" data-link="<?php echo esc_attr($event->event_share_link); ?>"><?php esc_html_e('Copy', 'intersoccer-referral'); ?></button>
                                    <a class="btn-secondary" href="<?php echo esc_url($event->event_share_link); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open', 'intersoccer-referral'); ?></a>
                                </div>
                            <?php endif; ?>
                                <?php if (!$is_admin): ?>
                                <div class="event-actions">
                                    <button class="btn-tertiary coach-event-remove" data-assignment-id="<?php echo esc_attr($event->id); ?>"><?php esc_html_e('Remove', 'intersoccer-referral'); ?></button>
                                </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="icon-calendar"></i>
                        <h4><?php esc_html_e('No events added yet', 'intersoccer-referral'); ?></h4>
                        <p><?php esc_html_e('Add the events you coach so we can generate direct referral links for customers.', 'intersoccer-referral'); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!$is_admin): ?>
            <div class="coach-events-form" data-nonce="<?php echo esc_attr($coach_events_nonce); ?>">
                <h4><?php esc_html_e('Add Event Participation', 'intersoccer-referral'); ?></h4>
                <p class="description"><?php esc_html_e('Search for the event or product you will coach. We\'ll notify admins so they can approve it.', 'intersoccer-referral'); ?></p>
                <div class="coach-event-search">
                    <div class="label-block">
                        <label for="coach-event-search-input"><?php esc_html_e('Search for an event or product', 'intersoccer-referral'); ?></label>
                        <input type="text" id="coach-event-search-input" class="wide-field" placeholder="<?php esc_attr_e('Start typing an event or product name…', 'intersoccer-referral'); ?>">
                    </div>
                    <button class="btn-secondary" id="coach-event-search-btn" type="button"><?php esc_html_e('Search', 'intersoccer-referral'); ?></button>
                </div>
                <input type="hidden" id="coach-event-selected-id" value="">
                <input type="hidden" id="coach-event-selected-type" value="">
                <div id="coach-event-search-results" class="coach-event-search-results" aria-live="polite"></div>
                <div class="coach-event-actions">
                    <button class="btn-primary" id="coach-event-add-btn" type="button"><?php esc_html_e('Request Event', 'intersoccer-referral'); ?></button>
                    <span class="spinner" id="coach-event-spinner"></span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Recent Activity -->
        <div class="dashboard-card activity-card" data-aos="fade-left">
            <div class="card-header">
                <h3><i class="icon-activity"></i> <?php esc_html_e('Recent Activity', 'intersoccer-referral'); ?></h3>
                <div class="card-actions">
                    <button class="btn-link" id="view-all-activity" type="button"><?php esc_html_e('View All', 'intersoccer-referral'); ?></button>
                </div>
            </div>

            <div class="activity-feed">
                <?php if (!empty($recent_referrals)): ?>
                    <?php foreach ($recent_referrals as $referral): ?>
                        <?php
                        $customer_name = $this->get_customer_name($referral->customer_id);
                        $time_diff = human_time_diff(strtotime($referral->created_at), current_time('timestamp'));
                        ?>
                        <div class="activity-item">
                            <div class="activity-icon success">
                                <i class="icon-check"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    <?php
                                    printf(
                                        esc_html__('New referral from %s', 'intersoccer-referral'),
                                        esc_html($customer_name)
                                    );
                                    ?>
                                </div>
                                <div class="activity-meta">
                                    <?php
                                    printf(
                                        esc_html__('Order #%1$s • %2$s ago', 'intersoccer-referral'),
                                        esc_html($referral->order_id),
                                        esc_html($time_diff)
                                    );
                                    ?>
                                </div>
                            </div>
                            <div class="activity-amount">
                                <?php
                                printf(
                                    esc_html__('+%s CHF', 'intersoccer-referral'),
                                    esc_html(number_format($referral->commission_amount, 2))
                                );
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="icon-activity-empty"></i>
                        <h4><?php esc_html_e('No recent activity', 'intersoccer-referral'); ?></h4>
                        <p><?php esc_html_e('Your referral activity will appear here once people start using your link.', 'intersoccer-referral'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="dashboard-card actions-card" data-aos="fade-up">
            <div class="card-header">
                <h3><i class="icon-actions"></i> <?php esc_html_e('Quick Actions', 'intersoccer-referral'); ?></h3>
            </div>

            <div class="quick-actions-grid">
                <button class="action-tile" id="create-post" type="button">
                    <div class="action-icon">
                        <i class="icon-social"></i>
                    </div>
                    <div class="action-content">
                        <h4><?php esc_html_e('Social Media Post', 'intersoccer-referral'); ?></h4>
                        <p><?php esc_html_e('Create engaging posts for your referral link.', 'intersoccer-referral'); ?></p>
                    </div>
                </button>

                <button class="action-tile" id="send-email" type="button">
                    <div class="action-icon">
                        <i class="icon-email"></i>
                    </div>
                    <div class="action-content">
                        <h4><?php esc_html_e('Email Campaign', 'intersoccer-referral'); ?></h4>
                        <p><?php esc_html_e('Send personalized emails to potential customers.', 'intersoccer-referral'); ?></p>
                    </div>
                </button>

                <button class="action-tile" id="view-resources" data-tour="resources" type="button">
                    <div class="action-icon">
                        <i class="icon-resources"></i>
                    </div>
                    <div class="action-content">
                        <h4><?php esc_html_e('Marketing Resources', 'intersoccer-referral'); ?></h4>
                        <p><?php esc_html_e('Access templates, guides, and promotional materials.', 'intersoccer-referral'); ?></p>
                    </div>
                </button>

                <button class="action-tile" id="contact-support" type="button">
                    <div class="action-icon">
                        <i class="icon-support"></i>
                    </div>
                    <div class="action-content">
                        <h4><?php esc_html_e('Get Support', 'intersoccer-referral'); ?></h4>
                        <p><?php esc_html_e('Need help? Contact our support team.', 'intersoccer-referral'); ?></p>
                    </div>
                </button>
            </div>
        </div>

        <!-- Performance Chart -->
        <div class="dashboard-card chart-card" data-aos="fade-up">
            <div class="card-header">
                <h3><i class="icon-chart"></i> <?php esc_html_e('Performance Overview', 'intersoccer-referral'); ?></h3>
                <div class="card-actions">
                    <select id="chart-period">
                        <option value="7"><?php esc_html_e('Last 7 days', 'intersoccer-referral'); ?></option>
                        <option value="30" selected><?php esc_html_e('Last 30 days', 'intersoccer-referral'); ?></option>
                        <option value="90"><?php esc_html_e('Last 3 months', 'intersoccer-referral'); ?></option>
                    </select>
                </div>
            </div>

            <div class="chart-container">
                <canvas id="performance-chart" height="200"></canvas>
            </div>
        </div>

        <!-- Leaderboard -->
        <div class="dashboard-card leaderboard-card" data-aos="fade-up">
            <div class="card-header">
                <h3><i class="icon-leaderboard"></i> <?php esc_html_e('Top Performers', 'intersoccer-referral'); ?></h3>
                <div class="card-actions">
                    <button class="btn-link" id="view-full-leaderboard" type="button"><?php esc_html_e('View Full List', 'intersoccer-referral'); ?></button>
                </div>
            </div>

            <div class="leaderboard-list">
                <?php $rank = 1; ?>
                <?php foreach ($top_performers as $performer): ?>
                    <div class="leaderboard-item <?php echo $performer->ID == $user_id ? 'current-user' : ''; ?>">
                        <div class="rank-badge <?php echo $rank <= 3 ? 'top-' . $rank : ''; ?>">
                            <?php echo $rank; ?>
                        </div>
                        <div class="performer-info">
                            <div class="performer-name">
                                <?php echo esc_html($performer->display_name); ?>
                                <?php if ($performer->ID == $user_id): ?>
                                    <span class="you-badge"><?php esc_html_e('You', 'intersoccer-referral'); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="performer-stats">
                                <?php
                                printf(
                                    esc_html__('%1$s referrals • %2$s CHF', 'intersoccer-referral'),
                                    esc_html($performer->referral_count),
                                    esc_html(number_format($performer->total_credits, 0))
                                );
                                ?>
                            </div>
                        </div>
                        <div class="performer-tier">
                            <span class="tier-badge <?php echo strtolower($performer->tier); ?>">
                                <?php echo esc_html($performer->tier); ?>
                            </span>
                        </div>
                    </div>
                    <?php $rank++; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Achievement Badges Section -->
    <div class="achievements-section" data-aos="fade-up">
        <h3><i class="icon-achievements"></i> <?php esc_html_e('Your Achievements', 'intersoccer-referral'); ?></h3>
        <div class="achievements-grid">
            <?php foreach ($achievements as $achievement): ?>
                <div class="achievement-badge <?php echo $achievement['unlocked'] ? 'unlocked' : 'locked'; ?>">
                    <div class="badge-icon">
                        <i class="icon-<?php echo esc_attr($achievement['icon']); ?>"></i>
                    </div>
                    <div class="badge-content">
                        <h4><?php echo esc_html($achievement['title']); ?></h4>
                        <p><?php echo esc_html($achievement['description']); ?></p>
                        <?php if (!$achievement['unlocked']): ?>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo esc_attr($achievement['progress']); ?>%"></div>
                            </div>
                            <div class="progress-text"><?php echo esc_html($achievement['progress_text']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php if ($is_admin): ?>
</div>
<?php endif; ?>

<!-- Dashboard Scripts -->


