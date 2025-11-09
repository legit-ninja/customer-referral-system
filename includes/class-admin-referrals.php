<?php
// includes/class-admin-referrals.php

class InterSoccer_Admin_Referrals {

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
            }

            if ($message) {
                printf('<div class="notice %1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
            }
        }

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
        </style>
        <?php
    }

    /**
     * Display coach referrals table
     */
    private function display_coach_referrals_table() {
        global $wpdb;

        $referrals = $wpdb->get_results("
            SELECT r.*, 
                   c.display_name as coach_name,
                   c.user_email AS coach_email,
                   u.display_name as customer_name,
                   u.user_email AS customer_email,
                   COALESCE(cc.commission_amount, rc.credit_amount) as commission,
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
            ORDER BY r.created_at DESC
            LIMIT 50
        ");

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
                    <td class="actions-column">
                        <?php if ($duplicate_count > 1 && strtolower($referral->status) !== 'completed'): ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('intersoccer_delete_referral_' . $referral->id, '_referral_delete_nonce'); ?>
                                <input type="hidden" name="action" value="intersoccer_delete_referral">
                                <input type="hidden" name="referral_id" value="<?php echo (int) $referral->id; ?>">
                                <button type="submit" class="button button-link-delete">
                                    <?php esc_html_e('Remove Duplicate', 'intersoccer-referral'); ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="muted">&mdash;</span>
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