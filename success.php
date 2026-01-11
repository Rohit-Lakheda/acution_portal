<?php
session_start();

$success = 'Registration completed successfully! Your login credentials have been sent to your email address. Please check your inbox and update your password.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auction Registration Completed</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
        }
        .card {
            border-radius: 0.75rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
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
        .btn-success {
            background-color: #00C853;
        }
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .success-icon {
            font-size: 64px;
            color: #28a745;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header">
                        <h4 class="mb-0">Auction Registration Completed</h4>
                    </div>
                    <div class="card-body text-center">
                        <div class="success-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" fill="currentColor" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
                                <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 2.04 4.907a.75.75 0 0 0-1.06 1.061l5.523 5.523a.75.75 0 0 0 1.137-.089l4.813-5.4a.75.75 0 0 0-.022-1.08z"/>
                            </svg>
                        </div>
                        
                        <div class="alert alert-success">
                            <p class="mb-0"><?php echo htmlspecialchars($success); ?></p>
                        </div>
                        
                        <div class="mt-4">
                            <a href="login.php" class="btn btn-primary">Click here to login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

