<?php
// Start session
session_start();

// Show all PHP errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load config
require_once '../config/config.php';

// Check if school admin is logged in
if (!isset($_SESSION['school_admin_id'])) {
    header('Location: ../login.php');
    exit;
}

// Get school_id from session
$school_id = $_SESSION['school_admin_school_id'] ?? 0;
if (!$school_id) {
    die("Error: School ID not found in session. Please log in again.");
}

// Get database connection
$conn = getDbConnection();

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

// Get school info
$school_info = [];
try {
    $stmt = $conn->prepare('SELECT name, logo, address, phone, email FROM schools WHERE id = ?');
    $stmt->bind_param('i', $school_id);
    $stmt->execute();
    $school_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching school info: " . $e->getMessage());
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student - <?php echo htmlspecialchars($school_info['name'] ?? 'School'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="form-styles.css">
    <link rel="stylesheet" href="enhanced-features.css">
    <style>
        /* Multi-step form styles */
        .form-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .form-steps::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--border-color);
            transform: translateY(-50%);
            z-index: 1;
        }
        
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            background: white;
            padding: 0 1rem;
        }
        
        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--gray-color);
            color: var(--dark-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 0.5rem;
            border: 2px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .step-title {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--dark-color);
            text-align: center;
        }
        
        .step.active .step-number {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .step.completed .step-number {
            background-color: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }
        
        .form-section {
            display: none;
        }
        
        .form-section.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 2rem;
        }
        
        /* Confirmation modal styles */
        .confirmation-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .confirmation-modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            animation: modalFadeIn 0.3s;
        }
        
        .confirmation-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }
        
        .confirmation-modal-header h3 {
            margin: 0;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .confirmation-modal-body {
            margin-bottom: 1.5rem;
        }
        
        .confirmation-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        
        .close-modal {
            color: #aaa;
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close-modal:hover {
            color: var(--dark-color);
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-50px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Form validation styles */
        .form-control.error {
            border-color: var(--danger-color);
        }
        
        .error-message {
            color: var(--danger-color);
            font-size: 0.8rem;
            margin-top: 0.3rem;
            display: none;
        }
        
        .error-message.show {
            display: block;
        }
        
        /* Section title styles */
        .section-title {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-user-plus"></i> Add New Student</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a>
                <span>/</span>
                <a href="students.php">Students</a>
                <span>/</span>
                <span>Add Student</span>
            </div>
        </div>
        
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['student_error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php 
                echo $_SESSION['student_error']; 
                unset($_SESSION['student_error']);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Add Student Card -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-user-plus"></i> Student Registration Form</h2>
            </div>
            <div class="card-body">
                <!-- Form Steps Indicator -->
                <div class="form-steps">
                    <div class="step active" id="step1-indicator">
                        <div class="step-number">1</div>
                        <div class="step-title">Student Information</div>
                    </div>
                    <div class="step" id="step2-indicator">
                        <div class="step-number">2</div>
                        <div class="step-title">Academic Information</div>
                    </div>
                    <div class="step" id="step3-indicator">
                        <div class="step-number">3</div>
                        <div class="step-title">Parent/Guardian Information</div>
                    </div>
                </div>
                
                <form id="studentForm" action="add_student.php" method="post">
                    <!-- Ensure redirect to students.php after successful registration -->
                    <input type="hidden" name="redirect_to" value="students.php">
                    <!-- Step 1: Student Information -->
                    <div class="form-section active" id="step1">
                        <h3 class="section-title"><i class="fas fa-user"></i> Student Personal Information</h3>
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
                                <label for="gender">Gender</label>
                                <select id="gender" name="gender" class="form-control">
                                    <option value="">Select Gender</option>
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="dob">Date of Birth</label>
                                <input type="date" id="dob" name="dob" class="form-control">
                            </div>
                        </div>
                        
                        <div class="form-navigation">
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='students.php'">
                                <i class="fas fa-arrow-left"></i> Cancel
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
                                    <?php foreach ($classes as $class): ?>
                                        <option value="<?php echo $class['id']; ?>">
                                            <?php echo htmlspecialchars($class['grade_level'] . ' - ' . $class['class_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
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
                            <button type="button" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-save"></i> Save Student
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <!-- Confirmation Modal Removed -->
    
    <footer>
        <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($school_info['name'] ?? 'School Admin System'); ?>. All rights reserved.</p>
    </footer>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get all form sections and step indicators
            const formSections = document.querySelectorAll('.form-section');
            const stepIndicators = document.querySelectorAll('.step');
            
            // Get navigation buttons
            const step1Next = document.getElementById('step1Next');
            const step2Prev = document.getElementById('step2Prev');
            const step2Next = document.getElementById('step2Next');
            const step3Prev = document.getElementById('step3Prev');
            const submitBtn = document.getElementById('submitBtn');
            
            // Get confirmation modal elements
            const confirmationModal = document.getElementById('confirmationModal');
            const closeModal = document.getElementById('closeModal');
            const cancelBtn = document.getElementById('cancelBtn');
            const confirmBtn = document.getElementById('confirmBtn');
            const studentForm = document.getElementById('studentForm');
            
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
            
            // Submit button click - directly submit the form without confirmation
            submitBtn.addEventListener('click', function() {
                if (validateStep3()) {
                    // Submit the form directly without confirmation
                    studentForm.submit();
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
        });
    </script>
</body>
</html>