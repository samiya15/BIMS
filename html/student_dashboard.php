<?php
session_start();
require_once __DIR__ . "/database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Student') {
    header("Location: login.php");
    exit;
}

/* ---------- GET STUDENT INFO ---------- */
$student_stmt = $pdo->prepare("
    SELECT 
        s.id, s.admission_number, s.first_name, s.last_name, s.gender,
        s.phone_number, s.residential_area, s.date_of_birth, s.parent_phone, s.parent_email,
        s.year_of_enrollment, cl.name as class_name, ct.name as curriculum_name, s.status
    FROM students s
    LEFT JOIN classes_levels cl ON s.class_level_id = cl.id
    LEFT JOIN curriculum_types ct ON cl.curriculum_type_id = ct.id
    WHERE s.user_id = ?
");
$student_stmt->execute([$_SESSION['user_id']]);
$student = $student_stmt->fetch(PDO::FETCH_ASSOC);

$student_name = ($student['first_name'] ?? 'Student') . ' ' . ($student['last_name'] ?? '');

/* ---------- GET STUDENT'S SUBJECTS ---------- */
$subjects_stmt = $pdo->prepare("SELECT subject_name FROM student_subjects WHERE student_id = ? ORDER BY subject_name");
$subjects_stmt->execute([$student['id']]);
$student_subjects = $subjects_stmt->fetchAll(PDO::FETCH_COLUMN);

/* ---------- GET RECENT GRADES (Latest Term) ---------- */
$recent_grades_stmt = $pdo->prepare("
    SELECT 
        g.subject_name, g.grade, g.final_score, g.term, g.academic_year, g.assessment_type,
        g.updated_at, t.first_name as teacher_first_name, t.last_name as teacher_last_name
    FROM grades g
    LEFT JOIN teachers t ON g.teacher_id = t.id
    WHERE g.student_id = ?
    ORDER BY g.academic_year DESC, 
        CASE g.term 
            WHEN 'Term 3' THEN 3 
            WHEN 'Term 2' THEN 2 
            WHEN 'Term 1' THEN 1 
        END DESC,
        CASE g.assessment_type
            WHEN 'End-Term' THEN 3
            WHEN 'Mid-Term' THEN 2
            WHEN 'Opener' THEN 1
        END DESC
    LIMIT 10
");
$recent_grades_stmt->execute([$student['id']]);
$recent_grades = $recent_grades_stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------- GET ALL AVAILABLE REPORT CARDS ---------- */
$current_year = (int)date('Y');
$years = range($student['year_of_enrollment'], $current_year);
$terms = ['Term 1', 'Term 2', 'Term 3'];
$assessments = ['Opener', 'Mid-Term', 'End-Term'];

// Check which report cards exist
$existing_reports = [];
$reports_check = $pdo->prepare("
    SELECT DISTINCT academic_year, term, assessment_type
    FROM grades
    WHERE student_id = ?
    ORDER BY academic_year DESC, 
        CASE term 
            WHEN 'Term 3' THEN 3 
            WHEN 'Term 2' THEN 2 
            WHEN 'Term 1' THEN 1 
        END DESC,
        CASE assessment_type
            WHEN 'End-Term' THEN 3
            WHEN 'Mid-Term' THEN 2
            WHEN 'Opener' THEN 1
        END DESC
");
$reports_check->execute([$student['id']]);
$reports_raw = $reports_check->fetchAll(PDO::FETCH_ASSOC);

foreach ($reports_raw as $report) {
    $key = $report['academic_year'] . '|' . $report['term'] . '|' . $report['assessment_type'];
    $existing_reports[$key] = true;
}

/* ---------- GET LINKED PARENTS ---------- */
$linked_parents = [];
if (!empty($student['admission_number'])) {
    $parents_stmt = $pdo->prepare("
        SELECT p.first_name, p.last_name, p.phone_number, p.relationship, u.email
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
    <style>
        .year-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 6px solid var(--navy);
        }
        .collapsible-header {
            cursor: pointer;
            user-select: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: var(--navy);
            color: white;
            border-radius: 6px;
            margin-bottom: 10px;
            transition: background 0.3s;
        }
        .collapsible-header:hover {
            background: var(--black);
        }
        .collapsible-header .toggle-icon {
            font-size: 20px;
            transition: transform 0.3s;
        }
        .collapsible-header.collapsed .toggle-icon {
            transform: rotate(-90deg);
        }
        .collapsible-content {
            max-height: 3000px;
            overflow: hidden;
            transition: max-height 0.4s ease-out;
        }
        .collapsible-content.collapsed {
            max-height: 0;
        }
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .report-card-link {
            background: linear-gradient(135deg, #f4c430 0%, #ddb300 100%);
            color: var(--black);
            padding: 15px;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: 600;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        .report-card-link:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(244, 196, 48, 0.4);
            border-color: var(--navy);
        }
        .report-icon {
            font-size: 24px;
        }
        .report-details {
            flex: 1;
            margin-left: 10px;
        }
        .report-title {
            font-size: 14px;
        }
        .report-subtitle {
            font-size: 12px;
            opacity: 0.8;
        }
    </style>
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
                <p class="no-data">No subjects assigned yet.</p>
            <?php endif; ?>
        </div>

        <!-- RECENT GRADES -->
        <?php if (!empty($recent_grades)): ?>
            <div class="card">
                <h2>📊 Recent Grades</h2>
                <div class="grades-table-container">
                    <table class="grades-table">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Assessment</th>
                                <th>Year/Term</th>
                                <th>Score</th>
                                <th>Grade</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_grades as $grade): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                                    <td><?php echo htmlspecialchars($grade['assessment_type']); ?></td>
                                    <td><?php echo $grade['academic_year'] . ' - ' . htmlspecialchars($grade['term']); ?></td>
                                    <td><?php echo $grade['final_score']; ?>/100</td>
                                    <td><span class="grade-badge"><?php echo htmlspecialchars($grade['grade'] ?? 'N/A'); ?></span></td>
                                    <td><?php echo date('M d, Y', strtotime($grade['updated_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- MY REPORT CARDS -->
        <div class="card">
            <h2>📄 My Report Cards</h2>
            <p style="color: #666; margin-bottom: 20px;">Click on any report card to view and print</p>

            <?php foreach (array_reverse($years) as $year_index => $year): ?>
                <div class="year-section">
                    <div class="collapsible-header <?php echo $year_index > 0 ? 'collapsed' : ''; ?>" onclick="toggleSection(this)">
                        <span><strong><?php echo $year; ?></strong> Academic Year</span>
                        <span class="toggle-icon">▼</span>
                    </div>
                    
                    <div class="collapsible-content <?php echo $year_index > 0 ? 'collapsed' : ''; ?>">
                        <div class="reports-grid">
                            <?php foreach ($terms as $term): ?>
                                <?php foreach ($assessments as $assessment): ?>
                                    <?php 
                                    $key = $year . '|' . $term . '|' . $assessment;
                                    if (isset($existing_reports[$key])):
                                    ?>
                                        <a href="teacher/view_report_card.php?student_id=<?php echo $student['id']; ?>&year=<?php echo $year; ?>&term=<?php echo urlencode($term); ?>&assessment=<?php echo urlencode($assessment); ?>" 
                                           class="report-card-link">
                                            <span class="report-icon">📄</span>
                                            <div class="report-details">
                                                <div class="report-title"><?php echo $term; ?> - <?php echo $assessment; ?></div>
                                                <div class="report-subtitle"><?php echo $year; ?></div>
                                            </div>
                                            <span>→</span>
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php 
                        // Check if no reports for this year
                        $has_reports = false;
                        foreach ($terms as $term) {
                            foreach ($assessments as $assessment) {
                                $key = $year . '|' . $term . '|' . $assessment;
                                if (isset($existing_reports[$key])) {
                                    $has_reports = true;
                                    break 2;
                                }
                            }
                        }
                        if (!$has_reports):
                        ?>
                            <p class="no-data">No report cards available for <?php echo $year; ?> yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
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
                <div class="stat-icon">📄</div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo count($existing_reports); ?></div>
                    <div class="stat-label">Report Cards</div>
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

<script>
function toggleSection(header) {
    const content = header.nextElementSibling;
    header.classList.toggle('collapsed');
    content.classList.toggle('collapsed');
}
</script>

</body>
</html>