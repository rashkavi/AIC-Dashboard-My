/* assets/js/intake-summary-timetable.js
 *
 * Used by two pages:
 *   intake-summary-timetable.php      list, preview, Download PDF, Notify Students,
 *                                     set active / mark inactive
 *     → body onload="initializeIntakeSummaryTimetables();"
 *   add-intake-summary-timetable.php  create / edit form (sections → modules)
 *     → body onload="initializeAddIntakeSummaryTimetable();"
 *
 * Backend: api/intakeTimetableManager.php (JSON) and api/notifyIntakeTimetable.php (PDF upload).
 */

const TT_CFG = window.TT_CONFIG || {};
const TT_API = 'api/intakeTimetableManager.php';
const TT_NOTIFY_API = 'api/notifyIntakeTimetable.php';

const TT_MODES = [
    { value: 'ONLINE', label: 'Online', note: 'MS Teams' },
    { value: 'PHYSICAL', label: 'Physical', note: '' },
    { value: 'HYBRID', label: 'Hybrid', note: '' }
];

const ttState = {
    options: null,          // { subjects, lecturers_by_subject, lecturers }
    timetables: [],
    recipientCount: 0,
    cache: {}               // timetable_id => full timetable
};

/* ════════════════════════════════════════════════════════════════════════
   Helpers
════════════════════════════════════════════════════════════════════════ */

function ttEsc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
}

function ttBusy(btn, on, label) {
    if (!btn) return;
    if (on) {
        btn.dataset.label = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + (label || 'Please wait…');
    } else {
        btn.disabled = false;
        if (btn.dataset.label) btn.innerHTML = btn.dataset.label;
    }
}

async function ttApi(action, payload) {
    try {
        const res = await fetch(TT_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ action: action }, payload || {}))
        });
        const text = await res.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error(text);
            return { error: 'Unexpected server response.' };
        }
    } catch (e) {
        console.error(e);
        return { error: 'Network error. Please check your connection.' };
    }
}

function ttModal(id) {
    const el = document.getElementById(id);
    return el ? bootstrap.Modal.getOrCreateInstance(el) : null;
}

// "13:00" → "1.00 pm"
function ttFmtTime(t) {
    if (!t) return '';
    const [h, m] = t.split(':').map(Number);
    const h12 = h % 12 === 0 ? 12 : h % 12;
    return h12 + '.' + String(m).padStart(2, '0') + ' ' + (h >= 12 ? 'pm' : 'am');
}

// "2026-09-19" → "September 19, 2026"
function ttFmtDate(d) {
    if (!d) return '';
    const [y, m, day] = d.split('-').map(Number);
    const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July',
        'August', 'September', 'October', 'November', 'December'];
    return months[m - 1] + ' ' + day + ', ' + y;
}

function ttFmtDateTime(dt) {
    if (!dt) return '';
    const d = new Date(dt.replace(' ', 'T'));
    if (isNaN(d)) return dt;
    const pad = n => String(n).padStart(2, '0');
    let h = d.getHours();
    const ap = h >= 12 ? 'PM' : 'AM';
    h = h % 12 === 0 ? 12 : h % 12;
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' + pad(h) + ':' + pad(d.getMinutes()) + ' ' + ap;
}

function ttModeLabel(value) {
    const m = TT_MODES.find(x => x.value === value);
    return m ? m.label : value;
}

function ttModeNote(value) {
    return (TT_MODES.find(m => m.value === value) || {}).note || '';
}

function ttFindLecturer(userId) {
    if (!ttState.options) return null;
    return ttState.options.lecturers.find(l => l.id === Number(userId)) || null;
}

function ttLecturersFor(subjectId) {
    if (!ttState.options) return [];
    const bySubject = ttState.options.lecturers_by_subject || {};
    if (subjectId && bySubject[subjectId] && bySubject[subjectId].length) {
        return bySubject[subjectId];
    }
    return ttState.options.lecturers;
}

function ttLecturerDetails(lecturer) {
    if (!lecturer) return '';
    return lecturer.name + (lecturer.email ? '\nEmail: ' + lecturer.email : '');
}

// Cell texts shared by the preview and the PDF
function ttCellTexts(row) {
    const mode = ttModeLabel(row.delivery_mode) + (row.delivery_note ? '\n(' + row.delivery_note + ')' : '');
    const date = row.schedule_day + (row.commence_date ? '\n(Commence from ' + ttFmtDate(row.commence_date) + ')' : '');
    const time = ttFmtTime(row.start_time) + '\n–\n' + ttFmtTime(row.end_time);
    let lecturer = row.lecturer_details || '';
    if (!lecturer && row.lecturer_user_id) {
        const l = ttFindLecturer(row.lecturer_user_id);
        lecturer = l ? l.name : '';
    }
    return { module: row.module_label, mode: mode, date: date, time: time, lecturer: lecturer };
}

/* ════════════════════════════════════════════════════════════════════════
   Data
════════════════════════════════════════════════════════════════════════ */

async function ttLoadOptions() {
    if (ttState.options) return ttState.options;
    const r = await ttApi('options', { intake_id: TT_CFG.intakeId });
    ttState.options = r.data || { subjects: [], lecturers_by_subject: {}, lecturers: [] };
    return ttState.options;
}

async function ttLoadList() {
    const r = await ttApi('list', { intake_id: TT_CFG.intakeId });
    if (r.error) {
        showToast("error", r.error);
        return false;
    }
    ttState.timetables = r.data.timetables || [];
    ttState.recipientCount = r.data.recipient_count || 0;
    ttState.cache = {};
    return true;
}

async function ttGetTimetable(id) {
    if (ttState.cache[id]) return ttState.cache[id];
    const r = await ttApi('get', { timetable_id: id });
    if (r.error || !r.data) {
        showToast("error", r.error || 'Timetable not found.');
        return null;
    }
    ttState.cache[id] = r.data;
    return r.data;
}

function ttActive() {
    return ttState.timetables.find(t => t.status === 'ACTIVE') || null;
}

/* ════════════════════════════════════════════════════════════════════════
   Preview + PDF
════════════════════════════════════════════════════════════════════════ */

// Logo shown at the top of the preview and the PDF
const TT_LOGO_URL = TT_CFG.logoUrl || './assets/imgs/common/logo.png';
let ttLogo;   // undefined = not loaded yet, null = failed, { data, w, h } = ready

// Loads the logo once and converts it to a PNG data URL for jsPDF
function ttLoadLogo() {
    if (ttLogo !== undefined) return Promise.resolve(ttLogo);
    return new Promise(resolve => {
        const img = new Image();
        img.onload = () => {
            try {
                const canvas = document.createElement('canvas');
                canvas.width = img.naturalWidth;
                canvas.height = img.naturalHeight;
                canvas.getContext('2d').drawImage(img, 0, 0);
                ttLogo = { data: canvas.toDataURL('image/png'), w: img.naturalWidth, h: img.naturalHeight };
            } catch (e) {
                console.error(e);
                ttLogo = null;
            }
            resolve(ttLogo);
        };
        img.onerror = () => {
            console.error('Timetable logo not found: ' + TT_LOGO_URL);
            ttLogo = null;
            resolve(ttLogo);
        };
        img.src = TT_LOGO_URL;
    });
}

function ttPreviewHtml(tt) {
    let html = '<div class="text-center mb-2">' +
        '<img src="' + ttEsc(TT_LOGO_URL) + '" alt="' + ttEsc(TT_CFG.instituteName) + '" class="tt-preview-logo">' +
        '<div class="text-14 main-text">' + ttEsc(TT_CFG.programme) + ' — ' + ttEsc(TT_CFG.intake) + '</div>' +
        '<div class="text-16 main-text fw-semibold mt-1">' + ttEsc(tt.title) + '</div>' +
        '</div>';

    tt.sections.forEach(section => {
        if (section.heading) {
            html += '<div class="tt-preview-heading main-text">' + ttEsc(section.heading) + '</div>';
        }
        html += '<div class="table-responsive"><table class="tt-preview-table"><thead><tr>' +
            '<th style="width:16%">Module Code and Name</th>' +
            '<th class="tt-col-mode-h" style="width:12%">Delivery Mode</th>' +
            '<th class="tt-col-date" style="width:26%">Date</th>' +
            '<th style="width:12%">Time</th>' +
            '<th style="width:34%">Lecturer</th>' +
            '</tr></thead><tbody>';
        section.rows.forEach(row => {
            const c = ttCellTexts(row);
            html += '<tr>' +
                '<td>' + ttEsc(c.module) + '</td>' +
                '<td class="tt-mode">' + ttEsc(c.mode).replace(/\n/g, '<br>') + '</td>' +
                '<td class="tt-col-date fw-semibold">' + ttEsc(c.date).replace(/\n/g, '<br>') + '</td>' +
                '<td>' + ttEsc(c.time).replace(/\n/g, '<br>') + '</td>' +
                '<td class="tt-lecturer">' + ttEsc(c.lecturer) + '</td>' +
                '</tr>';
        });
        html += '</tbody></table></div>';
    });

    if (tt.note) {
        html += '<p class="text-14 sub-text mt-3 mb-0">' + ttEsc(tt.note) + '</p>';
    }
    return html;
}

function ttBuildPdf(tt) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
    const pageW = doc.internal.pageSize.getWidth();
    const pageH = doc.internal.pageSize.getHeight();
    const margin = 12;
    let y = 16;

    if (ttLogo) {
        // Logo centred at the top: max 50mm wide, 22mm high, aspect ratio kept
        let w = 50;
        let h = w * ttLogo.h / ttLogo.w;
        if (h > 22) {
            h = 22;
            w = h * ttLogo.w / ttLogo.h;
        }
        doc.addImage(ttLogo.data, 'PNG', (pageW - w) / 2, 8, w, h);
        y = 8 + h + 7;
    } else {
        // Fallback when the logo file cannot be loaded
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(14);
        doc.text(TT_CFG.instituteName || '', pageW / 2, y, { align: 'center' });
        y += 6;
    }
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10);
    doc.text((TT_CFG.programme || '') + ' — ' + (TT_CFG.intake || ''), pageW / 2, y, { align: 'center' });
    y += 6;
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(12);
    doc.text(tt.title, pageW / 2, y, { align: 'center' });
    y += 6;

    tt.sections.forEach(section => {
        if (section.heading) {
            doc.setFont('helvetica', 'bolditalic');
            doc.setFontSize(15);
            const lines = doc.splitTextToSize(section.heading, pageW - margin * 2);
            if (y + lines.length * 7 + 20 > pageH - margin) {
                doc.addPage();
                y = margin + 4;
            }
            y += 6;
            doc.text(lines, margin, y);
            y += lines.length * 6.5;
        } else {
            y += 4;
        }

        doc.autoTable({
            startY: y,
            margin: { left: margin, right: margin },
            head: [['Module Code and Name', 'Delivery Mode', 'Date', 'Time', 'Lecturer']],
            body: section.rows.map(row => {
                const c = ttCellTexts(row);
                return [c.module, c.mode, c.date, c.time, c.lecturer];
            }),
            theme: 'grid',
            styles: {
                font: 'helvetica', fontSize: 9, textColor: [20, 20, 20],
                lineColor: [0, 0, 0], lineWidth: 0.25,
                halign: 'center', valign: 'middle', cellPadding: 2
            },
            headStyles: { fillColor: [255, 255, 255], textColor: [0, 0, 0], fontStyle: 'bold', fontSize: 10 },
            columnStyles: {
                0: { cellWidth: 30 },
                1: { cellWidth: 24, textColor: [224, 0, 0], fontStyle: 'bold' },
                2: { cellWidth: 46, fillColor: [189, 215, 238], fontStyle: 'bold' },
                3: { cellWidth: 24 },
                4: { cellWidth: 'auto' }
            },
            didParseCell: data => {
                if (data.section === 'head') {
                    if (data.column.index === 1) data.cell.styles.textColor = [91, 26, 107];
                    if (data.column.index === 2) data.cell.styles.fillColor = [189, 215, 238];
                }
            }
        });
        y = doc.lastAutoTable.finalY + 4;
    });

    if (tt.note) {
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);
        const lines = doc.splitTextToSize(tt.note, pageW - margin * 2);
        if (y + lines.length * 5 > pageH - margin) {
            doc.addPage();
            y = margin;
        }
        doc.text(lines, margin, y + 4);
    }

    const pages = doc.internal.getNumberOfPages();
    for (let i = 1; i <= pages; i++) {
        doc.setPage(i);
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(8);
        doc.setTextColor(120);
        doc.text('Page ' + i + ' of ' + pages, pageW - margin, pageH - 6, { align: 'right' });
        doc.setTextColor(0);
    }
    return doc;
}

function ttPdfName(tt) {
    const base = (TT_CFG.intake || 'Intake') + ' ' + (tt.title || 'Timetable');
    return 'Timetable-' + base.replace(/[^A-Za-z0-9]+/g, '-').replace(/^-|-$/g, '') + '.pdf';
}

function ttPdfReady() {
    if (window.jspdf && window.jspdf.jsPDF) return true;
    showToast("error", 'The PDF library did not load. Please refresh the page.');
    return false;
}

async function ttSavePdf(tt, btn) {
    if (!ttPdfReady()) return;
    ttBusy(btn, true, 'Preparing…');
    try {
        await ttLoadLogo();
        ttBuildPdf(tt).save(ttPdfName(tt));
    } catch (e) {
        console.error(e);
        showToast("error", 'The PDF could not be created.');
    } finally {
        ttBusy(btn, false);
    }
}

/* ════════════════════════════════════════════════════════════════════════
   LIST PAGE — intake-summary-timetable.php
════════════════════════════════════════════════════════════════════════ */

async function initializeIntakeSummaryTimetables() {
    ttLoadLogo();
    await ttLoadOptions();
    if (await ttLoadList()) ttRenderList();
}

function filterIntakeSummaryTimetables() {
    const q = (document.getElementById('tt-search-text').value || '').trim().toLowerCase();
    document.querySelectorAll('#tt-list-body tr[data-search]').forEach(tr => {
        tr.classList.toggle('d-none', q !== '' && !tr.dataset.search.includes(q));
    });
}

function ttRenderList() {
    const body = document.getElementById('tt-list-body');
    const card = document.getElementById('tt-active-card');
    const active = ttActive();
    const total = ttState.timetables.length;

    document.getElementById('tt-count').textContent = total + (total === 1 ? ' Timetable' : ' Timetables');
    document.getElementById('tt-student-count').textContent = ttState.recipientCount + (ttState.recipientCount === 1 ? ' Student' : ' Students');

    // Active timetable block
    if (active) {
        card.innerHTML =
            '<div class="col-12 col-lg-7 p-0">' +
            '<span class="text-14 sub-text">Active Timetable</span>' +
            '<div class="sub-topic lh-lg">' + ttEsc(active.title) + '</div>' +
            '<div class="text-14 main-text">' + active.row_count + ' Module(s) &nbsp;|&nbsp; Last Sent: ' +
            (active.last_notified_at ? ttEsc(ttFmtDateTime(active.last_notified_at)) + ' (' + active.last_notified_count + ' students)' : 'Not sent yet') +
            '</div>' +
            '</div>' +
            '<div class="col-12 col-lg-5 p-0">' +
            '<div class="row gx-3 gy-2 justify-content-end m-0">' +
            '<div class="col-12 col-md-4"><button class="custom-btn w-100" data-act="view" data-id="' + active.id + '">View</button></div>' +
            '<div class="col-12 col-md-4"><button class="custom-btn w-100" data-act="pdf" data-id="' + active.id + '">PDF</button></div>' +
            (TT_CFG.canManage ? '<div class="col-12 col-md-4"><button class="custom-btn w-100" data-act="notify" data-id="' + active.id + '">Notify</button></div>' : '') +
            '</div>' +
            '</div>';
    } else {
        card.innerHTML =
            '<div class="col-12 p-0 d-flex align-items-center">' +
            '<iconify-icon icon="iwwa:info" width="22" height="22" class="sub-text me-2"></iconify-icon>' +
            '<span class="text-14 main-text">No active timetable for this intake yet.' +
            (TT_CFG.canManage ? ' Click <strong>Create Timetable</strong> to add one.' : '') + '</span>' +
            '</div>';
    }

    if (!total) {
        body.innerHTML =
            '<tr class="justify-content-center align-middle"><td colspan="7" class="align-middle justify-content-center">' +
            '<div class="d-flex flex-column align-items-center">' +
            '<img src="./assets/imgs/common/no-data.png" class="no-data-icon" alt="No data" />' +
            '</div></td></tr>';
        return;
    }

    body.innerHTML = ttState.timetables.map(t => {
        const isActive = t.status === 'ACTIVE';
        const hr = '<hr class="mt-1 mb-1 me-2 mx-2">';
        const item = (act, icon, text) =>
            '<li><a class="dropdown-item text-14 main-text d-flex align-items-center" href="#" data-act="' + act + '" data-id="' + t.id + '">' +
            '<iconify-icon icon="' + icon + '" width="20" height="20" class="sub-text me-3"></iconify-icon>' + text + '</a></li>';

        let menu = item('view', 'mdi:eye-outline', 'View') + hr + item('pdf', 'mdi:file-pdf-box', 'Download PDF');
        if (TT_CFG.canManage) {
            menu += hr + item('edit', 'ic:sharp-edit', 'Edit');
            if (isActive) {
                menu += hr + item('notify', 'lets-icons:send-fill', 'Notify Students');
                menu += hr + item('deactivate', 'lsicon:minus-filled', 'Mark Inactive');
            } else {
                menu += hr + item('activate', 'dashicons:plus-alt', 'Set as Active');
            }
        }

        return '<tr data-search="' + ttEsc((t.title + ' ' + (t.note || '') + ' ' + t.created_by + ' ' + t.status).toLowerCase()) + '">' +
            '<td class="px-4 py-3 align-middle">' +
            '<span class="text-14 main-text">' + ttEsc(t.title) + '</span>' +
            (t.note ? '<div class="text-12 sub-text">' + ttEsc(t.note.length > 70 ? t.note.substring(0, 70) + '…' : t.note) + '</div>' : '') +
            '</td>' +
            '<td class="py-2 align-middle"><span class="text-14 main-text">' + t.row_count + '</span></td>' +
            '<td class="py-2 align-middle"><span class="text-14 main-text">' + ttEsc(ttFmtDateTime(t.date_added)) + '</span></td>' +
            '<td class="py-2 align-middle"><span class="text-14 main-text">' + ttEsc(t.created_by) + '</span></td>' +
            '<td class="py-2 align-middle"><span class="text-14 main-text">' +
            (t.last_notified_at ? ttEsc(ttFmtDateTime(t.last_notified_at)) + '<br><span class="text-12 sub-text">' + t.last_notified_count + ' students</span>' : 'N/A') +
            '</span></td>' +
            '<td class="py-2 align-middle"><button class="table-btn ' + (isActive ? 'table-btn-success' : 'table-btn-danger') + ' w-100">' + (isActive ? 'Active' : 'Inactive') + '</button></td>' +
            '<td class="py-3 align-middle">' +
            '<div class="dropdown">' +
            '<iconify-icon icon="charm:menu-kebab" width="20" height="20" class="justify-content-center sub-text cursor-pointer" data-bs-toggle="dropdown" aria-expanded="false"></iconify-icon>' +
            '<ul class="dropdown-menu border-0 shadow br-5">' + menu + '</ul>' +
            '</div>' +
            '</td>' +
            '</tr>';
    }).join('');

    filterIntakeSummaryTimetables();
}

// List / active-card actions
document.addEventListener('click', function (e) {
    const el = e.target.closest('[data-act]');
    if (!el || !el.dataset.id) return;
    e.preventDefault();
    const id = Number(el.dataset.id);
    switch (el.dataset.act) {
        case 'view': ttOpenPreview(id); break;
        case 'pdf': ttDownload(id, el); break;
        case 'edit': window.location.href = 'add-intake-summary-timetable?intake-id=' + TT_CFG.intakeId + '&timetable-id=' + id; break;
        case 'notify': ttOpenNotify(id); break;
        case 'activate': ttConfirmStatus(id, 'ACTIVE'); break;
        case 'deactivate': ttConfirmStatus(id, 'INACTIVE'); break;
    }
});

async function ttOpenPreview(id) {
    const tt = await ttGetTimetable(id);
    if (!tt) return;
    document.getElementById('ttPreviewLabel').textContent = tt.title + (tt.status === 'ACTIVE' ? '' : ' (Inactive)');
    document.getElementById('tt-preview-body').innerHTML = ttPreviewHtml(tt);

    const dl = document.getElementById('tt-preview-download');
    dl.onclick = () => ttSavePdf(tt, dl);

    const nt = document.getElementById('tt-preview-notify');
    if (nt) {
        nt.parentElement.classList.toggle('d-none', tt.status !== 'ACTIVE');
        nt.onclick = () => {
            ttModal('ttPreviewModal').hide();
            ttOpenNotify(id);
        };
    }
    ttModal('ttPreviewModal').show();
}

async function ttDownload(id, btn) {
    const tt = await ttGetTimetable(id);
    if (tt) ttSavePdf(tt, btn.tagName === 'BUTTON' ? btn : null);
}

function ttOpenNotify(id) {
    const t = ttState.timetables.find(x => x.id === id);
    if (!t || t.status !== 'ACTIVE') {
        showToast("error", 'Only the active timetable can be sent to students.');
        return;
    }
    if (!ttState.recipientCount) {
        showToast("error", 'There are no active students with an email address in this intake.');
        return;
    }
    document.getElementById('tt-notify-title').value = t.title;
    document.getElementById('tt-notify-count').value = ttState.recipientCount + ' active student(s)';

    const warn = document.getElementById('tt-notify-warning');
    if (t.last_notified_at) {
        warn.textContent = 'This timetable was already sent on ' + ttFmtDateTime(t.last_notified_at) + '. Sending again will email everyone a second time.';
        warn.classList.remove('d-none');
    } else {
        warn.classList.add('d-none');
    }

    const btn = document.getElementById('tt-notify-confirm');
    btn.onclick = () => ttSendNotify(id, btn);
    ttModal('ttNotifyModal').show();
}

async function ttSendNotify(id, btn) {
    if (!ttPdfReady()) return;
    ttBusy(btn, true, 'Sending…');
    try {
        const tt = await ttGetTimetable(id);
        if (!tt) return;

        const form = new FormData();
        form.append('timetable_id', id);
        await ttLoadLogo();
        form.append('pdf', ttBuildPdf(tt).output('blob'), ttPdfName(tt));

        const res = await fetch(TT_NOTIFY_API, { method: 'POST', body: form });
        const text = await res.text();
        let r;
        try { r = JSON.parse(text); } catch (e) { console.error(text); r = { error: 'Unexpected server response.' }; }

        if (r.error) {
            showToast("error", r.error);
        } else {
            showToast("success", r.done);
            ttModal('ttNotifyModal').hide();
            if (await ttLoadList()) ttRenderList();
        }
    } catch (e) {
        console.error(e);
        showToast("error", 'The email could not be sent. Please try again.');
    } finally {
        ttBusy(btn, false);
    }
}

function ttConfirmStatus(id, status) {
    const t = ttState.timetables.find(x => x.id === id);
    const title = t ? t.title : 'this timetable';
    const active = ttActive();

    document.getElementById('ttStatusLabel').textContent = status === 'ACTIVE' ? 'Set as Active' : 'Mark Inactive';
    document.getElementById('tt-status-text').innerHTML = status === 'ACTIVE'
        ? 'Set <strong>' + ttEsc(title) + '</strong> as the active timetable?' +
        (active ? ' <strong>' + ttEsc(active.title) + '</strong> will become inactive.' : '')
        : 'Mark <strong>' + ttEsc(title) + '</strong> as inactive? This intake will have no active timetable until you set one.';

    const btn = document.getElementById('tt-status-confirm');
    btn.onclick = async () => {
        ttBusy(btn, true, 'Saving…');
        const r = await ttApi('set_status', { timetable_id: id, status: status });
        ttBusy(btn, false);
        if (r.error) {
            showToast("error", r.error);
            return;
        }
        showToast("success", r.done);
        ttModal('ttStatusModal').hide();
        if (await ttLoadList()) ttRenderList();
    };
    ttModal('ttStatusModal').show();
}

/* ════════════════════════════════════════════════════════════════════════
   FORM PAGE — add-intake-summary-timetable.php
════════════════════════════════════════════════════════════════════════ */

const ttSectionsEl = () => document.getElementById('tt-sections');

async function initializeAddIntakeSummaryTimetable() {
    ttLoadLogo();
    await Promise.all([ttLoadOptions(), ttLoadList()]);

    if (TT_CFG.timetableId > 0) {
        const tt = await ttGetTimetable(TT_CFG.timetableId);
        ttFillForm(tt);
    } else {
        ttFillForm(null);
        const active = ttActive();
        if (active) {
            document.getElementById('tt-replace-text').innerHTML =
                'Saving this timetable will make <strong>' + ttEsc(active.title) + '</strong> inactive. ' +
                'The new timetable becomes the active one. Use <strong>Start From Active</strong> to copy it and change only what is different.';
            document.getElementById('tt-replace-note').classList.remove('d-none');
        }
    }

    // Warn before leaving with unsaved changes
    window.ttDirty = false;
    ttSectionsEl().addEventListener('input', () => { window.ttDirty = true; });
    document.getElementById('tt-title').addEventListener('input', () => { window.ttDirty = true; });
    window.addEventListener('beforeunload', e => {
        if (window.ttDirty) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
}

function ttField(cols, label, required, inner) {
    return '<div class="' + cols + ' d-flex flex-column">' +
        '<label class="text-14 main-text ' + (required ? 'required' : 'not-required') + '">' + label + '</label>' +
        inner +
        '<span class="text-dark-red text-12 tt-fb"></span>' +
        '</div>';
}

function ttSubjectOptions(selected) {
    let html = '<option value="0">- Custom Module -</option>';
    let level = null;
    (ttState.options.subjects || []).forEach(s => {
        if (s.level !== level) {
            if (level !== null) html += '</optgroup>';
            html += '<optgroup label="' + ttEsc(s.level) + '">';
            level = s.level;
        }
        html += '<option value="' + s.id + '"' + (Number(selected) === s.id ? ' selected' : '') + '>' + ttEsc(s.name) + '</option>';
    });
    if (level !== null) html += '</optgroup>';
    return html;
}

function ttLecturerOptions(subjectId, selected) {
    let html = '<option value="0">- Select -</option>';
    let found = false;
    ttLecturersFor(Number(subjectId)).forEach(l => {
        const sel = Number(selected) === l.id;
        if (sel) found = true;
        html += '<option value="' + l.id + '"' + (sel ? ' selected' : '') + '>' + ttEsc(l.name) + '</option>';
    });
    // Keep a saved lecturer even if no longer assigned to the subject
    if (selected && !found) {
        const l = ttFindLecturer(selected);
        html += '<option value="' + Number(selected) + '" selected>' + ttEsc(l ? l.name : 'Lecturer #' + selected) + '</option>';
    }
    return html;
}

function ttModeOptions(selected, withEmpty) {
    return (withEmpty ? '<option value="">- Select -</option>' : '') +
        TT_MODES.map(m => '<option value="' + m.value + '"' + (selected === m.value ? ' selected' : '') + '>' + m.label + '</option>').join('');
}

function ttRenumber() {
    ttSectionsEl().querySelectorAll('.tt-section').forEach((sec, si) => {
        sec.querySelector('.tt-section-title').textContent = 'Section ' + (si + 1);
        const modules = sec.querySelectorAll('.tt-module');
        modules.forEach((m, mi) => {
            m.querySelector('.tt-module-title').textContent = 'Module ' + (mi + 1);
        });
        sec.querySelector('.tt-empty').classList.toggle('d-none', modules.length > 0);
    });
}

function addTimetableSection(withModule, data) {
    data = data || {};
    const sec = document.createElement('div');
    sec.className = 'col-12 p-0 g-0 mt-5 tt-section';
    sec.innerHTML =
        '<div class="row gx-4 gy-3">' +

        '<div class="col-12 d-flex justify-content-between align-items-center">' +
        '<span class="sub-topic tt-section-title">Section</span>' +
        '<div class="tt-section-actions">' +
        '<button type="button" class="tt-icon-btn" title="Duplicate section" data-sec="duplicate"><iconify-icon icon="mdi:content-duplicate" width="20" height="20" class="sub-text"></iconify-icon></button>' +
        '<button type="button" class="tt-icon-btn danger" title="Remove section" data-sec="remove"><iconify-icon icon="mdi:trash-can-outline" width="20" height="20" class="text-dark-red"></iconify-icon></button>' +
        '</div>' +
        '</div>' +

        ttField('col-12', 'Section Heading', false,
            '<input type="text" class="form-control custom-input mt-2 tt-heading" maxlength="255" ' +
            'placeholder="Optional, e.g. Please follow the schedule below for the last week of each month." value="' + ttEsc(data.heading || '') + '">') +

        // Quick fill
        '<div class="col-12">' +
        '<div class="tt-quickfill">' +
        '<div class="row gx-3 gy-3">' +
        '<div class="col-12 d-flex align-items-center">' +
        '<span class="text-14 main-text fw-medium">Quick Fill</span>' +
        '<span class="chip-info-icon me-0 d-flex align-items-center mx-1" data-tooltip="Fill these once and click Apply to All to copy them into every module of this section">' +
        '<iconify-icon icon="iwwa:info" width="18" height="18"></iconify-icon></span>' +
        '</div>' +
        ttField('col-12 col-md-6 col-lg-3', 'Delivery Mode', false, '<select class="form-select custom-select mt-2 qf-mode">' + ttModeOptions('', true) + '</select>') +
        ttField('col-12 col-md-6 col-lg-3', 'Delivery Details', false, '<input type="text" class="form-control custom-input mt-2 qf-note" maxlength="150" placeholder="MS Teams">') +
        ttField('col-12 col-md-6 col-lg-3', 'Day', false, '<input type="text" class="form-control custom-input mt-2 qf-day" list="tt-day-options" maxlength="100" placeholder="Every Saturday">') +
        ttField('col-12 col-md-6 col-lg-3', 'Commence From', false, '<input type="date" class="form-control custom-input mt-2 qf-commence">') +
        ttField('col-6 col-lg-3', 'Start Time', false, '<input type="time" class="form-control custom-input mt-2 qf-start">') +
        ttField('col-6 col-lg-3', 'End Time', false, '<input type="time" class="form-control custom-input mt-2 qf-end">') +
        '<div class="col-12 col-lg-6 d-flex flex-column justify-content-end">' +
        '<button type="button" class="custom-btn w-100" data-sec="apply">Apply to All Modules</button>' +
        '</div>' +
        '</div>' +
        '</div>' +
        '</div>' +

        '<div class="col-12"><div class="row gx-4 gy-3 tt-modules"></div></div>' +
        '<div class="col-12 tt-empty"><span class="text-14 sub-text">No modules in this section yet.</span></div>' +

        '<div class="col-12">' +
        '<div class="row gx-3 gy-2">' +
        '<div class="col-12 col-md-6 col-lg-3">' +
        '<button type="button" class="custom-btn d-flex align-items-center justify-content-center w-100" data-sec="add-module">' +
        'Add Module <iconify-icon icon="mdi:plus" width="20" height="20" class="text-white mx-2"></iconify-icon></button>' +
        '</div>' +
        '<div class="col-12 col-md-6 col-lg-3">' +
        '<button type="button" class="custom-btn d-flex align-items-center justify-content-center w-100" data-sec="add-all">' +
        'Add All Subjects <iconify-icon icon="mdi:playlist-plus" width="20" height="20" class="text-white mx-2"></iconify-icon></button>' +
        '</div>' +
        '</div>' +
        '</div>' +

        '</div>';

    ttSectionsEl().appendChild(sec);
    (data.rows || []).forEach(r => ttAddModule(sec, r));
    if (withModule && !(data.rows || []).length) ttAddModule(sec);
    ttRenumber();
    return sec;
}

function ttAddModule(sec, data) {
    data = data || {};
    const mode = data.delivery_mode || 'ONLINE';
    const note = data.delivery_note !== undefined ? data.delivery_note : ttModeNote(mode);

    const mod = document.createElement('div');
    mod.className = 'col-12 tt-module';
    mod.innerHTML =
        '<div class="tt-module-box">' +
        '<div class="tt-module-head">' +
        '<span class="text-14 main-text fw-medium tt-module-title">Module</span>' +
        '<div class="d-flex gap-1">' +
        '<button type="button" class="tt-icon-btn" title="Duplicate module" data-mod="duplicate"><iconify-icon icon="mdi:content-copy" width="18" height="18" class="sub-text"></iconify-icon></button>' +
        '<button type="button" class="tt-icon-btn danger" title="Remove module" data-mod="remove"><iconify-icon icon="mdi:close" width="18" height="18" class="text-dark-red"></iconify-icon></button>' +
        '</div>' +
        '</div>' +
        '<div class="row gx-4 gy-3">' +
        ttField('col-12 col-md-4', 'Subject', false,
            '<select class="form-select custom-select mt-2 r-subject">' + ttSubjectOptions(data.subject_id || 0) + '</select>') +
        ttField('col-12 col-md-8', 'Module Code and Name', true,
            '<input type="text" class="form-control custom-input mt-2 r-label" maxlength="255" placeholder="e.g. CPRH52 - Leadership and Quality in the Early Years" value="' + ttEsc(data.module_label || '') + '">') +

        ttField('col-12 col-md-6 col-lg-3', 'Delivery Mode', true,
            '<select class="form-select custom-select mt-2 r-mode">' + ttModeOptions(mode, false) + '</select>') +
        ttField('col-12 col-md-6 col-lg-3', 'Delivery Details', false,
            '<input type="text" class="form-control custom-input mt-2 r-note" maxlength="150" placeholder="MS Teams / AIC Campus, Colombo 05" value="' + ttEsc(note) + '">') +
        ttField('col-12 col-md-6 col-lg-3', 'Day', true,
            '<input type="text" class="form-control custom-input mt-2 r-day" list="tt-day-options" maxlength="100" placeholder="Every Saturday" value="' + ttEsc(data.schedule_day || '') + '">') +
        ttField('col-12 col-md-6 col-lg-3', 'Commence From', false,
            '<input type="date" class="form-control custom-input mt-2 r-commence" value="' + ttEsc(data.commence_date || '') + '">') +

        ttField('col-6 col-lg-3', 'Start Time', true,
            '<input type="time" class="form-control custom-input mt-2 r-start" value="' + ttEsc(data.start_time || '') + '">') +
        ttField('col-6 col-lg-3', 'End Time', true,
            '<input type="time" class="form-control custom-input mt-2 r-end" value="' + ttEsc(data.end_time || '') + '">') +
        ttField('col-12 col-lg-6', 'Lecturer', false,
            '<select class="form-select custom-select mt-2 r-lecturer">' + ttLecturerOptions(data.subject_id || 0, data.lecturer_user_id || 0) + '</select>') +

        ttField('col-12', 'Lecturer Details (shown in the timetable)', false,
            '<textarea class="form-control custom-input mt-2 r-details" rows="4" maxlength="1000" placeholder="Name, qualifications, email and mobile">' + ttEsc(data.lecturer_details || '') + '</textarea>') +
        '</div>' +
        '</div>';

    sec.querySelector('.tt-modules').appendChild(mod);
    ttRenumber();
    return mod;
}

function ttReadModule(mod) {
    return {
        subject_id: Number(mod.querySelector('.r-subject').value) || 0,
        module_label: mod.querySelector('.r-label').value.trim(),
        delivery_mode: mod.querySelector('.r-mode').value,
        delivery_note: mod.querySelector('.r-note').value.trim(),
        schedule_day: mod.querySelector('.r-day').value.trim(),
        commence_date: mod.querySelector('.r-commence').value,
        start_time: mod.querySelector('.r-start').value,
        end_time: mod.querySelector('.r-end').value,
        lecturer_user_id: Number(mod.querySelector('.r-lecturer').value) || 0,
        lecturer_details: mod.querySelector('.r-details').value.trim()
    };
}

function ttReadForm() {
    return {
        title: document.getElementById('tt-title').value.trim(),
        note: document.getElementById('tt-note').value.trim(),
        sections: Array.from(ttSectionsEl().querySelectorAll('.tt-section')).map(sec => ({
            heading: sec.querySelector('.tt-heading').value.trim(),
            rows: Array.from(sec.querySelectorAll('.tt-module')).map(ttReadModule)
        }))
    };
}

function ttFillForm(tt) {
    ttSectionsEl().innerHTML = '';
    document.getElementById('tt-title').value = tt ? tt.title : '';
    document.getElementById('tt-note').value = tt ? (tt.note || '') : '';
    if (tt && tt.sections && tt.sections.length) {
        tt.sections.forEach(s => addTimetableSection(false, s));
    } else {
        addTimetableSection(true);
    }
}

async function copyActiveTimetableIntoForm() {
    const active = ttActive();
    if (!active) return;
    const tt = await ttGetTimetable(active.id);
    if (!tt) return;
    const hasContent = ttReadForm().sections.some(s => s.rows.some(r => r.module_label || r.subject_id));
    if (hasContent && !confirm('Replace what you have entered with the active timetable?')) return;
    ttFillForm(Object.assign({}, tt, { title: tt.title + ' (Updated)' }));
    showToast("success", "Copied from the active timetable. Change what you need and save.");
}

function ttQuickFill(sec) {
    return {
        mode: sec.querySelector('.qf-mode').value,
        note: sec.querySelector('.qf-note').value.trim(),
        day: sec.querySelector('.qf-day').value.trim(),
        commence: sec.querySelector('.qf-commence').value,
        start: sec.querySelector('.qf-start').value,
        end: sec.querySelector('.qf-end').value
    };
}

function ttApplyQuickFill(sec) {
    const qf = ttQuickFill(sec);
    const modules = sec.querySelectorAll('.tt-module');
    if (!modules.length) {
        showToast("error", 'Add modules to this section first.');
        return;
    }
    if (!qf.mode && !qf.note && !qf.day && !qf.commence && !qf.start && !qf.end) {
        showToast("error", 'Fill at least one Quick Fill field first.');
        return;
    }
    modules.forEach(mod => {
        if (qf.mode) {
            mod.querySelector('.r-mode').value = qf.mode;
            if (!qf.note) mod.querySelector('.r-note').value = ttModeNote(qf.mode);
        }
        if (qf.note) mod.querySelector('.r-note').value = qf.note;
        if (qf.day) mod.querySelector('.r-day').value = qf.day;
        if (qf.commence) mod.querySelector('.r-commence').value = qf.commence;
        if (qf.start) mod.querySelector('.r-start').value = qf.start;
        if (qf.end) mod.querySelector('.r-end').value = qf.end;
        mod.querySelectorAll('.is-invalid').forEach(el => ttSetFb(el, ''));
    });
    window.ttDirty = true;
    showToast("success", 'Applied to ' + modules.length + ' module(s).');
}

function ttAddAllSubjects(sec) {
    const subjects = ttState.options.subjects || [];
    if (!subjects.length) {
        showToast("error", 'This intake has no subjects yet.');
        return;
    }
    const used = new Set(Array.from(sec.querySelectorAll('.r-subject')).map(s => Number(s.value)));

    // Drop a single untouched empty module first
    const modules = sec.querySelectorAll('.tt-module');
    if (modules.length === 1) {
        const r = ttReadModule(modules[0]);
        if (!r.subject_id && !r.module_label && !r.schedule_day && !r.start_time) modules[0].remove();
    }

    const qf = ttQuickFill(sec);
    let added = 0;
    subjects.forEach(s => {
        if (used.has(s.id)) return;
        const lecturers = ttState.options.lecturers_by_subject[s.id] || [];
        const lecturer = lecturers.length === 1 ? lecturers[0] : null;
        const mode = qf.mode || 'ONLINE';
        const mod = ttAddModule(sec, {
            subject_id: s.id,
            module_label: s.name,
            delivery_mode: mode,
            delivery_note: qf.note !== '' ? qf.note : ttModeNote(mode),
            schedule_day: qf.day,
            commence_date: qf.commence,
            start_time: qf.start,
            end_time: qf.end,
            lecturer_user_id: lecturer ? lecturer.id : 0,
            lecturer_details: lecturer ? ttLecturerDetails(lecturer) : ''
        });
        mod.querySelector('.r-label').dataset.auto = '1';
        if (lecturer) mod.querySelector('.r-details').dataset.auto = '1';
        added++;
    });
    ttRenumber();
    window.ttDirty = true;
    if (added) {
        showToast("success", added + ' subject(s) added.');
    } else {
        showToast("error", 'All subjects are already in this section.');
    }
}

// Section + module buttons
document.addEventListener('click', function (e) {
    const root = ttSectionsEl();
    if (!root || !root.contains(e.target)) return;

    const sec = e.target.closest('.tt-section');
    const secBtn = e.target.closest('[data-sec]');
    const modBtn = e.target.closest('[data-mod]');

    if (secBtn && sec) {
        switch (secBtn.dataset.sec) {
            case 'add-module': {
                // New module copies the schedule of the previous one
                const last = sec.querySelector('.tt-module:last-child');
                const base = last ? ttReadModule(last) : {};
                const mod = ttAddModule(sec, {
                    delivery_mode: base.delivery_mode,
                    delivery_note: base.delivery_note,
                    schedule_day: base.schedule_day,
                    commence_date: base.commence_date
                });
                mod.scrollIntoView({ behavior: 'smooth', block: 'center' });
                mod.querySelector('.r-subject').focus();
                break;
            }
            case 'add-all':
                ttAddAllSubjects(sec);
                break;
            case 'apply':
                ttApplyQuickFill(sec);
                break;
            case 'duplicate': {
                const clone = addTimetableSection(false, {
                    heading: sec.querySelector('.tt-heading').value,
                    rows: Array.from(sec.querySelectorAll('.tt-module')).map(ttReadModule)
                });
                sec.after(clone);
                ttRenumber();
                clone.scrollIntoView({ behavior: 'smooth', block: 'start' });
                break;
            }
            case 'remove':
                if (root.querySelectorAll('.tt-section').length === 1) {
                    showToast("error", 'A timetable needs at least one section.');
                    return;
                }
                if (sec.querySelectorAll('.tt-module').length && !confirm('Remove this section and its modules?')) return;
                sec.remove();
                ttRenumber();
                break;
        }
        window.ttDirty = true;
        return;
    }

    if (modBtn) {
        const mod = modBtn.closest('.tt-module');
        if (modBtn.dataset.mod === 'remove') {
            mod.remove();
        } else if (modBtn.dataset.mod === 'duplicate') {
            const copy = ttAddModule(sec, ttReadModule(mod));
            mod.after(copy);
        }
        ttRenumber();
        window.ttDirty = true;
    }
});

// Auto-fill inside a module
document.addEventListener('change', function (e) {
    const mod = e.target.closest('.tt-module');
    if (!mod) return;
    ttSetFb(e.target, '');

    if (e.target.classList.contains('r-subject')) {
        const subjectId = Number(e.target.value) || 0;
        const labelEl = mod.querySelector('.r-label');
        const subject = (ttState.options.subjects || []).find(s => s.id === subjectId);
        if (subject && (labelEl.value.trim() === '' || labelEl.dataset.auto === '1')) {
            labelEl.value = subject.name;
            labelEl.dataset.auto = '1';
            ttSetFb(labelEl, '');
        }
        const lecSel = mod.querySelector('.r-lecturer');
        const current = Number(lecSel.value) || 0;
        const keep = ttLecturersFor(subjectId).some(l => l.id === current) ? current : 0;
        lecSel.innerHTML = ttLecturerOptions(subjectId, keep);
        const own = ttState.options.lecturers_by_subject[subjectId] || [];
        if (!keep && subjectId && own.length === 1) {
            lecSel.value = String(own[0].id);
            lecSel.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    if (e.target.classList.contains('r-label')) e.target.dataset.auto = '0';
    if (e.target.classList.contains('r-details')) e.target.dataset.auto = '0';

    if (e.target.classList.contains('r-mode')) {
        const noteEl = mod.querySelector('.r-note');
        const known = TT_MODES.map(m => m.note).filter(Boolean);
        if (noteEl.value.trim() === '' || known.includes(noteEl.value.trim())) {
            noteEl.value = ttModeNote(e.target.value);
        }
    }

    if (e.target.classList.contains('r-lecturer')) {
        const details = mod.querySelector('.r-details');
        const lecturer = ttFindLecturer(e.target.value);
        if (lecturer && (details.value.trim() === '' || details.dataset.auto === '1')) {
            details.value = ttLecturerDetails(lecturer);
            details.dataset.auto = '1';
        }
    }
});

// Clear a field's error while typing
document.addEventListener('input', function (e) {
    if (e.target.classList && e.target.classList.contains('is-invalid')) ttSetFb(e.target, '');
});

/* ── Validation (field-level, same style as the other forms) ──────────── */

function ttSetFb(el, msg) {
    if (!el || !el.parentElement) return;
    const fb = el.parentElement.querySelector('.tt-fb') || document.getElementById(el.id + '-feedback');
    if (fb) fb.textContent = msg || '';
    el.classList.toggle('is-invalid', !!msg);
}

function ttValidateForm() {
    let first = null;
    const fail = (el, msg) => {
        ttSetFb(el, msg);
        if (!first) first = el;
    };

    document.querySelectorAll('#real-list .is-invalid').forEach(el => ttSetFb(el, ''));
    document.getElementById('tt-title-feedback').textContent = '';

    const titleEl = document.getElementById('tt-title');
    if (!titleEl.value.trim()) {
        titleEl.classList.add('is-invalid');
        document.getElementById('tt-title-feedback').textContent = 'Please enter a timetable title';
        first = titleEl;
    }

    const sections = ttSectionsEl().querySelectorAll('.tt-section');
    if (!sections.length) {
        showToast("error", 'Add at least one section.');
        return false;
    }

    sections.forEach((sec, si) => {
        const modules = sec.querySelectorAll('.tt-module');
        if (!modules.length) {
            showToast("error", 'Section ' + (si + 1) + ' has no modules.');
            if (!first) first = sec;
        }
        modules.forEach(mod => {
            const label = mod.querySelector('.r-label');
            const day = mod.querySelector('.r-day');
            const start = mod.querySelector('.r-start');
            const end = mod.querySelector('.r-end');
            if (!label.value.trim()) fail(label, 'Please enter the module code and name');
            if (!day.value.trim()) fail(day, 'Please enter the day');
            if (!start.value) fail(start, 'Please select a start time');
            if (!end.value) fail(end, 'Please select an end time');
            else if (start.value && end.value <= start.value) fail(end, 'End time must be after the start time');
        });
    });

    if (first) {
        first.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return false;
    }
    return true;
}

function ttShowServerErrors(list) {
    const box = document.getElementById('tt-errors');
    if (!list || !list.length) {
        box.classList.add('d-none');
        box.innerHTML = '';
        return;
    }
    box.innerHTML = '<div class="tt-error-box">' +
        '<span class="text-14 text-dark-red fw-medium">Please fix the following:</span>' +
        '<ul class="text-14 text-dark-red mb-0 mt-1 ps-3">' +
        list.slice(0, 12).map(x => '<li>' + ttEsc(x) + '</li>').join('') +
        (list.length > 12 ? '<li>…and ' + (list.length - 12) + ' more</li>' : '') +
        '</ul></div>';
    box.classList.remove('d-none');
    box.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/* ── Preview / save ───────────────────────────────────────────────────── */

function previewTimetableDraft() {
    const data = ttReadForm();
    const tt = {
        title: data.title || 'Untitled Timetable',
        note: data.note,
        sections: data.sections.map(s => ({
            heading: s.heading,
            rows: s.rows.filter(r => r.module_label || r.schedule_day)
        })).filter(s => s.rows.length)
    };
    if (!tt.sections.length) {
        showToast("error", 'Add at least one module to preview.');
        return;
    }
    document.getElementById('ttPreviewLabel').textContent = 'Preview — ' + tt.title;
    document.getElementById('tt-preview-body').innerHTML = ttPreviewHtml(tt);
    const dl = document.getElementById('tt-preview-download');
    dl.onclick = () => ttSavePdf(tt, dl);
    ttModal('ttPreviewModal').show();
}

async function saveIntakeSummaryTimetable(btn) {
    ttShowServerErrors([]);
    if (!ttValidateForm()) return;

    ttBusy(btn, true, 'Saving…');
    const r = await ttApi('save', Object.assign({
        intake_id: TT_CFG.intakeId,
        timetable_id: TT_CFG.timetableId
    }, ttReadForm()));
    ttBusy(btn, false);

    if (r.error) {
        ttShowServerErrors(r.errors && r.errors.length ? r.errors : [r.error]);
        showToast("error", r.error);
        return;
    }

    window.ttDirty = false;
    showToast("success", r.done);
    setTimeout(() => {
        window.location.href = 'intake-summary-timetable?intake-id=' + TT_CFG.intakeId;
    }, 1200);
}