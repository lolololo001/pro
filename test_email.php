<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

try {
    $mail = new PHPMailer(true);

    // Enable verbose debug output
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    $mail->Debugoutput = function($str, $level) {
        error_log("PHPMailer Debug: $str");
        echo "Debug: $str<br>";
    };

    // Server settings
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'schoolcomm001@gmail.com';
    $mail->Password = 'nuos orzj keap bszp';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;

    // SSL/TLS settings
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );

    // Set timeout
    $mail->Timeout = 30;

    // Recipients
    $mail->setFrom('schoolcomm001@gmail.com', 'SchoolComm Test');
    $mail->addAddress('schoolcomm001@gmail.com', 'Test User'); // Add your test email here

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Test Email from SchoolComm';
    $mail->Body = '<h1>Test Email</h1><p>This is a test email from SchoolComm system. If you receive this, the email functionality is working.</p>';
    $mail->AltBody = 'This is a test email from SchoolComm system. If you receive this, the email functionality is working.';

    echo "Attempting to send email...<br>";
    $mail->send();
    echo "Message has been sent successfully!<br>";
    
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}<br>";
    error_log("Email Error: " . $e->getMessage());
}
