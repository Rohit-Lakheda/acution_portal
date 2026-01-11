
<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();
updateAuctionStatuses($pdo);

// Get active auctions
$stmt = $pdo->query("SELECT a.*, 
                     (SELECT MAX(amount) FROM bids WHERE auction_id = a.id) as current_bid,
                     (SELECT COUNT(*) FROM bids WHERE auction_id = a.id) as bid_count
                     FROM auctions a 
                     WHERE a.status = 'active' 
                     ORDER BY a.end_datetime ASC");
$auctions = $stmt->fetchAll();

renderHeader('Active Auctions', false);
?>

<div class="card">
    <h2>🔥 Active Auctions</h2>
    <p style="color: #6c757d; margin-bottom: 20px;">Browse and bid on active auctions. Click on any auction to view details and place your bid.</p>
</div>

<?php if (empty($auctions)): ?>
    <div class="card">
        <p style="color: #718096; text-align: center;">No active auctions at the moment. Check back later!</p>
    </div>
<?php else: ?>
    <div class="grid">
        <?php foreach ($auctions as $auction): ?>
            <?php
            $currentBid = $auction['current_bid'] ?? $auction['base_price'];
            $minNextBid = $currentBid + $auction['min_increment'];
            $timeLeft = strtotime($auction['end_datetime']) - time();
            $hoursLeft = floor($timeLeft / 3600);
            ?>
            <div class="auction-card">
                <h3><?php echo htmlspecialchars($auction['title']); ?></h3>
                <p><?php echo htmlspecialchars(substr($auction['description'], 0, 100)); ?><?php echo strlen($auction['description']) > 100 ? '...' : ''; ?></p>
                
                <div style="border-top: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; padding: 15px 0; margin: 15px 0;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: #718096;">Current Bid:</span>
                        <strong style="color: #667eea; font-size: 18px;"><?php echo formatINR($currentBid); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: #718096;">Min Next Bid:</span>
                        <strong><?php echo formatINR($minNextBid); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #718096;">Total Bids:</span>
                        <strong><?php echo $auction['bid_count']; ?></strong>
                    </div>
                </div>
                
                <p style="font-size: 13px; color: #718096; margin-bottom: 15px;">
                    ⏰ Ends: <?php echo date('d-M-Y H:i', strtotime($auction['end_datetime'])); ?>
                    <?php if ($hoursLeft > 0 && $hoursLeft < 24): ?>
                        <br><strong style="color: #e53e3e;">⚠️ Ending in <?php echo $hoursLeft; ?> hours!</strong>
                    <?php endif; ?>
                </p>
                
                <a href="user_auction_detail.php?id=<?php echo $auction['id']; ?>" class="btn" style="width: 100%; text-align: center;">Place Bid</a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php renderFooter(); ?>