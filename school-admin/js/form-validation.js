/**
 * Form Validation Handler
 * Handles validation for Teacher, Class, and Department forms
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize validation for all forms
    initializeTeacherFormValidation();
    initializeClassFormValidation();
    initializeDepartmentFormValidation();
});

/**
 * Initialize validation for the Teacher form
 */
function initializeTeacherFormValidation() {
    const form = document.getElementById('teacherForm');
    if (!form) return; // Exit if form doesn't exist
    
    // Get form fields
    const teacherName = document.getElementById('teacher_name');
    const teacherEmail = document.getElementById('teacher_email');
    const teacherPhone = document.getElementById('teacher_phone');
    const teacherDepartmentId = document.getElementById('teacher_department_id');
    
    // Get error elements
    const teacherNameError = document.getElementById('teacher_name-error');
    const teacherEmailError = document.getElementById('teacher_email-error');
    const teacherPhoneError = document.getElementById('teacher_phone-error');
    const teacherDepartmentIdError = document.getElementById('teacher_department_id-error');
    
    // Hide all error messages initially
    if (teacherNameError) teacherNameError.classList.remove('show');
    if (teacherEmailError) teacherEmailError.classList.remove('show');
    if (teacherPhoneError) teacherPhoneError.classList.remove('show');
    if (teacherDepartmentIdError) teacherDepartmentIdError.classList.remove('show');
    
    // Add form validation handler
    form.addEventListener('submit', function(event) {
        // The actual submission is now handled by the confirmation dialog in modals.php
        // This function only validates the form
        let isValid = true;
        
        // Validate teacher name
        if (teacherName && !teacherName.value.trim()) {
            teacherName.classList.add('error');
            if (teacherNameError) {
                teacherNameError.textContent = 'Teacher name is required';
                teacherNameError.classList.add('show');
            }
            isValid = false;
        } else if (teacherName) {
            teacherName.classList.remove('error');
            if (teacherNameError) teacherNameError.classList.remove('show');
        }
        
        // Validate teacher email
        if (teacherEmail && !teacherEmail.value.trim()) {
            teacherEmail.classList.add('error');
            if (teacherEmailError) {
                teacherEmailError.textContent = 'Email is required';
                teacherEmailError.classList.add('show');
            }
            isValid = false;
        } else if (teacherEmail && !isValidEmail(teacherEmail.value.trim())) {
            teacherEmail.classList.add('error');
            if (teacherEmailError) {
                teacherEmailError.textContent = 'Please enter a valid email address';
                teacherEmailError.classList.add('show');
            }
            isValid = false;
        } else if (teacherEmail) {
            teacherEmail.classList.remove('error');
            if (teacherEmailError) teacherEmailError.classList.remove('show');
        }
        
        // Validate teacher phone
        if (teacherPhone && !teacherPhone.value.trim()) {
            teacherPhone.classList.add('error');
            if (teacherPhoneError) {
                teacherPhoneError.textContent = 'Phone number is required';
                teacherPhoneError.classList.add('show');
            }
            isValid = false;
        } else if (teacherPhone) {
            teacherPhone.classList.remove('error');
            if (teacherPhoneError) teacherPhoneError.classList.remove('show');
        }
        
        // Validate department selection
        if (teacherDepartmentId && !teacherDepartmentId.value) {
            teacherDepartmentId.classList.add('error');
            if (teacherDepartmentIdError) {
                teacherDepartmentIdError.textContent = 'Please select a department';
                teacherDepartmentIdError.classList.add('show');
            }
            isValid = false;
        } else if (teacherDepartmentId) {
            teacherDepartmentId.classList.remove('error');
            if (teacherDepartmentIdError) teacherDepartmentIdError.classList.remove('show');
        }
        
        // If not valid, prevent form submission
        if (!isValid) {
            event.preventDefault();
        }
        
        // Prevent form submission if validation fails
        if (!isValid) {
            event.preventDefault();
        } else {
            // Show loading state on the save button
            const saveButton = document.getElementById('saveTeacherBtn');
            if (saveButton) {
                saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                saveButton.disabled = true;
            }
        }
    });
    
    // Add input event listeners to clear errors on input
    if (teacherName) {
        teacherName.addEventListener('input', function() {
            this.classList.remove('error');
            if (teacherNameError) teacherNameError.classList.remove('show');
        });
    }
    
    if (teacherEmail) {
        teacherEmail.addEventListener('input', function() {
            this.classList.remove('error');
            if (teacherEmailError) teacherEmailError.classList.remove('show');
        });
    }
    
    if (teacherPhone) {
        teacherPhone.addEventListener('input', function() {
            this.classList.remove('error');
            if (teacherPhoneError) teacherPhoneError.classList.remove('show');
        });
    }
    
    if (teacherDepartmentId) {
        teacherDepartmentId.addEventListener('change', function() {
            this.classList.remove('error');
            if (teacherDepartmentIdError) teacherDepartmentIdError.classList.remove('show');
        });
    }
}

/**
 * Initialize validation for the Class form
 */
function initializeClassFormValidation() {
    const form = document.getElementById('classForm');
    if (!form) return; // Exit if form doesn't exist
    
    // Get form fields
    const className = document.getElementById('class_name');
    const gradeLevel = document.getElementById('grade_level');
    
    // Get error elements
    const classNameError = document.getElementById('class_name-error');
    const gradeLevelError = document.getElementById('grade_level-error');
    
    // Hide all error messages initially
    if (classNameError) classNameError.classList.remove('show');
    if (gradeLevelError) gradeLevelError.classList.remove('show');
    
    // Add form submission handler
    form.addEventListener('submit', function(event) {
        let isValid = true;
        
        // Validate class name
        if (className && !className.value.trim()) {
            className.classList.add('error');
            if (classNameError) {
                classNameError.textContent = 'Class name is required';
                classNameError.classList.add('show');
            }
            isValid = false;
        } else if (className) {
            className.classList.remove('error');
            if (classNameError) classNameError.classList.remove('show');
        }
        
        // Validate grade level
        if (gradeLevel && !gradeLevel.value) {
            gradeLevel.classList.add('error');
            if (gradeLevelError) {
                gradeLevelError.textContent = 'Grade level is required';
                gradeLevelError.classList.add('show');
            }
            isValid = false;
        } else if (gradeLevel) {
            gradeLevel.classList.remove('error');
            if (gradeLevelError) gradeLevelError.classList.remove('show');
        }
        
        // Prevent form submission if validation fails
        if (!isValid) {
            event.preventDefault();
        } else {
            // Show loading state on the save button
            const saveButton = document.getElementById('saveClassBtn');
            if (saveButton) {
                saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                saveButton.disabled = true;
            }
        }
    });
    
    // Add input event listeners to clear errors on input
    if (className) {
        className.addEventListener('input', function() {
            this.classList.remove('error');
            if (classNameError) classNameError.classList.remove('show');
        });
    }
    
    if (gradeLevel) {
        gradeLevel.addEventListener('change', function() {
            this.classList.remove('error');
            if (gradeLevelError) gradeLevelError.classList.remove('show');
        });
    }
}

/**
 * Initialize validation for the Department form
 */
function initializeDepartmentFormValidation() {
    const form = document.getElementById('departmentForm');
    if (!form) return; // Exit if form doesn't exist
    
    // Get form fields
    const departmentName = document.getElementById('department_name');
    
    // Get error elements
    const departmentNameError = document.getElementById('department_name-error');
    
    // Hide all error messages initially
    if (departmentNameError) departmentNameError.classList.remove('show');
    
    // Add form submission handler
    form.addEventListener('submit', function(event) {
        let isValid = true;
        
        // Validate department name
        if (departmentName && !departmentName.value.trim()) {
            departmentName.classList.add('error');
            if (departmentNameError) {
                departmentNameError.textContent = 'Department name is required';
                departmentNameError.classList.add('show');
            }
            isValid = false;
        } else if (departmentName) {
            departmentName.classList.remove('error');
            if (departmentNameError) departmentNameError.classList.remove('show');
        }
        
        // Prevent form submission if validation fails
        if (!isValid) {
            event.preventDefault();
        } else {
            // Show loading state on the save button
            const saveButton = document.getElementById('saveDepartmentBtn');
            if (saveButton) {
                saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
                saveButton.disabled = true;
            }
        }
    });
    
    // Add input event listeners to clear errors on input
    if (departmentName) {
        departmentName.addEventListener('input', function() {
            this.classList.remove('error');
            if (departmentNameError) departmentNameError.classList.remove('show');
        });
    }
}

/**
 * Validate email format
 * @param {string} email - The email to validate
 * @returns {boolean} - Whether the email is valid
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}