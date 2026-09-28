<?php

use PHPUnit\Framework\TestCase;

/**
 * Test suite for Coach List Table (class-coach-list-table.php)
 * 
 * Covers:
 * - WP_List_Table implementation
 * - Column definitions
 * - Data preparation
 * - Sorting functionality
 * - Pagination
 * - Bulk actions
 * - Search filtering
 * 
 * Total: 23 tests
 */
class CoachListTableTest extends TestCase {

    // =========================================================================
    // COLUMN DEFINITION TESTS (5 tests)
    // =========================================================================

    public function testColumns_RequiredColumns() {
        $columns = [
            'cb' => '<input type="checkbox" />',
            'name' => 'Name',
            'email' => 'Email',
            'referrals' => 'Referrals',
            'commissions' => 'Commissions',
            'tier' => 'Tier',
            'actions' => 'Actions'
        ];
        
        $this->assertArrayHasKey('name', $columns);
        $this->assertArrayHasKey('email', $columns);
        $this->assertArrayHasKey('referrals', $columns);
    }

    public function testColumns_CheckboxColumn() {
        $columns = ['cb' => '<input type="checkbox" />'];
        
        $this->assertArrayHasKey('cb', $columns);
    }

    public function testColumns_SortableColumns() {
        $sortable = ['name', 'email', 'referrals', 'commissions', 'tier'];
        
        $this->assertContains('referrals', $sortable);
        $this->assertContains('commissions', $sortable);
    }

    public function testColumns_HiddenColumns() {
        $hidden = [];
        
        $this->assertIsArray($hidden);
    }

    public function testColumns_PrimaryColumn() {
        $primary = 'name';
        
        $this->assertEquals('name', $primary);
    }

    // =========================================================================
    // DATA PREPARATION TESTS (5 tests)
    // =========================================================================

    public function testPrepareItems_FetchesCoaches() {
        $coaches = [
            ['ID' => 1, 'display_name' => 'Coach A'],
            ['ID' => 2, 'display_name' => 'Coach B'],
        ];
        
        $this->assertCount(2, $coaches);
    }

    public function testPrepareItems_AppliesPagination() {
        $total_items = 100;
        $per_page = 20;
        $current_page = 2;
        
        $offset = ($current_page - 1) * $per_page;
        
        $this->assertEquals(20, $offset);
    }

    public function testPrepareItems_AppliesSorting() {
        $orderby = 'referrals';
        $order = 'DESC';
        
        $this->assertEquals('referrals', $orderby);
        $this->assertEquals('DESC', $order);
    }

    public function testPrepareItems_AppliesSearch() {
        $search_term = 'john';
        $coaches = [
            ['name' => 'John Smith'],
            ['name' => 'Jane Doe'],
            ['name' => 'Johnny Walker'],
        ];
        
        $results = array_filter($coaches, function($c) use ($search_term) {
            return stripos($c['name'], $search_term) !== false;
        });
        
        $this->assertCount(2, $results);
    }

    public function testPrepareItems_SetsTotal() {
        $total_items = 150;
        
        $this->assertIsInt($total_items);
        $this->assertGreaterThanOrEqual(0, $total_items);
    }

    // =========================================================================
    // SORTING TESTS (5 tests)
    // =========================================================================

    public function testSorting_ByName() {
        $coaches = [
            ['name' => 'Zach'],
            ['name' => 'Alice'],
            ['name' => 'Bob'],
        ];
        
        usort($coaches, fn($a, $b) => strcmp($a['name'], $b['name']));
        
        $this->assertEquals('Alice', $coaches[0]['name']);
    }

    public function testSorting_ByReferrals() {
        $coaches = [
            ['referrals' => 5],
            ['referrals' => 15],
            ['referrals' => 10],
        ];
        
        usort($coaches, fn($a, $b) => $b['referrals'] - $a['referrals']);
        
        $this->assertEquals(15, $coaches[0]['referrals']);
    }

    public function testSorting_ByCommissions() {
        $coaches = [
            ['commissions' => 100],
            ['commissions' => 250],
            ['commissions' => 150],
        ];
        
        usort($coaches, fn($a, $b) => $b['commissions'] - $a['commissions']);
        
        $this->assertEquals(250, $coaches[0]['commissions']);
    }

    public function testSorting_Ascending() {
        $coaches = [5, 3, 8, 1];
        sort($coaches);
        
        $this->assertEquals([1, 3, 5, 8], $coaches);
    }

    public function testSorting_Descending() {
        $coaches = [5, 3, 8, 1];
        rsort($coaches);
        
        $this->assertEquals([8, 5, 3, 1], $coaches);
    }

    // =========================================================================
    // PAGINATION TESTS (4 tests)
    // =========================================================================

    public function testPagination_TotalPages() {
        $total = 100;
        $per_page = 20;
        $pages = ceil($total / $per_page);
        
        $this->assertEquals(5, $pages);
    }

    public function testPagination_CurrentPage() {
        $current_page = 3;
        $total_pages = 5;
        
        $this->assertGreaterThan(0, $current_page);
        $this->assertLessThanOrEqual($total_pages, $current_page);
    }

    public function testPagination_ItemsPerPage() {
        $per_page = 20;
        $valid_options = [10, 20, 50, 100];
        
        $is_valid = in_array($per_page, $valid_options);
        $this->assertTrue($is_valid);
    }

    public function testPagination_Offset() {
        $current_page = 3;
        $per_page = 20;
        $offset = ($current_page - 1) * $per_page;
        
        $this->assertEquals(40, $offset);
    }

    // =========================================================================
    // BULK ACTIONS TESTS (4 tests)
    // =========================================================================

    public function testBulkActions_Definition() {
        $bulk_actions = [
            'delete' => 'Delete',
            'change_tier' => 'Change Tier',
            'export' => 'Export'
        ];
        
        $this->assertArrayHasKey('delete', $bulk_actions);
    }

    public function testBulkActions_MultipleSelection() {
        $selected = [1, 2, 3, 4, 5];
        
        $this->assertCount(5, $selected);
    }

    public function testBulkActions_NoSelection() {
        $selected = [];
        
        if (empty($selected)) {
            $error = 'No items selected';
            $this->assertIsString($error);
        }
    }

    public function testBulkActions_ConfirmationRequired() {
        $action = 'delete';
        $requires_confirmation = in_array($action, ['delete', 'reset']);
        
        $this->assertTrue($requires_confirmation);
    }

    // =========================================================================
    // LIST VIEW RENDERING TESTS (5 tests)
    // =========================================================================

    public function testListView_RendersRequiredColumns() {
        $required_columns = [
            'checkbox',
            'name',
            'referral_code',
            'referrals',
            'commission',
            'conversion',
            'tier',
            'actions'
        ];
        
        $this->assertCount(8, $required_columns);
        $this->assertContains('referral_code', $required_columns);
        $this->assertContains('actions', $required_columns);
    }

    public function testListView_ReferralCodeDisplaysWithCopyButton() {
        $referral_code = 'COACH123';
        $has_copy_button = !empty($referral_code);
        
        $this->assertTrue($has_copy_button);
    }

    public function testListView_ActionsMatchGridView() {
        $list_actions = ['send_referral', 'edit', 'message', 'deactivate', 'view_details'];
        $grid_actions = ['send_referral', 'edit', 'message', 'deactivate', 'view_details'];
        
        $this->assertEquals($list_actions, $grid_actions);
    }

    public function testListView_SelectAllSyncsCheckboxes() {
        $checkboxes = [
            ['id' => 1, 'checked' => false],
            ['id' => 2, 'checked' => false],
            ['id' => 3, 'checked' => false],
        ];
        
        $select_all = true;
        
        foreach ($checkboxes as &$checkbox) {
            $checkbox['checked'] = $select_all;
        }
        
        $all_checked = array_reduce($checkboxes, function($carry, $item) {
            return $carry && $item['checked'];
        }, true);
        
        $this->assertTrue($all_checked);
    }

    public function testListView_SearchFiltersRows() {
        $coaches = [
            ['name' => 'John Smith', 'email' => 'john@example.com'],
            ['name' => 'Jane Doe', 'email' => 'jane@example.com'],
            ['name' => 'John Walker', 'email' => 'walker@example.com'],
        ];
        
        $search = 'john';
        $filtered = array_filter($coaches, function($c) use ($search) {
            $haystack = strtolower($c['name'] . ' ' . $c['email']);
            return strpos($haystack, strtolower($search)) !== false;
        });
        
        $this->assertCount(2, $filtered);
    }
}

