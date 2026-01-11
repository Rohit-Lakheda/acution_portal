
<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

// Mark all as read if requested
if (isset($_GET['mark_read'])) {
    $stmt = $pdo->prepare("UPDATE notifications SET read_status = 1 WHERE user_id = ?");
    $stmt->execute([getCurrentUserId()]);
}

// Get notifications
$stmt = $pdo->prepare("SELECT * FROM notifications 
                       WHERE user_id = ? 
                       ORDER BY created_at DESC");
$stmt->execute([getCurrentUserId()]);
$notifications = $stmt->fetchAll();

// Count unread
$stmt = $pdo->prepare("SELECT COUNT(*) as unread FROM notifications 
                       WHERE user_id = ? AND read_status = 0");
$stmt->execute([getCurrentUserId()]);
$unreadCount = $stmt->fetch()['unread'];

renderHeader('Notifications', false);
?>

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h2>🔔 Notifications</h2>
            <p style="color: #6c757d; margin-top: 5px;">Stay updated with your auction activities, bid status, and important updates.</p>
            <?php if ($unreadCount > 0): ?>
                <p style="color: #c62828; margin-top: 10px; font-weight: 500;"><?php echo $unreadCount; ?> unread notification<?php echo $unreadCount > 1 ? 's' : ''; ?></p>
            <?php endif; ?>
        </div>
        <?php if ($unreadCount > 0): ?>
            <a href="?mark_read=1" class="btn btn-secondary">Mark All as Read</a>
        <?php endif; ?>
    </div>
</div>

<?php if (empty($notifications)): ?>
    <div class="card">
        <p style="color: #718096; text-align: center;">No notifications yet.</p>
    </div>
<?php else: ?>
    <?php foreach ($notifications as $notification): ?>
        <div class="card" style="<?php echo $notification['read_status'] == 0 ? 'background: #fef3c7; border-left: 4px solid #f59e0b;' : ''; ?>">
            <div style="display: flex; justify-content: space-between; align-items: start;">
                <div style="flex: 1;">
                    <p style="color: #2d3748; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($notification['message'])); ?></p>
                    <p style="color: #718096; font-size: 13px; margin-top: 10px;">
                        <?php echo date('d-M-Y H:i:s', strtotime($notification['created_at'])); ?>
                    </p>
                </div>
                <?php if ($notification['read_status'] == 0): ?>
                    <span style="background: #f59e0b; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">NEW</span>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php renderFooter(); ?>