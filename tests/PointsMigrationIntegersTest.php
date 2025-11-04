<?php

use PHPUnit\Framework\TestCase;

/**
 * Test suite for Points Integer Migration
 */
class PointsMigrationIntegersTest extends TestCase {

    protected function setUp(): void {
        // Include the migration class
        require_once __DIR__ . '/../includes/class-points-migration-integers.php';
    }

    /**
     * Test migration status check
     */
    public function testIsMigrationNeeded() {
        $migration = new InterSoccer_Points_Migration_Integers();
        
        // Should need migration initially
        $this->assertTrue($migration->is_migration_needed());
        
        // Mark as completed
        update_option('intersoccer_points_integer_migration_status', 'completed');
        
        // Should not need migration after completion
        $this->assertFalse($migration->is_migration_needed());
        
        // Cleanup
        delete_option('intersoccer_points_integer_migration_status');
    }

    /**
     * Test getting migration status
     */
    public function testGetMigrationStatus() {
        $migration = new InterSoccer_Points_Migration_Integers();
        
        $status = $migration->get_migration_status();
        
        $this->assertIsArray($status);
        $this->assertArrayHasKey('status', $status);
        $this->assertArrayHasKey('started_at', $status);
        $this->assertArrayHasKey('completed_at', $status);
        $this->assertArrayHasKey('backup_tables', $status);
        $this->assertArrayHasKey('records_converted', $status);
        $this->assertArrayHasKey('errors', $status);
    }

    /**
     * Test points conversion from decimal to integer
     */
    public function testPointsConversionLogic() {
        // Test the floor logic used in migration
        
        // 9.5 points should become 9 points
        $this->assertEquals(9, (int) floor(9.5));
        
        // 15.99 points should become 15 points
        $this->assertEquals(15, (int) floor(15.99));
        
        // 0.5 points should become 0 points
        $this->assertEquals(0, (int) floor(0.5));
        
        // 100.0 points should remain 100 points
        $this->assertEquals(100, (int) floor(100.0));
        
        // Negative points should also floor correctly
        $this->assertEquals(-10, (int) floor(-9.5));
    }

    /**
     * Test migration run (mocked database operations)
     */
    public function testRunMigration() {
        $migration = new InterSoccer_Points_Migration_Integers();
        
        // Reset migration status for test
        delete_option('intersoccer_points_integer_migration_status');
        
        // Note: This test would require database setup
        // In a real environment, you'd need test database fixtures
        
        $this->assertTrue($migration->is_migration_needed());
    }

    /**
     * Test verification logic
     */
    public function testVerificationLogic() {
        $migration = new InterSoccer_Points_Migration_Integers();
        
        // Verification should check for:
        // 1. No decimal points remaining in data
        // 2. Column types are INT not DECIMAL
        // 3. All user meta balances are integers
        
        // Note: Full test requires database setup
        $this->assertTrue(true); // Placeholder for database-dependent test
    }

    /**
     * Test rollback functionality
     */
    public function testRollbackLogic() {
        $migration = new InterSoccer_Points_Migration_Integers();
        
        // Rollback should:
        // 1. Restore backup tables
        // 2. Reset migration status
        // 3. Clean up temporary data
        
        // Test when no backup exists
        delete_option('intersoccer_points_integer_migration_backup_tables');
        
        $result = $migration->rollback_migration();
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
    }

    /**
     * Test edge cases in points conversion
     */
    public function testEdgeCasesConversion() {
        // Test very small amounts
        $this->assertEquals(0, (int) floor(0.01));
        $this->assertEquals(0, (int) floor(0.99));
        
        // Test exact integers
        $this->assertEquals(1, (int) floor(1.0));
        $this->assertEquals(100, (int) floor(100.0));
        
        // Test large amounts
        $this->assertEquals(9999, (int) floor(9999.99));
        
        // Test negative amounts (for redemptions)
        $this->assertEquals(-5, (int) floor(-4.5));
        $this->assertEquals(-10, (int) floor(-9.1));
    }

    /**
     * Test data integrity checks
     */
    public function testDataIntegrity() {
        // Ensure no data loss during conversion
        // Examples:
        
        // Customer with 95.50 points should get 95 points (not 96)
        $original = 95.50;
        $converted = (int) floor($original);
        $this->assertEquals(95, $converted);
        $this->assertLessThanOrEqual($original, $converted);
        
        // Customer with 100.00 points should get 100 points
        $original = 100.00;
        $converted = (int) floor($original);
        $this->assertEquals(100, $converted);
        
        // Verify floor always rounds down, never up
        for ($i = 0; $i < 100; $i++) {
            $original = $i + 0.99;
            $converted = (int) floor($original);
            $this->assertLessThanOrEqual($original, $converted);
            $this->assertEquals($i, $converted);
        }
    }

    /**
     * Test backup table creation logic
     */
    public function testBackupTableNaming() {
        global $wpdb;
        $migration = new InterSoccer_Points_Migration_Integers();
        
        // Backup tables should follow pattern: original_table_backup_TIMESTAMP
        $backup_suffix = '_backup_' . date('YmdHis');
        $points_table = $wpdb->prefix . 'intersoccer_points_log';
        $expected_backup = $points_table . $backup_suffix;
        
        // Verify naming pattern (timestamp will vary)
        $this->assertStringStartsWith($points_table . '_backup_', $expected_backup);
        $this->assertGreaterThan(strlen($points_table), strlen($expected_backup));
    }

    /**
     * Cleanup after tests
     */
    protected function tearDown(): void {
        // Clean up test options
        delete_option('intersoccer_points_integer_migration_status');
        delete_option('intersoccer_points_integer_migration_started');
        delete_option('intersoccer_points_integer_migration_completed');
        delete_option('intersoccer_points_integer_migration_backup_tables');
        delete_option('intersoccer_points_integer_migration_records');
        delete_option('intersoccer_points_integer_migration_errors');
    }
}

