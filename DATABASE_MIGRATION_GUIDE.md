# Database Migration Guide

## alarm_severity_config Table Migration

### Purpose
The `alarm_severity_config` table is required for the SNMP Admin Panel to configure alarm severity levels and notification routing.

### Migration File
- Location: `snmp_worker/migrations/create_alarm_severity_config.sql`
- Migration script: `apply_alarm_migration.sh`

### To Apply Migration

#### Option 1: Using the script (if MySQL is running)
```bash
cd /home/runner/work/SW02/SW02/Switchp
./apply_alarm_migration.sh
```

#### Option 2: Manual execution
```bash
mysql -u root -p switchdb < snmp_worker/migrations/create_alarm_severity_config.sql
```

#### Option 3: Via PHP (recommended for production)
```bash
cd /home/runner/work/SW02/SW02/Switchp
php apply_migration.php
```

### What the Migration Does
1. Creates the `alarm_severity_config` table
2. Adds default alarm types with severity levels
3. Configures which alarms should trigger Telegram/Email notifications

### Default Alarm Types Configured
- device_unreachable (CRITICAL)
- multiple_ports_down (CRITICAL)  
- mac_moved (HIGH)
- port_down (HIGH)
- vlan_changed (MEDIUM)
- port_up (MEDIUM)
- description_changed (LOW)
- mac_added (MEDIUM)
- snmp_error (HIGH)

### Verification
After running the migration, verify the table exists:
```sql
SHOW TABLES LIKE 'alarm_severity_config';
SELECT * FROM alarm_severity_config;
```

## Troubleshooting

### Error: Table doesn't exist
If you see "Table 'switchdb.alarm_severity_config' doesn't exist" in snmp_admin.php, run the migration using one of the methods above.

### Error: MySQL connection failed
Ensure MySQL service is running and credentials in config.php are correct.
