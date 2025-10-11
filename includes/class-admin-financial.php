<?php
// includes/class-admin-financial.php

class InterSoccer_Admin_Financial {

    public function render_financial_report_page() {
        $financial_data = $this->get_financial_report_data();
        ?>
        <div class="wrap intersoccer-admin">
            <h1 class="wp-heading-inline">Financial Report</h1>

            <div class="intersoccer-financial-summary">
                <div class="financial-card">
                    <h3>Total Revenue</h3>
                    <div class="amount"><?php echo number_format($financial_data['total_revenue'], 0); ?> CHF</div>
                </div>
                <div class="financial-card">
                    <h3>Total Costs</h3>
                    <div class="amount"><?php echo number_format($financial_data['total_costs'], 0); ?> CHF</div>
                </div>
                <div class="financial-card">
                    <h3>Net Profit</h3>
                    <div class="amount <?php echo $financial_data['net_profit'] >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo number_format($financial_data['net_profit'], 0); ?> CHF
                    </div>
                </div>
                <div class="financial-card">
                    <h3>Active Credits</h3>
                    <div class="amount"><?php echo number_format($financial_data['active_credits'], 0); ?> CHF</div>
                </div>
            </div>

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

        <style>
        .intersoccer-financial-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .financial-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .financial-card h3 {
            margin: 0 0 15px 0;
            color: #7f8c8d;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .financial-card .amount {
            font-size: 28px;
            font-weight: 700;
            color: #2c3e50;
        }

        .financial-card .amount.positive { color: #27ae60; }
        .financial-card .amount.negative { color: #e74c3c; }

        .intersoccer-export-actions {
            margin: 20px 0;
        }

        .intersoccer-financial-details {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        </style>
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

        $active_credits = $wpdb->get_var("
            SELECT COALESCE(SUM(meta_value), 0)
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'intersoccer_customer_credits'
            AND meta_value > 0
        ");

        return [
            'total_revenue' => (float)$total_revenue,
            'total_costs' => (float)$total_costs,
            'net_profit' => (float)($total_revenue - $total_costs),
            'active_credits' => (float)$active_credits
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
                SUM(CASE WHEN table_name = 'redemptions' THEN amount ELSE 0 END) as costs
            FROM (
                SELECT 'credits' as table_name, credit_amount as amount, created_at
                FROM {$wpdb->prefix}intersoccer_referral_credits
                UNION ALL
                SELECT 'redemptions' as table_name, credit_amount as amount, created_at
                FROM {$wpdb->prefix}intersoccer_credit_redemptions
            ) as combined
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC
            LIMIT 12
        ");

        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Revenue</th>
                    <th>Costs</th>
                    <th>Net Profit</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($monthly_data as $data): ?>
                <tr>
                    <td><?php echo date('F Y', strtotime($data->month . '-01')); ?></td>
                    <td><?php echo number_format($data->revenue, 0); ?> CHF</td>
                    <td><?php echo number_format($data->costs, 0); ?> CHF</td>
                    <td class="<?php echo ($data->revenue - $data->costs) >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo number_format($data->revenue - $data->costs, 0); ?> CHF
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <style>
        .positive { color: #27ae60; font-weight: 600; }
        .negative { color: #e74c3c; font-weight: 600; }
        </style>
        <?php
    }
}