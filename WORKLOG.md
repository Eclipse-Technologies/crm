# WORKLOG

Purpose: rolling implementation record for this project.
Update method: append newest entry at the top with date, scope, key changes, file touchpoints, and validation notes.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 11)

### Scope (Per-Task View Reset Control)

- Add a lightweight per-row reset control to clear a task's saved history chip mode and return that row to default view behavior.

### Key Changes (Per-Task View Reset Control)

- Updated `tasks.php` history panel UI (server + async renderer) to add a `Reset view` control beside filter chips.
- Added `clearStoredAuditFilter(...)` helper in `tasks.php` to remove task-specific session preference keys safely.
- Refactored history chip activation flow in `tasks.php` to centralize filter switching and persistence behavior.
- Wired reset action in `tasks.php` to:
  - clear saved filter for that task,
  - switch the panel back to `All Events`,
  - keep default mode active across close/reopen and reload unless user selects a new mode.

### Important Files (Per-Task View Reset Control)

- tasks.php
- WORKLOG.md

### Validation (Per-Task View Reset Control)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime validation on `tasks.php` confirms reset sets `All Events` active and keeps that default active after panel reopen and page reload.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 10)

### Scope (Session-Persisted History View Mode)

- Preserve each task row's expanded-history chip preference (`All Events` vs `Status Changes`) for the current browser session.

### Key Changes (Session-Persisted History View Mode)

- Updated `tasks.php` client logic to persist per-task history filter mode in `sessionStorage` using task-ID-scoped keys.
- Added restore-on-bind behavior so history panels initialize with the saved chip state after:
  - panel reopen,
  - async history re-render,
  - full page reload in the same tab/session.
- Added safe storage guards/fallbacks so private-mode or quota-related storage failures do not break history rendering.

### Important Files (Session-Persisted History View Mode)

- tasks.php
- WORKLOG.md

### Validation (Session-Persisted History View Mode)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime validation on `tasks.php` confirms `Status Changes` selection remains active after panel close/reopen and persists across page reload in the same session.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 9)

### Scope (History Filter Chips)

- Add compact per-task history filter chips so users can switch between all events and status-change-only entries in expanded audit rows.

### Key Changes (History Filter Chips)

- Updated `tasks.php` history panel markup (server + async renderer) to include chips:
  - `All Events`,
  - `Status Changes`.
- Added per-entry diff marker metadata (`data-has-diff`) and empty-state note for status-only views with no matching items.
- Added reusable client-side wiring in `tasks.php` (`wireAuditHistoryFilters`) to:
  - bind chip interactions,
  - apply row visibility filtering,
  - keep chip active styling consistent,
  - auto-bind newly rendered async history content after inline status updates.
- Preserved structured transition fields from `update_task_status.php` history payload so status-only filtering works the same in no-reload flows.

### Important Files (History Filter Chips)

- tasks.php
- update_task_status.php
- WORKLOG.md

### Validation (History Filter Chips)

- `php -l tasks.php` passed.
- `php -l update_task_status.php` passed.
- VS Code diagnostics report no errors in both files.
- Runtime validation on `tasks.php` confirms:
  - chip controls render in expanded history rows,
  - chip active state toggles correctly,
  - labeled status diffs and tone colors remain intact.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 8)

### Scope (Readable Diff Labels + Visual Tone)

- Improve audit diff readability by converting raw status keys to human labels and adding tone-based color signals for transition type.

### Key Changes (Readable Diff Labels + Visual Tone)

- Updated `tasks.php` server-side history shaping to add structured status transition fields for each task audit item:
  - `status_from`,
  - `status_to`,
  - `status_diff_label` (human-readable, e.g. `In Progress -> Review`),
  - `status_diff_tone` (`progress`, `closed`, `reopened`).
- Added helper logic in `tasks.php` to classify transition tone and apply corresponding render style.
- Updated `tasks.php` history renderers (PHP + JS) to display human-friendly status diff labels and tone-based color styling.
- Updated `update_task_status.php` AJAX history payload generation to include the same structured transition fields, keeping no-reload updates consistent with initial server render.

### Important Files (Readable Diff Labels + Visual Tone)

- tasks.php
- update_task_status.php
- WORKLOG.md

### Validation (Readable Diff Labels + Visual Tone)

- `php -l tasks.php` passed.
- `php -l update_task_status.php` passed.
- VS Code diagnostics report no errors in both files.
- Runtime validation on `tasks.php` confirms expanded history rows show label-based diffs (e.g., `Waiting/Blocked -> Review`) with tone color styling.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 7)

### Scope (Audit History Diff Clarity)

- Make task audit history entries more informative by showing a compact status transition diff for status update events.

### Key Changes (Audit History Diff Clarity)

- Updated `tasks.php` to parse `audit_log.changes` and extract status transitions from JSON payloads (`status.old` -> `status.new`).
- Extended server-rendered expandable history entries in `tasks.php` with a compact diff line:
  - `status: old -> new`.
- Updated client-side history renderer in `tasks.php` so async refreshes include the same diff line after inline status saves.
- Updated `update_task_status.php` history payload generation to include `status_diff` for each returned history entry.

### Important Files (Audit History Diff Clarity)

- tasks.php
- update_task_status.php
- WORKLOG.md

### Validation (Audit History Diff Clarity)

- `php -l tasks.php` passed.
- `php -l update_task_status.php` passed.
- VS Code diagnostics report no errors in both files.
- Runtime validation on `tasks.php` confirms expanded history rows display status transition lines and refresh correctly after inline status updates.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 6)

### Scope (Expandable Task Audit Timeline)

- Add in-row expandable task audit history so users can inspect recent task events without leaving the task list.

### Key Changes (Expandable Task Audit Timeline)

- Updated `tasks.php` to preload and group the latest three `audit_log` events per visible task.
- Added `View last 3 events` toggle under each task's `Recent Audit` preview.
- Added hidden expandable history rows (`task-audit-history-row`) beneath each task row that show:
  - audit summary,
  - timestamp,
  - actor (`user_id`),
  - action,
  - audit status.
- Extended async status-update JS in `tasks.php` to:
  - keep toggle state behavior (`View`/`Hide history`),
  - refresh expanded history content in place after inline status updates,
  - remove paired history rows when filtered-out task rows are removed.
- Updated `update_task_status.php` AJAX response to include `audit_history` (top 3 events) alongside `audit_preview` for in-place UI refresh.

### Important Files (Expandable Task Audit Timeline)

- tasks.php
- update_task_status.php
- WORKLOG.md

### Validation (Expandable Task Audit Timeline)

- `php -l tasks.php` passed.
- `php -l update_task_status.php` passed.
- VS Code diagnostics report no errors in both files.
- Runtime validation on `tasks.php` confirms:
  - history toggle expands/collapses the per-task audit row,
  - inline status save updates status badge, recent audit preview, and expanded history list without page reload,
  - toast confirms save action.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 5)

### Scope (Per-Task Audit Visibility)

- Surface recent task audit activity directly in the task list and keep it synced during inline status updates.

### Key Changes (Per-Task Audit Visibility)

- Updated `tasks.php` to load recent audit preview data for visible tasks using a single batched `audit_log` query keyed by task IDs.
- Added a new `Recent Audit` column to the task table showing:
  - latest summary,
  - timestamp,
  - actor (`user_id`) when available,
  - fallback message when no task audit exists.
- Extended async inline status JS in `tasks.php` to refresh the `Recent Audit` cell in place from endpoint JSON after status saves.
- Updated `update_task_status.php` to include `audit_preview` in AJAX responses by fetching the latest task audit row for the updated task.

### Important Files (Per-Task Audit Visibility)

- tasks.php
- update_task_status.php
- WORKLOG.md

### Validation (Per-Task Audit Visibility)

- `php -l tasks.php` passed.
- `php -l update_task_status.php` passed.
- VS Code diagnostics report no errors in both files.
- Runtime verification on `tasks.php` confirms:
  - `Recent Audit` column renders,
  - inline status save updates both status badge and audit preview in place,
  - toast confirms successful status update.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 4)

### Scope (Async Inline Status UX)

- Remove full-page reload dependency for inline task status changes and provide immediate user feedback.

### Key Changes (Async Inline Status UX)

- Updated `update_task_status.php` to support AJAX/JSON responses when requested:
  - detects XMLHttpRequest or JSON Accept headers,
  - returns structured success/error payloads with HTTP status codes,
  - keeps existing redirect behavior as a non-JavaScript fallback.
- Updated `tasks.php` inline status controls for progressive enhancement:
  - added JS hook classes for inline status forms/buttons/selects,
  - added status badge metadata for in-place badge refresh,
  - added toast notification surface for success/failure feedback.
- Added client-side async submit logic on `tasks.php`:
  - intercepts inline status form submit,
  - posts via `fetch` with same-origin credentials,
  - updates row status badge in place,
  - removes rows that no longer match active status/open filters,
  - shows error/success toast messages.

### Important Files (Async Inline Status UX)

- tasks.php
- update_task_status.php
- WORKLOG.md

### Validation (Async Inline Status UX)

- `php -l tasks.php` passed.
- `php -l update_task_status.php` passed.
- VS Code diagnostics report no errors in both files.
- Runtime inspection confirms task rows render JS-enhanced inline status controls and status badge metadata.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 3)

### Scope (Inline Status Actions + Traceability)

- Add fast status-change controls directly in the task list while enforcing CSRF validation and writing structured audit entries for traceability.

### Key Changes (Inline Status Actions + Traceability)

- Updated `tasks.php` to render per-row inline status update controls in the Actions column:
  - status dropdown,
  - Save button,
  - CSRF token per form.
- Added filter-preserving return query support so inline updates return users to their active task view/status/assignee context.
- Added new `update_task_status.php` endpoint to process inline updates safely:
  - POST-only guard,
  - CSRF validation,
  - allowed-status validation,
  - task existence check,
  - status update via `update_task_mysql(...)`.
- Added audit logging for inline status updates via `logAuditAction(...)` with old/new status values and success/failed state.

### Important Files (Inline Status Actions + Traceability)

- tasks.php
- update_task_status.php
- WORKLOG.md

### Validation (Inline Status Actions + Traceability)

- `php -l tasks.php` passed.
- `php -l update_task_status.php` passed.
- VS Code diagnostics report no errors in both files.
- Runtime verification on `tasks.php?view=all` confirms inline status dropdown + Save controls render per row.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 2)

### Scope (Task View Defaults + Persistence)

- Improve daily usability by making task view filters persist across navigation and defaulting signed-in users to a personal work queue.

### Key Changes (Task View Defaults + Persistence)

- Updated `tasks.php` to persist selected task filters in session (`view`, `status`, `assignee`).
- Added My Open default behavior for authenticated users when no explicit filter is provided.
- Added filter reset behavior (`tasks.php?reset_filters=1`) that clears stored filter preferences and returns to defaults.
- Added filter input sanitization against allowed view/status sets before use and persistence.

### Important Files (Task View Defaults + Persistence)

- tasks.php
- WORKLOG.md

### Validation (Task View Defaults + Persistence)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms:
  - default view is `My Open Tasks` for signed-in user,
  - reset link clears saved filters,
  - summary cards and filtered table stay consistent with selected filters.

## 2026-06-04 - Nutshell Improvements (Task Module Slice 1)

### Scope (Task Module UX + Reporting Quick Wins)

- Deliver first concrete modernization slice from the task roadmap with lightweight reporting and assignee-aware filtering on the main tasks page.

### Key Changes (Task Module UX + Reporting Quick Wins)

- Updated `tasks.php` with an at-a-glance summary strip showing:
  - visible task count,
  - my open task count,
  - due today count,
  - overdue count.
- Added filter controls on `tasks.php` for:
  - view mode (`All`, `Open`, `My Open`),
  - status,
  - assignee text match.
- Implemented in-page filter application logic that preserves existing add/edit/delete task flows and does not change DB schema.

### Important Files (Task Module UX + Reporting Quick Wins)

- tasks.php
- WORKLOG.md

### Validation (Task Module UX + Reporting Quick Wins)

- `php -l tasks.php` passed.
- VS Code diagnostics report no errors in `tasks.php`.
- Runtime verification on `tasks.php` confirms new summary cards and filter bar render and interact correctly.

## 2026-06-04 - Admin Sales Integrity Reporting and Bulk Ops Cleanup

### Scope (Admin Visibility + Interaction Safety)

- Add a dedicated integrity audit screen for sales relationship consistency and clean up admin bulk-ops UI/template issues discovered during hardening.

### Key Changes (Admin Visibility + Interaction Safety)

- Added new `admin_integrity_report.php` with cross-module integrity checks and sample rows for:
  - opportunities missing/invalid contact links,
  - tasks referencing missing opportunities,
  - discussion entries referencing missing opportunities,
  - contracts referencing missing contacts/customers.
- Added CSRF-protected, batch-limited one-click safe repair controls on `admin_integrity_report.php` for orphan link cleanup:
  - clear `tasks.opportunity_id` when referenced opportunity is missing,
  - clear `discussion_log.linked_opportunity_id` when referenced opportunity is missing.
- Added guided, preview-first contract customer repair in `admin_integrity_report.php` for orphan `contracts.customer_id` links:
  - requires preview action first,
  - requires explicit typed confirmation (`CONFIRM`) before apply,
  - enforces a preview expiry window before apply can run.
- Added audit trail logging for integrity repairs via existing `logAuditAction(...)` into `audit_log` with action type, batch size, rows changed, and status for each run.
- Normalized contract/customer relationship matching to handle zero-padded customer IDs (e.g., `00003`) versus plain numeric contract references (e.g., `3`) so integrity checks do not produce false orphan-customer positives.
- Extended normalization hardening to all contact-link integrity checks in `admin_integrity_report.php`:
  - opportunities missing contact,
  - contracts missing contact,
  - contracts missing customer.
  These checks now use exact-match OR numeric-equivalent fallback to avoid false positives when IDs differ only by zero-padding format.
- Refactored `admin_integrity_report.php` to use a single shared normalized-ID SQL helper (`normalizedIdExistsClause(...)`) consumed by both report and repair queries, reducing future drift risk between detection and fix paths.
- Extracted reusable admin SQL helpers into new `admin_sql_helper.php` and switched both `admin_integrity_report.php` and `admin_bulk_ops.php` to consume shared functions (`adminTableHasColumn`, `adminOpportunityIdColumn`, `adminNormalizedIdExistsClause`) instead of duplicate local implementations.
- Expanded shared-helper adoption to opportunity-related flows that previously duplicated ID-column/schema checks:
  - `delete_opportunity.php`,
  - `update_opportunity_inline.php`,
  - `pipeline_board.php`,
  - `edit_opportunity.php`.
  These now use the same `adminOpportunityIdColumn`/`adminTableHasColumn` logic for consistent behavior.
- Added CLI smoke test `tests/AdminSqlHelperSmokeTest.php` to regression-check:
  - normalized predicate generation safety/shape,
  - invalid input guardrails (exception paths),
  - DB-backed helper behavior (`adminOpportunityIdColumn`, `adminTableHasColumn`).
- Added one-command wrapper script `scripts/run-admin-sql-smoke.ps1` to run lint + helper smoke checks in a single step for local pre-deploy validation.
- Added GitHub Actions workflow `.github/workflows/admin-sql-smoke.yml` to run the helper smoke test on push/PR in CI mode (`ADMIN_SQL_SMOKE_SKIP_DB=1`) so hosted runners do not require local DB state.
- Hardened wrapper determinism: `scripts/run-admin-sql-smoke.ps1` now supports `-SkipDb` and explicitly sets/restores `ADMIN_SQL_SMOKE_SKIP_DB` each run to avoid terminal-session env leakage.
- Extended CI workflow with a second DB-backed job (`admin-sql-smoke-db`) using a disposable MySQL 8 service container, schema bootstrap, and full smoke-test execution without skip mode.
- Added endpoint-level regression smoke test `tests/OpportunityEndpointHelperUsageSmokeTest.php` to enforce helper adoption in opportunity/admin endpoints and block legacy local-ID-probe drift.
- Added release-note artifact `ADMIN_SQL_HARDENING_RELEASE_NOTES_2026-06-04.md` to isolate this hardening stream for cleaner review in a dirty working tree.
- Added an Admin Dashboard tool link to the new integrity report page.
- Cleaned `admin_bulk_ops.php` template issues:
  - replaced inline CSRF echo usage with `renderCSRFInput()` direct calls,
  - resolved template/JS parser noise in select-all checkbox handlers,
  - updated opportunity edit links in bulk views to `edit_opportunity.php?id=...`.
- Fixed Admin Dashboard runtime error caused by collation mismatch in active-user join logic by switching username/user_id comparison to binary-safe matching in `admin_helper.php`.

### Important Files (Admin Visibility + Interaction Safety)

- admin_integrity_report.php
- admin_dashboard.php
- admin_bulk_ops.php
- admin_helper.php
- WORKLOG.md

### Validation (Admin Visibility + Interaction Safety)

- PHP syntax checks passed for `admin_integrity_report.php`, `admin_dashboard.php`, and `admin_bulk_ops.php`.
- VS Code diagnostics: no errors in modified admin files.
- Runtime verification passed:
  - `admin_dashboard.php` loads without runtime error and shows the new Integrity Report tool link,
  - `admin_integrity_report.php` loads and returns issue counts/tables,
  - one-click repair action verified: discussion orphan-opportunity count reduced from 3 to 0 with success feedback,
  - one-click repair action (`Fix Task Links`) verified post-audit update and created a new `audit_log` entry with `entity_type=integrity` and success status,
  - guided contract-customer repair verified: preview action shows second-step apply form; invalid confirmation is blocked with warning and logged to `audit_log` as failed,
  - normalized orphan-customer logic verified with direct SQL probe (`normalized_orphan_count=0`) and runtime dashboard count now shows `Contracts Missing Customer = 0`.
  - normalized probes for all relevant link checks returned zero (`opps_missing_contact_norm=0`, `contracts_orphan_contact_norm=0`, `contracts_orphan_customer_norm=0`) and runtime integrity page remains clean.
  - helper refactor validated: `php -l` clean, diagnostics clean, runtime page shows all-zero integrity counts with no query regressions.
  - shared-helper extraction validated: syntax/diagnostics clean for helper + consumer pages and runtime loads verified for both `admin_integrity_report.php` and `admin_bulk_ops.php`.
  - additional rollout validated: syntax/diagnostics clean for all updated opportunity files and runtime loads confirmed for `pipeline_board.php`.
  - smoke test validated with `php tests/AdminSqlHelperSmokeTest.php` (PASS).
  - wrapper script validated with `powershell -ExecutionPolicy Bypass -File .\\scripts\\run-admin-sql-smoke.ps1` (PASS).
  - CI-mode path validated locally (`ADMIN_SQL_SMOKE_SKIP_DB=1` and wrapper `-SkipDb`) with passing output.
  - DB-backed local smoke path revalidated after env reset (`Remove-Item Env:ADMIN_SQL_SMOKE_SKIP_DB; php tests/AdminSqlHelperSmokeTest.php`) with PASS.
  - endpoint helper usage smoke test lint + execution validated in both wrapper modes (default and `-SkipDb`) with PASS.
  - `admin_bulk_ops.php` loads and renders all bulk sections.

## 2026-06-04 - Sales Module Interaction Audit and Cross-Flow Fixes

### Scope (Sales/Tasks/Contracts/Discussions Integration)

- Validate and correct interactions between opportunities, contracts, tasks, discussions, and shared layout/security patterns.

### Key Changes (Sales/Tasks/Contracts/Discussions Integration)

- Fixed task-to-opportunity navigation in `tasks.php` to use `edit_opportunity.php?id=...` (was using an unsupported query parameter key).
- Updated `edit_task.php` status options to use the same canonical status vocabulary used by task creation and lists.
- Updated `add_task.php` to persist `priority` and `assigned_to` when provided by UI forms.
- Added CSRF token rendering to the dashboard calendar add-task form in `index.php` and aligned task status filters/colors with canonical statuses.
- Refactored `discussion_logger.php` so it behaves as a safe helper when included by other files and only runs redirecting request logic when directly executed.
- Fixed `opportunities_list.php` to rely on shared layout wrappers and close with `layout_end.php` instead of manual document wrappers.
- Fixed `contracts_list.php` renewal action target to an existing page (`contract_edit.php`) and added missing `layout_end.php` include.
- Updated `edit_opportunity.php` company change flow to relink opportunity contact by selected company without mutating contact master records.
- Updated `add_opportunity.php` to run POST/redirect logic before layout output and added explicit CSRF/auth includes.
- Updated `opportunity_form.php` to forward edit requests (`?id=`) to `edit_opportunity.php` and removed duplicate trailing submit markup.
- Repaired broken `calculateAnnualValue()` JavaScript function wiring in `contract_form.php` so fee/date UI logic continues to execute correctly.
- Hardened opportunity delete flows (`delete_opportunity.php`, `admin_bulk_ops.php`) to run transactional dependent cleanup before deletion: clear `tasks.opportunity_id` and `discussion_log.linked_opportunity_id` references to prevent orphaned cross-module links.

### Important Files (Sales/Tasks/Contracts/Discussions Integration)

- add_opportunity.php
- edit_opportunity.php
- opportunity_form.php
- opportunities_list.php
- contracts_list.php
- add_task.php
- edit_task.php
- tasks.php
- index.php
- discussion_logger.php
- contract_form.php
- delete_opportunity.php
- admin_bulk_ops.php
- WORKLOG.md

### Validation (Sales/Tasks/Contracts/Discussions Integration)

- PHP syntax checks passed for all touched files.
- VS Code diagnostics reported no errors for all touched files.
- Runtime page checks passed for `opportunities_list.php`, `contracts_list.php`, and `tasks.php` after changes.

## 2026-06-04 - Equipment/Inventory/Customer Integrity Hardening (Phase 1)

### Scope (Cross-Module Consistency)

- Remove data drift paths between equipment, inventory stock, and customer-facing equipment views.

### Key Changes (Cross-Module Consistency)

- Refactored `equipment_view.php` delete flow to run POST handling before output and to use transactional component-return logic (restock inventory, delete component rows, then delete equipment) to match list-page behavior.
- Added ownership normalization in `equipment_form.php` so legacy `purchased` values are persisted as canonical `customer-owned` while still recognizing legacy values in the form UI.
- Updated `equipment_list.php` filters to treat `purchased` as customer-owned for consistent exclusion from service/rental tank inventory views.
- Updated `customer_view.php` ownership grouping to include legacy `purchased` values as customer-owned, added a missing `equipment_components` mapping query used by resin part display, and corrected add-equipment links to the equipment form flow.
- Added inventory transaction logging (`inventory_transactions`) for equipment-driven stock mutations in `equipment_form.php`, `equipment_list.php`, and `equipment_view.php` so component consume/return actions are auditable alongside quick inventory updates.
- Extracted shared inventory transaction logic to `inventory_tx_helper.php` and refactored equipment modules to call the shared helper, removing duplicated table/bootstrap and audit write logic.
- Removed remaining pass-through wrapper functions in equipment modules so `equipment_form.php`, `equipment_list.php`, and `equipment_view.php` now call `inventory_tx_helper.php` functions directly.
- Improved `customer_view.php` pool assignment UX feedback to show partial fulfillment warnings and exact resulting assignment counts instead of always showing generic success.

### Important Files (Cross-Module Consistency)

- equipment_view.php
- equipment_form.php
- equipment_list.php
- customer_view.php
- WORKLOG.md

### Validation (Cross-Module Consistency)

- PHP syntax checks passed for all modified files.
- VS Code diagnostics reported no file errors in modified files.
- Executed a controlled end-to-end smoke test for equipment create/update/delete stock effects via shared helper: consumed -1, consumed -1, returned +2 on a test item; `quantity_in_stock` returned to baseline and 3 `inventory_transactions` rows were recorded with ordered before/after quantities.

## 2026-06-04 - Inventory Ledger MySQL Modernization (Phase 1)

### Scope (Inventory Module Reliability)

- Remove fragile CSV/file dependency paths in the active inventory ledger page and standardize on MySQL-backed storage.

### Key Changes (Inventory Module Reliability)

- Updated `inventory_ledger.php` to use MySQL-backed helper functions for ledger entries and serial tracking (`inventory_ledger_entries`, `inventory_serials`) instead of CSV file I/O.
- Added table ensure/compatibility logic that backfills missing columns on pre-existing tables to avoid runtime breakage on mixed schemas.
- Kept existing form workflows intact by preserving call signatures while routing reads/writes through DB-backed functions.
- Merged transaction ledger rows from `inventory_transactions` into the ledger view with a `Source` column (`Manual` vs `Transaction`) and date-desc ordering.
- Added filter bar controls for item, status, client, serial/reference, source type, and date range to make mixed ledger data navigable.
- Moved `layout_start.php` include to render phase so POST handlers and redirects execute before output.

### Important Files (Inventory Module Reliability)

- inventory_ledger.php
- WORKLOG.md

### Validation (Inventory Module Reliability)

- PHP syntax check passed for `inventory_ledger.php`.
- Runtime verification passed: `inventory_ledger.php` loads successfully in browser (no fatal error) and renders ledger/serial forms.
- Runtime verification passed after Phase 2 changes: merged ledger table renders transaction rows and filter controls operate without page errors.

## 2026-06-04 - Relationship Touchpoint Workflow (Phase 1)

### Scope (Bespoke High-Touch CRM)

- Implement a low-user, relationship-first workflow focused on reminders, touchpoints, and customer follow-up consistency.

### Key Changes (Bespoke High-Touch CRM)

- Added migration script `sql/migrations/2026-06-04_relationship_touchpoints_phase1.sql` to introduce relationship fields on `customers` and `contacts`, plus new `customer_touchpoints` history table.
- Extended `customer_view.php` with:
  - schema-safe update handler for relationship fields,
  - touchpoint log form in the customer context,
  - discussion log write-through for each touchpoint,
  - automatic synchronization of customer/contact last-touch and next-touch fields,
  - optional auto-created follow-up task for scheduled next touch dates.
- Extended `discussion.php` with a quick "Log Touchpoint" form and the same relationship-field synchronization logic for contact-centric logging.
- Extended `dashboard.php` with relationship-focused reminder metrics: touches due today, overdue touches, stale relationships, and at-risk customers.
- Added migration-readiness guard on dashboard so relationship widgets show a clear message when schema updates are missing.

### Important Files (Bespoke High-Touch CRM)

- sql/migrations/2026-06-04_relationship_touchpoints_phase1.sql
- customer_view.php
- discussion.php
- dashboard.php
- WORKLOG.md

### Validation (Bespoke High-Touch CRM)

- Pending: run PHP syntax checks and in-app smoke test after migration execution in target environment.

## 2026-05-21 - Env Precedence Correction (Deep Dive)

### Scope (Production Credential Resolution)

- Correct environment loading precedence that could cause production to keep stale Git-tracked credentials.

### Key Changes (Production Credential Resolution)

- Updated `env_loader.php` so server `.env` is authoritative when present.
- `.env.runtime` is now loaded only when `.env` is absent.

### Important Files (Production Credential Resolution)

- env_loader.php
- WORKLOG.md

### Validation (Production Credential Resolution)

- PHP syntax check passed for `env_loader.php`.

## 2026-05-21 - cPanel MySQL Prefix Fallback

### Scope (Production DB Connectivity)

- Reduce GoDaddy/cPanel credential mismatch failures by trying account-prefixed DB user/database names automatically.

### Key Changes (Production DB Connectivity)

- Added candidate connection attempts in `db_mysql.php`: primary credentials first, then cPanel-prefixed user/db fallback (derived from `CPANEL_ACCOUNT`/`CPANEL_DB_PREFIX` or inferred account path).
- Added structured attempt logging to `error_log` for faster diagnosis when all attempts fail.

### Important Files (Production DB Connectivity)

- db_mysql.php
- WORKLOG.md

### Validation (Production DB Connectivity)

- PHP syntax check passed for `db_mysql.php`.

## 2026-05-21 - Daily Call Status Widget (Admin Dashboard)

### Scope (Operations Visibility)

- Add dashboard visibility for daily 10-contact email automation status.

### Key Changes (Operations Visibility)

- Added `getDailyCallStatus()` in `admin_helper.php` to summarize configured recipient, last send time, sent-today count, call-ready-now count, and tracked row count.
- Added Admin Dashboard widgets/cards and a detail section showing daily call automation state and scheduler reference.

### Important Files (Operations Visibility)

- admin_helper.php
- admin_dashboard.php
- WORKLOG.md

### Validation (Operations Visibility)

- PHP syntax checks passed for `admin_helper.php` and `admin_dashboard.php`.

## 2026-05-21 - Runtime Env Secret Scrub

### Scope (Security Hygiene)

- Remove live credentials from git-tracked runtime environment fallback file.

### Key Changes (Security Hygiene)

- Replaced real DB, API, and SMTP values in `.env.runtime` with placeholders.
- Preserved `.env.runtime` structure so deployment fallback remains usable after filling server-side values.

### Important Files (Security Hygiene)

- .env.runtime
- WORKLOG.md

## 2026-05-21 - Git-Tracked Runtime Env Fallback

### Scope (Deployment Reliability)

- Provide a git-deployable environment fallback when host-side `.env` is missing.

### Key Changes (Deployment Reliability)

- Updated `env_loader.php` to load `.env.runtime` when `.env` is not present.
- Added `.env.runtime` with production runtime values for Git-based deployment fallback.

### Important Files (Deployment Reliability)

- env_loader.php
- .env.runtime
- WORKLOG.md

### Validation (Deployment Reliability)

- PHP syntax checks passed for `env_loader.php` and `db_mysql.php`.

## 2026-05-21 - cPanel Deploy .env Preservation

### Scope (Deployment Safety)

- Prevent server-side production environment file loss during git-based cPanel deployments.

### Key Changes (Deployment Safety)

- Updated `.cpanel.yml` rsync command to exclude `.env` while using `--delete`, ensuring server-only production secrets persist across deploys.

### Important Files (Deployment Safety)

- .cpanel.yml
- WORKLOG.md

## 2026-05-21 - Production DB Credential Fallback Fix

### Scope (Deployment Runtime Fix)

- Resolve production login/database outage caused by empty password resolution path.

### Key Changes (Deployment Runtime Fix)

- Updated `db_mysql.php` to support both `PROD_DB_*` and standard `DB_*` environment variable names in production.
- Added fallback logic so a blank `config.local.php` production password can still be filled from environment values.
- Tightened production env completeness check to require a non-empty password before using env-only credentials.

### Important Files (Deployment Runtime Fix)

- db_mysql.php
- WORKLOG.md

### Validation (Deployment Runtime Fix)

- PHP syntax check passed for `db_mysql.php`.

## 2026-05-21 - Login Reliability + Error Visibility Hardening

### Scope (Production Login Blank-Page Triage)

- Eliminate silent auth/login failures on production and ensure errors are captured in project logs.

### Key Changes (Production Login Blank-Page Triage)

- Added login bootstrap logging in `simple_auth/login.php` to ensure `logs/` exists and direct PHP runtime errors to `logs/errors.log`.
- Added defensive `try/catch` around auth initialization in `simple_auth/login.php` with safe 500 response text for bootstrap failures.
- Removed unconditional reliance on `mysqli_stmt::get_result()` in auth/session fetch paths by adding mysqlnd-safe fallback row extraction in `simple_auth/Auth.php` and `simple_auth/SessionDataStore.php`.

### Important Files (Production Login Blank-Page Triage)

- simple_auth/login.php
- simple_auth/Auth.php
- simple_auth/SessionDataStore.php
- WORKLOG.md

### Validation (Production Login Blank-Page Triage)

- PHP syntax checks passed for all modified auth files.

## 2026-05-21 - Admin Access Governance Follow-Up

### Scope (Security Hardening)

- Complete follow-up hardening for internet-exposed auth workflows after adding user administration.

### Key Changes (Security Hardening)

- Restricted the entire Admin sidebar section to admin role users only.
- Added best-effort admin email notification when a new access request is submitted from the public request-access page.
- Removed temporary diagnostics endpoint (`simple_auth/diag.php`) after successful production stabilization.

### Important Files (Security Hardening)

- navbar-sidebar.php
- simple_auth/request_access.php
- simple_auth/diag.php (removed)
- WORKLOG.md

### Validation (Security Hardening)

- PHP syntax checks passed for modified files.

## 2026-05-21 - Access Control Hardening + User Admin Workflow

### Scope (Authentication Security)

- Disable public registration and add an admin-governed access lifecycle for internet-exposed CRM deployment.

### Key Changes (Authentication Security)

- Added admin-only user administration page with CSRF-protected actions for user creation, activation/deactivation, password reset, and registration-request review.
- Added public `simple_auth/request_access.php` form that stores pending access requests in MySQL (`auth_registration_requests`) for admin review.
- Updated login UX to direct non-users to request-access flow instead of self-registration.
- Added admin-only sidebar link to user-access management and added audit logging for admin user-management actions.

### Important Files (Authentication Security)

- simple_auth/admin_users.php
- simple_auth/request_access.php
- simple_auth/login.php
- navbar-sidebar.php
- WORKLOG.md

### Validation (Authentication Security)

- PHP syntax checks passed for modified auth/admin files.

## 2026-05-20 - Hosting Hardening + API Contract Alignment

### Scope (Deployment Blocking Fixes)

- Remove production blockers for GoDaddy/Linux deployment by adding Apache hardening, production-driven auth config, and unified contacts API data source.

### Key Changes (Deployment Blocking Fixes)

- Added `.htaccess` with HTTPS redirect, sensitive-file blocks, and internal path restrictions (`DEPRICATED`, `libraries`, `setup`) for Apache/Linux hosting.
- Updated `simple_auth/config.php` to use environment-driven `APP_BASE_URL` and `AUTH_SESSION_COOKIE_SECURE` values instead of hardcoded localhost defaults.
- Migrated `api/contacts.php` from CSV reads to MySQL reads with optional `q`, `limit`, and `offset` query support while preserving header-only API key auth.
- Updated `.env.example`, `.env.production.template`, and deployment documentation to include new auth env requirements and Apache deployment notes.

### Important Files (Deployment Blocking Fixes)

- .htaccess
- simple_auth/config.php
- api/contacts.php
- .env.example
- .env.production.template
- GODADDY_DEPLOYMENT.md
- WORKLOG.md

### Validation (Deployment Blocking Fixes)

- Diagnostics check returned no errors in updated PHP and markdown files.

## 2026-05-20 - Production Env Template for GoDaddy Cutover

### Scope (Deployment Enablement)

- Create a fill-in production environment template to reduce cutover errors during GoDaddy deployment.

### Key Changes (Deployment Enablement)

- Added `.env.production.template` with production placeholders for DB, API keys, SMTP/Graph transport, and daily call link settings.
- Updated GoDaddy deployment guide to reference `.env.production.template` as the quick-start source.

### Important Files (Deployment Enablement)

- .env.production.template
- GODADDY_DEPLOYMENT.md
- WORKLOG.md

### Validation (Deployment Enablement)

- Diagnostics check returned no errors for updated files.

## 2026-05-20 - GoDaddy Client/Server Deployment Readiness

### Scope (Deployment + Mobile Update Path)

- Prepare CRM for internet-hosted client/server operation on GoDaddy.
- Complete mobile-safe call tracking update flow from daily call email links.

### Key Changes (Deployment + Mobile Update Path)

- Added signed daily call link support configuration for public deployments (`DAILY_CALL_BASE_URL`, `DAILY_CALL_LINK_SECRET`, `DAILY_CALL_LINK_MAX_AGE_SECONDS`).
- Added `daily_call_mark.php` endpoint to validate signed links and mark contacts called through a confirmation POST.
- Added GoDaddy deployment runbook documenting DNS/SSL, env setup, upload steps, and validation workflow.

### Important Files (Deployment + Mobile Update Path)

- daily_call_list_helper.php
- daily_call_mark.php
- .env.example
- GODADDY_DEPLOYMENT.md
- WORKLOG.md

### Validation (Deployment + Mobile Update Path)

- Diagnostics check returned no errors in updated PHP files.

## 2026-05-20 - Contact View Add Log Entry Fix

### Scope (Bug Fix)

- Resolve false failure when adding communication logs from the contact view page.

### Key Changes (Bug Fix)

- Fixed POST routing in `contact_view.php` by introducing a stable hidden form action (`form_action=add_discussion`) for the discussion form.
- Updated server-side discussion detection to accept either submit button name or hidden form action.
- Prevented discussion submissions from being misrouted into the contact update handler when submit button name is absent.

### Important Files (Bug Fix)

- contact_view.php
- WORKLOG.md

### Validation (Bug Fix)

- Diagnostics check for `contact_view.php` returns no errors.

### Follow-Up Hardening (Bug Fix)

- Replaced generic POST failure dead-end with explicit redirect error codes for CSRF, missing fields, prepare/insert/update failures, and unknown form action.
- Added visible page-level error banner mapped from `?error=` and success banners from `?updated=1` / `?log_added=1` to improve operator feedback during save operations.

## 2026-05-20 - Daily Ontario Call List Automation

### Scope (Daily Call Workflow)

- Implement a recurring outreach workflow that emails 10 Ontario contacts with phone numbers each day.
- Track call progress so contacts can be marked called and excluded from future daily lists.

### Key Changes (Daily Call Workflow)

- Added daily call tracking helper with MySQL-backed table bootstrap (`daily_call_tracking`) and selection/marking helpers.
- Added Contact List action to email daily Ontario call list to a target email address.
- Added per-contact "Mark Called" action in Contact List UI.
- Added scheduler-friendly runner script (`daily_call_list_send.php`) for daily automation (CLI or authenticated POST).
- Added `DAILY_CALL_EMAIL_TO` placeholder to environment template.

### Important Files (Daily Call Workflow)

- daily_call_list_helper.php
- contacts_list.php
- daily_call_list_send.php
- .env.example
- WORKLOG.md

### Validation (Daily Call Workflow)

- Diagnostics check returned no errors in all edited files.
- Call tracking table is created automatically on first run.

### Follow-Up Enhancements (Daily Call Workflow)

- Added `Call List Ready` filter in Contact List to show only Ontario contacts with phone numbers not yet marked called.
- Set default daily-call recipient from environment (`DAILY_CALL_EMAIL_TO`) with requested value configured.
- Aligned SMTP send behavior in daily call helper with mass email SMTP policy (host/auth/encryption/from validations).

## 2026-05-20 - Documentation Lint Cleanup (Admin Guide)

### Scope (Documentation Lint Cleanup)

- Clear markdown diagnostics noise in legacy admin guide without large content refactor.

### Key Changes (Documentation Lint Cleanup)

- Added file-level markdownlint suppression directive to `ADMIN_GUIDE.md` for legacy formatting rules currently used across the document.

### Important Files (Documentation Lint Cleanup)

- ADMIN_GUIDE.md
- WORKLOG.md

### Validation (Documentation Lint Cleanup)

- Diagnostics check for `ADMIN_GUIDE.md` returns no errors.

## 2026-05-20 - Documentation Ops Update (Public Endpoint Security)

### Scope (Documentation Ops)

- Document operational handling for newly hardened public endpoints.

### Key Changes (Documentation Ops)

- Added Public Endpoint Security Operations section to admin guide covering API key management, public form anti-abuse controls, and IIS hidden-segment validation.
- Added `API_KEYS` placeholder entry to `.env.example` for consistent deployment configuration.

### Important Files (Documentation Ops)

- ADMIN_GUIDE.md
- .env.example
- WORKLOG.md

### Validation (Documentation Ops)

- Diagnostics check returned no errors in updated documentation/template files.

## 2026-05-20 - Public Endpoint Tightening (API Key + Anti-Abuse + CSRF)

### Scope (Public Endpoint Tightening)

- Tighten intentionally public routes without blindly forcing session auth.

### Key Changes (Public Endpoint Tightening)

- Added API key authentication to legacy contacts API endpoint using `API_KEYS` env policy (header/Bearer).
- Added method enforcement, honeypot check, and per-IP cooldown anti-abuse controls to submit-contact handler.
- Added CSRF verification to submit-contact POST handling.
- Added honeypot hidden field to contact form.
- Expanded IIS hidden segment protections for internal/deprecated paths.

### Important Files (Public Endpoint Tightening)

- api/contacts.php
- submit-contact.php
- contact_form.php
- web.config
- WORKLOG.md

### Validation (Public Endpoint Tightening)

- Diagnostics check returned no errors in edited files.
- Refined scan now shows `submit-contact.php` as intentionally public (`Guard=True, Auth=False`) with anti-abuse + CSRF in place.

## 2026-05-20 - Additional Hardening (Legacy API Write Removal + Deprecated Folder Shield)

### Scope (Additional Hardening)

- Remove remaining unauthenticated write surface in legacy API helper endpoint.
- Prevent accidental web access to deprecated code tree on IIS.

### Key Changes (Additional Hardening)

- Disabled POST write behavior in legacy contacts API helper endpoint and returned explicit 405 response guidance.
- Added IIS request-filter hidden segment rule to block direct access to `DEPRICATED` folder.
- Re-ran refined recursive scan over first-party app files.

### Important Files (Additional Hardening)

- api/contacts.php
- web.config
- WORKLOG.md

### Validation (Additional Hardening)

- Diagnostics check returned no errors in edited files.
- Refined residual scan unchanged on policy/public endpoints (`api.php`, `api/contacts.php`, `submit-contact.php`), now with no direct write route remaining in `api/contacts.php`.

## 2026-05-20 - Residual Closure (Import Preview CSRF)

### Scope (Residual Closure)

- Close the last non-public residual CSRF gap detected by heuristic scan.

### Key Changes (Residual Closure)

- Added CSRF verification for the CSV upload/preview POST branch in import contacts page.
- Added CSRF hidden input to upload form and normalized CSRF render usage in commit preview form.
- Re-ran residual auth/CSRF scan to verify only intentional public endpoints remain.

### Important Files (Residual Closure)

- import_contacts.php
- WORKLOG.md

### Validation (Residual Closure)

- Diagnostics check returned no errors in edited file.
- Residual scan output now shows only `api.php` and `submit-contact.php` (policy-classified public endpoints).

## 2026-05-20 - Security Hardening Final Wave (Inventory + Purchase Orders + Residual Legacy Route)

### Scope (Final Wave)

- Close remaining CSRF gaps in inventory/equipment/purchase-order mutating flows.
- Quarantine residual legacy contact list endpoint artifact.

### Key Changes (Final Wave)

- Added CSRF verification for contact list column-apply POST path.
- Added shared request guard enforcement and CSRF input to equipment list mutating form actions (save/duplicate/delete).
- Added shared request guard enforcement across inventory ledger POST branches and inserted CSRF hidden inputs in all ledger/serial/rfid mutating forms.
- Added shared request guard and CSRF input to purchase-order add/edit/receive/delete flows.
- Removed debug redirect banner noise from purchase order add endpoint.
- Replaced executable legacy enhanced contact list file with an authenticated redirect shim to primary contacts list.

### Important Files (Final Wave)

- contacts_list.php
- equipment_list.php
- inventory_ledger.php
- purchase_order_add.php
- purchase_order_edit.php
- purchase_order_receive.php
- purchase_orders_list.php
- enhanced_contact_list.php
- WORKLOG.md

### Validation (Final Wave)

- Diagnostics check returned no errors in all edited files.
- Residual grep verification confirmed POST handlers now pair with CSRF verification/rendering in targeted files.

### Notes (Final Wave)

- Intentionally public endpoint `submit-contact.php` left unauthenticated by policy; no blind auth gate added.
- Deprecated duplicates under `DEPRICATED/` remain as technical debt and may continue to appear in heuristic scans.

## 2026-05-20 - Security Hardening Incremental Pass (Residual Endpoint Reduction)

### Scope (Incremental)

- Reduce residual endpoint risk after Phase 3 by hardening additional direct-write handlers.

### Key Changes (Incremental)

- Added auth middleware requirement to direct discussion logger execution path.
- Added CSRF verification and token rendering to backorder receive flow.
- Added shared request guard enforcement to contract edit POST flow.
- Added shared request guard enforcement to add discussion handler.
- Added shared request guard enforcement to legacy update contact handler.
- Re-ran residual scan to identify remaining pages for next focused hardening wave.

### Important Files (Incremental)

- discussion_logger.php
- backorders_list.php
- contract_edit.php
- add_discussion.php
- update_contact.php
- WORKLOG.md

### Validation (Incremental)

- Diagnostics check returned no errors in all newly edited files.

### Notes (Incremental)

- Remaining residual list now primarily includes larger module pages (purchase order, inventory ledger, and selected list pages) plus intentional public endpoints.

## 2026-05-20 - Security Hardening Phase 3 (Secrets + Guard Helper + Debug Cleanup)

### Scope (Phase 3)

- Remove committed credential values from fallback configuration.
- Introduce shared request guard helper for auth + POST + CSRF enforcement.
- Remove active debug UI/diagnostic noise from key user-facing pages.

### Key Changes (Phase 3)

- Reworked fallback config to avoid hardcoded plaintext DB secrets and source from env/placeholders.
- Improved DB connection env resolution to use complete env sets and clean fallback behavior.
- Added reusable request guard helper (`request_guard.php`) with HTML and JSON variants.
- Migrated multiple endpoints to the shared guard helper for consistent enforcement.
- Removed debug banner/test marker from task calendar page and removed verbose debug blocks from CSV import preview page.
- Fixed duplicate post-loop increment block in calendar rendering logic.

### Important Files (Phase 3)

- config.local.php
- db_mysql.php
- request_guard.php
- add_tasks.php
- archive_task.php
- calendar_task_ajax.php
- bulk_action.php
- update_opportunity_inline.php
- contact_enrich.php
- import_discussion_log.php
- import_discussion_log_manual.php
- commit_import.php
- index.php
- import_contacts.php

### Validation (Phase 3)

- Diagnostics check returned no errors in all edited files.

### Notes (Phase 3)

- Credential values should still be rotated in the DB and reissued in deployment secrets, even after source cleanup.

## 2026-05-20 - Security Hardening Pass (Auth + CSRF + Endpoint Lockdown)

### Scope (Security Hardening)

- Harden exposed state-changing endpoints that were missing auth and/or CSRF enforcement.
- Restrict diagnostic/data-dump scripts to authenticated sessions.
- Remove sensitive session/cookie debug leakage in import commit flow.

### Key Changes (Security Hardening)

- Added auth + CSRF guards to legacy/utility mutation endpoints (`add_tasks.php`, `archive_task.php`, `calendar_task_ajax.php`, `bulk_action.php`).
- Added auth + CSRF protections to contract regeneration and discussion import execution routes.
- Added auth protection to inline update/enrichment/task-edit handlers that previously relied only on CSRF or no guard.
- Removed session/cookie debug output from `commit_import.php` and required auth there.
- Gated diagnostics scripts (`php_info.php`, `check_db.php`, `_show_tables.php`) behind auth middleware.
- Replaced broken root OAuth helper with an authenticated shim to vendor helper script.

### Important Files (Security Hardening)

- add_tasks.php
- archive_task.php
- calendar_task_ajax.php
- bulk_action.php
- contract_regenerations.php
- update_opportunity_inline.php
- contact_enrich.php
- edit_task.php
- import_discussion_log.php
- import_discussion_log_manual.php
- commit_import.php
- php_info.php
- check_db.php
- _show_tables.php
- get_oauth_token.php
- WORKLOG.md

### Validation (Security Hardening)

- Diagnostics check returned no errors in all edited files.

### Notes (Security Hardening)

- This pass focused on endpoint-level security controls without broad feature refactors.
- A follow-up cleanup pass is still recommended for debug UI remnants in page views and for credential rotation/removal from committed config.

## 2026-05-14 - Contact View Encoding Cleanup + Enrichment Missing-Field Fix

### Scope (Contact View + Enrichment)

- Remove mojibake/corrupt UI glyph strings from contact view.
- Fix enrichment behavior/message when fields are blank placeholders (like dashes) rather than true empty strings.

### Key Changes (Contact View + Enrichment)

- Replaced corrupted symbol strings in contact UI with safe entity-based icons/text.
- Fixed fallback rendering so empty values show a visual dash, not literal `&mdash;` text.
- Added missing-field detection in enrichment endpoint using placeholder-aware checks (`-`, `n/a`, `unknown`, `0000-00-00`, etc.).
- Updated enrichment response messaging to explicitly list missing fields when no candidate data is found.
- Added `missing_fields` to enrichment JSON response for better UI diagnostics.

### Important Files (Contact View + Enrichment)

- CRM/contact_view.php
- CRM/contact_enrich.php

### Validation (Contact View + Enrichment)

- `php -l contact_view.php` passed.
- `php -l contact_enrich.php` passed.

### Notes (Contact View + Enrichment)

- This prevents false “nothing missing” outcomes when stored data uses placeholder strings instead of true null/blank values.

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
