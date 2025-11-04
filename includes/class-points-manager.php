<?php
// includes/class-points-manager.php

class InterSoccer_Points_Manager {

    private $points_log_table;
    private $points_per_currency = 0.1; // 1 point per 10 CHF spent

    public function __construct() {
        global $wpdb;
        $this->points_log_table = $wpdb->prefix . 'intersoccer_points_log';

        add_action('woocommerce_order_status_completed', [$this, 'allocate_points_for_order'], 10, 1);
        add_action('woocommerce_order_status_refunded', [$this, 'deduct_points_for_refund'], 10, 1);
        add_action('wp_ajax_scan_orders_for_points', [$this, 'scan_orders_for_points']);
        add_action('wp_ajax_get_points_balance', [$this, 'get_points_balance_ajax']);
        add_action('wp_ajax_get_points_history', [$this, 'get_points_history_ajax']);

        // Add redemption hooks - disabled to prevent conflicts with admin dashboard system
        // add_action('woocommerce_cart_calculate_fees', [$this, 'apply_points_discount']);
        // add_action('woocommerce_checkout_process', [$this, 'validate_points_redemption']);
        // add_action('woocommerce_checkout_create_order', [$this, 'process_points_redemption'], 10, 2);
        add_action('woocommerce_order_status_cancelled', [$this, 'refund_points_on_cancellation'], 10, 1);
        add_action('woocommerce_order_status_failed', [$this, 'refund_points_on_failure'], 10, 1);
    }

    /**
     * Allocate points when an order is completed
     */
    public function allocate_points_for_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;

        $customer_id = $order->get_customer_id();
        if (!$customer_id) return;

        // Check if points already allocated for this order
        if ($this->order_has_points_allocated($order_id)) {
            return;
        }

        $order_total = $order->get_total();
        $points_to_allocate = $this->calculate_points_from_amount($order_total);

        if ($points_to_allocate > 0) {
            // Log points earning for audit
            do_action('intersoccer_points_earned', $customer_id, $points_to_allocate, 'order_purchase', $order_id);

            $this->add_points_transaction(
                $customer_id,
                $order_id,
                'order_purchase',
                $points_to_allocate,
                'Points allocated for order #' . $order_id,
                [
                    'order_total' => $order_total,
                    'currency' => $order->get_currency(),
                    'points_rate' => $this->points_per_currency
                ]
            );

            // Update user meta for quick balance lookup
            $this->update_user_points_balance($customer_id);

            // Log the allocation
            error_log("InterSoccer: Allocated {$points_to_allocate} points to customer {$customer_id} for order {$order_id}");
        }
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
            $order_id,
            'order_refund',
            -$allocated_points, // Negative for deduction
            'Points deducted for refunded order #' . $order_id,
            [
                'refund_reason' => 'order_refunded',
                'original_allocation' => $allocated_points
            ]
        );

        // Update user meta
        $this->update_user_points_balance($customer_id);

        error_log("InterSoccer: Deducted {$allocated_points} points from customer {$customer_id} for refunded order {$order_id}");
    }

    /**
     * Scan existing orders and allocate points (for backfilling)
     */
    public function scan_orders_for_points() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $result = $this->perform_points_sync();

        wp_send_json_success([
            'message' => "Backfill completed! Processed: {$result['processed']}, Skipped: {$result['skipped']}, Errors: {$result['errors']}",
            'processed' => $result['processed'],
            'skipped' => $result['skipped'],
            'errors' => $result['errors']
        ]);
    }

    /**
     * Perform the actual points sync operation and return results
     */
    public function perform_points_sync() {
        global $wpdb;

        $processed = 0;
        $skipped = 0;
        $errors = 0;
        $points_allocated = 0;

        // Get all completed orders without points allocation
        $orders = wc_get_orders([
            'status' => 'completed',
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'ASC'
        ]);

        // Filter out refunds - only process actual orders
        $orders = array_filter($orders, function($order) {
            return $order instanceof WC_Order && !$order instanceof WC_Order_Refund;
        });

        foreach ($orders as $order) {
            $order_id = $order->get_id();
            $customer_id = $order->get_customer_id();

            if (!$customer_id) {
                $skipped++;
                continue;
            }

            // Skip if already processed
            if ($this->order_has_points_allocated($order_id)) {
                $skipped++;
                continue;
            }

            try {
                $order_total = $order->get_total();
                $points_to_allocate = $this->calculate_points_from_amount($order_total);

                if ($points_to_allocate > 0) {
                    $this->add_points_transaction(
                        $customer_id,
                        $order_id,
                        'order_purchase_backfill',
                        $points_to_allocate,
                        'Points allocated for existing order #' . $order_id . ' (backfill)',
                        [
                            'order_total' => $order_total,
                            'currency' => $order->get_currency(),
                            'points_rate' => $this->points_per_currency,
                            'backfill' => true
                        ]
                    );

                    $this->update_user_points_balance($customer_id);
                    $processed++;
                    $points_allocated += $points_to_allocate;
                } else {
                    $skipped++;
                }
            } catch (Exception $e) {
                error_log("InterSoccer: Error processing order {$order_id}: " . $e->getMessage());
                $errors++;
            }
        }

        $this->log_audit('points_backfill', "Backfill completed: {$processed} processed, {$skipped} skipped, {$errors} errors");

        // Update sync status
        update_option('intersoccer_points_sync_status', [
            'last_sync' => current_time('mysql'),
            'total_processed' => $processed,
            'total_points' => $points_allocated,
            'status' => 'completed'
        ]);

        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'errors' => $errors,
            'points_allocated' => $points_allocated
        ];
    }

    /**
     * Calculate points from currency amount
     * Returns integer points only - no fractional points
     * 
     * @param float $amount The currency amount in CHF
     * @return int The number of points earned (integer only)
     */
    private function calculate_points_from_amount($amount) {
        // Use floor() to ensure integer points (10 CHF = 1 point)
        // floor() rounds down, so 95 CHF = 9 points (not 9.5)
        return (int) floor($amount / 10);
    }

    /**
     * Add a points transaction to the ledger
     */
    public function add_points_transaction($customer_id, $order_id = null, $transaction_type, $points_amount, $description = '', $metadata = []) {
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
            error_log("InterSoccer: Failed to insert points transaction: " . $wpdb->last_error);
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
     */
    private function order_has_points_allocated($order_id) {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->points_log_table}
             WHERE order_id = %d AND transaction_type IN ('order_purchase', 'order_purchase_backfill')",
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
             WHERE order_id = %d AND transaction_type IN ('order_purchase', 'order_purchase_backfill')
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
            $order->get_id(),
            'points_redemption',
            -$points_to_redeem,
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

        error_log("InterSoccer: Redeemed {$points_to_redeem} points for order {$order->get_id()}");
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
            $order_id,
            'points_refund',
            $points_redeemed,
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

        error_log("InterSoccer: Refunded {$points_redeemed} points for {$reason} order {$order_id}");
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