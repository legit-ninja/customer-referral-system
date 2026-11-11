<?php

use PHPUnit\Framework\TestCase;

if (!defined('INTERSOCCER_REFERRAL_URL')) {
    define('INTERSOCCER_REFERRAL_URL', 'https://example.com/wp-content/plugins/customer-referral-system/');
}

if (!defined('INTERSOCCER_REFERRAL_VERSION')) {
    define('INTERSOCCER_REFERRAL_VERSION', '0.0.0-test');
}

if (!function_exists('add_submenu_page')) {
    function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null) {
        global $mock_add_submenu_page_calls;
        if (!is_array($mock_add_submenu_page_calls)) {
            $mock_add_submenu_page_calls = [];
        }
        $hook = 'referrals_page_' . $menu_slug;
        $mock_add_submenu_page_calls[] = [
            'parent_slug' => $parent_slug,
            'page_title' => $page_title,
            'menu_title' => $menu_title,
            'capability' => $capability,
            'menu_slug' => $menu_slug,
            'callback' => $callback,
            'hook' => $hook,
        ];
        return $hook;
    }
}

if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style($handle, $src = '', $deps = [], $ver = false, $media = 'all') {
        global $mock_enqueued_styles;
        if (!is_array($mock_enqueued_styles)) {
            $mock_enqueued_styles = [];
        }
        $mock_enqueued_styles[$handle] = [
            'src' => $src,
            'deps' => $deps,
            'ver' => $ver,
            'media' => $media,
        ];
    }
}

if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script($handle, $src = '', $deps = [], $ver = false, $in_footer = false) {
        global $mock_enqueued_scripts;
        if (!is_array($mock_enqueued_scripts)) {
            $mock_enqueued_scripts = [];
        }
        $mock_enqueued_scripts[$handle] = [
            'src' => $src,
            'deps' => $deps,
            'ver' => $ver,
            'in_footer' => $in_footer,
        ];
    }
}

if (!function_exists('wp_localize_script')) {
    function wp_localize_script($handle, $object_name, $l10n) {
        global $mock_localized_scripts;
        if (!is_array($mock_localized_scripts)) {
            $mock_localized_scripts = [];
        }
        $mock_localized_scripts[$handle] = [
            'object_name' => $object_name,
            'l10n' => $l10n,
        ];
        return true;
    }
}

/**
 * Audit logs admin page: count query and menu registration.
 *
 * The harness cannot render the full admin screen (no admin chrome, and the
 * page renderer needs logger row objects). These tests cover the fatal count
 * helper and the submenu hook the enqueue callback must match.
 */
class AdminAuditPageTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        require_once dirname(__DIR__) . '/includes/class-admin-audit.php';

        global $mock_add_submenu_page_calls, $mock_enqueued_styles, $mock_enqueued_scripts, $mock_localized_scripts, $mock_wpdb_get_var_results;
        $mock_add_submenu_page_calls = [];
        $mock_enqueued_styles = [];
        $mock_enqueued_scripts = [];
        $mock_localized_scripts = [];
        $mock_wpdb_get_var_results = [];
    }

    protected function tearDown(): void {
        global $mock_wpdb_get_var_results;
        $mock_wpdb_get_var_results = [];
        parent::tearDown();
    }

    /**
     * Regression: #141 — do not read the private audit logger table property.
     */
    public function testTotalLogCountQueriesAuditTableWithoutFatal() {
        global $wpdb, $mock_wpdb_get_var_results, $mock_audit_count_query;
        $mock_audit_count_query = '';
        $table = $wpdb->prefix . 'intersoccer_audit_log';
        $mock_wpdb_get_var_results = [
            'intersoccer_audit_log' => function ($query) {
                global $mock_audit_count_query;
                $mock_audit_count_query = $query;
                return '4';
            },
        ];

        $admin = new InterSoccer_Admin_Audit();
        $method = new ReflectionMethod(InterSoccer_Admin_Audit::class, 'get_total_log_count');
        $method->setAccessible(true);
        $count = $method->invoke($admin, [
            'event_type' => 'user_login',
            'category' => '',
            'user_id' => '',
            'ip_address' => '',
            'date_from' => '',
            'date_to' => '',
        ]);

        $this->assertSame('4', $count);
        $this->assertNotFalse(strpos($mock_audit_count_query, $table));
        $this->assertNotFalse(strpos($mock_audit_count_query, 'user_login'));
        $this->assertNotFalse(strpos($mock_audit_count_query, 'COUNT(*)'));
    }

    /**
     * Regression: #141 — register under the referrals menu and enqueue on that hook.
     */
    public function testAuditMenuRegistersUnderReferralsAndEnqueuesOnReturnedHook() {
        global $mock_add_submenu_page_calls, $mock_enqueued_styles, $mock_enqueued_scripts;

        $admin = new InterSoccer_Admin_Audit();
        $admin->add_audit_menu();

        $this->assertCount(1, $mock_add_submenu_page_calls);
        $registered = $mock_add_submenu_page_calls[0];
        $this->assertSame('intersoccer-referrals', $registered['parent_slug']);
        $this->assertSame('intersoccer-audit', $registered['menu_slug']);
        $this->assertSame('manage_options', $registered['capability']);
        $this->assertSame([$admin, 'audit_logs_page'], $registered['callback']);
        $this->assertSame('referrals_page_intersoccer-audit', $registered['hook']);

        $admin->enqueue_scripts('intersoccer_page_intersoccer-audit');
        $this->assertArrayNotHasKey('intersoccer-audit', $mock_enqueued_styles);
        $this->assertArrayNotHasKey('intersoccer-audit', $mock_enqueued_scripts);

        $admin->enqueue_scripts($registered['hook']);
        $this->assertArrayHasKey('intersoccer-audit', $mock_enqueued_styles);
        $this->assertArrayHasKey('intersoccer-audit', $mock_enqueued_scripts);
        $this->assertNotFalse(strpos($mock_enqueued_styles['intersoccer-audit']['src'], 'assets/css/admin-audit.css'));
        $this->assertNotFalse(strpos($mock_enqueued_scripts['intersoccer-audit']['src'], 'assets/js/admin-audit.js'));
    }
}
