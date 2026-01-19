<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Teacher') {
    header("Location: ../login.php");
    exit;
}

$student_id = (int)($_GET['student_id'] ?? 0);

/* ---------- GET TEACHER INFO ---------- */
$teacher_stmt = $pdo->prepare("
    SELECT id, category, assigned_class_id 
    FROM teachers 
    WHERE user_id = ?
");
$teacher_stmt->execute([$_SESSION['user_id']]);
$teacher = $teacher_stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    die("Teacher profile not found");
}

/* ---------- GET TEACHER'S SUBJECTS ---------- */
$teacher_subjects_stmt = $pdo->prepare("
    SELECT DISTINCT subject_name 
    FROM teacher_subjects 
    WHERE teacher_id = ?
");
$teacher_subjects_stmt->execute([$teacher['id']]);
$teacher_subjects = $teacher_subjects_stmt->fetchAll(PDO::FETCH_COLUMN);

/* ---------- GET STUDENT INFO ---------- */
$student_stmt = $pdo->prepare("
    SELECT 
        s.id,
        s.admission_number,
        s.first_name,
        s.last_name,
        s.gender,
        s.year_of_enrollment,
        s.phone_number,
        s.residential_area,
        s.class_level_id,
        cl.name as class_name,
        ct.name as curriculum_name
    FROM students s
    JOIN classes_levels cl ON s.class_level_id = cl.id
    JOIN curriculum_types ct ON cl.curriculum_type_id = ct.id
    WHERE s.id = ?
");
$student_stmt->execute([$student_id]);
$student = $student_stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student not found");
}

/* ---------- VERIFY CLASS TEACHER AUTHORIZATION ---------- */
if ($teacher['category'] == 'Class Teacher') {
    if ($student['class_level_id'] != $teacher['assigned_class_id']) {
        die("You can only view students from your assigned class.");
    }
}

/* ---------- GET STUDENT'S SUBJECTS ---------- */
$student_subjects_stmt = $pdo->prepare("
    SELECT subject_name FROM student_subjects WHERE student_id = ? ORDER BY subject_name
");
$student_subjects_stmt->execute([$student_id]);
$student_subjects = $student_subjects_stmt->fetchAll(PDO::FETCH_COLUMN);

/* ---------- GET ALL GRADES ---------- */
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
$grades_stmt->execute([$student_id]);
$all_grades = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);

// Group grades by academic year and term
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
    <title>View Student</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/teacher.css">
    <link rel="stylesheet" href="../assets/css/student.css">
    <style>
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .btn-primary {
            background: var(--navy);
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-primary:hover {
            background: var(--black);
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: var(--yellow);
            color: var(--black);
            padding: 12px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-secondary:hover {
            background: #ddb300;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>BIMS Teacher</h2>
    <a href="../teacher_dashboard.php">Dashboard</a>
    <a href="my_profile.php">My Profile</a>
    <a href="manage_grades.php">Manage Grades</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main-content">
    <div class="container">
        <!-- STUDENT INFO -->
        <div class="card welcome-card">
            <h1><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h1>
            <div class="student-info-grid">
                <div class="info-item">
                    <span class="info-label">Admission Number:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['admission_number']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Gender:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['gender']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Class:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['curriculum_name'] . ' - ' . $student['class_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Enrolled Since:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['year_of_enrollment']); ?></span>
                </div>
            </div>

            <div class="action-buttons">
                <?php if (!empty($teacher_subjects)): ?>
                    <a href="update_grades.php?student_id=<?php echo $student_id; ?>" class="btn-primary">
                        📝 Update Grades (<?php echo implode(', ', $teacher_subjects); ?>)
                    </a>
                <?php endif; ?>
                <?php if ($teacher['category'] == 'Class Teacher'): ?>
                    <a href="manage_student_subjects.php?student_id=<?php echo $student_id; ?>" class="btn-secondary">
                        📚 Manage Subjects
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- SUBJECTS -->
        <div class="card">
            <h2>📚 Subjects</h2>
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
                <p class="no-data">No subjects assigned yet.</p>
            <?php endif; ?>
        </div>

        <!-- ALL GRADES -->
        <div class="card">
            <h2>📊 Grade History</h2>
            <?php if (!empty($grades_by_period)): ?>
                <?php foreach ($grades_by_period as $period => $grades): ?>
                    <h3 class="period-header"><?php echo htmlspecialchars($period); ?></h3>
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
                                <?php foreach ($grades as $grade): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                                        <td>
                                            <span class="grade-badge">
                                                <?php echo htmlspecialchars($grade['grade']); ?>
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
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-data">No grades recorded yet.</p>
            <?php endif; ?>
        </div>

        <!-- PARENT INFO -->
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
                                <p class="parent-contact">📧 <?php echo htmlspecialchars($parent['email']); ?></p>
                                <?php if ($parent['phone_number']): ?>
                                    <p class="parent-contact">📱 <?php echo htmlspecialchars($parent['phone_number']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <a href="../teacher_dashboard.php" class="button button-yellow" style="margin-top: 20px;">← Back to Dashboard</a>
    </div>
</div>

</body>
</html>