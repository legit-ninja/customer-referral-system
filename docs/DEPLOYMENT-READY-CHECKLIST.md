# 🚀 Phase 0 Deployment Readiness Checklist

**Plugin:** InterSoccer Customer Referral System  
**Phase:** Phase 0 - Points System Enhancements  
**Status:** IN PROGRESS (40% Complete)  
**Target:** Dev Server  
**Date:** November 4, 2025

---

## ✅ PRE-DEPLOYMENT CHECKLIST

### Code Changes

- [x] **PointsManager: Integer Points** 
  - ✅ Changed `calculate_points_from_amount()` to use `floor()` + `intval()`
  - ✅ Replaced 5 instances of `floatval()` with `intval()`
  - ✅ Added comprehensive PHPDoc documentation
  - ✅ All functions return integer points only

- [ ] **Database Schema Updates**
  - [ ] Update `customer-referral-system.php` lines 354, 355, 377
  - [ ] Change DECIMAL(10,2) to INT(11) for new installs
  - [ ] Test schema changes on fresh install

- [ ] **Migration Script**
  - [x] Created `class-points-migration-integers.php`
  - [x] Includes backup/rollback functionality
  - [ ] Add admin UI to run migration
  - [ ] Test migration on dev database

- [ ] **"Apply Max" Limit Removal**
  - [ ] Update 8 locations across 3 files
  - [ ] Change button text to "Apply All Available"
  - [ ] Remove 100 point constraints
  - [ ] Update translations (DE, FR)

- [ ] **Role-Specific Rates**
  - [ ] Add admin settings UI
  - [ ] Implement rate calculation logic
  - [ ] Test with different user roles

### Testing

- [x] **PHPUnit Tests**
  - ✅ Enhanced `PointsManagerTest.php` (15 test methods)
  - ✅ Created `PointsMigrationIntegersTest.php` (8 test methods)
  - ✅ All tests passing locally
  - ✅ No linting errors
  - ✅ Integrated into `deploy.sh`

- [x] **Deployment Script**
  - ✅ Updated `deploy.sh` with Phase 0 test integration
  - ✅ Tests run automatically with `--test` flag
  - ✅ Warning shown if deploying without tests
  - ✅ Phase 0 critical tests run first

- [ ] **Cypress Tests** (Recommended)
  - [ ] Create integer points display test
  - [ ] Create points redemption flow test
  - [ ] Create checkout integration test
  - [ ] Run on dev server after deployment

### Documentation

- [x] **Testing Documentation**
  - ✅ Created `TESTING.md` (comprehensive guide)
  - ✅ Created `TEST-QUICK-REFERENCE.md` (quick commands)
  - ✅ Updated `PHASE0-PROGRESS.md` (progress tracking)
  - ✅ Updated `todo.list` (task tracking)

- [ ] **User Documentation**
  - [ ] Update README.md with Phase 0 changes
  - [ ] Document integer points behavior
  - [ ] Update admin help text

### Database

- [ ] **Backup & Safety**
  - [ ] Create full database backup before deployment
  - [ ] Test rollback procedure
  - [ ] Document recovery steps
  - [ ] Verify migration on staging

---

## 🧪 TESTING COMMANDS

### Run Tests Before Deploy:
```bash
./deploy.sh --test
```

### Manual Test Sequence:
```bash
# 1. Run Phase 0 critical tests
vendor/bin/phpunit tests/PointsManagerTest.php
vendor/bin/phpunit tests/PointsMigrationIntegersTest.php

# 2. Run full test suite
vendor/bin/phpunit --colors=always

# 3. Deploy to dev
./deploy.sh --test --clear-cache
```

---

## 📊 DEPLOYMENT STATUS

### Current Progress: 40%

| Task | Status | Time Spent | Remaining |
|------|--------|------------|-----------|
| Eliminate Fractional Points | 🔄 75% | 2 hours | 1 hour |
| Remove Apply Max Limit | ⏳ 0% | 0 | 2-3 hours |
| Role-Specific Rates | ⏳ 0% | 0 | 4-6 hours |

### Files Modified: 7
- ✅ `includes/class-points-manager.php`
- ✅ `tests/PointsManagerTest.php`
- ✅ `deploy.sh`
- 🆕 `includes/class-points-migration-integers.php`
- 🆕 `tests/PointsMigrationIntegersTest.php`
- 🆕 `TESTING.md`
- 🆕 `PHASE0-PROGRESS.md`

---

## ⚠️ DEPLOYMENT BLOCKERS

### Must Complete Before Deploy:

1. ❌ **Database Schema Not Updated**
   - Schema in `customer-referral-system.php` still uses DECIMAL
   - New installs will have wrong column type
   - **Impact:** Medium (existing installs use migration)

2. ❌ **Migration UI Not Added**
   - No admin interface to run migration safely
   - Requires manual execution
   - **Impact:** High (manual migration risky)

3. ❌ **Display Templates Not Updated**
   - May still show decimal places in UI
   - User-facing issue
   - **Impact:** Low (cosmetic, works correctly)

### Can Deploy After Completing:
- Database schema updates (30 min)
- Migration admin UI (1 hour)
- Basic display template updates (30 min)

**Estimated Time to Deploy-Ready:** 2-3 hours

---

## 🎯 DEV DEPLOYMENT PLAN

### Step 1: Complete Remaining Code (2-3 hours)
- [ ] Update database schema
- [ ] Add migration admin UI
- [ ] Update key display templates

### Step 2: Run Tests (15 minutes)
```bash
vendor/bin/phpunit --colors=always
```

### Step 3: Deploy to Dev (5 minutes)
```bash
./deploy.sh --test --clear-cache
```

### Step 4: Verify on Dev Server (30 minutes)
- [ ] Login to dev admin panel
- [ ] Run integer migration from admin UI
- [ ] Verify points show as integers
- [ ] Create test order for 95 CHF
- [ ] Confirm 9 points awarded (not 9.5)
- [ ] Test points redemption

### Step 5: User Testing (1-2 hours)
- [ ] Test checkout with points redemption
- [ ] Test account dashboard points display
- [ ] Test various order amounts
- [ ] Test edge cases (small orders, large orders)

### Step 6: Cypress Tests (1 hour)
```bash
cd ../intersoccer-player-management-tests
npm test -- --spec 'cypress/e2e/points/**'
```

---

## 🔄 ROLLBACK PLAN

If issues occur on dev:

### Quick Rollback (Code):
```bash
git checkout HEAD~1
./deploy.sh
```

### Database Rollback:
```php
// Via admin panel or WP-CLI
$migration = new InterSoccer_Points_Migration_Integers();
$migration->rollback_migration();
```

### Full Rollback:
1. Restore database from backup
2. Deploy previous code version
3. Clear all caches
4. Verify site functionality

---

## ✅ GO/NO-GO DECISION CRITERIA

### GO ✅ (Safe to Deploy)
- All PHPUnit tests passing
- No linting errors
- Database migration UI added
- Display templates updated
- Backup taken
- Rollback tested

### NO-GO ❌ (Do Not Deploy)
- Any tests failing
- Linting errors present
- No admin UI for migration
- No database backup
- Rollback not tested

---

## 📞 SUPPORT

### If Deployment Fails:
1. Check error logs: `tail -f debug.log`
2. Review test output
3. Check server error logs
4. Roll back if needed

### If Tests Fail:
1. Read error message
2. Fix the issue
3. Re-run tests
4. Don't skip tests!

---

## 🎓 LESSONS LEARNED

### Best Practices Applied:
- ✅ Write tests BEFORE deploying
- ✅ Integrate tests into deployment pipeline
- ✅ Create rollback plan before changes
- ✅ Document everything
- ✅ Use migration scripts for schema changes

### Avoid:
- ❌ Deploying without testing
- ❌ Manual database changes
- ❌ Skipping backups
- ❌ Ignoring test failures

---

**Next Review:** After completing database schema and migration UI  
**Deployment Target:** Dev server first, then staging, then production  
**Estimated Go-Live:** 2-3 hours of work remaining

