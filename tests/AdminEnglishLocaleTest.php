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
        unset($GLOBALS['wp_locale_switcher']);
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
        $GLOBALS['wp_locale_switcher'] = new class() {
            public function is_switched() {
                return true;
            }
        };
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

    public function test_gettext_filters_safe_without_wp_locale_switcher() {
        unset($GLOBALS['wp_locale_switcher']);
        global $mock_is_admin;
        $mock_is_admin = true;

        $this->assertFalse(intersoccer_crs_is_locale_switched());
        $this->assertTrue(intersoccer_crs_is_admin_english_context());
        $this->assertSame(
            'Coach',
            intersoccer_crs_filter_gettext('Entraîneur', 'Coach', 'intersoccer-referral')
        );
        $this->assertSame(
            'Coach',
            intersoccer_crs_filter_ngettext('Entraîneurs', 'Coach', 'Coaches', 1, 'intersoccer-referral')
        );
        $this->assertSame(
            'Coach',
            intersoccer_crs_filter_gettext_with_context('Entraîneur', 'Coach', 'role', 'intersoccer-referral')
        );
    }

    public function test_role_label_gettext_during_plugins_loaded_admin_is_english() {
        unset($GLOBALS['wp_locale_switcher']);
        global $mock_is_admin;
        $mock_is_admin = true;

        $label = intersoccer_crs_filter_gettext('Entraîneur', 'Coach', 'intersoccer-referral');
        $this->assertSame('Coach', $label);
    }
}
