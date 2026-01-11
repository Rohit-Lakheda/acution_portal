<?php
/**
 * Email Sender Functions
 * Centralized SMTP email sending functionality
 * Now supports dynamic email configuration from database
 */

/**
 * Get email settings from database only
 * Returns array with email configuration from database
 * Returns false if database is unavailable or no settings found
 */
function getEmailSettings($pdo = null, $forceReload = false) {
    static $cachedSettings = null;
    
    // If forceReload is true, clear cache
    if ($forceReload) {
        $cachedSettings = null;
    }
    
    // Return cached settings if available (unless forcing reload)
    if ($cachedSettings !== null && !$forceReload) {
        return $cachedSettings;
    }
    
    // Try to get from database only - no defaults
    try {
        if ($pdo === null) {
            // Try to get PDO from global scope or create connection
            global $pdo;
            if (!isset($pdo)) {
                // Try to load database connection
                $dbFile = __DIR__ . '/database.php';
                if (file_exists($dbFile)) {
                    require_once $dbFile;
                } else {
                    // Database file doesn't exist
                    error_log("Email settings: database.php file not found");
                    return false;
                }
            }
        }
        
        // Check if PDO is available and valid
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            // PDO not available
            error_log("Email settings: PDO connection not available");
            return false;
        }
        
        // Query database for email settings
        $stmt = $pdo->prepare("SELECT * FROM email_settings WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result && !empty($result['smtp_host']) && !empty($result['smtp_username']) && !empty($result['smtp_password']) && !empty($result['from_email'])) {
            // Valid settings found in database
            $cachedSettings = [
                'smtp_host' => $result['smtp_host'],
                'smtp_port' => (int)$result['smtp_port'],
                'smtp_username' => $result['smtp_username'],
                'smtp_password' => $result['smtp_password'],
                'from_email' => $result['from_email'],
                'from_name' => $result['from_name'] ?? 'NIXI Auction Portal',
                'encryption' => $result['encryption'] ?? 'tls',
                'is_active' => (int)($result['is_active'] ?? 1)
            ];
            return $cachedSettings;
        } else {
            // No valid settings found in database
            error_log("Email settings: No active settings found in database");
            return false;
        }
        
    } catch (PDOException $e) {
        // Database query failed (table might not exist, or connection issue)
        error_log("Email settings database error: " . $e->getMessage());
        return false;
    } catch (Exception $e) {
        // Any other error
        error_log("Email settings fetch error: " . $e->getMessage());
        return false;
    } catch (Error $e) {
        // PHP 7+ Error (fatal errors)
        error_log("Email settings PHP error: " . $e->getMessage());
        return false;
    }
}

/**
 * Clear cached email settings (useful after updating settings)
 * Forces getEmailSettings to reload from database on next call
 */
function clearEmailSettingsCache() {
    // Clear the static cache variable directly
    // We need to use a workaround since we can't directly modify static variables
    // Force reload by calling getEmailSettings with forceReload flag
    if (function_exists('getEmailSettings')) {
        getEmailSettings(null, true);
    }
}

// Load email settings from database only (no defaults)
// Constants will be defined when settings are actually used
// This prevents errors if settings aren't available yet

/**
 * Send email via SMTP
 * Uses email settings from database only (no defaults)
 */
function sendEmailViaSMTP($to, $subject, $htmlMessage, $plainTextMessage = '', &$errorMsg = '') {
    // Get email settings from database only
    global $pdo;
    $emailSettings = getEmailSettings($pdo);
    
    // Check if settings are available
    if ($emailSettings === false) {
        $errorMsg = 'Email settings not configured. Please configure email settings in admin panel.';
        return false;
    }
    
    // Check if email is active
    if (!$emailSettings['is_active']) {
        $errorMsg = 'Email service is currently disabled';
        return false;
    }
    
    // Validate all required credentials are present and not empty
    if (empty($emailSettings['smtp_host']) || empty($emailSettings['smtp_username']) || 
        empty($emailSettings['smtp_password']) || empty($emailSettings['from_email'])) {
        $errorMsg = 'Email settings incomplete. Missing required credentials (SMTP Host, Username, Password, or From Email).';
        error_log("Email sending failed: Incomplete email settings in database");
        return false;
    }
    
    if (!function_exists('fsockopen')) {
        $errorMsg = 'fsockopen function not available';
        return false;
    }
    
    $smtpHost = trim($emailSettings['smtp_host']);
    $smtpPort = (int)$emailSettings['smtp_port'];
    $smtpUser = trim($emailSettings['smtp_username']);
    $smtpPass = trim($emailSettings['smtp_password']);
    $fromEmail = trim($emailSettings['from_email']);
    $fromName = trim($emailSettings['from_name'] ?? 'NIXI Auction Portal');
    $encryption = strtolower(trim($emailSettings['encryption'] ?? 'tls'));
    
    // Double-check credentials are not empty after trimming
    if (empty($smtpHost) || empty($smtpUser) || empty($smtpPass) || empty($fromEmail)) {
        $errorMsg = 'Email credentials are empty or invalid. Please check your email settings.';
        error_log("Email sending failed: Empty credentials after validation");
        return false;
    }
    
    $smtp = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, 30);
    if (!$smtp) {
        $errorMsg = "Connection failed: {$errstr} ({$errno})";
        return false;
    }
    
    // Read initial response
    $response = fgets($smtp, 515);
    if (substr($response, 0, 3) != '220') {
        $errorMsg = "SMTP Error: {$response}";
        fclose($smtp);
        return false;
    }
    
    // Send EHLO
    fputs($smtp, "EHLO " . $smtpHost . "\r\n");
    $response = '';
    $ehloResponse = '';
    while ($line = fgets($smtp, 515)) {
        $ehloResponse .= $line;
        if (substr(trim($line), 3, 1) == ' ') {
            break; // Last line of multi-line response
        }
    }
    
    // Handle encryption
    if ($encryption === 'tls' && $smtpPort == 587) {
        fputs($smtp, "STARTTLS\r\n");
        $response = fgets($smtp, 515);
        if (substr($response, 0, 3) == '220') {
            if (!stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $errorMsg = "Failed to enable TLS encryption";
                fclose($smtp);
                return false;
            }
            // Send EHLO again after TLS
            fputs($smtp, "EHLO " . $smtpHost . "\r\n");
            $response = '';
            while ($line = fgets($smtp, 515)) {
                $response .= $line;
                if (substr(trim($line), 3, 1) == ' ') {
                    break;
                }
            }
        } else {
            $errorMsg = "STARTTLS failed: {$response}";
            fclose($smtp);
            return false;
        }
    } elseif ($encryption === 'ssl' && $smtpPort == 465) {
        // SSL connection should be established via stream_context
        // For now, we'll handle it similarly
        if (!stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_SSLv23_CLIENT)) {
            $errorMsg = "Failed to enable SSL encryption";
            fclose($smtp);
            return false;
        }
    }
    
    // Authenticate
    fputs($smtp, "AUTH LOGIN\r\n");
    $response = fgets($smtp, 515);
    if (substr($response, 0, 3) != '334') {
        $errorMsg = "SMTP Auth Error: {$response}";
        fclose($smtp);
        return false;
    }
    
    fputs($smtp, base64_encode($smtpUser) . "\r\n");
    $response = fgets($smtp, 515);
    if (substr($response, 0, 3) != '334') {
        $errorMsg = "SMTP User Error: {$response}";
        fclose($smtp);
        return false;
    }
    
    fputs($smtp, base64_encode($smtpPass) . "\r\n");
    $response = fgets($smtp, 515);
    
    // Check authentication response - must be 235 for success
    if (substr($response, 0, 3) != '235') {
        $errorMsg = "SMTP Authentication failed. Wrong credentials or server error: {$response}";
        error_log("SMTP Authentication failed for user: {$smtpUser}. Response: {$response}");
        fclose($smtp);
        return false;
    }
    
    // Send email
    fputs($smtp, "MAIL FROM: <" . $fromEmail . ">\r\n");
    $response = fgets($smtp, 515);
    if (substr($response, 0, 3) != '250') {
        $errorMsg = "SMTP MAIL FROM Error: {$response}";
        fclose($smtp);
        return false;
    }
    
    fputs($smtp, "RCPT TO: <{$to}>\r\n");
    $response = fgets($smtp, 515);
    if (substr($response, 0, 3) != '250') {
        $errorMsg = "SMTP RCPT TO Error: {$response}";
        fclose($smtp);
        return false;
    }
    
    fputs($smtp, "DATA\r\n");
    $response = fgets($smtp, 515);
    if (substr($response, 0, 3) != '354') {
        $errorMsg = "SMTP DATA Error: {$response}";
        fclose($smtp);
        return false;
    }
    
    // Create multipart message with HTML and plain text
    $boundary = md5(uniqid(time()));
    
    $emailData = "From: " . $fromName . " <" . $fromEmail . ">\r\n";
    $emailData .= "To: {$to}\r\n";
    $emailData .= "Subject: {$subject}\r\n";
    $emailData .= "MIME-Version: 1.0\r\n";
    $emailData .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $emailData .= "\r\n";
    
    // Plain text version
    if ($plainTextMessage) {
        $emailData .= "--{$boundary}\r\n";
        $emailData .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $emailData .= "Content-Transfer-Encoding: 7bit\r\n";
        $emailData .= "\r\n";
        $emailData .= $plainTextMessage . "\r\n";
    }
    
    // HTML version
    $emailData .= "--{$boundary}\r\n";
    $emailData .= "Content-Type: text/html; charset=UTF-8\r\n";
    $emailData .= "Content-Transfer-Encoding: 7bit\r\n";
    $emailData .= "\r\n";
    $emailData .= $htmlMessage . "\r\n";
    $emailData .= "--{$boundary}--\r\n";
    $emailData .= ".\r\n";
    
    fputs($smtp, $emailData);
    $response = fgets($smtp, 515);
    
    fputs($smtp, "QUIT\r\n");
    fclose($smtp);
    
    if (substr($response, 0, 3) == '250') {
        return true;
    } else {
        $errorMsg = "SMTP Send failed: {$response}";
        return false;
    }
}

?>

