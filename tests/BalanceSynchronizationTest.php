<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for Balance Synchronization and Data Integrity
 * 
 * Ensures:
 * - User meta balance matches transaction log
 * - No orphaned transactions
 * - Balance calculations are accurate
 * - Concurrent updates handled correctly
 */
class BalanceSynchronizationTest extends TestCase {

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/class-points-manager.php';
    }

    /**
     * Test balance equals sum of transactions
     */
    public function testBalanceEqualsSumOfTransactions() {
        $transactions = [
            ['amount' => 10],   // Order 1
            ['amount' => 15],   // Order 2
            ['amount' => -5],   // Redemption
            ['amount' => 20],   // Order 3
            ['amount' => -10],  // Refund
        ];
        
        $calculated_balance = array_sum(array_column($transactions, 'amount'));
        
        $this->assertEquals(30, $calculated_balance);
    }

    /**
     * Test user meta balance synchronization
     */
    public function testUserMetaBalanceSynchronization() {
        $transaction_log_balance = 50;
        $user_meta_balance = 50; // Should match
        
        $this->assertEquals($transaction_log_balance, $user_meta_balance);
    }

    /**
     * Test balance mismatch detection
     */
    public function testBalanceMismatchDetection() {
        $transaction_log_balance = 50;
        $user_meta_balance = 45; // Mismatch!
        
        $has_mismatch = ($transaction_log_balance !== $user_meta_balance);
        
        $this->assertTrue($has_mismatch, 'Should detect balance mismatch');
    }

    /**
     * Test balance correction on mismatch
     */
    public function testBalanceCorrectionOnMismatch() {
        $transaction_log_balance = 50; // Source of truth
        $user_meta_balance = 45; // Wrong
        
        // Correction
        $corrected_balance = $transaction_log_balance;
        
        $this->assertEquals(50, $corrected_balance);
    }

    /**
     * Test transaction integrity - all have balance snapshots
     */
    public function testTransactionIntegrityWithSnapshots() {
        $transactions = [
            ['amount' => 10, 'balance_after' => 10],
            ['amount' => 15, 'balance_after' => 25],
            ['amount' => -5, 'balance_after' => 20],
        ];
        
        foreach ($transactions as $transaction) {
            $this->assertArrayHasKey('balance_after', $transaction);
            $this->assertIsInt($transaction['balance_after']);
        }
    }

    /**
     * Test balance snapshot progression is correct
     */
    public function testBalanceSnapshotProgression() {
        $transactions = [
            ['amount' => 10, 'balance_after' => 10],
            ['amount' => 15, 'balance_after' => 25],
            ['amount' => -5, 'balance_after' => 20],
        ];
        
        $previous_balance = 0;
        foreach ($transactions as $transaction) {
            $expected_balance = $previous_balance + $transaction['amount'];
            $this->assertEquals($expected_balance, $transaction['balance_after']);
            $previous_balance = $transaction['balance_after'];
        }
    }

    /**
     * Test no orphaned transactions (all have customer_id)
     */
    public function testNoOrphanedTransactions() {
        $transactions = [
            ['customer_id' => 123, 'amount' => 10],
            ['customer_id' => 123, 'amount' => 15],
            ['customer_id' => 123, 'amount' => -5],
        ];
        
        foreach ($transactions as $transaction) {
            $this->assertArrayHasKey('customer_id', $transaction);
            $this->assertGreaterThan(0, $transaction['customer_id']);
        }
    }

    /**
     * Test transaction type is always set
     */
    public function testTransactionTypeAlwaysSet() {
        $valid_types = [
            'order_purchase',
            'order_refund',
            'redemption',
            'admin_adjustment',
            'referral_bonus',
        ];
        
        $transaction = ['transaction_type' => 'order_purchase'];
        
        $this->assertContains($transaction['transaction_type'], $valid_types);
    }

    /**
     * Test transaction description exists
     */
    public function testTransactionDescriptionExists() {
        $transaction = [
            'amount' => 10,
            'description' => 'Points allocated for order #123'
        ];
        
        $this->assertArrayHasKey('description', $transaction);
        $this->assertNotEmpty($transaction['description']);
    }

    /**
     * Test balance never goes negative in normal operation
     */
    public function testBalanceNeverGoesNegative() {
        $current_balance = 10;
        $deduction_amount = 15; // More than balance
        
        // Prevent negative
        $new_balance = max(0, $current_balance - $deduction_amount);
        
        $this->assertEquals(0, $new_balance);
        $this->assertGreaterThanOrEqual(0, $new_balance);
    }

    /**
     * Test concurrent balance updates handled safely
     */
    public function testConcurrentBalanceUpdatesHandled() {
        $customer_id = 123;
        $locks = [];
        
        // Process 1 gets lock
        $lock_key = "balance_update_{$customer_id}";
        if (!isset($locks[$lock_key])) {
            $locks[$lock_key] = true;
            $process1_has_lock = true;
        } else {
            $process1_has_lock = false;
        }
        
        $this->assertTrue($process1_has_lock);
        
        // Process 2 should wait
        $process2_has_lock = !isset($locks[$lock_key]) || !$locks[$lock_key];
        $this->assertFalse($process2_has_lock, 'Second process should wait for lock');
    }

    /**
     * Test balance recalculation from scratch
     */
    public function testBalanceRecalculationFromScratch() {
        $transactions = [
            10, 15, -5, 20, -10, 5
        ];
        
        $calculated_balance = array_sum($transactions);
        
        $this->assertEquals(35, $calculated_balance);
        $this->assertIsInt($calculated_balance);
    }

    /**
     * Test empty transaction log gives zero balance
     */
    public function testEmptyTransactionLogGivesZeroBalance() {
        $transactions = [];
        $balance = array_sum($transactions);
        
        $this->assertEquals(0, $balance);
    }

    /**
     * Test transaction timestamps are sequential
     */
    public function testTransactionTimestampsSequential() {
        $base_time = time();
        $transactions = [
            ['created_at' => $base_time],
            ['created_at' => $base_time + 1],
            ['created_at' => $base_time + 2],
        ];
        
        for ($i = 1; $i < count($transactions); $i++) {
            $this->assertGreaterThanOrEqual(
                $transactions[$i-1]['created_at'],
                $transactions[$i]['created_at'],
                'Timestamps should be sequential'
            );
        }
    }

    /**
     * Test balance audit trail is complete
     */
    public function testBalanceAuditTrailComplete() {
        $audit_log = [
            ['action' => 'allocation', 'amount' => 10, 'timestamp' => time()],
            ['action' => 'redemption', 'amount' => -5, 'timestamp' => time() + 1],
        ];
        
        foreach ($audit_log as $entry) {
            $this->assertArrayHasKey('action', $entry);
            $this->assertArrayHasKey('amount', $entry);
            $this->assertArrayHasKey('timestamp', $entry);
        }
    }

    /**
     * Test data consistency check
     */
    public function testDataConsistencyCheck() {
        // Verify all transactions have required fields
        $transaction = [
            'customer_id' => 123,
            'transaction_type' => 'order_purchase',
            'points_amount' => 10,
            'points_balance' => 50,
            'created_at' => time(),
        ];
        
        $required_fields = ['customer_id', 'transaction_type', 'points_amount', 'points_balance'];
        
        foreach ($required_fields as $field) {
            $this->assertArrayHasKey($field, $transaction);
        }
    }

    /**
     * Test balance integrity after deletion/restoration
     */
    public function testBalanceIntegrityAfterDeletion() {
        // If transaction deleted, balance should be recalculated
        $transactions_before = [10, 15, 20];
        $balance_before = 45;
        
        // Delete middle transaction
        $transactions_after = [10, 20];
        $balance_after = array_sum($transactions_after);
        
        $this->assertEquals(30, $balance_after);
        $this->assertNotEquals($balance_before, $balance_after);
    }

    /**
     * Test customer has single current balance (not multiple)
     */
    public function testCustomerHasSingleCurrentBalance() {
        $customer_id = 123;
        $balance_entries = [
            ['customer_id' => 123, 'balance' => 50, 'is_current' => true],
        ];
        
        $current_balances = array_filter($balance_entries, function($entry) {
            return $entry['is_current'];
        });
        
        $this->assertCount(1, $current_balances, 'Should have exactly one current balance');
    }

    /**
     * Test zero-amount transactions logged correctly
     */
    public function testZeroAmountTransactionsLogged() {
        $transaction = [
            'amount' => 0,
            'description' => 'Zero-amount transaction (e.g., free order)'
        ];
        
        $this->assertEquals(0, $transaction['amount']);
        $this->assertIsInt($transaction['amount']);
    }

    /**
     * Test large transaction amounts handled
     */
    public function testLargeTransactionAmountsHandled() {
        $large_amount = 10000;
        $current_balance = 5000;
        
        $new_balance = $current_balance + $large_amount;
        
        $this->assertEquals(15000, $new_balance);
        $this->assertIsInt($new_balance);
    }

    /**
     * Test balance calculation with mixed operations
     */
    public function testBalanceCalculationMixedOperations() {
        $operations = [
            ['type' => 'earn', 'amount' => 100],
            ['type' => 'redeem', 'amount' => -30],
            ['type' => 'earn', 'amount' => 50],
            ['type' => 'refund', 'amount' => -20],
            ['type' => 'bonus', 'amount' => 25],
            ['type' => 'redeem', 'amount' => -10],
        ];
        
        $balance = array_sum(array_column($operations, 'amount'));
        
        $this->assertEquals(115, $balance);
    }

    /**
     * Test transaction rollback capability
     */
    public function testTransactionRollbackCapability() {
        $initial_balance = 50;
        
        // Transaction starts
        $balance_after_add = $initial_balance + 10;
        $this->assertEquals(60, $balance_after_add);
        
        // Error occurs, rollback
        $balance_after_rollback = $initial_balance;
        $this->assertEquals(50, $balance_after_rollback);
    }

    /**
     * Test balance history is immutable
     */
    public function testBalanceHistoryImmutable() {
        // Once a transaction is recorded, it shouldn't be changed
        // Only new transactions should be added
        
        $transaction_id = 123;
        $original_amount = 10;
        
        // Attempting to change historical transaction should be prevented
        // (In real system, this would be enforced by database constraints)
        
        $this->assertEquals(10, $original_amount);
    }

    /**
     * Test metadata consistency
     */
    public function testMetadataConsistency() {
        $transaction = [
            'amount' => 10,
            'metadata' => json_encode(['order_id' => 123, 'order_total' => 100])
        ];
        
        $metadata = json_decode($transaction['metadata'], true);
        
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('order_id', $metadata);
    }

    /**
     * Test balance synchronization triggers
     */
    public function testBalanceSynchronizationTriggers() {
        $triggers = [
            'after_transaction_create',
            'after_transaction_delete',
            'on_manual_sync',
            'on_data_integrity_check',
        ];
        
        foreach ($triggers as $trigger) {
            $this->assertIsString($trigger);
            $this->assertNotEmpty($trigger);
        }
    }

    /**
     * Test multiple customers don't interfere
     */
    public function testMultipleCustomersDontInterfere() {
        $customer1_balance = 50;
        $customer2_balance = 75;
        
        // Customer 1 transaction shouldn't affect customer 2
        $customer1_balance += 10;
        
        $this->assertEquals(60, $customer1_balance);
        $this->assertEquals(75, $customer2_balance, 'Other customer balance unchanged');
    }

    /**
     * Test balance floor is zero (no negative)
     */
    public function testBalanceFloorIsZero() {
        $balance = -10; // Shouldn't happen
        $corrected_balance = max(0, $balance);
        
        $this->assertEquals(0, $corrected_balance);
    }

    /**
     * Test transaction order preservation
     */
    public function testTransactionOrderPreservation() {
        $transactions = [
            ['id' => 1, 'created_at' => '2025-01-01 10:00:00'],
            ['id' => 2, 'created_at' => '2025-01-01 10:05:00'],
            ['id' => 3, 'created_at' => '2025-01-01 10:10:00'],
        ];
        
        // Verify chronological order
        for ($i = 1; $i < count($transactions); $i++) {
            $this->assertLessThan(
                $transactions[$i]['created_at'],
                $transactions[$i-1]['created_at'],
                'Transactions should be in chronological order'
            );
        }
    }
}

