# Database Schema Documentation

## Alarms Table

The `alarms` table stores port change alarm records from SNMP monitoring.

### Table Structure

```sql
CREATE TABLE alarms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    switch_name VARCHAR(255),
    port_number INT,
    device_ip VARCHAR(45),
    alarm_type VARCHAR(50),
    severity VARCHAR(20),
    message TEXT,
    details TEXT,
    first_seen DATETIME,
    last_seen DATETIME,
    occurrence_count INT DEFAULT 1,
    status VARCHAR(20) DEFAULT 'ACTIVE',
    acknowledged_at DATETIME NULL,
    acknowledged_by VARCHAR(100) NULL,
    ack_type VARCHAR(50) NULL,
    ack_note TEXT NULL,
    silence_until DATETIME NULL,
    is_silenced TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_switch_port (switch_name, port_number),
    INDEX idx_severity (severity),
    INDEX idx_status (status),
    INDEX idx_silence_until (silence_until),
    INDEX idx_is_silenced (is_silenced)
);
```

### Column Descriptions

#### Identity Columns
- **id**: Primary key, auto-incrementing unique identifier
- **switch_name**: Name of the switch where the alarm occurred
- **port_number**: Port number on the switch
- **device_ip**: IP address of the device/switch

#### Alarm Details
- **alarm_type**: Type of alarm (e.g., 'mac_moved', 'description_changed', 'mac_added', 'vlan_changed', 'port_up', 'port_down')
- **severity**: Alarm severity level (CRITICAL, HIGH, MEDIUM, LOW)
- **message**: Short alarm message
- **details**: Detailed alarm information

#### Occurrence Tracking
- **first_seen**: Timestamp when alarm first occurred
- **last_seen**: Timestamp when alarm last occurred
- **occurrence_count**: Number of times this alarm has occurred

#### Status Management
- **status**: Current alarm status (ACTIVE, ACKNOWLEDGED, RESOLVED)
- **acknowledged_at**: When the alarm was acknowledged
- **acknowledged_by**: Username who acknowledged the alarm
- **ack_type**: Type of acknowledgment ('known_change', 'resolved', 'false_positive')
- **ack_note**: Note added when acknowledging

#### Silence Functionality
- **silence_until**: Timestamp until which alarm should remain silenced (NULL = not silenced)
- **is_silenced**: Explicit flag indicating if alarm is currently silenced
  - 0 = Active (not silenced)
  - 1 = Silenced
  - Works with `silence_until` to track silence status
  - When `is_silenced = 1` AND `silence_until > NOW()`, alarm is actively silenced
  - When `is_silenced = 0`, alarm is not silenced regardless of `silence_until`

#### Timestamps
- **created_at**: When the alarm record was created
- **updated_at**: When the alarm record was last updated (auto-updated)

### Silence Functionality Explained

The alarm silence system uses two fields working together:

1. **is_silenced (TINYINT)**: Explicit boolean flag
   - Set to 1 when alarm is silenced
   - Set to 0 when alarm is active or unsilenced

2. **silence_until (DATETIME)**: Timestamp for when silence expires
   - Set to future timestamp when silencing (NOW() + duration)
   - Set to NULL when unsilencing

#### Silencing an Alarm
```sql
UPDATE alarms 
SET is_silenced = 1, 
    silence_until = DATE_ADD(NOW(), INTERVAL ? HOUR)
WHERE id = ?;
```

#### Unsilencing an Alarm
```sql
UPDATE alarms 
SET is_silenced = 0, 
    silence_until = NULL
WHERE id = ?;
```

#### Querying Active (Non-Silenced) Alarms
```sql
SELECT * FROM alarms 
WHERE is_silenced = 0 
AND status = 'ACTIVE';
```

#### Querying Silenced Alarms
```sql
SELECT * FROM alarms 
WHERE is_silenced = 1 
AND silence_until > NOW();
```

#### Auto-Expiring Silenced Alarms
A background job should periodically run:
```sql
UPDATE alarms 
SET is_silenced = 0 
WHERE is_silenced = 1 
AND silence_until <= NOW();
```

### Indexes

The following indexes are created for query performance:

- **idx_switch_port**: Composite index on (switch_name, port_number) for filtering by switch/port
- **idx_severity**: Index on severity for filtering by alarm level
- **idx_status**: Index on status for filtering by alarm state
- **idx_silence_until**: Index on silence_until for finding expiring silences
- **idx_is_silenced**: Index on is_silenced for filtering silenced/active alarms

### Common Queries

#### Get All Active Non-Silenced Alarms
```sql
SELECT * FROM alarms 
WHERE status = 'ACTIVE' 
AND is_silenced = 0
ORDER BY severity DESC, last_seen DESC;
```

#### Get All Silenced Alarms
```sql
SELECT * FROM alarms 
WHERE is_silenced = 1 
AND silence_until > NOW()
ORDER BY silence_until ASC;
```

#### Acknowledge an Alarm
```sql
UPDATE alarms 
SET status = 'ACKNOWLEDGED',
    acknowledged_at = NOW(),
    acknowledged_by = ?,
    ack_type = ?,
    ack_note = ?
WHERE id = ?;
```

### Migration Instructions

To add the `is_silenced` column to an existing database:

```bash
mysql -u username -p switchdb < database_migrations/001_add_is_silenced_column.sql
```

Or execute manually:
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

### Related Files

- **port_alarms.php**: Displays alarms with silence status
- **port_change_api.php**: Handles silence/unsilence actions
- **database_migrations/001_add_is_silenced_column.sql**: Migration script for is_silenced column
