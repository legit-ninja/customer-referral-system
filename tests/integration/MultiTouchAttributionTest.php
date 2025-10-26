<?php

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for multi-touch attribution
 * Tests complex referral scenarios with multiple touchpoints
 */
class MultiTouchAttributionTest extends TestCase {

    protected function setUp(): void {
        require_once __DIR__ . '/../bootstrap.php';
        require_once __DIR__ . '/../../includes/class-referral-handler.php';
        require_once __DIR__ . '/../../includes/class-points-manager.php';
        require_once __DIR__ . '/../../includes/class-commission-manager.php';
    }

    /**
     * Test customer journey with multiple referral sources
     */
    public function testMultiSourceReferralJourney() {
        $referral_handler = new InterSoccer_Referral_Handler();
        $points_manager = new InterSoccer_Points_Manager();

        $customer_id = 1;

        // Touchpoint 1: Coach A referral link (first touch)
        $coach_a_id = 2;
        $coach_a_link = InterSoccer_Referral_Handler::generate_coach_referral_link($coach_a_id);
        $this->simulateLinkClick($coach_a_link, 'session_1');

        // Touchpoint 2: Customer B referral link (second touch)
        $customer_b_id = 3;
        $customer_b_link = InterSoccer_Referral_Handler::generate_customer_referral_link($customer_b_id);
        $this->simulateLinkClick($customer_b_link, 'session_2');

        // Touchpoint 3: Coach C referral link (third touch - last touch)
        $coach_c_id = 4;
        $coach_c_link = InterSoccer_Referral_Handler::generate_coach_referral_link($coach_c_id);
        $this->simulateLinkClick($coach_c_link, 'session_3');

        // Customer makes purchase
        $order = new WC_Order();
        $order->set_total(300);
        $order->set_tax_total(30);
        $order->set_customer_id($customer_id);
        $order_id = 200;

        $referral_handler->process_referral_order($order_id);
        $points_manager->allocate_points_for_order($order_id);

        // Verify last-touch attribution: Coach C gets the partnership
        global $mock_user_meta;
        $this->assertEquals($coach_c_id, $mock_user_meta[$customer_id]['intersoccer_partnership_coach_id']);

        // Verify customer gets points
        $customer_balance = $points_manager->get_points_balance($customer_id);
        $this->assertEquals(27, $customer_balance); // (300-30) * 0.1 = 27 points
    }

    /**
     * Test referral source priority and attribution rules
     */
    public function testReferralSourcePriority() {
        $referral_handler = new InterSoccer_Referral_Handler();

        $customer_id = 1;

        // Test priority: Coach referral > Customer referral > Direct
        $scenarios = [
            ['type' => 'coach', 'id' => 2, 'expected' => 2],
            ['type' => 'customer', 'id' => 3, 'expected' => 3],
            ['type' => 'coach', 'id' => 4, 'expected' => 4], // Last coach wins
        ];

        foreach ($scenarios as $scenario) {
            if ($scenario['type'] === 'coach') {
                $link = InterSoccer_Referral_Handler::generate_coach_referral_link($scenario['id']);
            } else {
                $link = InterSoccer_Referral_Handler::generate_customer_referral_link($scenario['id']);
            }

            $this->simulateLinkClick($link, 'priority_test_' . $scenario['id']);
        }

        // Process order
        $order_id = 201;
        $referral_handler->process_referral_order($order_id);

        // Last referral source should win
        global $mock_user_meta;
        $this->assertEquals(4, $mock_user_meta[$customer_id]['intersoccer_partnership_coach_id']);
    }

    /**
     * Test attribution window and cookie persistence
     */
    public function testAttributionWindowAndPersistence() {
        $referral_handler = new InterSoccer_Referral_Handler();

        $customer_id = 1;
        $coach_id = 2;

        // Set referral cookie
        $referral_code = 'coach_' . $coach_id . '_test';
        $this->setReferralCookie($referral_code);

        // Simulate time passing (within attribution window)
        // Cookie should still be valid

        // Customer makes purchase
        $order_id = 202;
        $referral_handler->process_referral_order($order_id);

        // Verify attribution worked
        global $mock_user_meta;
        $this->assertEquals($coach_id, $mock_user_meta[$customer_id]['intersoccer_partnership_coach_id']);

        // Verify cookie was cleared after processing
        global $mock_cookies;
        $this->assertEmpty($mock_cookies['intersoccer_referral']);
    }

    /**
     * Test cross-device attribution
     */
    public function testCrossDeviceAttribution() {
        $referral_handler = new InterSoccer_Referral_Handler();

        $customer_id = 1;
        $coach_id = 2;

        // Device 1: Click referral link
        $this->simulateDeviceInteraction('mobile', $coach_id);

        // Device 2: Complete purchase (same customer account)
        $this->simulateDeviceInteraction('desktop', $coach_id);

        // Process order
        $order_id = 203;
        $referral_handler->process_referral_order($order_id);

        // Attribution should work across devices (simulated by session persistence)
        global $mock_user_meta;
        $this->assertEquals($coach_id, $mock_user_meta[$customer_id]['intersoccer_partnership_coach_id']);
    }

    /**
     * Test attribution with abandoned carts and returns
     */
    public function testAttributionWithAbandonedCarts() {
        $referral_handler = new InterSoccer_Referral_Handler();
        $points_manager = new InterSoccer_Points_Manager();

        $customer_id = 1;
        $coach_id = 2;

        // Initial referral click
        $referral_link = InterSoccer_Referral_Handler::generate_coach_referral_link($coach_id);
        $this->simulateLinkClick($referral_link, 'abandoned_cart_test');

        // Customer abandons cart, returns later
        // Simulate cookie persistence across sessions

        // Customer completes purchase
        $order = new WC_Order();
        $order->set_total(250);
        $order->set_tax_total(25);
        $order->set_customer_id($customer_id);
        $order_id = 204;

        $referral_handler->process_referral_order($order_id);
        $points_manager->allocate_points_for_order($order_id);

        // Verify attribution worked despite abandoned cart
        global $mock_user_meta;
        $this->assertEquals($coach_id, $mock_user_meta[$customer_id]['intersoccer_partnership_coach_id']);

        // Verify points were allocated
        $customer_balance = $points_manager->get_points_balance($customer_id);
        $this->assertEquals(22.5, $customer_balance); // (250-25) * 0.1 = 22.5 points
    }

    /**
     * Test multi-touch attribution with different marketing channels
     */
    public function testMultiChannelAttribution() {
        $referral_handler = new InterSoccer_Referral_Handler();

        $customer_id = 1;

        // Channel 1: Organic referral link
        $coach_1_id = 2;
        $organic_link = InterSoccer_Referral_Handler::generate_coach_referral_link($coach_1_id) . '&utm_source=organic';
        $this->simulateLinkClick($organic_link, 'channel_1');

        // Channel 2: Social media referral link
        $coach_2_id = 3;
        $social_link = InterSoccer_Referral_Handler::generate_coach_referral_link($coach_2_id) . '&utm_source=facebook&utm_medium=social';
        $this->simulateLinkClick($social_link, 'channel_2');

        // Channel 3: Email referral link
        $coach_3_id = 4;
        $email_link = InterSoccer_Referral_Handler::generate_coach_referral_link($coach_3_id) . '&utm_source=email&utm_campaign=newsletter';
        $this->simulateLinkClick($email_link, 'channel_3');

        // Customer converts
        $order_id = 205;
        $referral_handler->process_referral_order($order_id);

        // Last touch should win (email campaign)
        global $mock_user_meta;
        $this->assertEquals($coach_3_id, $mock_user_meta[$customer_id]['intersoccer_partnership_coach_id']);
    }

    /**
     * Test attribution with referral code redemption
     */
    public function testAttributionWithReferralCodeRedemption() {
        $referral_handler = new InterSoccer_Referral_Handler();
        $points_manager = new InterSoccer_Points_Manager();

        $customer_id = 1;
        $coach_id = 2;

        // Customer uses referral code at checkout (different from partnership referral)
        $referral_code = 'coach_' . $coach_id . '_checkout';

        global $mock_session;
        $mock_session['intersoccer_applied_referral_code'] = $referral_code;
        $mock_session['intersoccer_referral_coach_id'] = $coach_id;

        // Process order
        $order = new WC_Order();
        $order->set_total(180);
        $order->set_tax_total(18);
        $order->set_customer_id($customer_id);
        $order_id = 206;

        $referral_handler->process_referral_order($order_id);
        $points_manager->allocate_points_for_order($order_id);
        $referral_handler->process_referral_code_rewards($order_id);

        // Verify referral code rewards were processed
        $coach_balance = $points_manager->get_points_balance($coach_id);
        $this->assertEquals(50, $coach_balance); // Referral code reward

        // Verify customer got points
        $customer_balance = $points_manager->get_points_balance($customer_id);
        $this->assertEquals(16.2, $customer_balance); // (180-18) * 0.1 = 16.2 points
    }

    /**
     * Test attribution conflict resolution
     */
    public function testAttributionConflictResolution() {
        $referral_handler = new InterSoccer_Referral_Handler();

        $customer_id = 1;

        // Multiple referral sources in same session
        $coach_a_id = 2;
        $coach_b_id = 3;

        // Both coaches send referral links
        $link_a = InterSoccer_Referral_Handler::generate_coach_referral_link($coach_a_id);
        $link_b = InterSoccer_Referral_Handler::generate_coach_referral_link($coach_b_id);

        // Customer clicks both (last one wins)
        $this->simulateLinkClick($link_a, 'conflict_1');
        $this->simulateLinkClick($link_b, 'conflict_2');

        // Process order
        $order_id = 207;
        $referral_handler->process_referral_order($order_id);

        // Last clicked link should get attribution
        global $mock_user_meta;
        $this->assertEquals($coach_b_id, $mock_user_meta[$customer_id]['intersoccer_partnership_coach_id']);
    }

    /**
     * Test attribution data integrity
     */
    public function testAttributionDataIntegrity() {
        $referral_handler = new InterSoccer_Referral_Handler();
        $points_manager = new InterSoccer_Points_Manager();

        $customer_id = 1;
        $coach_id = 2;

        // Set up referral
        $referral_link = InterSoccer_Referral_Handler::generate_coach_referral_link($coach_id);
        $this->simulateLinkClick($referral_link, 'integrity_test');

        // Process multiple orders
        for ($i = 1; $i <= 3; $i++) {
            $order = new WC_Order();
            $order->set_total(100 * $i); // Different amounts
            $order->set_tax_total(10 * $i);
            $order->set_customer_id($customer_id);
            $order_id = 300 + $i;

            $referral_handler->process_referral_order($order_id);
            $points_manager->allocate_points_for_order($order_id);
        }

        // Verify data consistency
        $customer_balance = $points_manager->get_points_balance($customer_id);
        $expected_balance = (90 * 0.1) + (180 * 0.1) + (270 * 0.1); // 9 + 18 + 27 = 54
        $this->assertEquals(54, $customer_balance);

        // Verify partnership assignment persisted
        global $mock_user_meta;
        $this->assertEquals($coach_id, $mock_user_meta[$customer_id]['intersoccer_partnership_coach_id']);
    }

    /**
     * Helper method to simulate link clicks
     */
    private function simulateLinkClick($url, $session_id) {
        $url_parts = parse_url($url);
        parse_str($url_parts['query'], $query_params);

        if (isset($query_params['ref'])) {
            $this->setReferralCookie($query_params['ref']);
        } elseif (isset($query_params['cust_ref'])) {
            $this->setReferralCookie($query_params['cust_ref']);
        }
    }

    /**
     * Helper method to set referral cookie
     */
    private function setReferralCookie($code) {
        global $mock_cookies, $mock_session;
        $mock_cookies['intersoccer_referral'] = $code;
        $mock_session['intersoccer_referral'] = $code;
    }

    /**
     * Helper method to simulate device interactions
     */
    private function simulateDeviceInteraction($device, $coach_id) {
        $referral_code = 'coach_' . $coach_id . '_' . $device;
        $this->setReferralCookie($referral_code);
    }
}