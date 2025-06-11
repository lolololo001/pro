<?php
// Initialize the application
require_once 'config/config.php';

// Check if school ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirect('index.php');
}

$schoolId = (int)$_GET['id'];

// Connect to database
$conn = getDbConnection();

// Get school information
$stmt = $conn->prepare("SELECT * FROM schools WHERE id = ? AND status = 'active'");
$stmt->bind_param("i", $schoolId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    redirect('index.php');
}

$school = $result->fetch_assoc();

// Get school statistics
$stats = [
    'total_students' => 0,
    'total_parents' => 0,
    'total_classes' => 0
];

// Get total students
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM students WHERE school_id = ?");
$stmt->bind_param("i", $schoolId);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $stats['total_students'] = $result->fetch_assoc()['count'];
}

// Get total parents (unique parents connected to this school's students)
$stmt = $conn->prepare("SELECT COUNT(DISTINCT p.id) as count FROM parents p 
                        JOIN student_parent sp ON p.id = sp.parent_id 
                        JOIN students s ON sp.student_id = s.id 
                        WHERE s.school_id = ?");
$stmt->bind_param("i", $schoolId);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $stats['total_parents'] = $result->fetch_assoc()['count'];
}

// Get total classes (unique classes in this school)
$stmt = $conn->prepare("SELECT COUNT(DISTINCT class) as count FROM students WHERE school_id = ?");
$stmt->bind_param("i", $schoolId);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $stats['total_classes'] = $result->fetch_assoc()['count'];
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($school['name']); ?> - <?php echo APP_NAME; ?></title>
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
        
        nav ul {
            display: flex;
            list-style: none;
        }
        
        nav ul li {
            margin-left: 1.5rem;
        }
        
        nav ul li a {
            color: var(--light-color);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        nav ul li a:hover {
            color: var(--footer-color);
        }
        
        .auth-buttons a {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .login-btn {
            color: var(--light-color);
            margin-right: 10px;
        }
        
        .register-btn {
            background-color: var(--light-color);
            color: var(--primary-color);
        }
        
        .login-btn:hover {
            color: var(--footer-color);
        }
        
        .register-btn:hover {
            background-color: var(--footer-color);
        }
        
        /* School Header */
        .school-header {
            background: linear-gradient(rgba(0, 112, 74, 0.8), rgba(0, 112, 74, 0.9));
            color: var(--light-color);
            padding: 3rem 0;
            text-align: center;
        }
        
        .school-logo-container {
            width: 120px;
            height: 120px;
            background-color: var(--light-color);
            border-radius: 50%;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        
        .school-logo-container i {
            font-size: 3rem;
            color: var(--primary-color);
        }
        
        .school-header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .school-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 700px;
            margin: 0 auto;
        }
        
        /* School Info Section */
        .school-info-section {
            padding: 3rem 0;
        }
        
        .school-info-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }
        
        .school-details {
            background-color: var(--light-color);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }
        
        .school-details h2 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .info-item {
            display: flex;
            margin-bottom: 1rem;
        }
        
        .info-icon {
            width: 40px;
            height: 40px;
            background-color: var(--footer-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }
        
        .info-icon i {
            color: var(--primary-color);
            font-size: 1.2rem;
        }
        
        .info-content h3 {
            font-size: 1rem;
            margin-bottom: 0.2rem;
            color: #666;
        }
        
        .info-content p {
            font-weight: 500;
        }
        
        .school-stats {
            background-color: var(--light-color);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }
        
        .school-stats h2 {
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .stat-item {
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-weight: 500;
        }
        
        /* CTA Section */
        .school-cta {
            background-color: var(--primary-color);
            color: var(--light-color);
            padding: 3rem 0;
            text-align: center;
            margin-top: 3rem;
        }
        
        .school-cta h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        .school-cta p {
            max-width: 700px;
            margin: 0 auto 2rem;
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .cta-btn {
            display: inline-block;
            background-color: var(--light-color);
            color: var(--primary-color);
            padding: 0.8rem 1.5rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .cta-btn:hover {
            background-color: var(--footer-color);
        }
        
        /* Footer Styles */
        footer {
            background-color: var(--footer-color);
            padding: 3rem 0 1rem;
        }
        
        .footer-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .footer-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
            margin-bottom: 1rem;
            display: inline-block;
        }
        
        .footer-about p {
            margin-bottom: 1rem;
            color: #555;
        }
        
        .social-links {
            display: flex;
            gap: 1rem;
        }
        
        .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: var(--light-color);
            transition: background-color 0.3s;
        }
        
        .social-links a:hover {
            background-color: var(--accent-color);
        }
        
        .footer-links h3 {
            font-size: 1.2rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .footer-links ul {
            list-style: none;
        }
        
        .footer-links ul li {
            margin-bottom: 0.5rem;
        }
        
        .footer-links ul li a {
            color: #555;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-links ul li a:hover {
            color: var(--primary-color);
        }
        
        .footer-contact p {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            color: #555;
        }
        
        .footer-contact p i {
            margin-right: 0.5rem;
            color: var(--primary-color);
        }
        
        .copyright {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
            color: #555;
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                text-align: center;
            }
            
            nav ul {
                margin: 1rem 0;
            }
            
            .school-info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <header>
        <div class="container header-container">
            <a href="index.php" class="logo"><?php echo APP_NAME; ?><span>.</span></a>
            
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="index.php#features">Features</a></li>
                    <li><a href="index.php#about">About</a></li>
                    <li><a href="index.php#contact">Contact</a></li>
                </ul>
            </nav>
            
            <div class="auth-buttons">
                <a href="login.php" class="login-btn">Login</a>
                <a href="register.php" class="register-btn">Register School</a>
            </div>
        </div>
    </header>
    
    <!-- School Header -->
    <section class="school-header">
        <div class="container">
            <div class="school-logo-container">
                <?php if (!empty($school['logo'])): ?>
                    <img src="<?php echo htmlspecialchars($school['logo']); ?>" alt="<?php echo htmlspecialchars($school['name']); ?> Logo" style="width:120px;height:120px;object-fit:cover;border-radius:50%;box-shadow:0 2px 8px rgba(0,0,0,0.10);display:block;margin:0 auto;">
                <?php else: ?>
                    <i class="fas fa-school" style="font-size:120px;color:#ccc;display:block;margin:0 auto;"></i>
                <?php endif; ?>
            </div>
            <h1><?php echo htmlspecialchars($school['name']); ?></h1>
            <?php if (!empty($school['motto'])): ?>
                <p class="school-motto" style="font-style:italic;font-weight:500;margin-bottom:1rem;">
                    "<?php echo htmlspecialchars($school['motto']); ?>"
                </p>
            <?php endif; ?>
            <p><?php echo !empty($school['description']) ? htmlspecialchars($school['description']) : 'A proud member of the SchoolComm network'; ?></p>
        </div>
    </section>
    
    <!-- School Info Section -->
    <section class="school-info-section">
        <div class="container">
            <div class="school-info-grid">
                <div class="school-details">
                    <h2>School Information</h2>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="info-content">
                            <h3>Address</h3>
                            <p><?php echo htmlspecialchars($school['address']); ?></p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="info-content">
                            <h3>Phone</h3>
                            <p><?php echo htmlspecialchars($school['phone']); ?></p>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="info-content">
                            <h3>Email</h3>
                            <p><?php echo htmlspecialchars($school['email']); ?></p>
                        </div>
                    </div>
                    
                    <?php if (!empty($school['website'])): ?>
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-globe"></i>
                        </div>
                        <div class="info-content">
                            <h3>Website</h3>
                            <p><a href="<?php echo htmlspecialchars($school['website']); ?>" target="_blank" style="color: var(--primary-color);"><?php echo htmlspecialchars($school['website']); ?></a></p>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="info-content">
                            <h3>Joined SchoolComm</h3>
                            <p><?php echo date('F Y', strtotime($school['registration_date'])); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="school-stats">
                    <h2>School Statistics</h2>
                    
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['total_students']; ?></div>
                        <div class="stat-label">Students</div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['total_parents']; ?></div>
                        <div class="stat-label">Depart</div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['total_classes']; ?></div>
                        <div class="stat-label">Classes</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- CTA Section -->
    <section class="school-cta">
        <div class="container">
            <h2>Connect with <?php echo htmlspecialchars($school['name']); ?></h2>
            <p>Are you a parent with a child at this school? Register or log in to stay connected with your child's progress and school activities.</p>
            <a href="parent-register.php?school_id=<?php echo $school['id']; ?>" class="cta-btn">Register as Parent</a>
        </div>
    </section>
    
    <!-- Footer Section -->
    <footer>
        <div class="container">
            <div class="footer-container">
                <div class="footer-about">
                    <a href="index.php" class="footer-logo"><?php echo APP_NAME; ?></a>
                    <p>A smart communication system designed to improve interactions between secondary boarding school staff and parents.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="footer-links">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="index.php#features">Features</a></li>
                        <li><a href="index.php#about">About Us</a></li>
                        <li><a href="index.php#contact">Contact</a></li>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Register</a></li>
                    </ul>
                </div>
                
                <div class="footer-links">
                    <h3>For Schools</h3>
                    <ul>
                        <li><a href="#">How It Works</a></li>
                        <li><a href="#">Pricing</a></li>
                        <li><a href="#">Testimonials</a></li>
                        <li><a href="#">FAQ</a></li>
                        <li><a href="#">Support</a></li>
                    </ul>
                </div>
                
                <div class="footer-contact">
                    <h3>Contact Us</h3>
                    <p><i class="fas fa-map-marker-alt"></i> 123 Education Street, City</p>
                    <p><i class="fas fa-phone"></i> +1 234 567 890</p>
                    <p><i class="fas fa-envelope"></i> info@schoolcomm.com</p>
                </div>
            </div>
            
            <div class="copyright">
                <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>