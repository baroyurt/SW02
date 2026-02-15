# Alarm Management System Implementation

## Overview
This update implements a comprehensive alarm management system for network port monitoring with the following key features:

### ✅ Implemented Features

#### 1. Alarm Uniqueness & Deduplication
- **Alarm Fingerprinting**: Each alarm now has a unique fingerprint based on:
  - device_name
  - port_number
  - mac_address
  - from_port
  - to_port
  - alarm_type

- **Duplicate Prevention**: Same alarms are no longer created multiple times
- **Counter Tracking**: Duplicate alarm attempts increment `occurrence_count` and update `last_occurrence`

#### 2. MAC+Port Whitelist System
- **Database Table**: `acknowledged_port_mac` stores permanently whitelisted combinations
- **Automatic Suppression**: Whitelisted MAC+Port combinations won't trigger new alarms
- **Acknowledge Behavior**: When user clicks "Bilgi Dahilinde Kapat" (Acknowledge as Known):
  - Alarm status → ACKNOWLEDGED
  - MAC+Port combination added to whitelist
  - Future alarms for same MAC+Port won't be created

#### 3. Enhanced Port Alarms UI
- **Embedded Component**: Port alarms now integrated into main dashboard (index.php)
- **Consistent Design**: Matches existing index.php styling
- **Real-time Updates**: Auto-refreshes every 30 seconds
- **Key Features**:
  - Filter by alarm type (All, MAC Moved, VLAN Changed, Description)
  - Shows occurrence counter for repeated alarms
  - Click "View Port" to navigate to device/port in main view
  - Timestamps for first_seen and last_seen

#### 4. Bulk Operations
- **Multi-select**: Checkbox on each alarm card
- **Bulk Acknowledge**: Select multiple alarms and acknowledge all at once
- **Automatic Whitelisting**: All selected MAC+Port combinations added to whitelist

#### 5. Backend Improvements (Python)
- **database_manager.py**:
  - Updated `get_or_create_alarm()` with whitelist checking
  - Added fingerprint generation for uniqueness
  - Added support for from_port/to_port parameters

- **port_change_detector.py**:
  - Updated MAC movement detection to pass all required parameters

#### 6. Backend Improvements (PHP)
- **port_change_api.php**:
  - New `bulk_acknowledge` action for multi-alarm operations
  - Enhanced `acknowledgeAlarm()` to add to whitelist
  - Added `addToWhitelist()` helper function
  - Updated alarms query to use ACTIVE status (uppercase)

### 📦 Database Migration

**File**: `Switchp/snmp_worker/migrations/add_acknowledged_port_mac_table.sql`

**Changes**:
- New table: `acknowledged_port_mac` with columns:
  - device_name, port_number, mac_address
  - acknowledged_by, acknowledged_at
  - note (optional user comment)
  - Unique constraint on (device_name, port_number, mac_address)

- Added columns to `alarms` table:
  - `from_port` - source port for MAC movements
  - `to_port` - destination port for MAC movements  
  - `alarm_fingerprint` - unique identifier for deduplication

**To Apply Migration**:
```bash
cd Switchp
php apply_migration.php
```

Or manually:
```bash
mysql -u root -p switchdb < snmp_worker/migrations/add_acknowledged_port_mac_table.sql
```

### 🎯 How It Works

#### Alarm Lifecycle

1. **Detection**: Port change detected (MAC moved, VLAN changed, etc.)

2. **Whitelist Check**: 
   - If MAC+Port is in `acknowledged_port_mac` → **NO ALARM CREATED**
   - Otherwise, proceed to step 3

3. **Uniqueness Check**:
   - Generate fingerprint from device, port, MAC, from_port, to_port
   - Check if active alarm with same fingerprint exists
   - If YES → Increment `occurrence_count` and update `last_occurrence`
   - If NO → Create new alarm

4. **User Action**:
   - User clicks "Bilgi Dahilinde Kapat" (Acknowledge)
   - Alarm status → ACKNOWLEDGED
   - MAC+Port → Added to `acknowledged_port_mac` whitelist
   - Future alarms for this combination → Suppressed

#### Example Scenarios

**Scenario 1: Same MAC keeps appearing on same port**
- First time: Alarm created
- Second time: occurrence_count = 2, last_occurrence updated
- User acknowledges → Added to whitelist
- Third time: NO ALARM (whitelisted)

**Scenario 2: Same MAC moves to different port**
- MAC on Port 1 → Alarm 1
- User acknowledges Port 1 → Whitelisted
- MAC moves to Port 2 → NEW ALARM (different port)
- User acknowledges Port 2 → Also whitelisted

**Scenario 3: Different MAC on same port**
- MAC-A on Port 1 → Alarm 1
- User acknowledges → MAC-A + Port 1 whitelisted
- MAC-B on Port 1 → NEW ALARM (different MAC)

### 📁 File Changes

**New Files**:
- `Switchp/port_alarms_component.php` - Embedded alarm UI component
- `Switchp/snmp_worker/migrations/add_acknowledged_port_mac_table.sql` - Database migration
- `Switchp/apply_migration.php` - Migration application script

**Modified Files**:
- `Switchp/index.php` - Added alarm component inclusion
- `Switchp/port_change_api.php` - Added whitelist management, bulk operations
- `Switchp/snmp_worker/core/database_manager.py` - Alarm uniqueness logic
- `Switchp/snmp_worker/core/port_change_detector.py` - Pass additional alarm parameters

### 🔧 Configuration

No additional configuration needed. The system uses existing database connection settings.

### 🚀 Deployment Steps

1. **Apply Database Migration**:
   ```bash
   cd Switchp
   php apply_migration.php
   ```

2. **Restart SNMP Worker** (if running):
   ```bash
   cd Switchp/snmp_worker
   python main.py
   ```

3. **Access Dashboard**:
   - Navigate to `index.php`
   - Port Alarms section now visible on main dashboard
   - Alarms auto-refresh every 30 seconds

### 📊 Testing Checklist

- [ ] Database migration applied successfully
- [ ] Alarms visible in embedded component
- [ ] Acknowledge button works and adds to whitelist
- [ ] Bulk acknowledge works for multiple alarms
- [ ] Whitelisted MAC+Port combinations don't create new alarms
- [ ] Occurrence counter increments for duplicate alarms
- [ ] Navigate to device/port works from alarm card
- [ ] Real-time refresh updates alarm list

### 🔍 Remaining Tasks

The following features from the requirements are not yet implemented but can be added:

1. **Fiber Port Support**: Add FDB/LLDP/ARP fallback for fiber ports without MAC info
2. **URL Parameters**: Add ?device=XXX&port=YY support for direct navigation
3. **Scroll/Highlight**: Smooth scroll and highlight animation when navigating to port
4. **Silence Alarm**: Temporary silence feature (already has UI but needs backend completion)
5. **Confirmation Modal**: Enhanced confirmation with warning message

### 📝 Notes

- All status values are now uppercase (ACTIVE, ACKNOWLEDGED, RESOLVED) for consistency with AlarmStatus enum
- Whitelist check uses raw SQL for compatibility (can be converted to SQLAlchemy model later)
- The component is embedded using PHP include for easy maintenance
- Auto-refresh can be disabled by modifying the interval in port_alarms_component.php

### 🐛 Known Issues

- Database might not be running in development environment - migration needs to be applied on deployment
- Some Python debug print statements remain (can be removed in production)
