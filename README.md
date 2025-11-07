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
📊 See: [Roadmap](TODO-REORGANIZED.md) | [Test Coverage](docs/COMPLETE-TEST-COVERAGE-REPORT.md) | [Phase 0 Complete](docs/PHASE0-100-PERCENT-COMPLETE.md)

---

## Features

### 🎯 Core Referral System
- **Coach Referral Program**: Coaches can generate unique referral links to earn commissions
- **Multi-Tier Commission Structure**: First, second, and third-level referral commissions
- **Customer Partnerships**: Long-term relationships between coaches and customers
- **Referral Code Tracking**: Automatic tracking and attribution of referrals

### 🎮 Gamification & Achievements
- **Tier System**: Bronze, Silver, Gold, and Platinum coach tiers based on performance
- **Achievement System**: Points and badges for various accomplishments
- **Performance Tracking**: Monthly performance metrics and leaderboards
- **Loyalty Bonuses**: Additional rewards for customer retention

### 💰 Commission & Rewards
- **Dynamic Commission Rates**: Configurable rates for different referral levels
- **Loyalty Bonuses**: Bonuses for repeat customers and long-term partnerships
- **Retention Bonuses**: Rewards for customers returning for multiple seasons
- **Network Effect Bonuses**: Additional incentives for building referral networks

### 📊 Analytics & Reporting
- **Real-time Dashboards**: Separate dashboards for coaches and customers
- **Performance Analytics**: Detailed metrics on referrals, conversions, and earnings
- **Admin Reports**: Comprehensive system-wide analytics
- **Weekly Email Reports**: Automated performance summaries

### 🔧 Administration
- **Admin Dashboard**: Complete system management interface
- **Coach Management**: User role management and performance oversight
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

See `docs/WPML-SETUP.md` for detailed configuration guide (repository only).

## Configuration

### Commission Settings
- **First Level**: 15% (configurable)
- **Second Level**: 7.5% (configurable)
- **Third Level**: 5% (configurable)

### Loyalty Bonuses
- **First Season**: 5 CHF
- **Second Season**: 8 CHF
- **Third Season**: 15 CHF

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
- Available credits display
- Referral link sharing
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
│   ├── class-commission-calculator.php # Commission calculations
│   ├── class-dashboard.php          # Dashboard rendering
│   ├── class-admin-dashboard.php    # Admin interface
│   ├── class-coach-admin-dashboard.php # Coach admin features
│   ├── class-elementor-widgets.php  # Elementor integration
│   └── class-utils.php              # Utility functions
├── assets/                          # Frontend assets
│   ├── css/                         # Stylesheets
│   └── js/                          # JavaScript files
├── templates/                       # Template files
│   └── dashboard-template.php       # Dashboard template
├── elementor/                       # Elementor integration
│   └── widgets/                     # Elementor widgets
└── languages/                       # Translation files
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
./run-phase0-tests.sh

# Run all tests
php vendor/bin/phpunit --testdox

# Run specific test suite
php vendor/bin/phpunit tests/PointsManagerTest.php --testdox
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

### Localization
- Text domain: `intersoccer-referral`
- Translation ready with `load_plugin_textdomain()`
- Supports RTL languages

## Documentation

### 📚 Complete documentation available in `/docs/` folder:

**Quick Start:**
- [docs/TEST-QUICK-REFERENCE.md](docs/TEST-QUICK-REFERENCE.md) - Quick test commands
- [docs/TESTING.md](docs/TESTING.md) - Comprehensive testing guide
- [docs/DEV-TESTING-GUIDE.md](docs/DEV-TESTING-GUIDE.md) - Dev server testing guide

**Phase 0 (Current):**
- [docs/PHASE0-PROGRESS.md](docs/PHASE0-PROGRESS.md) - Current implementation progress
- [docs/SESSION-SUMMARY.md](docs/SESSION-SUMMARY.md) - Latest session summary
- [docs/DEPLOYMENT-READY-CHECKLIST.md](docs/DEPLOYMENT-READY-CHECKLIST.md) - Pre-deployment checklist

**Deployment:**
- [docs/DEPLOYMENT-TEST-FLOW.md](docs/DEPLOYMENT-TEST-FLOW.md) - Visual deployment flow
- [docs/VERIFICATION-TESTS-RUN-FIRST.md](docs/VERIFICATION-TESTS-RUN-FIRST.md) - Test execution proof

**Testing & Coverage:**
- [docs/TEST-COVERAGE-REPORT.md](docs/TEST-COVERAGE-REPORT.md) - Detailed coverage analysis
- [docs/ANSWER-TEST-COVERAGE.md](docs/ANSWER-TEST-COVERAGE.md) - Coverage Q&A

**System Documentation:**
- [docs/Customer-referral-plan.md](docs/Customer-referral-plan.md) - Original proposal
- [docs/FINANCIAL-MODEL-ANALYSIS.md](docs/FINANCIAL-MODEL-ANALYSIS.md) - Financial model
- [docs/PERFORMANCE-OPTIMIZATIONS.md](docs/PERFORMANCE-OPTIMIZATIONS.md) - Performance guide

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