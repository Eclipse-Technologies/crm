# Admin SQL Hardening Release Notes (2026-06-04)

## Scope

This release-note file captures the opportunity/admin SQL integrity hardening stream completed on 2026-06-04, including shared helper refactors, integrity repair tooling, and smoke-test/CI coverage additions.

## What Was Added

- New shared helper module: `admin_sql_helper.php`
  - `adminTableHasColumn(...)`
  - `adminOpportunityIdColumn(...)`
  - `adminNormalizedIdExistsClause(...)`
- New admin integrity page: `admin_integrity_report.php`
  - orphan detection for opportunities/tasks/discussions/contracts
  - CSRF-protected repair actions with bounded batch updates
  - guided preview/confirm flow for contract customer-link repairs
  - audit-log instrumentation for repair attempts/results
- New smoke tests:
  - `tests/AdminSqlHelperSmokeTest.php`
  - `tests/OpportunityEndpointHelperUsageSmokeTest.php`
- New local wrapper:
  - `scripts/run-admin-sql-smoke.ps1`
  - supports default DB-backed mode and `-SkipDb` CI-like mode
- New CI workflow:
  - `.github/workflows/admin-sql-smoke.yml`
  - lane 1: fast skip-DB smoke checks
  - lane 2: DB-backed smoke checks with disposable MySQL 8 + schema bootstrap

## Refactors and Integrations

The following files now consume shared helper logic for opportunity key resolution and related schema checks:

- `admin_bulk_ops.php`
- `delete_opportunity.php`
- `update_opportunity_inline.php`
- `pipeline_board.php`
- `edit_opportunity.php`

## Validation Performed

Local checks run successfully:

1. `php -l tests/AdminSqlHelperSmokeTest.php`
2. `php -l tests/OpportunityEndpointHelperUsageSmokeTest.php`
3. `php tests/AdminSqlHelperSmokeTest.php`
4. `php tests/OpportunityEndpointHelperUsageSmokeTest.php`
5. `powershell -ExecutionPolicy Bypass -File .\scripts\run-admin-sql-smoke.ps1`
6. `powershell -ExecutionPolicy Bypass -File .\scripts\run-admin-sql-smoke.ps1 -SkipDb`

Observed results:

- PASS in DB-backed mode.
- PASS in skip-DB mode.
- Endpoint usage smoke checks passed.

## Review Guidance

Because the repository currently has additional unrelated edits, reviewers should focus this release stream on:

- shared helper introduction and adoption,
- integrity report/repair workflow,
- smoke-test and CI workflow additions,
- related documentation updates in `WORKLOG.md` and `lessons_learned.md`.
