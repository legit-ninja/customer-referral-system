# Repository Organization

**Date:** December 5, 2025  
**Purpose:** Clean up documentation and improve repository structure

## Changes Made

### 1. Documentation Reorganization

Restructured `/docs/` folder with logical subdirectories:

#### Created Structure
```
docs/
├── guides/          # User-facing guides and how-tos
├── technical/       # Technical documentation and analysis
├── planning/        # Project planning and roadmaps
└── archive/         # Historical documentation
```

#### Removed Files (24 files)
Deleted conversation-specific and session-specific documentation:
- All `PHASE0-*` session summaries
- All `SESSION-*` progress reports
- All `TEST-COVERAGE-*` temporary reports
- All `ACHIEVEMENT-*` session notes
- All `DEPLOYMENT-*` temporary checklists
- `TODO-REORGANIZED.md` (moved to `planning/ROADMAP.md`)

#### Organized Files

**Guides** (`/docs/guides/`)
- `TESTING.md` - Comprehensive testing guide
- `TESTS-QUICK-START.md` - Quick start for running tests
- `TEST-QUICK-REFERENCE.md` - Quick command reference
- `WPML-SETUP.md` - Multilingual setup guide
- `CSV-IMPORT-FORMATS.md` - CSV import specifications

**Technical** (`/docs/technical/`)
- `FINANCIAL-MODEL-ANALYSIS.md` - Financial model & calculations
- `PERFORMANCE-OPTIMIZATIONS.md` - Performance strategies
- `CHECKOUT-PERFORMANCE-ANALYSIS.md` - Checkout performance analysis

**Planning** (`/docs/planning/`)
- `ROADMAP.md` - Complete implementation roadmap (moved from `todo.list`)
- `Customer-referral-plan.md` - Original project plan
- `Customer-Referral-System-Test-Plan-.md` - Test plan
- `Referral System - 2025.md` - 2025 roadmap

**Archive** (`/docs/archive/`)
- `BUGFIX-CSV-IMPORT.md` - Historical bug fixes
- `CSV-IMPORT-BUGFIX-SUMMARY.md` - Bug fix summary
- `CSV-TITLE-ROW-FIX.md` - Title row fix
- `MULTILINGUAL-DEPLOYMENT-SUMMARY.md` - Deployment notes
- `POINT-CONFIGURATION-UPDATE-LIST.md` - Configuration updates

### 2. Scripts Organization

Created `/scripts/` directory for development tools:
- `run-phase0-tests.sh` - Test runner script
- `run-simple-test.php` - Simple test verification
- `run-tests.php` - Test runner
- `test-verification.php` - Test verification script

### 3. Root Directory Cleanup

**Removed:**
- `debug.log` - Temporary debug file
- `todo.list` - Moved to `docs/planning/ROADMAP.md`

**Organized:**
- Test scripts → `/scripts/`
- Documentation → `/docs/` subdirectories

### 4. Updated Documentation References

**Updated Files:**
- `README.md` - Updated all doc links and file structure diagram
- `docs/INDEX.md` - Complete rewrite with new structure
- All internal documentation links updated

## Current Structure

```
customer-referral-system/
├── customer-referral-system.php     # Main plugin file
├── README.md                        # Main documentation
├── includes/                        # Core PHP classes
├── assets/                          # CSS & JavaScript
├── templates/                       # Template files
├── elementor/                       # Elementor widgets
├── languages/                       # Translation files
├── tests/                           # PHPUnit test suite
├── scripts/                         # Development scripts
│   ├── run-phase0-tests.sh
│   └── test-verification.php
├── docs/                            # Documentation
│   ├── INDEX.md                     # Documentation index
│   ├── guides/                      # User guides (5 files)
│   ├── technical/                   # Technical docs (3 files)
│   ├── planning/                    # Planning docs (4 files)
│   └── archive/                     # Historical docs (5 files)
├── deploy.sh                        # Deployment script
└── vendor/                          # Composer dependencies
```

## Benefits

1. **Cleaner Root Directory** - Only essential files at root level
2. **Organized Documentation** - Easy to find guides, technical docs, and planning
3. **Removed Clutter** - Deleted 24 conversation-specific documentation files
4. **Better Navigation** - Clear subdirectory structure with logical grouping
5. **Improved Maintainability** - Easier to update and manage documentation
6. **Professional Structure** - Industry-standard repository organization

## Finding Documentation

- **Quick Start:** Start with [docs/INDEX.md](INDEX.md)
- **Testing:** See [docs/guides/TESTING.md](guides/TESTING.md)
- **Technical:** See [docs/technical/](technical/)
- **Planning:** See [docs/planning/ROADMAP.md](planning/ROADMAP.md)
- **Setup:** See [docs/guides/WPML-SETUP.md](guides/WPML-SETUP.md)

## Deployment Impact

The deployment script (`deploy.sh`) already excludes the `docs/` folder, so:
- ✅ Documentation changes have **no impact** on production
- ✅ Only production-ready files are deployed
- ✅ Private development docs remain secure
- ✅ No action required for deployment



