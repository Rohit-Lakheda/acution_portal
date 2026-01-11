<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'functions.php';

requireAdmin();

$error = '';
$success = '';
$activeTab = $_GET['tab'] ?? 'registration';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        $formType = $_POST['form_type'] ?? '';
        
        if ($formType === 'registration_amount') {
            $registrationAmount = floatval($_POST['registration_amount'] ?? 0);
            
            if ($registrationAmount <= 0) {
                $error = 'Registration amount must be greater than zero';
            } else {
                try {
                    // Update or insert registration amount setting
                    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, description, updated_by) 
                                          VALUES ('registration_amount', ?, 'Registration fee amount in INR', ?)
                                          ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP");
                    $stmt->execute([number_format($registrationAmount, 2, '.', ''), getCurrentUserId(), number_format($registrationAmount, 2, '.', ''), getCurrentUserId()]);
                    
                    $success = 'Registration amount updated successfully!';
                    $activeTab = 'registration';
                } catch(PDOException $e) {
                    $error = 'Failed to update registration amount. Please try again.';
                    if (DEBUG_MODE ?? false) {
                        $error .= ' Error: ' . $e->getMessage();
                    }
                }
            }
        } elseif ($formType === 'email_settings') {
            // Handle email settings update
            $smtpHost = trim($_POST['smtp_host'] ?? '');
            $smtpPort = intval($_POST['smtp_port'] ?? 587);
            $smtpUsername = trim($_POST['smtp_username'] ?? '');
            $smtpPassword = trim($_POST['smtp_password'] ?? '');
            $fromEmail = trim($_POST['from_email'] ?? '');
            $fromName = trim($_POST['from_name'] ?? '');
            $encryption = trim($_POST['encryption'] ?? 'tls');
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            
            // Validation
            if (empty($smtpHost) || empty($smtpUsername) || empty($fromEmail)) {
                $error = 'Please fill in all required email fields (SMTP Host, Username, and From Email).';
            } elseif (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid From Email address.';
            } elseif ($smtpPort < 1 || $smtpPort > 65535) {
                $error = 'Invalid SMTP port. Must be between 1 and 65535.';
            } else {
                try {
                    // Create table if it doesn't exist
                    $pdo->exec("CREATE TABLE IF NOT EXISTS email_settings (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        smtp_host VARCHAR(255) NOT NULL DEFAULT 'smtp.elasticemail.com',
                        smtp_port INT NOT NULL DEFAULT 587,
                        smtp_username VARCHAR(255) NOT NULL,
                        smtp_password VARCHAR(255) NOT NULL,
                        from_email VARCHAR(255) NOT NULL,
                        from_name VARCHAR(255) NOT NULL DEFAULT 'NIXI Auction Portal',
                        encryption VARCHAR(20) DEFAULT 'tls',
                        is_active TINYINT(1) DEFAULT 1,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        updated_by INT NULL,
                        INDEX idx_is_active (is_active)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                    
                    // Check if record exists
                    $checkStmt = $pdo->prepare("SELECT id, smtp_password FROM email_settings WHERE is_active = 1 LIMIT 1");
                    $checkStmt->execute();
                    $existing = $checkStmt->fetch();
                    
                    // If password is empty, keep existing password
                    if (empty($smtpPassword) && $existing) {
                        $smtpPassword = $existing['smtp_password'];
                    } elseif (empty($smtpPassword) && !$existing) {
                        $error = 'Password is required for new email configuration.';
                        throw new Exception($error);
                    }
                    
                    if ($existing) {
                        // Update existing active record
                        $stmt = $pdo->prepare("UPDATE email_settings 
                                              SET smtp_host = ?, smtp_port = ?, smtp_username = ?, 
                                                  smtp_password = ?, from_email = ?, from_name = ?, 
                                                  encryption = ?, is_active = ?, updated_by = ?, updated_at = NOW()
                                              WHERE id = ?");
                        $stmt->execute([$smtpHost, $smtpPort, $smtpUsername, $smtpPassword, $fromEmail, $fromName, $encryption, $isActive, getCurrentUserId(), $existing['id']]);
                    } else {
                        // Insert new record
                        $stmt = $pdo->prepare("INSERT INTO email_settings 
                                              (smtp_host, smtp_port, smtp_username, smtp_password, from_email, from_name, encryption, is_active, updated_by) 
                                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$smtpHost, $smtpPort, $smtpUsername, $smtpPassword, $fromEmail, $fromName, $encryption, $isActive, getCurrentUserId()]);
                    }
                    
                    // Clear email settings cache to force reload
                    if (function_exists('clearEmailSettingsCache')) {
                        clearEmailSettingsCache();
                    }
                    
                    // Also clear the static cache in getEmailSettings by forcing reload
                    if (function_exists('getEmailSettings')) {
                        getEmailSettings(null, true);
                    }
                    
                    $success = 'Email settings updated successfully!';
                    $activeTab = 'email';
                } catch(Exception $e) {
                    if (empty($error)) {
                        $error = $e->getMessage();
                    }
                } catch(PDOException $e) {
                    $error = 'Failed to update email settings. Please try again.';
                    if (DEBUG_MODE ?? false) {
                        $error .= ' Error: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

// Get current registration amount
$registrationAmount = 500.00; // Default
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'registration_amount'");
    $stmt->execute();
    $result = $stmt->fetch();
    if ($result) {
        $registrationAmount = floatval($result['setting_value']);
    }
} catch(PDOException $e) {
    // Use default if table doesn't exist yet
}

// Get current email settings from database only (no defaults)
$emailSettings = [
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_username' => '',
    'smtp_password' => '',
    'from_email' => '',
    'from_name' => 'NIXI Auction Portal',
    'encryption' => 'tls',
    'is_active' => 1
];

try {
    $stmt = $pdo->prepare("SELECT * FROM email_settings WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch();
    if ($result) {
        $emailSettings = [
            'smtp_host' => $result['smtp_host'],
            'smtp_port' => $result['smtp_port'],
            'smtp_username' => $result['smtp_username'],
            'smtp_password' => $result['smtp_password'], // Will be shown as empty for security, but can be updated
            'from_email' => $result['from_email'],
            'from_name' => $result['from_name'],
            'encryption' => $result['encryption'] ?? 'tls',
            'is_active' => $result['is_active']
        ];
    }
} catch(PDOException $e) {
    // No settings found in database - form will show empty fields
    // User must configure email settings
}

renderHeader('Admin Settings', true);
?>

<style>
    .tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 30px;
        border-bottom: 2px solid #e9ecef;
    }
    .tab {
        padding: 12px 24px;
        background: transparent;
        border: none;
        border-bottom: 3px solid transparent;
        cursor: pointer;
        font-size: 16px;
        color: #6c757d;
        transition: all 0.3s;
        font-family: 'Varela Round', sans-serif;
    }
    .tab:hover {
        color: #1a237e;
    }
    .tab.active {
        color: #1a237e;
        border-bottom-color: #1a237e;
        font-weight: 500;
    }
    .tab-content {
        display: none;
    }
    .tab-content.active {
        display: block;
    }
    .form-group small {
        display: block;
        margin-top: 5px;
        color: #6c757d;
        font-size: 13px;
    }
    .password-field {
        position: relative;
    }
    .password-toggle {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #1a237e;
        cursor: pointer;
        font-size: 14px;
        padding: 5px;
    }
    .password-toggle:hover {
        opacity: 0.7;
    }
</style>

<div class="card">
    <h2>Admin Settings</h2>
    
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <div class="tabs">
        <button type="button" class="tab <?php echo $activeTab === 'registration' ? 'active' : ''; ?>" onclick="switchTab('registration')">
            Registration Settings
        </button>
        <button type="button" class="tab <?php echo $activeTab === 'email' ? 'active' : ''; ?>" onclick="switchTab('email')">
            Email Settings
        </button>
    </div>
    
    <!-- Registration Amount Tab -->
    <div id="tab-registration" class="tab-content <?php echo $activeTab === 'registration' ? 'active' : ''; ?>">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="form_type" value="registration_amount">
            
            <div class="form-group">
                <label>Registration Amount (₹) *</label>
                <input type="number" name="registration_amount" step="0.01" min="0" required value="<?php echo number_format($registrationAmount, 2, '.', ''); ?>">
                <small>This is the amount users need to pay for registration. You can update this anytime.</small>
            </div>
            
            <button type="submit" class="btn">Update Registration Amount</button>
        </form>
    </div>
    
    <!-- Email Settings Tab -->
    <div id="tab-email" class="tab-content <?php echo $activeTab === 'email' ? 'active' : ''; ?>">
        <form method="POST" id="emailSettingsForm">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            <input type="hidden" name="form_type" value="email_settings">
            
            <div class="form-group">
                <label>SMTP Host *</label>
                <input type="text" name="smtp_host" required value="<?php echo htmlspecialchars($emailSettings['smtp_host']); ?>" placeholder="smtp.example.com">
                <small>The SMTP server hostname (e.g., smtp.gmail.com, smtp.elasticemail.com)</small>
            </div>
            
            <div class="form-group">
                <label>SMTP Port *</label>
                <input type="number" name="smtp_port" required value="<?php echo htmlspecialchars($emailSettings['smtp_port']); ?>" min="1" max="65535" placeholder="587">
                <small>Common ports: 587 (TLS), 465 (SSL), 25 (unencrypted)</small>
            </div>
            
            <div class="form-group">
                <label>SMTP Username *</label>
                <input type="text" name="smtp_username" required value="<?php echo htmlspecialchars($emailSettings['smtp_username']); ?>" placeholder="your-email@example.com">
                <small>Your SMTP authentication username (usually your email address)</small>
            </div>
            
            <div class="form-group">
                <label>SMTP Password <?php echo empty($emailSettings['smtp_password']) ? '*' : ''; ?></label>
                <div class="password-field">
                    <input type="password" name="smtp_password" id="smtp_password" <?php echo empty($emailSettings['smtp_password']) ? 'required' : ''; ?> placeholder="<?php echo empty($emailSettings['smtp_password']) ? 'Enter SMTP password' : 'Leave blank to keep current password'; ?>" autocomplete="new-password">
                    <button type="button" class="password-toggle" onclick="togglePassword('smtp_password')">👁️</button>
                </div>
                <small><?php echo empty($emailSettings['smtp_password']) ? 'Enter your SMTP password' : 'Leave blank to keep current password, or enter new password to update'; ?></small>
            </div>
            
            <div class="form-group">
                <label>From Email Address *</label>
                <input type="email" name="from_email" required value="<?php echo htmlspecialchars($emailSettings['from_email']); ?>" placeholder="noreply@example.com">
                <small>The email address that will appear as the sender</small>
            </div>
            
            <div class="form-group">
                <label>From Name</label>
                <input type="text" name="from_name" value="<?php echo htmlspecialchars($emailSettings['from_name']); ?>" placeholder="NIXI Auction Portal">
                <small>The display name that will appear as the sender</small>
            </div>
            
            <div class="form-group">
                <label>Encryption Type *</label>
                <select name="encryption" required>
                    <option value="tls" <?php echo $emailSettings['encryption'] === 'tls' ? 'selected' : ''; ?>>TLS (Recommended)</option>
                    <option value="ssl" <?php echo $emailSettings['encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    <option value="none" <?php echo $emailSettings['encryption'] === 'none' ? 'selected' : ''; ?>>None (Not Recommended)</option>
                </select>
                <small>Choose the encryption method for SMTP connection</small>
            </div>
            
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px;">
                    <input type="checkbox" name="is_active" value="1" <?php echo $emailSettings['is_active'] ? 'checked' : ''; ?>>
                    <span>Active (Enable this email configuration)</span>
                </label>
                <small>Uncheck to disable this email configuration without deleting it</small>
            </div>
            
            <button type="submit" class="btn">Update Email Settings</button>
            <button type="button" class="btn btn-secondary" onclick="testEmailSettings()">Test Email Configuration</button>
        </form>
    </div>
    
    <div style="margin-top: 30px;">
        <a href="admin_dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
</div>

<script>
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById('tab-' + tabName).classList.add('active');
    event.target.classList.add('active');
    
    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.pushState({}, '', url);
}

function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.nextElementSibling;
    if (input.type === 'password') {
        input.type = 'text';
        button.textContent = '🙈';
    } else {
        input.type = 'password';
        button.textContent = '👁️';
    }
}

function testEmailSettings() {
    if (!confirm('This will send a test email to your "From Email" address. Continue?')) {
        return;
    }
    
    const form = document.getElementById('emailSettingsForm');
    const formData = new FormData(form);
    formData.append('test_email', '1');
    
    // Show loading
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Testing...';
    
    // Use current page's directory for the test_email.php path
    const currentPath = window.location.pathname;
    const basePath = currentPath.substring(0, currentPath.lastIndexOf('/') + 1);
    const testEmailUrl = basePath + 'test_email.php';
    
    fetch(testEmailUrl, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Check for 404 or other errors
        if (response.status === 404) {
            throw new Error('test_email.php file not found on server. Please make sure the file is uploaded to: ' + testEmailUrl);
        }
        
        if (!response.ok) {
            return response.text().then(text => {
                throw new Error('Server error (' + response.status + '): ' + text.substring(0, 200));
            });
        }
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return response.text().then(text => {
                console.error('Non-JSON response received:', text.substring(0, 500));
                throw new Error('Server returned non-JSON response. Response: ' + text.substring(0, 200));
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert('✓ Test email sent successfully! Please check your inbox.');
        } else {
            alert('✗ Failed to send test email: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Test email error:', error);
        alert('✗ Error: ' + error.message);
    })
    .finally(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    });
}

// Handle password field - if password is provided, make it optional
document.getElementById('smtp_password').addEventListener('input', function() {
    // This will be handled server-side
});
</script>

<?php
renderFooter();
?>

