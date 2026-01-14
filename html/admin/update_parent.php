<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

$user_id = (int) $_GET['id'];

/* ---------- GET PARENT DATA ---------- */
try {
    $stmt = $pdo->prepare("
        SELECT users.email, parents.first_name, parents.last_name, parents.linked_students
        FROM users
        LEFT JOIN parents ON users.id = parents.user_id
        WHERE users.id = ?
    ");
    $stmt->execute([$user_id]);
    $parent = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$parent) {
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
                SET first_name = ?, last_name = ?, linked_students = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$first_name, $last_name, $linked_students, $user_id]);
            
            header("Location: update_parent.php?id=" . $user_id . "&success=1");
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
    <title>Update Parent Profile</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/update_profile.css">
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
            <h2>Update Parent Profile</h2>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($parent['email']); ?></p>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert-success">✅ Profile updated successfully.</div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <label>First Name</label>
                <input type="text" name="first_name" required value="<?php echo htmlspecialchars($parent['first_name'] ?? ''); ?>">

                <label>Last Name</label>
                <input type="text" name="last_name" required value="<?php echo htmlspecialchars($parent['last_name'] ?? ''); ?>">

                <label>Student Admission Numbers <span class="hint">(separate multiple with commas)</span></label>
                <input type="text" name="admission_numbers" 
                       placeholder="e.g., 1234, 5678, 9012" 
                       value="<?php echo htmlspecialchars($parent['linked_students'] ?? ''); ?>">
                <p class="help-text">
                    💡 Enter the admission numbers of your children. You can add multiple admission numbers separated by commas.
                </p>

                <button type="submit">Update Profile</button>
            </form>

            <a href="../list_users.php" class="button button-yellow" style="margin-top: 20px;">← Back to Users</a>
        </div>
    </div>
</div>

</body>
</html>