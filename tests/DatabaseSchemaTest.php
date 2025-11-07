<?php

use PHPUnit\Framework\TestCase;

/**
 * Test suite for Database Schema Validation
 * 
 * Ensures database schema uses INT(11) for points fields (not DECIMAL)
 * Prevents regression to fractional points
 */
class DatabaseSchemaTest extends TestCase {

    /**
     * Test that schema definition uses INT for points_amount
     */
    public function testSchemaUsesIntegerForPointsAmount() {
        // Read the schema from customer-referral-system.php
        $plugin_file = __DIR__ . '/../customer-referral-system.php';
        $content = file_get_contents($plugin_file);
        
        // Check points_log table schema
        $this->assertStringContainsString(
            'points_amount int(11) NOT NULL',
            $content,
            'Schema must use INT(11) for points_amount, not DECIMAL(10,2)'
        );
        
        // Should NOT contain decimal definition for points_amount
        $this->assertStringNotContainsString(
            'points_amount decimal(10,2)',
            $content,
            'REGRESSION: Schema reverted to DECIMAL for points_amount!'
        );
    }

    /**
     * Test that schema definition uses INT for points_balance
     */
    public function testSchemaUsesIntegerForPointsBalance() {
        $plugin_file = __DIR__ . '/../customer-referral-system.php';
        $content = file_get_contents($plugin_file);
        
        // Check points_balance is INT
        $this->assertStringContainsString(
            'points_balance int(11) NOT NULL',
            $content,
            'Schema must use INT(11) for points_balance, not DECIMAL(10,2)'
        );
        
        // Should NOT contain decimal definition
        $this->assertStringNotContainsString(
            'points_balance decimal(10,2)',
            $content,
            'REGRESSION: Schema reverted to DECIMAL for points_balance!'
        );
    }

    /**
     * Test that schema definition uses INT for points_awarded
     */
    public function testSchemaUsesIntegerForPointsAwarded() {
        $plugin_file = __DIR__ . '/../customer-referral-system.php';
        $content = file_get_contents($plugin_file);
        
        // Check referral_rewards table
        $this->assertStringContainsString(
            'points_awarded int(11) NOT NULL DEFAULT 0',
            $content,
            'Schema must use INT(11) for points_awarded in referral_rewards table'
        );
        
        // Should NOT contain decimal definition for points_awarded in referral_rewards
        $this->assertStringNotContainsString(
            'points_awarded decimal(10,2) NOT NULL DEFAULT \'0.00\'',
            $content,
            'REGRESSION: Schema reverted to DECIMAL for points_awarded in referral_rewards!'
        );
    }

    /**
     * Test that purchase_rewards table correctly uses INT (should already be correct)
     */
    public function testPurchaseRewardsUsesInteger() {
        $plugin_file = __DIR__ . '/../customer-referral-system.php';
        $content = file_get_contents($plugin_file);
        
        // purchase_rewards should already be using INT
        $this->assertStringContainsString(
            'points_awarded int(11) NOT NULL DEFAULT 0',
            $content,
            'purchase_rewards table should use INT(11) for points_awarded'
        );
    }

    /**
     * Test that schema has helpful comments documenting integer-only change
     */
    public function testSchemaHasDocumentationComments() {
        $plugin_file = __DIR__ . '/../customer-referral-system.php';
        $content = file_get_contents($plugin_file);
        
        // Should have comments explaining the change
        $this->assertStringContainsString(
            'Integer points only',
            $content,
            'Schema should document that points are integer-only'
        );
    }

    /**
     * Test all points-related columns use INT not DECIMAL
     */
    public function testNoDecimalColumnsForPoints() {
        $plugin_file = __DIR__ . '/../customer-referral-system.php';
        $content = file_get_contents($plugin_file);
        
        // Extract all CREATE TABLE statements
        preg_match_all('/CREATE TABLE.*?\) \$charset_collate;/s', $content, $matches);
        
        foreach ($matches[0] as $table_sql) {
            // If it mentions 'points' in column name, should NOT use decimal
            if (preg_match('/(\w*points\w*)\s+decimal/i', $table_sql, $violation)) {
                $this->fail(
                    "REGRESSION: Found DECIMAL type for points column: {$violation[1]}\n" .
                    "All points columns must use INT(11), not DECIMAL(10,2)"
                );
            }
        }
        
        // If we get here, all points columns use INT (or no decimal columns)
        $this->assertTrue(true);
    }

    /**
     * Test schema version tracking
     */
    public function testSchemaVersionUpdated() {
        $plugin_file = __DIR__ . '/../customer-referral-system.php';
        $content = file_get_contents($plugin_file);
        
        // Should reference Nov 4, 2025 update in comments
        $this->assertStringContainsString(
            'Nov 4, 2025',
            $content,
            'Schema should document when integer-only points were implemented'
        );
    }

    /**
     * Test that discount_amount still uses DECIMAL (should not be changed)
     */
    public function testDiscountAmountStaysDecimal() {
        $plugin_file = __DIR__ . '/../customer-referral-system.php';
        $content = file_get_contents($plugin_file);
        
        // discount_amount should remain DECIMAL (it's CHF currency, not points)
        $this->assertStringContainsString(
            'discount_amount decimal(10,2)',
            $content,
            'discount_amount should remain DECIMAL (it represents CHF currency)'
        );
    }

    /**
     * Test that order_total still uses DECIMAL (should not be changed)
     */
    public function testOrderTotalStaysDecimal() {
        $plugin_file = __DIR__ . '/../customer-referral-system.php';
        $content = file_get_contents($plugin_file);
        
        // order_total should remain DECIMAL (it's CHF currency, not points)
        $this->assertStringContainsString(
            'order_total decimal(10,2)',
            $content,
            'order_total should remain DECIMAL (it represents CHF currency)'
        );
    }

    /**
     * Test schema consistency across all points tables
     */
    public function testSchemaConsistency() {
        $plugin_file = __DIR__ . '/../customer-referral-system.php';
        $content = file_get_contents($plugin_file);
        
        // All points_awarded columns should use INT consistently
        $points_awarded_matches = [];
        preg_match_all('/points_awarded\s+(\w+\(\d+,?\d*\))/i', $content, $points_awarded_matches);
        
        foreach ($points_awarded_matches[1] as $type) {
            $this->assertStringContainsString(
                'int',
                strtolower($type),
                "All points_awarded columns must use INT, found: {$type}"
            );
        }
    }

    /**
     * Test that schema changes don't break existing functionality
     */
    public function testSchemaBackwardCompatibility() {
        $plugin_file = __DIR__ . '/../customer-referral-system.php';
        $content = file_get_contents($plugin_file);
        
        // Verify all required tables are still defined
        $required_tables = [
            'intersoccer_points_log',
            'intersoccer_referral_rewards',
            'intersoccer_purchase_rewards',
            'intersoccer_referrals',
            'intersoccer_credit_redemptions',
            'intersoccer_coach_events'
        ];
        
        foreach ($required_tables as $table) {
            $this->assertStringContainsString(
                $table,
                $content,
                "Required table {$table} must be in schema"
            );
        }
    }

    /**
     * Test coach events table schema includes unique key
     */
    public function testCoachEventsTableHasUniqueKey() {
        $plugin_file = __DIR__ . '/../customer-referral-system.php';
        $content = file_get_contents($plugin_file);

        $this->assertStringContainsString(
            'unique_coach_event (coach_id, event_id, event_type)',
            $content,
            'Coach events table must enforce unique coach/event combinations'
        );
    }
}

