<?php
// includes/class-admin-referrals.php

class InterSoccer_Admin_Referrals {

    public function render_coach_referrals_page() {
        ?>
        <div class="wrap intersoccer-admin">
            <h1 class="wp-heading-inline">Coach Referrals</h1>

            <div class="intersoccer-filters">
                <select id="coach-filter">
                    <option value="">All Coaches</option>
                    <?php
                    $coaches = get_users(['role' => 'coach']);
                    foreach ($coaches as $coach) {
                        echo '<option value="' . $coach->ID . '">' . esc_html($coach->display_name) . '</option>';
                    }
                    ?>
                </select>
                <input type="date" id="date-from" placeholder="From Date">
                <input type="date" id="date-to" placeholder="To Date">
                <button class="button" id="filter-referrals">Filter</button>
            </div>

            <div class="intersoccer-referrals-table">
                <?php $this->display_coach_referrals_table(); ?>
            </div>
        </div>

        <style>
        .intersoccer-filters {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin: 20px 0;
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .intersoccer-referrals-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        </style>
        <?php
    }

    /**
     * Display coach referrals table
     */
    private function display_coach_referrals_table() {
        global $wpdb;

        $referrals = $wpdb->get_results("
            SELECT r.*, c.display_name as coach_name, u.display_name as customer_name,
                   rc.credit_amount as commission
            FROM {$wpdb->prefix}intersoccer_referrals r
            LEFT JOIN {$wpdb->users} c ON r.coach_id = c.ID
            LEFT JOIN {$wpdb->users} u ON r.customer_id = u.ID
            LEFT JOIN {$wpdb->prefix}intersoccer_referral_credits rc ON r.id = rc.referral_id
            ORDER BY r.created_at DESC
            LIMIT 50
        ");

        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Coach</th>
                    <th>Customer</th>
                    <th>Status</th>
                    <th>Commission</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($referrals as $referral): ?>
                <tr>
                    <td><?php echo date('M j, Y', strtotime($referral->created_at)); ?></td>
                    <td><?php echo esc_html($referral->coach_name); ?></td>
                    <td><?php echo esc_html($referral->customer_name); ?></td>
                    <td>
                        <span class="status-badge <?php echo $referral->status; ?>">
                            <?php echo ucfirst($referral->status); ?>
                        </span>
                    </td>
                    <td><?php echo $referral->commission ? number_format($referral->commission, 0) . ' CHF' : '-'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    public function render_customer_referrals_page() {
        ?>
        <div class="wrap intersoccer-admin">
            <h1 class="wp-heading-inline">Customer Credits</h1>

            <div class="intersoccer-actions">
                <button class="button button-primary" id="import-customer-credits">
                    <span class="dashicons dashicons-upload"></span>
                    Import Customer Credits
                </button>
                <button class="button button-secondary" id="export-customer-credits">
                    <span class="dashicons dashicons-download"></span>
                    Export Customer Credits
                </button>
                <button class="button button-link-delete" id="reset-all-credits">
                    <span class="dashicons dashicons-trash"></span>
                    Reset All Credits
                </button>
            </div>

            <div class="intersoccer-customer-credits">
                <?php $this->display_customer_credits_table(); ?>
            </div>
        </div>

        <style>
        .intersoccer-customer-credits {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        </style>
        <?php
    }

    /**
     * Display customer credits table
     */
    private function display_customer_credits_table() {
        global $wpdb;

        $customers = $wpdb->get_results("
            SELECT u.ID, u.display_name, u.user_email,
                   COALESCE(um.meta_value, 0) as credits
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'intersoccer_customer_credits'
            WHERE um.meta_value > 0 OR EXISTS (
                SELECT 1 FROM {$wpdb->prefix}intersoccer_referral_credits rc WHERE rc.customer_id = u.ID
            )
            ORDER BY CAST(COALESCE(um.meta_value, 0) AS DECIMAL) DESC
            LIMIT 100
        ");

        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Email</th>
                    <th>Current Credits</th>
                    <th>Total Earned</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $customer): ?>
                <tr>
                    <td>
                        <div class="customer-info">
                            <?php echo get_avatar($customer->ID, 32); ?>
                            <strong><?php echo esc_html($customer->display_name); ?></strong>
                        </div>
                    </td>
                    <td><?php echo esc_html($customer->user_email); ?></td>
                    <td><strong><?php echo number_format($customer->credits, 0); ?> CHF</strong></td>
                    <td><?php echo number_format($this->get_customer_total_earned($customer->ID), 0); ?> CHF</td>
                    <td>
                        <button class="button button-small update-credits" data-user-id="<?php echo $customer->ID; ?>">Update</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <style>
        .customer-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .customer-info img {
            border-radius: 50%;
        }
        </style>
        <?php
    }

    /**
     * Get customer total earned credits
     */
    private function get_customer_total_earned($customer_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(credit_amount), 0)
            FROM {$wpdb->prefix}intersoccer_referral_credits
            WHERE customer_id = %d
        ", $customer_id));
    }
}