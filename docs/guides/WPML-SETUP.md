# WPML Setup Guide - InterSoccer Referral System

## Overview

This plugin supports **English, French (fr_CH), and German (de_CH)** via WPML String Translation.

## Quick Setup

### 1. Install Translation Files

Translation files are already included in `languages/`:
- `intersoccer-referral-fr_CH.mo` - French (Switzerland)
- `intersoccer-referral-de_CH.mo` - German (Switzerland)
- `intersoccer-referral.pot` - Template file

###  2. Deploy Translations

The deployment script automatically copies `.mo` files to the global WordPress languages directory:

```bash
./deploy.sh --clear-cache
```

This copies files from:
- `languages/intersoccer-referral-fr_CH.mo`

To:
- `/wp-content/languages/plugins/intersoccer-referral-fr_CH.mo`

### 3. Verify in WPML

1. Go to **WPML → String Translation**
2. Select domain: **intersoccer-referral**
3. You should see all translatable strings

### 4. Test Translations

1. Switch WPML language to **French**
2. Go to cart/checkout page
3. Verify strings are translated:
   - "Coach Referral Code (Optional)" → "Code de parrainage de l'entraîneur (Facultatif)"
   - "Use Loyalty Points" → "Utiliser les points de fidélité"
   - "Link copied!" → "Lien copié !"

4. Repeat for **German**

## Translatable Features

### Frontend (Customer-Facing):

#### Checkout Page:
- Coach referral code input
- Loyalty points redemption
- Apply buttons ("Apply Code", "Apply All", "Apply Max")
- Validation messages

#### Cart/Checkout Fees:
- "Referral Credits Discount"
- "Coach Referral Discount"
- "Points Discount"

#### User Messages:
- Success notifications
- Error messages
- Loading states

### Backend (Admin-Facing):

#### User Roles:
- Coach
- Content Creator
- Partner

#### Email Notifications:
- Weekly reports
- Partnership notifications

#### Order Notes:
- Points redemption notes
- Coach bonus award notes
- Credit deduction notes

## Current Translation Coverage

### French (fr_CH): ✅ Complete
All customer-facing strings translated:
- Checkout interface
- Cart fees
- Validation messages
- User notifications

### German (de_CH): ✅ Complete
All customer-facing strings translated:
- Checkout interface
- Cart fees
- Validation messages
- User notifications

## Adding New Translations

### Option 1: Update .po Files Directly

1. Edit `languages/intersoccer-referral-fr_CH.po`
2. Add new msgid/msgstr pairs
3. Compile: `msgfmt -o intersoccer-referral-fr_CH.mo intersoccer-referral-fr_CH.po`
4. Deploy: `./deploy.sh --clear-cache`

### Option 2: Use WPML String Translation

1. Add new translatable string in code:
   ```php
   __('Your new string', 'intersoccer-referral')
   ```

2. WPML will auto-scan and register it
3. Translate via **WPML → String Translation**
4. Export from WPML to update .po files

## Translation Best Practices

### 1. Swiss German vs Standard German
Current translations use **Swiss German conventions**:
- CHF currency (not EUR)
- Swiss terminology
- Formal address (Sie/Ihnen)

### 2. Consistency with Other Plugins
Match terminology from:
- Player Management plugin
- Product Variations plugin
- Reports & Rosters plugin

Common terms:
- "Attendee" / "Participant" → "Participant" (FR), "Teilnehmer" (DE)
- "Coach" → "Entraîneur" (FR), "Trainer" (DE)
- "Credits" → "Crédits" (FR), "Credits" (DE)

### 3. Customer-Facing Priority
Focus translation efforts on:
1. **Checkout page** (high visibility)
2. **Error messages** (critical UX)
3. **Email notifications** (direct customer communication)
4. **Cart/Order summaries** (financial clarity)

Admin interfaces can remain in English if needed.

## Troubleshooting

### Translations Not Loading

1. **Check locale**:
   ```php
   // Add to wp-config.php for debugging
   define('WP_DEBUG', true);
   ```
   Check debug.log for: `InterSoccer Referral: Loaded translations from...`

2. **Check file permissions**:
   ```bash
   chmod 644 languages/*.mo
   ```

3. **Clear caches**:
   ```bash
   ./deploy.sh --clear-cache
   ```

4. **Verify WPML**:
   - WPML is active
   - Language is set correctly
   - String Translation module is enabled

### Strings Still in English

1. **Hard-coded strings**: Check if string uses text domain
   ```php
   // Bad
   echo 'Apply Code';
   
   // Good
   echo __('Apply Code', 'intersoccer-referral');
   ```

2. **Wrong text domain**: Verify all use `intersoccer-referral`
   ```bash
   grep -r "__(" --include="*.php" | grep -v "intersoccer-referral"
   ```

3. **Missing translation**: Add to .po file and recompile

### WPML Not Detecting Strings

1. **Rescan plugin**:
   - WPML → Theme and plugins localization
   - Scan for strings

2. **Register manually** (if needed):
   ```php
   if (function_exists('icl_register_string')) {
       icl_register_string('intersoccer-referral', 'Your String', 'Your String');
   }
   ```

## Testing Checklist

After deploying translations:

- [ ] French cart page shows French strings
- [ ] German cart page shows German strings
- [ ] "Coach Referral Code" translates correctly
- [ ] "Use Loyalty Points" translates correctly
- [ ] Error messages translate correctly
- [ ] Success messages translate correctly
- [ ] Order notes use correct language
- [ ] Email notifications use correct language

## Maintenance

### When Adding New Features:

1. **Use text domain** for all user-facing strings
2. **Update .pot file** with new strings
3. **Add translations** to .po files
4. **Recompile** .mo files
5. **Deploy** with `--clear-cache`
6. **Test** in all languages

### Regular Updates:

- Review WPML String Translation for missing translations
- Keep .po files in sync with code changes
- Document any new translatable features
- Test after WPML plugin updates

## Language Files Structure

```
languages/
├── intersoccer-referral.pot       # Template (English source)
├── intersoccer-referral-fr_CH.po  # French source
├── intersoccer-referral-fr_CH.mo  # French compiled
├── intersoccer-referral-de_CH.po  # German source
└── intersoccer-referral-de_CH.mo  # German compiled
```

**Note**: Only `.mo` files are needed for WordPress to load translations. `.po` and `.pot` files are for editing and regenerating translations.

---

**For complete WPML configuration, see the WPML documentation at wpml.org**

