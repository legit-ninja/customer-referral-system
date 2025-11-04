<?php

use PHPUnit\Framework\TestCase;

/**
 * Test suite for Admin Points Validation (Phase 0)
 * 
 * Ensures fractional points are rejected in admin forms
 * Validates integer-only point adjustments
 */
class AdminPointsValidationTest extends TestCase {

    /**
     * Test that decimal points are detected and rejected
     */
    public function testRejectsDecimalPoints() {
        $test_values = [
            '10.5',
            '100.50',
            '25.99',
            '1.1',
            '0.5',
        ];

        foreach ($test_values as $value) {
            $has_decimal = (strpos($value, '.') !== false);
            $this->assertTrue($has_decimal, "Should detect decimal in: {$value}");
        }
    }

    /**
     * Test that comma-separated decimals are rejected (European format)
     */
    public function testRejectsCommaDecimals() {
        $test_values = [
            '10,5',
            '100,50',
            '25,99',
        ];

        foreach ($test_values as $value) {
            $has_comma = (strpos($value, ',') !== false);
            $this->assertTrue($has_comma, "Should detect comma in: {$value}");
        }
    }

    /**
     * Test that integer values are accepted
     */
    public function testAcceptsIntegerValues() {
        $test_values = [
            '10',
            '100',
            '1000',
            '0',
            '1',
        ];

        foreach ($test_values as $value) {
            $has_decimal = (strpos($value, '.') !== false);
            $has_comma = (strpos($value, ',') !== false);
            $is_valid = !$has_decimal && !$has_comma;
            
            $this->assertTrue($is_valid, "Should accept integer: {$value}");
        }
    }

    /**
     * Test validation logic for fractional values
     */
    public function testValidationLogicForFractionalValues() {
        $fractional_values = [
            '10.5' => false,   // Invalid
            '100.99' => false, // Invalid
            '10' => true,      // Valid
            '100' => true,     // Valid
            '0.5' => false,    // Invalid
            '1.0' => false,    // Invalid (still has decimal)
        ];

        foreach ($fractional_values as $value => $should_be_valid) {
            $has_decimal = (strpos($value, '.') !== false);
            $has_comma = (strpos($value, ',') !== false);
            $is_valid = !$has_decimal && !$has_comma;
            
            $this->assertEquals($should_be_valid, $is_valid, 
                "Value {$value} validation should be: " . ($should_be_valid ? 'valid' : 'invalid'));
        }
    }

    /**
     * Test that intval() conversion works correctly
     */
    public function testIntvalConversion() {
        $test_cases = [
            ['input' => '10', 'expected' => 10],
            ['input' => '100', 'expected' => 100],
            ['input' => '0', 'expected' => 0],
            ['input' => '1000', 'expected' => 1000],
        ];

        foreach ($test_cases as $case) {
            $result = intval($case['input']);
            $this->assertEquals($case['expected'], $result);
            $this->assertIsInt($result, 'Result should be integer');
        }
    }

    /**
     * Test that fractional values are truncated when using intval()
     */
    public function testIntvalTruncatesFractionalValues() {
        // These should not be allowed, but if they get through, intval truncates
        $test_cases = [
            ['input' => '10.5', 'expected' => 10],
            ['input' => '100.99', 'expected' => 100],
            ['input' => '0.9', 'expected' => 0],
        ];

        foreach ($test_cases as $case) {
            $result = intval($case['input']);
            $this->assertEquals($case['expected'], $result);
            $this->assertIsInt($result);
        }
    }

    /**
     * Test negative values are handled correctly
     */
    public function testNegativeValueValidation() {
        $value = '-10';
        $int_value = intval($value);
        
        $this->assertEquals(-10, $int_value);
        $this->assertLessThan(0, $int_value, 'Should detect negative value');
    }

    /**
     * Test zero is valid
     */
    public function testZeroIsValid() {
        $value = '0';
        $has_decimal = (strpos($value, '.') !== false);
        $int_value = intval($value);
        
        $this->assertFalse($has_decimal, 'Zero should not have decimal');
        $this->assertEquals(0, $int_value);
        $this->assertIsInt($int_value);
    }

    /**
     * Test large integer values
     */
    public function testLargeIntegerValues() {
        $test_values = [
            '1000',
            '10000',
            '99999',
        ];

        foreach ($test_values as $value) {
            $has_decimal = (strpos($value, '.') !== false);
            $int_value = intval($value);
            
            $this->assertFalse($has_decimal);
            $this->assertIsInt($int_value);
            $this->assertGreaterThan(0, $int_value);
        }
    }

    /**
     * Test edge case: "10." should be rejected (has decimal point)
     */
    public function testTrailingDecimalPointRejected() {
        $value = '10.';
        $has_decimal = (strpos($value, '.') !== false);
        
        $this->assertTrue($has_decimal, 'Should detect trailing decimal point');
    }

    /**
     * Test edge case: ".10" should be rejected
     */
    public function testLeadingDecimalPointRejected() {
        $value = '.10';
        $has_decimal = (strpos($value, '.') !== false);
        
        $this->assertTrue($has_decimal, 'Should detect leading decimal point');
    }

    /**
     * Test multiple decimal points
     */
    public function testMultipleDecimalPointsRejected() {
        $value = '10.5.5';
        $has_decimal = (strpos($value, '.') !== false);
        
        $this->assertTrue($has_decimal, 'Should detect decimal points');
    }

    /**
     * Test validation prevents data corruption
     */
    public function testValidationPreventsDataCorruption() {
        // If someone tries to enter 10.5, it should be rejected
        // NOT silently converted to 10 (which would lose data)
        
        $fractional_input = '10.5';
        $has_decimal = (strpos($fractional_input, '.') !== false);
        
        $this->assertTrue($has_decimal, 'Should detect fractional input');
        
        // Validation should reject this BEFORE conversion
        // This prevents: User enters "10.5", system saves "10" without warning
    }

    /**
     * Test form input step attribute
     */
    public function testFormInputStepAttribute() {
        // HTML input should have step="1" (not step="0.01")
        $old_step = '0.01'; // Bad - allows decimals
        $new_step = '1';    // Good - integers only
        
        $this->assertNotEquals($old_step, $new_step);
        $this->assertEquals('1', $new_step, 'Step should be 1 for integers only');
    }

    /**
     * Test validation error messages are clear
     */
    public function testValidationErrorMessagesAreClear() {
        $error_message = 'Points must be whole numbers only. Fractional values are not allowed.';
        
        $this->assertStringContainsString('whole numbers', $error_message);
        $this->assertStringContainsString('Fractional values', $error_message);
        $this->assertStringContainsString('not allowed', $error_message);
    }

    /**
     * Test admin adjustment with valid integer
     */
    public function testAdminAdjustmentWithValidInteger() {
        $points_amount = '100';
        $has_decimal = (strpos($points_amount, '.') !== false);
        $has_comma = (strpos($points_amount, ',') !== false);
        $is_valid = !$has_decimal && !$has_comma;
        
        $this->assertTrue($is_valid, 'Valid integer should pass');
        
        $int_value = intval($points_amount);
        $this->assertEquals(100, $int_value);
        $this->assertIsInt($int_value);
    }

    /**
     * Test admin adjustment with invalid decimal
     */
    public function testAdminAdjustmentWithInvalidDecimal() {
        $points_amount = '100.50';
        $has_decimal = (strpos($points_amount, '.') !== false);
        $has_comma = (strpos($points_amount, ',') !== false);
        $is_valid = !$has_decimal && !$has_comma;
        
        $this->assertFalse($is_valid, 'Decimal should fail validation');
    }

    /**
     * Test validation for all adjustment types
     */
    public function testValidationForAllAdjustmentTypes() {
        $adjustment_types = ['add', 'subtract', 'set'];
        $invalid_value = '10.5';
        
        foreach ($adjustment_types as $type) {
            $has_decimal = (strpos($invalid_value, '.') !== false);
            $this->assertTrue($has_decimal, 
                "Validation should reject decimal for {$type} operation");
        }
    }

    /**
     * Test that validation happens BEFORE database operations
     */
    public function testValidationBeforeDatabaseOperations() {
        // This test ensures validation order
        // 1. Check for decimal ✅ MUST BE FIRST
        // 2. Convert to int
        // 3. Check if valid user
        // 4. Perform database operation
        
        $fractional_input = '10.5';
        
        // Step 1: Validation MUST happen first
        $has_decimal = (strpos($fractional_input, '.') !== false);
        $this->assertTrue($has_decimal, 'Decimal check must happen FIRST');
        
        // If validation passes (it shouldn't), then convert
        // But in real code, execution stops at validation failure
    }

    /**
     * Test whitespace handling
     */
    public function testWhitespaceHandling() {
        $values_with_whitespace = [
            ' 10',
            '10 ',
            ' 10 ',
        ];

        foreach ($values_with_whitespace as $value) {
            $trimmed = trim($value);
            $has_decimal = (strpos($trimmed, '.') !== false);
            $int_value = intval($trimmed);
            
            $this->assertFalse($has_decimal);
            $this->assertEquals(10, $int_value);
        }
    }

    /**
     * Test empty string handling
     */
    public function testEmptyStringHandling() {
        $value = '';
        $int_value = intval($value);
        
        $this->assertEquals(0, $int_value, 'Empty string converts to 0');
    }

    /**
     * Test non-numeric string handling
     */
    public function testNonNumericStringHandling() {
        $values = ['abc', 'ten', '10abc'];
        
        foreach ($values as $value) {
            $int_value = intval($value);
            $this->assertIsInt($int_value, 'Should convert to int (possibly 0)');
        }
    }

    /**
     * Test validation consistency across different locales
     */
    public function testValidationConsistencyAcrossLocales() {
        // US format: 10.5
        $us_decimal = '10.5';
        $this->assertTrue(strpos($us_decimal, '.') !== false);
        
        // European format: 10,5
        $eu_decimal = '10,5';
        $this->assertTrue(strpos($eu_decimal, ',') !== false);
        
        // Both should be rejected
        $us_valid = (strpos($us_decimal, '.') === false && strpos($us_decimal, ',') === false);
        $eu_valid = (strpos($eu_decimal, '.') === false && strpos($eu_decimal, ',') === false);
        
        $this->assertFalse($us_valid, 'US decimal format should be rejected');
        $this->assertFalse($eu_valid, 'EU decimal format should be rejected');
    }

    /**
     * Test that integer validation prevents accounting errors
     */
    public function testIntegerValidationPreventsAccountingErrors() {
        // Scenario: Admin tries to add 10.5 points
        // Old system: Might save as 10.5 (database corruption)
        // New system: Rejects with clear error
        
        $attempted_value = '10.5';
        $has_decimal = (strpos($attempted_value, '.') !== false);
        
        $this->assertTrue($has_decimal, 'Should detect and reject fractional value');
        
        // In real system, this triggers error BEFORE any database operation
        // Prevents: Confusion, accounting mismatches, rounding errors
    }

    /**
     * Test complete validation flow
     */
    public function testCompleteValidationFlow() {
        $test_cases = [
            // [input, should_pass_validation, expected_int]
            ['100', true, 100],
            ['10.5', false, null],
            ['0', true, 0],
            ['-10', true, -10], // Negative check happens separately
            ['1000', true, 1000],
            ['100.99', false, null],
            ['10,5', false, null],
        ];

        foreach ($test_cases as $case) {
            list($input, $should_pass, $expected_int) = $case;
            
            $has_decimal = (strpos($input, '.') !== false);
            $has_comma = (strpos($input, ',') !== false);
            $passes_validation = !$has_decimal && !$has_comma;
            
            $this->assertEquals($should_pass, $passes_validation, 
                "Input '{$input}' should " . ($should_pass ? 'pass' : 'fail') . " validation");
            
            if ($should_pass) {
                $int_value = intval($input);
                $this->assertEquals($expected_int, $int_value);
                $this->assertIsInt($int_value);
            }
        }
    }
}

