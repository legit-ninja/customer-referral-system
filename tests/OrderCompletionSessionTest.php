<?php

use PHPUnit\Framework\TestCase;

/**
 * Ensures order completion handlers do not require a WooCommerce frontend session.
 */
class OrderCompletionSessionTest extends TestCase {
    /** @var InterSoccer_Referral_Admin_Dashboard */
    private $dashboard;

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/class-admin-dashboard.php';
        require_once __DIR__ . '/../includes/class-points-manager.php';

        $reflection = new ReflectionClass(InterSoccer_Referral_Admin_Dashboard::class);
        $this->dashboard = $reflection->newInstanceWithoutConstructor();

        global $mock_session, $mock_user_meta, $mock_wc_orders_by_id, $mock_wpdb_get_var_results;
        $mock_session = [];
        $mock_user_meta = [];
        $mock_wc_orders_by_id = [];
        $mock_wpdb_get_var_results = [];
    }

    protected function tearDown(): void {
        global $mock_session, $mock_user_meta, $mock_wc_orders_by_id, $mock_wpdb_get_var_results;
        $mock_session = [];
        $mock_user_meta = [];
        $mock_wc_orders_by_id = [];
        $mock_wpdb_get_var_results = [];

        if (function_exists('WC')) {
            WC()->session = WC::session();
        }
    }

    public function testDeductPointsOnOrderCompletionWithNullSessionDoesNotFatal(): void {
        global $mock_user_meta;

        $order = new WC_Order(53353);
        $order->set_customer_id(501);
        $order->set_total(100);

        $mock_user_meta[501] = [
            'intersoccer_points_balance' => 100,
        ];

        if (function_exists('WC')) {
            WC()->session = null;
        }

        $this->dashboard->deduct_points_on_order_completion(53353, 'on-hold', 'completed', $order);

        $this->assertSame(100, (int) get_user_meta(501, 'intersoccer_points_balance', true));
    }

    public function testDeductPointsFromOrderFeeWhenSessionIsNull(): void {
        global $mock_user_meta, $mock_wpdb_get_var_results;

        $fee = new class {
            public function get_name() {
                return 'Referral Credits Discount';
            }
            public function get_total() {
                return -25.0;
            }
        };

        $order = new class(53354) extends WC_Order {
            private $fee_items = [];

            public function __construct($id) {
                parent::__construct($id);
                $this->set_customer_id(502);
            }

            public function set_fee_items(array $items) {
                $this->fee_items = $items;
            }

            public function get_items($type = '') {
                if ($type === 'fee') {
                    return $this->fee_items;
                }
                return [];
            }

            public function save() {
                return true;
            }
        };
        $order->set_fee_items([99 => $fee]);
        $order->set_total(75);

        $mock_user_meta[502] = [
            'intersoccer_points_balance' => 80,
        ];
        $mock_wpdb_get_var_results['intersoccer_referral_rewards'] = null;

        if (function_exists('WC')) {
            WC()->session = null;
        }

        $this->dashboard->deduct_points_on_order_completion(53354, 'processing', 'completed', $order);

        $this->assertSame(55, (int) get_user_meta(502, 'intersoccer_points_balance', true));
        $this->assertSame(1, (int) $order->get_meta('_intersoccer_credits_deducted_on_completion', true));
        $this->assertSame(25, (int) $order->get_meta('_intersoccer_points_redeemed', true));
    }
}
