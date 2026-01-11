<?php
/**
 * PayU Payment Gateway Helper
 * Handles PayU payment integration for auction portal
 */

// PayU Configuration
define('PAYU_MERCHANT_ID', '8847461');
define('PAYU_MERCHANT_KEY', 'iaH0zp');
define('PAYU_SALT', 'YSEB0ghJuWV69ZttwxW7fv1F9XXHEosC');
define('PAYU_MODE', 'test'); // 'test' or 'live'
define('PAYU_TEST_URL', 'https://test.payu.in/_payment');
define('PAYU_LIVE_URL', 'https://secure.payu.in/_payment');

/**
 * Get PayU payment URL based on mode
 */
function getPayuPaymentUrl() {
    return PAYU_MODE === 'test' ? PAYU_TEST_URL : PAYU_LIVE_URL;
}

/**
 * Generate hash for PayU payment
 * Formula: sha512(key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5|||||SALT)
 */
function generatePayuHash($params) {
    // Ensure all UDF fields are strings, even if empty
    $udf1 = trim((string) ($params['udf1'] ?? ''));
    $udf2 = trim((string) ($params['udf2'] ?? ''));
    $udf3 = trim((string) ($params['udf3'] ?? ''));
    $udf4 = trim((string) ($params['udf4'] ?? ''));
    $udf5 = trim((string) ($params['udf5'] ?? ''));

    // Ensure all required fields are strings and trimmed
    $txnid = trim((string) $params['txnid']);
    $amount = trim((string) $params['amount']);
    $productinfo = trim((string) $params['productinfo']);
    $firstname = trim((string) $params['firstname']);
    $email = trim((string) $params['email']);

    // Build hash string: key|txnid|amount|productinfo|firstname|email|udf1|udf2|udf3|udf4|udf5|||||SALT
    $hashString = PAYU_MERCHANT_KEY . '|'
        . $txnid . '|'
        . $amount . '|'
        . $productinfo . '|'
        . $firstname . '|'
        . $email . '|'
        . $udf1 . '|'
        . $udf2 . '|'
        . $udf3 . '|'
        . $udf4 . '|'
        . $udf5 . '|'
        . '|||||'
        . PAYU_SALT;

    return strtolower(hash('sha512', $hashString));
}

/**
 * Verify hash from PayU response
 * Formula: sha512(SALT|status|||||udf5|udf4|udf3|udf2|udf1|email|firstname|productinfo|amount|txnid|key)
 */
function verifyPayuHash($response) {
    $status = trim((string) ($response['status'] ?? ''));
    $udf1 = trim((string) ($response['udf1'] ?? ''));
    $udf2 = trim((string) ($response['udf2'] ?? ''));
    $udf3 = trim((string) ($response['udf3'] ?? ''));
    $udf4 = trim((string) ($response['udf4'] ?? ''));
    $udf5 = trim((string) ($response['udf5'] ?? ''));
    $email = trim((string) ($response['email'] ?? ''));
    $firstname = trim((string) ($response['firstname'] ?? ''));
    $productinfo = trim((string) ($response['productinfo'] ?? ''));
    $amount = trim((string) ($response['amount'] ?? ''));
    $txnid = trim((string) ($response['txnid'] ?? ''));
    $receivedHash = strtolower(trim((string) ($response['hash'] ?? '')));

    // Build hash string: SALT|status|||||udf5|udf4|udf3|udf2|udf1|email|firstname|productinfo|amount|txnid|key
    $hashString = PAYU_SALT . '|'
        . $status . '|'
        . '|||||'
        . $udf5 . '|'
        . $udf4 . '|'
        . $udf3 . '|'
        . $udf2 . '|'
        . $udf1 . '|'
        . $email . '|'
        . $firstname . '|'
        . $productinfo . '|'
        . $amount . '|'
        . $txnid . '|'
        . PAYU_MERCHANT_KEY;

    $calculatedHash = strtolower(hash('sha512', $hashString));
    
    return $calculatedHash === $receivedHash;
}

/**
 * Prepare payment data for PayU
 */
function preparePayuPaymentData($data) {
    // Validate mandatory fields
    $requiredFields = ['transaction_id', 'amount', 'product_info', 'firstname', 'email', 'phone', 'success_url', 'failure_url'];
    $missingFields = array_diff($requiredFields, array_keys($data));
    
    if (!empty($missingFields)) {
        throw new Exception('Missing required PayU parameters: ' . implode(', ', $missingFields));
    }

    // Build payment data array
    $paymentData = [
        'key' => PAYU_MERCHANT_KEY,
        'txnid' => $data['transaction_id'],
        'amount' => number_format((float) $data['amount'], 2, '.', ''),
        'productinfo' => $data['product_info'],
        'firstname' => $data['firstname'],
        'email' => $data['email'],
        'phone' => $data['phone'],
        'surl' => $data['success_url'],
        'furl' => $data['failure_url'],
    ];

    // Add optional parameters if provided
    if (isset($data['lastname']) && $data['lastname'] !== '') {
        $paymentData['lastname'] = $data['lastname'];
    }
    if (isset($data['address1']) && $data['address1'] !== '') {
        $paymentData['address1'] = $data['address1'];
    }
    if (isset($data['city']) && $data['city'] !== '') {
        $paymentData['city'] = $data['city'];
    }
    if (isset($data['state']) && $data['state'] !== '') {
        $paymentData['state'] = $data['state'];
    }
    if (isset($data['zipcode']) && $data['zipcode'] !== '') {
        $paymentData['zipcode'] = $data['zipcode'];
    }

    // UDF fields (user-defined fields)
    $paymentData['udf1'] = $data['udf1'] ?? '';
    $paymentData['udf2'] = $data['udf2'] ?? '';
    $paymentData['udf3'] = $data['udf3'] ?? '';
    $paymentData['udf4'] = $data['udf4'] ?? '';
    $paymentData['udf5'] = $data['udf5'] ?? '';

    // Generate hash
    $paymentData['hash'] = generatePayuHash($paymentData);

    return $paymentData;
}
?>

