<?php
/**
 * ================================================================
 * TechHat Shop - Scan Endpoints API
 * Handles communication between Mobile Scanner and PC
 * ================================================================
 * 
 * Endpoints:
 * - POST action=register : Register a new scan session
 * - POST action=push     : Mobile sends scanned code
 * - GET  action=check    : PC polls for new codes
 * - GET  action=ping     : Check if session is alive
 */

// CORS Headers for mobile access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database connection
require_once '../../core/db.php';

// Get action from request
$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
} else {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Also check POST for form data
    if (empty($data)) {
        $data = $_POST;
    }
    
    $action = isset($data['action']) ? $data['action'] : '';
}

// Route to appropriate handler
try {
    switch ($action) {
        case 'register':
            handleRegister($pdo, $data);
            break;
            
        case 'push':
            handlePush($pdo, $data);
            break;
            
        case 'check':
            handleCheck($pdo);
            break;
            
        case 'ping':
            handlePing($pdo);
            break;
            
        case 'cleanup':
            handleCleanup($pdo);
            break;
            
        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
} catch (PDOException $e) {
    error_log('Scan API Error: ' . $e->getMessage());
    jsonResponse(['error' => 'Database error', 'success' => false], 500);
} catch (Exception $e) {
    error_log('Scan API Error: ' . $e->getMessage());
    jsonResponse(['error' => $e->getMessage(), 'success' => false], 500);
}

/**
 * Register a new scan session
 * Called when PC clicks "Connect Scanner"
 */
function handleRegister($pdo, $data) {
    $sessionId = isset($data['session_id']) ? trim($data['session_id']) : '';
    
    if (empty($sessionId)) {
        jsonResponse(['error' => 'Session ID required', 'success' => false], 400);
    }
    
    // Validate session ID format (prevent injection)
    if (!preg_match('/^[a-zA-Z0-9_-]{10,64}$/', $sessionId)) {
        jsonResponse(['error' => 'Invalid session ID format', 'success' => false], 400);
    }
    
    // Get current user ID if logged in (optional)
    $userId = null;
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    // Set expiry time (1 hour from now)
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Insert or update session registry
    $stmt = $pdo->prepare("
        INSERT INTO scan_session_registry (session_id, user_id, purpose, is_active, last_activity, expires_at)
        VALUES (:session_id, :user_id, 'serial_entry', 1, NOW(), :expires_at)
        ON DUPLICATE KEY UPDATE 
            is_active = 1,
            last_activity = NOW(),
            expires_at = :expires_at2
    ");
    
    $stmt->execute([
        ':session_id' => $sessionId,
        ':user_id' => $userId,
        ':expires_at' => $expiresAt,
        ':expires_at2' => $expiresAt
    ]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Session registered',
        'session_id' => $sessionId,
        'expires_at' => $expiresAt
    ]);
}

/**
 * Push a scanned code from mobile
 * Called when mobile scans a barcode
 */
function handlePush($pdo, $data) {
    $sessionId = isset($data['session_id']) ? trim($data['session_id']) : '';
    $code = isset($data['code']) ? trim($data['code']) : '';
    $deviceInfo = isset($data['device_info']) ? substr($data['device_info'], 0, 255) : null;
    
    // Validate inputs
    if (empty($sessionId) || empty($code)) {
        jsonResponse(['error' => 'Session ID and code required', 'success' => false], 400);
    }
    
    // Validate session ID
    if (!preg_match('/^[a-zA-Z0-9_-]{10,64}$/', $sessionId)) {
        jsonResponse(['error' => 'Invalid session ID', 'success' => false], 400);
    }
    
    // Validate code (basic sanitization, allow typical barcode chars)
    $code = preg_replace('/[^\w\-\.\/\+\=\s]/', '', $code);
    if (strlen($code) > 255) {
        $code = substr($code, 0, 255);
    }
    
    if (empty($code)) {
        jsonResponse(['error' => 'Invalid code', 'success' => false], 400);
    }
    
    // Verify session exists and is active
    $stmt = $pdo->prepare("
        SELECT id FROM scan_session_registry 
        WHERE session_id = :session_id 
        AND is_active = 1 
        AND expires_at > NOW()
    ");
    $stmt->execute([':session_id' => $sessionId]);
    
    if (!$stmt->fetch()) {
        jsonResponse(['error' => 'Session expired or invalid', 'success' => false], 401);
    }
    
    // Get client IP
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ipAddress = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    
    // Determine code type
    $codeType = detectCodeType($code);
    
    // Insert the scanned code
    $stmt = $pdo->prepare("
        INSERT INTO scan_sessions (session_id, scanned_code, code_type, is_consumed, device_info, ip_address, created_at)
        VALUES (:session_id, :code, :code_type, 0, :device_info, :ip_address, NOW())
    ");
    
    $stmt->execute([
        ':session_id' => $sessionId,
        ':code' => $code,
        ':code_type' => $codeType,
        ':device_info' => $deviceInfo,
        ':ip_address' => $ipAddress
    ]);
    
    $insertId = $pdo->lastInsertId();
    
    // Update session last activity
    $pdo->prepare("
        UPDATE scan_session_registry SET last_activity = NOW() WHERE session_id = :session_id
    ")->execute([':session_id' => $sessionId]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Code received',
        'id' => $insertId,
        'code' => $code,
        'type' => $codeType
    ]);
}

/**
 * Check for new scanned codes
 * Called by PC polling every 1 second
 */
function handleCheck($pdo) {
    $sessionId = isset($_GET['session']) ? trim($_GET['session']) : '';
    
    if (empty($sessionId)) {
        jsonResponse(['status' => 'error', 'message' => 'Session ID required'], 400);
    }
    
    // Validate session ID
    if (!preg_match('/^[a-zA-Z0-9_-]{10,64}$/', $sessionId)) {
        jsonResponse(['status' => 'error', 'message' => 'Invalid session ID'], 400);
    }
    
    // Look for unconsumed codes for this session
    $stmt = $pdo->prepare("
        SELECT id, scanned_code, code_type, created_at
        FROM scan_sessions
        WHERE session_id = :session_id
        AND is_consumed = 0
        ORDER BY created_at ASC
        LIMIT 1
    ");
    
    $stmt->execute([':session_id' => $sessionId]);
    $row = $stmt->fetch();
    
    if ($row) {
        // Mark as consumed
        $updateStmt = $pdo->prepare("
            UPDATE scan_sessions 
            SET is_consumed = 1, consumed_at = NOW() 
            WHERE id = :id
        ");
        $updateStmt->execute([':id' => $row['id']]);
        
        // Update session activity
        $pdo->prepare("
            UPDATE scan_session_registry SET last_activity = NOW() WHERE session_id = :session_id
        ")->execute([':session_id' => $sessionId]);
        
        jsonResponse([
            'status' => 'found',
            'code' => $row['scanned_code'],
            'type' => $row['code_type'],
            'timestamp' => $row['created_at']
        ]);
    } else {
        jsonResponse([
            'status' => 'empty',
            'message' => 'No new codes'
        ]);
    }
}

/**
 * Ping to check if session is alive
 * Called by mobile to verify connection
 */
function handlePing($pdo) {
    $sessionId = isset($_GET['session']) ? trim($_GET['session']) : '';
    
    if (empty($sessionId)) {
        jsonResponse(['status' => 'error', 'message' => 'Session ID required'], 400);
    }
    
    // Check if session exists and is active
    $stmt = $pdo->prepare("
        SELECT id, created_at, last_activity, expires_at
        FROM scan_session_registry
        WHERE session_id = :session_id
        AND is_active = 1
        AND expires_at > NOW()
    ");
    
    $stmt->execute([':session_id' => $sessionId]);
    $session = $stmt->fetch();
    
    if ($session) {
        // Update last activity
        $pdo->prepare("
            UPDATE scan_session_registry SET last_activity = NOW() WHERE session_id = :session_id
        ")->execute([':session_id' => $sessionId]);
        
        jsonResponse([
            'status' => 'ok',
            'message' => 'Session active',
            'created_at' => $session['created_at'],
            'expires_at' => $session['expires_at']
        ]);
    } else {
        jsonResponse([
            'status' => 'expired',
            'message' => 'Session expired or not found'
        ]);
    }
}

/**
 * Cleanup old sessions and scanned codes
 * Can be called manually or via cron
 */
function handleCleanup($pdo) {
    // Delete scans older than 24 hours
    $stmt1 = $pdo->prepare("DELETE FROM scan_sessions WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $deleted1 = $stmt1->execute();
    $count1 = $stmt1->rowCount();
    
    // Delete expired sessions
    $stmt2 = $pdo->prepare("DELETE FROM scan_session_registry WHERE expires_at < NOW()");
    $deleted2 = $stmt2->execute();
    $count2 = $stmt2->rowCount();
    
    // Mark inactive sessions (no activity for 10 minutes)
    $stmt3 = $pdo->prepare("
        UPDATE scan_session_registry 
        SET is_active = 0 
        WHERE last_activity < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ");
    $stmt3->execute();
    $count3 = $stmt3->rowCount();
    
    jsonResponse([
        'success' => true,
        'message' => 'Cleanup completed',
        'deleted_scans' => $count1,
        'deleted_sessions' => $count2,
        'deactivated_sessions' => $count3
    ]);
}

/**
 * Detect type of scanned code based on pattern
 */
function detectCodeType($code) {
    // IMEI: 15-17 digits
    if (preg_match('/^\d{15,17}$/', $code)) {
        return 'imei';
    }
    
    // EAN-13: 13 digits
    if (preg_match('/^\d{13}$/', $code)) {
        return 'barcode';
    }
    
    // EAN-8: 8 digits
    if (preg_match('/^\d{8}$/', $code)) {
        return 'barcode';
    }
    
    // UPC-A: 12 digits
    if (preg_match('/^\d{12}$/', $code)) {
        return 'barcode';
    }
    
    // If contains URL-like pattern, it's probably QR
    if (preg_match('/^https?:\/\//', $code)) {
        return 'qrcode';
    }
    
    // Default to serial
    return 'serial';
}

/**
 * Send JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}
