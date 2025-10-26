<?php
// includes/class-audit-logger.php

/**
 * InterSoccer Audit Logger
 *
 * Comprehensive audit logging system for security and compliance.
 * Tracks all critical actions in the referral system.
 */
class InterSoccer_Audit_Logger {

    private static $instance = null;
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'intersoccer_audit_log';

        // Hook into various system events
        $this->register_hooks();
    }

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register hooks for audit logging
     */
    private function register_hooks() {
        // User and role management
        add_action('user_register', [$this, 'log_user_registration']);
        add_action('set_user_role', [$this, 'log_role_change'], 10, 3);
        add_action('profile_update', [$this, 'log_profile_update'], 10, 2);

        // Coach import events
        add_action('intersoccer_coach_import_success', [$this, 'log_coach_import'], 10, 2);
        add_action('intersoccer_coach_import_failed', [$this, 'log_coach_import_failure'], 10, 2);

        // Referral system events
        add_action('intersoccer_referral_code_used', [$this, 'log_referral_code_usage'], 10, 3);
        add_action('intersoccer_referral_code_invalid', [$this, 'log_referral_code_failure'], 10, 2);
        add_action('intersoccer_referral_created', [$this, 'log_referral_creation'], 10, 2);

        // Points system events
        add_action('intersoccer_points_earned', [$this, 'log_points_earned'], 10, 4);
        add_action('intersoccer_points_redeemed', [$this, 'log_points_redeemed'], 10, 4);
        add_action('intersoccer_points_adjusted', [$this, 'log_points_adjustment'], 10, 4);

        // Commission events
        add_action('intersoccer_commission_paid', [$this, 'log_commission_payment'], 10, 3);
        add_action('intersoccer_commission_calculated', [$this, 'log_commission_calculation'], 10, 3);

        // Security events
        add_action('intersoccer_suspicious_activity', [$this, 'log_suspicious_activity'], 10, 3);
        add_action('intersoccer_rate_limit_exceeded', [$this, 'log_rate_limit_exceeded'], 10, 2);

        // Admin actions
        add_action('intersoccer_admin_settings_changed', [$this, 'log_admin_settings_change'], 10, 3);
        add_action('intersoccer_admin_user_action', [$this, 'log_admin_user_action'], 10, 4);

        // System events
        add_action('intersoccer_system_error', [$this, 'log_system_error'], 10, 3);
        add_action('intersoccer_database_migration', [$this, 'log_database_migration'], 10, 2);
    }

    /**
     * Log user registration
     */
    public function log_user_registration($user_id) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $this->log_event('user_registration', [
            'user_id' => $user_id,
            'username' => $user->user_login,
            'email' => $user->user_email,
            'role' => implode(', ', $user->roles),
            'registration_method' => 'wordpress_standard'
        ], 'user', $user_id);
    }

    /**
     * Log role changes
     */
    public function log_role_change($user_id, $role, $old_roles) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $this->log_event('role_change', [
            'user_id' => $user_id,
            'username' => $user->user_login,
            'old_roles' => implode(', ', $old_roles),
            'new_role' => $role,
            'changed_by' => get_current_user_id()
        ], 'user', $user_id);
    }

    /**
     * Log profile updates
     */
    public function log_profile_update($user_id, $old_user_data) {
        $user = get_userdata($user_id);
        if (!$user) return;

        $changes = [];
        if ($old_user_data->user_email !== $user->user_email) {
            $changes['email_changed'] = [
                'old' => $old_user_data->user_email,
                'new' => $user->user_email
            ];
        }

        if (!empty($changes)) {
            $this->log_event('profile_update', [
                'user_id' => $user_id,
                'username' => $user->user_login,
                'changes' => $changes,
                'updated_by' => get_current_user_id()
            ], 'user', $user_id);
        }
    }

    /**
     * Log coach import success
     */
    public function log_coach_import($coach_id, $import_data) {
        $this->log_event('coach_import_success', [
            'coach_id' => $coach_id,
            'import_data' => $import_data,
            'imported_by' => get_current_user_id(),
            'referral_code_generated' => get_user_meta($coach_id, 'intersoccer_referral_code', true)
        ], 'admin', get_current_user_id());
    }

    /**
     * Log coach import failure
     */
    public function log_coach_import_failure($error_data, $raw_data) {
        $this->log_event('coach_import_failure', [
            'error' => $error_data,
            'raw_data' => $raw_data,
            'imported_by' => get_current_user_id()
        ], 'admin', get_current_user_id());
    }

    /**
     * Log referral code usage
     */
    public function log_referral_code_usage($referral_code, $customer_id, $coach_id) {
        $this->log_event('referral_code_used', [
            'referral_code' => $referral_code,
            'customer_id' => $customer_id,
            'coach_id' => $coach_id,
            'customer_ip' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ], 'referral', $customer_id);
    }

    /**
     * Log referral code failure
     */
    public function log_referral_code_failure($referral_code, $reason) {
        $this->log_event('referral_code_invalid', [
            'referral_code' => $referral_code,
            'reason' => $reason,
            'customer_ip' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'attempted_by' => get_current_user_id()
        ], 'security', get_current_user_id());
    }

    /**
     * Log referral creation
     */
    public function log_referral_creation($referral_id, $referral_data) {
        $this->log_event('referral_created', [
            'referral_id' => $referral_id,
            'coach_id' => $referral_data['coach_id'],
            'customer_id' => $referral_data['customer_id'],
            'referral_code' => $referral_data['referral_code'] ?? '',
            'order_id' => $referral_data['order_id'] ?? null
        ], 'referral', $referral_data['customer_id']);
    }

    /**
     * Log points earned
     */
    public function log_points_earned($user_id, $points, $reason, $order_id = null) {
        $this->log_event('points_earned', [
            'user_id' => $user_id,
            'points' => $points,
            'reason' => $reason,
            'order_id' => $order_id,
            'balance_before' => $this->get_points_balance_before($user_id),
            'balance_after' => get_user_meta($user_id, 'intersoccer_points_balance', true)
        ], 'points', $user_id);
    }

    /**
     * Log points redeemed
     */
    public function log_points_redeemed($user_id, $points, $discount_amount, $order_id) {
        $this->log_event('points_redeemed', [
            'user_id' => $user_id,
            'points' => $points,
            'discount_amount' => $discount_amount,
            'order_id' => $order_id,
            'balance_before' => $this->get_points_balance_before($user_id),
            'balance_after' => get_user_meta($user_id, 'intersoccer_points_balance', true)
        ], 'points', $user_id);
    }

    /**
     * Log points adjustment (admin action)
     */
    public function log_points_adjustment($user_id, $adjustment, $reason, $admin_id) {
        $this->log_event('points_adjusted', [
            'user_id' => $user_id,
            'adjustment' => $adjustment,
            'reason' => $reason,
            'admin_id' => $admin_id,
            'balance_before' => $this->get_points_balance_before($user_id),
            'balance_after' => get_user_meta($user_id, 'intersoccer_points_balance', true)
        ], 'admin', $admin_id);
    }

    /**
     * Log commission payment
     */
    public function log_commission_payment($coach_id, $amount, $order_id) {
        $this->log_event('commission_paid', [
            'coach_id' => $coach_id,
            'amount' => $amount,
            'order_id' => $order_id,
            'credits_before' => $this->get_credits_balance_before($coach_id),
            'credits_after' => get_user_meta($coach_id, 'intersoccer_credits', true)
        ], 'commission', $coach_id);
    }

    /**
     * Log commission calculation
     */
    public function log_commission_calculation($coach_id, $order_id, $commission_data) {
        $this->log_event('commission_calculated', [
            'coach_id' => $coach_id,
            'order_id' => $order_id,
            'commission_data' => $commission_data
        ], 'commission', $coach_id);
    }

    /**
     * Log suspicious activity
     */
    public function log_suspicious_activity($activity_type, $details, $user_id = null) {
        $this->log_event('suspicious_activity', [
            'activity_type' => $activity_type,
            'details' => $details,
            'user_id' => $user_id,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ], 'security', $user_id);
    }

    /**
     * Log rate limit exceeded
     */
    public function log_rate_limit_exceeded($action, $user_id) {
        $this->log_event('rate_limit_exceeded', [
            'action' => $action,
            'user_id' => $user_id,
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ], 'security', $user_id);
    }

    /**
     * Log admin settings change
     */
    public function log_admin_settings_change($setting_key, $old_value, $new_value) {
        $this->log_event('admin_settings_changed', [
            'setting_key' => $setting_key,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'changed_by' => get_current_user_id()
        ], 'admin', get_current_user_id());
    }

    /**
     * Log admin user action
     */
    public function log_admin_user_action($action, $target_user_id, $details, $admin_id) {
        $this->log_event('admin_user_action', [
            'action' => $action,
            'target_user_id' => $target_user_id,
            'details' => $details,
            'admin_id' => $admin_id
        ], 'admin', $admin_id);
    }

    /**
     * Log system error
     */
    public function log_system_error($error_type, $error_message, $context = []) {
        $this->log_event('system_error', [
            'error_type' => $error_type,
            'error_message' => $error_message,
            'context' => $context,
            'stack_trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ], 'system', null);
    }

    /**
     * Log database migration
     */
    public function log_database_migration($migration_name, $details) {
        $this->log_event('database_migration', [
            'migration_name' => $migration_name,
            'details' => $details,
            'executed_by' => get_current_user_id()
        ], 'system', get_current_user_id());
    }

    /**
     * Core logging method
     */
    private function log_event($event_type, $data, $category = 'general', $user_id = null) {
        global $wpdb;

        // Ensure table exists
        $this->ensure_table_exists();

        // Prepare log entry
        $log_entry = [
            'event_type' => $event_type,
            'category' => $category,
            'user_id' => $user_id,
            'data' => wp_json_encode($data),
            'ip_address' => $this->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'session_id' => session_id(),
            'created_at' => current_time('mysql'),
            'created_at_gmt' => current_time('mysql', true)
        ];

        // Insert log entry
        $result = $wpdb->insert($this->table_name, $log_entry);

        if ($result === false) {
            error_log('Failed to insert audit log entry: ' . $wpdb->last_error);
        }

        // Clean up old logs if needed
        $this->cleanup_old_logs();
    }

    /**
     * Ensure audit log table exists
     */
    private function ensure_table_exists() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_type varchar(100) NOT NULL,
            category varchar(50) NOT NULL DEFAULT 'general',
            user_id bigint(20) unsigned NULL,
            data longtext NOT NULL,
            ip_address varchar(45) DEFAULT '',
            user_agent text DEFAULT '',
            session_id varchar(255) DEFAULT '',
            created_at datetime NOT NULL,
            created_at_gmt datetime NOT NULL,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY category (category),
            KEY user_id (user_id),
            KEY created_at (created_at),
            KEY ip_address (ip_address)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Clean up old audit logs (keep last 6 months)
     */
    private function cleanup_old_logs() {
        global $wpdb;

        // Only run cleanup occasionally (1% chance)
        if (rand(1, 100) !== 1) {
            return;
        }

        $six_months_ago = date('Y-m-d H:i:s', strtotime('-6 months'));

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE created_at < %s",
            $six_months_ago
        ));
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];

                // Handle comma-separated IPs (like X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }

                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get points balance before transaction (for logging)
     */
    private function get_points_balance_before($user_id) {
        static $balances = [];

        if (!isset($balances[$user_id])) {
            $balances[$user_id] = get_user_meta($user_id, 'intersoccer_points_balance', true) ?: 0;
        }

        return $balances[$user_id];
    }

    /**
     * Get credits balance before transaction (for logging)
     */
    private function get_credits_balance_before($coach_id) {
        static $balances = [];

        if (!isset($balances[$coach_id])) {
            $balances[$coach_id] = get_user_meta($coach_id, 'intersoccer_credits', true) ?: 0;
        }

        return $balances[$coach_id];
    }

    /**
     * Query audit logs with filters
     */
    public static function get_logs($args = []) {
        global $wpdb;
        $instance = self::get_instance();

        $defaults = [
            'event_type' => '',
            'category' => '',
            'user_id' => '',
            'ip_address' => '',
            'date_from' => '',
            'date_to' => '',
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);

        $where = [];
        $params = [];

        if (!empty($args['event_type'])) {
            $where[] = 'event_type = %s';
            $params[] = $args['event_type'];
        }

        if (!empty($args['category'])) {
            $where[] = 'category = %s';
            $params[] = $args['category'];
        }

        if (!empty($args['user_id'])) {
            $where[] = 'user_id = %d';
            $params[] = $args['user_id'];
        }

        if (!empty($args['ip_address'])) {
            $where[] = 'ip_address = %s';
            $params[] = $args['ip_address'];
        }

        if (!empty($args['date_from'])) {
            $where[] = 'created_at >= %s';
            $params[] = $args['date_from'];
        }

        if (!empty($args['date_to'])) {
            $where[] = 'created_at <= %s';
            $params[] = $args['date_to'];
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $order_clause = sprintf('ORDER BY %s %s', $args['orderby'], $args['order']);
        $limit_clause = $wpdb->prepare('LIMIT %d OFFSET %d', $args['limit'], $args['offset']);

        $query = "SELECT * FROM {$instance->table_name} {$where_clause} {$order_clause} {$limit_clause}";

        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        return $wpdb->get_results($query);
    }

    /**
     * Get audit log statistics
     */
    public static function get_stats($period = '30 days') {
        global $wpdb;
        $instance = self::get_instance();

        $date_clause = '';
        switch ($period) {
            case '7 days':
                $date_clause = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
                break;
            case '30 days':
                $date_clause = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
                break;
            case '90 days':
                $date_clause = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)';
                break;
            case '1 year':
                $date_clause = 'AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)';
                break;
        }

        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT
                COUNT(*) as total_events,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT ip_address) as unique_ips,
                COUNT(CASE WHEN category = 'security' THEN 1 END) as security_events,
                COUNT(CASE WHEN category = 'admin' THEN 1 END) as admin_events,
                COUNT(CASE WHEN category = 'system' THEN 1 END) as system_events,
                COUNT(CASE WHEN event_type = 'suspicious_activity' THEN 1 END) as suspicious_activities,
                COUNT(CASE WHEN event_type = 'rate_limit_exceeded' THEN 1 END) as rate_limit_events
            FROM {$instance->table_name}
            WHERE 1=1 {$date_clause}
        "));

        return $stats;
    }

    /**
     * Export audit logs to CSV
     */
    public static function export_logs($args = []) {
        $logs = self::get_logs($args);

        if (empty($logs)) {
            return '';
        }

        $csv = "ID,Event Type,Category,User ID,Data,IP Address,User Agent,Session ID,Created At\n";

        foreach ($logs as $log) {
            $data = str_replace('"', '""', wp_json_encode($log->data)); // Escape quotes for CSV
            $user_agent = str_replace('"', '""', $log->user_agent);

            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                $log->id,
                $log->event_type,
                $log->category,
                $log->user_id,
                $data,
                $log->ip_address,
                $user_agent,
                $log->session_id,
                $log->created_at
            );
        }

        return $csv;
    }
}