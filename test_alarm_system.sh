#!/bin/bash
# Quick Test Script for Alarm Management System
# Run this after deploying the changes

echo "======================================"
echo "Alarm Management System - Quick Test"
echo "======================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
DB_USER="root"
DB_PASS="swdb2"
DB_NAME="switchdb"

echo "Step 1: Checking database connection..."
if mysql -u"$DB_USER" -p"$DB_PASS" -e "USE $DB_NAME" 2>/dev/null; then
    echo -e "${GREEN}✓ Database connection successful${NC}"
else
    echo -e "${RED}✗ Database connection failed${NC}"
    echo "Please check database credentials"
    exit 1
fi

echo ""
echo "Step 2: Checking if migration is needed..."
TABLE_EXISTS=$(mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -sse "SHOW TABLES LIKE 'acknowledged_port_mac'" 2>/dev/null)

if [ "$TABLE_EXISTS" = "acknowledged_port_mac" ]; then
    echo -e "${GREEN}✓ acknowledged_port_mac table exists${NC}"
else
    echo -e "${YELLOW}⚠ acknowledged_port_mac table not found${NC}"
    echo "  Running migration..."
    cd Switchp
    php apply_migration.php
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Migration completed${NC}"
    else
        echo -e "${RED}✗ Migration failed${NC}"
        exit 1
    fi
    cd ..
fi

echo ""
echo "Step 3: Checking alarm table columns..."
COLUMNS=$(mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -sse "SHOW COLUMNS FROM alarms WHERE Field IN ('from_port', 'to_port', 'alarm_fingerprint')" 2>/dev/null | wc -l)

if [ "$COLUMNS" -ge 3 ]; then
    echo -e "${GREEN}✓ New alarm columns exist${NC}"
else
    echo -e "${YELLOW}⚠ Some alarm columns missing (expected 3, found $COLUMNS)${NC}"
    echo "  Migration may need to be re-run"
fi

echo ""
echo "Step 4: Checking alarm data..."
ACTIVE_ALARMS=$(mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -sse "SELECT COUNT(*) FROM alarms WHERE status = 'ACTIVE'" 2>/dev/null)
echo "  Active alarms: $ACTIVE_ALARMS"

ACKNOWLEDGED_ALARMS=$(mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -sse "SELECT COUNT(*) FROM alarms WHERE status = 'ACKNOWLEDGED'" 2>/dev/null)
echo "  Acknowledged alarms: $ACKNOWLEDGED_ALARMS"

WHITELIST_COUNT=$(mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -sse "SELECT COUNT(*) FROM acknowledged_port_mac" 2>/dev/null)
echo "  Whitelist entries: $WHITELIST_COUNT"

echo ""
echo "Step 5: Sample whitelist entries..."
mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT device_name, port_number, mac_address, acknowledged_by, acknowledged_at FROM acknowledged_port_mac LIMIT 5" 2>/dev/null

echo ""
echo "Step 6: Checking PHP files..."
if [ -f "Switchp/port_alarms_component.php" ]; then
    echo -e "${GREEN}✓ port_alarms_component.php exists${NC}"
else
    echo -e "${RED}✗ port_alarms_component.php missing${NC}"
fi

if [ -f "Switchp/port_change_api.php" ]; then
    echo -e "${GREEN}✓ port_change_api.php exists${NC}"
    
    # Check for new functions
    if grep -q "bulkAcknowledgeAlarms" Switchp/port_change_api.php; then
        echo -e "${GREEN}  ✓ Bulk acknowledge function found${NC}"
    else
        echo -e "${YELLOW}  ⚠ Bulk acknowledge function not found${NC}"
    fi
    
    if grep -q "addToWhitelist" Switchp/port_change_api.php; then
        echo -e "${GREEN}  ✓ Whitelist function found${NC}"
    else
        echo -e "${YELLOW}  ⚠ Whitelist function not found${NC}"
    fi
else
    echo -e "${RED}✗ port_change_api.php missing${NC}"
fi

if [ -f "Switchp/index.php" ]; then
    if grep -q "port_alarms_component.php" Switchp/index.php; then
        echo -e "${GREEN}✓ Alarm component included in index.php${NC}"
    else
        echo -e "${YELLOW}⚠ Alarm component not included in index.php${NC}"
    fi
fi

echo ""
echo "Step 7: Testing API endpoint..."
echo "  Testing: port_change_api.php?action=get_active_alarms"
# This would require curl and web server running
# Skipped for now

echo ""
echo "======================================"
echo "Test Summary"
echo "======================================"
echo ""
echo -e "${GREEN}Completed Checks:${NC}"
echo "  - Database connection"
echo "  - Table existence"
echo "  - Column additions"
echo "  - File presence"
echo ""
echo -e "${YELLOW}Manual Testing Required:${NC}"
echo "  1. Open browser and navigate to index.php"
echo "  2. Verify 'Port Alarms' section appears"
echo "  3. Click 'Acknowledge' button on an alarm"
echo "  4. Check database for whitelist entry:"
echo "     mysql -u$DB_USER -p$DB_PASS $DB_NAME -e 'SELECT * FROM acknowledged_port_mac;'"
echo "  5. Generate same alarm again"
echo "  6. Verify it's suppressed (no new alarm)"
echo "  7. Test bulk acknowledge with multiple alarms"
echo "  8. Test 'View Port' navigation"
echo ""
echo -e "${GREEN}Troubleshooting:${NC}"
echo "  - If alarms don't appear: Check browser console for errors"
echo "  - If API fails: Check PHP error logs (Switchp/logs/php_errors.log)"
echo "  - If database errors: Verify migration ran successfully"
echo "  - If SNMP worker errors: Restart the worker process"
echo ""
echo "======================================"
