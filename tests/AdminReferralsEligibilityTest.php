<?php

use PHPUnit\Framework\TestCase;

class AdminReferralsEligibilityTest extends TestCase {

    protected function setUp(): void {
        if (!class_exists('InterSoccer_Admin_Referrals')) {
            require_once __DIR__ . '/../includes/class-admin-referrals.php';
        }

        update_option('date_format', 'Y-m-d');
        update_option('time_format', 'H:i');
    }

    private function callPrivateMethod($object, $methodName, array $args = []) {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }

    public function testNormalizeEligibilityDataHandlesJson() {
        $adminReferrals = new InterSoccer_Admin_Referrals();

        $payload = json_encode([
            'eligible' => false,
            'reason' => 'manual_block',
            'lookback_months' => 12,
            'last_order_id' => 123,
            'months_since_last' => 4
        ]);

        $result = $this->callPrivateMethod($adminReferrals, 'normalize_eligibility_data', [$payload]);

        $this->assertIsArray($result);
        $this->assertFalse($result['eligible']);
        $this->assertSame('manual_block', $result['reason']);
        $this->assertSame(12, $result['lookback_months']);
        $this->assertSame(4, $result['months_since_last']);
        $this->assertSame(123, $result['last_order_id']);
    }

    public function testPrepareEligibilityViewModelForManualOverride() {
        $adminReferrals = new InterSoccer_Admin_Referrals();

        $eligibility = [
            'eligible' => false,
            'reason' => 'manual_block',
            'lookback_months' => 18,
            'last_order_id' => 456,
            'last_order_date' => '2025-10-01 10:00:00',
            'months_since_last' => 6,
            'overrides' => [
                [
                    'status' => 'ineligible',
                    'note' => 'Verified duplicate referral',
                    'user_id' => 1,
                    'timestamp' => '2025-11-05 12:15:00',
                ],
                [
                    'status' => 'eligible',
                    'note' => '',
                    'user_id' => 2,
                    'timestamp' => '2025-11-06 09:00:00',
                ],
            ]
        ];

        $view = $this->callPrivateMethod($adminReferrals, 'prepare_eligibility_view_model', [$eligibility]);

        $this->assertIsArray($view);
        $this->assertArrayHasKey('status_label', $view);
        $this->assertArrayHasKey('button_label', $view);
        $this->assertArrayHasKey('reason_label', $view);
        $this->assertArrayHasKey('override_summary', $view);

        $this->assertStringContainsString('Ineligible', $view['status_label']);
        $this->assertStringContainsString('manual override', strtolower($view['reason_label']));
        $this->assertSame('eligible', $view['button_target']);
        $this->assertSame('Mark Eligible', $view['button_label']);
        $this->assertNotEmpty($view['override_summary']);
        $this->assertNotEmpty($view['override_notes']);
        $this->assertStringContainsString('Verified duplicate referral', implode('', $view['override_notes']));
        $this->assertSame('eligible partial', $view['status_class']);
        $this->assertSame('Coach commission active', $view['status_label']);
    }
}

