# InterSoccer Referral System

A comprehensive WordPress plugin that implements an advanced coach referral program with gamification, analytics, and customer loyalty features for InterSoccer.

---

## 🏆 Enterprise-Grade Quality

```
╔════════════════════════════════════════════════════════════╗
║  ✅ 1,210 Tests | 100% Passing | 100% Coverage | 🏰 Fortress ║
║  🎯 60% Complete | 5 of 10 Phases Done | Production-Ready  ║
╚════════════════════════════════════════════════════════════╝
```

**Production-Ready** with 100% test coverage across all active classes!  
📊 See: [Testing Guide](docs/guides/TESTING.md) | [Financial Model](docs/technical/FINANCIAL-MODEL-ANALYSIS.md) | [Documentation Index](docs/INDEX.md)

---

## Features

### 🎯 Core Referral System
- **Share Link & Referral Code**: Customers and coaches get a unique personal share link with referral code
- **Coach Commission**: Coaches earn commission on purchases made with their referral code (e.g., 10% — admin-configurable)
- **Single-Code Rule**: One referral code per order — codes cannot be stacked
- **Referral Code Tracking**: Automatic tracking and attribution of referrals

### 🎮 Gamification & Achievements
- **Tier System**: Bronze, Silver, Gold, and Platinum coach tiers based on performance
- **Achievement System**: Points and badges for various accomplishments
- **Performance Tracking**: Monthly performance metrics and leaderboards

### 💰 Commission & Loyalty Points
- **Loyalty Points Earn**: Customers earn points based on spend (default example: CHF 10 spent = 1 point)
- **Checkout-Only Redeem**: Customers redeem points at checkout only
- **Admin-Configurable Rates**: All commission and points rates are configurable in WordPress admin

### 📊 Analytics & Reporting
- **Real-time Dashboards**: Separate dashboards for coaches and customers
- **Performance Analytics**: Detailed metrics on referrals, conversions, and earnings
- **Admin Reports**: Comprehensive system-wide analytics
- **Weekly Email Reports**: Automated performance summaries

### 🔧 Administration
- **Admin Dashboard**: Complete system management interface
- **Coach Management**: User role management and performance oversight
- **Coach Email Template Builder**: Create referral campaign emails with six merge fields and friendly-coach tone — see [guide](docs/guides/COACH-EMAIL-TEMPLATE-BUILDER.md)
- **Settings Configuration**: Flexible configuration of all system parameters
- **Demo Data Tools**: Populate and clear demo data for testing

### 🎨 User Interface
- **Elementor Integration**: Drag-and-drop widgets for easy page building
- **Responsive Design**: Mobile-friendly dashboards and interfaces
- **AJAX-Powered**: Smooth, dynamic user interactions
- **Customizable Templates**: Flexible template system for customization

## Requirements

- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher
- **MySQL**: 5.6 or higher
- **WooCommerce**: Required for e-commerce integration
- **Elementor**: Optional, for enhanced page building
- **WPML**: Optional, for multilingual support (English, French, German)

## Installation

1. Download the plugin files
2. Upload the `customer-referral-system` folder to `/wp-content/plugins/`
3. Activate the plugin through the WordPress admin dashboard
4. Configure settings in **InterSoccer > Referral Settings**

## Deployment

### Quick Deployment to Server

```bash
# First time setup
cp deploy.local.sh.example deploy.local.sh
nano deploy.local.sh  # Set your server credentials

# Deploy to dev server
./deploy.sh

# Deploy with cache clearing (recommended)
./deploy.sh --clear-cache

# Preview before deploying
./deploy.sh --dry-run

# Run tests before deploying (when configured)
./deploy.sh --test
```

### What Gets Deployed
The deployment script uploads only production-ready files:
- ✅ PHP code (`*.php`)
- ✅ Assets (CSS, JS)
- ✅ Translation files (`languages/*.mo`)
- ✅ Templates
- ✅ README.md

### What Stays Private
Development files are automatically excluded:
- 🔒 `docs/` folder (internal documentation)
- 🔒 `*.sh` files (deployment scripts with server paths)
- 🔒 `vendor/` (Composer dependencies)
- 🔒 `tests/` (PHPUnit tests)
- 🔒 `*.log` files (debug logs)
- 🔒 Development configs (`composer.json`, `phpunit.xml`)

**Result**: Clean, secure production deployment

## Multilingual Support (WPML)

### Supported Languages
- 🇬🇧 **English** (default)
- 🇫🇷 **French (Switzerland)** - fr_CH
- 🇩🇪 **German (Switzerland)** - de_CH

### Translation Coverage
All customer-facing features are fully translated:
- ✅ Checkout page (referral code input, loyalty points)
- ✅ Cart fees and discounts
- ✅ Validation messages
- ✅ Success/error notifications
- ✅ Email notifications
- ✅ Order notes

### Setup WPML
1. Ensure WPML and WPML String Translation are active
2. Deploy plugin: `./deploy.sh --clear-cache`
3. Translations automatically load based on customer's language
4. Test in each language via WPML language switcher

See [docs/guides/WPML-SETUP.md](docs/guides/WPML-SETUP.md) for detailed configuration guide (repository only).

## Configuration

### Current Product Model (v1.9.19)

This plugin implements a **share-link + loyalty points** system. Key behaviors:

| Setting | Example Default | Notes |
|---------|-----------------|-------|
| Points earn rate | CHF 10 spent = 1 point | Admin-configurable |
| Points redemption | Checkout only | Customers redeem accumulated points when placing an order |
| Coach commission | 10% of referred purchases | Admin-configurable; applies when customer uses coach's referral code |
| Referral code stacking | Not allowed | One referral code per order |

All rates are **admin-configurable** in WordPress under InterSoccer > Referral Settings.

> **Note:** Historical planning documents (e.g., `docs/technical/FINANCIAL-MODEL-ANALYSIS.md`, `docs/planning/`) contain multi-tier commission structures, season CHF bonuses, and gamification milestones that were analyzed but **not shipped** in the current product. Those docs are retained for historical/analysis purposes and are clearly marked as superseded.

### Tier Thresholds
- **Silver**: 5 successful referrals
- **Gold**: 10 successful referrals
- **Platinum**: 20 successful referrals

## Database Tables

The plugin creates the following custom database tables:

- `wp_intersoccer_referrals`: Core referral tracking
- `wp_intersoccer_coach_performance`: Monthly performance metrics
- `wp_intersoccer_coach_achievements`: Achievement and badge system
- `wp_intersoccer_customer_partnerships`: Customer-coach relationships
- `wp_intersoccer_customer_activities`: Activity tracking for gamification

## User Roles & Capabilities

### Coach Role
- `view_referral_dashboard`: Access to coach dashboard
- `manage_referrals`: Manage personal referrals
- `view_coach_reports`: View performance reports

### Administrator
- All coach capabilities plus:
- `manage_coach_system`: Full system administration

## Shortcodes

### `[intersoccer_coach_dashboard]`
Displays the coach referral dashboard with:
- Referral link generation
- Performance metrics
- Commission tracking
- Achievement display

## Elementor Widgets

### Customer Dashboard Widget
- Customer referral statistics
- Points balance display
- Personal share link
- Progress tracking

### Coach Dashboard Widget
- Real-time performance metrics
- Commission earnings
- Referral network visualization
- Achievement showcase

## API Endpoints

### AJAX Endpoints
- `wp_ajax_intersoccer_copy_referral_link`: Generate referral links
- `wp_ajax_intersoccer_get_performance_data`: Retrieve performance metrics
- `wp_ajax_intersoccer_update_settings`: Admin settings updates

## Hooks & Filters

### Actions
- `intersoccer_referral_completed`: Fires when a referral converts
- `intersoccer_coach_tier_changed`: Fires when coach tier changes
- `intersoccer_daily_cleanup`: Daily maintenance tasks
- `intersoccer_weekly_reports`: Weekly report generation

### Filters
- `intersoccer_commission_rates`: Modify commission rates
- `intersoccer_tier_thresholds`: Adjust tier requirements
- `intersoccer_email_templates`: Customize email content

## File Structure

```
customer-referral-system/
├── customer-referral-system.php     # Main plugin file
├── includes/                        # Core classes
│   ├── class-referral-handler.php   # Referral logic
│   ├── class-commission-manager.php # Commission calculations
│   ├── class-points-manager.php     # Points system
│   ├── class-admin-settings.php     # Admin interface & simulator
│   ├── class-simulator.php          # Referral simulator
│   ├── class-dashboard.php          # Dashboard rendering
│   └── class-utils.php              # Utility functions
├── assets/                          # Frontend assets
│   ├── css/                         # Stylesheets
│   └── js/                          # JavaScript files
├── templates/                       # Template files
│   └── dashboard-template.php       # Dashboard template
├── elementor/                       # Elementor integration
│   └── widgets/                     # Elementor widgets
├── languages/                       # Translation files
├── tests/                           # PHPUnit test suite
├── scripts/                         # Development scripts
│   ├── run-phase0-tests.sh          # Test runner
│   └── test-verification.php        # Test verification
└── docs/                            # Documentation
    ├── guides/                      # User guides
    ├── technical/                   # Technical docs
    └── planning/                    # Planning documents
```

## Development

### Coding Standards
- Follows WordPress Coding Standards
- PSR-4 autoloading for classes
- Proper error handling and logging
- Secure database operations with prepared statements

### Testing

**🏆 ENTERPRISE-GRADE TEST COVERAGE: 1,210 Tests!**

```
Phase 0 Critical Tests (BLOCKING):     154 tests ✅
New Comprehensive Tests (WARNING):     720 tests ✅
Additional Coverage Tests:             266 tests ✅
Full Integration Suite:                ~70 tests ✅
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
TOTAL:                                 1,210 tests
PASS RATE:                             100% ✅
COVERAGE:                              100% (ALL 21 active classes)
```

**Test Categories:**
- ✅ **Unit Tests** (~310 tests): Individual method testing, edge cases
- ✅ **Integration Tests** (~120 tests): Order flow, WooCommerce integration
- ✅ **Security Tests** (85 tests): SQL injection, XSS, CSRF prevention
- ✅ **Regression Tests** (ALL): Prevent old bugs from returning

**Running Tests:**
```bash
# Run Phase 0 critical tests
./scripts/run-phase0-tests.sh

# Run all tests
php vendor/bin/phpunit --testdox

# Run specific test suite
php vendor/bin/phpunit tests/PointsManagerTest.php --testdox

# Simple test verification
php scripts/test-verification.php
```

**Deployment Protection:**
- 154 critical tests MUST pass before deployment
- If ANY fail → deployment BLOCKED
- Comprehensive regression protection
- See: `docs/COMPLETE-TEST-COVERAGE-REPORT.md` for details

**Cypress E2E Tests:**
- Available in: `intersoccer-player-management-tests` repository
- Tests checkout flow, points redemption, user journeys
- Run separately from PHPUnit suite

**G-EARN / Cypress Earn-Smoke Requirements:**

> ⚠️ **Important:** G-EARN and Cypress earn-smoke tests require `intersoccer_points_allocation_method=instant`.
> See [#44](https://github.com/legit-ninja/customer-referral-system/issues/44) for details.

The plugin supports two points allocation modes:
- **instant** (default): Points allocated immediately on order processing/completion
- **deferred**: Points queued for weekly cron batch processing

In deferred mode, points are not allocated immediately when an order is completed — they are queued for weekly cron processing. This causes G-EARN and earn-smoke tests to fail because they expect points to be credited on order completion.

**For sandbox/test environments:**
1. Set `IS_SANDBOX=true` in your `deploy.local.sh`
2. The deploy script will automatically set `intersoccer_points_allocation_method=instant` post-deploy

**Manual one-shot fix:**
```bash
wp option update intersoccer_points_allocation_method instant
```

**Do NOT change production to instant** unless that is the desired production behavior — this setting only affects sandbox/local/legit.ninja test deployments.

### Localization
- Text domain: `intersoccer-referral`
- Translation ready with `load_plugin_textdomain()`
- Supports RTL languages

## Technical Notes

### Customer Balance Meta Keys

The plugin uses two user-meta keys for customer balances:

| Meta Key | Purpose | Status |
|----------|---------|--------|
| `intersoccer_points_balance` | **Canonical.** The redeemable loyalty-points balance shown to customers and used at checkout. Managed by `InterSoccer_Points_Manager`. | Active |
| `intersoccer_customer_credits` | Legacy key formerly used by referral-credit flows. **Dual-write stopped as of issue #36.** Existing values remain for historical reference but are no longer updated. | Deprecated — read-only for migration purposes. |

**Guideline:** When adjusting balances, use the **Referrals > Customer Points** admin page — it updates `intersoccer_points_balance` and logs changes.

#### Migration from `intersoccer_customer_credits` (Issue #36)

As of PR #43 (issue #36), all earn/redeem operations write exclusively to `intersoccer_points_balance`:

1. **Referrer reward points** — now credited only to `intersoccer_points_balance`
2. **New customer bonus points** — now credited only to `intersoccer_points_balance`
3. **Gift points** — now uses `intersoccer_points_balance` instead of `intersoccer_customer_credits`

**Legacy data migration:** Existing `intersoccer_customer_credits` values are **not automatically migrated**. If a customer's `intersoccer_points_balance` is 0 but `intersoccer_customer_credits` has a non-zero value, an admin can manually reconcile via the Customer Points page. For bulk migration, use WP-CLI or a custom script:

```php
// Example: One-time migration script (run via WP-CLI or custom admin action)
$users = get_users(['meta_key' => 'intersoccer_customer_credits', 'meta_compare' => '>', 'meta_value' => 0]);
foreach ($users as $user) {
    $legacy = (int) get_user_meta($user->ID, 'intersoccer_customer_credits', true);
    $current = (int) get_user_meta($user->ID, 'intersoccer_points_balance', true);
    if ($legacy > 0 && $current === 0) {
        update_user_meta($user->ID, 'intersoccer_points_balance', $legacy);
        // Optionally log: intersoccer_referral_log("Migrated {$legacy} credits to points for user {$user->ID}");
    }
}
```

**Note:** The `intersoccer_customer_credits` key is retained read-only for backward compatibility with any external integrations. New code must not write to it.

### Import / Export Policy

This plugin supports **CSV** for all data imports and exports (see [CSV-IMPORT-FORMATS.md](docs/guides/CSV-IMPORT-FORMATS.md)).

**Excel / XLSX exports are not provided here.** Excel export functionality lives in the sibling plugin `intersoccer-reports-rosters` (GitHub: `legit-ninja/reports-rosters`). Do not add PhpSpreadsheet or XLSX generation to this plugin; keep the export surface intentionally small and CSV-only.

---

## Documentation

### 📚 Complete documentation available in `/docs/` folder

Documentation is organized by type for easy navigation:

**📖 User Guides** (`/docs/guides/`)
- [TESTING.md](docs/guides/TESTING.md) - Comprehensive testing guide
- [TESTS-QUICK-START.md](docs/guides/TESTS-QUICK-START.md) - Quick start for running tests
- [TEST-QUICK-REFERENCE.md](docs/guides/TEST-QUICK-REFERENCE.md) - Quick test command reference
- [WPML-SETUP.md](docs/guides/WPML-SETUP.md) - Multilingual setup guide
- [CSV-IMPORT-FORMATS.md](docs/guides/CSV-IMPORT-FORMATS.md) - CSV import formats

**🔧 Technical Documentation** (`/docs/technical/`)
- [FINANCIAL-MODEL-ANALYSIS.md](docs/technical/FINANCIAL-MODEL-ANALYSIS.md) - Financial model & calculations
- [PERFORMANCE-OPTIMIZATIONS.md](docs/technical/PERFORMANCE-OPTIMIZATIONS.md) - Performance strategies
- [CHECKOUT-PERFORMANCE-ANALYSIS.md](docs/technical/CHECKOUT-PERFORMANCE-ANALYSIS.md) - Checkout performance

**📋 Planning & Specifications** (`/docs/planning/`)
- [ROADMAP.md](docs/planning/ROADMAP.md) - Complete implementation roadmap
- [Customer-referral-plan.md](docs/planning/Customer-referral-plan.md) - Original project plan
- [Customer-Referral-System-Test-Plan-.md](docs/planning/Customer-Referral-System-Test-Plan-.md) - Test plan
- [Referral System - 2025.md](docs/planning/Referral System - 2025.md) - 2025 roadmap

**📖 Full Index:** [docs/INDEX.md](docs/INDEX.md) - Complete documentation catalog

## Security Features

- **Nonce Verification**: All AJAX requests protected
- **Capability Checks**: Proper user permission validation
- **Prepared Statements**: SQL injection prevention
- **Input Sanitization**: All user inputs sanitized
- **CSRF Protection**: Cross-site request forgery prevention

## Performance

- **Database Optimization**: Proper indexing on key tables
- **Lazy Loading**: Assets loaded only when needed
- **Caching**: WordPress object cache utilization
- **Background Processing**: Scheduled tasks for heavy operations

## Changelog

### Version 1.0.0
- Initial release
- Core referral system implementation
- Gamification features
- Elementor integration
- Admin dashboard
- Comprehensive analytics

## Support

For support, bug reports, or feature requests:
- Create an issue on GitHub
- Contact the development team
- Check the documentation wiki

## License

GPL-2.0+
See LICENSE file for full license details.

## Credits

Developed by Jeremy Lee for InterSoccer
Special thanks to the InterSoccer team for requirements and testing.