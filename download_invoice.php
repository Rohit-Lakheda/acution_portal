<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'functions.php';

requireLogin();

// Get auction ID(s) from request - support single or multiple (comma-separated)
$auctionIdsInput = $_GET['auction_id'] ?? '';
$userId = getCurrentUserId();

if (empty($auctionIdsInput)) {
    header('Location: user_won_auctions.php?error=invalid_auction');
    exit;
}

// Parse auction IDs - support single ID or comma-separated list
$auctionIds = [];
if (strpos($auctionIdsInput, ',') !== false) {
    // Multiple IDs
    $ids = explode(',', $auctionIdsInput);
    foreach ($ids as $id) {
        $id = trim($id);
        if (is_numeric($id) && $id > 0) {
            $auctionIds[] = (int)$id;
        }
    }
} else {
    // Single ID
    $auctionId = (int)$auctionIdsInput;
    if ($auctionId > 0) {
        $auctionIds[] = $auctionId;
    }
}

if (empty($auctionIds)) {
    header('Location: user_won_auctions.php?error=invalid_auction');
    exit;
}

// Get all auction details - verify user is the winner for all
$placeholders = str_repeat('?,', count($auctionIds) - 1) . '?';
$stmt = $pdo->prepare("SELECT * FROM auctions WHERE id IN ($placeholders) AND winner_user_id = ? AND status = 'closed'");
$params = array_merge($auctionIds, [$userId]);
$stmt->execute($params);
$auctions = $stmt->fetchAll();

if (empty($auctions)) {
    header('Location: user_won_auctions.php?error=auction_not_found');
    exit;
}

// Use first auction ID for invoice number (or combine if multiple)
$primaryAuctionId = $auctionIds[0];

// Get user details - try registration table first, then users table
$user = null;

// First try to get from registration table (has more details like mobile, address)
$stmt = $pdo->prepare("SELECT r.* FROM registration r 
                       INNER JOIN users u ON r.email = u.email 
                       WHERE u.id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// If not found in registration table, get from users table
if (!$user) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch();
    if ($userData) {
        $user = [
            'full_name' => $userData['name'],
            'email' => $userData['email'],
            'mobile' => 'N/A'
        ];
    }
}

if (!$user) {
    header('Location: user_won_auctions.php?error=user_not_found');
    exit;
}

// Get payment transaction details if available (for all auctions)
$placeholders = str_repeat('?,', count($auctionIds) - 1) . '?';
$stmt = $pdo->prepare("SELECT * FROM payment_transactions 
                       WHERE auction_id IN ($placeholders) AND user_id = ? AND status = 'success' 
                       ORDER BY created_at DESC");
$params = array_merge($auctionIds, [$userId]);
$stmt->execute($params);
$paymentTransactions = $stmt->fetchAll();
$paymentTransaction = !empty($paymentTransactions) ? $paymentTransactions[0] : null;

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
$customerName = $user['full_name'] ?? $user['name'] ?? 'Customer';
$customerEmail = $user['email'] ?? '';
$customerMobile = $user['mobile'] ?? 'N/A';
$customerAddress = $user['address'] ?? $customerEmail; // Use email if address not available
$customerGSTIN = $user['gstin'] ?? ''; // If available
$customerPAN = $user['pan_card_number'] ?? $user['pan'] ?? ''; // If available
$customerState = $user['state'] ?? '';
$customerStateCode = $user['state_code'] ?? '';
$customerAttn = $user['full_name'] ?? $user['name'] ?? ''; // Attention field
// $customerId = $user['customer_id'] ?? 'REG' . strtoupper(substr(md5($userId . $customerEmail), 0, 9)); // Generate customer ID if not available

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

$invoiceSeq = str_pad($primaryAuctionId, 4, '0', STR_PAD_LEFT);
$invoiceNumber = "NIXI-IX-{$fyShort}/{$invoiceSeq}-{$currentYear}-{$quarter}"; // Format with slash

$issueDate = date('d/m/Y'); // Format: dd/mm/yyyy
$issueDateDisplay = date('d-M-Y'); // For display
// Calculate due date from the latest auction end date
$latestEndDate = null;
foreach ($auctions as $auction) {
    $endDate = strtotime($auction['end_datetime']);
    if ($latestEndDate === null || $endDate > $latestEndDate) {
        $latestEndDate = $endDate;
    }
}
$dueDate = $latestEndDate ? date('d/m/Y', strtotime(date('Y-m-d', $latestEndDate) . ' +90 days')) : date('d/m/Y', strtotime('+90 days')); // Format: dd/mm/yyyy, 90 days due
$dueDateDisplay = $latestEndDate ? date('d-M-Y', strtotime(date('Y-m-d', $latestEndDate) . ' +90 days')) : date('d-M-Y', strtotime('+90 days'));

// Build items array from auctions
$invoiceItems = [];
foreach ($auctions as $auction) {
    $invoiceItems[] = [
        'description' => $auction['title'],
        'price' => (float)$auction['final_price'],
        'quantity' => 1,
        'peering_capacity' => '-', // Can be customized based on auction data
        'peering_charges' => number_format((float)$auction['final_price'], 2),
        'total' => (float)$auction['final_price']
    ];
}

// Place of supply
$placeOfSupply = $customerState ?: $companyState;

// Calculate totals
$subtotal = 0;
foreach ($invoiceItems as $item) {
    $subtotal += $item['total'];
}

// GST Calculation - Use IGST for inter-state, CGST/SGST for intra-state
$taxRate = 0.18; // 18% GST
$taxableAmount = $subtotal;
$isInterState = ($customerState && $customerState != $companyState) || ($customerStateCode && $customerStateCode != $companyStateCode);

// Initialize all tax variables
$cgstRate = 0;
$sgstRate = 0;
$igstRate = 0;
$cgstAmount = 0;
$sgstAmount = 0;
$igstAmount = 0;

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
// $totalDue = $subtotal + $taxAmount;   
$totalDue = $subtotal;

// Generate IRN and Acknowledge Number (for e-invoice compliance)
$irnNumber = strtoupper(substr(md5($invoiceNumber . $userId . time()), 0, 32));
$acknowledgeNumber = '110000000' . str_pad($primaryAuctionId, 3, '0', STR_PAD_LEFT);

// Number of empty rows for spacing in invoice table (dynamic based on item count)
// Show fewer empty rows if there are many items, max 6 rows
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
                <!-- <div class="logo-nixi">nixi</div> -->
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
                    <strong>Address:</strong> <?php echo htmlspecialchars($customerAddress); ?><br>
                    <?php if ($customerState): ?>
                    <?php echo htmlspecialchars($customerState); ?> <?php echo htmlspecialchars($customerStateCode ?: ''); ?> <?php echo htmlspecialchars($user['pincode'] ?? ''); ?><br>
                    <?php endif; ?>
                    <strong>Phone:</strong> <?php echo htmlspecialchars($customerMobile); ?><br>
                    <strong>Email:</strong> <?php echo htmlspecialchars($customerEmail); ?><br>
                    <?php if ($customerGSTIN): ?>
                    <strong>GSTIN/UIN:</strong> <?php echo htmlspecialchars($customerGSTIN); ?><br>
                    <?php endif; ?>
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
                <strong>Invoice No:</strong> <?php echo htmlspecialchars($invoiceNumber); ?> <strong>Customer Id:</strong> <?php// echo htmlspecialchars($customerId); ?>
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
                    <!-- <th style="width: 12%;">Peering Capacity</th> -->
                    <!-- <th style="width: 15%;" class="text-right">Peering Charges</th> -->
                    <th style="width: 20%;" class="text-right">Amount(₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php $sno = 1; foreach ($invoiceItems as $item): ?>
                <tr>
                    <td class="text-center"><?php echo $sno++; ?></td>
                    <td><?php echo htmlspecialchars($item['description']); ?></td>
                    <td class="text-center"><?php echo $item['quantity']; ?></td>
                    <!-- <td class="text-center"><?php //echo htmlspecialchars($item['peering_capacity'] ?? '-'); ?></td> -->
                    <!-- <td class="text-right"><?php //echo number_format((float)($item['peering_charges'] ?? $item['price']), 2); ?></td> -->
                    <td class="text-right"><?php echo number_format($item['total'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="total-section">
            <?php if ($isInterState): ?>
            <!-- <div class="total-row"><strong>IGST(18%):</strong> <span class="text-right"><?php// echo number_format($igstAmount, 2); ?></span></div> -->
            <?php else: ?>
            <!-- <div class="total-row"><strong>CGST(9%):</strong> <span class="text-right"><?php// echo number_format($cgstAmount, 2); ?></span></div>
            <div class="total-row"><strong>SGST(9%):</strong> <span class="text-right"><?php //echo number_format($sgstAmount, 2); ?></span></div> -->
            <?php endif; ?>
            <?php if ($paymentTransaction && isset($paymentTransaction['status']) && $paymentTransaction['status'] == 'success'): ?>
            <div class="total-row"><strong>Total Paid Amount:</strong> <span class="text-right"><?php echo number_format($totalDue, 2); ?></span></div>
            <?php else: ?>
            <div class="total-row"><strong>Total Amount Due:</strong> <span class="text-right"><?php echo number_format($totalDue, 2); ?></span></div>
            <?php endif; ?>
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
                <strong>Please pay as per following instructions:</strong><br>
                Online Payment/Internet Banking/Credit Card/Debit Card.<br>
                <strong>Bank Name:</strong> <?php echo htmlspecialchars($bankName); ?><br>
                <strong>IFSC Code:</strong> <?php echo htmlspecialchars($bankIFSC); ?><br>
                <strong>MICR No:</strong> <?php echo htmlspecialchars($bankMICR); ?><br>
                <strong>Account Name:</strong> <?php echo htmlspecialchars($bankAccountName); ?><br>
                <strong>Account Type:</strong> <?php echo htmlspecialchars($bankAccountType); ?><br>
                <strong>Account Number:</strong> <?php echo htmlspecialchars($bankAccountNumber); ?><br>
                <strong>Branch:</strong> <?php echo htmlspecialchars($bankBranch); ?>
            </div>
            <div class="payment-right">
                <strong>OR</strong><br><br>
                Make Cheque/Online PG / D.D in Favour of<br>
                <strong><?php echo htmlspecialchars($companyName); ?></strong><br>
                Payable to New Delhi and deposit it in your nearest ICICI branch and acknowledge the payment detail to<br>
                '<?php echo htmlspecialchars($companyEmail); ?>'.<br><br>
                
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
    $dompdf->stream('Tax_Invoice_' . $invoiceNumber . '.pdf', ['Attachment' => true]);
    exit;
    
} elseif ($usePDF && $hasTCPDF) {
    // Use TCPDF for PDF generation - NIXI Tax Invoice Format
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Auction Portal');
    $pdf->SetAuthor('Auction Portal');
    $pdf->SetTitle('Tax Invoice - ' . $invoiceNumber);
    $pdf->SetSubject('NIXI Tax Invoice');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(TRUE, 10);
    
    // Add a page
    $pdf->AddPage();
    
    // Tax Invoice Header
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.5);
    $pdf->Cell(0, 12, 'TAX INVOICE', 1, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, '(Original for Recipient)', 0, 1, 'C');
    $pdf->Ln(3);
    
    // Company Header
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 7, $companyName, 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, $companyFullName, 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 4, $companyAddress, 0, 1, 'C');
    $pdf->Cell(0, 4, 'GSTIN: ' . $companyGSTIN . ' | PAN: ' . $companyPAN, 0, 1, 'C');
    $pdf->Cell(0, 4, 'Phone: ' . $companyPhone . ' | Email: ' . $companyEmail, 0, 1, 'C');
    $pdf->Ln(3);
    
    // Sold By and Bill To
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->Cell(95, 6, 'Sold By:', 'B', 0, 'L');
    $pdf->Cell(95, 6, 'Bill To:', 'B', 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(95, 5, $companyName, 0, 0, 'L');
    $pdf->Cell(95, 5, $customerName, 0, 1, 'L');
    $pdf->Cell(95, 5, $companyAddress, 0, 0, 'L');
    $pdf->Cell(95, 5, $customerAddress, 0, 1, 'L');
    $pdf->Cell(95, 5, 'GSTIN: ' . $companyGSTIN, 0, 0, 'L');
    if ($customerGSTIN) {
        $pdf->Cell(95, 5, 'GSTIN: ' . $customerGSTIN, 0, 1, 'L');
    } else {
        $pdf->Cell(95, 5, '', 0, 1, 'L');
    }
    $pdf->Cell(95, 5, 'State: ' . $companyState . ' (Code: ' . $companyStateCode . ')', 0, 0, 'L');
    if ($customerState) {
        $pdf->Cell(95, 5, 'State: ' . $customerState . ($customerStateCode ? ' (Code: ' . $customerStateCode . ')' : ''), 0, 1, 'L');
    } else {
        $pdf->Cell(95, 5, '', 0, 1, 'L');
    }
    $pdf->Cell(95, 5, '', 0, 0, 'L');
    $pdf->Cell(95, 5, 'Email: ' . $customerEmail, 0, 1, 'L');
    $pdf->Cell(95, 5, '', 0, 0, 'L');
    $pdf->Cell(95, 5, 'Mobile: ' . $customerMobile, 0, 1, 'L');
    $pdf->Ln(3);
    
    // Invoice Details Box
    $pdf->SetDrawColor(0, 0, 0);
    $pdf->SetLineWidth(0.3);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(95, 5, 'Invoice Number: ' . $invoiceNumber, 1, 0, 'L');
    $pdf->Cell(95, 5, 'Invoice Date: ' . $issueDate, 1, 1, 'L');
    $pdf->Cell(95, 5, 'Place of Supply: ' . ($customerState ?: $companyState), 1, 0, 'L');
    $pdf->Cell(95, 5, 'Due Date: ' . $dueDate, 1, 1, 'L');
    $pdf->Ln(3);
    
    // Items Table Header
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(240, 240, 240);
    $colWidths = [12, 50, 12, 10, 12, 15, 8, 12, 8, 12, 15];
    $headers = ['S.No.', 'Description', 'HSN/SAC', 'Qty', 'Rate', 'Taxable Value', 'CGST%', 'CGST Amt', 'SGST%', 'SGST Amt', 'Total'];
    
    foreach ($headers as $idx => $header) {
        $align = ($idx == 0 || $idx == 2 || $idx == 3 || $idx == 6 || $idx == 8) ? 'C' : (($idx >= 4 && $idx <= 10 && $idx != 6 && $idx != 8) ? 'R' : 'L');
        $pdf->Cell($colWidths[$idx], 6, $header, 1, 0, $align, true);
    }
    $pdf->Ln();
    
    // Item Rows
    $pdf->SetFont('helvetica', '', 8);
    $sno = 1;
    foreach ($invoiceItems as $item) {
        $itemCgst = $item['total'] * $cgstRate;
        $itemSgst = $item['total'] * $sgstRate;
        $itemTotal = $item['total'] * (1 + $taxRate);
        
        $pdf->Cell($colWidths[0], 6, $sno++, 1, 0, 'C');
        $pdf->Cell($colWidths[1], 6, substr($item['description'], 0, 30), 1, 0, 'L');
        $pdf->Cell($colWidths[2], 6, '998314', 1, 0, 'C');
        $pdf->Cell($colWidths[3], 6, $item['quantity'], 1, 0, 'C');
        $pdf->Cell($colWidths[4], 6, '₹' . number_format($item['price'], 2), 1, 0, 'R');
        $pdf->Cell($colWidths[5], 6, '₹' . number_format($item['total'], 2), 1, 0, 'R');
        $pdf->Cell($colWidths[6], 6, number_format($cgstRate * 100, 2), 1, 0, 'C');
        $pdf->Cell($colWidths[7], 6, '₹' . number_format($itemCgst, 2), 1, 0, 'R');
        $pdf->Cell($colWidths[8], 6, number_format($sgstRate * 100, 2), 1, 0, 'C');
        $pdf->Cell($colWidths[9], 6, '₹' . number_format($itemSgst, 2), 1, 0, 'R');
        $pdf->Cell($colWidths[10], 6, '₹' . number_format($itemTotal, 2), 1, 1, 'R');
    }
    
    // Empty rows
    for ($i = 0; $i < $emptyRowsCount; $i++) {
        foreach ($colWidths as $width) {
            $pdf->Cell($width, 6, '', 1, 0);
        }
        $pdf->Ln();
    }
    
    $pdf->Ln(3);
    
    // Tax Summary and Total Amount
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(100, 6, 'Tax Summary', 1, 0, 'L', true);
    $pdf->Cell(85, 6, 'Total Amount', 1, 1, 'L', true);
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(25, 5, 'Taxable Value', 1, 0, 'L');
    $pdf->Cell(25, 5, 'CGST', 1, 0, 'C');
    $pdf->Cell(25, 5, 'SGST', 1, 0, 'C');
    $pdf->Cell(25, 5, 'Total Tax', 1, 0, 'C');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(40, 5, 'Subtotal:', 1, 0, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(45, 5, '₹' . number_format($subtotal, 2), 1, 1, 'R');
    
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(25, 5, '₹' . number_format($taxableAmount, 2), 1, 0, 'R');
    $pdf->Cell(25, 5, '₹' . number_format($cgstAmount, 2), 1, 0, 'R');
    $pdf->Cell(25, 5, '₹' . number_format($sgstAmount, 2), 1, 0, 'R');
    $pdf->Cell(25, 5, '₹' . number_format($taxAmount, 2), 1, 0, 'R');
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(40, 5, 'CGST (' . number_format($cgstRate * 100, 2) . '%):', 1, 0, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(45, 5, '₹' . number_format($cgstAmount, 2), 1, 1, 'R');
    
    $pdf->Cell(100, 5, '', 0, 0);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(40, 5, 'SGST (' . number_format($sgstRate * 100, 2) . '%):', 1, 0, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(45, 5, '₹' . number_format($sgstAmount, 2), 1, 1, 'R');
    
    $pdf->Cell(100, 5, '', 0, 0);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->SetLineWidth(0.5);
    $pdf->Cell(40, 6, 'Total Amount:', 1, 0, 'L', true);
    $pdf->Cell(45, 6, '₹' . number_format($totalDue, 2), 1, 1, 'R', true);
    
    $pdf->Ln(3);
    
    // Payment Information
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 5, 'Payment Information:', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    
    if ($paymentTransaction) {
        $pdf->Cell(0, 4, 'Transaction ID: ' . ($paymentTransaction['payu_transaction_id'] ?? $paymentTransaction['transaction_id']), 0, 1, 'L');
        $pdf->Cell(0, 4, 'Payment Date: ' . date('d-M-Y H:i', strtotime($paymentTransaction['updated_at'] ?? $paymentTransaction['created_at'])), 0, 1, 'L');
        $pdf->SetTextColor(0, 128, 0);
        $pdf->Cell(0, 4, 'Status: Paid', 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
    } else {
        $pdf->SetTextColor(255, 0, 0);
        $pdf->Cell(0, 4, 'Payment Status: Pending', 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 4, 'Please complete payment within 7 days.', 0, 1, 'L');
    }
    
    $pdf->Ln(5);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 4, 'This is a computer generated invoice and does not require a signature.', 0, 1, 'C');
    $pdf->Cell(0, 4, 'For any queries, please contact us at ' . $companyEmail . ' or ' . $companyPhone, 0, 1, 'C');
    
    // Output PDF
    $pdf->Output('Tax_Invoice_' . $invoiceNumber . '.pdf', 'D');
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
        <title>Invoice - <?php echo htmlspecialchars($invoiceNumber); ?></title>
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
                /* padding: 5px; */
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
                    <!-- <div class="logo-nixi">nixi</div> -->
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
                        <strong>Address:</strong> <?php echo htmlspecialchars($customerAddress); ?><br>
                        <?php if ($customerState): ?>
                        <?php echo htmlspecialchars($customerState); ?> <?php echo htmlspecialchars($customerStateCode ?: ''); ?> <?php echo htmlspecialchars($user['pincode'] ?? ''); ?><br>
                        <?php endif; ?>
                        <strong>Phone:</strong> <?php echo htmlspecialchars($customerMobile); ?><br>
                        <strong>Email:</strong> <?php echo htmlspecialchars($customerEmail); ?><br>
                        <?php if ($customerGSTIN): ?>
                        <strong>GSTIN/UIN:</strong> <?php echo htmlspecialchars($customerGSTIN); ?><br>
                        <?php endif; ?>
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
                    <strong>Invoice No:</strong> <?php echo htmlspecialchars($invoiceNumber); ?> <strong>Customer Id:</strong> <?php// echo htmlspecialchars($customerId); ?>
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
                        <!-- <th style="width: 12%;">Peering Capacity</th>
                        <th style="width: 15%;" class="text-right">Peering Charges</th> -->
                        <th style="width: 20%;" class="text-right">Amount(₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $sno = 1; foreach ($invoiceItems as $item): ?>
                    <tr>
                        <td class="text-center"><?php echo $sno++; ?></td>
                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                        <td class="text-center"><?php echo $item['quantity']; ?></td>
                        <!-- <td class="text-center"><?php// echo htmlspecialchars($item['peering_capacity'] ?? '-'); ?></td>
                        <td class="text-right"><?php// echo number_format((float)($item['peering_charges'] ?? $item['price']), 2); ?></td> -->
                        <td class="text-right"><?php echo number_format($item['total'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="total-section">
                <?php if ($isInterState): ?>
                <!-- <div class="total-row"><strong>IGST(18%):</strong> <span class="text-right"><?php// echo number_format($igstAmount, 2); ?></span></div> -->
                <?php else: ?>
                <!-- <div class="total-row"><strong>CGST(9%):</strong> <span class="text-right"><?php //echo number_format($cgstAmount, 2); ?></span></div>
                <div class="total-row"><strong>SGST(9%):</strong> <span class="text-right"><?php //echo number_format($sgstAmount, 2); ?></span></div> -->
                <?php endif; ?>
                <?php if ($paymentTransaction && isset($paymentTransaction['status']) && $paymentTransaction['status'] == 'success'): ?>
                <div class="total-row"><strong>Total Paid Amount:</strong> <span class="text-right"><?php echo number_format($totalDue, 2); ?></span></div>
                <?php else: ?>
                <div class="total-row"><strong>Total Amount Due:</strong> <span class="text-right"><?php echo number_format($totalDue, 2); ?></span></div>
                <?php endif; ?>
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
                    <strong>Please pay as per following instructions:</strong><br>
                    Online Payment/Internet Banking/Credit Card/Debit Card.<br>
                    <strong>Bank Name:</strong> <?php echo htmlspecialchars($bankName); ?><br>
                    <strong>IFSC Code:</strong> <?php echo htmlspecialchars($bankIFSC); ?><br>
                    <strong>MICR No:</strong> <?php echo htmlspecialchars($bankMICR); ?><br>
                    <strong>Account Name:</strong> <?php echo htmlspecialchars($bankAccountName); ?><br>
                    <strong>Account Type:</strong> <?php echo htmlspecialchars($bankAccountType); ?><br>
                    <strong>Account Number:</strong> <?php echo htmlspecialchars($bankAccountNumber); ?><br>
                    <strong>Branch:</strong> <?php echo htmlspecialchars($bankBranch); ?>
                </div>
                <div class="payment-right">
                    <strong>OR</strong><br><br>
                    Make Cheque/Online PG / D.D in Favour of<br>
                    <strong><?php echo htmlspecialchars($companyName); ?></strong><br>
                    Payable to New Delhi and deposit it in your nearest ICICI branch and acknowledge the payment detail to<br>
                    '<?php echo htmlspecialchars($companyEmail); ?>'.<br><br>
                   
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
            // Build the PDF download URL with all auction IDs
            $pdfUrl = '?auction_id=' . urlencode($auctionIdsInput) . '&format=pdf';
            $hasTCPDF = class_exists('TCPDF');
            ?>
            <?php if ($hasDomPDF || $hasTCPDF): ?>
                <a href="<?php echo htmlspecialchars($pdfUrl); ?>" class="btn">Download as PDF</a>
            <?php else: ?>
                <button class="btn" onclick="downloadPDF(event)">Download as PDF</button>
            <?php endif; ?>
            <!-- <button class="btn" onclick="window.print()" style="margin-left: 10px;">Print</button> -->
            <a href="user_won_auctions.php" class="btn" style="margin-left: 10px;">Back to Won Auctions</a>
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
                    filename: 'Tax_Invoice_<?php echo htmlspecialchars($invoiceNumber); ?>.pdf',
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
                
                // Show loading indicator
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

