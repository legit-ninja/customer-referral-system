<?php
/**
 * WordPress uninstall handler (preferred over register_uninstall_hook).
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once dirname(__FILE__) . '/includes/class-uninstaller.php';

InterSoccer_Referral_Uninstaller::run(WP_UNINSTALL_PLUGIN);
