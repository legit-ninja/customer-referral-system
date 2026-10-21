<?php
/**
 * Recipient email locale helper tests.
 */

use PHPUnit\Framework\TestCase;

class RecipientEmailLocaleTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();
        require_once __DIR__ . '/bootstrap.php';
        global $mock_apply_filters, $mock_user_locale, $mock_active_locale;
        $mock_apply_filters = [];
        unset($mock_user_locale, $mock_active_locale);
    }

    protected function tearDown(): void {
        global $mock_apply_filters, $mock_user_locale, $mock_active_locale;
        $mock_apply_filters = [];
        unset($mock_user_locale, $mock_active_locale);
        parent::tearDown();
    }

    public function test_user_locale_prefers_wpml_user_language() {
        global $mock_apply_filters;
        $mock_apply_filters['wpml_user_language'] = static function () {
            return 'fr';
        };
        $mock_apply_filters['wpml_locale'] = static function ($locale, $lang) {
            return $lang === 'fr' ? 'fr_FR' : $locale;
        };
        $this->assertSame('fr_FR', intersoccer_crs_get_user_locale_for_email(42));
    }

    public function test_user_locale_falls_back_to_get_user_locale() {
        global $mock_user_locale;
        $mock_user_locale = 'de_DE';
        $this->assertSame('de_DE', intersoccer_crs_get_user_locale_for_email(7));
    }

    public function test_mail_for_user_switches_locale_during_callback() {
        global $mock_user_locale, $mock_active_locale;
        $mock_user_locale = 'fr_FR';
        $seen = null;
        intersoccer_crs_mail_for_user(9, function () use (&$seen) {
            global $mock_active_locale;
            $seen = $mock_active_locale ?? null;
            return true;
        });
        $this->assertSame('fr_FR', $seen);
    }
}
