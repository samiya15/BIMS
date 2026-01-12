<?php
session_start();
require_once __DIR__ . "/../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

/* ---------- HANDLE DELETE ---------- */
if (isset($_GET['delete'])) {
    $user_id = (int) $_GET['delete'];
    
    try {
        $pdo->prepare("DELETE FROM teachers WHERE user_id = ?")->execute([$user_id]);
        $pdo->prepare("DELETE FROM students WHERE user_id = ?")->execute([$user_id]);
        $pdo->prepare("DELETE FROM parents WHERE user_id = ?")->execute([$user_id]);
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
        
        header("Location: list_users.php?deleted=1");
        exit;
    } catch (PDOException $e) {
        header("Location: list_users.php?error=1");
        exit;
    }
}

/* ---------- FETCH ALL USERS ---------- */
try {
    $stmt = $pdo->query("
        SELECT users.id, users.email, users.created_at, users.role_id, roles.name AS role
        FROM users
        JOIN roles ON users.role_id = roles.id
        ORDER BY users.created_at DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
    $fetch_error = "Error loading users: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>List Users</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/list_users.css">
</head>
<body>

<div class="sidebar">
    <h2>BIMS Admin</h2>
    <a href="../admin_dashboard.php">Dashboard</a>
    <a href="../create_user.php">Create User</a>
    <a href="list_users.php" class="active">List Users</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main-content">
    <div class="container">
        <div class="card">
            <h2>All Users</h2>

            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert-success">✅ User deleted successfully.</div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert-error">❌ Error deleting user.</div>
            <?php endif; ?>

            <?php if (isset($fetch_error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($fetch_error); ?></div>
            <?php endif; ?>

            <?php if (!empty($users)): ?>
                <span class="user-count">Total Users: <?php echo count($users); ?></span>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="role-badge role-<?php echo strtolower($user['role']); ?>">
                                            <?php echo htmlspecialchars($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <a href="admin/update_profile.php?id=<?php echo $user['id']; ?>" class="btn-update">Update Profile</a>
                                        <a href="list_users.php?delete=<?php echo $user['id']; ?>" 
                                           class="btn-delete" 
                                           onclick="return confirm('Are you sure you want to delete this user?');">
                                            Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-users">
                    No users found. <a href="create_user.php">Create your first user</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>