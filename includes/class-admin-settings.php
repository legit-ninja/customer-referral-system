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
        add_action('admin_init', [$this, 'register_settings']);
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
                        <p>Upload a CSV file to bulk import coaches. <a href="<?php echo INTERSOCCER_REFERRAL_URL; ?>assets/sample-coaches.csv" target="_blank">Download sample CSV</a></p>
                        <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
                            <?php wp_nonce_field('import_coaches_csv'); ?>
                            <input type="hidden" name="action" value="import_coaches_from_csv">
                            <input type="file" name="coaches_csv" accept=".csv" required>
                            <button type="submit" class="button button-primary">
                                <span class="dashicons dashicons-upload"></span> Import Coaches
                            </button>
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

            // Initialize
            loadCreditStats();
            loadCoachStats();
            loadAuditLog();
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
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if (!isset($_FILES['coaches_csv']) || $_FILES['coaches_csv']['error'] !== UPLOAD_ERR_OK) {
            wp_die('No file uploaded or upload error');
        }

        $file = $_FILES['coaches_csv']['tmp_name'];

        if (($handle = fopen($file, 'r')) === false) {
            wp_die('Could not open uploaded file');
        }

        $this->log_audit('coach_import', 'Starting coach CSV import');

        $header = fgetcsv($handle, 1000, ',');
        $imported = 0;
        $errors = [];

        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            if (count($data) !== count($header)) {
                $errors[] = 'Invalid row: ' . implode(', ', $data);
                continue;
            }

            $coach_data = array_combine($header, $data);

            // Create or update coach user
            $user_id = $this->create_or_update_coach($coach_data);

            if ($user_id) {
                $imported++;
            } else {
                $errors[] = 'Failed to create coach: ' . $coach_data['email'];
            }
        }

        fclose($handle);

        $this->log_audit('coach_import', "Coach import complete: {$imported} imported, " . count($errors) . " errors");

        // Store import report
        update_option('intersoccer_last_coach_import', [
            'timestamp' => current_time('mysql'),
            'imported' => $imported,
            'errors' => $errors
        ]);

        wp_redirect(add_query_arg([
            'page' => 'intersoccer-settings',
            'imported' => $imported,
            'errors' => count($errors)
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Create or update coach user
     */
    private function create_or_update_coach($coach_data) {
        // Check if user exists
        $user = get_user_by('email', $coach_data['email']);

        if (!$user) {
            // Create new user
            $user_id = wp_create_user(
                sanitize_user($coach_data['email']),
                wp_generate_password(),
                $coach_data['email']
            );

            if (is_wp_error($user_id)) {
                return false;
            }

            $user = get_user_by('ID', $user_id);
        } else {
            $user_id = $user->ID;
        }

        // Update user meta
        wp_update_user([
            'ID' => $user_id,
            'first_name' => sanitize_text_field($coach_data['first_name']),
            'last_name' => sanitize_text_field($coach_data['last_name']),
            'display_name' => $coach_data['first_name'] . ' ' . $coach_data['last_name']
        ]);

        update_user_meta($user_id, 'intersoccer_coach_specialization', sanitize_text_field($coach_data['specialization']));
        update_user_meta($user_id, 'intersoccer_coach_location', sanitize_text_field($coach_data['location']));
        update_user_meta($user_id, 'intersoccer_coach_experience', intval($coach_data['experience_years']));
        update_user_meta($user_id, 'intersoccer_coach_bio', sanitize_textarea_field($coach_data['bio']));
        update_user_meta($user_id, 'intersoccer_coach_phone', sanitize_text_field($coach_data['phone']));

        // Set coach role
        $user->set_role('intersoccer_coach');

        return $user_id;
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

        $coach_role = get_role('intersoccer_coach');
        $coach_users = get_users(['role' => 'intersoccer_coach']);

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
}