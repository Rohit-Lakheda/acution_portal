<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();
updateAuctionStatuses($pdo);

$userId = getCurrentUserId();

// Get user statistics
$stats = [];

// Total bids
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM bids WHERE user_id = ?");
$stmt->execute([$userId]);
$stats['total_bids'] = $stmt->fetch()['total'];

// Won auctions
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM auctions WHERE winner_user_id = ? AND status = 'closed'");
$stmt->execute([$userId]);
$stats['won_auctions'] = $stmt->fetch()['total'];

// Active auctions user is bidding on
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT auction_id) as total FROM bids b 
                      JOIN auctions a ON b.auction_id = a.id 
                      WHERE b.user_id = ? AND a.status = 'active'");
$stmt->execute([$userId]);
$stats['active_bidding'] = $stmt->fetch()['total'];

// Get user's winning bids (currently highest bidder)
$stmt = $pdo->prepare("SELECT a.id, a.title, a.end_datetime, b.amount as my_bid,
                      (SELECT MAX(amount) FROM bids WHERE auction_id = a.id) as highest_bid
                      FROM auctions a
                      JOIN bids b ON a.id = b.auction_id
                      WHERE b.user_id = ? AND a.status = 'active'
                      AND b.amount = (SELECT MAX(amount) FROM bids WHERE auction_id = a.id)
                      ORDER BY a.end_datetime ASC
                      LIMIT 5");
$stmt->execute([$userId]);
$winningBids = $stmt->fetchAll();

// Get recent bids
$stmt = $pdo->prepare("SELECT b.*, a.title, a.status 
                      FROM bids b
                      JOIN auctions a ON b.auction_id = a.id
                      WHERE b.user_id = ?
                      ORDER BY b.created_at DESC
                      LIMIT 5");
$stmt->execute([$userId]);
$recentBids = $stmt->fetchAll();

// Get active auctions
$stmt = $pdo->query("SELECT a.*, 
                    (SELECT MAX(amount) FROM bids WHERE auction_id = a.id) as current_bid,
                    (SELECT COUNT(*) FROM bids WHERE auction_id = a.id) as bid_count
                    FROM auctions a 
                    WHERE a.status = 'active' 
                    ORDER BY a.end_datetime ASC
                    LIMIT 5");
$activeAuctions = $stmt->fetchAll();

renderHeader('Dashboard', false);
?>

<div class="card">
    <h2>📊 Dashboard</h2>
    <p style="color: #6c757d; margin-bottom: 25px;">Welcome back! Here's an overview of your auction activity and available opportunities.</p>
</div>

<!-- Statistics Cards -->
<div class="grid" style="margin-bottom: 30px;">
    <div class="card" style="text-align: center; background: linear-gradient(135deg, #1a237e 0%, #283593 100%); color: white;">
        <h3 style="color: white; font-size: 36px; margin-bottom: 10px; font-weight: 400;"><?php echo $stats['total_bids']; ?></h3>
        <p style="color: rgba(255,255,255,0.9); margin: 0; font-size: 15px;">Total Bids Placed</p>
    </div>
    <div class="card" style="text-align: center; background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%); color: white;">
        <h3 style="color: white; font-size: 36px; margin-bottom: 10px; font-weight: 400;"><?php echo $stats['won_auctions']; ?></h3>
        <p style="color: rgba(255,255,255,0.9); margin: 0; font-size: 15px;">Auctions Won</p>
    </div>
    <div class="card" style="text-align: center; background: linear-gradient(135deg, #1565c0 0%, #0d47a1 100%); color: white;">
        <h3 style="color: white; font-size: 36px; margin-bottom: 10px; font-weight: 400;"><?php echo $stats['active_bidding']; ?></h3>
        <p style="color: rgba(255,255,255,0.9); margin: 0; font-size: 15px;">Active Bidding</p>
    </div>
</div>

<!-- Winning Bids Section -->
<?php if (!empty($winningBids)): ?>
<div class="card">
    <h3 style="color: #1a237e; margin-bottom: 15px; font-size: 22px;">🏆 Currently Winning</h3>
    <p style="color: #6c757d; margin-bottom: 20px; font-size: 14px;">Auctions where you currently have the highest bid</p>
    <table>
        <thead>
            <tr>
                <th>Auction Title</th>
                <th>Your Bid</th>
                <th>Ends At</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($winningBids as $bid): ?>
                <?php
                $timeLeft = strtotime($bid['end_datetime']) - time();
                $hoursLeft = floor($timeLeft / 3600);
                ?>
                <tr style="background: #e8f5e9;">
                    <td><strong><?php echo htmlspecialchars($bid['title']); ?></strong></td>
                    <td><strong style="color: #2e7d32;"><?php echo formatINR($bid['my_bid']); ?></strong></td>
                    <td><?php echo date('d-M-Y H:i', strtotime($bid['end_datetime'])); ?></td>
                    <td>
                        <?php if ($hoursLeft > 0 && $hoursLeft < 24): ?>
                            <span style="color: #c62828; font-weight: 500;">⚠️ Ending Soon</span>
                        <?php else: ?>
                            <span style="color: #2e7d32;">✓ Leading</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="user_auction_detail.php?id=<?php echo $bid['id']; ?>" class="btn" style="padding: 6px 12px; font-size: 13px;">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Recent Bids -->
<?php if (!empty($recentBids)): ?>
<div class="card">
    <h3 style="color: #1a237e; margin-bottom: 15px; font-size: 22px;">📝 Recent Bids</h3>
    <p style="color: #6c757d; margin-bottom: 20px; font-size: 14px;">Your latest bidding activity</p>
    <table>
        <thead>
            <tr>
                <th>Auction Title</th>
                <th>Bid Amount</th>
                <th>Status</th>
                <th>Bid Time</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($recentBids as $bid): ?>
                <tr>
                    <td><?php echo htmlspecialchars($bid['title']); ?></td>
                    <td><strong><?php echo formatINR($bid['amount']); ?></strong></td>
                    <td>
                        <span class="badge badge-<?php echo $bid['status']; ?>">
                            <?php echo strtoupper($bid['status']); ?>
                        </span>
                    </td>
                    <td><?php echo date('d-M-Y H:i', strtotime($bid['created_at'])); ?></td>
                    <td>
                        <?php if ($bid['status'] === 'active'): ?>
                            <a href="user_auction_detail.php?id=<?php echo $bid['auction_id']; ?>" class="btn" style="padding: 6px 12px; font-size: 13px;">View</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div style="margin-top: 20px; text-align: center;">
        <a href="user_my_bids.php" class="btn btn-secondary">View All My Bids</a>
    </div>
</div>
<?php endif; ?>

<!-- Active Auctions -->
<?php if (!empty($activeAuctions)): ?>
<div class="card">
    <h3 style="color: #1a237e; margin-bottom: 15px; font-size: 22px;">🔥 Active Auctions</h3>
    <p style="color: #6c757d; margin-bottom: 20px; font-size: 14px;">Browse and participate in active auctions</p>
    <div class="grid">
        <?php foreach ($activeAuctions as $auction): ?>
            <?php
            $currentBid = $auction['current_bid'] ?? $auction['base_price'];
            $minNextBid = $currentBid + $auction['min_increment'];
            $timeLeft = strtotime($auction['end_datetime']) - time();
            $hoursLeft = floor($timeLeft / 3600);
            ?>
            <div class="auction-card">
                <h3><?php echo htmlspecialchars($auction['title']); ?></h3>
                <p style="font-size: 14px; color: #6c757d; margin-bottom: 15px;">
                    <?php echo htmlspecialchars(substr($auction['description'], 0, 80)); ?><?php echo strlen($auction['description']) > 80 ? '...' : ''; ?>
                </p>
                <div style="border-top: 1px solid #e9ecef; border-bottom: 1px solid #e9ecef; padding: 12px 0; margin: 15px 0;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                        <span style="color: #6c757d; font-size: 14px;">Current Bid:</span>
                        <strong style="color: #1a237e; font-size: 18px;"><?php echo formatINR($currentBid); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #6c757d; font-size: 14px;">Min Next:</span>
                        <strong style="color: #1a237e;"><?php echo formatINR($minNextBid); ?></strong>
                    </div>
                </div>
                <p style="font-size: 13px; color: #6c757d; margin-bottom: 15px;">
                    ⏰ Ends: <?php echo date('d-M-Y H:i', strtotime($auction['end_datetime'])); ?>
                    <?php if ($hoursLeft > 0 && $hoursLeft < 24): ?>
                        <br><strong style="color: #c62828;">⚠️ Ending in <?php echo $hoursLeft; ?> hours!</strong>
                    <?php endif; ?>
                </p>
                <a href="user_auction_detail.php?id=<?php echo $auction['id']; ?>" class="btn" style="width: 100%; text-align: center;">Place Bid</a>
            </div>
        <?php endforeach; ?>
    </div>
    <div style="margin-top: 20px; text-align: center;">
        <a href="user_auctions.php" class="btn btn-secondary">View All Active Auctions</a>
    </div>
</div>
<?php else: ?>
<div class="card">
    <h3 style="color: #1a237e; margin-bottom: 15px; font-size: 22px;">🔥 Active Auctions</h3>
    <p style="color: #6c757d; text-align: center; padding: 30px;">No active auctions at the moment. Check back later!</p>
</div>
<?php endif; ?>

<?php renderFooter(); ?>
