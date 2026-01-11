
<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'functions.php';

requireAdmin();

$auctionId = intval($_GET['id'] ?? 0);
$error = '';
$success = '';

// Check if auction has bids
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bids WHERE auction_id = ?");
$stmt->execute([$auctionId]);
if ($stmt->fetch()['count'] > 0) {
    header('Location: admin_dashboard.php');
    exit();
}

// Get auction details
$stmt = $pdo->prepare("SELECT * FROM auctions WHERE id = ?");
$stmt->execute([$auctionId]);
$auction = $stmt->fetch();

if (!$auction) {
    header('Location: admin_dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $base_price = floatval($_POST['base_price'] ?? 0);
        $min_increment = floatval($_POST['min_increment'] ?? 0);
        $start_datetime = $_POST['start_datetime'] ?? '';
        $end_datetime = $_POST['end_datetime'] ?? '';
        
        if (empty($title) || empty($base_price) || empty($min_increment)) {
            $error = 'All required fields must be filled';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE auctions SET title = ?, description = ?, base_price = ?, min_increment = ?, start_datetime = ?, end_datetime = ? WHERE id = ?");
                $stmt->execute([$title, $description, $base_price, $min_increment, $start_datetime, $end_datetime, $auctionId]);
                
                $success = 'Auction updated successfully!';
                
                // Refresh auction data
                $stmt = $pdo->prepare("SELECT * FROM auctions WHERE id = ?");
                $stmt->execute([$auctionId]);
                $auction = $stmt->fetch();
            } catch(PDOException $e) {
                $error = 'Failed to update auction';
            }
        }
    }
}

renderHeader('Edit Auction', true);
?>

<div class="card">
    <h2>Edit Auction</h2>
    <p style="color: #6c757d; margin-bottom: 25px;">Update auction details. Note: You can only edit auctions that have no bids placed yet.</p>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div class="form-group">
            <label>Auction Title *</label>
            <input type="text" name="title" required value="<?php echo htmlspecialchars($auction['title']); ?>">
        </div>
        
        <div class="form-group">
            <label>Description</label>
            <textarea name="description"><?php echo htmlspecialchars($auction['description']); ?></textarea>
        </div>
        
        <div class="form-group">
            <label>Base Price (₹) *</label>
            <input type="number" name="base_price" step="0.01" min="0" required value="<?php echo $auction['base_price']; ?>">
        </div>
        
        <div class="form-group">
            <label>Minimum Bid Increment (₹) *</label>
            <input type="number" name="min_increment" step="0.01" min="0" required value="<?php echo $auction['min_increment']; ?>">
        </div>
        
        <div class="form-group">
            <label>Auction Start Date & Time *</label>
            <input type="datetime-local" name="start_datetime" required value="<?php echo date('Y-m-d\TH:i', strtotime($auction['start_datetime'])); ?>">
        </div>
        
        <div class="form-group">
            <label>Auction End Date & Time *</label>
            <input type="datetime-local" name="end_datetime" required value="<?php echo date('Y-m-d\TH:i', strtotime($auction['end_datetime'])); ?>">
        </div>
        
        <button type="submit" class="btn">Update Auction</button>
        <a href="admin_dashboard.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php renderFooter(); ?>