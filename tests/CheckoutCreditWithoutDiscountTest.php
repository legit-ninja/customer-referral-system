<?php

use PHPUnit\Framework\TestCase;
/**
 * Checkout must not take points unless a Credits Applied discount is on the order.
 */

class CheckoutCreditWithoutDiscountTest extends TestCase {
    protected function setUp(): void {
        require_once __DIR__ . "/../includes/class-referral-handler.php";
        parent::setUp();
    }

    protected function tearDown(): void {
        global $mock_user_meta, $mock_current_user_id, $mock_wc_orders_by_id, $mock_post_meta;
        $mock_user_meta = [];
        $mock_current_user_id = 0;
        $mock_wc_orders_by_id = [];
        $mock_post_meta = [];
        unset($_POST['intersoccer_apply_credits']);
        parent::tearDown();
    }

    public function testPostedCreditsDoNotReduceBalanceWhenTheOrderHasNoDiscount() {
        global $mock_user_meta, $mock_current_user_id, $mock_wc_orders_by_id;

        $user_id = 8801;
        $order_id = 88010;
        $mock_current_user_id = $user_id;
        $mock_user_meta[$user_id] = ['intersoccer_points_balance' => 100];
        $order = new WC_Order($order_id);
        $mock_wc_orders_by_id[$order_id] = $order;
        $_POST['intersoccer_apply_credits'] = '40';

        $handler = new InterSoccer_Referral_Handler();
        $handler->update_order_with_credits($order_id);

        $this->assertSame(100, (int) get_user_meta($user_id, 'intersoccer_points_balance', true));
        $this->assertSame('', get_post_meta($order_id, '_intersoccer_credits_used', true));
    }

    public function testPostedCreditsCannotExceedTheDiscountOnTheOrder() {
        global $mock_user_meta, $mock_current_user_id, $mock_wc_orders_by_id;

        $user_id = 8802;
        $order_id = 88020;
        $mock_current_user_id = $user_id;
        $mock_user_meta[$user_id] = ['intersoccer_points_balance' => 100];
        $order = new WC_Order($order_id);
        $order->add_fee(new WC_Order_Item_Fee('Credits Applied', -25));
        $mock_wc_orders_by_id[$order_id] = $order;
        $_POST['intersoccer_apply_credits'] = '40';

        $handler = new InterSoccer_Referral_Handler();
        $handler->update_order_with_credits($order_id);

        $this->assertSame(75, (int) get_user_meta($user_id, 'intersoccer_points_balance', true));
        $this->assertSame(25, (int) get_post_meta($order_id, '_intersoccer_credits_used', true));
    }

    public function testCheckoutMetaHookIsNotRegistered() {
        $source = file_get_contents(__DIR__ . '/../includes/class-referral-handler.php');
        $this->assertDoesNotMatchRegularExpression(
            '/^\s*add_action\(\s*[\x27"]woocommerce_checkout_update_order_meta[\x27"]/m',
            $source,
            'Checkout must not register the credit deduction while the discount fee is off'
        );
    }
}
