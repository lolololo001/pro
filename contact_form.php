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
    <title>Contact Us - <?php echo APP_NAME; ?></title>
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
            --success-color: #28a745;
            --danger-color: #dc3545;
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
        
        /* Contact Form Styles */
        .contact-container {
            max-width: 800px;
            margin: 2rem auto;
            background-color: var(--light-color);
            border-radius: 10px;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .contact-header {
            background-color: var(--primary-color);
            color: var(--light-color);
            padding: 2rem;
            text-align: center;
        }
        
        .contact-header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .contact-form {
            padding: 2rem;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }
        
        .form-group {
            flex: 1 0 100%;
            margin-bottom: 1.5rem;
            padding: 0 15px;
        }
        
        .form-group.half {
            flex: 1 0 50%;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            border: 2px solid var(--border-color);
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
            outline: none;
        }
          .form-success, .form-error {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .form-error {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .submit-btn {
            background-color: var(--primary-color);
            color: var(--light-color);
            padding: 0.8rem 2rem;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .submit-btn:hover {
            background-color: var(--accent-color);
            transform: translateY(-1px);
        }
        
        .copyright {
            text-align: center;
            padding-top: 2rem;
            color: #555;
        }
        
        @media (max-width: 768px) {
            .form-group.half {
                flex: 1 0 100%;
            }
            
            .contact-header h1 {
                font-size: 1.5rem;
            }
            
            .contact-form {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container header-container">
            <a href="index.php" class="logo"><?php echo APP_NAME; ?></a>
        </div>
    </header>

    <main class="container">
        <div class="contact-container">
            <div class="contact-header">
                <h1>Contact Us</h1>
                <p>Have questions or feedback? We'd love to hear from you!</p>
            </div>
              <div class="contact-form">
                <?php if (isset($_GET['contact']) && $_GET['contact'] == 'success'): ?>
                    <div class="form-success">
                        <i class="fas fa-check-circle"></i> Thank you for your message! We'll get back to you soon.
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="form-error">
                        <i class="fas fa-exclamation-circle"></i> 
                        <?php echo htmlspecialchars(urldecode($_GET['message'] ?? 'An error occurred. Please try again.')); ?>
                    </div>
                <?php endif; ?>
                
                <form action="process_contact.php" method="POST">
                    <div class="form-row">
                        <div class="form-group half">
                            <label for="name">Your Name</label>
                            <input type="text" id="name" name="name" class="form-control" required>
                        </div>
                        <div class="form-group half">
                            <label for="email">Your Email</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea id="message" name="message" class="form-control" rows="5" required></textarea>
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
            </div>
        </div>
        
        <div class="copyright">
            <p>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. All Rights Reserved.</p>
        </div>
    </main>
</body>
</html>