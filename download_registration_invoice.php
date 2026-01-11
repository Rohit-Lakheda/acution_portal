<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

$userId = getCurrentUserId();

// Get registration details for the current user
$stmt = $pdo->prepare("SELECT r.*, u.name, u.email as user_email 
                      FROM registration r
                      INNER JOIN users u ON r.email = u.email
                      WHERE u.id = ? AND r.payment_status = 'success'");
$stmt->execute([$userId]);
$registration = $stmt->fetch();

if (!$registration) {
    header('Location: user_profile.php?error=registration_not_found');
    exit;
}

// Get user details
$user = [
    'full_name' => $registration['full_name'] ?? $registration['name'],
    'email' => $registration['email'] ?? $registration['user_email'],
    'mobile' => $registration['mobile'] ?? 'N/A',
    'pan_card_number' => $registration['pan_card_number'] ?? ''
];

// Company details (NIXI - matching reference invoice)
$companyName = "National Internet Exchange of India";
$companyAddress = "H-223, Sector-63, Noida, Gautam Buddha Nagar, Uttar Pradesh, 201301 India";
$companyPhone = "+91-11-23738750";
$companyEmail = "ixbilling@nixi.in";
$companyGSTIN = "09AABCN9308A1ZP";
$companyPAN = "AABCN9308A";
$companyCIN = "U72900DL2003NPL120999";
$companyState = "Delhi";
$companyStateCode = "09";
$hsnCode = "998319";
$categoryOfService = "Other Information Technology Services N.E.C.";

// Bank details for payment
$bankName = "AXIS Bank Ltd.";
$bankIFSC = "UTIB0000007";
$bankMICR = "110211002";
$bankAccountName = "National Internet Exchange of India.";
$bankAccountType = "Savings Bank Account";
$bankAccountNumber = "922010006414634";
$bankBranch = "Statesman House, 148, Barakhamba Road, New Delhi-110001 (India)";
$paymentPortalLink = "https://payonline.nixi.in/online-payment";

// Customer details
$customerName = $user['full_name'];
$customerEmail = $user['email'];
$customerMobile = $user['mobile'];
$customerAddress = $customerEmail; // Use email if address not available
$customerGSTIN = ''; // If available
$customerPAN = $user['pan_card_number'];
$customerState = '';
$customerStateCode = '';
$customerAttn = $customerName;

// Invoice details - NIXI format: NIXI-IX-YY-YY-XXXX-YYYY-QX
$currentYear = date('Y');
$currentMonth = (int)date('m');
$financialYearStart = $currentMonth >= 4 ? $currentYear : $currentYear - 1;
$financialYearEnd = $financialYearStart + 1;
$fyShort = substr($financialYearStart, -2) . '-' . substr($financialYearEnd, -2);

// Determine quarter
$quarter = 'Q1';
if ($currentMonth >= 4 && $currentMonth <= 6) {
    $quarter = 'Q1';
} elseif ($currentMonth >= 7 && $currentMonth <= 9) {
    $quarter = 'Q2';
} elseif ($currentMonth >= 10 && $currentMonth <= 12) {
    $quarter = 'Q3';
} else {
    $quarter = 'Q4';
}

// Use registration ID for invoice sequence
$regIdNum = preg_replace('/[^0-9]/', '', $registration['registration_id']);
$invoiceSeq = str_pad($regIdNum ?: $userId, 4, '0', STR_PAD_LEFT);
$invoiceNumber = "NIXI-IX-{$fyShort}/{$invoiceSeq}-{$currentYear}-{$quarter}";

$issueDate = $registration['payment_date'] ? date('d/m/Y', strtotime($registration['payment_date'])) : date('d/m/Y');
$issueDateDisplay = $registration['payment_date'] ? date('d-M-Y', strtotime($registration['payment_date'])) : date('d-M-Y');
$dueDate = $registration['payment_date'] ? date('d/m/Y', strtotime($registration['payment_date'] . ' +90 days')) : date('d/m/Y', strtotime('+90 days'));
$dueDateDisplay = $registration['payment_date'] ? date('d-M-Y', strtotime($registration['payment_date'] . ' +90 days')) : date('d-M-Y', strtotime('+90 days'));

// Build invoice item for registration
$registrationAmount = floatval($registration['payment_amount'] ?? 0);
$invoiceItems = [[
    'description' => 'Registration Fee - ' . ($registration['registration_type'] ?? 'Standard'),
    'price' => $registrationAmount,
    'quantity' => 1,
    'total' => $registrationAmount
]];

// Place of supply
$placeOfSupply = $customerState ?: $companyState;

// Calculate totals
$subtotal = $registrationAmount;
$taxRate = 0.18; // 18% GST
$taxableAmount = $subtotal;

// Initialize all tax variables
$cgstRate = 0;
$sgstRate = 0;
$igstRate = 0;
$cgstAmount = 0;
$sgstAmount = 0;
$igstAmount = 0;

$isInterState = ($customerState && $customerState != $companyState) || ($customerStateCode && $customerStateCode != $companyStateCode);

if ($isInterState) {
    // Inter-state: Use IGST
    $igstRate = $taxRate; // 18% IGST
    $igstAmount = $taxableAmount * $igstRate;
    $taxAmount = $igstAmount;
} else {
    // Intra-state: Use CGST + SGST
    $cgstRate = $taxRate / 2; // 9% CGST
    $sgstRate = $taxRate / 2; // 9% SGST
    $cgstAmount = $taxableAmount * $cgstRate;
    $sgstAmount = $taxableAmount * $sgstRate;
    $taxAmount = $cgstAmount + $sgstAmount;
}

$totalDue = $subtotal; // Total amount (GST excluded as per auction invoice format)

// Generate IRN and Acknowledge Number
$irnNumber = strtoupper(substr(md5($invoiceNumber . $userId . time()), 0, 32));
$acknowledgeNumber = '110000000' . str_pad($userId, 3, '0', STR_PAD_LEFT);

// Number of empty rows for spacing in invoice table
$maxEmptyRows = 6;
$itemsCount = count($invoiceItems);
$emptyRowsCount = max(0, min($maxEmptyRows, $maxEmptyRows - $itemsCount + 1));

// Check if user wants PDF format
$forcePDF = isset($_GET['format']) && $_GET['format'] === 'pdf';

// Try to load vendor autoload (Composer)
$vendorAutoload = __DIR__ . '/vendor/autoload.php';
$hasVendor = file_exists($vendorAutoload);

if ($hasVendor) {
    require_once $vendorAutoload;
}

// Check for available PDF libraries
$hasDomPDF = class_exists('Dompdf\Dompdf');
$hasTCPDF = class_exists('TCPDF');

// Use PDF library if format is requested
$usePDF = $forcePDF && ($hasDomPDF || $hasTCPDF);

if ($usePDF && $hasDomPDF) {
    // Use DomPDF for better HTML to PDF conversion
    $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('isPhpEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isFontSubsettingEnabled', true);
    $options->set('chroot', __DIR__);
    
    $dompdf = new \Dompdf\Dompdf($options);
    
    // Generate HTML content for PDF
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; margin: 0; padding: 10px; border: 1px solid #000; }
            .top-header { display: table; width: 100%; margin-bottom: 10px; }
            .logo-section { display: table-cell; vertical-align: top; }
            .logo-nixi { font-size: 22px; font-weight: bold; color: #0066cc; margin-bottom: 2px; }
            .logo-company { font-size: 10px; color: #666666; }
            .original-recipient { display: table-cell; vertical-align: top; text-align: right; font-size: 10px; font-weight: bold; }
            .tax-invoice-title { font-size: 20px; font-weight: bold; margin: 10px 0; }
            .buyer-seller { display: table; width: 100%; margin-bottom: 10px; border: 1px solid #000; }
            .buyer, .seller { display: table-cell; width: 50%; vertical-align: top; padding: 5px 10px; }
            .buyer { border-right: 1px solid #000; }
            .section-label { font-weight: bold; margin-bottom: 3px; }
            .section-content { font-size: 9px; line-height: 1.4; }
            .invoice-info-boxes { display: table; width: 100%; margin: 10px 0; }
            .invoice-box { display: table-cell; width: 50%; padding: 5px; border: 1px solid #000; font-size: 9px; }
            table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 9px; }
            table th { background: #d3d3d3; padding: 5px 4px; border: 1px solid #000; text-align: center; font-weight: bold; }
            table td { padding: 4px; border: 1px solid #000; }
            .text-right { text-align: right; }
            .text-center { text-align: center; }
            .total-section { margin-top: 10px; font-size: 10px; }
            .total-row { margin: 3px 0; }
            .total-row strong { float: left; }
            .total-row .text-right { float: right; }
            .amount-words { margin-top: 8px; font-size: 11px; font-weight: bold; clear: both; }
            .blue-banner { background: #0066cc; color: #ffffff; padding: 8px; text-align: center; font-size: 10px; font-weight: bold; margin: 15px 0; }
            .payment-instructions { margin-top: 15px; font-size: 9px; display: table; width: 100%; border: 1px solid #000; }
            .payment-left, .payment-right { display: table-cell; width: 50%; vertical-align: top; padding: 0 10px; }
            .terms { margin-top: 10px; font-size: 8px; }
            .footer { margin-top: 15px; font-size: 8px; }
            .footer-row { margin: 2px 0; }
        </style>
    </head>
    <body>
        <div class="top-header">
            <div class="logo-section">
                <img src="images/nixi_logo1.jpg" alt="NIXI Logo" style="width: 100px; height: 50px;">
                <div class="logo-company">national internet exchange of india</div>
            </div>
            <div class="original-recipient">ORIGINAL FOR RECEIPIENT</div>
        </div>
        
        <div class="tax-invoice-title">Tax Invoice</div>
        
        <div class="buyer-seller">
            <div class="buyer">
                <div class="section-label">Buyer:</div>
                <div class="section-content">
                    <strong><?php echo htmlspecialchars($customerName); ?></strong><br>
                    <strong>Registration ID:</strong> <?php echo htmlspecialchars($registration['registration_id']); ?><br>
                    <strong>Address:</strong> <?php echo htmlspecialchars($customerAddress); ?><br>
                    <strong>Phone:</strong> <?php echo htmlspecialchars($customerMobile); ?><br>
                    <strong>Email:</strong> <?php echo htmlspecialchars($customerEmail); ?><br>
                    <?php if ($customerPAN): ?>
                    <strong>PAN:</strong> <?php echo htmlspecialchars($customerPAN); ?><br>
                    <?php endif; ?>
                    <strong>Attn:</strong> <?php echo htmlspecialchars($customerAttn); ?><br>
                    <strong>Place of Supply:</strong> <?php echo htmlspecialchars($placeOfSupply); ?>
                </div>
            </div>
            <div class="seller">
                <div class="section-label">Seller:</div>
                <div class="section-content">
                    <strong>Seller:</strong> <?php echo htmlspecialchars($companyName); ?><br>
                    <strong>PAN:</strong> <?php echo htmlspecialchars($companyPAN); ?><br>
                    <strong>CIN:</strong> <?php echo htmlspecialchars($companyCIN); ?><br>
                    <strong>GSTIN:</strong> <?php echo htmlspecialchars($companyGSTIN); ?><br>
                    <strong>HSN CODE:</strong> <?php echo htmlspecialchars($hsnCode); ?><br>
                    <strong>Category of Service:</strong> <?php echo htmlspecialchars($categoryOfService); ?>
                </div>
            </div>
        </div>
        
        <div class="invoice-info-boxes">
            <div class="invoice-box">
                <strong>Invoice No:</strong> <?php echo htmlspecialchars($invoiceNumber); ?>
            </div>
            <div class="invoice-box">
                <strong>Invoice Date (dd/mm/yyyy):</strong> <?php echo htmlspecialchars($issueDate); ?> 
            </div>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">S.N.o.</th>
                    <th style="width: 40%;">Particulars</th>
                    <th style="width: 8%;">Quantity</th>
                    <th style="width: 20%;" class="text-right">Amount(₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php $sno = 1; foreach ($invoiceItems as $item): ?>
                <tr>
                    <td class="text-center"><?php echo $sno++; ?></td>
                    <td><?php echo htmlspecialchars($item['description']); ?></td>
                    <td class="text-center"><?php echo $item['quantity']; ?></td>
                    <td class="text-right"><?php echo number_format($item['total'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="total-section">
            <div class="total-row"><strong>Total Paid Amount:</strong> <span class="text-right"><?php echo number_format($totalDue, 2); ?></span></div>
            <div class="amount-words">
                <strong>Rupees: <?php
                function numberToWordsNIXI($number) {
                    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
                    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
                    if ($number < 20) return $ones[$number];
                    if ($number < 100) return $tens[intval($number/10)] . ($number%10 ? ' ' . $ones[$number%10] : '');
                    if ($number < 1000) return $ones[intval($number/100)] . ' Hundred' . ($number%100 ? ' ' . numberToWordsNIXI($number%100) : '');
                    if ($number < 100000) return numberToWordsNIXI(intval($number/1000)) . ' Thousand' . ($number%1000 ? ' ' . numberToWordsNIXI($number%1000) : '');
                    if ($number < 10000000) return numberToWordsNIXI(intval($number/100000)) . ' Lakh' . ($number%100000 ? ' ' . numberToWordsNIXI($number%100000) : '');
                    return numberToWordsNIXI(intval($number/10000000)) . ' Crore' . ($number%10000000 ? ' ' . numberToWordsNIXI($number%10000000) : '');
                }
                $amount = round($totalDue);
                $rupees = intval($amount);
                echo ucfirst(numberToWordsNIXI($rupees)) . ' Rupees Only';
                ?></strong>
            </div>
        </div>
        
        <div class="blue-banner">
            BUILD YOUR DIGITAL IDENTITY WITH .IN TRUSTED BY 3 MILLION USERS Get a global reach with .IN
        </div>
        
        <div class="payment-instructions">
            <div class="payment-left">
                <strong>Payment Details:</strong><br>
                <?php if ($registration['payment_transaction_id']): ?>
                <strong>Transaction ID:</strong> <?php echo htmlspecialchars($registration['payment_transaction_id']); ?><br>
                <?php endif; ?>
                <strong>Payment Date:</strong> <?php echo htmlspecialchars($issueDateDisplay); ?><br>
                <strong>Payment Status:</strong> Paid<br><br>
                <strong>Bank Name:</strong> <?php echo htmlspecialchars($bankName); ?><br>
                <strong>IFSC Code:</strong> <?php echo htmlspecialchars($bankIFSC); ?><br>
                <strong>Account Number:</strong> <?php echo htmlspecialchars($bankAccountNumber); ?>
            </div>
            <div class="payment-right">
                <strong>Registration Details:</strong><br>
                <strong>Registration ID:</strong> <?php echo htmlspecialchars($registration['registration_id']); ?><br>
                <strong>Registration Type:</strong> <?php echo htmlspecialchars(ucfirst($registration['registration_type'] ?? 'Standard')); ?><br>
                <strong>Date of Birth:</strong> <?php echo $registration['date_of_birth'] ? date('d-M-Y', strtotime($registration['date_of_birth'])) : 'N/A'; ?><br>
                <strong>PAN Card:</strong> <?php echo htmlspecialchars($registration['pan_card_number'] ?? 'N/A'); ?>
            </div>
        </div>
        
        <div class="terms">
            <strong>Terms & Conditions:-</strong><br>
            1. Please Note that the date of receipt of payment in NIXI Bank account shall be treated as the date of payment.<br>
            2. Payment should be made as per NIXI Exchange billing procedure.<br>
            3. Any dispute subject to jurisdiction under the 'Delhi Courts only'.
        </div>
        
        <div class="footer">
            <div class="footer-row"><strong>Digitally Signed by NIC-IRP on:</strong> <?php echo date('Y-m-d\TH:i'); ?></div>
            <div class="footer-row"><strong>IRN Number:</strong> <?php echo htmlspecialchars($irnNumber); ?></div>
            <div class="footer-row"><strong>Acknowledge Number:</strong> <?php echo htmlspecialchars($acknowledgeNumber); ?></div>
            <div class="footer-row"><strong>Seller's Address:</strong> <?php echo htmlspecialchars($companyAddress); ?></div>
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();
    
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Output PDF
    $dompdf->stream('Registration_Invoice_' . $invoiceNumber . '.pdf', ['Attachment' => true]);
    exit;
    
} else {
    // Fallback: HTML-based invoice (can be printed as PDF)
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Registration Invoice - <?php echo htmlspecialchars($invoiceNumber); ?></title>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
        <style>
            @media print {
                body { margin: 0; padding: 10px; background: white; border: 1px solid #000; }
                .no-print { display: none !important; }
                .invoice-container { box-shadow: none; padding: 0; margin: 0; max-width: 100%; width: 100%; border: none; }
            }
            body {
                font-family: Arial, sans-serif;
                font-size: 10px;
                margin: 0;
                padding: 10px;
                background: #f5f5f5;
            }
            .invoice-container {
                background: white;
                padding: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                width: 100%;
                max-width: 900px;
                margin: 0 auto;
                border: 1px solid #000;
            }
            .top-header {
                display: table;
                width: 100%;
                margin-bottom: 10px;
            }
            .logo-section {
                display: table-cell;
                vertical-align: top;
            }
            .logo-nixi {
                font-size: 22px;
                font-weight: bold;
                color: #0066cc;
                margin-bottom: 2px;
            }
            .logo-company {
                font-size: 10px;
                color: #666666;
            }
            .original-recipient {
                display: table-cell;
                vertical-align: top;
                text-align: right;
                font-size: 10px;
                font-weight: bold;
            }
            .tax-invoice-title {
                font-size: 20px;
                font-weight: bold;
                margin: 10px 0;
            }
            .buyer-seller {
                display: table;
                width: 100%;
                margin-bottom: 10px;
                border: 1px solid #000;
            }
            .buyer, .seller {
                display: table-cell;
                width: 50%;
                vertical-align: top;
                padding: 5px 10px;
            }
            .buyer {
                border-right: 1px solid #000;
            }
            .section-label {
                font-weight: bold;
                margin-bottom: 3px;
            }
            .section-content {
                font-size: 9px;
                line-height: 1.4;
            }
            .invoice-info-boxes {
                display: table;
                width: 100%;
                margin: 10px 0;
            }
            .invoice-box {
                display: table-cell;
                width: 50%;
                padding: 5px;
                border: 1px solid #000;
                font-size: 9px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 10px 0;
                font-size: 9px;
            }
            table th {
                background: #d3d3d3;
                padding: 5px 4px;
                border: 1px solid #000;
                text-align: center;
                font-weight: bold;
            }
            table td {
                padding: 4px;
                border: 1px solid #000;
            }
            .text-right {
                text-align: right;
            }
            .text-center {
                text-align: center;
            }
            .total-section {
                margin-top: 10px;
                font-size: 10px;
            }
            .total-row {
                margin: 3px 0;
            }
            .total-row strong {
                float: left;
            }
            .total-row .text-right {
                float: right;
            }
            .amount-words {
                margin-top: 8px;
                font-size: 11px;
                font-weight: bold;
                clear: both;
            }
            .blue-banner {
                background: #0066cc;
                color: #ffffff;
                padding: 8px;
                text-align: center;
                font-size: 10px;
                font-weight: bold;
                margin: 15px 0;
            }
            .payment-instructions {
                margin-top: 15px;
                font-size: 9px;
                display: table;
                width: 100%;
                border: 1px solid #000;
            }
            .payment-left, .payment-right {
                display: table-cell;
                width: 50%;
                vertical-align: top;
                padding: 0 10px;
            }
            .terms {
                margin-top: 10px;
                font-size: 8px;
            }
            .footer {
                margin-top: 15px;
                font-size: 8px;
            }
            .footer-row {
                margin: 2px 0;
            }
            .print-btn {
                text-align: center;
                margin: 20px 0;
            }
            .btn {
                padding: 10px 20px;
                background: #667eea;
                color: white;
                text-decoration: none;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14px;
            }
            .btn:hover {
                background: #764ba2;
            }
        </style>
    </head>
    <body>
        <div class="invoice-container">
            <div class="top-header">
                <div class="logo-section">
                    <img src="images/nixi_logo1.jpg" alt="NIXI Logo" style="width: 100px; height: 50px;">
                    <div class="logo-company">national internet exchange of india</div>
                </div>
                <div class="original-recipient">ORIGINAL FOR RECEIPIENT</div>
            </div>
            
            <div class="tax-invoice-title">Tax Invoice</div>
            
            <div class="buyer-seller">
                <div class="buyer">
                    <div class="section-label">Buyer:</div>
                    <div class="section-content">
                        <strong><?php echo htmlspecialchars($customerName); ?></strong><br>
                        <strong>Registration ID:</strong> <?php echo htmlspecialchars($registration['registration_id']); ?><br>
                        <strong>Address:</strong> <?php echo htmlspecialchars($customerAddress); ?><br>
                        <strong>Phone:</strong> <?php echo htmlspecialchars($customerMobile); ?><br>
                        <strong>Email:</strong> <?php echo htmlspecialchars($customerEmail); ?><br>
                        <?php if ($customerPAN): ?>
                        <strong>PAN:</strong> <?php echo htmlspecialchars($customerPAN); ?><br>
                        <?php endif; ?>
                        <strong>Attn:</strong> <?php echo htmlspecialchars($customerAttn); ?><br>
                        <strong>Place of Supply:</strong> <?php echo htmlspecialchars($placeOfSupply); ?>
                    </div>
                </div>
                <div class="seller">
                    <div class="section-label">Seller:</div>
                    <div class="section-content">
                        <strong>Seller:</strong> <?php echo htmlspecialchars($companyName); ?><br>
                        <strong>PAN:</strong> <?php echo htmlspecialchars($companyPAN); ?><br>
                        <strong>CIN:</strong> <?php echo htmlspecialchars($companyCIN); ?><br>
                        <strong>GSTIN:</strong> <?php echo htmlspecialchars($companyGSTIN); ?><br>
                        <strong>HSN CODE:</strong> <?php echo htmlspecialchars($hsnCode); ?><br>
                        <strong>Category of Service:</strong> <?php echo htmlspecialchars($categoryOfService); ?>
                    </div>
                </div>
            </div>
            
            <div class="invoice-info-boxes">
                <div class="invoice-box">
                    <strong>Invoice No:</strong> <?php echo htmlspecialchars($invoiceNumber); ?>
                </div>
                <div class="invoice-box">
                    <strong>Invoice Date (dd/mm/yyyy):</strong> <?php echo htmlspecialchars($issueDate); ?> 
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th style="width: 5%;">S.N.o.</th>
                        <th style="width: 40%;">Particulars</th>
                        <th style="width: 8%;">Quantity</th>
                        <th style="width: 20%;" class="text-right">Amount(₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $sno = 1; foreach ($invoiceItems as $item): ?>
                    <tr>
                        <td class="text-center"><?php echo $sno++; ?></td>
                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                        <td class="text-right"><?php echo number_format($item['total'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="total-section">
                <div class="total-row"><strong>Total Paid Amount:</strong> <span class="text-right"><?php echo number_format($totalDue, 2); ?></span></div>
                <div class="amount-words">
                    <strong>Rupees: <?php
                    function numberToWordsHTML($number) {
                        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
                        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
                        if ($number < 20) return $ones[$number];
                        if ($number < 100) return $tens[intval($number/10)] . ($number%10 ? ' ' . $ones[$number%10] : '');
                        if ($number < 1000) return $ones[intval($number/100)] . ' Hundred' . ($number%100 ? ' ' . numberToWordsHTML($number%100) : '');
                        if ($number < 100000) return numberToWordsHTML(intval($number/1000)) . ' Thousand' . ($number%1000 ? ' ' . numberToWordsHTML($number%1000) : '');
                        if ($number < 10000000) return numberToWordsHTML(intval($number/100000)) . ' Lakh' . ($number%100000 ? ' ' . numberToWordsHTML($number%100000) : '');
                        return numberToWordsHTML(intval($number/10000000)) . ' Crore' . ($number%10000000 ? ' ' . numberToWordsHTML($number%10000000) : '');
                    }
                    $amount = round($totalDue);
                    $rupees = intval($amount);
                    echo ucfirst(numberToWordsHTML($rupees)) . ' Rupees Only';
                    ?></strong>
                </div>
            </div>
            
            <div class="blue-banner">
                BUILD YOUR DIGITAL IDENTITY WITH .IN TRUSTED BY 3 MILLION USERS Get a global reach with .IN
            </div>
            
            <div class="payment-instructions">
                <div class="payment-left">
                    <strong>Payment Details:</strong><br>
                    <?php if ($registration['payment_transaction_id']): ?>
                    <strong>Transaction ID:</strong> <?php echo htmlspecialchars($registration['payment_transaction_id']); ?><br>
                    <?php endif; ?>
                    <strong>Payment Date:</strong> <?php echo htmlspecialchars($issueDateDisplay); ?><br>
                    <strong>Payment Status:</strong> Paid<br><br>
                    <strong>Bank Name:</strong> <?php echo htmlspecialchars($bankName); ?><br>
                    <strong>IFSC Code:</strong> <?php echo htmlspecialchars($bankIFSC); ?><br>
                    <strong>Account Number:</strong> <?php echo htmlspecialchars($bankAccountNumber); ?>
                </div>
                <div class="payment-right">
                    <strong>Registration Details:</strong><br>
                    <strong>Registration ID:</strong> <?php echo htmlspecialchars($registration['registration_id']); ?><br>
                    <strong>Registration Type:</strong> <?php echo htmlspecialchars(ucfirst($registration['registration_type'] ?? 'Standard')); ?><br>
                    <strong>Date of Birth:</strong> <?php echo $registration['date_of_birth'] ? date('d-M-Y', strtotime($registration['date_of_birth'])) : 'N/A'; ?><br>
                    <strong>PAN Card:</strong> <?php echo htmlspecialchars($registration['pan_card_number'] ?? 'N/A'); ?>
                </div>
            </div>
            
            <div class="terms">
                <strong>Terms & Conditions:-</strong><br>
                1. Please Note that the date of receipt of payment in NIXI Bank account shall be treated as the date of payment.<br>
                2. Payment should be made as per NIXI Exchange billing procedure.<br>
                3. Any dispute subject to jurisdiction under the 'Delhi Courts only'.
            </div>
            
            <div class="footer">
                <div class="footer-row"><strong>Digitally Signed by NIC-IRP on:</strong> <?php echo date('Y-m-d\TH:i'); ?></div>
                <div class="footer-row"><strong>IRN Number:</strong> <?php echo htmlspecialchars($irnNumber); ?></div>
                <div class="footer-row"><strong>Acknowledge Number:</strong> <?php echo htmlspecialchars($acknowledgeNumber); ?></div>
                <div class="footer-row"><strong>Seller's Address:</strong> <?php echo htmlspecialchars($companyAddress); ?></div>
            </div>
        </div>
        
        <div class="print-btn no-print">
            <?php
            $pdfUrl = '?format=pdf';
            $hasTCPDF = class_exists('TCPDF');
            ?>
            <?php if ($hasDomPDF || $hasTCPDF): ?>
                <a href="<?php echo htmlspecialchars($pdfUrl); ?>" class="btn">Download as PDF</a>
            <?php else: ?>
                <button class="btn" onclick="downloadPDF(event)">Download as PDF</button>
            <?php endif; ?>
            <a href="user_profile.php" class="btn" style="margin-left: 10px;">Back to Profile</a>
        </div>
        <script>
            function downloadPDF(event) {
                if (event) {
                    event.preventDefault();
                }
                
                const element = document.querySelector('.invoice-container');
                if (!element) {
                    alert('Invoice container not found');
                    return;
                }
                
                const opt = {
                    margin: [0.5, 0.5, 0.5, 0.5],
                    filename: 'Registration_Invoice_<?php echo htmlspecialchars($invoiceNumber); ?>.pdf',
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { 
                        scale: 2,
                        useCORS: true,
                        logging: false,
                        letterRendering: true,
                        backgroundColor: '#ffffff'
                    },
                    jsPDF: { 
                        unit: 'in', 
                        format: 'a4', 
                        orientation: 'portrait' 
                    },
                    pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
                };
                
                const btn = event ? event.target : document.querySelector('button[onclick*="downloadPDF"]');
                let originalText = '';
                if (btn) {
                    originalText = btn.textContent;
                    btn.textContent = 'Generating PDF...';
                    btn.disabled = true;
                }
                
                html2pdf().set(opt).from(element).save().then(() => {
                    if (btn) {
                        btn.textContent = originalText;
                        btn.disabled = false;
                    }
                }).catch((error) => {
                    console.error('PDF generation error:', error);
                    alert('Error generating PDF. Please try printing instead.');
                    if (btn) {
                        btn.textContent = originalText;
                        btn.disabled = false;
                    }
                });
            }
        </script>
    </body>
    </html>
    <?php
}
?>

