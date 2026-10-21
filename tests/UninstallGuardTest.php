<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-uninstaller.php';

class UninstallGuardTest extends TestCase {

    private const PLUGIN_SELF = 'customer-referral-system/customer-referral-system.php';

    protected function setUp(): void {
        parent::setUp();
        $this->resetUninstallMocks();
        $this->seedRolesAndOptions();
    }

    protected function tearDown(): void {
        $this->resetUninstallMocks();
        parent::tearDown();
    }

    private function resetUninstallMocks(): void {
        global $mock_options, $mock_remove_role_calls, $mock_delete_option_calls, $mock_is_admin;

        $mock_remove_role_calls = [];
        $mock_delete_option_calls = [];
        $mock_is_admin = false;

        unset(
            $mock_options[InterSoccer_Referral_Uninstaller::OPTION_WIPE],
            $mock_options['intersoccer_commission_first'],
            $mock_options['intersoccer_commission_second'],
            $mock_options['intersoccer_commission_third'],
            $mock_options['intersoccer_version'],
            $mock_options['uninstall_plugins']
        );
    }

    private function seedRolesAndOptions(): void {
        add_role('coach', 'Coach', ['read' => true]);
        add_role('content_creator', 'Content Creator', ['read' => true]);
        add_role('partner', 'Partner', ['read' => true]);

        update_option('intersoccer_commission_first', '10');
        update_option('intersoccer_commission_second', '20');
        update_option('intersoccer_commission_third', '30');
        update_option('intersoccer_version', '1.10.18');
    }

    public function test_run_skipped_default_when_option_unset(): void {
        $result = InterSoccer_Referral_Uninstaller::run(self::PLUGIN_SELF, $this->onlySelfPlugins());

        $this->assertSame('skipped_default', $result);
        $this->assertEmpty($GLOBALS['mock_remove_role_calls']);
        $this->assertEmpty($GLOBALS['mock_delete_option_calls']);
        $this->assertNotNull(get_role('coach'));
        $this->assertSame('10', get_option('intersoccer_commission_first'));
    }

    public function test_run_skipped_other_copy_same_main_file_basename(): void {
        update_option(InterSoccer_Referral_Uninstaller::OPTION_WIPE, '1');

        $plugins = [
            self::PLUGIN_SELF => ['Name' => 'InterSoccer Referral System'],
            'customer-referral-system-2.6.0/customer-referral-system.php' => ['Name' => 'InterSoccer Referral System'],
            'other-plugin/other-plugin.php' => ['Name' => 'Other Plugin'],
        ];

        $result = InterSoccer_Referral_Uninstaller::run(self::PLUGIN_SELF, $plugins);

        $this->assertSame('skipped_other_copy', $result);
        $this->assertEmpty($GLOBALS['mock_remove_role_calls']);
        $this->assertEmpty($GLOBALS['mock_delete_option_calls']);
        $this->assertNotNull(get_role('coach'));
    }

    public function test_run_skipped_other_copy_same_plugin_name_different_file(): void {
        update_option(InterSoccer_Referral_Uninstaller::OPTION_WIPE, '1');

        $plugins = [
            self::PLUGIN_SELF => ['Name' => 'InterSoccer Referral System'],
            'legacy-folder/legacy-crs.php' => ['Name' => 'InterSoccer Referral System'],
            'unrelated/hello.php' => ['Name' => 'Hello Dolly'],
        ];

        $result = InterSoccer_Referral_Uninstaller::run(self::PLUGIN_SELF, $plugins);

        $this->assertSame('skipped_other_copy', $result);
        $this->assertEmpty($GLOBALS['mock_remove_role_calls']);
        $this->assertNotNull(get_role('partner'));
    }

    public function test_run_wiped_when_opted_in_and_no_other_copy(): void {
        update_option(InterSoccer_Referral_Uninstaller::OPTION_WIPE, '1');

        $plugins = [
            self::PLUGIN_SELF => ['Name' => 'InterSoccer Referral System'],
            'unrelated/hello.php' => ['Name' => 'Hello Dolly'],
        ];

        $result = InterSoccer_Referral_Uninstaller::run(self::PLUGIN_SELF, $plugins);

        $this->assertSame('wiped', $result);
        $this->assertSame(
            ['coach', 'content_creator', 'partner'],
            $GLOBALS['mock_remove_role_calls']
        );
        $this->assertContains('intersoccer_commission_first', $GLOBALS['mock_delete_option_calls']);
        $this->assertContains('intersoccer_commission_second', $GLOBALS['mock_delete_option_calls']);
        $this->assertContains('intersoccer_commission_third', $GLOBALS['mock_delete_option_calls']);
        $this->assertContains('intersoccer_version', $GLOBALS['mock_delete_option_calls']);
        $this->assertContains(InterSoccer_Referral_Uninstaller::OPTION_WIPE, $GLOBALS['mock_delete_option_calls']);
        $this->assertNull(get_role('coach'));
        $this->assertFalse(get_option('intersoccer_version'));
    }

    public function test_run_accepts_yes_and_true_opt_in(): void {
        update_option(InterSoccer_Referral_Uninstaller::OPTION_WIPE, 'yes');
        $this->assertSame('wiped', InterSoccer_Referral_Uninstaller::run(self::PLUGIN_SELF, $this->onlySelfPlugins()));

        $this->seedRolesAndOptions();
        $this->resetUninstallMocks();
        $this->seedRolesAndOptions();
        update_option(InterSoccer_Referral_Uninstaller::OPTION_WIPE, true);
        $this->assertSame('wiped', InterSoccer_Referral_Uninstaller::run(self::PLUGIN_SELF, $this->onlySelfPlugins()));
    }

    public function test_uninstall_php_does_not_load_main_plugin_file(): void {
        $path = dirname(__DIR__) . '/uninstall.php';
        $source = file_get_contents($path);
        $this->assertIsString($source);
        $this->assertStringNotContainsString('customer-referral-system.php', $source);
        $this->assertStringContainsString('class-uninstaller.php', $source);
    }

    public function test_uninstall_php_exits_without_wp_uninstall_plugin(): void {
        $path = dirname(__DIR__) . '/uninstall.php';
        $cmd = 'php -r ' . escapeshellarg('include ' . var_export($path, true) . '; echo "RAN";');
        $output = shell_exec($cmd);
        $this->assertNotSame('RAN', trim((string) $output));
    }

    public function test_cleanup_stale_uninstall_plugins_removes_only_crs_callbacks(): void {
        global $mock_options, $mock_is_admin;

        $mock_is_admin = true;
        $mock_options['uninstall_plugins'] = [
            'old-crs/customer-referral-system.php' => ['InterSoccer_Referral_System', 'uninstall'],
            'other/plugin.php' => 'Other_Plugin::uninstall',
            'legacy/customer-referral-system.php' => 'InterSoccer_Referral_System::uninstall',
        ];

        InterSoccer_Referral_Uninstaller::cleanup_stale_uninstall_hooks();

        $remaining = get_option('uninstall_plugins');
        $this->assertIsArray($remaining);
        $this->assertCount(1, $remaining);
        $this->assertArrayHasKey('other/plugin.php', $remaining);
        $this->assertSame('Other_Plugin::uninstall', $remaining['other/plugin.php']);
    }

    public function test_cleanup_stale_uninstall_plugins_no_op_when_not_admin(): void {
        global $mock_options, $mock_is_admin;

        $mock_is_admin = false;
        $mock_options['uninstall_plugins'] = [
            'old-crs/customer-referral-system.php' => ['InterSoccer_Referral_System', 'uninstall'],
        ];

        InterSoccer_Referral_Uninstaller::cleanup_stale_uninstall_hooks();

        $remaining = get_option('uninstall_plugins');
        $this->assertArrayHasKey('old-crs/customer-referral-system.php', $remaining);
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function onlySelfPlugins(): array {
        return [
            self::PLUGIN_SELF => ['Name' => 'InterSoccer Referral System'],
        ];
    }
}
