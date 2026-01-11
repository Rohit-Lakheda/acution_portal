<?php
/**
 * PayU Success Callback Handler for Registration
 * This file handles successful payment callback from PayU
 * CSRF token check is bypassed for payment callbacks
 */

session_start();
require_once 'database.php';
require_once 'payu.php';
require_once 'functions.php';
require_once 'logger.php';

// Bypass CSRF token check for payment callbacks
// This is safe because PayU verifies the hash

// Get all parameters from PayU (both GET and POST)
$payuResponse = array_merge($_GET, $_POST);

// Log the callback
logMessage('payment_callback', 'PayU callback received', $payuResponse);

// Verify hash
$hashVerified = verifyPayuHash($payuResponse);

// Get transaction details
$txnid = $payuResponse['txnid'] ?? '';
$status = $payuResponse['status'] ?? '';
$mihpayid = $payuResponse['mihpayid'] ?? '';
$amount = $payuResponse['amount'] ?? '';

// Get registration ID and transaction ID from UDF fields
$registrationId = $payuResponse['udf2'] ?? '';
$transactionId = $payuResponse['udf3'] ?? '';

// Store full response data as JSON
$responseDataJson = json_encode($payuResponse);

logMessage('payment_callback', 'Processing callback', [
    'txnid' => $txnid,
    'status' => $status,
    'transaction_id' => $transactionId,
    'registration_id' => $registrationId,
    'hash_verified' => $hashVerified
], $transactionId, $registrationId);

if (!$hashVerified) {
    // Hash verification failed - log and redirect
    logMessage('payment_failed', 'PayU Hash verification failed', [
        'txnid' => $txnid,
        'transaction_id' => $transactionId
    ], $transactionId, $registrationId);
    header('Location: register.php?error=payment_verification_failed');
    exit;
}

if ($status !== 'success') {
    // Payment failed - update payment record and redirect
    try {
        $stmt = $pdo->prepare("UPDATE registration_payments 
                              SET status = 'failed', 
                                  payu_transaction_id = ?,
                                  payu_response = ?,
                                  updated_at = CURRENT_TIMESTAMP
                              WHERE transaction_id = ?");
        $stmt->execute([$mihpayid, $responseDataJson, $transactionId]);
        
        logMessage('payment_failed', 'Payment failed - status: ' . $status, [
            'status' => $status,
            'mihpayid' => $mihpayid
        ], $transactionId, $registrationId);
    } catch(PDOException $e) {
        logMessage('registration_error', 'Failed to update payment record: ' . $e->getMessage(), null, $transactionId, $registrationId);
    }
    
    header('Location: register.php?error=payment_failed&status=' . urlencode($status));
    exit;
}

// Payment successful - retrieve registration data from database
try {
    $pdo->beginTransaction();
    
    // Retrieve registration data from database instead of session
    $stmt = $pdo->prepare("SELECT * FROM pending_registrations 
                          WHERE transaction_id = ? AND registration_id = ? 
                          AND expires_at > NOW()");
    $stmt->execute([$transactionId, $registrationId]);
    $pendingReg = $stmt->fetch();
    
    if (!$pendingReg) {
        logMessage('registration_error', 'Registration data not found in database', [
            'transaction_id' => $transactionId,
            'registration_id' => $registrationId
        ], $transactionId, $registrationId);
        throw new Exception('Registration data not found in database. Transaction ID: ' . $transactionId . ', Registration ID: ' . $registrationId);
    }
    
    // Convert database record to array format
    $registrationData = [
        'registration_type' => $pendingReg['registration_type'],
        'full_name' => $pendingReg['full_name'],
        'date_of_birth' => $pendingReg['date_of_birth'],
        'pan_card_number' => $pendingReg['pan_card_number'],
        'email' => $pendingReg['email'],
        'mobile' => $pendingReg['mobile'],
        'registration_id' => $pendingReg['registration_id'],
        'transaction_id' => $pendingReg['transaction_id']
    ];
    
    $panVerificationData = $pendingReg['pan_verification_data'];
    $panNo = $pendingReg['pan_card_number'];
    
    logMessage('payment_success', 'Payment successful, processing registration', [
        'transaction_id' => $transactionId,
        'registration_id' => $registrationId,
        'email' => $registrationData['email']
    ], $transactionId, $registrationId);
    
    // Check if email already exists in users table
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->execute([$registrationData['email']]);
    $existingUser = $checkStmt->fetch();
    
    if ($existingUser) {
        throw new Exception('Email address is already registered');
    }
    
    // Generate a secure temporary password
    $tempPassword = bin2hex(random_bytes(8)); // 16 character random password
    $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
    
    // Generate password reset token
    $resetToken = bin2hex(random_bytes(32));
    $resetExpires = date('Y-m-d H:i:s', strtotime('+7 days'));
    
    // Insert into registration table with payment details
    $regStmt = $pdo->prepare("INSERT INTO registration 
        (registration_id, registration_type, full_name, date_of_birth, pan_card_number, email, mobile, 
         pan_verification_data, payment_status, payment_transaction_id, payment_amount, payment_response, payment_date) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'success', ?, ?, ?, NOW())");
    $regStmt->execute([
        $registrationId,
        $registrationData['registration_type'],
        $registrationData['full_name'],
        $registrationData['date_of_birth'],
        $panNo,
        $registrationData['email'],
        $registrationData['mobile'],
        $panVerificationData,
        $transactionId,
        $amount,
        $responseDataJson
    ]);
    
    // Insert into users table
    $userStmt = $pdo->prepare("INSERT INTO users 
        (name, email, password, password_reset_token, password_reset_expires, role) 
        VALUES (?, ?, ?, ?, ?, 'user')");
    $userStmt->execute([
        $registrationData['full_name'],
        $registrationData['email'],
        $hashedPassword,
        $resetToken,
        $resetExpires
    ]);
    
    // Update payment transaction record
    $paymentStmt = $pdo->prepare("UPDATE registration_payments 
                                  SET status = 'success',
                                      payu_transaction_id = ?,
                                      payu_response = ?,
                                      updated_at = CURRENT_TIMESTAMP
                                  WHERE transaction_id = ?");
    $paymentStmt->execute([$mihpayid, $responseDataJson, $transactionId]);
    
    // Delete pending registration data (no longer needed)
    $deleteStmt = $pdo->prepare("DELETE FROM pending_registrations WHERE transaction_id = ?");
    $deleteStmt->execute([$transactionId]);
    
    // Commit transaction
    $pdo->commit();
    
    logMessage('registration_saved', 'Registration completed successfully', [
        'transaction_id' => $transactionId,
        'registration_id' => $registrationId,
        'email' => $registrationData['email']
    ], $transactionId, $registrationId);
    
    // Send email with credentials and password reset link
    require_once 'email_templates.php';
    
    // Email settings will be loaded from database via email_sender.php
    // No hardcoded defaults - must be configured in admin panel
    if (!function_exists('sendEmailViaSMTP')) {
        require_once 'email_sender.php';
    }
    
    $baseUrl = 'https://interlinxpartnering.com/auction-portal_new/auction-portal';
    $resetLink = rtrim($baseUrl, '/') . '/update_password.php?token=' . $resetToken;
    
    $subject = 'Welcome to NIXI Auction Portal - Registration Successful';
    $htmlMessage = getRegistrationSuccessEmail(
        $registrationData['full_name'],
        $registrationData['email'],
        $registrationId,
        $tempPassword,
        $resetLink
    );
    
    $plainTextMessage = "Welcome to NIXI Auction Portal!\n\n" .
                       "Your registration has been completed successfully.\n\n" .
                       "Registration ID: {$registrationId}\n" .
                       "Email: {$registrationData['email']}\n" .
                       "Temporary Password: {$tempPassword}\n\n" .
                       "Please update your password using this link: {$resetLink}\n\n" .
                       "This link is valid for 7 days.\n\n" .
                       "Thank you for joining us!";
    
    // Send email
    $emailSent = sendEmail($registrationData['email'], $subject, $htmlMessage, $plainTextMessage);
    
    if (!$emailSent) {
        logMessage('email_error', 'Failed to send registration success email', [
            'email' => $registrationData['email'],
            'registration_id' => $registrationId
        ], $transactionId, $registrationId);
    } else {
        logMessage('email_sent', 'Registration success email sent', [
            'email' => $registrationData['email'],
            'registration_id' => $registrationId
        ], $transactionId, $registrationId);
    }
    
    // Redirect to success page
    header('Location: registration_success.php?reg_id=' . urlencode($registrationId) . '&email=' . urlencode($registrationData['email']));
    exit;
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $errorMessage = $e->getMessage();
    logMessage('registration_error', 'Registration save error after payment: ' . $errorMessage, [
        'error' => $errorMessage,
        'transaction_id' => $transactionId,
        'registration_id' => $registrationId,
        'payu_response' => $payuResponse
    ], $transactionId, $registrationId);
    
    // Update payment status to failed
    try {
        $stmt = $pdo->prepare("UPDATE registration_payments 
                              SET status = 'failed',
                                  payu_response = ?,
                                  updated_at = CURRENT_TIMESTAMP
                              WHERE transaction_id = ?");
        $stmt->execute([$responseDataJson . ' | Error: ' . $errorMessage, $transactionId]);
    } catch(PDOException $ex) {
        logMessage('registration_error', 'Failed to update payment record: ' . $ex->getMessage(), null, $transactionId, $registrationId);
    }
    
    header('Location: register.php?error=registration_save_failed&msg=' . urlencode($errorMessage));
    exit;
}
?>

