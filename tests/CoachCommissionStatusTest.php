<?php

use PHPUnit\Framework\TestCase;

/**
 * Coach commission payout rows must flip to reversed on full reversal and back on reinstatement.
 */
class CoachCommissionStatusTest extends TestCase {

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/intersoccer-refund-helpers.php';
        require_once __DIR__ . '/../includes/class-points-manager.php';
        require_once __DIR__ . '/../includes/class-commission-manager.php';
        parent::setUp();
        global $mock_wpdb_updates, $mock_wpdb_inserts, $mock_wpdb_get_results, $mock_user_meta, $mock_wc_orders_by_id;
        $mock_wpdb_updates = [];
        $mock_wpdb_inserts = [];
        $mock_wpdb_get_results = [];
        $mock_user_meta = [];
        $mock_wc_orders_by_id = [];
    }

    private function make_order($order_id, $customer_id, $total, $status = 'completed') {
        $order = new WC_Order($order_id);
        $order->set_id($order_id);
        $order->set_customer_id($customer_id);
        $order->set_total($total);
        $order->set_status($status);
        global $mock_wc_orders_by_id;
        $mock_wc_orders_by_id[$order_id] = $order;
        return $order;
    }

    public function testFullReversalMarksCoachCommissionRowsReversed() {
        global $mock_wpdb_get_results, $mock_wpdb_updates, $mock_user_meta;

        $order_id = 96001;
        $coach_id = 96101;
        $mock_user_meta[$coach_id] = ['intersoccer_credits' => 50.0];
        $mock_wpdb_get_results['intersoccer_referrals'] = [
            (object) [
                'id' => 501,
                'order_id' => $order_id,
                'coach_id' => $coach_id,
                'customer_id' => 96011,
                'commission_amount' => 20.0,
                'loyalty_bonus' => 0,
                'retention_bonus' => 0,
                'status' => 'paid',
            ],
        ];
        $mock_wpdb_get_results['intersoccer_referral_rewards'] = [];
        $mock_wpdb_get_results['intersoccer_coach_commissions'] = [
            (object) ['id' => 801, 'order_id' => $order_id, 'status' => 'approved'],
        ];

        $order = $this->make_order($order_id, 96011, 100, 'cancelled');
        InterSoccer_Commission_Manager::get_instance()->reverse_paid_rewards($order_id);

        $status_updates = array_values(array_filter((array) $mock_wpdb_updates, static function ($row) {
            return strpos((string) ($row['table'] ?? ''), 'intersoccer_coach_commissions') !== false
                && ($row['data']['status'] ?? '') === 'reversed';
        }));
        $this->assertCount(1, $status_updates);
    }

    public function testReinstatementRestoresCoachCommissionRowsToApproved() {
        global $mock_wpdb_get_results, $mock_wpdb_updates, $mock_user_meta;

        $order_id = 96002;
        $coach_id = 96102;
        $mock_user_meta[$coach_id] = ['intersoccer_credits' => 10.0];
        $mock_wpdb_get_results['intersoccer_referral_rewards'] = [
            (object) ['coach_id' => $coach_id, 'order_id' => $order_id, 'points_awarded' => 0],
        ];
        $mock_wpdb_get_results['intersoccer_referrals'] = [
            (object) [
                'id' => 502,
                'order_id' => $order_id,
                'coach_id' => $coach_id,
                'customer_id' => 96012,
                'commission_amount' => 15.0,
                'status' => 'paid',
            ],
        ];
        $mock_wpdb_get_results['intersoccer_coach_commissions'] = [
            (object) ['id' => 802, 'order_id' => $order_id, 'status' => 'reversed'],
        ];

        global $mock_wpdb_inserts;
        $mock_wpdb_inserts[] = [
            'table' => 'wp_intersoccer_referral_credits',
            'data' => [
                'order_id' => $order_id,
                'credit_type' => 'commission_reversal',
                'credit_amount' => -15.0,
            ],
        ];

        $order = $this->make_order($order_id, 96012, 100, 'processing');
        InterSoccer_Commission_Manager::get_instance()->sync_reinstatement_paid_rewards_for_order($order);

        $restored = array_values(array_filter((array) $mock_wpdb_updates, static function ($row) {
            return strpos((string) ($row['table'] ?? ''), 'intersoccer_coach_commissions') !== false
                && ($row['data']['status'] ?? '') === 'approved';
        }));
        $this->assertCount(1, $restored);
    }
}
