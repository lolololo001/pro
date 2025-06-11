/**
 * Multi-Step Form Handler for School Admin System
 * Handles edit modals, form submissions, and confirmation dialogs
 */

// Modal elements
let editFormModal = null;
let confirmationModal = null;
let currentEntityType = null;
let currentEntityId = null;

// Initialize modals when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Create edit form modal if it doesn't exist
    if (!document.getElementById('editFormModal')) {
        createEditFormModal();
    }
    
    // Create confirmation modal if it doesn't exist
    if (!document.getElementById('confirmationModal')) {
        createConfirmationModal();
    }
    
    // Initialize edit buttons
    initializeEditButtons();
    
    // Initialize delete buttons
    initializeDeleteButtons();
});

/**
 * Create the edit form modal
 */
function createEditFormModal() {
    const modalHtml = `
        <div id="editFormModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="editModalTitle"><i class="fas fa-edit"></i> Edit</h2>
                    <span class="close-modal" onclick="closeModal('editFormModal')">&times;</span>
                </div>
                <div class="modal-body" id="editModalBody">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i> Loading...
                    </div>
                </div>
            </div>
        </div>
    `;
    
    const modalContainer = document.createElement('div');
    modalContainer.innerHTML = modalHtml;
    document.body.appendChild(modalContainer.firstElementChild);
    
    editFormModal = document.getElementById('editFormModal');
}

/**
 * Create the confirmation modal
 */
function createConfirmationModal() {
    const modalHtml = `
        <div id="confirmationModal" class="modal">
            <div class="modal-content" style="max-width: 500px;">
                <div class="modal-header">
                    <h2 id="confirmModalTitle"><i class="fas fa-question-circle"></i> Confirmation</h2>
                    <span class="close-modal" onclick="closeModal('confirmationModal')">&times;</span>
                </div>
                <div class="modal-body">
                    <p id="confirmModalMessage">Are you sure you want to perform this action?</p>
                    <div class="form-actions" style="margin-top: 1.5rem;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('confirmationModal')">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="button" class="btn btn-danger" id="confirmActionButton">
                            <i class="fas fa-check"></i> Confirm
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    const modalContainer = document.createElement('div');
    modalContainer.innerHTML = modalHtml;
    document.body.appendChild(modalContainer.firstElementChild);
    
    confirmationModal = document.getElementById('confirmationModal');
}

/**
 * Initialize edit buttons
 */
function initializeEditButtons() {
    // Department edit buttons
    const departmentEditButtons = document.querySelectorAll('a.btn-icon.edit[href*="edit_department.php"]');
    departmentEditButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.href.split('id=')[1];
            openEditModal('department', id, 'Department');
        });
    });
    
    // Teacher edit buttons
    const teacherEditButtons = document.querySelectorAll('a.btn-icon.edit[href*="edit_teacher.php"]');
    teacherEditButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.href.split('id=')[1];
            openEditModal('teacher', id, 'Teacher');
        });
    });
    
    // Class edit buttons
    const classEditButtons = document.querySelectorAll('a.btn-icon.edit[href*="edit_class.php"]');
    classEditButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.href.split('id=')[1];
            openEditModal('class', id, 'Class');
        });
    });
    
    // Student edit buttons
    const studentEditButtons = document.querySelectorAll('a.btn-icon.edit[href*="edit_student.php"]');
    studentEditButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.href.split('id=')[1];
            openEditModal('student', id, 'Student');
        });
    });
    
    // Parent edit buttons
    const parentEditButtons = document.querySelectorAll('a.action-btn[href*="edit_parent.php"]');
    parentEditButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.href.split('id=')[1];
            openEditModal('parent', id, 'Parent');
        });
    });
    
    // Bursar edit buttons
    const bursarEditButtons = document.querySelectorAll('a.btn-icon.edit[href*="edit_bursar.php"]');
    bursarEditButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.href.split('id=')[1];
            openEditModal('bursar', id, 'Bursar');
        });
    });
    
    // Payment edit buttons
    const paymentEditButtons = document.querySelectorAll('a.btn-edit[href*="edit_payment.php"]');
    paymentEditButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.href.split('id=')[1];
            openEditModal('payment', id, 'Payment');
        });
    });
}

/**
 * Initialize delete buttons
 */
function initializeDeleteButtons() {
    // Department delete buttons
    const departmentDeleteButtons = document.querySelectorAll('a.btn-icon.delete[href*="departments.php?action=delete"]');
    departmentDeleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.href.split('id=')[1];
            openConfirmationModal('department', id, 'delete', this.href);
        });
    });
    
    // Teacher delete buttons
    const teacherDeleteButtons = document.querySelectorAll('a.btn-icon.delete[href*="teachers.php?action=delete"]');
    teacherDeleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.href.split('id=')[1];
            openConfirmationModal('teacher', id, 'delete', this.href);
        });
    });
    
    // Class delete buttons
    const classDeleteButtons = document.querySelectorAll('a.btn-icon.delete[href*="classes.php?action=delete"]');
    classDeleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.href.split('id=')[1];
            openConfirmationModal('class', id, 'delete', this.href);
        });
    });
    
    // Student delete buttons
    const studentDeleteButtons = document.querySelectorAll('a.btn-icon.delete[href*="students.php?action=delete"]');
    studentDeleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.href.split('id=')[1];
            openConfirmationModal('student', id, 'delete', this.href);
        });
    });
    
    // Parent delete buttons
    const parentDeleteButtons = document.querySelectorAll('a.action-btn[href*="delete_parent.php"]');
    parentDeleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.href.split('id=')[1];
            openConfirmationModal('parent', id, 'delete', this.href);
        });
    });
    
    // Bursar delete buttons
    const bursarDeleteButtons = document.querySelectorAll('a.btn-icon.delete[href*="bursars.php?action=delete"]');
    bursarDeleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.href.split('id=')[1];
            openConfirmationModal('bursar', id, 'delete', this.href);
        });
    });
    
    // Payment delete buttons
    const paymentDeleteButtons = document.querySelectorAll('a.btn-delete[href*="payments.php?action=delete"]');
    paymentDeleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.href.split('id=')[1];
            openConfirmationModal('payment', id, 'delete', this.href);
        });
    });
}

/**
 * Open edit modal
 * @param {string} entityType - Type of entity (department, teacher, etc.)
 * @param {string} id - Entity ID
 * @param {string} entityName - Display name of entity
 */
function openEditModal(entityType, id, entityName) {
    // Set current entity info
    currentEntityType = entityType;
    currentEntityId = id;
    
    // Update modal title
    document.getElementById('editModalTitle').innerHTML = `<i class="fas fa-edit"></i> Edit ${entityName}`;
    
    // Show loading spinner
    document.getElementById('editModalBody').innerHTML = `
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin"></i> Loading...
        </div>
    `;
    
    // Open modal
    openModal('editFormModal');
    
    // Fetch form content via AJAX
    fetch(`get_edit_form.php?type=${entityType}&id=${id}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('editModalBody').innerHTML = html;
            
            // Add submit event listener to the form
            const form = document.getElementById('editForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    submitEditForm(entityType, id);
                });
            }
        })
        .catch(error => {
            document.getElementById('editModalBody').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> Error loading form: ${error.message}
                </div>
            `;
        });
}

/**
 * Open confirmation modal
 * @param {string} entityType - Type of entity (department, teacher, etc.)
 * @param {string} id - Entity ID
 * @param {string} action - Action to confirm (delete, etc.)
 * @param {string} actionUrl - URL to redirect to on confirmation
 */
function openConfirmationModal(entityType, id, action, actionUrl) {
    // Set modal title based on action
    let title = 'Confirmation';
    let message = 'Are you sure you want to perform this action?';
    let buttonText = 'Confirm';
    let buttonIcon = 'check';
    
    if (action === 'delete') {
        title = 'Delete Confirmation';
        message = `Are you sure you want to delete this ${entityType}? This action cannot be undone.`;
        buttonText = 'Delete';
        buttonIcon = 'trash';
    }
    
    // Update modal content
    document.getElementById('confirmModalTitle').innerHTML = `<i class="fas fa-question-circle"></i> ${title}`;
    document.getElementById('confirmModalMessage').textContent = message;
    
    const confirmButton = document.getElementById('confirmActionButton');
    confirmButton.innerHTML = `<i class="fas fa-${buttonIcon}"></i> ${buttonText}`;
    
    // Set button action
    confirmButton.onclick = function() {
        window.location.href = actionUrl;
    };
    
    // Open modal
    openModal('confirmationModal');
}

/**
 * Submit edit form via AJAX
 * @param {string} entityType - Type of entity (department, teacher, etc.)
 * @param {string} id - Entity ID
 */
function submitEditForm(entityType, id) {
    const form = document.getElementById('editForm');
    const formData = new FormData(form);
    
    // Add entity type and ID to form data
    formData.append('entity_type', entityType);
    formData.append('entity_id', id);
    
    // Show loading spinner
    const submitButton = form.querySelector('button[type="submit"]');
    const originalButtonText = submitButton.innerHTML;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    submitButton.disabled = true;
    
    // Determine the appropriate endpoint based on entity type
    let endpoint = '';
    switch (entityType) {
        case 'department':
            endpoint = `edit_department.php?id=${id}`;
            break;
        case 'teacher':
            endpoint = `edit_teacher.php?id=${id}`;
            break;
        case 'class':
            endpoint = `edit_class.php?id=${id}`;
            break;
        case 'student':
            endpoint = `edit_student.php?id=${id}`;
            break;
        case 'parent':
            endpoint = `edit_parent.php?id=${id}`;
            break;
        case 'bursar':
            endpoint = `edit_bursar.php?id=${id}`;
            break;
        case 'payment':
            endpoint = `edit_payment.php?id=${id}`;
            break;
    }
    
    // Submit form
    fetch(endpoint, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Reload the page to show updated data
        window.location.reload();
    })
    .catch(error => {
        // Show error message
        submitButton.innerHTML = originalButtonText;
        submitButton.disabled = false;
        
        alert('Error saving changes: ' + error.message);
    });
}

/**
 * Open modal
 * @param {string} modalId - ID of modal to open
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden'; // Prevent scrolling behind modal
    }
}

/**
 * Open student multi-step modal
 * Opens the multi-step student registration modal
 */
function openStudentMultiStepModal() {
    // First make sure the modal exists
    const modal = document.getElementById('addStudentMultiStepModal');
    if (!modal) {
        console.error('Student multi-step modal not found');
        return;
    }
    
    // Open the modal
    openModal('addStudentMultiStepModal');
    
    // Reset form if it exists
    const form = document.getElementById('studentMultiStepForm');
    if (form) {
        form.reset();
        
        // Reset to first step
        const steps = document.querySelectorAll('.form-section');
        steps.forEach(step => step.classList.remove('active'));
        
        const stepIndicators = document.querySelectorAll('.step');
        stepIndicators.forEach(indicator => indicator.classList.remove('active'));
        
        // Activate first step
        const firstStep = document.getElementById('step1');
        if (firstStep) firstStep.classList.add('active');
        
        const firstStepIndicator = document.getElementById('step1-indicator');
        if (firstStepIndicator) firstStepIndicator.classList.add('active');
    }
}

/**
 * Close modal
 * @param {string} modalId - ID of modal to close
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = ''; // Restore scrolling
    }
}

// Close modal when clicking outside of it
window.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
        document.body.style.overflow = ''; // Restore scrolling
    }
});

// Close modal with Escape key
window.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modals = document.getElementsByClassName('modal');
        for (let i = 0; i < modals.length; i++) {
            if (modals[i].style.display === 'block') {
                modals[i].style.display = 'none';
                document.body.style.overflow = ''; // Restore scrolling
            }
        }
    }
});