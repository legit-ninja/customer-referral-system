# Changelog

## 1.9.19 — 2026-09-19

### Added
- ROI Active Points (Liability) label with PTS unit in admin dashboard (#54)
- Dual liability mismatch banner when Active Points differs from Total Points Balance (#54)

### Changed
- Version bump to 1.9.19 (release cut 2026-09-19)

### Fixed
- Soft-NO: "Active Credits" → "Active Points (Liability)" with PTS unit in ROI breakdown (#54)

### Soft Approvals
- Soft-STAMP ACCEPT: Financial dashboards UX (#24)
- Soft-OK: Credits→Points vocabulary renames (definitions unchanged)

**Related:** Follow-up to #24 Financial dashboards; addresses Tess Soft-glance feedback

---

## 1.9.16 — 2026-09-16

### Added
- Financial dashboards with accurate figures + Soft-STAMP UX (#24, #50)
- PHPUnit test isolation improvements (#51)

### Changed
- Version bump to 1.9.16 (release cut 2026-09-16)
- Points redeem rate corrected to 100× (1 pt = CHF 1.00) (#52)

### Fixed
- Apply-on-click 1:1 CHF redemption UI (#52)
- PHPUnit test failures and improved test isolation (#51)
- Dashboard financial sections now match InterSoccer admin/customer visual patterns (#50)

**Related:** #52 points redeem 100×, #51 PHPUnit isolation, #50 financial dashboards (#24 Soft-STAMP)

---

## 1.9.11 — 2026-09-11

### Added
- Share CTA with personal link and referral code (#35)
- Backend share-link + loyalty points slice (#34)
- Checkout-only points redeem UI (#39)
- Admin rate clarity copy improvements (#33)

### Changed
- Version bump to 1.9.11 (#46)

### Fixed
- Checkout points panel visibility sync after `updated_checkout` event (#48)

### Deploy / Testing
- IS_SANDBOX deploy sets `alloc=instant` for G-EARN smoke tests (#47)

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

