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

        global $mock_user_meta, $mock_wc_orders_by_id, $mock_order_points_allocated, $mock_points_log_rows, $mock_points_balances, $mock_options, $mock_wpdb_get_results, $mock_wpdb_inserts, $mock_wc_get_orders, $mock_scheduled_single_events, $mock_object_cache, $mock_coach_credits_over_debit_drain_balance, $mock_fail_wpdb_insert_tables;
        $mock_user_meta = [];
        $mock_wc_orders_by_id = [];
        $mock_order_points_allocated = [];
        $mock_points_log_rows = [];
        $mock_points_balances = [];
        $mock_wpdb_get_results = [];
        $mock_wpdb_inserts = [];
        $mock_wc_get_orders = null;
        $mock_scheduled_single_events = [];
        $mock_object_cache = [];
        $mock_coach_credits_over_debit_drain_balance = [];
        $mock_fail_wpdb_insert_tables = [];
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

    public function testFix2RefundLockHeldSchedulesSingleRetry() {
        global $mock_scheduled_single_events;
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

        $points->sync_refund_reversals(94003, 'order_refunded', [
            'force_fraction_one' => true,
            'refund_id' => 940031,
        ]);

        $this->assertSame(0, $this->sum_abs_ledger_for_order(94003, 'order_refund'));
        $this->assertCount(1, $mock_scheduled_single_events);
        $this->assertSame(intersoccer_refund_sync_retry_hook(), $mock_scheduled_single_events[0]['hook']);
        $this->assertSame(94003, (int) ($mock_scheduled_single_events[0]['args'][0] ?? 0));
        $this->assertSame('points', (string) ($mock_scheduled_single_events[0]['args'][1] ?? ''));
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
        InterSoccer_Commission_Manager::get_instance()->handle_order_refunded_rewards(94005, 940052);
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

    public function testRetakeShortfallThenRefundDoesNotDoubleRedeemedReturn() {
        $points = $this->manager();
        $order = $this->make_order(94030, 9430, 100, 'failed');
        $order->update_meta_data('_intersoccer_points_redeemed', 30);
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order->save();
        $this->seed_balance(9430, 30);

        $points->handle_order_status_failed(94030);
        $this->assertSame(30, $this->sum_abs_ledger_for_order(94030, 'points_refund'));

        $this->seed_balance(9430, 0);

        $order->set_status('processing');
        $points->handle_order_status_changed(94030, 'failed', 'processing', $order);
        $this->assertSame(30, (int) $order->get_meta('_intersoccer_redeemed_points_returned_total', true));

        $refund = new WC_Order_Refund(940301, 100);
        $order->add_refund($refund);
        $order->set_status('refunded');
        $points->handle_woocommerce_order_refunded(94030, 940301);

        $this->assertSame(30, $this->sum_abs_ledger_for_order(94030, 'points_refund'));
    }

    public function testReinstatementRetakeShortfallThenRefundDoesNotDoubleRedeemedReturn() {
        $points = $this->manager();
        $order = $this->make_order(94031, 9431, 100, 'completed');
        $order->update_meta_data('_intersoccer_points_redeemed', 30);
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order->save();
        $this->seed_balance(9431, 0);

        global $mock_wc_orders_by_id;
        $mock_wc_orders_by_id[94031] = $order;
        $order->set_status('cancelled');
        $points->refund_points_on_cancellation(94031);
        $this->assertSame(30, $this->sum_abs_ledger_for_order(94031, 'points_refund'));
        $this->seed_balance(9431, 0);
        global $mock_points_balances;
        $mock_points_balances[9431] = 0;

        $order->set_status('processing');
        $points->handle_order_status_changed(94031, 'cancelled', 'processing', $order);
        $this->assertSame(30, (int) $order->get_meta('_intersoccer_redeemed_points_returned_total', true));

        $refund = new WC_Order_Refund(940311, 100);
        $order->add_refund($refund);
        $order->set_status('refunded');
        $points->handle_woocommerce_order_refunded(94031, 940311);

        $this->assertSame(30, $this->sum_abs_ledger_for_order(94031, 'points_refund'));
    }

    public function testFailedThenPaidThenRefundedReturnsRedeemedPointsAgain() {
        $points = $this->manager();
        $order = $this->make_order(94020, 9420, 100, 'failed');
        $order->update_meta_data('_intersoccer_points_redeemed', 30);
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order->save();
        $this->seed_balance(9420, 30);

        $points->handle_order_status_failed(94020);
        $this->assertSame(30, $this->sum_abs_ledger_for_order(94020, 'points_refund'));

        $order->set_status('processing');
        $points->handle_order_status_changed(94020, 'failed', 'processing', $order);
        $this->assertSame(30, $this->sum_abs_ledger_for_order(94020, 'redeemed_retaken'));
        $this->assertSame(0, (int) $order->get_meta('_intersoccer_redeemed_points_returned_total', true));

        $refund = new WC_Order_Refund(940201, 100);
        $order->add_refund($refund);
        $order->set_status('refunded');
        $points->handle_woocommerce_order_refunded(94020, 940201);

        $this->assertSame(60, $this->sum_abs_ledger_for_order(94020, 'points_refund'));
    }

    public function testCommissionBusyLockSchedulesSingleRetry() {
        global $mock_scheduled_single_events;
        $order = $this->make_order(94021, 9421, 100, 'completed');
        update_option(intersoccer_refund_order_lock_option_name(94021), time());

        InterSoccer_Commission_Manager::get_instance()->handle_order_refunded_rewards(94021, 940211);

        $this->assertCount(1, $mock_scheduled_single_events);
        $this->assertSame('commission', (string) ($mock_scheduled_single_events[0]['args'][1] ?? ''));
    }

    public function testCoachCreditsTwoOrdersRespectSharedBalanceAndCreditRows() {
        global $mock_wpdb_get_results, $mock_user_meta, $mock_wpdb_inserts;
        $mock_user_meta[94150] = ['intersoccer_credits' => 3.0];

        $order_a = $this->make_order(94022, 9422, 100, 'completed');
        $mock_wpdb_get_results['intersoccer_referrals'] = [
            (object) [
                'id' => 150,
                'order_id' => 94022,
                'coach_id' => 94150,
                'customer_id' => 9422,
                'commission_amount' => 20.0,
                'loyalty_bonus' => 0,
                'retention_bonus' => 0,
                'status' => 'paid',
            ],
        ];
        $mock_wpdb_get_results['intersoccer_referral_rewards'] = [];
        $mock_wpdb_get_results['intersoccer_coach_commissions'] = [];

        $refund_a = new WC_Order_Refund(940221, 50);
        $order_a->add_refund($refund_a);
        InterSoccer_Commission_Manager::get_instance()->handle_order_refunded_rewards(94022, 940221);

        $order_b = $this->make_order(94023, 9423, 100, 'completed');
        $mock_wpdb_get_results['intersoccer_referrals'] = [
            (object) [
                'id' => 151,
                'order_id' => 94023,
                'coach_id' => 94150,
                'customer_id' => 9423,
                'commission_amount' => 20.0,
                'loyalty_bonus' => 0,
                'retention_bonus' => 0,
                'status' => 'paid',
            ],
        ];
        $refund_b = new WC_Order_Refund(940231, 50);
        $order_b->add_refund($refund_b);
        InterSoccer_Commission_Manager::get_instance()->handle_order_refunded_rewards(94023, 940231);

        $this->assertSame(0.0, (float) ($mock_user_meta[94150]['intersoccer_credits'] ?? 0));

        $reversals = array_values(array_filter((array) $mock_wpdb_inserts, static function ($row) {
            return ($row['data']['credit_type'] ?? '') === 'commission_reversal';
        }));
        $writeoffs = array_values(array_filter((array) $mock_wpdb_inserts, static function ($row) {
            return ($row['data']['credit_type'] ?? '') === 'commission_write_off';
        }));
        $this->assertCount(1, $reversals);
        $this->assertCount(2, $writeoffs);
        $this->assertSame(3.0, abs((float) ($reversals[0]['data']['credit_amount'] ?? 0)));
        $this->assertSame(7.0, (float) ($writeoffs[0]['data']['credit_amount'] ?? 0));
        $this->assertSame(10.0, (float) ($writeoffs[1]['data']['credit_amount'] ?? 0));
    }

    public function testCommissionReplayUsesCreditRowsWhenOrderMetaLost() {
        global $mock_wpdb_get_results, $mock_user_meta, $mock_wpdb_inserts;
        $order = $this->make_order(94024, 9424, 100, 'completed');
        $mock_user_meta[94151] = ['intersoccer_credits' => 3.0];
        $mock_wpdb_get_results['intersoccer_referrals'] = [
            (object) [
                'id' => 152,
                'order_id' => 94024,
                'coach_id' => 94151,
                'customer_id' => 9424,
                'commission_amount' => 20.0,
                'loyalty_bonus' => 0,
                'retention_bonus' => 0,
                'status' => 'paid',
            ],
        ];
        $mock_wpdb_get_results['intersoccer_referral_rewards'] = [];
        $mock_wpdb_get_results['intersoccer_coach_commissions'] = [];

        $refund = new WC_Order_Refund(940241, 50);
        $order->add_refund($refund);
        $manager = InterSoccer_Commission_Manager::get_instance();
        $manager->handle_order_refunded_rewards(94024, 940241);

        $inserts_after_first = count((array) $mock_wpdb_inserts);
        $order->update_meta_data('_intersoccer_commission_row_152_reversed_total', 0);
        $order->update_meta_data('_intersoccer_commission_refund_processed_ids', []);
        $order->save();

        $manager->handle_order_refunded_rewards(94024, 940242);
        $this->assertSame($inserts_after_first, count((array) $mock_wpdb_inserts));
    }

    public function testPointsRefundSyncRetryAppliesAfterLockReleased() {
        global $mock_scheduled_single_events;
        $points = $this->manager();
        $order = $this->make_order(94032, 9432, 100, 'completed');
        $this->seed_ledger_row([
            'customer_id' => 9432,
            'order_id' => 94032,
            'transaction_type' => 'order_purchase',
            'points_amount' => 10,
            'points_balance' => 10,
        ]);
        $this->seed_balance(9432, 10);

        $refund_a = new WC_Order_Refund(940321, 50);
        $order->add_refund($refund_a);
        update_option(intersoccer_refund_order_lock_option_name(94032), time());
        $points->handle_woocommerce_order_refunded(94032, 940321);
        $this->assertSame(0, $this->sum_abs_ledger_for_order(94032, 'order_refund'));
        $this->assertCount(1, $mock_scheduled_single_events);

        delete_option(intersoccer_refund_order_lock_option_name(94032));
        $payload = $mock_scheduled_single_events[0]['args'][2] ?? [];
        $points->handle_order_refund_sync_retry(94032, 'points', $payload);
        $this->assertSame(5, $this->sum_abs_ledger_for_order(94032, 'order_refund'));

        $refund_b = new WC_Order_Refund(940322, 50);
        $order->add_refund($refund_b);
        $order->set_total_refunded(100);
        $points->handle_woocommerce_order_refunded(94032, 940322);
        $this->assertSame(10, $this->sum_abs_ledger_for_order(94032, 'order_refund'));
    }

    public function testCommissionRefundSyncRetryAppliesAfterLockReleased() {
        global $mock_scheduled_single_events, $mock_wpdb_get_results, $mock_user_meta;
        $order = $this->make_order(94033, 9433, 100, 'completed');
        $mock_user_meta[94160] = ['intersoccer_credits' => 20.0];
        $mock_wpdb_get_results['intersoccer_referrals'] = [
            (object) [
                'id' => 160,
                'order_id' => 94033,
                'coach_id' => 94160,
                'customer_id' => 9433,
                'commission_amount' => 20.0,
                'loyalty_bonus' => 0,
                'retention_bonus' => 0,
                'status' => 'paid',
            ],
        ];
        $mock_wpdb_get_results['intersoccer_referral_rewards'] = [];
        $mock_wpdb_get_results['intersoccer_coach_commissions'] = [];

        $refund_a = new WC_Order_Refund(940331, 50);
        $order->add_refund($refund_a);
        update_option(intersoccer_refund_order_lock_option_name(94033), time());
        InterSoccer_Commission_Manager::get_instance()->handle_order_refunded_rewards(94033, 940331);
        $this->assertCount(1, $mock_scheduled_single_events);

        delete_option(intersoccer_refund_order_lock_option_name(94033));
        $payload = $mock_scheduled_single_events[0]['args'][2] ?? [];
        InterSoccer_Commission_Manager::get_instance()->handle_order_refund_sync_retry(94033, 'commission', $payload);
        $this->assertSame(10.0, (float) $order->get_meta('_intersoccer_commission_row_160_reversed_total', true));

        $refund_b = new WC_Order_Refund(940332, 50);
        $order->add_refund($refund_b);
        InterSoccer_Commission_Manager::get_instance()->handle_order_refunded_rewards(94033, 940332);
        $this->assertSame(20.0, (float) $order->get_meta('_intersoccer_commission_row_160_reversed_total', true));
    }

    public function testUnpaidTimeoutPaidThenRefundedDoesNotDoubleRedeemedReturn() {
        $points = $this->manager();
        $order = $this->make_order(94034, 9434, 100, 'pending');
        $order->update_meta_data('_intersoccer_points_redeemed', 30);
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order->update_meta_data('_intersoccer_unpaid_redeemed_released', 1);
        $order->update_meta_data('_intersoccer_redeemed_points_returned_total', 30);
        $order->update_meta_data('_intersoccer_redeemed_points_returned', 1);
        $order->save();
        $this->seed_balance(9434, 0);

        $this->seed_ledger_row([
            'customer_id' => 9434,
            'order_id' => 94034,
            'transaction_type' => 'points_refund',
            'points_amount' => 30,
            'points_balance' => 30,
        ]);

        $order->set_status('processing');
        $points->handle_order_status_changed(94034, 'pending', 'processing', $order);
        $this->assertSame(30, (int) $order->get_meta('_intersoccer_redeemed_points_returned_total', true));

        $refund = new WC_Order_Refund(940341, 100);
        $order->add_refund($refund);
        $order->set_status('refunded');
        $points->handle_woocommerce_order_refunded(94034, 940341);

        $this->assertSame(30, $this->sum_abs_ledger_for_order(94034, 'points_refund'));
    }

    public function testCoachCreditLockAndFreshReadPreventDoubleClawAcrossOrders() {
        global $mock_wpdb_get_results, $mock_user_meta, $mock_wpdb_inserts;
        $mock_user_meta[94170] = ['intersoccer_credits' => 5.0];

        $order_a = $this->make_order(94035, 9435, 100, 'completed');
        $mock_wpdb_get_results['intersoccer_referrals'] = [
            (object) [
                'id' => 170,
                'order_id' => 94035,
                'coach_id' => 94170,
                'customer_id' => 9435,
                'commission_amount' => 20.0,
                'loyalty_bonus' => 0,
                'retention_bonus' => 0,
                'status' => 'paid',
            ],
        ];
        $mock_wpdb_get_results['intersoccer_referral_rewards'] = [];
        $mock_wpdb_get_results['intersoccer_coach_commissions'] = [];
        $refund_a = new WC_Order_Refund(940351, 50);
        $order_a->add_refund($refund_a);
        InterSoccer_Commission_Manager::get_instance()->handle_order_refunded_rewards(94035, 940351);

        wp_cache_set(94170, 5.0, 'user_meta');

        $order_b = $this->make_order(94036, 9436, 100, 'completed');
        $mock_wpdb_get_results['intersoccer_referrals'] = [
            (object) [
                'id' => 171,
                'order_id' => 94036,
                'coach_id' => 94170,
                'customer_id' => 9436,
                'commission_amount' => 20.0,
                'loyalty_bonus' => 0,
                'retention_bonus' => 0,
                'status' => 'paid',
            ],
        ];
        $refund_b = new WC_Order_Refund(940361, 50);
        $order_b->add_refund($refund_b);
        InterSoccer_Commission_Manager::get_instance()->handle_order_refunded_rewards(94036, 940361);

        $reversals = array_values(array_filter((array) $mock_wpdb_inserts, static function ($row) {
            return ($row['data']['credit_type'] ?? '') === 'commission_reversal';
        }));
        $claw_total = 0.0;
        foreach ($reversals as $row) {
            $claw_total += abs((float) ($row['data']['credit_amount'] ?? 0));
        }
        $this->assertSame(5.0, $claw_total);
        $this->assertSame(0.0, (float) ($mock_user_meta[94170]['intersoccer_credits'] ?? 0));
    }

    public function testCoachBusyCreditLockSchedulesRetryForSecondRefund() {
        global $mock_scheduled_single_events, $mock_wpdb_get_results, $mock_user_meta;
        $mock_user_meta[94171] = ['intersoccer_credits' => 20.0];
        $order = $this->make_order(94037, 9437, 100, 'completed');
        $mock_wpdb_get_results['intersoccer_referrals'] = [
            (object) [
                'id' => 172,
                'order_id' => 94037,
                'coach_id' => 94171,
                'customer_id' => 9437,
                'commission_amount' => 20.0,
                'loyalty_bonus' => 0,
                'retention_bonus' => 0,
                'status' => 'paid',
            ],
        ];
        $mock_wpdb_get_results['intersoccer_referral_rewards'] = [];
        $mock_wpdb_get_results['intersoccer_coach_commissions'] = [];

        update_option(intersoccer_coach_credit_lock_option_name(94171), time());
        $refund = new WC_Order_Refund(940371, 50);
        $order->add_refund($refund);
        InterSoccer_Commission_Manager::get_instance()->handle_order_refunded_rewards(94037, 940371);
        $this->assertCount(1, $mock_scheduled_single_events);
    }

    public function testCoachClawDeltaCapPreventsOverRecordedReversal() {
        global $mock_wpdb_get_results, $mock_user_meta, $mock_wpdb_inserts, $mock_coach_credits_over_debit_drain_balance;
        $mock_user_meta[94172] = ['intersoccer_credits' => 10.0];
        $mock_coach_credits_over_debit_drain_balance[94172] = true;
        $order = $this->make_order(94039, 9439, 100, 'completed');
        $mock_wpdb_get_results['intersoccer_referrals'] = [
            (object) [
                'id' => 174,
                'order_id' => 94039,
                'coach_id' => 94172,
                'customer_id' => 9439,
                'commission_amount' => 30.0,
                'loyalty_bonus' => 0,
                'retention_bonus' => 0,
                'status' => 'paid',
            ],
        ];
        $mock_wpdb_get_results['intersoccer_referral_rewards'] = [];
        $mock_wpdb_get_results['intersoccer_coach_commissions'] = [];
        $refund = new WC_Order_Refund(940391, 10);
        $order->add_refund($refund);
        InterSoccer_Commission_Manager::get_instance()->handle_order_refunded_rewards(94039, 940391);

        $reversals = array_values(array_filter((array) $mock_wpdb_inserts, static function ($row) {
            return ($row['data']['credit_type'] ?? '') === 'commission_reversal';
        }));
        $this->assertCount(1, $reversals);
        $this->assertSame(3.0, abs((float) ($reversals[0]['data']['credit_amount'] ?? 0)));
    }

    public function testCoachFreshBalanceReadIgnoresStaleObjectCache() {
        global $mock_wpdb_get_results, $mock_user_meta, $mock_wpdb_inserts;
        $mock_user_meta[94174] = ['intersoccer_credits' => 3.0];
        wp_cache_set(94174, 10.0, 'user_meta');
        $order = $this->make_order(94042, 9442, 100, 'completed');
        $mock_wpdb_get_results['intersoccer_referrals'] = [
            (object) [
                'id' => 176,
                'order_id' => 94042,
                'coach_id' => 94174,
                'customer_id' => 9442,
                'commission_amount' => 20.0,
                'loyalty_bonus' => 0,
                'retention_bonus' => 0,
                'status' => 'paid',
            ],
        ];
        $mock_wpdb_get_results['intersoccer_referral_rewards'] = [];
        $mock_wpdb_get_results['intersoccer_coach_commissions'] = [];
        $refund = new WC_Order_Refund(940421, 50);
        $order->add_refund($refund);
        InterSoccer_Commission_Manager::get_instance()->handle_order_refunded_rewards(94042, 940421);
        $reversals = array_values(array_filter((array) $mock_wpdb_inserts, static function ($row) {
            return ($row['data']['credit_type'] ?? '') === 'commission_reversal';
        }));
        $this->assertCount(1, $reversals);
        $this->assertSame(3.0, abs((float) ($reversals[0]['data']['credit_amount'] ?? 0)));
    }

    public function testCoachCommissionReversalRollsBackAndReleasesLockWhenInsertFails() {
        global $mock_wpdb_get_results, $mock_user_meta, $mock_wpdb_inserts, $mock_fail_wpdb_insert_tables;
        $mock_user_meta[94173] = ['intersoccer_credits' => 10.0];
        $mock_fail_wpdb_insert_tables['intersoccer_referral_credits'] = true;
        $order = $this->make_order(94040, 9440, 100, 'completed');
        $mock_wpdb_get_results['intersoccer_referrals'] = [
            (object) [
                'id' => 175,
                'order_id' => 94040,
                'coach_id' => 94173,
                'customer_id' => 9440,
                'commission_amount' => 20.0,
                'loyalty_bonus' => 0,
                'retention_bonus' => 0,
                'status' => 'paid',
            ],
        ];
        $mock_wpdb_get_results['intersoccer_referral_rewards'] = [];
        $mock_wpdb_get_results['intersoccer_coach_commissions'] = [];

        $refund = new WC_Order_Refund(940401, 50);
        $order->add_refund($refund);

        InterSoccer_Commission_Manager::get_instance()->handle_order_refunded_rewards(94040, 940401);

        $this->assertSame(10.0, (float) ($mock_user_meta[94173]['intersoccer_credits'] ?? 0));
        $this->assertSame(0, count(array_filter((array) $mock_wpdb_inserts, static function ($row) {
            return strpos((string) ($row['table'] ?? ''), 'intersoccer_referral_credits') !== false;
        })));
        $this->assertFalse((bool) get_option(intersoccer_coach_credit_lock_option_name(94173), false));
        global $mock_scheduled_single_events;
        $this->assertCount(1, $mock_scheduled_single_events);
        $this->assertSame('commission', (string) ($mock_scheduled_single_events[0]['args'][1] ?? ''));
        $payload = $mock_scheduled_single_events[0]['args'][2] ?? [];
        $this->assertSame(940401, (int) ($payload['refund_id'] ?? 0));
    }

    public function testReversePaidRewardsDoesNotThrowWhenCommissionInsertFails() {
        global $mock_wpdb_get_results, $mock_user_meta, $mock_wpdb_inserts, $mock_fail_wpdb_insert_tables, $mock_scheduled_single_events;
        $mock_user_meta[94175] = ['intersoccer_credits' => 10.0];
        $mock_fail_wpdb_insert_tables['intersoccer_referral_credits'] = true;
        $order = $this->make_order(94043, 9443, 100, 'refunded');
        $mock_wpdb_get_results['intersoccer_referrals'] = [
            (object) [
                'id' => 177,
                'order_id' => 94043,
                'coach_id' => 94175,
                'customer_id' => 9443,
                'commission_amount' => 20.0,
                'loyalty_bonus' => 0,
                'retention_bonus' => 0,
                'status' => 'paid',
            ],
        ];
        $mock_wpdb_get_results['intersoccer_referral_rewards'] = [];
        $mock_wpdb_get_results['intersoccer_coach_commissions'] = [];

        InterSoccer_Commission_Manager::get_instance()->reverse_paid_rewards(94043);

        $this->assertSame(10.0, (float) ($mock_user_meta[94175]['intersoccer_credits'] ?? 0));
        $this->assertCount(1, $mock_scheduled_single_events);
        $this->assertSame('commission', (string) ($mock_scheduled_single_events[0]['args'][1] ?? ''));
        $payload = $mock_scheduled_single_events[0]['args'][2] ?? [];
        $this->assertTrue(!empty($payload['force_full']));
    }

    public function testReinstatementSkipsSecondRetakeAfterPriorRetakeWriteOff() {
        $points = $this->manager();
        $order = $this->make_order(94041, 9441, 100, 'failed');
        $order->update_meta_data('_intersoccer_points_redeemed', 30);
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order->save();
        $this->seed_balance(9441, 0);

        $points->handle_order_status_failed(94041);
        $order->set_status('processing');
        $points->handle_order_status_changed(94041, 'failed', 'processing', $order);

        global $mock_points_balances;
        $mock_points_balances[9441] = 0;

        $order->set_status('cancelled');

        $writeoffs_before = $this->count_ledger_rows_for_order(94041, 'reversal_write_off');
        $retaken_before = $this->sum_abs_ledger_for_order(94041, 'redeemed_retaken');

        $order->set_status('processing');
        $points->handle_order_status_changed(94041, 'cancelled', 'processing', $order);

        $this->assertSame($writeoffs_before, $this->count_ledger_rows_for_order(94041, 'reversal_write_off'));
        $this->assertSame($retaken_before, $this->sum_abs_ledger_for_order(94041, 'redeemed_retaken'));
    }

    private function count_ledger_rows_for_order($order_id, $type) {
        global $mock_points_log_rows;
        $count = 0;
        foreach ((array) $mock_points_log_rows as $row) {
            if ((int) ($row['order_id'] ?? 0) === (int) $order_id
                && ($row['transaction_type'] ?? '') === $type
            ) {
                $count++;
            }
        }
        return $count;
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
