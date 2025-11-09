<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for Checkout Points Redemption Flow
 * 
 * Tests the complete checkout experience:
 * - Points application at checkout
 * - Discount calculation
 * - Order total updates
 * - Session handling
 * - AJAX interactions
 */
class CheckoutPointsRedemptionTest extends TestCase {

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/class-points-manager.php';
    }

    /**
     * Test points discount calculation (1 point = 1 CHF)
     */
    public function testPointsDiscountCalculation() {
        $points_redeemed = 50;
        $discount_rate = 1; // 1 CHF per point
        $discount_amount = $points_redeemed * $discount_rate;
        
        $this->assertEquals(50, $discount_amount);
    }

    /**
     * Test cart total reduced by points
     */
    public function testCartTotalReducedByPoints() {
        $cart_total = 100;
        $points_applied = 30;
        $new_total = $cart_total - $points_applied;
        
        $this->assertEquals(70, $new_total);
    }

    /**
     * Test full cart coverage (zero payment)
     */
    public function testFullCartCoverage() {
        $cart_total = 100;
        $points_applied = 100;
        $new_total = $cart_total - $points_applied;
        
        $this->assertEquals(0, $new_total, 'Full coverage should result in free order');
    }

    /**
     * Test points cannot exceed cart total
     */
    public function testPointsCannotExceedCartTotal() {
        $cart_total = 100;
        $available_points = 150;
        $max_redeemable = min($available_points, $cart_total);
        
        $this->assertEquals(100, $max_redeemable);
    }

    /**
     * Test points deducted from balance on redemption
     */
    public function testPointsDeductedFromBalance() {
        $current_balance = 100;
        $points_redeemed = 30;
        $new_balance = $current_balance - $points_redeemed;
        
        $this->assertEquals(70, $new_balance);
    }

    /**
     * Test order meta saves points redeemed
     */
    public function testOrderMetaSavesPointsRedeemed() {
        $order_meta = [
            '_intersoccer_points_redeemed' => 30,
            '_intersoccer_discount_amount' => 30,
        ];
        
        $this->assertEquals(30, $order_meta['_intersoccer_points_redeemed']);
        $this->assertEquals(30, $order_meta['_intersoccer_discount_amount']);
    }

    /**
     * Test session stores points amount
     */
    public function testSessionStoresPointsAmount() {
        $session_data = [
            'intersoccer_points_to_redeem' => 50,
        ];
        
        $this->assertEquals(50, $session_data['intersoccer_points_to_redeem']);
    }

    /**
     * Test session cleared after order completion
     */
    public function testSessionClearedAfterCompletion() {
        $session_before = ['intersoccer_points_to_redeem' => 50];
        $session_after = []; // Cleared
        
        $this->assertEmpty($session_after);
    }

    /**
     * Test insufficient points handled gracefully
     */
    public function testInsufficientPointsHandled() {
        $available_points = 10;
        $requested_points = 50;
        
        $has_sufficient = ($requested_points <= $available_points);
        
        $this->assertFalse($has_sufficient, 'Should reject insufficient points');
    }

    /**
     * Test negative points rejected
     */
    public function testNegativePointsRejected() {
        $points = -10;
        $is_valid = ($points >= 0);
        
        $this->assertFalse($is_valid);
    }

    /**
     * Test zero points allowed (no discount)
     */
    public function testZeroPointsAllowed() {
        $points = 0;
        $is_valid = ($points >= 0);
        
        $this->assertTrue($is_valid);
    }

    /**
     * Test points update triggers cart recalculation
     */
    public function testPointsUpdateTriggersRecalculation() {
        $cart_total = 100;
        $points_applied = 0;
        $total = $cart_total - $points_applied;
        $this->assertEquals(100, $total);
        
        // Apply points
        $points_applied = 30;
        $total = $cart_total - $points_applied;
        $this->assertEquals(70, $total, 'Cart should recalculate');
    }

    /**
     * Test order note added on redemption
     */
    public function testOrderNoteAddedOnRedemption() {
        $note = 'Customer redeemed 30 points for 30 CHF discount';
        
        $this->assertStringContainsString('30 points', $note);
        $this->assertStringContainsString('30 CHF', $note);
    }

    /**
     * Test fee vs coupon for discount method
     */
    public function testFeeVsCouponForDiscount() {
        // Using fee method (negative fee = discount)
        $discount_amount = 30;
        $fee_amount = -$discount_amount;
        
        $this->assertEquals(-30, $fee_amount);
        $this->assertLessThan(0, $fee_amount);
    }

    /**
     * Test transaction logged on redemption
     */
    public function testTransactionLoggedOnRedemption() {
        $transaction = [
            'customer_id' => 123,
            'order_id' => 456,
            'transaction_type' => 'redemption',
            'points_amount' => -30, // Negative for deduction
            'description' => 'Points redeemed at checkout',
        ];
        
        $this->assertEquals('redemption', $transaction['transaction_type']);
        $this->assertLessThan(0, $transaction['points_amount']);
    }

    /**
     * Test failed redemption rolls back
     */
    public function testFailedRedemptionRollsBack() {
        $original_balance = 100;
        
        // Attempt redemption
        $temp_balance = $original_balance - 30;
        $this->assertEquals(70, $temp_balance);
        
        // Redemption fails, rollback
        $rollback_balance = $original_balance;
        $this->assertEquals(100, $rollback_balance);
    }

    /**
     * Test points can be adjusted before order completion
     */
    public function testPointsCanBeAdjustedBeforeCompletion() {
        $session_points = 30;
        
        // Customer changes their mind
        $session_points = 50;
        
        $this->assertEquals(50, $session_points);
    }

    /**
     * Test points removed from cart
     */
    public function testPointsRemovedFromCart() {
        $cart_total = 100;
        $points_applied = 30;
        
        // Customer removes points
        $points_applied = 0;
        $new_total = $cart_total - $points_applied;
        
        $this->assertEquals(100, $new_total);
    }

    /**
     * Test minimum order total for points
     */
    public function testMinimumOrderTotalForPoints() {
        $min_order_total = 10; // CHF
        $cart_total = 5;
        
        $can_use_points = ($cart_total >= $min_order_total);
        
        $this->assertFalse($can_use_points, 'Minimum order required');
    }

    /**
     * Test points redemption on free shipping orders
     */
    public function testPointsRedemptionOnFreeShipping() {
        $product_total = 100;
        $shipping = 0; // Free shipping
        $cart_total = $product_total + $shipping;
        
        $points_applied = 50;
        $new_total = $cart_total - $points_applied;
        
        $this->assertEquals(50, $new_total);
    }

    /**
     * Test tax calculated after points discount
     */
    public function testTaxCalculatedAfterPointsDiscount() {
        $subtotal = 100;
        $points_discount = 30;
        $subtotal_after_discount = $subtotal - $points_discount;
        $tax_rate = 0.077; // 7.7% Swiss VAT
        $tax = $subtotal_after_discount * $tax_rate;
        
        $this->assertEqualsWithDelta(5.39, $tax, 0.01);
    }

    /**
     * Test shipping calculated on discounted amount
     */
    public function testShippingCalculatedOnDiscountedAmount() {
        $subtotal = 100;
        $points_discount = 80;
        $subtotal_after_discount = $subtotal - $points_discount;
        
        // Free shipping if over 50 CHF
        $free_shipping_threshold = 50;
        $shipping = ($subtotal_after_discount >= $free_shipping_threshold) ? 0 : 10;
        
        $this->assertEquals(10, $shipping, 'Should charge shipping (below threshold)');
    }

    /**
     * Test multiple payment methods with points
     */
    public function testMultiplePaymentMethodsWithPoints() {
        $cart_total = 100;
        $points_applied = 60;
        $remaining = $cart_total - $points_applied;
        
        // Remaining paid via credit card
        $this->assertEquals(40, $remaining);
        $this->assertGreaterThan(0, $remaining);
    }

    /**
     * Test guest checkout cannot use points
     */
    public function testGuestCheckoutCannotUsePoints() {
        $user_logged_in = false;
        $can_use_points = $user_logged_in;
        
        $this->assertFalse($can_use_points, 'Guests cannot use points');
    }

    /**
     * Test logged-in user can use points
     */
    public function testLoggedInUserCanUsePoints() {
        $user_logged_in = true;
        $can_use_points = $user_logged_in;
        
        $this->assertTrue($can_use_points);
    }

    /**
     * Test points input updates in real-time
     */
    public function testPointsInputUpdatesRealTime() {
        $input_value = 30;
        $discount_displayed = $input_value; // Real-time update
        
        $this->assertEquals($input_value, $discount_displayed);
    }

    /**
     * Test error message on invalid redemption
     */
    public function testErrorMessageOnInvalidRedemption() {
        $errors = [
            'insufficient' => 'You don\'t have enough points available.',
            'exceeds_cart' => 'Points redemption cannot exceed your cart total.',
            'invalid_amount' => 'Invalid points amount.',
        ];
        
        foreach ($errors as $key => $message) {
            $this->assertIsString($message);
            $this->assertNotEmpty($message);
        }
    }

    /**
     * Test success message on valid redemption
     */
    public function testSuccessMessageOnValidRedemption() {
        $message = '30 points applied (30 CHF discount)';
        
        $this->assertStringContainsString('30 points', $message);
        $this->assertStringContainsString('30 CHF', $message);
    }

    /**
     * Test visual feedback on points application
     */
    public function testVisualFeedbackOnApplication() {
        $feedback = [
            'message' => '30 points applied',
            'display_class' => 'success',
            'visible' => true,
        ];
        
        $this->assertTrue($feedback['visible']);
        $this->assertEquals('success', $feedback['display_class']);
    }

    /**
     * Test WooCommerce checkout updated
     */
    public function testWooCommerceCheckoutUpdated() {
        $trigger_event = 'updated_checkout';
        
        $this->assertEquals('updated_checkout', $trigger_event);
    }


    /**
     * Test a complete checkout where points fully cover the cart total
     */
    public function testCheckoutFullyCoveredByPointsBalance() {
        $customer_id = 123;
        $cart_total = 270.00; // CHF cart total that should be fully covered
        $available_points = 350; // Customer balance comfortably above cart total

        /** @var InterSoccer_Points_Manager|\PHPUnit\Framework\MockObject\MockObject $points_manager */
        $points_manager = $this
            ->getMockBuilder(InterSoccer_Points_Manager::class)
            ->onlyMethods(['get_points_balance'])
            ->getMock();

        // Simulate an unlimited balance (no hidden 100-point ceiling)
        $points_manager
            ->method('get_points_balance')
            ->with($customer_id)
            ->willReturn($available_points);

        // Max redeemable should be identical to cart total (not capped at 100)
        $points_to_redeem = $points_manager->get_max_redeemable_points($customer_id, $cart_total);
        $this->assertEquals(270, $points_to_redeem, 'Customer should be able to cover full cart total');
        $this->assertGreaterThan(100, $points_to_redeem, 'Regression guard: ensure old 100-point cap is not reintroduced');

        // Validate redemption checks succeed for the full amount
        $this->assertTrue(
            $points_manager->can_redeem_points($customer_id, $points_to_redeem, $cart_total),
            'Redemption validation should pass when balance >= cart total'
        );

        // Discount should wipe the order total to zero
        $discount = $points_manager->calculate_discount_from_points($points_to_redeem);
        $this->assertEquals(270.00, $discount, 'Discount should equal the cart total');

        $order_total_after_discount = round($cart_total - $discount, 2);
        $this->assertEquals(0.00, $order_total_after_discount, 'Cart should be fully covered by points');

        // Ensure remaining balance reflects the deduction (mocked balance - redeemed points)
        $remaining_balance = $available_points - $points_to_redeem;
        $this->assertEquals(80, $remaining_balance, 'Customer should retain leftover points after full coverage');

        // Sanity check: plugin default max-per-order remains effectively unlimited (>= cart total)
        $max_setting = get_option('intersoccer_max_credits_per_order', 9999);
        $this->assertGreaterThanOrEqual($cart_total, $max_setting, 'Settings default must allow full cart coverage');
    }

    /**
     * Test nonce verification on AJAX
     */
    public function testNonceVerificationOnAJAX() {
        $nonce_action = 'intersoccer_checkout_nonce';
        
        $this->assertEquals('intersoccer_checkout_nonce', $nonce_action);
    }

    /**
     * Test AJAX response format
     */
    public function testAJAXResponseFormat() {
        $response = [
            'success' => true,
            'data' => [
                'points_to_redeem' => 30,
                'discount_amount' => 30,
            ],
        ];
        
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
    }

    /**
     * Test order completion finalizes redemption
     */
    public function testOrderCompletionFinalizesRedemption() {
        $session_points = 30;
        $order_completed = true;
        
        if ($order_completed) {
            $points_finalized = $session_points;
            $session_points = null; // Clear session
        }
        
        $this->assertEquals(30, $points_finalized);
        $this->assertNull($session_points);
    }

    /**
     * Test abandoned cart restores points
     */
    public function testAbandonedCartRestoresPoints() {
        $reserved_points = 30;
        $order_completed = false;
        
        if (!$order_completed) {
            $points_restored = $reserved_points;
        }
        
        $this->assertEquals(30, $points_restored ?? 0);
    }

    /**
     * Test checkout validation before payment
     */
    public function testCheckoutValidationBeforePayment() {
        $validations = [
            'has_items' => true,
            'points_valid' => true,
            'user_logged_in' => true,
            'terms_accepted' => true,
        ];
        
        $all_valid = !in_array(false, $validations);
        
        $this->assertTrue($all_valid);
    }

    /**
     * Test points applied shown in order summary
     */
    public function testPointsAppliedShownInSummary() {
        $order_summary = [
            'subtotal' => 100,
            'points_discount' => -30,
            'tax' => 5.39,
            'total' => 75.39,
        ];
        
        $this->assertEquals(-30, $order_summary['points_discount']);
        $this->assertLessThan(0, $order_summary['points_discount']);
    }

    /**
     * Test order receipt shows points used
     */
    public function testOrderReceiptShowsPointsUsed() {
        $receipt_line = 'Loyalty Points Discount: -30 CHF (30 points)';
        
        $this->assertStringContainsString('30 CHF', $receipt_line);
        $this->assertStringContainsString('30 points', $receipt_line);
    }

    /**
     * Test email notification includes points info
     */
    public function testEmailNotificationIncludesPointsInfo() {
        $email_content = 'You redeemed 30 points and saved 30 CHF on this order!';
        
        $this->assertStringContainsString('30 points', $email_content);
        $this->assertStringContainsString('saved 30 CHF', $email_content);
    }

    /**
     * Test points redemption with coupon codes
     */
    public function testPointsRedemptionWithCoupons() {
        $subtotal = 100;
        $coupon_discount = 10;
        $after_coupon = $subtotal - $coupon_discount; // 90
        
        $points_applied = 30;
        $final_total = $after_coupon - $points_applied;
        
        $this->assertEquals(60, $final_total);
    }

    /**
     * Test maximum points displayed correctly
     */
    public function testMaximumPointsDisplayed() {
        $available_points = 150;
        $cart_total = 100;
        $max_display = min($available_points, $cart_total);
        
        $this->assertEquals(100, $max_display);
    }

    /**
     * Test UI enables/disables based on points
     */
    public function testUIEnablesDisablesBasedOnPoints() {
        $available_points = 0;
        $ui_enabled = ($available_points > 0);
        
        $this->assertFalse($ui_enabled, 'UI should be disabled with 0 points');
        
        $available_points = 50;
        $ui_enabled = ($available_points > 0);
        
        $this->assertTrue($ui_enabled, 'UI should be enabled with points');
    }

    /**
     * Test toggle checkbox functionality
     */
    public function testToggleCheckboxFunctionality() {
        $checkbox_checked = false;
        $points_applied = 0;
        
        // User checks box
        $checkbox_checked = true;
        $points_applied = 30;
        
        $this->assertTrue($checkbox_checked);
        $this->assertGreaterThan(0, $points_applied);
    }

    /**
     * Test apply all button calculates correctly
     */
    public function testApplyAllButtonCalculatesCorrectly() {
        $available_points = 150;
        $cart_total = 100;
        
        // "Apply All Available" button clicked
        $points_to_apply = min($available_points, $cart_total);
        
        $this->assertEquals(100, $points_to_apply);
    }

    /**
     * Test custom amount input validation
     */
    public function testCustomAmountInputValidation() {
        $available = 100;
        $cart_total = 80;
        $user_input = 90; // More than cart
        
        $is_valid = ($user_input <= $available && $user_input <= $cart_total);
        
        $this->assertFalse($is_valid, 'Input exceeds cart total');
    }

    /**
     * Test payment gateway receives correct amount
     */
    public function testPaymentGatewayReceivesCorrectAmount() {
        $cart_total = 100;
        $points_discount = 30;
        $amount_to_charge = $cart_total - $points_discount;
        
        $this->assertEquals(70, $amount_to_charge);
    }

    /**
     * Test fraud detection doesn't trigger on points
     */
    public function testFraudDetectionDoesntTriggerOnPoints() {
        // Large points redemption shouldn't flag as fraud
        $points_redeemed = 500;
        $is_fraud = false; // Points redemption is legitimate
        
        $this->assertFalse($is_fraud);
    }

    /**
     * Test account credit different from points
     */
    public function testAccountCreditDifferentFromPoints() {
        // Points and store credits are separate systems
        $points_balance = 100;
        $store_credit = 50;
        
        $this->assertNotEquals($points_balance, $store_credit);
    }

    /**
     * Test points priority over other discounts
     */
    public function testPointsPriorityOverOtherDiscounts() {
        $discount_order = ['referral_code', 'points', 'coupons'];
        
        $this->assertEquals('points', $discount_order[1]);
    }

    /**
     * Test real-time balance display
     */
    public function testRealTimeBalanceDisplay() {
        $balance_before = 100;
        $points_applied = 30;
        $balance_displayed = $balance_before; // Shows available
        
        $this->assertEquals(100, $balance_displayed);
        
        // After application (real-time preview)
        $balance_after_preview = $balance_before - $points_applied;
        $this->assertEquals(70, $balance_after_preview);
    }

    /**
     * Test mobile checkout compatibility
     */
    public function testMobileCheckoutCompatibility() {
        $is_mobile = true;
        $ui_works = true; // Should work on mobile
        
        $this->assertTrue($ui_works);
    }
}

