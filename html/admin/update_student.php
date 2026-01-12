<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

$user_id = (int) $_GET['id'];

/* ---------- GET STUDENT DATA ---------- */
try {
    $stmt = $pdo->prepare("
        SELECT users.email, students.admission_number, students.first_name, 
               students.last_name, students.gender, students.is_active, students.parent_id
        FROM users
        LEFT JOIN students ON users.id = students.user_id
        WHERE users.id = ?
    ");
    $stmt->execute([$user_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        header("Location: list_users.php");
        exit;
    }
    
    // Get all parents for dropdown
    $parents = $pdo->query("
        SELECT parents.id, parents.first_name, parents.last_name, users.email
        FROM parents
        JOIN users ON parents.user_id = users.id
        ORDER BY parents.last_name, parents.first_name
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

/* ---------- HANDLE UPDATE ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $admission_number = trim($_POST['admission_number']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $gender = $_POST['gender'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $parent_id = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE students 
            SET admission_number = ?, first_name = ?, last_name = ?, 
                gender = ?, is_active = ?, parent_id = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$admission_number, $first_name, $last_name, $gender, $is_active, $parent_id, $user_id]);
        
        header("Location: update_student.php?id=" . $user_id . "&success=1");
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
    <title>Update Student Profile</title>
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
            <h2>Update Student Profile</h2>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert-success">✅ Profile updated successfully.</div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <label>Admission Number</label>
                <input type="text" name="admission_number" required value="<?php echo htmlspecialchars($student['admission_number'] ?? ''); ?>">

                <label>First Name</label>
                <input type="text" name="first_name" required value="<?php echo htmlspecialchars($student['first_name'] ?? ''); ?>">

                <label>Last Name</label>
                <input type="text" name="last_name" required value="<?php echo htmlspecialchars($student['last_name'] ?? ''); ?>">

                <label>Gender</label>
                <select name="gender" required>
                    <option value="">Select Gender</option>
                    <option value="Male" <?php echo ($student['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo ($student['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                </select>

                <label>Parent</label>
                <select name="parent_id">
                    <option value="">No Parent Assigned</option>
                    <?php foreach ($parents as $parent): ?>
                        <option value="<?php echo $parent['id']; ?>" 
                            <?php echo ($student['parent_id'] ?? '') == $parent['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name'] . ' (' . $parent['email'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label style="display: flex; align-items: center; margin-top: 10px;">
                    <input type="checkbox" name="is_active" value="1" 
                        <?php echo ($student['is_active'] ?? 1) ? 'checked' : ''; ?> 
                        style="width: auto; margin-right: 10px;">
                    Active Student
                </label>

                <button type="submit" style="margin-top: 20px;">Update Profile</button>
            </form>

            <a href="list_users.php" class="button button-yellow" style="margin-top: 20px;">← Back to Users</a>
        </div>
    </div>
</div>

</body>
</html>