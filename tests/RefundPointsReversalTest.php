<?php

use PHPUnit\Framework\TestCase;

/**
 * Proportional earned-points reversal and redeemed-points return on WooCommerce refunds.
 */
class RefundPointsReversalTest extends TestCase {

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/class-points-manager.php';

        global $mock_user_meta, $mock_wc_orders_by_id, $mock_order_points_allocated, $mock_points_log_rows, $mock_points_balances, $mock_options, $mock_wpdb_inserts, $mock_referral_log_messages;
        $mock_user_meta = [];
        $mock_wc_orders_by_id = [];
        $mock_order_points_allocated = [];
        $mock_points_log_rows = [];
        $mock_points_balances = [];
        $mock_wpdb_inserts = [];
        $mock_referral_log_messages = [];
        $mock_options['intersoccer_points_allocation_mode'] = 'ratio';
        $mock_options['intersoccer_points_rate_customer_purchase'] = 10;
        $mock_options['intersoccer_points_golive_date'] = '';
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

    private function ledger_amounts_for_order($order_id, $transaction_type = null) {
        global $mock_points_log_rows;
        $amounts = [];
        foreach ((array) $mock_points_log_rows as $row) {
            if ((int) ($row['order_id'] ?? 0) !== (int) $order_id) {
                continue;
            }
            if ($transaction_type !== null && ($row['transaction_type'] ?? '') !== $transaction_type) {
                continue;
            }
            $amounts[] = (int) ($row['points_amount'] ?? 0);
        }
        return $amounts;
    }

    public function testFullRefundReversesAllEarnedPoints() {
        $points = $this->manager();
        $order = $this->make_order(92001, 9201, 100, 'completed');
        $this->seed_balance(9201, 0);

        $points->allocate_points_for_order(92001);
        $this->assertSame(10, $points->get_points_balance(9201));

        $order->set_status('refunded');
        $points->handle_order_status_refunded(92001);

        $this->assertSame(0, $points->get_points_balance(9201));
        $this->assertSame(10, (int) $order->get_meta('_intersoccer_purchase_points_reversed_total', true));
        $this->assertSame(1, (int) $order->get_meta('_intersoccer_purchase_points_reversed', true));
        $this->assertSame([-10], $this->ledger_amounts_for_order(92001, 'order_refund'));
    }

    public function testLineItemPartialRefundReversesProportionalEarnedPoints() {
        $points = $this->manager();
        $order = $this->make_order(92002, 9202, 100, 'completed');
        $order->set_line_items([
            new WC_Order_Item_Product(60),
            new WC_Order_Item_Product(40),
        ]);
        $this->seed_balance(9202, 0);

        $points->allocate_points_for_order(92002);
        $this->assertSame(10, $points->get_points_balance(9202));

        $refund = new WC_Order_Refund(920021, 0);
        $refund->add_line_item(new WC_Order_Item_Product(50));
        $order->add_refund($refund);

        $points->handle_woocommerce_order_refunded(92002, 920021);

        $this->assertSame(5, $points->get_points_balance(9202));
        $this->assertSame(5, (int) $order->get_meta('_intersoccer_purchase_points_reversed_total', true));
        $this->assertSame([-5], $this->ledger_amounts_for_order(92002, 'order_refund'));
    }

    public function testCashPartialRefundUsesOrderTotalDenominator() {
        $points = $this->manager();
        $order = $this->make_order(92003, 9203, 200, 'completed');
        $this->seed_balance(9203, 0);

        $points->allocate_points_for_order(92003);
        $this->assertSame(20, $points->get_points_balance(9203));

        $refund = new WC_Order_Refund(920031, 50);
        $order->add_refund($refund);

        $points->handle_woocommerce_order_refunded(92003, 920031);

        // floor(20 * 0.25) = 5
        $this->assertSame(15, $points->get_points_balance(9203));
        $this->assertSame(5, (int) $order->get_meta('_intersoccer_purchase_points_reversed_total', true));
    }

    public function testPartialThenFullRefundDoesNotDoubleReverse() {
        $points = $this->manager();
        $order = $this->make_order(92004, 9204, 100, 'completed');
        $this->seed_balance(9204, 0);

        $points->allocate_points_for_order(92004);

        $partial = new WC_Order_Refund(920041, 50);
        $order->add_refund($partial);
        $points->handle_woocommerce_order_refunded(92004, 920041);
        $this->assertSame(5, $points->get_points_balance(9204));

        $order->set_status('refunded');
        $points->handle_order_status_refunded(92004);

        $this->assertSame(0, $points->get_points_balance(9204));
        $this->assertSame(10, (int) $order->get_meta('_intersoccer_purchase_points_reversed_total', true));
        $this->assertSame([-5, -5], $this->ledger_amounts_for_order(92004, 'order_refund'));
    }

    public function testRepeatedRefundHookIsIdempotent() {
        $points = $this->manager();
        $order = $this->make_order(92005, 9205, 100, 'completed');
        $this->seed_balance(9205, 0);

        $points->allocate_points_for_order(92005);

        $refund = new WC_Order_Refund(920051, 50);
        $order->add_refund($refund);

        $points->handle_woocommerce_order_refunded(92005, 920051);
        $points->handle_woocommerce_order_refunded(92005, 920051);

        $this->assertSame(5, $points->get_points_balance(9205));
        $this->assertSame(5, (int) $order->get_meta('_intersoccer_purchase_points_reversed_total', true));
        $this->assertCount(1, $this->ledger_amounts_for_order(92005, 'order_refund'));
    }

    public function testReversalNeverExceedsEarnedAllocation() {
        $points = $this->manager();
        $order = $this->make_order(92006, 9206, 100, 'completed');
        $this->seed_balance(9206, 0);

        $points->allocate_points_for_order(92006);

        $refund = new WC_Order_Refund(920061, 100);
        $order->add_refund($refund);
        $refund2 = new WC_Order_Refund(920062, 100);
        $order->add_refund($refund2);

        $points->handle_woocommerce_order_refunded(92006, 920061);
        $points->handle_woocommerce_order_refunded(92006, 920062);

        $this->assertSame(0, $points->get_points_balance(9206));
        $this->assertSame(10, (int) $order->get_meta('_intersoccer_purchase_points_reversed_total', true));
        $this->assertSame(-10, array_sum($this->ledger_amounts_for_order(92006, 'order_refund')));
    }

    public function testNegativeBalanceClampRecordsShortfallWithoutOverDebit() {
        global $mock_referral_log_messages, $mock_user_meta;

        $points = $this->manager();
        $order = $this->make_order(92007, 9207, 100, 'completed');
        $this->seed_balance(9207, 0);

        $points->allocate_points_for_order(92007);
        $this->assertSame(10, $points->get_points_balance(9207));
        // Customer spent points elsewhere; balance is below earned allocation for this order.
        $mock_user_meta[9207]['intersoccer_points_balance'] = 3;

        $order->set_status('refunded');
        $points->handle_order_status_refunded(92007);

        $this->assertSame(0, $points->get_points_balance(9207));
        $this->assertSame(10, (int) $order->get_meta('_intersoccer_purchase_points_reversed_total', true));
        $this->assertSame(7, (int) $order->get_meta('_intersoccer_points_reversal_shortfall', true));

        $logged = implode("\n", (array) $mock_referral_log_messages);
        $this->assertStringContainsString('SHORTFALL', $logged);

        global $mock_points_log_rows;
        $refund_rows = array_values(array_filter((array) $mock_points_log_rows, static function ($row) {
            return ($row['transaction_type'] ?? '') === 'order_refund';
        }));
        $this->assertCount(1, $refund_rows);
        $meta = json_decode($refund_rows[0]['metadata'] ?? '{}', true);
        $this->assertSame(7, (int) ($meta['shortfall'] ?? 0));
    }

    public function testRedeemedPointsReturnedProportionallyOnPartialRefund() {
        $points = $this->manager();
        $order = $this->make_order(92008, 9208, 100, 'completed');
        $order->update_meta_data('_intersoccer_points_redeemed', 100);
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order->save();
        $this->seed_balance(9208, 0);

        $refund = new WC_Order_Refund(920081, 50);
        $order->add_refund($refund);

        $points->handle_woocommerce_order_refunded(92008, 920081);

        $this->assertSame(50, $points->get_points_balance(9208));
        $this->assertSame(50, (int) $order->get_meta('_intersoccer_redeemed_points_returned_total', true));
        $this->assertSame([50], $this->ledger_amounts_for_order(92008, 'points_refund'));
    }

    public function testCancellationReturnsAllRedeemedPointsWithoutDoubleReturn() {
        $points = $this->manager();
        $order = $this->make_order(92009, 9209, 100, 'cancelled');
        $order->update_meta_data('_intersoccer_points_redeemed', 80);
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order->save();
        $this->seed_balance(9209, 0);

        $points->handle_order_status_cancelled(92009);
        $points->handle_order_status_cancelled(92009);

        $this->assertSame(80, $points->get_points_balance(9209));
        $this->assertSame(1, (int) $order->get_meta('_intersoccer_redeemed_points_returned', true));
        $this->assertCount(1, $this->ledger_amounts_for_order(92009, 'points_refund'));
    }

    public function testCancellationReversesEarnedPurchasePointsWithOrderCancelledLedgerType() {
        $points = $this->manager();
        $order = $this->make_order(92010, 9210, 100, 'completed');
        $this->seed_balance(9210, 0);

        $points->allocate_points_for_order(92010);
        $order->set_status('cancelled');

        $points->handle_order_status_cancelled(92010);

        $this->assertSame(0, $points->get_points_balance(9210));
        $this->assertSame([-10], $this->ledger_amounts_for_order(92010, 'order_cancelled'));
    }

    public function testLegacyEarnedReversedFlagPreventsSecondReversal() {
        $points = $this->manager();
        $order = $this->make_order(92011, 9211, 100, 'refunded');
        $order->update_meta_data('_intersoccer_purchase_points_reversed', 1);
        $order->save();
        $this->seed_balance(9211, 10);

        global $mock_order_points_allocated;
        $mock_order_points_allocated[92011] = 10;

        $points->sync_refund_reversals(92011, 'order_refunded', ['force_fraction_one' => true]);

        $this->assertSame(10, $points->get_points_balance(9211));
        $this->assertSame([], $this->ledger_amounts_for_order(92011, 'order_refund'));
    }

    public function testLegacyRedeemedReturnedFlagPreventsSecondReturn() {
        $points = $this->manager();
        $order = $this->make_order(92012, 9212, 100, 'cancelled');
        $order->update_meta_data('_intersoccer_points_redeemed', 60);
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order->update_meta_data('_intersoccer_redeemed_points_returned', 1);
        $order->save();
        $this->seed_balance(9212, 5);

        $points->sync_refund_reversals(92012, 'order_cancelled', ['force_fraction_one' => true]);

        $this->assertSame(5, $points->get_points_balance(9212));
        $this->assertSame([], $this->ledger_amounts_for_order(92012, 'points_refund'));
    }

    /**
     * Woo's unpaid-order cleanup moves a pending order to cancelled. Points
     * taken at checkout come back once, and no purchase points are reversed
     * because none were earned.
     */
    public function testDeferredAllocationSkipsRefundedOrder() {
        global $mock_referral_log_messages;

        $points = $this->manager();
        $this->make_order(92030, 9230, 100, 'refunded');
        $this->seed_balance(9230, 0);

        $points->allocate_points_for_order(92030);

        $this->assertSame(0, $points->get_points_balance(9230));
        $this->assertSame([], $this->ledger_amounts_for_order(92030, 'order_purchase'));
        $logged = implode("\n", (array) $mock_referral_log_messages);
        $this->assertStringContainsString('Skipped points allocation for order 92030', $logged);
        $this->assertStringContainsString('status: refunded', $logged);
    }

    public function testDeferredAllocationSkipsCancelledOrder() {
        $points = $this->manager();
        $this->make_order(92031, 9231, 100, 'cancelled');
        $this->seed_balance(9231, 0);

        $points->allocate_points_for_order(92031);

        $this->assertSame(0, $points->get_points_balance(9231));
        $this->assertSame([], $this->ledger_amounts_for_order(92031, 'order_purchase'));
    }

    public function testDeferredAllocationStillAllocatesCompletedOrder() {
        $points = $this->manager();
        $this->make_order(92032, 9232, 100, 'completed');
        $this->seed_balance(9232, 0);

        $points->allocate_points_for_order(92032);

        $this->assertSame(10, $points->get_points_balance(9232));
        $this->assertSame([10], $this->ledger_amounts_for_order(92032, 'order_purchase'));
    }

    public function testLegacyEarnedRefundLedgerWithoutMetaPreventsSecondReversal() {
        global $mock_order_points_allocated, $mock_points_log_rows;

        $points = $this->manager();
        $order = $this->make_order(92013, 9213, 100, 'refunded');
        $this->seed_balance(9213, 0);

        $mock_order_points_allocated[92013] = 10;
        $mock_points_log_rows[] = [
            'order_id' => 92013,
            'customer_id' => 9213,
            'transaction_type' => 'order_purchase',
            'points_amount' => 10,
        ];
        $mock_points_log_rows[] = [
            'order_id' => 92013,
            'customer_id' => 9213,
            'transaction_type' => 'order_refund',
            'points_amount' => -10,
        ];

        $points->handle_order_status_refunded(92013);

        $this->assertSame(0, $points->get_points_balance(9213));
        $this->assertCount(1, $this->ledger_amounts_for_order(92013, 'order_refund'));
    }

    public function testLegacyRedeemedRefundLedgerWithoutMetaPreventsSecondReturn() {
        global $mock_points_log_rows;

        $points = $this->manager();
        $order = $this->make_order(92014, 9214, 100, 'cancelled');
        $order->update_meta_data('_intersoccer_points_redeemed', 60);
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order->save();
        $this->seed_balance(9214, 60);

        $mock_points_log_rows[] = [
            'order_id' => 92014,
            'customer_id' => 9214,
            'transaction_type' => 'points_refund',
            'points_amount' => 60,
        ];

        $points->handle_order_status_cancelled(92014);

        $this->assertSame(60, $points->get_points_balance(9214));
        $this->assertCount(1, $this->ledger_amounts_for_order(92014, 'points_refund'));
    }

    public function testLegacyPartialEarnedRefundLedgerReversesOnlyRemainderOnFullRefund() {
        global $mock_order_points_allocated, $mock_points_log_rows, $mock_user_meta;

        $points = $this->manager();
        $order = $this->make_order(92015, 9215, 100, 'completed');
        $this->seed_balance(9215, 0);

        $points->allocate_points_for_order(92015);
        $this->assertSame(10, $points->get_points_balance(9215));

        $mock_points_log_rows[] = [
            'order_id' => 92015,
            'customer_id' => 9215,
            'transaction_type' => 'order_refund',
            'points_amount' => -5,
        ];
        $mock_user_meta[9215]['intersoccer_points_balance'] = 5;

        $order->set_status('refunded');
        $points->handle_order_status_refunded(92015);

        $this->assertSame(0, $points->get_points_balance(9215));
        $this->assertSame(10, (int) $order->get_meta('_intersoccer_purchase_points_reversed_total', true));
        $this->assertSame([-5, -5], $this->ledger_amounts_for_order(92015, 'order_refund'));
    }

    public function testUnpaidPendingOrderAutoCancelReturnsRedeemedPointsOnce() {
        $points = $this->manager();
        $order = $this->make_order(92020, 9220, 50, 'pending');
        $order->update_meta_data('_intersoccer_points_redeemed', 30);
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order->save();
        $this->seed_balance(9220, 5);

        $order->set_status('cancelled');
        $points->handle_order_status_cancelled(92020);
        $points->refund_points_on_cancellation(92020);

        $this->assertSame(35, $points->get_points_balance(9220));
        $this->assertSame([30], $this->ledger_amounts_for_order(92020, 'points_refund'));
        $this->assertSame([], $this->ledger_amounts_for_order(92020, 'order_cancelled'));
    }
}
