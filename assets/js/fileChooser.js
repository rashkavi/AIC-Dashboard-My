const dropArea = document.querySelector('#drop-section');
const listSection = document.querySelector('#list-section');
const fileSelector = document.querySelector('#file-selector');
const fileSelectorInput = document.querySelector('#file-selector-input');

// Flag to toggle between single and multiple file modes
var allowMultipleFiles = false;

if ($('#file-selector-input').attr('multiple')) {
    allowMultipleFiles = true;
}

// Upload files with browse button
fileSelector.onclick = () => fileSelectorInput.click();

fileSelectorInput.onchange = () => {
    listSection.innerHTML = '';
    if (allowMultipleFiles) {
        console.log(fileSelectorInput.files + '0');
        [...fileSelectorInput.files].forEach((file) => {
            if (typeValidation(file.type)) {
                uploadMultipleFile(file);
            }
        });
    } else {
        const file = fileSelectorInput.files[0];
        if (file && typeValidation(file.type)) {
            uploadSingleFile(file);
        }
    }
};

// Handle drag-over effect
dropArea.ondragover = (e) => {
    e.preventDefault();
    dropArea.classList.add('drag-over-effect');
};

// Remove drag-over effect when leaving the drop area
dropArea.ondragleave = () => {
    dropArea.classList.remove('drag-over-effect');
};

// Handle file drop
dropArea.ondrop = (e) => {
    listSection.innerHTML = '';
    e.preventDefault();
    dropArea.classList.remove('drag-over-effect');

    if (allowMultipleFiles) {
        [...e.dataTransfer.files].forEach((file) => {
            if (typeValidation(file.type)) {
                uploadMultipleFile(file);
            }
        });
    } else {
        const file = e.dataTransfer.files[0];
        if (file && typeValidation(file.type)) {
            uploadSingleFile(file);
        }
    }
};

// Validate file type
function typeValidation(type) {
    return true;
}

let uploadedFiles = [];

// Upload and display multiple files
function uploadMultipleFile(file) {
    uploadedFiles.push(file);

    let fileType = file.type.split('/')[0] == 'image' ? 'image' : 'pdf';

    const template = `
    <div class="col-12 col-md-4 my-3 file-item">
        <div class="p-3 bl-solid">
            <div class="row">
                <div class="col-2">
                    <img src="assets/imgs/icons/${iconSelector(file.type)}" alt="">
                </div>
                <div class="col-8">
                    <div class="file-name">
                        <div class="name">${file.name}</div>
                    </div>
                    <div class="file-size">${formatFileSize(file.size)}</div>
                    <div class="file-type text-muted">${getFileType(file.type, file.name)}</div>
                </div>
                <div class="col-2 text-end">
                    <iconify-icon icon="mdi:close" class="remove-file" width="20" height="20" style="color: #49739D" data-file-name="${file.name}"></iconify-icon>
                </div>
            </div>
        </div>
    </div>
    `;

    appendFileTemplate(template, file.name);
}

// Upload and display a single file
function uploadSingleFile(file) {
    // Clear the previously uploaded file
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
                    <div class="file-size">${formatFileSize(file.size)}</div>
                    <div class="file-type text-muted">${getFileType(file.type, file.name)}</div>
                </div>
                <div class="col-2 text-end">
                    <iconify-icon icon="mdi:close" class="remove-file" width="20" height="20" style="color: #49739D" data-file-name="${file.name}"></iconify-icon>
                </div>
            </div>
        </div>
    </div>
    `;

    appendFileTemplate(template, file.name);
}

// Append the file template to the list section
function appendFileTemplate(template, fileName) {
    const tempContainer = document.createElement('div');
    tempContainer.innerHTML = template.trim();

    const removeIcon = tempContainer.querySelector('.remove-file');
    if (removeIcon) {
        removeIcon.addEventListener('click', () => removeFile(fileName));
    }

    listSection.appendChild(tempContainer.firstElementChild);
}

// Remove a file
function removeFile(fileName) {
    uploadedFiles = uploadedFiles.filter((file) => file.name !== fileName);

    const fileInput = document.getElementById('file-selector-input');
    const dataTransfer = new DataTransfer();
    uploadedFiles.forEach((file) => dataTransfer.items.add(file));
    fileInput.files = dataTransfer.files;

    const fileItem = listSection.querySelector(`.file-item .remove-file[data-file-name="${fileName}"]`).closest('.file-item');
    if (fileItem) fileItem.remove();
}

// Get the icon for a file
function iconSelector(type, name) {
    // Mapping MIME types to icons
    const mimeIconMap = {
        "application/pdf": "pdf.png",
        "application/msword": "doc.png",
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document": "doc.png",
        "application/zip": "zip.png",
        "application/x-zip-compressed": "zip.png",
        "application/x-rar-compressed": "zip.png",
        "application/vnd.ms-powerpoint": "ppt.png",
        "application/vnd.openxmlformats-officedocument.presentationml.presentation": "ppt.png"
    };

    // Mapping extensions as fallback
    const extensionIconMap = {
        "pdf": "pdf.png",
        "doc": "doc.png",
        "docx": "doc.png",
        "zip": "zip.png",
        "rar": "zip.png",
        "ppt": "ppt.png",
        "pptx": "ppt.png",
        "jpg": "image.png",
        "jpeg": "image.png",
        "png": "image.png",
        "gif": "image.png",
        "bmp": "image.png",
        "webp": "image.png",
        "svg": "image.png",
        'csv' : 'text/csv'
    };

    // Check MIME type first
    if (mimeIconMap[type]) return mimeIconMap[type];

    // Check if it's an image
    if (type && type.startsWith("image/")) return "image.png";

    // Check extension if name exists
    if (name) {
        const extension = name.split('.').pop().toLowerCase();
        console.log(extensionIconMap[extension]);
        if (extensionIconMap[extension]) return extensionIconMap[extension];
    }

   
}

function getFileType(type, name) {
    // Normalize MIME type first
    if (!type && name) {
        const ext = name.split('.').pop().toLowerCase();
        type = extToMime[ext] || '';
    }

    // Mapping common MIME types to readable names
    const mimeToName = {
        'application/pdf': 'PDF',
        'application/msword': 'DOC',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'DOCX',
        'application/vnd.ms-excel': 'XLS',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'XLSX',
        'application/vnd.ms-powerpoint': 'PPT',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'PPTX',
        'application/zip': 'ZIP',
        'application/x-zip-compressed': 'ZIP',
        'application/json': 'JSON',
        'text/plain': 'TXT',
        'text/csv': 'CSV',
        'image/jpeg': 'JPG',
        'image/png': 'PNG',
        'image/gif': 'GIF',
        'image/svg+xml': 'SVG',
        'audio/mpeg': 'MP3',
        'video/mp4': 'MP4'
        // Add more types here as needed
    };

    // Mapping extensions as fallback
    const extToMime = {
        'pdf': 'application/pdf',
        'doc': 'application/msword',
        'docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls': 'application/vnd.ms-excel',
        'xlsx': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt': 'application/vnd.ms-powerpoint',
        'pptx': 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'zip': 'application/zip',
        'json': 'application/json',
        'txt': 'text/plain',
        'csv': 'text/csv',
        'jpg': 'image/jpeg',
        'jpeg': 'image/jpeg',
        'png': 'image/png',
        'gif': 'image/gif',
        'svg': 'image/svg+xml',
        'mp3': 'audio/mpeg',
        'mp4': 'video/mp4',
        'csv' : 'text/csv'
    };

    // Check MIME type first
    if (type && mimeToName[type]) return mimeToName[type];

    // If MIME type is unknown, check extension
    if (name) {
        const ext = name.split('.').pop().toLowerCase();
        if (extToMime[ext] && mimeToName[extToMime[ext]]) return mimeToName[extToMime[ext]];
    }

    // Fallback to showing original MIME type or extension
    return type || name.split('.').pop().toUpperCase() || 'Unknown';
}

function formatFileSize(bytes) {
    if (bytes < 1024) {
        return bytes + ' bytes';
    } else if (bytes < 1024 * 1024) {
        return (bytes / 1024).toFixed(2) + ' KB';
    } else {
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    }
}
