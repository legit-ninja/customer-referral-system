# 📊 CSV Import Formats - Coach Import

**Issue Fixed:** November 4, 2025  
**Problem:** CSV import failed with "Missing required columns" error  
**Solution:** Flexible column name mapping

---

## ✅ WHAT WAS FIXED

### Before (Rigid):
- Required EXACT column names: `first_name`, `last_name`, `email`
- Failed with: "First Name", "Last Name", "Email"
- Failed with: "firstname", "lastname", "email"
- No flexibility for different CSV formats

### After (Flexible):
- ✅ Accepts multiple column name variations
- ✅ Automatically maps common formats
- ✅ Normalizes headers (lowercase, spaces→underscores)
- ✅ Better error messages showing what was found
- ✅ Supports 20+ column name variations

---

## 📋 SUPPORTED COLUMN NAMES

### Required Columns:

#### First Name (ANY of these):
- `first_name` or `first name` or `First Name`
- `firstname` or `firstName` or `FirstName`
- `given_name` or `given name` or `Given Name`
- `forename`
- `name` (if it's the only name column)

#### Last Name (ANY of these):
- `last_name` or `last name` or `Last Name`
- `lastname` or `lastName` or `LastName`
- `surname` or `Surname`
- `family_name` or `family name` or `Family Name`

#### Email (ANY of these):
- `email` or `Email` or `EMAIL`
- `e-mail` or `E-mail` or `E-Mail`
- `email_address` or `Email Address`
- `mail`

### Optional Columns:

#### Phone (ANY of these):
- `phone`, `Phone`, `telephone`, `Telephone`
- `phone_number`, `Phone Number`
- `mobile`, `Mobile`

#### Specialization (ANY of these):
- `specialization`, `Specialization`
- `specialty`, `Specialty`
- `focus`, `Focus`

#### Location (ANY of these):
- `location`, `Location`
- `city`, `City`
- `region`, `Region`

#### Experience (ANY of these):
- `experience_years`, `Experience Years`
- `experience`, `Experience`
- `years_experience`, `Years Experience`

#### Bio (ANY of these):
- `bio`, `Bio`, `biography`, `Biography`
- `description`, `Description`
- `about`, `About`

#### Referral Code (ANY of these):
- `referral_code`, `Referral Code`, `coach_referral_code`
- `code` (when used as the coach referral column in import)

Imported values are normalized to uppercase letters, numbers, hyphens, and underscores. Leave blank to skip assignment, or enable **Generate Missing Codes** on import.

---

## 📁 SAMPLE CSV FILES

### Format 1: Standard (Recommended)
**File:** `assets/sample-coaches.csv`
```csv
first_name,last_name,email,referral_code,phone,specialization,location,experience_years,bio
Thomas,Mueller,thomas.mueller@example.ch,COACH-TMUELLER,+41 79 123 4567,Youth Training,Zurich,8,Bio text
```

### Format 2: Capitalized with Spaces
**File:** `assets/sample-coaches-alternative-format.csv`
```csv
First Name,Last Name,Email,Referral Code,Phone,Specialty,City,Experience
Thomas,Mueller,thomas.mueller@example.ch,COACH-TMUELLER,+41 79 123 4567,Youth Training,Zurich,8
```

### Format 3: Minimal (Only Required)
```csv
Email,First Name,Last Name
thomas.mueller@example.ch,Thomas,Mueller
sandra.weber@example.ch,Sandra,Weber
```

### Format 4: Different Name Variations
```csv
Given Name,Surname,E-mail,Telephone
Thomas,Mueller,thomas.mueller@example.ch,+41 79 123 4567
```

**ALL OF THESE WORK NOW!** ✅

---

## 🔍 TROUBLESHOOTING

### Error: "Missing required columns"

**What it means:** The CSV doesn't have the required name/email columns

**Check:**
1. Open your CSV file
2. Look at the first row (headers)
3. Make sure you have columns for:
   - First name (any variation from list above)
   - Last name (any variation from list above)
   - Email (any variation from list above)

**Example Error Message (NEW - More Helpful):**
```
Missing required columns: first_name, last_name
Found columns: Name, Email Address, Phone
Supported variations: first_name/firstname/given_name, last_name/lastname/surname, email/e-mail/email_address
```

### Error: "Invalid number of columns"

**What it means:** A data row has more or fewer columns than the header

**Fix:**
- Check for extra commas in data
- Check for missing values
- Ensure all rows have same number of columns as header

### Error: "Invalid email address"

**What it means:** Email format is incorrect

**Fix:**
- Ensure emails are valid: `name@domain.com`
- Remove spaces from emails
- Check for typos

---

## 🎯 BEST PRACTICES

### CSV Format Tips:

1. **Use UTF-8 encoding** - Especially for names with special characters
2. **Include header row** - Always have column names in first row
3. **Be consistent** - Don't mix column name formats
4. **Remove empty rows** - Delete any blank rows
5. **Check commas** - If name contains comma, enclose in quotes: `"Smith, Jr.",John`

### Recommended Tools:

- **Excel:** Save As → CSV UTF-8 (Comma delimited)
- **Google Sheets:** Download → CSV
- **Numbers (Mac):** Export → CSV

---

## 🧪 TESTING YOUR CSV

### Before Importing:

1. **Open CSV in text editor** - Check the header row
2. **Verify columns** - Match against supported variations
3. **Check data** - Look for obvious errors
4. **Count columns** - All rows should have same count

### Import Settings:

- **Update existing:** Check this if you want to update coach info
- **Leave unchecked:** To only import new coaches

---

## 📝 EXAMPLE CSVs THAT WORK

### Example 1: Standard Format ✅
```csv
first_name,last_name,email
Thomas,Mueller,thomas.mueller@example.ch
Sandra,Weber,sandra.weber@example.ch
```

### Example 2: Capitalized ✅
```csv
First Name,Last Name,Email
Thomas,Mueller,thomas.mueller@example.ch
Sandra,Weber,sandra.weber@example.ch
```

### Example 3: With Spaces ✅
```csv
Given Name,Surname,E-mail,Phone Number
Thomas,Mueller,thomas.mueller@example.ch,+41 79 123 4567
Sandra,Weber,sandra.weber@example.ch,+41 76 234 5678
```

### Example 4: Mixed Case ✅
```csv
FirstName,LastName,Email,Specialization
Thomas,Mueller,thomas.mueller@example.ch,Youth Training
Sandra,Weber,sandra.weber@example.ch,Technical Skills
```

**All automatically mapped!** ✅

---

## 🐛 COMMON ISSUES FIXED

### Issue 1: "First Name" with space
**Before:** ❌ Failed  
**After:** ✅ Maps to `first_name`

### Issue 2: "Email Address" instead of "email"
**Before:** ❌ Failed  
**After:** ✅ Maps to `email`

### Issue 3: Capitalization differences
**Before:** ❌ Failed  
**After:** ✅ All normalized to lowercase

### Issue 4: Different name conventions
**Before:** ❌ Only accepted one format  
**After:** ✅ Accepts 20+ variations

---

## 🚀 WHAT TO DO NOW

### If Your Import Failed:

1. **Check debug.log** - See what columns were found
2. **Look at error message** - It now shows:
   - What columns are missing
   - What columns were found
   - What variations are supported

3. **Options:**
   - **Option A:** Rename your CSV columns to match supported names
   - **Option B:** Re-import (should work now with flexible mapping)
   - **Option C:** Add your column names to the mapping (contact dev)

### Re-Import Steps:

1. Go to: **Referrals → Settings**
2. Scroll to: **Import Coaches from CSV**
3. Select your CSV file
4. Check "Update existing" if needed
5. Click "Import Coaches"
6. Should work now! ✅

---

## 💡 NEED HELP?

### If Import Still Fails:

**Send debug.log excerpt showing:**
```
CSV Headers found: [your columns]
Normalized headers: [normalized]
Field mapping: [what was mapped]
```

**We can add your column name variations to the mapping!**

---

**Last Updated:** November 4, 2025  
**Status:** Fixed - Flexible column mapping implemented  
**Deploy:** Include in next deployment to dev

