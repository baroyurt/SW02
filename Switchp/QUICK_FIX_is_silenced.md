# Quick Fix Guide - is_silenced Column Error

## Problem
```
Hata: Unknown column 'is_silenced' in 'field list'
```

## Solution
Run the database migration to add the missing column.

## Steps to Fix

### Option 1: Command Line (Recommended)
```bash
cd Switchp
mysql -u username -p switchdb < database_migrations/001_add_is_silenced_column.sql
```
Replace `username` with your MySQL username. You'll be prompted for password.

### Option 2: phpMyAdmin
1. Log in to phpMyAdmin
2. Select `switchdb` database
3. Click "SQL" tab
4. Copy and paste this:
```sql
ALTER TABLE alarms 
ADD COLUMN is_silenced TINYINT(1) NOT NULL DEFAULT 0 
COMMENT 'Whether alarm is currently silenced (0=active, 1=silenced)'
AFTER silence_until;

CREATE INDEX idx_is_silenced ON alarms(is_silenced);

UPDATE alarms 
SET is_silenced = 1 
WHERE silence_until IS NOT NULL 
AND silence_until > NOW();
```
5. Click "Go"

### Option 3: MySQL Command Line
```bash
mysql -u username -p
```
Then:
```sql
USE switchdb;

ALTER TABLE alarms 
ADD COLUMN is_silenced TINYINT(1) NOT NULL DEFAULT 0 
COMMENT 'Whether alarm is currently silenced (0=active, 1=silenced)'
AFTER silence_until;

CREATE INDEX idx_is_silenced ON alarms(is_silenced);

UPDATE alarms 
SET is_silenced = 1 
WHERE silence_until IS NOT NULL 
AND silence_until > NOW();
```

## Verify It Worked
```sql
DESCRIBE alarms;
```
Should show `is_silenced` column.

## After Migration
1. Refresh port alarms page
2. Error should be gone
3. Silence/unsilence buttons will work

## Need Help?
- See `DATABASE_SCHEMA.md` for complete documentation
- See `database_migrations/001_add_is_silenced_column.sql` for full migration script
