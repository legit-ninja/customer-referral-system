<?php
// includes/class-points-manager.php

require_once dirname(__FILE__) . '/intersoccer-refund-helpers.php';

/**
 * Points ledger transaction types (customer-referral-system).
 *
 * Standard: order_purchase, order_refund, order_cancelled, points_redemption, points_refund,
 * gift_sent, gift_received, coach_referral_reversal, customer_referral_reversal, admin_adjustment, etc.
 *
 * Refund / reinstatement extensions:
 * - reversal_write_off — earned or retake shortfall recorded at current balance (points_amount 0).
 * - reversal_reinstated — earned points re-credited when a cancelled/refunded order is paid again.
 * - redeemed_retaken — redeemed points debited again after reinstatement or late payment.
 */
class InterSoccer_Points_Manager {

    /**
     * Singleton instance for reuse and direct method calls.
     *
     * @var InterSoccer_Points_Manager|null
     */
    private static $instance = null;

    private $points_log_table;
    private $points_per_currency = 0.1; // 1 point per 10 CHF spent (legacy ratio metadata)

    public function __construct() {
        if (self::$instance instanceof self) {
            $this->points_log_table = self::$instance->points_log_table;
            $this->points_per_currency = self::$instance->points_per_currency;
            return;
        }

        self::$instance = $this;

        global $wpdb;
        $this->points_log_table = $wpdb->prefix . 'intersoccer_points_log';

        // Check allocation method - instant or deferred
        $allocation_method = get_option('intersoccer_points_allocation_method', 'instant');
        
        // §9.4 Oracle: Points allocate on PROCESSING or COMPLETED (not pending/failed)
        // Hook both statuses to ensure points are awarded when payment is confirmed
        if ($allocation_method === 'instant') {
            add_action('woocommerce_order_status_processing', [$this, 'allocate_points_for_order'], 10, 1);
            add_action('woocommerce_order_status_completed', [$this, 'allocate_points_for_order'], 10, 1);
        } else {
            // For deferred, store order IDs for later processing
            add_action('woocommerce_order_status_processing', [$this, 'queue_order_for_points_allocation'], 10, 1);
            add_action('woocommerce_order_status_completed', [$this, 'queue_order_for_points_allocation'], 10, 1);
        }
        
        add_action('woocommerce_order_refunded', [$this, 'handle_woocommerce_order_refunded'], 10, 2);
        add_action('woocommerce_order_status_refunded', [$this, 'handle_order_status_refunded'], 10, 1);
        add_action('woocommerce_order_status_cancelled', [$this, 'handle_order_status_cancelled'], 10, 1);
        add_action('woocommerce_order_status_failed', [$this, 'handle_order_status_failed'], 10, 1);
        add_action('woocommerce_order_status_changed', [$this, 'handle_order_status_changed'], 10, 4);
        add_action('wp_ajax_get_points_balance', [$this, 'get_points_balance_ajax']);
        add_action('wp_ajax_get_points_history', [$this, 'get_points_history_ajax']);

        // Add redemption hooks - disabled to prevent conflicts with admin dashboard system
        // add_action('woocommerce_cart_calculate_fees', [$this, 'apply_points_discount']);
        // add_action('woocommerce_checkout_process', [$this, 'validate_points_redemption']);
        // add_action('woocommerce_checkout_create_order', [$this, 'process_points_redemption'], 10, 2);
        
        // Schedule weekly deferred allocation if not already scheduled
        if ($allocation_method === 'deferred' && !wp_next_scheduled('intersoccer_deferred_points_allocation')) {
            wp_schedule_event(time(), 'weekly', 'intersoccer_deferred_points_allocation');
        }
        
        // Hook into the scheduled event
        add_action('intersoccer_deferred_points_allocation', [$this, 'process_deferred_points_allocation']);

        if (!wp_next_scheduled('intersoccer_release_unpaid_redeemed_points')) {
            wp_schedule_event(time(), 'daily', 'intersoccer_release_unpaid_redeemed_points');
        }
        add_action('intersoccer_release_unpaid_redeemed_points', [$this, 'release_unpaid_redeemed_points_for_stale_orders']);
    }

    /**
     * Retrieve the singleton instance.
     *
     * @return self
     */
    public static function get_instance() {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }

        return self::$instance;
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

        $status = (string) $order->get_status();
        if (strpos($status, 'wc-') === 0) {
            $status = substr($status, 3);
        }
        if (in_array($status, ['cancelled', 'refunded', 'failed', 'pending', 'on-hold'], true)) {
            intersoccer_referral_log("InterSoccer: Skipped points allocation for order {$order_id} (status: {$status})");
            return;
        }

        // Check if points already allocated for this order
        if ($this->order_has_points_allocated($order_id)) {
            return;
        }

        $order_total = $this->get_order_net_total_for_allocation($order);
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

            // Log the allocation
            intersoccer_referral_log("InterSoccer: Allocated {$points_to_allocate} points to customer {$customer_id} for order {$order_id}");

            $netted_refund_ids = [];
            if (method_exists($order, 'get_refunds')) {
                foreach ((array) $order->get_refunds() as $refund) {
                    if (is_object($refund) && method_exists($refund, 'get_id')) {
                        $netted_refund_ids[] = (int) $refund->get_id();
                    }
                }
            }
            $order->update_meta_data('_intersoccer_award_netted_refund_ids', $netted_refund_ids);
            $order->update_meta_data('_intersoccer_award_net_total', $order_total);
            if (method_exists($order, 'save')) {
                $order->save();
            }
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

        $status = (string) $order->get_status();
        if (strpos($status, 'wc-') === 0) {
            $status = substr($status, 3);
        }
        if (in_array($status, ['cancelled', 'refunded', 'failed', 'pending', 'on-hold'], true)) {
            intersoccer_referral_log("InterSoccer: Skipped points allocation for order {$order_id} (status: {$status})");
            return 0;
        }

        if ($this->order_has_points_allocated($order_id)) {
            return 0;
        }

        $order_total = $this->get_order_net_total_for_allocation($order);
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

        $order_total = $this->get_order_net_total_for_allocation($order);
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
     * WooCommerce refund created (partial or full).
     *
     * @param int $order_id
     * @param int $refund_id
     */
    public function handle_woocommerce_order_refunded($order_id, $refund_id) {
        $this->sync_refund_reversals((int) $order_id, 'order_refunded', [
            'refund_id' => (int) $refund_id,
        ]);
    }

    /**
     * Order moved to refunded status (full refund).
     *
     * @param int $order_id
     */
    public function handle_order_status_refunded($order_id) {
        $this->sync_refund_reversals((int) $order_id, 'order_refunded', [
            'force_fraction_one' => true,
        ]);
    }

    /**
     * Order cancelled — reverse earned points and return redeemed points.
     *
     * @param int $order_id
     */
    public function handle_order_status_cancelled($order_id) {
        $this->sync_refund_reversals((int) $order_id, 'order_cancelled', [
            'force_fraction_one' => true,
        ]);
    }

    /**
     * Failed order — return redeemed points only (no purchase points were earned).
     *
     * @param int $order_id
     */
    public function handle_order_status_failed($order_id) {
        $this->sync_refund_reversals((int) $order_id, 'order_failed', [
            'force_fraction_one' => true,
            'skip_earned_reversal' => true,
        ]);
    }

    /**
     * Reinstate earned/redeemed points when an order returns to a paid workflow status.
     *
     * @param int    $order_id
     * @param string $old_status
     * @param string $new_status
     * @param WC_Order $order
     */
    public function handle_order_status_changed($order_id, $old_status, $new_status, $order = null) {
        if (!$order || !is_object($order)) {
            $order = wc_get_order($order_id);
        }
        if (!$order || !method_exists($order, 'get_status')) {
            return;
        }

        $old = (string) $old_status;
        $new = (string) $new_status;
        if (strpos($old, 'wc-') === 0) {
            $old = substr($old, 3);
        }
        if (strpos($new, 'wc-') === 0) {
            $new = substr($new, 3);
        }

        $reinstatement_transition = in_array($old, ['cancelled', 'refunded'], true)
            && in_array($new, ['processing', 'completed', 'on-hold'], true);

        if ($reinstatement_transition) {
            $this->sync_reinstatement_for_order($order);
        } elseif (
            in_array($new, ['processing', 'completed'], true)
            && (int) $order->get_meta('_intersoccer_credits_deducted_on_completion', true) === 1
        ) {
            if ((int) $order->get_meta('_intersoccer_unpaid_redeemed_released', true) === 1) {
                $this->maybe_retake_redeemed_points_after_payment($order);
            } elseif (
                $old === 'failed'
                && (int) $order->get_meta('_intersoccer_redeemed_points_returned_total', true) > 0
            ) {
                $this->maybe_retake_redeemed_points_after_payment($order);
            }
        }
    }

    /**
     * Take back purchase points after a cancel or a full refund (legacy entry point).
     *
     * @param int $order_id
     */
    public function reverse_earned_points_on_cancel_or_full_refund($order_id) {
        $order = wc_get_order($order_id);
        if (!$order || !method_exists($order, 'get_status')) {
            return;
        }

        $status = (string) $order->get_status();
        if (strpos($status, 'wc-') === 0) {
            $status = substr($status, 3);
        }
        if (!in_array($status, ['cancelled', 'refunded'], true)) {
            return;
        }

        $reason = $status === 'cancelled' ? 'order_cancelled' : 'order_refunded';
        $this->sync_refund_reversals((int) $order_id, $reason, [
            'force_fraction_one' => true,
            'earned_only' => true,
        ]);
    }

    /**
     * Full earned-points reversal (legacy entry point).
     *
     * @param int $order_id
     */
    public function deduct_points_for_refund($order_id) {
        $this->sync_refund_reversals((int) $order_id, 'order_refunded', [
            'force_fraction_one' => true,
            'earned_only' => true,
        ]);
    }

    /**
     * Sync proportional reversal of earned points and return of redeemed points for refunds/cancels.
     *
     * @param int    $order_id
     * @param string $reason order_refunded|order_cancelled|order_failed
     * @param array  $context  refund_id, force_fraction_one, skip_earned_reversal, earned_only
     */
    public function sync_refund_reversals($order_id, $reason, $context = []) {
        $order_id = (int) $order_id;
        if ($order_id <= 0) {
            return;
        }

        if (!intersoccer_acquire_order_refund_lock($order_id)) {
            return;
        }

        try {
            $order = wc_get_order($order_id);
            if (!$order || !method_exists($order, 'get_customer_id')) {
                return;
            }

            $refund_id = isset($context['refund_id']) ? (int) $context['refund_id'] : 0;
            if ($refund_id > 0 && $this->is_refund_id_processed_for_points($order, $refund_id)) {
                return;
            }

            $force_fraction_one = !empty($context['force_fraction_one']);
            $earned_fraction = $force_fraction_one
                ? 1.0
                : $this->get_post_award_refunded_fraction($order);
            $redeemed_fraction = $force_fraction_one
                ? 1.0
                : $this->get_cumulative_refunded_fraction($order);

            $earned_only = !empty($context['earned_only']);

            if (empty($context['skip_earned_reversal'])) {
                $this->sync_earned_points_reversal_for_order($order, $earned_fraction, $reason, $refund_id ?: null);
            }

            if (!$earned_only) {
                $this->sync_redeemed_points_return_for_order($order, $redeemed_fraction, $reason, $refund_id ?: null);
            }

            if ($refund_id > 0) {
                $this->mark_refund_id_processed_for_points($order, $refund_id);
            }
        } finally {
            intersoccer_release_order_refund_lock($order_id);
        }
    }

    /**
     * @param WC_Order $order
     * @param int      $refund_id
     * @return bool
     */
    private function is_refund_id_processed_for_points($order, $refund_id) {
        $refund_id = (int) $refund_id;
        if ($refund_id <= 0) {
            return false;
        }

        $processed = $order->get_meta('_intersoccer_points_refund_processed_ids', true);
        if (!is_array($processed)) {
            return false;
        }

        return in_array($refund_id, array_map('intval', $processed), true);
    }

    /**
     * @param WC_Order $order
     * @param int      $refund_id
     */
    private function mark_refund_id_processed_for_points($order, $refund_id) {
        $refund_id = (int) $refund_id;
        if ($refund_id <= 0) {
            return;
        }

        $processed = $order->get_meta('_intersoccer_points_refund_processed_ids', true);
        if (!is_array($processed)) {
            $processed = [];
        }
        if (!in_array($refund_id, array_map('intval', $processed), true)) {
            $processed[] = $refund_id;
            $order->update_meta_data('_intersoccer_points_refund_processed_ids', $processed);
            if (method_exists($order, 'save')) {
                $order->save();
            }
        }
    }

    /**
     * Cumulative refunded fraction in [0, 1] from refund objects, or 1 when order is cancelled/refunded.
     *
     * @param WC_Order $order
     * @return float
     */
    public function get_cumulative_refunded_fraction($order) {
        if (!method_exists($order, 'get_status')) {
            return 0.0;
        }

        $status = (string) $order->get_status();
        if (strpos($status, 'wc-') === 0) {
            $status = substr($status, 3);
        }
        if (in_array($status, ['cancelled', 'refunded'], true)) {
            return 1.0;
        }

        if (!method_exists($order, 'get_refunds')) {
            return 0.0;
        }

        $refunds = $order->get_refunds();
        if (!is_array($refunds) || empty($refunds)) {
            return 0.0;
        }

        $product_subtotal = $this->get_order_product_subtotal($order);
        $order_total_paid = max(0.0, (float) $order->get_total());
        $cumulative = 0.0;

        foreach ($refunds as $refund) {
            if (!is_object($refund)) {
                continue;
            }

            $cumulative += $this->get_refund_share_for_denominator(
                $refund,
                $order,
                $product_subtotal,
                $order_total_paid
            );
        }

        return max(0.0, min(1.0, $cumulative));
    }

    /**
     * Cumulative refunded fraction for earned reversal after points were awarded on a net total.
     * Pre-award refunds (netted at allocation) are excluded; later shares use the award net base.
     *
     * @param WC_Order $order
     * @return float
     */
    public function get_post_award_refunded_fraction($order) {
        if (!method_exists($order, 'get_status')) {
            return 0.0;
        }

        $status = (string) $order->get_status();
        if (strpos($status, 'wc-') === 0) {
            $status = substr($status, 3);
        }
        if (in_array($status, ['cancelled', 'refunded'], true)) {
            return 1.0;
        }

        $net_base = (float) $order->get_meta('_intersoccer_award_net_total', true);
        if ($net_base <= 0) {
            return $this->get_cumulative_refunded_fraction($order);
        }

        $netted = $order->get_meta('_intersoccer_award_netted_refund_ids', true);
        if (!is_array($netted)) {
            $netted = [];
        }
        $netted = array_map('intval', $netted);

        if (!method_exists($order, 'get_refunds')) {
            return 0.0;
        }

        $refunds = $order->get_refunds();
        if (!is_array($refunds) || empty($refunds)) {
            return 0.0;
        }

        $product_subtotal = $this->get_order_product_subtotal($order);
        $cumulative = 0.0;

        foreach ($refunds as $refund) {
            if (!is_object($refund)) {
                continue;
            }

            $refund_id = method_exists($refund, 'get_id') ? (int) $refund->get_id() : 0;
            if ($refund_id > 0 && in_array($refund_id, $netted, true)) {
                continue;
            }

            $cumulative += $this->get_refund_share_for_denominator(
                $refund,
                $order,
                $product_subtotal,
                $net_base
            );
        }

        return max(0.0, min(1.0, $cumulative));
    }

    /**
     * @param object   $refund
     * @param WC_Order $order
     * @param float    $product_subtotal
     * @param float    $cash_denominator
     * @return float
     */
    private function get_refund_share_for_denominator($refund, $order, $product_subtotal, $cash_denominator) {
        $line_items = method_exists($refund, 'get_items') ? $refund->get_items('line_item') : [];
        $line_refund_total = 0.0;
        if (is_array($line_items)) {
            foreach ($line_items as $item) {
                if (is_object($item) && method_exists($item, 'get_total')) {
                    $line_refund_total += abs((float) $item->get_total());
                }
            }
        }

        if ($line_refund_total > 0) {
            $denominator = $product_subtotal > 0 ? $product_subtotal : 0.0;
            $share = $denominator > 0 ? ($line_refund_total / $denominator) : 0.0;
        } else {
            $refund_amount = 0.0;
            if (method_exists($refund, 'get_amount')) {
                $refund_amount = abs((float) $refund->get_amount());
            }
            $denominator = $cash_denominator > 0 ? $cash_denominator : 0.0;
            $share = $denominator > 0 ? ($refund_amount / $denominator) : 0.0;
        }

        return max(0.0, min(1.0, (float) $share));
    }

    /**
     * Sum of order line item totals, or get_subtotal() when line items are unavailable.
     *
     * @param WC_Order $order
     * @return float
     */
    private function get_order_product_subtotal($order) {
        $sum = 0.0;
        if (method_exists($order, 'get_items')) {
            $items = $order->get_items('line_item');
            if (is_array($items) && !empty($items)) {
                foreach ($items as $item) {
                    if (is_object($item) && method_exists($item, 'get_total')) {
                        $sum += abs((float) $item->get_total());
                    }
                }
                if ($sum > 0) {
                    return $sum;
                }
            }
        }

        if (method_exists($order, 'get_subtotal')) {
            return max(0.0, (float) $order->get_subtotal());
        }

        return 0.0;
    }

    /**
     * Order total used for earning points (net of refunds when Woo exposes get_total_refunded).
     *
     * @param WC_Order $order
     * @return float
     */
    public function get_order_net_total_for_allocation($order) {
        $total = max(0.0, (float) $order->get_total());
        if (method_exists($order, 'get_total_refunded')) {
            $refunded = max(0.0, (float) $order->get_total_refunded());
            $total = max(0.0, $total - $refunded);
        }
        return $total;
    }

    /**
     * Ledger row id for this order's order_purchase credit (0 if none).
     *
     * @param int $order_id
     * @return int
     */
    public function get_order_purchase_log_row_id($order_id) {
        global $wpdb;

        $order_id = (int) $order_id;
        if ($order_id <= 0) {
            return 0;
        }

        $id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->points_log_table}
             WHERE order_id = %d AND transaction_type = 'order_purchase'
             ORDER BY id ASC
             LIMIT 1",
            $order_id
        ));

        return $id ? (int) $id : 0;
    }

    /**
     * Transaction types that form gift credit lots when points_amount is positive.
     *
     * @return string[]
     */
    public function get_points_gift_credit_types() {
        return (array) apply_filters('intersoccer_points_gift_credit_types', ['gift_received', 'admin_adjustment']);
    }

    /**
     * Gift points still available on the customer's balance (FIFO ledger walk).
     *
     * Definition:
     * - Load all of this customer's points-log rows ordered by created_at, id ascending
     *   (columns: id, transaction_type, points_amount).
     * - Walk FIFO: each positive row opens a lot (amount, is_gift). A lot is a gift when
     *   transaction_type is in {@see get_points_gift_credit_types()} and points_amount > 0
     *   (so positive admin_adjustment counts; negative rows do not).
     * - Each negative row consumes the oldest remaining lots first (gift or not) until its
     *   absolute amount is exhausted. Zero rows are ignored.
     * - Unspent gifted points = sum of remaining gift-lot amounts, capped at max(0, current balance).
     *
     * @param int $customer_id
     * @return int
     */
    public function get_unspent_gifted_points($customer_id) {
        global $wpdb;

        $customer_id = (int) $customer_id;
        if ($customer_id <= 0) {
            return 0;
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, transaction_type, points_amount FROM {$this->points_log_table}
             WHERE customer_id = %d
             ORDER BY created_at ASC, id ASC",
            $customer_id
        ));

        $gift_types = array_map('strval', $this->get_points_gift_credit_types());
        $lots = [];

        foreach ((array) $rows as $row) {
            $amount = (int) (is_array($row) ? ($row['points_amount'] ?? 0) : ($row->points_amount ?? 0));
            if ($amount === 0) {
                continue;
            }
            if ($amount > 0) {
                $type = (string) (is_array($row) ? ($row['transaction_type'] ?? '') : ($row->transaction_type ?? ''));
                $is_gift = in_array($type, $gift_types, true);
                $lots[] = ['amount' => $amount, 'is_gift' => $is_gift];
                continue;
            }

            $remaining = abs($amount);
            while ($remaining > 0 && !empty($lots)) {
                $take = min($lots[0]['amount'], $remaining);
                $lots[0]['amount'] -= $take;
                $remaining -= $take;
                if ($lots[0]['amount'] <= 0) {
                    array_shift($lots);
                }
            }
        }

        $gift_remaining = 0;
        foreach ($lots as $lot) {
            if (!empty($lot['is_gift'])) {
                $gift_remaining += (int) $lot['amount'];
            }
        }

        $balance = max(0, (int) $this->get_points_balance($customer_id));

        return min($gift_remaining, $balance);
    }

    /**
     * Points balance that may be debited when clawing back earned or referral points.
     *
     * Rule 2: gifted points are never clawed back. Clawback debit cap =
     * max(0, current balance − unspent gifted points). {@see get_unspent_gifted_points()}.
     *
     * @param int $customer_id
     * @param int $order_id Unused; kept for call-site compatibility.
     * @return int
     */
    public function get_attributable_balance_for_order_reversal($customer_id, $order_id) {
        $customer_id = (int) $customer_id;
        if ($customer_id <= 0) {
            return 0;
        }

        $balance = (int) $this->get_points_balance($customer_id);
        $unspent_gifted = $this->get_unspent_gifted_points($customer_id);

        return max(0, $balance - $unspent_gifted);
    }

    /**
     * Whether earned reversal for this order is blocked because points expired after purchase.
     *
     * @param int $order_id
     * @param int $customer_id
     * @return bool
     */
    public function customer_points_expired_blocks_reversal($order_id, $customer_id) {
        $order_id = (int) $order_id;
        $customer_id = (int) $customer_id;
        if ($customer_id <= 0) {
            return false;
        }

        $purchase_log_id = $this->get_order_purchase_log_row_id($order_id);
        $expired = false;
        if ($purchase_log_id > 0) {
            global $wpdb;
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->points_log_table}
                 WHERE customer_id = %d AND id > %d AND transaction_type = 'points_expired'",
                $customer_id,
                $purchase_log_id
            ));
            $expired = (int) $count > 0;
        }

        return (bool) apply_filters('intersoccer_points_reversal_points_expired', $expired, $order_id, $customer_id);
    }

    /**
     * Insert a zero-amount ledger row documenting a reversal write-off (balance unchanged).
     *
     * @param int    $customer_id
     * @param int    $order_id
     * @param int    $written_off
     * @param string $reason
     * @param float  $fraction
     * @param array  $extra_metadata
     * @return int|false Insert id or false
     */
    public function insert_reversal_write_off_log_row($customer_id, $order_id, $written_off, $reason, $fraction, array $extra_metadata = []) {
        global $wpdb;

        $customer_id = (int) $customer_id;
        $order_id = (int) $order_id;
        $written_off = (int) $written_off;
        if ($customer_id <= 0 || $written_off <= 0) {
            return false;
        }

        $balance = (int) $this->get_points_balance($customer_id);
        $metadata = array_merge([
            'written_off' => $written_off,
            'reason' => $reason,
            'order_id' => $order_id,
            'fraction' => $fraction,
        ], $extra_metadata);

        $result = $wpdb->insert(
            $this->points_log_table,
            [
                'customer_id' => $customer_id,
                'order_id' => $order_id,
                'transaction_type' => 'reversal_write_off',
                'points_amount' => 0,
                'points_balance' => $balance,
                'description' => sprintf('Write-off %d points for order #%d (%s)', $written_off, $order_id, $reason),
                'metadata' => wp_json_encode($metadata),
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s']
        );

        if ($result === false) {
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Record write-off in order meta and ledger; keeps SHORTFALL log line.
     *
     * @param WC_Order $order
     * @param int      $customer_id
     * @param int      $shortfall
     * @param string   $reason
     * @param float    $fraction
     * @param array    $extra_metadata
     */
    private function record_reversal_write_off($order, $customer_id, $shortfall, $reason, $fraction, array $extra_metadata = []) {
        $order_id = (int) $order->get_id();
        $shortfall = (int) $shortfall;
        if ($shortfall <= 0) {
            return;
        }

        $prior_shortfall = (int) $order->get_meta('_intersoccer_points_reversal_shortfall', true);
        $order->update_meta_data('_intersoccer_points_reversal_shortfall', $prior_shortfall + $shortfall);

        $prior_writeoff = (int) $order->get_meta('_intersoccer_points_reversal_writeoff_total', true);
        $order->update_meta_data('_intersoccer_points_reversal_writeoff_total', $prior_writeoff + $shortfall);

        $this->insert_reversal_write_off_log_row($customer_id, $order_id, $shortfall, $reason, $fraction, $extra_metadata);

        intersoccer_referral_log(
            "InterSoccer: SHORTFALL reversing earned points for order {$order_id}, customer {$customer_id}; write-off {$shortfall}, fraction {$fraction}"
        );
    }

    /**
     * @param WC_Order $order
     * @param float    $fraction
     * @param string   $reason
     * @param int|null $refund_id
     */
    private function sync_earned_points_reversal_for_order($order, $fraction, $reason, $refund_id = null) {
        $order_id = (int) $order->get_id();
        $customer_id = (int) $order->get_customer_id();
        if ($customer_id <= 0) {
            return;
        }

        $earned = (int) floor($this->get_points_allocated_for_order($order_id));
        if ($earned <= 0) {
            return;
        }

        $already_reversed = (int) $order->get_meta('_intersoccer_purchase_points_reversed_total', true);
        $total_meta_raw = $order->get_meta('_intersoccer_purchase_points_reversed_total', true);
        $has_total_meta = $total_meta_raw !== '' && $total_meta_raw !== false && $total_meta_raw !== null;
        if ((int) $order->get_meta('_intersoccer_purchase_points_reversed', true) === 1 && !$has_total_meta) {
            $already_reversed = $earned;
        } elseif (!$has_total_meta && (int) $order->get_meta('_intersoccer_purchase_points_reversed', true) !== 1) {
            $ledger_reversed = $this->get_abs_points_log_sum_for_order_transaction_types(
                $order_id,
                ['order_refund', 'order_cancelled']
            );
            $already_reversed = (int) min($earned, max(0, $ledger_reversed));
        }

        $target_reversed = intersoccer_proportional_floor_int($earned, $fraction);
        $delta = $target_reversed - $already_reversed;
        if ($delta <= 0) {
            if ($target_reversed >= $earned) {
                $order->update_meta_data('_intersoccer_purchase_points_reversed', 1);
                if (method_exists($order, 'save')) {
                    $order->save();
                }
            }
            return;
        }

        $write_off_reason = $reason;
        if ($this->customer_points_expired_blocks_reversal($order_id, $customer_id)) {
            $debit_amount = 0;
            $shortfall = $delta;
            $write_off_reason = 'expired';
        } else {
            $attributable = $this->get_attributable_balance_for_order_reversal($customer_id, $order_id);
            $debit_amount = min($delta, $attributable);
            $shortfall = $delta - $debit_amount;
            if ($shortfall > 0) {
                $write_off_reason = 'spent_or_gift_protected';
            }
        }

        $ledger_type = $reason === 'order_cancelled' ? 'order_cancelled' : 'order_refund';
        $metadata = [
            'refund_reason' => $reason,
            'fraction' => $fraction,
            'earned' => $earned,
            'cumulative_reversed' => $target_reversed,
            'requested_reversal' => $delta,
            'original_allocation' => $earned,
        ];
        if ($refund_id) {
            $metadata['refund_id'] = $refund_id;
        }
        if ($shortfall > 0) {
            $metadata['shortfall'] = $shortfall;
        }

        if ($debit_amount > 0) {
            $this->add_points_transaction(
                $customer_id,
                $ledger_type,
                -$debit_amount,
                $order_id,
                'Points deducted for refunded order #' . $order_id,
                $metadata
            );
        }

        if ($shortfall > 0) {
            $this->record_reversal_write_off($order, $customer_id, $shortfall, $write_off_reason, $fraction, [
                'refund_reason' => $reason,
            ]);
        }

        $order->update_meta_data('_intersoccer_purchase_points_reversed_total', $target_reversed);
        if ($target_reversed >= $earned) {
            $order->update_meta_data('_intersoccer_purchase_points_reversed', 1);
        }
        if (method_exists($order, 'save')) {
            $order->save();
        }

        if ($debit_amount > 0) {
            intersoccer_referral_log("InterSoccer: Deducted {$debit_amount} points from customer {$customer_id} for order {$order_id} (cumulative reversed {$target_reversed}/{$earned}, fraction {$fraction})");
        }
    }

    /**
     * @param WC_Order $order
     * @param float    $fraction
     * @param string   $reason
     * @param int|null $refund_id
     */
    private function sync_redeemed_points_return_for_order($order, $fraction, $reason, $refund_id = null) {
        $order_id = (int) $order->get_id();

        if ((int) $order->get_meta('_intersoccer_credits_deducted_on_completion', true) !== 1) {
            return;
        }

        $redeemed = (int) $order->get_meta('_intersoccer_points_redeemed', true);
        if ($redeemed <= 0) {
            return;
        }

        $already_returned = (int) $order->get_meta('_intersoccer_redeemed_points_returned_total', true);
        $returned_total_raw = $order->get_meta('_intersoccer_redeemed_points_returned_total', true);
        $has_returned_total_meta = $returned_total_raw !== '' && $returned_total_raw !== false && $returned_total_raw !== null;
        if ((int) $order->get_meta('_intersoccer_redeemed_points_returned', true) === 1 && !$has_returned_total_meta) {
            $already_returned = $redeemed;
        } elseif (!$has_returned_total_meta && (int) $order->get_meta('_intersoccer_redeemed_points_returned', true) !== 1) {
            $ledger_returned = $this->get_abs_points_log_sum_for_order_transaction_types(
                $order_id,
                ['points_refund']
            );
            $already_returned = (int) min($redeemed, max(0, $ledger_returned));
        }

        $target_returned = intersoccer_proportional_floor_int($redeemed, $fraction);
        $delta = $target_returned - $already_returned;
        if ($delta <= 0) {
            if ($target_returned >= $redeemed) {
                $order->update_meta_data('_intersoccer_redeemed_points_returned', 1);
                if (method_exists($order, 'save')) {
                    $order->save();
                }
            }
            return;
        }

        $user_id = (int) $order->get_customer_id();
        if ($user_id <= 0) {
            return;
        }

        $discount_amount = $order->get_meta('_intersoccer_discount_amount', true);
        $metadata = [
            'original_discount' => $discount_amount,
            'refund_reason' => $reason,
            'fraction' => $fraction,
            'redeemed' => $redeemed,
            'cumulative_returned' => $target_returned,
        ];
        if ($refund_id) {
            $metadata['refund_id'] = $refund_id;
        }

        $this->add_points_transaction(
            $user_id,
            'points_refund',
            $delta,
            $order_id,
            sprintf(__('Refunded %d points due to %s', 'intersoccer-referral'), $delta, $reason),
            $metadata
        );

        $order->update_meta_data('_intersoccer_redeemed_points_returned_total', $target_returned);
        if ($target_returned >= $redeemed) {
            $order->update_meta_data('_intersoccer_redeemed_points_returned', 1);
        }
        if (method_exists($order, 'save')) {
            $order->save();
        }

        intersoccer_referral_log("InterSoccer: Refunded {$delta} points for {$reason} order {$order_id} (cumulative returned {$target_returned}/{$redeemed}, fraction {$fraction})");
    }

    /**
     * Calculate points from currency amount
     * Returns integer points only - no fractional points
     * 
     * Phase 0: Now supports role-specific point acquisition rates
     * 
     * @param float $amount The currency amount in CHF
     * @param int $user_id Optional user ID to apply role-specific rate
     * @param string $context 'purchase' or 'referral'
     * @return int The number of points earned (integer only)
     */
    private function calculate_points_from_amount($amount, $user_id = null, $is_first_time = false, $context = 'purchase') {
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
        $rate = max(1, $this->get_points_rate_for_user($user_id, $context, $is_first_time));

        return (int) floor($amount / $rate);
    }

    /**
     * Calculate points earned from an order total using configured allocation rules.
     *
     * @param float    $order_total   Order total in CHF.
     * @param int|null $user_id       User earning the points.
     * @param string   $context       'purchase' or 'referral'.
     * @param bool     $is_first_time Whether this is the earner's first purchase (purchase context only).
     * @return int
     */
    public function calculate_points_from_order_total($order_total, $user_id = null, $context = 'purchase', $is_first_time = false) {
        return $this->calculate_points_from_amount($order_total, $user_id, $is_first_time, $context);
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
     * Insert the first points-balance row for a user.
     *
     * The update above missed, so no row is visible yet. add_user_meta checks
     * for an existing row and then writes, which is two steps. Two credits at
     * the same moment can both pass that check. The named lock makes the second
     * credit wait, look again, and add onto the row the first credit created.
     *
     * @param int    $user_id
     * @param string $meta_key
     * @param int    $delta
     * @param string $sql Update statement already prepared for this change.
     * @return bool True when one balance row now holds this change.
     */
    private function create_points_balance_row($user_id, $meta_key, $delta, $sql) {
        global $wpdb;

        $lock_name = 'intersoccer_pts_bal_' . (int) $user_id;
        $locked = is_object($wpdb)
            && method_exists($wpdb, 'get_var')
            && method_exists($wpdb, 'prepare')
            && (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, 5)) === 1;
        if (!$locked) {
            return false;
        }

        try {
            $updated = $wpdb->query($sql);
            if ($updated === false) {
                return false;
            }
            if ((int) $updated === 1) {
                return true;
            }

            $created = function_exists('add_user_meta')
                ? add_user_meta($user_id, $meta_key, $delta, true)
                : false;
            if ($created) {
                return true;
            }

            // The other credit inserted the row while we waited.
            $updated = $wpdb->query($sql);
            return (int) $updated === 1;
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }
    }

    /**
     * Change the stored points balance by $delta in one database update.
     *
     * A positive amount adds points. A negative amount takes points away.
     * When $only_if_balance_covers is true, points are taken only when the
     * stored balance is large enough. If there is no balance row and the
     * change is allowed, the row is created. Returns the new balance, or
     * false when the change did not happen.
     *
     * @param int  $user_id
     * @param int  $delta
     * @param bool $only_if_balance_covers
     * @return int|false
     */
    public function change_points_balance($user_id, $delta, $only_if_balance_covers = false) {
        global $wpdb;

        $user_id = (int) $user_id;
        $delta = (int) $delta;
        if ($user_id <= 0 || $delta === 0) {
            return false;
        }

        $meta_key = 'intersoccer_points_balance';
        if ($delta < 0 && $only_if_balance_covers) {
            $amount = abs($delta);
            $sql = $wpdb->prepare(
                "UPDATE {$wpdb->usermeta}
                 SET meta_value = CAST(meta_value AS SIGNED) - %d
                 WHERE user_id = %d
                   AND meta_key = %s
                   AND CAST(meta_value AS SIGNED) >= %d
                 LIMIT 1",
                $amount,
                $user_id,
                $meta_key,
                $amount
            );
        } elseif ($delta < 0) {
            $amount = abs($delta);
            $sql = $wpdb->prepare(
                "UPDATE {$wpdb->usermeta}
                 SET meta_value = CAST(meta_value AS SIGNED) - %d
                 WHERE user_id = %d
                   AND meta_key = %s
                 LIMIT 1",
                $amount,
                $user_id,
                $meta_key
            );
        } else {
            $sql = $wpdb->prepare(
                "UPDATE {$wpdb->usermeta}
                 SET meta_value = CAST(meta_value AS SIGNED) + %d
                 WHERE user_id = %d
                   AND meta_key = %s
                 LIMIT 1",
                $delta,
                $user_id,
                $meta_key
            );
        }

        $updated = $wpdb->query($sql);
        if ($updated === false) {
            return false;
        }
        if ((int) $updated !== 1) {
            // A guarded debit must not invent a balance row.
            if ($only_if_balance_covers) {
                return false;
            }
            // No row yet. Lock so two first credits cannot each insert one.
            if ($this->create_points_balance_row($user_id, $meta_key, $delta, $sql) !== true) {
                return false;
            }
        }

        if (function_exists('wp_cache_delete')) {
            wp_cache_delete($user_id, 'user_meta');
        }

        $remaining = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s LIMIT 1",
            $user_id,
            $meta_key
        ));

        return (int) $remaining;
    }

    /**
     * Balance written by the latest successful ledger entry in this request.
     *
     * @var int|null
     */
    private $balance_after_last_transaction = null;

    /**
     * Balance from the latest successful ledger entry, or null if none yet.
     *
     * @return int|null
     */
    public function get_balance_after_last_transaction() {
        return $this->balance_after_last_transaction;
    }

    /**
     * Add a points transaction to the ledger
     *
     * Uses row-level locking via SELECT ... FOR UPDATE to prevent race conditions
     * when multiple concurrent transactions modify the same customer's balance.
     */
    public function add_points_transaction($customer_id, $transaction_type, $points_amount, $order_id = null, $description = '', $metadata = [], $only_if_balance_covers = false) {
        global $wpdb;

        $customer_id = (int) $customer_id;
        $points_amount = (int) $points_amount;

        // Start transaction to ensure atomic balance update
        $wpdb->query('START TRANSACTION');

        try {
            // Lock the ledger row to prevent race conditions (row-level lock via FOR UPDATE)
            // The result is intentionally discarded; we use meta balance as the authoritative
            // starting point (see PR #41 for rationale on ledger vs meta divergence).
            $wpdb->get_var($wpdb->prepare(
                "SELECT points_balance FROM {$this->points_log_table}
                 WHERE customer_id = %d
                 ORDER BY created_at DESC, id DESC
                 LIMIT 1
                 FOR UPDATE",
                $customer_id
            ));

            // Add or take the points on the stored balance. Writing a balance
            // that was read earlier can put a gift debit back on the sender.
            $new_balance = $this->change_points_balance($customer_id, $points_amount, (bool) $only_if_balance_covers);
            $this->balance_after_last_transaction = $new_balance;
            if ($new_balance === false) {
                $wpdb->query('ROLLBACK');
                intersoccer_referral_log("InterSoccer: Failed to change points balance for customer " . $customer_id);
                return false;
            }

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
                ['%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s']
            );

            if ($result === false) {
                $wpdb->query('ROLLBACK');
                intersoccer_referral_log("InterSoccer: Failed to insert points transaction: " . $wpdb->last_error);
                return false;
            }

            $insert_id = $wpdb->insert_id;

            $wpdb->query('COMMIT');

            return $insert_id;

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            intersoccer_referral_log("InterSoccer: Points transaction failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get current redeemable points balance for a customer.
     *
     * User meta is the balance shown in admin and at checkout. Ledger last-row
     * is a fallback when meta has never been written. A stored 0 is a real
     * balance (do not treat it as missing).
     *
     * @param int $customer_id The customer's user ID
     * @return int The customer's current points balance (integer only)
     */
    public function get_points_balance($customer_id) {
        $meta = get_user_meta($customer_id, 'intersoccer_points_balance', true);
        if ($meta !== '' && $meta !== false && $meta !== null) {
            return intval($meta);
        }

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
     * Sum of absolute points_amount for an order and given ledger transaction types.
     *
     * @param int      $order_id
     * @param string[] $transaction_types
     * @return int
     */
    public function get_abs_points_log_sum_for_order_transaction_types($order_id, array $transaction_types) {
        global $wpdb;

        $transaction_types = array_values(array_filter(array_map('strval', $transaction_types)));
        if ($order_id <= 0 || empty($transaction_types)) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($transaction_types), '%s'));
        $sql = "SELECT COALESCE(SUM(ABS(points_amount)), 0) FROM {$this->points_log_table}
                WHERE order_id = %d AND transaction_type IN ($placeholders)";
        $prepared = $wpdb->prepare($sql, array_merge([(int) $order_id], $transaction_types));

        return (int) $wpdb->get_var($prepared);
    }

    /**
     * Customer Points Adjust / History access (admin or shop manager).
     *
     * @return bool
     */
    private function user_can_manage_points() {
        if (class_exists('InterSoccer_Admin_Points')) {
            return InterSoccer_Admin_Points::user_can_manage_points();
        }

        return current_user_can('manage_options') || current_user_can('manage_woocommerce');
    }

    /**
     * Get points balance via AJAX
     */
    public function get_points_balance_ajax() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!$this->user_can_manage_points()) {
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
        if (!$this->user_can_manage_points()) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        $customer_id = intval($_POST['customer_id'] ?? 0);
        if ($customer_id <= 0) {
            wp_send_json_error(['message' => 'Customer ID is required']);
            return;
        }

        $limit = min(200, max(1, intval($_POST['limit'] ?? 100)));
        $offset = max(0, intval($_POST['offset'] ?? 0));

        global $wpdb;

        $transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->points_log_table}
             WHERE customer_id = %d
             ORDER BY created_at DESC, id DESC
             LIMIT %d OFFSET %d",
            $customer_id,
            $limit,
            $offset
        ));

        if (!is_array($transactions)) {
            $transactions = [];
        }

        $formatted_transactions = [];
        foreach ($transactions as $transaction) {
            $order_id = isset($transaction->order_id) ? absint($transaction->order_id) : 0;
            $order_url = '';
            if ($order_id && function_exists('admin_url')) {
                $order_url = admin_url('post.php?post=' . $order_id . '&action=edit');
            }

            $metadata = null;
            if (!empty($transaction->metadata) && is_string($transaction->metadata)) {
                $decoded = json_decode($transaction->metadata, true);
                $metadata = is_array($decoded) ? $decoded : null;
            }

            $formatted_transactions[] = [
                'id' => $transaction->id,
                'customer_id' => $transaction->customer_id,
                'order_id' => $order_id,
                'order_url' => $order_url,
                'transaction_type' => $transaction->transaction_type,
                'points_amount' => intval($transaction->points_amount),
                'points_balance' => intval($transaction->points_balance),
                'description' => $transaction->description,
                'created_at' => $transaction->created_at,
                'metadata' => $metadata,
            ];
        }

        $redeemable_balance = $this->get_points_balance($customer_id);
        $ledger_running_balance = null;
        if (!empty($formatted_transactions)) {
            $ledger_running_balance = intval($formatted_transactions[0]['points_balance']);
        }

        $balance_mismatch = ($ledger_running_balance === null)
            ? ($redeemable_balance !== 0)
            : ($redeemable_balance !== $ledger_running_balance);

        wp_send_json_success([
            'transactions' => $formatted_transactions,
            'redeemable_balance' => $redeemable_balance,
            'ledger_running_balance' => $ledger_running_balance,
            'balance_mismatch' => $balance_mismatch,
        ]);
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
             WHERE points_amount > 0
               AND transaction_type NOT IN ('gift_sent', 'gift_received', 'gift_returned') {$where_clause}",
            $params
        ));

        // Total points spent/redeemed
        $total_spent = abs($wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points_amount), 0) FROM {$this->points_log_table}
             WHERE points_amount < 0
               AND transaction_type NOT IN ('gift_sent', 'gift_received', 'gift_returned') {$where_clause}",
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

        // Store redemption details in order meta
        $order->update_meta_data('_intersoccer_points_redeemed', $points_to_redeem);
        $order->update_meta_data('_intersoccer_discount_amount', $discount_amount);
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        if (method_exists($order, 'save')) {
            $order->save();
        }

        // Clear session
        WC()->session->set('intersoccer_points_to_redeem', 0);

        intersoccer_referral_log("InterSoccer: Redeemed {$points_to_redeem} points for order {$order->get_id()}");
    }

    /**
     * Re-award debited earned points and retake returned redeemed points after reinstatement.
     *
     * @param WC_Order $order
     */
    public function sync_reinstatement_for_order($order) {
        $order_id = (int) $order->get_id();
        $customer_id = (int) $order->get_customer_id();
        if ($customer_id <= 0) {
            return;
        }

        $debited = $this->get_abs_points_log_sum_for_order_transaction_types(
            $order_id,
            ['order_refund', 'order_cancelled']
        );
        $reinstated = $this->get_abs_points_log_sum_for_order_transaction_types(
            $order_id,
            ['reversal_reinstated']
        );
        $to_reaward = max(0, $debited - $reinstated);
        if ($to_reaward > 0) {
            $this->add_points_transaction(
                $customer_id,
                'reversal_reinstated',
                $to_reaward,
                $order_id,
                sprintf('Reinstated %d earned points for order #%d', $to_reaward, $order_id),
                ['fraction' => 1.0]
            );
        }

        $returned = $this->get_abs_points_log_sum_for_order_transaction_types($order_id, ['points_refund']);
        $retaken = $this->get_abs_points_log_sum_for_order_transaction_types($order_id, ['redeemed_retaken']);
        $to_retake = max(0, $returned - $retaken);
        if ($to_retake > 0) {
            $debitable = $this->get_attributable_balance_for_order_reversal($customer_id, $order_id);
            $debit = min($to_retake, $debitable);
            $shortfall = $to_retake - $debit;
            if ($debit > 0) {
                $this->add_points_transaction(
                    $customer_id,
                    'redeemed_retaken',
                    -$debit,
                    $order_id,
                    sprintf('Retook %d redeemed points for reinstated order #%d', $debit, $order_id),
                    ['fraction' => 1.0]
                );
            }
            if ($shortfall > 0) {
                $this->record_reversal_write_off($order, $customer_id, $shortfall, 'reinstated_retake_shortfall', 1.0);
            }
        }

        if ((int) $order->get_meta('_intersoccer_unpaid_redeemed_released', true) === 1) {
            $order->delete_meta_data('_intersoccer_unpaid_redeemed_released');
            if (method_exists($order, 'save')) {
                $order->save();
            }
        }

        $this->reset_refund_reversal_running_totals($order);
    }

    /**
     * Retake redeemed points when an unpaid-timeout order is later paid.
     *
     * @param WC_Order $order
     */
    public function maybe_retake_redeemed_points_after_payment($order) {
        $unpaid_released = (int) $order->get_meta('_intersoccer_unpaid_redeemed_released', true) === 1;
        $returned_on_failure = (int) $order->get_meta('_intersoccer_redeemed_points_returned_total', true) > 0;

        if (!$unpaid_released && !$returned_on_failure) {
            return;
        }

        $order_id = (int) $order->get_id();
        $customer_id = (int) $order->get_customer_id();
        if ($customer_id <= 0) {
            return;
        }

        $redeemed = (int) $order->get_meta('_intersoccer_points_redeemed', true);
        $returned = (int) $order->get_meta('_intersoccer_redeemed_points_returned_total', true);
        if ($returned <= 0 && (int) $order->get_meta('_intersoccer_redeemed_points_returned', true) === 1) {
            $returned = $redeemed;
        }
        $retaken = $this->get_abs_points_log_sum_for_order_transaction_types($order_id, ['redeemed_retaken']);
        $to_retake = max(0, min($redeemed, $returned) - $retaken);
        if ($to_retake <= 0) {
            return;
        }

        $debitable = $this->get_attributable_balance_for_order_reversal($customer_id, $order_id);
        $debit = min($to_retake, $debitable);
        $shortfall = $to_retake - $debit;
        if ($debit > 0) {
            $this->add_points_transaction(
                $customer_id,
                'redeemed_retaken',
                -$debit,
                $order_id,
                sprintf('Retook %d redeemed points after payment for order #%d', $debit, $order_id),
                ['fraction' => 1.0, 'reason' => 'unpaid_timeout_paid']
            );
        }
        if ($shortfall > 0) {
            $this->record_reversal_write_off($order, $customer_id, $shortfall, 'reinstated_retake_shortfall', 1.0);
        }

        if ($unpaid_released) {
            $order->delete_meta_data('_intersoccer_unpaid_redeemed_released');
        }
        if (method_exists($order, 'save')) {
            $order->save();
        }
    }

    /**
     * Reset per-order reversal counters after reinstatement so a later refund can run again.
     *
     * @param WC_Order $order
     */
    public function reset_refund_reversal_running_totals($order) {
        $order->update_meta_data('_intersoccer_purchase_points_reversed_total', 0);
        $order->update_meta_data('_intersoccer_purchase_points_reversed', 0);
        $order->update_meta_data('_intersoccer_redeemed_points_returned_total', 0);
        $order->update_meta_data('_intersoccer_redeemed_points_returned', 0);
        if (method_exists($order, 'save')) {
            $order->save();
        }
    }

    /**
     * Return redeemed points for stale pending/on-hold orders (daily cron).
     */
    public function release_unpaid_redeemed_points_for_stale_orders() {
        $days = (int) get_option('intersoccer_unpaid_redeemed_release_days', 14);
        if ($days <= 0 || !function_exists('wc_get_orders')) {
            return;
        }

        $cutoff = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $per_page = 200;
        $max_per_run = 1000;
        $processed = 0;
        $page = 1;

        while ($processed < $max_per_run) {
            $orders = wc_get_orders([
                'status' => ['pending', 'on-hold'],
                'date_created' => '<' . $cutoff,
                'limit' => $per_page,
                'page' => $page,
                'orderby' => 'date',
                'order' => 'ASC',
                'return' => 'objects',
            ]);

            if (empty($orders)) {
                break;
            }

            $batch_count = 0;
            foreach ((array) $orders as $order) {
                $batch_count++;
                if (!is_object($order) || !method_exists($order, 'get_id')) {
                    continue;
                }
                if ((int) $order->get_meta('_intersoccer_credits_deducted_on_completion', true) !== 1) {
                    continue;
                }
                if ((int) $order->get_meta('_intersoccer_unpaid_redeemed_released', true) === 1) {
                    continue;
                }
                $redeemed = (int) $order->get_meta('_intersoccer_points_redeemed', true);
                if ($redeemed <= 0) {
                    continue;
                }
                $already = (int) $order->get_meta('_intersoccer_redeemed_points_returned_total', true);
                if ($already >= $redeemed || (int) $order->get_meta('_intersoccer_redeemed_points_returned', true) === 1) {
                    continue;
                }

                $delta = $redeemed - $already;
                $user_id = (int) $order->get_customer_id();
                if ($user_id <= 0 || $delta <= 0) {
                    continue;
                }

                $this->add_points_transaction(
                    $user_id,
                    'points_refund',
                    $delta,
                    (int) $order->get_id(),
                    sprintf(__('Refunded %d points due to %s', 'intersoccer-referral'), $delta, 'unpaid_timeout'),
                    [
                        'refund_reason' => 'unpaid_timeout',
                        'fraction' => 1.0,
                        'redeemed' => $redeemed,
                        'cumulative_returned' => $redeemed,
                    ]
                );

                $order->update_meta_data('_intersoccer_redeemed_points_returned_total', $redeemed);
                $order->update_meta_data('_intersoccer_redeemed_points_returned', 1);
                $order->update_meta_data('_intersoccer_unpaid_redeemed_released', 1);
                if (method_exists($order, 'save')) {
                    $order->save();
                }

                $processed++;
                if ($processed >= $max_per_run) {
                    break;
                }
            }

            if ($batch_count < $per_page) {
                break;
            }
            $page++;
        }
    }

    /**
     * Refund points when order is cancelled or failed
     */
    public function refund_points_on_cancellation($order_id) {
        $this->sync_refund_reversals((int) $order_id, 'order_cancelled', [
            'force_fraction_one' => true,
            'skip_earned_reversal' => true,
            'earned_only' => false,
        ]);
    }

    public function refund_points_on_failure($order_id) {
        $this->sync_refund_reversals((int) $order_id, 'order_failed', [
            'force_fraction_one' => true,
            'skip_earned_reversal' => true,
        ]);
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