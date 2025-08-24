<?php
// Add this to your main plugin file or create a new class

class InterSoccer_Coach_Admin_Dashboard {
    
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
            
            // Add coach-specific admin bar items
            $wp_admin_bar->add_node([
                'id'    => 'coach-stats',
                'title' => '💰 Credits: ' . number_format(intersoccer_get_coach_credits(), 0) . ' CHF',
                'href'  => admin_url('admin.php?page=intersoccer-coach-dashboard'),
                'meta'  => ['class' => 'coach-credits-display']
            ]);
            
            $wp_admin_bar->add_node([
                'id'    => 'coach-tier',
                'title' => '🏆 ' . intersoccer_get_coach_tier(),
                'href'  => admin_url('admin.php?page=intersoccer-coach-dashboard'),
                'meta'  => ['class' => 'coach-tier-display']
            ]);
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
        $credits = intersoccer_get_coach_credits($user_id);
        $tier = intersoccer_get_coach_tier($user_id);
        $referral_link = method_exists('InterSoccer_Referral_Handler', 'generate_coach_referral_link') 
            ? InterSoccer_Referral_Handler::generate_coach_referral_link($user_id)
            : home_url('/?ref=coach_' . $user_id);
        ?>
        <div class="wrap coach-dashboard">
            <h1>Welcome back, <?php echo wp_get_current_user()->display_name; ?>! 👋</h1>
            
            <div class="coach-welcome-banner">
                <div class="coach-stats-overview">
                    <div class="stat-box">
                        <span class="stat-number"><?php echo number_format($credits, 0); ?> CHF</span>
                        <span class="stat-label">Total Credits</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-number tier-<?php echo strtolower($tier); ?>"><?php echo $tier; ?></span>
                        <span class="stat-label">Current Tier</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-number"><?php echo $this->get_coach_referral_count($user_id); ?></span>
                        <span class="stat-label">Total Referrals</span>
                    </div>
                </div>
            </div>
            
            <div class="coach-quick-actions">
                <a href="#" class="coach-action-btn primary" onclick="copyReferralLink()">
                    📋 Copy Referral Link
                </a>
                <a href="<?php echo admin_url('admin.php?page=intersoccer-coach-referrals'); ?>" class="coach-action-btn">
                    👥 View My Referrals
                </a>
                <a href="<?php echo admin_url('admin.php?page=intersoccer-coach-resources'); ?>" class="coach-action-btn">
                    📚 Marketing Resources
                </a>
            </div>
            
            <script>
            function copyReferralLink() {
                navigator.clipboard.writeText('<?php echo esc_js($referral_link); ?>');
                alert('Referral link copied to clipboard!');
            }
            </script>
        </div>
        <?php
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
        // Implementation for coach profile editing
        echo '<div class="wrap"><h1>My Profile</h1><p>Profile editing coming soon...</p></div>';
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
     * Enqueue coach-specific admin styles
     */
    public function enqueue_coach_admin_styles($hook) {
        if (current_user_can('coach') && !current_user_can('manage_options')) {
            wp_enqueue_style('coach-admin-styles', INTERSOCCER_REFERRAL_URL . 'assets/css/coach-admin.css', [], INTERSOCCER_REFERRAL_VERSION);
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
}
