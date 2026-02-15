<?php
/**
 * Port Change Alarms API
 * Handles fetching and managing port change alarms (MAC movements, VLAN changes, etc.)
 */

require_once 'db.php';
require_once 'auth.php';

$auth = new Auth($conn);
$auth->requireLogin();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_active_alarms':
            getActiveAlarms($conn);
            break;
            
        case 'get_port_changes':
            $deviceId = isset($_GET['device_id']) ? intval($_GET['device_id']) : 0;
            $portNumber = isset($_GET['port_number']) ? intval($_GET['port_number']) : 0;
            getPortChanges($conn, $deviceId, $portNumber);
            break;
            
        case 'acknowledge_alarm':
            // Accept both GET and POST for compatibility
            $alarmId = isset($_REQUEST['alarm_id']) ? intval($_REQUEST['alarm_id']) : 0;
            $ackType = isset($_REQUEST['ack_type']) ? $_REQUEST['ack_type'] : 'known_change';
            $note = isset($_REQUEST['note']) ? $_REQUEST['note'] : '';
            acknowledgeAlarm($conn, $auth, $alarmId, $ackType, $note);
            break;
            
        case 'bulk_acknowledge':
            // Bulk acknowledge multiple alarms
            $alarmIds = isset($_REQUEST['alarm_ids']) ? $_REQUEST['alarm_ids'] : [];
            $ackType = isset($_REQUEST['ack_type']) ? $_REQUEST['ack_type'] : 'known_change';
            $note = isset($_REQUEST['note']) ? $_REQUEST['note'] : '';
            bulkAcknowledgeAlarms($conn, $auth, $alarmIds, $ackType, $note);
            break;
            
        case 'silence_alarm':
            // Accept both GET and POST for compatibility
            $alarmId = isset($_REQUEST['alarm_id']) ? intval($_REQUEST['alarm_id']) : 0;
            $duration = isset($_REQUEST['duration']) ? intval($_REQUEST['duration']) : (isset($_REQUEST['duration_hours']) ? intval($_REQUEST['duration_hours']) : 24);
            silenceAlarm($conn, $auth, $alarmId, $duration);
            break;
            
        case 'get_alarm_details':
            $alarmId = isset($_GET['alarm_id']) ? intval($_GET['alarm_id']) : 0;
            getAlarmDetails($conn, $alarmId);
            break;
            
        case 'get_mac_history':
            $macAddress = isset($_GET['mac_address']) ? $_GET['mac_address'] : '';
            getMACHistory($conn, $macAddress);
            break;
            
        case 'get_port_history':
            $deviceId = isset($_GET['device_id']) ? intval($_GET['device_id']) : 0;
            $portNumber = isset($_GET['port_number']) ? intval($_GET['port_number']) : 0;
            getPortHistory($conn, $deviceId, $portNumber);
            break;
            
        case 'get_recently_changed_ports':
            $hours = isset($_GET['hours']) ? intval($_GET['hours']) : 24;
            getRecentlyChangedPorts($conn, $hours);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Get active alarms with port change details
 */
function getActiveAlarms($conn) {
    $sql = "SELECT 
                a.id, a.device_id, a.alarm_type, a.severity, a.status,
                a.port_number, a.title, a.message, a.details,
                a.occurrence_count, a.first_occurrence, a.last_occurrence,
                a.acknowledged_at, a.acknowledged_by, a.acknowledgment_type,
                a.silence_until, a.mac_address, a.old_value, a.new_value,
                a.from_port, a.to_port,
                d.name as device_name, d.ip_address as device_ip,
                CASE 
                    WHEN a.silence_until > NOW() THEN 1
                    ELSE 0
                END as is_silenced,
                CASE
                    WHEN a.alarm_type IN ('mac_moved', 'mac_added', 'vlan_changed', 'description_changed') THEN 1
                    ELSE 0
                END as is_port_change
            FROM alarms a
            LEFT JOIN snmp_devices d ON a.device_id = d.id
            WHERE a.status = 'ACTIVE'
            ORDER BY 
                CASE a.severity
                    WHEN 'CRITICAL' THEN 1
                    WHEN 'HIGH' THEN 2
                    WHEN 'MEDIUM' THEN 3
                    WHEN 'LOW' THEN 4
                    WHEN 'INFO' THEN 5
                END,
                a.last_occurrence DESC";
    
    $result = $conn->query($sql);
    $alarms = [];
    
    while ($row = $result->fetch_assoc()) {
        $alarms[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'alarms' => $alarms,
        'total_count' => count($alarms),
        'port_change_count' => count(array_filter($alarms, function($a) { 
            return $a['is_port_change'] == 1; 
        }))
    ]);
}

/**
 * Get port change history
 */
function getPortChanges($conn, $deviceId, $portNumber = null) {
    $sql = "SELECT 
                pch.id, pch.device_id, pch.port_number, pch.change_type,
                pch.change_timestamp, pch.old_value, pch.new_value,
                pch.old_mac_address, pch.new_mac_address,
                pch.old_vlan_id, pch.new_vlan_id,
                pch.old_description, pch.new_description,
                pch.from_device_id, pch.from_port_number,
                pch.to_device_id, pch.to_port_number,
                pch.change_details, pch.alarm_created, pch.alarm_id,
                d.name as device_name,
                fd.name as from_device_name,
                td.name as to_device_name
            FROM port_change_history pch
            LEFT JOIN snmp_devices d ON pch.device_id = d.id
            LEFT JOIN snmp_devices fd ON pch.from_device_id = fd.id
            LEFT JOIN snmp_devices td ON pch.to_device_id = td.id
            WHERE pch.device_id = ?";
    
    $params = [$deviceId];
    $types = 'i';
    
    if ($portNumber) {
        $sql .= " AND pch.port_number = ?";
        $params[] = $portNumber;
        $types .= 'i';
    }
    
    $sql .= " ORDER BY pch.change_timestamp DESC LIMIT 100";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $changes = [];
    
    while ($row = $result->fetch_assoc()) {
        $changes[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'changes' => $changes
    ]);
}

/**
 * Acknowledge an alarm
 */
function acknowledgeAlarm($conn, $auth, $alarmId, $ackType, $note) {
    $user = $auth->getUser();
    
    $conn->begin_transaction();
    
    try {
        // Get alarm
        $stmt = $conn->prepare("SELECT * FROM alarms WHERE id = ?");
        $stmt->bind_param("i", $alarmId);
        $stmt->execute();
        $alarm = $stmt->get_result()->fetch_assoc();
        
        if (!$alarm) {
            throw new Exception('Alarm not found');
        }
        
        if ($ackType === 'known_change') {
            // Mark as acknowledged
            $stmt = $conn->prepare("
                UPDATE alarms 
                SET status = 'ACKNOWLEDGED',
                    acknowledged_at = NOW(),
                    acknowledged_by = ?,
                    acknowledgment_type = 'known_change',
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("si", $user['username'], $alarmId);
            $stmt->execute();
            
            // Add to whitelist if MAC address and port are present
            if (!empty($alarm['mac_address']) && !empty($alarm['port_number'])) {
                $deviceName = getDeviceName($conn, $alarm['device_id']);
                addToWhitelist(
                    $conn,
                    $deviceName,
                    $alarm['port_number'],
                    $alarm['mac_address'],
                    $user['username'],
                    $note
                );
            }
            
            // Add to alarm history
            $stmt = $conn->prepare("
                INSERT INTO alarm_history 
                (alarm_id, old_status, new_status, change_reason, change_message, changed_at)
                VALUES (?, 'ACTIVE', 'ACKNOWLEDGED', 'Acknowledged by user', ?, NOW())
            ");
            $message = "Acknowledged as known change by {$user['username']}: $note";
            $stmt->bind_param("is", $alarmId, $message);
            $stmt->execute();
            
        } else if ($ackType === 'resolved') {
            // Mark as resolved
            $stmt = $conn->prepare("
                UPDATE alarms 
                SET status = 'RESOLVED',
                    resolved_at = NOW(),
                    resolved_by = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("si", $user['username'], $alarmId);
            $stmt->execute();
            
            // Add to alarm history
            $stmt = $conn->prepare("
                INSERT INTO alarm_history 
                (alarm_id, old_status, new_status, change_reason, change_message, changed_at)
                VALUES (?, 'ACTIVE', 'RESOLVED', 'Resolved by user', ?, NOW())
            ");
            $message = "Resolved by {$user['username']}: $note";
            $stmt->bind_param("is", $alarmId, $message);
            $stmt->execute();
        }
        
        // Log activity
        $auth->logActivity(
            $user['id'],
            $user['username'],
            'alarm_acknowledge',
            "Acknowledged alarm #{$alarmId} as $ackType"
        );
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Alarm acknowledged successfully'
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * Silence an alarm (remove red highlighting but keep it active)
 */
function silenceAlarm($conn, $auth, $alarmId, $durationHours) {
    $user = $auth->getUser();
    
    $stmt = $conn->prepare("
        UPDATE alarms 
        SET acknowledgment_type = 'silenced',
            silence_until = DATE_ADD(NOW(), INTERVAL ? HOUR),
            acknowledged_at = NOW(),
            acknowledged_by = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("isi", $durationHours, $user['username'], $alarmId);
    $stmt->execute();
    
    // Log activity
    $auth->logActivity(
        $user['id'],
        $user['username'],
        'alarm_silence',
        "Silenced alarm #{$alarmId} for {$durationHours} hours"
    );
    
    echo json_encode([
        'success' => true,
        'message' => "Alarm silenced for {$durationHours} hours"
    ]);
}

/**
 * Get MAC address movement history
 */
function getMACHistory($conn, $macAddress) {
    $sql = "SELECT 
                mat.*,
                cd.name as current_device_name,
                pd.name as previous_device_name,
                (SELECT COUNT(*) FROM port_change_history pch 
                 WHERE pch.old_mac_address = mat.mac_address 
                 OR pch.new_mac_address = mat.mac_address) as total_changes
            FROM mac_address_tracking mat
            LEFT JOIN snmp_devices cd ON mat.current_device_id = cd.id
            LEFT JOIN snmp_devices pd ON mat.previous_device_id = pd.id
            WHERE mat.mac_address = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $macAddress);
    $stmt->execute();
    
    $tracking = $stmt->get_result()->fetch_assoc();
    
    // Get change history
    $sql = "SELECT 
                pch.*,
                d.name as device_name,
                fd.name as from_device_name,
                td.name as to_device_name
            FROM port_change_history pch
            LEFT JOIN snmp_devices d ON pch.device_id = d.id
            LEFT JOIN snmp_devices fd ON pch.from_device_id = fd.id
            LEFT JOIN snmp_devices td ON pch.to_device_id = td.id
            WHERE pch.old_mac_address = ? OR pch.new_mac_address = ?
            ORDER BY pch.change_timestamp DESC
            LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $macAddress, $macAddress);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $history = [];
    
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'tracking' => $tracking,
        'history' => $history
    ]);
}

/**
 * Get port change history
 */
function getPortHistory($conn, $deviceId, $portNumber) {
    // Get snapshots
    $sql = "SELECT *
            FROM port_snapshot
            WHERE device_id = ? AND port_number = ?
            ORDER BY snapshot_timestamp DESC
            LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $deviceId, $portNumber);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $snapshots = [];
    
    while ($row = $result->fetch_assoc()) {
        $snapshots[] = $row;
    }
    
    // Get changes
    $sql = "SELECT 
                pch.*,
                d.name as device_name
            FROM port_change_history pch
            LEFT JOIN snmp_devices d ON pch.device_id = d.id
            WHERE pch.device_id = ? AND pch.port_number = ?
            ORDER BY pch.change_timestamp DESC
            LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $deviceId, $portNumber);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $changes = [];
    
    while ($row = $result->fetch_assoc()) {
        $changes[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'snapshots' => $snapshots,
        'changes' => $changes
    ]);
}

/**
 * Get recently changed ports (for highlighting in red)
 */
function getRecentlyChangedPorts($conn, $hours = 24) {
    $sql = "SELECT DISTINCT
                pch.device_id,
                pch.port_number,
                d.name as device_name,
                d.ip_address,
                COUNT(pch.id) as change_count,
                MAX(pch.change_timestamp) as last_change,
                GROUP_CONCAT(DISTINCT pch.change_type) as change_types
            FROM port_change_history pch
            LEFT JOIN snmp_devices d ON pch.device_id = d.id
            WHERE pch.change_timestamp > DATE_SUB(NOW(), INTERVAL ? HOUR)
            GROUP BY pch.device_id, pch.port_number, d.name, d.ip_address
            ORDER BY MAX(pch.change_timestamp) DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $hours);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $changed_ports = [];
    
    while ($row = $result->fetch_assoc()) {
        // Create a key for easy lookup
        $key = $row['device_id'] . '_' . $row['port_number'];
        $changed_ports[$key] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'changed_ports' => $changed_ports,
        'hours' => $hours,
        'count' => count($changed_ports)
    ]);
}

/**
 * Get detailed information about a specific alarm
 */
function getAlarmDetails($conn, $alarmId) {
    if ($alarmId <= 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid alarm ID'
        ]);
        return;
    }
    
    $sql = "SELECT 
                a.id, a.device_id, a.alarm_type, a.severity, a.status,
                a.port_number, a.title, a.message, a.details,
                a.occurrence_count, a.first_occurrence, a.last_occurrence,
                a.acknowledged_at, a.acknowledged_by, a.acknowledgment_type,
                a.resolved_at, a.resolved_by,
                a.silence_until, a.mac_address, a.old_value, a.new_value,
                a.created_at, a.updated_at,
                d.name as device_name, d.ip_address as device_ip,
                CASE 
                    WHEN a.silence_until > NOW() THEN 1
                    ELSE 0
                END as is_silenced
            FROM alarms a
            LEFT JOIN snmp_devices d ON a.device_id = d.id
            WHERE a.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $alarmId);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $alarm = $result->fetch_assoc();
    
    if (!$alarm) {
        echo json_encode([
            'success' => false,
            'error' => 'Alarm not found'
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'alarm' => $alarm
    ]);
}

/**
 * Get device name by ID
 */
function getDeviceName($conn, $deviceId) {
    $stmt = $conn->prepare("SELECT name FROM snmp_devices WHERE id = ?");
    $stmt->bind_param("i", $deviceId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row ? $row['name'] : '';
}

/**
 * Add MAC+Port combination to whitelist
 */
function addToWhitelist($conn, $deviceName, $portNumber, $macAddress, $acknowledgedBy, $note) {
    try {
        // Check if already whitelisted
        $stmt = $conn->prepare("
            SELECT id FROM acknowledged_port_mac
            WHERE device_name = ? AND port_number = ? AND mac_address = ?
        ");
        $stmt->bind_param("sis", $deviceName, $portNumber, $macAddress);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Already whitelisted, update note
            $stmt = $conn->prepare("
                UPDATE acknowledged_port_mac
                SET note = ?, acknowledged_by = ?, updated_at = NOW()
                WHERE device_name = ? AND port_number = ? AND mac_address = ?
            ");
            $stmt->bind_param("sssis", $note, $acknowledgedBy, $deviceName, $portNumber, $macAddress);
            $stmt->execute();
        } else {
            // Add to whitelist
            $stmt = $conn->prepare("
                INSERT INTO acknowledged_port_mac
                (device_name, port_number, mac_address, acknowledged_by, note)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sisss", $deviceName, $portNumber, $macAddress, $acknowledgedBy, $note);
            $stmt->execute();
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Failed to add to whitelist: " . $e->getMessage());
        return false;
    }
}

/**
 * Bulk acknowledge multiple alarms
 */
function bulkAcknowledgeAlarms($conn, $auth, $alarmIds, $ackType, $note) {
    $user = $auth->getUser();
    
    // Parse alarm IDs if it's a JSON string
    if (is_string($alarmIds)) {
        $alarmIds = json_decode($alarmIds, true);
    }
    
    if (!is_array($alarmIds) || empty($alarmIds)) {
        echo json_encode([
            'success' => false,
            'error' => 'No alarm IDs provided'
        ]);
        return;
    }
    
    $conn->begin_transaction();
    
    try {
        $successCount = 0;
        $failedCount = 0;
        
        foreach ($alarmIds as $alarmId) {
            $alarmId = intval($alarmId);
            if ($alarmId <= 0) {
                $failedCount++;
                continue;
            }
            
            // Get alarm
            $stmt = $conn->prepare("SELECT * FROM alarms WHERE id = ?");
            $stmt->bind_param("i", $alarmId);
            $stmt->execute();
            $alarm = $stmt->get_result()->fetch_assoc();
            
            if (!$alarm) {
                $failedCount++;
                continue;
            }
            
            // Mark as acknowledged
            $stmt = $conn->prepare("
                UPDATE alarms 
                SET status = 'ACKNOWLEDGED',
                    acknowledged_at = NOW(),
                    acknowledged_by = ?,
                    acknowledgment_type = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("ssi", $user['username'], $ackType, $alarmId);
            $stmt->execute();
            
            // Add to whitelist if MAC address and port are present
            if (!empty($alarm['mac_address']) && !empty($alarm['port_number'])) {
                $deviceName = getDeviceName($conn, $alarm['device_id']);
                addToWhitelist(
                    $conn,
                    $deviceName,
                    $alarm['port_number'],
                    $alarm['mac_address'],
                    $user['username'],
                    $note
                );
            }
            
            // Add to alarm history
            $stmt = $conn->prepare("
                INSERT INTO alarm_history 
                (alarm_id, old_status, new_status, change_reason, change_message, changed_at)
                VALUES (?, 'ACTIVE', 'ACKNOWLEDGED', 'Bulk acknowledged by user', ?, NOW())
            ");
            $message = "Bulk acknowledged by {$user['username']}: $note";
            $stmt->bind_param("is", $alarmId, $message);
            $stmt->execute();
            
            $successCount++;
        }
        
        // Log activity
        $auth->logActivity(
            $user['id'],
            $user['username'],
            'bulk_alarm_acknowledge',
            "Bulk acknowledged $successCount alarms"
        );
        
        $conn->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "$successCount alarms acknowledged successfully",
            'acknowledged_count' => $successCount,
            'failed_count' => $failedCount
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}



