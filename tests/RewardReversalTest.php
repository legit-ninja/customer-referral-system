<?php

use PHPUnit\Framework\TestCase;

/**
 * A full refund or a cancel reverses paid coach commission, coach first-order
 * points, and the customer referral reward. Partial refunds are not reversed
 * proportionally, and duplicate reward rows are not deleted.
 */
class RewardReversalTest extends TestCase {

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/class-points-manager.php';
        require_once __DIR__ . '/../includes/class-commission-manager.php';

        global $mock_user_meta, $mock_points_balance_rows, $mock_wc_orders_by_id, $mock_wpdb_get_results, $mock_wpdb_updates, $mock_wpdb_inserts, $mock_wpdb_deletes, $mock_referral_reward_rows, $mock_points_log_rows;
        $mock_user_meta = [];
        $mock_points_balance_rows = [];
        $mock_wc_orders_by_id = [];
        $mock_wpdb_get_results = [];
        $mock_wpdb_updates = [];
        $mock_wpdb_inserts = [];
        $mock_wpdb_deletes = [];
        $mock_referral_reward_rows = [];
        $mock_points_log_rows = [];
    }

    private function paid_order($id, $status) {
        $order = new WC_Order($id);
        $order->set_id($id);
        $order->set_customer_id(9100);
        $order->set_status($status);
        $order->update_meta_data('_intersoccer_referrer_reward_points', 8);
        $order->update_meta_data('_intersoccer_referrer_reward_user_id', 9102);
        global $mock_wc_orders_by_id;
        $mock_wc_orders_by_id[$id] = $order;
        return $order;
    }

    private function seed_paid_rows($order_id) {
        global $mock_wpdb_get_results, $mock_user_meta, $mock_referral_reward_rows;

        $mock_user_meta[9101] = [
            'intersoccer_credits' => 40,
            'intersoccer_points_balance' => 20,
        ];
        $mock_user_meta[9102] = [
            'intersoccer_points_balance' => 30,
        ];

        $referral_rows = [
            (object) [
                'id' => 11,
                'coach_id' => 9101,
                'customer_id' => 9100,
                'order_id' => $order_id,
                'status' => 'completed',
                'commission_amount' => 25,
                'loyalty_bonus' => 0,
                'retention_bonus' => 5,
                'referrer_type' => 'coach',
            ],
            (object) [
                'id' => 12,
                'coach_id' => 0,
                'customer_id' => 9100,
                'order_id' => $order_id,
                'status' => 'completed',
                'commission_amount' => 0,
                'loyalty_bonus' => 0,
                'retention_bonus' => 0,
                'referrer_type' => 'customer',
                'referrer_id' => 9102,
            ],
        ];
        $reward_rows = [
            (object) [
                'id' => 21,
                'coach_id' => 9101,
                'order_id' => $order_id,
                'points_awarded' => 10,
                'status' => 'paid',
            ],
            (object) [
                'id' => 22,
                'coach_id' => 9101,
                'order_id' => $order_id,
                'points_awarded' => 10,
                'status' => 'paid',
            ],
        ];
        $commission_rows = [
            (object) [
                'id' => 31,
                'coach_id' => 9101,
                'order_id' => $order_id,
                'status' => 'approved',
                'commission_amount' => 30,
            ],
        ];

        $mock_wpdb_get_results['intersoccer_referrals'] = $referral_rows;
        $mock_wpdb_get_results['intersoccer_referral_rewards'] = $reward_rows;
        $mock_wpdb_get_results['intersoccer_coach_commissions'] = $commission_rows;
        $mock_referral_reward_rows = $reward_rows;
    }

    public function testFullRefundReversesPaidRewardsAndKeepsDuplicateRows() {
        global $mock_wpdb_updates, $mock_wpdb_inserts, $mock_wpdb_deletes, $mock_referral_reward_rows;

        $this->paid_order(91001, 'refunded');
        $this->seed_paid_rows(91001);
        $before = count($mock_referral_reward_rows);

        $manager = InterSoccer_Commission_Manager::get_instance();
        $manager->reverse_paid_rewards(91001);
        $manager->reverse_paid_rewards(91001);

        $this->assertSame(10.0, (float) get_user_meta(9101, 'intersoccer_credits', true));
        $this->assertSame(0, (int) get_user_meta(9101, 'intersoccer_points_balance', true));
        $this->assertSame(22, (int) get_user_meta(9102, 'intersoccer_points_balance', true));
        $this->assertSame($before, count($mock_referral_reward_rows));

        $reversed_tables = [];
        foreach ($mock_wpdb_updates as $update) {
            if (($update['data']['status'] ?? '') === 'reversed') {
                $reversed_tables[] = $update['table'];
            }
        }
        $this->assertContains('wp_intersoccer_referrals', $reversed_tables);
        $this->assertContains('wp_intersoccer_coach_commissions', $reversed_tables);
        $this->assertContains('wp_intersoccer_referral_rewards', $reversed_tables);

        $negative_credits = 0;
        foreach ($mock_wpdb_inserts as $insert) {
            if (strpos($insert['table'], 'intersoccer_referral_credits') !== false && (float) $insert['data']['credit_amount'] < 0) {
                $negative_credits++;
            }
        }
        $this->assertSame(1, $negative_credits);

        foreach ((array) $mock_wpdb_deletes as $delete) {
            $this->assertStringNotContainsString('intersoccer_referral_rewards', $delete['table']);
        }
    }

    public function testCancelReversesTheSamePaidRewards() {
        $this->paid_order(91002, 'cancelled');
        $this->seed_paid_rows(91002);

        InterSoccer_Commission_Manager::get_instance()->reverse_paid_rewards(91002);

        $this->assertSame(10.0, (float) get_user_meta(9101, 'intersoccer_credits', true));
        $this->assertSame(0, (int) get_user_meta(9101, 'intersoccer_points_balance', true));
        $this->assertSame(22, (int) get_user_meta(9102, 'intersoccer_points_balance', true));
    }

    public function testPartialRefundDoesNotReverseRewardsProportionally() {
        global $mock_wpdb_updates, $mock_wpdb_inserts;

        $this->paid_order(91003, 'completed');
        $this->seed_paid_rows(91003);
        $mock_wpdb_updates = [];
        $mock_wpdb_inserts = [];

        InterSoccer_Commission_Manager::get_instance()->reverse_paid_rewards(91003);

        $this->assertSame(40.0, (float) get_user_meta(9101, 'intersoccer_credits', true));
        $this->assertSame(20, (int) get_user_meta(9101, 'intersoccer_points_balance', true));
        $this->assertSame(30, (int) get_user_meta(9102, 'intersoccer_points_balance', true));
        $this->assertSame([], $mock_wpdb_updates);
    }
}
