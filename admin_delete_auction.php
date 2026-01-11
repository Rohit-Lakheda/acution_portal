<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'functions.php';

requireAdmin();

$auctionId = intval($_GET['id'] ?? 0);

// Check if auction has bids
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bids WHERE auction_id = ?");
$stmt->execute([$auctionId]);
if ($stmt->fetch()['count'] > 0) {
    header('Location: admin_dashboard.php');
    exit();
}

// Delete auction
$stmt = $pdo->prepare("DELETE FROM auctions WHERE id = ?");
$stmt->execute([$auctionId]);

header('Location: admin_dashboard.php');
exit();
?>
