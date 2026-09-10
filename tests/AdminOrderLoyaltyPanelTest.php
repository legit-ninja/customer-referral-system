<?php

use PHPUnit\Framework\TestCase;

/**
 * Order-edit loyalty panel and Customer Points deep-link helpers.
 */
class AdminOrderLoyaltyPanelTest extends TestCase {

    protected function setUp(): void {
        global $mock_user_capabilities, $mock_user_meta, $mock_users, $mock_wp_json_response, $mock_points_balances;

        require_once __DIR__ . '/../includes/class-points-manager.php';
        require_once __DIR__ . '/../includes/class-admin-points.php';
        $this->resetPointsManagerSingleton();

        $mock_user_capabilities = [
            'manage_options' => true,
            'manage_woocommerce' => true,
        ];
        $mock_user_meta = [];
        $mock_users = [];
        $mock_wp_json_response = null;
        $mock_points_balances = [];
        $_GET = [];
        $_POST = [];
    }

    protected function tearDown(): void {
        global $mock_user_capabilities, $mock_user_meta, $mock_users, $mock_wp_json_response, $mock_points_balances;

        $this->resetPointsManagerSingleton();
        $mock_user_capabilities = [];
        $mock_user_meta = [];
        $mock_users = [];
        $mock_wp_json_response = null;
        $mock_points_balances = [];
        $_GET = [];
        $_POST = [];
        parent::tearDown();
    }

    private function resetPointsManagerSingleton(): void {
        if (!class_exists('InterSoccer_Points_Manager', false)) {
            return;
        }

        $reflection = new ReflectionClass(InterSoccer_Points_Manager::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    public function testUserCanManagePoints_EitherCap() {
        global $mock_user_capabilities;

        $mock_user_capabilities = ['manage_options' => true, 'manage_woocommerce' => false];
        $this->assertTrue(InterSoccer_Admin_Points::user_can_manage_points());

        $mock_user_capabilities = ['manage_options' => false, 'manage_woocommerce' => true];
        $this->assertTrue(InterSoccer_Admin_Points::user_can_manage_points());

        $mock_user_capabilities = ['manage_options' => false, 'manage_woocommerce' => false];
        $this->assertFalse(InterSoccer_Admin_Points::user_can_manage_points());
    }

    public function testCustomerPointsUrlContainsUserAndFocus() {
        $adjust = InterSoccer_Admin_Points::get_customer_points_url(45941, 'adjust');
        $history = InterSoccer_Admin_Points::get_customer_points_url(45941, 'history');

        $this->assertStringContainsString('page=intersoccer-customer-points', $adjust);
        $this->assertStringContainsString('user_id=45941', $adjust);
        $this->assertStringContainsString('focus=adjust', $adjust);
        $this->assertStringContainsString('focus=history', $history);
    }

    public function testOrderPanel_RegisteredCustomerShowsBalanceAndLinks() {
        global $mock_user_meta, $mock_user_capabilities;

        $mock_user_capabilities = ['manage_options' => true, 'manage_woocommerce' => true];
        $mock_user_meta[99]['intersoccer_points_balance'] = 42;

        $order = new WC_Order(10);
        $order->set_customer_id(99);

        $admin = new InterSoccer_Admin_Points();
        $html = $admin->get_order_loyalty_panel_html($order);

        $this->assertStringContainsString('intersoccer-order-loyalty-points', $html);
        $this->assertStringContainsString('42', $html);
        $this->assertStringContainsString('class="button adjust-points"', $html);
        $this->assertStringContainsString('class="button view-history"', $html);
        $this->assertStringContainsString('data-user-id="99"', $html);
        $this->assertStringNotContainsString('focus=adjust', $html);
        $this->assertStringNotContainsString('page=intersoccer-customer-points', $html);
        $this->assertStringNotContainsString('Redeemed on this order', $html);
    }

    public function testOrderPanel_GuestHasNoAdjustLinks() {
        $order = new WC_Order(11);
        $order->set_customer_id(0);

        $admin = new InterSoccer_Admin_Points();
        $html = $admin->get_order_loyalty_panel_html($order);

        $this->assertStringContainsString('no linked customer account', $html);
        $this->assertStringNotContainsString('adjust-points', $html);
        $this->assertStringNotContainsString('view-history', $html);
    }

    public function testOrderPanel_ShopManagerSeesLinksWithoutManageOptions() {
        global $mock_user_capabilities, $mock_user_meta;

        $mock_user_capabilities = ['manage_options' => false, 'manage_woocommerce' => true];
        $mock_user_meta[5]['intersoccer_points_balance'] = 7;

        $order = new WC_Order(12);
        $order->set_customer_id(5);

        $html = (new InterSoccer_Admin_Points())->get_order_loyalty_panel_html($order);
        $this->assertStringContainsString('class="button adjust-points"', $html);
        $this->assertStringContainsString('data-user-id="5"', $html);
    }

    public function testOrderPanel_NoLinksWithoutPointsCaps() {
        global $mock_user_capabilities, $mock_user_meta;

        $mock_user_capabilities = ['manage_options' => false, 'manage_woocommerce' => false];
        $mock_user_meta[5]['intersoccer_points_balance'] = 7;

        $order = new WC_Order(13);
        $order->set_customer_id(5);

        $html = (new InterSoccer_Admin_Points())->get_order_loyalty_panel_html($order);
        $this->assertStringContainsString('7', $html);
        $this->assertStringNotContainsString('adjust-points', $html);
        $this->assertStringNotContainsString('view-history', $html);
    }

    public function testPointsModalsHtmlHasExpectedIds() {
        $html = (new InterSoccer_Admin_Points())->get_points_modals_html();
        $this->assertStringContainsString('id="points-adjustment-modal"', $html);
        $this->assertStringContainsString('id="points-history-modal"', $html);
        $this->assertStringContainsString('id="points-adjustment-form"', $html);
    }

    public function testOrderPanel_ShowsRedeemedWhenMetaPositive() {
        global $mock_user_meta;

        $mock_user_meta[8]['intersoccer_points_balance'] = 3;
        $order = new WC_Order(14);
        $order->set_customer_id(8);
        $order->update_meta_data('_intersoccer_points_redeemed', 12);

        $html = (new InterSoccer_Admin_Points())->get_order_loyalty_panel_html($order);
        $this->assertStringContainsString('Redeemed on this order: 12', $html);
    }

    public function testFocusBannerEscapesHtmlInNameAndEmail() {
        $html = InterSoccer_Admin_Points::get_focus_banner_html([
            'id' => 22,
            'name' => '<script>alert(1)</script>',
            'email' => 'a"<img src=x>',
            'balance' => 1,
        ]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function testAdjustAjaxUnauthorizedWithoutPointsCaps() {
        global $mock_user_capabilities, $mock_wp_json_response;

        $mock_user_capabilities = ['manage_options' => false, 'manage_woocommerce' => false];
        $_POST = [
            'nonce' => 'test',
            'user_id' => 1,
            'adjustment_type' => 'add',
            'points_amount' => '5',
            'reason' => 'test',
        ];

        (new InterSoccer_Admin_Points())->adjust_user_points_ajax();

        $this->assertIsArray($mock_wp_json_response);
        $this->assertFalse($mock_wp_json_response['success']);
        $this->assertSame('Unauthorized', $mock_wp_json_response['data']['message']);
    }

    public function testGetPointsHistoryAjaxUnauthorizedWithoutPointsCaps() {
        global $mock_user_capabilities, $mock_wp_json_response;

        $mock_user_capabilities = ['manage_options' => false, 'manage_woocommerce' => false];
        $_POST = [
            'nonce' => 'test',
            'customer_id' => 44500,
        ];

        (new InterSoccer_Points_Manager())->get_points_history_ajax();

        $this->assertIsArray($mock_wp_json_response);
        $this->assertFalse($mock_wp_json_response['success']);
        $this->assertSame('Unauthorized', $mock_wp_json_response['data']['message']);
    }
}
