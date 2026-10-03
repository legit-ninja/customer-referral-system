<?php

use PHPUnit\Framework\TestCase;

/**
 * Cancel and failure only give points back when they were actually taken,
 * and a full refund or cancel takes back points the order earned.
 * Partial refunds are not reversed proportionally.
 */
class PointsRefundGuardTest extends TestCase {

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/class-points-manager.php';

        global $mock_user_meta, $mock_wc_orders_by_id, $mock_order_points_allocated, $mock_points_log_rows, $mock_points_balances, $mock_options;
        $mock_user_meta = [];
        $mock_wc_orders_by_id = [];
        $mock_order_points_allocated = [];
        $mock_points_log_rows = [];
        $mock_points_balances = [];
        $mock_options['intersoccer_points_allocation_mode'] = 'ratio';
        $mock_options['intersoccer_points_rate'] = 10;
        $mock_options['intersoccer_points_golive_date'] = '';
    }

    public function testFailedOrderDoesNotCreatePointsThatWereNeverDebited() {
        $points = new InterSoccer_Points_Manager();
        $order = new WC_Order(8901);
        $order->set_id(8901);
        $order->set_customer_id(8901);
        $order->set_status('failed');
        $order->update_meta_data('_intersoccer_points_redeemed', 100);
        $order->save();

        global $mock_wc_orders_by_id, $mock_user_meta;
        $mock_wc_orders_by_id[8901] = $order;
        $mock_user_meta[8901] = ['intersoccer_points_balance' => 100];

        $points->refund_points_on_failure(8901);
        $points->refund_points_on_failure(8901);

        $this->assertSame(100, (int) get_user_meta(8901, 'intersoccer_points_balance', true));
    }

    public function testReturnedPointsAreSavedSoTheyCannotBeReturnedTwice() {
        $points = new InterSoccer_Points_Manager();
        $order = new WC_Order(8902);
        $order->set_id(8902);
        $order->set_customer_id(8902);
        $order->set_status('cancelled');
        $order->update_meta_data('_intersoccer_points_redeemed', 100);
        $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
        $order->save();

        global $mock_wc_orders_by_id, $mock_user_meta;
        $mock_wc_orders_by_id[8902] = $order;
        $mock_user_meta[8902] = ['intersoccer_points_balance' => 0];

        $points->refund_points_on_cancellation(8902);
        $order->reload();
        $points->refund_points_on_cancellation(8902);

        $this->assertSame(100, (int) get_user_meta(8902, 'intersoccer_points_balance', true));
        $this->assertSame(1, (int) $order->get_meta('_intersoccer_redeemed_points_returned', true));
    }

    public function testCancelReversesEarnedPurchasePointsOnce() {
        $points = new InterSoccer_Points_Manager();
        $order = new WC_Order(8903);
        $order->set_id(8903);
        $order->set_customer_id(8903);
        $order->set_total(100);
        $order->set_status('processing');

        global $mock_wc_orders_by_id;
        $mock_wc_orders_by_id[8903] = $order;

        $points->allocate_points_for_order(8903);
        $this->assertSame(10, $points->get_points_balance(8903));

        $order->set_status('cancelled');
        $points->reverse_earned_points_on_cancel_or_full_refund(8903);
        $points->reverse_earned_points_on_cancel_or_full_refund(8903);

        $this->assertSame(0, $points->get_points_balance(8903));
    }

    public function testFullRefundReversesEarnedPurchasePoints() {
        $points = new InterSoccer_Points_Manager();
        $order = new WC_Order(8904);
        $order->set_id(8904);
        $order->set_customer_id(8904);
        $order->set_total(50);
        $order->set_status('completed');

        global $mock_wc_orders_by_id;
        $mock_wc_orders_by_id[8904] = $order;

        $points->allocate_points_for_order(8904);
        $order->set_status('refunded');
        $points->reverse_earned_points_on_cancel_or_full_refund(8904);

        $this->assertSame(0, $points->get_points_balance(8904));
    }

    public function testPartialRefundDoesNotReverseEarnedPurchasePoints() {
        $points = new InterSoccer_Points_Manager();
        $order = new WC_Order(8905);
        $order->set_id(8905);
        $order->set_customer_id(8905);
        $order->set_total(100);
        $order->set_status('completed');

        global $mock_wc_orders_by_id;
        $mock_wc_orders_by_id[8905] = $order;

        $points->allocate_points_for_order(8905);
        // A partial refund leaves the order completed. Do not invent a proportional reversal.
        $points->reverse_earned_points_on_cancel_or_full_refund(8905);

        $this->assertSame(10, $points->get_points_balance(8905));
    }
}
