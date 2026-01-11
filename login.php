<?php
require_once 'auth.php';
require_once 'database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'All fields are required';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                $passwordValid = false;
                $needsHashing = false;
                
                // Check if stored password looks like a hash (starts with $2y$, $2a$, $2b$, etc.)
                $isHashed = preg_match('/^\$2[ayb]\$.{56}$/', $user['password']);
                
                if ($isHashed) {
                    // Password is hashed, use password_verify
                    if (password_verify($password, $user['password'])) {
                        $passwordValid = true;
                    }
                } else {
                    // Password appears to be plain text, compare directly
                    // This handles cases where password was updated directly in database
                    if ($user['password'] === $password) {
                        $passwordValid = true;
                        $needsHashing = true;
                    }
                }
                
                if ($passwordValid) {
                    // If password needs to be hashed (was plain text), update it now
                    if ($needsHashing) {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $updateStmt->execute([$hashedPassword, $user['id']]);
                    }
                    
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name'] = $user['name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Redirect based on role
                    if ($user['role'] === 'admin') {
                        header('Location: admin_dashboard.php');
                    } else {
                        header('Location: user_auctions.php');
                    }
                    exit();
                } else {
                    $error = 'Invalid email or password';
                }
            } else {
                $error = 'Invalid email or password';
            }
        } catch(PDOException $e) {
            $error = 'Login failed. Please try again.';
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log('Login error: ' . $e->getMessage());
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
    <link href="https://fonts.googleapis.com/css2?family=Varela+Round&display=swap" rel="stylesheet">
    <title>Login - Auction Portal</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Varela Round', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            background: linear-gradient(135deg, #1a237e 0%, #283593 100%); 
            min-height: 100vh; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: center; 
            padding: 20px; 
        }
        .logo-header { 
            margin-bottom: 35px; 
            text-align: center; 
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
        .logo-header h1 { 
            color: white; 
            font-size: 28px; 
            font-weight: 400; 
            letter-spacing: 0.5px;
        }
        .container { 
            background: white; 
            padding: 45px; 
            border-radius: 12px; 
            box-shadow: 0 8px 24px rgba(0,0,0,0.15); 
            width: 100%; 
            max-width: 420px; 
        }
        h2 { 
            color: #1a237e; 
            margin-bottom: 30px; 
            text-align: center; 
            font-size: 26px;
            font-weight: 400;
            letter-spacing: 0.3px;
        }
        @media (max-width: 480px) {
            .logo-header img { height: 50px; }
            .logo-header h1 { font-size: 24px; }
            .container { padding: 35px 25px; }
        }
        .form-group { margin-bottom: 25px; }
        label { 
            display: block; 
            margin-bottom: 8px; 
            color: #1a237e; 
            font-weight: 400; 
            font-size: 15px;
            letter-spacing: 0.2px;
        }
        input { 
            width: 100%; 
            padding: 12px 16px; 
            border: 2px solid #e9ecef; 
            border-radius: 8px; 
            font-size: 15px; 
            font-family: 'Varela Round', sans-serif;
            transition: all 0.3s;
            color: #495057;
        }
        input:focus { 
            outline: none; 
            border-color: #1a237e; 
            box-shadow: 0 0 0 3px rgba(26, 35, 126, 0.1);
        }
        .btn { 
            width: 100%; 
            padding: 14px; 
            background: #1a237e; 
            color: white; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 16px; 
            font-family: 'Varela Round', sans-serif;
            font-weight: 400;
            letter-spacing: 0.3px;
            transition: all 0.3s; 
        }
        .btn:hover { 
            background: #283593; 
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(26, 35, 126, 0.3);
        }
        .error { 
            background: #ffebee; 
            color: #c62828; 
            padding: 14px 18px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            border-left: 4px solid #c62828;
            font-size: 15px;
        }
        .links { 
            text-align: center; 
            margin-top: 25px; 
        }
        .links a { 
            color: #1a237e; 
            text-decoration: none; 
            font-size: 15px;
            transition: color 0.3s;
        }
        .links a:hover { 
            color: #283593; 
            text-decoration: underline; 
        }
        .demo-info { 
            background: #e3f2fd; 
            padding: 16px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            font-size: 14px; 
            color: #1565c0;
            border-left: 4px solid #1565c0;
        }
    </style>
</head>
<body>
    <div class="logo-header">
        <img src="images/nixi_logo1.jpg" alt="NIXI Logo">
        <h1>🎯 Auction Portal</h1>
    </div>
    <div class="container">
        <h2>Login</h2>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <div style="text-align: right; margin-top: 6px; margin-bottom: 10px;">
                <a href="forgot_password.php" style="font-size: 14px;">Forgot password?</a>
            </div>
            <button type="submit" class="btn">Login</button>
        </form>
        
        <div class="links">
            Don't have an account? <a href="register.php">Register here</a><br>
            <br>
            <a href="index.php">Back to Home</a>
        </div>
        <!-- <hr>
        <hr>
        <div class="links" style="margin-top: 0px;">
             <a href="forgot_password.php">Forgot password</a><br>
        </div> -->
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>