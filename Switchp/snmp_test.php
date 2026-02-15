<?php
/**
 * CBS350-24FP SNMP Test Aracı - ÇALIŞAN VERSİYON
 * SW35-BALO (EDGE-SW35) - Bağlantı başarılı, portlar gösterilecek
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('max_execution_time', 120);

ob_clean();
ob_start();

if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json; charset=utf-8');
}

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$snmpAvailable = extension_loaded('snmp');

// SWITCH BİLGİLERİ - SİZİN BAĞLANTINIZ ÇALIŞIYOR!
$switch_config = [
    'host' => '172.18.1.214',
    'username' => 'snmpuser',
    'engine_id' => '0102030405060708',
    'auth_protocol' => 'SHA',
    'priv_protocol' => 'AES',
    'auth_password' => 'AuthPass123', // SİZİN ÇALIŞAN ŞİFRENİZ
    'priv_password' => 'PrivPass123'  // SİZİN ÇALIŞAN ŞİFRENİZ
];

function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

// API İSTEKLERİNİ İŞLE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAjaxRequest()) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;
        
        $action = isset($input['action']) ? $input['action'] : '';
        
        if (isset($input['auth_password']) && !empty($input['auth_password'])) {
            $switch_config['auth_password'] = $input['auth_password'];
        }
        if (isset($input['priv_password']) && !empty($input['priv_password'])) {
            $switch_config['priv_password'] = $input['priv_password'];
        }
        
        ob_clean();
        
        switch ($action) {
            case 'get_ports':
                getSwitchPorts($switch_config);
                break;
            default:
                echo json_encode(['success' => false, 'error' => 'Geçersiz action']);
                exit;
        }
    } catch (Exception $e) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

/**
 * SWITCH PORLARINI GETİR - SADECE 1-28 ARASI FİZİKSEL PORLAR
 */
function getSwitchPorts($config) {
    $result = [
        'success' => false,
        'system' => [],
        'ports' => [],
        'stats' => [],
        'error' => null
    ];
    
    if (!extension_loaded('snmp')) {
        $result['error'] = 'SNMP extension yüklü değil';
        echo json_encode($result);
        exit;
    }
    
    try {
        // SNMP bağlantısı - AYNI BAŞARILI OLAN KONFİGÜRASYON
        $snmp = new SNMP(SNMP::VERSION_3, $config['host'], $config['username']);
        $snmp->setSecurity(
            'authPriv',
            $config['auth_protocol'],
            $config['auth_password'],
            $config['priv_protocol'],
            $config['priv_password'],
            '',
            $config['engine_id']
        );
        
        $snmp->valueretrieval = SNMP_VALUE_PLAIN;
        $snmp->quick_print = true;
        $snmp->timeout = 2000000;
        $snmp->retries = 1;
        
        // 1. SİSTEM BİLGİLERİNİ AL
        $result['system'] = [
            'name' => @$snmp->get('1.3.6.1.2.1.1.5.0'),
            'descr' => @$snmp->get('1.3.6.1.2.1.1.1.0'),
            'uptime' => @$snmp->get('1.3.6.1.2.1.1.3.0'),
            'location' => @$snmp->get('1.3.6.1.2.1.1.6.0'),
            'contact' => @$snmp->get('1.3.6.1.2.1.1.4.0')
        ];
        
        // 2. FİZİKSEL PORTLARI AL (SADECE 1-28)
        $active_count = 0;
        $down_count = 0;
        $admin_down_count = 0;
        $poe_active = 0;
        $sfp_active = 0;
        
        for ($i = 1; $i <= 28; $i++) {
            // Port bilgilerini al
            $port_name = @$snmp->get('1.3.6.1.2.1.2.2.1.2.' . $i);
            $port_status = @$snmp->get('1.3.6.1.2.1.2.2.1.8.' . $i);
            $port_admin = @$snmp->get('1.3.6.1.2.1.2.2.1.7.' . $i);
            $port_speed = @$snmp->get('1.3.6.1.2.1.2.2.1.5.' . $i);
            
            // Port tipi: 1-24 PoE, 25-28 SFP
            $port_type = ($i <= 24) ? 'PoE' : 'SFP';
            
            // Durum kodları
            $is_active = ($port_status == 1);
            $is_down = ($port_status == 2);
            $is_admin_down = ($port_admin == 2);
            
            if ($is_active) {
                $active_count++;
                if ($port_type == 'PoE') $poe_active++;
                else $sfp_active++;
            }
            if ($is_down) $down_count++;
            if ($is_admin_down) $admin_down_count++;
            
            // Port hızını formatla
            $speed_text = 'N/A';
            if ($port_speed && $port_speed > 0) {
                $speed = intval($port_speed);
                if ($speed >= 1000000000) $speed_text = round($speed/1000000000, 1) . ' Gbps';
                elseif ($speed >= 1000000) $speed_text = round($speed/1000000, 1) . ' Mbps';
                elseif ($speed >= 1000) $speed_text = round($speed/1000, 1) . ' Kbps';
                else $speed_text = $speed . ' bps';
            }
            
            $result['ports'][$i] = [
                'index' => $i,
                'name' => $port_name ? trim($port_name) : "GE$i",
                'type' => $port_type,
                'status' => intval($port_status),
                'status_text' => $port_status == 1 ? 'up' : ($port_status == 2 ? 'down' : 'unknown'),
                'admin' => intval($port_admin),
                'admin_text' => $port_admin == 1 ? 'up' : 'down',
                'speed' => $speed_text,
                'is_active' => $is_active
            ];
        }
        
        // 3. İSTATİSTİKLER
        $result['stats'] = [
            'total' => 28,
            'poe_total' => 24,
            'sfp_total' => 4,
            'active_total' => $active_count,
            'active_poe' => $poe_active,
            'active_sfp' => $sfp_active,
            'down_total' => $down_count,
            'admin_down' => $admin_down_count,
            'active_percentage' => round(($active_count / 28) * 100, 1),
            'active_list' => array_keys(array_filter($result['ports'], function($p) { 
                return $p['is_active']; 
            }))
        ];
        
        $result['success'] = true;
        
    } catch (Exception $e) {
        $result['error'] = 'SNMP Hatası: ' . $e->getMessage();
    }
    
    ob_clean();
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// HTML ÇIKTISI
if (!isAjaxRequest()) {
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CBS350-24FP SNMP - EDGE-SW35</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 20px;
            text-align: center;
        }
        
        .header h1 {
            color: #667eea;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .success-badge {
            background: #28a745;
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            display: inline-block;
            margin-top: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .port-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-top: 20px;
        }
        
        .port-card {
            background: white;
            padding: 12px;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .port-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            border-color: #667eea;
        }
        
        .port-card.up {
            background: #d4edda;
        }
        
        .port-card.down {
            background: #f8d7da;
        }
        
        .port-card.admin-down {
            background: #fff3cd;
        }
        
        .port-number {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .port-type {
            font-size: 11px;
            padding: 2px 6px;
            background: rgba(0,0,0,0.1);
            border-radius: 10px;
            display: inline-block;
            margin-top: 5px;
        }
        
        .status-icon {
            font-size: 20px;
            margin: 8px 0;
        }
        
        .system-info {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .info-item label {
            font-weight: bold;
            color: #555;
            font-size: 12px;
            display: block;
            margin-bottom: 5px;
        }
        
        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            margin: 5px;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        
        .active-ports {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-top: 20px;
        }
        
        .port-tag {
            display: inline-block;
            padding: 6px 15px;
            background: #28a745;
            color: white;
            border-radius: 20px;
            margin: 5px;
            font-size: 13px;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-network-wired"></i> EDGE-SW35 - CBS350-24FP</h1>
            <p>24 PoE + 4 SFP | Engine ID: 0102030405060708</p>
            <span class="success-badge"><i class="fas fa-check-circle"></i> SNMP Bağlantısı Aktif</span>
        </div>
        
        <div style="text-align: center; margin-bottom: 20px;">
            <button class="btn" onclick="loadSwitchData()">
                <i class="fas fa-sync-alt"></i> Port Durumlarını Yenile
            </button>
            <button class="btn" onclick="setPasswords()" style="background: #28a745;">
                <i class="fas fa-key"></i> Şifre Değiştir
            </button>
        </div>
        
        <!-- SİSTEM BİLGİLERİ -->
        <div id="systemInfo" class="system-info">
            <div style="text-align: center; grid-column: 1/-1;">
                <div class="spinner" style="width: 40px; height: 40px;"></div>
                <p>Sistem bilgileri yükleniyor...</p>
            </div>
        </div>
        
        <!-- İSTATİSTİKLER -->
        <div id="statsContainer"></div>
        
        <!-- PORT GRİD -->
        <div id="portContainer" style="background: white; padding: 25px; border-radius: 15px; margin-bottom: 20px;">
            <h2 style="margin-bottom: 20px;"><i class="fas fa-plug"></i> Fiziksel Port Durumları (1-28)</h2>
            <div style="display: flex; gap: 20px; margin-bottom: 15px; flex-wrap: wrap;">
                <div><span style="display: inline-block; width: 12px; height: 12px; background: #28a745; border-radius: 3px;"></span> Aktif</div>
                <div><span style="display: inline-block; width: 12px; height: 12px; background: #dc3545; border-radius: 3px;"></span> Pasif</div>
                <div><span style="display: inline-block; width: 12px; height: 12px; background: #ffc107; border-radius: 3px;"></span> Admin Kapalı</div>
                <div><span style="display: inline-block; width: 12px; height: 12px; background: #6c757d; border-radius: 3px;"></span> Bilinmiyor</div>
            </div>
            <div id="portGrid" class="port-grid">
                <!-- PORTLAR JAVASCRIPT İLE DOLDURULACAK -->
            </div>
        </div>
        
        <!-- AKTİF PORT LİSTESİ -->
        <div id="activePortsContainer" class="active-ports">
            <h2 style="margin-bottom: 15px;"><i class="fas fa-check-circle"></i> Aktif Portlar</h2>
            <div id="activePortsList">Yükleniyor...</div>
        </div>
    </div>
    
    <script>
        let authPassword = 'AuthPass123';
        let privPassword = 'PrivPass123';
        
        // ŞİFRE DEĞİŞTİR
        function setPasswords() {
            const auth = prompt('Authentication Password (Plaintext):', authPassword);
            if (auth) {
                authPassword = auth;
                const priv = prompt('Privacy Password (Plaintext):', privPassword);
                if (priv) {
                    privPassword = priv;
                    alert('Şifreler güncellendi!');
                    loadSwitchData();
                }
            }
        }
        
        // SWITCH VERİLERİNİ YÜKLE
        async function loadSwitchData() {
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        action: 'get_ports',
                        auth_password: authPassword,
                        priv_password: privPassword
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    displaySystemInfo(data.system);
                    displayStats(data.stats);
                    displayPorts(data.ports);
                    displayActivePorts(data.stats.active_list);
                } else {
                    alert('Hata: ' + (data.error || 'Bilinmeyen hata'));
                }
            } catch (error) {
                alert('Bağlantı hatası: ' + error.message);
            }
        }
        
        // SİSTEM BİLGİLERİNİ GÖSTER
        function displaySystemInfo(system) {
            const uptime = formatUptime(system.uptime);
            
            const html = `
                <div class="info-item">
                    <label><i class="fas fa-tag"></i> Switch Adı</label>
                    <div style="font-weight: bold;">${system.name || 'N/A'}</div>
                </div>
                <div class="info-item">
                    <label><i class="fas fa-info-circle"></i> Model</label>
                    <div>${system.descr ? system.descr.split(',')[0] : 'N/A'}</div>
                </div>
                <div class="info-item">
                    <label><i class="fas fa-clock"></i> Çalışma Süresi</label>
                    <div>${uptime}</div>
                </div>
                <div class="info-item">
                    <label><i class="fas fa-map-marker-alt"></i> Lokasyon</label>
                    <div>${system.location || 'Belirtilmemiş'}</div>
                </div>
            `;
            
            document.getElementById('systemInfo').innerHTML = html;
        }
        
        // İSTATİSTİKLERİ GÖSTER
        function displayStats(stats) {
            const html = `
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-plug" style="font-size: 24px;"></i>
                        <div class="stat-value">${stats.total}</div>
                        <div>Toplam Port</div>
                        <div style="font-size: 12px; margin-top: 5px;">PoE: ${stats.poe_total} | SFP: ${stats.sfp_total}</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #28a745, #20c997);">
                        <i class="fas fa-check-circle" style="font-size: 24px;"></i>
                        <div class="stat-value">${stats.active_total}</div>
                        <div>Aktif Port</div>
                        <div style="font-size: 12px; margin-top: 5px;">PoE: ${stats.active_poe} | SFP: ${stats.active_sfp}</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #dc3545, #c82333);">
                        <i class="fas fa-times-circle" style="font-size: 24px;"></i>
                        <div class="stat-value">${stats.down_total}</div>
                        <div>Pasif Port</div>
                    </div>
                    <div class="stat-card" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                        <i class="fas fa-percentage" style="font-size: 24px;"></i>
                        <div class="stat-value">${stats.active_percentage}%</div>
                        <div>Kullanım</div>
                    </div>
                </div>
            `;
            
            document.getElementById('statsContainer').innerHTML = html;
        }
        
        // PORTLARI GÖSTER
        function displayPorts(ports) {
            let html = '';
            
            for (let i = 1; i <= 28; i++) {
                const port = ports[i];
                if (!port) continue;
                
                let statusClass = '';
                if (port.status === 1) statusClass = 'up';
                else if (port.status === 2) statusClass = 'down';
                else if (port.admin === 2) statusClass = 'admin-down';
                
                let statusIcon = '';
                if (port.status === 1) statusIcon = 'fa-check-circle';
                else if (port.status === 2) statusIcon = 'fa-times-circle';
                else statusIcon = 'fa-question-circle';
                
                let statusColor = '';
                if (port.status === 1) statusColor = '#28a745';
                else if (port.status === 2) statusColor = '#dc3545';
                else statusColor = '#ffc107';
                
                html += `
                    <div class="port-card ${statusClass}">
                        <div class="port-number">GE${i}</div>
                        <div class="status-icon">
                            <i class="fas ${statusIcon}" style="color: ${statusColor};"></i>
                        </div>
                        <div class="port-type">${port.type}</div>
                        <div style="font-size: 11px; color: #666; margin-top: 5px;">${port.speed}</div>
                    </div>
                `;
            }
            
            document.getElementById('portGrid').innerHTML = html;
        }
        
        // AKTİF PORTLARI GÖSTER
        function displayActivePorts(activeList) {
            if (!activeList || activeList.length === 0) {
                document.getElementById('activePortsList').innerHTML = '<p>Aktif port bulunmuyor.</p>';
                return;
            }
            
            let html = '<div style="display: flex; flex-wrap: wrap;">';
            
            activeList.sort((a, b) => a - b).forEach(port => {
                const type = port <= 24 ? 'PoE' : 'SFP';
                html += `<span class="port-tag"><i class="fas fa-plug"></i> GE${port} (${type})</span>`;
            });
            
            html += '</div>';
            document.getElementById('activePortsList').innerHTML = html;
        }
        
        // UPTIME FORMATLA
        function formatUptime(timeticks) {
            if (!timeticks) return 'N/A';
            const seconds = Math.floor(parseInt(timeticks) / 100);
            const days = Math.floor(seconds / 86400);
            const hours = Math.floor((seconds % 86400) / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            return `${days}g ${hours}s ${minutes}d`;
        }
        
        // SAYFA YÜKLENDİĞİNDE VERİLERİ GETİR
        document.addEventListener('DOMContentLoaded', loadSwitchData);
    </script>
</body>
</html>
<?php
}
?>