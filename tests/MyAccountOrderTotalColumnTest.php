<?php

use PHPUnit\Framework\TestCase;

if (!class_exists('WooCommerce')) {
    /**
     * Presence flag so the dashboard constructor runs its WooCommerce hooks.
     * The harness does not load WooCommerce itself.
     */
    class WooCommerce {
    }
}

/**
 * My Account orders table must keep WooCommerce's own total column output.
 */
class MyAccountOrderTotalColumnTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        require_once dirname(__DIR__) . '/includes/class-admin-dashboard-main.php';
        require_once dirname(__DIR__) . '/includes/class-admin-coaches.php';
        require_once dirname(__DIR__) . '/includes/class-admin-referrals.php';
        require_once dirname(__DIR__) . '/includes/class-admin-financial.php';
        require_once dirname(__DIR__) . '/includes/class-simulator.php';
        require_once dirname(__DIR__) . '/includes/class-admin-settings.php';
        require_once dirname(__DIR__) . '/includes/class-admin-points.php';
        require_once dirname(__DIR__) . '/includes/class-admin-coach-assignments.php';
        require_once dirname(__DIR__) . '/includes/class-admin-coach-events.php';
        require_once dirname(__DIR__) . '/includes/class-coach-email-templates.php';
        require_once dirname(__DIR__) . '/includes/class-admin-coach-email-templates.php';
        require_once dirname(__DIR__) . '/includes/class-admin-dashboard.php';

        global $mock_wp_action_callbacks;
        $GLOBALS['intersoccer_track_wp_actions'] = true;
        $mock_wp_action_callbacks = [];
    }

    protected function tearDown(): void {
        unset($GLOBALS['intersoccer_track_wp_actions']);
        parent::tearDown();
    }

    /**
     * Regression: #135 — an empty column callback blanks every order total.
     */
    public function testOrdersTotalColumnHookIsNotRegistered() {
        $this->assertTrue(class_exists('WooCommerce'));

        new InterSoccer_Referral_Admin_Dashboard();

        $this->assertFalse(has_action('woocommerce_my_account_my_orders_column_order-total'));
        $this->assertNotFalse(
            has_action('woocommerce_cart_calculate_fees'),
            'WooCommerce hook registration must still run'
        );
    }
}
