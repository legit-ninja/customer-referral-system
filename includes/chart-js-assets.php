<?php
/**
 * Self-hosted Chart.js for referral admin screens.
 *
 * @package InterSoccer_Referral
 */

defined('ABSPATH') || exit;

if (!function_exists('intersoccer_crs_plugin_main_file')) {
    /**
     * Main plugin bootstrap file path.
     */
    function intersoccer_crs_plugin_main_file(): string {
        return dirname(__DIR__) . '/customer-referral-system.php';
    }
}

if (!function_exists('intersoccer_crs_chart_js_asset_handle')) {
    function intersoccer_crs_chart_js_asset_handle(): string {
        return 'intersoccer-crs-chart-js';
    }
}

if (!function_exists('intersoccer_crs_referral_admin_page_slugs')) {
    /**
     * Admin page slugs registered by this plugin (add_menu_page / add_submenu_page).
     *
     * @return string[]
     */
    function intersoccer_crs_referral_admin_page_slugs(): array {
        return [
            'intersoccer-referrals',
            'intersoccer-coaches',
            'intersoccer-coach-referrals',
            'intersoccer-customer-referrals',
            'intersoccer-financial-report',
            'intersoccer-customer-points',
            'intersoccer-coach-event-assignments',
            'intersoccer-email-templates',
            'intersoccer-settings',
            'intersoccer-tools',
            'intersoccer-coach-dashboard',
            'intersoccer-coach-profile',
            'intersoccer-coach-resources',
            'intersoccer-audit',
        ];
    }
}

if (!function_exists('intersoccer_crs_is_referral_admin_screen')) {
    /**
     * Whether the current admin_enqueue_scripts hook is a CRS admin page.
     */
    function intersoccer_crs_is_referral_admin_screen(string $hook): bool {
        foreach (intersoccer_crs_referral_admin_page_slugs() as $slug) {
            if (str_ends_with($hook, $slug)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('intersoccer_crs_register_chart_js_vendor_asset')) {
    function intersoccer_crs_register_chart_js_vendor_asset(): void {
        $handle = intersoccer_crs_chart_js_asset_handle();
        if (wp_script_is($handle, 'registered')) {
            return;
        }

        wp_register_script(
            $handle,
            plugins_url('js/vendor/chart.js/chart.min.js', intersoccer_crs_plugin_main_file()),
            [],
            '3.9.1',
            true
        );
    }
}

if (!function_exists('intersoccer_crs_enqueue_chart_js_vendor_asset')) {
    function intersoccer_crs_enqueue_chart_js_vendor_asset(): void {
        intersoccer_crs_register_chart_js_vendor_asset();
        wp_enqueue_script(intersoccer_crs_chart_js_asset_handle());
    }
}

add_action('init', 'intersoccer_crs_register_chart_js_vendor_asset', 5);
