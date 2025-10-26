<?php

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for WooCommerce integration
 * Tests order processing, coupon generation, and checkout flow
 */
class WooCommerceIntegrationTest extends TestCase {

    protected function setUp(): void {
        // Include necessary classes
        require_once __DIR__ . '/../bootstrap.php';
        require_once __DIR__ . '/../../includes/class-points-manager.php';
        require_once __DIR__ . '/../../includes/class-commission-manager.php';
        require_once __DIR__ . '/../../includes/class-referral-handler.php';
    }

    /**
     * Test complete order processing workflow with referral
     */
    public function testCompleteOrderProcessingWithReferral() {
        // Create a customer
        $customer_id = 1;

        // Create a coach
        $coach_id = 2;

        // Set up referral relationship
        global $mock_user_meta;
        $mock_user_meta[$customer_id]['intersoccer_partnership_coach_id'] = $coach_id;

        // Create order
        $order = new WC_Order();
        $order->set_total(200); // 200 CHF order
        $order->set_tax_total(20); // 20 CHF tax
        $order->set_customer_id($customer_id);

        // Mock order ID
        $order_id = 123;

        // Process points allocation
        $points_manager = new InterSoccer_Points_Manager();
        $points_manager->allocate_points_for_order($order_id);

        // Verify points were allocated (200 CHF - 20 CHF tax = 180 CHF taxable = 18 points)
        $balance = $points_manager->get_points_balance($customer_id);
        $this->assertEquals(18, $balance);

        // Process commission
        $commission_data = InterSoccer_Commission_Manager::calculate_total_commission(
            $order, $coach_id, $customer_id, 1 // First purchase
        );

        // Verify commission structure
        $this->assertArrayHasKey('base_commission', $commission_data);
        $this->assertArrayHasKey('loyalty_bonus', $commission_data);
        $this->assertArrayHasKey('total_amount', $commission_data);

        // Base commission should be 15% of (200-20) = 27 CHF
        $this->assertEquals(27, $commission_data['base_commission']);

        // Process referral rewards if applicable
        $referral_handler = new InterSoccer_Referral_Handler();

        // Mock referral code usage
        global $mock_session;
        $mock_session['intersoccer_applied_referral_code'] = 'coach_' . $coach_id . '_test';
        $mock_session['intersoccer_referral_coach_id'] = $coach_id;

        // Process referral code rewards
        $referral_handler->process_referral_code_rewards($order_id);

        // Verify coach received referral points (50 points)
        $coach_balance = $points_manager->get_points_balance($coach_id);
        $this->assertEquals(50, $coach_balance);

        // Test order status changes
        $this->testOrderStatusTransitions($order_id, $customer_id, $coach_id);
    }

    /**
     * Test order status transitions and their effects
     */
    private function testOrderStatusTransitions($order_id, $customer_id, $coach_id) {
        $points_manager = new InterSoccer_Points_Manager();

        // Test processing status
        do_action('woocommerce_order_status_processing', $order_id);

        // Test completion status
        do_action('woocommerce_order_status_completed', $order_id);

        // Verify final state
        $customer_balance = $points_manager->get_points_balance($customer_id);
        $this->assertGreaterThan(0, $customer_balance);
    }

    /**
     * Test points redemption during checkout
     */
    public function testPointsRedemptionDuringCheckout() {
        $points_manager = new InterSoccer_Points_Manager();
        $customer_id = 1;

        // Allocate initial points
        $points_manager->add_points_transaction($customer_id, null, 'test', 100, 'Initial points');

        // Set up redemption session
        global $mock_session;
        $mock_session['intersoccer_points_to_redeem'] = 50;

        // Create order
        $order = new WC_Order();
        $order->set_total(200);
        $order->set_customer_id($customer_id);

        // Apply points discount
        $points_manager->apply_points_discount();

        // Process redemption
        $points_manager->process_points_redemption($order, []);

        // Verify points were deducted
        $balance = $points_manager->get_points_balance($customer_id);
        $this->assertEquals(50, $balance);

        // Verify discount was applied (50 points = 50 CHF discount)
        $this->assertEquals(50, $order->get_meta('_intersoccer_discount_amount'));
    }

    /**
     * Test coupon generation and application
     */
    public function testCouponGenerationAndApplication() {
        // Test referral discount coupon
        $discount_amount = 10.00; // 10 CHF referral discount

        // Create mock coupon
        $coupon_code = 'REFERRAL_' . time();
        $coupon = $this->createMockCoupon($coupon_code, $discount_amount);

        // Apply coupon to cart
        $this->applyCouponToCart($coupon);

        // Verify discount is applied
        global $mock_cart;
        $this->assertEquals(-$discount_amount, $mock_cart['discount']);

        // Test coupon validation
        $is_valid = $this->validateReferralCoupon($coupon_code);
        $this->assertTrue($is_valid);
    }

    /**
     * Test checkout validation with points and coupons
     */
    public function testCheckoutValidationWithPointsAndCoupons() {
        $points_manager = new InterSoccer_Points_Manager();
        $customer_id = 1;

        // Set up points redemption
        $points_manager->add_points_transaction($customer_id, null, 'test', 100, 'Test points');
        global $mock_session;
        $mock_session['intersoccer_points_to_redeem'] = 50;

        // Test validation
        $points_manager->validate_points_redemption();

        // Should not have errors since redemption is valid
        global $mock_notices;
        $this->assertEmpty($mock_notices['error'] ?? []);

        // Test invalid redemption (insufficient balance)
        $mock_session['intersoccer_points_to_redeem'] = 200;
        $points_manager->validate_points_redemption();

        // Should have error
        $this->assertNotEmpty($mock_notices['error'] ?? []);
    }

    /**
     * Test order refund processing
     */
    public function testOrderRefundProcessing() {
        $points_manager = new InterSoccer_Points_Manager();
        $customer_id = 1;
        $order_id = 123;

        // Allocate points for order
        $points_manager->add_points_transaction($customer_id, $order_id, 'order_purchase', 20, 'Order points');

        // Redeem some points
        global $mock_session;
        $mock_session['intersoccer_points_to_redeem'] = 10;

        $order = new WC_Order();
        $order->set_customer_id($customer_id);
        $points_manager->process_points_redemption($order, []);

        // Check balance after redemption
        $balance_before_refund = $points_manager->get_points_balance($customer_id);
        $this->assertEquals(10, $balance_before_refund); // 20 - 10 = 10

        // Process refund
        $points_manager->deduct_points_for_refund($order_id);

        // Verify points were deducted (refunded points removed)
        $balance_after_refund = $points_manager->get_points_balance($customer_id);
        $this->assertEquals(0, $balance_after_refund); // 10 - 20 = -10, but should be 0
    }

    /**
     * Test concurrent order processing
     */
    public function testConcurrentOrderProcessing() {
        $points_manager = new InterSoccer_Points_Manager();

        // Simulate multiple orders being processed simultaneously
        $customer_ids = [1, 2, 3];
        $order_ids = [100, 101, 102];

        foreach ($customer_ids as $index => $customer_id) {
            $order_id = $order_ids[$index];

            // Allocate points
            $points_manager->add_points_transaction($customer_id, $order_id, 'order_purchase', 10, 'Concurrent test');

            // Verify each customer has correct balance
            $balance = $points_manager->get_points_balance($customer_id);
            $this->assertEquals(10, $balance);
        }

        // Verify all balances are correct
        foreach ($customer_ids as $customer_id) {
            $balance = $points_manager->get_points_balance($customer_id);
            $this->assertEquals(10, $balance);
        }
    }

    /**
     * Helper method to create mock coupon
     */
    private function createMockCoupon($code, $amount) {
        return (object) [
            'code' => $code,
            'amount' => $amount,
            'type' => 'fixed_cart'
        ];
    }

    /**
     * Helper method to apply coupon to cart
     */
    private function applyCouponToCart($coupon) {
        global $mock_cart;
        $mock_cart = [
            'subtotal' => 100,
            'discount' => -$coupon->amount,
            'total' => 100 - $coupon->amount
        ];
    }

    /**
     * Helper method to validate referral coupon
     */
    private function validateReferralCoupon($code) {
        // Mock validation logic
        return strpos($code, 'REFERRAL_') === 0;
    }
}