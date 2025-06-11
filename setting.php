<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../config/config.php';
session_start();

if (!isset($_SESSION['school_admin_id']) || !isset($_SESSION['school_admin_school_id'])) {
    header('Location: ../login.php');
    exit;
}

$admin_id = $_SESSION['school_admin_id'];
$school_id = $_SESSION['school_admin_school_id'];
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_name = trim($_POST['school_name']);
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);

    if ($school_name && $email) {
        try {
            $conn = getDbConnection();
            $stmt = $conn->prepare("UPDATE schools SET name = ?, address = ?, phone = ?, email = ? WHERE id = ?");
            $stmt->bind_param('ssssi', $school_name, $address, $phone, $email, $school_id);
            $stmt->execute();
            $stmt->close();
            $conn->close();
            $success = 'Settings updated successfully.';
        } catch (Exception $e) {
            $error = 'Update failed: ' . $e->getMessage();
        }
    } else {
        $error = 'School name and email are required.';
    }
}

// Fetch current school info
$conn = getDbConnection();
$stmt = $conn->prepare("SELECT name, address, phone, email FROM schools WHERE id = ?");
$stmt->bind_param('i', $school_id);
$stmt->execute();
$result = $stmt->get_result();
$school = $result->fetch_assoc();
$stmt->close();
$conn->close();
?>

<?php include 'sidebar.php'; ?>

<main class="main-content">
    <div class="container">
        <h1>School Settings</h1>

        <?php if ($error): ?>
            <div style="color: red;"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($success): ?>
            <div style="color: green;"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label>School Name</label>
                <input type="text" name="school_name" value="<?php echo htmlspecialchars($school['name'] ?? ''); ?>" required class="form-control">
            </div>
            <div class="mb-3">
                <label>Address</label>
                <input type="text" name="address" value="<?php echo htmlspecialchars($school['address'] ?? ''); ?>" class="form-control">
            </div>
            <div class="mb-3">
                <label>Phone</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($school['phone'] ?? ''); ?>" class="form-control">
            </div>
            <div class="mb-3">
                <label>Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($school['email'] ?? ''); ?>" required class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </div>
</main>

<style>
.main-content {
    margin-left: 250px;
    padding: 20px;
}
</style>
