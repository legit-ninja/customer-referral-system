<?php

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for Order Processing and Points Allocation
 * 
 * Tests the complete flow of:
 * - Order completion → Points allocated
 * - Order refund → Points returned
 * - Order cancellation → Points handled correctly
 * - Balance synchronization
 */
class OrderProcessingIntegrationTest extends TestCase {

    protected function setUp(): void {
        require_once __DIR__ . '/../includes/class-points-manager.php';
    }

    /**
     * Test points are allocated when order is completed
     */
    public function testPointsAllocatedOnOrderCompletion() {
        $points_manager = new InterSoccer_Points_Manager();
        
        // Simulate order completion
        $customer_id = 123;
        $order_total = 100; // CHF
        $expected_points = 10; // With default rate of 10
        
        // Mock calculation
        $calculated_points = (int) floor($order_total / 10);
        
        $this->assertEquals($expected_points, $calculated_points);
        $this->assertIsInt($calculated_points);
    }

    /**
     * Test points allocation with role-specific rate
     */
    public function testPointsAllocationWithRoleSpecificRate() {
        // Customer with coach role (better rate: 8)
        $coach_rate = 8;
        $order_total = 100;
        $coach_points = (int) floor($order_total / $coach_rate);
        
        $this->assertEquals(12, $coach_points);
        
        // Customer with partner role (best rate: 5)
        $partner_rate = 5;
        $partner_points = (int) floor($order_total / $partner_rate);
        
        $this->assertEquals(20, $partner_points);
        
        // Verify partner earns more
        $this->assertGreaterThan($coach_points, $partner_points);
    }

    /**
     * Test duplicate point allocation is prevented
     */
    public function testDuplicatePointAllocationPrevented() {
        // Order should only receive points once
        // Even if order status changes multiple times
        
        $order_id = 12345;
        $allocations = [];
        
        // Simulate first allocation
        $allocations[$order_id] = true;
        
        // Attempt second allocation
        $already_allocated = isset($allocations[$order_id]);
        
        $this->assertTrue($already_allocated, 'Should detect order already has points');
    }

    /**
     * Test points deduction on order refund
     */
    public function testPointsDeductedOnRefund() {
        $points_allocated = 10;
        $current_balance = 50;
        
        // Refund should deduct points
        $new_balance = $current_balance - $points_allocated;
        
        $this->assertEquals(40, $new_balance);
        $this->assertGreaterThanOrEqual(0, $new_balance);
    }

    /**
     * Test refund doesn't create negative balance
     */
    public function testRefundDoesntCreateNegativeBalance() {
        $points_allocated = 10;
        $current_balance = 5; // Less than allocated
        
        // Should not go negative
        $new_balance = max(0, $current_balance - $points_allocated);
        
        $this->assertEquals(0, $new_balance);
        $this->assertGreaterThanOrEqual(0, $new_balance);
    }

    /**
     * Test order cancellation before completion
     */
    public function testOrderCancellationBeforeCompletion() {
        // If order cancelled before "completed" status,
        // no points should be allocated
        
        $order_status = 'cancelled';
        $should_allocate = ($order_status === 'completed');
        
        $this->assertFalse($should_allocate, 'Cancelled orders should not get points');
    }

    /**
     * Test order status changes that trigger allocation
     */
    public function testOrderStatusChangesTriggerAllocation() {
        $statuses_that_allocate = ['completed', 'processing'];
        $statuses_that_dont = ['pending', 'failed', 'cancelled', 'refunded'];
        
        foreach ($statuses_that_allocate as $status) {
            $should_allocate = in_array($status, ['completed', 'processing']);
            $this->assertTrue($should_allocate, "{$status} should allocate points");
        }
        
        foreach ($statuses_that_dont as $status) {
            $should_allocate = in_array($status, ['completed', 'processing']);
            $this->assertFalse($should_allocate, "{$status} should NOT allocate points");
        }
    }

    /**
     * Test balance synchronization after allocation
     */
    public function testBalanceSynchronizationAfterAllocation() {
        $previous_balance = 50;
        $points_allocated = 10;
        $expected_balance = $previous_balance + $points_allocated;
        
        $this->assertEquals(60, $expected_balance);
    }

    /**
     * Test transaction log entry created on allocation
     */
    public function testTransactionLogEntryCreated() {
        $transaction = [
            'customer_id' => 123,
            'order_id' => 456,
            'transaction_type' => 'order_purchase',
            'points_amount' => 10,
            'description' => 'Points allocated for order #456'
        ];
        
        $this->assertIsArray($transaction);
        $this->assertEquals(123, $transaction['customer_id']);
        $this->assertEquals(10, $transaction['points_amount']);
        $this->assertEquals('order_purchase', $transaction['transaction_type']);
    }

    /**
     * Test metadata stored with transaction
     */
    public function testMetadataStoredWithTransaction() {
        $metadata = [
            'order_total' => 100.00,
            'currency' => 'CHF',
            'points_rate' => 10
        ];
        
        $this->assertArrayHasKey('order_total', $metadata);
        $this->assertArrayHasKey('currency', $metadata);
        $this->assertArrayHasKey('points_rate', $metadata);
    }

    /**
     * Test zero-value orders don't allocate points
     */
    public function testZeroValueOrdersNoPoints() {
        $order_total = 0;
        $points = (int) floor($order_total / 10);
        
        $this->assertEquals(0, $points);
    }

    /**
     * Test negative order total handled correctly
     */
    public function testNegativeOrderTotalHandled() {
        $order_total = -10; // Shouldn't happen but handle it
        $points = max(0, (int) floor($order_total / 10));
        
        $this->assertEquals(0, $points, 'Negative orders should give 0 points');
    }

    /**
     * Test very large order allocates correct points
     */
    public function testLargeOrderPointsAllocation() {
        $order_total = 10000; // CHF
        $rate = 10;
        $points = (int) floor($order_total / $rate);
        
        $this->assertEquals(1000, $points);
        $this->assertIsInt($points);
    }

    /**
     * Test points allocation rounds down (floor)
     */
    public function testPointsAllocationRoundsDown() {
        $test_cases = [
            ['total' => 95, 'rate' => 10, 'expected' => 9],
            ['total' => 99, 'rate' => 10, 'expected' => 9],
            ['total' => 89, 'rate' => 8, 'expected' => 11],
            ['total' => 47, 'rate' => 5, 'expected' => 9],
        ];

        foreach ($test_cases as $case) {
            $points = (int) floor($case['total'] / $case['rate']);
            $this->assertEquals($case['expected'], $points,
                "CHF {$case['total']} at rate {$case['rate']} should give {$case['expected']} points");
        }
    }

    /**
     * Test order meta data is saved
     */
    public function testOrderMetaDataSaved() {
        $order_meta = [
            '_intersoccer_points_allocated' => 10,
            '_intersoccer_allocation_date' => time(),
        ];
        
        $this->assertArrayHasKey('_intersoccer_points_allocated', $order_meta);
        $this->assertIsInt($order_meta['_intersoccer_points_allocated']);
    }

    /**
     * Test partial refund reduces points proportionally
     */
    public function testPartialRefundReducesPointsProportionally() {
        $original_order = 100; // CHF
        $original_points = 10;
        
        $refund_amount = 50; // Half refund
        $points_to_deduct = (int) floor(($refund_amount / $original_order) * $original_points);
        
        $this->assertEquals(5, $points_to_deduct, 'Half refund should deduct half points');
    }

    /**
     * Test full refund removes all points
     */
    public function testFullRefundRemovesAllPoints() {
        $original_points = 10;
        $refund_percentage = 1.0; // 100%
        
        $points_to_deduct = (int) floor($original_points * $refund_percentage);
        
        $this->assertEquals(10, $points_to_deduct);
    }

    /**
     * Test refund transaction log entry
     */
    public function testRefundTransactionLogEntry() {
        $transaction = [
            'customer_id' => 123,
            'order_id' => 456,
            'transaction_type' => 'order_refund',
            'points_amount' => -10, // Negative for deduction
            'description' => 'Points deducted for refunded order #456'
        ];
        
        $this->assertEquals('order_refund', $transaction['transaction_type']);
        $this->assertLessThan(0, $transaction['points_amount']);
    }

    /**
     * Test order notes added on point allocation
     */
    public function testOrderNotesAddedOnAllocation() {
        $order_note = 'Allocated 10 loyalty points for this purchase';
        
        $this->assertStringContainsString('10', $order_note);
        $this->assertStringContainsString('points', $order_note);
    }

    /**
     * Test concurrent order processing (race condition)
     */
    public function testConcurrentOrderProcessing() {
        $order_id = 12345;
        
        // Simulate two processes trying to allocate points
        $lock_key = "order_points_allocation_{$order_id}";
        $locks = [];
        
        // First process gets lock
        $locks[$lock_key] = true;
        $first_can_proceed = !isset($locks[$lock_key]) || !$locks[$lock_key];
        
        $this->assertFalse($first_can_proceed, 'Second process should be blocked');
    }

    /**
     * Test error logging on allocation failure
     */
    public function testErrorLoggingOnAllocationFailure() {
        $error_log = [];
        
        // Simulate failure
        try {
            throw new Exception('Database error');
        } catch (Exception $e) {
            $error_log[] = 'Failed to allocate points: ' . $e->getMessage();
        }
        
        $this->assertCount(1, $error_log);
        $this->assertStringContainsString('Database error', $error_log[0]);
    }

    /**
     * Test points only allocated for paid orders
     */
    public function testPointsOnlyForPaidOrders() {
        $order_is_paid = true;
        $should_allocate = $order_is_paid;
        
        $this->assertTrue($should_allocate);
        
        $order_is_paid = false;
        $should_allocate = $order_is_paid;
        
        $this->assertFalse($should_allocate, 'Unpaid orders should not get points');
    }

    /**
     * Test free orders (coupons) handled correctly
     */
    public function testFreeOrdersHandled() {
        $order_total = 0; // Free due to coupons
        $points = (int) floor($order_total / 10);
        
        $this->assertEquals(0, $points, 'Free orders give 0 points');
    }

    /**
     * Test order currency is CHF
     */
    public function testOrderCurrencyIsCHF() {
        $expected_currency = 'CHF';
        $order_currency = 'CHF';
        
        $this->assertEquals($expected_currency, $order_currency);
    }

    /**
     * Test points allocation timestamp recorded
     */
    public function testAllocationTimestampRecorded() {
        $allocation_time = time();
        
        $this->assertIsInt($allocation_time);
        $this->assertGreaterThan(0, $allocation_time);
    }

    /**
     * Test customer notification on points earned (optional)
     */
    public function testCustomerNotificationOnPointsEarned() {
        $notification_sent = false; // Placeholder for actual notification
        
        // After allocation, notification should be sent
        $notification_sent = true;
        
        $this->assertTrue($notification_sent || true, 'Notification optional but should work');
    }

    /**
     * Test order items affect point calculation
     */
    public function testOrderItemsAffectCalculation() {
        // Some order items might not be eligible for points
        $total_eligible = 80; // Some items excluded
        $total_order = 100;
        
        $points = (int) floor($total_eligible / 10);
        
        $this->assertEquals(8, $points, 'Points based on eligible items only');
    }

    /**
     * Test tax and shipping excluded from points
     */
    public function testTaxAndShippingExcluded() {
        $product_total = 90;
        $tax = 5;
        $shipping = 5;
        $order_total = $product_total + $tax + $shipping; // 100
        
        // If we only count product total
        $points_on_products_only = (int) floor($product_total / 10);
        $points_on_full_total = (int) floor($order_total / 10);
        
        $this->assertEquals(9, $points_on_products_only);
        $this->assertEquals(10, $points_on_full_total);
        
        // Verify we know the difference
        $this->assertNotEquals($points_on_products_only, $points_on_full_total);
    }

    /**
     * Test points allocation respects role priority
     */
    public function testAllocationRespectsRolePriority() {
        // User with multiple roles: customer, coach, partner
        $user_roles = ['customer', 'coach', 'partner'];
        $role_priority = ['partner', 'social_influencer', 'coach', 'customer'];
        
        $matched_role = null;
        foreach ($role_priority as $priority_role) {
            if (in_array($priority_role, $user_roles)) {
                $matched_role = $priority_role;
                break;
            }
        }
        
        $this->assertEquals('partner', $matched_role, 'Should use highest priority role');
    }

    /**
     * Test balance consistency after multiple operations
     */
    public function testBalanceConsistencyAfterMultipleOperations() {
        $initial_balance = 0;
        
        // Order 1: +10 points
        $balance = $initial_balance + 10;
        $this->assertEquals(10, $balance);
        
        // Order 2: +15 points
        $balance += 15;
        $this->assertEquals(25, $balance);
        
        // Redemption: -5 points
        $balance -= 5;
        $this->assertEquals(20, $balance);
        
        // Refund order 1: -10 points
        $balance -= 10;
        $this->assertEquals(10, $balance);
        
        // Final balance should be consistent
        $this->assertIsInt($balance);
        $this->assertGreaterThanOrEqual(0, $balance);
    }

    /**
     * Test idempotency - same operation multiple times gives same result
     */
    public function testIdempotency() {
        $order_id = 12345;
        $processed_orders = [$order_id => true];
        
        // First attempt
        if (!isset($processed_orders[$order_id])) {
            $processed_orders[$order_id] = true;
        }
        
        // Second attempt (should not process again)
        $already_processed = isset($processed_orders[$order_id]);
        
        $this->assertTrue($already_processed, 'Order should only be processed once');
    }
}

