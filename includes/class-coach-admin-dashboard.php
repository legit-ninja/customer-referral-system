<?php
// Add this to your main plugin file or create a new class

class InterSoccer_Coach_Admin_Dashboard {

    /**
     * Get coach points balance
     */
    public static function get_coach_points_balance($coach_id = null) {
        if (!$coach_id) {
            $coach_id = get_current_user_id();
        }
        return (float) get_user_meta($coach_id, 'intersoccer_points_balance', true);
    }

    public function __construct() {
        // Redirect coaches to custom dashboard
        add_action('admin_init', [$this, 'redirect_coach_dashboard']);
        
        // Remove unwanted admin menu items for coaches
        add_action('admin_menu', [$this, 'remove_coach_menu_items'], 999);
        
        // Remove admin bar items for coaches
        add_action('wp_before_admin_bar_render', [$this, 'remove_coach_admin_bar_items']);
        
        // Custom dashboard widgets for coaches
        add_action('wp_dashboard_setup', [$this, 'setup_coach_dashboard_widgets']);
        
        // Enqueue coach-specific admin styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_coach_admin_styles']);

        // Hide WordPress admin notices for coaches
        add_action('admin_head', [$this, 'hide_admin_notices_for_coaches']);
        add_action('admin_init', [$this, 'remove_admin_notices_for_coaches']);

        // Coach dashboard tour
        add_action('wp_ajax_complete_tour', [$this, 'complete_tour']);
    }

    public static function generate_coach_referral_link($coach_id) {
        $code = get_user_meta($coach_id, 'referral_code', true);
        if (!$code) {
            $code = 'coach_' . $coach_id . '_' . wp_generate_password(6, false);
            update_user_meta($coach_id, 'referral_code', $code);
        }
        return home_url('/?ref=' . $code);
    }
    
    /**
     * Redirect coaches to their custom dashboard on login
     */
    public function redirect_coach_dashboard() {
        global $pagenow;
        
        if (current_user_can('coach') && !current_user_can('manage_options')) {
            // Redirect to coach dashboard instead of default admin
            if ($pagenow === 'index.php' && !isset($_GET['page'])) {
                wp_redirect(admin_url('admin.php?page=intersoccer-coach-dashboard'));
                exit;
            }
        }
    }
    
    /**
     * Remove unwanted admin menu items for coaches
     */
    public function remove_coach_menu_items() {
        if (current_user_can('coach') && !current_user_can('manage_options')) {
            // Remove core WordPress menus
            remove_menu_page('index.php'); // Default WP Dashboard
            remove_menu_page('edit.php');  
            remove_menu_page('upload.php'); 
            remove_menu_page('edit.php?post_type=page');
            remove_menu_page('edit-comments.php');
            remove_menu_page('themes.php');
            remove_menu_page('plugins.php');
            remove_menu_page('users.php');
            remove_menu_page('tools.php');
            remove_menu_page('options-general.php');
            remove_menu_page('theme-layouts'); // Custom slug for Theme Layouts
            remove_menu_page('woocommerce');
            remove_menu_page('edit.php?post_type=shop_order');
            remove_menu_page('edit.php?post_type=shop_coupon');
            remove_menu_page('edit.php?post_type=product');
            
            // Remove WooCommerce menus if present
            remove_menu_page('woocommerce');
            remove_menu_page('edit.php?post_type=shop_order');
            remove_menu_page('edit.php?post_type=shop_coupon');
            remove_menu_page('edit.php?post_type=product');

            // Remove ThemeRex Menu
            remove_menu_page('edit.php?post_type=cpt_layouts');

            // Remove Elementor Menu
            remove_menu_page('edit.php?post_type=elementor_library');

            // Remove Reports and Rosters
            remove_submenu_page('intersoccer-reports-rosters', 'intersoccer_render_plugin_overview_page');
            remove_submenu_page('intersoccer-reports-rosters', 'intersoccer_render_reports_page');
            // Remove Testimonials
            remove_menu_page('edit.php?post_type=cpt_testimonials');
            // Remove theme menus
            remove_menu_page('edit.php?post_type=cpt_services');
            remove_menu_page('edit.php?post_type=cpt_team');

            // Add custom coach dashboard as main menu
            add_menu_page(
                'Coach Dashboard',
                'My Dashboard',
                'read',
                'intersoccer-coach-dashboard',
                [$this, 'render_coach_admin_dashboard'],
                'dashicons-dashboard',
                2
            );
            
            // Add coach-specific submenus
            add_submenu_page(
                'intersoccer-coach-dashboard',
                'My Referrals',
                'My Referrals',
                'read',
                'intersoccer-coach-referrals',
                [$this, 'render_coach_referrals']
            );
            
            add_submenu_page(
                'intersoccer-coach-dashboard',
                'My Profile',
                'My Profile',
                'read',
                'intersoccer-coach-profile',
                [$this, 'render_coach_profile']
            );
            
            add_submenu_page(
                'intersoccer-coach-dashboard',
                'Resources',
                'Resources',
                'read',
                'intersoccer-coach-resources',
                [$this, 'render_coach_resources']
            );
        }
    }
    
    /**
     * Remove admin bar items for coaches
     */
    public function remove_coach_admin_bar_items() {
        if (current_user_can('coach') && !current_user_can('manage_options')) {
            global $wp_admin_bar;
            
            // Remove unwanted admin bar nodes
            $wp_admin_bar->remove_node('wp-logo');
            $wp_admin_bar->remove_node('about');
            $wp_admin_bar->remove_node('wporg');
            $wp_admin_bar->remove_node('documentation');
            $wp_admin_bar->remove_node('support-forums');
            $wp_admin_bar->remove_node('feedback');
            $wp_admin_bar->remove_node('comments');
            $wp_admin_bar->remove_node('new-content');
            $wp_admin_bar->remove_node('wpseo-menu');
            $wp_admin_bar->remove_node('updates'); // Remove updates menu
            
            // Add coach-specific admin bar items
            $wp_admin_bar->add_node([
                'id'    => 'coach-stats',
                'title' => '💰 Points: ' . number_format(self::get_coach_points_balance(), 0),
                'href'  => admin_url('admin.php?page=intersoccer-coach-dashboard'),
                'meta'  => ['class' => 'coach-points-display']
            ]);

            $wp_admin_bar->add_node([
                'id'    => 'coach-tier',
                'title' => '🏆 ' . intersoccer_get_coach_tier(),
                'href'  => admin_url('admin.php?page=intersoccer-coach-dashboard'),
                'meta'  => ['class' => 'coach-tier-display']
            ]);

            // Add venue assignments info
            if (class_exists('InterSoccer_Admin_Coach_Assignments')) {
                $assigned_venues = InterSoccer_Admin_Coach_Assignments::get_coach_accessible_venues(get_current_user_id());
                if (!empty($assigned_venues)) {
                    $venue_count = count($assigned_venues);
                    $venue_text = $venue_count === 1 ? $assigned_venues[0] : $venue_count . ' venues';
                    $wp_admin_bar->add_node([
                        'id'    => 'coach-venues',
                        'title' => '📍 ' . $venue_text,
                        'href'  => admin_url('admin.php?page=intersoccer-coach-dashboard'),
                        'meta'  => ['class' => 'coach-venues-display']
                    ]);
                }
            }
        }
    }
    
    /**
     * Setup custom dashboard widgets for coaches
     */
    public function setup_coach_dashboard_widgets() {
        if (current_user_can('coach') && !current_user_can('manage_options')) {
            // Remove default dashboard widgets
            remove_meta_box('dashboard_primary', 'dashboard', 'side');
            remove_meta_box('dashboard_secondary', 'dashboard', 'side');
            remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
            remove_meta_box('dashboard_recent_drafts', 'dashboard', 'side');
            remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
            remove_meta_box('dashboard_activity', 'dashboard', 'normal');
            
            // Add coach-specific widgets
            wp_add_dashboard_widget(
                'coach_performance_widget',
                '📊 My Performance',
                [$this, 'coach_performance_widget']
            );
            
            wp_add_dashboard_widget(
                'coach_referral_widget',
                '🔗 My Referral Link',
                [$this, 'coach_referral_widget']
            );
            
            wp_add_dashboard_widget(
                'coach_recent_referrals_widget',
                '👥 Recent Referrals',
                [$this, 'coach_recent_referrals_widget']
            );
            
            wp_add_dashboard_widget(
                'coach_tips_widget',
                '💡 Coach Tips',
                [$this, 'coach_tips_widget']
            );
        }
    }
    
    /**
     * Render main coach admin dashboard
     */
    public function render_coach_admin_dashboard() {
        $user_id = get_current_user_id();

        // Get venue assignments
        $venue_assignments = [];
        if (class_exists('InterSoccer_Admin_Coach_Assignments')) {
            $assignments = InterSoccer_Admin_Coach_Assignments::get_coach_assignments_static($user_id);
            foreach ($assignments as $assignment) {
                $venue_assignments[] = [
                    'venue' => $assignment->venue,
                    'assignment_type' => $assignment->assignment_type,
                    'canton' => $assignment->canton
                ];
            }
        }

        // Get event participation stats
        $event_stats = $this->get_coach_event_stats($user_id);

        // Get dashboard data
        $coach_data = [
            'user_id' => $user_id,
            'user_name' => wp_get_current_user()->display_name,
            'user_email' => wp_get_current_user()->user_email,
            'credits' => intersoccer_get_coach_credits($user_id),
            'points_balance' => self::get_coach_points_balance($user_id),
            'tier' => intersoccer_get_coach_tier($user_id),
            'referral_link' => InterSoccer_Referral_Handler::generate_coach_referral_link($user_id),
            'referral_code' => InterSoccer_Referral_Handler::get_coach_referral_code($user_id),
            'total_referrals' => $this->get_coach_referral_count($user_id),
            'recent_referrals' => $this->get_recent_referrals($user_id, 5),
            'earnings_data' => $this->get_earnings_data($user_id),
            'monthly_stats' => $this->get_monthly_stats($user_id),
            'coach_rank' => $this->get_coach_rank($user_id),
            'top_performers' => $this->get_top_performers(),
            'coach_achievements' => $this->get_coach_achievement_progress($user_id),
            'chart_labels' => $this->get_chart_labels(30),
            'chart_referrals' => $this->get_chart_data($user_id, 30, 'referrals'),
            'chart_credits' => $this->get_chart_data($user_id, 30, 'credits'),
            'venue_assignments' => $venue_assignments,
            'event_stats' => $event_stats,
            'coach_events' => InterSoccer_Coach_Events_Manager::get_coach_events($user_id),
            'coach_events_nonce' => wp_create_nonce('intersoccer_coach_events_nonce'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'is_admin_context' => current_user_can('manage_options')
        ];

        // Load the modern dashboard template
        $template_path = INTERSOCCER_REFERRAL_PATH . 'templates/modern-coach-dashboard.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // Fallback to enhanced dashboard if template doesn't exist
            $this->render_enhanced_dashboard($coach_data);
        }
    }

    /**
     * Render enhanced dashboard with coach profile, venue assignments, and event participation
     */
    private function render_enhanced_dashboard($data) {
        ?>
        <div class="wrap coach-dashboard">
            <div class="coach-dashboard-header">
                <h1>Welcome back, <?php echo esc_html($data['user_name']); ?>! 👋</h1>
                <p class="coach-subtitle">Here's your coaching dashboard overview</p>
            </div>

            <!-- Profile & Assignment Section -->
            <div class="coach-profile-section">
                <div class="coach-profile-card">
                    <h2>👤 Your Profile</h2>
                    <div class="profile-details">
                        <div class="profile-row">
                            <span class="profile-label">Name:</span>
                            <span class="profile-value"><?php echo esc_html($data['user_name']); ?></span>
                        </div>
                        <div class="profile-row">
                            <span class="profile-label">Email:</span>
                            <span class="profile-value"><?php echo esc_html($data['user_email']); ?></span>
                        </div>
                        <div class="profile-row">
                            <span class="profile-label">Coach Tier:</span>
                            <span class="profile-value tier-badge tier-<?php echo strtolower($data['tier']); ?>"><?php echo esc_html($data['tier']); ?></span>
                        </div>
                        <div class="profile-row">
                            <span class="profile-label">Referral Points:</span>
                            <span class="profile-value"><?php echo number_format($data['credits'], 0); ?> CHF</span>
                        </div>
                    </div>
                </div>

                <div class="coach-assignments-card">
                    <h2>📍 Your Venue Assignments</h2>
                    <?php if (!empty($data['venue_assignments'])): ?>
                        <div class="assignments-list">
                            <?php foreach ($data['venue_assignments'] as $assignment): ?>
                                <div class="assignment-item">
                                    <div class="assignment-venue"><?php echo esc_html($assignment['venue']); ?></div>
                                    <div class="assignment-details">
                                        <span class="assignment-type"><?php echo esc_html(ucfirst($assignment['assignment_type'])); ?></span>
                                        <?php if ($assignment['canton']): ?>
                                            <span class="assignment-canton">• <?php echo esc_html($assignment['canton']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="no-assignments">No venue assignments found. Contact an administrator to assign you to venues.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Event Participation Stats -->
            <div class="coach-stats-section">
                <h2>📊 Event Participation Overview</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">📅</div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo number_format($data['event_stats']['total_events']); ?></div>
                            <div class="stat-label">Total Events</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">🎯</div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo number_format($data['event_stats']['active_events']); ?></div>
                            <div class="stat-label">Active Events</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">👥</div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo number_format($data['event_stats']['total_participants']); ?></div>
                            <div class="stat-label">Total Participants</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">⏰</div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo number_format($data['event_stats']['upcoming_events']); ?></div>
                            <div class="stat-label">Upcoming (30 days)</div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($data['event_stats']['event_types'])): ?>
                    <div class="event-types-breakdown">
                        <h3>Event Types</h3>
                        <div class="event-types-list">
                            <?php foreach ($data['event_stats']['event_types'] as $event_type): ?>
                                <div class="event-type-item">
                                    <span class="event-type-name"><?php echo esc_html($event_type['activity_type'] ?: 'Other'); ?></span>
                                    <span class="event-type-count"><?php echo number_format($event_type['count']); ?> events</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Referral Performance -->
            <div class="coach-referral-section">
                <h2>💰 Referral Performance</h2>
                <div class="referral-stats-grid">
                    <div class="referral-stat-card">
                        <div class="stat-number"><?php echo number_format($data['total_referrals']); ?></div>
                        <div class="stat-label">Total Referrals</div>
                    </div>
                    <div class="referral-stat-card">
                        <div class="stat-number"><?php echo number_format($data['credits'], 0); ?> CHF</div>
                        <div class="stat-label">Referral Earnings</div>
                    </div>
                </div>

                <div class="referral-link-section">
                    <h3>Your Referral Link</h3>
                    <div class="referral-link-container">
                        <input type="text" value="<?php echo esc_attr($data['referral_link']); ?>" readonly id="referral-link-input">
                        <button onclick="copyReferralLink()" class="copy-link-btn">📋 Copy Link</button>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <?php if (!empty($data['recent_referrals'])): ?>
                <div class="coach-activity-section">
                    <h2>📈 Recent Activity</h2>
                    <div class="recent-referrals-list">
                        <?php foreach ($data['recent_referrals'] as $referral): ?>
                            <div class="referral-item">
                                <div class="referral-info">
                                    <span class="referral-customer"><?php echo esc_html($referral->customer_name ?: 'Unknown Customer'); ?></span>
                                    <span class="referral-date"><?php echo date('M j, Y', strtotime($referral->created_at)); ?></span>
                                </div>
                                <div class="referral-status">
                                    <span class="status-badge status-<?php echo esc_attr($referral->status); ?>">
                                        <?php echo esc_html(ucfirst($referral->status)); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="coach-actions-section">
                <h2>🚀 Quick Actions</h2>
                <div class="action-buttons">
                    <a href="<?php echo admin_url('admin.php?page=intersoccer-coach-referrals'); ?>" class="action-btn">
                        👥 View All Referrals
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=intersoccer-coach-resources'); ?>" class="action-btn">
                        📚 Marketing Resources
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=intersoccer-reports-rosters'); ?>" class="action-btn">
                        📊 View Rosters
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=intersoccer-coach-profile'); ?>" class="action-btn">
                        ⚙️ Edit Profile
                    </a>
                </div>
            </div>
        </div>

        <style>
        .coach-dashboard {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
        }

        .coach-dashboard-header {
            margin-bottom: 30px;
            text-align: center;
        }

        .coach-dashboard h1 {
            color: #2c3338;
            margin-bottom: 10px;
        }

        .coach-subtitle {
            color: #666;
            font-size: 16px;
        }

        .coach-profile-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .coach-profile-card,
        .coach-assignments-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e1e5e9;
        }

        .coach-profile-card h2,
        .coach-assignments-card h2 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #2c3338;
            font-size: 18px;
        }

        .profile-details {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .profile-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .profile-row:last-child {
            border-bottom: none;
        }

        .profile-label {
            font-weight: 600;
            color: #5f6368;
        }

        .profile-value {
            color: #2c3338;
        }

        .tier-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .tier-bronze { background: #cd7f32; color: white; }
        .tier-silver { background: #c0c0c0; color: #333; }
        .tier-gold { background: #ffd700; color: #333; }
        .tier-platinum { background: #e5e4e2; color: #333; }

        .assignments-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .assignment-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #0073aa;
        }

        .assignment-venue {
            font-weight: 600;
            color: #2c3338;
            margin-bottom: 5px;
        }

        .assignment-details {
            font-size: 14px;
            color: #666;
        }

        .assignment-type {
            font-weight: 500;
        }

        .assignment-canton {
            color: #0073aa;
        }

        .no-assignments {
            color: #666;
            font-style: italic;
            text-align: center;
            padding: 20px;
        }

        .coach-stats-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e1e5e9;
        }

        .coach-stats-section h2 {
            margin-top: 0;
            margin-bottom: 25px;
            color: #2c3338;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            font-size: 24px;
        }

        .stat-content {
            flex: 1;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }

        .event-types-breakdown h3 {
            margin-bottom: 15px;
            color: #2c3338;
        }

        .event-types-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .event-type-item {
            background: #f0f4f8;
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 14px;
            color: #2c3338;
        }

        .coach-referral-section,
        .coach-activity-section,
        .coach-actions-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e1e5e9;
        }

        .coach-referral-section h2,
        .coach-activity-section h2,
        .coach-actions-section h2 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #2c3338;
        }

        .referral-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .referral-stat-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .referral-link-container {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        #referral-link-input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .copy-link-btn {
            background: #0073aa;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }

        .copy-link-btn:hover {
            background: #005a87;
        }

        .recent-referrals-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .referral-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .referral-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .referral-customer {
            font-weight: 600;
            color: #2c3338;
        }

        .referral-date {
            font-size: 14px;
            color: #666;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-completed { background: #d4edda; color: #155724; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .action-btn {
            display: inline-block;
            padding: 12px 20px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .action-btn:hover {
            background: #005a87;
            color: white;
        }

        @media (max-width: 768px) {
            .coach-profile-section {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .action-buttons {
                grid-template-columns: 1fr;
            }

            .referral-link-container {
                flex-direction: column;
                align-items: stretch;
            }
        }
        </style>

        <script>
        function copyReferralLink() {
            const input = document.getElementById('referral-link-input');
            input.select();
            document.execCommand('copy');

            const btn = event.target;
            const originalText = btn.textContent;
            btn.textContent = '✅ Copied!';
            btn.style.background = '#28a745';

            setTimeout(() => {
                btn.textContent = originalText;
                btn.style.background = '#0073aa';
            }, 2000);
        }
        </script>
        <?php
    }

    /**
     * Get recent referrals for dashboard
     */
    private function get_recent_referrals($coach_id, $limit = 5) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, u.display_name as customer_name
             FROM $table_name r
             LEFT JOIN {$wpdb->users} u ON r.customer_id = u.ID
             WHERE r.coach_id = %d
             ORDER BY r.created_at DESC
             LIMIT %d",
            $coach_id, $limit
        ));
    }

    /**
     * Get earnings data for dashboard
     */
    private function get_earnings_data($coach_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';

        // Get monthly earnings for the last 6 months
        $earnings = $wpdb->get_results($wpdb->prepare(
            "SELECT
                DATE_FORMAT(created_at, '%Y-%m') as month,
                SUM(commission_amount) as total_earnings,
                COUNT(*) as referral_count
             FROM $table_name
             WHERE coach_id = %d AND status = 'completed'
             AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY month DESC",
            $coach_id
        ));

        return $earnings;
    }

    /**
     * Get chart labels for dashboard
     */
    private function get_chart_labels($days) {
        $labels = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $labels[] = date('M j', strtotime("-{$i} days"));
        }
        return $labels;
    }

    /**
     * Get chart data for dashboard
     */
    private function get_chart_data($coach_id, $days, $type) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';

        $data = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));

            if ($type === 'referrals') {
                $value = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE coach_id = %d AND DATE(created_at) = %s",
                    $coach_id, $date
                ));
            } else { // credits
                $value = $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(commission_amount), 0) FROM $table_name WHERE coach_id = %d AND DATE(created_at) = %s AND status = 'completed'",
                    $coach_id, $date
                ));
            }

            $data[] = (float) $value;
        }

        return $data;
    }

    /**
     * Get coach event participation statistics
     */
    private function get_coach_event_stats($coach_id) {
        global $wpdb;
        $rosters_table = $wpdb->prefix . 'intersoccer_rosters';

        // Get coach's assigned venues
        $assigned_venues = [];
        if (class_exists('InterSoccer_Admin_Coach_Assignments')) {
            $assigned_venues = InterSoccer_Admin_Coach_Assignments::get_coach_accessible_venues($coach_id);
        }

        if (empty($assigned_venues)) {
            return [
                'total_events' => 0,
                'active_events' => 0,
                'total_participants' => 0,
                'upcoming_events' => 0,
                'event_types' => []
            ];
        }

        // Build venue filter
        $placeholders = implode(',', array_fill(0, count($assigned_venues), '%s'));
        $venue_filter = $wpdb->prepare("venue IN ($placeholders)", $assigned_venues);

        // Get total events
        $total_events = $wpdb->get_var("
            SELECT COUNT(DISTINCT event_signature)
            FROM $rosters_table
            WHERE $venue_filter
        ");

        // Get active events (current/future)
        $active_events = $wpdb->get_var("
            SELECT COUNT(DISTINCT event_signature)
            FROM $rosters_table
            WHERE $venue_filter AND end_date >= CURDATE()
        ");

        // Get total participants
        $total_participants = $wpdb->get_var("
            SELECT COUNT(DISTINCT order_item_id)
            FROM $rosters_table
            WHERE $venue_filter
        ");

        // Get upcoming events (next 30 days)
        $upcoming_events = $wpdb->get_var("
            SELECT COUNT(DISTINCT event_signature)
            FROM $rosters_table
            WHERE $venue_filter AND start_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ");

        // Get event types breakdown
        $event_types = $wpdb->get_results("
            SELECT activity_type, COUNT(DISTINCT event_signature) as count
            FROM $rosters_table
            WHERE $venue_filter
            GROUP BY activity_type
            ORDER BY count DESC
        ", ARRAY_A);

        return [
            'total_events' => (int) $total_events,
            'active_events' => (int) $active_events,
            'total_participants' => (int) $total_participants,
            'upcoming_events' => (int) $upcoming_events,
            'event_types' => $event_types
        ];
    }
    
    /**
     * Render coach referrals page
     */
    public function render_coach_referrals() {
        // Implementation for detailed referrals view
        echo '<div class="wrap"><h1>My Referrals</h1><p>Detailed referrals coming soon...</p></div>';
    }
    
    /**
     * Render coach profile page
     */
    public function render_coach_profile() {
        $user_id = get_current_user_id();
        $user = wp_get_current_user();

        // Get venue assignments
        $venue_assignments = [];
        if (class_exists('InterSoccer_Admin_Coach_Assignments')) {
            $assignments = InterSoccer_Admin_Coach_Assignments::get_coach_assignments_static($user_id);
            foreach ($assignments as $assignment) {
                $venue_assignments[] = $assignment;
            }
        }

        // Get event participation stats
        $event_stats = $this->get_coach_event_stats($user_id);

        ?>
        <div class="wrap coach-profile-page">
            <h1>👤 My Profile</h1>

            <div class="profile-sections">
                <!-- Basic Information -->
                <div class="profile-section">
                    <h2>Basic Information</h2>
                    <div class="profile-info-grid">
                        <div class="info-item">
                            <label>Full Name:</label>
                            <span><?php echo esc_html($user->display_name); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Email:</label>
                            <span><?php echo esc_html($user->user_email); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Username:</label>
                            <span><?php echo esc_html($user->user_login); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Member Since:</label>
                            <span><?php echo date('F j, Y', strtotime($user->user_registered)); ?></span>
                        </div>
                        <div class="info-item">
                            <label>Coach Tier:</label>
                            <span class="tier-badge tier-<?php echo strtolower(intersoccer_get_coach_tier($user_id)); ?>">
                                <?php echo esc_html(intersoccer_get_coach_tier($user_id)); ?>
                            </span>
                        </div>
                        <div class="info-item">
                            <label>Points Balance:</label>
                            <span><?php echo number_format(InterSoccer_Coach_Admin_Dashboard::get_coach_points_balance($user_id), 0); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Venue Assignments -->
                <div class="profile-section">
                    <h2>📍 Venue Assignments</h2>
                    <?php if (!empty($venue_assignments)): ?>
                        <div class="assignments-table">
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th>Venue</th>
                                        <th>Assignment Type</th>
                                        <th>Canton</th>
                                        <th>Assigned Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($venue_assignments as $assignment): ?>
                                        <tr>
                                            <td><?php echo esc_html($assignment->venue); ?></td>
                                            <td><?php echo esc_html(ucfirst($assignment->assignment_type)); ?></td>
                                            <td><?php echo esc_html($assignment->canton ?: 'N/A'); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($assignment->created_at)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="assignment-note">
                            <strong>Note:</strong> You can only view rosters for events at your assigned venues.
                            Contact an administrator if you need access to additional venues.
                        </p>
                    <?php else: ?>
                        <div class="no-assignments-notice">
                            <p>You don't have any venue assignments yet.</p>
                            <p>Contact an administrator to assign you to specific venues, camps, or courses.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Event Participation Summary -->
                <div class="profile-section">
                    <h2>📊 Event Participation Summary</h2>
                    <div class="event-summary-grid">
                        <div class="summary-card">
                            <div class="summary-icon">📅</div>
                            <div class="summary-content">
                                <div class="summary-number"><?php echo number_format($event_stats['total_events']); ?></div>
                                <div class="summary-label">Total Events</div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon">🎯</div>
                            <div class="summary-content">
                                <div class="summary-number"><?php echo number_format($event_stats['active_events']); ?></div>
                                <div class="summary-label">Active Events</div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon">👥</div>
                            <div class="summary-content">
                                <div class="summary-number"><?php echo number_format($event_stats['total_participants']); ?></div>
                                <div class="summary-label">Participants Coached</div>
                            </div>
                        </div>
                        <div class="summary-card">
                            <div class="summary-icon">⏰</div>
                            <div class="summary-content">
                                <div class="summary-number"><?php echo number_format($event_stats['upcoming_events']); ?></div>
                                <div class="summary-label">Upcoming Events</div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($event_stats['event_types'])): ?>
                        <div class="event-types-section">
                            <h3>Event Types You've Coached</h3>
                            <div class="event-types-breakdown">
                                <?php foreach ($event_stats['event_types'] as $event_type): ?>
                                    <div class="event-type-stat">
                                        <span class="event-type-name"><?php echo esc_html($event_type['activity_type'] ?: 'Other'); ?></span>
                                        <span class="event-type-count"><?php echo number_format($event_type['count']); ?> events</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Account Settings -->
                <div class="profile-section">
                    <h2>⚙️ Account Settings</h2>
                    <div class="settings-notice">
                        <p>To update your profile information or change your password, please contact an administrator.</p>
                        <p>For questions about your venue assignments or event access, reach out to the InterSoccer team.</p>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .coach-profile-page {
            max-width: 1000px;
            margin: 0 auto;
        }

        .profile-sections {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .profile-section {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border: 1px solid #e1e5e9;
        }

        .profile-section h2 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #2c3338;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
        }

        .profile-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .info-item label {
            font-weight: 600;
            color: #5f6368;
        }

        .info-item span {
            color: #2c3338;
        }

        .tier-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .tier-bronze { background: #cd7f32; color: white; }
        .tier-silver { background: #c0c0c0; color: #333; }
        .tier-gold { background: #ffd700; color: #333; }
        .tier-platinum { background: #e5e4e2; color: #333; }

        .assignments-table table {
            border-radius: 8px;
            overflow: hidden;
        }

        .assignments-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3338;
        }

        .assignment-note {
            margin-top: 15px;
            padding: 15px;
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            border-radius: 4px;
        }

        .no-assignments-notice {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .no-assignments-notice p {
            margin: 10px 0;
        }

        .event-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .summary-icon {
            font-size: 24px;
        }

        .summary-content {
            flex: 1;
        }

        .summary-number {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .summary-label {
            font-size: 14px;
            opacity: 0.9;
        }

        .event-types-section h3 {
            margin-bottom: 15px;
            color: #2c3338;
        }

        .event-types-breakdown {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .event-type-stat {
            background: #f0f4f8;
            padding: 10px 15px;
            border-radius: 20px;
            font-size: 14px;
            color: #2c3338;
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-width: 150px;
        }

        .settings-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
        }

        .settings-notice p {
            margin: 0 0 10px 0;
        }

        .settings-notice p:last-child {
            margin-bottom: 0;
        }

        @media (max-width: 768px) {
            .profile-info-grid {
                grid-template-columns: 1fr;
            }

            .event-summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .assignments-table {
                overflow-x: auto;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Render coach resources page
     */
    public function render_coach_resources() {
        ?>
        <div class="wrap coach-resources">
            <h1>📚 Marketing Resources</h1>
            
            <div class="resource-section">
                <h2>🔗 Share Your Link</h2>
                <p>Your personalized referral link:</p>
                <div class="referral-link-box">
                    <input type="text" value="<?php echo InterSoccer_Referral_Handler::generate_coach_referral_link(get_current_user_id()); ?>" readonly>
                    <button onclick="copyLink(this)">Copy</button>
                </div>
            </div>
            
            <div class="resource-section">
                <h2>📱 Social Media Templates</h2>
                <div class="template-grid">
                    <div class="template-card">
                        <h3>Instagram Story</h3>
                        <p>"Transform your game with personalized soccer training! 🏈⚽ Join me at InterSoccer - link in bio!"</p>
                    </div>
                    <div class="template-card">
                        <h3>Facebook Post</h3>
                        <p>"Looking to improve your soccer skills? I'm now partnering with InterSoccer to offer personalized training programs. Click here to get started: [YOUR_LINK]"</p>
                    </div>
                </div>
            </div>
            
            <div class="resource-section">
                <h2>💡 Tips for Success</h2>
                <ul class="tips-list">
                    <li>Share your story - why you love coaching with InterSoccer</li>
                    <li>Post training videos and tag potential students</li>
                    <li>Offer free initial consultations to build trust</li>
                    <li>Follow up with referrals within 24 hours</li>
                </ul>
            </div>
        </div>
        
        <script>
        function copyLink(button) {
            const input = button.previousElementSibling;
            input.select();
            document.execCommand('copy');
            button.textContent = 'Copied!';
            setTimeout(() => button.textContent = 'Copy', 2000);
        }
        </script>
        <?php
    }
    
    /**
     * Dashboard widget functions
     */
    public function coach_performance_widget() {
        $user_id = get_current_user_id();
        $credits = intersoccer_get_coach_credits($user_id);
        $referrals = $this->get_coach_referral_count($user_id);
        
        echo "<p><strong>Credits:</strong> {$credits} CHF</p>";
        echo "<p><strong>Referrals:</strong> {$referrals}</p>";
        echo "<p><strong>Tier:</strong> " . intersoccer_get_coach_tier($user_id) . "</p>";
    }
    
    public function coach_referral_widget() {
        $link = InterSoccer_Referral_Handler::generate_coach_referral_link(get_current_user_id());
        echo '<input type="text" value="' . esc_attr($link) . '" readonly style="width: 100%; margin-bottom: 10px;">';
        echo '<button onclick="navigator.clipboard.writeText(\'' . esc_js($link) . '\'); this.textContent=\'Copied!\'">Copy Link</button>';
    }
    
    public function coach_recent_referrals_widget() {
        echo "<p>Your recent referrals will appear here.</p>";
    }
    
    public function coach_tips_widget() {
        $tips = [
            "Share your referral link on social media for maximum reach",
            "Personal recommendations work better than generic posts",
            "Follow up with potential students within 24 hours",
            "Offer value before asking for referrals"
        ];
        
        echo "<ul>";
        foreach ($tips as $tip) {
            echo "<li>{$tip}</li>";
        }
        echo "</ul>";
    }
    
    /**
     * Remove admin notices for coaches using WordPress hooks
     */
    public function remove_admin_notices_for_coaches() {
        if (current_user_can('coach') && !current_user_can('manage_options')) {
            // Remove common admin notices
            remove_action('admin_notices', 'update_nag', 3);
            remove_action('admin_notices', 'maintenance_nag', 10);
            remove_action('admin_notices', 'site_admin_notice', 10);
            remove_action('admin_notices', 'user_admin_notice', 10);

            // Remove update notifications
            remove_action('admin_notices', 'wp_update_notice', 10);
            remove_action('admin_notices', 'wp_plugin_update_rows', 10);
            remove_action('admin_notices', 'wp_theme_update_rows', 10);

            // Remove network admin notices if applicable
            if (is_multisite()) {
                remove_action('network_admin_notices', 'wp_update_notice', 10);
            }
        }
    }

    /**
     * Hide WordPress admin notices for coaches
     */
    public function hide_admin_notices_for_coaches() {
        if (current_user_can('coach') && !current_user_can('manage_options')) {
            // Hide admin notices with CSS
            echo '<style>
                .notice, .update-nag, .updated, .error, .warning, .info,
                #wp-admin-bar-updates, .wp-admin-bar-updates,
                .plugin-update-triggers, .theme-update-triggers {
                    display: none !important;
                }
                /* Hide specific WordPress notices */
                .notice-info, .notice-warning, .notice-error, .notice-success {
                    display: none !important;
                }
                /* Hide update notifications in admin bar */
                #wp-admin-bar-updates .ab-item {
                    display: none !important;
                }
                /* Hide plugin/theme update counts */
                .plugin-count, .theme-count {
                    display: none !important;
                }
            </style>';
        }
    }

    /**
     * Enqueue coach-specific admin styles
     */
    public function enqueue_coach_admin_styles($hook) {
        if (current_user_can('coach') && !current_user_can('manage_options')) {
            // Load modern dashboard assets for the coach dashboard page
            if (isset($_GET['page']) && $_GET['page'] === 'intersoccer-coach-dashboard') {
                wp_enqueue_style('modern-dashboard-css', INTERSOCCER_REFERRAL_URL . 'assets/css/modern-dashboard.css', [], INTERSOCCER_REFERRAL_VERSION);
                wp_enqueue_style('aos-css', 'https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css', [], '2.3.4');
                wp_enqueue_script('aos-js', 'https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js', [], '2.3.4', true);
                wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.0', true);
                wp_enqueue_script('modern-dashboard-js', INTERSOCCER_REFERRAL_URL . 'assets/js/modern-dashboard.js', ['jquery', 'aos-js', 'chart-js'], INTERSOCCER_REFERRAL_VERSION, true);

                wp_localize_script('modern-dashboard-js', 'intersoccer_dashboard', [
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('dashboard_nonce'),
                    'coach_events_nonce' => wp_create_nonce('intersoccer_coach_events_nonce'),
                    'user_id' => get_current_user_id(),
                    'user_name' => wp_get_current_user()->display_name,
                    'referral_link' => InterSoccer_Referral_Handler::generate_coach_referral_link(get_current_user_id()),
                    'referral_code' => InterSoccer_Referral_Handler::get_coach_referral_code(get_current_user_id()),
                    'admin_url' => admin_url(),
                    'chart_data' => [
                        'labels' => $this->get_chart_labels(30),
                        'referrals' => $this->get_chart_data(get_current_user_id(), 30, 'referrals'),
                        'credits' => $this->get_chart_data(get_current_user_id(), 30, 'credits')
                    ],
                    'i18n' => function_exists('intersoccer_referral_get_dashboard_i18n') ? intersoccer_referral_get_dashboard_i18n() : []
                ]);
            }

            // Load basic coach admin styles for other admin pages
            wp_enqueue_style('coach-admin-styles', INTERSOCCER_REFERRAL_URL . 'assets/css/coach-admin.css', [], INTERSOCCER_REFERRAL_VERSION);

            // Load tour assets
            wp_enqueue_style('shepherd-css', 'https://cdn.jsdelivr.net/npm/shepherd.js@10.0.1/dist/css/shepherd.css', [], '10.0.1');
            wp_enqueue_script('shepherd-js', 'https://cdn.jsdelivr.net/npm/shepherd.js@10.0.1/dist/js/shepherd.min.js', [], '10.0.1', true);
            wp_enqueue_script('coach-tour-js', INTERSOCCER_REFERRAL_URL . 'assets/js/coach-tour.js', ['shepherd-js'], INTERSOCCER_REFERRAL_VERSION, true);
            wp_localize_script('coach-tour-js', 'intersoccer_tour', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('intersoccer_tour_nonce'),
                'user_id' => get_current_user_id(),
                'tour_completed' => get_user_meta(get_current_user_id(), 'intersoccer_tour_completed', true),
                'debug' => defined('WP_DEBUG') && WP_DEBUG
            ]);
        }
    }
    
    /**
     * Helper function to get coach referral count
     */
    private function get_coach_referral_count($coach_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE coach_id = %d",
            $coach_id
        ));
    }

    private function get_customer_name($customer_id) {
        if (!$customer_id) {
            return __('Unknown Customer', 'intersoccer-referral');
        }

        $user = get_userdata($customer_id);
        if ($user && !empty($user->display_name)) {
            return $user->display_name;
        }

        return __('Unknown Customer', 'intersoccer-referral');
    }

    /**
     * Count the number of active customers linked to this coach.
     */
    private function get_linked_customers_count($coach_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_customer_partnerships';

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE coach_id = %d AND status = 'active'",
            $coach_id
        ));
    }

    /**
     * Fetch recent achievements for the coach (limited for display).
     */
    private function get_coach_achievements($coach_id, $limit = 5) {
        global $wpdb;
        $table = $wpdb->prefix . 'intersoccer_coach_achievements';

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT achievement_name, description, points, earned_at
             FROM $table
             WHERE coach_id = %d
             ORDER BY earned_at DESC
             LIMIT %d",
            $coach_id,
            $limit
        ));

        return $results ?: [];
    }

    /**
     * Build achievement progress data for dashboard cards.
     */
    private function get_coach_achievement_progress($coach_id) {
        $referral_count = $this->get_coach_referral_count($coach_id);
        $credits = (float) get_user_meta($coach_id, 'intersoccer_credits', true);

        return [
            [
                'title' => __('First Referral', 'intersoccer-referral'),
                'description' => __('Made your first successful referral', 'intersoccer-referral'),
                'icon' => 'handshake',
                'unlocked' => $referral_count >= 1,
                'progress' => min(100, $referral_count * 100),
                'progress_text' => $referral_count >= 1
                    ? __('Completed!', 'intersoccer-referral')
                    : sprintf(
                        _n('%d referral remaining', '%d referrals remaining', max(1 - $referral_count, 0), 'intersoccer-referral'),
                        max(1 - $referral_count, 0)
                    ),
            ],
            [
                'title' => __('Top Earner', 'intersoccer-referral'),
                'description' => __('Earned 500 CHF in commissions', 'intersoccer-referral'),
                'icon' => 'trophy',
                'unlocked' => $credits >= 500,
                'progress' => min(100, ($credits / 500) * 100),
                'progress_text' => $credits >= 500
                    ? __('Completed!', 'intersoccer-referral')
                    : sprintf(
                        __('%s CHF to go', 'intersoccer-referral'),
                        number_format_i18n(max(500 - $credits, 0), 0)
                    ),
            ],
            [
                'title' => __('Referral Master', 'intersoccer-referral'),
                'description' => __('Generated 25 successful referrals', 'intersoccer-referral'),
                'icon' => 'users',
                'unlocked' => $referral_count >= 25,
                'progress' => min(100, ($referral_count / 25) * 100),
                'progress_text' => $referral_count >= 25
                    ? __('Completed!', 'intersoccer-referral')
                    : sprintf(
                        _n('%d referral to go', '%d referrals to go', max(25 - $referral_count, 0), 'intersoccer-referral'),
                        max(25 - $referral_count, 0)
                    ),
            ],
            [
                'title' => __('Commission Champion', 'intersoccer-referral'),
                'description' => __('Earned 1000 CHF in total commissions', 'intersoccer-referral'),
                'icon' => 'crown',
                'unlocked' => $credits >= 1000,
                'progress' => min(100, ($credits / 1000) * 100),
                'progress_text' => $credits >= 1000
                    ? __('Completed!', 'intersoccer-referral')
                    : sprintf(
                        __('%s CHF to go', 'intersoccer-referral'),
                        number_format_i18n(max(1000 - $credits, 0), 0)
                    ),
            ],
        ];
    }

    private function get_monthly_stats($coach_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';

        $current_timestamp = current_time('timestamp');
        $current_month_start = gmdate('Y-m-01 00:00:00', $current_timestamp);
        $last_month_start = gmdate('Y-m-01 00:00:00', strtotime('-1 month', $current_timestamp));

        $current_referrals = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE coach_id = %d AND status = 'completed' AND created_at >= %s",
            $coach_id,
            $current_month_start
        ));

        $last_month_referrals = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE coach_id = %d AND status = 'completed' AND created_at >= %s AND created_at < %s",
            $coach_id,
            $last_month_start,
            $current_month_start
        ));

        $conversion_rate = $current_referrals > 0
            ? min(100, ($current_referrals / max(1, $current_referrals + 5)) * 100)
            : 0;
        $last_month_conversion = $last_month_referrals > 0
            ? min(100, ($last_month_referrals / max(1, $last_month_referrals + 5)) * 100)
            : 0;

        return [
            'new_referrals' => $current_referrals,
            'conversion_rate' => round($conversion_rate, 1),
            'conversion_trend' => round($conversion_rate - $last_month_conversion, 1),
        ];
    }

    private function get_top_performers($limit = 10) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT coach_id,
                    COUNT(*) AS referral_count,
                    COALESCE(SUM(commission_amount), 0) AS total_credits
             FROM $table_name
             WHERE status = 'completed'
             GROUP BY coach_id
             ORDER BY total_credits DESC, referral_count DESC
             LIMIT %d",
            $limit
        ));

        if (empty($rows)) {
            return [];
        }

        $performers = [];
        foreach ($rows as $row) {
            $user = get_userdata($row->coach_id);
            if (!$user) {
                continue;
            }

            $performers[] = (object) [
                'ID' => (int) $row->coach_id,
                'display_name' => $user->display_name,
                'referral_count' => (int) $row->referral_count,
                'total_credits' => (float) $row->total_credits,
                'tier' => intersoccer_get_coach_tier($row->coach_id),
            ];
        }

        return $performers;
    }

    private function get_coach_rank($coach_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';

        $coach_total = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(commission_amount), 0)
             FROM $table_name
             WHERE coach_id = %d AND status = 'completed'",
            $coach_id
        ));

        if ($coach_total <= 0) {
            return 1;
        }

        $higher_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM (
                SELECT coach_id
                FROM $table_name
                WHERE status = 'completed'
                GROUP BY coach_id
                HAVING SUM(commission_amount) > %f
            ) as ranked",
            $coach_total
        ));

        return $higher_count + 1;
    }

    /**
     * Calculate progress towards the next tier.
     */
    private function get_tier_progress($tier, $referral_count) {
        $thresholds = $this->get_tier_thresholds();
        $tier_key = ucfirst(strtolower($tier));

        if (!isset($thresholds[$tier_key])) {
            return 0;
        }

        $current = $thresholds[$tier_key];
        $current_min = $current['min'];
        $next_max = $current['max'];

        if ($next_max === null) {
            return 100;
        }

        $span = max($next_max - $current_min, 1);
        $progress = ($referral_count - $current_min) / $span * 100;

        return max(0, min(100, round($progress)));
    }

    /**
     * Provide messaging for requirements to reach the next tier.
     */
    private function get_next_tier_requirements($tier, $referral_count) {
        $order = [
            'Bronze' => 'Silver',
            'Silver' => 'Gold',
            'Gold' => 'Platinum',
            'Platinum' => null,
        ];

        $tier_key = ucfirst(strtolower($tier));
        if (!array_key_exists($tier_key, $order)) {
            return '';
        }

        $next_tier = $order[$tier_key];
        if ($next_tier === null) {
            return __('You have reached the highest tier. Fantastic work!', 'intersoccer-referral');
        }

        $thresholds = $this->get_tier_thresholds();
        $target = $thresholds[$next_tier]['min'];
        $remaining = max(0, $target - $referral_count);

        if ($remaining <= 0) {
            return sprintf(__('Ready to move into %s tier—keep up the momentum!', 'intersoccer-referral'), $next_tier);
        }

        return sprintf(
            _n('%d referral until %s tier', '%d referrals until %s tier', $remaining, 'intersoccer-referral'),
            $remaining,
            $next_tier
        );
    }

    /**
     * Fetch tier thresholds from options.
     */
    private function get_tier_thresholds() {
        $silver = (int) get_option('intersoccer_tier_silver', 5);
        $gold = (int) get_option('intersoccer_tier_gold', 10);
        $platinum = (int) get_option('intersoccer_tier_platinum', 20);

        return [
            'Bronze' => ['min' => 0, 'max' => $silver],
            'Silver' => ['min' => $silver, 'max' => $gold],
            'Gold' => ['min' => $gold, 'max' => $platinum],
            'Platinum' => ['min' => $platinum, 'max' => null],
        ];
    }

    public function complete_tour() {
        check_ajax_referer('intersoccer_tour_nonce', 'nonce');
        $user_id = absint($_POST['user_id']);
        if (current_user_can('coach') && get_current_user_id() === $user_id) {
            update_user_meta($user_id, 'intersoccer_tour_completed', 1);
            error_log('Coach tour completed for user: ' . $user_id);
            wp_send_json_success(['message' => 'Tour completed']);
        }
        wp_send_json_error(['message' => 'Unauthorized']);
    }
}
