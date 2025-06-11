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

<!-- Enhanced Student Registration Modal -->
<div id="addStudentMultiStepModal" class="modal enhanced-modal">
    <div class="modal-content enhanced-modal-content">
        <div class="modal-header enhanced-modal-header">
            <h2><i class="fas fa-user-graduate"></i> Student Registration</h2>
            <button class="close-modal enhanced-close" onclick="closeModal('addStudentMultiStepModal')" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="modal-body enhanced-modal-body">
            <?php if (isset($_SESSION['student_error'])): ?>
                <div class="form-alert form-alert-danger enhanced-alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo $_SESSION['student_error']; unset($_SESSION['student_error']); ?></span>
                </div>
            <?php endif; ?>

            <!-- Enhanced Form Steps Indicator -->
            <div class="form-steps enhanced-steps">
                <div class="step-progress-bar">
                    <div class="progress-line" id="progress-line"></div>
                </div>
                <div class="step active completed" id="modal-step1-indicator">
                    <div class="step-circle">
                        <div class="step-number">1</div>
                        <div class="step-check"><i class="fas fa-check"></i></div>
                    </div>
                    <div class="step-content">
                        <div class="step-title">Personal Info</div>
                        <div class="step-subtitle">Basic student details</div>
                    </div>
                </div>
                <div class="step" id="modal-step2-indicator">
                    <div class="step-circle">
                        <div class="step-number">2</div>
                        <div class="step-check"><i class="fas fa-check"></i></div>
                    </div>
                    <div class="step-content">
                        <div class="step-title">Academic Info</div>
                        <div class="step-subtitle">Class and department</div>
                    </div>
                </div>
                <div class="step" id="modal-step3-indicator">
                    <div class="step-circle">
                        <div class="step-number">3</div>
                        <div class="step-check"><i class="fas fa-check"></i></div>
                    </div>
                    <div class="step-content">
                        <div class="step-title">Guardian Info</div>
                        <div class="step-subtitle">Parent/guardian details</div>
                    </div>
                </div>
            </div>
            
            <form id="modalStudentForm" action="add_student.php" method="post">
                <!-- Ensure redirect to dashboard after successful registration -->
                <input type="hidden" name="redirect_to" value="dashboard.php">
                
                <!-- Enhanced Step 1: Student Information -->
                <div class="form-section enhanced-form-section active" id="modal-step1">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="section-info">
                            <h3 class="section-title">Personal Information</h3>
                            <p class="section-description">Enter the student's basic personal details</p>
                        </div>
                    </div>

                    <div class="form-grid enhanced-form-grid">
                        <div class="form-group enhanced-form-group">
                            <label for="modal_first_name" class="enhanced-label">
                                <i class="fas fa-user"></i>
                                First Name <span class="required-asterisk">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="modal_first_name" name="first_name" class="form-control enhanced-input"
                                       placeholder="Enter student's first name" required autocomplete="given-name">
                                <div class="input-icon">
                                    <i class="fas fa-user"></i>
                                </div>
                            </div>
                            <div class="error-message" id="modal_first_name-error">Please enter the first name</div>
                        </div>

                        <div class="form-group enhanced-form-group">
                            <label for="modal_last_name" class="enhanced-label">
                                <i class="fas fa-user"></i>
                                Last Name <span class="required-asterisk">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="modal_last_name" name="last_name" class="form-control enhanced-input"
                                       placeholder="Enter student's last name" required autocomplete="family-name">
                                <div class="input-icon">
                                    <i class="fas fa-user"></i>
                                </div>
                            </div>
                            <div class="error-message" id="modal_last_name-error">Please enter the last name</div>
                        </div>

                        <div class="form-group enhanced-form-group">
                            <label for="modal_gender" class="enhanced-label">
                                <i class="fas fa-venus-mars"></i>
                                Gender <span class="required-asterisk">*</span>
                            </label>
                            <div class="select-wrapper">
                                <select id="modal_gender" name="gender" class="form-control enhanced-select" required>
                                    <option value="">Choose gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                                <div class="select-icon">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="error-message" id="modal_gender-error">Please select a gender</div>
                        </div>

                        <div class="form-group enhanced-form-group">
                            <label for="modal_dob" class="enhanced-label">
                                <i class="fas fa-calendar-alt"></i>
                                Date of Birth <span class="required-asterisk">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="date" id="modal_dob" name="dob" class="form-control enhanced-input" required>
                                <div class="input-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                            </div>
                            <div class="error-message" id="modal_dob-error">Date of birth is required</div>
                        </div>
                    </div>

                    <div class="form-actions enhanced-form-actions">
                        <button type="button" class="btn btn-secondary enhanced-btn-secondary" onclick="closeModal('addStudentMultiStepModal')">
                            <i class="fas fa-times"></i>
                            <span>Cancel</span>
                        </button>
                        <button type="button" class="btn btn-primary enhanced-btn-primary" id="modalStep1Next">
                            <span>Continue</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Enhanced Step 2: Academic Information -->
                <div class="form-section enhanced-form-section" id="modal-step2">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <div class="section-info">
                            <h3 class="section-title">Academic Information</h3>
                            <p class="section-description">Select the student's class and academic department</p>
                        </div>
                    </div>

                    <div class="form-grid enhanced-form-grid">
                        <div class="form-group enhanced-form-group full-width">
                            <label for="modal_class_id" class="enhanced-label">
                                <i class="fas fa-school"></i>
                                Class <span class="required-asterisk">*</span>
                            </label>
                            <div class="select-wrapper">
                                <select id="modal_class_id" name="class_id" class="form-control enhanced-select" required>
                                    <option value="">Choose a class</option>
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>">
                                            <?php echo htmlspecialchars($class['grade_level'] . ' - ' . $class['class_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="select-icon">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="error-message" id="modal_class_id-error">Please select a class</div>
                        </div>

                        <div class="form-group enhanced-form-group full-width">
                            <label for="modal_department_id" class="enhanced-label">
                                <i class="fas fa-building"></i>
                                Department <span class="optional-text">(Optional)</span>
                            </label>
                            <div class="select-wrapper">
                                <select id="modal_department_id" name="department_id" class="form-control enhanced-select">
                                    <option value="">Choose a department</option>
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?php echo $department['dep_id']; ?>"><?php echo htmlspecialchars($department['department_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="select-icon">
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                            <div class="error-message" id="modal_department_id-error">Please select a department</div>
                        </div>
                    </div>

                    <div class="form-actions enhanced-form-actions">
                        <button type="button" class="btn btn-secondary enhanced-btn-secondary" id="modalStep2Prev">
                            <i class="fas fa-arrow-left"></i>
                            <span>Previous</span>
                        </button>
                        <button type="button" class="btn btn-primary enhanced-btn-primary" id="modalStep2Next">
                            <span>Continue</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Enhanced Step 3: Parent/Guardian Information -->
                <div class="form-section enhanced-form-section" id="modal-step3">
                    <div class="section-header">
                        <div class="section-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="section-info">
                            <h3 class="section-title">Guardian Information</h3>
                            <p class="section-description">Enter parent or guardian contact details</p>
                        </div>
                    </div>

                    <div class="form-grid enhanced-form-grid">
                        <div class="form-group enhanced-form-group full-width">
                            <label for="modal_parent_name" class="enhanced-label">
                                <i class="fas fa-user-friends"></i>
                                Parent/Guardian Name <span class="required-asterisk">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="text" id="modal_parent_name" name="parent_name" class="form-control enhanced-input"
                                       placeholder="Enter parent or guardian's full name" required autocomplete="name">
                                <div class="input-icon">
                                    <i class="fas fa-user-friends"></i>
                                </div>
                            </div>
                            <div class="error-message" id="modal_parent_name-error">Please enter the parent/guardian name</div>
                        </div>

                        <div class="form-group enhanced-form-group">
                            <label for="modal_parent_phone" class="enhanced-label">
                                <i class="fas fa-phone"></i>
                                Phone Number <span class="required-asterisk">*</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="tel" id="modal_parent_phone" name="parent_phone" class="form-control enhanced-input"
                                       placeholder="Enter phone number" required autocomplete="tel">
                                <div class="input-icon">
                                    <i class="fas fa-phone"></i>
                                </div>
                            </div>
                            <div class="error-message" id="modal_parent_phone-error">Please enter the parent/guardian phone</div>
                        </div>

                        <div class="form-group enhanced-form-group">
                            <label for="modal_parent_email" class="enhanced-label">
                                <i class="fas fa-envelope"></i>
                                Email Address <span class="optional-text">(Optional)</span>
                            </label>
                            <div class="input-wrapper">
                                <input type="email" id="modal_parent_email" name="parent_email" class="form-control enhanced-input"
                                       placeholder="Enter email address" autocomplete="email">
                                <div class="input-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                            </div>
                            <div class="error-message" id="modal_parent_email-error">Please enter a valid email address</div>
                        </div>

                        <div class="form-group enhanced-form-group full-width">
                            <label for="modal_address" class="enhanced-label">
                                <i class="fas fa-map-marker-alt"></i>
                                Home Address <span class="optional-text">(Optional)</span>
                            </label>
                            <div class="textarea-wrapper">
                                <textarea id="modal_address" name="address" class="form-control enhanced-textarea"
                                          rows="3" placeholder="Enter home address"></textarea>
                                <div class="textarea-icon">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions enhanced-form-actions final-step">
                        <button type="button" class="btn btn-secondary enhanced-btn-secondary" id="modalStep3Prev">
                            <i class="fas fa-arrow-left"></i>
                            <span>Previous</span>
                        </button>
                        <button type="submit" class="btn btn-primary" id="modalSubmitBtn">
                            <i class="fas fa-plus-circle"></i> Save Student
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Confirmation Dialog Removed -->
        </div>
    </div>
</div>

<!-- The multi-step form functionality is now handled by multi-step-form.js -->