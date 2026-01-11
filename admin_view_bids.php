<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'functions.php';

requireAdmin();

$auctionId = intval($_GET['id'] ?? 0);

// Get auction details
$stmt = $pdo->prepare("SELECT * FROM auctions WHERE id = ?");
$stmt->execute([$auctionId]);
$auction = $stmt->fetch();

if (!$auction) {
    header('Location: admin_dashboard.php');
    exit();
}

// Get all bids
$stmt = $pdo->prepare("SELECT b.*, u.name as bidder_name, u.email as bidder_email 
                       FROM bids b 
                       JOIN users u ON b.user_id = u.id 
                       WHERE b.auction_id = ? 
                       ORDER BY b.amount DESC, b.created_at ASC");
$stmt->execute([$auctionId]);
$bids = $stmt->fetchAll();

renderHeader('View Bids', true);
?>

<div class="card">
    <h2>Bids for: <?php echo htmlspecialchars($auction['title']); ?></h2>
    <p style="color: #6c757d; margin-bottom: 20px;">View all bids placed on this auction. The highest bidder is highlighted in green.</p>
    
    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #e9ecef;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div>
                <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">Status</label>
                <span class="badge badge-<?php echo $auction['status']; ?>"><?php echo strtoupper($auction['status']); ?></span>
            </div>
            <div>
                <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">Base Price</label>
                <strong style="color: #1a237e; font-size: 18px;"><?php echo formatINR($auction['base_price']); ?></strong>
            </div>
            <?php if ($auction['winner_user_id']): ?>
                <?php
                $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                $stmt->execute([$auction['winner_user_id']]);
                $winner = $stmt->fetch();
                ?>
                <div>
                    <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">Winner</label>
                    <strong style="color: #2e7d32;"><?php echo htmlspecialchars($winner['name'] ?? 'User #' . $auction['winner_user_id']); ?></strong>
                </div>
                <div>
                    <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">Winning Amount</label>
                    <strong style="color: #1a237e; font-size: 18px;"><?php echo formatINR($auction['final_price']); ?></strong>
                </div>
                <div>
                    <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">Payment Status</label>
                    <span class="badge <?php echo $auction['payment_status'] === 'paid' ? 'badge-active' : 'badge-upcoming'; ?>">
                        <?php echo strtoupper($auction['payment_status']); ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (empty($bids)): ?>
        <div class="card" style="text-align: center; padding: 40px;">
            <p style="color: #6c757d; font-size: 16px;">No bids placed yet for this auction.</p>
            <p style="color: #6c757d; margin-top: 10px;">Bids will appear here once users start participating.</p>
        </div>
    <?php else: ?>
        <h3 style="color: #1a237e; margin-bottom: 15px;">All Bids (<?php echo count($bids); ?>)</h3>
        <p style="color: #6c757d; margin-bottom: 20px;">Bids are sorted by amount (highest first). The current winning bid is highlighted.</p>
        <table>
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Bidder Name</th>
                    <th>Bidder Email</th>
                    <th>Bid Amount</th>
                    <th>Bid Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bids as $index => $bid): ?>
                <tr style="<?php echo $index === 0 ? 'background: #c6f6d5;' : ''; ?>">
                    <td><?php echo $index + 1; ?><?php echo $index === 0 ? ' 🏆' : ''; ?></td>
                    <td><?php echo htmlspecialchars($bid['bidder_name']); ?></td>
                    <td><?php echo htmlspecialchars($bid['bidder_email']); ?></td>
                    <td><strong><?php echo formatINR($bid['amount']); ?></strong></td>
                    <td><?php echo date('d-M-Y H:i:s', strtotime($bid['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <div style="margin-top: 20px;">
        <a href="admin_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
</div>

<?php renderFooter(); ?>
