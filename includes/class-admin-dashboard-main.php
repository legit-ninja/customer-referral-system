<?php
// includes/class-admin-dashboard-main.php

class InterSoccer_Admin_Dashboard_Main {

    public function __construct() {
        // Add AJAX handlers for demo data
        add_action('wp_ajax_intersoccer_populate_demo_data', [$this, 'populate_demo_data']);
        add_action('wp_ajax_intersoccer_clear_demo_data', [$this, 'clear_demo_data']);
    }

    public function render_main_dashboard() {
        $stats = $this->get_dashboard_stats();
        $credit_stats = $this->get_customer_credit_stats();
        $recent_referrals = $this->get_recent_referrals(10);
        $top_coaches = $this->get_top_coaches(5);
        $financial_overview = $this->get_financial_overview_dashboard();
        $chart_data = $this->get_dashboard_chart_data();

        ?>
        <div class="wrap intersoccer-admin">
            <h1 class="wp-heading-inline">InterSoccer Referral Dashboard</h1>

            <div class="intersoccer-demo-actions">
                <button id="populate-demo-data" class="button button-secondary">
                    <span class="dashicons dashicons-database-add"></span>
                    Populate Demo Data
                </button>
                <button id="clear-demo-data" class="button button-secondary">
                    <span class="dashicons dashicons-trash"></span>
                    Clear Demo Data
                </button>
            </div>

            <!-- Enhanced Stats Cards with Credit Data -->
            <div class="intersoccer-stats-grid">
                <div class="stat-card referrals-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-businessman"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_referrals']); ?></h3>
                        <p>Total Referrals</p>
                        <span class="stat-change positive">+<?php echo $stats['new_referrals_this_month']; ?> this month</span>
                    </div>
                </div>

                <div class="stat-card commissions-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_commissions'], 0); ?> CHF</h3>
                        <p>Coach Commissions</p>
                        <span class="stat-change positive">+<?php echo number_format($stats['commissions_this_month'], 0); ?> CHF this month</span>
                    </div>
                </div>

                <div class="stat-card credits-earned-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-awards"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($credit_stats['total_credits_earned'], 0); ?> CHF</h3>
                        <p>Credits Earned by Customers</p>
                        <span class="stat-change positive">+<?php echo number_format($credit_stats['credits_earned_this_month'], 0); ?> CHF this month</span>
                    </div>
                </div>

                <div class="stat-card credits-used-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-cart"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($credit_stats['total_credits_used'], 0); ?> CHF</h3>
                        <p>Credits Redeemed</p>
                        <span class="stat-change neutral"><?php echo number_format($credit_stats['redemption_rate'], 1); ?>% redemption rate</span>
                    </div>
                </div>

                <div class="stat-card active-credits-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-vault"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($credit_stats['active_credits'], 0); ?> CHF</h3>
                        <p>Active Credits (Liability)</p>
                        <span class="stat-change <?php echo $credit_stats['liability_change'] >= 0 ? 'neutral' : 'positive'; ?>">
                            <?php echo ($credit_stats['liability_change'] >= 0 ? '+' : '') . number_format($credit_stats['liability_change'], 0); ?> CHF vs last month
                        </span>
                    </div>
                </div>

                <div class="stat-card conversion-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['conversion_rate'], 1); ?>%</h3>
                        <p>Referral Conversion Rate</p>
                        <span class="stat-change <?php echo $stats['conversion_change'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo ($stats['conversion_change'] >= 0 ? '+' : '') . number_format($stats['conversion_change'], 1); ?>% vs last month
                        </span>
                    </div>
                </div>
            </div>

            <!-- Financial Health Summary -->
            <div class="financial-health-section">
                <h2>Financial Health Overview</h2>
                <div class="financial-metrics">
                    <div class="financial-metric">
                        <span class="metric-label">Program Cost (Monthly)</span>
                        <span class="metric-value"><?php echo number_format($financial_overview['monthly_program_cost'], 0); ?> CHF</span>
                    </div>
                    <div class="financial-metric">
                        <span class="metric-label">Credit Utilization Rate</span>
                        <span class="metric-value"><?php echo number_format($financial_overview['credit_utilization_rate'], 1); ?>%</span>
                    </div>
                    <div class="financial-metric">
                        <span class="metric-label">Avg Credits per Customer</span>
                        <span class="metric-value"><?php echo number_format($financial_overview['avg_credits_per_customer'], 0); ?> CHF</span>
                    </div>
                    <div class="financial-metric">
                        <span class="metric-label">ROI (Revenue vs Cost)</span>
                        <span class="metric-value <?php echo $financial_overview['roi_percentage'] > 0 ? 'positive' : 'negative'; ?>">
                            <?php echo number_format($financial_overview['roi_percentage'], 1); ?>%
                        </span>
                    </div>
                </div>
            </div>

            <div class="intersoccer-dashboard-grid">
                <!-- Referral Performance Trends -->
                <div class="dashboard-widget chart-widget">
                    <h2>Referral Performance Trends (12 Months)</h2>
                    <canvas id="referralTrendsChart" width="400" height="250"></canvas>
                    <div class="chart-legend">
                        <span class="legend-item"><span class="color-box referrals"></span>Referrals</span>
                        <span class="legend-item"><span class="color-box completed"></span>Completed</span>
                        <span class="legend-item"><span class="color-box conversion"></span>Conversion Rate (%)</span>
                    </div>
                </div>

                <!-- Financial Performance Chart -->
                <div class="dashboard-widget chart-widget">
                    <h2>Financial Performance (12 Months)</h2>
                    <canvas id="financialChart" width="400" height="250"></canvas>
                    <div class="chart-legend">
                        <span class="legend-item"><span class="color-box revenue"></span>Commission Revenue</span>
                        <span class="legend-item"><span class="color-box costs"></span>Redemption Costs</span>
                        <span class="legend-item"><span class="color-box profit"></span>Net Profit/Loss</span>
                    </div>
                </div>

                <!-- Coach Performance Comparison -->
                <div class="dashboard-widget chart-widget">
                    <h2>Top Coach Performance</h2>
                    <canvas id="coachPerformanceChart" width="400" height="250"></canvas>
                </div>

                <!-- Customer Credit Distribution -->
                <div class="dashboard-widget chart-widget">
                    <h2>Customer Credit Distribution</h2>
                    <canvas id="creditDistributionChart" width="400" height="250"></canvas>
                </div>

                <!-- Program ROI Summary -->
                <div class="dashboard-widget">
                    <h2>Program ROI Summary</h2>
                    <div class="roi-summary">
                        <div class="roi-metric main-roi">
                            <div class="roi-value <?php echo $chart_data['program_roi']['roi_status']; ?>">
                                <?php echo number_format($chart_data['program_roi']['net_roi_percentage'], 1); ?>%
                            </div>
                            <div class="roi-label">Overall ROI</div>
                        </div>

                        <div class="roi-breakdown">
                            <div class="roi-breakdown-item">
                                <span class="breakdown-label">Revenue Generated:</span>
                                <span class="breakdown-value"><?php echo number_format($chart_data['program_roi']['total_program_benefit'], 0); ?> CHF</span>
                            </div>
                            <div class="roi-breakdown-item">
                                <span class="breakdown-label">Total Costs:</span>
                                <span class="breakdown-value"><?php echo number_format($chart_data['program_roi']['total_program_cost'], 0); ?> CHF</span>
                            </div>
                            <div class="roi-breakdown-item">
                                <span class="breakdown-label">Active Credits:</span>
                                <span class="breakdown-value"><?php echo number_format($chart_data['program_roi']['active_credits'], 0); ?> CHF</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Redemption Activity -->
                <div class="dashboard-widget chart-widget">
                    <h2>Credit Redemption Activity (6 Months)</h2>
                    <canvas id="redemptionActivityChart" width="400" height="200"></canvas>
                </div>

                <!-- Top Coaches -->
                <div class="dashboard-widget">
                    <h2>Top Performing Coaches</h2>
                    <div class="coach-leaderboard">
                        <?php foreach ($top_coaches as $index => $coach): ?>
                        <div class="coach-item">
                            <div class="coach-rank"><?php echo $index + 1; ?></div>
                            <div class="coach-avatar">
                                <?php echo get_avatar($coach->coach_id, 40); ?>
                            </div>
                            <div class="coach-info">
                                <strong><?php echo esc_html($coach->display_name); ?></strong>
                                <span class="coach-stats">
                                    <?php echo $coach->referral_count; ?> referrals |
                                    <?php echo number_format($coach->total_commission, 0); ?> CHF |
                                    <?php echo number_format($coach->partnership_earnings ?? 0, 0); ?> CHF partnerships
                                </span>
                            </div>
                            <div class="coach-badge">
                                <?php echo $this->get_coach_tier_badge($coach->referral_count); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
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
                        <button class="quick-action-btn" id="credit-reconciliation">
                            <span class="dashicons dashicons-admin-settings"></span>
                            Credit Reconciliation
                        </button>
                    </div>
                </div>
            </div>

            <!-- Chart Data for JavaScript -->
            <script type="text/javascript">
            var intersoccerChartData = <?php echo json_encode($chart_data); ?>;
            </script>

            <!-- Enhanced CSS -->
            <style>
            .intersoccer-stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 20px;
                margin-bottom: 30px;
            }

            .stat-card {
                background: white;
                padding: 25px;
                border-radius: 12px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                display: flex;
                align-items: center;
                gap: 15px;
                transition: transform 0.2s ease, box-shadow 0.2s ease;
                border-left: 4px solid #e1e5e9;
            }

            .stat-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            }

            .referrals-card { border-left-color: #3498db; }
            .commissions-card { border-left-color: #27ae60; }
            .credits-earned-card { border-left-color: #f39c12; }
            .credits-used-card { border-left-color: #9b59b6; }
            .active-credits-card { border-left-color: #e74c3c; }
            .conversion-card { border-left-color: #1abc9c; }

            .stat-icon {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 24px;
                color: white;
            }

            .referrals-card .stat-icon { background: #3498db; }
            .commissions-card .stat-icon { background: #27ae60; }
            .credits-earned-card .stat-icon { background: #f39c12; }
            .credits-used-card .stat-icon { background: #9b59b6; }
            .active-credits-card .stat-icon { background: #e74c3c; }
            .conversion-card .stat-icon { background: #1abc9c; }

            .stat-content h3 {
                font-size: 28px;
                margin: 0 0 5px 0;
                font-weight: 700;
                color: #2c3e50;
            }

            .stat-content p {
                margin: 0 0 8px 0;
                color: #7f8c8d;
                font-weight: 500;
            }

            .stat-change {
                font-size: 12px;
                font-weight: 600;
                padding: 2px 8px;
                border-radius: 12px;
            }

            .stat-change.positive { background: #d5f4e6; color: #27ae60; }
            .stat-change.negative { background: #fadbd8; color: #e74c3c; }
            .stat-change.neutral { background: #fef9e7; color: #f39c12; }

            .financial-health-section {
                background: white;
                padding: 25px;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                margin-bottom: 30px;
            }

            .financial-health-section h2 {
                margin: 0 0 20px 0;
                color: #2c3e50;
            }

            .financial-metrics {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
            }

            .financial-metric {
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 15px;
                border: 2px solid #ecf0f1;
                border-radius: 8px;
            }

            .metric-label {
                font-size: 12px;
                color: #7f8c8d;
                text-transform: uppercase;
                margin-bottom: 5px;
            }

            .metric-value {
                font-size: 20px;
                font-weight: 700;
                color: #2c3e50;
            }

            .metric-value.positive { color: #27ae60; }
            .metric-value.negative { color: #e74c3c; }

            .intersoccer-dashboard-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
                gap: 20px;
                margin-bottom: 20px;
            }

            .dashboard-widget {
                background: white;
                padding: 20px;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }

            .dashboard-widget h2 {
                margin: 0 0 15px 0;
                color: #2c3e50;
                font-size: 18px;
                font-weight: 600;
            }

            .chart-widget canvas {
                max-width: 100%;
                height: auto !important;
            }

            .chart-legend {
                display: flex;
                justify-content: center;
                gap: 20px;
                margin-top: 15px;
                flex-wrap: wrap;
            }

            .legend-item {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 12px;
            }

            .color-box {
                width: 12px;
                height: 12px;
                border-radius: 2px;
            }

            .color-box.referrals { background: #3498db; }
            .color-box.completed { background: #27ae60; }
            .color-box.conversion { background: #e74c3c; }
            .color-box.revenue { background: #f39c12; }
            .color-box.costs { background: #9b59b6; }
            .color-box.profit { background: #1abc9c; }

            .coach-leaderboard {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .coach-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px;
                background: #f8f9fa;
                border-radius: 8px;
            }

            .coach-rank {
                width: 30px;
                height: 30px;
                border-radius: 50%;
                background: #007cba;
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: 700;
                font-size: 14px;
            }

            .coach-avatar img {
                border-radius: 50%;
            }

            .coach-info strong {
                display: block;
                color: #2c3e50;
                margin-bottom: 2px;
            }

            .coach-stats {
                font-size: 12px;
                color: #7f8c8d;
            }

            .recent-activity {
                max-height: 350px;
                overflow-y: auto;
            }

            .activity-item {
                display: flex;
                align-items: center;
                gap: 15px;
                padding: 12px 0;
                border-bottom: 1px solid #f1f3f4;
            }

            .activity-item:last-child { border-bottom: none; }

            .activity-icon {
                width: 35px;
                height: 35px;
                border-radius: 50%;
                background: #f8f9fa;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #007cba;
            }

            .activity-content p {
                margin: 0;
                font-size: 14px;
                color: #2c3e50;
            }

            .activity-time {
                font-size: 12px;
                color: #7f8c8d;
            }

            .activity-amount {
                margin-left: auto;
                font-weight: 600;
                color: #27ae60;
            }

            .quick-actions {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 10px;
            }

            .quick-action-btn {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 12px 16px;
                background: #f8f9fa;
                border: 1px solid #e9ecef;
                border-radius: 8px;
                text-decoration: none;
                color: #495057;
                font-weight: 500;
                transition: all 0.2s ease;
            }

            .quick-action-btn:hover {
                background: #007cba;
                color: white;
                border-color: #007cba;
                transform: translateY(-1px);
            }

            .roi-summary {
                text-align: center;
            }

            .roi-metric.main-roi {
                margin-bottom: 20px;
            }

            .roi-value {
                font-size: 48px;
                font-weight: 700;
                margin-bottom: 5px;
            }

            .roi-value.profitable { color: #27ae60; }
            .roi-value.loss { color: #e74c3c; }

            .roi-label {
                font-size: 16px;
                color: #7f8c8d;
                text-transform: uppercase;
                letter-spacing: 1px;
            }

            .roi-breakdown {
                display: grid;
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .roi-breakdown-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
                border-bottom: 1px solid #f1f3f4;
            }

            .roi-breakdown-item:last-child {
                border-bottom: none;
            }

            .breakdown-label {
                color: #7f8c8d;
                font-size: 14px;
            }

            .breakdown-value {
                font-weight: 600;
                color: #2c3e50;
                font-size: 14px;
            }

            @media (max-width: 768px) {
                .intersoccer-stats-grid {
                    grid-template-columns: 1fr;
                }

                .intersoccer-dashboard-grid {
                    grid-template-columns: 1fr;
                }

                .financial-metrics {
                    grid-template-columns: 1fr;
                }

                .quick-actions {
                    grid-template-columns: 1fr;
                }

                .chart-legend {
                    justify-content: flex-start;
                }
            }
            </style>
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
     * Get customer credit statistics for dashboard
     */
    private function get_customer_credit_stats() {
        global $wpdb;

        // Get total credits earned all time
        $total_credits_earned = $wpdb->get_var("
            SELECT COALESCE(SUM(credit_amount), 0)
            FROM {$wpdb->prefix}intersoccer_referral_credits
        ");

        // Get credits earned this month
        $this_month_start = date('Y-m-01');
        $this_month_end = date('Y-m-t');
        $credits_earned_this_month = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(credit_amount), 0)
            FROM {$wpdb->prefix}intersoccer_referral_credits
            WHERE created_at BETWEEN %s AND %s
        ", $this_month_start, $this_month_end));

        // Get total credits redeemed all time
        $total_credits_used = $wpdb->get_var("
            SELECT COALESCE(SUM(credit_amount), 0)
            FROM {$wpdb->prefix}intersoccer_credit_redemptions
        ");

        // Calculate redemption rate
        $redemption_rate = $total_credits_earned > 0 ? ($total_credits_used / $total_credits_earned) * 100 : 0;

        // Get active credits (current balance across all customers)
        $active_credits = $wpdb->get_var("
            SELECT COALESCE(SUM(meta_value), 0)
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'intersoccer_customer_credits'
            AND meta_value > 0
        ");

        // Get active credits from last month for comparison
        $last_month_start = date('Y-m-01', strtotime('last month'));
        $last_month_end = date('Y-m-t', strtotime('last month'));
        $active_credits_last_month = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(credit_amount), 0) - COALESCE(SUM(redeemed_amount), 0) as active_credits
            FROM (
                SELECT
                    COALESCE(SUM(rc.credit_amount), 0) as credit_amount,
                    0 as redeemed_amount
                FROM {$wpdb->prefix}intersoccer_referral_credits rc
                WHERE rc.created_at < %s
                UNION ALL
                SELECT
                    0 as credit_amount,
                    COALESCE(SUM(cr.credit_amount), 0) as redeemed_amount
                FROM {$wpdb->prefix}intersoccer_credit_redemptions cr
                WHERE cr.created_at < %s
            ) as combined
        ", $last_month_start, $last_month_start));

        $liability_change = $active_credits - $active_credits_last_month;

        return [
            'total_credits_earned' => (float)$total_credits_earned,
            'credits_earned_this_month' => (float)$credits_earned_this_month,
            'total_credits_used' => (float)$total_credits_used,
            'redemption_rate' => round($redemption_rate, 1),
            'active_credits' => (float)$active_credits,
            'liability_change' => (float)$liability_change
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
                COUNT(r.id) as referral_count,
                COALESCE(SUM(rc.credit_amount), 0) as total_commission,
                COALESCE(SUM(rc.credit_amount) * 0.1, 0) as partnership_earnings
            FROM {$wpdb->users} c
            LEFT JOIN {$wpdb->prefix}intersoccer_referrals r ON c.ID = r.coach_id
            LEFT JOIN {$wpdb->prefix}intersoccer_referral_credits rc ON r.id = rc.referral_id
            WHERE c.ID IN (
                SELECT DISTINCT coach_id
                FROM {$wpdb->prefix}intersoccer_referrals
                WHERE coach_id IS NOT NULL
            )
            GROUP BY c.ID, c.display_name
            ORDER BY total_commission DESC
            LIMIT %d
        ", $limit));
    }

    /**
     * Get financial overview data for dashboard
     */
    private function get_financial_overview_dashboard() {
        global $wpdb;

        // Monthly program cost (commissions paid this month)
        $this_month_start = date('Y-m-01');
        $this_month_end = date('Y-m-t');
        $monthly_program_cost = $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(credit_amount), 0)
            FROM {$wpdb->prefix}intersoccer_referral_credits
            WHERE created_at BETWEEN %s AND %s
        ", $this_month_start, $this_month_end));

        // Credit utilization rate (percentage of earned credits that have been redeemed)
        $total_earned = $wpdb->get_var("SELECT COALESCE(SUM(credit_amount), 0) FROM {$wpdb->prefix}intersoccer_referral_credits");
        $total_redeemed = $wpdb->get_var("SELECT COALESCE(SUM(credit_amount), 0) FROM {$wpdb->prefix}intersoccer_credit_redemptions");
        $credit_utilization_rate = $total_earned > 0 ? ($total_redeemed / $total_earned) * 100 : 0;

        // Average credits per customer
        $total_customers_with_credits = $wpdb->get_var("
            SELECT COUNT(DISTINCT customer_id)
            FROM {$wpdb->prefix}intersoccer_referral_credits
        ");
        $avg_credits_per_customer = $total_customers_with_credits > 0 ? $total_earned / $total_customers_with_credits : 0;

        // Total program benefit (commissions generated)
        $total_program_benefit = $total_earned;

        // Total program cost (redemptions paid out)
        $total_program_cost = $total_redeemed;

        // Active credits (current liability)
        $active_credits = $wpdb->get_var("
            SELECT COALESCE(SUM(meta_value), 0)
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'intersoccer_customer_credits'
            AND meta_value > 0
        ");

        // ROI calculation
        $roi_percentage = $total_program_cost > 0 ? (($total_program_benefit - $total_program_cost) / $total_program_cost) * 100 : 0;

        return [
            'monthly_program_cost' => (float)$monthly_program_cost,
            'credit_utilization_rate' => round($credit_utilization_rate, 1),
            'avg_credits_per_customer' => round($avg_credits_per_customer, 0),
            'total_program_benefit' => (float)$total_program_benefit,
            'total_program_cost' => (float)$total_program_cost,
            'active_credits' => (float)$active_credits,
            'roi_percentage' => round($roi_percentage, 1)
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
            'program_roi' => $this->get_program_roi_data()
        ];
    }

    /**
     * Get referral trends data for charts (12 months)
     */
    private function get_referral_trends_data() {
        global $wpdb;

        $data = [];
        $labels = [];

        // Get last 12 months
        for ($i = 11; $i >= 0; $i--) {
            $date = date('Y-m-01', strtotime("-$i months"));
            $end_date = date('Y-m-t', strtotime("-$i months"));
            $label = date('M Y', strtotime("-$i months"));

            $labels[] = $label;

            // Referrals count
            $referrals = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->prefix}intersoccer_referrals
                WHERE created_at BETWEEN %s AND %s
            ", $date, $end_date));

            // Completed referrals
            $completed = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->prefix}intersoccer_referrals
                WHERE status = 'completed' AND created_at BETWEEN %s AND %s
            ", $date, $end_date));

            $data[] = [
                'referrals' => (int)$referrals,
                'completed' => (int)$completed
            ];
        }

        return [
            'labels' => $labels,
            'referrals' => array_column($data, 'referrals'),
            'completed' => array_column($data, 'completed')
        ];
    }

    /**
     * Get credit trends data for charts (12 months)
     */
    private function get_credit_trends_data() {
        global $wpdb;

        $data = [];
        $labels = [];

        // Get last 12 months
        for ($i = 11; $i >= 0; $i--) {
            $date = date('Y-m-01', strtotime("-$i months"));
            $end_date = date('Y-m-t', strtotime("-$i months"));
            $label = date('M Y', strtotime("-$i months"));

            $labels[] = $label;

            // Credits earned
            $earned = $wpdb->get_var($wpdb->prepare("
                SELECT COALESCE(SUM(credit_amount), 0)
                FROM {$wpdb->prefix}intersoccer_referral_credits
                WHERE created_at BETWEEN %s AND %s
            ", $date, $end_date));

            // Credits redeemed
            $redeemed = $wpdb->get_var($wpdb->prepare("
                SELECT COALESCE(SUM(credit_amount), 0)
                FROM {$wpdb->prefix}intersoccer_credit_redemptions
                WHERE created_at BETWEEN %s AND %s
            ", $date, $end_date));

            // Active credits (earned - redeemed up to this month)
            $active = $wpdb->get_var($wpdb->prepare("
                SELECT
                    (SELECT COALESCE(SUM(credit_amount), 0) FROM {$wpdb->prefix}intersoccer_referral_credits WHERE created_at <= %s) -
                    (SELECT COALESCE(SUM(credit_amount), 0) FROM {$wpdb->prefix}intersoccer_credit_redemptions WHERE created_at <= %s)
            ", $end_date, $end_date));

            $data[] = [
                'earned' => (float)$earned,
                'redeemed' => (float)$redeemed,
                'active' => max(0, (float)$active)
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
     * Get program distribution data for pie chart
     */
    private function get_program_distribution_data() {
        global $wpdb;

        // Coach commissions
        $coach_commissions = $wpdb->get_var("
            SELECT COALESCE(SUM(credit_amount), 0)
            FROM {$wpdb->prefix}intersoccer_referral_credits
        ");

        // Customer credits redeemed
        $customer_redemptions = $wpdb->get_var("
            SELECT COALESCE(SUM(credit_amount), 0)
            FROM {$wpdb->prefix}intersoccer_credit_redemptions
        ");

        $total = $coach_commissions + $customer_redemptions;

        if ($total == 0) {
            return [
                'labels' => ['No Data'],
                'values' => [1],
                'colors' => ['#f39c12']
            ];
        }

        return [
            'labels' => ['Coach Commissions', 'Customer Redemptions'],
            'values' => [(float)$coach_commissions, (float)$customer_redemptions],
            'colors' => ['#3498db', '#e74c3c']
        ];
    }

    /**
     * Get redemption activity data for charts
     */
    private function get_redemption_activity_data() {
        global $wpdb;

        $data = [];
        $labels = [];

        // Get last 6 months
        for ($i = 5; $i >= 0; $i--) {
            $date = date('Y-m-01', strtotime("-$i months"));
            $end_date = date('Y-m-t', strtotime("-$i months"));
            $label = date('M Y', strtotime("-$i months"));

            $labels[] = $label;

            // Credits earned
            $earned = $wpdb->get_var($wpdb->prepare("
                SELECT COALESCE(SUM(credit_amount), 0)
                FROM {$wpdb->prefix}intersoccer_referral_credits
                WHERE created_at BETWEEN %s AND %s
            ", $date, $end_date));

            // Credits redeemed
            $redeemed = $wpdb->get_var($wpdb->prepare("
                SELECT COALESCE(SUM(credit_amount), 0)
                FROM {$wpdb->prefix}intersoccer_credit_redemptions
                WHERE created_at BETWEEN %s AND %s
            ", $date, $end_date));

            $data[] = [
                'earned' => (float)$earned,
                'redeemed' => (float)$redeemed
            ];
        }

        return [
            'labels' => $labels,
            'earned' => array_column($data, 'earned'),
            'redeemed' => array_column($data, 'redeemed')
        ];
    }

    /**
     * Get program ROI data for dashboard
     */
    private function get_program_roi_data() {
        $financial_overview = $this->get_financial_overview_dashboard();

        $total_benefit = $financial_overview['total_program_benefit'] ?? 0;
        $total_cost = $financial_overview['total_program_cost'] ?? 0;
        $active_credits = $financial_overview['active_credits'] ?? 0;

        $net_roi = $total_benefit - $total_cost;
        $roi_percentage = $total_cost > 0 ? (($net_roi / $total_cost) * 100) : 0;

        return [
            'total_program_benefit' => $total_benefit,
            'total_program_cost' => $total_cost,
            'active_credits' => $active_credits,
            'net_roi_percentage' => $roi_percentage,
            'roi_status' => $roi_percentage >= 0 ? 'profitable' : 'loss'
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

        // Recent redemptions
        $redemptions = $wpdb->get_results($wpdb->prepare("
            SELECT created_at, credit_amount as amount, customer_id as user_id
            FROM {$wpdb->prefix}intersoccer_credit_redemptions
            ORDER BY created_at DESC
            LIMIT %d
        ", $limit));

        foreach ($redemptions as $redemption) {
            $user = get_user_by('ID', $redemption->user_id);
            $activities[] = (object) [
                'type' => 'redemption',
                'icon' => 'cart',
                'message' => esc_html($user->display_name) . ' redeemed credits',
                'created_at' => $redemption->created_at,
                'amount' => $redemption->amount,
                'amount_prefix' => '-'
            ];
        }

        // Sort by date and limit
        usort($activities, function($a, $b) {
            return strtotime($b->created_at) - strtotime($a->created_at);
        });

        return array_slice($activities, 0, $limit);
    }

    /**
     * Populate demo data for testing
     */
    public function populate_demo_data() {
        // Security checks
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'intersoccer_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        global $wpdb;

        try {
            // Get existing users for demo data
            $admin_user = get_user_by('ID', 1); // Admin user should exist
            $coach_user = get_user_by('email', 'coach@example.com');
            if (!$coach_user) {
                // Create a demo coach user
                $coach_id = wp_create_user('demo_coach', 'password123', 'coach@example.com');
                if (!is_wp_error($coach_id)) {
                    wp_update_user([
                        'ID' => $coach_id,
                        'first_name' => 'Demo',
                        'last_name' => 'Coach',
                        'role' => 'coach'
                    ]);
                    $coach_user = get_user_by('ID', $coach_id);
                }
            }

            $customer_user = get_user_by('email', 'customer@example.com');
            if (!$customer_user) {
                // Create a demo customer user
                $customer_id = wp_create_user('demo_customer', 'password123', 'customer@example.com');
                if (!is_wp_error($customer_id)) {
                    wp_update_user([
                        'ID' => $customer_id,
                        'first_name' => 'Demo',
                        'last_name' => 'Customer'
                    ]);
                    $customer_user = get_user_by('ID', $customer_id);
                }
            }

            // Use actual user IDs or fallback to admin
            $coach_id = $coach_user ? $coach_user->ID : 1;
            $customer_id = $customer_user ? $customer_user->ID : 1;

            // Sample data for referrals
            $sample_referrals = [
                [
                    'coach_id' => $coach_id,
                    'customer_id' => $customer_id,
                    'referrer_id' => $coach_id,
                    'referrer_type' => 'coach',
                    'order_id' => 1001,
                    'commission_amount' => 100.00,
                    'loyalty_bonus' => 10.00,
                    'retention_bonus' => 5.00,
                    'status' => 'completed',
                    'purchase_count' => 1,
                    'referral_code' => 'DEMO001',
                    'conversion_date' => current_time('mysql'),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ]
            ];

            // Insert sample referrals
            foreach ($sample_referrals as $referral) {
                $wpdb->insert($wpdb->prefix . 'intersoccer_referrals', $referral);
            }

            // Sample data for referral credits
            $sample_credits = [
                [
                    'referral_id' => $wpdb->insert_id, // Use the ID of the inserted referral
                    'customer_id' => $customer_id,
                    'coach_id' => $coach_id,
                    'credit_amount' => 50.00,
                    'credit_type' => 'referral',
                    'status' => 'active',
                    'expires_at' => null,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ]
            ];

            // Insert sample credits
            foreach ($sample_credits as $credit) {
                $wpdb->insert($wpdb->prefix . 'intersoccer_referral_credits', $credit);
            }

            // Sample data for credit redemptions
            $sample_redemptions = [
                [
                    'customer_id' => $customer_id,
                    'order_item_id' => null,
                    'credit_amount' => 25.00,
                    'order_total' => 100.00,
                    'discount_applied' => 25.00,
                    'created_at' => current_time('mysql')
                ]
            ];

            // Insert sample redemptions
            $redemption_ids = [];
            foreach ($sample_redemptions as $redemption) {
                $wpdb->insert($wpdb->prefix . 'intersoccer_credit_redemptions', $redemption);
                $redemption_ids[] = $wpdb->insert_id;
            }

            // Sample data for points log
            $sample_points = [
                [
                    'customer_id' => $customer_id,
                    'order_id' => 1001,
                    'transaction_type' => 'earned',
                    'points_amount' => 10.00,
                    'points_balance' => 10.00,
                    'reference_type' => 'order',
                    'reference_id' => 1001,
                    'description' => 'Points earned from order #1001',
                    'created_at' => current_time('mysql')
                ],
                [
                    'customer_id' => $customer_id,
                    'order_id' => 1001,
                    'transaction_type' => 'redeemed',
                    'points_amount' => -5.00,
                    'points_balance' => 5.00,
                    'reference_type' => 'redemption',
                    'reference_id' => $redemption_ids[0] ?? null, // Use the first redemption ID
                    'description' => 'Points redeemed for discount',
                    'created_at' => current_time('mysql')
                ]
            ];

            // Insert sample points
            foreach ($sample_points as $point) {
                $wpdb->insert($wpdb->prefix . 'intersoccer_points_log', $point);
            }

            wp_send_json_success('Demo data populated successfully.');
        } catch (Exception $e) {
            wp_send_json_error('Error populating demo data: ' . $e->getMessage());
        }
    }

    /**
     * Clear demo data for testing
     */
    public function clear_demo_data() {
        error_log('Clear demo data function called');

        // Security checks
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'intersoccer_admin_nonce')) {
            error_log('Invalid nonce in clear demo data');
            wp_send_json_error('Invalid nonce');
        }

        if (!current_user_can('manage_options')) {
            error_log('Insufficient permissions in clear demo data');
            wp_send_json_error('Insufficient permissions');
        }

        global $wpdb;

        try {
            // Clear tables (use DELETE FROM for safety) - check if tables exist first
            $tables_to_clear = [
                'intersoccer_referrals',
                'intersoccer_referral_credits',
                'intersoccer_credit_redemptions',
                'intersoccer_points_log',
                'intersoccer_referral_rewards',
                'intersoccer_purchase_rewards',
                'intersoccer_coach_achievements',
                'intersoccer_coach_assignments',
                'intersoccer_coach_commissions',
                'intersoccer_coach_notes',
                'intersoccer_coach_performance',
                'intersoccer_customer_activities',
                'intersoccer_customer_partnerships',
                'intersoccer_player_events',
                'intersoccer_referral_tracking'
            ];

            foreach ($tables_to_clear as $table_name) {
                $full_table_name = $wpdb->prefix . $table_name;
                $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name));
                if ($table_exists) {
                    $result = $wpdb->query("DELETE FROM $full_table_name");
                    error_log("Cleared $table_name: $result rows deleted");
                } else {
                    error_log("Table $table_name does not exist, skipping");
                }
            }

            // Clear user meta data
            $meta_result = $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'intersoccer%'");
            error_log("Cleared user meta data: $meta_result rows deleted");

            // Clear options (be selective to avoid breaking core functionality)
            $options_to_clear = [
                'intersoccer_audit_log',
                'intersoccer_points_sync_status',
                'intersoccer_last_coach_import'
            ];

            foreach ($options_to_clear as $option_pattern) {
                $option_result = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $option_pattern));
                error_log("Cleared options matching '$option_pattern': $option_result rows deleted");
            }

            wp_send_json_success('Demo data cleared successfully.');
        } catch (Exception $e) {
            wp_send_json_error('Error clearing demo data: ' . $e->getMessage());
        }
    }
}