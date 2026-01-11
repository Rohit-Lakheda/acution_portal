<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();
updateAuctionStatuses($pdo);

$success = '';
$error = '';

// Get current user details
$userId = getCurrentUserId();
$stmt = $pdo->prepare("SELECT u.*, r.registration_id, r.registration_type, r.pan_card_number, r.mobile as reg_mobile, r.date_of_birth, r.payment_status, r.payment_date, r.payment_amount, r.payment_transaction_id
                      FROM users u
                      LEFT JOIN registration r ON u.email = r.email
                      WHERE u.id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: user_auctions.php');
    exit;
}

// Get user statistics
$stats = [];
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM bids WHERE user_id = ?");
$stmt->execute([$userId]);
$stats['total_bids'] = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM auctions WHERE winner_user_id = ? AND status = 'closed'");
$stmt->execute([$userId]);
$stats['won_auctions'] = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(DISTINCT auction_id) as total FROM bids WHERE user_id = ?");
$stmt->execute([$userId]);
$stats['auctions_bid_on'] = $stmt->fetch()['total'];

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = 'All password fields are required.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        // Verify current password
        if (password_verify($currentPassword, $user['password'])) {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $updateStmt->execute([$hashedPassword, $userId]);
            $success = 'Password updated successfully!';
        } else {
            $error = 'Current password is incorrect.';
        }
    }
}

renderHeader('My Profile', false);
?>

<div class="card">
    <h2>👤 My Profile</h2>
    <p style="color: #6c757d; margin-bottom: 25px;">View and manage your account information</p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- User Statistics -->
<div class="grid" style="margin-bottom: 30px;">
    <div class="card" style="text-align: center; background: linear-gradient(135deg, #1a237e 0%, #283593 100%); color: white;">
        <h3 style="color: white; font-size: 32px; margin-bottom: 10px;"><?php echo $stats['total_bids']; ?></h3>
        <p style="color: rgba(255,255,255,0.9); margin: 0;">Total Bids</p>
    </div>
    <div class="card" style="text-align: center; background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%); color: white;">
        <h3 style="color: white; font-size: 32px; margin-bottom: 10px;"><?php echo $stats['won_auctions']; ?></h3>
        <p style="color: rgba(255,255,255,0.9); margin: 0;">Won Auctions</p>
    </div>
    <div class="card" style="text-align: center; background: linear-gradient(135deg, #1565c0 0%, #0d47a1 100%); color: white;">
        <h3 style="color: white; font-size: 32px; margin-bottom: 10px;"><?php echo $stats['auctions_bid_on']; ?></h3>
        <p style="color: rgba(255,255,255,0.9); margin: 0;">Auctions Participated</p>
    </div>
</div>

<!-- Profile Information -->
<div class="card">
    <h3 style="color: #1a237e; margin-bottom: 20px; font-size: 22px;">📋 Personal Information</h3>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        <div>
            <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">Full Name</label>
            <div style="padding: 12px; background: #f8f9fa; border-radius: 8px; color: #2c3e50; font-size: 15px;">
                <?php echo htmlspecialchars($user['name']); ?>
            </div>
        </div>
        
        <div>
            <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">Email Address</label>
            <div style="padding: 12px; background: #f8f9fa; border-radius: 8px; color: #2c3e50; font-size: 15px;">
                <?php echo htmlspecialchars($user['email']); ?>
            </div>
        </div>
        
        <?php if ($user['registration_id']): ?>
        <div>
            <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">Registration ID</label>
            <div style="padding: 12px; background: #f8f9fa; border-radius: 8px; color: #1a237e; font-size: 15px; font-weight: 500; font-family: monospace;">
                <?php echo htmlspecialchars($user['registration_id']); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($user['registration_type']): ?>
        <div>
            <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">Registration Type</label>
            <div style="padding: 12px; background: #f8f9fa; border-radius: 8px; color: #2c3e50; font-size: 15px; text-transform: capitalize;">
                <?php echo htmlspecialchars($user['registration_type']); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($user['reg_mobile']): ?>
        <div>
            <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">Mobile Number</label>
            <div style="padding: 12px; background: #f8f9fa; border-radius: 8px; color: #2c3e50; font-size: 15px;">
                <?php echo htmlspecialchars($user['reg_mobile']); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($user['pan_card_number']): ?>
        <div>
            <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">PAN Card Number</label>
            <div style="padding: 12px; background: #f8f9fa; border-radius: 8px; color: #2c3e50; font-size: 15px; font-family: monospace;">
                <?php echo htmlspecialchars($user['pan_card_number']); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($user['date_of_birth']): ?>
        <div>
            <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">Date of Birth</label>
            <div style="padding: 12px; background: #f8f9fa; border-radius: 8px; color: #2c3e50; font-size: 15px;">
                <?php echo date('d-M-Y', strtotime($user['date_of_birth'])); ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($user['payment_status']): ?>
        <div>
            <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">Payment Status</label>
            <div style="padding: 12px; background: <?php echo $user['payment_status'] === 'success' ? '#e8f5e9' : '#fff3e0'; ?>; border-radius: 8px; color: <?php echo $user['payment_status'] === 'success' ? '#2e7d32' : '#e65100'; ?>; font-size: 15px; font-weight: 500; text-transform: capitalize; display: flex; align-items: center; justify-content: space-between;">
                <span><?php echo $user['payment_status'] === 'success' ? '✓ Paid' : ucfirst($user['payment_status']); ?></span>
                <?php if ($user['payment_status'] === 'success'): ?>
                    <a href="download_registration_invoice.php" class="btn" style="background: #1a237e; color: white; padding: 8px 16px; text-decoration: none; border-radius: 5px; font-size: 13px; font-weight: 500;">
                        📄 Download Invoice
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div>
            <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">Account Created</label>
            <div style="padding: 12px; background: #f8f9fa; border-radius: 8px; color: #2c3e50; font-size: 15px;">
                <?php echo date('d-M-Y H:i', strtotime($user['created_at'])); ?>
            </div>
        </div>
    </div>
</div>

<!-- Change Password Section -->
<div class="card">
    <h3 style="color: #1a237e; margin-bottom: 20px; font-size: 22px;">🔒 Change Password</h3>
    
    <form method="POST" style="max-width: 500px;">
        <input type="hidden" name="update_password" value="1">
        
        <div class="form-group">
            <label>Current Password *</label>
            <input type="password" name="current_password" required>
        </div>
        
        <div class="form-group">
            <label>New Password *</label>
            <input type="password" name="new_password" required minlength="8">
            <small style="color: #6c757d; font-size: 13px;">Must be at least 8 characters long</small>
        </div>
        
        <div class="form-group">
            <label>Confirm New Password *</label>
            <input type="password" name="confirm_password" required minlength="8">
        </div>
        
        <button type="submit" class="btn">Update Password</button>
    </form>
</div>

<?php renderFooter(); ?>

