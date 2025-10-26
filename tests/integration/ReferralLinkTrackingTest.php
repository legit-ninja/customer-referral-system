<?php

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for referral link tracking
 * Tests end-to-end referral link scenarios
 */
class ReferralLinkTrackingTest extends TestCase {

    protected function setUp(): void {
        require_once __DIR__ . '/../bootstrap.php';
        require_once __DIR__ . '/../../includes/class-referral-handler.php';
        require_once __DIR__ . '/../../includes/class-points-manager.php';
        require_once __DIR__ . '/../../includes/class-commission-manager.php';
    }

    /**
     * Test complete referral link journey: click -> registration -> purchase
     */
    public function testCompleteReferralLinkJourney() {
        $referral_handler = new InterSoccer_Referral_Handler();
        $points_manager = new InterSoccer_Points_Manager();

        // Step 1: Coach generates referral link
        $coach_id = 2;
        $referral_link = InterSoccer_Referral_Handler::generate_coach_referral_link($coach_id);

        // Verify link contains referral code
        $this->assertStringContains('ref=', $referral_link);

        // Extract referral code from link
        $url_parts = parse_url($referral_link);
        parse_str($url_parts['query'], $query_params);
        $referral_code = $query_params['ref'];

        // Step 2: Customer clicks referral link (simulated)
        $this->simulateReferralLinkClick($referral_code);

        // Step 3: Customer registers and places first order
        $customer_id = 1;
        $order = new WC_Order();
        $order->set_total(150);
        $order->set_tax_total(15);
        $order->set_customer_id($customer_id);
        $order_id = 123;

        // Process referral order
        $referral_handler->process_referral_order($order_id);

        // Step 4: Verify referral was processed
        // Check if customer got partnership auto-assignment
        global $mock_user_meta;
        $this->assertEquals($coach_id, $mock_user_meta[$customer_id]['intersoccer_partnership_coach_id']);

        // Check if coach got commission
        $commission_data = InterSoccer_Commission_Manager::calculate_total_commission(
            $order, $coach_id, $customer_id, 1
        );

        $this->assertGreaterThan(0, $commission_data['total_amount']);

        // Step 5: Process order completion
        $points_manager->allocate_points_for_order($order_id);

        // Verify customer got points (150-15=135 CHF taxable = 13.5 points)
        $customer_balance = $points_manager->get_points_balance($customer_id);
        $this->assertEquals(13.5, $customer_balance);

        // Step 6: Process referral code rewards
        global $mock_session;
        $mock_session['intersoccer_applied_referral_code'] = $referral_code;
        $mock_session['intersoccer_referral_coach_id'] = $coach_id;

        $referral_handler->process_referral_code_rewards($order_id);

        // Verify coach got referral points (50 points)
        $coach_balance = $points_manager->get_points_balance($coach_id);
        $this->assertEquals(50, $coach_balance);
    }

    /**
     * Test referral link with customer referral codes
     */
    public function testCustomerReferralLinkTracking() {
        $referral_handler = new InterSoccer_Referral_Handler();

        // Generate customer referral link
        $customer_id = 1;
        $referral_link = InterSoccer_Referral_Handler::generate_customer_referral_link($customer_id);

        // Verify link format
        $this->assertStringContains('cust_ref=', $referral_link);

        // Extract referral code
        $url_parts = parse_url($referral_link);
        parse_str($url_parts['query'], $query_params);
        $referral_code = $query_params['cust_ref'];

        // Simulate referral processing
        $this->simulateCustomerReferralLinkClick($referral_code);

        // Process order with customer referral
        $new_customer_id = 3;
        $order = new WC_Order();
        $order->set_total(100);
        $order->set_customer_id($new_customer_id);
        $order_id = 124;

        // Mock referral session
        global $mock_session;
        $mock_session['intersoccer_referral'] = $referral_code;

        $referral_handler->process_referral_order($order_id);

        // Verify customer referral was processed (should give credits, not commission)
        // This would normally create a referral record with type 'customer'
    }

    /**
     * Test referral link expiration and cleanup
     */
    public function testReferralLinkExpiration() {
        $referral_handler = new InterSoccer_Referral_Handler();

        // Set up referral cookie
        $referral_code = 'coach_2_test';
        $this->simulateReferralLinkClick($referral_code);

        // Verify cookie was set
        global $mock_cookies;
        $this->assertEquals($referral_code, $mock_cookies['intersoccer_referral']);

        // Simulate order processing (cookie should be cleared)
        $order_id = 125;
        $referral_handler->process_referral_order($order_id);

        // Verify cookie was cleared
        $this->assertEmpty($mock_cookies['intersoccer_referral']);
    }

    /**
     * Test multiple referral sources (multi-touch attribution)
     */
    public function testMultiTouchReferralAttribution() {
        $referral_handler = new InterSoccer_Referral_Handler();

        // Customer interacts with multiple referral sources
        $coach_1_code = 'coach_1_test';
        $coach_2_code = 'coach_2_test';
        $customer_code = 'cust_1_test';

        // First interaction: coach 1 referral link
        $this->simulateReferralLinkClick($coach_1_code);

        // Second interaction: customer referral link (overwrites)
        $this->simulateCustomerReferralLinkClick($customer_code);

        // Third interaction: coach 2 referral link (overwrites)
        $this->simulateReferralLinkClick($coach_2_code);

        // Process order - should use the last referral source (coach 2)
        $customer_id = 1;
        $order = new WC_Order();
        $order->set_customer_id($customer_id);
        $order_id = 126;

        $referral_handler->process_referral_order($order_id);

        // Verify the last referral source was used
        global $mock_user_meta;
        $this->assertEquals(2, $mock_user_meta[$customer_id]['intersoccer_partnership_coach_id']);
    }

    /**
     * Test referral link with UTM parameters
     */
    public function testReferralLinkWithUTMParameters() {
        $referral_handler = new InterSoccer_Referral_Handler();

        // Generate referral link with UTM parameters
        $coach_id = 2;
        $base_link = InterSoccer_Referral_Handler::generate_coach_referral_link($coach_id);

        // Add UTM parameters
        $utm_link = $base_link . '&utm_source=facebook&utm_medium=social&utm_campaign=referral_program';

        // Parse the link
        $url_parts = parse_url($utm_link);
        parse_str($url_parts['query'], $query_params);

        // Verify both referral code and UTM parameters are present
        $this->assertArrayHasKey('ref', $query_params);
        $this->assertArrayHasKey('utm_source', $query_params);
        $this->assertArrayHasKey('utm_medium', $query_params);
        $this->assertArrayHasKey('utm_campaign', $query_params);

        // Simulate click with UTM parameters
        $this->simulateReferralLinkClick($query_params['ref']);

        // Process order
        $order_id = 127;
        $referral_handler->process_referral_order($order_id);

        // Verify referral was processed despite UTM parameters
        global $mock_user_meta;
        $this->assertEquals(2, $mock_user_meta[1]['intersoccer_partnership_coach_id']);
    }

    /**
     * Test referral link tracking across sessions
     */
    public function testReferralLinkAcrossSessions() {
        $referral_handler = new InterSoccer_Referral_Handler();

        // Session 1: Customer clicks referral link
        $referral_code = 'coach_2_test';
        $this->simulateReferralLinkClick($referral_code);

        // Session 2: Customer returns and makes purchase (cookie persists)
        // Simulate cookie persistence
        global $mock_cookies;
        $mock_cookies['intersoccer_referral'] = $referral_code;

        // Process order
        $customer_id = 1;
        $order = new WC_Order();
        $order->set_customer_id($customer_id);
        $order_id = 128;

        $referral_handler->process_referral_order($order_id);

        // Verify referral was attributed correctly
        global $mock_user_meta;
        $this->assertEquals(2, $mock_user_meta[$customer_id]['intersoccer_partnership_coach_id']);
    }

    /**
     * Test invalid referral link handling
     */
    public function testInvalidReferralLinkHandling() {
        $referral_handler = new InterSoccer_Referral_Handler();

        // Test with invalid referral code
        $invalid_code = 'invalid_code_123';
        $this->simulateReferralLinkClick($invalid_code);

        // Process order
        $order_id = 129;
        $referral_handler->process_referral_order($order_id);

        // Verify no partnership was assigned
        global $mock_user_meta;
        $this->assertEmpty($mock_user_meta[1]['intersoccer_partnership_coach_id'] ?? null);
    }

    /**
     * Test referral link conversion funnel
     */
    public function testReferralLinkConversionFunnel() {
        $referral_handler = new InterSoccer_Referral_Handler();
        $points_manager = new InterSoccer_Points_Manager();

        $coach_id = 2;
        $customer_id = 1;

        // Step 1: Link click
        $referral_link = InterSoccer_Referral_Handler::generate_coach_referral_link($coach_id);
        $url_parts = parse_url($referral_link);
        parse_str($url_parts['query'], $query_params);
        $referral_code = $query_params['ref'];

        $this->simulateReferralLinkClick($referral_code);

        // Step 2: Registration (simulated by setting customer ID)
        // Step 3: First purchase
        $order = new WC_Order();
        $order->set_total(200);
        $order->set_tax_total(20);
        $order->set_customer_id($customer_id);
        $order_id = 130;

        $referral_handler->process_referral_order($order_id);
        $points_manager->allocate_points_for_order($order_id);

        // Step 4: Verify complete funnel
        // - Partnership assigned
        global $mock_user_meta;
        $this->assertEquals($coach_id, $mock_user_meta[$customer_id]['intersoccer_partnership_coach_id']);

        // - Points allocated to customer
        $customer_balance = $points_manager->get_points_balance($customer_id);
        $this->assertEquals(18, $customer_balance); // (200-20) * 0.1 = 18 points

        // - Referral rewards processed
        global $mock_session;
        $mock_session['intersoccer_applied_referral_code'] = $referral_code;
        $mock_session['intersoccer_referral_coach_id'] = $coach_id;

        $referral_handler->process_referral_code_rewards($order_id);
        $coach_balance = $points_manager->get_points_balance($coach_id);
        $this->assertEquals(50, $coach_balance);
    }

    /**
     * Helper method to simulate referral link click
     */
    private function simulateReferralLinkClick($referral_code) {
        global $mock_cookies, $mock_session;
        $mock_cookies['intersoccer_referral'] = $referral_code;
        $mock_session['intersoccer_referral'] = $referral_code;
    }

    /**
     * Helper method to simulate customer referral link click
     */
    private function simulateCustomerReferralLinkClick($referral_code) {
        global $mock_cookies, $mock_session;
        $mock_cookies['intersoccer_referral'] = $referral_code;
        $mock_session['intersoccer_referral'] = $referral_code;
    }
}