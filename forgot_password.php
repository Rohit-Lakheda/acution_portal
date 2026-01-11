<?php
/**
 * Forgot Password Page
 * Sends OTP to email if email matches user table
 * Allows user to reset password using OTP verification
 */

session_start();
require_once 'database.php';

// Configuration
define('DEBUG_MODE', true); // Set to false in production
define('SMTP_HOST', 'smtp.elasticemail.com'); // ElasticEmail SMTP
define('SMTP_PORT', 587);
define('SMTP_USER', 'enquiry@interlinx.in'); // SMTP username
define('SMTP_PASS', 'C8774994D4A25847EDBFBFD9D7996DD93391'); // SMTP password
define('FROM_EMAIL', 'enquiry@interlinx.in');
define('FROM_NAME', 'Auction Portal');

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

$error = '';
$success = '';
$step = 'email'; // email, otp, reset
$userEmail = '';
$userId = null;

// Handle AJAX request for sending OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_otp') {
    header('Content-Type: application/json');
    
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
        exit;
    }
    
    try {
        // Check if email exists in users table
        $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Email address not found in our system.']);
            exit;
        }
        
        // Generate OTP
        $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Store in session
        $_SESSION['forgot_password_otp_' . md5($email)] = $otp;
        $_SESSION['forgot_password_otp_time_' . md5($email)] = time();
        $_SESSION['forgot_password_user_id'] = $user['id'];
        $_SESSION['forgot_password_email'] = $email;
        
        // Use modern email template
        require_once 'email_templates.php';
        
        $subject = 'Password Reset OTP - NIXI Auction Portal';
        $message = getPasswordResetOTPEmail($otp, $user['name']);
        
        // Plain text version
        $plainTextMessage = "Hello {$user['name']},\n\n" .
                           "We received a request to reset your password. Your OTP is: {$otp}\n\n" .
                           "This OTP is valid for 10 minutes.\n\n" .
                           "If you did not request a password reset, please ignore this email.";
        
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
            $responseMsg = $sent ? 'OTP sent to your email successfully.' : 'OTP generated (email sending failed in debug mode).';
            echo json_encode([
                'success' => true,
                'message' => $responseMsg,
                'email_sent' => $sent,
                'otp' => DEBUG_MODE ? $otp : null // Show OTP in debug mode even if email fails
            ]);
        } else {
            $errorMessage = 'Failed to send email. ';
            if (DEBUG_MODE && $errorMsg) {
                $errorMessage .= 'Error: ' . $errorMsg;
            } else {
                $errorMessage .= 'Please try again later or contact support.';
            }
            echo json_encode([
                'success' => false,
                'message' => $errorMessage,
                'otp' => DEBUG_MODE ? $otp : null // Show OTP in debug mode even if email fails
            ]);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
        if (DEBUG_MODE) {
            error_log('Forgot password error: ' . $e->getMessage());
        }
    }
    exit;
}

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_otp') {
    header('Content-Type: application/json');
    
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $otp = trim($_POST['otp'] ?? '');
    
    if (empty($otp)) {
        echo json_encode(['success' => false, 'message' => 'Please enter the OTP.']);
        exit;
    }
    
    $sessionKey = 'forgot_password_otp_' . md5($email);
    $timeKey = 'forgot_password_otp_time_' . md5($email);
    
    $storedOtp = $_SESSION[$sessionKey] ?? null;
    $otpTime = $_SESSION[$timeKey] ?? null;
    
    if (!$storedOtp || !$otpTime) {
        echo json_encode(['success' => false, 'message' => 'OTP not found. Please request a new OTP.']);
        exit;
    }
    
    // Check if OTP is expired (10 minutes)
    if (time() - $otpTime > 600) {
        unset($_SESSION[$sessionKey]);
        unset($_SESSION[$timeKey]);
        echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
        exit;
    }
    
    if ($storedOtp === $otp) {
        $_SESSION['forgot_password_verified'] = true;
        echo json_encode(['success' => true, 'message' => 'OTP verified successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
    }
    exit;
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {
    header('Content-Type: application/json');
    
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($newPassword)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a new password.']);
        exit;
    }
    
    if (strlen($newPassword) < 8) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
        exit;
    }
    
    if ($newPassword !== $confirmPassword) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }
    
    if (!isset($_SESSION['forgot_password_verified']) || !$_SESSION['forgot_password_verified']) {
        echo json_encode(['success' => false, 'message' => 'Please verify OTP first.']);
        exit;
    }
    
    $userId = $_SESSION['forgot_password_user_id'] ?? null;
    $sessionEmail = $_SESSION['forgot_password_email'] ?? '';
    
    if (!$userId || $sessionEmail !== $email) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please start over.']);
        exit;
    }
    
    try {
        // Get current user data and password
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND email = ?");
        $stmt->execute([$userId, $email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'User not found.']);
            exit;
        }
        
        $oldPassword = $user['password']; // Store old hashed password
        
        // Hash new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Update password in users table
        $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $updateStmt->execute([$hashedPassword, $userId]);
        
        // Check if record already exists in changed_password table for this user
        // First check if current password matches a new_password in existing record (password was previously changed)
        // If not found, check if there's any record for this user (update most recent one)
        $checkStmt = $pdo->prepare("SELECT record_id FROM changed_password WHERE id = ? AND new_password = ? ORDER BY changed_at DESC LIMIT 1");
        $checkStmt->execute([$userId, $oldPassword]);
        $existingRecord = $checkStmt->fetch();
        
        // If not found by new_password match, check for any record for this user
        if (!$existingRecord) {
            $checkStmt2 = $pdo->prepare("SELECT record_id FROM changed_password WHERE id = ? ORDER BY changed_at DESC LIMIT 1");
            $checkStmt2->execute([$userId]);
            $existingRecord = $checkStmt2->fetch();
        }
        
        if ($existingRecord) {
            // Update existing record - update the most recent record for this user
            $changedStmt = $pdo->prepare("UPDATE changed_password SET 
                name = ?, 
                email = ?, 
                password = ?, 
                role = ?, 
                created_at = ?, 
                password_reset_token = ?, 
                password_reset_expires = ?, 
                old_password = ?,
                new_password = ?, 
                changed_at = NOW() 
                WHERE record_id = ?");
            $updateResult = $changedStmt->execute([
                $user['name'],
                $user['email'],
                $hashedPassword, // new password in password column
                $user['role'],
                $user['created_at'],
                $user['password_reset_token'],
                $user['password_reset_expires'],
                $oldPassword, // old password (current password before update)
                $hashedPassword, // new password
                $existingRecord['record_id']
            ]);
        } else {
            // Insert new record into changed_password table
            $changedStmt = $pdo->prepare("INSERT INTO changed_password 
                (id, name, email, password, role, created_at, password_reset_token, password_reset_expires, old_password, new_password, changed_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $changedStmt->execute([
                $user['id'],
                $user['name'],
                $user['email'],
                $hashedPassword, // new password in password column
                $user['role'],
                $user['created_at'],
                $user['password_reset_token'],
                $user['password_reset_expires'],
                $oldPassword, // old password
                $hashedPassword // new password
            ]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Clear session
        unset($_SESSION['forgot_password_otp_' . md5($email)]);
        unset($_SESSION['forgot_password_otp_time_' . md5($email)]);
        unset($_SESSION['forgot_password_verified']);
        unset($_SESSION['forgot_password_user_id']);
        unset($_SESSION['forgot_password_email']);
        
        echo json_encode(['success' => true, 'message' => 'Password updated successfully! You can now login with your new password.']);
    } catch(PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Failed to update password. Please try again.']);
        if (DEBUG_MODE) {
            error_log('Password reset error: ' . $e->getMessage());
        }
    }
    exit;
}

// Get current step from session
if (isset($_SESSION['forgot_password_email'])) {
    $userEmail = $_SESSION['forgot_password_email'];
    if (isset($_SESSION['forgot_password_verified']) && $_SESSION['forgot_password_verified']) {
        $step = 'reset';
    } else {
        $step = 'otp';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Auction Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Varela+Round&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Varela Round', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #2c3e50;
        }
        .logo-header {
            margin-bottom: 35px;
            text-align: center;
            padding: 20px;
        }
        .logo-header img {
            height: 65px;
            width: auto;
            max-width: 200px;
            object-fit: contain;
            margin-bottom: 18px;
            background: white;
            padding: 5px;
            border-radius: 12px;
        }
        .logo-header h2 {
            color: #1a237e;
            font-size: 26px;
            font-weight: 400;
            letter-spacing: 0.5px;
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
        @media (max-width: 480px) {
            .logo-header img { height: 50px; }
            .logo-header h2 { font-size: 22px; }
        }
        .card {
            border-radius: 0.75rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
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
        .invalid-feedback {
            color: #4169E1 !important;
        }
        .form-control.is-invalid {
            border-color: #4169E1 !important;
        }
        .alert-danger {
            color: #4169E1 !important;
        }
        .otp-input {
            font-size: 24px;
            text-align: center;
            letter-spacing: 5px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="logo-header">
        <img src="images/nixi_logo1.jpg" alt="NIXI Logo">
        <h2>🎯 Auction Portal</h2>
    </div>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header">
                        <h4 class="mb-0">Forgot Password</h4>
                    </div>
                    <div class="card-body">
                        <div id="alertContainer"></div>
                        
                        <!-- Step 1: Email Input -->
                        <div id="emailStep" style="display: <?php echo $step === 'email' ? 'block' : 'none'; ?>;">
                            <p class="text-muted mb-4">Enter your email address and we'll send you an OTP to reset your password.</p>
                            <form id="emailForm">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address <span style="color: #4169E1;">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" required placeholder="Enter your email address" value="<?php echo htmlspecialchars($userEmail); ?>">
                                </div>
                                <div class="mb-3">
                                    <button type="submit" class="btn btn-primary w-100" id="sendOtpBtn">Send OTP</button>
                                </div>
                                <div class="text-center">
                                    <a href="login.php" class="text-muted">Back to Login</a>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Step 2: OTP Verification -->
                        <div id="otpStep" style="display: <?php echo $step === 'otp' ? 'block' : 'none'; ?>;">
                            <p class="text-muted mb-4">We've sent an OTP to <strong id="otpEmail"><?php echo htmlspecialchars($userEmail); ?></strong>. Please enter it below.</p>
                            <form id="otpForm">
                                <input type="hidden" id="otpEmailInput" name="email" value="<?php echo htmlspecialchars($userEmail); ?>">
                                <div class="mb-3">
                                    <label for="otp" class="form-label">Enter OTP <span style="color: #4169E1;">*</span></label>
                                    <input type="text" class="form-control otp-input" id="otp" name="otp" required maxlength="6" placeholder="000000" pattern="[0-9]{6}">
                                    <small class="form-text text-muted">OTP is valid for 10 minutes</small>
                                </div>
                                <div class="mb-3">
                                    <button type="submit" class="btn btn-primary w-100" id="verifyOtpBtn">Verify OTP</button>
                                </div>
                                <div class="text-center">
                                    <a href="javascript:void(0)" onclick="resendOtp()" class="text-muted">Resend OTP</a> | 
                                    <a href="login.php" class="text-muted">Back to Login</a>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Step 3: Reset Password -->
                        <div id="resetStep" style="display: <?php echo $step === 'reset' ? 'block' : 'none'; ?>;">
                            <p class="text-muted mb-4">Enter your new password below.</p>
                            <form id="resetForm">
                                <input type="hidden" id="resetEmailInput" name="email" value="<?php echo htmlspecialchars($userEmail); ?>">
                                <div class="mb-3">
                                    <label for="password" class="form-label">New Password <span style="color: #4169E1;">*</span></label>
                                    <input type="password" class="form-control" id="password" name="password" required minlength="8" placeholder="Enter new password (minimum 8 characters)">
                                    <small class="form-text text-muted">Password must be at least 8 characters long</small>
                                </div>
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password <span style="color: #4169E1;">*</span></label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8" placeholder="Confirm new password">
                                    <div id="passwordMatch" class="invalid-feedback" style="display: none;">
                                        Passwords do not match.
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <button type="submit" class="btn btn-primary w-100" id="resetPasswordBtn">Reset Password</button>
                                </div>
                                <div class="text-center">
                                    <a href="login.php" class="text-muted">Back to Login</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showAlert(message, type = 'danger') {
            const alertContainer = document.getElementById('alertContainer');
            alertContainer.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;
        }
        
        function hideAlert() {
            document.getElementById('alertContainer').innerHTML = '';
        }
        
        // Email form submission
        document.getElementById('emailForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            hideAlert();
            
            const email = document.getElementById('email').value;
            const btn = document.getElementById('sendOtpBtn');
            const originalText = btn.textContent;
            
            btn.disabled = true;
            btn.textContent = 'Sending...';
            
            try {
                const response = await fetch('forgot_password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=send_otp&email=${encodeURIComponent(email)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert(data.message, 'success');
                    document.getElementById('emailStep').style.display = 'none';
                    document.getElementById('otpStep').style.display = 'block';
                    document.getElementById('otpEmail').textContent = email;
                    document.getElementById('otpEmailInput').value = email;
                    document.getElementById('resetEmailInput').value = email;
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                showAlert('An error occurred. Please try again.', 'danger');
            } finally {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        });
        
        // OTP form submission
        document.getElementById('otpForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            hideAlert();
            
            const email = document.getElementById('otpEmailInput').value;
            const otp = document.getElementById('otp').value;
            const btn = document.getElementById('verifyOtpBtn');
            const originalText = btn.textContent;
            
            btn.disabled = true;
            btn.textContent = 'Verifying...';
            
            try {
                const response = await fetch('forgot_password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=verify_otp&email=${encodeURIComponent(email)}&otp=${encodeURIComponent(otp)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert(data.message, 'success');
                    document.getElementById('otpStep').style.display = 'none';
                    document.getElementById('resetStep').style.display = 'block';
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                showAlert('An error occurred. Please try again.', 'danger');
            } finally {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        });
        
        // Reset password form submission
        document.getElementById('resetForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            hideAlert();
            
            const email = document.getElementById('resetEmailInput').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                showAlert('Passwords do not match.', 'danger');
                return;
            }
            
            if (password.length < 8) {
                showAlert('Password must be at least 8 characters long.', 'danger');
                return;
            }
            
            const btn = document.getElementById('resetPasswordBtn');
            const originalText = btn.textContent;
            
            btn.disabled = true;
            btn.textContent = 'Resetting...';
            
            try {
                const response = await fetch('forgot_password.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=reset_password&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}&confirm_password=${encodeURIComponent(confirmPassword)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showAlert(data.message + ' Redirecting to login...', 'success');
                    setTimeout(() => {
                        window.location.href = 'login.php';
                    }, 2000);
                } else {
                    showAlert(data.message, 'danger');
                }
            } catch (error) {
                showAlert('An error occurred. Please try again.', 'danger');
            } finally {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        });
        
        // Resend OTP
        function resendOtp() {
            const email = document.getElementById('otpEmailInput').value;
            document.getElementById('email').value = email;
            document.getElementById('emailForm').dispatchEvent(new Event('submit'));
        }
        
        // Password match validation
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const passwordMatchDiv = document.getElementById('passwordMatch');
        
        function checkPasswordMatch() {
            if (confirmPasswordInput && passwordInput) {
                if (confirmPasswordInput.value && passwordInput.value !== confirmPasswordInput.value) {
                    confirmPasswordInput.classList.add('is-invalid');
                    passwordMatchDiv.style.display = 'block';
                } else {
                    confirmPasswordInput.classList.remove('is-invalid');
                    passwordMatchDiv.style.display = 'none';
                }
            }
        }
        
        if (confirmPasswordInput && passwordInput) {
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
            passwordInput.addEventListener('input', checkPasswordMatch);
        }
        
        // OTP input - only numbers
        document.getElementById('otp')?.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
        });
    </script>
</body>
</html>

