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

/* ---------- GET STUDENTS BASED ON TEACHER CATEGORY ---------- */
$students_by_curriculum = [];

if ($category == 'Head Teacher') {
    // Head teacher sees all students - GROUPED BY CURRICULUM
    $curriculums = ['CBE', '8-4-4', 'IGCSE'];
    foreach ($curriculums as $curr) {
        $stmt = $pdo->prepare("
            SELECT s.id, s.admission_number, s.first_name, s.last_name, 
                   cl.name as class_name, ct.name as curriculum_name, s.gender, s.status
            FROM students s
            LEFT JOIN classes_levels cl ON s.class_level_id = cl.id
            LEFT JOIN curriculum_types ct ON s.curriculum_type_id = ct.id
            WHERE ct.name = ? AND s.status = 1
            ORDER BY cl.level_order, s.last_name, s.first_name
        ");
        $stmt->execute([$curr]);
        $students_by_curriculum[$curr] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} elseif ($category == 'Class Teacher' && $teacher['assigned_class_id']) {
    // Class teacher sees only their assigned class
    $stmt = $pdo->prepare("
        SELECT s.id, s.admission_number, s.first_name, s.last_name, 
               cl.name as class_name, ct.name as curriculum_name, s.gender, s.status
        FROM students s
        LEFT JOIN classes_levels cl ON s.class_level_id = cl.id
        LEFT JOIN curriculum_types ct ON s.curriculum_type_id = ct.id
        WHERE s.class_level_id = ? AND s.status = 1
        ORDER BY s.last_name, s.first_name
    ");
    $stmt->execute([$teacher['assigned_class_id']]);
    $my_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    // Subject teacher sees all active students - GROUPED BY CURRICULUM
    $curriculums = ['CBE', '8-4-4', 'IGCSE'];
    foreach ($curriculums as $curr) {
        $stmt = $pdo->prepare("
            SELECT s.id, s.admission_number, s.first_name, s.last_name, 
                   cl.name as class_name, ct.name as curriculum_name, s.gender, s.status
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
    // Table doesn't exist yet
    $teacher_subjects = [];
}

/* ---------- HELPER FUNCTION: GET GRADE UPDATE PAGE BASED ON CURRICULUM ---------- */
function getGradeUpdatePage($curriculum) {
    if ($curriculum === '8-4-4') {
        return 'teacher/update_grades_844.php';
    } elseif ($curriculum === 'IGCSE') {
        return 'teacher/update_grades_igcse.php'; // You'll create this later
    } else {
        return 'teacher/update_grades.php'; // CBE default
    }
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
        .curriculum-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 10px;
        }
        .curriculum-cbe {
            background: #4caf50;
            color: white;
        }
        .curriculum-844 {
            background: #2196f3;
            color: white;
        }
        .curriculum-igcse {
            background: #9c27b0;
            color: white;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>BIMS Teacher</h2>
    <a href="teacher_dashboard.php" class="active">Dashboard</a>
    <a href="teacher/my_profile.php">My Profile</a>
    <a href="teacher/manage_grades.php">Manage Grades</a>
    <a href="teacher/view_report_card.php">View Report Cards</a>
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

        <?php if ($category == 'Class Teacher' && isset($my_students)): ?>
            <!-- CLASS TEACHER VIEW - Single Class -->
            <div class="card curriculum-card">
                <h2 class="curriculum-title cbc-title">
                    📚 My Class: <?php echo htmlspecialchars($my_students[0]['curriculum_name'] ?? ''); ?> - <?php echo htmlspecialchars($my_students[0]['class_name'] ?? ''); ?>
                </h2>
                
                <?php if (!empty($my_students)): ?>
                    <table class="student-table">
                        <thead>
                            <tr>
                                <th>Admission No.</th>
                                <th>Name</th>
                                <th>Curriculum</th>
                                <th>Gender</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_students as $student): ?>
                                <?php $grade_page = getGradeUpdatePage($student['curriculum_name']); ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['admission_number']); ?></td>
                                    <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                    <td>
                                        <span class="curriculum-badge curriculum-<?php echo strtolower(str_replace(['-', '.'], '', $student['curriculum_name'])); ?>">
                                            <?php echo htmlspecialchars($student['curriculum_name']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($student['gender']); ?></td>
                                    <td>
                                        <a href="teacher/view_student.php?student_id=<?php echo $student['id']; ?>" class="grade-button">
                                            View All Grades
                                        </a>
                                        
                                        <a href="<?php echo $grade_page; ?>?student_id=<?php echo $student['id']; ?>" class="grade-button">
                                            Update Grades
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-students">No students in your class yet.</p>
                <?php endif; ?>
            </div>
            
        <?php else: ?>
            <!-- SUBJECT TEACHER & HEAD TEACHER VIEW - All Curriculums -->
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
                <tr>
                    <td><?php echo htmlspecialchars($student['admission_number']); ?></td>
                    <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($student['gender']); ?></td>
                    <td>
                        <?php if ($category == 'Head Teacher'): ?>
                            <a href="teacher/view_student.php?student_id=<?php echo $student['id']; ?>" class="grade-button">
                                View All Grades
                            </a>
                        <?php else: ?>
                            <a href="teacher/update_grades.php?student_id=<?php echo $student['id']; ?>" class="grade-button">
                                Update Grades
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
                    
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
                        <?php endforeach; // end foreach $by_class ?>
                    <?php else: ?>
                        <p class="no-students">No students found for this curriculum.</p>
                    <?php endif; ?>
                </div>
            <?php endforeach; // end foreach $students_by_curriculum ?>
        <?php endif; // end if/else for teacher category ?>
    </div>
</div>

</body>
</html>