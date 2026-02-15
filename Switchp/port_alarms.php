<?php
/**
 * Port Change Alarms Dashboard
 * Dedicated page for viewing and managing port change alarms
 */

session_start();
require_once 'db.php';
require_once 'auth.php';

// Initialize auth
$auth = new Auth($conn);

// Check if user is authenticated
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Cache control headers
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$pageTitle = "Port Değişiklik Alarmları";
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        
        .alarms-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .page-header {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-header h1 {
            margin: 0;
            color: #333;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .page-header h1 i {
            color: #f59e0b;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .filter-bar {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .filter-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 10px 20px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .filter-btn:hover {
            border-color: #3498db;
            background: #ecf0f1;
        }
        
        .filter-btn.active {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .alarms-list {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .alarm-item {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .alarm-item:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-color: #3498db;
        }
        
        .alarm-item.critical {
            border-left: 5px solid #ef4444;
        }
        
        .alarm-item.high {
            border-left: 5px solid #f59e0b;
        }
        
        .alarm-item.medium {
            border-left: 5px solid #fbbf24;
        }
        
        .alarm-item.low {
            border-left: 5px solid #10b981;
        }
        
        .alarm-item.silenced {
            opacity: 0.7;
            background: #f9fafb;
        }
        
        .alarm-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .alarm-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }
        
        .alarm-title:hover {
            color: #3498db;
        }
        
        .alarm-severity {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .alarm-severity.critical {
            background: #ef4444;
            color: white;
        }
        
        .alarm-severity.high {
            background: #f59e0b;
            color: white;
        }
        
        .alarm-severity.medium {
            background: #fbbf24;
            color: #333;
        }
        
        .alarm-severity.low {
            background: #10b981;
            color: white;
        }
        
        .alarm-message {
            color: #555;
            margin-bottom: 12px;
            line-height: 1.6;
        }
        
        .alarm-change-details {
            background: rgba(0,0,0,0.05);
            border-radius: 5px;
            padding: 12px;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .change-value {
            padding: 5px 12px;
            border-radius: 5px;
            font-family: monospace;
            font-size: 14px;
        }
        
        .change-old {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .change-new {
            background: #d1fae5;
            color: #059669;
        }
        
        .alarm-meta {
            display: flex;
            gap: 20px;
            color: #888;
            font-size: 14px;
            margin-bottom: 12px;
        }
        
        .alarm-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .alarm-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 14px;
            margin-bottom: 12px;
        }
        
        .alarm-status-badge.silenced {
            background: rgba(255, 193, 7, 0.2);
            color: #f59e0b;
        }
        
        .alarm-status-badge.acknowledged {
            background: rgba(40, 167, 69, 0.2);
            color: #059669;
        }
        
        .alarm-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
        }
        
        .btn-acknowledge {
            background: #3498db;
            color: white;
        }
        
        .btn-acknowledge:hover {
            background: #2980b9;
        }
        
        .btn-silence {
            background: #e67e22;
            color: white;
        }
        
        .btn-silence:hover {
            background: #d35400;
        }
        
        .btn-details {
            background: #95a5a6;
            color: white;
        }
        
        .btn-details:hover {
            background: #7f8c8d;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #888;
        }
        
        .empty-state i {
            font-size: 64px;
            color: #059669;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            font-size: 16px;
        }
        
        .loading-state {
            text-align: center;
            padding: 60px 20px;
            color: #888;
        }
        
        .loading-state i {
            font-size: 48px;
            color: #3498db;
            margin-bottom: 20px;
        }
        
        /* Modal styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal {
            background: white;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #888;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 5px;
        }
        
        .modal-close:hover {
            background: #f0f0f0;
            color: #333;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 20px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .toast.success {
            border-left: 4px solid #10b981;
        }
        
        .toast.error {
            border-left: 4px solid #ef4444;
        }
        
        .toast.info {
            border-left: 4px solid #3498db;
        }
    </style>
</head>
<body>
    <div class="alarms-container">
        <div class="page-header">
            <h1>
                <i class="fas fa-exclamation-triangle"></i>
                Port Değişiklik Alarmları
            </h1>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="window.location.href='index.php'">
                    <i class="fas fa-arrow-left"></i> Ana Sayfa
                </button>
                <button class="btn btn-primary" onclick="loadAlarms()">
                    <i class="fas fa-sync-alt"></i> Yenile
                </button>
            </div>
        </div>
        
        <div class="filter-bar">
            <div class="filter-buttons">
                <button class="filter-btn active" data-filter="all" onclick="filterAlarms('all')">
                    <i class="fas fa-list"></i> Tümü
                    <span class="badge" id="badge-all">0</span>
                </button>
                <button class="filter-btn" data-filter="mac_moved" onclick="filterAlarms('mac_moved')">
                    <i class="fas fa-exchange-alt"></i> MAC Taşındı
                    <span class="badge" id="badge-mac_moved">0</span>
                </button>
                <button class="filter-btn" data-filter="vlan_changed" onclick="filterAlarms('vlan_changed')">
                    <i class="fas fa-network-wired"></i> VLAN Değişti
                    <span class="badge" id="badge-vlan_changed">0</span>
                </button>
                <button class="filter-btn" data-filter="description_changed" onclick="filterAlarms('description_changed')">
                    <i class="fas fa-edit"></i> Açıklama Değişti
                    <span class="badge" id="badge-description_changed">0</span>
                </button>
            </div>
        </div>
        
        <div class="alarms-list" id="alarms-list">
            <div class="loading-state">
                <i class="fas fa-spinner fa-spin"></i>
                <p>Alarmlar yükleniyor...</p>
            </div>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <div class="modal-overlay" id="confirm-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="confirm-title">
                    <i class="fas fa-question-circle"></i> Onay
                </h3>
                <button class="modal-close" onclick="closeConfirmModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p id="confirm-message">Emin misiniz?</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeConfirmModal()">İptal</button>
                <button class="btn btn-primary" id="confirm-action-btn" onclick="confirmAction()">Onayla</button>
            </div>
        </div>
    </div>
    
    <!-- Silence Duration Modal -->
    <div class="modal-overlay" id="silence-modal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-volume-mute"></i> Alarmı Sesize Al
                </h3>
                <button class="modal-close" onclick="closeSilenceModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="silence-duration">Sesize Alma Süresi:</label>
                    <select id="silence-duration" class="form-control">
                        <option value="1">1 Saat</option>
                        <option value="4">4 Saat</option>
                        <option value="24" selected>24 Saat (1 Gün)</option>
                        <option value="168">168 Saat (1 Hafta)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeSilenceModal()">İptal</button>
                <button class="btn btn-primary" onclick="confirmSilence()">Sesize Al</button>
            </div>
        </div>
    </div>
    
    <!-- Alarm Details Modal -->
    <div class="modal-overlay" id="details-modal">
        <div class="modal" style="max-width: 700px;">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-info-circle"></i> Alarm Detayları
                </h3>
                <button class="modal-close" onclick="closeDetailsModal()">&times;</button>
            </div>
            <div class="modal-body" id="details-modal-body">
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Detaylar yükleniyor...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeDetailsModal()">Kapat</button>
            </div>
        </div>
    </div>
    
    <script>
        let currentFilter = 'all';
        let allAlarms = [];
        let pendingAction = null;
        let autoRefreshInterval = null;
        
        // Load alarms on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadAlarms();
            
            // Auto-refresh every 30 seconds, but only when page is visible
            autoRefreshInterval = setInterval(() => {
                if (!document.hidden) {
                    loadAlarms();
                }
            }, 30000);
            
            // Refresh when page becomes visible again
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    loadAlarms();
                }
            });
        });
        
        async function loadAlarms() {
            try {
                const response = await fetch('port_change_api.php?action=get_active_alarms');
                const data = await response.json();
                
                if (!data.success) {
                    throw new Error(data.error || 'Failed to load alarms');
                }
                
                allAlarms = data.alarms || [];
                updateBadges();
                displayAlarms(currentFilter);
            } catch (error) {
                console.error('Error loading alarms:', error);
                showToast('Alarmlar yüklenirken hata oluştu: ' + error.message, 'error');
            }
        }
        
        function updateBadges() {
            const counts = {
                all: allAlarms.length,
                mac_moved: allAlarms.filter(a => a.alarm_type === 'mac_moved').length,
                vlan_changed: allAlarms.filter(a => a.alarm_type === 'vlan_changed').length,
                description_changed: allAlarms.filter(a => a.alarm_type === 'description_changed').length
            };
            
            Object.keys(counts).forEach(key => {
                const badge = document.getElementById(`badge-${key}`);
                if (badge) {
                    badge.textContent = counts[key];
                }
            });
        }
        
        function filterAlarms(filter) {
            currentFilter = filter;
            
            // Update filter button states
            document.querySelectorAll('.filter-btn').forEach(btn => {
                if (btn.dataset.filter === filter) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            
            displayAlarms(filter);
        }
        
        function displayAlarms(filter) {
            const container = document.getElementById('alarms-list');
            
            if (!allAlarms || allAlarms.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <h3>Aktif Alarm Bulunmuyor</h3>
                        <p>Tüm portlar normal durumda</p>
                    </div>
                `;
                return;
            }
            
            let filteredAlarms = allAlarms;
            if (filter !== 'all') {
                filteredAlarms = allAlarms.filter(a => a.alarm_type === filter);
            }
            
            if (filteredAlarms.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-filter"></i>
                        <h3>Bu Kategoride Alarm Bulunmuyor</h3>
                        <p>Farklı bir filtre seçerek diğer alarmları görebilirsiniz</p>
                    </div>
                `;
                return;
            }
            
            let html = '';
            filteredAlarms.forEach(alarm => {
                const severityClass = alarm.severity.toLowerCase();
                const isSilenced = alarm.is_silenced == 1;
                const isAcknowledged = alarm.acknowledged_at != null;
                
                html += `
                    <div class="alarm-item ${severityClass} ${isSilenced ? 'silenced' : ''}" data-alarm-id="${alarm.id}">
                        <div class="alarm-header">
                            <div class="alarm-title" onclick="navigateToPort(${alarm.device_id}, ${alarm.port_number || 0}, '${escapeHtml(alarm.device_name)}', '${escapeHtml(alarm.device_ip || '')}')">
                                <i class="fas fa-network-wired"></i>
                                ${escapeHtml(alarm.device_name)}${alarm.port_number ? ' - Port ' + alarm.port_number : ''}
                            </div>
                            <span class="alarm-severity ${severityClass}">${alarm.severity}</span>
                        </div>
                        
                        <div class="alarm-message">${escapeHtml(alarm.message)}</div>
                        
                        ${alarm.old_value && alarm.new_value ? `
                            <div class="alarm-change-details">
                                <span class="change-value change-old">${escapeHtml(alarm.old_value)}</span>
                                <i class="fas fa-arrow-right"></i>
                                <span class="change-value change-new">${escapeHtml(alarm.new_value)}</span>
                            </div>
                        ` : ''}
                        
                        <div class="alarm-meta">
                            <span><i class="fas fa-clock"></i> ${formatDate(alarm.last_occurrence)}</span>
                            ${alarm.occurrence_count > 1 ? `<span><i class="fas fa-redo"></i> ${alarm.occurrence_count} kez</span>` : ''}
                        </div>
                        
                        ${isSilenced ? `
                            <div class="alarm-status-badge silenced">
                                <i class="fas fa-volume-mute"></i> Sesize alındı
                            </div>
                        ` : ''}
                        
                        ${isAcknowledged ? `
                            <div class="alarm-status-badge acknowledged">
                                <i class="fas fa-check"></i> Bilgi dahilinde
                            </div>
                        ` : ''}
                        
                        ${!isAcknowledged ? `
                            <div class="alarm-actions">
                                <button class="btn btn-sm btn-acknowledge" onclick="acknowledgeAlarm(${alarm.id})">
                                    <i class="fas fa-check"></i> Bilgi Dahilinde Kapat
                                </button>
                                <button class="btn btn-sm btn-silence" onclick="silenceAlarm(${alarm.id})">
                                    <i class="fas fa-volume-mute"></i> Sesize Al
                                </button>
                                <button class="btn btn-sm btn-details" onclick="showAlarmDetails(${alarm.id})">
                                    <i class="fas fa-info-circle"></i> Detaylar
                                </button>
                            </div>
                        ` : `
                            <div class="alarm-actions">
                                <button class="btn btn-sm btn-details" onclick="showAlarmDetails(${alarm.id})">
                                    <i class="fas fa-info-circle"></i> Detaylar
                                </button>
                            </div>
                        `}
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        function acknowledgeAlarm(alarmId) {
            pendingAction = {
                type: 'acknowledge',
                alarmId: alarmId
            };
            
            document.getElementById('confirm-title').innerHTML = '<i class="fas fa-check-circle"></i> Alarmı Bilgi Dahilinde Kapat';
            document.getElementById('confirm-message').textContent = 'Bu alarmı bilgi dahilinde kapatmak istediğinizden emin misiniz?';
            document.getElementById('confirm-action-btn').className = 'btn btn-primary';
            document.getElementById('confirm-modal').classList.add('active');
        }
        
        function silenceAlarm(alarmId) {
            pendingAction = {
                type: 'silence',
                alarmId: alarmId
            };
            
            document.getElementById('silence-modal').classList.add('active');
        }
        
        async function confirmAction() {
            if (!pendingAction) return;
            
            if (pendingAction.type === 'acknowledge') {
                try {
                    const response = await fetch(`port_change_api.php?action=acknowledge_alarm&alarm_id=${pendingAction.alarmId}&ack_type=known_change`);
                    const data = await response.json();
                    
                    if (data.success) {
                        showToast('Alarm bilgi dahilinde kapatıldı', 'success');
                        loadAlarms();
                    } else {
                        showToast(data.error || 'Hata oluştu', 'error');
                    }
                } catch (error) {
                    console.error('Error acknowledging alarm:', error);
                    showToast('İşlem başarısız oldu', 'error');
                }
                
                closeConfirmModal();
            }
        }
        
        async function confirmSilence() {
            if (!pendingAction || pendingAction.type !== 'silence') return;
            
            const duration = document.getElementById('silence-duration').value;
            
            try {
                const response = await fetch(`port_change_api.php?action=silence_alarm&alarm_id=${pendingAction.alarmId}&duration=${duration}`);
                const data = await response.json();
                
                if (data.success) {
                    showToast(`Alarm ${duration} saat sesize alındı`, 'success');
                    loadAlarms();
                } else {
                    showToast(data.error || 'Hata oluştu', 'error');
                }
            } catch (error) {
                console.error('Error silencing alarm:', error);
                showToast('İşlem başarısız oldu', 'error');
            }
            
            closeSilenceModal();
        }
        
        function closeConfirmModal() {
            document.getElementById('confirm-modal').classList.remove('active');
            pendingAction = null;
        }
        
        function closeSilenceModal() {
            document.getElementById('silence-modal').classList.remove('active');
            pendingAction = null;
        }
        
        function closeDetailsModal() {
            document.getElementById('details-modal').classList.remove('active');
        }
        
        async function showAlarmDetails(alarmId) {
            document.getElementById('details-modal').classList.add('active');
            
            const modalBody = document.getElementById('details-modal-body');
            modalBody.innerHTML = `
                <div class="loading-state">
                    <i class="fas fa-spinner fa-spin"></i>
                    <p>Detaylar yükleniyor...</p>
                </div>
            `;
            
            try {
                const response = await fetch(`port_change_api.php?action=get_alarm_details&alarm_id=${alarmId}`);
                const data = await response.json();
                
                if (!data.success || !data.alarm) {
                    throw new Error(data.error || 'Alarm bulunamadı');
                }
                
                const alarm = data.alarm;
                const severityClass = alarm.severity.toLowerCase();
                
                modalBody.innerHTML = `
                    <div style="margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h4 style="margin: 0; color: #333;">
                                <i class="fas fa-network-wired"></i> ${escapeHtml(alarm.device_name)}
                                ${alarm.port_number ? ' - Port ' + alarm.port_number : ''}
                            </h4>
                            <span class="alarm-severity ${severityClass}">${alarm.severity}</span>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                            <strong>Mesaj:</strong><br>
                            ${escapeHtml(alarm.message)}
                        </div>
                        
                        ${alarm.old_value && alarm.new_value ? `
                            <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                                <strong>Değişiklik:</strong><br>
                                <div style="display: flex; align-items: center; gap: 10px; margin-top: 10px;">
                                    <span class="change-value change-old">${escapeHtml(alarm.old_value)}</span>
                                    <i class="fas fa-arrow-right"></i>
                                    <span class="change-value change-new">${escapeHtml(alarm.new_value)}</span>
                                </div>
                            </div>
                        ` : ''}
                        
                        ${alarm.mac_address ? `
                            <div style="margin-bottom: 10px;">
                                <strong>MAC Adresi:</strong> <code>${escapeHtml(alarm.mac_address)}</code>
                            </div>
                        ` : ''}
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                            <div>
                                <strong>İlk Gerçekleşme:</strong><br>
                                ${formatDate(alarm.first_occurrence)}
                            </div>
                            <div>
                                <strong>Son Gerçekleşme:</strong><br>
                                ${formatDate(alarm.last_occurrence)}
                            </div>
                            <div>
                                <strong>Tekrar Sayısı:</strong><br>
                                ${alarm.occurrence_count} kez
                            </div>
                            <div>
                                <strong>Durum:</strong><br>
                                ${alarm.status === 'active' ? '<span style="color: #f59e0b;">Aktif</span>' : 
                                  alarm.status === 'acknowledged' ? '<span style="color: #059669;">Bilgi Dahilinde</span>' : 
                                  '<span style="color: #6b7280;">Çözüldü</span>'}
                            </div>
                        </div>
                        
                        ${alarm.acknowledged_at ? `
                            <div style="background: rgba(5, 150, 105, 0.1); padding: 15px; border-radius: 8px; margin-top: 15px;">
                                <strong>Onay Bilgisi:</strong><br>
                                Onaylayan: ${escapeHtml(alarm.acknowledged_by || 'Bilinmiyor')}<br>
                                Onay Zamanı: ${formatDate(alarm.acknowledged_at)}
                                ${alarm.acknowledgment_type ? '<br>Tip: ' + escapeHtml(alarm.acknowledgment_type) : ''}
                            </div>
                        ` : ''}
                        
                        ${alarm.is_silenced == 1 && alarm.silence_until ? `
                            <div style="background: rgba(245, 158, 11, 0.1); padding: 15px; border-radius: 8px; margin-top: 15px;">
                                <strong><i class="fas fa-volume-mute"></i> Sesize Alındı</strong><br>
                                ${formatDate(alarm.silence_until)} tarihine kadar
                            </div>
                        ` : ''}
                    </div>
                `;
            } catch (error) {
                console.error('Error loading alarm details:', error);
                modalBody.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-exclamation-circle" style="color: #ef4444;"></i>
                        <h3>Hata</h3>
                        <p>${error.message}</p>
                    </div>
                `;
            }
        }
        
        function navigateToPort(deviceId, portNumber, deviceName, deviceIp) {
            // Redirect to main page and highlight the port
            const params = new URLSearchParams({
                device_id: deviceId,
                port_number: portNumber,
                device_name: deviceName,
                device_ip: deviceIp
            });
            window.location.href = `index.php?highlight_port=true&${params.toString()}`;
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('tr-TR', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }
        
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            const icon = type === 'success' ? 'check-circle' : 
                        type === 'error' ? 'exclamation-circle' : 
                        'info-circle';
            
            toast.innerHTML = `
                <i class="fas fa-${icon}"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
    </script>
</body>
</html>
