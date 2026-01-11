
<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'functions.php';

requireAdmin();

$error = '';
$success = '';

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
        
        if (empty($title) || empty($base_price) || empty($min_increment) || empty($start_datetime) || empty($end_datetime)) {
            $error = 'All fields are required';
        } elseif ($base_price <= 0 || $min_increment <= 0) {
            $error = 'Prices must be greater than zero';
        } elseif (strtotime($end_datetime) <= strtotime($start_datetime)) {
            $error = 'End date must be after start date';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO auctions (title, description, base_price, min_increment, start_datetime, end_datetime, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $description, $base_price, $min_increment, $start_datetime, $end_datetime, getCurrentUserId()]);
                
                $success = 'Auction created successfully!';
                $_POST = []; // Clear form
            } catch(PDOException $e) {
                $error = 'Failed to create auction. Please try again.';
            }
        }
    }
}

renderHeader('Add Auction', true);
?>

<div class="card">
    <h2>Add New Auction</h2>
    <p style="color: #6c757d; margin-bottom: 25px;">Create a new auction. Fill in all required details. The auction will automatically become active when the start date/time is reached.</p>
    
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
            <input type="text" name="title" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label>Description</label>
            <textarea name="description"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="form-group">
            <label>Base Price (₹) *</label>
            <input type="number" name="base_price" step="0.01" min="0" required value="<?php echo htmlspecialchars($_POST['base_price'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label>Minimum Bid Increment (₹) *</label>
            <input type="number" name="min_increment" step="0.01" min="0" required value="<?php echo htmlspecialchars($_POST['min_increment'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label>Auction Start Date & Time *</label>
            <input type="datetime-local" name="start_datetime" required value="<?php echo htmlspecialchars($_POST['start_datetime'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label>Auction End Date & Time *</label>
            <input type="datetime-local" name="end_datetime" required value="<?php echo htmlspecialchars($_POST['end_datetime'] ?? ''); ?>">
        </div>
        
        <button type="submit" class="btn">Create Auction</button>
        <a href="admin_dashboard.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<?php renderFooter(); ?>