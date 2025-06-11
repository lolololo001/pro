/**
 * Multi-Step Form Handler for Student Registration
 * Handles navigation between form steps, validation, and form submission
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize the multi-step form if it exists
    initializeMultiStepForm();
});

/**
 * Initialize the multi-step form functionality
 */
function initializeMultiStepForm() {
    // Get form elements - check for both modal and non-modal form IDs
    const form = document.getElementById('studentMultiStepForm') || document.getElementById('modalStudentForm');
    if (!form) return; // Exit if form doesn't exist
    
    // Determine if we're in a modal context
    const isModal = form.id === 'modalStudentForm';
    const prefix = isModal ? 'modal-' : '';
    
    // Get step sections
    const step1 = document.getElementById(prefix + 'step1') || document.getElementById('step1');
    const step2 = document.getElementById(prefix + 'step2') || document.getElementById('step2');
    const step3 = document.getElementById(prefix + 'step3') || document.getElementById('step3');
    
    // Get step indicators
    const step1Indicator = document.getElementById(prefix + 'step1-indicator') || document.getElementById('step1-indicator');
    const step2Indicator = document.getElementById(prefix + 'step2-indicator') || document.getElementById('step2-indicator');
    const step3Indicator = document.getElementById(prefix + 'step3-indicator') || document.getElementById('step3-indicator');
    
    // Get navigation buttons - check for both modal and non-modal button IDs
    const step1Next = document.getElementById('step1Next') || document.getElementById('modalStep1Next');
    const step2Prev = document.getElementById('step2Prev') || document.getElementById('modalStep2Prev');
    const step2Next = document.getElementById('step2Next') || document.getElementById('modalStep2Next');    
    const step3Prev = document.getElementById('step3Prev') || document.getElementById('modalStep3Prev');
    const saveStudentBtn = document.getElementById('saveStudentBtn') || document.getElementById('modalSaveStudentBtn') || document.getElementById('modalSubmitBtn');
    
    // Get confirmation dialog elements
    const confirmationDialog = document.getElementById('registrationConfirmationDialog');
    const confirmationOkBtn = document.getElementById('confirmationOkBtn');
    const closeConfirmationModal = document.getElementById('closeConfirmationModal');
    const successMessage = document.getElementById('successMessage');
    const errorMessage = document.getElementById('errorMessage');
    const studentRegNumber = document.getElementById('studentRegNumber');
    
    // Add event listeners for navigation buttons - Progressive step completion
    if (step1Next) {
        step1Next.addEventListener('click', function() {
            // Check if step 1 is complete before allowing progression
            if (isStep1Complete()) {
                // Enable step 2 and navigate
                enableStep(2);
                goToStep(2);
            } else {
                // Show validation errors for incomplete fields
                validateStep1WithErrors();
            }
        });
    }

    if (step2Prev) {
        step2Prev.addEventListener('click', function() {
            goToStep(1);
        });
    }

    if (step2Next) {
        step2Next.addEventListener('click', function() {
            // Check if step 2 is complete before allowing progression
            if (isStep2Complete()) {
                // Enable step 3 and navigate
                enableStep(3);
                goToStep(3);
            } else {
                // Show validation errors for incomplete fields
                validateStep2WithErrors();
            }
        });
    }

    if (step3Prev) {
        step3Prev.addEventListener('click', function() {
            goToStep(2);
        });
    }
    
    // Handle form submission
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission

            if (validateAllStepsForSubmission()) {
                // Show enhanced loading state
                showLoadingState(saveStudentBtn);

                // Add success animation before submission
                setTimeout(() => {
                    // Submit the form
                    form.submit();
                }, 500);
            } else {
                // Shake animation for validation errors
                shakeForm();
            }
        });
    }

    /**
     * Show enhanced loading state for button
     * @param {HTMLElement} button - The button to show loading state
     */
    function showLoadingState(button) {
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving Student...';
        button.disabled = true;
        button.style.background = '#6c757d';

        // Add pulse animation
        button.style.animation = 'pulse 1.5s infinite';
    }

    /**
     * Shake form for validation errors
     */
    function shakeForm() {
        const activeStep = document.querySelector('.enhanced-form-section.active');
        if (activeStep) {
            activeStep.style.animation = 'shake 0.5s ease-in-out';
            setTimeout(() => {
                activeStep.style.animation = '';
            }, 500);
        }
    }
    
    // Add event listeners for confirmation dialog
    if (confirmationOkBtn) {
        confirmationOkBtn.addEventListener('click', function() {
            closeModal('registrationConfirmationDialog');
            closeModal('addStudentMultiStepModal');
            // Optionally refresh the page or update the student list
            // window.location.reload();
        });
    }

    if (closeConfirmationModal) {
        closeConfirmationModal.addEventListener('click', function() {
            closeModal('registrationConfirmationDialog');
        });
    }

    // Add real-time validation for better user experience
    addRealTimeValidation();

    // Add click handlers to step indicators for direct navigation
    addStepClickHandlers();

    // Initialize form with only step 1 accessible
    initializeFormSteps();
    
    /**
     * Navigate to the specified step with enhanced animations
     * @param {number} stepNumber - The step number to navigate to
     */
    function goToStep(stepNumber) {
        // Enhanced step navigation with animations

        // Hide all steps with smooth slide out animation
        const allSteps = [step1, step2, step3];
        const currentActiveStep = document.querySelector('.enhanced-form-section.active');

        if (currentActiveStep) {
            currentActiveStep.style.transition = 'all 0.4s ease';
            currentActiveStep.style.opacity = '0';
            currentActiveStep.style.transform = 'translateX(-50px)';

            setTimeout(() => {
                allSteps.forEach(step => {
                    if (step) {
                        step.classList.remove('active');
                        step.style.display = 'none';
                    }
                });
            }, 200);
        }

        // Reset all indicators
        step1Indicator.classList.remove('active', 'completed');
        step2Indicator.classList.remove('active', 'completed');
        step3Indicator.classList.remove('active', 'completed');

        // Update progress bar
        updateProgressBar(stepNumber);

        // Show the selected step with smooth slide in animation
        setTimeout(() => {
            let targetStep;

            if (stepNumber === 1) {
                targetStep = step1;
                step1.classList.add('active');
                step1Indicator.classList.add('active');
            } else if (stepNumber === 2) {
                targetStep = step2;
                step2.classList.add('active');
                step1Indicator.classList.add('completed');
                step2Indicator.classList.add('active');
            } else if (stepNumber === 3) {
                targetStep = step3;
                step3.classList.add('active');
                step1Indicator.classList.add('completed');
                step2Indicator.classList.add('completed');
                step3Indicator.classList.add('active');
            }

            if (targetStep) {
                // Prepare for slide in animation
                targetStep.style.display = 'block';
                targetStep.style.opacity = '0';
                targetStep.style.transform = 'translateX(50px)';
                targetStep.style.transition = 'all 0.4s ease';

                // Trigger slide in animation
                setTimeout(() => {
                    targetStep.style.opacity = '1';
                    targetStep.style.transform = 'translateX(0)';
                }, 50);
            }
        }, 250);

        // Smooth scroll to top of form
        const modalBody = document.querySelector('.enhanced-modal-body') || document.querySelector('.modal-body');
        if (modalBody) {
            modalBody.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }
    }



    /**
     * Update progress bar animation
     * @param {number} stepNumber - Current step number
     */
    function updateProgressBar(stepNumber) {
        const progressLine = document.getElementById('progress-line');
        if (progressLine) {
            let width = '0%';
            if (stepNumber === 2) width = '50%';
            else if (stepNumber === 3) width = '100%';

            progressLine.style.width = width;
        }
    }
    
    /**
     * Enable a specific step for navigation with visual feedback
     * @param {number} stepNumber - The step number to enable
     */
    function enableStep(stepNumber) {
        const stepIndicator = document.getElementById(`modal-step${stepNumber}-indicator`);
        if (stepIndicator && !stepIndicator.classList.contains('accessible')) {
            // Add accessible class with animation
            stepIndicator.classList.add('accessible');
            stepIndicator.style.cursor = 'pointer';

            // Add a subtle pulse animation to indicate it's now accessible
            stepIndicator.style.animation = 'pulse 0.6s ease';
            setTimeout(() => {
                stepIndicator.style.animation = '';
            }, 600);
        }
    }

    /**
     * Check if Step 1 is complete (all required fields filled)
     * @returns {boolean} - Whether step 1 is complete
     */
    function isStep1Complete() {
        const firstName = document.getElementById('modal_first_name');
        const lastName = document.getElementById('modal_last_name');
        const gender = document.getElementById('modal_gender');
        const dob = document.getElementById('modal_dob');

        return firstName?.value?.trim() &&
               lastName?.value?.trim() &&
               gender?.value &&
               dob?.value;
    }

    /**
     * Validate Step 1 and show errors for incomplete fields
     * @returns {boolean} - Whether step 1 is valid
     */
    function validateStep1WithErrors() {
        let isValid = true;
        const firstName = document.getElementById('modal_first_name');
        const lastName = document.getElementById('modal_last_name');
        const gender = document.getElementById('modal_gender');
        const dob = document.getElementById('modal_dob');

        // Reset previous error states first
        resetErrorState(firstName);
        resetErrorState(lastName);
        resetErrorState(gender);
        resetErrorState(dob);

        // Validate each field only if it's empty
        if (!firstName?.value?.trim()) {
            showError(firstName, 'modal_first_name-error', 'First name is required');
            isValid = false;
        }

        if (!lastName?.value?.trim()) {
            showError(lastName, 'modal_last_name-error', 'Last name is required');
            isValid = false;
        }

        if (!gender?.value) {
            showError(gender, 'modal_gender-error', 'Please select a gender');
            isValid = false;
        }

        if (!dob?.value) {
            showError(dob, 'modal_dob-error', 'Date of birth is required');
            isValid = false;
        }

        if (!isValid) {
            shakeForm();
        }

        return isValid;
    }

    /**
     * Check if Step 2 is complete (all required fields filled)
     * @returns {boolean} - Whether step 2 is complete
     */
    function isStep2Complete() {
        const classId = document.getElementById('modal_class_id');
        return classId?.value;
    }

    /**
     * Validate Step 2 and show errors for incomplete fields
     * @returns {boolean} - Whether step 2 is valid
     */
    function validateStep2WithErrors() {
        let isValid = true;
        const classId = document.getElementById('modal_class_id');

        // Reset previous error states
        resetErrorState(classId);

        // Validate class selection
        if (!classId?.value) {
            showError(classId, 'modal_class_id-error', 'Please select a class');
            isValid = false;
        }

        if (!isValid) {
            shakeForm();
        }

        return isValid;
    }

    /**
     * Check if Step 3 is complete (all required fields filled)
     * @returns {boolean} - Whether step 3 is complete
     */
    function isStep3Complete() {
        const parentName = document.getElementById('parent_name') || document.getElementById('modal_parent_name');
        const parentPhone = document.getElementById('parent_phone') || document.getElementById('modal_parent_phone');
        const parentEmail = document.getElementById('parent_email') || document.getElementById('modal_parent_email');

        const isValid = parentName?.value.trim() && parentPhone?.value.trim();

        // Check email format if provided
        if (parentEmail?.value.trim()) {
            return isValid && isValidEmail(parentEmail.value.trim());
        }

        return isValid;
    }

    /**
     * Validate all steps for final form submission - only show errors for empty fields
     * @returns {boolean} - Whether all steps are valid
     */
    function validateAllStepsForSubmission() {
        let isValid = true;

        // Get form fields
        const firstName = document.getElementById('modal_first_name');
        const lastName = document.getElementById('modal_last_name');
        const gender = document.getElementById('modal_gender');
        const dob = document.getElementById('modal_dob');
        const classId = document.getElementById('modal_class_id');
        const parentName = document.getElementById('modal_parent_name');
        const parentPhone = document.getElementById('modal_parent_phone');
        const parentEmail = document.getElementById('modal_parent_email');

        // Reset all error states first
        resetErrorState(firstName);
        resetErrorState(lastName);
        resetErrorState(gender);
        resetErrorState(dob);
        resetErrorState(classId);
        resetErrorState(parentName);
        resetErrorState(parentPhone);
        resetErrorState(parentEmail);

        // Validate Step 1 - Only show errors for truly empty fields
        if (!firstName?.value?.trim()) {
            showError(firstName, 'modal_first_name-error', 'First name is required');
            isValid = false;
        }

        if (!lastName?.value?.trim()) {
            showError(lastName, 'modal_last_name-error', 'Last name is required');
            isValid = false;
        }

        if (!gender?.value) {
            showError(gender, 'modal_gender-error', 'Please select a gender');
            isValid = false;
        }

        if (!dob?.value) {
            showError(dob, 'modal_dob-error', 'Date of birth is required');
            isValid = false;
        }

        // Validate Step 2
        if (!classId?.value) {
            showError(classId, 'modal_class_id-error', 'Please select a class');
            isValid = false;
        }

        // Validate Step 3
        if (!parentName?.value?.trim()) {
            showError(parentName, 'modal_parent_name-error', 'Parent/Guardian name is required');
            isValid = false;
        }

        if (!parentPhone?.value?.trim()) {
            showError(parentPhone, 'modal_parent_phone-error', 'Parent/Guardian phone is required');
            isValid = false;
        }

        // Validate Parent/Guardian Email (only if provided)
        if (parentEmail?.value?.trim() && !isValidEmail(parentEmail.value.trim())) {
            showError(parentEmail, 'modal_parent_email-error', 'Please enter a valid email address');
            isValid = false;
        }

        // If validation fails, go to the first step with errors
        if (!isValid) {
            if (!firstName?.value?.trim() || !lastName?.value?.trim() || !gender?.value || !dob?.value) {
                goToStep(1);
            } else if (!classId?.value) {
                goToStep(2);
            } else {
                goToStep(3);
            }
        }

        return isValid;
    }


    

    
    /**
     * Show error for a form field
     * @param {HTMLElement} field - The form field
     * @param {string} errorId - The ID of the error message element
     * @param {string} message - The error message to display
     */
    function showError(field, errorId, message) {
        if (!field) return; // Guard against null fields
        
        // Add error class to the field
        field.classList.add('error');
        
        // Try all possible error element ID patterns
        const possibleErrorIds = [
            errorId,                                  // Direct ID provided
            field.id + '-error',                     // Field ID + -error
            'modal_' + errorId,                      // modal_ + provided ID
            errorId.replace('modal_', ''),           // Remove modal_ prefix if present
            field.id.replace('modal_', '') + '-error' // Field ID without modal_ prefix + -error
        ];
        
        // If field ID has modal_ prefix, also try without it
        if (field.id && field.id.indexOf('modal_') === 0) {
            const baseFieldId = field.id.replace('modal_', '');
            possibleErrorIds.push(baseFieldId + '-error');
            possibleErrorIds.push('modal_' + baseFieldId + '-error');
        } 
        // If field ID doesn't have modal_ prefix, also try with it
        else if (field.id) {
            possibleErrorIds.push('modal_' + field.id + '-error');
        }
        
        // Try to find the error element using all possible IDs
        let errorElement = null;
        for (const id of possibleErrorIds) {
            const element = document.getElementById(id);
            if (element) {
                errorElement = element;
                break;
            }
        }
        
        if (errorElement) {
            // Set the custom error message
            errorElement.textContent = message;
            errorElement.classList.add('show');
        }
    }
    
    /**
     * Reset error state for a form field
     * @param {HTMLElement} field - The form field
     */
    function resetErrorState(field) {
        if (!field) return; // Guard against null fields
        
        field.classList.remove('error');
        
        // Try all possible error element ID patterns
        const possibleErrorIds = [
            field.id + '-error',                     // Field ID + -error
            'modal_' + field.id + '-error',         // modal_ + field ID + -error
            field.id.replace('modal_', '') + '-error' // Field ID without modal_ prefix + -error
        ];
        
        // If field ID has modal_ prefix, also try without it
        if (field.id.indexOf('modal_') === 0) {
            const baseFieldId = field.id.replace('modal_', '');
            possibleErrorIds.push(baseFieldId + '-error');
            possibleErrorIds.push('modal_' + baseFieldId + '-error');
        } else {
            // If field ID doesn't have modal_ prefix, also try with it
            possibleErrorIds.push('modal_' + field.id + '-error');
        }
        
        // Try to find the error element using all possible IDs
        let errorElement = null;
        for (const id of possibleErrorIds) {
            const element = document.getElementById(id);
            if (element) {
                errorElement = element;
                break;
            }
        }
        
        if (errorElement) {
            errorElement.classList.remove('show');
            // Clear any previous error message
            errorElement.textContent = '';
        }
    }
    
    /**
     * Add click handlers to step indicators for direct navigation
     */
    function addStepClickHandlers() {
        const step1Indicator = document.getElementById('modal-step1-indicator');
        const step2Indicator = document.getElementById('modal-step2-indicator');
        const step3Indicator = document.getElementById('modal-step3-indicator');

        // Step 1 is always accessible
        if (step1Indicator) {
            step1Indicator.addEventListener('click', function() {
                goToStep(1);
            });
        }

        // Step 2 and 3 will be enabled when previous steps are completed
        if (step2Indicator) {
            step2Indicator.addEventListener('click', function() {
                if (this.classList.contains('accessible')) {
                    goToStep(2);
                }
            });
        }

        if (step3Indicator) {
            step3Indicator.addEventListener('click', function() {
                if (this.classList.contains('accessible')) {
                    goToStep(3);
                }
            });
        }
    }

    /**
     * Initialize form steps - only step 1 is accessible initially
     */
    function initializeFormSteps() {
        const step1Indicator = document.getElementById('modal-step1-indicator');
        const step2Indicator = document.getElementById('modal-step2-indicator');
        const step3Indicator = document.getElementById('modal-step3-indicator');

        // Only step 1 is accessible initially
        if (step1Indicator) {
            step1Indicator.classList.add('accessible');
        }

        // Steps 2 and 3 are disabled initially
        if (step2Indicator) {
            step2Indicator.classList.remove('accessible');
        }

        if (step3Indicator) {
            step3Indicator.classList.remove('accessible');
        }
    }

    /**
     * Add real-time validation to form fields
     */
    function addRealTimeValidation() {
        // Get modal form fields
        const firstName = document.getElementById('modal_first_name');
        const lastName = document.getElementById('modal_last_name');
        const gender = document.getElementById('modal_gender');
        const dob = document.getElementById('modal_dob');
        const classId = document.getElementById('modal_class_id');
        const parentName = document.getElementById('modal_parent_name');
        const parentPhone = document.getElementById('modal_parent_phone');
        const parentEmail = document.getElementById('modal_parent_email');

        // Add event listeners for Step 1 fields - Only show errors on blur when empty
        if (firstName) {
            firstName.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    showError(this, 'modal_first_name-error', 'First name is required');
                } else {
                    resetErrorState(this);
                }
            });
            firstName.addEventListener('input', function() {
                // Always clear error when user starts typing
                resetErrorState(this);
            });
        }

        if (lastName) {
            lastName.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    showError(this, 'modal_last_name-error', 'Last name is required');
                } else {
                    resetErrorState(this);
                }
            });
            lastName.addEventListener('input', function() {
                // Always clear error when user starts typing
                resetErrorState(this);
            });
        }

        if (gender) {
            gender.addEventListener('blur', function() {
                if (!this.value) {
                    showError(this, 'modal_gender-error', 'Please select a gender');
                } else {
                    resetErrorState(this);
                }
            });
            gender.addEventListener('change', function() {
                // Always clear error when user makes selection
                resetErrorState(this);
            });
        }

        if (dob) {
            dob.addEventListener('blur', function() {
                if (!this.value) {
                    showError(this, 'modal_dob-error', 'Date of birth is required');
                } else {
                    resetErrorState(this);
                }
            });
            dob.addEventListener('change', function() {
                // Always clear error when user selects date
                resetErrorState(this);
            });
        }

        // Add event listeners for Step 2 fields
        if (classId) {
            classId.addEventListener('blur', function() {
                if (!this.value) {
                    showError(this, 'modal_class_id-error', 'Please select a class');
                } else {
                    resetErrorState(this);
                }
            });
            classId.addEventListener('change', function() {
                if (this.value) {
                    resetErrorState(this);
                }
            });
        }

        // Add event listeners for Step 3 fields
        if (parentName) {
            parentName.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    showError(this, 'modal_parent_name-error', 'Parent/Guardian name is required');
                } else {
                    resetErrorState(this);
                }
            });
            parentName.addEventListener('input', function() {
                if (this.value.trim()) {
                    resetErrorState(this);
                }
            });
        }

        if (parentPhone) {
            parentPhone.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    showError(this, 'modal_parent_phone-error', 'Parent/Guardian phone is required');
                } else {
                    resetErrorState(this);
                }
            });
            parentPhone.addEventListener('input', function() {
                if (this.value.trim()) {
                    resetErrorState(this);
                }
            });
        }

        if (parentEmail) {
            parentEmail.addEventListener('blur', function() {
                if (this.value.trim() && !isValidEmail(this.value.trim())) {
                    showError(this, 'modal_parent_email-error', 'Please enter a valid email address');
                } else {
                    resetErrorState(this);
                }
            });
            parentEmail.addEventListener('input', function() {
                if (!this.value.trim() || isValidEmail(this.value.trim())) {
                    resetErrorState(this);
                }
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
}

/**
 * Open a modal by ID
 * @param {string} modalId - The ID of the modal to open
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        document.body.classList.add('modal-open');
    }
}

/**
 * Close a modal by ID
 * @param {string} modalId - The ID of the modal to close
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.classList.remove('modal-open');
    }
}

/**
 * Show the registration confirmation dialog
 * @param {boolean} success - Whether the registration was successful
 * @param {string} regNumber - The registration number of the student
 */
function showRegistrationConfirmation(success, regNumber = '') {
    const successMessage = document.getElementById('successMessage');
    const errorMessage = document.getElementById('errorMessage');
    const studentRegNumber = document.getElementById('studentRegNumber');
    
    if (success) {
        successMessage.style.display = 'block';
        errorMessage.style.display = 'none';
        if (studentRegNumber) {
            studentRegNumber.textContent = regNumber;
        }
    } else {
        successMessage.style.display = 'none';
        errorMessage.style.display = 'block';
    }
    
    openModal('registrationConfirmationDialog');
}