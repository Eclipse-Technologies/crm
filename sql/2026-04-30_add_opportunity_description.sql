-- 2026-04-30_add_opportunity_description.sql
-- Adds a 'description' field to the opportunities table (CRM)
ALTER TABLE opportunities ADD COLUMN description TEXT NULL AFTER name;