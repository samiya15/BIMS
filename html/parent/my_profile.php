<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Parent') {
    header("Location: ../login.php");
    exit;
}

/* ---------- GET PARENT DATA ---------- */
$stmt = $pdo->prepare("
    SELECT 
        u.email,
        p.id as parent_id,
        p.first_name,
        p.last_name,
        p.phone_number,
        p.residential_area,
        p.relationship,
        p.linked_students
    FROM users u
    JOIN parents p ON u.id = p.user_id
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$parent = $stmt->fetch(PDO::FETCH_ASSOC);

/* ---------- HANDLE UPDATE ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $phone_number = trim($_POST['phone_number']);
    $residential_area = trim($_POST['residential_area']);
    $relationship = trim($_POST['relationship']);
    $admission_numbers = trim($_POST['admission_numbers']);
    
    // Validate admission numbers exist
    $error_msg = '';
    $valid_numbers = [];
    
    if (!empty($admission_numbers)) {
        $numbers = array_map('trim', explode(',', $admission_numbers));
        
        foreach ($numbers as $adm_no) {
            if (empty($adm_no)) continue;
            
            // Check if admission number exists
            $check = $pdo->prepare("SELECT admission_number FROM students WHERE admission_number = ?");
            $check->execute([$adm_no]);
            
            if ($check->fetch()) {
                $valid_numbers[] = $adm_no;
            } else {
                $error_msg .= "Admission number '{$adm_no}' not found. ";
            }
        }
    }
    
    if (empty($error_msg)) {
        try {
            $linked_students = implode(',', $valid_numbers);
            
            $stmt = $pdo->prepare("
                UPDATE parents 
                SET phone_number = ?, residential_area = ?, relationship = ?, linked_students = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$phone_number, $residential_area, $relationship, $linked_students, $_SESSION['user_id']]);
            
            header("Location: my_profile.php?success=1");
            exit;
            
        } catch (PDOException $e) {
            $error = "Error updating profile: " . $e->getMessage();
        }
    } else {
        $error = $error_msg;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/update_profile.css">
</head>
<body>

<div class="sidebar">
    <h2>BIMS Parent</h2>
    <a href="../parent_dashboard.php">Dashboard</a>
    <a href="my_profile.php" class="active">My Profile</a>
    <a href="my_children.php">My Children</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main-content">
    <div class="container">
        <div class="card">
            <h2>My Profile</h2>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert-success">✅ Profile updated successfully.</div>
            <?php endif