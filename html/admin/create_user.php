<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

/* ---------- HANDLE FORM SUBMISSION ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST['email']);
    $plainPassword = $_POST['password'];
    $role_id = (int) $_POST['role_id'];

    $password = password_hash($plainPassword, PASSWORD_DEFAULT);

    try {
        // Insert user
        $stmt = $pdo->prepare("
            INSERT INTO users (email, password, role_id)
            VALUES (:email, :password, :role_id)
        ");
        $stmt->execute([
            "email" => $email,
            "password" => $password,
            "role_id" => $role_id
        ]);

        $user_id = $pdo->lastInsertId();

        // Create role profile
        switch ($role_id) {
            case 2:
                $pdo->prepare("INSERT INTO teachers (user_id) VALUES (?)")
                    ->execute([$user_id]);
                break;
            case 3:
                $pdo->prepare("INSERT INTO students (user_id) VALUES (?)")
                    ->execute([$user_id]);
                break;
            case 4:
                $pdo->prepare("INSERT INTO parents (user_id) VALUES (?)")
                    ->execute([$user_id]);
                break;
        }

        // ✅ REDIRECT AFTER SUCCESS
        header("Location: create_user.php?success=1");
        exit;

    } catch (PDOException $e) {
        header("Location: create_user.php?error=1");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

<div class="sidebar">
    <h2>BIMS Admin</h2>
    <a href="../admin_dashboard.php">Dashboard</a>
    <a href="create_user.php" class="active">Create User</a>
    <a href="../list_users.php">List Users</a>
    <a href="../logout.php">Logout</a>
    <a href="manage_grade_uploads.php">Manage Grade Uploads</a>
</div>

<div class="main-content">
    <div class="container">
        <div class="card">
            <h2>Create User</h2>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert-success">✅ User created successfully.</div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert-error">❌ Error: Email already exists.</div>
            <?php endif; ?>

            <form method="POST">
                <label>Email</label>
                <input type="email" name="email" required placeholder="user@school.com">

                <label>Password</label>
                <input type="password" name="password" required placeholder="Enter password">

                <label>Role</label>
                <select name="role_id" required>
                    <option value="">Select role</option>
                    <option value="1">Admin</option>
                    <option value="2">Teacher</option>
                    <option value="3">Student</option>
                    <option value="4">Parent</option>
                </select>

                <button type="submit">Create User</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>