## Logging and Auditing
- For all audit and action logging, use SQL tables (not CSV files) for reliability, queryability, and scalability.
- Audit logging (`audit_handler.php`) already uses MySQL (`audit_log` table). Primary contact storage (`contacts_list.php`, customers, contracts, tasks) is fully MySQL.
- **Pending migration:** `admin_search.php`, `admin_bulk_ops.php`, `admin_timeline.php`, `admin_reports.php`, and `admin_dashboard.php` still read from `contacts.csv` — these must be migrated to query MySQL before the CSV file can be retired.
- `admin_backups.php`, `admin_audit.php`, `admin_deduplicate.php`, and `admin_maintenance.php` are documented in ADMIN_GUIDE.md but do not yet exist — implement these as MySQL-based tools.
# Lessons Learned

This document captures key lessons, recurring errors, and communication improvements identified during development. Update this file regularly to improve efficiency and avoid repeating mistakes.

## Communication & Workflow
- Clarify if you want a direct code change, a plan, or a comparison before work begins.
- Specify if you want a feature implemented immediately or just a review/roadmap.
- When asking for a fix, indicate if you want a best-practice solution or a minimal patch.
- If referencing a file or line, provide the filename and context (e.g., button, function, or section).
- Confirm after each major change if you want to proceed, test, or iterate further.

## Technical Lessons & Errors
- Always check for schema alignment between PHP, SQL, and documentation before debugging DB errors.
- If the same entity can be mutated from multiple pages (e.g., delete from list and detail views), keep one shared transactional mutation pattern (including dependent-table cleanup and stock/accounting side-effects) to avoid page-dependent data drift.
- For bulk/pooled assignment workflows, never show unconditional success messages; report partial fulfillment and final counts so users can reconcile requested vs applied state.
- When modules adjust inventory as a side effect (equipment build/delete/update), log those adjustments into the same transaction audit model used by direct inventory tools to preserve traceability.
- MySQL `SHOW COLUMNS ... LIKE` checks should use escaped literal SQL (not prepared placeholders) to avoid runtime syntax errors in schema-compatibility guards.
- In cPanel/MySQL environments, seeing a database in phpMyAdmin does not prove an app user has rights to it; always verify grants for the exact user/database pair.
- Distinguish DB auth failures by stage: `connect` failure means wrong user/password/host; `select_db` failure means missing privileges on that database.
- cPanel database identities may be prefixed or unprefixed depending on host configuration; production connection code should try both forms safely and log each attempt.
- For fallback connection loops, wrap both `new mysqli(...)` and `select_db(...)` in exception handling; otherwise first failure can abort retries and hide the real valid candidate.
- Environment precedence rule: server `.env` must be authoritative in production; git-tracked runtime env files should only be fallback when server `.env` is absent.
- Git deployment with `rsync --delete` can remove server-only secrets; always exclude `.env` from deployment sync and verify this rule after pipeline edits.
- Temporary diagnostics endpoints are useful for live auth triage but must be removed immediately after resolution.
- For phased feature rollouts that add DB columns/tables, guard UI queries and update handlers with runtime column checks (`SHOW COLUMNS ...`) so pages stay usable before and after migration runs.
- Use proper link/button types for UI actions (e.g., mailto: for email, window.location for redirects).
- Validate that all quick-action buttons perform their intended function, not just alerts.
- When updating documentation, fix markdownlint errors and check for duplicate headings and broken links.
- After DB schema changes, verify with both code and database to ensure consistency.
- For new features, review top industry standards before implementation.
- API key security: never accept sensitive API keys in query parameters (`?api_key=`) because URLs are logged by browsers, reverse proxies, and web servers. Enforce header-only auth (`X-API-Key` or `Authorization: Bearer ...`).
- Secret hygiene: if any credential appears in `.env` during development (SMTP, AI, API keys), rotate it immediately and clear committed values. Keep `.env` in `.gitignore` and assume exposed values are compromised.
- POST handler regression pattern: when merging form handlers, do not remove CSRF checks from generic update branches. Every state-changing POST path must validate `verifyCSRFToken()` before touching the database.
- Include-safe helper pattern: files that are both endpoints and reusable helpers must gate endpoint-side POST/redirect logic behind a direct-execution check; otherwise including them from another handler can trigger unintended redirects and short-circuit the caller flow.
- Cross-module route contract rule: when list pages link into edit pages, keep query parameter names (`id` vs `opportunity_id`) consistent with the target endpoint parser, or navigation silently breaks while data remains valid.
- Deletion integrity rule: when deleting parent sales entities (like opportunities), clear or reassign dependent references in related modules (`tasks`, `discussion_log`) inside the same transaction to prevent orphaned links and inconsistent UI histories.
- Admin observability rule: maintain a dedicated integrity report page for cross-module orphan checks (opportunity/contact, task/opportunity, discussion/opportunity, contract/customer) so data-link drift is visible before it causes workflow breakage.
- Collation resilience rule: when joining/comparing identifiers across tables that may use mixed collations, use binary-safe comparison (or explicit normalized collations) for fallback string matches to prevent runtime "Illegal mix of collations" failures.
- Admin repair safety rule: when adding one-click data repair tools, restrict them to idempotent/safe operations (e.g., null orphan foreign references), require CSRF protection + confirmation, and enforce batch size limits to reduce blast radius.
- Admin repair traceability rule: every automated repair action should write a structured `audit_log` row (action, scope/entity, batch size, row counts, status, error) so operators can review who ran a fix and what it changed.
- Sensitive repair workflow rule: for higher-impact relationship fixes (e.g., contract customer link nulling), require a preview step plus explicit typed confirmation before apply, and expire preview authorization after a short window.
- Identifier-normalization rule: when related modules store IDs with different formatting (e.g., `3` vs `00003`), integrity checks and repair logic must compare normalized values (numeric-equivalent fallback) or they will report false orphans.
- Apply normalization consistently across all integrity joins/checks for a given entity relationship (report queries and repair queries together); partial normalization causes conflicting counts and confusing admin outcomes.
- For repeatable SQL predicates (like normalized ID matching), centralize predicate generation in one helper and reuse it in both report and repair paths to prevent future logic drift.
- When multiple admin pages share schema-detection and key-resolution logic (`SHOW COLUMNS`, ID-column selection), move it to a shared helper module and remove per-page duplicates to reduce divergence risk.
- Apply the same shared ID-column helper to non-admin opportunity mutation/read endpoints as well (delete, inline update, pipeline stage updates, edit forms) so all opportunity paths resolve key columns identically.
- After helper extraction/refactors, add a fast CLI smoke test under `tests/` and run it in the same pass; this catches predicate drift and identifier-validation regressions early.
- For repeat-use local validation, wrap smoke tests in a single script command (e.g., PowerShell wrapper that runs lint + test) so checks are consistent and harder to skip.
- For CI portability, smoke tests with optional DB checks should support explicit skip mode via env flag; CI workflows can run logic-only checks while local wrappers keep full DB-backed validation by default.
- Best CI pattern for helper smoke tests: keep both jobs — fast logic-only (skip-DB) and DB-backed with disposable MySQL + minimal schema bootstrap — so syntax/predicate checks and real DB behavior are both covered.
- When refactoring repeated endpoint SQL helpers into a shared module, add a file-level usage smoke test that scans key endpoints for required helper calls and rejects legacy local helper/probe patterns.
- Avoid duplicate sidebar toggle logic: `js/modern-ui.js` already handles `#menuToggle` + `#sidebarOverlay`; adding a second toggle script in layout files can create conflicting behavior.
- Mass email recipient IDs in this CRM are string-based (not integer-only). Never cast `contact_id` with `intval` in selection pipelines, or sends can silently skip all intended recipients.
- Do not nest forms in `mass_email.php` (or any submit workflow page). Nested forms can cause the send button to submit the wrong form or no form in real browsers.
- For SMTP config troubleshooting, validate transport in this order: (1) auth mode (`SMTP_AUTH`), (2) credentials present, (3) host/port reachability from the current environment, (4) encryption/port pairing.
- GoDaddy relay mode (`localhost`/`relay-hosting.secureserver.net` on port 25, no auth) is often unavailable from local development networks; use authenticated SMTP when relay is blocked.
- SMTP2GO standard working baseline for this project: `mail.smtp2go.com`, port `2525`, `SMTP_AUTH=true`, `SMTP_ENCRYPTION=tls`, plus valid SMTP username/password.
- Credential safety: if a password is ever shared in chat or committed in config, rotate it immediately and replace with a new secret.

## Efficiency Improvements
- Use checklists or roadmaps for multi-step features.
- Summarize findings and next steps after each research or fetch operation.
- Store recurring issues and their solutions here for quick reference.
- After major implementation bursts, run a docs consistency pass: align API docs text with actual behavior and correct any stale worklog claims before handoff.

## Revenue Tracking for Contracts
- Separate recurring (monthly) and as-needed (regeneration) revenue in your schema.
- Store contract type, monthly recurring fee, and regeneration fee in the contracts table.
- Log each regeneration event in a separate table (e.g., contract_regenerations) with date and amount.
- For actual revenue, sum all logged regeneration events plus any monthly fees billed.
- For future projections, use active contracts’ monthly_fee and estimate regeneration revenue based on historical frequency.
- This enables accurate reporting, forecasting, and flexible analytics for both contract types.

---
Add new lessons below as they are discovered.
