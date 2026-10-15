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

        global $mock_user_meta, $mock_wc_orders_by_id, $mock_points_log_rows, $mock_options, $mock_wpdb_get_results, $mock_wpdb_inserts, $mock_order_points_allocated;
        $mock_user_meta = [];
        $mock_wc_orders_by_id = [];
        $mock_points_log_rows = [];
        $mock_wpdb_get_results = [];
        $mock_wpdb_inserts = [];
        $mock_order_points_allocated = [];
        $mock_options['intersoccer_points_rate_customer_referral'] = 10;
        $mock_options['intersoccer_points_rate_customer_purchase'] = 10;
        $mock_options['intersoccer_proportional_commission_reversal'] = 'yes';
        $mock_options['intersoccer_coach_referral_code_commission_rate'] = 10;
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

    private function seed_coach_credits($coach_id, $amount) {
        global $mock_user_meta;
        if (!isset($mock_user_meta[$coach_id]) || !is_array($mock_user_meta[$coach_id])) {
            $mock_user_meta[$coach_id] = [];
        }
        $mock_user_meta[$coach_id]['intersoccer_credits'] = (float) $amount;
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

    private function seed_commission_credit_row($order_id, $referral_id, $coach_id, $credit_type, $amount) {
        global $mock_wpdb_inserts;
        $mock_wpdb_inserts[] = [
            'table' => 'wp_intersoccer_referral_credits',
            'data' => [
                'order_id' => (int) $order_id,
                'referral_id' => (int) $referral_id,
                'coach_id' => (int) $coach_id,
                'customer_id' => 0,
                'credit_type' => (string) $credit_type,
                'credit_amount' => (float) $amount,
            ],
        ];
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

    private function count_ledger_type($order_id, $type) {
        return count($this->ledger_amounts_for_order($order_id, $type));
    }

    private function commission_reinstatement_rows($order_id) {
        global $mock_wpdb_inserts;
        $rows = [];
        foreach ((array) $mock_wpdb_inserts as $insert) {
            $data = $insert['data'] ?? [];
            if ((int) ($data['order_id'] ?? 0) !== (int) $order_id) {
                continue;
            }
            if (($data['credit_type'] ?? '') !== 'commission_reinstatement') {
                continue;
            }
            $rows[] = $data;
        }
        return $rows;
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

    public function testLedgerRuling1_CoachPointsReinstatementRestoresReversedAmount() {
        $order_id = 95110;
        $coach_id = 95111;
        $order = $this->make_order($order_id, 95112, 100, 'processing');
        $order->update_meta_data('_intersoccer_referring_coach_id', $coach_id);
        $order->save();
        $this->seed_balance($coach_id, 20);
        $this->seed_ledger_row([
            'order_id' => $order_id,
            'customer_id' => $coach_id,
            'transaction_type' => 'coach_referral_reversal',
            'points_amount' => -50,
            'points_balance' => 20,
        ]);

        InterSoccer_Commission_Manager::get_instance()->sync_reinstatement_paid_rewards_for_order($order);

        $this->assertSame([50], $this->ledger_amounts_for_order($order_id, 'coach_referral_reinstated'));
        $this->assertSame(70, (int) ($GLOBALS['mock_user_meta'][$coach_id]['intersoccer_points_balance'] ?? 0));
    }

    public function testLedgerRuling1_CommissionReinstatementRestoresCreditsAndBalance() {
        $order_id = 95120;
        $coach_id = 95121;
        $referral_id = 881;
        $order = $this->make_order($order_id, 95122, 100, 'processing');
        $order->update_meta_data('_intersoccer_referring_coach_id', $coach_id);
        $order->save();
        $this->seed_coach_credits($coach_id, 2.50);
        $this->seed_commission_credit_row($order_id, $referral_id, $coach_id, 'commission_reversal', -12.50);
        global $mock_wpdb_get_results;
        $mock_wpdb_get_results['intersoccer_referrals'] = [
            (object) [
                'id' => $referral_id,
                'order_id' => $order_id,
                'coach_id' => $coach_id,
                'customer_id' => 95122,
            ],
        ];

        InterSoccer_Commission_Manager::get_instance()->sync_reinstatement_paid_rewards_for_order($order);

        $reinstate_rows = $this->commission_reinstatement_rows($order_id);
        $this->assertCount(1, $reinstate_rows);
        $this->assertEqualsWithDelta(12.50, (float) $reinstate_rows[0]['credit_amount'], 0.001);
        $this->assertEqualsWithDelta(15.00, (float) $GLOBALS['mock_user_meta'][$coach_id]['intersoccer_credits'], 0.001);
    }

    public function testLedgerRuling1_FullReinstatementTwiceIsIdempotentAcrossFourChannels() {
        $order_id = 95130;
        $customer_id = 95131;
        $referrer_id = 95132;
        $coach_id = 95133;
        $referral_id = 882;

        $order = $this->make_order($order_id, $customer_id, 100, 'processing');
        $order->update_meta_data('_intersoccer_referrer_reward_user_id', $referrer_id);
        $order->update_meta_data('_intersoccer_referring_coach_id', $coach_id);
        $order->save();

        $this->seed_balance($customer_id, 0);
        $this->seed_balance($referrer_id, 1);
        $this->seed_balance($coach_id, 0);
        $this->seed_coach_credits($coach_id, 0.0);

        global $mock_order_points_allocated;
        $mock_order_points_allocated[$order_id] = 10;
        $this->seed_ledger_row([
            'order_id' => $order_id,
            'customer_id' => $customer_id,
            'transaction_type' => 'order_refund',
            'points_amount' => -10,
            'points_balance' => 0,
        ]);
        $this->seed_ledger_row([
            'order_id' => $order_id,
            'customer_id' => $referrer_id,
            'transaction_type' => 'customer_referral_reversal',
            'points_amount' => -4,
            'points_balance' => 1,
        ]);
        $this->seed_ledger_row([
            'order_id' => $order_id,
            'customer_id' => $coach_id,
            'transaction_type' => 'coach_referral_reversal',
            'points_amount' => -50,
            'points_balance' => 0,
        ]);
        $this->seed_commission_credit_row($order_id, $referral_id, $coach_id, 'commission_reversal', -8.00);
        global $mock_wpdb_get_results;
        $mock_wpdb_get_results['intersoccer_referrals'] = [
            (object) [
                'id' => $referral_id,
                'order_id' => $order_id,
                'coach_id' => $coach_id,
                'customer_id' => $customer_id,
            ],
        ];

        $points = $this->manager();
        $points->sync_reinstatement_for_order($order);

        $customer_balance_after_first = (int) $GLOBALS['mock_user_meta'][$customer_id]['intersoccer_points_balance'];
        $referrer_balance_after_first = (int) $GLOBALS['mock_user_meta'][$referrer_id]['intersoccer_points_balance'];
        $coach_points_after_first = (int) $GLOBALS['mock_user_meta'][$coach_id]['intersoccer_points_balance'];
        $coach_credits_after_first = (float) $GLOBALS['mock_user_meta'][$coach_id]['intersoccer_credits'];

        $this->assertSame(10, $customer_balance_after_first);
        $this->assertSame(5, $referrer_balance_after_first);
        $this->assertSame(50, $coach_points_after_first);
        $this->assertEqualsWithDelta(8.00, $coach_credits_after_first, 0.001);
        $this->assertCount(1, $this->ledger_amounts_for_order($order_id, 'reversal_reinstated'));
        $this->assertCount(1, $this->ledger_amounts_for_order($order_id, 'customer_referral_reinstated'));
        $this->assertCount(1, $this->ledger_amounts_for_order($order_id, 'coach_referral_reinstated'));
        $this->assertCount(1, $this->commission_reinstatement_rows($order_id));

        $points->sync_reinstatement_for_order($order);

        $this->assertSame($customer_balance_after_first, (int) $GLOBALS['mock_user_meta'][$customer_id]['intersoccer_points_balance']);
        $this->assertSame($referrer_balance_after_first, (int) $GLOBALS['mock_user_meta'][$referrer_id]['intersoccer_points_balance']);
        $this->assertSame($coach_points_after_first, (int) $GLOBALS['mock_user_meta'][$coach_id]['intersoccer_points_balance']);
        $this->assertEqualsWithDelta($coach_credits_after_first, (float) $GLOBALS['mock_user_meta'][$coach_id]['intersoccer_credits'], 0.001);
        $this->assertSame(1, $this->count_ledger_type($order_id, 'reversal_reinstated'));
        $this->assertSame(1, $this->count_ledger_type($order_id, 'customer_referral_reinstated'));
        $this->assertSame(1, $this->count_ledger_type($order_id, 'coach_referral_reinstated'));
        $this->assertCount(1, $this->commission_reinstatement_rows($order_id));
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

    public function testLedgerRuling2_CommissionBaseUsesNetPaidExVatAfterPreCompletionRefund() {
        $order = $this->make_order(95140, 95141, 110, 'processing', 10);
        $order->set_subtotal(100);
        $order->set_shipping_total(0);
        $order->set_date_created('2025-05-01 10:00:00');
        $order->save();

        $refund = new WC_Order_Refund(951401, 55);
        $refund->set_total(-55);
        $refund->set_date_created('2025-05-20 10:00:00');
        $order->add_refund($refund);
        $order->set_date_completed('2025-06-01 12:00:00');
        $order->save();

        $rewardable = InterSoccer_Commission_Manager::get_rewardable_commissionable_amount($order);
        $this->assertEqualsWithDelta(50.0, $rewardable, 0.01);

        $commission = InterSoccer_Commission_Manager::calculate_referral_code_commission($order, 95142);
        $this->assertEqualsWithDelta(5.0, $commission, 0.01);
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

    public function testLedgerRuling3_RefundReversalClawsCorrectionOnlyExactAmounts() {
        $customer_id = 95150;
        $order_id = 95151;
        $points = $this->manager();

        $order = $this->make_order($order_id, $customer_id, 100, 'completed');
        $this->seed_balance($customer_id, 0);
        $this->seed_ledger_row([
            'customer_id' => $customer_id,
            'order_id' => 0,
            'transaction_type' => 'admin_adjustment',
            'points_amount' => 12,
            'points_balance' => 12,
            'metadata_array' => ['adjustment_reason' => 'correction'],
            'created_at' => '2025-01-01 10:00:00',
        ]);
        $this->seed_ledger_row([
            'customer_id' => $customer_id,
            'order_id' => 0,
            'transaction_type' => 'admin_adjustment',
            'points_amount' => 28,
            'points_balance' => 40,
            'metadata_array' => ['adjustment_reason' => 'goodwill'],
            'created_at' => '2025-01-02 10:00:00',
        ]);
        $this->seed_balance($customer_id, 40);
        global $mock_order_points_allocated;
        $mock_order_points_allocated[$order_id] = 15;
        $this->seed_ledger_row([
            'customer_id' => $customer_id,
            'order_id' => $order_id,
            'transaction_type' => 'order_purchase',
            'points_amount' => 15,
            'points_balance' => 55,
            'created_at' => '2025-02-01 10:00:00',
        ]);
        $this->seed_balance($customer_id, 55);

        $order->set_status('refunded');
        $points->handle_order_status_refunded($order_id);

        // 12 correction + 3 purchase debited; 28 goodwill FIFO-protected.
        $this->assertSame(40, $points->get_points_balance($customer_id));
        $this->assertSame(28, $points->get_unspent_gifted_points($customer_id));
        $this->assertSame([-15], $this->ledger_amounts_for_order($order_id, 'order_refund'));
        $this->assertSame(0, $this->count_ledger_type($order_id, 'reversal_write_off'));
    }
}
