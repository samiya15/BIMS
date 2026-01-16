<?php
session_start();
require_once __DIR__ . "/../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Parent') {
    header("Location: login.php");
    exit;
}

/* ---------- GET PARENT INFO ---------- */
$parent_stmt = $pdo->prepare("
    SELECT 
        p.id,
        p.first_name,
        p.last_name,
        p.phone_number,
        p.residential_area,
        p.relationship,
        p.linked_students,
        u.email
    FROM parents p
    JOIN users u ON p.user_id = u.id
    WHERE p.user_id = ?
");
$parent_stmt->execute([$_SESSION['user_id']]);
$parent = $parent_stmt->fetch(PDO::FETCH_ASSOC);

$parent_name = ($parent['first_name'] ?? 'Parent') . ' ' . ($parent['last_name'] ?? '');

/* ---------- GET LINKED STUDENTS ---------- */
$linked_students = [];
if (!empty($parent['linked_students'])) {
    $admission_numbers = explode(',', $parent['linked_students']);
    $placeholders = str_repeat('?,', count($admission_numbers) - 1) . '?';
    
    $students_stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.admission_number,
            s.first_name,
            s.last_name,
            s.gender,
            cl.name as class_name,
            ct.name as curriculum_name,
            s.status
        FROM students s
        LEFT JOIN classes_levels cl ON s.class_level_id = cl.id
        LEFT JOIN curriculum_types ct ON s.curriculum_type_id = ct.id
        WHERE s.admission_number IN ($placeholders)
        ORDER BY s.first_name, s.last_name
    ");
    $students_stmt->execute($admission_numbers);
    $linked_students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="assets/css/student.css">
</head>
<body>

<div class="sidebar">
    <h2>BIMS Parent</h2>
    <a href="parent_dashboard.php" class="active">Dashboard</a>
    <a href="parent/my_profile.php">My Profile</a>
    <a href="parent/my_children.php">My Children</a>
    <a href="logout.php">Logout</a>
</div>

<div class="main-content">
    <div class="container">
        <!-- WELCOME CARD -->
        <div class="card welcome-card">
            <h1>Welcome, <?php echo htmlspecialchars($parent_name); ?> 👋</h1>
            <p style="color: rgba(255,255,255,0.9);">
                <?php if (!empty($parent['relationship'])): ?>
                    Role: <?php echo htmlspecialchars($parent['relationship']); ?>
                <?php endif; ?>
            </p>
            <p style="color: var(--yellow); font-weight: 600; margin-top: 10px;">
                You have <?php echo count($linked_students); ?> child(ren) registered
            </p>
        </div>

        <!-- MY CHILDREN -->
        <?php if (!empty($linked_students)): ?>
            <div class="card">
                <h2>👨‍👩‍👧‍👦 My Children</h2>
                <div class="parents-grid">
                    <?php foreach ($linked_students as $student): ?>
                        <div class="parent-card">
                            <div class="parent-icon">🎓</div>
                            <div class="parent-info">
                                <h4><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h4>
                                <p class="parent-relationship">Adm No: <?php echo htmlspecialchars($student['admission_number']); ?></p>
                                <p class="parent-contact">
                                    📚 <?php echo htmlspecialchars($student['curriculum_name'] ?? 'Not Assigned'); ?> - <?php echo htmlspecialchars($student['class_name'] ?? 'Not Assigned'); ?>
                                </p>
                                <p class="parent-contact">
                                    Status: <span class="status-badge <?php echo $student['status'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $student['status'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </p>
                                <a href="parent/view_child_grades.php?student_id=<?php echo $student['id']; ?>" class="grade-button" style="margin-top: 10px;">
                                    View Grades
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <h2>👨‍👩‍👧‍👦 My Children</h2>
                <p class="no-data">No children linked yet. Please update your profile with your child's admission number(s).</p>
                <a href="parent/my_profile.php" class="button" style="max-width: 300px; margin: 20px auto 0;">
                    Update Profile
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>