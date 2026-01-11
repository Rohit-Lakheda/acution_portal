<?php
/**
 * Test Email Configuration
 * This endpoint tests the email settings by sending a test email
 */

// Start output buffering immediately to catch any unexpected output
if (!ob_get_level()) {
    ob_start();
}

// Suppress any warnings/notices that might output HTML
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header first
header('Content-Type: application/json; charset=utf-8');

// Function to send JSON response and exit
function sendJsonResponse($success, $message, $code = 200) {
    ob_clean();
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);
    if (ob_get_level()) {
        ob_end_flush();
    }
    exit;
}

try {
    // Check if files exist before requiring
    $requiredFiles = [
        'auth.php',
        'database.php',
        'functions.php',
        'email_sender.php',
        'email_templates.php'
    ];
    
    foreach ($requiredFiles as $file) {
        if (!file_exists($file)) {
            sendJsonResponse(false, "Required file not found: {$file}", 500);
        }
    }
    
    require_once 'auth.php';
    require_once 'database.php';
    require_once 'functions.php';
    require_once 'email_sender.php';
    require_once 'email_templates.php';
} catch (Exception $e) {
    sendJsonResponse(false, 'Failed to load required files: ' . $e->getMessage(), 500);
} catch (Error $e) {
    sendJsonResponse(false, 'PHP Error loading files: ' . $e->getMessage(), 500);
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Method not allowed', 405);
}

// Check if session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication manually to avoid HTML redirects
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    sendJsonResponse(false, 'Access denied. Admin privileges required.', 403);
}

// Verify CSRF token
if (!function_exists('verifyCSRFToken')) {
    sendJsonResponse(false, 'CSRF verification function not found', 500);
}
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    sendJsonResponse(false, 'Invalid CSRF token', 403);
}

try {
    // Get form data
    $smtpHost = trim($_POST['smtp_host'] ?? '');
    $smtpPort = intval($_POST['smtp_port'] ?? 587);
    $smtpUsername = trim($_POST['smtp_username'] ?? '');
    $smtpPassword = trim($_POST['smtp_password'] ?? '');
    $fromEmail = trim($_POST['from_email'] ?? '');
    $fromName = trim($_POST['from_name'] ?? '');
    $encryption = trim($_POST['encryption'] ?? 'tls');
    
            // Validation
    if (empty($smtpHost) || empty($smtpUsername) || empty($fromEmail)) {
        sendJsonResponse(false, 'Please fill in all required fields (SMTP Host, Username, and From Email).', 400);
    }
    
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(false, 'Invalid From Email address.', 400);
    }
    
    if ($smtpPort < 1 || $smtpPort > 65535) {
        sendJsonResponse(false, 'Invalid SMTP port.', 400);
    }
    
    // If password is empty, try to get from existing settings
    if (empty($smtpPassword)) {
        try {
            $stmt = $pdo->prepare("SELECT smtp_password FROM email_settings WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 1");
            $stmt->execute();
            $existing = $stmt->fetch();
            if ($existing && !empty($existing['smtp_password'])) {
                $smtpPassword = $existing['smtp_password'];
            } else {
                sendJsonResponse(false, 'SMTP password is required. Please enter your password.', 400);
            }
        } catch (PDOException $e) {
            sendJsonResponse(false, 'SMTP password is required. Please enter your password.', 400);
        }
    }
    
    // Temporarily override email settings for testing
    // We'll create a temporary email settings array
    $tempEmailSettings = [
        'smtp_host' => $smtpHost,
        'smtp_port' => $smtpPort,
        'smtp_username' => $smtpUsername,
        'smtp_password' => $smtpPassword,
        'from_email' => $fromEmail,
        'from_name' => $fromName,
        'encryption' => $encryption,
        'is_active' => 1
    ];
    
    // Save original settings function if it exists
    $originalGetEmailSettings = function_exists('getEmailSettings') ? 'getEmailSettings' : null;
    
    // Create a temporary function that returns our test settings
    if (!function_exists('getEmailSettingsOverride')) {
        function getEmailSettingsOverride($pdo = null) {
            global $tempEmailSettings;
            return $tempEmailSettings;
        }
    }
    
    // Temporarily override the getEmailSettings function
    // We'll modify sendEmailViaSMTP to use our test settings directly
    $testSmtpHost = $smtpHost;
    $testSmtpPort = $smtpPort;
    $testSmtpUser = $smtpUsername;
    $testSmtpPass = $smtpPassword;
    $testFromEmail = $fromEmail;
    $testFromName = $fromName;
    $testEncryption = $encryption;
    
    // Create a test email function that uses the provided settings
    $testEmailFunction = function($to, $subject, $htmlMessage, $plainTextMessage = '', &$errorMsg = '') use ($testSmtpHost, $testSmtpPort, $testSmtpUser, $testSmtpPass, $testFromEmail, $testFromName, $testEncryption) {
        if (!function_exists('fsockopen')) {
            $errorMsg = 'fsockopen function not available';
            return false;
        }
        
        $smtp = @fsockopen($testSmtpHost, $testSmtpPort, $errno, $errstr, 30);
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
        fputs($smtp, "EHLO " . $testSmtpHost . "\r\n");
        $response = '';
        while ($line = fgets($smtp, 515)) {
            $response .= $line;
            if (substr(trim($line), 3, 1) == ' ') {
                break;
            }
        }
        
        // Handle encryption
        if ($testEncryption === 'tls' && $testSmtpPort == 587) {
            fputs($smtp, "STARTTLS\r\n");
            $response = fgets($smtp, 515);
            if (substr($response, 0, 3) == '220') {
                if (!stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    $errorMsg = "Failed to enable TLS encryption";
                    fclose($smtp);
                    return false;
                }
                // Send EHLO again after TLS
                fputs($smtp, "EHLO " . $testSmtpHost . "\r\n");
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
        } elseif ($testEncryption === 'ssl' && $testSmtpPort == 465) {
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
        
        fputs($smtp, base64_encode($testSmtpUser) . "\r\n");
        $response = fgets($smtp, 515);
        if (substr($response, 0, 3) != '334') {
            $errorMsg = "SMTP User Error: {$response}";
            fclose($smtp);
            return false;
        }
        
        fputs($smtp, base64_encode($testSmtpPass) . "\r\n");
        $response = fgets($smtp, 515);
        
        if (substr($response, 0, 3) != '235') {
            $errorMsg = "SMTP Authentication failed: {$response}";
            fclose($smtp);
            return false;
        }
        
        // Send email
        fputs($smtp, "MAIL FROM: <" . $testFromEmail . ">\r\n");
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
        
        // Create multipart message
        $boundary = md5(uniqid(time()));
        
        $emailData = "From: " . $testFromName . " <" . $testFromEmail . ">\r\n";
        $emailData .= "To: {$to}\r\n";
        $emailData .= "Subject: {$subject}\r\n";
        $emailData .= "MIME-Version: 1.0\r\n";
        $emailData .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $emailData .= "\r\n";
        
        if ($plainTextMessage) {
            $emailData .= "--{$boundary}\r\n";
            $emailData .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $emailData .= "Content-Transfer-Encoding: 7bit\r\n";
            $emailData .= "\r\n";
            $emailData .= $plainTextMessage . "\r\n";
        }
        
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
    };
    
    // Prepare test email
    $testTo = $fromEmail; // Send test email to the "From" email address
    $testSubject = 'Test Email - NIXI Auction Portal';
    $testHtmlMessage = getEmailTemplateWrapper('
        <h2 style="margin: 0 0 20px 0; color: #1a237e; font-size: 26px; font-weight: 500;">✓ Email Configuration Test</h2>
        
        <p style="margin: 0 0 20px 0; color: #495057; font-size: 16px; line-height: 1.7;">
            Congratulations! Your email configuration is working correctly.
        </p>
        
        <div style="background: #e8f5e9; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #2e7d32;">
            <p style="margin: 0 0 10px 0; color: #1b5e20; font-size: 14px; font-weight: 600;">Email Settings Tested:</p>
            <ul style="margin: 0; padding-left: 20px; color: #1b5e20; font-size: 14px; line-height: 1.8;">
                <li>SMTP Host: ' . htmlspecialchars($smtpHost) . '</li>
                <li>SMTP Port: ' . htmlspecialchars($smtpPort) . '</li>
                <li>Encryption: ' . htmlspecialchars(strtoupper($encryption)) . '</li>
                <li>From Email: ' . htmlspecialchars($fromEmail) . '</li>
                <li>From Name: ' . htmlspecialchars($fromName) . '</li>
            </ul>
        </div>
        
        <p style="margin: 20px 0 0 0; color: #6c757d; font-size: 14px; line-height: 1.6;">
            This is a test email sent from the NIXI Auction Portal admin panel. If you received this email, your email configuration is working properly.
        </p>
    ', 'Email Test Successful');
    
    $testPlainMessage = "Email Configuration Test\n\n" .
                        "Congratulations! Your email configuration is working correctly.\n\n" .
                        "Email Settings Tested:\n" .
                        "- SMTP Host: {$smtpHost}\n" .
                        "- SMTP Port: {$smtpPort}\n" .
                        "- Encryption: " . strtoupper($encryption) . "\n" .
                        "- From Email: {$fromEmail}\n" .
                        "- From Name: {$fromName}\n\n" .
                        "This is a test email sent from the NIXI Auction Portal admin panel.";
    
    // Send test email
    $errorMsg = '';
    $sent = $testEmailFunction($testTo, $testSubject, $testHtmlMessage, $testPlainMessage, $errorMsg);
    
    if ($sent) {
        sendJsonResponse(true, 'Test email sent successfully to ' . htmlspecialchars($testTo) . '. Please check your inbox.');
    } else {
        sendJsonResponse(false, 'Failed to send test email: ' . ($errorMsg ?: 'Unknown error'), 400);
    }
    
} catch (Exception $e) {
    sendJsonResponse(false, 'Error: ' . $e->getMessage(), 500);
} catch (PDOException $e) {
    sendJsonResponse(false, 'Database error: ' . $e->getMessage(), 500);
} catch (Error $e) {
    sendJsonResponse(false, 'PHP Error: ' . $e->getMessage(), 500);
}
