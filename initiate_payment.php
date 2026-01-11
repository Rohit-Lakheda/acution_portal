<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'payu.php';
require_once 'functions.php';

requireLogin();

// Get auction ID from request
$auctionId = isset($_GET['auction_id']) ? (int)$_GET['auction_id'] : 0;

if (!$auctionId) {
    header('Location: user_won_auctions.php?error=invalid_auction');
    exit;
}

// Get auction details
$stmt = $pdo->prepare("SELECT * FROM auctions WHERE id = ? AND winner_user_id = ? AND status = 'closed'");
$stmt->execute([$auctionId, getCurrentUserId()]);
$auction = $stmt->fetch();

if (!$auction) {
    header('Location: user_won_auctions.php?error=auction_not_found');
    exit;
}

// Check if already paid
if ($auction['payment_status'] === 'paid') {
    header('Location: user_won_auctions.php?error=already_paid');
    exit;
}

// Get user details - try registration table first, then users table
$userId = getCurrentUserId();
$user = null;

// First try to get from registration table (has more details like mobile)
$stmt = $pdo->prepare("SELECT r.* FROM registration r 
                       INNER JOIN users u ON r.email = u.email 
                       WHERE u.id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// If not found in registration table, get from users table
if (!$user) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch();
    if ($userData) {
        $user = [
            'full_name' => $userData['name'],
            'email' => $userData['email'],
            'mobile' => '9999999999' // Default if not available
        ];
    }
}

if (!$user) {
    header('Location: user_won_auctions.php?error=user_not_found');
    exit;
}

// Generate unique transaction ID
$transactionId = 'TXN' . time() . rand(1000, 9999);

// Save essential session data to cookies before redirecting to PayU
// This is needed because PayU redirect will clear the session
$cookieExpiry = time() + 3600; // 1 hour
setcookie('payment_user_id', $_SESSION['user_id'] ?? '', $cookieExpiry, '/', '', false, true);
setcookie('payment_user_name', $_SESSION['name'] ?? '', $cookieExpiry, '/', '', false, true);
setcookie('payment_user_email', $_SESSION['email'] ?? '', $cookieExpiry, '/', '', false, true);
setcookie('payment_user_role', $_SESSION['role'] ?? 'user', $cookieExpiry, '/', '', false, true);
setcookie('payment_transaction_id', $transactionId, $cookieExpiry, '/', '', false, true);
setcookie('payment_auction_id', $auctionId, $cookieExpiry, '/', '', false, true);

// Store payment transaction in database
try {
    // Create payment_transactions table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_id VARCHAR(100) UNIQUE NOT NULL,
        auction_id INT NOT NULL,
        user_id INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status VARCHAR(50) DEFAULT 'pending',
        payu_transaction_id VARCHAR(100) NULL,
        response_message TEXT NULL,
        response_data TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_transaction_id (transaction_id),
        INDEX idx_auction_id (auction_id),
        INDEX idx_user_id (user_id)
    )");
    
    // Add response_message and response_data columns if they don't exist
    try {
        $pdo->exec("ALTER TABLE payment_transactions ADD COLUMN response_message TEXT NULL");
    } catch (PDOException $e) {
        // Column might already exist, ignore error
    }
    try {
        $pdo->exec("ALTER TABLE payment_transactions ADD COLUMN response_data TEXT NULL");
    } catch (PDOException $e) {
        // Column might already exist, ignore error
    }
    
    // Insert payment transaction
    $stmt = $pdo->prepare("INSERT INTO payment_transactions (transaction_id, auction_id, user_id, amount, status) 
                          VALUES (?, ?, ?, ?, 'pending')");
    $stmt->execute([$transactionId, $auctionId, $userId, $auction['final_price']]);
} catch (PDOException $e) {
    error_log("Payment transaction creation error: " . $e->getMessage());
}

// Get base URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . '://' . $host . dirname($_SERVER['PHP_SELF']);

// Prepare payment data
$fullName = $user['full_name'] ?? $user['fullname'] ?? 'User';
$userEmail = $user['email'] ?? '';
$userMobile = $user['mobile'] ?? '9999999999';

$paymentData = preparePayuPaymentData([
    'transaction_id' => $transactionId,
    'amount' => $auction['final_price'],
    'product_info' => 'Auction Payment - ' . htmlspecialchars($auction['title']),
    'firstname' => $fullName,
    'email' => $userEmail,
    'phone' => $userMobile,
    'success_url' => $baseUrl . '/successuser.php',
    'failure_url' => $baseUrl . '/failuser.php',
    'udf1' => 'AUCTION_' . $auctionId,
    'udf2' => (string)$userId,
]);

$paymentUrl = getPayuPaymentUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting to Payment Gateway...</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f5f5f5;
        }
        .loader {
            text-align: center;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #000;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="loader">
        <div class="spinner"></div>
        <p>Redirecting to payment gateway...</p>
        <p style="font-size: 12px; color: #666;">Please do not close this page</p>
    </div>
    
    <form id="payuForm" method="POST" action="<?php echo htmlspecialchars($paymentUrl); ?>">
        <?php foreach ($paymentData as $key => $value): ?>
            <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
        <?php endforeach; ?>
    </form>
    
    <script>
        // Auto-submit form
        document.getElementById('payuForm').submit();
    </script>
</body>
</html>

