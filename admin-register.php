<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/config.php';
require_once 'includes/Database.php';

// Initialize variables
$username = $password = $confirm_password = $email = $full_name = '';
$username_err = $password_err = $confirm_password_err = $email_err = $full_name_err = $register_err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate username
    if (empty(trim($_POST['username']))) {
        $username_err = 'Please enter a username.';
    } else {
        $username = trim($_POST['username']);
    }

    // Validate email
    if (empty(trim($_POST['email']))) {
        $email_err = 'Please enter an email.';
    } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $email_err = 'Invalid email format.';
    } else {
        $email = trim($_POST['email']);
    }

    // Validate full name
    if (empty(trim($_POST['full_name']))) {
        $full_name_err = 'Please enter your full name.';
    } else {
        $full_name = trim($_POST['full_name']);
    }

    // Validate password
    if (empty(trim($_POST['password']))) {
        $password_err = 'Please enter a password.';
    } elseif (strlen(trim($_POST['password'])) < 6) {
        $password_err = 'Password must have at least 6 characters.';
    } else {
        $password = trim($_POST['password']);
    }

    // Validate confirm password
    if (empty(trim($_POST['confirm_password']))) {
        $confirm_password_err = 'Please confirm password.';
    } else {
        $confirm_password = trim($_POST['confirm_password']);
        if ($password !== $confirm_password) {
            $confirm_password_err = 'Password did not match.';
        }
    }

    // Check input errors before inserting in database
    if (empty($username_err) && empty($password_err) && empty($confirm_password_err) && empty($email_err) && empty($full_name_err)) {
        $db = new Database();
        // Check if admin already exists
        $db->query('SELECT id FROM system_admins WHERE username = :username OR email = :email');
        $db->bind(':username', $username);
        $db->bind(':email', $email);
        $db->execute();
        if ($db->rowCount() > 0) {
            $register_err = 'An admin with this username or email already exists.';
        } else {
            // Insert new admin
            $db->query('INSERT INTO system_admins (username, email, password, full_name) VALUES (:username, :email, :password, :full_name)');
            $db->bind(':username', $username);
            $db->bind(':email', $email);
            $db->bind(':password', password_hash($password, PASSWORD_DEFAULT));
            $db->bind(':full_name', $full_name);
            if ($db->execute()) {
                header('Location: login.php?admin_registered=1'); // Redirect to login page
                exit();
            } else {
                $register_err = 'Something went wrong. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Registration - SchoolComm</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <h2>Register System Administrator</h2>
        <p>Please fill this form to create an admin account.</p>
        <?php if (!empty($register_err)) echo '<div class="alert alert-danger">' . $register_err . '</div>'; ?>
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($username); ?>">
                <span class="help-block"><?php echo $username_err; ?></span>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>">
                <span class="help-block"><?php echo $email_err; ?></span>
            </div>
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($full_name); ?>">
                <span class="help-block"><?php echo $full_name_err; ?></span>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control">
                <span class="help-block"><?php echo $password_err; ?></span>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control">
                <span class="help-block"><?php echo $confirm_password_err; ?></span>
            </div>
            <div class="form-group">
                <input type="submit" class="btn btn-primary" value="Register">
            </div>
        </form>
    </div>
</body>
</html>