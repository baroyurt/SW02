#!/bin/bash
# Script to apply alarm_severity_config migration

cd /home/runner/work/SW02/SW02/Switchp

echo "Applying alarm_severity_config migration..."

# Check if table exists
mysql -h 127.0.0.1 -u root switchdb -e "SHOW TABLES LIKE 'alarm_severity_config';" 2>/dev/null

if [ $? -eq 0 ]; then
    # Table might exist, try to apply migration
    mysql -h 127.0.0.1 -u root switchdb < snmp_worker/migrations/create_alarm_severity_config.sql
    echo "Migration applied successfully"
else
    echo "MySQL not available or database connection failed"
    echo "Please run this migration manually on production:"
    echo "mysql -u root -p switchdb < snmp_worker/migrations/create_alarm_severity_config.sql"
fi
