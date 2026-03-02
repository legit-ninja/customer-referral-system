# InterSoccer Referral System — Intern Tutorial

**Audience:** InterSoccer interns and support staff  
**Purpose:** Understand how the referral and points system works, so you can troubleshoot issues and make manual adjustments when needed.  
**Last Updated:** February 2026

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Key Concepts](#2-key-concepts)
3. [Admin Navigation](#3-admin-navigation)
4. [How to Look Up a Customer's Points](#4-how-to-look-up-a-customers-points)
5. [How to Adjust Points](#5-how-to-adjust-points)
6. [How to Troubleshoot a Missing Referral Commission](#6-how-to-troubleshoot-a-missing-referral-commission)
7. [How to Troubleshoot Missing Points](#7-how-to-troubleshoot-missing-points)
8. [How to Troubleshoot a Missing First-Order Discount](#8-how-to-troubleshoot-a-missing-first-order-discount)
9. [How to Override Referral Eligibility](#9-how-to-override-referral-eligibility)
10. [How to Mark a Commission as Paid](#10-how-to-mark-a-commission-as-paid)
11. [Common Issues Quick-Reference](#11-common-issues-quick-reference)
12. [Settings Reference](#12-settings-reference)

---

## 1. System Overview

The InterSoccer Customer Referral System is a WordPress plugin that runs on the intersoccer.ch website. It manages three interconnected things:

**Coaches** — Intersoccer coaches each have a unique referral link. When a new customer signs up and makes their first purchase through that link, the coach earns a commission and bonus points.

**Customers** — Customers earn loyalty points every time they complete an order (CHF 10 spent = 1 point). Points can be spent like cash at checkout (1 point = CHF 1 discount). Customers can also share their own referral link and earn credits when friends sign up.

**Partnerships** — A customer can choose a "partner coach" who they are connected to. Every time that customer makes a purchase, the coach earns additional reward points. The customer can switch coaches but must wait 7 days between changes.

### Who Uses This System

| Person | What they care about |
|--------|---------------------|
| **Coach** | Did my referral code get used? Did I get my commission? Am I earning points from my partnership customers? |
| **Customer** | How many points do I have? Can I use them for a discount? Did my referral link give my friend a discount? |
| **Admin / Intern** | Adjusting points, troubleshooting missing commissions, overriding eligibility, marking commissions as paid |

---

## 2. Key Concepts

### Points vs. Credits

There are two similar-sounding things in the system. It helps to keep them distinct:

| Term | What it is | Where to find it |
|------|-----------|-----------------|
| **Points** (`intersoccer_points_balance`) | The current loyalty points balance. Displayed to customers. 1 point = CHF 1 at checkout. | Referrals > Customer Points |
| **Credits** (`intersoccer_customer_credits`) | An older field, now mostly kept in sync with the points balance. Rarely used independently. | Order meta / user meta (technical) |

> When adjusting someone's balance, always use the **Referrals > Customer Points** page — this updates the correct field and logs the change.

### Referral Types

There are two kinds of referral links:

- **Coach referral link:** `https://intersoccer.ch/?ref=COACH123ABC` — Used by coaches to bring in new customers. Earns the coach a commission and 50 bonus points on first conversion.
- **Customer referral link:** `https://intersoccer.ch/?cust_ref=CUST456DEF` — Used by customers to recommend friends. Earns the referring customer 10% of their friend's first purchase in credits.

### The Dormancy Window (Eligibility)

A coach only earns a commission for referring a customer if that customer is "new" or "dormant." The rule is:

> A referral earns a commission only if the customer has **not made a purchase in the last 18 months** (or has never purchased before).

If a returning customer who bought 6 months ago uses a coach's link, the coach does **not** earn a commission — even though the customer followed the link. Admins can override this rule manually (see [Section 9](#9-how-to-override-referral-eligibility)).

### First-Order Discount (CHF 10)

When a brand-new customer (with no prior orders) arrives via a referral link and makes their first purchase, they automatically receive **CHF 10 off**. This discount:

- Appears automatically in their cart — they do not need to enter a coupon code
- Can only be used once per customer
- Does not apply to returning customers, even if they use a referral link

### Points Go-Live Date

The system has a setting called the **go-live date**. Points are only awarded for orders placed **on or after** this date. Orders placed before the go-live date do not earn points. This setting exists to prevent retroactive point awards when the system was first turned on.

---

## 3. Admin Navigation

Log in to the WordPress admin at `https://intersoccer.ch/wp-admin/`.

In the left sidebar, look for the **Referrals** menu (icon looks like a currency symbol). It has these sub-pages:

| Sub-page | What you do here |
|----------|-----------------|
| **Dashboard** | Overview charts — referrals over time, commissions, performance |
| **Coaches** | View and manage coach profiles, send referral codes, import coaches |
| **Coach Referrals** | View all referral records, see eligibility, mark commissions as paid, override eligibility |
| **Customer Referrals** | View customer credit/referral records, import/export credits |
| **Financial Report** | Commission and earnings report |
| **Customer Points** | Look up any customer's points balance, make manual adjustments |
| **Settings** | Configure rates, go-live date, email notifications, eligibility window |

---

## 4. How to Look Up a Customer's Points

1. In the WordPress admin sidebar, click **Referrals > Customer Points**
2. In the search box at the top, type the customer's **name** or **email address**
3. Press Enter or click Search

The table shows:

| Column | What it means |
|--------|--------------|
| **Customer** | Name and email |
| **Current Points** | What they can spend right now |
| **Total Earned** | Lifetime points earned (including spent points) |
| **Total Spent** | Points already used at checkout |
| **Last Activity** | Date of their most recent points transaction |

You can also filter the list:
- **All** — everyone in the system
- **With Points** — only customers who have a positive balance
- **Zero Points** — customers with no points (useful to check if awarding failed)

---

## 5. How to Adjust Points

Use this when:
- A customer did not receive points they were owed
- Points were incorrectly awarded and need to be removed
- A customer requests a correction and you have approval to make it
- A negative balance needs to be corrected

### Step-by-Step

1. Navigate to **Referrals > Customer Points**
2. Find the customer using search
3. Click the **Adjust** button next to their name
4. A modal dialog will appear with these fields:

   **Adjustment Type** — choose one:
   - **Add** — increases the balance by the amount you enter
   - **Subtract** — decreases the balance by the amount you enter
   - **Set Balance** — sets the balance to exactly the number you enter (regardless of current balance)

5. **Amount** — enter a whole number (e.g., `25`). Decimals are not allowed.
6. **Reason** — type a brief explanation (this is required and cannot be left blank). Examples:
   - "Points not awarded after order #1234 completed — manual correction"
   - "Removing duplicate points award from system error"
   - "Customer request approved by manager — courtesy adjustment"

7. Click **Submit**

### What Happens After You Submit

- The customer's points balance is updated immediately
- A record is written to the points log with your admin user name, the amount, and the reason
- The customer will see the new balance next time they log in and visit My Account

### Verifying the Adjustment

After submitting, the table should refresh with the updated balance. If you want to double-check, search for the customer again and confirm the **Current Points** number is correct.

> **Important:** Always enter a meaningful reason. This creates an audit trail so that if a customer or manager asks why the balance changed, there is a clear record.

---

## 6. How to Troubleshoot a Missing Referral Commission

A coach may contact you saying: "I referred a customer and they made a purchase, but I don't see my commission."

Work through these checks in order:

### Step 1: Find the Order

1. Go to **WooCommerce > Orders**
2. Search for the order by customer name, email, or order number
3. Open the order and note the **Order Status**

> Commissions are only created when the order status is **Completed**. If the order is still `Processing` or `On Hold`, the commission has not been triggered yet. Change the status to Completed if appropriate.

### Step 2: Check Coach Referrals

1. Go to **Referrals > Coach Referrals**
2. Filter by the coach's name or search for the order number
3. Look for a row matching this order

If a row exists, check the **Eligibility** column:
- **Eligible** — commission should be owed; check the Status column
- **Ineligible** — the customer did not meet the dormancy window (see [Section 9](#9-how-to-override-referral-eligibility) to override if appropriate)

### Step 3: Check If a Referral Code Was Used

If there is no row at all in Coach Referrals for this order:

1. Open the order in **WooCommerce > Orders**
2. Scroll to the **Custom Fields** / **Order Meta** section
3. Look for `_intersoccer_referral_code` — this shows the code that was applied at checkout

If this field is empty or missing, the referral code was never captured. Common reasons:
- Customer cleared cookies or used a different browser between clicking the link and checking out
- Customer took longer than the cookie lifetime (30 days by default) to complete their purchase
- The referral link was pasted incorrectly (missing `?ref=` or wrong format)

### Step 4: Check the Referral Processed Flag

In the same **Order Meta** section, look for `_intersoccer_referral_processed`. If this is set to `1`, the system already ran the referral logic for this order. If the commission still isn't there, it may have been skipped due to eligibility.

### Step 5: Check the Go-Live Date

Go to **Referrals > Settings** and look at the **Points Go-Live Date**. If the order was placed before this date, referral processing may also have been skipped.

---

## 7. How to Troubleshoot Missing Points

A customer says: "I completed an order but my points haven't shown up."

### Step 1: Confirm the Order Is Completed

Points are only awarded when order status = **Completed**. Go to **WooCommerce > Orders**, find the order, and check the status. Change to Completed if appropriate.

### Step 2: Check the Go-Live Date

Go to **Referrals > Settings**. If the order date is before the **Points Go-Live Date**, no points will be awarded — this is by design.

### Step 3: Look Up the Customer's Points Log

1. Go to **Referrals > Customer Points**
2. Find the customer
3. Click the **View Log** button (or the customer's name, depending on the interface)
4. Look for a log entry with:
   - `transaction_type = order_purchase`
   - `order_id` matching the order in question

If an entry exists, the points were awarded. Ask the customer to refresh their My Account page.

If no entry exists for that order, the allocation was likely skipped. Proceed to Step 4.

### Step 4: Check the Points Rate Setting

Go to **Referrals > Settings** and look at the **Points Rate**. The default is 10 CHF = 1 point. A CHF 9.99 order would earn 0 points (floor rounding). Make sure the order total is high enough to earn at least 1 point.

### Step 5: Manual Correction

If you confirm the points should have been awarded and were not, manually adjust the customer's balance using the steps in [Section 5](#5-how-to-adjust-points). In the reason field, reference the order number.

---

## 8. How to Troubleshoot a Missing First-Order Discount

A new customer says: "I clicked the coach's referral link but I didn't get the CHF 10 discount."

### Check 1: Was the Customer Actually New?

The CHF 10 discount only applies to customers with **no prior completed orders**. Go to **WooCommerce > Customers** or **WooCommerce > Orders**, filter by the customer's email, and count their prior orders. If they had a previous order, the discount is not available to them.

### Check 2: Was the Referral Cookie Set?

The discount depends on the `intersoccer_referral` cookie being present in the browser when the customer checked out. If the customer:
- Clicked the link on one device but checked out on another
- Cleared cookies or browsed in a privacy mode inconsistently
- Waited more than 30 days between clicking the link and checking out

…then the cookie would be missing and the discount would not apply.

### Check 3: Was the Discount Already Used?

In **WooCommerce > Customers** (or by editing the WordPress user), look for the custom user field `intersoccer_first_order_discount_consumed`. If it is set to `1`, the discount was already consumed on a previous order.

Also check the **Order Meta** on their most recent order: `_intersoccer_first_order_discount_applied`. If this is `1`, it was applied on that order.

### Check 4: Was the Referral Code Format Correct?

Ask the coach to share their referral link with you and verify it matches the format:
`https://intersoccer.ch/?ref=COACH123ABC`

If the link was broken (e.g., `?ref=` with nothing after it, or extra characters), the code would not be recognized.

### If the Discount Was Missed Through No Fault of the Customer

You can compensate by adding points manually (see [Section 5](#5-how-to-adjust-points)). Add **10 points** (equivalent to CHF 10) with a note referencing the missed discount.

---

## 9. How to Override Referral Eligibility

A coach may have legitimately re-engaged a customer who is technically within the 18-month window due to an edge case or special circumstance. An admin can manually override the eligibility decision.

### Step-by-Step

1. Navigate to **Referrals > Coach Referrals**
2. Find the referral record for the relevant order (filter by coach or date if needed)
3. In the **Eligibility** column, look for the override buttons:
   - **Override → Eligible** — marks this referral as eligible (commission will be owed)
   - **Override → Ineligible** — marks this referral as ineligible (removes commission)
4. Click the appropriate button
5. A prompt will ask for an **override reason** — enter a brief explanation
6. Confirm

### What Gets Updated

- The eligibility status changes in the referrals table
- The override and reason are stored on the WooCommerce order
- The action is logged in the audit log with your admin username and timestamp

> Always get manager approval before overriding eligibility, and include a meaningful reason. The audit log cannot be edited after the fact.

---

## 10. How to Mark a Commission as Paid

When InterSoccer pays a coach their earned commission (by bank transfer, voucher, etc.), record this in the system so the balance stays accurate.

### Step-by-Step

1. Navigate to **Referrals > Coach Referrals**
2. Find the row with the commission you are marking as paid. Status should be **Pending**.
3. Click **Mark as Paid**
4. Confirm the dialog

### What Gets Updated

- The referral record status changes to **Paid**
- The coach's credit balance in `wp_intersoccer_referral_credits` is updated
- A record is written to `wp_intersoccer_coach_commissions`
- The action is logged in the audit log

> Only mark commissions as paid after the actual payment has been made. This action cannot easily be reversed.

---

## 11. Common Issues Quick-Reference

| Symptom | First thing to check | Fix |
|---------|---------------------|-----|
| Customer says their points are wrong (too low) | Check `wp_intersoccer_points_log` for that order. Is there an entry? | If missing, manually add the correct points with a note referencing the order number |
| Customer says their points are wrong (too high) | Check the log for duplicate entries or system errors | Subtract the excess points via manual adjustment with a reason |
| Points balance is negative | A deduction was processed but there was no balance to deduct from | Use **Set Balance** to set it to 0 (or correct value), with a reason |
| Coach says their commission is missing | Check order status (must be Completed) and eligibility column in Coach Referrals | Mark order as Completed if appropriate; override eligibility if justified |
| First-order discount was not applied | Check if customer is actually new; check if cookie was set | Compensate with 10-point manual adjustment if appropriate |
| Referral record exists but commission is 0 | Order may be ineligible (within 18-month window) | Override eligibility if justified |
| Customer cannot switch coaches | 7-day cooldown is active | Tell the customer when the cooldown expires (`intersoccer_partnership_switch_cooldown` user meta) |
| Points were not earned on a very old order | Go-live date is set after that order date | This is by design; explain to customer |
| Discount appeared twice in the cart | Race condition or page refresh during checkout | Check `_intersoccer_first_order_discount_applied` on the order; if charged twice, refund one manually in WooCommerce |
| Coach never received commission email | Email notifications may be disabled | Check **Referrals > Settings > Email Notifications** |

---

## 12. Settings Reference

Navigate to **Referrals > Settings** to view and change these values. Changes take effect immediately for new orders. They do not retroactively change existing records.

| Setting | What it controls | Default |
|---------|-----------------|---------|
| **Commission Rate (1st purchase)** | % of order value the coach earns on a customer's first purchase | 15% |
| **Commission Rate (2nd purchase)** | % for the second purchase by the same customer | 7.5% |
| **Commission Rate (3rd+ purchase)** | % for third and later purchases | 5% |
| **New Customer Discount** | CHF discount for new customers on their first order | CHF 10 |
| **New Customer Bonus Points** | Points awarded to coach when a new customer converts | 50 points |
| **Points Rate** | How many CHF earns 1 point | 10 CHF = 1 point |
| **Points Go-Live Date** | Orders before this date do not earn points | Set at launch |
| **Referral Eligibility Window** | How many months of inactivity makes a returning customer eligible for a referral commission | 18 months |
| **Cookie Duration** | How long the referral tracking cookie lasts | 30 days |
| **Email Notifications** | Enables or disables all system emails | Enabled |
| **Points Allocation Method** | `instant` (points given immediately on completion) or `deferred` (weekly batch) | instant |

> **Do not change settings without manager approval.** Changes to commission rates or the points rate will affect how future orders are processed and can create discrepancies if customers are expecting the old rates.

---

*For developer and automated testing documentation, see [TESTING.md](TESTING.md).*  
*For a full functional test plan to validate the system, see [TEST-PLAN.md](TEST-PLAN.md).*
