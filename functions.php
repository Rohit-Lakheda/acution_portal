
<?php
require_once 'database.php';

// Update auction statuses based on current time
function updateAuctionStatuses($pdo) {
    $now = date('Y-m-d H:i:s');
    
    // Update upcoming to active
    $stmt = $pdo->prepare("UPDATE auctions SET status = 'active' 
                          WHERE status = 'upcoming' AND start_datetime <= ?");
    $stmt->execute([$now]);
    
    // Update active to closed and determine winner
    $stmt = $pdo->prepare("SELECT id FROM auctions 
                          WHERE status = 'active' AND end_datetime <= ?");
    $stmt->execute([$now]);
    $expiredAuctions = $stmt->fetchAll();
    
    foreach ($expiredAuctions as $auction) {
        closeAuctionAndDetermineWinner($pdo, $auction['id']);
    }
}

// Close auction and determine winner
function closeAuctionAndDetermineWinner($pdo, $auctionId) {
    // Get highest bid
    $stmt = $pdo->prepare("SELECT user_id, amount FROM bids 
                          WHERE auction_id = ? ORDER BY amount DESC LIMIT 1");
    $stmt->execute([$auctionId]);
    $highestBid = $stmt->fetch();
    
    if ($highestBid) {
        // Update auction with winner
        $stmt = $pdo->prepare("UPDATE auctions SET status = 'closed', 
                              winner_user_id = ?, final_price = ? WHERE id = ?");
        $stmt->execute([$highestBid['user_id'], $highestBid['amount'], $auctionId]);
        
        // Get auction details
        $stmt = $pdo->prepare("SELECT title FROM auctions WHERE id = ?");
        $stmt->execute([$auctionId]);
        $auction = $stmt->fetch();
        
        // Send notification to winner
        $message = "Congratulations! You won the auction '{$auction['title']}' for ₹" . 
                   number_format($highestBid['amount'], 2) . 
                   ". Please complete payment within 7 days.";
        sendNotification($pdo, $highestBid['user_id'], $message);
        
        // Send email (basic implementation)
        sendWinnerEmail($pdo, $highestBid['user_id'], $auction['title'], $highestBid['amount']);
    } else {
        // No bids, just close
        $stmt = $pdo->prepare("UPDATE auctions SET status = 'closed' WHERE id = ?");
        $stmt->execute([$auctionId]);
    }
}

// Send notification
function sendNotification($pdo, $userId, $message) {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
    $stmt->execute([$userId, $message]);
}

// Send winner email
function sendWinnerEmail($pdo, $userId, $auctionTitle, $amount) {
    $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Use centralized email sending function which gets settings from database
        require_once __DIR__ . '/email_templates.php';
        
        $to = $user['email'];
        $subject = "You Won: " . $auctionTitle;
        $htmlMessage = "Dear {$user['name']},<br><br>" .
                      "Congratulations! You have won the auction '{$auctionTitle}' " .
                      "for ₹" . number_format($amount, 2) . ".<br><br>" .
                      "Please complete your payment within 7 days.<br><br>" .
                      "Thank you!";
        $plainTextMessage = "Dear {$user['name']},\n\n" .
                           "Congratulations! You have won the auction '{$auctionTitle}' " .
                           "for ₹" . number_format($amount, 2) . ".\n\n" .
                           "Please complete your payment within 7 days.\n\n" .
                           "Thank you!";
        
        // Use sendEmail function which gets settings from database only
        sendEmail($to, $subject, $htmlMessage, $plainTextMessage);
    }
}

// Get current highest bid for auction
function getCurrentHighestBid($pdo, $auctionId) {
    $stmt = $pdo->prepare("SELECT MAX(amount) as highest FROM bids WHERE auction_id = ?");
    $stmt->execute([$auctionId]);
    $result = $stmt->fetch();
    return $result['highest'] ?? 0;
}

// Check if user has bid on auction
function userHasBid($pdo, $auctionId, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bids 
                          WHERE auction_id = ? AND user_id = ?");
    $stmt->execute([$auctionId, $userId]);
    $result = $stmt->fetch();
    return $result['count'] > 0;
}

// Format Indian Rupees
function formatINR($amount) {
    return '₹' . number_format($amount, 2);
}

// Get registration amount from settings
function getRegistrationAmount($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'registration_amount'");
        $stmt->execute();
        $result = $stmt->fetch();
        if ($result) {
            return floatval($result['setting_value']);
        }
    } catch(PDOException $e) {
        // If settings table doesn't exist, return default
    }
    return 500.00; // Default registration amount
}

// Generate unique registration ID (REG + random alphanumeric)
function generateRegistrationId($pdo) {
    do {
        // Generate REG + 10 random alphanumeric characters
        $randomPart = strtoupper(substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 10));
        $registrationId = 'REG' . $randomPart;
        
        // Check if it already exists
        $stmt = $pdo->prepare("SELECT id FROM registration WHERE registration_id = ?");
        $stmt->execute([$registrationId]);
        $exists = $stmt->fetch();
    } while ($exists);
    
    return $registrationId;
}

// Rate limiting for bidding
function checkBidRateLimit($userId) {
    if (!isset($_SESSION['bid_attempts'])) {
        $_SESSION['bid_attempts'] = [];
    }
    
    $now = time();
    $_SESSION['bid_attempts'] = array_filter(
        $_SESSION['bid_attempts'], 
        function($timestamp) use ($now) {
            return ($now - $timestamp) < 60; // Last 60 seconds
        }
    );
    
    if (count($_SESSION['bid_attempts']) >= 10) {
        return false; // Too many attempts
    }
    
    $_SESSION['bid_attempts'][] = $now;
    return true;
}

// Common header for pages
function renderHeader($title, $isAdmin = false) {
    $homeLink = $isAdmin ? 'admin_dashboard.php' : 'user_auctions.php';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?> - Auction Portal</title>
        <link href="https://fonts.googleapis.com/css2?family=Varela+Round&display=swap" rel="stylesheet">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { 
                font-family: 'Varela Round', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
                background: #f8f9fa; 
                color: #2c3e50;
                line-height: 1.6;
            }
            .navbar { 
                background: linear-gradient(135deg, #1a237e 0%, #283593 100%); 
                color: white; 
                padding: 15px 0; 
                box-shadow: 0 4px 6px rgba(0,0,0,0.1); 
                position: sticky;
                top: 0;
                z-index: 1000;
            }
            .navbar-content { 
                max-width: 1400px; 
                margin: 0 auto; 
                display: flex; 
                justify-content: space-between; 
                align-items: center; 
                padding: 0 30px; 
                flex-wrap: wrap; 
            }
            .navbar-brand { 
                display: flex; 
                align-items: center; 
                gap: 15px; 
                text-decoration: none; 
                color: white; 
                transition: opacity 0.3s;
            }
            .navbar-brand:hover { opacity: 0.9; }
            .navbar-logo { 
                height: 50px; 
                width: auto; 
                max-width: 150px; 
                object-fit: contain; 
                transition: transform 0.3s; 
                background: white;
                padding: 5px;
                border-radius: 12px;
            }
            .navbar-logo:hover { transform: scale(1.05); }
            .navbar-title { 
                font-size: 22px; 
                font-weight: 400; 
                display: flex; 
                align-items: center; 
                gap: 10px; 
                letter-spacing: 0.5px;
            }
            .navbar nav { 
                display: flex; 
                align-items: center; 
                flex-wrap: wrap; 
                gap: 8px; 
            }
            .navbar nav a { 
                color: white; 
                text-decoration: none; 
                padding: 10px 18px; 
                border-radius: 8px; 
                transition: all 0.3s; 
                white-space: nowrap; 
                font-size: 15px;
                font-weight: 400;
            }
            .navbar nav a:hover { 
                background: rgba(255,255,255,0.15); 
                transform: translateY(-2px);
            }
            @media (max-width: 768px) {
                .navbar-content { flex-direction: column; gap: 15px; padding: 0 20px; }
                .navbar-brand { width: 100%; justify-content: center; }
                .navbar nav { width: 100%; justify-content: center; }
                .navbar-logo { height: 40px; }
                .navbar-title { font-size: 20px; }
            }
            @media (max-width: 480px) {
                .navbar nav a { padding: 8px 12px; font-size: 14px; }
                .navbar-logo { height: 35px; }
                .navbar-title { font-size: 18px; }
            }
            .container { 
                max-width: 1400px; 
                margin: 40px auto; 
                padding: 0 30px; 
            }
            .card { 
                background: white; 
                padding: 35px; 
                border-radius: 12px; 
                box-shadow: 0 2px 8px rgba(0,0,0,0.08); 
                margin-bottom: 25px; 
                border: 1px solid #e9ecef;
            }
            .card h2 {
                font-size: 28px;
                font-weight: 400;
                color: #1a237e;
                margin-bottom: 20px;
                letter-spacing: 0.3px;
            }
            .btn { 
                padding: 12px 24px; 
                background: #1a237e; 
                color: white; 
                text-decoration: none; 
                border: none; 
                border-radius: 8px; 
                cursor: pointer; 
                display: inline-block; 
                transition: all 0.3s; 
                font-family: 'Varela Round', sans-serif;
                font-size: 15px;
                font-weight: 400;
                letter-spacing: 0.3px;
            }
            .btn:hover { 
                background: #283593; 
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(26, 35, 126, 0.3);
            }
            .btn-danger { background: #c62828; }
            .btn-danger:hover { background: #b71c1c; }
            .btn-success { background: #2e7d32; }
            .btn-success:hover { background: #1b5e20; }
            .btn-secondary { background: #546e7a; }
            .btn-secondary:hover { background: #455a64; }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-top: 20px;
            }
            table th, table td { 
                padding: 15px; 
                text-align: left; 
                border-bottom: 1px solid #e9ecef; 
            }
            table th { 
                background: #f8f9fa; 
                font-weight: 400; 
                color: #1a237e; 
                font-size: 15px;
                letter-spacing: 0.3px;
            }
            table tr:hover { background: #f8f9fa; }
            table td {
                color: #495057;
                font-size: 15px;
            }
            .form-group { margin-bottom: 25px; }
            .form-group label { 
                display: block; 
                margin-bottom: 8px; 
                font-weight: 400; 
                color: #1a237e; 
                font-size: 15px;
                letter-spacing: 0.2px;
            }
            .form-group input, .form-group textarea, .form-group select { 
                width: 100%; 
                padding: 12px 16px; 
                border: 2px solid #e9ecef; 
                border-radius: 8px; 
                font-size: 15px; 
                font-family: 'Varela Round', sans-serif;
                transition: all 0.3s;
                color: #495057;
            }
            .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
                outline: none;
                border-color: #1a237e;
                box-shadow: 0 0 0 3px rgba(26, 35, 126, 0.1);
            }
            .form-group textarea { min-height: 120px; resize: vertical; }
            .alert { 
                padding: 16px 20px; 
                border-radius: 8px; 
                margin-bottom: 20px; 
                font-size: 15px;
                border-left: 4px solid;
            }
            .alert-error { 
                background: #ffebee; 
                color: #c62828; 
                border-color: #c62828;
            }
            .alert-success { 
                background: #e8f5e9; 
                color: #2e7d32; 
                border-color: #2e7d32;
            }
            .alert-info { 
                background: #e3f2fd; 
                color: #1565c0; 
                border-color: #1565c0;
            }
            .badge { 
                padding: 6px 12px; 
                border-radius: 6px; 
                font-size: 13px; 
                font-weight: 400; 
                letter-spacing: 0.3px;
            }
            .badge-upcoming { background: #fff3e0; color: #e65100; }
            .badge-active { background: #e8f5e9; color: #2e7d32; }
            .badge-closed { background: #f5f5f5; color: #616161; }
            .grid { 
                display: grid; 
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); 
                gap: 25px; 
            }
            .auction-card { 
                background: white; 
                border-radius: 12px; 
                padding: 25px; 
                box-shadow: 0 2px 8px rgba(0,0,0,0.08); 
                transition: all 0.3s; 
                border: 1px solid #e9ecef;
            }
            .auction-card:hover { 
                transform: translateY(-5px); 
                box-shadow: 0 8px 16px rgba(0,0,0,0.12); 
            }
            .auction-card h3 { 
                color: #1a237e; 
                margin-bottom: 12px; 
                font-size: 20px;
                font-weight: 400;
            }
            .auction-card p { 
                color: #6c757d; 
                margin-bottom: 12px; 
                font-size: 14px; 
            }
            .price { 
                font-size: 26px; 
                font-weight: 400; 
                color: #1a237e; 
                margin: 15px 0; 
                letter-spacing: 0.5px;
            }
        </style>
    </head>
    <body>
        <div class="navbar">
            <div class="navbar-content">
                <a href="<?php echo $homeLink; ?>" class="navbar-brand">
                    <img src="images/nixi_logo1.jpg" alt="NIXI Logo" class="navbar-logo">
                </a>
                <nav>
                    <?php if ($isAdmin): ?>
                        <a href="admin_dashboard.php">Dashboard</a>
                        <a href="admin_add_auction.php">Add Auction</a>
                        <a href="admin_upload_excel.php">Upload Excel</a>
                        <a href="admin_manage_users.php">Manage Users</a>
                        <a href="admin_completed.php">Completed</a>
                        <a href="admin_settings.php">Settings</a>
                    <?php else: ?>
                        <a href="user_dashboard.php">Dashboard</a>
                        <a href="user_auctions.php">Auctions</a>
                        <a href="user_my_bids.php">My Bids</a>
                        <a href="user_won_auctions.php">Won Auctions</a>
                        <a href="user_profile.php">My Profile</a>
                        <a href="user_notifications.php">Notifications</a>
                    <?php endif; ?>
                    <a href="logout.php">Logout</a>
                </nav>
            </div>
        </div>
        <div class="container">
    <?php
}

function renderFooter() {
    ?>
        </div>
    </body>
    </html>
    <?php
}
?>