<?php

use PHPUnit\Framework\TestCase;

/**
 * Points taken from the checkout session must not be spent on a second order.
 */
class PointsDoubleSpendTest extends TestCase {

    /** @var InterSoccer_Referral_Admin_Dashboard */
    private $dashboard;

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/class-admin-dashboard.php';
        require_once __DIR__ . '/../includes/class-points-manager.php';

        $reflection = new ReflectionClass(InterSoccer_Referral_Admin_Dashboard::class);
        $this->dashboard = $reflection->newInstanceWithoutConstructor();

        global $mock_session, $mock_user_meta, $mock_current_user_id, $mock_points_log_rows, $mock_points_balances, $mock_points_balance_rows;
        $mock_session = [];
        $mock_user_meta = [];
        $mock_points_balance_rows = [];
        $mock_current_user_id = 8801;
        $mock_points_log_rows = [];
        $mock_points_balances = [];

        if (function_exists('WC')) {
            WC()->session = WC::session();
            WC()->cart = null;
        }
    }

    protected function tearDown(): void {
        global $mock_session, $mock_user_meta, $mock_current_user_id;
        $mock_session = [];
        $mock_user_meta = [];
        $mock_current_user_id = 1;
        if (function_exists('WC')) {
            WC()->session = WC::session();
            WC()->cart = null;
        }
        parent::tearDown();
    }

    private function cart() {
        return new class {
            public $fees = [];
            public function add_fee($name, $amount, $taxable = false, $tax_class = '') {
                $this->fees[] = ['name' => $name, 'amount' => $amount];
            }
            public function get_subtotal() {
                return 200.0;
            }
            public function get_fees() {
                return [];
            }
            public function get_total($context = 'view') {
                return 200.0;
            }
        };
    }

    public function testFeeIsNotAppliedWhenTheBalanceIsAlreadySpent() {
        global $mock_session, $mock_user_meta;

        $mock_user_meta[8801] = ['intersoccer_points_balance' => 0];
        $mock_session['intersoccer_points_to_redeem'] = 100;

        $cart = $this->cart();
        $this->dashboard->apply_points_discount_as_fee($cart);

        $this->assertSame([], $cart->fees, 'A zero balance must not get the points discount from the session.');
    }

    public function testSecondOrderInTheSameSessionDoesNotGetTheDiscount() {
        global $mock_session, $mock_user_meta;

        $mock_user_meta[8801] = ['intersoccer_points_balance' => 100];
        $mock_session['intersoccer_points_to_redeem'] = 100;

        $order = new WC_Order(88001);
        $order->set_id(88001);
        $order->set_customer_id(8801);
        $order->update_meta_data('_intersoccer_points_redeemed', 100);

        $this->dashboard->debit_points_for_new_order(88001, [], $order);

        $this->assertSame(0, (int) get_user_meta(8801, 'intersoccer_points_balance', true));
        $this->assertSame(0, (int) ($mock_session['intersoccer_points_to_redeem'] ?? 0));
        $this->assertSame(1, (int) $order->get_meta('_intersoccer_credits_deducted_on_completion', true));

        $cart = $this->cart();
        $this->dashboard->apply_points_discount_as_fee($cart);
        $this->assertSame([], $cart->fees);

        $mock_session['intersoccer_points_to_redeem'] = 100;
        $cart_again = $this->cart();
        $this->dashboard->apply_points_discount_as_fee($cart_again);
        $this->assertSame([], $cart_again->fees, 'Putting the old amount back in the session must not discount a second order.');
    }
}
