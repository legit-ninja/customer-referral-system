<?php
// includes/class-admin-dashboard-main.php

require_once __DIR__ . '/class-roi-calculator.php';

class InterSoccer_Admin_Dashboard_Main {

    public $roi_calculator;

    public function __construct() {
        $this->roi_calculator = new InterSoccer_ROI_Calculator();
        
        // Handle cache refresh
        add_action('admin_init', [$this, 'handle_cache_refresh']);
    }
    
    /**
     * Handle manual cache refresh request
     */
    public function handle_cache_refresh() {
        if (!isset($_GET['roi_refresh']) || $_GET['roi_refresh'] !== '1') {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (!wp_verify_nonce($_GET['_wpnonce'] ?? '', 'roi_cache_refresh')) {
            return;
        }
        
        $this->roi_calculator->clear_cache();
        
        // Redirect back without the refresh param
        wp_safe_redirect(remove_query_arg(['roi_refresh', '_wpnonce']));
        exit;
    }

    public function render_main_dashboard() {
        $this->roi_calculator->begin_dashboard_roi_compute();
        
        $stats = $this->get_dashboard_stats();
        $credit_stats = $this->get_customer_credit_stats();
        $recent_referrals = $this->get_recent_referrals(10);
        $top_coaches = $this->get_top_coaches(5);
        $financial_overview = $this->get_financial_overview_dashboard();
        $chart_data = $this->get_dashboard_chart_data();
        
        // New ROI data from calculator
        $roi_range = $this->roi_calculator->get_roi_range_display();
        $referral_roi = $this->roi_calculator->calculate_referral_roi();
        $loyalty_roi = $this->roi_calculator->calculate_loyalty_roi();
        $liability = $this->roi_calculator->get_points_liability();
        $cost_breakdown = $this->roi_calculator->get_cost_breakdown();
        $fully_loaded = $this->roi_calculator->calculate_fully_loaded_roi();

        // Get 12-month stats for top cards (consistent with ROI panels)
        $stats_12mo = $this->get_dashboard_stats_12mo();
        ?>
        <div class="wrap intersoccer-admin">
            <h1 class="wp-heading-inline">InterSoccer Referral Dashboard</h1>

            <!-- Stats Cards - Rolling 12 Months (consistent with ROI panels below) -->
            <div class="intersoccer-stats-grid">
                <div class="stat-card referrals-card" data-source="intersoccer_referrals" title="<?php esc_attr_e('Rolling 12 months', 'intersoccer-referral'); ?>">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-businessman"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo esc_html(number_format($stats_12mo['total_referrals'])); ?></h3>
                        <p><?php esc_html_e('Total Referrals', 'intersoccer-referral'); ?> <span class="period-label">(12mo)</span></p>
                        <span class="stat-change positive">+<?php echo esc_html($stats['new_referrals_this_month']); ?> <?php esc_html_e('this month', 'intersoccer-referral'); ?></span>
                    </div>
                </div>

                <div class="stat-card commissions-card" data-source="intersoccer_coach_commissions" title="<?php esc_attr_e('Source: intersoccer_coach_commissions (rolling 12 months)', 'intersoccer-referral'); ?>">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo esc_html(number_format($stats_12mo['total_commissions'], 0)); ?> <span class="unit-label">CHF</span></h3>
                        <p><?php esc_html_e('Coach Commissions', 'intersoccer-referral'); ?> <span class="period-label">(12mo)</span></p>
                        <span class="stat-change positive">+<?php echo esc_html(number_format($stats_12mo['commissions_this_month'], 0)); ?> CHF <?php esc_html_e('this month', 'intersoccer-referral'); ?></span>
                    </div>
                </div>

                <div class="stat-card credits-earned-card" data-source="intersoccer_points_log" title="<?php esc_attr_e('Source: intersoccer_points_log (rolling 12 months). Excludes corrections (error fixes); correction total shown separately below.', 'intersoccer-referral'); ?>">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-awards"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo esc_html(number_format($cost_breakdown['points_earned_chf'], 0)); ?> <span class="unit-label">PTS</span></h3>
                        <p><?php esc_html_e('Points Earned', 'intersoccer-referral'); ?> <span class="period-label">(12mo)</span></p>
                        <span class="stat-change positive">+<?php echo esc_html(number_format($credit_stats['credits_earned_this_month'], 0)); ?> PTS <?php esc_html_e('this month', 'intersoccer-referral'); ?></span>
                        <?php if ($cost_breakdown['correction_points_chf'] > 0): ?>
                        <span class="stat-adjustment-note" title="<?php esc_attr_e('Corrections fix errors but don\'t create new value, so they\'re excluded from cost calculations.', 'intersoccer-referral'); ?>">
                            <?php printf(esc_html__('Excl. %s pts corrections', 'intersoccer-referral'), number_format($cost_breakdown['correction_points_chf'], 0)); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="stat-card credits-used-card" data-source="intersoccer_points_log" title="<?php esc_attr_e('Points redemption only (excl. reversals/refunds). Same definition as Financial Health.', 'intersoccer-referral'); ?>">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-cart"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo esc_html(number_format($liability['redeemed_12mo'], 0)); ?> <span class="unit-label">PTS</span></h3>
                        <p><?php esc_html_e('Points Redeemed', 'intersoccer-referral'); ?> <span class="period-label">(12mo)</span></p>
                        <span class="stat-change neutral"><?php echo esc_html(number_format($liability['redemption_rate'], 1)); ?>% <?php esc_html_e('redemption rate', 'intersoccer-referral'); ?></span>
                    </div>
                </div>

                <div class="stat-card active-credits-card" data-source="intersoccer_points_balance" title="<?php esc_attr_e('Source: usermeta.intersoccer_points_balance (current snapshot). 1 pt = CHF 1.', 'intersoccer-referral'); ?>">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-vault"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo esc_html(number_format($liability['current_chf'], 0)); ?> <span class="unit-label">CHF</span></h3>
                        <p><?php esc_html_e('Active Points (Liability)', 'intersoccer-referral'); ?> <span class="period-label">(now)</span></p>
                        <span class="stat-note">(<?php echo esc_html(number_format($liability['current_chf'], 0)); ?> pts)</span>
                        <span class="stat-change <?php echo $liability['change_chf'] >= 0 ? 'neutral' : 'positive'; ?>">
                            <?php echo ($liability['change_chf'] >= 0 ? '+' : '') . esc_html(number_format($liability['change_chf'], 0)); ?> CHF <?php esc_html_e('vs last month', 'intersoccer-referral'); ?>
                        </span>
                    </div>
                </div>

                <div class="stat-card conversion-card" data-source="intersoccer_referrals" title="<?php esc_attr_e('Rolling 12 months', 'intersoccer-referral'); ?>">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo esc_html(number_format($stats_12mo['conversion_rate'], 1)); ?>%</h3>
                        <p><?php esc_html_e('Referral Conversion Rate', 'intersoccer-referral'); ?> <span class="period-label">(12mo)</span></p>
                        <span class="stat-change <?php echo $stats['conversion_change'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo ($stats['conversion_change'] >= 0 ? '+' : '') . esc_html(number_format($stats['conversion_change'], 1)); ?>% <?php esc_html_e('vs last month', 'intersoccer-referral'); ?>
                        </span>
                    </div>
                </div>
            </div>

            <?php
            // Additional data for new charts
            $referral_funnel = $this->roi_calculator->get_referral_funnel();
            $profit_breakdown = $this->roi_calculator->get_referral_profit_breakdown();
            $coach_leaderboard = $this->roi_calculator->get_coach_leaderboard(10);
            $points_activity = $this->roi_calculator->get_points_activity();
            $families_by_month = $this->roi_calculator->get_referral_families_by_month();
            ?>
            
            <!-- 1. Program ROI Overview (Headline) -->
            <!-- Rolling 12-month ROI with referral and loyalty shown separately -->
            <!-- BLANK until admin enters camp margin % -->
            <?php 
            // Get cache status for display
            $cache_status = $this->roi_calculator->get_cache_status();
            $roi_widgets_pending = $this->roi_calculator->is_roi_widgets_pending();
            $roi_pending_message = $this->roi_calculator->get_roi_pending_message();
            ?>
            <div class="roi-headline-section">
                <?php if ($roi_widgets_pending): ?>
                <div class="notice notice-info inline roi-pending-notice">
                    <p><?php echo esc_html($roi_pending_message); ?></p>
                </div>
                <?php endif; ?>
                <div class="roi-headline-header">
                    <h2><?php esc_html_e('Program ROI (Rolling 12 Months)', 'intersoccer-referral'); ?></h2>
                    <div class="roi-cache-controls">
                        <?php if ($cache_status['cached']): ?>
                        <span class="cache-status" title="<?php esc_attr_e('Results are cached to improve performance. Click refresh to recalculate.', 'intersoccer-referral'); ?>">
                            <span class="dashicons dashicons-clock"></span>
                            <?php 
                            printf(
                                esc_html__('Cached %d min ago', 'intersoccer-referral'),
                                $cache_status['age_minutes']
                            );
                            ?>
                        </span>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(wp_nonce_url(add_query_arg('roi_refresh', '1'), 'roi_cache_refresh')); ?>" 
                           class="button button-secondary button-small roi-refresh-button"
                           title="<?php esc_attr_e('Recalculate all ROI metrics', 'intersoccer-referral'); ?>">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Refresh', 'intersoccer-referral'); ?>
                        </a>
                    </div>
                </div>
                
                <?php if ($roi_range['show_margin_prompt'] ?? false): ?>
                <div class="roi-margin-prompt">
                    <span class="dashicons dashicons-warning"></span>
                    <p>
                        <?php esc_html_e('Set camp margin % in', 'intersoccer-referral'); ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=intersoccer-settings&tab=roi')); ?>"><?php esc_html_e('Settings → ROI', 'intersoccer-referral'); ?></a>
                        <?php esc_html_e('to calculate ROI.', 'intersoccer-referral'); ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <?php 
                // Get per-program ROI ranges with incrementality
                $referral_roi_range = $this->roi_calculator->calculate_referral_roi_with_range();
                $loyalty_roi_range = $this->roi_calculator->calculate_loyalty_roi_with_range();
                $roi_settings = $this->roi_calculator->get_roi_settings();
                $has_margin = $this->roi_calculator->has_camp_margin();
                ?>
                <div class="roi-headline-grid">
                    <!-- Combined ROI Range -->
                    <div class="roi-card roi-card-primary" <?php if (!empty($roi_range['tooltip'])): ?>title="<?php echo esc_attr($roi_range['tooltip']); ?>"<?php endif; ?>>
                        <div class="roi-card-value <?php echo ($roi_range['has_data'] && isset($roi_range['min']) && $roi_range['min'] >= 0) ? 'positive' : (($roi_range['has_data'] && isset($roi_range['min'])) ? 'negative' : 'muted'); ?>">
                            <?php echo esc_html($roi_range['display']); ?>
                        </div>
                        <div class="roi-card-label"><?php esc_html_e('Combined Program ROI', 'intersoccer-referral'); ?></div>
                        <?php if ($roi_range['is_range'] ?? false): ?>
                        <div class="roi-card-note">
                            <?php echo esc_html($roi_range['method_note'] ?? ''); ?>
                            <span class="dashicons dashicons-info-outline roi-info-icon" title="<?php echo esc_attr($roi_range['tooltip'] ?? ''); ?>"></span>
                        </div>
                        <?php else: ?>
                        <div class="roi-card-note"><?php esc_html_e('Includes some bookings that would have happened anyway.', 'intersoccer-referral'); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Referral ROI (with range) -->
                    <?php 
                    $margin_rate = $has_margin ? $roi_settings['camp_margin_percent'] / 100 : 0;
                    $raw_profit = $referral_roi['revenue_ex_vat'] * $margin_rate;
                    $raw_cost = $referral_roi['total_cost'];
                    $displayed_profit = round($raw_profit);
                    $displayed_cost = round($raw_cost);
                    
                    // Display the range if available, otherwise fallback to single value
                    if ($roi_widgets_pending || !empty($referral_roi['pending']) || !empty($referral_roi_range['pending'])) {
                        $referral_roi_display = $roi_pending_message;
                        $referral_roi_class = 'muted';
                    } elseif ($referral_roi_range['has_data'] && $referral_roi_range['low'] !== null) {
                        $referral_roi_display = number_format($referral_roi_range['low'], 1) . '% – ' . number_format($referral_roi_range['high'], 1) . '%';
                        $referral_roi_class = ($referral_roi_range['low'] >= 0) ? 'positive' : 'negative';
                    } elseif (!$has_margin) {
                        $referral_roi_display = __('Set camp margin', 'intersoccer-referral');
                        $referral_roi_class = 'muted';
                    } else {
                        $referral_roi_display = '—';
                        $referral_roi_class = 'muted';
                    }
                    ?>
                    <div class="roi-card" <?php if (!empty($referral_roi_range['tooltip'])): ?>title="<?php echo esc_attr($referral_roi_range['tooltip']); ?>"<?php endif; ?>>
                        <div class="roi-card-value <?php echo esc_attr($referral_roi_class); ?>">
                            <?php echo esc_html($referral_roi_display); ?>
                        </div>
                        <div class="roi-card-label">
                            <?php esc_html_e('Referral ROI', 'intersoccer-referral'); ?>
                            <?php if ($referral_roi_range['has_data']): ?>
                            <span class="roi-badge"><?php esc_html_e('Finance assumption', 'intersoccer-referral'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="roi-card-metrics">
                            <span><?php printf(esc_html__('Gross profit (at margin): %s CHF', 'intersoccer-referral'), number_format($displayed_profit, 0)); ?></span>
                            <span><?php printf(esc_html__('Cost: %s CHF', 'intersoccer-referral'), number_format($displayed_cost, 0)); ?></span>
                        </div>
                        <?php if ($referral_roi_range['has_data']): ?>
                        <div class="roi-card-note roi-incrementality-note">
                            <?php printf(
                                esc_html__('%d%%–%d%% incrementality', 'intersoccer-referral'),
                                (int) ($referral_roi_range['incrementality_low'] * 100),
                                (int) ($referral_roi_range['incrementality_high'] * 100)
                            ); ?>
                            <span class="dashicons dashicons-info-outline roi-info-icon" title="<?php echo esc_attr($referral_roi_range['tooltip'] ?? ''); ?>"></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Loyalty ROI (with range) -->
                    <?php 
                    // Display the range if available, otherwise fallback to single value
                    if ($roi_widgets_pending || !empty($loyalty_roi['pending']) || !empty($loyalty_roi_range['pending'])) {
                        $loyalty_roi_display = $roi_pending_message;
                        $loyalty_roi_class = 'muted';
                    } elseif ($loyalty_roi_range['has_data'] && $loyalty_roi_range['low'] !== null) {
                        $loyalty_roi_display = number_format($loyalty_roi_range['low'], 1) . '% – ' . number_format($loyalty_roi_range['high'], 1) . '%';
                        $loyalty_roi_class = ($loyalty_roi_range['low'] >= 0) ? 'positive' : 'negative';
                    } elseif (!$has_margin) {
                        $loyalty_roi_display = __('Set camp margin', 'intersoccer-referral');
                        $loyalty_roi_class = 'muted';
                    } else {
                        $loyalty_roi_display = $loyalty_roi['roi_display'];
                        $loyalty_roi_class = ($loyalty_roi['roi_percentage'] !== null && $loyalty_roi['roi_percentage'] >= 0) ? 'positive' : (($loyalty_roi['roi_percentage'] !== null) ? 'negative' : 'muted');
                    }
                    ?>
                    <div class="roi-card" <?php if (!empty($loyalty_roi_range['tooltip'])): ?>title="<?php echo esc_attr($loyalty_roi_range['tooltip']); ?>"<?php endif; ?>>
                        <div class="roi-card-value <?php echo esc_attr($loyalty_roi_class); ?>">
                            <?php echo esc_html($loyalty_roi_display); ?>
                        </div>
                        <div class="roi-card-label">
                            <?php esc_html_e('Loyalty ROI', 'intersoccer-referral'); ?>
                            <?php if ($loyalty_roi_range['has_data']): ?>
                            <span class="roi-badge"><?php esc_html_e('Finance assumption', 'intersoccer-referral'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="roi-card-metrics">
                            <span><?php printf(esc_html__('Extra spend: +%s CHF/customer', 'intersoccer-referral'), number_format($loyalty_roi['extra_spend_per_customer'], 0)); ?></span>
                        </div>
                        <?php if ($loyalty_roi_range['has_data']): ?>
                        <div class="roi-card-note roi-incrementality-note">
                            <?php printf(
                                esc_html__('%d%%–%d%% incrementality', 'intersoccer-referral'),
                                (int) ($loyalty_roi_range['incrementality_low'] * 100),
                                (int) ($loyalty_roi_range['incrementality_high'] * 100)
                            ); ?>
                            <span class="dashicons dashicons-info-outline roi-info-icon" title="<?php echo esc_attr($loyalty_roi_range['tooltip'] ?? ''); ?>"></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Attribution Note -->
                <div class="roi-attribution-note">
                    <span class="dashicons dashicons-info-outline"></span>
                    <?php esc_html_e('Campaign and ad-spend decisions need channel attribution, which isn\'t tracked yet.', 'intersoccer-referral'); ?>
                </div>
                
                <!-- Fully Loaded ROI (behind toggle) -->
                <details class="fully-loaded-toggle">
                    <summary><?php esc_html_e('Show fully loaded estimate', 'intersoccer-referral'); ?></summary>
                    <div class="fully-loaded-content">
                        <?php if ($cost_breakdown['has_estimates']): ?>
                        <div class="roi-card roi-card-estimate">
                            <div class="roi-card-value <?php echo ($fully_loaded['roi_percentage'] !== null && $fully_loaded['roi_percentage'] >= 0) ? 'positive' : (($fully_loaded['roi_percentage'] !== null) ? 'negative' : 'muted'); ?>">
                                <?php echo esc_html($fully_loaded['roi_display']); ?>
                            </div>
                            <div class="roi-card-label">
                                <?php esc_html_e('Fully Loaded ROI', 'intersoccer-referral'); ?>
                                <span class="estimate-badge-inline"><?php esc_html_e('Estimate', 'intersoccer-referral'); ?></span>
                            </div>
                            <div class="roi-card-detail">
                                <?php esc_html_e('Includes staff time, platform fees, ad spend', 'intersoccer-referral'); ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <p class="empty-state"><?php esc_html_e('Enter estimated costs in Settings → ROI to see fully loaded ROI.', 'intersoccer-referral'); ?></p>
                        <?php endif; ?>
                    </div>
                </details>
            </div>
            
            <!-- 6. "Owed to Families": Unredeemed Points Liability -->
            <div class="liability-section">
                <h2><?php esc_html_e('Owed to Families', 'intersoccer-referral'); ?></h2>
                <p class="expiry-policy-note">
                    <span class="dashicons dashicons-clock"></span>
                    <?php echo esc_html($liability['expiry_policy']); ?>
                </p>
                <div class="liability-metrics">
                    <div class="liability-metric">
                        <span class="liability-value"><?php echo esc_html(number_format($liability['current_chf'], 0)); ?> <span class="unit-label">CHF</span></span>
                        <span class="liability-label"><?php esc_html_e('Unredeemed Points', 'intersoccer-referral'); ?></span>
                    </div>
                    <div class="liability-metric">
                        <span class="liability-value <?php echo $liability['change_chf'] >= 0 ? 'increase' : 'decrease'; ?>">
                            <?php echo ($liability['change_chf'] >= 0 ? '+' : '') . esc_html(number_format($liability['change_chf'], 0)); ?> <span class="unit-label">CHF</span>
                        </span>
                        <span class="liability-label"><?php esc_html_e('vs Last Month', 'intersoccer-referral'); ?></span>
                    </div>
                    <?php if ($liability['total_expired'] > 0 || $liability['total_reversed'] > 0): ?>
                    <div class="liability-metric">
                        <span class="liability-value decrease">-<?php echo esc_html(number_format($liability['total_expired'] + $liability['total_reversed'], 0)); ?> <span class="unit-label">CHF</span></span>
                        <span class="liability-label"><?php esc_html_e('Expired + Reversed (All Time)', 'intersoccer-referral'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php $age = $liability['age_breakdown']; ?>
                <?php if ($liability['current_chf'] > 0): ?>
                <div class="liability-age-breakdown">
                    <h4><?php esc_html_e('Liability Age (FIFO)', 'intersoccer-referral'); ?></h4>
                    <div class="age-buckets">
                        <div class="age-bucket">
                            <span class="age-value"><?php echo esc_html(number_format($age['0_6_months'], 0)); ?> CHF</span>
                            <span class="age-label"><?php esc_html_e('0-6 months', 'intersoccer-referral'); ?></span>
                            <span class="age-pct"><?php echo esc_html($age['0_6_months_pct']); ?>%</span>
                        </div>
                        <div class="age-bucket">
                            <span class="age-value"><?php echo esc_html(number_format($age['6_12_months'], 0)); ?> CHF</span>
                            <span class="age-label"><?php esc_html_e('6-12 months', 'intersoccer-referral'); ?></span>
                            <span class="age-pct"><?php echo esc_html($age['6_12_months_pct']); ?>%</span>
                        </div>
                        <div class="age-bucket <?php echo $age['12_plus_months'] > 0 ? 'age-warning' : ''; ?>">
                            <span class="age-value"><?php echo esc_html(number_format($age['12_plus_months'], 0)); ?> CHF</span>
                            <span class="age-label"><?php esc_html_e('12+ months', 'intersoccer-referral'); ?></span>
                            <span class="age-pct"><?php echo esc_html($age['12_plus_months_pct']); ?>%</span>
                        </div>
                        <?php if ($age['unreconciled'] != 0): ?>
                        <div class="age-bucket age-unreconciled <?php echo $age['unreconciled'] > 0 ? 'age-warning' : ''; ?>" title="<?php esc_attr_e('Gap between usermeta total and log-based calculation. May be due to manual adjustments or data issues.', 'intersoccer-referral'); ?>">
                            <span class="age-value"><?php echo esc_html(number_format($age['unreconciled'], 0)); ?> CHF</span>
                            <span class="age-label"><?php esc_html_e('Unreconciled', 'intersoccer-referral'); ?></span>
                            <span class="age-pct"><?php echo esc_html($age['unreconciled_pct']); ?>%</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Financial Health Summary -->
            <!-- Figure sources: intersoccer_points_log (canonical SoT) -->
            <?php
            $financial_pending = !empty($financial_overview['pending']);
            $has_financial_data = $financial_pending || !empty($financial_overview['has_data']);
            ?>
            <div class="financial-health-section">
                <h2><?php esc_html_e('Financial Health Overview', 'intersoccer-referral'); ?></h2>
                <?php if (!$has_financial_data): ?>
                <div class="intersoccer-notice intersoccer-notice-empty" style="margin-bottom: 16px;">
                    <span class="dashicons dashicons-info-outline"></span>
                    <p><?php esc_html_e('Financial metrics will appear once the loyalty program has transactions.', 'intersoccer-referral'); ?></p>
                </div>
                <?php endif; ?>
                <div class="financial-metrics">
                    <div class="financial-metric" title="<?php esc_attr_e('Points earned + commissions this calendar month (cost when earned)', 'intersoccer-referral'); ?>">
                        <span class="metric-label"><?php esc_html_e('Program Cost (This Month)', 'intersoccer-referral'); ?></span>
                        <?php if ($financial_pending): ?>
                        <span class="metric-value muted"><?php echo esc_html($this->roi_calculator->get_roi_pending_message()); ?></span>
                        <?php else: ?>
                        <span class="metric-value"><?php echo esc_html(number_format($financial_overview['monthly_program_cost'], 0)); ?> <span class="unit-label">CHF</span></span>
                        <span class="metric-detail"><?php printf(
                            esc_html__('12mo avg: %s CHF', 'intersoccer-referral'),
                            number_format($cost_breakdown['total_real_cost'] / 12, 0)
                        ); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="financial-metric" title="<?php esc_attr_e('Redeemed ÷ Earned (12mo, excl. corrections) × 100', 'intersoccer-referral'); ?>">
                        <span class="metric-label"><?php esc_html_e('Redemption Rate', 'intersoccer-referral'); ?></span>
                        <span class="metric-value"><?php echo esc_html(number_format($financial_overview['credit_utilization_rate'], 1)); ?>%</span>
                        <span class="metric-detail"><?php printf(
                            esc_html__('%s / %s pts', 'intersoccer-referral'),
                            number_format($financial_overview['points_redeemed'], 0),
                            number_format($financial_overview['points_earned'], 0)
                        ); ?></span>
                    </div>
                    <div class="financial-metric" title="<?php esc_attr_e('Source: intersoccer_points_log (total earned / unique customers)', 'intersoccer-referral'); ?>">
                        <span class="metric-label"><?php esc_html_e('Avg Points per Customer', 'intersoccer-referral'); ?></span>
                        <span class="metric-value"><?php echo esc_html(number_format($financial_overview['avg_credits_per_customer'], 0)); ?> <span class="unit-label">PTS</span></span>
                    </div>
                    <div class="financial-metric" title="<?php esc_attr_e('Points as CHF liability', 'intersoccer-referral'); ?>">
                        <span class="metric-label"><?php esc_html_e('Active Points (Liability)', 'intersoccer-referral'); ?></span>
                        <span class="metric-value"><?php echo esc_html(number_format($liability['current_chf'], 0)); ?> <span class="unit-label">CHF</span></span>
                    </div>
                </div>
            </div>

            <div class="intersoccer-dashboard-grid">
                <!-- 2. New Families per Month (12 months) -->
                <div class="dashboard-widget chart-widget">
                    <h2><?php esc_html_e('Referral: New Families per Month', 'intersoccer-referral'); ?></h2>
                    <?php if (array_sum(array_column($families_by_month, 'value')) > 0): ?>
                    <canvas id="newFamiliesChart" width="400" height="250"></canvas>
                    <?php else: ?>
                    <div class="intersoccer-notice intersoccer-notice-empty">
                        <span class="dashicons dashicons-info-outline"></span>
                        <p><?php esc_html_e('No referred families yet.', 'intersoccer-referral'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- 3. Referral Profit vs Program Cost -->
                <div class="dashboard-widget">
                    <h2><?php esc_html_e('Referral: Profit vs Program Cost', 'intersoccer-referral'); ?></h2>
                    <?php if ($profit_breakdown['show_margin_prompt'] ?? false): ?>
                    <div class="intersoccer-notice intersoccer-notice-empty">
                        <span class="dashicons dashicons-warning"></span>
                        <p><?php esc_html_e('Set camp margin % in Settings → ROI to see profit breakdown.', 'intersoccer-referral'); ?></p>
                    </div>
                    <?php elseif ($profit_breakdown['has_data'] ?? false): ?>
                    <div class="profit-breakdown">
                        <div class="profit-row">
                            <span class="profit-label"><?php esc_html_e('First Order Profit:', 'intersoccer-referral'); ?></span>
                            <span class="profit-value"><?php echo esc_html(number_format($profit_breakdown['first_order_profit'], 0)); ?> CHF</span>
                        </div>
                        <div class="profit-row">
                            <span class="profit-label"><?php esc_html_e('Repeat Order Profit:', 'intersoccer-referral'); ?></span>
                            <span class="profit-value"><?php echo esc_html(number_format($profit_breakdown['repeat_order_profit'], 0)); ?> CHF</span>
                        </div>
                        <div class="profit-row profit-total">
                            <span class="profit-label"><?php esc_html_e('Total Profit:', 'intersoccer-referral'); ?></span>
                            <span class="profit-value"><?php echo esc_html(number_format($profit_breakdown['total_profit'], 0)); ?> CHF</span>
                        </div>
                        <div class="profit-row">
                            <span class="profit-label"><?php esc_html_e('Program Cost:', 'intersoccer-referral'); ?></span>
                            <span class="profit-value negative"><?php echo esc_html(number_format($profit_breakdown['total_cost'], 0)); ?> CHF</span>
                        </div>
                        <hr class="profit-divider">
                        <div class="cost-comparison">
                            <div class="cost-item">
                                <span class="cost-label"><?php esc_html_e('Cost per Family (Referral):', 'intersoccer-referral'); ?></span>
                                <span class="cost-value"><?php echo esc_html(number_format($profit_breakdown['cost_per_family_referral'], 0)); ?> CHF</span>
                            </div>
                            <?php if ($profit_breakdown['show_ad_comparison']): ?>
                            <div class="cost-item">
                                <span class="cost-label"><?php esc_html_e('Cost per Family (Ads):', 'intersoccer-referral'); ?> <span class="estimate-badge-inline"><?php esc_html_e('Estimate', 'intersoccer-referral'); ?></span></span>
                                <span class="cost-value"><?php echo esc_html(number_format($profit_breakdown['cost_per_family_ads'], 0)); ?> CHF</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="intersoccer-notice intersoccer-notice-empty">
                        <span class="dashicons dashicons-info-outline"></span>
                        <p><?php esc_html_e('No referred families yet.', 'intersoccer-referral'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- 4. Loyalty Panel: Spend Lift + Repeat Rate -->
                <div class="dashboard-widget">
                    <h2><?php esc_html_e('Loyalty: Point Holder Performance', 'intersoccer-referral'); ?></h2>
                    <p class="widget-disclaimer"><?php esc_html_e('Comparison, not proof the program caused it.', 'intersoccer-referral'); ?></p>
                    <?php $comparison = $this->roi_calculator->get_holder_vs_nonholder_comparison(); ?>
                    <?php if ($comparison['holder_count'] > 0): ?>
                    <div class="loyalty-comparison">
                        <div class="comparison-section">
                            <h4><?php esc_html_e('Average Spend', 'intersoccer-referral'); ?></h4>
                            <div class="comparison-row">
                                <span class="comparison-label"><?php esc_html_e('Point Holders:', 'intersoccer-referral'); ?></span>
                                <span class="comparison-value highlight"><?php echo esc_html(number_format($comparison['holder_avg_spend'], 0)); ?> CHF</span>
                            </div>
                            <div class="comparison-row">
                                <span class="comparison-label"><?php esc_html_e('Non-Holders:', 'intersoccer-referral'); ?></span>
                                <span class="comparison-value"><?php echo esc_html(number_format($comparison['non_holder_avg_spend'], 0)); ?> CHF</span>
                            </div>
                            <?php 
                            $spend_diff = $comparison['holder_avg_spend'] - $comparison['non_holder_avg_spend'];
                            $spend_diff_pct = $comparison['non_holder_avg_spend'] > 0 ? ($spend_diff / $comparison['non_holder_avg_spend']) * 100 : 0;
                            ?>
                            <div class="comparison-row comparison-diff">
                                <span class="comparison-label"><?php esc_html_e('Difference:', 'intersoccer-referral'); ?></span>
                                <span class="comparison-value <?php echo $spend_diff >= 0 ? 'positive' : 'negative'; ?>">
                                    <?php echo ($spend_diff >= 0 ? '+' : '') . esc_html(number_format($spend_diff, 0)); ?> CHF
                                    (<?php echo ($spend_diff_pct >= 0 ? '+' : '') . esc_html(number_format($spend_diff_pct, 1)); ?>%)
                                </span>
                            </div>
                        </div>
                        <div class="comparison-section">
                            <h4><?php esc_html_e('Repeat Booking Rate', 'intersoccer-referral'); ?></h4>
                            <div class="comparison-row">
                                <span class="comparison-label"><?php esc_html_e('Point Holders:', 'intersoccer-referral'); ?></span>
                                <span class="comparison-value highlight"><?php echo esc_html(number_format($comparison['holder_repeat_rate'], 1)); ?>%</span>
                            </div>
                            <div class="comparison-row">
                                <span class="comparison-label"><?php esc_html_e('Non-Holders:', 'intersoccer-referral'); ?></span>
                                <span class="comparison-value"><?php echo esc_html(number_format($comparison['non_holder_repeat_rate'], 1)); ?>%</span>
                            </div>
                            <?php $repeat_diff = $comparison['holder_repeat_rate'] - $comparison['non_holder_repeat_rate']; ?>
                            <div class="comparison-row comparison-diff">
                                <span class="comparison-label"><?php esc_html_e('Difference:', 'intersoccer-referral'); ?></span>
                                <span class="comparison-value <?php echo $repeat_diff >= 0 ? 'positive' : 'negative'; ?>">
                                    <?php echo ($repeat_diff >= 0 ? '+' : '') . esc_html(number_format($repeat_diff, 1)); ?> pp
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="intersoccer-notice intersoccer-notice-empty">
                        <span class="dashicons dashicons-info-outline"></span>
                        <p><?php esc_html_e('Not enough data to compare.', 'intersoccer-referral'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- 5. Points Earned, Redeemed, Expired per Month -->
                <div class="dashboard-widget chart-widget">
                    <h2><?php esc_html_e('Points Activity (CHF, 12 Months)', 'intersoccer-referral'); ?></h2>
                    <canvas id="pointsActivityChart" width="400" height="250"></canvas>
                    <div class="chart-legend">
                        <span class="legend-item"><span class="color-box earned"></span><?php esc_html_e('Earned', 'intersoccer-referral'); ?></span>
                        <span class="legend-item"><span class="color-box redeemed"></span><?php esc_html_e('Redeemed', 'intersoccer-referral'); ?></span>
                        <span class="legend-item"><span class="color-box expired"></span><?php esc_html_e('Expired', 'intersoccer-referral'); ?></span>
                    </div>
                </div>

                <!-- 7. Program Cost Breakdown (Real costs only, split by referral vs loyalty) -->
                <div class="dashboard-widget">
                    <h2><?php esc_html_e('Program Cost Breakdown', 'intersoccer-referral'); ?> <span class="period-label">(<?php esc_html_e('12mo', 'intersoccer-referral'); ?>)</span></h2>
                    <?php if (!empty($cost_breakdown['pending'])): ?>
                    <p class="muted"><?php echo esc_html($this->roi_calculator->get_roi_pending_message()); ?></p>
                    <?php else: ?>
                    <div class="cost-breakdown">
                        <div class="cost-breakdown-section">
                            <h4><?php esc_html_e('Referral Cost', 'intersoccer-referral'); ?></h4>
                            <div class="cost-breakdown-item">
                                <span class="cost-label"><?php esc_html_e('Coach Commissions:', 'intersoccer-referral'); ?></span>
                                <span class="cost-value"><?php echo esc_html(number_format($cost_breakdown['commissions_chf'], 0)); ?> CHF</span>
                            </div>
                            <div class="cost-breakdown-item">
                                <span class="cost-label"><?php esc_html_e('Referral Points:', 'intersoccer-referral'); ?></span>
                                <span class="cost-value"><?php echo esc_html(number_format($cost_breakdown['referral_points_chf'], 0)); ?> CHF</span>
                            </div>
                            <div class="cost-breakdown-item cost-subtotal">
                                <span class="cost-label"><?php esc_html_e('Subtotal:', 'intersoccer-referral'); ?></span>
                                <span class="cost-value"><?php echo esc_html(number_format($cost_breakdown['referral_cost_chf'], 0)); ?> CHF</span>
                            </div>
                        </div>
                        <div class="cost-breakdown-section">
                            <h4><?php esc_html_e('Loyalty Cost', 'intersoccer-referral'); ?></h4>
                            <div class="cost-breakdown-item">
                                <span class="cost-label"><?php esc_html_e('Purchase Rewards:', 'intersoccer-referral'); ?></span>
                                <span class="cost-value"><?php echo esc_html(number_format($cost_breakdown['loyalty_activity_chf'], 0)); ?> CHF</span>
                            </div>
                            <?php if ($cost_breakdown['goodwill_points_chf'] > 0): ?>
                            <div class="cost-breakdown-item">
                                <span class="cost-label"><?php esc_html_e('Goodwill/Bonus:', 'intersoccer-referral'); ?></span>
                                <span class="cost-value"><?php echo esc_html(number_format($cost_breakdown['goodwill_points_chf'], 0)); ?> CHF</span>
                            </div>
                            <?php endif; ?>
                            <div class="cost-breakdown-item cost-subtotal">
                                <span class="cost-label"><?php esc_html_e('Subtotal:', 'intersoccer-referral'); ?></span>
                                <span class="cost-value"><?php echo esc_html(number_format($cost_breakdown['loyalty_cost_chf'], 0)); ?> CHF</span>
                            </div>
                        </div>
                        <div class="cost-breakdown-item cost-total">
                            <span class="cost-label"><?php esc_html_e('Total Program Cost:', 'intersoccer-referral'); ?></span>
                            <span class="cost-value"><?php echo esc_html(number_format($cost_breakdown['total_real_cost'], 0)); ?> CHF</span>
                        </div>
                        <?php if ($cost_breakdown['revenue_ex_vat'] > 0): ?>
                        <div class="cost-breakdown-item cost-percent-revenue">
                            <span class="cost-label"><?php esc_html_e('% of Ex-VAT Revenue:', 'intersoccer-referral'); ?></span>
                            <span class="cost-value">
                                <?php echo esc_html(number_format($cost_breakdown['cost_percent_revenue'], 2)); ?>%
                                <span class="revenue-note">(<?php echo esc_html(number_format($cost_breakdown['revenue_ex_vat'], 0)); ?> CHF)</span>
                            </span>
                        </div>
                        <?php endif; ?>
                        <?php if ($cost_breakdown['correction_points_chf'] > 0): ?>
                        <div class="cost-breakdown-section adjustments-section">
                            <h4><?php esc_html_e('Corrections (not cost)', 'intersoccer-referral'); ?></h4>
                            <div class="cost-breakdown-item adjustment">
                                <span class="cost-label"><?php esc_html_e('Error Fixes/Cancellations:', 'intersoccer-referral'); ?></span>
                                <span class="cost-value adjustment-value"><?php echo esc_html(number_format($cost_breakdown['correction_points_chf'], 0)); ?> CHF</span>
                            </div>
                            <p class="adjustment-note"><?php esc_html_e('Corrections fix mistakes, they don\'t create new value.', 'intersoccer-referral'); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <p class="cost-derivation-note">
                        <?php esc_html_e('Cost per new family = Referral Cost ÷ New Families', 'intersoccer-referral'); ?>
                    </p>
                    
                    <?php if ($cost_breakdown['has_estimates']): ?>
                    <details class="estimated-costs-toggle">
                        <summary><?php esc_html_e('Show estimated costs (fully loaded)', 'intersoccer-referral'); ?></summary>
                        <div class="estimated-costs">
                            <?php if ($cost_breakdown['ad_spend_chf'] > 0): ?>
                            <div class="cost-breakdown-item estimate">
                                <span class="cost-label"><?php esc_html_e('Ad Spend:', 'intersoccer-referral'); ?> <span class="estimate-badge-inline"><?php esc_html_e('Estimate', 'intersoccer-referral'); ?></span></span>
                                <span class="cost-value"><?php echo esc_html(number_format($cost_breakdown['ad_spend_chf'], 0)); ?> CHF</span>
                            </div>
                            <?php endif; ?>
                            <?php if ($cost_breakdown['staff_time_chf'] > 0): ?>
                            <div class="cost-breakdown-item estimate">
                                <span class="cost-label"><?php esc_html_e('Staff Time:', 'intersoccer-referral'); ?> <span class="estimate-badge-inline"><?php esc_html_e('Estimate', 'intersoccer-referral'); ?></span></span>
                                <span class="cost-value"><?php echo esc_html(number_format($cost_breakdown['staff_time_chf'], 0)); ?> CHF</span>
                            </div>
                            <?php endif; ?>
                            <?php if ($cost_breakdown['platform_fees_chf'] > 0): ?>
                            <div class="cost-breakdown-item estimate">
                                <span class="cost-label"><?php esc_html_e('Platform Fees:', 'intersoccer-referral'); ?> <span class="estimate-badge-inline"><?php esc_html_e('Estimate', 'intersoccer-referral'); ?></span></span>
                                <span class="cost-value"><?php echo esc_html(number_format($cost_breakdown['platform_fees_chf'], 0)); ?> CHF</span>
                            </div>
                            <?php endif; ?>
                            <div class="cost-breakdown-item cost-total">
                                <span class="cost-label"><?php esc_html_e('Total Fully Loaded:', 'intersoccer-referral'); ?></span>
                                <span class="cost-value"><?php echo esc_html(number_format($cost_breakdown['total_fully_loaded_chf'], 0)); ?> CHF</span>
                            </div>
                        </div>
                    </details>
                    <?php endif; ?>
                    <?php endif; /* cost breakdown pending */ ?>
                </div>

                <!-- 8. Referral Funnel -->
                <div class="dashboard-widget">
                    <h2><?php esc_html_e('Referral Funnel', 'intersoccer-referral'); ?> <span class="period-label">(<?php esc_html_e('12mo', 'intersoccer-referral'); ?>)</span></h2>
                    <?php if ($referral_funnel['has_data']): ?>
                    <div class="referral-funnel">
                        <div class="funnel-step">
                            <span class="funnel-value"><?php echo esc_html(number_format($referral_funnel['codes_used'])); ?></span>
                            <span class="funnel-label"><?php esc_html_e('Codes/Links Used', 'intersoccer-referral'); ?></span>
                            <span class="funnel-definition"><?php esc_html_e('Distinct codes attached to orders', 'intersoccer-referral'); ?></span>
                        </div>
                        <?php if ($referral_funnel['visits_tracked']): ?>
                        <div class="funnel-step">
                            <span class="funnel-value"><?php echo esc_html(number_format($referral_funnel['visits'])); ?></span>
                            <span class="funnel-label"><?php esc_html_e('Visits', 'intersoccer-referral'); ?></span>
                        </div>
                        <?php else: ?>
                        <div class="funnel-step funnel-empty">
                            <span class="funnel-value">—</span>
                            <span class="funnel-label"><?php esc_html_e('Visits (not tracked)', 'intersoccer-referral'); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="funnel-step">
                            <span class="funnel-value"><?php echo esc_html(number_format($referral_funnel['bookings'])); ?></span>
                            <span class="funnel-label"><?php esc_html_e('Bookings', 'intersoccer-referral'); ?></span>
                            <span class="funnel-definition"><?php esc_html_e('Referred orders placed', 'intersoccer-referral'); ?></span>
                        </div>
                        <div class="funnel-step">
                            <span class="funnel-value"><?php echo esc_html(number_format($referral_funnel['paid_orders'])); ?></span>
                            <span class="funnel-label"><?php esc_html_e('Paid Orders', 'intersoccer-referral'); ?></span>
                            <span class="funnel-definition"><?php esc_html_e('Completed or processing', 'intersoccer-referral'); ?></span>
                        </div>
                        <div class="funnel-step funnel-final">
                            <span class="funnel-value"><?php echo esc_html(number_format($referral_funnel['new_families'])); ?></span>
                            <span class="funnel-label"><?php esc_html_e('New Families', 'intersoccer-referral'); ?></span>
                            <span class="funnel-definition"><?php esc_html_e('No prior paid order', 'intersoccer-referral'); ?></span>
                            <span class="funnel-sample"><?php printf(esc_html__('n=%d', 'intersoccer-referral'), $referral_funnel['sample_size']); ?></span>
                        </div>
                    </div>
                    <?php if ($referral_funnel['sample_size'] < 30): ?>
                    <div class="funnel-warning">
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e('Too few families to judge yet.', 'intersoccer-referral'); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($referral_funnel['codes_used'] < $referral_funnel['bookings']): ?>
                    <div class="funnel-note">
                        <span class="dashicons dashicons-info-outline"></span>
                        <?php esc_html_e('Bookings can exceed codes when one code is used for multiple orders, or when a link referral has no code.', 'intersoccer-referral'); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($referral_funnel['existing_customer_share'] > 0): ?>
                    <div class="funnel-note">
                        <span class="dashicons dashicons-info-outline"></span>
                        <?php printf(
                            /* translators: 1: existing customer percentage, 2: existing customer count, 3: new family count */
                            esc_html__('%1$s%% of unique referred customers (%2$d) were existing customers; %3$d were new families.', 'intersoccer-referral'),
                            number_format($referral_funnel['existing_customer_share'], 1),
                            $referral_funnel['existing_customers'],
                            $referral_funnel['new_families']
                        ); ?>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="intersoccer-notice intersoccer-notice-empty">
                        <span class="dashicons dashicons-info-outline"></span>
                        <p><?php esc_html_e('No referral data yet.', 'intersoccer-referral'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- 9. Coach Leaderboard (ranked by new families) -->
                <div class="dashboard-widget">
                    <h2><?php esc_html_e('Coach Leaderboard', 'intersoccer-referral'); ?></h2>
                    <p class="widget-note"><?php esc_html_e('Coach referrals only — customer-to-customer referrals appear in the funnel totals above.', 'intersoccer-referral'); ?></p>
                    <?php if (!empty($coach_leaderboard)): ?>
                    <table class="coach-leaderboard-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Rank', 'intersoccer-referral'); ?></th>
                                <th><?php esc_html_e('Coach', 'intersoccer-referral'); ?></th>
                                <th><?php esc_html_e('New Families', 'intersoccer-referral'); ?></th>
                                <th><?php esc_html_e('Code Uses', 'intersoccer-referral'); ?></th>
                                <th><?php esc_html_e('Paid Orders', 'intersoccer-referral'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($coach_leaderboard as $index => $coach): ?>
                            <tr>
                                <td class="rank"><?php echo $index + 1; ?></td>
                                <td class="coach-name"><?php echo esc_html($coach->display_name); ?></td>
                                <td class="highlight"><?php echo esc_html($coach->new_families); ?></td>
                                <td><?php echo esc_html($coach->codes_used); ?></td>
                                <td><?php echo esc_html($coach->paid_orders); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="intersoccer-notice intersoccer-notice-empty">
                        <span class="dashicons dashicons-info-outline"></span>
                        <p><?php esc_html_e('No coach referrals yet.', 'intersoccer-referral'); ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Activity -->
                <div class="dashboard-widget">
                    <h2>Recent Activity</h2>
                    <div class="recent-activity">
                        <?php
                        $recent_activities = $this->get_recent_activities(8);
                        foreach ($recent_activities as $activity):
                        ?>
                        <div class="activity-item activity-<?php echo $activity->type; ?>">
                            <div class="activity-icon">
                                <span class="dashicons dashicons-<?php echo $activity->icon; ?>"></span>
                            </div>
                            <div class="activity-content">
                                <p><?php echo $activity->message; ?></p>
                                <span class="activity-time"><?php echo human_time_diff(strtotime($activity->created_at), current_time('timestamp')); ?> ago</span>
                            </div>
                            <?php if ($activity->amount > 0): ?>
                            <div class="activity-amount">
                                <?php echo $activity->amount_prefix; ?><?php echo number_format($activity->amount, 0); ?> CHF
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-widget">
                    <h2>Quick Actions</h2>
                    <div class="quick-actions">
                        <a href="<?php echo admin_url('admin.php?page=intersoccer-coaches'); ?>" class="quick-action-btn">
                            <span class="dashicons dashicons-groups"></span>
                            Manage Coaches
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=intersoccer-customer-referrals'); ?>" class="quick-action-btn">
                            <span class="dashicons dashicons-tickets-alt"></span>
                            Customer Credits
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=intersoccer-financial-report'); ?>" class="quick-action-btn">
                            <span class="dashicons dashicons-chart-area"></span>
                            Financial Report
                        </a>
                        <button class="quick-action-btn" id="export-data">
                            <span class="dashicons dashicons-download"></span>
                            Export All Data
                        </button>
                    </div>
                </div>
            </div>

            <?php
            // Provide chart data to JS without inline <script> tags in templates.
            wp_localize_script('intersoccer-admin-js', 'intersoccerChartData', $chart_data);
            ?>
        </div>
        <?php
    }

    /**
     * Get dashboard summary statistics
     */
    private function get_dashboard_stats() {
        global $wpdb;
        $referrals_table = $wpdb->prefix . 'intersoccer_referrals';

        // Total referrals
        $total_referrals = $wpdb->get_var("SELECT COUNT(*) FROM $referrals_table");

        // New referrals this month
        $month_start = date('Y-m-01');
        $new_referrals_this_month = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $referrals_table WHERE created_at >= %s",
            $month_start
        ));

        // Total commissions
        $total_commissions = $wpdb->get_var("SELECT COALESCE(SUM(commission_amount + COALESCE(loyalty_bonus, 0) + COALESCE(retention_bonus, 0)), 0) FROM $referrals_table WHERE status IN ('completed', 'approved', 'paid')");

        // Commissions this month
        $commissions_this_month = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(commission_amount + COALESCE(loyalty_bonus, 0) + COALESCE(retention_bonus, 0)), 0) FROM $referrals_table WHERE status IN ('completed', 'approved', 'paid') AND created_at >= %s",
            $month_start
        ));

        // Conversion rate
        $completed_referrals = $wpdb->get_var("SELECT COUNT(*) FROM $referrals_table WHERE status = 'completed'");
        $conversion_rate = $total_referrals > 0 ? ($completed_referrals / $total_referrals) * 100 : 0;

        // Conversion change vs last month
        $last_month_start = date('Y-m-01', strtotime('-1 month'));
        $last_month_end = date('Y-m-t', strtotime('-1 month'));
        $last_month_referrals = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $referrals_table WHERE created_at >= %s AND created_at <= %s",
            $last_month_start, $last_month_end . ' 23:59:59'
        ));
        $last_month_completed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $referrals_table WHERE status = 'completed' AND created_at >= %s AND created_at <= %s",
            $last_month_start, $last_month_end . ' 23:59:59'
        ));
        $last_month_conversion = $last_month_referrals > 0 ? ($last_month_completed / $last_month_referrals) * 100 : 0;
        $conversion_change = $conversion_rate - $last_month_conversion;

        return [
            'total_referrals' => intval($total_referrals),
            'new_referrals_this_month' => intval($new_referrals_this_month),
            'total_commissions' => floatval($total_commissions),
            'commissions_this_month' => floatval($commissions_this_month),
            'conversion_rate' => round($conversion_rate, 1),
            'conversion_change' => round($conversion_change, 1)
        ];
    }

    /**
     * Get dashboard statistics for rolling 12-month period (consistent with ROI panels)
     * 
     * Uses intersoccer_coach_commissions table for commissions (not intersoccer_referrals)
     * to match the ROI calculator's definition.
     */
    private function get_dashboard_stats_12mo() {
        global $wpdb;
        $referrals_table = $wpdb->prefix . 'intersoccer_referrals';
        $commissions_table = $wpdb->prefix . 'intersoccer_coach_commissions';
        $points_log = $wpdb->prefix . 'intersoccer_points_log';
        
        $period_start = date('Y-m-d', strtotime('-12 months'));
        $period_end = date('Y-m-d');
        $month_start = date('Y-m-01');

        // Total referrals in 12-month period
        $total_referrals = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $referrals_table WHERE created_at BETWEEN %s AND %s",
            $period_start, $period_end . ' 23:59:59'
        ));

        // Total commissions from intersoccer_coach_commissions (12-month period)
        // This matches the ROI calculator's cost_breakdown query
        $total_commissions = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(commission_amount), 0) FROM $commissions_table WHERE created_at BETWEEN %s AND %s",
            $period_start, $period_end . ' 23:59:59'
        ));

        // Commissions this month (from same table for consistency)
        $commissions_this_month = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(commission_amount), 0) FROM $commissions_table WHERE created_at >= %s",
            $month_start
        ));

        // Conversion rate in 12-month period
        $completed_referrals = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $referrals_table WHERE status = 'completed' AND created_at BETWEEN %s AND %s",
            $period_start, $period_end . ' 23:59:59'
        ));
        $conversion_rate = $total_referrals > 0 ? ($completed_referrals / $total_referrals) * 100 : 0;

        // Points earned in 12-month period
        $total_credits_earned = (float) $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(points_amount), 0)
            FROM {$points_log}
            WHERE points_amount > 0
              AND transaction_type NOT IN ('gift_sent', 'gift_received', 'gift_returned')
              AND created_at BETWEEN %s AND %s
        ", $period_start, $period_end . ' 23:59:59'));

        // Points redeemed in 12-month period
        $total_credits_used = (float) $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(ABS(SUM(points_amount)), 0)
            FROM {$points_log}
            WHERE points_amount < 0
              AND transaction_type NOT IN ('gift_sent', 'gift_received', 'gift_returned')
              AND created_at BETWEEN %s AND %s
        ", $period_start, $period_end . ' 23:59:59'));

        return [
            'total_referrals' => $total_referrals,
            'total_commissions' => $total_commissions,
            'commissions_this_month' => $commissions_this_month,
            'conversion_rate' => round($conversion_rate, 1),
            'total_credits_earned' => $total_credits_earned,
            'total_credits_used' => $total_credits_used,
        ];
    }

    /**
     * Get customer credit statistics for dashboard
     */
    private function get_customer_credit_stats() {
        global $wpdb;
        $points_log = $wpdb->prefix . 'intersoccer_points_log';

        // Total loyalty points earned by customers all time (from points ledger)
        $total_credits_earned = $wpdb->get_var("
            SELECT COALESCE(SUM(points_amount), 0)
            FROM {$points_log}
            WHERE points_amount > 0
              AND transaction_type NOT IN ('gift_sent', 'gift_received', 'gift_returned')
        ");

        // Loyalty points earned this month
        $this_month_start = date('Y-m-01');
        $this_month_end   = date('Y-m-t');
        $credits_earned_this_month = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(points_amount), 0)
            FROM {$points_log}
            WHERE points_amount > 0
              AND transaction_type NOT IN ('gift_sent', 'gift_received', 'gift_returned')
              AND created_at BETWEEN %s AND %s
        ", $this_month_start, $this_month_end . ' 23:59:59'));

        // Total points redeemed all time (negative ledger entries = spend)
        $total_credits_used = $wpdb->get_var("
            SELECT COALESCE(ABS(SUM(points_amount)), 0)
            FROM {$points_log}
            WHERE points_amount < 0
              AND transaction_type NOT IN ('gift_sent', 'gift_received', 'gift_returned')
        ");

        // Redemption rate
        $redemption_rate = $total_credits_earned > 0 ? ($total_credits_used / $total_credits_earned) * 100 : 0;

        // Active referral credits outstanding (current balance in user meta — the true liability)
        // Canonical source: intersoccer_points_balance (not legacy intersoccer_customer_credits)
        // Per issue #24 AC §2: Points balance must match intersoccer_points_balance
        $active_credits = $wpdb->get_var("
            SELECT COALESCE(SUM(meta_value), 0)
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'intersoccer_points_balance'
              AND meta_value > 0
        ");

        // Net points balance as of start of last month — for month-over-month liability change
        $last_month_start = date('Y-m-01', strtotime('last month'));
        $active_credits_last_month = $wpdb->get_var($wpdb->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN points_amount > 0 THEN points_amount ELSE 0 END), 0)
                - COALESCE(ABS(SUM(CASE WHEN points_amount < 0 THEN points_amount ELSE 0 END)), 0)
            FROM {$points_log}
            WHERE created_at < %s
        ", $last_month_start));

        $liability_change = $active_credits - (float) $active_credits_last_month;

        return [
            'total_credits_earned'      => (float) $total_credits_earned,
            'credits_earned_this_month' => (float) $credits_earned_this_month,
            'total_credits_used'        => (float) $total_credits_used,
            'redemption_rate'           => round($redemption_rate, 1),
            'active_credits'            => (float) $active_credits,
            'liability_change'          => (float) $liability_change,
        ];
    }

    /**
     * Get recent referrals for dashboard
     */
    private function get_recent_referrals($limit = 10) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT r.*,
                   c.display_name as coach_name,
                   u.display_name as customer_name,
                   r.created_at as referral_date
            FROM {$wpdb->prefix}intersoccer_referrals r
            LEFT JOIN {$wpdb->users} c ON r.coach_id = c.ID
            LEFT JOIN {$wpdb->users} u ON r.customer_id = u.ID
            ORDER BY r.created_at DESC
            LIMIT %d
        ", $limit));
    }

    /**
     * Get top performing coaches
     */
    private function get_top_coaches($limit = 5) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT
                c.ID as coach_id,
                c.display_name,
                COUNT(DISTINCT r.id) as referral_count,
                COALESCE(SUM(rc.credit_amount), 0) as total_commission,
                COALESCE(SUM(CASE WHEN rc.credit_type = 'partnership' THEN rc.credit_amount ELSE 0 END), 0) as partnership_earnings
            FROM {$wpdb->users} c
            INNER JOIN {$wpdb->prefix}intersoccer_referrals r ON c.ID = r.coach_id
            LEFT JOIN {$wpdb->prefix}intersoccer_referral_credits rc ON r.id = rc.referral_id
            GROUP BY c.ID, c.display_name
            ORDER BY total_commission DESC
            LIMIT %d
        ", $limit));
    }

    /**
     * Get financial overview data for dashboard.
     *
     * Uses ROI calculator for consistent definitions:
     * - Cost: Points when EARNED (not redeemed) + Coach commissions (12mo)
     * - Utilization: Redemption rate from calculator (12mo, excluding corrections)
     */
    private function get_financial_overview_dashboard() {
        global $wpdb;
        $points_log = $wpdb->prefix . 'intersoccer_points_log';
        
        // Get data from ROI calculator for consistency
        $cost_breakdown = $this->roi_calculator->get_cost_breakdown();
        $liability = $this->roi_calculator->get_points_liability();
        
        // Monthly program cost: points EARNED this month (cost when earned, not redemption)
        // Uses same classifier logic as 12-month cost: excludes corrections and adjustments
        $this_month_start = wp_date('Y-m-01');
        $this_month_end = wp_date('Y-m-t');
        
        // Activity points earned this month (excludes referral, adjustments, and gifts)
        $monthly_activity_points = (float) $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(points_amount), 0)
            FROM {$points_log}
            WHERE points_amount > 0
              AND transaction_type NOT IN (
                  'customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward',
                  'admin_adjustment', 'manual_adjustment', 'admin_balance_set', 'points_refund', 'admin_reset',
                  'gift_sent', 'gift_received', 'gift_returned'
              )
              AND created_at BETWEEN %s AND %s
        ", $this_month_start, $this_month_end . ' 23:59:59'));
        
        // Referral points this month
        $monthly_referral_points = (float) $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(points_amount), 0)
            FROM {$points_log}
            WHERE points_amount > 0
              AND transaction_type IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')
              AND created_at BETWEEN %s AND %s
        ", $this_month_start, $this_month_end . ' 23:59:59'));
        
        // Classify monthly adjustments using the same logic as 12-month (goodwill vs correction)
        $monthly_adj_result = $this->roi_calculator->classify_adjustments([
            'start' => $this_month_start,
            'end' => $this_month_end
        ]);
        $monthly_goodwill = $monthly_adj_result['goodwill_chf'];
        
        // Monthly points earned = activity + referral + goodwill (corrections excluded)
        $monthly_points_earned = $monthly_activity_points + $monthly_referral_points + $monthly_goodwill;
        
        // Monthly commissions
        $commissions_table = $wpdb->prefix . 'intersoccer_coach_commissions';
        $monthly_commissions = (float) $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(commission_amount), 0)
            FROM {$commissions_table}
            WHERE created_at BETWEEN %s AND %s
        ", $this_month_start, $this_month_end . ' 23:59:59'));
        
        // Monthly first-order discount (same criteria as 12-month)
        $monthly_first_order_discount = $this->roi_calculator->get_first_order_discount_cost([
            'start' => $this_month_start,
            'end' => $this_month_end
        ]);
        
        $monthly_program_cost = $monthly_points_earned + $monthly_commissions + $monthly_first_order_discount;
        
        // Use redemption rate from calculator (12mo, consistent definition)
        $credit_utilization_rate = $liability['redemption_rate'];
        
        // Average points per customer: use current balance holders (more meaningful)
        $customers_with_balance = (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT user_id)
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'intersoccer_points_balance'
              AND meta_value > 0
        ");
        $avg_credits_per_customer = $customers_with_balance > 0 
            ? $liability['current_chf'] / $customers_with_balance 
            : 0;
        
        // CHF-based ROI calculation
        $roi_data = $this->calculate_chf_roi();
        
        // ROI order scans deferred (time budget): don't show zero/partial costs as real figures.
        $roi_pending = !empty($cost_breakdown['pending']) || $this->roi_calculator->is_roi_widgets_pending();
        
        return [
            'pending' => $roi_pending,
            'monthly_program_cost' => $monthly_program_cost,
            'credit_utilization_rate' => $credit_utilization_rate,
            'avg_credits_per_customer' => round($avg_credits_per_customer, 0),
            'total_program_benefit' => $roi_data['revenue_chf'],
            'total_program_cost' => $cost_breakdown['total_real_cost'],
            'active_credits' => $liability['current_chf'],
            'roi_percentage' => $roi_data['roi_percentage'],
            'roi_display' => $roi_data['roi_display'],
            'has_data' => $roi_data['has_data'],
            'points_earned' => $liability['earned_12mo'],
            'points_redeemed' => $liability['redeemed_12mo'],
        ];
    }

    /**
     * Calculate CHF-based ROI for the loyalty program (HPOS-compatible).
     *
     * Delegates to ROI calculator for consistent cost/revenue definitions:
     * - Cost: Points when EARNED (not when redeemed) + Coach commissions
     * - Revenue: Ex-VAT line subtotals net of refunds
     * - ROI = (Profit - Cost) / Cost × 100
     */
    private function calculate_chf_roi() {
        $cost_breakdown = $this->roi_calculator->get_cost_breakdown();
        $referral_roi = $this->roi_calculator->calculate_referral_roi();
        $loyalty_roi = $this->roi_calculator->calculate_loyalty_roi();
        
        $total_cost = $cost_breakdown['total_real_cost'];
        $total_revenue = $referral_roi['revenue_ex_vat'] + $loyalty_roi['total_extra_spend'];
        
        // Determine if we have enough data
        $has_data = ($total_cost > 0 || $total_revenue > 0);
        
        // Calculate ROI (requires margin)
        $roi_range = $this->roi_calculator->get_roi_range_display();
        
        return [
            'cost_chf' => $total_cost,
            'revenue_chf' => $total_revenue,
            'points_discount_chf' => $cost_breakdown['loyalty_cost_chf'],
            'coach_commission_chf' => $cost_breakdown['commissions_chf'],
            'points_orders_revenue' => $loyalty_roi['total_extra_spend'],
            'referral_orders_revenue' => $referral_roi['revenue_ex_vat'],
            'roi_percentage' => $roi_range['min'] ?? null,
            'roi_display' => $roi_range['display'],
            'has_data' => $has_data,
        ];
    }

    /**
     * Get all dashboard chart data for JavaScript
     */
    private function get_dashboard_chart_data() {
        return [
            'referral_trends' => $this->get_referral_trends_data(),
            'financial_performance' => $this->get_credit_trends_data(),
            'coach_performance' => $this->get_top_coaches_chart_data(),
            'credit_distribution' => $this->get_program_distribution_data(),
            'redemption_activity' => $this->get_redemption_activity_data(),
            'program_roi' => $this->get_program_roi_data(),
            'new_families_by_month' => $this->roi_calculator->get_referral_families_by_month(),
            'points_activity' => $this->roi_calculator->get_points_activity(),
            'liability_trend' => $this->roi_calculator->get_points_liability()['trend'],
        ];
    }

    /**
     * Get referral trends data for charts (12 months)
     */
    private function get_referral_trends_data() {
        global $wpdb;

        $data = [];
        $labels = [];
        $go_live_date = get_option('intersoccer_points_golive_date', '');
        $points_table = $wpdb->prefix . 'intersoccer_points_log';

        // Get last 12 months
        for ($i = 11; $i >= 0; $i--) {
            $date = date('Y-m-01', strtotime("-$i months"));
            $end_date = date('Y-m-t', strtotime("-$i months"));
            $label = date('M Y', strtotime("-$i months"));

            $labels[] = $label;

            // Referrals count (exclude demo/test rows so chart reflects real program only)
            $referrals = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->prefix}intersoccer_referrals
                WHERE created_at BETWEEN %s AND %s
                AND (referral_code IS NULL OR referral_code NOT LIKE %s)
            ", $date, $end_date, 'DEMO%'));

            // Completed referrals (same exclusion)
            $completed = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->prefix}intersoccer_referrals
                WHERE status = 'completed' AND created_at BETWEEN %s AND %s
                AND (referral_code IS NULL OR referral_code NOT LIKE %s)
            ", $date, $end_date, 'DEMO%'));

            // Points earned from purchases this month (respects Points Go-Live Date)
            $points_sql = "SELECT COALESCE(SUM(points_amount), 0) FROM {$points_table}
                WHERE transaction_type = 'order_purchase' AND created_at BETWEEN %s AND %s";
            $points_params = [$date, $end_date];
            if (!empty($go_live_date)) {
                $points_sql .= " AND created_at >= %s";
                $points_params[] = $go_live_date;
            }
            $points_earned = $wpdb->get_var($wpdb->prepare($points_sql, $points_params));

            $data[] = [
                'referrals' => (int)$referrals,
                'completed' => (int)$completed,
                'points_earned' => (float)$points_earned
            ];
        }

        return [
            'labels' => $labels,
            'referrals' => array_column($data, 'referrals'),
            'completed' => array_column($data, 'completed'),
            'points_earned' => array_column($data, 'points_earned')
        ];
    }

    /**
     * Get credit trends data for charts (12 months).
     * Uses intersoccer_points_log as the single source of truth for earned/redeemed points.
     */
    private function get_credit_trends_data() {
        global $wpdb;
        $points_log = $wpdb->prefix . 'intersoccer_points_log';

        $data = [];
        $labels = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = date('Y-m-01', strtotime("-$i months"));
            $end_date = date('Y-m-t', strtotime("-$i months"));
            $label = date('M Y', strtotime("-$i months"));

            $labels[] = $label;

            // Points earned this month (positive ledger entries, excluding gifts)
            $earned = $wpdb->get_var($wpdb->prepare("
                SELECT COALESCE(SUM(points_amount), 0)
                FROM {$points_log}
                WHERE points_amount > 0
                  AND transaction_type NOT IN ('gift_sent', 'gift_received', 'gift_returned')
                  AND created_at BETWEEN %s AND %s
            ", $date, $end_date . ' 23:59:59'));

            // Points redeemed this month (negative ledger entries, excluding gifts)
            $redeemed = $wpdb->get_var($wpdb->prepare("
                SELECT COALESCE(ABS(SUM(points_amount)), 0)
                FROM {$points_log}
                WHERE points_amount < 0
                  AND transaction_type NOT IN ('gift_sent', 'gift_received', 'gift_returned')
                  AND created_at BETWEEN %s AND %s
            ", $date, $end_date . ' 23:59:59'));

            // Running net points balance as of end of this month
            $active = $wpdb->get_var($wpdb->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN points_amount > 0 THEN points_amount ELSE 0 END), 0)
                    - COALESCE(ABS(SUM(CASE WHEN points_amount < 0 THEN points_amount ELSE 0 END)), 0)
                FROM {$points_log}
                WHERE created_at <= %s
            ", $end_date . ' 23:59:59'));

            $data[] = [
                'earned' => (float) $earned,
                'redeemed' => (float) $redeemed,
                'active' => max(0, (float) $active)
            ];
        }

        return [
            'labels' => $labels,
            'revenue' => array_column($data, 'earned'),
            'costs' => array_column($data, 'redeemed'),
            'profit' => array_map(function($item) {
                return $item['earned'] - $item['redeemed'];
            }, $data)
        ];
    }

    /**
     * Get top coaches data formatted for charts
     */
    private function get_top_coaches_chart_data() {
        $coaches = $this->get_top_coaches(5);

        $labels = [];
        $referrals = [];
        $commissions = [];

        foreach ($coaches as $coach) {
            $labels[] = $coach->display_name;
            $referrals[] = (int)$coach->referral_count;
            $commissions[] = (float)$coach->total_commission;
        }

        return [
            'labels' => $labels,
            'referrals' => $referrals,
            'commissions' => $commissions
        ];
    }

    /**
     * Get program distribution data for pie chart.
     * Shows points earned by source (purchases, admin, referrals) vs points redeemed.
     */
    private function get_program_distribution_data() {
        global $wpdb;
        $points_log = $wpdb->prefix . 'intersoccer_points_log';

        // Points from order purchases
        $purchase_points = $wpdb->get_var("
            SELECT COALESCE(SUM(points_amount), 0)
            FROM {$points_log}
            WHERE points_amount > 0
              AND transaction_type = 'order_purchase'
        ");

        // Points from admin/manual adjustments
        $admin_points = $wpdb->get_var("
            SELECT COALESCE(SUM(points_amount), 0)
            FROM {$points_log}
            WHERE points_amount > 0
              AND transaction_type IN ('admin_adjustment', 'manual_adjustment', 'admin_balance_set')
        ");

        // Points from referrals and bonuses
        $referral_points = $wpdb->get_var("
            SELECT COALESCE(SUM(points_amount), 0)
            FROM {$points_log}
            WHERE points_amount > 0
              AND transaction_type IN ('customer_referral_reward', 'new_customer_bonus', 'coach_referral_reward')
        ");

        // Points redeemed
        $redeemed_points = $wpdb->get_var("
            SELECT COALESCE(ABS(SUM(points_amount)), 0)
            FROM {$points_log}
            WHERE points_amount < 0
              AND transaction_type NOT IN ('gift_sent', 'gift_received', 'gift_returned')
        ");

        $total = $purchase_points + $admin_points + $referral_points + $redeemed_points;

        if ($total == 0) {
            return [
                'labels' => ['No Data'],
                'values' => [1],
                'colors' => ['#e0e0e0']
            ];
        }

        $labels = [];
        $values = [];
        $colors = [];

        if ($purchase_points > 0) {
            $labels[] = 'Purchase Points';
            $values[] = (float) $purchase_points;
            $colors[] = '#27ae60';
        }
        if ($admin_points > 0) {
            $labels[] = 'Admin Adjustments';
            $values[] = (float) $admin_points;
            $colors[] = '#3498db';
        }
        if ($referral_points > 0) {
            $labels[] = 'Referral Rewards';
            $values[] = (float) $referral_points;
            $colors[] = '#9b59b6';
        }
        if ($redeemed_points > 0) {
            $labels[] = 'Points Redeemed';
            $values[] = (float) $redeemed_points;
            $colors[] = '#e74c3c';
        }

        if (empty($values)) {
            return [
                'labels' => ['No Data'],
                'values' => [1],
                'colors' => ['#e0e0e0']
            ];
        }

        return [
            'labels' => $labels,
            'values' => $values,
            'colors' => $colors
        ];
    }

    /**
     * Get redemption activity data for charts (6 months)
     * Uses intersoccer_points_log as the single source of truth for earned/redeemed points.
     */
    private function get_redemption_activity_data() {
        global $wpdb;
        $points_log = $wpdb->prefix . 'intersoccer_points_log';

        $data   = [];
        $labels = [];

        for ($i = 5; $i >= 0; $i--) {
            $date     = date('Y-m-01', strtotime("-$i months"));
            $end_date = date('Y-m-t', strtotime("-$i months"));
            $label    = date('M Y', strtotime("-$i months"));

            $labels[] = $label;

            // Points earned from purchases this month
            $earned = $wpdb->get_var($wpdb->prepare("
                SELECT COALESCE(SUM(points_amount), 0)
                FROM {$points_log}
                WHERE points_amount > 0
                  AND transaction_type NOT IN ('gift_sent', 'gift_received', 'gift_returned')
                  AND created_at BETWEEN %s AND %s
            ", $date, $end_date . ' 23:59:59'));

            // Points redeemed this month
            $redeemed = $wpdb->get_var($wpdb->prepare("
                SELECT COALESCE(ABS(SUM(points_amount)), 0)
                FROM {$points_log}
                WHERE points_amount < 0
                  AND transaction_type NOT IN ('gift_sent', 'gift_received', 'gift_returned')
                  AND created_at BETWEEN %s AND %s
            ", $date, $end_date . ' 23:59:59'));

            $data[] = [
                'earned'   => (float) $earned,
                'redeemed' => (float) $redeemed,
            ];
        }

        return [
            'labels'   => $labels,
            'earned'   => array_column($data, 'earned'),
            'redeemed' => array_column($data, 'redeemed'),
        ];
    }

    /**
     * Get program ROI data for dashboard
     */
    private function get_program_roi_data() {
        $roi_data = $this->calculate_chf_roi();
        $financial_overview = $this->get_financial_overview_dashboard();
        $active_credits = $financial_overview['active_credits'] ?? 0;

        $roi_percentage = $roi_data['roi_percentage'];
        if ($roi_percentage === null) {
            $roi_status = 'no_data';
        } elseif ($roi_percentage >= 0) {
            $roi_status = 'profitable';
        } else {
            $roi_status = 'loss';
        }

        return [
            'total_program_revenue' => $roi_data['revenue_chf'],
            'total_program_cost' => $roi_data['cost_chf'],
            'points_discount_chf' => $roi_data['points_discount_chf'],
            'coach_commission_chf' => $roi_data['coach_commission_chf'],
            'active_credits' => $active_credits,
            'net_roi_percentage' => $roi_percentage,
            'roi_display' => $roi_data['roi_display'],
            'roi_status' => $roi_status,
            'has_data' => $roi_data['has_data'],
        ];
    }

    /**
     * Get coach tier badge based on referral count
     */
    private function get_coach_tier_badge($referral_count) {
        if ($referral_count >= 20) {
            return '<span class="tier-badge platinum">Platinum</span>';
        } elseif ($referral_count >= 10) {
            return '<span class="tier-badge gold">Gold</span>';
        } elseif ($referral_count >= 5) {
            return '<span class="tier-badge silver">Silver</span>';
        } else {
            return '<span class="tier-badge bronze">Bronze</span>';
        }
    }

    /**
     * Get recent activities for dashboard
     */
    private function get_recent_activities($limit = 10) {
        global $wpdb;

        $activities = [];

        // Recent referrals
        $referrals = $wpdb->get_results($wpdb->prepare("
            SELECT r.created_at, u.display_name as customer_name, c.display_name as coach_name,
                   rc.credit_amount as amount
            FROM {$wpdb->prefix}intersoccer_referrals r
            LEFT JOIN {$wpdb->users} u ON r.customer_id = u.ID
            LEFT JOIN {$wpdb->users} c ON r.coach_id = c.ID
            LEFT JOIN {$wpdb->prefix}intersoccer_referral_credits rc ON r.id = rc.referral_id
            ORDER BY r.created_at DESC
            LIMIT %d
        ", $limit));

        foreach ($referrals as $referral) {
            $activities[] = (object) [
                'type' => 'referral',
                'icon' => 'plus',
                'message' => 'New referral: ' . esc_html($referral->customer_name) . ' joined via ' . esc_html($referral->coach_name),
                'created_at' => $referral->created_at,
                'amount' => $referral->amount,
                'amount_prefix' => '+'
            ];
        }

        // Recent redemptions — points_log negative entries are the only reliably populated source
        $redemptions = $wpdb->get_results($wpdb->prepare("
            SELECT created_at, ABS(points_amount) AS amount, customer_id AS user_id
            FROM {$wpdb->prefix}intersoccer_points_log
            WHERE points_amount < 0
            ORDER BY created_at DESC
            LIMIT %d
        ", $limit));

        foreach ($redemptions as $redemption) {
            $user = get_user_by('ID', $redemption->user_id);
            if (!$user) {
                continue;
            }
            $activities[] = (object) [
                'type'          => 'redemption',
                'icon'          => 'cart',
                'message'       => esc_html($user->display_name) . ' redeemed points',
                'created_at'    => $redemption->created_at,
                'amount'        => $redemption->amount,
                'amount_prefix' => '-',
            ];
        }

        // Sort by date and limit
        usort($activities, function($a, $b) {
            return strtotime($b->created_at) - strtotime($a->created_at);
        });

        return array_slice($activities, 0, $limit);
    }

}