<?php
/**
 * Log Viewer - View Payment and Registration Logs
 * Accessible via: https://interlinxpartnering.com/auction-portal_new/auction-portal/view_logs.php
 */

session_start();
require_once 'database.php';
require_once 'logger.php';

// Simple authentication (you can enhance this)
$logViewerPassword = 'admin123'; // Change this password
$isAuthenticated = false;

if (isset($_POST['password'])) {
    if ($_POST['password'] === $logViewerPassword) {
        $_SESSION['log_viewer_auth'] = true;
        $isAuthenticated = true;
    } else {
        $error = 'Invalid password';
    }
} elseif (isset($_SESSION['log_viewer_auth']) && $_SESSION['log_viewer_auth'] === true) {
    $isAuthenticated = true;
}

if (!$isAuthenticated) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="https://fonts.googleapis.com/css2?family=Varela+Round&display=swap" rel="stylesheet">
        <title>Log Viewer - Authentication</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Varela Round', sans-serif;
                background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .container {
                background: white;
                padding: 40px;
                border-radius: 12px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.15);
                max-width: 400px;
                width: 100%;
            }
            h1 {
                color: #1a237e;
                margin-bottom: 20px;
                text-align: center;
                font-weight: 400;
            }
            input {
                width: 100%;
                padding: 12px;
                border: 2px solid #e9ecef;
                border-radius: 8px;
                margin-bottom: 15px;
                font-family: 'Varela Round', sans-serif;
            }
            button {
                width: 100%;
                padding: 12px;
                background: #1a237e;
                color: white;
                border: none;
                border-radius: 8px;
                font-family: 'Varela Round', sans-serif;
                cursor: pointer;
            }
            .error {
                background: #ffebee;
                color: #c62828;
                padding: 12px;
                border-radius: 8px;
                margin-bottom: 15px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Log Viewer Access</h1>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="password" name="password" placeholder="Enter password" required>
                <button type="submit">Access Logs</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Get filter parameters
$type = $_GET['type'] ?? null;
$transactionId = $_GET['transaction_id'] ?? null;
$registrationId = $_GET['registration_id'] ?? null;
$limit = intval($_GET['limit'] ?? 100);

// Get logs
$logs = getLogs($type, $limit, $transactionId, $registrationId);

// Get log statistics
$stats = [];
$stats['total'] = $pdo->query("SELECT COUNT(*) as total FROM payment_logs")->fetch()['total'];
$stats['payment_success'] = $pdo->query("SELECT COUNT(*) as total FROM payment_logs WHERE log_type = 'payment_success'")->fetch()['total'];
$stats['payment_failed'] = $pdo->query("SELECT COUNT(*) as total FROM payment_logs WHERE log_type = 'payment_failed'")->fetch()['total'];
$stats['registration_error'] = $pdo->query("SELECT COUNT(*) as total FROM payment_logs WHERE log_type = 'registration_error'")->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Varela+Round&display=swap" rel="stylesheet">
    <title>Payment Logs Viewer</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Varela Round', sans-serif;
            background: #f8f9fa;
            padding: 20px;
            color: #2c3e50;
        }
        .header {
            background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            color: #1a237e;
            font-size: 24px;
            margin-bottom: 5px;
        }
        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .filters form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .filters input, .filters select {
            padding: 10px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-family: 'Varela Round', sans-serif;
        }
        .logs-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        th {
            background: #f8f9fa;
            color: #1a237e;
            font-weight: 400;
        }
        .log-type {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .type-payment_success { background: #e8f5e9; color: #2e7d32; }
        .type-payment_failed { background: #ffebee; color: #c62828; }
        .type-registration_error { background: #fff3e0; color: #e65100; }
        .type-payment_callback { background: #e3f2fd; color: #1565c0; }
        .data-preview {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .data-full {
            display: none;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin-top: 5px;
            font-family: monospace;
            font-size: 12px;
            white-space: pre-wrap;
            max-height: 300px;
            overflow-y: auto;
        }
        .toggle-data {
            color: #1a237e;
            cursor: pointer;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 Payment & Registration Logs</h1>
        <p>Real-time log viewer for payment transactions and registration processes</p>
    </div>
    
    <div class="stats">
        <div class="stat-card">
            <h3><?php echo $stats['total']; ?></h3>
            <p>Total Logs</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $stats['payment_success']; ?></h3>
            <p>Payment Success</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $stats['payment_failed']; ?></h3>
            <p>Payment Failed</p>
        </div>
        <div class="stat-card">
            <h3><?php echo $stats['registration_error']; ?></h3>
            <p>Registration Errors</p>
        </div>
    </div>
    
    <div class="filters">
        <form method="GET">
            <select name="type">
                <option value="">All Types</option>
                <option value="payment_success" <?php echo $type === 'payment_success' ? 'selected' : ''; ?>>Payment Success</option>
                <option value="payment_failed" <?php echo $type === 'payment_failed' ? 'selected' : ''; ?>>Payment Failed</option>
                <option value="payment_callback" <?php echo $type === 'payment_callback' ? 'selected' : ''; ?>>Payment Callback</option>
                <option value="registration_error" <?php echo $type === 'registration_error' ? 'selected' : ''; ?>>Registration Error</option>
                <option value="registration_saved" <?php echo $type === 'registration_saved' ? 'selected' : ''; ?>>Registration Saved</option>
            </select>
            <input type="text" name="transaction_id" placeholder="Transaction ID" value="<?php echo htmlspecialchars($transactionId ?? ''); ?>">
            <input type="text" name="registration_id" placeholder="Registration ID" value="<?php echo htmlspecialchars($registrationId ?? ''); ?>">
            <input type="number" name="limit" placeholder="Limit" value="<?php echo $limit; ?>" min="10" max="500">
            <button type="submit" style="padding: 10px 20px; background: #1a237e; color: white; border: none; border-radius: 8px; cursor: pointer;">Filter</button>
            <a href="view_logs.php" style="padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 8px; display: inline-block;">Clear</a>
        </form>
    </div>
    
    <div class="logs-table">
        <table>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Type</th>
                    <th>Transaction ID</th>
                    <th>Registration ID</th>
                    <th>Message</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: #6c757d;">No logs found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                            <td><span class="log-type type-<?php echo htmlspecialchars($log['log_type']); ?>"><?php echo htmlspecialchars($log['log_type']); ?></span></td>
                            <td><?php echo htmlspecialchars($log['transaction_id'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($log['registration_id'] ?: '-'); ?></td>
                            <td><?php echo htmlspecialchars($log['message']); ?></td>
                            <td>
                                <?php if ($log['data']): ?>
                                    <span class="toggle-data" onclick="toggleData(<?php echo $log['id']; ?>)">View Data</span>
                                    <div id="data-<?php echo $log['id']; ?>" class="data-full"><?php echo htmlspecialchars($log['data']); ?></div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <script>
        function toggleData(id) {
            const elem = document.getElementById('data-' + id);
            if (elem.style.display === 'block') {
                elem.style.display = 'none';
            } else {
                elem.style.display = 'block';
            }
        }
        
        // Auto-refresh every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>

