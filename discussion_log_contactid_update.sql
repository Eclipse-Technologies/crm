-- SQL script to update legacy string contact_id values in discussion_log to integer contact_id values
-- Auto-generated on 2026-05-04

-- Example mapping (partial, for illustration):
-- UPDATE discussion_log SET contact_id = 165 WHERE contact_id = '68db56a0a1886';
-- UPDATE discussion_log SET contact_id = 169 WHERE contact_id = '68db56a0a1892';
-- ...

-- BEGIN AUTO-GENERATED UPDATES
UPDATE discussion_log SET contact_id = 165 WHERE contact_id = '68db56a0a1886';
UPDATE discussion_log SET contact_id = 169 WHERE contact_id = '68db56a0a1892';
UPDATE discussion_log SET contact_id = 174 WHERE contact_id = '68db56a0a18a5';
UPDATE discussion_log SET contact_id = 177 WHERE contact_id = '68db56a0a18b2';
UPDATE discussion_log SET contact_id = 178 WHERE contact_id = '68db56a0a18b5';
-- (Add all other mappings here, following the same pattern)
-- END AUTO-GENERATED UPDATES

-- After running this script, you may want to check for any unmapped legacy IDs and handle them manually.
