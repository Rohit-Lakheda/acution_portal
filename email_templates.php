<?php
/**
 * Centralized Email Template System
 * Modern, aesthetic, user-friendly email templates with NIXI logo
 */

// Base URL for logo and links
define('BASE_URL', 'https://interlinxpartnering.com/auction-portal_new/auction-portal');
define('LOGO_URL', BASE_URL . '/images/nixi_logo1.jpg');

/**
 * Get email template wrapper with NIXI logo
 */
function getEmailTemplateWrapper($content, $title = 'Auction Portal') {
    $logoUrl = LOGO_URL;
    
    return '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: \'Varela Round\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, sans-serif; background-color: #f5f7fa;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f5f7fa;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" style="max-width: 600px; width: 100%; border-collapse: collapse; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden;">
                    <!-- Header with Logo -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1a237e 0%, #283593 100%); padding: 30px; text-align: center;">
                            <img src="' . htmlspecialchars($logoUrl) . '" alt="NIXI Logo" style="height: 60px; width: auto; max-width: 200px; background: white; padding: 5px; border-radius: 12px; margin-bottom: 15px;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 500; letter-spacing: 0.5px;">' . htmlspecialchars($title) . '</h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 35px;">
                            ' . $content . '
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 25px 35px; text-align: center; border-top: 1px solid #e9ecef;">
                            <p style="margin: 0 0 10px 0; color: #6c757d; font-size: 14px; line-height: 1.6;">
                                <strong style="color: #1a237e;">NIXI Auction Portal</strong>
                            </p>
                            <p style="margin: 0 0 10px 0; color: #6c757d; font-size: 13px;">
                                For support, please contact us at <a href="mailto:enquiry@interlinx.in" style="color: #1a237e; text-decoration: none;">enquiry@interlinx.in</a>
                            </p>
                            <p style="margin: 15px 0 0 0; color: #adb5bd; font-size: 12px; line-height: 1.6;">
                                © ' . date('Y') . ' NIXI. All rights reserved.<br>
                                <a href="' . htmlspecialchars(BASE_URL) . '" style="color: #1a237e; text-decoration: none;">Visit Portal</a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
}

/**
 * Registration Success Email Template
 */
function getRegistrationSuccessEmail($userName, $email, $registrationId, $tempPassword, $resetLink) {
    $content = '
        <h2 style="margin: 0 0 20px 0; color: #1a237e; font-size: 26px; font-weight: 500;">Welcome, ' . htmlspecialchars($userName) . '! 🎉</h2>
        
        <p style="margin: 0 0 20px 0; color: #495057; font-size: 16px; line-height: 1.7;">
            Your registration has been completed successfully and payment has been received. We are delighted to have you join our auction portal!
        </p>
        
        <div style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #2e7d32;">
            <p style="margin: 0 0 10px 0; color: #1b5e20; font-size: 14px; font-weight: 600;">Registration ID</p>
            <p style="margin: 0; color: #1b5e20; font-size: 20px; font-weight: 600; font-family: monospace; letter-spacing: 1px;">' . htmlspecialchars($registrationId) . '</p>
        </div>
        
        <div style="background: #f8f9fa; padding: 25px; border-radius: 8px; margin: 25px 0;">
            <h3 style="margin: 0 0 15px 0; color: #1a237e; font-size: 18px; font-weight: 500;">Your Login Credentials</h3>
            <table role="presentation" style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; color: #6c757d; font-size: 14px; width: 120px;">Email:</td>
                    <td style="padding: 8px 0;">
                        <span style="color: #1a237e; font-size: 15px; font-weight: 500; font-family: monospace;">' . htmlspecialchars($email) . '</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #6c757d; font-size: 14px;">Password:</td>
                    <td style="padding: 8px 0;">
                        <span style="color: #1a237e; font-size: 16px; font-weight: 600; font-family: monospace; background: #fff; padding: 8px 12px; border-radius: 4px; border: 1px solid #dee2e6; display: inline-block;">' . htmlspecialchars($tempPassword) . '</span>
                    </td>
                </tr>
            </table>
        </div>
        
        <div style="background: #fff3cd; padding: 18px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #ffc107;">
            <p style="margin: 0; color: #856404; font-size: 14px; line-height: 1.6;">
                <strong>🔒 Security Notice:</strong> For your account security, please update your password immediately using the link below. This temporary password should not be shared with anyone.
            </p>
        </div>
        
        <table role="presentation" style="width: 100%; margin: 30px 0;">
            <tr>
                <td align="center">
                    <a href="' . htmlspecialchars($resetLink) . '" style="display: inline-block; padding: 14px 35px; background: linear-gradient(135deg, #1a237e 0%, #283593 100%); color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 500; font-size: 16px; box-shadow: 0 2px 8px rgba(26, 35, 126, 0.3);">Update Your Password</a>
                </td>
            </tr>
        </table>
        
        <p style="margin: 20px 0 0 0; color: #6c757d; font-size: 14px; line-height: 1.6;">
            <strong style="color: #1a237e;">Note:</strong> This password reset link is valid for <strong>7 days</strong>. After updating your password, you can log in to start participating in auctions.
        </p>
        
        <div style="margin-top: 30px; padding-top: 25px; border-top: 1px solid #e9ecef;">
            <p style="margin: 0 0 10px 0; color: #495057; font-size: 15px; font-weight: 500;">What\'s Next?</p>
            <ul style="margin: 0; padding-left: 20px; color: #6c757d; font-size: 14px; line-height: 1.8;">
                <li>Update your password using the link above</li>
                <li>Log in to your account</li>
                <li>Browse active auctions and place your bids</li>
                <li>Track your bidding activity from your dashboard</li>
            </ul>
        </div>
    ';
    
    return getEmailTemplateWrapper($content, 'Registration Successful');
}

/**
 * Credentials Email Template (Admin sending)
 */
function getCredentialsEmail($userName, $email, $tempPassword, $resetLink) {
    $content = '
        <h2 style="margin: 0 0 20px 0; color: #1a237e; font-size: 26px; font-weight: 500;">Your Account Credentials</h2>
        
        <p style="margin: 0 0 20px 0; color: #495057; font-size: 16px; line-height: 1.7;">
            Hello ' . htmlspecialchars($userName) . ',<br><br>
            Your account credentials have been reset. Please find your new login details below.
        </p>
        
        <div style="background: #f8f9fa; padding: 25px; border-radius: 8px; margin: 25px 0;">
            <h3 style="margin: 0 0 15px 0; color: #1a237e; font-size: 18px; font-weight: 500;">Login Credentials</h3>
            <table role="presentation" style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 8px 0; color: #6c757d; font-size: 14px; width: 120px;">Email:</td>
                    <td style="padding: 8px 0;">
                        <span style="color: #1a237e; font-size: 15px; font-weight: 500; font-family: monospace;">' . htmlspecialchars($email) . '</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: #6c757d; font-size: 14px;">Password:</td>
                    <td style="padding: 8px 0;">
                        <span style="color: #1a237e; font-size: 16px; font-weight: 600; font-family: monospace; background: #fff; padding: 8px 12px; border-radius: 4px; border: 1px solid #dee2e6; display: inline-block;">' . htmlspecialchars($tempPassword) . '</span>
                    </td>
                </tr>
            </table>
        </div>
        
        <div style="background: #fff3cd; padding: 18px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #ffc107;">
            <p style="margin: 0; color: #856404; font-size: 14px; line-height: 1.6;">
                <strong>🔒 Security Notice:</strong> For your account security, please update your password immediately using the link below.
            </p>
        </div>
        
        <table role="presentation" style="width: 100%; margin: 30px 0;">
            <tr>
                <td align="center">
                    <a href="' . htmlspecialchars($resetLink) . '" style="display: inline-block; padding: 14px 35px; background: linear-gradient(135deg, #1a237e 0%, #283593 100%); color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 500; font-size: 16px; box-shadow: 0 2px 8px rgba(26, 35, 126, 0.3);">Update Your Password</a>
                </td>
            </tr>
        </table>
        
        <p style="margin: 20px 0 0 0; color: #6c757d; font-size: 14px; line-height: 1.6;">
            <strong style="color: #1a237e;">Note:</strong> This password reset link is valid for <strong>7 days</strong>.
        </p>
    ';
    
    return getEmailTemplateWrapper($content, 'Account Credentials');
}

/**
 * OTP Email Template
 */
function getOTPEmail($otp, $purpose = 'Email Verification') {
    $content = '
        <h2 style="margin: 0 0 20px 0; color: #1a237e; font-size: 26px; font-weight: 500;">' . htmlspecialchars($purpose) . '</h2>
        
        <p style="margin: 0 0 20px 0; color: #495057; font-size: 16px; line-height: 1.7;">
            Please use the following One-Time Password (OTP) to complete your ' . strtolower($purpose) . ':
        </p>
        
        <table role="presentation" style="width: 100%; margin: 30px 0;">
            <tr>
                <td align="center">
                    <div style="background: linear-gradient(135deg, #1a237e 0%, #283593 100%); padding: 25px 50px; border-radius: 12px; display: inline-block; box-shadow: 0 4px 12px rgba(26, 35, 126, 0.3);">
                        <div style="color: #ffffff; font-size: 42px; font-weight: 700; letter-spacing: 10px; font-family: \'Courier New\', monospace; text-align: center;">
                            ' . htmlspecialchars($otp) . '
                        </div>
                    </div>
                </td>
            </tr>
        </table>
        
        <div style="background: #fff3cd; padding: 18px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #ffc107;">
            <p style="margin: 0; color: #856404; font-size: 14px; line-height: 1.6;">
                <strong>⏰ Important:</strong> This OTP is valid for <strong>10 minutes</strong> only. Please do not share this OTP with anyone.
            </p>
        </div>
        
        <p style="margin: 20px 0 0 0; color: #6c757d; font-size: 14px; line-height: 1.6;">
            If you did not request this OTP, please ignore this email or contact our support team immediately.
        </p>
    ';
    
    return getEmailTemplateWrapper($content, $purpose);
}

/**
 * Password Reset OTP Email Template
 */
function getPasswordResetOTPEmail($otp, $userName = '') {
    $greeting = $userName ? 'Hello ' . htmlspecialchars($userName) . ',' : 'Hello,';
    
    $content = '
        <h2 style="margin: 0 0 20px 0; color: #1a237e; font-size: 26px; font-weight: 500;">Password Reset Request</h2>
        
        <p style="margin: 0 0 20px 0; color: #495057; font-size: 16px; line-height: 1.7;">
            ' . $greeting . '<br><br>
            We received a request to reset your password. Please use the following OTP to verify your identity:
        </p>
        
        <table role="presentation" style="width: 100%; margin: 30px 0;">
            <tr>
                <td align="center">
                    <div style="background: linear-gradient(135deg, #1a237e 0%, #283593 100%); padding: 25px 50px; border-radius: 12px; display: inline-block; box-shadow: 0 4px 12px rgba(26, 35, 126, 0.3);">
                        <div style="color: #ffffff; font-size: 42px; font-weight: 700; letter-spacing: 10px; font-family: \'Courier New\', monospace; text-align: center;">
                            ' . htmlspecialchars($otp) . '
                        </div>
                    </div>
                </td>
            </tr>
        </table>
        
        <div style="background: #fff3cd; padding: 18px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #ffc107;">
            <p style="margin: 0; color: #856404; font-size: 14px; line-height: 1.6;">
                <strong>⏰ Important:</strong> This OTP is valid for <strong>10 minutes</strong> only. Please do not share this OTP with anyone.
            </p>
        </div>
        
        <p style="margin: 20px 0 0 0; color: #6c757d; font-size: 14px; line-height: 1.6;">
            If you did not request a password reset, please ignore this email or contact our support team immediately.
        </p>
    ';
    
    return getEmailTemplateWrapper($content, 'Password Reset');
}

/**
 * Centralized Email Sending Function
 * Uses email settings from database only (no defaults)
 */
function sendEmail($to, $subject, $htmlMessage, $plainTextMessage = '') {
    // Load email_sender.php to get database-driven email settings
    $emailSenderFile = __DIR__ . '/email_sender.php';
    if (file_exists($emailSenderFile)) {
        require_once $emailSenderFile;
    }
    
    // Get email settings from database only
    global $pdo;
    $emailSettings = false;
    if (function_exists('getEmailSettings')) {
        $emailSettings = getEmailSettings($pdo);
    }
    
    // If no settings available, fail
    if ($emailSettings === false) {
        error_log("Email sending failed: Email settings not configured in database");
        return false;
    }
    
    $errorMsg = '';
    $sent = false;
    
    // Try SMTP only - no fallback to mail() function
    // This ensures emails only send if database credentials are correct
    if (function_exists('sendEmailViaSMTP')) {
        $sent = sendEmailViaSMTP($to, $subject, $htmlMessage, $plainTextMessage, $errorMsg);
    } else {
        $errorMsg = 'SMTP email function not available';
        error_log("Email sending failed: SMTP email function not available");
        return false;
    }
    
    // No fallback to mail() - if SMTP fails, email fails
    // This ensures wrong credentials in database will prevent email sending
    if (!$sent && !empty($errorMsg)) {
        error_log("Email sending failed: " . $errorMsg);
    }
    
    return $sent;
}

?>

