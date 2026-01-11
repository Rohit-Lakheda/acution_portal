<?php
/**
 * Password Update Page
 * Allows users to update their password using the reset token sent via email
 */

session_start();
require_once 'database.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$tokenValid = false;
$userEmail = '';

// Verify token if provided
if (!empty($token)) {
    try {
        $stmt = $pdo->prepare("SELECT id, email, password_reset_expires FROM users WHERE password_reset_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Check if token is not expired
            $now = date('Y-m-d H:i:s');
            if ($user['password_reset_expires'] && $user['password_reset_expires'] >= $now) {
                $tokenValid = true;
                $userEmail = $user['email'];
                $_SESSION['password_reset_user_id'] = $user['id'];
                $_SESSION['password_reset_token'] = $token;
            } else {
                $error = 'This password reset link has expired. Please request a new one.';
            }
        } else {
            $error = 'Invalid password reset link.';
        }
    } catch(PDOException $e) {
        $error = 'An error occurred. Please try again.';
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $error .= ' ' . $e->getMessage();
        }
    }
} else {
    $error = 'No password reset token provided.';
}

// Handle password update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValid) {
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($newPassword)) {
        $error = 'Please enter a new password.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Verify token again (security check)
            $userId = $_SESSION['password_reset_user_id'] ?? null;
            $resetToken = $_SESSION['password_reset_token'] ?? null;
            
            if ($userId && $resetToken) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND password_reset_token = ? AND password_reset_expires >= NOW()");
                $stmt->execute([$userId, $resetToken]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Get current user data and password
                    $userStmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                    $userStmt->execute([$userId]);
                    $userData = $userStmt->fetch();
                    
                    if ($userData) {
                        $oldPassword = $userData['password']; // Store old hashed password
                        
                        // Hash new password
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        
                        // Start transaction
                        $pdo->beginTransaction();
                        
                        // Update password and clear reset token in users table
                        $updateStmt = $pdo->prepare("UPDATE users SET password = ?, password_reset_token = NULL, password_reset_expires = NULL WHERE id = ?");
                        $updateStmt->execute([$hashedPassword, $userId]);
                        
                        // Insert into changed_password table
                        $changedStmt = $pdo->prepare("INSERT INTO changed_password 
                            (id, name, email, password, role, created_at, password_reset_token, password_reset_expires, old_password, new_password, changed_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                        $changedStmt->execute([
                            $userData['id'],
                            $userData['name'],
                            $userData['email'],
                            $hashedPassword, // new password in password column
                            $userData['role'],
                            $userData['created_at'],
                            $userData['password_reset_token'],
                            $userData['password_reset_expires'],
                            $oldPassword, // old password
                            $hashedPassword // new password
                        ]);
                        
                        // Commit transaction
                        $pdo->commit();
                        
                        // Clear session
                        unset($_SESSION['password_reset_user_id']);
                        unset($_SESSION['password_reset_token']);
                        
                        $success = 'Password updated successfully! You can now login with your new password.';
                        $tokenValid = false; // Prevent form from showing again
                    } else {
                        $error = 'User data not found. Please try again.';
                    }
                } else {
                    $error = 'Invalid or expired token. Please request a new password reset link.';
                }
            } else {
                $error = 'Session expired. Please use the link from your email again.';
            }
        } catch(PDOException $e) {
            // Rollback transaction if in progress
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Failed to update password. Please try again.';
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                $error .= ' ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Password - Auction Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Varela+Round&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Varela Round', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #2c3e50;
        }
        .logo-header {
            margin-bottom: 35px;
            text-align: center;
            padding: 20px;
        }
        .logo-header img {
            height: 65px;
            width: auto;
            max-width: 200px;
            object-fit: contain;
            margin-bottom: 18px;
            background: white;
            padding: 5px;
            border-radius: 12px;
        }
        .logo-header h2 {
            color: #1a237e;
            font-size: 26px;
            font-weight: 400;
            letter-spacing: 0.5px;
        }
        .card {
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }
        .card-header {
            background: linear-gradient(135deg, #1a237e 0%, #283593 100%);
            color: white;
            border-bottom: none;
            border-radius: 12px 12px 0 0;
            padding: 25px;
        }
        .card-header h4 {
            font-weight: 400;
            font-size: 24px;
            letter-spacing: 0.3px;
            margin: 0;
        }
        .form-label {
            font-weight: 400;
            color: #1a237e;
            font-size: 15px;
            letter-spacing: 0.2px;
        }
        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 16px;
            font-family: 'Varela Round', sans-serif;
            font-size: 15px;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #1a237e;
            box-shadow: 0 0 0 3px rgba(26, 35, 126, 0.1);
        }
        .btn-primary {
            background: #1a237e;
            border: none;
            border-radius: 8px;
            padding: 12px 24px;
            font-family: 'Varela Round', sans-serif;
            font-size: 15px;
            font-weight: 400;
            letter-spacing: 0.3px;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background: #283593;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(26, 35, 126, 0.3);
        }
        @media (max-width: 480px) {
            .logo-header img { height: 50px; }
            .logo-header h2 { font-size: 22px; }
        }
        .container {
            padding: 20px;
        }
        .card {
            border-radius: 0.75rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
            margin: 0 auto;
        }
        .card-header {
            background-color: #000;
            color: #fff;
            border-bottom: 2px solid #FFCD00;
            border-radius: 0.75rem 0.75rem 0 0;
        }
        .btn-primary {
            background-color: #000;
            border-color: #000;
        }
        .btn-primary:hover {
            background-color: #333;
        }
        .invalid-feedback {
            color: #4169E1 !important;
        }
        .form-control.is-invalid {
            border-color: #4169E1 !important;
        }
        .alert-danger {
            color: #4169E1 !important;
        }
    </style>
</head>
<body>
    <div class="logo-header">
        <img src="images/nixi_logo1.jpg" alt="NIXI Logo">
        <h2>🎯 Auction Portal</h2>
    </div>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header">
                        <h4 class="mb-0">Update Your Password</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <?php echo htmlspecialchars($success); ?>
                                <div class="mt-3">
                                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                                </div>
                            </div>
                        <?php elseif ($tokenValid): ?>
                            <?php if ($userEmail): ?>
                                <p class="text-muted mb-4">
                                    You are updating the password for: <strong><?php echo htmlspecialchars($userEmail); ?></strong>
                                </p>
                            <?php endif; ?>
                            
                            <form method="POST" id="passwordUpdateForm">
                                <div class="mb-3">
                                    <label for="password" class="form-label">New Password <span style="color: #4169E1;">*</span></label>
                                    <input type="password" class="form-control" id="password" name="password" required minlength="8" placeholder="Enter new password (minimum 8 characters)">
                                    <small class="form-text text-muted">Password must be at least 8 characters long</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password <span style="color: #4169E1;">*</span></label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8" placeholder="Confirm new password">
                                    <div id="passwordMatch" class="invalid-feedback" style="display: none;">
                                        Passwords do not match.
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <button type="submit" class="btn btn-primary w-100">Update Password</button>
                                </div>
                                
                                <div class="text-center">
                                    <a href="login.php" class="text-muted">Back to Login</a>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="text-center">
                                <p class="text-muted mb-3">Please use the password reset link sent to your email.</p>
                                <a href="login.php" class="btn btn-primary">Go to Login</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password match validation
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const passwordMatchDiv = document.getElementById('passwordMatch');
        const form = document.getElementById('passwordUpdateForm');
        
        function checkPasswordMatch() {
            if (confirmPasswordInput.value && passwordInput.value !== confirmPasswordInput.value) {
                confirmPasswordInput.classList.add('is-invalid');
                passwordMatchDiv.style.display = 'block';
            } else {
                confirmPasswordInput.classList.remove('is-invalid');
                passwordMatchDiv.style.display = 'none';
            }
        }
        
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', checkPasswordMatch);
            passwordInput.addEventListener('input', checkPasswordMatch);
        }
        
        if (form) {
            form.addEventListener('submit', function(e) {
                if (passwordInput.value !== confirmPasswordInput.value) {
                    e.preventDefault();
                    alert('Passwords do not match. Please try again.');
                    return false;
                }
                
                if (passwordInput.value.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long.');
                    return false;
                }
            });
        }
    </script>
</body>
</html>

