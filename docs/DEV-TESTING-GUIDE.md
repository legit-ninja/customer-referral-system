# 🧪 Dev Server Testing Guide - Phase 0

**What to Test:** Phase 0 changes deployed to dev server  
**Focus:** Integer points, test integration, deployment pipeline  
**Time Required:** 30-45 minutes

---

## 🚀 Quick Start

### Deploy to Dev with Tests:
```bash
./deploy.sh --test --clear-cache
```

This will:
1. ✅ Run Phase 0 critical tests first
2. ✅ Run full PHPUnit test suite
3. ✅ Show Cypress test reminder
4. ✅ Deploy code to dev server
5. ✅ Clear all server caches

---

## 🔍 What to Test on Dev Server

### Test 1: Integer Points Display (5 minutes)

**Objective:** Verify points show as integers, not decimals

1. **Login to dev server:**
   - URL: `https://intersoccer.legit.ninja/wp-admin/`
   - Go to: WooCommerce → Orders

2. **Check existing orders:**
   - Look for points awarded
   - Should see: "9 points" NOT "9.50 points"
   - Should see: "10 points" NOT "10.00 points"

3. **Check customer accounts:**
   - Go to: Users → Select a customer
   - Check user meta: `intersoccer_points_balance`
   - Should be integer value only

### Test 2: Points Calculation (10 minutes)

**Objective:** Verify floor() behavior (95 CHF = 9 points)

1. **Create test order:**
   - Go to: WooCommerce → Orders → Add Order
   - Customer: Any test customer
   - Add product totaling: **CHF 95.00**
   - Complete the order

2. **Verify points awarded:**
   - Expected: **9 points** (not 9.5)
   - Check customer's points balance
   - Check points_log table in database

3. **Test other amounts:**
   | Order Amount | Expected Points | NOT Expected |
   |--------------|----------------|--------------|
   | CHF 25 | 2 points | 2.5 points |
   | CHF 95 | 9 points | 9.5 points |
   | CHF 100 | 10 points | 10.0 points |
   | CHF 9.99 | 0 points | 1 point |

### Test 3: Points Redemption (10 minutes)

**Objective:** Verify points redemption works with integers

1. **Give customer 50 points:**
   - Go to: Referrals → Customer Points
   - Select customer
   - Adjust points: +50
   - Save

2. **Customer checkout:**
   - Login as customer on frontend
   - Add product to cart
   - Go to checkout
   - Check "Use Loyalty Points"
   - Click "Apply All"
   - Verify: Shows "50 points" (integer)

3. **Complete order:**
   - Place order
   - Verify discount applied correctly
   - Check points deducted (balance should be integer)

### Test 4: Deployment Pipeline (5 minutes)

**Objective:** Verify tests are integrated into deployment

1. **Test the --test flag:**
   ```bash
   ./deploy.sh --test
   ```
   - Should run PointsManagerTest.php first
   - Should run PointsMigrationIntegersTest.php second
   - Should run full test suite
   - Should show Cypress reminder

2. **Test without --test flag:**
   ```bash
   ./deploy.sh
   ```
   - Should show yellow warning
   - Should give 10-second delay
   - Should allow abort with Ctrl+C

### Test 5: Database Integrity (10 minutes)

**Objective:** Verify data integrity maintained

1. **Check points_log table:**
   ```sql
   SELECT points_amount, points_balance 
   FROM wp_intersoccer_points_log 
   ORDER BY id DESC 
   LIMIT 10;
   ```
   - All values should be whole numbers
   - No decimal places

2. **Check points balance consistency:**
   ```sql
   SELECT customer_id, points_balance 
   FROM wp_intersoccer_points_log 
   WHERE points_balance != FLOOR(points_balance);
   ```
   - Should return **0 rows** (no fractional points)

3. **Verify user meta:**
   ```sql
   SELECT user_id, meta_value 
   FROM wp_usermeta 
   WHERE meta_key = 'intersoccer_points_balance';
   ```
   - All values should be integers

---

## 🔍 Things to Look For

### ✅ GOOD Signs:

- Points displayed as: "9 points" (no decimals)
- Points calculations use floor: 95 CHF = 9 points
- Redemption works smoothly with integers
- No JavaScript errors in browser console
- Orders complete successfully with points
- Database values are clean integers

### ⚠️ WARNING Signs:

- Points shown as: "9.50 points" (has decimals)
- Points calculations wrong: 95 CHF ≠ 9 points
- Redemption fails or shows errors
- JavaScript errors in console
- Order totals incorrect
- Database has fractional values

### 🚨 CRITICAL Issues (Rollback Required):

- Site crashes or fatal errors
- Orders cannot be completed
- Points calculations completely wrong
- Database corruption
- Users cannot login
- Checkout broken

---

## 🐛 Common Issues to Check

### Issue 1: Points Still Show Decimals
**Symptom:** "9.50 points" instead of "9 points"  
**Cause:** Display templates not updated yet  
**Status:** Expected (not yet implemented)  
**Severity:** Low (cosmetic only, calculations correct)

### Issue 2: Migration Not Available
**Symptom:** No migration UI in admin panel  
**Cause:** Admin UI not yet implemented  
**Status:** Expected (TODO in next session)  
**Severity:** Medium (can run manually if needed)

### Issue 3: Database Still DECIMAL
**Symptom:** Column type shows DECIMAL(10,2)  
**Cause:** Schema not updated yet  
**Status:** Expected (TODO)  
**Severity:** Low (migration script will fix)

---

## 📝 Testing Checklist

Use this checklist while testing:

### Pre-Deployment:
- [ ] All PHPUnit tests passing locally
- [ ] No linting errors
- [ ] Database backup taken
- [ ] Deployed with `./deploy.sh --test`

### On Dev Server:
- [ ] Points display correctly (integer or acceptable decimal display)
- [ ] Points calculations use floor() (95 CHF = 9 points)
- [ ] Points balance shows integers
- [ ] Points redemption works
- [ ] Orders complete successfully
- [ ] No errors in browser console
- [ ] No errors in server error log

### Database Verification:
- [ ] Points_log table has integer values
- [ ] User meta has integer balances
- [ ] No fractional points in system

### Regression Testing:
- [ ] Existing features still work
- [ ] Commission calculations unaffected
- [ ] Referral codes still work
- [ ] Coach dashboard accessible
- [ ] Customer dashboard accessible

---

## 🎯 Success Criteria

**Deploy to dev is successful if:**

1. ✅ All PHPUnit tests pass
2. ✅ Points calculations return integers
3. ✅ No fatal errors on dev site
4. ✅ Basic checkout flow works
5. ✅ Points redemption functional

**Ready for next phase if:**

1. ✅ All success criteria met
2. ✅ No critical issues found
3. ✅ Regression testing passed
4. ✅ Database integrity verified

---

## 📞 What to Report Back

### If Everything Works ✅
- "All tests passed, dev deployment successful"
- Note any minor cosmetic issues (expected)
- Ready to continue with remaining Phase 0 tasks

### If Issues Found ⚠️
Report:
1. **What you were doing** (specific test)
2. **What happened** (error message, screenshot)
3. **Expected vs actual** behavior
4. **Browser console errors** (if any)
5. **Server error log** entries (if any)

### Where to Find Logs

**Dev Server Logs:**
```bash
ssh user@intersoccer.legit.ninja
tail -f /var/www/html/wp-content/plugins/customer-referral-system/debug.log
tail -f /var/www/html/wp-content/debug.log
```

**Browser Console:**
- F12 → Console tab
- Look for red errors
- Screenshot and report

---

## 🔄 If Rollback Needed

### Quick Rollback:
```bash
git checkout HEAD~1
./deploy.sh --clear-cache
```

### Verify Rollback:
- Check points show as before
- Verify old behavior restored
- Test basic functionality

---

## ✅ Next Steps After Testing

Once you've tested and confirmed everything works:

1. **Report findings** (what works, what doesn't)
2. **Continue Phase 0** (remaining tasks: schema, UI, display templates)
3. **Plan next deployment** (with additional fixes)
4. **Consider Cypress tests** (frontend validation)

---

**Happy Testing! 🎉**

Remember: Testing prevents regressions and gives us confidence to deploy to production.

