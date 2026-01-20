# Testing Guide - Customer Referral System

This document describes all testing procedures for the InterSoccer Customer Referral System, including PHPUnit tests, Cypress E2E tests, and deployment verification.

---

## 📋 Table of Contents

1. [PHPUnit Tests (Backend)](#phpunit-tests-backend)
2. [Cypress Tests (Frontend E2E)](#cypress-tests-frontend-e2e)
3. [Phase 0 Critical Tests](#phase-0-critical-tests)
4. [Running Tests Before Deployment](#running-tests-before-deployment)
5. [Test Coverage](#test-coverage)
6. [Writing New Tests](#writing-new-tests)
7. [Troubleshooting](#troubleshooting)

---

## PHPUnit Tests (Backend)

### Setup

1. **Install Dependencies:**
   ```bash
   composer install
   ```

2. **Configure WordPress Test Suite:**
   ```bash
   # Set up WordPress test database
   ./bin/install-wp-tests.sh wordpress_test root '' localhost latest
   ```

3. **Update `tests/bootstrap.php`:**
   ```php
   define('WP_TESTS_DIR', '/path/to/wordpress-tests-lib');
   ```

### Running Tests

#### Run All Tests:
```bash
vendor/bin/phpunit
```

#### Run Specific Test File:
```bash
vendor/bin/phpunit tests/PointsManagerTest.php
vendor/bin/phpunit tests/PointsMigrationIntegersTest.php
```

#### Run with Coverage:
```bash
vendor/bin/phpunit --coverage-html coverage/
```

#### Run with Verbose Output:
```bash
vendor/bin/phpunit --testdox --colors=always
```

### Test Files

| Test File | Purpose | Critical |
|-----------|---------|----------|
| `PointsManagerTest.php` | Validates integer-only points calculations | ✅ YES |
| `PointsMigrationIntegersTest.php` | Validates database migration safety | ✅ YES |
| `CommissionManagerTest.php` | Tests commission calculations | YES |
| `ReferralHandlerTest.php` | Tests referral code logic | YES |
| `UserRoleTest.php` | Tests role-based permissions | NO |

---

## Cypress Tests (Frontend E2E)

### Location
Cypress tests are maintained in a separate repository:
- **Repository:** `intersoccer-player-management-tests`
- **Path:** `../intersoccer-player-management-tests/cypress/e2e/`

### Setup

1. **Navigate to Test Repository:**
   ```bash
   cd ../intersoccer-player-management-tests
   ```

2. **Install Dependencies:**
   ```bash
   npm install
   ```

3. **Configure Base URL:**
   Edit `cypress.config.js`:
   ```javascript
   baseUrl: 'https://intersoccer.legit.ninja'
   ```

### Running Cypress Tests

#### Interactive Mode (Development):
```bash
npm run cypress:open
```

#### Headless Mode (CI/CD):
```bash
npm test
```

#### Run Specific Tests:
```bash
# Referral system tests
npm test -- --spec 'cypress/e2e/referral-system/**'

# Points redemption tests
npm test -- --spec 'cypress/e2e/points-redemption/**'

# Checkout flow tests
npm test -- --spec 'cypress/e2e/checkout/**'
```

### Phase 0 Cypress Tests (Recommended)

Create these tests in `intersoccer-player-management-tests` repository:

#### 1. Points Display Test (`cypress/e2e/points/integer-points-display.cy.js`)
```javascript
describe('Points Display - Integer Only', () => {
  it('should display points as integers (no decimals)', () => {
    cy.login('customer@example.com', 'password')
    cy.visit('/my-account/')
    cy.get('.points-balance').should('not.contain', '.')
    cy.get('.points-balance').invoke('text').then((text) => {
      const points = parseInt(text)
      expect(points).to.be.a('number')
      expect(Number.isInteger(points)).to.be.true
    })
  })
  
  it('should calculate points correctly (95 CHF = 9 points)', () => {
    // Complete order for 95 CHF
    cy.completeOrder(95.00)
    
    // Check points awarded
    cy.visit('/my-account/')
    cy.get('.points-earned-notification').should('contain', '9 points')
  })
})
```

#### 2. Points Redemption Test (`cypress/e2e/points/points-redemption.cy.js`)
```javascript
describe('Points Redemption - Unlimited', () => {
  it('should allow redeeming all available points', () => {
    // User has 150 points
    cy.login('customer-with-points@example.com', 'password')
    
    // Add 200 CHF product to cart
    cy.addToCart('product-id-123')
    cy.visit('/checkout/')
    
    // Apply all points
    cy.get('#intersoccer_use_points').check()
    cy.get('.apply-all-points').click()
    
    // Verify all 150 points applied
    cy.get('#intersoccer_points_to_redeem').should('have.value', '150')
    cy.get('.applied-amount').should('contain', '150 points')
    
    // Verify order total reduced by 150 CHF
    cy.get('.order-total').should('contain', '50.00')
  })
})
```

#### 3. Checkout Integration Test (`cypress/e2e/checkout/phase0-checkout.cy.js`)
```javascript
describe('Phase 0 Checkout Integration', () => {
  it('should complete order with points redemption', () => {
    cy.login('customer@example.com', 'password')
    cy.addToCart('camp-registration')
    cy.visit('/checkout/')
    
    // Apply points
    cy.get('#intersoccer_use_points').check()
    cy.get('.apply-all-points').click()
    
    // Complete checkout
    cy.fillCheckoutForm()
    cy.get('#place_order').click()
    
    // Verify success
    cy.url().should('include', '/checkout/order-received/')
    cy.get('.order-confirmation').should('be.visible')
  })
})
```

---

## Phase 0 Critical Tests

### Required Tests Before Deployment

**These tests MUST pass before deploying Phase 0 changes:**

1. ✅ **PointsManagerTest.php**
   - `testCalculatePointsFromAmount()` - Verifies integer points
   - `testIntegerPointsOnly()` - Tests floor behavior
   - `testGetPointsBalance()` - Ensures integer balance

2. ✅ **PointsMigrationIntegersTest.php**
   - `testPointsConversionLogic()` - Validates floor logic
   - `testDataIntegrity()` - Ensures no data loss
   - `testEdgeCasesConversion()` - Tests edge cases

3. 🔶 **Cypress Points Tests** (Recommended)
   - Integer points display
   - Points redemption flow
   - Order completion with points

---

## Running Tests Before Deployment

### Using deploy.sh (Recommended)

#### Deploy with Tests:
```bash
./deploy.sh --test
```

This will:
1. Run Phase 0 critical PHPUnit tests first
2. Run full PHPUnit test suite
3. Show Cypress test reminder
4. Deploy only if all tests pass

#### Deploy without Tests (Not Recommended):
```bash
./deploy.sh
```

⚠️ **WARNING:** You will see a 10-second warning before deployment proceeds.

### Manual Testing Sequence

1. **Run PHPUnit Tests:**
   ```bash
   vendor/bin/phpunit --colors=always
   ```

2. **Run Specific Phase 0 Tests:**
   ```bash
   vendor/bin/phpunit tests/PointsManagerTest.php
   vendor/bin/phpunit tests/PointsMigrationIntegersTest.php
   ```

3. **Run Cypress Tests:**
   ```bash
   cd ../intersoccer-player-management-tests
   npm test -- --spec 'cypress/e2e/points/**'
   ```

4. **Deploy:**
   ```bash
   ./deploy.sh
   ```

---

## Test Coverage

### Current Coverage (Phase 0)

#### PHPUnit:
- **PointsManagerTest.php:** 15 test methods, ~95% coverage
  - Points calculation logic
  - Balance management
  - Transaction handling
  - Redemption validation
  - Statistics generation

- **PointsMigrationIntegersTest.php:** 8 test methods
  - Migration status checks
  - Conversion logic
  - Data integrity
  - Rollback functionality

#### Cypress (Recommended):
- **Points Display:** 0% (TODO)
- **Points Redemption:** 0% (TODO)
- **Checkout Integration:** 0% (TODO)

### Coverage Goals

- **Backend (PHPUnit):** 80%+ coverage
- **Frontend (Cypress):** Cover all critical user flows
- **Integration:** Test all Phase 0 changes end-to-end

---

## Writing New Tests

### PHPUnit Test Template

```php
<?php
use PHPUnit\Framework\TestCase;

class YourFeatureTest extends TestCase {
    
    protected function setUp(): void {
        // Setup code
        require_once __DIR__ . '/../includes/class-your-feature.php';
    }
    
    public function testYourFeature() {
        // Arrange
        $feature = new YourFeature();
        
        // Act
        $result = $feature->doSomething();
        
        // Assert
        $this->assertEquals('expected', $result);
    }
    
    protected function tearDown(): void {
        // Cleanup
    }
}
```

### Cypress Test Template

```javascript
describe('Your Feature', () => {
  beforeEach(() => {
    cy.login('user@example.com', 'password')
  })
  
  it('should do something', () => {
    // Arrange
    cy.visit('/page/')
    
    // Act
    cy.get('.button').click()
    
    // Assert
    cy.get('.result').should('contain', 'Success')
  })
})
```

### Test Naming Conventions

- **PHPUnit:** `testFeatureNameDoesWhat()`
- **Cypress:** `should do what when condition`
- Be descriptive and specific
- Test one thing per test method

---

## Troubleshooting

### PHPUnit Issues

#### "Class not found" errors:
```bash
composer dump-autoload
```

#### "WordPress not found" errors:
Update `tests/bootstrap.php` with correct WordPress test suite path.

#### Tests hanging:
Check for infinite loops or database connection issues.

### Cypress Issues

#### "baseUrl not configured":
Set `baseUrl` in `cypress.config.js`.

#### "cy.login is not a function":
Add custom commands in `cypress/support/commands.js`.

#### Tests fail on CI but pass locally:
- Check timing issues (add `cy.wait()` where needed)
- Verify test data exists on CI environment
- Check for race conditions

### Deployment Issues

#### Tests pass but deployment fails:
- Check SSH credentials in `deploy.local.sh`
- Verify server path is correct
- Check file permissions on server

#### Deployment succeeds but site broken:
- Run rollback: `git checkout HEAD~1`
- Check error logs: `ssh user@server "tail -f /path/to/error.log"`
- Verify database migration completed successfully

---

## CI/CD Integration (Future)

### GitHub Actions (Planned)

```yaml
name: Tests

on: [push, pull_request]

jobs:
  phpunit:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Install dependencies
        run: composer install
      - name: Run PHPUnit
        run: vendor/bin/phpunit
  
  cypress:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Cypress run
        uses: cypress-io/github-action@v4
        with:
          working-directory: ../intersoccer-player-management-tests
```

---

## Quick Reference

### Commands

```bash
# PHPUnit
vendor/bin/phpunit                          # All tests
vendor/bin/phpunit tests/PointsManagerTest.php  # Specific test
vendor/bin/phpunit --testdox                # Readable output
vendor/bin/phpunit --coverage-html coverage/    # Coverage report

# Cypress (from intersoccer-player-management-tests/)
npm run cypress:open                        # Interactive
npm test                                    # Headless
npm test -- --spec 'cypress/e2e/points/**' # Specific tests

# Deployment
./deploy.sh --test                          # Deploy with tests (RECOMMENDED)
./deploy.sh --dry-run                       # Preview deployment
./deploy.sh --test --clear-cache            # Deploy, test, clear cache
```

### Test Status Indicators

- ✅ **PASS** - Test passed
- ❌ **FAIL** - Test failed (blocks deployment)
- ⚠️ **SKIP** - Test skipped (check configuration)
- 🔶 **TODO** - Test not yet implemented

---

## Support

### Getting Help

1. **Check test output** for specific error messages
2. **Review this documentation** for common issues
3. **Check debug logs** in `debug.log`
4. **Ask team** in development channel

### Useful Links

- PHPUnit Documentation: https://phpunit.de/documentation.html
- Cypress Documentation: https://docs.cypress.io
- WordPress Test Suite: https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/

---

**Last Updated:** November 4, 2025  
**Phase:** Phase 0 Implementation  
**Version:** 1.0

