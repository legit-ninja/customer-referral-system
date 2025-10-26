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
     * Test points calculation from currency amount
     */
    public function testCalculatePointsFromAmount() {
        $points_manager = new InterSoccer_Points_Manager();

        // Test 10 CHF = 1 point
        $points = $this->invokePrivateMethod($points_manager, 'calculate_points_from_amount', [10]);
        $this->assertEquals(1, $points);

        // Test 25 CHF = 2.5 points
        $points = $this->invokePrivateMethod($points_manager, 'calculate_points_from_amount', [25]);
        $this->assertEquals(2.5, $points);

        // Test 0 CHF = 0 points
        $points = $this->invokePrivateMethod($points_manager, 'calculate_points_from_amount', [0]);
        $this->assertEquals(0, $points);
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
     * Test points balance retrieval
     */
    public function testGetPointsBalance() {
        $points_manager = new InterSoccer_Points_Manager();

        // Test empty balance
        $balance = $points_manager->get_points_balance(1);
        $this->assertEquals(0, $balance);

        // Add some points
        $points_manager->add_points_transaction(1, 123, 'test', 10, 'Test points');

        // Test balance after adding points
        $balance = $points_manager->get_points_balance(1);
        $this->assertEquals(10, $balance);
    }

    /**
     * Test points transaction logging
     */
    public function testAddPointsTransaction() {
        $points_manager = new InterSoccer_Points_Manager();

        // Add a transaction
        $transaction_id = $points_manager->add_points_transaction(
            1, 123, 'test_transaction', 5, 'Test transaction', ['test' => 'data']
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
        $points_manager->add_points_transaction(1, null, 'test', 50, 'Test points');

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
        $points_manager->add_points_transaction(1, null, 'test', 150, 'Test points');

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
        $points_manager->add_points_transaction(1, null, 'test', 30, 'Test points');

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
        $points_manager->add_points_transaction(1, null, 'test', 20, 'Test points');

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

        $points_manager->add_points_transaction(1, null, 'test', 20, 'Test points');
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
        $points_manager->add_points_transaction(1, null, 'order_purchase', 10, 'Purchase 1');
        $points_manager->add_points_transaction(2, null, 'order_purchase', 20, 'Purchase 2');
        $points_manager->add_points_transaction(1, null, 'points_redemption', -5, 'Redemption 1');

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
        $points_manager->add_points_transaction(1, null, 'order_purchase', 10, 'Purchase');
        $points_manager->add_points_transaction(1, null, 'points_redemption', -5, 'Redemption');
        $points_manager->add_points_transaction(1, null, 'order_purchase', 15, 'Another purchase');

        $summary = $points_manager->get_transaction_summary();

        $this->assertArrayHasKey('order_purchase', $summary);
        $this->assertArrayHasKey('points_redemption', $summary);
        $this->assertEquals(2, $summary['order_purchase']['count']);
        $this->assertEquals(25, $summary['order_purchase']['total_points']);
        $this->assertEquals(1, $summary['points_redemption']['count']);
        $this->assertEquals(-5, $summary['points_redemption']['total_points']);
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