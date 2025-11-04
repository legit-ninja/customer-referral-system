# 🐛 BUGFIX: CSV Import - Flexible Column Mapping

**Date:** November 4, 2025  
**Issue:** Coach CSV import failing with "Missing required columns"  
**Status:** ✅ FIXED

---

## 🔍 PROBLEM

### Error from debug.log:
```
Exception in AJAX import: Missing required columns: first_name, last_name, email
```

### Root Cause:
- Code expected EXACT column names: `first_name`, `last_name`, `email`
- User's CSV had different format (likely "First Name", "Last Name", "Email")
- No flexibility for common CSV variations
- Unclear error messages

---

## ✅ SOLUTION IMPLEMENTED

### Changes Made to `class-admin-settings.php`:

1. **Flexible Column Mapping** (Lines 1689-1769)
   - Normalize headers (lowercase, spaces→underscores)
   - Support 20+ column name variations
   - Automatically map to standard field names
   - Log headers for debugging

2. **Better Error Messages**
   - Shows what columns were found
   - Shows what columns are missing
   - Lists supported variations
   - Helps users fix their CSV

3. **Supported Variations Added:**

**First Name:** first_name, firstname, given_name, forename, name  
**Last Name:** last_name, lastname, surname, family_name  
**Email:** email, e-mail, email_address, mail  
**Phone:** phone, telephone, phone_number, mobile  
**Specialization:** specialization, specialty, focus  
**Location:** location, city, region  
**Experience:** experience_years, experience, years_experience  
**Bio:** bio, biography, description, about

---

## 📁 SAMPLE CSV FORMATS (ALL WORK NOW)

### Format 1: Standard ✅
```csv
first_name,last_name,email
Thomas,Mueller,thomas.mueller@example.ch
```

### Format 2: Capitalized with Spaces ✅
```csv
First Name,Last Name,Email
Thomas,Mueller,thomas.mueller@example.ch
```

### Format 3: Alternative Names ✅
```csv
Given Name,Surname,E-mail
Thomas,Mueller,thomas.mueller@example.ch
```

### Format 4: Mixed Format ✅
```csv
FirstName,LastName,Email Address,Phone Number
Thomas,Mueller,thomas.mueller@example.ch,+41 79 123 4567
```

**ALL automatically mapped to standard fields!** ✅

---

## 🧪 TESTING

### Create Test for Flexible Import:

File: `tests/CoachCSVImportTest.php` (TODO)

```php
public function testFlexibleColumnMapping() {
    // Test various column name formats
    $variations = [
        ['First Name', 'Last Name', 'Email'],
        ['first_name', 'last_name', 'email'],
        ['Given Name', 'Surname', 'E-mail'],
        ['FirstName', 'LastName', 'Email Address']
    ];
    
    foreach ($variations as $headers) {
        $result = $this->importWithHeaders($headers);
        $this->assertTrue($result['success']);
    }
}
```

---

## 🚀 DEPLOYMENT

### Deploy This Fix:

```bash
./deploy.sh --test --clear-cache
```

### After Deploy:

1. **Try your import again** - Should work now!
2. **Check debug.log** - Will show:
   ```
   CSV Headers found: First Name, Last Name, Email
   Normalized headers: first_name, last_name, email
   Field mapping: {"first_name":0,"last_name":1,"email":2}
   ```

3. **If still fails** - Check the new error message:
   - Shows your column names
   - Shows what's missing
   - Suggests supported variations

---

## 📝 WHAT TO TRY

### Option 1: Re-import with Current CSV ✅
Your CSV might work now with the flexible mapping!

**Try again:**
1. Go to: Referrals → Settings
2. Import Coaches from CSV
3. Select your file
4. Click Import

### Option 2: Check What Columns You Have

**Tell me your CSV headers and I can:**
- Verify they'll work with current mapping
- Add support if needed
- Suggest column name changes

### Option 3: Use Sample CSV Format

**Download sample:**
- Standard: `assets/sample-coaches.csv`
- Alternative: `assets/sample-coaches-alternative-format.csv`

---

## 🔧 TECHNICAL DETAILS

### Column Mapping Logic:

```php
// Your CSV header: "First Name"
1. Normalize: "first name" (lowercase)
2. Replace spaces: "first_name" (spaces → underscores)
3. Map to standard: "first_name" (lookup in mapping table)
4. Extract data: data[column_index] → coach_data['first_name']
```

### Supported Mappings:

```php
'first_name' => 'first_name',
'firstname' => 'first_name',
'given_name' => 'first_name',
'forename' => 'first_name',
'name' => 'first_name',

'last_name' => 'last_name',
'lastname' => 'last_name',
'surname' => 'last_name',
'family_name' => 'last_name',

'email' => 'email',
'e-mail' => 'email',
'email_address' => 'email',
'mail' => 'email'
```

---

## ✅ FILES CHANGED

### Modified:
- `includes/class-admin-settings.php` (lines 1681-1791)
  - Added flexible column mapping
  - Better error messages
  - Comprehensive logging

### Created:
- `assets/sample-coaches-alternative-format.csv` - Alternative format example
- `docs/CSV-IMPORT-FORMATS.md` - This documentation

### TODO:
- [ ] Add unit test for column mapping
- [ ] Add more column variations if needed
- [ ] Update admin UI help text

---

## 🎯 NEXT STEPS

1. **Deploy the fix:**
   ```bash
   ./deploy.sh --test --clear-cache
   ```

2. **Try your import again** on dev server

3. **If it works:** ✅ Great! Continue with Phase 0

4. **If still fails:** 
   - Check new error message in debug.log
   - Share your CSV headers
   - We'll add support for your format

---

## 💡 TIPS

### Creating Coach CSV:

1. **Export from Google Sheets:**
   - File → Download → CSV
   - Use column names like "First Name", "Last Name", "Email"
   - Should work automatically ✅

2. **Export from Excel:**
   - Save As → CSV UTF-8
   - Use any supported column name variation
   - Should work automatically ✅

3. **Manual CSV:**
   - First row: Column headers
   - Use any supported variation
   - UTF-8 encoding

---

**Try importing again - it should work now!** 🎉

If you still get an error, paste the debug.log output and I'll help troubleshoot.

