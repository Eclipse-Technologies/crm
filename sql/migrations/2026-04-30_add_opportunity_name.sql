-- Add 'name' field to opportunities table for opportunity title/description
ALTER TABLE opportunities ADD COLUMN name VARCHAR(255) AFTER contact_id;