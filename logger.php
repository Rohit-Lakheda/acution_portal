<?php
/**
 * Logging System for Payment and Registration
 * Stores logs in database for easy viewing
 */

require_once 'database.php';

// Create logs table if it doesn't exist
function createLogsTable($pdo) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS payment_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            log_type VARCHAR(50) NOT NULL,
            transaction_id VARCHAR(100) NULL,
            registration_id VARCHAR(50) NULL,
            message TEXT NOT NULL,
            data TEXT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_type (log_type),
            INDEX idx_transaction (transaction_id),
            INDEX idx_registration (registration_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch(PDOException $e) {
        // Table might already exist, ignore
    }
}

// Initialize logs table
createLogsTable($pdo);

/**
 * Log a message
 */
function logMessage($type, $message, $data = null, $transactionId = null, $registrationId = null) {
    global $pdo;
    
    try {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $dataJson = $data ? json_encode($data, JSON_PRETTY_PRINT) : null;
        
        $stmt = $pdo->prepare("INSERT INTO payment_logs 
            (log_type, transaction_id, registration_id, message, data, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$type, $transactionId, $registrationId, $message, $dataJson, $ipAddress, $userAgent]);
        
        // Also log to PHP error log for backup
        error_log("[{$type}] {$message}" . ($transactionId ? " | TxnID: {$transactionId}" : "") . ($registrationId ? " | RegID: {$registrationId}" : ""));
    } catch(PDOException $e) {
        error_log("Failed to write log: " . $e->getMessage());
    }
}

/**
 * Get logs with filters
 */
function getLogs($type = null, $limit = 100, $transactionId = null, $registrationId = null) {
    global $pdo;
    
    $sql = "SELECT * FROM payment_logs WHERE 1=1";
    $params = [];
    
    if ($type) {
        $sql .= " AND log_type = ?";
        $params[] = $type;
    }
    
    if ($transactionId) {
        $sql .= " AND transaction_id = ?";
        $params[] = $transactionId;
    }
    
    if ($registrationId) {
        $sql .= " AND registration_id = ?";
        $params[] = $registrationId;
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT ?";
    $params[] = $limit;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
?>

