 <?php
// Initialize the application
require_once 'config/config.php';

// Process school search if submitted
$searchResults = [];
$searchError = '';
$searchQuery = '';

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchQuery = sanitize($_GET['search']);
    $searchResults = findSchoolByName($searchQuery);
    
    if (!$searchResults) {
        $searchError = 'School not available. Please try another search term.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Smart Communication System for Secondary Schools</title>
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
            background-color: var(--light-color);
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
        
        /* Hero Section Styles */
        .hero {
            background: linear-gradient(rgba(0, 112, 74, 0.8), rgba(0, 112, 74, 0.9)), url('assets/images/school-bg.jpg');
            background-size: cover;
            background-position: center;
            color: var(--light-color);
            padding: 5rem 0;
            text-align: center;
        }
        
        .hero h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .hero p {
            font-size: 1.2rem;
            max-width: 800px;
            margin: 0 auto 2rem;
        }
        
        .search-container {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .search-form {
            display: flex;
            background-color: var(--light-color);
            border-radius: 50px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        
        .search-input {
            flex: 1;
            padding: 1rem 1.5rem;
            border: none;
            font-size: 1rem;
            font-family: 'Poppins', sans-serif;
        }
        
        .search-input:focus {
            outline: none;
        }
        
        .search-btn {
            background-color: var(--accent-color);
            color: var(--light-color);
            border: none;
            padding: 1rem 1.5rem;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .search-btn:hover {
            background-color: var(--primary-color);
        }
        
        /* Search Results Styles */
        .search-results {
            background-color: var(--gray-color);
            padding: 3rem 0;
        }
        
        .search-results h2 {
            text-align: center;
            margin-bottom: 2rem;
            color: var(--primary-color);
        }
        
        .schools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .school-card {
            background-color: var(--light-color);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .school-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .school-logo {
            height: 150px;
            background-color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .school-logo i {
            font-size: 3rem;
            color: var(--light-color);
        }
        
        .school-info {
            padding: 1.5rem;
        }
        
        .school-info h3 {
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }
        
        .school-info p {
            color: #666;
            margin-bottom: 1rem;
        }
        
        .view-school-btn {
            display: inline-block;
            background-color: var(--primary-color);
            color: var(--light-color);
            padding: 0.5rem 1rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .view-school-btn:hover {
            background-color: var(--accent-color);
        }
        
        .no-results {
            text-align: center;
            padding: 2rem;
            background-color: var(--light-color);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        /* Features Section Styles */
        .features {
            padding: 5rem 0;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .section-title h2 {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .section-title p {
            max-width: 700px;
            margin: 0 auto;
            color: #666;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }
        
        .feature-card {
            text-align: center;
            padding: 2rem;
            background-color: var(--light-color);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
        }
        
        .feature-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .feature-card h3 {
            margin-bottom: 1rem;
            color: var(--primary-color);
        }
        
        /* Call to Action Styles */
        .cta {
            background-color: var(--primary-color);
            color: var(--light-color);
            padding: 5rem 0;
            text-align: center;
        }
        
        .cta h2 {
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        
        .cta p {
            max-width: 700px;
            margin: 0 auto 2rem;
            font-size: 1.1rem;
        }
        
        .cta-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .cta-btn {
            display: inline-block;
            padding: 0.8rem 1.5rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .primary-btn {
            background-color: var(--light-color);
            color: var(--primary-color);
        }
        
        .secondary-btn {
            border: 2px solid var(--light-color);
            color: var(--light-color);
        }
        
        .primary-btn:hover {
            background-color: var(--footer-color);
        }
        
        .secondary-btn:hover {
            background-color: rgba(255, 255, 255, 0.1);
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
            
            .hero h1 {
                font-size: 2rem;
            }
            
            .hero p {
                font-size: 1rem;
            }
            
            .search-form {
                flex-direction: column;
                border-radius: 8px;
            }
            
            .search-input {
                width: 100%;
                border-radius: 8px 8px 0 0;
            }
            
            .search-btn {
                width: 100%;
                border-radius: 0 0 8px 8px;
            }
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <header>
        <div class="container header-container">
            <a href="index.php" class="logo"><?php echo APP_NAME; ?><span></span></a>
            
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
            </nav>
            
            <div class="auth-buttons">
                <a href="login.php" class="login-btn">Login</a>
                <a href="register.php" class="register-btn">Register School</a>
            </div>
        </div>
    </header>
    
    <!-- Hero Section with Search -->
    <section class="hero">
        <div class="container">
            <h1>Smart Communication System for Secondary Schools</h1>
            <p>Bridging the gap between schools and parents with real-time updates, fee tracking, and digital permission requests.</p>
            
            <div class="search-container">
                <form action="index.php" method="GET" class="search-form">
                    <input type="text" name="search" class="search-input" placeholder="Search for your school..." value="<?php echo htmlspecialchars($searchQuery); ?>" required>
                    <button type="submit" class="search-btn">Search</button>
                </form>
            </div>
        </div>
    </section>
    
    <!-- Search Results Section -->
    <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
    <section class="search-results">
        <div class="container">
            <h2>Search Results for "<?php echo htmlspecialchars($searchQuery); ?>"</h2>
            
            <?php if (!empty($searchResults)): ?>
                <div class="schools-grid">
                    <?php foreach ($searchResults as $school): ?>
                        <div class="school-card">
                            <div class="school-info">
                                <h3><?php echo htmlspecialchars($school['name']); ?></h3>
                                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($school['address']); ?></p>
                                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($school['phone']); ?></p>
                                <a href="school.php?id=<?php echo $school['id']; ?>" class="view-school-btn">View School</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-results">
                    <i class="fas fa-exclamation-circle" style="font-size: 3rem; color: var(--primary-color); margin-bottom: 1rem;"></i>
                    <h3><?php echo $searchError; ?></h3>
                    <p>Try searching with a different school name or check your spelling.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <div class="section-title">
                <h2>Key Features</h2>
                <p>Discover how SchoolComm can transform communication between schools and parents</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h3>Real-Time Communication</h3>
                    <p>Instant updates between school staff and parents, ensuring everyone stays informed.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3>Academic Progress Tracking</h3>
                    <p>Monitor student performance with detailed academic reports and analytics.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <h3>Fee Balance Checking</h3>
                    <p>Access real-time financial information and payment history with ease.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <h3>Permission Requests</h3>
                    <p>Digital management of student leave requests and approvals.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-brain"></i>
                    </div>
                    <h3>Sentiment Analysis</h3>
                    <p>AI-powered analysis of parent feedback for continuous school improvement.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <h3>Notifications</h3>
                    <p>Stay updated with important announcements and events through instant notifications.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Call to Action Section -->
    <section class="cta">
        <div class="container">
            <h2>Ready to Transform School Communication?</h2>
            <p>Join hundreds of schools already using SchoolComm to improve parent-school interactions and student outcomes.</p>
            
            <div class="cta-buttons">
                <a href="register.php" class="cta-btn primary-btn">Register Your School</a>
                <a href="contact_form.php" class="cta-btn secondary-btn">Contact Us</a>
            </div>
        </div>
    </section>
    
    <!-- Footer Section -->
    <footer id="contact">
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
                        <li><a href="#features">Features</a></li>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="#contact">Contact</a></li>
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