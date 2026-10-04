<?php

use PHPUnit\Framework\TestCase;

/**
 * Completion stores referral fields on the order. Readers have to use that,
 * because post meta is empty when order storage does not sync it.
 */
class ReferralOrderMetaReadersTest extends TestCase {
    public function testDashboardReadsReferralFromTheOrderWhenPostMetaDiffers() {
        require_once __DIR__ . '/../includes/class-admin-dashboard.php';

        $order = new WC_Order(76001);
        $order->update_meta_data('_intersoccer_referral_code', 'COACH42');
        $order->update_meta_data('_intersoccer_referring_coach_id', 42);
        update_post_meta(76001, '_intersoccer_referral_code', 'POSTCODE');
        update_post_meta(76001, '_intersoccer_referring_coach_id', 7);

        $dashboard = (new ReflectionClass(InterSoccer_Referral_Admin_Dashboard::class))
            ->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($dashboard, 'resolve_referral_context_for_order');
        $method->setAccessible(true);

        $this->assertSame(
            ['COACH42', 42],
            $method->invoke($dashboard, 76001, $order)
        );
    }

    public function testAdminEligibilityReadUsesOrderMetaWhenPostMetaDiffers() {
        require_once __DIR__ . '/../includes/class-admin-referrals.php';

        global $mock_wc_orders_by_id, $mock_wc_order_override, $mock_wp_json_response, $mock_user_capabilities;
        $saved_orders = $mock_wc_orders_by_id;
        $saved_override = $mock_wc_order_override;
        $saved_json = $mock_wp_json_response;
        $saved_caps = $mock_user_capabilities;
        $saved_post = $_POST;

        $order_id = 76002;
        $order = new WC_Order($order_id);
        $order->update_meta_data('_intersoccer_referral_eligibility', [
            'eligible' => true,
            'reason' => 'from_order',
            'overrides' => [],
        ]);
        update_post_meta($order_id, '_intersoccer_referral_eligibility', [
            'eligible' => false,
            'reason' => 'from_post_meta',
            'overrides' => [],
        ]);
        $mock_wc_order_override = null;
        $mock_wc_orders_by_id[$order_id] = $order;
        $mock_user_capabilities['manage_options'] = true;
        $_POST = [
            'nonce' => 'test',
            'referral_id' => 5,
            'order_id' => $order_id,
            'target_status' => 'ineligible',
            'note' => 'blocked',
        ];

        try {
            (new InterSoccer_Admin_Referrals())->ajax_update_referral_eligibility();
            $stored = $order->get_meta('_intersoccer_referral_eligibility', true);
            $this->assertTrue($mock_wp_json_response['success']);
            $this->assertFalse($stored['eligible']);
            $this->assertSame('manual_block', $stored['reason']);
            $this->assertSame('from_post_meta', get_post_meta($order_id, '_intersoccer_referral_eligibility', true)['reason']);
        } finally {
            $mock_wc_orders_by_id = $saved_orders;
            $mock_wc_order_override = $saved_override;
            $mock_wp_json_response = $saved_json;
            $mock_user_capabilities = $saved_caps;
            $_POST = $saved_post;
        }
    }
}
