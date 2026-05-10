# CRM Admin Tools Guide

This guide explains how to use each admin tool available in the CRM system. All admin tools require authentication and provide comprehensive management, monitoring, and maintenance capabilities.

## Access the Admin Dashboard

Click the **⚙️ Admin** link in the navigation bar to access the admin dashboard. This is your central hub for all administrative functions.

---

## Admin Dashboard (`admin_dashboard.php`)

The main admin hub provides an overview of your CRM system at a glance.

### Features:
- **Data Integrity Alert** - Shows if your database has any issues that need attention
- **System Overview** - 8 key statistics:
  - Total Contacts - Number of contact records
  - Email Coverage % - Percentage of contacts with email addresses
  - Duplicate Emails - Number of duplicate email addresses detected
  - Unique Companies - Count of unique company entries
  - CSV Database Size - File size of contacts.csv
  - Total Backups - Number of backup copies
  - Audit Log Size - Size of activity tracking log
  - Error Log Size - Size of error log
- **Admin Tools** - Quick links to all 8 specialized admin tools
- **Recent Activity** - Last 10 actions with timestamps and status
- **Active Users** - Users with action counts

**When to use:** Start here to see system health and access other tools.

---

## 1. Backup Manager (`admin_backups.php`)

Manage backup copies of your contact database. Automatic backups are created whenever contacts are modified.

### Features:
- **List Backups** - View all backup copies with:
  - Timestamp (when created)
  - File size
  - Estimated contact count
- **Restore** - Restore a previous backup with one click
- **Download** - Download a backup file to your computer
- **Delete** - Remove old backups to save space
- **Info Section** - Explains backup retention policy (50 backups kept, 30-day retention)

### How to Use:
1. Go to Admin → Manage Backups
2. Review backup list with timestamps
3. To restore a backup:
   - Find the backup you want
   - Click **Restore** button
   - Confirm the operation
   - Current contacts.csv will be replaced
4. To download a backup:
   - Click the download link
   - Backup file will download to your computer

**When to use:** After accidental deletions, before major imports, or to recover old data.

---

## 2. Audit Log Viewer (`admin_audit.php`)

View detailed activity logs of all actions in the CRM system.

### Features:
- **Filter by User** - See actions by specific user
- **Filter by Action** - See specific types of actions (create, update, delete, etc.)
- **Filter by Date** - See activity on a specific date
- **Pagination** - 50 entries per page
- **Results Table** with:
  - Timestamp - When the action occurred
  - User - WHO performed the action
  - IP Address - WHERE the user was from
  - Action - WHAT they did
  - Entity - Contact, Customer, Opportunity, etc.
  - Status - Success ✓ or Failed ✗ (color coded)
  - Summary - Brief description of the change

### How to Use:
1. Go to Admin → View Audit Log
2. Select filters (optional):
   - Choose a user from dropdown
   - Choose an action type
   - Enter a date (YYYY-MM-DD)
3. Click "Apply Filters"
4. Results show with most recent first
5. Use pagination to browse entries

**When to use:** Track who changed what and when, investigate issues, ensure compliance.

---

## 3. Contact Timeline (`admin_timeline.php`)

View the complete modification history of a single contact.

### Features:
- **Search by Contact ID** - Enter a contact ID to view its history
- **Visual Timeline** - Chronological display with:
  - Green dots = successful changes
  - Red dots = failed operations
  - Connected line showing flow
- **Timeline Details**:
  - Action type (CREATE, UPDATE, DELETE, MERGE)
  - Status (Success/Failed)
  - When it happened (timestamp)
  - Who did it (user_id)
  - From where (IP address)
  - What changed (before/after values)
  - Change summary with icons:
    - ✓ Added
    - ✗ Removed
    - ~ Modified

### How to Use:
1. Go to Admin → Contact Timeline
2. Enter the contact ID
3. Click "View Timeline"
4. Review the visual timeline
5. Click on timeline items to see change details

**When to use:** Track changes to a specific contact, verify data modifications, understand contact history.

---

## 4. Deduplication Tool (`admin_deduplicate.php`)

Find and merge duplicate or similar contact records.

### Features:
- **Exact Duplicate Emails** - Contacts with identical email addresses
- **Similar Names** - Contacts with similar names (70%+ match using fuzzy matching)
- **Side-by-Side Comparison** - View duplicate records side by side
- **Merge Function** - Combine records with data preferences

### How to Use:
1. Go to Admin → Deduplicate
2. Two sections appear:
   - **Duplicates by Email** - Groups of contacts with same email
   - **Similar Names** - Possibly duplicate names
3. For each group:
   - Review the contact details side by side
   - Click the **Merge** button
   - Choose which values to keep for each field
   - Confirm the merge
4. The first contact is kept, details merged from others

**When to use:** After bulk imports, when manually adding contacts, to clean up the database.

**Caution:** Merging deletes the second contact. Make sure you want to keep the first contact's ID.

---

## 5. Bulk Operations (`admin_bulk_ops.php`)

Perform operations on multiple contacts at once.

### Features:
- **Bulk Delete**:
  - Select multiple contacts with checkboxes
  - Click "Select All" for convenience
  - Delete all selected at once
  
- **Bulk Update (Tag)**:
  - Select any field to update
  - Enter new value (added to all selected)
  - Select contacts to update
  - Apply to all selected at once

### How to Use - Delete:
1. Go to Admin → Bulk Operations
2. Scroll to "Bulk Delete" section
3. Check boxes next to contacts to delete
4. Use "Select All" to select all on page
5. Click "Delete Selected"
6. Confirm the deletion

### How to Use - Update:
1. Go to Admin → Bulk Operations
2. Scroll to "Bulk Update" section
3. Select the field to update (company, province, etc.)
4. Enter the new value
5. Select contacts to update
6. Click "Update Selected"
7. Field updated for all selected

**Note:** Only first 50 contacts shown. Total count displayed.

**When to use:** Correcting company name across multiple contacts, adding same value to many records, removing unwanted records.

---

## 6. Advanced Search (`admin_search.php`)

Search contacts across all fields with flexible matching options.

### Features:
- **Multi-Field Search** - Search across all contact fields
- **Partial Match** - Find partial text matches (e.g., "Smith" finds "Smith", "Smithson")
- **Exact Match** - Find exact matches only
- **Results Table** - Shows:
  - Name (first + last)
  - Company
  - Email
  - Phone
  - City
  - Province
  - Direct link to view/edit contact

### How to Use:
1. Go to Admin → Advanced Search
2. Choose search mode:
   - **Partial** = contains text (DEFAULT)
   - **Exact** = exact match
3. Fill in search fields (leave empty to skip):
   - First Name
   - Last Name
   - Company
   - Email
   - Phone
   - City
   - Province
4. Click "Search"
5. Results shown in table
6. Click contact name to view/edit full record

**When to use:** Finding contacts by partial information, searching by location, locating companies.

---

## 7. System Maintenance (`admin_maintenance.php`)

Manage system health, cleanup old data, and verify integrity.

### Features:
- **Data Integrity Check**:
  - Verifies all contacts have unique IDs
  - Checks for missing required fields
  - Validates CSV structure
  - Shows any issues found

- **Cleanup Audit Logs**:
  - Keep only most recent N audit entries
  - Default: 1000 entries
  - Range: 100-50,000 entries
  - Frees up disk space

- **Clear Error Log**:
  - Deletes all error log entries
  - Useful after resolving errors
  - Starts fresh log tracking

- **Backup Summary**:
  - Shows backup count and total size
  - Link to Backup Manager

- **System Statistics**:
  - Contact count
  - CSV file size
  - Email coverage %
  - Duplicate count
  - Companies, Provinces
  - Backup info

### How to Use:
1. Go to Admin → Maintenance
2. Run **Data Integrity Check**:
   - Click button
   - Review issues listed
   - Fix if needed
3. **Cleanup Audit Log**:
   - Enter number of entries to keep
   - Click "Cleanup"
   - Old entries deleted
4. **Clear Error Log**:
   - Click "Clear Error Log"
   - Confirm deletion
   - Log reset to clean state

**Caution:** Cleanup operations cannot be undone. Make backups first if unsure!

**When to use:** Weekly maintenance, before and after major imports, if database feels slow, after resolving issues.

---

## 8. Reports & Analytics (`admin_reports.php`)

Generate statistical reports and analyze system usage.

### Report Types:

#### Activity Report
- Date range selector (default: last 30 days)
- **Statistics**:
  - Total actions performed
  - Successful operations
  - Failed operations
  - Success rate %
- **Breakdown**:
  - Actions by type (create, update, delete, etc.)
  - Top users (who's most active)

#### Contacts Report
- **Statistics**:
  - Total contacts
  - Contacts with email
  - Unique companies
  - Duplicate email count
- **Charts**:
  - Top 10 companies
  - Top 10 provinces

#### Users Report
- **Statistics**:
  - Total active users
  - Total actions across all users
- **Activity Chart**:
  - Action count per user
  - Visual bar chart

#### Errors Report
- Error log size
- Recent errors (last 20)
- Full error messages for debugging

### How to Use:
1. Go to Admin → Reports
2. Click report type button (Activity, Contacts, Users, Errors)
3. For date-range reports:
   - Select start date
   - Select end date
   - Click "Update"
4. Review charts and statistics

**When to use:** Weekly management reviews, tracking user activity, analyzing trends, identifying problem areas.

---

## Admin Helper Functions (`admin_helper.php`)

All admin tools use a shared library of helper functions. While you don't need to understand these internally, they provide:

- `getSystemStats()` - Gathers all system statistics
- `findDuplicateEmails()` - Identifies exact email duplicates
- `findSimilarNames()` - Fuzzy matches similar names
- `mergeContacts()` - Merges two contact records
- `checkDataIntegrity()` - Validates database integrity
- `getRecentActivity()` - Retrieves audit log entries
- Plus 8+ more utility functions

---

## Security & Access Control

### Admin Access:
- Any authenticated user can access admin tools
- Future versions will support role-based permissions

### Data Protection:
- All operations logged to audit trail
- CSRF tokens protect against attacks
- File locking prevents concurrent conflicts
- Backups created automatically during changes

### Best Practices:
1. **Regular Backups** - Check admin dashboard weekly
2. **Monitor Activity** - Review audit logs
3. **Clean Up** - Monthly maintenance and log cleanup
4. **Verify Merges** - Double-check before deleting duplicates
5. **Test Changes** - Try bulk operations on small groups first

---

## Common Tasks

### Removing Duplicate Email After Import:
1. Go to **Deduplicate**
2. Find the email duplicates
3. Review side-by-side
4. Click **Merge**, confirm
5. Verify in **Audit Log**

### Finding All Contacts from a Company:
1. Go to **Advanced Search**
2. Leave Match as "Partial"
3. Enter company name
4. Click **Search**
5. All matching contacts shown

### Recovering Accidentally Deleted Contact:
1. Go to **Backup Manager**
2. Find a backup before deletion
3. Click **Restore**
4. Confirm (current contacts replaced!)
5. Check **Contact Timeline** to verify

### Generating Monthly Activity Report:
1. Go to **Reports**
2. Click **Activity**
3. Set date range (e.g., last month)
4. Review charts and stats
5. Share with management

### Fixing Data Quality Issues:
1. Go to **Maintenance**
2. Click "Data Integrity Check"
3. Review issues listed
4. Fix manually or use relevant tool
5. Re-run check to confirm

---

## Troubleshooting

### Issues Loading Admin Tools:
- Ensure you're logged in (admin tools require authentication)
- Check browser console for errors (F12 Developer Tools)
- Clear browser cache and reload

### Import Went Wrong:
1. Go to **Backup Manager**
2. Find backup before import
3. Restore it
4. Review **Audit Log** to see what happened
5. Retry import with corrections

### Need to Find Where Something Changed:
1. Go to **Contact Timeline**
2. Enter contact ID
3. Review complete history
4. See exact before/after values

### System Feels Slow:
1. Go to **Maintenance**
2. Check "Data Integrity" for issues
3. Run "Cleanup Audit Logs" (keep 1000)
4. Check backup count in **Backup Manager**
5. Delete old backups if not needed

---

## Contracts (`contracts_list.php`)

### Features:
- View all service contracts with status, value, renewal dates, and equipment info
- **Add New Contract** — `contract_form.php` — creates contract with auto-generated ID (`CNT-YYYYMMDD-XXXX`), deducts rental tank inventory, calculates end/renewal dates automatically
- **Edit** — `contract_edit.php` — update all contract fields; empty decimal fields (Regen Fee, Tank Sale Price) are saved as NULL rather than causing a database error
- **Delete** — red 🗑️ Delete button on each row — requires confirmation dialog, protected by CSRF token, handled by `delete_contract.php`
- **Success/error banners** shown after save, update, or delete

### Workflow Notes:
- After saving a new contract you will be redirected to `contracts_list.php?success=1`
- If "Not enough rental tanks available" appears, go to Equipment and confirm available rental inventory before retrying
- Blank Regen Fee or Tank Sale Price fields are intentionally allowed — they save as NULL in the database

---

## Contacts List (`contacts_list.php`)

### Customize Visible Columns:
- Click **Customize Visible Columns** to choose which fields appear in the table
- Click **Apply Changes** — preference is saved to both your browser session AND a 1-year cookie, so it persists after logging out, adding contacts, or refreshing
- Column selection survives the add-contact flow (contact_form → add_contact → contact_success → back to contacts_list)

### Column Persistence Troubleshooting:
- If columns reset, clear your browser cookies for `localhost` and re-apply your preferences
- The cookie is named `crm_display_fields`

---

## Security Notes

### CSRF Protection:
- All state-changing operations (insert, update, delete) are protected by CSRF tokens
- This includes: contact updates, task deletes, opportunity deletes, contract creates/edits/deletes
- A CSRF failure redirects to the referring page with `?error=csrf` and shows a banner

### Data Files & Git:
- CSV exports, debug logs, and error logs are excluded from version control via `.gitignore`
- Patterns excluded: `*.csv`, `*.log`, `debug_log.txt`, `error_log.txt`, `combined_php.txt`
- If a data file was accidentally committed, use `git rm --cached <filename>` to untrack it (file stays on disk)

---

## UX Standards

### Sticky Horizontal Scrollbar (All Table Pages)
All pages with wide tables display a scrollbar **fixed to the bottom edge of the browser window** — not the page bottom. This lets you scroll the table horizontally without first scrolling to the bottom of the page.

- Implemented globally in `layout_end.php` — applies automatically to any page using `<div class="table-responsive">`
- Appears only when the table is wider than the viewport and still visible on screen
- Disappears once you scroll past the table
- Stays in sync with the table scroll in both directions
- Uses `requestAnimationFrame` for smooth performance

**For new pages:** wrapping a table in `<div class="table-responsive">` is sufficient — no additional JS needed.



- See [CRM_FIXES_SUMMARY.md](CRM_FIXES_SUMMARY.md) for security improvements
- See [PHASES_STATUS.md](PHASES_STATUS.md) for implementation timeline
- See [DATABASE_UPDATES.md](DATABASE_UPDATES.md) for backup/audit system details

---

## Support

If you encounter issues or have questions:
1. Check this guide first
2. Review the **Audit Log** for what happened
3. Check error log in **Maintenance** → **Errors Report**
4. Restore from backup if needed
5. Contact your administrator

---

**Last Updated:** February 13, 2026
**Admin Tools Version:** 1.0
**CRM Version:** 4.0+ with security hardening
