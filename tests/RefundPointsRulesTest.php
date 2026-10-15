<?php

use PHPUnit\Framework\TestCase;

/**
 * Business rules for refund points reversal (rules 1–10).
 */
class RefundPointsRulesTest extends TestCase {

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/class-points-manager.php';
        require_once __DIR__ . '/../includes/class-commission-manager.php';
        require_once __DIR__ . '/../includes/class-cli-redeemed-backfill.php';

        global $mock_user_meta, $mock_wc_orders_by_id, $mock_order_points_allocated, $mock_points_log_rows, $mock_points_balances, $mock_options, $mock_wpdb_inserts, $mock_wpdb_updates, $mock_wpdb_deletes, $mock_referral_log_messages, $mock_wc_get_orders;
        $mock_user_meta = [];
        $mock_wc_orders_by_id = [];
        $mock_order_points_allocated = [];
        $mock_points_log_rows = [];
        $mock_points_balances = [];
        $mock_wpdb_inserts = [];
        $mock_wpdb_updates = [];
        $mock_wpdb_deletes = [];
        $mock_referral_log_messages = [];
        $mock_wc_get_orders = null;
        $mock_options['intersoccer_points_allocation_mode'] = 'ratio';
        $mock_options['intersoccer_points_rate_customer_purchase'] = 10;
        $mock_options['intersoccer_points_golive_date'] = '';
        $mock_options['intersoccer_unpaid_redeemed_release_days'] = 14;
        unset($mock_options['intersoccer_proportional_commission_reversal']);
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
        static $next_id = 1000;
        static $ledger_seq = 0;
        if (!isset($row['id'])) {
            $row['id'] = $next_id++;
        }
        if (!isset($row['created_at'])) {
            $row['created_at'] = gmdate('Y-m-d H:i:s', 1700000000 + $ledger_seq++);
        }
        $mock_points_log_rows[] = $row;
        if (isset($row['customer_id'], $row['points_balance'])) {
            $mock_points_balances[(int) $row['customer_id']] = (int) $row['points_balance'];
        }
        if (($row['transaction_type'] ?? '') === 'order_purchase' && isset($row['order_id'])) {
            $mock_order_points_allocated[(int) $row['order_id']] = $row['points_amount'] ?? true;
        }
    }

    private function ledger_types_for_order($order_id, $type) {
        global $mock_points_log_rows;
        $out = [];
        foreach ((array) $mock_points_log_rows as $row) {
            if ((int) ($row['order_id'] ?? 0) !== (int) $order_id) {
                continue;
            }
            if (($row['transaction_type'] ?? '') === $type) {
                $out[] = $row;
            }
        }
        return $out;
    }

    private function sum_abs_ledger_for_order($order_id, $type) {
        $sum = 0;
        foreach ($this->ledger_types_for_order($order_id, $type) as $row) {
            $sum += abs((int) ($row['points_amount'] ?? 0));
        }
        return $sum;
    }

    public function testRule1ShortfallCreatesReversalWriteOffRow() {
        $points = $this->manager();
        $order = $this->make_order(93001, 9301, 100, 'completed');
        $this->seed_balance(9301, 0);

        $points->allocate_points_for_order(93001);
        $this->seed_balance(9301, 2);

        $order->set_status('refunded');
        $points->handle_order_status_refunded(93001);

        $writeoffs = $this->ledger_types_for_order(93001, 'reversal_write_off');
        $this->assertNotEmpty($writeoffs);
        $this->assertSame(0, (int) $writeoffs[0]['points_amount']);
        $meta = json_decode($writeoffs[0]['metadata'] ?? '{}', true);
        $this->assertSame(8, (int) ($meta['written_off'] ?? 0));
        $this->assertArrayHasKey('fraction', $meta);
    }

    public function testRule2GiftReceivedBeforePurchaseIsProtected() {
        $points = $this->manager();
        $customer_id = 93021;
        $this->seed_ledger_row([
            'customer_id' => $customer_id,
            'order_id' => 0,
            'transaction_type' => 'gift_received',
            'points_amount' => 80,
            'points_balance' => 80,
            'created_at' => '2024-01-01 10:00:00',
            'id' => 1,
        ]);
        $this->seed_balance($customer_id, 90);
        $this->seed_ledger_row([
            'customer_id' => $customer_id,
            'order_id' => 93002,
            'transaction_type' => 'order_purchase',
            'points_amount' => 10,
            'points_balance' => 90,
            'created_at' => '2024-01-02 10:00:00',
            'id' => 2,
        ]);

        $this->assertSame(80, $points->get_unspent_gifted_points($customer_id));
        $this->assertSame(10, $points->get_attributable_balance_for_order_reversal($customer_id, 93002));

        $order = $this->make_order(93002, $customer_id, 100, 'completed');
        $order->set_status('refunded');
        $points->handle_order_status_refunded(93002);

        $this->assertSame(10, abs((int) ($this->ledger_types_for_order(93002, 'order_refund')[0]['points_amount'] ?? 0)));
        $this->assertSame(80, $points->get_points_balance($customer_id));
    }

    public function testRule2GiftReceivedAfterPurchaseIsProtected() {
        $points = $this->manager();
        $customer_id = 93022;
        $order = $this->make_order(930022, $customer_id, 100, 'completed');
        $this->seed_balance($customer_id, 0);
        $points->allocate_points_for_order(930022);

        $this->seed_ledger_row([
            'customer_id' => $customer_id,
            'order_id' => 0,
            'transaction_type' => 'gift_received',
            'points_amount' => 50,
            'points_balance' => 60,
            'created_at' => '2024-02-02 10:00:00',
            'id' => 5002,
        ]);
        $this->seed_balance($customer_id, 60);

        $this->assertSame(50, $points->get_unspent_gifted_points($customer_id));
        $this->assertSame(10, $points->get_attributable_balance_for_order_reversal($customer_id, 930022));

        $order->set_status('refunded');
        $points->handle_order_status_refunded(930022);

        $this->assertSame(10, abs((int) ($this->ledger_types_for_order(930022, 'order_refund')[0]['points_amount'] ?? 0)));
        $this->assertSame(50, $points->get_points_balance($customer_id));
    }

    public function testRule2PartlySpentGiftProtectedOnlyForUnspentPart() {
        $points = $this->manager();
        $customer_id = 93023;
        $this->seed_ledger_row([
            'customer_id' => $customer_id,
            'order_id' => 0,
            'transaction_type' => 'gift_received',
            'points_amount' => 30,
            'points_balance' => 30,
            'created_at' => '2024-03-01 10:00:00',
            'id' => 10,
        ]);
        $this->seed_ledger_row([
            'customer_id' => $customer_id,
            'order_id' => 777,
            'transaction_type' => 'points_redemption',
            'points_amount' => -20,
            'points_balance' => 10,
            'created_at' => '2024-03-02 10:00:00',
            'id' => 11,
        ]);
        $this->seed_ledger_row([
            'customer_id' => $customer_id,
            'order_id' => 930023,
            'transaction_type' => 'order_purchase',
            'points_amount' => 10,
            'points_balance' => 20,
            'created_at' => '2024-03-03 10:00:00',
            'id' => 12,
        ]);
        $this->seed_balance($customer_id, 20);

        $this->assertSame(10, $points->get_unspent_gifted_points($customer_id));
        $this->assertSame(10, $points->get_attributable_balance_for_order_reversal($customer_id, 930023));

        $order = $this->make_order(930023, $customer_id, 100, 'completed');
        $order->set_status('refunded');
        $points->handle_order_status_refunded(930023);

        $this->assertSame(10, abs((int) ($this->ledger_types_for_order(930023, 'order_refund')[0]['points_amount'] ?? 0)));
        $this->assertSame(10, $points->get_points_balance($customer_id));
    }

    public function testRule3ExpiredPointsWriteOffFullDelta() {
        $points = $this->manager();
        $order = $this->make_order(93003, 9303, 100, 'completed');
        $this->seed_balance(9303, 0);
        $points->allocate_points_for_order(93003);
        $purchase_id = $points->get_order_purchase_log_row_id(93003);

        $this->seed_ledger_row([
            'customer_id' => 9303,
            'order_id' => 0,
            'transaction_type' => 'points_expired',
            'points_amount' => -5,
            'points_balance' => 5,
            'id' => $purchase_id + 1,
        ]);
        $this->seed_balance(9303, 10);

        $order->set_status('refunded');
        $points->handle_order_status_refunded(93003);

        $refunds = $this->ledger_types_for_order(93003, 'order_refund');
        $this->assertCount(0, $refunds);
        $writeoffs = $this->ledger_types_for_order(93003, 'reversal_write_off');
        $this->assertNotEmpty($writeoffs);
        $meta = json_decode($writeoffs[0]['metadata'] ?? '{}', true);
        $this->assertSame('expired', $meta['reason'] ?? '');
    }

    public function testRule4CancelledNeverEarnedOrderWritesNoEarnedReversal() {
        $points = $this->manager();
        $order = $this->make_order(93004, 9304, 100, 'pending');
        $this->seed_balance(9304, 0);

        $points->allocate_points_for_order(93004);
        $this->assertSame(0, $points->get_points_balance(9304));

        $order->set_status('cancelled');
        $points->handle_order_status_cancelled(93004);

        $this->assertSame([], $this->ledger_types_for_order(93004, 'order_cancelled'));
        $this->assertSame([], $this->ledger_types_for_order(93004, 'order_refund'));
    }

    public function testRule5DeferredAllocationUsesNetTotalAfterRefunds() {
        $points = $this->manager();
        $order = $this->make_order(93005, 9305, 100, 'completed');
        $order->set_total_refunded(50);
        $this->seed_balance(9305, 0);

        $points->allocate_points_for_order(93005);
        $this->assertSame(5, $points->get_points_balance(9305));
    }

    public function testRule6ReversalMetadataIncludesFraction() {
        $points = $this->manager();
        $order = $this->make_order(93006, 9306, 100, 'completed');
        $order->update_meta_data('_intersoccer_points_redeemed', 20);
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order->save();
        $this->seed_balance(9306, 0);

        $refund = new WC_Order_Refund(930061, 50);
        $order->add_refund($refund);
        $points->handle_woocommerce_order_refunded(93006, 930061);

        global $mock_points_log_rows;
        $found = false;
        foreach ((array) $mock_points_log_rows as $row) {
            if (($row['transaction_type'] ?? '') === 'points_refund') {
                $meta = json_decode($row['metadata'] ?? '{}', true);
                $this->assertArrayHasKey('fraction', $meta);
                $found = true;
            }
        }
        $this->assertTrue($found);
    }

    public function testRule7ReferralRewardReversalDoesNotTakeReferrersGiftedPoints() {
        global $mock_wpdb_get_results;
        $referrer_id = 931201;
        $order = $this->make_order(931201, 931200, 100, 'completed');
        $order->update_meta_data('_intersoccer_referrer_reward_points', 10);
        $order->update_meta_data('_intersoccer_referrer_reward_user_id', $referrer_id);
        $order->save();

        $this->seed_ledger_row([
            'customer_id' => $referrer_id,
            'order_id' => 0,
            'transaction_type' => 'gift_received',
            'points_amount' => 50,
            'points_balance' => 50,
            'created_at' => '2024-05-01 10:00:00',
            'id' => 8001,
        ]);
        $this->seed_ledger_row([
            'customer_id' => $referrer_id,
            'order_id' => 888001,
            'transaction_type' => 'points_redemption',
            'points_amount' => -40,
            'points_balance' => 10,
            'created_at' => '2024-05-02 10:00:00',
            'id' => 8002,
        ]);
        $this->seed_balance($referrer_id, 10);

        $mock_wpdb_get_results['intersoccer_referral_rewards'] = [];
        $mock_wpdb_get_results['intersoccer_referrals'] = [];
        $mock_wpdb_get_results['intersoccer_coach_commissions'] = [];

        $refund = new WC_Order_Refund(9312011, 100);
        $order->add_refund($refund);
        InterSoccer_Commission_Manager::get_instance()->handle_order_refunded_rewards(931201, 9312011);

        $points = $this->manager();
        $this->assertSame(10, $points->get_points_balance($referrer_id));
        $reversals = array_filter((array) $GLOBALS['mock_points_log_rows'], static function ($row) use ($referrer_id) {
            return (int) ($row['customer_id'] ?? 0) === $referrer_id
                && ($row['transaction_type'] ?? '') === 'customer_referral_reversal';
        });
        $this->assertSame([], array_values($reversals));
        $writeoffs = $this->ledger_types_for_order(931201, 'reversal_write_off');
        $this->assertNotEmpty($writeoffs);
        $meta = json_decode($writeoffs[0]['metadata'] ?? '{}', true);
        $this->assertSame('spent_or_gift_protected', $meta['reason'] ?? '');
        $this->assertSame(10, (int) ($meta['written_off'] ?? 0));
    }

    public function testRule7PartialRefundReversesReferrerRewardProportionally() {
        global $mock_wpdb_get_results;
        $order = $this->make_order(93007, 9307, 100, 'completed');
        $order->update_meta_data('_intersoccer_referrer_reward_points', 10);
        $order->update_meta_data('_intersoccer_referrer_reward_user_id', 9308);
        $order->save();
        $this->seed_balance(9308, 10);

        $mock_wpdb_get_results['intersoccer_referral_rewards'] = [];
        $mock_wpdb_get_results['intersoccer_referrals'] = [];
        $mock_wpdb_get_results['intersoccer_coach_commissions'] = [];

        $refund = new WC_Order_Refund(930071, 50);
        $order->add_refund($refund);

        InterSoccer_Commission_Manager::get_instance()->handle_order_refunded_rewards(93007, 930071);

        $points = $this->manager();
        $this->assertSame(5, $points->get_points_balance(9308));
        $this->assertSame(5, (int) $order->get_meta('_intersoccer_referrer_reward_points_reversed_total', true));
    }

    public function testRule8ReinstatementReawardsDebitedAndResetsTotals() {
        $points = $this->manager();
        $order = $this->make_order(93008, 9308, 100, 'completed');
        $this->seed_balance(9308, 0);
        $points->allocate_points_for_order(93008);

        $order->set_status('refunded');
        $points->handle_order_status_refunded(93008);
        $this->assertSame(0, $points->get_points_balance(9308));

        $points->handle_order_status_changed(93008, 'refunded', 'processing', $order);

        $reinstated = $this->ledger_types_for_order(93008, 'reversal_reinstated');
        $this->assertNotEmpty($reinstated);
        $this->assertSame(10, (int) $reinstated[0]['points_amount']);

        $points->handle_order_status_changed(93008, 'refunded', 'processing', $order);
        $this->assertCount(1, $this->ledger_types_for_order(93008, 'reversal_reinstated'));
    }

    public function testRule9UnpaidTimeoutReleasesRedeemedOnce() {
        global $mock_wc_get_orders;
        $points = $this->manager();
        $order = $this->make_order(93009, 9309, 100, 'pending');
        $order->set_date_created(gmdate('Y-m-d H:i:s', time() - (20 * DAY_IN_SECONDS)));
        $order->update_meta_data('_intersoccer_points_redeemed', 40);
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order->save();
        $this->seed_balance(9309, 0);

        $mock_wc_get_orders = static function () use ($order) {
            return [$order];
        };

        $points->release_unpaid_redeemed_points_for_stale_orders();
        $this->assertSame(40, $points->get_points_balance(9309));
        $this->assertSame(1, (int) $order->get_meta('_intersoccer_unpaid_redeemed_released', true));

        $points->release_unpaid_redeemed_points_for_stale_orders();
        $this->assertSame(40, $points->get_points_balance(9309));
    }

    public function testRule10BackfillReportListsOwedRedeemedOnly() {
        $order = $this->make_order(93010, 9310, 100, 'refunded');
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order->update_meta_data('_intersoccer_points_redeemed', 30);
        $order->update_meta_data('_intersoccer_redeemed_points_returned_total', 10);
        $order->save();

        $rows = InterSoccer_CLI_Redeemed_Backfill::compute_redeemed_backfill_rows([93010]);
        $this->assertCount(1, $rows);
        $this->assertSame(20, $rows[0]['owed']);
    }

    public function testRule1NoWriteOffWhenBalanceFullyCoversReversal() {
        $points = $this->manager();
        $order = $this->make_order(93101, 93101, 100, 'completed');
        $this->seed_balance(93101, 0);
        $points->allocate_points_for_order(93101);
        $this->seed_balance(93101, 10);

        $order->set_status('refunded');
        $points->handle_order_status_refunded(93101);

        $this->assertSame([], $this->ledger_types_for_order(93101, 'reversal_write_off'));
        $this->assertSame(10, abs((int) ($this->ledger_types_for_order(93101, 'order_refund')[0]['points_amount'] ?? 0)));
    }

    public function testRule2SpentOrderPointsWrittenOffGiftNeverDebited() {
        $points = $this->manager();
        $order = $this->make_order(93102, 93102, 100, 'completed');
        $this->seed_balance(93102, 0);
        $points->allocate_points_for_order(93102);

        $this->seed_ledger_row([
            'customer_id' => 93102,
            'order_id' => 88888,
            'transaction_type' => 'order_purchase',
            'points_amount' => -8,
            'points_balance' => 2,
        ]);
        $this->seed_balance(93102, 2);
        $this->seed_ledger_row([
            'customer_id' => 93102,
            'order_id' => 0,
            'transaction_type' => 'gift_received',
            'points_amount' => 100,
            'points_balance' => 102,
        ]);
        $this->seed_balance(93102, 102);

        $order->set_status('refunded');
        $points->handle_order_status_refunded(93102);

        $this->assertSame(2, abs((int) ($this->ledger_types_for_order(93102, 'order_refund')[0]['points_amount'] ?? 0)));
        $writeoffs = $this->ledger_types_for_order(93102, 'reversal_write_off');
        $this->assertNotEmpty($writeoffs);
        $meta = json_decode($writeoffs[0]['metadata'] ?? '{}', true);
        $this->assertSame(8, (int) ($meta['written_off'] ?? 0));
        $this->assertSame(100, $points->get_points_balance(93102));
    }

    public function testRule3ExpiredBlocksDebitEvenWhenBalanceWouldCover() {
        $points = $this->manager();
        $order = $this->make_order(93103, 93103, 100, 'completed');
        $this->seed_balance(93103, 0);
        $points->allocate_points_for_order(93103);
        $purchase_id = $points->get_order_purchase_log_row_id(93103);

        $this->seed_ledger_row([
            'customer_id' => 93103,
            'order_id' => 0,
            'transaction_type' => 'points_expired',
            'points_amount' => -1,
            'points_balance' => 9,
            'id' => $purchase_id + 1,
        ]);
        $this->seed_balance(93103, 500);

        $order->set_status('refunded');
        $points->handle_order_status_refunded(93103);

        $this->assertSame([], $this->ledger_types_for_order(93103, 'order_refund'));
        $meta = json_decode($this->ledger_types_for_order(93103, 'reversal_write_off')[0]['metadata'] ?? '{}', true);
        $this->assertSame('expired', $meta['reason'] ?? '');
        $this->assertSame(500, $points->get_points_balance(93103));
    }

    public function testRule4FailedPendingAndOnHoldNeverAllocateEarned() {
        $points = $this->manager();

        foreach (['failed' => 931041, 'pending' => 931042, 'on-hold' => 931043] as $status => $order_id) {
            $order = $this->make_order($order_id, 93104, 100, $status);
            $this->seed_balance(93104, 0);
            $points->allocate_points_for_order($order_id);
            $this->assertSame(0, $points->get_points_balance(93104), "status {$status}");
        }

        $cancelled = $this->make_order(931044, 93104, 100, 'pending');
        $points->allocate_points_for_order(931044);
        $cancelled->set_status('cancelled');
        $points->handle_order_status_cancelled(931044);
        $this->assertSame([], $this->ledger_types_for_order(931044, 'order_cancelled'));
    }

    public function testRule5FullyRefundedOrderSkipsDeferredAllocation() {
        $points = $this->manager();
        $order = $this->make_order(93105, 93105, 100, 'completed');
        $order->set_total_refunded(100);
        $this->seed_balance(93105, 0);

        $points->allocate_points_for_order(93105);
        $this->assertSame(0, $points->get_points_balance(93105));
    }

    public function testRule6FractionFloorsOddShareAcrossLedgerTypes() {
        $points = $this->manager();
        $order = $this->make_order(93106, 93106, 100, 'completed');
        $order->update_meta_data('_intersoccer_points_redeemed', 11);
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order->save();
        $this->seed_balance(93106, 0);
        $points->allocate_points_for_order(93106);

        $refund = new WC_Order_Refund(931061, 50);
        $order->add_refund($refund);
        $points->handle_woocommerce_order_refunded(93106, 931061);

        $fractions = [];
        foreach ((array) $GLOBALS['mock_points_log_rows'] as $row) {
            if ((int) ($row['order_id'] ?? 0) !== 93106) {
                continue;
            }
            $type = $row['transaction_type'] ?? '';
            if (!in_array($type, ['points_refund', 'order_refund', 'reversal_write_off', 'reversal_reinstated'], true)) {
                continue;
            }
            $meta = json_decode($row['metadata'] ?? '{}', true);
            if (isset($meta['fraction'])) {
                $fractions[$type] = $meta['fraction'];
            }
        }
        $this->assertArrayHasKey('points_refund', $fractions);
        $this->assertSame(5, $this->sum_abs_ledger_for_order(93106, 'points_refund'));
        $this->assertSame(5, abs((int) ($this->ledger_types_for_order(93106, 'order_refund')[0]['points_amount'] ?? 0)));
    }

    public function testRule7CoachFirstOrderPointsProportionalPartialRefund() {
        global $mock_wpdb_get_results;
        $order = $this->make_order(93107, 93107, 100, 'completed');
        $this->seed_balance(93117, 9);

        $mock_wpdb_get_results['intersoccer_referral_rewards'] = [
            (object) ['order_id' => 93107, 'coach_id' => 93117, 'points_awarded' => 9, 'status' => 'active'],
        ];
        $mock_wpdb_get_results['intersoccer_referrals'] = [];
        $mock_wpdb_get_results['intersoccer_coach_commissions'] = [];

        $refund = new WC_Order_Refund(931071, 50);
        $order->add_refund($refund);
        InterSoccer_Commission_Manager::get_instance()->handle_order_refunded_rewards(93107, 931071);

        $points = $this->manager();
        $this->assertSame(5, $points->get_points_balance(93117));
        $this->assertSame(4, (int) $order->get_meta('_intersoccer_coach_referral_points_reversed_total', true));
    }

    public function testRule7CommissionNotReversedOnPartialRefundWhenOptionNo() {
        global $mock_wpdb_get_results, $mock_wpdb_updates, $mock_options;
        $mock_options['intersoccer_proportional_commission_reversal'] = 'no';
        $mock_wpdb_updates = [];
        $order = $this->make_order(93108, 93108, 100, 'completed');
        $mock_wpdb_get_results['intersoccer_referrals'] = [
            (object) ['order_id' => 93108, 'commission_amount' => 20.0, 'status' => 'paid'],
        ];
        $mock_wpdb_get_results['intersoccer_referral_rewards'] = [];
        $mock_wpdb_get_results['intersoccer_coach_commissions'] = [];

        $refund = new WC_Order_Refund(931081, 50);
        $order->add_refund($refund);
        InterSoccer_Commission_Manager::get_instance()->handle_order_refunded_rewards(93108, 931081);

        $this->assertSame(0.0, (float) $order->get_meta('_intersoccer_commission_amount_reversed_total', true));
    }

    public function testRule7CommissionReversedProportionallyWhenOptionUnsetDefaultsOn() {
        global $mock_wpdb_get_results, $mock_user_meta;
        $order = $this->make_order(931091, 931091, 100, 'completed');
        $mock_user_meta[93191] = ['intersoccer_credits' => 20.0];
        $mock_wpdb_get_results['intersoccer_referrals'] = [
            (object) [
                'id' => 2,
                'order_id' => 931091,
                'coach_id' => 93191,
                'customer_id' => 931091,
                'commission_amount' => 20.0,
                'loyalty_bonus' => 0,
                'retention_bonus' => 0,
                'status' => 'paid',
            ],
        ];
        $mock_wpdb_get_results['intersoccer_referral_rewards'] = [];
        $mock_wpdb_get_results['intersoccer_coach_commissions'] = [];

        $refund = new WC_Order_Refund(9310911, 50);
        $order->add_refund($refund);
        InterSoccer_Commission_Manager::get_instance()->handle_order_refunded_rewards(931091, 9310911);

        $this->assertSame(10.0, (float) $order->get_meta('_intersoccer_commission_amount_reversed_total', true));
        $this->assertSame(10.0, (float) ($mock_user_meta[93191]['intersoccer_credits'] ?? 0));
    }

    public function testRule7CommissionReversedProportionallyWhenOptionYes() {
        global $mock_wpdb_get_results, $mock_options, $mock_user_meta;
        $mock_options['intersoccer_proportional_commission_reversal'] = 'yes';
        $order = $this->make_order(93109, 93109, 100, 'completed');
        $mock_user_meta[93190] = ['intersoccer_credits' => 20.0];
        $mock_wpdb_get_results['intersoccer_referrals'] = [
            (object) [
                'id' => 1,
                'order_id' => 93109,
                'coach_id' => 93190,
                'customer_id' => 93109,
                'commission_amount' => 20.0,
                'loyalty_bonus' => 0,
                'retention_bonus' => 0,
                'status' => 'paid',
            ],
        ];
        $mock_wpdb_get_results['intersoccer_referral_rewards'] = [];
        $mock_wpdb_get_results['intersoccer_coach_commissions'] = [];

        $refund = new WC_Order_Refund(931091, 50);
        $order->add_refund($refund);
        InterSoccer_Commission_Manager::get_instance()->handle_order_refunded_rewards(93109, 931091);

        $this->assertSame(10.0, (float) $order->get_meta('_intersoccer_commission_amount_reversed_total', true));
        $this->assertSame(10.0, (float) ($mock_user_meta[93190]['intersoccer_credits'] ?? 0));
    }

    public function testRule7PartialThenFullRefundDoesNotDoubleReverseReferralReward() {
        global $mock_wpdb_get_results;
        $order = $this->make_order(93110, 93110, 100, 'completed');
        $order->update_meta_data('_intersoccer_referrer_reward_points', 10);
        $order->update_meta_data('_intersoccer_referrer_reward_user_id', 93120);
        $order->save();
        $this->seed_balance(93120, 10);
        $mock_wpdb_get_results['intersoccer_referral_rewards'] = [];
        $mock_wpdb_get_results['intersoccer_referrals'] = [];
        $mock_wpdb_get_results['intersoccer_coach_commissions'] = [];

        $refund1 = new WC_Order_Refund(931101, 50);
        $order->add_refund($refund1);
        InterSoccer_Commission_Manager::get_instance()->handle_order_refunded_rewards(93110, 931101);

        $refund2 = new WC_Order_Refund(931102, 50);
        $order->add_refund($refund2);
        InterSoccer_Commission_Manager::get_instance()->handle_order_refunded_rewards(93110, 931102);

        $points = $this->manager();
        $this->assertSame(0, $points->get_points_balance(93120));
        $this->assertSame(10, (int) $order->get_meta('_intersoccer_referrer_reward_points_reversed_total', true));
    }

    public function testRule8ReinstatementRetakeDoesNotTakeGiftedPoints() {
        $points = $this->manager();
        $customer_id = 931111;
        $order = $this->make_order(931111, $customer_id, 100, 'completed');
        $order->update_meta_data('_intersoccer_points_redeemed', 25);
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order->save();
        $this->seed_balance($customer_id, 0);

        $order->set_status('refunded');
        $points->handle_order_status_refunded(931111);
        $this->assertSame(25, $points->get_points_balance($customer_id));

        $this->seed_ledger_row([
            'customer_id' => $customer_id,
            'order_id' => 0,
            'transaction_type' => 'gift_received',
            'points_amount' => 100,
            'points_balance' => 125,
            'created_at' => '2024-04-01 10:00:00',
            'id' => 9001,
        ]);
        $this->seed_balance($customer_id, 125);

        $points->handle_order_status_changed(931111, 'refunded', 'processing', $order);

        $this->assertSame(25, $this->sum_abs_ledger_for_order(931111, 'redeemed_retaken'));
        $this->assertSame([], $this->ledger_types_for_order(931111, 'reversal_write_off'));
        $this->assertSame(100, $points->get_points_balance($customer_id));
    }

    public function testRule8ReinstatementRetakesRedeemedOnceWithShortfallWriteOff() {
        $points = $this->manager();
        $order = $this->make_order(93111, 93111, 100, 'completed');
        $order->update_meta_data('_intersoccer_points_redeemed', 25);
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order->save();
        $this->seed_balance(93111, 0);

        $order->set_status('refunded');
        $points->handle_order_status_refunded(93111);
        $this->assertSame(25, $this->sum_abs_ledger_for_order(93111, 'points_refund'));

        $this->seed_balance(93111, 5);
        $points->handle_order_status_changed(93111, 'refunded', 'processing', $order);

        $this->assertSame(5, $this->sum_abs_ledger_for_order(93111, 'redeemed_retaken'));
        $writeoffs = $this->ledger_types_for_order(93111, 'reversal_write_off');
        $reasons = array_map(static function ($row) {
            $meta = json_decode($row['metadata'] ?? '{}', true);
            return $meta['reason'] ?? '';
        }, $writeoffs);
        $this->assertContains('reinstated_retake_shortfall', $reasons);

        $points->handle_order_status_changed(93111, 'refunded', 'processing', $order);
        $this->assertSame(5, $this->sum_abs_ledger_for_order(93111, 'redeemed_retaken'));
    }

    public function testRule8RefundAfterReinstatementReversesEarnedAgain() {
        $points = $this->manager();
        $order = $this->make_order(93112, 93112, 100, 'completed');
        $this->seed_balance(93112, 0);
        $points->allocate_points_for_order(93112);

        $order->set_status('refunded');
        $points->handle_order_status_refunded(93112);
        $points->handle_order_status_changed(93112, 'refunded', 'processing', $order);
        $this->assertNotEmpty($this->ledger_types_for_order(93112, 'reversal_reinstated'));

        $order->set_status('refunded');
        $points->handle_order_status_refunded(93112);
        $this->assertGreaterThan(0, $this->sum_abs_ledger_for_order(93112, 'order_refund'));
    }

    public function testRule8UnpaidReleaseThenPaidRetakesRedeemedOnlyOnce() {
        $points = $this->manager();
        $order = $this->make_order(93113, 93113, 100, 'pending');
        $order->set_date_created(gmdate('Y-m-d H:i:s', time() - (20 * DAY_IN_SECONDS)));
        $order->update_meta_data('_intersoccer_points_redeemed', 40);
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order->save();
        $this->seed_balance(93113, 40);

        global $mock_wc_get_orders;
        $mock_wc_get_orders = static function () use ($order) {
            return [$order];
        };
        $points->release_unpaid_redeemed_points_for_stale_orders();
        $this->assertSame(1, (int) $order->get_meta('_intersoccer_unpaid_redeemed_released', true));

        $order->set_status('processing');
        $points->handle_order_status_changed(93113, 'pending', 'processing', $order);
        $this->assertSame(40, $this->sum_abs_ledger_for_order(93113, 'redeemed_retaken'));
        $points->handle_order_status_changed(93113, 'pending', 'processing', $order);
        $this->assertSame(40, $this->sum_abs_ledger_for_order(93113, 'redeemed_retaken'));
    }

    public function testRule8PendingToProcessingDoesNotReawardEarnedPoints() {
        $points = $this->manager();
        $order = $this->make_order(93114, 93114, 100, 'pending');
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order->save();
        $this->seed_balance(93114, 0);

        $points->handle_order_status_changed(93114, 'pending', 'processing', $order);
        $this->assertSame([], $this->ledger_types_for_order(93114, 'reversal_reinstated'));
        $this->assertSame([], $this->ledger_types_for_order(93114, 'order_purchase'));

        $order->set_status('processing');
        $points->allocate_points_for_order(93114);
        $this->assertSame(10, $points->get_points_balance(93114));
    }

    public function testRule9SkipsOrdersNewerThanReleaseDays() {
        global $mock_wc_get_orders;
        $points = $this->manager();
        $order = $this->make_order(93115, 93115, 100, 'pending');
        $order->set_date_created(gmdate('Y-m-d H:i:s', time() - (5 * DAY_IN_SECONDS)));
        $order->update_meta_data('_intersoccer_points_redeemed', 15);
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order->save();

        $mock_wc_get_orders = static function ($args) use ($order) {
            $cutoff_str = isset($args['date_created']) ? ltrim((string) $args['date_created'], '<') : '';
            $cutoff_ts = $cutoff_str !== '' ? strtotime($cutoff_str) : 0;
            $created = $order->get_date_created();
            $created_ts = is_object($created) && method_exists($created, 'getTimestamp')
                ? $created->getTimestamp()
                : strtotime((string) $created);
            if ($cutoff_ts > 0 && $created_ts >= $cutoff_ts) {
                return [];
            }
            return [$order];
        };

        $points->release_unpaid_redeemed_points_for_stale_orders();
        $this->assertSame(0, (int) $order->get_meta('_intersoccer_unpaid_redeemed_released', true));
    }

    public function testRule9ZeroReleaseDaysDisablesCronRelease() {
        global $mock_options, $mock_wc_get_orders;
        $mock_options['intersoccer_unpaid_redeemed_release_days'] = 0;
        $points = $this->manager();
        $order = $this->make_order(93116, 93116, 100, 'pending');
        $order->update_meta_data('_intersoccer_points_redeemed', 15);
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order->save();

        $called = false;
        $mock_wc_get_orders = static function () use (&$called) {
            $called = true;
            return [];
        };
        $points->release_unpaid_redeemed_points_for_stale_orders();
        $this->assertFalse($called);
    }

    public function testRule9ReleasedOrderCancelledDoesNotReturnRedeemedTwice() {
        $points = $this->manager();
        $order = $this->make_order(93117, 93117, 100, 'pending');
        $order->update_meta_data('_intersoccer_points_redeemed', 20);
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order->update_meta_data('_intersoccer_unpaid_redeemed_released', 1);
        $order->update_meta_data('_intersoccer_redeemed_points_returned_total', 20);
        $order->update_meta_data('_intersoccer_redeemed_points_returned', 1);
        $order->save();
        $this->seed_balance(93117, 20);

        $before = count($this->ledger_types_for_order(93117, 'points_refund'));
        $order->set_status('cancelled');
        $points->handle_order_status_cancelled(93117);
        $this->assertSame($before, count($this->ledger_types_for_order(93117, 'points_refund')));
    }

    public function testRule9PagingDoesNotStarveEligibleOrdersBehindReleasedBatch() {
        global $mock_wc_get_orders;
        $points = $this->manager();

        $released = [];
        for ($i = 0; $i < 200; $i++) {
            $id = 93200 + $i;
            $o = $this->make_order($id, 9320, 100, 'pending');
            $o->set_date_created(gmdate('Y-m-d H:i:s', time() - (20 * DAY_IN_SECONDS)));
            $o->update_meta_data('_intersoccer_unpaid_redeemed_released', 1);
            $o->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
            $o->save();
            $released[] = $o;
        }

        $eligible = $this->make_order(93210, 9321, 100, 'pending');
        $eligible->set_date_created(gmdate('Y-m-d H:i:s', time() - (20 * DAY_IN_SECONDS)));
        $eligible->update_meta_data('_intersoccer_points_redeemed', 12);
        $eligible->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $eligible->save();
        $this->seed_balance(9321, 0);

        $mock_wc_get_orders = static function ($args) use ($released, $eligible) {
            $page = isset($args['page']) ? (int) $args['page'] : 1;
            if ($page === 1) {
                return $released;
            }
            if ($page === 2) {
                return [$eligible];
            }
            return [];
        };

        $points->release_unpaid_redeemed_points_for_stale_orders();
        $this->assertSame(1, (int) $eligible->get_meta('_intersoccer_unpaid_redeemed_released', true));
        $this->assertSame(12, $points->get_points_balance(9321));
    }

    public function testRule10BackfillComputeNeverWrites() {
        global $mock_wpdb_inserts, $mock_wpdb_updates, $mock_wpdb_deletes, $mock_points_log_rows;
        $order = $this->make_order(93118, 93118, 100, 'refunded');
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order->update_meta_data('_intersoccer_points_redeemed', 25);
        $order->save();

        $inserts_before = count((array) $mock_wpdb_inserts);
        $updates_before = count((array) $mock_wpdb_updates);
        $deletes_before = count((array) $mock_wpdb_deletes);
        $ledger_before = count((array) $mock_points_log_rows);
        $meta_before = $order->get_meta('_intersoccer_points_redeemed', true);

        $rows = InterSoccer_CLI_Redeemed_Backfill::compute_redeemed_backfill_rows([93118]);

        $this->assertCount(1, $rows);
        $this->assertSame($inserts_before, count((array) $mock_wpdb_inserts));
        $this->assertSame($updates_before, count((array) $mock_wpdb_updates));
        $this->assertSame($deletes_before, count((array) $mock_wpdb_deletes));
        $this->assertSame($ledger_before, count((array) $mock_points_log_rows));
        $this->assertSame($meta_before, $order->get_meta('_intersoccer_points_redeemed', true));
    }

    public function testRule10BackfillTreatsLegacyReturnedFlagAndLedgerAsReturned() {
        $order_flag = $this->make_order(93119, 93119, 100, 'refunded');
        $order_flag->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order_flag->update_meta_data('_intersoccer_points_redeemed', 30);
        $order_flag->update_meta_data('_intersoccer_redeemed_points_returned', 1);
        $order_flag->save();
        $this->assertSame([], InterSoccer_CLI_Redeemed_Backfill::compute_redeemed_backfill_rows([93119]));

        $order_ledger = $this->make_order(93120, 93120, 100, 'refunded');
        $order_ledger->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order_ledger->update_meta_data('_intersoccer_points_redeemed', 30);
        $order_ledger->save();
        $this->seed_ledger_row([
            'customer_id' => 93120,
            'order_id' => 93120,
            'transaction_type' => 'points_refund',
            'points_amount' => 30,
            'points_balance' => 30,
        ]);
        $this->assertSame([], InterSoccer_CLI_Redeemed_Backfill::compute_redeemed_backfill_rows([93120]));
    }
}
