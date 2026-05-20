var currentSelectedMonth;
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

function generateCalendar(year, month) {
    $("#month").attr("data-i18n", `${monthNames[month].toLowerCase()}`);
    $("#year").text(`${year}`);

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();

    currentSelectedMonth = month;

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
            table += `<div class='lectures-container'>`;
            dayLectures.forEach(lecture => {
                table += `
                <div class='lecture-item ${lecture.status.toLowerCase()}' data-lecture-id='${lecture.lectureId}'>
                    <div class='subject-cover'>
                        <img src='${lecture.subjectImage}' alt='${lecture.subject}'>
                    </div>
                    <div class='lecture-details'>
                        <div class='lecture-subject'>${lecture.subject}</div>
                        <div class='lecture-meta'>
                            <span class='lecturer-name'>${lecture.lectureType}</span>
                            <span class='attendance-status'>${lecture.status}</span>
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

const prevMonthSchedule = () => {
    const year = currentSelectedMonth === 0 ? now.getFullYear() - 1 : now.getFullYear();
    const month = currentSelectedMonth === 0 ? 11 : currentSelectedMonth - 1;
    generateCalendar(year, month);
    initializeLanguage();
};

const nextMonthSchedule = () => {
    const year = currentSelectedMonth === 11 ? now.getFullYear() + 1 : now.getFullYear();
    const month = currentSelectedMonth === 11 ? 0 : currentSelectedMonth + 1;
    generateCalendar(year, month);
    initializeLanguage();
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
                        <img src="${lecture.subjectImage}" 
                             alt="${lecture.subject}"
                             onerror="this.src='https://dashboard.aiccampuslms.com/assets/imgs/common/empty-thumbnail.webp'">
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
                                <li><a class="dropdown-item" href="lecture-payment?lecture-id=${lecture.lectureId}&subject-id=${subject}">Lecturer Payment</a></li>
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
                                <li><a class="dropdown-item" href="lecture-payment?lecture-id=${lecture.lectureId}">Lecturer Payment</a></li>
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

    modalTitle.textContent = `${lecture.subject} - ${lecture.title}`;

    modalBody.innerHTML = `
    <div class="col-lg-12">
        <div class="lecture-card border-0 shadow-none">
            <div class="lecture-body px-4">
                <div class="info-row justify-content-center">
                  
                    <img src="${lecture.subjectImage}" alt="${lecture.subject}" class="w-50 h-auto"
                         onerror="this.src='../assets/imgs/common/empty-thumbnail.webp'">
                  
                    
                </div>

                <div class="info-row">
                    <div class="info-label">Subject:</div>
                    <div class="info-value">${lecture.subject || 'N/A'}</div>
                </div>

                <div class="info-row">
                    <div class="info-label">Faculty:</div>
                    <div class="info-value">${lecture.faculty || 'N/A'}</div>
                </div>

                <div class="info-row">
                    <div class="info-label">Programme:</div>
                    <div class="info-value">${lecture.programme || 'N/A'}</div>
                </div>

                <div class="info-row">
                    <div class="info-label">Intake:</div>
                    <div class="info-value">${lecture.intake || 'N/A'}</div>
                </div>

                <div class="info-row">
                    <div class="info-label">Level:</div>
                    <div class="info-value">${lecture.level || 'N/A'}</div>
                </div>

                <div class="info-row">
                    <div class="info-label">Subject Type:</div>
                    <div class="info-value">
                        <span class="subject-type type-${(lecture.subjectType || 'N/A').toLowerCase()}">
                            ${lecture.subjectType || 'N/A'}
                        </span>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label">Lecturer:</div>
                    <div class="info-value">${lecture.lecturerName || 'N/A'}</div>
                </div>

                <div class="info-row">
                    <div class="info-label">Date & Time:</div>
                    <div class="info-value">
                        ${lecture.date || 'N/A'}, ${lecture.startTime || 'N/A'} - ${lecture.endTime || 'N/A'}
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-label">Conduct Status:</div>
                    <div class="info-value">
                        <span class="status-badge status-${(lecture.status || '').toLowerCase()}">
                            ${lecture.status || 'N/A'}
                        </span>
                    </div>
                </div>
              
            </div>
        </div>
    </div>
`;

    modalFooter.innerHTML = `
                <div class="action-buttons justify-content-end">
                        ${lecture.status === 'Ongoing' && lecture.lectureType === 'Online' ? `
                            <button class="custom-success-btn px-5"
                                    onclick="window.open('${lecture.joinLink}', '_blank')">
                                Join Now
                            </button>
                        ` : ''}

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
                                                href="edit-lecture?lecture-id=${lecture.lectureId}${isSubjectLectureSchedule ? '&subject-id=' + subject : ''}">
                                                <i class="fas fa-edit me-2 text-warning"></i>Edit
                                            </a>
                                        </li>
                                    ` : ''}

                                    ${lecture.hasPermissionMarkAttendance ? `
                                        <li>
                                            <a class="dropdown-item" 
                                                href="lecture-attendance?lecture-id=${lecture.lectureId}${isSubjectLectureSchedule ? '&subject-id=' + subject : ''}">
                                                <i class="fas fa-user-check me-2 text-success"></i>Attendance
                                            </a>
                                        </li>
                                    ` : ''}
                                ` : ''}

                              ${lecture.status === 'Completed' ? `

                                ${lecture.hasPermissionEditPermission ? `
                                    <li>
                                        <a class="dropdown-item" 
                                            href="edit-lecture?lecture-id=${lecture.lectureId}${isSubjectLectureSchedule ? '&subject-id=' + subject : ''}">
                                            <i class="fas fa-edit me-2 text-warning"></i>Edit
                                        </a>
                                    </li>
                                ` : ''}

                                ${lecture.hasPermissionMarkAttendance ? `
                                    <li>
                                        <a class="dropdown-item" 
                                            href="lecture-attendance?lecture-id=${lecture.lectureId}${isSubjectLectureSchedule ? '&subject-id=' + subject : ''}">
                                            <i class="fas fa-user-check me-2 text-success"></i>Attendance
                                        </a>
                                    </li>
                                ` : ''}

                                ${lecture.hasPermissionConfirmLecturerPayment ? `
                                    <li>
                                        <a class="dropdown-item" 
                                            href="lecture-payment?lecture-id=${lecture.lectureId}${isSubjectLectureSchedule ? '&subject-id=' + subject : ''}">
                                            <i class="fas fa-credit-card me-2 text-info"></i>Payment
                                        </a>
                                    </li>
                                ` : ''}

                                ${lecture.hasPermissionUploadRecording ? `
                                    <li>
                                        <a class="dropdown-item" 
                                            href="lecture-recording?lecture-id=${lecture.lectureId}${isSubjectLectureSchedule ? '&subject-id=' + subject : ''}">
                                            <i class="fas fa-video me-2 text-secondary"></i>Recording
                                        </a>
                                    </li>
                                ` : ''}

                            ` : ''}


                                ${(lecture.status === 'Postponed' || lecture.status === 'Upcoming' || lecture.status === 'Cancelled' || lecture.status === 'Study Leave') ? `
                                    ${lecture.hasPermissionEditPermission ? `
                                    <li>
                                        <a class="dropdown-item" 
                                            href="edit-lecture?lecture-id=${lecture.lectureId}${isSubjectLectureSchedule ? '&subject-id=' + subject : ''}">
                                            <i class="fas fa-edit me-2 text-warning"></i>Edit
                                        </a>
                                    </li>
                                    ` : ''}

                                ` : ''}
                            </ul>
                        </div>
                          
            ` : ''}
                    </div>
`;

    modal.show();
}