<?php
require_once("./includes/connection.php");
require_once("./includes/Project.php");

if (!isset($_GET["receipt-id"]) || empty($_GET["receipt-id"])) {
    die("Invalid receipt");
}

$receiptId = base64_decode(str_replace("/", "", $_GET["receipt-id"]));

if (!$receiptId || !is_numeric($receiptId)) {
    die("Invalid receipt ID");
}

$receiptRs = Database::search("
    SELECT 
        cpp.course_payment_has_payment_id,
        cpp.paid_amount,
        cpp.discount_amount,
        cpp.panelty_amount,
        cpp.date_paid,

        cp.amount AS course_amount,
        cp.due_date,

        pt.payment_type,
        pm.payment_method,

        ld.first_name,
        ld.last_name,
        ld.email,

        s.student_id,
        s.mobile,

        t.title,

        p.programme,
        i.intake,

        c.code,
        c.decimal_places,

        cp.refference

    FROM course_payment_has_payments cpp

    INNER JOIN course_payments cp
        ON cpp.course_payments_course_payments_id = cp.course_payments_id

    INNER JOIN payment_type pt
        ON cp.payment_type_payment_type_id = pt.payment_type_id

    INNER JOIN payment_method pm
        ON cpp.payment_method_payment_method_id = pm.payment_method_id

    INNER JOIN payment_plan_has_students_has_intakes pphsi
        ON cp.payment_plan_has_students_has_intakes_id = pphsi.payment_plan_has_students_has_intakes_id

    INNER JOIN students_has_intakes shi
        ON pphsi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id

    INNER JOIN students s
        ON shi.students_student_id = s.student_id

    INNER JOIN login_details ld
        ON s.login_details_login_details_id = ld.login_details_id

    INNER JOIN title t
        ON s.title_title_id = t.title_id

    INNER JOIN intakes i
        ON shi.intakes_intake_id = i.intake_id

    INNER JOIN programmes p
        ON i.programmes_programme_id = p.programme_id

    INNER JOIN payment_category pc
        ON pphsi.payment_category_payment_category_id = pc.payment_category_id

    INNER JOIN currencies c
        ON pc.currencies_currency_id = c.currency_id
  
    WHERE cpp.course_payment_has_payment_id = '" . Database::escape($receiptId) . "'

    LIMIT 1
");

if ($receiptRs->num_rows == 0) {
    die("Receipt not found");
}

$data = $receiptRs->fetch_assoc();

$studentName = $data["title"] . ". " . $data["first_name"] . " " . $data["last_name"];
$currency = $data["code"];
$decimal = (int)$data["decimal_places"];

$paidAmount = number_format((float)$data["paid_amount"], $decimal);
$discountAmount = number_format((float)$data["discount_amount"], $decimal);
$penaltyAmount = number_format((float)$data["panelty_amount"], $decimal);

$totalAmount = (float)$data["course_amount"] + (float)$data["panelty_amount"] - (float)$data["discount_amount"];
$dueAmount = max(0, $totalAmount - (float)$data["paid_amount"]);

$receiptNo = "REC-" . str_pad($data["course_payment_has_payment_id"], 6, "0", STR_PAD_LEFT);
$date = date("Y-m-d", strtotime($data["date_paid"]));

date_default_timezone_set('Asia/Colombo');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Payment Receipt | <?= Project::getBaseName() ?></title>

    <link rel="stylesheet" href="./assets/css/payment-receipt.css?= Project::getVersion() ?>">

</head>

<body>

    <div class="print-btn">
        <button onclick="window.print()">Download / Print Receipt</button>
    </div>

    <div class="page">
        <div class="receipt">

            <div class="header">
                <div>
                    <img src="./assets/imgs/<?= Project::getInstituteLogo(); ?>" class="logo">
                </div>

                <div class="header-center">
                    <div class="campus-name"><?= Project::getBaseName(); ?></div>
                    <div class="campus-info">
                        Head Office: No.154, Havelock Road, Thimbirigasyaya, Colombo 05.<br>
                        Tel: +94 114 325 864 / Email: accounts@aicedu.lk
                    </div>
                </div>
            </div>

            <div class="top-meta">
                <div>
                    <strong>Reg. No:</strong> <?= htmlspecialchars($data["student_id"]); ?><br>
                    <strong>Receipt No:</strong> <?= $receiptNo; ?><br>
                    <strong>Date:</strong> <?= $date; ?>
                </div>
            </div>

            <div class="receipt-title">Receipt</div>

            <div class="line-row">
                <span class="label">Received with thanks from</span>
                <span class="line"><?= htmlspecialchars($studentName); ?></span>
            </div>

            <div class="line-row">
                <span class="label">The sum of <?= $currency; ?></span>
                <span class="line"><?= $currency . " " . $paidAmount; ?></span>
            </div>

            <div class="two-col">
                <div>
                    <div class="line-row">
                        <span class="label">Programme</span>
                        <span class="line"><?= htmlspecialchars($data["programme"]); ?></span>
                    </div>

                    <div class="line-row">
                        <span class="label">Intake</span>
                        <span class="line"><?= htmlspecialchars($data["intake"]); ?></span>
                    </div>

                    <div class="line-row">
                        <span class="label">Payment Method</span>
                        <span class="line"><?= htmlspecialchars($data["payment_method"]); ?></span>
                    </div>

                    <div class="line-row">
                        <span class="label">Reference No</span>
                        <span class="line"><?= htmlspecialchars($data["reference_number"] ?? "-"); ?></span>
                    </div>
                </div>

                <div>
                    <table class="fee-table">
                        <thead>
                            <tr>
                                <th>Type of Fee</th>
                                <th>Amount</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr>
                                <td><?= htmlspecialchars($data["payment_type"]); ?></td>
                                <td><?= $currency . " " . $paidAmount; ?></td>
                            </tr>

                            <tr>
                                <td>Discount</td>
                                <td><?= $currency . " " . $discountAmount; ?></td>
                            </tr>

                            <tr>
                                <td>Penalty</td>
                                <td><?= $currency . " " . $penaltyAmount; ?></td>
                            </tr>

                            <tr>
                                <td><strong>Total Paid</strong></td>
                                <td><strong><?= $currency . " " . $paidAmount; ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="payment-mode">
                <strong>Payment Mode:</strong>

                <div class="checkbox-row">
                    <div class="check-item">
                        <span class="box"><?= strtolower($data["payment_method"]) == "cash" ? "✓" : ""; ?></span>
                        Cash
                    </div>

                    <div class="check-item">
                        <span class="box"><?= strtolower($data["payment_method"]) == "chq" ? "✓" : ""; ?></span>
                        CHQ
                    </div>

                    <div class="check-item">
                        <span class="box"><?= strtolower($data["payment_method"]) == "tt" ? "✓" : ""; ?></span>
                        TT
                    </div>

                    <div class="check-item">
                        <span class="box"><?= str_contains(strtolower($data["payment_method"]), "card") ? "✓" : ""; ?></span>
                        Credit Card
                    </div>

                    <div class="check-item">
                        <span class="box"><?= strtolower($data["payment_method"]) == "draft" ? "✓" : ""; ?></span>
                        Draft
                    </div>
                </div>
            </div>

            <div class="amount-summary">
                <div>
                    <strong>Total Amount</strong>
                    <div class="summary-box"><?= $currency . " " . number_format($totalAmount, $decimal); ?></div>
                </div>

                <div>
                    <strong>Paid Amount</strong>
                    <div class="summary-box"><?= $currency . " " . $paidAmount; ?></div>
                </div>

                <div>
                    <strong>Due Amount</strong>
                    <div class="summary-box"><?= $currency . " " . number_format($dueAmount, $decimal); ?></div>
                </div>

                <div>
                    <strong>Due Date</strong>
                    <div class="summary-box"><?= htmlspecialchars($data["due_date"] ?? "-"); ?></div>
                </div>
            </div>

            <div class="note">
                Payments made will not be refundable or transferable under any circumstances.<br>
                Receipt is valid subject to realization of cheque. Taxes or bank charges may be applied where required.
            </div>

            <!-- <div class="signatures">
                <div class="signature-line">Official Signature</div>
                <div class="signature-line">Signature of Recipient</div>
            </div> -->

            <div class="footer-row">
                <div>Genarated At: <?= date("Y-m-d H:i A"); ?></div>
                <div><?= Project::getBaseName(); ?></div>
            </div>

        </div>
    </div>

</body>

</html>