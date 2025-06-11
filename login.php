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
    $role = $_POST['role'] ?? '';

    if (empty($username) || empty($password) || empty($role)) {
        $error = 'Please fill in all fields.';
    } else {
        $conn = getDbConnection();
        if ($role === 'parent') {
            $stmt = $conn->prepare('SELECT id, username, password FROM parents WHERE username = ?');
        } elseif ($role === 'school_admin') {
            $stmt = $conn->prepare('SELECT sa.id, sa.username, sa.password, sa.full_name, sa.school_id FROM school_admins sa WHERE sa.username = ?');
        } elseif ($role === 'system_admin') {
            $stmt = $conn->prepare('SELECT id, username, password FROM system_admins WHERE username = ?');
        } else {
            $stmt = false;
        }

        if ($stmt) {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            // Debugging: Check if the user is fetched
            if (!$user) {
                $error = 'No user found with the provided username.';
            } elseif (!password_verify($password, $user['password'])) {
                // Debugging: Check if the password matches
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
            $stmt->close();
        } else {
            $error = 'Invalid role selected.';
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
        body { font-family: 'Poppins', sans-serif; background: #f5f5f5; margin: 0; }
        .container { max-width: 400px; margin: 60px auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); padding: 2rem; }
        h1 { color: #333; text-align: center; }
        .alert { padding: 1rem; border-radius: 4px; margin-bottom: 1.5rem; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        input, select { width: 100%; padding: 0.8rem; border: 1px solid #e0e0e0; border-radius: 4px; font-family: 'Poppins', sans-serif; }
        .btn { background: #007bff; color: #fff; border: none; padding: 0.8rem 1.5rem; border-radius: 4px; font-size: 1rem; cursor: pointer; transition: background 0.3s; width: 100%; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Login</h1>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" required>
            </div>
            <div class="form-group">
                <label for="role">Login as</label>
                <select name="role" id="role" required>
                    <option value="">Select Role</option>
                    <option value="school_admin">School Admin</option>
                    <option value="system_admin">System Admin</option>
                    <option value="parent">Parent</option>
                </select>
            </div>
            <button type="submit" class="btn">Login</button>
        </form>
    </div>
</body>
</html>