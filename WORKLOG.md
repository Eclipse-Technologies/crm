# WORKLOG

Purpose: rolling implementation record for this project.
Update method: append newest entry at the top with date, scope, key changes, file touchpoints, and validation notes.

## 2026-04-24 - Ledger Parity Monitoring Page

### Scope (Ledger Parity Monitoring Page)

- Add a parity-check report to compare legacy and canonical ledgers during migration.

### Key Changes (Ledger Parity Monitoring Page)

- Added a dedicated page: `inventory_ledger_parity.php`.
- Added summary counters for legacy/canonical totals and linked canonical source_ref rows.
- Added three parity sections:
  - legacy rows missing canonical records
  - quantity mismatches between legacy and canonical rows
  - canonical orphan rows with `source_ref` not found in legacy table
- Added a `Ledger Parity` navigation button from movement history.

### Important Files (Ledger Parity Monitoring Page)

- CRM/inventory_ledger_parity.php
- inventory_ledger_parity.php
- CRM/inventory_movement_history.php
- inventory_movement_history.php

### Validation (Ledger Parity Monitoring Page)

- Diagnostics check: no errors in CRM and root parity/history/export files.

### Notes (Ledger Parity Monitoring Page)

- This page supports fallback deprecation decisions with objective parity evidence.

## 2026-04-24 - Transaction Ledger Phase 2 Read Cutover (Fallback-Safe)

### Scope (Transaction Ledger Phase 2 Read Cutover)

- Switch movement history and export reads to canonical transactions where available.
- Preserve backward compatibility with legacy movement table during transition.

### Key Changes (Transaction Ledger Phase 2 Read Cutover)

- Added canonical-first read logic in history and export endpoints.
- Added automatic fallback to `inventory_movements` when canonical table is missing or empty.
- Normalized canonical fields to current UI/export schema so pages remain unchanged.
- Preserved sorting and date/item filters across both sources.

### Important Files (Transaction Ledger Phase 2 Read Cutover)

- CRM/inventory_movement_history.php
- CRM/inventory_movement_export.php
- inventory_movement_history.php
- inventory_movement_export.php

### Validation (Transaction Ledger Phase 2 Read Cutover)

- Diagnostics check: no errors in CRM and root movement history/export files.

### Notes (Transaction Ledger Phase 2 Read Cutover)

- This allows incremental migration and parity checks before full legacy deprecation.

## 2026-04-24 - Transaction Ledger Phase 1 Dual-Write

### Scope (Transaction Ledger Phase 1 Dual-Write)

- Implement canonical transaction table and dual-write in quick inventory updates.
- Preserve existing movement logging and UI behavior.

### Key Changes (Transaction Ledger Phase 1 Dual-Write)

- Added `inventory_transactions` table bootstrap in quick update path.
- Added canonical transaction insert on normal adjustments (`inc`, `dec`, `set`).
- Added canonical reversal insert on undo with parent transaction lookup by source reference.
- Captured actor/session/network context hashes for future monitoring and AI scoring.
- Kept existing `inventory_movements` writes and notices unchanged for compatibility.

### Important Files (Transaction Ledger Phase 1 Dual-Write)

- CRM/inventory_quick_update.php
- inventory_quick_update.php

### Validation (Transaction Ledger Phase 1 Dual-Write)

- Diagnostics check: no errors in both CRM and root quick update files.

### Notes (Transaction Ledger Phase 1 Dual-Write)

- This is Phase 1 foundation; read paths still use `inventory_movements` until cutover.

## 2026-04-24 - Transaction Ledger V2 Design Record

### Scope (Transaction Ledger V2 Design Record)

- Define canonical transactional ledger model and long-horizon collection, storage, monitoring, and AI strategy.
- Define a canonical transactional ledger model for inventory adjustments and reversals.
- Document collection, archive, monitoring, learning, and AI enhancement strategy.

### Key Changes (Transaction Ledger V2 Design Record)

- Added deep-dive architecture document covering:
  - what to collect (transaction facts, context, actor/session, integrity, AI feedback)
  - how to store (append-only ledger, read models, archive)
  - what it means over 3/6/12 month horizons
  - AI staged rollout and governance
- Added a dedicated design document with:
  - Proposed inventory_transactions schema.
  - Reason code taxonomy and data capture standards.
  - Archive/retention approach.
  - Monitoring and KPI recommendations.
  - AI-assisted anomaly and recommendation strategy.
  - Phased implementation and starter SQL.

### Important Files (Transaction Ledger V2 Design Record)

- TRANSACTION_LEDGER_DEEP_DIVE.md
- CRM/TRANSACTION_LEDGER_DEEP_DIVE.md
- TRANSACTION_LEDGER_V2_PLAN.md
- CRM/TRANSACTION_LEDGER_V2_PLAN.md

### Validation (Transaction Ledger V2 Design Record)

- Confirmed deep-dive and design documents exist in root and CRM trees.

### Notes (Transaction Ledger V2 Design Record)

-- This entry is architectural guidance and design blueprint; does not alter runtime behavior yet.

## 2026-04-24 - Auth, Supplier Master, Inventory UX, Audit Trail

### Scope (Auth, Supplier Master, Inventory UX, Audit Trail)

- Authentication/session reliability improvements and routing cleanup.
- Supplier master data flow (source-of-truth supplier directory and cross-page integration).
- Inventory list UX modernization and operational safety enhancements.
- Quantity movement auditing, undo, history, export, filtering, and sorting.

### Key Changes (Auth, Supplier Master, Inventory UX, Audit Trail)

- Improved auth behavior to reduce unexpected sign-outs and reauth friction.
- Fixed path/routing issues in duplicated root/CRM structure.
- Established supplier master workflow with unique alphanumeric supplier IDs.
- Reworked inventory list to compact table UX with search/filter/sort/pagination.
- Added quick quantity actions (+/-/Set) with safety controls:
  - Large-jump confirmation.
  - Required reason for large Set changes.
  - One-click undo after update.
- Added inventory movement logging with metadata (old/new/delta/mode/reason/user/time).
- Added movement history page and CSV export.
- Added movement history date-range filters and all-column sorting.
- Added broader inventory-list sorting including supplier and operational headers.
- Resolved collation mismatch in movement join queries.

### Important Files (Auth, Supplier Master, Inventory UX, Audit Trail)

- Supplier: `supplier_directory.php`, `inventory_add.php`, `inventory_edit.php`, `purchase_order_add.php`
- Inventory list/updates: `inventory_list.php`, `inventory_quick_update.php`, `inventory_export.php`
- Movement history/export: `inventory_movement_history.php`, `inventory_movement_export.php`
- Navigation: `navbar-sidebar.php`
- Auth stack touchpoints: middleware/login/session flow files under `simple_auth/`

### Validation (Auth, Supplier Master, Inventory UX, Audit Trail)

- Repeated diagnostics checks reported no errors after patch batches.
- Sorting/filter/export consistency aligned across list/history/export endpoints.
- Undo + reason-required behavior verified through implemented flow and notices.

### Notes (Auth, Supplier Master, Inventory UX, Audit Trail)

- This repository contains mirrored files in root and `CRM/`; changes should continue to be applied/synced in both trees.

## 2026-04-30 - Opportunity/Contact Communication Log Refactor

### Scope

- Merge duplicate discussion log forms in contact view.
- Ensure opportunity dropdown is correctly populated for the current contact.
- Clean up UI to prevent duplicate or missing elements in communication logging.
- Update lessons_learned.md with new best practices for communication log features.

### Key Changes

- Removed duplicate discussion log form from the Discussions accordion in contact_view.php.
- Defined $contactOpportunities for correct dropdown population.
- Updated lessons_learned.md with guidance on merging forms, dropdown population, and UI validation.

### Important Files

- contact_view.php
- /memories/lessons_learned.md

### Validation

- UI tested: Only one discussion log form present, dropdown lists correct opportunities, no duplicate discussions.
- Lessons learned and documentation updated.

## 2026-04-30 Opportunity Description Field

- Added `description` field to `opportunity_schema.php` and MySQL schema (migration: sql/2026-04-30_add_opportunity_description.sql)
- Updated `opportunity_form.php` to render description as textarea
- Updated `opportunities_list.php` to display description column
- Next: Run migration to update DB

## 2026-05-12 CRM Enhancement Sprint

### Admin & Navigation
- Added Admin section to sidebar (`navbar-sidebar.php`) with 6 links: Dashboard, Advanced Search, Bulk Ops, Reports, Contact Timeline, Deduplicate
- Fixed blank admin pages (root cause 1): `requireAdmin()` was checking `$_SESSION['logged_in']` — changed to `$_SESSION['user_id']` to match `simple_auth/Auth.php`
- Fixed blank admin pages (root cause 2): Removed duplicate `<div class="main-content">` / `<div class="content-container">` wrappers from all 5 admin pages — `navbar-sidebar.php` already provides both
- `admin_timeline.php`: shows search form (name/company/email) when no `?id=` is provided

### Notification Badges
- Added `$_notif_overdue_tasks` (tasks past due_date, not completed/archived) and `$_notif_expiring_contracts` (active contracts ending within 30 days) queries to navbar-sidebar.php
- Red badge on Tasks nav item; amber badge on Contracts nav item

### Forecast Dashboard
- `forecast_calc.php`: added `name`, `expected_close`, `days_to_close` to each result row
- `forecast_dashboard.php`: new "Closing in 30 Days" (amber) and "Overdue Close Date" (red) metric cards; detail table sorted by close date with colour-coded Days Out column

### Duplicate Contact Merge
- Created `admin_deduplicate.php`: groups contacts by duplicate email, radio-select keep/discard, re-points tasks/opportunities/discussion_log/audit_log before deleting discarded contact; CSRF-protected; logs `merge_contact` audit action

### Bulk Operations
- `admin_bulk_ops.php`: added bulk delete and bulk update sections for Opportunities and Tasks (in addition to existing Contacts sections)
- Tasks use `id varchar(64)` — bind type 's' throughout
- Schema vars: `$opp_schema = ['stage','probability']`, `$task_schema = ['status','priority','assigned_to']`

### contracts_list.php Cleanup
- Removed 50-line tank_size retry/opcache workaround (was a transient issue; `SELECT tank_size FROM contracts` confirmed working)
- Removed stale `opcache_reset()` calls

### REST API
- Created `api.php`: read-only, authenticated via `API_KEYS` in `.env` (Bearer token or `?api_key=` param)
- Endpoints: `/contacts`, `/contacts/{id}`, `/opportunities`, `/opportunities/{id}`, `/tasks`, `/tasks/{id}`, `/contracts`, `/contracts/{id}`
- Supports `?q=`, `?stage=`, `?status=`, `?limit=`, `?offset=` query params; returns `{total, limit, offset, data}`
- API key added to `.env`; tested live — 293 contacts returned correctly

### Mass Email Segment Filters
- `mass_email.php`: added province/status/tags filter panel above recipient list; filters apply client-side (JS) for instant preview plus server-side for send

### Mobile Responsive Layout
- `layout_start.php`: added hamburger `<button id="sidebarToggle">` in top bar for screens ≤768px
- `layout_start.php` / `layout_end.php`: JS toggles `.open` on `#sidebar` and `.active` on `#sidebarOverlay`; CSS already defined these classes in `css/modern-sidebar.css`

### Admin Reports Expansion
- `admin_reports.php`: added "Revenue" report (monthly fee by month/status, total ARR) and "Pipeline" report (opportunities by stage with sum and count)
- New report buttons added to selector grid

### Customer Portal
- Created `customer_portal.php`: session-authenticated, shows customer's active contracts, tank sizes, end dates, and delivery history linked by `customer_id`

## 2026-05-12 Security Hardening Follow-up

### API Authentication Hardening
- Updated `api.php` to enforce **header-only** authentication:
  - `X-API-Key: <key>`
  - `Authorization: Bearer <key>`
- Disabled query-string key auth (`?api_key=`) to prevent credential leakage in logs/history.
- Updated API root metadata (`auth` field) to match header-only behavior.
- Validation run:
  - Header key returns `200`
  - Query-string key returns `401`

### Secret Rotation / Scrubbing
- Rotated `API_KEYS` value in `.env`.
- Cleared exposed `.env` secrets:
  - `OPENAI_API_KEY`
  - `SMTP_PASSWORD`
- Confirmed old API key is invalid after rotation.

### CSRF Regression Fix
- Restored CSRF validation in the general contact update POST path in `contact_view.php`.
- All state-changing POST paths on that page now validate `verifyCSRFToken()` before DB writes.

### Notes / Corrections
- Prior worklog line stated API supported query-param auth; final state is header-only.
- Sidebar mobile toggle was already implemented in `js/modern-ui.js`; final change kept CSS visibility fix and removed duplicate toggle script additions.
