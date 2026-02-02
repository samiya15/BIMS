<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Teacher') {
    header("Location: ../login.php");
    exit;
}

/* ---------- GET TEACHER INFO ---------- */
$teacher_stmt = $pdo->prepare("SELECT id, category FROM teachers WHERE user_id = ?");
$teacher_stmt->execute([$_SESSION['user_id']]);
$teacher = $teacher_stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher || $teacher['category'] !== 'Head Teacher') {
    die("This page is only accessible to Head Teachers.");
}

/* ---------- HANDLE REPORT RELEASE ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'release_to_students') {
    $student_id = (int)$_POST['student_id'];
    $year = (int)$_POST['year'];
    $term = $_POST['term'];
    $assessment = $_POST['assessment'];
    $principal_comment = trim($_POST['principal_comment']);
    
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
                SET principal_comment = ?, 
                    status = 'RELEASED',
                    released_to_students_at = NOW()
                WHERE id = ?
            ");
            $update->execute([$principal_comment, $submission['id']]);
            
            $success = "Report successfully released to student and parents!";
        } else {
            $error = "Grade submission not found.";
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

/* ---------- GET ALL PENDING REPORTS ---------- */
$pending_reports_stmt = $pdo->prepare("
    SELECT 
        gs.id,
        gs.student_id,
        gs.academic_year,
        gs.term,
        gs.assessment_type,
        gs.class_teacher_comment,
        gs.principal_comment,
        gs.status,
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
$pending_reports_stmt->execute();
$pending_reports = $pending_reports_stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------- GET RECENTLY RELEASED REPORTS ---------- */
$released_reports_stmt = $pdo->prepare("
    SELECT 
        gs.student_id,
        gs.academic_year,
        gs.term,
        gs.assessment_type,
        gs.released_to_students_at,
        s.admission_number,
        s.first_name,
        s.last_name,
        cl.name as class_name,
        ct.name as curriculum_name
    FROM grade_submissions gs
    JOIN students s ON gs.student_id = s.id
    JOIN classes_levels cl ON s.class_level_id = cl.id
    JOIN curriculum_types ct ON cl.curriculum_type_id = ct.id
    WHERE gs.status = 'RELEASED'
    ORDER BY gs.released_to_students_at DESC
    LIMIT 50
");
$released_reports_stmt->execute();
$released_reports = $released_reports_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Student Reports</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/teacher.css">
    <style>
        .header-banner {
            background: linear-gradient(135deg, var(--navy) 0%, #1a3a52 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .header-banner h3 {
            color: var(--yellow);
        }
        .pending-count {
            background: var(--yellow);
            color: var(--black);
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
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
            font-size: 13px;
        }
        .reports-table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            font-size: 13px;
        }
        .reports-table tr:hover {
            background: #f5f5f5;
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
            border: none;
            cursor: pointer;
        }
        .btn-review:hover {
            background: #ddb300;
            transform: translateY(-1px);
        }
        .btn-view {
            background: #2196f3;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 13px;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-view:hover {
            background: #1976d2;
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
            overflow-y: auto;
        }
        .modal-content {
            background: white;
            margin: 50px auto;
            padding: 30px;
            width: 90%;
            max-width: 1000px;
            border-radius: 8px;
            max-height: 85vh;
            overflow-y: auto;
        }
        .modal-header {
            border-bottom: 2px solid var(--navy);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .modal-header h3 {
            color: var(--navy);
            margin-bottom: 10px;
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
        .report-preview {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid var(--navy);
        }
        .grades-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin: 15px 0;
        }
        .grade-item {
            background: white;
            padding: 10px;
            border-radius: 4px;
            border-left: 3px solid var(--yellow);
        }
        .grade-item strong {
            color: var(--navy);
        }
        .comment-display {
            background: #fff9e6;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            border-left: 4px solid var(--yellow);
        }
        .comment-display h4 {
            color: var(--navy);
            margin-bottom: 10px;
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
        .section-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .tab-btn {
            padding: 10px 20px;
            background: #f0f0f0;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }
        .tab-btn.active {
            background: var(--navy);
            color: white;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>BIMS Head Teacher</h2>
    <a href="../teacher_dashboard.php">Dashboard</a>
    <a href="my_profile.php">My Profile</a>
    <a href="review_student_reports.php" class="active">Review Reports</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main-content">
    <div class="container">
        <div class="card">
            <h2>📋 Review Student Report Cards</h2>

            <div class="header-banner">
                <h3>Pending Reviews</h3>
                <p>Review class teacher submissions and release reports to students/parents</p>
                <p style="margin-top: 10px;">
                    <span class="pending-count"><?php echo count($pending_reports); ?> reports awaiting your review</span>
                </p>
            </div>

            <?php if (isset($success)): ?>
                <div class="alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="section-tabs">
                <button class="tab-btn active" onclick="switchTab('pending')">Pending Reviews (<?php echo count($pending_reports); ?>)</button>
                <button class="tab-btn" onclick="switchTab('released')">Recently Released (<?php echo count($released_reports); ?>)</button>
            </div>

            <!-- Pending Reports Tab -->
            <div id="pending-tab" class="tab-content active">
                <?php if (empty($pending_reports)): ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <p style="font-size: 18px;">✅ No pending reports!</p>
                        <p>All submitted reports have been reviewed.</p>
                    </div>
                <?php else: ?>
                    <table class="reports-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Admission No</th>
                                <th>Class</th>
                                <th>Year</th>
                                <th>Term</th>
                                <th>Assessment</th>
                                <th>Subjects</th>
                                <th>Submitted</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_reports as $report): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($report['admission_number']); ?></td>
                                    <td><?php echo htmlspecialchars($report['curriculum_name'] . ' - ' . $report['class_name']); ?></td>
                                    <td><?php echo $report['academic_year']; ?></td>
                                    <td><?php echo $report['term']; ?></td>
                                    <td><?php echo $report['assessment_type']; ?></td>
                                    <td><?php echo $report['subjects_count']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($report['submitted_to_principal_at'])); ?></td>
                                    <td>
                                        <button class="btn-review" onclick="openReviewModal(
                                            <?php echo $report['student_id']; ?>,
                                            <?php echo $report['academic_year']; ?>,
                                            '<?php echo $report['term']; ?>',
                                            '<?php echo $report['assessment_type']; ?>',
                                            '<?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name'], ENT_QUOTES); ?>',
                                            '<?php echo htmlspecialchars($report['curriculum_name'], ENT_QUOTES); ?>',
                                            '<?php echo htmlspecialchars($report['class_teacher_comment'], ENT_QUOTES); ?>',
                                            '<?php echo htmlspecialchars($report['principal_comment'] ?? '', ENT_QUOTES); ?>'
                                        )">Review & Release</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Released Reports Tab -->
            <div id="released-tab" class="tab-content">
                <?php if (empty($released_reports)): ?>
                    <div style="text-align: center; padding: 40px; color: #666;">
                        <p>No reports released yet.</p>
                    </div>
                <?php else: ?>
                    <table class="reports-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Admission No</th>
                                <th>Class</th>
                                <th>Year</th>
                                <th>Term</th>
                                <th>Assessment</th>
                                <th>Released</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($released_reports as $report): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($report['admission_number']); ?></td>
                                    <td><?php echo htmlspecialchars($report['curriculum_name'] . ' - ' . $report['class_name']); ?></td>
                                    <td><?php echo $report['academic_year']; ?></td>
                                    <td><?php echo $report['term']; ?></td>
                                    <td><?php echo $report['assessment_type']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($report['released_to_students_at'])); ?></td>
                                    <td>
                                        <a href="<?php 
                                            $curriculum = $report['curriculum_name'];
                                            if ($curriculum === 'IGCSE') {
                                                echo 'view_report_card_igcse.php';
                                            } elseif ($curriculum === '8-4-4') {
                                                echo 'view_report_card_844.php';
                                            } else {
                                                echo 'view_report_card.php';
                                            }
                                        ?>?student_id=<?php echo $report['student_id']; ?>&year=<?php echo $report['academic_year']; ?>&term=<?php echo urlencode($report['term']); ?>&assessment=<?php echo urlencode($report['assessment_type']); ?>" 
                                           class="btn-view" target="_blank">
                                            View Report
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

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
            <p id="modalSubtitle" style="color: #666; margin-top: 5px;"></p>
        </div>

        <div class="report-preview" id="reportPreview">
            <p>Loading report data...</p>
        </div>

        <div class="comment-display" id="classTeacherComment">
            <h4>📝 Class Teacher's Comment:</h4>
            <p id="ctCommentText"></p>
        </div>

        <form method="POST" id="releaseForm">
            <input type="hidden" name="action" value="release_to_students">
            <input type="hidden" name="student_id" id="modal_student_id">
            <input type="hidden" name="year" id="modal_year">
            <input type="hidden" name="term" id="modal_term">
            <input type="hidden" name="assessment" id="modal_assessment">

            <div class="comment-box">
                <label>Principal's Comment:</label>
                <textarea name="principal_comment" id="modal_principal_comment" required placeholder="Enter your overall assessment and recommendations for this student..."></textarea>
                <small style="color: #666;">This comment will appear on the student's report card.</small>
            </div>

            <button type="submit" class="button" style="width: 100%; background: #4caf50; color: white;">
                ✅ Release Report to Student & Parents
            </button>
        </form>
    </div>
</div>

<script>
function switchTab(tab) {
    // Update tab buttons
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    // Update tab content
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    document.getElementById(tab + '-tab').classList.add('active');
}

function openReviewModal(studentId, year, term, assessment, studentName, curriculum, ctComment, principalComment) {
    document.getElementById('modalTitle').textContent = `Review: ${studentName}`;
    document.getElementById('modalSubtitle').textContent = `${curriculum} - ${term} ${assessment} ${year}`;
    document.getElementById('modal_student_id').value = studentId;
    document.getElementById('modal_year').value = year;
    document.getElementById('modal_term').value = term;
    document.getElementById('modal_assessment').value = assessment;
    document.getElementById('ctCommentText').textContent = ctComment || 'No comment provided';
    document.getElementById('modal_principal_comment').value = principalComment || '';
    
    // Fetch and display grades
    fetchGradesForReview(studentId, year, term, assessment);
    
    document.getElementById('reviewModal').style.display = 'block';
}

function closeReviewModal() {
    document.getElementById('reviewModal').style.display = 'none';
}

function fetchGradesForReview(studentId, year, term, assessment) {
    const previewDiv = document.getElementById('reportPreview');
    previewDiv.innerHTML = '<p>Loading grades...</p>';
    
    fetch(`get_student_grades.php?student_id=${studentId}&year=${year}&term=${encodeURIComponent(term)}&assessment=${encodeURIComponent(assessment)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.grades.length > 0) {
                let html = '<h4 style="color: var(--navy); margin-bottom: 15px;">📊 Student Grades:</h4>';
                html += '<div class="grades-grid">';
                data.grades.forEach(g => {
                    html += `<div class="grade-item">
                        <div><strong>${g.subject_name}</strong></div>
                        <div style="font-size: 18px; color: var(--navy); margin: 5px 0;"><strong>${g.grade}</strong></div>
                        <div style="font-size: 12px; color: #666;">${g.grade_points} points</div>
                    </div>`;
                });
                html += '</div>';
                html += `<div style="margin-top: 20px; padding: 15px; background: var(--yellow); border-radius: 6px;">
                    <strong>Total Points:</strong> ${data.total_points} | 
                    <strong>Mean Points:</strong> ${data.mean_points}
                </div>`;
                previewDiv.innerHTML = html;
            } else {
                previewDiv.innerHTML = '<p style="color: #f44336;">No grades found for this assessment.</p>';
            }
        })
        .catch(error => {
            previewDiv.innerHTML = '<p style="color: #f44336;">Error loading grades.</p>';
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