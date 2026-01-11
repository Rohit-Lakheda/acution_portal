
<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'functions.php';

requireAdmin();

$error = '';
$success = '';
$preview = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } elseif (isset($_POST['confirm_import']) && !empty($_SESSION['import_data'])) {
        // Confirm and import
        try {
            $pdo->beginTransaction();
            
            foreach ($_SESSION['import_data'] as $row) {
                $stmt = $pdo->prepare("INSERT INTO auctions (title, description, base_price, min_increment, start_datetime, end_datetime, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $row['title'],
                    $row['description'],
                    $row['base_price'],
                    $row['min_increment'],
                    $row['start_datetime'],
                    $row['end_datetime'],
                    getCurrentUserId()
                ]);
            }
            
            $pdo->commit();
            $success = count($_SESSION['import_data']) . ' auctions imported successfully!';
            unset($_SESSION['import_data']);
            $preview = [];
        } catch(PDOException $e) {
            $pdo->rollBack();
            $error = 'Import failed. Please try again.';
        }
    } elseif (isset($_FILES['excel_file'])) {
        // Process file upload
        $file = $_FILES['excel_file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error = 'File upload failed';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($ext, ['csv', 'xlsx'])) {
                $error = 'Only CSV and XLSX files are allowed';
            } else {
                try {
                    if ($ext === 'csv') {
                        // Process CSV
                        $handle = fopen($file['tmp_name'], 'r');
                        $headers = fgetcsv($handle);
                        
                        while (($row = fgetcsv($handle)) !== false) {
                            if (count($row) >= 6) {
                                $preview[] = [
                                    'title' => $row[0],
                                    'description' => $row[1],
                                    'base_price' => floatval($row[2]),
                                    'min_increment' => floatval($row[3]),
                                    'start_datetime' => $row[4],
                                    'end_datetime' => $row[5]
                                ];
                            }
                        }
                        fclose($handle);
                    } else {
                        // For XLSX, you'd need PHPSpreadsheet library
                        // This is a basic implementation that requires the file to be converted to CSV first
                        $error = 'XLSX support requires PHPSpreadsheet library. Please use CSV format.';
                    }
                    
                    if (!empty($preview)) {
                        $_SESSION['import_data'] = $preview;
                    } else {
                        $error = 'No valid data found in file';
                    }
                } catch(Exception $e) {
                    $error = 'Failed to process file';
                }
            }
        }
    }
}

renderHeader('Upload Excel', true);
?>

<div class="card">
    <h2>Upload Auctions (Excel/CSV)</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <div class="alert alert-info">
        <strong>CSV Format Required:</strong><br>
        Columns: item_title, item_description, base_price, min_increment, start_datetime (YYYY-MM-DD HH:MM:SS), end_datetime (YYYY-MM-DD HH:MM:SS)<br>
        <a href="sample_auction_template.csv" download style="color: #2c5282; font-weight: bold;">Download Sample Template</a>
    </div>
    
    <?php if (empty($preview)): ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-group">
                <label>Select Excel/CSV File *</label>
                <input type="file" name="excel_file" accept=".csv,.xlsx" required>
            </div>
            
            <button type="submit" class="btn">Upload & Preview</button>
            <a href="admin_dashboard.php" class="btn btn-secondary">Cancel</a>
        </form>
    <?php else: ?>
        <h3>Preview Data (<?php echo count($preview); ?> rows)</h3>
        <div style="max-height: 400px; overflow-y: auto; margin: 20px 0;">
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Base Price</th>
                        <th>Min Increment</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preview as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                        <td><?php echo htmlspecialchars(substr($row['description'], 0, 50)) . '...'; ?></td>
                        <td><?php echo formatINR($row['base_price']); ?></td>
                        <td><?php echo formatINR($row['min_increment']); ?></td>
                        <td><?php echo htmlspecialchars($row['start_datetime']); ?></td>
                        <td><?php echo htmlspecialchars($row['end_datetime']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="confirm_import" value="1">
            <button type="submit" class="btn btn-success">✓ Confirm Import</button>
            <a href="admin_upload_excel.php" class="btn btn-secondary">Cancel</a>
        </form>
    <?php endif; ?>
</div>

<?php renderFooter(); ?>
