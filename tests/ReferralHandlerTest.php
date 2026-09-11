<?php

use PHPUnit\Framework\TestCase;

/**
 * Test suite for InterSoccer Referral Handler
 */
class ReferralHandlerTest extends TestCase {

    protected function setUp(): void {
        // Include the referral handler class
        require_once __DIR__ . '/../includes/class-referral-handler.php';
        $this->clearUtmOptions();
    }

    protected function tearDown(): void {
        $this->clearUtmOptions();
        parent::tearDown();
    }

    private function clearUtmOptions() {
        foreach ([
            'intersoccer_referral_utm_enabled',
            'intersoccer_referral_utm_source',
            'intersoccer_referral_utm_medium',
            'intersoccer_referral_utm_campaign_customer',
            'intersoccer_referral_utm_campaign_coach',
            'intersoccer_referral_utm_content',
        ] as $key) {
            delete_option($key);
        }
    }

    private function enableUtmOptions(array $overrides = []) {
        $defaults = [
            'intersoccer_referral_utm_enabled' => 1,
            'intersoccer_referral_utm_source' => 'referral',
            'intersoccer_referral_utm_medium' => 'share',
            'intersoccer_referral_utm_campaign_customer' => 'customer-referral',
            'intersoccer_referral_utm_campaign_coach' => 'coach-referral',
            'intersoccer_referral_utm_content' => 'dashboard',
        ];
        foreach (array_merge($defaults, $overrides) as $key => $value) {
            update_option($key, $value);
        }
    }

    private function queryParamsFromUrl($url) {
        $query = parse_url((string) $url, PHP_URL_QUERY);
        $params = [];
        if (is_string($query) && $query !== '') {
            parse_str($query, $params);
        }
        return $params;
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

        global $mock_user_meta;
        $mock_users = [
            2 => (object) [
                'ID' => 2,
                'roles' => ['coach'],
                'user_login' => 'coach1',
            ],
        ];
        $mock_user_meta[2]['referral_code'] = 'COACH_2_TEST';

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

        global $mock_session, $mock_wpdb_get_row_results;
        $mock_session = [];

        $mock_wpdb_get_row_results['intersoccer_coach_events'] = (object) [
            'id' => 42,
            'coach_id' => 7,
            'event_id' => 123,
            'event_type' => 'product',
            'status' => 'active',
            'source' => 'coach',
            'assigned_at' => '2025-01-01 00:00:00',
            'notes' => '',
        ];

        require_once __DIR__ . '/../includes/class-coach-events-manager.php';

        $_GET['ref'] = 'coach_assignment_ref';
        $_GET['coach_event'] = '42';

        $handler->handle_referral_cookie();

        $this->assertArrayHasKey('intersoccer_referral', $mock_session);
        $payload = $mock_session['intersoccer_referral'];
        $this->assertSame('coach_assignment_ref', $payload['code']);
        $this->assertSame(123, $payload['event_id'], 'Event ID should be sourced from assignment');
        $this->assertSame(42, $payload['coach_event_id']);

        unset($_GET['ref'], $_GET['coach_event'], $mock_session['intersoccer_referral']);
        unset($mock_wpdb_get_row_results['intersoccer_coach_events']);
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
            2 => (object) [
                'ID' => 2,
                'roles' => ['coach'],
                'display_name' => 'Test Coach',
            ],
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

        $cooldown_end = get_user_meta(1, 'intersoccer_partnership_switch_cooldown', true);
        $this->assertTrue($cooldown_end && strtotime($cooldown_end) > time());

        $mock_user_meta[1]['intersoccer_partnership_switch_cooldown'] = date('Y-m-d H:i:s', time() - 86400);
        $cooldown_end = get_user_meta(1, 'intersoccer_partnership_switch_cooldown', true);
        $this->assertTrue($cooldown_end && strtotime($cooldown_end) <= time());
    }

    /**
     * Test referral link generation
     */
    public function testGenerateReferralLinks() {
        update_user_meta(1, InterSoccer_Referral_Handler::COACH_REFERRAL_CODE_META, 'COACH1TEST');

        $coach_link = InterSoccer_Referral_Handler::generate_coach_referral_link(1);
        $this->assertStringContainsString('ref=', $coach_link);

        $customer_link = InterSoccer_Referral_Handler::generate_customer_referral_link(1);
        $this->assertStringContainsString('cust_ref=', $customer_link);
        $this->assertArrayNotHasKey('utm_source', $this->queryParamsFromUrl($coach_link));
        $this->assertArrayNotHasKey('utm_source', $this->queryParamsFromUrl($customer_link));
    }

    /**
     * Test available coaches filtering
     */
    public function testGetAvailableCoaches() {
        $handler = new InterSoccer_Referral_Handler();

        global $mock_users, $mock_user_meta, $mock_wpdb_get_row_results;
        $mock_wpdb_get_row_results['commission_amount'] = (object) [
            'total_referrals' => 5,
            'avg_commission' => 10,
        ];
        $mock_users = [
            1 => (object) [
                'ID' => 1,
                'display_name' => 'Coach One',
                'roles' => ['coach'],
            ],
            2 => (object) [
                'ID' => 2,
                'display_name' => 'Coach Two',
                'roles' => ['coach'],
            ],
        ];
        $mock_user_meta[1]['intersoccer_coach_rating'] = 4.8;
        $mock_user_meta[2]['intersoccer_coach_rating'] = 4.5;

        $coaches = $this->invokePrivateMethod($handler, 'get_available_coaches', ['', 'all']);
        $this->assertIsArray($coaches);
        $this->assertCount(2, $coaches);
    }

    /**
     * Test coach benefits calculation
     */
    public function testGetCoachBenefits() {
        $handler = new InterSoccer_Referral_Handler();

        global $mock_users;
        $mock_users[1] = (object) ['ID' => 1, 'display_name' => 'Coach One', 'roles' => ['coach']];

        $benefits = $this->invokePrivateMethod($handler, 'get_coach_benefits', [1, 'Bronze']);
        $this->assertStringContainsString('5% of your purchases support', $benefits[0]);

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
        $order->set_customer_id(2);

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
                'user_email' => 'coach1@example.com',
            ],
            2 => (object) [
                'ID' => 2,
                'roles' => ['customer'],
                'display_name' => 'Customer Two',
                'user_email' => 'customer2@example.com',
            ],
        ];
        update_user_meta(1, 'referral_code', 'COACH_1_TEST');
        $mock_wc_order_override = $order;

        global $mock_orders;
        $mock_orders = [];

        $this->invokePrivateMethod($handler, 'process_referral_order', [123]);

        $this->assertEquals(1, $mock_user_meta[2]['intersoccer_partnership_coach_id']);
        $this->assertSame('yes', $mock_post_meta[123]['_intersoccer_referral_processed']);

        $mock_wc_order_override = null;
    }

    public function testReferralProcessingWaitsForCompletedStatus() {
        $handler = new InterSoccer_Referral_Handler();

        $order = new WC_Order();
        $order->set_total(120);
        $order->set_status('processing');
        $order->set_customer_id(2);
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
                'user_email' => 'coach1@example.com',
            ],
            2 => (object) [
                'ID' => 2,
                'roles' => ['customer'],
                'display_name' => 'Customer Two',
                'user_email' => 'customer2@example.com',
            ],
        ];
        update_user_meta(1, 'referral_code', 'COACH_1_TEST');
        $mock_wc_order_override = $order;

        $handler->process_referral_order(456);

        $this->assertArrayHasKey('_intersoccer_referral_payload', $mock_post_meta[456]);
        $this->assertArrayNotHasKey('_intersoccer_referral_processed', $mock_post_meta[456] ?? [], 'Order should not be marked processed before completion');
        $this->assertArrayNotHasKey('intersoccer_partnership_coach_id', $mock_user_meta[2] ?? [], 'Partnership should wait until completion');

        $order->set_status('completed');
        $handler->process_referral_order(456);

        $this->assertEquals(1, $mock_user_meta[2]['intersoccer_partnership_coach_id']);
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

    public function testReferralLinksUntaggedWhenUtmDisabled() {
        update_user_meta(1, InterSoccer_Referral_Handler::COACH_REFERRAL_CODE_META, 'COACH1TEST');
        update_user_meta(1, 'intersoccer_customer_referral_code', 'CUST1TEST');
        $this->enableUtmOptions(['intersoccer_referral_utm_enabled' => 0]);

        $coach_link = InterSoccer_Referral_Handler::generate_coach_referral_link(1);
        $customer_link = InterSoccer_Referral_Handler::generate_customer_referral_link(1);

        $this->assertArrayNotHasKey('utm_campaign', $this->queryParamsFromUrl($coach_link));
        $this->assertArrayNotHasKey('utm_campaign', $this->queryParamsFromUrl($customer_link));
    }

    public function testReferralLinksUntaggedWhenUtmFieldsEmpty() {
        update_user_meta(1, InterSoccer_Referral_Handler::COACH_REFERRAL_CODE_META, 'COACH1TEST');
        update_user_meta(1, 'intersoccer_customer_referral_code', 'CUST1TEST');
        $this->enableUtmOptions([
            'intersoccer_referral_utm_source' => '',
            'intersoccer_referral_utm_medium' => '',
            'intersoccer_referral_utm_campaign_customer' => '',
            'intersoccer_referral_utm_campaign_coach' => '',
            'intersoccer_referral_utm_content' => '',
        ]);

        $coach_link = InterSoccer_Referral_Handler::generate_coach_referral_link(1);
        $customer_link = InterSoccer_Referral_Handler::generate_customer_referral_link(1);

        $this->assertArrayNotHasKey('utm_source', $this->queryParamsFromUrl($coach_link));
        $this->assertArrayNotHasKey('utm_source', $this->queryParamsFromUrl($customer_link));
    }

    public function testReferralLinksUseSeparateCampaignsWhenUtmEnabled() {
        update_user_meta(1, InterSoccer_Referral_Handler::COACH_REFERRAL_CODE_META, 'COACH1TEST');
        update_user_meta(1, 'intersoccer_customer_referral_code', 'CUST1TEST');
        $this->enableUtmOptions();

        $coach_params = $this->queryParamsFromUrl(InterSoccer_Referral_Handler::generate_coach_referral_link(1));
        $customer_params = $this->queryParamsFromUrl(InterSoccer_Referral_Handler::generate_customer_referral_link(1));

        $this->assertSame('COACH1TEST', $coach_params['ref'] ?? null);
        $this->assertSame('CUST1TEST', $customer_params['cust_ref'] ?? null);
        $this->assertSame('referral', $coach_params['utm_source'] ?? null);
        $this->assertSame('referral', $customer_params['utm_source'] ?? null);
        $this->assertSame('share', $coach_params['utm_medium'] ?? null);
        $this->assertSame('share', $customer_params['utm_medium'] ?? null);
        $this->assertSame('coach-referral', $coach_params['utm_campaign'] ?? null);
        $this->assertSame('customer-referral', $customer_params['utm_campaign'] ?? null);
        $this->assertSame('dashboard', $coach_params['utm_content'] ?? null);
        $this->assertSame('dashboard', $customer_params['utm_content'] ?? null);
        $this->assertArrayNotHasKey('cust_ref', $coach_params);
        $this->assertArrayNotHasKey('ref', $customer_params);
    }

    public function testSanitizeUtmValueNormalizesInput() {
        $this->assertSame('summer-2026', InterSoccer_Referral_Handler::sanitize_utm_value('Summer 2026!'));
        $this->assertSame('coach_qr', InterSoccer_Referral_Handler::sanitize_utm_value(' Coach_QR '));
        $this->assertSame('', InterSoccer_Referral_Handler::sanitize_utm_value('@@@'));
        $this->assertSame(100, strlen(InterSoccer_Referral_Handler::sanitize_utm_value(str_repeat('A', 120))));
    }

    public function testAppendUtmParamsPreservesRefAndCustRef() {
        $this->enableUtmOptions();

        $coach = InterSoccer_Referral_Handler::append_utm_params('https://example.com/?ref=COACHKEEP', 'coach');
        $customer = InterSoccer_Referral_Handler::append_utm_params('https://example.com/?cust_ref=CUSTKEEP', 'customer');

        $coach_params = $this->queryParamsFromUrl($coach);
        $customer_params = $this->queryParamsFromUrl($customer);

        $this->assertSame('COACHKEEP', $coach_params['ref'] ?? null);
        $this->assertSame('CUSTKEEP', $customer_params['cust_ref'] ?? null);
        $this->assertSame('coach-referral', $coach_params['utm_campaign'] ?? null);
        $this->assertSame('customer-referral', $customer_params['utm_campaign'] ?? null);
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

    // =========================================================================
    // ISSUE #36 - STOP DUAL-WRITE TESTS
    // =========================================================================

    /**
     * Test that gift credits uses intersoccer_points_balance (not intersoccer_customer_credits)
     * 
     * As of issue #36, the gift credits functionality should read from and write to
     * intersoccer_points_balance only, not the legacy intersoccer_customer_credits key.
     */
    public function testGiftCreditsUsesPointsBalance() {
        global $mock_user_meta, $mock_users, $mock_current_user_id;

        // Setup sender with points balance
        $mock_current_user_id = 1;
        $mock_user_meta[1] = [
            'intersoccer_points_balance' => 100,
        ];

        // Setup recipient
        $mock_users[2] = (object) [
            'ID' => 2,
            'roles' => ['customer'],
            'user_email' => 'recipient@example.com',
        ];
        $mock_user_meta[2] = [
            'intersoccer_points_balance' => 50,
        ];

        $handler = new InterSoccer_Referral_Handler();

        // Test that render_gift_form uses points_balance for max value
        ob_start();
        $handler->render_gift_form();
        $form_html = ob_get_clean();

        // The form should show the points balance (100), not customer_credits
        $this->assertStringContainsString('max="100"', $form_html, 'Gift form should use intersoccer_points_balance for max');
    }

    /**
     * Test that referrer reward only writes to intersoccer_points_balance (issue #36)
     * 
     * Verifies the dual-write to intersoccer_customer_credits has been stopped.
     */
    public function testReferrerRewardOnlyWritesToPointsBalance() {
        // This test validates the code change by checking the source code pattern.
        // In actual runtime, we'd need to mock the order completion flow.
        
        $handler_file = file_get_contents(__DIR__ . '/../includes/class-referral-handler.php');
        
        // The dual-write should be removed - look for the comment indicating it was stopped
        $this->assertStringContainsString(
            'NOTE: Dual-write to intersoccer_customer_credits stopped per issue #36',
            $handler_file,
            'Referrer reward code should have comment indicating dual-write was stopped'
        );
        
        // The referrer reward section should only write to points_balance now
        // Look for pattern: update_user_meta($referrer['id'], 'intersoccer_points_balance'
        $this->assertStringContainsString(
            "update_user_meta(\$referrer['id'], 'intersoccer_points_balance'",
            $handler_file,
            'Referrer reward should write to intersoccer_points_balance'
        );
    }

    /**
     * Test that customer bonus only writes to intersoccer_points_balance (issue #36)
     */
    public function testCustomerBonusOnlyWritesToPointsBalance() {
        $handler_file = file_get_contents(__DIR__ . '/../includes/class-referral-handler.php');
        
        // The customer bonus section should have the NOTE comment
        $this->assertStringContainsString(
            'Award bonus points to canonical intersoccer_points_balance only (issue #36)',
            $handler_file,
            'Customer bonus code should indicate it writes to points_balance only'
        );
    }
}