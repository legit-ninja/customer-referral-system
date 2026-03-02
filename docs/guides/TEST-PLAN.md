# InterSoccer Customer Referral System — Functional Test Plan

**Version:** 1.0  
**Last Updated:** February 2026  
**Scope:** Manual and automated QA validation of all referral system features

---

## Table of Contents

1. [Prerequisites & Test Environment](#1-prerequisites--test-environment)
2. [Referral Code Generation & Capture](#2-referral-code-generation--capture)
3. [First-Order Discount (CHF 10)](#3-first-order-discount-chf-10)
4. [Points Allocation on Order Completion](#4-points-allocation-on-order-completion)
5. [Points Redemption at Checkout](#5-points-redemption-at-checkout)
6. [Coach Commissions](#6-coach-commissions)
7. [Coach Referral Rewards (Points)](#7-coach-referral-rewards-points)
8. [Customer Referral Rewards](#8-customer-referral-rewards)
9. [Admin Points Adjustment](#9-admin-points-adjustment)
10. [Referral Eligibility & Override](#10-referral-eligibility--override)
11. [Coach–Customer Partnership Management](#11-coachcustomer-partnership-management)
12. [Email Notifications](#12-email-notifications)
13. [Audit Log](#13-audit-log)
14. [Regression Checklist](#14-regression-checklist)

---

## 1. Prerequisites & Test Environment

### Required Test Accounts

| Role | Username / Email | Notes |
|------|-----------------|-------|
| Admin | Site WordPress admin | Full access to Referrals menu |
| Coach | `coach-test@intersoccer.ch` | Must have coach role, referral code assigned |
| New Customer | `new-customer-test@intersoccer.ch` | No prior orders |
| Returning Customer | `returning-customer-test@intersoccer.ch` | Has at least one prior completed order |

### Required Test Products

- At least one purchasable camp or course product
- Price: CHF 100 (simple round number makes points easy to verify)

### Settings to Verify Before Testing

Navigate to **WordPress Admin > Referrals > Settings** and confirm:

| Setting | Expected Value |
|---------|---------------|
| Points Rate | 10 CHF = 1 point |
| New Customer Discount | CHF 10 |
| New Customer Bonus Points | 50 points |
| Referral Eligibility Window | 18 months |
| Points Go-Live Date | Set to a date before test orders |
| Email Notifications | Enabled |

### Reset State Between Test Runs

Before each major test scenario, verify:
- Test customer has zero prior orders (or reset to a known state)
- `intersoccer_first_order_discount_consumed` user meta is not set on the new customer
- `intersoccer_points_balance` is at a known starting value (0 for new customers)
- No orphaned referral records in `wp_intersoccer_referrals` for the test order

---

## 2. Referral Code Generation & Capture

### TC-2.1: Coach Referral Code Exists

**Precondition:** A user with the `coach` role exists.

**Steps:**
1. Log in as admin
2. Navigate to **Referrals > Coaches**
3. Find the test coach and open their profile
4. Note the displayed referral code and referral link

**Expected Result:**
- Referral code is present and matches the format `COACH{coach_id}{random_chars}` (uppercase alphanumeric, no underscores)
- Referral link is a full site URL with `?ref={code}` appended

---

### TC-2.2: Customer Referral Code Exists

**Precondition:** A logged-in customer has made at least one order (or has been assigned a code).

**Steps:**
1. Log in as the test customer
2. Navigate to **My Account > Referrals**
3. Note the customer referral code and share link

**Expected Result:**
- Code format is `CUST{user_id}{random_chars}` (uppercase alphanumeric)
- Share link uses `?cust_ref={code}`

---

### TC-2.3: Coach Referral Cookie Is Set on Link Visit

**Steps:**
1. Open a private/incognito browser
2. Visit `https://intersoccer.ch/?ref={COACH_CODE}` (replace with actual code)
3. Open browser developer tools > Application > Cookies
4. Look for the cookie named `intersoccer_referral`

**Expected Result:**
- Cookie `intersoccer_referral` is set
- Cookie value is a JSON payload containing the coach's code
- Cookie expiry is approximately 1 year from now

---

### TC-2.4: Customer Referral Cookie Is Set on Link Visit

**Steps:**
1. Open a private/incognito browser
2. Visit `https://intersoccer.ch/?cust_ref={CUSTOMER_CODE}`
3. Check for `intersoccer_referral` cookie

**Expected Result:**
- Cookie is set with customer referral code in the payload
- `referrer_type` in the JSON is `customer`

---

### TC-2.5: Self-Referral Is Prevented

**Steps:**
1. Log in as the coach who owns the referral code
2. Visit `https://intersoccer.ch/?ref={OWN_COACH_CODE}`
3. Add a product to cart and proceed to checkout
4. Check for the referral discount or processing

**Expected Result:**
- No CHF 10 first-order discount appears in the cart
- After completing the order, no referral entry is created in `wp_intersoccer_referrals` for this coach/order combination

---

## 3. First-Order Discount (CHF 10)

### TC-3.1: New Customer Receives CHF 10 Discount

**Precondition:** New customer account with no prior completed orders. Coach referral code captured in cookie.

**Steps:**
1. As new customer, visit the site via `?ref={COACH_CODE}`
2. Add a CHF 100 product to cart
3. Proceed to checkout
4. Observe the order summary

**Expected Result:**
- A line item labeled "Referral Discount" or similar shows **-CHF 10.00** in the cart
- Order total is CHF 90.00
- The discount is applied as a fee (not a WooCommerce coupon)

---

### TC-3.2: Discount Is Not Applied on Second Order

**Precondition:** The same customer from TC-3.1 has now completed their first order with the discount.

**Steps:**
1. Log in as the same customer
2. Add a product to cart and go to checkout
3. Observe the order summary

**Expected Result:**
- No CHF 10 referral discount appears
- `intersoccer_first_order_discount_consumed` in user meta = `1` (verifiable via admin > Users > edit user > custom fields, or WP-CLI: `wp user meta get {user_id} intersoccer_first_order_discount_consumed`)

---

### TC-3.3: Returning Customer Does Not Receive Discount

**Precondition:** Returning customer with prior completed orders. Coach referral code in cookie.

**Steps:**
1. As returning customer, visit site via `?ref={COACH_CODE}`
2. Add a product and checkout

**Expected Result:**
- No CHF 10 discount applied
- Referral eligibility evaluated against the 18-month dormancy window

---

### TC-3.4: Discount Is Not Double-Applied on Failed Order Retry

**Precondition:** New customer started checkout with discount applied, payment failed, retrying.

**Steps:**
1. New customer proceeds to checkout with referral code applied (CHF 10 discount visible)
2. Payment fails (test with a declined card)
3. Customer retries checkout

**Expected Result:**
- Discount appears only once in the cart
- Order meta `_intersoccer_first_order_discount_applied` is set on the order after completion
- `intersoccer_first_order_discount_consumed` is set only after order reaches `processing`, `on-hold`, or `completed` status

---

## 4. Points Allocation on Order Completion

### TC-4.1: Points Awarded After Order Completion (Standard)

**Precondition:** Customer with no existing points balance places a CHF 100 order using no referral code and no points redemption.

**Steps:**
1. Log in as customer
2. Add a CHF 100 product, complete checkout
3. In WordPress admin, navigate to **WooCommerce > Orders**, find the order, change status to **Completed**
4. Navigate to **Referrals > Customer Points**, search for the customer

**Expected Result:**
- Customer points balance = **10** (floor(100 / 10))
- `wp_intersoccer_points_log` has a new row: `transaction_type = order_purchase`, `points_amount = 10`

---

### TC-4.2: Points Are Always Integers (No Decimals)

**Precondition:** Order total is CHF 95.00.

**Steps:**
1. Complete a CHF 95 order and mark as Completed
2. Check the customer's points balance

**Expected Result:**
- Points awarded = **9** (floor(95 / 10), not 9.5)
- No decimal values in `wp_intersoccer_points_log`

---

### TC-4.3: Points Not Awarded for Pre-Go-Live Orders

**Precondition:** `intersoccer_points_golive_date` is set to today's date. Test order was placed yesterday (simulate by temporarily setting go-live to tomorrow).

**Steps:**
1. Set `intersoccer_points_golive_date` to tomorrow's date in Settings
2. Place and complete a test order
3. Check points log

**Expected Result:**
- No points entry in `wp_intersoccer_points_log` for this order
- Customer balance unchanged

---

### TC-4.4: Points Refunded on Order Cancellation

**Precondition:** Customer has 10 points from a completed CHF 100 order.

**Steps:**
1. In WooCommerce, cancel the order that awarded the 10 points
2. Check the customer's balance

**Expected Result:**
- Customer balance returns to pre-order value (e.g., 0)
- `wp_intersoccer_points_log` has a new row with negative `points_amount` (-10) and `transaction_type = order_cancelled` or `order_refunded`

---

### TC-4.5: Points Refunded on Full Refund

Same as TC-4.4 but use **Refund** instead of Cancel in the WooCommerce order screen.

**Expected Result:** Same as TC-4.4.

---

## 5. Points Redemption at Checkout

### TC-5.1: Customer Can Redeem Points for Discount

**Precondition:** Customer has 50 points balance.

**Steps:**
1. Add a CHF 100 product to cart
2. At checkout, toggle the "Use Points" option
3. Enter **20** in the points redemption field
4. Observe cart totals

**Expected Result:**
- Cart shows a "Points Discount" fee of **-CHF 20.00**
- Order total = CHF 80.00

---

### TC-5.2: Points Cannot Exceed Available Balance

**Precondition:** Customer has 30 points.

**Steps:**
1. At checkout, attempt to enter **50** in the points redemption field

**Expected Result:**
- System caps the redemption at 30 (or shows a validation error)
- Cart discount does not exceed CHF 30.00

---

### TC-5.3: Points Cannot Exceed Cart Total

**Precondition:** Customer has 500 points. Cart total is CHF 80.

**Steps:**
1. At checkout, enter **200** in the points redemption field

**Expected Result:**
- System caps the redemption at 80 (cart total)
- Cart discount = CHF 80.00, order total = CHF 0.00

---

### TC-5.4: Points Deducted After Order Completion, Not Placement

**Precondition:** Customer has 50 points and redeems 20 at checkout.

**Steps:**
1. Place the order (status becomes `processing` or `on-hold`)
2. Check the customer's points balance immediately after order placement
3. Change the order status to **Completed**
4. Check the balance again

**Expected Result:**
- After placement: balance may still show 50 (deduction pending) or 30 depending on implementation
- After completion: balance = **30** (50 − 20)
- `wp_intersoccer_points_log` shows `transaction_type = points_redemption`, `points_amount = -20`
- Order meta `_intersoccer_points_redeemed = 20`

---

### TC-5.5: Points Returned on Order Cancellation After Redemption

**Precondition:** Customer redeemed 20 points on an order; order is now Completed.

**Steps:**
1. Cancel the order
2. Check the customer's balance

**Expected Result:**
- 20 points are credited back to the customer
- Balance restored to pre-redemption level

---

## 6. Coach Commissions

### TC-6.1: Commission Recorded for Eligible Referral

**Precondition:** New customer visits via coach referral link, places and completes a CHF 100 order.

**Steps:**
1. New customer visits via `?ref={COACH_CODE}`, completes a CHF 100 order
2. Mark order as Completed
3. Navigate to **Referrals > Coach Referrals**
4. Filter by the test coach

**Expected Result:**
- A referral record exists for the coach + order
- Commission amount is calculated based on the configured tier rate (default 15% first purchase = CHF 15.00)
- Status = `pending` (not yet paid)

---

### TC-6.2: Commission Not Recorded for Ineligible Returning Customer

**Precondition:** Returning customer who purchased within the last 18 months uses coach referral code.

**Steps:**
1. Returning customer places and completes an order with coach referral code
2. Check **Referrals > Coach Referrals**

**Expected Result:**
- Either no referral record, or record exists with status = `ineligible`
- `_intersoccer_referral_eligibility` order meta contains `eligible: false` with reason

---

### TC-6.3: Commission Eligible for Dormant Returning Customer

**Precondition:** Customer's last purchase was more than 18 months ago.

**Steps:**
1. Customer uses coach referral code and places a new order
2. Mark order as Completed
3. Check **Referrals > Coach Referrals**

**Expected Result:**
- Commission record created with `status = pending`
- Eligibility reason notes the dormancy window was met

---

### TC-6.4: Mark Commission as Paid

**Precondition:** A pending commission exists from TC-6.1.

**Steps:**
1. Navigate to **Referrals > Coach Referrals**
2. Find the pending commission record
3. Click **Mark as Paid**

**Expected Result:**
- Record status changes to `paid`
- `wp_intersoccer_coach_commissions` table updated
- `wp_intersoccer_referral_credits` updated accordingly
- Coach's credits balance reduced by the commission amount

---

### TC-6.5: Commission CSV Export Contains Correct Data

**Steps:**
1. Navigate to **Referrals > Coach Referrals**
2. Apply any filters, then click **Export to CSV**
3. Download and open the CSV

**Expected Result:**
- Columns: Date, Order #, Coach, Customer, Commission, Status, Eligibility, Returning, First-time, Paid
- Values match what is displayed on screen

---

## 7. Coach Referral Rewards (Points)

### TC-7.1: Coach Receives 50 Bonus Points on First Customer Conversion

**Precondition:** New customer uses coach referral code for their very first completed order.

**Steps:**
1. New customer completes their first order using `?ref={COACH_CODE}`
2. Mark order as Completed
3. Check the coach's points balance in **Referrals > Customer Points** (or via user meta `intersoccer_points_balance` on the coach user)

**Expected Result:**
- Coach receives **50 points** (default `intersoccer_new_customer_credits`)
- `wp_intersoccer_referral_rewards` has a new row for this order
- `wp_intersoccer_points_log` has an entry for the coach with `transaction_type = referral_code_reward`

---

### TC-7.2: Coach Does Not Receive 50 Bonus Points on Second or Subsequent Orders

**Precondition:** Customer already completed one order using the coach's code.

**Steps:**
1. Same customer places a second order (no referral code needed — partnership assumed)
2. Mark order as Completed
3. Check coach's bonus points log

**Expected Result:**
- No new 50-point bonus entry in `wp_intersoccer_referral_rewards` for the same coach/customer pair
- (Purchase reward points from TC-7.3 should still apply)

---

### TC-7.3: Coach Receives Purchase Reward Points from Partnership Customer

**Precondition:** Customer is in a partnership with the coach (`intersoccer_preferred_coach` = coach ID). Customer places a CHF 100 order.

**Steps:**
1. Partnership customer places and completes a CHF 100 order
2. Check coach's points balance

**Expected Result:**
- Coach receives **10 points** (floor(100 / 10))
- `wp_intersoccer_purchase_rewards` has a new row for this order
- Coach's `intersoccer_points_balance` user meta is incremented accordingly

---

## 8. Customer Referral Rewards

### TC-8.1: Referring Customer Earns 10% of Friend's Purchase

**Precondition:** Existing customer shares their `?cust_ref={CUSTOMER_CODE}` link. A new customer uses that link to purchase.

**Steps:**
1. New customer visits via `?cust_ref={EXISTING_CUSTOMER_CODE}` and completes a CHF 100 order
2. Mark order as Completed
3. Check referring customer's points/credits balance

**Expected Result:**
- Referring customer earns approximately **CHF 10 in credits** (10% of CHF 100)
- `intersoccer_customer_credits` or `intersoccer_points_balance` on the referring user is updated
- A referral record exists in `wp_intersoccer_referrals` with `referrer_type = customer`

---

## 9. Admin Points Adjustment

### TC-9.1: Add Points to a Customer

**Steps:**
1. Navigate to **Referrals > Customer Points**
2. Search for the test customer
3. Click **Adjust**
4. Select **Add**, enter **25**, enter a reason: "Test credit top-up"
5. Click **Submit**

**Expected Result:**
- Customer's balance increases by 25
- `wp_intersoccer_points_log` has a new row: `transaction_type = admin_adjustment`, `points_amount = 25`
- The reason text is stored in the log entry

---

### TC-9.2: Subtract Points from a Customer

**Precondition:** Customer has at least 10 points.

**Steps:**
1. Adjust the customer's balance: select **Subtract**, enter **10**, enter reason

**Expected Result:**
- Balance decreases by 10
- Log entry: `transaction_type = admin_adjustment`, `points_amount = -10`

---

### TC-9.3: Set Balance to a Specific Value

**Precondition:** Customer currently has 30 points.

**Steps:**
1. Adjust: select **Set Balance**, enter **50**, enter reason

**Expected Result:**
- Balance is now exactly **50**
- Log entry: `transaction_type = admin_balance_set`
- The difference (+20) is recorded

---

### TC-9.4: Adjustment Rejected Without Reason

**Steps:**
1. Open the adjust modal, enter a points amount, leave the reason field blank
2. Click **Submit**

**Expected Result:**
- Form validation error — submission blocked
- No change to customer balance

---

### TC-9.5: Decimal Points Rejected

**Steps:**
1. Open the adjust modal, enter **10.5** as the points amount

**Expected Result:**
- Validation error: only integers are allowed
- No change to customer balance

---

## 10. Referral Eligibility & Override

### TC-10.1: Ineligible Referral Is Flagged Correctly

**Precondition:** A returning customer (last purchase < 18 months ago) uses a coach referral code.

**Steps:**
1. Complete the order
2. Navigate to **Referrals > Coach Referrals**
3. Find the order in the referrals list

**Expected Result:**
- Eligibility column shows **Ineligible** with a reason (e.g., "Customer purchased within 18 months")
- Commission status = `ineligible` or no commission is owed

---

### TC-10.2: Admin Can Override Eligibility to Eligible

**Steps:**
1. From the ineligible referral row in TC-10.1, click the **Override → Eligible** button
2. Enter an override reason when prompted
3. Confirm

**Expected Result:**
- Eligibility column updates to **Eligible (Override)**
- Commission is now owed / status updates
- Override history stored in order meta `_intersoccer_referral_eligibility`
- Action logged in `wp_intersoccer_audit_log`

---

### TC-10.3: Admin Can Override Eligibility to Ineligible

**Precondition:** A referral that is currently marked Eligible.

**Steps:**
1. Click **Override → Ineligible**, enter reason, confirm

**Expected Result:**
- Status updates to **Ineligible (Override)**
- Commission cleared
- Override logged in audit log

---

## 11. Coach–Customer Partnership Management

### TC-11.1: Customer Auto-Assigned to Coach After First Referral Purchase

**Precondition:** New customer uses coach referral code and completes first order.

**Steps:**
1. Check the customer's user meta after order completion

**Expected Result:**
- `intersoccer_preferred_coach` = coach's user ID
- `intersoccer_partnership_coach_id` = coach's user ID
- `intersoccer_partnership_start_date` is set to today

---

### TC-11.2: Customer Can Select a Coach from My Account

**Steps:**
1. Log in as customer, navigate to **My Account > Referrals**
2. Browse the coach list
3. Click **Select** on a coach

**Expected Result:**
- Partnership is created (or updated)
- Customer receives a confirmation message
- Coach receives a notification email

---

### TC-11.3: Coach Switch Is Blocked During Cooldown

**Precondition:** Customer switched coaches less than 7 days ago.

**Steps:**
1. Customer attempts to select a different coach

**Expected Result:**
- System shows a message indicating the cooldown period and when they can switch again
- `intersoccer_partnership_switch_cooldown` meta is checked

---

### TC-11.4: Coach Switch Is Allowed After Cooldown Expires

**Precondition:** `intersoccer_partnership_switch_cooldown` date is in the past.

**Steps:**
1. Customer selects a new coach

**Expected Result:**
- New partnership is created successfully
- Old coach receives a notification that the customer has moved

---

## 12. Email Notifications

### TC-12.1: Coach Receives Referral Code Email

**Steps:**
1. In **Referrals > Coaches**, select a coach and use the **Send Referral Code** bulk action (or individual button)
2. Check the coach's email inbox

**Expected Result:**
- Email received with subject "Your Coach Connection Referral Code - {Site Name}"
- Email contains the coach's referral code and referral link

---

### TC-12.2: Coach Receives Commission Notification

**Precondition:** A new customer uses the coach's referral link and completes an order.

**Steps:**
1. Complete the order workflow
2. Check the coach's email inbox

**Expected Result:**
- Email received with subject "New Commission Earned - Order #{order_id}"
- Email contains commission breakdown

---

### TC-12.3: Partnership Selection Notifies Both Coach and Customer

**Steps:**
1. Customer selects a new coach (TC-11.2)
2. Check both the coach's and customer's inboxes

**Expected Result:**
- Coach receives: "New partnership: {customer name} has selected you"
- Customer receives: welcome/confirmation message

---

### TC-12.4: Emails Can Be Disabled

**Steps:**
1. Navigate to **Referrals > Settings**, disable **Email Notifications**
2. Trigger a commission event (complete a referred order)
3. Check email inboxes

**Expected Result:**
- No emails sent
- All other system behavior (points, commission records) functions normally

---

## 13. Audit Log

### TC-13.1: Points Adjustment Is Logged

**Precondition:** Perform a manual points adjustment (TC-9.1).

**Steps:**
1. Navigate to **Referrals > Audit Log** (if exposed in admin) or query `wp_intersoccer_audit_log` directly

**Expected Result:**
- A new row exists for the adjustment event
- Fields present: `event_type`, `category`, `user_id`, `data` (JSON), `ip_address`, `created_at`

---

### TC-13.2: Eligibility Override Is Logged

**Precondition:** Perform an eligibility override (TC-10.2).

**Steps:**
1. Check `wp_intersoccer_audit_log`

**Expected Result:**
- Log entry for the override, including the admin user who made the change and the reason

---

### TC-13.3: Commission Marked as Paid Is Logged

**Precondition:** Mark a commission as paid (TC-6.4).

**Steps:**
1. Check `wp_intersoccer_audit_log`

**Expected Result:**
- Log entry shows commission payment event with order ID, coach ID, and amount

---

## 14. Regression Checklist

Use this checklist after any code deployment to confirm core flows have not regressed.

| # | Test | Pass | Fail | Notes |
|---|------|------|------|-------|
| R-01 | CHF 10 discount applies for new customer using coach referral code | | | |
| R-02 | CHF 10 discount does NOT apply on second order for same customer | | | |
| R-03 | Points awarded = floor(order_total / 10) with no decimals | | | |
| R-04 | Points NOT awarded for orders before go-live date | | | |
| R-05 | Points redemption reduces order total (1 point = 1 CHF) | | | |
| R-06 | Points deducted from balance after order completion | | | |
| R-07 | Points restored after order cancellation / refund | | | |
| R-08 | Coach commission created for eligible new customer referral | | | |
| R-09 | No commission created for ineligible (< 18 month dormancy) customer | | | |
| R-10 | Coach receives 50 bonus points on first customer conversion | | | |
| R-11 | Partnership customer purchases earn 1 point per CHF 10 for coach | | | |
| R-12 | Admin points adjustment (add/subtract/set) logged correctly | | | |
| R-13 | Admin points adjustment rejected without reason | | | |
| R-14 | Eligibility override updates status and is logged | | | |
| R-15 | Coach switch blocked during 7-day cooldown | | | |
| R-16 | Referral cookie set on `?ref=` link visit | | | |
| R-17 | Self-referral produces no commission and no discount | | | |
| R-18 | Mark commission as paid updates status and credits | | | |
| R-19 | Audit log captures all admin actions | | | |
| R-20 | Email notifications fire on commission and partnership events | | | |

---

*For PHPUnit and Cypress automated test instructions, see [TESTING.md](TESTING.md).*
