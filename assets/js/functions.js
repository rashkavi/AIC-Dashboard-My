const applyValidation = (response, validationMap, showMsg = true) => {
    if (showMsg) {
        showToast('error', 'Something went wrong.Check data fields and try again');
    }
    $('.contentBodyArea').animate({ scrollTop: 0 }, 'fast');
    Object.entries(validationMap).forEach(([key, selectors]) => {
        const [input, feedback] = selectors;

        if (response[key]) {
            $(input).addClass("is-invalid");
            $(feedback).text(response[key]);
        } else {
            $(input).removeClass("is-invalid");
            $(feedback).text("");
        }
    });
}

const signIn = (btn) => {
    toggleBtnLoader(btn, true);

    const email = $("#email").val().trim();
    const password = $("#password").val().trim();
    const rememberMe = $("#rememberMe").is(":checked");

    fetch("api/signIn.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            email: email,
            password: password,
            rememberMe: rememberMe
        }),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                const validationMap = {
                    email: ["#email", "#email-feedback"],
                    password: ["#password", "#password-feedback"],
                };

                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            console.log(error);
            showToast("error", "An error occured. Please try again later.");
        });
}
const signOut = () => {
    fetch("api/signOut.php", {
        method: "GET",
    })
        .then((response) => response.text())
        .then((data) => {
            if (data.error) {
                localStorage.removeItem('theme');
                localStorage.removeItem("welcomeAlertShown");
                showToast("error", data.error);
            } else {
                window.location.reload();
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const loadDistrict = () => {
    const id = $("#province").val();
    fetch(`api/loadDistrict.php?id=${id}`, {
        method: "GET",
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.error) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (error) {
                $("#district").html(data);
                $('#district').prop('disabled', false);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const loadCities = () => {
    const id = $("#district").val();
    fetch(`api/loadCities.php?id=${id}`, {
        method: "GET",
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.error) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (error) {
                $("#city").html(data);
                $('#city').prop('disabled', false);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const addUser = (btn) => {

    toggleBtnLoader(btn, true);

    const form = new FormData();
    form.append("fname", $("#first-name").val());
    form.append("lname", $("#last-name").val());
    form.append("email", $("#user-email").val());
    form.append("contact", $("#user-contact-number").val());
    form.append("role", $("#user-role").val());
    form.append("gender", $("#user-gender").val());
    form.append("title", $("#user-title").val());
    form.append("nic", $("#user-national-id").val());
    form.append("dob", $("#user-dob").val());
    form.append("branch", $("#branch-selector").val());
    form.append("address", $("#address-line-1").val());
    form.append("address2", $("#address-line-2").val());
    form.append("city", $("#city").val());
    form.append("pcode", $("#postal-code").val());
    form.append("about", about.getHTMLCode());
    form.append("bio", bio.getHTMLCode());
    form.append("achivements", achivements.getHTMLCode());
    form.append("experience", experience.getHTMLCode());
    form.append("image", $("#file-input").prop("files")[0]);
    form.append("cv", $("#file-selector-input").prop("files")[0]);

    fetch("api/addUser.php",
        {
            method: "POST",
            body: form
        })
        .then(res => res.json())
        .then(response => {

            if (response.done) {
                toggleBtnLoader(btn, false);
                showToast("success", response.done);
                setTimeout(() => window.location.reload(), 3000);
                return;
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    fname: ["#first-name", "#fname-feedback"],
                    lname: ["#last-name", "#lname-feedback"],
                    title: ["#user-title", "#title-feedback"],
                    email: ["#user-email", "#email-feedback"],
                    contact: ["#user-contact-number", "#contact-feedback"],
                    role: ["#user-role", "#role-feedback"],
                    gender: ["#user-gender", "#gender-feedback"],
                    image: ["#user-profile-image-container", "#image-feedback"],
                    nic: ["#user-national-id", "#nic-feedback"],
                    dob: ["#user-dob", "#dob-feedback"],
                    address: ["#address-line-1", "#address-feedback"],
                    address2: ["#address-line-2", "#address2-feedback"],
                    city: ["#city", "#city-feedback"],
                    pcode: ["#postal-code", "#pcode-feedback"],
                    cv: ["#file-info", "#cv-feedback"],
                    branch: ["#branch-selector", "#branch-feedback"]
                };
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch(() => {
            toggleBtnLoader(btn, false);
            showToast("error", "Network error. Please try again.");
        });
};
const editUser = (btn, id) => {
    toggleBtnLoader(btn, true);

    const form = new FormData();
    form.append("id", id);
    form.append("fname", $("#first-name").val());
    form.append("lname", $("#last-name").val());
    form.append("email", $("#user-email").val());
    form.append("contact", $("#user-contact-number").val());
    form.append("role", $("#user-role").val());
    form.append("gender", $("#user-gender").val());
    form.append("title", $("#user-title").val());
    form.append("nic", $("#user-national-id").val());
    form.append("dob", $("#user-dob").val());
    form.append("branch", $("#branch-selector").val());
    form.append("address", $("#address-line-1").val());
    form.append("address2", $("#address-line-2").val());
    form.append("city", $("#city").val());
    form.append("pcode", $("#postal-code").val());
    form.append("about", about.getHTMLCode());
    form.append("bio", bio.getHTMLCode());
    form.append("achivements", achivements.getHTMLCode());
    form.append("experience", experience.getHTMLCode());
    form.append("image", $("#file-input").prop("files")[0]);
    form.append("cv", $("#file-selector-input").prop("files")[0]);

    fetch("api/editUser.php",
        {
            method: "POST",
            body: form
        })
        .then(res => res.json())
        .then(response => {
            if (response.done) {
                toggleBtnLoader(btn, false);
                showToast("success", response.done);
                setTimeout(() => window.location.reload(), 3000);
                return;
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    fname: ["#first-name", "#fname-feedback"],
                    lname: ["#last-name", "#lname-feedback"],
                    title: ["#user-title", "#title-feedback"],
                    email: ["#user-email", "#email-feedback"],
                    contact: ["#user-contact-number", "#contact-feedback"],
                    role: ["#user-role", "#role-feedback"],
                    gender: ["#user-gender", "#gender-feedback"],
                    image: ["#user-profile-image-container", "#image-feedback"],
                    nic: ["#user-national-id", "#nic-feedback"],
                    dob: ["#user-dob", "#dob-feedback"],
                    address: ["#address-line-1", "#address-feedback"],
                    address2: ["#address-line-2", "#address2-feedback"],
                    city: ["#city", "#city-feedback"],
                    pcode: ["#postal-code", "#pcode-feedback"],
                    cv: ["#file-info", "#cv-feedback"],
                    branch: ["#branch-selector", "#branch-feedback"]

                };
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch(() => {
            toggleBtnLoader(btn, false);
            showToast("error", "Network error. Please try again.");
        });
}
const filterUsers = () => {
    const data = {
        text: $("#user-search-text").val() || "",
        role: $("#role-selector").val() || "",
        sort: $("#sort-selector").val() || "",
        sortName: sortCondition1,
        sortDate: sortCondition2,
        resultPerPage: $("#show-result-selector").val() || 10,
        update: $("#update-selector").val() || "",
        page: selectedPage || 1,
        selectUsers: selectedUsers || [],
        unSelectUsers: unSelectedUsers || [],
    };

    fetch('api/filterUsers.php', {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
    })
        .then(res => res.text())
        .then(text => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#users-table-container").html(result);
            }
        })
        .catch(error => {
            console.error("Filter users failed:", error);
            showToast("error", "An error occurred. Please try again later.");
        });
};

const applyUsersUpdate = (btn) => {
    toggleBtnLoader(btn, true);
    const update = $("#update-selector").val();
    if (update != 0) {
        const data = {
            update: update,
            checkedUsers: selectedUsers,
            uncheckedUsers: unSelectedUsers,
            currentPage: selectedPage,
            text: $("#user-search-text").val(),
            role: $("#role-selector").val(),
            sortSelector: $("#sort-selector").val(),
            resultPerPage: $("#show-result-selector").val(),
        }
        fetch('api/updateUsers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data),
        })
            .then(res => res.json())
            .then(response => {
                if (response.error) {
                    showToast("error", response.error);
                }
                if (response.warning) {
                    showToast("warning", response.warning);
                }
                if (response.done) {
                    toggleBtnLoader(btn, "done");
                    showToast("success", response.done);
                    filterUsers();
                    setDefault();
                    toggleBtnLoader(btn, false);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    } else {
        toggleBtnLoader(btn, false);
        showToast("warning", "Please select the desired user status change option.");
    }
}
const applySelectedUserUpdate = (update) => {
    if (update != 0) {
        toggleLoader(true);
        fetch('api/updateUsers.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ checkedUsers: selectedUsers, uncheckedUsers: unSelectedUsers, update: update }),
        })
            .then(res => res.json())
            .then(response => {
                toggleLoader(false);
                if (response.error) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
                if (response.warning) {
                    showToast("warning", response.warning);
                }
                if (response.done) {
                    setTimeout(() => { showToast("success", response.done); }, 1000);
                    filterUsers();
                    setDefault();
                }
            })
            .catch((error) => {
                toggleLoader(false);
                setTimeout(() => { showToast("error", "Something went wrong"); }, 1000);
            });
    } else {
        showToast("warning", "Please select the desired user status change option.");
    }
}

const addStudent = (btn) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("id", $("#student-intake-id").val());
    form.append("fname", $("#first-name").val());
    form.append("lname", $("#last-name").val());
    form.append("fullname", $("#full-name").val());
    form.append("nwi", $("#name-with-initials").val());
    form.append("title", $("#title-selector").val());
    form.append("gender", $("#gender-selector").val());
    form.append("marital_status", $("#marital-status-selector").val());
    form.append("nationality", $("#nationality").val());
    form.append("nic", $("#nic").val());
    form.append("passport", $("#passport").val());
    form.append("cob", $("#country-of-birth").val());
    form.append("cor", $("#country-of-residence").val());
    form.append("dob", $("#dob").val());
    form.append("email", $("#email").val());
    form.append("mobile", $("#mobile").val());
    form.append("whatsapp", $("#whatsapp").val());
    form.append("address", $("#address").val());
    form.append("address2", $("#address2").val());
    form.append("city", $("#city").val());
    form.append("pcode", $("#postal-code").val());
    form.append("image", $("#file-input").prop("files")[0]);
    form.append("branch", $("#branch-selector").val());
    form.append("faculty", $("#faculty-selector").val());
    form.append("programme", $("#programme-selector").val());
    form.append("intake", $("#intake-selector").val());
    form.append("grelationship", $("#grelationship-selector").val());
    form.append("gtitle", $("#gtitle-selector").val());
    form.append("gfname", $("#guardian-fname").val());
    form.append("glname", $("#guardian-lname").val());
    form.append("gemail", $("#guardian-email").val());
    form.append("gmobile", $("#guardian-mobile").val());
    form.append("gwhatsapp", $("#guardian-whatsapp").val());
    form.append("certificate_name", $("#certificate-name").val());
    form.append("school", $("#school").val());
    form.append("ol_results", $("#ol-results").val());
    form.append("al_results", $("#al-results").val());
    form.append("work_experience", $("#work-experience").val());
    form.append("team_username", $("#team-username").val());

    appendStudentDocumentsToFormData(form);

    const validationMap = {
        id: ["#student-intake-id", "#student-intake-id-feedback"],
        fname: ["#first-name", "#fname-feedback"],
        lname: ["#last-name", "#lname-feedback"],
        fullname: ["#full-name", "#fullname-feedback"],
        nwi: ["#name-with-initials", "#nwi-feedback"],
        title: ["#title-selector", "#title-feedback"],
        gender: ["#gender-selector", "#gender-feedback"],
        marital_status: ["#marital-status-selector", "#marital-feedback"],
        nationality: ["#nationality", "#nationality-feedback"],
        cob: ["#country-of-birth", "#cob-feedback"],
        cor: ["#country-of-residence", "#cor-feedback"],
        nic: ["#nic", "#nic-feedback"],
        passport: ["#passport", "#passport-feedback"],
        dob: ["#dob", "#dob-feedback"],
        email: ["#email", "#email-feedback"],
        mobile: ["#mobile", "#mobile-feedback"],
        whatsapp: ["#whatsapp", "#whatsapp-feedback"],
        address: ["#address", "#address-feedback"],
        address2: ["#address2", "#address2-feedback"],
        city: ["#city", "#city-feedback"],
        pcode: ["#postal-code", "#postal-code-feedback"],
        faculty: ["#faculty-selector", "#faculty-feedback"],
        programme: ["#programme-selector", "#programme-feedback"],
        intake: ["#intake-selector", "#intake-feedback"],
        grelationship: ["#grelationship-selector", "#grelationship-feedback"],
        gtitle: ["#gtitle-selector", "#gtitle-feedback"],
        gfname: ["#guardian-fname", "#gfname-feedback"],
        glname: ["#guardian-lname", "#glname-feedback"],
        gemail: ["#guardian-email", "#gemail-feedback"],
        gmobile: ["#guardian-mobile", "#gmobile-feedback"],
        gwhatsapp: ["#guardian-whatsapp", "#gwhatsapp-feedback"],
        aic_application: ["#doc-aic-application", "#doc-aic-application-feedback"],
        university_application: ["#doc-university-application", "#doc-university-application-feedback"],
        signed_agreement: ["#doc-signed-agreement", "#doc-signed-agreement-feedback"],
        signed_program_brief: ["#doc-signed-program-brief", "#doc-signed-program-brief-feedback"],
        passport_nic: ["#doc-passport-nic", "#doc-passport-nic-feedback"],
        academic_documents: ["#doc-academic-documents", "#doc-academic-documents-feedback"],
        other_entry_documents: ["#doc-other-entry-documents", "#doc-other-entry-documents-feedback"],
        payment_slip: ["#doc-payment-slip", "#doc-payment-slip-feedback"],
        manager_checklist: ["#doc-manager-checklist", "#doc-manager-checklist-feedback"],
        discount_approved_document: ["#doc-discount-approved-document", "#doc-discount-approved-document-feedback"],
        certificate_name: ["#certificate-name", "#certificate-name-feedback"],
        school: ["#school", "#school-feedback"],
        ol_results: ["#ol-results", "#ol-results-feedback"],
        al_results: ["#al-results", "#al-results-feedback"],
        work_experience: ["#work-experience", "#work-experience-feedback"],
        team_username: ["#team-username", "#team-username-feedback"],
        team_password: ["#team-password", "#team-password-feedback"]
    };

    fetch("api/addStudent.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            console.log(response);
            applyValidation(response, validationMap, false);
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => window.location.reload(), 3000);
                return;
            } else if (response.error) {

                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const editStudent = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("id", id);
    form.append("fname", $("#first-name").val());
    form.append("lname", $("#last-name").val());
    form.append("fullname", $("#full-name").val());
    form.append("nwi", $("#name-with-initials").val());
    form.append("title", $("#title-selector").val());
    form.append("gender", $("#gender-selector").val());
    form.append("marital_status", $("#marital-status-selector").val());
    form.append("nationality", $("#nationality").val());
    form.append("nic", $("#nic").val());
    form.append("passport", $("#passport").val());
    form.append("cob", $("#country-of-birth").val());
    form.append("cor", $("#country-of-residence").val());
    form.append("dob", $("#dob").val());
    form.append("email", $("#email").val());
    form.append("mobile", $("#mobile").val());
    form.append("whatsapp", $("#whatsapp").val());
    form.append("address", $("#address").val());
    form.append("address2", $("#address2").val());
    form.append("city", $("#city").val());
    form.append("pcode", $("#postal-code").val());
    form.append("image", $("#file-input").prop("files")[0]);
    form.append("grelationship", $("#grelationship-selector").val());
    form.append("gtitle", $("#gtitle-selector").val());
    form.append("gfname", $("#guardian-fname").val());
    form.append("glname", $("#guardian-lname").val());
    form.append("gemail", $("#guardian-email").val());
    form.append("gmobile", $("#guardian-mobile").val());
    form.append("gwhatsapp", $("#guardian-whatsapp").val());
    form.append("certificate_name", $("#certificate-name").val());
    form.append("school", $("#school").val());
    form.append("ol_results", $("#ol-results").val());
    form.append("al_results", $("#al-results").val());
    form.append("work_experience", $("#work-experience").val());
    form.append("team_username", $("#team-username").val());
    form.append("team_password", $("#team-password").val());
    form.append("removed_documents", removedExistingDocuments);
    appendStudentDocumentsToFormData(form);

    const validationMap = {
        id: ["#student-intake-id", "#student-intake-id-feedback"],
        fname: ["#first-name", "#fname-feedback"],
        lname: ["#last-name", "#lname-feedback"],
        fullname: ["#full-name", "#fullname-feedback"],
        nwi: ["#name-with-initials", "#nwi-feedback"],
        title: ["#title-selector", "#title-feedback"],
        gender: ["#gender-selector", "#gender-feedback"],
        marital_status: ["#marital-status-selector", "#marital-feedback"],
        nationality: ["#nationality", "#nationality-feedback"],
        cob: ["#country-of-birth", "#cob-feedback"],
        cor: ["#country-of-residence", "#cor-feedback"],
        nic: ["#nic", "#nic-feedback"],
        passport: ["#passport", "#passport-feedback"],
        dob: ["#dob", "#dob-feedback"],
        email: ["#email", "#email-feedback"],
        mobile: ["#mobile", "#mobile-feedback"],
        whatsapp: ["#whatsapp", "#whatsapp-feedback"],
        address: ["#address", "#address-feedback"],
        address2: ["#address2", "#address2-feedback"],
        city: ["#city", "#city-feedback"],
        pcode: ["#postal-code", "#postal-code-feedback"],
        faculty: ["#faculty-selector", "#faculty-feedback"],
        programme: ["#programme-selector", "#programme-feedback"],
        intake: ["#intake-selector", "#intake-feedback"],
        grelationship: ["#grelationship-selector", "#grelationship-feedback"],
        gtitle: ["#gtitle-selector", "#gtitle-feedback"],
        gfname: ["#guardian-fname", "#gfname-feedback"],
        glname: ["#guardian-lname", "#glname-feedback"],
        gemail: ["#guardian-email", "#gemail-feedback"],
        gmobile: ["#guardian-mobile", "#gmobile-feedback"],
        gwhatsapp: ["#guardian-whatsapp", "#gwhatsapp-feedback"],
        aic_application: ["#doc-aic-application", "#doc-aic-application-feedback"],
        university_application: ["#doc-university-application", "#doc-university-application-feedback"],
        signed_agreement: ["#doc-signed-agreement", "#doc-signed-agreement-feedback"],
        signed_program_brief: ["#doc-signed-program-brief", "#doc-signed-program-brief-feedback"],
        passport_nic: ["#doc-passport-nic", "#doc-passport-nic-feedback"],
        academic_documents: ["#doc-academic-documents", "#doc-academic-documents-feedback"],
        other_entry_documents: ["#doc-other-entry-documents", "#doc-other-entry-documents-feedback"],
        payment_slip: ["#doc-payment-slip", "#doc-payment-slip-feedback"],
        manager_checklist: ["#doc-manager-checklist", "#doc-manager-checklist-feedback"],
        discount_approved_document: ["#doc-discount-approved-document", "#doc-discount-approved-document-feedback"],
        payment_option: ["#payment-option", "#payment-option-feedback"],
        certificate_name: ["#certificate-name", "#certificate-name-feedback"],
        school: ["#school", "#school-feedback"],
        ol_results: ["#ol-results", "#ol-results-feedback"],
        al_results: ["#al-results", "#al-results-feedback"],
        work_experience: ["#work-experience", "#work-experience-feedback"],
        team_username: ["#team-username", "#team-username-feedback"],
        team_password: ["#team-password", "#team-password-feedback"]
    };

    fetch("api/editStudent.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => window.location.reload(), 3000);
                return;
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
const uploadStudentBulk = async (btn) => {
    toggleBtnLoader(btn, true);
    const formData = new FormData();
    formData.append("file", $("#file-selector-input")[0].files[0]);
    fetch("api/uploadStudentBulk.php", {
        method: "POST",
        body: formData,
    })
        .then((res) => res.json())
        .then(async (response) => {
            $("#csv-file-feedback").text('');
            if (response.done) {
                toggleBtnLoader(btn, false);
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
                $("#csv-file-feedback").text('');
            } else if (response.file) {
                toggleBtnLoader(btn, false);
                showToast("error", response.file);
                $("#csv-file-feedback").text(response.file);
            } else {
                $("#csv-file-feedback").text('');
                showToast("error", "An error occured.Please check error log and try again.");
                setTimeout(() => toggleBtnLoader(btn, false), 4000);
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF();

                let y = 10;

                pdf.setFontSize(10);

                for (let i = 0; i < response.dataErrors.length; i++) {
                    for (let j = 0; j < response.dataErrors[i].length; j++) {

                        pdf.text(response.dataErrors[i][j], 8, y);

                        y += 10;
                    }
                }

                const pdfOutput = pdf.output('blob');
                const pdfUrl = URL.createObjectURL(pdfOutput);

                setTimeout(() => window.open(pdfUrl, '_blank'), 3000);
                setTimeout(() => URL.revokeObjectURL(pdfUrl), 4000);

            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
const filterStudents = () => {
    const data = {
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        branch: $("#branch-selector").val(),
        faculty: $("#faculty-selector").val(),
        programme: $("#programme-selector").val(),
        intake: $("#intake-selector").val(),
        sortByName: sortCondition1,
        sortByDate: sortCondition2,
        update: $("#update-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
        selectStudents: selectedUsers,
        unSelectStudents: unSelectedUsers,
    }

    fetch('api/filterStudents.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.text())
        .then((text) => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#students-table-container").html(result);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.")
        });
}

const applyStudentsUpdate = (btn) => {
    toggleBtnLoader(btn, true);
    const update = $("#update-selector").val();
    if (update != 0) {
        const data = {
            update: update,
            checkedStudents: selectedUsers,
            uncheckedStudents: unSelectedUsers,
            currentPage: selectedPage,
            searchText: $("#search-text").val(),
            sortSelector: $("#sort-selector").val(),
            resultPerPage: $("#show-result-selector").val(),
        }
        fetch('api/updateStudentsStatus.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data),
        })
            .then(res => res.json())
            .then(response => {
                if (response.done) {
                    toggleBtnLoader(btn, false);
                    showToast("success", response.done);
                    filterStudents();
                    setDefault();
                    toggleBtnLoader(btn, false);
                } else if (response.error) {
                    showToast("error", response.error);
                } else if (response.warning) {
                    showToast("warning", response.warning);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "Something went wrong.Please try again later");
            });
    } else {
        toggleBtnLoader(btn, false);
        showToast("warning", "Please select the desired student status change option.");
    }
}

const applySelectedStudentUpdate = (update) => {
    if (update != 0) {
        toggleLoader(true);
        fetch('api/updateStudentsStatus.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ checkedStudents: selectedUsers, uncheckedStudents: unSelectedUsers, update: update }),
        })
            .then(res => res.json())
            .then(response => {
                toggleLoader(false);
                if (response.error) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else if (response.warning) {
                    showToast("warning", response.warning);
                } else {
                    setTimeout(() => { showToast("success", response.done); }, 1000);
                    filterStudents();
                    setDefault();
                }
            })
            .catch((error) => {
                toggleLoader(false);
                showToast("error", "Something went wrong.Please try again later");
            });
    } else {
        showToast("warning", "Please select the desired student status change option.");
    }
}

const addFaculty = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("branch_id", id);
    form.append("faculty_name", $("#faculty-name").val());
    form.append("faculty_cover", $("#cover-input").prop("files")[0]);

    fetch("api/addFaculty.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, false);
                $("#addfaculty").modal("hide");
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    name: ["#faculty-name", "#af-name-feedback"],
                    cover: ["#faculty-cover", "#af-cover-feedback"],
                };
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
const editFaculty = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("faculty_id", id);
    form.append("faculty_name", $("#edit-faculty-name").val());
    form.append("faculty_cover", $("#edit-cover-input").prop("files")[0]);

    fetch("api/editFaculty.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                $("#editFaculty").modal("hide");
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else {
                const validationMap = {
                    name: ["#edit-faculty-name", "#ef-name-feedback"],
                    cover: ["#edit-faculty-cover", "#ef-cover-feedback"],
                };
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
const addProgramme = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("programme_name", $("#programme-name").val());
    form.append("programme_duration", $("#programme-duration").val());
    form.append("programme_cover", $("#cover-input").prop("files")[0]);
    form.append("new_programme_duration", $("#new-programme-duration").val());
    form.append("faculty_id", id);

    fetch("api/addPrograme.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, false);
                $("#addProgramme").modal("hide");
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    name: ["#programm-name", "#ap-name-feedback"],
                    cover: ["#programm-cover", "#ap-cover-feedback"],
                    duration: ["#programm-duration", "#ap-duration-feedback"],
                    new_duration: ["#new-programme-duration", "#ap-new-duration-feedback"]
                };
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
const editProgramme = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("programme_name", $("#edit-programme-name").val());
    form.append("programme_cover", $("#edit-cover-input").prop("files")[0]);
    form.append("programme_duration", $("#edit-programme-duration").val());
    form.append("new_programme_duration", $("#new-edit-programme-duration").val());
    form.append("programme_id", id);

    fetch("api/editPrograme.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                $("#editProgramme").modal('hide');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    name: ["#edit-programme-name", "#ep-name-feedback"],
                    cover: ["#edit-programm-cover", "#ep-cover-feedback"],
                    duration: ["#edit-programm-duration", "#ep-duration-feedback"],
                    new_duration: ["#new-edit-programme-duration", "#ep-new-duration-feedback"]
                };
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addIntake = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("intake_name", $("#intake-name").val());
    form.append("intake_cover", $("#cover-input").prop("files")[0]);
    form.append("programme_id", id);

    fetch("api/addIntake.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, false);
                $("#addIntake").modal("hide");
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    name: ["#intake-name", "#ai-name-feedback"],
                    cover: ["#intake-cover", "#ai-cover-feedback"],
                };
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
const editIntake = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("intake_name", $("#edit-intake-name").val());
    form.append("intake_cover", $("#edit-cover-input").prop("files")[0]);
    form.append("intake_id", id);

    fetch("api/editIntake.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                $("#editIntake").modal("hide");
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    name: ["#edit-intake-name", "#ei-name-feedback"],
                    cover: ["#edit-intake-cover", "#ei-cover-feedback"],
                };
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
const addLevel = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("level_name", $("#level-name").val());
    form.append("level_cover", $("#cover-input").prop("files")[0]);
    form.append("intake_id", id);

    fetch("api/addLevel.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                $("#addLevel").modal("hide");
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    name: ["#level-name", "#al-name-feedback"],
                    cover: ["#level-cover", "#al-cover-feedback"],
                };
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
const editLevel = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("level_name", $("#edit-level-name").val());
    form.append("level_cover", $("#edit-cover-input").prop("files")[0]);
    form.append("level_id", id);

    fetch("api/editLevel.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                $("#editLevel").modal("hide");
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    name: ["#edit-level-name", "#el-name-feedback"],
                    cover: ["#edit-level-cover", "#el-cover-feedback"],
                };
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
const addSubject = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("subject_name", $("#subject-name").val());
    form.append("subject_hours", $("#subject-hours").val());
    form.append("subject_type", $("#subject-type").val());
    form.append("subject_group", $("#subject-group").val());
    form.append("new_subject_group", $("#new-subject-group").val());
    form.append("subject_cover", $("#cover-input").prop("files")[0]);
    form.append("level_id", id);

    fetch("api/addSubject.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, false);
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                $("#addSubject").modal("hide");
                showToast("error", response.error);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else {
                const validationMap = {
                    name: ["#subject-name", "#as-name-feedback"],
                    hours: ["#subject-hours", "#as-hours-feedback"],
                    type: ["#subject-type", "#as-type-feedback"],
                    group: ["#subject-group", "#as-group-feedback"],
                    cover: ["#subject-cover", "#as-cover-feedback"]
                };

                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
const editSubject = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("subject_name", $("#edit-subject-name").val());
    form.append("subject_hours", $("#edit-subject-hours").val());
    form.append("subject_type", $("#edit-subject-type").val());
    form.append("subject_group", $("#edit-subject-group").val());
    form.append("subject_cover", $("#edit-cover-input").prop("files")[0]);
    form.append("subject_id", id);

    fetch("api/editSubject.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, false);
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else {

                const validationMap = {
                    name: ["#edit-subject-name", "#es-name-feedback"],
                    hours: ["#edit-subject-hours", "#es-hours-feedback"],
                    type: ["#edit-subject-type", "#es-type-feedback"],
                    group: ["#edit-subject-group", "#es-group-feedback"],
                    cover: ["#edit-subject-cover", "#es-cover-feedback"]
                };

                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}
const loadFaculties = () => {
    const branch = $("#branch-selector").val();
    fetch(`api/loadFaculties.php?id=${branch}`)
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                    $("#faculty-selector").attr("disabled", true);
                } else if (response.hasOwnProperty('branch')) {
                    $("#faculty-selector").attr("disabled", true);
                    $("#faculty-selector").html("<option value='0'>- Select -</option>");
                    $("#branch-selector").addClass("is-invalid");
                    $("#branch-feedback").text(response.branch);
                }
            } catch (error) {
                $("#branch-selector").removeClass("is-invalid");
                $("#branch-feedback").text("");
                $("#faculty-selector").removeAttr("disabled");
                $("#faculty-selector").html(data);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const loadProgrammes = () => {
    const faculty = $("#faculty-selector").val();
    fetch(`api/loadProgrammes.php?id=${faculty}`)
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                    $("#programme-selector").attr("disabled", true);
                } else if (response.hasOwnProperty('faculty')) {
                    $("#programme-selector").attr("disabled", true);
                    $("#programme-selector").html("<option value='0'>- Select -</option>");
                    $("#faculty-selector").addClass("is-invalid");
                    $("#faculty-feedback").text(response.faculty);
                }
            } catch (error) {
                $("#faculty-selector").removeClass("is-invalid");
                $("#faculty-feedback").text("");
                $("#programme-selector").removeAttr("disabled");
                $("#programme-selector").html(data);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const loadIntakes = () => {
    const programme = $("#programme-selector").val();
    fetch(`api/loadIntakes.php?id=${programme}`)
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                    $("#intake-selector").attr("disabled", true);
                } else if (response.hasOwnProperty('programme')) {
                    $("#intake-selector").attr("disabled", true);
                    $("#intake-selector").html("<option value='0'>- Select -</option>");
                    $("#programme-selector").addClass("is-invalid");
                    $("#programme-feedback").text(response.programme);
                }
            } catch (error) {
                $("#programme-selector").removeClass("is-invalid");
                $("#programme-feedback").text("");
                $("#intake-selector").removeAttr("disabled");
                $("#intake-selector").html(data);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const removeDeletedFacultyProgrammes = (id) => {
    fetch(`api/removeDeletedFacultyProgrammes.php?id=${id}`)
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else {
                const multipleSelect = $("#programme-select2").get(0);
                for (let i = 0; i < multipleSelect.options.length; i++) {
                    ////console.log(multipleSelect.options[i].value);
                    response.programmes.forEach((value) => {
                        if (multipleSelect.options[i].value === value) {
                            removeItem(value, 'programme');
                        }
                    });
                }
                $("#programme-select1").attr("disabled", true);
                $("#programme-select1").html("<option value='0'>- Select -</option>");
                $("#intake-select1").attr("disabled", true);
                $("#intake-select1").html("<option value='0'>- Select -</option>");
            }
        })
        .catch((error) => {
            ////console.log(error);
            showToast("error", "An error occured. Please try again later.");
        });
}
const removeDeletedProgrammeIntakes = (id) => {
    fetch(`api/removeDeletedProgrammeIntakes.php?id=${id}`)
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else {
                const multipleSelect = $("#intake-select2").get(0);
                for (let i = 0; i < multipleSelect.options.length; i++) {
                    ////console.log(multipleSelect.options[i].value);
                    response.intakes.forEach((value) => {
                        if (multipleSelect.options[i].value === value) {
                            removeItem(value, 'intake');
                        }
                    });
                }
                $("#intake-select1").attr("disabled", true);
                $("#intake-select1").html("<option value='0'>- Select Intake -</option>");
            }
        })
        .catch((error) => {
            ////console.log(error);
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterIntakeHasStudents = (id) => {
    const data = {
        id: id,
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        sortByName: sortCondition1,
        sortByDate: sortCondition2,
        sortById: sortCondition3,
        update: $("#update-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
        selectStudents: selectedUsers,
        unSelectStudents: unSelectedUsers,
    }

    fetch('api/filterIntakeHasStudents.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-table-container").html(data);

            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const applyIntakeHasStudentsUpdate = (id) => {
    const update = $("#update-selector").val();
    if (update != 0) {
        const data = {
            intakeId: id,
            update: update,
            checkedStudents: selectedUsers,
            uncheckedStudents: unSelectedUsers,
            currentPage: selectedPage,
            searchText: $("#search-text").val(),
            sortSelector: $("#sort-selector").val(),
            resultPerPage: $("#show-result-selector").val(),
        }
        fetch('api/updateIntakeStudentsStatus.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data),
        })
            .then(response => response.text())
            .then(data => {
                console.log(data);
                const response = JSON.parse(data);
                if (response.error) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else if (response.warning) {
                    showToast("warning", response.warning);
                } else {
                    setTimeout(() => { showToast("success", response.done); }, 1000);
                    filterIntakeHasStudents(id);
                    setDefault();
                }
            })
            .catch((error) => {
                console.error('Error:', error);
                showToast("error", "Something went wrong");
            });
    } else {
        showToast("warning", "Please select the desired student status change option.");
    }
}

const applySelectedIntakeStudentUpdate = (id, update) => {
    if (update != 0) {
        const data = {
            intakeId: id,
            update: update,
            checkedStudents: selectedUsers,
            uncheckedStudents: unSelectedUsers,
            currentPage: selectedPage,
            searchText: $("#search-text").val(),
            sortSelector: $("#sort-selector").val(),
            resultPerPage: $("#show-result-selector").val(),
        }
        fetch('api/updateIntakeStudentsStatus.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data),
        })
            .then(response => response.text())
            .then(data => {

                const response = JSON.parse(data);
                if (response.error) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else if (response.warning) {
                    showToast("warning", response.warning);
                } else {
                    setTimeout(() => { showToast("success", response.done); }, 1000);
                    filterIntakeHasStudents(id);
                    setDefault();
                }
            })
            .catch((error) => {

                showToast("error", "Something went wrong");
            });
    } else {
        showToast("warning", "Please select the desired student status change option.");
    }
}

const addAssignment = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("subject_id", id);
    form.append("code", $("#code").val());
    form.append("topic", $("#topic").val())
    form.append("start_date", $("#start-date").val());
    form.append("start_time", $("#start-time").val());
    form.append("end_date", $("#end-date").val());
    form.append("end_time", $("#end-time").val());
    form.append("file", $("#file-selector-input").prop("files")[0]);
    form.append("plagiarism_report", $("#plagiarism-report").val());

    fetch('api/addAssignment.php', {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, false);
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.hasOwnProperty('error')) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    code: ["#code", "#code-feedback"],
                    topic: ["#topic", "#topic-feedback"],
                    start_date: ["#start-date", "#start-date-feedback"],
                    start_time: ["#start-time", "#start-time-feedback"],
                    end_date: ["#end-date", "#end-date-feedback"],
                    end_time: ["#end-time", "#end-time-feedback"],
                    file: ["#file-info", "#file-feedback"]
                };

                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        })
}

const editAssignment = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("assignment_id", id);
    form.append("code", $("#code").val());
    form.append("topic", $("#topic").val())
    form.append("start_date", $("#start-date").val());
    form.append("start_time", $("#start-time").val());
    form.append("end_date", $("#end-date").val());
    form.append("end_time", $("#end-time").val());
    form.append("file", $("#file-selector-input").prop("files")[0]);
    form.append("plagiarism_report", $("#plagiarism-report").val());
    form.append("assignment_status", $("#assignment-status").val());

    if ($("#assignment-status").val() == 4 || $("#assignment-status").val() == 5) {
        form.append("late_submi_end_date", $("#late-submision-date").val());
        form.append("late_submi_end_time", $("#late-submision-time").val());
        form.append("recorection_fee", $("#recorrection-fee").val());
        form.append("recorrection_end_date", $("#recorrection-end-date").val());
        form.append("recorrection_end_time", $("#recorrection-end-time").val());
    }

    fetch('api/editAssignment.php', {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, false);
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    code: ["#code", "#code-feedback"],
                    topic: ["#topic", "#topic-feedback"],
                    start_date: ["#start-date", "#start-date-feedback"],
                    start_time: ["#start-time", "#start-time-feedback"],
                    end_date: ["#end-date", "#end-date-feedback"],
                    end_time: ["#end-time", "#end-time-feedback"],
                    file: ["#file-info", "#file-feedback"],
                    plagiarism_report: ["#plagiarism-report", "#plagiarism-report-feedback"],
                    assi_status: ["#assignment-status", "#assignment-status-feedback"],
                    late_submi_end_date: ["#late-submision-date", "#late-submision-date-feedback"],
                    recorection_fee: ["#recorrection-fee-date", "#recorrection-fee-date-feedback"],
                    recorrection_end_date: ["#recorrection-end-date", "#recorrection-end-date-feedback"],
                    recorrection_end_time: ["#recorrection-end-time", "#recorrection-end-time-feedback"]
                };

                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);

            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        })
}

const loadLevels = () => {
    const intake = $("#intake-selector").val();
    fetch(`api/loadLevels.php?id=${intake}`)
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                    $("#level-selector").attr("disabled", true);
                } else if (response.hasOwnProperty('intake')) {
                    $("#level-selector").attr("disabled", true);
                    $("#level-selector").html("<option value='0'>- Select -</option>");
                    $("#intake-selector").addClass("is-invalid");
                    $("#intake-feedback").text(response.intake);
                }
            } catch (error) {
                $("#intake-selector").removeClass("is-invalid");
                $("#intake-feedback").text("");
                $("#level-selector").removeAttr("disabled");
                $("#level-selector").html(data);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const loadSubjects = () => {
    const level = $("#level-selector").val();
    fetch(`api/loadSubjects.php?id=${level}`)
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                    $("#subject-selector").attr("disabled", true);
                } else if (response.hasOwnProperty('level')) {
                    $("#subject-selector").attr("disabled", true);
                    $("#subject-selector").html("<option value='0'>- Select -</option>");
                    $("#level-selector").addClass("is-invalid");
                    $("#level-feedback").text(response.level);
                }
            } catch (error) {
                $("#level-selector").removeClass("is-invalid");
                $("#level-feedback").text("");
                $("#subject-selector").removeAttr("disabled");
                $("#subject-selector").html(data);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const loadLecturers = () => {
    const subject = $("#subject-selector").val();
    fetch(`api/loadLecturers.php?id=${subject}`)
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                    $("#lecturer-selector").attr("disabled", true);
                } else if (response.hasOwnProperty('subject')) {
                    $("#lecturer-selector").attr("disabled", true);
                    $("#lecturer-selector").html("<option value='0'>- Select -</option>");
                    $("#subject-selector").addClass("is-invalid");
                    $("#subject-feedback").text(response.subject);
                }
            } catch (error) {
                $("#subject-selector").removeClass("is-invalid");
                $("#subject-feedback").text("");
                $("#lecturer-selector").removeAttr("disabled");
                $("#lecturer-selector").html(data);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const scheduleLecture = (btn) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("branch", $("#branch-selector").val());
    form.append("faculty", $("#faculty-selector").val());
    form.append("programme_id", $("#programme-selector").val());
    form.append("intake_id", $("#intake-selector").val());
    form.append("level_id", $("#level-selector").val());
    form.append("subject_id", $("#subject-selector").val());
    form.append("lecturer_id", $("#lecturer-selector").val());
    form.append("lecture-date", $("#lecture-date").val());

    form.append("title", $("#title").val());
    form.append("physical", $("#physical-check-box").is(":checked") ? true : false);
    form.append("online", $("#online-check-box").is(":checked") ? true : false);
    form.append("link", $("#link-input").val());
    form.append("one_time", $("#oneTimeLecture").is(":checked") ? true : false);
    form.append("recurring", $("#recurringLecture").is(":checked") ? true : false);
    form.append("start_date", $("#start-date").val());
    form.append("end_date", $("#end-date").val());
    if ($("#recurringLecture").is(":checked")) {
        form.append("start_time", $("#r-start-time").val());
        form.append("end_time", $("#r-end-time").val());
    } else {
        form.append("start_time", $("#start-time").val());
        form.append("end_time", $("#end-time").val());
    }

    form.append("days", selectedDays);
    form.append("skip_poya_days", $("#skip-poya").is(":checked") ? true : false);
    form.append("skip_holidays", $("#skip-holidays").is(":checked") ? true : false);
    form.append("tutes", $("#file-selector-input")[0].files[0]);
    var files = $("#file-selector-input")[0].files;
    for (var i = 0; i < files.length; i++) {
        form.append("tutes[]", files[i]);
    }


    const validationMap = {
        faculty: ["#faculty-selector", "#faculty-feedback"],
        programme: ["#programme-selector", "#programme-feedback"],
        intake: ["#intake-selector", "#intake-feedback"],
        level: ["#level-selector", "#level-feedback"],
        subject: ["#subject-selector", "#subject-feedback"],
        lecturer: ["#lecturer-selector", "#lecturer-feedback"],
        date: ["#lecture-date", "#date-feedback"],
        start_time: ["#start-time", "#start-time-feedback"],
        end_time: ["#end-time", "#end-time-feedback"],
        attachment_name: ["#attachment-name", "#attachment-name-feedback"],
        link: ["#link-input", "#link-feedback"],
        title: ["#title", "#title-feedback"],
        tutes: ["#file-selector-input", "#tutes-feedback"],
        start_date: ["#start-date", "#start-date-feedback"],
        end_date: ["#end-date", "#end-date-feedback"],
        days: ["#days", "#days-feedback"],
    };

    if ($("#recurringLecture").is(":checked")) {
        validationMap.start_time = ["#r-start-time", "#r-start-time-feedback"];
        validationMap.end_time = ["#r-end-time", "#r-end-time-feedback"];
    }

    fetch("api/scheduleLecture.php", {
        method: "POST",
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation(response, validationMap, false);
            if (response.error) {
                showToast("error", response.error);
            } else if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload() }, 3000);
            } else {
                applyValidation(response, validationMap);
                if (response.tute) {
                    const feedback = document.getElementById('attachment-feedback');
                    feedback.innerHTML = `
                    <ul class="ps-3">
                        ${response.tute.map(e => `<li>${e}</li>`).join('')}
                    </ul>
                    `;
                }
            }
        })
        .catch((error) => {
            console.log(error);
            showToast("error", "Something went wrong.Please try again later");
        });
}

const filterScheduledLectures = () => {
    const data = {
        faculty: $("#faculty-selector").val(),
        programme: $("#programme-selector").val(),
        intake: $("#intake-selector").val(),
        level: $("#level-selector").val(),
        subject: $("#subject-selector").val(),
        lecturer: $("#lecturer-selector").val(),
        from_date: $("#from-date").val(),
        to_date: $("#to-date").val(),
        from_time: $("#from-time").val(),
        to_time: $("#to-time").val(),
        lec_type: $("#type-selector").val(),
        filter: $("#filter-selector").val(),
        page: selectedPage,
        result_per_page: $("#show-result-selector").val()
    }

    fetch('api/filterScheduledLectures.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                } else {
                    showToast("success", response.success);
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                    setTimeout(() => { window.location.reload() }, 4000);
                }
            } catch (error) {
                $("#schedule-table-container").html(data);

            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const loadLecture = (subject) => {
    const data = {
        subject: subject,
        lecturer: $("#lecturer-selector").val(),
        date: $("#lecture-date").val(),
    }

    fetch(`api/loadLecture.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                    $("#lecture-selector").attr("disabled", true);
                } else if (response.hasOwnProperty('subject')) {
                    $("#lecture-selector").attr("disabled", true);
                    $("#lecture-selector").html("<option value='0'>- Select lecturer -</option>");
                    $("#subject-selector").addClass("is-invalid");
                    $("#subject-feedback").text(response.subject);
                }
            } catch (error) {
                $("#subject-selector").removeClass("is-invalid");
                $("#subject-feedback").text("");
                $("#lecture-selector").removeAttr("disabled");
                $("#lecture-selector").html(data);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const assignLecturerToSubject = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        subject: id,
        lecturer: $("#lecturer-selector").val(),
        hourlyRate: $("#lecturer-hourly-rate").val(),
        assignmentFee: $("#assignment-fee").val(),
        examinationFee: $("#examination-fee").val()
    }

    const validationMap = {
        lecturer: ["#lecturer-selector", "#lecturer-feedback"],
        hourly_rate: ["#lecturer-hourly-rate", "#hourly-rate-feedback"],
        assignment_fee: ["#assignmnet-fee", "#assignment-fee-feedback"],
        examination_fee: ["#examination-fee", "#examination-fee-feedback"],
    };

    fetch('api/assignLecturerToSubject.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation(response, validationMap, false);
            if (response.done) {
                toggleBtnLoader(btn, false);
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const editSubjectAssignLecturer = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        lecturerId: id,
        hourlyRate: $("#edit-lecturer-hourly-rate").val(),
        assignmentFee: $("#edit-assignment-fee").val(),
        examinationFee: $("#edit-examination-fee").val()
    }

    const validationMap = {
        hourly_rate: ["#edit-lecturer-hourly-rate", "#edit-hourly-rate-feedback"],
        assignment_fee: ["#edit-assignmnet-fee", "#edit-assignment-free-feedback"],
        examination_fee: ["#edit-examination-fee", "#edit-examination-fee-feedback"],
    };

    fetch('api/editSubjectAssignLecturer.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation(response, validationMap, false);
            if (response.done) {
                toggleBtnLoader(btn, false);
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const saveStudentPolicy = (btn) => {
    toggleBtnLoader(btn, true);
    fetch('api/saveStudentPolicy.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ policy: studentPolicy.getHTMLCode() })
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, true);
            if (response.error) {
                showToast("error", response.error);
            } else {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addNews = (btn) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("content", newsContent.getHTMLCode());
    form.append("image", $("#file-input").prop("files")[0]);

    const validationMap = {
        content: ["#div_editor1", "#content-feedback"],
        image: [null, "#image-feedback"]
    };

    fetch('api/addNews.php', {
        method: 'POST',
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation(response, validationMap, false);
            if (response.error) {
                showToast("error", response.error);
            } else if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.replace("news"); }, 3000);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const editNews = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("id", id);
    form.append("content", newsContent.getHTMLCode());
    form.append("image", $("#file-input").prop("files")[0]);

    const validationMap = {
        content: ["#div_editor1", "#content-feedback"],
        image: [null, "#image-feedback"]
    };

    fetch('api/editNews.php', {
        method: 'POST',
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation(response, validationMap, false);
            if (response.error) {
                showToast("error", response.error);
            } else if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const removeNews = (id) => {
    toggleLoader(true);
    fetch('api/removeNews.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ news_id: id })
    })
        .then((res) => res.json())
        .then((data) => {
            toggleLoader(false);
            if (response.error) {
                showToast("error", response.error);
            } else {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const uploadLectureRecording = (btn, lectureId) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("id", lectureId);
    form.append("image", $("#file-input").prop("files")[0]);
    form.append("expire_date", $("#expire-date").val());
    form.append("recording_link", $("#recording-link").val());

    const validationMap = {
        cover: ["", "#cover-feedback"],
        expire_date: ["#expire-date", "#expire-date-feedback"],
        lecture: ["#lecture-selector", "#lecture-feedback"],
        recording_link: ["#recording-link", "#link-feedback"],
        image: ["#file-input", "#image-feedback"]
    };

    fetch('api/uploadLectureRecording.php', {
        method: 'POST',
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.history.back(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterSubjectHasAssignments = (id) => {
    const data = {
        subject_id: id,
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        sortByDate: sortCondition1,
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterSubjectHasAssignments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#assignments-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const uploadAssignmentsMarksBulk = (btn, id) => {
    toggleBtnLoader(btn, true);
    const formData = new FormData();
    formData.append("assignment_id", id);

    formData.append("file", $("#file-selector-input")[0].files[0]);

    console.log($("#file-selector-input")[0].files[0]);
    fetch("api/uploadAssignmentMarksBulk.php", {
        method: "POST",
        body: formData,
    })
        .then((res) => res.json())
        .then(async (response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else if (response.file) {
                showToast("error", response.file);
                $('#csv-file-feedback').text(response.file);
            } else {
                setTimeout(() => { showToast("error", "An error occured.Please check error log and try again."); }, 1000);
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF();

                let y = 10;

                pdf.setFontSize(10);

                for (let i = 0; i < response.dataErrors.length; i++) {
                    for (let j = 0; j < response.dataErrors[i].length; j++) {
                        pdf.text(response.dataErrors[i][j], 8, y);
                        y += 10;
                    }
                }

                const pdfOutput = pdf.output('blob');
                const pdfUrl = URL.createObjectURL(pdfOutput);

                setTimeout(() => window.open(pdfUrl, '_blank'), 4000);

                setTimeout(() => URL.revokeObjectURL(pdfUrl), 5000);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const addAssignmentMark = (btn, id, intakeId) => {
    toggleBtnLoader(btn, true);
    fetch('api/addAssignmentMark.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: id, mark: $("#mark").val() }),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                $("#addMark").modal('hide');
                $("#editMark").modal('hide');
                showToast("success", response.done);
                filterLPRecorrectionRequestStudentsAssignments(intakeId);
            } else if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else {
                showToast("error", "Something went wrong.Check data fields and try again");
                if (response.hasOwnProperty("mark")) {
                    $("#mark").addClass("is-invalid");
                    $("#mark-feedback").text(response.mark);
                } else {
                    $("#mark").removeClass("is-invalid");
                    $("#mark-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}

const editAssignmentMark = (id, assignmentId) => {
    fetch('api/addAssignmentMark.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: id, mark: $("#edit-mark").val() }),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#addMark").modal('hide');
                $("#editMark").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterLPStudentsAssignments(assignmentId);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("mark")) {
                    $("#mark").addClass("is-invalid");
                    $("#mark-feedback").text(response.mark);
                } else {
                    $("#mark").removeClass("is-invalid");
                    $("#mark-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}
const addLecturerAssignmentMark = (id, intakeId) => {
    toggleLoader(true);
    fetch('api/addAssignmentMark.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: id, mark: $("#mark").val() }),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);

            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#addMark").modal('hide');
                $("#editMark").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterLPStudentsAssignments(intakeId);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("mark")) {
                    $("#mark").addClass("is-invalid");
                    $("#mark-feedback").text(response.mark);
                } else {
                    $("#mark").removeClass("is-invalid");
                    $("#mark-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}

const editLecturerAssignmentMark = (btn, id, intakeId) => {
    toggleBtnLoader(btn, true);
    fetch('api/addAssignmentMark.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: id, mark: $("#edit-mark").val() }),
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                $("#editMark").modal('hide');
                showToast("success", response.done);
                filterLPStudentsAssignments(intakeId);
                toggleBtnLoader(btn, false);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    mark: ["#mark", "#mark-feedback"],
                };
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}
const filterStudentsAssignments = (id) => {
    const data = {
        assignmentId: id,
        text: $("#search-text").val(),
        filter: $("#filter-selector").val(),
        sort: $("#sort-selector").val(),
        sortByName: sortCondition1,
        sortByDate: sortCondition2,
        resultPerPage: $("#show-result-selector").val(),
        assignLecturer: $("#lecturer-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterStudentsAssignments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-assignments-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const addExamination = (id) => {
    toggleLoader(true);
    const form = new FormData();
    form.append("subject_id", id);
    form.append("code", $("#exam-code").val());
    form.append("topic", $("#exam-name").val());
    form.append("date", $("#exam-date").val());
    form.append("type", $("#exam-type").val());

    fetch('api/addExamination.php', {
        method: "POST",
        body: form,
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("code")) {
                    $("#exam-code").addClass("is-invalid");
                    $("#exam-code-feedback").text(response.code);
                } else {
                    $("#exam-code").removeClass("is-invalid");
                    $("#exam-code-feedback").text("");
                }

                if (response.hasOwnProperty("topic")) {
                    $("#exam-name").addClass("is-invalid");
                    $("#exam-name-feedback").text(response.topic);
                } else {
                    $("#exam-name").removeClass("is-invalid");
                    $("#exam-name-feedback").text("");
                }

                if (response.hasOwnProperty("date")) {
                    $("#exam-date").addClass("is-invalid");
                    $("#exam-date-feedback").text(response.date);
                } else {
                    $("#exam-date").removeClass("is-invalid");
                    $("#exam-date-feedback").text("");
                }

                if (response.hasOwnProperty("type")) {
                    $("#exam-type").addClass("is-invalid");
                    $("#exam-type-feedback").text(response.type);
                } else {
                    $("#exam-type").removeClass("is-invalid");
                    $("#exam-type-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}
const scheduleExamination = (id) => {
    toggleLoader(true);
    const form = new FormData();
    form.append("subject_id", id);
    form.append("code", $("#exam-code").val());
    form.append("topic", $("#exam-name").val());
    form.append("date", $("#exam-date").val());
    form.append("type", $("#exam-type").val());

    ////console.log($("#exam-type").val());
    const type = $("#exam-type").val();

    if (type == 1) {
        form.append("start_time", $("#physical-start-time").val());
        form.append("end_time", $("#physical-end-time").val());
        form.append("duration", $("#physical-duration").val());
        form.append("location", $("#physical-location").val());
    } else if (type == 2 || type == 3) {
        form.append("start_time", $("#online-start-time").val());
        form.append("end_time", $("#online-end-time").val());
        form.append("duration", $("#online-duration").val());
        form.append("file", $("#file-selector-input")[0].files[0]);

        if (type == 3) {
            form.append("quiz_type", $("#quiz-type").val());
        }
    }

    fetch('api/scheduleExamination.php', {
        method: "POST",
        body: form,
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("code")) {
                    $("#exam-code").addClass("is-invalid");
                    $("#exam-code-feedback").text(response.code);
                } else {
                    $("#exam-code").removeClass("is-invalid");
                    $("#exam-code-feedback").text("");
                }
                if (response.hasOwnProperty("topic")) {
                    $("#exam-name").addClass("is-invalid");
                    $("#exam-name-feedback").text(response.topic);
                } else {
                    $("#exam-name").removeClass("is-invalid");
                    $("#exam-name-feedback").text("");
                }
                if (response.hasOwnProperty("date")) {
                    $("#exam-date").addClass("is-invalid");
                    $("#exam-date-feedback").text(response.date);
                } else {
                    $("#exam-date").removeClass("is-invalid");
                    $("#exam-date-feedback").text("");
                }
                if (response.hasOwnProperty("type")) {
                    $("#exam-type").addClass("is-invalid");
                    $("#exam-type-feedback").text(response.type);
                } else {
                    $("#exam-type").removeClass("is-invalid");
                    $("#exam-type-feedback").text("");
                }
                if (response.hasOwnProperty("physical_start_time")) {
                    $("#physical-start-time").addClass("is-invalid");
                    $("#physical-start-time-feedback").text(response.physical_start_time);
                } else {
                    $("#physical-start-time").removeClass("is-invalid");
                    $("#physical-start-time-feedback").text("");
                }
                if (response.hasOwnProperty("physical_end_time")) {
                    $("#physical-end-time").addClass("is-invalid");
                    $("#physical-end-time-feedback").text(response.physical_end_time);
                } else {
                    $("#physical-end-time").removeClass("is-invalid");
                    $("#physical-end-time-feedback").text("");
                }
                if (response.hasOwnProperty("online_start_time")) {
                    $("#online-start-time").addClass("is-invalid");
                    $("#online-start-time-feedback").text(response.online_start_time);
                } else {
                    $("#online-start-time").removeClass("is-invalid");
                    $("#online-start-time-feedback").text("");
                }
                if (response.hasOwnProperty("online_end_time")) {
                    $("#online-end-time").addClass("is-invalid");
                    $("#online-end-time-feedback").text(response.online_end_time);
                } else {
                    $("#online-end-time").removeClass("is-invalid");
                    $("#online-end-time-feedback").text("");
                }
                if (response.hasOwnProperty("location")) {
                    $("#location").addClass("is-invalid");
                    $("#location-feedback").text(response.location);
                } else {
                    $("#location").removeClass("is-invalid");
                    $("#location-feedback").text("");
                }
                if (response.hasOwnProperty("file")) {
                    $("#file-selector-input").addClass("is-invalid");
                    $("#file-feedback").text(response.file);
                } else {
                    $("#file-selector-input").removeClass("is-invalid");
                    $("#file-feedback").text("");
                }
                if (response.hasOwnProperty("quiz_type")) {
                    $("#quiz-type").addClass("is-invalid");
                    $("#quiz-type-feedback").text(response.quiz_type);
                } else {
                    $("#quiz-type").removeClass("is-invalid");
                    $("#quiz-type-feedback").text("");
                }
                if (response.hasOwnProperty("physical_duration")) {
                    $("#physical-duration").addClass("is-invalid");
                    $("#physical-duration-feedback").text(response.physical_duration);
                } else {
                    $("#physical-duration").removeClass("is-invalid");
                    $("#physical-duration-feedback").text("");
                }
                if (response.hasOwnProperty("online_duration")) {
                    $("#online-duration").addClass("is-invalid");
                    $("#online-duration-feedback").text(response.online_duration);
                } else {
                    $("#online-duration").removeClass("is-invalid");
                    $("#online-duration-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}

const editScheduledLecture = (id) => {
    toggleLoader(true);
    const form = new FormData();
    form.append("lecture_id", id);
    form.append("level_id", $("#level-selector").val());
    form.append("subject_id", $("#subject-selector").val());
    form.append("lecturer_id", $("#lecturer-selector").val());
    form.append("lecture_date", $("#lecture-date").val());
    form.append("start_time", $("#start-time").val());
    form.append("end_time", $("#end-time").val());
    form.append("physical", $("#physical-check-box").is(":checked") ? true : false);
    form.append("online", $("#online-check-box").is(":checked") ? true : false);
    form.append("link", $("#link-input").val());
    form.append("tutes", $("#file-selector-input")[0].files[0]);
    // Or for multiple files:
    var files = $("#file-selector-input")[0].files;
    for (var i = 0; i < files.length; i++) {
        form.append("tutes[]", files[i]);
    }

    fetch("api/editScheduledLecture.php", {
        method: "POST",
        body: form
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload() }, 4000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty('date')) {
                    $("#lecture-date").addClass("is-invalid");
                    $("#date-feedback").text(response.date);
                } else {
                    $("#lecture-date").removeClass("is-invalid");
                    $("#date-feedback").text("");
                }
                if (response.hasOwnProperty('start_time')) {
                    $("#start-time").addClass("is-invalid");
                    $("#start-time-feedback").text(response.start_time);
                } else {
                    $("#start-time").removeClass("is-invalid");
                    $("#start-time-feedback").text("");
                }
                if (response.hasOwnProperty('end_time')) {
                    $("#end-time").addClass("is-invalid");
                    $("#end-time-feedback").text(response.end_time);
                } else {
                    $("#end-time").removeClass("is-invalid");
                    $("#end-time-feedback").text("");
                }
                if (response.hasOwnProperty('attachment_name')) {
                    $("#attachment-name").addClass("is-invalid");
                    $("#attachment-name-feedback").text(response.attachment_name);
                } else {
                    $("#attachment-name").removeClass("is-invalid");
                    $("#attachment-name-feedback").text("");
                }
                if (response.hasOwnProperty('link')) {
                    $("#link-input").addClass("is-invalid");
                    $("#link-feedback").text(response.link);
                } else {
                    $("#link-input").removeClass("is-invalid");
                    $("#link-feedback").text("");
                }
            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const assignLecturerToStudentsAssignments = (btn, id) => {
    const lecturer_id = $("#lecturer-selector").val();
    if (lecturer_id != 0) {
        toggleBtnLoader(btn, true);
        const data = {
            assignment_id: id,
            search_text: $("#search-text").val(),
            sort: $("#sort-selector").val(),
            resultPerPage: $("#result-per-page").val(),
            lecturer_id: lecturer_id,
            checkedStudents: selectedUsers,
            uncheckedStudents: unSelectedUsers,
            currentPage: selectedPage,
        }
        fetch('api/assignLecturerToStudentsAssignments.php', {
            method: "POST",
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.done) {
                    showToast("success", response.done);
                    filterStudentsAssignments(id);
                    toggleBtnLoader(btn, false);
                } else if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    } else {
        showToast("warning", "Please select the students assignments assigned to the lecturer first.");
    }
}

const filterSubjectHasExaminations = (id) => {
    ////console.log(id);
    const data = {
        subjectId: id,
        text: $("#search-text").val(),
        type: $("#type-selector").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterSubjectHasExaminations.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#examinations-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const updateScheduledExamination = (id) => {
    toggleLoader(true);
    const form = new FormData();

    form.append("exam_id", id);
    form.append("code", $("#exam-code").val());
    form.append("topic", $("#exam-name").val());
    form.append("date", $("#exam-date").val());
    form.append("type", $("#exam-type").val());

    ////console.log($("#exam-type").val());
    const type = $("#exam-type").val();

    if (type == 1) {
        form.append("start_time", $("#physical-start-time").val());
        form.append("end_time", $("#physical-end-time").val());
        form.append("duration", $("#physical-duration").val());
        form.append("location", $("#physical-location").val());
    } else if (type == 2 || type == 3) {
        form.append("start_time", $("#online-start-time").val());
        form.append("end_time", $("#online-end-time").val());
        form.append("duration", $("#online-duration").val());
        form.append("file", $("#file-selector-input")[0].files[0]);

        if (type == 3) {
            form.append("quiz_type", $("#quiz-type").val());
        }
    }

    fetch('api/updateScheduleExamination.php', {
        method: "POST",
        body: form,
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("code")) {
                    $("#exam-code").addClass("is-invalid");
                    $("#exam-code-feedback").text(response.code);
                } else {
                    $("#exam-code").removeClass("is-invalid");
                    $("#exam-code-feedback").text("");
                }
                if (response.hasOwnProperty("topic")) {
                    $("#exam-name").addClass("is-invalid");
                    $("#exam-name-feedback").text(response.topic);
                } else {
                    $("#exam-name").removeClass("is-invalid");
                    $("#exam-name-feedback").text("");
                }
                if (response.hasOwnProperty("date")) {
                    $("#exam-date").addClass("is-invalid");
                    $("#exam-date-feedback").text(response.date);
                } else {
                    $("#exam-date").removeClass("is-invalid");
                    $("#exam-date-feedback").text("");
                }
                if (response.hasOwnProperty("type")) {
                    $("#exam-type").addClass("is-invalid");
                    $("#exam-type-feedback").text(response.type);
                } else {
                    $("#exam-type").removeClass("is-invalid");
                    $("#exam-type-feedback").text("");
                }
                if (response.hasOwnProperty("physical_start_time")) {
                    $("#physical-start-time").addClass("is-invalid");
                    $("#physical-start-time-feedback").text(response.physical_start_time);
                } else {
                    $("#physical-start-time").removeClass("is-invalid");
                    $("#physical-start-time-feedback").text("");
                }
                if (response.hasOwnProperty("physical_end_time")) {
                    $("#physical-end-time").addClass("is-invalid");
                    $("#physical-end-time-feedback").text(response.physical_end_time);
                } else {
                    $("#physical-end-time").removeClass("is-invalid");
                    $("#physical-end-time-feedback").text("");
                }
                if (response.hasOwnProperty("online_start_time")) {
                    $("#online-start-time").addClass("is-invalid");
                    $("#online-start-time-feedback").text(response.online_start_time);
                } else {
                    $("#online-start-time").removeClass("is-invalid");
                    $("#online-start-time-feedback").text("");
                }
                if (response.hasOwnProperty("online_end_time")) {
                    $("#online-end-time").addClass("is-invalid");
                    $("#online-end-time-feedback").text(response.online_end_time);
                } else {
                    $("#online-end-time").removeClass("is-invalid");
                    $("#online-end-time-feedback").text("");
                }
                if (response.hasOwnProperty("location")) {
                    $("#location").addClass("is-invalid");
                    $("#location-feedback").text(response.location);
                } else {
                    $("#location").removeClass("is-invalid");
                    $("#location-feedback").text("");
                }
                if (response.hasOwnProperty("file")) {
                    $("#file-selector-input").addClass("is-invalid");
                    $("#file-feedback").text(response.file);
                } else {
                    $("#file-selector-input").removeClass("is-invalid");
                    $("#file-feedback").text("");
                }
                if (response.hasOwnProperty("quiz_type")) {
                    $("#quiz-type").addClass("is-invalid");
                    $("#quiz-type-feedback").text(response.quiz_type);
                } else {
                    $("#quiz-type").removeClass("is-invalid");
                    $("#quiz-type-feedback").text("");
                }
                if (response.hasOwnProperty("physical_duration")) {
                    $("#physical-duration").addClass("is-invalid");
                    $("#physical-duration-feedback").text(response.physical_duration);
                } else {
                    $("#physical-duration").removeClass("is-invalid");
                    $("#physical-duration-feedback").text("");
                }
                if (response.hasOwnProperty("online_duration")) {
                    $("#online-duration").addClass("is-invalid");
                    $("#online-duration-feedback").text(response.online_duration);
                } else {
                    $("#online-duration").removeClass("is-invalid");
                    $("#online-duration-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}
var addNewPermissionsUserCount = 0;
var newPermissionUsers = [];
const loadAvailableUserFacultyPermission = (btn, facultyId) => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            facultyId: facultyId,
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserFacultyPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.text())
            .then((data) => {
                try {
                    const response = JSON.parse(data);
                    if (response.error) {
                        toggleBtnLoader(btn, false);
                        showToast("error", response.error);
                    } else {
                        toggleBtnLoader(btn, false);
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                    }
                } catch (e) {
                    displayUserPermissions(data);
                    toggleBtnLoader(btn, true);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
};

const facultyPermissionsSearchUsers = (facultyId) => {
    const searchText = $('#search-text').val();
    const data = {
        facultyId: facultyId,
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/facultyPermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log("Data from server:", data);
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    ////console.log("JSON Response:", jsonResponse);
                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    ////console.log("Error parsing JSON:", e);
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addFacultyUserPermissions = (btn, facultyId, status) => {
    if (userPermissions == []) {
        showToast("warning", "Select new users' permissions and continue.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            facultyId: facultyId,
            usersPermissions: userPermissions,
        };
        fetch('api/addFacultyUserPermissions.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                } else if (response.done) {
                    toggleBtnLoader(btn, false);
                    showToast("success", response.done);
                    setTimeout(() => { window.location.reload(); }, 3000);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
}

const filterFacultyPermissions = (facultyId) => {
    const data = {
        facultyId: facultyId,
        text: $("#search-text").val(),
        selectedPermissions: selectedPermissions,
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterFacultyPermissions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const updateFacultyUserPermission = (btn, facultyId, userId) => {
    const selectValue = $("#user-permission-update-selector").val();
    const checkAll = $("#check-all").prop('checked');
    if (selectValue == 0) {
        showToast("warning", "Please select an option if you want to update the status.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            facultyId: facultyId,
            userId: userId,
            checkAll: checkAll,
            status: selectValue,
            selectedPermissions: selectedPermissions,
            unselectedPermissions: unSelectedPermissions
        }

        fetch('api/updateFacultyUserPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                } else if (response.done) {
                    toggleBtnLoader(btn, false);
                    showToast("success", response.done);
                    setTimeout(() => { window.location.reload(); }, 3000);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
}

const loadAvailableUserProgrammePermission = (btn, programmeId) => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            programmeId: programmeId,
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserProgrammePermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((response) => response.text())
            .then((data) => {
                try {
                    const response = JSON.parse(data);
                    if (response.error) {
                        toggleBtnLoader(btn, false);
                        showToast("error", response.error);
                    } else {
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                        toggleBtnLoader(btn, false);
                    }
                } catch (e) {
                    displayUserPermissions(data);
                    toggleBtnLoader(btn, false);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
};

const programmePermissionsSearchUsers = (facultyId) => {
    const searchText = $('#search-text').val();
    const data = {
        facultyId: facultyId,
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/programmePermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log("Data from server:", data);
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    ////console.log("JSON Response:", jsonResponse);

                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    ////console.log("Error parsing JSON:", e);
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addProgrammeUserPermissions = (btn, programmeId, status = 1) => {
    if (userPermissions == []) {
        showToast("warning", "Select new users' permissions and continue.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            programmeId: programmeId,
            usersPermissions: userPermissions,
        };
        fetch('api/addProgrammeUserPermissions.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                } else if (response.done) {
                    toggleBtnLoader(btn, false);
                    showToast("success", response.done);
                    setTimeout(() => { window.location.reload(); }, 3000);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
}

const filterProgrammePermissions = (programmeId) => {
    const data = {
        programmeId: programmeId,
        text: $("#search-text").val(),
        selectedPermissions: selectedPermissions,
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterProgrammePermissions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const updateProgrammeUserPermission = (btn, userId) => {
    const selectValue = $("#user-permission-update-selector").val();
    const checkAll = $("#check-all").prop('checked');
    if (selectValue == 0) {
        showToast("warning", "Please select an option if you want to update the status.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            userId: userId,
            checkAll: checkAll,
            status: selectValue,
            selectedPermissions: selectedPermissions,
            unselectedPermissions: unSelectedPermissions
        }

        fetch('api/updateProgrammeUserPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                } else if (response.done) {
                    toggleBtnLoader(btn, false);
                    showToast("success", response.done);
                    setTimeout(() => { window.location.reload(); }, 3000);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
}

const loadAvailableUserIntakePermission = (btn, intakeId) => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            intakeId: intakeId,
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserIntakePermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.text())
            .then((data) => {
                try {
                    const response = JSON.parse(data);
                    if (response.error) {
                        toggleBtnLoader(btn, false);
                        showToast("error", response.error);
                    } else {
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                        toggleBtnLoader(btn, false);
                    }
                } catch (e) {
                    displayUserPermissions(data);
                    toggleBtnLoader(btn, false);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "Failed to add user. Please try again.");
            });
    }
};

const intakePermissionsSearchUsers = (facultyId) => {
    const searchText = $('#search-text').val();
    const data = {
        facultyId: facultyId,
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/intakePermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log("Data from server:", data);
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    ////console.log("JSON Response:", jsonResponse);

                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    ////console.log("Error parsing JSON:", e);
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addIntakeUserPermissions = (btn, intakeId, status = 1) => {
    if (userPermissions == []) {
        showToast("warning", "Select new users' permissions and continue.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            intakeId: intakeId,
            usersPermissions: userPermissions,
        };
        fetch('api/addIntakeUserPermissions.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                } else if (response.done) {
                    toggleBtnLoader(btn, false);
                    showToast("success", response.done);
                    setTimeout(() => { window.location.reload(); }, 3000);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }

}

const filterIntakePermissions = (intakeId) => {
    const data = {
        intakeId: intakeId,
        text: $("#search-text").val(),
        selectedPermissions: selectedPermissions,
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterIntakePermissions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                }
            } catch (e) {
                $("#users-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "Failed to filter permissions. Please try again.");
        });
}

const updateIntakeUserPermission = (btn, userId) => {
    const selectValue = $("#user-permission-update-selector").val();
    const checkAll = $("#check-all").prop('checked');
    if (selectValue == 0) {
        showToast("warning", "Please select an option if you want to update the status.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            userId: userId,
            checkAll: checkAll,
            status: selectValue,
            selectedPermissions: selectedPermissions,
            unselectedPermissions: unSelectedPermissions
        }

        fetch('api/updateIntakeUserPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                } else if (response.done) {
                    toggleBtnLoader(btn, false);
                    showToast("success", response.done);
                    setTimeout(() => { window.location.reload(); }, 3000);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
}

const loadAvailableUserLevelPermission = (btn, levelId) => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            levelId: levelId,
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserLevelPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((response) => response.text())
            .then((data) => {
                try {
                    const response = JSON.parse(data);
                    if (response.error) {
                        toggleBtnLoader(btn, false);
                        showToast("error", response.error);
                    } else {
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                        toggleBtnLoader(btn, false);
                    }
                } catch (e) {
                    displayUserPermissions(data);
                    toggleBtnLoader(btn, false);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
};

const levelPermissionsSearchUsers = (facultyId) => {
    const searchText = $('#search-text').val();
    const data = {
        facultyId: facultyId,
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/levelPermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log("Data from server:", data);
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    ////console.log("JSON Response:", jsonResponse);

                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    ////console.log("Error parsing JSON:", e);
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addLevelUserPermissions = (btn, levelId) => {
    if (userPermissions == []) {
        showToast("warning", "Select new users' permissions and continue.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            levelId: levelId,
            usersPermissions: userPermissions,
        };
        fetch('api/addLevelUserPermissions.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.done) {
                    toggleBtnLoader(btn, false);
                    showToast("success", response.done);
                    setTimeout(() => { window.location.reload(); }, 3000);
                } else if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }

}

const filterLevelPermissions = (levelId) => {
    const data = {
        levelId: levelId,
        text: $("#search-text").val(),
        selectedPermissions: selectedPermissions,
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterLevelPermissions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const updateLevelUserPermission = (btn, levelId, userId) => {
    const selectValue = $("#user-permission-update-selector").val();
    const checkAll = $("#check-all").prop('checked');
    if (selectValue == 0) {
        showToast("warning", "Please select an option if you want to update the status.");
    } else if (selectedPermissions.length == 0) {
        showToast("warning", "This user already has all permissions in this status. Please select another status to continue.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            levelId, levelId,
            userId: userId,
            checkAll: checkAll,
            status: selectValue,
            selectedPermissions: selectedPermissions,
            unselectedPermissions: unSelectedPermissions
        }

        fetch('api/updateLevelUserPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                } else if (response.done) {
                    toggleBtnLoader(btn, false);
                    showToast("success", response.done);
                    setTimeout(() => { window.location.reload(); }, 3000);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
}

const loadAvailableUserSubjectPermission = (btn, subjectId) => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            subjectId: subjectId,
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserSubjectPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.text())
            .then((data) => {
                try {
                    const response = JSON.parse(data);
                    if (response.error) {
                        showToast("error", response.error);
                        toggleBtnLoader(btn, false);
                    } else {
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                        toggleBtnLoader(btn, false);
                    }
                } catch (e) {
                    displayUserPermissions(data);
                    toggleBtnLoader(btn, false);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
};
const subjectPermissionsSearchUsers = (facultyId) => {
    const searchText = $('#search-text').val();
    const data = {
        facultyId: facultyId,
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/subjectPermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log("Data from server:", data);
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    ////console.log("JSON Response:", jsonResponse);

                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    ////console.log("Error parsing JSON:", e);
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addSubjectUserPermissions = (btn, subjectId, status = 1) => {
    if (userPermissions == []) {
        showToast("warning", "Select new users' permissions and continue.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            subjectId: subjectId,
            usersPermissions: userPermissions,
        };
        fetch('api/addSubjectUserPermissions.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                } else if (response.done) {
                    toggleBtnLoader(btn, false);
                    showToast("success", response.done);
                    setTimeout(() => { window.location.reload(); }, 3000);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }

}

const filterSubjectPermissions = (subjectId) => {
    const data = {
        subjectId: subjectId,
        text: $("#search-text").val(),
        selectedPermissions: selectedPermissions,
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterSubjectPermissions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const updateSubjectUserPermission = (btn, userId) => {
    const selectValue = $("#user-permission-update-selector").val();
    const checkAll = $("#check-all").prop('checked');
    if (selectValue == 0) {
        showToast("warning", "Please select an option if you want to update the status.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            userId: userId,
            checkAll: checkAll,
            status: selectValue,
            selectedPermissions: selectedPermissions,
            unselectedPermissions: unSelectedPermissions
        }

        fetch('api/updateSubjectUserPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                } else if (response.done) {
                    toggleBtnLoader(btn, false);
                    showToast("success", response.done);
                    setTimeout(() => { window.location.reload(); }, 3000);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
}

const addQuiz = (subjectId) => {
    toggleLoader(true);
    const quizTitle = $("#quiz-title").val();
    const examDate = $("#exam-date").val();
    const startTime = $("#start-time").val();
    const endDate = $("#end-date").val();
    const endTime = $("#end-time").val();
    const duration = $("#duration").val();
    const quizType = $("#quiz-type").val();
    const quizOrder = $("#quiz-order").val();

    const data = {
        subjectId: subjectId,
        quizTitle: quizTitle,
        examDate: examDate,
        startTime: startTime,
        endDate: endDate,
        endTime: endTime,
        duration: duration,
        quizType: quizType,
        quizOrder: quizOrder
    }

    fetch('api/addQuiz.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.href = "add-quiz-questions?quiz-id=" + response.quizId; }, 4000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty('title')) {
                    $("#quiz-title").addClass("is-invalid");
                    $('#quiz-title-feedback').html(response.title);
                } else {
                    $("#quiz-title").removeClass("is-invalid");
                    $('#quiz-title-feedback').html("");
                }
                if (response.hasOwnProperty('startDate')) {
                    $("#exam-date").addClass("is-invalid");
                    $('#exam-date-feedback').html(response.startDate);
                } else {
                    $("#exam-date").removeClass("is-invalid");
                    $('#exam-date-feedback').html("");
                }

                if (response.hasOwnProperty('startTime')) {
                    $("#start-time").addClass("is-invalid");
                    $('#start-time-feedback').html(response.startTime);
                } else {
                    $("#start-time").removeClass("is-invalid");
                    $('#start-time-feedback').html("");
                }

                if (response.hasOwnProperty('endDate')) {
                    $("#end-date").addClass("is-invalid");
                    $('#end-date-feedback').html(response.endDate);
                } else {
                    $("#end-date").removeClass("is-invalid");
                    $('#end-date-feedback').html("");
                }

                if (response.hasOwnProperty('endTime')) {
                    $("#end-time").addClass("is-invalid");
                    $('#end-time-feedback').html(response.endTime);
                } else {
                    $("#end-time").removeClass("is-invalid");
                    $('#end-time-feedback').html("");
                }

                if (response.hasOwnProperty('duration')) {
                    $("#duration").addClass("is-invalid");
                    $('#duration-feedback').html(response.duration);
                } else {
                    $("#duration").removeClass("is-invalid");
                    $('#duration-feedback').html("");
                }

                if (response.hasOwnProperty('quizType')) {
                    $("#quiz-type").addClass("is-invalid");
                    $('#quiz-type-feedback').html(response.quizType);
                } else {
                    $("#quiz-type").removeClass("is-invalid");
                    $('#quiz-type-feedback').html("");
                }
                if (response.hasOwnProperty('quizOrder')) {
                    $("#quiz-order").addClass("is-invalid");
                    $('#quiz-order-feedback').html(response.quizOrder);
                } else {
                    $("#quiz-order").removeClass("is-invalid");
                    $('#quiz-order-feedback').html("");
                }
            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });

}

const saveQuizQuestions = (quizId, subjectId) => {
    const quizzes = [];
    $('.quiz-item').each(function () {
        const quizId = $(this).data('quiz-id');
        const question = $(this).find('.question-input').val();
        let correctAnswer = null;
        const answers = [];
        $(this).find('.answer-input').each(function (index) {
            const isCorrect = $(this).siblings('.answer-checkbox').is(':checked');
            answers.push({
                option: $(this).val(),
                isCorrect: isCorrect
            });

            if (isCorrect) {
                correctAnswer = index + 1;
            }
        });
        quizzes.push({
            quizId,
            question,
            answers,
            correctAnswer
        });
    });

    const data = {
        quizzes: quizzes,
        quizId: quizId,
    }

    ////console.log(quizzes);
    toggleLoader(true);
    fetch('api/addQuizQuestions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location = `subject-quizes?subject-id=${subjectId}` }, 4000);
            } else if (response.hasOwnProperty('errors')) {
                setTimeout(() => {
                    showToast("error", "An error occurred. Please check the error log and try again.");
                }, 1000);

                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF();
                let y = 10;
                pdf.setFontSize(10);

                for (let i = 0; i < response.errors.length; i++) {
                    pdf.text(response.errors[i], 8, y);
                    y += 10;
                }

                const pdfOutput = pdf.output('blob');
                const pdfUrl = URL.createObjectURL(pdfOutput);

                setTimeout(() => window.open(pdfUrl, '_blank'), 4000);

                setTimeout(() => URL.revokeObjectURL(pdfUrl), 5000);
            } else {
                setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterQuizes = (subjectId) => {
    const data = {
        subjectId: subjectId,
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,

    }

    fetch('api/filterQuizes.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#quizes-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const updateQuiz = (quizId) => {
    toggleLoader(true);
    const quizTitle = $("#quiz-title").val();
    const examDate = $("#exam-date").val();
    const startTime = $("#start-time").val();
    const endDate = $("#end-date").val();
    const endTime = $("#end-time").val();
    const duration = $("#duration").val();
    const quizType = $("#quiz-type").val();
    const quizOrder = $("#quiz-order").val();

    const data = {
        quizId: quizId,
        quizTitle: quizTitle,
        examDate: examDate,
        startTime: startTime,
        endDate: endDate,
        endTime: endTime,
        duration: duration,
        quizType: quizType,
        quizOrder: quizOrder
    }

    fetch('api/updateQuiz.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty('title')) {
                    $("#quiz-title").addClass("is-invalid");
                    $('#quiz-title-feedback').html(response.title);
                } else {
                    $("#quiz-title").removeClass("is-invalid");
                    $('#quiz-title-feedback').html("");
                }

                if (response.hasOwnProperty('date')) {
                    $("#exam-date").addClass("is-invalid");
                    $('#exam-date-feedback').html(response.date);
                } else {
                    $("#exam-date").removeClass("is-invalid");
                    $('#exam-date-feedback').html("");
                }

                if (response.hasOwnProperty('startTime')) {
                    $("#start-time").addClass("is-invalid");
                    $('#start-time-feedback').html(response.startTime);
                } else {
                    $("#start-time").removeClass("is-invalid");
                    $('#start-time-feedback').html("");
                }

                if (response.hasOwnProperty('endTime')) {
                    $("#end-time").addClass("is-invalid");
                    $('#end-time-feedback').html(response.endTime);
                } else {
                    $("#end-time").removeClass("is-invalid");
                    $('#end-time-feedback').html("");
                }

                if (response.hasOwnProperty('quizType')) {
                    $("#quiz-type").addClass("is-invalid");
                    $('#quiz-type-feedback').html(response.quizType);
                } else {
                    $("#quiz-type").removeClass("is-invalid");
                    $('#quiz-type-feedback').html("");
                }
            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });

}

const updateQuizQuestions = (quizId) => {
    const quizzes = [];
    $('.quiz-item').each(function () {
        const quizId = $(this).data('quiz-id');
        const quizStatus = $(this).data('quiz-status');
        const question = $(this).find('.question-input').val();
        let correctAnswer = null;
        const answers = [];
        $(this).find('.answer-input').each(function (index) {
            const isCorrect = $(this).siblings('.answer-checkbox').is(':checked');
            answers.push({
                option: $(this).val(),
                isCorrect: isCorrect,
                answerId: $(this).data('answer-id')
            });

            if (isCorrect) {
                correctAnswer = index + 1;
            }
        });
        quizzes.push({
            quizId,
            quizStatus,
            question,
            answers,
            correctAnswer
        });
    });

    const data = {
        quizzes: quizzes,
        removeQuizes: removeQuizes,
        quizId: quizId,
    }

    ////console.log(quizzes);
    toggleLoader(true);
    fetch('api/updateQuizQuestions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('errors')) {
                setTimeout(() => {
                    showToast("error", "An error occurred. Please check the error log and try again.");
                }, 1000);

                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF();
                let y = 10;
                pdf.setFontSize(10);

                for (let i = 0; i < response.errors.length; i++) {
                    pdf.text(response.errors[i], 8, y);
                    y += 10;
                }

                const pdfOutput = pdf.output('blob');
                const pdfUrl = URL.createObjectURL(pdfOutput);

                setTimeout(() => window.open(pdfUrl, '_blank'), 4000);

                setTimeout(() => URL.revokeObjectURL(pdfUrl), 5000);
            } else {
                setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const removeQuizPermanantly = () => {
    $("#removeConfirmModal").modal("hide");
    toggleLoader(true);
    const quizId = $('#removeConfirmModal').data('quiz-id');

    fetch('api/removeQuiz.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ quizId: quizId }),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addStudentToIntake = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("intake_id", id);
    form.append("id", $("#student-intake-id").val());
    form.append("fname", $("#first-name").val());
    form.append("lname", $("#last-name").val());
    form.append("fullname", $("#full-name").val());
    form.append("nwi", $("#name-with-initials").val());
    form.append("title", $("#title-selector").val());
    form.append("gender", $("#gender-selector").val());
    form.append("marital_status", $("#marital-status-selector").val());
    form.append("nationality", $("#nationality").val());
    form.append("nic", $("#nic").val());
    form.append("passport", $("#passport").val());
    form.append("cob", $("#country-of-birth").val());
    form.append("cor", $("#country-of-residence").val());
    form.append("dob", $("#dob").val());
    form.append("email", $("#email").val());
    form.append("mobile", $("#mobile").val());
    form.append("whatsapp", $("#whatsapp").val());
    form.append("address", $("#address").val());
    form.append("address2", $("#address2").val());
    form.append("city", $("#city").val());
    form.append("pcode", $("#postal-code").val());
    form.append("image", $("#file-input").prop("files")[0]);
    form.append("grelationship", $("#grelationship-selector").val());
    form.append("gtitle", $("#gtitle-selector").val());
    form.append("gfname", $("#guardian-fname").val());
    form.append("glname", $("#guardian-lname").val());
    form.append("gemail", $("#guardian-email").val());
    form.append("gmobile", $("#guardian-mobile").val());
    form.append("gwhatsapp", $("#guardian-whatsapp").val());
    form.append("entry_type", $("input[name='entry_type']:checked").val());
    form.append("payment_option", $("#payment-option").val());
    form.append("certificate_name", $("#certificate-name").val());
    form.append("school", $("#school").val());
    form.append("ol_results", $("#ol-results").val());
    form.append("al_results", $("#al-results").val());
    form.append("work_experience", $("#work-experience").val());
    form.append("team_username", $("#team-username").val());

    appendStudentDocumentsToFormData(form);

    const validationMap = {
        id: ["#student-intake-id", "#student-intake-id-feedback"],
        fname: ["#first-name", "#fname-feedback"],
        lname: ["#last-name", "#lname-feedback"],
        fullname: ["#full-name", "#fullname-feedback"],
        nwi: ["#name-with-initials", "#nwi-feedback"],
        title: ["#title-selector", "#title-feedback"],
        gender: ["#gender-selector", "#gender-feedback"],
        marital_status: ["#marital-status-selector", "#marital-feedback"],
        nationality: ["#nationality", "#nationality-feedback"],
        cob: ["#country-of-birth", "#cob-feedback"],
        cor: ["#country-of-residence", "#cor-feedback"],
        nic: ["#nic", "#nic-feedback"],
        passport: ["#passport", "#passport-feedback"],
        dob: ["#dob", "#dob-feedback"],
        email: ["#email", "#email-feedback"],
        mobile: ["#mobile", "#mobile-feedback"],
        whatsapp: ["#whatsapp", "#whatsapp-feedback"],
        address: ["#address", "#address-feedback"],
        address2: ["#address2", "#address2-feedback"],
        city: ["#city", "#city-feedback"],
        pcode: ["#postal-code", "#postal-code-feedback"],
        faculty: ["#faculty-selector", "#faculty-feedback"],
        programme: ["#programme-selector", "#programme-feedback"],
        intake: ["#intake-selector", "#intake-feedback"],
        grelationship: ["#grelationship-selector", "#grelationship-feedback"],
        gtitle: ["#gtitle-selector", "#gtitle-feedback"],
        gfname: ["#guardian-fname", "#gfname-feedback"],
        glname: ["#guardian-lname", "#glname-feedback"],
        gemail: ["#guardian-email", "#gemail-feedback"],
        gmobile: ["#guardian-mobile", "#gmobile-feedback"],
        gwhatsapp: ["#guardian-whatsapp", "#gwhatsapp-feedback"],
        aic_application: ["#doc-aic-application", "#doc-aic-application-feedback"],
        university_application: ["#doc-university-application", "#doc-university-application-feedback"],
        signed_agreement: ["#doc-signed-agreement", "#doc-signed-agreement-feedback"],
        signed_program_brief: ["#doc-signed-program-brief", "#doc-signed-program-brief-feedback"],
        passport_nic: ["#doc-passport-nic", "#doc-passport-nic-feedback"],
        academic_documents: ["#doc-academic-documents", "#doc-academic-documents-feedback"],
        other_entry_documents: ["#doc-other-entry-documents", "#doc-other-entry-documents-feedback"],
        payment_slip: ["#doc-payment-slip", "#doc-payment-slip-feedback"],
        manager_checklist: ["#doc-manager-checklist", "#doc-manager-checklist-feedback"],
        discount_approved_document: ["#doc-discount-approved-document", "#doc-discount-approved-document-feedback"],
        certificate_name: ["#certificate-name", "#certificate-name-feedback"],
        school: ["#school", "#school-feedback"],
        ol_results: ["#ol-results", "#ol-results-feedback"],
        al_results: ["#al-results", "#al-results-feedback"],
        work_experience: ["#work-experience", "#work-experience-feedback"],
        team_username: ["#team-username", "#team-username-feedback"],
        team_password: ["#team-password", "#team-password-feedback"]
    };

    fetch("api/addStudentToIntake.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addStudentToPaymentPlan = (btn, studentHasIntakeId) => {
    toggleBtnLoader(btn, true);
    const data = {
        studentHasIntakeId: studentHasIntakeId,
        paymentCategoryId: $("#payment-category").val(),
        paymentPlanId: $("#payment-plan").val(),
        paymentAmount: $("#payment-amount").val(),
        discountType: $("#discount-type").val(),
        discountAmount: $("#discount").val(),
        paymentPeriod: $("#period").val(),
        discountNote: $("#discount-note").val(),
    }

    const validationMap = {
        paymentCategory: ["#payment-category", "#payment-category-feedback"],
        paymentPlan: ["#payment-plan", "#payment-plan-feedback"],
        paymentAmount: ["#payment-amount", "#payment-amount-feedback"],
        discountType: ["#discount-type", "#discount-type-feedback"],
        discountAmount: ["#discount", "#discount-feedback"],
        discountNote: ["#discount-note", "#discount-note-feedback"],
        paymentPeriod: ["#period", "#period-feedback"]
    };

    applyValidation('', validationMap, false);

    fetch('api/addStudentToPaymentPlan.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleBtnLoader(btn, false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                applyValidation(response, validationMap);
            }
        })
}

const updatePaymentStatus = (btn, paymentId) => {
    toggleBtnLoader(btn, true);
    const data = {
        paymentId: paymentId,
        paymentStatus: $("#status-selector").val(),
        amount: $("#paid-amount").val(),
        method: $("#payment-method").val(),
        date: $("#paid-date").val(),
        note: $("#note").val()
    }

    fetch('api/updatePaymentStatus.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleBtnLoader(btn, false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#updatePaymentStatus").modal("hide");
                showToast("success", response.done);
                if (response.receiptData) {
                    openInvoicePrintWindow(response.receiptData);
                } else {
                    setTimeout(() => { window.location.reload(); }, 3000);
                }
            } else if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else if (response.hasOwnProperty('warning')) {
                showToast("warning", response.warning);
            } else {
                showToast("error", "Something went wrong.Check data fields and try again");
                if (response.hasOwnProperty('status')) {
                    $("#status-selector").addClass("is-invalid");
                    $("#status-selector-feedback").text(response.status);
                } else {
                    $("#status-selector").removeClass("is-invalid");
                    $("#status-selector-feedback").text("");
                }

                if (response.hasOwnProperty('note')) {
                    $("#note").addClass("is-invalid");
                    $("#note-feedback").text(response.note);
                } else {
                    $("#note").removeClass("is-invalid");
                    $("#note-feedback").text("");
                }

            }
        })
}

const filterPayments = (categoryId) => {
    const data = {
        categoryId: categoryId,
        text: $("#search-text").val(),
        status: $("#status-selector").val(),
        sort: $("#sort-selector").val(),
        year: $("#year").val(),
        month: $("#month").val(),
        sortByAmount: sortCondition1,
        sortByDate: sortCondition2,
        sortByName: sortCondition4,
        sortById: sortCondition3,
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterPayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#payments-table-container").html(data);
                getTableWidth("#payments-table", "#pagination-container")
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addPaymentCategory = (btn) => {
    toggleBtnLoader(btn, true);
    const data = {
        name: $("#new-category-name").val(),
        currency: $("#currency").val()
    }

    const validationMap = {
        name: ["#new-category-name", "#new-category-name-feedback"],
        currency: ["#currency", "#currency-feedback"]
    };


    fetch('api/addPaymentCategory.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            if (response.hasOwnProperty('done')) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const editPaymentCategory = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        categoryId: id,
        name: $("#edit-category-name").val(),
        currency: $("#edit-category-currency").val()
    }

    const validationMap = {
        name: ["#edit-category-name", "#edit-category-name-feedback"],
        currency: ["#edit-category-currency", "#edit-category-currency-feedback"]
    };

    fetch('api/editPaymentCategory.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            if (response.hasOwnProperty('done')) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterIntakePayments = (intakeId) => {
    const data = {
        intakeId: intakeId,
        text: $("#payment-search-text").val(),
        category: $("#payment-category-selector").val(),
        status: $("#payment-status-selector").val(),
        sort: $('#payment-sort-selector').val(),
        sortByAmount: sortCondition1,
        sortByDate: sortCondition2,
        sortByName: sortCondition3,
        sortById: sortCondition4,
        resultPerPage: $("#payment-show-result-selector").val(),
        page: selectedPage2,
    }

    fetch('api/filterIntakePayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#payments-table-container").html(data);
                getTableWidth("#payments-table", "#pagination-container")
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addStudentsToSubject = (id) => {
    toggleLoader(true);
    const data = {
        subjectId: id,
        selectStudents: selectedUsers,
        unSelectStudents: unSelectedUsers,
        checkAll: $('#check-all').prop('checked')
    }

    fetch('api/addStudentsToSubject.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false)
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                setTimeout(() => { showToast("warning", response.warning); }, 1000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterSubjectAddStudents = (id) => {
    const data = {
        subjectId: id,
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
        selectStudents: selectedUsers,
        unSelectStudents: unSelectedUsers,
    }

    ////console.log(data);

    fetch('api/filterSubjectAddStudents.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-table-container").html(data);

            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const filterSubjectStudents = (id) => {
    const data = {
        subjectId: id,
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        update: $("#update-selector").val(),
        page: selectedPage,
        selectStudents: selectedUsers,
        unSelectStudents: unSelectedUsers,
    }

    ////console.log(data);

    fetch('api/filterSubjectStudents.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-table-container").html(data);

            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const applySubjectStudentsUpdate = (id) => {
    const update = $("#update-selector").val();
    if (update != 0) {
        const data = {
            subjectId: id,
            update: update,
            selectStudents: selectedUsers,
            unSelectStudents: unSelectedUsers,
            currentPage: selectedPage,
            searchText: $("#search-text").val(),
            sortSelector: $("#sort-selector").val(),
            resultPerPage: $("#show-result-selector").val(),
        }
        ////console.log(data);
        fetch('api/updateSubjectStudents.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data),
        })
            .then(response => response.text())
            .then(data => {
                toggleLoader(false);
                ////console.log(data);
                const response = JSON.parse(data);
                if (response.done) {
                    setTimeout(() => { showToast("success", response.done); }, 1000);
                    filterSubjectStudents(id);
                    setDefault();
                } else if (response.error) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else if (response.warning) {
                    setTimeout(() => { showToast("warning", response.warning); }, 1000);
                }
            })
            .catch((error) => {
                console.error('Error:', error);
                showToast("error", "Something went wrong");
            });
    } else {
        showToast("warning", "Please select the desired student status change option.");
    }
}


const addStudentsToPaymentPlan = (btn, intakeId) => {
    toggleBtnLoader(btn, true);
    const data = {
        intakeId: intakeId,
        paymentPlanId: $("#payment-plan").val(),
        checkedStudents: selectedUsers,
        uncheckedStudents: unSelectedUsers,
        checkAll: $('#check-all').prop('checked')
    }

    const validationMap = {
        paymentAmount: ["#payment-amount", "#payment-amount-feedback"],
        discountType: ["#discount-type", "#discount-type-feedback"],
        discountAmount: ["#discount", "#discount-feedback"],
        paymentCategory: ["#payment-category", "#payment-category-feedback"],
        paymentPlan: ["#payment-plan", "#payment-plan-feedback"],
    }

    fetch('api/addStudentsToPaymentPlan.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.error) {
                showToast("error", response.error);
            } else if (response.warning) {
                showToast("warning", response.warning);
            } else {
                applyValidation(response, validationMap);
            }
        }).catch((error) => {
            console.error('Error:', error);
            showToast("error", "Something went wrong");
        });
}

const addStudentsToPayment = (intakeId, categoryId, planId) => {
    const data = {
        intakeId: intakeId,
        paymentCategoryId: categoryId,
        paymentPlanId: planId,
        paymentType: $("#payment-type").val(),
        paymentAmount: $("#payment-amount").val(),
        discountType: $("#discount-type").val(),
        discountAmount: $("#discount").val(),
        discountNote: $("#discount-note").val(),
        paymentNote: $("#payment-note").val(),
        cardPayment: $("#card-payament-enable").prop('checked'),
        checkedStudents: selectedUsers,
        uncheckedStudents: unSelectedUsers,
        checkAll: $('#check-all').prop('checked')
    }

    fetch('api/addStudentsToPayment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('warning')) {
                setTimeout(() => { showToast("warning", response.warning); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty('paymentType')) {
                    $("#payment-type").addClass("is-invalid");
                    $("#payment-type-feedback").text(response.paymentType);
                } else {
                    $("#payment-type").removeClass("is-invalid");
                    $("#payment-type-feedback").text("");
                }
                if (response.hasOwnProperty('paymentAmount')) {
                    $("#payment-amount").addClass("is-invalid");
                    $("#payment-amount-feedback").text(response.paymentAmount);
                } else {
                    $("#payment-amount").removeClass("is-invalid");
                    $("#payment-amount-feedback").text("");
                }
                if (response.hasOwnProperty('discountType')) {
                    $("#discount-type").addClass("is-invalid");
                    $("#discount-type-feedback").text(response.discountType);
                } else {
                    $("#discount-type").removeClass("is-invalid");
                    $("#discount-type-feedback").text("");
                }
                if (response.hasOwnProperty('discountAmount')) {
                    $("#discount").addClass("is-invalid");
                    $("#discount-feedback").text(response.discountAmount);
                } else {
                    $("#discount").removeClass("is-invalid");
                    $("#discount-feedback").text("");
                }
                if (response.hasOwnProperty('paymentNote')) {
                    $("#payment-note").addClass("is-invalid");
                    $("#payment-note-feedback").text(response.paymentNote);
                } else {
                    $("#payment-note").removeClass("is-invalid");
                    $("#payment-note-feedback").text("");
                }
            }
        }).catch((error) => {
            console.error('Error:', error);
        });
}

const addIntakeStudentPayment = (btn, paymentPlanId, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        paymentPlanId: paymentPlanId,
        data: collectPaymentData()
    }

    const validationMap = {
        paymentType: ['#payment-type', '#payment-type-feedback'],
        amount: ['#payment-amount', '#payment-amount-feedback'],
        discount: ['#discount', '#discount-feedback'],
        dueDate: ['#due-date', '#due-date-feedback'],
        penalty: ['#panelty-option', '#panelty-option-feedback'],
    };

    fetch('api/addIntakeStudentPayment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location = "intake-student-payments?student-id=" + id + "" }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);

                if (response.penalty_data) {
                    generatePenaltyPaymentErrorPDF(response);
                }
            }

        }).catch((error) => {
            console.log(error);
            showToast("error", "An error occured. Please try again later.");
        });
}

const scheduleLectureToSubject = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("subject_id", id);
    form.append("lecturer_id", $("#lecturer-selector").val());
    form.append("lecture-date", $("#lecture-date").val());
    form.append("start_time", $("#start-time").val());
    form.append("end_time", $("#end-time").val());
    form.append('title', $("#title").val());
    form.append("physical", $("#physical-checkbox").is(":checked"));
    form.append("online", $("#online-checkbox").is(":checked"));
    form.append("link", $("#link-input").val());
    form.append("tutes", $("#file-selector-input")[0].files[0]);
    var files = $("#file-selector-input")[0].files;
    for (var i = 0; i < files.length; i++) {
        form.append("tutes[]", files[i]);
    }
    fetch("api/scheduleLectureToSubject.php", {
        method: "POST",
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, false);
                showToast("success", response.done);
                setTimeout(() => { window.location.reload() }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    lecturer: ["#lecturer-selector", "#lecturer-feedback"],
                    date: ["#lecture-date", "#date-feedback"],
                    start_time: ["#start-time", "#start-time-feedback"],
                    end_time: ["#end-time", "#end-time-feedback"],
                    attachment_name: ["#attachment-name", "#attachment-name-feedback"],
                    link: ["#link-input", "#link-feedback"],
                    title: ["#title", "#title-feedback"]
                };
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);

                if (response.tute) {
                    const feedback = document.getElementById('attachment-feedback');
                    feedback.innerHTML = `
                    <ul class="ps-3">
                        ${response.tute.map(e => `<li>${e}</li>`).join('')}
                    </ul>
                    `;
                }

            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterSubjectScheduledLectures = (id) => {
    ////console.log($("#show-result-selector").val());
    const data = {
        subject: id,
        lecturer: $("#lecturer-selector").val(),
        from_date: $("#from-date").val(),
        to_date: $("#to-date").val(),
        from_time: $("#from-time").val(),
        to_time: $("#to-time").val(),
        lec_type: $("#type-selector").val(),
        filter: $("#filter-selector").val(),
        page: selectedPage,
        result_per_page: $("#show-result-selector").val()
    }



    fetch('api/filterSubjectScheduledLectures.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                } else {
                    showToast("success", response.success);
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                    setTimeout(() => { window.location.reload() }, 4000);
                }
            } catch (error) {
                $("#schedule-table-container").html(data);

            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const editLecture = (btn, id, programme, status, confirmation = false) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("lecture_id", id);
    form.append("programme_id", programme);
    form.append("title", $("#title").val());
    form.append("lecturer_id", $("#lecturer-selector").val());
    form.append("lecture_date", $("#lecture-date").val());
    form.append("confirmation", confirmation);
    form.append("start_time", $("#start-time").val());
    form.append("end_time", $("#end-time").val());
    form.append("status", $("#status-selector").val());
    form.append("physical", $("#physical-checkbox").is(":checked"));
    form.append("online", $("#online-checkbox").is(":checked"));
    form.append("recording", $("#recording-checkbox").is(":checked"));
    form.append("link", $("#link-input").val());
    form.append("tutes", $("#file-selector-input")[0].files[0]);
    form.append("edit_type", status);
    var files = $("#file-selector-input")[0].files;
    for (var i = 0; i < files.length; i++) {
        form.append("tutes[]", files[i]);
    }

    fetch("api/editLecture.php", {
        method: "POST",
        body: form
    })
        .then((res) => res.text())
        .then((data) => {
            const response = JSON.parse(data);
            $("#scheduleConfirmModal").modal('hide');
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload() }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else if (response.confirm_required) {
                let messages = "";

                if (response.lecturer_confirm) {
                    messages += `<div class="mb-2">• ${response.lecturer_confirm}</div>`;
                }

                if (response.date_confirm) {
                    messages += `<div class="mb-2">• ${response.date_confirm}</div>`;
                }

                document.getElementById("scheduleConfirmModalMessage").innerHTML =
                    "The selected lecture has scheduling conflicts. Do you want to continue anyway?";

                document.getElementById("scheduleConfirmDetails").innerHTML = messages;

                const confirmModal = new bootstrap.Modal(document.getElementById("scheduleConfirmModal"));
                $("#confirmScheduleSave").off("click");
                $("#confirmScheduleSave").on("click", function () {
                    editLecture(this, id, programme, status, true);
                });
                confirmModal.show();
                return;
            } else {
                const validationMap = {
                    date: ["#lecture-date", "#date-feedback"],
                    start_time: ["#start-time", "#start-time-feedback"],
                    end_time: ["#end-time", "#end-time-feedback"],
                    attachment_name: ["#attachment-name", "#attachment-name-feedback"],
                    link: ["#link-input", "#link-feedback"],
                    lecturer: ["#lecturer-input", "#lecturer-feedback"],
                    title: ["#title", "#title-feedback"]
                };

                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const editLectureRecording = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("recording", id);
    form.append("image", $("#file-input").prop("files")[0]);
    form.append("expire_date", $("#expire-date").val());
    form.append("recording_link", $("#recording-link").val());

    fetch('api/editLectureRecording.php', {
        method: 'POST',
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, false);
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    image: [null, "#cover-feedback"],
                    expire_date: ["#expire-date", "#expire-date-feedback"],
                    lecture: ["#lecture-selector", "#lecture-feedback"],
                    recording_link: ["#recording-link", "#link-feedback"]
                };

                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const addPreRecordedLectureToSubject = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("subject_id", id);
    form.append("lecturer_id", $("#lecturer-selector").val());
    form.append("duration", $("#duration").val());
    form.append("link", $("#link-input").val());
    form.append("tutes", $("#file-selector-input")[0].files[0]);
    var files = $("#file-selector-input")[0].files;
    for (var i = 0; i < files.length; i++) {
        form.append("tutes[]", files[i]);
    }

    fetch("api/addPreRecordedLectureToSubject.php", {
        method: "POST",
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, false);
                showToast("success", response.done);
                setTimeout(() => { window.location.reload() }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    lecturer: ["#lecturer-selector", "#lecturer-feedback"],
                    duration: ["#duration", "#duration-feedback"],
                    attachment_name: ["#attachment-name", "#attachment-name-feedback"],
                    link: ["#link-input", "#link-feedback"]
                };

                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);

                if (response.tute) {
                    const feedback = document.getElementById('attachment-feedback');
                    feedback.innerHTML = `
                    <ul class="ps-3">
                        ${response.tute.map(e => `<li>${e}</li>`).join('')}
                    </ul>
                    `;
                }
            }

        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const editSubjectPreRecordedLecture = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("lecture_id", id);
    form.append("lecturer_id", $("#lecturer-selector").val());
    form.append("duration", $("#duration").val());
    form.append("status", $("#status-selector").val());
    form.append("link", $("#link-input").val());
    form.append("tutes", $("#file-selector-input")[0].files[0]);
    var files = $("#file-selector-input")[0].files;
    for (var i = 0; i < files.length; i++) {
        form.append("tutes[]", files[i]);
    }

    fetch("api/editSubjectPreRecordedLecture.php", {
        method: "POST",
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, false);
                showToast("success", response.done);
                setTimeout(() => { window.location.reload() }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    attachment_name: ["#attachment-name", "#attachment-name-feedback"],
                    link: ["#link-input", "#link-feedback"],
                };

                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const saveBank = () => {
    const form = new FormData();
    form.append("bank", $("#bank-name").val());
    form.append("acc_no", $("#acc-number").val());
    form.append("acc_name", $("#acc-name").val());
    form.append("branch", $("#branch").val());
    form.append("branch_selector", $("#branch-selector").val());
    form.append("image", $("#file-input").prop("files")[0]);

    const validationMap = {
        bank: ["#bank-name", "#bank-feedback"],
        acc_no: ["#acc-number", "#acc-number-feedback"],
        acc_name: ["#acc-name", "#acc-name-feedback"],
        branch: ["#branch", "#branch-feedback"],
        branch_selector: ["#branch-selector", "#branch-selector-feedback"],
    }

    fetch('api/addBank.php', {
        method: 'POST',
        body: form
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else if (response.hasOwnProperty('done')) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                applyValidation(response, validationMap);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const loadAvailableUserUsersPermission = () => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        const data = {
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserUserPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((response) => response.text())
            .then((data) => {
                try {
                    const response = JSON.parse(data);
                    if (response.error) {
                        showToast("error", response.error);
                    } else {
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                    }
                } catch (e) {
                    displayUserPermissions(data);
                }
            })
            .catch((error) => {
                showToast("error", "An error occured. Please try again later.");
                showToast("error", "Failed to add user. Please try again.");
            });
    }
}

const UsersPermissionsSearchUsers = () => {
    const searchText = $('#search-text').val();
    const data = {
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/usersPermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log("Data from server:", data);
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    ////console.log("JSON Response:", jsonResponse);

                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    ////console.log("Error parsing JSON:", e);
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const loadAvailableUserStudentsPermission = (btn) => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserStudentPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.text())
            .then((text) => {
                let result;
                try {
                    result = JSON.parse(text);
                } catch {
                    result = text;
                }

                if (typeof result === "object" && result.error) {
                    if (response.error) {
                        showToast("error", response.error);
                    } else {
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                    }
                    toggleBtnLoader(btn, false);
                } else {
                    displayUserPermissions(result);
                    toggleBtnLoader(btn, false);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
}
const studentsPermissionsSearchUsers = () => {
    const searchText = $('#search-text').val();
    const data = {
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/studentPermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log("Data from server:", data);
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    ////console.log("JSON Response:", jsonResponse);

                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    ////console.log("Error parsing JSON:", e);
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addUsersOtherPermissions = (btn, status = 1) => {
    if (userPermissions == []) {
        showToast("warning", "Select new users' permissions and continue.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            usersPermissions: userPermissions,
        };
        fetch('api/addUsersOtherPermissions.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.json()) // Get the response as text
            .then((response) => {
                if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                } else if (response.done) {
                    toggleBtnLoader(btn, false);
                    showToast("success", response.done);
                    setTimeout(() => { window.history.back(); }, 3000);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
}

const filterUsersPermissions = () => {
    console.log(selectedPermissions);
    const data = {
        text: $("#search-text").val(),
        selectedPermissions: selectedPermissions,
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterUsersPermissions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterStudentsPermissions = () => {
    const data = {
        text: $("#search-text").val(),
        selectedPermissions: selectedPermissions,
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterStudentsPermissions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const updateUsersOtherPermission = (btn, userId, type) => {
    const selectValue = $("#user-permission-update-selector").val();
    const checkAll = $("#check-all").prop('checked');
    if (selectValue == 0) {
        showToast("warning", "Please select an option if you want to update the status.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            userId: userId,
            type: type,
            checkAll: checkAll,
            status: selectValue,
            selectedPermissions: selectedPermissions,
            unselectedPermissions: unSelectedPermissions
        }

        fetch('api/updateUsersOtherPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                } else if (response.done) {
                    toggleBtnLoader(btn, false);
                    showToast("success", response.done);
                    setTimeout(() => { window.location.reload(); }, 3000);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, true);
                showToast("error", "An error occured. Please try again later.");
            });
    }
}

const displayUserPermissions = (data) => {
    newPermissionUsers = newPermissionUsers.concat(selectedUsers);
    $('#search-text').val('');
    $('#search-text').removeClass('is-invalid');
    $('#search-text-feedback').text('');
    $('#users-table-container').html('');
    $('#addNewUserModal').modal('hide');
    addNewPermissionsUserCount += selectedUsers.length;
    selectedUsers = [];
    $('#empty-users-display-area').addClass('d-none');
    $('#user-detailss-display-area').append(data);
    $('#save-permission-btn-area').removeClass('d-none');

}

const downloadStudentsCSV = () => {
    const modal = new bootstrap.Modal($('#csvDownload')[0]);
    modal.show();

    const waitingMessage = $('#waitingMessage');
    const successMessage = $('#successMessage');

    waitingMessage.show();
    successMessage.hide();

    const data = {
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
    };

    fetch('api/downloadStudentsCSV.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => {
            console.log(response);
            const contentType = response.headers.get("content-type");
            if (contentType && contentType.includes("text/csv")) {
                ////console.log("text/csv");
                return response.blob();

            } else {
                ////console.log("erro/csv");
                return response.json();
            }
        })
        .then((data) => {
            ////console.log(data);
            if (data instanceof Blob) {
                const url = window.URL.createObjectURL(data);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'students.csv';
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);

                waitingMessage.hide();
                successMessage.show();
                ////console.log("223223")
            } else {
                ////console.log("2233")
                // Handle JSON error response
                if (data.error) {
                    showToast("error", data.error);
                } else {
                    showToast("error", "An unknown error occurred.");
                }
                modal.hide(); // Hide modal on error
            }
        })
        .catch((error) => {
            showToast("error", "An error occurred. Please try again later.");
            modal.hide(); // Hide modal on error
        });
};

const applySubjectHasLecturersUpdate = (id) => {
    const update = $("#lecturers-update-selector").val();
    if (update != 0) {
        const data = {
            subjectId: id,
            update: update,
            checkedLecturers: selectedLecturers,
            uncheckedLecturers: unSelectedLecturers
        }
        fetch('api/applySubjectHasLecturersUpdate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data),
        })
            .then(response => response.text())
            .then(data => {
                ////console.log(data);
                const response = JSON.parse(data);
                if (response.error) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else if (response.warning) {
                    showToast("warning", response.warning);
                } else if (response.done) {
                    setTimeout(() => { showToast("success", response.done); }, 1000);
                    filterSubjectHasLecturers(id);
                    setDefault();
                }
            })
            .catch((error) => {
                console.error('Error:', error);
                showToast("error", "Something went wrong");
            });
    } else {
        showToast("warning", "Please select the desired student status change option.");
    }
}
const filterSubjectHasLecturers = (id) => {
    console.log(sortCondition1);
    const data = {
        id: id,
        text: $("#lecturers-search-text").val(),
        sort: $("#lecturers-sort-selector").val(),
        sortByName: sortCondition1,
        sortByDate: sortCondition2,
        update: $("#lecturers-update-selector").val(),
        resultPerPage: $("#lecturers-show-result-selector").val(),
        page: selectedPage,
        checkedLecturers: selectedLecturers,
        uncheckedLecturers: unSelectedLecturers
    }

    fetch('api/filterSubjectHasLecturers.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#lecturers-table-container").html(data);

            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}
const addIntakeToRegisteredStudents = (studentId, intakeId) => {
    ////console.log(studentId, intakeId);
    const data = {
        studentId: studentId,
        intakeId: intakeId,
        intakeStudentId: $("#student-intake-id").val()
    }
    fetch('api/addIntakeToRegisteredStudents.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.done) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterRegisteredStudents(intakeId);
                $("#student-intake-id").val('');
                $("#studentAddIntake").modal('hide');

                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.error) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.sIId) {
                $("#student-intake-id").addClass("is-invalid");
                $("#student-intake-id-feedback").text(response.sIId);
            }

        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}
const filterRegisteredStudents = (intakeId) => {
    const data = {
        intakeId: intakeId,
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage
    }

    ////console.log(data);

    fetch('api/filterRegisteredStudents.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-table-container").html(data);

            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}
const uploadStudentBulkToIntake = (id) => {
    toggleLoader(true);
    const formData = new FormData();
    formData.append("intake_id", id);
    formData.append("file", $("#file-selector-input")[0].files[0]);
    fetch("api/uploadStudentBulkToIntake.php", {
        method: "POST",
        body: formData,
    })
        .then((response) => response.text())
        .then(async (data) => {
            toggleLoader(false);
            console.log(data);
            const response = JSON.parse(data);
            $("#studentBulkUpload").modal('hide');
            $("#csv-file-feedback").text('');
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
                $("#csv-file-feedback").text('');
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
                $("#csv-file-feedback").text('');
            } else if (response.hasOwnProperty('file')) {
                setTimeout(() => { showToast("error", response.file); }, 1000);
                $("#csv-file-feedback").text(response.file);
            } else {
                $("#csv-file-feedback").text('');
                setTimeout(() => { showToast("error", "An error occured.Please check error log and try again."); }, 1000);
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF();

                let y = 10;

                // Set font size and color for the error messages
                pdf.setFontSize(10);

                for (let i = 0; i < response.dataErrors.length; i++) {
                    ////console.log(response.dataErrors[i]);
                    for (let j = 0; j < response.dataErrors[i].length; j++) {
                        ////console.log(response.dataErrors[i][j]);

                        pdf.text(response.dataErrors[i][j], 8, y);

                        y += 10;
                    }
                }

                // Create a Blob and URL for the PDF
                const pdfOutput = pdf.output('blob');
                const pdfUrl = URL.createObjectURL(pdfOutput);

                // Open the PDF in a new window
                setTimeout(() => window.open(pdfUrl, '_blank'), 4000);

                // Optionally, you can revoke the object URL after some time to free memory
                setTimeout(() => URL.revokeObjectURL(pdfUrl), 5000);
            }
        })
        .catch((error) => {
            ////console.log(error);
            showToast("error", "An error occured. Please try again later.");
        });
}
const downloadIntakeStudentsCSV = (id) => {
    const modal = new bootstrap.Modal($('#csvDownload')[0]);
    modal.show();

    const waitingMessage = $('#waitingMessage');
    const successMessage = $('#successMessage');

    waitingMessage.show();
    successMessage.hide();

    const data = {
        intake_id: id,
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
    };

    fetch('api/downloadIntakeStudentsCSV.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => {
            console.log(response);
            const contentType = response.headers.get("content-type");

            // First extract the filename from response headers
            const contentDisposition = response.headers.get('Content-Disposition');
            let fileName = 'students.csv';

            if (contentDisposition) {
                const filenameMatch = contentDisposition.match(/filename="(.+)"/);
                if (filenameMatch && filenameMatch[1]) {
                    fileName = filenameMatch[1];
                }
            }

            if (contentType && contentType.includes("text/csv")) {
                ////console.log("text/csv");
                // Return both blob and filename
                return response.blob().then(blob => ({ blob, fileName }));
            } else {
                ////console.log("erro/csv");
                return response.json();
            }
        })
        .then((data) => {
            ////console.log(data);

            // Check if data contains blob (CSV case)
            if (data.blob && data.blob instanceof Blob) {
                const url = window.URL.createObjectURL(data.blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = data.fileName; // Use the extracted filename
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);

                waitingMessage.hide();
                successMessage.show();
            }
            // Handle JSON response (error case)
            else if (data.error) {
                showToast("error", data.error);
                modal.hide();
            } else {
                showToast("error", "An unknown error occurred.");
                modal.hide();
            }
        })
        .catch((error) => {
            ////console.log(error);
            showToast("error", "An error occurred. Please try again later.");
            modal.hide();
        });
}
const downloadIntakePaymentsReport = (btn, id, intake) => {
    toggleBtnLoader(btn, true);

    const data = {
        intake_id: id,
        text: $("#payment-search-text").val(),
        category: $("#payment-category-selector").val(),
        status: $("#payment-status-selector").val(),
        sort: $("#payment-sort-selector").val(),
    };

    fetch('api/downloadIntakePaymentsReport.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then(async (response) => {
            const contentType = response.headers.get("content-type");

            if (contentType && contentType.includes("application/vnd.openxmlformats-officedocument.spreadsheetml.sheet")) {
                return response.blob();
            }

            if (contentType && contentType.includes("application/json")) {
                return response.json();
            }

            const text = await response.text();
            console.log(text);
            throw new Error("Unexpected response type.");
        })
        .then((data) => {
            toggleBtnLoader(btn, false);

            if (data instanceof Blob) {
                const url = window.URL.createObjectURL(data);
                const a = document.createElement("a");

                a.href = url;
                a.download = `Payments Report - ${intake}.xlsx`;

                document.body.appendChild(a);
                a.click();
                a.remove();

                window.URL.revokeObjectURL(url);
            } else {
                showToast("error", data.error || "Unknown error");
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            console.error(error);
            showToast("error", error.message);
        });
}

const assignLecturerToLateSubmitStudentsAssignments = (id) => {
    const lecturer_id = $("#late-lecturer-selector").val();
    if (lecturer_id != 0) {
        toggleLoader(true);
        const data = {
            assignment_id: id,
            search_text: $("#late-search-text").val(),
            sort: $("#late-sort-selector").val(),
            resultPerPage: $("#late-result-per-page").val(),
            lecturer_id: lecturer_id,
            checkedStudents: selectedUsers,
            uncheckedStudents: unSelectedUsers,
            currentPage: selectedPage,
        }
        fetch('api/assignLecturerToLateSubmitStudentsAssignments.php', {
            method: "POST",
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
            .then((response) => response.text())
            .then((data) => {
                ////console.log(data);
                toggleLoader(false);
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                } else if (response.hasOwnProperty('done')) {
                    showToast("success", response.done);
                    filterLateSubmitStudentsAssignments(id);
                }
            })
            .catch((error) => {
                showToast("error", "An error occured. Please try again later.");
            });
    } else {
        showToast("warning", "Please select the students assignments assigned to the lecturer first.");
    }


}
const addFAQ = () => {
    toggleLoader(true);
    const data = {
        question: $("#question").val(),
        answer: faqAnswer.getHTMLCode(),
        video_link: $("#video_link").val()
    }
    fetch('api/addFAQ.php', {
        method: "POST",
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else if (response.hasOwnProperty('done')) {
                showToast("success", response.done);
                $("#addFAQ").modal("hide");
                setTimeout(() => { window.location.reload(); }, 3000);
            } else {
                showToast("error", "Something went wrong.Check data fields and try again");
                if (response.hasOwnProperty('question')) {
                    $("#question-feedback").text(response.question);
                    $("#question").addClass("is-invalid");
                }
                if (response.hasOwnProperty('answer')) {
                    $("#answer-feedback").text(response.answer);
                }
                if (response.hasOwnProperty('link')) {
                    $("#video-link-feedback").text(response.link);
                    $("#video_link").addClass("is-invalid");
                }
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const deleteFAQ = (id, status = 1) => {
    toggleLoader(true);
    const data = {
        faq: id
    }
    fetch('api/deleteFAQ.php', {
        method: "POST",
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const editFAQ = (id) => {
    toggleLoader(true);
    const data = {
        faq: id,
        question: $("#question").val(),
        answer: faqAnswer.getHTMLCode(),
        video_link: $("#video_link").val()
    }
    fetch('api/editFAQ.php', {
        method: "POST",
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else if (response.hasOwnProperty('done')) {
                showToast("success", response.done);
                $("#editFAQ").modal("hide");
            } else {
                showToast("error", "Something went wrong.Check data fields and try again");
                if (response.hasOwnProperty('question')) {
                    $("#question-feedback").text(response.question);
                    $("#question").addClass("is-invalid");
                }
                if (response.hasOwnProperty('answer')) {
                    $("#answer-feedback").text(response.answer);
                }
                if (response.hasOwnProperty('link')) {
                    $("#video-link-feedback").text(response.link);
                    $("#video_link").addClass("is-invalid");
                }
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const filterFAQ = () => {
    const data = {
        text: $("#user-search-text").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage
    }

    fetch('api/filterFAQ.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#faq-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const cancelAssignment = (id, subjectId) => {

    fetch('api/cancelAssignmnet.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ assignment: id }),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else if (response.hasOwnProperty('done')) {
                showToast("success", response.done);
                filterSubjectHasAssignments(subjectId);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const deleteAssignment = (id, subjectId) => {

    fetch('api/deleteAssignment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ assignment: id }),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else if (response.hasOwnProperty('done')) {
                showToast("success", response.done);
                filterSubjectHasAssignments(subjectId);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const assignLecturerToStudentsReCorrectionAssignments = (btn, id) => {
    const lecturer_id = $("#recorrection-lecturer-selector").val();
    if (lecturer_id != 0) {
        toggleBtnLoader(btn, true);
        const data = {
            assignment_id: id,
            search_text: $("#recorrection-search-text").val(),
            sort: $("#recorrection-sort-selector").val(),
            resultPerPage: $("#recorrection-result-per-page").val(),
            lecturer_id: lecturer_id,
            checkedStudents: selectedUsers,
            uncheckedStudents: unSelectedUsers,
            currentPage: selectedPage,
        }
        fetch('api/assignLecturerToStudentsReCorrectionAssignments.php', {
            method: "POST",
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
            .then((res) => res.json())
            .then((response) => {
                if (response.error) {
                    toggleBtnLoader(btn, false);
                    showToast("error", response.error);
                } else if (response.done) {
                    toggleBtnLoader(btn, false);
                    showToast("success", response.done);
                    filterRecorrectionRequestStudentsAssignments(id);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    } else {
        showToast("warning", "Please select the students assignments assigned to the lecturer first.");
    }
}

const filterRecorrectionRequestStudentsAssignments = (id) => {
    const data = {
        assignmentId: id,
        text: $("#recorrection-search-text").val(),
        filter: $("#recorrection-filter-selector").val(),
        sort: $("#recorrection-sort-selector").val(),
        sortByName: sortCondition1,
        sortByDate: sortCondition2,
        resultPerPage: $("#recorrection-show-result-selector").val(),
        assignLecturer: $("#recorrection-lecturer-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterRecorrectionRequestStudentsAssignments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-recorrection-assignments-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const updateAssignmentRecorrectionPaymentStatus = (paymentId) => {
    const data = {
        paymentId: paymentId,
        paymentStatus: $("#status-selector").val(),
        note: $("#note").val()
    }

    fetch('api/updateAssignmentRecorrectionPaymentStatus.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty('status')) {
                    $("#status-selector").addClass("is-invalid");
                    // Show a success toast.
                    $("#status-selector-feedback").text(response.status);
                    // Reload the page after 4 seconds.
                } else {
                    $("#status-selector").removeClass("is-invalid");
                    // Show an error toast.
                    $("#status-selector-feedback").text("");
                }

                if (response.hasOwnProperty('note')) {
                    $("#note").addClass("is-invalid");
                    $("#note-feedback").text(response.note);
                } else {
                    $("#note").removeClass("is-invalid");
                    $("#note-feedback").text("");
                }

            }
        })
}

const addRecorrectionAssignmentMark = (btn, id) => {
    toggleBtnLoader(btn, true);
    fetch('api/addRecorrectionAssignmentMark.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: id, mark: $("#mark").val() }),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                $("#addMark").modal('hide');
                $("#editMark").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterRecorrectionRequestStudentsAssignments(id);
            } else if (response.error) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("mark")) {
                    $("#mark").addClass("is-invalid");
                    $("#mark-feedback").text(response.mark);
                } else {
                    $("#mark").removeClass("is-invalid");
                    $("#mark-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}

const editRecorrectionAssignmentMark = (id, assignmentId) => {
    toggleLoader(true);
    fetch('api/addRecorrectionAssignmentMark.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: id, mark: $("#edit-mark").val() }),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#addMark").modal('hide');
                $("#editMark").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterRecorrectionRequestStudentsAssignments(assignmentId);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("mark")) {
                    $("#mark").addClass("is-invalid");
                    $("#mark-feedback").text(response.mark);
                } else {
                    $("#mark").removeClass("is-invalid");
                    $("#mark-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}


const addLecturerRecorrectionAssignmentMark = (id) => {
    toggleLoader(true);
    fetch('api/addRecorrectionAssignmentMark.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: id, mark: $("#mark").val() }),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#addMark").modal('hide');
                $("#editMark").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterLPRecorrectionRequestStudentsAssignments(id);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("mark")) {
                    $("#mark").addClass("is-invalid");
                    $("#mark-feedback").text(response.mark);
                } else {
                    $("#mark").removeClass("is-invalid");
                    $("#mark-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}
const editLecturerRecorrectionAssignmentMark = (id, assignmentId) => {
    toggleLoader(true);
    fetch('api/addRecorrectionAssignmentMark.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: id, mark: $("#edit-mark").val() }),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#addMark").modal('hide');
                $("#editMark").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterLPRecorrectionRequestStudentsAssignments(assignmentId);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("mark")) {
                    $("#mark").addClass("is-invalid");
                    $("#mark-feedback").text(response.mark);
                } else {
                    $("#mark").removeClass("is-invalid");
                    $("#mark-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}

const uploadRecorrectionAssignmentsMarksBulk = (btn, id) => {
    toggleBtnLoader(btn, true);
    const formData = new FormData();
    formData.append("assignment_id", id);
    formData.append("file", $("#file-selector-input")[0].files[0]);
    fetch("api/uploadRecorrectionAssignmentsMarksBulk.php", {
        method: "POST",
        body: formData,
    })
        .then((res) => res.json())
        .then(async (response) => {
            if (response.done) {
                toggleBtnLoader(btn, false);
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else if (response.file) {
                toggleBtnLoader(btn, false);
                showToast("error", response.done);
            } else {
                toggleBtnLoader(btn, false);
                setTimeout(() => { showToast("error", "An error occured.Please check error log and try again."); }, 1000);
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF();

                let y = 10;

                pdf.setFontSize(10);

                for (let i = 0; i < response.dataErrors.length; i++) {
                    for (let j = 0; j < response.dataErrors[i].length; j++) {
                        pdf.text(response.dataErrors[i][j], 8, y);

                        y += 10;
                    }
                }

                const pdfOutput = pdf.output('blob');
                const pdfUrl = URL.createObjectURL(pdfOutput);

                setTimeout(() => window.open(pdfUrl, '_blank'), 4000);

                setTimeout(() => URL.revokeObjectURL(pdfUrl), 5000);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const openMarksReleasedModal = (id) => {
    toggleLoader(true);
    const data = {
        assignmentId: id,
        text: $("#search-text").val(),
        filter: $("#filter-selector").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        assignLecturer: $("#lecturer-selector").val(),
    }

    fetch('api/getMarkReleasedStudentsDetails.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleLoader(false);
            if (response.error) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.modal) {
                $("#markReleasedModalBody").html(response.modal.body);
                $("#markReleasedModalFooter").html(response.modal.footer);
                $("#markReleasedModal").modal("show");
            }

        })
        .catch((error) => {
            toggleLoader(false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const releaseAssignmentMarks = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        assignmentId: id,
        text: $("#search-text").val(),
        filter: $("#filter-selector").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        assignLecturer: $("#lecturer-selector").val(),
    }

    fetch('api/releaseAssignmentMarks.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, false);
                $("#markReleasedModal").modal('hide');
                showToast("success", response.done);
                filterStudentsAssignments(id);

            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const openRecorrectionMarksReleasedModal = (id) => {
    toggleLoader(true);
    const data = {
        assignmentId: id,
        text: $("#recorrection-search-text").val(),
        sort: $("#recorrection-sort-selector").val(),
        resultPerPage: $("#recorrection-show-result-selector").val(),
        assignLecturer: $("#recorrection-lecturer-selector").val(),
        page: selectedPage,
    }

    fetch('api/getRecorrectionMarkReleasedStudentsDetails.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleLoader(false);
            if (response.error) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.modal) {
                $("#markReleasedModalBody").html(response.modal.body);
                $("#markReleasedModalFooter").html(response.modal.footer);
                $("#markReleasedModal").modal("show");
            }

        })
        .catch((error) => {
            toggleLoader(false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const releaseRecorrectionAssignmentMarks = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        assignmentId: id,
        text: $("#recorrection-search-text").val(),
        sort: $("#recorrection-sort-selector").val(),
        resultPerPage: $("#recorrection-show-result-selector").val(),
        assignLecturer: $("#recorrection-lecturer-selector").val(),
        page: selectedPage,
    }

    fetch('api/releaseRecorrectionAssignmentMarks.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, false);
                $("#markReleasedModal").modal('hide');
                showToast("success", response.done);
                filterStudentsAssignments(id);
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterLPRecorrectionRequestStudentsAssignments = (id) => {
    const data = {
        assignmentId: id,
        text: $("#recorrection-search-text").val(),
        sort: $("#recorrection-sort-selector").val(),
        filter: $("#recorrection-filter-selector").val(),
        resultPerPage: $("#recorrection-show-result-selector").val(),
        assignLecturer: $("#recorrection-lecturer-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterLPRecorrectionRequestStudentsAssignments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-recorrection-assignments-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterLPStudentsAssignments = (id) => {
    const data = {
        assignmentId: id,
        text: $("#search-text").val(),
        filter: $("#filter-selector").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterLPStudentsAssignments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-assignments-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addPhysicalExaminationMark = (studentExamId, examId) => {
    toggleLoader(true);
    fetch('api/addPhysicalExaminationMark.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: studentExamId, mark: $("#mark").val() }),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#addMark").modal('hide');
                $("#editMark").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterStudentsExaminations(examId);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("mark")) {
                    $("#mark").addClass("is-invalid");
                    $("#mark-feedback").text(response.mark);
                } else {
                    $("#mark").removeClass("is-invalid");
                    $("#mark-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}

const addOnlineExaminationMark = (studentExamId, examId) => {
    toggleLoader(true);
    fetch('api/addOnlineExaminationMark.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: studentExamId, mark: $("#mark").val() }),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#addMark").modal('hide');
                $("#editMark").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterStudentsExaminations(examId);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("mark")) {
                    $("#mark").addClass("is-invalid");
                    $("#mark-feedback").text(response.mark);
                } else {
                    $("#mark").removeClass("is-invalid");
                    $("#mark-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}

const editPhysicalExaminationMark = (studentExamId, examId) => {
    toggleLoader(true);
    fetch('api/addPhysicalExaminationMark.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: studentExamId, mark: $("#edit-mark").val() }),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#addMark").modal('hide');
                $("#editMark").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterStudentsExaminations(examId);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("mark")) {
                    $("#mark").addClass("is-invalid");
                    $("#mark-feedback").text(response.mark);
                } else {
                    $("#mark").removeClass("is-invalid");
                    $("#mark-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}

const editOnlineExaminationMark = (studentExamId, examId) => {
    toggleLoader(true);
    fetch('api/addOnlineExaminationMark.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id: studentExamId, mark: $("#edit-mark").val() }),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#addMark").modal('hide');
                $("#editMark").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterStudentsExaminations(examId);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty("mark")) {
                    $("#mark").addClass("is-invalid");
                    $("#mark-feedback").text(response.mark);
                } else {
                    $("#mark").removeClass("is-invalid");
                    $("#mark-feedback").text("");
                }
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        })
}

const openExamMarksReleasedModal = (id) => {
    const data = {
        examId: id,
        filter: $("#filter-selector").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
    }

    fetch('api/getExamMarkReleasedStudentsDetails.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('modal')) {
                $("#markReleasedModalBody").html(response.modal.body);
                $("#markReleasedModalFooter").html(response.modal.footer);
                $("#markReleasedModal").modal("show");
            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const releaseExamMarks = (id) => {
    const data = {
        examId: id,
        text: $("#search-text").val(),
        filter: $("#filter-selector").val(),
        resultPerPage: $("#show-result-selector").val(),

    }

    fetch('api/releaseExamMarks.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#markReleasedModal").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterStudentsExaminations(id);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const uploadExamMarksBulk = (id) => {
    ////console.log(id);
    toggleLoader(true);
    const formData = new FormData();
    formData.append("exam_id", id);
    formData.append("file", $("#file-selector-input")[0].files[0]);
    fetch("api/uploadExamMarksBulk.php", {
        method: "POST",
        body: formData,
    })
        .then((response) => response.text())
        .then(async (data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterStudentsExaminations(id);
                $("#file-selector-input").val('');
                $("#marksBulkUpload").modal('hide');
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('file')) {
                setTimeout(() => { showToast("error", response.done); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "An error occured.Please check error log and try again."); }, 1000);
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF();

                let y = 10;

                // Set font size and color for the error messages
                pdf.setFontSize(10);

                for (let i = 0; i < response.dataErrors.length; i++) {
                    ////console.log(response.dataErrors[i]);
                    for (let j = 0; j < response.dataErrors[i].length; j++) {
                        ////console.log(response.dataErrors[i][j]);

                        pdf.text(response.dataErrors[i][j], 8, y);

                        y += 10;
                    }
                }

                // Create a Blob and URL for the PDF
                const pdfOutput = pdf.output('blob');
                const pdfUrl = URL.createObjectURL(pdfOutput);

                // Open the PDF in a new window
                setTimeout(() => window.open(pdfUrl, '_blank'), 4000);

                // Optionally, you can revoke the object URL after some time to free memory
                setTimeout(() => URL.revokeObjectURL(pdfUrl), 5000);
            }
        })
        .catch((error) => {
            ////console.log(error);
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterStudentsExaminations = (id) => {
    const data = {
        examId: id,
        text: $("#search-text").val(),
        filter: $("#filter-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterStudentsExaminations.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-examination-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterStudentsQuizzes = (id) => {
    const data = {
        quizId: id,
        text: $("#search-text").val(),
        filter: $("#filter-selector").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterStudentsQuizzes.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-quiz-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const openQuizMarksReleasedModal = (id) => {
    const data = {
        examId: id,
        text: $("#search-text").val(),
        filter: $("#filter-selector").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
    }

    fetch('api/getQuizMarkReleasedStudentsDetails.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('modal')) {
                $("#markReleasedModalBody").html(response.modal.body);
                $("#markReleasedModalFooter").html(response.modal.footer);
                $("#markReleasedModal").modal("show");
            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const releaseQuizMarks = (id) => {
    const data = {
        examId: id,
        text: $("#search-text").val(),
        filter: $("#filter-selector").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
    }

    fetch('api/releaseQuizMarks.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#markReleasedModal").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterStudentsQuizzes(id);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const branchPermissionsSearchUsers = (id) => {
    const searchText = $('#search-text').val();
    const data = {
        branch_id: id,
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/branchPermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    showToast("error", "An error occured. Please try again later.");
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const loadAvailableUserBranchPermission = (btn, id) => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            branch_id: id,
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserBranchPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.text())
            .then((text) => {
                let result;
                try {
                    result = JSON.parse(text);
                } catch {
                    result = text;
                }

                if (typeof result === "object" && result.error) {
                    if (response.error) {
                        showToast("error", response.error);
                    } else {
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                    }
                    toggleBtnLoader(btn, false);
                } else {
                    displayUserPermissions(result);
                    toggleBtnLoader(btn, false);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
}

const filterFacultiesPermissions = () => {
    const data = {
        text: $("#search-text").val(),
        selectedPermissions: selectedPermissions,
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterFacultiesPermissions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const removeRequestStudentPayment = (id) => {
    fetch('api/removeRequestStudentPayment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ paymentId: id }),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const updateStudentPayment = (btn, id) => {
    toggleBtnLoader(btn, true);
    console.log(getInstallmentPenalty());
    const data = {
        paymentId: id,
        paymentType: $("#payment-type").val(),
        paymentAmount: $("#payment-amount").val(),
        discountType: $("#discount-type").val(),
        discountAmount: $("#discount").val(),
        discountNote: $("#discount-note").val(),
        paymentNote: $("#payment-note").val(),
        dueDate: $("#due-date").val(),
        enablePenalty: $("#enable-panelty").prop('checked'),
        penalty: getInstallmentPenalty()
    }

    const validationMap = {
        paymentType: ["#payment-type", "#payment-type-feedback"],
        paymentAmount: ["#payment-amount", "#payment-amount-feedback"],
        discountType: ["#discount-type", "#discount-type-feedback"],
        discountAmount: ["#discount", "#discount-feedback"],
        paymentNote: ["#payment-note", "#payment-note-feedback"],
        dueDate: ["#due-date", "#due-date-feedback"],
        enablePenalty: ["#enable-panelty", "#enable-panelty-feedback"],
        penaltyOption: ["#panelty-option", "#panelty-option-feedback"],
    };

    fetch('api/updateStudentPayment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            if (response.done) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.error) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.warning) {
                setTimeout(() => { showToast("warning", response.warning); }, 1000);
            } else {
                applyValidation(response, validationMap);
            }
        })
}

const loadAvailableUserPaymentsPermission = () => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        const data = {
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserPaymentsPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((response) => response.text())
            .then((data) => {
                try {
                    const response = JSON.parse(data);
                    if (response.error) {
                        showToast("error", response.error);
                    } else {
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                    }
                } catch (e) {
                    displayUserPermissions(data);
                }
            })
            .catch((error) => {
                showToast("error", "An error occured. Please try again later.");
                showToast("error", "Failed to add user. Please try again.");
            });
    }
}

const paymentsPermissionsSearchUsers = () => {
    const searchText = $('#search-text').val();
    const data = {
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/paymentsPermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log("Data from server:", data);
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    ////console.log("JSON Response:", jsonResponse);

                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    ////console.log("Error parsing JSON:", e);
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterPaymentsPermissions = () => {
    const data = {
        text: $("#search-text").val(),
        selectedPermissions: selectedPermissions,
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterPaymentsPermissions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const lectureSchedulePermissionsSearchUsers = () => {
    const searchText = $('#search-text').val();
    const data = {
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/lectureSchedulePermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log("Data from server:", data);
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    ////console.log("JSON Response:", jsonResponse);

                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    ////console.log("Error parsing JSON:", e);
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const loadAvailableUserLectureSchedulePermission = () => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        const data = {
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserLectureSchedulePermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((response) => response.text())
            .then((data) => {
                try {
                    const response = JSON.parse(data);
                    if (response.error) {
                        showToast("error", response.error);
                    } else {
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                    }
                } catch (e) {
                    displayUserPermissions(data);
                }
            })
            .catch((error) => {
                showToast("error", "An error occured. Please try again later.");
                showToast("error", "Failed to add user. Please try again.");
            });
    }
}

const lectureRecordingsPermissionsSearchUsers = () => {
    const searchText = $('#search-text').val();
    const data = {
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/lectureRecordingsPermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log("Data from server:", data);
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    ////console.log("JSON Response:", jsonResponse);

                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    ////console.log("Error parsing JSON:", e);
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const loadAvailableUserLectureRecordingPermission = () => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        const data = {
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserLectureRecordingPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((response) => response.text())
            .then((data) => {
                try {
                    const response = JSON.parse(data);
                    if (response.error) {
                        showToast("error", response.error);
                    } else {
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                    }
                } catch (e) {
                    displayUserPermissions(data);
                }
            })
            .catch((error) => {
                showToast("error", "An error occured. Please try again later.");
                showToast("error", "Failed to add user. Please try again.");
            });
    }
}

const filterPortalPayments = () => {
    const data = {
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
        selectStudents: selectedUsers,
        unSelectStudents: unSelectedUsers,
    }

    fetch('api/filterPortalPayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-table-container").html(data);

            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const addPortalPayment = (studentId) => {
    const data = {
        studentId: studentId
    }

    fetch('api/addPortalPayment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterPortalPaymentAddStudents();
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('warning')) {
                setTimeout(() => { showToast("warning", response.warning); }, 1000);
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const updateStudentPortalPaymentStatus = (paymentId, status) => {
    const data = {
        paymentId: paymentId,
        status: status
    }

    fetch('api/updateStudentPortalPaymentStatus.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterStudentsPortalPayments();
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('warning')) {
                setTimeout(() => { showToast("warning", response.warning); }, 1000);
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const filterStudentsPortalPayments = () => {
    const data = {
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        update: $("#update-selector").val(),
        page: selectedPage,
        selectPayment: selectedUsers,
        unSelectPayment: unSelectedUsers,
    }

    ////console.log($("#update-selector").val())
    ////console.log(selectedUsers);

    fetch('api/filterStudentsPortalPayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {

            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-table-container").html(data);

            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const updateStudentsPortalPaymentBulk = () => {
    const data = {
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        update: $("#update-selector").val(),
        currentPage: selectedPage,
        page: selectedPage,
        selectPayments: selectedUsers,
        unSelectPayments: unSelectedUsers,
    }
    ////console.log($("#update-selector").val());
    fetch('api/updateStudentsPortalPaymentBulk.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                selectedUsers = [];
                unSelectedUsers = [];
                filterStudentsPortalPayments();

            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('warning')) {
                setTimeout(() => { showToast("warning", response.warning); }, 1000);
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const uploadStudentPortalPaymentBulk = () => {
    const formData = new FormData();
    formData.append("file", $("#file-selector-input")[0].files[0]);
    fetch("api/uploadStudentPortalPaymentBulk.php", {
        method: "POST",
        body: formData,
    })
        .then((response) => response.text())
        .then(async (data) => {
            toggleLoader(false);
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterPortalPaymentAddStudents();
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('file')) {
                setTimeout(() => { showToast("error", response.done); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "An error occured.Please check error log and try again."); }, 1000);
                const { jsPDF } = window.jspdf;
                const pdf = new jsPDF();

                let y = 10;

                // Set font size and color for the error messages
                pdf.setFontSize(10);

                for (let i = 0; i < response.dataErrors.length; i++) {
                    ////console.log(response.dataErrors[i]);
                    for (let j = 0; j < response.dataErrors[i].length; j++) {
                        ////console.log(response.dataErrors[i][j]);

                        pdf.text(response.dataErrors[i][j], 8, y);

                        y += 10;
                    }
                }

                // Create a Blob and URL for the PDF
                const pdfOutput = pdf.output('blob');
                const pdfUrl = URL.createObjectURL(pdfOutput);

                // Open the PDF in a new window
                setTimeout(() => window.open(pdfUrl, '_blank'), 4000);

                // Optionally, you can revoke the object URL after some time to free memory
                setTimeout(() => URL.revokeObjectURL(pdfUrl), 5000);
            }
        })
        .catch((error) => {
            ////console.log(error);
            showToast("error", "An error occured. Please try again later.");
        });
}

const markAttendance = (id, lectureId, status) => {
    const data = {
        studentId: id,
        lectureId: lectureId,
        attendStatus: status
    }

    ////console.log(data);

    fetch('api/markAttendance.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterStudentsOngoingLectureAttendance(lectureId);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('warning')) {
                setTimeout(() => { showToast("warning", response.warning); }, 1000);
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const updateAttendance = (id, lectureId) => {
    const data = {
        attendanceId: id
    }

    ////console.log(data);

    fetch('api/updateAttendance.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterStudentsOngoingLectureAttendance(lectureId);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('warning')) {
                setTimeout(() => { showToast("warning", response.warning); }, 1000);
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const filterStudentsOngoingLectureAttendance = (lectureId) => {
    const data = {
        lecture_id: lectureId,
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    ////console.log($("#update-selector").val())
    ////console.log(selectedUsers);

    fetch('api/filterStudentsOngoingLectureAttendance.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {

            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-table-container").html(data);

            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}


const editIntakeStudent = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("id", id);
    form.append("siid", $("#siid").val());
    form.append("fname", $("#first-name").val());
    form.append("lname", $("#last-name").val());
    form.append("fullname", $("#full-name").val());
    form.append("nwi", $("#name-with-initials").val());
    form.append("title", $("#title-selector").val());
    form.append("gender", $("#gender-selector").val());
    form.append("marital_status", $("#marital-status-selector").val());
    form.append("nationality", $("#nationality").val());
    form.append("nic", $("#nic").val());
    form.append("passport", $("#passport").val());
    form.append("cob", $("#country-of-birth").val());
    form.append("cor", $("#country-of-residence").val());
    form.append("dob", $("#dob").val());
    form.append("email", $("#email").val());
    form.append("mobile", $("#mobile").val());
    form.append("whatsapp", $("#whatsapp").val());
    form.append("address", $("#address").val());
    form.append("address2", $("#address2").val());
    form.append("city", $("#city").val());
    form.append("pcode", $("#postal-code").val());
    form.append("image", $("#file-input").prop("files")[0]);
    form.append("grelationship", $("#grelationship-selector").val());
    form.append("gtitle", $("#gtitle-selector").val());
    form.append("gfname", $("#guardian-fname").val());
    form.append("glname", $("#guardian-lname").val());
    form.append("gemail", $("#guardian-email").val());
    form.append("gmobile", $("#guardian-mobile").val());
    form.append("gwhatsapp", $("#guardian-whatsapp").val());
    form.append("removed_documents", removedExistingDocuments);
    appendStudentDocumentsToFormData(form);

    const validationMap = {
        id: ["#student-intake-id", "#student-intake-id-feedback"],
        fname: ["#first-name", "#fname-feedback"],
        lname: ["#last-name", "#lname-feedback"],
        fullname: ["#full-name", "#fullname-feedback"],
        nwi: ["#name-with-initials", "#nwi-feedback"],
        title: ["#title-selector", "#title-feedback"],
        gender: ["#gender-selector", "#gender-feedback"],
        marital_status: ["#marital-status-selector", "#marital-feedback"],
        nationality: ["#nationality", "#nationality-feedback"],
        cob: ["#country-of-birth", "#cob-feedback"],
        cor: ["#country-of-residence", "#cor-feedback"],
        nic: ["#nic", "#nic-feedback"],
        passport: ["#passport", "#passport-feedback"],
        dob: ["#dob", "#dob-feedback"],
        email: ["#email", "#email-feedback"],
        mobile: ["#mobile", "#mobile-feedback"],
        whatsapp: ["#whatsapp", "#whatsapp-feedback"],
        address: ["#address", "#address-feedback"],
        address2: ["#address2", "#address2-feedback"],
        city: ["#city", "#city-feedback"],
        pcode: ["#postal-code", "#postal-code-feedback"],
        faculty: ["#faculty-selector", "#faculty-feedback"],
        programme: ["#programme-selector", "#programme-feedback"],
        intake: ["#intake-selector", "#intake-feedback"],
        grelationship: ["#grelationship-selector", "#grelationship-feedback"],
        gtitle: ["#gtitle-selector", "#gtitle-feedback"],
        gfname: ["#guardian-fname", "#gfname-feedback"],
        glname: ["#guardian-lname", "#glname-feedback"],
        gemail: ["#guardian-email", "#gemail-feedback"],
        gmobile: ["#guardian-mobile", "#gmobile-feedback"],
        gwhatsapp: ["#guardian-whatsapp", "#gwhatsapp-feedback"],
        aic_application: ["#doc-aic-application", "#doc-aic-application-feedback"],
        university_application: ["#doc-university-application", "#doc-university-application-feedback"],
        signed_agreement: ["#doc-signed-agreement", "#doc-signed-agreement-feedback"],
        signed_program_brief: ["#doc-signed-program-brief", "#doc-signed-program-brief-feedback"],
        passport_nic: ["#doc-passport-nic", "#doc-passport-nic-feedback"],
        academic_documents: ["#doc-academic-documents", "#doc-academic-documents-feedback"],
        other_entry_documents: ["#doc-other-entry-documents", "#doc-other-entry-documents-feedback"],
        payment_slip: ["#doc-payment-slip", "#doc-payment-slip-feedback"],
        manager_checklist: ["#doc-manager-checklist", "#doc-manager-checklist-feedback"],
        discount_approved_document: ["#doc-discount-approved-document", "#doc-discount-approved-document-feedback"],
        payment_option: ["#payment-option", "#payment-option-feedback"]
    };

    fetch("api/editIntakeStudent.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload() }, 4000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterSubjectPreRecordedLectures = (id) => {
    ////console.log($("#show-result-selector").val());
    const data = {
        subject: id,
        lecturer: $("#lecturer-selector").val(),
        filter: $("#filter-selector").val(),
        page: selectedPage,
        result_per_page: $("#show-result-selector").val()
    }

    fetch('api/filterSubjectPreRecordedLectures.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                } else {
                    showToast("success", response.success);
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                    setTimeout(() => { window.location.reload() }, 4000);
                }
            } catch (error) {
                $("#schedule-table-container").html(data);

            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterSubjectRecordings = (id) => {
    ////console.log($("#show-result-selector").val());
    const data = {
        subject: id,
        lecturer: $("#lecturer-selector").val(),
        from_date: $("#from-date").val(),
        to_date: $("#to-date").val(),
        from_time: $("#from-time").val(),
        to_time: $("#to-time").val(),
        lec_type: $("#type-selector").val(),
        filter: $("#filter-selector").val(),
        page: selectedPage,
        result_per_page: $("#show-result-selector").val()
    }

    fetch('api/filterSubjectRecordings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                } else {
                    showToast("success", response.success);
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                    setTimeout(() => { window.location.reload() }, 4000);
                }
            } catch (error) {
                $("#schedule-table-container").html(data);

            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const applySelectedSubjectStudentsUpdate = (subject, update) => {
    if (update != 0) {
        const data = {
            subjectId: subject,
            update: update,
            selectStudents: selectedUsers,
            unSelectStudents: unSelectedUsers
        }
        fetch('api/updateSubjectStudents.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data),
        })
            .then(response => response.text())
            .then(data => {
                ////console.log(data);
                const response = JSON.parse(data);
                if (response.error) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else if (response.warning) {
                    showToast("warning", response.warning);
                } else {
                    setTimeout(() => { showToast("success", response.done); }, 1000);
                    filterSubjectStudents(subject);
                    setDefault();
                }
            })
            .catch((error) => {
                console.error('Error:', error);
                showToast("error", "Something went wrong");
            });
    } else {
        showToast("warning", "Please select the desired student status change option.");
    }
}

const addLecturerLecturePayment = (btn, lectureId) => {
    toggleBtnLoader(btn, true);
    const data = {
        lectureId: lectureId,
        startTime: $('#start-time').val(),
        endTime: $('#end-time').val(),

    }
    fetch('api/addLecturerLecturePayment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data),
    })
        .then(res => res.json())
        .then(response => {
            if (response.done) {
                toggleBtnLoader(btn, false);
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.hasOwnProperty('error')) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                const validationMap = {
                    start: ["#start-time", "#start-time-feedback"],
                    end: ["#end-time", "#end-time-feedback"],
                };

                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const updateLecturerLecturePayment = (id, status) => {
    toggleLoader(true)
    const data = {
        lecturerPayemntId: id,
        paymentStatus: status
    }
    fetch('api/updateLecturerLecturePayment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data),
    })
        .then(response => response.text())
        .then(data => {
            ////console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);

            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            }
        })
        .catch((error) => {
            console.error('Error:', error);
            showToast("error", "Something went wrong");
        });
}

const addLecturerAssignmentPayment = (lecturerId, assignemntId) => {
    toggleLoader(true)
    const data = {
        lecturerId: lecturerId,
        assignmentId: assignemntId
    }
    ////console.log(lecturerId, assignemntId);
    fetch('api/addLecturerAssignmentPayment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data),
    })
        .then(response => response.text())
        .then(data => {
            ////console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);

            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            }
        })
        .catch((error) => {
            console.error('Error:', error);
            showToast("error", "Something went wrong");
        });
}

const updateLecturerAssignmentPayment = (id, status) => {
    toggleLoader(true)
    const data = {
        lecturerPayemntId: id,
        paymentStatus: status
    }
    fetch('api/updateLecturerAssignmentPayment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data),
    })
        .then(response => response.text())
        .then(data => {
            ////console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);

            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            }
        })
        .catch((error) => {
            console.error('Error:', error);
            showToast("error", "Something went wrong");
        });
}

const payoutLecturerPayments = () => {
    let payoutsPayment = [];
    collectedItems.forEach(item => {
        payoutsPayment.push({
            lecturerId: item.lecturersId,
            amount: item.amount,
            count: item.count,
            type: item.type
        });
    });

    ////console.log(payoutsPayment);

    const data = {
        payoutsPayment: payoutsPayment
    }
    fetch('api/payoutLecturerPayments.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data),
    })
        .then(response => response.text())
        .then(data => {
            ////console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);

            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            }
        })
        .catch((error) => {
            console.error('Error:', error);
            showToast("error", "Something went wrong");
        });

}

const updatelecturerPayoutRequestStatus = (id) => {
    const data = {
        payoutId: id,
        payoutStatus: $("#status-selector").val(),
        note: $("#note").val()
    }

    fetch('api/updateLecturerPayoutRequestStatus.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty('status')) {
                    $("#status-selector").addClass("is-invalid");
                    $("#status-selector-feedback").text(response.status);
                } else {
                    $("#status-selector").removeClass("is-invalid");
                    $("#status-selector-feedback").text("");
                }

                if (response.hasOwnProperty('note')) {
                    $("#note").addClass("is-invalid");
                    $("#note-feedback").text(response.note);
                } else {
                    $("#note").removeClass("is-invalid");
                    $("#note-feedback").text("");
                }

            }
        })
}

const setupUserPassword = (token, status) => {
    const data = {
        token: token,
        status: status,
        password: $("#password").val()
    }

    fetch('api/setupUserPassword.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {

            const response = JSON.parse(data);
            $("#codeError").html('');
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location = "https://dashboard.aiccampuslms.com/"; }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                if (response.hasOwnProperty("code")) {
                    $("#codeError").html(response.code);
                }
            }
        })
}

const forgottenPassword = () => {
    toggleLoader(true);
    const data = {
        email: $("#email").val()
    }

    fetch('api/forgottenPassword.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('email')) {
                if (response.hasOwnProperty('email')) {
                    $("#email").addClass("is-invalid");
                    $("#email-feedback").html(response.email);
                } else {
                    $("#email").removeClass("is-invalid");
                    $("#email-feedback").html("");
                }
            }
        })
        .catch((error) => {
            console.error('Error:', error);
            showToast("error", "Something went wrong");
        });
}
const approveQuizResubmitRequest = (request_id, quizId) => {
    fetch("api/approveQuizResubmitRequest.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({ request_id })
    })
        .then(res => res.json())
        .then(response => {
            if (response.error) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.warning) {
                showToast("warning", response.warning);
            } else if (response.done) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                filterStudentsQuizResetRequests(quizId);
            }
        }).catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });

}

const filterStudentsQuizResetRequests = (id) => {
    const data = {
        quizId: id,
        text: $("#search-text")?.val(),
        filter: $("#filter-selector")?.val(),
        sort: $("#sort-selector")?.val(),
        resultPerPage: $("#show-result-selector")?.val(),
        page: selectedPage,
    }

    fetch('api/filterStudentsQuizResetRequests.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-quiz-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}


const removeLectureAttachment = (id) => {
    const data = {
        id: id,
    }
    fetch('api/removeLectureAttachment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                showToast("success", response.done);
                const attachmentDiv = document.querySelector(`[data-attachment-id="${id}"]`);
                if (attachmentDiv) {
                    attachmentDiv.style.transition = "opacity 0.5s, transform 0.5s";
                    attachmentDiv.style.opacity = 0;
                    attachmentDiv.style.transform = "scale(0.95)";
                    setTimeout(() => attachmentDiv.remove(), 500);
                }
            } else if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const editStudentPaymentPlan = (planId) => {
    toggleLoader(true);
    const data = {
        planId: planId,
        paymentCategoryId: $("#payment-category")?.val(),
        paymentPlanId: $("#payment-plan")?.val(),
        paymentAmount: $("#payment-amount")?.val(),
        discountType: $("#discount-type")?.val(),
        discountAmount: $("#discount")?.val(),
        paymentPeriod: $("#period")?.val(),
        discountNote: $("#discount-note")?.val(),
    }

    fetch('api/editStudentPaymentPlan.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                if (response.hasOwnProperty('paymentCategory')) {
                    $("#payment-category").addClass("is-invalid");
                    $("#payment-category-feedback").text(response.paymentCategory);
                } else {
                    $("#payment-category").removeClass("is-invalid");
                    $("#payment-category-feedback").text("");
                }

                if (response.hasOwnProperty('paymentPlan')) {
                    $("#payment-plan").addClass("is-invalid");
                    $("#payment-plan-feedback").text(response.paymentPlan);
                } else {
                    $("#payment-plan").removeClass("is-invalid");
                    $("#payment-plan-feedback").text("");
                }

                if (response.hasOwnProperty('paymentAmount')) {
                    $("#payment-amount").addClass("is-invalid");
                    $("#payment-amount-feedback").text(response.paymentAmount);
                } else {
                    $("#payment-amount").removeClass("is-invalid");
                    $("#payment-amount-feedback").text("");
                }

                if (response.hasOwnProperty('discountType')) {
                    $("#discount-type").addClass("is-invalid");
                    $("#discount-type-feedback").text(response.discountType);
                } else {
                    $("#discount-type").removeClass("is-invalid");
                    $("#discount-type-feedback").text("");
                }

                if (response.hasOwnProperty('discountAmount')) {
                    $("#discount").addClass("is-invalid");
                    $("#discount-feedback").text(response.discountAmount);
                } else {
                    $("#discount").removeClass("is-invalid");
                    $("#discount-feedback").text("");
                }
            }
        })
}

const filterSubjectLectureAttendance = (lectureId) => {
    const data = {
        lecture_id: lectureId,
        text: $("#search-text")?.val(),
        sort: $("#sort-selector")?.val(),
        sortByName: sortCondition1,
        sortById: sortCondition2,
        resultPerPage: $("#show-result-selector")?.val(),
        page: selectedPage,
    }

    fetch('api/filterSubjectLectureAttendance.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#attendance-table-container").html(data);
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}


const updateInquiryStatus = (id) => {
    toggleLoader(true);
    const data = {
        inquiryId: id,
        inquiryStatus: $("#status-selector").val(),
        note: $("#note").val()
    }

    fetch('api/updateInquiryStatus.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#updateInquiryStatus").modal("hide");
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty('status')) {
                    $("#status-selector").addClass("is-invalid");
                    $("#status-selector-feedback").text(response.status);
                } else {
                    $("#status-selector").removeClass("is-invalid");
                    $("#status-selector-feedback").text("");
                }

                if (response.hasOwnProperty('note')) {
                    $("#note").addClass("is-invalid");
                    $("#note-feedback").text(response.note);
                } else {
                    $("#note").removeClass("is-invalid");
                    $("#note-feedback").text("");
                }

            }
        })
}

const filterInquiries = () => {

    const data = {
        text: $("#user-search-text").val(),
        category: $("#category-selector").val(),
        status: $("#status-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage
    }

    console.log(data);

    fetch(`api/filterInquiries.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#inquiries-table-container").html(data);
            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });

}

const assignUserToInquiry = (id) => {
    toggleLoader(true);
    const data = {
        inquiry: id,
        user: $("#userHidden").val(),
    }

    fetch('api/assignUserToInquiry.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                $("#assignUser").modal("hide");
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty('user')) {
                    $("#user-feedback").text(response.user);
                } else {
                    $("#user-feedback").text("");
                }

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterLectureRecordings = () => {
    const data = {
        faculty: $("#faculty-selector").val(),
        programme: $("#programme-selector").val(),
        intake: $("#intake-selector").val(),
        level: $("#level-selector").val(),
        subject: $("#subject-selector").val(),
        lecturer: $("#lecturer-selector").val(),
        lec_type: $("#type-selector").val(),
        page: selectedPage,
        result_per_page: $("#show-result-selector").val()
    }

    fetch('api/filterLectureRecordings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                } else {
                    showToast("success", response.success);
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                    setTimeout(() => { window.history.back() }, 4000);
                }
            } catch (error) {
                $("#schedule-table-container").html(data);

            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterReCorrectionAssignmentLecturersPayments = (id) => {
    const data = {
        id: id,
        text: $("#user-search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterReCorrectionAssignmentLecturersPayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);
                getTableWidth('#users-table', '#pagination-container');
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterLecturersPayments = () => {
    const data = {
        text: $("#user-search-text").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterLecturersPayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);
                getTableWidth('#users-table', '#pagination-container');
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterAssignmentLecturersPayments = (id) => {
    const data = {
        id: id,
        text: $("#user-search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterAssignmentLecturersPayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);
                getTableWidth('#users-table', '#pagination-container');
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterLecturersAssignmentsPayments = () => {
    const data = {
        text: $("#user-search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterLecturersAssignmentsPayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);
                getTableWidth('#users-table', '#pagination-container');
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterLecturersLecturePayments = () => {
    const data = {
        text: $("#user-search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterLecturersLecturePayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);
                getTableWidth('#users-table', '#pagination-container');
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterLecturersPayoutRequest = () => {
    const data = {
        text: $("#user-search-text").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterLecturersPayoutRequest.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);
                getTableWidth('#users-table', '#pagination-container');
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterPortalSubscribedStudents = () => {
    const data = {
        text: $("#search-text").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterPortalSubscribedStudents.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-table-container").html(data);
                getTableWidth('#students-table', '#pagination-container');
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const filterLectureSchedulePermissions = () => {
    console.log(selectedPermissions);
    const data = {
        text: $("#search-text").val(),
        selectedPermissions: selectedPermissions,
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterLectureSchedulePermissions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const getLiveSearchResults = () => {
    const searchText = $("#live-search-input").val();
    fetch(`api/getLiveSearchResults.php?text=${searchText}`)
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                } else {
                    renderSearchHistory();
                }
            } catch (e) {
                $("#live-search-results").html(data);
                initializeLanguage();
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addPaneltyPayment = (paymentId) => {
    toggleLoader(true);
    const data = {
        paymentId: paymentId,
        paymentAmount: $("#payment-amount").val(),
        paymentNote: $("#payment-note").val(),
        cardPayment: $("#card-payament-enable").prop('checked'),
    }

    fetch('api/addPaneltyPayment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('warning')) {
                setTimeout(() => { showToast("warning", response.warning); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);

                if (response.hasOwnProperty('paymentAmount')) {
                    $("#payment-amount").addClass("is-invalid");
                    $("#payment-amount-feedback").text(response.paymentAmount);
                } else {
                    $("#payment-amount").removeClass("is-invalid");
                    $("#payment-amount-feedback").text("");
                }

                if (response.hasOwnProperty('paymentNote')) {
                    $("#payment-note").addClass("is-invalid");
                    $("#payment-note-feedback").text(response.paymentNote);
                } else {
                    $("#payment-note").removeClass("is-invalid");
                    $("#payment-note-feedback").text("");
                }
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const editPaneltyPayment = (paneltyId) => {

    toggleLoader(true);
    const data = {
        paneltyId: paneltyId,
        paymentAmount: $("#payment-amount").val(),
        paymentNote: $("#payment-note").val(),
        cardPayment: $("#card-payament-enable").prop('checked'),
    }

    fetch('api/editPaneltyPayment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('warning')) {
                setTimeout(() => { showToast("warning", response.warning); }, 1000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);

                if (response.hasOwnProperty('paymentAmount')) {
                    $("#payment-amount").addClass("is-invalid");
                    $("#payment-amount-feedback").text(response.paymentAmount);
                } else {
                    $("#payment-amount").removeClass("is-invalid");
                    $("#payment-amount-feedback").text("");
                }

                if (response.hasOwnProperty('paymentNote')) {
                    $("#payment-note").addClass("is-invalid");
                    $("#payment-note-feedback").text(response.paymentNote);
                } else {
                    $("#payment-note").removeClass("is-invalid");
                    $("#payment-note-feedback").text("");
                }
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const updatePortalPaymentStatus = (paymentId) => {
    toggleLoader(true);
    const data = {
        paymentId: paymentId,
        paymentStatus: $("#status-selector").val(),
        note: $("#note").val()
    }

    fetch('api/updatePortalPaymentStatus.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                $("#updatePaymentStatus").modal("hide");
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty('status')) {
                    $("#status-selector").addClass("is-invalid");
                    $("#status-selector-feedback").text(response.status);
                } else {
                    $("#status-selector").removeClass("is-invalid");
                    $("#status-selector-feedback").text("");
                }

                if (response.hasOwnProperty('note')) {
                    $("#note").addClass("is-invalid");
                    $("#note-feedback").text(response.note);
                } else {
                    $("#note").removeClass("is-invalid");
                    $("#note-feedback").text("");
                }
            }
        })
        .catch((error) => {
            showToast("error", "An error occurred. Please try again.");
        });
}

const addGradeCalculationStructure = (btn, subject) => {
    toggleBtnLoader(btn, true);
    const gradeType = $("#grade-type").val();
    let gradeTypeData = [];

    if (gradeType == 2) {
        gradeTypeData = getCategoryGradeValues();
    } else if (gradeType == 4) {
        gradeTypeData = getRubricData();
    }

    const data = {
        subject: subject,
        gradeScales: getGradeScalesData(),
        gradeType: gradeType,
        gradeTypeData: gradeTypeData
    };

    fetch('api/addGradeCalculationStructure.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            console.log(response);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {

                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();

                let y = 10;

                doc.setFontSize(16);

                if (response.grade_scales) {

                    const gradeScaleBody = Object.entries(response.grade_scales).map(([key, msg]) => [
                        "Row " + (parseInt(key) + 1),
                        msg
                    ]);

                    doc.autoTable({
                        startY: y,
                        theme: "grid",
                        head: [["Grade Scale Row", "Error Message"]],
                        body: gradeScaleBody,
                        styles: { fontSize: 11 },
                        headStyles: { fillColor: [67, 160, 71] }
                    });

                }

                if (response.rubric) {

                    Object.keys(response.rubric).forEach(rIndex => {
                        const rubric = response.rubric[rIndex];

                        const rows = [];

                        if (rubric.rubricName != null && String(rubric.rubricName).trim() !== "") {
                            rows.push(["Rubric Name", rubric.rubricName]);
                        }

                        if (rubric.rubricWeight != null && String(rubric.rubricWeight).trim() !== "") {
                            rows.push(["Rubric Weight", rubric.rubricWeight]);
                        }

                        if (rows.length > 0) {
                            doc.autoTable({
                                startY: y,
                                theme: "grid",
                                head: [["Rubric " + rIndex, "Error Message"]],
                                body: rows,
                                styles: { fontSize: 11 },
                                headStyles: { fillColor: [30, 136, 229] }
                            });
                            y = doc.lastAutoTable.finalY + 10;
                        }

                        Object.keys(rubric.criteria).forEach(cIndex => {
                            const criteria = rubric.criteria[cIndex];

                            let colname = "Rubric " + rIndex + " - Criteria " + cIndex;

                            doc.autoTable({
                                startY: y,
                                theme: "grid",
                                head: [[colname, "Error Message"]],
                                body: [
                                    ["Name", criteria.name],
                                    ["Weight", criteria.weight],
                                    ["Max Mark", criteria.maxMark],
                                ],
                                styles: { fontSize: 11 },
                                headStyles: { fillColor: [67, 160, 71] },
                            });

                            y = doc.lastAutoTable.finalY + 12;
                        });
                    });

                }
                const pdfData = doc.output("blob");
                const url = URL.createObjectURL(pdfData);
                window.open(url, "_blank");
            }
        })
        .catch((error) => {
            console.log(error);
            toggleBtnLoader(btn, false);
            showToast("error", "An error occurred. Please try again.");
        });
}

const addPaymentType = (btn, planId) => {
    toggleBtnLoader(btn, true);
    const data = {
        planId: planId,
        name: $("#type-name").val(),
    }

    const validationMap = {
        name: ["#type-name", "#type-feedback"],
    };


    fetch('api/addPaymentType.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            if (response.hasOwnProperty('done')) {
                $("#addPaymentType").modal('hide');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const editPaymentType = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        id: id,
        name: $("#edit-type-name").val(),
    }

    const validationMap = {
        name: ["#edit-type-name", "#edit-type-feedback"],
    };


    fetch('api/editPaymentType.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            if (response.done) {
                $("#editPaymentType").modal('hide');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addPaneltyPaymentCategory = (btn) => {
    toggleBtnLoader(btn, true);
    const data = {
        option: $("#option").val(),
        type: $("#type").val(),
        currency: $("#currency").val(),
        amount: $("#panelty-amount").val(),
        days: $("#panelty-days").val()
    }

    const validationMap = {
        option: ["#option", "#option-feedback"],
        type: ["#type", "#amount-type-feedback"],
        currency: ["#currency", "#currency-feedback"],
        amount: ["#panelty-amount", "#amount-feedback"],
        days: ["#panelty-days", "#days-feedack"]
    };


    fetch('api/addPaneltyPaymentCategory.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {

            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            if (response.done) {
                $("#addPaneltyType").modal('hide');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const editPaneltyPaymentCategory = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        id: id,
        option: $("#edit-option").val(),
        type: $("#edit-type").val(),
        currency: $("#edit-currency").val(),
        amount: $("#edit-panelty-amount").val(),
        days: $("#edit-panelty-days").val()
    }

    const validationMap = {
        option: ["#edit-option", "#edit-option-feedback"],
        type: ["#edit-type", "#edit-amount-type-feedback"],
        currency: ["#edit-currency", "#edit-currency-feedback"],
        amount: ["#edit-panelty-amount", "#edit-amount-feedback"],
        days: ["#edit-panelty-days", "#edit-days-feedack"]
    };

    fetch('api/editPaneltyPaymentCategory.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            if (response.done) {
                $("#editPaneltyType").modal('hide');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addPaymentGroup = (btn, intake_id) => {
    toggleBtnLoader(btn, true);
    const data = {
        intake_id: intake_id,
        name: $("#group-name").val(),
    }

    const validationMap = {
        name: ["#group-name", "#group-name-feedback"]
    };

    fetch('api/addPaymentGroup.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            if (response.done) {
                $("#addPaymentGroup").modal('hide');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}


const addPaymentIntakePaymentGroup = (btn, group) => {
    toggleBtnLoader(btn, true);
    const data = {
        group: group,
        category: $("#payment-category").val(),
        type: $("#payment-type").val(),
        plan: $("#payment-plan").val(),
        amount: $("#amount").val(),
        discountType: $("#discount-type").val(),
        discount: $("#discount").val(),
        discountNote: $("#discount-note").val(),
        dueDate: $("#due-date").val(),
        isEnablePanelty: $("#enable-panelty").prop('checked'),
        paneltyOption: $("#panelty-option").val(),
        paneltyCategories: selectedCategories
    }

    const validationMap = {
        category: ["#payment-category", "#payment-category-feedback"],
        type: ["#payment-type", "#payment-type-feedback"],
        plan: ["#payment-plan", "#payment-plan-feedback"],
        amount: ["#amount", "#amount-feedback"],
        discountType: ["#discount-type", "#discount-type-feedback"],
        discount: ["#discount", "#discount-feedback"],
        discountNote: ["#discount-note", "#discount-note-feedback"],
        dueDate: ["#due-date", "#due-date-feedback"],
        paneltyOption: ["#panelty-option", "#panelty-option-feedback"]
    }

    fetch('api/addPaymentIntakePaymentGroup.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            console.log(response);
            toggleBtnLoader(btn, false);
            applyValidation("", validationMap, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}


const loadPaymentType = (planId = null) => {
    const plan = planId ? planId : $("#payment-plan").val();
    fetch(`api/loadPaymentType.php?id=${plan}`)
        .then(res => res.json())
        .then(response => {
            if (response.error) {
                showToast("error", response.error);
                $("#payment-type").prop("disabled", true);
                return;
            }

            planHasPaymentTypes = response.types;
            maximumAllowedPayments = response.types.length;

            if (planId == null) {
                if (response.plan) {
                    $("#payment-plan").addClass("is-invalid");
                    $("#payment-plan-feedback").text(response.plan);
                    $("#payment-type").prop("disabled", true).html("<option value='0'>- Select -</option>");
                    return;
                }

                if (response.types.length != 0) {
                    // Clear validation
                    $("#payment-plan").removeClass("is-invalid");
                    $("#payment-plan-feedback").text("");

                    // Populate payment types
                    let options = `<option value="0">- Select -</option>`;
                    response.types.forEach(type => {
                        options += `<option value="${type.payment_type_id}">${type.payment_type}</option>`;
                    });

                    $("#payment-type")
                        .html(options)
                        .prop("disabled", false);
                    $("#fees-type").hasClass('d-none') ? $("#fees-type").removeClass('d-none') : "";
                } else {
                    $("#plan-feedback").text("No payment type found for this plan.");
                }


                if (response.types.length > 1) {
                    $("#add-new-payment-area").hasClass('d-none') ? $("#add-new-payment-area").removeClass('d-none') : "";
                } else {
                    $("#add-new-payment-area").hasClass('d-none') ? '' : $("#add-new-payment-area").addClass('d-none');
                }
            }


        })
        .catch(() => {
            showToast("error", "An error occurred. Please try again later.");
        });
};

const loadPaymentPlanHasStudents = (intakeId) => {
    const data = {
        id: intakeId,
        plan: $("#payment-plan").val(),
        category: $("#payment-category").val()
    }
    fetch('api/loadPaymentPlanHasStudents.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((text) => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#student-list-container").html(result);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addIntakeStudentsToPayments = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        id: id,
        category: $("#payment-category").val(),
        plan: $("#payment-plan").val(),
        payments: collectInstallmentData(),
        checkedStudents: selectedUsers,
        unCheckedStudents: unSelectedUsers,
        checkAll: $("#check-all").prop('checked')
    }

    fetch('api/addIntakeStudentsToPayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            if (response.done) {
                toggleBtnLoader(btn, false);
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            }
            if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            }

            if (response.installments) {
                toggleBtnLoader(btn, false);
                generatePaymentErrorPDF(response);
            }
        })
        .catch((error) => {
            console.log(error);
            showToast("error", "An error occured. Please try again later.");
        });

}

const filterPaymentCategoryStudents = (id) => {
    const data = {
        id: id,
        plan: $("#payment-plan").val(),
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        sortByName: sortCondition1,
        sortByDate: sortCondition2,
        sortById: sortCondition3,
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
        selectStudents: selectedUsers,
        unSelectStudents: unSelectedUsers,
    }

    fetch('api/filterPaymentCategoryStudents.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#student-list-container").html(data);
                $("#student-list-container").hasClass('d-none') ? $("#student-list-container").removeClass('d-none') : "";

            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}


const addBranch = (btn) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("branch_name", $("#branch-name").val());
    form.append("branch_cover", $("#cover-input").prop("files")[0]);

    const validationMap = {
        name: ["#branch-name", "#af-name-feedback"],
        cover: ["#cover-input", "#af-cover-feedback"]
    }

    fetch("api/addBranch.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            if (response.done) {
                $("#addfaculty").modal("hide");
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const editBranch = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("branch_id", id);
    form.append("branch_name", $("#edit-branch-name").val());
    form.append("branch_cover", $("#edit-cover-input").prop("files")[0]);

    const validationMap = {
        name: ["#edit-branch-name", "#ef-name-feedback"],
        cover: ["#edit-cover-input", "#ef-cover-feedback"]
    }

    fetch("api/editBranch.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                $("#editBranch").modal("hide");
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else if (response.error) {
                $("#editBranch").modal("hide");
                setTimeout(() => { showToast("error", response.error); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterBranchesPermissions = () => {
    const data = {
        text: $("#search-text").val(),
        selectedPermissions: selectedPermissions,
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterBranchesPermissions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);

            }
        })
        .catch((error) => {
            ////console.log("Error:", error);
        });
}

const branchesPermissionsSearchUsers = () => {
    const searchText = $('#search-text').val();
    const data = {
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/branchesPermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    showToast("error", "An error occured. Please try again later.");
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const loadAvailableUserBranchesPermission = (btn) => {
    console.log("helooo");
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserBranchesPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((res) => res.text())
            .then((text) => {
                let result;
                try {
                    result = JSON.parse(text);
                } catch {
                    result = text;
                }

                if (typeof result === "object" && result.error) {
                    if (response.error) {
                        showToast("error", response.error);
                    } else {
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                    }
                    toggleBtnLoader(btn, false);
                } else {
                    displayUserPermissions(result);
                    toggleBtnLoader(btn, false);
                }
            })
            .catch((error) => {
                toggleBtnLoader(btn, false);
                showToast("error", "An error occured. Please try again later.");
            });
    }
}


const addBranchUserPermissions = (btn, branchId) => {
    console.log('heloo');

    if (userPermissions == []) {
        showToast("warning", "Select new users' permissions and continue.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            branch_id: branchId,
            usersPermissions: userPermissions,
        };
        fetch('api/addBranchUserPermissions.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((response) => response.text()) // Get the response as text
            .then((data) => {

                toggleBtnLoader(btn, false);
                console.log(data);
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                } else if (response.hasOwnProperty('done')) {
                    setTimeout(() => { showToast("success", response.done); }, 1000);
                    if (status == 1) {
                        setTimeout(() => { window.location.reload(); }, 4000);
                    } else {
                        setTimeout(() => { window.location.reload() }, 4000);
                    }

                }
            })
            .catch((error) => {
                ////console.log("Error:", error);
            });
    }

}

const filterBranchPermissions = (branchId) => {
    const data = {
        branchId: branchId,
        text: $("#search-text").val(),
        selectedPermissions: selectedPermissions,
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterBranchPermissions.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#users-table-container").html(data);

            }
        })
        .catch((error) => {
            ////console.log("Error:", error);
        });
}


const updateBranchUserPermission = (btn, branchId, userId) => {
    const selectValue = $("#user-permission-update-selector").val();
    const checkAll = $("#check-all").prop('checked');
    if (selectValue == 0) {
        showToast("warning", "Please select an option if you want to update the status.");
    } else if (selectedPermissions.length == 0) {
        showToast("warning", "This user already has all permissions in this status. Please select another status to continue.");
    } else {
        toggleBtnLoader(btn, true);
        const data = {
            branchId, branchId,
            userId: userId,
            checkAll: checkAll,
            status: selectValue,
            selectedPermissions: selectedPermissions,
            unselectedPermissions: unSelectedPermissions
        }

        fetch('api/updateBranchUserPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((response) => response.text())
            .then((data) => {
                toggleBtnLoader(btn, false);
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                } else if (response.hasOwnProperty('done')) {
                    setTimeout(() => { showToast("success", response.done); }, 1000);
                    setTimeout(() => { window.location.reload(); }, 4000);

                }
            })
            .catch((error) => {
                ////console.log("Error:", error);
            });
    }
}

const resendStudentRegistrationEmail = (student) => {
    toggleLoader(true);
    fetch('api/resendStudentRegistrationEmail.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ student: student })
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const resendUserRegistrationEmail = (user) => {
    toggleLoader(true);
    fetch('api/resendUserRegistrationEmail.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user: user })
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}


const addPortalSubscription = () => {
    toggleLoader(true);
    const data = {
        studentCount: $("#student-count").val(),
        amount: $("#amount").val(),
        note: $("#note").val()
    }
    console.log(data);
    fetch('api/addPortalSubscription.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                $("#addSubscriptions").modal("hide");
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            } else {
                setTimeout(() => { showToast("error", "Something went wrong.Check data fields and try again"); }, 1000);
                if (response.hasOwnProperty('student')) {
                    $("#student-count").addClass("is-invalid");
                    $("#sc-feedback").text(response.student);
                } else {
                    $("#student-count").removeClass("is-invalid");
                    $("#sc-feedback").text("");
                }

                if (response.hasOwnProperty('amount')) {
                    $("#amount").addClass("is-invalid");
                    $("#am-feedback").text(response.amount);
                } else {
                    $("#amount").removeClass("is-invalid");
                    $("#am-feedback").text("");
                }

                if (response.hasOwnProperty('note')) {
                    $("#note").addClass("is-invalid");
                    $("#note-feedback").text(response.note);
                } else {
                    $("#note").removeClass("is-invalid");
                    $("#note-feedback").text("");
                }

            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterPortalSubscriptionPayment = () => {
    const data = {
        text: $("#search-text").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterPortalSubscriptionPayment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-table-container").html(data);
                getTableWidth('#students-table', '#pagination-container');
            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const downloadStudentIDCard = (id, shiId = null) => {
    toggleLoader(true);
    fetch('api/downloadStudentIDCard.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id, shiId })
    })
        // .then((response) => response.text())
        // .then((data) => {
        //     console.log(data);
        // })
        // .catch((error) => {
        //     setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        // });
        .then(response => {
            const disposition = response.headers.get("Content-Disposition");
            let filename = "application.pdf";

            if (disposition && disposition.includes("filename=")) {
                filename = disposition.split("filename=")[1].replace(/"/g, "");
            }

            return response.blob().then(blob => ({ blob, filename }));
        })
        .then(({ blob, filename }) => {
            toggleLoader(false);

            const url = window.URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = filename;
            a.click();
            window.URL.revokeObjectURL(url);
        })
        .catch(() => {
            toggleLoader(false);
            showToast("error", "An error occurred. Please try again later.");
        });
}
const downloadIntakeStudentIDCard = (id, intakeId) => {
    toggleLoader(true);
    fetch('api/downloadStudentIDCard.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({ id, shiId })
    })
        // .then((response) => response.text())
        // .then((data) => {
        //     console.log(data);
        // })
        // .catch((error) => {
        //     setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        // });
        .then(response => {
            const disposition = response.headers.get("Content-Disposition");
            let filename = "application.pdf";

            if (disposition && disposition.includes("filename=")) {
                filename = disposition.split("filename=")[1].replace(/"/g, "");
            }

            return response.blob().then(blob => ({ blob, filename }));
        })
        .then(({ blob, filename }) => {
            toggleLoader(false);

            const url = window.URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = filename;
            a.click();
            window.URL.revokeObjectURL(url);
        })
        .catch(() => {
            toggleLoader(false);
            showToast("error", "An error occurred. Please try again later.");
        });
}

const addSubjectAttachments = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("subject", id);
    form.append("tutes", $("#file-selector-input")[0].files[0]);
    // Or for multiple files:
    var files = $("#file-selector-input")[0].files;
    for (var i = 0; i < files.length; i++) {
        form.append("tutes[]", files[i]);
    }

    fetch("api/addSubjectAttachments.php", {
        method: "POST",
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.error) {
                showToast("error", response.error);
            } else if (response.done) {
                $("#addTute").modal('hide');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload() }, 3000);
            } else {
                showToast("error", "Something went wrong.Check data fields and try again");
                if (response.tute) {
                    const feedback = document.getElementById('attachment-feedback');
                    feedback.innerHTML = `
                    <ul class="ps-3">
                        ${response.tute.map(e => `<li>${e}</li>`).join('')}
                    </ul>
                    `;
                }
            }

        })
        .catch((error) => {
            showToast("error", "Something went wrong.Please try again later");
        });
}

const removeSubjectAttachment = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        id: id,
    }
    fetch('api/removeSubjectAttachment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                const attachments = document.querySelectorAll('[data-attachment-id]');

                if (attachments.length == 1) {
                    const attachmentDiv = document.querySelector(`[data-attachment-id="${id}"]`);
                    if (attachmentDiv) {
                        attachmentDiv.style.transition = "opacity 0.5s, transform 0.5s";
                        attachmentDiv.style.opacity = 0;
                        attachmentDiv.style.transform = "scale(0.95)";
                        setTimeout(() => attachmentDiv.remove(), 500);
                    }
                    setTimeout(() => { window.location.reload() }, 3500);
                } else {
                    const attachmentDiv = document.querySelector(`[data-attachment-id="${id}"]`);
                    if (attachmentDiv) {
                        attachmentDiv.style.transition = "opacity 0.5s, transform 0.5s";
                        attachmentDiv.style.opacity = 0;
                        attachmentDiv.style.transform = "scale(0.95)";
                        setTimeout(() => attachmentDiv.remove(), 500);
                    }
                }

            } else if (response.error) {
                showToast("error", response.error);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const markInOutStatus = (id) => {
    fetch('api/markInOutStatus.php?id=' + id)
        .then(res => res.json())
        .then(response => {
            if (response.done) {
                if (response.status == 1) {
                    updateMarkStatus("check-in", response.done);
                } else {
                    updateMarkStatus("check-out", response.done);
                }
            } else if (response.error) {
                updateMarkStatus("error", response.error);
            } else if (response.marked) {
                updateMarkStatus("marked", response.marked);
            }
            filterAttendnace();
        })
        .catch(() => {
            updateMarkStatus("error", "Something went wrong");
        });
};

const filterAttendnace = () => {
    const data = {
        text: $("#search-text").val(),
        date: $("#date").val(),
        faculty: $("#faculty-selector").val(),
        programme: $("#programme-selector").val(),
        intake: $("#intake-selector").val(),
        month: $("#month-selector").val(),
        sortByName: sortCondition1,
        sortByDate: sortCondition2,
        update: $("#update-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
        selectStudents: selectedUsers,
        unSelectStudents: unSelectedUsers,
    }

    fetch('api/filterAttendnace.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.text())
        .then((text) => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#students-table-container").html(result);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.")
        });
}

const downloadCheckInCheckOutReport = (btn) => {
    toggleBtnLoader(btn, true);

    const data = {
        faculty: $("#report-faculty-selector").val(),
        programme: $("#report-programme-selector").val(),
        intake: $("#report-intake-selector").val(),
        month: $("#report-month-selector").val(),
    }

    const validationMap = {
        faculty: ["#report-faculty-selector", "#report-faculty-feedback"],
        programme: ["#report-programme-selector", "#report-programme-feedback"],
        intake: ["#report-intake-selector", "#report-intake-feedback"],
        month: ["#report-month-selector", "#report-month-feedback"],
    }

    fetch('api/downloadCheckInCheckOutReport.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then(async (response) => {
            toggleBtnLoader(btn, false);

            const contentType = response.headers.get('content-type');

            if (contentType && contentType.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
                return { type: 'file', data: await response.blob() };
            }

            if (contentType && contentType.includes('application/json')) {
                return { type: 'json', data: await response.json() };
            }

            const text = await response.text();
            console.log('Raw response:', text);
            throw new Error('Unexpected response type.');
        })
        .then((res) => {

            // 🔥 HANDLE RESPONSE HERE

            if (res.type === 'file') {
                // download file
                const url = window.URL.createObjectURL(res.data);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'attendance.xlsx';
                document.body.appendChild(a);
                a.click();
                a.remove();
            }

            if (res.type === 'json') {
                if (res.data.error) {
                    showToast("error", res.error);
                } else {
                    applyValidation(res.data, validationMap);
                }
            }
        })
        .catch((err) => {
            toggleBtnLoader(btn, false);
            console.error(err);
        });
}

const loadProgrammesForReport = () => {
    const faculty = $("#report-faculty-selector").val();
    fetch(`api/loadProgrammes.php?id=${faculty}`)
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                    $("#report-programme-selector").attr("disabled", true);
                } else if (response.hasOwnProperty('faculty')) {
                    $("#report-programme-selector").attr("disabled", true);
                    $("#report-programme-selector").html("<option value='0'>- Select -</option>");
                    $("#report-faculty-selector").addClass("is-invalid");
                    $("#report-faculty-feedback").text(response.faculty);
                }
            } catch (error) {
                $("#report-faculty-selector").removeClass("is-invalid");
                $("#report-faculty-feedback").text("");
                $("#report-programme-selector").removeAttr("disabled");
                $("#report-programme-selector").html(data);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const loadIntakesForReport = () => {
    const programme = $("#report-programme-selector").val();
    fetch(`api/loadIntakes.php?id=${programme}`)
        .then((response) => response.text())
        .then((data) => {
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    showToast("error", response.error);
                    $("#report-intake-selector").attr("disabled", true);
                } else if (response.hasOwnProperty('programme')) {
                    $("#report-intake-selector").attr("disabled", true);
                    $("#report-intake-selector").html("<option value='0'>- Select -</option>");
                    $("#report-programme-selector").addClass("is-invalid");
                    $("#report-programme-feedback").text(response.programme);
                }
            } catch (error) {
                $("#report-programme-selector").removeClass("is-invalid");
                $("#report-programme-feedback").text("");
                $("#report-intake-selector").removeAttr("disabled");
                $("#report-intake-selector").html(data);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

/**
 * ════════════════════════════════════════════════════════════════════════════════
 * TAX-INCLUSIVE PAYMENT RECEIPT UPLOAD - UPDATED v3
 * ════════════════════════════════════════════════════════════════════════════════
 * 
 * This function handles:
 * 1. Tax-inclusive payment calculation (Paid = Base + Tax)
 * 2. Automatic overpayment detection
 * 3. Application to next installments
 * 4. Multi-currency support with exchange rates
 * 5. Real-time feedback to user
 */

/**
 * ════════════════════════════════════════════════════════════════════════════════
 * TAX-INCLUSIVE PAYMENT RECEIPT UPLOAD - UPDATED v3
 * ════════════════════════════════════════════════════════════════════════════════
 * 
 * This function handles:
 * 1. Tax-inclusive payment calculation (Paid = Base + Tax)
 * 2. Automatic overpayment detection
 * 3. Application to next installments
 * 4. Multi-currency support with exchange rates
 * 5. Real-time feedback to user
 */

const uploadStudentPaymentReceipt = (btn, id, currency_data = null) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("payment_id", id);
    form.append("discount_amount", $("#discount-amount").val());
    form.append("penalty_amount", $("#penalty-amount").val());
    form.append("method", $("#payment-method").val());
    form.append("paid_amount", $("#paid-amount").val()); // ← Tax-inclusive amount
    form.append("paid_date", $("#paid-date").val());
    form.append("balance", $("#balance-amount").val()); // ← For validation
    form.append("note", $("#note").val());

    // Append payment slips
    var files = $("#file-selector-input")[0].files;
    for (var i = 0; i < files.length; i++) {
        form.append("slips[]", files[i]);
    }
    console.log(currency_data);
    // Append currency data
    if (currency_data != null) {
        form.append("currency", JSON.stringify(currency_data));
    }


    // Validation mapping
    const validationMap = {
        amount: ["#paid-amount", "#paid-amount-feedback"],
        method: ["#payment-method", "#payment-method-feedback"],
        discount: ["#discount-amount", "#discount-amount-feedback"],
        penalty: ["#penalty-amount", "#penalty-amount-feedback"],
        paid_date: ["#paid-date", "#paid-date-feedback"],
        note: ["#note", "#note-feedback"],
    };

    // ════════════════════════════════════════════════════════════════════════════
    // SUBMIT FORM
    // ════════════════════════════════════════════════════════════════════════════

    fetch("api/uploadStudentPaymentReceipt.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.text())
        .then((responseText) => {
            toggleBtnLoader(btn, false);

            try {
                // Parse JSON response
                var response = JSON.parse(responseText);
                console.log('✅ Payment Receipt Response:', response);

                // Clear previous validation errors
                applyValidation('', validationMap, false);

                // ════════════════════════════════════════════════════════════
                // SUCCESS CASE
                // ════════════════════════════════════════════════════════════
                if (response.done) {
                    // Show success toast with details
                    let successMsg = '✅ ' + response.done;

                    if (response.receiptNumber) {
                        successMsg += '<br>Receipt: ' + response.receiptNumber;
                    }

                    if (response.overpayment && response.overpayment.detected) {
                        successMsg += '<br>⚠️ Overpayment detected: Applied to ' + response.overpayment.nextInstallments + ' next installment(s)';
                    }

                    showToast("success", successMsg);

                    // Redirect after brief delay
                    setTimeout(() => {
                        window.location = "./intake-student-payments?student-id=" + response.id;
                    }, 2500);

                    return;
                }

                // ════════════════════════════════════════════════════════════
                // ERROR CASE
                // ════════════════════════════════════════════════════════════
                if (response.error) {
                    // Check if this is an overpayment error that needs modal
                    if (response.errorType === 'OVERPAYMENT_NO_NEXT_INSTALLMENT' && response.errorDetails) {
                        showOverpaymentErrorModal(response);
                    } else {
                        showToast("error", "❌ " + response.error);
                    }
                    return;
                }

                if (response.warning) {
                    showToast("warning", "⚠️ " + response.warning);
                    return;
                }

                // ════════════════════════════════════════════════════════════
                // VALIDATION ERRORS
                // ════════════════════════════════════════════════════════════

                // Apply field-level validation errors
                applyValidation(response, validationMap);

                // Handle file slip errors separately
                if (response.slip) {
                    const slipErrorContainer = $("#file-feedback");
                    slipErrorContainer.html("");

                    if (Array.isArray(response.slip)) {
                        slipErrorContainer.append(
                            '<p class="text-14 text-danger fw-bold">⚠️ Upload Errors:</p>'
                        );
                        response.slip.forEach(item => {
                            slipErrorContainer.append(
                                '<p class="text-12 text-danger" style="margin:4px 0;">• ' + item + '</p>'
                            );
                        });
                    } else {
                        slipErrorContainer.append(
                            '<p class="text-12 text-danger">• ' + response.slip + '</p>'
                        );
                    }

                    // Highlight the file input area
                    $("#drop-section").addClass("border-danger");
                } else {
                    $("#file-feedback").html("");
                    $("#drop-section").removeClass("border-danger");
                }

            } catch (parseErr) {
                console.error('❌ JSON Parse Error:', parseErr);
                console.log('Raw response:', responseText);
                showToast("error", "Invalid response from server. Please try again.");
            }
        })
        .catch((error) => {
            console.error('❌ Network Error:', error);
            toggleBtnLoader(btn, false);
            showToast("error", "Network error occurred. Please check your connection and try again.");
        });
};

/**
 * ════════════════════════════════════════════════════════════════════════════════
 * OVERPAYMENT ERROR MODAL
 * Shows error message when overpayment is attempted but no next installment exists
 * ════════════════════════════════════════════════════════════════════════════════
 */
function showOverpaymentErrorModal(response) {
    const errorDetails = response.errorDetails || {};

    const modalHtml = `
        <div class="modal fade" id="overpaymentErrorModal" tabindex="-1" role="dialog" aria-labelledby="overpaymentErrorTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content border-danger">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="overpaymentErrorTitle">
                            <i class="fas fa-exclamation-triangle"></i> Overpayment Not Allowed
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger" role="alert">
                            <h6 class="alert-heading fw-bold">⚠️ ${response.error}</h6>
                        </div>

                        <div class="row my-3">
                            <div class="col-md-6 mb-3">
                                <div class="card">
                                    <div class="card-body p-3">
                                        <small class="text-muted d-block">Amount Entered</small>
                                        <h5 class="text-primary mb-0">
                                            ${errorDetails.paidAmount
            ? new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(errorDetails.paidAmount)
            : '0.00'}
                                        </h5>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card">
                                    <div class="card-body p-3">
                                        <small class="text-muted d-block">Current Balance</small>
                                        <h5 class="text-warning mb-0">
                                            ${errorDetails.currentBalance
            ? new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(errorDetails.currentBalance)
            : '0.00'}
                                        </h5>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card bg-light mb-3">
                            <div class="card-body p-3">
                                <h6 class="card-title fw-bold mb-2">Problem</h6>
                                <p class="mb-0">
                                    <i class="fas fa-circle-xmark text-danger"></i>
                                    <strong>Overpayment Detected:</strong> 
                                    ${errorDetails.overpayAmount
            ? new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(errorDetails.overpayAmount)
            : '0.00'}
                                </p>
                                <p class="text-muted small mt-2 mb-0">
                                    There is no next installment available in the payment plan to apply this overpayment.
                                </p>
                            </div>
                        </div>

                        <div class="card bg-info bg-opacity-10 border-info mb-3">
                            <div class="card-body p-3">
                                <h6 class="card-title fw-bold mb-2">💡 Solution</h6>
                                <p class="mb-0">
                                    ${errorDetails.suggestion || 'Please enter an amount less than or equal to the current balance.'}
                                </p>
                            </div>
                        </div>

                        <div class="text-muted small">
                            <strong>What to do:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Adjust the paid amount to match or be less than the current balance</li>
                                <li>Or contact the administration if this payment is for a future installment</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remove old modal if exists
    $('#overpaymentErrorModal').remove();

    // Add modal to body and show
    $('body').append(modalHtml);
    $('#overpaymentErrorModal').modal('show');

    // Clean up modal when hidden
    $('#overpaymentErrorModal').on('hidden.bs.modal', function () {
        $(this).remove();
    });
}


const addIncident = (btn) => {
    toggleBtnLoader(btn, true);

    const form = new FormData();
    form.append('student', $("#reg-no").val());
    form.append('location', $("#location").val());
    form.append('date', $("#date").val());
    form.append('time', $("#time").val());
    form.append('type', $("#type-selector").val());
    form.append('note', $("#note").val());
    form.append("attachments", $("#file-selector-input")[0].files[0]);
    var files = $("#file-selector-input")[0].files;
    for (var i = 0; i < files.length; i++) {
        form.append("attachments[]", files[i]);
    }

    const validationMap = {
        student: ["#reg-no", "#reg-no-feedback"],
        location: ["#location", "#location-feedback"],
        date: ["#date", "#date-feedback"],
        time: ["#time", "#time-feedback"],
        type: ["#type-selector", "#type-selector-feedback"],
        note: ["#note", "#note-feedback"]
    };

    fetch('api/addIncident.php', {
        method: 'POST',
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation(response, validationMap, false);
            if (response.done) {
                toggleBtnLoader(btn, false);
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
                if (response.attachment) {
                    const feedback = document.getElementById('attachment-feedback');
                    feedback.innerHTML = `
                    <ul class="ps-3">
                        ${response.attachment.map(e => `<li>${e}</li>`).join('')}
                    </ul>
                    `;
                }
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const editIncident = (btn, id) => {
    toggleBtnLoader(btn, true);

    const form = new FormData();
    form.append('id', id);
    form.append('student', $("#reg-no").val());
    form.append('location', $("#location").val());
    form.append('date', $("#date").val());
    form.append('time', $("#time").val());
    form.append('type', $("#type-selector").val());
    form.append('note', $("#note").val());
    form.append("attachments", $("#file-selector-input")[0].files[0]);
    var files = $("#file-selector-input")[0].files;
    for (var i = 0; i < files.length; i++) {
        form.append("attachments[]", files[i]);
    }

    const validationMap = {
        student: ["#reg-no", "#reg-no-feedback"],
        location: ["#location", "#location-feedback"],
        date: ["#date", "#date-feedback"],
        time: ["#time", "#time-feedback"],
        type: ["#type-selector", "#type-selector-feedback"],
        note: ["#note", "#note-feedback"]
    };

    fetch('api/editIncident.php', {
        method: 'POST',
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            console.log(response);
            toggleBtnLoader(btn, false);
            applyValidation(response, validationMap, false);
            if (response.done) {
                toggleBtnLoader(btn, false);
                showToast("success", response.done);
                setTimeout(() => { window.history.back(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}


const filterIncidents = () => {
    const data = {
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        sortByName: sortCondition1,
        sortByDate: sortCondition2,

        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,

    }

    fetch('api/filterIncidents.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.text())
        .then((text) => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#students-table-container").html(result);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.")
        });
}

const attendancePermissionsSearchUsers = () => {
    const searchText = $('#search-text').val();
    const data = {
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/attendancePermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log("Data from server:", data);
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    ////console.log("JSON Response:", jsonResponse);

                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    ////console.log("Error parsing JSON:", e);
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const loadAvailableUserAttendancePermission = () => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        const data = {
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserAttendancePermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((response) => response.text())
            .then((data) => {
                try {
                    const response = JSON.parse(data);
                    if (response.error) {
                        showToast("error", response.error);
                    } else {
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                    }
                } catch (e) {
                    displayUserPermissions(data);
                }
            })
            .catch((error) => {
                showToast("error", "An error occured. Please try again later.");
                showToast("error", "Failed to add user. Please try again.");
            });
    }
}


const incidentsPermissionsSearchUsers = () => {
    const searchText = $('#search-text').val();
    const data = {
        searchText: searchText,
        selectedUsers: selectedUsers,
        newPermissionUsers: newPermissionUsers
    }
    fetch(`api/incidentsPermissionsSearchUsers.php`, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log("Data from server:", data);
            const [htmlContent, jsonContent] = data.split('{"msg":"');
            if (jsonContent) {
                const fullJsonContent = `{"msg":"${jsonContent}`;
                try {
                    const jsonResponse = JSON.parse(fullJsonContent);
                    ////console.log("JSON Response:", jsonResponse);

                    if (jsonResponse.msg != "Valid user email") {
                        $('#search-text').addClass('is-invalid');
                    }
                } catch (e) {
                    ////console.log("Error parsing JSON:", e);
                }
            }

            if (htmlContent) {
                $('#users-table-container').html(htmlContent);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const loadAvailableUserIncidentsPermission = () => {
    if (selectedUsers.length == 0) {
        showToast("error", "Please select at least one user.");
    } else {
        const data = {
            selectedUsers: selectedUsers,
            userCount: addNewPermissionsUserCount,
        };

        fetch('api/loadAvailableUserIncidentsPermission.php', {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data),
        })
            .then((response) => response.text())
            .then((data) => {
                try {
                    const response = JSON.parse(data);
                    if (response.error) {
                        showToast("error", response.error);
                    } else {
                        $('#search-text').addClass('is-invalid');
                        $('#search-text-feedback').text(response.msg);
                    }
                } catch (e) {
                    displayUserPermissions(data);
                }
            })
            .catch((error) => {
                showToast("error", "An error occured. Please try again later.");
                showToast("error", "Failed to add user. Please try again.");
            });
    }
}


const removeIncidentAttachment = (id) => {
    const data = {
        id: id,
    }
    fetch('api/removeIncidentAttachment.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('done')) {
                showToast("success", response.done);
                const attachmentDiv = document.querySelector(`[data-attachment-id="${id}"]`);
                if (attachmentDiv) {
                    attachmentDiv.style.transition = "opacity 0.5s, transform 0.5s";
                    attachmentDiv.style.opacity = 0;
                    attachmentDiv.style.transform = "scale(0.95)";
                    setTimeout(() => attachmentDiv.remove(), 500);
                }
            } else if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

function sendSelectedMessage(btn) {
    toggleBtnLoader(btn, true);
    const sendStudent = document.getElementById("sendStudent").checked;
    const sendParent = document.getElementById("sendParent").checked;
    const message = document.getElementById("messageText").value.trim();
    const title = document.getElementById("messageTitle").value.trim();
    const errorBox = document.getElementById("messageError");

    errorBox.classList.add("d-none");
    errorBox.innerText = "";

    if (selectedUsers.length === 0) {
        toggleBtnLoader(btn, false);
        showMessageError("Please select at least one student.");
        return;
    }

    if (!sendStudent && !sendParent) {
        toggleBtnLoader(btn, false);
        showMessageError("Please select at least one recipient: students or parents.");
        return;
    }

    if (message === "") {
        toggleBtnLoader(btn, false);
        showMessageError("Please type your message.");
        return;
    }


    const data = {
        selected_students: selectedUsers,
        send_student: sendStudent ? 1 : 0,
        send_parent: sendParent ? 1 : 0,
        title: title,
        message: message,
    };

    fetch("api/sendMessageProcess.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data)
    })
        .then(res => res.json())
        .then(response => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                const modalEl = document.getElementById("messageModal");
                const modal = bootstrap.Modal.getInstance(modalEl);
                modal.hide();
                showToast("success", response.done);
            } else if (response.error) {
                showToast('error', response.error);
            }

        })
        .catch(error => {
            showToast("error", "An error occured. Please try again later.")
        });
}

const filterMessageCenterStudentsPagination = () => {
    const data = {
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        sortByName: sortCondition1,
        sortByDate: sortCondition2,
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
        selectStudents: selectedUsers,
        unSelectStudents: unSelectedUsers,
        faculty: $("#faculty-selector").val(),
        programme: $("#programme-selector").val(),
        intake: $("#intake-selector").val()
    }

    fetch('api/filterMessageCenterStudentsPagination.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.text())
        .then((text) => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#students-table-container").html(result);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.")
        });
}

const filterIntakePaymentPlanStudents = (id) => {
    const data = {
        id: id,
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        plan: $("#payment-plan").val(),
        category: $("#payment-category").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterIntakePaymentPlanStudents.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            ////console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-table-container").html(data);

            }
        })
        .catch((error) => {
            setTimeout(() => { showToast("error", "An error occured. Please try again later."); }, 1000);
        });
}

const saveExchangeRate = (btn) => {
    toggleBtnLoader(btn, true);
    const data = {
        baseCurrencyId: $("#baseCurrency").val(),
        targetCurrencyId: $("#targetCurrency").val(),
        rate: $("#exchangeRate").val(),
        effectiveDate: $("#effectiveDate").val(),
        effectiveTime: $("#effectiveTime").val(),
        note: $("#exchangeNote").val(),
    };

    const validationMap = {
        baseCurrency: ["#baseCurrency", "#base-currency-feedback"],
        targetCurrency: ["#targetCurrency", "#target-currency-feedback"],
        rate: ["#exchangeRate", "#exchange-rate-feedback"],
        effectiveDate: ["#effectiveDate", "#effective-date-feedback"],
        effectiveTime: ["#effectiveTime", "#effective-time-feedback"],
        note: ["#exchangeNote", "#exchange-note-feedback"],
    };

    fetch('api/addCurrencyExchangeRate.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            applyValidation(response, validationMap, false);
            toggleBtnLoader(btn, false);
            if (response.done) {
                $("#exchangeRateModal").modal('hide');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            setTimeout(() => {
                showToast("error", "An error occurred. Please try again later.");
            }, 500);

        });

}

const updateExchangeRate = () => {
    const data = {
        exchangeRateId: $("#editExchangeRateId").val(),
        rate: $("#editExchangeRate").val(),
        effectiveDate: $("#editEffectiveDate").val(),
        note: $("#editExchangeNote").val(),
    };

    const validationMap = {
        rate: ["#editExchangeRate", "#edit-exchange-rate-feedback"],
        effectiveDate: ["#editEffectiveDate", "#edit-effective-date-feedback"],
        note: ["#editExchangeNote", "#edit-exchange-note-feedback"],
    };

    fetch("api/updateExchangeRate.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            applyValidation(response, validationMap, false);
            if (response.hasOwnProperty("done")) {
                showToast("success", response.done);
                $("#editExchangeRateModal").modal("hide");

                setTimeout(() => {
                    location.reload();
                }, 800);
            } else if (response.hasOwnProperty("error")) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch(() => {
            showToast("error", "An error occurred. Please try again later.");
        });
};

const filterCurrencyExchange = (id) => {
    const data = {
        currency: id,
        text: $("#user-search-text").val() || "",
        fromDate: $("#from-date").val() || "",
        toDate: $("#to-date").val() || "",
        resultPerPage: $("#show-result-selector").val() || 10,
        page: selectedPage || 1,
    };

    fetch('api/filterCurrencyExchange.php', {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
    })
        .then(res => res.text())
        .then(text => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#users-table-container").html(result);
            }
        })
        .catch(error => {
            console.error("Filter users failed:", error);
            showToast("error", "An error occurred. Please try again later.");
        });
};

const addRegistration = (btn) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("fname", $("#first-name").val());
    form.append("lname", $("#last-name").val());
    form.append("fullname", $("#full-name").val());
    form.append("nwi", $("#name-with-initials").val());
    form.append("title", $("#title-selector").val());
    form.append("gender", $("#gender-selector").val());
    form.append("marital_status", $("#marital-status-selector").val());
    form.append("nationality", $("#nationality").val());
    form.append("nic", $("#nic").val());
    form.append("passport", $("#passport").val());
    form.append("cob", $("#country-of-birth").val());
    form.append("cor", $("#country-of-residence").val());
    form.append("dob", $("#dob").val());
    form.append("email", $("#email").val());
    form.append("mobile", $("#mobile").val());
    form.append("whatsapp", $("#whatsapp").val());
    form.append("address", $("#address").val());
    form.append("address2", $("#address2").val());
    form.append("city", $("#city").val());
    form.append("pcode", $("#postal-code").val());
    form.append("image", $("#file-input").prop("files")[0]);
    form.append("branch", $("#branch-selector").val());
    form.append("faculty", $("#faculty-selector").val());
    form.append("programme", $("#programme-selector").val());
    form.append("intake", $("#intake-selector").val());
    form.append("grelationship", $("#grelationship-selector").val());
    form.append("gtitle", $("#gtitle-selector").val());
    form.append("gfname", $("#guardian-fname").val());
    form.append("glname", $("#guardian-lname").val());
    form.append("gemail", $("#guardian-email").val());
    form.append("gmobile", $("#guardian-mobile").val());
    form.append("gwhatsapp", $("#guardian-whatsapp").val());
    form.append("entry_type", $("input[name='entry_type']:checked").val());
    form.append("payment_option", $("#payment-option").val());

    appendStudentDocumentsToFormData(form);

    const validationMap = {
        id: ["#student-intake-id", "#student-intake-id-feedback"],
        fname: ["#first-name", "#fname-feedback"],
        lname: ["#last-name", "#lname-feedback"],
        fullname: ["#full-name", "#fullname-feedback"],
        nwi: ["#name-with-initials", "#nwi-feedback"],
        title: ["#title-selector", "#title-feedback"],
        gender: ["#gender-selector", "#gender-feedback"],
        marital_status: ["#marital-status-selector", "#marital-feedback"],
        nationality: ["#nationality", "#nationality-feedback"],
        cob: ["#country-of-birth", "#cob-feedback"],
        cor: ["#country-of-residence", "#cor-feedback"],
        nic: ["#nic", "#nic-feedback"],
        passport: ["#passport", "#passport-feedback"],
        dob: ["#dob", "#dob-feedback"],
        email: ["#email", "#email-feedback"],
        mobile: ["#mobile", "#mobile-feedback"],
        whatsapp: ["#whatsapp", "#whatsapp-feedback"],
        address: ["#address", "#address-feedback"],
        address2: ["#address2", "#address2-feedback"],
        city: ["#city", "#city-feedback"],
        pcode: ["#postal-code", "#postal-code-feedback"],
        branch: ["#branch-selector", "#branch-feedback"],
        faculty: ["#faculty-selector", "#faculty-feedback"],
        programme: ["#programme-selector", "#programme-feedback"],
        intake: ["#intake-selector", "#intake-feedback"],
        grelationship: ["#grelationship-selector", "#grelationship-feedback"],
        gtitle: ["#gtitle-selector", "#gtitle-feedback"],
        gfname: ["#guardian-fname", "#gfname-feedback"],
        glname: ["#guardian-lname", "#glname-feedback"],
        gemail: ["#guardian-email", "#gemail-feedback"],
        gmobile: ["#guardian-mobile", "#gmobile-feedback"],
        gwhatsapp: ["#guardian-whatsapp", "#gwhatsapp-feedback"],
        aic_application: ["#doc-aic-application", "#doc-aic-application-feedback"],
        university_application: ["#doc-university-application", "#doc-university-application-feedback"],
        signed_agreement: ["#doc-signed-agreement", "#doc-signed-agreement-feedback"],
        signed_program_brief: ["#doc-signed-program-brief", "#doc-signed-program-brief-feedback"],
        passport_nic: ["#doc-passport-nic", "#doc-passport-nic-feedback"],
        academic_documents: ["#doc-academic-documents", "#doc-academic-documents-feedback"],
        other_entry_documents: ["#doc-other-entry-documents", "#doc-other-entry-documents-feedback"],
        payment_slip: ["#doc-payment-slip", "#doc-payment-slip-feedback"],
        manager_checklist: ["#doc-manager-checklist", "#doc-manager-checklist-feedback"],
        discount_approved_document: ["#doc-discount-approved-document", "#doc-discount-approved-document-feedback"],
        signed_fee_structure: ["#doc-signed-fee-structure", "#doc-signed-fee-structure-feedback"],
        payment_option: ["#payment-option", "#payment-option-feedback"]
    };

    fetch("api/addRegistration.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            applyValidation(response, validationMap, false);
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => window.location.reload(), 3000);
                return;
            } else if (response.error) {

                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const updateLeadStatus = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        id: id,
        updateStatus: $("#status-selector").val(),
        note: $("#note").val(),
        intake: $("#intake-selector").val(),
        sid: $("#student-intake-id").val()
    };

    fetch('api/updateLeadStatus.php', {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                $("updateLead").modal('hide');
                showToast("success", response.done);
                setTimeout(() => window.location.reload(), 3000);
                return;
            } else if (response.error) {
                showToast("error", response.error);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addIntakePaymentPlan = (btn, id, confirmation = null) => {
    const data = {
        id: id,
        planName: $("#plan-name").val(),
        category: $("#payment-category").val(),
        plan: $("#payment-plan").val(),
        isDefault: $("#is-default").is(":checked"),
        paymentAmount: $("#payment-amount").val(),
        discountType: $("#plan-discount-type").val(),
        discountAmount: $("#plan-discount").val(),
        discountNote: $("#plan-discount-note").val(),
        payments: collectInstallmentData(),
        confirmation: confirmation

    }

    const validationMap = {
        name: ["#plan-name", "#plan-name-feedback"],
        discountNote: ['#plan-discount-note', "#plan-discount-note-feedback"],
        paymentAmount: ["#payment-amount", "#payment-amount-feedback"],
        discountType: ["#plan-discount-type", "#plan-discount-type-feedback"],
        discountAmount: ["#plan-discount", "#plan-discount-feedback"],
        category: ["#payment-category", "#payment-category-feedback"],
        plan: ["#payment-plan", "#payment-plan-feedback"],
    }

    fetch('api/addIntakePaymentPlan.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            console.log(response);
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            $("#scheduleConfirmModal").modal('hide');
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.history.back(); }, 4000);
            } else if (response.error) {
                showToast("error", response.error);
            } else if (response.warning) {
                showToast("warning", response.warning);
            } else if (response.confirm_required) {

                const confirmModal = new bootstrap.Modal(document.getElementById("scheduleConfirmModal"));

                $("#confirmScheduleSave").off("click");
                $("#confirmScheduleSave").on("click", function () {
                    addIntakePaymentPlan(btn, id, true);
                });

                $("#scheduleConfirmModalCancel").off("click");
                $("#scheduleConfirmModalCancel").on("click", function () {
                    addIntakePaymentPlan(btn, id, false);
                });
                confirmModal.show();
                return;
            } else {
                applyValidation(response, validationMap);

                if (response.installments) {
                    generatePaymentErrorPDF(response);
                }
            }
        }).catch((error) => {
            console.error('Error:', error);
            showToast("error", "Something went wrong");
        });
}

const removeIntakePaymentPlan = (id) => {
    toggleLoader(true);
    const data = {
        plan: id
    }
    fetch('api/removeIntakePaymentPlan.php', {
        method: "POST",
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                $("#removeIntakePaymentPlanConfirmModal").modal("hide");
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const editIntakePaymentPlan = (btn, id, confirmation = null) => {
    const data = {
        id: id,
        planName: $("#plan-name").val(),
        category: $("#payment-category").val(),
        plan: $("#payment-plan").val(),
        isDefault: $("#is-default").is(":checked"),
        paymentAmount: $("#payment-amount").val(),
        discountType: $("#plan-discount-type").val(),
        discountAmount: $("#plan-discount").val(),
        discountNote: $("#plan-discount-note").val(),
        payments: collectInstallmentData(),
        confirmation: confirmation
    }

    const validationMap = {
        name: ["#plan-name", "#plan-name-feedback"],
        discountNote: ['#plan-discount-note', "#plan-discount-note-feedback"],
        paymentAmount: ["#payment-amount", "#payment-amount-feedback"],
        discountType: ["#plan-discount-type", "#plan-discount-type-feedback"],
        discountAmount: ["#plan-discount", "#plan-discount-feedback"],
        category: ["#payment-category", "#payment-category-feedback"],
        plan: ["#payment-plan", "#payment-plan-feedback"],
    }

    fetch('api/editIntakePaymentPlan.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            $("#scheduleConfirmModal").modal('hide');
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.history.back(); }, 4000);
            } else if (response.error) {
                showToast("error", response.error);
            } else if (response.warning) {
                showToast("warning", response.warning);
            } else if (response.confirm_required) {

                const confirmModal = new bootstrap.Modal(document.getElementById("scheduleConfirmModal"));

                $("#confirmScheduleSave").off("click");
                $("#confirmScheduleSave").on("click", function () {
                    addIntakePaymentPlan(btn, id, true);
                });

                $("#scheduleConfirmModalCancel").off("click");
                $("#scheduleConfirmModalCancel").on("click", function () {
                    addIntakePaymentPlan(btn, id, false);
                });
                confirmModal.show();
                return;
            } else {
                applyValidation(response, validationMap);

                if (response.installments) {
                    generatePaymentErrorPDF(response);
                }
            }
        }).catch((error) => {
            console.error('Error:', error);
            showToast("error", "Something went wrong");
        });
}

const createStudentPaymentPlan = (btn, leadId) => {
    toggleBtnLoader(btn, true);
    const selectedPlan = $(".intake-plan-checkbox:checked");
    if (selectedPlan.length === 0) {
        toggleBtnLoader(btn, false);
        showToast("error", "Please select an intake payment plan");
        return;
    }

    console.log(collectMarkAsPaidPayments());

    const intakePaymentPlanId = selectedPlan.val();
    const data = {
        leadId: leadId,
        intakePaymentPlanId: intakePaymentPlanId,
        paidPayments: collectMarkAsPaidPayments()
    };

    const markAsPaidValidationMap = {
        paid_amount: ["#paid-amount", "#paid-amount-feedback"],
        discount: ["#discount", "#discount-feedback"],
        paid_date: ["#paid-date", "#paid-date-feedback"],
        payment_method_id: ["#paid-method", "#method-feedback"],
    };
    fetch('api/createStudentPaymentPlan', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => {
                    window.history.back();
                }, 3000);

            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, markAsPaidValidationMap);
            }
        }).catch((error) => {

            console.error('Error:', error);

            toggleBtnLoader(btn, false);

            showToast("error", "Something went wrong");
        });

};

const uploadOtherDocuments = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("id", id);
    form.append("removed_documents", removedExistingDocuments);
    appendStudentDocumentsToFormData(form);

    const validationMap = {
        aic_application: ["#doc-aic-application", "#doc-aic-application-feedback"],
        university_application: ["#doc-university-application", "#doc-university-application-feedback"],
        signed_agreement: ["#doc-signed-agreement", "#doc-signed-agreement-feedback"],
        signed_program_brief: ["#doc-signed-program-brief", "#doc-signed-program-brief-feedback"],
        passport_nic: ["#doc-passport-nic", "#doc-passport-nic-feedback"],
        academic_documents: ["#doc-academic-documents", "#doc-academic-documents-feedback"],
        other_entry_documents: ["#doc-other-entry-documents", "#doc-other-entry-documents-feedback"],
        payment_slip: ["#doc-payment-slip", "#doc-payment-slip-feedback"],
        manager_checklist: ["#doc-manager-checklist", "#doc-manager-checklist-feedback"],
        discount_approved_document: ["#doc-discount-approved-document", "#doc-discount-approved-document-feedback"],
        payment_option: ["#payment-option", "#payment-option-feedback"]
    };

    fetch("api/uploadOtherDocuments.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            applyValidation(response, validationMap, false);
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => window.location.reload(), 3000);
                return;
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const editRegistration = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("id", id);
    form.append("fname", $("#first-name").val());
    form.append("lname", $("#last-name").val());
    form.append("fullname", $("#full-name").val());
    form.append("nwi", $("#name-with-initials").val());
    form.append("title", $("#title-selector").val());
    form.append("gender", $("#gender-selector").val());
    form.append("marital_status", $("#marital-status-selector").val());
    form.append("nationality", $("#nationality").val());
    form.append("nic", $("#nic").val());
    form.append("passport", $("#passport").val());
    form.append("cob", $("#country-of-birth").val());
    form.append("cor", $("#country-of-residence").val());
    form.append("dob", $("#dob").val());
    form.append("email", $("#email").val());
    form.append("mobile", $("#mobile").val());
    form.append("whatsapp", $("#whatsapp").val());
    form.append("address", $("#address").val());
    form.append("address2", $("#address2").val());
    form.append("city", $("#city").val());
    form.append("pcode", $("#postal-code").val());
    form.append("image", $("#file-input").prop("files")[0]);
    form.append("branch", $("#branch-selector").val());
    form.append("faculty", $("#faculty-selector").val());
    form.append("programme", $("#programme-selector").val());
    form.append("intake", $("#intake-selector").val());
    form.append("grelationship", $("#grelationship-selector").val());
    form.append("gtitle", $("#gtitle-selector").val());
    form.append("gfname", $("#guardian-fname").val());
    form.append("glname", $("#guardian-lname").val());
    form.append("gemail", $("#guardian-email").val());
    form.append("gmobile", $("#guardian-mobile").val());
    form.append("gwhatsapp", $("#guardian-whatsapp").val());
    form.append("entry_type", $("input[name='entry_type']:checked").val());
    form.append("payment_option", $("#payment-option").val());
    form.append("removed_documents", removedExistingDocuments);
    appendStudentDocumentsToFormData(form);

    const validationMap = {
        id: ["#student-intake-id", "#student-intake-id-feedback"],
        fname: ["#first-name", "#fname-feedback"],
        lname: ["#last-name", "#lname-feedback"],
        fullname: ["#full-name", "#fullname-feedback"],
        nwi: ["#name-with-initials", "#nwi-feedback"],
        title: ["#title-selector", "#title-feedback"],
        gender: ["#gender-selector", "#gender-feedback"],
        marital_status: ["#marital-status-selector", "#marital-feedback"],
        nationality: ["#nationality", "#nationality-feedback"],
        cob: ["#country-of-birth", "#cob-feedback"],
        cor: ["#country-of-residence", "#cor-feedback"],
        nic: ["#nic", "#nic-feedback"],
        passport: ["#passport", "#passport-feedback"],
        dob: ["#dob", "#dob-feedback"],
        email: ["#email", "#email-feedback"],
        mobile: ["#mobile", "#mobile-feedback"],
        whatsapp: ["#whatsapp", "#whatsapp-feedback"],
        address: ["#address", "#address-feedback"],
        address2: ["#address2", "#address2-feedback"],
        city: ["#city", "#city-feedback"],
        pcode: ["#postal-code", "#postal-code-feedback"],
        faculty: ["#faculty-selector", "#faculty-feedback"],
        programme: ["#programme-selector", "#programme-feedback"],
        intake: ["#intake-selector", "#intake-feedback"],
        grelationship: ["#grelationship-selector", "#grelationship-feedback"],
        gtitle: ["#gtitle-selector", "#gtitle-feedback"],
        gfname: ["#guardian-fname", "#gfname-feedback"],
        glname: ["#guardian-lname", "#glname-feedback"],
        gemail: ["#guardian-email", "#gemail-feedback"],
        gmobile: ["#guardian-mobile", "#gmobile-feedback"],
        gwhatsapp: ["#guardian-whatsapp", "#gwhatsapp-feedback"],
        aic_application: ["#doc-aic-application", "#doc-aic-application-feedback"],
        university_application: ["#doc-university-application", "#doc-university-application-feedback"],
        signed_agreement: ["#doc-signed-agreement", "#doc-signed-agreement-feedback"],
        signed_program_brief: ["#doc-signed-program-brief", "#doc-signed-program-brief-feedback"],
        passport_nic: ["#doc-passport-nic", "#doc-passport-nic-feedback"],
        academic_documents: ["#doc-academic-documents", "#doc-academic-documents-feedback"],
        other_entry_documents: ["#doc-other-entry-documents", "#doc-other-entry-documents-feedback"],
        payment_slip: ["#doc-payment-slip", "#doc-payment-slip-feedback"],
        manager_checklist: ["#doc-manager-checklist", "#doc-manager-checklist-feedback"],
        discount_approved_document: ["#doc-discount-approved-document", "#doc-discount-approved-document-feedback"],
        payment_option: ["#payment-option", "#payment-option-feedback"]
    };

    fetch("api/editRegistration.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => window.location.reload(), 3000);
                return;
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const confirmAccountsCompletion = (btn) => {
    const studentId = document.getElementById("accountsConfirmStudentId").value;

    if (!studentId) {
        showToast("error", "Invalid student");
        return;
    }

    toggleBtnLoader(btn, true);
    const data = {
        student_id: studentId
    }

    fetch("api/confirmAccountsCompletion.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify(data)
    })
        .then(response => response.json())
        .then(data => {
            toggleBtnLoader(btn, false);
            if (data.done) {
                showToast("success", data.done);
                const modalEl = document.getElementById("accountsConfirmModal");
                const modal = bootstrap.Modal.getInstance(modalEl);
                modal.hide();

                setTimeout(() => {
                    window.location.reload();
                }, 3000);
            } else {
                showToast("error", data.error || "Something went wrong");
            }
        })
        .catch(error => {
            toggleBtnLoader(btn, false);
            console.error(error);
            showToast("error", "Request failed. Please try again.");
        });
}

function finalizeRegistrationVerification(btn) {

    const studentId = document.getElementById("registrationVerificationStudentId").value;

    if (!studentId) {
        showToast("error", "Invalid student");
        return;
    }

    toggleBtnLoader(btn, true);
    const data = {
        student_id: studentId
    }

    fetch("api/finalizeRegistrationVerification.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify(data)
    })
        .then(response => response.json())
        .then(data => {
            console.log(data);
            toggleBtnLoader(btn, false);
            if (data.done) {
                showToast("success", data.done);
                const modalEl = document.getElementById("registrationVerificationModal");
                const modal = bootstrap.Modal.getInstance(modalEl);

                if (modal) {
                    modal.hide();
                }
                setTimeout(() => {
                    window.location.reload();
                }, 800);

            } else if (data.error) {
                showToast("error", data.message || "Something went wrong");
            } else {
                showRegistrationVerificationError(
                    data.message || "Unable to finalize registration verification."
                );
            }

        })
        .catch(error => {
            console.error(error);
            toggleBtnLoader(btn, false);
            showToast(
                "error",
                "Request failed. Please try again."
            );
        });
}

const confirmAcademicCompletion = (btn) => {

    const studentId = document.getElementById("academicAdministrationStudentId").value;

    if (!studentId) {
        showToast("error", "Invalid student");
        return;
    }

    toggleBtnLoader(btn, true);
    const data = {
        student_id: studentId,
        ms_teams_username: $("#academicAdministrationTeamsUsername").val()
    }

    fetch("api/confirmAcademicCompletion.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify(data)
    })
        .then(response => response.json())
        .then(data => {
            toggleBtnLoader(btn, false);
            if (data.done) {
                showToast("success", data.done);
                const modalEl = document.getElementById("registrationVerificationModal");
                const modal = bootstrap.Modal.getInstance(modalEl);

                if (modal) {
                    modal.hide();
                }
                setTimeout(() => {
                    window.location.reload();
                }, 800);

            } else if (data.error) {
                showToast("error", data.error || "Something went wrong");
            } else {
                showRegistrationVerificationError(
                    data.message || "Unable to finalize registration verification."
                );
            }

        })
        .catch(error => {
            console.error(error);
            toggleBtnLoader(btn, false);
            showToast(
                "error",
                "Request failed. Please try again."
            );
        });
}

const filterStudentsInquiries = () => {
    const data = {
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        branch: $("#branch-selector").val(),
        faculty: $("#faculty-selector").val(),
        programme: $("#programme-selector").val(),
        intake: $("#intake-selector").val(),
        user: $("#user-selector").val(),
        paymentStatus: $("#payment-status-selector").val(),
        sortByName: sortCondition1,
        sortByDate: sortCondition2,
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,

    }

    fetch('api/filterStudentsInquiries.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.text())
        .then((text) => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#students-table-container").html(result);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.")
        });
}

const reuploadRegistrationPaymentSlip = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("id", id);
    appendStudentDocumentsToFormData(form);

    const validationMap = {
        payment_slip: ["#doc-payment-slip", "#doc-payment-slip-feedback"],
    };

    fetch("api/reuploadRegistrationPaymentSlip.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            applyValidation(response, validationMap, false);
            if (response.done) {
                toggleBtnLoader(btn, 'done');
                showToast("success", response.done);
                setTimeout(() => window.history.back(), 3000);
                return;
            } else if (response.error) {
                toggleBtnLoader(btn, false);
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
                toggleBtnLoader(btn, false);
            }
        })
        .catch((error) => {
            console.log(error);
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}

const rejectAcademicDocuments = (btn) => {
    const studentId = document.getElementById(
        "academicRejectStudentId"
    ).value;
    const noteInput = document.getElementById(
        "academicRejectNote"
    );
    const note = noteInput.value.trim();
    const feedback = document.getElementById(
        "academicRejectNote-feedback"
    );
    const errorArea = document.getElementById(
        "academicRejectErrorArea"
    );
    // reset
    noteInput.classList.remove("is-invalid");
    feedback.innerHTML = "";

    errorArea.classList.add("d-none");
    errorArea.innerHTML = "";
    // validation
    if (note === "") {
        noteInput.classList.add("is-invalid");
        feedback.innerHTML =
            "Rejection note is required";
        return;
    }

    toggleBtnLoader(btn, true);

    fetch("api/rejectAcademicDocuments.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({
            student_id: studentId,
            note: note
        })
    })
        .then(response => response.json())
        .then(data => {
            toggleBtnLoader(btn, false);
            if (data.done) {
                showToast("success", data.done);
                const modalEl = document.getElementById("registrationVerificationModal");
                const modal = bootstrap.Modal.getInstance(modalEl);

                if (modal) {
                    modal.hide();
                }
                setTimeout(() => {
                    window.location.reload();
                }, 800);

            } else if (data.error) {
                showToast("error", data.message || "Something went wrong");
            } else {
                showRegistrationVerificationError(
                    data.message || "Unable to finalize registration verification."
                );
            }

        })
        .catch(error => {
            console.error(error);
            toggleBtnLoader(btn, false);
            showToast(
                "error",
                "Request failed. Please try again."
            );
        });
}

const confirmAcademicDepartmentCompletion = (btn) => {

    const studentId = document.getElementById("academicDepartmentStudentId").value;

    if (!studentId) {
        showToast("error", "Invalid student");
        return;
    }

    toggleBtnLoader(btn, true);
    const data = {
        student_id: studentId
    }

    fetch("api/confirmAcademicDepartmentCompletion.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify(data)
    })
        .then(response => response.json())
        .then(data => {
            console.log(data);
            toggleBtnLoader(btn, false);
            if (data.done) {
                showToast("success", data.done);
                const modalEl = document.getElementById("academicDepartmentConfirmModal");
                const modal = bootstrap.Modal.getInstance(modalEl);

                if (modal) {
                    modal.hide();
                }
                setTimeout(() => {
                    window.location.reload();
                }, 800);

            } else if (data.error) {
                showToast("error", data.message || "Something went wrong");
            } else {
                showRegistrationVerificationError(
                    data.message || "Unable to finalize registration verification."
                );
            }

        })
        .catch(error => {
            console.error(error);
            toggleBtnLoader(btn, false);
            showToast(
                "error",
                "Request failed. Please try again."
            );
        });
}

const createCustomStudentPaymentPlan = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        id: id,
        category: $("#payment-category").val(),
        plan: $("#payment-plan").val(),
        paymentAmount: $("#payment-amount").val(),
        discountType: $("#plan-discount-type").val(),
        discountAmount: $("#plan-discount").val(),
        discountNote: $("#plan-discount-note").val(),
        payments: customPlanCollectInstallmentData(),

    }

    const validationMap = {
        name: ["#plan-name", "#plan-name-feedback"],
        discountNote: ['#plan-discount-note', "#plan-discount-note-feedback"],
        paymentAmount: ["#payment-amount", "#payment-amount-feedback"],
        discountType: ["#plan-discount-type", "#plan-discount-type-feedback"],
        discountAmount: ["#plan-discount", "#plan-discount-feedback"],
        category: ["#payment-category", "#payment-category-feedback"],
        plan: ["#payment-plan", "#payment-plan-feedback"],
    }

    fetch('api/createCustomStudentPaymentPlan.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.history.back(); }, 4000);
            } else if (response.error) {
                showToast("error", response.error);
            } else if (response.warning) {
                showToast("warning", response.warning);

            } else {
                applyValidation(response, validationMap);

                if (response.installments) {
                    generatePaymentErrorPDF(response);
                }
            }
        }).catch((error) => {
            console.error('Error:', error);
            showToast("error", "Something went wrong");
        });
}

const filterStudentsPayments = () => {
    const data = {
        text: $("#search-text").val(),
        status: $("#status-selector").val(),
        sort: $("#sort-selector").val(),
        year: $("#year").val(),
        month: $("#month").val(),
        branch: $("#branch-selector").val(),
        faculty: $("#faculty-selector").val(),
        programme: $("#programme-selector").val(),
        intake: $("#intake-selector").val(),
        category: $("#payment-category-selector").val(),
        plan: $("#payment-plan-selector").val(),
        type: $("#payment-type-selector").val(),
        sortByAmount: sortCondition1,
        sortByDate: sortCondition2,
        sortByName: sortCondition4,
        sortById: sortCondition3,
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterStudentsPayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#payments-table-container").html(data);
                getTableWidth("#payments-table", "#pagination-container")
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const addSystemPaymentPlan = (btn) => {
    const data = {
        planName: $("#plan-name").val(),
        category: $("#payment-category").val(),
        plan: $("#payment-plan").val(),
        isDefault: $("#is-default").is(":checked"),
        paymentAmount: $("#payment-amount").val(),
        discountType: $("#plan-discount-type").val(),
        discountAmount: $("#plan-discount").val(),
        discountNote: $("#plan-discount-note").val(),
        payments: collectInstallmentData(),

    }

    const validationMap = {
        name: ["#plan-name", "#plan-name-feedback"],
        discountNote: ['#plan-discount-note', "#plan-discount-note-feedback"],
        paymentAmount: ["#payment-amount", "#payment-amount-feedback"],
        discountType: ["#plan-discount-type", "#plan-discount-type-feedback"],
        discountAmount: ["#plan-discount", "#plan-discount-feedback"],
        category: ["#payment-category", "#payment-category-feedback"],
        plan: ["#payment-plan", "#payment-plan-feedback"],
    }

    fetch('api/addSystemPaymentPlan.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.history.back(); }, 4000);
            } else if (response.error) {
                showToast("error", response.error);
            } else if (response.warning) {
                showToast("warning", response.warning);
            } else {
                applyValidation(response, validationMap);

                if (response.installments) {
                    generatePaymentErrorPDF(response);
                }
            }
        }).catch((error) => {
            console.error('Error:', error);
            showToast("error", "Something went wrong");
        });
}


const submitUpdateTaxRate = (btn) => {
    const data = {
        taxRateId: $("#update-tax-id").val(),
        taxRateName: $("#update-tax-name").val(),
        taxRate: $("#update-tax-rate").val(),
        note: $("#update-tax-note").val(),
    };

    const validationMap = {
        taxRateName: ["#update-tax-name", "#update-tax-name-feedback"],
        taxRate: ["#update-tax-rate", "#update-tax-rate-feedback"],
        note: ["#update-tax-note", "#update-tax-note-feedback"],
    };

    toggleBtnLoader(btn, true);

    fetch('api/updateTaxRate.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.text())
        .then((response) => {
            console.log(response);
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);

            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { location.reload(); }, 2000);
            } else if (response.error) {
                showToast("error", response.error);
            } else if (response.warning) {
                showToast("warning", response.warning);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            console.error('Error:', error);
            toggleBtnLoader(btn, false);
            showToast("error", "Something went wrong");
        });
};

const editSystemPaymentPlan = (btn, id) => {
    const data = {
        id: id,
        planName: $("#plan-name").val(),
        category: $("#payment-category").val(),
        plan: $("#payment-plan").val(),
        isDefault: $("#is-default").is(":checked"),
        paymentAmount: $("#payment-amount").val(),
        discountType: $("#plan-discount-type").val(),
        discountAmount: $("#plan-discount").val(),
        discountNote: $("#plan-discount-note").val(),
        payments: collectInstallmentData(),
    }

    const validationMap = {
        name: ["#plan-name", "#plan-name-feedback"],
        discountNote: ['#plan-discount-note', "#plan-discount-note-feedback"],
        paymentAmount: ["#payment-amount", "#payment-amount-feedback"],
        discountType: ["#plan-discount-type", "#plan-discount-type-feedback"],
        discountAmount: ["#plan-discount", "#plan-discount-feedback"],
        category: ["#payment-category", "#payment-category-feedback"],
        plan: ["#payment-plan", "#payment-plan-feedback"],
    }

    fetch('api/editSystemPaymentPlan.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            $("#scheduleConfirmModal").modal('hide');
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.history.back(); }, 4000);
            } else if (response.error) {
                showToast("error", response.error);
            } else if (response.warning) {
                showToast("warning", response.warning);
            } else {
                applyValidation(response, validationMap);

                if (response.installments) {
                    generatePaymentErrorPDF(response);
                }
            }
        }).catch((error) => {
            console.error('Error:', error);
            showToast("error", "Something went wrong");
        });
}

const removeSystemPaymentPlan = (id) => {
    toggleLoader(true);
    const data = {
        plan: id
    }
    fetch('api/removeSystemPaymentPlan.php', {
        method: "POST",
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                $("#removeSystemPaymentPlanConfirmModal").modal("hide");
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}


const duplicateIntake = (btn, programme) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("intake_id", $("#intake-selector").val());
    form.append("programme_id", programme);
    form.append("intake_name", $("#duplicate-name").val());
    form.append("intake_cover", $("#duplicate-cover-input").prop("files")[0]);

    const validationMap = {
        intake: ["#intake-selector", "#intake-selector-feedback"],
        name: ["#intake_name", "#intake_name-feedback"],
        start: ["#duplicate-start-date", "#duplicate-start-date-feedback"],
        end: ["#duplicate-end-date", "#duplicate-end-date-feedback"],
        cover: ["#duplicate-cover-input", "#duplicate-cover-input-feedback"]
    }

    fetch('api/duplicateIntake.php', {
        method: "POST",
        body: form
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            if (response.done) {
                $("#duplicateIntake").modal('hide');
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            } else if (response.error) {
                showToast("error", response.error);
            } else {
                applyValidation(response, validationMap);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const uploadInquiryStudentPaymentReceipt = (btn, id) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("payment_id", id);
    form.append("note", $("#note").val());
    var files = $("#file-selector-input")[0].files;
    for (var i = 0; i < files.length; i++) {
        form.append("slips[]", files[i]);
    }

    const validationMap = {
        amount: ["#paid-amount", "#paid-amount-feedback"],
        method: ["#payment-method", "#payment-method-feedback"],
        discount: ["#discount-amount", "#discount-amount-feedback"],
        penalty: ["#penalty-amount", "#penalty-amount-feedback"],
        note: ["#note", "#note-feedback"],
    }

    fetch("api/uploadInquiryStudentPaymentReceipt.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.json())
        .then((response) => {
            toggleBtnLoader(btn, false);
            applyValidation('', validationMap, false);
            if (response.done) {
                showToast("success", response.done);
                setTimeout(() => { window.history.back(); }, 3000);
                setTimeout(() => window.location.reload(), 4000);
                return;
            } else if (response.error) {
                showToast("error", response.error);
            } else if (response.warning) {
                toggleBtnLoader(btn, false);
                showToast("warning", response.warning);
            } else {
                applyValidation(response, validationMap);

                if (response.slip) {
                    const slipErrorContainer = $("#file-feedback");
                    if (Array.isArray(response.slip)) {
                        const slipArray = response.slip; // Get the actual array
                        $("#file-feedback").html("");
                        slipErrorContainer.append('<p class="text-14 text-danger fw-bold">Upload Payment Slip Error Messages : </p>');
                        slipArray.forEach(function (item) {
                            slipErrorContainer.append('<p class="text-12 text-danger">*' + item + '</p>');
                        });
                    } else {
                        slipErrorContainer.append('<p class="text-12 text-danger">*' + response.slip + '</p>');
                    }

                } else {
                    $("#file-feedback").html("");
                }
            }
        })
        .catch((error) => {
            console.log(error);
            toggleBtnLoader(btn, false);
            showToast("error", "An error occured. Please try again later.");
        });
}


// const addNewPaymentPlan = (btn, confirmation = null) => {
//     const data = {
//         branch: $("#branch-selector").val(),
//         faculty: $("#faculty-selector").val(),
//         programmeId: $("#programme-selector").val(),
//         intakeId: $("#intake-selector").val(),
//         planName: $("#plan-name").val(),
//         category: $("#payment-category").val(),
//         plan: $("#payment-plan").val(),
//         isDefault: $("#is-default").is(":checked"),
//         paymentAmount: $("#payment-amount").val(),
//         discountType: $("#plan-discount-type").val(),
//         discountAmount: $("#plan-discount").val(),
//         discountNote: $("#plan-discount-note").val(),
//         payments: collectInstallmentData(),
//         confirmation: confirmation
//     }

//     const validationMap = {
//         branch: ["#branch-selector", "#branch-feedback"],
//         faculty: ["#faculty-selector", "#faculty-feedback"],
//         programme: ["#programme-selector", "#programme-feedback"],
//         intake: ["#intake-selector", "#intake-feedback"],
//         name: ["#plan-name", "#plan-name-feedback"],
//         discountNote: ['#plan-discount-note', "#plan-discount-note-feedback"],
//         paymentAmount: ["#payment-amount", "#payment-amount-feedback"],
//         discountType: ["#plan-discount-type", "#plan-discount-type-feedback"],
//         discountAmount: ["#plan-discount", "#plan-discount-feedback"],
//         category: ["#payment-category", "#payment-category-feedback"],
//         plan: ["#payment-plan", "#payment-plan-feedback"],
//     }

//     fetch('api/addNewPaymentPlan.php', {
//         method: "POST",
//         headers: {
//             "Content-Type": "application/json",
//         },
//         body: JSON.stringify(data),
//     })
//         .then((res) => res.json())
//         .then((response) => {
//             console.log(response);
//             toggleBtnLoader(btn, false);
//             applyValidation('', validationMap, false);
//             $("#scheduleConfirmModal").modal('hide');
//             if (response.done) {
//                 showToast("success", response.done);
//                 setTimeout(() => { window.history.back(); }, 4000);
//             } else if (response.error) {
//                 showToast("error", response.error);
//             } else if (response.warning) {
//                 showToast("warning", response.warning);
//             } else if (response.confirm_required) {

//                 const confirmModal = new bootstrap.Modal(document.getElementById("scheduleConfirmModal"));

//                 $("#confirmScheduleSave").off("click");
//                 $("#confirmScheduleSave").on("click", function () {
//                     addIntakePaymentPlan(btn, id, true);
//                 });

//                 $("#scheduleConfirmModalCancel").off("click");
//                 $("#scheduleConfirmModalCancel").on("click", function () {
//                     addIntakePaymentPlan(btn, id, false);
//                 });
//                 confirmModal.show();
//                 return;
//             } else {
//                 applyValidation(response, validationMap);

//                 if (response.installments) {
//                     generatePaymentErrorPDF(response);
//                 }
//             }
//         }).catch((error) => {
//             console.error('Error:', error);
//             showToast("error", "Something went wrong");
//         });
// }

const filterPaymentPlans = () => {
    const data = {
        text: $("#user-search-text").val() || "",
        faculty: $("#faculty-selector").val(),
        programmeId: $("#programme-selector").val(),
        intakeId: $("#intake-selector").val(),
        resultPerPage: $("#show-result-selector").val() || 10,
        page: selectedPage || 1,
    }

    fetch('api/filterPaymentPlans.php', {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
    })
        .then(res => res.text())
        .then(text => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#accordionParent").html(result);
            }
        })
        .catch(error => {
            console.error("Filter users failed:", error);
            showToast("error", "An error occurred. Please try again later.");
        });
}

const removeLecture = (id) => {
    toggleLoader(true);
    const data = {
        id: id,
    }
    fetch('api/removeLecture.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.json())
        .then((response) => {
            toggleLoader(false);
            if (response.done) {
                $("#removeLectureConfirmModal").modal('hide');
                $("#lectureDetailsModal").modal('hide');
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload() }, 4000);
            } else if (response.error) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const genarateStudentPaymentSummaryReport = (btn) => {
    toggleBtnLoader(btn, true);
    const data = {
        text: $("#search-text").val(),
        branch: $("#branch-selector").val(),
        faculty: $("#faculty-selector").val(),
        programme: $("#programme-selector").val(),
        intake: $("#intake-selector").val(),
        filter: $("#filter-selector").val(),
        from: $('#from-date').val(),
        to: $("#to-date").val(),
    }


    fetch('api/genarateStudentPaymentSummaryReport.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then(async (response) => {
            const contentType = response.headers.get('content-type');
            console.log('HTTP Status:', response.status);
            console.log('Content-Type:', contentType);

            if (contentType && contentType.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
                return response.blob();
            }

            if (contentType && contentType.includes('application/json')) {
                return response.json();
            }

            const text = await response.text();
            console.log('Raw response:', text);
            throw new Error('Unexpected response type.');
        })
        .then((data) => {
            toggleBtnLoader(btn, false);
            if (data instanceof Blob) {
                const url = window.URL.createObjectURL(data);
                const a = document.createElement('a');
                const now = new Date();

                const pad = (num) => String(num).padStart(2, '0');

                const dateTime =
                    `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}_` +
                    `${pad(now.getHours())}-${pad(now.getMinutes())}-${pad(now.getSeconds())}`;

                a.href = url;
                a.download = `Students Payment Summary Report (${dateTime}).xlsx`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
            } else {
                showToast("error", data.error || "Unknown error");
            }
        })
        .catch((error) => {
            console.error(error);
            showToast("error", error.message);
        });
}

const filterStudentsPaymentsSummaryReport = () => {
    const data = {
        text: $("#search-text").val(),
        status: $("#status-selector").val(),
        branch: $("#branch-selector").val(),
        faculty: $("#faculty-selector").val(),
        programme: $("#programme-selector").val(),
        intake: $("#intake-selector").val(),
        filter: $("#filter-selector").val(),
        from: $('#from-date').val(),
        to: $("#to-date").val(),
        sortByAmount: sortCondition1,
        sortByDate: sortCondition2,
        sortByName: sortCondition4,
        sortById: sortCondition3,
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterStudentsPaymentsSummaryReport.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#payments-table-container").html(data);
                getTableWidth("#payments-table", "#pagination-container")
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const genarateStudentsOutstandingBalancesReport = (btn) => {
    toggleBtnLoader(btn, true);
    const data = {
        text: $("#search-text").val(),
        branch: $("#branch-selector").val(),
        faculty: $("#faculty-selector").val(),
        programme: $("#programme-selector").val(),
        intake: $("#intake-selector").val(),
        filter: $("#filter-selector").val(),
        from: $('#from-date').val(),
        to: $("#to-date").val(),
    }


    fetch('api/genarateStudentsOutstandingBalancesReport.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then(async (response) => {
            const contentType = response.headers.get('content-type');
            console.log('HTTP Status:', response.status);
            console.log('Content-Type:', contentType);

            if (contentType && contentType.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
                return response.blob();
            }

            if (contentType && contentType.includes('application/json')) {
                return response.json();
            }

            const text = await response.text();
            console.log('Raw response:', text);
            throw new Error('Unexpected response type.');
        })
        .then((data) => {
            toggleBtnLoader(btn, false);
            if (data instanceof Blob) {
                const url = window.URL.createObjectURL(data);
                const a = document.createElement('a');
                const now = new Date();

                const pad = (num) => String(num).padStart(2, '0');

                const dateTime =
                    `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}_` +
                    `${pad(now.getHours())}-${pad(now.getMinutes())}-${pad(now.getSeconds())}`;

                a.href = url;
                a.download = `Students Payment Summary Report (${dateTime}).xlsx`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
            } else {
                showToast("error", data.error || "Unknown error");
            }
        })
        .catch((error) => {
            console.error(error);
            showToast("error", error.message);
        });
}

const filterStudentsOutstandingBalancesReport = () => {
    const data = {
        text: $("#search-text").val(),
        status: $("#status-selector").val(),
        branch: $("#branch-selector").val(),
        faculty: $("#faculty-selector").val(),
        programme: $("#programme-selector").val(),
        intake: $("#intake-selector").val(),
        filter: $("#filter-selector").val(),
        from: $('#from-date').val(),
        to: $("#to-date").val(),
        sortByAmount: sortCondition1,
        sortByDate: sortCondition2,
        sortByName: sortCondition4,
        sortById: sortCondition3,
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterStudentsOutstandingBalancesReport.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#payments-table-container").html(data);
                getTableWidth("#payments-table", "#pagination-container")
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}
const filterInquiryRegistrationPayments = () => {

    const data = {
        text: $("#search-text").val(),
        branch: $("#branch-selector").val(),
        faculty: $("#faculty-selector").val(),
        programme: $("#programme-selector").val(),
        intake: $("#intake-selector").val(),
        sort: $("#sort-selector").val(),
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage
    }

    console.log(data);

    fetch(`api/filterInquiryRegistrationPayments.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#students-table-container").html(data);
            }

        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });

}

const genarateLatePaymentsReport = (btn) => {
    toggleBtnLoader(btn, true);
    const data = {
        text: $("#search-text").val(),
        branch: $("#branch-selector").val(),
        faculty: $("#faculty-selector").val(),
        programme: $("#programme-selector").val(),
        intake: $("#intake-selector").val(),
        filter: $("#filter-selector").val(),
        from: $('#from-date').val(),
        to: $("#to-date").val(),
    }


    fetch('api/genarateLatePaymentsReport.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then(async (response) => {
            const contentType = response.headers.get('content-type');
            console.log('HTTP Status:', response.status);
            console.log('Content-Type:', contentType);

            if (contentType && contentType.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
                return response.blob();
            }

            if (contentType && contentType.includes('application/json')) {
                return response.json();
            }

            const text = await response.text();
            console.log('Raw response:', text);
            throw new Error('Unexpected response type.');
        })
        .then((data) => {
            toggleBtnLoader(btn, false);
            if (data instanceof Blob) {
                const url = window.URL.createObjectURL(data);
                const a = document.createElement('a');
                const now = new Date();

                const pad = (num) => String(num).padStart(2, '0');

                const dateTime =
                    `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}_` +
                    `${pad(now.getHours())}-${pad(now.getMinutes())}-${pad(now.getSeconds())}`;

                a.href = url;
                a.download = `Students Payment Summary Report (${dateTime}).xlsx`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
            } else {
                showToast("error", data.error || "Unknown error");
            }
        })
        .catch((error) => {
            console.error(error);
            showToast("error", error.message);
        });
}


const submitUpdatePaymentStatus = (btn, id, paid_id, currency_data = null) => {
    toggleBtnLoader(btn, true);
    const form = new FormData();
    form.append("payment_id", id);
    form.append("paid_payment_id", paid_id);
    form.append("payment_status", $("#status-selector").val());
    form.append("discount_amount", $("#discount-amount").val());
    form.append("penalty_amount", $("#penalty-amount").val());
    form.append("method", $("#payment-method").val());
    form.append("paid_amount", $("#paid-amount").val()); // ← Tax-inclusive amount
    form.append("paid_date", $("#paid-date").val());
    form.append("balance", $("#balance-amount").val()); // ← For validation
    form.append("note", $("#note").val());


    console.log(currency_data);
    // Append currency data
    if (currency_data != null) {
        form.append("currency", JSON.stringify(currency_data));
    }


    // Validation mapping
    const validationMap = {
        amount: ["#paid-amount", "#paid-amount-feedback"],
        method: ["#payment-method", "#payment-method-feedback"],
        discount: ["#discount-amount", "#discount-amount-feedback"],
        penalty: ["#penalty-amount", "#penalty-amount-feedback"],
        paid_date: ["#paid-date", "#paid-date-feedback"],
        note: ["#note", "#note-feedback"],
    };

    // ════════════════════════════════════════════════════════════════════════════
    // SUBMIT FORM
    // ════════════════════════════════════════════════════════════════════════════

    fetch("api/submitUpdatePaymentStatus.php", {
        method: "POST",
        body: form,
    })
        .then((res) => res.text())
        .then((responseText) => {
            toggleBtnLoader(btn, false);

            try {
                // Parse JSON response
                var response = JSON.parse(responseText);
                console.log('✅ Payment Receipt Response:', response);

                // Clear previous validation errors
                applyValidation('', validationMap, false);

                // ════════════════════════════════════════════════════════════
                // SUCCESS CASE
                // ════════════════════════════════════════════════════════════
                if (response.done) {
                    // Show success toast with details
                    $("#updatePaymentStatus").modal('hide');
                    let successMsg = '✅ ' + response.done;

                    if (response.receiptNumber) {
                        successMsg += '<br>Receipt: ' + response.receiptNumber;
                    }

                    if (response.overpayment && response.overpayment.detected) {
                        successMsg += '<br>⚠️ Overpayment detected: Applied to ' + response.overpayment.nextInstallments + ' next installment(s)';
                    }

                    showToast("success", successMsg);

                    // Redirect after brief delay
                    setTimeout(() => {
                        window.location.reload()
                    }, 2500);

                    return;
                }

                // ════════════════════════════════════════════════════════════
                // ERROR CASE
                // ════════════════════════════════════════════════════════════
                if (response.error) {
                    // Check if this is an overpayment error that needs modal
                    if (response.errorType === 'OVERPAYMENT_NO_NEXT_INSTALLMENT' && response.errorDetails) {
                        showOverpaymentErrorModal(response);
                    } else {
                        showToast("error", "❌ " + response.error);
                    }
                    return;
                }

                if (response.warning) {
                    showToast("warning", "⚠️ " + response.warning);
                    return;
                }

                // ════════════════════════════════════════════════════════════
                // VALIDATION ERRORS
                // ════════════════════════════════════════════════════════════

                // Apply field-level validation errors
                applyValidation(response, validationMap);

                // Handle file slip errors separately
                if (response.slip) {
                    const slipErrorContainer = $("#file-feedback");
                    slipErrorContainer.html("");

                    if (Array.isArray(response.slip)) {
                        slipErrorContainer.append(
                            '<p class="text-14 text-danger fw-bold">⚠️ Upload Errors:</p>'
                        );
                        response.slip.forEach(item => {
                            slipErrorContainer.append(
                                '<p class="text-12 text-danger" style="margin:4px 0;">• ' + item + '</p>'
                            );
                        });
                    } else {
                        slipErrorContainer.append(
                            '<p class="text-12 text-danger">• ' + response.slip + '</p>'
                        );
                    }

                    // Highlight the file input area
                    $("#drop-section").addClass("border-danger");
                } else {
                    $("#file-feedback").html("");
                    $("#drop-section").removeClass("border-danger");
                }

            } catch (parseErr) {
                console.error('❌ JSON Parse Error:', parseErr);
                console.log('Raw response:', responseText);
                showToast("error", "Invalid response from server. Please try again.");
            }
        })
        .catch((error) => {
            console.error('❌ Network Error:', error);
            toggleBtnLoader(btn, false);
            showToast("error", "Network error occurred. Please check your connection and try again.");
        });
};

const genarateCurrencyExchangeRateReport = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        currency: id,
        text: $("#user-search-text").val() || "",
        fromDate: $("#from-date").val() || "",
        toDate: $("#to-date").val() || "",
    }


    fetch('api/genarateCurrencyExchangeRateReport.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then(async (response) => {
            const contentType = response.headers.get('content-type');
            console.log('HTTP Status:', response.status);
            console.log('Content-Type:', contentType);

            if (contentType && contentType.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
                return response.blob();
            }

            if (contentType && contentType.includes('application/json')) {
                return response.json();
            }

            const text = await response.text();
            console.log('Raw response:', text);
            throw new Error('Unexpected response type.');
        })
        .then((data) => {
            toggleBtnLoader(btn, false);
            if (data instanceof Blob) {
                const url = window.URL.createObjectURL(data);
                const a = document.createElement('a');
                const now = new Date();

                const pad = (num) => String(num).padStart(2, '0');

                const dateTime =
                    `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}_` +
                    `${pad(now.getHours())}-${pad(now.getMinutes())}-${pad(now.getSeconds())}`;

                a.href = url;
                a.download = `Currency Exchange Rate Report (${dateTime}).xlsx`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
            } else {
                showToast("error", data.error || "Unknown error");
            }
        })
        .catch((error) => {
            console.error(error);
            showToast("error", error.message);
        });
}

const filterDuePayments = () => {
    const data = {
        text: $("#search-text").val(),
        branch: $("#branch-selector").val(),
        faculty: $("#faculty-selector").val(),
        programme: $("#programme-selector").val(),
        intake: $("#intake-selector").val(),
        category: $("#payment-category-selector").val(),
        plan: $("#payment-plan-selector").val(),
        type: $("#payment-type-selector").val(),
        sortByAmount: sortCondition1,
        sortByDate: sortCondition2,
        sortByName: sortCondition3,
        sortById: sortCondition4,
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage2,
    }

    fetch('api/filterDuePayments.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((response) => response.text())
        .then((data) => {

            try {
                const response = JSON.parse(data);
                if (response.hasOwnProperty('error')) {
                    setTimeout(() => { showToast("error", response.error); }, 1000);
                }
            } catch (e) {
                $("#payments-table-container").html(data);
            }
        })
        .catch((error) => {
            console.log(error);
            showToast("error", "An error occured. Please try again later.");
        });
}

const genarateStudentRegistrationsReport = (btn) => {
    toggleBtnLoader(btn, true);
    const data = {
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        branch: $("#branch-selector").val(),
        faculty: $("#faculty-selector").val(),
        programme: $("#programme-selector").val(),
        intake: $("#intake-selector").val(),
        user: $("#user-selector").val(),
        paymentStatus: $("#payment-status-selector").val(),
    }

    fetch('api/genarateStudentRegistrationsReport.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then(async (response) => {
            const contentType = response.headers.get('content-type');
            console.log('HTTP Status:', response.status);
            console.log('Content-Type:', contentType);

            if (contentType && contentType.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
                return response.blob();
            }

            if (contentType && contentType.includes('application/json')) {
                return response.json();
            }

            const text = await response.text();
            console.log('Raw response:', text);
            throw new Error('Unexpected response type.');
        })
        .then((data) => {
            toggleBtnLoader(btn, false);
            if (data instanceof Blob) {
                const url = window.URL.createObjectURL(data);
                const a = document.createElement('a');
                const now = new Date();

                const pad = (num) => String(num).padStart(2, '0');

                const dateTime =
                    `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}_` +
                    `${pad(now.getHours())}-${pad(now.getMinutes())}-${pad(now.getSeconds())}`;

                a.href = url;
                a.download = `Students Registrations Report (${dateTime}).xlsx`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
            } else {
                showToast("error", data.error || "Unknown error");
            }
        })
        .catch((error) => {
            console.error(error);
            showToast("error", error.message);
        });
}
const removeLectureRecording = (btn, id) => {
    toggleBtnLoader(btn, true);
    const data = {
        recording: id
    }
    fetch('api/removeLectureRecording.php', {
        method: "POST",
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            toggleBtnLoader(btn, false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                showToast("error", response.error);
            } else if (response.hasOwnProperty('done')) {
                $("#removeLectureRecordingConfirmModal").modal("hide");
                showToast("success", response.done);
                setTimeout(() => { window.location.reload(); }, 3000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const filterCollectionReport = () => {
    const data = {
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        branch: $("#branch-selector").val(),
        faculty: $("#faculty-selector").val(),
        programme: $("#programme-selector").val(),
        intake: $("#intake-selector").val(),
        dateFilter: $("filter-selector").val(),
        fromDate: $("#from-date").val(),
        toDate: $("#to-date").val(),
        sortByName: sortCondition1,
        sortByDate: sortCondition2,
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterCollectionReport.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.text())
        .then((text) => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#collection-table-container").html(result);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.")
        });
}


const genarateCollectionReport = (btn) => {
    toggleBtnLoader(btn, true);
    const data = {
        text: $("#search-text").val(),
        sort: $("#sort-selector").val(),
        branch: $("#branch-selector").val(),
        faculty: $("#faculty-selector").val(),
        programme: $("#programme-selector").val(),
        intake: $("#intake-selector").val(),
        dateFilter: $("filter-selector").val(),
        fromDate: $("#from-date").val(),
        toDate: $("#to-date").val(),

    }


    fetch('api/genarateCollectionReport.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then(async (response) => {
            const contentType = response.headers.get('content-type');
            console.log('HTTP Status:', response.status);
            console.log('Content-Type:', contentType);

            if (contentType && contentType.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
                return response.blob();
            }

            if (contentType && contentType.includes('application/json')) {
                return response.json();
            }

            const text = await response.text();
            console.log('Raw response:', text);
            throw new Error('Unexpected response type.');
        })
        .then((data) => {
            toggleBtnLoader(btn, false);
            if (data instanceof Blob) {
                const url = window.URL.createObjectURL(data);
                const a = document.createElement('a');
                const now = new Date();

                const pad = (num) => String(num).padStart(2, '0');

                const dateTime =
                    `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}_` +
                    `${pad(now.getHours())}-${pad(now.getMinutes())}-${pad(now.getSeconds())}`;

                a.href = url;
                a.download = `Collection Report (${dateTime}).xlsx`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
            } else {
                showToast("error", data.error || "Unknown error");
            }
        })
        .catch((error) => {
            console.error(error);
            showToast("error", error.message);
        });
}

const filterDueReport = () => {
    const data = {
        text: $("#search-text").val(),
        branch: $("#branch-selector").val(),
        faculty: $("#faculty-selector").val(),
        programme: $("#programme-selector").val(),
        intake: $("#intake-selector").val(),
        counsellor: $("#counsellor-selector").val(),
        currency: $("#currency-selector").val(),
        status: $("#status-selector").val(),
        dateFilter: $("#filter-selector").val(),
        fromDate: $("#from-date").val(),
        toDate: $("#to-date").val(),
        sortByName: sortCondition1,
        sortByDue: sortCondition2,
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterDueReport.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.text())
        .then((text) => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#due-table-container").html(result);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.")
        });
}

const genarateDueReport = (btn) => {
    toggleBtnLoader(btn, true);
    const data = {
        text: $("#search-text").val(),
        branch: $("#branch-selector").val(),
        faculty: $("#faculty-selector").val(),
        programme: $("#programme-selector").val(),
        intake: $("#intake-selector").val(),
        counsellor: $("#counsellor-selector").val(),
        currency: $("#currency-selector").val(),
        status: $("#status-selector").val(),
        dateFilter: $("#filter-selector").val(),
        fromDate: $("#from-date").val(),
        toDate: $("#to-date").val(),
    }


    fetch('api/genarateDueReport.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then(async (response) => {
            const contentType = response.headers.get('content-type');
            console.log('HTTP Status:', response.status);
            console.log('Content-Type:', contentType);

            if (contentType && contentType.includes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')) {
                return response.blob();
            }

            if (contentType && contentType.includes('application/json')) {
                return response.json();
            }

            const text = await response.text();
            console.log('Raw response:', text);
            throw new Error('Unexpected response type.');
        })
        .then((data) => {
            toggleBtnLoader(btn, false);
            if (data instanceof Blob) {
                const url = window.URL.createObjectURL(data);
                const a = document.createElement('a');
                const now = new Date();

                const pad = (num) => String(num).padStart(2, '0');

                const dateTime =
                    `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}_` +
                    `${pad(now.getHours())}-${pad(now.getMinutes())}-${pad(now.getSeconds())}`;

                a.href = url;
                a.download = `Due Report (${dateTime}).xlsx`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
            } else {
                showToast("error", data.error || "Unknown error");
            }
        })
        .catch((error) => {
            showToast("error", error.message || "An error occured. Please try again later.");
        })

}

const filterPerformanceUsers = () => {
    const data = {
        text: $("#user-search-text").val(),
        branch: $("#branch-selector").val(),
        faculty: $("#faculty-selector").val(),
        programme: $("#programme-selector").val(),
        intake: $("#intake-selector").val(),
        sortByName: sortCondition1,
        resultPerPage: $("#show-result-selector").val(),
        page: selectedPage,
    }

    fetch('api/filterPerformance.php', {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(data),
    })
        .then((res) => res.text())
        .then((text) => {
            let result;
            try {
                result = JSON.parse(text);
            } catch {
                result = text;
            }

            if (typeof result === "object" && result.error) {
                showToast("error", result.error);
            } else {
                $("#users-table-container").html(result);

                const countMarker = $("#total-count-marker");
                if (countMarker.length) {
                    $("#user-count-label").html(countMarker.data("count") + " <span data-i18n=\"users\"></span>");
                    countMarker.remove();
                }
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.")
        });
}

const removeBranch = (id) => {
    toggleLoader(true);
    const data = {
        branch: id
    }
    fetch('api/removeBranch.php', {
        method: "POST",
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                $("#removeBranchConfirmModal").modal("hide");
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}


const removeFaculty = (id) => {
    toggleLoader(true);
    const data = {
        faculty: id
    }
    fetch('api/removeFaculty.php', {
        method: "POST",
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                $("#removeFacultyConfirmModal").modal("hide");
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const removeProgramme = (id) => {
    toggleLoader(true);
    const data = {
        programme: id
    }
    fetch('api/removeProgramme.php', {
        method: "POST",
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                $("#removeProgrammeConfirmModal").modal("hide");
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

const removeIntake = (id) => {
    toggleLoader(true);
    const data = {
        intake: id
    }
    fetch('api/removeIntake.php', {
        method: "POST",
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
        .then((response) => response.text())
        .then((data) => {
            console.log(data);
            toggleLoader(false);
            const response = JSON.parse(data);
            if (response.hasOwnProperty('error')) {
                setTimeout(() => { showToast("error", response.error); }, 1000);
            } else if (response.hasOwnProperty('done')) {
                $("#removeBatchConfirmModal").modal("hide");
                setTimeout(() => { showToast("success", response.done); }, 1000);
                setTimeout(() => { window.location.reload(); }, 4000);
            }
        })
        .catch((error) => {
            showToast("error", "An error occured. Please try again later.");
        });
}

