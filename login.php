<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/config.php';
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $conn = getDbConnection();
        $user = null;
        $role = '';
        // Try parent
        $stmt = $conn->prepare('SELECT id, username, password FROM parents WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $role = 'parent';
        }
        $stmt->close();
        // Try school_admin if not found
        if (!$user) {
            $stmt = $conn->prepare('SELECT sa.id, sa.username, sa.password, sa.full_name, sa.school_id FROM school_admins sa WHERE sa.username = ?');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $role = 'school_admin';
            }
            $stmt->close();
        }
        // Try system_admin if not found
        if (!$user) {
            $stmt = $conn->prepare('SELECT id, username, password FROM system_admins WHERE username = ?');
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $user = $result->fetch_assoc();
                $role = 'system_admin';
            }
            $stmt->close();
        }
        if (!$user) {
            $error = 'No user found with the provided username.';
        } elseif (!password_verify($password, $user['password'])) {
            $error = 'Invalid credentials. Password does not match.';
        } else {
            // Set session variables and redirect based on role
            if ($role === 'parent') {
                $_SESSION['parent_id'] = $user['id'];
                $_SESSION['parent_username'] = $user['username'];
                header('Location: parent/dashboard.php');
                exit;
            } elseif ($role === 'school_admin') {
                $_SESSION['school_admin_id'] = $user['id'];
                $_SESSION['school_admin_username'] = $user['username'];
                $_SESSION['school_admin_name'] = $user['full_name'];
                $_SESSION['school_admin_school_id'] = $user['school_id'];
                header('Location:school-admin/dashboard.php');
                exit;
            } elseif ($role === 'system_admin') {
                $_SESSION['system_admin_id'] = $user['id'];
                $_SESSION['system_admin_username'] = $user['username'];
                header('Location: admin/dashboard.php');
                exit;
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
    <title>Login - <?php echo APP_NAME; ?></title>
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
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--gray-color);
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-outer-container {
            display: flex;
            box-shadow: 0 8px 40px rgba(0,0,0,0.18), 0 1.5px 8px rgba(0,0,0,0.08);
            border-radius: 28px;
            background: #fff;
            overflow: hidden;
            max-width: 900px;
            width: 100%;
            min-height: 520px;
        }
        .login-left {
            flex: 1.2;
            background: linear-gradient(135deg, var(--primary-color) 60%, var(--footer-color) 100%);
            color: var(--light-color);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 3rem 2rem;
            position: relative;
        }
        .login-left .icon {
            font-size: 5.5rem;
            margin-bottom: 2rem;
            color: #fff;
            filter: drop-shadow(0 4px 24px rgba(67,233,123,0.18));
        }
        .login-left h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            letter-spacing: 1px;
            text-shadow: 0 2px 16px #43e97b33, 0 1px 0 #fff;
        }
        .login-left p {
            font-size: 1.2rem;
            opacity: 0.95;
            max-width: 400px;
            text-align: center;
            text-shadow: 0 2px 8px #43e97b22;
        }
        .login-right {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            min-width: 350px;
            padding: 2rem 1rem;
        }
        .login-card {
            background: #fff;
            border-radius: 18px;
            box-shadow: none;
            padding: 2.5rem 2.2rem 2rem 2.2rem;
            width: 100%;
            max-width: 400px;
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }
        .login-card h2 {
            text-align: center;
            color: var(--primary-color);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 1rem;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .form-group {
            position: relative;
            margin-bottom: 1.5rem;
        }
        .form-group input {
            width: 100%;
            padding: 1.1rem 1rem 0.6rem 1rem;
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            background: transparent;
            font-family: 'Poppins', sans-serif;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        .form-group label {
            position: absolute;
            left: 1rem;
            top: 1.1rem;
            background: #fff;
            padding: 0 0.3rem;
            color: #888;
            font-size: 1rem;
            pointer-events: none;
            transition: 0.2s;
        }
        .form-group input:focus + label,
        .form-group input:not(:placeholder-shown) + label {
            top: -0.7rem;
            left: 0.8rem;
            font-size: 0.88rem;
            color: var(--primary-color);
            background: #fff;
        }
        .form-actions {
            display: flex;
            flex-direction: column;
            gap: 0.7rem;
        }
        .btn {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: #fff;
            border: none;
            padding: 0.9rem 1.5rem;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s, transform 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
        }
        .btn:hover {
            background: linear-gradient(135deg, var(--accent-color), var(--primary-color));
            transform: translateY(-2px);
        }
        .form-links {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            margin-top: 0.5rem;
            font-size: 0.98rem;
            gap: 0.3rem;
        }
        .form-links .register-text {
            color: #888;
            font-size: 0.97rem;
        }
        .form-links .register-link {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            margin-left: 0.3rem;
            transition: color 0.2s;
        }
        .form-links .register-link:hover {
            color: var(--accent-color);
        }
        .form-links a {
            color: var(--primary-color);
            text-decoration: none;
            transition: color 0.2s;
            font-weight: 500;
        }
        .form-links a:hover {
            color: var(--accent-color);
        }
        .show-password {
            position: absolute;
            right: 1.1rem;
            top: 1.1rem;
            background: none;
            border: none;
            color: #888;
            font-size: 1.1rem;
            cursor: pointer;
            z-index: 2;
        }
        @media (max-width: 900px) {
            .login-outer-container { flex-direction: column; min-height: unset; }
            .login-left, .login-right { flex: unset; width: 100%; min-width: unset; }
            .login-left { min-height: 220px; padding: 2.5rem 1rem 1.5rem 1rem; border-radius: 28px 28px 0 0; }
            .login-right { padding: 2rem 0.5rem; border-radius: 0 0 28px 28px; }
        }
        @media (max-width: 600px) {
            .login-card { padding: 1.2rem 0.7rem; }
            .login-left h1 { font-size: 1.5rem; }
            .login-left .icon { font-size: 3.5rem; }
        }
    </style>
</head>
<body>
    <div class="login-outer-container">
        <div class="login-left">
            <i class="fas fa-graduation-cap icon"></i>
            <h1>Welcome Back!</h1>
            <p>Log in to access your dashboard, manage your school, or connect with your child's progress. SchoolComm brings parents, staff, and students together.</p>
        </div>
        <div class="login-right">
            <div class="login-card">
                <h2>Login</h2>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <form method="POST" autocomplete="off">
                    <div class="form-group">
                        <input type="text" name="username" id="username" required placeholder=" " autocomplete="username">
                        <label for="username">Username</label>
                    </div>
                    <div class="form-group" style="position:relative;">
                        <input type="password" name="password" id="password" required placeholder=" " autocomplete="current-password">
                        <label for="password">Password</label>
                        <button type="button" class="show-password" onclick="togglePassword()"><i class="fas fa-eye" id="eyeIcon"></i></button>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn">Login</button>
                    </div>
                    <div class="form-links">
                        <a href="#" onclick="alert('Forgot password feature coming soon!'); return false;">Forgot password?</a>
                        <span class="register-text">Don't have an account?<a href="register.php" class="register-link">Register here</a></span>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        function togglePassword() {
            const pwd = document.getElementById('password');
            const eye = document.getElementById('eyeIcon');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                eye.classList.remove('fa-eye');
                eye.classList.add('fa-eye-slash');
            } else {
                pwd.type = 'password';
                eye.classList.remove('fa-eye-slash');
                eye.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>