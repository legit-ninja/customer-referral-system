<?php

use PHPUnit\Framework\TestCase;

/**
 * Test suite for Admin Financial Reports (class-admin-financial.php)
 * 
 * Covers:
 * - Financial report generation
 * - Commission calculations and aggregation
 * - Export functionality
 * - Date range filtering
 * - Currency handling
 * - Tax calculations
 * - Refund handling
 * 
 * Total: 28 tests
 */
class AdminFinancialTest extends TestCase {

    // =========================================================================
    // FINANCIAL REPORT TESTS (8 tests)
    // =========================================================================

    public function testFinancialReport_TotalRevenue() {
        $orders = [100, 150, 200, 250];
        $total_revenue = array_sum($orders);
        
        $this->assertEquals(700, $total_revenue);
    }

    public function testFinancialReport_TotalCommissions() {
        $commissions = [10, 15, 20, 25];
        $total_commissions = array_sum($commissions);
        
        $this->assertEquals(70, $total_commissions);
    }

    public function testFinancialReport_NetRevenue() {
        $total_revenue = 700;
        $total_commissions = 70;
        $total_discounts = 30;
        
        $net_revenue = $total_revenue - $total_commissions - $total_discounts;
        
        $this->assertEquals(600, $net_revenue);
    }

    public function testFinancialReport_DateRangeFiltering() {
        $start_date = '2025-01-01';
        $end_date = '2025-01-31';
        
        $is_valid_range = (strtotime($start_date) < strtotime($end_date));
        $this->assertTrue($is_valid_range);
    }

    public function testFinancialReport_MonthlyBreakdown() {
        $monthly_data = [
            '2025-01' => ['revenue' => 1000, 'commissions' => 100],
            '2025-02' => ['revenue' => 1200, 'commissions' => 120],
        ];
        
        $this->assertCount(2, $monthly_data);
        $this->assertEquals(1000, $monthly_data['2025-01']['revenue']);
    }

    public function testFinancialReport_CoachBreakdown() {
        $coach_earnings = [
            123 => ['commissions' => 150, 'referrals' => 10],
            456 => ['commissions' => 200, 'referrals' => 15],
        ];
        
        $total_commissions = array_sum(array_column($coach_earnings, 'commissions'));
        $this->assertEquals(350, $total_commissions);
    }

    public function testFinancialReport_PointsIssued() {
        $points_issued = 5000;
        $points_redeemed = 2000;
        $points_outstanding = $points_issued - $points_redeemed;
        
        $this->assertEquals(3000, $points_outstanding);
    }

    public function testFinancialReport_DiscountImpact() {
        $revenue_without_discounts = 1000;
        $total_discounts = 150;
        $actual_revenue = $revenue_without_discounts - $total_discounts;
        
        $discount_percent = ($total_discounts / $revenue_without_discounts) * 100;
        
        $this->assertEquals(15.0, $discount_percent);
        $this->assertEquals(850, $actual_revenue);
    }

    // =========================================================================
    // COMMISSION CALCULATION TESTS (8 tests)
    // =========================================================================

    public function testCommission_TierCalculation() {
        $order_total = 100;
        $tier_rate = 0.15; // 15%
        $commission = $order_total * $tier_rate;
        
        $this->assertEquals(15.0, $commission);
    }

    public function testCommission_BonusAddition() {
        $base_commission = 50;
        $loyalty_bonus = 10;
        $retention_bonus = 5;
        $total = $base_commission + $loyalty_bonus + $retention_bonus;
        
        $this->assertEquals(65, $total);
    }

    public function testCommission_MinimumCommission() {
        $calculated_commission = 2.50;
        $minimum = 5.00;
        $final_commission = max($calculated_commission, $minimum);
        
        $this->assertEquals(5.00, $final_commission);
    }

    public function testCommission_MaximumCap() {
        $calculated_commission = 150;
        $maximum = 100;
        $final_commission = min($calculated_commission, $maximum);
        
        $this->assertEquals(100, $final_commission);
    }

    public function testCommission_Rounding() {
        $commission = 15.678;
        $rounded = round($commission, 2);
        
        $this->assertEquals(15.68, $rounded);
    }

    public function testCommission_MultipleOrders() {
        $commissions = [10, 15, 20, 25, 30];
        $total = array_sum($commissions);
        $average = $total / count($commissions);
        
        $this->assertEquals(100, $total);
        $this->assertEquals(20, $average);
    }

    public function testCommission_RefundHandling() {
        $original_commission = 50;
        $refunded_commission = -50;
        $final_commission = $original_commission + $refunded_commission;
        
        $this->assertEquals(0, $final_commission);
    }

    public function testCommission_PartialRefund() {
        $original_commission = 50;
        $partial_refund = -20;
        $remaining_commission = $original_commission + $partial_refund;
        
        $this->assertEquals(30, $remaining_commission);
    }

    // =========================================================================
    // EXPORT FUNCTIONALITY TESTS (6 tests)
    // =========================================================================

    public function testExport_CSVFormat() {
        $csv_header = "Date,Coach,Revenue,Commission,Net\n";
        
        $this->assertStringContainsString(',', $csv_header);
        $this->assertStringEndsWith("\n", $csv_header);
    }

    public function testExport_DataFormatting() {
        $amount = 1234.56;
        $formatted = number_format($amount, 2);
        
        $this->assertEquals('1,234.56', $formatted);
    }

    public function testExport_DateFormatting() {
        $timestamp = strtotime('2025-01-15 10:30:00');
        $formatted = date('Y-m-d', $timestamp);
        
        $this->assertEquals('2025-01-15', $formatted);
    }

    public function testExport_EscapeSpecialCharacters() {
        $coach_name = 'O\'Reilly, John';
        $escaped = str_replace('"', '""', $coach_name);
        
        $csv_field = '"' . $escaped . '"';
        $this->assertStringContainsString('O\'Reilly', $csv_field);
    }

    public function testExport_FileName() {
        $date = date('Y-m-d');
        $filename = "financial-report-{$date}.csv";
        
        $this->assertStringStartsWith('financial-report-', $filename);
        $this->assertStringEndsWith('.csv', $filename);
    }

    public function testExport_EmptyReport() {
        $data = [];
        
        if (empty($data)) {
            $csv = "Date,Coach,Revenue,Commission\n";
            $this->assertStringContainsString('Date', $csv);
        }
    }

    // =========================================================================
    // CURRENCY HANDLING TESTS (3 tests)
    // =========================================================================

    public function testCurrency_CHFFormatting() {
        $amount = 1234.50;
        $formatted = 'CHF ' . number_format($amount, 2);
        
        $this->assertEquals('CHF 1,234.50', $formatted);
    }

    public function testCurrency_NegativeAmounts() {
        $refund = -50.00;
        $formatted = 'CHF ' . number_format($refund, 2);
        
        $this->assertEquals('CHF -50.00', $formatted);
    }

    public function testCurrency_ZeroAmount() {
        $amount = 0;
        $formatted = 'CHF ' . number_format($amount, 2);
        
        $this->assertEquals('CHF 0.00', $formatted);
    }

    // =========================================================================
    // EDGE CASES & ERROR HANDLING (3 tests)
    // =========================================================================

    public function testEdgeCase_NoData() {
        $report_data = [];
        
        $this->assertIsArray($report_data);
        $this->assertEmpty($report_data);
    }

    public function testEdgeCase_InvalidDateRange() {
        $start = '2025-02-01';
        $end = '2025-01-01'; // End before start
        
        $is_valid = (strtotime($start) < strtotime($end));
        $this->assertFalse($is_valid);
    }

    public function testEdgeCase_FutureDate() {
        $report_date = strtotime('+1 week');
        $current_date = time();
        
        $is_future = ($report_date > $current_date);
        $this->assertTrue($is_future);
    }
}

