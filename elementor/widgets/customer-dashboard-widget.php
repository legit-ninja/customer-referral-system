<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class InterSoccer_Customer_Dashboard_Widget extends \Elementor\Widget_Base {

    public function get_name() {
        return 'intersoccer_customer_dashboard';
    }

    public function get_title() {
        return esc_html__( 'InterSoccer Customer Dashboard', 'intersoccer-referral' );
    }

    public function get_icon() {
        return 'eicon-dashboard';
    }

    public function get_categories() {
        return [ 'general' ];  // Add to a custom category if needed, e.g., 'intersoccer'
    }

    public function get_keywords() {
        return [ 'intersoccer', 'referral', 'dashboard', 'customer' ];
    }

    protected function register_controls() {
        // Content Section: Toggles for visibility
        $this->start_controls_section(
            'section_content',
            [
                'label' => esc_html__( 'Content Settings', 'intersoccer-referral' ),
            ]
        );

        $this->add_control(
            'show_stats',
            [
                'label' => esc_html__( 'Show Dashboard Stats', 'intersoccer-referral' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Show', 'intersoccer-referral' ),
                'label_off' => esc_html__( 'Hide', 'intersoccer-referral' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_partnership',
            [
                'label' => esc_html__( 'Show Coach Partnership Section', 'intersoccer-referral' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Show', 'intersoccer-referral' ),
                'label_off' => esc_html__( 'Hide', 'intersoccer-referral' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_badges',
            [
                'label' => esc_html__( 'Show Badges Section', 'intersoccer-referral' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Show', 'intersoccer-referral' ),
                'label_off' => esc_html__( 'Hide', 'intersoccer-referral' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_referral',
            [
                'label' => esc_html__( 'Show Referral Section', 'intersoccer-referral' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Show', 'intersoccer-referral' ),
                'label_off' => esc_html__( 'Hide', 'intersoccer-referral' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_progress',
            [
                'label' => esc_html__( 'Show Progress Section', 'intersoccer-referral' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Show', 'intersoccer-referral' ),
                'label_off' => esc_html__( 'Hide', 'intersoccer-referral' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_gift',
            [
                'label' => esc_html__( 'Show Gift Credits Section', 'intersoccer-referral' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Show', 'intersoccer-referral' ),
                'label_off' => esc_html__( 'Hide', 'intersoccer-referral' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->end_controls_section();

        // Custom Texts Section
        $this->start_controls_section(
            'section_texts',
            [
                'label' => esc_html__( 'Custom Texts', 'intersoccer-referral' ),
            ]
        );

        $this->add_control(
            'header_title',
            [
                'label' => esc_html__( 'Dashboard Header Title', 'intersoccer-referral' ),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => esc_html__( 'Your Referral Dashboard', 'intersoccer-referral' ),
                'placeholder' => esc_html__( 'Enter custom title', 'intersoccer-referral' ),
            ]
        );

        $this->add_control(
            'referral_description',
            [
                'label' => esc_html__( 'Referral Description', 'intersoccer-referral' ),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => esc_html__( 'Earn 500 points (50 CHF) for every friend who joins InterSoccer! Share your personalized link:', 'intersoccer-referral' ),
                'placeholder' => esc_html__( 'Enter custom referral text', 'intersoccer-referral' ),
            ]
        );

        // Add more text controls as needed for other sections

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'section_style',
            [
                'label' => esc_html__( 'Style', 'intersoccer-referral' ),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'primary_color',
            [
                'label' => esc_html__( 'Primary Color', 'intersoccer-referral' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .intersoccer-customer-dashboard' => '--primary-color: {{VALUE}};',
                ],
                'default' => '#1e3a8a',
            ]
        );

        $this->add_control(
            'button_color',
            [
                'label' => esc_html__( 'Button Color', 'intersoccer-referral' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .button, {{WRAPPER}} .confirm-btn' => 'background-color: {{VALUE}};',
                ],
                'default' => '#22c55e',
            ]
        );

        // Add more style controls (e.g., typography, spacing) as needed

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        if ( ! is_user_logged_in() ) {
            echo '<p>' . esc_html__( 'Please log in to view your referral dashboard.', 'intersoccer-referral' ) . '</p>';
            return;
        }

        // Enqueue assets (ensure they load in Elementor editor too)
        wp_enqueue_style( 'intersoccer-dashboard-css', INTERSOCCER_REFERRAL_URL . 'assets/css/dashboard.css', [], INTERSOCCER_REFERRAL_VERSION );
        wp_enqueue_script( 'intersoccer-dashboard-js', INTERSOCCER_REFERRAL_URL . 'assets/js/dashboard.js', [ 'jquery' ], INTERSOCCER_REFERRAL_VERSION, true );
        wp_localize_script( 'intersoccer-dashboard-js', 'intersoccer_dashboard', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'intersoccer_dashboard_nonce' ),
        ] );

        // To make sections toggleable and texts customizable, we'd ideally refactor render_customer_dashboard() to accept params.
        // For now, since it's monolithic, we'll call it and use JS/CSS to hide sections based on settings (or modify the output).
        // Future: Break render_customer_dashboard() into modular functions for better override.

        $dashboard = new InterSoccer_Referral_Dashboard();
        $output = $dashboard->render_customer_dashboard();

        // Apply basic customizations (e.g., replace title)
        $output = str_replace( '<h2>Your Referral Dashboard</h2>', '<h2>' . esc_html( $settings['header_title'] ) . '</h2>', $output );
        $output = str_replace( '<p class="referral-description"> <span class="highlight">Earn 500 points (50 CHF)</span> for every friend who joins InterSoccer!  Share your personalized link: </p>', '<p class="referral-description">' . wp_kses_post( $settings['referral_description'] ) . '</p>', $output );

        // Hide sections via inline style (crude but effective; better with modular render in future)
        if ( 'yes' !== $settings['show_stats'] ) {
            $output = preg_replace( '/<div class="dashboard-stats">.*?<\/div>/s', '', $output );
        }
        if ( 'yes' !== $settings['show_partnership'] ) {
            $output = preg_replace( '/<div class="coach-partnership-section">.*?<\/div>/s', '', $output );
        }
        if ( 'yes' !== $settings['show_badges'] ) {
            $output = preg_replace( '/<div class="badges-section">.*?<\/div>/s', '', $output );
        }
        if ( 'yes' !== $settings['show_referral'] ) {
            $output = preg_replace( '/<div class="referral-section">.*?<\/div>/s', '', $output );
        }
        if ( 'yes' !== $settings['show_progress'] ) {
            $output = preg_replace( '/<div class="progress-section">.*?<\/div>/s', '', $output );
        }
        if ( 'yes' !== $settings['show_gift'] ) {
            $output = preg_replace( '/<div class="gift-section">.*?<\/div>/s', '', $output );
        }

        echo $output;
    }

    protected function content_template() {
        // Live editor preview (simplified)
        ?>
        <div class="intersoccer-customer-dashboard">
            <h2>{{{ settings.header_title }}}</h2>
            <# if ( 'yes' === settings.show_stats ) { #>
                <div class="dashboard-stats">Stats Preview</div>
            <# } #>
            <!-- Add similar previews for other sections -->
        </div>
        <?php
    }
}