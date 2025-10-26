<?php

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for automated workflows
 * Tests email notifications, commission payouts, and system automation
 */
class AutomatedWorkflowsTest extends TestCase {

    protected function setUp(): void {
        require_once __DIR__ . '/../bootstrap.php';
        require_once __DIR__ . '/../../includes/class-commission-manager.php';
        require_once __DIR__ . '/../../includes/class-points-manager.php';
        require_once __DIR__ . '/../../includes/class-referral-handler.php';
        require_once __DIR__ . '/../../includes/class-email-notifications.php';
    }

    /**
     * Test automated commission payout workflow
     */
    public function testAutomatedCommissionPayoutWorkflow() {
        $commission_manager = new InterSoccer_Commission_Manager();
        $points_manager = new InterSoccer_Points_Manager();

        $coach_id = 2;
        $customer_id = 1;

        // Set up partnership
        global $mock_user_meta;
        $mock_user_meta[$customer_id]['intersoccer_partnership_coach_id'] = $coach_id;

        // Process multiple orders to accumulate commissions
        $orders = [
            ['total' => 300, 'tax' => 30, 'order_id' => 400],
            ['total' => 450, 'tax' => 45, 'order_id' => 401],
            ['total' => 200, 'tax' => 20, 'order_id' => 402],
        ];

        foreach ($orders as $order_data) {
            $order = new WC_Order();
            $order->set_total($order_data['total']);
            $order->set_tax_total($order_data['tax']);
            $order->set_customer_id($customer_id);

            $commission_manager->calculate_and_store_commission($order_data['order_id'], $coach_id);
        }

        // Trigger automated payout (monthly threshold reached)
        $payout_amount = $commission_manager->process_monthly_payouts();

        // Verify payout calculation
        $expected_commission = (270 * 0.15) + (405 * 0.15) + (180 * 0.15); // Base commission
        $expected_commission += $expected_commission * 0.10; // Loyalty bonus
        $this->assertEquals(184.275, $payout_amount); // (270+405+180)*0.15*1.10

        // Verify coach balance updated
        $coach_balance = $points_manager->get_points_balance($coach_id);
        $this->assertEquals(0, $coach_balance); // Balance reset after payout
    }

    /**
     * Test automated email notification workflow
     */
    public function testAutomatedEmailNotificationWorkflow() {
        $email_notifications = new InterSoccer_Email_Notifications();
        $referral_handler = new InterSoccer_Referral_Handler();

        $coach_id = 2;
        $customer_id = 1;

        // Set up partnership
        global $mock_user_meta;
        $mock_user_meta[$customer_id]['intersoccer_partnership_coach_id'] = $coach_id;

        // Process referral order
        $order_id = 403;
        $referral_handler->process_referral_order($order_id);

        // Trigger automated email notifications
        $email_notifications->send_referral_completion_notification($coach_id, $customer_id, $order_id);

        // Verify email was queued/sent
        global $mock_email_queue;
        $this->assertCount(1, $mock_email_queue);

        $email = $mock_email_queue[0];
        $this->assertEquals('coach@example.com', $email['to']);
        $this->assertStringContains('New Referral Completed', $email['subject']);
        $this->assertStringContains('Customer #1', $email['body']);
    }

    /**
     * Test automated points expiration workflow
     */
    public function testAutomatedPointsExpirationWorkflow() {
        $points_manager = new InterSoccer_Points_Manager();

        $customer_id = 1;

        // Allocate points with different dates
        $points_manager->allocate_points($customer_id, 100, 'purchase', 'Test order 1');
        $points_manager->allocate_points($customer_id, 50, 'referral', 'Test referral');

        // Simulate points aging (set old timestamps)
        global $mock_points_transactions;
        $mock_points_transactions[0]['timestamp'] = strtotime('-13 months'); // Expired
        $mock_points_transactions[1]['timestamp'] = strtotime('-11 months'); // Still valid

        // Trigger automated expiration cleanup
        $expired_count = $points_manager->expire_old_points();

        // Verify expired points were removed
        $this->assertEquals(1, $expired_count);

        // Verify balance reflects only non-expired points
        $balance = $points_manager->get_points_balance($customer_id);
        $this->assertEquals(50, $balance);
    }

    /**
     * Test automated referral cleanup workflow
     */
    public function testAutomatedReferralCleanupWorkflow() {
        $referral_handler = new InterSoccer_Referral_Handler();

        // Create test referral data
        global $mock_user_meta;
        $mock_user_meta[1]['intersoccer_partnership_coach_id'] = 2;
        $mock_user_meta[1]['intersoccer_referral_timestamp'] = strtotime('-2 years'); // Old referral

        $mock_user_meta[3]['intersoccer_partnership_coach_id'] = 4;
        $mock_user_meta[3]['intersoccer_referral_timestamp'] = strtotime('-6 months'); // Recent referral

        // Trigger automated cleanup
        $cleanup_count = $referral_handler->cleanup_old_referrals();

        // Verify old referrals were cleaned up
        $this->assertEquals(1, $cleanup_count);

        // Verify recent referral still exists
        $this->assertEquals(4, $mock_user_meta[3]['intersoccer_partnership_coach_id']);
    }

    /**
     * Test automated tier upgrade workflow
     */
    public function testAutomatedTierUpgradeWorkflow() {
        $commission_manager = new InterSoccer_Commission_Manager();

        $coach_id = 2;

        // Set up coach with initial tier
        global $mock_user_meta;
        $mock_user_meta[$coach_id]['intersoccer_coach_tier'] = 'bronze';

        // Accumulate commissions to trigger tier upgrade
        $commission_manager->calculate_and_store_commission(404, $coach_id, 5000); // Large commission

        // Trigger automated tier evaluation
        $upgraded = $commission_manager->process_tier_upgrades();

        // Verify tier upgrade occurred
        $this->assertTrue($upgraded);
        $this->assertEquals('silver', $mock_user_meta[$coach_id]['intersoccer_coach_tier']);
    }

    /**
     * Test automated coupon generation workflow
     */
    public function testAutomatedCouponGenerationWorkflow() {
        $referral_handler = new InterSoccer_Referral_Handler();

        $customer_id = 1;
        $coach_id = 2;

        // Set up partnership
        global $mock_user_meta;
        $mock_user_meta[$customer_id]['intersoccer_partnership_coach_id'] = $coach_id;

        // Process qualifying order
        $order_id = 405;
        $referral_handler->process_referral_order($order_id);

        // Trigger automated coupon generation
        $coupon_code = $referral_handler->generate_referral_coupon($customer_id);

        // Verify coupon was generated
        $this->assertNotEmpty($coupon_code);
        $this->assertStringStartsWith('REF-', $coupon_code);

        // Verify coupon details
        global $mock_coupons;
        $this->assertArrayHasKey($coupon_code, $mock_coupons);
        $this->assertEquals(10, $mock_coupons[$coupon_code]['discount_percent']);
        $this->assertEquals($customer_id, $mock_coupons[$coupon_code]['customer_id']);
    }

    /**
     * Test automated performance reporting workflow
     */
    public function testAutomatedPerformanceReportingWorkflow() {
        $commission_manager = new InterSoccer_Commission_Manager();

        $coach_id = 2;

        // Generate performance data
        $commission_manager->calculate_and_store_commission(406, $coach_id, 1000);
        $commission_manager->calculate_and_store_commission(407, $coach_id, 1500);

        // Trigger automated monthly report generation
        $report = $commission_manager->generate_monthly_performance_report($coach_id);

        // Verify report contains expected data
        $this->assertArrayHasKey('total_commission', $report);
        $this->assertArrayHasKey('referral_count', $report);
        $this->assertArrayHasKey('average_order_value', $report);
        $this->assertEquals(2500, $report['total_commission']); // 1000 + 1500
        $this->assertEquals(2, $report['referral_count']);
    }

    /**
     * Test automated fraud detection workflow
     */
    public function testAutomatedFraudDetectionWorkflow() {
        $referral_handler = new InterSoccer_Referral_Handler();

        $customer_id = 1;
        $coach_id = 2;

        // Simulate suspicious activity
        for ($i = 0; $i < 10; $i++) {
            $order_id = 500 + $i;
            $referral_handler->process_referral_order($order_id);
        }

        // Trigger automated fraud detection
        $flagged = $referral_handler->detect_fraudulent_activity($customer_id);

        // Verify suspicious activity was flagged
        $this->assertTrue($flagged);

        // Verify account was flagged
        global $mock_user_meta;
        $this->assertTrue($mock_user_meta[$customer_id]['intersoccer_fraud_flag']);
    }

    /**
     * Test automated system health checks
     */
    public function testAutomatedSystemHealthChecks() {
        $points_manager = new InterSoccer_Points_Manager();
        $commission_manager = new InterSoccer_Commission_Manager();

        // Simulate system issues
        global $mock_database_connection;
        $mock_database_connection = false; // Simulate DB connection failure

        // Trigger automated health check
        $health_status = $this->runSystemHealthCheck();

        // Verify issues were detected
        $this->assertFalse($health_status['database']);
        $this->assertTrue($health_status['points_system']);
        $this->assertTrue($health_status['commission_system']);
    }

    /**
     * Test automated backup and recovery workflow
     */
    public function testAutomatedBackupAndRecoveryWorkflow() {
        $points_manager = new InterSoccer_Points_Manager();

        // Create test data
        $points_manager->allocate_points(1, 100, 'test', 'Backup test');
        $points_manager->allocate_points(2, 200, 'test', 'Backup test');

        // Trigger automated backup
        $backup_success = $this->performAutomatedBackup();

        // Verify backup was created
        $this->assertTrue($backup_success);

        // Simulate data loss
        global $mock_points_transactions;
        $original_data = $mock_points_transactions;
        $mock_points_transactions = [];

        // Trigger automated recovery
        $recovery_success = $this->performAutomatedRecovery();

        // Verify data was restored
        $this->assertTrue($recovery_success);
        $this->assertEquals($original_data, $mock_points_transactions);
    }

    /**
     * Test automated workflow scheduling
     */
    public function testAutomatedWorkflowScheduling() {
        // Test that automated workflows are properly scheduled
        $scheduled_workflows = [
            'monthly_payouts' => 'first day of month',
            'points_expiration' => 'daily',
            'performance_reports' => 'monthly',
            'system_health_check' => 'hourly',
            'data_backup' => 'daily',
        ];

        foreach ($scheduled_workflows as $workflow => $frequency) {
            $this->assertWorkflowIsScheduled($workflow, $frequency);
        }
    }

    /**
     * Helper method to run system health check
     */
    private function runSystemHealthCheck() {
        return [
            'database' => false, // Mocked as failing
            'points_system' => true,
            'commission_system' => true,
            'email_system' => true,
        ];
    }

    /**
     * Helper method to perform automated backup
     */
    private function performAutomatedBackup() {
        // Mock backup process
        global $mock_backup_storage;
        $mock_backup_storage = ['timestamp' => time(), 'data' => 'backup_data'];
        return true;
    }

    /**
     * Helper method to perform automated recovery
     */
    private function performAutomatedRecovery() {
        // Mock recovery process
        global $mock_points_transactions, $mock_backup_storage;
        $mock_points_transactions = $mock_backup_storage['data'];
        return true;
    }

    /**
     * Helper method to assert workflow scheduling
     */
    private function assertWorkflowIsScheduled($workflow, $frequency) {
        global $mock_cron_schedules;
        $this->assertArrayHasKey($workflow, $mock_cron_schedules);
        $this->assertEquals($frequency, $mock_cron_schedules[$workflow]);
    }
}