<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

$user_id = (int) $_GET['id'];

/* ---------- GET TEACHER DATA ---------- */
try {
    $stmt = $pdo->prepare("
        SELECT users.email, teachers.first_name, teachers.last_name
        FROM users
        LEFT JOIN teachers ON users.id = teachers.user_id
        WHERE users.id = ?
    ");
    $stmt->execute([$user_id]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$teacher) {
        header("Location: list_users.php");
        exit;
    }
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

/* ---------- HANDLE UPDATE ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    
    try {
        $stmt = $pdo->prepare("
            UPDATE teachers 
            SET first_name = ?, last_name = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$first_name, $last_name, $user_id]);
        
        header("Location: update_teacher.php?id=" . $user_id . "&success=1");
        exit;
        
    } catch (PDOException $e) {
        $error = "Error updating profile: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Teacher Profile</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

<div class="sidebar">
    <h2>BIMS Admin</h2>
    <a href="../admin_dashboard.php">Dashboard</a>
    <a href="create_user.php">Create User</a>
    <a href="../list_users.php">List Users</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main-content">
    <div class="container">
        <div class="card">
            <h2>Update Teacher Profile</h2>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($teacher['email']); ?></p>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert-success">✅ Profile updated successfully.</div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <label>First Name</label>
                <input type="text" name="first_name" required value="<?php echo htmlspecialchars($teacher['first_name'] ?? ''); ?>">

                <label>Last Name</label>
                <input type="text" name="last_name" required value="<?php echo htmlspecialchars($teacher['last_name'] ?? ''); ?>">

                <button type="submit">Update Profile</button>
            </form>

            <a href="../list_users.php" class="button button-yellow" style="margin-top: 20px;">← Back to Users</a>
        </div>
    </div>
</div>

</body>
</html>