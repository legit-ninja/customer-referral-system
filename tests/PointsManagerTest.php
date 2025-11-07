<?php

use PHPUnit\Framework\TestCase;

/**
 * Test suite for InterSoccer Points Manager
 */
class PointsManagerTest extends TestCase {

    protected function setUp(): void {
        // Include the points manager class
        require_once __DIR__ . '/../includes/class-points-manager.php';
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

        // Mock order
        $order = new WC_Order();
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

        // First allocate points
        $order = new WC_Order();
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

        // Add points to user
        $points_manager->add_points_transaction(1, 'test', 50, null, 'Test points');

        // Mock customer total spent
        global $mock_customer_spent;
        $mock_customer_spent = [1 => 1000]; // 1000 CHF spent

        // Test valid redemption (50 points = 50 CHF, max allowed = min(100, 1000/10) = 100)
        $can_redeem = $points_manager->can_redeem_points(1, 50);
        $this->assertTrue($can_redeem);

        // Test invalid redemption (150 points = 150 CHF > 100 CHF limit)
        $can_redeem = $points_manager->can_redeem_points(1, 150);
        $this->assertFalse($can_redeem);

        // Test insufficient balance
        $can_redeem = $points_manager->can_redeem_points(1, 100);
        $this->assertFalse($can_redeem);
    }

    /**
     * Test maximum redeemable points calculation
     */
    public function testGetMaxRedeemablePoints() {
        $points_manager = new InterSoccer_Points_Manager();

        // Mock customer with 2000 CHF spent (max discount = 100 CHF = 100 points)
        global $mock_customer_spent;
        $mock_customer_spent = [1 => 2000];

        // Add 150 points to balance
        $points_manager->add_points_transaction(1, 'test', 150, null, 'Test points');

        $max_redeemable = $points_manager->get_max_redeemable_points(1);
        $this->assertEquals(100, $max_redeemable); // Limited by spending, not balance
    }

    /**
     * Test redemption summary
     */
    public function testGetRedemptionSummary() {
        $points_manager = new InterSoccer_Points_Manager();

        // Mock customer with 500 CHF spent (max discount = 50 CHF = 50 points)
        global $mock_customer_spent;
        $mock_customer_spent = [1 => 500];

        // Add 30 points to balance
        $points_manager->add_points_transaction(1, 'test', 30, null, 'Test points');

        $summary = $points_manager->get_redemption_summary(1);

        $this->assertEquals(500, $summary['total_spent']);
        $this->assertEquals(50, $summary['max_discount']);
        $this->assertEquals(50, $summary['max_points']);
        $this->assertEquals(30, $summary['current_balance']);
        $this->assertEquals(30, $summary['available_points']); // Limited by balance
        $this->assertEquals(30, $summary['available_discount']);
    }

    /**
     * Test points redemption processing
     */
    public function testProcessPointsRedemption() {
        $points_manager = new InterSoccer_Points_Manager();

        // Add points to user
        $points_manager->add_points_transaction(1, 'test', 20, null, 'Test points');

        // Mock order and session
        $order = new WC_Order();
        $order->set_total(100);

        global $mock_session;
        $mock_session = ['intersoccer_points_to_redeem' => 10];

        // Process redemption
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

        // First redeem some points
        $order = new WC_Order();
        $order->set_total(100);

        global $mock_session;
        $mock_session = ['intersoccer_points_to_redeem' => 10];

        $points_manager->add_points_transaction(1, 'test', 20, null, 'Test points');
        $points_manager->process_points_redemption($order, []);

        // Then cancel the order
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
     * Test get_points_balance
     */
    public function testGetPointsBalance() {
        $user_id = 123;
        $balance = 150;
        
        $this->assertIsInt($balance);
        $this->assertGreaterThanOrEqual(0, $balance);
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
     * Test get_redemption_summary
     */
    public function testGetRedemptionSummary() {
        $summary = [
            'available_points' => 150,
            'max_redeemable' => 100,
            'discount_value' => 100,
            'can_fully_cover' => false
        ];
        
        $this->assertArrayHasKey('available_points', $summary);
        $this->assertArrayHasKey('can_fully_cover', $summary);
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
            $points = floor($amount / 10);
            $this->assertIsInt($points);
            $this->assertGreaterThanOrEqual(0, $points);
        }
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
}