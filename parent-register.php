<?php
// Initialize the application
require_once 'config/config.php';

// Initialize variables
$error = '';
$success = '';
$schoolId = isset($_GET['school_id']) ? (int)$_GET['school_id'] : 0;
$schoolName = '';

// If school ID is provided, get school name
if ($schoolId > 0) {
    $conn = getDbConnection();
    $stmt = $conn->prepare("SELECT name FROM schools WHERE id = ? AND status = 'active'");
    $stmt->bind_param("i", $schoolId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $schoolName = $result->fetch_assoc()['name'];
    } else {
        $schoolId = 0; // Reset if invalid school
    }
    $conn->close();
}

$formData = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'relationship' => 'parent',
    'address' => '',
    'school_id' => $schoolId
];

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $formData = [
        'first_name' => sanitize($_POST['first_name']),
        'last_name' => sanitize($_POST['last_name']),
        'email' => sanitize($_POST['email']),
        'phone' => sanitize($_POST['phone']),
        'relationship' => sanitize($_POST['relationship']),
        'address' => sanitize($_POST['address'] ?? ''),
        'school_id' => isset($_POST['school_id']) ? (int)$_POST['school_id'] : 0
    ];
    
    // Validate input
    if (empty($formData['first_name']) || empty($formData['last_name']) || 
        empty($formData['email']) || empty($formData['phone']) || 
        empty($formData['relationship']) || empty($_POST['password']) || 
        empty($_POST['confirm_password'])) {
        $error = 'Please fill in all required fields';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif ($_POST['password'] !== $_POST['confirm_password']) {
        $error = 'Passwords do not match';
    } else {
        // Connect to database
        $conn = getDbConnection();
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM parents WHERE email = ?");
        $stmt->bind_param("s", $formData['email']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'A parent with this email already exists';
        } else {
            // Hash password
            $hashedPassword = hashPassword($_POST['password']);
            
            // Generate username from name
            $username = strtolower($formData['first_name'] . '.' . $formData['last_name']);
            
            // Insert parent
            $stmt = $conn->prepare("INSERT INTO parents (username, password, email, first_name, last_name, phone, address, relationship) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssss", $username, $hashedPassword, $formData['email'], $formData['first_name'], $formData['last_name'], $formData['phone'], $formData['address'], $formData['relationship']);
              if ($stmt->execute()) {
                $parentId = $stmt->insert_id;
                
                // If school_id is provided, link parent to school
                if ($formData['school_id'] > 0) {
                    // Log the connection between parent and school for future student linking
                    $stmt = $conn->prepare("INSERT INTO parent_school_interest (parent_id, school_id, created_at) VALUES (?, ?, NOW())");
                    $stmt->bind_param("ii", $parentId, $formData['school_id']);
                    $stmt->execute();
                }

                // Send welcome email
                try {
                    require 'vendor/autoload.php';
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

                    // Server settings
                    $mail->SMTPDebug = 0; // Disable debug output
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'schoolcomm001@gmail.com';
                    $mail->Password = 'nuos orzj keap bszp';
                    $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port = 465;

                    // Set high priority
                    $mail->Priority = 1;
                    $mail->addCustomHeader('X-MSMail-Priority', 'High');
                    $mail->addCustomHeader('Importance', 'High');

                    // Recipients
                    $mail->setFrom('schoolcomm001@gmail.com', 'SchoolComm');
                    $mail->addAddress($formData['email'], $formData['first_name'] . ' ' . $formData['last_name']);

                    // Email content
                    $welcomeBody = "
                        <html>
                        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                            <p><strong>Dear {$formData['first_name']},</strong></p>
                            <p>Welcome to SchoolComm! Your parent account has been successfully created.</p>
                            <div style='background: #f5f5f5; padding: 15px; margin: 15px 0; border-left: 4px solid #00704a;'>
                                <p><strong>Your Login Information:</strong></p>
                                <ul>
                                    <li>Username: {$username}</li>
                                    <li>Email: {$formData['email']}</li>
                                </ul>
                            </div>
                            " . ($formData['school_id'] > 0 ? "<p>You are now connected with " . htmlspecialchars($schoolName) . ".</p>" : "") . "
                            <p>You can now log in to your account to:</p>
                            <ul>
                                <li>Link your children to your account</li>
                                <li>View academic progress</li>
                                <li>Communicate with teachers</li>
                                <li>Manage permissions and more</li>
                            </ul>
                            <p style='margin-top: 20px;'><a href='" . APP_URL . "/login.php' style='background: #00704a; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login to Your Account</a></p>
                            <br>
                            <p>Best regards,<br>SchoolComm Team</p>
                        </body>
                        </html>
                    ";

                    $mail->isHTML(true);
                    $mail->Subject = 'Welcome to SchoolComm - Parent Account Created';
                    $mail->Body = $welcomeBody;
                    $mail->AltBody = strip_tags($welcomeBody);

                    $mail->send();
                    
                    // Set success message based on school connection
                    if ($formData['school_id'] > 0) {
                        $success = 'Registration successful! You can now log in to connect with ' . htmlspecialchars($schoolName) . 
                                 '. Please check your email for login information.';
                    } else {
                        $success = 'Registration successful! You can now log in to your account. Please check your email for login information.';
                    }
                } catch (Exception $e) {
                    error_log("Failed to send welcome email to parent: " . $e->getMessage());
                    // Still show success but without email reference
                    if ($formData['school_id'] > 0) {
                        $success = 'Registration successful! You can now log in to connect with ' . htmlspecialchars($schoolName) . '.';
                    } else {
                        $success = 'Registration successful! You can now log in to your account.';
                    }
                }
                
                // Clear form data
                $formData = [
                    'first_name' => '',
                    'last_name' => '',
                    'email' => '',
                    'phone' => '',
                    'relationship' => 'parent',
                    'address' => '',
                    'school_id' => 0
                ];
            } else {
                $error = 'Registration failed: ' . $stmt->error;
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
    <title>Parent Registration - <?php echo APP_NAME; ?></title>
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
            max-width: 600px;
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
            width: 100%;
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
            <a href="index.php" class="logo"><?php echo APP_NAME; ?><span>.</span></a>
        </div>
    </header>
    
    <!-- Main Content -->
    <main>
        <div class="container">
            <div class="register-container">
                <div class="register-header">
                    <h1>Parent Registration</h1>
                    <?php if (!empty($schoolName)): ?>
                        <p>Create an account to stay connected with <?php echo htmlspecialchars($schoolName); ?></p>
                    <?php else: ?>
                        <p>Create an account to stay connected with your child's school</p>
                    <?php endif; ?>
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
                    
                    <form action="parent-register.php<?php echo $schoolId ? '?school_id=' . $schoolId : ''; ?>" method="POST">
                        <?php if ($schoolId): ?>
                            <input type="hidden" name="school_id" value="<?php echo $schoolId; ?>">
                        <?php endif; ?>
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="first_name">First Name*</label>
                                <input type="text" name="first_name" id="first_name" class="form-control" value="<?php echo htmlspecialchars($formData['first_name']); ?>" required>
                            </div>
                            
                            <div class="form-group half">
                                <label for="last_name">Last Name*</label>
                                <input type="text" name="last_name" id="last_name" class="form-control" value="<?php echo htmlspecialchars($formData['last_name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="email">Email Address*</label>
                                <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($formData['email']); ?>" required>
                            </div>
                            
                            <div class="form-group half">
                                <label for="phone">Phone Number*</label>
                                <input type="tel" name="phone" id="phone" class="form-control" value="<?php echo htmlspecialchars($formData['phone']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="relationship">Relationship to Student*</label>
                            <select name="relationship" id="relationship" class="form-control" required>
                                <option value="father" <?php echo ($formData['relationship'] === 'father') ? 'selected' : ''; ?>>Father</option>
                                <option value="mother" <?php echo ($formData['relationship'] === 'mother') ? 'selected' : ''; ?>>Mother</option>
                                <option value="guardian" <?php echo ($formData['relationship'] === 'guardian') ? 'selected' : ''; ?>>Guardian</option>
                                <option value="other" <?php echo ($formData['relationship'] === 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea name="address" id="address" class="form-control" rows="3"><?php echo htmlspecialchars($formData['address']); ?></textarea>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="password">Password*</label>
                                <input type="password" name="password" id="password" class="form-control" required>
                            </div>
                            
                            <div class="form-group half">
                                <label for="confirm_password">Confirm Password*</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Register</button>
                        
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