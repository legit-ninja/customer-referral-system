# Changelog

## 1.9.11 — 2026-09-11

### Added
- Share CTA with personal link and referral code (#35)
- Backend share-link + loyalty points slice (#34)
- Checkout-only points redeem UI (#39)
- Admin rate clarity copy improvements (#33)

### Changed
- Version bump to 1.9.11 (#46)

### Documentation
- Docs sync for share-link + loyalty product truth (closes #32)
- Updated README Configuration and Features to reflect current product model
- Added supersession banner to FINANCIAL-MODEL-ANALYSIS.md
- Added product truth guide at docs/guides/PRODUCT-TRUTH-SHARE-LOYALTY.md

**Related:** Epic #25 (Quill: share-link + loyalty)

---

## 1.7.25 — 2026-07-25

### Changed
- `deploy.sh` skips missing PointsMigration PHPUnit files cleanly to avoid false deploy-gate failures when optional migration tests are absent.

