<?php
session_start();
require_once __DIR__ . "/../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Student') {
    header("Location: login.php");
    exit;
}

/* ---------- GET STUDENT INFO ---------- */
$student_stmt = $pdo->prepare("
    SELECT 
        s.id,
        s.admission_number,
        s.first_name,
        s.last_name,
        s.gender,
        s.phone_number,
        s.residential_area,
        s.date_of_birth,
        s.parent_phone,
        s.parent_email,
        cl.name as class_name,
        ct.name as curriculum_name,
        s.status
    FROM students s
    LEFT JOIN classes_levels cl ON s.class_level_id = cl.id
    LEFT JOIN curriculum_types ct ON cl.curriculum_type_id = ct.id
    WHERE s.user_id = ?
");
$student_stmt->execute([$_SESSION['user_id']]);
$student = $student_stmt->fetch(PDO::FETCH_ASSOC);

$student_name = ($student['first_name'] ?? 'Student') . ' ' . ($student['last_name'] ?? '');

/* ---------- GET STUDENT'S SUBJECTS ---------- */
$subjects_stmt = $pdo->prepare("
    SELECT subject_name 
    FROM student_subjects 
    WHERE student_id = ?
    ORDER BY subject_name
");
$subjects_stmt->execute([$student['id']]);
$student_subjects = $subjects_stmt->fetchAll(PDO::FETCH_COLUMN);

/* ---------- GET STUDENT'S GRADES ---------- */
$grades_stmt = $pdo->prepare("
    SELECT 
        g.subject_name,
        g.grade,
        g.term,
        g.academic_year,
        g.updated_at,
        t.first_name as teacher_first_name,
        t.last_name as teacher_last_name
    FROM grades g
    LEFT JOIN teachers t ON g.teacher_id = t.id
    WHERE g.student_id = ?
    ORDER BY g.academic_year DESC, g.term DESC, g.subject_name
");
$grades_stmt->execute([$student['id']]);
$all_grades = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);

// Group grades by term and year
$grades_by_period = [];
foreach ($all_grades as $grade) {
    $period = $grade['academic_year'] . ' - ' . $grade['term'];
    $grades_by_period[$period][] = $grade;
}

/* ---------- GET LINKED PARENTS ---------- */
$linked_parents = [];
if (!empty($student['admission_number'])) {
    $parents_stmt = $pdo->prepare("
        SELECT 
            p.first_name, 
            p.last_name, 
            p.phone_number,
            p.relationship,
            u.email
        FROM parents p
        JOIN users u ON p.user_id = u.id
        WHERE p.linked_students LIKE ?
    ");
    $parents_stmt->execute(['%' . $student['admission_number'] . '%']);
    $linked_parents = $parents_stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="assets/css/student.css">
</head>
<body>

<div class="sidebar">
    <h2>BIMS Student</h2>
    <a href="student_dashboard.php" class="active">Dashboard</a>
    <a href="student/my_profile.php">My Profile</a>
    <a href="student/my_grades.php">My Grades</a>
    <a href="student/my_subjects.php">My Subjects</a>
    <a href="logout.php">Logout</a>
</div>

<div class="main-content">
    <div class="container">
        <!-- WELCOME CARD -->
        <div class="card welcome-card">
            <h1>Welcome, <?php echo htmlspecialchars($student_name); ?> 👋</h1>
            <div class="student-info-grid">
                <div class="info-item">
                    <span class="info-label">Admission Number:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['admission_number']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Class:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['class_name'] ?? 'Not Assigned'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Curriculum:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['curriculum_name'] ?? 'Not Assigned'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status:</span>
                    <span class="status-badge <?php echo $student['status'] ? 'active' : 'inactive'; ?>">
                        <?php echo $student['status'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- MY SUBJECTS CARD -->
        <div class="card">
            <h2>📚 My Subjects</h2>
            <?php if (!empty($student_subjects)): ?>
                <div class="subjects-grid">
                    <?php foreach ($student_subjects as $subject): ?>
                        <div class="subject-card">
                            <div class="subject-icon">📖</div>
                            <div class="subject-name"><?php echo htmlspecialchars($subject); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-data">No subjects assigned yet. Please contact your class teacher.</p>
            <?php endif; ?>
        </div>

        <!-- RECENT GRADES CARD -->
        <div class="card">
            <h2>📊 Recent Grades</h2>
            <?php if (!empty($grades_by_period)): ?>
                <?php 
                // Show only the most recent period
                $recent_period = array_key_first($grades_by_period);
                $recent_grades = $grades_by_period[$recent_period];
                ?>
                <h3 class="period-header"><?php echo htmlspecialchars($recent_period); ?></h3>
                <div class="grades-table-container">
                    <table class="grades-table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Grade</th>
                                <th>Teacher</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_grades as $grade): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                                    <td>
                                        <span class="grade-badge">
                                            <?php echo htmlspecialchars($grade['grade'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($grade['teacher_first_name']) {
                                            echo htmlspecialchars($grade['teacher_first_name'] . ' ' . $grade['teacher_last_name']);
                                        } else {
                                            echo 'Not assigned';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($grade['updated_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <a href="student/my_grades.php" class="view-all-link">View All Grades →</a>
            <?php else: ?>
                <p class="no-data">No grades available yet.</p>
            <?php endif; ?>
        </div>

        <!-- PARENT/GUARDIAN INFO -->
        <?php if (!empty($linked_parents)): ?>
            <div class="card">
                <h2>👨‍👩‍👧 Parent/Guardian Information</h2>
                <div class="parents-grid">
                    <?php foreach ($linked_parents as $parent): ?>
                        <div class="parent-card">
                            <div class="parent-icon">👤</div>
                            <div class="parent-info">
                                <h4><?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?></h4>
                                <?php if ($parent['relationship']): ?>
                                    <p class="parent-relationship"><?php echo htmlspecialchars($parent['relationship']); ?></p>
                                <?php endif; ?>
                                <p class="parent-contact">
                                    📧 <?php echo htmlspecialchars($parent['email']); ?>
                                </p>
                                <?php if ($parent['phone_number']): ?>
                                    <p class="parent-contact">
                                        📱 <?php echo htmlspecialchars($parent['phone_number']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- QUICK STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo count($student_subjects); ?></div>
                    <div class="stat-label">Subjects</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo count($all_grades); ?></div>
                    <div class="stat-label">Total Grades</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👨‍👩‍👧</div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo count($linked_parents); ?></div>
                    <div class="stat-label">Guardians</div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>