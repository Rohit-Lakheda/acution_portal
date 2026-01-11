<?php
/**
 * Initiate PayU Payment for Registration
 * This file handles payment initiation after registration form validation
 */

session_start();
require_once 'database.php';
require_once 'payu.php';
require_once 'functions.php';
require_once 'logger.php';

// Check if registration data exists in session
if (!isset($_SESSION['pending_registration'])) {
    logMessage('registration_error', 'No registration data in session', null, null, null);
    header('Location: register.php?error=no_registration_data');
    exit;
}

$registrationData = $_SESSION['pending_registration'];

try {
    // Get registration amount from settings
    $registrationAmount = getRegistrationAmount($pdo);
    
    // Generate unique transaction ID
    $transactionId = 'REG_' . time() . '_' . uniqid();
    
    // Generate registration ID
    $registrationId = generateRegistrationId($pdo);
    
    // Get PAN verification data from session if available
    $panVerificationData = null;
    $panNo = $registrationData['pan_card_number'];
    $panKey = md5($panNo);
    if (isset($_SESSION['pan_verification_data_' . $panKey])) {
        $panVerificationData = json_encode($_SESSION['pan_verification_data_' . $panKey]);
    }
    
    // Store registration data in database (expires in 24 hours)
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $stmt = $pdo->prepare("INSERT INTO pending_registrations 
        (transaction_id, registration_id, registration_type, full_name, date_of_birth, 
         pan_card_number, email, mobile, pan_verification_data, expires_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $transactionId,
        $registrationId,
        $registrationData['registration_type'],
        $registrationData['full_name'],
        $registrationData['date_of_birth'],
        $panNo,
        $registrationData['email'],
        $registrationData['mobile'],
        $panVerificationData,
        $expiresAt
    ]);
    
    logMessage('payment_initiated', 'Payment initiated for registration', [
        'transaction_id' => $transactionId,
        'registration_id' => $registrationId,
        'email' => $registrationData['email'],
        'amount' => $registrationAmount
    ], $transactionId, $registrationId);
    
    // Insert payment transaction record
    $stmt = $pdo->prepare("INSERT INTO registration_payments (transaction_id, registration_id, amount, status) 
                          VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$transactionId, $registrationId, $registrationAmount]);
    
    // Get base URL - use live domain
    $baseUrl = 'https://interlinxpartnering.com/auction-portal_new/auction-portal';
    
    // Prepare payment data
    $paymentData = preparePayuPaymentData([
        'transaction_id' => $transactionId,
        'amount' => $registrationAmount,
        'product_info' => 'Registration Fee - ' . $registrationId,
        'firstname' => $registrationData['full_name'],
        'email' => $registrationData['email'],
        'phone' => $registrationData['mobile'],
        'success_url' => $baseUrl . '/payu_success.php',
        'failure_url' => $baseUrl . '/payu_failure.php',
        'udf1' => 'REGISTRATION',
        'udf2' => $registrationId,
        'udf3' => $transactionId,
    ]);
    
    $paymentUrl = getPayuPaymentUrl();
    
} catch (Exception $e) {
    error_log("Registration payment initiation error: " . $e->getMessage());
    header('Location: register.php?error=payment_initiation_failed');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Varela+Round&display=swap" rel="stylesheet">
    <title>Redirecting to Payment Gateway...</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Varela Round', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        .logo-header {
            margin-bottom: 35px;
            text-align: center;
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
        .container {
            text-align: center;
            background: white;
            padding: 45px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            max-width: 520px;
            width: 100%;
            border: 1px solid #e9ecef;
        }
        .container h2 {
            color: #1a237e;
            font-size: 24px;
            font-weight: 400;
            margin-bottom: 15px;
            letter-spacing: 0.3px;
        }
        .container p {
            color: #6c757d;
            font-size: 15px;
            margin-bottom: 10px;
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #1a237e;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            animation: spin 1s linear infinite;
            margin: 25px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        button {
            margin-top: 25px;
            padding: 14px 28px;
            background: #1a237e;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-family: 'Varela Round', sans-serif;
            font-size: 16px;
            font-weight: 400;
            letter-spacing: 0.3px;
            transition: all 0.3s;
        }
        button:hover {
            background: #283593;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(26, 35, 126, 0.3);
        }
        @media (max-width: 480px) {
            .logo-header img { height: 50px; }
            .logo-header h2 { font-size: 22px; }
            .container { padding: 35px 25px; }
        }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="logo-header">
        <img src="images/nixi_logo1.jpg" alt="NIXI Logo">
        <h2>🎯 Auction Portal</h2>
    </div>
    <div class="container">
        <h2>Redirecting to Payment Gateway...</h2>
        <p>Please wait while we redirect you to complete your payment.</p>
        <div class="spinner"></div>
        <p><small>If you are not redirected automatically, please click the button below.</small></p>
        
        <form method="POST" action="<?php echo htmlspecialchars($paymentUrl); ?>" id="paymentForm">
            <?php foreach ($paymentData as $key => $value): ?>
                <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
            <?php endforeach; ?>
            <button type="submit" style="margin-top: 20px; padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 5px; cursor: pointer;">
                Proceed to Payment
            </button>
        </form>
    </div>
    
    <script>
        // Auto-submit form after 2 seconds
        setTimeout(function() {
            document.getElementById('paymentForm').submit();
        }, 2000);
    </script>
</body>
</html>

