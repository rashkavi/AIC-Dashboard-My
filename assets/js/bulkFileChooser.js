const dropArea = document.querySelector('#drop-section');
const listSection = document.querySelector('#list-section');
const fileSelector = document.querySelector('#file-selector');
const fileSelectorInput = document.querySelector('#file-selector-input');

var allowMultipleFiles = false;

fileSelector.onclick = () => fileSelectorInput.click();

fileSelectorInput.onchange = () => {
    listSection.innerHTML = '';
    const file = fileSelectorInput.files[0];
    if (file && typeValidation(file.type)) {
        uploadSingleFile(file);
    }

};

dropArea.ondragover = (e) => {
    e.preventDefault();
    dropArea.classList.add('drag-over-effect');
};

dropArea.ondragleave = () => {
    dropArea.classList.remove('drag-over-effect');
};

dropArea.ondrop = (e) => {
    listSection.innerHTML = '';
    e.preventDefault();
    dropArea.classList.remove('drag-over-effect');
    const file = e.dataTransfer.files[0];
    if (file && typeValidation(file.type)) {
        uploadSingleFile(file);

    }
};

function typeValidation(type) {
    return type === 'text/csv';
}

let uploadedFiles = [];

function uploadSingleFile(file) {
    uploadedFiles = [file];
    listSection.innerHTML = '';

    const template = `
    <div class="col-12 my-3 file-item">
        <div class="p-3 bl-solid">
            <div class="row">
                <div class="col-2">
                    <img src="assets/imgs/icons/${iconSelector(file.type)}" alt="">
                </div>
                <div class="col-8">
                    <div class="file-name">
                        <div class="name">${file.name}</div>
                    </div>
                    <div class="file-size">${(file.size / (1024 * 1024)).toFixed(2)} MB</div>
                </div>
                <div class="col-2 text-end">
                    <iconify-icon icon="mdi:close" class="remove-file" width="20" height="20" style="color: #49739D" data-file-name="${file.name}"></iconify-icon>
                </div>
            </div>
        </div>
    </div>
    `;

    appendFileTemplate(template, file.name);
    if (!$("#drop-section").hasClass("d-none")) {
        dropArea.classList.add('d-none');
    }
    $("#list-section-container").removeClass("d-none");
}

function appendFileTemplate(template, fileName) {
    const tempContainer = document.createElement('div');
    tempContainer.innerHTML = template.trim();

    const removeIcon = tempContainer.querySelector('.remove-file');
    if (removeIcon) {
        removeIcon.addEventListener('click', () => removeFile(fileName));
    }

    listSection.appendChild(tempContainer.firstElementChild);
}

function removeFile(fileName) {
    uploadedFiles = uploadedFiles.filter((file) => file.name !== fileName);

    const fileInput = document.getElementById('file-selector-input');
    const dataTransfer = new DataTransfer();
    uploadedFiles.forEach((file) => dataTransfer.items.add(file));
    fileInput.files = dataTransfer.files;

    const fileItem = listSection.querySelector(`.file-item .remove-file[data-file-name="${fileName}"]`).closest('.file-item');
    if (fileItem) fileItem.remove();
    if ($("#drop-section").hasClass("d-none")) {
        dropArea.classList.remove('d-none');
    }
    $("#list-section-container").addClass("d-none");
}

function iconSelector(type) {
    const splitType = type.split('/')[1] || type.split('/')[0];
    return `${splitType}.png`;
}
