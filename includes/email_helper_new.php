<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
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
        $mail->SMTPDebug = 0; // Disable debug output
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'schoolcomm001@gmail.com';
        $mail->Password = 'nuos orzj keap bszp'; // Use environment variables in production
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

        //Set email priority
        $mail->Priority = 1;
        $mail->addCustomHeader('X-MSMail-Priority', 'High');
        $mail->addCustomHeader('Importance', 'High');

        //Recipients
        $mail->setFrom('schoolcomm001@gmail.com', $school_info['name'] ?? 'SchoolComm');
        $mail->addAddress($parent_email, $parent_name);

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
        $mail->AltBody = strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $body));

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

function sendFeedbackConfirmationEmail($parentEmail, $parentName, $feedbackType, $subject) {
    if (empty($parentEmail)) {
        error_log("Feedback email sending skipped: No parent email provided");
        return ['success' => false, 'message' => 'No email address provided'];
    }

    try {
        error_log("Attempting to send feedback confirmation email to: " . $parentEmail);

        require_once __DIR__ . '/../vendor/autoload.php';
        $mail = new PHPMailer(true);

        // Server settings with enhanced error logging
        $mail->SMTPDebug = 3; // Enable even more verbose debug output
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer debug ($level): $str");
        };
        
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'schoolcomm001@gmail.com';
        $mail->Password = 'nuos orzj keap bszp';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;
        
        // Connection options for better reliability
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Set email priority
        $mail->Priority = 1;
        $mail->addCustomHeader('X-MSMail-Priority', 'High');
        $mail->addCustomHeader('Importance', 'High');

        // Recipients
        $mail->setFrom('schoolcomm001@gmail.com', 'SchoolComm');
        $mail->addAddress($parentEmail, $parentName);

        // Email content
        $emailBody = "
            <html>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <div style='text-align: center; margin-bottom: 30px;'>
                        <h2 style='color: #007bff;'>Feedback Confirmation</h2>
                    </div>
                    
                    <p><strong>Dear {$parentName},</strong></p>
                    
                    <p>Thank you for submitting your feedback to SchoolComm. We appreciate your input and will carefully review your comments.</p>
                    
                    <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <p><strong>Feedback Details:</strong></p>
                        <ul style='list-style: none; padding-left: 0;'>
                            <li><strong>Type:</strong> {$feedbackType}</li>
                            <li><strong>Subject:</strong> {$subject}</li>
                            <li><strong>Date:</strong> " . date('F j, Y, g:i a') . "</li>
                        </ul>
                    </div>
                    
                    <p>We will process your feedback and take appropriate action if necessary. If your feedback requires a response, our team will contact you soon.</p>
                    
                    <p>Best regards,<br>SchoolComm Team</p>
                    
                    <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666;'>
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>";

        $mail->isHTML(true);
        $mail->Subject = 'Feedback Received - SchoolComm';
        $mail->Body = $emailBody;
        $mail->AltBody = strip_tags($emailBody);

        error_log("Attempting to send email to: $parentEmail");
        $mail->send();
        error_log("Feedback confirmation email sent successfully to: " . $parentEmail);
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        $error_message = "Failed to send feedback confirmation email: " . $e->getMessage();
        error_log($error_message);
        if (isset($mail)) {
            error_log("Mail Error Info: " . print_r($mail->ErrorInfo, true));
        }
        return ['success' => false, 'message' => $error_message];
    }
}
