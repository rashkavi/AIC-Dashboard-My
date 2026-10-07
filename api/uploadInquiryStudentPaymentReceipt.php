<?php

/**
 * PAYMENT RECEIPT PROCESSOR - CORRECTED VERSION (FINAL FIX - TAX MODEL)
 * 
 * Key Fixes:
 * 1. Tax Model: Tax is calculated as a PERCENTAGE OF THE FINAL (TAX-INCLUSIVE) AMOUNT
 * 2. Tax Rate: Latest from tax_rates table, default 2.50%
 * 3. First Payment: amount (base) is scheduled amount
 *    When student pays this amount WITH tax:
 *    - base = paid_amount × (1 - taxRate%)
 *    - tax = paid_amount × (taxRate%)
 *    - total_due = paid_amount
 *    Example: Student pays 200 GBP (tax-inclusive)
 *      base = 200 × 0.975 = 195 GBP
 *      tax = 200 × 0.025 = 5 GBP
 *      total = 200 GBP ✓ (exactly 2.5% of 200)
 * 
 * 4. SUBSEQUENT PAYMENTS (CRITICAL FIX):
 *    When balance remains from prior partial payments:
 *    - Balance is the remaining TOTAL due (base + tax)
 *    - Extract base: base = balance × (1 - taxRate%)
 *    - Calculate tax: tax = balance × (taxRate%)
 *    - Total owed = balance
 *    
 *    Example: Scheduled 200 GBP, paid 100 GBP (including tax)
 *      Remaining balance: 200 - 100 = 100 GBP
 *      Base of remaining: 100 × 0.975 = 97.5 GBP
 *      Tax of remaining: 100 × 0.025 = 2.5 GBP
 *      Total: 97.5 + 2.5 = 100 GBP ✓
 */

$status = new stdClass();
require_once("../includes/UserClass.php");
require_once("../includes/connection.php");
require_once("../includes/UserPermissionsClass.php");
require_once("../includes/Project.php");
require_once("../includes/DateTimeClass.php");
require_once("../includes/phpmailer/EmailLayoutRenderer.php");
require_once("../includes/phpmailer/EmailNotificationService.php");
require_once("../includes/phpmailer/EmailTemplateContent.php");
require_once("../includes/phpmailer/mail-process.php");

if (SessionUser::hasValidSession()) {
    $sessionUserRole   = SessionUser::getCurrentUserRole();
    if ($sessionUserRole != null) {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            $payment_id = isset($_POST['payment_id']) ? (int)$_POST['payment_id'] : 0;
            $note       = isset($_POST['note'])       ? trim($_POST['note'])       : '';

            $awaitingQuery = "
                SELECT
                    `cp`.`course_payments_id`,
                    `ps`.`payment_status`,
                    `ps`.`payment_status_id`,
                    `cp`.`due_date`,
                    `cp`.`amount`,
                    `cp`.`discount`,
                    `cp`.`discount_type_discount_type_id`,
                    `shi`.`student_id`,
                    `shi`.`intakes_intake_id`,
                    `ld`.`first_name`,
                    `ld`.`last_name`,
                    `t`.`title`,
                    `s`.`profile_img`,
                    `c`.`code`,
                    `c`.`decimal_places`,
                    `c`.`currency_id`  AS `course_currency_id`,
                    `pt`.`payment_type`,
                    `pc`.`payment_category`,
                    IFNULL(pp.panelty_amount, 0) AS panelty_amount,
                    `lead_student_status_lead_student_status_id`
                FROM `course_payments` AS `cp`
                INNER JOIN `payment_status`  AS `ps`  ON `cp`.`payment_status_payment_status_id`        = `ps`.`payment_status_id`
                INNER JOIN `payment_plan_has_students_has_intakes` AS `pshi`
                                                       ON `cp`.`payment_plan_has_students_has_intakes_id` = `pshi`.`payment_plan_has_students_has_intakes_id`
                INNER JOIN `payment_category` AS `pc` ON `pshi`.`payment_category_payment_category_id`   = `pc`.`payment_category_id`
                INNER JOIN `currencies`       AS `c`  ON `pc`.`currencies_currency_id`                   = `c`.`currency_id`
                INNER JOIN `payment_type`     AS `pt` ON `cp`.`payment_type_payment_type_id`              = `pt`.`payment_type_id`
                INNER JOIN `students_has_intakes` AS `shi`
                                                       ON `pshi`.`students_has_intakes_students_has_intakes_id` = `shi`.`students_has_intakes_id`
                INNER JOIN `students`         AS `s`  ON `shi`.`students_student_id`                     = `s`.`student_id`
                INNER JOIN `login_details`    AS `ld` ON `s`.`login_details_login_details_id`             = `ld`.`login_details_id`
                INNER JOIN `title`            AS `t`  ON `s`.`title_title_id`                             = `t`.`title_id`
                LEFT JOIN (
                    SELECT `course_payments_course_payments_id`, SUM(`amount`) AS `panelty_amount`
                    FROM   `panelty_payments`
                    GROUP BY `course_payments_course_payments_id`
                ) pp ON `cp`.`course_payments_id` = pp.`course_payments_course_payments_id`
                WHERE `cp`.`course_payments_id` = '" . $payment_id . "'
                  AND `cp`.`payment_status_payment_status_id` = '1'
                
                GROUP BY `cp`.`course_payments_id`
                ORDER BY `cp`.`course_payments_id` DESC
            ";

            $paymentPlan = Database::search($awaitingQuery);

            if ($paymentPlan->num_rows == 0) {
                $status->error = 'Invalid request';
            } else {
                $paymentData = $paymentPlan->fetch_assoc();
                UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

                if (!(
                    SessionUser::ifUserIsPro($sessionUserRole) ||
                    SessionUser::ifUserIsCounsiler($sessionUserRole) ||
                    UserPermissions::validPermisisonType('leads') ||
                    UserPermissions::validPermisisonType('payments') ||
                    UserPermissions::hasPermission('intake_payments', 24, $paymentData['intakes_intake_id'])
                )) {
                    $status->error = 'You are not authorized to this action.';
                } else {

                    $paymentSlips = [];

                    // ── File validation ──────────────────────────────────────────────────────
                    if (isset($_FILES["slips"])) {
                        $allowed = ["application/pdf", "image/jpeg", "image/png", "image/jpg"];
                        $ext_map = [
                            "application/pdf" => ".pdf",
                            "image/jpeg"      => ".jpg",
                            "image/png"       => ".png",
                            "image/jpg"       => ".jpg",
                        ];

                        foreach ($_FILES["slips"]["name"] as $key => $slip_name) {
                            $slip_ex = $_FILES["slips"]["type"][$key];
                            if (!in_array($slip_ex, $allowed)) {
                                $status->slip[] = "File '$slip_name' is not a valid type. Only PDF, JPEG, PNG and JPG files are allowed.";
                            } elseif ($_FILES["slips"]["error"][$key] !== 0) {
                                $status->slip[] = "There was an error uploading '$slip_name'.";
                            } elseif ($_FILES["slips"]["size"][$key] > 10 * 1024 * 1024) {
                                $status->slip[] = "File '$slip_name' exceeds the maximum allowed size of 10MB.";
                            } else {
                                $slipHash    = hash_file('sha256', $_FILES["slips"]["tmp_name"][$key]);
                                $isDuplicate = Database::search(
                                    "SELECT `course_payment_slip_id` FROM `course_payment_slips`
                                    INNER JOIN `course_payment_has_payments` AS `cpp`
                                        ON `course_payment_slips`.`course_payment_has_payment_id` = `cpp`.`course_payment_has_payment_id`
                                     WHERE `slip_hash` = '" . $slipHash . "' AND `cpp`.`payment_status_payment_status_id` IN (2,3)"
                                );
                                if ($isDuplicate->num_rows > 0) {
                                    $status->slip[] = "$slip_name — Duplicate receipt detected. This slip was already used.";
                                } else {
                                    $paymentSlips[] = 'R' . $payment_id . '_' . uniqid() . $ext_map[$slip_ex];
                                }
                            }
                        }
                    } else {
                        $status->slip[] = "At least one payment slip is required.";
                    }

                    if (!empty($note) && strlen($note) > 150) {
                        $status->note = 'Note must be less than 150 characters';
                    }

                    // ── Validate all clear before proceeding ─────────────────────────────────
                    $requiredProperties = ['error', 'amount', 'slip', 'paid_date', 'method', 'discount', 'panelty', 'note'];
                    $allValid = true;
                    foreach ($requiredProperties as $prop) {
                        if (property_exists($status, $prop)) {
                            $allValid = false;
                            break;
                        }
                    }

                    if ($allValid) {

                        $timezone = new DateTimeZone("Asia/Colombo");
                        $now      = (new DateTime("now", $timezone))->format('Y-m-d H:i:s');

                        // ════════════════════════════════════════════════════════════════════
                        // STEP 1: Fetch latest tax rate from tax_rates table
                        //         Default to 2.50% if table is empty
                        //
                        // Tax Model: CORRECT FORMULA (as percentage of final amount)
                        //   When student pays X amount (tax-inclusive):
                        //   - base = X × (1 - taxRate%)
                        //   - tax = X × (taxRate%)
                        //
                        // Example: Student pays 200 GBP (tax-inclusive), taxRate = 2.5%
                        //   base = 200 × 0.975 = 195 GBP
                        //   tax = 200 × 0.025 = 5 GBP
                        //   total = 200 GBP ✓
                        //   Check: 5 ÷ 200 = 2.5% ✓
                        // ════════════════════════════════════════════════════════════════════
                        $taxRate = 2.50; // Default: 2.50%

                        $latestTaxRateRs = Database::search(
                            "SELECT `tax_rate` FROM `tax_rates` 
                             WHERE `tax_rate` IS NOT NULL AND `tax_rate` > 0
                             ORDER BY `date_added` DESC LIMIT 1"
                        );
                        if ($latestTaxRateRs && $latestTaxRateRs->num_rows > 0) {
                            $taxRateRow = $latestTaxRateRs->fetch_assoc();
                            $taxRate = (float)$taxRateRow['tax_rate'];
                        }

                        // ════════════════════════════════════════════════════════════════════
                        // HELPER: Extract base and tax from a TAX-INCLUSIVE amount
                        //
                        // Given an amount that INCLUDES tax, extract the base and tax portions
                        // using the CORRECT formula.
                        //
                        // When you have the INCLUSIVE (total) amount:
                        //   base = inclusive × (1 - taxRate%)
                        //   tax = inclusive × (taxRate%)
                        //
                        // This ensures: tax is exactly taxRate% of the paid amount
                        // ════════════════════════════════════════════════════════════════════
                        function extractBaseAndTaxFromInclusive(float $inclusiveAmount, float $taxRate): array
                        {
                            if ($taxRate < 0 || $inclusiveAmount < 0) {
                                return ['base' => $inclusiveAmount, 'tax' => 0.0, 'total' => $inclusiveAmount];
                            }
                            if ($inclusiveAmount == 0 || $taxRate == 0) {
                                return ['base' => round($inclusiveAmount, 2), 'tax' => 0.0, 'total' => round($inclusiveAmount, 2)];
                            }

                            // CORRECT FORMULA:
                            // base = inclusive × (1 - rate/100)
                            // tax = inclusive × (rate/100)
                            $taxRateDecimal = $taxRate / 100;
                            $basePortion = 1.0 - $taxRateDecimal;
                            $base = $inclusiveAmount * $basePortion;
                            $tax = $inclusiveAmount * $taxRateDecimal;

                            return [
                                'base' => round($base, 2),
                                'tax' => round($tax, 2),
                                'total' => round($inclusiveAmount, 2)
                            ];
                        }

                        // ════════════════════════════════════════════════════════════════════
                        // HELPER: Calculate tax-inclusive amount from a BASE amount
                        //
                        // Given a base amount, calculate the tax-inclusive total.
                        // 
                        // Formula:
                        //   inclusive = base / (1 - taxRate%)
                        //
                        // Example: base = 195 GBP, taxRate = 2.5%
                        //   inclusive = 195 / 0.975 = 200 GBP
                        //   tax = 200 × 0.025 = 5 GBP ✓
                        // ════════════════════════════════════════════════════════════════════
                        function calculateTaxInclusiveFromBase(float $baseAmount, float $taxRate): array
                        {
                            if ($taxRate < 0 || $baseAmount < 0) {
                                return ['base' => $baseAmount, 'tax' => 0.0, 'total' => $baseAmount];
                            }
                            if ($baseAmount == 0 || $taxRate == 0) {
                                return ['base' => round($baseAmount, 2), 'tax' => 0.0, 'total' => round($baseAmount, 2)];
                            }

                            // CORRECT FORMULA:
                            // basePortion = 1 - (rate/100)
                            // inclusive = base / basePortion
                            $taxRateDecimal = $taxRate / 100;
                            $basePortion = 1.0 - $taxRateDecimal;

                            if ($basePortion <= 0) {
                                return ['base' => round($baseAmount, 2), 'tax' => 0.0, 'total' => round($baseAmount, 2)];
                            }

                            $inclusive = $baseAmount / $basePortion;
                            $tax = $inclusive - $baseAmount;

                            return [
                                'base' => round($baseAmount, 2),
                                'tax' => round($tax, 2),
                                'total' => round($inclusive, 2)
                            ];
                        }

                        // ════════════════════════════════════════════════════════════════════
                        // HELPER: Resolve exchange rate for a receipt row
                        // ════════════════════════════════════════════════════════════════════
                        function resolveExchangeRate($row, $courseCurrencyId)
                        {
                            if (empty($row['currencies_currency_id']) || (int)$row['currencies_currency_id'] === (int)$courseCurrencyId) {
                                return null; // same currency — no conversion needed
                            }
                            if (!empty($row['currency_exchange_rate']) && (float)$row['currency_exchange_rate'] > 0) {
                                return (float)$row['currency_exchange_rate'];
                            }
                            if (!empty($row['currency_exchange_rate_id'])) {
                                $rateRow = Database::search(
                                    "SELECT `rate` FROM `currency_exchange_rates`
                                     WHERE `currency_exchange_rate_id` = '" . (int)$row['currency_exchange_rate_id'] . "'
                                     LIMIT 1"
                                );
                                if ($rateRow && $rateRow->num_rows > 0) {
                                    $r = (float)$rateRow->fetch_assoc()['rate'];
                                    if ($r > 0) return $r;
                                }
                            }
                            return null;
                        }

                        // ════════════════════════════════════════════════════════════════════
                        // HELPER: Convert paid-currency amount → course currency
                        //   paid in foreign → divide by exchange rate
                        //   same currency   → return as-is
                        // ════════════════════════════════════════════════════════════════════
                        function toCourseAmount(float $amount, $exchangeRate): float
                        {
                            if ($exchangeRate === null || $exchangeRate <= 0) return $amount;
                            return $amount / $exchangeRate;
                        }

                        $lkrCurrencyId = 1;

                        // ════════════════════════════════════════════════════════════════════
                        // STEP 2: Resolve course-level exchange rate (non-LKR courses)
                        // ════════════════════════════════════════════════════════════════════
                        $exchangeRate      = null;
                        $exchangeRateValue = 1.0;
                        $exchangeRateId    = null;

                        if ((int)$paymentData['course_currency_id'] !== $lkrCurrencyId) {
                            $rateResult = Database::search("
                                SELECT
                                    cer.`rate`,
                                    cer.`currency_exchange_rate_id`
                                FROM `currency_exchange_rates` cer
                                WHERE cer.`base_currency_id`   = '" . (int)$paymentData['course_currency_id'] . "'
                                  AND cer.`target_currency_id` = '" . $lkrCurrencyId . "'
                                ORDER BY cer.`effective_date` DESC, cer.`effective_time` DESC
                                LIMIT 1
                            ");
                            if ($rateResult && $rateResult->num_rows > 0) {
                                $rateRow           = $rateResult->fetch_assoc();
                                $exchangeRateValue = (float)$rateRow['rate'];
                                $exchangeRateId    = $rateRow['currency_exchange_rate_id'] ?? null;
                            }
                        }

                        // ════════════════════════════════════════════════════════════════════
                        // STEP 3: Installment-level discount (in course currency)
                        // ════════════════════════════════════════════════════════════════════
                        $cpDiscountAmount = ($paymentData['discount_type_discount_type_id'] == 3)
                            ? ($paymentData['amount'] / 100 * $paymentData['discount'])
                            : (float)$paymentData['discount'];

                        // ════════════════════════════════════════════════════════════════════
                        // STEP 4: Penalty from panelty_payments table
                        //   panelty_payments stores amounts in LKR → convert to course currency
                        // ════════════════════════════════════════════════════════════════════
                        $penaltyFromTableLKR = (float)($paymentData['panelty_amount'] ?? 0);

                        $mainPenaltyCP = ((int)$paymentData['course_currency_id'] !== $lkrCurrencyId && $exchangeRateValue > 0)
                            ? $penaltyFromTableLKR / $exchangeRateValue
                            : $penaltyFromTableLKR;

                        // ════════════════════════════════════════════════════════════════════
                        // STEP 5: Fetch existing receipts and accumulate running totals
                        //
                        // We collect from course_payment_has_payments:
                        //   - convertedPaidTotal : sum of paid_amount (in course currency)
                        //   - receiptDiscountCP  : sum of discount_amount (course currency)
                        //   - receiptPenaltyCP   : sum of panelty_amount  (course currency)
                        //   - receiptTaxCP       : sum of tax_amount      (course currency)
                        //
                        // Only status 2 (pending) and 3 (approved) receipts are counted.
                        // ════════════════════════════════════════════════════════════════════
                        $receiptsRs = Database::search("
                            SELECT
                                cpp.`course_payment_has_payment_id`,
                                cpp.`paid_amount`,
                                cpp.`discount_amount`,
                                cpp.`panelty_amount`,
                                cpp.`tax_amount`,
                                cpp.`tax_rate`,
                                cpp.`payment_status_payment_status_id`,
                                cpp.`currencies_currency_id`,
                                cpp.`currency_exchange_rate`,
                                cpp.`currency_exchange_rate_id`,
                                ps2.`payment_status_id`,
                                cur.`code` AS paid_currency_code
                            FROM `course_payment_has_payments` AS cpp
                            INNER JOIN `payment_status` AS ps2
                                ON cpp.`payment_status_payment_status_id` = ps2.`payment_status_id`
                            LEFT JOIN `currencies` AS cur
                                ON cpp.`currencies_currency_id` = cur.`currency_id`
                            WHERE cpp.`course_payments_course_payments_id` = '" . (int)$paymentData['course_payments_id'] . "'
                            AND ps2.`payment_status_id` IN (2, 3)
                            GROUP BY cpp.`course_payment_has_payment_id`
                        ");

                        $convertedPaidTotal = 0.0; // sum of all paid_amounts (course currency)
                        $receiptDiscountCP  = 0.0; // sum of receipt-level discounts (course currency)
                        $receiptPenaltyCP   = 0.0; // sum of receipt-level penalties (course currency)
                        $receiptTaxCP       = 0.0; // sum of receipt-level tax amounts (course currency)
                        $receiptRows        = [];

                        if ($receiptsRs && $receiptsRs->num_rows > 0) {
                            while ($rec = $receiptsRs->fetch_assoc()) {
                                $rate = resolveExchangeRate($rec, $paymentData['course_currency_id']);

                                // paid_amount is the total (base + tax); convert to course currency
                                $rec['converted_paid']         = toCourseAmount((float)$rec['paid_amount'], $rate);
                                $rec['is_foreign_currency']    = ($rate !== null);
                                $rec['resolved_exchange_rate'] = $rate;

                                $receiptRows[] = $rec;

                                // Only accumulate from pending (2) or approved (3) receipts
                                if (in_array((int)$rec['payment_status_id'], [2, 3])) {
                                    // paid total (accumulated amounts)
                                    $convertedPaidTotal += $rec['converted_paid'];

                                    // receipt-level discounts (in course currency)
                                    $receiptDiscountCP += toCourseAmount((float)($rec['discount_amount'] ?? 0), $rate);

                                    // receipt-level penalties (in course currency)
                                    $receiptPenaltyCP  += toCourseAmount((float)($rec['panelty_amount']  ?? 0), $rate);

                                    // receipt-level tax (in course currency)
                                    $receiptTaxCP      += toCourseAmount((float)($rec['tax_amount']      ?? 0), $rate);
                                }
                            }
                        }

                        // ════════════════════════════════════════════════════════════════════
                        // STEP 6: Calculate tax on THIS new receipt's amount
                        //
                        // The installment amount (cp.amount) is the BASE amount
                        // (what the student is scheduled to pay before tax).
                        //
                        // Using CORRECT tax model (tax as percentage of final amount):
                        //   inclusive = base / (1 - taxRate%)
                        //   tax = inclusive - base
                        //
                        // Example: cp.amount = 195 GBP, taxRate = 2.5%
                        //   inclusive = 195 / 0.975 = 200 GBP
                        //   tax = 200 - 195 = 5 GBP
                        //   Check: 5 ÷ 200 = 2.5% ✓
                        // ════════════════════════════════════════════════════════════════════
                        $baseAmountToStore = (float)$paymentData['amount'];  // BASE amount (scheduled)
                        $taxInfo           = calculateTaxInclusiveFromBase($baseAmountToStore, $taxRate);
                        $taxAmountToStore  = $taxInfo['tax'];                 // Calculated tax
                        $totalScheduled    = $taxInfo['total'];              // Total including tax

                        // ════════════════════════════════════════════════════════════════════
                        // STEP 7: Payable amount & balance (all in course currency)
                        //
                        // payableAmount = scheduled_base + scheduled_tax
                        //              - cpDiscountAmount (installment-level discount)
                        //              + mainPenaltyCP (from panelty_payments table)
                        //
                        // CRITICAL: Do NOT add back receiptTaxCP, receiptDiscountCP, or 
                        // receiptPenaltyCP as those are already included in the 
                        // convertedPaidTotal (the paid_amount column already includes tax).
                        //
                        // The payableAmount is FIXED for each installment.
                        // Prior payments are subtracted from it directly.
                        //
                        // This represents the TOTAL amount due for this installment
                        // including any applicable tax, discounts, and penalties.
                        //
                        // Example: 
                        //   scheduled_base = 195, scheduled_tax = 5, discount = 0, penalty = 0
                        //   payableAmount = 195 + 5 = 200 (FIXED)
                        //   
                        //   If prior payment = 100:
                        //   remaining = 200 - 100 = 100 (correct!)
                        //   NOT: 200 + prior_tax = wrong!
                        // ════════════════════════════════════════════════════════════════════
                        $payableAmount = $baseAmountToStore
                            + $taxAmountToStore
                            - $cpDiscountAmount
                            + $mainPenaltyCP;

                        // Total paid after this new receipt is added
                        $totalPaidWithNewAmount = $convertedPaidTotal + $baseAmountToStore + $taxAmountToStore;
                        $balance                = $payableAmount - $totalPaidWithNewAmount;

                        // LKR equivalents for display/logging purposes only
                        $balanceLKR    = $balance        * $exchangeRateValue;
                        $payableLKR    = $payableAmount  * $exchangeRateValue;
                        $paidTotalLKR  = $totalPaidWithNewAmount * $exchangeRateValue;

                        // ════════════════════════════════════════════════════════════════════
                        // STEP 8: Determine payment amounts for INSERT
                        //
                        // IF no prior payments:
                        //   Use the full installment base amount + calculated tax
                        //
                        // IF has prior payments:
                        //   Calculate remaining balance (which is a TOTAL: base + tax)
                        //   EXTRACT base and tax from this remaining balance
                        //   Using: base = remaining × (1 - taxRate%)
                        //          tax = remaining × (taxRate%)
                        //
                        // All amounts stored to 2 decimal places
                        //
                        // CRITICAL FIX:
                        // When $convertedPaidTotal > 0, the remainingBalance is already
                        // a TOTAL amount (base + tax). We must use extractBaseAndTaxFromInclusive()
                        // to extract base and tax properly.
                        // ════════════════════════════════════════════════════════════════════

                        $escapedNote = !empty($note) ? "'" . Database::escape($note) . "'" : "NULL";

                        // Determine if this is the first payment or a subsequent payment
                        if ($convertedPaidTotal == 0) {
                            // ────────────────────────────────────────────────────────────
                            // NO prior payments — use full installment amount
                            // ────────────────────────────────────────────────────────────
                            $payment_base_amount = $baseAmountToStore;
                            $paid_tax_amount = $taxAmountToStore;
                        } else {
                            // ────────────────────────────────────────────────────────────
                            // HAS prior payments — remainingBalance is TOTAL (base+tax)
                            // EXTRACT base and tax using CORRECT formula
                            //
                            // Example: Scheduled 200 (195 base + 5 tax), paid 100
                            //   Payable: 200
                            //   Remaining: 200 - 100 = 100
                            //   Base of remaining: 100 × 0.975 = 97.5
                            //   Tax of remaining: 100 × 0.025 = 2.5
                            //   Total: 97.5 + 2.5 = 100 ✓
                            // ────────────────────────────────────────────────────────────
                            $remainingBalance = $payableAmount - $convertedPaidTotal;
                            $balanceNegative = $remainingBalance < 0;
                            $absBalance = abs($remainingBalance);

                            // Use the CORRECT helper function to extract base from inclusive total
                            $balanceTaxInfo = extractBaseAndTaxFromInclusive($absBalance, $taxRate);

                            $payment_base_amount = $balanceTaxInfo['base'];
                            $paid_tax_amount = $balanceTaxInfo['tax'];
                        }

                        // Format amounts to 2 decimal places
                        $payment_base_amount = number_format($payment_base_amount, 2, '.', '');
                        $paid_tax_amount = number_format($paid_tax_amount, 2, '.', '');
                        $taxRate_formatted = number_format($taxRate, 2, '.', '');

                        // Total paid amount (base + tax)
                        $payment_total_amount = (float)$payment_base_amount + (float)$paid_tax_amount;
                        $payment_total_amount = number_format($payment_total_amount, 2, '.', '');

                        $insert_col = "
                            `course_payments_course_payments_id`,
                            `payment_method_payment_method_id`,
                            `date_paid`,
                            `paid_amount`,
                            `discount_amount`,
                            `payment_status_payment_status_id`,
                            `panelty_amount`,
                            `currencies_currency_id`,
                            `note`,
                            `tax_rate`,
                            `tax_amount`,
                            `date_added`";

                        $insert_val = "
                            '{$payment_id}',
                            '1',
                            '{$now}',
                            '{$payment_total_amount}',
                            '0.00',
                            '2',
                            '0.00',
                            '{$paymentData['course_currency_id']}',
                            {$escapedNote},
                            '{$taxRate_formatted}',
                            '{$paid_tax_amount}',
                            '{$now}'";

                        Database::iud("
                            INSERT INTO `course_payment_has_payments`
                            ({$insert_col})
                            VALUES ({$insert_val})
                        ");

                        $newReceiptId = Database::$connection->insert_id;

                        // ════════════════════════════════════════════════════════════════════
                        // STEP 9: Save uploaded slips
                        // ════════════════════════════════════════════════════════════════════
                        if (!empty($paymentSlips)) {
                            foreach ($_FILES["slips"]["name"] as $i => $slip_name) {
                                $slipHash  = hash_file('sha256', $_FILES["slips"]["tmp_name"][$i]);
                                $reference = strtoupper(substr($slipHash, 0, 10));
                                Database::iud("
                                    INSERT INTO `course_payment_slips`
                                        (`course_payment_has_payment_id`, `slip_name`, `reference_number`, `slip_hash`)
                                    VALUES
                                        ('{$newReceiptId}', '{$paymentSlips[$i]}', '{$reference}', '{$slipHash}')
                                ");
                                move_uploaded_file(
                                    $_FILES["slips"]["tmp_name"][$i],
                                    "../assets/uploads/payment-slips/" . $paymentSlips[$i]
                                );
                            }
                        }

                        // ════════════════════════════════════════════════════════════════════
                        // STEP 10: Payment log
                        // ════════════════════════════════════════════════════════════════════
                        $log = 'Upload Direct Payment Receipt';

                        $log_cols = "`course_payments_course_payments_id`,
                                     `course_payment_has_payment_id`,
                                     `users_user_id`,
                                     `date_updated`,
                                     `log`";

                        $log_vals = "'{$payment_id}',
                                     '{$newReceiptId}',
                                     '" . $sessionUserObject['user_id'] . "',
                                     '{$now}',
                                     '" . Database::escape($log) . "'";

                        if (!empty($note)) {
                            $log_cols .= ", `note`";
                            $log_vals .= ", '" . Database::escape($note) . "'";
                        }

                        Database::iud("INSERT INTO `payment_logs` ({$log_cols}) VALUES ({$log_vals})");

                        // ════════════════════════════════════════════════════════════════════
                        // STEP 11: First-installment student status log
                        // ════════════════════════════════════════════════════════════════════
                        if (in_array((int)$paymentData['lead_student_status_lead_student_status_id'], [5, 9])) {
                            $nextRs = Database::search("
                                SELECT `cp`.`course_payments_id`
                                FROM `course_payments` cp
                                INNER JOIN `payment_type` pt
                                    ON cp.`payment_type_payment_type_id` = pt.`payment_type_id`
                                INNER JOIN `payment_plan_has_students_has_intakes` pshi
                                    ON cp.`payment_plan_has_students_has_intakes_id` = pshi.`payment_plan_has_students_has_intakes_id`
                                WHERE cp.`payment_plan_has_students_has_intakes_id` = (
                                    SELECT `payment_plan_has_students_has_intakes_id`
                                    FROM `course_payments`
                                    WHERE `course_payments_id` = '{$payment_id}'
                                    LIMIT 1
                                )
                                AND pshi.`students_has_intakes_students_has_intakes_id` = '{$paymentData['student_id']}'
                                AND cp.`payment_status_payment_status_id` != '5'
                                AND pt.`payment_type` NOT LIKE '%Registration%'
                                ORDER BY cp.`due_date` ASC, cp.`course_payments_id` ASC
                                LIMIT 1
                            ");

                            if ($nextRs && $nextRs->num_rows > 0) {
                                $next = $nextRs->fetch_assoc();
                                if ((int)$next['course_payments_id'] === $payment_id) {
                                    Database::iud("
                                        INSERT INTO `students_logs`
                                            (`students_student_id`, `date_updated`, `users_user_id`, `log`, `lead_student_status_lead_student_status_id`)
                                        VALUES
                                            ('{$paymentData['student_id']}', '{$now}', '" . $sessionUserObject['user_id'] . "',
                                             'First installment payment receipt uploaded', '8')
                                    ");
                                }
                            }
                        }

                        http_response_code(200);
                        $status->done = 'Payment receipt uploaded successfully';
                    }
                }
            }
        } else {
            http_response_code(400);
            $status->error = 'Invalid request method';
        }
    } else {
        $status->error = 'Authentication Failed!';
    }
} else {
    $status->error = 'Authentication Failed!';
}

echo json_encode($status);
exit();
