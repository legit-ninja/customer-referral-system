<?php
// includes/class-admin-settings.php

class InterSoccer_Admin_Settings {

    public function render_settings_page() {
        ?>
        <div class="wrap intersoccer-admin">
            <h1 class="wp-heading-inline">Referral System Settings</h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('intersoccer_settings');
                do_settings_sections('intersoccer_settings');
                submit_button();
                ?>
            </form>

            <div class="intersoccer-settings-info">
                <h2>System Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <strong>Plugin Version:</strong> <?php echo INTERSOCCER_REFERRAL_VERSION; ?>
                    </div>
                    <div class="info-item">
                        <strong>Database Tables:</strong>
                        <span class="status-badge <?php echo $this->check_database_tables() ? 'active' : 'inactive'; ?>">
                            <?php echo $this->check_database_tables() ? 'OK' : 'Issues'; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <strong>WooCommerce:</strong>
                        <span class="status-badge <?php echo class_exists('WooCommerce') ? 'active' : 'inactive'; ?>">
                            <?php echo class_exists('WooCommerce') ? 'Connected' : 'Not Found'; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .intersoccer-settings-info {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }

        .info-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .status-badge.active { background: #d5f4e6; color: #27ae60; }
        .status-badge.inactive { background: #fadbd8; color: #e74c3c; }
        </style>
        <?php
    }

    /**
     * Check if database tables exist
     */
    private function check_database_tables() {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'intersoccer_referrals',
            $wpdb->prefix . 'intersoccer_referral_credits',
            $wpdb->prefix . 'intersoccer_credit_redemptions'
        ];

        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                return false;
            }
        }

        return true;
    }
}