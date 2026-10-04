<?php

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        $email = trim((string) $email);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = 'default') {
        echo esc_html($text);
    }
}

if (!function_exists('esc_attr_e')) {
    function esc_attr_e($text, $domain = 'default') {
        echo esc_attr($text);
    }
}


use PHPUnit\Framework\TestCase;

/**
 * A gift moves points. It must not change issued or spent dashboard totals.
 */
class GiftDashboardTotalsTest extends TestCase {
    private $saved = [];

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/class-points-manager.php';
        require_once __DIR__ . '/../includes/class-admin-dashboard-main.php';
        require_once __DIR__ . '/../includes/class-admin-financial.php';
        require_once __DIR__ . '/../includes/class-admin-points.php';
        require_once __DIR__ . '/../includes/class-referral-handler.php';

        global $mock_points_log_rows, $mock_user_meta, $mock_users, $mock_points_balances;
        global $mock_current_user_id, $mock_wp_json_response, $mock_fail_points_balance_change_for;
        $this->saved = [
            'rows' => $mock_points_log_rows,
            'meta' => $mock_user_meta,
            'users' => $mock_users,
            'balances' => $mock_points_balances,
            'user' => $mock_current_user_id,
            'json' => $mock_wp_json_response,
            'fail' => $mock_fail_points_balance_change_for,
            'post' => $_POST,
        ];
        $mock_points_log_rows = [];
        $mock_points_balances = [];
        $mock_wp_json_response = null;
        $mock_fail_points_balance_change_for = null;
        parent::setUp();
    }

    protected function tearDown(): void {
        global $mock_points_log_rows, $mock_user_meta, $mock_users, $mock_points_balances;
        global $mock_current_user_id, $mock_wp_json_response, $mock_fail_points_balance_change_for;
        $mock_points_log_rows = $this->saved['rows'];
        $mock_user_meta = $this->saved['meta'];
        $mock_users = $this->saved['users'];
        $mock_points_balances = $this->saved['balances'];
        $mock_current_user_id = $this->saved['user'];
        $mock_wp_json_response = $this->saved['json'];
        $mock_fail_points_balance_change_for = $this->saved['fail'];
        $_POST = $this->saved['post'];
        parent::tearDown();
    }

    public function testGiftDoesNotChangeIssuedOrSpentTotals() {
        global $mock_users, $mock_user_meta, $mock_points_log_rows, $mock_fail_points_balance_change_for;

        $sender = 9101;
        $recipient = 9102;
        $failed_recipient = 9103;
        $this->rememberUser($sender, 'gift-dash-sender@example.com', 'Gift Sender');
        $this->rememberUser($recipient, 'gift-dash-recipient@example.com', 'Gift Recipient');
        $this->rememberUser($failed_recipient, 'gift-dash-failed@example.com', 'Gift Failed');

        $points = new InterSoccer_Points_Manager();
        $points->add_points_transaction($sender, 'order_purchase', 100, null, 'Purchase');
        $points->add_points_transaction($sender, 'points_redemption', -20, null, 'Redeemed');
        $points->add_points_transaction($recipient, 'order_purchase', 40, null, 'Purchase');

        $before = $this->issuedAndSpent();
        $this->assertSame(140, $before['statistics_earned']);
        $this->assertSame(20, $before['statistics_spent']);
        $this->assertSame(140, $before['dashboard_issued']);
        $this->assertSame(20, $before['dashboard_spent']);
        $this->assertSame(140, $before['financial_issued']);
        $this->assertSame(20, $before['overview_spent']);
        $this->assertSame(100, $before['customer_earned'][$sender]);
        $this->assertSame(20, $before['customer_spent'][$sender]);
        $this->assertSame(40, $before['customer_earned'][$recipient]);
        $this->assertSame(0, $before['customer_spent'][$recipient]);

        $sent = $this->giftPoints($sender, 'gift-dash-recipient@example.com', 50);
        $this->assertTrue($sent['success']);

        $mock_fail_points_balance_change_for = $failed_recipient;
        $returned = $this->giftPoints($sender, 'gift-dash-failed@example.com', 20);
        $mock_fail_points_balance_change_for = null;
        $this->assertFalse($returned['success']);
        $this->assertStringContainsString('returned', strtolower((string) $returned['data']['message']));

        $types = array_map(function ($row) {
            return $row['transaction_type'] ?? '';
        }, $mock_points_log_rows);
        $this->assertContains('gift_sent', $types);
        $this->assertContains('gift_received', $types);
        $this->assertContains('gift_returned', $types);

        $after = $this->issuedAndSpent();
        $this->assertSame($before, $after);
    }

    private function rememberUser($id, $email, $name) {
        global $mock_users;
        $mock_users[$id] = (object) [
            'ID' => $id,
            'roles' => ['customer'],
            'user_email' => $email,
            'display_name' => $name,
        ];
    }

    private function giftPoints($sender_id, $recipient_email, $amount) {
        global $mock_current_user_id, $mock_wp_json_response;
        $mock_current_user_id = $sender_id;
        $_POST['gift_amount'] = (string) $amount;
        $_POST['recipient_email'] = $recipient_email;
        $_POST['nonce'] = 'test-nonce';
        $mock_wp_json_response = null;
        $handler = new InterSoccer_Referral_Handler();
        $handler->handle_gift_credits();
        return $mock_wp_json_response;
    }

    private function issuedAndSpent() {
        $points = new InterSoccer_Points_Manager();
        $stats = $points->get_points_statistics();

        $dashboard = new InterSoccer_Admin_Dashboard_Main();
        $credit = $this->invoke($dashboard, 'get_customer_credit_stats');
        $overview = $this->invoke($dashboard, 'get_financial_overview_dashboard');
        $distribution = $this->invoke($dashboard, 'get_program_distribution_data');
        $activity = $this->invoke($dashboard, 'get_redemption_activity_data');
        $trends = $this->invoke($dashboard, 'get_credit_trends_data');

        $financial = new InterSoccer_Admin_Financial();
        $report = $this->invoke($financial, 'get_financial_report_data');
        ob_start();
        $this->invoke($financial, 'display_financial_breakdown');
        $breakdown = ob_get_clean();

        global $mock_wp_json_response;
        $mock_wp_json_response = null;
        $_POST['filter'] = 'all';
        $_POST['search'] = '';
        $_POST['page'] = 1;
        $admin = new InterSoccer_Admin_Points();
        $admin->get_points_users_ajax();
        $customers = [];
        foreach ($mock_wp_json_response['data']['users'] as $user) {
            $customers[(int) $user->ID] = [
                'earned' => (int) $user->total_earned,
                'spent' => (int) $user->total_spent,
            ];
        }
        ksort($customers);

        return [
            'statistics_earned' => (int) $stats['total_earned'],
            'statistics_spent' => (int) $stats['total_spent'],
            'dashboard_issued' => (int) $credit['total_credits_earned'],
            'dashboard_issued_month' => (int) $credit['credits_earned_this_month'],
            'dashboard_spent' => (int) $credit['total_credits_used'],
            'financial_issued' => (int) $report['points_earned'],
            'overview_spent' => (int) $overview['total_program_cost'],
            'distribution_redeemed' => (int) $distribution['values'][1],
            'activity_earned' => array_map('intval', $activity['earned']),
            'activity_redeemed' => array_map('intval', $activity['redeemed']),
            'trend_redeemed' => array_map('intval', $trends['costs']),
            'breakdown' => $breakdown,
            'customer_earned' => array_map(function ($row) {
                return $row['earned'];
            }, $customers),
            'customer_spent' => array_map(function ($row) {
                return $row['spent'];
            }, $customers),
        ];
    }

    private function invoke($object, $method) {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);
        return $reflection->invoke($object);
    }
}
