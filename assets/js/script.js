const BASE_STUDENT_PORTAL_URL = "https://portal.aiccampuslms.com";
const SORT_NONE = 0;
const SORT_ASC = 1;
const SORT_DESC = 2;

let selectedPage = 1;
let selectedPage2 = 1;
let sortDirection = 0;
let sortCondition1 = 0;
let sortCondition2 = 0;
let sortCondition3 = 0;
let sortCondition4 = 0;
let selectedUsers = [];
let unSelectedUsers = [];

$(window).on('load', function () {
    document.body.style.opacity = '1';
    // initializeTheme();
    initializeTheme2();
    initializeLanguage2();
    renderSearchHistory();
    toggleSkeleton();

    const toggleBtn = $('#toggleMenu');
    const sideMenu = $('#sideMenu');
    const appWrapper = $('#app-wrapper');

    const dropdown = document.querySelector('.header-ud-toggle-area');
    const dropdownMenu = new bootstrap.Dropdown(dropdown);
    let timeout;

    dropdown.parentElement.addEventListener('mouseenter', function () {
        clearTimeout(timeout);
        dropdownMenu.show();
    });

    dropdown.parentElement.addEventListener('mouseleave', function () {
        timeout = setTimeout(() => dropdownMenu.hide(), 300);
    });

    // Check if elements exist
    if (toggleBtn.length && sideMenu.length && appWrapper.length) {
        // Initialize menu state from localStorage
        const menuVisible = localStorage.getItem('menuVisible') !== 'false';
        setMenuState(menuVisible);

        // Toggle menu on click
        toggleBtn.on('click', function () {
            const isCurrentlyVisible = !sideMenu.hasClass('d-xl-block');
            setMenuState(!isCurrentlyVisible);
        });

        function setMenuState(visible) {
            //console.log(visible);
            if (visible) {
                // Show menu
                sideMenu.removeClass('d-xl-block');
                if (appWrapper.hasClass("app-wrapper") == true) {
                    appWrapper.removeClass("app-wrapper");
                    appWrapper.addClass("app-wrapper-full");
                }
            } else {
                // Hide menu
                sideMenu.addClass('d-xl-block');
                if (appWrapper.hasClass("app-wrapper") == false) {
                    appWrapper.removeClass("app-wrapper-full");
                    appWrapper.addClass("app-wrapper");
                }
            }
            localStorage.setItem('menuVisible', visible);
        }
    } else {
        console.error('Required elements not found');
    }

});

const initializeTheme = () => {
    // Get theme elements
    const htmlElement = document.documentElement;
    const themeItems = document.querySelectorAll('.theme-item');
    const lightIcon = document.querySelector('.light-theme-icon');
    const darkIcon = document.querySelector('.dark-theme-icon');

    // Theme setting function
    const setTheme = (theme) => {
        // Update UI icons
        if (theme === 'light') {
            lightIcon?.classList.remove('d-none');
            darkIcon?.classList.add('d-none');
        } else {
            lightIcon?.classList.add('d-none');
            darkIcon?.classList.remove('d-none');
        }

        // Update attributes
        htmlElement.setAttribute('data-bs-theme', theme);
        htmlElement.setAttribute('data-custom-theme', theme);

        // Save to localStorage (persists across tabs and sessions)
        localStorage.setItem('theme', theme);

        // Update dropdown UI
        themeItems.forEach(item => {
            const checkIcon = item.querySelector('.theme-check');
            const isActive = item.getAttribute('data-theme') === theme;
            item.classList.toggle('active-theme', isActive);
            checkIcon.style.display = isActive ? 'block' : 'none';
        });
    };

    const initThemeUI = () => {
        const storedTheme = localStorage.getItem('theme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: light)').matches;
        const initialTheme = storedTheme || (systemPrefersDark ? 'dark' : 'light');

        setTheme(initialTheme);
        document.body.style.opacity = '1'; // Fade in content
    };

    themeItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            setTheme(e.currentTarget.getAttribute('data-theme'));
        });
    });

    window.addEventListener('storage', (event) => {
        if (event.key === 'theme') {
            setTheme(event.newValue);
        }
    });

    initThemeUI();
};

const initializeTheme2 = () => {
    const htmlElement = document.documentElement;
    const themeButtons = document.querySelectorAll('.theme-toggle-btn');
    const lightBtn = document.getElementById('lightThemeBtn');
    const darkBtn = document.getElementById('darkThemeBtn');

    const setTheme = (theme) => {
        // Update HTML attributes
        htmlElement.setAttribute('data-bs-theme', theme);
        htmlElement.setAttribute('data-custom-theme', theme);

        // Save in local storage
        localStorage.setItem('theme', theme);

        // Update active button UI
        if (theme === 'light') {
            lightBtn.classList.add('btn-primary');
            darkBtn.classList.remove('btn-primary');
        } else {
            darkBtn.classList.add('btn-primary');
            lightBtn.classList.remove('btn-primary');
        }
    };

    const initThemeUI = () => {
        const storedTheme = localStorage.getItem('theme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        // If no stored theme → follow system
        const initialTheme = storedTheme || (systemPrefersDark ? 'dark' : 'light');

        setTheme(initialTheme);
    };

    // Listen for clicks
    themeButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const theme = btn.getAttribute('data-theme');
            setTheme(theme);
        });
    });

    initThemeUI();
};

const initializeLanguage = () => {
    const htmlElement = document.documentElement;
    const langItems = document.querySelectorAll('.lang-item');
    const currentLangText = document.querySelector('.current-lang-text');

    async function loadTranslations(lang = "en") {
        try {
            const response = await fetch(`assets/i18n/${lang}.json`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const translations = await response.json();

            document.querySelectorAll("[data-i18n]").forEach(el => {
                const key = el.getAttribute("data-i18n");

                if (key.startsWith("[placeholder]")) {
                    const cleanKey = key.replace("[placeholder]", "");
                    if (translations[cleanKey]) {
                        el.setAttribute("placeholder", translations[cleanKey]);
                    }
                } else if (key.startsWith("[title]")) {
                    const cleanKey = key.replace("[title]", "");
                    if (translations[cleanKey]) {
                        el.setAttribute("title", translations[cleanKey]);
                    }
                } else if (translations[key]) {
                    el.textContent = translations[key];
                }
            });

            // Update attributes
            htmlElement.setAttribute('lang', lang);
            htmlElement.setAttribute('custom-lang', lang);

            // Save language
            localStorage.setItem("lang", lang);

            // Update dropdown checkmarks
            if (langItems.length == 0) return;
            langItems.forEach(item => {
                const checkIcon = item.querySelector('.lang-check');
                const isActive = item.dataset.lang === lang;
                item.classList.toggle('active-lang', isActive);
                if (checkIcon) checkIcon.style.display = isActive ? 'inline' : 'none';
                // Update dropdown button icon
                if (isActive) {
                    const fullText = item.textContent.trim();
                    if (lang == "en") {
                        currentLangText.textContent = "EN";
                    } else {
                        const shortText = fullText.substring(0, 3);
                        currentLangText.textContent = shortText;
                    }
                }
            });

        } catch (err) {
            console.error("Error loading translations:", err);
        }
    }

    const initLangUI = () => {
        const storedLang = localStorage.getItem("lang") || "en";
        loadTranslations(storedLang);
    };

    // Event listeners
    langItems.forEach(item => {
        item.addEventListener("click", (e) => {
            e.preventDefault();
            loadTranslations(item.dataset.lang);
        });
    });

    // Sync across tabs
    window.addEventListener('storage', (event) => {
        if (event.key === 'lang') {
            loadTranslations(event.newValue);
        }
    });

    initLangUI();
};

const initializeLanguage2 = () => {
    const htmlElement = document.documentElement;
    const dropdownButton = document.querySelector("#languageDropdown");
    const languageItems = document.querySelectorAll(".language-select");

    // Load JSON translations
    async function loadTranslations(lang = "en") {
        const langKey = lang.toLowerCase(); // English → english.json

        try {
            const response = await fetch(`assets/i18n/${langKey}.json`);
            if (!response.ok) throw new Error("Translation file not found: " + langKey);

            const translations = await response.json();

            // Apply translations
            document.querySelectorAll("[data-i18n]").forEach(el => {
                const key = el.getAttribute("data-i18n");

                if (key.startsWith("[placeholder]")) {
                    const cleanKey = key.replace("[placeholder]", "");
                    if (translations[cleanKey]) el.setAttribute("placeholder", translations[cleanKey]);
                } else if (key.startsWith("[title]")) {
                    const cleanKey = key.replace("[title]", "");
                    if (translations[cleanKey]) el.setAttribute("title", translations[cleanKey]);
                } else if (translations[key]) {
                    el.textContent = translations[key];
                }
            });

            // Update html tag
            htmlElement.setAttribute("lang", langKey);
            htmlElement.setAttribute("custom-lang", langKey);

            // Save selected language
            localStorage.setItem("lang", lang);

            // Update button label
            dropdownButton.textContent = lang == "en" ? "English" : lang == "si" ? "සිංහල" : "தமிழ்";

        } catch (err) {
            console.error("Error:", err);
        }
    }

    // Initialize UI on page load
    const initLangUI = () => {
        const savedLang = localStorage.getItem("lang") || "en";
        loadTranslations(savedLang);
    };

    // Handle click events
    languageItems.forEach(item => {
        item.addEventListener("click", e => {
            e.preventDefault();
            const selectedLang = item.getAttribute("data-value");

            loadTranslations(selectedLang);
        });
    });

    // Sync across browser tabs
    window.addEventListener("storage", event => {
        if (event.key === "lang") {
            dropdownButton.textContent = event.newValue;
            loadTranslations(event.newValue);
        }
    });

    initLangUI();
};

const createLoader = () => {
    if ($(".loader-container").length) return; // already exists

    const loaderContainer = $(`
        <div class="loader-container" style="display:none;">
            <div class="fading-bars">
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
            </div>
        </div>
    `);

    $("body").append(loaderContainer);
};


const toggleLoader = (isLoading) => {
    createLoader();
    if (isLoading) {
        $(".loader-container").fadeIn();
    } else {
        $(".loader-container").fadeOut(1000);
        setTimeout(() => { $(".loader-container").remove(); }, 1000);

    }
};

function toggleBtnLoader(btn, state) {
    if (!btn.dataset.originalText) {
        btn.dataset.originalText = btn.innerHTML.trim();
    }

    if (state === true) {
        btn.disabled = true;
        btn.style.cursor = "not-allowed";

        btn.innerHTML = `
            <iconify-icon icon="eos-icons:bubble-loading" width="22" height="22" class="text-white"></iconify-icon>
        `;
        return;
    }
    if (state === "done") {
        btn.innerHTML = `
            <iconify-icon icon="line-md:check-all" width="22" height="22"></iconify-icon>
        `;

        return;
    }
    if (state === false) {
        btn.disabled = false;
        btn.style.cursor = "pointer";
        btn.innerHTML = btn.dataset.originalText;
    }
}
const toggleSkeleton = () => {
    const delay = ms => new Promise(res => setTimeout(res, ms));
    (async () => {

        document.getElementById('skeleton-list').classList.add('d-none');
        document.getElementById('real-list').classList.remove('d-none');
    })();
}

const notifications = document.querySelector(".notifications");

const toastDetails = {
    timer: 3000,
    success: {
        topic: 'Success',
        icon: 'mdi:check-circle',  // Iconify icon for success
        defaultText: 'This is a success toast.',
    },
    error: {
        topic: 'Error',
        icon: 'mdi:close-circle',  // Iconify icon for error
        defaultText: 'This is an error toast.',
    },
    warning: {
        topic: 'Warning',
        icon: 'mdi:alert-circle',  // Iconify icon for warning
        defaultText: 'This is a warning toast.',
    },
    info: {
        topic: 'Info',
        icon: 'mdi:information',  // Iconify icon for info
        defaultText: 'This is an information toast.',
    }
}

const removeToast = (toast) => {
    toast.classList.add("hide");
    if (toast.timeoutId) clearTimeout(toast.timeoutId); // Clear the timeout for the toast
    setTimeout(() => toast.remove(), 500); // Remove the toast after 500ms
}

const showToast = (type, message) => {
    const { topic, icon, defaultText } = toastDetails[type];
    const toast = document.createElement("li");
    toast.className = `custom-toast ${type}`;
    const toastMessage = message || defaultText;

    // Using Iconify icons in place of FontAwesome
    toast.innerHTML = `<div class="column">
                         <iconify-icon icon="${icon}" width="24" height="24"></iconify-icon>
                         <div class="custom-toast-text">
                           <span class="custom-toast-type">${topic}</span>
                           <span class="custom-toast-message">${toastMessage}</span>
                         </div>
                       </div>
                       <iconify-icon icon="mdi:close" width="24" height="24" class="close-btn" onclick="removeToast(this.parentElement)"></iconify-icon>`;

    notifications.appendChild(toast);

    toast.timeoutId = setTimeout(() => removeToast(toast), toastDetails.timer);
};

const togglePasswordVisibility = () => {
    const passwordInput = $('#password');
    const togglePasswordIcon = $('#togglePassword');

    const type = passwordInput.attr('type') === 'password' ? 'text' : 'password';
    passwordInput.attr('type', type);

    // Change the icon based on the input type
    if (type === 'password') {
        togglePasswordIcon.attr('icon', 'tabler:eye-off');   // Show eye-off icon
    } else {
        togglePasswordIcon.attr('icon', 'eva:eye-outline');  // Show eye icon
    }
}


const initializeMultipleSelect = (condition) => {
    const selectValue = $('#' + condition + '-selector').val();
    const multipleSelect = $('#' + condition + '-multi-selector').get(0);
    //console.log(multipleSelect.options.length);
    for (let i = 0; i < multipleSelect.options.length; i++) {
        //console.log(multipleSelect.options[i].value);
        if (multipleSelect.options[i].value === selectValue) {
            multipleSelect.options[i].selected = true;
            break;
        }
    }

    // Optionally, update the display container here with the selected value
    updateSelectedItemsDisplay(multipleSelect, condition);
}

const updateSelectedItemsDisplay = (multipleSelect, condition) => {
    const selectedItemsContainer = $('#selected-' + condition + '-container');
    const child = $('#child');
    selectedItemsContainer.empty(); // Clear the container

    const length = multipleSelect.selectedOptions.length;
    if (length >= 1) {
        selectedItemsContainer.removeClass('d-none');
        selectedItemsContainer.addClass('bl-solid');
    } else {
        selectedItemsContainer.removeClass('bl-solid');
    }
    // Loop through selected options and display them
    for (let i = 0; i < length; i++) {
        const option = multipleSelect.selectedOptions[i];
        // Clone the child element
        const clonedChild = child.clone();

        // Update the text of the cloned element
        clonedChild.find('span').first().text(option.text);

        // Update the onclick function with the appropriate value
        clonedChild.find('iconify-icon').on('click', () => {
            removeItem(option.value, condition);
        });

        // Append the cloned child to the selected items container
        selectedItemsContainer.append(clonedChild);
    }
}

const removeItem = (value, condition) => {
    const multipleSelect = $('#' + condition + '-multi-selector').get(0);
    let relationalSelect;

    // Deselect the option in the multiple select
    for (let i = 0; i < multipleSelect.options.length; i++) {
        if (multipleSelect.options[i].value === value) {
            multipleSelect.options[i].selected = false;
            break;
        }
    }

    // Update the selected items display after removal
    updateSelectedItemsDisplay(multipleSelect, condition);
    if (condition === 'faculty') {
        if ($("#programme-multi-selector").val().length > 0) {
            removeDeletedFacultyProgrammes(value);
        }
    } else if (condition === 'programme') {
        if ($("#intake-multi-selector").val().length > 0) {
            removeDeletedProgrammeIntakes(value);
        }
    }

    $('#' + condition + '-selector').val(0);

}

const uploadImage = (input, displayTag) => {
    const fileInput = document.getElementById(input);
    const imageTag = document.getElementById(displayTag);
    const closeModalButtons = document.querySelectorAll('[data-bs-dismiss="modal"]');
    const image = document.getElementById("image");
    const cropButton = document.getElementById("crop-button");
    let cropper;

    // Trigger file input click
    fileInput.click();

    // Reset cropper and image before selecting a new file
    fileInput.addEventListener('click', () => {
        if (cropper) {
            cropper.destroy(); // Destroy previous cropper instance
        }
        image.src = ''; // Clear previous image
    }, { once: true });

    fileInput.addEventListener('change', function (event) {
        const selectedFile = event.target.files[0];

        if (selectedFile) {
            const img = new Image();
            const reader = new FileReader();

            reader.onload = function (e) {
                img.src = e.target.result;

                img.onload = function () {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');

                    // Set a consistent minimum size for the image
                    const minSize = 500; // Minimum width/height
                    const scale = Math.max(minSize / img.width, minSize / img.height);

                    canvas.width = img.width * scale;
                    canvas.height = img.height * scale;

                    // Draw the resized image on the canvas
                    ctx.fillStyle = '#fff'; // Optional: Add a background color
                    ctx.fillRect(0, 0, canvas.width, canvas.height);
                    ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                    // Convert the canvas to a Blob and set it to the image source
                    canvas.toBlob(function (blob) {
                        const resizedURL = URL.createObjectURL(blob);
                        image.src = resizedURL;

                        // Show the modal
                        $('#crop-modal').modal('show');

                        // Initialize Cropper.js
                        if (cropper) {
                            cropper.destroy();
                        }

                        cropper = new Cropper(image, {
                            aspectRatio: 1, // Maintain square crop
                            viewMode: 1,    // Fully show the image in the canvas
                            autoCropArea: 1, // Maximize crop area
                            responsive: true, // Resize dynamically
                            scalable: true, // Allow scaling
                            minContainerWidth: 500, // Minimum cropper container width
                            minContainerHeight: 500, // Minimum cropper container height
                        });
                    }, 'image/png');
                };
            };

            reader.readAsDataURL(selectedFile);
        }
    }, { once: true });

    $('#crop-modal').on('show.bs.modal', function () {
        // Remove inert attribute and allow interactions
        $(this).removeAttr('aria-hidden').prop('inert', false);
    }).on('hidden.bs.modal', function () {
        // Add inert attribute and prevent interactions when hidden
        $(this).attr('aria-hidden', 'true').prop('inert', true);
    });

    // Add crop logic with proper checks
    cropButton.addEventListener("click", function () {
        if (cropper) {
            const canvas = cropper.getCroppedCanvas();

            if (canvas) {
                canvas.toBlob(function (blob) {
                    if (blob) {
                        const reader = new FileReader();
                        reader.onloadend = function () {
                            const file = new File([blob], "cropped_image.png", {
                                type: "image/png",
                            });

                            // Assign cropped image to file input and display
                            const dataTransfer = new DataTransfer();
                            dataTransfer.items.add(file);
                            fileInput.files = dataTransfer.files;

                            imageTag.src = reader.result; // Update preview image
                        };

                        reader.readAsDataURL(blob);
                        $('#crop-modal').modal('hide');
                    }
                }, "image/png");
            }
        }
    });


    // Handle modal close and reset
    closeModalButtons.forEach((button) => {
        button.addEventListener("click", function () {
            fileInput.value = ""; // Reset file input
            image.src = "";       // Reset image source
            $('#crop-modal').modal('hide');
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
        });
    });
};

const uploadCover = (input, displayTag) => {
    const fileInput = document.getElementById(input);
    const imageTag = document.getElementById(displayTag);
    const closeModalButtons = document.querySelectorAll('[data-bs-dismiss="modal"]');
    const image = document.getElementById("image");
    const cropButton = document.getElementById("crop-button");
    let cropper;

    // Trigger the file input click
    fileInput.click();

    // Reset cropper and image before selecting a new file
    fileInput.addEventListener('click', () => {
        if (cropper) {
            cropper.destroy(); // Destroy previous cropper instance
        }
        image.src = '';  // Clear previous image
    }, { once: true });

    // Handle file selection
    fileInput.addEventListener('change', function (event) {
        const selectedFile = event.target.files[0];

        if (selectedFile) {
            const reader = new FileReader();

            reader.onload = function (e) {
                image.src = e.target.result;
                $('#crop-modal').modal('show');
                image.onload = function () {  // Ensure image is fully loaded
                    if (cropper) {
                        cropper.destroy(); // Destroy previous cropper
                    }
                    cropper = new Cropper(image, {
                        aspectRatio: 16 / 9, // Maintain square crop
                        viewMode: 1,    // Fully show the image in the canvas
                        autoCropArea: 1, // Maximize crop area
                        responsive: true, // Resize dynamically
                        scalable: true, // Allow scaling
                        minContainerWidth: 500, // Minimum cropper container width
                        minContainerHeight: 500, // Minimum cropper container height
                    });
                };
            };

            reader.readAsDataURL(selectedFile);
        }
    }, { once: true });

    cropButton.addEventListener("click", function () {
        if (cropper) {
            const canvas = cropper.getCroppedCanvas();

            if (canvas) {
                canvas.toBlob(function (blob) {
                    if (blob) {
                        const reader = new FileReader();
                        reader.onloadend = function () {
                            const file = new File([blob], "cropped_image.png", {
                                type: "image/png",
                            });

                            // Assign cropped image to file input and display
                            const dataTransfer = new DataTransfer();
                            dataTransfer.items.add(file);
                            fileInput.files = dataTransfer.files;

                            imageTag.src = reader.result; // Update preview image
                        };

                        reader.readAsDataURL(blob);
                        $('#crop-modal').modal('hide');
                    }
                }, "image/png");
            }
        }
    });

    // Reset modal and cropper on close
    closeModalButtons.forEach((button) => {
        button.addEventListener("click", function () {
            fileInput.value = '';
            image.src = "";
            $('#crop-modal').modal('hide');
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
        });
    });
};

const uploadRecording = () => {

    // Get the video input and preview elements
    const videoInput = document.getElementById('video-input');
    const videoPreview = document.getElementById('video-preview');


    videoInput.click();
    // Handle file selection
    videoInput.addEventListener('change', function (event) {
        const selectedFile = event.target.files[0]; // Get the selected video file

        if (selectedFile) {
            const fileURL = URL.createObjectURL(selectedFile); // Create a URL for the file
            videoPreview.src = fileURL; // Set the video src to the file URL
            videoPreview.style.display = 'block'; // Display the video preview
            videoPreview.load(); // Load the video to prepare for playback
            $("#empty-rec").addClass("d-none");
            $("#video-preview").removeClass("d-none");
        }
    });
}

const uploadFile = () => {
    const fileInput = document.getElementById('file-input2');
    const fileNameDisplay = document.getElementById('file-name');

    fileInput.click();
    // Handle file selection
    fileInput.addEventListener('change', function (event) {
        const selectedFile = event.target.files[0]; // Get the selected file

        if (selectedFile) {
            // Display file info
            fileNameDisplay.textContent = selectedFile.name; // Show the file name

        }
    });
}

const getTableWidth = (table, pagination) => {
    //console.log(table);
    // Get the table element
    var table = document.querySelector(table);

    // Get the table's width
    var tableWidth = table.offsetWidth;

    // Output the width to the console or use as needed
    document.querySelector(pagination).style.width = tableWidth + 'px';
    //console.log(tableWidth);
}
var about;
var bio;
var achivements;
var experience;
const initializeAddUser = () => {
    about = new RichTextEditor("#div_editor1");
    bio = new RichTextEditor("#div_editor2");
    achivements = new RichTextEditor("#div_editor3");
    experience = new RichTextEditor("#div_editor4");

    var today = new Date();
    var minDate = new Date(today.getFullYear() - 70, today.getMonth(), today.getDate());
    var maxDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());
    var minDateString = minDate.toISOString().split('T')[0];
    var maxDateString = maxDate.toISOString().split('T')[0];

    // Set the min and max attributes on the input field
    document.getElementById('user-dob').setAttribute('min', minDateString);
    document.getElementById('user-dob').setAttribute('max', maxDateString);
}

const checkDigit = (event) => {
    const charCode = event.which ? event.which : event.keyCode;
    if (charCode > 31 && (charCode < 48 || charCode > 57)) {
        event.preventDefault();
    }
}

const checkValidChar = (event) => {
    var charCode = event.which ? event.which : event.keyCode;
    if (!(charCode >= 48 && charCode <= 57) && charCode !== 86 && charCode !== 118) {
        event.preventDefault(); // Prevent any other characters
    }
}

const initializeViewUser = () => {
    about = new RichTextEditor("#div_editor1");
    bio = new RichTextEditor("#div_editor2");
    achivements = new RichTextEditor("#div_editor3");
    experience = new RichTextEditor("#div_editor4");

    var today = new Date();
    var minDate = new Date(today.getFullYear() - 70, today.getMonth(), today.getDate());
    var maxDate = new Date(today.getFullYear() - 18, today.getMonth(), today.getDate());
    var minDateString = minDate.toISOString().split('T')[0];
    var maxDateString = maxDate.toISOString().split('T')[0];

    // Set the min and max attributes on the input field
    document.getElementById('user-dob').setAttribute('min', minDateString);
    document.getElementById('user-dob').setAttribute('max', maxDateString);

}

const viewFile = (fileName) => {
    //console.log(fileName);
    const extension = fileName.split('.').pop().toLowerCase();
    if (extension === 'pdf') {
        window.open(`./assets/uploads/cv/${fileName}`, '_blank');
    } else {
        const userConfirmed = confirm(`Do you want to download ${fileName}?`);
        if (userConfirmed) {
            window.location.href = `./assets/uploads/cv/${fileName}`;
        }
    }
}

const tableCheckEvents = () => {
    var isChecked = $("#check-all").prop('checked');
    $('.row-check').prop('checked', isChecked);
    if (isChecked) {
        selectedUsers = [];
        unSelectedUsers = [];
    } else {
        if (selectedUsers.length == 0 || unSelectedUsers.length == 0) {
            selectedUsers = [];
            unSelectedUsers = [];
        }
    }
}

const checkRowEvents = (id) => {
    var isChecked = $("#check-user" + id).prop('checked');

    //console.log(isChecked)

    if (isChecked) {
        if (!selectedUsers.includes(id)) {
            if (unSelectedUsers.length != 0 && unSelectedUsers.includes(id)) {
                unSelectedUsers = unSelectedUsers.filter(userId => userId !== id);
            } else {
                selectedUsers.push(id);
            }
        }
        $("#check-all").prop('checked', false);
    } else {
        $("#check-all").prop('checked', false);
        if (selectedUsers.length != 0 && selectedUsers.includes(id)) {
            selectedUsers = selectedUsers.filter(userId => userId !== id);
        } else {
            unSelectedUsers.push(id);
        }
    }
};

const setDefault = () => {
    $("#check-all").prop('checked', false);
    $("#update-selector").val(0);
    selectedUsers = [];
    unSelectedUsers = [];
}

function toggleSort(current) {
    return current === SORT_ASC ? SORT_DESC : SORT_ASC;
}

const handleUsersPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = SORT_NONE;
            sortCondition1 = toggleSort(sortCondition1);
            break;
        case 'sortByDate':
            sortCondition1 = 0;
            sortCondition2 = toggleSort(sortCondition2);
            break;
    }

    filterUsers();
}

const selectFilterCondition = () => {
    selectedPage = 1;
    filterUsers();
}

const loadBranchEditModel = (id, name, image) => {
    $("#edit-branch-name").val(name);
    $("#edit-branch-cover").attr('src', `./assets/imgs/branches-covers/${image}`);
    $("#save-edit").off('click').on('click', function () {
        editBranch(this, id);
    });
    $("#editBranch").modal('show');
}

const loadFacultyEditModel = (id, name, image) => {
    $("#edit-faculty-name").val(name);
    $("#edit-faculty-cover").attr('src', `./assets/imgs/faculties-covers/${image}`);
    $("#save-edit").off('click').on('click', function () {
        editFaculty(this, id);
    });
    $("#editFaculty").modal('show');
}

const loadProgrammeEditModel = (id, name, image, duration) => {
    $("#edit-programme-name").val(name);
    $("#edit-programme-cover").attr('src', `./assets/imgs/programmes-covers/${image}`);
    $("#edit-programme-duration").val(duration);
    $("#save-edit").off('click').on('click', function () {
        editProgramme(this, id);
    });
    $("#editProgramme").modal('show');
}
const loadIntakeEditModel = (id, name, image) => {
    $("#edit-intake-name").val(name);
    $("#edit-intake-cover").attr('src', `./assets/imgs/intakes-covers/${image}`);
    $("#save-edit").off('click').on('click', function () {
        editIntake(this, id);
    });
    $("#editIntake").modal('show');
}

const loadLevelEditModel = (id, name, image) => {
    $("#edit-level-name").val(name);
    $("#edit-level-cover").attr('src', `./assets/imgs/levels-covers/${image}`);
    $("#save-edit").off('click').on('click', function () {
        editLevel(this, id);
    });
    $("#editLevel").modal('show');
}

const loadSubjectEditModel = (id, name, image, hours, type, group) => {

    $("#edit-subject-name").val(name);
    $("#edit-subject-cover").attr('src', `./assets/imgs/subjects-covers/${image}`);
    $("#edit-subject-hours").val(hours);
    $("#edit-subject-type").val(type);
    if (type == 2) {
        $("#edit-subject-group-display-area").removeClass('d-none');
        $("#edit-subject-group").val(group);
    }
    $("#save-edit").off('click').on('click', function () {
        editSubject(this, id);
    });
    $("#editSubject").modal('show');
}


const downloadCSV = () => {
    const filePath = 'assets/uploads/csv-format/studentBulkUpload.csv';

    const link = document.createElement('a');
    link.href = filePath;
    link.download = 'studentData.csv';
    link.click();
}

const handleStudentsPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = 0;
            sortCondition1 = sortCondition1 === 1 ? 2 : 1;
            break;
        case 'sortByDate':
            sortCondition1 = 0;
            sortCondition2 = sortCondition2 == 1 ? 2 : 1;
            break;
    }

    filterStudents();
}

const handleIntakeHasStudentPagination = (id, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = sortCondition3 = 0;
            sortCondition1 = sortCondition1 === 1 ? 2 : 1;
            break;
        case 'sortByDate':
            sortCondition1 = sortCondition3 = 0;
            sortCondition2 = sortCondition2 == 1 ? 2 : 1;
            break;
        case 'sortById':
            sortCondition1 = sortCondition2 = 0;
            sortCondition3 = sortCondition3 == 1 ? 2 : 1;
            break;
    }

    filterIntakeHasStudents(id);
}

const updateUserStatus = (update, id) => {
    selectedUsers = [];
    selectedUsers.push(id);
    unSelectedUsers = [];
    applySelectedUserUpdate(update);
}
const updateStudentStatus = (update, id) => {
    selectedUsers = [];
    selectedUsers.push(id);
    unSelectedUsers = [];
    applySelectedStudentUpdate(update);
}

const updateIntakeStudentStatus = (update, intakeId, id) => {
    selectedUsers = [];
    selectedUsers.push(id);
    unSelectedUsers = [];
    applySelectedIntakeStudentUpdate(intakeId, update);
}


const updateSubjectStudentStatus = (subject, update, id) => {
    selectedUsers = [];
    selectedUsers.push(id);
    unSelectedUsers = [];
    applySelectedSubjectStudentsUpdate(subject, update);
}

const acceptFutureDate = (id) => {
    const today = new Date().toISOString().split("T")[0];
    document.getElementById(id).setAttribute("min", today);
}

const initializeSubjectHasAssignments = () => {


}

const initializeScheduleLecture = () => {
    $("#physical-check-box").on("change", function () {
        if ($(this).is(":checked")) {
            $("#online-check-box").prop("checked", false);
        } else {
            $("#online-check-box").prop("checked", true);
        }
        if ($("#lecture-link-area").hasClass("d-none") == false) {
            $("#lecture-link-area").addClass("d-none");
        }
    });

    $("#online-check-box").on("change", function () {
        if ($(this).is(":checked")) {
            $("#physical-check-box").prop("checked", false);
            $("#lecture-link-area").removeClass("d-none");
        } else {
            $("#physical-check-box").prop("checked", true);
            $("#lecture-link-area").addClass("d-none");
        }
    });

    $("#oneTimeLecture").on("change", function () {
        if ($(this).is(":checked")) {
            $("#recurringLecture").prop("checked", false);
            $("#onetimeLectureArea").removeClass("d-none");
        } else {
            $("#recurringLecture").prop("checked", true);
            $("#onetimeLectureArea").addClass("d-none");
        }
        if ($("#recurringLectureArea").hasClass("d-none") == false) {
            $("#recurringLectureArea").addClass("d-none");
        }

        if ($("#lectureTypeArea").hasClass("d-none")) {
            $("#lectureTypeArea").removeClass("d-none");
        }

    });

    $("#recurringLecture").on("change", function () {
        if ($(this).is(":checked")) {
            $("#oneTimeLecture").prop("checked", false);
            $("#recurringLectureArea").removeClass("d-none");
        } else {
            $("#oneTimeLecture").prop("checked", true);
            $("#recurringLectureArea").addClass("d-none");
        }
        if ($("#onetimeLectureArea").hasClass("d-none") == false) {
            $("#onetimeLectureArea").addClass("d-none");
        }
        if ($("#lectureTypeArea").hasClass("d-none")) {
            $("#lectureTypeArea").removeClass("d-none");
        }
    });

    const today = new Date().toISOString().split("T")[0];
    document.getElementById('lecture-date').setAttribute("min", today);

    const now = new Date();
    const currentTime = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    //console.log(currentTime);

    document.getElementById('start-time').setAttribute("min", currentTime);
    document.getElementById('end-time').setAttribute("min", currentTime);
}

const handleClassSchedulePagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterScheduledLectures();
}

const viewLectureAttachment = (fileName, tuteName) => {
    //console.log(fileName);
    const extension = fileName.split('.').pop().toLowerCase();
    if (extension === 'pdf') {
        window.open(`./assets/uploads/lectures-attachments/${fileName}`, '_blank');
    } else {
        const userConfirmed = confirm(`Do you want to download ${tuteName}?`);
        if (userConfirmed) {
            window.location.href = `./assets/uploads/lectures-attachments/${tuteName}`;
        }
    }
}
var studentPolicy;
const initializeStudentPolicy = () => {
    studentPolicy = new RichTextEditor("#div_editor1");
}
var newsContent;
const initializeNewsContent = () => {
    newsContent = new RichTextEditor("#div_editor1");
}

const initializeUploadRecording = () => {
    const today = new Date().toISOString().split("T")[0];
    document.getElementById("expire-date").setAttribute("min", today);
}

const displaySubjectGroup = (id) => {
    if (id == 2) {
        $("#subject-group-display-area").removeClass("d-none");
        $("#subject-group-add-area").removeClass("d-none");
    } else {
        $("#subject-group-display-area").addClass("d-none");
        $("#subject-group-add-area").addClass("d-none");
    }
}

const displayAddNewSubjectGroupInput = () => {
    $("#subject-group").val(0);
    $("#subject-group-display-area").addClass("d-none");
    $("#subject-group-add-area").addClass("d-none");
    $("#add-subject-group-input-area").removeClass("d-none");
}

const viewAssignmentsAttachement = (fileName) => {
    const extension = fileName.split('.').pop().toLowerCase();
    if (extension === 'pdf') {
        window.open(`./assets/uploads/assignments-attachments/${fileName}`, '_blank');
    } else {
        const userConfirmed = confirm(`Do you want to download ${fileName}?`);
        if (userConfirmed) {
            window.location.href = `./assets/uploads/assignments-attachments/${fileName}`;
        }
    }
}

const loadAssignmentEditModel = (data) => {
    //console.log(data);
    $("#em-code").text(data.assignment_code);
    $("#em-topic").text(data.assignment);

    const start = new Date(data.start_date);
    const end = new Date(data.end_date);
    const diffInMs = end - start;
    const days = Math.floor(diffInMs / (1000 * 60 * 60 * 24));
    const weeks = Math.floor(days / 7);

    $("#em-duration").text(Number.isInteger(weeks) && weeks >= 0 == true ? weeks + " Weeks" : days == 0 ? " - -" : days + " Days");

    $("#editAssignment").modal("show");
}


const handleSubjectHasAssignmentsPagination = (id, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByDate':
            selectedPage = 1;
            sortCondition1 = sortCondition1 == '1' ? '2' : '1';
            break;
    }

    filterSubjectHasAssignments(id);
}
const viewAssignment = (fileName) => {
    const extension = fileName.split('.').pop().toLowerCase();
    if (extension === 'pdf') {
        window.open(`./assets/uploads/assignments-attachments/${fileName}`, '_blank');
    }
}

const downloadMarksUploadCSV = (code) => {
    const filePath = './assets/uploads/csv-format/assignmentMarksUpload.csv';

    const link = document.createElement('a');
    link.href = filePath;
    link.download = 'AssignmentMarksUpload-AssignmentCode-' + code + '.csv';
    link.click();
}

const downloadSelectedStudentAssignment = (fileName) => {
    const extension = fileName.split('.').pop().toLowerCase();
    if (extension === 'pdf') {
        window.open(`${BASE_STUDENT_PORTAL_URL}/assets/uploads/assignments/${fileName}`, '_blank');
    }
}

const loadAddMarkModel = (intakeId, code, topic, student, studentId, id, status = 1) => {
    $("#code").text(code);
    $("#topic").text(topic);
    $("#student-name").text(student);
    $("#student-id").text(studentId);
    if (status == 1) {
        $("#saveMark")
            .off("click")
            .on("click", function () {
                addAssignmentMark(this, id, intakeId);
            });
    } else {
        $("#saveMark")
            .off("click")
            .on("click", function () {
                addRecorrectionAssignmentMark(this, id, intakeId);
            });
    }

    $("#addMark").modal('show');
}

const loadEditMarkModel = (assignmentId, code, topic, student, studentId, mark, id, status = 1) => {
    $("#edit-code").text(code);
    $("#edit-topic").text(topic);
    $("#edit-student-name").text(student);
    $("#edit-student-id").text(studentId);
    $("#edit-already-mark").val(mark);
    if (status == 1) {
        $("#edit-saveMark").attr("onclick", "editAssignmentMark('" + id + "','" + assignmentId + "')");
    } else {
        $("#edit-saveMark").attr("onclick", "editRecorrectionAssignmentMark('" + id + "','" + assignmentId + "')");
    }


    $("#editMark").modal('show');
}

const loadLecturerAddMarkModel = (intakeId, code, topic, student, studentId, id, status = 1) => {
    $("#code").text(code);
    $("#topic").text(topic);
    $("#student-name").text(student);
    $("#student-id").text(studentId);
    if (status == 1) {
        $("#saveMark").attr("onclick", "addLecturerAssignmentMark('" + id + "','" + intakeId + "')");
    } else {
        $("#saveMark").attr("onclick", "addLecturerRecorrectionAssignmentMark('" + id + "','" + intakeId + "')");
    }

    $("#addMark").modal('show');
}

const loadLecturerEditMarkModel = (intakeId, code, topic, student, studentId, mark, id, status = 1) => {
    $("#edit-code").text(code);
    $("#edit-topic").text(topic);
    $("#edit-student-name").text(student);
    $("#edit-student-id").text(studentId);
    $("#edit-already-mark").val(mark);
    if (status == 1) {
        $("#edit-saveMark").attr("onclick", "editLecturerAssignmentMark('" + id + "','" + intakeId + "')");
    } else {
        $("#edit-saveMark").attr("onclick", "editLecturerRecorrectionAssignmentMark('" + id + "','" + intakeId + "')");
    }


    $("#editMark").modal('show');
}


const handleStudentsAssignmentsPagination = (assignmentId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = 0;
            sortCondition1 = sortCondition1 == 1 ? 2 : 1;
            break;
        case 'sortByDate':
            sortCondition1 = 0;
            sortCondition2 = sortCondition2 == 1 ? 2 : 1;
            break;
    }

    filterStudentsAssignments(assignmentId);
}

const loadExaminationAdvancedDetails = () => {
    const type = $("#exam-type").val();

    if (type == 1) {
        $("#physical-examination").removeClass("d-none");
        if ($("#online-examination").hasClass("d-none") == false) {
            $("#online-examination").addClass("d-none");
        }
        if ($("#quiz-type-section").hasClass("d-none") == false) {
            $("#quiz-type-section").addClass("d-none");
        }
    } else if (type == 2) {
        $("#online-examination").removeClass("d-none");
        if ($("#physical-examination").hasClass("d-none") == false) {
            $("#physical-examination").addClass("d-none");
        }
        if ($("#quiz-type-section").hasClass("d-none") == false) {
            $("#quiz-type-section").addClass("d-none");
        }
    } else if (type == 3) {
        $("#quiz-type-section").removeClass("d-none");
        $("#online-examination").removeClass("d-none");
        if ($("#physical-examination").hasClass("d-none") == false) {
            $("#physical-examination").addClass("d-none");
        }
    }


}
const downloadOnlineExaminationCSVFormat = () => {
    const type = $("#exam-type").val();
    if (type == 2) {
        const filePath = 'assets/uploads/csv-format/essayQuestionsUpload.csv';
        const link = document.createElement('a');
        link.href = filePath;
        link.download = 'Essay Questions Upload.csv';
        link.click();
    } else {
        const filePath = 'assets/uploads/csv-format/quizUpload.csv';
        const link = document.createElement('a');
        link.href = filePath;
        link.download = 'Quiz Upload.csv';
        link.click();
    }

}

const handleSubjectHasExaminationsPagination = (subjectId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            selectedPage = pageId;
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterSubjectHasExaminations(subjectId);
}


const userPermissions = {};
const chipBoxAction = (clickedElement) => {
    const $chip = $(clickedElement);
    const userId = $chip.data('user-id');
    const permissionId = $chip.data('value');

    $chip.toggleClass('selected');

    if (!userPermissions[userId]) {
        userPermissions[userId] = [];
    }

    if ($chip.hasClass('selected')) {
        if (!userPermissions[userId].includes(permissionId)) {
            userPermissions[userId].push(permissionId);
        }
    } else {
        userPermissions[userId] = userPermissions[userId].filter(perm => perm !== permissionId);
    }

};


const removePermissionToAddUser = (userId, deleteIcon) => {
    const userPermissionTemplate = $(deleteIcon).closest('#user-permission-template');

    if (userPermissionTemplate.length) {
        userPermissionTemplate.remove();
        addNewPermissionsUserCount--;

        if (userPermissions[userId]) {
            delete userPermissions[userId];
        }
        newPermissionUsers = newPermissionUsers.filter((id) => id !== userId);

        if (addNewPermissionsUserCount == 0) {
            $("#save-permission-btn-area").addClass("d-none");
            $('#empty-users-display-area').removeClass('d-none');
        }
    } else {
        showToast('error', 'Something went wrong. Please try again.');
    }
};

const handleFacultyPermissionPagination = (facultyId, action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterFacultyPermissions(facultyId);
}

let selectedPermissions = [];
let unSelectedPermissions = [];

$(document).on("change", ".row-check", function () {
    const permissionId = $(this).attr("id").replace("check-permission", "");
    if ($(this).is(":checked")) {
        if (!selectedPermissions.includes(permissionId)) {
            selectedPermissions.push(permissionId);
        }
        unSelectedPermissions = unSelectedPermissions.filter(id => id !== permissionId);
    } else {
        selectedPermissions = selectedPermissions.filter(id => id !== permissionId);
    }

});

$("#user-permission-update-selector").on("change", function () {
    const selectedValue = $(this).val();

    $(".row-check").each(function () {
        const permissionId = $(this).attr("id").replace("check-permission", "");
        const statusCell = $(this).closest("tr").find(".table-btn");
        const statusText = statusCell.text().trim();

        if (selectedValue === "1") {
            if (statusText === "Inactive") {
                $(this).prop("checked", true);
                if (!selectedPermissions.includes(permissionId)) {
                    selectedPermissions.push(permissionId);
                    unSelectedPermissions = unSelectedPermissions.filter(id => id !== permissionId);
                }
            } else {
                $(this).prop("checked", false);
                if (!unSelectedPermissions.includes(permissionId)) {
                    selectedPermissions = selectedPermissions.filter(id => id !== permissionId);
                }
            }
        } else if (selectedValue === "2") {
            if (statusText === "Active") {
                $(this).prop("checked", true);
                if (!selectedPermissions.includes(permissionId)) {
                    selectedPermissions.push(permissionId);
                    unSelectedPermissions = unSelectedPermissions.filter(id => id !== permissionId);
                }
            } else {
                $(this).prop("checked", false);
                if (!unSelectedPermissions.includes(permissionId)) {
                    selectedPermissions = selectedPermissions.filter(id => id !== permissionId);
                }
            }
        } else {
            $(this).prop("checked", false);
            selectedPermissions = [];
            unSelectedPermissions = [];
        }
    });

});

const togglePermissionCheckbox = (perId) => {
    const checkbox = $(`#check-permission${perId}`);
    const isChecked = checkbox.prop('checked');

    // Toggle the checkbox state
    checkbox.prop('checked', !isChecked).trigger('change');
}

const handleProgrammePermissionPagination = (programmeId, action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterProgrammePermissions(programmeId);
}

const handleIntakePermissionPagination = (intakeId, action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterIntakePermissions(intakeId);
}

const handleLevelPermissionPagination = (levelId, action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterLevelPermissions(levelId);
}

const handleSubjectPermissionPagination = (subejctId, action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterSubjectPermissions(subejctId);
}

const initializeAddQuiz = () => {
    const today = new Date().toISOString().split("T")[0];
    document.getElementById('exam-date').setAttribute("min", today);

    const now = new Date();
    const currentTime = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    //console.log(currentTime);

    document.getElementById('start-time').setAttribute("min", currentTime);
    document.getElementById('end-time').setAttribute("min", currentTime);
}


let quizCount = 0;

$('#add-quiz-btn').click(function () {

    if (quizCount == 0) {
        quizCount = $('.quiz-item').length;
    }

    quizCount++; // Increment quiz count
    if ($("#quiz-questions-container").hasClass('d-none')) {
        $("#quiz-questions-container").removeClass('d-none');
        $("#empty-question-display-area").addClass('d-none');
    }
    const quizHTML = `
            <div class="col-12 p-0 mb-3 quiz-item" data-quiz-id="${quizCount}" data-quiz-status="2">
                <div class="row p-0 m-0">
                    <div class="col-12 p-2 px-md-4 pe-md-4 pt-md-2 pb-md-2 d-flex p-0 m-0 align-items-center bl-solid" data-bs-toggle="collapse" data-bs-target="#quiz-body-${quizCount}" aria-expanded="false" aria-controls="quiz-body-${quizCount}">
                        <span class="text-16 main-text flex-grow-1">Quiz ${quizCount}</span>
                        <div class="col-6 d-flex justify-content-end">
                            <iconify-icon icon="typcn:delete" width="30" height="30" class="sub-text delete-quiz-btn"></iconify-icon>
                        </div>
                    </div>
                    <div class="col-12 p-0 m-0 bl-solid-lrb p-4 accordion-collapse collapse" id="quiz-body-${quizCount}" data-bs-parent="#quiz-container">
                        <div class="row gy-3 accordion-body">
                            <div class="col-12 d-flex flex-column">
                                <label for="" class="text-16 sub-text">Question</label>
                                <div class="d-flex align-items-center gx-3 mt-3">
                                    <div class="d-flex align-items-center flex-grow-1 pe-3">
                                        <input type="text" class="form-control custom-input question-input" placeholder="Enter question">
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <img src="./assets/imgs/common/vector.png" alt="add image question">
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 d-flex align-items-center">
                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 answer-checkbox" name="quiz-${quizCount}" value="Option 1">
                                <input type="text" class="form-control custom-input mt-2 answer-input" placeholder="Option 1">
                            </div>
                            <div class="col-12 col-md-6 d-flex align-items-center">
                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 answer-checkbox" name="quiz-${quizCount}" value="Option 2">
                                <input type="text" class="form-control custom-input mt-2 answer-input" placeholder="Option 2">
                            </div>
                            <div class="col-12 col-md-6 d-flex align-items-center">
                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 answer-checkbox" name="quiz-${quizCount}" value="Option 3">
                                <input type="text" class="form-control custom-input mt-2 answer-input" placeholder="Option 3">
                            </div>
                            <div class="col-12 col-md-6 d-flex align-items-center">
                                <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 answer-checkbox" name="quiz-${quizCount}" value="Option 4">
                                <input type="text" class="form-control custom-input mt-2 answer-input" placeholder="Option 4">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    $('#quiz-container').append(quizHTML);

});

$(document).on('click', '.delete-quiz-btn', function () {
    const quizItem = $(this).closest('.quiz-item');
    quizItem.remove();
    renumberQuizes();
});


$(document).on('change', '.answer-checkbox', function () {
    const quizId = $(this).attr('name');
    $(`input[name="${quizId}"]`).not(this).prop('checked', false);
});


const renumberQuizes = () => {
    let quizCount = 0;
    const quizzes = $('.quiz-item');

    //console.log(`Found ${quizzes.length} quiz items.`);

    quizzes.each(function () {
        quizCount++;
        $(this).attr('data-quiz-id', quizCount);
        $(this).find('.main-text').text(`Quiz ${quizCount}`);
        $(this).find('.answer-checkbox').attr('name', `quiz-${quizCount}`);
    });

    if (quizCount == 0) {
        $('#quiz-questions-container').addClass('d-none');
        $('#empty-question-display-area').removeClass('d-none');
    }
}

const downloadQuizCSVFormat = () => {
    const filePath = 'assets/uploads/csv-format/quizUpload.csv';

    const link = document.createElement('a');
    link.href = filePath;
    link.download = 'quizUpload.csv';
    link.click();
}

let quizContainer
const initializeAddQuizQuestion = () => {
    const fileInput = $('#file-selector-input');
    const uploadButton = $('.modal-footer .custom-btn');
    quizContainer = $('#quiz-container');
    const fileFeedback = $('#file-feedback');

    uploadButton.on('click', () => {
        const file = fileInput[0].files[0];
        if (file) {
            readCSVFile(file);
            $('#file-selector-input').val('');
            $("#quizBulkUpload").modal('hide');
        } else {
            fileFeedback.text('No file selected.');
        }
    });

}

const uploadQuizQuestionBulk = () => {
    const fileInput = $('#file-selector-input');
    const fileFeedback = $('#file-feedback');
    quizContainer = $('#quiz-container');
    const file = fileInput[0].files[0];
    if (file) {
        readCSVFile(file);
        $('#file-selector-input').val('');
        $("#quizBulkUpload").modal('hide');
    } else {
        fileFeedback.text('No file selected.');
    }
}

const readCSVFile = (file) => {
    const reader = new FileReader();
    reader.onload = (e) => {
        const text = e.target.result;
        const { headers, rows } = parseCSV(text);
        if (rows.length > 0) {
            populateQuizFields(headers, rows);
        } else {
            fileFeedback.text('The CSV file is empty or invalid.');
        }
    };
    reader.readAsText(file);
};

const parseCSV = (text) => {
    const lines = text.split('\n');
    const headers = ['Question', 'Answer 1', 'Answer 2', 'Answer 3', 'Answer 4', 'Correct Answer'];
    const rows = [];

    for (let i = 0; i < lines.length; i++) {
        const values = lines[i].split(',');
        if (values.length === headers.length) {
            const row = {};
            for (let j = 0; j < headers.length; j++) {
                row[headers[j]] = values[j].trim();
            }
            rows.push(row);
        }
    }
    return { headers, rows };
};

const populateQuizFields = (headers, rows) => {
    quizContainer.empty();
    if ($("#quiz-questions-container").hasClass('d-none')) { $("#quiz-questions-container").removeClass('d-none'); }
    rows.forEach((row, index) => {
        if (index != 0) {
            quizCount++;
            const quizHTML = `
                <div class="col-12 p-0 mb-3 quiz-item" data-quiz-id="${index}" data-quiz-status="2">
                    <div class="row p-0 m-0">
                        <div class="col-12 p-2 px-md-4 pe-md-4 pt-md-2 pb-md-2 d-flex p-0 m-0 align-items-center bl-solid" data-bs-toggle="collapse" data-bs-target="#quiz-body-${index}" aria-expanded="false" aria-controls="quiz-body-${index}">
                            <span class="text-16 main-text flex-grow-1">Quiz ${index}</span>
                            <div class="col-6 d-flex justify-content-end">
                                <iconify-icon icon="typcn:delete" width="30" height="30" class="sub-text delete-quiz-btn"></iconify-icon>
                            </div>
                        </div>
                        <div class="col-12 p-0 m-0 bl-solid-lrb p-4 accordion-collapse collapse" id="quiz-body-${index}" data-bs-parent="#quiz-container">
                            <div class="row gy-3 accordion-body">
                                <div class="col-12 d-flex flex-column">
                                    <label for="" class="text-16 sub-text">Question</label>
                                    <div class="d-flex align-items-center gx-3 mt-3">
                                        <div class="d-flex align-items-center flex-grow-1 pe-3">
                                            <input type="text" class="form-control custom-input question-input" placeholder="Enter question" value="${row['Question']}">
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <img src="./assets/imgs/common/vector.png" alt="add image question">
                                        </div>
                                    </div>
                                </div>
                                ${['Answer 1', 'Answer 2', 'Answer 3', 'Answer 4'].map((answer, i) => `
                                    <div class="col-12 col-md-6 d-flex align-items-center">
                                        <input type="checkbox" class="form-check-input text-20 mt-0 mb-0 me-2 answer-checkbox" name="quiz-${index}" value="Option ${i + 1}" ${row['Correct Answer'] === (i + 1).toString() ? 'checked' : ''}>
                                        <input type="text" class="form-control custom-input mt-2 answer-input" placeholder="${answer}" value="${row[answer]}">
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            quizContainer.append(quizHTML);
            $("#empty-question-display-area").addClass('d-none');
        }
    });
};

$(document).on('change', '.answer-checkbox', function () {
    const quizId = $(this).attr('name');
    $(`input[name="${quizId}"]`).not(this).prop('checked', false);
});

$(document).on('click', '.delete-quiz-btn', function () {
    $(this).closest('.quiz-item').remove();
    renumberQuizes();
});

const handleQuizesPagination = (subjectId, action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterQuizes(subjectId);
}

const removeQuizes = [];

const deleteQuiz = (element) => {
    const quizItem = $(element).closest('.quiz-item');
    removeQuizes.push(quizItem.data('quiz-id'));
    quizItem.remove();
    quizCount = 0;
    $('.quiz-item').each(function () {
        quizCount++;
        $(this).find('.main-text').text(`Quiz ${quizCount}`);
        $(this).find('.answer-checkbox').attr('name', `quiz-${quizCount}`);
    });
    //console.log(removeQuizes);
}

const removeQuiz = (quizId) => {
    $('#removeConfirmModal').data('quiz-id', quizId);
    $("#removeConfirmModal").modal("show");
}

// Open full-screen image viewer
const openFullScreen = (imgPath) => {
    const $overlay = $('#fullScreenOverlay');
    const $fullScreenImage = $('#fullScreenImage');

    // Set the image source
    $fullScreenImage.attr('src', imgPath);

    // Set fixed dimensions (1200px width, 900px height)
    $fullScreenImage.css({
        'width': '1200px',
        'height': '900px',
        'object-fit': 'contain' // Ensures the image fits while maintaining aspect ratio
    });

    // Display the overlay
    $overlay.css('display', 'flex');

    // Enable dragging and zooming (if needed)
    enableDragAndZoom($fullScreenImage);
};
// Close full-screen image viewer
const closeFullScreen = () => {
    const $overlay = $('#fullScreenOverlay');
    $overlay.hide(); // Hide the overlay
};

// Enable dragging and zooming for the image
const enableDragAndZoom = ($image) => {
    let isDragging = false;
    let startX, startY, translateX = 0, translateY = 0;
    let scale = 1;

    // Mouse down event for dragging
    $image.on('mousedown', (e) => {
        isDragging = true;
        startX = e.clientX - translateX;
        startY = e.clientY - translateY;
        $image.css('cursor', 'grabbing'); // Change cursor to grabbing
    });

    // Mouse move event for dragging
    $image.on('mousemove', (e) => {
        if (isDragging) {
            translateX = e.clientX - startX;
            translateY = e.clientY - startY;
            $image.css('transform', `translate(${translateX}px, ${translateY}px) scale(${scale})`);
        }
    });

    // Mouse up event to stop dragging
    $image.on('mouseup', () => {
        isDragging = false;
        $image.css('cursor', 'grab'); // Change cursor back to grab
    });

    // Mouse leave event to stop dragging
    $image.on('mouseleave', () => {
        isDragging = false;
        $image.css('cursor', 'grab'); // Change cursor back to grab
    });

    // Zoom in/out with mouse wheel
    $image.on('wheel', (e) => {
        e.preventDefault();
        if (e.originalEvent.deltaY < 0) {
            scale += 0.1; // Zoom in
        } else {
            scale -= 0.1; // Zoom out
        }
        scale = Math.min(Math.max(0.5, scale), 3); // Limit zoom range
        $image.css('transform', `translate(${translateX}px, ${translateY}px) scale(${scale})`);
    });
};



const handleSelectedPaymentCategoryPagination = (categoryId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByAmount':
            sortCondition2 = sortCondition3 = sortCondition4 = SORT_NONE;
            sortCondition1 = toggleSort(sortCondition1);
            break;
        case 'sortByDate':
            sortCondition1 = sortCondition3 = sortCondition4 = SORT_NONE;
            sortCondition2 = toggleSort(sortCondition2);
            break;
        case 'sortById':
            sortCondition1 = sortCondition2 = sortCondition4 = SORT_NONE;
            sortCondition3 = toggleSort(sortCondition3);
            break;
        case 'sortByName':
            sortCondition1 = sortCondition2 = sortCondition3 = SORT_NONE;
            sortCondition4 = toggleSort(sortCondition4);
            break;
    }

    filterPayments(categoryId);
}

const openPaymentCategoryEditModal = (id, name, currency) => {
    //console.log(id, name);
    $("#edit-category-name").val(name);
    if (currency != null) {
        $("#edit-category-currency").val(currency);
    }
    $("#save-edit-category-btn").on("click", function () {
        editPaymentCategory(this, id);
    });

    $("#editPayment").modal("show");
}

const handleIntakeHasPaymentsPagination = (intakeId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage2 = pageId;
            break;
        case 'previousPage':
            if (selectedPage2 > 1) {
                selectedPage2--;
            }
            break;
        case 'nextPage':
            if (selectedPage2 < totalPages) {
                selectedPage2++;
            }
            break;
        case 'filter':
            selectedPage2 = 1;
            break;
        case 'sortByAmount':
            sortCondition2 = sortCondition3 = sortCondition4 = 0;
            sortCondition1 = (sortCondition1 === 1 ? 2 : 1);
            break;
        case 'sortByDate':
            sortCondition1 = sortCondition3 = sortCondition4 = 0;
            sortCondition2 = (sortCondition2 == 1 ? 2 : 1);
            break;
        case 'sortByName':
            sortCondition1 = sortCondition2 = sortCondition4 = 0;
            sortCondition3 = (sortCondition3 === 1 ? 2 : 1);
            break;
        case 'sortById':
            sortCondition1 = sortCondition2 = sortCondition3 = 0;
            sortCondition4 = (sortCondition4 === 1 ? 2 : 1);
            break;

    }

    filterIntakePayments(intakeId);
}

const handleSubjectAddStudentsPagination = (subjectId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterSubjectAddStudents(subjectId);
}

const handleSubjectStudentsPagination = (subjectId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterSubjectStudents(subjectId);
}

const assignmentRecorectionOnchange = () => {
    const checked = $("#recorection").prop("checked"); // Removed the colon

    if (checked) {
        $("#recorection-fee-add-area").removeClass("d-none");
    } else {
        $("#recorection-fee-add-area").addClass("d-none");
        $("#recorrection-fee").val('');
    }
}

const checkboxes = document.querySelectorAll('.lecture-type-checkbox');

checkboxes.forEach(checkbox => {
    checkbox.addEventListener('change', function () {
        if (this.checked) {
            checkboxes.forEach(cb => {
                if (cb !== this) cb.checked = false;
            });

            const dependentField = document.getElementById(this.dataset.dependentField);
            if (dependentField) {
                if (this.dataset.dependentAction === 'show') {
                    dependentField.classList.remove('d-none');
                } else {
                    dependentField.classList.add('d-none');
                }
            }
        } else {
            const anyChecked = Array.from(checkboxes).some(cb => cb.checked);
            if (!anyChecked) {
                this.checked = true;
            }
        }
    });
});

const initialChecked = document.querySelector('.lecture-type-checkbox:checked');
if (initialChecked) {
    const dependentField = document.getElementById(initialChecked.dataset.dependentField);
    if (dependentField && initialChecked.dataset.dependentAction === 'show') {
        dependentField.classList.remove('d-none');
    }
}

const handleSubjectHasClassSchedulePagination = (subjectId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterSubjectScheduledLectures(subjectId);
}


const handleUsersPermissionPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterUsersPermissions();
}

const handleStudentsPermissionPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterStudentsPermissions();
}

const toggleCheckbox = (userId) => {
    let checkbox = document.getElementById('check-user' + userId);
    checkbox.checked = !checkbox.checked;
    checkRowEvents(userId);
}

let selectedLecturers = [];
let unSelectedLecturers = [];

const lecturersTableCheckEvents = () => {
    var isChecked = $("#lecturers-check-all").prop('checked');
    $('.lecturer-row-check').prop('checked', isChecked);
    if (isChecked) {
        selectedLecturers = [];
        unSelectedLecturers = [];
    } else {
        if (selectedLecturers.length == 0 || unSelectedLecturers.length == 0) {
            selectedLecturers = [];
            unSelectedLecturers = [];
        }
    }

}

const lecturerCheckRowEvents = (id) => {
    var isChecked = $("#check-lecturer" + id).prop('checked');

    //console.log(isChecked)

    if (isChecked) {
        if (!selectedLecturers.includes(id)) {
            if (unSelectedLecturers.length != 0 && unSelectedLecturers.includes(id)) {
                unSelectedLecturers = unSelectedLecturers.filter(userId => userId !== id);
            } else {
                selectedLecturers.push(id);
            }
        }
        $("#check-all").prop('checked', false);
    } else {
        $("#check-all").prop('checked', false);
        if (selectedLecturers.length != 0 && selectedLecturers.includes(id)) {
            selectedLecturers = selectedLecturers.filter(userId => userId !== id);
        } else {
            unSelectedLecturers.push(id);
        }
    }

    //console.log(selectedLecturers, unSelectedLecturers);
};

const handleLecturersPagination = (subjectId, action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = 0;
            sortCondition1 = sortCondition1 === 1 ? 2 : 1;
            break;
        case 'sortByDate':
            sortCondition1 = 0;
            sortCondition2 = sortCondition2 == 1 ? 2 : 1;
            break;
    }

    filterSubjectHasLecturers(subjectId);
}

const openRegisteredStudentAddIntakeModal = (id, intakeId, stuName) => {
    //console.log(id, intakeId, stuName);
    $("#student-id").val(id);
    $("#student-name").val(stuName);
    $("#add-registered-student-to-intake").attr("onclick", `addIntakeToRegisteredStudents(${id}, ${intakeId})`);

    $("#studentAddIntake").modal('show');
};

const handleRegisteredStudentsPagination = (intakeId, action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterRegisteredStudents(intakeId);
}

const downloadIntakeStudentBulkUploadCSV = () => {
    const filePath = 'assets/uploads/csv-format/studentBulkUploadToIntake.csv';

    const link = document.createElement('a');
    link.href = filePath;
    link.download = 'studentData.csv';
    link.click();
}

const handleStudentsRecorrectionAssignmentsPagination = (assignmentId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = 0;
            sortCondition1 = sortCondition1 == 1 ? 2 : 1;
            break;
        case 'sortByDate':
            sortCondition1 = 0;
            sortCondition2 = sortCondition2 == 1 ? 2 : 1;
            break;
    }

    filterRecorrectionRequestStudentsAssignments(assignmentId);
}

var faqAnswer;
var faqUpdatedAnswer;
const initializeFAQ = () => {
    faqAnswer = new RichTextEditor("#div_editor1");
}


const handleFAQPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterFAQ();
}


const initializeNews = () => {
    const container = document.querySelector('#comment-section');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
}

const handleSLPRecorrectionAssignmentsPagination = (assignmentId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterLPRecorrectionRequestStudentsAssignments(assignmentId);
}
const handleSLPAssignmentsPagination = (assignmentId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterLPStudentsAssignments(assignmentId);
}


const loadLecturerExamAddMarkModel = (examId, code, student, studentId, id, status = 1) => {
    $("#code").text(code);
    $("#student-name").text(student);
    $("#student-id").text(studentId);
    if (status == 1) {
        $("#saveMark").attr("onclick", "addPhysicalExaminationMark('" + id + "','" + examId + "')");
    } else {
        $("#saveMark").attr("onclick", "addOnlineExaminationMark('" + id + "','" + examId + "')");
    }

    $("#addMark").modal('show');
}

const loadLecturerExamEditMarkModel = (examId, code, student, studentId, mark, id, status = 1) => {
    $("#edit-code").text(code);
    $("#edit-student-name").text(student);
    $("#edit-student-id").text(studentId);
    $("#edit-already-mark").val(mark);
    if (status == 1) {
        $("#edit-saveMark").attr("onclick", "editPhysicalExaminationMark('" + id + "','" + examId + "')");
    } else {
        $("#edit-saveMark").attr("onclick", "editOnlineExaminationMark('" + id + "','" + examId + "')");
    }


    $("#editMark").modal('show');
}

const downloadExamMarksUploadCSV = (exam) => {
    const filePath = './assets/uploads/csv-format/assignmentMarksUpload.csv';

    const link = document.createElement('a');
    link.href = filePath;
    link.download = 'ExaminationMarksUpload-Examination-' + exam + '.csv';
    link.click();
}


const handleStudentsExaminationssPagination = (examId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterStudentsExaminations(examId);
}

const handleStudentsQuizPagination = (quizId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterStudentsQuizzes(quizId);
}

const handlePaymentsPermissionPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterPaymentsPermissions();
}


const handlePortalPaymnetAddStudentsPagination = (action, pageId = null, totalPages = null) => {
    console.log(action);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    console.log(selectedPage);

    filterPortalPayments();
}


const handlePortalPaymentStudentsPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterStudentsPortalPayments();
}

const downloadPortalpaymentBulkUploadCSV = () => {
    const filePath = 'assets/uploads/csv-format/portalPaymentBulkUpload.csv';

    const link = document.createElement('a');
    link.href = filePath;
    link.download = 'portalPaymentBulkUpload.csv';
    link.click();
}

const handleStudentsOngoingLectureAttendancePagination = (lectureId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterStudentsOngoingLectureAttendance(lectureId);
}
const handleSubjectPrerecordedLecturesPagination = (subjectId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterSubjectPreRecordedLectures(subjectId);
}


const handleSubjectRecordingsPagination = (subjectId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterSubjectRecordings(subjectId);
}

let collectedItems = [];

// Function to handle collect button click
function handleCollect(button, lecturersId, subjectAmount, count, subjectType) {
    const $button = $(button);

    // Change button to checked state
    $button.html('Collected')
        .removeClass('active')
        .addClass('table-btn-success')
        .off('click');

    // Create undo button
    const $undoBtn = $('<button>')
        .html('Undo')
        .addClass('table-btn table-btn-danger active ms-2')
        .on('click', function () {
            undoCollection($button, $undoBtn, lecturersId, subjectAmount, count, subjectType);
        });

    // Insert undo button after the collect button
    $button.after($undoBtn);

    // Store collected item data
    collectedItems.push({
        button: $button,
        undoBtn: $undoBtn,
        lecturersId: lecturersId,
        amount: subjectAmount,
        count: count,
        type: subjectType
    });

    // Update total collected amount display
    updateTotalCollected(subjectAmount, true);

}

// Function to undo a collection
function undoCollection($button, $undoBtn, lecturersId, subjectAmount, count, subjectType) {
    // Restore original collect button
    $button.html('+ Collect')
        .addClass('active')
        .on('click', function () {
            handleCollect(this, lecturersId, subjectAmount, count, subjectType);
        });

    // Remove undo button
    $undoBtn.remove();

    // Remove item from collectedItems array
    collectedItems = collectedItems.filter(item => item.lecturersId !== lecturersId);

    // Update total collected amount display
    updateTotalCollected(subjectAmount, false);

}

// Function to update the total collected amount display
function updateTotalCollected(amount, isAdd) {
    const totalElement = $('#totalCollected');
    const balanceElement = $('#totalBalance');
    hidePayoutButton();
    if (totalElement.length) {
        let currentTotal = parseFloat(totalElement.text().replace('LKR ', '').replace(/,/g, '')) || 0;
        currentTotal = isAdd ? currentTotal + parseFloat(amount) : currentTotal - parseFloat(amount);
        totalElement.text('LKR ' + currentTotal.toLocaleString('en-US'));

        if (currentTotal > 0) {
            showPayoutButton();
        }
    }

    if (balanceElement.length) {
        //console.log(amount);
        let currentBalance = parseFloat(balanceElement.text().replace('LKR ', '').replace(/,/g, '')) || 0;
        //console.log(currentBalance);
        //console.log(amount);

        currentBalance = isAdd ? currentBalance - parseFloat(amount) : currentBalance + parseFloat(amount);
        balanceElement.text('LKR ' + currentBalance.toLocaleString('en-US'));
    }
}

// Function to initialize collect buttons
function initCollectButtons() {
    $('.table-btn-success').off('click').each(function () {
        const $button = $(this);
        const lecturersId = $button.data('lecturer-id');
        const subjectAmount = $button.data('amount');
        const subjectType = $button.data('type');
        const count = $button.data('count');

        $button.on('click', function () {
            handleCollect(this, lecturersId, subjectAmount, count, subjectType);
        });
    });

}

const showPayoutButton = () => {
    const payoutHTML = `
        <div class="row justify-content-end gx-3 h-100">
            <div class="col-12 mt-3 m-md-0 d-flex justify-content-end align-items-center">
                <button class="custom-btn w-100" onclick="payoutLecturerPayments();">Payout</button>
            </div>
        </div>
    `;
    $('#payoutBtnContainer').html(payoutHTML);
};

const hidePayoutButton = () => $('#payoutBtnContainer').html('');

const initPasswordSetup = (token, status) => {
    const form = document.getElementById('passwordSetupForm');
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirmPassword');
    const togglePassword = document.getElementById('togglePassword');
    const toggleConfirm = document.getElementById('toggleConfirmPassword');
    const submitBtn = document.getElementById('submitBtn');
    const strengthBar = document.getElementById('strengthBar');

    // Password toggle functionality
    togglePassword.addEventListener('click', function () {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.textContent = type === 'password' ? 'Show' : 'Hide';
    });

    toggleConfirm.addEventListener('click', function () {
        const type = confirmInput.getAttribute('type') === 'password' ? 'text' : 'password';
        confirmInput.setAttribute('type', type);
        this.textContent = type === 'password' ? 'Show' : 'Hide';
    });

    // Password strength checker
    passwordInput.addEventListener('input', function () {
        const password = this.value;
        const strength = calculateStrength(password);
        updateStrengthIndicator(strength);
    });


    confirmInput.addEventListener('input', validateForm);

    // Form submission
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (!submitBtn.disabled) {
            // Simulate form submission
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';

            setupUserPassword(token, status);
        }
    });

    function calculateStrength(password) {
        let strength = 0;

        // Length check
        if (password.length >= 8) strength += 20;
        if (password.length >= 12) strength += 20;

        // Character diversity
        if (/[A-Z]/.test(password)) strength += 20;
        if (/[0-9]/.test(password)) strength += 20;
        if (/[^A-Za-z0-9]/.test(password)) strength += 20;

        return Math.min(strength, 100);
    }

    function updateStrengthIndicator(strength) {
        strengthBar.style.width = strength + '%';

        if (strength < 40) {
            strengthBar.style.backgroundColor = 'var(--error)';
        } else if (strength < 70) {
            strengthBar.style.backgroundColor = '#4e5d6f'; // orange
        } else {
            strengthBar.style.backgroundColor = 'var(--success)';
        }

        // Update password hints
        const hasLength = passwordInput.value.length >= 8;
        const hasNumber = /[0-9]/.test(passwordInput.value);
        const hasSpecial = /[^A-Za-z0-9]/.test(passwordInput.value);

        updateHint('lengthHint', hasLength);
        updateHint('numberHint', hasNumber);
        updateHint('specialHint', hasSpecial);
    }

    function updateHint(id, isValid) {
        const hint = document.getElementById(id);
        const icon = hint.querySelector('.hint-icon');

        if (isValid) {
            icon.textContent = '✓';
            icon.style.color = 'var(--success)';
        } else {
            icon.textContent = '✗';
            icon.style.color = 'var(--error)';
        }
    }

    function validateForm() {
        const password = passwordInput.value;
        const confirmPassword = confirmInput.value;
        let isValid = true;


        // Validate password
        const passwordError = document.getElementById('passwordError');
        if (password.length < 8 || !/[0-9]/.test(password) || !/[^A-Za-z0-9]/.test(password)) {
            passwordError.style.display = 'block';
            isValid = false;
        } else {
            passwordError.style.display = 'none';
        }

        // Validate password match
        const confirmError = document.getElementById('confirmError');
        if (password !== confirmPassword || confirmPassword === '') {
            confirmError.style.display = 'block';
            isValid = false;
        } else {
            confirmError.style.display = 'none';
        }

        // Enable/disable submit button
        submitBtn.disabled = !isValid;

        return isValid;
    }
}

const initializeLecturerSchedule = () => {
    getLectureScheduleData();
}

const addNewProgrammeDuration = () => {
    $("#programme-duration-row").addClass("d-none");
    $("#new-programme-duration-row").removeClass("d-none");
}

const selectExistingProgrameDuration = () => {
    $("#programme-duration-row").removeClass("d-none");
    $("#new-programme-duration-row").addClass("d-none");
}

const addNewEditProgrammeDuration = () => {
    $("#edit-programme-duration-row").addClass("d-none");
    $("#edit-new-programme-duration-row").removeClass("d-none");
}

const selectExistingEditProgrameDuration = (value) => {
    $("#edit-programme-duration-row").removeClass("d-none");
    $("#edit-new-programme-duration-row").addClass("d-none");
    $("#edit-programme-duration").val(value);
}


const initializeSelectedIntake = () => {
    const counters = document.querySelectorAll(".count-up");

    const animateCount = (counter) => {
        const target = parseFloat(counter.getAttribute("data-target"));
        const formatted = counter.getAttribute("data-formatted");
        const duration = 2250; // milliseconds
        const frameRate = 45; // fps
        const totalFrames = Math.round(duration / (1000 / frameRate));
        let frame = 0;

        const animate = () => {
            frame++;
            const progress = frame / totalFrames;
            const current = Math.round(target * progress);
            counter.innerText = current.toLocaleString();
            if (frame < totalFrames) {
                requestAnimationFrame(animate);
            } else {
                counter.innerText = formatted;
            }
        };

        requestAnimationFrame(animate);
    };

    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCount(entry.target);
                observer.unobserve(entry.target); // only once
            }
        });
    }, { threshold: 0.5 });

    counters.forEach(counter => observer.observe(counter));
}

const openLectureModal = (lectureUrl) => {
    const $modalBody = $("#lectureModalBody");
    $modalBody.empty();

    // Create loading spinner
    const $spinner = $("<div>", {
        class: "spinner-border dark-theme",
        role: "status",
        css: {
            display: "block",
            margin: "50px auto"
        }
    }).append($("<span>", { class: "visually-hidden", text: "Loading..." }));

    $modalBody.append($spinner);

    // Create iframe
    const $iframe = $("<iframe>", {
        src: lectureUrl,
        width: "100%",
        height: "500px",
        frameborder: "0",
        allow: "accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture",
        allowfullscreen: true,
        css: { display: "none" } // hide until loaded
    });

    $iframe.on("load", function () {
        $spinner.remove();
        $(this).show();
    });

    $modalBody.append($iframe);

    // Show modal
    const lectureModalEl = document.getElementById("lectureView");
    const lectureModal = new bootstrap.Modal(lectureModalEl);
    lectureModal.show();

    // Stop video when modal closes
    $(lectureModalEl).on("hidden.bs.modal", function () {
        $iframe.remove(); // remove iframe to stop video
    });
};

const handleStudentsQuizResetRequestPagination = (quizId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterStudentsQuizResetRequests(quizId);
}

const handleSubjectLectureAttendancePagination = (lectureId, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = 0;
            sortCondition1 = sortCondition1 === 1 ? 2 : 1;
            break;
        case 'sortById':
            sortCondition2 = sortCondition2 === 1 ? 2 : 1;
            sortCondition1 = 0;
            break;
    }

    filterSubjectLectureAttendance(lectureId);
}

const openModelEditLecture = (name, hourlyRate, assignmentFee, examinationFee, lecturerId) => {
    $("#lecturer-name").val(name);

    $("#edit-lecturer-hourly-rate").val(hourlyRate);
    $("#edit-assignment-fee").val(assignmentFee);
    $("#edit-examination-fee").val(examinationFee);

    $("#editLecturerBtn").off("click").on("click", function () {
        editSubjectAssignLecturer(this, lecturerId);
    });

    $("#editLecturer").modal("show");
}


const openUpdateInquiryStatusModal = () => {
    $("#updateInquiryStatus").modal("show");
}

const handleInquiriesPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterInquiries();
}


const initializeStudentInquiry = () => {
    const container = document.getElementById("userSelect");
    const display = container.querySelector(".custom-select-display");
    const options = container.querySelector(".custom-options");
    const searchInput = options.querySelector("input");
    const items = options.querySelectorAll("li");
    const hiddenInput = document.getElementById("userHidden");

    // Toggle dropdown
    display.addEventListener("click", () => {
        options.classList.toggle("show");
        searchInput.focus();
    });

    // Filter options
    searchInput.addEventListener("keyup", () => {
        let filter = searchInput.value.toLowerCase();
        items.forEach(item => {
            let text = item.textContent.toLowerCase();
            item.style.display = text.includes(filter) ? "block" : "none";
        });
    });

    // Select option
    items.forEach(item => {
        item.addEventListener("click", () => {
            display.textContent = item.textContent;
            hiddenInput.value = item.dataset.value;
            options.classList.remove("show");
        });
    });

    // Close if clicked outside
    document.addEventListener("click", e => {
        if (!container.contains(e.target)) {
            options.classList.remove("show");
        }
    });
}

const openAssignUserModal = () => {
    $("#assignUser").modal("show");
}

const handleLectureRecordingPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }
    filterLectureRecordings();
}

const handleReCorrectionAssignmentLecturersPayments = (id, action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterReCorrectionAssignmentLecturersPayments(id);
}

const handleLecturersPaymentsPagination = (action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterLecturersPayments();
}

const handleAssignmentLecturersPayments = (id, action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterAssignmentLecturersPayments(id);
}

const handleLecturersAssignmentsPaymentsPagination = (action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterLecturersAssignmentsPayments();
}

const handleLecturersLecturePaymentsPagination = (action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterLecturersLecturePayments();
}

const handleLecturersPayoutRequestPagination = (action, pageId = null, totalPages = null) => {
    //console.log(action, pageId, totalPages);
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterLecturersPayoutRequest();
}

const viewLectureRecording = (link) => {
    $("#lectureRecordingModalBody").html(link);
    $("#lectureRecordingModal").modal('show');
}

const initializeSubjectLecturerSchedule = (id) => {
    getSubjectLectureScheduleData(id);
}

const handlePortalSubscribedStudentsPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterPortalSubscribedStudents();
}

const copyToClipboardIntakeStudentId = (id, icon) => {
    navigator.clipboard.writeText(id);
    icon.setAttribute('icon', 'lucide:copy-check');
}

const handleLectureSchedulePermissionPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterLectureSchedulePermissions();
}

const searchModal = document.getElementById('searchModal');

searchModal.addEventListener('hidden.bs.modal', function () {
    document.getElementById('live-search-input').value = '';
    renderSearchHistory();
});

const moveOnSearchResult = (text, link) => {
    storeSearchTerm(text, link);
    $("#live-search-input").val('');
    window.location.href = link;
}

function storeSearchTerm(term, link) {
    if (!term.trim()) return;

    const MAX_HISTORY = 5;
    let history = JSON.parse(localStorage.getItem("recentSearches") || "[]");

    history = history.filter(item => item.text.toLowerCase() !== term.toLowerCase());

    history.unshift({ text: term, link: link });

    if (history.length > MAX_HISTORY) history = history.slice(0, MAX_HISTORY);

    localStorage.setItem("recentSearches", JSON.stringify(history));
}

function renderSearchHistory() {
    const history = JSON.parse(localStorage.getItem("recentSearches") || "[]");
    const container = document.querySelector('#live-search-results');
    container.innerHTML = '';

    if (history.length === 0) {
        const no_recent = `
        <div class="d-flex align-items-center justify-content-center py-3">
            <h6 class="text-muted text-16 fw-semibold mb-3">No recent searches</h5>
        </div>
        `;

        $("#live-search-results").html(no_recent);
        return;
    }

    const modal_body = `
                    <div id="search-results" class="list-group mt-2">
                        <h6 class="dark-theme text-16 fw-semibold mb-3">Recent</h6>
                        ${history.map((item, index) => `
                             <div class="list-group-item list-group-item-action d-flex align-items-center gap-3 p-3 bl-solid rounded-0 hover-bg">
                                <div class="icon-container d-flex align-items-center justify-content-center" onclick="window.location.href='${item.link}'">
                                    <iconify-icon icon="mdi:recent" width="20" height="20"></iconify-icon>
                                </div>

                                <div class="flex-grow-1 d-flex align-items-center" onclick="window.location.href='${item.link}'">
                                    <div class="fw-semibold text-dark-color px-3">${item.text}</div>
                                </div>
                                <div class="icon-container d-flex align-items-center justify-content-center">
                                    <iconify-icon icon="material-symbols-light:close" width="20" height="20" class="cursor-pointer" onclick="removeSearchItem('${item.text}')"></iconify-icon>
                                </div>
                            </div>
                            `).join('')}
                       
                    </div>
    `;

    $("#live-search-results").html(modal_body);

}

function removeSearchItem(term) {
    let history = JSON.parse(localStorage.getItem("recentSearches") || "[]");
    history = history.filter(item => item.text !== term);
    localStorage.setItem("recentSearches", JSON.stringify(history));
    renderSearchHistory();
}

const gradeTypeChanged = (select) => {
    if (select.value == 2) {
        if ($("#category-grade-type").hasClass("d-none")) {
            $("#category-grade-type").removeClass("d-none");
        }

        if ($("#rubrics-grade-type").hasClass("d-none") == false) {
            $("#rubrics-grade-type").addClass("d-none");
        }
    }

    if (select.value == 4) {
        $("#rubrics-container").html('');
        if ($("#category-grade-type").hasClass("d-none") == false) {
            $("#category-grade-type").addClass("d-none");
        }

        if ($("#rubrics-grade-type").hasClass("d-none")) {
            $("#rubrics-grade-type").removeClass("d-none");
            addNewRubric();
        }
    }
}

const initializeAddSubjectGradeCalculationStructure = () => {
    const container = document.getElementById("grade-scales-container");
    const addBtn = document.getElementById("add-new-grade-scale");

    const defaultGradeScales = [
        { min: 80, max: 100, grade: "A", gpa: 4.0 },
        { min: 65, max: 79, grade: "B", gpa: 3.0 },
        { min: 50, max: 64, grade: "C", gpa: 2.0 },
        { min: 35, max: 49, grade: "D", gpa: 1.0 },
        { min: 0, max: 34, grade: "F", gpa: 0.0 }
    ];

    const createGradeScaleBlock = (index, data = null) => {
        const newBlock = document.createElement("div");
        newBlock.classList.add("col-12", "mt-4");

        newBlock.innerHTML = `
            <div class="row bl-solid p-4 m-0 gy-3 grade-scale-block">
                <div class="d-flex justify-content-between p-0 m-0">
                    <span class="text-14 main-text p-0 m-0 grade-scale-title">Grade Scale - ${index}</span>
                    <iconify-icon class="remove-grade-scale-icon cursor-pointer ${index == 1 ? "d-none" : ""}"
                        icon="material-symbols:delete-outline-sharp"
                        width="24" height="24">
                    </iconify-icon>
                </div>

                <div class="col-12 col-lg-3 d-flex flex-column">
                    <label class="text-14 main-text required">Minimum Mark</label>
                    <input type="number" name="minMark" class="form-control custom-input mt-2"
                        value="${data ? data.min : ""}" placeholder="e.g. 40" />
                </div>

                <div class="col-12 col-lg-3 d-flex flex-column">
                    <label class="text-14 main-text required">Maximum Marks</label>
                    <input type="number" name="maxMark" class="form-control custom-input mt-2"
                        value="${data ? data.max : ""}" placeholder="e.g. 100" min="1" step="1" />
                </div>

                <div class="col-12 col-lg-3 d-flex flex-column">
                    <label class="text-14 main-text required">Grade</label>
                    <input type="text" name="grade" class="form-control custom-input mt-2"
                        value="${data ? data.grade : ""}" placeholder="e.g. A / Pass" />
                </div>

                <div class="col-12 col-lg-3 d-flex flex-column">
                    <label class="text-14 main-text not-required">GPA</label>
                    <input type="number" name="gpa" class="form-control custom-input mt-2"
                        value="${data ? data.gpa : ""}" placeholder="e.g. 4.0" step="0.1" />
                </div>
            </div>
        `;

        return newBlock;
    };

    defaultGradeScales.forEach((scale, index) => {
        const block = createGradeScaleBlock(index + 1, scale);
        container.appendChild(block);
    });


    addBtn.addEventListener("click", function () {
        const index = container.children.length + 1;
        const block = createGradeScaleBlock(index);
        container.appendChild(block);
        updateScaleTitles();
    });

    container.addEventListener("click", function (e) {
        if (e.target.closest(".remove-grade-scale-icon")) {
            const block = e.target.closest(".grade-scale-block");
            block.parentElement.remove();
            updateScaleTitles();
        }
    });

    function updateScaleTitles() {
        const titles = container.querySelectorAll(".grade-scale-title");
        titles.forEach((title, i) => {
            title.textContent = `Grade Scale - ${i + 1}`;
        });
    }
};

document.addEventListener("click", function (event) {

    if (event.target.closest(".remove-rubric")) {
        event.target.closest(".rubric-block").remove();
        reIndexRubrics();
    }

    if (event.target.closest(".add-rubric-criteria")) {
        const rubricIndex = event.target.closest(".add-rubric-criteria").dataset.rubric;
        const container = document.getElementById(`rubric-${rubricIndex}-criterias`);
        const count = container.querySelectorAll(".rubric-criteria-block").length + 1;

        container.insertAdjacentHTML(
            "beforeend",
            createCriteriaBlock(rubricIndex, count)
        );
        reIndexCriteria(rubricIndex);
    }

    if (event.target.closest(".remove-rubric-criteria")) {
        const rubricIndex = event.target.closest(".remove-rubric-criteria")
            .closest(".rubric-block").dataset.index;

        event.target.closest(".rubric-criteria-block").remove();
        reIndexCriteria(rubricIndex);
    }

});


const addNewRubric = () => {
    const container = document.getElementById("rubrics-container");
    const index = container.querySelectorAll(".rubric-block").length + 1;

    const rubricBlock = createRubricBlock(index);
    container.insertAdjacentHTML("beforeend", rubricBlock);

    reIndexRubrics();
};

function createRubricBlock(index) {
    return `
        <div class="col-12 rubric-block mt-4" id="rubric-${index}" data-index="${index}">
            <div class="row bl-solid p-4 m-0 gy-3">

                <div class="d-flex justify-content-between">
                    <span class="text-14 main-text rubric-title">Rubric - ${index}</span>
                    <iconify-icon class="remove-rubric cursor-pointer ${index == 1 ? "d-none" : ""}"
                        icon="material-symbols:delete-outline-sharp"
                        width="24" height="24"></iconify-icon>
                </div>

                <div class="col-12 col-lg-9">
                    <label class="text-14 main-text required">Rubric Name</label>
                    <input type="text" class="form-control custom-input mt-2"
                        id="rubric-name-${index}" placeholder="e.g. Group Project">
                </div>

                <div class="col-12 col-lg-3">
                    <label class="text-14 main-text required">Weight</label>
                    <input type="number" class="form-control custom-input mt-2"
                        id="rubric-weight-${index}" placeholder="e.g. 80" min="1">
                </div>

                <div class="d-flex justify-content-end">
                    <div class="d-flex align-items-center cursor-pointer add-rubric-criteria" 
                        data-rubric="${index}">
                        <iconify-icon icon="gridicons:add-outline" width="30" height="30"
                            class="dark-theme"></iconify-icon>
                        <span class="text-14 dark-theme px-2 pe-0">Add New Rubric Criteria</span>
                    </div>
                </div>

                <div class="col-12 rubric-criteria-container" id="rubric-${index}-criterias">
                    ${createCriteriaBlock(index, 1)}
                </div>
            </div>
        </div>
    `;
}


function createCriteriaBlock(rubricIndex, criteriaIndex) {
    return `
        <div class="row bl-solid p-4 mt-4 gy-3 rubric-criteria-block">
            <div class="d-flex justify-content-between">
                <span class="text-14 main-text criteria-title">
                    Rubric Criteria - ${criteriaIndex}
                </span>
                <iconify-icon class="remove-rubric-criteria cursor-pointer ${criteriaIndex == 1 ? "d-none" : ""}"
                    icon="material-symbols:delete-outline-sharp"
                    width="24" height="24"></iconify-icon>
            </div>

            <div class="col-12 col-lg-6">
                <label class="text-14 main-text required">Criteria Name</label>
                <input type="text" class="form-control custom-input mt-2"
                    placeholder="e.g. Communication Skills">
            </div>

            <div class="col-12 col-lg-3">
                <label class="text-14 main-text required">Weight</label>
                <input type="number" class="form-control custom-input mt-2"
                    placeholder="e.g. 10">
            </div>

            <div class="col-12 col-lg-3">
                <label class="text-14 main-text required">Maximum Mark</label>
                <input type="number" class="form-control custom-input mt-2"
                    placeholder="e.g. 20">
            </div>
        </div>
    `;
}

function reIndexRubrics() {
    document.querySelectorAll(".rubric-block").forEach((block, i) => {
        const newIndex = i + 1;
        block.dataset.index = newIndex;
        block.id = `rubric-${newIndex}`;

        block.querySelector(".rubric-title").textContent = `Rubric - ${newIndex}`;
        block.querySelector(".add-rubric-criteria").dataset.rubric = newIndex;

        block.querySelector(".remove-rubric").classList.toggle("d-none", newIndex === 1);

        reIndexCriteria(newIndex);
    });
}

function reIndexCriteria(rubricIndex) {
    const container = document.getElementById(`rubric-${rubricIndex}-criterias`);
    const criteriaBlocks = container.querySelectorAll(".rubric-criteria-block");

    criteriaBlocks.forEach((block, i) => {
        const newIndex = i + 1;
        block.querySelector(".criteria-title").textContent =
            `Rubric Criteria - ${newIndex}`;

        const deleteBtn = block.querySelector(".remove-rubric-criteria");
        deleteBtn.classList.toggle("d-none", newIndex === 1);
    });
}


function getGradeScalesData() {
    const container = document.getElementById("grade-scales-container");
    const blocks = container.querySelectorAll(".grade-scale-block");
    const gradeScales = [];

    blocks.forEach(block => {
        const minMark = block.querySelector('input[name="minMark"]').value;
        const maxMark = block.querySelector('input[name="maxMark"]').value;
        const grade = block.querySelector('input[name="grade"]').value;
        const gpaInput = block.querySelector('input[name="gpa"]');
        const gpa = gpaInput ? gpaInput.value : "";

        // Optionally convert to number where appropriate
        gradeScales.push({
            minMark: minMark === "" ? null : Number(minMark),
            maxMark: maxMark === "" ? null : Number(maxMark),
            grade: grade.trim(),
            gpa: gpa === "" ? null : Number(gpa)
        });
    });

    return gradeScales;
}

function getRubricData() {
    const container = document.getElementById("rubrics-container");
    const rubricBlocks = container.querySelectorAll(".rubric-block");
    const rubricData = [];

    rubricBlocks.forEach((rubric, rIndex) => {
        const rubricIndex = rIndex + 1;

        const rubricName = rubric.querySelector(`#rubric-name-${rubricIndex}`).value.trim();
        const rubricWeight = rubric.querySelector(`#rubric-weight-${rubricIndex}`).value.trim();

        const criteriaContainer = rubric.querySelector(`#rubric-${rubricIndex}-criterias`);
        const criteriaBlocks = criteriaContainer.querySelectorAll(".rubric-criteria-block");

        const criteriaData = [];

        criteriaBlocks.forEach((criteria, cIndex) => {
            const inputs = criteria.querySelectorAll("input");

            criteriaData.push({
                criteriaName: inputs[0].value.trim(),
                weight: Number(inputs[1].value) || null,
                maxMark: Number(inputs[2].value) || null
            });
        });

        rubricData.push({
            rubricName: rubricName,
            rubricWeight: Number(rubricWeight) || null,
            criterias: criteriaData
        });
    });

    return rubricData;
}

function getCategoryGradeValues() {
    const inputs = document.querySelectorAll('#category-grade-type input');
    const results = [];

    inputs.forEach(input => {
        const id = input.id;
        const value = input.value.trim();

        const categoryId = id.split("-")[2];

        results.push({
            category_id: Number(categoryId),
            weight: value === "" ? null : Number(value)
        });
    });

    return results;
}


const openEditPaymentTypeModal = (id, name) => {
    //console.log(id, name);
    $("#edit-type-name").val(name);

    $("#save-edit-type").on("click", function () {
        editPaymentType(this, id);
    });

    $("#editPaymentType").modal("show");
}

const changePaneltyType = (select) => {
    if (select.value == 1) {
        if ($("#days-area").hasClass('d-none') == false) {
            $("#days-area").addClass('d-none');
        }
        if ($("#edit-days-area").hasClass('d-none') == false) {
            $("#edit-days-area").addClass('d-none');
        }
    } else if (select.value == 2) {
        if ($("#days-area").hasClass('d-none')) {
            $("#days-area").removeClass('d-none');
        }
        if ($("#edit-days-area").hasClass('d-none')) {
            $("#edit-days-area").removeClass('d-none');
        }
    }
}

const changePaneltyPaymentType = (select) => {
    if (select.value == 2) {
        if ($("#currency-area").hasClass('d-none') == false) {
            $("#currency-area").addClass('d-none');
        }

        $("#amount-label").text("Precentage");

    } else if (select.value == 1) {
        if ($("#currency-area").hasClass('d-none')) {
            $("#currency-area").removeClass('d-none');
        }

        $("#amount-label").text("Amount");
    }
}

const editChangePaneltyType = (select) => {
    if (select.value == 1) {

        if ($("#edit-days-area").hasClass('d-none') == false) {
            $("#edit-days-area").addClass('d-none');
        }
    } else if (select.value == 2) {

        if ($("#edit-days-area").hasClass('d-none')) {
            $("#edit-days-area").removeClass('d-none');
        }
    }
}

const editChangePaneltyPaymentType = (select) => {
    if (select.value == 2) {
        if ($("#edit-currency-area").hasClass('d-none') == false) {
            $("#edit-currency-area").addClass('d-none');
        }
        $("#edit-amount-label").text("Precentage");

    } else if (select.value == 1) {

        if ($("#edit-currency-area").hasClass('d-none')) {
            $("#edit-currency-area").removeClass('d-none');
        }
        $("#edit-amount-label").text("Amount");
    }
}

const openPaneltyPaymentCategoryEditModal = (data) => {
    $("#edit-option").val(data.panelty_payment_option_id);
    $("#edit-type").val(data.panelty_payments_type_id);
    $("#edit-currency").val(data.currency_id);
    $("#edit-panelty-amount").val(data.panelty_amount);
    $("#edit-panelty-days").val(data.due_dates);

    if (data.panelty_payments_type_id == 1) {
        $("#edit-currency-area").removeClass('d-none');
    }

    if (data.panelty_payment_option_id == 2) {
        $("#edit-days-area").removeClass('d-none');
    }

    $("#edit-panelty-type-btn").on("click", function () {
        editPaneltyPaymentCategory(this, data.panelty_payment_category_id);
    });

    $("#editPaneltyType").modal('show');
}

$(document).on("contextmenu", "#intake-row", function (e) {
    e.preventDefault();
    const intakeId = $(this).data("intake-id");
    openContextMenu(e, intakeId);
});

const openContextMenu = (e, intakeId) => {
    e.preventDefault();

    $("#custom-context-menu")
        .css({
            top: e.pageY,
            left: e.pageX
        })
        .removeClass("d-none");

    $("#duplicate-intake").on("click", function () {
        duplicateIntake(intakeId);
    });
};


let selectedCategories = [];

const checkPaneltyCategory = (input, categoryId) => {
    const optionType = input.dataset.option;
    if (optionType === "1") {

        if (input.checked) {

            document.querySelectorAll('input[data-option]').forEach(cb => {
                cb.checked = false;
            });

            input.checked = true;
            selectedCategories = [categoryId];

        } else {
            selectedCategories = [];
        }
    }

    else if (optionType === "2") {

        const currentDate = input.dataset.dates;

        document.querySelectorAll('input[data-option="1"]').forEach(cb => {
            cb.checked = false;
        });

        selectedCategories = selectedCategories.filter(id => {
            const el = document.querySelector(`input[data-category-id="${id}"]`);
            return el && el.dataset.option === "2";
        });

        if (input.checked) {
            document.querySelectorAll(
                `input[data-option="2"][data-dates="${currentDate}"]`
            ).forEach(cb => {
                if (cb !== input) {
                    cb.checked = false;

                    const removeId = parseInt(cb.getAttribute("data-category-id"));
                    selectedCategories = selectedCategories.filter(id => id !== removeId);
                }
            });

            if (!selectedCategories.includes(categoryId)) {
                selectedCategories.push(categoryId);
            }

        } else {
            selectedCategories = selectedCategories.filter(id => id !== categoryId);
        }
    }

    console.log(selectedCategories);
};

function viewPaymentPlanHasPaymentTypes(planId) {
    const allAreas = document.querySelectorAll('.payment-types-display-area');
    allAreas.forEach(area => {
        const areaPlanId = area.getAttribute('data-plan');

        if (areaPlanId == planId) {
            area.classList.remove('d-none');
        } else {
            area.classList.add('d-none');
        }
    });
}

const openAddPaymentTypeModal = (planId, planName) => {
    $("#plan-name").val(planName);

    $('#add-payment-type').off('click');
    $('#add-payment-type').on('click', function () {
        addPaymentType(this, planId);
    });

    $("#addPaymentType").modal('show');
}

function enablePanelty(checkbox) {
    const installmentId = checkbox.dataset.installmentId;

    const optionArea = document.querySelector(
        `#panelty-option-area[data-installment-id="${installmentId}"]`
    );

    if (checkbox.checked) {
        optionArea.classList.remove("d-none");
    } else {
        optionArea.classList.add("d-none");

        hideFlatAndCustom(installmentId);
    }
}


function paneltyOptionChange(select) {
    const installmentId = select.dataset.installmentId;
    const selected = select.value;

    hideFlatAndCustom(installmentId);

    if (selected === "2" || selected === "3") {
        // Flat or Per Day
        document
            .querySelector(`#panelty-flat-area[data-installment-id="${installmentId}"]`)
            .classList.remove("d-none");
    }

    if (selected === "4") {
        // Custom
        document
            .querySelector(`#panelty-custom-area[data-installment-id="${installmentId}"]`)
            .classList.remove("d-none");
    }
}

function hideFlatAndCustom(installmentId) {
    document
        .querySelector(`#panelty-flat-area[data-installment-id="${installmentId}"]`)
        ?.classList.add("d-none");

    document
        .querySelector(`#panelty-custom-area[data-installment-id="${installmentId}"]`)
        ?.classList.add("d-none");
}
function addNewCustomPaneltyArea(btn, installmentId) {
    const wrapper = btn.closest('#panelty-custom-area');
    const container = wrapper.querySelector('#custom-slots');

    const count = container.querySelectorAll('.custom-slot').length + 1;

    const html = `
    <div class="col-12 bl-solid p-0 m-0 mt-4 custom-slot">

        <div class="row p-4 gy-3 gx-3 align-content-center ">
            <div class="d-flex justify-content-between">
                <span class="text-14 sub-topic">Custom Date Slot - ${count}</span>
                <iconify-icon icon="carbon:close-filled"
                    width="20"
                    height="20"
                    class="main-text cursor pointor"
                
                    onclick="removeCustomPanelty(this)">
                </iconify-icon>
            </div>

            <div class="col-12 col-lg-4 d-flex flex-column">
                <label class="text-14 main-text required">Panelty Amount Type</label>
                <select class="form-select custom-select mt-2 panelty-type">
                    <option value="0">- Select -</option>
                    <option value="1">Fixed</option>
                    <option value="2">Percentage</option>
                </select>
            </div>

            <div class="col-12 col-lg-4 d-flex flex-column">
                <label class="text-14 main-text required">Amount</label>
                <input type="number" class="form-control custom-input mt-2 panelty-amount">
            </div>

            <div class="col-12 col-lg-4 d-flex flex-column">
                <label class="text-14 main-text required">Days</label>
                <input type="number" class="form-control custom-input mt-2 panelty-days">
            </div>
        </div>
        </div>
    `;

    container.insertAdjacentHTML("beforeend", html);
}
function removeCustomPanelty(icon) {
    icon.closest(".custom-slot").remove();
}

function changenstallmentDiscountType(select) {
    const installmentId = select.dataset.installmentId;
    const value = select.value;

    // Find the correct accordion item
    const accordionItem = select.closest('.accordion-item');

    const amountArea = accordionItem.querySelector('#discount-amount-area');
    const noteArea = accordionItem.querySelector('#discount-note-area');

    // Hide by default
    amountArea.classList.add('d-none');
    noteArea.classList.add('d-none');

    // Reset values when hidden
    accordionItem.querySelector('#discount').value = '';
    accordionItem.querySelector('#discount-note').value = '';

    // Show only for discount types 2 or 3
    if (value === '2' || value === '3') {
        amountArea.classList.remove('d-none');
        noteArea.classList.remove('d-none');
    }
}

let planHasPaymentTypes = [];
let maximumAllowedPayments = 0;

function addNewPaymentArea() {
    const container = document.getElementById("fees-type");

    const nextId = container.children.length + 1;

    const paymentHTML = `
    <div class="accordion-item br-0" data-installment-id="${nextId}">
                                                                    <h2 class="accordion-header br-0" id="headingOne${nextId}">
                                                                        <button class="accordion-button br-0 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne${nextId}" aria-expanded="false" aria-controls="collapseOne${nextId}">
                                                                             <span class="flex-grow-1 payment-btn-text">Payment - ${nextId}</span>
                                                                            <iconify-icon
                                                                                icon="material-symbols:delete-outline-rounded"
                                                                                width="22"
                                                                                height="22"
                                                                                class="text-danger cursor-pointer"
                                                                                onclick="removePaymentArea(this)">
                                                                            </iconify-icon>
                                                                        </button>
                                                                    </h2>
                                                                    <div id="collapseOne${nextId}" class="accordion-collapse collapse" aria-labelledby="headingOne${nextId}" data-bs-parent="#fees-type">
                                                                        <div class="accordion-body">
                                                                            <div class="col-12 component-theme">
                                                                                <div class="row p-0 gy-3 gx-3 align-content-center">
                                                                                    <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                                        <label class="text-14 main-text required" for="">Payment Type</label>
                                                                                        <select id="payment-type" data-installment-id="${nextId}" class="form-select custom-select mt-2 payment-type">
                                                                                            <option selected value="0">- Select -</option>
                                                                                        </select>
                                                                                        <span id="payment-type-feedback" class="text-dark-red text-12"></span>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                                        <label for="" class="text-14 main-text required">Amount</label>
                                                                                        <input type="number" class="form-control custom-input mt-2" id="amount">
                                                                                        <span class="text-dark-red text-12" id="amount-feedback"></span>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                                        <label class="text-14 main-text not-required" for="">Discount Type</label>
                                                                                        <select id="discount-type" class="form-select custom-select mt-2" data-installment-id="${nextId}" onchange="changenstallmentDiscountType(this)">
                                                                                            <option selected value="1">No Discount</option>
                                                                                            <option value="2">Fixed Amount Discount</option>
                                                                                            <option value="3">Percentage Based Discount</option>
                                                                                        </select>
                                                                                        <span id="discount-type-feedback" class="text-dark-red text-12"></span>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-6 col-lg-3 d-flex flex-column d-none" id="discount-amount-area">
                                                                                        <label class="text-14 main-text required" id="discount-label" for="">Discount</label>
                                                                                        <input type="number" class="form-control custom-input mt-2" id="discount" min="0">
                                                                                        <span id="discount-feedback" class="text-dark-red text-12"></span>
                                                                                    </div>
                                                                                    <div class="col-12 d-flex flex-column d-none" id="discount-note-area">
                                                                                        <label class="text-14 main-text" id="discount-note-label" for="">Discount Note</label>
                                                                                        <textarea id="discount-note" class="form-control custom-input mt-2" rows="5"></textarea>
                                                                                        <span id="discount-note-feedback" class="text-dark-red text-12"></span>
                                                                                    </div>
                                                                                    <div class="col-12 col-md-6 col-lg-3 d-flex flex-column">
                                                                                        <label class="text-14 main-text not-required" for="">Due Date</label>
                                                                                        <input type="date" class="form-control custom-input mt-2" id="due-date" min="0">
                                                                                        <span id="due-date-feedback" class="text-dark-red text-12"></span>
                                                                                    </div>
                                                                                    <div class="col-12 d-flex align-items-center">
                                                                                        <input type="checkbox" class="form-check-input hw-20 m-0 p-0" id="enable-panelty" data-installment-id="${nextId}" onchange="enablePanelty(this)">
                                                                                        <label class="text-14 main-text px-3" for="">Enable Panelty Payment</label>
                                                                                    </div>

                                                                                    <div class="col-12 col-lg-4 d-flex flex-column d-none" data-installment-id="${nextId}" id="panelty-option-area">
                                                                                        <label class="text-14 main-text required" for="">Panelty Options</label>
                                                                                        <select id="panelty-option" class="form-select custom-select mt-2" data-installment-id="${nextId}" onchange="paneltyOptionChange(this)">
                                                                                            <option selected value="0">- Select -</option>
                                                                                            <option value="2">Flat Amount</option>
                                                                                            <option value="3">Per Day</option>
                                                                                            <option value="4">Custom Date Slots</option>
                                                                                        </select>
                                                                                        <span id="panelty-option-feedback" data-installment-id="${nextId}" class="text-dark-red text-12"></span>
                                                                                    </div>

                                                                                    <div class="col-12 d-none" id="panelty-flat-area" data-installment-id="${nextId}">
                                                                                        <div class="row p-0 gy-3 gx-3 align-content-center">
                                                                                            <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                <label for="" class="text-14 main-text required">Panelty Amount Type</label>
                                                                                                <select class="form-select custom-select mt-2" id="flat-type">
                                                                                                    <option value="0">- Select -</option>
                                                                                                    <option value="1">Fixed Amount</option>
                                                                                                    <option value="2">Percentage</option>
                                                                                                </select>
                                                                                                <span class="text-12 text-dark-red" id="flat-type-feedback"></span>
                                                                                            </div>
                                                                                            <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                <label for="" class="text-14 main-text required" id="amount-label">Amount</label>
                                                                                                <input type="number" class="form-control custom-input mt-2" id="flat-amount">
                                                                                                <span class="text-12 text-dark-red" id="flat-amount-feedback"></span>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>

                                                                                    <div class="col-12 d-flex flex-column d-none" id="panelty-custom-area" data-installment-id="${nextId}">
                                                                                        <div class="row p-0 m-0" id="custom-slots">

                                                                                            <div class="col-12 bl-solid p-0 m-0 mt-4 custom-slot">
                                                                                                <div class="row p-4 gy-3 gx-3 align-content-center ">
                                                                                                    <div class="d-flex justify-content-between">
                                                                                                        <span class="text-14 sub-topic">Custom Date Slot - 1</span>

                                                                                                    </div>

                                                                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                        <label class="text-14 main-text required">Panelty Amount Type</label>
                                                                                                        <select class="form-select custom-select mt-2 panelty-type">
                                                                                                            <option value="0">- Select -</option>
                                                                                                            <option value="1">Fixed</option>
                                                                                                            <option value="2">Percentage</option>
                                                                                                        </select>
                                                                                                    </div>

                                                                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                        <label class="text-14 main-text required">Amount</label>
                                                                                                        <input type="number" class="form-control custom-input mt-2 panelty-amount">
                                                                                                    </div>

                                                                                                    <div class="col-12 col-lg-4 d-flex flex-column">
                                                                                                        <label class="text-14 main-text required">Days</label>
                                                                                                        <input type="number" class="form-control custom-input mt-2 panelty-days">
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                                        <div class="col-12 p-0 m-0 d-flex justify-content-end mt-3">
                                                                                            <button class="custom-btn col-6 col-lg-2" onclick="addNewCustomPaneltyArea(this, ${nextId});">Add New</button>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>`;

    container.insertAdjacentHTML("beforeend", paymentHTML);

    loadPaymentTypesForInstallment(nextId);

    if (nextId + 1 > maximumAllowedPayments) {
        $("#add-new-payment-area").hasClass('d-none') ? '' : $("#add-new-payment-area").addClass('d-none');
    }
}

function loadPaymentTypesForInstallment(installmentId) {
    const select = document.querySelector(
        `.payment-type[data-installment-id="${installmentId}"]`
    );

    if (!select) return;

    let options = `<option value="0">- Select -</option>`;

    planHasPaymentTypes.forEach(type => {
        options += `<option value="${type.payment_type_id}">
                        ${type.payment_type}
                    </option>`;
    });

    select.innerHTML = options;
}
function removePaymentArea(icon) {
    const item = icon.closest(".accordion-item");
    item.remove();
    reindexPayments();
}
function reindexPayments() {
    const items = document.querySelectorAll("#fees-type .accordion-item");

    items.forEach((item, index) => {
        const newId = index + 1;

        item.setAttribute("data-installment-id", newId);
        item.querySelector(".payment-btn-text").innerText = `Payment - ${newId}`;
    });

    if (items.length < maximumAllowedPayments) {
        $("#add-new-payment-area").hasClass('d-none') ? $("#add-new-payment-area").removeClass('d-none') : '';
    }
}

function collectInstallmentData() {
    const data = [];
    document.querySelectorAll(".accordion-item").forEach(item => {
        const installmentId =
            item.querySelector("#enable-panelty")?.dataset.installmentId;

        const installment = {
            installmentId,
            paymentType: item.querySelector("#payment-type")?.value,
            amount: item.querySelector("#amount")?.value,
            discountType: item.querySelector("#discount-type")?.value,
            discount: item.querySelector("#discount")?.value,
            discountNote: item.querySelector("#discount-note")?.value,
            dueDate: item.querySelector("#due-date")?.value,
            penaltyEnabled: item.querySelector("#enable-panelty")?.checked,
            penalty: null
        };

        if (installment.penaltyEnabled) {
            const option = item.querySelector("#panelty-option")?.value;

            if (option === "2" || option === "3") {
                installment.penalty = {
                    mode: option,
                    type: item.querySelector("#flat-type")?.value,
                    amount: item.querySelector("#flat-amount")?.value,
                };
            }

            if (option === "4") {
                installment.penalty = {
                    mode: option,
                    slots: []
                };

                item.querySelectorAll(".custom-slot").forEach(slot => {
                    installment.penalty.slots.push({
                        type: slot.querySelector(".panelty-type")?.value,
                        amount: slot.querySelector(".panelty-amount")?.value,
                        days: slot.querySelector(".panelty-days")?.value
                    });
                });
            }
        }

        data.push(installment);
    });

    return data;
}


function generatePaymentErrorPDF(errorData) {

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'mm', 'a4');

    let y = 15;

    doc.setFontSize(18);
    doc.text('Payment Validation Errors', 14, y);
    y += 8;

    doc.setFontSize(10);
    doc.text(`Generated on: ${new Date().toLocaleString()}`, 14, y);
    y += 10;

    errorData.installments.forEach((installment, index) => {

        doc.setFontSize(14);
        doc.text(`Fees Type : ${installment.installmentId}`, 14, y);
        y += 6;

        const tableBody = [];

        Object.entries(installment.errors).forEach(([field, error]) => {

            if (field === 'panelty') {
                Object.entries(error).forEach(([key, msg]) => {
                    const slotNo = parseInt(key.split('.')[1]) + 1;
                    tableBody.push([
                        `Penalty Slot ${slotNo}`,
                        msg
                    ]);
                });
            } else {
                tableBody.push([
                    field.replace(/([A-Z])/g, ' $1'),
                    error
                ]);
            }
        });

        doc.autoTable({
            startY: y,
            head: [['Field', 'Error Message']],
            body: tableBody,
            theme: 'grid',
            styles: { fontSize: 10 },
            headStyles: { fillColor: [220, 220, 220] }
        });

        y = doc.lastAutoTable.finalY + 10;
    });

    doc.output('bloburl');
    window.open(doc.output('bloburl'), '_blank');
}
const handleSelectedPaymentCategoryStudents = (id, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = sortCondition3 = 0;
            sortCondition1 = sortCondition1 === 1 ? 2 : 1;
            break;
        case 'sortByDate':
            sortCondition1 = sortCondition3 = 0;
            sortCondition2 = sortCondition2 == 1 ? 2 : 1;
            break;
        case 'sortById':
            sortCondition1 = sortCondition2 = 0;
            sortCondition3 = sortCondition3 == 1 ? 2 : 1;
            break;
    }

    filterPaymentCategoryStudents(id);
}

let selectedDays = [];

const dateChipBoxAction = (clickedElement) => {
    const chip = $(clickedElement);
    const day = chip.data('day-id');

    chip.toggleClass('selected');

    if (chip.hasClass('selected')) {
        selectedDays.push(day);
    } else {
        selectedDays = selectedDays.filter(d => d !== day);
    }

};

const handleBranchesPermissionPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterBranchesPermissions();
}


const handleBranchPermissionPagination = (id, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterBranchPermissions(id);
}


const initializeAddStudent = () => {
    const firstNameInput = document.getElementById("first-name");
    const lastNameInput = document.getElementById("last-name");
    const fullNameInput = document.getElementById("full-name");
    const initialsInput = document.getElementById("name-with-initials");

    function updateNames() {

        const firstName = firstNameInput.value.trim();
        const lastName = lastNameInput.value.trim();

        if (firstName || lastName) {
            fullNameInput.value = (firstName + " " + lastName).trim();
        } else {
            fullNameInput.value = "";
        }

        if (firstName && lastName) {

            const parts = firstName.split(/\s+/);

            const initials = parts
                .filter(p => p.length > 0)
                .map(p => p.charAt(0).toUpperCase() + ".")
                .join(" ");
            initialsInput.value = initials + " " + lastName;
        } else {
            initialsInput.value = "";
        }
    }

    // Trigger on typing
    firstNameInput.addEventListener("input", updateNames);
    lastNameInput.addEventListener("input", updateNames);


}

const handlePortalSubscriptionPaymentPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterPortalSubscriptionPayment();
}

let qrReader = null;
let isScanning = false;

let studentMap = new Map();

const initializeAttendnace = () => {
    studentsData.forEach(student => {
        studentMap.set(student.student_id, student);
    });

}

const beepAudio = new Audio("./assets/sound/beep_sound.mp3");

function playBeep() {
    beepAudio.currentTime = 0;
    beepAudio.play().catch(() => { });
}

const openScannerModal = () => {

    const modalEl = document.getElementById('qrScannerModal');
    const modal = new bootstrap.Modal(modalEl);

    modal.show();

    // Start scanner AFTER modal is visible
    modalEl.addEventListener('shown.bs.modal', startScanner, { once: true });
    modalEl.addEventListener('hidden.bs.modal', stopScanner, { once: true });
};

const startScanner = async () => {

    if (isScanning) return;

    try {
        if (!qrReader) {
            qrReader = new Html5Qrcode("qr-reader");
        }

        isScanning = true;

        await qrReader.start(
            { facingMode: "environment" },
            {
                fps: 10,
                qrbox: { width: 250, height: 250 }
            },

            async (decodedText) => {

                playBeep();

                await stopScanner();

                // close scanner modal
                const scanModal = bootstrap.Modal.getInstance(
                    document.getElementById('qrScannerModal')
                );
                scanModal.hide();

                // 🔥 Show student modal FIRST
                openStudentModal();

                // 🔄 show loader
                toggleStudentLoader(true);

                // ⚡ get student instantly (from preloaded map)
                const student = studentMap.get(decodedText);

                if (student) {
                    showStudentCard(student);
                    markInOutStatus(decodedText);
                } else {

                    showStudentNotFound(decodedText);
                }
            },

            () => { }
        );

    } catch (err) {
        console.error("Camera error:", err);
        isScanning = false;
    }
};
const stopScanner = async () => {
    if (qrReader && isScanning) {
        try {
            await qrReader.stop();
            await qrReader.clear();
        } catch (e) { }

        isScanning = false;
    }
};

function openStudentModal() {
    const modal = new bootstrap.Modal(document.getElementById('studentModal'));
    modal.show();
}
function toggleStudentLoader(show) {
    document.getElementById("student-loader").classList.toggle("d-none", !show);
    document.getElementById("student-content").classList.toggle("d-none", show);
}
function showStudentCard(student) {

    document.getElementById("student-name").innerText =
        student.title + "." + student.first_name + " " + student.last_name;

    document.getElementById("reg-no").innerText = student.student_id;
    document.getElementById("course-name").innerText = student.programme;
    document.getElementById("nic").innerText = student.nic || student.passport;
    const address = [student.address_line_1, student.address_line_2]
        .filter(val => val && val.trim() !== "")
        .join(", ");

    setStudentImage(student.profile_img);

    toggleStudentLoader(false);
}

function showMarkSuccess() {

    const statusEl = document.getElementById("mark-status");

    statusEl.innerHTML = "✅ Marked Successfully";
    statusEl.classList.remove("text-danger");
    statusEl.classList.add("text-success");
}

function updateMarkStatus(type, message) {
    const el = document.getElementById("mark-status");

    if (!el) {
        console.error("mark-status element not found!");
        return;
    }

    switch (type) {
        case "check-in":
            el.className = "status-badge status-success";
            el.innerHTML = `
                <iconify-icon icon="mdi:login-variant"></iconify-icon>
                ${message}
            `;
            break;

        case "check-out":
            el.className = "status-badge status-primary";
            el.innerHTML = `
                <iconify-icon icon="mdi:logout-variant"></iconify-icon>
                ${message}
            `;
            break;

        case "marked":
            el.className = "status-badge status-warning";
            el.innerHTML = `
                <iconify-icon icon="mdi:information-outline"></iconify-icon>
                ${message}
            `;
            break;

        case "error":
            el.className = "status-badge status-error";
            el.innerHTML = `
                <iconify-icon icon="mdi:close-circle-outline"></iconify-icon>
                ${message}
            `;
            break;

        default:
            el.className = "status-badge status-secondary";
            el.innerHTML = `
                <iconify-icon icon="mdi:help-circle-outline"></iconify-icon>
                ${message}
            `;
            break;
    }
}
function setStudentImage(imgName) {
    const img = document.getElementById("student-image");

    img.src = "./assets/imgs/students/" + imgName;

    img.onerror = function () {
        img.src = "./assets/imgs/students/default-user.png";
    };

}

function showStudentNotFound(qr) {
    toggleStudentLoader(false);

    document.getElementById("student-name").innerText = "Student Not Found";
    document.getElementById("student-email").innerText = "QR: " + qr;

    document.getElementById("student-image").src = "./assets/profile/default.png";
    const statusEl = document.getElementById("mark-status");

    statusEl.className = "status-badge status-error";
    statusEl.innerHTML = `
        <iconify-icon icon="mdi:alert-circle-outline"></iconify-icon>
        Invalid QR Code
    `;

    document.getElementById("mark-loader").classList.add("d-none");
}

const handleAttendnacePagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = 0;
            sortCondition1 = sortCondition1 === 1 ? 2 : 1;
            break;
        case 'sortByDate':
            sortCondition1 = 0;
            sortCondition2 = sortCondition2 == 1 ? 2 : 1;
            break;
    }

    filterAttendnace();
}

const changeDiscountType = (select) => {
    if (select.value == 1) {
        if ($('#discount-area').hasClass('d-none') == false) {
            $('#discount-area').addClass('d-none');
            $('#discount-note-area').addClass('d-none');
        }
    } else {
        if ($('#discount-area').hasClass('d-none')) {
            $('#discount-area').removeClass('d-none');
            $('#discount-note-area').removeClass('d-none');
        }
    }
}

const getInstallmentPenalty = () => {
    const isEnabled = document.querySelector("#enable-panelty")?.checked;

    if (!isEnabled) return null;

    const option = document.querySelector("#panelty-option")?.value;

    // Flat / Percentage
    if (option === "2" || option === "3") {
        return {
            mode: option,
            type: document.querySelector("#flat-type")?.value,
            amount: document.querySelector("#flat-amount")?.value,
        };
    }

    // Slot-based penalties
    if (option === "4") {
        const slots = [];

        document.querySelectorAll(".custom-slot").forEach(slot => {
            slots.push({
                type: slot.querySelector(".panelty-type")?.value,
                amount: slot.querySelector(".panelty-amount")?.value,
                days: slot.querySelector(".panelty-days")?.value
            });
        });

        return {
            mode: option,
            slots
        };
    }

    return null;
};

function collectPaymentData() {

    const penaltyEnabled = document.querySelector("#enable-panelty")?.checked || false;
    const installmentId = document.querySelector("#enable-panelty")?.dataset.installmentId || null;

    const payment = {
        installmentId: installmentId,
        paymentType: document.querySelector("#payment-type")?.value || null,
        amount: document.querySelector("#payment-amount")?.value || null,
        discountType: document.querySelector("#discount-type")?.value || null,
        discount: document.querySelector("#discount")?.value || null,
        discountNote: document.querySelector("#discount-note")?.value || null,
        dueDate: document.querySelector("#due-date")?.value || null,
        penaltyEnabled: penaltyEnabled,
        note: document.querySelector("#payment-note")?.value || null,
        penalty: null
    };

    if (penaltyEnabled) {
        const option = document.querySelector("#panelty-option")?.value;

        // Flat / percentage penalty
        if (option === "2" || option === "3") {
            payment.penalty = {
                mode: option,
                type: document.querySelector("#flat-type")?.value || null,
                amount: document.querySelector("#flat-amount")?.value || null
            };
        }

        // Slot-based penalty
        if (option === "4") {
            payment.penalty = {
                mode: option,
                slots: []
            };

            document.querySelectorAll(".custom-slot").forEach(slot => {
                payment.penalty.slots.push({
                    type: slot.querySelector(".panelty-type")?.value || null,
                    amount: slot.querySelector(".panelty-amount")?.value || null,
                    days: slot.querySelector(".panelty-days")?.value || null
                });
            });
        }
    }

    return payment;
}

const handleSelectedPaymentStatus = (value) => {
    if (value == 6) {
        $("#payment-receipt-send-options").hasClass('d-none') ? $("#payment-receipt-send-options").removeClass('d-none') : '';
    } else {
        $("#payment-receipt-send-options").hasClass('d-none') ? '' : $("#payment-receipt-send-options").addClass('d-none');
    }
}

function openInvoicePrintWindow(invoice) {
    const printWindow = window.open('', '_blank', 'width=1000,height=900');

    const html = `
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Invoice</title>

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

      <style>
    @page {
        size: A4;
        margin: 0;
    }

    * {
        box-sizing: border-box;
    }

    html, body {
        margin: 0;
        padding: 0;
        width: 210mm;
        height: 297mm;
        font-family: Arial, Helvetica, sans-serif !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    body {
        display: flex;
        justify-content: center;
        align-items: flex-start;
    }

    .print-page {
        width: 210mm;
        height: 297mm;
        background: #ffffff;
        position: relative;
        overflow: hidden;
    }

    .invoice-card {
        width: 100%;
        height: 100%;
        padding: 18mm 18mm 18mm 18mm;
        position: relative;
    }

    .invoice-body {
        padding-bottom: 32mm;
    }

    .brand-logo {
        width: 300px;
        max-width: 300px;
        height: auto;
        object-fit: contain;
        display: block;
    }

    .company-address {
        margin-top: 8px;
        font-size: 20px;
        line-height: 1.45;
        color: #617188;
        font-weight: 500;
    }

    .invoice-title {
        text-align: right;
        padding-top: 2px;
    }

    .invoice-title h1 {
        margin: 0;
        font-size: 40px;
        line-height: 1.05;
        font-weight: 700;
        color: #4c555b;
        letter-spacing: 0.1px;
    }

    .invoice-number {
        margin-top: 9px;
        font-size: 25px;
        font-weight: 700;
        color: #4c555b;
        letter-spacing: 0.2px;
    }

    .info-section {
        margin-top: 34px;
    }

    .info-label {
        display: block;
       font-size: 18px;
        font-weight: 700;
        color: #4c555b;
        margin-bottom: 4px;
        line-height: 1.2;
    }

    .info-value {
       font-size: 18px;
        line-height: 1.3;
        color: #617188;
        font-weight: 500;
        word-break: break-word;
    }

    .invoice-table-wrap {
        margin-top: 56px;
    }

   .invoice-table {
    width: 100%;
    border-collapse: collapse;
    border-top: 1px solid #F3F4F4; /* TOP LINE */
}

.invoice-table thead th {
    font-size: 18px;
    font-weight: 700;
    color:#4c555b;
    padding: 12px 0;
    border-bottom: 1px solid #F3F4F4; /* HEADER LINE */
}

.invoice-table tbody td {
    font-size: 18px;
    color: #617188;
    padding: 14px 0;
    vertical-align: top;
    border-bottom: 1px solid #F3F4F4; /* ROW LINE */
}
    .remarks-row td {
    border-bottom: none;
    padding-top: 12px;
      padding-top: 9px;
        color: #617188;
}

    .amount-col {
        text-align: right;
        white-space: nowrap;
    }
    
    .signature-section {
        margin-top: 54px;
        width: 150px;
        text-align: center;
    }

    .signature-line {
        width: 150px;
        border-top: 1px solid #4f647c;
        margin-bottom: 5px;
    }

    .signature-text {
       font-size: 18px;
        color: #617188;
        font-weight: 500;
    }

    .invoice-footer {
        position: absolute;
        left: 18mm;
        right: 18mm;
        bottom: 14mm;
        border-top: 1px solid #d8dde5;
        padding-top: 9px;
        text-align: center;
    }

    .content-div-bottom {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0;
    }

    .sub-text {
        font-size: 10px;
        color: #6e7b8c;
        margin: 0;
        line-height: 1.35;
        font-weight: 500;
    }

    .footer-link {
        text-decoration: none;
    }

    .enviiz-logo {
        width: 24px;
        height: auto;
        vertical-align: middle;
        margin-left: 3px;
    }

    @media print {
        html, body {
            width: 210mm;
            height: 297mm;
            margin: 0;
            padding: 0;
            background: #ffffff;
        }

        .print-page {
            width: 210mm;
            height: 297mm;
            margin: 0;
            background: #ffffff;
        }

        .invoice-card {
            width: 100%;
            height: 100%;
            padding: 18mm 18mm 18mm 18mm;
        }

        .invoice-footer {
            position: absolute;
            left: 18mm;
            right: 18mm;
            bottom: 14mm;
        }
    }
</style>
    </head>
    <body>
        <div class="print-page">
            <div class="invoice-card">
                <div class="invoice-body">

                    <div class="row align-items-start">
                        <div class="col-7">
                            <img src="./assets/imgs/common/logo.png" alt="Logo" class="brand-logo">
                            <div class="company-address">
                                58/A1, Nugegoda Road, Pepiliyana.<br>
                                +94 77 888 37 36 / +94 112 82 62 82
                            </div>
                        </div>

                        <div class="col-5">
                            <div class="invoice-title">
                                <h1>Invoice</h1>
                                <div class="invoice-number"># ${invoice.reference_no ?? '0000'}</div>
                            </div>
                        </div>
                    </div>

                    <div class="row info-section">
                        <div class="col-4">
                            <span class="info-label">Billed To:</span>
                            <div class="info-value">
                                ${invoice.student ?? 'N/A'}
                            </div>
                        </div>

                        <div class="col-4 text-center">
                            <span class="info-label">Pay Method:</span>
                            <div class="info-value">
                                ${invoice.payment_method ?? invoice.cashier ?? 'N/A'}
                            </div>
                        </div>

                        <div class="col-4 text-end">
                            <span class="info-label">Date:</span>
                            <div class="info-value">
                                ${invoice.date_on ?? 'N/A'}<br>
                                ${invoice.printed_on ?? ''}
                            </div>
                        </div>
                    </div>

                    <div class="invoice-table-wrap">
                        <table class="invoice-table">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th class="amount-col">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>${invoice.bill_title ?? 'Invoice Item'}</td>
                                    <td class="amount-col">${invoice.net_total ?? '0.00'}</td>
                                </tr>
                                <tr class="remarks-row">
                                    <td>Remarks: ${invoice.remarks ?? '--'}</td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="signature-section">
                        <div class="signature-line"></div>
                        <div class="signature-text">Prepared by</div>
                    </div>

                </div>

                <div class="invoice-footer">
                    <div class="content-div-bottom">
                        <p class="sub-text">
                            &copy;${new Date().getFullYear()} ENLIXO Uni. All Rights Reserved | Powered by
                            <a href="https://enviiz.com" target="_blank" class="footer-link">
                                <img src="./assets/imgs/common/enviiz-theme.webp" alt="ENVIIZ Softwares (PVT) LTD" class="enviiz-logo">
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <script>
            window.onload = function () {
                setTimeout(() => {
                    window.print();
                }, 400);
            };
        <\/script>
    </body>
    </html>`;

    printWindow.document.open();
    printWindow.document.write(html);
    printWindow.document.close();
}

const initializeAddIncident = () => {
    studentsData.forEach(student => {
        studentMap.set(student.student_id, student);
    });

}

const loadIncidentStudentData = (value) => {
    const student = studentMap.get(value);

    if (student) {
        $("#name-container").hasClass('d-none') ? $("#name-container").removeClass('d-none') : '';
        $("#batch-container").hasClass('d-none') ? $("#batch-container").removeClass('d-none') : '';
        $("#name").val(student.first_name + " " + student.last_name);
        $("#batch").val(student.intake);
        $("#reg-no-feedback").text("");
    } else {
        $("#name-container").hasClass('d-none') ? '' : $("#name-container").addClass('d-none');
        $("#batch-container").hasClass('d-none') ? '' : $("#batch-container").addClass('d-none');
        $("#reg-no-feedback").text("Invalid Student ID");
    }
}

const handleIncidentsPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = 0;
            sortCondition1 = sortCondition1 === 1 ? 2 : 1;
            break;
        case 'sortByDate':
            sortCondition1 = 0;
            sortCondition2 = sortCondition2 == 1 ? 2 : 1;
            break;
    }

    filterIncidents();
}


const getStudentList = () => {
    fetch('api/getStudentList')
        .then((res) => res.json())
        .then((response) => {
            console.log(response);
            if (response.done) {
                const studentsData = response.students;
                studentsData.forEach(student => {
                    studentMap.set(student.student_id, student);
                });
            } else {
                showToast("error", "An error occured. Please try again later.");
            }
        })
        .catch((error) => {
            console.log(error);
            showToast("error", "An error occured. Please try again later.");
        });
}

const initializeStudentsReports = () => {
    getStudentList();

    const input = document.getElementById('student-name');
    const codeInput = document.getElementById('student-code');
    const suggestionBox = document.getElementById('student-suggestions');

    input.addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        suggestionBox.innerHTML = '';

        if (query.length < 2) return;

        let count = 0;

        studentMap.forEach((student, id) => {
            const firstName = (student.first_name || '').toLowerCase();
            const lastName = (student.last_name || '').toLowerCase();

            if (firstName.includes(query) || lastName.includes(query)) {

                const item = document.createElement('a');
                item.classList.add('list-group-item', 'list-group-item-action');

                item.textContent = student.first_name + ' ' + student.last_name;
                item.code = student.student_id;

                item.addEventListener('click', () => {
                    input.value = item.textContent;
                    codeInput.value = item.code;
                    suggestionBox.innerHTML = '';
                    input.dataset.studentId = id;
                });

                suggestionBox.appendChild(item);

                count++;
                if (count >= 10) return;
            }
        });
    });
};

const handleStudentAction = (element, callback) => {
    console.log(callback);
    // remove active from all
    document.querySelectorAll('.student-action-card').forEach(item => {
        item.classList.remove('active');
    });

    // add active to clicked
    element.classList.add('active');

    document.querySelectorAll('.report-details').forEach(item => {
        if (!item.classList.contains('d-none')) {
            item.classList.add('d-none');
        }
    });
    if (callback == "profile") {
        document.getElementById('student-profile-report').classList.remove('d-none');
        $("#report-download")
            .off('click')
            .on('click', function () {
                downloadStudentProfileReportPdf(this, 1);
            });
    }
    if (callback == "attendance") {
        document.getElementById('student-attendance-report').classList.remove('d-none');
        $("#report-download")
            .off('click')
            .on('click', function () {
                downloadStudentProfileReportPdf(this, 2);
            });
    }
    if (callback == "incidents") {
        document.getElementById('student-incidents-report').classList.remove('d-none');
        $("#report-download")
            .off('click')
            .on('click', function () {
                downloadStudentProfileReportPdf(this, 3);
            });
    }
    if (callback == "payments") {
        document.getElementById('student-payments-report').classList.remove('d-none');
        $("#report-download")
            .off('click')
            .on('click', function () {
                downloadStudentProfileReportPdf(this, 4);
            });
    }

};


function openMessageModal() {
    const selectedCount = selectedUsers.length;

    if (selectedCount === 0) {
        showToast('warning', "Please select at least one student.");
        return;
    }

    document.getElementById("selectedStudentCount").innerText = selectedCount;

    document.getElementById("sendStudent").checked = false;
    document.getElementById("sendParent").checked = false;
    document.getElementById("messageText").value = "";
    document.getElementById("messageError").classList.add("d-none");

    const modal = new bootstrap.Modal(document.getElementById("messageModal"));
    modal.show();
}


function showMessageError(message) {
    const errorBox = document.getElementById("messageError");
    errorBox.innerText = message;
    errorBox.classList.remove("d-none");
}

const handleMessageCenterStudentsPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = 0;
            sortCondition1 = sortCondition1 === 1 ? 2 : 1;
            break;
        case 'sortByDate':
            sortCondition1 = 0;
            sortCondition2 = sortCondition2 == 1 ? 2 : 1;
            break;
    }

    filterMessageCenterStudentsPagination();
}

const messageTableCheckEvents = () => {
    const isChecked = $("#check-all").prop("checked");

    if (isChecked) {
        $(".row-check").prop("checked", true);

        selectedUsers = [];

        $(".row-check").each(function () {
            const id = parseInt($(this).attr("id").replace("check-user", ""));
            selectedUsers.push(id);
        });
    } else {
        $(".row-check").prop("checked", false);
        selectedUsers = [];
    }
};

const handleLoadPaymentPlanStudentsPagination = (id, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = sortCondition3 = 0;
            sortCondition1 = sortCondition1 === 1 ? 2 : 1;
            break;
        case 'sortByDate':
            sortCondition1 = sortCondition3 = 0;
            sortCondition2 = sortCondition2 == 1 ? 2 : 1;
            break;
        case 'sortById':
            sortCondition1 = sortCondition2 = 0;
            sortCondition3 = sortCondition3 == 1 ? 2 : 1;
            break;
    }

    filterIntakePaymentPlanStudents(id);
}

const openEditExchangeRateModal = (id, rate, effectiveDate, note) => {
    $("#editExchangeRateId").val(id);
    $("#editExchangeRate").val(rate);
    $("#editEffectiveDate").val(effectiveDate);
    $("#editExchangeNote").val(note);

    $("#edit-exchange-rate-feedback").text("");
    $("#edit-effective-date-feedback").text("");
    $("#edit-exchange-note-feedback").text("");

    $("#editExchangeRate").removeClass("is-invalid");
    $("#editEffectiveDate").removeClass("is-invalid");
    $("#editExchangeNote").removeClass("is-invalid");

    $("#editExchangeRateModal").modal("show");
};

const handleCurrencyExchangeHistorysPagination = (id, action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterCurrencyExchange(id);
}


const REQUIRED_STUDENT_DOCUMENTS = [
    "aic_application",
    "university_application",
    "signed_agreement",
    "signed_program_brief",
    "passport_nic",
    "academic_documents",
    "other_entry_documents",
    "payment_slip",
    "manager_checklist",
    "passport_size_photo",
    "discount_approved_document",
    "signed_fee_structure"
];

const studentDocumentFiles = {};


function initializeStudentDocumentUploads() {
    document.querySelectorAll(".student-doc-upload-box").forEach((box) => {
        const docKey = box.dataset.docKey;
        const dropArea = box.querySelector(".doc-drop-area");
        const input = box.querySelector(".student-doc-input");
        const browseBtn = box.querySelector(".doc-browse-btn");

        studentDocumentFiles[docKey] = [];

        browseBtn.addEventListener("click", function (e) {
            e.preventDefault();
            input.click();
        });

        dropArea.addEventListener("click", function (e) {
            if (!e.target.classList.contains("doc-browse-btn")) {
                input.click();
            }
        });

        input.addEventListener("change", function () {
            handleStudentDocumentFiles(box, input.files);
        });

        dropArea.addEventListener("dragover", function (e) {
            e.preventDefault();
            dropArea.classList.add("drag-over-effect");
        });

        dropArea.addEventListener("dragleave", function () {
            dropArea.classList.remove("drag-over-effect");
        });

        dropArea.addEventListener("drop", function (e) {
            e.preventDefault();
            dropArea.classList.remove("drag-over-effect");
            handleStudentDocumentFiles(box, e.dataTransfer.files);
        });
    });
}

function handleStudentDocumentFiles(box, fileList) {
    const docKey = box.dataset.docKey;
    const input = box.querySelector(".student-doc-input");
    const feedback = box.querySelector(".doc-feedback");
    const allowMultiple = input.hasAttribute("multiple");

    feedback.innerHTML = "";

    const files = Array.from(fileList);

    if (files.length === 0) {
        return;
    }

    let validFiles = [];

    files.forEach((file) => {
        const validation = validateStudentDocumentFile(file, input);

        if (!validation.valid) {
            feedback.innerHTML = validation.message;
            return;
        }

        validFiles.push(file);
    });

    if (validFiles.length === 0) {
        return;
    }

    if (allowMultiple) {
        studentDocumentFiles[docKey] = [
            ...studentDocumentFiles[docKey],
            ...validFiles
        ];
    } else {
        studentDocumentFiles[docKey] = [validFiles[0]];
    }

    syncStudentDocumentInput(input, studentDocumentFiles[docKey]);
    renderStudentDocumentPreview(box);
}

function validateStudentDocumentFile(file, input) {
    const maxFileSize = 8 * 1024 * 1024; // 8MB

    const allowedExtensions = input
        .getAttribute("accept")
        .split(",")
        .map((ext) => ext.trim().replace(".", "").toLowerCase());

    const fileExtension = file.name.split(".").pop().toLowerCase();

    if (!allowedExtensions.includes(fileExtension)) {
        return {
            valid: false,
            message: `Invalid file type. Allowed: ${allowedExtensions.join(", ")}`
        };
    }

    if (file.size > maxFileSize) {
        return {
            valid: false,
            message: "File size must be less than 8MB"
        };
    }

    return {
        valid: true,
        message: ""
    };
}

function renderStudentDocumentPreview(box) {
    const docKey = box.dataset.docKey;
    const preview = box.querySelector(".doc-preview");

    preview.innerHTML = "";

    studentDocumentFiles[docKey].forEach((file, index) => {
        const template = `
            <div class="student-doc-file-card mb-2">
                <div class="d-flex align-items-center">
                    <div class="me-2">
                        <img src="assets/imgs/icons/${getStudentDocumentIcon(file)}" width="32" alt="file">
                    </div>

                    <div class="flex-grow-1">
                        <div class="student-doc-file-name">${escapeHtml(file.name)}</div>
                        <div class="student-doc-file-meta">
                            ${getReadableFileType(file.name)} • ${formatStudentDocumentFileSize(file.size)}
                        </div>
                    </div>

                    <div>
                        <iconify-icon 
                            icon="mdi:close-circle-outline"
                            width="22"
                            height="22"
                            class="remove-student-doc"
                            data-doc-key="${docKey}"
                            data-index="${index}">
                        </iconify-icon>
                    </div>
                </div>
            </div>
        `;

        preview.insertAdjacentHTML("beforeend", template);
    });

    preview.querySelectorAll(".remove-student-doc").forEach((btn) => {
        btn.addEventListener("click", function () {
            removeStudentDocumentFile(
                this.dataset.docKey,
                parseInt(this.dataset.index)
            );
        });
    });
}

function removeStudentDocumentFile(docKey, index) {
    const box = document.querySelector(`.student-doc-upload-box[data-doc-key="${docKey}"]`);
    const input = box.querySelector(".student-doc-input");

    studentDocumentFiles[docKey].splice(index, 1);

    syncStudentDocumentInput(input, studentDocumentFiles[docKey]);
    renderStudentDocumentPreview(box);
}

function syncStudentDocumentInput(input, files) {
    const dataTransfer = new DataTransfer();

    files.forEach((file) => {
        dataTransfer.items.add(file);
    });

    input.files = dataTransfer.files;
}

function validateRequiredStudentDocuments() {
    let isValid = true;

    REQUIRED_STUDENT_DOCUMENTS.forEach((docKey) => {
        const box = document.querySelector(`.student-doc-upload-box[data-doc-key="${docKey}"]`);
        const feedback = box.querySelector(".doc-feedback");

        feedback.innerHTML = "";

        if (!studentDocumentFiles[docKey] || studentDocumentFiles[docKey].length === 0) {
            feedback.innerHTML = "This document is required";
            isValid = false;
        }
    });

    return isValid;
}

function appendStudentDocumentsToFormData(formData) {
    Object.keys(studentDocumentFiles).forEach((docKey) => {
        studentDocumentFiles[docKey].forEach((file, index) => {
            formData.append(`${docKey}[]`, file);
        });
    });

    return formData;
}

function getStudentDocumentsData() {
    return studentDocumentFiles;
}

function getStudentDocumentIcon(file) {
    const extension = file.name.split(".").pop().toLowerCase();

    const iconMap = {
        pdf: "pdf.png",
        doc: "doc.png",
        docx: "doc.png",
        jpg: "image.png",
        jpeg: "image.png",
        png: "image.png"
    };

    return iconMap[extension] || "file.png";
}

function getReadableFileType(fileName) {
    return fileName.split(".").pop().toUpperCase();
}

function formatStudentDocumentFileSize(bytes) {
    if (bytes < 1024) {
        return bytes + " bytes";
    }

    if (bytes < 1024 * 1024) {
        return (bytes / 1024).toFixed(2) + " KB";
    }

    return (bytes / (1024 * 1024)).toFixed(2) + " MB";
}

function escapeHtml(text) {
    const div = document.createElement("div");
    div.innerText = text;
    return div.innerHTML;
}


const handleCurrencyExchangePagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
    }

    filterCurrencyExchange();
}

const initializeEditIntakePlan = (planId) => {
    loadPaymentType(planId);
}


const confirmDeletePlan = (id) => {
    $("#confirmRemoveIntakePaymentPlan").off("click");
    $("#confirmRemoveIntakePaymentPlan").on("click", function () {
        removeIntakePaymentPlan(id);
    });
    $("#removeIntakePaymentPlanConfirmModal").modal("show");
}

function toggleLeadCardDetails(button) {
    const section = document.getElementById('paymentGuidelineDetails');

    if (section.classList.contains('show')) {
        section.classList.remove('show');
        button.classList.remove('active');
        button.querySelector('.btn-text').textContent = 'View Details';
    } else {
        section.classList.add('show');
        button.classList.add('active');
        button.querySelector('.btn-text').textContent = 'Read Less';
    }
}

const togglePaymentPlanType = () => {

    const selectedType = $("input[name='paymentPlanType']:checked").val();

    if (selectedType === "intake") {

        $("#intakes-payment-plan-arae").removeClass("d-none");
        $("#custom-plan-area").addClass("d-none");
        $("#save-plan-btn").addClass("d-none");

    } else {
        $("#intakes-payment-plan-arae").addClass("d-none");
        $("#custom-plan-area").removeClass("d-none");
        $("#save-plan-btn").removeClass("d-none");
    }
};

$("input[name='paymentPlanType']").on("change", function () {
    togglePaymentPlanType();
});

let selectedIntakePlanId = null;

function selectIntakePaymentPlan(input) {

    $(".intake-plan-checkbox").not(input).prop("checked", false);

    if ($(input).is(":checked")) {
        selectedIntakePlanId = $(input).data("plan-id");
        $("#save-plan-btn").removeClass("d-none");
    } else {
        selectedIntakePlanId = null;
        $("#save-plan-btn").addClass("d-none");
    }
}

const handleStudentPaymentPlan = (btn, leadId) => {
    const paymentPlanType = $("input[name='paymentPlanType']:checked").val();
    if (paymentPlanType === "intake") {
        if (!selectedIntakePlanId) {
            showToast("error", "Please select an intake payment plan");
            return;
        }
        createStudentPaymentPlan(btn, leadId);
    } else {
        createCustomStudentPaymentPlan(btn, leadId);
    }
};

function escapeHtml(text) {
    const div = document.createElement("div");
    div.innerText = text;
    return div.innerHTML;
}


const EXISTING_STUDENT_DOCUMENTS = window.EXISTING_STUDENT_DOCUMENTS || {};

const removedExistingDocuments = {};

function initializeUpdateStudentDocumentUploads() {
    document.querySelectorAll(".student-doc-upload-box").forEach((box) => {
        const docKey = box.dataset.docKey;
        const dropArea = box.querySelector(".doc-drop-area");
        const input = box.querySelector(".student-doc-input");
        const browseBtn = box.querySelector(".doc-browse-btn");

        studentDocumentFiles[docKey] = [];
        removedExistingDocuments[docKey] = [];

        browseBtn.addEventListener("click", function (e) {
            e.preventDefault();
            input.click();
        });

        dropArea.addEventListener("click", function (e) {
            if (!e.target.classList.contains("doc-browse-btn")) {
                input.click();
            }
        });

        input.addEventListener("change", function () {
            handleStudentDocumentFiles(box, input.files);
        });

        dropArea.addEventListener("dragover", function (e) {
            e.preventDefault();
            dropArea.classList.add("drag-over-effect");
        });

        dropArea.addEventListener("dragleave", function () {
            dropArea.classList.remove("drag-over-effect");
        });

        dropArea.addEventListener("drop", function (e) {
            e.preventDefault();
            dropArea.classList.remove("drag-over-effect");
            handleStudentDocumentFiles(box, e.dataTransfer.files);
        });

        renderStudentDocumentPreview(box);
    });
}

function getExistingDocs(docKey) {
    return (EXISTING_STUDENT_DOCUMENTS[docKey] || []).filter((doc) => {
        return !removedExistingDocuments[docKey].includes(String(doc.id));
    });
}

function handleStudentDocumentFiles(box, fileList) {
    const docKey = box.dataset.docKey;
    const input = box.querySelector(".student-doc-input");
    const feedback = box.querySelector(".doc-feedback");
    const allowMultiple = input.hasAttribute("multiple");

    feedback.innerHTML = "";

    const files = Array.from(fileList);

    if (files.length === 0) {
        return;
    }

    let validFiles = [];

    files.forEach((file) => {
        const validation = validateStudentDocumentFile(file, input);

        if (!validation.valid) {
            feedback.innerHTML = validation.message;
            return;
        }

        validFiles.push(file);
    });

    if (validFiles.length === 0) {
        return;
    }

    if (allowMultiple) {
        studentDocumentFiles[docKey] = [
            ...studentDocumentFiles[docKey],
            ...validFiles
        ];
    } else {
        studentDocumentFiles[docKey] = [validFiles[0]];
    }

    syncStudentDocumentInput(input, studentDocumentFiles[docKey]);
    renderStudentDocumentPreview(box);
}

function validateStudentDocumentFile(file, input) {
    const maxFileSize = 8 * 1024 * 1024;

    const allowedExtensions = input
        .getAttribute("accept")
        .split(",")
        .map((ext) => ext.trim().replace(".", "").toLowerCase());

    const fileExtension = file.name.split(".").pop().toLowerCase();

    if (!allowedExtensions.includes(fileExtension)) {
        return {
            valid: false,
            message: `Invalid file type. Allowed: ${allowedExtensions.join(", ")}`
        };
    }

    if (file.size > maxFileSize) {
        return {
            valid: false,
            message: "File size must be less than 8MB"
        };
    }

    return {
        valid: true,
        message: ""
    };
}

function renderStudentDocumentPreview(box) {
    const docKey = box.dataset.docKey;
    const preview = box.querySelector(".doc-preview");

    preview.innerHTML = "";

    const existingDocs = getExistingDocs(docKey);

    existingDocs.forEach((doc) => {
        const template = `
          <div class="student-doc-file-card mb-2 existing-doc-card cursor-pointer"
                    onclick="window.open('${escapeHtml(doc.url)}', '_blank')">

                <div class="d-flex align-items-center">
                    <div class="me-2">
                        <img src="assets/imgs/icons/${getStudentDocumentIconByName(doc.name)}" width="32" alt="file">
                    </div>

                    <div class="flex-grow-1">
                        <div class="student-doc-file-name">${escapeHtml(doc.name)}</div>
                        <div class="student-doc-file-meta">
                            Existing Document • ${escapeHtml(doc.date_added || '')}
                        </div>
                    </div>

                    ${doc.access_remove ? `
                          <div class="d-flex align-items-center gap-2">
                        <iconify-icon 
                            icon="mdi:close-circle-outline"
                            width="22"
                            height="22"
                            class="remove-existing-student-doc cursor-pointer"
                            data-doc-key="${docKey}"
                            data-doc-id="${doc.id}">
                        </iconify-icon>
                    </div>
                        ` : ``}

                  
                </div>
            </div>
        `;

        preview.insertAdjacentHTML("beforeend", template);
    });

    studentDocumentFiles[docKey].forEach((file, index) => {
        const template = `
            <div class="student-doc-file-card mb-2">
                <div class="d-flex align-items-center">
                    <div class="me-2">
                        <img src="assets/imgs/icons/${getStudentDocumentIcon(file)}" width="32" alt="file">
                    </div>

                    <div class="flex-grow-1">
                        <div class="student-doc-file-name">${escapeHtml(file.name)}</div>
                        <div class="student-doc-file-meta">
                            New File • ${getReadableFileType(file.name)} • ${formatStudentDocumentFileSize(file.size)}
                        </div>
                    </div>

                    <div>
                        <iconify-icon 
                            icon="mdi:close-circle-outline"
                            width="22"
                            height="22"
                            class="remove-student-doc cursor-pointer"
                            data-doc-key="${docKey}"
                            data-index="${index}">
                        </iconify-icon>
                    </div>
                </div>
            </div>
        `;

        preview.insertAdjacentHTML("beforeend", template);
    });

    preview.querySelectorAll(".remove-student-doc").forEach((btn) => {
        btn.addEventListener("click", function () {
            removeStudentDocumentFile(
                this.dataset.docKey,
                parseInt(this.dataset.index)
            );
        });
    });

    preview.querySelectorAll(".remove-existing-student-doc").forEach((btn) => {
        btn.addEventListener("click", function () {
            removeExistingStudentDocument(
                this.dataset.docKey,
                this.dataset.docId
            );
        });
    });
}

function removeExistingStudentDocument(docKey, docId) {
    removedExistingDocuments[docKey].push(String(docId));

    const box = document.querySelector(`.student-doc-upload-box[data-doc-key="${docKey}"]`);
    renderStudentDocumentPreview(box);
}

function removeStudentDocumentFile(docKey, index) {
    const box = document.querySelector(`.student-doc-upload-box[data-doc-key="${docKey}"]`);
    const input = box.querySelector(".student-doc-input");

    studentDocumentFiles[docKey].splice(index, 1);

    syncStudentDocumentInput(input, studentDocumentFiles[docKey]);
    renderStudentDocumentPreview(box);
}

function syncStudentDocumentInput(input, files) {
    const dataTransfer = new DataTransfer();

    files.forEach((file) => {
        dataTransfer.items.add(file);
    });

    input.files = dataTransfer.files;
}

function validateRequiredStudentDocuments() {
    let isValid = true;

    REQUIRED_STUDENT_DOCUMENTS.forEach((docKey) => {
        const box = document.querySelector(`.student-doc-upload-box[data-doc-key="${docKey}"]`);
        const feedback = box.querySelector(".doc-feedback");

        feedback.innerHTML = "";

        const hasExisting = getExistingDocs(docKey).length > 0;
        const hasNew = studentDocumentFiles[docKey] && studentDocumentFiles[docKey].length > 0;

        if (!hasExisting && !hasNew) {
            feedback.innerHTML = "This document is required";
            isValid = false;
        }
    });

    return isValid;
}

function appendStudentDocumentsToFormData(formData) {
    Object.keys(studentDocumentFiles).forEach((docKey) => {
        studentDocumentFiles[docKey].forEach((file) => {
            formData.append(`${docKey}[]`, file);
        });
    });

    Object.keys(removedExistingDocuments).forEach((docKey) => {
        removedExistingDocuments[docKey].forEach((docId) => {
            formData.append(`removed_documents[]`, docId);
        });
    });

    return formData;
}

function getStudentDocumentIcon(file) {
    return getStudentDocumentIconByName(file.name);
}

function getStudentDocumentIconByName(fileName) {
    const extension = fileName.split(".").pop().toLowerCase();

    const iconMap = {
        pdf: "pdf.png",
        doc: "doc.png",
        docx: "doc.png",
        jpg: "image.png",
        jpeg: "image.png",
        png: "image.png"
    };

    return iconMap[extension] || "file.png";
}

function getReadableFileType(fileName) {
    return fileName.split(".").pop().toUpperCase();
}

function formatStudentDocumentFileSize(bytes) {
    if (bytes < 1024) {
        return bytes + " bytes";
    }

    if (bytes < 1024 * 1024) {
        return (bytes / 1024).toFixed(2) + " KB";
    }

    return (bytes / (1024 * 1024)).toFixed(2) + " MB";
}

function escapeHtml(text) {
    const div = document.createElement("div");
    div.innerText = text ?? "";
    return div.innerHTML;
}

function collectMarkAsPaidPayments() {
    const payments = [];

    document.querySelectorAll(".intake-mark-paid-checkbox:checked, .custom-mark-paid-checkbox:checked").forEach((checkbox) => {
        const box = checkbox.closest(".mark-paid-box");
        const paymentTypeId = parseInt(checkbox.dataset.paymentTypeId || 0);

        if (paymentTypeId !== 18) {
            return;
        }

        payments.push({
            payment_type_id: paymentTypeId,
            installment_id: checkbox.dataset.installmentId || null,
            paid_amount: box.querySelector(".paid-amount")?.value || 0,
            discount: box.querySelector(".discount")?.value || 0,
            paid_date: box.querySelector(".paid-date")?.value || "",
            payment_method_id: box.querySelector(".paid-method")?.value || 0,
            note: box.querySelector(".paid-note")?.value || ""
        });
    });

    return payments;
}

function toggleMarkPaidFields(checkbox) {
    const box = checkbox.closest(".mark-paid-box");
    const fields = box.querySelector(".mark-paid-fields");

    if (checkbox.checked) {
        fields.classList.remove("d-none");
    } else {
        fields.classList.add("d-none");
    }
}

function openAccountsConfirmModal(studentId) {
    document.getElementById("accountsConfirmStudentId").value = studentId;

    const modal = new bootstrap.Modal(document.getElementById("accountsConfirmModal"));
    modal.show();
}

function openRegistrationVerificationModal(studentId) {
    document.getElementById("registrationVerificationStudentId").value = studentId;

    const modal = new bootstrap.Modal(
        document.getElementById("registrationVerificationModal")
    );

    modal.show();
}

function showRegistrationVerificationError(message) {
    const area = document.getElementById(
        "registrationVerificationErrorArea"
    );
    const text = document.getElementById(
        "registrationVerificationErrorText"
    );
    text.innerText = message;
    area.classList.remove("d-none");
}

function hideRegistrationVerificationError() {
    const area = document.getElementById(
        "registrationVerificationErrorArea"
    );
    const text = document.getElementById(
        "registrationVerificationErrorText"
    );
    text.innerText = "";
    area.classList.add("d-none");
}

function openAcademicAdministrationConfirmModal(studentId) {

    hideAcademicCompletionError();

    document.getElementById(
        "academicAdministrationStudentId"
    ).value = studentId;

    const modal = new bootstrap.Modal(
        document.getElementById(
            "academicAdministrationConfirmModal"
        )
    );

    modal.show();
}

function showAcademicCompletionError(message) {
    const area = document.getElementById(
        "academicCompletionErrorArea"
    );
    const text = document.getElementById(
        "academicCompletionErrorText"
    );
    text.innerText = message;
    area.classList.remove("d-none");
}

function hideAcademicCompletionError() {
    const area = document.getElementById(
        "academicCompletionErrorArea"
    );
    const text = document.getElementById(
        "academicCompletionErrorText"
    );
    text.innerText = "";
    area.classList.add("d-none");
}

function openAcademicDepartmentConfirmModal(studentId) {
    document.getElementById("academicDepartmentStudentId").value = studentId;

    const modal = new bootstrap.Modal(
        document.getElementById("academicDepartmentConfirmModal")
    );

    modal.show();
}

const handleInquiryPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = 0;
            sortCondition1 = sortCondition1 === 1 ? 2 : 1;
            break;
        case 'sortByDate':
            sortCondition1 = 0;
            sortCondition2 = sortCondition2 == 1 ? 2 : 1;
            break;
    }

    filterStudentsInquiries();
}

function openAcademicAdministrationRejectModal(studentId) {

    document.getElementById(
        "academicRejectStudentId"
    ).value = studentId;

    document.getElementById(
        "academicRejectNote"
    ).value = "";

    document.getElementById(
        "academicRejectErrorArea"
    ).classList.add("d-none");

    const feedback = document.getElementById(
        "academicRejectNote-feedback"
    );

    feedback.innerHTML = "";

    document.getElementById(
        "academicRejectNote"
    ).classList.remove("is-invalid");

    const modal = new bootstrap.Modal(
        document.getElementById(
            "academicDocumentsRejectModal"
        )
    );

    modal.show();
}


function customPlanCollectInstallmentData() {
    const data = [];
    document.querySelectorAll("#fees-type .accordion-item").forEach(item => {
        const installmentId =
            item.querySelector("#enable-panelty")?.dataset.installmentId;

        const installment = {
            installmentId,
            paymentType: item.querySelector("#payment-type")?.value,
            amount: item.querySelector("#amount")?.value,
            discountType: item.querySelector("#discount-type")?.value,
            discount: item.querySelector("#discount")?.value,
            discountNote: item.querySelector("#discount-note")?.value,
            dueDate: item.querySelector("#due-date")?.value,
            penaltyEnabled: item.querySelector("#enable-panelty")?.checked,
            penalty: null
        };

        if (installment.penaltyEnabled) {
            const option = item.querySelector("#panelty-option")?.value;

            if (option === "2" || option === "3") {
                installment.penalty = {
                    mode: option,
                    type: item.querySelector("#flat-type")?.value,
                    amount: item.querySelector("#flat-amount")?.value,
                };
            }

            if (option === "4") {
                installment.penalty = {
                    mode: option,
                    slots: []
                };

                item.querySelectorAll(".custom-slot").forEach(slot => {
                    installment.penalty.slots.push({
                        type: slot.querySelector(".panelty-type")?.value,
                        amount: slot.querySelector(".panelty-amount")?.value,
                        days: slot.querySelector(".panelty-days")?.value
                    });
                });
            }
        }

        data.push(installment);
    });

    return data;
}


const handleStudentsPaymentsPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByAmount':
            sortCondition2 = sortCondition3 = sortCondition4 = SORT_NONE;
            sortCondition1 = toggleSort(sortCondition1);
            break;
        case 'sortByDate':
            sortCondition1 = sortCondition3 = sortCondition4 = SORT_NONE;
            sortCondition2 = toggleSort(sortCondition2);
            break;
        case 'sortById':
            sortCondition1 = sortCondition2 = sortCondition4 = SORT_NONE;
            sortCondition3 = toggleSort(sortCondition3);
            break;
        case 'sortByName':
            sortCondition1 = sortCondition2 = sortCondition3 = SORT_NONE;
            sortCondition4 = toggleSort(sortCondition4);
            break;
    }

    filterStudentsPayments();
}


/* ── Open Update Modal ── */
function openUpdateTaxModal(id, name, rate) {
    document.getElementById('update-tax-id').value = id;
    document.getElementById('update-tax-name').value = name;
    document.getElementById('update-tax-rate').value = rate;
    document.getElementById('update-tax-note').value = '';
    document.getElementById('update-tax-name-feedback').textContent = '';
    document.getElementById('update-tax-rate-feedback').textContent = '';
    var modal = new bootstrap.Modal(document.getElementById('updateTaxModal'));
    modal.show();
}

/* ── Submit Update ── */
/* ── Toggle History ── */
function toggleTaxHistory(taxId, btn) {
    var area = document.getElementById('tax-history-' + taxId);
    var isHidden = area.classList.contains('d-none');

    if (isHidden) {
        area.classList.remove('d-none');
        btn.innerHTML = '<iconify-icon icon="mdi:chevron-up" width="13" style="vertical-align:-2px;margin-right:4px;"></iconify-icon> Hide History';
    } else {
        area.classList.add('d-none');
        btn.innerHTML = '<iconify-icon icon="mdi:history" width="13" style="vertical-align:-2px;margin-right:4px;"></iconify-icon> View History';
    }
}

const initializeEditSystemPlan = (planId) => {
    loadPaymentType(planId);
}

const confirmDeleteSystemPlan = (id) => {
    $("#confirmRemoveSystemPaymentPlan").off("click");
    $("#confirmRemoveSystemPaymentPlan").on("click", function () {
        removeSystemPaymentPlan(id);
    });
    $("#removeSystemPaymentPlanConfirmModal").modal("show");
}

var selectedIntakePlans = [];
var intakePlansLoaded = false;

function switchPaymentGroup(type) {
    var intakeBtn = document.getElementById('btn-intake-group');
    var customBtn = document.getElementById('btn-custom-group');
    var intakeSec = document.getElementById('section-intake-group');
    var customSec = document.getElementById('section-custom-group');
    var radioIntake = document.getElementById('radio-intake');
    var radioCustom = document.getElementById('radio-custom');
    var iconCustom = document.getElementById('icon-custom');

    if (type === 'intake') {
        intakeBtn.classList.add('active');
        customBtn.classList.remove('active');
        intakeSec.classList.remove('d-none');
        customSec.classList.add('d-none');
        if (iconCustom) iconCustom.style.color = 'var(--sub-text)';
        radioIntake.innerHTML = '<iconify-icon icon="mdi:check" width="10" height="10" style="color:#fff;"></iconify-icon>';
        radioCustom.innerHTML = '';
        if (!intakePlansLoaded) loadIntakePaymentPlans();
    } else {
        customBtn.classList.add('active');
        intakeBtn.classList.remove('active');
        customSec.classList.remove('d-none');
        intakeSec.classList.add('d-none');
        if (iconCustom) iconCustom.style.color = '#fff';
        radioCustom.innerHTML = '<iconify-icon icon="mdi:check" width="10" height="10" style="color:#fff;"></iconify-icon>';
        radioIntake.innerHTML = '';
    }
}

const handlePaymentPlansPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;

    }

    filterPaymentPlans();
}

function switchLectureScheduleType(type) {
    var intakeBtn = document.getElementById('btn-intake-group');
    var customBtn = document.getElementById('btn-custom-group');
    var intakeSec = document.getElementById('schedule-single-branch');
    var customSec = document.getElementById('schedule-multiple-branch');
    var singleBtn = document.getElementById('save-single-lecture');
    var multipleBtn = document.getElementById('save-multiple-lectures');
    var radioIntake = document.getElementById('radio-intake');
    var radioCustom = document.getElementById('radio-custom');
    var iconCustom = document.getElementById('icon-custom');

    if (type === 'single') {
        intakeBtn.classList.add('active');
        customBtn.classList.remove('active');
        intakeSec.classList.remove('d-none');
        customSec.classList.add('d-none');
        singleBtn.classList.remove('d-none');
        multipleBtn.classList.add('d-none');
        if (iconCustom) iconCustom.style.color = 'var(--sub-text)';
        radioIntake.innerHTML = '<iconify-icon icon="mdi:check" width="10" height="10" style="color:#fff;"></iconify-icon>';
        radioCustom.innerHTML = '';
    } else {
        customBtn.classList.add('active');
        intakeBtn.classList.remove('active');
        customSec.classList.remove('d-none');
        intakeSec.classList.add('d-none');
        singleBtn.classList.add('d-none');
        multipleBtn.classList.remove('d-none');
        if (iconCustom) iconCustom.style.color = '#fff';
        radioCustom.innerHTML = '<iconify-icon icon="mdi:check" width="10" height="10" style="color:#fff;"></iconify-icon>';
        radioIntake.innerHTML = '';
    }
}
const removeLectureOpenModal = (id) => {
    $("#confirmRemoveLecture").off("click");
    $("#confirmRemoveLecture").on("click", function () {
        removeLecture(id);
    });

    $("#removeLectureConfirmModal").modal("show");
}

const enableDateSelector = (select) => {
    if ($(select).val() == 3) {
        $('#from-date-container, #to-date-container').removeClass('d-none');
        // optional focus
        $('#from-date').focus();

    } else {
        $('#from-date-container, #to-date-container').addClass('d-none');

        $('#from-date').val('');
        $('#to-date').val('');
    }
};

const handleStudentsPaymentsSumamryReportPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByAmount':
            sortCondition2 = sortCondition3 = sortCondition4 = SORT_NONE;
            sortCondition1 = toggleSort(sortCondition1);
            break;
        case 'sortByDate':
            sortCondition1 = sortCondition3 = sortCondition4 = SORT_NONE;
            sortCondition2 = toggleSort(sortCondition2);
            break;
        case 'sortById':
            sortCondition1 = sortCondition2 = sortCondition4 = SORT_NONE;
            sortCondition3 = toggleSort(sortCondition3);
            break;
        case 'sortByName':
            sortCondition1 = sortCondition2 = sortCondition3 = SORT_NONE;
            sortCondition4 = toggleSort(sortCondition4);
            break;
    }

    filterStudentsPaymentsSummaryReport();
}


const handleStudentsOutstandingBalancesReportPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByAmount':
            sortCondition2 = sortCondition3 = sortCondition4 = SORT_NONE;
            sortCondition1 = toggleSort(sortCondition1);
            break;
        case 'sortByDate':
            sortCondition1 = sortCondition3 = sortCondition4 = SORT_NONE;
            sortCondition2 = toggleSort(sortCondition2);
            break;
        case 'sortById':
            sortCondition1 = sortCondition2 = sortCondition4 = SORT_NONE;
            sortCondition3 = toggleSort(sortCondition3);
            break;
        case 'sortByName':
            sortCondition1 = sortCondition2 = sortCondition3 = SORT_NONE;
            sortCondition4 = toggleSort(sortCondition4);
            break;
    }

    filterStudentsOutstandingBalancesReport();
}


const handleInquiryRegistrationPaymentsPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage = pageId;
            break;
        case 'previousPage':
            if (selectedPage > 1) {
                selectedPage--;
            }
            break;
        case 'nextPage':
            if (selectedPage < totalPages) {
                selectedPage++;
            }
            break;
        case 'filter':
            selectedPage = 1;
            break;
        case 'sortByName':
            sortCondition2 = 0;
            sortCondition1 = sortCondition1 === 1 ? 2 : 1;
            break;
        case 'sortByDate':
            sortCondition1 = 0;
            sortCondition2 = sortCondition2 == 1 ? 2 : 1;
            break;
    }

    filterInquiryRegistrationPayments();
}

const handleDuePaymentsPagination = (action, pageId = null, totalPages = null) => {
    switch (action) {
        case 'goToPage':
            selectedPage2 = pageId;
            break;
        case 'previousPage':
            if (selectedPage2 > 1) {
                selectedPage2--;
            }
            break;
        case 'nextPage':
            if (selectedPage2 < totalPages) {
                selectedPage2++;
            }
            break;
        case 'filter':
            selectedPage2 = 1;
            break;
        case 'sortByAmount':
            sortCondition2 = sortCondition3 = sortCondition4 = 0;
            sortCondition1 = (sortCondition1 === 1 ? 2 : 1);
            break;
        case 'sortByDate':
            sortCondition1 = sortCondition3 = sortCondition4 = 0;
            sortCondition2 = (sortCondition2 == 1 ? 2 : 1);
            break;
        case 'sortByName':
            sortCondition1 = sortCondition2 = sortCondition4 = 0;
            sortCondition3 = (sortCondition3 === 1 ? 2 : 1);
            break;
        case 'sortById':
            sortCondition1 = sortCondition2 = sortCondition3 = 0;
            sortCondition4 = (sortCondition4 === 1 ? 2 : 1);
            break;

    }

    filterDuePayments();
}
