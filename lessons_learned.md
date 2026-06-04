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
