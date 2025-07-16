<?php
/**
 * Simple email helper for student registration
 */

function sendStudentRegistrationEmail($parent_email, $parent_name, $student_data, $school_info) {
    // Return early if no email provided
    if (empty($parent_email)) {
        error_log("Email sending skipped: No parent email provided");
        return false;
    }

    try {
        // Log the attempt
        error_log("Attempting to send registration email to: " . $parent_email);

        // Check if PHPMailer is available
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            // Try to load autoloader
            if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
                require_once __DIR__ . '/../vendor/autoload.php';
            } else {
                error_log("PHPMailer not available, using PHP mail() function");
                return sendSimpleEmail($parent_email, $parent_name, $student_data, $school_info);
            }
        }

        // Use PHPMailer
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'schoolcomm001@gmail.com';
        $mail->Password = 'nuos orzj keap bszp';
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        // Recipients
        $mail->setFrom('schoolcomm001@gmail.com', $school_info['name'] ?? 'SchoolComm');
        $mail->addAddress($parent_email, $parent_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Student Registration Confirmation - ' . $student_data['first_name'] . ' ' . $student_data['last_name'];
        
        $body = createEmailBody($parent_name, $student_data, $school_info);
        $mail->Body = $body;
        $mail->AltBody = createTextEmailBody($parent_name, $student_data, $school_info);

        $mail->send();
        error_log("Registration email sent successfully to: " . $parent_email);
        return true;
        
    } catch (Exception $e) {
        error_log("PHPMailer failed: " . $e->getMessage());
        // Fallback to simple mail
        return sendSimpleEmail($parent_email, $parent_name, $student_data, $school_info);
    }
}

function sendSimpleEmail($parent_email, $parent_name, $student_data, $school_info) {
    try {
        $subject = 'Student Registration Confirmation - ' . $student_data['first_name'] . ' ' . $student_data['last_name'];
        $message = createTextEmailBody($parent_name, $student_data, $school_info);
        
        $headers = "From: " . ($school_info['name'] ?? 'School') . " <schoolcomm001@gmail.com>\r\n";
        $headers .= "Reply-To: schoolcomm001@gmail.com\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        $result = mail($parent_email, $subject, $message, $headers);
        
        if ($result) {
            error_log("Simple email sent successfully to: " . $parent_email);
            return true;
        } else {
            error_log("Simple email failed to: " . $parent_email);
            return false;
        }
    } catch (Exception $e) {
        error_log("Simple email exception: " . $e->getMessage());
        return false;
    }
}

function createEmailBody($parent_name, $student_data, $school_info) {
    return "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #00704a;'>Student Registration Confirmation</h2>
                <p><strong>Dear " . htmlspecialchars($parent_name) . ",</strong></p>
                <p>Thank you for registering your child at " . htmlspecialchars($school_info['name'] ?? 'our school') . ".</p>
                
                <div style='background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                    <h3 style='color: #00704a; margin-top: 0;'>Student Information:</h3>
                    <p><strong>Student Name:</strong> " . htmlspecialchars($student_data['first_name'] . ' ' . $student_data['last_name']) . "</p>
                    <p><strong>Registration Number:</strong> <span style='background: #ffeb3b; padding: 2px 6px; border-radius: 3px; font-weight: bold;'>" . htmlspecialchars($student_data['reg_number']) . "</span></p>
                    <p><strong>Class:</strong> " . htmlspecialchars($student_data['class_name']) . "</p>
                    " . (!empty($student_data['department_name']) && $student_data['department_name'] !== 'Not assigned' ? "<p><strong>Department:</strong> " . htmlspecialchars($student_data['department_name']) . "</p>" : "") . "
                </div>
                
                <p>Please keep this registration number for future reference. You can use it to:</p>
                <ul>
                    <li>Access your child's academic records</li>
                    <li>Make fee payments</li>
                    <li>Communicate with teachers</li>
                    <li>Track your child's progress</li>
                </ul>
                
                <p>For any queries, please contact us at:</p>
                <p>Phone: " . htmlspecialchars($school_info['phone'] ?? 'N/A') . "<br>
                   Email: " . htmlspecialchars($school_info['email'] ?? 'N/A') . "</p>
                
                <p style='margin-top: 30px;'>Best regards,<br>
                " . htmlspecialchars($school_info['name'] ?? 'School') . " Administration</p>
            </div>
        </body>
        </html>
    ";
}

function createTextEmailBody($parent_name, $student_data, $school_info) {
    $text = "Dear " . $parent_name . ",\n\n";
    $text .= "Thank you for registering your child at " . ($school_info['name'] ?? 'our school') . ".\n\n";
    $text .= "STUDENT INFORMATION:\n";
    $text .= "Student Name: " . $student_data['first_name'] . ' ' . $student_data['last_name'] . "\n";
    $text .= "Registration Number: " . $student_data['reg_number'] . "\n";
    $text .= "Class: " . $student_data['class_name'] . "\n";
    if (!empty($student_data['department_name']) && $student_data['department_name'] !== 'Not assigned') {
        $text .= "Department: " . $student_data['department_name'] . "\n";
    }
    $text .= "\nPlease keep this registration number for future reference.\n";
    $text .= "You can use it to:\n";
    $text .= "- Access your child's academic records\n";
    $text .= "- Make fee payments\n";
    $text .= "- Communicate with teachers\n";
    $text .= "- Track your child's progress\n\n";
    $text .= "For any queries, contact us at:\n";
    $text .= "Phone: " . ($school_info['phone'] ?? 'N/A') . "\n";
    $text .= "Email: " . ($school_info['email'] ?? 'N/A') . "\n\n";
    $text .= "Best regards,\n";
    $text .= ($school_info['name'] ?? 'School') . " Administration";
    
    return $text;
}
?>
