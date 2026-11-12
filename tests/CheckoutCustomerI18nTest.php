<?php
/**
 * Checkout customer-facing strings must exist in FR/DE catalogs.
 */

use PHPUnit\Framework\TestCase;

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return esc_html(__($text, $domain));
    }
}

if (!function_exists('esc_attr__')) {
    function esc_attr__($text, $domain = 'default') {
        global $mock_esc_attr_i18n_calls;
        if (!is_array($mock_esc_attr_i18n_calls)) {
            $mock_esc_attr_i18n_calls = [];
        }
        $mock_esc_attr_i18n_calls[] = [$text, $domain];
        return esc_attr(__($text, $domain));
    }
}

class CheckoutCustomerI18nTest extends TestCase {
    /**
     * Msgids required for checkout referral UX (follow-up 6).
     *
     * @return string[]
     */
    public static function checkout_customer_msgids(): array {
        return [
            'Enter referral code',
            'Please enter a referral code.',
            'Please enter a referral code',
            'Applying...',
            'Applied',
            'Apply Code',
            'Error applying referral code',
            'Discount:',
            'points discount',
            'Change Code',
            'Clearing...',
            'Must be logged in to apply referral code',
            'Error clearing referral code.',
            'Network error. Please try again.',
            'Please log in before applying a referral code.',
            'Unable to access your checkout session. Please refresh and try again.',
            'A different referral code is already applied to this order.',
            'Another referral code is already applied (%s). Please remove it before using a coach code.',
            'You cannot use your own referral code.',
            'Invalid referral code.',
            'Referral code saved! Coach %s will still receive credit on this order. First-time discount already used.',
            'Referral code applied! You will receive a discount. Your friend %s will earn points on this order.',
            'Referral code saved! %s will still earn points on this order. First-time discount already used.',
            'Must be logged in.',
            'Session not available. Please refresh the page.',
            'Referral code removed.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parse_po_translations(string $path): array {
        $this->assertFileExists($path);
        $raw = file_get_contents($path);
        $entries = preg_split('/\n\n+/', $raw);
        $map = [];

        foreach ($entries as $entry) {
            if (!preg_match('/^msgid\s/m', $entry)) {
                continue;
            }
            if (preg_match('/^msgid\s+""\s*$/m', $entry) && str_starts_with(trim($entry), 'msgid ""')) {
                continue;
            }

            $msgid = '';
            if (preg_match('/^msgid\s+"(.*)"\s*$/m', $entry, $m)) {
                $msgid = stripcslashes($m[1]);
            } elseif (preg_match('/^msgid\s+""\s*\n((?:".*"\s*\n?)+)/m', $entry, $m)) {
                $msgid = $this->join_po_quoted_lines($m[1]);
            }
            if ($msgid === '') {
                continue;
            }

            $msgstr = '';
            if (preg_match('/^msgstr\s+"(.*)"\s*$/m', $entry, $m)) {
                $msgstr = stripcslashes($m[1]);
            } elseif (preg_match('/^msgstr\s+""\s*\n((?:".*"\s*\n?)+)/m', $entry, $m)) {
                $msgstr = $this->join_po_quoted_lines($m[1]);
            }

            $map[$msgid] = $msgstr;
        }

        return $map;
    }

    private function join_po_quoted_lines(string $block): string {
        $out = '';
        if (preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/', $block, $matches)) {
            foreach ($matches[1] as $part) {
                $out .= stripcslashes($part);
            }
        }
        return $out;
    }

    /**
     * @dataProvider locale_po_mo_provider
     */
    public function test_no_duplicate_msgids_in_po(string $locale): void {
        $path = dirname(__DIR__) . '/languages/intersoccer-referral-' . $locale . '.po';
        $raw = (string) file_get_contents($path);
        $msgids = [];
        if (preg_match_all('/^msgid\s+"(.*)"\s*$/m', $raw, $matches)) {
            foreach ($matches[1] as $msgid) {
                $msgid = stripcslashes($msgid);
                if ($msgid === '') {
                    continue;
                }
                $msgids[] = $msgid;
            }
        }

        $counts = array_count_values($msgids);
        $dupes = array_keys(array_filter($counts, static function ($count) {
            return $count > 1;
        }));

        $this->assertSame([], $dupes, 'Duplicate msgids in ' . $locale . ' .po: ' . implode(', ', $dupes));
    }

    /**
     * @dataProvider locale_po_mo_provider
     */
    public function test_checkout_customer_strings_present_in_po(string $locale): void {
        $base = dirname(__DIR__) . '/languages/intersoccer-referral-' . $locale . '.po';
        $map = $this->parse_po_translations($base);

        foreach (self::checkout_customer_msgids() as $msgid) {
            $this->assertArrayHasKey($msgid, $map, "Missing msgid in $locale .po: $msgid");
            $this->assertNotSame('', trim($map[$msgid]), "Empty msgstr in $locale .po: $msgid");
        }
    }

    /**
     * @dataProvider locale_po_mo_provider
     */
    public function test_checkout_customer_mo_file_exists_and_valid_magic(string $locale): void {
        $mo = dirname(__DIR__) . '/languages/intersoccer-referral-' . $locale . '.mo';
        $this->assertFileExists($mo);
        $header = file_get_contents($mo, false, null, 0, 4);
        $this->assertSame("\xde\x12\x04\x95", $header, 'Invalid GNU MO magic for ' . $locale);
        $this->assertGreaterThan(100, filesize($mo), 'MO file too small for ' . $locale);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function locale_po_mo_provider(): array {
        return [
            'fr_FR' => ['fr_FR'],
            'de_DE' => ['de_DE'],
        ];
    }

    public function test_checkout_referral_field_placeholder_uses_intersoccer_referral_domain(): void {
        require_once dirname(__DIR__) . '/includes/class-admin-dashboard.php';

        global $mock_esc_attr_i18n_calls, $mock_current_user_id;
        $mock_esc_attr_i18n_calls = [];
        $mock_current_user_id = 501;

        $reflection = new ReflectionClass(InterSoccer_Referral_Admin_Dashboard::class);
        $dashboard = $reflection->newInstanceWithoutConstructor();

        ob_start();
        $dashboard->add_referral_code_field();
        $html = (string) ob_get_clean();

        $expected_placeholder = esc_attr__('Enter referral code', 'intersoccer-referral');
        $this->assertStringContainsString(
            'placeholder="' . $expected_placeholder . '"',
            $html,
            'Checkout referral input should output a translated placeholder attribute'
        );
        $this->assertContains(
            ['Enter referral code', 'intersoccer-referral'],
            $mock_esc_attr_i18n_calls,
            'Placeholder should be wrapped with esc_attr__ and the intersoccer-referral text domain'
        );
    }

    public function test_checkout_localize_array_includes_change_code_flow_i18n_keys(): void {
        $plugin_file = dirname(__DIR__) . '/customer-referral-system.php';
        $source = (string) file_get_contents($plugin_file);

        $required_keys = [
            'change_code',
            'clearing',
            'error_clearing_referral_code',
            'network_error_please_try_again',
        ];

        foreach ($required_keys as $key) {
            $this->assertMatchesRegularExpression(
                "/['\"]{$key}['\"]\s*=>\s*__\(/",
                $source,
                "Missing checkout i18n localize entry for {$key}"
            );
        }

        $this->assertStringContainsString(
            "'change_code' => __('Change Code', 'intersoccer-referral')",
            $source
        );
        $this->assertStringContainsString(
            "'clearing' => __('Clearing...', 'intersoccer-referral')",
            $source
        );
        $this->assertStringContainsString(
            "'error_clearing_referral_code' => __('Error clearing referral code.', 'intersoccer-referral')",
            $source
        );
        $this->assertStringContainsString(
            "'network_error_please_try_again' => __('Network error. Please try again.', 'intersoccer-referral')",
            $source
        );
    }
}
