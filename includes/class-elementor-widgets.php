<?php
/**
 * File: includes/class-elementor-widgets.php
 * InterSoccer Elementor Widgets Integration
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class InterSoccer_Elementor_Integration {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Log constructor call
        error_log('InterSoccer: Elementor Integration class instantiated');
        // Register hooks only if Elementor is available
        if (class_exists('Elementor\Widget_Base')) {
            add_action('elementor/widgets/register', [$this, 'register_widgets'], 10);
            add_action('elementor/elements/categories_registered', [$this, 'add_widget_categories'], 10);
            add_action('wp_ajax_intersoccer_elementor_preview', [$this, 'handle_elementor_preview']);
            add_action('wp_ajax_nopriv_intersoccer_elementor_preview', [$this, 'handle_elementor_preview']);
            error_log('InterSoccer: Elementor Widgets have been successfully loaded');
        } else {
            error_log('InterSoccer: Elementor\Widget_Base not found, skipping widget hooks');
        }
    }

    
    public function add_widget_categories($elements_manager) {
        error_log('InterSoccer: Adding widget category');
        
        $elements_manager->add_category(
            'intersoccer-widgets',
            [
                'title' => __('InterSoccer Widgets', 'intersoccer-referral'),
                'icon' => 'eicon-posts-ticker',
            ]
        );
        
        error_log('InterSoccer: Widget category added');
    }
    
    public function register_widgets($widgets_manager) {
        error_log('InterSoccer: Widget registration hook fired');

        // Ensure widgets manager is valid
        if (!$widgets_manager || !method_exists($widgets_manager, 'register')) {
            error_log('InterSoccer: Invalid widgets manager');
            return;
        }

        // Register widgets
        try {
            $widgets_manager->register(new InterSoccer_Customer_Dashboard_Widget());
            error_log('InterSoccer: Registered widget: intersoccer_customer_dashboard');

            $widgets_manager->register(new InterSoccer_Coach_Dashboard_Widget());
            error_log('InterSoccer: Registered widget: intersoccer_coach_dashboard');

            $widgets_manager->register(new InterSoccer_Referral_Stats_Widget());
            error_log('InterSoccer: Registered widget: intersoccer_referral_stats');

            $widgets_manager->register(new InterSoccer_Coach_Leaderboard_Widget());
            error_log('InterSoccer: Registered widget: intersoccer_coach_leaderboard');

            $widgets_manager->register(new InterSoccer_Customer_Progress_Widget());
            error_log('InterSoccer: Registered widget: intersoccer_customer_progress');
            
        } catch (Exception $e) {
            error_log('InterSoccer: Widget registration failed: ' . $e->getMessage());
        }

        error_log('InterSoccer: Widget registration completed');
    }
    
    public function handle_elementor_preview() {
        check_ajax_referer('intersoccer_elementor_nonce', 'nonce');

        $widget_type = sanitize_text_field($_POST['widget_type']);
        $settings = $_POST['settings'] ?? [];

        switch ($widget_type) {
            case 'intersoccer_customer_dashboard':
                wp_send_json_success($this->get_customer_preview_data($settings));
                break;
            case 'intersoccer_coach_dashboard':
                wp_send_json_success($this->get_coach_preview_data($settings));
                break;
            default:
                wp_send_json_error('Invalid widget type');
        }
    }
    
    private function get_customer_preview_data($settings) {
        return [
            'credits' => 1250.50,
            'referrals_count' => 8,
            'badges_count' => 4,
            'next_milestone' => 2000,
            'partnership_coach' => 'Maria Silva',
            'tier' => 'Gold'
        ];
    }
    
    private function get_coach_preview_data($settings) {
        return [
            'credits' => 2750.00,
            'active_referrals' => 15,
            'conversion_rate' => 68,
            'tier' => 'Platinum',
            'monthly_earnings' => 890.50
        ];
    }
}

// Customer Dashboard Widget
class InterSoccer_Customer_Dashboard_Widget extends \Elementor\Widget_Base {
    
    public function get_name() {
        return 'intersoccer_customer_dashboard';
    }
    
    public function get_title() {
        return __('Customer Referral Dashboard', 'intersoccer-referral');
    }
    
    public function get_icon() {
        return 'eicon-dashboard';
    }
    
    public function get_categories() {
        return ['intersoccer-widgets'];
    }
    
    protected function _register_controls() {
        // Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Dashboard Settings', 'intersoccer-referral'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'show_header',
            [
                'label' => __('Show Header', 'intersoccer-referral'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'intersoccer-referral'),
                'label_off' => __('Hide', 'intersoccer-referral'),
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'dashboard_sections',
            [
                'label' => __('Sections to Display', 'intersoccer-referral'),
                'type' => \Elementor\Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => [
                    'stats' => __('Statistics Cards', 'intersoccer-referral'),
                    'coach_partnership' => __('Coach Partnership', 'intersoccer-referral'),
                    'badges' => __('Achievement Badges', 'intersoccer-referral'),
                    'referral_link' => __('Referral Link', 'intersoccer-referral'),
                    'progress' => __('Progress Tracker', 'intersoccer-referral'),
                    'gift_credits' => __('Gift Credits', 'intersoccer-referral'),
                    'activity_feed' => __('Recent Activity', 'intersoccer-referral'),
                ],
                'default' => ['stats', 'coach_partnership', 'referral_link', 'progress'],
            ]
        );
        
        $this->add_control(
            'layout_style',
            [
                'label' => __('Layout Style', 'intersoccer-referral'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'default' => __('Default Grid', 'intersoccer-referral'),
                    'compact' => __('Compact View', 'intersoccer-referral'),
                    'cards' => __('Individual Cards', 'intersoccer-referral'),
                    'tabs' => __('Tabbed Interface', 'intersoccer-referral'),
                ],
                'default' => 'default',
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section
        $this->start_controls_section(
            'style_section',
            [
                'label' => __('Styling', 'intersoccer-referral'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'primary_color',
            [
                'label' => __('Primary Color', 'intersoccer-referral'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#667eea',
            ]
        );
        
        $this->add_control(
            'accent_color',
            [
                'label' => __('Accent Color', 'intersoccer-referral'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#28a745',
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'heading_typography',
                'label' => __('Heading Typography', 'intersoccer-referral'),
                'selector' => '{{WRAPPER}} .dashboard-header h2, {{WRAPPER}} h3',
            ]
        );
        
        $this->add_control(
            'card_border_radius',
            [
                'label' => __('Card Border Radius', 'intersoccer-referral'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 30,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 12,
                ],
                'selectors' => [
                    '{{WRAPPER}} .stat-card, {{WRAPPER}} .intersoccer-customer-dashboard > div' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'card_shadow',
                'label' => __('Card Shadow', 'intersoccer-referral'),
                'selector' => '{{WRAPPER}} .stat-card, {{WRAPPER}} .intersoccer-customer-dashboard > div',
            ]
        );
        
        $this->end_controls_section();
        
        // Animation Section
        $this->start_controls_section(
            'animation_section',
            [
                'label' => __('Animations', 'intersoccer-referral'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'enable_animations',
            [
                'label' => __('Enable Animations', 'intersoccer-referral'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'animation_style',
            [
                'label' => __('Animation Style', 'intersoccer-referral'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'fade-in' => __('Fade In', 'intersoccer-referral'),
                    'slide-up' => __('Slide Up', 'intersoccer-referral'),
                    'scale-in' => __('Scale In', 'intersoccer-referral'),
                    'bounce-in' => __('Bounce In', 'intersoccer-referral'),
                ],
                'default' => 'fade-in',
                'condition' => [
                    'enable_animations' => 'yes',
                ],
            ]
        );
        
        $this->end_controls_section();
    }
    
    protected function render() {
        $settings = $this->get_settings_for_display();

        if (!is_user_logged_in()) {
            echo '<div class="intersoccer-login-notice"><p>' . esc_html__('Please log in to view your referral dashboard.', 'intersoccer-referral') . '</p></div>';
            return;
        }

        // Enqueue Elementor-specific assets
        wp_enqueue_style('intersoccer-elementor-dashboard', INTERSOCCER_REFERRAL_URL . 'assets/css/elementor-dashboard.css', [], INTERSOCCER_REFERRAL_VERSION);
        wp_enqueue_script('intersoccer-elementor-dashboard', INTERSOCCER_REFERRAL_URL . 'assets/js/elementor-dashboard.js', ['jquery'], INTERSOCCER_REFERRAL_VERSION, true);
        wp_localize_script('intersoccer-elementor-dashboard', 'intersoccer_elementor', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('intersoccer_dashboard_nonce'),
            'strings' => [
                'copy_success' => __('Link copied to clipboard!', 'intersoccer-referral'),
                'error' => __('An error occurred', 'intersoccer-referral'),
            ]
        ]);

        // Render dashboard with selected sections
        $dashboard = new InterSoccer_Referral_Dashboard();
        $output = $dashboard->render_customer_dashboard();

        // Apply section visibility
        $sections = $settings['dashboard_sections'] ?? [];
        if (!in_array('stats', $sections)) {
            $output = preg_replace('/<div class="dashboard-stats">.*?<\/div>/s', '', $output);
        }
        if (!in_array('coach_partnership', $sections)) {
            $output = preg_replace('/<div class="coach-partnership-section">.*?<\/div>/s', '', $output);
        }
        if (!in_array('badges', $sections)) {
            $output = preg_replace('/<div class="badges-section">.*?<\/div>/s', '', $output);
        }
        if (!in_array('referral_link', $sections)) {
            $output = preg_replace('/<div class="referral-section">.*?<\/div>/s', '', $output);
        }
        if (!in_array('progress', $sections)) {
            $output = preg_replace('/<div class="progress-section">.*?<\/div>/s', '', $output);
        }
        if (!in_array('gift_credits', $sections)) {
            $output = preg_replace('/<div class="gift-section">.*?<\/div>/s', '', $output);
        }
        if (!in_array('activity_feed', $sections)) {
            $output = preg_replace('/<div class="activity-section">.*?<\/div>/s', '', $output);
        }

        // Apply layout style
        $layout = $settings['layout_style'] ?? 'default';
        $output = str_replace('intersoccer-customer-dashboard', "intersoccer-customer-dashboard dashboard-wrapper layout-{$layout}", $output);

        echo $output;
    }
    
    private function get_customer_credits_safe($user_id) {
        $credits = get_user_meta($user_id, 'intersoccer_customer_credits', true);
        return is_numeric($credits) ? (float) $credits : 0.0;
    }
    
    private function get_customer_referral_link_safe($user_id) {
        if (class_exists('InterSoccer_Referral_Handler')) {
            return InterSoccer_Referral_Handler::generate_customer_referral_link($user_id);
        }
        return home_url('?ref=' . base64_encode('customer_' . $user_id));
    }
    
    private function get_partnership_data_safe($user_id) {
        return [
            'coach_id' => get_user_meta($user_id, 'intersoccer_partnership_coach_id', true) ?: null,
            'start_date' => get_user_meta($user_id, 'intersoccer_partnership_start', true) ?: null,
            'order_count' => get_user_meta($user_id, 'intersoccer_partnership_orders', true) ?: 0,
            'cooldown_end' => get_user_meta($user_id, 'intersoccer_partnership_cooldown_end', true) ?: null,
        ];
    }
    
    private function get_customer_badges_safe($user_id, $total_referrals, $credits) {
        $badges = [];
        
        if ($total_referrals >= 1) {
            $badges[] = [
                'name' => 'First Friend',
                'icon' => '🤝',
                'class' => 'first-referral',
                'description' => 'Made your first referral',
                'is_new' => false
            ];
        }
        
        if ($total_referrals >= 5) {
            $badges[] = [
                'name' => 'Top Referrer',
                'icon' => '🌟',
                'class' => 'top-referrer',
                'description' => 'Referred 5+ friends',
                'is_new' => false
            ];
        }
        
        if ($credits >= 500) {
            $badges[] = [
                'name' => '500 CHF Club',
                'icon' => '💎',
                'class' => 'milestone-500',
                'description' => 'Earned 500+ CHF in credits',
                'is_new' => false
            ];
        }
        
        return $badges;
    }
    
    private function get_recent_customer_activity_safe($user_id) {
        return [
            [
                'icon' => '🎉',
                'message' => 'Welcome bonus earned!',
                'time' => '2 hours ago',
                'points' => 50
            ],
            [
                'icon' => '👥',
                'message' => 'Friend joined via your link',
                'time' => '1 day ago',
                'points' => 500
            ]
        ];
    }
    
    private function render_stats_section($credits, $total_referrals, $partnership_data) {
        echo '<div class="dashboard-stats">';
        
        // Credits Card
        echo '<div class="stat-card credits-card" data-credits="' . $credits . '">';
        echo '<div class="stat-icon">💰</div>';
        echo '<div class="stat-content">';
        echo '<span class="stat-number" id="credits-display">' . number_format($credits, 2) . '</span>';
        echo '<span class="stat-label">CHF Credits</span>';
        if ($credits > 0) {
            echo '<div class="credit-pulse"></div>';
        }
        echo '</div></div>';
        
        // Referrals Card
        echo '<div class="stat-card referrals-card">';
        echo '<div class="stat-icon">👥</div>';
        echo '<div class="stat-content">';
        echo '<span class="stat-number">' . $total_referrals . '</span>';
        echo '<span class="stat-label">Friends Referred</span>';
        echo '</div></div>';
        
        // Partnership Card (if applicable)
        if ($partnership_data['coach_id']) {
            echo '<div class="stat-card partnership-card">';
            echo '<div class="stat-icon">🤝</div>';
            echo '<div class="stat-content">';
            echo '<span class="stat-number">' . $partnership_data['order_count'] . '</span>';
            echo '<span class="stat-label">Partnership Orders</span>';
            echo '</div></div>';
        }
        
        echo '</div>';
    }
    
    private function render_coach_partnership_section($partnership_data, $user_id) {
        echo '<div class="coach-partnership-section">';
        echo '<h3>🎯 Your Coach Connection</h3>';
        
        if ($partnership_data['coach_id']) {
            $coach = get_user_by('ID', $partnership_data['coach_id']);
            if ($coach) {
                $tier = $this->get_coach_tier_safe($partnership_data['coach_id']);
                $partnership_duration = $partnership_data['start_date'] ? 
                    human_time_diff(strtotime($partnership_data['start_date'])) : 'Recently';
                
                echo '<div class="current-partnership">';
                echo '<div class="coach-info">';
                echo '<div class="coach-avatar">';
                echo get_avatar($coach->ID, 60);
                echo '<div class="coach-tier-badge ' . strtolower($tier) . '">' . $tier . '</div>';
                echo '</div>';
                echo '<div class="coach-details">';
                echo '<h4>' . $coach->display_name . '</h4>';
                echo '<p class="coach-specialty">' . (get_user_meta($coach->ID, 'coach_specialty', true) ?: 'General Training') . '</p>';
                echo '<p class="partnership-info">';
                echo '<span class="partnership-duration">Connected for ' . $partnership_duration . '</span>';
                echo '<span class="partnership-commission">Earning 5% commission on your purchases</span>';
                echo '</p>';
                echo '</div></div>';
                
                echo '<div class="partnership-actions">';
                if ($partnership_data['cooldown_end'] && strtotime($partnership_data['cooldown_end']) > time()) {
                    echo '<div class="cooldown-notice">';
                    echo '<span class="cooldown-icon">⏳</span>';
                    echo '<span>Coach change available in ' . human_time_diff(time(), strtotime($partnership_data['cooldown_end'])) . '</span>';
                    echo '</div>';
                } else {
                    echo '<button class="change-coach-btn" onclick="showCoachSelection()">Change Coach Connection</button>';
                }
                echo '</div></div>';
            }
        } else {
            echo '<div class="no-partnership">';
            echo '<div class="partnership-intro">';
            echo '<h4>Connect with a Coach Partner</h4>';
            echo '<p>Choose a coach to support with every purchase. They\'ll earn 5% commission and provide you with personalized guidance.</p>';
            echo '<button class="select-coach-btn" onclick="showCoachSelection()">Choose Your Coach Partner</button>';
            echo '</div></div>';
        }
        
        echo '</div>';
    }
    
    private function render_badges_section($badges) {
        echo '<div class="badges-section">';
        echo '<h3>🏅 Your Achievements</h3>';
        echo '<div class="badges-container">';
        
        foreach ($badges as $badge) {
            echo '<div class="badge-item ' . $badge['class'] . '" title="' . esc_attr($badge['description']) . '">';
            echo '<span class="badge-icon">' . $badge['icon'] . '</span>';
            echo '<span class="badge-name">' . $badge['name'] . '</span>';
            if ($badge['is_new']) {
                echo '<div class="badge-new-indicator">NEW!</div>';
            }
            echo '</div>';
        }
        
        echo '</div></div>';
    }
    
    private function render_referral_link_section($referral_link) {
        echo '<div class="referral-section">';
        echo '<h3>📤 Share & Earn</h3>';
        echo '<div class="referral-info">';
        echo '<p class="referral-description">';
        echo '<span class="highlight">Earn 500 points (50 CHF)</span> for every friend who joins InterSoccer! Share your personalized link:';
        echo '</p></div>';
        
        echo '<div class="referral-link-container">';
        echo '<input type="text" id="referral-link" value="' . esc_attr($referral_link) . '" readonly>';
        echo '<button id="copy-link-btn" class="copy-button" onclick="copyReferralLink()">';
        echo '<span class="button-text">📋 Copy</span>';
        echo '<span class="button-success">✅ Copied!</span>';
        echo '</button></div>';
        
        echo '<div class="social-share-buttons">';
        echo '<a href="https://wa.me/?text=' . urlencode("Join me at InterSoccer for amazing soccer training! " . $referral_link) . '" target="_blank" class="social-btn whatsapp-btn">📱 WhatsApp</a>';
        echo '<a href="https://www.facebook.com/sharer/sharer.php?u=' . urlencode($referral_link) . '" target="_blank" class="social-btn facebook-btn">📘 Facebook</a>';
        echo '<a href="mailto:?subject=' . urlencode('Join InterSoccer!') . '&body=' . urlencode("I thought you'd love InterSoccer's soccer training programs! Join here: " . $referral_link) . '" class="social-btn email-btn">📧 Email</a>';
        echo '</div></div>';
    }
    
    private function render_progress_section($credits) {
        echo '<div class="progress-section">';
        echo '<h3>📈 Your Progress</h3>';
        echo '<div class="progress-container">';
        
        $next_milestone = 1000;
        $progress_percentage = min(100, ($credits / $next_milestone) * 100);
        
        echo '<div class="progress-bar-wrapper">';
        echo '<div class="progress-info">';
        echo '<span>Next milestone: 1000 points for 100 CHF bonus!</span>';
        echo '<span class="progress-percentage">' . round($progress_percentage) . '%</span>';
        echo '</div>';
        echo '<div class="progress-bar">';
        echo '<div class="progress-fill" style="width: ' . $progress_percentage . '%"></div>';
        echo '</div></div>';
        
        echo '<div class="milestones">';
        $milestones = [500, 1000, 2000];
        $milestone_icons = ['🥉', '🥈', '🥇'];
        
        foreach ($milestones as $i => $milestone) {
            $achieved_class = $credits >= $milestone ? 'achieved' : '';
            echo '<div class="milestone ' . $achieved_class . '">';
            echo '<span class="milestone-icon">' . $milestone_icons[$i] . '</span>';
            echo '<span class="milestone-text">' . $milestone . ' CHF</span>';
            echo '</div>';
        }
        
        echo '</div></div></div>';
    }
    
    private function render_gift_credits_section($credits) {
        echo '<div class="gift-section">';
        echo '<h3>🎁 Gift Credits</h3>';
        echo '<p>Spread the joy! Gift credits to friends and family (you get 20 points back!)</p>';
        
        echo '<form id="gift-credits" method="post" class="gift-form">';
        echo '<div class="form-row">';
        echo '<div class="form-group">';
        echo '<label>Gift Amount (50-' . min($credits, 500) . ' CHF):</label>';
        echo '<input type="number" name="gift_amount" min="50" max="' . min($credits, 500) . '" step="10" required>';
        echo '</div>';
        echo '<div class="form-group">';
        echo '<label>Recipient Email:</label>';
        echo '<input type="email" name="recipient_email" placeholder="friend@example.com" required>';
        echo '</div></div>';
        echo '<button type="submit" class="gift-button">🎁 Send Gift</button>';
        echo '</form></div>';
    }
    
    private function render_activity_feed_section($recent_activity) {
        echo '<div class="activity-section">';
        echo '<h3>📋 Recent Activity</h3>';
        echo '<div class="activity-feed">';
        
        foreach ($recent_activity as $activity) {
            echo '<div class="activity-item">';
            echo '<span class="activity-icon">' . $activity['icon'] . '</span>';
            echo '<div class="activity-content">';
            echo '<p>' . $activity['message'] . '</p>';
            echo '<span class="activity-time">' . $activity['time'] . '</span>';
            echo '</div>';
            if (isset($activity['points'])) {
                echo '<span class="activity-points">+' . $activity['points'] . ' CHF</span>';
            }
            echo '</div>';
        }
        
        echo '</div></div>';
    }
    
    private function get_coach_tier_safe($coach_id) {
        if (function_exists('intersoccer_get_coach_tier')) {
            return intersoccer_get_coach_tier($coach_id);
        }
        return 'Bronze'; // Default fallback
    }
    
    private function enqueue_dashboard_assets() {
        wp_enqueue_style(
            'intersoccer-elementor-dashboard',
            INTERSOCCER_REFERRAL_URL . 'assets/css/elementor-dashboard.css',
            [],
            INTERSOCCER_REFERRAL_VERSION
        );
        
        wp_enqueue_script(
            'intersoccer-elementor-dashboard',
            INTERSOCCER_REFERRAL_URL . 'assets/js/elementor-dashboard.js',
            ['jquery'],
            INTERSOCCER_REFERRAL_VERSION,
            true
        );
        
        wp_localize_script('intersoccer-elementor-dashboard', 'intersoccer_elementor', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('intersoccer_elementor_nonce'),
            'strings' => [
                'copy_success' => __('Link copied!', 'intersoccer-referral'),
                'copy_error' => __('Failed to copy', 'intersoccer-referral'),
                'loading' => __('Loading...', 'intersoccer-referral'),
            ]
        ]);
    }
}

// AJAX Handlers for Preview
add_action('wp_ajax_registerintersoccer_elementor_preview', 'intersoccer_handle_elementor_preview');
add_action('wp_ajax_nopriv_intersoccer_elementor_preview', 'intersoccer_handle_elementor_preview');

function intersoccer_handle_elementor_preview() {
    check_ajax_referer('intersoccer_elementor_nonce', 'nonce');

    $widget_type = sanitize_text_field($_POST['widget_type']);
    $settings = $_POST['settings'] ?? [];

    switch ($widget_type) {
        case 'intersoccer_customer_dashboard':
            wp_send_json_success([
                'credits' => 1250.50,
                'referrals_count' => 8,
                'badges_count' => 4,
                'next_milestone' => 2000,
                'partnership_coach' => 'Maria Silva',
                'tier' => 'Gold'
            ]);
            break;
        case 'intersoccer_coach_dashboard':
            wp_send_json_success([
                'credits' => 2750.00,
                'active_referrals' => 15,
                'conversion_rate' => 68,
                'tier' => 'Platinum',
                'monthly_earnings' => 890.50
            ]);
            break;
        default:
            wp_send_json_error('Invalid widget type');
    }
}

// Coach Dashboard Widget
class InterSoccer_Coach_Dashboard_Widget extends \Elementor\Widget_Base {
    
    public function get_name() {
        return 'intersoccer_coach_dashboard';
    }
    
    public function get_title() {
        return __('Coach Referral Dashboard', 'intersoccer-referral');
    }
    
    public function get_icon() {
        return 'eicon-trophy';
    }
    
    public function get_categories() {
        return ['intersoccer-widgets'];
    }
    
    protected function _register_controls() {
        // Similar control structure to customer widget
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Dashboard Settings', 'intersoccer-referral'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'coach_sections',
            [
                'label' => __('Coach Dashboard Sections', 'intersoccer-referral'),
                'type' => \Elementor\Controls_Manager::SELECT2,
                'multiple' => true,
                'options' => [
                    'stats' => __('Performance Statistics', 'intersoccer-referral'),
                    'earnings' => __('Earnings Overview', 'intersoccer-referral'),
                    'referrals' => __('Recent Referrals', 'intersoccer-referral'),
                    'tier_progress' => __('Tier Progress', 'intersoccer-referral'),
                    'marketing_tools' => __('Marketing Tools', 'intersoccer-referral'),
                    'leaderboard' => __('Coach Leaderboard', 'intersoccer-referral'),
                ],
                'default' => ['stats', 'earnings', 'referrals', 'tier_progress'],
            ]
        );
        
        $this->end_controls_section();
        
        // Style controls similar to customer dashboard
        $this->start_controls_section(
            'style_section',
            [
                'label' => __('Styling', 'intersoccer-referral'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'primary_color',
            [
                'label' => __('Primary Color', 'intersoccer-referral'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#667eea',
            ]
        );
        
        $this->end_controls_section();
    }
    
    protected function render() {
        if (!is_user_logged_in() || !current_user_can('view_referral_dashboard')) {
            echo '<div class="intersoccer-access-denied">';
            echo '<p>' . __('Access denied. Coach privileges required.', 'intersoccer-referral') . '</p>';
            echo '</div>';
            return;
        }
        
        $settings = $this->get_settings_for_display();
        $user_id = get_current_user_id();
        
        // Get coach data
        $credits = get_user_meta($user_id, 'intersoccer_credits', true) ?: 0;
        $tier = $this->get_coach_tier_safe($user_id);
        $referrals = $this->get_recent_referrals_safe($user_id);
        $referral_link = $this->get_coach_referral_link_safe($user_id);
        
        echo '<div class="intersoccer-coach-dashboard elementor-widget">';
        
        $sections = $settings['coach_sections'];
        
        if (in_array('stats', $sections)) {
            $this->render_coach_stats($user_id, $credits, $tier);
        }
        
        if (in_array('earnings', $sections)) {
            $this->render_earnings_section($credits, $user_id);
        }
        
        if (in_array('referrals', $sections)) {
            $this->render_referrals_section($referrals);
        }
        
        if (in_array('tier_progress', $sections)) {
            $this->render_tier_progress($user_id, $tier);
        }
        
        if (in_array('marketing_tools', $sections)) {
            $this->render_marketing_tools($referral_link);
        }
        
        echo '</div>';
    }
    
    private function get_coach_referral_link_safe($coach_id) {
        if (class_exists('InterSoccer_Referral_Handler')) {
            return InterSoccer_Referral_Handler::generate_coach_referral_link($coach_id);
        }
        return home_url('?ref=' . base64_encode('coach_' . $coach_id));
    }
    
    private function get_recent_referrals_safe($coach_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return []; // Table doesn't exist yet
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE coach_id = %d ORDER BY created_at DESC LIMIT 5",
            $coach_id
        )) ?: [];
    }
    
    private function render_coach_stats($user_id, $credits, $tier) {
        echo '<div class="coach-stats-section">';
        echo '<h3>Performance Overview</h3>';
        echo '<div class="coach-stats-grid">';
        
        echo '<div class="stat-card">';
        echo '<div class="stat-icon">💰</div>';
        echo '<div class="stat-content">';
        echo '<span class="stat-number">' . number_format($credits, 2) . '</span>';
        echo '<span class="stat-label">CHF Credits</span>';
        echo '</div></div>';
        
        echo '<div class="stat-card">';
        echo '<div class="stat-icon">🏆</div>';
        echo '<div class="stat-content">';
        echo '<span class="stat-number">' . $tier . '</span>';
        echo '<span class="stat-label">Current Tier</span>';
        echo '</div></div>';
        
        echo '</div></div>';
    }
    
    private function render_earnings_section($credits, $user_id) {
        echo '<div class="earnings-section">';
        echo '<h3>Earnings Overview</h3>';
        echo '<div class="earnings-summary">';
        echo '<p>Total Credits: <strong>' . number_format($credits, 2) . ' CHF</strong></p>';
        echo '</div></div>';
    }
    
    private function render_referrals_section($referrals) {
        echo '<div class="referrals-section">';
        echo '<h3>Recent Referrals</h3>';
        
        if (empty($referrals)) {
            echo '<p>No referrals yet. Start sharing your link!</p>';
            return;
        }
        
        echo '<div class="referrals-list">';
        foreach ($referrals as $referral) {
            echo '<div class="referral-item">';
            echo '<span class="referral-date">' . date('M d', strtotime($referral->created_at)) . '</span>';
            echo '<span class="referral-amount">' . number_format($referral->commission_amount, 2) . ' CHF</span>';
            echo '<span class="referral-status ' . $referral->status . '">' . ucfirst($referral->status) . '</span>';
            echo '</div>';
        }
        echo '</div></div>';
    }
    
    private function render_tier_progress($user_id, $current_tier) {
        echo '<div class="tier-progress-section">';
        echo '<h3>Tier Progress</h3>';
        echo '<div class="tier-indicator">';
        echo '<span class="current-tier">Current: ' . $current_tier . '</span>';
        echo '</div></div>';
    }
    
    private function render_marketing_tools($referral_link) {
        echo '<div class="marketing-tools-section">';
        echo '<h3>Marketing Tools</h3>';
        echo '<div class="referral-link-container">';
        echo '<input type="text" value="' . esc_attr($referral_link) . '" readonly>';
        echo '<button onclick="copyReferralLink()">Copy Link</button>';
        echo '</div></div>';
    }
}

// Referral Stats Widget
class InterSoccer_Referral_Stats_Widget extends \Elementor\Widget_Base {
    
    public function get_name() {
        return 'intersoccer_referral_stats';
    }
    
    public function get_title() {
        return __('Referral Statistics', 'intersoccer-referral');
    }
    
    public function get_icon() {
        return 'eicon-counter';
    }
    
    public function get_categories() {
        return ['intersoccer-widgets'];
    }
    
    protected function _register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Statistics Settings', 'intersoccer-referral'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'stat_type',
            [
                'label' => __('Statistic Type', 'intersoccer-referral'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'credits' => __('Total Credits', 'intersoccer-referral'),
                    'referrals' => __('Total Referrals', 'intersoccer-referral'),
                    'conversions' => __('Conversion Rate', 'intersoccer-referral'),
                    'tier' => __('Current Tier', 'intersoccer-referral'),
                ],
                'default' => 'credits',
            ]
        );
        
        $this->add_control(
            'show_icon',
            [
                'label' => __('Show Icon', 'intersoccer-referral'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'custom_icon',
            [
                'label' => __('Custom Icon', 'intersoccer-referral'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'condition' => [
                    'show_icon' => 'yes',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Style section
        $this->start_controls_section(
            'style_section',
            [
                'label' => __('Styling', 'intersoccer-referral'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_control(
            'number_color',
            [
                'label' => __('Number Color', 'intersoccer-referral'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .stat-number' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'number_typography',
                'selector' => '{{WRAPPER}} .stat-number',
            ]
        );
        
        $this->end_controls_section();
    }
    
    protected function render() {
        $settings = $this->get_settings_for_display();
        $user_id = get_current_user_id();
        
        if (!is_user_logged_in()) {
            echo '<div class="intersoccer-stat-widget login-required">';
            echo '<p>' . __('Login required', 'intersoccer-referral') . '</p>';
            echo '</div>';
            return;
        }
        
        $stat_value = $this->get_stat_value($settings['stat_type'], $user_id);
        $stat_label = $this->get_stat_label($settings['stat_type']);
        
        echo '<div class="intersoccer-stat-widget">';
        
        if ($settings['show_icon'] === 'yes' && !empty($settings['custom_icon']['value'])) {
            echo '<div class="stat-icon">';
            \Elementor\Icons_Manager::render_icon($settings['custom_icon']);
            echo '</div>';
        }
        
        echo '<div class="stat-content">';
        echo '<span class="stat-number">' . $stat_value . '</span>';
        echo '<span class="stat-label">' . $stat_label . '</span>';
        echo '</div>';
        
        echo '</div>';
    }
    
    private function get_stat_value($type, $user_id) {
        switch ($type) {
            case 'credits':
                if (current_user_can('view_referral_dashboard')) {
                    return number_format(get_user_meta($user_id, 'intersoccer_credits', true) ?: 0, 2);
                } else {
                    return number_format(get_user_meta($user_id, 'intersoccer_customer_credits', true) ?: 0, 2);
                }
            case 'referrals':
                $referrals = get_user_meta($user_id, 'intersoccer_referrals_made', true) ?: [];
                return count($referrals);
            case 'tier':
                return $this->get_coach_tier_safe($user_id);
            case 'conversions':
                return '0%'; // Placeholder
            default:
                return '0';
        }
    }
    
    private function get_stat_label($type) {
        switch ($type) {
            case 'credits':
                return __('CHF Credits', 'intersoccer-referral');
            case 'referrals':
                return __('Referrals', 'intersoccer-referral');
            case 'tier':
                return __('Current Tier', 'intersoccer-referral');
            case 'conversions':
                return __('Conversion Rate', 'intersoccer-referral');
            default:
                return __('Statistic', 'intersoccer-referral');
        }
    }
}

// Coach Leaderboard Widget
class InterSoccer_Coach_Leaderboard_Widget extends \Elementor\Widget_Base {
    
    public function get_name() {
        return 'intersoccer_coach_leaderboard';
    }
    
    public function get_title() {
        return __('Coach Leaderboard', 'intersoccer-referral');
    }
    
    public function get_icon() {
        return 'eicon-ranking';
    }
    
    public function get_categories() {
        return ['intersoccer-widgets'];
    }
    
    protected function _register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Leaderboard Settings', 'intersoccer-referral'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'show_count',
            [
                'label' => __('Number of Coaches to Show', 'intersoccer-referral'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => 3,
                'max' => 20,
                'default' => 10,
            ]
        );
        
        $this->add_control(
            'ranking_criteria',
            [
                'label' => __('Ranking Criteria', 'intersoccer-referral'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'referrals' => __('Total Referrals', 'intersoccer-referral'),
                    'credits' => __('Total Credits', 'intersoccer-referral'),
                    'monthly_referrals' => __('Monthly Referrals', 'intersoccer-referral'),
                ],
                'default' => 'referrals',
            ]
        );
        
        $this->add_control(
            'show_avatars',
            [
                'label' => __('Show Avatars', 'intersoccer-referral'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );
        
        $this->end_controls_section();
    }
    
    protected function render() {
        $settings = $this->get_settings_for_display();
        $leaderboard_data = $this->get_leaderboard_data($settings);
        
        echo '<div class="intersoccer-coach-leaderboard">';
        echo '<h3>' . __('Top Coaches', 'intersoccer-referral') . '</h3>';
        
        if (empty($leaderboard_data)) {
            echo '<p>' . __('No data available yet.', 'intersoccer-referral') . '</p>';
            echo '</div>';
            return;
        }
        
        echo '<div class="leaderboard-list">';
        
        foreach ($leaderboard_data as $index => $coach) {
            $rank = $index + 1;
            echo '<div class="leaderboard-item rank-' . $rank . '">';
            
            echo '<div class="rank-number">' . $rank . '</div>';
            
            if ($settings['show_avatars'] === 'yes') {
                echo '<div class="coach-avatar">';
                echo get_avatar($coach['ID'], 40);
                echo '</div>';
            }
            
            echo '<div class="coach-info">';
            echo '<span class="coach-name">' . esc_html($coach['display_name']) . '</span>';
            echo '<span class="coach-stat">' . $coach['stat_value'] . '</span>';
            echo '</div>';
            
            if ($rank <= 3) {
                $medals = ['🥇', '🥈', '🥉'];
                echo '<div class="medal">' . $medals[$rank - 1] . '</div>';
            }
            
            echo '</div>';
        }
        
        echo '</div></div>';
    }
    
    private function get_leaderboard_data($settings) {
        global $wpdb;
        
        $coaches = get_users(['role' => 'coach']);
        $leaderboard = [];
        
        foreach ($coaches as $coach) {
            $stat_value = 0;
            
            switch ($settings['ranking_criteria']) {
                case 'credits':
                    $stat_value = (float) get_user_meta($coach->ID, 'intersoccer_credits', true);
                    break;
                case 'referrals':
                    $table_name = $wpdb->prefix . 'intersoccer_referrals';
                    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                        $stat_value = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM $table_name WHERE coach_id = %d",
                            $coach->ID
                        ));
                    }
                    break;
                case 'monthly_referrals':
                    $table_name = $wpdb->prefix . 'intersoccer_referrals';
                    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
                        $stat_value = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM $table_name WHERE coach_id = %d AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
                            $coach->ID
                        ));
                    }
                    break;
            }
            
            $leaderboard[] = [
                'ID' => $coach->ID,
                'display_name' => $coach->display_name,
                'stat_value' => $stat_value,
            ];
        }
        
        // Sort by stat value descending
        usort($leaderboard, function($a, $b) {
            return $b['stat_value'] <=> $a['stat_value'];
        });
        
        return array_slice($leaderboard, 0, $settings['show_count']);
    }
}

// Customer Progress Widget
class InterSoccer_Customer_Progress_Widget extends \Elementor\Widget_Base {
    
    public function get_name() {
        return 'intersoccer_customer_progress';
    }
    
    public function get_title() {
        return __('Customer Progress Tracker', 'intersoccer-referral');
    }
    
    public function get_icon() {
        return 'eicon-progress-tracker';
    }
    
    public function get_categories() {
        return ['intersoccer-widgets'];
    }
    
    protected function _register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Progress Settings', 'intersoccer-referral'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'progress_type',
            [
                'label' => __('Progress Type', 'intersoccer-referral'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => [
                    'credits' => __('Credits Progress', 'intersoccer-referral'),
                    'referrals' => __('Referrals Progress', 'intersoccer-referral'),
                    'tier' => __('Tier Progress', 'intersoccer-referral'),
                ],
                'default' => 'credits',
            ]
        );
        
        $this->add_control(
            'show_milestones',
            [
                'label' => __('Show Milestones', 'intersoccer-referral'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );
        
        $this->end_controls_section();
    }
    
    protected function render() {
        if (!is_user_logged_in()) {
            echo '<div class="intersoccer-progress-widget login-required">';
            echo '<p>' . __('Login required to view progress', 'intersoccer-referral') . '</p>';
            echo '</div>';
            return;
        }
        
        $settings = $this->get_settings_for_display();
        $user_id = get_current_user_id();
        
        echo '<div class="intersoccer-progress-widget">';
        
        if ($settings['progress_type'] === 'credits') {
            $this->render_credits_progress($user_id, $settings);
        } elseif ($settings['progress_type'] === 'referrals') {
            $this->render_referrals_progress($user_id, $settings);
        }
        
        echo '</div>';
    }
    
    private function render_credits_progress($user_id, $settings) {
        $credits = get_user_meta($user_id, 'intersoccer_customer_credits', true) ?: 0;
        $next_milestone = 1000;
        $progress_percentage = min(100, ($credits / $next_milestone) * 100);
        
        echo '<div class="progress-container">';
        echo '<h4>' . __('Credits Progress', 'intersoccer-referral') . '</h4>';
        echo '<div class="progress-bar">';
        echo '<div class="progress-fill" style="width: ' . $progress_percentage . '%"></div>';
        echo '</div>';
        echo '<div class="progress-info">';
        echo '<span>' . number_format($credits, 2) . ' / ' . $next_milestone . ' CHF</span>';
        echo '<span>' . round($progress_percentage) . '%</span>';
        echo '</div>';
        echo '</div>';
    }
    
    private function render_referrals_progress($user_id, $settings) {
        $referrals = get_user_meta($user_id, 'intersoccer_referrals_made', true) ?: [];
        $referral_count = count($referrals);
        $next_milestone = 5;
        $progress_percentage = min(100, ($referral_count / $next_milestone) * 100);
        
        echo '<div class="progress-container">';
        echo '<h4>' . __('Referral Progress', 'intersoccer-referral') . '</h4>';
        echo '<div class="progress-bar">';
        echo '<div class="progress-fill" style="width: ' . $progress_percentage . '%"></div>';
        echo '</div>';
        echo '<div class="progress-info">';
        echo '<span>' . $referral_count . ' / ' . $next_milestone . ' referrals</span>';
        echo '<span>' . round($progress_percentage) . '%</span>';
        echo '</div>';
        echo '</div>';
    }
}

// Add missing AJAX handlers for customer dashboard functionality
add_action('wp_ajax_get_available_coaches', 'intersoccer_handle_get_available_coaches');
add_action('wp_ajax_select_coach_partner', 'intersoccer_handle_select_coach_partner');
add_action('wp_ajax_gift_credits', 'intersoccer_handle_gift_credits');

function intersoccer_handle_get_available_coaches() {
    check_ajax_referer('intersoccer_dashboard_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not logged in']);
    }
    
    $search = sanitize_text_field($_POST['search'] ?? '');
    $filter = sanitize_text_field($_POST['filter'] ?? 'all');
    
    $args = ['role' => 'coach', 'number' => 50];
    
    if (!empty($search)) {
        $args['search'] = '*' . $search . '*';
        $args['search_columns'] = ['display_name', 'user_email'];
    }
    
    $coaches = get_users($args);
    $coach_data = [];
    
    foreach ($coaches as $coach) {
        $tier = function_exists('intersoccer_get_coach_tier') ? 
                intersoccer_get_coach_tier($coach->ID) : 'Bronze';
        $specialty = get_user_meta($coach->ID, 'coach_specialty', true) ?: 'General Training';
        $rating = get_user_meta($coach->ID, 'coach_rating', true) ?: '4.5';
        $total_athletes = get_user_meta($coach->ID, 'total_athletes', true) ?: rand(10, 100);
        
        // Apply filter
        if ($filter !== 'all') {
            if ($filter === 'youth' && !strpos(strtolower($specialty), 'youth')) continue;
            if ($filter === 'advanced' && !strpos(strtolower($specialty), 'advanced')) continue;
            if ($filter === 'top' && $rating < 4.5) continue;
        }
        
        $coach_data[] = [
            'id' => $coach->ID,
            'name' => $coach->display_name,
            'tier' => $tier,
            'specialty' => $specialty,
            'rating' => $rating,
            'total_athletes' => $total_athletes,
            'benefits' => [
                'Personalized training tips',
                'Monthly progress reviews',
                'Access to exclusive content'
            ]
        ];
    }
    
    wp_send_json_success(['coaches' => $coach_data]);
}

function intersoccer_handle_select_coach_partner() {
    check_ajax_referer('intersoccer_dashboard_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not logged in']);
    }
    
    $user_id = get_current_user_id();
    $coach_id = intval($_POST['coach_id']);
    
    // Validate coach exists
    $coach = get_user_by('ID', $coach_id);
    if (!$coach || !in_array('coach', $coach->roles)) {
        wp_send_json_error(['message' => 'Invalid coach selection']);
    }
    
    // Check cooldown period
    $cooldown_end = get_user_meta($user_id, 'intersoccer_partnership_cooldown_end', true);
    if ($cooldown_end && strtotime($cooldown_end) > time()) {
        wp_send_json_error(['message' => 'Coach change is in cooldown period']);
    }
    
    // Set partnership
    update_user_meta($user_id, 'intersoccer_partnership_coach_id', $coach_id);
    update_user_meta($user_id, 'intersoccer_partnership_start', current_time('mysql'));
    update_user_meta($user_id, 'intersoccer_partnership_orders', 0);
    
    // Set 30-day cooldown for next change
    $cooldown_date = date('Y-m-d H:i:s', strtotime('+30 days'));
    update_user_meta($user_id, 'intersoccer_partnership_cooldown_end', $cooldown_date);
    
    wp_send_json_success([
        'message' => sprintf('Successfully connected with coach %s!', $coach->display_name)
    ]);
}

function intersoccer_handle_gift_credits() {
    check_ajax_referer('intersoccer_dashboard_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not logged in']);
    }
    
    $user_id = get_current_user_id();
    $gift_amount = floatval($_POST['gift_amount']);
    $recipient_email = sanitize_email($_POST['recipient_email']);
    
    // Validate inputs
    if ($gift_amount < 50 || $gift_amount > 500) {
        wp_send_json_error(['message' => 'Invalid gift amount']);
    }
    
    if (!is_email($recipient_email)) {
        wp_send_json_error(['message' => 'Invalid email address']);
    }
    
    $current_credits = get_user_meta($user_id, 'intersoccer_customer_credits', true) ?: 0;
    if ($current_credits < $gift_amount) {
        wp_send_json_error(['message' => 'Insufficient credits']);
    }
    
    // Process gift
    $new_credits = $current_credits - $gift_amount + 20; // 20 CHF back for gifting
    update_user_meta($user_id, 'intersoccer_customer_credits', $new_credits);
    
    // Send gift notification email
    $user = wp_get_current_user();
    $subject = sprintf('You received %s CHF credit gift from %s', $gift_amount, $user->display_name);
    $message = sprintf(
        'Hi there!\n\n%s has gifted you %s CHF in InterSoccer credits!\n\nUse this link to claim: %s\n\nBest regards,\nInterSoccer Team',
        $user->display_name,
        $gift_amount,
        home_url('/claim-gift/?token=' . wp_generate_password(32, false))
    );
    
    wp_mail($recipient_email, $subject, $message);
    
    wp_send_json_success([
        'message' => 'Gift sent successfully! You earned 20 CHF back as a thank you.',
        'new_credits' => $new_credits
    ]);
}
?>