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
let lectures = {};
generateCalendar(now.getFullYear(), now.getMonth());

const getLectureScheduleData = () => {
    fetch('api/getLectureScheduleData')
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
                table += `
                <div class="lecture-item ${lecture.status.toLowerCase()}"
                     data-lecture-id="${lecture.lectureId}"
                     data-bs-toggle="tooltip"
                     data-id="${lecture.lectureId}"
                     
                     data-lecturer="${lecture.lecturerName}"
                     >
        
                    <div class="subject-cover">
                        <img src="${lecture.programmeImage}" alt="${lecture.programme}"
                         onerror="this.src='https://dashboard.summersetlms.com/assets/imgs/common/empty-thumbnail.webp'">
                    </div>
        
                    <div class="lecture-details">
                        <div class="lecture-subject">${lecture.title}</div>
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

                table += `
                  <div class="tt-mini ${statusKey}" data-lecture-id="${lecture.lectureId}">
                    <div class="tt-mini-top">
                      <span class="tt-mini-icon">
                        ${ttStatusIcon(statusKey)}
                      </span>
                       
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
    const year = currentSelectedMonth === 11 ?currentSelectedYear + 1 : currentSelectedYear;
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
                    </div>
                    <div class="lecturer-meta">
                        <h4>${lecture.subject}</h4>
                        <div class="subject-badge">Lecture Type : ${lecture.lectureType}</div>
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

    modalBody.innerHTML = `
    <div class="col-lg-12">
    <div class="lecture-card border-0 shadow-none">
        <div class="lecture-body px-4 py-4">

            <!-- Main Lecture Image -->
            <div class="info-row border-0 justify-content-center mb-4">
                <img src="${lecture.programmeImage}" 
                     alt="${lecture.subject}" 
                     class="lecture-main-image"
                     onerror="this.src='./assets/imgs/common/empty-thumbnail.webp'">
            </div>

            <!-- Lecture Details -->
            <div class="info-row">
                <div class="info-label">Faculty:</div>
                <div class="info-value">${lecture.faculty || 'N/A'}</div>
            </div>

            <div class="info-row">
                <div class="info-label">Programme:</div>
                <div class="info-value">${lecture.programme || 'N/A'}</div>
            </div>

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

            <div class="info-row m-0">
                <div class="info-label">Conduct Status:</div>
                <div class="info-value">
                    <span class="status-badge status-${(lecture.status || '').toLowerCase().replace(/\s+/g, '-')}">
                        ${lecture.status || 'N/A'}
                    </span>
                </div>
            </div>

            <!-- Batch Section -->
            <div class="batch-section mt-4">
                <div class="batch-section-header">
                    <div class="batch-title-wrap">
                        <div class="batch-section-title">Assigned Batches</div>
                        <div class="batch-section-subtitle">
                            ${lecture.batchList?.length || 0} batch(es) linked to this lecture
                        </div>
                    </div>
                </div>

                <div class="batch-list">
                    ${lecture.batchList && lecture.batchList.length
            ? lecture.batchList.map(batch => `
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
                                        <div class="dropdown">
                                             ${lecture.hasPermissionEditPermission || lecture.hasPermissionConfirmLecturerPayment || lecture.hasPermissionUploadRecording || lecture.hasPermissionMarkAttendance
                    ? `
           
                            <div class="btn-group">
                            <button type="button" class="custom-btn btn-action px-5 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                Actions
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                ${lecture.status === 'Ongoing' && (lecture.hasPermissionEditPermission || lecture.hasPermissionMarkAttendance) ? `
                                    ${lecture.hasPermissionEditPermission ? `
                                        <li>
                                            <a class="dropdown-item" 
                                                href="edit-lecture?lecture-id=${batch.lecture_id}${isSubjectLectureSchedule ? '&subject-id=' + batch.subject_id : ''}">
                                                <i class="fas fa-edit me-2 text-warning"></i>Edit
                                            </a>
                                        </li>
                                    ` : ''}

                                    ${lecture.hasPermissionMarkAttendance ? `
                                        <li>
                                            <a class="dropdown-item" 
                                                href="lecture-attendance?lecture-id=${batch.lecture_id}${isSubjectLectureSchedule ? '&subject-id=' + batch.subject_id : ''}">
                                                <i class="fas fa-user-check me-2 text-success"></i>Attendance
                                            </a>
                                        </li>
                                    ` : ''}
                                ` : ''}

                              ${lecture.status === 'Completed' ? `

                                ${lecture.hasPermissionEditPermission ? `
                                    <li>
                                        <a class="dropdown-item" 
                                            href="edit-lecture?lecture-id=${batch.lecture_id}${isSubjectLectureSchedule ? '&subject-id=' + batch.subject_id : ''}">
                                            <i class="fas fa-edit me-2 text-warning"></i>Edit
                                        </a>
                                    </li>
                                ` : ''}

                                ${lecture.hasPermissionMarkAttendance ? `
                                    <li>
                                        <a class="dropdown-item" 
                                            href="lecture-attendance?lecture-id=${batch.lecture_id}${isSubjectLectureSchedule ? '&subject-id=' + batch.subject_id : ''}">
                                            <i class="fas fa-user-check me-2 text-success"></i>Attendance
                                        </a>
                                    </li>
                                ` : ''}
                              
                            
                            ` : ''}

                                ${(lecture.status === 'Postponed' || lecture.status === 'Upcoming' || lecture.status === 'Cancelled' || lecture.status === 'Study Leave') ? `
                                    ${lecture.hasPermissionEditPermission ? `
                                    <li>
                                        <a class="dropdown-item" 
                                            href="edit-lecture?lecture-id=${batch.lecture_id}${isSubjectLectureSchedule ? '&subject-id=' + batch.subject_id : ''}">
                                            <i class="fas fa-edit me-2 text-warning"></i>Edit
                                        </a>
                                    </li>
                                     ${lecture.attendanceCount > 0 ? `
                                    <li>
                                                                            <a class="dropdown-item"
                                            href="lecture-attendance?lecture-id=${batch.lecture_id}${isSubjectLectureSchedule ? '&subject-id=' + batch.subject_id : ''}">
                                            <i class="fas fa-user-check me-2 text-warning"></i>Attendance
                                        </a>
                                    </li>
                                    ` : ''}
                                    <li>
                                        <a class="dropdown-item" onclick="removeLectureOpenModal(${batch.lecture_id})"
                                            href="#">
                                            <i class="fas fa-edit me-2 text-warning"></i>Remove
                                        </a>
                                    </li>
                                    ` : ''}

                                ` : ''}
                            </ul>
                        </div>
                          
            ` : ''}
                                        </div>
                                    </div>
                                </div>
                            `).join('')
            : `
                                <div class="batch-empty-state">
                                    No batches assigned for this lecture yet.
                                </div>
                            `
        }
                </div>
            </div>

        </div>
    </div>
</div>
`;

    let footerHTML = "";

    const timeTableId = lecture.batchList?.[0]?.lecture_id;
    const subject = lecture.batchList?.[0]?.subject_id;

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
        footerHTML += `
        <div class="action-buttons px-4 justify-content-end">
            <button class="custom-btn px-5"
                onclick="window.location.href='lecture-recording?lecture-id=${timeTableId}${isSubjectLectureSchedule ? '&subject-id=' + subject : ''}'">
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
        const lecturer = lecture.lecturer || 'Lecture';
        const type = lecture.lectureType || '';
        const start = lecture.startTime || '';
        const end = lecture.endTime || '';
        const thumb = lecture.subjectImage || '';
        const id = lecture.lectureId || '';
        const date = lecture.date || '';
        const status = lecture.status || '';

        const statusClass = (lecture.status || '').toLowerCase();

        const content = `
        <div class="pro-pop">
          <div class="pro-pop-head">
            <div class="pro-pop-icon">
              <img src="${thumb}" onerror="this.src='https://dashboard.enviiz.com/assets/imgs/common/empty-thumbnail.webp'">
            </div>
  
            <div class="pro-pop-titlewrap">
              <div class="pro-pop-title">${name}</div>
               <div class="pro-pop-sub">
                <span class="pro-pop-pill ${statusClass}">${status}</span>
               
              </div>
            </div>
          </div>
  
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
        const time = lecture.startTime + ' - ' + lecture.endTime || '';

        const name = lecture.title;
        const date = lecture.date;
        const type = lecture.lectureType;
        const status = lecture.status;

        const lecturer = lecture.lecturerName;

        const typeClass = type.toLowerCase();
        const statusClass = status.toLowerCase();

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
               
              </div>
            </div>
          </div>

           <div class="pro-pop-row">
            <span class="pro-pop-label">Programme</span>
            <span class="pro-pop-value">${programme}</span>
          </div>

           <div class="pro-pop-row">
            <span class="pro-pop-label">Subject</span>
            <span class="pro-pop-value">${subject}</span>
          </div>
          
       
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

