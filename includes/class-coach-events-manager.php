<?php
/**
 * Central data access helper for coach event participation records.
 */

if (!defined('ABSPATH')) {
    exit;
}

class InterSoccer_Coach_Events_Manager {

    /**
     * Get table name with WordPress prefix.
     */
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'intersoccer_coach_events';
    }

    /**
     * Normalize status values.
     */
    private static function normalize_status($status) {
        $allowed = ['active', 'inactive', 'pending'];
        $status = strtolower($status ?: 'active');
        return in_array($status, $allowed, true) ? $status : 'active';
    }

    /**
     * Normalize source values.
     */
    private static function normalize_source($source) {
        $allowed = ['coach', 'admin'];
        $source = strtolower($source ?: 'coach');
        return in_array($source, $allowed, true) ? $source : 'coach';
    }

    /**
     * Normalize event type values.
     */
    private static function normalize_event_type($event_type) {
        $default = 'product';
        if (empty($event_type)) {
            return $default;
        }

        $event_type = sanitize_key($event_type);
        $allowed = apply_filters('intersoccer_coach_events_allowed_types', ['product', 'product_variation', 'tribe_events', 'camp', 'course']);
        return in_array($event_type, $allowed, true) ? $event_type : $default;
    }

    /**
     * Create or update an event association.
     */
    public static function add_event($coach_id, $event_id, $args = []) {
        global $wpdb;

        $defaults = [
            'event_type' => 'product',
            'status' => 'active',
            'source' => 'coach',
            'assigned_by' => null,
            'notes' => '',
        ];

        $args = wp_parse_args($args, $defaults);

        $coach_id = intval($coach_id);
        $event_id = intval($event_id);

        if (!$coach_id || !$event_id) {
            return new WP_Error('invalid_parameters', __('Missing coach or event identifier', 'intersoccer-referral'));
        }

        $event_type = self::normalize_event_type($args['event_type']);
        $status = self::normalize_status($args['status']);
        $source = self::normalize_source($args['source']);
        $assigned_by = $args['assigned_by'] ? intval($args['assigned_by']) : null;
        $notes = sanitize_text_field($args['notes']);

        // Avoid duplicates by checking existing record.
        $existing = self::get_assignment_by_keys($coach_id, $event_id, $event_type);
        if ($existing) {
            // Update the existing record instead of inserting a duplicate.
            $update = $wpdb->update(
                self::table_name(),
                [
                    'status' => $status,
                    'source' => $source,
                    'assigned_by' => $assigned_by,
                    'notes' => $notes,
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => intval($existing->id)],
                ['%s', '%s', '%d', '%s', '%s'],
                ['%d']
            );

            if ($update === false) {
                return new WP_Error('db_error', $wpdb->last_error);
            }

            return intval($existing->id);
        }

        $data = [
            'coach_id' => $coach_id,
            'event_id' => $event_id,
            'event_type' => $event_type,
            'status' => $status,
            'source' => $source,
            'notes' => $notes,
            'assigned_at' => current_time('mysql'),
        ];

        $formats = ['%d', '%d', '%s', '%s', '%s', '%s', '%s'];

        if ($assigned_by) {
            $data['assigned_by'] = $assigned_by;
            $formats[] = '%d';
        }

        $result = $wpdb->insert(self::table_name(), $data, $formats);

        if ($result === false) {
            return new WP_Error('db_error', $wpdb->last_error);
        }

        return intval($wpdb->insert_id);
    }

    /**
     * Update status of an assignment.
     */
    public static function update_status($assignment_id, $status, $updated_by = null) {
        global $wpdb;

        $status = self::normalize_status($status);
        $assignment_id = intval($assignment_id);

        if (!$assignment_id) {
            return new WP_Error('invalid_parameters', __('Missing assignment identifier', 'intersoccer-referral'));
        }

        $data = [
            'status' => $status,
            'updated_at' => current_time('mysql'),
        ];
        $formats = ['%s', '%s'];

        if ($updated_by) {
            $data['assigned_by'] = intval($updated_by);
            $formats[] = '%d';
        }

        $result = $wpdb->update(
            self::table_name(),
            $data,
            ['id' => $assignment_id],
            $formats,
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', $wpdb->last_error);
        }

        return true;
    }

    /**
     * Delete an assignment permanently.
     */
    public static function delete_assignment($assignment_id) {
        global $wpdb;

        $assignment_id = intval($assignment_id);
        if (!$assignment_id) {
            return new WP_Error('invalid_parameters', __('Missing assignment identifier', 'intersoccer-referral'));
        }

        $result = $wpdb->delete(self::table_name(), ['id' => $assignment_id], ['%d']);

        if ($result === false) {
            return new WP_Error('db_error', $wpdb->last_error);
        }

        return true;
    }

    /**
     * Fetch a single assignment by composite key.
     */
    public static function get_assignment_by_keys($coach_id, $event_id, $event_type = 'product') {
        global $wpdb;

        $coach_id = intval($coach_id);
        $event_id = intval($event_id);
        $event_type = self::normalize_event_type($event_type);

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table_name() . " WHERE coach_id = %d AND event_id = %d AND event_type = %s",
            $coach_id,
            $event_id,
            $event_type
        ));
    }

    /**
     * Fetch a single assignment by primary key.
     */
    public static function get_assignment($assignment_id) {
        global $wpdb;

        $assignment_id = intval($assignment_id);
        if (!$assignment_id) {
            return null;
        }

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table_name() . " WHERE id = %d",
            $assignment_id
        ));

        if (!$row) {
            return null;
        }

        return self::enrich_assignment($row);
    }

    /**
     * Retrieve assignments for a specific coach.
     */
    public static function get_coach_events($coach_id, $args = []) {
        $defaults = [
            'status' => null,
            'include_meta' => true,
        ];

        $args = wp_parse_args($args, $defaults);
        $assignments = self::get_assignments([
            'coach_id' => intval($coach_id),
            'status' => $args['status'],
            'include_meta' => $args['include_meta'],
        ]);

        return $assignments;
    }

    /**
     * Fetch assignments optionally filtered by coach or status.
     */
    public static function get_assignments($args = []) {
        global $wpdb;

        $defaults = [
            'coach_id' => null,
            'status' => null,
            'limit' => 200,
            'include_meta' => true,
        ];

        $args = wp_parse_args($args, $defaults);

        $where = [];
        $params = [];

        if (!empty($args['coach_id'])) {
            $where[] = 'ce.coach_id = %d';
            $params[] = intval($args['coach_id']);
        }

        if (!empty($args['status'])) {
            if (is_array($args['status'])) {
                $statuses = array_map('self::normalize_status', $args['status']);
                $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
                $where[] = "ce.status IN ($placeholders)";
                $params = array_merge($params, $statuses);
            } else {
                $where[] = 'ce.status = %s';
                $params[] = self::normalize_status($args['status']);
            }
        }

        $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $limit = $args['limit'] ? intval($args['limit']) : 200;

        $sql = "SELECT ce.*, u.display_name AS coach_name, u.user_email, p.post_title, p.post_type, p.post_status
                FROM " . self::table_name() . " ce
                LEFT JOIN {$wpdb->users} u ON ce.coach_id = u.ID
                LEFT JOIN {$wpdb->posts} p ON ce.event_id = p.ID
                $where_sql
                ORDER BY ce.assigned_at DESC
                LIMIT %d";

        $params[] = $limit;
        $prepared = $wpdb->prepare($sql, $params);
        $results = $wpdb->get_results($prepared);

        if (!$results) {
            return [];
        }

        if (empty($args['include_meta'])) {
            return $results;
        }

        return array_map([__CLASS__, 'enrich_assignment'], $results);
    }

    /**
     * Attach post meta (title, permalink) to assignment rows.
     */
    private static function enrich_assignment($assignment) {
        $event_post = $assignment->event_id ? get_post($assignment->event_id) : null;

        $assignment->event_title = $event_post ? get_the_title($event_post) : __('Unknown Event', 'intersoccer-referral');
        $assignment->event_status = $event_post ? $event_post->post_status : 'missing';
        $assignment->event_permalink = $event_post ? get_permalink($event_post) : '';
        $assignment->event_permalink = self::maybe_expand_variation_link($assignment);
        $assignment->event_share_link = self::build_event_share_link($assignment);

        return $assignment;
    }

    /**
     * Build a shareable referral link for a specific event assignment.
     *
     * @param object|int $assignment Either the assignment object or its ID
     * @return string
     */
    public static function build_event_share_link($assignment) {
        if (is_numeric($assignment)) {
            $assignment = self::get_assignment(intval($assignment));
        }

        if (!$assignment || empty($assignment->event_id) || empty($assignment->coach_id)) {
            return '';
        }

        $event_permalink = !empty($assignment->event_permalink) ? $assignment->event_permalink : get_permalink($assignment->event_id);
        if (!$event_permalink) {
            return '';
        }

        if (!class_exists('InterSoccer_Referral_Handler')) {
            return '';
        }

        $ref_code = InterSoccer_Referral_Handler::get_coach_referral_code($assignment->coach_id);
        if (!$ref_code) {
            return '';
        }

        $assignment_id = intval($assignment->id ?? 0);

        $args = [
            'ref' => $ref_code,
        ];

        if ($assignment_id > 0) {
            $args['coach_event'] = $assignment_id;
        }

        // Preserve event parameter for legacy tracking if needed
        $args['event'] = intval($assignment->event_id);

        return add_query_arg($args, $event_permalink);
    }

    /**
     * If the assignment references a variable product variation, append its attributes to the URL.
     */
    private static function maybe_expand_variation_link($assignment) {
        if (!function_exists('wc_get_product')) {
            return $assignment->event_permalink;
        }

        $event_post = $assignment->event_id ? get_post($assignment->event_id) : null;
        if (!$event_post) {
            return $assignment->event_permalink;
        }

        $product = wc_get_product($assignment->event_id);
        if (!$product) {
            return $assignment->event_permalink;
        }

        if (!$product->is_type('variation')) {
            return $assignment->event_permalink;
        }

        $parent = wc_get_product($product->get_parent_id());
        if (!$parent) {
            return $assignment->event_permalink;
        }

        $attributes = $product->get_attributes();

        if (function_exists('wc_get_formatted_variation') && $parent) {
            $assignment->event_title = $parent->get_name() . ' — ' . wc_get_formatted_variation($product, true, false, true);
        } elseif ($parent) {
            $assignment->event_title = $parent->get_name();
        }

        if (empty($attributes)) {
            return $product->get_permalink();
        }

        $permalink = $parent->get_permalink();
        $query_args = [];

        foreach ($attributes as $attribute_name => $value) {
            if (empty($value)) {
                continue;
            }

            // Normalize attribute key (e.g., pa_course-times -> attribute_pa_course-times)
            $normalized = 'attribute_' . $attribute_name;
            $query_args[$normalized] = $value;
        }

        if (!empty($query_args)) {
            return add_query_arg($query_args, $permalink);
        }

        return $assignment->event_permalink;
    }

    /**
     * Perform a lightweight search of events/products by term.
     */
    public static function search_events($term, $limit = 20) {
        $term = sanitize_text_field($term);
        if (strlen($term) < 2) {
            return [];
        }

        if (!function_exists('wc_get_products')) {
            return [];
        }

        $product_args = [
            'status' => ['publish', 'future'],
            'limit' => $limit,
            'search' => $term,
            'type' => ['simple', 'variable'],
        ];

        $products = wc_get_products(apply_filters('intersoccer_coach_events_search_args', $product_args, $term));
        if (empty($products)) {
            return [];
        }

        $results = [];

        foreach ($products as $product) {
            if (!is_a($product, 'WC_Product')) {
                continue;
            }

            if ($product->is_type('simple')) {
                $results[] = [
                    'id' => $product->get_id(),
                    'title' => $product->get_name(),
                    'type' => 'product',
                    'type_label' => __('Product', 'intersoccer-referral'),
                    'permalink' => $product->get_permalink(),
                    'status' => $product->get_status(),
                ];
                continue;
            }

            if ($product->is_type('variable')) {
                $variations = $product->get_visible_children();
                foreach ($variations as $variation_id) {
                    $variation = wc_get_product($variation_id);
                    if (!$variation) {
                        continue;
                    }

                    $formatted = function_exists('wc_get_formatted_variation')
                        ? wc_get_formatted_variation($variation, true, false, false)
                        : implode(', ', $variation->get_attributes());
                    $results[] = [
                        'id' => $variation->get_id(),
                        'title' => sprintf('%s — %s', $product->get_name(), $formatted ?: __('Variation', 'intersoccer-referral')),
                        'type' => 'product_variation',
                        'type_label' => __('Variation', 'intersoccer-referral'),
                        'permalink' => $variation->get_permalink(),
                        'status' => $variation->get_status(),
                    ];

                    if (count($results) >= $limit) {
                        break 2;
                    }
                }
            }
        }

        return $results;
    }
}


