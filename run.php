<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Template</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>

    <style>
        body {
            background: #f3f4f6;
            font-family: Arial, Helvetica, sans-serif;
            color: #2f4058;
        }

        .invoice-wrapper {
            max-width: 850px;
            margin: 40px auto;
            padding: 20px;
        }

        .invoice-card {
            background: #ffffff;
            border: 1px solid #d9dde3;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
            padding: 42px 46px 50px;
            min-height: 700px;
        }

        .brand-logo {
            max-width: 180px;
            height: auto;
            object-fit: contain;
        }

        .company-address {
            margin-top: 14px;
            font-size: 14px;
            line-height: 1.5;
            color: #4f5f73;
        }

        .invoice-title {
            text-align: right;
        }

        .invoice-title h1 {
            margin: 0;
            font-size: 52px;
            line-height: 1;
            font-weight: 700;
            color: #3f5168;
            letter-spacing: 0.5px;
        }

        .invoice-number {
            margin-top: 8px;
            font-size: 32px;
            font-weight: 700;
            color: #3f5168;
        }

        .info-section {
            margin-top: 44px;
        }

        .info-label {
            display: block;
            font-size: 15px;
            font-weight: 700;
            color: #3f5168;
            margin-bottom: 6px;
        }

        .info-value {
            font-size: 15px;
            line-height: 1.5;
            color: #506174;
        }

        .invoice-table-wrap {
            margin-top: 70px;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
        }

        .invoice-table thead th {
            font-size: 15px;
            font-weight: 700;
            color: #3f5168;
            padding: 12px 0;
            border-bottom: 1px solid #d7dce2;
        }

        .invoice-table tbody td {
            font-size: 15px;
            color: #506174;
            padding: 16px 0 6px;
            vertical-align: top;
        }

        .invoice-table .amount-col {
            text-align: right;
            white-space: nowrap;
        }

        .remarks-row td {
            padding-top: 8px;
            color: #5d6c7c;
        }

        .signature-section {
            margin-top: 110px;
        }

        .signature-line {
            width: 120px;
            border-top: 1.5px solid #55677c;
            margin-bottom: 8px;
        }

        .signature-text {
            font-size: 14px;
            color: #4f5f73;
        }

        @media (max-width: 767.98px) {
            .invoice-card {
                padding: 28px 22px 36px;
            }

            .invoice-title {
                text-align: left;
                margin-top: 25px;
            }

            .invoice-title h1 {
                font-size: 38px;
            }

            .invoice-number {
                font-size: 24px;
            }

            .invoice-table tbody td,
            .invoice-table thead th,
            .info-value,
            .info-label,
            .company-address {
                font-size: 14px;
            }

            .signature-section {
                margin-top: 70px;
            }
        }

        @media print {
            body {
                background: #ffffff;
            }

            .invoice-wrapper {
                max-width: 100%;
                margin: 0;
                padding: 0;
            }

            .invoice-card {
                border: none;
                box-shadow: none;
                min-height: auto;
            }
        }
    </style>
</head>
<body>

    <div class="container invoice-wrapper">
        <div class="invoice-card">

            <!-- Header -->
            <div class="row align-items-start">
                <div class="col-md-7">
                    <img src="./assets/imgs/common/logo.png" alt="Logo" class="brand-logo">

                    <div class="company-address">
                        58/A1, Nugegoda Road, Pepiliyana.<br>
                        +94 77 888 37 36 / +94 112 82 62 82
                    </div>
                </div>

                <div class="col-md-5">
                    <div class="invoice-title">
                        <h1>Invoice</h1>
                        <div class="invoice-number"># 4593</div>
                    </div>
                </div>
            </div>

            <!-- Billing info -->
            <div class="row info-section">
                <div class="col-md-4 col-sm-6 mb-4">
                    <span class="info-label">Billed To:</span>
                    <div class="info-value">
                        S. R. B. Kavisha<br>
                        Prabodini<br>
                        Rathwathia
                    </div>
                </div>

                <div class="col-md-4 col-sm-6 mb-4">
                    <span class="info-label">Pay Method:</span>
                    <div class="info-value">
                        Seylan 001
                    </div>
                </div>

                <div class="col-md-4 col-sm-12 mb-4 text-md-end">
                    <span class="info-label">Date:</span>
                    <div class="info-value">
                        2026-04-08<br>
                        14:07:00
                    </div>
                </div>
            </div>

            <!-- Items -->
            <div class="invoice-table-wrap">
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th class="amount-col">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Monthly Fee - April</td>
                            <td class="amount-col">Rs. 30,000.00</td>
                        </tr>
                        <tr class="remarks-row">
                            <td>Remarks: 2026/06/08</td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Signature -->
            <div class="signature-section">
                <div class="signature-line"></div>
                <div class="signature-text">Prepared by</div>
            </div>

        </div>
    </div>

</body>
</html>