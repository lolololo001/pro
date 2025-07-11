<?php
//Import PHPMailer classes into the global namespace
//These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
// Initialize the application
require_once 'config/config.php';

// Initialize variables
$error = '';
$success = '';
$formData = [
    'school_name' => '',
    'address' => '',
    'phone' => '',
    'email' => '',
    'website' => '',
    'admin_full_name' => '',
    'admin_email' => '',
    'admin_phone' => ''
];

// Directory to store uploaded logos
$logoDir = 'logos/';

// Ensure the directory exists
if (!is_dir($logoDir)) {
    mkdir($logoDir, 0755, true);
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $formData = [
        'school_name' => sanitize($_POST['school_name']),
        'address' => sanitize($_POST['address']),
        'phone' => sanitize($_POST['phone']),
        'email' => sanitize($_POST['email']),
        'website' => sanitize($_POST['website'] ?? ''),
        'admin_full_name' => sanitize($_POST['admin_full_name']),
        'admin_email' => sanitize($_POST['admin_email']),
        'admin_phone' => sanitize($_POST['admin_phone'])
    ];
    
    // School logo will be managed in the admin dashboard
    $logoPath = null;

    // Validate input
    if (empty($formData['school_name']) || empty($formData['address']) || 
        empty($formData['phone']) || empty($formData['email']) || 
        empty($formData['admin_full_name']) || empty($formData['admin_email']) || 
        empty($formData['admin_phone']) || empty($_POST['admin_password']) || 
        empty($_POST['confirm_password'])) {
        $error = 'Please fill in all required fields';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid school email address';
    } elseif (!filter_var($formData['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid admin email address';
    } elseif ($_POST['admin_password'] !== $_POST['confirm_password']) {
        $error = 'Passwords do not match';
    } else {
        // Connect to database
        $conn = getDbConnection();
        
        // Check if school email already exists
        $stmt = $conn->prepare("SELECT id FROM schools WHERE email = ?");
        $stmt->bind_param("s", $formData['email']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'A school with this email already exists';
        } else {
            // Check if admin email already exists
            $stmt = $conn->prepare("SELECT id FROM school_admins WHERE email = ?");
            $stmt->bind_param("s", $formData['admin_email']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = 'An admin with this email already exists';
            } else {
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    // Insert school with logo
                    $stmt = $conn->prepare("INSERT INTO schools (name, address, phone, email, website, school_logo, status, description) VALUES (?, ?, ?, ?, ?, ?, 'pending', '')");
                    $stmt->bind_param("ssssss", $formData['school_name'], $formData['address'], $formData['phone'], $formData['email'], $formData['website'], $logoPath);
                    $stmt->execute();
                    $schoolId = $conn->insert_id;
                    
                    // Hash password
                    $hashedPassword = hashPassword($_POST['admin_password']);
                    
                    // Generate username from admin name
                    $username = strtolower(str_replace(' ', '.', $formData['admin_full_name']));
                    
                    // Insert school admin
                    $stmt = $conn->prepare("INSERT INTO school_admins (school_id, username, password, email, full_name, phone) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isssss", $schoolId, $username, $hashedPassword, $formData['admin_email'], $formData['admin_full_name'], $formData['admin_phone']);
                    $stmt->execute();
                    
        if ($stmt->execute())
            {
                //Load Composer's autoloader (created by composer, not included with PHPMailer)
                require 'vendor/autoload.php';

                //Create an instance; passing `true` enables exceptions
                $mail = new PHPMailer(true);

                try {
                    //Server settings
                    $mail->SMTPDebug = 1;
                    $mail->SMTPDebug = 0; // 👈 Completely silent (recommended for live site)
                     //Enable verbose debug output
                    $mail->isSMTP();                                            //Send using SMTP
                    $mail->Host       = 'smtp.gmail.com';                     //Set the SMTP server to send through
                    $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
                    $mail->Username = 'schoolcomm001@gmail.com';                  //SMTP username
                    $mail->Password   = 'nuos orzj keap bszp';                               //SMTP password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
                    $mail->Port       = 465;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
                    $mail->addCustomHeader('X-Priority', '1'); // Highest
                    $mail->addCustomHeader('X-MSMail-Priority', 'High');
                    $mail->addCustomHeader('Importance', 'High');

                    //Recipients
                    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
                    $name = htmlspecialchars(trim($_POST['name']));

                    $mail->setFrom('schoolcomm001@gmail.com', 'SchoolComm');

                    $mail->addAddress($email, $admin_email);    //Add a recipient             //Name is optional
                   $body = "
                        <html>
                        <body>
                            <p><strong>Hello Admin,</strong></p>
                            <p>Thank you for being part of SchoolComm.</p>
                            <p>Your school is well registered, you'll be admitted ASAP</p>
                            <br>
                            <p>Regards,<br>SchoolComm Team</p>
                        </body>
                        </html>
                    ";
                    //Content
                    $mail->isHTML(true);                                  //Set email format to HTML
                    $mail->Subject = 'Thanks for Messaging us';
                    $mail->Body    = $body;
                    $mail->AltBody =strip_tags($body);                   
                    $mail->send();
                    $response['success'] = true;
                    $response['message'] = 'Your message has been sent successfully!';
                    if (!$isAjax) {
                        $_SESSION['success_message'] = 'Your message has been sent successfully!';
                        header('Location: register.php?contact=success');
                        exit();
                    }
                    echo json_encode($response);
                    exit();
                } 
                catch (Exception $e){
                    $response['success'] = false;
                    $response['message'] = "Failed to send email. Please try again.";
                    if (!$isAjax) {
                        header('Location: register.php?error=1&message=' . urlencode($response['message']));
                        exit();
                    }
                    echo json_encode($response);
                    exit();
                }
        } 
        else {
            throw new Exception("Failed to registering school. Please try again.");
        }





                    // Commit transaction
                    $conn->commit();
                    
                    // Set success message
                    $success = 'School registration successful! Your account is pending approval by the system administrator.';
                    header('Location: login.php'); // Redirect to login page
                    exit();
                  
                } catch (Exception $e) {
                    // Rollback transaction on error
                    if ($logoPath) {
                        unlink($logoPath); // Remove uploaded logo on failure
                    }
                    $conn->rollback();
                    $error = 'Registration failed: ' . $e->getMessage();
                }
            }
        }
        
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register School - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: <?php echo PRIMARY_COLOR; ?>;
            --footer-color: <?php echo FOOTER_COLOR; ?>;
            --accent-color: <?php echo ACCENT_COLOR; ?>;
            --light-color: #ffffff;
            --dark-color: #333333;
            --gray-color: #f5f5f5;
            --border-color: #e0e0e0;
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
            flex-direction: column;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header Styles */
        header {
            background-color: var(--primary-color);
            color: var(--light-color);
            padding: 1rem 0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--light-color);
            text-decoration: none;
        }
        
        .logo span {
            color: var(--footer-color);
        }
        
        /* Main Content Styles */
        main {
            flex: 1;
            padding: 3rem 0;
        }
        
        .register-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: var(--light-color);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .register-header {
            background-color: var(--primary-color);
            color: var(--light-color);
            padding: 1.5rem;
            text-align: center;
        }
        
        .register-header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .register-header p {
            opacity: 0.9;
        }
        
        .register-form {
            padding: 2rem;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -10px;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            padding: 0 10px;
            flex: 1 0 100%;
        }
        
        .form-group.half {
            flex: 1 0 50%;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 0.8rem 1rem;
            font-size: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .section-title {
            font-size: 1.2rem;
            color: var(--primary-color);
            margin: 1.5rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .btn {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            text-align: center;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: var(--light-color);
        }
        
        .btn-primary:hover {
            background-color: var(--accent-color);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
    
        
        .form-footer {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }
        
        .form-footer a {
            color: var(--primary-color);
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .form-footer a:hover {
            color: var(--accent-color);
        }
        
        /* Footer Styles */
        footer {
            background-color: var(--footer-color);
            padding: 1.5rem 0;
            text-align: center;
            margin-top: auto;
        }
        
        .footer-text {
            color: var(--primary-color);
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                text-align: center;
            }
            
            .form-group.half {
                flex: 1 0 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <header>
        <div class="container header-container">
            <a href="index.php" class="logo"><?php echo APP_NAME; ?></a>
        </div>
    </header>
    
    <!-- Main Content -->
    <main>
        <div class="container">
            <div class="register-container">
                <div class="register-header">
                    <h1>Register Your School</h1>
                    <p>Join the SchoolComm platform to enhance communication with parents</p>
                </div>
                
                <div class="register-form">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success">
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form action="register.php" method="POST" enctype="multipart/form-data">
                        <h2 class="section-title">School Information</h2>
                        
                        <div class="form-group">
                            <label for="school_name">School Name*</label>
                            <input type="text" name="school_name" id="school_name" class="form-control" value="<?php echo htmlspecialchars($formData['school_name']); ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="email">School Email Address*</label>
                                <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($formData['email']); ?>" required>
                            </div>
                            
                            <div class="form-group half">
                                <label for="phone">School Phone Number*</label>
                                <input type="tel" name="phone" id="phone" class="form-control" value="<?php echo htmlspecialchars($formData['phone']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">School Address*</label>
                            <textarea name="address" id="address" class="form-control" rows="3" required><?php echo htmlspecialchars($formData['address']); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="website">School Website (Optional)</label>
                            <input type="text" name="website" id="website" class="form-control" value="<?php echo htmlspecialchars($formData['website']); ?>" placeholder="https://">
                        </div>
                        
                        <h2 class="section-title">Administrator Account</h2>
                        <p>Create an administrator account to manage your school on SchoolComm</p>
                        
                        <div class="form-group">
                            <label for="admin_full_name">Full Name*</label>
                            <input type="text" name="admin_full_name" id="admin_full_name" class="form-control" value="<?php echo htmlspecialchars($formData['admin_full_name']); ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="admin_email">Email Address*</label>
                                <input type="email" name="admin_email" id="admin_email" class="form-control" value="<?php echo htmlspecialchars($formData['admin_email']); ?>" required>
                            </div>
                            
                            <div class="form-group half">
                                <label for="admin_phone">Phone Number*</label>
                                <input type="tel" name="admin_phone" id="admin_phone" class="form-control" value="<?php echo htmlspecialchars($formData['admin_phone']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="admin_password">Password*</label>
                                <input type="password" name="admin_password" id="admin_password" class="form-control" required>
                            </div>
                            
                            <div class="form-group half">
                                <label for="confirm_password">Confirm Password*</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Register School</button>
                        
                        <div class="form-footer">
                            <p>Already have an account? <a href="login.php">Log In</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Footer Section -->
    <footer>
        <div class="container">
            <p class="footer-text">&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All Rights Reserved.</p>
        </div>
    </footer>
</body>
</html>