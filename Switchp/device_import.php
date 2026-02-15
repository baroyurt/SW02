<?php
// Require authentication
require_once 'db.php';
require_once 'auth.php';

$auth = new Auth($conn);
$auth->requireLogin();

$currentUser = $auth->getUser();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Import - MAC Address Registry</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --secondary: #8b5cf6;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #0f172a;
            --dark-light: #1e293b;
            --text: #e2e8f0;
            --text-light: #94a3b8;
            --border: #334155;
        }
        
        body {
            background: linear-gradient(135deg, var(--dark) 0%, var(--dark-light) 100%);
            color: var(--text);
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: var(--dark-light);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            margin-bottom: 20px;
            border: 1px solid var(--border);
        }
        
        .header h1 {
            color: var(--primary);
            font-size: 28px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header p {
            color: var(--text-light);
            line-height: 1.6;
        }
        
        .main-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 1024px) {
            .main-content {
                grid-template-columns: 1fr;
            }
        }
        
        .card {
            background: var(--dark-light);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            border: 1px solid var(--border);
        }
        
        .card h2 {
            color: var(--primary);
            margin-bottom: 20px;
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .upload-area {
            border: 3px dashed var(--primary);
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
            background: rgba(59, 130, 246, 0.05);
        }
        
        .upload-area:hover, .upload-area.drag-over {
            background: rgba(59, 130, 246, 0.1);
            border-color: var(--secondary);
        }
        
        .upload-area i {
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .upload-area p {
            color: var(--text);
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .upload-area .file-types {
            color: var(--text-light);
            font-size: 14px;
        }
        
        #file-input {
            display: none;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.4);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        .progress-bar {
            width: 100%;
            height: 30px;
            background: var(--dark);
            border-radius: 15px;
            overflow: hidden;
            margin-top: 20px;
            display: none;
            border: 1px solid var(--border);
        }
        
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);
            transition: width 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .result-message {
            margin-top: 20px;
            padding: 15px;
            border-radius: 8px;
            display: none;
            border: 1px solid;
        }
        
        .result-message.success {
            background: rgba(16, 185, 129, 0.1);
            border-color: var(--success);
            color: var(--success);
        }
        
        .result-message.error {
            background: rgba(239, 68, 68, 0.1);
            border-color: var(--danger);
            color: var(--danger);
        }
        
        .instructions {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid var(--warning);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .instructions h3 {
            color: var(--warning);
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .instructions ol {
            margin-left: 20px;
            color: var(--text-light);
        }
        
        .instructions li {
            margin-bottom: 8px;
        }
        
        .template-download {
            margin-top: 20px;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text);
            font-weight: 600;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--dark);
            color: var(--text);
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .form-group input::placeholder {
            color: var(--text-light);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        th {
            background: var(--dark);
            color: var(--primary);
            font-weight: 600;
        }
        
        tr:hover {
            background: rgba(59, 130, 246, 0.05);
        }
        
        .stat-box {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .stat-box .value {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-box .label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .error-list {
            max-height: 300px;
            overflow-y: auto;
            margin-top: 15px;
        }
        
        .error-item {
            padding: 8px;
            background: rgba(239, 68, 68, 0.1);
            border-left: 3px solid var(--danger);
            margin-bottom: 5px;
            border-radius: 3px;
            color: var(--danger);
        }
        
        code {
            background: var(--dark);
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-file-import"></i>
                Device Import - MAC Address Registry
            </h1>
            <p>Upload Excel file or manually add devices with IP address, hostname, and MAC address information.</p>
        </div>
        
        <div class="main-content">
            <!-- Excel Upload Section -->
            <div class="card">
                <h2><i class="fas fa-upload"></i> Excel Import</h2>
                
                <div class="instructions">
                    <h3><i class="fas fa-info-circle"></i> Excel Format:</h3>
                    <ol>
                        <li>Column 1: <strong>IP Adresi</strong> (IP Address)</li>
                        <li>Column 2: <strong>Hostname</strong></li>
                        <li>Column 3: <strong>MAC Adresi</strong> (MAC Address)</li>
                    </ol>
                    <p style="margin-top: 10px;">Example: <code>192.0.2.10 | TEST-PC-01 | 00:11:22:33:44:55</code></p>
                </div>
                
                <div class="template-download">
                    <button class="btn btn-primary" onclick="downloadTemplate()">
                        <i class="fas fa-download"></i> Download Excel Template
                    </button>
                </div>
                
                <div class="upload-area" id="upload-area">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p><strong>Drag and drop your Excel file here</strong></p>
                    <p>or click to browse</p>
                    <p class="file-types">Supported: .xlsx, .xls, .csv</p>
                    <input type="file" id="file-input" accept=".xlsx,.xls,.csv">
                </div>
                
                <div class="progress-bar" id="progress-bar">
                    <div class="progress-bar-fill" id="progress-fill">0%</div>
                </div>
                
                <div class="result-message" id="result-message"></div>
            </div>
            
            <!-- Manual Entry Section -->
            <div class="card">
                <h2><i class="fas fa-edit"></i> Manual Entry</h2>
                
                <form id="manual-form">
                    <div class="form-group">
                        <label for="ip-address">
                            <i class="fas fa-network-wired"></i> IP Address
                        </label>
                        <input type="text" id="ip-address" placeholder="e.g., 192.0.2.10" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="hostname">
                            <i class="fas fa-server"></i> Hostname
                        </label>
                        <input type="text" id="hostname" placeholder="e.g., TEST-PC-01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="mac-address">
                            <i class="fas fa-ethernet"></i> MAC Address
                        </label>
                        <input type="text" id="mac-address" placeholder="e.g., 00:11:22:33:44:55" required>
                    </div>
                    
                    <button type="submit" class="btn btn-success" style="width: 100%;">
                        <i class="fas fa-plus-circle"></i> Add Device
                    </button>
                </form>
                
                <div class="result-message" id="manual-result"></div>
                
                <div style="margin-top: 30px;">
                    <h3 style="color: var(--primary); margin-bottom: 15px;">
                        <i class="fas fa-info-circle"></i> Format Guidelines
                    </h3>
                    <ul style="color: var(--text-light); line-height: 2;">
                        <li>IP: IPv4 format (e.g., 192.168.1.1)</li>
                        <li>Hostname: Any alphanumeric + dash/underscore</li>
                        <li>MAC: Any format (will be normalized)</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Device List -->
        <div class="card">
            <h2><i class="fas fa-list"></i> Recent Devices</h2>
            <div id="device-list">
                <p style="text-align: center; color: var(--text-light);">Loading devices...</p>
            </div>
        </div>
    </div>
    
    <script>
        const uploadArea = document.getElementById('upload-area');
        const fileInput = document.getElementById('file-input');
        const progressBar = document.getElementById('progress-bar');
        const progressFill = document.getElementById('progress-fill');
        const resultMessage = document.getElementById('result-message');
        const manualForm = document.getElementById('manual-form');
        const manualResult = document.getElementById('manual-result');
        
        // Drag and drop handlers
        uploadArea.addEventListener('click', () => fileInput.click());
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('drag-over');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('drag-over');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('drag-over');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFile(files[0]);
            }
        });
        
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFile(e.target.files[0]);
            }
        });
        
        function handleFile(file) {
            const allowedTypes = ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'];
            if (!allowedTypes.includes(file.type) && !file.name.match(/\.(xlsx|xls|csv)$/i)) {
                showResult('error', 'Invalid file type. Please upload an Excel or CSV file.');
                return;
            }
            
            uploadFile(file);
        }
        
        function uploadFile(file) {
            const formData = new FormData();
            formData.append('excel_file', file);
            
            progressBar.style.display = 'block';
            resultMessage.style.display = 'none';
            
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    progressFill.style.width = percentComplete + '%';
                    progressFill.textContent = Math.round(percentComplete) + '%';
                }
            });
            
            xhr.addEventListener('load', () => {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            let message = `Import completed successfully!<br>
                                Total Rows: ${response.total_rows}<br>
                                Success: ${response.success_count}<br>
                                Errors: ${response.error_count}`;
                            
                            showResult('success', message);
                            
                            if (response.errors && response.errors.length > 0) {
                                let errorHtml = '<div class="error-list"><h4>Errors:</h4>';
                                response.errors.forEach(err => {
                                    errorHtml += `<div class="error-item">${err}</div>`;
                                });
                                errorHtml += '</div>';
                                resultMessage.innerHTML += errorHtml;
                            }
                            
                            loadDevices();
                        } else {
                            showResult('error', response.error || 'Import failed');
                        }
                    } catch (e) {
                        showResult('error', 'Failed to parse response');
                    }
                } else {
                    showResult('error', 'Upload failed with status ' + xhr.status);
                }
                progressBar.style.display = 'none';
            });
            
            xhr.addEventListener('error', () => {
                showResult('error', 'Upload failed. Please check your connection.');
                progressBar.style.display = 'none';
            });
            
            xhr.open('POST', 'device_import_api.php');
            xhr.send(formData);
        }
        
        function showResult(type, message) {
            resultMessage.className = 'result-message ' + type;
            resultMessage.innerHTML = message;
            resultMessage.style.display = 'block';
        }
        
        function showManualResult(type, message) {
            manualResult.className = 'result-message ' + type;
            manualResult.innerHTML = message;
            manualResult.style.display = 'block';
            
            setTimeout(() => {
                manualResult.style.display = 'none';
            }, 5000);
        }
        
        // Manual form submission
        manualForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const ipAddress = document.getElementById('ip-address').value.trim();
            const hostname = document.getElementById('hostname').value.trim();
            const macAddress = document.getElementById('mac-address').value.trim();
            
            // Client-side validation with proper IP octet range check
            const ipPattern = /^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;
            if (!ipPattern.test(ipAddress)) {
                showManualResult('error', 'Invalid IP address format (each octet must be 0-255)');
                return;
            }
            
            if (!hostname) {
                showManualResult('error', 'Hostname is required');
                return;
            }
            
            if (!macAddress) {
                showManualResult('error', 'MAC address is required');
                return;
            }
            
            try {
                const response = await fetch('device_import_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'manual_add',
                        ip_address: ipAddress,
                        hostname: hostname,
                        mac_address: macAddress
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showManualResult('success', 'Device added successfully!');
                    manualForm.reset();
                    loadDevices();
                } else {
                    const errors = data.errors ? data.errors.join('<br>') : data.error;
                    showManualResult('error', errors);
                }
            } catch (error) {
                showManualResult('error', 'Error: ' + error.message);
            }
        });
        
        function loadDevices() {
            fetch('device_import_api.php?action=list&per_page=10')
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.devices) {
                        let html = `<table>
                            <tr>
                                <th>MAC Address</th>
                                <th>IP Address</th>
                                <th>Hostname</th>
                                <th>Source</th>
                                <th>Updated</th>
                            </tr>`;
                        
                        data.devices.forEach(device => {
                            html += `<tr>
                                <td><code>${device.mac_address}</code></td>
                                <td>${device.ip_address || '-'}</td>
                                <td><strong>${device.device_name}</strong></td>
                                <td><span style="color: var(--${device.source === 'manual' ? 'success' : 'primary'});">${device.source}</span></td>
                                <td style="color: var(--text-light);">${new Date(device.updated_at).toLocaleString()}</td>
                            </tr>`;
                        });
                        
                        html += '</table>';
                        
                        if (data.total > 10) {
                            html += `<p style="text-align: center; margin-top: 20px; color: var(--text-light);">
                                Showing 10 of ${data.total} devices
                            </p>`;
                        }
                        
                        document.getElementById('device-list').innerHTML = html;
                    }
                })
                .catch(error => {
                    document.getElementById('device-list').innerHTML = 
                        '<p style="color: var(--danger);">Error loading devices</p>';
                });
        }
        
        function downloadTemplate() {
            // Create a simple CSV template with RFC 5737 test addresses
            const csvContent = "IP Adresi,Hostname,MAC Adresi\n192.0.2.10,TEST-PC-01,00:11:22:33:44:55\n192.0.2.20,TEST-SW-02,AA:BB:CC:DD:EE:FF\n";
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            const url = URL.createObjectURL(blob);
            link.setAttribute("href", url);
            link.setAttribute("download", "device_import_template.csv");
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Load devices on page load
        document.addEventListener('DOMContentLoaded', () => {
            loadDevices();
        });
    </script>
</body>
</html>
