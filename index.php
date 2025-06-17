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
        }        /* Hero Section Styles */        .hero {
            color: var(--light-color);
            padding: 0;
            text-align: center;
            position: relative;
            overflow: hidden;
            min-height: 70vh;
            display: flex;
            align-items: center;
            background: #009260;
        }

        .hero-slider {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }

        .slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            opacity: 0;
            transform: scale(1.1);
            transition: all 1s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .slide.active {
            opacity: 1;
            transform: scale(1);
        }

        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;            background: linear-gradient(135deg, 
                rgba(0, 146, 96, 0.85) 0%,
                rgba(0, 146, 96, 0.75) 50%,
                rgba(0, 146, 96, 0.65) 100%
            );
            z-index: 2;
        }

        .slider-nav {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            position: relative;
            z-index: 3;
        }

        .nav-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid var(--footer-color);
            background: transparent;
            padding: 0;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .nav-dot::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 100%;
            height: 100%;
            background: var(--footer-color);
            transform: translate(-50%, -50%) scale(0);
            border-radius: 50%;
            transition: transform 0.3s ease;
        }

        .nav-dot.active::after {
            transform: translate(-50%, -50%) scale(1);
        }

        @keyframes kenBurns {
            0% {
                transform: scale(1);
            }
            100% {
                transform: scale(1.1);
            }
        }        .hero-content {
            position: relative;
            z-index: 3;
            max-width: 1000px;
            margin: 0 auto;
            padding: 1.5rem;
            background: rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .hero-text-container {
            margin-bottom: 2rem;
            position: relative;
        }

        .welcome-text {
            font-size: 1.5rem;
            font-weight: 300;
            margin-bottom: 0.5rem;
            opacity: 0.9;
            letter-spacing: 4px;
            text-transform: uppercase;
            animation: fadeInDown 1s ease;
            color: var(--footer-color);
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            position: relative;
        }

        .welcome-text::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 2px;
            background: var(--footer-color);
            box-shadow: 0 0 10px var(--footer-color);
        }

        .hero-title {            font-size: 4rem;
            font-weight: 800;
            margin: 1rem 0;
            background: linear-gradient(135deg, #ffffff 30%, var(--footer-color) 70%);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: fadeInUp 1s ease 0.2s both;
            text-shadow: 0 5px 15px rgba(0,0,0,0.2);
            letter-spacing: -1px;
            position: relative;
            display: inline-block;
        }

        .hero-title::before,
        .hero-title::after {
            content: '';
            position: absolute;
            width: 50px;
            height: 50px;
            border: 2px solid var(--footer-color);
            opacity: 0.5;
        }

        .hero-title::before {
            top: -10px;
            left: -20px;
            border-right: none;
            border-bottom: none;
        }

        .hero-title::after {
            bottom: -10px;
            right: -20px;
            border-left: none;
            border-top: none;
        }

        .hero-subtitle {
            font-size: 2.2rem;
            font-weight: 600;
            margin-bottom: 2rem;
            color: #ffffff;
            animation: fadeInUp 1s ease 0.4s both;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            position: relative;
            z-index: 2;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
          .search-container {
            max-width: 700px;
            margin: 0 auto;
            animation: fadeInUp 1s ease 0.6s both;
        }
        
        .search-form {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 100px;
            padding: 0.5rem;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .search-wrapper {
            display: flex;
            align-items: center;
            background-color: var(--light-color);
            border-radius: 50px;
            padding: 0.5rem 1rem;
            transition: transform 0.3s ease;
        }

        .search-wrapper:focus-within {
            transform: translateY(-2px);
        }

        .search-icon {
            color: var(--primary-color);
            font-size: 1.2rem;
            margin-right: 1rem;
        }
        
        .search-input {
            flex: 1;
            padding: 0.8rem 1rem;
            border: none;
            font-size: 1.1rem;
            font-family: 'Poppins', sans-serif;
            background: transparent;
        }
        
        .search-input:focus {
            outline: none;
        }
        
        .search-btn {
            background: linear-gradient(135deg, var(--accent-color), var(--primary-color));
            color: var(--light-color);
            border: none;
            padding: 1rem 2rem;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .search-btn:hover {
            transform: translateX(5px);
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        }

        .search-btn i {
            transition: transform 0.3s ease;
        }

        .search-btn:hover i {
            transform: translateX(3px);
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
        }        .features-grid {
            display: flex;
            gap: 2rem;
            padding: 1rem;
            overflow-x: auto;
            scroll-snap-type: x mandatory;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
            margin: 0 auto;
        }

        .features-grid::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }

        .feature-card {
            text-align: center;
            padding: 2rem;
            background-color: var(--light-color);
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            flex: 0 0 300px;
            scroll-snap-align: start;
            position: relative;
            overflow: hidden;
        }
        
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--footer-color));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .features {
            overflow: hidden;
        }
          .feature-icon {
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            font-size: 2.5rem;
            color: var(--primary-color);
            background: linear-gradient(135deg, rgba(0, 112, 74, 0.1), rgba(248, 195, 1, 0.1));
            transition: all 0.3s ease;
        }
        
        .feature-card:hover .feature-icon {
            transform: scale(1.1);
            color: var(--footer-color);
            background: linear-gradient(135deg, rgba(0, 112, 74, 0.15), rgba(248, 195, 1, 0.15));
        }
        
        .feature-card h3 {
            margin-bottom: 1rem;
            color: var(--primary-color);
            font-size: 1.4rem;
            font-weight: 600;
        }

        .feature-card p {
            color: #666;
            font-size: 0.95rem;
            line-height: 1.6;
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
      <!-- Hero Section with Search -->    <section class="hero">
        <div class="hero-slider">
            <div class="slide active" style="background-image: url('image/image1.jpg')"></div>
            <div class="slide" style="background-image: url('image/image5.jpg')"></div>
            <div class="slide" style="background-image: url('image/image12.jpg')"></div>
        </div>
        <div class="hero-overlay"></div>
        <div class="container hero-content">
            <div class="hero-text-container">
                <div class="welcome-text" data-aos="fade-down">Welcome to</div>
                <h1 class="hero-title" data-aos="fade-up"><?php echo APP_NAME; ?></h1>
                <div class="hero-subtitle" data-aos="fade-up" data-aos-delay="200">
                    Smart Communication System for Secondary Schools
                </div>
                <div class="slider-nav">
                    <button class="nav-dot active" data-slide="0"></button>
                    <button class="nav-dot" data-slide="1"></button>
                    <button class="nav-dot" data-slide="2"></button>
                </div>
            </div>
            
            <div class="search-container" data-aos="fade-up" data-aos-delay="600">
                <form action="index.php" method="GET" class="search-form">
                    <div class="search-wrapper">
                        <i class="fas fa-search search-icon"></i>
                        <input type="text" name="search" class="search-input" placeholder="Search for your school..." value="<?php echo htmlspecialchars($searchQuery); ?>" required>
                        <button type="submit" class="search-btn">
                            <span>Find School</span>
                            <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
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
        <div class="container" style="max-width: 1400px;">
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const slides = document.querySelectorAll('.slide');
            const dots = document.querySelectorAll('.nav-dot');
            let currentSlide = 0;
            const slideInterval = 5000; // Change slide every 5 seconds

            function goToSlide(n) {
                slides[currentSlide].classList.remove('active');
                dots[currentSlide].classList.remove('active');
                currentSlide = (n + slides.length) % slides.length;
                slides[currentSlide].classList.add('active');
                dots[currentSlide].classList.add('active');
            }

            function nextSlide() {
                goToSlide(currentSlide + 1);
            }

            // Add click events to dots
            dots.forEach((dot, index) => {
                dot.addEventListener('click', () => {
                    goToSlide(index);
                });
            });

            // Start automatic slideshow
            setInterval(nextSlide, slideInterval);
        });
    </script>
</body>
</html>