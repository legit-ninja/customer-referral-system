# Point Configuration Update List

This document lists all code locations that need to be updated to use the new customizable point configuration system.

## ✅ Completed Updates

### 1. Commission Tier System
- ✅ **includes/class-admin-settings.php**: Added customizable tier UI with AJAX support
- ✅ **includes/class-commission-manager.php**: Updated to use `get_commission_rate_for_customer_count()` method
- ✅ **Database**: Tiers stored in `intersoccer_commission_tiers` option

### 2. Point Allocation Method
- ✅ **includes/class-admin-settings.php**: Added instant/deferred radio button in General Settings
- ✅ **includes/class-points-manager.php**: 
  - Updated constructor to check allocation method
  - Added `queue_order_for_points_allocation()` for deferred mode
  - Added `process_deferred_points_allocation()` for wp_cron processing
  - Scheduled weekly wp_cron event

## 📋 Files That Need Updates

### High Priority (Core Functionality)

#### 1. **tests/CommissionCalculationTest.php**
- **Current**: Hardcoded tier rates (10%, 15%, 20%)
- **Needs**: Update tests to use `get_commission_rate_for_customer_count()` method
- **Lines**: 8-11, 20, 33, 46, 60-62, 73-75, 80-91, 204-208
- **Action**: Refactor tests to load tiers from database or mock the option

#### 2. **tests/CommissionManagerTest.php**
- **Current**: Tests assume hardcoded tier structure
- **Needs**: Update to work with customizable tiers
- **Lines**: 54-78 (tier bonus tests), 83-106 (coach tier tests)
- **Action**: Mock `intersoccer_commission_tiers` option in tests

#### 3. **includes/class-commission-calculator.php** (if still in use)
- **Current**: May have hardcoded commission rates
- **Needs**: Verify it uses `InterSoccer_Commission_Manager::get_commission_rate_for_customer_count()`
- **Action**: Check if deprecated, update if still active

### Medium Priority (Documentation & Tests)

#### 4. **tests/AdminFinancialTest.php**
- **Current**: May reference hardcoded commission rates
- **Needs**: Update test expectations to use customizable tiers
- **Action**: Review and update commission rate assertions

#### 5. **tests/integration/WooCommerceIntegrationTest.php**
- **Current**: May test commission calculations with hardcoded rates
- **Needs**: Update to use tier system
- **Action**: Review commission-related tests

#### 6. **tests/CoachHelperFunctionsTest.php**
- **Current**: May reference tier bonuses or commission rates
- **Needs**: Update if it tests commission functionality
- **Action**: Review for commission/tier references

### Low Priority (Documentation Only)

#### 7. **docs/Referral System - 2025.md**
- **Current**: Documents hardcoded tier structure (10%, 15%, 20%)
- **Needs**: Update to reflect customizable tiers
- **Lines**: 125-136 (commission structure documentation)
- **Action**: Update documentation to explain customizable tier system

#### 8. **docs/FINANCIAL-MODEL-ANALYSIS.md**
- **Current**: References hardcoded commission rates
- **Needs**: Update to reflect customizable system
- **Lines**: 34, 50-51, 84-108 (commission rate references)
- **Action**: Update financial model documentation

#### 9. **docs/Customer-referral-plan.md**
- **Current**: Documents tier structure
- **Needs**: Update to reflect customizable tiers
- **Lines**: 258-269 (tier documentation)
- **Action**: Update plan documentation

#### 10. **docs/TODO-REORGANIZED.md**
- **Current**: May reference old tier structure
- **Needs**: Update if it mentions commission tiers
- **Action**: Review and update if needed

#### 11. **docs/COMPLETE-TEST-COVERAGE-REPORT.md**
- **Current**: May reference tier tests
- **Needs**: Update test coverage documentation
- **Action**: Review and update

### Code That May Need Review

#### 12. **includes/class-admin-coaches.php**
- **Action**: Check if it displays commission rates or tier information
- **Needs**: Update UI to reflect customizable tiers if applicable

#### 13. **templates/modern-coach-dashboard.php**
- **Action**: Check if it displays commission rates to coaches
- **Needs**: Update to show current tier rate dynamically

#### 14. **includes/class-admin-dashboard-main.php**
- **Action**: Check for commission rate references
- **Needs**: Update if it displays tier information

#### 15. **assets/js/elementor-dashboard.js**
- **Action**: Check for hardcoded commission rate references
- **Needs**: Update if JavaScript calculates or displays rates

## 🔄 Point Allocation Method Updates

### Already Updated
- ✅ **includes/class-points-manager.php**: Full implementation complete

### May Need Updates

#### 16. **Tests that verify point allocation timing**
- **Action**: Add tests for deferred allocation mode
- **Files**: Any test files that verify `allocate_points_for_order()` is called
- **Needs**: New tests for `queue_order_for_points_allocation()` and `process_deferred_points_allocation()`

#### 17. **Documentation about point allocation**
- **Action**: Document the instant vs deferred allocation options
- **Files**: README.md, any point allocation documentation
- **Needs**: Explain when to use each method

## 🎯 Recommended Update Order

1. **Update Tests** (High Priority)
   - `tests/CommissionCalculationTest.php`
   - `tests/CommissionManagerTest.php`
   - Add tests for deferred point allocation

2. **Review Active Code** (Medium Priority)
   - `includes/class-commission-calculator.php` (if still used)
   - `includes/class-admin-coaches.php`
   - `templates/modern-coach-dashboard.php`

3. **Update Documentation** (Low Priority)
   - All documentation files listed above

## 📝 Notes

- The new tier system uses `get_option('intersoccer_commission_tiers')` with default fallback
- Commission rates are stored as percentages (e.g., 10 for 10%) and converted to decimals (0.10) in calculations
- Point allocation method is stored in `intersoccer_points_allocation_method` option ('instant' or 'deferred')
- Deferred allocation uses wp_cron event `intersoccer_deferred_points_allocation` scheduled weekly
- Queued orders are stored in transient `intersoccer_queued_points_orders`

## ✅ Verification Checklist

- [x] All tests pass with new tier system
- [x] Commission calculations use customizable tiers
- [x] Point allocation respects instant/deferred setting
- [x] wp_cron is properly scheduled for deferred allocation
- [x] UI displays current tier configuration correctly
- [ ] Documentation reflects new customizable system
- [x] No hardcoded commission rates remain in active code

## ✅ Completed Code Updates

### Test Files Updated
- ✅ **tests/CommissionCalculationTest.php**: Updated to use `get_commission_rate_for_customer_count()` method with tier setup/teardown
- ✅ **tests/CommissionManagerTest.php**: Added tier setup/teardown in setUp/tearDown methods
- ✅ **tests/PointsManagerTest.php**: Added tests for instant/deferred allocation methods
- ✅ **tests/integration/WooCommerceIntegrationTest.php**: Updated commission expectations to work with tier system

### Code Files Verified
- ✅ **includes/class-commission-calculator.php**: Already deprecated and delegates to Commission Manager (no changes needed)

