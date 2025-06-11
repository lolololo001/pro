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
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

// Get school_id from session
$school_id = $_SESSION['school_admin_school_id'] ?? 0;
if (!$school_id) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'School ID not found in session']);
    exit;
}

// Check if type and id parameters are provided
if (!isset($_GET['type']) || !isset($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$type = $_GET['type'];
$id = intval($_GET['id']);

// Get database connection
$conn = getDbConnection();

// Based on the entity type, fetch the appropriate data and return the form HTML
switch ($type) {
    case 'department':
        getDepartmentForm($conn, $id, $school_id);
        break;
    case 'teacher':
        getTeacherForm($conn, $id, $school_id);
        break;
    case 'class':
        getClassForm($conn, $id, $school_id);
        break;
    case 'student':
        getStudentForm($conn, $id, $school_id);
        break;
    case 'parent':
        getParentForm($conn, $id, $school_id);
        break;
    case 'bursar':
        getBursarForm($conn, $id, $school_id);
        break;
    case 'payment':
        getPaymentForm($conn, $id, $school_id);
        break;
    default:
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid entity type']);
        exit;
}

$conn->close();

/**
 * Get Department Edit Form
 */
function getDepartmentForm($conn, $id, $school_id) {
    // Get department details
    $stmt = $conn->prepare("SELECT * FROM departments WHERE dep_id = ? AND school_id = ?");
    $stmt->bind_param('ii', $id, $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo '<div class="alert alert-danger">Department not found!</div>';
        return;
    }
    
    $department = $result->fetch_assoc();
    $stmt->close();
    
    // Output the form HTML
    ?>
    <form id="editForm" class="modal-form">
        <div class="form-group">
            <label for="department_name">Department Name*</label>
            <input type="text" id="department_name" name="department_name" class="form-control" 
                   value="<?php echo htmlspecialchars($department['department_name']); ?>" required>
        </div>
        
        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="closeModal('editFormModal')">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update Department
            </button>
        </div>
    </form>
    <?php
}

/**
 * Get Teacher Edit Form
 */
function getTeacherForm($conn, $id, $school_id) {
    // Get teacher details
    $stmt = $conn->prepare("SELECT * FROM teachers WHERE id = ? AND school_id = ?");
    $stmt->bind_param('ii', $id, $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo '<div class="alert alert-danger">Teacher not found!</div>';
        return;
    }
    
    $teacher = $result->fetch_assoc();
    $stmt->close();
    
    // Get all departments for dropdown
    $departments = [];
    $dept_stmt = $conn->prepare("SELECT dep_id, department_name FROM departments WHERE school_id = ? ORDER BY department_name ASC");
    $dept_stmt->bind_param('i', $school_id);
    $dept_stmt->execute();
    $dept_result = $dept_stmt->get_result();
    
    while ($row = $dept_result->fetch_assoc()) {
        $departments[] = $row;
    }
    $dept_stmt->close();
    
    // Output the form HTML
    ?>
    <form id="editForm" class="modal-form">
        <div class="form-section">
            <h3 class="section-title"><i class="fas fa-id-card"></i> Teacher Information</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="name">Full Name*</label>
                    <input type="text" id="name" name="name" class="form-control" 
                           value="<?php echo htmlspecialchars($teacher['name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email*</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo htmlspecialchars($teacher['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="tel" id="phone" name="phone" class="form-control" 
                           value="<?php echo htmlspecialchars($teacher['phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <input type="text" id="subject" name="subject" class="form-control" 
                           value="<?php echo htmlspecialchars($teacher['subject'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="qualification">Qualification</label>
                    <input type="text" id="qualification" name="qualification" class="form-control" 
                           value="<?php echo htmlspecialchars($teacher['qualification'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="department_id">Department</label>
                    <select id="department_id" name="department_id" class="form-control">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?php echo $department['dep_id']; ?>" 
                                <?php echo ($teacher['department_id'] == $department['dep_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($department['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="closeModal('editFormModal')">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update Teacher
            </button>
        </div>
    </form>
    <?php
}

/**
 * Get Class Edit Form
 */
function getClassForm($conn, $id, $school_id) {
    // Get class details
    $stmt = $conn->prepare("SELECT * FROM classes WHERE id = ? AND school_id = ?");
    $stmt->bind_param('ii', $id, $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo '<div class="alert alert-danger">Class not found!</div>';
        return;
    }
    
    $class = $result->fetch_assoc();
    $stmt->close();
    
    // Get all teachers for dropdown
    $teachers = [];
    $teacher_stmt = $conn->prepare("SELECT id, name FROM teachers WHERE school_id = ? ORDER BY name ASC");
    $teacher_stmt->bind_param('i', $school_id);
    $teacher_stmt->execute();
    $teacher_result = $teacher_stmt->get_result();
    
    while ($row = $teacher_result->fetch_assoc()) {
        $teachers[] = $row;
    }
    $teacher_stmt->close();
    
    // Output the form HTML
    ?>
    <form id="editForm" class="modal-form">
        <div class="form-grid">
            <div class="form-group">
                <label for="class_name">Class Name*</label>
                <input type="text" id="class_name" name="class_name" class="form-control" 
                       value="<?php echo htmlspecialchars($class['class_name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="grade_level">Grade Level*</label>
                <select id="grade_level" name="grade_level" class="form-control" required>
                    <option value="">Select Grade Level</option>
                    <option value="Ordinary Level" <?php echo ($class['grade_level'] == 'Ordinary Level') ? 'selected' : ''; ?>>Ordinary Level</option>
                    <option value="Advanced Level" <?php echo ($class['grade_level'] == 'Advanced Level') ? 'selected' : ''; ?>>Advanced Level</option>
                </select>
            </div>
            <div class="form-group">
                <label for="teacher_id">Class Teacher</label>
                <select id="teacher_id" name="teacher_id" class="form-control">
                    <option value="">Select Teacher</option>
                    <?php foreach ($teachers as $teacher): ?>
                        <option value="<?php echo $teacher['id']; ?>" 
                            <?php echo ($class['teacher_id'] == $teacher['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($teacher['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="closeClassEditModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update Class
            </button>
        </div>
    </form>
    <?php
}

/**
 * Get Student Edit Form
 */
function getStudentForm($conn, $id, $school_id) {
    // Get student details
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ? AND school_id = ?");
    $stmt->bind_param('ii', $id, $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo '<div class="alert alert-danger">Student not found!</div>';
        return;
    }
    
    $student = $result->fetch_assoc();
    $stmt->close();
    
    // Get all departments for dropdown
    $departments = [];
    $dept_stmt = $conn->prepare("SELECT dep_id, department_name FROM departments WHERE school_id = ? ORDER BY department_name ASC");
    $dept_stmt->bind_param('i', $school_id);
    $dept_stmt->execute();
    $dept_result = $dept_stmt->get_result();
    
    while ($row = $dept_result->fetch_assoc()) {
        $departments[] = $row;
    }
    $dept_stmt->close();
    
    // Get all classes for dropdown
    $classes = [];
    $class_stmt = $conn->prepare("SELECT DISTINCT class FROM students WHERE school_id = ? ORDER BY class ASC");
    $class_stmt->bind_param('i', $school_id);
    $class_stmt->execute();
    $class_result = $class_stmt->get_result();
    
    while ($row = $class_result->fetch_assoc()) {
        $classes[] = $row['class'];
    }
    $class_stmt->close();
    
    // Output the form HTML
    ?>
    <form id="editForm" class="modal-form">
        <div class="form-section">
            <h3 class="section-title"><i class="fas fa-id-card"></i> Student Information</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="first_name">First Name*</label>
                    <input type="text" id="first_name" name="first_name" class="form-control" 
                           value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name*</label>
                    <input type="text" id="last_name" name="last_name" class="form-control" 
                           value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="admission_number">Admission Number*</label>
                    <input type="text" id="admission_number" name="admission_number" class="form-control" 
                           value="<?php echo htmlspecialchars($student['admission_number'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="reg_number">Registration Number</label>
                    <input type="text" id="reg_number" name="reg_number" class="form-control" 
                           value="<?php echo htmlspecialchars($student['reg_number'] ?? 'Auto-generated'); ?>" readonly>
                    <small class="form-text text-muted">Automatically generated based on year and student ID</small>
                </div>
                <div class="form-group">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender" class="form-control">
                        <option value="">Select Gender</option>
                        <option value="Male" <?php echo ($student['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($student['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo ($student['gender'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="dob">Date of Birth</label>
                    <input type="date" id="dob" name="dob" class="form-control" 
                           value="<?php echo htmlspecialchars($student['dob'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="admission_date">Admission Date</label>
                    <input type="date" id="admission_date" name="admission_date" class="form-control" 
                           value="<?php echo htmlspecialchars($student['admission_date'] ?? ''); ?>">
                </div>
            </div>
        </div>
        
        <div class="form-section">
            <h3 class="section-title"><i class="fas fa-school"></i> Academic Information</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="class">Class*</label>
                    <input type="text" id="class" name="class" class="form-control" list="class-list" 
                           value="<?php echo htmlspecialchars($student['class']); ?>" required>
                    <datalist id="class-list">
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo htmlspecialchars($class); ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group">
                    <label for="department_id">Department</label>
                    <select id="department_id" name="department_id" class="form-control">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?php echo $department['dep_id']; ?>" 
                                <?php echo ($student['department_id'] == $department['dep_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($department['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="active" <?php echo ($student['status'] == 'active' || empty($student['status'])) ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($student['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        <option value="graduated" <?php echo ($student['status'] == 'graduated') ? 'selected' : ''; ?>>Graduated</option>
                        <option value="shifted" <?php echo ($student['status'] == 'shifted') ? 'selected' : ''; ?>>Shifted</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="form-section">
            <h3 class="section-title"><i class="fas fa-users"></i> Parent/Guardian Information</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label for="parent_name">Parent/Guardian Name*</label>
                    <input type="text" id="parent_name" name="parent_name" class="form-control" 
                           value="<?php echo htmlspecialchars($student['parent_name'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="parent_phone">Parent/Guardian Phone*</label>
                    <input type="tel" id="parent_phone" name="parent_phone" class="form-control" 
                           value="<?php echo htmlspecialchars($student['parent_phone'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="parent_email">Parent/Guardian Email</label>
                    <input type="email" id="parent_email" name="parent_email" class="form-control" 
                           value="<?php echo htmlspecialchars($student['parent_email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="closeModal('editFormModal')">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update Student
            </button>
        </div>
    </form>
    <?php
}

/**
 * Get Parent Edit Form
 */
function getParentForm($conn, $id, $school_id) {
    // Get parent details
    $stmt = $conn->prepare("SELECT * FROM parents WHERE id = ? AND school_id = ?");
    $stmt->bind_param('ii', $id, $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo '<div class="alert alert-danger">Parent not found!</div>';
        return;
    }
    
    $parent = $result->fetch_assoc();
    $stmt->close();
    
    // Output the form HTML
    ?>
    <form id="editForm" class="modal-form">
        <div class="form-grid">
            <div class="form-group">
                <label for="name">Full Name*</label>
                <input type="text" id="name" name="name" class="form-control" 
                       value="<?php echo htmlspecialchars($parent['name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email*</label>
                <input type="email" id="email" name="email" class="form-control" 
                       value="<?php echo htmlspecialchars($parent['email']); ?>" required>
            </div>
            <div class="form-group">
                <label for="phone">Phone*</label>
                <input type="tel" id="phone" name="phone" class="form-control" 
                       value="<?php echo htmlspecialchars($parent['phone']); ?>" required>
            </div>
            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" class="form-control" rows="3"><?php echo htmlspecialchars($parent['address'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="occupation">Occupation</label>
                <input type="text" id="occupation" name="occupation" class="form-control" 
                       value="<?php echo htmlspecialchars($parent['occupation'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="closeModal('editFormModal')">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update Parent
            </button>
        </div>
    </form>
    <?php
}

/**
 * Get Bursar Edit Form
 */
function getBursarForm($conn, $id, $school_id) {
    // Get bursar details
    $stmt = $conn->prepare("SELECT * FROM bursars WHERE id = ? AND school_id = ?");
    $stmt->bind_param('ii', $id, $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo '<div class="alert alert-danger">Bursar not found!</div>';
        return;
    }
    
    $bursar = $result->fetch_assoc();
    $stmt->close();
    
    // Output the form HTML
    ?>
    <form id="editForm" class="modal-form">
        <div class="form-grid">
            <div class="form-group">
                <label for="name">Full Name*</label>
                <input type="text" id="name" name="name" class="form-control" 
                       value="<?php echo htmlspecialchars($bursar['name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="email">Email*</label>
                <input type="email" id="email" name="email" class="form-control" 
                       value="<?php echo htmlspecialchars($bursar['email']); ?>" required>
            </div>
            <div class="form-group">
                <label for="phone">Phone*</label>
                <input type="tel" id="phone" name="phone" class="form-control" 
                       value="<?php echo htmlspecialchars($bursar['phone']); ?>" required>
            </div>
            <div class="form-group">
                <label for="position">Position</label>
                <input type="text" id="position" name="position" class="form-control" 
                       value="<?php echo htmlspecialchars($bursar['position'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="closeModal('editFormModal')">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update Bursar
            </button>
        </div>
    </form>
    <?php
}

/**
 * Get Payment Edit Form
 */
function getPaymentForm($conn, $id, $school_id) {
    // Get payment details
    $stmt = $conn->prepare("SELECT * FROM payments WHERE id = ? AND school_id = ?");
    $stmt->bind_param('ii', $id, $school_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo '<div class="alert alert-danger">Payment not found!</div>';
        return;
    }
    
    $payment = $result->fetch_assoc();
    $stmt->close();
    
    // Get all students for dropdown
    $students = [];
    $student_stmt = $conn->prepare("SELECT id, first_name, last_name, admission_number FROM students WHERE school_id = ? ORDER BY first_name ASC");
    $student_stmt->bind_param('i', $school_id);
    $student_stmt->execute();
    $student_result = $student_stmt->get_result();
    
    while ($row = $student_result->fetch_assoc()) {
        $students[] = $row;
    }
    $student_stmt->close();
    
    // Output the form HTML
    ?>
    <form id="editForm" class="modal-form">
        <div class="form-grid">
            <div class="form-group">
                <label for="student_id">Student*</label>
                <select id="student_id" name="student_id" class="form-control" required>
                    <option value="">Select Student</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?php echo $student['id']; ?>" 
                            <?php echo ($payment['student_id'] == $student['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['admission_number'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="payment_type">Payment Type*</label>
                <select id="payment_type" name="payment_type" class="form-control" required>
                    <option value="">Select Payment Type</option>
                    <option value="Tuition" <?php echo ($payment['payment_type'] == 'Tuition') ? 'selected' : ''; ?>>Tuition</option>
                    <option value="Books" <?php echo ($payment['payment_type'] == 'Books') ? 'selected' : ''; ?>>Books</option>
                    <option value="Uniform" <?php echo ($payment['payment_type'] == 'Uniform') ? 'selected' : ''; ?>>Uniform</option>
                    <option value="Transportation" <?php echo ($payment['payment_type'] == 'Transportation') ? 'selected' : ''; ?>>Transportation</option>
                    <option value="Other" <?php echo ($payment['payment_type'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="form-group">
                <label for="amount">Amount*</label>
                <input type="number" id="amount" name="amount" class="form-control" step="0.01" min="0" 
                       value="<?php echo htmlspecialchars($payment['amount']); ?>" required>
            </div>
            <div class="form-group">
                <label for="payment_date">Payment Date*</label>
                <input type="date" id="payment_date" name="payment_date" class="form-control" 
                       value="<?php echo htmlspecialchars($payment['payment_date']); ?>" required>
            </div>
            <div class="form-group">
                <label for="payment_method">Payment Method</label>
                <select id="payment_method" name="payment_method" class="form-control">
                    <option value="">Select Payment Method</option>
                    <option value="Cash" <?php echo ($payment['payment_method'] == 'Cash') ? 'selected' : ''; ?>>Cash</option>
                    <option value="Bank Transfer" <?php echo ($payment['payment_method'] == 'Bank Transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                    <option value="Check" <?php echo ($payment['payment_method'] == 'Check') ? 'selected' : ''; ?>>Check</option>
                    <option value="Online Payment" <?php echo ($payment['payment_method'] == 'Online Payment') ? 'selected' : ''; ?>>Online Payment</option>
                    <option value="Other" <?php echo ($payment['payment_method'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="3"><?php echo htmlspecialchars($payment['description'] ?? ''); ?></textarea>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="button" class="btn btn-secondary" onclick="closeModal('editFormModal')">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Update Payment
            </button>
        </div>
    </form>
    <?php
}