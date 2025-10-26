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
        add_action('wp_ajax_run_points_sync', [$this, 'run_points_sync_ajax']);
        add_action('wp_ajax_get_sync_info', [$this, 'get_sync_info_ajax']);
        add_action('wp_ajax_get_sync_info_ajax', [$this, 'get_sync_info_ajax']);
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
                            <th scope="row">Referral Commission (%)</th>
                            <td>
                                <input type="number" name="intersoccer_referral_commission" value="<?php echo get_option('intersoccer_referral_commission', '10'); ?>" min="0" max="100" step="0.1">
                                <p class="description">Percentage commission for coach referrals</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Save Settings'); ?>
                </form>
            </div>

            <!-- Points Management -->
            <div class="intersoccer-settings-section">
                <h2>Points Management</h2>
                <div class="settings-grid">
                    <div class="settings-card">
                        <h3>Scan Orders for Points</h3>
                        <p>Scan existing WooCommerce orders and allocate points to customers based on order amounts.</p>
                        <button id="scan-orders-points" class="button button-primary">
                            <span class="dashicons dashicons-search"></span> Scan Orders
                        </button>
                        <div id="scan-progress" style="display: none; margin-top: 10px;">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 0%"></div>
                            </div>
                            <p class="progress-text">Scanning orders...</p>
                        </div>
                    </div>

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

            <!-- One-Time Points Sync -->
            <div class="intersoccer-settings-section">
                <h2>🚀 One-Time Points Synchronization</h2>
                <div class="sync-notice">
                    <div class="notice notice-info">
                        <p><strong>Important:</strong> This tool scans all existing WooCommerce orders and allocates points to customers based on their order history. This is designed to be run once during initial setup.</p>
                        <p><strong>What it does:</strong></p>
                        <ul>
                            <li>Scans all completed orders that haven't been processed for points</li>
                            <li>Allocates 1 point per CHF spent on each order</li>
                            <li>Creates detailed transaction records in the points ledger</li>
                            <li>Updates customer point balances</li>
                        </ul>
                        <p><strong>⚠️ Note:</strong> Future orders will automatically allocate points. This is only needed for historical data.</p>
                    </div>
                </div>

                <div class="sync-controls">
                    <div class="sync-card">
                        <h3>Initial Points Allocation</h3>
                        <p>Run this once to allocate points for all existing orders.</p>
                        <div class="sync-status" id="sync-status">
                            <span class="status-indicator status-ready">Ready to sync</span>
                        </div>
                        <button id="run-points-sync" class="button button-primary button-hero">
                            <span class="dashicons dashicons-update"></span>
                            Run One-Time Points Sync
                        </button>
                        <div id="sync-progress" style="display: none; margin-top: 20px;">
                            <div class="progress-container">
                                <div class="progress-bar">
                                    <div class="progress-fill" id="sync-progress-fill" style="width: 0%"></div>
                                </div>
                                <div class="progress-text" id="sync-progress-text">Initializing sync...</div>
                            </div>
                            <div class="sync-details" id="sync-details" style="margin-top: 15px;">
                                <div class="detail-item"><strong>Orders Processed:</strong> <span id="orders-processed">0</span></div>
                                <div class="detail-item"><strong>Points Allocated:</strong> <span id="points-allocated">0.00</span></div>
                                <div class="detail-item"><strong>Customers Updated:</strong> <span id="customers-updated">0</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="sync-card">
                        <h3>Sync Information</h3>
                        <div id="sync-info">
                            <p>Loading sync information...</p>
                        </div>
                        <button id="refresh-sync-info" class="button">Refresh Info</button>
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

            $('#scan-orders-points').on('click', function(e) {
                e.preventDefault();
                if (!confirm('This will scan all completed orders and allocate points. This may take some time. Continue?')) {
                    return;
                }

                const $button = $(this);
                const $progress = $('#scan-progress');
                const $progressFill = $('.progress-fill');
                const $progressText = $('.progress-text');

                $button.prop('disabled', true).text('Scanning...');
                $progress.show();
                $progressFill.css('width', '0%');
                $progressText.text('Scanning orders...');

                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'scan_orders_for_points',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        $progressFill.css('width', '100%');
                        $progressText.text('Scan completed!');
                        setTimeout(() => {
                            $progress.hide();
                            $button.prop('disabled', false).text('Scan Orders');
                            alert(response.data.message);
                            loadPointsStats();
                        }, 2000);
                    },
                    error: function() {
                        $progress.hide();
                        $button.prop('disabled', false).text('Scan Orders');
                        alert('Error scanning orders. Please try again.');
                    }
                });
            });

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

            // One-Time Points Sync
            $('#run-points-sync').on('click', function(e) {
                e.preventDefault();

                if (!confirm('This will scan ALL existing WooCommerce orders and allocate points. This operation may take several minutes. Continue?')) {
                    return;
                }

                const $button = $(this);
                const $progress = $('#sync-progress');
                const $status = $('#sync-status');
                const $progressFill = $('#sync-progress-fill');
                const $progressText = $('#sync-progress-text');

                // Update status
                $status.html('<span class="status-indicator status-running">Running sync...</span>');
                $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Running Sync...');

                $progress.show();
                $progressFill.css('width', '0%');
                $progressText.text('Initializing points synchronization...');

                // Start the sync
                runPointsSync();
            });

            function runPointsSync() {
                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'run_points_sync',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#sync-progress-fill').css('width', '100%');
                            $('#sync-progress-text').text('Sync completed successfully!');
                            $('#sync-status').html('<span class="status-indicator status-success">Sync completed</span>');
                            $('#run-points-sync').prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Sync Complete');

                            // Update details
                            $('#orders-processed').text(response.data.processed || 0);
                            $('#points-allocated').text((response.data.points_allocated || 0).toFixed(2));
                            $('#customers-updated').text(response.data.customers_updated || 0);

                            // Show success message
                            setTimeout(() => {
                                alert(response.data.message);
                                loadSyncInfo();
                                loadPointsStats();
                            }, 1000);
                        } else {
                            $('#sync-status').html('<span class="status-indicator status-error">Sync failed</span>');
                            $('#run-points-sync').prop('disabled', false).html('<span class="dashicons dashicons-warning"></span> Retry Sync');
                            alert('Sync failed: ' + (response.data?.message || 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#sync-status').html('<span class="status-indicator status-error">Sync error</span>');
                        $('#run-points-sync').prop('disabled', false).html('<span class="dashicons dashicons-warning"></span> Retry Sync');
                        $('#sync-progress-text').text('Error occurred during sync');
                        alert('AJAX Error: ' + error);
                    }
                });
            }

            // Load sync information
            function loadSyncInfo() {
                $('#sync-info').html('<p>Loading sync information...</p>');
                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_sync_info',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        $('#sync-info').html(response.data.html);
                    },
                    error: function() {
                        $('#sync-info').html('<p>Error loading sync information</p>');
                    }
                });
            }

            $('#refresh-sync-info').on('click', loadSyncInfo);

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

            // One-time points sync functionality
            $('#run-points-sync').on('click', function(e) {
                e.preventDefault();

                if (!confirm('This will scan ALL existing WooCommerce orders and allocate points. This operation may take several minutes. Continue?')) {
                    return;
                }

                const $button = $(this);
                const $progress = $('#sync-progress');
                const $status = $('#sync-status');
                const $progressFill = $('#sync-progress-fill');
                const $progressText = $('#sync-progress-text');

                // Update status
                $status.html('<span class="status-indicator status-running">Running sync...</span>');
                $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Running Sync...');

                $progress.show();
                $progressFill.css('width', '0%');
                $progressText.text('Initializing points synchronization...');

                // Start the sync
                runPointsSync();
            });

            function runPointsSync() {
                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'run_points_sync',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#sync-progress-fill').css('width', '100%');
                            $('#sync-progress-text').text('Sync completed successfully!');
                            $('#sync-status').html('<span class="status-indicator status-success">Sync completed</span>');
                            $('#run-points-sync').prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Sync Complete');

                            // Update details
                            $('#orders-processed').text(response.data.processed || 0);
                            $('#points-allocated').text((response.data.points_allocated || 0).toFixed(2));
                            $('#customers-updated').text(response.data.customers_updated || 0);

                            // Show success message
                            setTimeout(() => {
                                alert(response.data.message);
                                loadSyncInfo();
                                loadPointsStats();
                            }, 1000);
                        } else {
                            $('#sync-status').html('<span class="status-indicator status-error">Sync failed</span>');
                            $('#run-points-sync').prop('disabled', false).html('<span class="dashicons dashicons-warning"></span> Retry Sync');
                            alert('Sync failed: ' + (response.data?.message || 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#sync-status').html('<span class="status-indicator status-error">Sync error</span>');
                        $('#run-points-sync').prop('disabled', false).html('<span class="dashicons dashicons-warning"></span> Retry Sync');
                        $('#sync-progress-text').text('Error occurred during sync');
                        alert('AJAX Error: ' + error);
                    }
                });
            }

            // Load sync information
            function loadSyncInfo() {
                $('#sync-info').html('<p>Loading sync information...</p>');
                $.ajax({
                    url: intersoccer_admin.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'get_sync_info',
                        nonce: intersoccer_admin.nonce
                    },
                    success: function(response) {
                        $('#sync-info').html(response.data.html);
                    },
                    error: function() {
                        $('#sync-info').html('<p>Error loading sync information</p>');
                    }
                });
            }

            $('#refresh-sync-info').on('click', loadSyncInfo);

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
            loadSyncInfo();
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

        $header = fgetcsv($handle, 1000, ',');
        if (!$header) {
            fclose($handle);
            throw new Exception('Could not read CSV header');
        }

        // Validate required columns
        $required_columns = ['first_name', 'last_name', 'email'];
        $missing_columns = array_diff($required_columns, $header);
        if (!empty($missing_columns)) {
            fclose($handle);
            throw new Exception('Missing required columns: ' . implode(', ', $missing_columns));
        }

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

            $coach_data = array_combine($header, $data);

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
            <p><strong>Current Balance:</strong> " . number_format($stats['current_balance'], 2) . "</p>
            <p><strong>Customers with Points:</strong> " . number_format($stats['customers_with_points']) . "</p>
            <p><strong>Avg Points per Customer:</strong> " . number_format($stats['avg_points_per_customer'], 2) . "</p>
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
                    ($transaction->points_amount >= 0 ? '+' : '') . number_format($transaction->points_amount, 2),
                    floatval($transaction->points_balance),
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

        register_setting('intersoccer_settings', 'intersoccer_referral_commission', [
            'type' => 'number',
            'default' => '10',
            'sanitize_callback' => 'floatval'
        ]);
    }

    /**
     * Get sync information via AJAX
     */
    public function get_sync_info_ajax() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $points_manager = new InterSoccer_Points_Manager();
        $sync_status = get_option('intersoccer_points_sync_status', [
            'last_sync' => null,
            'total_processed' => 0,
            'total_points' => 0,
            'status' => 'never_run'
        ]);

        $stats = $points_manager->get_points_statistics();

        $html = "
            <div class='sync-details'>
                <div class='detail-item'>
                    <strong>Last Sync:</strong> " . ($sync_status['last_sync'] ? date('Y-m-d H:i:s', strtotime($sync_status['last_sync'])) : 'Never') . "
                </div>
                <div class='detail-item'>
                    <strong>Orders Processed:</strong> " . number_format($sync_status['total_processed']) . "
                </div>
                <div class='detail-item'>
                    <strong>Total Points Allocated:</strong> " . number_format($sync_status['total_points'], 2) . "
                </div>
                <div class='detail-item'>
                    <strong>Current Status:</strong> " . ucfirst($sync_status['status']) . "
                </div>
                <div class='detail-item'>
                    <strong>Total Customers with Points:</strong> " . number_format($stats['customers_with_points']) . "
                </div>
                <div class='detail-item'>
                    <strong>Total Points in System:</strong> " . number_format($stats['total_points_earned'], 2) . "
                </div>
            </div>
        ";

        wp_send_json_success(['html' => $html]);
    }

    /**
     * Run points sync via AJAX
     */
    public function run_points_sync_ajax() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $points_manager = new InterSoccer_Points_Manager();
        $result = $points_manager->perform_points_sync();

        wp_send_json_success([
            'message' => 'Points sync completed successfully',
            'processed' => $result['processed'],
            'points_allocated' => $result['points_allocated']
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
}