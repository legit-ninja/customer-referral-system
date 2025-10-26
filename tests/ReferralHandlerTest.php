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

        global $mock_session;
        $mock_session = ['intersoccer_referral' => 'coach_1_test'];

        global $mock_orders;
        $mock_orders = []; // First purchase

        // Test referral processing
        $this->invokePrivateMethod($handler, 'process_referral_order', [123]);

        // Verify partnership was auto-assigned
        global $mock_user_meta;
        $this->assertEquals(1, $mock_user_meta[1]['intersoccer_partnership_coach_id']);
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