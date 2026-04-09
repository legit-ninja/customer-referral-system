<?php
// includes/class-points-manager.php

class InterSoccer_Points_Manager {

    private $points_log_table;
    private $points_per_currency = 0.1; // 1 point per 10 CHF spent (legacy ratio metadata)

    public function __construct() {
        global $wpdb;
        $this->points_log_table = $wpdb->prefix . 'intersoccer_points_log';

        // Check allocation method - instant or deferred
        $allocation_method = get_option('intersoccer_points_allocation_method', 'instant');
        
        if ($allocation_method === 'instant') {
            add_action('woocommerce_order_status_completed', [$this, 'allocate_points_for_order'], 10, 1);
        } else {
            // For deferred, store order IDs for later processing
            add_action('woocommerce_order_status_completed', [$this, 'queue_order_for_points_allocation'], 10, 1);
        }
        
        add_action('woocommerce_order_status_refunded', [$this, 'deduct_points_for_refund'], 10, 1);
        add_action('wp_ajax_get_points_balance', [$this, 'get_points_balance_ajax']);
        add_action('wp_ajax_get_points_history', [$this, 'get_points_history_ajax']);

        // Add redemption hooks - disabled to prevent conflicts with admin dashboard system
        // add_action('woocommerce_cart_calculate_fees', [$this, 'apply_points_discount']);
        // add_action('woocommerce_checkout_process', [$this, 'validate_points_redemption']);
        // add_action('woocommerce_checkout_create_order', [$this, 'process_points_redemption'], 10, 2);
        add_action('woocommerce_order_status_cancelled', [$this, 'refund_points_on_cancellation'], 10, 1);
        add_action('woocommerce_order_status_failed', [$this, 'refund_points_on_failure'], 10, 1);
        
        // Schedule weekly deferred allocation if not already scheduled
        if ($allocation_method === 'deferred' && !wp_next_scheduled('intersoccer_deferred_points_allocation')) {
            wp_schedule_event(time(), 'weekly', 'intersoccer_deferred_points_allocation');
        }
        
        // Hook into the scheduled event
        add_action('intersoccer_deferred_points_allocation', [$this, 'process_deferred_points_allocation']);
    }

    /**
     * Allocate points when an order is completed
     */
    public function allocate_points_for_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $customer_id = $order->get_customer_id();
        if (!$customer_id) return;

        // Respect go-live date configuration
        $order_created = $order->get_date_created();
        $order_timestamp = null;

        if (class_exists('WC_DateTime') && $order_created instanceof WC_DateTime) {
            $order_timestamp = $order_created->getTimestamp();
        } elseif (!empty($order_created)) {
            $order_timestamp = strtotime((string) $order_created);
        }

        if ($order_timestamp !== null && $this->is_order_before_go_live($order_timestamp)) {
            intersoccer_referral_log("InterSoccer: Skipped points allocation for order {$order_id} (before go-live date)");
            return;
        }

        // Check if points already allocated for this order
        if ($this->order_has_points_allocated($order_id)) {
            return;
        }

        $order_total = max(0, $order->get_total());
        $allocation_mode = $this->get_allocation_mode();
        $allocation_meta = [
            'method' => $allocation_mode,
        ];

        $is_first_time = false; // Default to false
        if ($allocation_mode === 'percentage') {
            $allocation_meta['percentage_rate'] = $this->get_points_percentage_rate($customer_id);
        } else {
            // Check if this is a first-time customer
            $is_first_time = $this->is_first_time_customer($customer_id, $order_id);
            $allocation_meta['points_rate'] = $this->get_points_rate_for_user($customer_id, 'purchase', $is_first_time);
        }

        $points_to_allocate = $this->calculate_points_from_amount($order_total, $customer_id, $is_first_time);

        if ($points_to_allocate > 0) {
            // Log points earning for audit
            do_action('intersoccer_points_earned', $customer_id, $points_to_allocate, 'order_purchase', $order_id);

            $this->add_points_transaction(
                $customer_id,
                'order_purchase',
                $points_to_allocate,
                $order_id,
                'Points allocated for order #' . $order_id,
                [
                    'order_total' => $order_total,
                    'currency' => $order->get_currency(),
                    'points_rate' => $allocation_meta['method'] === 'ratio'
                        ? $allocation_meta['points_rate']
                        : null,
                    'percentage_rate' => $allocation_meta['method'] === 'percentage'
                        ? $allocation_meta['percentage_rate']
                        : null,
                    'allocation_method' => $allocation_meta['method']
                ]
            );

            // Update user meta for quick balance lookup
            $this->update_user_points_balance($customer_id);

            // Log the allocation
            intersoccer_referral_log("InterSoccer: Allocated {$points_to_allocate} points to customer {$customer_id} for order {$order_id}");
        }
    }

    /**
     * Allocate points for an order during backfill.
     * Skips orders before the configured go-live date. Used from Tools > Backfill tab.
     *
     * @param int $order_id WooCommerce order ID
     * @return int Points allocated (0 if skipped or zero total)
     */
    public function allocate_points_for_order_backfill($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return 0;
        }
        // Skip refunds: OrderRefund does not have get_customer_id() and we do not award points for refunds.
        if (is_callable([$order, 'is_type']) && $order->is_type('refund')) {
            return 0;
        }
        if (!method_exists($order, 'get_customer_id')) {
            return 0;
        }

        // Enforce go-live: do not award points for orders before the configured go-live date.
        $go_live = get_option('intersoccer_points_golive_date', '');
        if (!empty($go_live) && method_exists($order, 'get_date_created')) {
            $order_date = $order->get_date_created();
            if ($order_date) {
                $order_date_str = $order_date->format('Y-m-d');
                if ($order_date_str < $go_live) {
                    intersoccer_referral_log("InterSoccer: Backfill skipped order #{$order_id}: order date {$order_date_str} is before go-live {$go_live}");
                    return 0;
                }
            }
        }

        $customer_id = $order->get_customer_id();
        if (!$customer_id) {
            return 0;
        }

        if ($this->order_has_points_allocated($order_id)) {
            return 0;
        }

        $order_total = max(0, $order->get_total());
        $allocation_mode = $this->get_allocation_mode();
        $allocation_meta = [
            'method' => $allocation_mode,
        ];

        $is_first_time = false;
        if ($allocation_mode === 'percentage') {
            $allocation_meta['percentage_rate'] = $this->get_points_percentage_rate($customer_id);
        } else {
            $is_first_time = $this->is_first_time_customer($customer_id, $order_id);
            $allocation_meta['points_rate'] = $this->get_points_rate_for_user($customer_id, 'purchase', $is_first_time);
        }

        $points_to_allocate = $this->calculate_points_from_amount($order_total, $customer_id, $is_first_time);

        if ($points_to_allocate > 0) {
            do_action('intersoccer_points_earned', $customer_id, $points_to_allocate, 'order_purchase', $order_id);

            $this->add_points_transaction(
                $customer_id,
                'order_purchase',
                $points_to_allocate,
                $order_id,
                'Points allocated for order #' . $order_id . ' (backfill)',
                [
                    'order_total' => $order_total,
                    'currency' => $order->get_currency(),
                    'points_rate' => $allocation_meta['method'] === 'ratio'
                        ? $allocation_meta['points_rate']
                        : null,
                    'percentage_rate' => $allocation_meta['method'] === 'percentage'
                        ? $allocation_meta['percentage_rate']
                        : null,
                    'allocation_method' => $allocation_meta['method'],
                    'backfill' => true,
                ]
            );

            $this->update_user_points_balance($customer_id);
            intersoccer_referral_log("InterSoccer: Backfill allocated {$points_to_allocate} points to customer {$customer_id} for order {$order_id}");
        }

        return $points_to_allocate;
    }

    /**
     * Return the points that would be allocated for an order (for dry-run / preview).
     * Does not write to the points log or user meta.
     *
     * @param int $order_id WooCommerce order ID
     * @return int Points that would be allocated (0 if already allocated, no customer, or zero total)
     */
    public function get_points_for_order_dry_run($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return 0;
        }
        // Skip refunds: OrderRefund does not have get_customer_id(); dry-run should match backfill behaviour.
        if (is_callable([$order, 'is_type']) && $order->is_type('refund')) {
            return 0;
        }
        if (!method_exists($order, 'get_customer_id')) {
            return 0;
        }

        $customer_id = $order->get_customer_id();
        if (!$customer_id) {
            return 0;
        }

        if ($this->order_has_points_allocated($order_id)) {
            return 0;
        }

        $order_total = max(0, $order->get_total());
        $allocation_mode = $this->get_allocation_mode();
        $is_first_time = false;
        if ($allocation_mode !== 'percentage') {
            $is_first_time = $this->is_first_time_customer($customer_id, $order_id);
        }

        return $this->calculate_points_from_amount($order_total, $customer_id, $is_first_time);
    }

    /**
     * Queue order for deferred points allocation
     * Stores order ID in a transient for weekly processing
     */
    public function queue_order_for_points_allocation($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $customer_id = $order->get_customer_id();
        if (!$customer_id) return;

        // Check if points already allocated
        if ($this->order_has_points_allocated($order_id)) {
            return;
        }

        // Get existing queued orders
        $queued_orders = get_transient('intersoccer_queued_points_orders');
        if (!is_array($queued_orders)) {
            $queued_orders = [];
        }

        // Add order ID if not already queued
        if (!in_array($order_id, $queued_orders)) {
            $queued_orders[] = $order_id;
            set_transient('intersoccer_queued_points_orders', $queued_orders, WEEK_IN_SECONDS * 2);
            intersoccer_referral_log("InterSoccer: Queued order {$order_id} for deferred points allocation");
        }
    }

    /**
     * Process deferred points allocation for all queued orders
     * Called by wp_cron weekly
     */
    public function process_deferred_points_allocation() {
        $queued_orders = get_transient('intersoccer_queued_points_orders');
        
        if (!is_array($queued_orders) || empty($queued_orders)) {
            intersoccer_referral_log("InterSoccer: No orders queued for deferred points allocation");
            return;
        }

        $processed = 0;
        $failed = 0;

        foreach ($queued_orders as $order_id) {
            try {
                // Check if points already allocated (in case it was processed elsewhere)
                if (!$this->order_has_points_allocated($order_id)) {
                    $this->allocate_points_for_order($order_id);
                    $processed++;
                }
            } catch (Exception $e) {
                intersoccer_referral_log("InterSoccer: Failed to allocate points for order {$order_id}: " . $e->getMessage());
                $failed++;
            }
        }

        // Clear the queue after processing
        delete_transient('intersoccer_queued_points_orders');

        intersoccer_referral_log("InterSoccer: Deferred points allocation completed. Processed: {$processed}, Failed: {$failed}");
        
        // Log audit event
        do_action('intersoccer_audit_log', 'deferred_points_allocation', [
            'processed' => $processed,
            'failed' => $failed,
            'timestamp' => current_time('mysql')
        ]);
    }

    /**
     * Deduct points when an order is refunded
     */
    public function deduct_points_for_refund($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $customer_id = $order->get_customer_id();
        if (!$customer_id) return;

        // Only deduct if points were previously allocated
        $allocated_points = $this->get_points_allocated_for_order($order_id);
        if ($allocated_points <= 0) return;

        $this->add_points_transaction(
            $customer_id,
            'order_refund',
            -$allocated_points,
            $order_id,
            'Points deducted for refunded order #' . $order_id,
            [
                'refund_reason' => 'order_refunded',
                'original_allocation' => $allocated_points
            ]
        );

        // Update user meta
        $this->update_user_points_balance($customer_id);

        intersoccer_referral_log("InterSoccer: Deducted {$allocated_points} points from customer {$customer_id} for refunded order {$order_id}");
    }

    /**
     * Calculate points from currency amount
     * Returns integer points only - no fractional points
     * 
     * Phase 0: Now supports role-specific point acquisition rates
     * 
     * @param float $amount The currency amount in CHF
     * @param int $user_id Optional user ID to apply role-specific rate
     * @return int The number of points earned (integer only)
     */
    private function calculate_points_from_amount($amount, $user_id = null, $is_first_time = false) {
        $amount = max(0, (float) $amount);
        $mode = $this->get_allocation_mode();

        if ($mode === 'percentage') {
            $percentage = $this->get_points_percentage_rate($user_id);

            if ($percentage <= 0) {
                return 0;
            }

            $points_value = max(0.0001, $this->get_redemption_rate()); // CHF per point
            $raw_points = ($amount * ($percentage / 100)) / $points_value;

            return (int) floor($raw_points);
        }

        // Default ratio-based allocation
        // For purchases, use purchase rate; for referrals, use referral rate
        // First-time customers get special rate if applicable
        $context = 'purchase'; // Default to purchase context
        $rate = max(1, $this->get_points_rate_for_user($user_id, $context, $is_first_time));

        // Use floor() to ensure integer points (no fractional points)
        return (int) floor($amount / $rate);
    }

    /**
     * Get points acquisition rate for user based on their role
     * 
     * Priority order: partner > social_influencer > coach > customer
     * 
     * @param int $user_id User ID
     * @return int Points rate (CHF per point)
     */
    private function get_points_rate_for_user($user_id, $context = 'purchase', $is_first_time = false) {
        // Default rate if no user specified
        if (!$user_id) {
            // For purchases, check if first-time customer
            if ($context === 'purchase' && $is_first_time) {
                return intval(get_option('intersoccer_points_rate_first_time_customer', 10));
            }
            // For purchases, use purchase rate; for referrals, use referral rate
            if ($context === 'referral') {
                return intval(get_option('intersoccer_points_rate_customer_referral', 10));
            }
            return intval(get_option('intersoccer_points_rate_customer_purchase', 10));
        }

        $user = get_userdata($user_id);
        if (!$user) {
            // For purchases, check if first-time customer
            if ($context === 'purchase' && $is_first_time) {
                return intval(get_option('intersoccer_points_rate_first_time_customer', 10));
            }
            // For purchases, use purchase rate; for referrals, use referral rate
            if ($context === 'referral') {
                return intval(get_option('intersoccer_points_rate_customer_referral', 10));
            }
            return intval(get_option('intersoccer_points_rate_customer_purchase', 10));
        }

        // Note: Coaches, Partners, and Social Influencers use customer rates when making purchases.
        // They are rewarded through commission tiers (see Commission Manager) rather than special point rates.
        
        // Check if first-time customer (only for purchase context)
        if ($context === 'purchase' && $is_first_time) {
            return intval(get_option('intersoccer_points_rate_first_time_customer', 10));
        }

        // Use customer rate based on context
        if ($context === 'referral') {
            return intval(get_option('intersoccer_points_rate_customer_referral', 10));
        }
        return intval(get_option('intersoccer_points_rate_customer_purchase', 10));
    }

    /**
     * Check if a customer is a first-time customer (no previous completed orders).
     * Only the first completed purchase should be tagged as first-time. We exclude
     * the current order and check for prior completed orders only.
     *
     * @param int $customer_id Customer user ID
     * @param int|null $current_order_id Current order ID to exclude from check (critical: ensures we don't count the order being processed)
     * @return bool True if first-time customer, false otherwise
     */
    private function is_first_time_customer($customer_id, $current_order_id = null) {
        if (empty($customer_id)) {
            return true; // Guest checkout treated as first-time
        }

        if (!function_exists('wc_get_orders')) {
            return true; // WooCommerce not available, assume first-time
        }

        $query_args = [
            'customer' => $customer_id,
            'status' => ['wc-completed', 'completed'],
            'limit' => 1,
            'return' => 'ids',
        ];

        if (!empty($current_order_id)) {
            $query_args['exclude'] = [$current_order_id];
        }

        $previous_orders = wc_get_orders($query_args);

        return empty($previous_orders);
    }

    /**
     * Retrieve the active points allocation mode.
     *
     * @return string ratio|percentage
     */
    private function get_allocation_mode() {
        $mode = get_option('intersoccer_points_allocation_mode', 'ratio');

        if (!in_array($mode, ['ratio', 'percentage'], true)) {
            return 'ratio';
        }

        return $mode;
    }

    /**
     * Get percentage-based allocation rate.
     *
     * @param int|null $user_id Optional for future role-specific overrides
     * @return float Percentage value (0-100)
     */
    private function get_points_percentage_rate($user_id = null) {
        $percentage = (float) get_option('intersoccer_points_percentage_rate', 0);

        /**
         * Filter the percentage-based allocation rate.
         *
         * @since 1.0.0
         *
         * @param float $percentage Configured percentage (0-100)
         * @param int|null $user_id Optional user ID
         */
        if (function_exists('apply_filters')) {
            $percentage = apply_filters('intersoccer_points_percentage_rate', $percentage, $user_id);
        }

        if ($percentage < 0) {
            return 0;
        }

        if ($percentage > 100) {
            return 100;
        }

        return $percentage;
    }

    /**
     * Add a points transaction to the ledger
     */
    public function add_points_transaction($customer_id, $transaction_type, $points_amount, $order_id = null, $description = '', $metadata = []) {
        global $wpdb;

        // Get current balance before this transaction
        $current_balance = $this->get_points_balance($customer_id);

        // Calculate new balance
        $new_balance = $current_balance + $points_amount;

        $result = $wpdb->insert(
            $this->points_log_table,
            [
                'customer_id' => $customer_id,
                'order_id' => $order_id,
                'transaction_type' => $transaction_type,
                'points_amount' => $points_amount,
                'points_balance' => $new_balance,
                'description' => $description,
                'metadata' => json_encode($metadata),
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s']
        );

        if ($result === false) {
            intersoccer_referral_log("InterSoccer: Failed to insert points transaction: " . $wpdb->last_error);
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Get current points balance for a customer
     * 
     * @param int $customer_id The customer's user ID
     * @return int The customer's current points balance (integer only)
     */
    public function get_points_balance($customer_id) {
        global $wpdb;

        $balance = $wpdb->get_var($wpdb->prepare(
            "SELECT points_balance FROM {$this->points_log_table}
             WHERE customer_id = %d
             ORDER BY created_at DESC, id DESC
             LIMIT 1",
            $customer_id
        ));

        return $balance ? intval($balance) : 0;
    }

    /**
     * Update user meta with current points balance
     */
    public function update_user_points_balance($customer_id) {
        $balance = $this->get_points_balance($customer_id);
        update_user_meta($customer_id, 'intersoccer_points_balance', $balance);
    }

    /**
     * Check if an order already has points allocated
     *
     * @param int $order_id WooCommerce order ID
     * @return bool
     */
    public function order_has_points_allocated($order_id) {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->points_log_table}
             WHERE order_id = %d AND transaction_type = 'order_purchase'",
            $order_id
        ));

        return $count > 0;
    }

    /**
     * Get points allocated for a specific order
     */
    private function get_points_allocated_for_order($order_id) {
        global $wpdb;

        $points = $wpdb->get_var($wpdb->prepare(
            "SELECT points_amount FROM {$this->points_log_table}
             WHERE order_id = %d AND transaction_type = 'order_purchase'
             ORDER BY created_at DESC LIMIT 1",
            $order_id
        ));

        return $points ? floatval($points) : 0.0;
    }

    /**
     * Get points balance via AJAX
     */
    public function get_points_balance_ajax() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $customer_id = intval($_POST['customer_id']);
        $balance = $this->get_points_balance($customer_id);

        wp_send_json_success(['balance' => $balance]);
    }

    /**
     * Get points history via AJAX
     */
    public function get_points_history_ajax() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $customer_id = intval($_POST['customer_id'] ?? 0);
        $limit = intval($_POST['limit'] ?? 50);
        $offset = intval($_POST['offset'] ?? 0);

        global $wpdb;

        $where_clause = $customer_id ? $wpdb->prepare("WHERE customer_id = %d", $customer_id) : "";

        $transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->points_log_table}
             {$where_clause}
             ORDER BY created_at DESC, id DESC
             LIMIT %d OFFSET %d",
            $limit, $offset
        ));

        $formatted_transactions = [];
        foreach ($transactions as $transaction) {
            $formatted_transactions[] = [
                'id' => $transaction->id,
                'customer_id' => $transaction->customer_id,
                'order_id' => $transaction->order_id,
                'transaction_type' => $transaction->transaction_type,
                'points_amount' => intval($transaction->points_amount),
                'points_balance' => intval($transaction->points_balance),
                'description' => $transaction->description,
                'created_at' => $transaction->created_at,
                'metadata' => json_decode($transaction->metadata, true)
            ];
        }

        wp_send_json_success(['transactions' => $formatted_transactions]);
    }

    /**
     * Get points statistics for financial reporting
     */
    public function get_points_statistics($date_from = null, $date_to = null) {
        global $wpdb;

        $where_clause = "";
        $params = [];

        if ($date_from && $date_to) {
            $where_clause = "WHERE created_at BETWEEN %s AND %s";
            $params = [$date_from, $date_to];
        }

        // Total points earned
        $total_earned = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points_amount), 0) FROM {$this->points_log_table}
             WHERE points_amount > 0 {$where_clause}",
            $params
        ));

        // Total points spent/redeemed
        $total_spent = abs($wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points_amount), 0) FROM {$this->points_log_table}
             WHERE points_amount < 0 {$where_clause}",
            $params
        )));

        // Current total balance across all customers
        $current_balance = $wpdb->get_var(
            "SELECT COALESCE(SUM(points_balance), 0) FROM (
                SELECT points_balance FROM {$this->points_log_table} pl1
                WHERE created_at = (
                    SELECT MAX(created_at) FROM {$this->points_log_table} pl2
                    WHERE pl2.customer_id = pl1.customer_id
                )
                GROUP BY customer_id
            ) as latest_balances"
        );

        // Customers with points
        $customers_with_points = $wpdb->get_var(
            "SELECT COUNT(DISTINCT customer_id) FROM {$this->points_log_table}
             WHERE points_balance > 0"
        );

        // Average points per customer (integer only)
        $avg_points_per_customer = $customers_with_points > 0 ?
            intval($current_balance / $customers_with_points) : 0;

        return [
            'total_earned' => intval($total_earned),
            'total_spent' => intval($total_spent),
            'current_balance' => intval($current_balance),
            'customers_with_points' => intval($customers_with_points),
            'avg_points_per_customer' => $avg_points_per_customer
        ];
    }

    /**
     * Get points transaction summary by type
     */
    public function get_transaction_summary($date_from = null, $date_to = null) {
        global $wpdb;

        $where_clause = "";
        $params = [];

        if ($date_from && $date_to) {
            $where_clause = "WHERE created_at BETWEEN %s AND %s";
            $params = [$date_from, $date_to];
        }

        $summary = $wpdb->get_results($wpdb->prepare(
            "SELECT transaction_type,
                    COUNT(*) as transaction_count,
                    SUM(points_amount) as total_points,
                    AVG(points_amount) as avg_points
             FROM {$this->points_log_table}
             {$where_clause}
             GROUP BY transaction_type
             ORDER BY total_points DESC",
            $params
        ));

        $formatted_summary = [];
        foreach ($summary as $row) {
            $formatted_summary[$row->transaction_type] = [
                'count' => intval($row->transaction_count),
                'total_points' => intval($row->total_points),
                'avg_points' => intval($row->avg_points)
            ];
        }

        return $formatted_summary;
    }

    /**
     * Log audit event
     */
    private function log_audit($action, $details) {
        $audit_log = get_option('intersoccer_audit_log', []);

        $audit_log[] = [
            'timestamp' => current_time('mysql'),
            'action' => $action,
            'user' => wp_get_current_user()->user_login,
            'details' => $details
        ];

        // Keep only last 1000 entries
        if (count($audit_log) > 1000) {
            $audit_log = array_slice($audit_log, -1000);
        }

        update_option('intersoccer_audit_log', $audit_log);
    }

    /**
     * Determine if an order timestamp falls before the configured go-live date
     *
     * @param int $order_timestamp Unix timestamp representing order creation
     * @return bool True when order should be skipped due to being before go-live date
     */
    private function is_order_before_go_live($order_timestamp) {
        if (empty($order_timestamp)) {
            return false;
        }

        $go_live_date = get_option('intersoccer_points_golive_date', '');
        if (empty($go_live_date)) {
            return false;
        }

        $go_live_timestamp = strtotime($go_live_date . ' 00:00:00');

        if (!$go_live_timestamp) {
            return false;
        }

        return $order_timestamp < $go_live_timestamp;
    }

    /**
     * Apply points discount to cart
     */
    public function apply_points_discount() {
        if (!is_checkout() || !is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        $points_to_redeem = WC()->session->get('intersoccer_points_to_redeem', 0);

        if ($points_to_redeem <= 0) {
            return;
        }

        // Validate redemption limits
        if (!$this->can_redeem_points($user_id, $points_to_redeem)) {
            WC()->session->set('intersoccer_points_to_redeem', 0);
            return;
        }

        $discount_amount = $this->calculate_discount_from_points($points_to_redeem);

        if ($discount_amount > 0) {
            WC()->cart->add_fee(__('Points Discount', 'intersoccer-referral'), -$discount_amount, true, 'intersoccer_points');
        }
    }

    /**
     * Validate points redemption during checkout
     */
    public function validate_points_redemption() {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        $points_to_redeem = WC()->session->get('intersoccer_points_to_redeem', 0);

        if ($points_to_redeem <= 0) {
            return;
        }

        // Check if user has enough points
        $current_balance = $this->get_points_balance($user_id);
        if ($points_to_redeem > $current_balance) {
            wc_add_notice(__('Insufficient points balance.', 'intersoccer-referral'), 'error');
            return;
        }

        // Validate redemption limits
        if (!$this->can_redeem_points($user_id, $points_to_redeem)) {
            wc_add_notice(__('Points redemption exceeds allowed limits.', 'intersoccer-referral'), 'error');
            return;
        }

        $discount_amount = $this->calculate_discount_from_points($points_to_redeem);
        $cart_total = WC()->cart->get_total('edit');

        if ($discount_amount > $cart_total) {
            wc_add_notice(__('Points discount cannot exceed order total.', 'intersoccer-referral'), 'error');
            return;
        }
    }

    /**
     * Process points redemption when order is created
     */
    public function process_points_redemption($order, $data) {
        $points_to_redeem = WC()->session->get('intersoccer_points_to_redeem', 0);

        if ($points_to_redeem <= 0) {
            return;
        }

        $user_id = $order->get_customer_id();
        $discount_amount = $this->calculate_discount_from_points($points_to_redeem);

        // Record the redemption
        $this->add_points_transaction(
            $user_id,
            'points_redemption',
            -$points_to_redeem,
            $order->get_id(),
            sprintf(__('Redeemed %d points for %.2f CHF discount', 'intersoccer-referral'), $points_to_redeem, $discount_amount),
            [
                'discount_amount' => $discount_amount,
                'redemption_rate' => $this->get_redemption_rate()
            ]
        );

        // Log points redemption for audit
        do_action('intersoccer_points_redeemed', $user_id, $points_to_redeem, $discount_amount, $order->get_id());

        // Update user meta
        $this->update_user_points_balance($user_id);

        // Store redemption details in order meta
        $order->update_meta_data('_intersoccer_points_redeemed', $points_to_redeem);
        $order->update_meta_data('_intersoccer_discount_amount', $discount_amount);

        // Clear session
        WC()->session->set('intersoccer_points_to_redeem', 0);

        intersoccer_referral_log("InterSoccer: Redeemed {$points_to_redeem} points for order {$order->get_id()}");
    }

    /**
     * Refund points when order is cancelled or failed
     */
    public function refund_points_on_cancellation($order_id) {
        $this->refund_points_for_order($order_id, 'order_cancelled');
    }

    public function refund_points_on_failure($order_id) {
        $this->refund_points_for_order($order_id, 'order_failed');
    }

    private function refund_points_for_order($order_id, $reason) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $points_redeemed = $order->get_meta('_intersoccer_points_redeemed', true);
        if (!$points_redeemed || $points_redeemed <= 0) return;

        $user_id = $order->get_customer_id();
        $discount_amount = $order->get_meta('_intersoccer_discount_amount', true);

        // Refund the points
        $this->add_points_transaction(
            $user_id,
            'points_refund',
            $points_redeemed,
            $order_id,
            sprintf(__('Refunded %d points due to %s', 'intersoccer-referral'), $points_redeemed, $reason),
            [
                'original_discount' => $discount_amount,
                'refund_reason' => $reason
            ]
        );

        // Update user meta
        $this->update_user_points_balance($user_id);

        // Clear order meta
        $order->delete_meta_data('_intersoccer_points_redeemed');
        $order->delete_meta_data('_intersoccer_discount_amount');

        intersoccer_referral_log("InterSoccer: Refunded {$points_redeemed} points for {$reason} order {$order_id}");
    }

    /**
     * Check if user can redeem points based on limits
     * 
     * Updated Phase 0: Removed 100-point limit and CHF 1,000 spent ratio
     * Now only validates against available balance and cart total
     * 
     * @param int $user_id Customer user ID
     * @param int $points_to_redeem Number of points to redeem
     * @param float $cart_total Optional cart total (if available)
     * @return bool True if redemption is allowed
     */
    public function can_redeem_points($user_id, $points_to_redeem, $cart_total = null) {
        // Check balance
        $current_balance = $this->get_points_balance($user_id);
        if ($points_to_redeem > $current_balance) {
            return false;
        }

        // If cart total provided, validate against it
        if ($cart_total !== null) {
            $discount_amount = $this->calculate_discount_from_points($points_to_redeem);
            if ($discount_amount > $cart_total) {
                return false;
            }
        }

        // No other limits - customers can use all available points!
        return true;
    }

    /**
     * Get customer's total spent amount
     */
    private function get_customer_total_spent($user_id) {
        global $wpdb;

        $total_spent = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(pm.meta_value)
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type = 'shop_order'
             AND p.post_status IN ('wc_completed', 'wc_processing')
             AND pm.meta_key = '_customer_user'
             AND pm.meta_value = %d",
            $user_id
        ));

        return floatval($total_spent);
    }

    /**
     * Calculate discount amount from points
     */
    public function calculate_discount_from_points($points) {
        $redemption_rate = $this->get_redemption_rate(); // CHF per point
        return round($points * $redemption_rate, 2);
    }

    /**
     * Get redemption rate (CHF per point)
     */
    private function get_redemption_rate() {
        return 1.0; // 1 CHF per point redeemed
    }

    /**
     * Calculate points from discount amount
     */
    public function calculate_points_from_discount($discount_amount) {
        $redemption_rate = $this->get_redemption_rate();
        return round($discount_amount / $redemption_rate, 2);
    }

    /**
     * Get maximum redeemable points for user
     * 
     * Updated Phase 0: No longer limited by spending ratio or 100-point cap
     * Returns full available balance (limited only by cart total at checkout)
     * 
     * @param int $user_id Customer user ID
     * @param float $cart_total Optional cart total to calculate against
     * @return int Maximum redeemable points
     */
    public function get_max_redeemable_points($user_id, $cart_total = null) {
        $current_balance = $this->get_points_balance($user_id);
        
        // If cart total provided, limit to that
        if ($cart_total !== null) {
            $max_by_cart = intval($cart_total); // 1 point = 1 CHF
            return min($current_balance, $max_by_cart);
        }
        
        // Otherwise return full balance (no arbitrary limits)
        return $current_balance;
    }

    /**
     * Get redemption summary for user
     * 
     * Updated Phase 0: No longer includes old spending ratio limits
     * 
     * @param int $user_id Customer user ID
     * @param float $cart_total Optional current cart total
     * @return array Redemption summary
     */
    public function get_redemption_summary($user_id, $cart_total = null) {
        $current_balance = $this->get_points_balance($user_id);
        $total_spent = $this->get_customer_total_spent($user_id);
        
        // Calculate maximum redeemable based on cart total (if provided)
        if ($cart_total !== null) {
            $max_by_cart = intval($cart_total);
            $available_points = min($current_balance, $max_by_cart);
        } else {
            $available_points = $current_balance;
        }

        return [
            'total_spent' => $total_spent,
            'current_balance' => $current_balance,
            'available_points' => $available_points,
            'available_discount' => $this->calculate_discount_from_points($available_points),
            'cart_total' => $cart_total,
            'can_fully_cover' => ($cart_total !== null && $available_points >= $cart_total)
        ];
    }
}