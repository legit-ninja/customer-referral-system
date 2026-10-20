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

    private function campExampleOrder(): WC_Order {
        $order = new WC_Order(1001);
        $order->set_total(472.50);
        return $order;
    }

    private function referrerRewardPoints(WC_Order $order, float $paid_amount, int $referrer_id = 501): int {
        $base = InterSoccer_Referral_Handler::get_referrer_points_base_amount($order, $paid_amount);
        $points_manager = new InterSoccer_Points_Manager();

        return (int) $points_manager->calculate_points_from_order_total(
            $base,
            $referrer_id,
            'referral',
            false
        );
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

    public function testPaidBasisCampExampleYields47Points() {
        $order = $this->campExampleOrder();
        $this->assertSame(47, $this->referrerRewardPoints($order, 472.50));
    }

    public function testFullPriceBasisCampExampleViaFeeMetaYields52Points() {
        $order = $this->campExampleOrder();
        $fee = new WC_Order_Item_Fee('Referral discount', -52.50);
        $fee->add_meta_data('_intersoccer_referral_discount_fee', '1');
        $order->add_item($fee);
        update_option('intersoccer_referrer_points_basis', 'full_price');

        $this->assertSame(52, $this->referrerRewardPoints($order, 472.50));
    }

    public function testFullPriceBasisCampExampleViaOrderMetaYields52Points() {
        $order = $this->campExampleOrder();
        $order->update_meta_data('_intersoccer_first_order_discount_applied', 'yes');
        $order->update_meta_data('_intersoccer_first_order_discount_amount', '52.50');
        update_option('intersoccer_referrer_points_basis', 'full_price');

        $this->assertSame(52, $this->referrerRewardPoints($order, 472.50));
    }

    public function testChangingBasisDoesNotAlterStoredReferrerRewardPoints() {
        $order = $this->campExampleOrder();
        $fee = new WC_Order_Item_Fee('Referral discount', -52.50);
        $fee->add_meta_data('_intersoccer_referral_discount_fee', '1');
        $order->add_item($fee);

        $awarded_points = $this->referrerRewardPoints($order, 472.50);
        $this->assertSame(47, $awarded_points);
        $order->update_meta_data('_intersoccer_referrer_reward_points', $awarded_points);

        update_option('intersoccer_referrer_points_basis', 'full_price');

        $this->assertSame(47, (int) $order->get_meta('_intersoccer_referrer_reward_points'));
        $this->assertSame(52, $this->referrerRewardPoints($order, 472.50));
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
        $this->assertStringContainsString('Full booking price before the friend', $source);
    }
}
