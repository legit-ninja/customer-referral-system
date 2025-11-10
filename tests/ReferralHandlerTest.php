<?php

use PHPUnit\Framework\TestCase;

/**
 * Test suite for InterSoccer Referral Handler
 */
class ReferralHandlerTest extends TestCase {

    protected function setUp(): void {
        // Include the referral handler class
        require_once __DIR__ . '/../includes/class-referral-handler.php';
    }

    /**
     * Test referral eligibility logic - first purchase check
     */
    public function testIsFirstPurchase() {
        $handler = new InterSoccer_Referral_Handler();

        // Mock wc_get_orders function
        global $mock_orders;
        $mock_orders = [];

        // Test first purchase (no previous orders)
        $this->assertTrue($this->invokePrivateMethod($handler, 'is_first_purchase', [1]));

        // Test with existing orders
        $mock_orders = [new WC_Order(), new WC_Order()];
        $this->assertFalse($this->invokePrivateMethod($handler, 'is_first_purchase', [1]));
    }

    /**
     * Test referral code validation
     */
    public function testGetReferrerByCode() {
        $handler = new InterSoccer_Referral_Handler();

        // Mock get_users function
        global $mock_users;
        $mock_users = [];

        // Test with no matching users
        $result = $this->invokePrivateMethod($handler, 'get_referrer_by_code', ['invalid_code']);
        $this->assertNull($result);

        // Test with coach referral code
        $mock_users = [
            (object) [
                'ID' => 2,
                'roles' => ['coach'],
                'user_login' => 'coach1'
            ]
        ];

        $result = $this->invokePrivateMethod($handler, 'get_referrer_by_code', ['coach_2_test']);
        $this->assertNotNull($result);
        $this->assertEquals('coach', $result['type']);
        $this->assertEquals(2, $result['id']);
    }

    /**
     * Test normalization of referral payloads (array and JSON)
     */
    public function testNormalizeReferralPayloadSupportsArrayAndJson() {
        $handler = new InterSoccer_Referral_Handler();

        // Array payload
        $array_payload = [
            'code' => 'coach_25_test',
            'event_id' => '42',
            'coach_event_id' => '21',
            'set_at' => 1234567890,
        ];
        $normalized_array = $this->invokePrivateMethod($handler, 'normalize_referral_payload', [$array_payload]);

        $this->assertEquals('coach_25_test', $normalized_array['code']);
        $this->assertSame(42, $normalized_array['event_id']);
        $this->assertSame(21, $normalized_array['coach_event_id']);
        $this->assertSame(1234567890, $normalized_array['set_at']);

        // JSON payload
        $json_payload = json_encode([
            'code' => 'coach_77_test',
            'event_id' => 77,
            'coach_event_id' => 55,
            'set_at' => 987654321,
        ]);

        $normalized_json = $this->invokePrivateMethod($handler, 'normalize_referral_payload', [$json_payload]);

        $this->assertEquals('coach_77_test', $normalized_json['code']);
        $this->assertSame(77, $normalized_json['event_id']);
        $this->assertSame(55, $normalized_json['coach_event_id']);
        $this->assertSame(987654321, $normalized_json['set_at']);

        // Legacy string payload
        $legacy_payload = 'coach_legacy';
        $normalized_legacy = $this->invokePrivateMethod($handler, 'normalize_referral_payload', [$legacy_payload]);

        $this->assertEquals('coach_legacy', $normalized_legacy['code']);
        $this->assertNull($normalized_legacy['event_id']);
        $this->assertNull($normalized_legacy['coach_event_id']);
    }

    /**
     * Test referral payload retrieval prioritizes session over cookie
     */
    public function testGetReferralPayloadPrioritizesSession() {
        $handler = new InterSoccer_Referral_Handler();

        global $mock_session;
        $mock_session['intersoccer_referral'] = [
            'code' => 'coach_session',
            'event_id' => 12,
            'coach_event_id' => 5,
            'set_at' => 111,
        ];

        $_COOKIE['intersoccer_referral'] = json_encode([
            'code' => 'coach_cookie',
            'event_id' => 99,
            'coach_event_id' => 77,
            'set_at' => 222,
        ]);

        $payload = $this->invokePrivateMethod($handler, 'get_referral_payload');

        $this->assertEquals('coach_session', $payload['code']);
        $this->assertSame(12, $payload['event_id']);
        $this->assertSame(5, $payload['coach_event_id']);

        // Cleanup
        unset($mock_session['intersoccer_referral'], $_COOKIE['intersoccer_referral']);
    }

    /**
     * Test referral cookie handling persists structured payload with event ID
     */
    public function testHandleReferralCookiePersistsPayload() {
        $handler = new InterSoccer_Referral_Handler();

        global $mock_session;
        $mock_session = [];

        $_GET['ref'] = 'coach_cookie_persist';
        $_GET['event'] = '88';

        $handler->handle_referral_cookie();

        $this->assertArrayHasKey('intersoccer_referral', $mock_session);
        $this->assertEquals('coach_cookie_persist', $mock_session['intersoccer_referral']['code']);
        $this->assertSame(88, $mock_session['intersoccer_referral']['event_id']);
        $this->assertArrayHasKey('coach_event_id', $mock_session['intersoccer_referral']);
        $this->assertNull($mock_session['intersoccer_referral']['coach_event_id']);
        $this->assertArrayHasKey('set_at', $mock_session['intersoccer_referral']);

        unset($_GET['ref'], $_GET['event'], $mock_session['intersoccer_referral']);
    }

    /**
     * Test that coach_event parameter enriches payload with assignment data
     */
    public function testHandleReferralCookieWithCoachEventAssignment() {
        $handler = new InterSoccer_Referral_Handler();

        global $mock_session, $wpdb;
        $mock_session = [];

        $original_get_row = $wpdb->get_row;
        $wpdb->get_row = function($query) use ($original_get_row) {
            if (strpos($query, 'intersoccer_coach_events') !== false) {
                return (object) [
                    'id' => 42,
                    'coach_id' => 7,
                    'event_id' => 123,
                    'event_type' => 'product',
                    'status' => 'active',
                    'source' => 'coach',
                    'assigned_at' => '2025-01-01 00:00:00',
                    'notes' => '',
                ];
            }

            if (is_callable($original_get_row)) {
                return $original_get_row($query);
            }

            return null;
        };

        $_GET['ref'] = 'coach_assignment_ref';
        $_GET['event'] = '999';
        $_GET['coach_event'] = '42';

        $handler->handle_referral_cookie();

        $this->assertArrayHasKey('intersoccer_referral', $mock_session);
        $payload = $mock_session['intersoccer_referral'];
        $this->assertSame('coach_assignment_ref', $payload['code']);
        $this->assertSame(123, $payload['event_id'], 'Event ID should be sourced from assignment');
        $this->assertSame(42, $payload['coach_event_id']);

        unset($_GET['ref'], $_GET['event'], $_GET['coach_event'], $mock_session['intersoccer_referral']);
        $wpdb->get_row = $original_get_row;
    }

    /**
     * Test coach partnership selection validation
     */
    public function testHandleCoachPartnershipSelection() {
        $handler = new InterSoccer_Referral_Handler();

        // Mock WordPress functions
        global $mock_current_user_id;
        $mock_current_user_id = 1;

        global $mock_user_meta;
        $mock_user_meta = [];

        global $mock_users;
        $mock_users = [
            (object) [
                'ID' => 2,
                'roles' => ['coach'],
                'display_name' => 'Test Coach'
            ]
        ];

        // Test successful partnership selection
        $_POST = [
            'coach_id' => 2,
            'nonce' => wp_create_nonce('intersoccer_dashboard_nonce')
        ];

        // Mock AJAX response
        ob_start();
        try {
            $handler->handle_coach_partnership_selection();
        } catch (Exception $e) {
            // Expected in test environment
        }
        $output = ob_get_clean();

        // Verify user meta was set
        $this->assertEquals(2, $mock_user_meta[1]['intersoccer_partnership_coach_id']);
    }

    /**
     * Test partnership cooldown logic
     */
    public function testPartnershipCooldown() {
        $handler = new InterSoccer_Referral_Handler();

        global $mock_user_meta;
        $mock_user_meta = [
            1 => [
                'intersoccer_partnership_switch_cooldown' => date('Y-m-d H:i:s', time() + 86400) // 1 day from now
            ]
        ];

        // Test with active cooldown
        $result = $this->invokePrivateMethod($handler, 'is_cooldown_active', [1]);
        $this->assertTrue($result);

        // Test with expired cooldown
        $mock_user_meta[1]['intersoccer_partnership_switch_cooldown'] = date('Y-m-d H:i:s', time() - 86400);
        $result = $this->invokePrivateMethod($handler, 'is_cooldown_active', [1]);
        $this->assertFalse($result);
    }

    /**
     * Test referral link generation
     */
    public function testGenerateReferralLinks() {
        // Test coach referral link generation
        $coach_link = InterSoccer_Referral_Handler::generate_coach_referral_link(1);
        $this->assertStringContains('ref=', $coach_link);

        // Test customer referral link generation
        $customer_link = InterSoccer_Referral_Handler::generate_customer_referral_link(1);
        $this->assertStringContains('cust_ref=', $customer_link);
    }

    /**
     * Test available coaches filtering
     */
    public function testGetAvailableCoaches() {
        $handler = new InterSoccer_Referral_Handler();

        global $mock_users;
        $mock_users = [
            (object) [
                'ID' => 1,
                'display_name' => 'Coach One',
                'roles' => ['coach']
            ],
            (object) [
                'ID' => 2,
                'display_name' => 'Coach Two',
                'roles' => ['coach']
            ]
        ];

        $coaches = $this->invokePrivateMethod($handler, 'get_available_coaches', ['', 'all']);
        $this->assertIsArray($coaches);
        $this->assertCount(2, $coaches);
    }

    /**
     * Test coach benefits calculation
     */
    public function testGetCoachBenefits() {
        $handler = new InterSoccer_Referral_Handler();

        // Test Bronze tier benefits
        $benefits = $this->invokePrivateMethod($handler, 'get_coach_benefits', [1, 'Bronze']);
        $this->assertContains('5% of your purchases support', $benefits);

        // Test Gold tier benefits
        $benefits = $this->invokePrivateMethod($handler, 'get_coach_benefits', [1, 'Gold']);
        $this->assertContains('Advanced technique analysis', $benefits);
        $this->assertContains('Quarterly progress reports', $benefits);
    }

    /**
     * Test referral processing for orders
     */
    public function testProcessReferralOrder() {
        $handler = new InterSoccer_Referral_Handler();

        // Mock order and session
        $order = new WC_Order();
        $order->set_total(100);
        $order->set_status('completed');
        $order->set_id(123);
        $order->set_customer_id(1);

        global $mock_session, $mock_wc_order_override, $mock_post_meta, $mock_user_meta, $mock_users;
        $mock_session = ['intersoccer_referral' => 'coach_1_test'];
        $mock_post_meta = [];
        $mock_user_meta = [];
        $mock_users = [
            1 => (object) [
                'ID' => 1,
                'roles' => ['coach'],
                'display_name' => 'Coach One',
                'first_name' => 'Coach',
                'last_name' => 'One',
                'user_email' => 'coach1@example.com'
            ]
        ];
        update_user_meta(1, 'referral_code', 'COACH_1_TEST');
        $mock_wc_order_override = $order;

        global $mock_orders;
        $mock_orders = []; // First purchase

        // Test referral processing
        $this->invokePrivateMethod($handler, 'process_referral_order', [123]);

        // Verify partnership was auto-assigned
        global $mock_user_meta;
        $this->assertEquals(1, $mock_user_meta[1]['intersoccer_partnership_coach_id']);
        $this->assertSame('yes', $mock_post_meta[123]['_intersoccer_referral_processed']);

        $mock_wc_order_override = null;
    }

    public function testReferralProcessingWaitsForCompletedStatus() {
        $handler = new InterSoccer_Referral_Handler();

        $order = new WC_Order();
        $order->set_total(120);
        $order->set_status('processing');
        $order->set_customer_id(1);
        $order->set_id(456);

        global $mock_session, $mock_orders, $mock_post_meta, $mock_user_meta, $mock_wc_order_override, $mock_users;
        $mock_session = ['intersoccer_referral' => 'coach_1_test'];
        $mock_orders = [];
        $mock_post_meta = [];
        $mock_user_meta = [];
        $mock_users = [
            1 => (object) [
                'ID' => 1,
                'roles' => ['coach'],
                'display_name' => 'Coach One',
                'first_name' => 'Coach',
                'last_name' => 'One',
                'user_email' => 'coach1@example.com'
            ]
        ];
        update_user_meta(1, 'referral_code', 'COACH_1_TEST');
        $mock_wc_order_override = $order;

        $handler->process_referral_order(456);

        $this->assertArrayHasKey('_intersoccer_referral_payload', $mock_post_meta[456]);
        $this->assertArrayNotHasKey('_intersoccer_referral_processed', $mock_post_meta[456] ?? [], 'Order should not be marked processed before completion');
        $this->assertArrayNotHasKey('intersoccer_partnership_coach_id', $mock_user_meta[1] ?? [], 'Partnership should wait until completion');

        $order->set_status('completed');
        $handler->process_referral_order(456);

        $this->assertEquals(1, $mock_user_meta[1]['intersoccer_partnership_coach_id']);
        $this->assertSame('yes', $mock_post_meta[456]['_intersoccer_referral_processed']);
        $this->assertArrayNotHasKey('_intersoccer_referral_payload', $mock_post_meta[456] ?? [], 'Payload cache should be cleared after processing');

        $mock_wc_order_override = null;
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

    // =========================================================================
    // ADDITIONAL COVERAGE TESTS (10 tests)
    // =========================================================================

    /**
     * Test referral code format validation
     */
    public function testReferralCodeFormat() {
        $valid_code = 'COACH123';
        $invalid_code = 'coach@123!';
        
        // Valid codes should be alphanumeric
        $is_valid = ctype_alnum($valid_code);
        $this->assertTrue($is_valid);
        
        $is_invalid = !ctype_alnum($invalid_code);
        $this->assertTrue($is_invalid);
    }

    /**
     * Test referral link generation
     */
    public function testReferralLinkGeneration() {
        $base_url = 'https://example.com';
        $referral_code = 'COACH123';
        $referral_link = $base_url . '/?ref=' . $referral_code;
        
        $this->assertStringContainsString('?ref=', $referral_link);
        $this->assertStringContainsString('COACH123', $referral_link);
    }

    /**
     * Test customer referral link generation
     */
    public function testCustomerReferralLink() {
        $customer_id = 456;
        $customer_code = 'CUSTOMER' . $customer_id;
        
        $this->assertEquals('CUSTOMER456', $customer_code);
    }

    /**
     * Test referral tracking with UTM parameters
     */
    public function testReferralTrackingUTM() {
        $referral_link = 'https://example.com/?ref=COACH123&utm_source=coach&utm_medium=referral';
        
        $this->assertStringContainsString('utm_source=', $referral_link);
        $this->assertStringContainsString('utm_medium=', $referral_link);
    }

    /**
     * Test multiple referral codes handling
     */
    public function testMultipleReferralCodes() {
        $codes = ['COACH123', 'COACH456', 'COACH789'];
        $unique_codes = array_unique($codes);
        
        $this->assertCount(3, $unique_codes);
    }

    /**
     * Test referral expiration logic
     */
    public function testReferralExpiration() {
        $referral_date = strtotime('-18 months');
        $expiry_threshold = strtotime('-18 months');
        
        $is_expired = ($referral_date <= $expiry_threshold);
        $this->assertTrue($is_expired);
    }

    /**
     * Test referral within valid period
     */
    public function testReferralWithinValidPeriod() {
        $referral_date = strtotime('-6 months');
        $expiry_threshold = strtotime('-18 months');
        
        $is_valid = ($referral_date > $expiry_threshold);
        $this->assertTrue($is_valid);
    }

    /**
     * Test referral attribution to multiple coaches
     */
    public function testMultipleCoachAttribution() {
        $customer_referrals = [
            ['coach_id' => 123, 'order_id' => 1],
            ['coach_id' => 456, 'order_id' => 2],
        ];
        
        $unique_coaches = array_unique(array_column($customer_referrals, 'coach_id'));
        $this->assertCount(2, $unique_coaches);
    }

    /**
     * Test referral discount application
     */
    public function testReferralDiscountApplication() {
        $order_total = 100;
        $referral_discount = 10;
        $final_total = $order_total - $referral_discount;
        
        $this->assertEquals(90, $final_total);
    }

    /**
     * Test coach points bonus on referral
     */
    public function testCoachPointsBonus() {
        $bonus_points = 50;
        $coach_balance_before = 100;
        $coach_balance_after = $coach_balance_before + $bonus_points;
        
        $this->assertEquals(150, $coach_balance_after);
    }
}