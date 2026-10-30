<?php

use PHPUnit\Framework\TestCase;

if (!class_exists('WooCommerce')) {
    class WooCommerce {
    }
}

/**
 * Regression: CRS #135 — empty My Account orders Total column when CRS registers an empty column callback.
 */
class MyAccountOrdersTotalColumnTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();

        require_once __DIR__ . '/../includes/class-admin-dashboard-main.php';
        require_once __DIR__ . '/../includes/class-admin-coaches.php';
        require_once __DIR__ . '/../includes/class-admin-referrals.php';
        require_once __DIR__ . '/../includes/class-admin-financial.php';
        require_once __DIR__ . '/../includes/class-admin-settings.php';
        require_once __DIR__ . '/../includes/class-admin-points.php';
        require_once __DIR__ . '/../includes/class-admin-coach-assignments.php';
        require_once __DIR__ . '/../includes/class-admin-coach-events.php';
        require_once __DIR__ . '/../includes/class-coach-email-templates.php';
        require_once __DIR__ . '/../includes/class-admin-coach-email-templates.php';
        require_once __DIR__ . '/../includes/class-admin-dashboard.php';

        global $mock_wp_action_callbacks;
        $GLOBALS['intersoccer_track_wp_actions'] = true;
        $mock_wp_action_callbacks = [];
    }

    protected function tearDown(): void {
        unset($GLOBALS['intersoccer_track_wp_actions']);
        parent::tearDown();
    }

    public function testDoesNotHookMyAccountOrdersTotalColumn(): void {
        new InterSoccer_Referral_Admin_Dashboard();

        $this->assertFalse(
            has_action('woocommerce_my_account_my_orders_column_order-total'),
            'CRS must not register woocommerce_my_account_my_orders_column_order-total so WooCommerce can render the total'
        );
    }
}
