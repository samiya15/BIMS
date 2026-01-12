<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

$user_id = (int) $_GET['id'];

/* ---------- GET ADMIN DATA ---------- */
try {
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        header("Location: list_users.php");
        exit;
    }
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

/* ---------- HANDLE PASSWORD UPDATE ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $new_password = $_POST['new_password'];
    
    try {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$hashed, $user_id]);
        
        header("Location: update_admin.php?id=" . $user_id . "&success=1");
        exit;
        
    } catch (PDOException $e) {
        $error = "Error updating password: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Admin Profile</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

<div class="sidebar">
    <h2>BIMS Admin</h2>
    <a href="../admin_dashboard.php">Dashboard</a>
    <a href="create_user.php">Create User</a>
    <a href="list_users.php">List Users</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main-content">
    <div class="container">
        <div class="card">
            <h2>Update Admin Profile</h2>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($admin['email']); ?></p>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert-success">✅ Password updated successfully.</div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <label>New Password</label>
                <input type="password" name="new_password" required placeholder="Enter new password">

                <button type="submit">Update Password</button>
            </form>

            <a href="list_users.php" class="button button-yellow" style="margin-top: 20px;">← Back to Users</a>
        </div>
    </div>
</div>

</body>
</html>