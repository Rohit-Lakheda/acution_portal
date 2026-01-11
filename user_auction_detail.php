<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();
updateAuctionStatuses($pdo);

$auctionId = intval($_GET['id'] ?? 0);

// Get auction details
$stmt = $pdo->prepare("SELECT * FROM auctions WHERE id = ?");
$stmt->execute([$auctionId]);
$auction = $stmt->fetch();

if (!$auction || $auction['status'] !== 'active') {
    header('Location: user_auctions.php');
    exit();
}

// Get current highest bid
$currentBid = getCurrentHighestBid($pdo, $auctionId);
if ($currentBid == 0) {
    $currentBid = $auction['base_price'];
}
$minNextBid = $currentBid + $auction['min_increment'];

// Get recent bids
$stmt = $pdo->prepare("SELECT b.amount, b.created_at, u.name 
                       FROM bids b 
                       JOIN users u ON b.user_id = u.id 
                       WHERE b.auction_id = ? 
                       ORDER BY b.created_at DESC 
                       LIMIT 10");
$stmt->execute([$auctionId]);
$recentBids = $stmt->fetchAll();

// Check if user has already bid
$userHasBidOnThis = userHasBid($pdo, $auctionId, getCurrentUserId());

renderHeader('Auction Details', false);
?>

<?php if (isset($_SESSION['bid_success'])): ?>
    <div class="alert alert-success" style="margin-bottom: 20px;">
        ✓ <?php echo htmlspecialchars($_SESSION['bid_success']); ?>
    </div>
    <?php unset($_SESSION['bid_success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['bid_error'])): ?>
    <div class="alert alert-error" style="margin-bottom: 20px;">
        ✗ <?php echo htmlspecialchars($_SESSION['bid_error']); ?>
    </div>
    <?php unset($_SESSION['bid_error']); ?>
<?php endif; ?>

<div class="card">
    <a href="user_auctions.php" class="btn btn-secondary" style="margin-bottom: 20px;">← Back to Auctions</a>
    
    <h2><?php echo htmlspecialchars($auction['title']); ?></h2>
    <p style="color: #6c757d; margin-bottom: 20px;">Review auction details and place your bid. Make sure your bid is at least the minimum next bid amount.</p>
    
    <div style="background: #f7fafc; padding: 20px; border-radius: 5px; margin: 20px 0;">
        <h3 style="margin-bottom: 15px;">Description</h3>
        <p style="color: #2d3748; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($auction['description'])); ?></p>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
        <div style="background: #e6fffa; padding: 15px; border-radius: 5px;">
            <p style="color: #234e52; font-size: 14px; margin-bottom: 5px;">Base Price</p>
            <p style="color: #234e52; font-size: 24px; font-weight: bold;"><?php echo formatINR($auction['base_price']); ?></p>
        </div>
        <div style="background: #e0e7ff; padding: 15px; border-radius: 5px;">
            <p style="color: #3730a3; font-size: 14px; margin-bottom: 5px;">Current Highest Bid</p>
            <p style="color: #3730a3; font-size: 24px; font-weight: bold;"><?php echo formatINR($currentBid); ?></p>
        </div>
        <div style="background: #fef3c7; padding: 15px; border-radius: 5px;">
            <p style="color: #78350f; font-size: 14px; margin-bottom: 5px;">Minimum Next Bid</p>
            <p style="color: #78350f; font-size: 24px; font-weight: bold;"><?php echo formatINR($minNextBid); ?></p>
        </div>
        <div style="background: #fee2e2; padding: 15px; border-radius: 5px;">
            <p style="color: #7f1d1d; font-size: 14px; margin-bottom: 5px;">Auction Ends</p>
            <p style="color: #7f1d1d; font-size: 16px; font-weight: bold;"><?php echo date('d-M-Y H:i', strtotime($auction['end_datetime'])); ?></p>
        </div>
    </div>
</div>

<div class="card">
    <h3>Place Your Bid</h3>
    <p style="color: #6c757d; margin-bottom: 20px;">Enter your bid amount. Your bid must be at least <?php echo formatINR($minNextBid); ?> to be valid.</p>
    
    <?php if ($userHasBidOnThis): ?>
        <div class="alert alert-info" style="margin-bottom: 20px;">
            ℹ️ <strong>You have already placed a bid on this auction.</strong> You can place a higher bid to increase your chances of winning.
        </div>
    <?php else: ?>
        <div class="alert alert-info" style="margin-bottom: 20px;">
            ℹ️ <strong>First time bidding?</strong> Make sure your bid meets the minimum requirement. You can increase your bid anytime before the auction ends.
        </div>
    <?php endif; ?>
    
    <form action="user_place_bid.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="auction_id" value="<?php echo $auctionId; ?>">
        
        <div class="form-group">
            <label>Your Bid Amount (₹) *</label>
            <input type="number" name="bid_amount" step="0.01" min="<?php echo $minNextBid; ?>" 
                   value="<?php echo $minNextBid; ?>" required 
                   style="font-size: 18px; font-weight: bold;">
            <small style="color: #718096; display: block; margin-top: 5px;">
                Minimum bid: <?php echo formatINR($minNextBid); ?>
            </small>
        </div>
        
        <button type="submit" class="btn btn-success" style="font-size: 16px; padding: 15px 30px;">
            🎯 Place Bid
        </button>
    </form>
</div>

<div class="card">
    <h3>Recent Bids (<?php echo count($recentBids); ?>)</h3>
    
    <?php if (empty($recentBids)): ?>
        <p style="color: #718096;">No bids yet. Be the first to bid!</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Bidder</th>
                    <th>Amount</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentBids as $bid): ?>
                <tr>
                    <td><?php echo htmlspecialchars($bid['name']); ?></td>
                    <td><strong><?php echo formatINR($bid['amount']); ?></strong></td>
                    <td><?php echo date('d-M-Y H:i:s', strtotime($bid['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php renderFooter(); ?>