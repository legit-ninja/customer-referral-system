<?php

use PHPUnit\Framework\TestCase;

class CoachEventsManagerTest extends TestCase {

    private $backup_user_meta;
    private $backup_get_posts_results;
    private $backup_wpdb_get_row_results;
    private $backup_wpdb_get_results;
    private $backup_wpdb_last_insert;
    private $backup_wpdb_last_update;
    private $backup_wpdb_last_delete;
    private $backup_wc_products;
    private $backup_wc_product_lookup;

    protected function setUp(): void {
        parent::setUp();

        require_once __DIR__ . '/../includes/class-coach-events-manager.php';

        global $mock_user_meta, $mock_get_posts_results, $mock_wpdb_get_row_results, $mock_wpdb_get_results,
               $mock_wpdb_last_insert, $mock_wpdb_last_update, $mock_wpdb_last_delete,
               $mock_wc_products, $mock_wc_product_lookup;

        $this->backup_user_meta = $mock_user_meta;
        $this->backup_get_posts_results = $mock_get_posts_results;
        $this->backup_wpdb_get_row_results = $mock_wpdb_get_row_results;
        $this->backup_wpdb_get_results = $mock_wpdb_get_results;
        $this->backup_wpdb_last_insert = $mock_wpdb_last_insert;
        $this->backup_wpdb_last_update = $mock_wpdb_last_update;
        $this->backup_wpdb_last_delete = $mock_wpdb_last_delete;
        $this->backup_wc_products = $mock_wc_products;
        $this->backup_wc_product_lookup = $mock_wc_product_lookup;

        $mock_user_meta = [];
        $mock_get_posts_results = [];
        $mock_wpdb_get_row_results = [];
        $mock_wpdb_get_results = [];
        $mock_wpdb_last_insert = null;
        $mock_wpdb_last_update = null;
        $mock_wpdb_last_delete = null;
        $mock_wc_products = [];
        $mock_wc_product_lookup = [];
    }

    protected function tearDown(): void {
        parent::tearDown();

        global $mock_user_meta, $mock_get_posts_results, $mock_wpdb_get_row_results, $mock_wpdb_get_results,
               $mock_wpdb_last_insert, $mock_wpdb_last_update, $mock_wpdb_last_delete,
               $mock_wc_products, $mock_wc_product_lookup;

        $mock_user_meta = $this->backup_user_meta;
        $mock_get_posts_results = $this->backup_get_posts_results;
        $mock_wpdb_get_row_results = $this->backup_wpdb_get_row_results;
        $mock_wpdb_get_results = $this->backup_wpdb_get_results;
        $mock_wpdb_last_insert = $this->backup_wpdb_last_insert;
        $mock_wpdb_last_update = $this->backup_wpdb_last_update;
        $mock_wpdb_last_delete = $this->backup_wpdb_last_delete;
        $mock_wc_products = $this->backup_wc_products;
        $mock_wc_product_lookup = $this->backup_wc_product_lookup;
    }

    public function testAddEventCreatesNewAssignment(): void {
        global $mock_wpdb_get_row_results, $mock_wpdb_last_insert;

        $mock_wpdb_get_row_results = [
            'intersoccer_coach_events WHERE coach_id' => null,
        ];
        $mock_wpdb_last_insert = null;

        $result = InterSoccer_Coach_Events_Manager::add_event(10, 25, [
            'status' => 'pending',
            'notes' => 'Initial association',
        ]);

        $this->assertIsInt($result);
        $this->assertNotEmpty($mock_wpdb_last_insert, 'Insert should record last insert data');
        $this->assertSame('wp_intersoccer_coach_events', $mock_wpdb_last_insert['table']);
        $this->assertSame(25, $mock_wpdb_last_insert['data']['event_id']);
        $this->assertSame('pending', $mock_wpdb_last_insert['data']['status']);
    }

    public function testAddEventUpdatesExistingAssignment(): void {
        global $mock_wpdb_get_row_results, $mock_wpdb_last_update;

        $mock_wpdb_get_row_results = [
            'intersoccer_coach_events WHERE coach_id' => (object) [
                'id' => 77,
                'coach_id' => 5,
                'event_id' => 33,
                'event_type' => 'product',
                'status' => 'active',
                'source' => 'coach',
                'assigned_at' => '2025-01-01 00:00:00',
            ],
        ];
        $mock_wpdb_last_update = null;

        $result = InterSoccer_Coach_Events_Manager::add_event(5, 33, [
            'status' => 'inactive',
            'source' => 'admin',
            'notes' => 'Admin override',
        ]);

        $this->assertSame(77, $result);
        $this->assertNotEmpty($mock_wpdb_last_update, 'Update should record last update data');
        $this->assertSame('wp_intersoccer_coach_events', $mock_wpdb_last_update['table']);
        $this->assertSame('inactive', $mock_wpdb_last_update['data']['status']);
        $this->assertSame('admin', $mock_wpdb_last_update['data']['source']);
    }

    public function testBuildEventShareLink(): void {
        update_user_meta(12, 'referral_code', 'COACH12ABC');

        $assignment = (object) [
            'id' => 99,
            'coach_id' => 12,
            'event_id' => 345,
            'event_type' => 'product',
            'event_permalink' => home_url('/events/summer-camp'),
        ];

        $link = InterSoccer_Coach_Events_Manager::build_event_share_link($assignment);

        $this->assertNotEmpty($link);
        $this->assertStringContainsString('ref=COACH12ABC', $link);
        $this->assertStringContainsString('coach_event=99', $link);
        $this->assertStringContainsString('event=345', $link);
    }

    public function testGetCoachEventsReturnsEnrichedAssignments(): void {
        global $mock_wpdb_get_results;

        $mock_wpdb_get_results = [
            'FROM wp_intersoccer_coach_events' => [
                (object) [
                    'id' => 12,
                    'coach_id' => 3,
                    'event_id' => 101,
                    'event_type' => 'product',
                    'status' => 'active',
                    'source' => 'coach',
                    'assigned_at' => '2025-03-01 08:00:00',
                    'notes' => '',
                    'coach_name' => 'Coach Example',
                    'user_email' => 'coach@example.com',
                    'post_title' => 'Existing Title',
                    'post_type' => 'product',
                    'post_status' => 'publish',
                ],
            ],
        ];

        update_user_meta(3, 'referral_code', 'COACH3XYZ');

        $events = InterSoccer_Coach_Events_Manager::get_coach_events(3);

        $this->assertCount(1, $events);
        $event = $events[0];

        $this->assertEquals('Test Event 101', $event->event_title, 'Title should be sourced from get_post mock');
        $this->assertEquals('publish', $event->event_status);
        $this->assertNotEmpty($event->event_share_link);
        $this->assertStringContainsString('coach_event=12', $event->event_share_link);
    }

    public function testSearchEventsReturnsStructuredResults(): void {
        global $mock_wc_products, $mock_wc_product_lookup;

        $simple = new WC_Product(201, 'Camp Alpha');
        $variable = new WC_Product_Variable(202, 'Camp Beta', 'publish', [
            'children' => [301],
        ]);
        $variation = new WC_Product_Variation(301, 'Camp Beta Sunday', 'publish', [
            'parent' => 202,
            'attributes' => [
                'pa_course-day' => 'sunday',
                'pa_age-group' => '5-12',
            ],
        ]);

        $mock_wc_products = [$simple, $variable];
        $mock_wc_product_lookup = [
            201 => $simple,
            202 => $variable,
            301 => $variation,
        ];

        $results = InterSoccer_Coach_Events_Manager::search_events('Camp');

        $this->assertCount(2, $results);
        $this->assertSame(201, $results[0]['id']);
        $this->assertSame('product', $results[0]['type']);
        $this->assertSame('Product', $results[0]['type_label']);
        $this->assertStringContainsString('?p=201', $results[0]['permalink']);

        $this->assertSame(301, $results[1]['id']);
        $this->assertSame('product_variation', $results[1]['type']);
        $this->assertSame('Variation', $results[1]['type_label']);
        $this->assertStringContainsString('?p=301', $results[1]['permalink']);
    }

    public function testDeleteAssignmentRemovesRow(): void {
        global $mock_wpdb_last_delete;

        $mock_wpdb_last_delete = null;

        $result = InterSoccer_Coach_Events_Manager::delete_assignment(55);

        $this->assertTrue($result);
        $this->assertNotEmpty($mock_wpdb_last_delete);
        $this->assertSame('wp_intersoccer_coach_events', $mock_wpdb_last_delete['table']);
        $this->assertSame(['id' => 55], $mock_wpdb_last_delete['where']);
    }
}


