<?php
/**
 * Recent Activity uses a translatable "ago" wrapper around human_time_diff.
 */

use PHPUnit\Framework\TestCase;

class RecentActivityTimeAgoTest extends TestCase {
    public function test_wraps_human_time_diff_in_translatable_ago(): void {
        $from = time() - (14 * DAY_IN_SECONDS) - 30;
        $this->assertSame('14 days ago', intersoccer_referral_time_ago($from));
    }

    public function test_empty_datetime_returns_empty_string(): void {
        $this->assertSame('', intersoccer_referral_time_ago(''));
    }

    public function test_admin_english_context_uses_en_us_for_the_core_phrase(): void {
        global $mock_is_admin, $mock_active_locale;
        $mock_is_admin = true;
        unset($mock_active_locale);

        $this->assertSame('2 days ago', intersoccer_referral_time_ago(time() - (2 * DAY_IN_SECONDS) - 30));
        $this->assertFalse(isset($mock_active_locale));

        $mock_is_admin = false;
    }

    public function test_recent_activity_templates_use_the_helper(): void {
        $dashboard = (string) file_get_contents(dirname(__DIR__) . '/includes/class-admin-dashboard-main.php');
        $coaches = (string) file_get_contents(dirname(__DIR__) . '/includes/class-admin-coaches.php');

        $this->assertStringContainsString('intersoccer_referral_time_ago', $dashboard);
        $this->assertStringContainsString('intersoccer_referral_time_ago', $coaches);
        $this->assertStringNotContainsString('?> ago', $dashboard);
        $this->assertStringNotContainsString('?> ago', $coaches);
    }

    /**
     * @dataProvider locale_catalogs
     */
    public function test_ago_catalog_translation(string $file, string $expected): void {
        $path = dirname(__DIR__) . '/languages/' . $file;
        $raw = (string) file_get_contents($path);
        $this->assertSame(1, preg_match_all('/^msgid "%s ago"$/m', $raw));
        $this->assertStringContainsString('msgstr "' . $expected . '"', $raw);
    }

    public static function locale_catalogs(): array {
        return [
            'fr' => ['intersoccer-referral-fr.po', 'il y a %s'],
            'fr_CH' => ['intersoccer-referral-fr_CH.po', 'il y a %s'],
            'fr_FR' => ['intersoccer-referral-fr_FR.po', 'il y a %s'],
            'de' => ['intersoccer-referral-de.po', 'vor %s'],
            'de_CH' => ['intersoccer-referral-de_CH.po', 'vor %s'],
            'de_DE' => ['intersoccer-referral-de_DE.po', 'vor %s'],
        ];
    }
}
