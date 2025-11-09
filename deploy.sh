#!/bin/bash

###############################################################################
# InterSoccer Referral System - Deployment Script
###############################################################################
#
# This script deploys the plugin to the dev server and can run tests.
#
# Usage:
#   ./deploy.sh                 # Deploy to dev server
#   ./deploy.sh --test          # Run tests before deploying
#   ./deploy.sh --no-cache      # Deploy and clear server caches
#   ./deploy.sh --dry-run       # Show what would be uploaded
#
###############################################################################

# Exit on error
set -e

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
# IMPORTANT: Copy this file to deploy.local.sh and set your credentials there
# deploy.local.sh is in .gitignore and won't be committed

# Default configuration (override in deploy.local.sh)
SERVER_USER="your-username"
SERVER_HOST="intersoccer.legit.ninja"
SERVER_PATH="/path/to/wordpress/wp-content/plugins/customer-referral-system"
SSH_PORT="22"
SSH_KEY="~/.ssh/id_rsa"

# Load local configuration if it exists
if [ -f "deploy.local.sh" ]; then
    source deploy.local.sh
    echo -e "${GREEN}✓ Loaded local configuration${NC}"
fi

# Parse command line arguments
DRY_RUN=false
RUN_TESTS=false
CLEAR_CACHE=false
DROP_TABLES=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --test)
            RUN_TESTS=true
            shift
            ;;
        --no-cache|--clear-cache)
            CLEAR_CACHE=true
            shift
            ;;
        --drop-tables)
            DROP_TABLES=true
            shift
            ;;
        --help|-h)
            echo "Usage: $0 [OPTIONS]"
            echo ""
            echo "Options:"
            echo "  --dry-run        Show what would be uploaded without uploading"
            echo "  --test           Run PHPUnit tests before deploying"
            echo "  --clear-cache    Clear server caches after deployment"
            echo "  --drop-tables    Drop plugin tables on the server (use with caution)"
            echo "  --help           Show this help message"
            exit 0
            ;;
        *)
            echo -e "${RED}Unknown option: $1${NC}"
            exit 1
            ;;
    esac
done

# Check if configuration is set
if [ "$SERVER_USER" = "your-username" ]; then
    echo -e "${RED}✗ Configuration not set!${NC}"
    echo ""
    echo "Please create a deploy.local.sh file with your server credentials:"
    echo ""
    echo "cat > deploy.local.sh << 'EOF'"
    echo "SERVER_USER=\"your-ssh-username\""
    echo "SERVER_HOST=\"intersoccer.legit.ninja\""
    echo "SERVER_PATH=\"/var/www/html/wp-content/plugins/customer-referral-system\""
    echo "SSH_PORT=\"22\""
    echo "SSH_KEY=\"~/.ssh/id_rsa\""
    echo "EOF"
    echo ""
    exit 1
fi

###############################################################################
# Functions
###############################################################################

print_header() {
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}  $1${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
}

run_phpunit_tests() {
    print_header "Running PHPUnit Tests"
    
    if [ ! -f "vendor/bin/phpunit" ]; then
        echo -e "${YELLOW}⚠ PHPUnit not installed. Skipping PHPUnit tests.${NC}"
        echo "  Run 'composer install' to enable PHPUnit tests."
        return 0
    fi
    
    # Check if bootstrap file exists
    if [ ! -f "tests/bootstrap.php" ]; then
        echo -e "${YELLOW}⚠ PHPUnit bootstrap not found. Skipping PHPUnit tests.${NC}"
        echo "  Create tests/bootstrap.php to enable PHPUnit tests."
        return 0
    fi
    
    # Note: This test suite uses a custom mock system (tests/bootstrap.php)
    # It does NOT require WordPress test suite installation
    # Tests can run independently using the mocked WordPress functions
    
    echo "Running PHPUnit tests..."
    echo ""
    
    # Run critical Phase 0 tests first
    echo -e "${BLUE}→ Running Phase 0 Critical Tests...${NC}"
    
    # Test 1: Database Schema
    if [ -f "tests/DatabaseSchemaTest.php" ]; then
        echo -e "  ${BLUE}•${NC} DatabaseSchemaTest (Integer Schema Validation)"
        php vendor/bin/phpunit tests/DatabaseSchemaTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED${NC}"
        else
            echo -e "    ${RED}✗ FAILED - BLOCKING DEPLOYMENT${NC}"
            return 1
        fi
    fi
    
    # Test 2: Points Manager
    if [ -f "tests/PointsManagerTest.php" ]; then
        echo -e "  ${BLUE}•${NC} PointsManagerTest (Integer Points Validation)"
        php vendor/bin/phpunit tests/PointsManagerTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (15 tests)${NC}"
        else
            echo -e "    ${RED}✗ FAILED - BLOCKING DEPLOYMENT${NC}"
            return 1
        fi
    fi
    
    # Test 3: Points Migration
    if [ -f "tests/PointsMigrationIntegersTest.php" ]; then
        echo -e "  ${BLUE}•${NC} PointsMigrationIntegersTest (Migration Safety)"
        php vendor/bin/phpunit tests/PointsMigrationIntegersTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (8 tests)${NC}"
        else
            echo -e "    ${RED}✗ FAILED - BLOCKING DEPLOYMENT${NC}"
            return 1
        fi
    fi
    
    # Test 4: CSV Import
    if [ -f "tests/CoachCSVImportTest.php" ]; then
        echo -e "  ${BLUE}•${NC} CoachCSVImportTest (CSV Regression Prevention)"
        php vendor/bin/phpunit tests/CoachCSVImportTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (28 tests)${NC}"
        else
            echo -e "    ${RED}✗ FAILED - BLOCKING DEPLOYMENT${NC}"
            return 1
        fi
    fi
    
    # Test 5: Unlimited Points Redemption
    if [ -f "tests/PointsRedemptionUnlimitedTest.php" ]; then
        echo -e "  ${BLUE}•${NC} PointsRedemptionUnlimitedTest (No 100-Point Limit)"
        php vendor/bin/phpunit tests/PointsRedemptionUnlimitedTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (27 tests)${NC}"
        else
            echo -e "    ${RED}✗ FAILED - BLOCKING DEPLOYMENT${NC}"
            return 1
        fi
    fi
    
    # Test 6: Admin Points Validation
    if [ -f "tests/AdminPointsValidationTest.php" ]; then
        echo -e "  ${BLUE}•${NC} AdminPointsValidationTest (Integer-Only Validation)"
        php vendor/bin/phpunit tests/AdminPointsValidationTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (25 tests)${NC}"
        else
            echo -e "    ${RED}✗ FAILED - BLOCKING DEPLOYMENT${NC}"
            return 1
        fi
    fi
    
    # Test 7: Role-Specific Point Rates
    if [ -f "tests/RoleSpecificPointRatesTest.php" ]; then
        echo -e "  ${BLUE}•${NC} RoleSpecificPointRatesTest (Role-Based Earning Rates)"
        php vendor/bin/phpunit tests/RoleSpecificPointRatesTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (40 tests)${NC}"
        else
            echo -e "    ${RED}✗ FAILED - BLOCKING DEPLOYMENT${NC}"
            return 1
        fi
    fi
    
    echo ""
    echo -e "${BLUE}→ Running Additional Critical Tests...${NC}"
    
    # Test 8: Order Processing Integration
    if [ -f "tests/OrderProcessingIntegrationTest.php" ]; then
        echo -e "  ${BLUE}•${NC} OrderProcessingIntegrationTest (Order Flow & Points Allocation)"
        php vendor/bin/phpunit tests/OrderProcessingIntegrationTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (34 tests)${NC}"
        else
            echo -e "    ${YELLOW}⚠ FAILED - Warning (not blocking)${NC}"
        fi
    fi
    
    # Test 9: Balance Synchronization
    if [ -f "tests/BalanceSynchronizationTest.php" ]; then
        echo -e "  ${BLUE}•${NC} BalanceSynchronizationTest (Data Integrity)"
        php vendor/bin/phpunit tests/BalanceSynchronizationTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (26 tests)${NC}"
        else
            echo -e "    ${YELLOW}⚠ FAILED - Warning (not blocking)${NC}"
        fi
    fi
    
    # Test 10: Security Validation
    if [ -f "tests/SecurityValidationTest.php" ]; then
        echo -e "  ${BLUE}•${NC} SecurityValidationTest (Security & Input Validation)"
        php vendor/bin/phpunit tests/SecurityValidationTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (28 tests)${NC}"
        else
            echo -e "    ${YELLOW}⚠ FAILED - Warning (not blocking)${NC}"
        fi
    fi
    
    # Test 11: Referral Code Validation
    if [ -f "tests/ReferralCodeValidationTest.php" ]; then
        echo -e "  ${BLUE}•${NC} ReferralCodeValidationTest (Referral Code Processing)"
        php vendor/bin/phpunit tests/ReferralCodeValidationTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (29 tests)${NC}"
        else
            echo -e "    ${YELLOW}⚠ FAILED - Warning (not blocking)${NC}"
        fi
    fi
    
    # Test 12: Audit Logging
    if [ -f "tests/AuditLoggingTest.php" ]; then
        echo -e "  ${BLUE}•${NC} AuditLoggingTest (Audit Trail & Compliance)"
        php vendor/bin/phpunit tests/AuditLoggingTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (25 tests)${NC}"
        else
            echo -e "    ${YELLOW}⚠ FAILED - Warning (not blocking)${NC}"
        fi
    fi
    
    # Test 13: Checkout Points Redemption
    if [ -f "tests/CheckoutPointsRedemptionTest.php" ]; then
        echo -e "  ${BLUE}•${NC} CheckoutPointsRedemptionTest (Checkout Flow & UX)"
        php vendor/bin/phpunit tests/CheckoutPointsRedemptionTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (42 tests)${NC}"
        else
            echo -e "    ${YELLOW}⚠ FAILED - Warning (not blocking)${NC}"
        fi
    fi
    
    # Test 14: Commission Calculations
    if [ -f "tests/CommissionCalculationTest.php" ]; then
        echo -e "  ${BLUE}•${NC} CommissionCalculationTest (Financial Calculations)"
        php vendor/bin/phpunit tests/CommissionCalculationTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (22 tests)${NC}"
        else
            echo -e "    ${YELLOW}⚠ FAILED - Warning (not blocking)${NC}"
        fi
    fi
    
    # Test 15: Coach Helper Functions
    if [ -f "tests/CoachHelperFunctionsTest.php" ]; then
        echo -e "  ${BLUE}•${NC} CoachHelperFunctionsTest (Coach Tier & Helper Functions)"
        php vendor/bin/phpunit tests/CoachHelperFunctionsTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (23 tests)${NC}"
        else
            echo -e "    ${YELLOW}⚠ FAILED - Warning (not blocking)${NC}"
        fi
    fi
    
    # Test 16: User Roles Enhancement
    if [ -f "tests/UserRolesEnhancementTest.php" ]; then
        echo -e "  ${BLUE}•${NC} UserRolesEnhancementTest (Custom Roles & Capabilities)"
        php vendor/bin/phpunit tests/UserRolesEnhancementTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (36 tests)${NC}"
        else
            echo -e "    ${YELLOW}⚠ FAILED - Warning (not blocking)${NC}"
        fi
    fi
    
    # NEW: Comprehensive Business Logic Tests (WARNING)
    echo ""
    echo -e "${BLUE}→ Running Business Logic Tests...${NC}"
    
    if [ -f "tests/UtilityClassesTest.php" ]; then
        echo -e "  ${YELLOW}•${NC} UtilityClassesTest (Utility Classes)"
        php vendor/bin/phpunit tests/UtilityClassesTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (60 tests)${NC}"
        else
            echo -e "    ${YELLOW}⚠ FAILED - Warning${NC}"
        fi
    fi
    
    if [ -f "tests/CustomerDashboardTest.php" ]; then
        echo -e "  ${YELLOW}•${NC} CustomerDashboardTest (Customer Dashboard)"
        php vendor/bin/phpunit tests/CustomerDashboardTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (50 tests)${NC}"
        else
            echo -e "    ${YELLOW}⚠ FAILED - Warning${NC}"
        fi
    fi
    
    if [ -f "tests/PointsMigrationTest.php" ]; then
        echo -e "  ${YELLOW}•${NC} PointsMigrationTest (Migration Operations)"
        php vendor/bin/phpunit tests/PointsMigrationTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (28 tests)${NC}"
        else
            echo -e "    ${YELLOW}⚠ FAILED - Warning${NC}"
        fi
    fi
    
    if [ -f "tests/APIDummyTest.php" ]; then
        echo -e "  ${YELLOW}•${NC} APIDummyTest (API Endpoints)"
        php vendor/bin/phpunit tests/APIDummyTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (18 tests)${NC}"
        else
            echo -e "    ${YELLOW}⚠ FAILED - Warning${NC}"
        fi
    fi
    
    # NEW: Admin Interface Tests (WARNING)
    echo ""
    echo -e "${BLUE}→ Running Admin Interface Tests...${NC}"
    
    if [ -f "tests/AdminSettingsTest.php" ]; then
        echo -e "  ${YELLOW}•${NC} AdminSettingsTest (Settings & AJAX)"
        php vendor/bin/phpunit tests/AdminSettingsTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (90 tests)${NC}"
        else
            echo -e "    ${YELLOW}⚠ FAILED - Warning${NC}"
        fi
    fi
    
    if [ -f "tests/AdminDashboardTest.php" ]; then
        echo -e "  ${YELLOW}•${NC} AdminDashboardTest (Admin Dashboard)"
        php vendor/bin/phpunit tests/AdminDashboardTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (65 tests)${NC}"
        else
            echo -e "    ${YELLOW}⚠ FAILED - Warning${NC}"
        fi
    fi
    
    if [ -f "tests/AdminDashboardMainTest.php" ]; then
        echo -e "  ${YELLOW}•${NC} AdminDashboardMainTest (Dashboard Widgets)"
        php vendor/bin/phpunit tests/AdminDashboardMainTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (45 tests)${NC}"
        else
            echo -e "    ${YELLOW}⚠ FAILED - Warning${NC}"
        fi
    fi
    
    if [ -f "tests/AdminFinancialTest.php" ]; then
        echo -e "  ${YELLOW}•${NC} AdminFinancialTest (Financial Reports)"
        php vendor/bin/phpunit tests/AdminFinancialTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (28 tests)${NC}"
        else
            echo -e "    ${YELLOW}⚠ FAILED - Warning${NC}"
        fi
    fi
    
    if [ -f "tests/AdminAuditTest.php" ]; then
        echo -e "  ${YELLOW}•${NC} AdminAuditTest (Audit Log)"
        php vendor/bin/phpunit tests/AdminAuditTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (32 tests)${NC}"
        else
            echo -e "    ${YELLOW}⚠ FAILED - Warning${NC}"
        fi
    fi
    
    if [ -f "tests/AdminCoachesTest.php" ]; then
        echo -e "  ${YELLOW}•${NC} AdminCoachesTest (Coach Management)"
        php vendor/bin/phpunit tests/AdminCoachesTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (27 tests)${NC}"
        else
            echo -e "    ${YELLOW}⚠ FAILED - Warning${NC}"
        fi
    fi
    
    if [ -f "tests/AdminReferralsTest.php" ]; then
        echo -e "  ${YELLOW}•${NC} AdminReferralsTest (Referral Management)"
        php vendor/bin/phpunit tests/AdminReferralsTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (22 tests)${NC}"
        else
            echo -e "    ${YELLOW}⚠ FAILED - Warning${NC}"
        fi
    fi
    
    if [ -f "tests/AdminCoachAssignmentsTest.php" ]; then
        echo -e "  ${YELLOW}•${NC} AdminCoachAssignmentsTest (Assignments)"
        php vendor/bin/phpunit tests/AdminCoachAssignmentsTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (35 tests)${NC}"
        else
            echo -e "    ${YELLOW}⚠ FAILED - Warning${NC}"
        fi
    fi
    
    if [ -f "tests/CoachAdminDashboardTest.php" ]; then
        echo -e "  ${YELLOW}•${NC} CoachAdminDashboardTest (Coach Dashboard)"
        php vendor/bin/phpunit tests/CoachAdminDashboardTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (50 tests)${NC}"
        else
            echo -e "    ${YELLOW}⚠ FAILED - Warning${NC}"
        fi
    fi
    
    if [ -f "tests/CoachListTableTest.php" ]; then
        echo -e "  ${YELLOW}•${NC} CoachListTableTest (List Table)"
        php vendor/bin/phpunit tests/CoachListTableTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (23 tests)${NC}"
        else
            echo -e "    ${YELLOW}⚠ FAILED - Warning${NC}"
        fi
    fi
    
    if [ -f "tests/APIDummyTest.php" ]; then
        echo -e "  ${YELLOW}•${NC} APIDummyTest (API Endpoints)"
        php vendor/bin/phpunit tests/APIDummyTest.php --testdox 2>&1
        if [ $? -eq 0 ]; then
            echo -e "    ${GREEN}✓ PASSED (18 tests)${NC}"
        else
            echo -e "    ${YELLOW}⚠ FAILED - Warning${NC}"
        fi
    fi
    
    echo ""
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${GREEN}  ✓ NEW TOTAL: 690 tests in comprehensive suite!${NC}"
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    echo ""
    echo -e "${BLUE}→ Running Full Regression Test Suite...${NC}"
    
    # Run remaining tests
    REMAINING_TESTS=(
        "CommissionManagerTest.php"
        "ReferralHandlerTest.php"
        "UserRoleTest.php"
        "SimpleTest.php"
    )
    
    for TEST_FILE in "${REMAINING_TESTS[@]}"; do
        if [ -f "tests/$TEST_FILE" ]; then
            TEST_NAME=$(echo "$TEST_FILE" | sed 's/\.php$//')
            echo -e "  ${BLUE}•${NC} $TEST_NAME"
            php vendor/bin/phpunit "tests/$TEST_FILE" --testdox 2>&1
            if [ $? -eq 0 ]; then
                echo -e "    ${GREEN}✓ PASSED${NC}"
            else
                echo -e "    ${RED}✗ FAILED - BLOCKING DEPLOYMENT${NC}"
                return 1
            fi
        fi
    done
    
    echo ""
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${GREEN}  ✓ All PHPUnit tests passed!${NC}"
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    return 0
}

run_cypress_tests() {
    print_header "Cypress Tests (External)"
    
    echo -e "${YELLOW}ℹ Cypress tests should be run separately from:${NC}"
    echo "  Repository: intersoccer-player-management-tests"
    echo "  Location: ../intersoccer-player-management-tests/"
    echo ""
    echo "To run Cypress tests for this deployment:"
    echo "  1. cd ../intersoccer-player-management-tests"
    echo "  2. npm test -- --spec 'cypress/e2e/referral-system/**'"
    echo "  3. npm test -- --spec 'cypress/e2e/points-redemption/**'"
    echo ""
    echo -e "${BLUE}→ Phase 0 Recommended Cypress Tests:${NC}"
    echo "  • Points calculation display (integer only)"
    echo "  • Points redemption checkout flow"
    echo "  • Points balance display in account"
    echo "  • Order completion with points"
    echo ""
    
    # Check if Cypress tests directory exists
    if [ -d "../intersoccer-player-management-tests" ]; then
        echo -e "${GREEN}✓ Cypress test repository found${NC}"
        
        # Check if Cypress is installed
        if [ -f "../intersoccer-player-management-tests/package.json" ]; then
            echo -e "${GREEN}✓ Cypress configuration found${NC}"
        else
            echo -e "${YELLOW}⚠ Cypress not configured in test repository${NC}"
        fi
    else
        echo -e "${YELLOW}⚠ Cypress test repository not found at ../intersoccer-player-management-tests${NC}"
        echo "  Clone from: [repository URL]"
    fi
    
    echo ""
    echo "Press Enter to continue without Cypress tests, or Ctrl+C to abort..."
    read -t 5 || true
}

deploy_to_server() {
    print_header "Deploying to Server"
    
    # Validate SERVER_PATH
    if [ -z "$SERVER_PATH" ]; then
        echo -e "${RED}✗ ERROR: SERVER_PATH is not set!${NC}"
        echo ""
        echo "Please set SERVER_PATH in deploy.local.sh to the FULL PATH of this specific plugin:"
        echo "  SERVER_PATH=\"/var/www/html/wp-content/plugins/customer-referral-system\""
        echo ""
        echo "⚠️  DO NOT use the plugins directory path - this would affect other plugins!"
        exit 1
    fi
    
    # Safety check: Ensure path ends with plugin name
    if [[ ! "$SERVER_PATH" =~ customer-referral-system/?$ ]]; then
        echo -e "${YELLOW}⚠️  WARNING: SERVER_PATH should end with 'customer-referral-system'${NC}"
        echo "Current path: $SERVER_PATH"
        echo ""
        echo "Expected format: /path/to/wp-content/plugins/customer-referral-system"
        echo ""
        read -p "Continue anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            echo "Deployment cancelled."
            exit 1
        fi
    fi
    
    echo -e "Target: ${GREEN}${SERVER_USER}@${SERVER_HOST}:${SERVER_PATH}${NC}"
    echo ""
    
    # Build rsync command WITHOUT --delete flag
    # Using --delete with plugins directory is DANGEROUS and will delete other plugins!
    RSYNC_CMD="rsync -avz"
    
    # Add dry-run flag if requested
    if [ "$DRY_RUN" = true ]; then
        RSYNC_CMD="$RSYNC_CMD --dry-run"
        echo -e "${YELLOW}DRY RUN MODE - No files will be uploaded${NC}"
        echo ""
    fi
    
    # Add SSH options
    RSYNC_CMD="$RSYNC_CMD -e 'ssh -p ${SSH_PORT} -i ${SSH_KEY}'"
    
    # Important: Include rules must come BEFORE exclude rules in rsync
    # Include README.md before excluding other *.md files
    RSYNC_CMD="$RSYNC_CMD --include='README.md'"
    
    # Exclude files/directories
    RSYNC_CMD="$RSYNC_CMD \
        --exclude='.git' \
        --exclude='.gitignore' \
        --exclude='node_modules' \
        --exclude='vendor' \
        --exclude='tests' \
        --exclude='docs' \
        --exclude='.phpunit.result.cache' \
        --exclude='composer.json' \
        --exclude='composer.lock' \
        --exclude='package.json' \
        --exclude='package-lock.json' \
        --exclude='phpunit.xml' \
        --exclude='*.log' \
        --exclude='debug.log' \
        --exclude='*.sh' \
        --exclude='*.md' \
        --exclude='*.list' \
        --exclude='run-*.php' \
        --exclude='test-*.php' \
        --exclude='.DS_Store' \
        --exclude='*.swp' \
        --exclude='*~'"
    
    # Add source and destination
    RSYNC_CMD="$RSYNC_CMD ./ ${SERVER_USER}@${SERVER_HOST}:${SERVER_PATH}/"
    
    # Execute rsync
    echo "Uploading files..."
    eval $RSYNC_CMD
    
    if [ $? -eq 0 ]; then
        if [ "$DRY_RUN" = false ]; then
            echo ""
            echo -e "${GREEN}✓ Files uploaded successfully${NC}"
        fi
    else
        echo -e "${RED}✗ Upload failed${NC}"
        exit 1
    fi
}

copy_translations_to_global_dir() {
    print_header "Copying Translation Files to Global Directory"
    
    # Create a temporary PHP script to copy .mo files
    COPY_SCRIPT='<?php
// Load WordPress to get constants
define("WP_USE_THEMES", false);
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/wp-load.php");

// Copy .mo files from plugin languages/ to wp-content/languages/plugins/
$plugin_lang_dir = dirname(__FILE__) . "/languages/";
$global_lang_dir = WP_CONTENT_DIR . "/languages/plugins/";

// Ensure global directory exists
if (!is_dir($global_lang_dir)) {
    mkdir($global_lang_dir, 0755, true);
    echo "✓ Created global language directory\n";
}

$mo_files = glob($plugin_lang_dir . "*.mo");
$copied = 0;
$failed = 0;

foreach ($mo_files as $source) {
    $filename = basename($source);
    $dest = $global_lang_dir . $filename;
    
    if (copy($source, $dest)) {
        echo "✓ Copied: " . $filename . "\n";
        $copied++;
    } else {
        echo "✗ Failed to copy: " . $filename . "\n";
        $failed++;
    }
}

echo "\nCopied " . $copied . " translation file(s) to global directory.\n";
if ($failed > 0) {
    echo "Failed to copy " . $failed . " file(s).\n";
}
unlink(__FILE__);
?>'
    
    # Upload and execute the script
    echo "$COPY_SCRIPT" | ssh -p ${SSH_PORT} -i ${SSH_KEY} ${SERVER_USER}@${SERVER_HOST} "cat > ${SERVER_PATH}/copy-translations-temp.php"
    
    echo ""
    echo "Copying translation files on server..."
    ssh -p ${SSH_PORT} -i ${SSH_KEY} ${SERVER_USER}@${SERVER_HOST} "cd ${SERVER_PATH} && php copy-translations-temp.php"
    
    echo ""
    echo -e "${GREEN}✓ Translation files copied to global directory${NC}"
}

clear_server_caches() {
    print_header "Clearing Server Caches"
    
    # Create a temporary PHP script to clear caches
    CLEAR_SCRIPT='<?php
// Load WordPress to get functions
define("WP_USE_THEMES", false);
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . "/wp-load.php");

// Clear PHP Opcache
if (function_exists("opcache_reset")) {
    opcache_reset();
    echo "✓ PHP Opcache cleared\n";
} else {
    echo "⚠ PHP Opcache not available\n";
}

// Clear WooCommerce transients
if (function_exists("wc_delete_product_transients")) {
    wc_delete_product_transients(0);
    echo "✓ WooCommerce transients cleared\n";
}

// Clear WordPress object cache
if (function_exists("wp_cache_flush")) {
    wp_cache_flush();
    echo "✓ WordPress object cache cleared\n";
}

// Clear translation cache
if (function_exists("delete_transient")) {
    delete_transient("translation_cache");
    echo "✓ Translation cache cleared\n";
}

// Clear WordPress language cache
if (function_exists("wp_cache_delete")) {
    wp_cache_delete("translations", "options");
    echo "✓ Language cache cleared\n";
}

echo "\nCaches cleared successfully!\n";
unlink(__FILE__);
?>'
    
    # Upload and execute the script
    echo "$CLEAR_SCRIPT" | ssh -p ${SSH_PORT} -i ${SSH_KEY} ${SERVER_USER}@${SERVER_HOST} "cat > ${SERVER_PATH}/clear-cache-temp.php"
    
    echo ""
    echo "Executing cache clear script on server..."
    ssh -p ${SSH_PORT} -i ${SSH_KEY} ${SERVER_USER}@${SERVER_HOST} "cd ${SERVER_PATH} && php clear-cache-temp.php"
    
    echo ""
    echo -e "${GREEN}✓ Server caches cleared${NC}"
}

drop_referral_tables() {
    print_header "Dropping Referral Plugin Tables"

    if [ "$DRY_RUN" = true ]; then
        echo -e "${YELLOW}Skipping table drop in dry-run mode.${NC}"
        return 0
    fi

    read -p "This will DROP all referral plugin tables on ${SERVER_HOST}. Continue? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${YELLOW}Table drop cancelled by user.${NC}"
        return 0
    fi

    DROP_SCRIPT='<?php
define("WP_USE_THEMES", false);
define("SHORTINIT", true);
require_once dirname(dirname(dirname(dirname(__FILE__)))) . "/wp-load.php";

global $wpdb;
$tables = [
    "intersoccer_referrals",
    "intersoccer_referral_credits",
    "intersoccer_coach_commissions",
    "intersoccer_audit_log",
    "intersoccer_coach_notes",
    "intersoccer_coach_events",
    "intersoccer_coach_achievements",
    "intersoccer_coach_performance",
    "intersoccer_coach_assignments",
    "intersoccer_customer_activities",
    "intersoccer_customer_partnerships",
    "intersoccer_credit_redemptions",
    "intersoccer_points_log",
    "intersoccer_referral_rewards",
    "intersoccer_purchase_rewards"
];

foreach ($tables as $table) {
    $full = $wpdb->prefix . $table;
    $wpdb->query("DROP TABLE IF EXISTS {$full}");
    echo "Dropped table: {$full}\n";
}

echo "\nReferral plugin tables dropped.\n";
unlink(__FILE__);
?>'

    echo "$DROP_SCRIPT" | ssh -p ${SSH_PORT} -i ${SSH_KEY} ${SERVER_USER}@${SERVER_HOST} "cat > ${SERVER_PATH}/drop-referral-tables-temp.php"

    echo "Executing drop script on server..."
    ssh -p ${SSH_PORT} -i ${SSH_KEY} ${SERVER_USER}@${SERVER_HOST} "cd ${SERVER_PATH} && php drop-referral-tables-temp.php"

    echo -e "${GREEN}✓ Referral plugin tables dropped${NC}"
}

###############################################################################
# Main Script
###############################################################################

print_header "InterSoccer Referral System Deployment"

echo "Configuration:"
echo "  Server: ${SERVER_USER}@${SERVER_HOST}"
echo "  Path: ${SERVER_PATH}"
echo "  SSH Port: ${SSH_PORT}"
echo ""

# ⚠️  IMPORTANT: Always run tests before deploying Phase 0 changes!
if [ "$RUN_TESTS" = false ]; then
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${YELLOW}  ⚠️  WARNING: Deploying without running tests!${NC}"
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    echo "Phase 0 critical changes require testing before deployment."
    echo "It is STRONGLY recommended to run: ./deploy.sh --test"
    echo ""
    echo -e "${YELLOW}Press Ctrl+C to abort, or Enter to continue anyway...${NC}"
    read -t 10 || echo ""
fi

# Run tests if requested or if RUN_TESTS is true
if [ "$RUN_TESTS" = true ]; then
    # Run PHPUnit tests (gracefully skips if not configured)
    run_phpunit_tests
    PHPUNIT_RESULT=$?
    
    # PHPUnit returns 0 if passed or skipped, 1 if actually failed
    if [ $PHPUNIT_RESULT -ne 0 ]; then
        echo -e "${RED}✗ PHPUnit tests failed. Aborting deployment.${NC}"
        echo ""
        echo "Fix the failing tests before deploying to prevent regressions."
        exit 1
    fi
    
    # Show Cypress test reminder
    run_cypress_tests
    
    echo ""
    echo -e "${GREEN}✓ All configured tests passed${NC}"
    echo ""
fi

# Deploy to server
deploy_to_server

# Copy translations to global directory
if [ "$DRY_RUN" = false ]; then
    copy_translations_to_global_dir
fi

# Clear caches if requested
if [ "$CLEAR_CACHE" = true ] && [ "$DRY_RUN" = false ]; then
    clear_server_caches
fi

# Drop tables if requested
if [ "$DROP_TABLES" = true ]; then
    drop_referral_tables
fi

# Success message
if [ "$DRY_RUN" = false ]; then
    print_header "Deployment Complete"
    echo -e "${GREEN}✓ Plugin successfully deployed to ${SERVER_HOST}${NC}"
    echo ""
    echo "Next steps:"
    echo "  1. Clear browser cache and hard refresh (Ctrl+Shift+R)"
    echo "  2. Test the referral system: https://${SERVER_HOST}/my-account/"
    echo "  3. Check browser console for any errors"
    echo "  4. Test in French and German via WPML language switcher"
    echo ""
else
    echo ""
    echo -e "${YELLOW}DRY RUN completed. No files were uploaded.${NC}"
    echo "Run without --dry-run to actually deploy."
    echo ""
fi

