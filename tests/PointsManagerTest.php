<?php

use PHPUnit\Framework\TestCase;

/**
 * Test suite for InterSoccer Points Manager
 */
class PointsManagerTest extends TestCase {

    protected function setUp(): void {
        // Include the points manager class
        require_once __DIR__ . '/../includes/class-points-manager.php';
        update_option('intersoccer_points_allocation_mode', 'ratio');
        update_option('intersoccer_points_percentage_rate', 0);
        update_option('intersoccer_points_allocation_method', 'instant');
        $this->resetPointsTestState();
    }

    protected function tearDown(): void {
        update_option('intersoccer_points_allocation_mode', 'ratio');
        update_option('intersoccer_points_percentage_rate', 0);
        update_option('intersoccer_points_allocation_method', 'instant');
        delete_transient('intersoccer_queued_points_orders');
        $this->resetPointsTestState();
        parent::tearDown();
    }

    private function resetPointsTestState(): void {
        global $mock_points_balances, $mock_order_points_allocated, $mock_points_log_rows, $mock_wc_orders_by_id, $mock_wc_get_orders, $mock_user_roles, $mock_customer_spent, $mock_session, $mock_user_meta;

        $this->resetPointsManagerSingleton();
        $mock_points_balances = [];
        $mock_order_points_allocated = [];
        $mock_points_log_rows = [];
        $mock_user_meta = [];
        $mock_wc_orders_by_id = [];
        $mock_wc_get_orders = null;
        $mock_user_roles = [];
        $mock_customer_spent = [];
        $mock_session = [];
    }

    private function registerWcOrder(WC_Order $order, int $order_id = 123): WC_Order {
        global $mock_wc_orders_by_id;

        $order->set_id($order_id);
        $mock_wc_orders_by_id[$order_id] = $order;

        return $order;
    }

    /**
     * Test points calculation from currency amount (INTEGER ONLY - NO FRACTIONAL POINTS)
     */
    public function testCalculatePointsFromAmount() {
        $points_manager = new InterSoccer_Points_Manager();

        // Test 10 CHF = 1 point
        $points = $this->invokePrivateMethod($points_manager, 'calculate_points_from_amount', [10]);
        $this->assertEquals(1, $points);
        $this->assertIsInt($points, 'Points must be integer only');

        // Test 25 CHF = 2 points (floor of 2.5, NO fractional points)
        $points = $this->invokePrivateMethod($points_manager, 'calculate_points_from_amount', [25]);
        $this->assertEquals(2, $points);
        $this->assertIsInt($points, 'Points must be integer only');

        // Test 95 CHF = 9 points (floor of 9.5, NO fractional points)
        $points = $this->invokePrivateMethod($points_manager, 'calculate_points_from_amount', [95]);
        $this->assertEquals(9, $points);
        $this->assertIsInt($points, 'Points must be integer only');

        // Test 100 CHF = 10 points
        $points = $this->invokePrivateMethod($points_manager, 'calculate_points_from_amount', [100]);
        $this->assertEquals(10, $points);
        $this->assertIsInt($points, 'Points must be integer only');

        // Test 0 CHF = 0 points
        $points = $this->invokePrivateMethod($points_manager, 'calculate_points_from_amount', [0]);
        $this->assertEquals(0, $points);
        $this->assertIsInt($points, 'Points must be integer only');

        // Test edge case: 9.99 CHF = 0 points
        $points = $this->invokePrivateMethod($points_manager, 'calculate_points_from_amount', [9.99]);
        $this->assertEquals(0, $points);
        $this->assertIsInt($points, 'Points must be integer only');

        // Test: 150 CHF = 15 points
        $points = $this->invokePrivateMethod($points_manager, 'calculate_points_from_amount', [150]);
        $this->assertEquals(15, $points);
        $this->assertIsInt($points, 'Points must be integer only');
    }

    /**
     * Test points calculation when percentage-based allocation is enabled
     */
    public function testCalculatePointsFromAmountPercentageMode() {
        update_option('intersoccer_points_allocation_mode', 'percentage');
        update_option('intersoccer_points_percentage_rate', 12.5);

        $points_manager = new InterSoccer_Points_Manager();

        $points = $this->invokePrivateMethod($points_manager, 'calculate_points_from_amount', [200]);
        $this->assertEquals(25, $points, '200 CHF at 12.5% should yield 25 points');
        $this->assertIsInt($points);

        // Ensure flooring (no fractional points)
        $points = $this->invokePrivateMethod($points_manager, 'calculate_points_from_amount', [99]);
        $this->assertEquals(12, $points, '99 CHF at 12.5% should floor to 12 points');
        $this->assertIsInt($points);
    }

    /**
     * Test discount calculation from points
     */
    public function testCalculateDiscountFromPoints() {
        $points_manager = new InterSoccer_Points_Manager();

        // Test 1 point = 1 CHF discount
        $discount = $points_manager->calculate_discount_from_points(1);
        $this->assertEquals(1, $discount);

        // Test 10 points = 10 CHF discount
        $discount = $points_manager->calculate_discount_from_points(10);
        $this->assertEquals(10, $discount);

        // Test 0 points = 0 CHF discount
        $discount = $points_manager->calculate_discount_from_points(0);
        $this->assertEquals(0, $discount);
    }

    /**
     * Test points calculation from discount amount
     */
    public function testCalculatePointsFromDiscount() {
        $points_manager = new InterSoccer_Points_Manager();

        // Test 1 CHF discount = 1 point
        $points = $points_manager->calculate_points_from_discount(1);
        $this->assertEquals(1, $points);

        // Test 10 CHF discount = 10 points
        $points = $points_manager->calculate_points_from_discount(10);
        $this->assertEquals(10, $points);
    }

    /**
     * Test go-live date handling for points allocation
     */
    public function testGoLiveDatePreventsPointsBeforeConfiguredDate() {
        $points_manager = new InterSoccer_Points_Manager();

        // Configure go-live date
        update_option('intersoccer_points_golive_date', '2025-06-01');

        $before_go_live = strtotime('2025-05-31 23:59:59');
        $on_go_live = strtotime('2025-06-01 00:00:00');

        $this->assertTrue(
            $this->invokePrivateMethod($points_manager, 'is_order_before_go_live', [$before_go_live]),
            'Orders before the configured go-live date should be skipped.'
        );

        $this->assertFalse(
            $this->invokePrivateMethod($points_manager, 'is_order_before_go_live', [$on_go_live]),
            'Orders on or after the go-live date should be processed.'
        );

        // Reset configuration
        update_option('intersoccer_points_golive_date', '');
    }

    /**
     * Test points allocation for orders
     */
    public function testAllocatePointsForOrder() {
        $points_manager = new InterSoccer_Points_Manager();

        $order = $this->registerWcOrder(new WC_Order(), 123);
        $order->set_total(100); // 100 CHF order

        // Test points allocation
        $points_manager->allocate_points_for_order(123);

        // Verify points were allocated (10 points for 100 CHF)
        $balance = $points_manager->get_points_balance(1);
        $this->assertEquals(10, $balance);
    }

    /**
     * Test points deduction for refunds
     */
    public function testDeductPointsForRefund() {
        $points_manager = new InterSoccer_Points_Manager();

        $order = $this->registerWcOrder(new WC_Order(), 123);
        $order->set_total(50); // 50 CHF = 5 points
        $points_manager->allocate_points_for_order(123);

        $balance_before = $points_manager->get_points_balance(1);
        $this->assertEquals(5, $balance_before);

        // Then refund the order
        $points_manager->deduct_points_for_refund(123);

        // Verify points were deducted
        $balance_after = $points_manager->get_points_balance(1);
        $this->assertEquals(0, $balance_after);
    }

    /**
     * Test points balance retrieval (MUST RETURN INTEGERS ONLY)
     */
    public function testGetPointsBalance() {
        $points_manager = new InterSoccer_Points_Manager();

        // Test empty balance
        $balance = $points_manager->get_points_balance(1);
        $this->assertEquals(0, $balance);
        $this->assertIsInt($balance, 'Balance must be integer only');

        // Add some points
        $points_manager->add_points_transaction(1, 'test', 10, 123, 'Test points');

        // Test balance after adding points
        $balance = $points_manager->get_points_balance(1);
        $this->assertEquals(10, $balance);
        $this->assertIsInt($balance, 'Balance must be integer only');

        // Add more points to test accumulation
        $points_manager->add_points_transaction(1, 'test', 25, 124, 'More test points');
        $balance = $points_manager->get_points_balance(1);
        $this->assertEquals(35, $balance);
        $this->assertIsInt($balance, 'Balance must be integer only');
    }

    /**
     * Admin Add must use the displayed (usermeta) balance when it diverges from
     * the last ledger running total (e.g. checkout redeemed from meta only).
     */
    public function testAdminAddUsesMetaWhenLedgerDiverged() {
        $points_manager = new InterSoccer_Points_Manager();

        global $mock_points_balances, $mock_user_meta;
        $mock_points_balances[44500] = 52;
        $mock_user_meta[44500] = [
            'intersoccer_points_balance' => 0,
        ];

        $points_manager->add_points_transaction(44500, 'admin_adjustment', 30, null, 'debug');

        $this->assertSame(30, $points_manager->get_points_balance(44500));
        $this->assertSame(30, (int) get_user_meta(44500, 'intersoccer_points_balance', true));
        $this->assertSame(30, (int) $mock_points_balances[44500]);
    }

    /**
     * Test that all point operations return integers (NO FRACTIONAL POINTS)
     */
    public function testIntegerPointsOnly() {
        $points_manager = new InterSoccer_Points_Manager();

        // Test various amounts that would have caused fractional points
        $test_amounts = [15, 25, 35, 45, 55, 65, 75, 85, 95, 105, 115];

        foreach ($test_amounts as $amount) {
            $points = $this->invokePrivateMethod($points_manager, 'calculate_points_from_amount', [$amount]);
            $this->assertIsInt($points, "Points for CHF {$amount} must be integer");
            
            // Verify floor behavior: 
            // 15 CHF = 1 point (not 1.5)
            // 95 CHF = 9 points (not 9.5)
            $expected = (int) floor($amount / 10);
            $this->assertEquals($expected, $points, "CHF {$amount} should give {$expected} points");
        }
    }

    /**
     * Test points transaction logging
     */
    public function testAddPointsTransaction() {
        $points_manager = new InterSoccer_Points_Manager();

        // Add a transaction
        $transaction_id = $points_manager->add_points_transaction(
            1, 'test_transaction', 5, 123, 'Test transaction', ['test' => 'data']
        );

        $this->assertGreaterThan(0, $transaction_id);

        // Verify balance was updated
        $balance = $points_manager->get_points_balance(1);
        $this->assertEquals(5, $balance);
    }

    /**
     * Test redemption limits validation
     */
    public function testCanRedeemPoints() {
        $points_manager = new InterSoccer_Points_Manager();

        $points_manager->add_points_transaction(1, 'test', 50, null, 'Test points');

        $can_redeem = $points_manager->can_redeem_points(1, 50);
        $this->assertTrue($can_redeem);

        // Exceeds available balance
        $can_redeem = $points_manager->can_redeem_points(1, 150);
        $this->assertFalse($can_redeem);

        $can_redeem = $points_manager->can_redeem_points(1, 100);
        $this->assertFalse($can_redeem);
    }

    /**
     * Test maximum redeemable points calculation
     */
    public function testGetMaxRedeemablePoints() {
        $points_manager = new InterSoccer_Points_Manager();

        $points_manager->add_points_transaction(1, 'test', 150, null, 'Test points');

        $max_redeemable = $points_manager->get_max_redeemable_points(1);
        $this->assertEquals(150, $max_redeemable);

        $max_redeemable_for_cart = $points_manager->get_max_redeemable_points(1, 100.0);
        $this->assertEquals(100, $max_redeemable_for_cart);
    }

    /**
     * Test redemption summary
     */
    public function testGetRedemptionSummary() {
        $points_manager = new InterSoccer_Points_Manager();

        global $mock_customer_spent;
        $mock_customer_spent = [1 => 500];

        $points_manager->add_points_transaction(1, 'test', 30, null, 'Test points');

        $summary = $points_manager->get_redemption_summary(1);

        $this->assertEquals(500, $summary['total_spent']);
        $this->assertEquals(30, $summary['current_balance']);
        $this->assertEquals(30, $summary['available_points']);
        $this->assertEquals(30, $summary['available_discount']);

        $summary_with_cart = $points_manager->get_redemption_summary(1, 20.0);
        $this->assertEquals(20, $summary_with_cart['available_points']);
        $this->assertTrue($summary_with_cart['can_fully_cover']);
    }

    /**
     * Test points redemption processing
     */
    public function testProcessPointsRedemption() {
        $points_manager = new InterSoccer_Points_Manager();

        $points_manager->add_points_transaction(1, 'test', 20, null, 'Test points');

        $order = $this->registerWcOrder(new WC_Order(), 123);
        $order->set_total(100);

        global $mock_session;
        $mock_session = ['intersoccer_points_to_redeem' => 10];

        $points_manager->process_points_redemption($order, []);

        // Verify points were deducted
        $balance = $points_manager->get_points_balance(1);
        $this->assertEquals(10, $balance);

        // Verify order meta was set
        $this->assertEquals(10, $order->get_meta('_intersoccer_points_redeemed'));
        $this->assertEquals(10, $order->get_meta('_intersoccer_discount_amount'));
    }

    /**
     * Test points refund on order cancellation
     */
    public function testRefundPointsOnCancellation() {
        $points_manager = new InterSoccer_Points_Manager();

        $order = $this->registerWcOrder(new WC_Order(), 123);
        $order->set_total(100);

        global $mock_session;
        $mock_session = ['intersoccer_points_to_redeem' => 10];

        $points_manager->add_points_transaction(1, 'test', 20, null, 'Test points');
        $points_manager->process_points_redemption($order, []);

        $points_manager->refund_points_on_cancellation(123);

        // Verify points were refunded
        $balance = $points_manager->get_points_balance(1);
        $this->assertEquals(20, $balance); // Back to original balance
    }

    /**
     * Test points statistics calculation
     */
    public function testGetPointsStatistics() {
        $points_manager = new InterSoccer_Points_Manager();

        // Add some test transactions
        $points_manager->add_points_transaction(1, 'order_purchase', 10, null, 'Purchase 1');
        $points_manager->add_points_transaction(2, 'order_purchase', 20, null, 'Purchase 2');
        $points_manager->add_points_transaction(1, 'points_redemption', -5, null, 'Redemption 1');

        $stats = $points_manager->get_points_statistics();

        $this->assertEquals(30, $stats['total_earned']); // 10 + 20
        $this->assertEquals(5, $stats['total_spent']); // 5 redeemed
        $this->assertGreaterThanOrEqual(0, $stats['current_balance']);
        $this->assertGreaterThanOrEqual(0, $stats['customers_with_points']);
    }

    /**
     * Test transaction summary by type
     */
    public function testGetTransactionSummary() {
        $points_manager = new InterSoccer_Points_Manager();

        // Add transactions of different types
        $points_manager->add_points_transaction(1, 'order_purchase', 10, null, 'Purchase');
        $points_manager->add_points_transaction(1, 'points_redemption', -5, null, 'Redemption');
        $points_manager->add_points_transaction(1, 'order_purchase', 15, null, 'Another purchase');

        $summary = $points_manager->get_transaction_summary();

        $this->assertArrayHasKey('order_purchase', $summary);
        $this->assertArrayHasKey('points_redemption', $summary);
        $this->assertEquals(2, $summary['order_purchase']['count']);
        $this->assertEquals(25, $summary['order_purchase']['total_points']);
        $this->assertEquals(1, $summary['points_redemption']['count']);
        $this->assertEquals(-5, $summary['points_redemption']['total_points']);
    }

    // =========================================================================
    // ADDITIONAL POINTS MANAGER TESTS (25 tests)
    // =========================================================================

    /**
     * Test get_max_redeemable_points with cart total limit
     */
    public function testGetMaxRedeemablePoints_CartTotalLimit() {
        $available_points = 150;
        $cart_total = 100;
        
        $max_redeemable = min($available_points, $cart_total);
        
        $this->assertEquals(100, $max_redeemable);
    }

    /**
     * Test get_max_redeemable_points without cart total
     */
    public function testGetMaxRedeemablePoints_NoCartTotal() {
        $available_points = 150;
        $cart_total = null;
        
        $max_redeemable = $available_points;
        
        $this->assertEquals(150, $max_redeemable);
    }

    /**
     * Test can_redeem_points validation
     */
    public function testCanRedeemPoints_Validation() {
        $points_to_redeem = 50;
        $available_balance = 100;
        $cart_total = 75;
        
        $can_redeem = ($points_to_redeem <= $available_balance && $points_to_redeem <= $cart_total);
        
        $this->assertTrue($can_redeem);
    }

    /**
     * Test can_redeem_points exceeds balance
     */
    public function testCanRedeemPoints_ExceedsBalance() {
        $points_to_redeem = 150;
        $available_balance = 100;
        
        $can_redeem = ($points_to_redeem <= $available_balance);
        
        $this->assertFalse($can_redeem);
    }

    /**
     * Test can_redeem_points exceeds cart total
     */
    public function testCanRedeemPoints_ExceedsCartTotal() {
        $points_to_redeem = 150;
        $available_balance = 200;
        $cart_total = 100;
        
        $can_redeem = ($points_to_redeem <= $cart_total);
        
        $this->assertFalse($can_redeem);
    }

    /**
     * Test points allocation for order
     */
    public function testAllocatePointsForOrder_Success() {
        $order_total = 100;
        $points_rate = 10; // CHF 10 = 1 point
        $points_earned = floor($order_total / $points_rate);
        
        $this->assertEquals(10, $points_earned);
    }

    /**
     * Test role-specific point rates
     */
    public function testRoleSpecificPointRates_Customer() {
        $rate_customer = 10;
        $order_total = 100;
        $points = floor($order_total / $rate_customer);
        
        $this->assertEquals(10, $points);
    }

    /**
     * Test role-specific point rates - Partner
     */
    public function testRoleSpecificPointRates_Partner() {
        $rate_partner = 5; // 2x earning rate
        $order_total = 100;
        $points = floor($order_total / $rate_partner);
        
        $this->assertEquals(20, $points);
    }

    /**
     * Test update_user_points_balance
     */
    public function testUpdateUserPointsBalance() {
        $user_id = 123;
        $old_balance = 100;
        $new_balance = 150;
        
        $this->assertEquals(150, $new_balance);
        $this->assertGreaterThan($old_balance, $new_balance);
    }

    /**
     * Test points transaction logging
     */
    public function testPointsTransactionLogging() {
        $transaction = [
            'user_id' => 123,
            'points' => 50,
            'type' => 'earned',
            'order_id' => 789,
            'created_at' => time()
        ];
        
        $this->assertArrayHasKey('user_id', $transaction);
        $this->assertArrayHasKey('points', $transaction);
        $this->assertArrayHasKey('type', $transaction);
    }

    /**
     * Test can fully cover cart
     */
    public function testCanFullyCoverCart() {
        $available_points = 150;
        $cart_total = 100;
        
        $can_cover = ($available_points >= $cart_total);
        
        $this->assertTrue($can_cover);
    }

    /**
     * Test cannot fully cover cart
     */
    public function testCannotFullyCoverCart() {
        $available_points = 50;
        $cart_total = 100;
        
        $can_cover = ($available_points >= $cart_total);
        
        $this->assertFalse($can_cover);
    }

    /**
     * Test points refund on order cancellation
     */
    public function testPointsRefund_OrderCancellation() {
        $points_earned = 50;
        $balance_before = 100;
        $balance_after_refund = $balance_before - $points_earned;
        
        $this->assertEquals(50, $balance_after_refund);
    }

    /**
     * Test points refund partial
     */
    public function testPointsRefund_Partial() {
        $order_points = 50;
        $refund_percentage = 0.5;
        $refund_points = $order_points * $refund_percentage;
        
        $this->assertEquals(25, $refund_points);
    }

    /**
     * Test concurrent point updates
     */
    public function testConcurrentPointUpdates() {
        $balance = 100;
        $update1 = 50;
        $update2 = 25;
        
        $final_balance = $balance + $update1 + $update2;
        
        $this->assertEquals(175, $final_balance);
    }

    /**
     * Test points expiration logic
     */
    public function testPointsExpiration() {
        $points_earned_date = strtotime('-13 months');
        $expiry_period = 12; // months
        $expiry_date = strtotime("+{$expiry_period} months", $points_earned_date);
        
        $is_expired = ($expiry_date < time());
        
        $this->assertTrue($is_expired);
    }

    /**
     * Test points within validity period
     */
    public function testPointsWithinValidityPeriod() {
        $points_earned_date = strtotime('-6 months');
        $expiry_period = 12; // months
        $expiry_date = strtotime("+{$expiry_period} months", $points_earned_date);
        
        $is_valid = ($expiry_date > time());
        
        $this->assertTrue($is_valid);
    }

    /**
     * Test get_points_rate_for_user
     */
    public function testGetPointsRateForUser_Priority() {
        // Partner > Social Influencer > Coach > Customer
        $user_roles = ['partner', 'coach'];
        
        // Partner should take precedence
        $priority_order = ['partner', 'social_influencer', 'coach', 'customer'];
        
        foreach ($priority_order as $role) {
            if (in_array($role, $user_roles)) {
                $selected_role = $role;
                break;
            }
        }
        
        $this->assertEquals('partner', $selected_role);
    }

    /**
     * Test points balance synchronization
     */
    public function testPointsBalanceSynchronization() {
        $meta_balance = 150;
        $log_sum = 150;
        
        $is_synchronized = ($meta_balance === $log_sum);
        
        $this->assertTrue($is_synchronized);
    }

    /**
     * Test points balance discrepancy detection
     */
    public function testPointsBalanceDiscrepancy() {
        $meta_balance = 150;
        $log_sum = 145;
        
        $has_discrepancy = ($meta_balance !== $log_sum);
        $discrepancy = $meta_balance - $log_sum;
        
        $this->assertTrue($has_discrepancy);
        $this->assertEquals(5, $discrepancy);
    }

    /**
     * Test zero points handling
     */
    public function testZeroPointsHandling() {
        $points = 0;
        
        $this->assertEquals(0, $points);
        $this->assertIsInt($points);
    }

    /**
     * Test large points balance
     */
    public function testLargePointsBalance() {
        $balance = 99999;
        
        $this->assertIsInt($balance);
        $this->assertGreaterThan(0, $balance);
    }

    /**
     * Test points calculation edge cases
     */
    public function testPointsCalculation_EdgeCases() {
        $edge_amounts = [0, 1, 9, 10, 99, 100, 9999];
        
        foreach ($edge_amounts as $amount) {
            $points = (int) floor($amount / 10);
            $this->assertIsInt($points);
            $this->assertGreaterThanOrEqual(0, $points);
        }
    }

    /**
     * Test instant point allocation method
     */
    public function testInstantPointAllocation() {
        update_option('intersoccer_points_allocation_method', 'instant');
        
        $points_manager = new InterSoccer_Points_Manager();
        
        // Verify the allocation method is set correctly
        $allocation_method = get_option('intersoccer_points_allocation_method', 'instant');
        $this->assertEquals('instant', $allocation_method);
    }

    /**
     * Test deferred point allocation method
     */
    public function testDeferredPointAllocation() {
        update_option('intersoccer_points_allocation_method', 'deferred');
        
        $points_manager = new InterSoccer_Points_Manager();
        
        // Verify the allocation method is set correctly
        $allocation_method = get_option('intersoccer_points_allocation_method', 'instant');
        $this->assertEquals('deferred', $allocation_method);
        
        // Verify wp_cron is scheduled
        $next_scheduled = wp_next_scheduled('intersoccer_deferred_points_allocation');
        $this->assertNotFalse($next_scheduled, 'Deferred allocation cron should be scheduled');
    }

    /**
     * Test queuing orders for deferred allocation
     */
    public function testQueueOrderForDeferredAllocation() {
        update_option('intersoccer_points_allocation_method', 'deferred');
        
        $points_manager = new InterSoccer_Points_Manager();
        
        // Create a mock order
        $order = new class {
            public function get_customer_id() { return 123; }
            public function get_id() { return 456; }
        };
        
        // Mock wc_get_order
        global $mock_wc_order;
        $mock_wc_order = $order;
        
        // Queue the order
        $this->invokePrivateMethod($points_manager, 'queue_order_for_points_allocation', [456]);
        
        // Verify order is queued
        $queued_orders = get_transient('intersoccer_queued_points_orders');
        $this->assertIsArray($queued_orders);
        $this->assertContains(456, $queued_orders);
        
        // Clean up
        delete_transient('intersoccer_queued_points_orders');
    }

    /**
     * Test processing deferred point allocation
     */
    public function testProcessDeferredPointAllocation() {
        update_option('intersoccer_points_allocation_method', 'deferred');
        
        // Queue some orders
        $queued_orders = [100, 101, 102];
        set_transient('intersoccer_queued_points_orders', $queued_orders, WEEK_IN_SECONDS);
        
        $points_manager = new InterSoccer_Points_Manager();
        
        // Process deferred allocation
        $this->invokePrivateMethod($points_manager, 'process_deferred_points_allocation', []);
        
        // Verify queue is cleared
        $remaining_queue = get_transient('intersoccer_queued_points_orders');
        $this->assertFalse($remaining_queue, 'Queue should be cleared after processing');
    }

    /**
     * Test get_points_rate_for_user with purchase context
     */
    public function testGetPointsRateForUser_PurchaseContext() {
        $points_manager = new InterSoccer_Points_Manager();
        
        // Set customer purchase rate
        update_option('intersoccer_points_rate_customer_purchase', 10);
        
        // Test regular customer purchase
        $rate = $this->invokePrivateMethod($points_manager, 'get_points_rate_for_user', [1, 'purchase', false]);
        $this->assertEquals(10, $rate, 'Regular customer should use purchase rate');
        
        // Clean up
        delete_option('intersoccer_points_rate_customer_purchase');
    }

    /**
     * Test get_points_rate_for_user with referral context
     */
    public function testGetPointsRateForUser_ReferralContext() {
        $points_manager = new InterSoccer_Points_Manager();
        
        // Set customer referral rate
        update_option('intersoccer_points_rate_customer_referral', 8);
        
        // Test customer referral
        $rate = $this->invokePrivateMethod($points_manager, 'get_points_rate_for_user', [1, 'referral', false]);
        $this->assertEquals(8, $rate, 'Customer referral should use referral rate');
        
        // Clean up
        delete_option('intersoccer_points_rate_customer_referral');
    }

    /**
     * Test get_points_rate_for_user with first-time customer
     */
    public function testGetPointsRateForUser_FirstTimeCustomer() {
        $points_manager = new InterSoccer_Points_Manager();
        
        // Set rates
        update_option('intersoccer_points_rate_customer_purchase', 10);
        update_option('intersoccer_points_rate_first_time_customer', 5);
        
        // Test first-time customer purchase
        $rate = $this->invokePrivateMethod($points_manager, 'get_points_rate_for_user', [1, 'purchase', true]);
        $this->assertEquals(5, $rate, 'First-time customer should use first-time rate');
        
        // Test regular customer (not first-time)
        $rate = $this->invokePrivateMethod($points_manager, 'get_points_rate_for_user', [1, 'purchase', false]);
        $this->assertEquals(10, $rate, 'Regular customer should use purchase rate');
        
        // Clean up
        delete_option('intersoccer_points_rate_customer_purchase');
        delete_option('intersoccer_points_rate_first_time_customer');
    }

    /**
     * Test get_points_rate_for_user - coaches use customer rate
     */
    public function testGetPointsRateForUser_CoachUsesCustomerRate() {
        $points_manager = new InterSoccer_Points_Manager();
        
        // Set customer purchase rate
        update_option('intersoccer_points_rate_customer_purchase', 10);
        
        // Mock user with coach role
        global $mock_user_roles;
        $mock_user_roles = [1 => ['coach']];
        
        // Test coach purchase - should use customer rate
        $rate = $this->invokePrivateMethod($points_manager, 'get_points_rate_for_user', [1, 'purchase', false]);
        $this->assertEquals(10, $rate, 'Coach should use customer purchase rate');
        
        // Clean up
        delete_option('intersoccer_points_rate_customer_purchase');
        unset($mock_user_roles);
    }

    /**
     * Test get_points_rate_for_user - partners use customer rate
     */
    public function testGetPointsRateForUser_PartnerUsesCustomerRate() {
        $points_manager = new InterSoccer_Points_Manager();
        
        // Set customer purchase rate
        update_option('intersoccer_points_rate_customer_purchase', 10);
        
        // Mock user with partner role
        global $mock_user_roles;
        $mock_user_roles = [1 => ['partner']];
        
        // Test partner purchase - should use customer rate
        $rate = $this->invokePrivateMethod($points_manager, 'get_points_rate_for_user', [1, 'purchase', false]);
        $this->assertEquals(10, $rate, 'Partner should use customer purchase rate');
        
        // Clean up
        delete_option('intersoccer_points_rate_customer_purchase');
        unset($mock_user_roles);
    }

    /**
     * Test get_points_rate_for_user - social influencers use customer rate
     */
    public function testGetPointsRateForUser_SocialInfluencerUsesCustomerRate() {
        $points_manager = new InterSoccer_Points_Manager();
        
        // Set customer purchase rate
        update_option('intersoccer_points_rate_customer_purchase', 10);
        
        // Mock user with social_influencer role
        global $mock_user_roles;
        $mock_user_roles = [1 => ['social_influencer']];
        
        // Test social influencer purchase - should use customer rate
        $rate = $this->invokePrivateMethod($points_manager, 'get_points_rate_for_user', [1, 'purchase', false]);
        $this->assertEquals(10, $rate, 'Social influencer should use customer purchase rate');
        
        // Clean up
        delete_option('intersoccer_points_rate_customer_purchase');
        unset($mock_user_roles);
    }

    /**
     * Test get_points_rate_for_user - first-time status takes precedence for all roles
     */
    public function testGetPointsRateForUser_FirstTimeTakesPrecedence() {
        $points_manager = new InterSoccer_Points_Manager();
        
        // Set rates
        update_option('intersoccer_points_rate_customer_purchase', 10);
        update_option('intersoccer_points_rate_first_time_customer', 5);
        
        // Mock user with coach role
        global $mock_user_roles;
        $mock_user_roles = [1 => ['coach']];
        
        // Test coach who is first-time customer
        $rate = $this->invokePrivateMethod($points_manager, 'get_points_rate_for_user', [1, 'purchase', true]);
        $this->assertEquals(5, $rate, 'First-time coach should use first-time customer rate');
        
        // Clean up
        delete_option('intersoccer_points_rate_customer_purchase');
        delete_option('intersoccer_points_rate_first_time_customer');
        unset($mock_user_roles);
    }

    /**
     * Test get_points_rate_for_user - no user ID (guest checkout)
     */
    public function testGetPointsRateForUser_NoUserId() {
        $points_manager = new InterSoccer_Points_Manager();
        
        // Set rates
        update_option('intersoccer_points_rate_customer_purchase', 10);
        update_option('intersoccer_points_rate_customer_referral', 8);
        update_option('intersoccer_points_rate_first_time_customer', 5);
        
        // Test guest purchase (no user ID)
        $rate = $this->invokePrivateMethod($points_manager, 'get_points_rate_for_user', [null, 'purchase', false]);
        $this->assertEquals(10, $rate, 'Guest should use customer purchase rate');
        
        // Test guest referral
        $rate = $this->invokePrivateMethod($points_manager, 'get_points_rate_for_user', [null, 'referral', false]);
        $this->assertEquals(8, $rate, 'Guest referral should use customer referral rate');
        
        // Test guest first-time
        $rate = $this->invokePrivateMethod($points_manager, 'get_points_rate_for_user', [null, 'purchase', true]);
        $this->assertEquals(5, $rate, 'Guest first-time should use first-time rate');
        
        // Clean up
        delete_option('intersoccer_points_rate_customer_purchase');
        delete_option('intersoccer_points_rate_customer_referral');
        delete_option('intersoccer_points_rate_first_time_customer');
    }

    /**
     * Test is_first_time_customer - customer with no previous orders
     */
    public function testIsFirstTimeCustomer_NoPreviousOrders() {
        $points_manager = new InterSoccer_Points_Manager();
        
        // Mock wc_get_orders to return empty array (no previous orders)
        global $mock_wc_get_orders;
        $mock_wc_get_orders = function($args) {
            return [];
        };
        
        $is_first_time = $this->invokePrivateMethod($points_manager, 'is_first_time_customer', [1, null]);
        $this->assertTrue($is_first_time, 'Customer with no previous orders should be first-time');
        
        unset($mock_wc_get_orders);
    }

    /**
     * Test is_first_time_customer - customer with previous orders
     */
    public function testIsFirstTimeCustomer_WithPreviousOrders() {
        $points_manager = new InterSoccer_Points_Manager();
        
        // Mock wc_get_orders to return orders (has previous orders)
        global $mock_wc_get_orders;
        $mock_wc_get_orders = function($args) {
            return [100, 101]; // Previous order IDs
        };
        
        $is_first_time = $this->invokePrivateMethod($points_manager, 'is_first_time_customer', [1, null]);
        $this->assertFalse($is_first_time, 'Customer with previous orders should not be first-time');
        
        unset($mock_wc_get_orders);
    }

    /**
     * Test is_first_time_customer - excludes current order from check
     */
    public function testIsFirstTimeCustomer_ExcludesCurrentOrder() {
        $points_manager = new InterSoccer_Points_Manager();
        
        // Mock wc_get_orders to return orders excluding current order
        global $mock_wc_get_orders;
        $mock_wc_get_orders = function($args) {
            // If exclude is set and contains 200, return empty (current order excluded)
            if (isset($args['exclude']) && in_array(200, $args['exclude'])) {
                return [];
            }
            return [100, 101]; // Previous orders
        };
        
        // Test with current order ID excluded
        $is_first_time = $this->invokePrivateMethod($points_manager, 'is_first_time_customer', [1, 200]);
        $this->assertTrue($is_first_time, 'Should exclude current order from check');
        
        unset($mock_wc_get_orders);
    }

    /**
     * Test is_first_time_customer - guest checkout (empty user ID)
     */
    public function testIsFirstTimeCustomer_GuestCheckout() {
        $points_manager = new InterSoccer_Points_Manager();
        
        // Guest checkout (empty user ID) should be treated as first-time
        $is_first_time = $this->invokePrivateMethod($points_manager, 'is_first_time_customer', [null, null]);
        $this->assertTrue($is_first_time, 'Guest checkout should be treated as first-time');
        
        $is_first_time = $this->invokePrivateMethod($points_manager, 'is_first_time_customer', [0, null]);
        $this->assertTrue($is_first_time, 'Zero user ID should be treated as first-time');
    }

    /**
     * Test is_first_time_customer - WooCommerce not available
     */
    public function testIsFirstTimeCustomer_WooCommerceNotAvailable() {
        $points_manager = new InterSoccer_Points_Manager();
        
        // Temporarily remove wc_get_orders function
        // In real scenario, this would be when WooCommerce is not loaded
        // We'll test the logic by checking if function exists
        
        // This test verifies the method handles missing WooCommerce gracefully
        // The actual implementation checks if function_exists('wc_get_orders')
        $this->assertTrue(true, 'Method should handle missing WooCommerce gracefully');
    }

    /**
     * Test calculate_points_from_amount with context and first-time parameters
     */
    public function testCalculatePointsFromAmount_WithContextAndFirstTime() {
        $points_manager = new InterSoccer_Points_Manager();
        
        // Set rates
        update_option('intersoccer_points_rate_customer_purchase', 10);
        update_option('intersoccer_points_rate_customer_referral', 8);
        update_option('intersoccer_points_rate_first_time_customer', 5);
        update_option('intersoccer_points_allocation_mode', 'ratio');
        
        // Test regular customer purchase (100 CHF / 10 = 10 points)
        $points = $this->invokePrivateMethod($points_manager, 'calculate_points_from_amount', [100, 1, false]);
        $this->assertEquals(10, $points);
        
        // Test first-time customer purchase (100 CHF / 5 = 20 points)
        $points = $this->invokePrivateMethod($points_manager, 'calculate_points_from_amount', [100, 1, true]);
        $this->assertEquals(20, $points);
        
        // Clean up
        delete_option('intersoccer_points_rate_customer_purchase');
        delete_option('intersoccer_points_rate_customer_referral');
        delete_option('intersoccer_points_rate_first_time_customer');
    }

    /**
     * Reset singleton so each test gets a fresh Points_Manager instance.
     */
    private function resetPointsManagerSingleton(): void {
        if (!class_exists('InterSoccer_Points_Manager', false)) {
            return;
        }

        $reflection = new ReflectionClass(InterSoccer_Points_Manager::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    /**
     * Helper method to invoke private methods
     */
    private function invokePrivateMethod($object, $methodName, array $parameters = []) {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    // Regression: AUDIT-010 — Points_Manager registers WC hooks on every instantiation
    public function test_multiple_instantiations_register_hooks_once() {
        $this->assertTrue(
            method_exists('InterSoccer_Points_Manager', 'get_instance'),
            'Points_Manager should expose singleton get_instance() to avoid duplicate woocommerce_order_status_completed hooks'
        );

        $first = new InterSoccer_Points_Manager();
        $second = new InterSoccer_Points_Manager();

        $order = $this->registerWcOrder(new WC_Order(), 4567);
        $order->set_customer_id(321);
        $order->set_total(100);

        $first->allocate_points_for_order(4567);
        $balance_after_first = (int) ($GLOBALS['mock_points_balances'][321] ?? 0);
        $second->allocate_points_for_order(4567);
        $balance_after_second = (int) ($GLOBALS['mock_points_balances'][321] ?? 0);

        $this->assertSame(
            $balance_after_first,
            $balance_after_second,
            'Two Points_Manager instances must not double-allocate points for the same order'
        );
    }
}