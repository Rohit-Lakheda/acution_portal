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

// Verify hash
$hashVerified = verifyPayuHash($payuResponse);

// Get transaction ID
$txnid = $payuResponse['txnid'] ?? '';
$status = $payuResponse['status'] ?? '';
$mihpayid = $payuResponse['mihpayid'] ?? '';

// Get auction ID from UDF1
$udf1 = $payuResponse['udf1'] ?? '';
$auctionId = 0;
if (preg_match('/AUCTION_(\d+)/', $udf1, $matches)) {
    $auctionId = (int)$matches[1];
}

// Get user ID from UDF2
$userId = isset($payuResponse['udf2']) ? (int)$payuResponse['udf2'] : getCurrentUserId();

if (!$hashVerified) {
    header('Location: user_won_auctions.php?error=hash_verification_failed');
    exit;
}

if ($status !== 'success') {
    header('Location: user_won_auctions.php?error=payment_failed&status=' . urlencode($status));
    exit;
}

// Prepare response message
$responseMessage = 'Payment successful. Transaction ID: ' . $mihpayid;
if (isset($payuResponse['bank_ref_num']) && !empty($payuResponse['bank_ref_num'])) {
    $responseMessage .= ', Bank Reference: ' . $payuResponse['bank_ref_num'];
}
if (isset($payuResponse['bankcode']) && !empty($payuResponse['bankcode'])) {
    $responseMessage .= ', Payment Mode: ' . $payuResponse['bankcode'];
}

// Store full response data as JSON
$responseDataJson = json_encode($payuResponse);

// Update payment transaction
try {
    $pdo->beginTransaction();
    
    // First, verify the auction exists and belongs to the user
    if ($auctionId > 0) {
        $checkStmt = $pdo->prepare("SELECT id, payment_status FROM auctions 
                                   WHERE id = ? AND winner_user_id = ? AND status = 'closed'");
        $checkStmt->execute([$auctionId, $userId]);
        $auction = $checkStmt->fetch();
        
        if (!$auction) {
            throw new Exception('Auction not found or you are not the winner. Auction ID: ' . $auctionId . ', User ID: ' . $userId);
        }
        
        if ($auction['payment_status'] === 'paid') {
            throw new Exception('This auction has already been paid.');
        }
    }
    
    // Update payment transaction with response message and data
    $stmt = $pdo->prepare("UPDATE payment_transactions 
                          SET status = 'success', 
                              payu_transaction_id = ?,
                              response_message = ?,
                              response_data = ?,
                              updated_at = NOW()
                          WHERE transaction_id = ?");
    $stmt->execute([$mihpayid, $responseMessage, $responseDataJson, $txnid]);
    
    $paymentTransactionUpdated = $stmt->rowCount();
    
    // Update auction payment status
    if ($auctionId > 0) {
        // Try to update with payment_date first, if column doesn't exist, update without it
        try {
            $stmt = $pdo->prepare("UPDATE auctions 
                                  SET payment_status = 'paid',
                                      payment_date = NOW()
                                  WHERE id = ? AND winner_user_id = ?");
            $stmt->execute([$auctionId, $userId]);
        } catch (PDOException $e) {
            // If payment_date column doesn't exist, update without it
            if (strpos($e->getMessage(), 'payment_date') !== false || strpos($e->getMessage(), 'Unknown column') !== false) {
                $stmt = $pdo->prepare("UPDATE auctions 
                                      SET payment_status = 'paid'
                                      WHERE id = ? AND winner_user_id = ?");
                $stmt->execute([$auctionId, $userId]);
            } else {
                throw $e; // Re-throw if it's a different error
            }
        }
        
        $auctionUpdated = $stmt->rowCount();
        
        if ($auctionUpdated === 0) {
            throw new Exception('Failed to update auction payment status. No rows affected. Auction ID: ' . $auctionId . ', User ID: ' . $userId);
        }
    }
    
    $pdo->commit();
    
    // Clear payment cookies
    setcookie('payment_user_id', '', time() - 3600, '/');
    setcookie('payment_user_name', '', time() - 3600, '/');
    setcookie('payment_user_email', '', time() - 3600, '/');
    setcookie('payment_user_role', '', time() - 3600, '/');
    setcookie('payment_transaction_id', '', time() - 3600, '/');
    setcookie('payment_auction_id', '', time() - 3600, '/');
    
    // Redirect to success page
    header('Location: user_won_auctions.php?payment=success&auction_id=' . $auctionId);
    exit;
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Payment success update error: " . $e->getMessage());
    error_log("Payment success update error - Auction ID: " . $auctionId . ", User ID: " . $userId . ", Transaction ID: " . $txnid);
    
    header('Location: user_won_auctions.php?error=payment_update_failed');
    exit;
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Payment success update PDO error: " . $e->getMessage());
    error_log("Payment success update PDO error - Auction ID: " . $auctionId . ", User ID: " . $userId . ", Transaction ID: " . $txnid);
    
    header('Location: user_won_auctions.php?error=payment_update_failed');
    exit;
}
?>

