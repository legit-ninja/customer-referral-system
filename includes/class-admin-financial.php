<?php
// includes/class-admin-financial.php

class InterSoccer_Admin_Financial {

    public function render_financial_report_page() {
        $financial_data = $this->get_financial_report_data();
        $has_data = ($financial_data['total_revenue'] > 0 || $financial_data['total_costs'] > 0 || $financial_data['points_balance'] > 0);
        ?>
        <div class="wrap intersoccer-admin">
            <h1 class="wp-heading-inline"><?php esc_html_e('Financial Report', 'intersoccer-referral'); ?></h1>

            <?php if (!$has_data): ?>
            <div class="intersoccer-notice intersoccer-notice-empty">
                <span class="dashicons dashicons-info-outline"></span>
                <p><?php esc_html_e('No financial data available yet. Financial figures will appear once there are transactions in the system.', 'intersoccer-referral'); ?></p>
            </div>
            <?php endif; ?>

            <div class="intersoccer-financial-summary" data-loading="false">
                <div class="financial-card" data-source="intersoccer_referral_credits">
                    <h3><?php esc_html_e('Total Revenue', 'intersoccer-referral'); ?></h3>
                    <div class="amount"><?php echo esc_html(number_format($financial_data['total_revenue'], 0)); ?> <span class="currency-label">CHF</span></div>
                    <div class="source-info" title="<?php esc_attr_e('Source: intersoccer_referral_credits.credit_amount', 'intersoccer-referral'); ?>">
                        <span class="dashicons dashicons-database"></span>
                    </div>
                </div>
                <div class="financial-card" data-source="intersoccer_credit_redemptions">
                    <h3><?php esc_html_e('Total Costs', 'intersoccer-referral'); ?></h3>
                    <div class="amount"><?php echo esc_html(number_format($financial_data['total_costs'], 0)); ?> <span class="currency-label">CHF</span></div>
                    <div class="source-info" title="<?php esc_attr_e('Source: intersoccer_credit_redemptions.credit_amount', 'intersoccer-referral'); ?>">
                        <span class="dashicons dashicons-database"></span>
                    </div>
                </div>
                <div class="financial-card" data-source="computed">
                    <h3><?php esc_html_e('Net Profit', 'intersoccer-referral'); ?></h3>
                    <div class="amount <?php echo $financial_data['net_profit'] >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo esc_html(number_format($financial_data['net_profit'], 0)); ?> <span class="currency-label">CHF</span>
                    </div>
                    <div class="source-info" title="<?php esc_attr_e('Computed: Total Revenue - Total Costs', 'intersoccer-referral'); ?>">
                        <span class="dashicons dashicons-calculator"></span>
                    </div>
                </div>
                <div class="financial-card" data-source="intersoccer_points_balance">
                    <h3><?php esc_html_e('Active Points Liability', 'intersoccer-referral'); ?></h3>
                    <div class="amount"><?php echo esc_html(number_format($financial_data['active_credits'], 0)); ?> <span class="currency-label">PTS</span></div>
                    <div class="source-info" title="<?php esc_attr_e('Source: usermeta.intersoccer_points_balance (canonical)', 'intersoccer-referral'); ?>">
                        <span class="dashicons dashicons-database"></span>
                    </div>
                </div>
                <div class="financial-card" data-source="intersoccer_points_log">
                    <h3><?php esc_html_e('Total Points Balance', 'intersoccer-referral'); ?></h3>
                    <div class="amount"><?php echo esc_html(number_format($financial_data['points_balance'], 0)); ?> <span class="currency-label">PTS</span></div>
                    <div class="source-info" title="<?php esc_attr_e('Source: intersoccer_points_log latest balance per customer', 'intersoccer-referral'); ?>">
                        <span class="dashicons dashicons-database"></span>
                    </div>
                </div>
                <div class="financial-card" data-source="intersoccer_points_log">
                    <h3><?php esc_html_e('Total Points Earned', 'intersoccer-referral'); ?></h3>
                    <div class="amount"><?php echo esc_html(number_format($financial_data['points_earned'], 0)); ?> <span class="currency-label">PTS</span></div>
                    <div class="source-info" title="<?php esc_attr_e('Source: intersoccer_points_log (positive amounts)', 'intersoccer-referral'); ?>">
                        <span class="dashicons dashicons-database"></span>
                    </div>
                </div>
            </div>

            <?php
            $liability_mismatch = abs($financial_data['active_credits'] - $financial_data['points_balance']);
            if ($liability_mismatch > 0):
            ?>
            <div class="intersoccer-notice intersoccer-notice-warning" style="margin-top: 16px;">
                <span class="dashicons dashicons-warning"></span>
                <p>
                    <?php
                    printf(
                        esc_html__('Points balance mismatch: Active Points Liability (%1$s PTS) differs from Total Points Balance (%2$s PTS) by %3$s PTS. Consider running a balance synchronization.', 'intersoccer-referral'),
                        number_format($financial_data['active_credits'], 0),
                        number_format($financial_data['points_balance'], 0),
                        number_format($liability_mismatch, 0)
                    );
                    ?>
                </p>
            </div>
            <?php endif; ?>

            <div class="intersoccer-export-actions">
                <button class="button button-primary" id="export-financial-report">
                    <span class="dashicons dashicons-download"></span>
                    Export Financial Report
                </button>
            </div>

            <div class="intersoccer-financial-details">
                <h2>Monthly Breakdown</h2>
                <?php $this->display_financial_breakdown(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get financial report data
     */
    private function get_financial_report_data() {
        global $wpdb;

        $total_revenue = $wpdb->get_var("
            SELECT COALESCE(SUM(credit_amount), 0)
            FROM {$wpdb->prefix}intersoccer_referral_credits
        ");

        $total_costs = $wpdb->get_var("
            SELECT COALESCE(SUM(credit_amount), 0)
            FROM {$wpdb->prefix}intersoccer_credit_redemptions
        ");

        // Canonical source: intersoccer_points_balance (not legacy intersoccer_customer_credits)
        // Per issue #24 AC §2: Points balance must match intersoccer_points_balance
        $active_credits = $wpdb->get_var("
            SELECT COALESCE(SUM(meta_value), 0)
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'intersoccer_points_balance'
            AND meta_value > 0
        ");

        $points_balance = $wpdb->get_var("
            SELECT COALESCE(SUM(points_balance), 0) FROM (
                SELECT points_balance FROM {$wpdb->prefix}intersoccer_points_log pl1
                WHERE created_at = (
                    SELECT MAX(created_at) FROM {$wpdb->prefix}intersoccer_points_log pl2
                    WHERE pl2.customer_id = pl1.customer_id
                )
                GROUP BY customer_id
            ) as latest_balances
        ");

        $points_earned = $wpdb->get_var("
            SELECT COALESCE(SUM(points_amount), 0)
            FROM {$wpdb->prefix}intersoccer_points_log
            WHERE points_amount > 0
        ");

        return [
            'total_revenue' => (float)$total_revenue,
            'total_costs' => (float)$total_costs,
            'net_profit' => (float)($total_revenue - $total_costs),
            'active_credits' => (float)$active_credits,
            'points_balance' => (float)$points_balance,
            'points_earned' => (float)$points_earned
        ];
    }

    /**
     * Display financial breakdown table
     */
    private function display_financial_breakdown() {
        global $wpdb;

        $monthly_data = $wpdb->get_results("
            SELECT
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(CASE WHEN table_name = 'credits' THEN amount ELSE 0 END) as revenue,
                SUM(CASE WHEN table_name = 'redemptions' THEN amount ELSE 0 END) as costs,
                SUM(CASE WHEN table_name = 'points_earned' THEN amount ELSE 0 END) as points_earned,
                SUM(CASE WHEN table_name = 'points_spent' THEN ABS(amount) ELSE 0 END) as points_spent
            FROM (
                SELECT 'credits' as table_name, credit_amount as amount, created_at
                FROM {$wpdb->prefix}intersoccer_referral_credits
                UNION ALL
                SELECT 'redemptions' as table_name, credit_amount as amount, created_at
                FROM {$wpdb->prefix}intersoccer_credit_redemptions
                UNION ALL
                SELECT 'points_earned' as table_name, points_amount as amount, created_at
                FROM {$wpdb->prefix}intersoccer_points_log
                WHERE points_amount > 0
                UNION ALL
                SELECT 'points_spent' as table_name, points_amount as amount, created_at
                FROM {$wpdb->prefix}intersoccer_points_log
                WHERE points_amount < 0
            ) as combined
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
            LIMIT 12
        ");

        ?>
        <?php if (empty($monthly_data)): ?>
        <div class="intersoccer-notice intersoccer-notice-empty">
            <span class="dashicons dashicons-calendar-alt"></span>
            <p><?php esc_html_e('No monthly data available. Monthly breakdown will appear once there are transactions.', 'intersoccer-referral'); ?></p>
        </div>
        <?php else: ?>
        <table class="wp-list-table widefat fixed striped" role="grid" aria-label="<?php esc_attr_e('Monthly Financial Breakdown', 'intersoccer-referral'); ?>">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e('Month', 'intersoccer-referral'); ?></th>
                    <th scope="col"><?php esc_html_e('Revenue (CHF)', 'intersoccer-referral'); ?></th>
                    <th scope="col"><?php esc_html_e('Costs (CHF)', 'intersoccer-referral'); ?></th>
                    <th scope="col"><?php esc_html_e('Net Profit (CHF)', 'intersoccer-referral'); ?></th>
                    <th scope="col"><?php esc_html_e('Points Earned (PTS)', 'intersoccer-referral'); ?></th>
                    <th scope="col"><?php esc_html_e('Points Spent (PTS)', 'intersoccer-referral'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($monthly_data as $data): ?>
                <tr>
                    <td><?php echo esc_html(date_i18n('F Y', strtotime($data->month . '-01'))); ?></td>
                    <td><?php echo esc_html(number_format($data->revenue, 0)); ?></td>
                    <td><?php echo esc_html(number_format($data->costs, 0)); ?></td>
                    <td class="<?php echo ($data->revenue - $data->costs) >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo esc_html(number_format($data->revenue - $data->costs, 0)); ?>
                    </td>
                    <td><?php echo esc_html(number_format($data->points_earned, 0)); ?></td>
                    <td><?php echo esc_html(number_format($data->points_spent, 0)); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php
    }
}