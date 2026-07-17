/* ════════════════════════════════════════════════════════════════════════════
   paymentReceiptRenderer.js  — v7 FIXED LOGO BACKGROUND
   
   FORMAT CHANGE:
   Instead of showing what was OWED, now shows what was ACTUALLY PAID
   
   LOGO AREA FIX:
   Changed header background from dark blue (#1e3a5f) to white (#ffffff)
   for better logo visibility
   
   Old Format:
   ├─ Total Course Fee (owed): 30,000
   ├─ Total Discount: —
   ├─ Total Penalty: —
   ├─ Total Tax: 300
   ├─ TOTAL PAID: 10,256.41
   └─ BALANCE DUE: 20,043.59
   
   New Format:
   ├─ Course Fee (paid/net): 9,956.41
   ├─ Discount: —
   ├─ Penalty: —
   ├─ Tax: 300
   ├─ TOTAL PAID AMOUNT: 10,256.41
   └─ (No Balance - only show FULLY PAID when paid)
   
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

var currentReceiptId = null;

function openReceiptModal(receiptId) {
    currentReceiptId = receiptId;
    var body = mGet('receipt-modal-body');
    if (body) {
        body.style.overflow = 'hidden';
        body.innerHTML = spinner();
    }
    var modal = new bootstrap.Modal(document.getElementById('receiptPreviewModal'));
    modal.show();
    fetch('api/getReceiptData.php?receipt_id=' + receiptId)
        .then(function (r) { return r.json(); })
        .then(function (data) {
            console.log(data);
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
        'body{font-family:"Segoe UI",system-ui,sans-serif;font-size:11px;background:#fff;color:#111827;}' +
        '@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}' +
        '@page{size:A4 portrait;margin:8mm 10mm;}}</style></head><body>' +
        printArea.outerHTML +
        '<script>window.onload=function(){setTimeout(function(){window.print();},400);' +
        'window.onafterprint=function(){window.close();};};<\/script></body></html>'
    );
    win.document.close();
}

function sendReceiptEmail() {
    if (!currentReceiptId) return;
    var btn = document.querySelector('#receiptPreviewModal .table-btn-success');
    var origHtml = btn ? btn.innerHTML : '';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<iconify-icon icon="mdi:loading" width="13" style="vertical-align:-2px;margin-right:4px;animation:rcptSpin .7s linear infinite;display:inline-block;"></iconify-icon>Sending…';
    }
    fetch('api/sendReceiptEmail.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ receipt_id: currentReceiptId })
    })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
            if (res.done) showToast('success', res.done);
            else showToast('error', res.error || 'Failed to send email.');
        })
        .catch(function () {
            if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }
            showToast('error', 'Network error — could not send email.');
        });
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
   MAIN RENDERER
════════════════════════════════════════════════════════════════════════════ */
function renderReceiptHTML(data) {
    var body = mGet('receipt-modal-body');

    var cc = (data.course_currency || 'LKR').trim();
    var recDate = data.date_added ? data.date_added.substring(0, 10) : '';
    var recNum = esc(data.receipt_number || '');

    /* ── Payment table rows ─────────────────────────────────────────────── */
    var payRows = '';
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
        var discMain = parseFloat(p.discount_amount || 0) > 0 ? cc + ' ' + fmt(p.discount_amount) : dash();
        var penMain = parseFloat(p.penalty_course || 0) > 0 ? cc + ' ' + fmt(p.penalty_course) : dash();
        var bg = idx % 2 === 0 ? '#ffffff' : '#f8fafc';

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
            '</tr>';
    });
    if (!payRows) payRows = noDataRow();

    /* ════════════════════════════════════════════════════════════════════
       🔴 NEW SUMMARY FORMAT - SHOWS WHAT WAS ACTUALLY PAID
       
       Course Fee (paid/net) = Total Paid (gross) - Tax
       This is the actual amount that went toward the course
    ════════════════════════════════════════════════════════════════════ */

    var totalPaidGross = data.grand_total_paid || 0;
    var totalTax = data.grand_total_tax || 0;
    var totalDiscount = data.grand_total_discount || 0;
    var totalPenalty = data.grand_total_penalty || 0;

    // Course Fee (paid) = Gross Paid - Tax
    // This is the net amount that actually paid the course
    var courseFeeActuallyPaid = Math.max(0, totalPaidGross - totalTax);

    // Check if fully paid by comparing paid to total course fee
    var totalCourseFeeListed = data.grand_total_amount || 0;
    var isPaid = (courseFeeActuallyPaid >= totalCourseFeeListed - 0.005);

    /* ── Summary rows (NEW FORMAT) ─── */
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
        /* 1 — Course Fee (Paid/Net) */
        sRow('Course Fee', cc + ' ' + fmt(courseFeeActuallyPaid)) +

        /* 2 — Discount */
        sRow(
            'Discount',
            parseFloat(totalDiscount || 0) > 0
                ? cc + ' ' + fmt(totalDiscount)
                : dash()
        ) +

        /* 3 — Penalty */
        sRow(
            'Penalty',
            parseFloat(totalPenalty || 0) > 0
                ? cc + ' ' + fmt(totalPenalty)
                : dash()
        ) +

        /* 4 — Tax */
        sRow(
            'Tax',
            parseFloat(totalTax || 0) > 0
                ? cc + ' ' + fmt(totalTax)
                : dash()
        );

    /* ════════════════════════════════════════════════════════════════════
       Total Paid Amount (Gross - includes tax)
    ════════════════════════════════════════════════════════════════════ */
    var totalRow =
        '<tr style="background:#1e3a5f;border-top:2px solid #1e3a5f;">' +
        '<td style="padding:10px 13px;font-size:12px;font-weight:700;color:#fff;">TOTAL PAID AMOUNT</td>' +
        '<td style="padding:10px 13px;font-size:13px;font-weight:800;color:#fff;text-align:right;">' +
        cc + ' ' + fmt(totalPaidGross) +
        '</td></tr>';

    /* ════════════════════════════════════════════════════════════════════
       Status Row - FULLY PAID or nothing (no balance shown)
    ════════════════════════════════════════════════════════════════════ */
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

    /* ── Student info ───────────────────────────────────────────────────── */
    var studentName = esc((data.student_title || '') + '. ' + (data.first_name || '') + ' ' + (data.last_name || ''));
    var processedBy = esc((data.user_title || '') + '. ' + (data.user_fname || '') + ' ' + (data.user_lname || ''));

    /* ════════════════════════════════════════════════════════════════════════
       BUILD HTML
    ════════════════════════════════════════════════════════════════════════ */
    var html =
        /* ── Scoped styles ── */
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

        /* ════ HEADER BAND - NOW WITH WHITE BACKGROUND ════ */
        '<div style="background:#ffffff;padding:22px 28px 0;position:relative;overflow:hidden;border-bottom:2px solid #1e3a5f;">' +
        '<table style="width:100%;border-collapse:collapse;padding-bottom:28px;"><tr>' +

        /* Left — logo + branch */
        '<td style="vertical-align:top;width:55%;">' +
        '<div>' +
        '<img src="./assets/imgs/common/logo.png" alt="" style="height:40px;width:auto;" onerror="this.style.display=\'none\'">' +
        '<div style="font-size:8px;color:#6b7280;text-transform:uppercase;letter-spacing:.12em;margin-top:5px;">Learning Management System</div>' +
        (data.branch_address ? '<div style="font-size:9px;color:#6b7280;margin-top:6px;line-height:1.8;">' + esc(data.branch_address) + '</div>' : '') +
        (data.branch_phone ? '<div style="font-size:9px;color:#6b7280;">Tel: ' + esc(data.branch_phone) + '</div>' : '') +
        (data.branch_email ? '<div style="font-size:9px;color:#6b7280;">' + esc(data.branch_email) + '</div>' : '') +
        '</div></td>' +

        /* Right — receipt ID */
        '<td style="vertical-align:top;text-align:right;padding-bottom:32px;">' +
        '<div style="font-size:8px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.14em;margin-bottom:4px;">Official Payment Receipt</div>' +
        '<div style="font-size:22px;font-weight:800;color:#1e3a5f;letter-spacing:-.5px;">' + recNum + '</div>' +
        '<div style="font-size:9px;color:#6b7280;margin-top:3px;">Date: ' + recDate + '</div>' +
        '<div style="margin-top:7px;">' +
        '<span style="display:inline-flex;align-items:center;gap:4px;background:#e6f5ed;border:1px solid #86efac;border-radius:20px;padding:3px 11px;font-size:9px;font-weight:700;color:#16a34a;letter-spacing:.06em;">&#10003; APPROVED</span>' +
        '</div></td>' +
        '</tr></table>' +
    
        /* ════ BODY ════ */
        '<div >' +

        /* Bill To + Programme */
        '<table style="width:100%;border-collapse:collapse;margin-bottom:20px;"><tr>' +
        '<td style="width:50%;vertical-align:top;padding-right:10px;">' + infoBox(
            'Bill To',
            '<div style="font-size:13px;font-weight:700;color:#111827;">' + studentName + '</div>' +
            '<div style="font-size:10px;color:#6b7280;margin-top:4px;line-height:1.9;">' +
            'ID: ' + esc(data.student_reg_id || '') + '<br>' +
            esc(data.email || '') + '<br>' + esc(data.mobile || '') +
            '</div>'
        ) + '</td>' +
        '<td style="width:50%;vertical-align:top;padding-left:10px;">' + infoBox(
            'Programme Details',
            '<div style="font-size:11px;font-weight:700;color:#111827;">' + esc(data.faculty || '') + '</div>' +
            '<div style="font-size:10px;color:#6b7280;margin-top:3px;line-height:1.9;">' +
            esc(data.programme || '') + '<br>' + esc(data.intake || '') +
            '</div>' +
            '<div style="font-size:9px;color:#9ca3af;margin-top:7px;padding-top:7px;border-top:1px solid #f3f4f6;">' +
            'Processed by: ' + processedBy +
            '</div>'
        ) + '</td>' +
        '</tr></table>' +

        /* Section label */
        sectionLabel('Payment Records') +

        /* Payment table */
        '<div style="border:1px solid #e5e7eb;overflow:hidden;margin-bottom:20px;">' +
        '<div style="overflow-x:auto;">' +
        '<table style="width:100%;border-collapse:collapse;min-width:640px;">' +
        '<thead><tr style="background:#1e3a5f;">' +
        thL('#') + thL('Reference') + thL('Type') + thL('Date') + thL('Method') +
        thR('Schedule Amount') + thR('Discount') + thR('Penalty') +
        thC('Tax %') + thR('Tax Amt') + thR('Paid') +
        '</tr></thead>' +
        '<tbody>' + payRows + '</tbody>' +
        '</table></div></div>' +

        /* Summary row */
        '<table style="width:100%;border-collapse:collapse;margin-bottom:22px;"><tr>' +

        /* Left — notes */
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

        /* Right — totals summary (NEW FORMAT) */
        '<td style="vertical-align:top;">' +
        '<table style="width:100%;border-collapse:collapse;border:1px solid #e5e7eb;overflow:hidden;">' +
        summaryRows +
        totalRow +
        statusRow +
        '</table></td>' +
        '</tr></table>' +

        '</div>' + /* /body */

        /* ════ FOOTER ════ */
        '<div style="border-top:1px dashed #e5e7eb;padding:10px 28px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;background:#fafafa;">' +
        '<div style="font-size:9px;color:#9ca3af;">This is a computer-generated receipt and does not require a physical signature.</div>' +
        '<div style="font-size:9px;color:#6b7280;text-align:right;">' +
        'Receipt No: <strong style="color:#374151;">' + recNum + '</strong>' +
        '&nbsp;|&nbsp;Generated: ' + recDate +
        '</div></div>' +

        /* System tag */
        '<div style="text-align:center;padding:8px;border-top:1px solid #f3f4f6;background:#fff;">' +
        '<span style="font-size:8px;color:#d1d5db;letter-spacing:.06em;">ENLIXO LMS 2026 &copy; — All rights reserved</span>' +
        '</div>' +

        '</div>' + /* /print-area */
        '</div>';  /* /scroll */

    if (body) {
        body.style.overflow = 'hidden';
        body.innerHTML = html;
    }
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
    return '<tr><td colspan="11" style="text-align:center;padding:24px;color:#9ca3af;font-size:10px;">No payment records found</td></tr>';
}

function infoBox(labelText, content) {
    return '<div style="border:1px solid #e5e7eb;padding:12px 14px;background:#fafafa;">' +
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