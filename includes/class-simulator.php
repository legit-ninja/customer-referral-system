<?php
// includes/class-simulator.php

/**
 * InterSoccer Revenue Simulator
 * 
 * Handles all simulation, analysis, and recommendation functionality
 * for the Sales & Marketing Revenue Simulator tool.
 */
class InterSoccer_Simulator {

    private static $instance = null;
    private $admin_settings = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Register AJAX handlers
        add_action('wp_ajax_intersoccer_search_orders_for_simulator', [$this, 'ajax_search_orders_for_simulator']);
        add_action('wp_ajax_intersoccer_run_referral_simulation', [$this, 'ajax_run_referral_simulation']);
        add_action('wp_ajax_intersoccer_save_simulator_scenario', [$this, 'ajax_save_simulator_scenario']);
        add_action('wp_ajax_intersoccer_load_simulator_scenario', [$this, 'ajax_load_simulator_scenario']);
        add_action('wp_ajax_intersoccer_list_simulator_scenarios', [$this, 'ajax_list_simulator_scenarios']);
        add_action('wp_ajax_intersoccer_delete_simulator_scenario', [$this, 'ajax_delete_simulator_scenario']);
        add_action('wp_ajax_intersoccer_load_scenario_template', [$this, 'ajax_load_scenario_template']);
        add_action('wp_ajax_intersoccer_load_current_settings', [$this, 'ajax_load_current_settings']);
        add_action('wp_ajax_intersoccer_export_simulator_excel', [$this, 'ajax_export_simulator_excel']);
        add_action('wp_ajax_intersoccer_export_simulator_pdf', [$this, 'ajax_export_simulator_pdf']);
        add_action('wp_ajax_intersoccer_run_sensitivity_analysis', [$this, 'ajax_run_sensitivity_analysis']);
        add_action('wp_ajax_intersoccer_get_recommendations', [$this, 'ajax_get_recommendations']);
    }

    /**
     * Get admin settings instance (lazy load to avoid circular dependency)
     */
    private function get_admin_settings() {
        if (null === $this->admin_settings) {
            // Use singleton instance from InterSoccer_Admin_Settings
            $this->admin_settings = InterSoccer_Admin_Settings::get_instance();
        }
        return $this->admin_settings;
    }

    /**
     * Search orders for simulator via AJAX
     * Delegates to InterSoccer_Admin_Settings for now
     */
    public function ajax_search_orders_for_simulator() {
        return $this->get_admin_settings()->ajax_search_orders_for_simulator();
    }

    /**
     * Run referral simulation via AJAX
     * Delegates to InterSoccer_Admin_Settings for now (methods still there)
     * TODO: Move full implementation here
     */
    public function ajax_run_referral_simulation() {
        return $this->get_admin_settings()->ajax_run_referral_simulation();
    }

    /**
     * Save simulator scenario
     * Delegates to InterSoccer_Admin_Settings for now
     */
    public function ajax_save_simulator_scenario() {
        return $this->get_admin_settings()->ajax_save_simulator_scenario();
    }

    /**
     * Load simulator scenario
     * Delegates to InterSoccer_Admin_Settings for now
     */
    public function ajax_load_simulator_scenario() {
        return $this->get_admin_settings()->ajax_load_simulator_scenario();
    }

    /**
     * List simulator scenarios
     * Delegates to InterSoccer_Admin_Settings for now
     */
    public function ajax_list_simulator_scenarios() {
        return $this->get_admin_settings()->ajax_list_simulator_scenarios();
    }

    /**
     * Delete simulator scenario
     * Delegates to InterSoccer_Admin_Settings for now
     */
    public function ajax_delete_simulator_scenario() {
        return $this->get_admin_settings()->ajax_delete_simulator_scenario();
    }

    /**
     * Load scenario template
     * Delegates to InterSoccer_Admin_Settings for now
     */
    public function ajax_load_scenario_template() {
        return $this->get_admin_settings()->ajax_load_scenario_template();
    }

    /**
     * Load current settings
     * Delegates to InterSoccer_Admin_Settings for now
     */
    public function ajax_load_current_settings() {
        return $this->get_admin_settings()->ajax_load_current_settings();
    }

    /**
     * Export simulator to Excel
     * Delegates to InterSoccer_Admin_Settings for now
     */
    public function ajax_export_simulator_excel() {
        return $this->get_admin_settings()->ajax_export_simulator_excel();
    }

    /**
     * Export simulator to PDF
     * Delegates to InterSoccer_Admin_Settings for now
     */
    public function ajax_export_simulator_pdf() {
        return $this->get_admin_settings()->ajax_export_simulator_pdf();
    }

    /**
     * Run sensitivity analysis
     * Delegates to InterSoccer_Admin_Settings for now
     */
    public function ajax_run_sensitivity_analysis() {
        return $this->get_admin_settings()->ajax_run_sensitivity_analysis();
    }

    /**
     * Get recommendations
     * Delegates to InterSoccer_Admin_Settings for now
     */
    public function ajax_get_recommendations() {
        return $this->get_admin_settings()->ajax_get_recommendations();
    }

}
