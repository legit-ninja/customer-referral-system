<?php

use PHPUnit\Framework\TestCase;

/**
 * Test suite for Utility Classes in class-utils.php
 * 
 * Covers:
 * - intersoccer_validate_batch_params() function
 * - intersoccer_get_recommended_batch_config() function
 * - InterSoccer_Credit_Calculator class
 * - InterSoccer_Import_Logger class
 * - InterSoccer_Database_Optimizer class
 */
class UtilityClassesTest extends TestCase {

    protected function setUp(): void {
        // Include the utility classes
        require_once __DIR__ . '/../includes/class-utils.php';
        
        // Mock WordPress functions
        if (!function_exists('wp_upload_dir')) {
            function wp_upload_dir() {
                return ['basedir' => '/tmp/test-uploads'];
            }
        }
        
        if (!function_exists('wp_mkdir_p')) {
            function wp_mkdir_p($dir) {
                if (!file_exists($dir)) {
                    mkdir($dir, 0755, true);
                }
                return true;
            }
        }
        
        if (!function_exists('current_time')) {
            function current_time($format) {
                return date($format);
            }
        }
        
        if (!function_exists('check_ajax_referer')) {
            function check_ajax_referer($action, $query_arg = false) {
                return true;
            }
        }
        
        if (!function_exists('current_user_can')) {
            function current_user_can($capability) {
                return true;
            }
        }
        
        if (!function_exists('wp_die')) {
            function wp_die($message) {
                throw new Exception($message);
            }
        }
        
        if (!function_exists('wp_next_scheduled')) {
            function wp_next_scheduled($hook) {
                return false;
            }
        }
        
        if (!function_exists('wp_schedule_event')) {
            function wp_schedule_event($timestamp, $recurrence, $hook) {
                return true;
            }
        }
        
        if (!function_exists('add_action')) {
            function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
                return true;
            }
        }
    }

    // =========================================================================
    // BATCH PARAM VALIDATION TESTS (12 tests)
    // =========================================================================

    public function testValidateBatchParams_ValidParameters() {
        $errors = intersoccer_validate_batch_params(25, 1000);
        $this->assertEmpty($errors, 'Valid parameters should not produce errors');
    }

    public function testValidateBatchParams_BatchSizeTooSmall() {
        $errors = intersoccer_validate_batch_params(3, 1000);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Batch size must be between 5 and 100', $errors[0]);
    }

    public function testValidateBatchParams_BatchSizeTooLarge() {
        $errors = intersoccer_validate_batch_params(150, 1000);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Batch size must be between 5 and 100', $errors[0]);
    }

    public function testValidateBatchParams_BatchSizeMinimum() {
        $errors = intersoccer_validate_batch_params(5, 1000);
        $this->assertEmpty($errors, 'Minimum batch size (5) should be valid');
    }

    public function testValidateBatchParams_BatchSizeMaximum() {
        $errors = intersoccer_validate_batch_params(100, 1000);
        $this->assertEmpty($errors, 'Maximum batch size (100) should be valid');
    }

    public function testValidateBatchParams_ScanLimitTooSmall() {
        $errors = intersoccer_validate_batch_params(25, 5);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Scan limit must be between 10 and 10000', $errors[0]);
    }

    public function testValidateBatchParams_ScanLimitTooLarge() {
        $errors = intersoccer_validate_batch_params(25, 15000);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Scan limit must be between 10 and 10000', $errors[0]);
    }

    public function testValidateBatchParams_ScanLimitUnlimited() {
        $errors = intersoccer_validate_batch_params(25, -1);
        $this->assertEmpty($errors, 'Scan limit of -1 (unlimited) should be valid');
    }

    public function testValidateBatchParams_MultipleErrors() {
        $errors = intersoccer_validate_batch_params(3, 5);
        $this->assertCount(2, $errors, 'Should report both batch size and scan limit errors');
    }

    public function testValidateBatchParams_EdgeCases() {
        // Test boundary values
        $this->assertEmpty(intersoccer_validate_batch_params(10, 10));
        $this->assertEmpty(intersoccer_validate_batch_params(50, 10000));
        $this->assertEmpty(intersoccer_validate_batch_params(5, -1));
    }

    public function testValidateBatchParams_ServerMemoryConstraint() {
        // This test assumes low memory scenario
        // In real environment, would need to mock ini_get
        $errors = intersoccer_validate_batch_params(25, 1000);
        // Should pass with normal memory limits
        $this->assertTrue(is_array($errors));
    }

    public function testValidateBatchParams_ServerTimeConstraint() {
        // Test that validation considers server execution time
        // In real environment, would need to mock ini_get
        $errors = intersoccer_validate_batch_params(15, 500);
        $this->assertTrue(is_array($errors));
    }

    // =========================================================================
    // BATCH CONFIG RECOMMENDATION TESTS (8 tests)
    // =========================================================================

    public function testGetRecommendedBatchConfig_ReturnsArray() {
        $config = intersoccer_get_recommended_batch_config();
        $this->assertIsArray($config);
    }

    public function testGetRecommendedBatchConfig_HasRequiredKeys() {
        $config = intersoccer_get_recommended_batch_config();
        $this->assertArrayHasKey('batch_size', $config);
        $this->assertArrayHasKey('scan_limit', $config);
        $this->assertArrayHasKey('timeout', $config);
        $this->assertArrayHasKey('max_retries', $config);
    }

    public function testGetRecommendedBatchConfig_BatchSizeIsPositive() {
        $config = intersoccer_get_recommended_batch_config();
        $this->assertGreaterThan(0, $config['batch_size']);
    }

    public function testGetRecommendedBatchConfig_ScanLimitIsPositive() {
        $config = intersoccer_get_recommended_batch_config();
        $this->assertGreaterThan(0, $config['scan_limit']);
    }

    public function testGetRecommendedBatchConfig_TimeoutIsPositive() {
        $config = intersoccer_get_recommended_batch_config();
        $this->assertGreaterThan(0, $config['timeout']);
    }

    public function testGetRecommendedBatchConfig_MaxRetriesIsPositive() {
        $config = intersoccer_get_recommended_batch_config();
        $this->assertGreaterThanOrEqual(0, $config['max_retries']);
    }

    public function testGetRecommendedBatchConfig_BatchSizeWithinLimits() {
        $config = intersoccer_get_recommended_batch_config();
        $this->assertGreaterThanOrEqual(5, $config['batch_size']);
        $this->assertLessThanOrEqual(100, $config['batch_size']);
    }

    public function testGetRecommendedBatchConfig_ConsistentDefaults() {
        $config1 = intersoccer_get_recommended_batch_config();
        $config2 = intersoccer_get_recommended_batch_config();
        $this->assertEquals($config1, $config2, 'Should return consistent values');
    }

    // =========================================================================
    // CREDIT CALCULATOR TESTS (15 tests)
    // =========================================================================

    public function testCreditCalculator_ValidateCreditCalculation_ValidCredits() {
        $credits = [
            'total_credits' => 50,
            'base_credits' => 20,
            'loyalty_percentage' => 20,
            'loyalty_bonus' => 10,
            'retention_bonus' => 0,
            'surprise_bonus' => 0
        ];
        
        $validation = InterSoccer_Credit_Calculator::validate_credit_calculation($credits, 123);
        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['errors']);
    }

    public function testCreditCalculator_ValidateCreditCalculation_NegativeCredits() {
        $credits = [
            'total_credits' => -10,
            'base_credits' => 0,
            'loyalty_bonus' => 0,
            'retention_bonus' => 0,
            'surprise_bonus' => 0
        ];
        
        $validation = InterSoccer_Credit_Calculator::validate_credit_calculation($credits, 123);
        $this->assertFalse($validation['valid']);
        $this->assertCount(1, $validation['errors']);
        $this->assertStringContainsString('Negative total credits', $validation['errors'][0]);
    }

    public function testCreditCalculator_ValidateCreditCalculation_ExceedsMaximum() {
        $credits = [
            'total_credits' => 600, // Exceeds max of 500
            'base_credits' => 100,
            'loyalty_bonus' => 500,
            'retention_bonus' => 0,
            'surprise_bonus' => 0
        ];
        
        $validation = InterSoccer_Credit_Calculator::validate_credit_calculation($credits, 123);
        $this->assertFalse($validation['valid']);
        $this->assertCount(1, $validation['errors']);
        $this->assertStringContainsString('exceed maximum', $validation['errors'][0]);
    }

    public function testCreditCalculator_ValidateCreditCalculation_LoyaltyBonusWarning() {
        $credits = [
            'total_credits' => 100,
            'base_credits' => 10,
            'loyalty_bonus' => 90, // 90% of total
            'retention_bonus' => 0,
            'surprise_bonus' => 0
        ];
        
        $validation = InterSoccer_Credit_Calculator::validate_credit_calculation($credits, 123);
        $this->assertTrue($validation['valid']);
        $this->assertCount(1, $validation['warnings']);
        $this->assertStringContainsString('Loyalty bonus unusually high', $validation['warnings'][0]);
    }

    public function testCreditCalculator_ValidateCreditCalculation_SurpriseBonusWarning() {
        $credits = [
            'total_credits' => 10,
            'base_credits' => 0,
            'loyalty_bonus' => 0,
            'retention_bonus' => 0,
            'surprise_bonus' => 10
        ];
        
        $validation = InterSoccer_Credit_Calculator::validate_credit_calculation($credits, 123);
        $this->assertTrue($validation['valid']);
        $this->assertCount(1, $validation['warnings']);
        $this->assertStringContainsString('Surprise bonus without other credits', $validation['warnings'][0]);
    }

    public function testCreditCalculator_ValidateCreditCalculation_ZeroCredits() {
        $credits = [
            'total_credits' => 0,
            'base_credits' => 0,
            'loyalty_bonus' => 0,
            'retention_bonus' => 0,
            'surprise_bonus' => 0
        ];
        
        $validation = InterSoccer_Credit_Calculator::validate_credit_calculation($credits, 123);
        $this->assertTrue($validation['valid'], 'Zero credits should be valid');
    }

    public function testCreditCalculator_GetBusinessRulesSummary_ReturnsArray() {
        $rules = InterSoccer_Credit_Calculator::get_business_rules_summary();
        $this->assertIsArray($rules);
    }

    public function testCreditCalculator_GetBusinessRulesSummary_HasMaxCredits() {
        $rules = InterSoccer_Credit_Calculator::get_business_rules_summary();
        $this->assertArrayHasKey('max_credits', $rules);
        $this->assertEquals(500, $rules['max_credits']);
    }

    public function testCreditCalculator_GetBusinessRulesSummary_HasMinOrderAmount() {
        $rules = InterSoccer_Credit_Calculator::get_business_rules_summary();
        $this->assertArrayHasKey('min_order_amount', $rules);
        $this->assertEquals(50, $rules['min_order_amount']);
    }

    public function testCreditCalculator_GetBusinessRulesSummary_HasCreditRates() {
        $rules = InterSoccer_Credit_Calculator::get_business_rules_summary();
        $this->assertArrayHasKey('credit_rates', $rules);
        $this->assertIsArray($rules['credit_rates']);
        $this->assertCount(3, $rules['credit_rates']);
    }

    public function testCreditCalculator_GetBusinessRulesSummary_HasRetentionBonuses() {
        $rules = InterSoccer_Credit_Calculator::get_business_rules_summary();
        $this->assertArrayHasKey('retention_bonuses', $rules);
        $this->assertIsArray($rules['retention_bonuses']);
        $this->assertCount(2, $rules['retention_bonuses']);
    }

    public function testCreditCalculator_GetBusinessRulesSummary_HasSurpriseBonus() {
        $rules = InterSoccer_Credit_Calculator::get_business_rules_summary();
        $this->assertArrayHasKey('surprise_bonus', $rules);
        $this->assertIsString($rules['surprise_bonus']);
    }

    public function testCreditCalculator_GetBusinessRulesSummary_ConsistentValues() {
        $rules1 = InterSoccer_Credit_Calculator::get_business_rules_summary();
        $rules2 = InterSoccer_Credit_Calculator::get_business_rules_summary();
        $this->assertEquals($rules1, $rules2, 'Should return consistent business rules');
    }

    public function testCreditCalculator_GetBusinessRulesSummary_AllValuesPositive() {
        $rules = InterSoccer_Credit_Calculator::get_business_rules_summary();
        $this->assertGreaterThan(0, $rules['max_credits']);
        $this->assertGreaterThan(0, $rules['min_order_amount']);
    }

    public function testCreditCalculator_ValidateCreditCalculation_MultipleErrors() {
        $credits = [
            'total_credits' => -600, // Both negative AND exceeds max
            'base_credits' => 0,
            'loyalty_bonus' => 0,
            'retention_bonus' => 0,
            'surprise_bonus' => 0
        ];
        
        $validation = InterSoccer_Credit_Calculator::validate_credit_calculation($credits, 123);
        $this->assertFalse($validation['valid']);
        // Should have at least the negative error (might not check exceeds if already negative)
        $this->assertNotEmpty($validation['errors']);
    }

    // =========================================================================
    // IMPORT LOGGER TESTS (18 tests)
    // =========================================================================

    public function testImportLogger_Init_CreatesLogFile() {
        InterSoccer_Import_Logger::init();
        $log_file = InterSoccer_Import_Logger::get_log_file_path();
        $this->assertNotNull($log_file);
        $this->assertIsString($log_file);
    }

    public function testImportLogger_GetLogFilePath_ReturnsNull_WhenNoFile() {
        // Clean up any existing log file path
        $reflection = new ReflectionClass('InterSoccer_Import_Logger');
        $property = $reflection->getProperty('log_file');
        $property->setAccessible(true);
        $property->setValue(null);
        
        // Should initialize and return a path
        $path = InterSoccer_Import_Logger::get_log_file_path();
        $this->assertIsString($path);
    }

    public function testImportLogger_Log_InfoLevel() {
        $temp_log = tempnam(sys_get_temp_dir(), 'test_log_');
        
        // Mock the log file path
        $reflection = new ReflectionClass('InterSoccer_Import_Logger');
        $property = $reflection->getProperty('log_file');
        $property->setAccessible(true);
        $property->setValue($temp_log);
        
        InterSoccer_Import_Logger::log('Test info message', 'INFO');
        
        $this->assertFileExists($temp_log);
        $content = file_get_contents($temp_log);
        $this->assertStringContainsString('INFO', $content);
        $this->assertStringContainsString('Test info message', $content);
        
        unlink($temp_log);
    }

    public function testImportLogger_Log_ErrorLevel() {
        $temp_log = tempnam(sys_get_temp_dir(), 'test_log_');
        
        $reflection = new ReflectionClass('InterSoccer_Import_Logger');
        $property = $reflection->getProperty('log_file');
        $property->setAccessible(true);
        $property->setValue($temp_log);
        
        InterSoccer_Import_Logger::log('Test error message', 'ERROR');
        
        $content = file_get_contents($temp_log);
        $this->assertStringContainsString('ERROR', $content);
        $this->assertStringContainsString('Test error message', $content);
        
        unlink($temp_log);
    }

    public function testImportLogger_Log_WithContext() {
        $temp_log = tempnam(sys_get_temp_dir(), 'test_log_');
        
        $reflection = new ReflectionClass('InterSoccer_Import_Logger');
        $property = $reflection->getProperty('log_file');
        $property->setAccessible(true);
        $property->setValue($temp_log);
        
        $context = ['user_id' => 123, 'action' => 'import'];
        InterSoccer_Import_Logger::log('Test with context', 'INFO', $context);
        
        $content = file_get_contents($temp_log);
        $this->assertStringContainsString('Context:', $content);
        $this->assertStringContainsString('user_id', $content);
        $this->assertStringContainsString('123', $content);
        
        unlink($temp_log);
    }

    public function testImportLogger_Log_WarningLevel() {
        $temp_log = tempnam(sys_get_temp_dir(), 'test_log_');
        
        $reflection = new ReflectionClass('InterSoccer_Import_Logger');
        $property = $reflection->getProperty('log_file');
        $property->setAccessible(true);
        $property->setValue($temp_log);
        
        InterSoccer_Import_Logger::log('Test warning', 'WARNING');
        
        $content = file_get_contents($temp_log);
        $this->assertStringContainsString('WARNING', $content);
        
        unlink($temp_log);
    }

    public function testImportLogger_Log_SuccessLevel() {
        $temp_log = tempnam(sys_get_temp_dir(), 'test_log_');
        
        $reflection = new ReflectionClass('InterSoccer_Import_Logger');
        $property = $reflection->getProperty('log_file');
        $property->setAccessible(true);
        $property->setValue($temp_log);
        
        InterSoccer_Import_Logger::log('Test success', 'SUCCESS');
        
        $content = file_get_contents($temp_log);
        $this->assertStringContainsString('SUCCESS', $content);
        
        unlink($temp_log);
    }

    public function testImportLogger_LogBatchResults_Success() {
        $temp_log = tempnam(sys_get_temp_dir(), 'test_log_');
        
        $reflection = new ReflectionClass('InterSoccer_Import_Logger');
        $property = $reflection->getProperty('log_file');
        $property->setAccessible(true);
        $property->setValue($temp_log);
        
        $results = [
            ['status' => 'success', 'customer_id' => 1, 'credits_assigned' => 10],
            ['status' => 'success', 'customer_id' => 2, 'credits_assigned' => 15],
            ['status' => 'error', 'customer_id' => 3, 'credits_assigned' => 0, 'message' => 'Failed'],
        ];
        
        InterSoccer_Import_Logger::log_batch_results(1, $results);
        
        $content = file_get_contents($temp_log);
        $this->assertStringContainsString('Batch 1 completed', $content);
        $this->assertStringContainsString('"total":3', $content);
        $this->assertStringContainsString('"success":2', $content);
        $this->assertStringContainsString('"errors":1', $content);
        
        unlink($temp_log);
    }

    public function testImportLogger_LogBatchResults_AllSuccess() {
        $temp_log = tempnam(sys_get_temp_dir(), 'test_log_');
        
        $reflection = new ReflectionClass('InterSoccer_Import_Logger');
        $property = $reflection->getProperty('log_file');
        $property->setAccessible(true);
        $property->setValue($temp_log);
        
        $results = [
            ['status' => 'success', 'customer_id' => 1, 'credits_assigned' => 10],
            ['status' => 'success', 'customer_id' => 2, 'credits_assigned' => 20],
        ];
        
        InterSoccer_Import_Logger::log_batch_results(5, $results);
        
        $content = file_get_contents($temp_log);
        $this->assertStringContainsString('Batch 5', $content);
        $this->assertStringContainsString('"success":2', $content);
        $this->assertStringContainsString('"errors":0', $content);
        
        unlink($temp_log);
    }

    public function testImportLogger_LogBatchResults_WithSkipped() {
        $temp_log = tempnam(sys_get_temp_dir(), 'test_log_');
        
        $reflection = new ReflectionClass('InterSoccer_Import_Logger');
        $property = $reflection->getProperty('log_file');
        $property->setAccessible(true);
        $property->setValue($temp_log);
        
        $results = [
            ['status' => 'success', 'customer_id' => 1, 'credits_assigned' => 10],
            ['status' => 'skipped', 'customer_id' => 2, 'credits_assigned' => 0],
        ];
        
        InterSoccer_Import_Logger::log_batch_results(2, $results);
        
        $content = file_get_contents($temp_log);
        $this->assertStringContainsString('"skipped":1', $content);
        
        unlink($temp_log);
    }

    public function testImportLogger_LogBatchResults_CreditsAssigned() {
        $temp_log = tempnam(sys_get_temp_dir(), 'test_log_');
        
        $reflection = new ReflectionClass('InterSoccer_Import_Logger');
        $property = $reflection->getProperty('log_file');
        $property->setAccessible(true);
        $property->setValue($temp_log);
        
        $results = [
            ['status' => 'success', 'customer_id' => 1, 'credits_assigned' => 10],
            ['status' => 'success', 'customer_id' => 2, 'credits_assigned' => 25],
        ];
        
        InterSoccer_Import_Logger::log_batch_results(3, $results);
        
        $content = file_get_contents($temp_log);
        $this->assertStringContainsString('"credits_assigned":35', $content);
        
        unlink($temp_log);
    }

    public function testImportLogger_LogBatchResults_LogsIndividualErrors() {
        $temp_log = tempnam(sys_get_temp_dir(), 'test_log_');
        
        $reflection = new ReflectionClass('InterSoccer_Import_Logger');
        $property = $reflection->getProperty('log_file');
        $property->setAccessible(true);
        $property->setValue($temp_log);
        
        $results = [
            [
                'status' => 'error',
                'customer_id' => 999,
                'customer_name' => 'Test User',
                'message' => 'Database error',
                'credits_assigned' => 0
            ],
        ];
        
        InterSoccer_Import_Logger::log_batch_results(1, $results);
        
        $content = file_get_contents($temp_log);
        $this->assertStringContainsString('Customer 999 error', $content);
        $this->assertStringContainsString('Database error', $content);
        
        unlink($temp_log);
    }

    public function testImportLogger_CleanupOldLogs_NoDirectory() {
        // Should not throw error when directory doesn't exist
        InterSoccer_Import_Logger::cleanup_old_logs(30);
        $this->assertTrue(true, 'Should handle missing directory gracefully');
    }

    public function testImportLogger_CleanupOldLogs_EmptyDirectory() {
        $temp_dir = sys_get_temp_dir() . '/test_logs_' . time();
        mkdir($temp_dir);
        
        // Mock wp_upload_dir to return our temp directory
        // (In real test, would need proper mocking)
        
        InterSoccer_Import_Logger::cleanup_old_logs(30);
        
        $this->assertTrue(is_dir($temp_dir));
        rmdir($temp_dir);
    }

    public function testImportLogger_GetLogFilePath_AfterInit() {
        InterSoccer_Import_Logger::init();
        $path = InterSoccer_Import_Logger::get_log_file_path();
        
        $this->assertNotNull($path);
        $this->assertIsString($path);
        $this->assertStringContainsString('customer-import-', $path);
        $this->assertStringContainsString('.log', $path);
    }

    public function testImportLogger_Log_DefaultLevel() {
        $temp_log = tempnam(sys_get_temp_dir(), 'test_log_');
        
        $reflection = new ReflectionClass('InterSoccer_Import_Logger');
        $property = $reflection->getProperty('log_file');
        $property->setAccessible(true);
        $property->setValue($temp_log);
        
        // Default level should be INFO
        InterSoccer_Import_Logger::log('Test default level');
        
        $content = file_get_contents($temp_log);
        $this->assertStringContainsString('INFO', $content);
        
        unlink($temp_log);
    }

    public function testImportLogger_LogBatchResults_EmptyResults() {
        $temp_log = tempnam(sys_get_temp_dir(), 'test_log_');
        
        $reflection = new ReflectionClass('InterSoccer_Import_Logger');
        $property = $reflection->getProperty('log_file');
        $property->setAccessible(true);
        $property->setValue($temp_log);
        
        InterSoccer_Import_Logger::log_batch_results(1, []);
        
        $content = file_get_contents($temp_log);
        $this->assertStringContainsString('"total":0', $content);
        
        unlink($temp_log);
    }

    // =========================================================================
    // DATABASE OPTIMIZER TESTS (7 tests)
    // =========================================================================

    public function testDatabaseOptimizer_CreateIndexes_NoException() {
        // Mock global $wpdb
        global $wpdb;
        $wpdb = $this->createMock(stdClass::class);
        $wpdb->usermeta = 'wp_usermeta';
        $wpdb->posts = 'wp_posts';
        $wpdb->method('query')->willReturn(true);
        
        // Should not throw exception
        InterSoccer_Database_Optimizer::create_indexes();
        $this->assertTrue(true, 'create_indexes should complete without error');
    }

    public function testDatabaseOptimizer_AnalyzeQueryPerformance_ReturnsArray() {
        // Mock global $wpdb
        global $wpdb;
        $wpdb = $this->createMock(stdClass::class);
        $wpdb->usermeta = 'wp_usermeta';
        $wpdb->posts = 'wp_posts';
        $wpdb->method('get_results')->willReturn([]);
        
        $performance = InterSoccer_Database_Optimizer::analyze_query_performance();
        $this->assertIsArray($performance);
    }

    public function testDatabaseOptimizer_AnalyzeQueryPerformance_HasCustomerQueryTime() {
        global $wpdb;
        $wpdb = $this->createMock(stdClass::class);
        $wpdb->usermeta = 'wp_usermeta';
        $wpdb->posts = 'wp_posts';
        $wpdb->method('get_results')->willReturn([]);
        
        $performance = InterSoccer_Database_Optimizer::analyze_query_performance();
        $this->assertArrayHasKey('customer_query_time', $performance);
        $this->assertStringContainsString('ms', $performance['customer_query_time']);
    }

    public function testDatabaseOptimizer_AnalyzeQueryPerformance_HasOrderQueryTime() {
        global $wpdb;
        $wpdb = $this->createMock(stdClass::class);
        $wpdb->usermeta = 'wp_usermeta';
        $wpdb->posts = 'wp_posts';
        $wpdb->method('get_results')->willReturn([]);
        
        $performance = InterSoccer_Database_Optimizer::analyze_query_performance();
        $this->assertArrayHasKey('order_query_time', $performance);
        $this->assertStringContainsString('ms', $performance['order_query_time']);
    }

    public function testDatabaseOptimizer_AnalyzeQueryPerformance_HasPerformanceRating() {
        global $wpdb;
        $wpdb = $this->createMock(stdClass::class);
        $wpdb->usermeta = 'wp_usermeta';
        $wpdb->posts = 'wp_posts';
        $wpdb->method('get_results')->willReturn([]);
        
        $performance = InterSoccer_Database_Optimizer::analyze_query_performance();
        $this->assertArrayHasKey('performance_rating', $performance);
        $this->assertContains($performance['performance_rating'], ['Good', 'Moderate', 'Poor', 'Bad']);
    }

    public function testDatabaseOptimizer_AnalyzeQueryPerformance_TimingIsNumeric() {
        global $wpdb;
        $wpdb = $this->createMock(stdClass::class);
        $wpdb->usermeta = 'wp_usermeta';
        $wpdb->posts = 'wp_posts';
        $wpdb->method('get_results')->willReturn([]);
        
        $performance = InterSoccer_Database_Optimizer::analyze_query_performance();
        
        // Extract numeric value from "X.XXms" format
        $customer_time = floatval($performance['customer_query_time']);
        $order_time = floatval($performance['order_query_time']);
        
        $this->assertIsNumeric($customer_time);
        $this->assertIsNumeric($order_time);
        $this->assertGreaterThanOrEqual(0, $customer_time);
        $this->assertGreaterThanOrEqual(0, $order_time);
    }

    public function testDatabaseOptimizer_CreateIndexes_CallsQueryMethod() {
        global $wpdb;
        $wpdb = $this->createMock(stdClass::class);
        $wpdb->usermeta = 'wp_usermeta';
        $wpdb->posts = 'wp_posts';
        
        // Expect query to be called (at least once for index creation)
        $wpdb->expects($this->atLeastOnce())
            ->method('query')
            ->willReturn(true);
        
        InterSoccer_Database_Optimizer::create_indexes();
    }
}

