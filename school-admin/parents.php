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

// Test database connection and parents table
if (isset($_GET['test']) && $_GET['test'] == '1') {
    echo "<div style='background: #f0f0f0; padding: 1rem; margin: 1rem; border-radius: 5px;'>";
    echo "<h3>Database Test Results:</h3>";

    // Test connection
    if ($conn) {
        echo "✅ Database connection: OK<br>";

        // Test parents table existence
        $table_check = $conn->query("SHOW TABLES LIKE 'parents'");
        if ($table_check && $table_check->num_rows > 0) {
            echo "✅ Parents table: EXISTS<br>";

            // Count all parents
            $count_result = $conn->query("SELECT COUNT(*) as total FROM parents");
            if ($count_result) {
                $count = $count_result->fetch_assoc()['total'];
                echo "✅ Total parents in database: $count<br>";

                // Show first few parents
                $sample_result = $conn->query("SELECT id, name, email, created_at FROM parents LIMIT 3");
                if ($sample_result && $sample_result->num_rows > 0) {
                    echo "✅ Sample parents:<br>";
                    while ($row = $sample_result->fetch_assoc()) {
                        echo "&nbsp;&nbsp;- ID: {$row['id']}, Name: {$row['name']}, Email: {$row['email']}<br>";
                    }
                } else {
                    echo "❌ No parents found in table<br>";
                }
            } else {
                echo "❌ Error counting parents: " . $conn->error . "<br>";
            }

            // Test students table and parent-child relationships
            $students_check = $conn->query("SHOW TABLES LIKE 'students'");
            if ($students_check && $students_check->num_rows > 0) {
                echo "✅ Students table: EXISTS<br>";

                $students_count = $conn->query("SELECT COUNT(*) as total FROM students");
                if ($students_count) {
                    $s_count = $students_count->fetch_assoc()['total'];
                    echo "✅ Total students in database: $s_count<br>";

                    // Check for student_parent relationship table (parent portal assignments)
                    $student_parent_check = $conn->query("SHOW TABLES LIKE 'student_parent'");
                    if ($student_parent_check && $student_parent_check->num_rows > 0) {
                        echo "✅ Student-Parent relationship table: EXISTS<br>";

                        $sp_count = $conn->query("SELECT COUNT(*) as total FROM student_parent");
                        if ($sp_count) {
                            $sp_total = $sp_count->fetch_assoc()['total'];
                            echo "✅ Total parent-child relationships: $sp_total<br>";

                            // Show sample relationships from student_parent table
                            $sp_relationships = $conn->query("
                                SELECT p.name as parent_name, p.email as parent_email,
                                       s.first_name, s.last_name, sp.is_primary
                                FROM student_parent sp
                                JOIN parents p ON sp.parent_id = p.id
                                JOIN students s ON sp.student_id = s.id
                                LIMIT 5
                            ");
                            if ($sp_relationships && $sp_relationships->num_rows > 0) {
                                echo "✅ Sample parent-child relationships (from parent portal):<br>";
                                while ($rel = $sp_relationships->fetch_assoc()) {
                                    $primary = $rel['is_primary'] ? ' (PRIMARY)' : '';
                                    echo "&nbsp;&nbsp;- Parent: {$rel['parent_name']} ({$rel['parent_email']}) → Child: {$rel['first_name']} {$rel['last_name']}$primary<br>";
                                }
                            }
                        }
                    } else {
                        echo "❌ Student-Parent relationship table: NOT FOUND<br>";
                        echo "&nbsp;&nbsp;→ Using fallback method (parent_name/parent_email fields in students table)<br>";

                        // Show fallback relationships
                        $relationships = $conn->query("SELECT parent_name, parent_email, parent_phone, first_name, last_name FROM students LIMIT 5");
                        if ($relationships && $relationships->num_rows > 0) {
                            echo "✅ Sample parent-child relationships (fallback method):<br>";
                            while ($rel = $relationships->fetch_assoc()) {
                                echo "&nbsp;&nbsp;- Parent: {$rel['parent_name']} ({$rel['parent_email']}) → Child: {$rel['first_name']} {$rel['last_name']}<br>";
                            }
                        }
                    }
                }
            } else {
                echo "❌ Students table: NOT FOUND<br>";
            }
        } else {
            echo "❌ Parents table: NOT FOUND<br>";
        }
    } else {
        echo "❌ Database connection: FAILED<br>";
    }
    echo "</div>";
}

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $parent_id = intval($_GET['id']);

    // Delete the parent
    $stmt = $conn->prepare("DELETE FROM parents WHERE id = ? AND school_id = ?");
    $stmt->bind_param('ii', $parent_id, $school_id);

    if ($stmt->execute()) {
        $_SESSION['parent_success'] = 'Parent deleted successfully!';
    } else {
        $_SESSION['parent_error'] = 'Failed to delete parent: ' . $conn->error;
    }

    $stmt->close();
    header('Location: parents.php');
    exit;
}

// Handle reply to feedback
if (isset($_POST['reply_feedback']) && isset($_POST['feedback_id']) && isset($_POST['reply_message'])) {
    $feedback_id = intval($_POST['feedback_id']);
    $reply_message = trim($_POST['reply_message']);
    $admin_id = $_SESSION['school_admin_id'];

    if (!empty($reply_message)) {
        // Insert reply into feedback_replies table
        $stmt = $conn->prepare("INSERT INTO feedback_replies (feedback_id, admin_id, reply_message, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param('iis', $feedback_id, $admin_id, $reply_message);

        if ($stmt->execute()) {
            $_SESSION['parent_success'] = 'Reply sent successfully!';
        } else {
            $_SESSION['parent_error'] = 'Failed to send reply: ' . $conn->error;
        }
        $stmt->close();
    } else {
        $_SESSION['parent_error'] = 'Reply message cannot be empty.';
    }

    header('Location: parents.php');
    exit;
}

// Create necessary tables if they don't exist
try {
    // Parents table
    $result = $conn->query("SHOW TABLES LIKE 'parents'");
    if ($result->num_rows == 0) {
        $conn->query("CREATE TABLE IF NOT EXISTS parents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            school_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(20),
            address TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
        )");
    }

    // Parent feedback table
    $result = $conn->query("SHOW TABLES LIKE 'parent_feedback'");
    if ($result->num_rows == 0) {
        $create_feedback_sql = "CREATE TABLE parent_feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            parent_id INT NOT NULL,
            school_id INT NOT NULL,
            feedback_text TEXT NOT NULL,
            sentiment_score DECIMAL(3, 2) DEFAULT NULL,
            sentiment_label ENUM('positive', 'neutral', 'negative') DEFAULT 'neutral',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_parent_id (parent_id),
            INDEX idx_school_id (school_id)
        )";

        if (!$conn->query($create_feedback_sql)) {
            error_log("Error creating parent_feedback table: " . $conn->error);
        }
    }

    // Feedback replies table
    $result = $conn->query("SHOW TABLES LIKE 'feedback_replies'");
    if ($result->num_rows == 0) {
        $create_replies_sql = "CREATE TABLE feedback_replies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            feedback_id INT NOT NULL,
            admin_id INT NOT NULL,
            reply_message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_feedback_id (feedback_id),
            INDEX idx_admin_id (admin_id)
        )";

        if (!$conn->query($create_replies_sql)) {
            error_log("Error creating feedback_replies table: " . $conn->error);
        }
    }

    // Add sample feedback data if tables are empty (for testing)
    if (isset($_GET['add_sample_data']) && $_GET['add_sample_data'] == '1') {
        // Check if we have any parents first
        $parent_check = $conn->query("SELECT COUNT(*) as count FROM parents WHERE school_id = $school_id");
        $parent_count = $parent_check->fetch_assoc()['count'];

        if ($parent_count > 0) {
            // Get a parent ID for sample data
            $parent_result = $conn->query("SELECT id FROM parents WHERE school_id = $school_id LIMIT 1");
            $parent_id = $parent_result->fetch_assoc()['id'];

            // Check if feedback table is empty
            $feedback_check = $conn->query("SELECT COUNT(*) as count FROM parent_feedback WHERE school_id = $school_id");
            $feedback_count = $feedback_check->fetch_assoc()['count'];

            if ($feedback_count == 0) {
                // Insert sample feedback
                $sample_feedback = [
                    "The school is doing an excellent job with my child's education. The teachers are very supportive and caring.",
                    "I'm concerned about the lack of extracurricular activities. My child needs more opportunities to explore their interests.",
                    "The communication between school and parents could be improved. Sometimes I feel out of the loop about school events."
                ];

                $sentiments = ['positive', 'negative', 'neutral'];

                for ($i = 0; $i < 3; $i++) {
                    $stmt = $conn->prepare("INSERT INTO parent_feedback (parent_id, school_id, feedback_text, sentiment_label) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param('iiss', $parent_id, $school_id, $sample_feedback[$i], $sentiments[$i]);
                    $stmt->execute();
                    $stmt->close();
                }

                $_SESSION['parent_success'] = 'Sample feedback data added for testing!';
            }
        }
    }

} catch (Exception $e) {
    $_SESSION['parent_error'] = "Database error: " . $e->getMessage();
}

// Fetch all parents from database
$parents = [];
$parent_stats = ['total' => 0, 'with_feedback' => 0, 'recent' => 0];

try {
    // First, let's try a simple SELECT * FROM parents to get all parents
    $simple_query = "SELECT * FROM parents ORDER BY created_at DESC";
    $result = $conn->query($simple_query);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Add default values for feedback-related fields
            $row['feedback_count'] = 0;
            $row['last_feedback_date'] = null;
            $row['is_recent'] = (strtotime($row['created_at']) >= strtotime('-30 days')) ? 1 : 0;
            $row['children'] = []; // Initialize children array
            $row['children_names'] = ''; // Initialize children names string

            $parents[] = $row;
            $parent_stats['total']++;
            if ($row['is_recent']) {
                $parent_stats['recent']++;
            }
        }

        // Now try to get children information for each parent
        $students_table_check = $conn->query("SHOW TABLES LIKE 'students'");
        if ($students_table_check && $students_table_check->num_rows > 0) {
            // First check what columns exist in students table
            $columns_check = $conn->query("SHOW COLUMNS FROM students");
            $available_columns = [];
            while ($col = $columns_check->fetch_assoc()) {
                $available_columns[] = $col['Field'];
            }

            // Build query based on available columns
            $select_fields = "s.id, s.first_name, s.last_name";
            if (in_array('reg_number', $available_columns)) {
                $select_fields .= ", s.reg_number";
            }
            if (in_array('registration_number', $available_columns)) {
                $select_fields .= ", s.registration_number";
            }
            if (in_array('admission_number', $available_columns)) {
                $select_fields .= ", s.admission_number";
            }
            if (in_array('class_name', $available_columns)) {
                $select_fields .= ", s.class_name";
            }
            if (in_array('class', $available_columns)) {
                $select_fields .= ", s.class";
            }
            if (in_array('grade_level', $available_columns)) {
                $select_fields .= ", s.grade_level";
            }
            if (in_array('grade', $available_columns)) {
                $select_fields .= ", s.grade";
            }
            if (in_array('department_id', $available_columns)) {
                $select_fields .= ", s.department_id";
            }
            if (in_array('class_id', $available_columns)) {
                $select_fields .= ", s.class_id";
            }
            if (in_array('school_id', $available_columns)) {
                $select_fields .= ", s.school_id";
            }

            // Add department and class information if tables exist
            $dept_join = "";
            $class_join = "";
            $dept_table_check = $conn->query("SHOW TABLES LIKE 'departments'");
            $class_table_check = $conn->query("SHOW TABLES LIKE 'classes'");

            if ($dept_table_check && $dept_table_check->num_rows > 0) {
                // Check what columns exist in departments table
                $dept_columns_check = $conn->query("SHOW COLUMNS FROM departments");
                $dept_columns = [];
                while ($col = $dept_columns_check->fetch_assoc()) {
                    $dept_columns[] = $col['Field'];
                }

                // Use the correct department name column
                if (in_array('dep_name', $dept_columns)) {
                    $dept_join = " LEFT JOIN departments d ON s.department_id = d.dep_id";
                    $select_fields .= ", d.dep_name as department_name";
                } elseif (in_array('name', $dept_columns)) {
                    $dept_join = " LEFT JOIN departments d ON s.department_id = d.id";
                    $select_fields .= ", d.name as department_name";
                } elseif (in_array('department_name', $dept_columns)) {
                    $dept_join = " LEFT JOIN departments d ON s.department_id = d.id";
                    $select_fields .= ", d.department_name as department_name";
                }
            }

            if ($class_table_check && $class_table_check->num_rows > 0) {
                // Check what columns exist in classes table
                $class_columns_check = $conn->query("SHOW COLUMNS FROM classes");
                $class_columns = [];
                while ($col = $class_columns_check->fetch_assoc()) {
                    $class_columns[] = $col['Field'];
                }

                // Use the correct class columns
                $class_join_parts = [];
                if (in_array('class_name', $class_columns)) {
                    $class_join_parts[] = "c.class_name as class_table_name";
                }
                if (in_array('name', $class_columns)) {
                    $class_join_parts[] = "c.name as class_table_name";
                }
                if (in_array('grade_level', $class_columns)) {
                    $class_join_parts[] = "c.grade_level as class_grade_level";
                }
                if (in_array('grade', $class_columns)) {
                    $class_join_parts[] = "c.grade as class_grade_level";
                }

                if (!empty($class_join_parts)) {
                    $class_join = " LEFT JOIN classes c ON s.class_id = c.id";
                    $select_fields .= ", " . implode(", ", $class_join_parts);
                }
            }

            // Check if student_parent relationship table exists (parent portal assignments)
            $student_parent_check = $conn->query("SHOW TABLES LIKE 'student_parent'");
            $use_relationship_table = $student_parent_check && $student_parent_check->num_rows > 0;

            // Update parents with their children information
            foreach ($parents as $index => $parent) {
                $children = [];
                $children_names = [];

                try {
                    if ($use_relationship_table) {
                        // Use student_parent relationship table (parent portal assignments)
                        // Try with JOINs first
                        if (!empty($dept_join) || !empty($class_join)) {
                            $children_query = "SELECT $select_fields, sp.is_primary, sp.created_at as assignment_date
                                             FROM student_parent sp
                                             JOIN students s ON sp.student_id = s.id
                                             $dept_join $class_join
                                             WHERE sp.parent_id = ?
                                             ORDER BY sp.is_primary DESC, s.first_name ASC, s.last_name ASC";
                        } else {
                            // Simple query without JOINs
                            $simple_fields = "s.id, s.first_name, s.last_name";
                            if (in_array('reg_number', $available_columns)) {
                                $simple_fields .= ", s.reg_number";
                            }
                            if (in_array('registration_number', $available_columns)) {
                                $simple_fields .= ", s.registration_number";
                            }
                            if (in_array('admission_number', $available_columns)) {
                                $simple_fields .= ", s.admission_number";
                            }
                            if (in_array('class_name', $available_columns)) {
                                $simple_fields .= ", s.class_name";
                            }
                            if (in_array('grade_level', $available_columns)) {
                                $simple_fields .= ", s.grade_level";
                            }

                            $children_query = "SELECT $simple_fields, sp.is_primary, sp.created_at as assignment_date
                                             FROM student_parent sp
                                             JOIN students s ON sp.student_id = s.id
                                             WHERE sp.parent_id = ?
                                             ORDER BY sp.is_primary DESC, s.first_name ASC, s.last_name ASC";
                        }
                        $stmt = $conn->prepare($children_query);
                        $stmt->bind_param('i', $parent['id']);
                    } else {
                        // Fallback to old method - match by parent name, email, and phone
                        $simple_fields = "s.id, s.first_name, s.last_name";
                        if (in_array('reg_number', $available_columns)) {
                            $simple_fields .= ", s.reg_number";
                        }
                        if (in_array('class_name', $available_columns)) {
                            $simple_fields .= ", s.class_name";
                        }
                        if (in_array('grade_level', $available_columns)) {
                            $simple_fields .= ", s.grade_level";
                        }

                        $children_query = "SELECT $simple_fields FROM students s WHERE s.school_id = ? AND (s.parent_name = ? OR s.parent_email = ? OR s.parent_phone = ?)";
                        $stmt = $conn->prepare($children_query);
                        $parent_name = $parent['name'];
                        $parent_email = $parent['email'];
                        $parent_phone = $parent['phone'] ?? '';
                        $stmt->bind_param('isss', $school_id, $parent_name, $parent_email, $parent_phone);
                    }

                    $stmt->execute();
                    $children_result = $stmt->get_result();
                } catch (Exception $child_e) {
                    // If there's an error with the complex query, try a very simple one
                    error_log("Error in children query: " . $child_e->getMessage());

                    if ($use_relationship_table) {
                        $simple_query = "SELECT s.id, s.first_name, s.last_name, sp.is_primary
                                       FROM student_parent sp
                                       JOIN students s ON sp.student_id = s.id
                                       WHERE sp.parent_id = ?
                                       ORDER BY sp.is_primary DESC, s.first_name ASC";
                        $stmt = $conn->prepare($simple_query);
                        $stmt->bind_param('i', $parent['id']);
                        $stmt->execute();
                        $children_result = $stmt->get_result();
                    } else {
                        $children_result = false;
                    }
                }

                while ($child = $children_result->fetch_assoc()) {
                    $children[] = $child;
                    $full_name = trim($child['first_name'] . ' ' . $child['last_name']);

                    // Build class info from available fields (prioritize joined table data)
                    $class_info = '';
                    $department_info = '';
                    $id_info = '';

                    // Get student ID information
                    if (isset($child['admission_number']) && $child['admission_number']) {
                        $id_info = 'ID: ' . $child['admission_number'];
                    } elseif (isset($child['registration_number']) && $child['registration_number']) {
                        $id_info = 'ID: ' . $child['registration_number'];
                    } elseif (isset($child['reg_number']) && $child['reg_number']) {
                        $id_info = 'ID: ' . $child['reg_number'];
                    }

                    // Get department information
                    if (isset($child['department_name']) && $child['department_name']) {
                        $department_info = $child['department_name'];
                    }

                    // Get grade information
                    if (isset($child['class_grade_level']) && $child['class_grade_level']) {
                        $class_info .= $child['class_grade_level'];
                    } elseif (isset($child['grade_level']) && $child['grade_level']) {
                        $class_info .= $child['grade_level'];
                    } elseif (isset($child['grade']) && $child['grade']) {
                        $class_info .= $child['grade'];
                    }

                    // Get class information
                    if (isset($child['class_table_name']) && $child['class_table_name']) {
                        $class_info .= ($class_info ? ' - ' : '') . $child['class_table_name'];
                    } elseif (isset($child['class_name']) && $child['class_name']) {
                        $class_info .= ($class_info ? ' - ' : '') . $child['class_name'];
                    } elseif (isset($child['class']) && $child['class']) {
                        $class_info .= ($class_info ? ' - ' : '') . $child['class'];
                    }

                    // Build full display name with class and department info
                    $display_parts = [];
                    if ($class_info) {
                        $display_parts[] = $class_info;
                    }
                    if ($department_info) {
                        $display_parts[] = $department_info;
                    }
                    if ($id_info) {
                        $display_parts[] = $id_info;
                    }

                    // Add primary indicator if using relationship table
                    if ($use_relationship_table && isset($child['is_primary']) && $child['is_primary']) {
                        $display_parts[] = 'Primary';
                    }

                    if (!empty($display_parts)) {
                        $children_names[] = $full_name . ' (' . implode(' | ', $display_parts) . ')';
                    } else {
                        $children_names[] = $full_name;
                    }
                }

                $parents[$index]['children'] = $children;
                $parents[$index]['children_names'] = implode(', ', $children_names);
                $parents[$index]['children_count'] = count($children);
                $stmt->close();
            }
        }

        // Now try to get feedback counts if the feedback table exists
        $feedback_table_check = $conn->query("SHOW TABLES LIKE 'parent_feedback'");
        if ($feedback_table_check && $feedback_table_check->num_rows > 0) {
            // Update parents with feedback counts
            foreach ($parents as $index => $parent) {
                $feedback_query = "SELECT COUNT(*) as count, MAX(created_at) as last_date FROM parent_feedback WHERE parent_id = ?";
                $stmt = $conn->prepare($feedback_query);
                $stmt->bind_param('i', $parent['id']);
                $stmt->execute();
                $feedback_result = $stmt->get_result();
                $feedback_data = $feedback_result->fetch_assoc();

                $parents[$index]['feedback_count'] = $feedback_data['count'];
                $parents[$index]['last_feedback_date'] = $feedback_data['last_date'];

                if ($feedback_data['count'] > 0) {
                    $parent_stats['with_feedback']++;
                }
                $stmt->close();
            }
        }

        // Debug information
        if (isset($_GET['debug']) && $_GET['debug'] == '1') {
            echo "<!-- DEBUG INFO:\n";
            echo "Total parents found: " . count($parents) . "\n";
            echo "Parent stats: " . print_r($parent_stats, true) . "\n";
            echo "Using student_parent relationship table: " . ($use_relationship_table ?? false ? 'YES' : 'NO') . "\n";
            echo "Department join: " . ($dept_join ?? 'NONE') . "\n";
            echo "Class join: " . ($class_join ?? 'NONE') . "\n";
            if (count($parents) > 0) {
                echo "First parent: " . print_r($parents[0], true) . "\n";
                echo "Available columns in students table: " . print_r($available_columns ?? [], true) . "\n";
                if (isset($dept_columns)) {
                    echo "Available columns in departments table: " . print_r($dept_columns, true) . "\n";
                }
                if (isset($class_columns)) {
                    echo "Available columns in classes table: " . print_r($class_columns, true) . "\n";
                }

                // Show ALL parent-child relationships
                foreach ($parents as $p) {
                    if (!empty($p['children'])) {
                        echo "Parent: " . $p['name'] . " (" . $p['email'] . ") has " . count($p['children']) . " children:\n";
                        foreach ($p['children'] as $c) {
                            $primary_text = (isset($c['is_primary']) && $c['is_primary']) ? ' (PRIMARY)' : '';
                            $id_text = '';
                            if (isset($c['admission_number']) && $c['admission_number']) {
                                $id_text = ' [ID: ' . $c['admission_number'] . ']';
                            } elseif (isset($c['registration_number']) && $c['registration_number']) {
                                $id_text = ' [ID: ' . $c['registration_number'] . ']';
                            } elseif (isset($c['reg_number']) && $c['reg_number']) {
                                $id_text = ' [ID: ' . $c['reg_number'] . ']';
                            }
                            echo "  - " . $c['first_name'] . " " . $c['last_name'] . $primary_text . $id_text . "\n";
                        }
                    } else {
                        echo "Parent: " . $p['name'] . " (" . $p['email'] . ") has NO children assigned\n";
                    }
                }
            }
            echo "-->\n";
        }

    } else {
        throw new Exception("Failed to execute query: " . $conn->error);
    }

} catch (Exception $e) {
    error_log("Error fetching parents: " . $e->getMessage());

    // Try even simpler query as fallback
    try {
        $fallback_result = $conn->query("SELECT * FROM parents LIMIT 10");
        if ($fallback_result) {
            $parent_count = $fallback_result->num_rows;
            $_SESSION['parent_error'] = "Found $parent_count parents but there was an issue loading full data. Debug: " . $e->getMessage();
        } else {
            $_SESSION['parent_error'] = "Database connection issue: " . $conn->error;
        }
    } catch (Exception $fallback_e) {
        $_SESSION['parent_error'] = "Critical database error: " . $fallback_e->getMessage();
    }
}

// Fetch parent feedback with replies
$feedback = [];
try {
    // First check if parent_feedback table exists and has the correct structure
    $table_check = $conn->query("SHOW TABLES LIKE 'parent_feedback'");
    if ($table_check->num_rows > 0) {
        // Check columns in parent_feedback table
        $columns_check = $conn->query("SHOW COLUMNS FROM parent_feedback");
        $columns = [];
        while ($col = $columns_check->fetch_assoc()) {
            $columns[] = $col['Field'];
        }

        // Check if required columns exist
        if (in_array('feedback_text', $columns) && in_array('parent_id', $columns)) {
            $stmt = $conn->prepare('
                SELECT
                    pf.id,
                    pf.feedback_text,
                    pf.sentiment_label,
                    pf.created_at,
                    p.name as parent_name,
                    p.email as parent_email,
                    p.id as parent_id,
                    fr.reply_message,
                    fr.created_at as reply_date
                FROM parent_feedback pf
                JOIN parents p ON pf.parent_id = p.id
                LEFT JOIN feedback_replies fr ON pf.id = fr.feedback_id
                WHERE pf.school_id = ?
                ORDER BY pf.created_at DESC, fr.created_at ASC
            ');
            $stmt->bind_param('i', $school_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $feedback_data = [];
            while ($row = $result->fetch_assoc()) {
                $feedback_id = $row['id'];
                if (!isset($feedback_data[$feedback_id])) {
                    $feedback_data[$feedback_id] = [
                        'id' => $row['id'],
                        'feedback_text' => $row['feedback_text'],
                        'sentiment_label' => $row['sentiment_label'],
                        'created_at' => $row['created_at'],
                        'parent_name' => $row['parent_name'],
                        'parent_email' => $row['parent_email'],
                        'parent_id' => $row['parent_id'],
                        'replies' => []
                    ];
                }

                if ($row['reply_message']) {
                    $feedback_data[$feedback_id]['replies'][] = [
                        'reply_message' => $row['reply_message'],
                        'reply_date' => $row['reply_date']
                    ];
                }
            }
            $feedback = array_values($feedback_data);
            $stmt->close();
        }
    }
} catch (Exception $e) {
    // Don't show error for missing feedback table, just log it
    error_log("Feedback table issue: " . $e->getMessage());
}

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
    <title>Manage Parents - <?php echo htmlspecialchars($school_info['name'] ?? 'School'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo PRIMARY_COLOR ?? '#00704a'; ?>;
            --footer-color: <?php echo FOOTER_COLOR ?? '#f8c301'; ?>;
            --accent-color: <?php echo ACCENT_COLOR ?? '#00704a'; ?>;
            --light-color: #ffffff;
            --dark-color: #333333;
            --gray-color: #f5f5f5;
            --border-color: #e0e0e0;
            --danger-color: #f44336;
            --sidebar-width: 250px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.12);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --radius-sm: 4px;
            --radius-md: 8px;
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--dark-color);
            background-color: var(--gray-color);
            min-height: 100vh;
            display: flex;
        }

        /* Search Box Styles */
        .search-container {
            margin-bottom: 1.5rem;
        }

        .search-box {
            display: flex;
            align-items: center;
            background-color: white;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 0.5rem 1rem;
            box-shadow: var(--shadow-sm);
        }

        .search-icon {
            color: var(--primary-color);
            margin-right: 0.5rem;
        }

        .search-input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 1rem;
            padding: 0.25rem 0;
        }

        .search-clear {
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .search-clear:hover {
            color: var(--danger-color);
        }

        .empty-search-results {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        .empty-search-results i {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #ccc;
        }
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--primary-color);
            color: var(--light-color);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--light-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .school-logo-container {
            display: flex;
            justify-content: center;
            margin-bottom: 1rem;
        }

        .school-logo, .school-logo-placeholder {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }

        .school-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .school-logo-placeholder i {
            font-size: 2rem;
            color: var(--primary-color);
        }

        .sidebar-logo span {
            color: var(--footer-color);
        }

        .sidebar-user {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--accent-color);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.8rem;
            color: white;
            font-weight: bold;
        }

        .user-info h3 {
            font-size: 0.9rem;
            margin-bottom: 0.2rem;
        }

        .user-info p {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        .sidebar-menu {
            padding: 1rem 0;
        }

        .menu-heading {
            padding: 0.5rem 1.5rem;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.6;
        }

        .menu-item {
            padding: 0.8rem 1.5rem;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }

        .menu-item:hover, .menu-item.active {
            background-color: var(--accent-color);
        }

        .menu-item i {
            margin-right: 0.8rem;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }

        .menu-item a {
            color: var(--light-color);
            text-decoration: none;
            font-weight: 500;
            flex: 1;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-header h1 {
            font-size: 1.8rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
        }

        .breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
        }

        .breadcrumb span {
            margin: 0 0.5rem;
            color: #999;
        }
        /* Card Styles */
        .card {
            background-color: var(--light-color);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            font-size: 1.2rem;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 112, 74, 0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: var(--radius-sm);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            border: none;
            font-size: 1rem;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--accent-color);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #d32f2f;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        /* Alert Styles */
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid transparent;
        }

        .alert-success {
            background-color: #e8f5e9;
            border-color: #4caf50;
            color: #2e7d32;
        }

        .alert-danger {
            background-color: #ffebee;
            border-color: #f44336;
            color: #c62828;
        }

        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .data-table th {
            font-weight: 600;
            color: var(--primary-color);
            background-color: rgba(0, 112, 74, 0.05);
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .data-table tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }

        .action-btns {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
            text-decoration: none;
        }

        .btn-icon.edit {
            background-color: var(--primary-color);
        }

        .btn-icon.delete {
            background-color: var(--danger-color);
        }

        .btn-icon.reply {
            background-color: #2196f3;
        }

        /* Feedback Styles */
        .feedback-item {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .feedback-header {
            background-color: #f8f9fa;
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .feedback-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .sentiment-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .sentiment-positive {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .sentiment-negative {
            background-color: #ffebee;
            color: #c62828;
        }

        .sentiment-neutral {
            background-color: #fff3e0;
            color: #f57c00;
        }

        .feedback-content {
            padding: 1rem;
        }

        .feedback-text {
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .replies-section {
            border-top: 1px solid var(--border-color);
            background-color: #f8f9fa;
        }

        .reply-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
            background-color: #e3f2fd;
        }

        .reply-item:last-child {
            border-bottom: none;
        }

        .reply-meta {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .reply-form {
            padding: 1rem;
            background-color: #f8f9fa;
            border-top: 1px solid var(--border-color);
        }

        .reply-form textarea {
            width: 100%;
            min-height: 80px;
            margin-bottom: 0.5rem;
            resize: vertical;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
        }

        .empty-icon {
            font-size: 4rem;
            color: #ccc;
            margin-bottom: 1rem;
        }

        .empty-text {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 1.5rem;
        }

        /* Tabs */
        .tab-container {
            margin-bottom: 2rem;
        }

        .tab-nav {
            display: flex;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 1.5rem;
        }

        .tab-btn {
            padding: 1rem 1.5rem;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 500;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .tab-btn.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Export button */
        .export-btn:hover {
            background-color: #e9ecef !important;
            border-color: #adb5bd !important;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
                overflow: visible;
            }

            .sidebar-header, .sidebar-user, .menu-heading {
                display: none;
            }

            .menu-item {
                padding: 1rem 0;
                justify-content: center;
            }

            .menu-item i {
                margin-right: 0;
                font-size: 1.3rem;
            }

            .menu-item a span {
                display: none;
            }

            .main-content {
                margin-left: 70px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .tab-nav {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-users"></i> Manage Parents & Feedback</h1>
            <div class="breadcrumb">
                <a href="dashboard.php">Home</a>
                <span>/</span>
                <span>Parents</span>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($_SESSION['parent_success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php
                echo $_SESSION['parent_success'];
                unset($_SESSION['parent_success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['parent_error'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php
                echo $_SESSION['parent_error'];
                unset($_SESSION['parent_error']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Tab Navigation -->
        <div class="tab-container">
            <div class="tab-nav">
                <button class="tab-btn active" onclick="switchTab('parents')">
                    <i class="fas fa-users"></i> Parents Management
                </button>
                <button class="tab-btn" onclick="switchTab('feedback')">
                    <i class="fas fa-comments"></i> Parent Feedback
                </button>
            </div>

            <!-- Parents Tab -->
            <div id="parents-tab" class="tab-content active">
                <!-- Parent Statistics Cards -->
                <div class="stats-grid">
                    <div class="stat-card all-parents" onclick="filterParents('all')">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $parent_stats['total']; ?></h3>
                            <p>Total Parents</p>
                        </div>
                    </div>

                    <div class="stat-card active-parents" onclick="filterParents('with_feedback')">
                        <div class="stat-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $parent_stats['with_feedback']; ?></h3>
                            <p>With Feedback</p>
                        </div>
                    </div>

                    <div class="stat-card recent-parents" onclick="filterParents('recent')">
                        <div class="stat-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $parent_stats['recent']; ?></h3>
                            <p>Recent (30 days)</p>
                        </div>
                    </div>

                    <div class="stat-card no-feedback-parents" onclick="filterParents('no_feedback')">
                        <div class="stat-icon">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo ($parent_stats['total'] - $parent_stats['with_feedback']); ?></h3>
                            <p>No Feedback</p>
                        </div>
                    </div>

                    <div class="stat-card contact-parents" onclick="filterParents('with_phone')">
                        <div class="stat-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php
                                $with_phone = 0;
                                foreach($parents as $parent) {
                                    if (!empty($parent['phone'])) $with_phone++;
                                }
                                echo $with_phone;
                            ?></h3>
                            <p>With Phone</p>
                        </div>
                    </div>

                    <div class="stat-card children-parents" onclick="filterParents('with_children')">
                        <div class="stat-icon">
                            <i class="fas fa-child"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php
                                $with_children = 0;
                                foreach($parents as $parent) {
                                    if (!empty($parent['children']) && count($parent['children']) > 0) $with_children++;
                                }
                                echo $with_children;
                            ?></h3>
                            <p>With Children</p>
                        </div>
                    </div>
                </div>

                <!-- Parents List Card -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-list"></i> All Parents (<?php echo count($parents); ?>)</h2>
                        <!-- Action buttons positioned at the right -->
                        <div style="display: flex; gap: 1rem; align-items: center;">
                            <button onclick="exportParentData()" class="btn export-btn" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.25rem; font-size: 0.9rem; border-radius: 6px; background-color: #f8f9fa; border: 1px solid #dee2e6; color: #495057; transition: all 0.3s ease;">
                                <i class="fas fa-download"></i> Export List
                            </button>
                            <button class="btn btn-primary" onclick="openModal('addParentModal')" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; font-size: 0.9rem; border-radius: 6px; transition: all 0.3s ease;">
                                <i class="fas fa-user-plus"></i> Add Parent
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (count($parents) > 0): ?>
                            <!-- Search Box -->
                            <div class="search-container" data-table="parents-table">
                                <div class="search-box">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="text" class="search-input" placeholder="Search parents...">
                                    <button type="button" class="search-clear" onclick="clearSearch('parents-table')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <br>

                            <!-- Debug info for troubleshooting -->
                            <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                                <div style="background: #e3f2fd; padding: 1rem; margin-bottom: 1rem; border-radius: 5px; font-size: 0.9rem;">
                                    <strong>Debug Info:</strong><br>
                                    Parents loaded: <?php echo count($parents); ?><br>
                                    <?php if (count($parents) > 0): ?>
                                        First parent: <?php echo htmlspecialchars($parents[0]['name'] ?? 'No name'); ?> (ID: <?php echo $parents[0]['id'] ?? 'No ID'; ?>)<br>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="table-responsive">
                                <table class="data-table" id="parents-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 3%;">#</th>
                                            <th style="width: 15%;">Name</th>
                                            <th style="width: 18%;">Email</th>
                                            <th style="width: 12%;">Phone</th>
                                            <th style="width: 15%;">Location</th>
                                            <th style="width: 20%;">Children</th>
                                            <th style="width: 8%;">Feedback</th>
                                            <th style="width: 6%;">Status</th>
                                            <th style="width: 3%;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $counter = 1; ?>
                                        <?php foreach ($parents as $parent): ?>
                                            <tr>
                                                <td style="font-weight: 500; color: #666;"><?php echo $counter++; ?></td>
                                                <td>
                                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                        <div style="width: 32px; height: 32px; background: <?php echo $parent['is_recent'] ? '#00704a' : '#6c757d'; ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 0.8rem;">
                                                            <?php
                                                                // Get first letter for avatar
                                                                $display_name = '';
                                                                if (isset($parent['first_name']) && isset($parent['last_name'])) {
                                                                    $display_name = trim($parent['first_name'] . ' ' . $parent['last_name']);
                                                                    echo strtoupper(substr($parent['first_name'], 0, 1));
                                                                } elseif (isset($parent['name'])) {
                                                                    $display_name = $parent['name'];
                                                                    echo strtoupper(substr($parent['name'], 0, 1));
                                                                } else {
                                                                    echo 'P';
                                                                }
                                                            ?>
                                                        </div>
                                                        <div>
                                                            <div style="font-weight: 500;">
                                                                <?php
                                                                    // Display full name
                                                                    if (isset($parent['first_name']) && isset($parent['last_name'])) {
                                                                        echo htmlspecialchars(trim($parent['first_name'] . ' ' . $parent['last_name']));
                                                                    } elseif (isset($parent['name'])) {
                                                                        echo htmlspecialchars($parent['name']);
                                                                    } else {
                                                                        echo 'Unknown Parent';
                                                                    }
                                                                ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                        <i class="fas fa-envelope" style="color: #6c757d; font-size: 0.8rem;"></i>
                                                        <a href="mailto:<?php echo htmlspecialchars($parent['email']); ?>" style="color: #00704a; text-decoration: none;">
                                                            <?php echo htmlspecialchars($parent['email']); ?>
                                                        </a>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php if ($parent['phone']): ?>
                                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                            <i class="fas fa-phone" style="color: #6c757d; font-size: 0.8rem;"></i>
                                                            <a href="tel:<?php echo htmlspecialchars($parent['phone']); ?>" style="color: #00704a; text-decoration: none;">
                                                                <?php echo htmlspecialchars($parent['phone']); ?>
                                                            </a>
                                                        </div>
                                                    <?php else: ?>
                                                        <span style="color: #999; font-style: italic;">No phone</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($parent['address']): ?>
                                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                            <i class="fas fa-map-marker-alt" style="color: #6c757d; font-size: 0.8rem;"></i>
                                                            <span style="color: #333;" title="<?php echo htmlspecialchars($parent['address']); ?>">
                                                                <?php echo htmlspecialchars(substr($parent['address'], 0, 25)) . (strlen($parent['address']) > 25 ? '...' : ''); ?>
                                                            </span>
                                                        </div>
                                                    <?php else: ?>
                                                        <span style="color: #999; font-style: italic;">No location</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($parent['children']) && count($parent['children']) > 0): ?>
                                                        <div style="display: flex; flex-direction: column; gap: 0.3rem; max-height: 120px; overflow-y: auto;">
                                                            <?php foreach ($parent['children'] as $index => $child): ?>
                                                                <div style="display: flex; align-items: center; gap: 0.5rem; padding: 0.2rem; background: <?php echo ($index % 2 == 0) ? '#f8f9fa' : '#ffffff'; ?>; border-radius: 4px;">
                                                                    <div style="width: 20px; height: 20px; background: <?php echo isset($child['is_primary']) && $child['is_primary'] ? '#4caf50' : '#2196f3'; ?>; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 0.7rem;">
                                                                        <?php echo strtoupper(substr($child['first_name'], 0, 1)); ?>
                                                                    </div>
                                                                    <div style="font-size: 0.85rem; flex: 1;">
                                                                        <div style="font-weight: 500; color: #333; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                                                            <span><?php echo htmlspecialchars(trim($child['first_name'] . ' ' . $child['last_name'])); ?></span>
                                                                            <?php if (isset($child['is_primary']) && $child['is_primary']): ?>
                                                                                <span style="background: #4caf50; color: white; font-size: 0.6rem; padding: 0.1rem 0.3rem; border-radius: 8px; font-weight: bold;">PRIMARY</span>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                        <?php
                                                                            // Build class info from available fields (prioritize joined table data)
                                                                            $class_info = '';
                                                                            $department_info = '';
                                                                            $id_info = '';

                                                                            // Get student ID information
                                                                            if (isset($child['admission_number']) && $child['admission_number']) {
                                                                                $id_info = 'ID: ' . $child['admission_number'];
                                                                            } elseif (isset($child['registration_number']) && $child['registration_number']) {
                                                                                $id_info = 'ID: ' . $child['registration_number'];
                                                                            } elseif (isset($child['reg_number']) && $child['reg_number']) {
                                                                                $id_info = 'ID: ' . $child['reg_number'];
                                                                            }

                                                                            // Get department information
                                                                            if (isset($child['department_name']) && $child['department_name']) {
                                                                                $department_info = $child['department_name'];
                                                                            }

                                                                            // Get grade information
                                                                            if (isset($child['class_grade_level']) && $child['class_grade_level']) {
                                                                                $class_info .= $child['class_grade_level'];
                                                                            } elseif (isset($child['grade_level']) && $child['grade_level']) {
                                                                                $class_info .= $child['grade_level'];
                                                                            } elseif (isset($child['grade']) && $child['grade']) {
                                                                                $class_info .= $child['grade'];
                                                                            }

                                                                            // Get class information
                                                                            if (isset($child['class_table_name']) && $child['class_table_name']) {
                                                                                $class_info .= ($class_info ? ' - ' : '') . $child['class_table_name'];
                                                                            } elseif (isset($child['class_name']) && $child['class_name']) {
                                                                                $class_info .= ($class_info ? ' - ' : '') . $child['class_name'];
                                                                            } elseif (isset($child['class']) && $child['class']) {
                                                                                $class_info .= ($class_info ? ' - ' : '') . $child['class'];
                                                                            }

                                                                            if ($class_info || $department_info || $id_info): ?>
                                                                            <div style="font-size: 0.75rem; color: #666; margin-top: 0.1rem;">
                                                                                <?php
                                                                                    $info_parts = [];
                                                                                    if ($class_info) $info_parts[] = $class_info;
                                                                                    if ($department_info) $info_parts[] = $department_info;
                                                                                    if ($id_info) $info_parts[] = $id_info;
                                                                                    echo htmlspecialchars(implode(' | ', $info_parts));
                                                                                ?>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            <?php endforeach; ?>

                                                            <!-- Show total count if more than 3 children -->
                                                            <?php if (count($parent['children']) > 3): ?>
                                                                <div style="font-size: 0.75rem; color: #666; font-style: italic; text-align: center; padding: 0.2rem; background: #e9ecef; border-radius: 4px;">
                                                                    Total: <?php echo count($parent['children']); ?> child<?php echo count($parent['children']) > 1 ? 'ren' : ''; ?> assigned
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span style="color: #999; font-style: italic; font-size: 0.85rem;">No children assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($parent['feedback_count'] > 0): ?>
                                                        <div style="text-align: center;">
                                                            <span class="badge" style="background: #e3f2fd; color: #1976d2; padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.75rem; font-weight: 500;">
                                                                <?php echo $parent['feedback_count']; ?>
                                                            </span>
                                                        </div>
                                                    <?php else: ?>
                                                        <div style="text-align: center;">
                                                            <span style="color: #999; font-size: 0.8rem;">0</span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($parent['is_recent']): ?>
                                                        <span class="badge" style="background: #e8f5e9; color: #2e7d32; padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.75rem; font-weight: 500;">
                                                            <i class="fas fa-star" style="font-size: 0.6rem;"></i> New
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge" style="background: #f5f5f5; color: #666; padding: 0.25rem 0.5rem; border-radius: 12px; font-size: 0.75rem; font-weight: 500;">
                                                            Active
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="action-btns">
                                                        <a href="javascript:void(0)"
                                                           class="btn-icon edit"
                                                           title="Edit <?php echo htmlspecialchars($parent['name']); ?>"
                                                           data-id="<?php echo $parent['id']; ?>"
                                                           data-type="parent"
                                                           data-name="<?php echo htmlspecialchars($parent['name']); ?>"
                                                           data-modal="true">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="javascript:void(0)"
                                                           class="btn-icon delete"
                                                           title="Delete <?php echo htmlspecialchars($parent['name']); ?>"
                                                           data-id="<?php echo $parent['id']; ?>"
                                                           data-type="parent"
                                                           data-name="<?php echo htmlspecialchars($parent['name']); ?>">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                        <?php if ($parent['feedback_count'] > 0): ?>
                                                            <a href="#" class="btn-icon" style="background-color: #2196f3;" title="View Feedback" onclick="viewParentFeedback(<?php echo $parent['id']; ?>)">
                                                                <i class="fas fa-comments"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if (!empty($parent['children']) && count($parent['children']) > 0): ?>
                                                            <a href="#" class="btn-icon" style="background-color: #ff9800;" title="View Children Details" onclick="viewChildrenDetails(<?php echo $parent['id']; ?>)">
                                                                <i class="fas fa-child"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                                <!-- Empty search results message -->
                                <div id="parents-table-empty-search" class="empty-search-results" style="display: none;">
                                    <i class="fas fa-search"></i>
                                    <p>No parents found matching your search.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fas fa-user-slash"></i></div>
                                <div class="empty-text">No parents found</div>
                                <p>Start by adding a new parent using the Add Parent button above.</p>
                                <br>
                                <div style="margin-top: 1rem;">
                                    <a href="parents.php?test=1" class="btn btn-secondary" style="margin-right: 1rem;">
                                        <i class="fas fa-database"></i> Test Database Connection
                                    </a>
                                    <a href="parents.php?debug=1" class="btn btn-secondary">
                                        <i class="fas fa-bug"></i> Enable Debug Mode
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Feedback Tab -->
            <div id="feedback-tab" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-comments"></i> Parent Feedback & Messages</h2>
                        <div style="display: flex; gap: 1rem; align-items: center;">
                            <button onclick="exportFeedbackData()" class="btn export-btn" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.25rem; font-size: 0.9rem; border-radius: 6px; background-color: #f8f9fa; border: 1px solid #dee2e6; color: #495057; transition: all 0.3s ease;">
                                <i class="fas fa-download"></i> Export Feedback
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (count($feedback) > 0): ?>
                            <!-- Search Box for Feedback -->
                            <div class="search-container" data-table="feedback-container">
                                <div class="search-box">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="text" class="search-input" placeholder="Search feedback...">
                                    <button type="button" class="search-clear" onclick="clearFeedbackSearch()">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <br>

                            <div id="feedback-container">
                                <?php foreach ($feedback as $item): ?>
                                    <div class="feedback-item" data-feedback-text="<?php echo htmlspecialchars($item['feedback_text']); ?>" data-parent-name="<?php echo htmlspecialchars($item['parent_name']); ?>">
                                        <div class="feedback-header">
                                            <div class="feedback-meta">
                                                <strong><?php echo htmlspecialchars($item['parent_name']); ?></strong>
                                                <span class="text-muted">(<?php echo htmlspecialchars($item['parent_email']); ?>)</span>
                                                <span class="sentiment-badge sentiment-<?php echo $item['sentiment_label'] ?? 'neutral'; ?>">
                                                    <?php echo ucfirst($item['sentiment_label'] ?? 'neutral'); ?>
                                                </span>
                                            </div>
                                            <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($item['created_at'])); ?></small>
                                        </div>

                                        <div class="feedback-content">
                                            <div class="feedback-text">
                                                <?php echo nl2br(htmlspecialchars($item['feedback_text'])); ?>
                                            </div>

                                            <?php if (!empty($item['replies'])): ?>
                                                <div class="replies-section">
                                                    <h6 style="padding: 0.75rem 1rem; margin: 0; background-color: #e9ecef; font-weight: 600;">
                                                        <i class="fas fa-reply"></i> Admin Replies
                                                    </h6>
                                                    <?php foreach ($item['replies'] as $reply): ?>
                                                        <div class="reply-item">
                                                            <div class="reply-meta">
                                                                <i class="fas fa-user-shield"></i> School Admin • <?php echo date('M d, Y H:i', strtotime($reply['reply_date'])); ?>
                                                            </div>
                                                            <div><?php echo nl2br(htmlspecialchars($reply['reply_message'])); ?></div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Reply Form -->
                                            <div class="reply-form">
                                                <form method="POST" action="parents.php">
                                                    <input type="hidden" name="feedback_id" value="<?php echo $item['id']; ?>">
                                                    <textarea name="reply_message" class="form-control" placeholder="Type your reply to this parent..." required></textarea>
                                                    <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                                                        <button type="submit" name="reply_feedback" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-reply"></i> Send Reply
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Empty search results message -->
                            <div id="feedback-empty-search" class="empty-search-results" style="display: none;">
                                <i class="fas fa-search"></i>
                                <p>No feedback found matching your search.</p>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fas fa-comment-slash"></i></div>
                                <div class="empty-text">No feedback received yet</div>
                                <p>Parent feedback and messages will appear here when submitted.</p>
                                <?php if (count($parents) > 0): ?>
                                    <br>
                                    <a href="parents.php?add_sample_data=1" class="btn btn-secondary">
                                        <i class="fas fa-plus"></i> Add Sample Feedback for Testing
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Parent Modal -->
    <div id="addParentModal" class="modal">
        <div class="modal-content" style="max-width: 600px; border-radius: 12px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #00704a, #2563eb); color: white; padding: 1.5rem 2rem; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; display: flex; align-items: center; gap: 0.5rem; font-size: 1.25rem;"><i class="fas fa-user-plus"></i> Add New Parent</h2>
                <span class="close-modal" onclick="closeModal('addParentModal')" style="color: white; font-size: 1.5rem; cursor: pointer; background: none; border: none;">&times;</span>
            </div>
            <div class="modal-body" style="padding: 2rem; background: white;">
                <?php if (isset($_SESSION['parent_error'])): ?>
                    <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $_SESSION['parent_error']; unset($_SESSION['parent_error']); ?>
                    </div>
                <?php endif; ?>

                <form action="add_parent.php" method="POST" class="modal-form" id="parentForm">
                    <div class="form-section" style="margin-bottom: 2rem;">
                        <div class="section-header" style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #e9ecef;">
                            <div style="width: 40px; height: 40px; background: #00704a; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: white;">
                                <i class="fas fa-user"></i>
                            </div>
                            <h3 style="margin: 0; color: #00704a; font-size: 1.1rem;">Parent Information</h3>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                            <div style="margin-bottom: 1rem;">
                                <label for="parent_name" style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333;">
                                    Full Name <span style="color: #dc3545;">*</span>
                                </label>
                                <input type="text" id="parent_name" name="name" class="form-control"
                                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem;" required>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label for="parent_email" style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333;">
                                    Email Address <span style="color: #dc3545;">*</span>
                                </label>
                                <input type="email" id="parent_email" name="email" class="form-control"
                                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem;" required>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <label for="parent_phone" style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333;">
                                    Phone Number
                                </label>
                                <input type="tel" id="parent_phone" name="phone" class="form-control"
                                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem;">
                            </div>
                            <div style="margin-bottom: 1rem; grid-column: 1 / -1;">
                                <label for="parent_address" style="display: block; margin-bottom: 0.5rem; font-weight: 500; color: #333;">
                                    Address
                                </label>
                                <textarea id="parent_address" name="address" class="form-control" rows="3"
                                          style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px; font-size: 0.95rem; resize: vertical;"></textarea>
                            </div>
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-start; gap: 1rem; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #e9ecef;">
                        <button type="submit" id="saveParentBtn"
                                style="background: #00704a; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; transition: background-color 0.3s ease;"
                                onmouseover="this.style.background='#2563eb'" onmouseout="this.style.background='#00704a'">
                            <i class="fas fa-save"></i> Save Parent
                        </button>
                        <button type="button" onclick="closeModal('addParentModal')"
                                style="background: #6c757d; color: white; border: none; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        /* Modal Styles */
        .modal {
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

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            width: 80%;
            max-width: 800px;
            animation: modalFadeIn 0.3s;
        }

        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-20px);}
            to {opacity: 1; transform: translateY(0);}
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .text-muted {
            color: #6c757d;
        }

        /* Enhanced table styles */
        .data-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
            padding: 1rem 0.75rem;
        }

        .data-table td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f3f4;
        }

        .data-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .badge {
            display: inline-block;
            font-weight: 500;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
        }

        .stat-card {
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        /* Action buttons enhancement */
        .action-btns {
            display: flex;
            gap: 0.25rem;
            justify-content: center;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 0.8rem;
        }

        .btn-icon:hover {
            transform: scale(1.1);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .btn-icon.edit {
            background-color: #00704a;
        }

        .btn-icon.edit:hover {
            background-color: #005a3c;
        }

        .btn-icon.delete {
            background-color: #dc3545;
        }

        .btn-icon.delete:hover {
            background-color: #c82333;
        }

        /* Parent Statistics Cards - Matching Students.php Design */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 1rem;
            margin: 1.5rem 0;
            width: 100%;
            overflow-x: auto;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            opacity: 0.1;
            transition: opacity 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }

        .stat-card:hover::before {
            opacity: 0.15;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 1.5rem;
        }

        .stat-info {
            flex: 1;
        }

        .stat-info h3 {
            font-size: 1.8rem;
            margin: 0;
            font-weight: 600;
        }

        .stat-info p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }

        /* Card variants for parents */
        .all-parents {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border: 1px solid #e9ecef;
        }

        .all-parents .stat-icon {
            color: var(--primary-color);
            background: rgba(0, 112, 74, 0.1);
        }

        .active-parents {
            background: linear-gradient(135deg, #ffffff 0%, #e3f2fd 100%);
            border: 1px solid #bbdefb;
        }

        .active-parents .stat-icon {
            color: #2196f3;
            background: rgba(33, 150, 243, 0.1);
        }

        .recent-parents {
            background: linear-gradient(135deg, #ffffff 0%, #e8f5e9 100%);
            border: 1px solid #c8e6c9;
        }

        .recent-parents .stat-icon {
            color: #4caf50;
            background: rgba(76, 175, 80, 0.1);
        }

        .no-feedback-parents {
            background: linear-gradient(135deg, #ffffff 0%, #fff3e0 100%);
            border: 1px solid #ffe0b2;
        }

        .no-feedback-parents .stat-icon {
            color: #ff9800;
            background: rgba(255, 152, 0, 0.1);
        }

        .contact-parents {
            background: linear-gradient(135deg, #ffffff 0%, #f3e5f5 100%);
            border: 1px solid #e1bee7;
        }

        .contact-parents .stat-icon {
            color: #9c27b0;
            background: rgba(156, 39, 176, 0.1);
        }

        .children-parents {
            background: linear-gradient(135deg, #ffffff 0%, #e8f5e9 100%);
            border: 1px solid #c8e6c9;
        }

        .children-parents .stat-icon {
            color: #4caf50;
            background: rgba(76, 175, 80, 0.1);
        }

        /* Responsive adjustments for parent cards */
        @media (max-width: 1400px) {
            .stats-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <!-- Include search.js for search functionality -->
    <script src="js/search.js"></script>

    <script>
        // Tab switching functionality
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');

            // Add active class to clicked button
            event.target.classList.add('active');
        }

        // Export parent data function
        function exportParentData() {
            const table = document.getElementById('parents-table');
            const rows = table.querySelectorAll('tbody tr:not([style*="display: none"])');

            if (rows.length === 0) {
                alert('No parents to export');
                return;
            }

            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "ID,Name,Email,Phone,Location,Children,Feedback Count,Status\n";

            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const rowData = [
                    cells[0].textContent.trim(), // ID
                    cells[1].textContent.trim().replace(/\s+/g, ' '), // Name
                    cells[2].textContent.trim().replace(/\s+/g, ' '), // Email
                    cells[3].textContent.trim().replace(/\s+/g, ' '), // Phone
                    cells[4].textContent.trim().replace(/\s+/g, ' '), // Location
                    cells[5].textContent.trim().replace(/\s+/g, ' '), // Children
                    cells[6].textContent.trim(), // Feedback count
                    cells[7].textContent.trim() // Status
                ];
                csvContent += rowData.map(field => `"${field}"`).join(',') + '\n';
            });

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', 'parents_list_' + new Date().toISOString().split('T')[0] + '.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Edit parent function
        function editParent(parentId) {
            // For now, show an alert. This can be expanded to open an edit modal
            alert('Edit functionality for parent ID: ' + parentId + '\nThis feature can be implemented with an edit modal similar to the add modal.');
        }

        // View parent feedback function
        function viewParentFeedback(parentId) {
            // Switch to feedback tab and filter by parent
            switchTab('feedback');

            // Wait for tab to switch, then filter
            setTimeout(() => {
                const feedbackSearchInput = document.querySelector('#feedback-tab .search-input');
                if (feedbackSearchInput) {
                    // Find parent name from the table
                    const parentRow = document.querySelector(`a[data-id="${parentId}"]`).closest('tr');
                    const parentName = parentRow.querySelector('td:nth-child(2) div div').textContent;

                    feedbackSearchInput.value = parentName;
                    filterFeedback(parentName);

                    // Scroll to feedback section
                    document.getElementById('feedback-tab').scrollIntoView({ behavior: 'smooth' });
                }
            }, 100);
        }

        // View children details function
        function viewChildrenDetails(parentId) {
            // Find parent row and get children information
            const parentRow = document.querySelector(`a[data-id="${parentId}"]`).closest('tr');
            const parentName = parentRow.querySelector('td:nth-child(2) div div').textContent;
            const childrenCell = parentRow.querySelector('td:nth-child(6)');

            // Create modal content
            let childrenInfo = '';
            const childrenElements = childrenCell.querySelectorAll('div[style*="display: flex"]');

            if (childrenElements.length > 0) {
                childrenInfo = '<ul style="list-style: none; padding: 0;">';
                childrenElements.forEach(child => {
                    const nameElement = child.querySelector('div[style*="font-weight: 500"]');
                    const classElement = child.querySelector('div[style*="font-size: 0.75rem"]');

                    if (nameElement) {
                        childrenInfo += '<li style="margin-bottom: 0.5rem; padding: 0.5rem; background: #f8f9fa; border-radius: 5px;">';
                        childrenInfo += '<strong>' + nameElement.textContent + '</strong>';
                        if (classElement) {
                            childrenInfo += '<br><small style="color: #666;">' + classElement.textContent + '</small>';
                        }
                        childrenInfo += '</li>';
                    }
                });
                childrenInfo += '</ul>';
            } else {
                childrenInfo = '<p style="color: #666; font-style: italic;">No children assigned to this parent.</p>';
            }

            // Show modal with children details
            const modalContent = `
                <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; align-items: center; justify-content: center;" onclick="this.remove()">
                    <div style="background: white; padding: 2rem; border-radius: 8px; max-width: 500px; width: 90%; max-height: 80%; overflow-y: auto;" onclick="event.stopPropagation()">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #eee;">
                            <h3 style="margin: 0; color: #00704a;"><i class="fas fa-child"></i> Children of ${parentName}</h3>
                            <button onclick="this.closest('div[style*=\"position: fixed\"]').remove()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #666;">&times;</button>
                        </div>
                        ${childrenInfo}
                        <div style="margin-top: 1.5rem; text-align: right;">
                            <button onclick="this.closest('div[style*=\"position: fixed\"]').remove()" style="background: #6c757d; color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer;">Close</button>
                        </div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', modalContent);
        }

        // Filter parents based on card selection
        function filterParents(filterType) {
            const table = document.getElementById('parents-table');
            const rows = table.querySelectorAll('tbody tr');
            const emptyMessage = document.getElementById('parents-table-empty-search');
            let visibleCount = 0;

            rows.forEach(row => {
                let shouldShow = false;

                switch(filterType) {
                    case 'all':
                        shouldShow = true;
                        break;
                    case 'with_feedback':
                        // Check if feedback count > 0
                        const feedbackCell = row.querySelector('td:nth-child(5)');
                        shouldShow = feedbackCell && !feedbackCell.textContent.includes('No feedback');
                        break;
                    case 'recent':
                        // Check if status badge contains "New"
                        const statusCell = row.querySelector('td:nth-child(6)');
                        shouldShow = statusCell && statusCell.textContent.includes('New');
                        break;
                    case 'no_feedback':
                        // Check if feedback count is 0
                        const noFeedbackCell = row.querySelector('td:nth-child(5)');
                        shouldShow = noFeedbackCell && noFeedbackCell.textContent.includes('No feedback');
                        break;
                    case 'with_phone':
                        // Check if phone is not "No phone"
                        const phoneCell = row.querySelector('td:nth-child(4)');
                        shouldShow = phoneCell && !phoneCell.textContent.includes('No phone');
                        break;
                    case 'with_children':
                        // Check if children column doesn't contain "No children assigned"
                        const childrenCell = row.querySelector('td:nth-child(6)');
                        shouldShow = childrenCell && !childrenCell.textContent.includes('No children assigned');
                        break;
                }

                if (shouldShow) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Update search input placeholder
            const searchInput = document.querySelector('#parents-tab .search-input');
            if (searchInput) {
                const filterNames = {
                    'all': 'all parents',
                    'with_feedback': 'parents with feedback',
                    'recent': 'recent parents',
                    'no_feedback': 'parents without feedback',
                    'with_phone': 'parents with phone numbers',
                    'with_children': 'parents with children'
                };
                searchInput.placeholder = `Search ${filterNames[filterType]}...`;
            }

            // Show/hide empty message
            if (visibleCount === 0) {
                emptyMessage.style.display = 'block';
                emptyMessage.innerHTML = `
                    <i class="fas fa-search"></i>
                    <p>No parents found in this category.</p>
                `;
            } else {
                emptyMessage.style.display = 'none';
            }

            // Visual feedback for card selection
            document.querySelectorAll('.stat-card').forEach(card => {
                card.style.transform = '';
                card.style.boxShadow = '';
            });

            const activeCard = document.querySelector(`.${filterType.replace('_', '-')}-parents`);
            if (activeCard) {
                activeCard.style.transform = 'translateY(-3px)';
                activeCard.style.boxShadow = '0 8px 16px rgba(0,0,0,0.15)';
            }
        }

        // Export feedback data function
        function exportFeedbackData() {
            const feedbackItems = document.querySelectorAll('.feedback-item:not([style*="display: none"])');

            if (feedbackItems.length === 0) {
                alert('No feedback to export');
                return;
            }

            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Parent Name,Email,Feedback,Sentiment,Date,Replies Count\n";

            feedbackItems.forEach(item => {
                const parentName = item.querySelector('.feedback-meta strong').textContent.trim();
                const email = item.querySelector('.feedback-meta .text-muted').textContent.replace(/[()]/g, '').trim();
                const feedback = item.querySelector('.feedback-text').textContent.trim();
                const sentiment = item.querySelector('.sentiment-badge').textContent.trim();
                const date = item.querySelector('.feedback-header small').textContent.trim();
                const repliesCount = item.querySelectorAll('.reply-item').length;

                const rowData = [parentName, email, feedback, sentiment, date, repliesCount];
                csvContent += rowData.map(field => `"${field}"`).join(',') + '\n';
            });

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement('a');
            link.setAttribute('href', encodedUri);
            link.setAttribute('download', 'parent_feedback.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Feedback search functionality
        function clearFeedbackSearch() {
            const searchInput = document.querySelector('#feedback-tab .search-input');
            searchInput.value = '';
            filterFeedback('');
        }

        function filterFeedback(searchTerm) {
            const feedbackItems = document.querySelectorAll('.feedback-item');
            const emptyMessage = document.getElementById('feedback-empty-search');
            let visibleCount = 0;

            feedbackItems.forEach(item => {
                const feedbackText = item.getAttribute('data-feedback-text').toLowerCase();
                const parentName = item.getAttribute('data-parent-name').toLowerCase();
                const searchLower = searchTerm.toLowerCase();

                if (feedbackText.includes(searchLower) || parentName.includes(searchLower)) {
                    item.style.display = 'block';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            // Show/hide empty message
            if (visibleCount === 0 && searchTerm !== '') {
                emptyMessage.style.display = 'block';
            } else {
                emptyMessage.style.display = 'none';
            }
        }

        // Function to open modal
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }

        // Function to close modal
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        }

        // Confirm delete parent
        function confirmDeleteParent(parentId) {
            if (confirm('Are you sure you want to delete this parent? This action cannot be undone.')) {
                window.location.href = `parents.php?action=delete&id=${parentId}`;
            }
        }

        // Initialize page functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners to delete buttons
            const deleteButtons = document.querySelectorAll('a.btn-icon.delete');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const parentId = this.getAttribute('data-id');
                    confirmDeleteParent(parentId);
                });
            });

            // Add feedback search functionality
            const feedbackSearchInput = document.querySelector('#feedback-tab .search-input');
            if (feedbackSearchInput) {
                feedbackSearchInput.addEventListener('input', function() {
                    filterFeedback(this.value);
                });
            }
        });

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            });
        }
    </script>

    <!-- Universal Modals -->
    <?php include 'includes/universal-modals.php'; ?>
</body>
</html>