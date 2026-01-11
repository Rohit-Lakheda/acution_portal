<?php
/**
 * Auction Registration Page
 * Standalone single-page registration form with validation and OTP verification
 * All code in one file - ready for PHP core projects
 */

session_start();
require_once 'database.php';
require_once 'functions.php';

// Configuration
define('DEBUG_MODE', true); // Set to false in production
define('SMTP_HOST', 'smtp.elasticemail.com'); // ElasticEmail SMTP
define('SMTP_PORT', 587);
define('SMTP_USER', 'enquiry@interlinx.in'); // SMTP username
define('SMTP_PASS', 'C8774994D4A25847EDBFBFD9D7996DD93391'); // SMTP password
define('FROM_EMAIL', 'enquiry@interlinx.in');
define('FROM_NAME', 'Auction Registration');

// Idfy PAN Verification API Configuration (Optional)
define('IDFY_ACCOUNT_ID', '038bb27f4ff8/cef38574-71bd-45b9-9faf-e1041c23ad46');
define('IDFY_API_KEY', 'edd2efad-0f6d-4b60-95a7-fd6be3672cbe');
define('IDFY_BASE_URL', 'https://eve.idfy.com');

// Helper function to send email via SMTP
function sendEmailViaSMTP($to, $subject, $htmlMessage, $plainTextMessage = '', &$errorMsg = '') {
    if (!function_exists('fsockopen')) {
        $errorMsg = 'fsockopen function not available';
        return false;
    }
    
    $smtp = @fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 30);
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
    fputs($smtp, "EHLO " . SMTP_HOST . "\r\n");
    $response = '';
    $ehloResponse = '';
    while ($line = fgets($smtp, 515)) {
        $ehloResponse .= $line;
        if (substr(trim($line), 3, 1) == ' ') {
            break; // Last line of multi-line response
        }
    }
    
    // Start TLS if port is 587 (Gmail requires this)
    if (SMTP_PORT == 587) {
        fputs($smtp, "STARTTLS\r\n");
        $response = fgets($smtp, 515);
        if (substr($response, 0, 3) == '220') {
            if (!stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $errorMsg = "Failed to enable TLS encryption";
                fclose($smtp);
                return false;
            }
            // Send EHLO again after TLS
            fputs($smtp, "EHLO " . SMTP_HOST . "\r\n");
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
    }
    
    // Authenticate
    fputs($smtp, "AUTH LOGIN\r\n");
    $response = fgets($smtp, 515);
    if (substr($response, 0, 3) != '334') {
        $errorMsg = "SMTP Auth Error: {$response}";
        fclose($smtp);
        return false;
    }
    
    fputs($smtp, base64_encode(SMTP_USER) . "\r\n");
    $response = fgets($smtp, 515);
    if (substr($response, 0, 3) != '334') {
        $errorMsg = "SMTP User Error: {$response}";
        fclose($smtp);
        return false;
    }
    
    fputs($smtp, base64_encode(SMTP_PASS) . "\r\n");
    $response = fgets($smtp, 515);
    
    if (substr($response, 0, 3) != '235') {
        $errorMsg = "SMTP Authentication failed: {$response}";
        fclose($smtp);
        return false;
    }
    
    // Send email
    fputs($smtp, "MAIL FROM: <" . FROM_EMAIL . ">\r\n");
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
    
    $emailData = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
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

// Generate UUID v4 for task_id and group_id (similar to NIXI project)
function generateUUID() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xFFFF),
        mt_rand(0, 0xFFFF),
        mt_rand(0, 0xFFFF),
        mt_rand(0, 0x0FFF) | 0x4000,
        mt_rand(0, 0x3FFF) | 0x8000,
        mt_rand(0, 0xFFFF),
        mt_rand(0, 0xFFFF),
        mt_rand(0, 0xFFFF)
    );
}

// Initialize variables
$errors = [];
$success = '';
$emailVerified = false;
$mobileVerified = false;
$panVerified = false;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'send_email_otp':
            $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
                exit;
            }
            
            // Check if email already exists in database
            try {
                // Check in users table
                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $checkStmt->execute([$email]);
                $existingEmail = $checkStmt->fetch();
                
                if ($existingEmail) {
                    echo json_encode(['success' => false, 'message' => 'Email address already exists.']);
                    exit;
                }
                
                // Check in registration table
                $checkRegStmt = $pdo->prepare("SELECT id FROM registration WHERE email = ?");
                $checkRegStmt->execute([$email]);
                $existingRegEmail = $checkRegStmt->fetch();
                
                if ($existingRegEmail) {
                    echo json_encode(['success' => false, 'message' => 'Email address already exists.']);
                    exit;
                }
                
                // // Check in pending_registrations table (active pending registrations)
                // $checkPendingStmt = $pdo->prepare("SELECT id FROM pending_registrations WHERE email = ? AND expires_at > NOW()");
                // $checkPendingStmt->execute([$email]);
                // $existingPendingEmail = $checkPendingStmt->fetch();
                
                // if ($existingPendingEmail) {
                //     echo json_encode(['success' => false, 'message' => 'Email address already exists in the database.']);
                //     exit;
                // }
            } catch(PDOException $e) {
                // Log error but continue with OTP sending (don't block if DB check fails)
                if (DEBUG_MODE) {
                    error_log("Email database check error: " . $e->getMessage());
                }
            }
            
            // Generate OTP
            $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Store in session
            $_SESSION['email_otp_' . md5($email)] = $otp;
            $_SESSION['email_otp_time_' . md5($email)] = time();
            
            // Use modern email template
            require_once 'email_templates.php';
            
            $subject = 'Email Verification OTP - NIXI Auction Portal';
            $message = getOTPEmail($otp, 'Email Verification');
            
            // Plain text version for email clients that don't support HTML
            $plainTextMessage = "Your OTP for email verification is: {$otp}\n\nThis OTP is valid for 10 minutes.\n\nIf you did not request this OTP, please ignore this email.";
            
            $sent = false;
            $errorMsg = '';
            $lastError = '';
            
            // First try SMTP (more reliable) with HTML email
            $sent = sendEmailViaSMTP($email, $subject, $message, $plainTextMessage, $errorMsg);
            $lastError = $errorMsg;
            
            // If SMTP fails, try mail() function as fallback
            if (!$sent && function_exists('mail')) {
                $headers = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
                $headers .= "Reply-To: " . FROM_EMAIL . "\r\n";
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion();
                
                // Capture error if mail() fails
                $oldErrorReporting = error_reporting(0);
                $sent = mail($email, $subject, $message, $headers);
                $lastError = error_get_last();
                error_reporting($oldErrorReporting);
                
                if (!$sent) {
                    $lastError = $lastError ? $lastError['message'] : 'mail() function returned false';
                } else {
                    $lastError = '';
                }
            }
            
            // Log error for debugging
            if (!$sent && DEBUG_MODE) {
                error_log("Email sending failed. SMTP Error: {$errorMsg}, Mail Error: {$lastError}");
            }
            
            if ($sent || DEBUG_MODE) {
                $responseMsg = $sent ? 'OTP sent to your email.' : 'OTP generated (email sending failed in debug mode).';
                echo json_encode([
                    'success' => true,
                    'message' => $responseMsg,
                    'otp' => DEBUG_MODE ? $otp : null,
                    'email_sent' => $sent,
                    'debug_info' => DEBUG_MODE ? [
                        'smtp_error' => $errorMsg,
                        'mail_error' => $lastError
                    ] : null
                ]);
            } else {
                $errorMessage = 'Failed to send email. ';
                if (DEBUG_MODE) {
                    $errorMessage .= "SMTP Error: " . ($errorMsg ?: 'None') . ". ";
                    $errorMessage .= "Mail Error: " . ($lastError ?: 'None');
                } else {
                    $errorMessage .= 'Please try again or contact support.';
                }
                
                echo json_encode([
                    'success' => false, 
                    'message' => $errorMessage,
                    'otp' => DEBUG_MODE ? $otp : null // Show OTP in debug mode even if email fails
                ]);
            }
            exit;
            
        case 'verify_email_otp':
            $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
            $otp = preg_replace('/[^0-9]/', '', $_POST['otp'] ?? '');
            
            if (strlen($otp) !== 6) {
                echo json_encode(['success' => false, 'message' => 'Invalid OTP format.']);
                exit;
            }
            
            $sessionKey = 'email_otp_' . md5($email);
            $storedOtp = $_SESSION[$sessionKey] ?? null;
            
            if ($storedOtp && $storedOtp === $otp) {
                $_SESSION['email_verified_' . md5($email)] = true;
                echo json_encode(['success' => true, 'message' => 'Email verified successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
            }
            exit;
            
        case 'send_mobile_otp':
            $mobile = preg_replace('/[^0-9]/', '', $_POST['mobile'] ?? '');
            
            if (strlen($mobile) !== 10) {
                echo json_encode(['success' => false, 'message' => 'Invalid mobile number.']);
                exit;
            }
            
            // Check if mobile number already exists in database
            try {
                // Check in registration table
                $checkStmt = $pdo->prepare("SELECT id FROM registration WHERE mobile = ?");
                $checkStmt->execute([$mobile]);
                $existingMobile = $checkStmt->fetch();
                
                if ($existingMobile) {
                    echo json_encode(['success' => false, 'message' => 'Mobile number already exists.']);
                    exit;
                }
                
                // Check in pending_registrations table (active pending registrations)
                // $checkPendingStmt = $pdo->prepare("SELECT id FROM pending_registrations WHERE mobile = ? AND expires_at > NOW()");
                // $checkPendingStmt->execute([$mobile]);
                // $existingPendingMobile = $checkPendingStmt->fetch();
                
                // if ($existingPendingMobile) {
                //     echo json_encode(['success' => false, 'message' => 'Mobile number already exists in the database.']);
                //     exit;
                // }
            } catch(PDOException $e) {
                // Log error but continue with OTP sending (don't block if DB check fails)
                if (DEBUG_MODE) {
                    error_log("Mobile database check error: " . $e->getMessage());
                }
            }
            
            // Generate OTP
            $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            // Store in session
            $_SESSION['mobile_otp_' . md5($mobile)] = $otp;
            $_SESSION['mobile_otp_time_' . md5($mobile)] = time();
            
            // In production, send SMS here
            // For now, just return OTP in debug mode
            echo json_encode([
                'success' => true,
                'message' => 'OTP sent to your mobile.',
                'otp' => DEBUG_MODE ? $otp : null
            ]);
            exit;
            
        case 'verify_mobile_otp':
            $mobile = preg_replace('/[^0-9]/', '', $_POST['mobile'] ?? '');
            $otp = preg_replace('/[^0-9]/', '', $_POST['otp'] ?? '');
            
            if (strlen($otp) !== 6) {
                echo json_encode(['success' => false, 'message' => 'Invalid OTP format.']);
                exit;
            }
            
            $sessionKey = 'mobile_otp_' . md5($mobile);
            $storedOtp = $_SESSION[$sessionKey] ?? null;
            
            if ($storedOtp && $storedOtp === $otp) {
                $_SESSION['mobile_verified_' . md5($mobile)] = true;
                echo json_encode(['success' => true, 'message' => 'Mobile verified successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
            }
            exit;
            
        case 'verify_pan':
            $panNo = strtoupper(preg_replace('/[^A-Z0-9]/', '', $_POST['pancardno'] ?? ''));
            $fullName = trim(strip_tags($_POST['fullname'] ?? ''));
            $dob = $_POST['dateofbirth'] ?? '';
            
            // Validate PAN format
            if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $panNo)) {
                echo json_encode(['success' => false, 'message' => 'Invalid PAN format.']);
                exit;
            }
            
            // Validate name
            if (!preg_match('/^[a-zA-Z\s\'-]+$/', $fullName) || strlen($fullName) < 2) {
                echo json_encode(['success' => false, 'message' => 'Invalid name format.']);
                exit;
            }
            
            // Validate date
            if (empty($dob) || strtotime($dob) >= time()) {
                echo json_encode(['success' => false, 'message' => 'Invalid date of birth.']);
                exit;
            }
            
            // Check if PAN number already exists in database
            try {
                // Check in registration table (completed registrations)
                $checkStmt = $pdo->prepare("SELECT id FROM registration WHERE pan_card_number = ?");
                $checkStmt->execute([$panNo]);
                $existingPan = $checkStmt->fetch();
                
                if ($existingPan) {
                    echo json_encode(['success' => false, 'message' => 'PAN number already exists.']);
                    exit;
                }
                
                // Check in pending_registrations table (active pending registrations)
                // $checkPendingStmt = $pdo->prepare("SELECT id FROM pending_registrations WHERE pan_card_number = ? AND expires_at > NOW()");
                // $checkPendingStmt->execute([$panNo]);
                // $existingPendingPan = $checkPendingStmt->fetch();
                
                // if ($existingPendingPan) {
                //     echo json_encode(['success' => false, 'message' => 'PAN number already exists in the database.']);
                //     exit;
                // }
            } catch(PDOException $e) {
                // Log error but continue with verification (don't block if DB check fails)
                if (DEBUG_MODE) {
                    error_log("PAN database check error: " . $e->getMessage());
                }
            }
            
            // Create PAN verification task (using Idfy API)
            // Generate UUIDs similar to NIXI project
            $taskId = generateUUID();
            $groupId = generateUUID();
            
            $url = IDFY_BASE_URL . '/v3/tasks/async/verify_with_source/ind_pan';
            $postData = json_encode([
                'task_id' => $taskId,
                'group_id' => $groupId,
                'data' => [
                    'id_number' => $panNo,
                    'full_name' => strtoupper($fullName),
                    'dob' => $dob
                ]
            ]);
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'account-id: ' . IDFY_ACCOUNT_ID,
                'api-key: ' . IDFY_API_KEY,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                $errorMsg = DEBUG_MODE ? "cURL Error: {$curlError}" : 'Connection error. Please try again.';
                echo json_encode(['success' => false, 'message' => $errorMsg]);
                exit;
            }
            
            // HTTP 202 (Accepted) or 200 (OK) means task was created successfully
            if ($httpCode === 200 || $httpCode === 202) {
                $data = json_decode($response, true);
                if (isset($data['request_id'])) {
                    // Store request_id in session for status checking
                    $_SESSION['pan_verification_request_id_' . md5($panNo)] = $data['request_id'];
                    $_SESSION['pan_verification_pan_' . md5($panNo)] = $panNo;
                    $_SESSION['pan_verification_name_' . md5($panNo)] = $fullName;
                    $_SESSION['pan_verification_dob_' . md5($panNo)] = $dob;
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'PAN verification initiated. Checking status...',
                        'request_id' => $data['request_id'],
                        'status' => 'initiated'
                    ]);
                } else {
                    $errorMsg = DEBUG_MODE ? ('API Response: ' . $response) : 'Invalid response from verification service.';
                    if (isset($data['message'])) {
                        $errorMsg = $data['message'];
                    }
                    echo json_encode(['success' => false, 'message' => $errorMsg]);
                }
            } else {
                $data = json_decode($response, true);
                $errorMsg = 'PAN verification API error.';
                if (DEBUG_MODE) {
                    $errorMsg = "HTTP {$httpCode}: " . ($data['message'] ?? $response ?? 'Unknown error');
                } elseif (isset($data['message'])) {
                    $errorMsg = $data['message'];
                }
                echo json_encode(['success' => false, 'message' => $errorMsg]);
            }
            
            
            // For demo purposes, simulate successful verification
            // $_SESSION['pan_verified_' . md5($panNo)] = true;
            // $_SESSION['pan_verification_data'] = [
            //     'pan_number' => $panNo,
            //     'full_name' => $fullName,
            //     'date_of_birth' => $dob,
            //     'is_verified' => true
            // ];
            
            // echo json_encode([
            //     'success' => true,
            //     'message' => 'PAN verified successfully!',
            //     'status' => 'completed'
            // ]);
            exit;
            
        case 'check_pan_status':
            $requestId = $_POST['request_id'] ?? '';
            
            if (empty($requestId)) {
                echo json_encode(['success' => false, 'message' => 'Request ID is required.']);
                exit;
            }
            
            // Check PAN verification status (using Idfy API)
            $url = IDFY_BASE_URL . '/v3/tasks?request_id=' . urlencode($requestId);
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'account-id: ' . IDFY_ACCOUNT_ID,
                'api-key: ' . IDFY_API_KEY,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                $errorMsg = DEBUG_MODE ? "cURL Error: {$curlError}" : 'Connection error. Please try again.';
                echo json_encode(['success' => false, 'message' => $errorMsg]);
                exit;
            }
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                if (!empty($data) && isset($data[0])) {
                    $task = $data[0];
                    $status = $task['status'] ?? 'unknown';
                    
                    if ($status === 'completed') {
                        // Verify the result details
                        $result = $task['result'] ?? null;
                        $sourceOutput = $result['source_output'] ?? null;
                        
                        if (!$sourceOutput) {
                            echo json_encode([
                                'success' => false,
                                'status' => 'failed',
                                'message' => 'Invalid verification result. Please try again.'
                            ]);
                            exit;
                        }
                        
                        // Extract verification details
                        $panStatus = $sourceOutput['pan_status'] ?? '';
                        $nameMatch = $sourceOutput['name_match'] ?? false;
                        $dobMatch = $sourceOutput['dob_match'] ?? false;
                        $verificationStatus = $sourceOutput['status'] ?? '';
                        
                        // Check if verification is valid
                        $isValid = $verificationStatus === 'id_found' &&
                                  (stripos($panStatus, 'Valid') !== false || $panStatus === 'Valid') &&
                                  $nameMatch &&
                                  $dobMatch;
                        
                        if ($isValid) {
                            // Get PAN number from session to mark as verified
                            $panNo = $_POST['pancardno'] ?? '';
                            if ($panNo) {
                                $panNo = strtoupper(preg_replace('/[^A-Z0-9]/', '', $panNo));
                                $_SESSION['pan_verified_' . md5($panNo)] = true;
                                $_SESSION['pan_verification_data_' . md5($panNo)] = [
                                    'pan_number' => $panNo,
                                    'pan_status' => $panStatus,
                                    'name_match' => $nameMatch,
                                    'dob_match' => $dobMatch,
                                    'status' => $verificationStatus,
                                    'verified_at' => date('Y-m-d H:i:s')
                                ];
                            }
                            
                            echo json_encode([
                                'success' => true,
                                'status' => 'completed',
                                'verified' => true,
                                'message' => 'PAN verified successfully!',
                                'pan_status' => $panStatus,
                                'name_match' => $nameMatch,
                                'dob_match' => $dobMatch
                            ]);
                        } else {
                            $failureReasons = [];
                            if ($verificationStatus !== 'id_found') {
                                $failureReasons[] = 'PAN not found';
                            }
                            if (stripos($panStatus, 'Valid') === false && $panStatus !== 'Valid') {
                                $failureReasons[] = 'Invalid PAN status: ' . $panStatus;
                            }
                            if (!$nameMatch) {
                                $failureReasons[] = 'Name does not match';
                            }
                            if (!$dobMatch) {
                                $failureReasons[] = 'Date of birth does not match';
                            }
                            
                            $errorMsg = 'PAN verification failed. ' . implode(', ', $failureReasons);
                            
                            echo json_encode([
                                'success' => false,
                                'status' => 'failed',
                                'verified' => false,
                                'message' => $errorMsg,
                                'pan_status' => $panStatus,
                                'name_match' => $nameMatch,
                                'dob_match' => $dobMatch
                            ]);
                        }
                    } elseif ($status === 'failed' || $status === 'error') {
                        echo json_encode([
                            'success' => false,
                            'status' => 'failed',
                            'message' => $task['error'] ?? 'PAN verification failed.'
                        ]);
                    } else {
                        // Still processing
                        echo json_encode([
                            'success' => true,
                            'status' => $status,
                            'message' => 'Verification in progress... Please wait.'
                        ]);
                    }
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Invalid response from verification service.'
                    ]);
                }
            } else {
                $data = json_decode($response, true);
                $errorMsg = 'Failed to check verification status.';
                if (DEBUG_MODE) {
                    $errorMsg = "HTTP {$httpCode}: " . ($data['message'] ?? $response ?? 'Unknown error');
                } elseif (isset($data['message'])) {
                    $errorMsg = $data['message'];
                }
                echo json_encode(['success' => false, 'message' => $errorMsg]);
            }
            exit;
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit;
}

// Handle form submission (validation only, no database save)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
    $registrationType = $_POST['registration_type'] ?? '';
    $panNo = strtoupper(preg_replace('/[^A-Z0-9]/', '', $_POST['pancardno'] ?? ''));
    $fullName = trim(strip_tags($_POST['fullname'] ?? ''));
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $mobile = preg_replace('/[^0-9]/', '', $_POST['mobile'] ?? '');
    $dob = $_POST['dateofbirth'] ?? '';
    $declaration = isset($_POST['declaration']);
    
    // Validation
    if (!in_array($registrationType, ['individual', 'entity'])) {
        $errors[] = 'Invalid registration type.';
    }
    
    if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $panNo)) {
        $errors[] = 'Invalid PAN Card Number format. Format: ABCDE1234F';
    }
    
    if (!preg_match('/^[a-zA-Z\s\'-]+$/', $fullName) || strlen($fullName) < 2) {
        $errors[] = 'Invalid name format. Only letters, spaces, apostrophes, and hyphens are allowed.';
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email address.';
    }
    
    if (strlen($mobile) !== 10) {
        $errors[] = 'Mobile number must be exactly 10 digits.';
    }
    
    if (empty($dob) || strtotime($dob) >= time()) {
        $errors[] = 'Date of birth must be a past date.';
    }
    
    if (!$declaration) {
        $errors[] = 'You must accept the declaration and authorization.';
    }
    
    // Check verifications
    $emailVerified = $_SESSION['email_verified_' . md5($email)] ?? false;
    $mobileVerified = $_SESSION['mobile_verified_' . md5($mobile)] ?? false;
    $panVerified = $_SESSION['pan_verified_' . md5($panNo)] ?? false;
    
    if (!$emailVerified) {
        $errors[] = 'Please verify your email address.';
    }
    
    if (!$mobileVerified) {
        $errors[] = 'Please verify your mobile number.';
    }
    
    if (!$panVerified) {
        $errors[] = 'Please verify your PAN Card.';
    }
    
    if (empty($errors)) {
        // All validations passed - check if email already exists
        try {
            // Check if email already exists in users table
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $checkStmt->execute([$email]);
            $existingUser = $checkStmt->fetch();
            
            if ($existingUser) {
                $errors[] = 'Email address is already registered. Please use a different email or login.';
            } else {
                // Store registration data in session for payment processing
                // Data will be saved to database only after successful payment
                $_SESSION['pending_registration'] = [
                    'registration_type' => $registrationType,
                    'full_name' => $fullName,
                    'date_of_birth' => $dob,
                    'pan_card_number' => $panNo,
                    'email' => $email,
                    'mobile' => $mobile
                ];
                
                // Get registration amount from settings
                $registrationAmount = getRegistrationAmount($pdo);
                
                // Redirect to payment page
                header('Location: initiate_registration_payment.php');
                exit;
            }
        } catch(PDOException $e) {
            $errors[] = 'Registration validation failed. Please try again.';
            if (DEBUG_MODE) {
                $errors[] = 'Error: ' . $e->getMessage();
            }
        }
    }
}

// Get current verification status
$currentEmail = $_POST['email'] ?? '';
$currentMobile = $_POST['mobile'] ?? '';
$currentPan = $_POST['pancardno'] ?? '';
if ($currentEmail) {
    $emailVerified = $_SESSION['email_verified_' . md5($currentEmail)] ?? false;
}
if ($currentMobile) {
    $mobileVerified = $_SESSION['mobile_verified_' . md5($currentMobile)] ?? false;
}
if ($currentPan) {
    $panVerified = $_SESSION['pan_verified_' . md5(strtoupper(preg_replace('/[^A-Z0-9]/', '', $currentPan)))] ?? false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auction Registration</title>
    <link href="https://fonts.googleapis.com/css2?family=Varela+Round&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Varela Round', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            padding: 0;
            color: #2c3e50;
            line-height: 1.6;
        }
        .top-header {
            background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
            color: white;
            padding: 18px 0;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 40px;
        }
        .top-header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 18px;
            padding: 0 30px;
        }
        .top-header-logo {
            height: 50px;
            width: auto;
            max-width: 150px;
            object-fit: contain;
            background: white;
            padding: 5px;
            border-radius: 12px;
        }
        .top-header-title {
            font-size: 24px;
            font-weight: 400;
            letter-spacing: 0.5px;
        }
        @media (max-width: 768px) {
            .top-header-logo { height: 40px; }
            .top-header-title { font-size: 20px; }
        }
        @media (max-width: 480px) {
            .top-header-logo { height: 35px; }
            .top-header-title { font-size: 18px; }
        }
        .container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .card {
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }
        .card-header {
            background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
            color: white;
            border-bottom: none;
            border-radius: 12px 12px 0 0;
            padding: 25px;
        }
        .card-header h4 {
            font-weight: 400;
            font-size: 24px;
            letter-spacing: 0.3px;
            margin: 0;
        }
        .form-label {
            font-weight: 400;
            color: #1a237e;
            font-size: 15px;
            letter-spacing: 0.2px;
            margin-bottom: 8px;
        }
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 16px;
            font-family: 'Varela Round', sans-serif;
            font-size: 15px;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #1a237e;
            box-shadow: 0 0 0 3px rgba(26, 35, 126, 0.1);
        }
        .btn-primary {
            background: #1a237e;
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-family: 'Varela Round', sans-serif;
            font-size: 15px;
            font-weight: 400;
            letter-spacing: 0.3px;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background: #283593;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(26, 35, 126, 0.3);
        }
        .btn-outline-primary {
            border: 2px solid #1a237e;
            color: #1a237e;
            border-radius: 8px;
            padding: 10px 20px;
            font-family: 'Varela Round', sans-serif;
            font-size: 14px;
            transition: all 0.3s;
        }
        .btn-outline-primary:hover {
            background: #1a237e;
            color: white;
        }
        .btn-success {
            background: #2e7d32;
            border: none;
            border-radius: 8px;
            font-family: 'Varela Round', sans-serif;
        }
        .alert {
            border-radius: 8px;
            border-left: 4px solid;
            font-size: 15px;
        }
        .alert-info {
            background: #e3f2fd;
            color: #1565c0;
            border-color: #1565c0;
        }
        .alert-danger {
            border-left-color: #c62828;
        }
        .alert-warning {
            background: #fff3e0;
            color: #e65100;
            border-color: #e65100;
        }
        .card {
            border-radius: 0.75rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .card-header {
            background-color: #000;
            color: #fff;
            border-bottom: 2px solid #FFCD00;
            border-radius: 0.75rem 0.75rem 0 0;
        }
        .btn-primary {
            background-color: #000;
            border-color: #000;
        }
        .btn-primary:hover {
            background-color: #333;
        }
        .btn-success {
            background-color: #00C853;
        }
        .btn-danger {
            background-color: #FF1744;
        }
        .invalid-feedback {
            color: #4169E1 !important;
        }
        .form-control.is-invalid {
            border-color: #4169E1 !important;
        }
        .alert-danger {
            color: #4169E1 !important;
        }
    </style>
</head>
<body>
    <div class="top-header">
        <div class="top-header-content">
            <img src="images/nixi_logo1.jpg" alt="NIXI Logo" class="top-header-logo">
            <span class="top-header-title">🎯 Auction Portal</span>
        </div>
    </div>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10 col-xl-9">
                <div class="card shadow">
                    <div class="card-header">
                        <h4 class="mb-0">Auction Registration</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>
                        
                        <p class="lead">Welcome to the Auction Registration Portal</p>
                        <p>Please fill out the form below to register for an account.</p>
                        
                        <?php
                        // Display registration amount
                        try {
                            $regAmount = getRegistrationAmount($pdo);
                            echo '<div class="alert alert-info mb-4">';
                            echo '<strong>Registration Fee:</strong> ₹' . number_format($regAmount, 2) . ' (Payable after form submission)';
                            echo '</div>';
                        } catch(Exception $e) {
                            // Ignore if function not available
                        }
                        
                        // Display error messages from payment callbacks
                        if (isset($_GET['error'])) {
                            $errorMsg = '';
                            switch($_GET['error']) {
                                case 'payment_failed':
                                    $errorMsg = 'Payment failed. Please try again.';
                                    if (isset($_GET['msg'])) {
                                        $errorMsg .= ' ' . htmlspecialchars(urldecode($_GET['msg']));
                                    }
                                    break;
                                case 'payment_verification_failed':
                                    $errorMsg = 'Payment verification failed. Please contact support.';
                                    break;
                                case 'registration_save_failed':
                                    $errorMsg = 'Registration could not be completed. Please contact support.';
                                    break;
                                case 'no_registration_data':
                                    $errorMsg = 'Registration data not found. Please fill the form again.';
                                    break;
                                case 'payment_initiation_failed':
                                    $errorMsg = 'Could not initiate payment. Please try again.';
                                    break;
                            }
                            if ($errorMsg) {
                                echo '<div class="alert alert-danger">' . htmlspecialchars($errorMsg) . '</div>';
                            }
                        }
                        ?>
                        
                        <form method="POST" id="registrationForm">
                            <!-- Registration Type -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Registration Type <span style="color: #4169E1;">*</span></label>
                                <div class="d-flex gap-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="registration_type" id="registration_type_entity" value="entity" <?php echo (($_POST['registration_type'] ?? 'entity') === 'entity') ? 'checked' : ''; ?> required>
                                        <label class="form-check-label" for="registration_type_entity">Entity</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="registration_type" id="registration_type_individual" value="individual" <?php echo (($_POST['registration_type'] ?? '') === 'individual') ? 'checked' : ''; ?> required>
                                        <label class="form-check-label" for="registration_type_individual">Individual</label>
                                    </div>
                                </div>
                                <small class="form-text text-muted">Select whether you are registering as an individual or an entity</small>
                            </div>
                            
                            <!-- Name, DOB, and PAN Section -->
                            <div class="mb-4" style="border-top: 2px solid #e9ecef; padding-top: 25px;">
                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <label for="fullname" class="form-label" id="fullnameLabel">Full Name <span style="color: #4169E1;">*</span></label>
                                        <input type="text" class="form-control" id="fullname" name="fullname" value="<?php echo htmlspecialchars($_POST['fullname'] ?? ''); ?>" placeholder="Enter full name or entity name" required>
                                        <small class="form-text text-muted" id="fullnameHelp">Only letters, spaces, apostrophes, and hyphens are allowed</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="dateofbirth" class="form-label" id="dateofbirthLabel">Date of Birth <span style="color: #4169E1;">*</span></label>
                                        <input type="date" class="form-control" id="dateofbirth" name="dateofbirth" value="<?php echo htmlspecialchars($_POST['dateofbirth'] ?? ''); ?>" required>
                                        <small class="form-text text-muted" id="dateofbirthHelp"></small>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="pancardno" class="form-label">PAN Number <span style="color: #4169E1;">*</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="pancardno" name="pancardno" value="<?php echo htmlspecialchars($_POST['pancardno'] ?? ''); ?>" placeholder="ABCDE1234F" maxlength="10" required>
                                        <button type="button" class="btn btn-outline-primary" id="verifyPanBtn" onclick="verifyPan()">Verify PAN</button>
                                    </div>
                                    <small class="form-text text-muted">Format: ABCDE1234F (5 letters, 4 digits, 1 letter)</small>
                                    <div id="panVerificationStatus" class="mt-2" style="display: none;"></div>
                                </div>
                            </div>
                            
                            <!-- Email and Mobile Section -->
                            <div class="mb-4" style="border-top: 2px solid #e9ecef; padding-top: 25px;">
                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <label for="email" class="form-label">Email Address <span style="color: #4169E1;">*</span></label>
                                        <div class="input-group">
                                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                            <button type="button" class="btn btn-outline-primary" id="getEmailOtpBtn" onclick="getEmailOtp()">Get OTP</button>
                                        </div>
                                        <div id="emailOtpSection" style="display: none;" class="mt-2">
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="email_otp" placeholder="Enter 6-digit OTP" maxlength="6">
                                                <button type="button" class="btn btn-success" onclick="verifyEmailOtp()">Verify</button>
                                            </div>
                                            <small class="form-text text-muted" id="emailOtpStatus"></small>
                                        </div>
                                        <div id="emailVerificationStatus" class="mt-2" style="display: none;"></div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="mobile" class="form-label">Mobile Number <span style="color: #4169E1;">*</span></label>
                                        <div class="input-group">
                                            <input type="tel" class="form-control" id="mobile" name="mobile" value="<?php echo htmlspecialchars($_POST['mobile'] ?? ''); ?>" placeholder="10-digit mobile number" maxlength="10" required>
                                            <button type="button" class="btn btn-outline-primary" id="getMobileOtpBtn" onclick="getMobileOtp()">Get OTP</button>
                                        </div>
                                        <div id="mobileOtpSection" style="display: none;" class="mt-2">
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="mobile_otp" placeholder="Enter 6-digit OTP" maxlength="6">
                                                <button type="button" class="btn btn-success" onclick="verifyMobileOtp()">Verify</button>
                                            </div>
                                            <small class="form-text text-muted" id="mobileOtpStatus"></small>
                                        </div>
                                        <div id="mobileVerificationStatus" class="mt-2" style="display: none;"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Declaration -->
                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="declaration" name="declaration" value="1" required>
                                    <label class="form-check-label" for="declaration">
                                        <strong>I hereby declare and authorize</strong> to collect, process, store, and use the information provided in this registration form for the purpose of verification, authentication, and service delivery. I confirm that all the information provided is true, accurate, and complete to the best of my knowledge. <span style="color: #4169E1;">*</span>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Submit Button -->
                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Register</button>
                                <!-- <a href="#" class="btn btn-secondary" onclick="window.location.reload()">Cancel</a> -->
                            </div>
                            
                            <div id="verificationWarning" class="alert alert-warning" style="display: none;">
                                Please complete all required fields and verifications before submitting the form.
                            </div>
                        </form>
                        <br>
                        <div class="links">
                            Already have an account? <a href="login.php">Login here</a><br>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let emailVerified = false;
        let mobileVerified = false;
        let panVerified = false;
        let registrationType = 'entity';
        
        // Registration Type Change Handler
        document.querySelectorAll('input[name="registration_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                registrationType = this.value;
                updateLabelsBasedOnRegistrationType();
            });
        });
        
        function updateLabelsBasedOnRegistrationType() {
            const fullnameLabel = document.getElementById('fullnameLabel');
            const fullnameInput = document.getElementById('fullname');
            const fullnameHelp = document.getElementById('fullnameHelp');
            const dateofbirthLabel = document.getElementById('dateofbirthLabel');
            const dateofbirthHelp = document.getElementById('dateofbirthHelp');
            
            if (registrationType === 'individual') {
                fullnameLabel.innerHTML = 'Full Name <span style="color: #4169E1;">*</span>';
                fullnameInput.placeholder = 'Enter your full name';
                fullnameHelp.textContent = 'Only letters, spaces, apostrophes (\'), and hyphens (-) are allowed';
                dateofbirthLabel.innerHTML = 'Date of Birth <span style="color: #4169E1;">*</span>';
                dateofbirthHelp.textContent = 'Enter your date of birth';
            } else {
                fullnameLabel.innerHTML = 'Entity Name <span style="color: #4169E1;">*</span>';
                fullnameInput.placeholder = 'Enter entity/company name';
                fullnameHelp.textContent = 'Only letters, spaces, apostrophes (\'), and hyphens (-) are allowed';
                dateofbirthLabel.innerHTML = 'Date of Incorporation <span style="color: #4169E1;">*</span>';
                dateofbirthHelp.textContent = 'Enter the date of incorporation';
            }
        }
        
        // Initialize
        const selectedType = document.querySelector('input[name="registration_type"]:checked');
        if (selectedType) {
            registrationType = selectedType.value;
            updateLabelsBasedOnRegistrationType();
        }
        
        // PAN Card format validation
        document.getElementById('pancardno').addEventListener('input', function(e) {
            let value = e.target.value.toUpperCase();
            value = value.replace(/[^A-Z0-9]/g, '');
            e.target.value = value;
        });
        
        // Full name validation
        document.getElementById('fullname').addEventListener('input', function(e) {
            let value = e.target.value;
            value = value.replace(/[^a-zA-Z\s'-]/g, '');
            e.target.value = value;
            checkAllValidations();
        });
        
        // Mobile number validation
        document.getElementById('mobile').addEventListener('input', function(e) {
            let value = e.target.value;
            value = value.replace(/\D/g, '');
            e.target.value = value;
        });
        
        // OTP input validation
        document.getElementById('email_otp')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            e.target.value = value;
        });
        
        document.getElementById('mobile_otp')?.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            e.target.value = value;
        });
        
        // Verify PAN
        function verifyPan() {
            const panNo = document.getElementById('pancardno').value;
            const fullName = document.getElementById('fullname').value;
            const dob = document.getElementById('dateofbirth').value;
            
            if (!fullName) {
                alert('Please enter your ' + (registrationType === 'individual' ? 'Full Name' : 'Entity Name') + ' first.');
                return;
            }
            
            if (!dob) {
                alert('Please enter your ' + (registrationType === 'individual' ? 'Date of Birth' : 'Date of Incorporation') + ' first.');
                return;
            }
            
            if (!panNo) {
                alert('Please enter your PAN Number first.');
                return;
            }
            
            const panRegex = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;
            if (!panRegex.test(panNo)) {
                alert('Please enter a valid PAN Number. Format: ABCDE1234F');
                return;
            }
            
            const btn = document.getElementById('verifyPanBtn');
            const statusDiv = document.getElementById('panVerificationStatus');
            
            btn.disabled = true;
            btn.textContent = 'Verifying...';
            statusDiv.style.display = 'block';
            statusDiv.innerHTML = '<small class="text-info">Verifying PAN... Please wait...</small>';
            
            const formData = new FormData();
            formData.append('action', 'verify_pan');
            formData.append('pancardno', panNo);
            formData.append('fullname', fullName);
            formData.append('dateofbirth', dob);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.request_id) {
                    // Task created, now poll for status
                    statusDiv.innerHTML = '<small class="text-info">Verification initiated. Checking status...</small>';
                    checkPanStatus(data.request_id, panNo, fullName, dob, btn, statusDiv);
                } else {
                    const errorMsg = data.message || 'PAN verification failed. Please try again.';
                    statusDiv.innerHTML = '<small style="color: #dc3545;"><strong>✗ ' + errorMsg + '</strong></small>';
                    btn.disabled = false;
                    btn.textContent = 'Verify PAN';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                statusDiv.innerHTML = '<small style="color: #dc3545;"><strong>✗ An error occurred: ' + error.message + '. Please try again.</strong></small>';
                btn.disabled = false;
                btn.textContent = 'Verify PAN';
            });
        }
        
        // Function to poll PAN verification status
        function checkPanStatus(requestId, panNo, fullName, dob, btn, statusDiv) {
            let pollCount = 0;
            const maxPolls = 30; // Maximum 30 polls (about 60-90 seconds)
            const pollInterval = 2000; // Poll every 2 seconds
            
            const pollStatus = () => {
                pollCount++;
                
                if (pollCount > maxPolls) {
                    statusDiv.innerHTML = '<small style="color: #dc3545;"><strong>✗ Verification timeout. Please try again.</strong></small>';
                    btn.disabled = false;
                    btn.textContent = 'Verify PAN';
                    return;
                }
                
                const formData = new FormData();
                formData.append('action', 'check_pan_status');
                formData.append('request_id', requestId);
                formData.append('pancardno', panNo);
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'completed') {
                        if (data.verified === true) {
                            // Verification successful
                            panVerified = true;
                            const panInput = document.getElementById('pancardno');
                            const fullnameInput = document.getElementById('fullname');
                            const dobInput = document.getElementById('dateofbirth');
                            
                            panInput.readOnly = true;
                            panInput.style.backgroundColor = '#d4edda';
                            panInput.style.borderColor = '#28a745';
                            
                            fullnameInput.readOnly = true;
                            fullnameInput.style.backgroundColor = '#d4edda';
                            fullnameInput.style.borderColor = '#28a745';
                            
                            dobInput.readOnly = true;
                            dobInput.style.backgroundColor = '#d4edda';
                            dobInput.style.borderColor = '#28a745';
                            
                            btn.disabled = true;
                            btn.textContent = 'Verified';
                            btn.classList.remove('btn-outline-primary');
                            btn.classList.add('btn-success');
                            statusDiv.innerHTML = '<small class="text-success"><strong>✓ PAN verified successfully!</strong></small>';
                            checkAllValidations();
                        } else {
                            // Verification failed (PAN invalid or details don't match)
                            const errorMsg = data.message || 'PAN verification failed.';
                            statusDiv.innerHTML = '<small style="color: #dc3545;"><strong>✗ ' + errorMsg + '</strong></small>';
                            btn.disabled = false;
                            btn.textContent = 'Verify PAN';
                        }
                    } else if (data.status === 'failed') {
                        const errorMsg = data.message || 'PAN verification failed.';
                        statusDiv.innerHTML = '<small style="color: #dc3545;"><strong>✗ ' + errorMsg + '</strong></small>';
                        btn.disabled = false;
                        btn.textContent = 'Verify PAN';
                    } else {
                        // Still processing, continue polling
                        statusDiv.innerHTML = '<small class="text-info">' + (data.message || 'Verification in progress... Please wait.') + '</small>';
                        setTimeout(pollStatus, pollInterval);
                    }
                })
                .catch(error => {
                    console.error('Error checking status:', error);
                    statusDiv.innerHTML = '<small style="color: #dc3545;"><strong>✗ Error checking status. Please try again.</strong></small>';
                    btn.disabled = false;
                    btn.textContent = 'Verify PAN';
                });
            };
            
            // Start polling
            setTimeout(pollStatus, pollInterval);
        }
        
        // Get Email OTP
        function getEmailOtp() {
            const email = document.getElementById('email').value;
            
            if (!email) {
                alert('Please enter your email address first.');
                return;
            }
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Please enter a valid email address.');
                return;
            }
            
            const btn = document.getElementById('getEmailOtpBtn');
            btn.disabled = true;
            btn.textContent = 'Sending...';
            
            const formData = new FormData();
            formData.append('action', 'send_email_otp');
            formData.append('email', email);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    document.getElementById('emailOtpSection').style.display = 'block';
                    let otpMessage = 'OTP sent to your email. Please check and enter it.';
                    
                    // Show OTP in debug mode
                    if (data.otp) {
                        otpMessage += ' (Dev Mode OTP: ' + data.otp + ')';
                    }
                    
                    // Warn if email wasn't actually sent
                    if (data.email_sent === false) {
                        otpMessage += ' [Email sending failed - using debug mode OTP]';
                        document.getElementById('emailOtpStatus').className = 'form-text text-warning';
                    } else {
                        document.getElementById('emailOtpStatus').className = 'form-text text-success';
                    }
                    
                    document.getElementById('emailOtpStatus').textContent = otpMessage;
                    
                    // Show debug info if available
                    if (data.debug_info) {
                        console.log('Email Debug Info:', data.debug_info);
                    }
                    
                    btn.disabled = false;
                    btn.textContent = 'Resend OTP';
                } else {
                    const errorMsg = data.message || 'Failed to send OTP. Please try again.';
                    
                    // Always show OTP section in debug mode
                    if (data.otp) {
                        document.getElementById('emailOtpSection').style.display = 'block';
                        let warningMsg = 'Email sending failed. OTP: ' + data.otp + ' (Debug Mode)';
                        if (data.debug_info) {
                            warningMsg += '\nSMTP Error: ' + (data.debug_info.smtp_error || 'None');
                            warningMsg += '\nMail Error: ' + (data.debug_info.mail_error || 'None');
                        }
                        document.getElementById('emailOtpStatus').textContent = warningMsg;
                        document.getElementById('emailOtpStatus').className = 'form-text text-warning';
                    } else {
                        // Show detailed error in alert
                        let alertMsg = errorMsg;
                        if (data.debug_info) {
                            alertMsg += '\n\nDebug Info:\n';
                            alertMsg += 'SMTP Error: ' + (data.debug_info.smtp_error || 'None') + '\n';
                            alertMsg += 'Mail Error: ' + (data.debug_info.mail_error || 'None');
                        }
                        alert(alertMsg);
                    }
                    btn.disabled = false;
                    btn.textContent = 'Get OTP';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred: ' + error.message + '. Please try again.');
                btn.disabled = false;
                btn.textContent = 'Get OTP';
            });
        }
        
        // Verify Email OTP
        function verifyEmailOtp() {
            const email = document.getElementById('email').value;
            const otp = document.getElementById('email_otp').value;
            
            if (!otp || otp.length !== 6) {
                alert('Please enter a valid 6-digit OTP.');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'verify_email_otp');
            formData.append('email', email);
            formData.append('otp', otp);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    emailVerified = true;
                    const emailInput = document.getElementById('email');
                    emailInput.readOnly = true;
                    emailInput.style.backgroundColor = '#d4edda';
                    emailInput.style.borderColor = '#28a745';
                    document.getElementById('getEmailOtpBtn').disabled = true;
                    document.getElementById('getEmailOtpBtn').textContent = 'Verified';
                    document.getElementById('getEmailOtpBtn').classList.remove('btn-outline-primary');
                    document.getElementById('getEmailOtpBtn').classList.add('btn-success');
                    document.getElementById('emailOtpSection').style.display = 'none';
                    const statusDiv = document.getElementById('emailVerificationStatus');
                    statusDiv.style.display = 'block';
                    statusDiv.innerHTML = '<small class="text-success"><strong>✓ Email verified successfully!</strong></small>';
                    checkAllValidations();
                } else {
                    document.getElementById('emailOtpStatus').textContent = data.message || 'Invalid OTP. Please try again.';
                    document.getElementById('emailOtpStatus').className = 'form-text';
                    document.getElementById('emailOtpStatus').style.color = '#4169E1';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
        
        // Get Mobile OTP
        function getMobileOtp() {
            const mobile = document.getElementById('mobile').value;
            
            if (!mobile) {
                alert('Please enter your mobile number first.');
                return;
            }
            
            if (mobile.length !== 10) {
                alert('Please enter a valid 10-digit mobile number.');
                return;
            }
            
            const btn = document.getElementById('getMobileOtpBtn');
            btn.disabled = true;
            btn.textContent = 'Sending...';
            
            const formData = new FormData();
            formData.append('action', 'send_mobile_otp');
            formData.append('mobile', mobile);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('mobileOtpSection').style.display = 'block';
                    document.getElementById('mobileOtpStatus').textContent = 'OTP sent to your mobile. Please check and enter it.' + (data.otp ? ' (Dev: ' + data.otp + ')' : '');
                    document.getElementById('mobileOtpStatus').className = 'form-text text-success';
                } else {
                    alert(data.message || 'Failed to send OTP. Please try again.');
                    btn.disabled = false;
                    btn.textContent = 'Get OTP';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                btn.disabled = false;
                btn.textContent = 'Get OTP';
            });
        }
        
        // Verify Mobile OTP
        function verifyMobileOtp() {
            const mobile = document.getElementById('mobile').value;
            const otp = document.getElementById('mobile_otp').value;
            
            if (!otp || otp.length !== 6) {
                alert('Please enter a valid 6-digit OTP.');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'verify_mobile_otp');
            formData.append('mobile', mobile);
            formData.append('otp', otp);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mobileVerified = true;
                    const mobileInput = document.getElementById('mobile');
                    mobileInput.readOnly = true;
                    mobileInput.style.backgroundColor = '#d4edda';
                    mobileInput.style.borderColor = '#28a745';
                    document.getElementById('getMobileOtpBtn').disabled = true;
                    document.getElementById('getMobileOtpBtn').textContent = 'Verified';
                    document.getElementById('getMobileOtpBtn').classList.remove('btn-outline-primary');
                    document.getElementById('getMobileOtpBtn').classList.add('btn-success');
                    document.getElementById('mobileOtpSection').style.display = 'none';
                    const statusDiv = document.getElementById('mobileVerificationStatus');
                    statusDiv.style.display = 'block';
                    statusDiv.innerHTML = '<small class="text-success"><strong>✓ Mobile verified successfully!</strong></small>';
                    checkAllValidations();
                } else {
                    document.getElementById('mobileOtpStatus').textContent = data.message || 'Invalid OTP. Please try again.';
                    document.getElementById('mobileOtpStatus').className = 'form-text';
                    document.getElementById('mobileOtpStatus').style.color = '#4169E1';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
        
        // Check all validations
        function checkAllValidations() {
            const panInput = document.getElementById('pancardno');
            const fullnameInput = document.getElementById('fullname');
            const emailInput = document.getElementById('email');
            const mobileInput = document.getElementById('mobile');
            const dobInput = document.getElementById('dateofbirth');
            const declarationCheckbox = document.getElementById('declaration');
            
            const panRegex = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;
            const panValid = panVerified && panRegex.test(panInput.value);
            
            const nameRegex = /^[a-zA-Z\s'-]+$/;
            const nameValid = fullnameInput.value.trim().length > 0 && nameRegex.test(fullnameInput.value);
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const emailValid = emailVerified && emailRegex.test(emailInput.value);
            
            const mobileRegex = /^[0-9]{10}$/;
            const mobileValid = mobileVerified && mobileRegex.test(mobileInput.value);
            
            const dobValid = dobInput.value && new Date(dobInput.value) < new Date();
            const declarationValid = declarationCheckbox && declarationCheckbox.checked;
            
            const allValid = panValid && nameValid && emailValid && mobileValid && dobValid && declarationValid;
            
            if (allValid) {
                document.getElementById('submitBtn').disabled = false;
                document.getElementById('verificationWarning').style.display = 'none';
            } else {
                document.getElementById('submitBtn').disabled = true;
                let warningMsg = 'Please complete all required fields:';
                if (!panValid) warningMsg += '<br>- Verify PAN';
                if (!nameValid) warningMsg += '<br>- Enter valid Full Name / Entity Name';
                if (!emailValid) warningMsg += '<br>- Verify Email';
                if (!mobileValid) warningMsg += '<br>- Verify Mobile';
                if (!dobValid) warningMsg += '<br>- Enter Date of Birth / Date of Incorporation';
                if (!declarationValid) warningMsg += '<br>- Accept the declaration and authorization';
                document.getElementById('verificationWarning').innerHTML = warningMsg;
                document.getElementById('verificationWarning').style.display = 'block';
            }
        }
        
        // Add event listeners
        document.getElementById('dateofbirth').addEventListener('change', checkAllValidations);
        document.getElementById('declaration').addEventListener('change', checkAllValidations);
        
        // Prevent form submission if not all validations pass
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            if (!panVerified || !emailVerified || !mobileVerified || !document.getElementById('declaration').checked) {
                e.preventDefault();
                alert('Please complete all required fields, verifications, and accept the declaration before submitting.');
                checkAllValidations();
                return false;
            }
        });
        
        // Initial validation check
        checkAllValidations();
    </script>
</body>
</html>

