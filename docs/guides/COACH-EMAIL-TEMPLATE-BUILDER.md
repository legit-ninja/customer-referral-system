# Coach Email Template Builder

Admin guide for creating and managing coach referral campaign emails.

**Version:** 1.9.20 (Epic #12)  
**Related:** [CHANGELOG](../CHANGELOG.md) | [README Features](../../README.md#-administration)

---

## Field Reference

All six merge fields are available in the template builder. The system substitutes these placeholders with coach-specific data when sending.

| Field | Placeholder | Required on send? | Purpose | Example resolved value |
|-------|-------------|-------------------|---------|------------------------|
| Coach name | `{{coach_name}}` | Yes — fail if missing | Greeting / address | `Alex` |
| Referral code | `{{referral_code}}` | Yes — fail if missing | Coach's referral code | `COACH-ALEX-24` |
| Campaign name | `{{campaign_name}}` | Prefer present | Named campaign context | `Autumn Kickoff` |
| Share URL | `{{share_url}}` | Yes — fail if missing | Shareable referral link | `https://intersoccer.ch/r/COACH-ALEX-24` |
| Campaign end date | `{{campaign_end_date}}` | Prefer present | Expiry (EU day-month-year for CH) | `30 November 2026` |
| CTA URL | `{{cta_url}}` | Prefer present | Primary call-to-action | `https://intersoccer.ch/coach/referrals` |

---

## Example Starter Template (Friendly Coach)

The default starter template uses a warm, encouraging, peer-to-peer tone.

### Subject

```
{{campaign_name}} is live — your code {{referral_code}} is ready to share
```

### Body

```
Hey {{coach_name}},

Quick one from the InterSoccer team — {{campaign_name}} is open and your referral link is ready whenever you are.

Your code: {{referral_code}}
Share link: {{share_url}}

Families who sign up through you help keep the community growing, and this campaign runs through {{campaign_end_date}}.

When you're ready, grab everything here:
{{cta_url}}

Thanks for coaching with us — we appreciate you.

— The InterSoccer team
```

### Shorter Alternate Subject (Optional)

```
{{coach_name}}, your {{campaign_name}} referral link is ready
```

---

## Engineering Notes

- **Preview:** Shows sample resolved values for all six fields before send.
- **Fail on missing required data:** Sends fail cleanly when `coach_name`, `referral_code`, or `share_url` is missing. Unresolved placeholders must not reach the inbox.
- **Subset usage:** Templates may use a subset of the six fields; any field that appears must resolve.
- **Language:** EN first; FR/DE out of scope for this slice.

---

## Admin Workflow

1. Navigate to **InterSoccer > Coach Email Templates** in WordPress admin
2. Create a new template or duplicate an existing one
3. Use the **Insert Field** controls to add merge fields to subject or body
4. **Preview** with sample data to verify field substitution
5. **Test Send** to a designated admin address before production use
6. Archive templates that are no longer needed (they can be restored later)

---

## Related

- Epic [#12](https://github.com/legit-ninja/customer-referral-system/issues/12) — Coach Email Template Builder
- Quill [#18](https://github.com/legit-ninja/customer-referral-system/issues/18) — Docs-ready sync
- Eng PR [#56](https://github.com/legit-ninja/customer-referral-system/pull/56)
