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
                    <h1>Welcome back, <?php echo esc_html($user->first_name ?: $user->display_name); ?>! 👋</h1>
                    <p class="welcome-subtitle">Here's what's happening with your referral program today</p>
                </div>
            </div>

            <div class="header-actions">
                <button class="action-btn primary" id="share-link-btn" data-tooltip="Share your referral link">
                    <i class="icon-share"></i>
                    <span>Share Link</span>
                </button>
                <button class="action-btn secondary" id="view-analytics-btn" data-tooltip="View detailed analytics">
                    <i class="icon-analytics"></i>
                    <span>Analytics</span>
                </button>
                <button class="action-btn secondary" id="theme-toggle" data-tooltip="Toggle theme">
                    <i class="icon-theme"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Stats Overview Cards -->
    <div class="stats-grid">
        <div class="stat-card credits-card" data-aos="fade-up" data-aos-delay="0">
            <div class="stat-icon">
                <i class="icon-credits"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value" data-counter="<?php echo number_format($points_balance, 0); ?>">
                    <?php echo number_format($points_balance, 0); ?>
                </div>
                <div class="stat-label">Points Balance</div>
                <div class="stat-change positive">
                    <i class="icon-trend-up"></i>
                    +12% this month
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
                <div class="stat-label">Total Referrals</div>
                <div class="stat-change positive">
                    <i class="icon-trend-up"></i>
                    +<?php echo $monthly_stats['new_referrals']; ?> this month
                </div>
            </div>
            <div class="stat-sparkline">
                <canvas id="referrals-sparkline" width="60" height="30"></canvas>
            </div>
        </div>

        <div class="stat-card tier-card" data-aos="fade-up" data-aos-delay="200">
            <div class="stat-icon">
                <i class="icon-tier"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value tier-badge <?php echo strtolower($tier); ?>">
                    <?php echo $tier; ?>
                </div>
                <div class="stat-label">Current Tier</div>
                <div class="stat-change neutral">
                    <i class="icon-rank"></i>
                    Rank #<?php echo $coach_rank; ?>
                </div>
            </div>
            <div class="tier-progress">
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $this->get_tier_progress($tier, $referral_count); ?>%"></div>
                </div>
                <div class="progress-text">
                    <?php echo $this->get_next_tier_requirements($tier, $referral_count); ?>
                </div>
            </div>
        </div>

        <div class="stat-card conversion-card" data-aos="fade-up" data-aos-delay="300">
            <div class="stat-icon">
                <i class="icon-conversion"></i>
            </div>
            <div class="stat-content">
                <div class="stat-value">
                    <?php echo $monthly_stats['conversion_rate']; ?>%
                </div>
                <div class="stat-label">Conversion Rate</div>
                <div class="stat-change <?php echo $monthly_stats['conversion_trend'] > 0 ? 'positive' : 'negative'; ?>">
                    <i class="icon-trend-<?php echo $monthly_stats['conversion_trend'] > 0 ? 'up' : 'down'; ?>"></i>
                    <?php echo abs($monthly_stats['conversion_trend']); ?>% vs last month
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
                <div class="stat-value" data-counter="<?php echo $this->get_linked_customers_count($user_id); ?>">
                    <?php echo $this->get_linked_customers_count($user_id); ?>
                </div>
                <div class="stat-label">Linked Customers</div>
                <div class="stat-change positive">
                    <i class="icon-trend-up"></i>
                    Ongoing earnings
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
                <h3><i class="icon-link"></i> Your Referral Link</h3>
                <div class="card-actions">
                    <button class="btn-icon" id="copy-link" data-tooltip="Copy to clipboard">
                        <i class="icon-copy"></i>
                    </button>
                    <button class="btn-icon" id="show-qr" data-tooltip="Show QR code">
                        <i class="icon-qr"></i>
                    </button>
                </div>
            </div>

            <div class="referral-link-container">
                <input type="text" id="referral-link-input" value="<?php echo esc_attr($referral_link); ?>" readonly>
                <div class="link-actions">
                    <button class="btn-primary" id="copy-link-text">Copy Link</button>
                    <button class="btn-secondary" id="customize-link">Customize</button>
                </div>
            </div>

            <div class="referral-code-container" data-aos="fade-up">
                <div class="referral-code-header">
                    <span class="code-icon" aria-hidden="true">🏷️</span>
                    <div>
                        <h4>Share Your Referral Code</h4>
                        <p class="code-subtitle">Customers can enter this code directly at checkout.</p>
                    </div>
                </div>
                <div class="referral-code-body">
                    <span class="code-value" id="referral-code-value"><?php echo esc_html($referral_code); ?></span>
                    <button class="btn-tertiary" id="copy-code">Copy Code</button>
                </div>
            </div>

            <!-- QR Code Modal -->
            <div id="qr-modal" class="modal">
                <div class="modal-content qr-modal-content">
                    <div class="modal-header">
                        <h3>QR Code for Your Referral Link</h3>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="qr-code-container">
                            <img src="<?php echo esc_attr($qr_code_url); ?>" alt="Referral Link QR Code">
                            <p>Scan this QR code to access your referral link</p>
                        </div>
                        <div class="qr-actions">
                            <button class="btn-primary" id="download-qr">Download QR Code</button>
                            <button class="btn-secondary" id="share-qr">Share QR Code</button>
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
                <h3><i class="icon-activity"></i> Recent Activity</h3>
                <div class="card-actions">
                    <button class="btn-link" id="view-all-activity">View All</button>
                </div>
            </div>

            <div class="activity-feed">
                <?php if (!empty($recent_referrals)): ?>
                    <?php foreach ($recent_referrals as $referral): ?>
                        <div class="activity-item">
                            <div class="activity-icon success">
                                <i class="icon-check"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    New referral from <?php echo esc_html($this->get_customer_name($referral->customer_id)); ?>
                                </div>
                                <div class="activity-meta">
                                    Order #<?php echo esc_html($referral->order_id); ?> •
                                    <?php echo human_time_diff(strtotime($referral->created_at), current_time('timestamp')); ?> ago
                                </div>
                            </div>
                            <div class="activity-amount">
                                +<?php echo number_format($referral->commission_amount, 2); ?> CHF
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="icon-activity-empty"></i>
                        <h4>No recent activity</h4>
                        <p>Your referral activity will appear here once people start using your link.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="dashboard-card actions-card" data-aos="fade-up">
            <div class="card-header">
                <h3><i class="icon-actions"></i> Quick Actions</h3>
            </div>

            <div class="quick-actions-grid">
                <button class="action-tile" id="create-post">
                    <div class="action-icon">
                        <i class="icon-social"></i>
                    </div>
                    <div class="action-content">
                        <h4>Social Media Post</h4>
                        <p>Create engaging posts for your referral link</p>
                    </div>
                </button>

                <button class="action-tile" id="send-email">
                    <div class="action-icon">
                        <i class="icon-email"></i>
                    </div>
                    <div class="action-content">
                        <h4>Email Campaign</h4>
                        <p>Send personalized emails to potential customers</p>
                    </div>
                </button>

                <button class="action-tile" id="view-resources">
                    <div class="action-icon">
                        <i class="icon-resources"></i>
                    </div>
                    <div class="action-content">
                        <h4>Marketing Resources</h4>
                        <p>Access templates, guides, and promotional materials</p>
                    </div>
                </button>

                <button class="action-tile" id="contact-support">
                    <div class="action-icon">
                        <i class="icon-support"></i>
                    </div>
                    <div class="action-content">
                        <h4>Get Support</h4>
                        <p>Need help? Contact our support team</p>
                    </div>
                </button>
            </div>
        </div>

        <!-- Performance Chart -->
        <div class="dashboard-card chart-card" data-aos="fade-up">
            <div class="card-header">
                <h3><i class="icon-chart"></i> Performance Overview</h3>
                <div class="card-actions">
                    <select id="chart-period">
                        <option value="7">Last 7 days</option>
                        <option value="30" selected>Last 30 days</option>
                        <option value="90">Last 3 months</option>
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
                <h3><i class="icon-leaderboard"></i> Top Performers</h3>
                <div class="card-actions">
                    <button class="btn-link" id="view-full-leaderboard">View Full List</button>
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
                                    <span class="you-badge">You</span>
                                <?php endif; ?>
                            </div>
                            <div class="performer-stats">
                                <?php echo $performer->referral_count; ?> referrals •
                                <?php echo number_format($performer->total_credits, 0); ?> CHF
                            </div>
                        </div>
                        <div class="performer-tier">
                            <span class="tier-badge <?php echo strtolower($performer->tier); ?>">
                                <?php echo $performer->tier; ?>
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
        <h3><i class="icon-achievements"></i> Your Achievements</h3>
        <div class="achievements-grid">
            <?php
            $achievements = $this->get_coach_achievements($user_id);
            foreach ($achievements as $achievement):
            ?>
                <div class="achievement-badge <?php echo $achievement['unlocked'] ? 'unlocked' : 'locked'; ?>">
                    <div class="badge-icon">
                        <i class="icon-<?php echo $achievement['icon']; ?>"></i>
                    </div>
                    <div class="badge-content">
                        <h4><?php echo esc_html($achievement['title']); ?></h4>
                        <p><?php echo esc_html($achievement['description']); ?></p>
                        <?php if (!$achievement['unlocked']): ?>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $achievement['progress']; ?>%"></div>
                            </div>
                            <div class="progress-text"><?php echo $achievement['progress_text']; ?></div>
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


<style>
/* Modern Coach Dashboard Styles */
.modern-coach-dashboard {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    background: #f8fafc;
    min-height: 100vh;
    color: #1e293b;
    transition: background-color 0.3s ease, color 0.3s ease;
}

.modern-coach-dashboard[data-theme="dark"] {
    background: #0f172a;
    color: #f1f5f9;
}

/* Header Section */
.dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 2rem;
    border-radius: 0 0 24px 24px;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
}

.header-content {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.welcome-section {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.welcome-avatar {
    position: relative;
}

.welcome-avatar img {
    border-radius: 50%;
    border: 4px solid rgba(255, 255, 255, 0.9);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.online-status {
    position: absolute;
    bottom: 4px;
    right: 4px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 2px solid white;
}

.online-status.online {
    background: #10b981;
}

.welcome-text h1 {
    margin: 0 0 0.5rem 0;
    font-size: 2rem;
    font-weight: 700;
    color: white;
}

.welcome-subtitle {
    margin: 0;
    opacity: 0.9;
    font-size: 1.1rem;
}

.header-actions {
    display: flex;
    gap: 1rem;
}

.action-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.action-btn.primary {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    backdrop-filter: blur(10px);
}

.action-btn.secondary {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    max-width: 1200px;
    margin: -4rem auto 2rem;
    padding: 0 2rem;
    position: relative;
    z-index: 10;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.credits-card .stat-icon {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.referrals-card .stat-icon {
    background: linear-gradient(135deg, #f093fb, #f5576c);
    color: white;
}

.tier-card .stat-icon {
    background: linear-gradient(135deg, #4facfe, #00f2fe);
    color: white;
}

.conversion-card .stat-icon {
    background: linear-gradient(135deg, #43e97b, #38f9d7);
    color: white;
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
    color: #1e293b;
}

.tier-badge {
    font-size: 1.25rem;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.tier-badge.bronze { background: #cd7f32; color: white; }
.tier-badge.silver { background: #c0c0c0; color: #333; }
.tier-badge.gold { background: #ffd700; color: #333; }
.tier-badge.platinum { background: #e5e4e2; color: #333; }

.stat-label {
    color: #64748b;
    font-size: 0.875rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-change {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.75rem;
    font-weight: 600;
    margin-top: 0.5rem;
}

.stat-change.positive { color: #10b981; }
.stat-change.negative { color: #ef4444; }
.stat-change.neutral { color: #64748b; }

/* Dashboard Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 1.5rem;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem 2rem;
}

.dashboard-card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    transition: all 0.3s ease;
}

.dashboard-card:hover {
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.card-header {
    padding: 1.5rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-actions {
    display: flex;
    gap: 0.5rem;
}

/* Referral Link Card */
.referral-link-container {
    padding: 1.5rem;
}

.referral-link-container input {
    width: 100%;
    padding: 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 0.875rem;
    margin-bottom: 1rem;
    background: #f8fafc;
}

.link-actions {
    display: flex;
    gap: 1rem;
}

.btn-primary, .btn-secondary {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
}

.btn-secondary:hover {
    background: #e2e8f0;
}

.btn-tertiary {
    padding: 0.65rem 1.25rem;
    border-radius: 999px;
    background: #fff7ed;
    color: #c2410c;
    border: 1px solid #fed7aa;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-tertiary:hover {
    background: #fed7aa;
    color: #9a3412;
    transform: translateY(-1px);
}

.referral-code-container {
    margin: 0 1.5rem 1.5rem;
    padding: 1.25rem 1.5rem;
    border: 1px dashed #cbd5f5;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.08), rgba(129, 140, 248, 0.05));
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.referral-code-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.referral-code-header h4 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: #1e293b;
}

.code-subtitle {
    margin: 0.125rem 0 0;
    font-size: 0.875rem;
    color: #475569;
}

.code-icon {
    font-size: 1.75rem;
    line-height: 1;
}

.referral-code-body {
    display: flex;
    align-items: center;
    gap: 1rem;
    justify-content: space-between;
    background: #ffffff;
    border-radius: 999px;
    padding: 0.75rem 1rem;
    border: 1px solid rgba(99, 102, 241, 0.15);
    box-shadow: inset 0 1px 2px rgba(99, 102, 241, 0.08);
}

.code-value {
    font-family: 'Monaco', 'Menlo', monospace;
    font-size: 1rem;
    letter-spacing: 0.1em;
    color: #312e81;
    font-weight: 700;
    text-transform: uppercase;
}

/* Activity Feed */
.activity-feed {
    max-height: 400px;
    overflow-y: auto;
    padding: 0 1.5rem 1.5rem;
}

.activity-item {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: background-color 0.2s ease;
}

.activity-item:hover {
    background: #f8fafc;
}

.activity-item:last-child {
    border-bottom: none;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.activity-icon.success {
    background: #d1fae5;
    color: #10b981;
}

.activity-content {
    flex: 1;
}

.activity-title {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.activity-meta {
    font-size: 0.875rem;
    color: #64748b;
}

.activity-amount {
    font-weight: 700;
    color: #10b981;
    font-size: 1.1rem;
}

/* Quick Actions */
.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    padding: 1.5rem;
}

.action-tile {
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    color: inherit;
}

.action-tile:hover {
    border-color: #667eea;
    background: #f0f4ff;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
}

.action-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 1rem;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}

.action-content h4 {
    margin: 0 0 0.5rem 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #1e293b;
}

.action-content p {
    margin: 0;
    font-size: 0.875rem;
    color: #64748b;
}

/* Achievements Section */
.achievements-section {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem 2rem;
}

.achievements-section h3 {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.achievements-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.achievement-badge {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
}

.achievement-badge.unlocked {
    border: 2px solid #10b981;
    background: linear-gradient(135deg, #d1fae5, #f0fdf4);
}

.achievement-badge.locked {
    opacity: 0.6;
    filter: grayscale(1);
}

.badge-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    background: #e2e8f0;
    color: #64748b;
}

.achievement-badge.unlocked .badge-icon {
    background: #10b981;
    color: white;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.modal-content {
    background: white;
    margin: 5% auto;
    padding: 0;
    border-radius: 16px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-50px) scale(0.9);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #64748b;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background: #f1f5f9;
    color: #1e293b;
}

.modal-body {
    padding: 1.5rem;
}

/* QR Modal Specific */
.qr-code-container {
    text-align: center;
    margin-bottom: 1.5rem;
}

.qr-code-container img {
    max-width: 200px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.qr-code-container p {
    margin: 1rem 0 0 0;
    color: #64748b;
}

.qr-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

/* Notifications */
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: white;
    padding: 1rem 1.5rem;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    gap: 0.75rem;
    z-index: 1001;
    transform: translateX(400px);
    transition: transform 0.3s ease;
}

.notification.show {
    transform: translateX(0);
}

.notification.success {
    border-left: 4px solid #10b981;
}

/* Dark Theme */
.modern-coach-dashboard[data-theme="dark"] {
    background: #0f172a;
    color: #f1f5f9;
}

.modern-coach-dashboard[data-theme="dark"] .dashboard-card,
.modern-coach-dashboard[data-theme="dark"] .stat-card,
.modern-coach-dashboard[data-theme="dark"] .achievement-badge {
    background: #1e293b;
    color: #f1f5f9;
    border: 1px solid #334155;
}

.modern-coach-dashboard[data-theme="dark"] .modal-content {
    background: #1e293b;
    color: #f1f5f9;
}

/* Responsive Design */
@media (max-width: 768px) {
    .header-content {
        flex-direction: column;
        gap: 1.5rem;
        text-align: center;
    }

    .stats-grid {
        grid-template-columns: 1fr;
        margin-top: -2rem;
        padding: 0 1rem;
    }

    .dashboard-grid {
        grid-template-columns: 1fr;
        padding: 0 1rem;
    }

    .quick-actions-grid {
        grid-template-columns: 1fr;
    }

    .achievements-grid {
        grid-template-columns: 1fr;
    }

    .welcome-text h1 {
        font-size: 1.5rem;
    }

    .stat-card {
        padding: 1rem;
    }

    .stat-value {
        font-size: 1.5rem;
    }
}

/* Icon Definitions (using CSS content for icons) */
.icon-share:before { content: "📤"; }
.icon-analytics:before { content: "📊"; }
.icon-theme:before { content: "🌙"; }
.icon-credits:before { content: "💰"; }
.icon-referrals:before { content: "👥"; }
.icon-tier:before { content: "🏆"; }
.icon-conversion:before { content: "📈"; }
.icon-trend-up:before { content: "↗️"; }
.icon-trend-down:before { content: "↘️"; }
.icon-rank:before { content: "#"; }
.icon-link:before { content: "🔗"; }
.icon-copy:before { content: "📋"; }
.icon-qr:before { content: "📱"; }
.icon-activity:before { content: "⚡"; }
.icon-check:before { content: "✓"; }
.icon-activity-empty:before { content: "📭"; }
.icon-actions:before { content: "⚡"; }
.icon-social:before { content: "📱"; }
.icon-email:before { content: "✉️"; }
.icon-resources:before { content: "📚"; }
.icon-support:before { content: "🆘"; }
.icon-chart:before { content: "📊"; }
.icon-leaderboard:before { content: "🏅"; }
.icon-achievements:before { content: "🎖️"; }
.icon-info:before { content: "ℹ️"; }
.icon-chat:before { content: "💬"; }
.icon-faq:before { content: "❓"; }

/* Coach Events */
.coach-events-card .card-header {
    align-items: center;
}

.coach-events-body {
    margin-bottom: 1.5rem;
    padding: 0 1.5rem;
}

.coach-events-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.coach-event-item {
    border: 1px solid rgba(148, 163, 184, 0.25);
    border-radius: 12px;
    padding: 12px 16px;
    background: rgba(248, 250, 252, 0.7);
    transition: border-color 0.2s ease, transform 0.2s ease;
}

.coach-event-item:hover {
    border-color: rgba(37, 99, 235, 0.4);
    transform: translateY(-1px);
}

.modern-coach-dashboard[data-theme="dark"] .coach-event-item {
    background: rgba(30, 41, 59, 0.6);
    border-color: rgba(148, 163, 184, 0.3);
}

.coach-event-item .event-title {
    font-weight: 600;
    margin-bottom: 6px;
}

.coach-event-item .event-title a {
    color: inherit;
    text-decoration: none;
}

.coach-event-item .event-title a:hover {
    text-decoration: underline;
}

.coach-event-item .event-meta {
    font-size: 0.85rem;
    color: #64748b;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}

.modern-coach-dashboard[data-theme="dark"] .coach-event-item .event-meta {
    color: #cbd5f5;
}

.coach-event-item .event-status {
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.coach-event-item .status-active {
    background: rgba(34, 197, 94, 0.18);
    color: #166534;
}

.coach-event-item .status-pending {
    background: rgba(234, 179, 8, 0.18);
    color: #b45309;
}

.coach-event-item .status-inactive {
    background: rgba(148, 163, 184, 0.2);
    color: #475569;
}

.modern-coach-dashboard[data-theme="dark"] .coach-event-item .status-active {
    background: rgba(34, 197, 94, 0.25);
    color: #bbf7d0;
}

.modern-coach-dashboard[data-theme="dark"] .coach-event-item .status-pending {
    background: rgba(234, 179, 8, 0.25);
    color: #fbbf24;
}

.modern-coach-dashboard[data-theme="dark"] .coach-event-item .status-inactive {
    background: rgba(148, 163, 184, 0.25);
    color: #e2e8f0;
}

.coach-event-item .event-actions {
    margin-top: 10px;
}

.event-share {
    margin-top: 10px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.event-share .coach-event-share-input {
    flex: 1;
    min-width: 220px;
    padding: 0.5rem;
    border-radius: 8px;
    border: 1px solid rgba(148, 163, 184, 0.4);
    background: rgba(241, 245, 249, 0.6);
}

.modern-coach-dashboard[data-theme="dark"] .event-share .coach-event-share-input {
    background: rgba(30, 41, 59, 0.7);
    color: #f8fafc;
    border-color: rgba(148, 163, 184, 0.4);
}

.event-share .btn-secondary,
.event-share .btn-tertiary {
    flex: 0 0 auto;
}

.coach-events-form {
    background: rgba(102, 126, 234, 0.08);
    border-radius: 12px;
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin: 0 1.5rem 1.5rem;
}

.modern-coach-dashboard[data-theme="dark"] .coach-events-form {
    border-top-color: rgba(148, 163, 184, 0.2);
}

.coach-event-search {
    display: flex;
    gap: 10px;
    margin-bottom: 10px;
}

.coach-event-search .label-block {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.coach-event-search label {
    font-weight: 600;
    color: #475569;
}

.coach-event-search input[type="text"],
.coach-event-share-input,
.coach-events-card .wide-field {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid rgba(148, 163, 184, 0.35);
    border-radius: 8px;
    background: rgba(248, 250, 252, 0.9);
    font-size: 0.9rem;
}

.coach-events-card .btn-secondary,
.coach-events-card .btn-primary {
    height: 44px;
    align-self: flex-end;
    padding: 0 16px;
}

.coach-event-search-results {
    max-height: 220px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 12px;
}

.coach-event-result {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    padding: 10px 12px;
    border: 1px solid rgba(148, 163, 184, 0.35);
    border-radius: 10px;
    background: #fff;
    cursor: pointer;
    text-align: left;
    transition: border-color 0.2s ease, background 0.2s ease;
}

.coach-event-result strong {
    font-size: 0.95rem;
}

.coach-event-result-meta {
    font-size: 0.75rem;
    color: #64748b;
}

.coach-event-result:hover,
.coach-event-result.selected {
    border-color: rgba(37, 99, 235, 0.6);
    background: rgba(37, 99, 235, 0.08);
}

.modern-coach-dashboard[data-theme="dark"] .coach-event-result {
    background: rgba(30, 41, 59, 0.8);
    border-color: rgba(148, 163, 184, 0.4);
    color: #e2e8f0;
}

.coach-event-search-empty {
    font-size: 0.85rem;
    color: #64748b;
}

.modern-coach-dashboard[data-theme="dark"] .coach-event-search-empty {
    color: #cbd5f5;
}

.coach-event-actions {
    display: flex;
    align-items: center;
    gap: 12px;
}

.coach-event-actions .spinner {
    float: none;
    visibility: hidden;
}

.coach-event-actions .spinner.is-active {
    visibility: visible;
}
</style>