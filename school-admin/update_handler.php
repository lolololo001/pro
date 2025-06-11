<?php
/**
 * Universal Update Handler
 * Handles AJAX update requests for all entity types
 */

require_once '../config/config.php';

// Start session and check authentication
session_start();
if (!isset($_SESSION['school_admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$school_id = $_SESSION['school_admin_school_id'] ?? 0;
$admin_id = $_SESSION['school_admin_id'];

// Get request method and entity type
$request_method = $_SERVER['REQUEST_METHOD'];
if ($request_method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get entity type from URL or POST data
$entity_type = $_GET['type'] ?? $_POST['type'] ?? '';
$entity_id = $_POST['id'] ?? $_POST[$entity_type . '_id'] ?? 0;

if (empty($entity_type) || empty($entity_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing entity type or ID']);
    exit;
}

try {
    $conn = getDbConnection();
    $success = false;
    $message = '';
    
    switch ($entity_type) {
        case 'student':
            $success = updateStudent($conn, $entity_id, $school_id, $_POST);
            $message = $success ? 'Student updated successfully' : 'Failed to update student';
            break;
            
        case 'teacher':
            $success = updateTeacher($conn, $entity_id, $school_id, $_POST);
            $message = $success ? 'Teacher updated successfully' : 'Failed to update teacher';
            break;
            
        case 'parent':
            $success = updateParent($conn, $entity_id, $school_id, $_POST);
            $message = $success ? 'Parent updated successfully' : 'Failed to update parent';
            break;
            
        case 'class':
            $success = updateClass($conn, $entity_id, $school_id, $_POST);
            $message = $success ? 'Class updated successfully' : 'Failed to update class';
            break;
            
        case 'department':
            $success = updateDepartment($conn, $entity_id, $school_id, $_POST);
            $message = $success ? 'Department updated successfully' : 'Failed to update department';
            break;
            
        case 'bursar':
            $success = updateBursar($conn, $entity_id, $school_id, $_POST);
            $message = $success ? 'Bursar updated successfully' : 'Failed to update bursar';
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown entity type: ' . $entity_type]);
            exit;
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'entity_type' => $entity_type,
        'entity_id' => $entity_id
    ]);
    
} catch (Exception $e) {
    error_log("Update error for {$entity_type} ID {$entity_id}: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating the ' . $entity_type . ': ' . $e->getMessage()
    ]);
}

/**
 * Update student information
 */
function updateStudent($conn, $student_id, $school_id, $data) {
    try {
        $conn->begin_transaction();
        
        // Verify student belongs to this school
        $stmt = $conn->prepare("SELECT id FROM students WHERE id = ? AND school_id = ?");
        $stmt->bind_param('ii', $student_id, $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Student not found or access denied");
        }
        $stmt->close();
        
        // Prepare update fields
        $updateFields = [];
        $updateValues = [];
        $types = '';
        
        // Basic information fields
        $fields = [
            'first_name' => 's',
            'last_name' => 's',
            'email' => 's',
            'phone' => 's',
            'dob' => 's',
            'date_of_birth' => 's',
            'gender' => 's',
            'admission_number' => 's',
            'registration_number' => 's',
            'class_name' => 's',
            'class' => 's',
            'grade_level' => 's',
            'grade' => 's',
            'department_id' => 'i',
            'parent_name' => 's',
            'parent_phone' => 's',
            'parent_email' => 's',
            'address' => 's'
        ];
        
        foreach ($fields as $field => $type) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $updateFields[] = "{$field} = ?";
                $updateValues[] = $data[$field];
                $types .= $type;
            }
        }
        
        if (empty($updateFields)) {
            throw new Exception("No fields to update");
        }
        
        // Add school_id and student_id to the end
        $updateValues[] = $student_id;
        $updateValues[] = $school_id;
        $types .= 'ii';
        
        // Build and execute update query
        $sql = "UPDATE students SET " . implode(', ', $updateFields) . " WHERE id = ? AND school_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$updateValues);
        $success = $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        return $success;
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * Update teacher information
 */
function updateTeacher($conn, $teacher_id, $school_id, $data) {
    try {
        $conn->begin_transaction();
        
        // Verify teacher belongs to this school
        $stmt = $conn->prepare("SELECT id FROM teachers WHERE id = ? AND school_id = ?");
        $stmt->bind_param('ii', $teacher_id, $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Teacher not found or access denied");
        }
        $stmt->close();
        
        // Prepare update fields
        $updateFields = [];
        $updateValues = [];
        $types = '';
        
        // Teacher fields
        $fields = [
            'name' => 's',
            'first_name' => 's',
            'last_name' => 's',
            'email' => 's',
            'phone' => 's',
            'subject' => 's',
            'qualification' => 's',
            'department_id' => 'i'
        ];
        
        foreach ($fields as $field => $type) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $updateFields[] = "{$field} = ?";
                $updateValues[] = $data[$field];
                $types .= $type;
            }
        }
        
        if (empty($updateFields)) {
            throw new Exception("No fields to update");
        }
        
        // Add teacher_id and school_id to the end
        $updateValues[] = $teacher_id;
        $updateValues[] = $school_id;
        $types .= 'ii';
        
        // Build and execute update query
        $sql = "UPDATE teachers SET " . implode(', ', $updateFields) . " WHERE id = ? AND school_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$updateValues);
        $success = $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        return $success;
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * Update parent information
 */
function updateParent($conn, $parent_id, $school_id, $data) {
    try {
        $conn->begin_transaction();
        
        // Verify parent belongs to this school
        $stmt = $conn->prepare("SELECT id FROM parents WHERE id = ? AND school_id = ?");
        $stmt->bind_param('ii', $parent_id, $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Parent not found or access denied");
        }
        $stmt->close();
        
        // Prepare update fields
        $updateFields = [];
        $updateValues = [];
        $types = '';
        
        // Parent fields
        $fields = [
            'name' => 's',
            'email' => 's',
            'phone' => 's',
            'address' => 's',
            'occupation' => 's'
        ];
        
        foreach ($fields as $field => $type) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $updateFields[] = "{$field} = ?";
                $updateValues[] = $data[$field];
                $types .= $type;
            }
        }
        
        if (empty($updateFields)) {
            throw new Exception("No fields to update");
        }
        
        // Add parent_id and school_id to the end
        $updateValues[] = $parent_id;
        $updateValues[] = $school_id;
        $types .= 'ii';
        
        // Build and execute update query
        $sql = "UPDATE parents SET " . implode(', ', $updateFields) . " WHERE id = ? AND school_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$updateValues);
        $success = $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        return $success;
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

/**
 * Update class information
 */
function updateClass($conn, $class_id, $school_id, $data) {
    // Implementation for class updates
    return true; // Placeholder
}

/**
 * Update department information
 */
function updateDepartment($conn, $department_id, $school_id, $data) {
    // Implementation for department updates
    return true; // Placeholder
}

/**
 * Update bursar information
 */
function updateBursar($conn, $bursar_id, $school_id, $data) {
    // Implementation for bursar updates
    return true; // Placeholder
}
?>
