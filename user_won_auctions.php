
<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();
updateAuctionStatuses($pdo);

// Get won auctions
$stmt = $pdo->prepare("SELECT * FROM auctions 
                       WHERE winner_user_id = ? AND status = 'closed' 
                       ORDER BY end_datetime DESC");
$stmt->execute([getCurrentUserId()]);
$wonAuctions = $stmt->fetchAll();

renderHeader('Won Auctions', false);
?>

<?php
// Display success/error messages
if (isset($_GET['payment']) && $_GET['payment'] === 'success'): ?>
    <div class="card" style="background: #c6f6d5; border-left: 4px solid #22543d; margin-bottom: 20px;">
        <p style="color: #22543d; margin: 0; font-weight: bold;">✓ Payment successful! Your payment has been confirmed.</p>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="card" style="background: #fed7d7; border-left: 4px solid #c53030; margin-bottom: 20px;">
        <p style="color: #c53030; margin: 0; font-weight: bold;">
            <?php
            $error = $_GET['error'];
            $messages = [
                'payment_failed' => 'Payment failed. ' . (isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Please try again.'),
                'hash_verification_failed' => 'Payment verification failed. Please contact support.',
                'payment_update_failed' => 'Payment received but update failed. Please contact support.',
                'already_paid' => 'This auction has already been paid.',
                'invalid_auction' => 'Invalid auction selected.',
                'auction_not_found' => 'Auction not found or you are not the winner.',
                'user_not_found' => 'User not found.'
            ];
            echo $messages[$error] ?? 'An error occurred. Please try again.';
            ?>
        </p>
    </div>
<?php endif; ?>

<div class="card">
    <h2>🏆 Won Auctions</h2>
    <p style="color: #6c757d; margin-bottom: 20px;">Congratulations! Here are the auctions you've won. Please complete payment within 7 days to secure your items.</p>
</div>

<?php if (empty($wonAuctions)): ?>
    <div class="card">
        <p style="color: #718096; text-align: center;">You haven't won any auctions yet.</p>
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
                    <th>Winning Amount</th>
                    <th>Payment Status</th>
                    <th>Won Date</th>
                    <th>Payment Instructions</th>
                    <th>Download Invoice</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($wonAuctions as $auction): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($auction['title']); ?></strong></td>
                    <td><strong style="color: #667eea; font-size: 18px;"><?php echo formatINR($auction['final_price']); ?></strong></td>
                    <td>
                        <?php if ($auction['payment_status'] === 'paid'): ?>
                            <span class="badge badge-active" style="background: #c6f6d5; color: #22543d;">PAID</span>
                        <?php else: ?>
                            <span class="badge badge-upcoming" style="background: #feebc8; color: #c05621;">PENDING</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('d-M-Y H:i', strtotime($auction['end_datetime'])); ?></td>
                    <td>
                        <?php if ($auction['payment_status'] === 'pending'): ?>
                            <div style="background: #fef3c7; padding: 10px; border-radius: 5px; font-size: 13px; margin-bottom: 10px;">
                                <strong>Payment Required:</strong><br>
                                Please complete payment within 7 days.<br>
                                Contact: admin@nixi.in
                            </div>
                            <a href="initiate_payment.php?auction_id=<?php echo $auction['id']; ?>" 
                               class="btn" 
                               style="background: #000; color: #FFCD00; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">
                                Pay Now
                            </a>
                        <?php else: ?>
                            <span style="color: #22543d; font-weight: bold;">✓ Payment Confirmed</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="download_invoice.php?auction_id=<?php echo $auction['id']; ?>" class="btn" style="background: success; color:white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">
                            Download Invoice
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php renderFooter(); ?>