<?php
/**
 * Points System Migration: DECIMAL to INT
 * 
 * Migrates all points data from DECIMAL(10,2) to INT(11) format
 * This ensures no fractional points exist in the system
 * 
 * @package InterSoccer_Referral_System
 * @since 1.12.0
 */

class InterSoccer_Points_Migration_Integers {

    private $points_log_table;
    private $referral_rewards_table;
    private $backup_suffix;

    public function __construct() {
        global $wpdb;
        $this->points_log_table = $wpdb->prefix . 'intersoccer_points_log';
        $this->referral_rewards_table = $wpdb->prefix . 'intersoccer_referral_rewards';
        $this->backup_suffix = '_backup_' . date('YmdHis');
    }

    /**
     * Check if migration is needed
     * 
     * @return bool True if migration needed, false if already completed
     */
    public function is_migration_needed() {
        $migration_status = get_option('intersoccer_points_integer_migration_status');
        return $migration_status !== 'completed';
    }

    /**
     * Get current migration status
     * 
     * @return array Migration status details
     */
    public function get_migration_status() {
        return [
            'status' => get_option('intersoccer_points_integer_migration_status', 'pending'),
            'started_at' => get_option('intersoccer_points_integer_migration_started'),
            'completed_at' => get_option('intersoccer_points_integer_migration_completed'),
            'backup_tables' => get_option('intersoccer_points_integer_migration_backup_tables'),
            'records_converted' => get_option('intersoccer_points_integer_migration_records'),
            'errors' => get_option('intersoccer_points_integer_migration_errors', [])
        ];
    }

    /**
     * Run the complete migration
     * 
     * @return array Migration results
     */
    public function run_migration() {
        global $wpdb;

        // Check if already completed
        if (!$this->is_migration_needed()) {
            return [
                'success' => false,
                'message' => 'Migration already completed',
                'status' => $this->get_migration_status()
            ];
        }

        // Mark migration as started
        update_option('intersoccer_points_integer_migration_status', 'in_progress');
        update_option('intersoccer_points_integer_migration_started', current_time('mysql'));

        $errors = [];
        $backup_tables = [];
        $records_converted = 0;

        try {
            // Step 1: Create backup of points_log table
            error_log('InterSoccer Migration: Creating backup of points_log table');
            $backup_points_log = $this->points_log_table . $this->backup_suffix;
            $result = $wpdb->query("CREATE TABLE {$backup_points_log} AS SELECT * FROM {$this->points_log_table}");
            
            if ($result === false) {
                throw new Exception("Failed to create backup of points_log table: " . $wpdb->last_error);
            }
            $backup_tables[] = $backup_points_log;
            error_log("InterSoccer Migration: Backup created: {$backup_points_log}");

            // Step 2: Convert all decimal points to integers in points_log
            error_log('InterSoccer Migration: Converting points_log records to integers');
            $records = $wpdb->get_results("SELECT id, points_amount, points_balance FROM {$this->points_log_table}");
            
            foreach ($records as $record) {
                $new_amount = (int) floor($record->points_amount);
                $new_balance = (int) floor($record->points_balance);
                
                $updated = $wpdb->update(
                    $this->points_log_table,
                    [
                        'points_amount' => $new_amount,
                        'points_balance' => $new_balance
                    ],
                    ['id' => $record->id],
                    ['%d', '%d'],
                    ['%d']
                );
                
                if ($updated !== false) {
                    $records_converted++;
                }
            }
            error_log("InterSoccer Migration: Converted {$records_converted} points_log records");

            // Step 3: Alter points_log table schema to INT(11)
            error_log('InterSoccer Migration: Altering points_log schema to INT(11)');
            $wpdb->query("ALTER TABLE {$this->points_log_table} MODIFY points_amount INT(11) NOT NULL");
            $wpdb->query("ALTER TABLE {$this->points_log_table} MODIFY points_balance INT(11) NOT NULL");
            
            if ($wpdb->last_error) {
                throw new Exception("Failed to alter points_log schema: " . $wpdb->last_error);
            }

            // Step 4: Create backup of referral_rewards table
            error_log('InterSoccer Migration: Creating backup of referral_rewards table');
            $backup_referral_rewards = $this->referral_rewards_table . $this->backup_suffix;
            $result = $wpdb->query("CREATE TABLE {$backup_referral_rewards} AS SELECT * FROM {$this->referral_rewards_table}");
            
            if ($result === false) {
                throw new Exception("Failed to create backup of referral_rewards table: " . $wpdb->last_error);
            }
            $backup_tables[] = $backup_referral_rewards;
            error_log("InterSoccer Migration: Backup created: {$backup_referral_rewards}");

            // Step 5: Convert all decimal points to integers in referral_rewards
            error_log('InterSoccer Migration: Converting referral_rewards records to integers');
            $rewards = $wpdb->get_results("SELECT id, points_awarded FROM {$this->referral_rewards_table}");
            
            foreach ($rewards as $reward) {
                $new_points = (int) floor($reward->points_awarded);
                
                $wpdb->update(
                    $this->referral_rewards_table,
                    ['points_awarded' => $new_points],
                    ['id' => $reward->id],
                    ['%d'],
                    ['%d']
                );
            }
            error_log("InterSoccer Migration: Converted " . count($rewards) . " referral_rewards records");

            // Step 6: Alter referral_rewards table schema to INT(11)
            error_log('InterSoccer Migration: Altering referral_rewards schema to INT(11)');
            $wpdb->query("ALTER TABLE {$this->referral_rewards_table} MODIFY points_awarded INT(11) NOT NULL DEFAULT 0");
            
            if ($wpdb->last_error) {
                throw new Exception("Failed to alter referral_rewards schema: " . $wpdb->last_error);
            }

            // Step 7: Update all user meta points balances to integers
            error_log('InterSoccer Migration: Updating user meta points balances');
            $user_metas = $wpdb->get_results(
                "SELECT umeta_id, user_id, meta_value 
                 FROM {$wpdb->usermeta} 
                 WHERE meta_key = 'intersoccer_points_balance'"
            );
            
            foreach ($user_metas as $meta) {
                $new_balance = (int) floor($meta->meta_value);
                $wpdb->update(
                    $wpdb->usermeta,
                    ['meta_value' => $new_balance],
                    ['umeta_id' => $meta->umeta_id],
                    ['%d'],
                    ['%d']
                );
            }
            error_log("InterSoccer Migration: Updated " . count($user_metas) . " user meta records");

            // Mark migration as complete
            update_option('intersoccer_points_integer_migration_status', 'completed');
            update_option('intersoccer_points_integer_migration_completed', current_time('mysql'));
            update_option('intersoccer_points_integer_migration_backup_tables', $backup_tables);
            update_option('intersoccer_points_integer_migration_records', $records_converted);

            error_log('InterSoccer Migration: COMPLETED SUCCESSFULLY');

            return [
                'success' => true,
                'message' => 'Migration completed successfully',
                'backup_tables' => $backup_tables,
                'records_converted' => $records_converted,
                'status' => $this->get_migration_status()
            ];

        } catch (Exception $e) {
            error_log('InterSoccer Migration ERROR: ' . $e->getMessage());
            $errors[] = $e->getMessage();
            
            // Mark migration as failed
            update_option('intersoccer_points_integer_migration_status', 'failed');
            update_option('intersoccer_points_integer_migration_errors', $errors);

            return [
                'success' => false,
                'message' => 'Migration failed: ' . $e->getMessage(),
                'errors' => $errors,
                'backup_tables' => $backup_tables
            ];
        }
    }

    /**
     * Rollback migration if needed
     * 
     * @return array Rollback results
     */
    public function rollback_migration() {
        global $wpdb;

        $status = $this->get_migration_status();
        $backup_tables = $status['backup_tables'];

        if (empty($backup_tables)) {
            return [
                'success' => false,
                'message' => 'No backup tables found to rollback'
            ];
        }

        try {
            foreach ($backup_tables as $backup_table) {
                // Determine original table name
                $original_table = str_replace($this->backup_suffix, '', $backup_table);
                
                // Drop current table
                $wpdb->query("DROP TABLE IF EXISTS {$original_table}");
                
                // Rename backup to original
                $wpdb->query("RENAME TABLE {$backup_table} TO {$original_table}");
            }

            // Reset migration status
            delete_option('intersoccer_points_integer_migration_status');
            delete_option('intersoccer_points_integer_migration_started');
            delete_option('intersoccer_points_integer_migration_completed');
            delete_option('intersoccer_points_integer_migration_backup_tables');
            delete_option('intersoccer_points_integer_migration_records');
            delete_option('intersoccer_points_integer_migration_errors');

            return [
                'success' => true,
                'message' => 'Migration rolled back successfully'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Rollback failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify migration integrity
     * 
     * @return array Verification results
     */
    public function verify_migration() {
        global $wpdb;

        $issues = [];

        // Check for any remaining decimal points in points_log
        $decimal_points = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->points_log_table} 
             WHERE points_amount != FLOOR(points_amount) 
             OR points_balance != FLOOR(points_balance)"
        );

        if ($decimal_points > 0) {
            $issues[] = "Found {$decimal_points} records with decimal points in points_log";
        }

        // Check for any remaining decimal points in referral_rewards
        $decimal_rewards = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$this->referral_rewards_table} 
             WHERE points_awarded != FLOOR(points_awarded)"
        );

        if ($decimal_rewards > 0) {
            $issues[] = "Found {$decimal_rewards} records with decimal points in referral_rewards";
        }

        // Check column types
        $points_log_schema = $wpdb->get_results(
            "SHOW COLUMNS FROM {$this->points_log_table} WHERE Field IN ('points_amount', 'points_balance')"
        );

        foreach ($points_log_schema as $column) {
            if (stripos($column->Type, 'decimal') !== false) {
                $issues[] = "Column {$column->Field} in points_log is still DECIMAL type";
            }
        }

        return [
            'success' => empty($issues),
            'issues' => $issues,
            'message' => empty($issues) ? 'Migration verified successfully' : 'Migration has issues'
        ];
    }
}

