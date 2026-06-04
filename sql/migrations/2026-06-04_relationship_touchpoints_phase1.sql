-- Phase 1: Relationship and touchpoint workflow fields
-- Run this once in MySQL before using the new relationship workflow UI.

ALTER TABLE customers
  ADD COLUMN IF NOT EXISTS relationship_tier VARCHAR(8) NULL AFTER address,
  ADD COLUMN IF NOT EXISTS touch_cadence_days INT NULL AFTER relationship_tier,
  ADD COLUMN IF NOT EXISTS preferred_channel VARCHAR(32) NULL AFTER touch_cadence_days,
  ADD COLUMN IF NOT EXISTS relationship_health VARCHAR(16) NULL AFTER preferred_channel,
  ADD COLUMN IF NOT EXISTS last_touch_at DATETIME NULL AFTER relationship_health,
  ADD COLUMN IF NOT EXISTS next_touch_at DATETIME NULL AFTER last_touch_at,
  ADD COLUMN IF NOT EXISTS last_touch_summary VARCHAR(255) NULL AFTER next_touch_at,
  ADD COLUMN IF NOT EXISTS next_touch_goal VARCHAR(255) NULL AFTER last_touch_summary;

ALTER TABLE contacts
  ADD COLUMN IF NOT EXISTS preferred_channel VARCHAR(32) NULL AFTER phone,
  ADD COLUMN IF NOT EXISTS last_touch_at DATETIME NULL AFTER preferred_channel,
  ADD COLUMN IF NOT EXISTS next_touch_at DATETIME NULL AFTER last_touch_at;

CREATE TABLE IF NOT EXISTS customer_touchpoints (
  id BIGINT NOT NULL AUTO_INCREMENT,
  customer_id VARCHAR(32) NOT NULL,
  contact_id VARCHAR(32) NULL,
  touch_type VARCHAR(64) NOT NULL,
  channel VARCHAR(32) NULL,
  summary TEXT NOT NULL,
  value_delivered TEXT NULL,
  next_action TEXT NULL,
  next_touch_at DATETIME NULL,
  created_by VARCHAR(128) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_customer_touchpoints_customer (customer_id),
  KEY idx_customer_touchpoints_contact (contact_id),
  KEY idx_customer_touchpoints_next_touch (next_touch_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
