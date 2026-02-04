<?php
session_start();
require_once __DIR__ . "/../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Teacher') {
    header("Location: login.php");
    exit;
}

/* ---------- GET TEACHER INFO ---------- */
$teacher_stmt = $pdo->prepare("
    SELECT id, first_name, last_name, category, assigned_class_id
    FROM teachers 
    WHERE user_id = ?
");
$teacher_stmt->execute([$_SESSION['user_id']]);
$teacher = $teacher_stmt->fetch(PDO::FETCH_ASSOC);

$teacher_name = ($teacher['first_name'] ?? 'Teacher') . ' ' . ($teacher['last_name'] ?? '');
$category = $teacher['category'] ?? 'Subject Teacher';

/* ---------- FOR HEAD TEACHER: GET PENDING REVIEWS ---------- */
$pending_reviews = [];
if ($category == 'Head Teacher') {
    $pending_stmt = $pdo->prepare("
        SELECT 
            gs.id,
            gs.student_id,
            gs.academic_year,
            gs.term,
            gs.assessment_type,
            gs.class_teacher_comment,
            gs.submitted_to_principal_at,
            s.admission_number,
            s.first_name,
            s.last_name,
            cl.name as class_name,
            ct.name as curriculum_name,
            COUNT(DISTINCT g.subject_name) as subjects_count
        FROM grade_submissions gs
        JOIN students s ON gs.student_id = s.id
        JOIN classes_levels cl ON s.class_level_id = cl.id
        JOIN curriculum_types ct ON cl.curriculum_type_id = ct.id
        LEFT JOIN grades g ON gs.student_id = g.student_id 
            AND gs.academic_year = g.academic_year 
            AND gs.term = g.term 
            AND gs.assessment_type = g.assessment_type
        WHERE gs.status = 'AWAITING_PRINCIPAL'
        GROUP BY gs.id
        ORDER BY gs.submitted_to_principal_at DESC, cl.name, s.last_name, s.first_name
    ");
    $pending_stmt->execute();
    $pending_reviews = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ---------- FUNCTION TO GET CORRECT UPDATE PAGE ---------- */
function getGradeUpdatePage($curriculum_name) {
    if ($curriculum_name == '8-4-4') {
        return 'teacher/update_grades_844.php';
    } elseif ($curriculum_name == 'IGCSE') {
        return 'teacher/update_grades_igcse.php';
    } else {
        return 'teacher/update_grades.php'; // CBE
    }
}

/* ---------- GET STUDENTS BASED ON TEACHER CATEGORY ---------- */
$students_by_curriculum = [];
$my_assigned_class_students = []; // For class teacher's assigned class

// First, get the curricula this teacher actually teaches
$teacher_curricula = [];
if (!empty($teacher_subjects)) {
    foreach ($teacher_subjects as $curr => $subjects) {
        $teacher_curricula[] = $curr;
    }
}

if ($category == 'Head Teacher') {
    // Head teacher sees all students
    $curriculums = ['CBE', '8-4-4', 'IGCSE'];
    foreach ($curriculums as $curr) {
        $stmt = $pdo->prepare("
            SELECT s.id, s.admission_number, s.first_name, s.last_name, 
                   cl.name as class_name, ct.name as curriculum_name, s.gender, s.status,
                   s.class_level_id
            FROM students s
            LEFT JOIN classes_levels cl ON s.class_level_id = cl.id
            LEFT JOIN curriculum_types ct ON s.curriculum_type_id = ct.id
            WHERE ct.name = ? AND s.status = 1
            ORDER BY cl.level_order, s.last_name, s.first_name
        ");
        $stmt->execute([$curr]);
        $students_by_curriculum[$curr] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} elseif ($category == 'Class Teacher') {
    // Class teacher sees:
    // 1. Their assigned class students (with full access)
    // 2. Students from other classes in curricula they teach (subject teacher access only)
    
    // Get assigned class students
    if ($teacher['assigned_class_id']) {
        $stmt = $pdo->prepare("
            SELECT s.id, s.admission_number, s.first_name, s.last_name, 
                   cl.name as class_name, ct.name as curriculum_name, s.gender, s.status,
                   s.class_level_id
            FROM students s
            LEFT JOIN classes_levels cl ON s.class_level_id = cl.id
            LEFT JOIN curriculum_types ct ON s.curriculum_type_id = ct.id
            WHERE s.class_level_id = ? AND s.status = 1
            ORDER BY s.last_name, s.first_name
        ");
        $stmt->execute([$teacher['assigned_class_id']]);
        $my_assigned_class_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get students from curricula they teach (excluding assigned class)
    foreach ($teacher_curricula as $curr) {
        $stmt = $pdo->prepare("
            SELECT s.id, s.admission_number, s.first_name, s.last_name, 
                   cl.name as class_name, ct.name as curriculum_name, s.gender, s.status,
                   s.class_level_id
            FROM students s
            LEFT JOIN classes_levels cl ON s.class_level_id = cl.id
            LEFT JOIN curriculum_types ct ON s.curriculum_type_id = ct.id
            WHERE ct.name = ? AND s.status = 1
            ORDER BY cl.level_order, s.last_name, s.first_name
        ");
        $stmt->execute([$curr]);
        $students_by_curriculum[$curr] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} else {
    // Subject teacher sees only students from curricula they teach
    foreach ($teacher_curricula as $curr) {
        $stmt = $pdo->prepare("
            SELECT s.id, s.admission_number, s.first_name, s.last_name, 
                   cl.name as class_name, ct.name as curriculum_name, s.gender, s.status,
                   s.class_level_id
            FROM students s
            LEFT JOIN classes_levels cl ON s.class_level_id = cl.id
            LEFT JOIN curriculum_types ct ON s.curriculum_type_id = ct.id
            WHERE ct.name = ? AND s.status = 1
            ORDER BY cl.level_order, s.last_name, s.first_name
        ");
        $stmt->execute([$curr]);
        $students_by_curriculum[$curr] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/* ---------- GET TEACHER'S SUBJECTS ---------- */
$teacher_subjects = [];
try {
    $subjects_stmt = $pdo->prepare("
        SELECT ts.curriculum_type_id, ct.name as curriculum_name, ts.subject_name
        FROM teacher_subjects ts
        JOIN curriculum_types ct ON ts.curriculum_type_id = ct.id
        WHERE ts.teacher_id = ?
        ORDER BY ct.id, ts.subject_name
    ");
    $subjects_stmt->execute([$teacher['id']]);
    $teacher_subjects_raw = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize by curriculum
    foreach ($teacher_subjects_raw as $row) {
        $teacher_subjects[$row['curriculum_name']][] = $row['subject_name'];
    }
} catch (PDOException $e) {
    $teacher_subjects = [];
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
    <style>
        .collapsible-header {
            cursor: pointer;
            user-select: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: white;
            border-radius: 6px;
            margin-bottom: 10px;
            transition: background 0.3s;
        }
        .collapsible-header:hover {
            background: #f5f5f5;
        }
        .collapsible-header .toggle-icon {
            font-size: 20px;
            transition: transform 0.3s;
        }
        .collapsible-header.collapsed .toggle-icon {
            transform: rotate(-90deg);
        }
        .collapsible-content {
            max-height: 2000px;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        .collapsible-content.collapsed {
            max-height: 0;
        }
        .grade-button {
            background: var(--yellow);
            color: var(--black);
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            margin-top: 10px;
            transition: all 0.3s;
        }
        .grade-button:hover {
            background: #ddb300;
            transform: translateY(-2px);
        }
        .category-badge {
            background: var(--navy);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        .subjects-list {
            background: #f9f9f9;
            padding: 10px;
            border-radius: 6px;
            margin-top: 10px;
        }
        .subjects-list strong {
            color: var(--navy);
        }
        .pending-reviews-card {
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .pending-reviews-card h3 {
            color: white;
            margin-bottom: 15px;
        }
        .pending-count {
            background: white;
            color: #ff9800;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 15px;
        }
        .review-button {
            background: white;
            color: #ff9800;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            transition: all 0.3s;
        }
        .review-button:hover {
            background: #f5f5f5;
            transform: translateY(-2px);
        }
        .pending-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 15px;
        }
        .pending-table table {
            width: 100%;
            border-collapse: collapse;
        }
        .pending-table th {
            background: var(--navy);
            color: white;
            padding: 12px;
            text-align: left;
            font-size: 13px;
        }
        .pending-table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            font-size: 13px;
        }
        .pending-table tr:hover {
            background: #f9f9f9;
        }
        .add-comment-btn {
            background: #4caf50;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 13px;
            display: inline-block;
            transition: all 0.3s;
        }
        .add-comment-btn:hover {
            background: #45a049;
            transform: translateY(-1px);
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>BIMS Teacher</h2>
    <a href="teacher_dashboard.php" class="active">Dashboard</a>
    <a href="teacher/my_profile.php">My Profile</a>
    <?php if ($category == 'Head Teacher'): ?>
        <a href="teacher/review_student_reports.php">📋 Review Reports (<?php echo count($pending_reviews); ?>)</a>
    <?php endif; ?>
    <a href="logout.php">Logout</a>
</div>

<div class="main-content">
    <div class="container">
        <div class="card">
            <h1>Teacher Dashboard</h1>
            <p>Welcome, <?php echo htmlspecialchars($teacher_name); ?> 👋</p>
            <p><span class="category-badge"><?php echo htmlspecialchars($category); ?></span></p>
            
            <?php if (!empty($teacher_subjects)): ?>
                <div class="subjects-list">
                    <strong>Teaching Subjects:</strong>
                    <?php foreach ($teacher_subjects as $curr => $subjects): ?>
                        <p><strong><?php echo htmlspecialchars($curr); ?>:</strong> <?php echo htmlspecialchars(implode(', ', $subjects)); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($category == 'Head Teacher' && !empty($pending_reviews)): ?>
            <!-- PENDING REVIEWS CARD -->
            <div class="pending-reviews-card">
                <h3>📋 Pending Report Card Reviews</h3>
                <div class="pending-count">
                    <?php echo count($pending_reviews); ?> report<?php echo count($pending_reviews) > 1 ? 's' : ''; ?> awaiting your approval
                </div>
                <p style="margin-bottom: 15px;">Class teachers have submitted these reports for your review and approval.</p>

                <?php 
                // Group by curriculum
                $reviews_by_curriculum = [];
                foreach ($pending_reviews as $review) {
                    $reviews_by_curriculum[$review['curriculum_name']][] = $review;
                }
                
                foreach ($reviews_by_curriculum as $curr_name => $curr_reviews): 
                ?>
                    <div style="background: white; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                        <h4 style="color: var(--navy); margin-bottom: 10px; padding-bottom: 10px; border-bottom: 2px solid var(--yellow);">
                            <?php echo htmlspecialchars($curr_name); ?> Curriculum (<?php echo count($curr_reviews); ?> pending)
                        </h4>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--navy); color: white;">
                                    <th style="padding: 10px; text-align: left; font-size: 12px;">Student</th>
                                    <th style="padding: 10px; text-align: left; font-size: 12px;">Class</th>
                                    <th style="padding: 10px; text-align: left; font-size: 12px;">Year</th>
                                    <th style="padding: 10px; text-align: left; font-size: 12px;">Term</th>
                                    <th style="padding: 10px; text-align: left; font-size: 12px;">Assessment</th>
                                    <th style="padding: 10px; text-align: left; font-size: 12px;">Submitted</th>
                                    <th style="padding: 10px; text-align: left; font-size: 12px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($curr_reviews as $review): ?>
                                    <tr style="border-bottom: 1px solid #ddd;">
                                        <td style="padding: 10px; font-size: 13px;"><strong><?php echo htmlspecialchars($review['first_name'] . ' ' . $review['last_name']); ?></strong></td>
                                        <td style="padding: 10px; font-size: 13px;"><?php echo htmlspecialchars($review['class_name']); ?></td>
                                        <td style="padding: 10px; font-size: 13px;"><?php echo $review['academic_year']; ?></td>
                                        <td style="padding: 10px; font-size: 13px;"><?php echo $review['term']; ?></td>
                                        <td style="padding: 10px; font-size: 13px;"><?php echo $review['assessment_type']; ?></td>
                                        <td style="padding: 10px; font-size: 13px;"><?php echo date('M d', strtotime($review['submitted_to_principal_at'])); ?></td>
                                        <td style="padding: 10px;">
                                            <a href="teacher/add_principal_comment.php?student_id=<?php echo $review['student_id']; ?>&year=<?php echo $review['academic_year']; ?>&term=<?php echo urlencode($review['term']); ?>&assessment=<?php echo urlencode($review['assessment_type']); ?>" 
                                               class="add-comment-btn">
                                                Add Comment & Release
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($category == 'Class Teacher' && !empty($my_assigned_class_students)): ?>
            <!-- MY ASSIGNED CLASS (FULL ACCESS) -->
            <div class="card curriculum-card" style="border-left: 6px solid #4caf50;">
                <h2 class="curriculum-title" style="background: #4caf50; color: white;">
                    🏫 My Assigned Class: <?php echo htmlspecialchars($my_assigned_class_students[0]['curriculum_name'] ?? ''); ?> - <?php echo htmlspecialchars($my_assigned_class_students[0]['class_name'] ?? ''); ?>
                    <span style="font-size: 14px; background: white; color: #4caf50; padding: 4px 12px; border-radius: 12px; margin-left: 10px;">Full Access</span>
                </h2>
                
                <table class="student-table">
                    <thead>
                        <tr>
                            <th>Admission No.</th>
                            <th>Name</th>
                            <th>Gender</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($my_assigned_class_students as $student): ?>
                            <?php $grade_page = getGradeUpdatePage($student['curriculum_name']); ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['admission_number']); ?></td>
                                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['gender']); ?></td>
                                <td>
                                    <a href="<?php echo $grade_page; ?>?student_id=<?php echo $student['id']; ?>" class="grade-button">
                                        Update Grades (All Subjects)
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <?php if ($category != 'Head Teacher' && !empty($students_by_curriculum)): ?>
            <!-- SUBJECT TEACHER VIEW / CLASS TEACHER FOR OTHER CLASSES -->
            <?php if ($category == 'Class Teacher'): ?>
                <div class="card" style="background: #fff9e6; border-left: 6px solid #ff9800;">
                    <p style="color: #666; margin: 0;">
                        ℹ️ <strong>Note:</strong> For students below (not in your assigned class), you can only update the subjects you teach.
                    </p>
                </div>
            <?php endif; ?>
            
            <?php
            $curriculum_colors = [
                'CBE' => ['title' => 'cbc-title', 'color' => '#2ecc71'],
                '8-4-4' => ['title' => 'system-844-title', 'color' => '#3498db'],
                'IGCSE' => ['title' => 'igcse-title', 'color' => '#9b59b6']
            ];
            
            foreach ($students_by_curriculum as $curr_name => $students):
                $color_class = $curriculum_colors[$curr_name]['title'] ?? '';
                
                // Group by class
                $by_class = [];
                foreach ($students as $student) {
                    if (!empty($student['class_name'])) {
                        $by_class[$student['class_name']][] = $student;
                    }
                }
            ?>
                <div class="card curriculum-card">
                    <h2 class="curriculum-title <?php echo $color_class; ?>">
                        📚 <?php echo htmlspecialchars($curr_name); ?> Curriculum
                    </h2>
                    
                    <?php if (!empty($by_class)): ?>
                        <?php foreach ($by_class as $class_name => $class_students): ?>
                            <div class="class-section">
                                <div class="collapsible-header" onclick="toggleSection(this)">
                                    <h3 style="margin: 0;"><?php echo htmlspecialchars($class_name); ?> (<?php echo count($class_students); ?> students)</h3>
                                    <span class="toggle-icon">▼</span>
                                </div>
                                
                                <div class="collapsible-content">
                                    <table class="student-table">
                                        <thead>
                                            <tr>
                                                <th>Admission No.</th>
                                                <th>Name</th>
                                                <th>Gender</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($class_students as $student): ?>
                                                <?php $grade_page = getGradeUpdatePage($curr_name); ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($student['admission_number']); ?></td>
                                                    <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($student['gender']); ?></td>
                                                    <td>
                                                        <a href="<?php echo $grade_page; ?>?student_id=<?php echo $student['id']; ?>" class="grade-button">
                                                            <?php 
                                                            if ($category == 'Class Teacher') {
                                                                // Check if this is their assigned class
                                                                $is_my_class = ($student['class_level_id'] == $teacher['assigned_class_id']);
                                                                echo $is_my_class ? 'Update Grades (All Subjects)' : 'Update Grades (My Subjects)';
                                                            } else {
                                                                echo 'Update Grades';
                                                            }
                                                            ?>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-students">No students enrolled in <?php echo htmlspecialchars($curr_name); ?> yet.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

<script>
function toggleSection(header) {
    const content = header.nextElementSibling;
    header.classList.toggle('collapsed');
    content.classList.toggle('collapsed');
}

// Collapse all sections by default
window.addEventListener('DOMContentLoaded', function() {
    const headers = document.querySelectorAll('.collapsible-header');
    headers.forEach(header => {
        header.classList.add('collapsed');
        header.nextElementSibling.classList.add('collapsed');
    });
});
</script>

</body>
</html>