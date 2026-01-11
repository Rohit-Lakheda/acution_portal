<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'payu.php';
require_once 'functions.php';

// Restore session from cookies if session is lost (PayU redirect clears session)
if (!isset($_SESSION['user_id']) && isset($_COOKIE['payment_user_id'])) {
    $_SESSION['user_id'] = $_COOKIE['payment_user_id'];
    $_SESSION['name'] = $_COOKIE['payment_user_name'] ?? '';
    $_SESSION['email'] = $_COOKIE['payment_user_email'] ?? '';
    $_SESSION['role'] = $_COOKIE['payment_user_role'] ?? 'user';
}

// Get all parameters from PayU (both GET and POST)
$payuResponse = array_merge($_GET, $_POST);

// Get transaction ID
$txnid = $payuResponse['txnid'] ?? '';
$status = $payuResponse['status'] ?? '';
$errorMessage = $payuResponse['error_Message'] ?? $payuResponse['error'] ?? 'Payment failed';

// Get auction ID from UDF1 or cookie
$udf1 = $payuResponse['udf1'] ?? '';
$auctionId = 0;
if (preg_match('/AUCTION_(\d+)/', $udf1, $matches)) {
    $auctionId = (int)$matches[1];
} elseif (isset($_COOKIE['payment_auction_id'])) {
    $auctionId = (int)$_COOKIE['payment_auction_id'];
}

// Get user ID from UDF2 or cookie
$userId = isset($payuResponse['udf2']) ? (int)$payuResponse['udf2'] : null;
if (!$userId && isset($_COOKIE['payment_user_id'])) {
    $userId = (int)$_COOKIE['payment_user_id'];
}

// Prepare response message
$responseMessage = 'Payment failed. ';
if (!empty($errorMessage)) {
    $responseMessage .= 'Error: ' . $errorMessage;
}
if (isset($payuResponse['status']) && !empty($payuResponse['status'])) {
    $responseMessage .= ' Status: ' . $payuResponse['status'];
}

// Store full response data as JSON
$responseDataJson = json_encode($payuResponse);

// Update payment transaction status with response message
try {
    $stmt = $pdo->prepare("UPDATE payment_transactions 
                          SET status = 'failed',
                              response_message = ?,
                              response_data = ?,
                              updated_at = NOW()
                          WHERE transaction_id = ?");
    $stmt->execute([$responseMessage, $responseDataJson, $txnid]);
} catch (PDOException $e) {
    error_log("Payment failure update error: " . $e->getMessage());
}

// Clear payment cookies
setcookie('payment_user_id', '', time() - 3600, '/');
setcookie('payment_user_name', '', time() - 3600, '/');
setcookie('payment_user_email', '', time() - 3600, '/');
setcookie('payment_user_role', '', time() - 3600, '/');
setcookie('payment_transaction_id', '', time() - 3600, '/');
setcookie('payment_auction_id', '', time() - 3600, '/');

// Redirect to won auctions page with error
$errorParam = 'payment_failed';
if ($errorMessage) {
    $errorParam .= '&message=' . urlencode($errorMessage);
}
if ($auctionId > 0) {
    $errorParam .= '&auction_id=' . $auctionId;
}

header('Location: user_won_auctions.php?' . $errorParam);
exit;
?>

