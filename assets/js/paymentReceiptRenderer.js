 var mGet = function(id) {
                                    return document.getElementById(id);
                                };

                                function fmt(n, dp) {
                                    dp = (dp === undefined) ? 2 : dp;
                                    return parseFloat(n || 0).toLocaleString('en-US', {
                                        minimumFractionDigits: dp,
                                        maximumFractionDigits: dp
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
                                        body.innerHTML = '<div style="text-align:center;padding:80px 20px;"><div style="width:32px;height:32px;border:3px solid #e2e8f0;border-top-color:#1e3a5f;border-radius:50%;animation:rcptSpin .7s linear infinite;margin:0 auto 14px;"></div><span style="font-size:12px;color:#94a3b8;">Loading receipt…</span></div><style>@keyframes rcptSpin{to{transform:rotate(360deg);}}</style>';
                                    }
                                    var modal = new bootstrap.Modal(document.getElementById('receiptPreviewModal'));
                                    modal.show();
                                    fetch('api/getReceiptData.php?receipt_id=' + receiptId)
                                        .then(function(r) {
                                            return r.json();
                                        })
                                        .then(function(data) {
                                            if (data.error) {
                                                if (body) body.innerHTML = '<div style="text-align:center;padding:60px;color:#ef4444;font-size:12px;">' + esc(data.error) + '</div>';
                                                return;
                                            }
                                            renderReceiptHTML(data);
                                        })
                                        .catch(function() {
                                            if (body) body.innerHTML = '<div style="text-align:center;padding:60px;color:#ef4444;font-size:12px;">Failed to load receipt.</div>';
                                        });
                                }

                                function downloadReceipt() {
                                    var printArea = mGet('receipt-print-area');
                                    if (!printArea) return;
                                    var win = window.open('', '_blank');
                                    win.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Payment Receipt</title><style>*{box-sizing:border-box;margin:0;padding:0;}body{font-family:"Segoe UI",system-ui,sans-serif;font-size:11px;background:#fff;color:#111827;}@media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}@page{size:A4 portrait;margin:8mm 10mm;}}</style></head><body>' + printArea.outerHTML + '<script>window.onload=function(){setTimeout(function(){window.print();},400);window.onafterprint=function(){window.close();};};<\/script></body></html>');
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
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json'
                                            },
                                            body: JSON.stringify({
                                                receipt_id: currentReceiptId
                                            })
                                        })
                                        .then(function(r) {
                                            return r.json();
                                        })
                                        .then(function(res) {
                                            if (btn) {
                                                btn.disabled = false;
                                                btn.innerHTML = origHtml;
                                            }
                                            if (res.done) showToast('success', res.done);
                                            else showToast('error', res.error || 'Failed to send email.');
                                        })
                                        .catch(function() {
                                            if (btn) {
                                                btn.disabled = false;
                                                btn.innerHTML = origHtml;
                                            }
                                            showToast('error', 'Network error — could not send email.');
                                        });
                                }

                                /* ════════════════════════════════════════════════════════════════════════════
                                    MAIN RENDERER
                                 ════════════════════════════════════════════════════════════════════════════ */
                                function renderReceiptHTML(data) {
                                    var body = mGet('receipt-modal-body');

                                    /* ── Currency setup ──────────────────────────────────────────────────── */
                                    var cc = (data.course_currency || 'LKR').trim(); // invoice currency
                                    var isLKR = (cc === 'LKR');

                                    /* ── Payment table rows ──────────────────────────────────────────────── */
                                    var payRows = '';
                                    (data.payments || []).forEach(function(p, idx) {

                                        /* Determine the currency the student paid in */
                                        var paidCurr = (p.paid_currency || cc).trim();
                                        var isForeign = (paidCurr !== cc); // paid in a different currency than invoice
                                        var exRate = parseFloat(p.exchange_rate || 0);

                                        /*
                                         * PAID column
                                         * Primary  : always show what the student actually handed over (paid_currency + paid_amount)
                                         * Sub-note : if foreign, also show the course-currency equivalent
                                         */
                                        var paidMain, paidSub = '';
                                        if (isForeign && exRate > 0) {
                                            paidMain = paidCurr + ' ' + fmt(p.paid_amount);
                                            paidSub = subLine('= ' + cc + ' ' + fmt(p.paid_in_course) +
                                                ' &nbsp;(@ 1 ' + cc + ' = ' + paidCurr + ' ' + fmt(exRate) + ')');
                                        } else {
                                            /* Same currency — just show paid_in_course (already in cc) */
                                            paidMain = cc + ' ' + fmt(p.paid_in_course);
                                        }

                                        /*
                                         * TAX column  — tax_amount is stored in paid_currency;
                                         *               tax_course is the course-currency equivalent (from PHP)
                                         */
                                        var taxRateDisp = parseFloat(p.tax_rate || 0) > 0 ? fmt(p.tax_rate) + '%' : dash();
                                        var taxMain = dash();
                                        var taxSub = '';
                                        if (parseFloat(p.tax_course || p.tax_amount || 0) > 0) {
                                            taxMain = cc + ' ' + fmt(p.tax_course || p.tax_amount);
                                            if (isForeign && parseFloat(p.tax_amount || 0) > 0) {
                                                taxSub = subLine(paidCurr + ' ' + fmt(p.tax_amount));
                                            }
                                        }

                                        /* DISCOUNT & PENALTY — stored / returned in course currency */
                                        var discMain = parseFloat(p.discount_amount || 0) > 0 ?
                                            cc + ' ' + fmt(p.discount_amount) : dash();
                                        var penMain = parseFloat(p.penalty_course || 0) > 0 ?
                                            cc + ' ' + fmt(p.penalty_course) : dash();

                                        var bg = (idx % 2 === 0) ? '#ffffff' : '#f8fafc';

                                        payRows +=
                                            '<tr style="background:' + bg + ';">' +
                                            tdN(idx + 1) +
                                            tdB(esc(p.reference || '—')) +
                                            td(esc(p.payment_type || '—')) +
                                            td(p.date_paid ? p.date_paid.substring(0, 10) : '—', 'white-space:nowrap;') +
                                            td(esc(p.payment_method || '—')) +
                                            tdR(cc + ' ' + fmt(p.cp_amount)) +
                                            tdR(discMain) +
                                            tdR(penMain) +
                                            tdC(taxRateDisp) +
                                            tdR(taxMain + taxSub) +
                                            tdRB(paidMain + paidSub) +
                                            '</tr>';
                                    });

                                    /* ── Summary totals (always in course currency cc) ───────────────────── */
                                    var balanceDue = Math.max(0,
                                        (data.grand_total_amount || 0) -
                                        (data.grand_total_discount || 0) +
                                        (data.grand_total_penalty || 0) +
                                        (data.grand_total_tax || 0) -
                                        (data.grand_total_paid || 0)
                                    );

                                    var summaryRows = [
                                        ['Course Amount', cc + ' ' + fmt(data.grand_total_amount)],
                                        ['Total Discount', cc + ' ' + fmt(data.grand_total_discount)],
                                        ['Total Penalty', cc + ' ' + fmt(data.grand_total_penalty)],
                                        ['Total Tax', cc + ' ' + fmt(data.grand_total_tax)],
                                    ];

                                    var summaryHTML = summaryRows.map(function(row) {
                                        return '<tr>' +
                                            '<td style="' + STYL.sumLabel + '">' + row[0] + '</td>' +
                                            '<td style="' + STYL.sumVal + '">' + row[1] + '</td>' +
                                            '</tr>';
                                    }).join('');

                                    var balanceRow = balanceDue > 0.005 ?
                                        '<tr style="background:#fef2f2;">' +
                                        '<td style="' + STYL.sumLabel + 'color:#dc2626;">Balance Due</td>' +
                                        '<td style="' + STYL.sumVal + 'color:#dc2626;font-weight:700;">' +
                                        cc + ' ' + fmt(balanceDue) + '</td></tr>' :
                                        '<tr style="background:#f0fdf4;">' +
                                        '<td style="' + STYL.sumLabel + 'color:#16a34a;">Status</td>' +
                                        '<td style="' + STYL.sumVal + 'color:#16a34a;font-weight:700;">FULLY PAID</td></tr>';

                                    /* ── Exchange rate note (only when foreign-currency receipts exist) ──── */
                                    var exRateNote = buildExchangeNote(data, cc);

                                    /* ── Dates ───────────────────────────────────────────────────────────── */
                                    var recDate = data.date_added ? data.date_added.substring(0, 10) : '';

                                    /* ════════════════════════════════════════════════════════════════════════
                                       BUILD HTML
                                    ════════════════════════════════════════════════════════════════════════ */
                                    var html =
                                        /* ── Scoped print styles ── */
                                        '<style>' +
                                        '@keyframes rcptSpin{to{transform:rotate(360deg);}}' +
                                        '@media print{' +
                                        ' body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}' +
                                        ' @page{size:A4 portrait;margin:8mm 10mm;}' +
                                        ' .rcpt-no-print{display:none!important;}' +
                                        '}' +
                                        '#receipt-print-area table{page-break-inside:auto;}' +
                                        '#receipt-print-area tr{page-break-inside:avoid;}' +
                                        /* Fix modal scrollbar — only scroll the inner receipt wrapper */
                                        '#receipt-modal-body{overflow:hidden!important;}' +
                                        '#receipt-scroll-wrap{overflow-y:auto;overflow-x:hidden;' +
                                        ' max-height:calc(90vh - 120px);scrollbar-width:thin;' +
                                        ' scrollbar-color:#d1d5db #f9fafb;}' +
                                        '#receipt-scroll-wrap::-webkit-scrollbar{width:5px;}' +
                                        '#receipt-scroll-wrap::-webkit-scrollbar-thumb{background:#d1d5db;border-radius:4px;}' +
                                        '#receipt-scroll-wrap::-webkit-scrollbar-track{background:#f9fafb;}' +
                                        '</style>' +

                                        /* ── Outer scroll wrapper ── */
                                        '<div id="receipt-scroll-wrap">' +

                                        /* ── Print area (A4-proportioned, small dense text) ── */
                                        '<div id="receipt-print-area" style="' +
                                        'font-family:\'Segoe UI\',system-ui,-apple-system,sans-serif;' +
                                        'font-size:11px;line-height:1.5;color:#111827;background:#fff;' +
                                        'max-width:880px;margin:0 auto;padding:28px 32px;">' +

                                        /* ════ HEADER ════ */
                                        '<table style="width:100%;border-collapse:collapse;margin-bottom:18px;">' +
                                        '<tr>' +
                                        '<td style="vertical-align:top;width:58%;">' +
                                        '<div style="display:flex-column;align-items:center;gap:12px;">' +
                                        '<img src="./assets/imgs/common/logo.png" alt="" style="height:44px;width:auto;" onerror="this.style.display=\'none\'">' +
                                        '<div>' +
                                        (data.branch_address ? '<div style="font-size:10px;color:#6b7280;margin-top:1px;">' + esc(data.branch_address) + '</div>' : '') +
                                        (data.branch_phone ? '<div style="font-size:10px;color:#6b7280;">Tel: ' + esc(data.branch_phone) + '</div>' : '') +
                                        (data.branch_email ? '<div style="font-size:10px;color:#6b7280;">' + esc(data.branch_email) + '</div>' : '') +
                                        '</div></div>' +
                                        '</td>' +
                                        '<td style="vertical-align:top;text-align:right;">' +
                                        '<div style="font-size:8px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.12em;margin-bottom:3px;">OFFICIAL PAYMENT RECEIPT</div>' +
                                        '<div style="font-size:20px;font-weight:800;color:#111827;letter-spacing:-.4px;">' + esc(data.receipt_number || '') + '</div>' +
                                        '<div style="font-size:10px;color:#6b7280;margin-top:2px;">Date: ' + recDate + '</div>' +
                                        '<div style="margin-top:6px;">' +
                                        '<span style="display:inline-block;background:#d1fae5;color:#065f46;' +
                                        'padding:2px 12px;border-radius:20px;font-size:9px;font-weight:700;' +
                                        'letter-spacing:.06em;border:1px solid #6ee7b7;">&#10003; APPROVED</span>' +
                                        '</div>' +
                                        '</td>' +
                                        '</tr>' +
                                        '</table>' +

                                        /* Divider */
                                        '<div style="height:2px;background:##F7F9FF;margin-bottom:18px;border-radius:1px;"></div>' +

                                        /* ════ BILL TO + PROGRAMME ════ */
                                        '<table style="width:100%;border-collapse:collapse;margin-bottom:18px;">' +
                                        '<tr>' +
                                        '<td style="width:50%;vertical-align:top;padding-right:10px;">' +
                                        boxOpen() +
                                        label('Bill To') +
                                        '<div style="font-size:13px;font-weight:700;color:#111827;">' +
                                        esc((data.student_title || '') + '. ' + (data.first_name || '') + ' ' + (data.last_name || '')) +
                                        '</div>' +
                                        '<div style="font-size:10px;color:#6b7280;margin-top:4px;line-height:1.8;">' +
                                        'ID: ' + esc(data.student_reg_id || '') + '<br>' +
                                        esc(data.email || '') + '<br>' + esc(data.mobile || '') +
                                        '</div>' +
                                        boxClose() +
                                        '</td>' +
                                        '<td style="width:50%;vertical-align:top;padding-left:10px;">' +
                                        boxOpen() +
                                        label('Programme Details') +
                                        '<div style="font-size:11px;font-weight:600;color:#111827;">' + esc(data.faculty || '') + '</div>' +
                                        '<div style="font-size:10px;color:#6b7280;margin-top:3px;line-height:1.8;">' +
                                        esc(data.programme || '') + '<br>' + esc(data.intake || '') +
                                        '</div>' +
                                        '<div style="font-size:9px;color:#9ca3af;margin-top:6px;padding-top:6px;border-top:1px solid #f3f4f6;">' +
                                        'Processed by: ' + esc((data.user_title || '') + '. ' + (data.user_fname || '') + ' ' + (data.user_lname || '')) +
                                        '</div>' +
                                        boxClose() +
                                        '</td>' +
                                        '</tr>' +
                                        '</table>' +

                                        /* ════ PAYMENT TABLE ════ */
                                        label('Payment Records') +
                                        '<div style="border:1px solid #e5e7eb;border-radius:3px;overflow:hidden;margin-bottom:18px;">' +
                                        '<div style="overflow-x:auto;">' +
                                        '<table style="width:100%;border-collapse:collapse;min-width:640px;">' +
                                        '<thead>' +
                                        '<tr style="background:#1e3a5f;">' +
                                        thH('#') + thH('Reference') + thH('Type') + thH('Date') + thH('Method') +
                                        thHR('Invoice Amt') + thHR('Discount') + thHR('Penalty') +
                                        thHC('Tax %') + thHR('Tax Amt') + thHRB('Paid') +
                                        '</tr>' +
                                        '</thead>' +
                                        '<tbody>' + (payRows || noDataRow()) + '</tbody>' +
                                        '</table>' +
                                        '</div>' +
                                        '</div>' +

                                        /* ════ SUMMARY + EXCHANGE NOTES ════ */
                                        '<table style="width:100%;border-collapse:collapse;margin-bottom:20px;">' +
                                        '<tr>' +

                                        /* Left — exchange rate notes (or empty if none) */
                                        '<td style="width:50%;vertical-align:top;padding-right:16px;">' +
                                        (exRateNote || '<div style="font-size:9px;color:#9ca3af;line-height:1.7;margin-top:4px;">' +
                                            'Payments made will not be refundable or transferable under any circumstances.<br>' +
                                            'This receipt is valid subject to realisation of cheque.<br>' +
                                            'Taxes or bank charges may be applied where required.' +
                                            '</div>') +
                                        '</td>' +

                                        /* Right — totals panel */
                                        '<td style="vertical-align:top;">' +
                                        '<table style="width:100%;border-collapse:collapse;border:1px solid #e5e7eb;border-radius:3px;overflow:hidden;">' +
                                        summaryHTML +
                                        '<tr style="background:#1e3a5f;">' +
                                        '<td style="padding:8px 14px;font-size:11px;font-weight:700;color:#fff;">TOTAL PAID</td>' +
                                        '<td style="padding:8px 14px;font-size:12px;font-weight:800;color:#fff;text-align:right;">' +
                                        cc + ' ' + fmt(data.grand_total_paid) +
                                        '</td>' +
                                        '</tr>' +
                                        balanceRow +
                                        '</table>' +
                                        '</td>' +

                                        '</tr>' +
                                        '</table>' +

                                        /* ════ FOOTER ════ */
                                        '<div style="border-top:1px dashed #d1d5db;padding-top:10px;' +
                                        'display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">' +
                                        '<div style="font-size:9px;color:#9ca3af;">' +
                                        'This is a computer-generated receipt and does not require a physical signature.' +
                                        '</div>' +
                                        '<div style="font-size:9px;color:#6b7280;text-align:right;">' +
                                        'Receipt No: <strong style="color:#374151;">' + esc(data.receipt_number || '') + '</strong>' +
                                        '&nbsp; | &nbsp;Generated: ' + recDate +
                                        '</div>' +
                                        '</div>' +

                                        /* ════ SYSTEM FOOTER ════ */
                                        '<div style="text-align:center;margin-top:14px;padding-top:10px;' +
                                        'border-top:1px solid #f3f4f6;">' +
                                        '<span style="font-size:9px;color:#c4c9d4;letter-spacing:.04em;">' +
                                        'ENLIXO LMS 2026 &copy; — All rights reserved' +
                                        '</span>' +
                                        '</div>' +

                                        '</div>' + /* /receipt-print-area */
                                        '</div>'; /* /receipt-scroll-wrap */

                                    if (body) {
                                        body.style.overflow = 'hidden'; /* parent stays hidden — scroll handled inside */
                                        body.innerHTML = html;
                                    }
                                }

                                /* ════════════════════════════════════════════════════════════════════════════
                                   EXCHANGE RATE NOTE BLOCK
                                ════════════════════════════════════════════════════════════════════════════ */
                                function buildExchangeNote(data, cc) {
                                    if (!data.payments || !data.payments.length) return '';
                                    var lines = [];
                                    var seen = {};
                                    data.payments.forEach(function(p) {
                                        var pc = (p.paid_currency || cc).trim();
                                        if (pc !== cc && p.exchange_rate && !seen[pc]) {
                                            seen[pc] = true;
                                            lines.push('1 ' + cc + ' = ' + pc + ' ' + fmt(p.exchange_rate) +
                                                (p.date_paid ? ' (' + p.date_paid.substring(0, 10) + ')' : ''));
                                        }
                                    });
                                    if (!lines.length) return '';
                                    return '<div style="border:1px solid #e5e7eb;border-radius:3px;padding:10px 12px;font-size:10px;color:#6b7280;line-height:2;">' +
                                        '<div style="font-size:8px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.08em;margin-bottom:5px;">Exchange Rates Used</div>' +
                                        lines.map(function(l) {
                                            return '&#8226; ' + l;
                                        }).join('<br>') +
                                        '</div>' +
                                        '<div style="font-size:9px;color:#9ca3af;line-height:1.7;margin-top:8px;">' +
                                        'Payments made will not be refundable or transferable under any circumstances.<br>' +
                                        'This receipt is valid subject to realisation of cheque.' +
                                        '</div>';
                                }

                                /* ════════════════════════════════════════════════════════════════════════════
                                   MINI STYLE CONSTANTS
                                ════════════════════════════════════════════════════════════════════════════ */
                                var STYL = {
                                    sumLabel: 'padding:6px 12px;font-size:10px;color:#374151;border-bottom:1px solid #e5e7eb;',
                                    sumVal: 'padding:6px 12px;font-size:10px;color:#111827;font-weight:500;text-align:right;border-bottom:1px solid #e5e7eb;'
                                };

                                /* ── reusable fragment helpers ── */
                                function boxOpen() {
                                    return '<div style="border:1px solid #e5e7eb;border-radius:3px;padding:12px 14px;">';
                                }

                                function boxClose() {
                                    return '</div>';
                                }

                                function label(t) {
                                    return '<div style="font-size:8px;font-weight:700;color:#9ca3af;text-transform:uppercase;' +
                                        'letter-spacing:.1em;margin-bottom:7px;">' + t + '</div>';
                                }

                                function subLine(t) {
                                    return '<div style="font-size:9px;color:#6b7280;margin-top:2px;">' + t + '</div>';
                                }

                                function dash() {
                                    return '<span style="color:#d1d5db;">—</span>';
                                }

                                /* ── table cell helpers ── */
                                var C = 'padding:7px 8px;border-bottom:1px solid #f3f4f6;font-size:10px;vertical-align:top;';

                                function td(c, ex) {
                                    return '<td style="' + C + (ex || '') + '">' + c + '</td>';
                                }

                                function tdN(c) {
                                    return '<td style="' + C + 'color:#9ca3af;width:24px;">' + c + '</td>';
                                }

                                function tdB(c) {
                                    return '<td style="' + C + 'font-weight:600;color:#111827;">' + c + '</td>';
                                }

                                function tdR(c) {
                                    return '<td style="' + C + 'text-align:right;">' + c + '</td>';
                                }

                                function tdRB(c) {
                                    return '<td style="' + C + 'text-align:right;font-weight:700;color:#111827;">' + c + '</td>';
                                }

                                function tdC(c) {
                                    return '<td style="' + C + 'text-align:center;">' + c + '</td>';
                                }

                                var TH = 'padding:7px 8px;font-size:9px;font-weight:600;color:rgba(255,255,255,.9);letter-spacing:.04em;';

                                function thH(l) {
                                    return '<th style="' + TH + 'text-align:left;">' + l + '</th>';
                                }

                                function thHR(l) {
                                    return '<th style="' + TH + 'text-align:right;">' + l + '</th>';
                                }

                                function thHRB(l) {
                                    return '<th style="' + TH + 'text-align:right;color:#fff;">' + l + '</th>';
                                }

                                function thHC(l) {
                                    return '<th style="' + TH + 'text-align:center;">' + l + '</th>';
                                }

                                function noDataRow() {
                                    return '<tr><td colspan="11" style="text-align:center;padding:24px;color:#9ca3af;font-size:10px;">No payment records found</td></tr>';
                                }

                                /* ════════════════════════════════════════════════════════════════════════════
                                   DOWNLOAD PDF  (print-to-PDF via new window)
                                ════════════════════════════════════════════════════════════════════════════ */
                                function downloadReceipt() {
                                    var printArea = mGet('receipt-print-area');
                                    if (!printArea) return;

                                    var win = window.open('', '_blank');
                                    win.document.write(
                                        '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Payment Receipt</title>' +
                                        '<style>' +
                                        '*{box-sizing:border-box;margin:0;padding:0;}' +
                                        'body{font-family:"Segoe UI",system-ui,sans-serif;font-size:11px;background:#fff;color:#111827;}' +
                                        '@media print{' +
                                        ' body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}' +
                                        ' @page{size:A4 portrait;margin:8mm 10mm;}' +
                                        '}' +
                                        '</style></head><body>' +
                                        printArea.outerHTML +
                                        '<script>window.onload=function(){setTimeout(function(){window.print();},400);' +
                                        'window.onafterprint=function(){window.close();};};<\/script>' +
                                        '</body></html>'
                                    );
                                    win.document.close();
                                }

                                /* ════════════════════════════════════════════════════════════════════════════
                                   SEND EMAIL  → ajax/sendReceiptEmail.php
                                ════════════════════════════════════════════════════════════════════════════ */
                                function sendReceiptEmail() {
                                    if (!currentReceiptId) return;

                                    var btn = document.querySelector('#receiptPreviewModal .table-btn-success');
                                    var origHtml = btn ? btn.innerHTML : '';

                                    if (btn) {
                                        btn.disabled = true;
                                        btn.innerHTML =
                                            '<iconify-icon icon="mdi:loading" width="13" ' +
                                            'style="vertical-align:-2px;margin-right:4px;' +
                                            'animation:rcptSpin .7s linear infinite;display:inline-block;"></iconify-icon>' +
                                            'Sending…';
                                    }

                                    fetch('api/sendReceiptEmail.php', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json'
                                            },
                                            body: JSON.stringify({
                                                receipt_id: currentReceiptId
                                            })
                                        })
                                        .then(function(r) {
                                            console.log(r.text());
                                            return r.json();
                                        })
                                        .then(function(res) {

                                            if (btn) {
                                                btn.disabled = false;
                                                btn.innerHTML = origHtml;
                                            }
                                            if (res.done) showToast('success', res.done);
                                            else showToast('error', res.error || 'Failed to send email.');
                                        })
                                        .catch(function() {
                                            if (btn) {
                                                btn.disabled = false;
                                                btn.innerHTML = origHtml;
                                            }
                                            showToast('error', 'Network error — could not send email.');
                                        });
                                }