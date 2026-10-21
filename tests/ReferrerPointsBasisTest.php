<?php

use PHPUnit\Framework\TestCase;

/**
 * Referrer points basis admin setting and award calculation helpers.
 */
class ReferrerPointsBasisTest extends TestCase {

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/class-referral-handler.php';
        require_once __DIR__ . '/../includes/class-admin-settings.php';
        require_once __DIR__ . '/../includes/class-points-manager.php';

        global $mock_options;
        $mock_options['intersoccer_points_rate_customer_referral'] = 10;
        $mock_options['intersoccer_points_allocation_mode'] = 'ratio';
        unset($mock_options['intersoccer_referrer_points_basis']);
    }

    protected function tearDown(): void {
        global $mock_options, $mock_registered_settings;
        unset($mock_options['intersoccer_referrer_points_basis']);
        $mock_registered_settings = [];
        parent::tearDown();
    }

    private function pointsManager(): InterSoccer_Points_Manager {
        return new InterSoccer_Points_Manager();
    }

    private function paidExVat(WC_Order $order): float {
        return $this->pointsManager()->get_order_net_rewardable_amount_ex_vat($order);
    }

    private function setLineItemDiscountMeta(WC_Order_Item $line, array $entries): void {
        $total = 0.0;
        foreach ($entries as $entry) {
            $total += (float) ($entry['amount'] ?? 0);
        }
        $line->add_meta_data('_intersoccer_item_discounts', $entries);
        $line->add_meta_data('_intersoccer_total_item_discount', $total);
    }

    private function fullPriceBase(WC_Order $order): float {
        update_option('intersoccer_referrer_points_basis', 'full_price');
        $paid = $this->paidExVat($order);

        return InterSoccer_Referral_Handler::get_referrer_points_base_amount($order, $paid);
    }

    private function referrerPointsForPaidBasis(WC_Order $order): int {
        $points_manager = $this->pointsManager();
        $paid = $this->paidExVat($order);
        $base = InterSoccer_Referral_Handler::get_referrer_points_base_amount($order, $paid);

        return (int) $points_manager->calculate_points_from_order_total($base, 501, 'referral', false);
    }

    private function referrerPointsForFullPriceBasis(WC_Order $order): int {
        $points_manager = $this->pointsManager();
        $paid = $this->paidExVat($order);
        $base = $this->fullPriceBase($order);

        return (int) $points_manager->calculate_points_from_order_total($base, 501, 'referral', false);
    }

    private function campReferralDiscountOrder(): WC_Order {
        $order = new WC_Order(1001);
        $line = new WC_Order_Item_Product();
        $line->set_subtotal(525);
        $line->set_total(525);
        $this->setLineItemDiscountMeta($line, [
            [
                'name' => 'Referral',
                'type' => 'referral_first_order',
                'amount' => 52.50,
                'source' => 'referral_order_meta',
                'allocation_method' => 'proportional',
            ],
        ]);
        $order->add_item($line);
        $order->add_item(new WC_Order_Item_Fee('Referral discount', -52.50));
        $order->set_total(472.50);
        $order->set_tax_total(0);
        return $order;
    }

    private function stackedSiblingReferralCouponOrder(): WC_Order {
        $order = new WC_Order(1002);
        $line_one = new WC_Order_Item_Product();
        $line_one->set_subtotal(525);
        $line_one->set_total(525);
        $this->setLineItemDiscountMeta($line_one, [
            [
                'name' => 'Coupon',
                'type' => 'coupon',
                'amount' => 52.50,
                'source' => 'woocommerce_coupon',
                'allocation_method' => 'proportional',
            ],
            [
                'name' => 'Referral',
                'type' => 'referral_first_order',
                'amount' => 52.50,
                'source' => 'referral_order_meta',
                'allocation_method' => 'proportional',
            ],
        ]);
        $order->add_item($line_one);

        $line_two = new WC_Order_Item_Product();
        $line_two->set_subtotal(420);
        $line_two->set_total(420);
        $this->setLineItemDiscountMeta($line_two, [
            [
                'name' => 'Coupon',
                'type' => 'coupon',
                'amount' => 42.00,
                'source' => 'woocommerce_coupon',
                'allocation_method' => 'proportional',
            ],
            [
                'name' => 'Referral',
                'type' => 'referral_first_order',
                'amount' => 42.00,
                'source' => 'referral_order_meta',
                'allocation_method' => 'proportional',
            ],
            [
                'name' => 'Sibling',
                'type' => 'sibling',
                'amount' => 105,
                'source' => 'legacy_item_meta',
                'allocation_method' => 'direct',
            ],
        ]);
        $order->add_item($line_two);

        $order->set_total(756.00);
        $order->set_tax_total(0);
        return $order;
    }

    private function stackedSiblingReferralCouponWithPointsOrder(): WC_Order {
        $order = $this->stackedSiblingReferralCouponOrder();
        $points_share_line_one = round(20 * 525 / 945, 2);
        $points_share_line_two = round(20 - $points_share_line_one, 2);

        $items = array_values($order->get_items('line_item'));
        $line_one = $items[0];
        $line_two = $items[1];

        $entries_one = $line_one->get_meta('_intersoccer_item_discounts', true);
        if (!is_array($entries_one)) {
            $entries_one = [];
        }
        $entries_one[] = [
            'name' => 'Points',
            'type' => 'referral_points',
            'amount' => $points_share_line_one,
            'source' => 'order_fee',
            'allocation_method' => 'proportional',
        ];
        $this->setLineItemDiscountMeta($line_one, $entries_one);

        $entries_two = $line_two->get_meta('_intersoccer_item_discounts', true);
        if (!is_array($entries_two)) {
            $entries_two = [];
        }
        $entries_two[] = [
            'name' => 'Points',
            'type' => 'referral_points',
            'amount' => $points_share_line_two,
            'source' => 'order_fee',
            'allocation_method' => 'proportional',
        ];
        $this->setLineItemDiscountMeta($line_two, $entries_two);

        $order->add_item(new WC_Order_Item_Fee('Points redemption', -20));
        $order->set_total(736.00);
        $order->set_tax_total(0);
        return $order;
    }

    public function testGetReferrerPointsBasisDefaultsToPaidWhenUnset() {
        $this->assertSame('paid', InterSoccer_Referral_Handler::get_referrer_points_basis());
    }

    public function testGetReferrerPointsBasisUnknownValueFallsBackToPaid() {
        update_option('intersoccer_referrer_points_basis', 'invalid');
        $this->assertSame('paid', InterSoccer_Referral_Handler::get_referrer_points_basis());
    }

    public function testSanitizeReferrerPointsBasisOptionWhitelists() {
        $settings = InterSoccer_Admin_Settings::get_instance();

        $this->assertSame('paid', $settings->sanitize_referrer_points_basis_option('paid'));
        $this->assertSame('full_price', $settings->sanitize_referrer_points_basis_option('full_price'));
        $this->assertSame('paid', $settings->sanitize_referrer_points_basis_option('other'));
        $this->assertSame('paid', $settings->sanitize_referrer_points_basis_option(null));
    }

    public function testCampReferralMetaDoesNotDoubleCountFullPriceBase() {
        $order = $this->campReferralDiscountOrder();

        $this->assertSame(47, $this->referrerPointsForPaidBasis($order));
        $this->assertSame(52, $this->referrerPointsForFullPriceBasis($order));
        $this->assertSame(525.0, $this->fullPriceBase($order));
    }

    public function testStackedSiblingReferralCouponPaid75FullPrice105() {
        $order = $this->stackedSiblingReferralCouponOrder();

        $this->assertSame(75, $this->referrerPointsForPaidBasis($order));
        $this->assertSame(105, $this->referrerPointsForFullPriceBasis($order));
        $this->assertSame(1050.0, $this->fullPriceBase($order));
    }

    public function testStackedOrderWithPointsRedemptionPaid73FullPrice105() {
        $order = $this->stackedSiblingReferralCouponWithPointsOrder();

        $this->assertSame(73, $this->referrerPointsForPaidBasis($order));
        $this->assertSame(105, $this->referrerPointsForFullPriceBasis($order));
        $this->assertSame(1050.0, $this->fullPriceBase($order));
    }

    public function testOrderFeeSiblingDiscountNotAddedBack() {
        $order = new WC_Order(1003);
        $line = new WC_Order_Item_Product();
        $line->set_subtotal(420);
        $this->setLineItemDiscountMeta($line, [
            [
                'name' => 'Sibling fee',
                'type' => 'sibling',
                'amount' => 105,
                'source' => 'order_fee',
                'allocation_method' => 'proportional',
            ],
        ]);
        $order->add_item($line);
        $order->set_total(420);
        $order->set_tax_total(0);

        $this->assertSame(420.0, $this->fullPriceBase($order));
    }

    public function testTotalItemDiscountWithoutItemDiscountsListAddsNothingBack() {
        $order = new WC_Order(1004);
        $line = new WC_Order_Item_Product();
        $line->set_subtotal(525);
        $line->add_meta_data('_intersoccer_total_item_discount', 52.50);
        $order->add_item($line);
        $order->set_total(472.50);
        $order->set_tax_total(0);

        $this->assertSame(525.0, $this->fullPriceBase($order));
    }

    public function testPvCampSiblingCartLineDiscountAddedBack() {
        $order = new WC_Order(1005);
        $line = new WC_Order_Item_Product();
        $line->set_subtotal(420);
        $this->setLineItemDiscountMeta($line, [
            [
                'name' => 'Sibling',
                'type' => 'camp_sibling',
                'amount' => 105,
                'allocation_method' => 'cart_line',
            ],
        ]);
        $order->add_item($line);
        $order->set_total(420);
        $order->set_tax_total(0);

        $this->assertSame(525.0, $this->fullPriceBase($order));
    }

    public function testPaidModeReturnsPaidAmountUnchanged() {
        $order = $this->stackedSiblingReferralCouponOrder();
        $paid = $this->paidExVat($order);

        $this->assertSame($paid, InterSoccer_Referral_Handler::get_referrer_points_base_amount($order, $paid));

        update_option('intersoccer_referrer_points_basis', 'paid');
        $this->assertSame($paid, InterSoccer_Referral_Handler::get_referrer_points_base_amount($order, $paid));
    }

    public function testPreCompletionRefundReducesFullPriceBasis() {
        $order = $this->stackedSiblingReferralCouponOrder();
        $order->add_refund(new WC_Order_Refund(1, 100));

        $this->assertSame(95, $this->referrerPointsForFullPriceBasis($order));
        $this->assertSame(950.0, $this->fullPriceBase($order));
    }

    public function testChangingBasisDoesNotAlterStoredReferrerRewardPoints() {
        $order = $this->campReferralDiscountOrder();

        $awarded_points = $this->referrerPointsForPaidBasis($order);
        $this->assertSame(47, $awarded_points);
        $order->update_meta_data('_intersoccer_referrer_reward_points', $awarded_points);

        update_option('intersoccer_referrer_points_basis', 'full_price');

        $this->assertSame(47, (int) $order->get_meta('_intersoccer_referrer_reward_points'));
        $this->assertSame(52, $this->referrerPointsForFullPriceBasis($order));
    }

    public function testReferrerPointsBasisSettingRegisteredInGeneralSettings() {
        global $mock_registered_settings;
        $mock_registered_settings = [];

        InterSoccer_Admin_Settings::get_instance()->register_settings();

        $this->assertArrayHasKey('intersoccer_settings', $mock_registered_settings);
        $this->assertArrayHasKey('intersoccer_referrer_points_basis', $mock_registered_settings['intersoccer_settings']);
        $this->assertSame('paid', $mock_registered_settings['intersoccer_settings']['intersoccer_referrer_points_basis']['default']);
        $this->assertSame(
            [InterSoccer_Admin_Settings::get_instance(), 'sanitize_referrer_points_basis_option'],
            $mock_registered_settings['intersoccer_settings']['intersoccer_referrer_points_basis']['sanitize_callback']
        );
    }

    public function testReferrerPointsBasisSettingsRowInAdminSource() {
        $source = file_get_contents(__DIR__ . '/../includes/class-admin-settings.php');
        $this->assertIsString($source);
        $this->assertStringContainsString('intersoccer_referrer_points_basis', $source);
        $this->assertStringContainsString('Referrer points basis', $source);
        $this->assertStringContainsString('Amount the friend paid after the discount', $source);
        $this->assertStringContainsString('value="full_price"', $source);
        $this->assertStringContainsString('Full booking price before any discounts', $source);
    }
}
