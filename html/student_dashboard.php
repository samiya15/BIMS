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
        s.id, s.admission_number, s.first_name, s.last_name, s.gender,
        s.year_of_enrollment, cl.name as class_name, ct.name as curriculum_name, s.status
    FROM students s
    LEFT JOIN classes_levels cl ON s.class_level_id = cl.id
    LEFT JOIN curriculum_types ct ON cl.curriculum_type_id = ct.id
    WHERE s.user_id = ?
");
$student_stmt->execute([$_SESSION['user_id']]);
$student = $student_stmt->fetch(PDO::FETCH_ASSOC);

$student_name = ($student['first_name'] ?? 'Student') . ' ' . ($student['last_name'] ?? '');

/* ---------- GET ALL AVAILABLE REPORT CARDS ---------- */
$current_year = (int)date('Y');
$years = range($student['year_of_enrollment'], $current_year);
$terms = ['Term 1', 'Term 2', 'Term 3'];
$assessments = ['Opener', 'Mid-Term', 'End-Term'];

// Check which report cards exist
$existing_reports = [];
$reports_check = $pdo->prepare("
    SELECT DISTINCT academic_year, term, assessment_type, COUNT(DISTINCT subject_name) as subject_count
    FROM grades
    WHERE student_id = ?
    GROUP BY academic_year, term, assessment_type
    HAVING COUNT(DISTINCT subject_name) > 0
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
    $existing_reports[$report['academic_year']][$report['term']][$report['assessment_type']] = $report['subject_count'];
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
        .term-section {
            margin-bottom: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 6px;
        }
        .term-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--yellow);
        }
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
        }
        .report-card-link {
            background: linear-gradient(135deg, #f4c430 0%, #ddb300 100%);
            color: var(--black);
            padding: 20px;
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
            font-size: 32px;
        }
        .report-details {
            flex: 1;
            margin: 0 15px;
        }
        .report-title {
            font-size: 16px;
            margin-bottom: 5px;
        }
        .report-subtitle {
            font-size: 12px;
            opacity: 0.8;
        }
        .report-count {
            font-size: 11px;
            background: rgba(0,0,0,0.1);
            padding: 3px 8px;
            border-radius: 12px;
            margin-top: 5px;
            display: inline-block;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>BIMS Student</h2>
    <a href="student_dashboard.php" class="active">Dashboard</a>
    <a href="student/my_profile.php">My Profile</a>
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

        <!-- MY REPORT CARDS -->
        <div class="card">
            <h2>📄 My Report Cards</h2>
            <p style="color: #666; margin-bottom: 20px;">Click on any report card to view and print your results</p>

            <?php if (empty($existing_reports)): ?>
                <p class="no-data">No report cards available yet. Your teacher will upload your grades soon.</p>
            <?php else: ?>
                <?php foreach (array_reverse($years) as $year_index => $year): ?>
                    <?php if (isset($existing_reports[$year])): ?>
                        <div class="year-section">
                            <div class="collapsible-header <?php echo $year_index > 0 ? 'collapsed' : ''; ?>" onclick="toggleSection(this)">
                                <span><strong><?php echo $year; ?></strong> Academic Year</span>
                                <span class="toggle-icon">▼</span>
                            </div>
                            
                            <div class="collapsible-content <?php echo $year_index > 0 ? 'collapsed' : ''; ?>">
                                <?php foreach ($terms as $term): ?>
                                    <?php if (isset($existing_reports[$year][$term])): ?>
                                        <div class="term-section">
                                            <div class="term-title"><?php echo $term; ?></div>
                                            <div class="reports-grid">
                                                <?php foreach ($assessments as $assessment): ?>
                                                    <?php if (isset($existing_reports[$year][$term][$assessment])): ?>
                                                        <a href="teacher/view_report_card.php?student_id=<?php echo $student['id']; ?>&year=<?php echo $year; ?>&term=<?php echo urlencode($term); ?>&assessment=<?php echo urlencode($assessment); ?>" 
                                                           class="report-card-link"
                                                           target="_blank">
                                                            <span class="report-icon">📄</span>
                                                            <div class="report-details">
                                                                <div class="report-title"><?php echo $assessment; ?></div>
                                                                <div class="report-subtitle"><?php echo $year; ?> - <?php echo $term; ?></div>
                                                                <div class="report-count"><?php echo $existing_reports[$year][$term][$assessment]; ?> subjects</div>
                                                            </div>
                                                            <span style="font-size: 24px;">→</span>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
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