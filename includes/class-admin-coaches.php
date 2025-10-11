<?php
// includes/class-admin-coaches.php

class InterSoccer_Admin_Coaches {

    public function render_coaches_page() {
        ?>
        <div class="wrap intersoccer-admin">
            <h1 class="wp-heading-inline">Coach Management</h1>

            <div class="intersoccer-actions">
                <a href="<?php echo admin_url('admin-post.php?action=import_coaches_from_csv'); ?>" class="button button-primary">
                    <span class="dashicons dashicons-upload"></span>
                    Import Coaches from CSV
                </a>
                <button class="button button-secondary" id="add-new-coach">
                    <span class="dashicons dashicons-plus"></span>
                    Add New Coach
                </button>
            </div>

            <div class="intersoccer-coaches-grid">
                <?php $this->display_coaches_list(); ?>
            </div>
        </div>

        <style>
        .intersoccer-actions {
            margin: 20px 0;
            display: flex;
            gap: 10px;
        }

        .intersoccer-coaches-grid {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        </style>
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

            ?>
            <div class="coach-card" data-coach-id="<?php echo $coach->ID; ?>">
                <div class="coach-card-header">
                    <div class="coach-avatar">
                        <?php echo get_avatar($coach->ID, 60); ?>
                    </div>
                    <div class="coach-info">
                        <h3><?php echo esc_html($coach->display_name); ?></h3>
                        <p class="coach-email"><?php echo esc_html($coach->user_email); ?></p>
                        <div class="coach-tier-badge <?php echo strtolower($tier); ?>">
                            <?php echo esc_html($tier); ?>
                        </div>
                    </div>
                    <div class="coach-actions">
                        <button class="coach-action-btn edit-coach" data-coach-id="<?php echo $coach->ID; ?>" title="Edit Coach">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <button class="coach-action-btn message-coach" data-coach-id="<?php echo $coach->ID; ?>" title="Send Message">
                            <span class="dashicons dashicons-email"></span>
                        </button>
                        <button class="coach-action-btn deactivate-coach" data-coach-id="<?php echo $coach->ID; ?>" title="Deactivate Coach">
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

        // Add card-specific styles
        ?>
        <style>
        .coaches-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .coach-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            border: 1px solid #e1e5e9;
        }

        .coach-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .coach-card-header {
            display: flex;
            align-items: center;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: relative;
        }

        .coach-avatar {
            margin-right: 15px;
        }

        .coach-avatar img {
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }

        .coach-info h3 {
            margin: 0 0 5px 0;
            font-size: 18px;
            font-weight: 600;
        }

        .coach-email {
            margin: 0 0 8px 0;
            font-size: 14px;
            opacity: 0.9;
        }

        .coach-tier-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            background: rgba(255, 255, 255, 0.2);
        }

        .coach-tier-badge.gold { background: linear-gradient(45deg, #ffd700, #ffed4e); color: #2c3e50; }
        .coach-tier-badge.platinum { background: linear-gradient(45deg, #e8e8e8, #c0c0c0); color: #2c3e50; }
        .coach-tier-badge.bronze { background: linear-gradient(45deg, #cd7f32, #a0522d); color: white; }
        .coach-tier-badge.silver { background: linear-gradient(45deg, #c0c0c0, #a8a8a8); color: #2c3e50; }

        .coach-actions {
            position: absolute;
            top: 15px;
            right: 15px;
            display: flex;
            gap: 8px;
        }

        .coach-action-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 6px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .coach-action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .coach-action-btn.deactivate-coach:hover {
            background: rgba(231, 76, 60, 0.8);
        }

        .coach-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            padding: 20px;
            background: #f8f9fa;
        }

        .stat-item {
            text-align: center;
        }

        .stat-label {
            display: block;
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .stat-value {
            display: block;
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
        }

        .coach-recent-activity {
            padding: 0 20px 15px 20px;
        }

        .coach-recent-activity h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #2c3e50;
            font-weight: 600;
        }

        .activity-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #ecf0f1;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
        }

        .activity-text {
            flex: 1;
            font-size: 13px;
            color: #2c3e50;
        }

        .activity-date {
            display: block;
            font-size: 11px;
            color: #7f8c8d;
            margin-top: 2px;
        }

        .coach-card-footer {
            padding: 15px 20px;
            background: white;
            border-top: 1px solid #ecf0f1;
        }

        .view-details-btn {
            display: inline-block;
            padding: 8px 16px;
            background: #007cba;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.2s ease;
        }

        .view-details-btn:hover {
            background: #005a87;
        }

        .no-coaches-message {
            text-align: center;
            padding: 40px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .no-coaches-message p {
            font-size: 16px;
            color: #7f8c8d;
            margin: 0;
        }

        .no-coaches-message a {
            color: #007cba;
            text-decoration: none;
            font-weight: 600;
        }

        .no-coaches-message a:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .coaches-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .coach-card-header {
                flex-direction: column;
                text-align: center;
                padding: 15px;
            }

            .coach-avatar {
                margin-right: 0;
                margin-bottom: 10px;
            }

            .coach-actions {
                position: static;
                justify-content: center;
                margin-top: 10px;
            }

            .coach-stats {
                grid-template-columns: repeat(2, 1fr);
                padding: 15px;
            }

            .stat-value {
                font-size: 18px;
            }
        }

        @media (max-width: 480px) {
            .coach-stats {
                grid-template-columns: 1fr;
            }

            .coach-card-header {
                padding: 12px;
            }

            .coach-info h3 {
                font-size: 16px;
            }
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Handle coach actions
            $('.edit-coach').on('click', function() {
                var coachId = $(this).data('coach-id');
                window.location.href = '<?php echo admin_url('user-edit.php?user_id='); ?>' + coachId;
            });

            $('.message-coach').on('click', function() {
                var coachId = $(this).data('coach-id');
                // Open message modal or redirect to message page
                alert('Message functionality coming soon for coach ID: ' + coachId);
            });

            $('.deactivate-coach').on('click', function() {
                if (confirm('Are you sure you want to deactivate this coach?')) {
                    var coachId = $(this).data('coach-id');
                    // AJAX call to deactivate coach
                    $.ajax({
                        url: intersoccer_admin.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'deactivate_coach',
                            coach_id: coachId,
                            nonce: intersoccer_admin.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            } else {
                                alert('Error deactivating coach: ' + response.data.message);
                            }
                        }
                    });
                }
            });

            $('#add-new-coach-link').on('click', function(e) {
                e.preventDefault();
                window.location.href = '<?php echo admin_url('user-new.php'); ?>';
            });
        });
        </script>
        <?php
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