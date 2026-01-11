
<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'functions.php';

requireAdmin();
updateAuctionStatuses($pdo);

// Update payment status if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['auction_id'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $auctionId = intval($_POST['auction_id']);
        $paymentStatus = $_POST['payment_status'];
        
        $stmt = $pdo->prepare("UPDATE auctions SET payment_status = ? WHERE id = ?");
        $stmt->execute([$paymentStatus, $auctionId]);
    }
}

// Get completed auctions
$stmt = $pdo->query("SELECT a.*, u.name as winner_name, u.email as winner_email 
                     FROM auctions a 
                     LEFT JOIN users u ON a.winner_user_id = u.id 
                     WHERE a.status = 'closed' 
                     ORDER BY a.end_datetime DESC");
$completedAuctions = $stmt->fetchAll();

renderHeader('Completed Auctions', true);
?>

<div class="card">
    <h2>Completed Auctions</h2>
    <p style="color: #6c757d; margin-bottom: 20px;">View all closed auctions, winners, and payment status. You can update payment status directly from this page.</p>
    
    <?php if (empty($completedAuctions)): ?>
        <div class="card" style="text-align: center; padding: 40px;">
            <p style="color: #6c757d; font-size: 16px;">No completed auctions yet.</p>
            <p style="color: #6c757d; margin-top: 10px;">Completed auctions will appear here once they end.</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Winner</th>
                    <th>Final Price</th>
                    <th>Payment Status</th>
                    <th>End Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($completedAuctions as $auction): ?>
                <tr>
                    <td><?php echo $auction['id']; ?></td>
                    <td><?php echo htmlspecialchars($auction['title']); ?></td>
                    <td>
                        <?php if ($auction['winner_name']): ?>
                            <?php echo htmlspecialchars($auction['winner_name']); ?><br>
                            <small style="color: #718096;"><?php echo htmlspecialchars($auction['winner_email']); ?></small>
                        <?php else: ?>
                            <em style="color: #718096;">No winner</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($auction['final_price']): ?>
                            <strong><?php echo formatINR($auction['final_price']); ?></strong>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($auction['winner_user_id']): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <input type="hidden" name="auction_id" value="<?php echo $auction['id']; ?>">
                                <select name="payment_status" onchange="this.form.submit()" style="padding: 5px; border-radius: 3px;">
                                    <option value="pending" <?php echo $auction['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="paid" <?php echo $auction['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                                </select>
                            </form>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('d-M-Y H:i', strtotime($auction['end_datetime'])); ?></td>
                    <td>
                        <a href="admin_view_bids.php?id=<?php echo $auction['id']; ?>" class="btn btn-secondary" style="padding: 5px 10px; font-size: 12px;">View Bids</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php renderFooter(); ?>