<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Teacher') {
    header("Location: ../login.php");
    exit;
}

/* ---------- GET TEACHER INFO ---------- */
$teacher_stmt = $pdo->prepare("SELECT id, category, assigned_class_id FROM teachers WHERE user_id = ?");
$teacher_stmt->execute([$_SESSION['user_id']]);
$teacher = $teacher_stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher || $teacher['category'] !== 'Class Teacher' || !$teacher['assigned_class_id']) {
    die("This page is only accessible to class teachers with an assigned class.");
}

/* ---------- GET CLASS INFO ---------- */
$class_stmt = $pdo->prepare("
    SELECT cl.id, cl.name, ct.name as curriculum_name
    FROM classes_levels cl
    JOIN curriculum_types ct ON cl.curriculum_type_id = ct.id
    WHERE cl.id = ?
");
$class_stmt->execute([$teacher['assigned_class_id']]);
$class_info = $class_stmt->fetch(PDO::FETCH_ASSOC);

/* ---------- GET STUDENTS IN CLASS ---------- */
$students_stmt = $pdo->prepare("
    SELECT id, admission_number, first_name, last_name
    FROM students
    WHERE class_level_id = ? AND status = 1
    ORDER BY last_name, first_name
");
$students_stmt->execute([$teacher['assigned_class_id']]);
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------- HANDLE COMMENT SUBMISSION ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'submit_to_principal') {
    $student_id = (int)$_POST['student_id'];
    $year = (int)$_POST['year'];
    $term = $_POST['term'];
    $assessment = $_POST['assessment'];
    $class_teacher_comment = trim($_POST['class_teacher_comment']);
    
    try {
        // Check if submission exists
        $check = $pdo->prepare("
            SELECT id FROM grade_submissions 
            WHERE student_id = ? AND academic_year = ? AND term = ? AND assessment_type = ?
        ");
        $check->execute([$student_id, $year, $term, $assessment]);
        $submission = $check->fetch();
        
        if ($submission) {
            // Update existing submission
            $update = $pdo->prepare("
                UPDATE grade_submissions 
                SET class_teacher_comment = ?, 
                    status = 'AWAITING_PRINCIPAL',
                    submitted_to_principal_at = NOW()
                WHERE id = ?
            ");
            $update->execute([$class_teacher_comment, $submission['id']]);
        } else {
            // Create new submission
            $insert = $pdo->prepare("
                INSERT INTO grade_submissions 
                (student_id, teacher_id, academic_year, term, assessment_type, class_teacher_comment, status, submitted_to_principal_at, is_locked)
                VALUES (?, ?, ?, ?, ?, ?, 'AWAITING_PRINCIPAL', NOW(), 1)
            ");
            $insert->execute([$student_id, $teacher['id'], $year, $term, $assessment, $class_teacher_comment]);
        }
        
        header("Location: manage_class_reports.php?success=submitted&student=" . $student_id);
        exit;
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

/* ---------- GET REPORT STATUS FOR ALL STUDENTS ---------- */
$current_year = (int)date('Y');
$terms = ['Term 1', 'Term 2', 'Term 3'];
$assessments_cbe = ['Opener', 'Mid-Term', 'End-Term'];
$assessments_844 = ['Opener', 'Mid-Term', 'End-Term'];
$assessments_igcse = ['Mid-Term', 'End-Term'];

$curriculum = $class_info['curriculum_name'];
if ($curriculum === 'IGCSE') {
    $assessments = $assessments_igcse;
} else {
    $assessments = $assessments_cbe; // Both CBE and 8-4-4 use same assessments
}

// Get all grade submissions status
$submissions_stmt = $pdo->prepare("
    SELECT student_id, academic_year, term, assessment_type, status, class_teacher_comment,
           COUNT(DISTINCT g.subject_name) as subjects_graded
    FROM grade_submissions gs
    LEFT JOIN grades g ON gs.student_id = g.student_id 
        AND gs.academic_year = g.academic_year 
        AND gs.term = g.term 
        AND gs.assessment_type = g.assessment_type
    WHERE gs.student_id IN (SELECT id FROM students WHERE class_level_id = ?)
    GROUP BY gs.student_id, gs.academic_year, gs.term, gs.assessment_type
");
$submissions_stmt->execute([$teacher['assigned_class_id']]);
$submissions_data = $submissions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize by student
$student_reports = [];
foreach ($submissions_data as $sub) {
    $key = $sub['student_id'] . '_' . $sub['academic_year'] . '_' . $sub['term'] . '_' . $sub['assessment_type'];
    $student_reports[$key] = $sub;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Class Reports</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/teacher.css">
    <style>
        .class-banner {
            background: linear-gradient(135deg, var(--navy) 0%, #1a3a52 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .class-banner h3 {
            color: var(--yellow);
        }
        .reports-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
        }
        .reports-table th {
            background: var(--navy);
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }
        .reports-table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        .reports-table tr:hover {
            background: #f5f5f5;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-pending {
            background: #ff9800;
            color: white;
        }
        .status-awaiting {
            background: #2196f3;
            color: white;
        }
        .status-released {
            background: #4caf50;
            color: white;
        }
        .btn-review {
            background: var(--yellow);
            color: var(--black);
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 13px;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-review:hover {
            background: #ddb300;
            transform: translateY(-1px);
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        .modal-content {
            background: white;
            margin: 50px auto;
            padding: 30px;
            width: 90%;
            max-width: 800px;
            border-radius: 8px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .modal-header {
            border-bottom: 2px solid var(--navy);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .modal-header h3 {
            color: var(--navy);
        }
        .close {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #999;
        }
        .close:hover {
            color: #000;
        }
        .grades-summary {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .grades-summary table {
            width: 100%;
            border-collapse: collapse;
        }
        .grades-summary th, .grades-summary td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .comment-box {
            margin: 20px 0;
        }
        .comment-box label {
            display: block;
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 10px;
        }
        .comment-box textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            min-height: 100px;
            font-family: Arial, sans-serif;
            font-size: 14px;
        }
        .year-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .year-selector select {
            padding: 10px;
            border: 2px solid var(--navy);
            border-radius: 6px;
            font-size: 14px;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>BIMS Teacher</h2>
    <a href="../teacher_dashboard.php">Dashboard</a>
    <a href="my_profile.php">My Profile</a>
    <a href="manage_class_reports.php" class="active">Manage Reports</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main-content">
    <div class="container">
        <div class="card">
            <h2>📋 Manage Class Report Cards</h2>

            <div class="class-banner">
                <h3>Your Class: <?php echo htmlspecialchars($class_info['curriculum_name'] . ' - ' . $class_info['name']); ?></h3>
                <p>Review student grades and submit reports to Head Teacher</p>
            </div>

            <?php if (isset($_GET['success']) && $_GET['success'] === 'submitted'): ?>
                <div class="alert-success">✅ Report successfully submitted to Head Teacher!</div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <div class="year-selector">
                <select id="yearFilter" onchange="filterReports()">
                    <option value="">All Years</option>
                    <?php for ($y = $current_year; $y >= $current_year - 5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $current_year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
                <select id="termFilter" onchange="filterReports()">
                    <option value="">All Terms</option>
                    <?php foreach ($terms as $t): ?>
                        <option value="<?php echo $t; ?>"><?php echo $t; ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="assessmentFilter" onchange="filterReports()">
                    <option value="">All Assessments</option>
                    <?php foreach ($assessments as $a): ?>
                        <option value="<?php echo $a; ?>"><?php echo $a; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <table class="reports-table" id="reportsTable">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Admission No</th>
                        <th>Year</th>
                        <th>Term</th>
                        <th>Assessment</th>
                        <th>Subjects Graded</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $student): ?>
                        <?php 
                        $years_to_show = [$current_year];
                        foreach ($years_to_show as $year):
                            foreach ($terms as $term):
                                foreach ($assessments as $assessment):
                                    $key = $student['id'] . '_' . $year . '_' . $term . '_' . $assessment;
                                    $report = $student_reports[$key] ?? null;
                                    $subjects_count = $report['subjects_graded'] ?? 0;
                                    $status = $report['status'] ?? 'PENDING';
                                    $has_comment = !empty($report['class_teacher_comment']);
                        ?>
                        <tr data-year="<?php echo $year; ?>" data-term="<?php echo $term; ?>" data-assessment="<?php echo $assessment; ?>">
                            <td><strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($student['admission_number']); ?></td>
                            <td><?php echo $year; ?></td>
                            <td><?php echo $term; ?></td>
                            <td><?php echo $assessment; ?></td>
                            <td><?php echo $subjects_count; ?> subjects</td>
                            <td>
                                <?php if ($status === 'RELEASED'): ?>
                                    <span class="status-badge status-released">✅ Released</span>
                                <?php elseif ($status === 'AWAITING_PRINCIPAL'): ?>
                                    <span class="status-badge status-awaiting">⏳ With Principal</span>
                                <?php else: ?>
                                    <span class="status-badge status-pending">📝 Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($subjects_count > 0 && $status !== 'RELEASED'): ?>
                                    <button class="btn-review" onclick="openReviewModal(<?php echo $student['id']; ?>, <?php echo $year; ?>, '<?php echo $term; ?>', '<?php echo $assessment; ?>', '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'], ENT_QUOTES); ?>', '<?php echo $has_comment ? htmlspecialchars($report['class_teacher_comment'], ENT_QUOTES) : ''; ?>')">
                                        <?php echo $status === 'AWAITING_PRINCIPAL' ? 'Edit Comment' : 'Review & Submit'; ?>
                                    </button>
                                <?php elseif ($status === 'RELEASED'): ?>
                                    <span style="color: #4caf50;">✓ Completed</span>
                                <?php else: ?>
                                    <span style="color: #999;">No grades yet</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php 
                                endforeach;
                            endforeach;
                        endforeach;
                        ?>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <a href="../teacher_dashboard.php" class="button button-yellow" style="margin-top: 20px;">← Back to Dashboard</a>
        </div>
    </div>
</div>

<!-- Review Modal -->
<div id="reviewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span class="close" onclick="closeReviewModal()">&times;</span>
            <h3 id="modalTitle">Review Report Card</h3>
        </div>

        <div class="grades-summary" id="gradesSummary">
            <p>Loading grades...</p>
        </div>

        <form method="POST" id="reviewForm">
            <input type="hidden" name="action" value="submit_to_principal">
            <input type="hidden" name="student_id" id="modal_student_id">
            <input type="hidden" name="year" id="modal_year">
            <input type="hidden" name="term" id="modal_term">
            <input type="hidden" name="assessment" id="modal_assessment">

            <div class="comment-box">
                <label>Class Teacher's Overall Comment:</label>
                <textarea name="class_teacher_comment" id="modal_comment" required placeholder="Enter your overall assessment of this student's performance..."></textarea>
                <small style="color: #666;">This comment will appear on the student's report card.</small>
            </div>

            <button type="submit" class="button" style="width: 100%;">Submit to Head Teacher for Review</button>
        </form>
    </div>
</div>

<script>
function filterReports() {
    const year = document.getElementById('yearFilter').value;
    const term = document.getElementById('termFilter').value;
    const assessment = document.getElementById('assessmentFilter').value;
    
    const rows = document.querySelectorAll('#reportsTable tbody tr');
    rows.forEach(row => {
        const matchYear = !year || row.dataset.year === year;
        const matchTerm = !term || row.dataset.term === term;
        const matchAssessment = !assessment || row.dataset.assessment === assessment;
        
        row.style.display = (matchYear && matchTerm && matchAssessment) ? '' : 'none';
    });
}

function openReviewModal(studentId, year, term, assessment, studentName, existingComment) {
    document.getElementById('modalTitle').textContent = `Review: ${studentName} - ${term} ${assessment} ${year}`;
    document.getElementById('modal_student_id').value = studentId;
    document.getElementById('modal_year').value = year;
    document.getElementById('modal_term').value = term;
    document.getElementById('modal_assessment').value = assessment;
    document.getElementById('modal_comment').value = existingComment || '';
    
    // Fetch and display grades
    fetchGrades(studentId, year, term, assessment);
    
    document.getElementById('reviewModal').style.display = 'block';
}

function closeReviewModal() {
    document.getElementById('reviewModal').style.display = 'none';
}

function fetchGrades(studentId, year, term, assessment) {
    const summaryDiv = document.getElementById('gradesSummary');
    summaryDiv.innerHTML = '<p>Loading grades...</p>';
    
    fetch(`get_student_grades.php?student_id=${studentId}&year=${year}&term=${encodeURIComponent(term)}&assessment=${encodeURIComponent(assessment)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.grades.length > 0) {
                let html = '<table><thead><tr><th>Subject</th><th>Grade</th><th>Points</th></tr></thead><tbody>';
                data.grades.forEach(g => {
                    html += `<tr><td>${g.subject_name}</td><td><strong>${g.grade}</strong></td><td>${g.grade_points}</td></tr>`;
                });
                html += '</tbody></table>';
                html += `<p style="margin-top: 15px;"><strong>Total Points:</strong> ${data.total_points} | <strong>Mean Points:</strong> ${data.mean_points}</p>`;
                summaryDiv.innerHTML = html;
            } else {
                summaryDiv.innerHTML = '<p style="color: #f44336;">No grades found for this assessment.</p>';
            }
        })
        .catch(error => {
            summaryDiv.innerHTML = '<p style="color: #f44336;">Error loading grades.</p>';
        });
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('reviewModal');
    if (event.target == modal) {
        closeReviewModal();
    }
}
</script>

</body>
</html>