# InterSoccer Referral System - Documentation Index

This folder contains all documentation for the InterSoccer Customer Referral System plugin.

---

## 🎯 Current Product Truth (v1.9.11 / Epic #25)

The **share-link + loyalty** slice shipped in v1.9.11. Here is the frozen product model:

| Feature | Behavior |
|---------|----------|
| Referral mechanism | Unique personal **share link + referral code** (not name-mention) |
| Points earn rate | Admin-configurable (default example: CHF 10 spent = 1 point) |
| Points redemption | **Checkout only** |
| Coach commission | Admin-configurable (example: 10% of purchases using their code) |
| Referral code stacking | **Not allowed** — one code per order |

**Shipped PRs:** #33 (admin copy), #34 (backend), #35 (share CTA), #39 (checkout redeem), #46 (version bump)

> **Superseded concepts:** Multi-tier commission (15%/7.5%/5%), season CHF bonuses, 250-pt referrer rewards, free-at-6+ milestone, and name-mention CHF 30 were analyzed in planning docs but are **not** part of the shipped product. Those docs remain under `docs/planning/` and `docs/technical/` for historical reference and are marked outdated.

See also:
- [CHANGELOG.md](CHANGELOG.md) — version history
- [README Configuration](../README.md#configuration) — admin-configurable rates
- [PRODUCT-TRUTH-SHARE-LOYALTY.md](guides/PRODUCT-TRUTH-SHARE-LOYALTY.md) — detailed product truth guide

---

## 📚 Documentation Structure

### 📖 Guides (`/guides/`)

User-facing guides and how-to documentation:

- **[COACH-EMAIL-TEMPLATE-BUILDER.md](guides/COACH-EMAIL-TEMPLATE-BUILDER.md)** - Admin guide for coach email templates: six merge fields, friendly-coach tone, preview/test send (v1.9.20)
- **[ADMIN-TUTORIAL.md](guides/ADMIN-TUTORIAL.md)** - Admin/support staff guide: understanding the system, adjusting points, and troubleshooting
- **[TEST-PLAN.md](guides/TEST-PLAN.md)** - Functional QA test plan covering all referral and points scenarios
- **[TESTING.md](guides/TESTING.md)** - Developer testing guide (PHPUnit & Cypress setup)
- **[TESTS-QUICK-START.md](guides/TESTS-QUICK-START.md)** - Quick start guide for running tests
- **[TEST-QUICK-REFERENCE.md](guides/TEST-QUICK-REFERENCE.md)** - Quick reference for test commands
- **[WPML-SETUP.md](guides/WPML-SETUP.md)** - Multilingual setup with WPML
- **[CSV-IMPORT-FORMATS.md](guides/CSV-IMPORT-FORMATS.md)** - CSV import format specifications

### 🔧 Technical Documentation (`/technical/`)

Technical analysis and architecture documentation:

- **[FINANCIAL-MODEL-ANALYSIS.md](technical/FINANCIAL-MODEL-ANALYSIS.md)** - Financial model and calculations
- **[PERFORMANCE-OPTIMIZATIONS.md](technical/PERFORMANCE-OPTIMIZATIONS.md)** - Performance optimization strategies
- **[CHECKOUT-PERFORMANCE-ANALYSIS.md](technical/CHECKOUT-PERFORMANCE-ANALYSIS.md)** - Checkout flow performance analysis

### 📋 Planning Documents (`/planning/`)

Project planning and specifications:

- **[ROADMAP.md](planning/ROADMAP.md)** - Complete implementation roadmap and TODO list
- **[Customer-referral-plan.md](planning/Customer-referral-plan.md)** - Original project plan and requirements
- **[Customer-Referral-System-Test-Plan-.md](planning/Customer-Referral-System-Test-Plan-.md)** - Comprehensive test plan
- **[Referral System - 2025.md](planning/Referral System - 2025.md)** - 2025 roadmap and enhancements

### 📦 Archive (`/archive/`)

Historical documentation and migration notes:

- **[BUGFIX-CSV-IMPORT.md](archive/BUGFIX-CSV-IMPORT.md)** - CSV import bug fixes
- **[CSV-IMPORT-BUGFIX-SUMMARY.md](archive/CSV-IMPORT-BUGFIX-SUMMARY.md)** - CSV import bug summary
- **[CSV-TITLE-ROW-FIX.md](archive/CSV-TITLE-ROW-FIX.md)** - CSV title row fix
- **[MULTILINGUAL-DEPLOYMENT-SUMMARY.md](archive/MULTILINGUAL-DEPLOYMENT-SUMMARY.md)** - Multilingual deployment notes
- **[POINT-CONFIGURATION-UPDATE-LIST.md](archive/POINT-CONFIGURATION-UPDATE-LIST.md)** - Points configuration updates

## ⚙️ Technical Quick Reference

### Customer Balance Meta Keys
- **`intersoccer_points_balance`** — canonical loyalty-points balance (redeemable at checkout)
- **`intersoccer_customer_credits`** — legacy referral-credits key; kept in sync by older flows

For details, see the README "Technical Notes" section or [ADMIN-TUTORIAL.md](guides/ADMIN-TUTORIAL.md).

### Import / Export
- **CSV only** in this plugin — see [CSV-IMPORT-FORMATS.md](guides/CSV-IMPORT-FORMATS.md)
- **Excel/XLSX** exports live in the sibling plugin `intersoccer-reports-rosters` (GitHub: `legit-ninja/reports-rosters`)

---

## 🚀 Quick Links

### For Developers
- Start with [TESTING.md](guides/TESTING.md) to understand the test suite
- Review [FINANCIAL-MODEL-ANALYSIS.md](technical/FINANCIAL-MODEL-ANALYSIS.md) for business logic
- Check [PERFORMANCE-OPTIMIZATIONS.md](technical/PERFORMANCE-OPTIMIZATIONS.md) for best practices

### For Administrators
- Setup multilingual support: [WPML-SETUP.md](guides/WPML-SETUP.md)
- Import coaches: [CSV-IMPORT-FORMATS.md](guides/CSV-IMPORT-FORMATS.md)

### For Admins & Support Staff
- Understanding the system and troubleshooting: [ADMIN-TUTORIAL.md](guides/ADMIN-TUTORIAL.md)

### For QA/Testing
- Functional test plan: [TEST-PLAN.md](guides/TEST-PLAN.md)
- Quick start: [TESTS-QUICK-START.md](guides/TESTS-QUICK-START.md)
- Full developer testing guide: [TESTING.md](guides/TESTING.md)
- Command reference: [TEST-QUICK-REFERENCE.md](guides/TEST-QUICK-REFERENCE.md)

## 📝 Contributing to Documentation

When adding new documentation:
1. Place in the appropriate subdirectory based on content type
2. Use descriptive filenames with UPPERCASE-KEBAB-CASE.md
3. Update this INDEX.md file
4. Link from main README.md if it's user-facing

## 🧹 Documentation Maintenance

This documentation structure was reorganized on December 5, 2025 to:
- Remove conversation-specific session notes
- Organize by content type (guides, technical, planning, archive)
- Improve discoverability and maintenance
