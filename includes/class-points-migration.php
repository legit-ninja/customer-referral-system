<?php
/**
 * Points Migration Class
 * Migrates existing points from 1:1 ratio to 10:1 ratio (10 CHF = 1 point)
 */

class InterSoccer_Points_Migration {

    private $points_log_table;
    private $old_ratio = 1; // Old: 1 CHF = 1 point
    private $new_ratio = 0.1; // New: 1 CHF = 0.1 points (10 CHF = 1 point)

    public function __construct() {
        global $wpdb;
        $this->points_log_table = $wpdb->prefix . 'intersoccer_points_log';
    }

    /**
     * Run the complete migration
     */
    public function run_migration() {
        error_log('InterSoccer: Starting points migration from 1:1 to 10:1 ratio');

        // Backup current data
        $this->backup_current_data();

        // Migrate points log
        $this->migrate_points_log();

        // Migrate user meta balances
        $this->migrate_user_meta_balances();

        // Update metadata in transactions
        $this->update_transaction_metadata();

        // Log migration completion
        update_option('intersoccer_points_migration_completed', current_time('mysql'));
        update_option('intersoccer_points_migration_version', '1.1');

        error_log('InterSoccer: Points migration completed successfully');
    }

    /**
     * Backup current points data
     */
    private function backup_current_data() {
        global $wpdb;

        $backup_table = $this->points_log_table . '_backup_' . date('Y_m_d_H_i_s');

        $wpdb->query("CREATE TABLE {$backup_table} AS SELECT * FROM {$this->points_log_table}");

        update_option('intersoccer_points_backup_table', $backup_table);

        error_log("InterSoccer: Created backup table {$backup_table}");
    }

    /**
     * Migrate points log transactions
     */
    private function migrate_points_log() {
        global $wpdb;

        // Get all transactions that need migration (order_purchase and order_purchase_backfill)
        $transactions = $wpdb->get_results(
            "SELECT * FROM {$this->points_log_table}
             WHERE transaction_type IN ('order_purchase', 'order_purchase_backfill')
             AND (metadata LIKE '%\"points_rate\":\"1\"%' OR metadata LIKE '%\"points_rate\":1%')
             ORDER BY created_at ASC"
        );

        $migrated_count = 0;
        $total_points_adjusted = 0;

        foreach ($transactions as $transaction) {
            $metadata = json_decode($transaction->metadata, true);
            $old_points = $transaction->points_amount;

            // Only migrate if it was using the old ratio
            if (isset($metadata['points_rate']) && $metadata['points_rate'] == $this->old_ratio) {
                $order_total = $metadata['order_total'] ?? 0;

                if ($order_total > 0) {
                    // Calculate new points amount (10 CHF = 1 point)
                    $new_points = round($order_total / 10, 2);
                    $points_difference = $new_points - $old_points;

                    if ($points_difference != 0) {
                        // Update the transaction
                        $wpdb->update(
                            $this->points_log_table,
                            [
                                'points_amount' => $new_points,
                                'points_balance' => $transaction->points_balance + $points_difference,
                                'metadata' => json_encode(array_merge($metadata, [
                                    'migrated' => true,
                                    'old_points' => $old_points,
                                    'migration_date' => current_time('mysql'),
                                    'points_rate' => $this->new_ratio
                                ]))
                            ],
                            ['id' => $transaction->id],
                            ['%f', '%f', '%s'],
                            ['%d']
                        );

                        // Update subsequent balances for this customer
                        $this->update_subsequent_balances($transaction->customer_id, $transaction->created_at, $points_difference);

                        $migrated_count++;
                        $total_points_adjusted += $points_difference;

                        error_log("InterSoccer: Migrated transaction {$transaction->id}: {$old_points} -> {$new_points} points");
                    }
                }
            }
        }

        error_log("InterSoccer: Migrated {$migrated_count} transactions, adjusted {$total_points_adjusted} total points");
    }

    /**
     * Update subsequent transaction balances after a migration
     */
    private function update_subsequent_balances($customer_id, $after_date, $adjustment) {
        global $wpdb;

        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->points_log_table}
             SET points_balance = points_balance + %f
             WHERE customer_id = %d
             AND created_at > %s
             ORDER BY created_at ASC, id ASC",
            $adjustment, $customer_id, $after_date
        ));
    }

    /**
     * Migrate user meta balances
     */
    private function migrate_user_meta_balances() {
        global $wpdb;

        // Get all users with points balances
        $users_with_points = $wpdb->get_results(
            "SELECT user_id, meta_value as balance
             FROM {$wpdb->usermeta}
             WHERE meta_key = 'intersoccer_points_balance'
             AND meta_value > 0"
        );

        $migrated_users = 0;

        foreach ($users_with_points as $user) {
            $old_balance = floatval($user->balance);

            // For existing balances, we need to determine if they were calculated with old or new ratio
            // Since we're migrating from old system, assume old ratio and convert
            $new_balance = round($old_balance / 10, 2); // Convert from old 1:1 to new 10:1 ratio

            if ($new_balance != $old_balance) {
                update_user_meta($user->user_id, 'intersoccer_points_balance', $new_balance);
                update_user_meta($user->user_id, 'intersoccer_points_migrated', current_time('mysql'));
                update_user_meta($user->user_id, 'intersoccer_old_balance', $old_balance);

                $migrated_users++;
                error_log("InterSoccer: Migrated user {$user->user_id} balance: {$old_balance} -> {$new_balance}");
            }
        }

        error_log("InterSoccer: Migrated {$migrated_users} user balances");
    }

    /**
     * Update transaction metadata to reflect new ratio
     */
    private function update_transaction_metadata() {
        global $wpdb;

        // Update metadata for all transactions to reflect new ratio
        $transactions = $wpdb->get_results(
            "SELECT id, metadata FROM {$this->points_log_table}
             WHERE metadata LIKE '%points_rate%'"
        );

        foreach ($transactions as $transaction) {
            $metadata = json_decode($transaction->metadata, true);

            if (isset($metadata['points_rate']) && $metadata['points_rate'] == $this->old_ratio) {
                $metadata['points_rate'] = $this->new_ratio;
                $metadata['ratio_migrated'] = true;

                $wpdb->update(
                    $this->points_log_table,
                    ['metadata' => json_encode($metadata)],
                    ['id' => $transaction->id],
                    ['%s'],
                    ['%d']
                );
            }
        }
    }

    /**
     * Get migration status
     */
    public function get_migration_status() {
        return [
            'completed' => get_option('intersoccer_points_migration_completed', false),
            'version' => get_option('intersoccer_points_migration_version', '1.0'),
            'backup_table' => get_option('intersoccer_points_backup_table', false)
        ];
    }

    /**
     * Rollback migration (use with caution)
     */
    public function rollback_migration() {
        $backup_table = get_option('intersoccer_points_backup_table');

        if (!$backup_table) {
            error_log('InterSoccer: No backup table found for rollback');
            return false;
        }

        global $wpdb;

        // Restore from backup
        $wpdb->query("TRUNCATE TABLE {$this->points_log_table}");
        $wpdb->query("INSERT INTO {$this->points_log_table} SELECT * FROM {$backup_table}");

        // Restore user meta balances
        $users_with_old_balance = $wpdb->get_results(
            "SELECT user_id, meta_value as old_balance
             FROM {$wpdb->usermeta}
             WHERE meta_key = 'intersoccer_old_balance'"
        );

        foreach ($users_with_old_balance as $user) {
            update_user_meta($user->user_id, 'intersoccer_points_balance', $user->old_balance);
            delete_user_meta($user->user_id, 'intersoccer_points_migrated');
            delete_user_meta($user->user_id, 'intersoccer_old_balance');
        }

        // Clear migration flags
        delete_option('intersoccer_points_migration_completed');
        delete_option('intersoccer_points_migration_version');

        error_log('InterSoccer: Migration rolled back successfully');
        return true;
    }

    /**
     * Clean up old backup tables (keep last 3)
     */
    public function cleanup_old_backups() {
        global $wpdb;

        $backup_tables = $wpdb->get_col(
            "SHOW TABLES LIKE '{$this->points_log_table}_backup_%'"
        );

        if (count($backup_tables) > 3) {
            // Sort by date and keep newest 3
            rsort($backup_tables);
            $tables_to_drop = array_slice($backup_tables, 3);

            foreach ($tables_to_drop as $table) {
                $wpdb->query("DROP TABLE {$table}");
                error_log("InterSoccer: Dropped old backup table {$table}");
            }
        }
    }
}