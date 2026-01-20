<?php
// includes/class-admin-referrals.php

class InterSoccer_Admin_Referrals {

    public function __construct() {
        if (function_exists('add_action')) {
            add_action('admin_post_intersoccer_export_coach_referrals', [$this, 'handle_export_coach_referrals']);
            add_action('admin_post_intersoccer_mark_commission_paid', [$this, 'handle_mark_commission_paid']);
        }
    }

    public function render_coach_referrals_page() {
        if (isset($_GET['referral_notice'])) {
            $notice = sanitize_key($_GET['referral_notice']);
            $message = '';
            $class = 'notice-info';

            if ($notice === 'deleted') {
                $message = __('Duplicate referral entry removed.', 'intersoccer-referral');
                $class = 'notice-success';
            } elseif ($notice === 'delete_error') {
                $message = __('Unable to remove the referral entry. It may already be completed or missing.', 'intersoccer-referral');
                $class = 'notice-error';
            } elseif ($notice === 'commission_paid') {
                $message = __('Commission was marked as paid.', 'intersoccer-referral');
                $class = 'notice-success';
            } elseif ($notice === 'commission_error') {
                $message = __('We could not update the commission status. Please try again.', 'intersoccer-referral');
                $class = 'notice-error';
            } elseif ($notice === 'commission_already_paid') {
                $message = __('This commission has already been marked as paid.', 'intersoccer-referral');
                $class = 'notice-warning';
            }

            if ($message) {
                printf('<div class="notice %1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
            }
        }

        ?>
        <div class="wrap intersoccer-admin">
            <h1 class="wp-heading-inline"><?php esc_html_e('Coach Referrals', 'intersoccer-referral'); ?></h1>

            <div class="intersoccer-filters">
                <div class="filters-group">
                    <select id="coach-filter">
                        <option value=""><?php esc_html_e('All Coaches', 'intersoccer-referral'); ?></option>
                        <?php
                        $coaches = get_users(['role' => 'coach']);
                        foreach ($coaches as $coach) {
                            echo '<option value="' . esc_attr($coach->ID) . '">' . esc_html($coach->display_name) . '</option>';
                        }
                        ?>
                    </select>
                    <input type="date" id="date-from" placeholder="<?php esc_attr_e('From Date', 'intersoccer-referral'); ?>">
                    <input type="date" id="date-to" placeholder="<?php esc_attr_e('To Date', 'intersoccer-referral'); ?>">
                    <button class="button" id="filter-referrals"><?php esc_html_e('Filter', 'intersoccer-referral'); ?></button>
                </div>
                <form class="coach-referrals-export" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('intersoccer_export_coach_referrals', 'intersoccer_export_coach_referrals_nonce'); ?>
                    <input type="hidden" name="action" value="intersoccer_export_coach_referrals">
                    <input type="hidden" name="export_limit" value="500">
                    <button type="submit" class="button button-secondary">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e('Export to Excel', 'intersoccer-referral'); ?>
                    </button>
                </form>
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
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
            justify-content: space-between;
        }

        .intersoccer-filters .filters-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }

        .coach-referrals-export {
            margin-left: auto;
        }

        .coach-referrals-export .dashicons {
            margin-right: 4px;
        }

        .intersoccer-referrals-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .intersoccer-referrals-table table th,
        .intersoccer-referrals-table table td {
            vertical-align: top;
        }

        .intersoccer-referrals-table table td {
            line-height: 1.4;
        }

        .intersoccer-referrals-table .muted {
            color: #6b7280;
        }

        .intersoccer-referrals-table .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .intersoccer-referrals-table .status-badge.completed {
            background: #dcfce7;
            color: #166534;
        }

        .intersoccer-referrals-table .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .intersoccer-referrals-table .status-badge.failed,
        .intersoccer-referrals-table .status-badge.ineligible {
            background: #fee2e2;
            color: #991b1b;
        }

        .duplicate-flag {
            display: inline-block;
            margin-top: 4px;
            padding: 2px 6px;
            border-radius: 4px;
            background: #fef3c7;
            color: #92400e;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .actions-column form {
            display: inline;
        }

        .actions-column .muted {
            color: #999;
        }

        .payout-controls {
            margin-bottom: 8px;
        }

        .payout-status {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            font-size: 12px;
        }

        .payout-status .amount {
            font-weight: 600;
        }

        .payout-status small {
            color: #6b7280;
        }

        .payout-badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            font-weight: 600;
        }

        .payout-badge.paid {
            background: #dcfce7;
            color: #166534;
        }

        .payout-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .payout-form {
            margin-top: 4px;
        }

        .payout-form .button-small {
            line-height: 1.8;
        }

        .returning-column .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .returning-column .badge.new {
            background: #ecfdf5;
            color: #047857;
        }
        .returning-column .badge.returning {
            background: #eef2ff;
            color: #4338ca;
        }
        .eligibility-column small.meta {
            display: block;
            margin-top: 4px;
            color: #6b7280;
            font-size: 11px;
        }
        </style>
        <?php
    }

    /**
     * Retrieve coach referral records with optional filters.
     *
     * @param array<string,mixed> $args
     * @return array<stdClass>
     */
    private function get_coach_referrals(array $args = []) {
        global $wpdb;

        if (!method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_results')) {
            return [];
        }

        $defaults = [
            'limit'       => 50,
            'referral_id' => null,
            'coach_id'    => null,
            'date_from'   => null,
            'date_to'     => null,
        ];

        $args  = wp_parse_args($args, $defaults);
        $limit = max(1, (int) $args['limit']);

        $conditions = ['1=1'];
        $params     = [];

        if (!empty($args['referral_id'])) {
            $conditions[] = 'r.id = %d';
            $params[]     = (int) $args['referral_id'];
        }

        if (!empty($args['coach_id'])) {
            $conditions[] = 'r.coach_id = %d';
            $params[]     = (int) $args['coach_id'];
        }

        if (!empty($args['date_from'])) {
            $conditions[] = 'r.created_at >= %s';
            $params[]     = sanitize_text_field($args['date_from']);
        }

        if (!empty($args['date_to'])) {
            $conditions[] = 'r.created_at <= %s';
            $params[]     = sanitize_text_field($args['date_to']);
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $sql = "
            SELECT r.*,
                   c.display_name AS coach_name,
                   c.user_email AS coach_email,
                   u.display_name AS customer_name,
                   u.user_email AS customer_email,
                   COALESCE(cc.commission_amount, rc.credit_amount) AS commission,
                   cc.commission_amount AS commission_amount_raw,
                   rc.credit_amount AS credit_amount_raw,
                   rc.id AS credit_id,
                   rc.status AS credit_status,
                   rc.created_at AS credit_created_at,
                   rc.updated_at AS credit_updated_at,
                   cc.id AS commission_id,
                   cc.status AS commission_status,
                   cc.updated_at AS commission_updated_at,
                   pm.meta_value AS eligibility_meta,
                   ot.meta_value AS order_total,
                   oc.meta_value AS order_currency,
                   code.meta_value AS coach_referral_code,
                   (
                       SELECT COUNT(*)
                       FROM {$wpdb->prefix}intersoccer_referrals r2
                       WHERE r2.order_id = r.order_id
                         AND r2.coach_id = r.coach_id
                   ) AS duplicate_count
            FROM {$wpdb->prefix}intersoccer_referrals r
            LEFT JOIN {$wpdb->users} c ON r.coach_id = c.ID
            LEFT JOIN {$wpdb->users} u ON r.customer_id = u.ID
            LEFT JOIN {$wpdb->prefix}intersoccer_referral_credits rc ON r.id = rc.referral_id
            LEFT JOIN {$wpdb->prefix}intersoccer_coach_commissions cc ON cc.order_id = r.order_id AND cc.coach_id = r.coach_id
            LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = r.order_id AND pm.meta_key = '_intersoccer_referral_eligibility'
            LEFT JOIN {$wpdb->postmeta} ot ON ot.post_id = r.order_id AND ot.meta_key = '_order_total'
            LEFT JOIN {$wpdb->postmeta} oc ON oc.post_id = r.order_id AND oc.meta_key = '_order_currency'
            LEFT JOIN {$wpdb->usermeta} code ON code.user_id = r.coach_id AND code.meta_key = 'referral_code'
            $where
            ORDER BY r.created_at DESC
            LIMIT %d
        ";

        $params[] = $limit;
        $prepared = $wpdb->prepare($sql, $params);

        return $wpdb->get_results($prepared);
    }

    /**
     * Display coach referrals table
     */
    private function display_coach_referrals_table() {
        $referrals = $this->get_coach_referrals();

        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Order</th>
                    <th>Coach</th>
                    <th>Coach Email</th>
                    <th>Customer</th>
                    <th>Customer Email</th>
                    <th>Commission</th>
                    <th>Status</th>
                    <th>Eligibility</th>
                    <th>Return Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($referrals as $referral): ?>
                <?php $duplicate_count = isset($referral->duplicate_count) ? (int) $referral->duplicate_count : 0; ?>
                <tr>
                    <td><?php echo date('M j, Y', strtotime($referral->created_at)); ?></td>
                    <td>
                        <?php
                        $order_link = get_edit_post_link($referral->order_id);
                        $order_label = '#' . (int) $referral->order_id;
                        $order_total = $this->format_order_total($referral);

                        if ($order_link) {
                            printf(
                                '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a><br><span class="muted">%s</span>',
                                esc_url($order_link),
                                esc_html($order_label),
                                esc_html($order_total)
                            );
                        } else {
                            printf(
                                '<strong>%s</strong><br><span class="muted">%s</span>',
                                esc_html($order_label),
                                esc_html($order_total)
                            );
                        }
                        ?>
                    </td>
                    <td><?php echo $this->format_coach_display($referral); ?></td>
                    <td><?php echo $this->format_email_display($referral->coach_email ?? ''); ?></td>
                    <td><?php echo $this->format_customer_display($referral); ?></td>
                    <td><?php echo $this->format_email_display($referral->customer_email ?? ''); ?></td>
                    <td><?php echo $this->format_commission_amount($referral); ?></td>
                    <td>
                        <span class="status-badge <?php echo esc_attr($referral->status); ?>">
                            <?php echo esc_html(ucfirst($referral->status)); ?>
                        </span>
                        <?php if ($duplicate_count > 1): ?>
                            <span class="duplicate-flag">
                                <?php esc_html_e('Duplicate detected', 'intersoccer-referral'); ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="eligibility-column">
                        <?php
                        $eligibility_data = $this->normalize_eligibility_data($referral->eligibility_meta);
                        $eligibility_view = $this->prepare_eligibility_view_model($eligibility_data);
                        echo $this->build_eligibility_markup($referral->id, $referral->order_id, $eligibility_view);
                        ?>
                    </td>
                    <td class="returning-column">
                        <?php echo $this->build_returning_customer_markup($eligibility_data); ?>
                    </td>
                    <td class="actions-column">
                        <?php echo $this->build_payout_controls($referral); ?>
                        <?php if ($duplicate_count > 1 && strtolower($referral->status) !== 'completed'): ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('intersoccer_delete_referral_' . $referral->id, '_referral_delete_nonce'); ?>
                                <input type="hidden" name="action" value="intersoccer_delete_referral">
                                <input type="hidden" name="referral_id" value="<?php echo (int) $referral->id; ?>">
                                <button type="submit" class="button button-link-delete">
                                    <?php esc_html_e('Remove Duplicate', 'intersoccer-referral'); ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
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

    /**
     * Handle AJAX updates to referral eligibility overrides
     */
    public function ajax_update_referral_eligibility() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => __('You do not have permission to modify referral eligibility.', 'intersoccer-referral')
            ], 403);
        }

        $referral_id = isset($_POST['referral_id']) ? absint($_POST['referral_id']) : 0;
        $order_id    = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $target      = isset($_POST['target_status']) ? sanitize_key($_POST['target_status']) : '';
        $note        = isset($_POST['note']) ? sanitize_textarea_field($_POST['note']) : '';

        if (!$referral_id || !$order_id || !in_array($target, ['eligible', 'ineligible'], true)) {
            wp_send_json_error([
                'message' => __('Invalid referral eligibility request.', 'intersoccer-referral')
            ], 400);
        }

        $current_meta = get_post_meta($order_id, '_intersoccer_referral_eligibility', true);
        $eligibility  = $this->normalize_eligibility_data($current_meta);

        $eligibility['eligible'] = ($target === 'eligible');
        $eligibility['reason']   = $target === 'eligible' ? 'manual_override' : 'manual_block';

        $overrides = isset($eligibility['overrides']) && is_array($eligibility['overrides'])
            ? $eligibility['overrides']
            : [];

        $overrides[] = [
            'status' => $target,
            'note' => $note,
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('mysql'),
        ];

        $eligibility['overrides'] = $overrides;

        update_post_meta($order_id, '_intersoccer_referral_eligibility', $eligibility);

        $view = $this->prepare_eligibility_view_model($eligibility);

        wp_send_json_success([
            'html' => $this->build_eligibility_markup($referral_id, $order_id, $view),
            'referral_status' => 'pending',
            'referral_status_label' => __('Pending', 'intersoccer-referral'),
        ]);
    }

    /**
     * Normalize stored eligibility metadata into a consistent array structure.
     *
     * @param mixed $raw_meta
     * @return array<string,mixed>
     */
    private function normalize_eligibility_data($raw_meta) {
        $defaults = [
            'eligible' => true,
            'reason' => 'no_history',
            'lookback_months' => (int) get_option('intersoccer_referral_eligibility_months', 18),
            'last_order_id' => null,
            'last_order_date' => null,
            'months_since_last' => null,
            'evaluated_at' => current_time('mysql'),
            'overrides' => [],
        ];

        if (empty($raw_meta)) {
            return $defaults;
        }

        if (is_string($raw_meta)) {
            $maybe = maybe_unserialize($raw_meta);
            if ($maybe === $raw_meta) {
                $json = json_decode($raw_meta, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $maybe = $json;
                }
            }
            $raw_meta = $maybe;
        }

        if (!is_array($raw_meta)) {
            return $defaults;
        }

        $data = array_merge($defaults, $raw_meta);
        $data['eligible'] = !empty($data['eligible']);
        $data['lookback_months'] = isset($data['lookback_months']) ? (int) $data['lookback_months'] : $defaults['lookback_months'];
        $data['months_since_last'] = isset($data['months_since_last']) && $data['months_since_last'] !== null
            ? (int) $data['months_since_last']
            : null;

        if (isset($data['overridden_by']) || isset($data['overridden_at']) || isset($data['manual_note'])) {
            $overrides = isset($data['overrides']) && is_array($data['overrides']) ? $data['overrides'] : [];
            $overrides[] = [
                'status' => $data['eligible'] ? 'eligible' : 'ineligible',
                'note' => isset($data['manual_note']) ? (string) $data['manual_note'] : '',
                'user_id' => isset($data['overridden_by']) ? (int) $data['overridden_by'] : null,
                'timestamp' => $data['overridden_at'] ?? current_time('mysql'),
            ];
            $data['overrides'] = $overrides;
            unset($data['overridden_by'], $data['overridden_at'], $data['manual_note']);
        }

        if (!isset($data['overrides']) || !is_array($data['overrides'])) {
            $data['overrides'] = [];
        }

        return $data;
    }

    /**
     * Prepare eligibility data for display.
     *
     * @param array<string,mixed> $eligibility
     * @return array<string,string|null>
     */
    private function prepare_eligibility_view_model(array $eligibility) {
        $status_label = $eligibility['eligible']
            ? __('Eligible', 'intersoccer-referral')
            : __('Coach commission active', 'intersoccer-referral');

        $status_class = $eligibility['eligible'] ? 'eligible' : 'eligible partial';
        if (!empty($eligibility['reason']) && strpos($eligibility['reason'], 'manual') === 0) {
            $status_class .= ' manual';
        }

        $reason_label = $this->format_eligibility_reason($eligibility);

        $months_text = '';
        if ($eligibility['months_since_last'] !== null) {
            $months_value = (int) $eligibility['months_since_last'];
            $formatted_months = function_exists('number_format_i18n')
                ? number_format_i18n($months_value)
                : number_format($months_value);

            $months_text = sprintf(
                _n('%s month since last purchase', '%s months since last purchase', $eligibility['months_since_last'], 'intersoccer-referral'),
                $formatted_months
            );
        }

        $last_order_label = '';
        $last_order_url = '';
        if (!empty($eligibility['last_order_id'])) {
            $formatted_date = '';
            if (!empty($eligibility['last_order_date'])) {
                $formatted_date = $this->format_datetime($eligibility['last_order_date'], false);
            }

            $last_order_label = sprintf(
                __('Order #%1$s %2$s', 'intersoccer-referral'),
                $eligibility['last_order_id'],
                $formatted_date ? '(' . $formatted_date . ')' : ''
            );

            $admin_base = function_exists('admin_url') ? admin_url('post.php') : (home_url('/wp-admin/post.php'));
            $last_order_url = add_query_arg(
                [
                    'post' => $eligibility['last_order_id'],
                    'action' => 'edit',
                ],
                $admin_base
            );
        }

        $button_target = '';
        $button_label = '';
        $button_variant = 'button-secondary';

        $override_summary = '';
        $override_notes = [];
        $overrides = isset($eligibility['overrides']) && is_array($eligibility['overrides'])
            ? $eligibility['overrides']
            : [];

        if (!empty($overrides)) {
            $latest_override = end($overrides);
            $user = function_exists('get_userdata') && !empty($latest_override['user_id'])
                ? get_userdata((int) $latest_override['user_id'])
                : null;
            $name = $user ? $user->display_name : __('Unknown', 'intersoccer-referral');
            $date = $this->format_datetime($latest_override['timestamp'] ?? '', true);
            $override_summary = sprintf(__('Override by %1$s on %2$s', 'intersoccer-referral'), $name, $date);

            foreach ($overrides as $entry) {
                if (empty($entry['note'])) {
                    continue;
                }
                $note_user = function_exists('get_userdata') && !empty($entry['user_id'])
                    ? get_userdata((int) $entry['user_id'])
                    : null;
                $note_name = $note_user ? $note_user->display_name : __('Unknown', 'intersoccer-referral');
                $note_date = $this->format_datetime($entry['timestamp'] ?? '', true);
                $override_notes[] = sprintf(
                    '%1$s — %2$s: %3$s',
                    $note_date,
                    $note_name,
                    $entry['note']
                );
            }

            if ($eligibility['eligible']) {
                $button_target = 'ineligible';
                $button_label = __('Mark Ineligible', 'intersoccer-referral');
                $button_variant = 'button-link-delete';
            } else {
                $button_target = 'eligible';
                $button_label = __('Mark Eligible', 'intersoccer-referral');
                $button_variant = 'button-primary';
            }
        }

        return [
            'status_label' => $status_label,
            'status_class' => $status_class,
            'reason_label' => $reason_label,
            'reason_code' => $eligibility['reason'] ?? 'unknown',
            'months_text' => $months_text,
            'months_value' => $eligibility['months_since_last'],
            'last_order_label' => $last_order_label,
            'last_order_url' => $last_order_url,
            'button_label' => $button_label,
            'button_target' => $button_target,
            'button_variant' => $button_variant,
            'override_summary' => $override_summary,
            'override_notes' => $override_notes,
        ];
    }

    /**
     * Build HTML markup for the eligibility cell.
     *
     * @param int $referral_id
     * @param int $order_id
     * @param array<string,mixed> $view
     * @return string
     */
    private function build_eligibility_markup($referral_id, $order_id, array $view) {
        ob_start();
        ?>
        <div class="eligibility-status" data-referral-id="<?php echo intval($referral_id); ?>" data-order-id="<?php echo intval($order_id); ?>">
            <div class="eligibility-status__header">
                <span class="eligibility-status__badge <?php echo esc_attr($view['status_class']); ?>">
                    <?php echo esc_html($view['status_label']); ?>
                </span>
                <?php if (!empty($view['override_summary'])): ?>
                    <span class="eligibility-status__override-info"><?php echo esc_html($view['override_summary']); ?></span>
                <?php endif; ?>
            </div>
            <div class="eligibility-status__meta">
                <span class="eligibility-status__reason"><?php echo esc_html($view['reason_label']); ?></span>
                <?php if (!empty($view['months_text'])): ?>
                    <span class="eligibility-status__months"><?php echo esc_html($view['months_text']); ?></span>
                <?php endif; ?>
                <?php if (!empty($view['last_order_label']) && !empty($view['last_order_url'])): ?>
                    <a class="eligibility-status__last-order" href="<?php echo esc_url($view['last_order_url']); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo esc_html($view['last_order_label']); ?>
                    </a>
                <?php endif; ?>
                <?php if (!empty($view['override_notes'])): ?>
                    <div class="eligibility-status__note">
                        <?php foreach ($view['override_notes'] as $entry): ?>
                            <div><?php echo esc_html($entry); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="eligibility-status__actions">
                <?php if (!empty($view['button_label'])): ?>
                    <button
                        class="button button-small <?php echo esc_attr($view['button_variant']); ?> intersoccer-eligibility-override"
                        data-target-status="<?php echo esc_attr($view['button_target']); ?>"
                        data-referral-id="<?php echo intval($referral_id); ?>"
                        data-order-id="<?php echo intval($order_id); ?>"
                    >
                        <?php echo esc_html($view['button_label']); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    private function build_returning_customer_markup(array $eligibility) {
        $meta = $this->get_returning_customer_meta($eligibility);

        ob_start();
        ?>
        <span class="<?php echo esc_attr($meta['class']); ?>">
            <?php echo esc_html($meta['label']); ?>
        </span>
        <?php if (!empty($meta['detail'])): ?>
            <small class="meta"><?php echo esc_html($meta['detail']); ?></small>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Get structured return status data for reuse.
     *
     * @param array<string,mixed> $eligibility
     * @return array{label:string,class:string,detail:string}
     */
    private function get_returning_customer_meta(array $eligibility) {
        $has_prior_order = !empty($eligibility['last_order_id']);
        $months          = isset($eligibility['months_since_last']) ? (int) $eligibility['months_since_last'] : null;
        $reason          = $eligibility['reason'] ?? '';

        if ($reason === 'guest_checkout') {
            $label = __('Guest checkout', 'intersoccer-referral');
            $class = 'badge returning';
        } elseif (!$has_prior_order) {
            $label = __('New customer', 'intersoccer-referral');
            $class = 'badge new';
        } else {
            $label = __('Returning customer', 'intersoccer-referral');
            $class = 'badge returning';
        }

        $detail = '';
        if ($months !== null) {
            $detail = sprintf(
                _n('%d month since last order', '%d months since last order', $months, 'intersoccer-referral'),
                $months
            );
        } elseif ($has_prior_order) {
            $detail = __('Previous order detected', 'intersoccer-referral');
        }

        return [
            'label'  => $label,
            'class'  => $class,
            'detail' => $detail,
        ];
    }

    private function describe_returning_customer_status(array $eligibility) {
        $meta = $this->get_returning_customer_meta($eligibility);
        if (!empty($meta['detail'])) {
            return sprintf('%1$s — %2$s', $meta['label'], $meta['detail']);
        }
        return $meta['label'];
    }

    private function resolve_commission_amount($referral) {
        if (isset($referral->commission_amount_raw) && $referral->commission_amount_raw !== null) {
            return (float) $referral->commission_amount_raw;
        }

        if (isset($referral->credit_amount_raw) && $referral->credit_amount_raw !== null) {
            return (float) $referral->credit_amount_raw;
        }

        if (isset($referral->commission)) {
            return (float) $referral->commission;
        }

        return 0.0;
    }

    private function get_commission_payout_status($referral) {
        if (!empty($referral->commission_status)) {
            return strtolower($referral->commission_status);
        }

        if (!empty($referral->credit_status)) {
            return strtolower($referral->credit_status);
        }

        return 'pending';
    }

    private function is_commission_paid($referral) {
        return $this->get_commission_payout_status($referral) === 'paid';
    }

    private function format_commission_status_label($status) {
        switch ($status) {
            case 'paid':
                return __('Paid', 'intersoccer-referral');
            case 'approved':
            case 'completed':
            case 'active':
            case 'earned':
            default:
                return __('Unpaid', 'intersoccer-referral');
        }
    }

    private function build_payout_controls($referral) {
        $amount    = $this->resolve_commission_amount($referral);
        $status    = $this->get_commission_payout_status($referral);
        $is_paid   = $status === 'paid';
        $label     = $this->format_commission_status_label($status);
        $updated_at = $referral->commission_updated_at ?? $referral->credit_updated_at ?? '';

        ob_start();
        ?>
        <div class="payout-status">
            <span class="payout-badge <?php echo esc_attr($is_paid ? 'paid' : 'pending'); ?>">
                <?php echo esc_html($label); ?>
            </span>
            <?php if ($amount > 0): ?>
                <span class="amount"><?php echo esc_html(sprintf('%s CHF', number_format_i18n($amount, 2))); ?></span>
            <?php endif; ?>
            <?php if ($is_paid && $updated_at): ?>
                <small><?php printf(esc_html__('Updated %s', 'intersoccer-referral'), esc_html($this->format_datetime($updated_at, true))); ?></small>
            <?php endif; ?>
        </div>
        <?php if (!$is_paid): ?>
            <?php if ($amount > 0): ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="payout-form">
                    <?php wp_nonce_field('intersoccer_mark_commission_paid_' . (int) $referral->id, '_commission_paid_nonce'); ?>
                    <input type="hidden" name="action" value="intersoccer_mark_commission_paid">
                    <input type="hidden" name="referral_id" value="<?php echo (int) $referral->id; ?>">
                    <button type="submit" class="button button-primary button-small">
                        <?php esc_html_e('Mark Paid', 'intersoccer-referral'); ?>
                    </button>
                </form>
            <?php else: ?>
                <span class="muted"><?php esc_html_e('No payable commission', 'intersoccer-referral'); ?></span>
            <?php endif; ?>
        <?php endif; ?>
        <?php
        return ob_get_clean();
    }

    /**
     * Format eligibility reason label.
     *
     * @param array<string,mixed> $eligibility
     * @return string
     */
    private function format_eligibility_reason(array $eligibility) {
        $reason = $eligibility['reason'] ?? 'unknown';

        switch ($reason) {
            case 'no_history':
                return __('First-time customer (eligible)', 'intersoccer-referral');
            case 'dormant_customer':
                return __('Dormant customer (eligible)', 'intersoccer-referral');
            case 'recent_purchase':
                return sprintf(
                    __('Recent purchase within %d months', 'intersoccer-referral'),
                    max(1, (int) ($eligibility['lookback_months'] ?? 18))
                );
            case 'guest_checkout':
                return __('Guest checkout (no history)', 'intersoccer-referral');
            case 'rule_disabled':
                return __('Eligibility window disabled', 'intersoccer-referral');
            case 'manual_override':
                return __('Marked eligible (manual override)', 'intersoccer-referral');
            case 'manual_block':
                return __('Marked ineligible (manual override)', 'intersoccer-referral');
            default:
                return __('Eligibility status unknown', 'intersoccer-referral');
        }
    }

    /**
     * Format a datetime string for admin display.
     *
     * @param string $datetime
     * @param bool $include_time
     * @return string
     */
    private function format_datetime($datetime, $include_time = false) {
        if (empty($datetime)) {
            return '';
        }

        $timestamp = strtotime($datetime);
        if (!$timestamp) {
            return $datetime;
        }

        $format = $include_time
            ? get_option('date_format', 'Y-m-d') . ' ' . get_option('time_format', 'H:i')
            : get_option('date_format', 'Y-m-d');

        if (function_exists('date_i18n')) {
            return date_i18n($format, $timestamp);
        }

        return date($format, $timestamp);
    }

    private function format_order_total($referral) {
        $currency = !empty($referral->order_currency) ? $referral->order_currency : get_woocommerce_currency();
        $raw_total = isset($referral->order_total) ? (float) $referral->order_total : null;

        if ($raw_total === null) {
            $order = wc_get_order($referral->order_id);
            if ($order) {
                $currency = $order->get_currency();
                $raw_total = (float) $order->get_total();
            }
        }

        if ($raw_total === null) {
            return __('Unknown total', 'intersoccer-referral');
        }

        return strip_tags(wc_price($raw_total, ['currency' => $currency]));
    }

    private function format_commission_amount($referral) {
        $amount = isset($referral->commission) ? (float) $referral->commission : 0.0;

        if ($amount <= 0 && class_exists('InterSoccer_Commission_Manager')) {
            $order = wc_get_order($referral->order_id);
            if ($order) {
                $manager = InterSoccer_Commission_Manager::get_instance();
                if ($manager && method_exists($manager, 'calculate_total_commission')) {
                    $calculated = $manager->calculate_total_commission(
                        $order,
                        (int) $referral->coach_id,
                        (int) $referral->customer_id,
                        (int) $referral->purchase_count
                    );
                    if (is_array($calculated) && isset($calculated['total_amount'])) {
                        $amount = (float) $calculated['total_amount'];
                    }
                }
            }
        }

        return $amount > 0
            ? sprintf('%s CHF', number_format_i18n($amount, 2))
            : '—';
    }

    private function format_coach_display($referral) {
        $name = '';

        if (!empty($referral->coach_name)) {
            $name = $referral->coach_name;
        } else {
            $coach = get_userdata($referral->coach_id);
            if ($coach) {
                $name = $coach->display_name;
            }
        }

        if (!$name) {
            $name = __('Coach (removed)', 'intersoccer-referral');
        }

        $code = '';
        if (!empty($referral->coach_referral_code)) {
            $code = $referral->coach_referral_code;
        } elseif (class_exists('InterSoccer_Referral_Handler') && method_exists('InterSoccer_Referral_Handler', 'get_coach_referral_code')) {
            $code = InterSoccer_Referral_Handler::get_coach_referral_code($referral->coach_id);
        }

        $output = sprintf('<strong>%s</strong>', esc_html($name));
        if ($code) {
            $output .= sprintf('<br><span class="muted">%s</span>', esc_html($code));
        }

        return $output;
    }

    private function format_customer_display($referral) {
        $name = '';
        $email = '';

        if (!empty($referral->customer_name)) {
            $name = $referral->customer_name;
        }

        if (!empty($referral->customer_email)) {
            $email = $referral->customer_email;
        }

        if (!$name || !$email) {
            $customer = get_userdata($referral->customer_id);
            if ($customer) {
                if (!$name) {
                    $name = $customer->display_name;
                }
                if (!$email) {
                    $email = $customer->user_email;
                }
            }
        }

        if (!$name) {
            $name = __('Customer', 'intersoccer-referral');
        }

        return sprintf('<strong>%s</strong>', esc_html($name));
    }

    private function format_email_display($email) {
        $email = trim((string) $email);
        if (!$email) {
            return '<span class="muted">—</span>';
        }

        $escaped = esc_html($email);
        $mailto = esc_url('mailto:' . $email);

        return sprintf('<a href="%s">%s</a>', $mailto, $escaped);
    }

    public function handle_export_coach_referrals() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to export referrals.', 'intersoccer-referral'));
        }

        if (
            empty($_POST['intersoccer_export_coach_referrals_nonce']) ||
            !wp_verify_nonce($_POST['intersoccer_export_coach_referrals_nonce'], 'intersoccer_export_coach_referrals')
        ) {
            $this->redirect_with_notice('commission_error');
        }

        $limit = isset($_POST['export_limit']) ? absint($_POST['export_limit']) : 500;
        $limit = min(max(1, $limit), 2000);

        $referrals = $this->get_coach_referrals([
            'limit' => $limit,
        ]);

        if (function_exists('nocache_headers')) {
            nocache_headers();
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="coach-referrals-' . gmdate('Ymd-His') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, [
            'Date',
            'Order',
            'Coach',
            'Coach Email',
            'Customer',
            'Customer Email',
            'Commission (CHF)',
            'Referral Status',
            'Eligibility',
            'Returning',
            'Payout Status',
        ]);

        foreach ($referrals as $referral) {
            $eligibility_data = $this->normalize_eligibility_data($referral->eligibility_meta);
            $eligibility_view = $this->prepare_eligibility_view_model($eligibility_data);
            $eligibility_text = $eligibility_view['status_label'];
            if (!empty($eligibility_view['reason_label'])) {
                $eligibility_text .= ' - ' . $eligibility_view['reason_label'];
            }
            if (!empty($eligibility_view['months_text'])) {
                $eligibility_text .= ' (' . $eligibility_view['months_text'] . ')';
            }

            $return_text = $this->describe_returning_customer_status($eligibility_data);
            $commission_amount = $this->resolve_commission_amount($referral);
            $payout_status = $this->format_commission_status_label($this->get_commission_payout_status($referral));

            $date = $referral->created_at ? $this->format_datetime($referral->created_at, true) : '';

            fputcsv($output, [
                $date,
                '#' . (int) $referral->order_id,
                $referral->coach_name,
                $referral->coach_email,
                $referral->customer_name,
                $referral->customer_email,
                number_format($commission_amount, 2),
                ucfirst($referral->status),
                $eligibility_text,
                $return_text,
                $payout_status,
            ]);
        }

        fclose($output);
        exit;
    }

    public function handle_mark_commission_paid() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to update commissions.', 'intersoccer-referral'));
        }

        $referral_id = isset($_POST['referral_id']) ? absint($_POST['referral_id']) : 0;
        $nonce_action = 'intersoccer_mark_commission_paid_' . $referral_id;

        if (!$referral_id || empty($_POST['_commission_paid_nonce']) || !wp_verify_nonce($_POST['_commission_paid_nonce'], $nonce_action)) {
            $this->redirect_with_notice('commission_error');
        }

        $records = $this->get_coach_referrals([
            'referral_id' => $referral_id,
            'limit' => 1,
        ]);

        if (empty($records)) {
            $this->redirect_with_notice('commission_error');
        }

        $referral = $records[0];

        if ($this->is_commission_paid($referral)) {
            $this->redirect_with_notice('commission_already_paid');
        }

        $amount = $this->resolve_commission_amount($referral);
        if ($amount <= 0) {
            $this->redirect_with_notice('commission_error');
        }

        global $wpdb;
        $now = current_time('mysql');

        if (!empty($referral->commission_id)) {
            $wpdb->update(
                $wpdb->prefix . 'intersoccer_coach_commissions',
                [
                    'status' => 'paid',
                    'updated_at' => $now,
                ],
                ['id' => (int) $referral->commission_id]
            );
        }

        if (!empty($referral->credit_id)) {
            $wpdb->update(
                $wpdb->prefix . 'intersoccer_referral_credits',
                [
                    'status' => 'paid',
                    'updated_at' => $now,
                ],
                ['id' => (int) $referral->credit_id]
            );
        }

        $coach_id = (int) $referral->coach_id;
        if ($coach_id > 0) {
            $current_balance = (float) get_user_meta($coach_id, 'intersoccer_credits', true);
            $new_balance = max(0, $current_balance - $amount);
            update_user_meta($coach_id, 'intersoccer_credits', $new_balance);
        }

        do_action('intersoccer_commission_paid', $coach_id, $amount, $referral->order_id);

        $this->redirect_with_notice('commission_paid');
    }

    public function handle_delete_referral() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'intersoccer-referral'));
        }

        $referral_id = isset($_POST['referral_id']) ? absint($_POST['referral_id']) : 0;
        $nonce_action = 'intersoccer_delete_referral_' . $referral_id;

        if (!$referral_id || !isset($_POST['_referral_delete_nonce']) || !wp_verify_nonce($_POST['_referral_delete_nonce'], $nonce_action)) {
            $this->redirect_with_notice('delete_error');
        }

        global $wpdb;
        $table = $wpdb->prefix . 'intersoccer_referrals';

        if (!method_exists($wpdb, 'get_row')) {
            $this->redirect_with_notice('delete_error');
        }

        $referral = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $referral_id));

        if (!$referral) {
            $this->redirect_with_notice('delete_error');
        }

        $status = isset($referral->status) ? strtolower($referral->status) : '';
        if ($status === 'completed') {
            $this->redirect_with_notice('delete_error');
        }

        $duplicates = $wpdb->get_results($wpdb->prepare(
            "SELECT id, status FROM {$table} WHERE order_id = %d AND coach_id = %d",
            (int) $referral->order_id,
            (int) $referral->coach_id
        ));

        if (count($duplicates) <= 1) {
            $this->redirect_with_notice('delete_error');
        }

        $wpdb->delete($table, ['id' => $referral_id]);
        $wpdb->delete($wpdb->prefix . 'intersoccer_referral_credits', ['referral_id' => $referral_id]);

        $this->redirect_with_notice('deleted');
    }

    private function redirect_with_notice($code) {
        $redirect = wp_get_referer();
        if (!$redirect) {
            $redirect = admin_url('admin.php?page=intersoccer-coach-referrals');
        }

        wp_safe_redirect(add_query_arg('referral_notice', $code, $redirect));
        exit;
    }

}