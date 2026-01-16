<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Teacher') {
    header("Location: ../login.php");
    exit;
}

/* ---------- GET TEACHER DATA ---------- */
$stmt = $pdo->prepare("
    SELECT 
        u.email,
        t.id as teacher_id,
        t.first_name,
        t.last_name,
        t.phone_number,
        t.residential_area,
        t.date_of_birth,
        t.national_id,
        t.category,
        cl.name as assigned_class,
        ct.name as class_curriculum
    FROM users u
    JOIN teachers t ON u.id = t.user_id
    LEFT JOIN classes_levels cl ON t.assigned_class_id = cl.id
    LEFT JOIN curriculum_types ct ON cl.curriculum_type_id = ct.id
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

/* ---------- GET TEACHER'S SUBJECTS ---------- */
$subjects_stmt = $pdo->prepare("
    SELECT ts.curriculum_type_id, ct.name as curriculum_name, ts.subject_name
    FROM teacher_subjects ts
    JOIN curriculum_types ct ON ts.curriculum_type_id = ct.id
    WHERE ts.teacher_id = ?
    ORDER BY ct.id, ts.subject_name
");
$subjects_stmt->execute([$teacher['teacher_id']]);
$teacher_subjects_raw = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize by curriculum
$teacher_subjects = [];
foreach ($teacher_subjects_raw as $row) {
    $teacher_subjects[$row['curriculum_name']][] = $row['subject_name'];
}

/* ---------- HANDLE UPDATE ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $phone_number = trim($_POST['phone_number']);
    $residential_area = trim($_POST['residential_area']);
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE teachers 
            SET phone_number = ?, residential_area = ?, date_of_birth = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$phone_number, $residential_area, $date_of_birth, $_SESSION['user_id']]);
        
        header("Location: my_profile.php?success=1");
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
    <title>My Profile</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/update_profile.css">
</head>
<body>

<div class="sidebar">
    <h2>BIMS Teacher</h2>
    <a href="../teacher_dashboard.php">Dashboard</a>
    <a href="my_profile.php" class="active">My Profile</a>
    <a href="manage_grades.php">Manage Grades</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main-content">
    <div class="container">
        <div class="card">
            <h2>My Profile</h2>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert-success">✅ Profile updated successfully.</div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- VIEW-ONLY INFORMATION -->
            <div class="profile-section">
                <h3>📋 Basic Information (View Only)</h3>
                
                <label>Email</label>
                <input type="text" value="<?php echo htmlspecialchars($teacher['email']); ?>" readonly style="background: #f0f0f0;">

                <label>First Name</label>
                <input type="text" value="<?php echo htmlspecialchars($teacher['first_name']); ?>" readonly style="background: #f0f0f0;">

                <label>Last Name</label>
                <input type="text" value="<?php echo htmlspecialchars($teacher['last_name']); ?>" readonly style="background: #f0f0f0;">

                <label>National ID / Passport</label>
                <input type="text" value="<?php echo htmlspecialchars($teacher['national_id'] ?? 'Not set'); ?>" readonly style="background: #f0f0f0;">

                <label>Teacher Category</label>
                <input type="text" value="<?php echo htmlspecialchars($teacher['category']); ?>" readonly style="background: #f0f0f0;">

                <?php if (!empty($teacher['assigned_class'])): ?>
                    <label>Assigned Class</label>
                    <input type="text" value="<?php echo htmlspecialchars($teacher['class_curriculum'] . ' - ' . $teacher['assigned_class']); ?>" readonly style="background: #f0f0f0;">
                <?php endif; ?>
            </div>

            <?php if (!empty($teacher_subjects)): ?>
                <div class="profile-section">
                    <h3>📚 Teaching Subjects (View Only)</h3>
                    <?php foreach ($teacher_subjects as $curr => $subjects): ?>
                        <label><?php echo htmlspecialchars($curr); ?></label>
                        <input type="text" value="<?php echo htmlspecialchars(implode(', ', $subjects)); ?>" readonly style="background: #f0f0f0;">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- EDITABLE INFORMATION -->
            <form method="POST">
                <div class="profile-section">
                    <h3>✏️ Editable Information</h3>
                    
                    <label>Phone Number</label>
                    <input type="tel" name="phone_number" placeholder="+254..." value="<?php echo htmlspecialchars($teacher['phone_number'] ?? ''); ?>">

                    <label>Residential Area</label>
                    <input type="text" name="residential_area" placeholder="e.g., Nairobi, Westlands" value="<?php echo htmlspecialchars($teacher['residential_area'] ?? ''); ?>">

                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($teacher['date_of_birth'] ?? ''); ?>">
                </div>

                <button type="submit" style="margin-top: 20px;">Update Profile</button>
            </form>

            <a href="../teacher_dashboard.php" class="button button-yellow" style="margin-top: 20px;">← Back to Dashboard</a>
        </div>
    </div>
</div>

</body>
</html>