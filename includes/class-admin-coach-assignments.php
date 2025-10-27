<?php
/**
 * Coach Assignments Admin Page
 * Manages which coaches are assigned to which venues, camps, and courses
 */

class InterSoccer_Admin_Coach_Assignments {

    public function __construct() {
        add_action('wp_ajax_save_coach_assignments', [$this, 'save_coach_assignments']);
        add_action('wp_ajax_get_coach_assignments', [$this, 'get_coach_assignments']);
        add_action('wp_ajax_delete_coach_assignment', [$this, 'delete_coach_assignment']);
    }

    /**
     * Render the coach assignments admin page
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Get all coaches
        $coaches = $this->get_all_coaches();

        // Get all venues from rosters table
        $venues = $this->get_all_venues();

        // Get existing assignments
        $assignments = $this->get_all_assignments();

        ?>
        <div class="wrap">
            <h1><?php _e('Coach Assignments', 'intersoccer-referral'); ?></h1>
            <p><?php _e('Manage which coaches are assigned to specific venues, camps, and courses. Coaches will only see rosters for events they are assigned to.', 'intersoccer-referral'); ?></p>

            <div class="coach-assignments-container">
                <div class="assignments-form">
                    <h2><?php _e('Add New Assignment', 'intersoccer-referral'); ?></h2>
                    <form id="coach-assignment-form">
                        <?php wp_nonce_field('coach_assignments_nonce', 'nonce'); ?>

                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="coach_id"><?php _e('Coach', 'intersoccer-referral'); ?> *</label>
                                </th>
                                <td>
                                    <select name="coach_id" id="coach_id" required>
                                        <option value=""><?php _e('Select a coach...', 'intersoccer-referral'); ?></option>
                                        <?php foreach ($coaches as $coach): ?>
                                            <option value="<?php echo esc_attr($coach->ID); ?>">
                                                <?php echo esc_html($coach->display_name . ' (' . $coach->user_email . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="venue"><?php _e('Venue/Camp/Course', 'intersoccer-referral'); ?> *</label>
                                </th>
                                <td>
                                    <select name="venue" id="venue" required>
                                        <option value=""><?php _e('Select a venue...', 'intersoccer-referral'); ?></option>
                                        <?php foreach ($venues as $venue): ?>
                                            <option value="<?php echo esc_attr($venue); ?>">
                                                <?php echo esc_html($venue); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description"><?php _e('Select the venue, camp, or course this coach is assigned to.', 'intersoccer-referral'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="assignment_type"><?php _e('Assignment Type', 'intersoccer-referral'); ?> *</label>
                                </th>
                                <td>
                                    <select name="assignment_type" id="assignment_type" required>
                                        <option value="venue"><?php _e('Venue', 'intersoccer-referral'); ?></option>
                                        <option value="camp"><?php _e('Camp', 'intersoccer-referral'); ?></option>
                                        <option value="course"><?php _e('Course', 'intersoccer-referral'); ?></option>
                                        <option value="event"><?php _e('Event', 'intersoccer-referral'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="canton"><?php _e('Canton (Optional)', 'intersoccer-referral'); ?></label>
                                </th>
                                <td>
                                    <input type="text" name="canton" id="canton" class="regular-text"
                                           placeholder="<?php _e('e.g., Zurich, Bern, Geneva', 'intersoccer-referral'); ?>">
                                    <p class="description"><?php _e('Specify the canton for regional filtering.', 'intersoccer-referral'); ?></p>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <input type="submit" name="submit" id="submit" class="button button-primary"
                                   value="<?php _e('Add Assignment', 'intersoccer-referral'); ?>">
                            <span id="assignment-spinner" class="spinner" style="float: none; margin-top: 0;"></span>
                        </p>
                    </form>
                </div>

                <div class="current-assignments">
                    <h2><?php _e('Current Assignments', 'intersoccer-referral'); ?></h2>
                    <div id="assignments-list">
                        <?php $this->render_assignments_list($assignments, $coaches); ?>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .coach-assignments-container {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 30px;
                margin-top: 20px;
            }

            .assignments-form, .current-assignments {
                background: #fff;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }

            .assignment-item {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 15px;
                border: 1px solid #ddd;
                border-radius: 6px;
                margin-bottom: 10px;
                background: #fafafa;
            }

            .assignment-info h4 {
                margin: 0 0 5px 0;
                color: #23282d;
            }

            .assignment-meta {
                color: #666;
                font-size: 12px;
            }

            .assignment-actions {
                display: flex;
                gap: 10px;
            }

            .delete-assignment {
                color: #dc3232;
                text-decoration: none;
                padding: 4px 8px;
                border: 1px solid #dc3232;
                border-radius: 4px;
                font-size: 12px;
            }

            .delete-assignment:hover {
                background: #dc3232;
                color: #fff;
            }

            @media (max-width: 768px) {
                .coach-assignments-container {
                    grid-template-columns: 1fr;
                }
            }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Handle form submission
            $('#coach-assignment-form').on('submit', function(e) {
                e.preventDefault();

                var $form = $(this);
                var $submit = $form.find('#submit');
                var $spinner = $('#assignment-spinner');

                $submit.prop('disabled', true);
                $spinner.addClass('is-active');

                var formData = {
                    action: 'save_coach_assignments',
                    nonce: $form.find('#nonce').val(),
                    coach_id: $('#coach_id').val(),
                    venue: $('#venue').val(),
                    assignment_type: $('#assignment_type').val(),
                    canton: $('#canton').val()
                };

                $.post(ajaxurl, formData)
                    .done(function(response) {
                        if (response.success) {
                            // Reload assignments list
                            loadAssignments();
                            // Reset form
                            $form[0].reset();
                            alert('Assignment added successfully!');
                        } else {
                            alert('Error: ' + (response.data || 'Unknown error'));
                        }
                    })
                    .fail(function() {
                        alert('Network error occurred.');
                    })
                    .always(function() {
                        $submit.prop('disabled', false);
                        $spinner.removeClass('is-active');
                    });
            });

            // Load assignments function
            function loadAssignments() {
                $.post(ajaxurl, {
                    action: 'get_coach_assignments'
                })
                .done(function(response) {
                    if (response.success) {
                        $('#assignments-list').html(response.data.html);
                    }
                });
            }

            // Handle delete assignment
            $(document).on('click', '.delete-assignment', function(e) {
                e.preventDefault();

                if (!confirm('Are you sure you want to delete this assignment?')) {
                    return;
                }

                var $link = $(this);
                var assignmentId = $link.data('id');

                $.post(ajaxurl, {
                    action: 'delete_coach_assignment',
                    assignment_id: assignmentId,
                    nonce: '<?php echo wp_create_nonce("coach_assignments_nonce"); ?>'
                })
                .done(function(response) {
                    if (response.success) {
                        $link.closest('.assignment-item').fadeOut();
                    } else {
                        alert('Error: ' + (response.data || 'Unknown error'));
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Get all coaches
     */
    private function get_all_coaches() {
        $args = [
            'role' => 'coach',
            'orderby' => 'display_name',
            'order' => 'ASC'
        ];
        return get_users($args);
    }

    /**
     * Get all venues from rosters table
     */
    private function get_all_venues() {
        global $wpdb;
        $rosters_table = $wpdb->prefix . 'intersoccer_rosters';

        $venues = $wpdb->get_col("SELECT DISTINCT venue FROM $rosters_table WHERE venue != '' ORDER BY venue");
        return array_filter($venues);
    }

    /**
     * Get all assignments
     */
    private function get_all_assignments() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_coach_assignments';

        return $wpdb->get_results("
            SELECT a.*, u.display_name as coach_name, u.user_email
            FROM $table_name a
            LEFT JOIN {$wpdb->users} u ON a.coach_id = u.ID
            WHERE a.active = 1
            ORDER BY u.display_name, a.venue
        ");
    }

    /**
     * Render assignments list
     */
    private function render_assignments_list($assignments, $coaches) {
        if (empty($assignments)) {
            echo '<p>' . __('No assignments found.', 'intersoccer-referral') . '</p>';
            return;
        }

        foreach ($assignments as $assignment) {
            ?>
            <div class="assignment-item">
                <div class="assignment-info">
                    <h4><?php echo esc_html($assignment->coach_name ?: 'Unknown Coach'); ?></h4>
                    <div class="assignment-meta">
                        <strong><?php _e('Venue:', 'intersoccer-referral'); ?></strong> <?php echo esc_html($assignment->venue); ?> |
                        <strong><?php _e('Type:', 'intersoccer-referral'); ?></strong> <?php echo esc_html(ucfirst($assignment->assignment_type)); ?>
                        <?php if ($assignment->canton): ?>
                            | <strong><?php _e('Canton:', 'intersoccer-referral'); ?></strong> <?php echo esc_html($assignment->canton); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="assignment-actions">
                    <a href="#" class="delete-assignment" data-id="<?php echo esc_attr($assignment->id); ?>">
                        <?php _e('Delete', 'intersoccer-referral'); ?>
                    </a>
                </div>
            </div>
            <?php
        }
    }

    /**
     * AJAX handler for saving coach assignments
     */
    public function save_coach_assignments() {
        check_ajax_referer('coach_assignments_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $coach_id = intval($_POST['coach_id']);
        $venue = sanitize_text_field($_POST['venue']);
        $assignment_type = sanitize_text_field($_POST['assignment_type']);
        $canton = sanitize_text_field($_POST['canton']);

        if (!$coach_id || !$venue || !$assignment_type) {
            wp_send_json_error('Missing required fields');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_coach_assignments';

        $result = $wpdb->insert(
            $table_name,
            [
                'coach_id' => $coach_id,
                'venue' => $venue,
                'assignment_type' => $assignment_type,
                'canton' => $canton,
                'active' => 1
            ],
            ['%d', '%s', '%s', '%s', '%d']
        );

        if ($result === false) {
            wp_send_json_error('Database error: ' . $wpdb->last_error);
        }

        wp_send_json_success('Assignment saved successfully');
    }

    /**
     * AJAX handler for getting coach assignments
     */
    public function get_coach_assignments() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $assignments = $this->get_all_assignments();
        $coaches = $this->get_all_coaches();

        ob_start();
        $this->render_assignments_list($assignments, $coaches);
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    /**
     * AJAX handler for deleting coach assignments
     */
    public function delete_coach_assignment() {
        check_ajax_referer('coach_assignments_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        $assignment_id = intval($_POST['assignment_id']);

        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_coach_assignments';

        $result = $wpdb->update(
            $table_name,
            ['active' => 0],
            ['id' => $assignment_id],
            ['%d'],
            ['%d']
        );

        if ($result === false) {
            wp_send_json_error('Database error: ' . $wpdb->last_error);
        }

        wp_send_json_success('Assignment deleted successfully');
    }

    /**
     * Get coach assignments for a specific coach (static method)
     */
    public static function get_coach_assignments_static($coach_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_coach_assignments';

        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $table_name
            WHERE coach_id = %d AND active = 1
        ", $coach_id));
    }

    /**
     * Check if coach has access to a specific venue
     */
    public static function coach_has_venue_access($coach_id, $venue) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_coach_assignments';

        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $table_name
            WHERE coach_id = %d AND venue = %s AND active = 1
        ", $coach_id, $venue));

        return $count > 0;
    }

    /**
     * Get venues accessible by a coach
     */
    public static function get_coach_accessible_venues($coach_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_coach_assignments';

        return $wpdb->get_col($wpdb->prepare("
            SELECT venue FROM $table_name
            WHERE coach_id = %d AND active = 1
        ", $coach_id));
    }
}