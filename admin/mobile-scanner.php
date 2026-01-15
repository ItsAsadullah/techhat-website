<?php
/**
 * ================================================================
 * TechHat Shop - Mobile Scanner Page
 * Opens on mobile device to scan barcodes and send to PC
 * ================================================================
 */

// Get session ID from URL
$sessionId = isset($_GET['session']) ? htmlspecialchars($_GET['session']) : '';

if (empty($sessionId)) {
    die('Invalid session. Please scan the QR code again.');
}

// Determine base URL for API endpoint
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>TechHat Mobile Scanner</title>
    
    <!-- Prevent zoom on iOS -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    
    <!-- html5-qrcode Library -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    
    <style>
        :root {
            --primary: #4f46e5;
            --primary-dark: #4338ca;
            --success: #10b981;
            --danger: #ef4444;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-500: #6b7280;
            --gray-700: #374151;
            --gray-900: #111827;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--gray-900) 0%, #1e1b4b 100%);
            min-height: 100vh;
            color: white;
            overflow-x: hidden;
        }
        
        .container {
            padding: 1rem;
            max-width: 500px;
            margin: 0 auto;
        }
        
        /* Header */
        .header {
            text-align: center;
            padding: 1.5rem 0;
        }
        
        .header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .header h1 i {
            font-size: 1.75rem;
            color: var(--primary);
        }
        
        .header p {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.875rem;
        }
        
        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 9999px;
            font-size: 0.875rem;
            margin-top: 1rem;
        }
        
        .status-badge.connected {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
        }
        
        .status-badge .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--gray-500);
        }
        
        .status-badge.connected .dot {
            background: var(--success);
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        /* Scanner Container */
        .scanner-container {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 1rem;
            overflow: hidden;
            margin: 1.5rem 0;
            border: 2px solid rgba(255, 255, 255, 0.1);
        }
        
        #reader {
            width: 100%;
            border-radius: 0.75rem;
            overflow: hidden;
        }
        
        #reader video {
            width: 100% !important;
            border-radius: 0.75rem;
        }
        
        /* Scanner region styling override */
        #reader__scan_region {
            background: transparent !important;
        }
        
        #reader__scan_region > img {
            display: none !important;
        }
        
        #reader__dashboard_section {
            padding: 0.5rem !important;
        }
        
        #reader__dashboard_section button {
            background: var(--primary) !important;
            border: none !important;
            padding: 0.75rem 1.5rem !important;
            border-radius: 0.5rem !important;
            color: white !important;
            font-weight: 500 !important;
            cursor: pointer !important;
        }
        
        #reader__dashboard_section select {
            padding: 0.5rem !important;
            border-radius: 0.375rem !important;
            border: 1px solid var(--gray-200) !important;
            margin: 0.25rem !important;
        }
        
        /* Scan Result */
        .scan-result {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .scan-result h3 {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .last-scan {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            border-radius: 0.75rem;
        }
        
        .last-scan.error {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.3);
        }
        
        .last-scan .icon {
            width: 3rem;
            height: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--success);
            border-radius: 50%;
            font-size: 1.25rem;
        }
        
        .last-scan.error .icon {
            background: var(--danger);
        }
        
        .last-scan .details {
            flex: 1;
        }
        
        .last-scan .code {
            font-family: 'SF Mono', 'Fira Code', monospace;
            font-size: 1rem;
            font-weight: 600;
            word-break: break-all;
        }
        
        .last-scan .time {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.5);
            margin-top: 0.25rem;
        }
        
        .scan-placeholder {
            text-align: center;
            padding: 2rem;
            color: rgba(255, 255, 255, 0.4);
        }
        
        .scan-placeholder i {
            font-size: 2.5rem;
            margin-bottom: 0.75rem;
            display: block;
        }
        
        /* Scan History */
        .scan-history {
            margin-top: 1rem;
        }
        
        .scan-history h4 {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 0.75rem;
        }
        
        .history-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            max-height: 150px;
            overflow-y: auto;
        }
        
        .history-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }
        
        .history-item .code {
            font-family: monospace;
            font-weight: 500;
        }
        
        .history-item .time {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.4);
        }
        
        /* Control Buttons */
        .controls {
            display: flex;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }
        
        .btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 1rem;
            border: none;
            border-radius: 0.75rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        /* Manual Input */
        .manual-input {
            margin-top: 1.5rem;
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 1rem;
        }
        
        .manual-input h4 {
            font-size: 0.875rem;
            margin-bottom: 1rem;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .input-group {
            display: flex;
            gap: 0.5rem;
        }
        
        .input-group input {
            flex: 1;
            padding: 0.875rem 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 0.5rem;
            color: white;
            font-size: 1rem;
        }
        
        .input-group input::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }
        
        .input-group input:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .input-group button {
            padding: 0 1.25rem;
            background: var(--primary);
            border: none;
            border-radius: 0.5rem;
            color: white;
            font-size: 1.25rem;
            cursor: pointer;
        }
        
        /* Toast Notification */
        .toast {
            position: fixed;
            top: 1rem;
            left: 50%;
            transform: translateX(-50%);
            padding: 1rem 1.5rem;
            background: var(--success);
            color: white;
            border-radius: 0.75rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            display: none;
            z-index: 1000;
            animation: slideDown 0.3s ease;
        }
        
        .toast.error {
            background: var(--danger);
        }
        
        .toast.show {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translate(-50%, -100%); }
            to { opacity: 1; transform: translate(-50%, 0); }
        }
        
        /* Footer */
        .footer {
            text-align: center;
            padding: 2rem 0;
            color: rgba(255, 255, 255, 0.3);
            font-size: 0.75rem;
        }
        
        /* Vibration for feedback */
        .vibrate {
            animation: vibrate 0.1s linear;
        }
        
        @keyframes vibrate {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-2px); }
            75% { transform: translateX(2px); }
        }
        
        /* Icons - Using Unicode for simplicity */
        .icon-scan::before { content: "📷"; }
        .icon-check::before { content: "✓"; }
        .icon-error::before { content: "✕"; }
        .icon-history::before { content: "📋"; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <h1>
                <span>📱</span>
                TechHat Scanner
            </h1>
            <p>Scan barcodes and send to your PC instantly</p>
            
            <div class="status-badge" id="statusBadge">
                <span class="dot"></span>
                <span id="statusText">Connecting...</span>
            </div>
        </header>
        
        <!-- Scanner Container -->
        <div class="scanner-container">
            <div id="reader"></div>
        </div>
        
        <!-- Scan Result -->
        <div class="scan-result">
            <h3>📤 Last Scanned</h3>
            <div id="lastScanDisplay">
                <div class="scan-placeholder">
                    <span>🎯</span>
                    <p>Point camera at a barcode to scan</p>
                </div>
            </div>
            
            <!-- Scan History -->
            <div class="scan-history" id="scanHistory" style="display: none;">
                <h4>📋 Recent Scans</h4>
                <div class="history-list" id="historyList"></div>
            </div>
        </div>
        
        <!-- Manual Input -->
        <div class="manual-input">
            <h4>⌨️ Or enter code manually</h4>
            <div class="input-group">
                <input type="text" id="manualInput" placeholder="Enter barcode/serial...">
                <button onclick="sendManualCode()">➤</button>
            </div>
        </div>
        
        <!-- Controls -->
        <div class="controls">
            <button class="btn btn-secondary" onclick="toggleTorch()">
                💡 Torch
            </button>
            <button class="btn btn-secondary" onclick="switchCamera()">
                🔄 Camera
            </button>
        </div>
        
        <!-- Footer -->
        <footer class="footer">
            Session: <?= htmlspecialchars($sessionId) ?><br>
            TechHat Shop © 2024
        </footer>
    </div>
    
    <!-- Toast -->
    <div class="toast" id="toast">
        <span id="toastIcon">✓</span>
        <span id="toastText">Sent successfully!</span>
    </div>
    
    <!-- Audio Beep -->
    <audio id="beepSound" preload="auto">
        <source src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2teleRQOSoXM66lbMC5NlfzqpmNANVJyxfZ6TjBEf7j+mkFBbJG86mtLT3mwzn5ZWXefvcWJZ2J6n6y9e2dnenR0bHZ7eXt9gICDg4WFh4iIiYqKioqKiYmIh4aEg4GAfXt5d3VzcnBvbm5ub3Byc3V4ent/gYSHiYuNj5GSlJWWl5iYmJiXlpWTkY+Mi4iFg4B+e3l3dXRycXBwcHFydHZ4e36BhIeKjI+RlJaYmpudnp6enp2cmpmWlJKPjImGg4B9ent4dnRzcnJycnN0dnh7foGEh4qNkJOWmJudnp+goKCfn56cmpiVkpCNioeEgX57eXd1c3JxcXFxcnR2eHt+gYSHio2QkpaYmp2en6ChoaGhoKCenJqYlZKPjImGg4B9e3h2dHNycXBxcXJ0dnh7foGEh4qNkJOWmJqdnp+goaGhoaCfnZuZl5SRjouIhYJ/fHp3dXNycXBwcHFzdHd5fH+ChYiLjo+RlJeZm52en6ChoqKioaCfnZuZl5SRjo2KiIWDgH58enh3dXRzcnNzdHZ4eXx/goWIi46QkpSXmZucnZ6foKChoaCfnp2bmJaTkY6LiIaEgX98e3l3dXRzcnJyc3R2eHp9f4KEh4qMjpGTlpiZm5ydnp6fn5+enZyamJaTkI6LiIaEgX98e3l3dXRzcnJyc3R2eHp9f4KEh4qMjpGTlpiZm5ydnp6fn5+enZyamJaTkI6LiIaEgX98e3l3dXRzcnJyc3R2eHp9gIKFiIqNj5GTlZeYmpucnZ2dnp6dnJuZl5WSj42KiIWDgX58e3l3dnVzcnFxcnN0dnh6fH+BhIaJi42QkpSWl5manJ2dnZ6dnJuamJaSj42KiIWDgH58e3l3dXRzcnFxcnN0dnh6fX+ChYeJjI6QkpSWmJqbnJycnZ2cnJqZl5WTkI6LiYaEgX98e3l3dXRzcnFxcXJzdXd5fH6BhIaJi42PkZOVl5ibo=" type="audio/wav">
    </audio>
    
    <script>
    // Configuration
    const CONFIG = {
        sessionId: '<?= htmlspecialchars($sessionId) ?>',
        apiUrl: '<?= rtrim($baseUrl, '/') ?>/api/scan_endpoints.php',
        debounceTime: 1500 // Prevent duplicate scans
    };
    
    // State
    let html5QrCode = null;
    let lastScannedCode = '';
    let lastScanTime = 0;
    let scanHistory = [];
    let torchOn = false;
    let currentCamera = 'environment'; // 'environment' = back, 'user' = front
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', () => {
        initScanner();
        checkConnection();
    });
    
    /**
     * Initialize QR/Barcode Scanner
     */
    function initScanner() {
        html5QrCode = new Html5Qrcode("reader");
        
        const config = {
            fps: 10,
            qrbox: { width: 250, height: 150 },
            aspectRatio: 1.0,
            formatsToSupport: [
                Html5QrcodeSupportedFormats.QR_CODE,
                Html5QrcodeSupportedFormats.CODE_128,
                Html5QrcodeSupportedFormats.CODE_39,
                Html5QrcodeSupportedFormats.EAN_13,
                Html5QrcodeSupportedFormats.EAN_8,
                Html5QrcodeSupportedFormats.UPC_A,
                Html5QrcodeSupportedFormats.UPC_E,
                Html5QrcodeSupportedFormats.ITF,
                Html5QrcodeSupportedFormats.CODABAR
            ]
        };
        
        html5QrCode.start(
            { facingMode: currentCamera },
            config,
            onScanSuccess,
            onScanError
        ).then(() => {
            updateStatus(true);
        }).catch(err => {
            console.error('Scanner init error:', err);
            showToast('Camera permission denied', true);
        });
    }
    
    /**
     * Handle successful scan
     */
    function onScanSuccess(decodedText, decodedResult) {
        const now = Date.now();
        
        // Debounce: prevent rapid duplicate scans
        if (decodedText === lastScannedCode && (now - lastScanTime) < CONFIG.debounceTime) {
            return;
        }
        
        lastScannedCode = decodedText;
        lastScanTime = now;
        
        // Vibrate if supported
        if (navigator.vibrate) {
            navigator.vibrate(100);
        }
        
        // Play beep
        playBeep();
        
        // Send to server
        sendCodeToServer(decodedText);
    }
    
    /**
     * Handle scan errors (usually just "no code found" - ignore)
     */
    function onScanError(errorMessage) {
        // Ignore - this fires constantly when no code is visible
    }
    
    /**
     * Send scanned code to server
     */
    async function sendCodeToServer(code) {
        try {
            const response = await fetch(CONFIG.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'push',
                    session_id: CONFIG.sessionId,
                    code: code,
                    device_info: navigator.userAgent
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                updateLastScan(code, true);
                addToHistory(code);
                showToast('✓ Sent: ' + code.substring(0, 20) + (code.length > 20 ? '...' : ''));
            } else {
                updateLastScan(code, false);
                showToast('Failed to send', true);
            }
        } catch (error) {
            console.error('Send error:', error);
            updateLastScan(code, false);
            showToast('Network error', true);
        }
    }
    
    /**
     * Update last scan display
     */
    function updateLastScan(code, success) {
        const container = document.getElementById('lastScanDisplay');
        const time = new Date().toLocaleTimeString();
        
        container.innerHTML = `
            <div class="last-scan ${success ? '' : 'error'}">
                <div class="icon">${success ? '✓' : '✕'}</div>
                <div class="details">
                    <div class="code">${escapeHtml(code)}</div>
                    <div class="time">${time} - ${success ? 'Sent successfully' : 'Failed to send'}</div>
                </div>
            </div>
        `;
    }
    
    /**
     * Add to scan history
     */
    function addToHistory(code) {
        const time = new Date().toLocaleTimeString();
        scanHistory.unshift({ code, time });
        
        // Keep only last 10
        if (scanHistory.length > 10) {
            scanHistory.pop();
        }
        
        updateHistoryDisplay();
    }
    
    function updateHistoryDisplay() {
        const historySection = document.getElementById('scanHistory');
        const historyList = document.getElementById('historyList');
        
        if (scanHistory.length > 0) {
            historySection.style.display = 'block';
            historyList.innerHTML = scanHistory.map(item => `
                <div class="history-item">
                    <span class="code">${escapeHtml(item.code.substring(0, 25))}${item.code.length > 25 ? '...' : ''}</span>
                    <span class="time">${item.time}</span>
                </div>
            `).join('');
        }
    }
    
    /**
     * Manual code input
     */
    function sendManualCode() {
        const input = document.getElementById('manualInput');
        const code = input.value.trim();
        
        if (code) {
            sendCodeToServer(code);
            input.value = '';
        }
    }
    
    // Enter key support for manual input
    document.getElementById('manualInput').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            sendManualCode();
        }
    });
    
    /**
     * Toggle Torch/Flashlight
     */
    async function toggleTorch() {
        try {
            const track = html5QrCode.getRunningTrackCameraCapabilities();
            if (track && track.torchFeature && track.torchFeature().isSupported()) {
                torchOn = !torchOn;
                await track.torchFeature().apply(torchOn);
                showToast(torchOn ? '💡 Torch ON' : '🔦 Torch OFF');
            } else {
                showToast('Torch not supported', true);
            }
        } catch (err) {
            showToast('Torch error', true);
        }
    }
    
    /**
     * Switch between front and back camera
     */
    async function switchCamera() {
        try {
            await html5QrCode.stop();
            currentCamera = currentCamera === 'environment' ? 'user' : 'environment';
            
            html5QrCode.start(
                { facingMode: currentCamera },
                {
                    fps: 10,
                    qrbox: { width: 250, height: 150 }
                },
                onScanSuccess,
                onScanError
            );
            
            showToast(currentCamera === 'environment' ? '📷 Back Camera' : '🤳 Front Camera');
        } catch (err) {
            showToast('Camera switch failed', true);
        }
    }
    
    /**
     * Check connection to PC
     */
    async function checkConnection() {
        try {
            const response = await fetch(CONFIG.apiUrl + '?action=ping&session=' + CONFIG.sessionId);
            const data = await response.json();
            
            if (data.status === 'ok') {
                updateStatus(true);
            }
        } catch (error) {
            updateStatus(false);
        }
    }
    
    /**
     * Update connection status display
     */
    function updateStatus(connected) {
        const badge = document.getElementById('statusBadge');
        const text = document.getElementById('statusText');
        
        if (connected) {
            badge.classList.add('connected');
            text.textContent = 'Connected';
        } else {
            badge.classList.remove('connected');
            text.textContent = 'Disconnected';
        }
    }
    
    /**
     * Play beep sound
     */
    function playBeep() {
        const beep = document.getElementById('beepSound');
        if (beep) {
            beep.currentTime = 0;
            beep.play().catch(() => {});
        }
    }
    
    /**
     * Show toast notification
     */
    function showToast(message, isError = false) {
        const toast = document.getElementById('toast');
        const icon = document.getElementById('toastIcon');
        const text = document.getElementById('toastText');
        
        icon.textContent = isError ? '✕' : '✓';
        text.textContent = message;
        toast.classList.remove('error');
        if (isError) toast.classList.add('error');
        
        toast.classList.add('show');
        
        setTimeout(() => {
            toast.classList.remove('show');
        }, 2500);
    }
    
    /**
     * Escape HTML for safe display
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    </script>
</body>
</html>
