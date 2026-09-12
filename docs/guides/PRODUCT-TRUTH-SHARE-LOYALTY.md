# Product Truth: Share-Link + Loyalty (v1.9.11)

> **Status:** Frozen acceptance criteria for epic #25  
> **Version:** 1.9.11  
> **Shipped:** 2026-09-11

---

## Frozen Product Model

| Feature | Shipped Behavior |
|---------|------------------|
| Referral mechanism | Unique personal **share link + referral code** |
| Points earn rate | Admin-configurable (example: CHF 10 spent = 1 point → CHF 500 purchase = 50 points) |
| Points redemption | **Checkout only** |
| Coach commission | Admin-configurable (example: 10% of purchases made with their referral code) |
| Referral code stacking | **Not allowed** — one referral code per order |

All rates are configurable in WordPress admin under **InterSoccer > Referral Settings**.

---

## Shipped PRs (Epic #25)

| PR | Description |
|----|-------------|
| #33 | Admin rate clarity copy |
| #34 | Backend share-link + loyalty slice |
| #35 | Share CTA with personal link/code |
| #39 | Checkout-only redeem UI |
| #46 | Version bump to 1.9.11 |

Docs sync: #32 (this documentation update)

---

## Superseded Concepts

The following concepts appear in historical planning/analysis documents but are **not** part of the shipped product:

| Concept | Source Doc | Status |
|---------|-----------|--------|
| Multi-tier commission (15%/7.5%/5%) | FINANCIAL-MODEL-ANALYSIS.md | Not shipped |
| Season CHF bonuses (5/8/15 CHF) | FINANCIAL-MODEL-ANALYSIS.md | Not shipped |
| Referrer 250 points + friend 10% first booking | FINANCIAL-MODEL-ANALYSIS.md | Not shipped |
| Milestone bonuses (+100 @ 5 referrals, +250 @ 10) | FINANCIAL-MODEL-ANALYSIS.md | Not shipped |
| Legacy free-at-6+ / name-mention CHF 30 | Planning docs | Not shipped |

These documents are retained for historical/psychological analysis and are marked with supersession banners.

---

## Related Documentation

- [CHANGELOG.md](../CHANGELOG.md) — version history including 1.9.11
- [README Configuration](../../README.md#configuration) — current admin-configurable settings
- [FINANCIAL-MODEL-ANALYSIS.md](../technical/FINANCIAL-MODEL-ANALYSIS.md) — historical analysis (superseded for product truth)
