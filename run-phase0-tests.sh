#!/bin/bash

###############################################################################
# Phase 0 Critical Tests Runner
# Simplified test execution without PHPUnit complexity
###############################################################################

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}  Phase 0 Critical Tests${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Check if vendor/bin/phpunit exists
if [ ! -f "vendor/bin/phpunit" ]; then
    echo -e "${RED}✗ PHPUnit not installed${NC}"
    echo "  Run: composer install"
    exit 1
fi

# Run tests
echo -e "${GREEN}Running Phase 0 tests with PHPUnit...${NC}"
echo ""

FAILED=0

# Test 1: Database Schema
echo -e "${BLUE}→ DatabaseSchemaTest...${NC}"
php vendor/bin/phpunit tests/DatabaseSchemaTest.php --colors=never 2>&1
if [ $? -ne 0 ]; then
    echo -e "${RED}✗ DatabaseSchemaTest FAILED${NC}"
    FAILED=1
else
    echo -e "${GREEN}✓ DatabaseSchemaTest PASSED${NC}"
fi
echo ""

# Test 2: Points Manager
echo -e "${BLUE}→ PointsManagerTest...${NC}"
php vendor/bin/phpunit tests/PointsManagerTest.php --colors=never 2>&1
if [ $? -ne 0 ]; then
    echo -e "${RED}✗ PointsManagerTest FAILED${NC}"
    FAILED=1
else
    echo -e "${GREEN}✓ PointsManagerTest PASSED${NC}"
fi
echo ""

# Test 3: Points Migration
echo -e "${BLUE}→ PointsMigrationIntegersTest...${NC}"
php vendor/bin/phpunit tests/PointsMigrationIntegersTest.php --colors=never 2>&1
if [ $? -ne 0 ]; then
    echo -e "${RED}✗ PointsMigrationIntegersTest FAILED${NC}"
    FAILED=1
else
    echo -e "${GREEN}✓ PointsMigrationIntegersTest PASSED${NC}"
fi
echo ""

# Test 4: CSV Import
echo -e "${BLUE}→ CoachCSVImportTest...${NC}"
php vendor/bin/phpunit tests/CoachCSVImportTest.php --colors=never 2>&1
if [ $? -ne 0 ]; then
    echo -e "${RED}✗ CoachCSVImportTest FAILED${NC}"
    FAILED=1
else
    echo -e "${GREEN}✓ CoachCSVImportTest PASSED${NC}"
fi
echo ""

# Test 5: Unlimited Points Redemption
echo -e "${BLUE}→ PointsRedemptionUnlimitedTest...${NC}"
php vendor/bin/phpunit tests/PointsRedemptionUnlimitedTest.php --colors=never 2>&1
if [ $? -ne 0 ]; then
    echo -e "${RED}✗ PointsRedemptionUnlimitedTest FAILED${NC}"
    FAILED=1
else
    echo -e "${GREEN}✓ PointsRedemptionUnlimitedTest PASSED${NC}"
fi
echo ""

# Test 6: Admin Points Validation
echo -e "${BLUE}→ AdminPointsValidationTest...${NC}"
php vendor/bin/phpunit tests/AdminPointsValidationTest.php --colors=never 2>&1
if [ $? -ne 0 ]; then
    echo -e "${RED}✗ AdminPointsValidationTest FAILED${NC}"
    FAILED=1
else
    echo -e "${GREEN}✓ AdminPointsValidationTest PASSED${NC}"
fi
echo ""

if [ $FAILED -eq 1 ]; then
    echo -e "${RED}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${RED}  ✗ Some tests FAILED - See output above${NC}"
    echo -e "${RED}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    exit 1
else
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${GREEN}  ✓ All Phase 0 tests PASSED!${NC}"
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    exit 0
fi

