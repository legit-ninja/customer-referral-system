<?php
// includes/class-admin-points.php

class InterSoccer_Admin_Points {

    /**
     * Print Adjust/History modals in admin footer on order edit.
     *
     * @var bool
     */
    private $print_order_loyalty_modals = false;

    public function __construct() {
        add_action('wp_ajax_get_points_users', [$this, 'get_points_users_ajax']);
        add_action('wp_ajax_adjust_user_points', [$this, 'adjust_user_points_ajax']);
        add_action('wp_ajax_export_points_report', [$this, 'export_points_report_ajax']);
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'render_order_loyalty_panel'], 10, 1);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_order_loyalty_assets']);
        add_action('admin_footer', [$this, 'render_order_loyalty_modals']);
    }

    /**
     * Whether the current user may open Customer Points (Adjust / History).
     */
    public static function user_can_manage_points() {
        return current_user_can('manage_options') || current_user_can('manage_woocommerce');
    }

    /**
     * Deep-link to Customer Points for a user.
     *
     * @param int    $user_id
     * @param string $focus adjust|history|empty
     * @return string
     */
    public static function get_customer_points_url($user_id, $focus = '') {
        $args = [
            'page' => 'intersoccer-customer-points',
            'user_id' => absint($user_id),
        ];
        $focus = sanitize_key((string) $focus);
        if (in_array($focus, ['adjust', 'history'], true)) {
            $args['focus'] = $focus;
        }

        return admin_url('admin.php?' . http_build_query($args));
    }

    /**
     * Focus payload from GET user_id / focus (Customer Points page).
     *
     * @return array<string, mixed>|null
     */
    public static function get_request_focus_data() {
        $user_id = isset($_GET['user_id']) ? absint($_GET['user_id']) : 0;
        if ($user_id <= 0) {
            return null;
        }

        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return null;
        }

        $focus = isset($_GET['focus']) ? sanitize_key((string) $_GET['focus']) : '';
        if (!in_array($focus, ['adjust', 'history'], true)) {
            $focus = '';
        }

        $balance = 0;
        if (class_exists('InterSoccer_Points_Manager')) {
            $balance = InterSoccer_Points_Manager::get_instance()->get_points_balance($user_id);
        }

        return [
            'id' => $user_id,
            'name' => (string) $user->display_name,
            'email' => (string) $user->user_email,
            'balance' => (int) $balance,
            'focus' => $focus,
        ];
    }

    /**
     * Localize payload for admin-points.js deep-link.
     *
     * @return array{focus_user: array<string, mixed>|null, focus_action: string}
     */
    public function get_script_focus_payload() {
        $data = self::get_request_focus_data();
        if (!$data) {
            return [
                'focus_user' => null,
                'focus_action' => '',
            ];
        }

        return [
            'focus_user' => [
                'id' => $data['id'],
                'name' => $data['name'],
                'email' => $data['email'],
                'balance' => $data['balance'],
            ],
            'focus_action' => $data['focus'],
        ];
    }

    /**
     * Order-edit panel HTML (no echo).
     *
     * @param mixed $order
     * @return string
     */
    public function get_order_loyalty_panel_html($order) {
        if (!is_object($order) || !method_exists($order, 'get_customer_id')) {
            return '';
        }

        $customer_id = (int) $order->get_customer_id();
        $can_manage = self::user_can_manage_points();

        if ($customer_id <= 0) {
            return '<div class="intersoccer-order-loyalty-points"><p>'
                . esc_html(__('This order has no linked customer account, so there is no loyalty points balance.', 'intersoccer-referral'))
                . '</p></div>';
        }

        $balance = 0;
        if (class_exists('InterSoccer_Points_Manager')) {
            $balance = (int) InterSoccer_Points_Manager::get_instance()->get_points_balance($customer_id);
        }

        $html = '<div class="intersoccer-order-loyalty-points">';
        $html .= '<p><strong>' . esc_html(__('Loyalty points', 'intersoccer-referral')) . ':</strong> '
            . esc_html((string) $balance) . '</p>';

        $redeemed = 0;
        if (method_exists($order, 'get_meta')) {
            $redeemed = (int) $order->get_meta('_intersoccer_points_redeemed', true);
        }
        if ($redeemed > 0) {
            $html .= '<p>' . esc_html(sprintf(
                /* translators: %d: points redeemed on this order */
                __('Redeemed on this order: %d', 'intersoccer-referral'),
                $redeemed
            )) . '</p>';
        }

        if ($can_manage) {
            $user = function_exists('get_user_by') ? get_user_by('ID', $customer_id) : false;
            $name = ($user && isset($user->display_name)) ? (string) $user->display_name : '';
            $html .= '<p class="intersoccer-order-loyalty-points__actions">';
            $html .= '<button type="button" class="button adjust-points" data-user-id="'
                . esc_attr((string) $customer_id) . '" data-user-name="' . esc_attr($name) . '">'
                . esc_html(__('Adjust points', 'intersoccer-referral')) . '</button> ';
            $html .= '<button type="button" class="button view-history" data-user-id="'
                . esc_attr((string) $customer_id) . '" data-user-name="' . esc_attr($name) . '">'
                . esc_html(__('History', 'intersoccer-referral')) . '</button>';
            $html .= '</p>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Print loyalty panel on WooCommerce order edit (classic + HPOS).
     *
     * @param mixed $order
     */
    public function render_order_loyalty_panel($order) {
        echo $this->get_order_loyalty_panel_html($order);
    }

    /**
     * Load panel styles on order edit (admin-dashboard enqueue is gated on "intersoccer" hooks).
     *
     * @param string $hook
     */
    public function enqueue_order_loyalty_assets($hook) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $id = ($screen && isset($screen->id)) ? (string) $screen->id : (string) $hook;
        if (strpos($id, 'shop_order') === false && strpos($id, 'shop-order') === false && strpos($id, 'wc-orders') === false) {
            return;
        }
        if (!defined('INTERSOCCER_REFERRAL_URL')) {
            return;
        }
        $version = defined('INTERSOCCER_REFERRAL_VERSION') ? INTERSOCCER_REFERRAL_VERSION : '1.0';
        wp_enqueue_style(
            'intersoccer-order-loyalty-css',
            INTERSOCCER_REFERRAL_URL . 'assets/css/admin-points.css',
            [],
            $version
        );
        wp_enqueue_script(
            'intersoccer-admin-points-js',
            INTERSOCCER_REFERRAL_URL . 'assets/js/admin-points.js',
            ['jquery'],
            $version,
            true
        );
        wp_localize_script('intersoccer-admin-points-js', 'intersoccer_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('intersoccer_admin_nonce'),
        ]);
        if (self::user_can_manage_points()) {
            $this->print_order_loyalty_modals = true;
        }
    }

    /**
     * Existing Adjust / History dialogs (same IDs as Customer Points).
     *
     * @return string
     */
    public function get_points_modals_html() {
        ob_start();
        ?>
            <!-- Points Adjustment Modal -->
            <div id="points-adjustment-modal" class="intersoccer-modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Adjust Customer Points</h2>
                        <button class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div id="customer-info"></div>
                        <form id="points-adjustment-form">
                            <div class="form-row">
                                <label for="adjustment-type">Adjustment Type:</label>
                                <select id="adjustment-type" name="adjustment_type">
                                    <option value="add">Add Points</option>
                                    <option value="subtract">Subtract Points</option>
                                    <option value="set">Set Points Balance</option>
                                </select>
                            </div>
                            <div class="form-row">
                                <label for="points-amount">Points Amount:</label>
                                <input type="number" id="points-amount" name="points_amount" step="1" min="0" required>
                                <small style="color: #666; font-style: italic;">Integer values only (no decimals)</small>
                            </div>
                            <div class="form-row">
                                <label for="adjustment-reason">Reason:</label>
                                <textarea id="adjustment-reason" name="reason" rows="3" placeholder="Enter reason for adjustment..." required></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="button button-primary">Apply Adjustment</button>
                                <button type="button" class="button modal-close">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Points History Modal (read-only journal) -->
            <div id="points-history-modal" class="intersoccer-modal points-history-modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Points History</h2>
                        <button type="button" class="modal-close">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div id="points-history-customer"></div>
                        <div id="points-history-banner" class="points-history-banner" style="display: none;"></div>
                        <div id="points-history-table-wrap">
                            <p class="points-history-loading">Loading history...</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Print Adjust/History modals on order edit (outside overflow-clipped metaboxes).
     */
    public function render_order_loyalty_modals() {
        if (empty($this->print_order_loyalty_modals)) {
            return;
        }
        echo $this->get_points_modals_html();
    }

    /**
     * Banner HTML when arriving from an order deep-link.
     *
     * @param array<string, mixed> $data
     * @return string
     */
    public static function get_focus_banner_html(array $data) {
        $user_id = isset($data['id']) ? absint($data['id']) : 0;
        $name = isset($data['name']) ? (string) $data['name'] : '';
        $email = isset($data['email']) ? (string) $data['email'] : '';
        $balance = isset($data['balance']) ? (int) $data['balance'] : 0;

        $html = '<div class="intersoccer-points-focus-banner">';
        $html .= '<p><strong>' . esc_html(__('Viewing', 'intersoccer-referral')) . ':</strong> '
            . esc_html($name) . ' (' . esc_html($email) . ')</p>';
        $html .= '<p><strong>' . esc_html(__('Current points', 'intersoccer-referral')) . ':</strong> '
            . esc_html((string) $balance) . '</p>';
        $html .= '<p>';
        $html .= '<button type="button" class="button button-primary adjust-points" data-user-id="'
            . esc_attr((string) $user_id) . '" data-user-name="' . esc_attr($name) . '">'
            . esc_html(__('Adjust points', 'intersoccer-referral')) . '</button> ';
        $html .= '<button type="button" class="button view-history" data-user-id="'
            . esc_attr((string) $user_id) . '" data-user-name="' . esc_attr($name) . '">'
            . esc_html(__('History', 'intersoccer-referral')) . '</button>';
        $html .= '</p></div>';

        return $html;
    }

    public function render_points_page() {
        if (!self::user_can_manage_points()) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'intersoccer-referral'));
        }

        $focus_data = self::get_request_focus_data();
        ?>
        <div class="wrap intersoccer-admin">
            <h1 class="wp-heading-inline"><?php echo esc_html(__('Customer Points Management', 'intersoccer-referral')); ?></h1>
            <?php
            if ($focus_data) {
                echo self::get_focus_banner_html($focus_data);
            }
            ?>

            <div class="intersoccer-points-controls">
                <button class="button button-primary" id="refresh-points-table">
                    <span class="dashicons dashicons-update"></span>
                    Refresh
                </button>
                <button class="button button-secondary" id="export-points-report">
                    <span class="dashicons dashicons-download"></span>
                    Export Report
                </button>
                <div class="points-summary">
                    <span id="total-customers">Loading...</span> customers with points
                </div>
            </div>

            <div class="intersoccer-points-filters">
                <select id="points-filter">
                    <option value="all">All Customers</option>
                    <option value="with-points">With Points Only</option>
                    <option value="zero-points">Zero Points</option>
                </select>
                <input type="text" id="points-search" placeholder="Search by name or email..." style="min-width: 250px;">
                <button class="button" id="clear-filters">Clear Filters</button>
            </div>

            <div class="intersoccer-points-table-container">
                <table class="wp-list-table widefat fixed striped" id="points-users-table">
                    <thead>
                        <tr>
                            <th class="column-user">Customer</th>
                            <th class="column-email">Email</th>
                            <th class="column-points">Current Points</th>
                            <th class="column-total-earned">Total Earned</th>
                            <th class="column-total-spent">Total Spent</th>
                            <th class="column-last-activity">Last Activity</th>
                            <th class="column-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="points-table-body">
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">
                                <div class="spinner is-active" style="float: none; margin: 0 auto;"></div>
                                <p>Loading customer points data...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php echo $this->get_points_modals_html(); ?>
        </div>

        <?php
    }

    /**
     * Get points users via AJAX
     */
    public function get_points_users_ajax() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!self::user_can_manage_points()) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        global $wpdb;

        $filter = sanitize_text_field($_POST['filter'] ?? 'all');
        $search = sanitize_text_field($_POST['search'] ?? '');
        $page = intval($_POST['page'] ?? 1);
        $per_page = 50;
        $offset = ($page - 1) * $per_page;

        // Build WHERE clause
        $where = "WHERE 1=1";

        if ($filter === 'with-points') {
            $where .= " AND COALESCE(um.meta_value, 0) > 0";
        } elseif ($filter === 'zero-points') {
            $where .= " AND (um.meta_value IS NULL OR um.meta_value = 0)";
        }

        if (!empty($search)) {
            $where .= $wpdb->prepare(" AND (u.display_name LIKE %s OR u.user_email LIKE %s)",
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }

        // Get total count
        $total_query = "
            SELECT COUNT(DISTINCT u.ID)
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'intersoccer_points_balance'
            {$where}
        ";
        $total = $wpdb->get_var($total_query);

        // Get users with points data
        $users_query = "
            SELECT
                u.ID,
                u.display_name,
                u.user_email,
                COALESCE(um.meta_value, 0) as current_points,
                COALESCE(earned.total_earned, 0) as total_earned,
                COALESCE(spent.total_spent, 0) as total_spent,
                GREATEST(
                    COALESCE(earned.last_earned, '0000-00-00'),
                    COALESCE(spent.last_spent, '0000-00-00')
                ) as last_activity
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'intersoccer_points_balance'
            LEFT JOIN (
                SELECT customer_id,
                       SUM(points_amount) as total_earned,
                       MAX(created_at) as last_earned
                FROM {$wpdb->prefix}intersoccer_points_log
                WHERE points_amount > 0
                GROUP BY customer_id
            ) earned ON u.ID = earned.customer_id
            LEFT JOIN (
                SELECT customer_id,
                       ABS(SUM(points_amount)) as total_spent,
                       MAX(created_at) as last_spent
                FROM {$wpdb->prefix}intersoccer_points_log
                WHERE points_amount < 0
                GROUP BY customer_id
            ) spent ON u.ID = spent.customer_id
            {$where}
            ORDER BY u.display_name
            LIMIT {$offset}, {$per_page}
        ";

        $users = $wpdb->get_results($users_query);

        wp_send_json_success([
            'users' => $users,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page
        ]);
    }

    /**
     * Adjust user points via AJAX
     */
    public function adjust_user_points_ajax() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!self::user_can_manage_points()) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        $user_id = intval($_POST['user_id']);
        $adjustment_type = sanitize_text_field($_POST['adjustment_type']);
        $points_amount_raw = sanitize_text_field($_POST['points_amount']);
        $reason = sanitize_textarea_field($_POST['reason']);

        // Phase 0: Validate integer-only points (reject fractional values)
        if (strpos($points_amount_raw, '.') !== false || strpos($points_amount_raw, ',') !== false) {
            wp_send_json_error(['message' => 'Points must be whole numbers only. Fractional values are not allowed.']);
        }

        $points_amount = intval($points_amount_raw);

        if (!$user_id || empty($reason)) {
            wp_send_json_error(['message' => 'Invalid data provided']);
        }

        if ($points_amount < 0) {
            wp_send_json_error(['message' => 'Points amount must be a positive integer']);
        }

        $points_manager = new InterSoccer_Points_Manager();

        switch ($adjustment_type) {
            case 'add':
                $result = $points_manager->add_points_transaction($user_id, 'admin_adjustment', $points_amount, null, $reason);
                break;
            case 'subtract':
                $result = $points_manager->add_points_transaction($user_id, 'admin_adjustment', -$points_amount, null, $reason);
                break;
            case 'set':
                // For setting balance, we need to calculate the difference
                $current_balance = $points_manager->get_points_balance($user_id);
                $difference = $points_amount - $current_balance;
                if ($difference != 0) {
                    $result = $points_manager->add_points_transaction($user_id, 'admin_balance_set', $difference, null, $reason . " (Balance set to {$points_amount})");
                } else {
                    wp_send_json_success(['message' => 'Balance already at target amount']);
                    return;
                }
                break;
            default:
                wp_send_json_error(['message' => 'Invalid adjustment type']);
                return;
        }

        if ($result === false) {
            wp_send_json_error(['message' => 'Failed to adjust points']);
        }

        // Update user meta balance
        $points_manager->update_user_points_balance($user_id);

        wp_send_json_success(['message' => 'Points adjusted successfully']);
    }

    /**
     * Export points report via AJAX
     */
    public function export_points_report_ajax() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!self::user_can_manage_points()) {
            wp_die('Unauthorized');
        }

        global $wpdb;

        // Get all users with points data
        $users = $wpdb->get_results("
            SELECT
                u.ID,
                u.display_name,
                u.user_email,
                COALESCE(um.meta_value, 0) as current_points,
                COALESCE(earned.total_earned, 0) as total_earned,
                COALESCE(spent.total_spent, 0) as total_spent,
                GREATEST(
                    COALESCE(um.updated_at, '0000-00-00'),
                    COALESCE(earned.last_earned, '0000-00-00'),
                    COALESCE(spent.last_spent, '0000-00-00')
                ) as last_activity
            FROM {$wpdb->users} u
            LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'intersoccer_points_balance'
            LEFT JOIN (
                SELECT customer_id,
                       SUM(points_amount) as total_earned,
                       MAX(created_at) as last_earned
                FROM {$wpdb->prefix}intersoccer_points_log
                WHERE points_amount > 0
                GROUP BY customer_id
            ) earned ON u.ID = earned.customer_id
            LEFT JOIN (
                SELECT customer_id,
                       ABS(SUM(points_amount)) as total_spent,
                       MAX(created_at) as last_spent
                FROM {$wpdb->prefix}intersoccer_points_log
                WHERE points_amount < 0
                GROUP BY customer_id
            ) spent ON u.ID = spent.customer_id
            ORDER BY u.display_name
        ");

        // Generate CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="customer-points-report-' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Customer ID', 'Name', 'Email', 'Current Points', 'Total Earned', 'Total Spent', 'Last Activity']);

        foreach ($users as $user) {
            fputcsv($output, [
                $user->ID,
                $user->display_name,
                $user->user_email,
                $user->current_points,
                $user->total_earned,
                $user->total_spent,
                $user->last_activity ? date('Y-m-d H:i:s', strtotime($user->last_activity)) : 'Never'
            ]);
        }

        fclose($output);
        exit;
    }
}