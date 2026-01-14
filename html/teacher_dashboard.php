<?php
session_start();
require_once __DIR__ . "/../database/db_connect.php";

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Teacher') {
    header("Location: login.php");
    exit;
}

/* ---------- GET TEACHER INFO ---------- */
$teacher_stmt = $pdo->prepare("
    SELECT first_name, last_name 
    FROM teachers 
    WHERE user_id = ?
");
$teacher_stmt->execute([$_SESSION['user_id']]);
$teacher = $teacher_stmt->fetch(PDO::FETCH_ASSOC);

$teacher_name = trim(($teacher['first_name'] ?? '') . ' ' . ($teacher['last_name'] ?? ''));
if (empty($teacher_name)) {
    $teacher_name = 'Teacher';
}

/* ---------- FETCH STUDENTS GROUPED BY CURRICULUM AND CLASS ---------- */
$stmt = $pdo->query("
    SELECT 
        s.admission_number,
        s.first_name,
        s.last_name,
        s.gender,
        ct.name AS curriculum,
        cl.name AS class_name
    FROM students s
    JOIN curriculum_types ct ON s.curriculum_type_id = ct.id
    JOIN classes_levels cl ON s.class_level_id = cl.id
    WHERE s.status = 1
    ORDER BY ct.id, cl.level_order, s.last_name, s.first_name
");

$students_by_curriculum = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $students_by_curriculum[$row['curriculum']][$row['class_name']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="assets/css/teacher.css">
</head>
<body>

<div class="sidebar">
    <h2>BIMS Teacher</h2>
    <a href="teacher_dashboard.php" class="active">Dashboard</a>
    <a href="teacher/my_classes.php">My Classes</a>
    <a href="teacher/attendance.php">Attendance</a>
    <a href="logout.php">Logout</a>
</div>

<div class="main-content">
    <div class="container">

        <div class="card">
            <h1>Teacher Dashboard</h1>
            <p>Welcome, <?php echo htmlspecialchars($teacher_name); ?> 👋</p>
        </div>

        <?php foreach ($students_by_curriculum as $curriculum => $classes): ?>
            <div class="card curriculum-card">
                <h2 class="curriculum-title curriculum-<?php echo strtolower(str_replace(['-', ' '], '', $curriculum)); ?>">
                    📚 <?php echo htmlspecialchars($curriculum); ?>
                </h2>

                <?php foreach ($classes as $class => $students): ?>
                    <div class="class-section">
                        <h3 class="class-header">
                            <?php echo htmlspecialchars($class); ?> (<?php echo count($students); ?> students)
                        </h3>

                        <table class="student-table">
                            <thead>
                                <tr>
                                    <th>Admission No</th>
                                    <th>Name</th>
                                    <th>Gender</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $s): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($s['admission_number']); ?></td>
                                        <td><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></td>
                                        <td><?php echo htmlspecialchars($s['gender']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>

            </div>
        <?php endforeach; ?>

        <?php if (empty($students_by_curriculum)): ?>
            <div class="card">
                <p class="no-students">No students enrolled yet.</p>
            </div>
        <?php endif; ?>

    </div>
</div>

</body>
</html>