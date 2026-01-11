
<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();
updateAuctionStatuses($pdo);

// Get user's bids
$stmt = $pdo->prepare("SELECT DISTINCT a.id, a.title, a.status, a.end_datetime,
                       (SELECT MAX(amount) FROM bids WHERE auction_id = a.id) as highest_bid,
                       (SELECT amount FROM bids WHERE auction_id = a.id AND user_id = ? ORDER BY amount DESC LIMIT 1) as my_highest_bid,
                       (SELECT COUNT(*) FROM bids WHERE auction_id = a.id) as total_bids
                       FROM auctions a
                       JOIN bids b ON a.id = b.auction_id
                       WHERE b.user_id = ?
                       ORDER BY a.end_datetime DESC");
$stmt->execute([getCurrentUserId(), getCurrentUserId()]);
$myBids = $stmt->fetchAll();

renderHeader('My Bids', false);
?>

<div class="card">
    <h2>📊 My Bids</h2>
    <p style="color: #6c757d; margin-bottom: 20px;">Track all your bidding activity. See which auctions you're currently winning and where you've been outbid.</p>
</div>

<?php if (empty($myBids)): ?>
    <div class="card">
        <p style="color: #718096; text-align: center;">You haven't placed any bids yet.</p>
        <div style="text-align: center; margin-top: 20px;">
            <a href="user_auctions.php" class="btn">Browse Active Auctions</a>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <table>
            <thead>
                <tr>
                    <th>Auction Title</th>
                    <th>Status</th>
                    <th>My Highest Bid</th>
                    <th>Current Highest Bid</th>
                    <th>Total Bids</th>
                    <th>Winning</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($myBids as $bid): ?>
                    <?php $isWinning = ($bid['my_highest_bid'] == $bid['highest_bid']); ?>
                <tr style="<?php echo $isWinning && $bid['status'] === 'active' ? 'background: #c6f6d5;' : ''; ?>">
                    <td><strong><?php echo htmlspecialchars($bid['title']); ?></strong></td>
                    <td><span class="badge badge-<?php echo $bid['status']; ?>"><?php echo strtoupper($bid['status']); ?></span></td>
                    <td><strong><?php echo formatINR($bid['my_highest_bid']); ?></strong></td>
                    <td><?php echo formatINR($bid['highest_bid']); ?></td>
                    <td><?php echo $bid['total_bids']; ?></td>
                    <td>
                        <?php if ($bid['status'] === 'active'): ?>
                            <?php if ($isWinning): ?>
                                <span style="color: #22543d;">✓ Winning</span>
                            <?php else: ?>
                                <span style="color: #c53030;">✗ Outbid</span>
                            <?php endif; ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($bid['status'] === 'active'): ?>
                            <a href="user_auction_detail.php?id=<?php echo $bid['id']; ?>" class="btn" style="padding: 5px 10px; font-size: 12px;">View</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php renderFooter(); ?>