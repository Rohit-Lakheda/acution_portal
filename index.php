<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'functions.php';

// Update auction statuses
updateAuctionStatuses($pdo);

// Redirect based on login status
if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: user_auctions.php');
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Varela+Round&display=swap" rel="stylesheet">
    <title>Auction Portal - Home</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Varela Round', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            background: linear-gradient(135deg, #1a237e 0%, #283593 100%); 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 20px; 
        }
        .container { 
            background: white; 
            padding: 50px 40px; 
            border-radius: 12px; 
            box-shadow: 0 8px 24px rgba(0,0,0,0.15); 
            text-align: center; 
            max-width: 550px; 
            width: 100%; 
        }
        .logo-container { margin-bottom: 30px; }
        .logo-container img { 
            height: 75px; 
            width: auto; 
            max-width: 200px; 
            object-fit: contain; 
            margin-bottom: 18px; 
            background: white;
            padding: 5px;
            border-radius: 12px;
        }
        h1 { 
            color: #1a237e; 
            margin-bottom: 20px; 
            font-size: 30px; 
            font-weight: 400;
            letter-spacing: 0.5px;
        }
        p { 
            color: #6c757d; 
            margin-bottom: 35px; 
            line-height: 1.7; 
            font-size: 16px;
        }
        .buttons { 
            display: flex; 
            gap: 15px; 
            justify-content: center; 
            flex-wrap: wrap; 
        }
        .btn { 
            padding: 14px 32px; 
            background: #1a237e; 
            color: white; 
            text-decoration: none; 
            border-radius: 8px; 
            transition: all 0.3s; 
            display: inline-block; 
            font-family: 'Varela Round', sans-serif;
            font-size: 16px;
            font-weight: 400;
            letter-spacing: 0.3px;
        }
        .btn:hover { 
            background: #283593; 
            transform: translateY(-2px); 
            box-shadow: 0 4px 8px rgba(26, 35, 126, 0.3);
        }
        .btn-secondary { 
            background: #2e7d32; 
        }
        .btn-secondary:hover { 
            background: #1b5e20; 
        }
        @media (max-width: 480px) {
            .logo-container img { height: 55px; }
            h1 { font-size: 26px; }
            .container { padding: 40px 25px; }
            .buttons { flex-direction: column; }
            .btn { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo-container">
            <img src="images/nixi_logo1.jpg" alt="NIXI Logo">
            <h1>🎯 Auction Portal</h1>
        </div>
        <p>Welcome to the online auction platform. Place bids and win amazing items!</p>
        <div class="buttons">
            <a href="login.php" class="btn">Login</a>
            <a href="register.php" class="btn btn-secondary">Register</a>
        </div>
    </div>
</body>
</html>