<?php
/**
 * Registration Success Page
 * Displayed after successful payment and registration
 */

session_start();
require_once 'database.php';

$registrationId = $_GET['reg_id'] ?? '';
$email = $_GET['email'] ?? '';

if (empty($registrationId)) {
    header('Location: register.php');
    exit;
}

// Get registration details
try {
    $stmt = $pdo->prepare("SELECT * FROM registration WHERE registration_id = ?");
    $stmt->execute([$registrationId]);
    $registration = $stmt->fetch();
    
    if (!$registration) {
        header('Location: register.php');
        exit;
    }
} catch(PDOException $e) {
    header('Location: register.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Successful - Auction Portal</title>
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
            max-width: 700px;
            margin: 0 auto;
        }
        .success-card {
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            overflow: hidden;
        }
        .success-header {
            background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
            color: white;
            padding: 35px;
            text-align: center;
        }
        .success-header h1 {
            font-size: 28px;
            font-weight: 400;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .success-body {
            background: white;
            padding: 45px;
        }
        .success-body h2 {
            color: #1a237e;
            font-size: 24px;
            font-weight: 400;
            margin-bottom: 20px;
            letter-spacing: 0.3px;
        }
        .reg-id-box {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin: 25px 0;
            text-align: center;
            border: 2px solid #e9ecef;
        }
        .reg-id-box p {
            color: #6c757d;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .reg-id {
            font-size: 26px;
            font-weight: 400;
            color: #1a237e;
            font-family: 'Varela Round', monospace;
            letter-spacing: 1px;
        }
        .btn-primary {
            background-color: #1a237e;
            border-color: #1a237e;
            border-radius: 8px;
            padding: 12px 28px;
            font-family: 'Varela Round', sans-serif;
            font-size: 16px;
            font-weight: 400;
            letter-spacing: 0.3px;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background-color: #283593;
            border-color: #283593;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(26, 35, 126, 0.3);
        }
        .alert-info {
            background: #e3f2fd;
            color: #1565c0;
            border-left: 4px solid #1565c0;
            border-radius: 8px;
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
        <div class="card success-card">
            <div class="success-header">
                <h1 class="mb-0">✓ Registration Successful!</h1>
            </div>
            <div class="success-body">
                <h2 class="mb-4">Welcome, <?php echo htmlspecialchars($registration['full_name']); ?>!</h2>
                
                <p class="lead">Your registration has been completed successfully and payment has been received.</p>
                
                <div class="reg-id-box">
                    <p class="mb-2 text-muted">Your Registration ID:</p>
                    <div class="reg-id"><?php echo htmlspecialchars($registration['registration_id']); ?></div>
                </div>
                
                <div class="alert alert-info">
                    <strong>Important:</strong> Your login credentials have been sent to your email address: 
                    <strong><?php echo htmlspecialchars($registration['email']); ?></strong>
                </div>
                
                <p>Please check your email inbox (and spam folder) for your login credentials and password reset link.</p>
                
                <div class="mt-4">
                    <a href="login.php" class="btn btn-primary btn-lg">Go to Login</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

