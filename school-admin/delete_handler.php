<?php
/**
 * Universal Delete Handler
 * Handles AJAX delete requests for all entity types
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
            $success = deleteStudent($conn, $entity_id, $school_id);
            $message = $success ? 'Student deleted successfully' : 'Failed to delete student';
            break;
            
        case 'teacher':
            $success = deleteTeacher($conn, $entity_id, $school_id);
            $message = $success ? 'Teacher deleted successfully' : 'Failed to delete teacher';
            break;
            
        case 'parent':
            $success = deleteParent($conn, $entity_id, $school_id);
            $message = $success ? 'Parent deleted successfully' : 'Failed to delete parent';
            break;
            
        case 'class':
            $success = deleteClass($conn, $entity_id, $school_id);
            $message = $success ? 'Class deleted successfully' : 'Failed to delete class';
            break;
            
        case 'department':
            $success = deleteDepartment($conn, $entity_id, $school_id);
            $message = $success ? 'Department deleted successfully' : 'Failed to delete department';
            break;
            
        case 'bursar':
            $success = deleteBursar($conn, $entity_id, $school_id);
            $message = $success ? 'Bursar deleted successfully' : 'Failed to delete bursar';
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
    error_log("Delete error for {$entity_type} ID {$entity_id}: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while deleting the ' . $entity_type . ': ' . $e->getMessage()
    ]);
}

/**
 * Delete student and related data
 */
function deleteStudent($conn, $student_id, $school_id) {
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
        
        // Delete related records first
        $tables = [
            'student_parent' => 'student_id',
            'enrollments' => 'student_id',
            'grades' => 'student_id',
            'attendance' => 'student_id',
            'fees' => 'student_id'
        ];
        
        foreach ($tables as $table => $column) {
            // Check if table exists
            $result = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
            if ($result && $result->num_rows > 0) {
                // Safe because $table and $column are from a hardcoded array
                $sql = "DELETE FROM `{$table}` WHERE `{$column}` = " . intval($student_id);
                $conn->query($sql);
            }
        }
        
        // Delete the student
        $stmt = $conn->prepare("DELETE FROM students WHERE id = ? AND school_id = ?");
        $stmt->bind_param('ii', $student_id, $school_id);
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
 * Delete teacher and related data
 */
function deleteTeacher($conn, $teacher_id, $school_id) {
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
        
        // Update classes to remove teacher assignment
        $update_stmt = $conn->prepare("UPDATE classes SET teacher_id = NULL WHERE teacher_id = ?");
        $update_stmt->bind_param('i', $teacher_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Delete the teacher
        $stmt = $conn->prepare("DELETE FROM teachers WHERE id = ? AND school_id = ?");
        $stmt->bind_param('ii', $teacher_id, $school_id);
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
 * Delete parent and related data
 */
function deleteParent($conn, $parent_id, $school_id) {
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
        
        // Delete related records
        $tables = [
            'student_parent' => 'parent_id',
            'parent_feedback' => 'parent_id',
            'permission_requests' => 'parent_id'
        ];
        
        foreach ($tables as $table => $column) {
            $check_stmt = $conn->prepare("SHOW TABLES LIKE ?");
            $check_stmt->bind_param('s', $table);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                $delete_stmt = $conn->prepare("DELETE FROM {$table} WHERE {$column} = ?");
                $delete_stmt->bind_param('i', $parent_id);
                $delete_stmt->execute();
                $delete_stmt->close();
            }
            $check_stmt->close();
        }
        
        // Delete the parent
        $stmt = $conn->prepare("DELETE FROM parents WHERE id = ? AND school_id = ?");
        $stmt->bind_param('ii', $parent_id, $school_id);
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
 * Delete class and related data
 */
function deleteClass($conn, $class_id, $school_id) {
    try {
        $conn->begin_transaction();
        
        // Verify class belongs to this school
        $stmt = $conn->prepare("SELECT id FROM classes WHERE id = ? AND school_id = ?");
        $stmt->bind_param('ii', $class_id, $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Class not found or access denied");
        }
        
        // Update students to remove class assignment
        $update_stmt = $conn->prepare("UPDATE students SET class_id = NULL WHERE class_id = ?");
        $update_stmt->bind_param('i', $class_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Delete the class
        $stmt = $conn->prepare("DELETE FROM classes WHERE id = ? AND school_id = ?");
        $stmt->bind_param('ii', $class_id, $school_id);
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
 * Delete department and related data
 */
function deleteDepartment($conn, $department_id, $school_id) {
    try {
        $conn->begin_transaction();
        
        // Verify department belongs to this school
        $stmt = $conn->prepare("SELECT dep_id FROM departments WHERE dep_id = ? AND school_id = ?");
        $stmt->bind_param('ii', $department_id, $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Department not found or access denied");
        }
        
        // Update students and teachers to remove department assignment
        $update_students = $conn->prepare("UPDATE students SET department_id = NULL WHERE department_id = ?");
        $update_students->bind_param('i', $department_id);
        $update_students->execute();
        $update_students->close();
        
        $update_teachers = $conn->prepare("UPDATE teachers SET department_id = NULL WHERE department_id = ?");
        $update_teachers->bind_param('i', $department_id);
        $update_teachers->execute();
        $update_teachers->close();
        
        // Delete the department
        $stmt = $conn->prepare("DELETE FROM departments WHERE dep_id = ? AND school_id = ?");
        $stmt->bind_param('ii', $department_id, $school_id);
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
 * Delete bursar and related data
 */
function deleteBursar($conn, $bursar_id, $school_id) {
    try {
        $conn->begin_transaction();
        
        // Verify bursar belongs to this school
        $stmt = $conn->prepare("SELECT id FROM bursars WHERE id = ? AND school_id = ?");
        $stmt->bind_param('ii', $bursar_id, $school_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Bursar not found or access denied");
        }
        
        // Delete the bursar
        $stmt = $conn->prepare("DELETE FROM bursars WHERE id = ? AND school_id = ?");
        $stmt->bind_param('ii', $bursar_id, $school_id);
        $success = $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        return $success;
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}
?>
