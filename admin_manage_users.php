<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'functions.php';

requireAdmin();
updateAuctionStatuses($pdo);

$success = '';
$error = '';
$selectedUser = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        $action = $_POST['action'] ?? '';
        $userId = intval($_POST['user_id'] ?? 0);
        
        if ($action === 'send_credentials') {
            // Get user details
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate new temporary password
                $tempPassword = bin2hex(random_bytes(8));
                $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
                
                // Generate password reset token
                $resetToken = bin2hex(random_bytes(32));
                $resetExpires = date('Y-m-d H:i:s', strtotime('+7 days'));
                
                // Update user password and reset token
                $updateStmt = $pdo->prepare("UPDATE users SET password = ?, password_reset_token = ?, password_reset_expires = ? WHERE id = ?");
                $updateStmt->execute([$hashedPassword, $resetToken, $resetExpires, $userId]);
                
                // Send email with credentials
                require_once 'email_templates.php';
                
                // Email settings will be loaded from database via email_sender.php
                // No hardcoded defaults - must be configured in admin panel
                if (!function_exists('sendEmailViaSMTP')) {
                    require_once 'email_sender.php';
                }
                
                $baseUrl = 'https://interlinxpartnering.com/auction-portal_new/auction-portal';
                $resetLink = rtrim($baseUrl, '/') . '/update_password.php?token=' . $resetToken;
                
                $subject = 'Your NIXI Auction Portal Account Credentials';
                $htmlMessage = getCredentialsEmail(
                    $user['name'],
                    $user['email'],
                    $tempPassword,
                    $resetLink
                );
                
                $plainTextMessage = "Hello {$user['name']},\n\n" .
                                   "Your account credentials have been reset.\n\n" .
                                   "Email: {$user['email']}\n" .
                                   "Temporary Password: {$tempPassword}\n\n" .
                                   "Please update your password using this link: {$resetLink}\n\n" .
                                   "This link is valid for 7 days.\n\n" .
                                   "Thank you!";
                
                // Send email
                $emailSent = sendEmail($user['email'], $subject, $htmlMessage, $plainTextMessage);
                
                if ($emailSent) {
                    $success = 'Credentials sent successfully to ' . htmlspecialchars($user['email']) . '!';
                } else {
                    $success = 'Credentials generated successfully! Temporary Password: ' . htmlspecialchars($tempPassword) . ' (Email sending failed - please send manually)';
                }
            } else {
                $error = 'User not found.';
            }
        }
    }
}

// Get user ID for view details
$viewUserId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($viewUserId > 0) {
    $stmt = $pdo->prepare("SELECT u.*, r.registration_id, r.registration_type, r.pan_card_number, r.mobile as reg_mobile, r.date_of_birth, r.payment_status, r.payment_date, r.payment_amount, r.payment_transaction_id
                          FROM users u
                          LEFT JOIN registration r ON u.email = r.email
                          WHERE u.id = ?");
    $stmt->execute([$viewUserId]);
    $selectedUser = $stmt->fetch();
}

// Get all users with registration details
$stmt = $pdo->query("SELECT u.id, u.name, u.email, u.role, u.created_at, 
                    r.registration_id, r.registration_type, r.payment_status,
                    (SELECT COUNT(*) FROM bids WHERE user_id = u.id) as total_bids,
                    (SELECT COUNT(*) FROM auctions WHERE winner_user_id = u.id) as won_auctions
                    FROM users u
                    LEFT JOIN registration r ON u.email = r.email
                    WHERE u.role = 'user'
                    ORDER BY u.created_at DESC");
$users = $stmt->fetchAll();

renderHeader('Manage Users', true);
?>

<div class="card">
    <h2>👥 Manage Users</h2>
    <p style="color: #6c757d; margin-bottom: 25px;">View and manage all registered users</p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($selectedUser): ?>
    <!-- User Details Modal -->
    <div class="card" style="margin-bottom: 30px; background: #f8f9fa;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="color: #1a237e; margin: 0;">📋 User Details</h3>
            <a href="admin_manage_users.php" class="btn btn-secondary">Close</a>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <div>
                <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">Full Name</label>
                <div style="padding: 12px; background: white; border-radius: 8px; color: #2c3e50; font-size: 15px;">
                    <?php echo htmlspecialchars($selectedUser['name']); ?>
                </div>
            </div>
            
            <div>
                <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">Email Address</label>
                <div style="padding: 12px; background: white; border-radius: 8px; color: #2c3e50; font-size: 15px;">
                    <?php echo htmlspecialchars($selectedUser['email']); ?>
                </div>
            </div>
            
            <?php if ($selectedUser['registration_id']): ?>
            <div>
                <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">Registration ID</label>
                <div style="padding: 12px; background: white; border-radius: 8px; color: #1a237e; font-size: 15px; font-weight: 500; font-family: monospace;">
                    <?php echo htmlspecialchars($selectedUser['registration_id']); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($selectedUser['registration_type']): ?>
            <div>
                <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">Registration Type</label>
                <div style="padding: 12px; background: white; border-radius: 8px; color: #2c3e50; font-size: 15px; text-transform: capitalize;">
                    <?php echo htmlspecialchars($selectedUser['registration_type']); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($selectedUser['reg_mobile']): ?>
            <div>
                <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">Mobile Number</label>
                <div style="padding: 12px; background: white; border-radius: 8px; color: #2c3e50; font-size: 15px;">
                    <?php echo htmlspecialchars($selectedUser['reg_mobile']); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($selectedUser['pan_card_number']): ?>
            <div>
                <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">PAN Card Number</label>
                <div style="padding: 12px; background: white; border-radius: 8px; color: #2c3e50; font-size: 15px; font-family: monospace;">
                    <?php echo htmlspecialchars($selectedUser['pan_card_number']); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($selectedUser['date_of_birth']): ?>
            <div>
                <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">Date of Birth</label>
                <div style="padding: 12px; background: white; border-radius: 8px; color: #2c3e50; font-size: 15px;">
                    <?php echo date('d-M-Y', strtotime($selectedUser['date_of_birth'])); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($selectedUser['payment_status']): ?>
            <div>
                <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">Payment Status</label>
                <div style="padding: 12px; background: <?php echo $selectedUser['payment_status'] === 'success' ? '#e8f5e9' : '#fff3e0'; ?>; border-radius: 8px; color: <?php echo $selectedUser['payment_status'] === 'success' ? '#2e7d32' : '#e65100'; ?>; font-size: 15px; font-weight: 500; text-transform: capitalize;">
                    <?php echo $selectedUser['payment_status'] === 'success' ? '✓ Paid' : ucfirst($selectedUser['payment_status']); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($selectedUser['payment_amount']): ?>
            <div>
                <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">Payment Amount</label>
                <div style="padding: 12px; background: white; border-radius: 8px; color: #2c3e50; font-size: 15px; font-weight: 500;">
                    ₹<?php echo number_format($selectedUser['payment_amount'], 2); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($selectedUser['payment_date']): ?>
            <div>
                <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">Payment Date</label>
                <div style="padding: 12px; background: white; border-radius: 8px; color: #2c3e50; font-size: 15px;">
                    <?php echo date('d-M-Y H:i', strtotime($selectedUser['payment_date'])); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div>
                <label style="display: block; color: #6c757d; font-size: 14px; margin-bottom: 5px;">Account Created</label>
                <div style="padding: 12px; background: white; border-radius: 8px; color: #2c3e50; font-size: 15px;">
                    <?php echo date('d-M-Y H:i', strtotime($selectedUser['created_at'])); ?>
                </div>
            </div>
        </div>
        
        <div style="margin-top: 25px; padding-top: 25px; border-top: 2px solid #e9ecef;">
            <form method="POST" style="display: inline-block;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="send_credentials">
                <input type="hidden" name="user_id" value="<?php echo $selectedUser['id']; ?>">
                <button type="submit" class="btn btn-success" onclick="return confirm('This will generate new credentials and send them to the user. Continue?');">
                    📧 Send Credentials Again
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Users List -->
<div class="card">
    <h3 style="color: #1a237e; margin-bottom: 20px;">All Users (<?php echo count($users); ?>)</h3>
    
    <?php if (empty($users)): ?>
        <p style="color: #6c757d; text-align: center; padding: 40px;">No users found.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Registration ID</th>
                    <th>Payment Status</th>
                    <th>Total Bids</th>
                    <th>Won Auctions</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <?php if ($user['registration_id']): ?>
                                <span style="font-family: monospace; color: #1a237e;"><?php echo htmlspecialchars($user['registration_id']); ?></span>
                            <?php else: ?>
                                <span style="color: #6c757d;">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($user['payment_status']): ?>
                                <span class="badge <?php echo $user['payment_status'] === 'success' ? 'badge-active' : 'badge-upcoming'; ?>">
                                    <?php echo $user['payment_status'] === 'success' ? 'Paid' : ucfirst($user['payment_status']); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #6c757d;">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $user['total_bids']; ?></td>
                        <td><?php echo $user['won_auctions']; ?></td>
                        <td><?php echo date('d-M-Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <a href="admin_manage_users.php?id=<?php echo $user['id']; ?>" class="btn" style="padding: 6px 12px; font-size: 13px;">View Details</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php renderFooter(); ?>

