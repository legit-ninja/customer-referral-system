<?php
/**
 * WPML language on customer admin-ajax URLs and AJAX bootstrap.
 */

use PHPUnit\Framework\TestCase;

class CrsWpmlAjaxTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        require_once __DIR__ . '/bootstrap.php';

        if (!function_exists('is_textdomain_loaded')) {
            function is_textdomain_loaded($domain) {
                return !empty($GLOBALS['intersoccer_crs_test_textdomain_loaded'][$domain]);
            }
        }
        if (!function_exists('unload_textdomain')) {
            function unload_textdomain($domain, $reloadable = false) {
                unset($GLOBALS['intersoccer_crs_test_textdomain_loaded'][$domain]);
            }
        }
        if (!function_exists('load_textdomain')) {
            function load_textdomain($domain, $mofile, $locale = null) {
                $GLOBALS['intersoccer_crs_test_textdomain_loaded'][$domain] = true;
                $GLOBALS['intersoccer_crs_textdomain_locale'] = $locale ?? '';
                return true;
            }
        }

        global $mock_apply_filters, $mock_is_admin, $mock_wp_doing_ajax, $mock_http_referer;
        $mock_apply_filters = [];
        $mock_is_admin = false;
        $mock_wp_doing_ajax = false;
        $mock_http_referer = '';
        unset($GLOBALS['intersoccer_crs_wpml_switched'], $GLOBALS['intersoccer_crs_textdomain_locale']);
        unset($GLOBALS['intersoccer_crs_test_textdomain_loaded'], $_REQUEST['lang']);
        unset($GLOBALS['mock_do_action_callbacks'], $GLOBALS['mock_wp_action_callbacks']);
    }

    protected function tearDown(): void {
        global $mock_apply_filters, $mock_is_admin, $mock_wp_doing_ajax, $mock_http_referer;
        $mock_apply_filters = [];
        $mock_is_admin = false;
        $mock_wp_doing_ajax = false;
        $mock_http_referer = '';
        unset(
            $GLOBALS['intersoccer_crs_wpml_switched'],
            $GLOBALS['intersoccer_crs_textdomain_locale'],
            $GLOBALS['intersoccer_crs_test_textdomain_loaded'],
            $GLOBALS['mock_do_action_callbacks'],
            $GLOBALS['mock_wp_action_callbacks']
        );
        unset($_REQUEST['lang'], $_SERVER['HTTP_REFERER']);
        parent::tearDown();
    }

    public function test_frontend_admin_ajax_url_includes_wpml_lang(): void {
        global $mock_apply_filters;
        $mock_apply_filters['wpml_current_language'] = static function ($value) {
            return 'fr';
        };

        $url = intersoccer_crs_frontend_admin_ajax_url();
        $this->assertStringContainsString('lang=fr', $url);
    }

    public function test_frontend_admin_ajax_url_omits_lang_when_wpml_empty(): void {
        $url = intersoccer_crs_frontend_admin_ajax_url();
        $this->assertStringNotContainsString('lang=', $url);
    }

    public function test_bootstrap_switches_wpml_on_customer_ajax(): void {
        $_REQUEST['lang'] = 'fr';
        global $mock_is_admin, $mock_wp_doing_ajax, $mock_http_referer;
        $mock_is_admin = true;
        $mock_wp_doing_ajax = true;
        $mock_http_referer = 'https://example.test/fr/checkout/';

        $GLOBALS['mock_wp_action_callbacks']['wpml_switch_language'] = [
            [
                'callback' => static function ($lang) {
                    $GLOBALS['intersoccer_crs_wpml_switched'] = $lang;
                },
                'priority' => 10,
                'accepted_args' => 1,
            ],
        ];
        $GLOBALS['mock_do_action_callbacks']['wpml_switch_language'] = [
            static function ($lang) {
                $GLOBALS['intersoccer_crs_wpml_switched'] = $lang;
            },
        ];

        intersoccer_crs_bootstrap_customer_ajax_language();

        $this->assertSame('fr', $GLOBALS['intersoccer_crs_wpml_switched'] ?? null);
        $this->assertSame('fr_FR', $GLOBALS['intersoccer_crs_textdomain_locale'] ?? null);
    }

    public function test_bootstrap_skips_admin_english_ajax(): void {
        $_REQUEST['lang'] = 'fr';
        global $mock_is_admin, $mock_wp_doing_ajax, $mock_http_referer;
        $mock_is_admin = true;
        $mock_wp_doing_ajax = true;
        $mock_http_referer = 'https://example.test/wp-admin/users.php';

        $GLOBALS['mock_do_action_callbacks']['wpml_switch_language'] = [
            static function ($lang) {
                $GLOBALS['intersoccer_crs_wpml_switched'] = $lang;
            },
        ];

        intersoccer_crs_bootstrap_customer_ajax_language();

        $this->assertArrayNotHasKey('intersoccer_crs_wpml_switched', $GLOBALS);
    }

    public function test_bootstrap_ignores_non_language_lang_value(): void {
        $_REQUEST['lang'] = '../../evil';
        global $mock_is_admin, $mock_wp_doing_ajax, $mock_http_referer;
        $mock_is_admin = true;
        $mock_wp_doing_ajax = true;
        $mock_http_referer = 'https://example.test/fr/checkout/';
        $GLOBALS['mock_do_action_callbacks']['wpml_switch_language'] = [
            static function ($lang) {
                $GLOBALS['intersoccer_crs_wpml_switched'] = $lang;
            },
        ];

        intersoccer_crs_bootstrap_customer_ajax_language();

        $this->assertArrayNotHasKey('intersoccer_crs_wpml_switched', $GLOBALS);
    }
}
