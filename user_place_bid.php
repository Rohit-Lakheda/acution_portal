
<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$error = '';
$auctionId = intval($_POST['auction_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: user_auctions.php');
    exit();
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['bid_error'] = 'Invalid CSRF token';
    header('Location: user_auction_detail.php?id=' . $auctionId);
    exit();
}

// Rate limiting
if (!checkBidRateLimit(getCurrentUserId())) {
    $_SESSION['bid_error'] = 'Too many bid attempts. Please wait a moment.';
    header('Location: user_auction_detail.php?id=' . $auctionId);
    exit();
}

$bidAmount = floatval($_POST['bid_amount'] ?? 0);

// Get auction details
$stmt = $pdo->prepare("SELECT * FROM auctions WHERE id = ? AND status = 'active'");
$stmt->execute([$auctionId]);
$auction = $stmt->fetch();

if (!$auction) {
    $_SESSION['bid_error'] = 'Auction not found or not active';
    header('Location: user_auctions.php');
    exit();
}

// Validate bid
$currentBid = getCurrentHighestBid($pdo, $auctionId);
if ($currentBid == 0) {
    $currentBid = $auction['base_price'];
}
$minNextBid = $currentBid + $auction['min_increment'];

if ($bidAmount < $minNextBid) {
    $_SESSION['bid_error'] = 'Bid must be at least ' . formatINR($minNextBid);
    header('Location: user_auction_detail.php?id=' . $auctionId);
    exit();
}

// Place bid
try {
    $stmt = $pdo->prepare("INSERT INTO bids (auction_id, user_id, amount) VALUES (?, ?, ?)");
    $stmt->execute([$auctionId, getCurrentUserId(), $bidAmount]);
    
    $_SESSION['bid_success'] = 'Bid placed successfully for ' . formatINR($bidAmount) . '!';
    header('Location: user_auction_detail.php?id=' . $auctionId);
    exit();
} catch(PDOException $e) {
    $_SESSION['bid_error'] = 'Failed to place bid. Please try again.';
    header('Location: user_auction_detail.php?id=' . $auctionId);
    exit();
}
?>