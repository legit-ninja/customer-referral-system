<?php
/**
 * Coach Email Template Builder
 * 
 * Handles creation, management, and sending of coach referral campaign emails
 * with merge field support. Implements AC §§1-10,13 from CRS epic #12.
 * 
 * @package InterSoccer_Referral
 * @since 1.9.17
 */

if (!defined('ABSPATH')) {
    exit;
}

class InterSoccer_Coach_Email_Templates {

    private static $instance = null;

    /**
     * Required merge fields that must resolve for a send to succeed
     */
    private const REQUIRED_FIELDS = ['coach_name', 'referral_code', 'share_url'];

    /**
     * All available merge fields for templates
     */
    private const MERGE_FIELDS = [
        'coach_name' => [
            'label' => 'Coach Name',
            'description' => 'Personalize greeting / address',
            'sample' => 'Sarah',
            'required' => true,
        ],
        'referral_code' => [
            'label' => 'Referral Code',
            'description' => "Coach's referral code",
            'sample' => 'SARAH2026',
            'required' => true,
        ],
        'campaign_name' => [
            'label' => 'Campaign Name',
            'description' => 'Named campaign context',
            'sample' => 'Autumn Enrollment 2026',
            'required' => false,
        ],
        'share_url' => [
            'label' => 'Share URL',
            'description' => 'Shareable referral link',
            'sample' => 'https://intersoccer.ch/?ref=SARAH2026',
            'required' => true,
        ],
        'campaign_end_date' => [
            'label' => 'Campaign End Date',
            'description' => 'Campaign expiry (coach-friendly format)',
            'sample' => '31-12-2026',
            'required' => false,
        ],
        'cta_url' => [
            'label' => 'CTA URL',
            'description' => 'Primary call-to-action URL',
            'sample' => 'https://intersoccer.ch/enroll',
            'required' => false,
        ],
    ];

    /**
     * Database table name (without prefix)
     */
    private const TABLE_NAME = 'intersoccer_coach_email_templates';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        add_action('admin_init', [$this, 'maybe_create_table']);
        add_action('wp_ajax_intersoccer_save_email_template', [$this, 'ajax_save_template']);
        add_action('wp_ajax_intersoccer_delete_email_template', [$this, 'ajax_delete_template']);
        add_action('wp_ajax_intersoccer_duplicate_email_template', [$this, 'ajax_duplicate_template']);
        add_action('wp_ajax_intersoccer_archive_email_template', [$this, 'ajax_archive_template']);
        add_action('wp_ajax_intersoccer_preview_email_template', [$this, 'ajax_preview_template']);
        add_action('wp_ajax_intersoccer_test_send_email_template', [$this, 'ajax_test_send_template']);
        add_action('wp_ajax_intersoccer_get_email_template', [$this, 'ajax_get_template']);
    }

    /**
     * Get the full table name with WordPress prefix
     */
    private function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Create the database table if it doesn't exist
     */
    public function maybe_create_table() {
        global $wpdb;

        $table_name = $this->get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            return;
        }

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            subject text NOT NULL,
            body longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            is_default tinyint(1) NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            archived_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_is_default (is_default),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        $this->maybe_seed_default_templates();
    }

    /**
     * Seed default friendly-coach starter templates
     */
    private function maybe_seed_default_templates() {
        global $wpdb;

        $table_name = $this->get_table_name();
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        if ($count > 0) {
            return;
        }

        $templates = $this->get_starter_templates();

        foreach ($templates as $template) {
            $wpdb->insert(
                $table_name,
                [
                    'name' => $template['name'],
                    'subject' => $template['subject'],
                    'body' => $template['body'],
                    'status' => 'active',
                    'is_default' => $template['is_default'] ? 1 : 0,
                    'created_by' => get_current_user_id() ?: 1,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ],
                ['%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s']
            );
        }
    }

    /**
     * Get starter templates in friendly coach voice
     * AC §9: Default starter templates ship in friendly coach voice
     */
    private function get_starter_templates() {
        return [
            [
                'name' => 'Welcome to the Team',
                'subject' => 'Hey {{coach_name}}, ready to share the soccer love? ⚽',
                'body' => $this->get_welcome_template_body(),
                'is_default' => true,
            ],
            [
                'name' => 'Campaign Reminder',
                'subject' => '{{coach_name}}, {{campaign_name}} ends {{campaign_end_date}} – let\'s finish strong!',
                'body' => $this->get_campaign_reminder_template_body(),
                'is_default' => false,
            ],
        ];
    }

    /**
     * Welcome template body in friendly coach voice
     */
    private function get_welcome_template_body() {
        return "Hi {{coach_name}},

Welcome aboard! We're so excited to have you as part of the InterSoccer coaching family. 🎉

You've got your very own referral code ready to go:

**{{referral_code}}**

Here's your personal share link that you can send to parents and friends:
{{share_url}}

When someone signs up using your code, they get a special discount, and you earn rewards – it's a win-win!

Ready to get started? Share your link now:
{{cta_url}}

If you have any questions, just reply to this email – we're always here to help.

Keep inspiring those young players!

Cheers,
The InterSoccer Team";
    }

    /**
     * Campaign reminder template body in friendly coach voice
     */
    private function get_campaign_reminder_template_body() {
        return "Hey {{coach_name}},

Quick heads up – our **{{campaign_name}}** is wrapping up on **{{campaign_end_date}}**!

This is a great time to remind your network about the special offers available. Your referral code:

**{{referral_code}}**

Share your link one more time:
{{share_url}}

Every family you bring in not only supports the program but also earns you rewards. Let's make these last days count!

Sign up families here:
{{cta_url}}

You've got this! 💪

Cheers,
The InterSoccer Team";
    }

    /**
     * Get all available merge fields with metadata
     */
    public function get_merge_fields() {
        $fields = self::MERGE_FIELDS;

        foreach ($fields as $key => &$field) {
            $field['key'] = $key;
            $field['placeholder'] = '{{' . $key . '}}';
        }

        return $fields;
    }

    /**
     * Get all templates (optionally filtered)
     */
    public function get_templates($args = []) {
        global $wpdb;

        $defaults = [
            'status' => 'active',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => -1,
            'include_archived' => false,
        ];

        $args = wp_parse_args($args, $defaults);
        $table_name = $this->get_table_name();

        $where = [];
        $values = [];

        if (!$args['include_archived']) {
            $where[] = "status != 'archived'";
        }

        if ($args['status'] && $args['status'] !== 'all') {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }

        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $order_clause = sprintf(
            'ORDER BY %s %s',
            sanitize_sql_orderby($args['orderby']) ?: 'created_at',
            strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC'
        );

        $limit_clause = $args['limit'] > 0 ? $wpdb->prepare('LIMIT %d', $args['limit']) : '';

        $sql = "SELECT * FROM $table_name $where_clause $order_clause $limit_clause";

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Get a single template by ID
     */
    public function get_template($id) {
        global $wpdb;

        $table_name = $this->get_table_name();
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id),
            ARRAY_A
        );
    }

    /**
     * Create a new template
     */
    public function create_template($data) {
        global $wpdb;

        $table_name = $this->get_table_name();

        $insert_data = [
            'name' => sanitize_text_field($data['name']),
            'subject' => sanitize_text_field($data['subject']),
            'body' => wp_kses_post($data['body']),
            'status' => 'active',
            'is_default' => !empty($data['is_default']) ? 1 : 0,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        $result = $wpdb->insert($table_name, $insert_data);

        if ($result === false) {
            return new WP_Error('db_insert_error', __('Failed to create template', 'intersoccer-referral'));
        }

        return $wpdb->insert_id;
    }

    /**
     * Update an existing template
     */
    public function update_template($id, $data) {
        global $wpdb;

        $table_name = $this->get_table_name();

        $update_data = [
            'name' => sanitize_text_field($data['name']),
            'subject' => sanitize_text_field($data['subject']),
            'body' => wp_kses_post($data['body']),
            'updated_at' => current_time('mysql'),
        ];

        if (isset($data['is_default'])) {
            $update_data['is_default'] = !empty($data['is_default']) ? 1 : 0;
        }

        $result = $wpdb->update(
            $table_name,
            $update_data,
            ['id' => $id],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_update_error', __('Failed to update template', 'intersoccer-referral'));
        }

        return true;
    }

    /**
     * Duplicate a template
     */
    public function duplicate_template($id) {
        $template = $this->get_template($id);

        if (!$template) {
            return new WP_Error('not_found', __('Template not found', 'intersoccer-referral'));
        }

        return $this->create_template([
            'name' => sprintf(__('%s (Copy)', 'intersoccer-referral'), $template['name']),
            'subject' => $template['subject'],
            'body' => $template['body'],
            'is_default' => false,
        ]);
    }

    /**
     * Archive a template (soft delete)
     * AC §1: Admin can archive email templates
     */
    public function archive_template($id) {
        global $wpdb;

        $table_name = $this->get_table_name();

        $result = $wpdb->update(
            $table_name,
            [
                'status' => 'archived',
                'archived_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_update_error', __('Failed to archive template', 'intersoccer-referral'));
        }

        return true;
    }

    /**
     * Restore an archived template
     */
    public function restore_template($id) {
        global $wpdb;

        $table_name = $this->get_table_name();

        $result = $wpdb->update(
            $table_name,
            [
                'status' => 'active',
                'archived_at' => null,
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_update_error', __('Failed to restore template', 'intersoccer-referral'));
        }

        return true;
    }

    /**
     * Delete a template permanently
     */
    public function delete_template($id) {
        global $wpdb;

        $table_name = $this->get_table_name();

        $result = $wpdb->delete($table_name, ['id' => $id], ['%d']);

        if ($result === false) {
            return new WP_Error('db_delete_error', __('Failed to delete template', 'intersoccer-referral'));
        }

        return true;
    }

    /**
     * Resolve merge fields in a string
     * AC §5-6: All six fields must render correctly when used
     * 
     * @param string $content Template content with {{placeholders}}
     * @param array $values Key-value pairs for merge fields
     * @return string Resolved content
     */
    public function resolve_merge_fields($content, $values) {
        foreach (self::MERGE_FIELDS as $key => $field) {
            $placeholder = '{{' . $key . '}}';
            $value = isset($values[$key]) ? $values[$key] : '';
            $content = str_replace($placeholder, $value, $content);
        }

        return $content;
    }

    /**
     * Get sample values for preview
     * AC §3: Preview shows sample resolved values for all six fields
     */
    public function get_sample_values() {
        $values = [];
        foreach (self::MERGE_FIELDS as $key => $field) {
            $values[$key] = $field['sample'];
        }
        return $values;
    }

    /**
     * Get real values for a specific coach
     * 
     * @param int $coach_id Coach user ID
     * @param array $campaign_data Optional campaign-specific data
     * @return array Merge field values
     */
    public function get_coach_values($coach_id, $campaign_data = []) {
        $coach = get_userdata($coach_id);

        if (!$coach) {
            return [];
        }

        $coach_name = trim($coach->first_name);
        if (empty($coach_name)) {
            $coach_name = $coach->display_name;
        }

        $referral_code = get_user_meta($coach_id, 'referral_code', true);
        if (empty($referral_code)) {
            $referral_code = '';
        }

        $share_url = '';
        if (!empty($referral_code)) {
            $share_url = add_query_arg('ref', $referral_code, home_url('/'));
        }

        $campaign_name = isset($campaign_data['campaign_name']) ? $campaign_data['campaign_name'] : '';
        $campaign_end_date = isset($campaign_data['campaign_end_date']) ? $campaign_data['campaign_end_date'] : '';
        $cta_url = isset($campaign_data['cta_url']) ? $campaign_data['cta_url'] : home_url('/');

        if (!empty($campaign_end_date)) {
            $campaign_end_date = $this->format_coach_friendly_date($campaign_end_date);
        }

        return [
            'coach_name' => $coach_name,
            'referral_code' => $referral_code,
            'campaign_name' => $campaign_name,
            'share_url' => $share_url,
            'campaign_end_date' => $campaign_end_date,
            'cta_url' => $cta_url,
        ];
    }

    /**
     * Format date in coach-friendly format
     * AC §7: campaign_end_date renders in coach-friendly date format
     * Default European day-month-year for InterSoccer CH
     */
    public function format_coach_friendly_date($date) {
        if (empty($date)) {
            return '';
        }

        $timestamp = is_numeric($date) ? $date : strtotime($date);

        if ($timestamp === false) {
            return $date;
        }

        return date_i18n('j-n-Y', $timestamp);
    }

    /**
     * Validate that required fields are present
     * AC §6: Fail send on missing required data for coach_name, referral_code, share_url
     * 
     * @param array $values Resolved merge field values
     * @return array|true Array of missing field keys, or true if all present
     */
    public function validate_required_fields($values) {
        $missing = [];

        foreach (self::REQUIRED_FIELDS as $field) {
            if (empty($values[$field])) {
                $missing[] = $field;
            }
        }

        return empty($missing) ? true : $missing;
    }

    /**
     * Check for unresolved placeholders in content
     * AC §6: Unresolved placeholders must not ship to the inbox
     * 
     * @param string $content Resolved content
     * @return array Array of unresolved placeholder keys
     */
    public function find_unresolved_placeholders($content) {
        $unresolved = [];

        foreach (self::MERGE_FIELDS as $key => $field) {
            $placeholder = '{{' . $key . '}}';
            if (strpos($content, $placeholder) !== false) {
                $unresolved[] = $key;
            }
        }

        return $unresolved;
    }

    /**
     * Send email to a coach
     * 
     * @param int $template_id Template ID
     * @param int $coach_id Coach user ID
     * @param array $campaign_data Optional campaign data
     * @return array Result with success status and message
     */
    public function send_to_coach($template_id, $coach_id, $campaign_data = []) {
        $template = $this->get_template($template_id);

        if (!$template) {
            return [
                'success' => false,
                'message' => __('Template not found', 'intersoccer-referral'),
            ];
        }

        $coach = get_userdata($coach_id);

        if (!$coach) {
            return [
                'success' => false,
                'message' => __('Coach not found', 'intersoccer-referral'),
            ];
        }

        $values = $this->get_coach_values($coach_id, $campaign_data);
        $validation = $this->validate_required_fields($values);

        if ($validation !== true) {
            return [
                'success' => false,
                'message' => sprintf(
                    __('Cannot send: missing required fields (%s)', 'intersoccer-referral'),
                    implode(', ', $validation)
                ),
                'missing_fields' => $validation,
            ];
        }

        $subject = $this->resolve_merge_fields($template['subject'], $values);
        $body = $this->resolve_merge_fields($template['body'], $values);

        $unresolved_subject = $this->find_unresolved_placeholders($subject);
        $unresolved_body = $this->find_unresolved_placeholders($body);
        $unresolved = array_unique(array_merge($unresolved_subject, $unresolved_body));

        if (!empty($unresolved)) {
            return [
                'success' => false,
                'message' => sprintf(
                    __('Cannot send: unresolved placeholders (%s)', 'intersoccer-referral'),
                    implode(', ', $unresolved)
                ),
                'unresolved_fields' => $unresolved,
            ];
        }

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $body_html = $this->convert_to_html($body);

        $sent = wp_mail($coach->user_email, $subject, $body_html, $headers);

        if (!$sent) {
            return [
                'success' => false,
                'message' => __('Failed to send email', 'intersoccer-referral'),
            ];
        }

        return [
            'success' => true,
            'message' => sprintf(
                __('Email sent to %s', 'intersoccer-referral'),
                $coach->user_email
            ),
        ];
    }

    /**
     * Test send to admin address
     * AC §8: Test send delivers to designated admin address with same merge behavior
     * 
     * @param int $template_id Template ID
     * @param string $admin_email Admin email address
     * @param int|null $sample_coach_id Coach ID for sample data (or null for sample values)
     * @param array $campaign_data Optional campaign data
     * @return array Result with success status and message
     */
    public function test_send($template_id, $admin_email, $sample_coach_id = null, $campaign_data = []) {
        $template = $this->get_template($template_id);

        if (!$template) {
            return [
                'success' => false,
                'message' => __('Template not found', 'intersoccer-referral'),
            ];
        }

        if (!is_email($admin_email)) {
            return [
                'success' => false,
                'message' => __('Invalid email address', 'intersoccer-referral'),
            ];
        }

        if ($sample_coach_id) {
            $values = $this->get_coach_values($sample_coach_id, $campaign_data);
        } else {
            $values = $this->get_sample_values();
            if (!empty($campaign_data['campaign_name'])) {
                $values['campaign_name'] = $campaign_data['campaign_name'];
            }
            if (!empty($campaign_data['campaign_end_date'])) {
                $values['campaign_end_date'] = $this->format_coach_friendly_date($campaign_data['campaign_end_date']);
            }
            if (!empty($campaign_data['cta_url'])) {
                $values['cta_url'] = $campaign_data['cta_url'];
            }
        }

        $validation = $this->validate_required_fields($values);

        if ($validation !== true) {
            return [
                'success' => false,
                'message' => sprintf(
                    __('Cannot send: missing required fields (%s)', 'intersoccer-referral'),
                    implode(', ', $validation)
                ),
                'missing_fields' => $validation,
            ];
        }

        $subject = $this->resolve_merge_fields($template['subject'], $values);
        $body = $this->resolve_merge_fields($template['body'], $values);

        $unresolved = $this->find_unresolved_placeholders($subject . $body);

        if (!empty($unresolved)) {
            return [
                'success' => false,
                'message' => sprintf(
                    __('Cannot send: unresolved placeholders (%s)', 'intersoccer-referral'),
                    implode(', ', $unresolved)
                ),
                'unresolved_fields' => $unresolved,
            ];
        }

        $subject = '[TEST] ' . $subject;

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $body_html = $this->convert_to_html($body);

        $sent = wp_mail($admin_email, $subject, $body_html, $headers);

        if (!$sent) {
            return [
                'success' => false,
                'message' => __('Failed to send test email', 'intersoccer-referral'),
            ];
        }

        return [
            'success' => true,
            'message' => sprintf(
                __('Test email sent to %s', 'intersoccer-referral'),
                $admin_email
            ),
        ];
    }

    /**
     * Convert plain text body to simple HTML
     */
    private function convert_to_html($body) {
        $body = nl2br(esc_html($body));
        $body = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $body);
        $body = preg_replace('/(https?:\/\/[^\s<]+)/', '<a href="$1">$1</a>', $body);

        return sprintf(
            '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
a { color: #2563eb; }
strong { color: #1e40af; }
</style>
</head>
<body>%s</body>
</html>',
            $body
        );
    }

    /**
     * Generate preview HTML
     * AC §3: Preview shows sample resolved values for all six fields
     */
    public function get_preview($template_id, $sample_coach_id = null, $campaign_data = []) {
        $template = $this->get_template($template_id);

        if (!$template) {
            return [
                'success' => false,
                'message' => __('Template not found', 'intersoccer-referral'),
            ];
        }

        if ($sample_coach_id) {
            $values = $this->get_coach_values($sample_coach_id, $campaign_data);
        } else {
            $values = $this->get_sample_values();
            if (!empty($campaign_data['campaign_name'])) {
                $values['campaign_name'] = $campaign_data['campaign_name'];
            }
            if (!empty($campaign_data['campaign_end_date'])) {
                $values['campaign_end_date'] = $this->format_coach_friendly_date($campaign_data['campaign_end_date']);
            }
            if (!empty($campaign_data['cta_url'])) {
                $values['cta_url'] = $campaign_data['cta_url'];
            }
        }

        $subject = $this->resolve_merge_fields($template['subject'], $values);
        $body = $this->resolve_merge_fields($template['body'], $values);

        return [
            'success' => true,
            'subject' => $subject,
            'body' => $body,
            'body_html' => $this->convert_to_html($body),
            'values' => $values,
        ];
    }

    /**
     * AJAX: Save template (create or update)
     */
    public function ajax_save_template() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'intersoccer-referral')]);
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $data = [
            'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
            'subject' => isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '',
            'body' => isset($_POST['body']) ? wp_kses_post($_POST['body']) : '',
            'is_default' => !empty($_POST['is_default']),
        ];

        if (empty($data['name'])) {
            wp_send_json_error(['message' => __('Template name is required', 'intersoccer-referral')]);
        }

        if (empty($data['subject'])) {
            wp_send_json_error(['message' => __('Subject is required', 'intersoccer-referral')]);
        }

        if (empty($data['body'])) {
            wp_send_json_error(['message' => __('Body is required', 'intersoccer-referral')]);
        }

        if ($id > 0) {
            $result = $this->update_template($id, $data);
            $message = __('Template updated successfully', 'intersoccer-referral');
        } else {
            $result = $this->create_template($data);
            $id = is_numeric($result) ? $result : 0;
            $message = __('Template created successfully', 'intersoccer-referral');
        }

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => $message,
            'template_id' => $id,
        ]);
    }

    /**
     * AJAX: Delete template
     */
    public function ajax_delete_template() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'intersoccer-referral')]);
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;

        if (!$id) {
            wp_send_json_error(['message' => __('Invalid template ID', 'intersoccer-referral')]);
        }

        $result = $this->delete_template($id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => __('Template deleted', 'intersoccer-referral')]);
    }

    /**
     * AJAX: Duplicate template
     */
    public function ajax_duplicate_template() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'intersoccer-referral')]);
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;

        if (!$id) {
            wp_send_json_error(['message' => __('Invalid template ID', 'intersoccer-referral')]);
        }

        $new_id = $this->duplicate_template($id);

        if (is_wp_error($new_id)) {
            wp_send_json_error(['message' => $new_id->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Template duplicated', 'intersoccer-referral'),
            'template_id' => $new_id,
        ]);
    }

    /**
     * AJAX: Archive template
     */
    public function ajax_archive_template() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'intersoccer-referral')]);
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $restore = !empty($_POST['restore']);

        if (!$id) {
            wp_send_json_error(['message' => __('Invalid template ID', 'intersoccer-referral')]);
        }

        if ($restore) {
            $result = $this->restore_template($id);
            $message = __('Template restored', 'intersoccer-referral');
        } else {
            $result = $this->archive_template($id);
            $message = __('Template archived', 'intersoccer-referral');
        }

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success(['message' => $message]);
    }

    /**
     * AJAX: Preview template
     */
    public function ajax_preview_template() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'intersoccer-referral')]);
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $coach_id = isset($_POST['coach_id']) ? absint($_POST['coach_id']) : 0;
        $campaign_data = [
            'campaign_name' => isset($_POST['campaign_name']) ? sanitize_text_field($_POST['campaign_name']) : '',
            'campaign_end_date' => isset($_POST['campaign_end_date']) ? sanitize_text_field($_POST['campaign_end_date']) : '',
            'cta_url' => isset($_POST['cta_url']) ? esc_url_raw($_POST['cta_url']) : '',
        ];

        if ($id > 0) {
            $result = $this->get_preview($id, $coach_id ?: null, $campaign_data);
        } else {
            $subject = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
            $body = isset($_POST['body']) ? wp_kses_post($_POST['body']) : '';

            if ($coach_id) {
                $values = $this->get_coach_values($coach_id, $campaign_data);
            } else {
                $values = $this->get_sample_values();
                if (!empty($campaign_data['campaign_name'])) {
                    $values['campaign_name'] = $campaign_data['campaign_name'];
                }
                if (!empty($campaign_data['campaign_end_date'])) {
                    $values['campaign_end_date'] = $this->format_coach_friendly_date($campaign_data['campaign_end_date']);
                }
                if (!empty($campaign_data['cta_url'])) {
                    $values['cta_url'] = $campaign_data['cta_url'];
                }
            }

            $resolved_subject = $this->resolve_merge_fields($subject, $values);
            $resolved_body = $this->resolve_merge_fields($body, $values);

            $result = [
                'success' => true,
                'subject' => $resolved_subject,
                'body' => $resolved_body,
                'body_html' => $this->convert_to_html($resolved_body),
                'values' => $values,
            ];
        }

        if (isset($result['success']) && !$result['success']) {
            wp_send_json_error($result);
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX: Test send template
     */
    public function ajax_test_send_template() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'intersoccer-referral')]);
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $coach_id = isset($_POST['coach_id']) ? absint($_POST['coach_id']) : 0;
        $campaign_data = [
            'campaign_name' => isset($_POST['campaign_name']) ? sanitize_text_field($_POST['campaign_name']) : '',
            'campaign_end_date' => isset($_POST['campaign_end_date']) ? sanitize_text_field($_POST['campaign_end_date']) : '',
            'cta_url' => isset($_POST['cta_url']) ? esc_url_raw($_POST['cta_url']) : '',
        ];

        if (!$id) {
            wp_send_json_error(['message' => __('Please save the template before sending a test', 'intersoccer-referral')]);
        }

        if (empty($email)) {
            $email = get_option('admin_email');
        }

        $result = $this->test_send($id, $email, $coach_id ?: null, $campaign_data);

        if (!$result['success']) {
            wp_send_json_error($result);
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX: Get template
     */
    public function ajax_get_template() {
        check_ajax_referer('intersoccer_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'intersoccer-referral')]);
        }

        $id = isset($_POST['id']) ? absint($_POST['id']) : 0;

        if (!$id) {
            wp_send_json_error(['message' => __('Invalid template ID', 'intersoccer-referral')]);
        }

        $template = $this->get_template($id);

        if (!$template) {
            wp_send_json_error(['message' => __('Template not found', 'intersoccer-referral')]);
        }

        wp_send_json_success(['template' => $template]);
    }
}
