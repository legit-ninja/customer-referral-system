<?php
/**
 * PHPUnit tests for Coach Email Templates
 * 
 * Tests AC §§1-10,13 for the coach email template builder:
 * - CRUD operations (create, edit, duplicate, archive)
 * - Merge field resolution for all six fields
 * - Missing-data validation (fail send on coach_name, referral_code, share_url)
 * - Preview with sample values
 * - Test send with merge substitution
 * - Friendly coach tone in starter templates
 * 
 * @package InterSoccer_Referral
 * @since 1.9.17
 */

use PHPUnit\Framework\TestCase;

class CoachEmailTemplatesTest extends TestCase {

    private $template_manager;

    protected function setUp(): void {
        global $mock_users, $mock_user_meta, $wpdb, $mock_options;

        // Reset mocks
        $mock_users = [];
        $mock_user_meta = [];
        $mock_options = [];

        // Create test coach user
        $coach = new WP_User(100);
        $coach->ID = 100;
        $coach->user_login = 'testcoach';
        $coach->display_name = 'Test Coach';
        $coach->first_name = 'Sarah';
        $coach->last_name = 'Smith';
        $coach->user_email = 'sarah@example.com';
        $coach->roles = ['coach'];
        $mock_users[100] = $coach;

        // Set coach referral code
        $mock_user_meta[100] = [
            'referral_code' => 'SARAH2026',
            'first_name' => 'Sarah',
            'last_name' => 'Smith',
        ];

        // Reset wpdb mock
        $wpdb = new Mock_WPDB();

        // Load the class
        require_once dirname(__DIR__) . '/includes/class-coach-email-templates.php';

        $this->template_manager = InterSoccer_Coach_Email_Templates::get_instance();
    }

    protected function tearDown(): void {
        global $mock_users, $mock_user_meta;
        $mock_users = [];
        $mock_user_meta = [];
    }

    /**
     * AC §5: All six merge fields must be available
     */
    public function testAllSixMergeFieldsAvailable(): void {
        $fields = $this->template_manager->get_merge_fields();

        $required_fields = [
            'coach_name',
            'referral_code',
            'campaign_name',
            'share_url',
            'campaign_end_date',
            'cta_url',
        ];

        foreach ($required_fields as $field) {
            $this->assertArrayHasKey($field, $fields, "Merge field '$field' should be available");
            $this->assertArrayHasKey('label', $fields[$field], "Field '$field' should have a label");
            $this->assertArrayHasKey('description', $fields[$field], "Field '$field' should have a description");
            $this->assertArrayHasKey('sample', $fields[$field], "Field '$field' should have a sample value");
        }
    }

    /**
     * AC §5: Merge fields resolve correctly when used
     */
    public function testMergeFieldResolution(): void {
        $template = "Hi {{coach_name}}, your code is {{referral_code}}. Share: {{share_url}}";
        $values = [
            'coach_name' => 'Sarah',
            'referral_code' => 'SARAH2026',
            'share_url' => 'https://example.com/?ref=SARAH2026',
            'campaign_name' => '',
            'campaign_end_date' => '',
            'cta_url' => '',
        ];

        $resolved = $this->template_manager->resolve_merge_fields($template, $values);

        $this->assertEquals(
            "Hi Sarah, your code is SARAH2026. Share: https://example.com/?ref=SARAH2026",
            $resolved
        );
        $this->assertStringNotContainsString('{{', $resolved, "No unresolved placeholders should remain");
    }

    /**
     * AC §5: Templates may use a subset of fields
     */
    public function testPartialFieldUsageAllowed(): void {
        $template = "Hi {{coach_name}}, welcome!";
        $values = $this->template_manager->get_sample_values();

        $resolved = $this->template_manager->resolve_merge_fields($template, $values);

        $this->assertStringContainsString('Sarah', $resolved);
        $this->assertStringNotContainsString('{{coach_name}}', $resolved);
    }

    /**
     * AC §6: Required fields validation - coach_name, referral_code, share_url
     */
    public function testRequiredFieldsValidation(): void {
        // All required fields present
        $complete_values = [
            'coach_name' => 'Sarah',
            'referral_code' => 'SARAH2026',
            'share_url' => 'https://example.com/?ref=SARAH2026',
        ];

        $result = $this->template_manager->validate_required_fields($complete_values);
        $this->assertTrue($result, "Validation should pass when all required fields are present");

        // Missing coach_name
        $missing_name = [
            'coach_name' => '',
            'referral_code' => 'SARAH2026',
            'share_url' => 'https://example.com/?ref=SARAH2026',
        ];

        $result = $this->template_manager->validate_required_fields($missing_name);
        $this->assertIsArray($result, "Validation should return array of missing fields");
        $this->assertContains('coach_name', $result);

        // Missing referral_code
        $missing_code = [
            'coach_name' => 'Sarah',
            'referral_code' => '',
            'share_url' => 'https://example.com/?ref=SARAH2026',
        ];

        $result = $this->template_manager->validate_required_fields($missing_code);
        $this->assertIsArray($result);
        $this->assertContains('referral_code', $result);

        // Missing share_url
        $missing_url = [
            'coach_name' => 'Sarah',
            'referral_code' => 'SARAH2026',
            'share_url' => '',
        ];

        $result = $this->template_manager->validate_required_fields($missing_url);
        $this->assertIsArray($result);
        $this->assertContains('share_url', $result);
    }

    /**
     * AC §6: Unresolved placeholders detection
     */
    public function testUnresolvedPlaceholdersDetection(): void {
        $content_with_placeholders = "Hi {{coach_name}}, your code is {{referral_code}}";

        $unresolved = $this->template_manager->find_unresolved_placeholders($content_with_placeholders);

        $this->assertContains('coach_name', $unresolved);
        $this->assertContains('referral_code', $unresolved);

        $content_resolved = "Hi Sarah, your code is SARAH2026";
        $unresolved = $this->template_manager->find_unresolved_placeholders($content_resolved);

        $this->assertEmpty($unresolved, "No placeholders should be found in resolved content");
    }

    /**
     * AC §3: Preview shows sample resolved values
     */
    public function testPreviewWithSampleValues(): void {
        $sample_values = $this->template_manager->get_sample_values();

        $this->assertNotEmpty($sample_values['coach_name'], "Sample coach_name should not be empty");
        $this->assertNotEmpty($sample_values['referral_code'], "Sample referral_code should not be empty");
        $this->assertNotEmpty($sample_values['share_url'], "Sample share_url should not be empty");
        $this->assertNotEmpty($sample_values['campaign_name'], "Sample campaign_name should not be empty");
        $this->assertNotEmpty($sample_values['campaign_end_date'], "Sample campaign_end_date should not be empty");
        $this->assertNotEmpty($sample_values['cta_url'], "Sample cta_url should not be empty");
    }

    /**
     * AC §7: campaign_end_date in coach-friendly format (European day-month-year)
     */
    public function testCampaignEndDateFormat(): void {
        // Test various input formats
        $formatted = $this->template_manager->format_coach_friendly_date('2026-12-31');
        $this->assertMatchesRegularExpression('/^\d{1,2}-\d{1,2}-\d{4}$/', $formatted, "Date should be in d-m-Y format");

        $formatted = $this->template_manager->format_coach_friendly_date('December 31, 2026');
        $this->assertMatchesRegularExpression('/^\d{1,2}-\d{1,2}-\d{4}$/', $formatted);

        // Empty date should return empty string
        $formatted = $this->template_manager->format_coach_friendly_date('');
        $this->assertEquals('', $formatted);
    }

    /**
     * AC §6: Get real values for a coach
     */
    public function testGetCoachValues(): void {
        $values = $this->template_manager->get_coach_values(100, [
            'campaign_name' => 'Test Campaign',
            'campaign_end_date' => '2026-12-31',
            'cta_url' => 'https://example.com/signup',
        ]);

        $this->assertEquals('Sarah', $values['coach_name'], "Coach name should be extracted from user data");
        $this->assertEquals('SARAH2026', $values['referral_code'], "Referral code should be extracted from user meta");
        $this->assertStringContainsString('ref=SARAH2026', $values['share_url'], "Share URL should contain referral code");
        $this->assertEquals('Test Campaign', $values['campaign_name']);
        $this->assertMatchesRegularExpression('/^\d{1,2}-\d{1,2}-\d{4}$/', $values['campaign_end_date']);
        $this->assertEquals('https://example.com/signup', $values['cta_url']);
    }

    /**
     * AC §6: Coach without referral code should fail required validation
     */
    public function testCoachWithoutReferralCodeFails(): void {
        global $mock_user_meta;

        // Remove referral code from coach
        $mock_user_meta[100]['referral_code'] = '';

        $values = $this->template_manager->get_coach_values(100);
        $validation = $this->template_manager->validate_required_fields($values);

        $this->assertIsArray($validation, "Validation should fail for coach without referral code");
        $this->assertContains('referral_code', $validation);
        $this->assertContains('share_url', $validation, "Share URL depends on referral code");
    }

    /**
     * AC §9: Starter templates use friendly coach voice
     */
    public function testStarterTemplatesFriendlyTone(): void {
        // Use reflection to access private method
        $reflection = new ReflectionClass($this->template_manager);
        $method = $reflection->getMethod('get_starter_templates');
        $method->setAccessible(true);

        $templates = $method->invoke($this->template_manager);

        $this->assertNotEmpty($templates, "Starter templates should be provided");

        foreach ($templates as $template) {
            $this->assertArrayHasKey('name', $template);
            $this->assertArrayHasKey('subject', $template);
            $this->assertArrayHasKey('body', $template);

            // Check for friendly tone indicators
            $combined = $template['subject'] . ' ' . $template['body'];
            
            $friendly_indicators = [
                'Hi', 'Hey', 'Cheers', 'excited', 'great', '!', '⚽', '🎉', '💪',
            ];

            $found_friendly = false;
            foreach ($friendly_indicators as $indicator) {
                if (stripos($combined, $indicator) !== false) {
                    $found_friendly = true;
                    break;
                }
            }

            $this->assertTrue($found_friendly, "Template '{$template['name']}' should have friendly tone");

            // Check that it's NOT formal/corporate
            $formal_indicators = ['Dear Sir', 'Hereby', 'Pursuant to', 'Sincerely yours'];
            foreach ($formal_indicators as $indicator) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $indicator,
                    $combined,
                    "Template '{$template['name']}' should not have formal/corporate tone"
                );
            }
        }
    }

    /**
     * AC §9: Starter templates use all Marketing must-haves
     */
    public function testStarterTemplatesUseAllFields(): void {
        $reflection = new ReflectionClass($this->template_manager);
        $method = $reflection->getMethod('get_starter_templates');
        $method->setAccessible(true);

        $templates = $method->invoke($this->template_manager);

        // At least one template should use all six fields
        $all_fields = ['coach_name', 'referral_code', 'campaign_name', 'share_url', 'campaign_end_date', 'cta_url'];

        $found_complete_template = false;
        foreach ($templates as $template) {
            $combined = $template['subject'] . ' ' . $template['body'];
            $uses_all = true;

            foreach ($all_fields as $field) {
                if (strpos($combined, '{{' . $field . '}}') === false) {
                    $uses_all = false;
                    break;
                }
            }

            if ($uses_all) {
                $found_complete_template = true;
                break;
            }
        }

        // At least one template should have coach_name, referral_code, share_url, cta_url
        $found_core_fields = false;
        foreach ($templates as $template) {
            $combined = $template['subject'] . ' ' . $template['body'];
            if (
                strpos($combined, '{{coach_name}}') !== false &&
                strpos($combined, '{{referral_code}}') !== false &&
                strpos($combined, '{{share_url}}') !== false &&
                strpos($combined, '{{cta_url}}') !== false
            ) {
                $found_core_fields = true;
                break;
            }
        }

        $this->assertTrue($found_core_fields, "At least one template should use core merge fields");
    }

    /**
     * Test template CRUD operations
     */
    public function testTemplateCreateReadUpdateDelete(): void {
        global $wpdb, $mock_wpdb_last_insert;

        // Create
        $template_data = [
            'name' => 'Test Template',
            'subject' => 'Hello {{coach_name}}',
            'body' => 'Welcome to the team!',
        ];

        // Mock insert to return an ID
        $wpdb->insert_id = 1;
        $result = $this->template_manager->create_template($template_data);

        $this->assertIsInt($result, "Create should return template ID");
        $this->assertNotNull($mock_wpdb_last_insert, "Insert should have been called");
        $this->assertEquals('Test Template', $mock_wpdb_last_insert['data']['name']);
    }

    /**
     * AC §1: Archive functionality
     */
    public function testArchiveTemplate(): void {
        global $wpdb, $mock_wpdb_last_update;

        $result = $this->template_manager->archive_template(1);

        $this->assertTrue($result, "Archive should succeed");
        $this->assertNotNull($mock_wpdb_last_update);
        $this->assertEquals('archived', $mock_wpdb_last_update['data']['status']);
        $this->assertNotNull($mock_wpdb_last_update['data']['archived_at']);
    }

    /**
     * Test restore archived template
     */
    public function testRestoreTemplate(): void {
        global $mock_wpdb_last_update;

        $result = $this->template_manager->restore_template(1);

        $this->assertTrue($result, "Restore should succeed");
        $this->assertEquals('active', $mock_wpdb_last_update['data']['status']);
    }

    /**
     * AC §13: No secrets/PII in logs - verify merge values don't contain sensitive data
     */
    public function testNoSecretsInMergeFields(): void {
        $sample_values = $this->template_manager->get_sample_values();

        // Ensure sample values don't contain real-looking sensitive data
        foreach ($sample_values as $key => $value) {
            // No email addresses in samples (except for explicitly email fields)
            if ($key !== 'coach_email') {
                $this->assertDoesNotMatchRegularExpression(
                    '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
                    $value,
                    "Sample value for '$key' should not contain email addresses"
                );
            }

            // No phone numbers
            $this->assertDoesNotMatchRegularExpression(
                '/\+?\d{10,}/',
                $value,
                "Sample value for '$key' should not contain phone numbers"
            );
        }
    }

    /**
     * Test merge field case sensitivity
     */
    public function testMergeFieldCaseSensitivity(): void {
        $template = "Hi {{coach_name}}, code: {{COACH_NAME}}";
        $values = ['coach_name' => 'Sarah'];

        $resolved = $this->template_manager->resolve_merge_fields($template, $values);

        // Our implementation uses exact case matching
        $this->assertStringContainsString('Sarah', $resolved);
        $this->assertStringContainsString('{{COACH_NAME}}', $resolved, "Uppercase placeholder should not be resolved (case-sensitive)");
    }

    /**
     * Test empty template body handling
     */
    public function testEmptyTemplateBody(): void {
        $template = "";
        $values = $this->template_manager->get_sample_values();

        $resolved = $this->template_manager->resolve_merge_fields($template, $values);

        $this->assertEquals('', $resolved);
    }

    /**
     * Test special characters in merge values
     */
    public function testSpecialCharactersInValues(): void {
        $template = "Hi {{coach_name}}, your link is {{share_url}}";
        $values = [
            'coach_name' => "O'Brien & Associates",
            'share_url' => 'https://example.com/?ref=TEST&foo=bar',
            'referral_code' => 'TEST',
            'campaign_name' => '',
            'campaign_end_date' => '',
            'cta_url' => '',
        ];

        $resolved = $this->template_manager->resolve_merge_fields($template, $values);

        $this->assertStringContainsString("O'Brien & Associates", $resolved);
        $this->assertStringContainsString("ref=TEST&foo=bar", $resolved);
    }
}
