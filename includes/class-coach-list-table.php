<?php
// includes/class-coach-list-table.php
class InterSoccer_Coach_List_Table extends WP_List_Table {
    public function __construct() {
        parent::__construct([
            'singular' => 'coach',
            'plural' => 'coaches',
            'ajax' => true
        ]);
    }

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'coach_name' => __('Coach Name', 'intersoccer-referral'),
            'email' => __('Email', 'intersoccer-referral'),
            'referrals' => __('Referrals', 'intersoccer-referral'),
            'credits' => __('Credits (CHF)', 'intersoccer-referral'),
            'tier' => __('Tier', 'intersoccer-referral'),
            'venues' => __('Venues', 'intersoccer-referral'),
            'actions' => __('Actions', 'intersoccer-referral')
        ];
    }

    public function get_sortable_columns() {
        return [
            'coach_name' => ['display_name', false],
            'email' => ['user_email', false],
            'referrals' => ['referral_count', false],
            'credits' => ['credits', false],
            'tier' => ['tier', false]
        ];
    }

    public function get_bulk_actions() {
        return [
            'assign_venue' => __('Assign Venue', 'intersoccer-referral'),
            'send_message' => __('Send Message', 'intersoccer-referral'),
            'deactivate' => __('Deactivate', 'intersoccer-referral')
        ];
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'coach_name':
                return esc_html($item->display_name);
            case 'email':
                return esc_html($item->user_email);
            case 'referrals':
                return esc_html($item->referral_count);
            case 'credits':
                return number_format($item->credits, 0);
            case 'tier':
                return esc_html(intersoccer_get_coach_tier($item->ID));
            case 'venues':
                $venues = get_user_meta($item->ID, 'intersoccer_venues', true) ?: [];
                $venue_names = [];
                foreach ($venues as $venue_id) {
                    $venue = get_post($venue_id);
                    if ($venue) $venue_names[] = esc_html($venue->post_title);
                }
                return implode(', ', $venue_names);
            case 'actions':
                return sprintf(
                    '<a href="%s" class="button">Edit</a> <a href="#" class="button send-message" data-coach-id="%d">Message</a> <a href="#" class="button deactivate-coach" data-coach-id="%d">Deactivate</a>',
                    admin_url('user-edit.php?user_id=' . $item->ID),
                    $item->ID,
                    $item->ID
                );
            default:
                return '';
        }
    }

    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="coach_ids[]" value="%s" />', $item->ID);
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'intersoccer_referrals';
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $search = !empty($_REQUEST['s']) ? sanitize_text_field($_REQUEST['s']) : '';
        $tier = !empty($_REQUEST['tier']) ? sanitize_text_field($_REQUEST['tier']) : '';
        $venue = !empty($_REQUEST['venue']) ? absint($_REQUEST['venue']) : 0;

        $where = "WHERE EXISTS (SELECT 1 FROM {$wpdb->usermeta} WHERE user_id = u.ID AND meta_key = 'wp_capabilities' AND meta_value LIKE '%coach%')";
        if ($search) {
            $where .= $wpdb->prepare(" AND (u.display_name LIKE %s OR u.user_email LIKE %s)", "%$search%", "%$search%");
        }
        if ($tier) {
            $thresholds = [
                'Bronze' => 0,
                'Silver' => get_option('intersoccer_tier_silver', 5),
                'Gold' => get_option('intersoccer_tier_gold', 10),
                'Platinum' => get_option('intersoccer_tier_platinum', 20)
            ];
            $min = $thresholds[$tier];
            $max = $tier === 'Platinum' ? 9999 : ($thresholds[array_keys($thresholds)[array_search($tier, array_keys($thresholds)) + 1]] ?? 9999);
            $where .= $wpdb->prepare(" AND r.referral_count >= %d AND r.referral_count < %d", $min, $max);
        }
        if ($venue) {
            $where .= $wpdb->prepare(" AND EXISTS (SELECT 1 FROM {$wpdb->usermeta} um WHERE um.user_id = u.ID AND um.meta_key = 'intersoccer_venues' AND um.meta_value LIKE %s)", "%$venue%");
        }

        $query = "
            SELECT 
                u.*,
                COALESCE(r.referral_count, 0) as referral_count,
                COALESCE(um.meta_value, 0) as credits
            FROM {$wpdb->users} u
            LEFT JOIN (
                SELECT coach_id, COUNT(*) as referral_count
                FROM $table_name 
                GROUP BY coach_id
            ) r ON u.ID = r.coach_id
            LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = 'intersoccer_credits'
            $where
        ";

        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM ($query) as total");
        $this->set_pagination_args(['total_items' => $total_items, 'per_page' => $per_page]);

        $orderby = !empty($_REQUEST['orderby']) ? sanitize_text_field($_REQUEST['orderby']) : 'display_name';
        $order = !empty($_REQUEST['order']) ? sanitize_text_field($_REQUEST['order']) : 'asc';
        $query .= " ORDER BY u.$orderby $order LIMIT " . ($current_page - 1) * $per_page . ", $per_page";

        $this->items = $wpdb->get_results($query);
        error_log('Preparing coach list table, orderby: ' . $orderby . ', query: ' . $wpdb->last_query);
    }
}
?>