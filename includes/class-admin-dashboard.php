<?php
// includes/class-admin-dashboard.php

class InterSoccer_Referral_Admin_Dashboard {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_populate_demo_data', [$this, 'populate_demo_data']);
        add_action('wp_ajax_clear_demo_data', [$this, 'clear_demo_data']);
        add_action('admin_init', [$this, 'handle_settings']);
        add_action('wp_ajax_send_coach_message', [$this, 'send_coach_message']);
        add_action('wp_ajax_deactivate_coach', [$this, 'deactivate_coach']);
        add_action('wp_ajax_assign_venue', [$this, 'assign_venue']);
    }

    public function add_admin_menus() {
        // Main menu
        add_menu_page(
            'InterSoccer Referrals',
            'Referrals',
            'manage_options',
            'intersoccer-referrals',
            [$this, 'render_main_dashboard'],
            'dashicons-money-alt', // Changed from dashicons-groups
            30
        );

        // Submenus
        add_submenu_page(
            'intersoccer-referrals',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'intersoccer-referrals',
            [$this, 'render_main_dashboard']
        );

        add_submenu_page(
            'intersoccer-referrals',
            'Coaches',
            'Coaches',
            'manage_options',
            'intersoccer-coaches',
            [$this, 'render_coaches_page']
        );

        add_submenu_page(
            'intersoccer-referrals',
            'Referrals',
            'Referrals',
            'manage_options',
            'intersoccer-referral-list',
            [$this, 'render_referrals_page']
        );

        add_submenu_page(
            'intersoccer-referrals',
            'Settings',
            'Settings',
            'manage_options',
            'intersoccer-settings',
            [$this, 'render_settings_page']
        );
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'intersoccer') !== false) {
            wp_enqueue_style('intersoccer-admin-css', INTERSOCCER_REFERRAL_URL . 'assets/css/admin-dashboard.css', [], INTERSOCCER_REFERRAL_VERSION);
            wp_enqueue_script('intersoccer-admin-js', INTERSOCCER_REFERRAL_URL . 'assets/js/admin-dashboard.js', ['jquery', 'chart-js'], INTERSOCCER_REFERRAL_VERSION, true);
            wp_enqueue_script('chart-js', 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js', [], '3.9.1');
            
            wp_localize_script('intersoccer-admin-js', 'intersoccer_ajax', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('intersoccer_admin_nonce')
            ]);
        }
    }

    public function render_main_dashboard() {
        $stats = $this->get_dashboard_stats();
        $recent_referrals = $this->get_recent_referrals(10);
        $top_coaches = $this->get_top_coaches(5);
        
        ?>
        <div class="wrap intersoccer-admin">
            <h1 class="wp-heading-inline">InterSoccer Referral Dashboard</h1>
            
            <div class="intersoccer-demo-actions">
                <button id="populate-demo-data" class="button button-secondary">
                    <span class="dashicons dashicons-database-add"></span>
                    Populate Demo Data
                </button>
                <button id="clear-demo-data" class="button button-secondary">
                    <span class="dashicons dashicons-trash"></span>
                    Clear Demo Data
                </button>
            </div>
            <form method="get" class="intersoccer-filter-form">
                <input type="hidden" name="page" value="intersoccer-referrals">
                <label for="date_range">Date Range:</label>
                <select name="date_range" id="date_range">
                    <option value="this_month" <?php selected($_GET['date_range'] ?? '', 'this_month'); ?>>This Month</option>
                    <option value="last_30_days" <?php selected($_GET['date_range'] ?? '', 'last_30_days'); ?>>Last 30 Days</option>
                    <option value="all_time" <?php selected($_GET['date_range'] ?? '', 'all_time'); ?>>All Time</option>
                </select>
                <label for="coach_id">Coach:</label>
                <select name="coach_id" id="coach_id">
                    <option value="">All Coaches</option>
                    <?php
                    $coaches = get_users(['role' => 'coach']);
                    foreach ($coaches as $coach) {
                        echo '<option value="' . $coach->ID . '" ' . selected($_GET['coach_id'] ?? 0, $coach->ID, false) . '>' . esc_html($coach->display_name) . '</option>';
                    }
                    ?>
                </select>
                <label for="status">Status:</label>
                <select name="status" id="status">
                    <option value="all" <?php selected($_GET['status'] ?? '', 'all'); ?>>All Statuses</option>
                    <option value="pending" <?php selected($_GET['status'] ?? '', 'pending'); ?>>Pending</option>
                    <option value="completed" <?php selected($_GET['status'] ?? '', 'completed'); ?>>Paid</option>
                    <option value="processing" <?php selected($_GET['status'] ?? '', 'processing'); ?>>Processing</option>
                </select>
                <button type="submit" class="button">Filter</button>
                <a href="<?php echo admin_url('admin.php?page=intersoccer-referrals&action=export_referral_report'); ?>" class="button button-primary">Export CSV</a>
            </form>
            <!-- Stats Cards -->
            <div class="intersoccer-stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_coaches']); ?></h3>
                        <p>Active Coaches</p>
                        <span class="stat-change positive">+<?php echo $stats['new_coaches_this_month']; ?> this month</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-businessman"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_referrals']); ?></h3>
                        <p>Total Referrals</p>
                        <span class="stat-change positive">+<?php echo $stats['new_referrals_this_month']; ?> this month</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['total_commissions'], 2); ?> CHF</h3>
                        <p>Total Commissions</p>
                        <span class="stat-change positive">+<?php echo number_format($stats['commissions_this_month'], 2); ?> CHF this month</span>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo number_format($stats['conversion_rate'], 1); ?>%</h3>
                        <p>Conversion Rate</p>
                        <span class="stat-change <?php echo $stats['conversion_change'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo ($stats['conversion_change'] >= 0 ? '+' : '') . number_format($stats['conversion_change'], 1); ?>% vs last month
                        </span>
                    </div>
                </div>
            </div>

            <div class="intersoccer-dashboard-grid">
                <!-- Performance Chart -->
                <div class="dashboard-widget chart-widget">
                    <h2>Referral Performance</h2>
                    <canvas id="performanceChart" width="400" height="200"></canvas>
                </div>

                <!-- Top Coaches -->
                <div class="dashboard-widget">
                    <h2>Top Performing Coaches</h2>
                    <div class="coach-leaderboard">
                        <?php foreach ($top_coaches as $index => $coach): ?>
                        <div class="coach-item">
                            <div class="coach-rank"><?php echo $index + 1; ?></div>
                            <div class="coach-avatar">
                                <?php echo get_avatar($coach->coach_id, 40); ?>
                            </div>
                            <div class="coach-info">
                                <strong><?php echo esc_html($coach->display_name); ?></strong>
                                <span class="coach-stats"><?php echo $coach->referral_count; ?> referrals | <?php echo number_format($coach->total_commission, 2); ?> CHF</span>
                            </div>
                            <div class="coach-badge">
                                <?php echo $this->get_coach_tier_badge($coach->referral_count); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="dashboard-widget">
                    <h2>Recent Referrals</h2>
                    <div class="recent-activity">
                        <?php foreach ($recent_referrals as $referral): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <span class="dashicons dashicons-plus-alt"></span>
                            </div>
                            <div class="activity-content">
                                <p><strong><?php echo esc_html($referral->coach_name); ?></strong> earned <?php echo number_format($referral->commission_amount, 2); ?> CHF</p>
                                <span class="activity-time"><?php echo human_time_diff(strtotime($referral->created_at), current_time('timestamp')); ?> ago</span>
                            </div>
                            <div class="activity-amount">
                                +<?php echo number_format($referral->commission_amount, 2); ?> CHF
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="dashboard-widget">
                    <h2>Quick Actions</h2>
                    <div class="quick-actions">
                        <a href="<?php echo admin_url('admin.php?page=intersoccer-coaches'); ?>" class="quick-action-btn">
                            <span class="dashicons dashicons-groups"></span>
                            Manage Coaches
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=intersoccer-referral-list'); ?>" class="quick-action-btn">
                            <span class="dashicons dashicons-list-view"></span>
                            View All Referrals
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=intersoccer-settings'); ?>" class="quick-action-btn">
                            <span class="dashicons dashicons-admin-settings"></span>
                            Settings
                        </a>
                        <button class="quick-action-btn" id="export-data">
                            <span class="dashicons dashicons-download"></span>
                            Export Data
                        </button>
                    </div>
                </div>
            </div>

            <!-- Performance Data for Chart -->
            <script type="text/javascript">
                var performanceData = <?php echo json_encode($this->get_performance_chart_data()); ?>;
            </script>
        </div>
        <?php
    }

    public function render_coaches_page() {
        require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-coach-list-table.php';
        $table = new InterSoccer_Coach_List_Table();
        $table->prepare_items();
        ?>
        <div class="wrap intersoccer-admin">
            <h1>Coach Management</h1>
            <form method="get">
                <input type="hidden" name="page" value="intersoccer-coaches">
                <p class="search-box">
                    <label class="screen-reader-text" for="coach-search-input">Search Coaches:</label>
                    <input type="search" id="coach-search-input" name="s" value="<?php echo esc_attr($_REQUEST['s'] ?? ''); ?>">
                    <input type="submit" class="button" value="Search Coaches">
                </p>
                <label>Tier:</label>
                <select name="tier">
                    <option value="">All Tiers</option>
                    <option value="Bronze" <?php selected($_REQUEST['tier'] ?? '', 'Bronze'); ?>>Bronze</option>
                    <option value="Silver" <?php selected($_REQUEST['tier'] ?? '', 'Silver'); ?>>Silver</option>
                    <option value="Gold" <?php selected($_REQUEST['tier'] ?? '', 'Gold'); ?>>Gold</option>
                    <option value="Platinum" <?php selected($_REQUEST['tier'] ?? '', 'Platinum'); ?>>Platinum</option>
                </select>
                <label>Venue:</label>
                <select name="venue">
                    <option value="">All Venues</option>
                    <?php
                    $venues = get_posts(['post_type' => 'intersoccer_venue', 'numberposts' => -1]);
                    foreach ($venues as $venue) {
                        echo '<option value="' . $venue->ID . '" ' . selected($_REQUEST['venue'] ?? 0, $venue->ID, false) . '>' . esc_html($venue->post_title) . '</option>';
                    }
                    ?>
                </select>
                <input type="submit" class="button" value="Filter">
                <a href="<?php echo admin_url('admin.php?page=intersoccer-coaches&action=export_coaches'); ?>" class="button button-primary">Export CSV</a>
            </form>
            <form method="post">
                <?php $table->display(); ?>
            </form>
            <div id="message-modal" style="display:none;">
                <div class="modal-content">
                    <h2>Send Message to Coaches</h2>
                    <form id="bulk-message-form">
                        <input type="hidden" name="coach_ids" id="message-coach-ids">
                        <label>Subject:</label>
                        <input type="text" name="subject" required>
                        <label>Message:</label>
                        <textarea name="content" rows="5" required></textarea>
                        <button type="submit" class="button button-primary">Send</button>
                        <button type="button" class="button modal-close">Cancel</button>
                    </form>
                </div>
            </div>
            <div id="venue-modal" style="display:none;">
                <div class="modal-content">
                    <h2>Assign Venue</h2>
                    <form id="bulk-venue-form">
                        <input type="hidden" name="coach_ids" id="venue-coach-ids">
                        <label>Venue:</label>
                        <select name="venue_id" required>
                            <?php foreach ($venues as $venue) {
                                echo '<option value="' . $venue->ID . '">' . esc_html($venue->post_title) . '</option>';
                            } ?>
                        </select>
                        <button type="submit" class="button button-primary">Assign</button>
                        <button type="button" class="button modal-close">Cancel</button>
                    </form>
                </div>
            </div>
        </div>
        <script>
        jQuery(document).ready(function($) {
            $('.send-message').on('click', function(e) {
                e.preventDefault();
                $('#message-coach-ids').val($(this).data('coach-id'));
                $('#message-modal').show();
            });
            $('.deactivate-coach').on('click', function(e) {
                e.preventDefault();
                if (confirm('Deactivate this coach?')) {
                    $.post({
                        url: intersoccer_ajax.ajax_url,
                        data: {
                            action: 'deactivate_coach',
                            nonce: intersoccer_ajax.nonce,
                            coach_id: $(this).data('coach-id')
                        },
                        success: function(res) {
                            if (res.success) location.reload();
                            else alert(res.data.message);
                        }
                    });
                }
            });
            $('.modal-close').on('click', function() {
                $(this).closest('.modal').hide();
            });
            $('#bulk-message-form').on('submit', function(e) {
                e.preventDefault();
                $.post({
                    url: intersoccer_ajax.ajax_url,
                    data: {
                        action: 'send_coach_message',
                        nonce: intersoccer_ajax.nonce,
                        coach_ids: $('#message-coach-ids').val(),
                        subject: $('input[name="subject"]').val(),
                        content: $('textarea[name="content"]').val()
                    },
                    success: function(res) {
                        alert(res.data.message);
                        if (res.success) location.reload();
                    }
                });
            });
            $('#bulk-venue-form').on('submit', function(e) {
                e.preventDefault();
                $.post({
                    url: intersoccer_ajax.ajax_url,
                    data: {
                        action: 'assign_venue',
                        nonce: intersoccer_ajax.nonce,
                        coach_ids: $('#venue-coach-ids').val(),
                        venue_id: $('select[name="venue_id"]').val()
                    },
                    success: function(res) {
                        alert(res.data.message);
                        if (res.success) location.reload();
                    }
                });
            });
            $('.bulk-action-apply-button').on('click', function() {
                var action = $(this).closest('.tablenav').find('select[name="action"]').val();
                var ids = $('input[name="coach_ids[]"]:checked').map(function() { return $(this).val(); }).get().join(',');
                if (action === 'send_message') {
                    $('#message-coach-ids').val(ids);
                    $('#message-modal').show();
                } else if (action === 'assign_venue') {
                    $('#venue-coach-ids').val(ids);
                    $('#venue-modal').show();
                }
            });
        });
        </script>
        <?php
    }

    public function send_coach_message() {
        check_ajax_referer('intersoccer_admin_nonce');
        $coach_ids = array_map('absint', explode(',', $_POST['coach_ids']));
        $subject = sanitize_text_field($_POST['subject']);
        $content = wp_kses_post($_POST['content']);
        foreach ($coach_ids as $coach_id) {
            $coach = get_user_by('ID', $coach_id);
            if ($coach) {
                wp_mail($coach->user_email, $subject, $content);
                error_log('Message sent to coach ID: ' . $coach_id);
            }
        }
        wp_send_json_success(['message' => 'Messages sent!']);
    }

    public function deactivate_coach() {
        check_ajax_referer('intersoccer_admin_nonce');
        $coach_id = absint($_POST['coach_id']);
        $user = get_user_by('ID', $coach_id);
        if ($user && in_array('coach', $user->roles)) {
            wp_update_user(['ID' => $coach_id, 'role' => 'subscriber']);
            error_log('Deactivated coach ID: ' . $coach_id);
            wp_send_json_success(['message' => 'Coach deactivated']);
        }
        wp_send_json_error(['message' => 'Invalid coach ID']);
    }

    public function assign_venue() {
        check_ajax_referer('intersoccer_admin_nonce');
        $coach_ids = array_map('absint', explode(',', $_POST['coach_ids']));
        $venue_id = absint($_POST['venue_id']);
        foreach ($coach_ids as $coach_id) {
            $venues = get_user_meta($coach_id, 'intersoccer_venues', true) ?: [];
            if (!in_array($venue_id, $venues)) {
                $venues[] = $venue_id;
                update_user_meta($coach_id, 'intersoccer_venues', $venues);
                error_log('Assigned venue ' . $venue_id . ' to coach ' . $coach_id);
            }
        }
        wp_send_json_success(['message' => 'Venues assigned']);
    }

    public function export_coaches() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        $coaches = $wpdb->get_results("
            SELECT 
                u.display_name AS coach_name,
                u.user_email AS email,
                COALESCE(r.referral_count, 0) AS referrals,
                COALESCE(um.meta_value, 0) AS credits,
                (SELECT GROUP_CONCAT(p.post_title) FROM {$wpdb->posts} p WHERE p.ID IN (SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = u.ID AND meta_key = 'intersoccer_venues')) AS venues
            FROM {$wpdb->users} u
            LEFT JOIN (
                SELECT coach_id, COUNT(*) as referral_count
                FROM $table_name 
                GROUP BY coach_id
            ) r ON u.ID = r.coach_id
            LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'intersoccer_credits'
            WHERE EXISTS (
                SELECT 1 FROM {$wpdb->usermeta} WHERE user_id = u.ID AND meta_key = 'wp_capabilities' AND meta_value LIKE '%coach%'
            )
        ");
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="InterSoccer_Coaches_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Coach Name', 'Email', 'Referrals', 'Credits (CHF)', 'Venues']);
        foreach ($coaches as $coach) {
            fputcsv($output, [
                $coach->coach_name,
                $coach->email,
                $coach->referrals,
                number_format($coach->credits, 2),
                $coach->venues ?: ''
            ]);
        }
        fclose($output);
        exit;
    }

    public function render_referrals_page() {
        // Implementation for referrals listing page
        ?>
        <div class="wrap intersoccer-admin">
            <h1>All Referrals</h1>
            <!-- Referrals table implementation -->
        </div>
        <?php
    }

    public function render_settings_page() {
        ?>
        <div class="wrap intersoccer-admin">
            <h1>Referral System Settings</h1>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_coaches">
                <h2>Import Coaches</h2>
                <p>Upload a CSV with columns: first_name, last_name, email</p>
                <input type="file" name="coach_csv" accept=".csv" required>
                <button type="submit" class="button button-primary">Import Coaches</button>
                <?php
                settings_fields('intersoccer_settings');
                do_settings_sections('intersoccer_settings');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Commission Rate (First Purchase)</th>
                        <td>
                            <input type="number" name="intersoccer_commission_first" value="<?php echo get_option('intersoccer_commission_first', 15); ?>" step="0.1" min="0" max="100" />%
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Commission Rate (Second Purchase)</th>
                        <td>
                            <input type="number" name="intersoccer_commission_second" value="<?php echo get_option('intersoccer_commission_second', 7.5); ?>" step="0.1" min="0" max="100" />%
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Commission Rate (Third+ Purchase)</th>
                        <td>
                            <input type="number" name="intersoccer_commission_third" value="<?php echo get_option('intersoccer_commission_third', 5); ?>" step="0.1" min="0" max="100" />%
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function import_coaches_from_csv() {
        if (!current_user_can('manage_options') || !isset($_FILES['coach_csv'])) {
            wp_die('Unauthorized or no file uploaded');
        }
        $file = $_FILES['coach_csv']['tmp_name'];
        if (($handle = fopen($file, 'r')) !== false) {
            $header = fgetcsv($handle);
            while (($data = fgetcsv($handle)) !== false) {
                $coach_data = array_combine($header, $data);
                $user_id = wp_create_user(
                    sanitize_title($coach_data['first_name'] . '_' . $coach_data['last_name']),
                    wp_generate_password(12),
                    sanitize_email($coach_data['email'])
                );
                if (!is_wp_error($user_id)) {
                    wp_update_user([
                        'ID' => $user_id,
                        'first_name' => sanitize_text_field($coach_data['first_name']),
                        'last_name' => sanitize_text_field($coach_data['last_name']),
                        'display_name' => sanitize_text_field($coach_data['first_name'] . ' ' . $coach_data['last_name']),
                        'role' => 'coach'
                    ]);
                    InterSoccer_Referral_Handler::generate_coach_referral_link($user_id);
                    wp_new_user_notification($user_id, null, 'both');
                    error_log('Imported coach: ' . $coach_data['email']);
                }
            }
            fclose($handle);
        }
        wp_redirect(admin_url('admin.php?page=intersoccer-settings&imported=1'));
        exit;
    }

    // AJAX handler for populating demo data
    public function populate_demo_data() {
        if (!check_ajax_referer('intersoccer_admin_nonce', 'nonce', false)) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        try {
            $this->create_demo_coaches();
            $this->create_demo_customers();
            $this->create_demo_referrals();
            
            wp_send_json_success([
                'message' => 'Demo data populated successfully!',
                'coaches_created' => 10,
                'customers_created' => 25,
                'referrals_created' => 50
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error creating demo data: ' . $e->getMessage()]);
        }
    }

    public function clear_demo_data() {
        if (!check_ajax_referer('intersoccer_admin_nonce', 'nonce', false)) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        global $wpdb;
        
        try {
            // Clear referrals table
            $table_name = $wpdb->prefix . 'intersoccer_referrals';
            $wpdb->query("TRUNCATE TABLE $table_name");
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}intersoccer_coach_performance");
            $wpdb->query("TRUNCATE TABLE {$wpdb->prefix}intersoccer_coach_achievements");

            // Remove demo users
            $demo_users = get_users(['meta_key' => 'intersoccer_demo_user', 'meta_value' => '1']);
            foreach ($demo_users as $user) {
                wp_delete_user($user->ID);
            }
            
            wp_send_json_success(['message' => 'Demo data cleared successfully!']);
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Error clearing demo data: ' . $e->getMessage()]);
        }
    }

    private function create_demo_coaches() {
        $coach_names = [
            'Marcus Mueller', 'Sandra Weber', 'Thomas Fischer', 'Anna Schmidt',
            'Michael Schneider', 'Lisa Wagner', 'David Becker', 'Sarah Schulz',
            'Andreas Koch', 'Julia Richter'
        ];

        foreach ($coach_names as $name) {
            $names = explode(' ', $name);
            $username = strtolower($names[0] . '_' . $names[1]);
            $email = strtolower($names[0] . '.' . $names[1]) . '@intersoccer-demo.com';
            
            $user_id = wp_create_user($username, 'demo123', $email);
            if (!is_wp_error($user_id)) {
                wp_update_user([
                    'ID' => $user_id,
                    'display_name' => $name,
                    'first_name' => $names[0],
                    'last_name' => $names[1],
                    'role' => 'coach'
                ]);
                
                // Add demo user meta
                update_user_meta($user_id, 'intersoccer_demo_user', '1');
                update_user_meta($user_id, 'intersoccer_credits', rand(50, 500));
                
                // Generate referral code
                InterSoccer_Referral_Handler::generate_coach_referral_link($user_id);
            }
        }
    }

    private function create_demo_customers() {
        $customer_names = [
            'Max Mustermann', 'Anna Beispiel', 'Hans Test', 'Maria Demo',
            'Peter Sample', 'Eva Probe', 'Klaus Muster', 'Anja Beispiel'
        ];

        foreach ($customer_names as $name) {
            $names = explode(' ', $name);
            $username = strtolower($names[0] . '_' . $names[1] . '_' . rand(1000, 9999));
            $email = strtolower($names[0] . '.' . $names[1]) . rand(1, 100) . '@customer-demo.com';
            
            $user_id = wp_create_user($username, 'demo123', $email);
            if (!is_wp_error($user_id)) {
                wp_update_user([
                    'ID' => $user_id,
                    'display_name' => $name,
                    'first_name' => $names[0],
                    'last_name' => $names[1],
                    'role' => 'customer'
                ]);
                
                update_user_meta($user_id, 'intersoccer_demo_user', '1');
                update_user_meta($user_id, 'intersoccer_customer_credits', rand(0, 100));
            }
        }
    }

    private function create_demo_referrals() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        
        $coaches = get_users(['role' => 'coach', 'meta_key' => 'intersoccer_demo_user']);
        $customers = get_users(['role' => 'customer', 'meta_key' => 'intersoccer_demo_user']);
        
        for ($i = 0; $i < 50; $i++) {
            $coach = $coaches[array_rand($coaches)];
            $customer = $customers[array_rand($customers)];
            
            $wpdb->insert($table_name, [
                'coach_id' => $coach->ID,
                'customer_id' => $customer->ID,
                'order_id' => rand(1000, 9999),
                'commission_amount' => rand(10, 200),
                'status' => 'completed',
                'created_at' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 90) . ' days'))
            ]);
        }
    }

    private function get_dashboard_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        
        return [
            'total_coaches' => count(get_users(['role' => 'coach'])),
            'new_coaches_this_month' => count(get_users(['role' => 'coach', 'date_query' => [['after' => '1 month ago']]])),
            'total_referrals' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name"),
            'new_referrals_this_month' => $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"),
            'total_commissions' => $wpdb->get_var("SELECT SUM(commission_amount) FROM $table_name"),
            'commissions_this_month' => $wpdb->get_var("SELECT SUM(commission_amount) FROM $table_name WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"),
            'conversion_rate' => 15.8, // Placeholder
            'conversion_change' => 2.3   // Placeholder
        ];
    }

    private function get_recent_referrals($limit = 10) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT r.*, u.display_name as coach_name 
            FROM $table_name r 
            LEFT JOIN {$wpdb->users} u ON r.coach_id = u.ID 
            ORDER BY r.created_at DESC 
            LIMIT %d
        ", $limit));
    }

    private function get_top_coaches($limit = 5) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                r.coach_id,
                u.display_name,
                COUNT(r.id) as referral_count,
                SUM(r.commission_amount) as total_commission
            FROM $table_name r 
            LEFT JOIN {$wpdb->users} u ON r.coach_id = u.ID 
            GROUP BY r.coach_id 
            ORDER BY total_commission DESC 
            LIMIT %d
        ", $limit));
    }

    private function get_all_coaches() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        
        return $wpdb->get_results("
            SELECT 
                u.*,
                COALESCE(r.referral_count, 0) as referral_count,
                COALESCE(r.total_commission, 0) as total_commission,
                COALESCE(um.meta_value, 0) as credits
            FROM {$wpdb->users} u
            LEFT JOIN (
                SELECT 
                    coach_id,
                    COUNT(*) as referral_count,
                    SUM(commission_amount) as total_commission
                FROM $table_name 
                GROUP BY coach_id
            ) r ON u.ID = r.coach_id
            LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'intersoccer_credits'
            WHERE EXISTS (
                SELECT 1 FROM {$wpdb->usermeta} 
                WHERE user_id = u.ID AND meta_key = 'wp_capabilities' AND meta_value LIKE '%coach%'
            )
        ");
    }

    private function get_performance_chart_data() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i months"));
            $month = date('M Y', strtotime("-$i months"));
            
            $referrals = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM $table_name 
                WHERE created_at >= %s AND created_at < %s + INTERVAL 1 MONTH
            ", $date, $date));
            
            $commissions = $wpdb->get_var($wpdb->prepare("
                SELECT SUM(commission_amount) FROM $table_name 
                WHERE created_at >= %s AND created_at < %s + INTERVAL 1 MONTH
            ", $date, $date));
            
            $data[] = [
                'month' => $month,
                'referrals' => (int) $referrals,
                'commissions' => (float) $commissions
            ];
        }
        
        return $data;
    }

    private function get_coach_tier($referral_count) {
        if ($referral_count >= 20) return 'Platinum';
        if ($referral_count >= 10) return 'Gold';
        if ($referral_count >= 5) return 'Silver';
        return 'Bronze';
    }

    private function get_coach_tier_badge($referral_count) {
        $tier = $this->get_coach_tier($referral_count);
        $colors = [
            'Bronze' => '#CD7F32',
            'Silver' => '#C0C0C0', 
            'Gold' => '#FFD700',
            'Platinum' => '#E5E4E2'
        ];
        
        return '<span class="tier-badge" style="background-color: ' . $colors[$tier] . ';">' . $tier . '</span>';
    }

    public function handle_settings() {
        register_setting('intersoccer_settings', 'intersoccer_commission_first');
        register_setting('intersoccer_settings', 'intersoccer_commission_second');
        register_setting('intersoccer_settings', 'intersoccer_commission_third');
    }

    public function export_referral_report() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';

        // Handle filter parameters
        $date_range = $_GET['date_range'] ?? 'this_month';
        $coach_id = isset($_GET['coach_id']) ? absint($_GET['coach_id']) : 0;
        $status = sanitize_text_field($_GET['status'] ?? 'all');

        $where = "WHERE 1=1";
        $params = [];
        if ($date_range === 'last_30_days') {
            $where .= " AND r.created_at >= %s";
            $params[] = date('Y-m-d 00:00:00', strtotime('-30 days'));
        } elseif ($date_range === 'this_month') {
            $where .= " AND r.created_at >= %s AND r.created_at <= %s";
            $params[] = date('Y-m-01 00:00:00');
            $params[] = date('Y-m-t 23:59:59');
        }
        if ($coach_id) {
            $where .= " AND r.coach_id = %d";
            $params[] = $coach_id;
        }
        if ($status !== 'all') {
            $where .= " AND r.status = %s";
            $params[] = $status;
        }

        // Fetch referral data
        $referrals = $wpdb->get_results($wpdb->prepare("
            SELECT 
                cu.display_name AS coach_name,
                cu.user_email AS coach_email,
                u.display_name AS customer_name,
                u.user_email AS customer_email,
                r.order_id,
                r.created_at AS order_date,
                r.commission_amount,
                r.status,
                r.conversion_date AS payment_date,
                r.purchase_count,
                r.referral_code
            FROM $table_name r
            LEFT JOIN {$wpdb->users} cu ON r.coach_id = cu.ID
            LEFT JOIN {$wpdb->users} u ON r.customer_id = u.ID
            $where
            ORDER BY r.created_at DESC
        ", $params));

        // Fetch order details
        $data = [];
        $total_commission = 0;
        foreach ($referrals as $ref) {
            $order = wc_get_order($ref->order_id);
            if (!$order) continue;

            $products = [];
            foreach ($order->get_items() as $item) {
                $products[] = $item->get_name();
            }
            $customer_type = $ref->purchase_count == 1 ? 'New' : 'Returning';
            $discount = $order->get_total_discount() ?: 0;
            $commission_rate = $ref->purchase_count == 1 ? get_option('intersoccer_commission_first', 15) :
                              ($ref->purchase_count == 2 ? get_option('intersoccer_commission_second', 7.5) :
                              get_option('intersoccer_commission_third', 5));

            $data[] = [
                'Coach Name' => $ref->coach_name,
                'Coach Email' => $ref->coach_email,
                'Customer Name' => $ref->customer_name,
                'Customer Email' => $ref->customer_email,
                'Order ID' => $ref->order_id,
                'Order Date' => date_i18n('d.m.Y', strtotime($ref->order_date)),
                'Product Name' => implode(', ', $products),
                'Order Total (CHF)' => number_format($order->get_total(), 2),
                'Discount Applied' => number_format($discount, 2),
                'Commission Rate (%)' => number_format($commission_rate, 1),
                'Commission Amount (CHF)' => number_format($ref->commission_amount, 2),
                'Commission Status' => ucfirst($ref->status),
                'Payment Date' => $ref->payment_date ? date_i18n('d.m.Y', strtotime($ref->payment_date)) : '-',
                'Customer Type' => $customer_type,
                'Referral Source' => $ref->referral_code
            ];
            $total_commission += $ref->commission_amount;
        }

        // Add totals row
        $data[] = [
            'Coach Name' => 'TOTALS',
            'Coach Email' => '',
            'Customer Name' => '',
            'Customer Email' => '',
            'Order ID' => '',
            'Order Date' => '',
            'Product Name' => '',
            'Order Total (CHF)' => '',
            'Discount Applied' => '',
            'Commission Rate (%)' => '',
            'Commission Amount (CHF)' => number_format($total_commission, 2),
            'Commission Status' => '',
            'Payment Date' => '',
            'Customer Type' => '',
            'Referral Source' => ''
        ];

        // Output CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="InterSoccer_Referral_Report_' . date('Y-m-d') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }
    // add_action('wp_ajax_export_roi_report', [$this, 'export_roi_report']);
}