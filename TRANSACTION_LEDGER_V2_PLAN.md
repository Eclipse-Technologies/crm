# Transaction Ledger V2 Plan

Date: 2026-04-24
Status: Design draft aligned to current inventory movement implementation.

## Goals

- Convert inventory movements into a true transaction ledger.
- Preserve an immutable audit trail with reversal chains.
- Improve data quality and operational visibility.
- Enable AI-assisted anomaly detection and operator guidance.

## Data Model

Use two layers:

1. Event layer (append-only ledger)
- Every change is a new event record.
- No in-place update for prior records.

2. Context layer (optional denormalized metadata)
- Enriched fields for analytics/search.
- Can be backfilled from related entities.

### Core Tables

#### inventory_transactions (new canonical ledger)

Recommended columns:

- transaction_id BIGINT UNSIGNED PK AUTO_INCREMENT
- transaction_uuid CHAR(36) NOT NULL UNIQUE
- entity_type VARCHAR(40) NOT NULL DEFAULT 'inventory'
- entity_id VARCHAR(120) NOT NULL
- item_id VARCHAR(100) NOT NULL
- location_id VARCHAR(64) NULL
- supplier_id VARCHAR(32) NULL
- source_type VARCHAR(32) NOT NULL
- source_ref VARCHAR(120) NULL
- transaction_type VARCHAR(32) NOT NULL
- reason_code VARCHAR(32) NULL
- reason_text VARCHAR(255) NULL
- quantity_before DECIMAL(18,4) NOT NULL
- quantity_delta DECIMAL(18,4) NOT NULL
- quantity_after DECIMAL(18,4) NOT NULL
- unit_cost DECIMAL(18,4) NULL
- amount_impact DECIMAL(18,4) NULL
- currency_code CHAR(3) NULL
- actor_user_id VARCHAR(64) NULL
- actor_username VARCHAR(120) NULL
- session_id VARCHAR(128) NULL
- ip_hash CHAR(64) NULL
- user_agent_hash CHAR(64) NULL
- idempotency_key VARCHAR(100) NULL
- correlation_id VARCHAR(100) NULL
- parent_transaction_id BIGINT UNSIGNED NULL
- is_reversal TINYINT(1) NOT NULL DEFAULT 0
- risk_score DECIMAL(5,2) NULL
- ai_recommendation JSON NULL
- validation_status VARCHAR(24) NOT NULL DEFAULT 'accepted'
- prev_hash CHAR(64) NULL
- row_hash CHAR(64) NULL
- occurred_at DATETIME NOT NULL
- recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP

Recommended indexes:

- UNIQUE KEY uq_tx_uuid (transaction_uuid)
- UNIQUE KEY uq_idempotency_key (idempotency_key)
- INDEX idx_item_time (item_id, occurred_at)
- INDEX idx_type_time (transaction_type, occurred_at)
- INDEX idx_actor_time (actor_username, occurred_at)
- INDEX idx_parent (parent_transaction_id)
- INDEX idx_validation (validation_status, occurred_at)

Notes:

- Keep existing inventory_movements during transition.
- Reversal should create a new row with is_reversal=1 and parent_transaction_id set.

## Suggested Reason Codes

- receive_po
- sale_fulfillment
- damage_shrink
- cycle_count_adjustment
- transfer_in
- transfer_out
- return_customer
- return_supplier
- correction_data_entry
- undo_action

## What To Collect (Minimum vs Preferred)

Minimum required now:

- transaction_type
- item_id
- quantity_before
- quantity_delta
- quantity_after
- reason_code and reason_text
- actor_username
- occurred_at
- source_type and source_ref

Preferred next:

- idempotency_key
- correlation_id
- location_id
- session hash signals
- risk_score and validation_status

## Archive Strategy

Hot window:

- Keep full-detail rows in primary DB for 6 months.

Warm archive:

- Partition or copy to monthly archive tables after 6 months.
- Keep same schema for easy union queries.

Cold archive:

- Export monthly parquet/csv snapshots to low-cost storage.
- Retain row hash + parent links for forensic integrity.

Retention:

- Never delete ledger records.
- Correct via reversal transactions only.

## Monitoring and Alerts

Operational alerts:

- Large delta over SKU-specific threshold.
- Repeated same-user edits on same SKU within short window.
- Undo/reversal rate spikes.
- Adjustments outside business hours.

Data quality alerts:

- Missing reason_code for manual adjustments.
- Null supplier/location where required.
- Failed writes or retry storms.

KPI dashboards:

- Top adjusted SKUs.
- Manual correction percentage by user.
- Mean time between correction and reversal.
- Adjustment-to-sales ratio by category.

## What You Can Learn

- Which SKUs have unstable process controls.
- Which workflows produce most corrections.
- Which users need UX or SOP support.
- Better reorder and cycle-count frequencies by volatility profile.

## AI Opportunities

Online guardrail model:

- Input: delta, SKU history, actor pattern, time, source_type.
- Output: risk_score and recommendation.
- Action: warn, require second reason, or soft-block pending confirmation.

Reason code assistant:

- Suggest likely reason_code from context and recent patterns.

Anomaly summaries:

- Daily digest of unusual transactions and likely root causes.

Forecast-informed checks:

- Compare proposed change against expected demand/receipts.

## Implementation Phases

Phase 1 (1-2 days)

- Introduce inventory_transactions table.
- Start dual-write from current quick update path.
- Keep current inventory_movements unchanged.

Phase 2 (2-3 days)

- Route history page to read from inventory_transactions.
- Preserve export compatibility.
- Add reason_code dropdown for Set/adjustment actions.

Phase 3 (2-4 days)

- Add monitoring queries and alert thresholds.
- Introduce idempotency key and correlation IDs.
- Add reversal-only correction policy in UI.

Phase 4 (optional AI)

- Add risk scoring service and recommendations.
- Store inference results in ai_recommendation and risk_score.

## Migration SQL Starter (Draft)

```sql
CREATE TABLE IF NOT EXISTS inventory_transactions (
  transaction_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transaction_uuid CHAR(36) NOT NULL UNIQUE,
  entity_type VARCHAR(40) NOT NULL DEFAULT 'inventory',
  entity_id VARCHAR(120) NOT NULL,
  item_id VARCHAR(100) NOT NULL,
  location_id VARCHAR(64) NULL,
  supplier_id VARCHAR(32) NULL,
  source_type VARCHAR(32) NOT NULL,
  source_ref VARCHAR(120) NULL,
  transaction_type VARCHAR(32) NOT NULL,
  reason_code VARCHAR(32) NULL,
  reason_text VARCHAR(255) NULL,
  quantity_before DECIMAL(18,4) NOT NULL,
  quantity_delta DECIMAL(18,4) NOT NULL,
  quantity_after DECIMAL(18,4) NOT NULL,
  unit_cost DECIMAL(18,4) NULL,
  amount_impact DECIMAL(18,4) NULL,
  currency_code CHAR(3) NULL,
  actor_user_id VARCHAR(64) NULL,
  actor_username VARCHAR(120) NULL,
  session_id VARCHAR(128) NULL,
  ip_hash CHAR(64) NULL,
  user_agent_hash CHAR(64) NULL,
  idempotency_key VARCHAR(100) NULL,
  correlation_id VARCHAR(100) NULL,
  parent_transaction_id BIGINT UNSIGNED NULL,
  is_reversal TINYINT(1) NOT NULL DEFAULT 0,
  risk_score DECIMAL(5,2) NULL,
  ai_recommendation JSON NULL,
  validation_status VARCHAR(24) NOT NULL DEFAULT 'accepted',
  prev_hash CHAR(64) NULL,
  row_hash CHAR(64) NULL,
  occurred_at DATETIME NOT NULL,
  recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_idempotency_key (idempotency_key),
  INDEX idx_item_time (item_id, occurred_at),
  INDEX idx_type_time (transaction_type, occurred_at),
  INDEX idx_actor_time (actor_username, occurred_at),
  INDEX idx_parent (parent_transaction_id),
  INDEX idx_validation (validation_status, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Integration Points in Current Code

- Write path: CRM/inventory_quick_update.php
- History page: CRM/inventory_movement_history.php
- Export endpoint: CRM/inventory_movement_export.php

## Definition of Done for V2 Baseline

- All inventory adjustments generate canonical transaction rows.
- Reversals link to parent transactions.
- History and export read from canonical source.
- Reason code coverage above 95 percent for manual adjustments.
- Alerting baseline active for large-delta and high-undo anomalies.
