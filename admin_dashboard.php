
<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'functions.php';

requireAdmin();
updateAuctionStatuses($pdo);

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM auctions");
$totalAuctions = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM auctions WHERE status = 'active'");
$activeAuctions = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM auctions WHERE status = 'closed'");
$closedAuctions = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
$totalUsers = $stmt->fetch()['total'];

// Get all auctions
$stmt = $pdo->query("SELECT a.*, u.name as creator_name, 
                     (SELECT COUNT(*) FROM bids WHERE auction_id = a.id) as bid_count
                     FROM auctions a 
                     LEFT JOIN users u ON a.created_by = u.id 
                     WHERE a.status != 'closed'
                     ORDER BY a.start_datetime DESC");
$auctions = $stmt->fetchAll();

renderHeader('Admin Dashboard', true);
?>

<div class="card">
    <h2>Admin Dashboard</h2>
    <p style="color: #6c757d; margin-bottom: 20px;">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>! Manage auctions, users, and system settings from here.</p>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="card" style="text-align: center;">
        <h3 style="color: #667eea; font-size: 36px;"><?php echo $totalAuctions; ?></h3>
        <p style="color: #718096;">Total Auctions</p>
    </div>
    <div class="card" style="text-align: center;">
        <h3 style="color: #48bb78; font-size: 36px;"><?php echo $activeAuctions; ?></h3>
        <p style="color: #718096;">Active Auctions</p>
    </div>
    <div class="card" style="text-align: center;">
        <h3 style="color: #e53e3e; font-size: 36px;"><?php echo $closedAuctions; ?></h3>
        <p style="color: #718096;">Closed Auctions</p>
    </div>
    <div class="card" style="text-align: center;">
        <h3 style="color: #9f7aea; font-size: 36px;"><?php echo $totalUsers; ?></h3>
        <p style="color: #718096;">Total Users</p>
    </div>
</div>

<div class="card">
    <h2>All Auctions</h2>
    <p style="color: #6c757d; margin-bottom: 20px;">Manage all active and upcoming auctions. View bids, edit details, or close auctions as needed.</p>
    <div style="margin-bottom: 20px;">
        <a href="admin_add_auction.php" class="btn">➕ Add New Auction</a>
        <a href="admin_upload_excel.php" class="btn btn-success">📤 Upload Excel</a>
        <a href="admin_manage_users.php" class="btn" style="background: #1565c0;">👥 Manage Users</a>
        <a href="admin_settings.php" class="btn btn-secondary">⚙️ Settings</a>
    </div>
    
    <?php if (empty($auctions)): ?>
        <p style="color: #718096;">No auctions found. Create your first auction!</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Base Price</th>
                    <th>Status</th>
                    <th>Bids</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($auctions as $auction): ?>
                <tr>
                    <td><?php echo $auction['id']; ?></td>
                    <td><?php echo htmlspecialchars($auction['title']); ?></td>
                    <td><?php echo formatINR($auction['base_price']); ?></td>
                    <td>
                        <span class="badge badge-<?php echo $auction['status']; ?>">
                            <?php echo strtoupper($auction['status']); ?>
                        </span>
                    </td>
                    <td><?php echo $auction['bid_count']; ?></td>
                    <td><?php echo date('d-M-Y H:i', strtotime($auction['start_datetime'])); ?></td>
                    <td><?php echo date('d-M-Y H:i', strtotime($auction['end_datetime'])); ?></td>
                    <td>
                        <a href="admin_view_bids.php?id=<?php echo $auction['id']; ?>" class="btn btn-secondary" style="padding: 5px 10px; font-size: 12px;">View Bids</a>
                        <?php if ($auction['bid_count'] == 0): ?>
                            <a href="admin_edit_auction.php?id=<?php echo $auction['id']; ?>" class="btn" style="padding: 5px 10px; font-size: 12px;">Edit</a>
                            <a href="admin_delete_auction.php?id=<?php echo $auction['id']; ?>" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;" onclick="return confirm('Are you sure?')">Delete</a>
                        <?php endif; ?>
                        <?php if ($auction['status'] != 'closed'): ?>
                            <a href="admin_close_auction.php?id=<?php echo $auction['id']; ?>" class="btn btn-danger" style="padding: 5px 10px; font-size: 12px;" onclick="return confirm('Close this auction now?')">Force Close</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php renderFooter(); ?>