<?php

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for coach referral codes: CSV import and explicit generation only.
 */
class CoachReferralCodeImportTest extends TestCase {

    /** @var InterSoccer_Admin_Settings */
    private $admin_settings;

    protected function setUp(): void {
        global $mock_users, $mock_user_meta;

        $mock_users = [];
        $mock_user_meta = [];

        if (!defined('INTERSOCCER_REFERRAL_PATH')) {
            define('INTERSOCCER_REFERRAL_PATH', dirname(__DIR__) . '/');
        }
        if (!defined('INTERSOCCER_REFERRAL_URL')) {
            define('INTERSOCCER_REFERRAL_URL', 'https://example.com/wp-content/plugins/customer-referral-system/');
        }

        require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-referral-handler.php';
        require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-simulator.php';
        require_once INTERSOCCER_REFERRAL_PATH . 'includes/class-admin-settings.php';

        if (!get_role('coach')) {
            add_role('coach', 'Coach', ['read' => true]);
        }

        $this->admin_settings = InterSoccer_Admin_Settings::get_instance();
    }

    protected function tearDown(): void {
        global $mock_users, $mock_user_meta;

        $mock_users = [];
        $mock_user_meta = [];
    }

    public function testImportStoresCustomReferralCodeFromCsv(): void {
        $csv = "First Name,Last Name,Email,referral_code\n";
        $csv .= "Anna,Example,anna.example@test.ch,COACH000042\n";

        $results = $this->importCsv($csv);

        $this->assertCount(1, $results['created']);
        $this->assertSame('COACH000042', $results['created'][0]['referral_code']);

        $user = get_user_by('email', 'anna.example@test.ch');
        $this->assertNotNull($user);
        $this->assertSame('COACH000042', InterSoccer_Referral_Handler::get_coach_referral_code($user->ID));
    }

    public function testImportWithoutCodeDoesNotGenerateByDefault(): void {
        $csv = "First Name,Last Name,Email\n";
        $csv .= "Bob,Example,bob.example@test.ch\n";

        $results = $this->importCsv($csv);

        $this->assertCount(1, $results['created']);
        $this->assertSame('', $results['created'][0]['referral_code']);

        $user = get_user_by('email', 'bob.example@test.ch');
        $this->assertSame('', InterSoccer_Referral_Handler::get_coach_referral_code($user->ID));
        $this->assertSame('', InterSoccer_Referral_Handler::generate_coach_referral_link($user->ID));
    }

    public function testImportWithGenerateFlagCreatesReferralCode(): void {
        $csv = "First Name,Last Name,Email\n";
        $csv .= "Cara,Example,cara.example@test.ch\n";

        $results = $this->importCsv($csv, false, true);

        $this->assertCount(1, $results['created']);
        $this->assertNotEmpty($results['created'][0]['referral_code']);

        $user = get_user_by('email', 'cara.example@test.ch');
        $stored = InterSoccer_Referral_Handler::get_coach_referral_code($user->ID);
        $this->assertNotEmpty($stored);
        $this->assertStringStartsWith('COACH' . $user->ID, $stored);
    }

    public function testImportPreservesExistingCodeWhenCsvOmitsCode(): void {
        $existing = new WP_User(501);
        $existing->user_email = 'dana.example@test.ch';
        $existing->set_role('coach');

        global $mock_users;
        $mock_users[501] = $existing;
        update_user_meta(501, InterSoccer_Referral_Handler::COACH_REFERRAL_CODE_META, 'HR-ASSIGNED-501');

        $csv = "First Name,Last Name,Email\n";
        $csv .= "Dana,Example,dana.example@test.ch\n";

        $results = $this->importCsv($csv, true, false);

        $this->assertCount(1, $results['updated']);
        $this->assertSame('HR-ASSIGNED-501', $results['updated'][0]['referral_code']);
        $this->assertSame('HR-ASSIGNED-501', InterSoccer_Referral_Handler::get_coach_referral_code(501));
    }

    public function testImportRejectsDuplicateReferralCode(): void {
        $first = "First Name,Last Name,Email,referral_code\n";
        $first .= "Eve,One,eve.one@test.ch,DUPCODE99\n";
        $this->importCsv($first);

        $second = "First Name,Last Name,Email,referral_code\n";
        $second .= "Eve,Two,eve.two@test.ch,DUPCODE99\n";
        $results = $this->importCsv($second);

        $this->assertCount(0, $results['created']);
        $this->assertNotEmpty($results['errors']);
        $this->assertStringContainsString('DUPCODE99', $results['errors'][0]);

        $this->assertNull(get_user_by('email', 'eve.two@test.ch'));
    }

    public function testGetCoachReferralCodeDoesNotLazyGenerate(): void {
        $coach_id = 777;
        update_user_meta($coach_id, InterSoccer_Referral_Handler::COACH_REFERRAL_CODE_META, '');

        $this->assertSame('', InterSoccer_Referral_Handler::get_coach_referral_code($coach_id));
        $this->assertSame('', get_user_meta($coach_id, InterSoccer_Referral_Handler::COACH_REFERRAL_CODE_META, true));
    }

    public function testEnsureCoachReferralCodeGeneratesOnRequest(): void {
        $coach_id = 888;

        $code = InterSoccer_Referral_Handler::ensure_coach_referral_code($coach_id);

        $this->assertNotEmpty($code);
        $this->assertSame($code, InterSoccer_Referral_Handler::get_coach_referral_code($coach_id));
        $this->assertStringStartsWith('COACH' . $coach_id, $code);
    }

    public function testGenerateCoachReferralLinkRequiresStoredCode(): void {
        $coach_id = 999;
        update_user_meta($coach_id, InterSoccer_Referral_Handler::COACH_REFERRAL_CODE_META, 'STORED999');

        $link = InterSoccer_Referral_Handler::generate_coach_referral_link($coach_id);

        $this->assertStringContainsString('ref=STORED999', $link);
    }

    public function testImportNormalizesReferralCodeColumn(): void {
        $csv = "First Name,Last Name,Email,Coach Referral Code\n";
        $csv .= "Finn,Example,finn.example@test.ch,coach-000055\n";

        $results = $this->importCsv($csv);

        $this->assertCount(1, $results['created']);
        $this->assertSame('COACH-000055', $results['created'][0]['referral_code']);
    }

    /**
     * @return array{created: array, updated: array, skipped: array, errors: array}
     */
    private function importCsv(string $csv_content, bool $update_existing = false, bool $generate_referral_codes = false): array {
        $temp_file = tempnam(sys_get_temp_dir(), 'coach_ref_import_');
        file_put_contents($temp_file, $csv_content);

        try {
            $reflection = new ReflectionClass($this->admin_settings);
            $method = $reflection->getMethod('process_coach_csv_import');
            $method->setAccessible(true);

            return $method->invoke($this->admin_settings, $temp_file, $update_existing, $generate_referral_codes);
        } finally {
            if (is_file($temp_file)) {
                unlink($temp_file);
            }
        }
    }
}
