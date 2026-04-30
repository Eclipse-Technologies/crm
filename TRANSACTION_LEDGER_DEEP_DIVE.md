# Transaction Ledger Deep Dive

Date: 2026-04-24
Audience: owner/operator, future developer, analytics and AI implementation planning.

## 1) Why this matters

A transaction ledger is not just an audit trail. It becomes the system of record for:

- trust: who changed what, when, and why
- control: reversal vs overwrite discipline
- analytics: root causes behind inventory variance
- forecasting: demand and operations signals
- AI: anomaly detection and recommendation quality

Without a strong ledger, AI output is weak because historical labels and context are incomplete.

## 2) What to collect (deep specification)

Collect data in five categories for every transaction.

### A. Transaction facts (required)

- transaction_id: internal primary key
- transaction_uuid: globally unique ID for cross-system joins
- transaction_type: receive_po, adjustment, sale_fulfillment, transfer_in, transfer_out, undo_action
- entity_type and entity_id: inventory + item identifier
- item_id: SKU-level key
- quantity_before
- quantity_delta
- quantity_after
- occurred_at_utc
- recorded_at_utc

What this means later:

- reconstruct full stock state at any point in time
- prove line-by-line auditability
- compute variance and correction rates accurately

### B. Business context (strongly recommended)

- location_id
- supplier_id
- source_type: ui, import, api, job
- source_ref: PO number, import batch id, workflow id
- reason_code: controlled taxonomy
- reason_text: optional operator details
- unit_cost and currency_code at transaction time

What this means later:

- understand why changes happen, not just that they happened
- run supplier and location quality analysis
- connect operational errors to specific processes

### C. Actor and session context (recommended)

- actor_user_id
- actor_username
- session_id (or hashed token)
- ip_hash (not raw IP)
- user_agent_hash
- endpoint/page name

What this means later:

- identify training opportunities and risky patterns
- detect automation abuse or suspicious behavior
- preserve privacy while maintaining forensic value

### D. Data integrity and replay safety (important)

- idempotency_key
- correlation_id (to chain multi-step workflows)
- parent_transaction_id (for reversals)
- is_reversal flag
- validation_status: accepted, warned, blocked, pending_review
- prev_hash and row_hash for tamper-evidence

What this means later:

- prevent duplicate writes during retries
- support safe distributed workflows
- prove records are not altered after write

### E. AI and quality feedback (future-ready)

- risk_score at write time
- ai_recommendation JSON (reason suggestion, confidence)
- ai_action_taken: ignored, accepted, overridden
- post_review_label: true_positive, false_positive

What this means later:

- continuous model improvement from real operator feedback
- measurable AI value and error rates
- safer automation over time

## 3) How to store it

Use a layered architecture.

### Layer 1: Canonical append-only ledger

Table: inventory_transactions

Principles:

- append-only writes
- no updates to historic facts
- corrections are new reversal transactions
- all timestamps stored in UTC

Storage guidance:

- InnoDB with strict keys and indexes
- monthly partitioning by occurred_at if volume grows
- decimal precision for quantity and cost

### Layer 2: Operational read models

Examples:

- current stock table/materialized view
- fast dashboard aggregates by day/item/location
- anomaly summary tables

Principles:

- derived from canonical ledger
- rebuildable if needed
- optimized for read performance

### Layer 3: Archive and analytics lake

Examples:

- monthly parquet/csv snapshots
- low-cost object storage
- retained hashes and parent links

Principles:

- immutable retention
- cheaper long-term history
- easy BI and model training extraction

## 4) Retention and archive policy

Recommended policy:

- hot: 0-6 months in primary DB, full query speed
- warm: 6-24 months in archive tables in same DB or read replica
- cold: 24+ months in object storage snapshots

Data governance:

- do not hard-delete transaction facts
- apply privacy rules to context fields where required
- keep hash chain and reversal links indefinitely

## 5) Monitoring and alerting

Track three groups of metrics.

### A. Risk and misuse

- large_delta_count by user and item
- after_hours_adjustment_rate
- repeated_same_sku_edits within short windows
- reversal_rate by user and reason_code

### B. Data quality

- missing_reason_rate for manual adjustments
- null_context_rate for location/supplier/source_ref
- idempotency_collision_count
- failed_write_and_retry_count

### C. Business health

- adjustment_to_sales_ratio
- top 20 most corrected SKUs
- variance by supplier/location
- cycle-count drift trend

Alert thresholds example:

- risk_score >= 80 -> require extra confirmation
- reversal_rate > 15% for a user over 7 days -> review
- missing_reason_rate > 5% -> enforce stricter UI

## 6) What this means for the future

### In 3 months

- cleaner and explainable inventory history
- fewer accidental edits due to guardrails
- better operator accountability without micromanagement

### In 6 months

- clear root-cause insights for recurring adjustments
- better reorder thresholds from observed volatility
- reliable historical slices for finance and operations

### In 12 months

- high-quality training data for AI-driven risk scoring
- near real-time anomaly guidance to prevent bad writes
- stronger audit readiness and faster incident investigations

## 7) AI implementation path

### Stage 1: Rules-first (low risk)

- deterministic checks (delta size, off-hours, rapid repeats)
- reason-code suggestions from simple heuristics
- warnings only, no auto-block

### Stage 2: Hybrid scoring

- combine rules with statistical baseline per SKU/user/time
- output risk_score and recommendation text
- require second confirmation above threshold

### Stage 3: Learning loop

- capture operator decisions and override reasons
- retrain model with true/false positive labels
- tune thresholds by cost of false positives vs false negatives

### Stage 4: Assisted automation

- auto-fill reason_code and context fields where confidence is high
- optionally route high-risk transactions to lightweight approval queue

## 8) Reason taxonomy design

Use controlled reason_code values and keep free-text secondary.

Core codes:

- receive_po
- sale_fulfillment
- transfer_in
- transfer_out
- cycle_count_adjustment
- damage_shrink
- return_customer
- return_supplier
- correction_data_entry
- undo_action

Why this matters:

- analytics are only as good as category consistency
- AI can learn patterns reliably from stable labels

## 9) Security and privacy

- hash IP and user-agent before storage when possible
- store least privileged actor data needed for audit
- enforce RBAC for ledger read access if multi-user grows
- sign and verify row hash chain for tamper-evidence
- keep immutable backups of ledger and hash metadata

## 10) Performance and scaling considerations

- composite index: (item_id, occurred_at)
- composite index: (transaction_type, occurred_at)
- composite index: (actor_username, occurred_at)
- separate read models for dashboard-heavy queries
- paginate and filter history endpoints by default
- avoid large OFFSET scans at scale, prefer keyset pagination later

## 11) Migration from current implementation

Current state already has:

- inventory_movements table
- quick update/undo flow
- history and export pages
- reason requirement for large set adjustments

Migration approach:

1. add inventory_transactions table
2. dual-write from quick update endpoint
3. backfill historical movements into new table
4. compare counts and sample records
5. switch history/export reads to canonical table
6. keep old table as compatibility view or archive

## 12) Definition of done for Ledger V2

- 100% of quantity-changing operations write canonical transaction rows
- all reversals link to parent_transaction_id
- idempotency key enforced for API/import writes
- reason_code present for manual adjustments (target >= 95%)
- monitoring dashboard and alert thresholds active
- history and export sourced from canonical ledger

## 13) Practical next implementation step

Start with dual-write only. This gives immediate future compatibility while keeping current UI stable.

- Keep existing inventory_movements behavior unchanged
- Add canonical write side-by-side
- Validate parity for 1-2 weeks
- Then cut read paths over to canonical source
