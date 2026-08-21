// ============================================================
// Self-contained styling for concurrent-programme features.
// Injected at load time so the badge / tooltip formatting /
// accordion styling work even if lecture-schedule-concurrent.css
// was not linked in the page <head>. Safe to call multiple times
// (guarded by the #concurrent-lecture-styles id check).
// ============================================================
(function injectConcurrentStyles() {
    if (document.getElementById('concurrent-lecture-styles')) return;
    const style = document.createElement('style');
    style.id = 'concurrent-lecture-styles';
    style.textContent = `
        .subject-cover { position: relative; }
        .concurrent-badge {
            /* FIX #1: was top:-6px; right:-6px which caused the badge to
               spill outside .subject-cover and get clipped by the
               calendar <td>/<table> boundary, showing only half a circle.
               Pulled fully inside the box instead. */
            position: absolute; top: 2px; right: 2px;
            min-width: 20px; height: 20px; padding: 0 5px;
            border-radius: 999px; background: #2f7de1; color: #fff;
            font-size: 11px; font-weight: 700; line-height: 20px;
            text-align: center; box-shadow: 0 0 0 2px #fff, 0 2px 6px rgba(0,0,0,.25);
            z-index: 5; display: inline-flex; align-items: center; justify-content: center;
            animation: concurrentPulse 1.8s ease-in-out infinite;
        }
        .concurrent-badge-sm { position: static; margin-left: auto; box-shadow: none; min-width: 16px; height: 16px; font-size: 10px; line-height: 16px; animation: none; }
        .concurrent-badge-lg {
            /* FIX #2: was top:-8px; right:calc(50% - 60px), which positioned
               the badge relative to the whole centered .info-row instead of
               the image itself, so it only lined up by coincidence at one
               viewport width. Now positioned relative to a tight wrapper
               around just the image (see .lecture-main-image-wrap), anchored
               to the image's bottom-right corner. */
            top: auto; bottom: -8px; right: -8px;
            min-width: 28px; height: 28px; font-size: 14px; line-height: 28px;
        }
        .lecture-main-image-wrap { position: relative; display: inline-block; }
        @keyframes concurrentPulse {
            0% { box-shadow: 0 0 0 2px #fff, 0 0 0 0 rgba(47,125,225,.55); }
            70% { box-shadow: 0 0 0 2px #fff, 0 0 0 8px rgba(47,125,225,0); }
            100% { box-shadow: 0 0 0 2px #fff, 0 0 0 0 rgba(47,125,225,0); }
        }
        .lecture-item.is-concurrent, .tt-mini.is-concurrent { border-left: 3px solid #2f7de1; }
        .concurrent-tag {
            display: inline-flex; align-items: center; gap: 3px; font-size: 10px; font-weight: 600;
            color: #2f7de1; background: rgba(47,125,225,.1); border-radius: 999px; padding: 1px 6px;
            margin-left: 4px; vertical-align: middle;
        }
        .tt-mini-top { display: flex; align-items: center; justify-content: space-between; }
        .concurrent-alert {
            display: flex; align-items: center; gap: 10px; background: rgba(47,125,225,.08);
            border: 1px solid rgba(47,125,225,.25); color: #1a5cb8;
            border-radius: 0px !important;
            padding: 10px 14px; font-size: 13px; margin-bottom: 18px;
        }
        .programme-accordion .accordion-item.programme-accordion-item { border: 1px solid var(--light-theme); overflow: hidden; margin-bottom: 10px; }
        .programme-accordion .accordion-button { display: flex; align-items: center; gap: 12px; font-weight: 600; }
        .programme-accordion .accordion-button:not(.collapsed) { background: rgba(47,125,225,.06); box-shadow: none; color: inherit; }
        .programme-accordion-icon { width: auto !important; height: 36px; object-fit: cover; flex-shrink: 0; }
        .programme-accordion-title { display: flex; flex-direction: column; line-height: 1.3; }
        .programme-accordion-title .main-text { font-size: 14px; font-weight: 700; }
        .programme-accordion-title .sub-text { font-size: 12px; color: #7a8699; font-weight: 400; }
        .pro-pop-section-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: #7a8699; margin: 8px 0 4px; }
        .pro-pop-divider { border: none; border-top: 1px dashed #e7ebf0; margin: 8px 0; }
        .pro-pop-pill.concurrent { background: rgba(47,125,225,.12); color: #2f7de1; display: inline-flex; align-items: center; gap: 4px; }
        /* NEW: small muted branch tag shown next to a programme name in the
           concurrent programme summary (tooltip / popover). */
        .pro-pop-branch-tag { font-size: 10px; font-weight: 400; color: #7a8699; }

        /* FIX #3: the batch "Actions" dropdown was being clipped by the
           accordion-collapse / modal-body overflow. Give the menu a high
           z-index (above Bootstrap's .modal at 1055) as a safety net -
           the real fix is forcing Popper to 'fixed' strategy in
           initBatchActionDropdowns() below, which lets it escape any
           overflow:hidden ancestor entirely. */
        .batch-right .dropdown-menu { z-index: 1080; }
    `;
    document.head.appendChild(style);
})();

var currentSelectedMonth;
var currentSelectedYear;
const now = new Date();
let isSubjectLectureSchedule = false;
let subject = null;

const monthNames = [
    "January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December"
];


// Sample data structure for lectures
// Updated data structure with more details
// NOTE: each entry in lectures[date] is now a SESSION object. A session can
// span multiple programmes (concurrent lectures - same lecturer, same
// date/time, different programme). session.programmes is always an array
// (length 1 for a normal single-programme lecture, length > 1 when the
// lecturer is teaching two+ programmes at once). session.isConcurrent /
// session.programmeCount are set by the backend for convenience.
// NOTE: each programme entry (and, for the non-concurrent/flat case, the
// session itself) now also carries a `branch` field (branch name) resolved
// from faculty -> branch on the backend.
let lectures = {};
generateCalendar(now.getFullYear(), now.getMonth());

const getLectureScheduleData = () => {
    fetch('api/getLectureScheduleData')
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {

                lectures = response.lectures;
                generateCalendar(now.getFullYear(), now.getMonth());
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}
const getSubjectLectureScheduleData = (id) => {
    isSubjectLectureSchedule = true;
    subject = id;
    fetch(`api/getLectureScheduleData?subject=${id}`)
        .then((response) => response.text())
        .then((data) => {
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                lectures = response.lectures;
                generateCalendar(now.getFullYear(), now.getMonth());
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const getSelectedLecturerScheduleData = (lecturerd) => {
    fetch('api/getSelectedLecturerScheduleData?lecturer=' + lecturerd)
        .then((response) => response.text())
        .then((data) => {
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                lectures = response.lectures;
                generateCalendar(now.getFullYear(), now.getMonth());
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const getBatchStudentAttendanceData = (id) => {
    fetch('api/getBatchStudentAttendanceData?student=' + id)
        .then((response) => response.text())
        .then((data) => {
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {

                lectures = response.lectures;
                generateAttendnaceCalendar(now.getFullYear(), now.getMonth());
                initAttendancePopovers();
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}



const filterLectureSchedule = () => {
    const search_text = $("#search-input").val();
    fetch('api/getLectureScheduleData?text=' + search_text)
        .then((response) => response.text())
        .then((data) => {
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                lectures = response.lectures;
                generateCalendar(now.getFullYear(), now.getMonth());
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

// Builds "Programme A (Branch) - Subject X, Programme B (Branch) - Subject Y"
// style summary, used in tooltips / popovers for concurrent sessions.
// Each programme's branch (when present) is shown as a small tag next to
// the programme name so it's clear which branch that programme belongs to.
function getSessionProgrammeSummaryHtml(lecture) {
    if (!lecture.programmes || lecture.programmes.length === 0) {
        return `<div class="pro-pop-row"><span class="pro-pop-value">${lecture.subject || ''}</span></div>`;
    }
    return lecture.programmes.map(p => {
        const subjects = (p.batchList || []).map(b => b.subject).filter((v, i, a) => v && a.indexOf(v) === i).join(', ');
        return `
            <div class="pro-pop-row">
                <span class="pro-pop-label">${p.programme}${p.branch ? ` <span class="pro-pop-branch-tag">(${p.branch})</span>` : ''}</span>
                <span class="pro-pop-value">${subjects || '-'}</span>
            </div>`;
    }).join('');
}

function generateCalendar(year, month) {
    $("#month").attr("data-i18n", `${monthNames[month].toLowerCase()}`);
    $("#year").text(`${year}`);

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();

    currentSelectedMonth = month;
    currentSelectedYear = year;

    let table = `<table class='calendar-grid'><thead><tr>`;
    const daysOfWeek = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
    daysOfWeek.forEach(day => table += `<th>${day}</th>`);
    table += "</tr></thead><tbody><tr>";

    for (let i = 0; i < firstDay.getDay(); i++) {
        table += "<td class='empty-day'></td>";
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${(month + 1).toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
        const dayLectures = lectures[dateStr] || [];

        const cellHeight = dayLectures.length > 0 ? `${100 + (dayLectures.length * 70)}px` : "100px";

        table += `<td class='calendar-cell ${dayLectures.length > 0 ? 'has-lectures' : ''}' 
                  style='height:${cellHeight}' data-date='${dateStr}'>`;

        table += `<div class='day-header'>${day}</div>`;

        if (dayLectures.length > 0) {
            table += `<div class="lectures-container">`;

            dayLectures.forEach(lecture => {
                const isConcurrent = !!lecture.isConcurrent;
                table += `
                <div class="lecture-item ${lecture.status.toLowerCase()} ${isConcurrent ? 'is-concurrent' : ''}"
                     data-lecture-id="${lecture.lectureId}"
                     data-bs-toggle="tooltip"
                     data-id="${lecture.lectureId}"
                     data-lecturer="${lecture.lecturerName}"
                     >

                    <div class="subject-cover">
                        <img src="${lecture.programmeImage}" alt="${lecture.programme}"
                         onerror="this.src='https://dashboard.summersetlms.com/assets/imgs/common/empty-thumbnail.webp'">
                        ${isConcurrent ? `<span class="concurrent-badge" title="${lecture.programmeCount} programmes at this time">${lecture.programmeCount}</span>` : ''}
                    </div>

                    <div class="lecture-details">
                        <div class="lecture-subject">${lecture.title}${isConcurrent ? ` <span class="concurrent-tag"><iconify-icon icon="solar:users-group-rounded-bold"></iconify-icon> ${lecture.programmeCount} programmes</span>` : ''}</div>
                        <div class="lecture-meta">
                            <span class="lecturer-name">${lecture.lectureType}</span>
                            <span class="attendance-status">${lecture.status}</span>
                        </div>
                    </div>
                </div>`;
            });

            table += `</div>`;

        }

        table += `</td>`;

        if ((firstDay.getDay() + day) % 7 === 0) {
            table += "</tr><tr>";
        }


    }

    table += "</tr></tbody></table>";
    document.getElementById("calendar").innerHTML = table;
    initTooltips2();
    document.querySelectorAll("td[data-date]").forEach(cell => {
        cell.addEventListener("click", function () {
            const date = this.getAttribute("data-date");
            const dayLectures = lectures[date] || [];
            if (dayLectures.length === 0) {
                showLectureModal(date, dayLectures);
            }

        });
    });

    document.querySelectorAll(".lecture-item").forEach(item => {
        item.addEventListener("click", function () {
            const lectureId = this.getAttribute("data-lecture-id");
            showLectureDetails(lectureId);
        });
    });

}

function generateAttendnaceCalendar(year, month) {
    $("#monthYear").text('Attendance - ' + `${monthNames[month]} ${year}`);

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();

    currentSelectedMonth = month;
    currentSelectedYear = year;

    let table = `<table class='calendar-grid'><thead><tr>`;
    const daysOfWeek = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
    daysOfWeek.forEach(day => table += `<th>${day}</th>`);
    table += "</tr></thead><tbody><tr>";

    for (let i = 0; i < firstDay.getDay(); i++) {
        table += "<td class='empty-day'></td>";
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${(month + 1).toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
        const dayLectures = lectures[dateStr] || [];

        const cellHeight = dayLectures.length > 0 ? `${100 + (dayLectures.length * 70)}px` : "100px";

        table += `<td class='calendar-cell ${dayLectures.length > 0 ? 'has-lectures' : ''}' 
                  style='height:${cellHeight}' data-date='${dateStr}'>`;

        table += `<div class='day-header'>${day}</div>`;

        if (dayLectures.length > 0) {
            table += `<div class='events-stack'>`;
            dayLectures.forEach(lecture => {

                const statusKey = (lecture.status || '').toLowerCase(); // completed/upcoming/cancelled/ongoing
                const isConcurrent = !!lecture.isConcurrent;

                table += `
                  <div class="tt-mini ${statusKey} ${isConcurrent ? 'is-concurrent' : ''}" data-lecture-id="${lecture.lectureId}">
                    <div class="tt-mini-top">
                      <span class="tt-mini-icon">
                        ${ttStatusIcon(statusKey)}
                      </span>
                      ${isConcurrent ? `<span class="concurrent-badge concurrent-badge-sm" title="${lecture.programmeCount} programmes at this time">${lecture.programmeCount}</span>` : ''}
                    </div>
                 
                    <div class="tt-mini-title">${lecture.subject}</div>
                    <div class="tt-mini-time">${lecture.startTime} - ${lecture.endTime}</div>
                  </div>
                `;
            });

            table += `</div>`;
        }
        table += `</td>`;

        if ((firstDay.getDay() + day) % 7 === 0) {
            table += "</tr><tr>";
        }
    }

    table += "</tr></tbody></table>";
    document.getElementById("calendar").innerHTML = table;

    document.querySelectorAll("td[data-date]").forEach(cell => {
        cell.addEventListener("click", function () {
            const date = this.getAttribute("data-date");
            const dayLectures = lectures[date] || [];
            if (dayLectures.length === 0) {
                showLectureModal(date, dayLectures);
            }

        });
    });

    document.querySelectorAll(".tt-mini").forEach(item => {
        item.addEventListener("click", function (e) {
            e.stopPropagation();
            const lectureId = this.getAttribute("data-lecture-id");
            showLectureDetails(lectureId);
        });
    });


    document.querySelectorAll(".lecture-item").forEach(item => {
        item.addEventListener("click", function () {
            const lectureId = this.getAttribute("data-lecture-id");
            showLectureDetails(lectureId);
        });
    });

}


const prevMonthSchedule = () => {
    const year = currentSelectedMonth === 0 ? currentSelectedYear - 1 : currentSelectedYear;
    const month = currentSelectedMonth === 0 ? 11 : currentSelectedMonth - 1;
    generateCalendar(year, month);
    initializeLanguage();
};

const nextMonthSchedule = () => {
    const year = currentSelectedMonth === 11 ? currentSelectedYear + 1 : currentSelectedYear;
    const month = currentSelectedMonth === 11 ? 0 : currentSelectedMonth + 1;
    generateCalendar(year, month);
    initializeLanguage();
};

const prevMonth = (status = 1) => {
    const year = currentSelectedMonth === 0 ? currentSelectedYear - 1 : currentSelectedYear;
    const month = currentSelectedMonth === 0 ? 11 : currentSelectedMonth - 1;
    generateAttendnaceCalendar(year, month);
    initAttendancePopovers();

};

const nextMonth = (status = 1) => {
    const year = currentSelectedMonth === 11 ? currentSelectedYear + 1 : currentSelectedYear;
    const month = currentSelectedMonth === 11 ? 0 : currentSelectedMonth + 1;
    generateAttendnaceCalendar(year, month);
    initAttendancePopovers();
};

function showLectureModal(date, dayLectures) {
    const modalDateEl = document.getElementById("eventModalLabel");
    const modalContentEl = document.getElementById("eventDetails");

    // Format the date nicely
    const dateObj = new Date(date);
    const formattedDate = dateObj.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });

    modalDateEl.textContent = formattedDate;

    const inputDate = new Date(date);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    inputDate.setHours(0, 0, 0, 0);

    const isFutureOrToday = inputDate.getTime() >= today.getTime();

    if (dayLectures.length > 0) {
        modalContentEl.innerHTML = `
        <div class="lecture-cards-container">
            ${dayLectures.map(lecture =>
            `
            <div class="lecture-card ${lecture.status.toLowerCase()} d-flex flex-column">
                <div class="lecture-card-header ${lecture.status.toLowerCase()}">
                    <div class="subject-cover">
                        <img src="${lecture.programmeImage}" 
                             alt="${lecture.subject}"
                             onerror="this.src='https://dashboard.summersetlms.com/assets/imgs/common/empty-thumbnail.webp'">
                        ${lecture.isConcurrent ? `<span class="concurrent-badge" title="${lecture.programmeCount} programmes at this time">${lecture.programmeCount}</span>` : ''}
                    </div>
                    <div class="lecturer-meta">
                        <h4>${lecture.subject}</h4>
                        <div class="subject-badge">Lecture Type : ${lecture.lectureType}</div>
                        <div class="subject-badge">Branch : ${lecture.branch || 'N/A'}</div>
                        ${lecture.isConcurrent ? `<div class="subject-badge concurrent"><iconify-icon icon="solar:users-group-rounded-bold"></iconify-icon> ${lecture.programmeCount} programmes at this time</div>` : ''}
                    </div>
                    <div class="lecture-time">${lecture.startTime} - ${lecture.endTime}</div>
                </div>
                <div class="lecture-card-body">
                    <div class="d-flex flex-column">
                        <span class="lecture-time">Lecturer</span>
                        <span class="text-14 man-text">${lecture.lecturerName}</span>
                    </div>
                   
                    <div class="status-indicator">
                        <span class="status-dot ${lecture.status.toLowerCase()}"></span>
                        <span>${lecture.status}</span>
                    </div>
                </div>
               <div class="d-flex justify-content-between align-items-center mb-3 px-3">
               ${lecture.status === 'Ongoing' && lecture.lectureType === 'Online'
                ? `
                    <button class="table-btn table-btn-success active" onclick="window.open('${lecture.joinLink}', '_blank')">
                        Join Now
                    </button>
                `
                : ''}

                <div class="flex-grow-1"></div>
                <div class="dropdown">
                    <button class="table-btn table-btn-dark-theme dropdown-toggle br-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Action
                    </button>
                   ${isSubjectLectureSchedule ? `
                    <ul class="dropdown-menu z-3">
                        <li>
                            <a class="dropdown-item" href="lecture-details?lecture-id=${lecture.lectureId}&subject-id=${subject}">
                                Lecture Details
                            </a>
                        </li>

                        ${lecture.status === 'Ongoing'
                    ? `<li><a class="dropdown-item" href="lecture-attendance?lecture-id=${lecture.lectureId}&subject-id=${subject}">Attendance</a></li>`
                    : ''}

                        ${lecture.status === 'Completed'
                    ? `
                                <li><a class="dropdown-item" href="lecture-attendance?lecture-id=${lecture.lectureId}&subject-id=${subject}">Attendance</a></li>
                                <li><a class="dropdown-item" href="lecture-recording?lecture-id=${lecture.lectureId}&subject-id=${subject}">Lecture Recording</a></li>
                            `
                    : ''}
                    </ul>
                ` : `
                 <ul class="dropdown-menu z-3">
                        <li>
                            <a class="dropdown-item" href="lecture-details?lecture-id=${lecture.lectureId}">
                                Lecture Details
                            </a>
                        </li>

                        ${lecture.status === 'Ongoing'
                ? `<li><a class="dropdown-item" href="lecture-attendance?lecture-id=${lecture.lectureId}">Attendance</a></li>`
                : ''}

                        ${lecture.status === 'Completed'
                ? `
                                <li><a class="dropdown-item" href="lecture-attendance?lecture-id=${lecture.lectureId}">Attendance</a></li>
                                <li><a class="dropdown-item" href="lecture-recording?lecture-id=${lecture.lectureId}">Lecture Recording</a></li>
                            `
                : ''}
                    </ul>
                `}
                    </ul>
                </div>
            </div>

            </div>
            `).join('')}
                ${isFutureOrToday ? `
            <button class="custom-btn mt-3 px-4" onclick="window.location.href = 'subject-schedule-lecture?subject-id=${subject}&date=${date}'">
                        Schedule a Lecture
            </button>
            ` : ''}
        </div>`;
    } else {
        modalContentEl.innerHTML = `
        <div class="no-lectures">
            <div class="no-lectures-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" fill="#6c757d" viewBox="0 0 16 16">
                    <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                    <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
                </svg>
            </div>
            <h5>No Lectures Scheduled</h5>
            <p>There are no classes scheduled for this day</p>
            ${isFutureOrToday ?
                isSubjectLectureSchedule ? `
            <button class="custom-btn mt-3 px-4" onclick="window.location.href = 'subject-schedule-lecture?subject-id=${subject}&date=${date}'">
                        Schedule a Lecture
            </button>
            `: `
             <button class="custom-btn mt-3 px-4" onclick="window.location.href = 'schedule-lecture?date=${date}'">
                        Schedule a Lecture
            </button>
            ` : ''}
           
        </div>`;
    }

    // Show the modal
    const modal = new bootstrap.Modal(document.getElementById('eventModal'));
    modal.show();
}

// Builds one accordion panel (icon, programme name, faculty, branch, batch
// count, per-programme "Edit This Programme's Batches" action, and a batch
// list with individual per-batch Edit/Attendance/Recording actions).
function buildProgrammeAccordionItem(lecture, prog, index, isOnlyProgramme) {
    const accordionId = `programmeAccordion-${prog.programmeId}-${index}`;
    const isFirst = index === 0;

    const batchRows = (prog.batchList || []).map(batch => `
        <div class="batch-item">
            <div class="batch-left">
                <div class="batch-cover-wrap">
                    <img src="./assets/imgs/intakes-covers/${batch.batch_cover}"
                         alt="${batch.batch_name || 'Batch'}"
                         class="batch-cover"
                         onerror="this.src='./assets/imgs/common/empty-thumbnail.webp'">
                </div>
                <div class="batch-content">
                    <div class="batch-top-line">
                        <span class="batch-name">${batch.batch_name || 'N/A'}</span>
                        <span class="batch-level">${batch.subject || 'N/A'}</span>
                    </div>
                </div>
            </div>

            <div class="batch-right">
                ${(lecture.hasPermissionEditPermission || lecture.hasPermissionConfirmLecturerPayment || lecture.hasPermissionUploadRecording || lecture.hasPermissionMarkAttendance) ? `
                <div class="dropdown">
                    <button type="button" class="custom-btn btn-action px-4 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        Actions
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        ${lecture.hasPermissionEditPermission ? `
                        <li>
                            <a class="dropdown-item" href="edit-lecture?lecture-id=${batch.lecture_id}${isSubjectLectureSchedule ? '&subject-id=' + batch.subject_id : ''}">
                                <i class="fas fa-edit me-2 text-warning"></i>Edit This Batch
                            </a>
                        </li>` : ''}

                        ${(lecture.status === 'Ongoing' || lecture.status === 'Completed') && lecture.hasPermissionMarkAttendance ? `
                        <li>
                            <a class="dropdown-item" href="lecture-attendance?lecture-id=${batch.lecture_id}${isSubjectLectureSchedule ? '&subject-id=' + batch.subject_id : ''}">
                                <i class="fas fa-user-check me-2 text-success"></i>Attendance
                            </a>
                        </li>` : ''}

                        ${lecture.status === 'Completed' && lecture.hasPermissionUploadRecording ? `
                        <li>
                            <a class="dropdown-item" href="lecture-recording?lecture-id=${batch.lecture_id}${isSubjectLectureSchedule ? '&subject-id=' + batch.subject_id : ''}">
                                <i class="fas fa-video me-2 text-primary"></i>Lecture Recording
                            </a>
                        </li>` : ''}

                        ${(lecture.status === 'Postponed' || lecture.status === 'Upcoming' || lecture.status === 'Cancelled' || lecture.status === 'Study Leave') && lecture.hasPermissionEditPermission ? `
                        <li>
                            <a class="dropdown-item text-danger" onclick="removeLectureOpenModal(${batch.lecture_id})" href="#">
                                <i class="fas fa-trash me-2"></i>Remove
                            </a>
                        </li>` : ''}
                    </ul>
                </div>` : ''}
            </div>
        </div>
    `).join('');

    return `
    <div class="accordion-item programme-accordion-item">
        <h2 class="accordion-header" id="heading-${accordionId}">
            <button class="accordion-button ${isFirst ? '' : 'collapsed'}" type="button"
                data-bs-toggle="collapse" data-bs-target="#collapse-${accordionId}"
                aria-expanded="${isFirst ? 'true' : 'false'}" aria-controls="collapse-${accordionId}">
                <img src="${prog.programmeImage}" alt="${prog.programme}" class="programme-accordion-icon"
                     onerror="this.src='./assets/imgs/common/empty-thumbnail.webp'">
                <span class="programme-accordion-title">
                    <span class="main-text">${prog.programme}</span>
                    <span class="sub-text">${prog.faculty} &middot; ${prog.branch || ''} &middot; ${(prog.batchList || []).length} batch(es)</span>
                </span>
            </button>
        </h2>
        <div id="collapse-${accordionId}" class="accordion-collapse collapse ${isFirst ? 'show' : ''}"
             aria-labelledby="heading-${accordionId}">
            <div class="accordion-body">
                ${lecture.hasPermissionEditPermission ? `
                <div class="d-flex justify-content-end mb-2">
                    <button class="table-btn table-btn-dark-theme" type="button"
                        onclick="window.location='edit-all-concurent-lectures?lecture-id=${(prog.batchList && prog.batchList[0]) ? prog.batchList[0].lecture_id : ''}&programme-id=${prog.programmeId}'">
                        <iconify-icon icon="line-md:edit-twotone" width="16" height="16"></iconify-icon>
                        Edit This Programme's Batches
                    </button>
                </div>` : ''}
                <div class="batch-list">
                    ${batchRows || `<div class="batch-empty-state">No batches assigned for this programme yet.</div>`}
                </div>
            </div>
        </div>
    </div>`;
}

// FIX #3: initializes (and re-initializes) the per-batch "Actions"
// dropdown buttons every time the accordion HTML is rebuilt. Forces
// Popper's positioning strategy to 'fixed' so the menu is positioned
// relative to the viewport instead of the nearest positioned/overflow
// ancestor - this lets it escape the modal-body / accordion-collapse
// overflow clipping that was cutting the menu off in the screenshot.
function initBatchActionDropdowns() {
    document.querySelectorAll('.batch-right .dropdown-toggle').forEach(el => {
        const existing = bootstrap.Dropdown.getInstance(el);
        if (existing) existing.dispose();

        new bootstrap.Dropdown(el, {
            popperConfig: (defaultConfig) => ({
                ...defaultConfig,
                strategy: 'fixed'
            })
        });
    });
}

function showLectureDetails(lectureId) {
    const allLecturesArray = Object.values(lectures).flat();
    const lecture = allLecturesArray.find(l => l.lectureId == lectureId);

    if (!lecture) {
        return;
    }

    const modal = new bootstrap.Modal(document.getElementById('lectureDetailsModal'));
    const modalTitle = document.getElementById('lectureDetailsTitle');
    const modalBody = document.getElementById('lectureDetailsBody');
    const modalFooter = document.getElementById('lectureDetailsFooter');

    modalTitle.textContent = `${lecture.title}`;

    const programmes = lecture.programmes && lecture.programmes.length ? lecture.programmes : [];
    const accordionId = `lectureProgAccordion-${lecture.lectureId}`;

    modalBody.innerHTML = `
    <div class="col-lg-12">
    <div class="lecture-card border-0 shadow-none">
        <div class="lecture-body px-4 py-4">

            <!-- Main Lecture Image -->
            <!-- FIX #2: badge now lives inside a wrapper sized to just the
                 image (.lecture-main-image-wrap), instead of being
                 positioned relative to the full-width, centered .info-row.
                 That's what made it float in an unrelated spot before. -->
            <div class="info-row border-0 justify-content-center mb-4">
                <div class="lecture-main-image-wrap">
                    <img src="${lecture.programmeImage}" 
                         alt="${lecture.subject}" 
                         class="lecture-main-image"
                         onerror="this.src='./assets/imgs/common/empty-thumbnail.webp'">
                    ${lecture.isConcurrent ? `<span class="concurrent-badge concurrent-badge-lg" title="${lecture.programmeCount} programmes at this time">${lecture.programmeCount}</span>` : ''}
                </div>
            </div>

            ${lecture.isConcurrent ? `
            <div class="concurrent-alert">
                <iconify-icon icon="solar:users-group-rounded-bold" width="20" height="20"></iconify-icon>
                <span>This lecturer is teaching <strong>${lecture.programmeCount} programmes</strong> at the same time slot. Expand each programme below to view or edit its batches.</span>
            </div>` : ''}

            <!-- Lecture Details -->
            <div class="info-row">
                <div class="info-label">Lecturer:</div>
                <div class="info-value">
                <img src="${lecture.lecturerProfile}" alt="${lecture.lecturerName}" class="lecturer-profile-img me-2 hwr-40" onerror="this.src='./assets/imgs/common/empty-img.png'">
                ${lecture.lecturerName || 'N/A'}
                </div>
            </div>

            <div class="info-row">
                <div class="info-label">Date & Time:</div>
                <div class="info-value">
                    ${lecture.date || 'N/A'}, ${lecture.startTime || 'N/A'} - ${lecture.endTime || 'N/A'}
                </div>
            </div>

            ${!lecture.isConcurrent ? `
            <div class="info-row">
                <div class="info-label">Branch:</div>
                <div class="info-value">${lecture.branch || 'N/A'}</div>
            </div>
            ` : ''}

            <div class="info-row m-0">
                <div class="info-label">Conduct Status:</div>
                <div class="info-value">
                    <span class="status-badge status-${(lecture.status || '').toLowerCase().replace(/\s+/g, '-')}">
                        ${lecture.status || 'N/A'}
                    </span>
                </div>
            </div>

            <!-- Batch Section (now grouped by programme, accordion style) -->
            <div class="batch-section mt-4">
                <div class="batch-section-header">
                    <div class="batch-title-wrap">
                        <div class="batch-section-title">${lecture.isConcurrent ? 'Programmes & Batches' : 'Assigned Batches'}</div>
                        <div class="batch-section-subtitle">
                            ${programmes.length} programme(s) &middot; ${(lecture.batchList || []).length} batch(es) linked to this lecture
                        </div>
                    </div>

                    ${lecture.isConcurrent && lecture.hasPermissionEditPermission ? `
                    <button class="btn-edit-batches custom-btn px-2" type="button"
                        onclick="window.location='edit-all-concurent-lectures?lecture-id=${lecture.lectureId}'">
                         <iconify-icon icon="line-md:edit-twotone" width="20" height="20" class="text-white"></iconify-icon>
                        Edit All Programmes
                    </button>` : (lecture.hasPermissionEditPermission && programmes[0] && programmes[0].batchList && programmes[0].batchList[0] ? `
                    <button class="btn-edit-batches custom-btn px-2" type="button"
                        onclick="window.location='edit-all-concurent-lectures?lecture-id=${programmes[0].batchList[0].lecture_id}'">
                         <iconify-icon icon="line-md:edit-twotone" width="20" height="20" class="text-white"></iconify-icon>
                        Edit
                    </button>` : '')}
                </div>

                <div class="accordion programme-accordion" id="${accordionId}">
                    ${programmes.length
            ? programmes.map((prog, idx) => buildProgrammeAccordionItem(lecture, prog, idx, programmes.length === 1)).join('')
            : `<div class="batch-empty-state">No batches assigned for this lecture yet.</div>`
        }
                </div>
            </div>

        </div>
    </div>
</div>
`;

    // FIX #3 (cont.): (re)bind the batch Actions dropdowns every time this
    // modal body is rebuilt, so the 'fixed' Popper strategy is applied and
    // the menu no longer gets clipped by the accordion/modal overflow.
    initBatchActionDropdowns();

    let footerHTML = "";

    // ✅ Ongoing + Online → Join button
    if (lecture.status === 'Ongoing' && lecture.lectureType === 'Online') {
        footerHTML += `
        <div class="action-buttons px-4 justify-content-end">
            <button class="custom-success-btn px-5"
                onclick="window.open('${lecture.joinLink}', '_blank')">
                Join Now
            </button>
        </div>
    `;
    }

    // ✅ Completed → Upload Recording
    if (lecture.status === 'Completed' && lecture.hasPermissionUploadRecording) {
        const timeTableId = programmes[0]?.batchList?.[0]?.lecture_id || lecture.lectureId;
        const subjectId = programmes[0]?.batchList?.[0]?.subject_id;
        footerHTML += `
        <div class="action-buttons px-4 justify-content-end">
            <button class="custom-btn px-5"
                onclick="window.location.href='lecture-recording?lecture-id=${timeTableId}${isSubjectLectureSchedule ? '&subject-id=' + subjectId : ''}'">
                lecture Recording
            </button>
        </div>
    `;
    }

    // ✅ Set HTML
    modalFooter.innerHTML = footerHTML;
    modal.show();
}

function initTooltips() {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {

        // 🔴 Destroy existing tooltip (VERY IMPORTANT)
        if (bootstrap.Tooltip.getInstance(el)) {
            bootstrap.Tooltip.getInstance(el).dispose();
        }

        const image = el.dataset.image;
        const batch = el.dataset.batch;
        const lecturer = el.dataset.lecturer;

        const tooltipHTML = `
            <div class="lecture-tooltip">
                <img src="${image}" class="tooltip-cover">
                <div class="tooltip-info d-flex flex-column justify-content-center align-content-start">
                    <div class="tooltip-batch">${batch}</div>
                    <div class="tooltip-lecturer">${lecturer}</div>
                </div>
            </div>
        `;

        new bootstrap.Tooltip(el, {
            title: tooltipHTML,
            html: true,
            sanitize: false,
            trigger: 'hover focus',
            container: 'body' // ✅ REQUIRED for dynamic layouts
        });
    });
}

function initAttendancePopovers() {

    // Remove old popovers (important when rerender calendar)
    document.querySelectorAll('.tt-mini').forEach(el => {
        const instance = bootstrap.Popover.getInstance(el);
        if (instance) instance.dispose();
    });

    document.querySelectorAll('.tt-mini').forEach(el => {
        let lectureId = el.dataset.lectureId;
        const allLecturesArray = Object.values(lectures).flat();
        const lecture = allLecturesArray.find(l => l.lectureId == lectureId);

        const name = lecture.subject || 'Subject';
        const lecturer = lecture.lecturerName || lecture.lecturer || 'Lecture';
        const type = lecture.lectureType || '';
        const start = lecture.startTime || '';
        const end = lecture.endTime || '';
        const thumb = lecture.programmeImage || lecture.subjectImage || '';
        const id = lecture.lectureId || '';
        const date = lecture.date || '';
        const status = lecture.status || '';
        const branch = lecture.branch || '';

        const statusClass = (lecture.status || '').toLowerCase();

        const content = `
        <div class="pro-pop border-0">
          <div class="pro-pop-head">
            <div class="pro-pop-icon">
              <img src="${thumb}" onerror="this.src='https://dashboard.enviiz.com/assets/imgs/common/empty-thumbnail.webp'">
            </div>
  
            <div class="pro-pop-titlewrap">
              <div class="pro-pop-title">${name}</div>
               <div class="pro-pop-sub">
                <span class="pro-pop-pill ${statusClass}">${status}</span>
                ${lecture.isConcurrent ? `<span class="pro-pop-pill concurrent">${lecture.programmeCount} programmes</span>` : ''}
              </div>
            </div>
          </div>

          ${lecture.isConcurrent ? `
          <div class="pro-pop-section-label">Programmes in this slot</div>
          ${getSessionProgrammeSummaryHtml(lecture)}
          ` : `
          <div class="pro-pop-row">
            <span class="pro-pop-label">Branch</span>
            <span class="pro-pop-value">${branch || '-'}</span>
          </div>
          `}
  
           <div class="pro-pop-row">
            <span class="pro-pop-label">Lecturer</span>
            <span class="pro-pop-value">${lecturer}</span>
          </div>
        <div class="pro-pop-row">
            <span class="pro-pop-label">Date</span>
            <span class="pro-pop-value">${date}</span>
          </div>

          <div class="pro-pop-row">
            <span class="pro-pop-label">Time</span>
            <span class="pro-pop-value">${start} - ${end}</span>
          </div>
          
            <div class="pro-pop-row">
            <span class="pro-pop-label">Attendance Status</span>
            <span class="pro-pop-value">${status}</span>
          </div>
           
           
        </div>
      `;

        new bootstrap.Popover(el, {
            trigger: 'hover focus',
            html: true,
            placement: 'auto',
            container: 'body',
            customClass: 'pro-popover',
            title: '',
            content
        });
    });
}

function initTooltips2() {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {

        // destroy old tooltip
        const old = bootstrap.Tooltip.getInstance(el);
        if (old) old.dispose();

        const lectureId = el.dataset.id;
        const allLecturesArray = Object.values(lectures).flat();
        const lecture = allLecturesArray.find(l => l.lectureId == lectureId);

        const image = lecture.programmeImage || '';
        const title = lecture.title || lecture.batch || 'Lecture';
        const programme = lecture.programme || '';
        const subject = lecture.subject || '';
        const branch = lecture.branch || '';
        const time = lecture.startTime + ' - ' + lecture.endTime || '';

        const name = lecture.title;
        const date = lecture.date;
        const type = lecture.lectureType;
        const status = lecture.status;

        const lecturer = lecture.lecturerName;

        const typeClass = (type || '').toLowerCase();
        const statusClass = (status || '').toLowerCase();

        const content = `
        <div class="pro-pop border-0">
          <div class="pro-pop-head">
            <div class="pro-pop-icon">
              <img src="${image}" onerror="this.src='https://dashboard.enviiz.com/assets/imgs/common/empty-thumbnail.webp'">
            </div>
  
            <div class="pro-pop-titlewrap">
            
              <div class="pro-pop-title text-start pro-pop-value mt-2">${name}</div>
              <div class="pro-pop-sub">
                <span class="pro-pop-pill ${typeClass}">${type} Lecture</span>
                ${lecture.isConcurrent ? `<span class="pro-pop-pill concurrent"><iconify-icon icon="solar:users-group-rounded-bold"></iconify-icon> ${lecture.programmeCount} programmes</span>` : ''}
              </div>
            </div>
          </div>

          ${lecture.isConcurrent ? `
          <div class="pro-pop-section-label">Programmes in this slot</div>
          ${getSessionProgrammeSummaryHtml(lecture)}
          <hr class="pro-pop-divider">
          ` : `
           <div class="pro-pop-row">
            <span class="pro-pop-label">Programme</span>
            <span class="pro-pop-value">${programme}</span>
          </div>

           <div class="pro-pop-row">
            <span class="pro-pop-label">Branch</span>
            <span class="pro-pop-value">${branch}</span>
          </div>

           <div class="pro-pop-row">
            <span class="pro-pop-label">Subject</span>
            <span class="pro-pop-value">${subject}</span>
          </div>
          `}
       
          <div class="pro-pop-row">
            <span class="pro-pop-label">Lcturer</span>
            <span class="pro-pop-value">${lecturer}</span>
          </div>

           <div class="pro-pop-row">
            <span class="pro-pop-label">Date</span>
            <span class="pro-pop-value">${date}</span>
          </div>

          <div class="pro-pop-row">
            <span class="pro-pop-label">Time</span>
            <span class="pro-pop-value">${time}</span>
          </div>
          <div class="pro-pop-row">
            <span class="pro-pop-label">Status</span>
            <span class="pro-pop-pill ${statusClass}">${status.toUpperCase()}</span>
          </div>
           
          
        </div>
      `;
        new bootstrap.Tooltip(el, {
            title: content,
            html: true,
            sanitize: false,
            trigger: 'hover focus',
            container: 'body',
            placement: 'auto',
            boundary: 'window',
            offset: [0, 10],
            customClass: 'tt-premium-tooltip',
            // IMPORTANT: makes Bootstrap keep your padding/rounded/shadow correctly
            template: `
          <div class="tooltip tt-premium-tooltip" role="tooltip">
            <div class="tooltip-arrow"></div>
            <div class="tooltip-inner"></div>
          </div>
        `
        });
    });
}
function ttStatusIcon(status) {
    if (status.includes("completed")) return `<iconify-icon icon="solar:check-circle-bold"></iconify-icon>`;
    if (status.includes("cancel")) return `<iconify-icon icon="solar:close-circle-bold"></iconify-icon>`;
    if (status.includes("ongoing")) return `<iconify-icon icon="solar:play-circle-bold"></iconify-icon>`;
    if (status.includes("pending")) return `<iconify-icon icon="tabler:reload"></iconify-icon>`;
    if (status.includes("present")) return `<iconify-icon icon="ph:seal-check-bold"></iconify-icon>`;
    if (status.includes("absent")) return `<iconify-icon icon="carbon:close-outline"></iconify-icon>`;
    if (status.includes("watched")) return `<iconify-icon icon="mynaui:video"></iconify-icon>`;
    return `<iconify-icon icon="solar:clock-circle-bold"></iconify-icon>`; // upcoming/default
}