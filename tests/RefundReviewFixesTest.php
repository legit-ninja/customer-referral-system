<?php

use PHPUnit\Framework\TestCase;

/**
 * Code-review must-fix regressions for refund / commission reversal.
 */
class RefundReviewFixesTest extends TestCase {

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/intersoccer-refund-helpers.php';
        require_once __DIR__ . '/../includes/class-points-manager.php';
        require_once __DIR__ . '/../includes/class-commission-manager.php';
        require_once __DIR__ . '/../includes/class-cli-redeemed-backfill.php';

        global $mock_user_meta, $mock_wc_orders_by_id, $mock_order_points_allocated, $mock_points_log_rows, $mock_points_balances, $mock_options, $mock_wpdb_get_results, $mock_wpdb_inserts, $mock_wc_get_orders;
        $mock_user_meta = [];
        $mock_wc_orders_by_id = [];
        $mock_order_points_allocated = [];
        $mock_points_log_rows = [];
        $mock_points_balances = [];
        $mock_wpdb_get_results = [];
        $mock_wpdb_inserts = [];
        $mock_wc_get_orders = null;
        $mock_options['intersoccer_points_allocation_mode'] = 'ratio';
        $mock_options['intersoccer_points_rate_customer_purchase'] = 10;
        $mock_options['intersoccer_points_golive_date'] = '';
        $mock_options['intersoccer_proportional_commission_reversal'] = 'yes';
    }

    private function manager(): InterSoccer_Points_Manager {
        return new InterSoccer_Points_Manager();
    }

    private function make_order($id, $customer_id, $total, $status = 'completed') {
        $order = new WC_Order($id);
        $order->set_id($id);
        $order->set_customer_id($customer_id);
        $order->set_total($total);
        $order->set_status($status);
        global $mock_wc_orders_by_id;
        $mock_wc_orders_by_id[$id] = $order;
        return $order;
    }

    private function seed_balance($customer_id, $balance) {
        global $mock_user_meta;
        $mock_user_meta[$customer_id] = ['intersoccer_points_balance' => (int) $balance];
    }

    private function seed_ledger_row(array $row) {
        global $mock_points_log_rows, $mock_points_balances, $mock_order_points_allocated;
        static $next_id = 20000;
        if (!isset($row['id'])) {
            $row['id'] = $next_id++;
        }
        $mock_points_log_rows[] = $row;
        if (isset($row['customer_id'], $row['points_balance'])) {
            $mock_points_balances[(int) $row['customer_id']] = (int) $row['points_balance'];
        }
        if (($row['transaction_type'] ?? '') === 'order_purchase' && isset($row['order_id'])) {
            $mock_order_points_allocated[(int) $row['order_id']] = $row['points_amount'] ?? true;
        }
    }

    private function sum_abs_ledger_for_order($order_id, $type) {
        global $mock_points_log_rows;
        $sum = 0;
        foreach ((array) $mock_points_log_rows as $row) {
            if ((int) ($row['order_id'] ?? 0) !== (int) $order_id) {
                continue;
            }
            if (($row['transaction_type'] ?? '') === $type) {
                $sum += abs((int) ($row['points_amount'] ?? 0));
            }
        }
        return $sum;
    }

    public function testFix1ProportionalFloorReverses29PointsOn29PercentRefund() {
        $points = $this->manager();
        $order = $this->make_order(94001, 9401, 100, 'completed');
        $this->seed_ledger_row([
            'customer_id' => 9401,
            'order_id' => 94001,
            'transaction_type' => 'order_purchase',
            'points_amount' => 100,
            'points_balance' => 100,
        ]);
        $this->seed_balance(9401, 100);

        $refund = new WC_Order_Refund(940011, 29);
        $order->add_refund($refund);
        $points->handle_woocommerce_order_refunded(94001, 940011);

        $this->assertSame(29, $this->sum_abs_ledger_for_order(94001, 'order_refund'));
        $this->assertSame(71, $points->get_points_balance(9401));
    }

    public function testFix2DuplicateRefundIdProcessesPointsReversalOnce() {
        $points = $this->manager();
        $order = $this->make_order(94002, 9402, 100, 'completed');
        $this->seed_ledger_row([
            'customer_id' => 9402,
            'order_id' => 94002,
            'transaction_type' => 'order_purchase',
            'points_amount' => 100,
            'points_balance' => 100,
        ]);
        $this->seed_balance(9402, 100);

        $refund = new WC_Order_Refund(940021, 50);
        $order->add_refund($refund);
        $points->handle_woocommerce_order_refunded(94002, 940021);
        $points->handle_woocommerce_order_refunded(94002, 940021);

        $this->assertSame(50, $this->sum_abs_ledger_for_order(94002, 'order_refund'));
        $this->assertSame(50, $points->get_points_balance(9402));
    }

    public function testFix2RefundLockHeldSkipsSecondSyncCall() {
        $points = $this->manager();
        $order = $this->make_order(94003, 9403, 100, 'completed');
        $this->seed_ledger_row([
            'customer_id' => 9403,
            'order_id' => 94003,
            'transaction_type' => 'order_purchase',
            'points_amount' => 10,
            'points_balance' => 10,
        ]);
        $this->seed_balance(9403, 10);

        update_option(intersoccer_refund_order_lock_option_name(94003), time());

        $points->sync_refund_reversals(94003, 'order_refunded', ['force_fraction_one' => true]);

        $this->assertSame(0, $this->sum_abs_ledger_for_order(94003, 'order_refund'));
        $this->assertSame(10, $points->get_points_balance(9403));
    }

    public function testFix3PreAwardRefundExcludedFromLaterEarnedReversalFraction() {
        $points = $this->manager();
        $order = $this->make_order(94004, 9404, 100, 'completed');

        $pre_refund = new WC_Order_Refund(940041, 30);
        $order->add_refund($pre_refund);
        $order->set_total_refunded(30);
        $this->seed_balance(9404, 0);

        $points->allocate_points_for_order(94004);
        $this->assertSame(7, $points->get_points_balance(9404));

        $later_refund = new WC_Order_Refund(940042, 20);
        $order->add_refund($later_refund);
        $order->set_total_refunded(50);
        $points->handle_woocommerce_order_refunded(94004, 940042);

        $this->assertSame(2, $this->sum_abs_ledger_for_order(94004, 'order_refund'));
        $this->assertSame(5, $points->get_points_balance(9404));
    }

    public function testFix4CommissionWriteOffRecordsFullDeltaAndProtectsLaterCredits() {
        global $mock_wpdb_get_results, $mock_user_meta, $mock_wpdb_inserts;
        $order = $this->make_order(94005, 9405, 100, 'completed');
        $mock_user_meta[94105] = ['intersoccer_credits' => 3.0];
        $mock_wpdb_get_results['intersoccer_referrals'] = [
            (object) [
                'id' => 55,
                'order_id' => 94005,
                'coach_id' => 94105,
                'customer_id' => 9405,
                'commission_amount' => 20.0,
                'loyalty_bonus' => 0,
                'retention_bonus' => 0,
                'status' => 'paid',
            ],
        ];
        $mock_wpdb_get_results['intersoccer_referral_rewards'] = [];
        $mock_wpdb_get_results['intersoccer_coach_commissions'] = [];

        $refund = new WC_Order_Refund(940051, 50);
        $order->add_refund($refund);
        InterSoccer_Commission_Manager::get_instance()->handle_order_refunded_rewards(94005, 940051);

        $this->assertSame(0.0, (float) ($mock_user_meta[94105]['intersoccer_credits'] ?? 0));
        $this->assertSame(10.0, (float) $order->get_meta('_intersoccer_commission_amount_reversed_total', true));

        $writeoffs = array_values(array_filter((array) $mock_wpdb_inserts, static function ($row) {
            return ($row['data']['credit_type'] ?? '') === 'commission_write_off';
        }));
        $this->assertCount(1, $writeoffs);
        $this->assertSame(7.0, (float) ($writeoffs[0]['data']['credit_amount'] ?? 0));

        $mock_user_meta[94105]['intersoccer_credits'] = 50.0;
        InterSoccer_Commission_Manager::get_instance()->handle_order_refunded_rewards(94005, 940051);
        $this->assertSame(50.0, (float) ($mock_user_meta[94105]['intersoccer_credits'] ?? 0));
    }

    public function testFix6FailedThenPaidRetakesRedeemedOnce() {
        $points = $this->manager();
        $order = $this->make_order(94006, 9406, 100, 'failed');
        $order->update_meta_data('_intersoccer_points_redeemed', 30);
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order->save();
        $this->seed_balance(9406, 30);

        $points->handle_order_status_failed(94006);
        $this->assertSame(30, $this->sum_abs_ledger_for_order(94006, 'points_refund'));

        $order->set_status('processing');
        $points->handle_order_status_changed(94006, 'failed', 'processing', $order);
        $this->assertSame(30, $this->sum_abs_ledger_for_order(94006, 'redeemed_retaken'));

        $points->handle_order_status_changed(94006, 'failed', 'processing', $order);
        $this->assertSame(30, $this->sum_abs_ledger_for_order(94006, 'redeemed_retaken'));
    }

    public function testFix8BackfillScansAllPagesOfRefundedOrders() {
        global $mock_wc_get_orders;
        $pages_called = 0;
        $mock_wc_get_orders = static function ($args) use (&$pages_called) {
            $page = isset($args['page']) ? (int) $args['page'] : 1;
            $pages_called++;
            if ($page === 1) {
                $batch = [];
                for ($i = 0; $i < 200; $i++) {
                    $id = 95000 + $i;
                    $o = new WC_Order($id);
                    $o->set_id($id);
                    $o->set_status('refunded');
                    $o->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
                    $o->update_meta_data('_intersoccer_points_redeemed', 5);
                    $o->update_meta_data('_intersoccer_redeemed_points_returned_total', 5);
                    $batch[] = $o;
                }
                return $batch;
            }
            if ($page === 2) {
                $o = new WC_Order(95200);
                $o->set_id(95200);
                $o->set_status('refunded');
                $o->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
                $o->update_meta_data('_intersoccer_points_redeemed', 12);
                $o->update_meta_data('_intersoccer_redeemed_points_returned_total', 0);
                return [$o];
            }
            return [];
        };

        $rows = InterSoccer_CLI_Redeemed_Backfill::compute_redeemed_backfill_rows();
        $this->assertSame(2, $pages_called);
        $this->assertCount(1, $rows);
        $this->assertSame(95200, $rows[0]['order_id']);
        $this->assertSame(12, $rows[0]['owed']);
    }

    public function testFix9PerRowCommissionCapWithTwoReferralRows() {
        global $mock_wpdb_get_results, $mock_user_meta;
        $order = $this->make_order(94009, 9409, 100, 'completed');
        $mock_user_meta[94109] = ['intersoccer_credits' => 100.0];
        $mock_wpdb_get_results['intersoccer_referrals'] = [
            (object) [
                'id' => 91,
                'order_id' => 94009,
                'coach_id' => 94109,
                'customer_id' => 9409,
                'commission_amount' => 10.0,
                'loyalty_bonus' => 0,
                'retention_bonus' => 0,
                'status' => 'paid',
            ],
            (object) [
                'id' => 92,
                'order_id' => 94009,
                'coach_id' => 94109,
                'customer_id' => 9409,
                'commission_amount' => 30.0,
                'loyalty_bonus' => 0,
                'retention_bonus' => 0,
                'status' => 'paid',
            ],
        ];
        $mock_wpdb_get_results['intersoccer_referral_rewards'] = [];
        $mock_wpdb_get_results['intersoccer_coach_commissions'] = [];

        $refund = new WC_Order_Refund(940091, 50);
        $order->add_refund($refund);
        InterSoccer_Commission_Manager::get_instance()->handle_order_refunded_rewards(94009, 940091);

        $this->assertSame(5.0, (float) $order->get_meta('_intersoccer_commission_row_91_reversed_total', true));
        $this->assertSame(15.0, (float) $order->get_meta('_intersoccer_commission_row_92_reversed_total', true));
        $this->assertSame(20.0, (float) $order->get_meta('_intersoccer_commission_amount_reversed_total', true));
        $this->assertSame(80.0, (float) ($mock_user_meta[94109]['intersoccer_credits'] ?? 0));
    }

    public function testFix5CommissionReversalFloorsToCentsNotRounds() {
        global $mock_wpdb_get_results, $mock_user_meta;
        $order = $this->make_order(94010, 9410, 100, 'completed');
        $mock_user_meta[94110] = ['intersoccer_credits' => 100.0];
        $mock_wpdb_get_results['intersoccer_referrals'] = [
            (object) [
                'id' => 93,
                'order_id' => 94010,
                'coach_id' => 94110,
                'customer_id' => 9410,
                'commission_amount' => 10.0,
                'loyalty_bonus' => 0,
                'retention_bonus' => 0,
                'status' => 'paid',
            ],
        ];
        $mock_wpdb_get_results['intersoccer_referral_rewards'] = [];
        $mock_wpdb_get_results['intersoccer_coach_commissions'] = [];

        // 3.37% of 10.00 is 0.337: floored to cents is 0.33, rounding would give 0.34.
        $refund = new WC_Order_Refund(940101, 3.37);
        $order->add_refund($refund);
        InterSoccer_Commission_Manager::get_instance()->handle_order_refunded_rewards(94010, 940101);

        $this->assertSame(0.33, (float) $order->get_meta('_intersoccer_commission_row_93_reversed_total', true));
        $this->assertSame(99.67, round((float) ($mock_user_meta[94110]['intersoccer_credits'] ?? 0), 2));
    }
}
