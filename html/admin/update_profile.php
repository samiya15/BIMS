<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

/* ---------- GET USER ---------- */
if (!isset($_GET['id'])) {
    header("Location: list_users.php");
    exit;
}

$user_id = (int) $_GET['id'];

try {
    $stmt = $pdo->prepare("
        SELECT users.id, users.email, users.role_id, roles.name AS role
        FROM users
        JOIN roles ON users.role_id = roles.id
        WHERE users.id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header("Location: list_users.php");
        exit;
    }
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

/* ---------- ROUTE TO CORRECT PROFILE UPDATE PAGE ---------- */
switch ($user['role']) {
    case 'Parent':
        header("Location: update_parent.php?id=" . $user_id);
        exit;
    case 'Student':
        header("Location: update_student.php?id=" . $user_id);
        exit;
    case 'Teacher':
        header("Location: update_teacher.php?id=" . $user_id);
        exit;
    case 'Admin':
        header("Location: update_admin.php?id=" . $user_id);
        exit;
    default:
        header("Location: ../list_users.php");
        exit;
}
?>