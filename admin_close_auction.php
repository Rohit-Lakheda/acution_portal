
<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'functions.php';

requireAdmin();

$auctionId = intval($_GET['id'] ?? 0);

// Close auction and determine winner
closeAuctionAndDetermineWinner($pdo, $auctionId);

header('Location: admin_dashboard.php');
exit();
?>