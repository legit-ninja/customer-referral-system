<?php

if (!defined('ABSPATH')) {
    exit;
}

class InterSoccer_Admin_Coach_Events {

    private $page_slug = 'intersoccer-coach-events';

    public function __construct() {
        add_action('wp_ajax_intersoccer_get_coach_events', [$this, 'ajax_get_coach_events']);
        add_action('wp_ajax_intersoccer_save_coach_event', [$this, 'ajax_save_coach_event']);
        add_action('wp_ajax_intersoccer_delete_coach_event', [$this, 'ajax_delete_coach_event']);
        add_action('wp_ajax_intersoccer_update_coach_event_status', [$this, 'ajax_update_coach_event_status']);
        add_action('wp_ajax_intersoccer_search_events', [$this, 'ajax_search_events']);
    }

    /**
     * Render admin page content (called from admin dashboard class).
     */
    public function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'intersoccer-referral'));
        }

        $coaches = $this->get_all_coaches();
        $assignments = InterSoccer_Coach_Events_Manager::get_assignments();
        $nonce = wp_create_nonce('intersoccer_coach_events_nonce');

        ?>
        <div class="wrap intersoccer-coach-events">
            <h1><?php esc_html_e('Coach Event Participation', 'intersoccer-referral'); ?></h1>
            <p><?php esc_html_e('Manage which events coaches are associated with. Coaches can request new events which appear as Pending; administrators can approve, deactivate or remove them.', 'intersoccer-referral'); ?></p>

            <div class="coach-events-grid">
                <div class="coach-events-form">
                    <h2><?php esc_html_e('Add Event Participation', 'intersoccer-referral'); ?></h2>
                    <form id="coach-events-form">
                        <?php wp_nonce_field('intersoccer_coach_events_nonce', 'nonce'); ?>

                        <table class="form-table" role="presentation">
                            <tbody>
                            <tr>
                                <th scope="row"><label for="coach-id"><?php esc_html_e('Coach', 'intersoccer-referral'); ?> *</label></th>
                                <td>
                                    <select id="coach-id" name="coach_id" required>
                                        <option value=""><?php esc_html_e('Select a coach…', 'intersoccer-referral'); ?></option>
                                        <?php foreach ($coaches as $coach): ?>
                                            <option value="<?php echo esc_attr($coach->ID); ?>">
                                                <?php echo esc_html($coach->display_name . ' (' . $coach->user_email . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="event-search-input"><?php esc_html_e('Event', 'intersoccer-referral'); ?> *</label></th>
                                <td>
                                    <div class="event-search-control">
                                        <input type="text" id="event-search-input" placeholder="<?php esc_attr_e('Search by event or product name…', 'intersoccer-referral'); ?>">
                                        <button type="button" class="button" id="event-search-button"><?php esc_html_e('Search', 'intersoccer-referral'); ?></button>
                                        <input type="hidden" id="event-id" name="event_id" value="">
                                        <input type="hidden" id="event-type" name="event_type" value="product">
                                    </div>
                                    <p class="description"><?php esc_html_e('Search WooCommerce products or other supported event post types.', 'intersoccer-referral'); ?></p>
                                    <div id="event-search-results" class="event-search-results" aria-live="polite"></div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="event-status"><?php esc_html_e('Status', 'intersoccer-referral'); ?></label></th>
                                <td>
                                    <select id="event-status" name="status">
                                        <option value="active"><?php esc_html_e('Active', 'intersoccer-referral'); ?></option>
                                        <option value="pending"><?php esc_html_e('Pending', 'intersoccer-referral'); ?></option>
                                        <option value="inactive"><?php esc_html_e('Inactive', 'intersoccer-referral'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="event-notes"><?php esc_html_e('Notes', 'intersoccer-referral'); ?></label></th>
                                <td>
                                    <textarea id="event-notes" name="notes" rows="3" class="large-text" placeholder="<?php esc_attr_e('Optional notes or context for this assignment.', 'intersoccer-referral'); ?>"></textarea>
                                </td>
                            </tr>
                            </tbody>
                        </table>

                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php esc_html_e('Add Event Participation', 'intersoccer-referral'); ?></button>
                            <span class="spinner" id="coach-events-spinner"></span>
                        </p>
                    </form>
                </div>

                <div class="coach-events-list">
                    <h2><?php esc_html_e('Current Assignments', 'intersoccer-referral'); ?></h2>
                    <div id="coach-events-list" class="coach-events-table-wrapper">
                        <?php echo $this->render_events_table($assignments); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .coach-events-grid {
                display: grid;
                grid-template-columns: 1fr 1.4fr;
                gap: 30px;
                margin-top: 20px;
            }
            .coach-events-form,
            .coach-events-list {
                background: #fff;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            }
            @media (max-width: 960px) {
                .coach-events-grid { grid-template-columns: 1fr; }
            }
            .coach-events-table {
                width: 100%;
                border-collapse: collapse;
            }
            .coach-events-table th,
            .coach-events-table td {
                border-bottom: 1px solid #eee;
                padding: 12px 10px;
                vertical-align: middle;
            }
            .coach-event-share {
                display: flex;
                gap: 6px;
                align-items: center;
            }
            .coach-event-link {
                flex: 1;
                min-width: 220px;
            }
            .coach-events-table tbody tr:hover {
                background: #f9f9f9;
            }
            .coach-events-status.pending { color: #d97706; }
            .coach-events-status.active { color: #198754; }
            .coach-events-status.inactive { color: #6c757d; }
            .event-search-results {
                margin-top: 8px;
            }
            .event-search-result-item {
                padding: 6px 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                margin-bottom: 4px;
                cursor: pointer;
                background: #fafafa;
            }
            .event-search-result-item:hover {
                background: #f0f6ff;
                border-color: #2271b1;
            }
            .coach-events-actions {
                display: flex;
                gap: 6px;
            }
        </style>

        <script>
        jQuery(function($) {
            const nonce = '<?php echo esc_js($nonce); ?>';
            const actions = {
                list: 'intersoccer_get_coach_events',
                save: 'intersoccer_save_coach_event',
                delete: 'intersoccer_delete_coach_event',
                status: 'intersoccer_update_coach_event_status',
                search: 'intersoccer_search_events'
            };

            function refreshAssignments() {
                $.post(ajaxurl, { action: actions.list, nonce: nonce })
                    .done(function(response) {
                        if (response.success && response.data && response.data.html) {
                            $('#coach-events-list').html(response.data.html);
                        }
                    });
            }

            $('#coach-events-form').on('submit', function(e) {
                e.preventDefault();
                const $form = $(this);
                const $spinner = $('#coach-events-spinner');

                if (!$('#event-id').val()) {
                    alert('<?php echo esc_js(__('Please select an event before saving.', 'intersoccer-referral')); ?>');
                    return;
                }

                $spinner.addClass('is-active');

                const payload = {
                    action: actions.save,
                    nonce: nonce,
                    coach_id: $('#coach-id').val(),
                    event_id: $('#event-id').val(),
                    event_type: $('#event-type').val(),
                    status: $('#event-status').val(),
                    notes: $('#event-notes').val()
                };

                $.post(ajaxurl, payload)
                    .done(function(response) {
                        if (response.success) {
                            $form[0].reset();
                            $('#event-search-results').empty();
                            refreshAssignments();
                        } else {
                            alert(response.data || 'Error saving event');
                        }
                    })
                    .fail(function() {
                        alert('Network error while saving event');
                    })
                    .always(function() {
                        $spinner.removeClass('is-active');
                    });
            });

            $('#event-search-button').on('click', function() {
                const term = $('#event-search-input').val();
                if (term.length < 2) {
                    alert('<?php echo esc_js(__('Enter at least two characters to search.', 'intersoccer-referral')); ?>');
                    return;
                }

                $('#event-search-results').html('<p><?php echo esc_js(__('Searching…', 'intersoccer-referral')); ?></p>');

                $.post(ajaxurl, { action: actions.search, nonce: nonce, term: term })
                    .done(function(response) {
                        if (!response.success || !response.data || !response.data.results) {
                            $('#event-search-results').html('<p><?php echo esc_js(__('No events found.', 'intersoccer-referral')); ?></p>');
                            return;
                        }

                        const results = response.data.results;
                        if (!results.length) {
                            $('#event-search-results').html('<p><?php echo esc_js(__('No events found.', 'intersoccer-referral')); ?></p>');
                            return;
                        }

                        const list = $('<div/>');
                        results.forEach(function(item) {
                            const element = $('<div class="event-search-result-item" tabindex="0" />');
                            element.text(item.title + ' (ID: ' + item.id + ')');
                            element.data('eventId', item.id);
                            element.data('eventType', item.type);
                            list.append(element);
                        });
                        $('#event-search-results').html(list);
                    })
                    .fail(function() {
                        $('#event-search-results').html('<p><?php echo esc_js(__('Search failed. Please try again.', 'intersoccer-referral')); ?></p>');
                    });
            });

            $('#event-search-results').on('click keypress', '.event-search-result-item', function(e) {
                if (e.type === 'click' || e.key === 'Enter') {
                    const $item = $(this);
                    $('#event-id').val($item.data('eventId'));
                    $('#event-type').val($item.data('eventType'));
                    $('#event-search-input').val($item.text());
                    $('#event-search-results').empty();
                }
            });

            $('#coach-events-list').on('click', '.coach-event-delete', function(e) {
                e.preventDefault();
                if (!confirm('<?php echo esc_js(__('Remove this event assignment?', 'intersoccer-referral')); ?>')) {
                    return;
                }
                const assignmentId = $(this).data('id');
                $.post(ajaxurl, { action: actions.delete, nonce: nonce, assignment_id: assignmentId })
                    .done(function(response) {
                        if (response.success) {
                            refreshAssignments();
                        } else {
                            alert(response.data || 'Error removing event');
                        }
                    });
            });

            $('#coach-events-list').on('click', '.coach-event-copy', function(e) {
                e.preventDefault();
                const link = $(this).data('link');
                if (!link) {
                    return;
                }

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(link)
                        .then(function() {
                            alert('<?php echo esc_js(__('Link copied to clipboard.', 'intersoccer-referral')); ?>');
                        })
                        .catch(function() {
                            window.prompt('<?php echo esc_js(__('Press Ctrl+C to copy the link', 'intersoccer-referral')); ?>', link);
                        });
                } else {
                    window.prompt('<?php echo esc_js(__('Press Ctrl+C to copy the link', 'intersoccer-referral')); ?>', link);
                }
            });

            $('#coach-events-list').on('change', '.coach-event-status-select', function() {
                const $select = $(this);
                const assignmentId = $select.data('id');
                $.post(ajaxurl, { action: actions.status, nonce: nonce, assignment_id: assignmentId, status: $select.val() })
                    .done(function(response) {
                        if (!response.success) {
                            alert(response.data || 'Error updating status');
                            refreshAssignments();
                        } else {
                            // Update status badge text without reload
                            $select.closest('tr').find('.coach-events-status').text($select.val()).attr('class', 'coach-events-status ' + $select.val());
                        }
                    })
                    .fail(function() {
                        alert('Network error updating status');
                        refreshAssignments();
                    });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX: return assignments.
     */
    public function ajax_get_coach_events() {
        check_ajax_referer('intersoccer_coach_events_nonce', 'nonce');

        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');

        $coach_id = null;
        if ($is_admin && !empty($_POST['coach_id'])) {
            $coach_id = intval($_POST['coach_id']);
        } elseif (!$is_admin && current_user_can('coach')) {
            $coach_id = $current_user_id;
        }

        $args = [];
        if ($coach_id) {
            $args['coach_id'] = $coach_id;
        }

        $assignments = InterSoccer_Coach_Events_Manager::get_assignments($args);

        wp_send_json_success([
            'html' => $is_admin ? $this->render_events_table($assignments) : '',
            'events' => $this->serialize_assignments($assignments),
        ]);
    }

    /**
     * AJAX: save assignment (handles both admin and coach initiated).
     */
    public function ajax_save_coach_event() {
        check_ajax_referer('intersoccer_coach_events_nonce', 'nonce');

        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');

        if (!$is_admin && !current_user_can('coach')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'intersoccer-referral'));
        }

        $coach_id = $is_admin ? intval($_POST['coach_id'] ?? 0) : $current_user_id;
        $event_id = intval($_POST['event_id'] ?? 0);

        if (!$coach_id || !$event_id) {
            wp_send_json_error(__('Coach and event are required.', 'intersoccer-referral'));
        }

        $status = $is_admin ? sanitize_text_field($_POST['status'] ?? 'active') : 'pending';
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        $event_type = sanitize_key($_POST['event_type'] ?? 'product');

        $result = InterSoccer_Coach_Events_Manager::add_event($coach_id, $event_id, [
            'event_type' => $event_type,
            'status' => $status,
            'source' => $is_admin ? 'admin' : 'coach',
            'assigned_by' => $current_user_id,
            'notes' => $notes,
        ]);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success([
            'assignment_id' => $result,
            'status' => $status,
        ]);
    }

    /**
     * AJAX: delete assignment.
     */
    public function ajax_delete_coach_event() {
        check_ajax_referer('intersoccer_coach_events_nonce', 'nonce');

        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');

        if (!$is_admin && !current_user_can('coach')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'intersoccer-referral'));
        }

        $assignment_id = intval($_POST['assignment_id'] ?? 0);
        if (!$assignment_id) {
            wp_send_json_error(__('Missing assignment identifier.', 'intersoccer-referral'));
        }

        $assignment = InterSoccer_Coach_Events_Manager::get_assignment($assignment_id);

        if (!$assignment) {
            wp_send_json_error(__('Assignment not found.', 'intersoccer-referral'));
        }

        if (!$is_admin && intval($assignment->coach_id) !== $current_user_id) {
            wp_send_json_error(__('You can only modify your own events.', 'intersoccer-referral'));
        }

        $result = InterSoccer_Coach_Events_Manager::delete_assignment($assignment_id);
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success();
    }

    /**
     * AJAX: update assignment status (admins only).
     */
    public function ajax_update_coach_event_status() {
        check_ajax_referer('intersoccer_coach_events_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Only administrators can update event status.', 'intersoccer-referral'));
        }

        $assignment_id = intval($_POST['assignment_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? 'active');

        if (!$assignment_id) {
            wp_send_json_error(__('Missing assignment identifier.', 'intersoccer-referral'));
        }

        $result = InterSoccer_Coach_Events_Manager::update_status($assignment_id, $status, get_current_user_id());
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }

        wp_send_json_success();
    }

    /**
     * AJAX: search events across supported post types.
     */
    public function ajax_search_events() {
        check_ajax_referer('intersoccer_coach_events_nonce', 'nonce');

        if (!current_user_can('manage_options') && !current_user_can('coach')) {
            wp_send_json_error(__('You do not have permission to search events.', 'intersoccer-referral'));
        }

        $term = sanitize_text_field($_POST['term'] ?? '');
        $results = InterSoccer_Coach_Events_Manager::search_events($term);

        wp_send_json_success(['results' => $results]);
    }

    /**
     * Render HTML table for admin list.
     */
    private function render_events_table($assignments) {
        if (empty($assignments)) {
            return '<p>' . esc_html__('No event assignments found.', 'intersoccer-referral') . '</p>';
        }

        ob_start();
        ?>
        <table class="coach-events-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Coach', 'intersoccer-referral'); ?></th>
                    <th><?php esc_html_e('Event', 'intersoccer-referral'); ?></th>
                    <th><?php esc_html_e('Share Link', 'intersoccer-referral'); ?></th>
                    <th><?php esc_html_e('Status', 'intersoccer-referral'); ?></th>
                    <th><?php esc_html_e('Source', 'intersoccer-referral'); ?></th>
                    <th><?php esc_html_e('Assigned', 'intersoccer-referral'); ?></th>
                    <th><?php esc_html_e('Actions', 'intersoccer-referral'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($assignments as $assignment): ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($assignment->coach_name ?: __('Unknown Coach', 'intersoccer-referral')); ?></strong><br>
                        <small><?php echo esc_html($assignment->user_email ?? ''); ?></small>
                    </td>
                    <td>
                        <?php if (!empty($assignment->event_permalink)): ?>
                            <a href="<?php echo esc_url($assignment->event_permalink); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($assignment->event_title); ?></a>
                        <?php else: ?>
                            <?php echo esc_html($assignment->event_title); ?>
                        <?php endif; ?>
                        <br>
                        <small><?php echo esc_html(sprintf(__('Event ID: %d • Type: %s', 'intersoccer-referral'), $assignment->event_id, $assignment->event_type)); ?></small>
                    </td>
                    <td>
                        <?php if (!empty($assignment->event_share_link)): ?>
                            <div class="coach-event-share">
                                <input type="text" class="coach-event-link" value="<?php echo esc_attr($assignment->event_share_link); ?>" readonly>
                                <button type="button" class="button coach-event-copy" data-link="<?php echo esc_attr($assignment->event_share_link); ?>"><?php esc_html_e('Copy', 'intersoccer-referral'); ?></button>
                            </div>
                        <?php else: ?>
                            <em><?php esc_html_e('Link unavailable', 'intersoccer-referral'); ?></em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="coach-events-status <?php echo esc_attr($assignment->status); ?>"><?php echo esc_html(ucfirst($assignment->status)); ?></span><br>
                        <select class="coach-event-status-select" data-id="<?php echo esc_attr($assignment->id); ?>">
                            <option value="active" <?php selected($assignment->status, 'active'); ?>><?php esc_html_e('Active', 'intersoccer-referral'); ?></option>
                            <option value="pending" <?php selected($assignment->status, 'pending'); ?>><?php esc_html_e('Pending', 'intersoccer-referral'); ?></option>
                            <option value="inactive" <?php selected($assignment->status, 'inactive'); ?>><?php esc_html_e('Inactive', 'intersoccer-referral'); ?></option>
                        </select>
                    </td>
                    <td><?php echo esc_html(ucfirst($assignment->source)); ?></td>
                    <td>
                        <?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $assignment->assigned_at)); ?><br>
                        <?php if (!empty($assignment->assigned_by)): ?>
                            <small><?php printf(esc_html__('Updated by #%d', 'intersoccer-referral'), intval($assignment->assigned_by)); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="coach-events-actions">
                            <a href="#" class="button-link-delete coach-event-delete" data-id="<?php echo esc_attr($assignment->id); ?>"><?php esc_html_e('Remove', 'intersoccer-referral'); ?></a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    /**
     * Serialize assignments for JSON responses (coach dashboards).
     */
    private function serialize_assignments($assignments) {
        return array_map(function($assignment) {
            return [
                'id' => intval($assignment->id),
                'coach_id' => intval($assignment->coach_id),
                'event_id' => intval($assignment->event_id),
                'event_title' => $assignment->event_title,
                'event_permalink' => $assignment->event_permalink,
                'event_link' => $assignment->event_share_link,
                'event_type' => $assignment->event_type,
                'status' => $assignment->status,
                'source' => $assignment->source,
                'assigned_at' => $assignment->assigned_at,
            ];
        }, $assignments);
    }

    private function get_all_coaches() {
        return get_users([
            'role' => 'coach',
            'orderby' => 'display_name',
            'order' => 'ASC',
        ]);
    }
}


