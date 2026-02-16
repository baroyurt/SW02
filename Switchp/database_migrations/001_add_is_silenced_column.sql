-- Migration: Add is_silenced column to alarms table
-- Date: 2026-02-16
-- Purpose: Add explicit silence status flag to support silence/unsilence functionality

-- Add is_silenced column to alarms table
-- This column explicitly tracks whether an alarm is currently silenced
-- Works in conjunction with silence_until timestamp
ALTER TABLE alarms 
ADD COLUMN is_silenced TINYINT(1) NOT NULL DEFAULT 0 
COMMENT 'Whether alarm is currently silenced (0=active, 1=silenced)'
AFTER silence_until;

-- Create index for filtering silenced alarms
CREATE INDEX idx_is_silenced ON alarms(is_silenced);

-- Update existing records: mark alarms as silenced if silence_until is in the future
UPDATE alarms 
SET is_silenced = 1 
WHERE silence_until IS NOT NULL 
AND silence_until > NOW();

-- Verify the changes
SELECT 
    COUNT(*) as total_alarms,
    SUM(is_silenced) as silenced_alarms,
    SUM(CASE WHEN silence_until > NOW() THEN 1 ELSE 0 END) as active_silenced
FROM alarms;

-- Expected result: 
-- - total_alarms: Total number of alarms in the table
-- - silenced_alarms: Number of alarms with is_silenced = 1
-- - active_silenced: Number of alarms with future silence_until timestamp
-- silenced_alarms should equal active_silenced after this migration
