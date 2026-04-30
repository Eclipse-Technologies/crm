# CRM REMEDIATION GUIDE
**Quick Reference for Fixing Critical Issues**

---

## 🚨 CRITICAL FIX #1: XSS Vulnerability in task-list.js

**File:** `task-list.js`  
**Lines:** 12-20  
**Problem:** Direct innerHTML injection of server output  
**Risk:** Session hijacking, credential theft, malware injection  

### Current Code (VULNERABLE):
```javascript
function loadTasks() {
    fetch('get_tasks.php')
        .then(response => response.text())
        .then(html => {
            document.getElementById('task-list').innerHTML = html;  // ❌ XSS RISK
        });
}
```

### Fixed Code (SAFE):
```javascript
function loadTasks() {
    fetch('get_tasks.php')
        .then(response => response.text())
        .then(html => {
            // Option A: If get_tasks.php returns plain text only
            document.getElementById('task-list').textContent = html;  // ✅ SAFE
            
            // Option B: If you need HTML, add DOMPurify library
            // First: <script src="https://cdn.jsdelivr.net/npm/dompurify@latest/dist/purify.min.js"></script>
            // Then: document.getElementById('task-list').innerHTML = DOMPurify.sanitize(html);
        })
        .catch(error => {
            console.error('Error loading tasks:', error);
            document.getElementById('task-list').textContent = 'Error loading tasks';
        });
}
```

### Verification:
After fixing, test that:
1. Tasks still load normally ✓
2. Special characters display correctly ✓
3. No error console messages ✓

**Effort:** 5 minutes | **Priority:** MUST FIX IMMEDIATELY

---

## 🚨 CRITICAL FIX #2: Race Conditions in CSV Operations

**Files:** `csv_handler.php`, `add_contact.php`, `bulk_action.php`  
**Problem:** No file locking during read-modify-write operations  
**Risk:** Data loss, corruption, duplicate records, concurrent update failures  

### Current Code (VULNERABLE):
```php
// ❌ NO FILE LOCKING
$filename = 'contacts.csv';
$data = readCSV($filename, $schema);
$data[] = $newContact;
writeCSV($filename, $data, $schema);  // Another user's changes lost here!
```

### Fixed Code (SAFE):
```php
// ✅ WITH FILE LOCKING
$filename = 'contacts.csv';
$handle = fopen($filename, 'c+b');

if (flock($handle, LOCK_EX)) {
    try {
        // Read with handle
        rewind($handle);
        $data = readCsvFromHandle($handle);
        
        // Modify data
        $data[] = $newContact;
        
        // Write back atomically
        ftruncate($handle, 0);
        rewind($handle);
        writeCsvToHandle($handle, $data);
        
        flock($handle, LOCK_UN);
    } catch (Exception $e) {
        flock($handle, LOCK_UN);
        fclose($handle);
        throw $e;
    }
} else {
    die('Unable to acquire lock on contacts file');
}
fclose($handle);
```

### Required Helper Functions in csv_handler.php:
```php
<?php
// Add these to existing csv_handler.php

/**
 * Read CSV from file handle (for locked operations)
 */
function readCsvFromHandle($handle) {
    $schema = require __DIR__ . '/contact_schema.php';
    $data = [];
    $header = fgetcsv($handle);
    
    if ($header === false) {
        return [];
    }
    
    while (($row = fgetcsv($handle)) !== false) {
        if (count($row) === count($header)) {
            $data[] = array_combine($header, $row);
        }
    }
    
    return $data;
}

/**
 * Write CSV to file handle (for locked operations)
 */
function writeCsvToHandle($handle, $data, $schema = null) {
    if ($schema === null) {
        $schema = require __DIR__ . '/contact_schema.php';
    }
    
    // Write header
    fputcsv($handle, $schema);
    
    // Write data rows with CSV injection prevention
    foreach ($data as $row) {
        $sanitized = [];
        foreach ($row as $value) {
            // Prevent CSV injection
            if (is_string($value) && preg_match('/^[\r\n]*[=+\-@]/', $value)) {
                $value = "'" . $value;
            }
            $sanitized[] = $value;
        }
        fputcsv($handle, $sanitized);
    }
}
?>
```

### Implementation Steps:
1. Update `csv_handler.php` with new functions above
2. Update `add_contact.php` to use file locking
3. Update `delete_contact.php` to use file locking  
4. Update `bulk_action.php` to use file locking
5. Test concurrent operations (2+ browser tabs)

**Effort:** 2-3 hours | **Priority:** MUST FIX BEFORE MULTI-USER

---

## 🟠 HIGH FIX #1: Add CSRF Protection

**Affected Files:** ALL FORMS
- `add_contact.php`
- `delete_contact.php`
- `contact_form.php`
- `import_contacts.php`
- `add_task.php`
- `bulk_action.php`

### Step 1: Create csrf_helper.php
```php
<?php
// File: csrf_helper.php
/**
 * CSRF Protection Helper Functions
 */

session_start();

function initializeCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function getCSRFToken() {
    initializeCSRFToken();
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token ?? '');
}

function renderCSRFInput() {
    echo '<input type="hidden" name="csrf_token" value="' . 
         htmlspecialchars(getCSRFToken(), ENT_QUOTES, 'UTF-8') . '">';
}
?>
```

### Step 2: Add to layout_start.php (after middleware)
```php
<?php
require_once __DIR__ . '/simple_auth/middleware.php';
require_once __DIR__ . '/csrf_helper.php';  // ← ADD THIS LINE
initializeCSRFToken();
?>
```

### Step 3: Update add_contact.php
```php
<?php
require_once __DIR__ . '/layout_start.php';

// ... validation code ...

// VALIDATE CSRF: Add this check before processing POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        die(json_encode(['error' => 'Security validation failed. Please try again.']));
    }
    
    // ... continue with existing code ...
}
?>
```

### Step 4: Update Forms in contact_form.php
**Before (VULNERABLE):**
```html
<form method="POST" action="add_contact.php">
    <input type="text" name="first_name" required>
    <!-- Missing CSRF token! -->
    <button type="submit">Add Contact</button>
</form>
```

**After (SAFE):**
```html
<form method="POST" action="add_contact.php">
    <?php renderCSRFInput(); ?>  <!-- ADD THIS -->
    <input type="text" name="first_name" required>
    <button type="submit">Add Contact</button>
</form>
```

### Step 5: Update All Fetch Requests in JavaScript
**Before (VULNERABLE):**
```javascript
fetch('add_task.php', {
    method: 'POST',
    body: new FormData(form)
    // ❌ No CSRF token
})
```

**After (SAFE):**
```javascript
function attachCSRFTokenToForm(form) {
    const token = document.querySelector('input[name="csrf_token"]');
    if (token) {
        return new FormData(form);  // FormData auto-includes all inputs
    }
    throw new Error('CSRF token not found');
}

function attachCSRFTokenToFetch(options = {}) {
    const body = new FormData();
    for (const [key, value] of Object.entries(options)) {
        body.append(key, value);
    }
    
    // Add CSRF token
    const token = document.querySelector('input[name="csrf_token"]');
    if (token) {
        body.append('csrf_token', token.value);
    }
    
    return body;
}

// Usage in add_task.php fetch:
fetch('add_tasks.php', {
    method: 'POST',
    body: attachCSRFTokenToFetch({
        task_name: taskName,
        task_date: taskDate
    })
})
```

### Checklist - CSRF Protection:
- [ ] csrf_helper.php created
- [ ] Added to layout_start.php
- [ ] All form POST endpoints check csrf token
- [ ] All HTML forms include hidden CSRF input
- [ ] All fetch requests include CSRF token
- [ ] Test that form submission works
- [ ] Test that missing token fails safely

**Effort:** 1.5-2 hours | **Priority:** HIGH

---

## 🟠 HIGH FIX #2: Output Encoding & XSS Prevention

**Problem:** User input displayed without escaping allows XSS  
**Affected Files:** Most PHP files  

### Create sanitize_helper.php
```php
<?php
/**
 * Output Sanitization & Escaping Helpers
 */

/**
 * Safely output HTML (escape special chars)
 */
function escapeHtml($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Alias for brevity
 */
function e($str) {
    return escapeHtml($str);
}

/**
 * Escape attribute values
 */
function escapeAttr($str) {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Escape for JavaScript context
 */
function escapeJs($str) {
    return json_encode($str);  // Safe JSON escaping
}

/**
 * Escape for JSON
 */
function escapeJson($data) {
    return json_encode($data, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG);
}

/**
 * Sanitize input (strip tags)
 */
function sanitizeInput($str) {
    return trim(strip_tags((string)$str));
}

?>
```

### Update layout_start.php
```php
<?php
require_once __DIR__ . '/simple_auth/middleware.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/sanitize_helper.php';  // ← ADD THIS
initializeCSRFToken();
?>
```

### Fix contacts_list.php - Examples
**Before (VULNERABLE):**
```php
echo "<td>$firstName</td>";
echo "<td>$company</td>";
echo "<a href='contact_view.php?id=$id'>View</a>";
```

**After (SAFE):**
```php
echo "<td>" . e($firstName) . "</td>";
echo "<td>" . e($company) . "</td>";
echo "<a href='contact_view.php?id=" . escapeAttr($id) . "'>View</a>";
```

### Fix dashboard.php - Examples
**Before (VULNERABLE):**
```php
echo "<td>$stage</td>";
echo "<td>$forecast</td>";
```

**After (SAFE):**
```php
echo "<td>" . e($stage) . "</td>";
echo "<td>" . e(number_format($forecast, 2)) . "</td>";
```

### Security Headers - Add to layout_start.php
```php
<?php
// Add after middleware but before any output

// Prevent MIME type sniffing
header('X-Content-Type-Options: nosniff');

// Prevent clickjacking
header('X-Frame-Options: DENY');

// Enable XSS protection in older browsers
header('X-XSS-Protection: 1; mode=block');

// Prevent data exposure in referrer
header('Referrer-Policy: strict-origin-when-cross-origin');

// Content Security Policy (adjust as needed)
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");

// Disable caching for sensitive pages
if (strpos($_SERVER['REQUEST_URI'], 'admin') !== false) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}
?>
```

**Effort:** 1.5-2 hours | **Priority:** HIGH

---

## 🟠 HIGH FIX #3: Input Validation Improvements

**File:** `contact_validator.php`  

### Enhanced Validator
```php
<?php
/**
 * Enhanced Contact Validator
 */

require_once __DIR__ . '/contact_schema.php';
require_once __DIR__ . '/sanitize_helper.php';

$FIELD_LIMITS = [
    'first_name' => 50,
    'last_name' => 50,
    'company' => 100,
    'email' => 254,
    'phone' => 20,
    'address' => 100,
    'city' => 50,
    'province' => 50,
    'postal_code' => 10,
    'country' => 50,
    'notes' => 1000,
    'tank_number' => 50,
];

$REQUIRED_FIELDS = ['company', 'first_name', 'last_name'];

function validateContact(array $contact) {
    global $FIELD_LIMITS, $REQUIRED_FIELDS;
    
    $schema = require __DIR__ . '/contact_schema.php';
    $errors = [];

    foreach ($schema as $field) {
        $value = isset($contact[$field]) ? trim($contact[$field]) : '';

        // 1. Required field check
        if (in_array($field, $REQUIRED_FIELDS)) {
            if (empty($value)) {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
                continue;
            }
        }

        // skip empty optional fields
        if (empty($value)) {
            continue;
        }

        // 2. Length validation
        if (isset($FIELD_LIMITS[$field])) {
            if (strlen($value) > $FIELD_LIMITS[$field]) {
                $errors[$field] = "Cannot exceed {$FIELD_LIMITS[$field]} characters (you have " . 
                                 strlen($value) . ").";
            }
        }

        // 3. Format validation
        switch ($field) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = 'Invalid email address format.';
                }
                break;

            case 'phone':
                // Allow formats: +1-555-123-4567, 555.123.4567, 5551234567, etc.
                if (!preg_match('/^[\d\s\-\+\(\)\.]+$/', $value) || strlen($value) < 7) {
                    $errors[$field] = 'Invalid phone number format.';
                }
                break;

            case 'postal_code':
                // Basic validation - allow alphanumeric + space
                if (!preg_match('/^[A-Z0-9\s\-]{3,}$/i', $value)) {
                    $errors[$field] = 'Invalid postal code format.';
                }
                break;

            case 'country':
                // Validate against ISO country list (optional)
                $validCountries = ['Canada', 'United States', 'Mexico', '...'];
                if (!in_array($value, $validCountries)) {
                    // Either be strict or allow anything - choose one
                    // errors[$field] = 'Invalid country.';
                }
                break;
        }
    }

    // 4. Cross-field validation
    if (!empty($contact['email']) && !empty($contact['phone'])) {
        // Could add logic like: duplicate check
    }

    return $errors;
}

/**
 * Sanitize contact for safe storage
 */
function sanitizeContact(array $contact) {
    $schema = require __DIR__ . '/contact_schema.php';
    $sanitized = [];

    foreach ($schema as $field) {
        $value = isset($contact[$field]) ? $contact[$field] : '';
        
        // Trim whitespace
        $value = trim($value);
        
        // Remove null bytes
        $value = str_replace("\0", '', $value);
        
        // For most fields, remove HTML tags (but keep data)
        if ($field !== 'notes') {
            $value = strip_tags($value);
        }
        
        // For notes, allow some HTML but sanitize
        if ($field === 'notes') {
            // Option: Use HTML purifier library or
            // Simple: Allow only line breaks
            $value = str_replace(['<script>', '<iframe>'], '', $value, $count);
        }
        
        $sanitized[$field] = $value;
    }

    return $sanitized;
}

?>
```

### Update add_contact.php to use enhanced validator
```php
<?php
require_once __DIR__ . '/contact_validator.php';

$newContact = [
    'first_name' => $_POST['first_name'] ?? '',
    'last_name' => $_POST['last_name'] ?? '',
    'company' => $_POST['company'] ?? '',
    'email' => $_POST['email'] ?? '',
    // ... other fields
];

// Step 1: Sanitize input
$newContact = sanitizeContact($newContact);

// Step 2: Validate
$errors = validateContact($newContact);

if (!empty($errors)) {
    // Return errors to user
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Step 3: Proceed with adding contact
?>
```

**Effort:** 1 hour | **Priority:** HIGH

---

## Implementation Priority Matrix

```
CRITICAL FIXES (FIX TODAY):
├─ Fix 1: XSS in task-list.js          [5 min]
├─ Fix 2: Race conditions (file lock)   [2 hrs]
└─ Fix 3: Add CSRF protection          [1.5 hrs]

HIGH PRIORITY (WEEK 1):
├─ Output encoding (sanitize_helper)   [1 hr]
├─ Enhanced input validation           [1 hr]
├─ Error handling improvements         [1 hr]
└─ Security headers                    [0.5 hrs]

MEDIUM PRIORITY (WEEK 2):
├─ Backup mechanism                    [2 hrs]
├─ Import validation                   [1 hr]
├─ Audit logging                       [2 hrs]
└─ Data pagination                     [2 hrs]

OPTIONAL (MONTH 2):
├─ Unit tests                          [6 hrs]
├─ Code refactoring                    [8 hrs]
├─ Database migration                  [40 hrs]
└─ Feature enhancements                [TBD hrs]
```

---

## Testing After Fixes

### Security Testing
```bash
# 1. XSS Testing - Try entering in a text field:
<img src=x onerror="alert('XSS')">
# Should display as text, not execute

# 2. CSRF Testing - Form should work but:
# Copy form HTML from one tab to another, submit
# Should fail with security error

# 3. Input Length Testing - Enter very long text
# Should be truncated or rejected with error message

# 4. Concurrent Operations - Open 2 browser windows
# Add contact in window 1, add contact in window 2 simultaneously
# Both should succeed, no data loss
```

### Functional Testing Post-Fixes
- [ ] Add contact - works with validation
- [ ] Edit contact - saves correctly
- [ ] Delete contact - requires confirmation
- [ ] Bulk delete - protected with CSRF
- [ ] Import CSV - validates before preview
- [ ] Export - filename is safe
- [ ] Tasks - load without XSS errors
- [ ] Forms - all display CSRF token

---

## Quick Status Checklist

```
CRITICAL ISSUES:
[ ] Phase 1a: Fix XSS in task-list.js
[ ] Phase 1b: Add file locking to csv_handler.php
[ ] Phase 1c: Add CSRF protection (csrf_helper.php)

HIGH PRIORITY:  
[ ] Phase 2a: Add sanitize_helper.php
[ ] Phase 2b: Update all output with e() escaping
[ ] Phase 2c: Enhanced input validation
[ ] Phase 2d: Security headers in layout_start.php

MEDIUM PRIORITY:
[ ] Phase 3a: Implement backup mechanism
[ ] Phase 3b: Add audit logging
[ ] Phase 3c: Import validation
[ ] Phase 3d: Pagination for large datasets

DEPLOYMENT GATES:
✅ All CRITICAL fixes applied & tested
✅ All HIGH fixes applied & tested  
✅ Security audit passed
✅ No known active vulnerabilities
✅ Data backed up before going live
```

---

**Last Updated:** February 13, 2026  
**Status:** Ready for implementation  
**Estimated Total Time:** Phase 1-2: 15 hours | Phase 1-3: 22 hours
