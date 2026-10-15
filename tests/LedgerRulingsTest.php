<?php

use PHPUnit\Framework\TestCase;

/**
 * Finance (Ledger) rulings on CRS #114.
 */
class LedgerRulingsTest extends TestCase {

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/intersoccer-refund-helpers.php';
        require_once __DIR__ . '/../includes/class-points-manager.php';
        require_once __DIR__ . '/../includes/class-commission-manager.php';

        global $mock_user_meta, $mock_wc_orders_by_id, $mock_points_log_rows, $mock_options, $mock_wpdb_get_results;
        $mock_user_meta = [];
        $mock_wc_orders_by_id = [];
        $mock_points_log_rows = [];
        $mock_wpdb_get_results = [];
        $mock_options['intersoccer_points_rate_customer_referral'] = 10;
        $mock_options['intersoccer_proportional_commission_reversal'] = 'yes';
    }

    private function manager(): InterSoccer_Points_Manager {
        return new InterSoccer_Points_Manager();
    }

    private function make_order($id, $customer_id, $total, $status = 'completed', $tax = 0.0) {
        $order = new WC_Order($id);
        $order->set_id($id);
        $order->set_customer_id($customer_id);
        $order->set_total($total);
        if ($tax > 0) {
            $order->set_tax_total($tax);
        }
        $order->set_status($status);
        if ($status === 'completed') {
            $order->set_date_completed('2025-06-01 12:00:00');
        }
        global $mock_wc_orders_by_id;
        $mock_wc_orders_by_id[$id] = $order;
        return $order;
    }

    private function seed_balance($customer_id, $balance) {
        global $mock_user_meta;
        $mock_user_meta[$customer_id] = ['intersoccer_points_balance' => (int) $balance];
    }

    private function seed_ledger_row(array $row) {
        global $mock_points_log_rows;
        static $next_id = 50000;
        if (!isset($row['id'])) {
            $row['id'] = $next_id++;
        }
        if (!isset($row['metadata'])) {
            $row['metadata'] = isset($row['metadata_array']) ? json_encode($row['metadata_array']) : '';
        }
        unset($row['metadata_array']);
        $mock_points_log_rows[] = $row;
    }

    private function ledger_amounts_for_order($order_id, $type) {
        global $mock_points_log_rows;
        $out = [];
        foreach ((array) $mock_points_log_rows as $row) {
            if ((int) ($row['order_id'] ?? 0) !== (int) $order_id) {
                continue;
            }
            if (($row['transaction_type'] ?? '') !== $type) {
                continue;
            }
            $out[] = (int) ($row['points_amount'] ?? 0);
        }
        return $out;
    }

    public function testLedgerRuling1_ReinstatementRestoresReferrerRewardIdempotently() {
        $order = $this->make_order(95001, 9501, 100, 'processing');
        $order->update_meta_data('_intersoccer_referrer_reward_user_id', 9502);
        $order->save();
        $this->seed_balance(9502, 5);
        $this->seed_ledger_row([
            'order_id' => 95001,
            'customer_id' => 9502,
            'transaction_type' => 'customer_referral_reversal',
            'points_amount' => -5,
            'points_balance' => 0,
        ]);

        $cm = InterSoccer_Commission_Manager::get_instance();
        $cm->sync_reinstatement_paid_rewards_for_order($order);
        $this->assertSame([5], $this->ledger_amounts_for_order(95001, 'customer_referral_reinstated'));

        $cm->sync_reinstatement_paid_rewards_for_order($order);
        $this->assertCount(1, $this->ledger_amounts_for_order(95001, 'customer_referral_reinstated'));
    }

    public function testLedgerRuling2_PreCompletionPartialRefundUsesNetExVatForReferrerReward() {
        $points = $this->manager();
        $order = $this->make_order(95002, 9503, 110, 'processing', 10);
        $order->set_subtotal(100);
        $order->set_date_created('2025-05-01 10:00:00');
        $order->save();

        $refund = new WC_Order_Refund(950021, 55);
        $refund->set_total(-55);
        $refund->set_date_created('2025-05-15 10:00:00');
        $order->add_refund($refund);
        $order->set_date_completed('2025-06-01 12:00:00');
        $order->set_status('completed');
        $order->save();

        $net = $points->get_order_net_rewardable_amount_ex_vat($order);
        $this->assertEqualsWithDelta(50.0, $net, 0.01);

        $reward_points = (int) $points->calculate_points_from_order_total($net, 9504, 'referral', false);
        $this->assertSame(5, $reward_points);
    }

    public function testLedgerRuling3_CorrectionAdjustmentIsClawableGoodwillIsNot() {
        $points = $this->manager();
        $this->seed_balance(9505, 0);
        $this->seed_ledger_row([
            'customer_id' => 9505,
            'order_id' => 0,
            'transaction_type' => 'admin_adjustment',
            'points_amount' => 20,
            'points_balance' => 20,
            'metadata_array' => ['adjustment_reason' => 'correction'],
        ]);
        $this->seed_ledger_row([
            'customer_id' => 9505,
            'order_id' => 0,
            'transaction_type' => 'admin_adjustment',
            'points_amount' => 30,
            'points_balance' => 50,
            'metadata_array' => ['adjustment_reason' => 'goodwill'],
        ]);
        $this->seed_balance(9505, 50);

        $this->assertSame(30, $points->get_unspent_gifted_points(9505));
        $this->assertSame(20, $points->get_attributable_balance_for_order_reversal(9505, 95099));
    }

    public function testLedgerRuling3_UntaggedPositiveAdjustmentRemainsGift() {
        $points = $this->manager();
        $this->seed_ledger_row([
            'customer_id' => 9506,
            'order_id' => 0,
            'transaction_type' => 'admin_adjustment',
            'points_amount' => 15,
            'points_balance' => 15,
            'metadata' => '',
        ]);
        $this->seed_balance(9506, 15);

        $this->assertTrue($points->is_positive_points_lot_gift('admin_adjustment', 15, ''));
        $this->assertSame(15, $points->get_unspent_gifted_points(9506));
    }
}
