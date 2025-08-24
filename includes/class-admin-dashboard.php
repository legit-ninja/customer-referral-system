<?php
// includes/class-admin-dashboard.php

class InterSoccer_Referral_Admin_Dashboard {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_populate_demo_data', [$this, 'populate_demo_data']);
        add_action('wp_ajax_clear_demo_data', [$this, 'clear_demo_data']);
        add_action('wp_ajax_export_roi_report', [$this, 'export_roi_report']);
        add_action('wp_ajax_send_coach_message', [$this, 'send_coach_message']);
        add_action('wp_ajax_deactivate_coach', [$this, 'deactivate_coach']);
        add_action('wp_ajax_update_customer_credits', [$this, 'update_customer_credits']);
        add_action('admin_init', [$this, 'handle_settings']);
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
            wp_enqueue_style('intersoccer-admin-css', INTERSOCCER_REFERRAL_URL . 'assets/css/admin-dashboard.css', [], INTERSOCCER_REFERRAL_VERSION);
            wp_enqueue_script('intersoccer-admin-js', INTERSOCCER_REFERRAL_URL . 'assets/js/admin-dashboard.js', ['jquery', 'chart-js'], INTERSOCCER_REFERRAL_VERSION, true);
            wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', [], '3.9.1');
            
            wp_localize_script('intersoccer-admin-js', 'intersoccer_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('intersoccer_admin_nonce')
            ]);
        }
    }

    public function render_main_dashboard() {
        $stats = $this->get_dashboard_stats();
        $recent_referrals = $this->get_recent_referrals(10);
        $top_coaches = $this->get_top_coaches(5);
        
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

            <!-- Stats Cards -->
            <div class="intersoccer-stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_coaches']); ?></h3>
                        <p>Active Coaches</p>
                        <span class="stat-change positive">+<?php echo $stats['new_coaches_this_month']; ?> this month</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-businessman"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_referrals']); ?></h3>
                        <p>Total Referrals</p>
                        <span class="stat-change positive">+<?php echo $stats['new_referrals_this_month']; ?> this month</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_commissions'], 2); ?> CHF</h3>
                        <p>Total Commissions</p>
                        <span class="stat-change positive">+<?php echo number_format($stats['commissions_this_month'], 2); ?> CHF this month</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['conversion_rate'], 1); ?>%</h3>
                        <p>Conversion Rate</p>
                        <span class="stat-change <?php echo $stats['conversion_change'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo ($stats['conversion_change'] >= 0 ? '+' : '') . number_format($stats['conversion_change'], 1); ?>% vs last month
                        </span>
                    </div>
                </div>
            </div>

            <div class="intersoccer-dashboard-grid">
                <!-- Performance Chart -->
                <div class="dashboard-widget chart-widget">
                    <h2>Referral Performance</h2>
                    <canvas id="performanceChart" width="400" height="200"></canvas>
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
                                <span class="coach-stats"><?php echo $coach->referral_count; ?> referrals | <?php echo number_format($coach->total_commission, 2); ?> CHF</span>
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
                    <h2>Recent Referrals</h2>
                    <div class="recent-activity">
                        <?php foreach ($recent_referrals as $referral): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <span class="dashicons dashicons-plus-alt"></span>
                            </div>
                            <div class="activity-content">
                                <p><strong><?php echo esc_html($referral->coach_name); ?></strong> earned <?php echo number_format($referral->commission_amount, 2); ?> CHF</p>
                                <span class="activity-time"><?php echo human_time_diff(strtotime($referral->created_at), current_time('timestamp')); ?> ago</span>
                            </div>
                            <div class="activity-amount">
                                +<?php echo number_format($referral->commission_amount, 2); ?> CHF
                            </div>
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
                        <a href="<?php echo admin_url('admin.php?page=intersoccer-referral-list'); ?>" class="quick-action-btn">
                            <span class="dashicons dashicons-list-view"></span>
                            View All Referrals
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=intersoccer-settings'); ?>" class="quick-action-btn">
                            <span class="dashicons dashicons-admin-settings"></span>
                            Settings
                        </a>
                        <button class="quick-action-btn" id="export-data">
                            <span class="dashicons dashicons-download"></span>
                            Export Coach Data
                        </button>
                        <button class="quick-action-btn" id="export-roi">
                            <span class="dashicons dashicons-chart-line"></span>
                            Export ROI Report
                        </button>
                    </div>
                </div>
            </div>

            <!-- Performance Data for Chart -->
            <script type="text/javascript">
                var performanceData = <?php echo json_encode($this->get_performance_chart_data()); ?>;
            </script>
        </div>
        <?php
    }

    public function render_coaches_page() {
        $coaches = $this->get_all_coaches();
        ?>
        <div class="wrap intersoccer-admin">
            <h1>Coach Management</h1>
            
            <?php if (empty($coaches)): ?>
                <div class="notice notice-info">
                    <p>No coaches found. <a href="<?php echo admin_url('admin.php?page=intersoccer-settings'); ?>">Import coaches from CSV</a> to get started.</p>
                </div>
            <?php else: ?>
                <p>Total coaches: <strong><?php echo count($coaches); ?></strong></p>
            <?php endif; ?>
            
            <div class="coaches-grid">
                <?php foreach ($coaches as $coach): ?>
                <div class="coach-card">
                    <div class="coach-header">
                        <?php echo get_avatar($coach->ID, 60); ?>
                        <div class="coach-info">
                            <h3><?php echo esc_html($coach->display_name); ?></h3>
                            <p><?php echo esc_html($coach->user_email); ?></p>
                            <?php if ($coach->location): ?>
                                <p><span class="dashicons dashicons-location"></span> <?php echo esc_html($coach->location); ?></p>
                            <?php endif; ?>
                            <?php if ($coach->specialization): ?>
                                <span class="coach-specialization"><?php echo esc_html($coach->specialization); ?></span>
                            <?php endif; ?>
                            <span class="coach-tier"><?php echo $this->get_coach_tier($coach->referral_count); ?></span>
                        </div>
                    </div>
                    
                    <div class="coach-stats">
                        <div class="stat">
                            <span class="number"><?php echo $coach->referral_count; ?></span>
                            <span class="label">Referrals</span>
                        </div>
                        <div class="stat">
                            <span class="number"><?php echo number_format($coach->total_commission, 0); ?></span>
                            <span class="label">CHF Earned</span>
                        </div>
                        <div class="stat">
                            <span class="number"><?php echo number_format($coach->credits, 0); ?></span>
                            <span class="label">Credits</span>
                        </div>
                    </div>
                    
                    <?php if ($coach->bio): ?>
                    <div class="coach-bio">
                        <p><?php echo esc_html(wp_trim_words($coach->bio, 15)); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="coach-actions">
                        <button class="button button-small view-details" data-coach-id="<?php echo $coach->ID; ?>">
                            View Details
                        </button>
                        <button class="button button-small send-message" data-coach-id="<?php echo $coach->ID; ?>">
                            Send Message
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    public function render_coach_referrals_page() {
        $coach_referrals = $this->get_coach_referrals();
        ?>
        <div class="wrap intersoccer-admin">
            <h1>Coach Referrals</h1>
            <p class="description">These referrals generate commission payments for coaches. Credits are used for financial compensation.</p>
            
            <div class="tablenav top">
                <div class="alignleft actions">
                    <select name="status_filter" id="status-filter">
                        <option value="">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="paid">Paid</option>
                    </select>
                    <button type="button" class="button" onclick="filterReferrals()">Filter</button>
                </div>
                <div class="alignright actions">
                    <button type="button" class="button" onclick="exportCoachReferrals()">Export CSV</button>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Coach</th>
                        <th>Customer</th>
                        <th>Order</th>
                        <th>Commission</th>
                        <th>Loyalty Bonus</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coach_referrals as $referral): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($referral->coach_name); ?></strong>
                            <div class="row-actions">
                                <span class="tier"><?php echo intersoccer_get_coach_tier($referral->coach_id); ?> Coach</span>
                            </div>
                        </td>
                        <td><?php echo esc_html($referral->customer_name ?: 'Customer #' . $referral->customer_id); ?></td>
                        <td>
                            <a href="<?php echo admin_url('post.php?post=' . $referral->order_id . '&action=edit'); ?>">
                                Order #<?php echo $referral->order_id; ?>
                            </a>
                        </td>
                        <td><strong><?php echo number_format($referral->commission_amount, 2); ?> CHF</strong></td>
                        <td><?php echo number_format($referral->loyalty_bonus ?: 0, 2); ?> CHF</td>
                        <td>
                            <span class="status-badge status-<?php echo $referral->status; ?>">
                                <?php echo ucfirst($referral->status); ?>
                            </span>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($referral->created_at)); ?></td>
                        <td>
                            <?php if ($referral->status === 'pending'): ?>
                                <button class="button button-small" onclick="updateReferralStatus(<?php echo $referral->id; ?>, 'approved')">Approve</button>
                            <?php elseif ($referral->status === 'approved'): ?>
                                <button class="button button-primary button-small" onclick="updateReferralStatus(<?php echo $referral->id; ?>, 'paid')">Mark Paid</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <style>
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #cce5ff; color: #004085; }
        .status-paid { background: #d4edda; color: #155724; }
        </style>
        <?php
    }

    public function render_customer_referrals_page() {
        $customer_referrals = $this->get_customer_referrals();
        ?>
        <div class="wrap intersoccer-admin">
            <h1>Customer Referrals</h1>
            <p class="description">Customer-to-customer referrals. Credits are redeemable at checkout only.</p>
            
            <div class="stats-overview" style="display: flex; gap: 20px; margin-bottom: 20px;">
                <div class="stat-card" style="flex: 1; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3>Total Customer Referrals</h3>
                    <span style="font-size: 24px; font-weight: bold; color: #0073aa;"><?php echo count($customer_referrals); ?></span>
                </div>
                <div class="stat-card" style="flex: 1; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h3>Credits Distributed</h3>
                    <span style="font-size: 24px; font-weight: bold; color: #00a32a;">
                        <?php echo number_format(array_sum(array_column($customer_referrals, 'credits_awarded')), 0); ?> CHF
                    </span>
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Referring Customer</th>
                        <th>New Customer</th>
                        <th>Credits Awarded</th>
                        <th>Credits Used</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customer_referrals)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px;">
                            <div style="color: #666;">
                                <span class="dashicons dashicons-groups" style="font-size: 48px; opacity: 0.3;"></span>
                                <p>No customer referrals yet.</p>
                                <p><small>Customer referrals will appear here when customers share their referral links.</small></p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($customer_referrals as $referral): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($referral->referrer_name); ?></strong>
                                <div class="row-actions">
                                    <span><?php echo esc_html($referral->referrer_email); ?></span>
                                </div>
                            </td>
                            <td>
                                <strong><?php echo esc_html($referral->referred_name); ?></strong>
                                <div class="row-actions">
                                    <span><?php echo esc_html($referral->referred_email); ?></span>
                                </div>
                            </td>
                            <td><strong><?php echo number_format($referral->credits_awarded, 0); ?> CHF</strong></td>
                            <td>
                                <?php if ($referral->credits_used > 0): ?>
                                    <?php echo number_format($referral->credits_used, 2); ?> CHF
                                <?php else: ?>
                                    <span style="color: #666;">Not used</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $referral->status; ?>">
                                    <?php echo ucfirst($referral->status); ?>
                                </span>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($referral->created_at)); ?></td>
                            <td>
                                <button class="button button-small" onclick="viewCustomerDetails(<?php echo $referral->referrer_id; ?>)">
                                    View Profile
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div style="margin-top: 20px; padding: 15px; background: #f0f6ff; border-left: 4px solid #0073aa;">
                <h4>💡 Customer Referral System</h4>
                <ul style="margin: 10px 0;">
                    <li>Customers earn <strong>50 CHF credits</strong> per successful referral</li>
                    <li>Credits can only be redeemed at checkout (not cash payments)</li>
                    <li>Lower reward rate than coach referrals to encourage coach program</li>
                    <li>Builds customer loyalty and organic growth</li>
                </ul>
            </div>
        </div>
        <?php
    }

    public function render_referrals_page() {
        // Legacy method - redirect to coach referrals
        wp_redirect(admin_url('admin.php?page=intersoccer-coach-referrals'));
        exit;
    }

    public function render_settings_page() {
        ?>
        <div class="wrap intersoccer-admin">
            <h1>Referral System Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('intersoccer_settings');
                do_settings_sections('intersoccer_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Commission Rate (First Purchase)</th>
                        <td>
                            <input type="number" name="intersoccer_commission_first" value="<?php echo get_option('intersoccer_commission_first', 15); ?>" step="0.1" min="0" max="100" />%
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Commission Rate (Second Purchase)</th>
                        <td>
                            <input type="number" name="intersoccer_commission_second" value="<?php echo get_option('intersoccer_commission_second', 7.5); ?>" step="0.1" min="0" max="100" />%
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Commission Rate (Third+ Purchase)</th>
                        <td>
                            <input type="number" name="intersoccer_commission_third" value="<?php echo get_option('intersoccer_commission_third', 5); ?>" step="0.1" min="0" max="100" />%
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render Financial Report Page (Complete Implementation)
     */
    public function render_financial_report_page() {
        $financial_data = $this->get_financial_overview();
        $customer_credits = $this->get_customer_credits_breakdown();
        
        ?>
        <div class="wrap intersoccer-admin">
            <h1>💰 Financial Report</h1>
            <p class="description">Complete overview of credits, redemptions, and financial obligations.</p>
            
            <!-- Financial Overview Cards -->
            <div class="financial-overview-grid">
                <div class="financial-card total-active">
                    <div class="card-icon">💳</div>
                    <div class="card-content">
                        <h3><?php echo number_format($financial_data['total_active_credits'], 2); ?> CHF</h3>
                        <p>Total Active Credits</p>
                        <span class="card-subtitle">Liability on books</span>
                    </div>
                </div>
                
                <div class="financial-card total-redeemed">
                    <div class="card-icon">✅</div>
                    <div class="card-content">
                        <h3><?php echo number_format($financial_data['total_redeemed_credits'], 2); ?> CHF</h3>
                        <p>Credits Redeemed</p>
                        <span class="card-subtitle">Revenue impact</span>
                    </div>
                </div>
                
                <div class="financial-card coach-earnings">
                    <div class="card-icon">👨‍🏫</div>
                    <div class="card-content">
                        <h3><?php echo number_format($financial_data['total_coach_earnings'], 2); ?> CHF</h3>
                        <p>Coach Commissions</p>
                        <span class="card-subtitle">Payment obligations</span>
                    </div>
                </div>
                
                <div class="financial-card net-impact">
                    <div class="card-icon">📊</div>
                    <div class="card-content">
                        <h3><?php echo number_format($financial_data['net_financial_impact'], 2); ?> CHF</h3>
                        <p>Net Financial Impact</p>
                        <span class="card-subtitle">Total program cost</span>
                    </div>
                </div>
            </div>
            
            <!-- Customer Credits Management -->
            <div class="credits-management-section">
                <div class="section-header">
                    <h2>👥 Customer Credits Management</h2>
                    <button class="button button-primary" onclick="showBulkCreditModal()">Bulk Credit Adjustment</button>
                </div>
                
                <div class="credits-filters">
                    <select id="credit-filter" onchange="filterCredits()">
                        <option value="all">All Customers</option>
                        <option value="high">High Balance (>200 CHF)</option>
                        <option value="medium">Medium Balance (50-200 CHF)</option>
                        <option value="low">Low Balance (<50 CHF)</option>
                        <option value="zero">Zero Balance</option>
                    </select>
                    
                    <input type="text" id="customer-search" placeholder="Search customers..." onkeyup="searchCustomers()">
                    <button class="button" onclick="exportCreditsReport()">Export CSV</button>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Current Credits</th>
                            <th>Credits Earned</th>
                            <th>Credits Used</th>
                            <th>Last Activity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="customer-credits-table">
                        <?php foreach ($customer_credits as $customer): ?>
                        <tr data-credits="<?php echo $customer->current_credits; ?>" data-customer="<?php echo esc_attr(strtolower($customer->display_name . ' ' . $customer->user_email)); ?>">
                            <td>
                                <div class="customer-info">
                                    <?php echo get_avatar($customer->ID, 32); ?>
                                    <div class="customer-details">
                                        <strong><?php echo esc_html($customer->display_name); ?></strong>
                                        <div class="customer-email"><?php echo esc_html($customer->user_email); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="credits-display <?php echo $customer->current_credits > 200 ? 'high-credits' : ($customer->current_credits > 50 ? 'medium-credits' : 'low-credits'); ?>">
                                    <?php echo number_format($customer->current_credits, 2); ?> CHF
                                </span>
                            </td>
                            <td><?php echo number_format($customer->credits_earned, 2); ?> CHF</td>
                            <td><?php echo number_format($customer->credits_used, 2); ?> CHF</td>
                            <td><?php echo $customer->last_activity ? date('M j, Y', strtotime($customer->last_activity)) : 'Never'; ?></td>
                            <td>
                                <button class="button button-small edit-credits" 
                                        data-customer-id="<?php echo $customer->ID; ?>" 
                                        data-current-credits="<?php echo $customer->current_credits; ?>"
                                        data-customer-name="<?php echo esc_attr($customer->display_name); ?>">
                                    Edit Credits
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Add the modal and JavaScript here -->
        <div id="edit-credits-modal" class="modal-overlay" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Edit Customer Credits</h3>
                    <button class="modal-close" onclick="closeEditModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <form id="edit-credits-form">
                        <div class="form-group">
                            <label>Customer:</label>
                            <p id="edit-customer-name" class="customer-name"></p>
                        </div>
                        <div class="form-group">
                            <label for="current-credits-display">Current Credits:</label>
                            <p id="current-credits-display" class="current-credits"></p>
                        </div>
                        <div class="form-group">
                            <label for="new-credits">New Credit Amount (CHF):</label>
                            <input type="number" id="new-credits" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="adjustment-reason">Reason for Adjustment:</label>
                            <select id="adjustment-reason" required>
                                <option value="">Select reason...</option>
                                <option value="customer_service">Customer Service Adjustment</option>
                                <option value="promotion">Promotional Credit</option>
                                <option value="refund">Refund Credits</option>
                                <option value="correction">Data Correction</option>
                                <option value="bonus">Special Bonus</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="adjustment-notes">Notes:</label>
                            <textarea id="adjustment-notes" rows="3" placeholder="Optional notes about this adjustment..."></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="button button-primary">Update Credits</button>
                            <button type="button" class="button" onclick="closeEditModal()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <style>
        .financial-overview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .financial-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.2s ease;
        }
        
        .financial-card:hover {
            transform: translateY(-2px);
        }
        
        .financial-card.total-active { border-left: 4px solid #e74c3c; }
        .financial-card.total-redeemed { border-left: 4px solid #27ae60; }
        .financial-card.coach-earnings { border-left: 4px solid #3498db; }
        .financial-card.net-impact { border-left: 4px solid #9b59b6; }
        
        .card-icon {
            font-size: 32px;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 0, 0, 0.05);
            border-radius: 50%;
        }
        
        .credits-management-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .credits-filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .customer-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .credits-display {
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 12px;
        }
        
        .high-credits { background: #ffebee; color: #c62828; }
        .medium-credits { background: #fff3e0; color: #ef6c00; }
        .low-credits { background: #f3e5f5; color: #7b1fa2; }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e1e5e9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        </style>
        
        <script>
        let currentEditingCustomer = null;
        
        function showEditModal(customerId, currentCredits, customerName) {
            currentEditingCustomer = customerId;
            document.getElementById('edit-customer-name').textContent = customerName;
            document.getElementById('current-credits-display').textContent = currentCredits + ' CHF';
            document.getElementById('new-credits').value = currentCredits;
            document.getElementById('edit-credits-modal').style.display = 'flex';
        }
        
        function closeEditModal() {
            document.getElementById('edit-credits-modal').style.display = 'none';
            document.getElementById('edit-credits-form').reset();
            currentEditingCustomer = null;
        }
        
        // Edit credits button handlers
        document.querySelectorAll('.edit-credits').forEach(button => {
            button.addEventListener('click', function() {
                const customerId = this.dataset.customerId;
                const currentCredits = this.dataset.currentCredits;
                const customerName = this.dataset.customerName;
                showEditModal(customerId, currentCredits, customerName);
            });
        });
        
        // Form submission
        document.getElementById('edit-credits-form').addEventListener('submit', function(e) {
            e.preventDefault();
            updateCustomerCredits();
        });
        
        function updateCustomerCredits() {
            const formData = {
                action: 'update_customer_credits',
                nonce: '<?php echo wp_create_nonce("intersoccer_admin_nonce"); ?>',
                customer_id: currentEditingCustomer,
                new_credits: document.getElementById('new-credits').value,
                reason: document.getElementById('adjustment-reason').value,
                notes: document.getElementById('adjustment-notes').value
            };
            
            jQuery.post(ajaxurl, formData)
                .done(function(response) {
                    if (response.success) {
                        alert('Credits updated successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + response.data.message);
                    }
                })
                .fail(function() {
                    alert('Request failed. Please try again.');
                });
        }
        
        function filterCredits() {
            const filter = document.getElementById('credit-filter').value;
            const rows = document.querySelectorAll('#customer-credits-table tr');
            
            rows.forEach(row => {
                const credits = parseFloat(row.dataset.credits) || 0;
                let show = true;
                
                switch(filter) {
                    case 'high': show = credits > 200; break;
                    case 'medium': show = credits >= 50 && credits <= 200; break;
                    case 'low': show = credits < 50 && credits > 0; break;
                    case 'zero': show = credits === 0; break;
                    default: show = true;
                }
                
                row.style.display = show ? '' : 'none';
            });
        }
        
        function searchCustomers() {
            const search = document.getElementById('customer-search').value.toLowerCase();
            const rows = document.querySelectorAll('#customer-credits-table tr');
            
            rows.forEach(row => {
                const customerText = row.dataset.customer || '';
                row.style.display = customerText.includes(search) ? '' : 'none';
            });
        }
        </script>
        <?php
    }

    public function import_coaches_from_csv() {
        
        if (!current_user_can('manage_options') || !isset($_FILES['coach_csv']) || !check_admin_referer('import_coaches_nonce', 'import_coaches_nonce')) {
            error_log('Import coaches failed: Unauthorized or no file uploaded');
            wp_safe_redirect(admin_url('admin.php?page=intersoccer-settings&error=unauthorized'));
            exit;
        }

        $file = $_FILES['coach_csv']['tmp_name'];
        error_log('Import coaches triggered, $_FILES: ' . json_encode($_FILES));
        if (!is_uploaded_file($file) || $_FILES['coach_csv']['error'] !== UPLOAD_ERR_OK) {
            error_log('Import coaches failed: File upload error, code: ' . $_FILES['coach_csv']['error']);
            wp_safe_redirect(admin_url('admin.php?page=intersoccer-settings&error=upload_failed'));
            exit;
        }

        if (($handle = fopen($file, 'r')) !== false) {
            $header = fgetcsv($handle);
            
            // More flexible header validation - check required fields exist
            $required_fields = ['first_name', 'last_name', 'email'];
            $missing_fields = array_diff($required_fields, $header);
            
            if (!empty($missing_fields)) {
                error_log('Import coaches failed: Missing required fields: ' . implode(', ', $missing_fields));
                wp_safe_redirect(admin_url('admin.php?page=intersoccer-settings&error=missing_fields'));
                exit;
            }

            while (($data = fgetcsv($handle)) !== false) {
                $coach_data = array_combine($header, $data);
                $user_id = wp_create_user(
                    sanitize_title($coach_data['first_name'] . '_' . $coach_data['last_name']),
                    wp_generate_password(12),
                    sanitize_email($coach_data['email'])
                );
                if (is_wp_error($user_id)) {
                    error_log('Import failed for ' . $coach_data['email'] . ': ' . $user_id->get_error_message());
                    continue;
                }
                if (!is_wp_error($user_id)) {
                    wp_update_user([
                        'ID' => $user_id,
                        'first_name' => sanitize_text_field($coach_data['first_name']),
                        'last_name' => sanitize_text_field($coach_data['last_name']),
                        'display_name' => sanitize_text_field($coach_data['first_name'] . ' ' . $coach_data['last_name']),
                        'role' => 'coach'
                    ]);
                    update_user_meta($user_id, 'intersoccer_phone', sanitize_text_field($coach_data['phone']));
                    update_user_meta($user_id, 'intersoccer_specialization', sanitize_text_field($coach_data['specialization']));
                    update_user_meta($user_id, 'intersoccer_location', sanitize_text_field($coach_data['location']));
                    update_user_meta($user_id, 'intersoccer_experience_years', absint($coach_data['experience_years']));
                    update_user_meta($user_id, 'intersoccer_bio', wp_kses_post($coach_data['bio']));
                    InterSoccer_Referral_Handler::generate_coach_referral_link($user_id);
                    wp_new_user_notification($user_id, null, 'both');
                    error_log('Imported coach: ' . $coach_data['email'] . ', meta saved: ' . json_encode($coach_data));
                } else {
                    error_log('Failed to import coach: ' . $coach_data['email'] . ', error: ' . $user_id->get_error_message());
                }
            }
            fclose($handle);
        } else {
            error_log('Import coaches failed: Unable to open CSV file');
            wp_safe_redirect(admin_url('admin.php?page=intersoccer-settings&error=file_open_failed'));
            exit;
        }

        wp_safe_redirect(admin_url('admin.php?page=intersoccer-settings&imported=1'));
        exit;
    }

    // AJAX handler for populating demo data
    public function populate_demo_data() {
        if (!check_ajax_referer('intersoccer_admin_nonce', 'nonce', false)) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        try {
            $this->create_demo_coaches();
            $this->create_demo_customers();
            $this->create_demo_referrals();
            
            wp_send_json_success([
                'message' => 'Demo data populated successfully!',
                'coaches_created' => 10,
                'customers_created' => 25,
                'referrals_created' => 50
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error creating demo data: ' . $e->getMessage()]);
        }
    }
    

    public function clear_demo_data() {
        if (!check_ajax_referer('intersoccer_admin_nonce', 'nonce', false)) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        global $wpdb;
        
        try {
            // Clear all related tables
            $referrals_table = $wpdb->prefix . 'intersoccer_referrals';
            $performance_table = $wpdb->prefix . 'intersoccer_coach_performance';
            $achievements_table = $wpdb->prefix . 'intersoccer_coach_achievements';
            
            $wpdb->query("TRUNCATE TABLE $referrals_table");
            $wpdb->query("TRUNCATE TABLE $performance_table");
            $wpdb->query("TRUNCATE TABLE $achievements_table");
            
            // Remove demo users
            $demo_users = get_users(['meta_key' => 'intersoccer_demo_user', 'meta_value' => '1']);
            foreach ($demo_users as $user) {
                wp_delete_user($user->ID);
            }
            
            wp_send_json_success(['message' => 'Demo data cleared successfully!']);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error clearing demo data: ' . $e->getMessage()]);
        }
    }

    // AJAX handler for updating customer credits
    public function update_customer_credits() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Unauthorized']);
        
        $customer_id = intval($_POST['customer_id']);
        $new_credits = floatval($_POST['new_credits']);
        $reason = sanitize_text_field($_POST['reason']);
        $notes = sanitize_textarea_field($_POST['notes']);
        
        $customer = get_user_by('ID', $customer_id);
        if (!$customer) {
            wp_send_json_error(['message' => 'Customer not found']);
        }
        
        $old_credits = get_user_meta($customer_id, 'intersoccer_customer_credits', true) ?: 0;
        
        // Update credits
        update_user_meta($customer_id, 'intersoccer_customer_credits', $new_credits);
        
        // Log the adjustment
        $adjustment_log = get_user_meta($customer_id, 'intersoccer_credit_adjustments', true) ?: [];
        $adjustment_log[] = [
            'date' => current_time('mysql'),
            'admin_user' => get_current_user_id(),
            'old_credits' => $old_credits,
            'new_credits' => $new_credits,
            'difference' => $new_credits - $old_credits,
            'reason' => $reason,
            'notes' => $notes
        ];
        update_user_meta($customer_id, 'intersoccer_credit_adjustments', $adjustment_log);
        
        error_log("Credits updated for customer {$customer_id}: {$old_credits} → {$new_credits} CHF (Reason: {$reason})");
        
        wp_send_json_success(['message' => 'Credits updated successfully']);
    }

    // ROI Report Export
    public function export_roi_report() {
        if (!check_ajax_referer('intersoccer_admin_nonce', 'nonce', false)) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        $report = InterSoccer_Commission_Calculator::generate_admin_report();
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="intersoccer_roi_' . date('Ymd_His') . '.csv"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        
        $fp = fopen('php://output', 'w');
        
        // Add BOM for Excel UTF-8 support
        fputs($fp, "\xEF\xBB\xBF");
        
        // Header section
        fputcsv($fp, ['InterSoccer ROI Report - ' . date('Y-m-d H:i:s')]);
        fputcsv($fp, ['Report Period', $report['period']['start'] . ' to ' . $report['period']['end']]);
        fputcsv($fp, []);
        
        // Summary metrics
        fputcsv($fp, ['PERFORMANCE SUMMARY']);
        fputcsv($fp, ['Metric', 'Value', 'Unit']);
        fputcsv($fp, ['Total Referrals', number_format($report['summary']->total_referrals), 'referrals']);
        fputcsv($fp, ['Active Coaches', number_format($report['summary']->active_coaches), 'coaches']);
        fputcsv($fp, ['Unique Customers', number_format($report['summary']->unique_customers), 'customers']);
        fputcsv($fp, ['Total Base Commission', number_format($report['summary']->total_base_commission, 2), 'CHF']);
        fputcsv($fp, ['Total Loyalty Bonuses', number_format($report['summary']->total_loyalty_bonus, 2), 'CHF']);
        fputcsv($fp, ['Total Retention Bonuses', number_format($report['summary']->total_retention_bonus, 2), 'CHF']);
        fputcsv($fp, ['Total Payout', number_format($report['summary']->total_payout, 2), 'CHF']);
        
        // Calculate ROI metrics
        $avg_commission_per_referral = $report['summary']->total_referrals > 0 ? 
            $report['summary']->total_payout / $report['summary']->total_referrals : 0;
        $avg_commission_per_coach = $report['summary']->active_coaches > 0 ?
            $report['summary']->total_payout / $report['summary']->active_coaches : 0;
        
        fputcsv($fp, ['Average Commission per Referral', number_format($avg_commission_per_referral, 2), 'CHF']);
        fputcsv($fp, ['Average Earnings per Coach', number_format($avg_commission_per_coach, 2), 'CHF']);
        fputcsv($fp, []);
        
        // Tier breakdown
        fputcsv($fp, ['COACH TIER BREAKDOWN']);
        fputcsv($fp, ['Tier', 'Coach Count', 'Total Commission (CHF)', 'Avg per Coach (CHF)']);
        foreach ($report['tier_breakdown'] as $tier) {
            $avg_per_coach = $tier->coach_count > 0 ? $tier->tier_commission / $tier->coach_count : 0;
            fputcsv($fp, [
                $tier->tier,
                number_format($tier->coach_count),
                number_format($tier->tier_commission, 2),
                number_format($avg_per_coach, 2)
            ]);
        }
        fputcsv($fp, []);
        
        // Growth metrics
        fputcsv($fp, ['GROWTH METRICS']);
        $trends = InterSoccer_Commission_Calculator::get_commission_trends(6);
        fputcsv($fp, ['Month', 'Referrals', 'Commission (CHF)', 'Avg Commission (CHF)']);
        foreach ($trends as $trend) {
            fputcsv($fp, [
                $trend['month'],
                number_format($trend['referrals']),
                number_format($trend['total_commission'], 2),
                number_format($trend['avg_commission'], 2)
            ]);
        }
        
        fclose($fp);
        exit;
    }

    private function create_demo_coaches() {
        $coach_names = [
            'Marcus Mueller', 'Sandra Weber', 'Thomas Fischer', 'Anna Schmidt',
            'Michael Schneider', 'Lisa Wagner', 'David Becker', 'Sarah Schulz',
            'Andreas Koch', 'Julia Richter'
        ];

        foreach ($coach_names as $name) {
            $names = explode(' ', $name);
            $username = strtolower($names[0] . '_' . $names[1]);
            $email = strtolower($names[0] . '.' . $names[1]) . '@intersoccer-demo.com';
            
            $user_id = wp_create_user($username, 'demo123', $email);
            if (!is_wp_error($user_id)) {
                wp_update_user([
                    'ID' => $user_id,
                    'display_name' => $name,
                    'first_name' => $names[0],
                    'last_name' => $names[1],
                    'role' => 'coach'
                ]);
                
                // Add demo user meta
                update_user_meta($user_id, 'intersoccer_demo_user', '1');
                update_user_meta($user_id, 'intersoccer_credits', rand(50, 500));
                
                // Generate referral code
                InterSoccer_Referral_Handler::generate_coach_referral_link($user_id);
            }
        }
    }

    private function create_demo_customers() {
        $customer_names = [
            'Max Mustermann', 'Anna Beispiel', 'Hans Test', 'Maria Demo',
            'Peter Sample', 'Eva Probe', 'Klaus Muster', 'Anja Beispiel'
        ];

        foreach ($customer_names as $name) {
            $names = explode(' ', $name);
            $username = strtolower($names[0] . '_' . $names[1] . '_' . rand(1000, 9999));
            $email = strtolower($names[0] . '.' . $names[1]) . rand(1, 100) . '@customer-demo.com';
            
            $user_id = wp_create_user($username, 'demo123', $email);
            if (!is_wp_error($user_id)) {
                wp_update_user([
                    'ID' => $user_id,
                    'display_name' => $name,
                    'first_name' => $names[0],
                    'last_name' => $names[1],
                    'role' => 'customer'
                ]);
                
                update_user_meta($user_id, 'intersoccer_demo_user', '1');
                update_user_meta($user_id, 'intersoccer_customer_credits', rand(0, 100));
            }
        }
    }

    private function create_demo_referrals() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        
        $coaches = get_users(['role' => 'coach', 'meta_key' => 'intersoccer_demo_user']);
        $customers = get_users(['role' => 'customer', 'meta_key' => 'intersoccer_demo_user']);
        
        for ($i = 0; $i < 50; $i++) {
            $coach = $coaches[array_rand($coaches)];
            $customer = $customers[array_rand($customers)];
            
            $wpdb->insert($table_name, [
                'coach_id' => $coach->ID,
                'customer_id' => $customer->ID,
                'order_id' => rand(1000, 9999),
                'commission_amount' => rand(10, 200),
                'status' => 'completed',
                'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 90) . ' days'))
            ]);
        }
    }

    private function get_dashboard_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        
        return [
            'total_coaches' => count(get_users(['role' => 'coach'])),
            'new_coaches_this_month' => count(get_users(['role' => 'coach', 'date_query' => [['after' => '1 month ago']]])),
            'total_referrals' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name"),
            'new_referrals_this_month' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"),
            'total_commissions' => $wpdb->get_var("SELECT SUM(commission_amount) FROM $table_name"),
            'commissions_this_month' => $wpdb->get_var("SELECT SUM(commission_amount) FROM $table_name WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"),
            'conversion_rate' => 15.8, // Placeholder
            'conversion_change' => 2.3   // Placeholder
        ];
    }

    private function get_recent_referrals($limit = 10) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT r.*, u.display_name as coach_name 
            FROM $table_name r 
            LEFT JOIN {$wpdb->users} u ON r.coach_id = u.ID 
            ORDER BY r.created_at DESC 
            LIMIT %d
        ", $limit));
    }

    private function get_top_coaches($limit = 5) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                r.coach_id,
                u.display_name,
                COUNT(r.id) as referral_count,
                SUM(r.commission_amount) as total_commission
            FROM $table_name r 
            LEFT JOIN {$wpdb->users} u ON r.coach_id = u.ID 
            GROUP BY r.coach_id 
            ORDER BY total_commission DESC 
            LIMIT %d
        ", $limit));
    }

    private function get_all_coaches() {
        // Debug logging
        error_log('InterSoccer: Fetching all coaches...');
        
        // Get all users with coach role
        $coach_users = get_users([
            'role' => 'coach',
            'orderby' => 'display_name',
            'order' => 'ASC',
            'number' => -1  // Get all coaches
        ]);
        
        error_log('InterSoccer: Found ' . count($coach_users) . ' coach users');
        
        if (empty($coach_users)) {
            // Fallback: get users with coach role in capabilities
            $coach_users = get_users([
                'meta_query' => [
                    [
                        'key' => 'wp_capabilities',
                        'value' => 'coach',
                        'compare' => 'LIKE'
                    ]
                ]
            ]);
            error_log('InterSoccer: Fallback found ' . count($coach_users) . ' coaches with capabilities');
        }
        
        global $wpdb;
        $referrals_table = $wpdb->prefix . 'intersoccer_referrals';
        $coaches = [];
        
        foreach ($coach_users as $user) {
            // Verify user has coach role
            if (!in_array('coach', $user->roles)) {
                error_log('InterSoccer: User ' . $user->ID . ' missing coach role, skipping');
                continue;
            }
            
            // Get referral stats for this coach
            $referral_stats = $wpdb->get_row($wpdb->prepare("
                SELECT 
                    COUNT(*) as referral_count,
                    COALESCE(SUM(commission_amount + COALESCE(loyalty_bonus, 0) + COALESCE(retention_bonus, 0)), 0) as total_commission
                FROM $referrals_table 
                WHERE coach_id = %d AND status IN ('completed', 'approved', 'paid')
            ", $user->ID));
            
            // Get coach credits
            $credits = get_user_meta($user->ID, 'intersoccer_credits', true) ?: 0;
            
            // Build coach object
            $coach = new stdClass();
            $coach->ID = $user->ID;
            $coach->display_name = $user->display_name ?: ($user->first_name . ' ' . $user->last_name);
            $coach->user_email = $user->user_email;
            $coach->first_name = $user->first_name;
            $coach->last_name = $user->last_name;
            $coach->referral_count = (int) ($referral_stats ? $referral_stats->referral_count : 0);
            $coach->total_commission = (float) ($referral_stats ? $referral_stats->total_commission : 0);
            $coach->credits = (float) $credits;
            
            // Add coach-specific meta
            $coach->phone = get_user_meta($user->ID, 'intersoccer_phone', true);
            $coach->specialization = get_user_meta($user->ID, 'intersoccer_specialization', true);
            $coach->location = get_user_meta($user->ID, 'intersoccer_location', true);
            $coach->experience_years = get_user_meta($user->ID, 'intersoccer_experience_years', true);
            $coach->bio = get_user_meta($user->ID, 'intersoccer_bio', true);
            $coach->join_date = $user->user_registered;
            
            $coaches[] = $coach;
            error_log('InterSoccer: Added coach ' . $coach->display_name . ' (ID: ' . $coach->ID . ')');
        }
        
        error_log('InterSoccer: Returning ' . count($coaches) . ' processed coaches');
        return $coaches;
    }

    private function get_performance_chart_data() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i months"));
            $month = date('M Y', strtotime("-$i months"));
            
            $referrals = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM $table_name 
                WHERE created_at >= %s AND created_at < %s + INTERVAL 1 MONTH
            ", $date, $date));
            
            $commissions = $wpdb->get_var($wpdb->prepare("
                SELECT SUM(commission_amount) FROM $table_name 
                WHERE created_at >= %s AND created_at < %s + INTERVAL 1 MONTH
            ", $date, $date));
            
            $data[] = [
                'month' => $month,
                'referrals' => (int) $referrals,
                'commissions' => (float) $commissions
            ];
        }
        
        return $data;
    }

    private function get_coach_tier($referral_count) {
        if ($referral_count >= 20) return 'Platinum';
        if ($referral_count >= 10) return 'Gold';
        if ($referral_count >= 5) return 'Silver';
        return 'Bronze';
    }

    private function get_coach_tier_badge($referral_count) {
        $tier = $this->get_coach_tier($referral_count);
        $colors = [
            'Bronze' => '#CD7F32',
            'Silver' => '#C0C0C0', 
            'Gold' => '#FFD700',
            'Platinum' => '#E5E4E2'
        ];
        
        return '<span class="tier-badge" style="background-color: ' . $colors[$tier] . ';">' . $tier . '</span>';
    }

    public function handle_settings() {
        register_setting('intersoccer_settings', 'intersoccer_commission_first');
        register_setting('intersoccer_settings', 'intersoccer_commission_second');
        register_setting('intersoccer_settings', 'intersoccer_commission_third');
    }
    
    /**
     * Get coach referrals with enhanced data
     */
    private function get_coach_referrals($limit = 50) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                r.*,
                u1.display_name as coach_name,
                u1.user_email as coach_email,
                u2.display_name as customer_name,
                u2.user_email as customer_email
            FROM $table_name r 
            LEFT JOIN {$wpdb->users} u1 ON r.coach_id = u1.ID 
            LEFT JOIN {$wpdb->users} u2 ON r.customer_id = u2.ID 
            ORDER BY r.created_at DESC 
            LIMIT %d
        ", $limit));
    }
    
    /**
     * Get customer referrals (customer-to-customer)
     */
    private function get_customer_referrals($limit = 50) {
        global $wpdb;
        
        // Get customer referral data from user meta
        $customer_referrals = $wpdb->get_results("
            SELECT 
                u1.ID as referrer_id,
                u1.display_name as referrer_name,
                u1.user_email as referrer_email,
                um1.meta_value as referred_user_id,
                um2.meta_value as credits_awarded,
                um3.meta_value as date_referred
            FROM {$wpdb->users} u1
            INNER JOIN {$wpdb->usermeta} um1 ON u1.ID = um1.user_id AND um1.meta_key = 'intersoccer_customer_referrals'
            LEFT JOIN {$wpdb->usermeta} um2 ON u1.ID = um2.user_id AND um2.meta_key = 'intersoccer_referral_credits_earned'
            LEFT JOIN {$wpdb->usermeta} um3 ON u1.ID = um3.user_id AND um3.meta_key = 'intersoccer_last_referral_date'
            WHERE um1.meta_value != ''
            ORDER BY um3.meta_value DESC
            LIMIT $limit
        ");
        
        // Enhanced data for each referral
        $enhanced_referrals = [];
        foreach ($customer_referrals as $referral) {
            if ($referral->referred_user_id) {
                $referred_user = get_user_by('ID', $referral->referred_user_id);
                if ($referred_user) {
                    $enhanced_referral = new stdClass();
                    $enhanced_referral->referrer_id = $referral->referrer_id;
                    $enhanced_referral->referrer_name = $referral->referrer_name;
                    $enhanced_referral->referrer_email = $referral->referrer_email;
                    $enhanced_referral->referred_name = $referred_user->display_name;
                    $enhanced_referral->referred_email = $referred_user->user_email;
                    $enhanced_referral->credits_awarded = $referral->credits_awarded ?: 50;
                    $enhanced_referral->credits_used = get_user_meta($referral->referrer_id, 'intersoccer_credits_used_total', true) ?: 0;
                    $enhanced_referral->status = 'active';
                    $enhanced_referral->created_at = $referral->date_referred ?: current_time('mysql');
                    
                    $enhanced_referrals[] = $enhanced_referral;
                }
            }
        }
        
        return $enhanced_referrals;
    }

    /**
     * Secure AJAX handler for coach messaging
     */
    public function send_coach_message() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $coach_id = intval($_POST['coach_id']);
        $subject = sanitize_text_field($_POST['subject']);
        $message = sanitize_textarea_field($_POST['message']);
        
        $coach = get_user_by('ID', $coach_id);
        if (!$coach || !in_array('coach', $coach->roles)) {
            wp_send_json_error(['message' => 'Invalid coach ID']);
        }
        
        $sent = wp_mail($coach->user_email, $subject, $message);
        
        if ($sent) {
            error_log("Message sent to coach {$coach_id}: {$subject}");
            wp_send_json_success(['message' => 'Message sent successfully']);
        } else {
            error_log("Failed to send message to coach {$coach_id}");
            wp_send_json_error(['message' => 'Failed to send message']);
        }
    }
    
    /**
     * Secure AJAX handler for coach deactivation
     */
    public function deactivate_coach() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $coach_id = intval($_POST['coach_id']);
        $coach = get_user_by('ID', $coach_id);
        
        if (!$coach || !in_array('coach', $coach->roles)) {
            wp_send_json_error(['message' => 'Invalid coach ID']);
        }
        
        // Remove coach role but keep user account
        $coach->remove_role('coach');
        update_user_meta($coach_id, 'intersoccer_deactivated', current_time('mysql'));
        
        error_log("Coach {$coach_id} deactivated by " . get_current_user_id());
        wp_send_json_success(['message' => 'Coach deactivated successfully']);
    }

     /**
     * Get financial overview data
     */
    private function get_financial_overview() {
        global $wpdb;
        
        // Get all customer credits
        $customer_credits = $wpdb->get_results("
            SELECT 
                user_id,
                meta_value as credits
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'intersoccer_customer_credits' 
            AND meta_value > 0
        ");
        
        $total_active_credits = array_sum(array_column($customer_credits, 'credits'));
        
        // Get redeemed credits from order meta
        $total_redeemed = $wpdb->get_var("
            SELECT COALESCE(SUM(meta_value), 0) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_intersoccer_credits_used'
        ") ?: 0;
        
        // Get coach earnings
        $referrals_table = $wpdb->prefix . 'intersoccer_referrals';
        $coach_earnings = $wpdb->get_var("
            SELECT COALESCE(SUM(commission_amount + COALESCE(loyalty_bonus, 0) + COALESCE(retention_bonus, 0)), 0)
            FROM $referrals_table 
            WHERE status IN ('approved', 'paid', 'completed')
        ") ?: 0;
        
        return [
            'total_active_credits' => $total_active_credits,
            'total_redeemed_credits' => $total_redeemed,
            'total_coach_earnings' => $coach_earnings,
            'net_financial_impact' => $total_active_credits + $total_redeemed + $coach_earnings
        ];
    }

    /**
     * Get customer credits breakdown
     */
    private function get_customer_credits_breakdown() {
        global $wpdb;
        
        return $wpdb->get_results("
            SELECT 
                u.ID,
                u.display_name,
                u.user_email,
                u.user_registered,
                COALESCE(credits.meta_value, 0) as current_credits,
                COALESCE(earned.meta_value, 0) as credits_earned,
                COALESCE(used_total.total_used, 0) as credits_used,
                activity.last_activity
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} credits ON u.ID = credits.user_id AND credits.meta_key = 'intersoccer_customer_credits'
            LEFT JOIN {$wpdb->usermeta} earned ON u.ID = earned.user_id AND earned.meta_key = 'intersoccer_total_credits_earned'
            LEFT JOIN (
                SELECT 
                    customer_id,
                    SUM(CAST(pm.meta_value as DECIMAL(10,2))) as total_used
                FROM {$wpdb->prefix}intersoccer_referrals r
                JOIN {$wpdb->postmeta} pm ON r.order_id = pm.post_id AND pm.meta_key = '_intersoccer_credits_used'
                GROUP BY customer_id
            ) used_total ON u.ID = used_total.customer_id
            LEFT JOIN (
                SELECT 
                    user_id,
                    MAX(meta_value) as last_activity
                FROM {$wpdb->usermeta} 
                WHERE meta_key = 'intersoccer_last_activity'
                GROUP BY user_id
            ) activity ON u.ID = activity.user_id
            WHERE (credits.meta_value IS NOT NULL AND credits.meta_value > 0) 
               OR earned.meta_value IS NOT NULL 
               OR used_total.total_used IS NOT NULL
            ORDER BY CAST(COALESCE(credits.meta_value, 0) as DECIMAL(10,2)) DESC
        ");
    }
}