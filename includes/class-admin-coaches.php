<?php
// includes/class-admin-coaches.php

class InterSoccer_Admin_Coaches {

    public function render_coaches_page() {
        ?>
        <div class="wrap intersoccer-admin">
            <h1 class="wp-heading-inline">Coach Management</h1>

            <div class="intersoccer-actions">
                <button class="button button-primary" id="import-coaches-btn">
                    <span class="dashicons dashicons-upload"></span>
                    Import Coaches from CSV
                </button>
                <button class="button button-secondary" id="add-new-coach">
                    <span class="dashicons dashicons-plus"></span>
                    Add New Coach
                </button>
                <div class="coach-bulk-actions" style="display: none;">
                    <button type="button" class="button button-secondary" id="send-referral-selected" title="<?php esc_attr_e('Send referral code to selected coaches', 'intersoccer-referral'); ?>">
                        <span class="dashicons dashicons-email-alt"></span>
                        <?php esc_html_e('Send referral code to selected', 'intersoccer-referral'); ?>
                    </button>
                    <button type="button" class="button button-secondary" id="send-referral-all" title="<?php esc_attr_e('Send referral code to all coaches', 'intersoccer-referral'); ?>">
                        <span class="dashicons dashicons-email-alt"></span>
                        <?php esc_html_e('Send referral code to all', 'intersoccer-referral'); ?>
                    </button>
                </div>
            </div>

            <!-- Coach Import Modal -->
            <div id="coach-import-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 100000; align-items: center; justify-content: center;">
                <div style="background: white; padding: 30px; border-radius: 8px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;">
                    <h2 style="margin-top: 0;"><?php esc_html_e('Import Coaches from CSV', 'intersoccer-referral'); ?></h2>
                    <p class="description">
                        <?php esc_html_e('Upload a CSV file containing coach information. Required columns: First Name, Last Name, Email. Optional: referral_code (imported as-is if provided). Enable “Generate missing codes” only when you want the system to create codes for rows without one.', 'intersoccer-referral'); ?>
                    </p>
                    <p>
                        <a href="<?php echo esc_url(InterSoccer_Admin_Settings::get_coach_csv_sample_download_url()); ?>" class="button button-secondary">
                            <span class="dashicons dashicons-download" style="vertical-align: text-top;"></span>
                            <?php esc_html_e('Download sample CSV', 'intersoccer-referral'); ?>
                        </a>
                    </p>
                    
                    <form id="coach-import-form-modal" method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('import_coaches_from_csv', '_wpnonce'); ?>
                        
                        <table class="form-table" role="presentation">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="coaches_csv_modal"><?php esc_html_e('CSV File', 'intersoccer-referral'); ?></label>
                                    </th>
                                    <td>
                                        <input type="file" 
                                               id="coaches_csv_modal" 
                                               name="coaches_csv" 
                                               accept=".csv"
                                               required>
                                        <p class="description">
                                            <?php esc_html_e('Select a CSV file containing coach information. Maximum file size: 10MB.', 'intersoccer-referral'); ?>
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="update_existing_modal"><?php esc_html_e('Update Existing', 'intersoccer-referral'); ?></label>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox" 
                                                   id="update_existing_modal" 
                                                   name="update_existing" 
                                                   value="1">
                                            <?php esc_html_e('Update existing coaches if they already exist (by email)', 'intersoccer-referral'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="generate_referral_codes_modal"><?php esc_html_e('Generate Missing Codes', 'intersoccer-referral'); ?></label>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox"
                                                   id="generate_referral_codes_modal"
                                                   name="generate_referral_codes"
                                                   value="1">
                                            <?php esc_html_e('Generate referral codes for coaches without a code in the CSV (and without an existing code)', 'intersoccer-referral'); ?>
                                        </label>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <p class="submit">
                            <button type="submit" 
                                    id="import-submit-btn-modal" 
                                    class="button button-primary">
                                <span class="dashicons dashicons-upload"></span>
                                <?php esc_html_e('Import Coaches', 'intersoccer-referral'); ?>
                            </button>
                            <button type="button" 
                                    id="cancel-import-btn" 
                                    class="button button-secondary">
                                <?php esc_html_e('Cancel', 'intersoccer-referral'); ?>
                            </button>
                        </p>
                        
                        <!-- Import Status -->
                        <div id="import-status-modal" style="display: none; margin-top: 20px;">
                            <div class="progress-container" style="margin-bottom: 10px;">
                                <div class="progress-bar" style="background: #f0f0f0; border-radius: 4px; overflow: hidden; height: 30px;">
                                    <div id="progress-fill-modal" class="progress-fill" style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s;"></div>
                                </div>
                                <div id="progress-text-modal" class="progress-text" style="text-align: center; margin-top: 10px;"></div>
                            </div>
                        </div>
                        
                        <!-- Import Results -->
                        <div id="import-results-modal" style="display: none; margin-top: 20px;">
                            <div id="import-summary-content-modal"></div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add New Coach Modal -->
            <div id="add-coach-modal" class="intersoccer-modal" style="display: none;">
                <div class="intersoccer-modal-overlay"></div>
                <div class="intersoccer-modal-content">
                    <div class="intersoccer-modal-header">
                        <h2><?php esc_html_e('Add New Coach', 'intersoccer-referral'); ?></h2>
                        <button type="button" class="intersoccer-modal-close" aria-label="<?php esc_attr_e('Close', 'intersoccer-referral'); ?>">
                            <span class="dashicons dashicons-no-alt"></span>
                        </button>
                    </div>
                    <form id="add-coach-form" method="post">
                        <?php wp_nonce_field('add_new_coach', 'add_coach_nonce'); ?>
                        
                        <div class="intersoccer-modal-body">
                            <table class="form-table" role="presentation">
                                <tbody>
                                    <tr>
                                        <th scope="row">
                                            <label for="coach_first_name"><?php esc_html_e('First Name', 'intersoccer-referral'); ?> <span class="required">*</span></label>
                                        </th>
                                        <td>
                                            <input type="text" 
                                                   id="coach_first_name" 
                                                   name="first_name" 
                                                   class="regular-text"
                                                   required>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="coach_last_name"><?php esc_html_e('Last Name', 'intersoccer-referral'); ?> <span class="required">*</span></label>
                                        </th>
                                        <td>
                                            <input type="text" 
                                                   id="coach_last_name" 
                                                   name="last_name" 
                                                   class="regular-text"
                                                   required>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="coach_email"><?php esc_html_e('Email', 'intersoccer-referral'); ?> <span class="required">*</span></label>
                                        </th>
                                        <td>
                                            <input type="email" 
                                                   id="coach_email" 
                                                   name="email" 
                                                   class="regular-text"
                                                   required>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="coach_referral_code"><?php esc_html_e('Referral Code', 'intersoccer-referral'); ?></label>
                                        </th>
                                        <td>
                                            <input type="text" 
                                                   id="coach_referral_code" 
                                                   name="referral_code" 
                                                   class="regular-text"
                                                   placeholder="<?php esc_attr_e('Leave blank to auto-generate', 'intersoccer-referral'); ?>">
                                            <p class="description">
                                                <?php esc_html_e('Optional. If left blank, a unique code will be generated automatically.', 'intersoccer-referral'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
                                            <label for="coach_send_notification"><?php esc_html_e('Send Welcome Email', 'intersoccer-referral'); ?></label>
                                        </th>
                                        <td>
                                            <label>
                                                <input type="checkbox" 
                                                       id="coach_send_notification" 
                                                       name="send_notification" 
                                                       value="1"
                                                       checked>
                                                <?php esc_html_e('Send welcome email with login credentials', 'intersoccer-referral'); ?>
                                            </label>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="intersoccer-modal-footer">
                            <div id="add-coach-message" style="display: none;"></div>
                            <button type="button" class="button button-secondary intersoccer-modal-cancel">
                                <?php esc_html_e('Cancel', 'intersoccer-referral'); ?>
                            </button>
                            <button type="submit" id="add-coach-submit" class="button button-primary">
                                <span class="dashicons dashicons-plus-alt2"></span>
                                <?php esc_html_e('Add Coach', 'intersoccer-referral'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="intersoccer-coaches-search">
                <label for="coach-search-input" class="screen-reader-text">Search coaches</label>
                <span class="dashicons dashicons-search"></span>
                <input
                    type="search"
                    id="coach-search-input"
                    placeholder="Search coaches by name or email..."
                    autocomplete="off"
                />
            </div>
            <p class="coaches-search-status" aria-live="polite"></p>

            <div class="intersoccer-coaches-grid">
                <?php $this->display_coaches_list(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Display coaches list as cards
     */
    private function display_coaches_list() {
        global $wpdb;

        $coaches = get_users([
            'role' => 'coach',
            'orderby' => 'display_name',
            'order' => 'ASC'
        ]);

        if (empty($coaches)) {
            echo '<div class="no-coaches-message">';
            echo '<p>No coaches found. <a href="#" id="add-new-coach-link">Add your first coach</a> to get started.</p>';
            echo '</div>';
            return;
        }

        echo '<div class="coaches-grid">';

        foreach ($coaches as $coach) {
            $referral_count = $this->get_coach_referral_count($coach->ID);
            $total_commission = $this->get_coach_total_commission($coach->ID);
            $conversion_rate = $this->get_coach_conversion_rate($coach->ID);
            $tier = intersoccer_get_coach_tier($coach->ID);
            $recent_referrals = $this->get_coach_recent_referrals($coach->ID, 3);
            $active_partnerships = $this->get_coach_active_partnerships($coach->ID);
            $search_tokens = strtolower($coach->display_name . ' ' . $coach->user_email);

            <?php
            $referral_code = InterSoccer_Referral_Handler::get_coach_referral_code($coach->ID);
            ?>
            <div class="coach-card" data-coach-id="<?php echo $coach->ID; ?>" data-search="<?php echo esc_attr($search_tokens); ?>">
                <div class="coach-card-header">
                    <div class="coach-select">
                        <label class="screen-reader-text" for="coach-select-<?php echo $coach->ID; ?>"><?php esc_html_e('Select coach', 'intersoccer-referral'); ?></label>
                        <input type="checkbox" class="coach-checkbox" id="coach-select-<?php echo $coach->ID; ?>" value="<?php echo $coach->ID; ?>" aria-label="<?php esc_attr_e('Select coach', 'intersoccer-referral'); ?>">
                    </div>
                    <div class="coach-avatar">
                        <?php echo get_avatar($coach->ID, 60); ?>
                    </div>
                    <div class="coach-info">
                        <h3><?php echo esc_html($coach->display_name); ?></h3>
                        <p class="coach-email"><?php echo esc_html($coach->user_email); ?></p>
                        <?php if (!empty($referral_code)): ?>
                        <div class="coach-referral-code">
                            <span class="referral-code-label"><?php esc_html_e('Referral Code:', 'intersoccer-referral'); ?></span>
                            <code class="referral-code-value"><?php echo esc_html($referral_code); ?></code>
                        </div>
                        <?php endif; ?>
                        <div class="coach-tier-badge <?php echo strtolower($tier); ?>">
                            <?php echo esc_html($tier); ?>
                        </div>
                    </div>
                    <div class="coach-actions">
                        <button class="coach-action-btn send-referral-code" data-coach-id="<?php echo $coach->ID; ?>" title="<?php esc_attr_e('Send referral code', 'intersoccer-referral'); ?>">
                            <span class="dashicons dashicons-email-alt"></span>
                        </button>
                        <button class="coach-action-btn edit-coach" data-coach-id="<?php echo $coach->ID; ?>" title="<?php esc_attr_e('Edit Coach', 'intersoccer-referral'); ?>">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <button
                            class="coach-action-btn message-coach"
                            data-coach-id="<?php echo $coach->ID; ?>"
                            data-coach-email="<?php echo esc_attr($coach->user_email); ?>"
                            title="<?php esc_attr_e('Contact via MS Teams', 'intersoccer-referral'); ?>"
                        >
                            <span class="dashicons dashicons-format-chat"></span>
                        </button>
                        <button class="coach-action-btn deactivate-coach" data-coach-id="<?php echo $coach->ID; ?>" title="<?php esc_attr_e('Deactivate Coach', 'intersoccer-referral'); ?>">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>
                </div>

                <div class="coach-stats">
                    <div class="stat-item">
                        <span class="stat-label">Referrals</span>
                        <span class="stat-value"><?php echo number_format($referral_count); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Commission</span>
                        <span class="stat-value"><?php echo number_format($total_commission, 0); ?> CHF</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Conversion</span>
                        <span class="stat-value"><?php echo number_format($conversion_rate, 1); ?>%</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Partnerships</span>
                        <span class="stat-value"><?php echo number_format($active_partnerships); ?></span>
                    </div>
                </div>

                <?php if (!empty($recent_referrals)): ?>
                <div class="coach-recent-activity">
                    <h4>Recent Activity</h4>
                    <ul class="activity-list">
                        <?php foreach ($recent_referrals as $referral): ?>
                        <li class="activity-item">
                            <span class="activity-icon">
                                <span class="dashicons dashicons-plus"></span>
                            </span>
                            <span class="activity-text">
                                Referred <?php echo esc_html($referral->customer_name); ?>
                                <span class="activity-date"><?php echo human_time_diff(strtotime($referral->created_at), current_time('timestamp')); ?> ago</span>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="coach-card-footer">
                    <a href="<?php echo admin_url('admin.php?page=intersoccer-coach-referrals&coach_id=' . $coach->ID); ?>" class="view-details-btn">
                        View Details
                    </a>
                </div>
            </div>
            <?php
        }

        echo '</div>';

    }

    /**
     * Get coach referral count
     */
    private function get_coach_referral_count($coach_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}intersoccer_referrals
            WHERE coach_id = %d
        ", $coach_id));
    }

    /**
     * Get coach total commission
     */
    private function get_coach_total_commission($coach_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(rc.credit_amount), 0)
            FROM {$wpdb->prefix}intersoccer_referral_credits rc
            INNER JOIN {$wpdb->prefix}intersoccer_referrals r ON rc.referral_id = r.id
            WHERE r.coach_id = %d
        ", $coach_id));
    }

    /**
     * Get coach conversion rate
     */
    private function get_coach_conversion_rate($coach_id) {
        global $wpdb;

        $total_referrals = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}intersoccer_referrals
            WHERE coach_id = %d
        ", $coach_id));

        $completed_referrals = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}intersoccer_referrals
            WHERE coach_id = %d AND status = 'completed'
        ", $coach_id));

        return $total_referrals > 0 ? ($completed_referrals / $total_referrals) * 100 : 0;
    }

    /**
     * Get coach recent referrals
     */
    private function get_coach_recent_referrals($coach_id, $limit = 3) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT r.created_at, u.display_name as customer_name
            FROM {$wpdb->prefix}intersoccer_referrals r
            LEFT JOIN {$wpdb->users} u ON r.customer_id = u.ID
            WHERE r.coach_id = %d
            ORDER BY r.created_at DESC
            LIMIT %d
        ", $coach_id, $limit));
    }

    /**
     * Get coach active partnerships count
     */
    private function get_coach_active_partnerships($coach_id) {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->usermeta}
            WHERE meta_key = 'intersoccer_partnership_coach_id'
            AND meta_value = %d
        ", $coach_id));
    }
}