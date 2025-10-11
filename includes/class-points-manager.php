<?php
// includes/class-points-manager.php

class InterSoccer_Points_Manager {

    private $points_log_table;
    private $points_per_currency = 1; // 1 point per CHF spent

    public function __construct() {
        global $wpdb;
        $this->points_log_table = $wpdb->prefix . 'intersoccer_points_log';

        add_action('woocommerce_order_status_completed', [$this, 'allocate_points_for_order'], 10, 1);
        add_action('woocommerce_order_status_refunded', [$this, 'deduct_points_for_refund'], 10, 1);
        add_action('wp_ajax_scan_orders_for_points', [$this, 'scan_orders_for_points']);
        add_action('wp_ajax_get_points_balance', [$this, 'get_points_balance_ajax']);
        add_action('wp_ajax_get_points_history', [$this, 'get_points_history_ajax']);
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

        global $wpdb;

        $processed = 0;
        $skipped = 0;
        $errors = 0;

        // Get all completed orders without points allocation
        $orders = wc_get_orders([
            'status' => 'completed',
            'limit' => -1,
            'orderby' => 'date',
            'order' => 'ASC'
        ]);

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
                } else {
                    $skipped++;
                }
            } catch (Exception $e) {
                error_log("InterSoccer: Error processing order {$order_id}: " . $e->getMessage());
                $errors++;
            }
        }

        $this->log_audit('points_backfill', "Backfill completed: {$processed} processed, {$skipped} skipped, {$errors} errors");

        wp_send_json_success([
            'message' => "Backfill completed! Processed: {$processed}, Skipped: {$skipped}, Errors: {$errors}",
            'processed' => $processed,
            'skipped' => $skipped,
            'errors' => $errors
        ]);
    }

    /**
     * Calculate points from currency amount
     */
    private function calculate_points_from_amount($amount) {
        return round($amount * $this->points_per_currency, 2);
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

        return $balance ? floatval($balance) : 0.0;
    }

    /**
     * Update user meta with current points balance
     */
    private function update_user_points_balance($customer_id) {
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
                'points_amount' => floatval($transaction->points_amount),
                'points_balance' => floatval($transaction->points_balance),
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

        // Average points per customer
        $avg_points_per_customer = $customers_with_points > 0 ?
            $current_balance / $customers_with_points : 0;

        return [
            'total_earned' => floatval($total_earned),
            'total_spent' => floatval($total_spent),
            'current_balance' => floatval($current_balance),
            'customers_with_points' => intval($customers_with_points),
            'avg_points_per_customer' => round($avg_points_per_customer, 2)
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
                'total_points' => floatval($row->total_points),
                'avg_points' => round(floatval($row->avg_points), 2)
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
}