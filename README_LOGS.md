# Payment & Registration Logs System

## Setup Instructions

1. **Run the SQL to create the pending registrations table:**
   ```sql
   -- Run fix_registration_payment.sql
   ```

2. **Access the Log Viewer:**
   - URL: `https://interlinxpartnering.com/auction-portal_new/auction-portal/view_logs.php`
   - Default Password: `admin123` (Change this in view_logs.php)

## What Was Fixed

### Problem
- Session data was lost when PayU redirected back after payment
- Registration data stored in `$_SESSION['pending_registration']` was not available
- Payment was successful but registration failed

### Solution
1. **Database Storage**: Registration data is now stored in `pending_registrations` table before payment
2. **Database Retrieval**: Payment callback retrieves data from database instead of session
3. **Comprehensive Logging**: All payment and registration events are logged
4. **Log Viewer**: Real-time log viewer to monitor and debug issues

## Log Types

- `payment_initiated` - Payment process started
- `payment_callback` - PayU callback received
- `payment_success` - Payment successful
- `payment_failed` - Payment failed
- `registration_saved` - Registration completed successfully
- `registration_error` - Registration error occurred

## How to Use Logs

1. Go to the log viewer URL
2. Filter by type, transaction ID, or registration ID
3. View detailed data for each log entry
4. Logs auto-refresh every 30 seconds

## Security Note

**IMPORTANT**: Change the password in `view_logs.php` before going live:
```php
$logViewerPassword = 'your_secure_password_here';
```

