<?php
// includes/class-admin-audit.php

/**
 * InterSoccer Admin Audit Logs
 *
 * Admin interface for viewing and managing audit logs.
 */
class InterSoccer_Admin_Audit {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_audit_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Add audit logs menu to admin
     */
    public function add_audit_menu() {
        add_submenu_page(
            'intersoccer-admin',
            __('Audit Logs', 'intersoccer-referral'),
            __('Audit Logs', 'intersoccer-referral'),
            'manage_options',
            'intersoccer-audit',
            [$this, 'audit_logs_page']
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'intersoccer_page_intersoccer-audit') {
            return;
        }

        wp_enqueue_style('intersoccer-audit', INTERSOCCER_REFERRAL_URL . 'assets/css/admin-audit.css', [], INTERSOCCER_REFERRAL_VERSION);
        wp_enqueue_script('intersoccer-audit', INTERSOCCER_REFERRAL_URL . 'assets/js/admin-audit.js', ['jquery'], INTERSOCCER_REFERRAL_VERSION, true);

        wp_localize_script('intersoccer-audit', 'intersoccer_audit', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('intersoccer_audit_nonce'),
            'strings' => [
                'confirm_delete' => __('Are you sure you want to delete these audit logs?', 'intersoccer-referral'),
                'export_success' => __('Audit logs exported successfully.', 'intersoccer-referral'),
                'delete_success' => __('Audit logs deleted successfully.', 'intersoccer-referral'),
                'no_logs_selected' => __('Please select logs to delete.', 'intersoccer-referral')
            ]
        ]);
    }

    /**
     * Main audit logs page
     */
    public function audit_logs_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Handle export request
        if (isset($_POST['export_logs']) && check_admin_referer('intersoccer_export_audit_logs')) {
            $this->export_audit_logs();
            return;
        }

        // Handle bulk delete request
        if (isset($_POST['bulk_delete']) && check_admin_referer('intersoccer_bulk_delete_audit_logs')) {
            $this->bulk_delete_logs();
        }

        // Get filter parameters
        $filters = [
            'event_type' => isset($_GET['event_type']) ? sanitize_text_field($_GET['event_type']) : '',
            'category' => isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '',
            'user_id' => isset($_GET['user_id']) ? intval($_GET['user_id']) : '',
            'ip_address' => isset($_GET['ip_address']) ? sanitize_text_field($_GET['ip_address']) : '',
            'date_from' => isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '',
            'date_to' => isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '',
            'limit' => isset($_GET['limit']) ? intval($_GET['limit']) : 50,
            'paged' => isset($_GET['paged']) ? intval($_GET['paged']) : 1
        ];

        $filters['offset'] = ($filters['paged'] - 1) * $filters['limit'];

        // Get audit logs
        $logs = InterSoccer_Audit_Logger::get_logs($filters);
        $stats = InterSoccer_Audit_Logger::get_stats('30 days');

        // Get unique event types and categories for filters
        $event_types = $this->get_unique_values('event_type');
        $categories = $this->get_unique_values('category');

        ?>
        <div class="wrap">
            <h1><?php _e('Audit Logs', 'intersoccer-referral'); ?></h1>

            <!-- Statistics Cards -->
            <div class="audit-stats-grid">
                <div class="audit-stat-card">
                    <h3><?php _e('Total Events (30 days)', 'intersoccer-referral'); ?></h3>
                    <span class="stat-number"><?php echo number_format($stats->total_events); ?></span>
                </div>
                <div class="audit-stat-card">
                    <h3><?php _e('Unique Users', 'intersoccer-referral'); ?></h3>
                    <span class="stat-number"><?php echo number_format($stats->unique_users); ?></span>
                </div>
                <div class="audit-stat-card">
                    <h3><?php _e('Security Events', 'intersoccer-referral'); ?></h3>
                    <span class="stat-number"><?php echo number_format($stats->security_events); ?></span>
                </div>
                <div class="audit-stat-card">
                    <h3><?php _e('Suspicious Activities', 'intersoccer-referral'); ?></h3>
                    <span class="stat-number"><?php echo number_format($stats->suspicious_activities); ?></span>
                </div>
            </div>

            <!-- Filters -->
            <div class="audit-filters">
                <form method="GET" action="">
                    <input type="hidden" name="page" value="intersoccer-audit">

                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="event_type"><?php _e('Event Type:', 'intersoccer-referral'); ?></label>
                            <select name="event_type" id="event_type">
                                <option value=""><?php _e('All Events', 'intersoccer-referral'); ?></option>
                                <?php foreach ($event_types as $type): ?>
                                    <option value="<?php echo esc_attr($type); ?>" <?php selected($filters['event_type'], $type); ?>>
                                        <?php echo esc_html($type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="category"><?php _e('Category:', 'intersoccer-referral'); ?></label>
                            <select name="category" id="category">
                                <option value=""><?php _e('All Categories', 'intersoccer-referral'); ?></option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo esc_attr($cat); ?>" <?php selected($filters['category'], $cat); ?>>
                                        <?php echo esc_html($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="user_id"><?php _e('User ID:', 'intersoccer-referral'); ?></label>
                            <input type="number" name="user_id" id="user_id" value="<?php echo esc_attr($filters['user_id']); ?>">
                        </div>

                        <div class="filter-group">
                            <label for="ip_address"><?php _e('IP Address:', 'intersoccer-referral'); ?></label>
                            <input type="text" name="ip_address" id="ip_address" value="<?php echo esc_attr($filters['ip_address']); ?>">
                        </div>
                    </div>

                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="date_from"><?php _e('From Date:', 'intersoccer-referral'); ?></label>
                            <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($filters['date_from']); ?>">
                        </div>

                        <div class="filter-group">
                            <label for="date_to"><?php _e('To Date:', 'intersoccer-referral'); ?></label>
                            <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($filters['date_to']); ?>">
                        </div>

                        <div class="filter-group">
                            <label for="limit"><?php _e('Per Page:', 'intersoccer-referral'); ?></label>
                            <select name="limit" id="limit">
                                <option value="25" <?php selected($filters['limit'], 25); ?>>25</option>
                                <option value="50" <?php selected($filters['limit'], 50); ?>>50</option>
                                <option value="100" <?php selected($filters['limit'], 100); ?>>100</option>
                                <option value="200" <?php selected($filters['limit'], 200); ?>>200</option>
                            </select>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="button"><?php _e('Filter', 'intersoccer-referral'); ?></button>
                            <a href="<?php echo admin_url('admin.php?page=intersoccer-audit'); ?>" class="button"><?php _e('Clear Filters', 'intersoccer-referral'); ?></a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Bulk Actions and Export -->
            <div class="audit-actions">
                <form method="POST" action="" style="display: inline;">
                    <?php wp_nonce_field('intersoccer_export_audit_logs'); ?>
                    <button type="submit" name="export_logs" class="button button-primary">
                        <?php _e('Export to CSV', 'intersoccer-referral'); ?>
                    </button>
                </form>

                <form method="POST" action="" id="bulk-delete-form" style="display: inline;">
                    <?php wp_nonce_field('intersoccer_bulk_delete_audit_logs'); ?>
                    <button type="submit" name="bulk_delete" class="button button-secondary" disabled>
                        <?php _e('Delete Selected', 'intersoccer-referral'); ?>
                    </button>
                </form>
            </div>

            <!-- Audit Logs Table -->
            <form method="POST" action="" id="audit-logs-form">
                <?php wp_nonce_field('intersoccer_bulk_delete_audit_logs'); ?>

                <table class="wp-list-table widefat fixed striped audit-logs-table">
                    <thead>
                        <tr>
                            <th class="check-column">
                                <input type="checkbox" id="select-all-logs">
                            </th>
                            <th><?php _e('Date/Time', 'intersoccer-referral'); ?></th>
                            <th><?php _e('Event Type', 'intersoccer-referral'); ?></th>
                            <th><?php _e('Category', 'intersoccer-referral'); ?></th>
                            <th><?php _e('User', 'intersoccer-referral'); ?></th>
                            <th><?php _e('IP Address', 'intersoccer-referral'); ?></th>
                            <th><?php _e('Details', 'intersoccer-referral'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7"><?php _e('No audit logs found.', 'intersoccer-referral'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <th class="check-column">
                                        <input type="checkbox" name="log_ids[]" value="<?php echo esc_attr($log->id); ?>" class="log-checkbox">
                                    </th>
                                    <td><?php echo esc_html(get_date_from_gmt($log->created_at, 'Y-m-d H:i:s')); ?></td>
                                    <td><?php echo esc_html($log->event_type); ?></td>
                                    <td><?php echo esc_html($log->category); ?></td>
                                    <td>
                                        <?php
                                        if ($log->user_id) {
                                            $user = get_userdata($log->user_id);
                                            if ($user) {
                                                echo esc_html($user->display_name) . ' (' . $log->user_id . ')';
                                            } else {
                                                echo esc_html($log->user_id);
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo esc_html($log->ip_address); ?></td>
                                    <td>
                                        <button type="button" class="button button-small audit-log-details" data-log-id="<?php echo esc_attr($log->id); ?>">
                                            <?php _e('View Details', 'intersoccer-referral'); ?>
                                        </button>
                                        <div id="log-details-<?php echo esc_attr($log->id); ?>" class="log-details" style="display: none;">
                                            <pre><?php echo esc_html(wp_json_encode(json_decode($log->data), JSON_PRETTY_PRINT)); ?></pre>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>

            <!-- Pagination -->
            <?php if (!empty($logs)): ?>
                <div class="audit-pagination">
                    <?php
                    $total_logs = $this->get_total_log_count($filters);
                    $total_pages = ceil($total_logs / $filters['limit']);

                    if ($total_pages > 1) {
                        $base_url = add_query_arg(array_diff_key($_GET, ['paged' => '']));

                        echo paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo; Previous'),
                            'next_text' => __('Next &raquo;'),
                            'total' => $total_pages,
                            'current' => $filters['paged'],
                            'add_args' => array_diff_key($_GET, ['paged' => ''])
                        ]);
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>

        <style>
            .audit-stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }

            .audit-stat-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                text-align: center;
            }

            .audit-stat-card h3 {
                margin: 0 0 10px 0;
                color: #23282d;
                font-size: 14px;
                font-weight: 600;
            }

            .stat-number {
                font-size: 24px;
                font-weight: bold;
                color: #007cba;
            }

            .audit-filters {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                margin: 20px 0;
            }

            .filter-row {
                display: flex;
                gap: 20px;
                margin-bottom: 15px;
                flex-wrap: wrap;
            }

            .filter-group {
                display: flex;
                flex-direction: column;
                min-width: 150px;
            }

            .filter-group label {
                font-weight: 600;
                margin-bottom: 5px;
            }

            .filter-actions {
                display: flex;
                gap: 10px;
                align-items: end;
            }

            .audit-actions {
                margin: 20px 0;
            }

            .audit-logs-table {
                margin-top: 20px;
            }

            .log-details {
                margin-top: 10px;
                padding: 10px;
                background: #f8f9fa;
                border: 1px solid #dee2e6;
                border-radius: 4px;
            }

            .log-details pre {
                margin: 0;
                white-space: pre-wrap;
                word-wrap: break-word;
                font-size: 12px;
            }

            .audit-pagination {
                margin-top: 20px;
                text-align: center;
            }
        </style>

        <script>
            jQuery(document).ready(function($) {
                // Toggle log details
                $('.audit-log-details').on('click', function() {
                    var logId = $(this).data('log-id');
                    $('#log-details-' + logId).toggle();
                });

                // Select all checkbox
                $('#select-all-logs').on('change', function() {
                    $('.log-checkbox').prop('checked', $(this).prop('checked'));
                    updateBulkDeleteButton();
                });

                // Individual checkboxes
                $(document).on('change', '.log-checkbox', function() {
                    updateBulkDeleteButton();
                });

                function updateBulkDeleteButton() {
                    var checkedCount = $('.log-checkbox:checked').length;
                    $('button[name="bulk_delete"]').prop('disabled', checkedCount === 0);
                }
            });
        </script>
        <?php
    }

    /**
     * Export audit logs to CSV
     */
    private function export_audit_logs() {
        $filters = [
            'event_type' => isset($_POST['event_type']) ? sanitize_text_field($_POST['event_type']) : '',
            'category' => isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '',
            'user_id' => isset($_POST['user_id']) ? intval($_POST['user_id']) : '',
            'date_from' => isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '',
            'date_to' => isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '',
            'limit' => 10000 // Export up to 10k records
        ];

        $csv_content = InterSoccer_Audit_Logger::export_logs($filters);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=intersoccer-audit-logs-' . date('Y-m-d-H-i-s') . '.csv');
        header('Content-Length: ' . strlen($csv_content));

        echo $csv_content;
        exit;
    }

    /**
     * Bulk delete audit logs
     */
    private function bulk_delete_logs() {
        if (!isset($_POST['log_ids']) || !is_array($_POST['log_ids'])) {
            wp_die(__('No logs selected for deletion.'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_audit_log';
        $log_ids = array_map('intval', $_POST['log_ids']);

        $placeholders = str_repeat('%d,', count($log_ids) - 1) . '%d';
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table_name} WHERE id IN ({$placeholders})",
            $log_ids
        ));

        add_settings_error(
            'intersoccer_audit',
            'bulk_delete_success',
            sprintf(__('Successfully deleted %d audit log(s).', 'intersoccer-referral'), count($log_ids)),
            'success'
        );
    }

    /**
     * Get unique values for filter dropdowns
     */
    private function get_unique_values($column) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_audit_log';

        $results = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT {$column} FROM {$table_name} WHERE {$column} != '' ORDER BY {$column} LIMIT 100"
        ));

        return $results ?: [];
    }

    /**
     * Get total count of logs matching filters
     */
    private function get_total_log_count($filters) {
        global $wpdb;
        $instance = InterSoccer_Audit_Logger::get_instance();
        $table_name = $instance->table_name;

        $where = [];
        $params = [];

        if (!empty($filters['event_type'])) {
            $where[] = 'event_type = %s';
            $params[] = $filters['event_type'];
        }

        if (!empty($filters['category'])) {
            $where[] = 'category = %s';
            $params[] = $filters['category'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = %d';
            $params[] = $filters['user_id'];
        }

        if (!empty($filters['ip_address'])) {
            $where[] = 'ip_address = %s';
            $params[] = $filters['ip_address'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= %s';
            $params[] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= %s';
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $query = "SELECT COUNT(*) FROM {$table_name} {$where_clause}";

        if (!empty($params)) {
            $query = $wpdb->prepare($query, $params);
        }

        return $wpdb->get_var($query);
    }
}