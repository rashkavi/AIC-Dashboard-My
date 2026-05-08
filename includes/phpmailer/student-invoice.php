<?php
function renderInvoice($invoiceData)
{
    $currentYear = date('Y');

    // Extract data from the invoice object
    $invoiceNumber = htmlspecialchars($invoiceData['invoice_number'] ?? 'INV-00000');
    $invoiceDate = htmlspecialchars($invoiceData['date'] ?? date('d F Y'));
    $billTo = $invoiceData['bill_to'] ?? [];
    $items = $invoiceData['items'] ?? [];
    $totalAmount = htmlspecialchars($invoiceData['total_amount'] ?? '$0.00');
    $notes = htmlspecialchars($invoiceData['notes'] ?? 'Thank you for your business!');
    $paymentInfo = $invoiceData['payment_info'] ?? [];

    // Start building the HTML
    $html = "<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>ENVIIZ Invoice</title>
    <link href=\"https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap\" rel=\"stylesheet\">
    <style>
    /* PDF-optimized styles */
    body {
        font-family: 'Inter', sans-serif;
        line-height: 1.4;
        color: #2d3748;
        margin: 0;
        padding: 0;
        font-size: 10pt;
    }

    .invoice-container {
        width: 100%;
        max-width: 800px;
        margin: 0 auto;
        background: white;
    }

    .invoice-header {
        background: #24274c;
        padding: 15px 25px;
        display: block;
        overflow: hidden;
        display: flex;
        flex-direction: row;
    }

    .logo-container {
        display: inline-block;
        vertical-align: middle;
        margin-right: 15px;
        justify-content: start;
    }

    .logo {
        height: 35px;
        width: auto;
    }

    .invoice-title-container {
        display: inline-block;
        vertical-align: middle;
        justify-content: end;
    }

    .invoice-title {
        color: white;
        font-size: 16pt;
        font-weight: bold;
        margin: 0 0 5px 0;
    }

    .invoice-number {
        color: rgba(255,255,255,0.9);
        font-size: 9pt;
    }

    .billing-section {
        padding: 20px 25px;
        overflow: hidden;
        border-bottom: 1px solid #e4e7eb;
    }

    .bill-card {
        width: 48%;
        float: left;
        background: #f9fafc;
        padding: 15px;
        margin-right: 2%;
        box-sizing: border-box;
        border-radius: 4px;
    }

    .bill-card:last-child {
        margin-right: 0;
        float: right;
    }

    .section-title {
        font-size: 9pt;
        font-weight: bold;
        color: #24274c;
        margin-bottom: 8px;
    }

    .address {
        line-height: 1.5;
        color: #718096;
        font-size: 8pt;
    }

    .date-status {
        padding: 12px 25px;
        background: #f9fafc;
        font-size: 9pt;
    }

    .items-table {
        width: 100%;
        border-collapse: collapse;
        margin: 0 25px;
        font-size: 9pt;
    }

    .items-table thead th {
        background: #f9fafc;
        padding: 8px 12px;
        text-align: left;
        font-weight: bold;
        color: #718096;
        border-bottom: 1px solid #e4e7eb;
    }

    .items-table tbody td {
        padding: 8px 12px;
        border-bottom: 1px solid #e4e7eb;
    }

    .total-row {
        background: #f9fafc;
        font-weight: bold;
    }

    .notes-section {
        padding: 15px 25px;
        border-top: 1px solid #e4e7eb;
        font-size: 9pt;
    }

    .notes-title {
        font-weight: bold;
        margin-bottom: 5px;
        color: #24274c;
    }

    .payment-info {
        padding: 15px 25px;
        background: #f9fafc;
        font-size: 9pt;
    }

    .payment-title {
        font-weight: bold;
        margin-bottom: 8px;
        color: #24274c;
    }

    .payment-detail {
        margin-bottom: 5px;
        overflow: hidden;
    }

    .payment-label {
        width: 100px;
        float: left;
        font-weight: normal;
        color: #718096;
    }

    .payment-value {
        overflow: hidden;
    }

    .thank-you {
        text-align: center;
        padding: 15px;
        color: #718096;
        font-size: 8pt;
        border-top: 1px solid #e4e7eb;
    }

    /* Mobile adjustments */
    @media screen and (max-width: 600px) {
        .bill-card {
            width: 100%;
            float: none;
            margin-bottom: 15px;
            margin-right: 0;
        }
        
        .items-table {
            margin: 0 10px;
        }
    }
</style>
</head>
<body>
<div class=\"invoice-container\">
    <div class=\"invoice-header\">
        <div class=\"logo-container\">
            <img src=\"https://dashboard.enviiz.com/assets/imgs/common/enviiz-white.webp\" alt=\"ENVIIZ Logo\" class=\"logo\">
        </div>
        <div class=\"invoice-title-container\">
            <h1 class=\"invoice-title\">INVOICE</h1>
            <div class=\"invoice-number\">NO: {$invoiceNumber}</div>
        </div>
    </div>

    <div class=\"billing-section\">
        <div class=\"bill-card\">
            <div class=\"section-title\">Bill To:</div>
            <div class=\"address\">
                <div>" . htmlspecialchars($billTo['name'] ?? 'Customer Name') . "</div>
                <div>" . htmlspecialchars($billTo['mobile'] ?? '+123-456-7890') . "</div>
                <div>" . htmlspecialchars($billTo['email'] ?? '123 Anywhere St., Any City') . "</div>
            </div>
        </div>
        <div class=\"bill-card\">
            <div class=\"section-title\">From:</div>
            <div class=\"address\">
                <div>ENVIIZ LMS</div>
                <div>+123-456-7890</div>
                <div>enviiz@example.com</div>
            </div>
        </div>
    </div>
    <div class=\"date-status\">
        <div class=\"date\">Date: {$invoiceDate}</div>
    </div>
    <div style=\"padding: 0 40px;\">
        <table class=\"items-table\">
            <thead>
                <tr>
                    <th>Course</th>
                    <th>Payment Type</th>
                    <th>Amount</th>
                    <th>Discount</th>
                </tr>
            </thead>
            <tbody>";

    foreach ($items as $item) {
        $html .= "<tr>
                    <td>" . htmlspecialchars($item['course']) . "</td>
                    <td>" . htmlspecialchars($item['payment_type']) . "</td>
                    <td>" . htmlspecialchars($item['amount']) . "</td>
                    <td>" . htmlspecialchars($item['discount']) . "</td>
                </tr>";
    }

    $html .= "<tr class=\"total-row\">
                <td colspan=\"3\" style=\"text-align: right;\">Total Amount:</td>
                <td>{$totalAmount}</td>
            </tr>
            </tbody>
        </table>
    </div>

    <div class=\"notes-section\">
        <div class=\"notes-title\">Notes:</div>
        <div class=\"notes-content\">{$notes}</div>
        <div style=\"font-size: 12px; color: var(--text-light);\">Thank you for your business!</div>
    </div>

    <div class=\"payment-info\">
        <div class=\"payment-title\">Payment Information:</div>
        <div class=\"payment-detail\">
            <div class=\"payment-label\">Bank:</div>
            <div>" . htmlspecialchars($paymentInfo['bank'] ?? 'Name Bank') . "</div>
        </div>
        <div class=\"payment-detail\">
            <div class=\"payment-label\">Account No:</div>
            <div>" . htmlspecialchars($paymentInfo['account_number'] ?? '123-456-7890') . "</div>
        </div>
        <div class=\"payment-detail\">
            <div class=\"payment-label\">Account Name:</div>
            <div>" . htmlspecialchars($paymentInfo['account_name'] ?? '123-456-7890') . "</div>
        </div>
        <div class=\"payment-detail\">
            <div class=\"payment-label\">Account Name:</div>
            <div>" . htmlspecialchars($paymentInfo['branch'] ?? '123-456-7890') . "</div>
        </div>
        <div class=\"payment-detail\">
            <div class=\"payment-label\">Email:</div>
            <div>" . htmlspecialchars($paymentInfo['email'] ?? 'support@enviiz.com') . "</div>
        </div>
    </div>

    <div class=\"thank-you\">
        <div>Need help? Contact us at support@enviiz.com or call +1 (234) 567-8900</div>
        <div style=\"margin-top: 10px;\">© {$currentYear} ENVIIZ LMS. All rights reserved.</div>
    </div>
</div>
</body>
</html>";

    return $html;
}
