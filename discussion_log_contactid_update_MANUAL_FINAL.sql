-- FINAL: Update unmapped legacy contact_id values in discussion_log to integer contact_id values
-- Please fill in the correct integer contact_id for each legacy value below, then run this script.

-- Example:
-- UPDATE discussion_log SET contact_id = 3 WHERE contact_id = '68db56a0a1c56';

-- Unmapped legacy contact_id values:
-- 68dab0ad04660
-- 68db56a0a1c56
-- 690507e9c30e8
-- CNT_20251003023317_ecf31f

-- Automated mapping:
-- 690507e9c30e8 → contact_id 3 (Jim Edmonds, Activation Labratories)
UPDATE discussion_log SET contact_id = 3 WHERE contact_id = '690507e9c30e8';

-- Manual review required for the following (no direct match found):
-- 68dab0ad04660 (Robert Lee/Rob/filtration)
-- 68db56a0a1c56 (Ilya/Prism)
-- CNT_20251003023317_ecf31f (Erin/Solar)
-- Please update these with the correct integer contact_id or create new contacts as needed.

-- After running this, all discussion_log entries will use integer contact_id values and all logs will display correctly for each contact.
