<?php
/**
 * Admin English locale helper tests.
 */

use PHPUnit\Framework\TestCase;

class AdminEnglishLocaleTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        require_once __DIR__ . '/bootstrap.php';
        global $mock_is_admin, $mock_wp_doing_ajax, $mock_locale_switched, $mock_http_referer, $mock_apply_filters;
        $mock_is_admin = false;
        $mock_wp_doing_ajax = false;
        $mock_locale_switched = false;
        $mock_http_referer = '';
        $mock_apply_filters = [];
    }

    protected function tearDown(): void {
        global $mock_is_admin, $mock_wp_doing_ajax, $mock_locale_switched, $mock_http_referer, $mock_apply_filters;
        $mock_is_admin = false;
        $mock_wp_doing_ajax = false;
        $mock_locale_switched = false;
        $mock_http_referer = '';
        $mock_apply_filters = [];
        parent::tearDown();
    }

    public function test_admin_screen_forces_english_gettext() {
        global $mock_is_admin;
        $mock_is_admin = true;
        $this->assertTrue(intersoccer_crs_is_admin_english_context());
        $this->assertSame(
            'Customer Points',
            intersoccer_crs_filter_gettext('[t]Customer Points', 'Customer Points', 'intersoccer-referral')
        );
    }

    public function test_frontend_ajax_keeps_translations() {
        global $mock_is_admin, $mock_wp_doing_ajax, $mock_http_referer;
        $mock_is_admin = true;
        $mock_wp_doing_ajax = true;
        $mock_http_referer = 'https://example.test/fr/product/camp/';
        $this->assertFalse(intersoccer_crs_is_admin_english_context());
        $this->assertSame(
            '[t]Customer Points',
            intersoccer_crs_filter_gettext('[t]Customer Points', 'Customer Points', 'intersoccer-referral')
        );
    }

    public function test_non_admin_rest_keeps_translations() {
        $this->assertFalse(intersoccer_crs_is_admin_english_context());
        $this->assertSame(
            '[t]Customer Points',
            intersoccer_crs_filter_gettext('[t]Customer Points', 'Customer Points', 'intersoccer-referral')
        );
    }

    public function test_switched_locale_keeps_translations_in_admin() {
        global $mock_is_admin, $mock_locale_switched;
        $mock_is_admin = true;
        $mock_locale_switched = true;
        $this->assertFalse(intersoccer_crs_is_admin_english_context());
        $this->assertSame(
            '[t]Customer Points',
            intersoccer_crs_filter_gettext('[t]Customer Points', 'Customer Points', 'intersoccer-referral')
        );
    }

    public function test_filter_override_disables_admin_english() {
        global $mock_is_admin, $mock_apply_filters;
        $mock_is_admin = true;
        $mock_apply_filters['intersoccer_crs_admin_force_english'] = static function () {
            return false;
        };
        $this->assertFalse(intersoccer_crs_is_admin_english_context());
    }

    public function test_other_domains_untouched() {
        global $mock_is_admin;
        $mock_is_admin = true;
        $this->assertSame('[t]Other', intersoccer_crs_filter_gettext('[t]Other', 'Other', 'woocommerce'));
    }
}
