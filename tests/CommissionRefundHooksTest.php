<?php

use PHPUnit\Framework\TestCase;

/**
 * Commission manager must register refund hooks at plugin load, not only after first completed order.
 */
class CommissionRefundHooksTest extends TestCase {

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/intersoccer-refund-helpers.php';
        require_once __DIR__ . '/../includes/class-commission-manager.php';
        parent::setUp();
        global $mock_wp_action_callbacks;
        $GLOBALS['intersoccer_track_wp_actions'] = true;
        $mock_wp_action_callbacks = [];

        $reflection = new ReflectionClass(InterSoccer_Commission_Manager::class);
        $flag = $reflection->getProperty('woocommerce_hooks_registered');
        $flag->setAccessible(true);
        $flag->setValue(null, false);
    }

    protected function tearDown(): void {
        unset($GLOBALS['intersoccer_track_wp_actions']);
        parent::tearDown();
    }

    public function testRefundHooksRegisteredAtPluginLoadWithoutOrderCompletion() {
        InterSoccer_Commission_Manager::register_woocommerce_hooks();

        $manager = InterSoccer_Commission_Manager::get_instance();
        $this->assertNotFalse(
            has_action('woocommerce_order_refunded', [$manager, 'handle_order_refunded_rewards']),
            'woocommerce_order_refunded must be registered at bootstrap'
        );
        $this->assertNotFalse(
            has_action('woocommerce_order_status_cancelled', [$manager, 'reverse_paid_rewards']),
            'woocommerce_order_status_cancelled must be registered at bootstrap'
        );
    }

    public function testRefundHooksAreNotRegisteredTwice() {
        InterSoccer_Commission_Manager::register_woocommerce_hooks();
        InterSoccer_Commission_Manager::register_woocommerce_hooks();

        global $mock_wp_action_callbacks;
        $refund_hooks = $mock_wp_action_callbacks['woocommerce_order_refunded'] ?? [];
        $this->assertCount(1, $refund_hooks, 'Duplicate registration would run commission reversal twice per refund');
    }
}
