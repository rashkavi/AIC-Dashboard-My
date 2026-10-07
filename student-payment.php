<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once './includes/UserClass.php';
require_once './includes/UserPermissionsClass.php';
require_once './includes/SlipVerifier.php';
require_once "./includes/connection.php";
require_once "./includes/Project.php";

if (!SessionUser::hasValidSession()) {
    header("Location: ./index");
} else {
    $sessionUserRole = SessionUser::getCurrentUserRole();
    if ($sessionUserRole == null) {
        header("Location: ./index");
    } else {
        $sessionUserObject = SessionUser::getCurrentUserObject();
        if (!($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['payment-id']) && !empty($_GET['payment-id']))) {
            header("Location: ./payments");
        } else {
            UserPermissions::setUserPermissions($sessionUserObject['user_permissions']);

            if (!(SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::validPermisisonType('payments'))) {
                header("Location: ./dashboard");
            } else {
                $id = $_GET['payment-id'];

                $payment = Database::search("
                    SELECT * FROM `course_payment_has_payments`
                    INNER JOIN `payment_method` pm ON `course_payment_has_payments`.`payment_method_payment_method_id` = pm.payment_method_id
                    INNER JOIN `currencies` c ON `course_payment_has_payments`.`currencies_currency_id` = c.currency_id
                    LEFT JOIN `course_payment_receipts` ON `course_payment_has_payments`.`course_payment_receipts_course_payment_receipt_id` = `course_payment_receipts`.`course_payment_receipt_id`
                    WHERE `course_payment_has_payment_id` = '" . $id . "'
                ");

                if ($payment->num_rows == 0) {
                    header("Location: ./payments");
                } else {
                    $coursepaymentData = $payment->fetch_assoc();

                    $paymentPlan = Database::search("
                        SELECT
                            cp.course_payments_id,
                            ps.payment_status,
                            ps.payment_status_id,
                            pgp.payment_type,
                            pgp.payment_type_id,
                            cp.amount,
                            cp.discount,
                            cp.discount_note,
                            cp.panelty_payment_option_panelty_payment_option_id,
                            dt.discount_type,
                            dt.discount_type_id,
                            cp.due_date,
                            cp.refference,
                            i.intake_id,
                            p.programme_id,
                            f.faculty_id,
                            i.intake,
                            p.programme,
                            f.faculty,
                            t.title,
                            ld.first_name,
                            ld.last_name,
                            ld.email,
                            s.mobile,
                            s.student_id,
                            ppo.panelty_payment_option,
                            c.code         AS currency_code,
                            c.decimal_places,
                            c.currency_id,
                            IFNULL(pp.panelty_amount_lkr, 0) AS panelty_amount_lkr,
                            shi.status_status_id,
                            ld.`status_status_id` AS 'status_status_id_login_details'
                        FROM course_payments cp
                        INNER JOIN payment_status ps
                            ON cp.payment_status_payment_status_id = ps.payment_status_id
                        INNER JOIN panelty_payment_option ppo
                            ON cp.panelty_payment_option_panelty_payment_option_id = ppo.panelty_payment_option_id
                        INNER JOIN discount_type dt
                            ON cp.discount_type_discount_type_id = dt.discount_type_id
                        INNER JOIN payment_type pgp
                            ON cp.payment_type_payment_type_id = pgp.payment_type_id
                        INNER JOIN payment_plan_has_students_has_intakes phsi
                            ON cp.payment_plan_has_students_has_intakes_id = phsi.payment_plan_has_students_has_intakes_id
                        INNER JOIN payment_category pc
                            ON phsi.payment_category_payment_category_id = pc.payment_category_id
                        INNER JOIN currencies c
                            ON pc.currencies_currency_id = c.currency_id
                        INNER JOIN students_has_intakes shi
                            ON phsi.students_has_intakes_students_has_intakes_id = shi.students_has_intakes_id
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
                        INNER JOIN faculties f
                            ON p.faculties_faculty_id = f.faculty_id
                        LEFT JOIN (
                            SELECT course_payments_course_payments_id,
                            SUM(amount) AS panelty_amount_lkr
                            FROM panelty_payments
                            GROUP BY course_payments_course_payments_id
                        ) pp ON cp.course_payments_id = pp.course_payments_course_payments_id
                        WHERE cp.course_payments_id = '" . $coursepaymentData['course_payments_course_payments_id'] . "'
                    ");

                    if ($paymentPlan->num_rows == 0) {
                        header("Location: ./payments");
                    } else {
                        $paymentData = $paymentPlan->fetch_assoc();

                        // ════════════════════════════════════════════════════════════════════════
                        // FETCH LATEST TAX RATE
                        //
                        // FIX: aligned with add-receipt.php — no `tax_rate > 0` filter and no
                        // `tax_rate IS NOT NULL` filter. The previous version of this query on
                        // this page filtered those rows out, which meant this page and
                        // add-receipt.php could resolve a DIFFERENT "latest" tax rate row if a
                        // newer `tax_rates` entry ever had a 0 value, or if NULLs were present.
                        // Both pages must resolve identically or their payable/balance figures
                        // will silently diverge. Fallback also aligned to 0 (matches
                        // add-receipt.php) instead of a hardcoded 2.50 default.
                        // ════════════════════════════════════════════════════════════════════════
                        $taxRateQuery = Database::search("
                            SELECT `tax_rate` AS `rate` FROM `tax_rates`
                            ORDER BY `date_added` DESC LIMIT 1
                        ");
                        $latestTaxRate = 0; // DEFAULT fallback — matches add-receipt.php
                        if ($taxRateQuery && $taxRateQuery->num_rows > 0) {
                            $taxRateRow    = $taxRateQuery->fetch_assoc();
                            $latestTaxRate = (float)$taxRateRow['rate'];
                        }

                        // ── Currency helpers ─────────────────────────────────────────────────
                        function resolveExchangeRate($row, $courseCurrencyId)
                        {
                            if (empty($row['currencies_currency_id']) || $row['currencies_currency_id'] == $courseCurrencyId) {
                                return null;
                            }
                            if (!empty($row['currency_exchange_rate']) && $row['currency_exchange_rate'] > 0) {
                                return (float)$row['currency_exchange_rate'];
                            }
                            if (!empty($row['currency_exchange_rate_id'])) {
                                $rateRow = Database::search(
                                    "SELECT `rate` AS `currency_exchange_rate` FROM `currency_exchange_rates`
                                     WHERE `currency_exchange_rate_id` = '" . (int)$row['currency_exchange_rate_id'] . "' LIMIT 1"
                                );
                                if ($rateRow && $rateRow->num_rows > 0) {
                                    $r = $rateRow->fetch_assoc();
                                    if (!empty($r['currency_exchange_rate']) && $r['currency_exchange_rate'] > 0) {
                                        return (float)$r['currency_exchange_rate'];
                                    }
                                }
                            }
                            return null;
                        }

                        function convertToCourseAmount($paidAmount, $exchangeRate)
                        {
                            if ($exchangeRate === null || $exchangeRate <= 0) return (float)$paidAmount;
                            return (float)$paidAmount / $exchangeRate;
                        }

                        $lkrCurrencyId     = 1;
                        $isBaseCurrencyLKR = ($paymentData['currency_id'] == $lkrCurrencyId);

                        // ── CP-level discount (course currency) ──────────────────────────────
                        $discountAmountCP = ($paymentData['discount_type_id'] == 3)
                            ? $paymentData['amount'] / 100 * $paymentData['discount']
                            : (float)$paymentData['discount'];

                        // ── Exchange rate for display ─────────────────────────────────────────
                        $exchangeRate      = null;
                        $exchangeRateValue = 1;
                        $exchangeRateId    = 0;

                        if (!$isBaseCurrencyLKR) {
                            $rateResult = Database::search("
                                SELECT
                                    cer.rate,
                                    cer.effective_date,
                                    cer.effective_time,
                                    c_base.code   AS base_code,
                                    c_base.symbol AS base_symbol,
                                    c_target.code AS target_code,
                                    cer.currency_exchange_rate_id
                                FROM currency_exchange_rates cer
                                INNER JOIN currencies c_base   ON cer.base_currency_id   = c_base.currency_id
                                INNER JOIN currencies c_target ON cer.target_currency_id = c_target.currency_id
                                WHERE cer.base_currency_id   = '" . $paymentData['currency_id'] . "'
                                  AND cer.target_currency_id = '" . $lkrCurrencyId . "'
                                ORDER BY cer.effective_date DESC, cer.effective_time DESC
                                LIMIT 1
                            ");
                            if ($rateResult->num_rows > 0) {
                                $exchangeRate      = $rateResult->fetch_assoc();
                                $exchangeRateValue = (float)$exchangeRate['rate'];
                                $exchangeRateId    = $exchangeRate['currency_exchange_rate_id'] ?? null;
                            }
                        }

                        // ════════════════════════════════════════════════════════════════════════
                        // PENALTY CONVERSION HELPERS
                        //
                        // panelty_payments.amount        => ALWAYS stored in LKR
                        // course_payment_has_payments.panelty_amount => ALWAYS stored in LKR
                        //
                        // To use in balance (course currency):
                        //   if course IS LKR  → use LKR value directly
                        //   if course is GBP  → divide by exchangeRateValue (LKR per 1 GBP)
                        //                       i.e. LKR 5000 / 320 = GBP 15.625
                        // ════════════════════════════════════════════════════════════════════════
                        function convertLKRToCourseCurrency($lkrAmount, $isLKRCourse, $exchangeRateValue)
                        {
                            if ($isLKRCourse || $exchangeRateValue <= 0) {
                                return (float)$lkrAmount;
                            }
                            return (float)$lkrAmount / $exchangeRateValue;
                        }

                        // ── Fetch all receipts ────────────────────────────────────────────────
                        $receipts = Database::search("
                            SELECT
                                cpp.*,
                                pm.payment_method,
                                pm.payment_method_id,
                                ps2.payment_status,
                                ps2.payment_status_id,
                                cur.code           AS paid_currency_code,
                                cur.decimal_places AS paid_decimal_places,
                                cpp.currency_exchange_rate,
                                cpp.tax_rate,
                                cpp.tax_amount
                            FROM `course_payment_has_payments` AS cpp
                            INNER JOIN `payment_method` AS pm  ON cpp.`payment_method_payment_method_id` = pm.`payment_method_id`
                            INNER JOIN `payment_status` AS ps2 ON cpp.`payment_status_payment_status_id` = ps2.`payment_status_id`
                            LEFT  JOIN `currencies`     AS cur ON cpp.`currencies_currency_id`           = cur.`currency_id`
                            WHERE cpp.`course_payments_course_payments_id` = '" . (int)$paymentData['course_payments_id'] . "'
                            AND ps2.`payment_status_id` = 3
                            GROUP BY cpp.`course_payment_has_payment_id`
                        ");

                        // ════════════════════════════════════════════════════════════════════════
                        // ACCUMULATE PAID TOTALS
                        //
                        //   paid_amount       → stored in the receipt's own currency,
                        //                       converted to course currency via resolveExchangeRate
                        //
                        //   discount_amount   → stored in receipt's currency → convert same way
                        //
                        //   panelty_amount    → ALWAYS LKR → convert to course currency via
                        //                       convertLKRToCourseCurrency()
                        //
                        //   tax_amount        → stored in receipt's currency → convert same way
                        //
                        //   paid_lkr          → NEW: this receipt's paid amount expressed in LKR,
                        //                       at full precision (no intermediate course-currency
                        //                       rounding). This is the ground-truth figure used for
                        //                       overpayment detection below — see FIX note further
                        //                       down. Mirrors the payment-currency-first approach
                        //                       already used in uploadStudentPaymentReceipt.php.
                        // ════════════════════════════════════════════════════════════════════════
                        $convertedPaidTotal    = 0;
                        $convertedPaidTotalLKR = 0; // NEW — paid total in LKR, full precision, single rounding point at the end
                        $receiptRows           = [];
                        $totalReceiptDisc      = 0;
                        $totalReceiptPenInCC   = 0; // penalty in course currency
                        $totalReceiptPenLKR    = 0; // penalty always in LKR (for display)
                        $totalReceiptTax       = 0;

                        if ($receipts && $receipts->num_rows > 0) {
                            while ($rec = $receipts->fetch_assoc()) {
                                // Exchange rate for paid_amount / discount / tax conversions
                                $rate = resolveExchangeRate($rec, $paymentData['currency_id']);

                                $rec['converted_paid']         = convertToCourseAmount((float)$rec['paid_amount'], $rate);
                                $rec['is_foreign_currency']    = ($rate !== null);
                                $rec['currency_exchange_rate'] = $rate;

                                $convertedPaidTotal += $rec['converted_paid'];

                                // ════════════════════════════════════════════════════════════════
                                // NEW: LKR-precise paid amount for THIS receipt
                                //
                                // Assumption (same as the rest of this file's penalty logic):
                                // every receipt is paid either directly in LKR, or directly in the
                                // course currency — never a third currency. If that assumption
                                // ever changes, this needs the receipt's own currency->LKR rate.
                                // ════════════════════════════════════════════════════════════════
                                if (!empty($rec['currencies_currency_id']) && $rec['currencies_currency_id'] == $lkrCurrencyId) {
                                    // Paid directly in LKR — use as-is, no conversion.
                                    $rec['paid_lkr'] = (float)$rec['paid_amount'];
                                } elseif (!$isBaseCurrencyLKR && $exchangeRateValue > 0) {
                                    // Paid in the (foreign) course currency — convert up to LKR.
                                    $rec['paid_lkr'] = (float)$rec['paid_amount'] * $exchangeRateValue;
                                } else {
                                    // Course IS LKR — course-currency amount already IS the LKR amount.
                                    $rec['paid_lkr'] = (float)$rec['paid_amount'];
                                }
                                $convertedPaidTotalLKR += $rec['paid_lkr'];

                                $receiptRows[] = $rec;

                                if ($rec['payment_status_id'] == 2 || $rec['payment_status_id'] == 3) {
                                    $totalReceiptDisc += convertToCourseAmount((float)($rec['discount_amount'] ?? 0), $rate);

                                    // ── Penalty: stored in LKR, convert to course currency ────────
                                    $recPenLKR = (float)($rec['panelty_amount'] ?? 0);
                                    $totalReceiptPenLKR  += $recPenLKR;
                                    $totalReceiptPenInCC += convertLKRToCourseCurrency($recPenLKR, $isBaseCurrencyLKR, $exchangeRateValue);

                                    $totalReceiptTax += convertToCourseAmount((float)($rec['tax_amount'] ?? 0), $rate);
                                }
                            }
                        }

                        // ── CP-level penalty: panelty_payments table — ALWAYS in LKR ──────────
                        $cpPenaltyLKR  = (float)$paymentData['panelty_amount_lkr'];
                        $cpPenaltyInCC = convertLKRToCourseCurrency($cpPenaltyLKR, $isBaseCurrencyLKR, $exchangeRateValue);

                        // ── Aggregate totals (all in course currency) ─────────────────────────
                        $totalDiscount   = $discountAmountCP + $totalReceiptDisc;
                        $totalPenaltyCC  = $cpPenaltyInCC   + $totalReceiptPenInCC;  // course currency
                        $totalPenaltyLKR = $cpPenaltyLKR    + $totalReceiptPenLKR;   // LKR (for display)
                        $totalTax        = $totalReceiptTax;

                        // ════════════════════════════════════════════════════════════════════════
                        // PAYABLE / BALANCE CALCULATION  (all in course currency)
                        //
                        //   netBase       = amount − totalDiscount + totalPenaltyCC
                        //
                        //   FIX (overpayment bug): payableAmount MUST be a fixed, tax-inclusive
                        //   target derived only from netBase and the tax rate — it must NOT
                        //   depend on how much tax happens to have been extracted from receipts
                        //   paid so far. The previous version used:
                        //       payableAmount = netBase + totalTax   (totalTax = sum of tax
                        //       extracted from whatever was actually paid)
                        //   which made "Total Payable" / "Current Installment Payable" move
                        //   every time a paid amount was typed, corrupting the overpayment
                        //   calculation. This mirrors the correct gross-up formula already used
                        //   in add-receipt.php:
                        //       payableAmount = netBase / (1 - taxRate/100)
                        //
                        //   balance       = payableAmount − convertedPaidTotal
                        // ════════════════════════════════════════════════════════════════════════
                        $netBase        = max(0, $paymentData['amount'] - $totalDiscount + $totalPenaltyCC);

                        $taxRateDecimal = $latestTaxRate / 100;
                        $basePortion    = 1 - $taxRateDecimal;
                        $payableAmount  = $basePortion > 0 ? round($netBase / $basePortion, 2) : $netBase;

                        // $taxOnPayable: tax portion of the fixed payable target (for display),
                        // NOT the sum of tax extracted from receipts paid so far.
                        $taxOnPayable   = round($payableAmount - $netBase, 2);

                        $paidAmount    = $convertedPaidTotal;

                        // ════════════════════════════════════════════════════════════════════════
                        // LKR-PRECISE OVERPAYMENT CHECK (FIX)
                        //
                        // PROBLEM: $payableAmount above is rounded to 2dp in COURSE currency
                        // (e.g. EUR 307.69) before ever being compared against what was paid.
                        // When that rounded EUR figure is later multiplied back up by the
                        // exchange rate for LKR display, the EUR-level rounding (up to roughly
                        // ±0.005 EUR, i.e. a few LKR at typical rates) compounds on top of any
                        // genuine LKR-level overpayment/shortfall — a real LKR 0.91 can get
                        // buried inside that noise, shown as 0.00, or shown as the wrong value.
                        //
                        // FIX: recompute the payable target and the balance in LKR — the
                        // currency the student actually paid in — at full float precision,
                        // and round ONLY ONCE, at the very end. The course-currency ($payableAmount,
                        // $balance) versions are used for on-screen course-currency figures as
                        // before, but the OVERPAYMENT DECISION and the overpayment LKR amount
                        // are always taken from this LKR-precise figure, never re-derived by
                        // multiplying an already-rounded course-currency number.
                        //
                        // Same fix already applied to $overpayAmount_paymentCcy in
                        // uploadStudentPaymentReceipt.php (the "payment-currency-first,
                        // single-rounding-point" pattern).
                        // ════════════════════════════════════════════════════════════════════════
                        // ════════════════════════════════════════════════════════════════
                        // FIX #2 (payable-target mismatch):
                        //
                        // The PREVIOUS version of this fix computed the LKR payable target
                        // via a SEPARATE path: netBase(GBP) -> ×rate -> ÷basePortion. That
                        // path rounds in a different ORDER than $payableAmount above (which
                        // is round(netBase/basePortion) in GBP, THEN later ×rate for LKR
                        // display everywhere else on this page — LKR summary panel, receipt
                        // card, "Total Payable Amount" line, etc). The two paths land on
                        // different pennies (in this example: LKR 120,880.00 vs the
                        // page-wide LKR 120,879.09), which silently cancelled out a real
                        // LKR 0.91 overpayment instead of surfacing it.
                        //
                        // FIX: the LKR-precise target MUST be the exact same figure already
                        // shown everywhere else — $payableAmount (course currency, rounded
                        // once) × exchange rate — never an independently re-derived LKR
                        // number. Only $convertedPaidTotalLKR (the SUM actually paid) stays
                        // unrounded/precise; the TARGET stays anchored to the single
                        // canonical $payableAmount used by the rest of the page.
                        // ════════════════════════════════════════════════════════════════
                        $payableAmountLKRPrecise = round($payableAmount * $exchangeRateValue, 2);
                        $balanceLKRPrecise       = round($payableAmountLKRPrecise - $convertedPaidTotalLKR, 2);

                        // Course-currency balance — used for the non-overpay display branch.
                        $balance = $payableAmount - $convertedPaidTotal;

                        // ── Overpayment (next installment credit) ─────────────────────────────
                        //
                        // FIX (display bug): this block already correctly detects and computes
                        // the overpayment (same math as add-receipt.php), but $nextInstallmentData
                        // was never rendered anywhere on this page — the overpay-panel CSS classes
                        // only existed inside the "Update Payment Status" modal, which doesn't even
                        // render for already-accepted (status 3) receipts. The values were computed
                        // and silently discarded. A static overpayment panel is now rendered on the
                        // main page below — see "OVERPAYMENT SUMMARY PANEL" further down.
                        //
                        // FIX (LKR precision): the detection condition and the overpay amount now
                        // come from $balanceLKRPrecise / the LKR-precise total, not from the
                        // course-currency $balance — see note above.
                        // ────────────────────────────────────────────────────────────────────────
                        $nextInstallmentData = null;
                        if ($balanceLKRPrecise < 0) {
                            // Primary, precise figure — LKR, single rounding point.
                            $overpayAmountLKR = abs($balanceLKRPrecise);

                            // Course-currency equivalent DERIVED from the LKR figure (never
                            // independently rounded), so the two numbers always agree with
                            // each other and with what was actually paid.
                            $overpayAmount = $isBaseCurrencyLKR
                                ? $overpayAmountLKR
                                : $overpayAmountLKR / $exchangeRateValue;

                            $taxRateDecimalOv = $latestTaxRate / 100;
                            $basePortionOv    = 1 - $taxRateDecimalOv;
                            $nextBase       = round($overpayAmount * $basePortionOv, 2);
                            $nextTax        = round($overpayAmount * $taxRateDecimalOv, 2);
                            $nextInstallmentData = [
                                'overpay'    => $overpayAmount,     // course currency
                                'overpayLKR' => $overpayAmountLKR,  // NEW — precise, ground-truth LKR figure
                                'base'       => $nextBase,
                                'tax'        => $nextTax,
                            ];
                            $balance = 0;
                        } else {
                            $balance = max(0, $balance);
                        }

                        // ── LKR equivalents for display ───────────────────────────────────────
                        // For non-LKR courses: multiply course-currency values by exchange rate
                        // For penalty: we already have the LKR value directly
                        $balanceLKR     = $isBaseCurrencyLKR ? $balance       : $balance       * $exchangeRateValue;
                        $payableAmtLKR  = $isBaseCurrencyLKR ? $payableAmount : $payableAmount * $exchangeRateValue;
                        $paidAmountLKR  = $isBaseCurrencyLKR ? $paidAmount    : $paidAmount    * $exchangeRateValue;
                        $discountAmtLKR = $isBaseCurrencyLKR ? $totalDiscount : $totalDiscount * $exchangeRateValue;
                        $penaltyAmtLKR  = $totalPenaltyLKR;  // already in LKR
                        $penaltyAmtCC   = $totalPenaltyCC;   // course currency
                        $taxAmtLKR      = $isBaseCurrencyLKR ? $taxOnPayable  : $taxOnPayable  * $exchangeRateValue;

                        $currencyCode = htmlspecialchars($paymentData['currency_code']);

                        // ── This receipt's details ────────────────────────────────────────────
                        $receiptRate         = resolveExchangeRate($coursepaymentData, $paymentData['currency_id']);
                        $receiptConvertedAmt = convertToCourseAmount($coursepaymentData['paid_amount'], $receiptRate);
                        $receiptIsForeign    = ($receiptRate !== null);

                        $receiptTaxRate    = (float)($coursepaymentData['tax_rate']   ?? $latestTaxRate);
                        $receiptPaidAmt    = (float)$coursepaymentData['paid_amount'];
                        $receiptTaxAmount  = (float)($coursepaymentData['tax_amount'] ?? 0);
                        $receiptBaseAmount = $receiptPaidAmt - $receiptTaxAmount;
                        $receiptTaxConverted = convertToCourseAmount($receiptTaxAmount, $receiptRate);

                        // ── This receipt's penalty (LKR) → for display ───────────────────────
                        $receiptPenaltyLKR = (float)($coursepaymentData['panelty_amount'] ?? 0);
                        $receiptPenaltyCC  = convertLKRToCourseCurrency($receiptPenaltyLKR, $isBaseCurrencyLKR, $exchangeRateValue);

                        // ── JS constants for modal (this receipt's stored values) ─────────────
                        // NOTE: paid/discount/tax are in their receipt currency.
                        //       penalty is in LKR → pass LKR to JS, convert on JS side.
                        $jsCurrentReceiptPaid         = (float)$coursepaymentData['paid_amount'];
                        $jsCurrentReceiptDiscount     = (float)($coursepaymentData['discount_amount'] ?? 0);
                        $jsCurrentReceiptPenaltyLKR   = $receiptPenaltyLKR;   // LKR
                        $jsCurrentReceiptPenaltyCC    = $receiptPenaltyCC;    // course currency
                        $jsCurrentReceiptTax          = (float)($coursepaymentData['tax_amount'] ?? 0);

                        function formatDateSafe($date, $outputFormat = 'Y-m-d')
                        {
                            if (empty($date)) return null;
                            try {
                                $d = new DateTime($date);
                                return $d->format($outputFormat);
                            } catch (Exception $e) {
                                $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d', 'd.m.Y', 'm/d/Y', 'm-d-Y'];
                                foreach ($formats as $format) {
                                    $d = DateTime::createFromFormat($format, $date);
                                    if ($d !== false) return $d->format($outputFormat);
                                }
                            }
                            return null;
                        }
?>
                        <!DOCTYPE html>
                        <html lang="en">

                        <head>
                            <meta charset="UTF-8">
                            <meta name="viewport" content="width=device-width, initial-scale=1.0">
                            <meta name="robots" content="noindex, nofollow">
                            <title>Student Payment Details - Admin Dashboard | <?= Project::getBaseName() ?></title>
                            <link rel="stylesheet" href="./assets/css/style.css?v=<?= Project::getVersion() ?>">
                            <link rel="stylesheet" href="./assets/css/skeleton.css?v=<?= Project::getVersion() ?>">
                            <link rel="stylesheet" href="./assets/plugins/bootstrap/css/bootstrap.min.css?v=<?= Project::getVersion() ?>">
                            <link href="./assets/plugins/cropperjs/cropper.min.css" rel="stylesheet">
                            <script src="./assets/plugins/cropperjs/cropper.min.js"></script>
                            <script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js"></script>
                            <script src="./assets/js/init.js?v=<?= Project::getVersion() ?>"></script>
                            <script src="./assets/js/translations.js?= Project::getVersion() ?>"></script>
                            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
                            <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
                        </head>

                        <style>
                            .overpay-panel {
                                border-left: 4px solid #3b82f6;
                                background: rgba(59, 130, 246, 0.07);
                                border-radius: 0 8px 8px 0;
                                padding: 14px 18px;
                                margin: 12px 0 0;
                            }

                            .overpay-panel-title {
                                font-size: 12px;
                                font-weight: 700;
                                color: #2563eb;
                                text-transform: uppercase;
                                letter-spacing: .06em;
                                margin-bottom: 10px;
                                display: flex;
                                align-items: center;
                                gap: 6px;
                            }

                            .overpay-grid {
                                display: grid;
                                grid-template-columns: repeat(3, minmax(0, 1fr));
                                gap: 10px;
                            }

                            .overpay-item {
                                display: flex;
                                flex-direction: column;
                                gap: 2px;
                            }

                            .overpay-label {
                                font-size: 11px;
                                color: var(--sub-text-color);
                                text-transform: uppercase;
                                letter-spacing: .04em;
                            }

                            .overpay-value {
                                font-size: 15px;
                                font-weight: 600;
                                color: #2563eb;
                            }

                            .overpay-sub {
                                font-size: 11px;
                                color: var(--sub-text-color);
                            }

                            .currency-toggle {
                                display: flex;
                                border: 0.5px solid var(--border-color);
                                border-radius: 8px;
                                overflow: hidden;
                            }

                            .currency-toggle-btn {
                                padding: 5px 14px;
                                font-size: 12px;
                                font-weight: 500;
                                border: none;
                                background: transparent;
                                color: var(--sub-text-color);
                                cursor: pointer;
                                transition: background 0.15s, color 0.15s;
                            }

                            .currency-toggle-btn.active {
                                background: var(--dark-theme);
                                color: #fff;
                            }

                            .exchange-rate-badge {
                                display: inline-flex;
                                align-items: center;
                                gap: 5px;
                                padding: 4px 10px;
                                border-radius: 20px;
                                background: var(--color-background-secondary, #f0f4f8);
                                border: 1px solid var(--border-color);
                                font-size: 11.5px;
                                color: var(--sub-text-color);
                            }

                            .rate-unavailable {
                                border-color: #f5c6cb;
                                background: #fff5f5;
                                color: #a32d2d;
                            }

                            .currency-label-badge {
                                display: inline-block;
                                margin-left: 6px;
                                padding: 1px 7px;
                                border-radius: 10px;
                                font-size: 10px;
                                font-weight: 600;
                                background: var(--light-theme, #eef2f7);
                                color: var(--sub-text-color);
                                vertical-align: middle;
                                border: 1px solid var(--border-color);
                            }

                            .tax-badge {
                                background: rgba(245, 158, 11, .12);
                                color: #d97706;
                                border-color: rgba(245, 158, 11, .3);
                            }

                            .penalty-lkr-badge {
                                display: inline-block;
                                margin-left: 6px;
                                padding: 1px 7px;
                                border-radius: 10px;
                                font-size: 10px;
                                font-weight: 600;
                                background: rgba(239, 68, 68, .1);
                                color: #dc2626;
                                border: 1px solid rgba(239, 68, 68, .3);
                                vertical-align: middle;
                            }

                            .convert-hint {
                                font-size: 11.5px;
                                color: var(--sub-text-color);
                                margin-top: 3px;
                                opacity: 0.8;
                            }

                            .detail-lkr-hint {
                                font-size: 11px;
                                color: var(--sub-text-color);
                                opacity: 0.75;
                                margin-top: 2px;
                            }

                            .penalty-stored-note {
                                font-size: 10.5px;
                                color: #dc2626;
                                opacity: 0.85;
                                margin-top: 2px;
                                display: flex;
                                align-items: center;
                                gap: 3px;
                            }

                            .tax-readonly {
                                background: rgba(245, 158, 11, .06) !important;
                                border-color: rgba(245, 158, 11, .3) !important;
                                color: #d97706 !important;
                                cursor: not-allowed;
                            }

                            .penalty-lkr-input {
                                background: rgba(239, 68, 68, .04) !important;
                                border-color: rgba(239, 68, 68, .3) !important;
                            }

                            @media (max-width: 768px) {
                                .lkr-panel-grid {
                                    grid-template-columns: repeat(3, minmax(0, 1fr));
                                }

                                .lkr-panel-item:nth-child(3) {
                                    border-right: none;
                                }

                                .lkr-panel-item {
                                    border-bottom: 0.5px solid rgba(255, 255, 255, 0.1);
                                }

                                .overpay-grid {
                                    grid-template-columns: repeat(2, minmax(0, 1fr));
                                }
                            }
                        </style>

                        <body class="overflow-hidden">
                            <div class="notifications"></div>
                            <div class="col-12 vh-100 container-fluid overflow-hidden content-container">
                                <div id="appContainer" class="row p-0">
                                    <div id="sideMenu" class="d-none d-xl-block d-flex flex-column sideMenuArea p-0 vh-100">
                                        <?php include "includes/side-menu.php";
                                        load_side_menu::load("Payments"); ?>
                                    </div>
                                    <div id="app-wrapper" class="app-wrapper p-0 m-0">
                                        <div class="row p-0 m-0 g-0">
                                            <div class="col-12 p-0 m-0 g-0">
                                                <?php include "./includes/header.php";
                                                selectItem::setSelected("Payments"); ?>
                                            </div>

                                            <div class="col-12 d-flex flex-column p-0 m-0 g-0 contentBodyArea vh-100">
                                                <div class="row p-4 m-0 g-0">

                                                    <div class="col-12 px-4 py-3 component-theme mb-4">
                                                        <div class="row p-0 m-0 g-0">
                                                            <div class="col-12 col-md-10">
                                                                <div class="row">
                                                                    <span class="main-topic lh-lg" data-i18n="payment-details">Payment Details</span>
                                                                    <nav style="--bs-breadcrumb-divider: url(&#34;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8'%3E%3Cpath d='M2.5 0L1 1.5 3.5 4 1 6.5 2.5 8l4-4-4-4z' fill='%236c757d'/%3E%3C/svg%3E&#34;);" aria-label="breadcrumb">
                                                                        <ol class="breadcrumb m-0">
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='payments'>Payments</a></li>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./students-payments'>Student Payments</a></li>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none cursor-pointer" href='./students-scheduled-payments'>Students Scheduled Payments</a></li>
                                                                            <li class="breadcrumb-item"><a class="sub-text text-decoration-none"><?= $paymentData['title'] . "." . $paymentData['first_name'] . " " . $paymentData['last_name'] . " - #" . $paymentData['course_payments_id'] ?></a></li>
                                                                        </ol>
                                                                    </nav>
                                                                </div>
                                                            </div>
                                                            <?php
                                                            $receiptCheck = Database::search("
                                                                SELECT cpr.receipt_number, cpr.course_payment_receipt_id
                                                                FROM course_payment_has_payments cpp
                                                                INNER JOIN course_payment_receipts cpr
                                                                    ON cpp.course_payment_receipts_course_payment_receipt_id = cpr.course_payment_receipt_id
                                                                WHERE cpp.course_payment_has_payment_id = '" . $coursepaymentData['course_payment_has_payment_id'] . "'
                                                                
                                                                LIMIT 1
                                                            ");
                                                            if ($receiptCheck && $receiptCheck->num_rows > 0):
                                                                $receiptInfo = $receiptCheck->fetch_assoc();
                                                            ?>
                                                                <div class="col-12 col-md-2 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="custom-btn w-100" type="button"
                                                                        onclick="openReceiptModal(<?= $receiptInfo['course_payment_receipt_id'] ?>)">
                                                                        View Receipt &amp; Send
                                                                    </button>
                                                                </div>
                                                            <?php endif; ?>
                                                            <?php
                                                            if ((SessionUser::ifUserIsPro($sessionUserRole) || SessionUser::ifUserIsAccounts($sessionUserRole) || UserPermissions::validPermisisonType('payments', 0)) && $coursepaymentData['payment_status_payment_status_id'] == 2): ?>
                                                                <div class="col-12 col-md-2 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                                                                    <button class="custom-btn w-100" type="button" data-bs-target="#updatePaymentStatus" data-bs-toggle="modal">
                                                                        Update Payment Status
                                                                    </button>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <div class="col-12">
                                                        <div class="row p-4 m-0 g-0 component-theme w-100">

                                                            <div class="col-12">
                                                                <div class="payment-timeline">
                                                                    <div class="timeline-progress">
                                                                        <?php
                                                                        $query = "SELECT * FROM `payment_status`";
                                                                        $progress = 0;
                                                                        $progressColor = "";
                                                                        switch ($coursepaymentData['payment_status_payment_status_id']) {
                                                                            case 2:
                                                                                $progressColor = "completed";
                                                                                $progress = 50;
                                                                                $query .= " WHERE `payment_status_id` IN (1,2)";
                                                                                break;
                                                                            case 3:
                                                                                $progressColor = "accepted";
                                                                                $progress = 100;
                                                                                $query .= " WHERE `payment_status_id` IN (1,3)";
                                                                                break;
                                                                            case 4:
                                                                                $progressColor = "reject";
                                                                                $progress = 100;
                                                                                $query .= " WHERE `payment_status_id` IN (1,4)";
                                                                                break;
                                                                        }
                                                                        ?>
                                                                        <div class="progress-bar <?= $progressColor ?>" style="width:<?= $progress ?>%"></div>
                                                                    </div>
                                                                    <div class="timeline-items">
                                                                        <?php
                                                                        $icons = ["majesticons:clock", "material-symbols:pending-outline", "mdi:snowflake-approve", "fontisto:close", "material-symbols:pending-outline", "mdi:snowflake-approve", "fontisto:close", "mdi:approve"];
                                                                        $paymentStatus = Database::search($query);
                                                                        while ($st = $paymentStatus->fetch_assoc()) {
                                                                            $timelineItemColor = "";
                                                                            $bubbleAction = "";
                                                                            if ($coursepaymentData['payment_status_payment_status_id'] >= $st['payment_status_id']) {
                                                                                if ($coursepaymentData['payment_status_payment_status_id'] == 4) {
                                                                                    $timelineItemColor = "reject";
                                                                                } elseif ($coursepaymentData['payment_status_payment_status_id'] == 3) {
                                                                                    $timelineItemColor = "accepted";
                                                                                } else {
                                                                                    $timelineItemColor = ($coursepaymentData['payment_status_payment_status_id'] == $st['payment_status_id']) ? "" : "completed";
                                                                                    $bubbleAction      = ($coursepaymentData['payment_status_payment_status_id'] == $st['payment_status_id']) ? "pulse" : "";
                                                                                }
                                                                            }
                                                                        ?>
                                                                            <div class="timeline-item <?= $timelineItemColor ?>">
                                                                                <div class="timeline-bubble <?= $bubbleAction ?>">
                                                                                    <iconify-icon icon="<?= $icons[$st['payment_status_id'] - 1] ?>" width="25" height="25"></iconify-icon>
                                                                                </div>
                                                                                <div class="timeline-label"><span><?= $st['payment_status'] ?></span></div>
                                                                            </div>
                                                                        <?php } ?>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="payment-card-container mt-4">
                                                                <div class="payment-card">
                                                                    <div class="card-background"></div>
                                                                    <div class="card-content">
                                                                        <div class="payment-header reference-section">
                                                                            <div class="reference-section">
                                                                                <span class="reference-label">PAYMENT REFERENCE</span>
                                                                                <h2 class="reference-number"><?= $paymentData["refference"] ?></h2>
                                                                                <hr>
                                                                            </div>
                                                                            <div class="amount-section">
                                                                                <div class="amount-display">
                                                                                    <span class="amount-label">Total Balance Amount</span>
                                                                                    <span class="amount-value"><?= $currencyCode ?> <?= $balance > 0 ? number_format($balance, 2) : '0.00' ?></span>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <span class="reference-label my-3">STUDENT DETAILS</span>
                                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                                            <div class="detail-item align-items-start">
                                                                                <div class="detail-icon"><iconify-icon icon="icons8:student" width="20" height="20"></iconify-icon></div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Student</span>
                                                                                    <span class="detail-value"><?= $paymentData['student_id'] ?></span>
                                                                                    <span class="detail-value"><?= $paymentData['title'] . "." . $paymentData['first_name'] . " " . $paymentData['last_name'] ?></span>
                                                                                    <span class="detail-value"><?= $paymentData['email'] ?></span>
                                                                                    <span class="detail-value"><?= $paymentData['mobile'] ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item align-items-start">
                                                                                <div class="detail-icon"><iconify-icon icon="carbon:batch-job" width="20" height="20"></iconify-icon></div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Batch</span>
                                                                                    <span class="detail-value"><?= $paymentData["faculty"] ?></span>
                                                                                    <span class="detail-value"><?= $paymentData["programme"] ?></span>
                                                                                    <span class="detail-value"><?= $paymentData["intake"] ?></span>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <span class="reference-label my-3">PAYMENT DETAILS</span>
                                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Amount</span>
                                                                                    <span class="detail-value"><?= $currencyCode ?> <?= number_format($paymentData['amount'], 2) ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Type</span>
                                                                                    <span class="detail-value"><?= $paymentData["payment_type"] ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Due Date</span>
                                                                                    <span class="detail-value"><?= $paymentData["due_date"] ?? '--/--/----' ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Discount</span>
                                                                                    <span class="detail-value">
                                                                                        <?php
                                                                                        if ($paymentData["discount_type_id"] == 1) echo "No Discount";
                                                                                        elseif ($paymentData["discount_type_id"] == 2) echo $currencyCode . " " . number_format($paymentData["discount"], 2) . " | " . $paymentData["discount_type"];
                                                                                        else echo number_format($paymentData["discount"]) . "% | " . $paymentData["discount_type"];
                                                                                        ?>
                                                                                    </span>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Discount</span>
                                                                                    <span class="detail-value"><?= $currencyCode ?> <?= number_format($totalDiscount, 2) ?></span>
                                                                                </div>
                                                                            </div>

                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">
                                                                                        Total Penalty
                                                                                        <span class="penalty-lkr-badge">Stored in LKR</span>
                                                                                    </span>
                                                                                    <span class="detail-value">LKR <?= number_format($penaltyAmtLKR, 2) ?></span>
                                                                                    <?php if (!$isBaseCurrencyLKR && !$exchangeRate): ?>
                                                                                        <span class="penalty-stored-note">
                                                                                            <iconify-icon icon="mdi:alert-circle-outline" width="11"></iconify-icon>
                                                                                            No exchange rate — cannot convert to <?= $currencyCode ?>
                                                                                        </span>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            </div>

                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Tax Amount
                                                                                        <span class="currency-label-badge tax-badge"><?= number_format($latestTaxRate, 2) ?>%</span>
                                                                                    </span>
                                                                                    <span class="detail-value"><?= $currencyCode ?> <?= number_format($taxOnPayable, 2) ?></span>
                                                                                    <span class="detail-lkr-hint">Tax on total payable amount</span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Payable Amount</span>
                                                                                    <span class="detail-value"><?= $currencyCode ?> <?= number_format($payableAmount, 2) ?></span>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Paid Amount</span>
                                                                                    <span class="detail-value"><?= $currencyCode ?> <?= number_format($paidAmount, 2) ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Total Balance</span>
                                                                                    <?php if ($balance > 0): ?>
                                                                                        <span class="detail-value"><?= $currencyCode ?> <?= number_format($balance, 2) ?></span>
                                                                                    <?php else: ?>
                                                                                        <span class="detail-value"><?= $currencyCode ?> 0.00</span>

                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <?php
                                                                        if ($paymentData['panelty_payment_option_panelty_payment_option_id'] != 1):
                                                                            $panelties = Database::search("
                                                                                SELECT * FROM `course_payments_penalties`
                                                                                INNER JOIN `panelty_payments_type`
                                                                                    ON `course_payments_penalties`.`panelty_payment_type_panelty_payment_type_id` = `panelty_payments_type`.`panelty_payments_type_id`
                                                                                WHERE `course_payments_course_payments_id` = '" . $paymentData['course_payments_id'] . "'
                                                                            ");
                                                                            if ($panelties->num_rows > 0):
                                                                        ?>
                                                                                <span class="reference-label my-3">PENALTY PAYMENT ENABLED</span>
                                                                                <?php while ($panelty = $panelties->fetch_assoc()): ?>
                                                                                    <div class="details-row d-flex flex-column flex-lg-row">
                                                                                        <div class="detail-item">
                                                                                            <div class="detail-content">
                                                                                                <span class="detail-label">Option</span>
                                                                                                <span class="detail-value"><?= $paymentData["panelty_payment_option"] ?></span>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="detail-item">
                                                                                            <div class="detail-content">
                                                                                                <span class="detail-label">Type</span>
                                                                                                <span class="detail-value"><?= $panelty["panelty_payments_type"] ?></span>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="detail-item">
                                                                                            <div class="detail-content">
                                                                                                <span class="detail-label">
                                                                                                    Amount
                                                                                                    <span class="penalty-lkr-badge">LKR</span>
                                                                                                </span>
                                                                                                <span class="detail-value">LKR <?= number_format($panelty["amount"], 2) ?></span>
                                                                                            </div>
                                                                                        </div>
                                                                                        <?php if (!empty($panelty["days"])): ?>
                                                                                            <div class="detail-item">
                                                                                                <div class="detail-content">
                                                                                                    <span class="detail-label">Days</span>
                                                                                                    <span class="detail-value"><?= $panelty["days"] ?></span>
                                                                                                </div>
                                                                                            </div>
                                                                                        <?php endif; ?>
                                                                                    </div>
                                                                                <?php endwhile; ?>
                                                                        <?php endif;
                                                                        endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- ── Receipt card ─────────────────────────────────── -->
                                                            <div class="payment-card-container mt-4">
                                                                <div class="payment-card">
                                                                    <div class="card-background-ongoing"></div>
                                                                    <div class="card-content">
                                                                        <?php
                                                                        $paidCurrCode         = !empty($coursepaymentData['code']) ? htmlspecialchars($coursepaymentData['code']) : $currencyCode;
                                                                        $paidIsDiffFromCourse = ($paidCurrCode !== $currencyCode);
                                                                        $recDiscConverted     = convertToCourseAmount((float)$coursepaymentData['discount_amount'], $receiptRate);
                                                                        ?>
                                                                        <div class="payment-header reference-section">
                                                                            <div class="reference-section">
                                                                                <span class="reference-label">RECEIPT NO</span>
                                                                                <h2 class="reference-number"><?= $coursepaymentData["receipt_number"] == null ? "N/A" : $coursepaymentData["receipt_number"] ?></h2>
                                                                                <hr>
                                                                            </div>
                                                                            <div class="amount-section">
                                                                                <div class="amount-display">
                                                                                    <span class="amount-label">Paid Amount (Tax-Inclusive)</span>
                                                                                    <span class="amount-value"><?= $paidCurrCode ?> <?= number_format($receiptPaidAmt, 2) ?></span>
                                                                                    <?php if ($receiptTaxAmount > 0): ?>
                                                                                        <small class="amount-label" style="font-size:11px;opacity:.7;">
                                                                                            Course Fee: <?= $paidCurrCode ?> <?= number_format($receiptBaseAmount, 2) ?>
                                                                                            &nbsp;+&nbsp;
                                                                                            Tax (<?= number_format($receiptTaxRate, 2) ?>%): <?= $paidCurrCode ?> <?= number_format($receiptTaxAmount, 2) ?>
                                                                                        </small>
                                                                                    <?php endif; ?>
                                                                                    <?php if ($paidIsDiffFromCourse): ?>
                                                                                        <small class="amount-label" style="font-size:12px;opacity:.7;">
                                                                                            = <?= $currencyCode ?> <?= number_format($receiptConvertedAmt, 2) ?>
                                                                                            <?php if ($receiptRate): ?>&nbsp;<span style="opacity:.6;">(@ <?= number_format($receiptRate, 2) ?>)</span><?php endif; ?>
                                                                                        </small>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                        </div>

                                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Date Paid</span>
                                                                                    <span class="detail-value"><?= date_format(date_create($coursepaymentData["date_paid"]), "Y-m-d h:i A") ?? '--/--/----' ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Given Discount</span>
                                                                                    <span class="detail-value"><?= $paidCurrCode ?> <?= number_format((float)$coursepaymentData['discount_amount'], 2) ?></span>
                                                                                </div>
                                                                            </div>

                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">
                                                                                        Added Penalty
                                                                                        <span class="penalty-lkr-badge">LKR</span>
                                                                                    </span>
                                                                                    <span class="detail-value">LKR <?= number_format($receiptPenaltyLKR, 2) ?></span>
                                                                                </div>
                                                                            </div>

                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Tax Rate</span>
                                                                                    <span class="detail-value"><?= $receiptTaxRate > 0 ? number_format($receiptTaxRate, 2) . '%' : 'N/A' ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Tax Amount</span>
                                                                                    <span class="detail-value"><?= $receiptTaxAmount > 0 ? $paidCurrCode . ' ' . number_format($receiptTaxAmount, 2) : 'N/A' ?></span>
                                                                                    <?php if ($receiptTaxAmount > 0): ?>
                                                                                        <span class="detail-lkr-hint">Extracted from paid (tax-inclusive)</span>
                                                                                    <?php endif; ?>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Course Fee (Base)</span>
                                                                                    <span class="detail-value"><?= $paidCurrCode ?> <?= number_format($receiptBaseAmount, 2) ?></span>
                                                                                    <span class="detail-lkr-hint"><?= $paidCurrCode ?> <?= number_format($receiptPaidAmt, 2) ?> − <?= number_format($receiptTaxAmount, 2) ?> tax</span>
                                                                                </div>
                                                                            </div>
                                                                            <div class="detail-item">
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Payment Method</span>
                                                                                    <span class="detail-value"><?= $coursepaymentData['payment_method'] ?></span>
                                                                                </div>
                                                                            </div>
                                                                            <?php if ($paidIsDiffFromCourse && $receiptRate): ?>
                                                                                <div class="detail-item">
                                                                                    <div class="detail-content">
                                                                                        <span class="detail-label">Exchange Rate Used</span>
                                                                                        <span class="detail-value">1 <?= $currencyCode ?> = <?= $paidCurrCode ?> <?= number_format($receiptRate, 2) ?></span>
                                                                                    </div>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        </div>

                                                                        <div class="details-row d-flex flex-column flex-lg-row">
                                                                            <div class="detail-item align-items-start">
                                                                                <div class="detail-icon"><iconify-icon icon="hugeicons:note" width="20"></iconify-icon></div>
                                                                                <div class="detail-content">
                                                                                    <span class="detail-label">Note</span>
                                                                                    <span class="detail-value"><?= $coursepaymentData['note'] == null ? "N/A" : $coursepaymentData['note'] ?></span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- ── Payment slips ─────────────────────────────────────── -->
                                                            <?php
                                                            $paymnetSlips = Database::search("SELECT * FROM `course_payment_slips` 
                                                            WHERE (`course_payment_has_payment_id` = '" . $coursepaymentData['course_payment_has_payment_id'] . "' OR 
                                                            `course_payment_has_payment_id` IN (SELECT `course_payment_has_payment_id` FROM `course_payment_has_payments` 
                                                            WHERE `course_payment_receipts_course_payment_receipt_id` IS NOT NULL 
                                                            AND `course_payment_receipts_course_payment_receipt_id`='" . $coursepaymentData['course_payment_receipts_course_payment_receipt_id'] . "'))");
                                                            if ($paymnetSlips->num_rows > 0):
                                                            ?>
                                                                <div class="col-12">
                                                                    <div class="row p-4 m-0 g-0 align-content-center bl-solid mt-4">
                                                                        <span class="main-topic lh-lg">Payment Slips</span>
                                                                        <div class="accordion accordion-flush" id="accordionParent">
                                                                            <?php while ($paymentSlip = $paymnetSlips->fetch_assoc()) {
                                                                                $slipVerifier = new SlipVerifier();
                                                                                $fileType     = $slipVerifier->checkFileType($paymentSlip['slip_name']);
                                                                                $bankAccNo    = '';
                                                                                if ($coursepaymentData['payment_method_id'] == 3) {
                                                                                    $bank = Database::search("SELECT `acc_no` FROM `bank`");
                                                                                    if ($bank->num_rows > 0) $bankAccNo = $bank->fetch_assoc()['acc_no'];
                                                                                } else {
                                                                                    $bank = Database::search("SELECT `acc_no` FROM `bank` WHERE `bank_id`='" . $coursepaymentData['bank_bank_id'] . "'");
                                                                                    if ($bank->num_rows > 0) $bankAccNo = $bank->fetch_assoc()['acc_no'];
                                                                                }
                                                                                $fileUrl  = file_exists("./assets/uploads/payment-slips/" . $paymentSlip['slip_name'])
                                                                                    ? Project::getBaseUrl()       . "/assets/uploads/payment-slips/" . $paymentSlip['slip_name']
                                                                                    : Project::getBasePortalUrl() . "/assets/uploads/payment-slips/" . $paymentSlip['slip_name'];
                                                                                $fileData = $slipVerifier->verifySlip($paymentData['refference'], $bankAccNo, $coursepaymentData['paid_amount'], $fileUrl);
                                                                                if ($fileType != 'unsupported'):
                                                                            ?>
                                                                                    <div class="accordion-item mt-4">
                                                                                        <h2 class="accordion-header bl-solid">
                                                                                            <button class="accordion-button collapsed d-flex align-items-center" type="button"
                                                                                                data-bs-toggle="collapse"
                                                                                                data-bs-target="#flush-collapse-<?= $paymentSlip['course_payment_slip_id'] ?>"
                                                                                                aria-expanded="false">
                                                                                                <img src="./assets/imgs/icons/<?= $fileType ?>.png" class="img-fluid pe-4" alt="Payment Slip">
                                                                                                <?php if ($fileData['verified']): ?>
                                                                                                    <iconify-icon icon="ph:seal-check-duotone" width="40" height="40" class="text-dark-green"></iconify-icon>
                                                                                                    <div class="ms-3 d-flex flex-column">
                                                                                                        <span class="text-dark-green text-16 fw-semibold">Payment slip successfully verified</span>
                                                                                                        <span class="text-12">Transaction details have been authenticated.</span>
                                                                                                    </div>
                                                                                                <?php else: ?>
                                                                                                    <iconify-icon icon="ant-design:close-circle-twotone" width="40" height="40" class="text-dark-red"></iconify-icon>
                                                                                                    <div class="ms-3 d-flex flex-column">
                                                                                                        <span class="text-dark-red text-16 fw-semibold">Unable to verify payment</span>
                                                                                                        <span class="text-12">The uploaded slip appears to be invalid, duplicated, or altered.</span>
                                                                                                    </div>
                                                                                                <?php endif; ?>
                                                                                            </button>
                                                                                        </h2>
                                                                                        <div id="flush-collapse-<?= $paymentSlip['course_payment_slip_id'] ?>" class="accordion-collapse collapse bl-solid-lrb" data-bs-parent="#accordionParent">
                                                                                            <div class="accordion-body">
                                                                                                <div class="col-12">
                                                                                                    <div class="row p-0 m-0">
                                                                                                        <hr>
                                                                                                        <div class="col-12 col-xl-6">
                                                                                                            <div class="row">
                                                                                                                <div class="col-12">
                                                                                                                    <div class="d-flex align-items-start">
                                                                                                                        <?php if ($fileData['reference']): ?>
                                                                                                                            <iconify-icon icon="gg:check-o" width="25" height="25" class="text-dark-green"></iconify-icon>
                                                                                                                            <div class="ms-3 d-flex flex-column"><span class="text-dark-green text-16 fw-semibold">Reference Number</span><span class="text-12">Reference number verified and matches the transaction record.</span></div>
                                                                                                                        <?php else: ?>
                                                                                                                            <iconify-icon icon="gg:close-o" width="25" height="25" class="text-dark-red"></iconify-icon>
                                                                                                                            <div class="ms-3 d-flex flex-column"><span class="text-dark-red text-16 fw-semibold">Reference Number</span><span class="text-12">Reference number could not be matched.</span></div>
                                                                                                                        <?php endif; ?>
                                                                                                                    </div>
                                                                                                                </div>
                                                                                                                <div class="col-12 mt-2">
                                                                                                                    <div class="d-flex align-items-start">
                                                                                                                        <?php
                                                                                                                        if ($fileData['date'] != null) {
                                                                                                                            $slipDate = date('Y-m-d', strtotime($fileData['date']));
                                                                                                                            $paidDate = date('Y-m-d', strtotime($coursepaymentData['date_paid']));
                                                                                                                            if ($slipDate == $paidDate): ?>
                                                                                                                                <iconify-icon icon="gg:check-o" width="25" height="25" class="text-dark-green"></iconify-icon>
                                                                                                                                <div class="ms-3 d-flex flex-column"><span class="text-dark-green text-16 fw-semibold">Payment Date</span><span class="text-12">Payment date successfully matched.</span></div>
                                                                                                                            <?php else: ?>
                                                                                                                                <iconify-icon icon="gg:close-o" width="25" height="25" class="text-dark-red"></iconify-icon>
                                                                                                                                <div class="ms-3 d-flex flex-column"><span class="text-dark-red text-16 fw-semibold">Payment Date</span><span class="text-12">Payment date does not match system records.</span></div>
                                                                                                                            <?php endif; ?>
                                                                                                                        <?php } else { ?>
                                                                                                                            <iconify-icon icon="gg:close-o" width="25" height="25" class="text-dark-red"></iconify-icon>
                                                                                                                            <div class="ms-3 d-flex flex-column"><span class="text-dark-red text-16 fw-semibold">Payment Date</span><span class="text-12">Payment date missing or does not match.</span></div>
                                                                                                                        <?php } ?>
                                                                                                                    </div>
                                                                                                                </div>
                                                                                                                <div class="col-12">
                                                                                                                    <div class="d-flex align-items-start">
                                                                                                                        <?php if ($fileData['amount'] != null): ?>
                                                                                                                            <iconify-icon icon="gg:check-o" width="25" height="25" class="text-dark-green"></iconify-icon>
                                                                                                                            <div class="ms-3 d-flex flex-column"><span class="text-dark-green text-16 fw-semibold">Payment Amount</span><span class="text-12"><?= $coursepaymentData['paid_amount'] ?> — amount confirmed.</span></div>
                                                                                                                        <?php else: ?>
                                                                                                                            <iconify-icon icon="gg:close-o" width="25" height="25" class="text-dark-red"></iconify-icon>
                                                                                                                            <div class="ms-3 d-flex flex-column"><span class="text-dark-red text-16 fw-semibold">Payment Amount</span><span class="text-12">Mismatch detected in payment amount.</span></div>
                                                                                                                        <?php endif; ?>
                                                                                                                    </div>
                                                                                                                </div>
                                                                                                                <div class="col-12">
                                                                                                                    <div class="d-flex align-items-start">
                                                                                                                        <?php if ($fileData['account']): ?>
                                                                                                                            <iconify-icon icon="gg:check-o" width="25" height="25" class="text-dark-green"></iconify-icon>
                                                                                                                            <div class="ms-3 d-flex flex-column"><span class="text-dark-green text-16 fw-semibold">Account Number</span><span class="text-12">Account number correct.</span></div>
                                                                                                                        <?php else: ?>
                                                                                                                            <iconify-icon icon="gg:close-o" width="25" height="25" class="text-dark-red"></iconify-icon>
                                                                                                                            <div class="ms-3 d-flex flex-column"><span class="text-dark-red text-16 fw-semibold">Account Number</span><span class="text-12">Incorrect or unrecognized account number.</span></div>
                                                                                                                        <?php endif; ?>
                                                                                                                    </div>
                                                                                                                </div>
                                                                                                            </div>
                                                                                                        </div>
                                                                                                        <div class="col-12 col-xl-6 mt-3 mt-xl-0">
                                                                                                            <div class="row justify-content-end align-items-center">
                                                                                                                <?php if ($fileData['verified']): ?>
                                                                                                                    <div class="alert alert-success br-0" role="alert">✅ This payment slip has been successfully verified and is valid.</div>
                                                                                                                <?php else: ?>
                                                                                                                    <div class="alert alert-warning br-0" role="alert">⚠️ This payment slip has not been verified yet. Please review carefully before approval.</div>
                                                                                                                <?php endif; ?>
                                                                                                                <button class="custom-btn col-4" type="button" onclick="window.open('<?= $fileUrl ?>', '_blank');">View</button>
                                                                                                            </div>
                                                                                                        </div>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                            <?php endif;
                                                                            } ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>

                                                            <?php if ($paymentData['payment_type_id'] == 18):
                                                                $paymnetSlips = Database::search("
                                                                                        SELECT * FROM students_identity_documents
                                                                                        WHERE students_student_id = '" . $paymentData['student_id'] . "'
                                                                                        AND `students_identity_documents_type_id` = '8'
                                                                                        ORDER BY date_added DESC LIMIT 1
                                                                                    ");
                                                                if ($paymnetSlips->num_rows > 0):
                                                            ?>
                                                                    <div class="col-12">
                                                                        <div class="row p-4 m-0 g-0 align-content-center bl-solid mt-4">
                                                                            <span class="main-topic lh-lg">Payment Slips</span>
                                                                            <div class="accordion accordion-flush" id="accordionParent">
                                                                                <?php while ($paymentSlip = $paymnetSlips->fetch_assoc()) {
                                                                                    $slipVerifier = new SlipVerifier();
                                                                                    $fileType     = $slipVerifier->checkFileType($paymentSlip['document']);
                                                                                    $bankAccNo    = '';

                                                                                    $fileUrl  = file_exists("./assets/uploads/student-identity-documents/payment-slips/" . $paymentSlip['document'])
                                                                                        ? Project::getBaseUrl()       . "/assets/uploads/student-identity-documents/payment-slips/" . $paymentSlip['document']
                                                                                        : Project::getBasePortalUrl() . "/assets/uploads/payment-slips/" . $paymentSlip['document'];
                                                                                    $fileData = $slipVerifier->verifySlip($paymentData['refference'], $bankAccNo, $coursepaymentData['paid_amount'], $fileUrl);
                                                                                    if ($fileType != 'unsupported'):
                                                                                ?>
                                                                                        <div class="accordion-item mt-4">
                                                                                            <h2 class="accordion-header bl-solid">
                                                                                                <button class="accordion-button collapsed d-flex align-items-center" type="button"
                                                                                                    data-bs-toggle="collapse"
                                                                                                    data-bs-target="#flush-collapse-<?= $paymentSlip['students_identity_document_id'] ?>"
                                                                                                    aria-expanded="false">
                                                                                                    <img src="./assets/imgs/icons/<?= $fileType ?>.png" class="img-fluid pe-4" alt="Payment Slip">
                                                                                                    <?php if ($fileData['verified']): ?>
                                                                                                        <iconify-icon icon="ph:seal-check-duotone" width="40" height="40" class="text-dark-green"></iconify-icon>
                                                                                                        <div class="ms-3 d-flex flex-column">
                                                                                                            <span class="text-dark-green text-16 fw-semibold">Payment slip successfully verified</span>
                                                                                                            <span class="text-12">Transaction details have been authenticated.</span>
                                                                                                        </div>
                                                                                                    <?php else: ?>
                                                                                                        <iconify-icon icon="ant-design:close-circle-twotone" width="40" height="40" class="text-dark-red"></iconify-icon>
                                                                                                        <div class="ms-3 d-flex flex-column">
                                                                                                            <span class="text-dark-red text-16 fw-semibold">Unable to verify payment</span>
                                                                                                            <span class="text-12">The uploaded slip appears to be invalid, duplicated, or altered.</span>
                                                                                                        </div>
                                                                                                    <?php endif; ?>
                                                                                                </button>
                                                                                            </h2>
                                                                                            <div id="flush-collapse-<?= $paymentSlip['students_identity_document_id'] ?>" class="accordion-collapse collapse bl-solid-lrb" data-bs-parent="#accordionParent">
                                                                                                <div class="accordion-body">
                                                                                                    <div class="col-12">
                                                                                                        <div class="row p-0 m-0">
                                                                                                            <hr>
                                                                                                            <div class="col-12 col-xl-6">
                                                                                                                <div class="row">
                                                                                                                    <div class="col-12">
                                                                                                                        <div class="d-flex align-items-start">
                                                                                                                            <?php if ($fileData['reference']): ?>
                                                                                                                                <iconify-icon icon="gg:check-o" width="25" height="25" class="text-dark-green"></iconify-icon>
                                                                                                                                <div class="ms-3 d-flex flex-column"><span class="text-dark-green text-16 fw-semibold">Reference Number</span><span class="text-12">Reference number verified and matches the transaction record.</span></div>
                                                                                                                            <?php else: ?>
                                                                                                                                <iconify-icon icon="gg:close-o" width="25" height="25" class="text-dark-red"></iconify-icon>
                                                                                                                                <div class="ms-3 d-flex flex-column"><span class="text-dark-red text-16 fw-semibold">Reference Number</span><span class="text-12">Reference number could not be matched.</span></div>
                                                                                                                            <?php endif; ?>
                                                                                                                        </div>
                                                                                                                    </div>
                                                                                                                    <div class="col-12 mt-2">
                                                                                                                        <div class="d-flex align-items-start">
                                                                                                                            <?php
                                                                                                                            if ($fileData['date'] != null) {
                                                                                                                                $slipDate = date('Y-m-d', strtotime($fileData['date']));
                                                                                                                                $paidDate = date('Y-m-d', strtotime($coursepaymentData['date_paid']));
                                                                                                                                if ($slipDate == $paidDate): ?>
                                                                                                                                    <iconify-icon icon="gg:check-o" width="25" height="25" class="text-dark-green"></iconify-icon>
                                                                                                                                    <div class="ms-3 d-flex flex-column"><span class="text-dark-green text-16 fw-semibold">Payment Date</span><span class="text-12">Payment date successfully matched.</span></div>
                                                                                                                                <?php else: ?>
                                                                                                                                    <iconify-icon icon="gg:close-o" width="25" height="25" class="text-dark-red"></iconify-icon>
                                                                                                                                    <div class="ms-3 d-flex flex-column"><span class="text-dark-red text-16 fw-semibold">Payment Date</span><span class="text-12">Payment date does not match system records.</span></div>
                                                                                                                                <?php endif; ?>
                                                                                                                            <?php } else { ?>
                                                                                                                                <iconify-icon icon="gg:close-o" width="25" height="25" class="text-dark-red"></iconify-icon>
                                                                                                                                <div class="ms-3 d-flex flex-column"><span class="text-dark-red text-16 fw-semibold">Payment Date</span><span class="text-12">Payment date missing or does not match.</span></div>
                                                                                                                            <?php } ?>
                                                                                                                        </div>
                                                                                                                    </div>
                                                                                                                    <div class="col-12">
                                                                                                                        <div class="d-flex align-items-start">
                                                                                                                            <?php if ($fileData['amount'] != null && empty($fileData['amount'])): ?>
                                                                                                                                <iconify-icon icon="gg:check-o" width="25" height="25" class="text-dark-green"></iconify-icon>
                                                                                                                                <div class="ms-3 d-flex flex-column"><span class="text-dark-green text-16 fw-semibold">Payment Amount</span><span class="text-12"><?= $coursepaymentData['paid_amount'] ?> — amount confirmed.</span></div>
                                                                                                                            <?php else: ?>
                                                                                                                                <iconify-icon icon="gg:close-o" width="25" height="25" class="text-dark-red"></iconify-icon>
                                                                                                                                <div class="ms-3 d-flex flex-column"><span class="text-dark-red text-16 fw-semibold">Payment Amount</span><span class="text-12">Mismatch detected in payment amount.</span></div>
                                                                                                                            <?php endif; ?>
                                                                                                                        </div>
                                                                                                                    </div>
                                                                                                                    <div class="col-12">
                                                                                                                        <div class="d-flex align-items-start">
                                                                                                                            <?php if ($fileData['account']): ?>
                                                                                                                                <iconify-icon icon="gg:check-o" width="25" height="25" class="text-dark-green"></iconify-icon>
                                                                                                                                <div class="ms-3 d-flex flex-column"><span class="text-dark-green text-16 fw-semibold">Account Number</span><span class="text-12">Account number correct.</span></div>
                                                                                                                            <?php else: ?>
                                                                                                                                <iconify-icon icon="gg:close-o" width="25" height="25" class="text-dark-red"></iconify-icon>
                                                                                                                                <div class="ms-3 d-flex flex-column"><span class="text-dark-red text-16 fw-semibold">Account Number</span><span class="text-12">Incorrect or unrecognized account number.</span></div>
                                                                                                                            <?php endif; ?>
                                                                                                                        </div>
                                                                                                                    </div>
                                                                                                                </div>
                                                                                                            </div>
                                                                                                            <div class="col-12 col-xl-6 mt-3 mt-xl-0">
                                                                                                                <div class="row justify-content-end align-items-center">
                                                                                                                    <?php if ($fileData['verified']): ?>
                                                                                                                        <div class="alert alert-success br-0" role="alert">✅ This payment slip has been successfully verified and is valid.</div>
                                                                                                                    <?php else: ?>
                                                                                                                        <div class="alert alert-warning br-0" role="alert">⚠️ This payment slip has not been verified yet. Please review carefully before approval.</div>
                                                                                                                    <?php endif; ?>
                                                                                                                    <button class="custom-btn col-4" type="button" onclick="window.open('<?= $fileUrl ?>', '_blank');">View</button>
                                                                                                                </div>
                                                                                                            </div>
                                                                                                        </div>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                <?php endif;
                                                                                } ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                            <?php
                                                                endif;
                                                            endif; ?>

                                                            <!-- ── Payment logs ──────────────────────────────────────── -->
                                                            <div class="col-12 mt-4">
                                                                <div class="row p-0 m-0 g-0">
                                                                    <div class="col-12 mb-4">
                                                                        <div class="row p-0 gy-3 gx-3 align-content-center">
                                                                            <div class="col-12 col-md-4 col-lg-2 d-flex flex-column">
                                                                                <div class="d-flex custom-div align-items-center justify-content-center">
                                                                                    <?php
                                                                                    $logs_count = Database::search("SELECT COUNT(*) as 'count' FROM `payment_logs` WHERE `course_payments_course_payments_id`='" . $paymentData['course_payments_id'] . "'");
                                                                                    $logsData   = $logs_count->fetch_assoc();
                                                                                    ?>
                                                                                    <span class="text-14 dark-theme fw-medium"><?= $logsData['count'] ?> Logs</span>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-12">
                                                                        <div class="table-responsive-wrapper">
                                                                            <div class="table-responsive" id="users-table-container">
                                                                                <table class="table bl-solid m-0 p-0 g-0" id="users-table">
                                                                                    <thead>
                                                                                        <tr class="br-t-5">
                                                                                            <th scope="col" class="px-4 py-3 bg-table-header main-text fw-medium">
                                                                                                <div class="d-flex flex-row align-items-center">
                                                                                                    <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2" id="check-all" onchange="tableCheckEvents()">
                                                                                                    <span class="text-14 main-text fw-medium mx-2">User Name</span>
                                                                                                </div>
                                                                                            </th>
                                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Date Updated</th>
                                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Log</th>
                                                                                            <th scope="col" class="px-4 py-3 bg-table-header text-14 main-text fw-medium">Note</th>
                                                                                        </tr>
                                                                                    </thead>
                                                                                    <tbody id="user-table-data">
                                                                                        <?php
                                                                                        $users = Database::search("
                                                                                            SELECT * FROM `payment_logs`
                                                                                            INNER JOIN `users` ON `payment_logs`.`users_user_id` = `users`.`user_id`
                                                                                            INNER JOIN `title` ON `users`.`title_title_id` = `title`.`title_id`
                                                                                            WHERE `course_payments_course_payments_id` = '" . $paymentData['course_payments_id'] . "'
                                                                                            ORDER BY `date_updated` DESC
                                                                                        ");
                                                                                        if ($users->num_rows > 0) {
                                                                                            while ($data = $users->fetch_assoc()):
                                                                                        ?>
                                                                                                <tr>
                                                                                                    <td class="px-4 py-3 pe-0 align-middle">
                                                                                                        <div class="d-flex flex-row align-items-center">
                                                                                                            <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 row-check"
                                                                                                                id="check-user<?= $data["user_id"] ?>"
                                                                                                                onchange="checkRowEvents(<?= $data['user_id'] ?>);">
                                                                                                            <?php if (!empty($data["profile_img"]) && file_exists('./assets/imgs/users/' . $data["profile_img"])): ?>
                                                                                                                <img src="./assets/imgs/users/<?= $data["profile_img"] ?>" class="hwr-40" alt="User image">
                                                                                                            <?php else:
                                                                                                                $fni = strtoupper(substr($data["user_fname"], 0, 1));
                                                                                                                $lni = strtoupper(substr($data["user_lname"], 0, 1));
                                                                                                            ?>
                                                                                                                <div class="d-flex hwr-40 dark-theme bg-light-theme align-items-center justify-content-center fw-medium"><?= $fni . $lni ?></div>
                                                                                                            <?php endif; ?>
                                                                                                            <span class="text-14 main-text mx-2"><?= $data["title"] . "." . $data["user_fname"] . " " . $data["user_lname"] ?></span>
                                                                                                        </div>
                                                                                                    </td>
                                                                                                    <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["date_updated"] ?></span></td>
                                                                                                    <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["log"] ?></span></td>
                                                                                                    <td class="px-4 py-2 align-middle"><span class="text-14 main-text"><?= $data["note"] ?></span></td>
                                                                                                </tr>
                                                                                            <?php endwhile;
                                                                                        } else { ?>
                                                                                            <tr>
                                                                                                <td colspan="13" class="align-middle justify-content-center">
                                                                                                    <div class="d-flex flex-column align-items-center">
                                                                                                        <img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />
                                                                                                    </div>
                                                                                                </td>
                                                                                            </tr>
                                                                                        <?php } ?>
                                                                                    </tbody>
                                                                                </table>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1"></div>
                                                <footer><?php include './includes/footer.php' ?></footer>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ════════════════════════════════════════════════════════════════════════
                                 UPDATE PAYMENT STATUS MODAL
                            ════════════════════════════════════════════════════════════════════════ -->
                            <div class="modal fade" id="updatePaymentStatus" tabindex="-1" role="dialog" aria-hidden="true">
                                <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
                                    <div class="modal-content component-theme">

                                        <div class="modal-header" style="border-bottom:1px solid var(--line-color);">
                                            <div class="d-flex align-items-center gap-3 flex-wrap w-100">
                                                <span class="text-16 main-text fw-semibold flex-grow-1">Update Payment Status</span>
                                                <?php if (!$isBaseCurrencyLKR): ?>
                                                    <div class="d-flex align-items-center gap-3 flex-wrap">
                                                        <span class="text-14 main-text fw-medium">Payment Currency:</span>
                                                        <div class="currency-toggle">
                                                            <button type="button" class="currency-toggle-btn active" id="btn-base-currency" onclick="switchCurrency('base')"><?= htmlspecialchars($paymentData['currency_code']) ?></button>
                                                            <button type="button" class="currency-toggle-btn" id="btn-lkr-currency" onclick="switchCurrency('lkr')">LKR</button>
                                                        </div>
                                                        <?php if ($exchangeRate): ?>
                                                            <div class="exchange-rate-badge">
                                                                <iconify-icon icon="mdi:swap-horizontal" width="14" height="14"></iconify-icon>
                                                                <span id="live-rate-text">1 <?= htmlspecialchars($paymentData['currency_code']) ?> = LKR <?= number_format($exchangeRateValue, 2) ?> (<?= date('d M Y', strtotime($exchangeRate['effective_date'])) ?>)</span>
                                                            </div>
                                                        <?php else: ?>
                                                            <div class="exchange-rate-badge rate-unavailable">
                                                                <iconify-icon icon="mdi:alert-outline" width="14" height="14"></iconify-icon>
                                                                <span>No exchange rate available</span>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>

                                        <div class="modal-body">
                                            <div class="col-12">
                                                <div class="row g-4">

                                                    <?php if (!$isBaseCurrencyLKR && $exchangeRate): ?>
                                                        <div class="col-12" id="lkr-summary-panel" style="display:none;">
                                                            <div class="lkr-panel">
                                                                <div class="lkr-panel-top">
                                                                    <div class="lkr-panel-top-left"><iconify-icon icon="mdi:currency-usd" width="16" height="16"></iconify-icon> LKR equivalent summary</div>
                                                                    <span class="lkr-panel-rate-note">@ <span id="panel-rate-display"><?= number_format($exchangeRateValue, 2) ?></span> per <?= htmlspecialchars($paymentData['currency_code']) ?></span>
                                                                </div>
                                                                <div class="lkr-panel-grid">
                                                                    <div class="lkr-panel-item"><span class="lkr-item-label">Total Amount</span><span class="lkr-item-value" id="lkr-amount-val">LKR <?= number_format($paymentData['amount'] * $exchangeRateValue, 2) ?></span><span class="lkr-item-sub" id="lkr-amount-sub"><?= $paymentData['currency_code'] ?> <?= number_format($paymentData['amount'], 2) ?></span></div>
                                                                    <div class="lkr-panel-item"><span class="lkr-item-label">Total Discount</span><span class="lkr-item-value" id="lkr-discount-val">LKR <?= number_format($discountAmtLKR, 2) ?></span><span class="lkr-item-sub" id="lkr-discount-sub"><?= $paymentData['currency_code'] ?> <?= number_format($totalDiscount, 2) ?></span></div>
                                                                    <div class="lkr-panel-item"><span class="lkr-item-label">Total Penalty</span><span class="lkr-item-value" id="lkr-penalty-val">LKR <?= number_format($penaltyAmtLKR, 2) ?></span><span class="lkr-item-sub" id="lkr-penalty-sub"><?= $paymentData['currency_code'] ?> <?= number_format($totalPenaltyLKR, 2) ?></span></div>
                                                                    <div class="lkr-panel-item"><span class="lkr-item-label">Total Tax</span><span class="lkr-item-value" id="lkr-tax-val">LKR <?= number_format($taxOnPayable * $exchangeRateValue, 2) ?></span><span class="lkr-item-sub" id="lkr-tax-sub"><?= $paymentData['currency_code'] ?> <?= number_format($taxOnPayable, 2) ?></span></div>
                                                                    <div class="lkr-panel-item"><span class="lkr-item-label">Total Payable</span><span class="lkr-item-value" id="lkr-payable-val">LKR <?= number_format($payableAmtLKR, 2) ?></span><span class="lkr-item-sub" id="lkr-payable-sub"><?= $paymentData['currency_code'] ?> <?= number_format($payableAmtLKR / $exchangeRateValue, 2) ?></span></div>
                                                                    <div class="lkr-panel-item"><span class="lkr-item-label">Total Paid</span><span class="lkr-item-value" id="lkr-paid-val">LKR <?= number_format($paidAmountLKR, 2) ?></span><span class="lkr-item-sub" id="lkr-paid-sub"><?= $paymentData['currency_code'] ?> <?= number_format($paidAmount, 2) ?></span></div>
                                                                    <div class="lkr-panel-item balance-item"><span class="lkr-item-label">Balance Due</span><span class="lkr-item-value" id="lkr-balance-display">LKR <?= number_format($balanceLKR, 2) ?></span><span class="lkr-item-sub" id="lkr-balance-sub"><?= $paymentData['currency_code'] ?> <?= number_format($balance, 2) ?></span></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                        <label class="text-14 main-text required">Payment Status</label>
                                                        <select class="form-select custom-select mt-2" id="status-selector">
                                                            <?php
                                                            $statusQ = Database::search("SELECT * FROM `payment_status` WHERE `payment_status_id` IN (3,4)");
                                                            while ($sd = $statusQ->fetch_assoc()): ?>
                                                                <option value="<?= $sd['payment_status_id'] ?>" <?= $sd['payment_status_id'] == $coursepaymentData['payment_status_payment_status_id'] ? 'selected' : '' ?>><?= $sd['payment_status'] ?></option>
                                                            <?php endwhile; ?>
                                                        </select>
                                                        <span id="status-selector-feedback" class="text-dark-red text-12"></span>
                                                    </div>

                                                    <div class="col-12"></div>

                                                    <div class="col-12 col-lg-3 d-flex flex-column">
                                                        <label class="text-14 main-text">Discount Given Amount <span class="currency-label-badge" id="discount-currency-label"><?= htmlspecialchars($paymentData['currency_code']) ?></span></label>
                                                        <input type="number" class="form-control custom-input mt-2" id="discount-amount" value="0" step="0.01" min="0">
                                                        <div class="text-12 text-danger" id="discount-amount-feedback"></div>
                                                        <span class="convert-hint" id="discount-lkr-hint">≈ LKR 0.00</span>
                                                        <span class="convert-hint d-none" id="discount-base-hint">= <?= htmlspecialchars($paymentData['currency_code']) ?> 0.00</span>
                                                    </div>

                                                    <div class="col-12 col-lg-3 d-flex flex-column">
                                                        <label class="text-14 main-text">Penalty Added Amount <span class="currency-label-badge" id="penalty-currency-label"><?= htmlspecialchars($paymentData['currency_code']) ?></span></label>
                                                        <input type="number" class="form-control custom-input mt-2" id="penalty-amount" value="0" step="0.01" min="0">
                                                        <div class="text-12 text-danger" id="penalty-amount-feedback"></div>
                                                        <span class="convert-hint" id="penalty-lkr-hint">≈ LKR 0.00</span>
                                                        <span class="convert-hint d-none" id="penalty-base-hint">= <?= htmlspecialchars($paymentData['currency_code']) ?> 0.00</span>
                                                    </div>

                                                    <div class="col-12 col-lg-3 d-flex flex-column">
                                                        <label class="text-14 main-text">Balance (Next Installment) <span class="currency-label-badge" id="balance-currency-label"><?= htmlspecialchars($paymentData['currency_code']) ?></span></label>
                                                        <input type="text" class="form-control custom-input mt-2" id="balance-amount" disabled value="<?= number_format($balance, 2) ?>">
                                                        <div class="text-12 text-danger" id="balance-amount-feedback"></div>
                                                        <span class="convert-hint" id="balance-lkr-hint">≈ LKR <?= number_format($balanceLKR, 2) ?></span>
                                                        <span class="convert-hint d-none" id="balance-base-hint">= <?= htmlspecialchars($paymentData['currency_code']) ?> <?= number_format($balance, 2) ?></span>
                                                        <span class="text-12 sub-text mt-1" id="balance-breakdown-hint">Base <?= number_format($payableAmount - $taxOnPayable, 2) ?> + Tax <?= number_format($taxOnPayable, 2) ?></span>
                                                    </div>

                                                    <div class="col-12 col-lg-3 d-flex flex-column">
                                                        <label class="text-14 main-text">Payment Method</label>
                                                        <select name="payment_method" class="form-select custom-select mt-2" id="payment-method">
                                                            <option value="0">- Select -</option>
                                                            <?php
                                                            $methods = Database::search("SELECT * FROM `payment_method`");
                                                            while ($method = $methods->fetch_assoc()) { ?>
                                                                <option value="<?= $method["payment_method_id"] ?>" <?= $method["payment_method_id"] == $coursepaymentData['payment_method_payment_method_id'] ? 'selected' : '' ?>><?= $method["payment_method"] ?></option>
                                                            <?php } ?>
                                                        </select>
                                                        <div class="text-12 text-danger" id="payment-method-feedback"></div>
                                                    </div>

                                                    <div class="col-12 col-lg-3 d-flex flex-column">
                                                        <label class="text-14 main-text">Paid Amount (Tax-Inclusive) <span class="currency-label-badge" id="paid-currency-label"><?= htmlspecialchars($paymentData['currency_code']) ?></span></label>
                                                        <input type="number" class="form-control custom-input mt-2" id="paid-amount" step="0.01" min="0" placeholder="Amount including tax" value="<?= $coursepaymentData['paid_amount'] ?>">
                                                        <div class="text-12 text-danger" id="paid-amount-feedback"></div>
                                                        <span class="convert-hint" id="paid-lkr-hint">≈ LKR 0.00</span>
                                                        <span class="convert-hint d-none" id="paid-base-hint">= <?= htmlspecialchars($paymentData['currency_code']) ?> 0.00</span>
                                                        <span class="text-12 sub-text mt-1" style="color:#d97706;">Includes tax automatically</span>
                                                    </div>

                                                    <div class="col-12 col-lg-3 d-flex flex-column">
                                                        <label class="text-14 main-text">Paid Date</label>
                                                        <input type="date" class="form-control custom-input mt-2" id="paid-date" value="<?= date("Y-m-d") ?>" <?= $coursepaymentData['date_paid'] ?>>
                                                        <div class="text-12 text-danger" id="paid-date-feedback"></div>
                                                        <?php if (!$isBaseCurrencyLKR): ?>
                                                            <span class="text-12 sub-text mt-1" id="rate-status">Rate loaded for today</span>
                                                        <?php endif; ?>
                                                    </div>

                                                    <div class="col-12 col-lg-3 d-flex flex-column">
                                                        <label class="text-14 main-text">Tax Amount <span class="currency-label-badge" style="background:rgba(245,158,11,.12);color:#d97706;border-color:rgba(245,158,11,.3);">Auto-Calculated</span></label>
                                                        <input type="text" class="form-control custom-input mt-2" id="tax-amount-display-input" value="0.00" disabled tabindex="-1" style="background:rgba(245,158,11,.06);border-color:rgba(245,158,11,.3);color:#d97706;">
                                                        <div class="text-12 text-danger" id="tax-amount-feedback"></div>
                                                        <span class="convert-hint" id="tax-amount-lkr-hint">≈ LKR 0.00</span>
                                                        <span class="convert-hint d-none" id="tax-amount-base-hint">= <?= htmlspecialchars($paymentData['currency_code']) ?> 0.00</span>
                                                        <span class="text-12 sub-text mt-1" style="color:#d97706;">Latest: <?= number_format($latestTaxRate, 2) ?>%</span>
                                                    </div>

                                                    <?php if (!$isBaseCurrencyLKR): ?>
                                                        <div class="col-12 col-lg-3 d-flex flex-column">
                                                            <label class="text-14 main-text d-flex align-items-center gap-2">Custom Exchange Rate <span class="currency-label-badge">1 <?= htmlspecialchars($paymentData['currency_code']) ?> = ? LKR</span></label>
                                                            <input type="number" class="form-control custom-input mt-2" id="custom-exchange-rate" value="<?= number_format($exchangeRateValue, 2, '.', '') ?>" step="0.0001" min="0.0001" placeholder="e.g. 300.0000">
                                                            <span class="text-12 sub-text mt-1">Override auto-fetched rate if needed</span>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="col-lg-3"></div>
                                                    <?php endif; ?>

                                                    <div class="col-12" id="overpay-panel-wrapper" style="display:none;">
                                                        <div class="overpay-panel">
                                                            <div class="overpay-panel-title">
                                                                <iconify-icon icon="mdi:arrow-right-circle-outline" width="16" height="16"></iconify-icon>
                                                                Overpayment — Will Be Applied to Next Installment
                                                            </div>
                                                            <div class="overpay-explain">
                                                                The paid amount covers this installment in full. The extra amount shown below is automatically carried forward as credit and will reduce the student's next installment balance.
                                                            </div>
                                                            <div class="overpay-grid">
                                                                <div class="overpay-item">
                                                                    <span class="overpay-label">Current Installment Payable</span>
                                                                    <span class="overpay-value" id="overpay-current">—</span>
                                                                    <span class="overpay-sub">Will be fully settled</span>
                                                                </div>
                                                                <div class="overpay-item">
                                                                    <span class="overpay-label">Overpayment Amount</span>
                                                                    <span class="overpay-value" id="overpay-amount">—</span>
                                                                    <span class="overpay-sub">Credited to next installment</span>
                                                                </div>
                                                                <div class="overpay-item">
                                                                    <span class="overpay-label">Next Installment Breakdown</span>
                                                                    <span class="overpay-value" id="overpay-next-breakdown">—</span>
                                                                    <span class="overpay-sub" id="overpay-next-sub">—</span>
                                                                    <span class="overpay-total" id="overpay-next-total">—</span>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="col-12 d-flex flex-column">
                                                        <label class="text-14 main-text">Note</label>
                                                        <textarea class="form-control custom-input mt-2" id="note" rows="4"></textarea>
                                                        <span id="note-feedback" class="text-dark-red text-12"></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>


                                        <div class="modal-footer" style="border-top:1px solid var(--line-color);">
                                            <button type="button" class="col-12 col-md-4 custom-btn"
                                                onclick="submitUpdatePaymentStatus(this, <?= $coursepaymentData['course_payments_course_payments_id'] ?>,<?= $coursepaymentData['course_payment_has_payment_id'] ?>,getCurrentCurrencyData())">
                                                Update Payment Status
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Receipt Modal ───────────────────────────────────────────────── -->
                            <?php if ($coursepaymentData['payment_status_payment_status_id'] == 3): ?>
                                <div class="modal fade" id="receiptPreviewModal" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-xl modal-dialog-centered">
                                        <div class="modal-content component-theme">
                                            <div class="modal-header" style="border-bottom:1px solid var(--line-color);flex-shrink:0;">
                                                <span class="text-16 main-text fw-semibold flex-grow-1">
                                                    <iconify-icon icon="mdi:receipt-text-outline" width="18" style="vertical-align:-3px;margin-right:6px;"></iconify-icon>
                                                    Payment Receipt
                                                </span>
                                                <div class="d-flex gap-2 me-3">
                                                    <button class="table-btn table-btn-primary py-2 px-3" onclick="downloadReceipt()">
                                                        <iconify-icon icon="mdi:download" width="14" style="vertical-align:-2px;margin-right:4px;"></iconify-icon>Download PDF
                                                    </button>
                                                    <button class="table-btn table-btn-success py-2 px-3" id="send-invoice-btn">
                                                        <iconify-icon icon="mdi:email-send-outline" width="14" style="vertical-align:-2px;margin-right:4px;"></iconify-icon>Send Invoice
                                                    </button>
                                                </div>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body p-0" id="receipt-modal-body" style="overflow:hidden;max-height:85vh;">
                                                <div style="text-align:center;padding:60px 20px;">
                                                    <div style="width:32px;height:32px;border:3px solid #e2e8f0;border-top-color:#1e3a5f;border-radius:50%;animation:rcptSpin .7s linear infinite;margin:0 auto 14px;"></div>
                                                    <span style="font-size:12px;color:#94a3b8;">Loading receipt…</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>


                            <div class="modal fade" id="sendReceiptStatusModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
                                <div class="modal-dialog modal-dialog-centered" style="max-width:380px;">
                                    <div class="modal-content" style="border-radius:12px;overflow:hidden;">
                                        <div class="modal-body" style="padding:28px 26px 22px;">
                                            <div style="text-align:center;margin-bottom:18px;">
                                                <div id="srs-header-icon" style="width:48px;height:48px;border-radius:50%;background:var(--light-theme);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;transition:background .25s ease;">
                                                    <iconify-icon icon="mdi:email-fast-outline" width="24" height="24" style="color:var(--dark-theme);"></iconify-icon>
                                                </div>
                                                <div id="srs-title" style="font-size:15px;font-weight:700;color:var(--dark-theme);">Sending Receipt</div>
                                                <div id="srs-subtext" style="font-size:11px;color:#6b7280;margin-top:2px;">Please wait, this only takes a moment.</div>
                                            </div>

                                            <div id="srs-steps" style="display:flex;flex-direction:column;gap:14px;"></div>

                                            <div id="srs-footer" style="display:none;text-align:center;margin-top:22px;">
                                                <button type="button" class="custom-btn px-4" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <style>
                                .srs-spin {
                                    animation: srsSpin 0.8s linear infinite;
                                    display: inline-block;
                                }

                                @keyframes srsSpin {
                                    to {
                                        transform: rotate(360deg);
                                    }
                                }
                            </style>

                            <script src="./assets/plugins/bootstrap/js/bootstrap.bundle.min.js?v=<?= Project::getVersion() ?>"></script>
                            <script src="./assets/plugins/jquery/jquery-3.7.1.min.js"></script>
                            <script src="./assets/js/script.js?v=<?= Project::getVersion() ?>"></script>
                            <script src="./assets/js/functions.js?v=<?= Project::getVersion() ?>"></script>

                            <script>
                                const LATEST_TAX_RATE = <?= (float)$latestTaxRate ?>;
                                const COURSE_AMOUNT = <?= (float)$paymentData['amount'] ?>;
                                const EXISTING_TOTAL_DISCOUNT = <?= (float)$totalDiscount ?>;
                                const EXISTING_TOTAL_PENALTY = <?= (float)$totalPenaltyCC ?>;
                                const EXISTING_TOTAL_PENALTY_LKR = <?= (float)$totalPenaltyLKR ?>; // ground truth — always LKR, never reconvert
                                const EXISTING_PAID_TOTAL = <?= (float)$convertedPaidTotal ?>;
                                const baseCurrencyId = <?= (int)$paymentData['currency_id'] ?>;
                                const lkrCurrencyId = <?= (int)$lkrCurrencyId ?>;
                                const currencyCode = "<?= htmlspecialchars($paymentData['currency_code']) ?>";
                                const isBaseCurrencyLKR = <?= $isBaseCurrencyLKR ? 'true' : 'false' ?>;

                                const OVERPAY_EPSILON = 0.005;

                                let exchangeRate = <?= $isBaseCurrencyLKR ? 'null' : (float)$exchangeRateValue ?>;
                                let exchangeRateId = <?= $exchangeRateId ? (int)$exchangeRateId : 'null' ?>;
                                let modalCurrentMode = 'base';

                                const discountInput = document.getElementById('discount-amount');
                                const penaltyInput = document.getElementById('penalty-amount');
                                const balanceInput = document.getElementById('balance-amount');
                                const paidInput = document.getElementById('paid-amount');
                                const taxAmountDisplay = document.getElementById('tax-amount-display-input');
                                const paidDateInput = document.getElementById('paid-date');
                                const customRateInput = document.getElementById('custom-exchange-rate');
                                const rateStatusEl = document.getElementById('rate-status');
                                const balBreakdownEl = document.getElementById('balance-breakdown-hint');

                                function fmt(n) {
                                    return parseFloat(n || 0).toLocaleString('en-US', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }

                                function round2(n) {
                                    return Math.round((n + Number.EPSILON) * 100) / 100;
                                }

                                // Split a TAX-INCLUSIVE amount into its base + tax portions.
                                // Used only for the "Tax Amount" display field (how much of THIS
                                // payment is tax) — never feeds into the balance calculation.
                                function extractBaseAndTax(taxInclusiveAmount, taxRate) {
                                    if (taxRate <= 0 || taxInclusiveAmount <= 0) {
                                        return {
                                            base: taxInclusiveAmount,
                                            tax: 0
                                        };
                                    }
                                    const taxRateDecimal = taxRate / 100;
                                    const basePortion = 1 - taxRateDecimal;
                                    if (basePortion <= 0) return {
                                        base: 0,
                                        tax: taxInclusiveAmount
                                    };
                                    return {
                                        base: round2(taxInclusiveAmount * basePortion),
                                        tax: round2(taxInclusiveAmount * taxRateDecimal)
                                    };
                                }

                                // ════════════════════════════════════════════════════════════════
                                // SINGLE SOURCE OF TRUTH for payable / balance.
                                //
                                // Mirrors the PHP page-load calculation exactly:
                                //   netBase       = max(0, amount - totalDiscount + totalPenalty)
                                //   payableAmount = round(netBase / (1 - taxRate/100), 2)   ← rounds ONCE
                                //   balance       = round(payableAmount - totalPaid, 2)      ← rounds ONCE
                                //
                                // All inputs/outputs are in the COURSE (base) currency. Both the
                                // top LKR summary panel and the "Balance (Next Installment)" field
                                // call THIS function — nothing else computes a balance independently,
                                // so they can never disagree again.
                                // ════════════════════════════════════════════════════════════════
                                function computeBalance(totalDiscountBase, totalPenaltyBase, totalPaidBase) {
                                    const netBase = Math.max(0, COURSE_AMOUNT - totalDiscountBase + totalPenaltyBase);
                                    const taxRateDecimal = LATEST_TAX_RATE / 100;
                                    const basePortion = 1 - taxRateDecimal;
                                    const payableAmount = basePortion > 0 ? round2(netBase / basePortion) : netBase;
                                    const taxOnPayable = round2(payableAmount - netBase);
                                    const rawBalance = round2(payableAmount - totalPaidBase);

                                    return {
                                        netBase,
                                        payableAmount,
                                        taxOnPayable,
                                        balance: Math.max(0, rawBalance),
                                        overpay: rawBalance < -OVERPAY_EPSILON ? round2(Math.abs(rawBalance)) : 0
                                    };
                                }

                                function setHint(id, label, value, isApprox = true) {
                                    const el = document.getElementById(id);
                                    if (!el) return;
                                    el.textContent = (isApprox ? '≈ ' : '= ') + label + ' ' + fmt(value);
                                }

                                function setRateStatus(type, msg) {
                                    if (!rateStatusEl) return;
                                    const cls = {
                                        loading: 'sub-text',
                                        success: 'text-dark-green',
                                        warning: 'text-warning',
                                        error: 'text-dark-red',
                                        info: 'text-primary'
                                    };
                                    rateStatusEl.className = 'text-12 mt-1 ' + (cls[type] || 'sub-text');
                                    rateStatusEl.textContent = msg;
                                }

                                // Top "LKR equivalent summary" panel — uses computeBalance() with
                                // the EXISTING (already-saved) totals only, so it reflects the
                                // current saved state, not whatever the user is mid-typing below.
                                function updateLkrSummaryPanel() {
                                    if (isBaseCurrencyLKR || !exchangeRate) return;

                                    const result = computeBalance(EXISTING_TOTAL_DISCOUNT, EXISTING_TOTAL_PENALTY, EXISTING_PAID_TOTAL);

                                    const setVal = (idPrefix, baseCurrencyValue) => {
                                        const valEl = document.getElementById(idPrefix + '-val');
                                        if (valEl) valEl.textContent = 'LKR ' + fmt(baseCurrencyValue * exchangeRate);
                                        const subEl = document.getElementById(idPrefix + '-sub');
                                        if (subEl) subEl.textContent = currencyCode + ' ' + fmt(baseCurrencyValue);
                                    };

                                    setVal('lkr-amount', COURSE_AMOUNT);
                                    setVal('lkr-discount', EXISTING_TOTAL_DISCOUNT);
                                    setVal('lkr-tax', result.taxOnPayable);
                                    setVal('lkr-payable', result.payableAmount);
                                    setVal('lkr-paid', EXISTING_PAID_TOTAL);

                                    const balLkrEl = document.getElementById('lkr-balance-display');
                                    if (balLkrEl) balLkrEl.textContent = 'LKR ' + fmt(result.balance * exchangeRate);
                                    const balSubEl = document.getElementById('lkr-balance-sub');
                                    if (balSubEl) balSubEl.textContent = currencyCode + ' ' + fmt(result.balance);
                                    // Penalty is stored in LKR — it must stay fixed, never re-derived via CC × exchangeRate
                                    const penValEl = document.getElementById('lkr-penalty-val');
                                    if (penValEl) penValEl.textContent = 'LKR ' + fmt(EXISTING_TOTAL_PENALTY_LKR);

                                    const penSubEl = document.getElementById('lkr-penalty-sub');
                                    if (penSubEl) penSubEl.textContent = currencyCode + ' ' +
                                        fmt(exchangeRate > 0 ? EXISTING_TOTAL_PENALTY_LKR / exchangeRate : EXISTING_TOTAL_PENALTY);
                                }

                                function showOverpaymentMessage(currentPayable, paidAmount, overpayTotal, overpayBase, overpayTax) {
                                    const displayLabel = (!isBaseCurrencyLKR && modalCurrentMode === 'lkr') ? 'LKR' : currencyCode;
                                    const wrapper = document.getElementById('overpay-panel-wrapper');
                                    if (!wrapper) return;
                                    document.getElementById('overpay-current').textContent = fmt(currentPayable) + ' ' + displayLabel;
                                    document.getElementById('overpay-amount').textContent = fmt(overpayTotal) + ' ' + displayLabel;
                                    document.getElementById('overpay-next-breakdown').textContent = fmt(overpayTotal) + ' ' + displayLabel;
                                    document.getElementById('overpay-next-sub').textContent =
                                        displayLabel + ' ' + fmt(overpayBase) + ' course fee' +
                                        ' + ' + displayLabel + ' ' + fmt(overpayTax) +
                                        ' tax (' + LATEST_TAX_RATE.toFixed(2) + '%)';
                                    const totalEl = document.getElementById('overpay-next-total');
                                    if (totalEl) totalEl.textContent = 'Total: ' + displayLabel + ' ' + fmt(overpayBase + overpayTax);
                                    wrapper.style.display = 'block';
                                }

                                function removeOverpaymentMessage() {
                                    const wrapper = document.getElementById('overpay-panel-wrapper');
                                    if (wrapper) wrapper.style.display = 'none';
                                }

                                // ════════════════════════════════════════════════════════════════
                                // Single recalculate() for BOTH single-currency and multi-currency
                                // courses. toBase()/toDisplay() are the only conversion points —
                                // everything in between happens in the course (base) currency,
                                // exactly like the PHP calculation. For an LKR course, toBase and
                                // toDisplay are no-ops, so this naturally collapses to the old
                                // "single currency" behaviour without a separate code path.
                                // ════════════════════════════════════════════════════════════════
                                function recalculate() {
                                    const rawDiscount = parseFloat(discountInput.value) || 0;
                                    const rawPenalty = parseFloat(penaltyInput.value) || 0;
                                    const rawPaid = parseFloat(paidInput?.value) || 0;

                                    const inLkrMode = !isBaseCurrencyLKR && modalCurrentMode === 'lkr' && exchangeRate;
                                    const toBase = v => inLkrMode ? v / exchangeRate : v;
                                    const toDisplay = v => inLkrMode ? v * exchangeRate : v;

                                    const discountBase = toBase(rawDiscount);
                                    const penaltyBase = toBase(rawPenalty);
                                    const paidBase = toBase(rawPaid);

                                    const totalDiscountBase = EXISTING_TOTAL_DISCOUNT + discountBase;
                                    const totalPenaltyBase = EXISTING_TOTAL_PENALTY + penaltyBase;
                                    const totalPaidBase = EXISTING_PAID_TOTAL + paidBase;

                                    const result = computeBalance(totalDiscountBase, totalPenaltyBase, totalPaidBase);

                                    // Tax on THIS payment (display only)
                                    const paidTaxSplit = extractBaseAndTax(paidBase, LATEST_TAX_RATE);
                                    const paidTaxDisplay = toDisplay(paidTaxSplit.tax);
                                    if (taxAmountDisplay) taxAmountDisplay.value = fmt(paidTaxDisplay);

                                    if (result.overpay > 0) {
                                        balanceInput.value = '0.00';
                                        if (balBreakdownEl) balBreakdownEl.textContent = 'Fully paid — overpayment credited to next installment';

                                        const overpaySplit = extractBaseAndTax(result.overpay, LATEST_TAX_RATE);
                                        showOverpaymentMessage(
                                            toDisplay(result.payableAmount),
                                            rawPaid,
                                            toDisplay(result.overpay),
                                            toDisplay(overpaySplit.base),
                                            toDisplay(overpaySplit.tax)
                                        );
                                        setHint('balance-lkr-hint', 'LKR', 0, false);
                                        setHint('balance-base-hint', currencyCode, 0, false);
                                    } else {
                                        const balDisplay = toDisplay(result.balance);
                                        balanceInput.value = fmt(balDisplay);
                                        removeOverpaymentMessage();

                                        if (balBreakdownEl) {
                                            balBreakdownEl.textContent =
                                                'Base ' + fmt(toDisplay(result.netBase)) + ' + Tax ' + fmt(toDisplay(result.taxOnPayable));
                                        }

                                        if (modalCurrentMode === 'lkr') {
                                            setHint('balance-base-hint', currencyCode, result.balance, false);
                                            setHint('balance-lkr-hint', 'LKR', balDisplay, false);
                                        } else {
                                            setHint('balance-lkr-hint', 'LKR', toDisplay(result.balance));
                                            setHint('balance-base-hint', currencyCode, result.balance, false);
                                        }
                                    }

                                    // Discount / penalty / paid hint pairs
                                    if (modalCurrentMode === 'lkr') {
                                        setHint('discount-base-hint', currencyCode, discountBase, false);
                                        setHint('penalty-base-hint', currencyCode, penaltyBase, false);
                                        setHint('paid-base-hint', currencyCode, paidBase, false);
                                        setHint('tax-amount-base-hint', currencyCode, paidTaxSplit.tax, false);
                                        setHint('discount-lkr-hint', 'LKR', rawDiscount, false);
                                        setHint('penalty-lkr-hint', 'LKR', rawPenalty, false);
                                        setHint('paid-lkr-hint', 'LKR', rawPaid, false);
                                        setHint('tax-amount-lkr-hint', 'LKR', paidTaxDisplay, false);
                                    } else {
                                        setHint('discount-lkr-hint', 'LKR', toDisplay(rawDiscount));
                                        setHint('penalty-lkr-hint', 'LKR', toDisplay(rawPenalty));
                                        setHint('paid-lkr-hint', 'LKR', toDisplay(rawPaid));
                                        setHint('tax-amount-lkr-hint', 'LKR', paidTaxDisplay);
                                        setHint('discount-base-hint', currencyCode, rawDiscount, false);
                                        setHint('penalty-base-hint', currencyCode, rawPenalty, false);
                                        setHint('paid-base-hint', currencyCode, paidBase, false);
                                        setHint('tax-amount-base-hint', currencyCode, paidTaxSplit.tax, false);
                                    }
                                }

                                function switchCurrency(mode) {
                                    if (isBaseCurrencyLKR) return;
                                    modalCurrentMode = mode;
                                    document.getElementById('btn-base-currency').classList.toggle('active', mode === 'base');
                                    document.getElementById('btn-lkr-currency').classList.toggle('active', mode === 'lkr');
                                    const lkrPanel = document.getElementById('lkr-summary-panel');
                                    if (lkrPanel) lkrPanel.style.display = mode === 'lkr' ? 'block' : 'none';
                                    const label = mode === 'lkr' ? 'LKR' : currencyCode;
                                    ['discount', 'penalty', 'balance', 'paid', 'tax-amount'].forEach(id => {
                                        const el = document.getElementById(id + '-currency-label');
                                        if (el) el.textContent = label;
                                    });
                                    document.querySelectorAll('[id$="-lkr-hint"]').forEach(el => el.classList.toggle('d-none', mode === 'lkr'));
                                    document.querySelectorAll('[id$="-base-hint"]').forEach(el => el.classList.toggle('d-none', mode === 'base'));
                                    if (exchangeRate && !isBaseCurrencyLKR) {
                                        [discountInput, penaltyInput, paidInput].forEach(el => {
                                            if (!el) return;
                                            const v = parseFloat(el.value) || 0;
                                            if (!v) return;
                                            el.value = mode === 'lkr' ? (v * exchangeRate).toFixed(2) : (v / exchangeRate).toFixed(2);
                                        });
                                    }
                                    recalculate();
                                }

                                function onPaidDateChange() {
                                    if (isBaseCurrencyLKR || !paidDateInput.value) return;
                                    setRateStatus('loading', 'Fetching exchange rate for selected date...');
                                    fetch(`api/getExchangeRate.php?base=${baseCurrencyId}&target=${lkrCurrencyId}&date=${paidDateInput.value}`)
                                        .then(r => r.json())
                                        .then(data => {
                                            if (data.rate) {
                                                exchangeRate = parseFloat(data.rate);
                                                exchangeRateId = data.currency_exchange_rate_id;
                                                if (customRateInput) customRateInput.value = exchangeRate.toFixed(2);
                                                const rb = document.getElementById('live-rate-text');
                                                if (rb) rb.textContent = `1 ${currencyCode} = LKR ${exchangeRate.toFixed(2)} (${data.effective_date})`;
                                                const pr = document.getElementById('panel-rate-display');
                                                if (pr) pr.textContent = exchangeRate.toFixed(2);
                                                setRateStatus('success', `Rate updated: 1 ${currencyCode} = LKR ${exchangeRate.toFixed(2)}`);
                                            } else {
                                                setRateStatus('warning', `No rate found. Using last known (${exchangeRate?.toFixed(2)}).`);
                                            }
                                            updateLkrSummaryPanel();
                                            recalculate();
                                        })
                                        .catch(() => {
                                            setRateStatus('error', 'Could not fetch rate.');
                                            updateLkrSummaryPanel();
                                            recalculate();
                                        });
                                }

                                function onCustomRateChange() {
                                    const val = parseFloat(customRateInput.value);
                                    if (!isNaN(val) && val > 0) {
                                        exchangeRate = val;
                                        exchangeRateId = null;
                                        const pr = document.getElementById('panel-rate-display');
                                        if (pr) pr.textContent = val.toFixed(2);
                                        const rb = document.getElementById('live-rate-text');
                                        if (rb) rb.textContent = `1 ${currencyCode} = LKR ${val.toFixed(2)} (custom)`;
                                        setRateStatus('info', `Custom rate: 1 ${currencyCode} = LKR ${val.toFixed(2)}`);
                                        updateLkrSummaryPanel();
                                        recalculate();
                                    }
                                }

                                function getCurrentCurrencyData() {
                                    const customRateEl = document.getElementById('custom-exchange-rate');
                                    const customRateVal = customRateEl ? parseFloat(customRateEl.value) : NaN;
                                    const isCustomRate = !isNaN(customRateVal) && customRateVal > 0 && customRateVal !== exchangeRate;
                                    const finalRate = (customRateEl && !isNaN(customRateVal) && customRateVal > 0) ? customRateVal : exchangeRate;
                                    const finalRateId = isCustomRate ? null : exchangeRateId;

                                    const rawPaid = parseFloat(paidInput?.value) || 0;
                                    const toBase = v => (!isBaseCurrencyLKR && modalCurrentMode === 'lkr' && finalRate) ? v / finalRate : v;
                                    const paidBase = toBase(rawPaid);
                                    const calc = extractBaseAndTax(paidBase, LATEST_TAX_RATE);

                                    return {
                                        mode: modalCurrentMode,
                                        baseCurrencyId: baseCurrencyId,
                                        exchangeRate: finalRate,
                                        isLKR: isBaseCurrencyLKR || modalCurrentMode === 'lkr',
                                        selectedCurrencyId: (isBaseCurrencyLKR || modalCurrentMode === 'base') ? baseCurrencyId : lkrCurrencyId,
                                        exchangeRateId: (isBaseCurrencyLKR || modalCurrentMode === 'base') ? null : finalRateId,
                                        isCustomRate: isCustomRate,
                                        customRateValue: isCustomRate ? customRateVal : null,
                                        taxRate: LATEST_TAX_RATE,
                                        taxAmount: calc.tax,
                                    };
                                }

                                discountInput.addEventListener('input', recalculate);
                                penaltyInput.addEventListener('input', recalculate);
                                if (paidInput) paidInput.addEventListener('input', recalculate);
                                if (paidDateInput) paidDateInput.addEventListener('change', onPaidDateChange);
                                if (customRateInput) customRateInput.addEventListener('input', onCustomRateChange);

                                updateLkrSummaryPanel();
                                recalculate();
                                console.log('✓ Payment Tax Calculation v11 (unified computeBalance — single source of truth for top panel + balance field)');
                            </script>

                            <script src="./assets/js/paymentReceiptRenderer.js?v=<?= Project::getVersion() ?>"></script>

                        </body>

                        </html>
<?php
                    }
                }
            }
        }
    }
}
?>