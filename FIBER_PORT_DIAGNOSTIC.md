# Fiber Port Diagnostic Guide

## Issue: Fiber portlarda bilgi gelmiyor (Fiber ports don't show information)

### Diagnostic Steps

#### Step 1: Check if SNMP Data Exists for Fiber Ports

Run this SQL query to check if fiber ports have data in the SNMP database:

```sql
-- Check if fiber ports have SNMP data
SELECT 
    d.name as device_name,
    p.port_number,
    p.port_name,
    p.port_description,
    p.admin_status,
    p.oper_status,
    p.vlan_id,
    p.mac_address,
    p.poll_timestamp
FROM port_status_data p
JOIN snmp_devices d ON p.device_id = d.id
WHERE p.port_number > (d.total_ports - 4)  -- Fiber ports (last 4)
ORDER BY d.name, p.port_number;
```

**Expected Result:**
- If rows exist: SNMP data is being collected for fiber ports ✅
- If no rows: SNMP worker is not collecting fiber port data ❌

#### Step 2: Check Manual Switch Port Data

```sql
-- Check if fiber ports have manual entry data
SELECT 
    s.name as switch_name,
    s.ports as total_ports,
    sp.port,
    sp.type,
    sp.device,
    sp.ip,
    sp.mac,
    sp.vlan
FROM switch_ports sp
JOIN switches s ON sp.switch_id = s.id
WHERE sp.port > (s.ports - 4)  -- Fiber ports (last 4)
AND sp.device IS NOT NULL
AND sp.device != ''
ORDER BY s.name, sp.port;
```

**Expected Result:**
- Shows manually entered fiber port connections
- Check if IP, MAC, VLAN fields are populated

#### Step 3: Test with Browser Console

Open browser console on index.php and run:

```javascript
// Check fiber port data
function checkFiberPorts() {
    const sw = selectedSwitch;
    if (!sw) {
        console.log('No switch selected');
        return;
    }
    
    const connections = portConnections[sw.id] || [];
    const fiberStartPort = sw.ports - 3; // Last 4 ports
    
    console.log(`\n=== Fiber Ports for ${sw.name} ===`);
    console.log(`Total ports: ${sw.ports}`);
    console.log(`Fiber ports: ${fiberStartPort} to ${sw.ports}`);
    
    for (let i = fiberStartPort; i <= sw.ports; i++) {
        const conn = connections.find(c => c.port === i);
        console.log(`\nPort ${i}:`);
        if (conn) {
            console.log('  - Type:', conn.type);
            console.log('  - Device:', conn.device);
            console.log('  - IP:', conn.ip);
            console.log('  - MAC:', conn.mac);
            console.log('  - VLAN:', conn.vlan);
            console.log('  - Connection Info:', conn.connection_info);
        } else {
            console.log('  - No connection data');
        }
    }
}

// Run check
checkFiberPorts();
```

#### Step 4: Check Port Tooltip

1. Open a switch detail view
2. Hover over a fiber port (typically ports 45-48 on 48-port switch)
3. Check what appears in tooltip
4. Take note of what information is missing

### Common Problems and Solutions

#### Problem 1: No SNMP Data for Fiber Ports

**Symptoms:**
- SQL query in Step 1 returns no rows
- Fiber ports show type but no IP/MAC/VLAN

**Cause:**
- SNMP worker not polling fiber port interfaces
- Port range query excludes high-numbered ports

**Solution:**
Check `snmp_worker/core/snmp_client.py` and ensure port polling includes all ports:
```python
# Make sure port range includes all ports 1 to total_ports
for port_num in range(1, device.total_ports + 1):
    # Collect data for all ports including fiber (high numbers)
```

#### Problem 2: Data Exists but Not Synced

**Symptoms:**
- SQL query shows SNMP data exists
- But index.php doesn't show the information

**Cause:**
- Sync function doesn't update switch_ports for fiber ports
- Fiber ports might be treated specially

**Solution:**
Check sync in `snmp_data_api.php` and ensure it processes all ports.

#### Problem 3: Display Logic Filters Fiber Ports

**Symptoms:**
- Data exists in switch_ports
- But tooltip shows nothing

**Cause:**
- Display code might skip fiber ports
- Connection object not loaded properly

**Solution:**
Add debugging to `showSwitchDetail` function to log fiber port connections.

### Quick Fix: Add Debug Logging

Add this code to index.php after line 5767 to see fiber port data:

```javascript
// Debug: Log fiber port data
if (isFiber) {
    console.log(`Fiber Port ${i}:`, {
        isConnected,
        connection,
        type: portType,
        device: deviceName
    });
}
```

### Manual Workaround

If SNMP data is not available, users can manually enter fiber port information:
1. Click Edit (pencil icon) on fiber port
2. Fill in: Type, Device, IP, MAC
3. Save
4. Information will appear in tooltip and port display

---

## Decision Tree

```
Fiber port shows no information
    │
    ├─ Is SNMP data being collected?
    │   │
    │   ├─ YES: Check sync process
    │   │   │
    │   │   ├─ Data in switch_ports? 
    │   │   │   │
    │   │   │   ├─ YES: Display bug - fix tooltip/display code
    │   │   │   └─ NO: Sync bug - fix sync function
    │   │   │
    │   └─ NO: SNMP worker bug - fix data collection
    │
    └─ Is manual data entered?
        │
        ├─ YES: Display bug - check tooltip code
        └─ NO: No data source - need to add data
```

---

## Contact Developer

If fiber ports still don't show information after these checks:
1. Share results from Step 1 & 2 SQL queries
2. Share browser console output from Step 3
3. Describe what appears in tooltip (Step 4)
4. Specify switch model and which fiber ports have connections
