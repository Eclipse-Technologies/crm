# Worklog: Auth, Supplier Master, Inventory UX and Audit

Date: 2026-04-24
Scope: Session/auth reliability, supplier master data, inventory usability, quick updates, undo, movement auditing, export and sorting.

## Summary

This workstream improved daily inventory operations and data safety while keeping CRM and root file trees in sync.

## Major Outcomes

- Stabilized authentication behavior to reduce unwanted logouts and re-auth friction.
- Consolidated routing behavior to avoid duplicate path issues (including /CRM/CRM style mistakes).
- Established supplier master workflow with unique supplier IDs and cross-page reuse.
- Rebuilt inventory list for readability and speed (table-first, filters, sort, pagination, quick actions).
- Added safer quantity changes: large-jump confirmation, reason requirement for big Set changes, and one-click Undo.
- Added inventory movement audit trail with history page and CSV export.
- Added date-range filtering and all-column sorting to movement history.
- Added broader column sorting support in inventory list, including supplier and operational columns.

## Supplier Master Data

Implemented supplier directory as source of truth with create/update/delete safeguards.

- Supplier IDs are alphanumeric (example: SUP-0002).
- Deletion is blocked when supplier is still referenced by inventory or purchase orders.
- Supplier selector integration completed in inventory add/edit and purchase order add pages.
- Schema safety/migration included where supplier_id was previously numeric.

## Inventory List UX Improvements

Implemented in inventory list pages (CRM and root mirrors):

- Compact table layout with expandable details.
- Search, status filter, remembered filters, page size selector.
- Pagination with numbered links and keyboard shortcuts.
- Column visibility toggles persisted in localStorage.
- Inline quick quantity controls (+ / - / Set).
- CSV export for filtered/sorted inventory view.
- Last-change badge per row (delta, mode, user, timestamp).

## Quantity Safety + Audit

Implemented in quick update endpoint and list UI:

- Large quantity jump confirmation before Set.
- Mandatory reason for large Set changes (frontend and backend enforced).
- Undo button on success notice.
- inventory_movements table auto-created if missing.
- Movement records include item, old/new qty, delta, mode, reason, user, timestamps, undo linkage.

## Movement History + Export

Added history and export endpoints/pages:

- Inventory movement history page with filters.
- CSV export endpoint for movement records.
- Date range filters (from/to) for both history and export.
- Sortable headers on all displayed history columns.
- Export follows current history filters and sort settings.

## Important Fixes Applied During Iteration

- Avoided blank pages by improving redirect/header flow and DB guard checks.
- Normalized empty date/numeric values to NULL where needed.
- Fixed MySQL syntax compatibility and prepare/execute handling.
- Fixed collation mismatch in movement joins by applying explicit shared collation in join conditions.

## Files Touched (Key)

- Auth/session/routing area:
  - simple_auth/middleware.php and related login/session routing behavior.
- Supplier area:
  - supplier_directory.php
  - inventory_add.php
  - inventory_edit.php
  - purchase_order_add.php
- Inventory list and quick updates:
  - inventory_list.php
  - inventory_quick_update.php
  - inventory_export.php
- Movement history and export:
  - inventory_movement_history.php
  - inventory_movement_export.php
- Navigation:
  - navbar-sidebar.php

Note: In this repository, many pages are mirrored in both root and CRM trees, and matching updates were applied to both.

## Validation Checklist Used

- No PHP diagnostics errors on modified files after each patch batch.
- Verified sort behavior and export alignment.
- Verified quick-update notice/undo flow and movement record creation.
- Verified large-jump reason enforcement path and error notice handling.

## Recommended Ongoing Practice

- Keep root and CRM duplicate files synchronized for every functional change.
- Use this document as the starting point for the next iteration and append deltas by date.
