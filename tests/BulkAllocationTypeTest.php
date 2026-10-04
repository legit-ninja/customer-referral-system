<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-simulator.php';
require_once __DIR__ . '/../includes/class-admin-settings.php';

class BulkAllocationTypeTest extends TestCase {
    private $saved_results;
    private $saved_caps;
    private $saved_post;

    protected function setUp(): void {
        global $mock_wpdb_get_results, $mock_user_capabilities, $mock_wp_json_response;
        $this->saved_results = $mock_wpdb_get_results;
        $this->saved_caps = $mock_user_capabilities;
        $this->saved_post = $_POST;
        $mock_user_capabilities['manage_options'] = true;
        $mock_wp_json_response = null;
        $_POST = [
            'nonce' => 'test',
            'credit_amount' => '10',
        ];
    }

    protected function tearDown(): void {
        global $mock_wpdb_get_results, $mock_user_capabilities, $mock_wp_json_response;
        $mock_wpdb_get_results = $this->saved_results;
        $mock_user_capabilities = $this->saved_caps;
        $mock_wp_json_response = null;
        $_POST = $this->saved_post;
    }

    public function testUnknownAllocationTypeDoesNotSelectEveryUser() {
        global $mock_wpdb_get_results, $mock_wp_json_response, $mock_user_meta;

        $queried = false;
        $mock_wpdb_get_results['SELECT ID, user_email'] = function () use (&$queried) {
            $queried = true;
            return [
                (object) ['ID' => 7701, 'user_email' => 'everyone@example.com'],
            ];
        };
        $mock_user_meta[7701] = ['intersoccer_points_balance' => 3];
        $_POST['allocation_type'] = 'all_users';

        (new InterSoccer_Admin_Settings())->allocate_credits_to_customers();

        $this->assertFalse($queried, 'An unknown allocation type must not query users');
        $this->assertFalse($mock_wp_json_response['success']);
        $this->assertSame('Unknown allocation type.', $mock_wp_json_response['data']['message']);
        $this->assertSame(3, (int) $mock_user_meta[7701]['intersoccer_points_balance']);
    }

    public function testEmptyAllocationTypeDoesNotSelectEveryUser() {
        global $mock_wpdb_get_results, $mock_wp_json_response;

        $queried = false;
        $mock_wpdb_get_results['SELECT ID, user_email'] = function () use (&$queried) {
            $queried = true;
            return [];
        };
        $_POST['allocation_type'] = '';

        (new InterSoccer_Admin_Settings())->allocate_credits_to_customers();

        $this->assertFalse($queried);
        $this->assertFalse($mock_wp_json_response['success']);
    }

    public function testCoachesAllocationStillSelectsCoachUsers() {
        global $mock_wpdb_get_results, $mock_wp_json_response;

        $seen = '';
        $mock_wpdb_get_results['SELECT ID, user_email'] = function ($query) use (&$seen) {
            $seen = $query;
            return [];
        };
        $_POST['allocation_type'] = 'coaches';

        (new InterSoccer_Admin_Settings())->allocate_credits_to_customers();

        $this->assertTrue($mock_wp_json_response['success']);
        $this->assertSame(0, $mock_wp_json_response['data']['allocated_count']);
        $this->assertStringContainsString('coach_id', $seen);
        $this->assertStringNotContainsString('WHERE 1=1 AND ID NOT IN', $seen);
    }
}
