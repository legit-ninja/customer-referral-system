# 🧪 Quick Test Reference - Phase 0

## Before Every Deployment

```bash
# RECOMMENDED: Deploy with tests
./deploy.sh --test

# Run tests manually
vendor/bin/phpunit --colors=always
```

## Phase 0 Critical Tests

### Must Pass Before Deploy ✅

```bash
# Test 1: Integer Points Validation
vendor/bin/phpunit tests/PointsManagerTest.php

# Test 2: Database Migration Safety
vendor/bin/phpunit tests/PointsMigrationIntegersTest.php
```

## Quick Commands

| Command | Purpose |
|---------|---------|
| `./deploy.sh --test` | Deploy with full test suite |
| `./deploy.sh --dry-run` | Preview what will be deployed |
| `vendor/bin/phpunit` | Run all PHPUnit tests |
| `vendor/bin/phpunit --testdox` | Readable test output |

## Cypress Tests (External Repo)

```bash
cd ../intersoccer-player-management-tests
npm test -- --spec 'cypress/e2e/points/**'
```

## Test Status

✅ PointsManagerTest.php - READY  
✅ PointsMigrationIntegersTest.php - READY  
🔶 Cypress Points Tests - TODO (recommended)

## What Gets Tested

### PHPUnit (Backend)
- ✅ Points calculations use floor() → integers only
- ✅ 95 CHF = 9 points (not 9.5)
- ✅ Balance always returns integers
- ✅ Migration converts DECIMAL to INT safely
- ✅ Data integrity maintained (no loss)

### Cypress (Frontend) - Recommended
- 🔶 Points display shows integers (no decimals)
- 🔶 Points redemption uses all available
- 🔶 Checkout flow with points works

## If Tests Fail

1. **Read error message** carefully
2. **Fix the issue** - don't skip tests!
3. **Re-run tests** until all pass
4. **Then deploy** with confidence

## Emergency

If you must deploy without tests (not recommended):
```bash
./deploy.sh
# You'll get a 10-second warning
```

---

**See TESTING.md for full documentation**

