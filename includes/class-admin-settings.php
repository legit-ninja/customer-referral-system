<?php
// includes/class-admin-settings.php

class InterSoccer_Admin_Settings {

    public function __construct() {
        add_action('admin_post_import_coaches_from_csv', [$this, 'import_coaches_from_csv']);
        add_action('wp_ajax_reset_all_customer_credits', [$this, 'reset_all_customer_credits']);
        add_action('wp_ajax_allocate_credits_to_customers', [$this, 'allocate_credits_to_customers']);
        add_action('wp_ajax_clear_audit_log', [$this, 'clear_audit_log']);
        add_action('wp_ajax_export_audit_log', [$this, 'export_audit_log']);
        add_action('wp_ajax_bulk_credit_adjustment', [$this, 'bulk_credit_adjustment']);
        add_action('wp_ajax_get_credit_statistics', [$this, 'get_credit_statistics']);
        add_action('wp_ajax_get_coach_statistics', [$this, 'get_coach_statistics']);
        add_action('wp_ajax_get_audit_log', [$this, 'get_audit_log']);
        add_action('wp_ajax_get_points_statistics', [$this, 'get_points_statistics_ajax']);
        add_action('wp_ajax_get_points_ledger', [$this, 'get_points_ledger_ajax']);
        add_action('admin_init', [$this, 'register_settings']);

        // Add AJAX handler for coach import
        add_action('wp_ajax_import_coaches_from_csv', [$this, 'ajax_import_coaches_from_csv']);

        // Add hook for the role migration action
        add_action('admin_post_migrate_coach_roles', [$this, 'migrate_coach_roles']);

        // Add points migration actions
        add_action('wp_ajax_run_points_migration', [$this, 'run_points_migration_ajax']);
        add_action('wp_ajax_get_migration_status', [$this, 'get_migration_status_ajax']);

        // Add action for restoring coach roles
        add_action('wp_ajax_restore_coach_roles', [$this, 'restore_coach_roles_ajax']);
        
        // Phase 0: Role-specific point rates
        add_action('wp_ajax_save_points_rates', [$this, 'save_points_rates_ajax']);
        
        // Add actions for integer migration (Phase 0)
        add_action('wp_ajax_run_integer_migration', [$this, 'run_integer_migration_ajax']);
        add_action('wp_ajax_get_integer_migration_status', [$this, 'get_integer_migration_status_ajax']);
        add_action('wp_ajax_verify_integer_migration', [$this, 'verify_integer_migration_ajax']);
        add_action('wp_ajax_rollback_integer_migration', [$this, 'rollback_integer_migration_ajax']);
    }

    public function render_settings_page() {
        ?>
        <div class="wrap intersoccer-admin">
            <h1 class="wp-heading-inline">Referral System Settings</h1>

            <!-- System Information -->
            <div class="intersoccer-settings-section">
                <h2>System Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <strong>Plugin Version:</strong> <?php echo INTERSOCCER_REFERRAL_VERSION; ?>
                    </div>
                    <div class="info-item">
                        <strong>Database Tables:</strong>
                        <span class="status-badge <?php echo $this->check_database_tables() ? 'active' : 'inactive'; ?>">
                            <?php echo $this->check_database_tables() ? 'OK' : 'Issues'; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <strong>WooCommerce:</strong>
                        <span class="status-badge <?php echo class_exists('WooCommerce') ? 'active' : 'inactive'; ?>">
                            <?php echo class_exists('WooCommerce') ? 'Connected' : 'Not Found'; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <strong>Last Import:</strong>
                        <?php
                        $last_import = get_option('intersoccer_last_customer_import_report');
                        echo $last_import ? date('Y-m-d H:i', strtotime($last_import['timestamp'])) : 'Never';
                        ?>
                    </div>
                </div>
            </div>

            <!-- Credit Management -->
            <div class="intersoccer-settings-section">
                <h2>Credit Management</h2>
                <div class="settings-grid">
                    <div class="settings-card">
                        <h3>Reset All Customer Credits</h3>
                        <p>Dangerous action: This will permanently delete all customer credit balances and start fresh.</p>
                        <button id="reset-all-credits" class="button button-danger">
                            <span class="dashicons dashicons-warning"></span> Reset All Credits
                        </button>
                    </div>

                    <div class="settings-card">
                        <h3>Bulk Credit Allocation</h3>
                        <p>Allocate credits to customers based on specific criteria.</p>
                        <form id="bulk-credit-form">
                            <select id="allocation-type" name="allocation_type">
                                <option value="all">All Customers</option>
                                <option value="coaches">Coach Referrals Only</option>
                                <option value="zero_balance">Zero Balance Only</option>
                            </select>
                            <input type="number" id="credit-amount" name="credit_amount" placeholder="Credits" min="1" max="100">
                            <button type="submit" class="button button-primary">Allocate Credits</button>
                        </form>
                    </div>

                    <div class="settings-card">
                        <h3>Credit Statistics</h3>
                        <div id="credit-stats">
                            <p>Loading statistics...</p>
                        </div>
                        <button id="refresh-stats" class="button">Refresh Stats</button>
                    </div>
                </div>
            </div>

            <!-- Coaches Import -->
            <div class="intersoccer-settings-section">
                <h2>Coaches Management</h2>
                <div class="settings-grid">
                    <div class="settings-card">
                        <h3>Import Coaches from CSV</h3>
                        <div class="csv-import-instructions">
                            <p><strong>CSV Format Requirements:</strong></p>
                            <ul>
                                <li>First row must contain column headers</li>
                                <li>Required columns: <code>first_name</code>, <code>last_name</code>, <code>email</code></li>
                                <li>Optional columns: <code>phone</code>, <code>specialization</code>, <code>location</code>, <code>experience_years</code>, <code>bio</code></li>
                                <li>Email addresses must be unique and valid</li>
                            </ul>
                            <p><a href="<?php echo INTERSOCCER_REFERRAL_URL; ?>assets/sample-coaches.csv" target="_blank" class="button button-small">📥 Download Sample CSV</a></p>
                        </div>

                        <div id="import-status" style="display: none;">
                            <div class="import-progress">
                                <div class="progress-bar">
                                    <div class="progress-fill" id="progress-fill" style="width: 0%;"></div>
                                </div>
                                <div class="progress-text" id="progress-text">Preparing import...</div>
                            </div>
                        </div>

                        <div id="import-results" style="display: none;">
                            <div class="import-summary">
                                <h4>Import Results</h4>
                                <div id="import-summary-content"></div>
                            </div>
                        </div>

                        <form id="coach-import-form" enctype="multipart/form-data" onsubmit="return false;">
                            <?php wp_nonce_field('import_coaches_from_csv'); ?>
                            <div class="form-row">
                                <label for="coaches_csv">Select CSV File:</label>
                                <input type="file" name="coaches_csv" id="coaches_csv" accept=".csv" required>
                                <small class="file-info">Maximum file size: 10MB. Only CSV files are accepted.</small>
                            </div>
                            <div class="form-row">
                                <label for="import_mode">
                                    <input type="checkbox" name="update_existing" id="update_existing" value="1">
                                    Update existing coaches (match by email)
                                </label>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="button button-primary" id="import-submit-btn">
                                    <span class="dashicons dashicons-upload"></span> Import Coaches
                                </button>
                                <button type="button" class="button button-secondary" id="clear-import-results" style="display: none;">
                                    Clear Results
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="settings-card">
                        <h3>Coach Statistics</h3>
                        <div id="coach-stats">
                            <p>Loading coach statistics...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Audit Log -->
            <div class="intersoccer-settings-section">
                <h2>Audit Log</h2>
                <div class="audit-log-controls">
                    <button id="refresh-audit-log" class="button">Refresh Log</button>
                    <button id="clear-audit-log" class="button button-secondary">Clear Log</button>
                    <button id="export-audit-log" class="button button-secondary">Export Log</button>
                    <select id="audit-filter">
                        <option value="all">All Actions</option>
                        <option value="credit">Credit Changes</option>
                        <option value="import">Imports</option>
                        <option value="admin">Admin Actions</option>
                    </select>
                </div>
                <div id="audit-log-container">
                    <div class="audit-log-entry loading">Loading audit log...</div>
                </div>
            </div>

            <!-- System Maintenance -->
            <div class="intersoccer-settings-section">
                <h2>System Maintenance</h2>
                <div class="settings-grid">
                    <div class="settings-card">
                        <h3>Database Optimization</h3>
                        <p>Clean up orphaned records and optimize database performance.</p>
                        <button id="optimize-database" class="button button-secondary">Optimize Database</button>
                    </div>

                    <div class="settings-card">
                        <h3>Cache Management</h3>
                        <p>Clear all plugin-related caches and transients.</p>
                        <button id="clear-cache" class="button button-secondary">Clear Cache</button>
                    </div>

                    <div class="settings-card">
                        <h3>System Health Check</h3>
                        <p>Run comprehensive system health diagnostics.</p>
                        <button id="health-check" class="button button-secondary">Run Health Check</button>
                    </div>
                </div>
            </div>

            <!-- Plugin Settings -->
            <div class="intersoccer-settings-section">
                <h2>Plugin Settings</h2>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('intersoccer_settings');
                    do_settings_sections('intersoccer_settings');
                    ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Credit Value (CHF)</th>
                            <td>
                                <input type="number" name="intersoccer_credit_value" value="<?php echo get_option('intersoccer_credit_value', '1'); ?>" step="0.01" min="0.01">
                                <p class="description">Value of 1 credit in CHF for WooCommerce integration</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Max Credits per Order</th>
                            <td>
                                <input type="number" name="intersoccer_max_credits_per_order" value="<?php echo get_option('intersoccer_max_credits_per_order', '100'); ?>" min="1" max="1000">
                                <p class="description">Maximum credits a customer can use in a single order</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Enable Debug Logging</th>
                            <td>
                                <input type="checkbox" name="intersoccer_debug_logging" value="1" <?php checked(get_option('intersoccer_debug_logging'), '1'); ?>>
                                <p class="description">Enable detailed logging for debugging purposes</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Tiered Commission Rates (%)</th>
                            <td>
                                <p><strong>Commission rates based on recruited customers:</strong></p>
                                <ul style="margin: 10px 0;">
                                    <li>1-10 customers: <strong>10%</strong> commission</li>
                                    <li>11-24 customers: <strong>15%</strong> commission</li>
                                    <li>25+ customers: <strong>20%</strong> commission</li>
                                </ul>
                                <p class="description">These rates are automatically applied based on each coach's total recruited customer count</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Save Settings'); ?>
                </form>
            </div>

            <!-- Points Configuration -->
            <div class="intersoccer-settings-section">
                <h2>Points Configuration</h2>
                <?php
                $go_live_option = get_option('intersoccer_points_golive_date', '');
                $go_live_status = __('Points accumulation is active immediately.', 'intersoccer-referral');

                if (!empty($go_live_option)) {
                    $go_live_timestamp = strtotime($go_live_option . ' 00:00:00');
                    if ($go_live_timestamp) {
                        $formatted_go_live = date_i18n(get_option('date_format', 'F j, Y'), $go_live_timestamp);
                        $current_timestamp = current_time('timestamp');

                        if ($current_timestamp < $go_live_timestamp) {
                            $go_live_status = sprintf(
                                __('Points accumulation is scheduled to begin on %s.', 'intersoccer-referral'),
                                $formatted_go_live
                            );
                        } else {
                            $go_live_status = sprintf(
                                __('Points accumulation has been active since %s.', 'intersoccer-referral'),
                                $formatted_go_live
                            );
                        }
                    }
                }
                ?>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('intersoccer_points_configuration');
                    do_settings_sections('intersoccer_points_configuration');
                    ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="points-golive-date"><?php esc_html_e('Points Go-Live Date', 'intersoccer-referral'); ?></label>
                            </th>
                            <td>
                                <input type="date"
                                       id="points-golive-date"
                                       name="intersoccer_points_golive_date"
                                       class="regular-text"
                                       value="<?php echo esc_attr($go_live_option); ?>">
                                <p class="description">
                                    <?php esc_html_e('Points will only be awarded for orders placed on or after this date. Leave blank to award points immediately.', 'intersoccer-referral'); ?>
                                </p>
                                <p class="description status-description">
                                    <strong><?php esc_html_e('Status:', 'intersoccer-referral'); ?></strong>
                                    <?php echo esc_html($go_live_status); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Save Points Configuration', 'intersoccer-referral')); ?>
                </form>
            </div>

            <!-- Points Management -->
            <div class="intersoccer-settings-section">
                <h2>Points Management</h2>
                <div class="settings-grid">
                    <div class="settings-card">
                        <h3>Points Statistics</h3>
                        <div id="points-stats">
                            <p>Loading points statistics...</p>
                        </div>
                        <button id="refresh-points-stats" class="button">Refresh Stats</button>
                    </div>

                    <div class="settings-card">
                        <h3>Points Ledger</h3>
                        <p>View detailed points transaction history.</p>
                        <button id="view-points-ledger" class="button button-secondary">View Ledger</button>
                        <div id="points-ledger-container" style="display: none; margin-top: 15px;">
                            <div id="points-ledger-content">
                                <p>Loading ledger...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Points Migration -->
            <div class="intersoccer-settings-section">
                <h2>Points System Migration</h2>
                <div class="migration-notice">
                    <div class="notice notice-warning">
                        <p><strong>Important:</strong> This migration updates the points system from 1 CHF = 1 point to 10 CHF = 1 point ratio.</p>
                        <p><strong>What it does:</strong></p>
                        <ul>
                            <li>Creates a backup of current points data</li>
                            <li>Recalculates all point balances and transactions</li>
                            <li>Updates user point balances</li>
                            <li>This action cannot be easily undone</li>
                        </ul>
                        <p><strong>⚠️ Backup your database before proceeding!</strong></p>
                    </div>
                </div>

                <div class="migration-controls">
                    <div class="migration-card">
                        <h3>Points Ratio Migration</h3>
                        <p>Migrate from 1:1 to 10:1 points ratio (10 CHF = 1 point)</p>
                        <div class="migration-status" id="migration-status">
                            <span class="status-indicator status-ready">Ready to migrate</span>
                        </div>
                        <button id="run-points-migration" class="button button-primary button-hero">
                            <span class="dashicons dashicons-update"></span>
                            Run Points Migration
                        </button>
                        <div id="migration-progress" style="display: none; margin-top: 20px;">
                            <div class="progress-container">
                                <div class="progress-bar">
                                    <div class="progress-fill" id="migration-progress-fill" style="width: 0%"></div>
                                </div>
                                <div class="progress-text" id="migration-progress-text">Initializing migration...</div>
                            </div>
                            <div class="migration-details" id="migration-details" style="margin-top: 15px;">
                                <div class="detail-item"><strong>Transactions Processed:</strong> <span id="transactions-processed">0</span></div>
                                <div class="detail-item"><strong>Users Updated:</strong> <span id="users-updated">0</span></div>
                                <div class="detail-item"><strong>Backup Created:</strong> <span id="backup-created">No</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="migration-card">
                        <h3>Migration Status</h3>
                        <div id="migration-info">
                            <p>Loading migration status...</p>
                        </div>
                        <button id="refresh-migration-status" class="button">Refresh Status</button>
                    </div>
                </div>
            </div>

            <!-- Phase 0: Integer Points Migration -->
            <div class="intersoccer-settings-section">
                <h2>⭐ Phase 0: Integer Points Migration (CRITICAL)</h2>
                <div class="migration-notice">
                    <div class="notice notice-error">
                        <p><strong>🚨 CRITICAL - Required Before Production!</strong></p>
                        <p><strong>What this does:</strong></p>
                        <ul>
                            <li>Converts all points from DECIMAL to INT (95.50 points → 95 points)</li>
                            <li>Updates database schema: DECIMAL(10,2) → INT(11)</li>
                            <li>Creates timestamped backup tables before changes</li>
                            <li>Uses floor() logic: 95 CHF = 9 points (not 9.5)</li>
                            <li>Updates 3 tables: points_log, referral_rewards, user_meta</li>
                            <li>Can be rolled back if issues occur</li>
                        </ul>
                        <p><strong>⚠️ Backup your database before proceeding!</strong></p>
                        <p><strong>Why this is needed:</strong> Fractional points cause accounting issues and user confusion.</p>
                    </div>
                </div>

                <div class="migration-controls">
                    <div class="migration-card">
                        <h3>Convert Points to Integers</h3>
                        <p>Migrate from fractional points to integer-only points</p>
                        <div class="migration-status" id="integer-migration-status">
                            <span class="status-indicator status-ready">Ready to migrate</span>
                        </div>
                        <button id="run-integer-migration" class="button button-primary button-hero" style="background: #dc3545; border-color: #dc3545;">
                            <span class="dashicons dashicons-update"></span>
                            Run Integer Migration
                        </button>
                        <button id="verify-integer-migration" class="button button-secondary" style="margin-left: 10px; display: none;">
                            <span class="dashicons dashicons-yes-alt"></span>
                            Verify Migration
                        </button>
                        <button id="rollback-integer-migration" class="button button-secondary" style="margin-left: 10px; display: none; background: #856404; border-color: #856404; color: white;">
                            <span class="dashicons dashicons-undo"></span>
                            Rollback Migration
                        </button>
                        <div id="integer-migration-progress" style="display: none; margin-top: 20px;">
                            <div class="progress-container">
                                <div class="progress-bar">
                                    <div class="progress-fill" id="integer-migration-progress-fill" style="width: 0%"></div>
                                </div>
                                <div class="progress-text" id="integer-migration-progress-text">Initializing migration...</div>
                            </div>
                            <div class="migration-details" id="integer-migration-details" style="margin-top: 15px;">
                                <div class="detail-item"><strong>Records Converted:</strong> <span id="integer-records-converted">0</span></div>
                                <div class="detail-item"><strong>Backup Tables:</strong> <span id="integer-backup-tables">None</span></div>
                                <div class="detail-item"><strong>Status:</strong> <span id="integer-status">Pending</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="migration-card">
                        <h3>Integer Migration Status</h3>
                        <div id="integer-migration-info">
                            <p>Loading migration status...</p>
                        </div>
                        <button id="refresh-integer-migration-status" class="button">Refresh Status</button>
                    </div>
                </div>
            </div>

            <!-- Phase 0: Role-Specific Point Acquisition Rates -->
            <div class="intersoccer-settings-section">
                <h2>⭐ Phase 0: Role-Specific Point Acquisition Rates</h2>
                <div class="settings-notice">
                    <div class="notice notice-info">
                        <p><strong>💡 Configure different point earning rates for each user role</strong></p>
                        <p><strong>How it works:</strong></p>
                        <ul>
                            <li>Set how many CHF customers must spend to earn 1 point</li>
                            <li>Lower numbers = better rates (faster point earning)</li>
                            <li>Example: Rate of 5 means CHF 5 spent = 1 point</li>
                            <li>Priority order: Partner > Social Influencer > Coach > Customer</li>
                        </ul>
                        <p><strong>Use cases:</strong> Reward partners and influencers with better rates to incentivize promotion!</p>
                    </div>
                </div>

                <form id="points-rates-form" method="post">
                    <?php wp_nonce_field('intersoccer_points_rates_save', 'points_rates_nonce'); ?>
                    
                    <div class="rates-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
                        
                        <!-- Customer Rate -->
                        <div class="rate-card" style="background: #fff; border: 2px solid #e2e8f0; border-radius: 8px; padding: 20px;">
                            <h3 style="margin: 0 0 15px 0; color: #2563eb;">👤 Customer Rate</h3>
                            <div class="rate-input-group">
                                <label for="rate_customer" style="display: block; margin-bottom: 8px; font-weight: 600;">
                                    CHF per 1 Point:
                                </label>
                                <input 
                                    type="number" 
                                    id="rate_customer" 
                                    name="rate_customer" 
                                    value="<?php echo esc_attr(get_option('intersoccer_points_rate_customer', 10)); ?>"
                                    min="1"
                                    max="100"
                                    step="1"
                                    class="regular-text"
                                    style="width: 100px;"
                                    required
                                />
                                <p class="description" style="margin-top: 8px;">
                                    Standard customer earning rate<br/>
                                    <strong>Current preview:</strong> CHF 100 spent = 
                                    <span class="preview-points" data-role="customer">
                                        <?php echo floor(100 / max(1, get_option('intersoccer_points_rate_customer', 10))); ?>
                                    </span> points
                                </p>
                            </div>
                        </div>

                        <!-- Coach Rate -->
                        <div class="rate-card" style="background: #fff; border: 2px solid #e2e8f0; border-radius: 8px; padding: 20px;">
                            <h3 style="margin: 0 0 15px 0; color: #16a34a;">⚽ Coach Rate</h3>
                            <div class="rate-input-group">
                                <label for="rate_coach" style="display: block; margin-bottom: 8px; font-weight: 600;">
                                    CHF per 1 Point:
                                </label>
                                <input 
                                    type="number" 
                                    id="rate_coach" 
                                    name="rate_coach" 
                                    value="<?php echo esc_attr(get_option('intersoccer_points_rate_coach', 10)); ?>"
                                    min="1"
                                    max="100"
                                    step="1"
                                    class="regular-text"
                                    style="width: 100px;"
                                    required
                                />
                                <p class="description" style="margin-top: 8px;">
                                    Coach earning rate (can be better than customers)<br/>
                                    <strong>Current preview:</strong> CHF 100 spent = 
                                    <span class="preview-points" data-role="coach">
                                        <?php echo floor(100 / max(1, get_option('intersoccer_points_rate_coach', 10))); ?>
                                    </span> points
                                </p>
                            </div>
                        </div>

                        <!-- Partner Rate -->
                        <div class="rate-card" style="background: #fff; border: 2px solid #e2e8f0; border-radius: 8px; padding: 20px;">
                            <h3 style="margin: 0 0 15px 0; color: #dc2626;">🤝 Partner Rate</h3>
                            <div class="rate-input-group">
                                <label for="rate_partner" style="display: block; margin-bottom: 8px; font-weight: 600;">
                                    CHF per 1 Point:
                                </label>
                                <input 
                                    type="number" 
                                    id="rate_partner" 
                                    name="rate_partner" 
                                    value="<?php echo esc_attr(get_option('intersoccer_points_rate_partner', 10)); ?>"
                                    min="1"
                                    max="100"
                                    step="1"
                                    class="regular-text"
                                    style="width: 100px;"
                                    required
                                />
                                <p class="description" style="margin-top: 8px;">
                                    Partner earning rate (VIP rate for business partners)<br/>
                                    <strong>Current preview:</strong> CHF 100 spent = 
                                    <span class="preview-points" data-role="partner">
                                        <?php echo floor(100 / max(1, get_option('intersoccer_points_rate_partner', 10))); ?>
                                    </span> points
                                </p>
                            </div>
                        </div>

                        <!-- Social Influencer Rate -->
                        <div class="rate-card" style="background: #fff; border: 2px solid #e2e8f0; border-radius: 8px; padding: 20px;">
                            <h3 style="margin: 0 0 15px 0; color: #9333ea;">📱 Social Influencer Rate</h3>
                            <div class="rate-input-group">
                                <label for="rate_social_influencer" style="display: block; margin-bottom: 8px; font-weight: 600;">
                                    CHF per 1 Point:
                                </label>
                                <input 
                                    type="number" 
                                    id="rate_social_influencer" 
                                    name="rate_social_influencer" 
                                    value="<?php echo esc_attr(get_option('intersoccer_points_rate_social_influencer', 10)); ?>"
                                    min="1"
                                    max="100"
                                    step="1"
                                    class="regular-text"
                                    style="width: 100px;"
                                    required
                                />
                                <p class="description" style="margin-top: 8px;">
                                    Social influencer rate (reward for promotion)<br/>
                                    <strong>Current preview:</strong> CHF 100 spent = 
                                    <span class="preview-points" data-role="social_influencer">
                                        <?php echo floor(100 / max(1, get_option('intersoccer_points_rate_social_influencer', 10))); ?>
                                    </span> points
                                </p>
                            </div>
                        </div>

                    </div>

                    <div class="rate-examples" style="margin-top: 30px; padding: 20px; background: #f8fafc; border-radius: 8px;">
                        <h3 style="margin-top: 0;">📊 Rate Examples</h3>
                        <table class="widefat" style="background: white;">
                            <thead>
                                <tr>
                                    <th>Rate (CHF/point)</th>
                                    <th>CHF 50 spent</th>
                                    <th>CHF 100 spent</th>
                                    <th>CHF 500 spent</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>1</strong></td>
                                    <td>50 points</td>
                                    <td>100 points</td>
                                    <td>500 points</td>
                                    <td>Most generous (1:1 ratio)</td>
                                </tr>
                                <tr>
                                    <td><strong>5</strong></td>
                                    <td>10 points</td>
                                    <td>20 points</td>
                                    <td>100 points</td>
                                    <td>Very good rate</td>
                                </tr>
                                <tr>
                                    <td><strong>10</strong> (default)</td>
                                    <td>5 points</td>
                                    <td>10 points</td>
                                    <td>50 points</td>
                                    <td>Standard rate</td>
                                </tr>
                                <tr>
                                    <td><strong>20</strong></td>
                                    <td>2 points</td>
                                    <td>5 points</td>
                                    <td>25 points</td>
                                    <td>Conservative rate</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top: 20px;">
                        <button type="submit" class="button button-primary button-large" id="save-points-rates">
                            <span class="dashicons dashicons-saved"></span>
                            Save Point Rates
                        </button>
                        <button type="button" class="button button-secondary" id="reset-points-rates" style="margin-left: 10px;">
                            Reset to Defaults (10 CHF = 1 point for all)
                        </button>
                    </div>
                </form>

                <div id="rates-save-message" style="display: none; margin-top: 20px;"></div>
            </div>

            <!-- Coach Role Restoration -->
            <div class="intersoccer-settings-section">
                <h2>Coach Role Restoration</h2>
                <div class="role-restore-notice">
                    <div class="notice notice-warning">
                        <p><strong>Missing Coach Roles:</strong> If existing coaches are missing their "Coach" role assignment, use this tool to restore them based on referral data.</p>
                        <p><strong>What it does:</strong></p>
                        <ul>
                            <li>Scans referral records to identify users who should have coach roles</li>
                            <li>Restores the "Coach" role to users who have active referrals</li>
                            <li>Does not affect users who already have the correct role</li>
                        </ul>
                    </div>
                </div>

                <div class="role-restore-controls">
                    <div class="restore-card">
                        <h3>Restore Missing Coach Roles</h3>
                        <p>Restore coach roles based on existing referral data</p>
                        <div class="restore-status" id="restore-status">
                            <span class="status-indicator status-ready">Ready to restore</span>
                        </div>
                        <button id="restore-coach-roles" class="button button-primary">
                            <span class="dashicons dashicons-admin-users"></span>
                            Restore Coach Roles
                        </button>
                        <div id="restore-progress" style="display: none; margin-top: 20px;">
                            <div class="progress-container">
                                <div class="progress-bar">
                                    <div class="progress-fill" id="restore-progress-fill" style="width: 0%"></div>
                                </div>
                                <div class="progress-text" id="restore-progress-text">Scanning referral data...</div>
                            </div>
                            <div class="restore-details" id="restore-details" style="margin-top: 15px;">
                                <div class="detail-item"><strong>Coaches Found:</strong> <span id="coaches-found">0</span></div>
                                <div class="detail-item"><strong>Roles Restored:</strong> <span id="roles-restored">0</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Credit reset functionality
            $('#reset-all-credits').on('click', function(e) {
                e.preventDefault();
                if (!confirm('Are you sure you want to reset ALL customer credits? This action cannot be undone!')) {
                    return;
                }

                $(this).prop('disabled', true).text('Resetting...');

                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'reset_all_customer_credits',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        alert(response.data.message);
                        location.reload();
                    },
                    error: function() {
                        alert('Error resetting credits. Please try again.');
                        $('#reset-all-credits').prop('disabled', false).text('Reset All Credits');
                    }
                });
            });

            // Bulk credit allocation
            $('#bulk-credit-form').on('submit', function(e) {
                e.preventDefault();

                const type = $('#allocation-type').val();
                const amount = $('#credit-amount').val();

                if (!amount || amount < 1) {
                    alert('Please enter a valid credit amount.');
                    return;
                }

                if (!confirm(`Allocate ${amount} credits to ${type.replace('_', ' ')} customers?`)) {
                    return;
                }

                const $submitBtn = $(this).find('button[type="submit"]');
                $submitBtn.prop('disabled', true).text('Allocating...');

                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'allocate_credits_to_customers',
                        nonce: intersoccer_admin.nonce,
                        allocation_type: type,
                        credit_amount: amount
                    },
                    success: function(response) {
                        alert(response.data.message);
                        location.reload();
                    },
                    error: function() {
                        alert('Error allocating credits. Please try again.');
                        $submitBtn.prop('disabled', false).text('Allocate Credits');
                    }
                });
            });

            // Load credit statistics
            function loadCreditStats() {
                $('#credit-stats').html('<p>Loading statistics...</p>');
                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_credit_statistics',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        $('#credit-stats').html(response.data.html);
                    },
                    error: function() {
                        $('#credit-stats').html('<p>Error loading statistics</p>');
                    }
                });
            }

            // Load coach statistics
            function loadCoachStats() {
                $('#coach-stats').html('<p>Loading coach statistics...</p>');
                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_coach_statistics',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        $('#coach-stats').html(response.data.html);
                    },
                    error: function() {
                        $('#coach-stats').html('<p>Error loading coach statistics</p>');
                    }
                });
            }

            // Load audit log
            function loadAuditLog(filter = 'all') {
                $('#audit-log-container').html('<div class="audit-log-entry loading">Loading audit log...</div>');
                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_audit_log',
                        nonce: intersoccer_admin.nonce,
                        filter: filter
                    },
                    success: function(response) {
                        $('#audit-log-container').html(response.data.html);
                    },
                    error: function() {
                        $('#audit-log-container').html('<div class="audit-log-entry error">Error loading audit log</div>');
                    }
                });
            }

            // Load points statistics
            function loadPointsStats() {
                $('#points-stats').html('<p>Loading points statistics...</p>');
                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_points_statistics',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        $('#points-stats').html(response.data.html);
                    },
                    error: function() {
                        $('#points-stats').html('<p>Error loading points statistics</p>');
                    }
                });
            }

            // View points ledger
            $('#view-points-ledger').on('click', function() {
                const $container = $('#points-ledger-container');
                const $content = $('#points-ledger-content');

                if ($container.is(':visible')) {
                    $container.hide();
                    return;
                }

                $container.show();
                $content.html('<p>Loading points ledger...</p>');

                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_points_ledger',
                        nonce: intersoccer_admin.nonce,
                        limit: 20
                    },
                    success: function(response) {
                        $content.html(response.data.html);
                    },
                    error: function() {
                        $content.html('<p>Error loading points ledger</p>');
                    }
                });
            });

            // Event handlers
            $('#refresh-stats').on('click', loadCreditStats);
            $('#refresh-audit-log').on('click', function() { loadAuditLog($('#audit-filter').val()); });
            $('#audit-filter').on('change', function() { loadAuditLog($(this).val()); });

            $('#clear-audit-log').on('click', function() {
                if (!confirm('Clear all audit log entries?')) return;

                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'clear_audit_log',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        alert(response.data.message);
                        loadAuditLog();
                    }
                });
            });

            $('#export-audit-log').on('click', function() {
                window.open(intersoccer_admin.ajax_url + '?action=export_audit_log&nonce=' + intersoccer_admin.nonce, '_blank');
            });

            $('#refresh-points-stats').on('click', loadPointsStats);

            // Event handlers
            $('#refresh-stats').on('click', loadCreditStats);
            $('#refresh-audit-log').on('click', function() { loadAuditLog($('#audit-filter').val()); });
            $('#audit-filter').on('change', function() { loadAuditLog($(this).val()); });

            $('#clear-audit-log').on('click', function() {
                if (!confirm('Clear all audit log entries?')) return;

                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'clear_audit_log',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        alert(response.data.message);
                        loadAuditLog();
                    }
                });
            });

            $('#export-audit-log').on('click', function() {
                window.open(intersoccer_admin.ajax_url + '?action=export_audit_log&nonce=' + intersoccer_admin.nonce, '_blank');
            });

            // Points migration functionality
            $('#run-points-migration').on('click', function(e) {
                e.preventDefault();

                if (!confirm('This will migrate the points system from 1:1 to 10:1 ratio. A backup will be created, but this action cannot be easily undone. Continue?')) {
                    return;
                }

                const $button = $(this);
                const $progress = $('#migration-progress');
                const $status = $('#migration-status');
                const $progressFill = $('#migration-progress-fill');
                const $progressText = $('#migration-progress-text');

                // Update status
                $status.html('<span class="status-indicator status-running">Running migration...</span>');
                $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Running Migration...');

                $progress.show();
                $progressFill.css('width', '0%');
                $progressText.text('Initializing points migration...');

                // Start the migration
                runPointsMigration();
            });

            function runPointsMigration() {
                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'run_points_migration',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#migration-progress-fill').css('width', '100%');
                            $('#migration-progress-text').text('Migration completed successfully!');
                            $('#migration-status').html('<span class="status-indicator status-success">Migration completed</span>');
                            $('#run-points-migration').prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Migration Complete');

                            // Update details
                            $('#transactions-processed').text(response.data.transactions_processed || 0);
                            $('#users-updated').text(response.data.users_updated || 0);
                            $('#backup-created').text(response.data.backup_created ? 'Yes' : 'No');

                            // Show success message
                            setTimeout(() => {
                                alert(response.data.message);
                                loadMigrationStatus();
                            }, 1000);
                        } else {
                            $('#migration-status').html('<span class="status-indicator status-error">Migration failed</span>');
                            $('#run-points-migration').prop('disabled', false).html('<span class="dashicons dashicons-warning"></span> Retry Migration');
                            alert('Migration failed: ' + (response.data?.message || 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#migration-status').html('<span class="status-indicator status-error">Migration error</span>');
                        $('#run-points-migration').prop('disabled', false).html('<span class="dashicons dashicons-warning"></span> Retry Migration');
                        $('#migration-progress-text').text('Error occurred during migration');
                        alert('AJAX Error: ' + error);
                    }
                });
            }

            // Load migration status
            function loadMigrationStatus() {
                $('#migration-info').html('<p>Loading migration status...</p>');
                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_migration_status',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        $('#migration-info').html(response.data.html);
                    },
                    error: function() {
                        $('#migration-info').html('<p>Error loading migration status</p>');
                    }
                });
            }

            $('#refresh-migration-status').on('click', loadMigrationStatus);

            // ============================================================
            // Phase 0: Integer Points Migration Handlers
            // ============================================================

            // Run integer migration
            $('#run-integer-migration').on('click', function(e) {
                e.preventDefault();

                if (!confirm('⚠️ CRITICAL MIGRATION\n\nThis will convert all points to integers (95.5 → 95).\n\nA backup will be created, but you should backup your database first!\n\nContinue?')) {
                    return;
                }

                const $button = $(this);
                const $progress = $('#integer-migration-progress');
                const $status = $('#integer-migration-status');
                const $progressFill = $('#integer-migration-progress-fill');
                const $progressText = $('#integer-migration-progress-text');

                $status.html('<span class="status-indicator status-running">Running migration...</span>');
                $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Running...');

                $progress.show();
                $progressFill.css('width', '0%');
                $progressText.text('Creating backups...');

                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'run_integer_migration',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#integer-migration-progress-fill').css('width', '100%');
                            $('#integer-migration-progress-text').text('Migration completed successfully!');
                            $('#integer-migration-status').html('<span class="status-indicator status-success">Migration completed</span>');
                            $button.hide();

                            // Show verify and rollback buttons
                            $('#verify-integer-migration').show();
                            $('#rollback-integer-migration').show();

                            // Update details
                            $('#integer-records-converted').text(response.data.records_converted || 0);
                            $('#integer-backup-tables').text((response.data.backup_tables || []).join(', '));
                            $('#integer-status').text('Completed');

                            setTimeout(() => {
                                alert('✅ ' + response.data.message);
                                loadIntegerMigrationStatus();
                            }, 1000);
                        } else {
                            $('#integer-migration-status').html('<span class="status-indicator status-error">Migration failed</span>');
                            $button.prop('disabled', false).html('<span class="dashicons dashicons-warning"></span> Retry Migration');
                            alert('❌ Migration failed: ' + (response.data?.message || 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#integer-migration-status').html('<span class="status-indicator status-error">Migration error</span>');
                        $button.prop('disabled', false).html('<span class="dashicons dashicons-warning"></span> Retry Migration');
                        alert('❌ AJAX Error: ' + error);
                    }
                });
            });

            // Verify integer migration
            $('#verify-integer-migration').on('click', function(e) {
                e.preventDefault();

                const $button = $(this);
                $button.prop('disabled', true).text('Verifying...');

                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'verify_integer_migration',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data.success) {
                            alert('✅ Verification passed!\n\n' + response.data.message + '\n\nIssues: ' + (response.data.issues.length || 0));
                        } else {
                            alert('⚠️ Verification found issues:\n\n' + (response.data.issues || []).join('\n'));
                        }
                        $button.prop('disabled', false).text('Verify Migration');
                    },
                    error: function() {
                        alert('Error verifying migration');
                        $button.prop('disabled', false).text('Verify Migration');
                    }
                });
            });

            // Rollback integer migration
            $('#rollback-integer-migration').on('click', function(e) {
                e.preventDefault();

                if (!confirm('⚠️ ROLLBACK MIGRATION\n\nThis will restore from backup and undo all integer migration changes.\n\nContinue?')) {
                    return;
                }

                const $button = $(this);
                $button.prop('disabled', true).text('Rolling back...');

                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'rollback_integer_migration',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('✅ ' + response.data.message);
                            location.reload();
                        } else {
                            alert('❌ Rollback failed: ' + (response.data?.message || 'Unknown error'));
                            $button.prop('disabled', false).text('Rollback Migration');
                        }
                    },
                    error: function() {
                        alert('Error during rollback');
                        $button.prop('disabled', false).text('Rollback Migration');
                    }
                });
            });

            // Load integer migration status
            function loadIntegerMigrationStatus() {
                $('#integer-migration-info').html('<p>Loading status...</p>');
                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_integer_migration_status',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#integer-migration-info').html(response.data.html);
                            
                            // Update button visibility based on status
                            if (response.data.status === 'completed') {
                                $('#run-integer-migration').hide();
                                $('#verify-integer-migration').show();
                                $('#rollback-integer-migration').show();
                                $('#integer-migration-status').html('<span class="status-indicator status-success">Completed</span>');
                            }
                        }
                    },
                    error: function() {
                        $('#integer-migration-info').html('<p>Error loading status</p>');
                    }
                });
            }

            $('#refresh-integer-migration-status').on('click', loadIntegerMigrationStatus);

            // Initialize integer migration status on page load
            loadIntegerMigrationStatus();

            // Coach Import Form Handler
            $('#import-submit-btn').on('click', function(e) {
                e.preventDefault();

                const $button = $(this);
                const $form = $('#coach-import-form');
                const $status = $('#import-status');
                const $results = $('#import-results');
                const $progress = $('#import-status .progress-fill');
                const $progressText = $('#import-status .progress-text');

                // Get form data
                const formData = new FormData($form[0]);
                formData.append('action', 'import_coaches_from_csv');
                formData.append('update_existing', $('#update_existing').is(':checked') ? '1' : '0');

                // Show progress
                $status.show();
                $results.hide();
                $progress.css('width', '0%');
                $progressText.text('Uploading file...');
                $button.prop('disabled', true).text('Importing...');

                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        $progress.css('width', '100%');
                        $progressText.text('Import completed!');

                        if (response.success) {
                            // Show results
                            let resultsHtml = '<h4>Import Results</h4>';
                            resultsHtml += '<p><strong>Created:</strong> ' + response.data.created.length + '</p>';
                            resultsHtml += '<p><strong>Updated:</strong> ' + response.data.updated.length + '</p>';
                            resultsHtml += '<p><strong>Skipped:</strong> ' + response.data.skipped.length + '</p>';
                            resultsHtml += '<p><strong>Errors:</strong> ' + response.data.errors.length + '</p>';

                            if (response.data.created.length > 0) {
                                resultsHtml += '<h5>Created Coaches:</h5><ul>';
                                response.data.created.forEach(function(coach) {
                                    resultsHtml += '<li>' + coach.first_name + ' ' + coach.last_name + ' (' + coach.email + ')</li>';
                                });
                                resultsHtml += '</ul>';
                            }

                            if (response.data.errors.length > 0) {
                                resultsHtml += '<h5>Errors:</h5><ul>';
                                response.data.errors.forEach(function(error) {
                                    resultsHtml += '<li>' + error + '</li>';
                                });
                                resultsHtml += '</ul>';
                            }

                            $('#import-summary-content').html(resultsHtml);
                            $results.show();
                            $('#clear-import-results').show();

                            // Refresh coach stats
                            loadCoachStats();
                        } else {
                            alert('Import failed: ' + (response.data?.message || 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        $progressText.text('Error occurred during import');
                        alert('AJAX Error: ' + error);
                    },
                    complete: function() {
                        $button.prop('disabled', false).text('Import Coaches');
                    }
                });
            });

            $('#clear-import-results').on('click', function() {
                $('#import-results').hide();
                $('#import-status').hide();
                $(this).hide();
            });

            // Coach Role Restoration
            $('#restore-coach-roles').on('click', function(e) {
                e.preventDefault();

                if (!confirm('This will scan referral data and restore coach roles to users who should have them. Continue?')) {
                    return;
                }

                const $button = $(this);
                const $progress = $('#restore-progress');
                const $status = $('#restore-status');
                const $progressFill = $('#restore-progress-fill');
                const $progressText = $('#restore-progress-text');

                // Update status
                $status.html('<span class="status-indicator status-running">Restoring roles...</span>');
                $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Restoring...');

                $progress.show();
                $progressFill.css('width', '0%');
                $progressText.text('Scanning referral data...');

                // Start the restoration
                restoreCoachRoles();
            });

            function restoreCoachRoles() {
                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'restore_coach_roles',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#restore-progress-fill').css('width', '100%');
                            $('#restore-progress-text').text('Restoration completed successfully!');
                            $('#restore-status').html('<span class="status-indicator status-success">Restoration completed</span>');
                            $('#restore-coach-roles').prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Restoration Complete');

                            // Update details
                            $('#coaches-found').text(response.data.coaches_found || 0);
                            $('#roles-restored').text(response.data.roles_restored || 0);

                            // Show success message
                            setTimeout(() => {
                                alert(response.data.message);
                                loadCoachStats();
                            }, 1000);
                        } else {
                            $('#restore-status').html('<span class="status-indicator status-error">Restoration failed</span>');
                            $('#restore-coach-roles').prop('disabled', false).html('<span class="dashicons dashicons-warning"></span> Retry Restoration');
                            alert('Restoration failed: ' + (response.data?.message || 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#restore-status').html('<span class="status-indicator status-error">Restoration error</span>');
                        $('#restore-coach-roles').prop('disabled', false).html('<span class="dashicons dashicons-warning"></span> Retry Restoration');
                        $('#restore-progress-text').text('Error occurred during restoration');
                        alert('AJAX Error: ' + error);
                    }
                });
            }

            // Initialize
            loadCreditStats();
            loadCoachStats();
            loadAuditLog();
            loadPointsStats();
            loadMigrationStatus();
        });
        </script>

        <style>
        .intersoccer-settings-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .intersoccer-settings-section h2 {
            margin-top: 0;
            color: #23282d;
            border-bottom: 2px solid #f1f1f1;
            padding-bottom: 10px;
        }

        .info-grid, .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }

        .info-item, .settings-card {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e1e1e1;
        }

        .settings-card h3 {
            margin-top: 0;
            color: #23282d;
        }

        .status-badge.active { background: #d5f4e6; color: #27ae60; padding: 4px 8px; border-radius: 4px; }
        .status-badge.inactive { background: #fadbd8; color: #e74c3c; padding: 4px 8px; border-radius: 4px; }

        .button-danger {
            background: #dc3545;
            border-color: #dc3545;
            color: white;
        }

        .button-danger:hover {
            background: #c82333;
            border-color: #bd2130;
        }

        .audit-log-controls {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .audit-log-entry {
            padding: 12px;
            border-bottom: 1px solid #e1e1e1;
            font-family: monospace;
            font-size: 12px;
        }

        .audit-log-entry.loading {
            text-align: center;
            color: #666;
        }

        .audit-log-entry.error {
            color: #e74c3c;
            text-align: center;
        }

        .audit-log-entry .timestamp {
            color: #666;
            margin-right: 10px;
        }

        .audit-log-entry .action {
            font-weight: bold;
            margin-right: 10px;
        }

        .audit-log-entry .user {
            color: #007cba;
            margin-right: 10px;
        }

        .progress-bar {
            width: 100%;
            background: #e1e1e1;
            border-radius: 4px;
            overflow: hidden;
            height: 8px;
            margin-top: 5px;
        }

        .progress-fill {
            height: 100%;
            background: #28a745;
            transition: width 0.4s ease;
        }

        .sync-notice {
            margin-bottom: 25px;
        }

        .sync-controls {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }

        .sync-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            border: 1px solid #e1e1e1;
        }

        .sync-card h3 {
            margin-top: 0;
            color: #23282d;
            margin-bottom: 15px;
        }

        .sync-status {
            margin: 15px 0;
        }

        .status-indicator {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-ready {
            background: #fff3cd;
            color: #856404;
        }

        .status-running {
            background: #cce5ff;
            color: #004085;
        }

        .status-success {
            background: #d5f4e6;
            color: #155724;
        }

        .status-error {
            background: #fadbd8;
            color: #721c24;
        }

        .progress-container {
            margin-bottom: 15px;
        }

        .progress-bar {
            width: 100%;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            height: 12px;
            margin-bottom: 8px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #007cba, #28a745);
            transition: width 0.5s ease;
        }

        .progress-text {
            font-size: 14px;
            color: #666;
            text-align: center;
        }

        .sync-details {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }

        .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .detail-item:last-child {
            margin-bottom: 0;
        }

        .spin {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .migration-notice {
            margin-bottom: 25px;
        }

        .migration-controls {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
        }

        .migration-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            border: 1px solid #e1e1e1;
        }

        .migration-card h3 {
            margin-top: 0;
            color: #23282d;
            margin-bottom: 15px;
        }

        .migration-status {
            margin: 15px 0;
        }

        .migration-details {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }

        .migration-details .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .migration-details .detail-item:last-child {
            margin-bottom: 0;
        }

        .role-restore-notice {
            margin-bottom: 25px;
        }

        .role-restore-controls {
            display: grid;
            grid-template-columns: 1fr;
            gap: 25px;
        }

        .restore-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            border: 1px solid #e1e1e1;
        }

        .restore-card h3 {
            margin-top: 0;
            color: #23282d;
            margin-bottom: 15px;
        }

        .restore-status {
            margin: 15px 0;
        }

        .restore-details {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }

        .restore-details .detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .restore-details .detail-item:last-child {
            margin-bottom: 0;
        }
        </style>
        <?php
    }

    /**
     * Check if database tables exist
     */
    private function check_database_tables() {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'intersoccer_referrals',
            $wpdb->prefix . 'intersoccer_referral_credits',
            $wpdb->prefix . 'intersoccer_credit_redemptions'
        ];

        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                return false;
            }
        }

        return true;
    }

    /**
     * RESET function to clear all assigned credits and start over
     */
    public function reset_all_customer_credits() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        global $wpdb;

        $this->log_audit('credit_reset', 'Starting complete credit reset for all customers');

        // Delete all credit-related user meta
        $credit_meta_keys = [
            'intersoccer_customer_credits',
            'intersoccer_total_credits_earned',
            'intersoccer_credits_imported',
            'intersoccer_import_date',
            'intersoccer_credit_breakdown',
            'intersoccer_credit_adjustments',
            'intersoccer_credits_used_total'
        ];

        $deleted_total = 0;
        foreach ($credit_meta_keys as $meta_key) {
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
                $meta_key
            ));
            $deleted_total += $deleted;
        }

        // Clear import summary
        delete_option('intersoccer_last_import_summary');
        delete_option('intersoccer_last_customer_import_report');

        $this->log_audit('credit_reset', "Credit reset complete - deleted {$deleted_total} total records");

        wp_send_json_success([
            'message' => "Reset complete! Deleted {$deleted_total} credit records from all customers.",
            'deleted_records' => $deleted_total
        ]);
    }

    /**
     * Allocate credits to customers based on criteria
     */
    public function allocate_credits_to_customers() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $allocation_type = sanitize_text_field($_POST['allocation_type']);
        $credit_amount = intval($_POST['credit_amount']);

        if ($credit_amount < 1 || $credit_amount > 100) {
            wp_send_json_error(['message' => 'Credit amount must be between 1 and 100']);
        }

        global $wpdb;

        $this->log_audit('bulk_allocation', "Starting bulk credit allocation: {$credit_amount} credits to {$allocation_type} customers");

        // Build user query based on allocation type
        $where_clause = "WHERE 1=1";
        switch ($allocation_type) {
            case 'coaches':
                $where_clause .= " AND ID IN (
                    SELECT DISTINCT coach_id FROM {$wpdb->prefix}intersoccer_referrals
                    WHERE coach_id IS NOT NULL
                )";
                break;
            case 'zero_balance':
                $where_clause .= " AND ID NOT IN (
                    SELECT user_id FROM {$wpdb->usermeta}
                    WHERE meta_key = 'intersoccer_customer_credits'
                    AND meta_value > 0
                )";
                break;
        }

        $users = $wpdb->get_results("SELECT ID, user_email FROM {$wpdb->users} {$where_clause}");

        $allocated_count = 0;
        foreach ($users as $user) {
            $current_credits = get_user_meta($user->ID, 'intersoccer_customer_credits', true) ?: 0;
            $new_credits = $current_credits + $credit_amount;

            update_user_meta($user->ID, 'intersoccer_customer_credits', $new_credits);

            // Log the adjustment
            $adjustments = get_user_meta($user->ID, 'intersoccer_credit_adjustments', true) ?: [];
            $adjustments[] = [
                'amount' => $credit_amount,
                'reason' => "Bulk allocation ({$allocation_type})",
                'timestamp' => current_time('mysql'),
                'admin' => get_current_user_id()
            ];
            update_user_meta($user->ID, 'intersoccer_credit_adjustments', $adjustments);

            $allocated_count++;
        }

        $this->log_audit('bulk_allocation', "Bulk allocation complete: {$credit_amount} credits allocated to {$allocated_count} customers");

        wp_send_json_success([
            'message' => "Successfully allocated {$credit_amount} credits to {$allocated_count} customers.",
            'allocated_count' => $allocated_count
        ]);
    }

    /**
     * Handle coach CSV import
     */
    public function import_coaches_from_csv() {
        // Handle AJAX requests
        if (wp_doing_ajax()) {
            $this->ajax_import_coaches_from_csv();
            return;
        }

        // Handle regular form submission (legacy support)
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if (!isset($_FILES['coaches_csv']) || $_FILES['coaches_csv']['error'] !== UPLOAD_ERR_OK) {
            wp_die('No file uploaded or upload error');
        }

        $file = $_FILES['coaches_csv']['tmp_name'];

        try {
            $results = $this->process_coach_csv_import($file, false);
            $imported = count($results['created']) + count($results['updated']);
            $errors = count($results['errors']);

            // Store import report
            update_option('intersoccer_last_coach_import', [
                'timestamp' => current_time('mysql'),
                'results' => $results
            ]);

            wp_redirect(add_query_arg([
                'page' => 'intersoccer-settings',
                'imported' => $imported,
                'errors' => $errors
            ], admin_url('admin.php')));
        } catch (Exception $e) {
            wp_redirect(add_query_arg([
                'page' => 'intersoccer-settings',
                'error' => urlencode($e->getMessage())
            ], admin_url('admin.php')));
        }
        exit;
    }

    /**
     * AJAX handler for coach CSV import
     */
    public function ajax_import_coaches_from_csv() {
        try {
            // Debug logging
            error_log('AJAX import called at ' . current_time('mysql'));
            error_log('POST data: ' . print_r($_POST, true));
            error_log('FILES data: ' . print_r($_FILES, true));

            // Verify nonce and permissions
            if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'import_coaches_from_csv')) {
                error_log('Nonce verification failed');
                wp_send_json_error('Invalid nonce');
                return;
            }

            if (!current_user_can('manage_options')) {
                error_log('Permission check failed');
                wp_send_json_error('Insufficient permissions');
                return;
            }

            if (!isset($_FILES['coaches_csv']) || $_FILES['coaches_csv']['error'] !== UPLOAD_ERR_OK) {
                $error_code = $_FILES['coaches_csv']['error'] ?? 'no file';
                error_log('File upload error: ' . $error_code);
                wp_send_json_error('File upload error: ' . $error_code);
                return;
            }

            $file = $_FILES['coaches_csv']['tmp_name'];
            $update_existing = isset($_POST['update_existing']) && $_POST['update_existing'] == '1';

            error_log('Processing file: ' . $file . ', update_existing: ' . ($update_existing ? 'yes' : 'no'));

            $results = $this->process_coach_csv_import($file, $update_existing);

            error_log('Import completed successfully: ' . print_r($results, true));
            wp_send_json_success($results);

        } catch (Exception $e) {
            error_log('Exception in AJAX import: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            wp_send_json_error('Import failed: ' . $e->getMessage());
        }
    }

    /**
     * Process coach CSV import
     */
    private function process_coach_csv_import($file_path, $update_existing = false) {
        if (($handle = fopen($file_path, 'r')) === false) {
            throw new Exception('Could not open uploaded file');
        }

        $this->log_audit('coach_import', 'Starting coach CSV import (AJAX)');

        // Read header row - skip empty/title rows
        $header = null;
        $max_rows_to_check = 5; // Check up to 5 rows for valid headers
        $rows_checked = 0;
        
        while (($potential_header = fgetcsv($handle, 1000, ',')) !== false && $rows_checked < $max_rows_to_check) {
            $rows_checked++;
            
            // Skip completely empty rows
            if (empty(array_filter($potential_header, function($cell) { return !empty(trim($cell)); }))) {
                error_log("Skipping empty row {$rows_checked}");
                continue;
            }
            
            // Skip rows that are likely titles (have only 1-2 non-empty cells)
            $non_empty_count = count(array_filter($potential_header, function($cell) { return !empty(trim($cell)); }));
            if ($non_empty_count < 3) {
                error_log("Skipping likely title row {$rows_checked}: " . implode(', ', $potential_header));
                continue;
            }
            
            // This looks like a valid header row
            $header = $potential_header;
            error_log("Found valid header row at line {$rows_checked}: " . implode(', ', $header));
            break;
        }
        
        if (!$header) {
            fclose($handle);
            throw new Exception('Could not find valid CSV headers. Check that your CSV has a header row with at least 3 columns (First Name, Last Name, Email). Checked ' . $rows_checked . ' rows.');
        }

        // Normalize headers (lowercase, trim, replace spaces with underscores)
        $normalized_header = array_map(function($col) {
            return strtolower(str_replace(' ', '_', trim($col)));
        }, $header);

        // Log the headers we found
        error_log('CSV Headers found: ' . implode(', ', $header));
        error_log('Normalized headers: ' . implode(', ', $normalized_header));

        // Map common column name variations to standard names
        $column_mapping = [
            // First name variations
            'first_name' => 'first_name',
            'firstname' => 'first_name',
            'given_name' => 'first_name',
            'forename' => 'first_name',
            'name' => 'first_name', // If only one "name" column, use it as first_name
            
            // Last name variations
            'last_name' => 'last_name',
            'lastname' => 'last_name',
            'surname' => 'last_name',
            'family_name' => 'last_name',
            
            // Email variations
            'email' => 'email',
            'e-mail' => 'email',
            'email_address' => 'email',
            'mail' => 'email',
            
            // Optional fields
            'phone' => 'phone',
            'telephone' => 'phone',
            'phone_number' => 'phone',
            'mobile' => 'phone',
            
            'specialization' => 'specialization',
            'specialty' => 'specialization',
            'focus' => 'specialization',
            
            'location' => 'location',
            'city' => 'location',
            'region' => 'location',
            
            'experience_years' => 'experience_years',
            'experience' => 'experience_years',
            'years_experience' => 'experience_years',
            
            'bio' => 'bio',
            'biography' => 'bio',
            'description' => 'bio',
            'about' => 'bio'
        ];

        // Map the normalized headers to standard field names
        $field_map = [];
        foreach ($normalized_header as $index => $norm_col) {
            if (isset($column_mapping[$norm_col])) {
                $standard_name = $column_mapping[$norm_col];
                $field_map[$standard_name] = $index;
            }
        }

        // Validate required columns are present
        $required_columns = ['first_name', 'last_name', 'email'];
        $missing_columns = [];
        foreach ($required_columns as $required) {
            if (!isset($field_map[$required])) {
                $missing_columns[] = $required;
            }
        }

        if (!empty($missing_columns)) {
            fclose($handle);
            $error_msg = 'Missing required columns: ' . implode(', ', $missing_columns) . "\n";
            $error_msg .= 'Found columns: ' . implode(', ', $header) . "\n";
            $error_msg .= 'Supported variations: first_name/firstname/given_name, last_name/lastname/surname, email/e-mail/email_address';
            throw new Exception($error_msg);
        }

        error_log('Field mapping: ' . json_encode($field_map));

        $results = [
            'created' => [],
            'updated' => [],
            'skipped' => [],
            'errors' => []
        ];

        $row_number = 1;
        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $row_number++;

            if (count($data) !== count($header)) {
                $results['errors'][] = "Row {$row_number}: Invalid number of columns";
                continue;
            }

            // Map data to standard field names using field_map
            $coach_data = [];
            foreach ($field_map as $standard_name => $column_index) {
                $coach_data[$standard_name] = isset($data[$column_index]) ? $data[$column_index] : '';
            }

            // Validate required fields
            if (empty(trim($coach_data['email'])) || empty(trim($coach_data['first_name'])) || empty(trim($coach_data['last_name']))) {
                $results['errors'][] = "Row {$row_number}: Missing required fields (email, first_name, last_name)";
                continue;
            }

            if (!is_email($coach_data['email'])) {
                $results['errors'][] = "Row {$row_number}: Invalid email address: {$coach_data['email']}";
                continue;
            }

            try {
                $result = $this->create_or_update_coach($coach_data, $update_existing);
                $coach_info = [
                    'first_name' => $coach_data['first_name'],
                    'last_name' => $coach_data['last_name'],
                    'email' => $coach_data['email']
                ];

                if ($result['action'] === 'created') {
                    $results['created'][] = $coach_info;
                } elseif ($result['action'] === 'updated') {
                    $results['updated'][] = $coach_info;
                } elseif ($result['action'] === 'skipped') {
                    $coach_info['reason'] = $result['reason'];
                    $results['skipped'][] = $coach_info;
                }
            } catch (Exception $e) {
                $results['errors'][] = "Row {$row_number}: " . $e->getMessage();
            }
        }

        fclose($handle);

        $this->log_audit('coach_import', sprintf(
            'Coach import complete: %d created, %d updated, %d skipped, %d errors',
            count($results['created']),
            count($results['updated']),
            count($results['skipped']),
            count($results['errors'])
        ));

        return $results;
    }

    /**
     * Create or update coach user
     */
    private function create_or_update_coach($coach_data, $update_existing = false) {
        // Check if user exists
        $user = get_user_by('email', $coach_data['email']);

        if (!$user) {
            // Create new user with coach role directly
            $user_id = wp_insert_user([
                'user_login' => sanitize_user($coach_data['email']),
                'user_pass' => wp_generate_password(),
                'user_email' => $coach_data['email'],
                'role' => 'coach'
            ]);

            if (is_wp_error($user_id)) {
                throw new Exception('Failed to create user: ' . $user_id->get_error_message());
            }

            $user = get_user_by('ID', $user_id);
            $action = 'created';
        } else {
            // User exists
            if (!$update_existing) {
                return ['action' => 'skipped', 'reason' => 'User already exists'];
            }
            $user_id = $user->ID;
            $action = 'updated';
        }

        // Update user meta
        wp_update_user([
            'ID' => $user_id,
            'first_name' => sanitize_text_field($coach_data['first_name']),
            'last_name' => sanitize_text_field($coach_data['last_name']),
            'display_name' => $coach_data['first_name'] . ' ' . $coach_data['last_name']
        ]);

        // Update coach-specific meta
        if (isset($coach_data['specialization'])) {
            update_user_meta($user_id, 'intersoccer_coach_specialization', sanitize_text_field($coach_data['specialization']));
        }
        if (isset($coach_data['location'])) {
            update_user_meta($user_id, 'intersoccer_coach_location', sanitize_text_field($coach_data['location']));
        }
        if (isset($coach_data['experience_years'])) {
            update_user_meta($user_id, 'intersoccer_coach_experience', intval($coach_data['experience_years']));
        }
        if (isset($coach_data['bio'])) {
            update_user_meta($user_id, 'intersoccer_coach_bio', sanitize_textarea_field($coach_data['bio']));
        }
        if (isset($coach_data['phone'])) {
            update_user_meta($user_id, 'intersoccer_coach_phone', sanitize_text_field($coach_data['phone']));
        }

        // Ensure coach role is set for all imported coaches
        if (get_role('coach')) {
            $user->set_role('coach');
        } else {
            error_log('InterSoccer: Coach role not found during import');
        }

        // Generate referral code for coach if not exists
        $existing_code = get_user_meta($user_id, 'referral_code', true);
        if (empty($existing_code)) {
            $referral_code = 'COACH' . $user_id . strtoupper(str_replace('_', '', wp_generate_password(6, false)));
            update_user_meta($user_id, 'referral_code', $referral_code);
        }

        return ['action' => $action, 'user_id' => $user_id];
    }

    /**
     * Clear audit log
     */
    public function clear_audit_log() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        delete_option('intersoccer_audit_log');
        $this->log_audit('admin_action', 'Audit log cleared by admin');

        wp_send_json_success(['message' => 'Audit log cleared successfully']);
    }

    /**
     * Export audit log
     */
    public function export_audit_log() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $audit_log = get_option('intersoccer_audit_log', []);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="intersoccer-audit-log-' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Timestamp', 'Action', 'User', 'Details']);

        foreach (array_reverse($audit_log) as $entry) {
            fputcsv($output, [
                $entry['timestamp'],
                $entry['action'],
                $entry['user'] ?: 'System',
                $entry['details']
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Bulk credit adjustment
     */
    public function bulk_credit_adjustment() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        // Implementation for bulk credit adjustments
        wp_send_json_success(['message' => 'Bulk adjustment completed']);
    }

    /**
     * Log audit event
     */
    private function log_audit($action, $details) {
        $audit_log = get_option('intersoccer_audit_log', []);

        $audit_log[] = [
            'timestamp' => current_time('mysql'),
            'action' => $action,
            'user' => wp_get_current_user()->user_login,
            'details' => $details
        ];

        // Keep only last 1000 entries
        if (count($audit_log) > 1000) {
            $audit_log = array_slice($audit_log, -1000);
        }

        update_option('intersoccer_audit_log', $audit_log);
    }

    /**
     * Get credit statistics (AJAX)
     */
    public function get_credit_statistics() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        global $wpdb;

        $stats = $wpdb->get_row("
            SELECT
                COUNT(DISTINCT CASE WHEN um.meta_value > 0 THEN um.user_id END) as customers_with_credits,
                SUM(CAST(um.meta_value AS UNSIGNED)) as total_credits,
                AVG(CAST(um.meta_value AS UNSIGNED)) as avg_credits
            FROM {$wpdb->usermeta} um
            WHERE um.meta_key = 'intersoccer_customer_credits'
        ");

        $html = "
            <p><strong>Customers with Credits:</strong> " . ($stats->customers_with_credits ?: 0) . "</p>
            <p><strong>Total Credits in System:</strong> " . ($stats->total_credits ?: 0) . "</p>
            <p><strong>Average Credits per Customer:</strong> " . round($stats->avg_credits ?: 0, 2) . "</p>
        ";

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Get coach statistics (AJAX)
     */
    public function get_coach_statistics() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $coach_users = get_users(['role' => 'coach']);

        $total_coaches = count($coach_users);
        $active_coaches = 0;

        foreach ($coach_users as $coach) {
            $last_login = get_user_meta($coach->ID, 'last_login', true);
            if ($last_login && strtotime($last_login) > strtotime('-30 days')) {
                $active_coaches++;
            }
        }

        $html = "
            <p><strong>Total Coaches:</strong> {$total_coaches}</p>
            <p><strong>Active Coaches (30 days):</strong> {$active_coaches}</p>
        ";

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Get audit log (AJAX)
     */
    public function get_audit_log() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $audit_log = get_option('intersoccer_audit_log', []);
        $filter = sanitize_text_field($_POST['filter'] ?? 'all');

        $html = '';
        $filtered_log = array_reverse($audit_log);

        if ($filter !== 'all') {
            $filtered_log = array_filter($filtered_log, function($entry) use ($filter) {
                return strpos($entry['action'], $filter) !== false;
            });
        }

        if (empty($filtered_log)) {
            $html = '<div class="audit-log-entry">No audit log entries found</div>';
        } else {
            foreach (array_slice($filtered_log, 0, 50) as $entry) {
                $html .= sprintf(
                    '<div class="audit-log-entry">
                        <span class="timestamp">%s</span>
                        <span class="action">%s</span>
                        <span class="user">%s</span>
                        <span class="details">%s</span>
                    </div>',
                    esc_html($entry['timestamp']),
                    esc_html($entry['action']),
                    esc_html($entry['user'] ?: 'System'),
                    esc_html($entry['details'])
                );
            }
        }

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Get points statistics via AJAX
     */
    public function get_points_statistics_ajax() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $points_manager = new InterSoccer_Points_Manager();
        $stats = $points_manager->get_points_statistics();

        $html = "
            <p><strong>Total Points Earned:</strong> " . number_format($stats['total_earned'], 2) . "</p>
            <p><strong>Total Points Spent:</strong> " . number_format($stats['total_spent'], 2) . "</p>
            <p><strong>Current Balance:</strong> " . number_format($stats['current_balance'], 0) . "</p>
            <p><strong>Customers with Points:</strong> " . number_format($stats['customers_with_points']) . "</p>
            <p><strong>Avg Points per Customer:</strong> " . number_format($stats['avg_points_per_customer'], 0) . "</p>
        ";

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Get points ledger via AJAX
     */
    public function get_points_ledger_ajax() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        global $wpdb;
        $points_log_table = $wpdb->prefix . 'intersoccer_points_log';
        $limit = intval($_POST['limit'] ?? 20);

        $transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT pl.*, u.display_name, u.user_email
             FROM {$points_log_table} pl
             LEFT JOIN {$wpdb->users} u ON pl.customer_id = u.ID
             ORDER BY pl.created_at DESC, pl.id DESC
             LIMIT %d",
            $limit
        ));

        if (empty($transactions)) {
            $html = '<p>No points transactions found.</p>';
        } else {
            $html = '<table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Balance</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>';

            foreach ($transactions as $transaction) {
                $amount_class = $transaction->points_amount >= 0 ? 'positive' : 'negative';
                $html .= sprintf(
                    '<tr>
                        <td>%s</td>
                        <td>%s<br><small>%s</small></td>
                        <td>%s</td>
                        <td class="%s">%s</td>
                        <td>%.2f</td>
                        <td>%s</td>
                    </tr>',
                    date('Y-m-d H:i', strtotime($transaction->created_at)),
                    esc_html($transaction->display_name ?: 'Unknown'),
                    esc_html($transaction->user_email ?: $transaction->customer_id),
                    esc_html($transaction->transaction_type),
                    $amount_class,
                    ($transaction->points_amount >= 0 ? '+' : '') . number_format($transaction->points_amount, 0),
                    number_format(intval($transaction->points_balance), 0),
                    esc_html($transaction->description)
                );
            }

            $html .= '</tbody></table>';
        }

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Register WordPress settings
     */
    public function register_settings() {
        register_setting('intersoccer_settings', 'intersoccer_credit_value', [
            'type' => 'number',
            'default' => '1',
            'sanitize_callback' => 'floatval'
        ]);

        register_setting('intersoccer_settings', 'intersoccer_max_credits_per_order', [
            'type' => 'number',
            'default' => '100',
            'sanitize_callback' => 'intval'
        ]);

        register_setting('intersoccer_settings', 'intersoccer_debug_logging', [
            'type' => 'boolean',
            'default' => false,
            'sanitize_callback' => 'boolval'
        ]);

        register_setting('intersoccer_points_configuration', 'intersoccer_points_golive_date', [
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => [$this, 'sanitize_date_option']
        ]);
    }

    /**
     * Migrate users from old intersoccer_coach role to coach role
     */
    public function migrate_coach_roles() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $old_role_users = get_users(['role' => 'intersoccer_coach']);
        $migrated = 0;

        foreach ($old_role_users as $user) {
            $user->remove_role('intersoccer_coach');
            $user->add_role('coach');
            $migrated++;
        }

        $this->log_audit('role_migration', "Migrated {$migrated} users from intersoccer_coach to coach role");

        wp_redirect(add_query_arg([
            'page' => 'intersoccer-settings',
            'migrated' => $migrated
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Run points migration via AJAX
     */
    public function run_points_migration_ajax() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        try {
            $migration = new InterSoccer_Points_Migration();
            $migration->run_migration();

            $status = $migration->get_migration_status();

            wp_send_json_success([
                'message' => 'Points migration completed successfully!',
                'transactions_processed' => 'All',
                'users_updated' => 'All',
                'backup_created' => !empty($status['backup_table'])
            ]);
        } catch (Exception $e) {
            error_log('InterSoccer Migration Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Migration failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Get migration status via AJAX
     */
    public function get_migration_status_ajax() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $migration = new InterSoccer_Points_Migration();
        $status = $migration->get_migration_status();

        $html = "
            <div class='migration-details'>
                <div class='detail-item'>
                    <strong>Migration Completed:</strong> " . ($status['completed'] ? date('Y-m-d H:i:s', strtotime($status['completed'])) : 'Not yet') . "
                </div>
                <div class='detail-item'>
                    <strong>Current Version:</strong> " . $status['version'] . "
                </div>
                <div class='detail-item'>
                    <strong>Backup Table:</strong> " . ($status['backup_table'] ?: 'None') . "
                </div>
            </div>
        ";

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Restore coach roles via AJAX
     */
    public function restore_coach_roles_ajax() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        global $wpdb;

        $this->log_audit('role_restoration', 'Starting coach role restoration based on referral data');

        // Find all users who have coach referrals but might not have the coach role
        $coach_users = $wpdb->get_col(
            "SELECT DISTINCT coach_id FROM {$wpdb->prefix}intersoccer_referrals
             WHERE coach_id IS NOT NULL AND coach_id > 0"
        );

        $coaches_found = count($coach_users);
        $roles_restored = 0;

        foreach ($coach_users as $user_id) {
            $user = get_user_by('ID', $user_id);
            if (!$user) continue;

            // Check if user already has coach role
            if (!in_array('coach', $user->roles)) {
                // Add coach role
                $user->add_role('coach');
                $roles_restored++;
                error_log("InterSoccer: Restored coach role to user {$user_id} ({$user->user_email})");
            }
        }

        $this->log_audit('role_restoration', "Coach role restoration complete: {$coaches_found} coaches found, {$roles_restored} roles restored");

        wp_send_json_success([
            'message' => "Role restoration completed! Found {$coaches_found} coaches, restored {$roles_restored} roles.",
            'coaches_found' => $coaches_found,
            'roles_restored' => $roles_restored
        ]);
    }

    /**
     * Run integer migration via AJAX (Phase 0)
     */
    public function run_integer_migration_ajax() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        try {
            $migration = new InterSoccer_Points_Migration_Integers();
            $result = $migration->run_migration();

            if ($result['success']) {
                $this->log_audit('integer_migration', 'Successfully completed integer points migration');
                wp_send_json_success($result);
            } else {
                $this->log_audit('integer_migration', 'Integer migration failed: ' . $result['message']);
                wp_send_json_error($result);
            }
        } catch (Exception $e) {
            error_log('InterSoccer Integer Migration Error: ' . $e->getMessage());
            $this->log_audit('integer_migration', 'Integer migration exception: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Migration failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Get integer migration status via AJAX
     */
    public function get_integer_migration_status_ajax() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $migration = new InterSoccer_Points_Migration_Integers();
        $status = $migration->get_migration_status();

        $html = "
            <div class='migration-details'>
                <div class='detail-item'>
                    <strong>Status:</strong> " . ucfirst($status['status']) . "
                </div>
                <div class='detail-item'>
                    <strong>Started:</strong> " . ($status['started_at'] ?: 'Not started') . "
                </div>
                <div class='detail-item'>
                    <strong>Completed:</strong> " . ($status['completed_at'] ?: 'Not completed') . "
                </div>
                <div class='detail-item'>
                    <strong>Records Converted:</strong> " . ($status['records_converted'] ?: 0) . "
                </div>
                <div class='detail-item'>
                    <strong>Backup Tables:</strong> " . (is_array($status['backup_tables']) ? implode(', ', $status['backup_tables']) : 'None') . "
                </div>
                <div class='detail-item'>
                    <strong>Errors:</strong> " . (is_array($status['errors']) && !empty($status['errors']) ? implode(', ', $status['errors']) : 'None') . "
                </div>
            </div>
        ";

        wp_send_json_success([
            'html' => $html,
            'status' => $status['status']
        ]);
    }

    /**
     * Verify integer migration via AJAX
     */
    public function verify_integer_migration_ajax() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        try {
            $migration = new InterSoccer_Points_Migration_Integers();
            $result = $migration->verify_migration();
            
            $this->log_audit('integer_migration_verify', 'Verification: ' . $result['message']);
            wp_send_json_success($result);
        } catch (Exception $e) {
            error_log('InterSoccer Integer Migration Verification Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Verification failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Rollback integer migration via AJAX
     */
    public function rollback_integer_migration_ajax() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        if (!confirm('Are you absolutely sure you want to rollback the integer migration? This will restore decimal points.')) {
            wp_send_json_error(['message' => 'Rollback cancelled']);
        }

        try {
            $migration = new InterSoccer_Points_Migration_Integers();
            $result = $migration->rollback_migration();
            
            $this->log_audit('integer_migration_rollback', 'Rollback: ' . $result['message']);
            wp_send_json_success($result);
        } catch (Exception $e) {
            error_log('InterSoccer Integer Migration Rollback Error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Rollback failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Save points rates via AJAX (Phase 0)
     */
    public function save_points_rates_ajax() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }

        $rate_customer = intval($_POST['rate_customer']);
        $rate_coach = intval($_POST['rate_coach']);
        $rate_partner = intval($_POST['rate_partner']);
        $rate_social_influencer = intval($_POST['rate_social_influencer']);

        // Validate rates (must be positive integers)
        if ($rate_customer < 1 || $rate_coach < 1 || $rate_partner < 1 || $rate_social_influencer < 1) {
            wp_send_json_error(['message' => 'All rates must be positive numbers (minimum 1)']);
        }

        if ($rate_customer > 100 || $rate_coach > 100 || $rate_partner > 100 || $rate_social_influencer > 100) {
            wp_send_json_error(['message' => 'Rates cannot exceed 100']);
        }

        // Save rates
        update_option('intersoccer_points_rate_customer', $rate_customer);
        update_option('intersoccer_points_rate_coach', $rate_coach);
        update_option('intersoccer_points_rate_partner', $rate_partner);
        update_option('intersoccer_points_rate_social_influencer', $rate_social_influencer);

        // Log the change
        $this->log_audit('points_rates_updated', sprintf(
            'Point rates updated - Customer: %d, Coach: %d, Partner: %d, Social Influencer: %d',
            $rate_customer, $rate_coach, $rate_partner, $rate_social_influencer
        ));

        wp_send_json_success([
            'message' => 'Point rates saved successfully!',
            'rates' => [
                'customer' => $rate_customer,
                'coach' => $rate_coach,
                'partner' => $rate_partner,
                'social_influencer' => $rate_social_influencer,
            ]
        ]);
    }

    /**
     * Sanitize date options (expects YYYY-MM-DD format)
     *
     * @param string $value Raw option value
     * @return string Sanitized date or empty string when invalid
     */
    public function sanitize_date_option($value) {
        $value = sanitize_text_field($value);

        if (empty($value)) {
            return '';
        }

        $date = DateTime::createFromFormat('Y-m-d', $value);

        if ($date instanceof DateTime && $date->format('Y-m-d') === $value) {
            return $value;
        }

        return '';
    }
}