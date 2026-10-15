<?php

use PHPUnit\Framework\TestCase;

/**
 * Guards for apply_commission_reinstatement_for_order (lock, transaction, insert-before-balance).
 */
class CommissionReinstatementGuardsTest extends TestCase {

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/intersoccer-refund-helpers.php';
        require_once __DIR__ . '/../includes/class-commission-manager.php';

        global $mock_user_meta, $mock_wpdb_get_results, $mock_wpdb_inserts, $mock_fail_wpdb_insert_tables,
               $mock_fail_wpdb_commit, $mock_scheduled_single_events, $mock_wpdb_transaction_depth, $mock_wc_orders_by_id;
        $mock_user_meta = [];
        $mock_wc_orders_by_id = [];
        $mock_wpdb_get_results = [];
        $mock_wpdb_inserts = [];
        $mock_fail_wpdb_insert_tables = [];
        $mock_fail_wpdb_commit = false;
        $mock_scheduled_single_events = [];
        $mock_wpdb_transaction_depth = 0;

        $reflection = new ReflectionClass(InterSoccer_Commission_Manager::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        $hooks = $reflection->getProperty('woocommerce_hooks_registered');
        $hooks->setAccessible(true);
        $hooks->setValue(null, false);
        $this->resetCommissionManagerTransactionState();
    }

    private function resetCommissionManagerTransactionState(): void {
        $reflection = new ReflectionClass(InterSoccer_Commission_Manager::class);
        foreach (['wpdb_transaction_depth', 'referral_credits_table_engine'] as $property) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            $prop->setValue(null, $property === 'wpdb_transaction_depth' ? 0 : null);
        }
    }

    private function forceReferralCreditsTableEngine(string $engine): void {
        $reflection = new ReflectionClass(InterSoccer_Commission_Manager::class);
        $prop = $reflection->getProperty('referral_credits_table_engine');
        $prop->setAccessible(true);
        $prop->setValue(null, strtoupper($engine));
    }

    protected function tearDown(): void {
        global $mock_fail_wpdb_insert_tables, $mock_fail_wpdb_commit, $mock_scheduled_single_events,
               $mock_wpdb_get_results, $mock_wpdb_inserts, $mock_wpdb_transaction_depth;
        $mock_fail_wpdb_insert_tables = [];
        $mock_fail_wpdb_commit = false;
        $mock_scheduled_single_events = [];
        $mock_wpdb_get_results = [];
        $mock_wpdb_inserts = [];
        $mock_wpdb_transaction_depth = 0;
        parent::tearDown();
    }

    private function make_order($order_id, $customer_id) {
        $order = new WC_Order($order_id);
        $order->set_id($order_id);
        $order->set_customer_id($customer_id);
        global $mock_wc_orders_by_id;
        $mock_wc_orders_by_id[$order_id] = $order;
        return $order;
    }

    private function invoke_reinstatement($order, $coach_id, $amount) {
        $manager = InterSoccer_Commission_Manager::get_instance();
        $method = new ReflectionMethod($manager, 'apply_commission_reinstatement_for_order');
        $method->setAccessible(true);
        $method->invoke($manager, $order, (int) $coach_id, (float) $amount);
    }

    private function seed_referral_row($order_id, $coach_id, $customer_id, $referral_id = 901) {
        global $mock_wpdb_get_results;
        $mock_wpdb_get_results['intersoccer_referrals'] = [
            (object) [
                'id' => $referral_id,
                'order_id' => $order_id,
                'coach_id' => $coach_id,
                'customer_id' => $customer_id,
                'status' => 'paid',
            ],
        ];
    }

    public function testReinstatementDefersWhenCoachCreditLockIsBusy() {
        global $mock_user_meta, $mock_scheduled_single_events;
        $coach_id = 97101;
        $order_id = 97001;
        $mock_user_meta[$coach_id] = ['intersoccer_credits' => 12.0];
        update_option(intersoccer_coach_credit_lock_option_name($coach_id), time(), false);
        $this->seed_referral_row($order_id, $coach_id, 97011);

        $order = $this->make_order($order_id, 97011);
        $this->invoke_reinstatement($order, $coach_id, 5.0);

        $this->assertSame(12.0, (float) ($mock_user_meta[$coach_id]['intersoccer_credits'] ?? 0));
        $this->assertCount(1, $mock_scheduled_single_events);
        $this->assertSame('commission', (string) ($mock_scheduled_single_events[0]['args'][1] ?? ''));
        $payload = $mock_scheduled_single_events[0]['args'][2] ?? [];
        $this->assertSame('reinstatement', (string) ($payload['reason'] ?? ''));
        delete_option(intersoccer_coach_credit_lock_option_name($coach_id));
    }

    public function testReinstatementDoesNotCreditBalanceWhenLedgerInsertFails() {
        global $mock_user_meta, $mock_fail_wpdb_insert_tables, $mock_wpdb_inserts;
        // Without durable referral-credit transactions, a balance-first reinstatement would leave coach credits changed after a failed insert.
        $this->forceReferralCreditsTableEngine('MyISAM');
        $coach_id = 97102;
        $order_id = 97002;
        $mock_user_meta[$coach_id] = ['intersoccer_credits' => 20.0];
        $mock_fail_wpdb_insert_tables['intersoccer_referral_credits'] = true;
        $this->seed_referral_row($order_id, $coach_id, 97012, 902);

        $order = $this->make_order($order_id, 97012);
        try {
            $this->invoke_reinstatement($order, $coach_id, 7.5);
            $this->fail('Expected exception when referral credits insert fails');
        } catch (RuntimeException $e) {
            $this->assertSame('commission_reinstatement_insert_failed', $e->getMessage());
        }

        $this->assertSame(20.0, (float) ($mock_user_meta[$coach_id]['intersoccer_credits'] ?? 0));
        $this->assertSame(0, count(array_filter((array) $mock_wpdb_inserts, static function ($row) {
            return strpos((string) ($row['table'] ?? ''), 'intersoccer_referral_credits') !== false;
        })));
    }

    public function testReinstatementRollsBackCreditsWhenCommitFails() {
        global $mock_user_meta, $mock_fail_wpdb_commit;
        $coach_id = 97103;
        $order_id = 97003;
        $mock_user_meta[$coach_id] = ['intersoccer_credits' => 9.0];
        $mock_fail_wpdb_commit = true;
        $this->seed_referral_row($order_id, $coach_id, 97013, 903);

        $order = $this->make_order($order_id, 97013);
        try {
            $this->invoke_reinstatement($order, $coach_id, 4.0);
            $this->fail('Expected exception when COMMIT fails');
        } catch (RuntimeException $e) {
            $this->assertSame('wpdb_commit_failed', $e->getMessage());
        }

        $this->assertSame(9.0, (float) ($mock_user_meta[$coach_id]['intersoccer_credits'] ?? 0));
    }
}
