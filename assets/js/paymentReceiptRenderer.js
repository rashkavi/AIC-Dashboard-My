/* ════════════════════════════════════════════════════════════════════════════
   paymentReceiptRenderer.js  — v12 CURRENCY FIX

   NEW IN THIS VERSION (on top of v11):
   1. Discount column shows the PAID currency (e.g. LKR stays LKR) using
      p.discount_amount + (p.discount_currency || p.paid_currency).
   2. Penalty column is ALWAYS LKR (p.penalty_lkr).
   3. Summary box: Penalty is LKR (grand_total_penalty_lkr); Discount is the
      converted course-currency total (grand_total_discount).

   REQUIRES getReceiptData.php v7 (discount_amount in paid currency,
   penalty_lkr, grand_total_penalty_lkr).

   CARRIED FROM v11 / v10 / v9:
   - Send-status modal controller, sendReceiptEmail() step flow,
     buildReceiptHTML(data) as the single source of truth for the on-screen
     receipt and the emailed PDF.
════════════════════════════════════════════════════════════════════════════ */

var mGet = function (id) { return document.getElementById(id); };

function fmt(n, dp) {
    dp = (dp === undefined) ? 2 : dp;
    return parseFloat(n || 0).toLocaleString('en-US', {
        minimumFractionDigits: dp, maximumFractionDigits: dp
    });
}
function esc(s) {
    return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

/* Formats a MySQL datetime ("2026-08-16 09:00:00") as "2026-08-16 09:00" */
function fmtDateTime(s) {
    if (!s) return '—';
    var parts = String(s).split(/[ T]/);
    var datePart = parts[0] || '';
    var timePart = parts[1] ? parts[1].substring(0, 5) : '';
    return timePart ? (datePart + ' ' + timePart) : datePart;
}

var currentReceiptId = null;

function openReceiptModal(receiptId) {
    currentReceiptId = receiptId;
    var body = mGet('receipt-modal-body');
    if (body) {
        body.style.overflow = 'hidden';
        body.innerHTML = spinner();
    }

    $("#send-invoice-btn").off("click").on("click", function () { sendReceiptEmail(receiptId); });
    var modal = new bootstrap.Modal(document.getElementById('receiptPreviewModal'));
    modal.show();
    fetch('api/getReceiptData.php?receipt_id=' + receiptId)
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.error) {
                if (body) body.innerHTML = errMsg(data.error);
                return;
            }
            renderReceiptHTML(data);
        })
        .catch(function () { if (body) body.innerHTML = errMsg('Failed to load receipt.'); });
}

function downloadReceipt() {
    var printArea = mGet('receipt-print-area');
    if (!printArea) return;
    var win = window.open('', '_blank');
    win.document.write(
        '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Payment Receipt</title>' +
        '<style>*{box-sizing:border-box;margin:0;padding:0;}' +
        'body{font-family:"Segoe UI",system-ui,sans-serif;font-size:11px;background:#fff;color:#111827;' +
        'padding-bottom:64px;}' + /* leave room so content never sits under the fixed footer block */
        /* ── Force the payment table to fit the printed page width — no clipping, no scroll ── */
        '.rcpt-scroll-wrap{overflow:visible!important;}' +
        '.rcpt-payment-table{width:100%!important;min-width:0!important;table-layout:fixed!important;}' +
        '.rcpt-payment-table th{font-size:7px!important;padding:5px 3px!important;word-break:break-word;white-space:normal!important;}' +
        '.rcpt-payment-table td{font-size:7.5px!important;padding:5px 3px!important;word-break:break-word;white-space:normal!important;}' +
        '.rcpt-payment-table td div{font-size:6.5px!important;}' +
        /* ── Whole footer block (disclaimer + receipt/generated info + copyright) pinned to page bottom ── */
        '.rcpt-page-footer{position:fixed;bottom:0;left:0;right:0;background:#fff;}' +
        '@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}' +
        '@page{size:A4 portrait;margin:8mm 10mm;}}</style></head><body>' +
        printArea.outerHTML +
        '<script>window.onload=function(){setTimeout(function(){window.print();},400);' +
        'window.onafterprint=function(){window.close();};};<\/script></body></html>'
    );
    win.document.close();
}

/* ════════════════════════════════════════════════════════════════════════════
   HELPERS
════════════════════════════════════════════════════════════════════════════ */
function spinner() {
    return '<div style="text-align:center;padding:80px 20px;">' +
        '<div style="width:32px;height:32px;border:3px solid #e2e8f0;border-top-color:#1e3a5f;border-radius:50%;animation:rcptSpin .7s linear infinite;margin:0 auto 14px;"></div>' +
        '<span style="font-size:12px;color:#94a3b8;">Loading receipt…</span></div>' +
        '<style>@keyframes rcptSpin{to{transform:rotate(360deg);}}</style>';
}
function errMsg(msg) {
    return '<div style="text-align:center;padding:60px;color:#ef4444;font-size:12px;">' + esc(msg) + '</div>';
}
function dash() { return '<span style="color:#d1d5db;">—</span>'; }
function subNote(t) { return '<div style="font-size:9px;color:#6b7280;margin-top:2px;">' + t + '</div>'; }

/* ════════════════════════════════════════════════════════════════════════════
   MODAL WRAPPER — thin, just injects the built markup into the modal body.
════════════════════════════════════════════════════════════════════════════ */
function renderReceiptHTML(data) {
    var body = mGet('receipt-modal-body');
    var html = buildReceiptHTML(data);
    if (body) {
        body.style.overflow = 'hidden';
        body.innerHTML = html;
    }
}

/* ════════════════════════════════════════════════════════════════════════════
   BUILDER — returns the receipt markup as a string. This is the single
   source of truth used by BOTH the on-screen modal and the emailed PDF.
════════════════════════════════════════════════════════════════════════════ */
function buildReceiptHTML(data) {
    var cc = (data.course_currency || 'LKR').trim();
    var recDate = data.date_added ? data.date_added.substring(0, 10) : '';
    var recNum = esc(data.receipt_number || '');

    /* ── Payment table rows ─────────────────────────────────────────────── */
    var payRows = '';
    var penaltyLkrSum = 0; // fallback if API doesn't send grand_total_penalty_lkr

    (data.payments || []).forEach(function (p, idx) {
        var paidCurr = (p.paid_currency || cc).trim();
        var isForeign = (paidCurr !== cc);
        var exRate = parseFloat(p.exchange_rate || 0);

        var paidMain, paidSub = '';
        if (isForeign && exRate > 0) {
            paidMain = paidCurr + ' ' + fmt(p.paid_amount);
            paidSub = subNote('= ' + cc + ' ' + fmt(p.paid_in_course) +
                '&nbsp;(@ 1 ' + cc + ' = ' + paidCurr + ' ' + fmt(exRate) + ')');
        } else {
            paidMain = cc + ' ' + fmt(p.paid_in_course);
        }

        var taxRateDisp = parseFloat(p.tax_rate || 0) > 0 ? fmt(p.tax_rate) + '%' : dash();
        var taxMain = dash(), taxSub = '';
        if (parseFloat(p.tax_course || p.tax_amount || 0) > 0) {
            taxMain = cc + ' ' + fmt(p.tax_course || p.tax_amount);
            if (isForeign && parseFloat(p.tax_amount || 0) > 0)
                taxSub = subNote(paidCurr + ' ' + fmt(p.tax_amount));
        }

        /* v12: Discount is shown in the PAID currency (LKR stays LKR).
           For foreign payments, show the course-currency equivalent underneath. */
        var discCurr = (p.discount_currency || paidCurr).trim();
        var discVal = parseFloat(p.discount_amount || 0);
        var discMain = dash();
        if (discVal > 0) {
            discMain = discCurr + ' ' + fmt(discVal);
            if (discCurr !== cc && parseFloat(p.discount_course || 0) > 0) {
                discMain += subNote('= ' + cc + ' ' + fmt(p.discount_course));
            }
        }

        /* v12: Penalty is ALWAYS LKR */
        var penLkr = parseFloat(p.penalty_lkr || 0);
        penaltyLkrSum += penLkr;
        var penMain = penLkr > 0 ? 'LKR ' + fmt(penLkr) : dash();

        var bg = idx % 2 === 0 ? '#ffffff' : '#f8fafc';

        /* per-installment balance cell */
        var balMain;
        var balVal = parseFloat(p.installment_balance);
        if (isNaN(balVal)) {
            balMain = dash();
        } else if (balVal > 0.005) {
            balMain =
                '<span style="color:#b45309;font-weight:700;">' + cc + ' ' + fmt(balVal) + '</span>' +
                subNote('as of ' + fmtDateTime(p.balance_as_of));
        } else {
            balMain = '<span style="color:#16a34a;font-weight:700;">Fully Paid</span>';
        }

        payRows +=
            '<tr style="background:' + bg + ';">' +
            rTD(idx + 1, 'color:#9ca3af;width:22px;') +
            rTD(esc(p.reference || '—'), 'font-weight:700;color:#1e3a5f;') +
            rTD(esc(p.payment_type || '—'), 'color:#6b7280;') +
            rTD(p.date_paid ? p.date_paid.substring(0, 10) : '—', 'white-space:nowrap;color:#6b7280;') +
            rTD(esc(p.payment_method || '—'), 'color:#6b7280;') +
            rTDR(cc + ' ' + fmt(p.cp_amount)) +
            rTDR(discMain) +
            rTDR(penMain) +
            rTDC(taxRateDisp) +
            rTDR(taxMain + taxSub) +
            rTDR(paidMain + paidSub, 'font-weight:700;color:#111827;') +
            rTDR(balMain) +
            '</tr>';
    });
    if (!payRows) payRows = noDataRow();

    /* ════════════════════════════════════════════════════════════════════
       SUMMARY FORMAT - SHOWS WHAT WAS ACTUALLY PAID
    ════════════════════════════════════════════════════════════════════ */

    var totalPaidGross = data.grand_total_paid || 0;
    var totalTax = data.grand_total_tax || 0;
    var totalDiscount = data.grand_total_discount || 0;           // course currency (converted)
    var totalPenaltyLkr = (data.grand_total_penalty_lkr !== undefined && data.grand_total_penalty_lkr !== null)
        ? data.grand_total_penalty_lkr
        : penaltyLkrSum;                                          // always LKR

    var courseFeeActuallyPaid = Math.max(0, totalPaidGross - totalTax);

    var totalCourseFeeListed = data.grand_total_amount || 0;
    var isPaid = (courseFeeActuallyPaid >= totalCourseFeeListed - 0.005);

    var ROW_STYLE = 'padding:8px 13px;font-size:11px;border-bottom:1px solid #f3f4f6;';
    var LABEL_STYLE = ROW_STYLE + 'color:#374151;font-weight:500;';
    var VALUE_STYLE = ROW_STYLE + 'color:#111827;font-weight:600;text-align:right;';

    function sRow(label, value, labelEx, valueEx) {
        return '<tr>' +
            '<td style="' + LABEL_STYLE + (labelEx || '') + '">' + label + '</td>' +
            '<td style="' + VALUE_STYLE + (valueEx || '') + '">' + value + '</td>' +
            '</tr>';
    }

    var summaryRows =
        sRow('Course Fee', cc + ' ' + fmt(courseFeeActuallyPaid)) +
        sRow('Discount', parseFloat(totalDiscount || 0) > 0 ? cc + ' ' + fmt(totalDiscount) : dash()) +
        sRow('Penalty', parseFloat(totalPenaltyLkr || 0) > 0 ? 'LKR ' + fmt(totalPenaltyLkr) : dash()) +
        sRow('Tax', parseFloat(totalTax || 0) > 0 ? cc + ' ' + fmt(totalTax) : dash());

    var totalRow =
        '<tr style="background:#1e3a5f;border-top:2px solid #1e3a5f;">' +
        '<td style="padding:10px 13px;font-size:12px;font-weight:700;color:#fff;">TOTAL PAID AMOUNT</td>' +
        '<td style="padding:10px 13px;font-size:13px;font-weight:800;color:#fff;text-align:right;">' +
        cc + ' ' + fmt(totalPaidGross) +
        '</td></tr>';

    var statusRow = '';
    if (isPaid) {
        statusRow =
            '<tr style="background:#f0fdf4;border-top:2px solid #86efac;">' +
            '<td style="padding:10px 13px;font-size:11px;color:#16a34a;font-weight:700;letter-spacing:.05em;">' +
            '<span style="display:inline-flex;align-items:center;gap:6px;">' +
            '<span style="width:18px;height:18px;background:#16a34a;color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;">✓</span>' +
            'FULLY PAID' +
            '</span>' +
            '</td>' +
            '<td style="padding:10px 13px;font-size:12px;text-align:right;color:#16a34a;font-weight:700;">OK</td>' +
            '</tr>';
    }

    var exRateNote = buildExchangeNote(data, cc);

    /* ── Student / counsellor info ─────────────────────────────────────── */
    var studentName = esc((data.student_title || '') + '. ' + (data.first_name || '') + ' ' + (data.last_name || ''));
    var processedBy = esc((data.user_title || '') + '. ' + (data.user_fname || '') + ' ' + (data.user_lname || ''));

    var counsellorLine = '';
    if (data.counsellor_fname || data.counsellor_lname) {
        var counsellorName = esc((data.counsellor_title || '') + '. ' + (data.counsellor_fname || '') + ' ' + (data.counsellor_lname || ''));
        counsellorLine =
            '<div style="font-size:9px;color:#9ca3af;margin-top:3px;">' +
            'Counsellor: ' + counsellorName +
            '</div>';
    }

    /* ════════════════════════════════════════════════════════════════════════
       BUILD HTML
    ════════════════════════════════════════════════════════════════════════ */
    var html =
        '<style>' +
        '@keyframes rcptSpin{to{transform:rotate(360deg);}}' +
        '@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}' +
        '@page{size:A4 portrait;margin:8mm 10mm;}.rcpt-no-print{display:none!important;}}' +
        '#receipt-print-area table{page-break-inside:auto;}' +
        '#receipt-print-area tr{page-break-inside:avoid;}' +
        '#receipt-modal-body{overflow:hidden!important;}' +
        '#rcpt-scroll{overflow-y:auto;overflow-x:hidden;max-height:calc(90vh - 120px);' +
        'scrollbar-width:thin;scrollbar-color:#d1d5db #f9fafb;}' +
        '#rcpt-scroll::-webkit-scrollbar{width:5px;}' +
        '#rcpt-scroll::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:4px;}' +
        '#rcpt-scroll::-webkit-scrollbar-track{background:#f9fafb;}' +
        '</style>' +

        '<div id="rcpt-scroll">' +
        '<div id="receipt-print-area" style="font-family:\'Segoe UI\',system-ui,-apple-system,sans-serif;font-size:11px;line-height:1.5;color:#111827;background:#fff;max-width:880px;margin:0 auto;">' +

        '<div style="background:#ffffff;padding:22px 28px 0;position:relative;overflow:hidden;border-bottom:2px solid #1e3a5f;">' +
        '<table style="width:100%;border-collapse:collapse;padding-bottom:28px;"><tr>' +

        '<td style="vertical-align:top;width:55%;">' +
        '<div>' +
        '<img src="./assets/imgs/common/logo.png" alt="" style="height:40px;width:auto;" onerror="this.style.display=\'none\'">' +
        '<div style="font-size:8px;color:#6b7280;text-transform:uppercase;letter-spacing:.12em;margin-top:5px;">Learning Management System</div>' +
        (data.branch_address ? '<div style="font-size:9px;color:#6b7280;margin-top:6px;line-height:1.8;">' + esc(data.branch_address) + '</div>' : '') +
        (data.branch_phone ? '<div style="font-size:9px;color:#6b7280;">Tel: ' + esc(data.branch_phone) + '</div>' : '') +
        (data.branch_email ? '<div style="font-size:9px;color:#6b7280;">' + esc(data.branch_email) + '</div>' : '') +
        '</div></td>' +

        '<td style="vertical-align:top;text-align:right;padding-bottom:32px;">' +
        '<div style="font-size:8px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.14em;margin-bottom:4px;">Official Payment Receipt</div>' +
        '<div style="font-size:22px;font-weight:800;color:#1e3a5f;letter-spacing:-.5px;">' + recNum + '</div>' +
        '<div style="font-size:9px;color:#6b7280;margin-top:3px;">Date: ' + recDate + '</div>' +
        '<div style="margin-top:7px;">' +
        '<span style="display:inline-flex;align-items:center;gap:4px;background:#e6f5ed;border:1px solid #86efac;border-radius:20px;padding:3px 11px;font-size:9px;font-weight:700;color:#16a34a;letter-spacing:.06em;">&#10003; APPROVED</span>' +
        '</div></td>' +
        '</tr></table>' +

        '<div >' +

        '<div style="display:flex;align-items:stretch;gap:20px;margin-bottom:20px;">' +
        '<div style="width:50%;">' + infoBox(
            'Bill To',
            '<div style="font-size:13px;font-weight:700;color:#111827;">' + studentName + '</div>' +
            '<div style="font-size:10px;color:#6b7280;margin-top:4px;line-height:1.9;">' +
            esc(data.email || '') + '<br>' + esc(data.mobile || '') +
            '</div>'
        ) + '</div>' +
        '<div style="width:50%;">' + infoBox(
            'Programme Details',
            '<div style="font-size:11px;font-weight:700;color:#111827;">' + esc(data.faculty || '') + '</div>' +
            '<div style="font-size:10px;color:#6b7280;margin-top:3px;line-height:1.9;">' +
            esc(data.programme || '') + '<br>' + esc(data.intake || '') +
            '</div>' +
            '<div style="font-size:9px;color:#9ca3af;margin-top:7px;padding-top:7px;border-top:1px solid #f3f4f6;">' +
            'Processed by: ' + processedBy +
            '</div>' +
            counsellorLine
        ) + '</div>' +
        '</div>' +

        sectionLabel('Payment Records') +

        '<div style="border:1px solid #e5e7eb;overflow:hidden;margin-bottom:20px;">' +
        '<div class="rcpt-scroll-wrap" style="overflow-x:auto;">' +
        '<table class="rcpt-payment-table" style="width:100%;border-collapse:collapse;min-width:720px;">' +
        '<colgroup>' +
        '<col style="width:3%;"><col style="width:9%;"><col style="width:9%;">' +
        '<col style="width:7%;"><col style="width:8%;"><col style="width:9%;">' +
        '<col style="width:7%;"><col style="width:7%;"><col style="width:6%;">' +
        '<col style="width:9%;"><col style="width:14%;"><col style="width:12%;">' +
        '</colgroup>' +
        '<thead><tr style="background:#1e3a5f;">' +
        thL('#') + thL('Reference') + thL('Type') + thL('Date') + thL('Method') +
        thR('Schedule Amount') + thR('Discount') + thR('Penalty') +
        thC('Tax %') + thR('Tax Amt') + thR('Paid') + thR('Balance') +
        '</tr></thead>' +
        '<tbody>' + payRows + '</tbody>' +
        '</table></div></div>' +

        '<table style="width:100%;border-collapse:collapse;margin-bottom:22px;"><tr>' +

        '<td style="width:50%;vertical-align:top;padding-right:16px;">' +
        (exRateNote ||
            '<div style="font-size:9px;color:#9ca3af;line-height:1.95;margin-top:2px;">' +
            '&#8226; Payments made will not be refundable or transferable under any circumstances.<br>' +
            '&#8226; This receipt is valid subject to realisation of cheque.<br>' +
            '&#8226; Taxes or bank charges may be applied where required.<br>' +
            '&#8226; Only penalties added on or before the payment date are included in this receipt.' +
            '</div>'
        ) +
        '</td>' +

        '<td style="vertical-align:top;">' +
        '<table style="width:100%;border-collapse:collapse;border:1px solid #e5e7eb;overflow:hidden;">' +
        summaryRows +
        totalRow +
        statusRow +
        '</table></td>' +
        '</tr></table>' +

        '</div>' +

        '<div class="rcpt-page-footer">' +
        '<div style="border-top:1px dashed #e5e7eb;padding:10px 28px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;background:#fafafa;">' +
        '<div style="font-size:9px;color:#9ca3af;">This is a computer-generated receipt and does not require a physical signature.</div>' +
        '<div style="font-size:9px;color:#6b7280;text-align:right;">' +
        'Receipt No: <strong style="color:#374151;">' + recNum + '</strong>' +
        '&nbsp;|&nbsp;Generated: ' + new Date().toLocaleString() +
        '</div></div>' +

        '<div class="rcpt-footer-copy" style="text-align:center;padding:8px;border-top:1px solid #f3f4f6;background:#fff;">' +
        '<span style="font-size:8px;color:#d1d5db;letter-spacing:.06em;">ENLIXO LMS 2026 &copy; — All rights reserved</span>' +
        '</div>' +
        '</div>' +

        '</div>' +
        '</div>';

    return html;
}

/* ════════════════════════════════════════════════════════════════════════════
   EXCHANGE RATE NOTE
════════════════════════════════════════════════════════════════════════════ */
function buildExchangeNote(data, cc) {
    if (!data.payments || !data.payments.length) return '';
    var lines = [], seen = {};
    data.payments.forEach(function (p) {
        var pc = (p.paid_currency || cc).trim();
        if (pc !== cc && p.exchange_rate && !seen[pc]) {
            seen[pc] = true;
            lines.push('1 ' + cc + ' = ' + pc + ' ' + fmt(p.exchange_rate) +
                (p.date_paid ? ' (' + p.date_paid.substring(0, 10) + ')' : ''));
        }
    });
    if (!lines.length) return '';
    return '<div style="border:1px solid #e5e7eb;padding:10px 12px;font-size:10px;color:#6b7280;line-height:2;">' +
        '<div style="font-size:8px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.08em;margin-bottom:5px;">Exchange Rates Used</div>' +
        lines.map(function (l) { return '&#8226; ' + l; }).join('<br>') +
        '</div>' +
        '<div style="font-size:9px;color:#9ca3af;line-height:1.9;margin-top:8px;">' +
        '&#8226; Payments made will not be refundable or transferable under any circumstances.<br>' +
        '&#8226; This receipt is valid subject to realisation of cheque.' +
        '</div>';
}

/* ════════════════════════════════════════════════════════════════════════════
   FRAGMENT HELPERS
════════════════════════════════════════════════════════════════════════════ */
var TD_BASE = 'padding:7px 9px;border-bottom:1px solid #f3f4f6;font-size:10px;vertical-align:top;';

function rTD(c, ex) { return '<td style="' + TD_BASE + (ex || '') + '">' + c + '</td>'; }
function rTDR(c, ex) { return '<td style="' + TD_BASE + 'text-align:right;' + (ex || '') + '">' + c + '</td>'; }
function rTDC(c) { return '<td style="' + TD_BASE + 'text-align:center;">' + c + '</td>'; }

var TH = 'padding:8px 9px;font-size:9px;font-weight:600;color:rgba(255,255,255,.85);letter-spacing:.04em;';
function thL(l) { return '<th style="' + TH + 'text-align:left;">' + l + '</th>'; }
function thR(l) { return '<th style="' + TH + 'text-align:right;">' + l + '</th>'; }
function thC(l) { return '<th style="' + TH + 'text-align:center;">' + l + '</th>'; }

function noDataRow() {
    return '<tr><td colspan="12" style="text-align:center;padding:24px;color:#9ca3af;font-size:10px;">No payment records found</td></tr>';
}

function infoBox(labelText, content) {
    return '<div style="border:1px solid #e5e7eb;padding:12px 14px;background:#fafafa;height:100%;box-sizing:border-box;">' +
        '<div style="font-size:8px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.1em;margin-bottom:7px;">' + labelText + '</div>' +
        content +
        '</div>';
}

function sectionLabel(t) {
    return '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">' +
        '<span style="font-size:8px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.12em;white-space:nowrap;">' + t + '</span>' +
        '<div style="flex:1;height:1px;background:#f3f4f6;"></div>' +
        '</div>';
}

/* ════════════════════════════════════════════════════════════════════════════
   SEND-STATUS MODAL CONTROLLER  (same design as Resend Payment Plan modal)
════════════════════════════════════════════════════════════════════════════ */
var SEND_RECEIPT_STEPS = [
    { key: 'fetch', label: 'Fetching receipt data' },
    { key: 'render', label: 'Rendering receipt' },
    { key: 'pdf', label: 'Generating PDF' },
    { key: 'upload', label: 'Sending email' }
];

var srsModalInstance = null;

function srsStepRowHTML(step) {
    return '<li data-step="' + step.key + '">' +
        '<span class="rpp-dot"></span>' +
        '<span class="text-14">' + step.label + '</span>' +
        '</li>';
}

function srsShow(view) {
    ['Sending', 'Success', 'Error'].forEach(function (v) {
        var el = mGet('srsStep' + v);
        if (el) el.classList.toggle('d-none', v !== view);
    });
}

function openSendStatusModal() {
    var modalEl = document.getElementById('sendReceiptStatusModal');
    if (!modalEl) return; // caller falls back to toasts if the modal markup isn't on this page

    var stepsWrap = mGet('srs-steps');
    if (stepsWrap) stepsWrap.innerHTML = SEND_RECEIPT_STEPS.map(srsStepRowHTML).join('');

    var bar = mGet('srsProgressBar');
    if (bar) { bar.style.width = '5%'; bar.style.background = 'var(--dark-theme)'; }

    var txt = mGet('srsProgressText');
    if (txt) txt.textContent = 'Preparing';

    var footer = mGet('srs-footer');
    if (footer) footer.style.display = 'none';
    var closeX = mGet('srsCloseX');
    if (closeX) closeX.classList.add('d-none');

    srsShow('Sending');

    srsModalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
    srsModalInstance.show();
}

function setSendStepStatus(stepKey, status) {
    // status: 'active' | 'done' | 'error'
    var row = document.querySelector('#srs-steps li[data-step="' + stepKey + '"]');
    if (!row) return;

    row.classList.remove('active', 'done');
    if (status === 'active') row.classList.add('active');
    if (status === 'done') row.classList.add('done');

    var idx = SEND_RECEIPT_STEPS.findIndex(function (s) { return s.key === stepKey; });
    var total = SEND_RECEIPT_STEPS.length;
    var bar = mGet('srsProgressBar');
    var txt = mGet('srsProgressText');

    if (status === 'active') {
        if (bar) bar.style.width = Math.round((idx / total) * 100 + 8) + '%';
        if (txt) txt.textContent = SEND_RECEIPT_STEPS[idx].label + '…';
    } else if (status === 'done') {
        if (bar) bar.style.width = Math.round(((idx + 1) / total) * 100) + '%';
    } else if (status === 'error') {
        if (bar) bar.style.background = 'var(--dark-red)';
        var dot = row.querySelector('.rpp-dot');
        if (dot) {
            dot.style.background = 'var(--dark-red)';
            dot.style.borderColor = 'var(--dark-red)';
            dot.style.animation = 'none';
        }
        row.style.color = 'var(--dark-red)';
    }
}

function finishSendStatusModal(success, message) {
    if (success) {
        var ok = mGet('srsSuccessText');
        if (ok) ok.textContent = message;
        // small delay so the last step can be seen turning green
        setTimeout(function () { srsShow('Success'); }, 400);
    } else {
        var er = mGet('srsErrorText');
        if (er) er.textContent = message;
        srsShow('Error');
    }

    var footer = mGet('srs-footer');
    if (footer) footer.style.display = 'flex';
    var closeX = mGet('srsCloseX');
    if (closeX) closeX.classList.remove('d-none');
}

/* ════════════════════════════════════════════════════════════════════════════
   SEND RECEIPT EMAIL — renders the SAME markup as the modal into a hidden
   off-DOM container, converts #receipt-print-area to a PDF client-side, and
   uploads it.
════════════════════════════════════════════════════════════════════════════ */
async function sendReceiptEmail(receiptId) {
    var hasStatusModal = !!document.getElementById('sendReceiptStatusModal');
    if (hasStatusModal) {
        openSendStatusModal();
    } else {
        showToast('info', 'Preparing receipt...'); // fallback if the modal markup isn't present
    }

    var container = null;
    var currentStep = 'fetch';

    try {
        // 1. Same data source the modal uses.
        currentStep = 'fetch';
        if (hasStatusModal) setSendStepStatus('fetch', 'active');
        const res = await fetch('api/getReceiptData.php?receipt_id=' + receiptId);
        const data = await res.json();
        if (data.error) {
            if (hasStatusModal) { setSendStepStatus('fetch', 'error'); finishSendStatusModal(false, data.error); }
            else showToast('error', data.error);
            return;
        }
        if (hasStatusModal) setSendStepStatus('fetch', 'done');

        // 2. Build the exact same markup the modal renders.
        currentStep = 'render';
        if (hasStatusModal) setSendStepStatus('render', 'active');
        container = document.createElement('div');
        container.style.cssText =
            'width:210mm;position:fixed;top:0;left:0;z-index:-1;opacity:0.01;pointer-events:none;background:#fff;';
        document.body.appendChild(container);
        container.innerHTML = buildReceiptHTML(data);

        var printArea = container.querySelector('#receipt-print-area');
        if (!printArea) {
            throw new Error('Could not locate receipt content to render.');
        }
        if (hasStatusModal) setSendStepStatus('render', 'done');

        // 3. DOM -> PDF Blob, client-side, no print dialog.
        currentStep = 'pdf';
        if (hasStatusModal) setSendStepStatus('pdf', 'active');
        const pdfBlob = await html2pdf()
            .set({
                margin: 0,
                filename: 'Receipt-' + data.receipt_number + '.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, scrollX: 0, scrollY: 0 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            })
            .from(printArea)
            .outputPdf('blob');

        document.body.removeChild(container);
        container = null;
        if (hasStatusModal) setSendStepStatus('pdf', 'done');

        // 4. Upload the exact rendered PDF.
        currentStep = 'upload';
        if (hasStatusModal) setSendStepStatus('upload', 'active');
        const formData = new FormData();
        formData.append('receipt_id', receiptId);
        formData.append('pdf', pdfBlob, 'Receipt-' + data.receipt_number + '.pdf');
        formData.append('total_paid', data.grand_total_paid);
        formData.append('balance_due', Math.max(0, (data.grand_total_amount + data.grand_total_tax || 0) - (data.grand_total_paid || 0)));

        const sendRes = await fetch('api/sendReceiptEmail.php', { method: 'POST', body: formData });
        const result = await sendRes.json();

        if (result.error) {
            if (hasStatusModal) { setSendStepStatus('upload', 'error'); finishSendStatusModal(false, result.error); }
            else showToast('error', result.error);
            return;
        }

        if (hasStatusModal) {
            setSendStepStatus('upload', 'done');
            finishSendStatusModal(true, result.done || 'Receipt emailed successfully.');
        } else {
            showToast('success', result.done || 'Receipt emailed successfully.');
        }
    } catch (err) {
        console.error(err);
        if (hasStatusModal) {
            setSendStepStatus(currentStep, 'error');
            finishSendStatusModal(false, 'Failed to generate or send the receipt PDF.');
        } else {
            showToast('error', 'Failed to generate or send the receipt PDF.');
        }
    } finally {
        if (container && container.parentNode) document.body.removeChild(container);
    }
}