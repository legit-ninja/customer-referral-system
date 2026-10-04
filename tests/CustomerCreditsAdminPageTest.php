<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-admin-referrals.php';

class CustomerCreditsAdminPageTest extends TestCase {
    private $saved_results;

    protected function setUp(): void {
        global $mock_wpdb_get_results;
        $this->saved_results = $mock_wpdb_get_results;
    }

    protected function tearDown(): void {
        global $mock_wpdb_get_results;
        $mock_wpdb_get_results = $this->saved_results;
    }

    public function testCustomerCreditsTableReadsPointsBalance() {
        global $mock_wpdb_get_results;

        $seen = '';
        $mock_wpdb_get_results['FROM wp_users u'] = function ($query) use (&$seen) {
            $seen = $query;
            return [
                (object) [
                    'ID' => 7101,
                    'display_name' => 'Ada Customer',
                    'user_email' => 'ada@example.com',
                    'credits' => 33,
                ],
            ];
        };

        $admin = new InterSoccer_Admin_Referrals();
        $method = new ReflectionMethod($admin, 'display_customer_credits_table');
        $method->setAccessible(true);
        ob_start();
        $method->invoke($admin);
        $html = ob_get_clean();

        $this->assertStringContainsString("meta_key = 'intersoccer_points_balance'", $seen);
        $this->assertStringNotContainsString('intersoccer_customer_credits', $seen);
        $this->assertStringContainsString('Ada Customer', $html);
        $this->assertStringContainsString('33', $html);
    }
}
