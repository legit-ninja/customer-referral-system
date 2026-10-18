<?php
/**
 * Self-contained uninstall logic (no dependency on the rest of the plugin).
 */

defined('ABSPATH') || exit;

class InterSoccer_Referral_Uninstaller {

    const OPTION_WIPE = 'intersoccer_wipe_data_on_uninstall';

    /**
     * @param string $plugin_file Plugin basename being uninstalled.
     * @param array|null $plugins Optional plugin list (same shape as get_plugins()).
     * @return string 'skipped_default'|'skipped_other_copy'|'wiped'
     */
    public static function run($plugin_file, $plugins = null) {
        if (!self::is_wipe_opted_in()) {
            return 'skipped_default';
        }

        if (self::other_copy_installed($plugin_file, $plugins)) {
            return 'skipped_other_copy';
        }

        self::wipe();

        return 'wiped';
    }

    /**
     * @param string $plugin_file
     * @param array|null $plugins
     * @return bool
     */
    public static function other_copy_installed($plugin_file, $plugins = null) {
        if ($plugins === null) {
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $plugins = get_plugins();
        }

        if (!is_array($plugins)) {
            return false;
        }

        $self_name = '';
        if (isset($plugins[$plugin_file]['Name']) && is_string($plugins[$plugin_file]['Name'])) {
            $self_name = $plugins[$plugin_file]['Name'];
        }
        if ($self_name === '') {
            $self_name = 'InterSoccer Referral System';
        }

        $self_basename = basename($plugin_file);

        foreach ($plugins as $key => $data) {
            if ($key === $plugin_file) {
                continue;
            }

            if (basename($key) === $self_basename) {
                return true;
            }

            if (!is_array($data)) {
                continue;
            }

            $other_name = isset($data['Name']) && is_string($data['Name']) ? $data['Name'] : '';
            if ($other_name !== '' && $other_name === $self_name) {
                return true;
            }
        }

        return false;
    }

    public static function wipe() {
        remove_role('coach');
        remove_role('content_creator');
        remove_role('partner');

        delete_option('intersoccer_commission_first');
        delete_option('intersoccer_commission_second');
        delete_option('intersoccer_commission_third');
        delete_option('intersoccer_version');
        delete_option(self::OPTION_WIPE);
    }

    /**
     * Remove legacy CRS entries from the uninstall_plugins option.
     *
     * @return void
     */
    public static function cleanup_stale_uninstall_hooks() {
        if (!is_admin()) {
            return;
        }

        $hooks = get_option('uninstall_plugins');
        if (!is_array($hooks)) {
            return;
        }

        $changed = false;
        foreach ($hooks as $basename => $callback) {
            if ($callback === array('InterSoccer_Referral_System', 'uninstall')
                || $callback === 'InterSoccer_Referral_System::uninstall') {
                unset($hooks[$basename]);
                $changed = true;
            }
        }

        if ($changed) {
            update_option('uninstall_plugins', $hooks);
        }
    }

    /**
     * @return bool
     */
    private static function is_wipe_opted_in() {
        $value = get_option(self::OPTION_WIPE);

        if ($value === true || $value === '1') {
            return true;
        }

        if (is_string($value) && strtolower($value) === 'yes') {
            return true;
        }

        return false;
    }
}
