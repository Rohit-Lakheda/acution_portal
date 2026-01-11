<?php
/**
 * PayU Failure Callback Handler for Registration
 * This file handles failed payment callback from PayU
 * CSRF token check is bypassed for payment callbacks
 */

session_start();
require_once 'database.php';
require_once 'payu.php';
require_once 'logger.php';

// Bypass CSRF token check for payment callbacks

// Get all parameters from PayU (both GET and POST)
$payuResponse = array_merge($_GET, $_POST);

// Get transaction details
$txnid = $payuResponse['txnid'] ?? '';
$status = $payuResponse['status'] ?? '';
$error = $payuResponse['error'] ?? 'Payment failed';
$errorMessage = $payuResponse['error_Message'] ?? 'Payment transaction failed';

// Get registration ID and transaction ID from UDF fields
$registrationId = $payuResponse['udf2'] ?? '';
$transactionId = $payuResponse['udf3'] ?? '';

// Store full response data as JSON
$responseDataJson = json_encode($payuResponse);

// Log the failure
logMessage('payment_failed', 'Payment failed callback received', [
    'status' => $status,
    'error' => $error,
    'error_message' => $errorMessage,
    'txnid' => $txnid
], $transactionId, $registrationId);

// Update payment transaction record
try {
    $stmt = $pdo->prepare("UPDATE registration_payments 
                          SET status = 'failed',
                              payu_transaction_id = ?,
                              payu_response = ?,
                              updated_at = CURRENT_TIMESTAMP
                          WHERE transaction_id = ?");
    $stmt->execute([$txnid, $responseDataJson, $transactionId]);
} catch(PDOException $e) {
    logMessage('registration_error', 'Failed to update payment record: ' . $e->getMessage(), null, $transactionId, $registrationId);
}

// Clear pending registration from session (optional - you may want to keep it for retry)
// unset($_SESSION['pending_registration']);

// Redirect to registration page with error
$errorMsg = urlencode($errorMessage ?: $error ?: 'Payment failed');
header('Location: register.php?error=payment_failed&msg=' . $errorMsg);
exit;
?>

