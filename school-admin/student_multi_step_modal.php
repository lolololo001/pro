<?php
// Get all departments for this school
$departments = [];
$stmt = $conn->prepare("SELECT * FROM departments WHERE school_id = ? ORDER BY department_name ASC");
$stmt->bind_param('i', $school_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $departments[] = $row;
}
$stmt->close();

// Get all classes for this school
$classes = [];
$stmt = $conn->prepare("SELECT * FROM classes WHERE school_id = ? ORDER BY grade_level ASC, class_name ASC");
$stmt->bind_param('i', $school_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $classes[] = $row;
}
$stmt->close();
?>

<!-- Student Registration Modal with Multi-Step Form -->
<div id="addStudentMultiStepModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-user-graduate"></i> Add New Student</h2>
            <span class="close-modal" onclick="closeModal('addStudentMultiStepModal')">&times;</span>
        </div>
        <div class="modal-body">
            <?php if (isset($_SESSION['student_error'])): ?>
                <div class="form-alert form-alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $_SESSION['student_error']; unset($_SESSION['student_error']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Form Steps Indicator -->
            <div class="form-steps">
                <div class="step active" id="step1-indicator">
                    <div class="step-number">1</div>
                    <div class="step-title">Student Information</div>
                </div>
                <div class="step" id="step2-indicator">
                    <div class="step-number">2</div>
                    <div class="step-title">Academic Details</div>
                </div>
                <div class="step" id="step3-indicator">
                    <div class="step-number">3</div>
                    <div class="step-title">Parent/Guardian Details</div>
                </div>
            </div>
            
            <form id="studentMultiStepForm" action="add_student.php" method="post">
                <!-- Ensure redirect to dashboard after successful registration -->
                <input type="hidden" name="redirect_to" value="dashboard.php">
                
                <!-- Step 1: Student Personal Information -->
                <div class="form-section active" id="step1">
                    <h3 class="section-title"><i class="fas fa-user-graduate"></i> Student Personal Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name" class="required-label">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-control" placeholder="Enter first name" required>
                            <div class="error-message" id="first_name-error">Please enter the first name</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name" class="required-label">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-control" placeholder="Enter last name" required>
                            <div class="error-message" id="last_name-error">Please enter the last name</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="gender" class="required-label">Gender</label>
                            <select id="gender" name="gender" class="form-control" required>
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                            <div class="error-message" id="gender-error">Please select a gender</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="dob" class="required-label">Date of Birth</label>
                            <input type="date" id="dob" name="dob" class="form-control" required>
                            <div class="error-message" id="dob-error">Please enter the date of birth</div>
                        </div>
                    </div>
                    
                    <div class="form-navigation">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addStudentMultiStepModal')">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="button" class="btn btn-primary" id="step1Next">
                            Next <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Step 2: Academic Information -->
                <div class="form-section" id="step2">
                    <h3 class="section-title"><i class="fas fa-graduation-cap"></i> Academic Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="class_id" class="required-label">Class</label>
                            <select id="class_id" name="class_id" class="form-control" required>
                                <option value="">Select Class</option>
                                <?php 
                                // Group classes by education level
                                $ordinary_level_classes = [];
                                $advanced_level_classes = [];
                                $other_classes = [];
                                
                                foreach ($classes as $class) {
                                    // Check if grade level contains O-Level or A-Level
                                    if (stripos($class['grade_level'], 'O-Level') !== false || stripos($class['grade_level'], 'O Level') !== false || in_array($class['grade_level'], ['S1', 'S2', 'S3', 'S4'])) {
                                        $ordinary_level_classes[] = $class;
                                    } elseif (stripos($class['grade_level'], 'A-Level') !== false || stripos($class['grade_level'], 'A Level') !== false || in_array($class['grade_level'], ['S5', 'S6'])) {
                                        $advanced_level_classes[] = $class;
                                    } else {
                                        $other_classes[] = $class;
                                    }
                                }
                                ?>
                                
                                <?php if (!empty($ordinary_level_classes)): ?>
                                    <optgroup label="Ordinary Level (O-Level)">
                                        <?php foreach ($ordinary_level_classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>">
                                                <?php echo htmlspecialchars($class['grade_level'] . ' - ' . $class['class_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                
                                <?php if (!empty($advanced_level_classes)): ?>
                                    <optgroup label="Advanced Level (A-Level)">
                                        <?php foreach ($advanced_level_classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>">
                                                <?php echo htmlspecialchars($class['grade_level'] . ' - ' . $class['class_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                
                                <?php if (!empty($other_classes)): ?>
                                    <optgroup label="Other Classes">
                                        <?php foreach ($other_classes as $class): ?>
                                            <option value="<?php echo $class['id']; ?>">
                                                <?php echo htmlspecialchars($class['grade_level'] . ' - ' . $class['class_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                            <div class="error-message" id="class_id-error">Please select a class</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="department_id">Department</label>
                            <select id="department_id" name="department_id" class="form-control">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $department): ?>
                                    <option value="<?php echo $department['dep_id']; ?>"><?php echo htmlspecialchars($department['department_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-navigation">
                        <button type="button" class="btn btn-secondary" id="step2Prev">
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                        <button type="button" class="btn btn-primary" id="step2Next">
                            Next <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Step 3: Parent/Guardian Information -->
                <div class="form-section" id="step3">
                    <h3 class="section-title"><i class="fas fa-users"></i> Parent/Guardian Information</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="parent_name" class="required-label">Parent/Guardian Name</label>
                            <input type="text" id="parent_name" name="parent_name" class="form-control" placeholder="Enter parent/guardian name" required>
                            <div class="error-message" id="parent_name-error">Please enter the parent/guardian name</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="parent_phone" class="required-label">Parent/Guardian Phone</label>
                            <input type="tel" id="parent_phone" name="parent_phone" class="form-control" placeholder="Enter parent/guardian phone" required>
                            <div class="error-message" id="parent_phone-error">Please enter the parent/guardian phone</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="parent_email">Parent/Guardian Email</label>
                            <input type="email" id="parent_email" name="parent_email" class="form-control" placeholder="Enter parent/guardian email">
                            <div class="error-message" id="parent_email-error">Please enter a valid email address</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea id="address" name="address" class="form-control" rows="3" placeholder="Enter address"></textarea>
                        </div>
                    </div>
                    
                    <div class="form-navigation">
                        <button type="button" class="btn btn-secondary" id="step3Prev">
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                        <button type="button" class="btn btn-primary" id="saveStudentBtn">
                            <i class="fas fa-save"></i> Save Student
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Registration Confirmation Dialog -->
<div id="registrationConfirmationDialog" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fas fa-check-circle"></i> <span id="confirmationTitle">Student Registration</span></h3>
            <span class="close-modal" id="closeConfirmationModal" style="font-size: 1.5rem; cursor: pointer; transition: all 0.3s;">&times;</span>
        </div>
        <div class="modal-body">
            <div id="successMessage" style="display: none;">
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--success-color); margin-bottom: 1rem;"></i>
                    <h4>Success!</h4>
                    <p>Student <strong id="studentRegNumber"></strong> has been successfully registered.</p>
                </div>
            </div>
            <div id="errorMessage" style="display: none;">
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <i class="fas fa-times-circle" style="font-size: 3rem; color: var(--danger-color); margin-bottom: 1rem;"></i>
                    <h4>Error!</h4>
                    <p>Student registration failed. Please try again.</p>
                </div>
            </div>
            <div class="form-actions" style="justify-content: center;">
                <button type="button" class="btn btn-primary" id="confirmationOkBtn">
                    <i class="fas fa-check"></i> OK
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Additional styles for the confirmation dialog */
    #closeConfirmationModal:hover {
        color: var(--danger-color);
        transform: scale(1.2);
    }
    
    /* Form navigation styles */
    .form-navigation {
        display: flex;
        justify-content: space-between;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid #e0e0e0;
    }
    
    .form-navigation button {
        min-width: 120px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.3s ease;
    }
    
    .form-navigation button:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    
    .form-navigation button:active {
        transform: translateY(0);
    }
    
    /* Error message styles */
    .error-message {
        color: var(--danger-color);
        font-size: 0.85rem;
        margin-top: 0.3rem;
        display: none;
    }
    
    .error-message.show {
        display: block;
        animation: fadeIn 0.3s ease-in-out;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    /* Step indicator active styles */
    .step.active .step-number {
        background-color: var(--primary-color);
        color: white;
        box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb), 0.2);
    }
    
    .step.active .step-title {
        color: var(--primary-color);
        font-weight: 600;
    }
    
    /* Form section transition styles */
    .form-section {
        opacity: 0;
        transform: translateY(10px);
        transition: opacity 0.4s ease, transform 0.4s ease;
    }
    
    .form-section.active {
        opacity: 1;
        transform: translateY(0);
    }
    
    .form-control.error {
        border-color: var(--danger-color);
        box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.25);
    }
    
    /* Required label styles */
    .required-label::after {
        content: ' *';
        color: var(--danger-color);
    }
    
    /* Enhanced form styles */
    .form-section {
        display: none;
        padding: 1.75rem;
        border-radius: 10px;
        background-color: #f9f9f9;
        box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        animation: fadeIn 0.4s ease-in-out;
    }
    
    .form-section.active {
        display: block;
    }
    
    .section-title {
        margin-bottom: 1.75rem;
        color: #333;
        border-bottom: 2px solid #e0e0e0;
        padding-bottom: 0.75rem;
        font-weight: 600;
    }
    
    .section-title i {
        margin-right: 8px;
        color: var(--primary-color);
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1.5rem;
    }
    
    .form-group {
        margin-bottom: 1.25rem;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: #444;
    }
    
    .form-control {
        width: 100%;
        padding: 0.85rem;
        border: 1px solid #ddd;
        border-radius: 6px;
        transition: all 0.3s ease;
        font-size: 1rem;
    }
    
    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(var(--primary-color-rgb), 0.2);
        outline: none;
    }
    
    .form-control:hover:not(:focus):not(.error) {
        border-color: #b0b0b0;
    }
    
    select.form-control {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 10px center;
        background-size: 16px;
        padding-right: 35px;
    }
    
    select.form-control optgroup {
        font-weight: 600;
        color: #444;
    }
    
    select.form-control option {
        padding: 8px;
    }
    
    /* Step indicator enhancements */
    .form-steps {
        display: flex;
        justify-content: space-between;
        margin-bottom: 2.5rem;
        position: relative;
    }
    
    .form-steps::before {
        content: '';
        position: absolute;
        top: 20px;
        left: 0;
        right: 0;
        height: 3px;
        background: #e0e0e0;
        z-index: 1;
    }
    
    .step {
        position: relative;
        z-index: 2;
        background: white;
        padding: 0 15px;
        text-align: center;
        flex: 1;
        transition: all 0.3s ease;
        transition: all 0.3s ease;
    }
    
    .step-number {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: #e0e0e0;
        color: #666;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 12px;
        font-weight: bold;
        transition: all 0.3s;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .step.active .step-number {
        background: var(--primary-color);
        color: white;
        transform: scale(1.1);
        box-shadow: 0 3px 8px rgba(var(--primary-color-rgb), 0.4);
    }
    
    .step.completed .step-number {
        background: var(--success-color);
        color: white;
        box-shadow: 0 3px 8px rgba(var(--success-color-rgb), 0.4);
    }
    
    .step-title {
        font-size: 0.95rem;
        color: #666;
        transition: all 0.3s;
        font-weight: 500;
    }
    
    .step.active .step-title {
        color: var(--primary-color);
        font-weight: 600;
    }
    
    .step.completed .step-title {
        color: var(--success-color);
    }
    
    /* Button enhancements */
    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 6px;
        font-weight: 500;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .btn-primary {
        background-color: var(--primary-color);
        border: 1px solid var(--primary-color);
        color: white;
        box-shadow: 0 2px 5px rgba(var(--primary-color-rgb), 0.3);
    }
    
    .btn-primary:hover {
        background-color: var(--primary-color-dark, #0056b3);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(var(--primary-color-rgb), 0.4);
    }
    
    .btn-secondary {
        background-color: #f8f9fa;
        border: 1px solid #ddd;
        color: #444;
    }
    
    .btn-secondary:hover {
        background-color: #e9ecef;
        transform: translateY(-2px);
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    /* Modal enhancements */
    .modal-content {
        border-radius: 12px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.15);
    }
    
    .modal-header {
        border-bottom: 2px solid #f0f0f0;
        padding: 1.25rem 1.5rem;
    }
    
    .modal-header h2 {
        font-weight: 600;
    }
    
    .modal-header h2 i {
        color: var(--primary-color);
        margin-right: 10px;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get all form sections and step indicators
        const formSections = document.querySelectorAll('#studentMultiStepForm .form-section');
        const stepIndicators = document.querySelectorAll('#addStudentMultiStepModal .step');
        
        // Get navigation buttons
        const step1Next = document.getElementById('step1Next');
        const step2Prev = document.getElementById('step2Prev');
        const step2Next = document.getElementById('step2Next');
        const step3Prev = document.getElementById('step3Prev');
        const saveStudentBtn = document.getElementById('saveStudentBtn');
        
        // Get confirmation dialog elements
        const registrationConfirmationDialog = document.getElementById('registrationConfirmationDialog');
        const closeConfirmationModal = document.getElementById('closeConfirmationModal');
        const confirmationOkBtn = document.getElementById('confirmationOkBtn');
        const successMessage = document.getElementById('successMessage');
        const errorMessage = document.getElementById('errorMessage');
        const studentRegNumber = document.getElementById('studentRegNumber');
        const studentForm = document.getElementById('studentMultiStepForm');
        
        // Step 1 to Step 2
        step1Next.addEventListener('click', function() {
            if (validateStep1()) {
                goToStep(2);
            }
        });
        
        // Step 2 to Step 1
        step2Prev.addEventListener('click', function() {
            goToStep(1);
        });
        
        // Step 2 to Step 3
        step2Next.addEventListener('click', function() {
            if (validateStep2()) {
                goToStep(3);
            }
        });
        
        // Step 3 to Step 2
        step3Prev.addEventListener('click', function() {
            goToStep(2);
        });
        
        // Save Student button click
        saveStudentBtn.addEventListener('click', function() {
            if (validateStep3()) {
                // Submit the form using AJAX
                submitStudentForm();
            }
        });
        
        // Close confirmation modal
        closeConfirmationModal.addEventListener('click', function() {
            registrationConfirmationDialog.style.display = 'none';
        });
        
        // Confirmation OK button
        confirmationOkBtn.addEventListener('click', function() {
            registrationConfirmationDialog.style.display = 'none';
            // If success, close the student modal and refresh the page
            if (successMessage.style.display === 'block') {
                closeModal('addStudentMultiStepModal');
                window.location.reload();
            }
        });
        
        // Function to navigate to a specific step
        function goToStep(stepNumber) {
            // Hide all sections and remove active class from indicators
            formSections.forEach(section => section.classList.remove('active'));
            stepIndicators.forEach(indicator => indicator.classList.remove('active', 'completed'));
            
            // Show the current section and mark its indicator as active
            document.getElementById(`step${stepNumber}`).classList.add('active');
            document.getElementById(`step${stepNumber}-indicator`).classList.add('active');
            
            // Mark previous steps as completed
            for (let i = 1; i < stepNumber; i++) {
                document.getElementById(`step${i}-indicator`).classList.add('completed');
            }
        }
        
        // Validation functions
        function validateStep1() {
            let isValid = true;
            
            // Validate first name
            const firstName = document.getElementById('first_name');
            const firstNameError = document.getElementById('first_name-error');
            
            if (!firstName.value.trim()) {
                firstName.classList.add('error');
                firstNameError.classList.add('show');
                isValid = false;
            } else {
                firstName.classList.remove('error');
                firstNameError.classList.remove('show');
            }
            
            // Validate last name
            const lastName = document.getElementById('last_name');
            const lastNameError = document.getElementById('last_name-error');
            
            if (!lastName.value.trim()) {
                lastName.classList.add('error');
                lastNameError.classList.add('show');
                isValid = false;
            } else {
                lastName.classList.remove('error');
                lastNameError.classList.remove('show');
            }
            
            // Validate gender
            const gender = document.getElementById('gender');
            const genderError = document.getElementById('gender-error');
            
            if (!gender.value) {
                gender.classList.add('error');
                genderError.classList.add('show');
                isValid = false;
            } else {
                gender.classList.remove('error');
                genderError.classList.remove('show');
            }
            
            // Validate date of birth
            const dob = document.getElementById('dob');
            const dobError = document.getElementById('dob-error');
            
            if (!dob.value) {
                dob.classList.add('error');
                dobError.classList.add('show');
                isValid = false;
            } else {
                dob.classList.remove('error');
                dobError.classList.remove('show');
            }
            
            return isValid;
        }
        
        function validateStep2() {
            let isValid = true;
            
            // Validate class selection
            const classId = document.getElementById('class_id');
            const classIdError = document.getElementById('class_id-error');
            
            if (!classId.value) {
                classId.classList.add('error');
                classIdError.classList.add('show');
                isValid = false;
            } else {
                classId.classList.remove('error');
                classIdError.classList.remove('show');
            }
            
            return isValid;
        }
        
        function validateStep3() {
            let isValid = true;
            
            // Validate parent name
            const parentName = document.getElementById('parent_name');
            const parentNameError = document.getElementById('parent_name-error');
            
            if (!parentName.value.trim()) {
                parentName.classList.add('error');
                parentNameError.classList.add('show');
                isValid = false;
            } else {
                parentName.classList.remove('error');
                parentNameError.classList.remove('show');
            }
            
            // Validate parent phone
            const parentPhone = document.getElementById('parent_phone');
            const parentPhoneError = document.getElementById('parent_phone-error');
            
            if (!parentPhone.value.trim()) {
                parentPhone.classList.add('error');
                parentPhoneError.classList.add('show');
                isValid = false;
            } else {
                parentPhone.classList.remove('error');
                parentPhoneError.classList.remove('show');
            }
            
            // Validate parent email if provided
            const parentEmail = document.getElementById('parent_email');
            const parentEmailError = document.getElementById('parent_email-error');
            
            if (parentEmail.value.trim() && !isValidEmail(parentEmail.value.trim())) {
                parentEmail.classList.add('error');
                parentEmailError.classList.add('show');
                isValid = false;
            } else {
                parentEmail.classList.remove('error');
                parentEmailError.classList.remove('show');
            }
            
            return isValid;
        }
        
        // Email validation helper function
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
        
        // Function to submit the form using AJAX
        function submitStudentForm() {
            const formData = new FormData(studentForm);
            
            // Create and configure the AJAX request
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'add_student.php', true);
            
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        // Try to parse the response as JSON
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.success) {
                            // Show success message with registration number
                            successMessage.style.display = 'block';
                            errorMessage.style.display = 'none';
                            studentRegNumber.textContent = response.reg_number;
                        } else {
                            // Show error message
                            successMessage.style.display = 'none';
                            errorMessage.style.display = 'block';
                        }
                    } catch (e) {
                        // If response is not JSON, check for success message in session
                        if (xhr.responseText.includes('student_success')) {
                            // Extract registration number if possible
                            const regNumberMatch = xhr.responseText.match(/registration number: ([^<]+)/);
                            const regNumber = regNumberMatch ? regNumberMatch[1] : 'N/A';
                            
                            // Show success message
                            successMessage.style.display = 'block';
                            errorMessage.style.display = 'none';
                            studentRegNumber.textContent = regNumber;
                        } else {
                            // Show error message
                            successMessage.style.display = 'none';
                            errorMessage.style.display = 'block';
                        }
                    }
                    
                    // Show the confirmation dialog
                    registrationConfirmationDialog.style.display = 'block';
                } else {
                    // Show error message for HTTP error
                    successMessage.style.display = 'none';
                    errorMessage.style.display = 'block';
                    registrationConfirmationDialog.style.display = 'block';
                }
            };
            
            xhr.onerror = function() {
                // Show error message for network error
                successMessage.style.display = 'none';
                errorMessage.style.display = 'block';
                registrationConfirmationDialog.style.display = 'block';
            };
            
            // Send the form data
            xhr.send(formData);
        }
    });
</script>