<?php
session_start();

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>

<div class="sidebar">
    <h2>BIMS Admin</h2>
    <a href="admin_dashboard.php" class="active">Dashboard</a>
    <a href="admin/create_user.php">Create User</a>
    <a href="list_users.php">List Users</a>
    <a href="logout.php">Logout</a>
    <a href="admin/manage_grade_uploads.php">Manage Grade Uploads</a>
</div>

<div class="main-content">
    <div class="container">
        <div class="card">
            <h1>Admin Dashboard</h1>
            <p>Welcome, System Administrator 👋</p>
            <a href="admin/create_user.php" class="button">➕ Create User</a>
        </div>
    </div>
</div>

</body>
</html>