<?php

use PHPUnit\Framework\TestCase;

if (!function_exists('is_admin')) {
    function is_admin() {
        return false;
    }
}

if (!function_exists('wp_doing_ajax')) {
    function wp_doing_ajax() {
        return false;
    }
}

if (!function_exists('is_checkout')) {
    function is_checkout() {
        return true;
    }
}

class CheckoutReferralDiscountTest extends TestCase {
    /** @var InterSoccer_Referral_Admin_Dashboard */
    private $dashboard;

    private function invokeApplyInternal($code, array $args = []) {
        $reflection = new ReflectionClass(InterSoccer_Referral_Admin_Dashboard::class);
        $method = $reflection->getMethod('apply_coach_referral_code_internal');
        $method->setAccessible(true);
        return $method->invoke($this->dashboard, $code, $args);
    }

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/class-admin-dashboard.php';

        $reflection = new ReflectionClass(InterSoccer_Referral_Admin_Dashboard::class);
        $this->dashboard = $reflection->newInstanceWithoutConstructor();

        global $mock_session, $mock_user_meta, $mock_users, $mock_current_user_id, $mock_orders;
        $mock_session = [];
        $mock_user_meta = [];
        $mock_users = [];
        $mock_current_user_id = 501;
        $mock_orders = [];

        $coach = (object) [
            'ID' => 902,
            'roles' => ['coach'],
            'first_name' => 'Taylor',
            'last_name' => 'Swift',
            'display_name' => 'Coach Taylor'
        ];
        $mock_users[$coach->ID] = $coach;
        update_user_meta($coach->ID, 'referral_code', 'COACHSWIFT');

        WC()->cart = new class {
            public $fees = [];
            public $calculate_called = false;
            public $set_session_called = false;
            public function calculate_totals() {
                $this->calculate_called = true;
            }
            public function set_session() {
                $this->set_session_called = true;
            }
            public function add_fee($name, $amount, $taxable = true, $tax_class = '') {
                $this->fees[] = compact('name', 'amount', 'taxable', 'tax_class');
            }
        };
    }

    protected function tearDown(): void {
        global $mock_session, $mock_user_meta, $mock_users, $mock_orders;
        $mock_session = [];
        $mock_user_meta = [];
        $mock_users = [];
        $mock_orders = [];
        WC()->cart = null;
    }

    public function testCoachReferralApplicationStoresSessionAndAddsDiscount() {
        global $mock_session;

        $result = $this->invokeApplyInternal('COACHSWIFT', [
            'recalculate' => true,
            'context' => 'test'
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('COACHSWIFT', $mock_session['intersoccer_applied_referral_code']);
        $this->assertSame(902, $mock_session['intersoccer_referral_coach_id']);
        $this->assertTrue(WC()->cart->calculate_called);
        $this->assertTrue(WC()->cart->set_session_called);
        $this->assertSame(10, $result['discount_amount']);

        // Applying fees should add the 10 CHF discount.
        $this->dashboard->apply_points_discount_as_fee(WC()->cart);
        $this->assertNotEmpty(WC()->cart->fees);

        $coachFee = null;
        foreach (WC()->cart->fees as $fee) {
            if ($fee['name'] === 'Coach Referral Discount') {
                $coachFee = $fee;
                break;
            }
        }

        $this->assertNotNull($coachFee, 'Coach referral fee should exist');
        $this->assertEquals(-10, $coachFee['amount']);
    }

    public function testCoachReferralApplicationBlocksCustomerCodeConflict() {
        global $mock_session;
        $mock_session['customer_referral_code'] = 'FRIEND123';

        $result = $this->invokeApplyInternal('COACHSWIFT');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('friend referral code', $result['message']);
        $this->assertArrayNotHasKey('intersoccer_applied_referral_code', $mock_session);
    }

    public function testCoachReferralApplicationRejectsInvalidCode() {
        global $mock_users;
        $mock_users = []; // No coach available for lookup

        $result = $this->invokeApplyInternal('UNKNOWN');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid referral code', $result['message']);
    }

    public function testCoachReferralDiscountNotAppliedAfterFirstOrder() {
        global $mock_orders;
        $mock_orders = [1234]; // simulate existing completed order

        $result = $this->invokeApplyInternal('COACHSWIFT', [
            'recalculate' => true,
            'context' => 'test'
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['discount_amount']);
        $this->assertStringContainsString('First-time discount already used', $result['message']);

        // Ensure no coach discount fee is added when calculating totals
        WC()->cart->fees = [];
        $this->dashboard->apply_points_discount_as_fee(WC()->cart);

        $coachFee = array_filter(WC()->cart->fees, function($fee) {
            return $fee['name'] === 'Coach Referral Discount';
        });

        $this->assertEmpty($coachFee, 'Coach referral fee should not be added for returning customers');
    }
}
