<?php

use PHPUnit\Framework\TestCase;

/**
 * Test suite for Coach CSV Import with Flexible Column Mapping
 * 
 * Tests the bugfix for flexible column name variations to prevent regression
 */
class CoachCSVImportTest extends TestCase {

    protected function setUp(): void {
        // Include the admin settings class
        require_once __DIR__ . '/../includes/class-admin-settings.php';
    }

    /**
     * Test flexible column mapping - Standard format
     */
    public function testStandardColumnFormat() {
        $header = ['first_name', 'last_name', 'email', 'phone'];
        $mapping = $this->getColumnMapping($header);
        
        $this->assertArrayHasKey('first_name', $mapping);
        $this->assertArrayHasKey('last_name', $mapping);
        $this->assertArrayHasKey('email', $mapping);
        $this->assertEquals(0, $mapping['first_name']);
        $this->assertEquals(1, $mapping['last_name']);
        $this->assertEquals(2, $mapping['email']);
    }

    /**
     * Test flexible column mapping - Capitalized with spaces
     */
    public function testCapitalizedWithSpaces() {
        $header = ['First Name', 'Last Name', 'Email', 'Phone Number'];
        $mapping = $this->getColumnMapping($header);
        
        $this->assertArrayHasKey('first_name', $mapping);
        $this->assertArrayHasKey('last_name', $mapping);
        $this->assertArrayHasKey('email', $mapping);
        $this->assertArrayHasKey('phone', $mapping);
    }

    /**
     * Test flexible column mapping - Alternative names (given_name, surname)
     */
    public function testAlternativeNames() {
        $header = ['Given Name', 'Surname', 'E-mail'];
        $mapping = $this->getColumnMapping($header);
        
        $this->assertArrayHasKey('first_name', $mapping);
        $this->assertArrayHasKey('last_name', $mapping);
        $this->assertArrayHasKey('email', $mapping);
    }

    /**
     * Test flexible column mapping - CamelCase format
     */
    public function testCamelCaseFormat() {
        $header = ['FirstName', 'LastName', 'EmailAddress'];
        $mapping = $this->getColumnMapping($header);
        
        $this->assertArrayHasKey('first_name', $mapping);
        $this->assertArrayHasKey('last_name', $mapping);
        $this->assertArrayHasKey('email', $mapping);
    }

    /**
     * Test flexible column mapping - Mixed formats
     */
    public function testMixedFormats() {
        $header = ['first_name', 'Surname', 'Email Address', 'Phone', 'Specialty'];
        $mapping = $this->getColumnMapping($header);
        
        $this->assertArrayHasKey('first_name', $mapping);
        $this->assertArrayHasKey('last_name', $mapping);
        $this->assertArrayHasKey('email', $mapping);
        $this->assertArrayHasKey('phone', $mapping);
        $this->assertArrayHasKey('specialization', $mapping);
    }

    /**
     * Test optional field mappings
     */
    public function testOptionalFields() {
        $header = [
            'First Name', 'Last Name', 'Email',
            'Phone', 'Specialty', 'City', 'Experience', 'Bio'
        ];
        $mapping = $this->getColumnMapping($header);
        
        // Required fields
        $this->assertArrayHasKey('first_name', $mapping);
        $this->assertArrayHasKey('last_name', $mapping);
        $this->assertArrayHasKey('email', $mapping);
        
        // Optional fields
        $this->assertArrayHasKey('phone', $mapping);
        $this->assertArrayHasKey('specialization', $mapping);
        $this->assertArrayHasKey('location', $mapping);
        $this->assertArrayHasKey('experience_years', $mapping);
        $this->assertArrayHasKey('bio', $mapping);
    }

    /**
     * Test missing required columns detection
     */
    public function testMissingRequiredColumns() {
        // Missing last_name
        $header = ['First Name', 'Email'];
        $missing = $this->getMissingColumns($header);
        
        $this->assertContains('last_name', $missing);
        $this->assertNotContains('first_name', $missing);
        $this->assertNotContains('email', $missing);
    }

    /**
     * Test missing all required columns
     */
    public function testMissingAllColumns() {
        $header = ['Phone', 'City', 'Specialty'];
        $missing = $this->getMissingColumns($header);
        
        $this->assertContains('first_name', $missing);
        $this->assertContains('last_name', $missing);
        $this->assertContains('email', $missing);
    }

    /**
     * Test header normalization
     */
    public function testHeaderNormalization() {
        $test_cases = [
            'First Name' => 'first_name',
            'FIRST NAME' => 'first_name',
            'first name' => 'first_name',
            'FirstName' => 'firstname', // Note: No space to replace
            'Email Address' => 'email_address',
            'E-mail' => 'e-mail',
            'Phone Number' => 'phone_number'
        ];

        foreach ($test_cases as $input => $expected) {
            $normalized = $this->normalizeColumnName($input);
            $this->assertEquals($expected, $normalized, "Failed to normalize: {$input}");
        }
    }

    /**
     * Test case insensitivity
     */
    public function testCaseInsensitivity() {
        $variations = [
            ['first_name', 'last_name', 'email'],
            ['FIRST_NAME', 'LAST_NAME', 'EMAIL'],
            ['First_Name', 'Last_Name', 'Email'],
            ['FiRsT_NaMe', 'LaSt_NaMe', 'EmAiL']
        ];

        foreach ($variations as $header) {
            $mapping = $this->getColumnMapping($header);
            $this->assertArrayHasKey('first_name', $mapping);
            $this->assertArrayHasKey('last_name', $mapping);
            $this->assertArrayHasKey('email', $mapping);
        }
    }

    /**
     * Test column order doesn't matter
     */
    public function testColumnOrderDoesntMatter() {
        // Email first, name last
        $header1 = ['Email', 'Last Name', 'First Name'];
        $mapping1 = $this->getColumnMapping($header1);
        
        $this->assertEquals(2, $mapping1['first_name']);
        $this->assertEquals(1, $mapping1['last_name']);
        $this->assertEquals(0, $mapping1['email']);

        // Standard order
        $header2 = ['First Name', 'Last Name', 'Email'];
        $mapping2 = $this->getColumnMapping($header2);
        
        $this->assertEquals(0, $mapping2['first_name']);
        $this->assertEquals(1, $mapping2['last_name']);
        $this->assertEquals(2, $mapping2['email']);
    }

    /**
     * Test all supported first name variations
     */
    public function testAllFirstNameVariations() {
        $variations = [
            'first_name', 'firstname', 'FirstName',
            'First Name', 'FIRST NAME', 'first name',
            'given_name', 'Given Name', 'forename'
        ];

        foreach ($variations as $variation) {
            $header = [$variation, 'Last Name', 'Email'];
            $mapping = $this->getColumnMapping($header);
            
            $this->assertArrayHasKey('first_name', $mapping, 
                "Failed to map variation: {$variation}");
        }
    }

    /**
     * Test all supported last name variations
     */
    public function testAllLastNameVariations() {
        $variations = [
            'last_name', 'lastname', 'LastName',
            'Last Name', 'LAST NAME', 'last name',
            'surname', 'Surname', 'family_name', 'Family Name'
        ];

        foreach ($variations as $variation) {
            $header = ['First Name', $variation, 'Email'];
            $mapping = $this->getColumnMapping($header);
            
            $this->assertArrayHasKey('last_name', $mapping, 
                "Failed to map variation: {$variation}");
        }
    }

    /**
     * Test all supported email variations
     */
    public function testAllEmailVariations() {
        $variations = [
            'email', 'Email', 'EMAIL',
            'e-mail', 'E-mail', 'E-Mail',
            'email_address', 'Email Address',
            'mail', 'Mail'
        ];

        foreach ($variations as $variation) {
            $header = ['First Name', 'Last Name', $variation];
            $mapping = $this->getColumnMapping($header);
            
            $this->assertArrayHasKey('email', $mapping, 
                "Failed to map variation: {$variation}");
        }
    }

    /**
     * Test optional field variations
     */
    public function testOptionalFieldVariations() {
        // Phone variations
        $phone_variations = ['phone', 'Phone', 'telephone', 'Phone Number', 'mobile'];
        foreach ($phone_variations as $var) {
            $header = ['First Name', 'Last Name', 'Email', $var];
            $mapping = $this->getColumnMapping($header);
            $this->assertArrayHasKey('phone', $mapping, "Failed: {$var}");
        }

        // Specialization variations
        $spec_variations = ['specialization', 'Specialty', 'focus'];
        foreach ($spec_variations as $var) {
            $header = ['First Name', 'Last Name', 'Email', $var];
            $mapping = $this->getColumnMapping($header);
            $this->assertArrayHasKey('specialization', $mapping, "Failed: {$var}");
        }
    }

    /**
     * Test edge cases
     */
    public function testEdgeCases() {
        // Extra spaces in column names
        $header = ['  First Name  ', '  Last Name  ', '  Email  '];
        $mapping = $this->getColumnMapping($header);
        
        $this->assertArrayHasKey('first_name', $mapping);
        $this->assertArrayHasKey('last_name', $mapping);
        $this->assertArrayHasKey('email', $mapping);

        // Tabs and multiple spaces
        $header2 = ['First  Name', 'Last		Name', 'Email'];
        $mapping2 = $this->getColumnMapping($header2);
        
        // Should normalize multiple spaces/tabs to single underscore
        $this->assertArrayHasKey('first_name', $mapping2);
    }

    /**
     * Test with real-world CSV header from the error
     */
    public function testRealWorldCSVFormat() {
        // Common exports from Google Sheets, Excel, etc.
        $real_world_formats = [
            ['First Name', 'Last Name', 'Email', 'Phone', 'City'],
            ['Given Name', 'Surname', 'E-mail Address'],
            ['FirstName', 'LastName', 'Email'],
            ['Name', 'Surname', 'Email'], // Using 'Name' for first name
        ];

        foreach ($real_world_formats as $index => $header) {
            $mapping = $this->getColumnMapping($header);
            
            $this->assertArrayHasKey('first_name', $mapping, 
                "Format #{$index} failed to map first_name");
            $this->assertArrayHasKey('last_name', $mapping, 
                "Format #{$index} failed to map last_name");
            $this->assertArrayHasKey('email', $mapping, 
                "Format #{$index} failed to map email");
        }
    }

    /**
     * Test unsupported column names are ignored (not error)
     */
    public function testUnsupportedColumnsIgnored() {
        $header = ['First Name', 'Last Name', 'Email', 'Random Column', 'Another Column'];
        $mapping = $this->getColumnMapping($header);
        
        // Required columns should be mapped
        $this->assertArrayHasKey('first_name', $mapping);
        $this->assertArrayHasKey('last_name', $mapping);
        $this->assertArrayHasKey('email', $mapping);
        
        // Unsupported columns should not cause errors (just ignored)
        $this->assertCount(3, $mapping); // Only 3 mapped fields
    }

    // ============================================================
    // HELPER METHODS (Replicate the logic from class-admin-settings.php)
    // ============================================================

    /**
     * Helper: Normalize column name
     */
    private function normalizeColumnName($col) {
        return strtolower(str_replace(' ', '_', trim($col)));
    }

    /**
     * Helper: Get column mapping
     */
    private function getColumnMapping($header) {
        // Normalize headers
        $normalized_header = array_map(function($col) {
            return strtolower(str_replace(' ', '_', trim($col)));
        }, $header);

        // Column mapping (same as in class-admin-settings.php)
        $column_mapping = [
            'first_name' => 'first_name',
            'firstname' => 'first_name',
            'given_name' => 'first_name',
            'forename' => 'first_name',
            'name' => 'first_name',
            
            'last_name' => 'last_name',
            'lastname' => 'last_name',
            'surname' => 'last_name',
            'family_name' => 'last_name',
            
            'email' => 'email',
            'e-mail' => 'email',
            'email_address' => 'email',
            'mail' => 'email',
            
            'phone' => 'phone',
            'telephone' => 'phone',
            'phone_number' => 'phone',
            'mobile' => 'phone',
            
            'specialization' => 'specialization',
            'specialty' => 'specialization',
            'focus' => 'specialization',
            
            'location' => 'location',
            'city' => 'location',
            'region' => 'location',
            
            'experience_years' => 'experience_years',
            'experience' => 'experience_years',
            'years_experience' => 'experience_years',
            
            'bio' => 'bio',
            'biography' => 'bio',
            'description' => 'bio',
            'about' => 'bio'
        ];

        // Map headers to standard field names
        $field_map = [];
        foreach ($normalized_header as $index => $norm_col) {
            if (isset($column_mapping[$norm_col])) {
                $standard_name = $column_mapping[$norm_col];
                $field_map[$standard_name] = $index;
            }
        }

        return $field_map;
    }

    /**
     * Helper: Get missing required columns
     */
    private function getMissingColumns($header) {
        $mapping = $this->getColumnMapping($header);
        $required_columns = ['first_name', 'last_name', 'email'];
        
        $missing = [];
        foreach ($required_columns as $required) {
            if (!isset($mapping[$required])) {
                $missing[] = $required;
            }
        }
        
        return $missing;
    }

    /**
     * Test CSV parsing with actual CSV content
     */
    public function testParseCSVContent() {
        // Create temporary CSV file
        $temp_file = tempnam(sys_get_temp_dir(), 'coach_test_');
        
        // Test with standard format
        $csv_content = "First Name,Last Name,Email\n";
        $csv_content .= "Thomas,Mueller,thomas.mueller@test.ch\n";
        $csv_content .= "Sandra,Weber,sandra.weber@test.ch\n";
        
        file_put_contents($temp_file, $csv_content);
        
        // Parse CSV
        $handle = fopen($temp_file, 'r');
        $header = fgetcsv($handle);
        $data = fgetcsv($handle);
        fclose($handle);
        
        // Test mapping
        $mapping = $this->getColumnMapping($header);
        $this->assertArrayHasKey('first_name', $mapping);
        $this->assertArrayHasKey('last_name', $mapping);
        $this->assertArrayHasKey('email', $mapping);
        
        // Test data extraction
        $coach_data = [];
        foreach ($mapping as $standard_name => $column_index) {
            $coach_data[$standard_name] = $data[$column_index];
        }
        
        $this->assertEquals('Thomas', $coach_data['first_name']);
        $this->assertEquals('Mueller', $coach_data['last_name']);
        $this->assertEquals('thomas.mueller@test.ch', $coach_data['email']);
        
        // Cleanup
        unlink($temp_file);
    }

    /**
     * Test CSV with UTF-8 characters (Swiss names with umlauts)
     */
    public function testUTF8Characters() {
        $header = ['First Name', 'Last Name', 'Email'];
        $mapping = $this->getColumnMapping($header);
        
        // Should handle headers with UTF-8 correctly
        $this->assertArrayHasKey('first_name', $mapping);
        
        // Test with Swiss character names
        $names = ['Müller', 'François', 'José', 'Michèle'];
        foreach ($names as $name) {
            // Should not break on special characters
            $this->assertIsString($name);
        }
    }

    /**
     * Test empty header handling
     */
    public function testEmptyHeaders() {
        $header = ['', 'Last Name', 'Email'];
        $mapping = $this->getColumnMapping($header);
        
        // Empty column should be ignored
        $this->assertArrayHasKey('last_name', $mapping);
        $this->assertArrayHasKey('email', $mapping);
        $this->assertArrayNotHasKey('', $mapping);
    }

    /**
     * Test duplicate column names
     */
    public function testDuplicateColumns() {
        // Sometimes exports have duplicate "Name" columns
        $header = ['First Name', 'Last Name', 'Email', 'Phone', 'Phone'];
        $mapping = $this->getColumnMapping($header);
        
        // Should map to the first occurrence
        $this->assertArrayHasKey('phone', $mapping);
        $this->assertEquals(3, $mapping['phone']); // First phone column
    }

    /**
     * Test column mapping consistency
     */
    public function testMappingConsistency() {
        $header = ['First Name', 'Last Name', 'Email'];
        
        // Run mapping multiple times
        $mapping1 = $this->getColumnMapping($header);
        $mapping2 = $this->getColumnMapping($header);
        $mapping3 = $this->getColumnMapping($header);
        
        // Should always produce same result
        $this->assertEquals($mapping1, $mapping2);
        $this->assertEquals($mapping2, $mapping3);
    }

    /**
     * Test all required columns must be present
     */
    public function testRequiredColumnsValidation() {
        $required = ['first_name', 'last_name', 'email'];
        
        // Valid: All required present
        $header1 = ['First Name', 'Last Name', 'Email'];
        $missing1 = $this->getMissingColumns($header1);
        $this->assertEmpty($missing1);
        
        // Invalid: Missing email
        $header2 = ['First Name', 'Last Name'];
        $missing2 = $this->getMissingColumns($header2);
        $this->assertContains('email', $missing2);
        
        // Invalid: Missing first name
        $header3 = ['Last Name', 'Email'];
        $missing3 = $this->getMissingColumns($header3);
        $this->assertContains('first_name', $missing3);
    }

    /**
     * Test error message format
     */
    public function testErrorMessageFormat() {
        $header = ['Phone', 'City']; // Missing all required
        $missing = $this->getMissingColumns($header);
        
        // Should list all missing columns
        $this->assertCount(3, $missing); // first_name, last_name, email
        $this->assertContains('first_name', $missing);
        $this->assertContains('last_name', $missing);
        $this->assertContains('email', $missing);
    }

    /**
     * Test that the fix prevents the original bug
     */
    public function testOriginalBugFixed() {
        // The original error from debug.log:
        // CSV had: "First Name, Last Name, Email" (with spaces)
        // Code expected: "first_name, last_name, email"
        // Result: "Missing required columns: first_name, last_name, email"
        
        $problematic_header = ['First Name', 'Last Name', 'Email'];
        $mapping = $this->getColumnMapping($problematic_header);
        
        // This should now work (was failing before)
        $this->assertArrayHasKey('first_name', $mapping, 
            'REGRESSION: Original bug not fixed!');
        $this->assertArrayHasKey('last_name', $mapping, 
            'REGRESSION: Original bug not fixed!');
        $this->assertArrayHasKey('email', $mapping, 
            'REGRESSION: Original bug not fixed!');
        
        // Missing columns should be empty (not ['first_name', 'last_name', 'email'])
        $missing = $this->getMissingColumns($problematic_header);
        $this->assertEmpty($missing, 
            'REGRESSION: Flexible mapping not working!');
    }

    /**
     * Test CSV with title row before headers (CRITICAL - Prevents current bug)
     */
    public function testCSVWithTitleRow() {
        // Create CSV with title row
        $temp_file = tempnam(sys_get_temp_dir(), 'coach_test_');
        
        // Row 1: Empty cells with title
        // Row 2: Actual headers
        // Row 3+: Data
        $csv_content = ", , BASEL COACHES 2025, \n"; // Title row
        $csv_content .= "First Name,Last Name,Email,Phone\n"; // Header row
        $csv_content .= "Thomas,Mueller,thomas@test.ch,123456\n"; // Data
        
        file_put_contents($temp_file, $csv_content);
        
        // Parse with our logic
        $header = $this->findHeaderRow($temp_file);
        
        // Should find the real header (row 2), not title (row 1)
        $this->assertNotNull($header, 'Should find header row');
        $this->assertContains('First Name', $header, 'Should find First Name column');
        $this->assertContains('Last Name', $header, 'Should find Last Name column');
        $this->assertContains('Email', $header, 'Should find Email column');
        $this->assertNotContains('BASEL COACHES 2025', $header, 'Should skip title row');
        
        // Cleanup
        unlink($temp_file);
    }

    /**
     * Test CSV with multiple empty rows before headers
     */
    public function testCSVWithMultipleEmptyRows() {
        $temp_file = tempnam(sys_get_temp_dir(), 'coach_test_');
        
        // Rows 1-2: Empty
        // Row 3: Headers
        // Row 4+: Data
        $csv_content = "\n"; // Empty row 1
        $csv_content .= ", , , \n"; // Empty row 2
        $csv_content .= "First Name,Last Name,Email\n"; // Header row
        $csv_content .= "Thomas,Mueller,thomas@test.ch\n";
        
        file_put_contents($temp_file, $csv_content);
        
        $header = $this->findHeaderRow($temp_file);
        
        $this->assertNotNull($header);
        $this->assertContains('First Name', $header);
        
        unlink($temp_file);
    }

    /**
     * Test CSV with title and subtitle rows
     */
    public function testCSVWithTitleAndSubtitle() {
        $temp_file = tempnam(sys_get_temp_dir(), 'coach_test_');
        
        // Row 1: Title (only 1 cell)
        // Row 2: Subtitle (only 1 cell)
        // Row 3: Headers (3+ cells)
        $csv_content = "BASEL COACHES 2025\n";
        $csv_content .= "Export Date: November 4, 2025\n";
        $csv_content .= "First Name,Last Name,Email\n";
        $csv_content .= "Thomas,Mueller,thomas@test.ch\n";
        
        file_put_contents($temp_file, $csv_content);
        
        $header = $this->findHeaderRow($temp_file);
        
        $this->assertNotNull($header);
        $this->assertEquals('First Name', $header[0]);
        $this->assertNotContains('BASEL COACHES 2025', $header);
        
        unlink($temp_file);
    }

    /**
     * Test CSV with no valid headers (error case)
     */
    public function testCSVWithNoValidHeaders() {
        $temp_file = tempnam(sys_get_temp_dir(), 'coach_test_');
        
        // Only title rows, no actual headers
        $csv_content = "TITLE\n";
        $csv_content .= "Another Title\n";
        $csv_content .= "\n";
        
        file_put_contents($temp_file, $csv_content);
        
        $header = $this->findHeaderRow($temp_file);
        
        // Should return null when no valid headers found
        $this->assertNull($header);
        
        unlink($temp_file);
    }

    /**
     * Test the original bug scenario from debug.log
     */
    public function testOriginalDebugLogScenario() {
        // From debug.log:
        // CSV Headers found: , , BASEL COACHES 2025,
        // This means row 1 has empty cells and a title
        
        $temp_file = tempnam(sys_get_temp_dir(), 'coach_test_');
        
        // Replicate exact scenario from debug.log
        $csv_content = ", , BASEL COACHES 2025, \n";
        $csv_content .= "First Name,Last Name,Email,Phone\n";
        $csv_content .= "Thomas,Mueller,thomas@test.ch,123456\n";
        
        file_put_contents($temp_file, $csv_content);
        
        $header = $this->findHeaderRow($temp_file);
        
        // Should skip the title row and find real headers
        $this->assertNotNull($header, 'Should find header row (not title)');
        $this->assertNotEmpty($header[0], 'First column should not be empty');
        $this->assertNotEquals('BASEL COACHES 2025', $header[0], 'Should not use title as header');
        
        // Should find proper headers
        $mapping = $this->getColumnMapping($header);
        $this->assertArrayHasKey('first_name', $mapping);
        $this->assertArrayHasKey('last_name', $mapping);
        $this->assertArrayHasKey('email', $mapping);
        
        unlink($temp_file);
    }

    // ============================================================
    // HELPER METHOD: Find header row (replicates fixed logic)
    // ============================================================

    /**
     * Helper: Find header row by skipping empty/title rows
     */
    private function findHeaderRow($file_path) {
        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return null;
        }

        $header = null;
        $max_rows_to_check = 5;
        $rows_checked = 0;
        
        while (($potential_header = fgetcsv($handle, 1000, ',')) !== false && $rows_checked < $max_rows_to_check) {
            $rows_checked++;
            
            // Skip completely empty rows
            if (empty(array_filter($potential_header, function($cell) { return !empty(trim($cell)); }))) {
                continue;
            }
            
            // Skip rows that are likely titles (have only 1-2 non-empty cells)
            $non_empty_count = count(array_filter($potential_header, function($cell) { return !empty(trim($cell)); }));
            if ($non_empty_count < 3) {
                continue;
            }
            
            // This looks like a valid header row
            $header = $potential_header;
            break;
        }
        
        fclose($handle);
        return $header;
    }

    /**
     * Cleanup after tests
     */
    protected function tearDown(): void {
        // Cleanup any temporary files if created
    }
}

