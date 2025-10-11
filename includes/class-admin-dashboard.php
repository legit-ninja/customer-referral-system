<?php
// includes/class-admin-dashboard.php

class InterSoccer_Referral_Admin_Dashboard {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'handle_settings']);
        add_action('admin_post_import_coaches_from_csv', [$this, 'import_coaches_from_csv']);
        add_action('admin_post_nopriv_import_coaches_from_csv', [$this, 'import_coaches_from_csv']);
        add_action('wp_ajax_start_points_migration', [$this, 'start_points_migration']);
        add_action('wp_ajax_get_migration_progress', [$this, 'get_migration_progress']);
        add_action('wp_ajax_cancel_points_migration', [$this, 'cancel_points_migration']);
        add_action('wp_ajax_reset_points_migration', [$this, 'reset_points_migration']);
        add_action('wp_ajax_preview_points_migration', [$this, 'preview_points_migration']);
        add_action('wp_ajax_populate_demo_data', [$this, 'populate_demo_data']);
        add_action('wp_ajax_clear_demo_data', [$this, 'clear_demo_data']);
        add_action('wp_ajax_export_roi_report', [$this, 'export_roi_report']);
        add_action('wp_ajax_send_coach_message', [$this, 'send_coach_message']);
        add_action('wp_ajax_deactivate_coach', [$this, 'deactivate_coach']);
        add_action('wp_ajax_update_customer_credits', [$this, 'update_customer_credits']);
        add_action('wp_ajax_import_customers_credits', [$this, 'import_customers_and_assign_credits']);
        add_action('wp_ajax_emergency_cleanup_import', [$this, 'emergency_cleanup_import_session']);
        add_action('wp_ajax_debug_join_issue', [$this, 'debug_join_issue']);
        add_action('wp_ajax_reset_all_customer_credits', [$this, 'reset_all_customer_credits']);
       
        // Debug action to test AJAX is working
        add_action('wp_ajax_test_ajax_connection', [$this, 'test_ajax_connection']);

        // WooCommerce Points Integration
        if (class_exists('WooCommerce')) {
            add_action('woocommerce_checkout_before_order_review', [$this, 'add_points_redemption_field']);
            add_action('woocommerce_checkout_process', [$this, 'validate_points_redemption']);
            add_action('woocommerce_checkout_create_order', [$this, 'apply_points_discount_to_order'], 10, 2);
            add_action('woocommerce_order_status_changed', [$this, 'deduct_points_on_order_completion'], 10, 4);
            add_action('woocommerce_my_account_my_orders_column_order-total', [$this, 'display_points_used_in_orders']);
        }
    }

    public function add_admin_menus() {
        // Main menu
        add_menu_page(
            'InterSoccer Referrals',
            'Referrals',
            'manage_options',
            'intersoccer-referrals',
            [$this, 'render_main_dashboard'],
            'dashicons-money-alt',
            30
        );

        // Submenus
        add_submenu_page(
            'intersoccer-referrals',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'intersoccer-referrals',
            [$this, 'render_main_dashboard']
        );

        add_submenu_page(
            'intersoccer-referrals',
            'Coaches',
            'Coaches',
            'manage_options',
            'intersoccer-coaches',
            [$this, 'render_coaches_page']
        );

        add_submenu_page(
            'intersoccer-referrals',
            'Coach Referrals',
            'Coach Referrals',
            'manage_options',
            'intersoccer-coach-referrals',
            [$this, 'render_coach_referrals_page']
        );

        add_submenu_page(
            'intersoccer-referrals',
            'Customer Referrals',
            'Customer Referrals',
            'manage_options',
            'intersoccer-customer-referrals',
            [$this, 'render_customer_referrals_page']
        );

        add_submenu_page(
            'intersoccer-referrals',
            'Financial Report',
            'Financial Report',
            'manage_options',
            'intersoccer-financial-report',
            [$this, 'render_financial_report_page']
        );

        add_submenu_page(
            'intersoccer-referrals',
            'Settings',
            'Settings',
            'manage_options',
            'intersoccer-settings',
            [$this, 'render_settings_page']
        );
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'intersoccer') !== false) {
            // Enqueue Chart.js first
            wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', [], '3.9.1');
            
            // Enqueue our admin assets
            wp_enqueue_style('intersoccer-admin-css', INTERSOCCER_REFERRAL_URL . 'assets/css/admin-dashboard.css', [], INTERSOCCER_REFERRAL_VERSION);
            wp_enqueue_script('intersoccer-admin-js', INTERSOCCER_REFERRAL_URL . 'assets/js/admin-dashboard.js', ['jquery', 'chart-js'], INTERSOCCER_REFERRAL_VERSION, true);
            
            wp_localize_script('intersoccer-admin-js', 'intersoccer_admin', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('intersoccer_admin_nonce')
            ]);
        }
    }

    /**
     * RESET function to clear all assigned credits and start over
     */
    public function reset_all_customer_credits() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        global $wpdb;
        
        error_log('InterSoccer: Starting complete credit reset...');
        
        // Delete all credit-related user meta
        $credit_meta_keys = [
            'intersoccer_customer_credits',
            'intersoccer_total_credits_earned',
            'intersoccer_credits_imported',
            'intersoccer_import_date',
            'intersoccer_credit_breakdown',
            'intersoccer_credit_adjustments',
            'intersoccer_credits_used_total'
        ];
        
        $deleted_total = 0;
        foreach ($credit_meta_keys as $meta_key) {
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
                $meta_key
            ));
            $deleted_total += $deleted;
            error_log("InterSoccer: Deleted {$deleted} records for meta_key: {$meta_key}");
        }
        
        // Clear import summary
        delete_option('intersoccer_last_import_summary');
        delete_option('intersoccer_last_customer_import_report');
        
        error_log("InterSoccer: Credit reset complete - deleted {$deleted_total} total records");
        
        wp_send_json_success([
            'message' => "Reset complete! Deleted {$deleted_total} credit records from all customers.",
            'deleted_records' => $deleted_total
        ]);
    }

    /**
     * Handle admin settings and form submissions
     */
    public function handle_settings() {
        // Handle any settings form submissions here
        // This method is called on admin_init

        // Check if we're on our settings page
        if (isset($_GET['page']) && $_GET['page'] === 'intersoccer-settings') {
            // Handle settings form submissions
            if (isset($_POST['submit']) && check_admin_referer('intersoccer_settings_nonce')) {
                // Process settings updates
                $this->process_settings_update();
            }
        }
    }

    /**
     * Process settings form updates
     */
    private function process_settings_update() {
        // Handle settings updates here
        // This is a placeholder for future settings functionality
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
    public function get_customer_credit_stats() {
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
    public function get_recent_referrals($limit = 10) {
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
    public function get_top_coaches($limit = 5) {
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
    public function get_financial_overview_dashboard() {
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
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
                padding: 20px;
                background: #f8f9fa;
            }

            .stat-item {
                text-align: center;
            }

            .stat-label {
                display: block;
                font-size: 12px;
                color: #7f8c8d;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-bottom: 5px;
            }

            .stat-value {
                display: block;
                font-size: 20px;
                font-weight: 700;
                color: #2c3e50;
            }

            .coach-recent-activity {
                padding: 0 20px 15px 20px;
            }

            .coach-recent-activity h4 {
                margin: 0 0 10px 0;
                font-size: 14px;
                color: #2c3e50;
                font-weight: 600;
            }

            .activity-list {
                list-style: none;
                margin: 0;
                padding: 0;
            }

            .activity-item {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 8px 0;
                border-bottom: 1px solid #ecf0f1;
            }

            .activity-item:last-child {
                border-bottom: none;
            }

            .activity-icon {
                width: 24px;
                height: 24px;
                border-radius: 50%;
                background: #3498db;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 12px;
            }

            .activity-text {
                flex: 1;
                font-size: 13px;
                color: #2c3e50;
            }

            .activity-date {
                display: block;
                font-size: 11px;
                color: #7f8c8d;
                margin-top: 2px;
            }

            .coach-card-footer {
                padding: 15px 20px;
                background: white;
                border-top: 1px solid #ecf0f1;
            }

            .view-details-btn {
                display: inline-block;
                padding: 8px 16px;
                background: #007cba;
                color: white;
                text-decoration: none;
                border-radius: 6px;
                font-size: 14px;
                font-weight: 500;
                transition: background-color 0.2s ease;
            }

            .view-details-btn:hover {
                background: #005a87;
            }

            .no-coaches-message {
                text-align: center;
                padding: 40px 20px;
                background: white;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }

            .no-coaches-message p {
                font-size: 16px;
                color: #7f8c8d;
                margin: 0;
            }

            .no-coaches-message a {
                color: #007cba;
                text-decoration: none;
                font-weight: 600;
            }

            .no-coaches-message a:hover {
                text-decoration: underline;
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

                .coaches-grid {
                    grid-template-columns: 1fr;
                    gap: 15px;
                }

                .coach-card-header {
                    flex-direction: column;
                    text-align: center;
                    padding: 15px;
                }

                .coach-avatar {
                    margin-right: 0;
                    margin-bottom: 10px;
                }

                .coach-actions {
                    position: static;
                    justify-content: center;
                    margin-top: 10px;
                }

                .coach-stats {
                    grid-template-columns: repeat(2, 1fr);
                    padding: 15px;
                }

                .stat-value {
                    font-size: 18px;
                }
            }

            @media (max-width: 480px) {
                .coach-stats {
                    grid-template-columns: 1fr;
                }

                .coach-card-header {
                    padding: 12px;
                }

                .coach-info h3 {
                    font-size: 16px;
                }
            }
            </style>
        </div>
        <?php
    }

    /**
     * Get all dashboard chart data for JavaScript
     */
    public function get_dashboard_chart_data() {
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
    public function get_referral_trends_data() {
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
    public function get_credit_trends_data() {
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
    public function get_program_distribution_data() {
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
     * Handle coach CSV import
     */
    public function import_coaches_from_csv() {
        // Handle CSV import for coaches
        // This is a placeholder implementation
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Basic CSV import logic would go here
        // For now, just redirect back
        wp_redirect(admin_url('admin.php?page=intersoccer-coaches&imported=1'));
        exit;
    }

    /**
     * Start points migration process
     */
    public function start_points_migration() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        // Points migration logic would go here
        wp_send_json_success(['message' => 'Migration started']);
    }

    /**
     * Get migration progress
     */
    public function get_migration_progress() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        // Return migration progress
        wp_send_json_success(['progress' => 0, 'message' => 'Migration in progress']);
    }

    /**
     * Cancel points migration
     */
    public function cancel_points_migration() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['message' => 'Migration cancelled']);
    }

    /**
     * Reset points migration
     */
    public function reset_points_migration() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['message' => 'Migration reset']);
    }

    /**
     * Preview points migration
     */
    public function preview_points_migration() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['preview' => [], 'message' => 'Migration preview']);
    }

    /**
     * Populate demo data (placeholder)
     */
    public function populate_demo_data() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['message' => 'Demo data populated']);
    }

    /**
     * Clear demo data (placeholder)
     */
    public function clear_demo_data() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['message' => 'Demo data cleared']);
    }

    /**
     * Export ROI report
     */
    public function export_roi_report() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Export logic would go here
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="roi-report.csv"');
        echo "Date,Revenue,Costs,ROI\n";
        exit;
    }

    /**
     * Send coach message
     */
    public function send_coach_message() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['message' => 'Message sent']);
    }

    /**
     * Deactivate coach
     */
    public function deactivate_coach() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['message' => 'Coach deactivated']);
    }

    /**
     * Update customer credits
     */
    public function update_customer_credits() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['message' => 'Credits updated']);
    }

    /**
     * Import customers and assign credits
     */
    public function import_customers_and_assign_credits() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['message' => 'Import completed']);
    }

    /**
     * Emergency cleanup import session
     */
    public function emergency_cleanup_import_session() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['message' => 'Cleanup completed']);
    }

    /**
     * Debug join issue
     */
    public function debug_join_issue() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['debug' => 'Debug info']);
    }

    /**
     * Test AJAX connection
     */
    public function test_ajax_connection() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        wp_send_json_success(['message' => 'AJAX connection working', 'timestamp' => current_time('mysql')]);
    }

    /**
     * Add points redemption field to checkout
     */
    public function add_points_redemption_field() {
        // WooCommerce checkout integration
        if (!is_user_logged_in()) return;

        $user_id = get_current_user_id();
        $available_credits = get_user_meta($user_id, 'intersoccer_customer_credits', true) ?: 0;

        if ($available_credits > 0) {
            echo '<div class="intersoccer-points-redemption">';
            echo '<h3>' . __('Use Referral Credits', 'intersoccer-referral') . '</h3>';
            echo '<p>' . sprintf(__('You have %s credits available (%s CHF value)', 'intersoccer-referral'), $available_credits, number_format($available_credits, 0)) . '</p>';
            echo '<input type="number" name="intersoccer_points_to_redeem" id="intersoccer_points_to_redeem" min="0" max="' . $available_credits . '" step="1" placeholder="0" />';
            echo '<small>' . __('Enter the number of credits to use (max 100 per order)', 'intersoccer-referral') . '</small>';
            echo '</div>';
        }
    }

    /**
     * Validate points redemption on checkout
     */
    public function validate_points_redemption() {
        if (!isset($_POST['intersoccer_points_to_redeem'])) return;

        $points_to_redeem = intval($_POST['intersoccer_points_to_redeem']);

        if ($points_to_redeem < 0) {
            wc_add_notice(__('Invalid points amount.', 'intersoccer-referral'), 'error');
            return;
        }

        if ($points_to_redeem > 100) {
            wc_add_notice(__('You can redeem a maximum of 100 credits per order.', 'intersoccer-referral'), 'error');
            return;
        }

        $user_id = get_current_user_id();
        $available_credits = get_user_meta($user_id, 'intersoccer_customer_credits', true) ?: 0;

        if ($points_to_redeem > $available_credits) {
            wc_add_notice(__('You don\'t have enough credits available.', 'intersoccer-referral'), 'error');
            return;
        }
    }

    /**
     * Apply points discount to order
     */
    public function apply_points_discount_to_order($order) {
        if (!isset($_POST['intersoccer_points_to_redeem'])) return;

        $points_to_redeem = intval($_POST['intersoccer_points_to_redeem']);

        if ($points_to_redeem > 0) {
            // Store points to be deducted in session for later processing
            WC()->session->set('intersoccer_points_to_redeem', $points_to_redeem);

            // Add order note
            $order->add_order_note(sprintf(__('Customer redeemed %d referral credits.', 'intersoccer-referral'), $points_to_redeem));
        }
    }

    /**
     * Deduct points when order is completed
     */
    public function deduct_points_on_order_completion($order_id, $old_status, $new_status) {
        if ($new_status !== 'completed') return;

        $points_to_redeem = WC()->session->get('intersoccer_points_to_redeem');

        if ($points_to_redeem > 0) {
            $order = wc_get_order($order_id);
            $user_id = $order->get_customer_id();

            // Deduct points from user
            $current_credits = get_user_meta($user_id, 'intersoccer_customer_credits', true) ?: 0;
            $new_credits = max(0, $current_credits - $points_to_redeem);
            update_user_meta($user_id, 'intersoccer_customer_credits', $new_credits);

            // Record the redemption
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'intersoccer_credit_redemptions',
                [
                    'customer_id' => $user_id,
                    'credit_amount' => $points_to_redeem,
                    'order_total' => $order->get_total(),
                    'discount_applied' => $points_to_redeem, // 1 point = 1 CHF discount
                    'created_at' => current_time('mysql')
                ]
            );

            // Clear session
            WC()->session->set('intersoccer_points_to_redeem', 0);

            // Add order note
            $order->add_order_note(sprintf(__('Deducted %d credits from customer balance. New balance: %d', 'intersoccer-referral'), $points_to_redeem, $new_credits));
        }
    }

    /**
     * Display points used in order history
     */
    public function display_points_used_in_orders($order) {
        // This would modify the order total column to show points used
        // Implementation depends on WooCommerce hooks
    }

    /**
     * Get coach tier badge based on referral count
     */
    private function get_coach_tier_badge($referral_count) {
        if ($referral_count >= 20) {
            return '<span class="coach-badge platinum">Platinum</span>';
        } elseif ($referral_count >= 10) {
            return '<span class="coach-badge gold">Gold</span>';
        } elseif ($referral_count >= 5) {
            return '<span class="coach-badge silver">Silver</span>';
        } else {
            return '<span class="coach-badge bronze">Bronze</span>';
        }
    }

    /**
     * Get recent activities for dashboard feed
     */
    public function get_recent_activities($limit = 10) {
        global $wpdb;

        $activities = [];

        // Get recent referrals
        $referrals = $wpdb->get_results($wpdb->prepare("
            SELECT r.id, r.created_at, c.display_name as coach_name, u.display_name as customer_name
            FROM {$wpdb->prefix}intersoccer_referrals r
            LEFT JOIN {$wpdb->users} c ON r.coach_id = c.ID
            LEFT JOIN {$wpdb->users} u ON r.customer_id = u.ID
            ORDER BY r.created_at DESC
            LIMIT %d
        ", $limit));

        foreach ($referrals as $referral) {
            $activities[] = (object) [
                'type' => 'referral',
                'icon' => 'businessman',
                'message' => sprintf('%s referred %s', $referral->coach_name, $referral->customer_name),
                'created_at' => $referral->created_at,
                'amount' => 0,
                'amount_prefix' => ''
            ];
        }

        // Get recent credit redemptions
        $redemptions = $wpdb->get_results($wpdb->prepare("
            SELECT cr.created_at, u.display_name as customer_name, cr.credit_amount
            FROM {$wpdb->prefix}intersoccer_credit_redemptions cr
            LEFT JOIN {$wpdb->users} u ON cr.customer_id = u.ID
            ORDER BY cr.created_at DESC
            LIMIT %d
        ", $limit));

        foreach ($redemptions as $redemption) {
            $activities[] = (object) [
                'type' => 'redemption',
                'icon' => 'cart',
                'message' => sprintf('%s redeemed %d credits', $redemption->customer_name, $redemption->credit_amount),
                'created_at' => $redemption->created_at,
                'amount' => $redemption->credit_amount,
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
     * Render coaches management page
     */
    public function render_coaches_page() {
        ?>
        <div class="wrap intersoccer-admin">
            <h1 class="wp-heading-inline">Coach Management</h1>

            <div class="intersoccer-actions">
                <a href="<?php echo admin_url('admin-post.php?action=import_coaches_from_csv'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-upload"></span>
                    Import Coaches from CSV
                </a>
                <button class="button button-secondary" id="add-new-coach">
                    <span class="dashicons dashicons-plus"></span>
                    Add New Coach
                </button>
            </div>

            <div class="intersoccer-coaches-grid">
                <?php $this->display_coaches_list(); ?>
            </div>
        </div>

        <style>
        .intersoccer-actions {
            margin: 20px 0;
            display: flex;
            gap: 10px;
        }

        .intersoccer-coaches-grid {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        </style>
        <?php
    }

    /**
     * Display coaches list as cards
     */
    private function display_coaches_list() {
        global $wpdb;

        $coaches = get_users([
            'role' => 'coach',
            'orderby' => 'display_name',
            'order' => 'ASC'
        ]);

        if (empty($coaches)) {
            echo '<div class="no-coaches-message">';
            echo '<p>No coaches found. <a href="#" id="add-new-coach-link">Add your first coach</a> to get started.</p>';
            echo '</div>';
            return;
        }

        echo '<div class="coaches-grid">';

        foreach ($coaches as $coach) {
            $referral_count = $this->get_coach_referral_count($coach->ID);
            $total_commission = $this->get_coach_total_commission($coach->ID);
            $conversion_rate = $this->get_coach_conversion_rate($coach->ID);
            $tier = intersoccer_get_coach_tier($coach->ID);
            $recent_referrals = $this->get_coach_recent_referrals($coach->ID, 3);
            $active_partnerships = $this->get_coach_active_partnerships($coach->ID);

            ?>
            <div class="coach-card" data-coach-id="<?php echo $coach->ID; ?>">
                <div class="coach-card-header">
                    <div class="coach-avatar">
                        <?php echo get_avatar($coach->ID, 60); ?>
                    </div>
                    <div class="coach-info">
                        <h3><?php echo esc_html($coach->display_name); ?></h3>
                        <p class="coach-email"><?php echo esc_html($coach->user_email); ?></p>
                        <div class="coach-tier-badge <?php echo strtolower($tier); ?>">
                            <?php echo esc_html($tier); ?>
                        </div>
                    </div>
                    <div class="coach-actions">
                        <button class="coach-action-btn edit-coach" data-coach-id="<?php echo $coach->ID; ?>" title="Edit Coach">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <button class="coach-action-btn message-coach" data-coach-id="<?php echo $coach->ID; ?>" title="Send Message">
                            <span class="dashicons dashicons-email"></span>
                        </button>
                        <button class="coach-action-btn deactivate-coach" data-coach-id="<?php echo $coach->ID; ?>" title="Deactivate Coach">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>
                </div>

                <div class="coach-stats">
                    <div class="stat-item">
                        <span class="stat-label">Referrals</span>
                        <span class="stat-value"><?php echo number_format($referral_count); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Commission</span>
                        <span class="stat-value"><?php echo number_format($total_commission, 0); ?> CHF</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Conversion</span>
                        <span class="stat-value"><?php echo number_format($conversion_rate, 1); ?>%</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Partnerships</span>
                        <span class="stat-value"><?php echo number_format($active_partnerships); ?></span>
                    </div>
                </div>

                <?php if (!empty($recent_referrals)): ?>
                <div class="coach-recent-activity">
                    <h4>Recent Activity</h4>
                    <ul class="activity-list">
                        <?php foreach ($recent_referrals as $referral): ?>
                        <li class="activity-item">
                            <span class="activity-icon">
                                <span class="dashicons dashicons-plus"></span>
                            </span>
                            <span class="activity-text">
                                Referred <?php echo esc_html($referral->customer_name); ?>
                                <span class="activity-date"><?php echo human_time_diff(strtotime($referral->created_at), current_time('timestamp')); ?> ago</span>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="coach-card-footer">
                    <a href="<?php echo admin_url('admin.php?page=intersoccer-coach-referrals&coach_id=' . $coach->ID); ?>" class="view-details-btn">
                        View Details
                    </a>
                </div>
            </div>
            <?php
        }

        echo '</div>';

        // Add card-specific styles
        ?>
        <style>
        .coaches-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .coach-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid #e1e5e9;
        }

        .coach-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .coach-card-header {
            display: flex;
            align-items: center;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: relative;
        }

        .coach-avatar {
            margin-right: 15px;
        }

        .coach-avatar img {
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }

        .coach-info h3 {
            margin: 0 0 5px 0;
            font-size: 18px;
            font-weight: 600;
        }

        .coach-email {
            margin: 0 0 8px 0;
            font-size: 14px;
            opacity: 0.9;
        }

        .coach-tier-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            background: rgba(255, 255, 255, 0.2);
        }

        .coach-tier-badge.gold { background: linear-gradient(45deg, #ffd700, #ffed4e); color: #2c3e50; }
        .coach-tier-badge.platinum { background: linear-gradient(45deg, #e8e8e8, #c0c0c0); color: #2c3e50; }
        .coach-tier-badge.bronze { background: linear-gradient(45deg, #cd7f32, #a0522d); color: white; }
        .coach-tier-badge.silver { background: linear-gradient(45deg, #c0c0c0, #a8a8a8); color: #2c3e50; }

        .coach-actions {
            position: absolute;
            top: 15px;
            right: 15px;
            display: flex;
            gap: 8px;
        }

        .coach-action-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 6px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .coach-action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .coach-action-btn.deactivate-coach:hover {
            background: rgba(231, 76, 60, 0.8);
        }

        .coach-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            padding: 20px;
            background: #f8f9fa;
        }

        .stat-item {
            text-align: center;
        }

        .stat-label {
            display: block;
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .stat-value {
            display: block;
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
        }

        .coach-recent-activity {
            padding: 0 20px 15px 20px;
        }

        .coach-recent-activity h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #2c3e50;
            font-weight: 600;
        }

        .activity-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #ecf0f1;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
        }

        .activity-text {
            flex: 1;
            font-size: 13px;
            color: #2c3e50;
        }

        .activity-date {
            display: block;
            font-size: 11px;
            color: #7f8c8d;
            margin-top: 2px;
        }

        .coach-card-footer {
            padding: 15px 20px;
            background: white;
            border-top: 1px solid #ecf0f1;
        }

        .view-details-btn {
            display: inline-block;
            padding: 8px 16px;
            background: #007cba;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.2s ease;
        }

        .view-details-btn:hover {
            background: #005a87;
        }

        .no-coaches-message {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .no-coaches-message p {
            font-size: 16px;
            color: #7f8c8d;
            margin: 0;
        }

        .no-coaches-message a {
            color: #007cba;
            text-decoration: none;
            font-weight: 600;
        }

        .no-coaches-message a:hover {
            text-decoration: underline;
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

            .coaches-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .coach-card-header {
                flex-direction: column;
                text-align: center;
                padding: 15px;
            }

            .coach-avatar {
                margin-right: 0;
                margin-bottom: 10px;
            }

            .coach-actions {
                position: static;
                justify-content: center;
                margin-top: 10px;
            }

            .coach-stats {
                grid-template-columns: repeat(2, 1fr);
                padding: 15px;
            }

            .stat-value {
                font-size: 18px;
            }
        }

        @media (max-width: 480px) {
            .coach-stats {
                grid-template-columns: 1fr;
            }

            .coach-card-header {
                padding: 12px;
            }

            .coach-info h3 {
                font-size: 16px;
            }
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Handle coach actions
            $('.edit-coach').on('click', function() {
                var coachId = $(this).data('coach-id');
                window.location.href = '<?php echo admin_url('user-edit.php?user_id='); ?>' + coachId;
            });

            $('.message-coach').on('click', function() {
                var coachId = $(this).data('coach-id');
                // Open message modal or redirect to message page
                alert('Message functionality coming soon for coach ID: ' + coachId);
            });

            $('.deactivate-coach').on('click', function() {
                if (confirm('Are you sure you want to deactivate this coach?')) {
                    var coachId = $(this).data('coach-id');
                    // AJAX call to deactivate coach
                    $.ajax({
                        url: intersoccer_admin.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'deactivate_coach',
                            coach_id: coachId,
                            nonce: intersoccer_admin.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Error deactivating coach: ' + response.data.message);
                            }
                        }
                    });
                }
            });

            $('#add-new-coach-link').on('click', function(e) {
                e.preventDefault();
                window.location.href = '<?php echo admin_url('user-new.php'); ?>';
            });
        });
        </script>
        <?php
    }

    /**
     * Get coach referral count
     */
    private function get_coach_referral_count($coach_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}intersoccer_referrals
            WHERE coach_id = %d
        ", $coach_id));
    }

    /**
     * Get coach total commission
     */
    private function get_coach_total_commission($coach_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(rc.credit_amount), 0)
            FROM {$wpdb->prefix}intersoccer_referral_credits rc
            INNER JOIN {$wpdb->prefix}intersoccer_referrals r ON rc.referral_id = r.id
            WHERE r.coach_id = %d
        ", $coach_id));
    }

    /**
     * Get coach conversion rate
     */
    private function get_coach_conversion_rate($coach_id) {
        global $wpdb;

        $total_referrals = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}intersoccer_referrals
            WHERE coach_id = %d
        ", $coach_id));

        $completed_referrals = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}intersoccer_referrals
            WHERE coach_id = %d AND status = 'completed'
        ", $coach_id));

        return $total_referrals > 0 ? ($completed_referrals / $total_referrals) * 100 : 0;
    }

    /**
     * Get coach recent referrals
     */
    private function get_coach_recent_referrals($coach_id, $limit = 3) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT r.created_at, u.display_name as customer_name
            FROM {$wpdb->prefix}intersoccer_referrals r
            LEFT JOIN {$wpdb->users} u ON r.customer_id = u.ID
            WHERE r.coach_id = %d
            ORDER BY r.created_at DESC
            LIMIT %d
        ", $coach_id, $limit));
    }

    /**
     * Get coach active partnerships count
     */
    private function get_coach_active_partnerships($coach_id) {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'intersoccer_partnership_coach_id'
            AND meta_value = %d
        ", $coach_id));
    }
}