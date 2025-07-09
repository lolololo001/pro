<?php
// Email helper functions
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendStudentRegistrationEmail($parent_email, $parent_name, $student_data, $school_info) {
    // Return early if no email provided
    if (empty($parent_email)) {
        error_log("Email sending skipped: No parent email provided");
        return false;
    }

    try {
        // Log the attempt
        error_log("Attempting to send registration email to: " . $parent_email);

        //Load Composer's autoloader
        require_once __DIR__ . '/../vendor/autoload.php';

        //Create an instance; passing `true` enables exceptions
        $mail = new PHPMailer(true);

        //Server settings
        $mail->SMTPDebug = 2; // Enable debug output for troubleshooting
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'schoolcomm001@gmail.com'; // Must match the Gmail account
        $mail->Password = 'nuos orzj keap bszp'; // App password provided by user
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        //Set email priority
        $mail->Priority = 1;
        $mail->addCustomHeader('X-MSMail-Priority', 'High');
        $mail->addCustomHeader('Importance', 'High');

        //Recipients
        $mail->setFrom('schoolcomm001@gmail.com', $school_info['name'] ?? 'SchoolComm');
        $mail->addAddress($parent_email, $parent_name);
        $mail->addReplyTo('schoolcomm001@gmail.com', $school_info['name'] ?? 'SchoolComm');

        // Log email parameters
        error_log("Email Parameters - From: schoolcomm001@gmail.com, To: $parent_email, Student: {$student_data['first_name']} {$student_data['last_name']}, Reg: {$student_data['reg_number']}");

        // Create HTML body with student registration details
        $body = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #00704a;'>Student Registration Confirmation</h2>
                    <p><strong>Dear {$parent_name},</strong></p>
                    <p>Thank you for registering your child at " . htmlspecialchars($school_info['name'] ?? 'our school') . ".</p>
                    
                    <div style='background-color: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <h3 style='color: #00704a; margin-top: 0;'>Student Information:</h3>
                        <p><strong>Student Name:</strong> {$student_data['first_name']} {$student_data['last_name']}</p>
                        <p><strong>Registration Number:</strong> {$student_data['reg_number']}</p>
                        <p><strong>Class:</strong> {$student_data['class_name']}</p>
                        " . (!empty($student_data['department_name']) ? "<p><strong>Department:</strong> {$student_data['department_name']}</p>" : "") . "
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

        //Content
        $mail->isHTML(true);
        $mail->Subject = 'Student Registration Confirmation - ' . $student_data['first_name'] . ' ' . $student_data['last_name'];
        $mail->Body = $body;
        $mail->AltBody = "Dear {$parent_name},\n\n" .
            "Thank you for registering your child at " . ($school_info['name'] ?? 'our school') . ".\n" .
            "Student Name: {$student_data['first_name']} {$student_data['last_name']}\n" .
            "Registration Number: {$student_data['reg_number']}\n" .
            "Class: {$student_data['class_name']}\n" .
            (!empty($student_data['department_name']) ? ("Department: {$student_data['department_name']}\n") : "") .
            "\nPlease keep this registration number for future reference.\n" .
            "For any queries, contact us at: Phone: " . ($school_info['phone'] ?? 'N/A') . ", Email: " . ($school_info['email'] ?? 'N/A') . "\n\n" .
            "Best regards,\n" . ($school_info['name'] ?? 'School') . " Administration";

        $mail->send();
        error_log("Registration email sent successfully to: " . $parent_email);
        return true;
    } catch (Exception $e) {
        $error_message = "Failed to send registration email: " . $e->getMessage();
        error_log($error_message);
        error_log("Mail Error Info: " . print_r($mail->ErrorInfo, true));
        return false;
    }
}
