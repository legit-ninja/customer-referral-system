<?php
// includes/class-admin-dashboard.php

class InterSoccer_Referral_Admin_Dashboard {

    private $main_dashboard;
    private $coaches;
    private $referrals;
    private $financial;
    private $settings;
    private $points;
    private $coach_assignments;
    private $coach_events;

    public function __construct() {
        // Initialize modular classes
        $this->main_dashboard = new InterSoccer_Admin_Dashboard_Main();
        $this->coaches = new InterSoccer_Admin_Coaches();
        $this->referrals = new InterSoccer_Admin_Referrals();
        $this->financial = new InterSoccer_Admin_Financial();
        $this->settings = new InterSoccer_Admin_Settings();
        $this->points = new InterSoccer_Admin_Points();

        // Initialize coach assignments if class exists
        if (class_exists('InterSoccer_Admin_Coach_Assignments')) {
            $this->coach_assignments = new InterSoccer_Admin_Coach_Assignments();
        } else {
            $this->coach_assignments = null;
        }

        if (class_exists('InterSoccer_Admin_Coach_Events')) {
            $this->coach_events = new InterSoccer_Admin_Coach_Events();
        } else {
            $this->coach_events = null;
        }

        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'handle_settings']);
        add_action('admin_post_import_coaches_from_csv', [$this->settings, 'import_coaches_from_csv']);
        add_action('admin_post_nopriv_import_coaches_from_csv', [$this->settings, 'import_coaches_from_csv']);
        add_action('admin_post_download_coach_csv_sample', [$this->settings, 'download_coach_csv_sample']);
        add_action('wp_ajax_start_points_migration', [$this, 'start_points_migration']);
        add_action('wp_ajax_get_migration_progress', [$this, 'get_migration_progress']);
        add_action('wp_ajax_cancel_points_migration', [$this, 'cancel_points_migration']);
        add_action('wp_ajax_reset_points_migration', [$this, 'reset_points_migration']);
        add_action('wp_ajax_preview_points_migration', [$this, 'preview_points_migration']);
        add_action('wp_ajax_export_roi_report', [$this, 'export_roi_report']);
        add_action('wp_ajax_send_coach_message', [$this, 'send_coach_message']);
        add_action('wp_ajax_deactivate_coach', [$this, 'deactivate_coach']);
        add_action('wp_ajax_send_referral_code', [$this, 'send_referral_code']);
        add_action('wp_ajax_update_customer_credits', [$this, 'update_customer_credits']);
        add_action('wp_ajax_import_customers_credits', [$this, 'import_customers_and_assign_credits']);
        add_action('wp_ajax_emergency_cleanup_import', [$this, 'emergency_cleanup_import_session']);
        add_action('wp_ajax_debug_join_issue', [$this, 'debug_join_issue']);
        add_action('wp_ajax_allocate_credits_to_customers', [$this->settings, 'allocate_credits_to_customers']);
        add_action('wp_ajax_get_credit_statistics', [$this->settings, 'get_credit_statistics']);
        add_action('wp_ajax_get_coach_statistics', [$this->settings, 'get_coach_statistics']);
        add_action('wp_ajax_get_audit_log', [$this->settings, 'get_audit_log']);
        add_action('wp_ajax_clear_audit_log', [$this->settings, 'clear_audit_log']);
        add_action('wp_ajax_export_audit_log', [$this->settings, 'export_audit_log']);
        add_action('wp_ajax_bulk_credit_adjustment', [$this->settings, 'bulk_credit_adjustment']);
        add_action('wp_ajax_get_points_statistics', [$this->settings, 'get_points_statistics_ajax']);
        add_action('wp_ajax_get_points_ledger', [$this->points, 'get_points_ledger_ajax']);
        add_action('wp_ajax_get_points_users', [$this->points, 'get_points_users_ajax']);
        add_action('wp_ajax_adjust_user_points', [$this->points, 'adjust_user_points_ajax']);
        add_action('wp_ajax_export_points_report', [$this->points, 'export_points_report_ajax']);
        add_action('wp_ajax_intersoccer_update_referral_eligibility', [$this->referrals, 'ajax_update_referral_eligibility']);
        add_action('wp_ajax_intersoccer_clear_referral_code', [$this, 'clear_referral_code_ajax']);
        add_action('wp_ajax_intersoccer_filter_coach_referrals', [$this->referrals, 'ajax_filter_coach_referrals']);
        add_action('wp_ajax_intersoccer_get_coach_monthly_report', [$this->referrals, 'ajax_get_coach_monthly_report']);
        add_action('admin_post_intersoccer_delete_referral', [$this->referrals, 'handle_delete_referral']);

        // Debug action to test AJAX is working
        add_action('wp_ajax_test_ajax_connection', [$this, 'test_ajax_connection']);

        // WooCommerce Points Integration
        if (class_exists('WooCommerce')) {
            add_action('woocommerce_review_order_before_payment', [$this, 'add_referral_code_field']);
            add_action('woocommerce_review_order_before_payment', [$this, 'add_points_redemption_field']);
            add_action('woocommerce_checkout_process', [$this, 'validate_points_redemption']);
            // Mark discount usage on the order (so we can consume it only once the order becomes successful).
            add_action('woocommerce_checkout_create_order', [$this, 'maybe_mark_first_order_discount_on_order'], 10, 2);
            // add_action('woocommerce_checkout_create_order', [$this, 'apply_points_discount_to_order'], 10, 2); // Disabled - cart fees are automatically converted to order items
            add_action('woocommerce_order_status_changed', [$this, 'deduct_points_on_order_completion'], 10, 4);
            // Consume the first-order discount only when the order reaches a successful state.
            add_action('woocommerce_order_status_changed', [$this, 'maybe_consume_first_order_discount'], 20, 4);
            add_action('woocommerce_my_account_my_orders_column_order-total', [$this, 'display_points_used_in_orders']);
            add_action('woocommerce_cart_calculate_fees', [$this, 'apply_points_discount_as_fee'], 10, 1);
            add_action('wp_ajax_update_points_session', [$this, 'update_points_session']);
            add_action('wp_ajax_apply_referral_code', [$this, 'apply_referral_code_ajax']);
        }
    }

    public function add_admin_menus() {
        // Main menu
        add_menu_page(
            'InterSoccer Referrals',
            'Referrals',
            'manage_options',
            'intersoccer-referrals',
            [$this->main_dashboard, 'render_main_dashboard'],
            'dashicons-money-alt',
            30
        );

        // Submenus
        add_submenu_page(
            'intersoccer-referrals',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'intersoccer-referrals',
            [$this->main_dashboard, 'render_main_dashboard']
        );

        add_submenu_page(
            'intersoccer-referrals',
            'Coaches',
            'Coaches',
            'manage_options',
            'intersoccer-coaches',
            [$this->coaches, 'render_coaches_page']
        );

        add_submenu_page(
            'intersoccer-referrals',
            'Coach Referrals',
            'Coach Referrals',
            'manage_options',
            'intersoccer-coach-referrals',
            [$this->referrals, 'render_coach_referrals_page']
        );

        add_submenu_page(
            'intersoccer-referrals',
            'Customer Referrals',
            'Customer Referrals',
            'manage_options',
            'intersoccer-customer-referrals',
            [$this->referrals, 'render_customer_referrals_page']
        );

        add_submenu_page(
            'intersoccer-referrals',
            'Financial Report',
            'Financial Report',
            'manage_options',
            'intersoccer-financial-report',
            [$this->financial, 'render_financial_report_page']
        );

        add_submenu_page(
            'intersoccer-referrals',
            'Customer Points',
            'Customer Points',
            'manage_options',
            'intersoccer-customer-points',
            [$this->points, 'render_points_page']
        );

        if ($this->coach_assignments && $this->coach_events) {
            add_submenu_page(
                'intersoccer-referrals',
                __('Coach Event Assignments', 'intersoccer-referral'),
                __('Coach Event Assignments', 'intersoccer-referral'),
                'manage_options',
                'intersoccer-coach-event-assignments',
                [$this, 'render_coach_event_assignments_page']
            );
        }

        add_submenu_page(
            'intersoccer-referrals',
            __('Settings', 'intersoccer-referral'),
            __('Settings', 'intersoccer-referral'),
            'manage_options',
            'intersoccer-settings',
            [$this->settings, 'render_settings_page']
        );

        add_submenu_page(
            'intersoccer-referrals',
            __('Tools', 'intersoccer-referral'),
            __('Tools', 'intersoccer-referral'),
            'manage_options',
            'intersoccer-tools',
            [$this->settings, 'render_tools_page']
        );
    }

    public function render_coach_event_assignments_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        ?>
        <div class="wrap coach-event-assignments-page">
            <h1><?php esc_html_e('Coach Event Assignments', 'intersoccer-referral'); ?></h1>
            <p class="description">
                <?php esc_html_e('Manage venue/camp/course assignments as well as specific event participation from a single workspace.', 'intersoccer-referral'); ?>
            </p>

            <h2 class="nav-tab-wrapper coach-event-tabs">
                <a href="#" class="nav-tab nav-tab-active" data-target="coach-assignments-panel">
                    <?php esc_html_e('Venue & Course Assignments', 'intersoccer-referral'); ?>
                </a>
                <a href="#" class="nav-tab" data-target="coach-events-panel">
                    <?php esc_html_e('Event Participation', 'intersoccer-referral'); ?>
                </a>
            </h2>

            <div class="coach-event-tab-panels">
                <section id="coach-assignments-panel" class="coach-event-panel active" aria-label="<?php esc_attr_e('Venue and course assignments', 'intersoccer-referral'); ?>">
                    <?php
                    if ($this->coach_assignments && method_exists($this->coach_assignments, 'render_section')) {
                        $this->coach_assignments->render_section();
                    }
                    ?>
                </section>

                <section id="coach-events-panel" class="coach-event-panel" aria-label="<?php esc_attr_e('Event participation assignments', 'intersoccer-referral'); ?>">
                    <?php
                    if ($this->coach_events && method_exists($this->coach_events, 'render_section')) {
                        $this->coach_events->render_section();
                    }
                    ?>
                </section>
            </div>
        </div>
        <?php
    }

    public function enqueue_admin_assets($hook) {
        // Debug: log the current hook
        intersoccer_referral_log('Enqueue hook: ' . $hook);

        if (strpos($hook, 'intersoccer') !== false) {
            // Enqueue Chart.js first
            wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', [], '3.9.1');

            // Enqueue our admin assets
            wp_enqueue_style('intersoccer-admin-css', INTERSOCCER_REFERRAL_URL . 'assets/css/admin-dashboard.css', [], INTERSOCCER_REFERRAL_VERSION);
            // Dashboard main page has extra layout styles.
            if (strpos($hook, 'intersoccer-referrals') !== false) {
                wp_enqueue_style(
                    'intersoccer-admin-dashboard-main-css',
                    INTERSOCCER_REFERRAL_URL . 'assets/css/admin-dashboard-main.css',
                    ['intersoccer-admin-css'],
                    INTERSOCCER_REFERRAL_VERSION
                );
            }
            if (strpos($hook, 'intersoccer-financial-report') !== false) {
                wp_enqueue_style(
                    'intersoccer-admin-financial-css',
                    INTERSOCCER_REFERRAL_URL . 'assets/css/admin-financial.css',
                    ['intersoccer-admin-css'],
                    INTERSOCCER_REFERRAL_VERSION
                );
            }
            if (strpos($hook, 'intersoccer-customer-points') !== false) {
                wp_enqueue_style(
                    'intersoccer-admin-points-css',
                    INTERSOCCER_REFERRAL_URL . 'assets/css/admin-points.css',
                    ['intersoccer-admin-css'],
                    INTERSOCCER_REFERRAL_VERSION
                );
                wp_enqueue_script(
                    'intersoccer-admin-points-js',
                    INTERSOCCER_REFERRAL_URL . 'assets/js/admin-points.js',
                    ['jquery'],
                    INTERSOCCER_REFERRAL_VERSION,
                    true
                );
            }
            wp_enqueue_script('intersoccer-admin-js', INTERSOCCER_REFERRAL_URL . 'assets/js/admin-dashboard.js', ['jquery', 'chart-js'], INTERSOCCER_REFERRAL_VERSION, true);

            $localize = [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('intersoccer_admin_nonce'),
                'simulator_nonce' => wp_create_nonce('intersoccer_simulator_nonce'),
                'i18n' => [
                    'coach_events_select_event' => __('Please select an event before saving.', 'intersoccer-referral'),
                    'coach_events_search_min_chars' => __('Enter at least two characters to search.', 'intersoccer-referral'),
                    'coach_events_searching' => __('Searching…', 'intersoccer-referral'),
                    'coach_events_no_events' => __('No events found.', 'intersoccer-referral'),
                    'coach_events_search_failed' => __('Search failed. Please try again.', 'intersoccer-referral'),
                    'coach_events_remove_confirm' => __('Remove this event assignment?', 'intersoccer-referral'),
                    'coach_events_link_copied' => __('Link copied to clipboard.', 'intersoccer-referral'),
                    'coach_events_press_ctrl_c' => __('Press Ctrl+C to copy the link', 'intersoccer-referral'),
                    'coach_events_save_error' => __('Error saving event', 'intersoccer-referral'),
                    'coach_events_save_network_error' => __('Network error while saving event', 'intersoccer-referral'),
                    'coach_events_remove_error' => __('Error removing event', 'intersoccer-referral'),
                    'coach_events_status_error' => __('Error updating status', 'intersoccer-referral'),
                    'coach_events_status_network_error' => __('Network error updating status', 'intersoccer-referral'),
                    'coach_referrals_loading' => __('Loading report…', 'intersoccer-referral'),
                    'coach_referrals_error' => __('Could not load monthly report. Please try again.', 'intersoccer-referral'),
                ],
            ];

            if (strpos($hook, 'intersoccer-coach-referrals') !== false) {
                $initial_month = gmdate('Y-m');
                $initial_coach = !empty($_GET['coach_id']) ? absint($_GET['coach_id']) : 0;
                if (!empty($_GET['month'])) {
                    $parsed = $this->referrals->sanitize_month(wp_unslash($_GET['month']));
                    if ($parsed) {
                        $initial_month = $parsed;
                    }
                }
                $localize['coach_referrals_report'] = $this->referrals->get_coach_monthly_report_data(
                    $initial_month,
                    $initial_coach ?: null
                );
            }

            wp_localize_script('intersoccer-admin-js', 'intersoccer_admin', $localize);

            // Enqueue settings page and tools page specific assets
            if (strpos($hook, 'intersoccer-settings') !== false || strpos($hook, 'intersoccer-tools') !== false) {
                intersoccer_referral_log('Enqueueing settings/tools page assets for hook: ' . $hook);
                wp_enqueue_style('intersoccer-admin-settings-css', INTERSOCCER_REFERRAL_URL . 'assets/css/admin-settings.css', [], INTERSOCCER_REFERRAL_VERSION);
                wp_enqueue_script('intersoccer-admin-settings-js', INTERSOCCER_REFERRAL_URL . 'assets/js/admin-settings.js', ['jquery'], INTERSOCCER_REFERRAL_VERSION, true);
                wp_enqueue_script('intersoccer-admin-simulator-js', INTERSOCCER_REFERRAL_URL . 'assets/js/admin-simulator.js', ['jquery', 'chart-js'], INTERSOCCER_REFERRAL_VERSION, true);

                wp_localize_script('intersoccer-admin-settings-js', 'intersoccer_admin', [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('intersoccer_admin_nonce')
                ]);
            }
        }
    }

    /**
     * Handle admin settings and form submissions
     */
    public function handle_settings() {
        // Handle any settings form submissions here
        // This method is called on admin_init

        // Check if we're on our settings page
        if (isset($_GET['page']) && $_GET['page'] === 'intersoccer-settings') {
            // Handle settings form submissions
            if (isset($_POST['submit']) && check_admin_referer('intersoccer_settings_nonce')) {
                // Process settings updates
                $this->process_settings_update();
            }
        }
    }

    /**
     * Start points migration process
     */
    public function start_points_migration() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        // Points migration logic would go here
        wp_send_json_success(['message' => 'Migration started']);
    }

    /**
     * Get migration progress
     */
    public function get_migration_progress() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        // Return migration progress
        wp_send_json_success(['progress' => 0, 'message' => 'Migration in progress']);
    }

    /**
     * Cancel points migration
     */
    public function cancel_points_migration() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['message' => 'Migration cancelled']);
    }

    /**
     * Reset points migration
     */
    public function reset_points_migration() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['message' => 'Migration reset']);
    }

    /**
     * Preview points migration
     */
    public function preview_points_migration() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['preview' => [], 'message' => 'Migration preview']);
    }

    /**
     * Export ROI report
     */
    public function export_roi_report() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Export logic would go here
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="roi-report.csv"');
        echo "Date,Revenue,Costs,ROI\n";
        exit;
    }

    /**
     * Send coach message
     */
    public function send_coach_message() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['message' => 'Message sent']);
    }

    /**
     * Deactivate coach
     */
    public function deactivate_coach() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['message' => 'Coach deactivated']);
    }

    /**
     * Send referral code email to one or more coaches.
     * Expects: coach_ids (array of ints) or coach_id (single int), or send_all (bool).
     */
    public function send_referral_code() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized.', 'intersoccer-referral')]);
        }

        $coach_ids = [];
        if (!empty($_POST['send_all']) && $_POST['send_all'] === '1') {
            $users = get_users(['role' => 'coach', 'fields' => 'ID']);
            $coach_ids = array_map('intval', $users);
        } elseif (!empty($_POST['coach_ids']) && is_array($_POST['coach_ids'])) {
            $coach_ids = array_map('intval', $_POST['coach_ids']);
        } elseif (!empty($_POST['coach_id'])) {
            $coach_ids = [intval($_POST['coach_id'])];
        }

        if (empty($coach_ids)) {
            wp_send_json_error(['message' => __('No coaches selected.', 'intersoccer-referral')]);
        }

        $results = [];
        $success_count = 0;
        $fail_count = 0;
        $skipped_count = 0;

        foreach ($coach_ids as $id) {
            $result = InterSoccer_Referral_Handler::send_referral_code_email($id);
            $sent = isset($result['sent']) ? (bool) $result['sent'] : (bool) $result['success'];
            $results[] = ['coach_id' => $id, 'success' => $result['success'], 'sent' => $sent, 'message' => $result['message']];
            if ($result['success'] && $sent) {
                $success_count++;
            } elseif ($result['success'] && !$sent) {
                $skipped_count++;
            } else {
                $fail_count++;
            }
        }

        $total = count($coach_ids);
        if ($fail_count === 0 && $skipped_count === 0) {
            $summary = sprintf(
                /* translators: %d: number of coaches */
                _n('Referral code sent to %d coach.', 'Referral codes sent to %d coaches.', $total, 'intersoccer-referral'),
                $total
            );
        } elseif ($success_count === 0 && $fail_count === 0 && $skipped_count > 0) {
            $summary = __('Email notifications are disabled in settings. No referral emails were sent.', 'intersoccer-referral');
        } else {
            $summary = sprintf(
                __('%1$d sent, %2$d skipped, %3$d failed.', 'intersoccer-referral'),
                $success_count,
                $skipped_count,
                $fail_count
            );
        }

        wp_send_json_success([
            'message' => $summary,
            'results' => $results,
            'success_count' => $success_count,
            'skipped_count' => $skipped_count,
            'fail_count' => $fail_count,
        ]);
    }

    /**
     * Update customer credits
     */
    public function update_customer_credits() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'intersoccer-referral')]);
        }

        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        if (!$user_id || !get_userdata($user_id)) {
            wp_send_json_error(['message' => __('Invalid user.', 'intersoccer-referral')]);
        }

        if (!isset($_POST['credits'])) {
            wp_send_json_error(['message' => __('Credits value is required.', 'intersoccer-referral')]);
        }

        $credits = (float) $_POST['credits'];
        if ($credits < 0) {
            wp_send_json_error(['message' => __('Credits cannot be negative.', 'intersoccer-referral')]);
        }

        update_user_meta($user_id, 'intersoccer_customer_credits', $credits);
        update_user_meta($user_id, 'intersoccer_points_balance', $credits);

        wp_send_json_success([
            'message' => __('Credits updated.', 'intersoccer-referral'),
            'credits'  => $credits,
        ]);
    }

    /**
     * Import customers and assign credits
     */
    public function import_customers_and_assign_credits() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['message' => 'Import completed']);
    }

    /**
     * Emergency cleanup import session
     */
    public function emergency_cleanup_import_session() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['message' => 'Cleanup completed']);
    }

    /**
     * Debug join issue
     */
    public function debug_join_issue() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        wp_send_json_success(['debug' => 'Debug info']);
    }

    /**
     * Test AJAX connection
     */
    public function test_ajax_connection() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');
        wp_send_json_success(['message' => 'AJAX connection working', 'timestamp' => current_time('mysql')]);
    }

    /**
     * Add points redemption field to checkout
     */
    public function add_referral_code_field() {
        // Add referral code input field before order review
        if (!is_user_logged_in()) return;

        // Hide checkout fields in passive mode
        if (get_option('intersoccer_passive_mode', false)) {
            return;
        }

        $referral_context = $this->get_checkout_referral_context();
        $prefill_code = $referral_context['prefill_code'];
        $is_code_applied = !empty($referral_context['applied_code']);
        $auto_apply = $referral_context['auto_apply'] ? 'yes' : 'no';

        $input_attributes = sprintf(
            ' value="%s" data-code-applied="%s"',
            esc_attr($prefill_code),
            $is_code_applied ? 'yes' : 'no'
        );

        $input_disabled = $is_code_applied ? ' disabled="disabled"' : '';
        $button_disabled = $is_code_applied ? ' disabled="disabled"' : '';
        $button_label = $is_code_applied ? __('Applied', 'intersoccer-referral') : __('Apply Code', 'intersoccer-referral');

        $message_text = '';
        $message_classes = 'intersoccer-referral-message';
        $message_display = 'none';
        $message_status_attr = '';

        if (!empty($referral_context['status_message'])) {
            $message_status_attr = ' data-status-message="' . esc_attr($referral_context['status_message']) . '"';
            $message_text = $referral_context['status_message'];
            $message_classes .= ' success';
            $message_display = 'block';
        }

        if ($is_code_applied) {
            $message_text = sprintf(
                __('Referral code %s is applied. Your discount appears in the order summary.', 'intersoccer-referral'),
                esc_html($prefill_code)
            );
            $message_classes .= ' success';
            $message_display = 'block';
        }

        if (function_exists('WC') && WC()->session) {
            WC()->session->set('intersoccer_referral_status_message', null);
        }

        echo '<div class="intersoccer-referral-code-wrapper">';
        echo '<div class="intersoccer-referral-code">';
        echo '<div class="intersoccer-referral-code-inner">';
        echo '<label for="intersoccer_referral_code">' . __('Referral Code (Optional)', 'intersoccer-referral') . '</label>';
        echo '<input type="text" name="intersoccer_referral_code" id="intersoccer_referral_code" placeholder="Enter referral code"' . $input_attributes . $input_disabled . ' />';
        echo '<button type="button" id="apply_referral_code" class="button button-secondary" data-auto-apply="' . esc_attr($auto_apply) . '"' . $button_disabled . '>' . esc_html($button_label) . '</button>';
        if ($is_code_applied) {
            echo '<button type="button" id="change_referral_code" class="button button-link" style="margin-left:8px;">' . esc_html__('Change Code', 'intersoccer-referral') . '</button>';
        }
        echo '</div>';
        echo '<div id="referral_code_message" class="' . esc_attr($message_classes) . '" data-applied="' . ($is_code_applied ? 'yes' : 'no') . '"' . $message_status_attr . ' style="display: ' . $message_display . ';">' . esc_html($message_text) . '</div>';
        echo '</div>';
        echo '</div>';
    }

    public function add_points_redemption_field() {
        // WooCommerce checkout integration
        if (!is_user_logged_in()) return;

        // Hide checkout fields in passive mode
        if (get_option('intersoccer_passive_mode', false)) {
            return;
        }

        $user_id = get_current_user_id();
        $available_credits = get_user_meta($user_id, 'intersoccer_points_balance', true) ?: 0;
        intersoccer_referral_log("Checkout points field - User: $user_id, Available credits: $available_credits");

        if ($available_credits > 0) {
            // Get cart total for context
            $cart_total = WC()->cart->get_total('edit');

            echo '<div class="intersoccer-points-redemption-wrapper">';
            echo '<div class="intersoccer-points-redemption">';
            echo '<div class="intersoccer-points-redemption-toggle">';
            echo '<input type="checkbox" name="intersoccer_use_points" id="intersoccer_use_points" />';
            echo '<label for="intersoccer_use_points">' . __('Use Loyalty Points', 'intersoccer-referral') . '</label>';
            echo '</div>';

            echo '<div class="points-details" style="display: none;">';
            echo '<p class="points-available">' . sprintf(__('You have %s points available', 'intersoccer-referral'), '<strong>' . number_format($available_credits, 0) . '</strong>') . '</p>';

            echo '<div class="points-quick-apply">';
            echo '<button type="button" class="apply-all-points button button-secondary">' . __('Apply All Available', 'intersoccer-referral') . '</button>';
            echo '</div>';

            echo '<div class="custom-amount">';
            echo '<label for="intersoccer_points_to_redeem">' . __('Or enter custom amount:', 'intersoccer-referral') . '</label>';
            echo '<input type="number" name="intersoccer_points_to_redeem" id="intersoccer_points_to_redeem" min="0" max="' . $available_credits . '" step="1" placeholder="0" />';
            echo '<span class="points-unit">points</span>';
            echo '</div>';

            echo '<p class="points-limit-desc">' . __('You can redeem up to your cart total or available points, whichever is less.', 'intersoccer-referral') . '</p>';

            echo '<div class="applied-amount" style="display: none;">';
            echo '<span class="applied-text"></span>';
            echo '</div>';

            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
    }

    /**
     * Validate points redemption on checkout
     */
    public function validate_points_redemption() {
        // Only validate if points usage is enabled
        if (!isset($_POST['intersoccer_use_points']) || $_POST['intersoccer_use_points'] !== 'on') {
            return;
        }

        $points_to_redeem = isset($_POST['intersoccer_points_to_redeem']) ? intval($_POST['intersoccer_points_to_redeem']) : 0;

        if ($points_to_redeem < 0) {
            wc_add_notice(__('Invalid points amount.', 'intersoccer-referral'), 'error');
            return;
        }

        $user_id = get_current_user_id();
        $available_credits = get_user_meta($user_id, 'intersoccer_points_balance', true) ?: 0;

        if ($points_to_redeem > $available_credits) {
            wc_add_notice(__('You don\'t have enough points available.', 'intersoccer-referral'), 'error');
            return;
        }

        // Validate against cart total (not against arbitrary 100-point limit)
        $cart_total = WC()->cart->get_total('edit');
        if ($points_to_redeem > $cart_total) {
            wc_add_notice(__('Points redemption cannot exceed your cart total.', 'intersoccer-referral'), 'error');
            return;
        }

        // Ensure at least 1 point is being redeemed if checkbox is checked
        if ($points_to_redeem === 0) {
            wc_add_notice(__('Please enter the number of credits to redeem.', 'intersoccer-referral'), 'error');
            return;
        }
    }

    /**
     * Apply points discount to order
     */
    public function apply_points_discount_to_order($order) {
        // Only process if points usage is enabled
        if (!isset($_POST['intersoccer_use_points']) || $_POST['intersoccer_use_points'] !== 'on') {
            return;
        }

        $points_to_redeem = isset($_POST['intersoccer_points_to_redeem']) ? intval($_POST['intersoccer_points_to_redeem']) : 0;

        if ($points_to_redeem > 0) {
            if (is_object($order) && method_exists($order, 'update_meta_data')) {
                $order->update_meta_data('_intersoccer_points_redeemed', $points_to_redeem);
            }

            $session = $this->get_wc_session();
            if ($session) {
                $session->set('intersoccer_points_to_redeem', $points_to_redeem);
            }

            // Add order note
            $order->add_order_note(sprintf(__('Customer redeemed %d referral credits.', 'intersoccer-referral'), $points_to_redeem));

            // Add a fee line item for the discount
            $fee = new WC_Order_Item_Fee();
            $fee->set_name(__('Referral Credits Discount', 'intersoccer-referral'));
            $fee->set_amount(-$points_to_redeem); // Negative amount for discount
            $fee->set_tax_status('none');
            $fee->set_total(-$points_to_redeem);
            $fee->set_total_tax(0);

            // Add the fee to the order
            $order->add_item($fee);

            // Recalculate totals
            $order->calculate_totals();
        }
    }

    /**
     * WooCommerce session handle when available (null in wp-admin and other non-checkout contexts).
     *
     * @return object|null
     */
    private function get_wc_session() {
        if (!function_exists('WC')) {
            return null;
        }

        $wc = WC();
        return (is_object($wc) && isset($wc->session) && $wc->session) ? $wc->session : null;
    }

    /**
     * Points redeemed via the "Referral Credits Discount" order fee line.
     *
     * @param WC_Order $order
     * @return int
     */
    private function get_referral_credits_discount_points_from_order($order) {
        if (!is_object($order) || !method_exists($order, 'get_items')) {
            return 0;
        }

        $fee_label = __('Referral Credits Discount', 'intersoccer-referral');

        foreach ($order->get_items('fee') as $item) {
            if (!is_object($item) || !method_exists($item, 'get_name')) {
                continue;
            }
            if ($item->get_name() !== $fee_label) {
                continue;
            }
            if (!method_exists($item, 'get_total')) {
                return 0;
            }

            return (int) round(abs((float) $item->get_total()));
        }

        return 0;
    }

    /**
     * Resolve loyalty credits to deduct when an order is marked completed.
     *
     * @param WC_Order $order
     * @return int
     */
    private function resolve_points_to_redeem_on_completion($order) {
        if (!is_object($order) || !method_exists($order, 'get_meta')) {
            return 0;
        }

        if ((int) $order->get_meta('_intersoccer_credits_deducted_on_completion', true) === 1) {
            return 0;
        }

        $from_fee = $this->get_referral_credits_discount_points_from_order($order);
        if ($from_fee > 0) {
            return $from_fee;
        }

        $from_meta = (int) $order->get_meta('_intersoccer_points_redeemed', true);
        if ($from_meta > 0) {
            return $from_meta;
        }

        $session = $this->get_wc_session();
        if ($session) {
            return (int) $session->get('intersoccer_points_to_redeem', 0);
        }

        return 0;
    }

    /**
     * Referral code and coach ID for completion handlers (session + order meta).
     *
     * @param int      $order_id
     * @param WC_Order $order
     * @return array{0: string, 1: int}
     */
    private function resolve_referral_context_for_order($order_id, $order) {
        $referral_code = '';
        $referral_coach_id = 0;

        $session = $this->get_wc_session();
        if ($session) {
            $referral_code = (string) $session->get('intersoccer_applied_referral_code', '');
            $referral_coach_id = (int) $session->get('intersoccer_referral_coach_id', 0);
        }

        if ($referral_code === '' && is_object($order) && method_exists($order, 'get_meta')) {
            $referral_code = (string) $order->get_meta('_intersoccer_referral_code', true);
        }
        if ($referral_code === '') {
            $referral_code = (string) get_post_meta($order_id, '_intersoccer_referral_code', true);
        }

        if ($referral_coach_id <= 0 && is_object($order) && method_exists($order, 'get_meta')) {
            $referral_coach_id = (int) $order->get_meta('_intersoccer_referring_coach_id', true);
        }
        if ($referral_coach_id <= 0) {
            $referral_coach_id = (int) get_post_meta($order_id, '_intersoccer_referring_coach_id', true);
        }

        return [trim($referral_code), $referral_coach_id];
    }

    /**
     * Whether a referral bonus was already stored for this order.
     *
     * @param int $order_id
     * @return bool
     */
    private function referral_reward_already_recorded($order_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'intersoccer_referral_rewards';
        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$table} WHERE order_id = %d LIMIT 1", (int) $order_id)
        );

        return !empty($existing);
    }

    /**
     * Deduct points when order is completed
     *
     * @param int           $order_id
     * @param string        $old_status
     * @param string        $new_status
     * @param WC_Order|null $order
     */
    public function deduct_points_on_order_completion($order_id, $old_status, $new_status, $order = null) {
        if ($new_status !== 'completed') {
            return;
        }

        if (!$order instanceof WC_Order) {
            $order = wc_get_order($order_id);
        }
        if (!$order) {
            return;
        }

        $points_to_redeem = $this->resolve_points_to_redeem_on_completion($order);

        if ($points_to_redeem > 0) {
            $user_id = (int) $order->get_customer_id();
            if ($user_id > 0) {
                // Deduct points from user
                $current_credits = get_user_meta($user_id, 'intersoccer_points_balance', true) ?: 0;
                $new_credits = max(0, $current_credits - $points_to_redeem);
                update_user_meta($user_id, 'intersoccer_points_balance', $new_credits);

                // Find the fee item for the discount
                $fee_item_id = null;
                if (method_exists($order, 'get_items')) {
                    $fee_label = __('Referral Credits Discount', 'intersoccer-referral');
                    foreach ($order->get_items('fee') as $item_id => $item) {
                        if (is_object($item) && method_exists($item, 'get_name') && $item->get_name() === $fee_label) {
                            $fee_item_id = $item_id;
                            break;
                        }
                    }
                }

                // Record the redemption
                global $wpdb;
                $wpdb->insert(
                    $wpdb->prefix . 'intersoccer_credit_redemptions',
                    [
                        'customer_id' => $user_id,
                        'order_item_id' => $fee_item_id,
                        'credit_amount' => $points_to_redeem,
                        'order_total' => $order->get_total(),
                        'discount_applied' => $points_to_redeem, // 1 point = 1 CHF discount
                        'created_at' => current_time('mysql'),
                    ]
                );

                if (method_exists($order, 'update_meta_data') && method_exists($order, 'save')) {
                    $order->update_meta_data('_intersoccer_credits_deducted_on_completion', 1);
                    $order->update_meta_data('_intersoccer_points_redeemed', $points_to_redeem);
                    $order->save();
                }

                $session = $this->get_wc_session();
                if ($session) {
                    $session->set('intersoccer_points_to_redeem', 0);
                }

                // Add order note
                $order->add_order_note(sprintf(
                    __('Deducted %d credits from customer balance. New balance: %d', 'intersoccer-referral'),
                    $points_to_redeem,
                    $new_credits
                ));
            }
        }

        // Award points to coach for every purchase (uses configured purchase rate)
        $this->award_purchase_points_to_coach($order);

        // Award points to coach for referral code usage (one-time bonus)
        list($referral_code, $referral_coach_id) = $this->resolve_referral_context_for_order($order_id, $order);
        intersoccer_referral_log("Checking referral bonus: code=$referral_code, coach_id=$referral_coach_id");

        if ($referral_code && $referral_coach_id && !$this->referral_reward_already_recorded($order_id)) {
            // Check if this is the customer's first completed order
            $customer_orders = wc_get_orders([
                'customer_id' => $order->get_customer_id(),
                'status' => 'completed',
                'limit' => 1,
            ]);
            intersoccer_referral_log('Customer completed orders count: ' . count($customer_orders));

            // If this is their first completed order, award bonus points to coach
            if (count($customer_orders) === 1 && $customer_orders[0]->get_id() === $order_id) {
                intersoccer_referral_log("Awarding referral bonus to coach $referral_coach_id");
                $points_to_award = intersoccer_referral_get_coach_referral_bonus_points();

                if ($points_to_award > 0) {
                // Get current coach points balance
                $current_coach_points = get_user_meta($referral_coach_id, 'intersoccer_points_balance', true) ?: 0;
                $new_coach_points = $current_coach_points + $points_to_award;
                update_user_meta($referral_coach_id, 'intersoccer_points_balance', $new_coach_points);

                // Record the referral reward
                global $wpdb;
                $wpdb->insert(
                    $wpdb->prefix . 'intersoccer_referral_rewards',
                    [
                        'coach_id' => $referral_coach_id,
                        'customer_id' => $order->get_customer_id(),
                        'order_id' => $order_id,
                        'referral_code' => $referral_code,
                        'points_awarded' => $points_to_award,
                        'created_at' => current_time('mysql'),
                    ]
                );

                // Add order note
                $coach_info = get_userdata($referral_coach_id);
                if ($coach_info) {
                    $order->add_order_note(sprintf(
                        __('Awarded %d bonus points to coach %s for referral code usage. New balance: %d', 'intersoccer-referral'),
                        $points_to_award,
                        $coach_info->display_name,
                        $new_coach_points
                    ));
                }

                $session = $this->get_wc_session();
                if ($session) {
                    $session->set('intersoccer_applied_referral_code', null);
                    $session->set('intersoccer_referral_coach_id', null);
                }
                }
            }
        }
    }

    /**
     * Award points to coach for customer purchases (CHF 10 spent = 1 point)
     */
    private function award_purchase_points_to_coach($order) {
        $customer_id = $order->get_customer_id();
        intersoccer_referral_log("Awarding purchase points: customer_id=$customer_id, order_id=" . $order->get_id());

        // Get the customer's preferred coach
        $coach_id = get_user_meta($customer_id, 'intersoccer_preferred_coach', true);
        intersoccer_referral_log("Coach ID for customer $customer_id: $coach_id");

        if (!$coach_id) {
            intersoccer_referral_log("No coach linked to customer $customer_id");
            return; // No linked coach
        }

        // Calculate points to award using configured purchase allocation rules.
        $order_total = max(0, (float) $order->get_total());
        $points_to_award = 0;
        if (class_exists('InterSoccer_Points_Manager')) {
            $points_manager = new InterSoccer_Points_Manager();
            $points_to_award = (int) $points_manager->calculate_points_from_order_total(
                $order_total,
                (int) $coach_id,
                'purchase',
                false
            );
        } else {
            $rate = max(1, (int) get_option('intersoccer_points_rate_customer_purchase', 10));
            $points_to_award = (int) floor($order_total / $rate);
        }
        intersoccer_referral_log("Order total: $order_total, points to award: $points_to_award");

        if ($points_to_award <= 0) {
            intersoccer_referral_log("No points to award for order total $order_total");
            return; // No points to award
        }

        // Get current coach points balance
        $current_coach_points = get_user_meta($coach_id, 'intersoccer_points_balance', true) ?: 0;
        $new_coach_points = $current_coach_points + $points_to_award;
        update_user_meta($coach_id, 'intersoccer_points_balance', $new_coach_points);
        intersoccer_referral_log("Updated coach $coach_id points: $current_coach_points -> $new_coach_points");

        // Record the purchase reward
        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'intersoccer_purchase_rewards',
            [
                'coach_id' => $coach_id,
                'customer_id' => $customer_id,
                'order_id' => $order->get_id(),
                'order_total' => $order_total,
                'points_awarded' => $points_to_award,
                'created_at' => current_time('mysql')
            ]
        );

        if ($result === false) {
            intersoccer_referral_log("Failed to insert purchase reward: " . $wpdb->last_error);
        } else {
            intersoccer_referral_log("Successfully recorded purchase reward for coach $coach_id");
        }

        // Add order note
        $coach_info = get_userdata($coach_id);
        $order->add_order_note(sprintf(__('Awarded %d points to coach %s for customer purchase (CHF %.2f). New balance: %d', 'intersoccer-referral'),
            $points_to_award, $coach_info->display_name, $order_total, $new_coach_points));

        intersoccer_referral_log("Awarded $points_to_award points to coach $coach_id for customer $customer_id purchase of CHF $order_total");
    }

    /**
     * AJAX handler to update points session
     */
    public function update_points_session() {
        check_ajax_referer('intersoccer_checkout_nonce', 'nonce');

        $points_to_redeem = isset($_POST['points_to_redeem']) ? intval($_POST['points_to_redeem']) : 0;

        // Validate points
        if ($points_to_redeem < 0) {
            $points_to_redeem = 0;
        }

        $user_id = get_current_user_id();
        $available_points = get_user_meta($user_id, 'intersoccer_points_balance', true) ?: 0;
        
        // Get cart total to limit redemption
        $cart_total = WC()->cart ? WC()->cart->get_total('edit') : 0;

        intersoccer_referral_log("Points session update - User: $user_id, Requested: $points_to_redeem, Available: $available_points, Cart Total: $cart_total");

        // Limit to available points AND cart total (no 100-point limit)
        $points_to_redeem = min($points_to_redeem, $available_points, $cart_total);

        // Update session
        WC()->session->set('intersoccer_points_to_redeem', $points_to_redeem);

        wp_send_json_success([
            'points_to_redeem' => $points_to_redeem,
            'discount_amount' => $points_to_redeem
        ]);
    }

    public function apply_referral_code_ajax() {
        check_ajax_referer('intersoccer_checkout_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Must be logged in to apply referral code']);
        }

        $referral_code = sanitize_text_field($_POST['referral_code'] ?? '');
        $result = $this->apply_coach_referral_code_internal($referral_code, [
            'recalculate' => true,
            'context' => 'ajax'
        ]);

        if (!$result['success']) {
            wp_send_json_error(['message' => $result['message']]);
        }

        wp_send_json_success([
            'message' => $result['message'],
            'coach_name' => $result['coach_name'],
            'discount_amount' => $result['discount_amount']
        ]);
    }

    /**
     * AJAX: clear any referral code currently applied to the checkout session.
     * This allows the customer to replace a previously auto-applied referral code
     * (e.g., a customer/friend code) with a different one (e.g., a coach code).
     */
    public function clear_referral_code_ajax() {
        check_ajax_referer('intersoccer_checkout_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('Must be logged in.', 'intersoccer-referral')]);
        }

        if (!function_exists('WC') || !WC()->session) {
            wp_send_json_error(['message' => __('Session not available. Please refresh the page.', 'intersoccer-referral')]);
        }

        $session = WC()->session;

        // Clear all referral-related session keys
        foreach ([
            'intersoccer_applied_referral_code',
            'intersoccer_referral',
            'intersoccer_referral_coach_id',
            'coach_referral_code',
            'customer_referral_code',
            'intersoccer_first_order_discount_available',
            'intersoccer_referral_status_message',
        ] as $key) {
            $session->set($key, null);
        }

        // Expire the referral cookie so it cannot auto-apply on the next page load
        $cookie_path   = defined('COOKIEPATH')    ? COOKIEPATH    : '/';
        $cookie_domain = defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '';
        setcookie('intersoccer_referral', '', time() - 3600, $cookie_path, $cookie_domain, is_ssl(), true);

        if (WC()->cart) {
            WC()->cart->calculate_totals();
            WC()->cart->set_session();
        }

        wp_send_json_success(['message' => __('Referral code removed.', 'intersoccer-referral')]);
    }

    /**
     * Apply points discount to cart total
     */
    public function apply_points_discount_as_fee($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        if (!function_exists('WC') || !WC()->session) {
            return;
        }

        $session = WC()->session;

        // Apply referral code discount only for first purchase
        $referral_code = $session->get('intersoccer_applied_referral_code');
        $current_user_id = get_current_user_id();
        $eligible_for_discount = $this->customer_is_eligible_for_first_order_discount($current_user_id);

        if ($referral_code && $eligible_for_discount) {
            $cart_subtotal = method_exists($cart, 'get_subtotal') ? (float) $cart->get_subtotal() : 0;
            $discount_value = $this->calculate_first_order_referral_discount($cart_subtotal);
            $discount_amount = $discount_value > 0 ? -$discount_value : 0;
            if ($discount_amount < 0) {
                $cart->add_fee(__('Referral Discount', 'intersoccer-referral'), $discount_amount, true, '');

                // Mark that we applied the first-order discount during this checkout attempt.
                // This is later copied to the order meta in maybe_mark_first_order_discount_on_order().
                $session->set('intersoccer_first_order_discount_pending', 'yes');
                $session->set('intersoccer_first_order_discount_code', strtoupper((string) $referral_code));
                $session->set('intersoccer_first_order_discount_amount', $discount_value);

                if (defined('WP_DEBUG') && WP_DEBUG) {
                    intersoccer_referral_log("InterSoccer Referral: Applying first-order referral discount - user=$current_user_id, code=$referral_code, discount=$discount_amount");
                }
            }
        } elseif ($referral_code && defined('WP_DEBUG') && WP_DEBUG) {
            intersoccer_referral_log("InterSoccer Referral: Skipping referral discount - user=$current_user_id, code=$referral_code, eligible=" . ($eligible_for_discount ? 'yes' : 'no'));
        }

        // Apply points discount
        $points_to_redeem = $session->get('intersoccer_points_to_redeem', 0);
        $is_checkout_context = (function_exists('is_checkout') && is_checkout());

        if (!$is_checkout_context && wp_doing_ajax() && isset($_REQUEST['wc-ajax'])) {
            $ajax_action = sanitize_text_field(wp_unslash($_REQUEST['wc-ajax']));
            if ($ajax_action === 'update_order_review') {
                $is_checkout_context = true;
            }
        }

        if ($is_checkout_context && $points_to_redeem > 0) {
            $discount_amount = -$points_to_redeem; // Negative fee for discount
            $cart->add_fee(__('Referral Credits Discount', 'intersoccer-referral'), $discount_amount, true, '');
            
            // Debug logging (only in debug mode to prevent excessive disk I/O)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                intersoccer_referral_log("InterSoccer Referral: Applying points discount as fee - points=$points_to_redeem, discount=$discount_amount");
            }
        }
    }

    private function customer_is_eligible_for_first_order_discount($user_id) {
        if (empty($user_id) || !is_user_logged_in()) {
            return false;
        }

        $consumed = get_user_meta($user_id, 'intersoccer_first_order_discount_consumed', true);
        if (!empty($consumed)) {
            return false;
        }

        if (!function_exists('wc_get_orders')) {
            return true;
        }

        $orders = wc_get_orders([
            'customer' => $user_id,
            'status' => [
                'wc-processing',
                'processing',
                'wc-completed',
                'completed',
                'wc-on-hold',
                'on-hold',
            ],
            'limit' => 1,
            'return' => 'ids',
        ]);

        return empty($orders);
    }

    /**
     * Percentage off first order when a referral code is applied (default 10%).
     */
    private function get_first_order_referral_discount_percent() {
        $percent = (float) get_option('intersoccer_new_customer_discount', 10);
        return max(0, min(100, $percent));
    }

    /**
     * Calculate first-order referral discount from cart subtotal.
     */
    private function calculate_first_order_referral_discount($cart_subtotal) {
        $subtotal = max(0, (float) $cart_subtotal);
        $percent = $this->get_first_order_referral_discount_percent();
        return round($subtotal * ($percent / 100), 2);
    }

    /**
     * If the first-order discount was applied in cart fees, copy a marker to the order.
     * This lets us \"consume\" the one-time benefit only when the order reaches a successful status.
     *
     * @param WC_Order $order
     * @param array    $data
     * @return void
     */
    public function maybe_mark_first_order_discount_on_order($order, $data) {
        if (!function_exists('WC') || !WC()->session) {
            return;
        }

        $session = WC()->session;
        $pending = $session->get('intersoccer_first_order_discount_pending');
        if ($pending !== 'yes') {
            return;
        }

        if (!is_object($order) || !method_exists($order, 'update_meta_data')) {
            return;
        }

        $code = strtoupper((string) $session->get('intersoccer_first_order_discount_code'));
        $amount = (float) $session->get('intersoccer_first_order_discount_amount', 0);

        $order->update_meta_data('_intersoccer_first_order_discount_applied', 1);
        $order->update_meta_data('_intersoccer_first_order_discount_code', $code);
        $order->update_meta_data('_intersoccer_first_order_discount_amount', $amount);
        $order->update_meta_data('_intersoccer_first_order_discount_applied_at', current_time('mysql'));

        // Clear pending markers; if payment fails/cancels, a later attempt can still apply again
        // because we only \"consume\" on a successful order status.
        $session->set('intersoccer_first_order_discount_pending', 'no');
    }

    /**
     * Consume the first-order discount only once the order becomes successful.
     *
     * Policy: first successful order is when status reaches processing, on-hold, or completed.
     * If an order fails/cancels before success, the customer can receive the discount again later.
     *
     * @param int         $order_id
     * @param string      $old_status
     * @param string      $new_status
     * @param WC_Order|null $order
     * @return void
     */
    public function maybe_consume_first_order_discount($order_id, $old_status, $new_status, $order = null) {
        $normalized = ltrim((string) $new_status, 'wc-');
        if (!in_array($normalized, ['processing', 'on-hold', 'completed'], true)) {
            return;
        }

        if (!$order || !is_object($order)) {
            $order = function_exists('wc_get_order') ? wc_get_order($order_id) : null;
        }

        if (!$order || !method_exists($order, 'get_customer_id') || !method_exists($order, 'get_meta')) {
            return;
        }

        $applied = $order->get_meta('_intersoccer_first_order_discount_applied', true);
        if (empty($applied)) {
            return;
        }

        $customer_id = (int) $order->get_customer_id();
        if ($customer_id <= 0) {
            return;
        }

        update_user_meta($customer_id, 'intersoccer_first_order_discount_consumed', 1);
        update_user_meta($customer_id, 'intersoccer_first_order_discount_consumed_order_id', (int) $order_id);
        update_user_meta($customer_id, 'intersoccer_first_order_discount_consumed_at', current_time('mysql'));
    }

    /**
     * Display points used in order history
     */
    public function display_points_used_in_orders($order) {
        // This would modify the order total column to show points used
        // Implementation depends on WooCommerce hooks
    }

    private function get_checkout_referral_context() {
        $context = [
            'prefill_code' => '',
            'applied_code' => '',
            'auto_apply' => false,
            'source' => 'none'
        ];

        if (function_exists('WC') && WC()->session) {
            $session = WC()->session;
            $session_payload = $session->get('intersoccer_referral');
            $context['prefill_code'] = $this->normalize_referral_code_source($session_payload);
            $context['applied_code'] = $this->normalize_referral_code_source(
                $session->get('intersoccer_applied_referral_code')
            );
            if (!empty($context['prefill_code'])) {
                $context['source'] = 'session';
            }
            $context['status_message'] = $session->get('intersoccer_referral_status_message');
        }

        if (empty($context['prefill_code']) && isset($_COOKIE['intersoccer_referral'])) {
            $context['prefill_code'] = $this->normalize_referral_code_source($_COOKIE['intersoccer_referral']);
            if (!empty($context['prefill_code'])) {
                $context['source'] = 'cookie';
            }
        }

        if (!empty($context['applied_code'])) {
            $context['prefill_code'] = $context['applied_code'];
        } elseif (!empty($context['prefill_code'])) {
            $context['auto_apply'] = true;
        }

        if ($context['auto_apply'] && !empty($context['prefill_code'])) {
            $autoAppliedKey = 'intersoccer_referral_auto_applied_' . md5($context['prefill_code']);
            $session_applied = (function_exists('WC') && WC()->session) ? WC()->session->get($autoAppliedKey) : null;

            if (!$session_applied) {
                $result = $this->apply_coach_referral_code_internal($context['prefill_code'], [
                    'recalculate' => true,
                    'context' => 'auto-apply',
                    'silent' => true
                ]);

                if ($result['success']) {
                    $context['applied_code'] = strtoupper($context['prefill_code']);
                    $context['prefill_code'] = $context['applied_code'];
                    $context['auto_apply'] = false;
                    $context['status_message'] = $result['message'];

                    if (function_exists('WC') && WC()->session) {
                        WC()->session->set('intersoccer_referral_status_message', $result['message']);
                        WC()->session->set($autoAppliedKey, time());
                    }
                }
            }
        }

        return $context;
    }

    private function normalize_referral_code_source($source) {
        if (empty($source)) {
            return '';
        }

        if (is_array($source)) {
            return strtoupper(sanitize_text_field($source['code'] ?? ''));
        }

        if (is_string($source)) {
            $decoded = json_decode(stripslashes($source), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->normalize_referral_code_source($decoded);
            }

            return strtoupper(sanitize_text_field($source));
        }

        return '';
    }

    private function apply_coach_referral_code_internal($referral_code, array $args = []) {
        $defaults = [
            'recalculate' => false,
            'context' => 'manual',
            'silent' => false,
        ];

        $args = array_merge($defaults, $args);
        $referral_code = strtoupper(trim($referral_code));

        if ($referral_code === '') {
            return [
                'success' => false,
                'message' => __('Please enter a referral code.', 'intersoccer-referral')
            ];
        }

        if (!is_user_logged_in()) {
            return [
                'success' => false,
                'message' => __('Please log in before applying a referral code.', 'intersoccer-referral')
            ];
        }

        if (!function_exists('WC') || !WC()->session) {
            return [
                'success' => false,
                'message' => __('Unable to access your checkout session. Please refresh and try again.', 'intersoccer-referral')
            ];
        }

        $session = WC()->session;

        $existing_applied_code = strtoupper((string) $session->get('intersoccer_applied_referral_code'));
        if ($existing_applied_code && $existing_applied_code !== $referral_code) {
            do_action('intersoccer_referral_code_invalid', $referral_code, 'code_already_applied');
            return [
                'success' => false,
                'message' => __('A different referral code is already applied to this order.', 'intersoccer-referral')
            ];
        }

        $customer_referral_code = strtoupper((string) $session->get('customer_referral_code'));
        $partner_referral_code = strtoupper((string) $session->get('partner_referral_code'));
        $influencer_referral_code = strtoupper((string) $session->get('influencer_referral_code'));
        $affiliate_referral_code = strtoupper((string) $session->get('affiliate_referral_code'));
        $existing_coach_session_code = strtoupper((string) $session->get('coach_referral_code'));

        $conflicts = [];
        if ($customer_referral_code && $customer_referral_code !== $referral_code) {
            $conflicts[] = __('friend referral code', 'intersoccer-referral');
        }
        if ($partner_referral_code) {
            $conflicts[] = __('partner referral code', 'intersoccer-referral');
        }
        if ($influencer_referral_code) {
            $conflicts[] = __('influencer referral code', 'intersoccer-referral');
        }
        if ($affiliate_referral_code) {
            $conflicts[] = __('affiliate referral code', 'intersoccer-referral');
        }
        if ($existing_coach_session_code && $existing_coach_session_code !== $referral_code) {
            $conflicts[] = __('a different coach referral code', 'intersoccer-referral');
        }

        if (!empty($conflicts)) {
            return [
                'success' => false,
                'message' => sprintf(
                    __('Another referral code is already applied (%s). Please remove it before using a coach code.', 'intersoccer-referral'),
                    implode(', ', $conflicts)
                )
            ];
        }

        $coaches = get_users([
            'role' => 'coach',
            'meta_key' => 'referral_code',
            'meta_value' => $referral_code,
            'number' => 1
        ]);

        if (!empty($coaches)) {
            $coach = $coaches[0];
            $coach_name = trim(($coach->first_name ?? '') . ' ' . ($coach->last_name ?? ''));
            if ($coach_name === '') {
                $coach_name = $coach->display_name;
            }

            $current_user_id = get_current_user_id();
            if ((int) $coach->ID === (int) $current_user_id) {
                do_action('intersoccer_referral_code_invalid', $referral_code, 'self_referral');
                return [
                    'success' => false,
                    'message' => __('You cannot use your own referral code.', 'intersoccer-referral')
                ];
            }

            $session->set('intersoccer_applied_referral_code', $referral_code);
            $session->set('intersoccer_referral_coach_id', $coach->ID);
            $session->set('coach_referral_code', $referral_code);
            $session->set('intersoccer_referral', ['code' => $referral_code, 'event_id' => null, 'coach_event_id' => null, 'set_at' => time()]);

            $eligible_for_discount = $this->customer_is_eligible_for_first_order_discount($current_user_id);

            if ($eligible_for_discount) {
                $status_message = sprintf(
                    __('Referral code applied! You will receive a discount from coach %s.', 'intersoccer-referral'),
                    $coach_name
                );
            } else {
                $status_message = sprintf(
                    __('Referral code saved! Coach %s will still receive credit on this order. First-time discount already used.', 'intersoccer-referral'),
                    $coach_name
                );
            }

            $session->set('intersoccer_referral_status_message', $status_message);
            $session->set('intersoccer_first_order_discount_available', $eligible_for_discount ? 'yes' : 'no');

            update_user_meta(get_current_user_id(), 'intersoccer_preferred_coach', $coach->ID);

            if (!empty($args['recalculate']) && WC()->cart) {
                WC()->cart->calculate_totals();
                WC()->cart->set_session();
            }

            do_action('intersoccer_referral_code_used', $referral_code, get_current_user_id(), $coach->ID);

            $ajax_cart_subtotal = (WC()->cart && method_exists(WC()->cart, 'get_subtotal')) ? (float) WC()->cart->get_subtotal() : 0;
            $ajax_discount_amount = $eligible_for_discount
                ? $this->calculate_first_order_referral_discount($ajax_cart_subtotal)
                : 0;
            return [
                'success' => true,
                'message' => $status_message,
                'coach_name' => $coach_name,
                'discount_amount' => $ajax_discount_amount
            ];
        }

        // Customer referral code (friend referral): lookup by intersoccer_customer_referral_code
        $customers_by_code = get_users([
            'meta_key' => 'intersoccer_customer_referral_code',
            'meta_value' => $referral_code,
            'number' => 1
        ]);
        if (empty($customers_by_code)) {
            $customers_by_code = get_users([
                'meta_key' => 'intersoccer_customer_referral_code',
                'meta_value' => strtolower($referral_code),
                'number' => 1
            ]);
        }

        if (!empty($customers_by_code)) {
            $referrer_customer = $customers_by_code[0];
            $referrer_name = trim(($referrer_customer->first_name ?? '') . ' ' . ($referrer_customer->last_name ?? ''));
            if ($referrer_name === '') {
                $referrer_name = $referrer_customer->display_name;
            }

            $current_user_id = get_current_user_id();
            if ((int) $referrer_customer->ID === (int) $current_user_id) {
                do_action('intersoccer_referral_code_invalid', $referral_code, 'self_referral');
                return [
                    'success' => false,
                    'message' => __('You cannot use your own referral code.', 'intersoccer-referral')
                ];
            }

            $session->set('intersoccer_applied_referral_code', $referral_code);
            $session->set('intersoccer_referral', ['code' => $referral_code, 'event_id' => null, 'coach_event_id' => null, 'set_at' => time()]);
            $eligible_for_discount = $this->customer_is_eligible_for_first_order_discount($current_user_id);

            if ($eligible_for_discount) {
                $status_message = sprintf(
                    __('Referral code applied! You will receive a discount. Your friend %s will earn points on this order.', 'intersoccer-referral'),
                    $referrer_name
                );
            } else {
                $status_message = sprintf(
                    __('Referral code saved! %s will still earn points on this order. First-time discount already used.', 'intersoccer-referral'),
                    $referrer_name
                );
            }

            $session->set('intersoccer_referral_status_message', $status_message);
            $session->set('intersoccer_first_order_discount_available', $eligible_for_discount ? 'yes' : 'no');

            if (!empty($args['recalculate']) && WC()->cart) {
                WC()->cart->calculate_totals();
                WC()->cart->set_session();
            }

            do_action('intersoccer_referral_code_used', $referral_code, get_current_user_id(), $referrer_customer->ID);

            $ajax_cart_subtotal = (WC()->cart && method_exists(WC()->cart, 'get_subtotal')) ? (float) WC()->cart->get_subtotal() : 0;
            $ajax_discount_amount = $eligible_for_discount
                ? $this->calculate_first_order_referral_discount($ajax_cart_subtotal)
                : 0;
            return [
                'success' => true,
                'message' => $status_message,
                'coach_name' => $referrer_name,
                'discount_amount' => $ajax_discount_amount
            ];
        }

        do_action('intersoccer_referral_code_invalid', $referral_code, 'code_not_found');
        return [
            'success' => false,
            'message' => __('Invalid referral code.', 'intersoccer-referral')
        ];
    }
}