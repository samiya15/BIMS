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
    die("Access denied. This page is for Head Teachers only.");
}

/* ---------- HANDLE ACTIONS ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';
    $submission_id = (int)($_POST['submission_id'] ?? 0);
    
    if ($action === 'release' && $submission_id > 0) {
        $principal_comment = trim($_POST['principal_comment'] ?? '');
        
        try {
            $pdo->beginTransaction();
            
            // Update submission status
            $update = $pdo->prepare("
                UPDATE grade_submissions 
                SET status = 'RELEASED',
                    principal_comment = ?,
                    released_to_students_at = NOW()
                WHERE id = ?
            ");
            $update->execute([$principal_comment, $submission_id]);
            
            $pdo->commit();
            header("Location: review_student_reports.php?success=released");
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error releasing report: " . $e->getMessage();
        }
    }
}

/* ---------- GET PENDING SUBMISSIONS ---------- */
$pending_stmt = $pdo->prepare("
    SELECT 
        gs.id as submission_id,
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
        t.first_name as teacher_first_name,
        t.last_name as teacher_last_name
    FROM grade_submissions gs
    JOIN students s ON gs.student_id = s.id
    JOIN teachers t ON gs.teacher_id = t.id
    JOIN classes_levels cl ON s.class_level_id = cl.id
    JOIN curriculum_types ct ON cl.curriculum_type_id = ct.id
    WHERE gs.status = 'AWAITING_PRINCIPAL'
    ORDER BY gs.submitted_to_principal_at DESC
");
$pending_stmt->execute();
$pending_submissions = $pending_stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------- GET RELEASED SUBMISSIONS (Recent 20) ---------- */
$released_stmt = $pdo->prepare("
    SELECT 
        gs.id as submission_id,
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
    LIMIT 20
");
$released_stmt->execute();
$released_submissions = $released_stmt->fetchAll(PDO::FETCH_ASSOC);
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
        .submissions-grid {
            display: grid;
            gap: 20px;
            margin-top: 20px;
        }
        .submission-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border-left: 6px solid var(--yellow);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .submission-card.pending {
            border-left-color: #ff9800;
            background: #fff9e6;
        }
        .submission-card.released {
            border-left-color: #4caf50;
            background: #f1f8f4;
        }
        .submission-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        .student-info h3 {
            color: var(--navy);
            margin-bottom: 5px;
        }
        .student-meta {
            color: #666;
            font-size: 13px;
            margin: 3px 0;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending {
            background: #ff9800;
            color: white;
        }
        .status-released {
            background: #4caf50;
            color: white;
        }
        .comment-box {
            background: white;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            border-left: 4px solid #2196f3;
        }
        .comment-box h4 {
            color: var(--navy);
            margin-bottom: 10px;
            font-size: 14px;
        }
        .comment-text {
            color: #333;
            line-height: 1.6;
            font-size: 14px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn-view {
            background: var(--navy);
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-view:hover {
            background: var(--black);
            transform: translateY(-2px);
        }
        .btn-review {
            background: #ff9800;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-review:hover {
            background: #f57c00;
            transform: translateY(-2px);
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 1000;
            overflow-y: auto;
        }
        .modal-content {
            background: white;
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 10px;
            position: relative;
        }
        .modal-close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 28px;
            font-weight: bold;
            color: #999;
            cursor: pointer;
            border: none;
            background: none;
        }
        .modal-close:hover {
            color: #333;
        }
        .principal-comment-form {
            margin-top: 20px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        .principal-comment-form label {
            display: block;
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 10px;
        }
        .principal-comment-form textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            resize: vertical;
            min-height: 100px;
        }
        .principal-comment-form button {
            background: #4caf50;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            margin-top: 15px;
            transition: all 0.3s;
        }
        .principal-comment-form button:hover {
            background: #45a049;
            transform: translateY(-2px);
        }
        .curriculum-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 10px;
        }
        .badge-cbc {
            background: #e3f2fd;
            color: #1976d2;
        }
        .badge-844 {
            background: #fff3e0;
            color: #f57c00;
        }
        .badge-igcse {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
        }
        .tab {
            padding: 12px 24px;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-weight: 600;
            color: #666;
            transition: all 0.3s;
        }
        .tab.active {
            color: var(--navy);
            border-bottom-color: var(--yellow);
        }
        .tab:hover {
            color: var(--navy);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>BIMS Head Teacher</h2>
    <a href="../teacher_dashboard.php">Dashboard</a>
    <a href="review_student_reports.php" class="active">Review Reports</a>
    <a href="my_profile.php">My Profile</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main-content">
    <div class="container">
        <div class="card">
            <h1>📋 Review Student Reports</h1>
            <p>Review and release student report cards submitted by class teachers.</p>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert-success">✅ Report card successfully released to student/parent!</div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- TABS -->
            <div class="tabs">
                <button class="tab active" onclick="switchTab('pending')">
                    ⏳ Pending Reviews (<?php echo count($pending_submissions); ?>)
                </button>
                <button class="tab" onclick="switchTab('released')">
                    ✅ Recently Released (<?php echo count($released_submissions); ?>)
                </button>
            </div>

            <!-- PENDING SUBMISSIONS TAB -->
            <div id="pending-tab" class="tab-content active">
                <?php if (empty($pending_submissions)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <h3>No Pending Reviews</h3>
                        <p>All submitted reports have been reviewed. Check back later!</p>
                    </div>
                <?php else: ?>
                    <div class="submissions-grid">
                        <?php foreach ($pending_submissions as $sub): ?>
                            <div class="submission-card pending">
                                <div class="submission-header">
                                    <div class="student-info">
                                        <h3>
                                            <?php echo htmlspecialchars($sub['first_name'] . ' ' . $sub['last_name']); ?>
                                            <span class="curriculum-badge badge-<?php echo strtolower(str_replace(['-', ' '], '', $sub['curriculum_name'])); ?>">
                                                <?php echo htmlspecialchars($sub['curriculum_name']); ?>
                                            </span>
                                        </h3>
                                        <div class="student-meta">📝 Adm: <?php echo htmlspecialchars($sub['admission_number']); ?></div>
                                        <div class="student-meta">🎓 Class: <?php echo htmlspecialchars($sub['class_name']); ?></div>
                                        <div class="student-meta">
                                            📅 <?php echo htmlspecialchars($sub['academic_year'] . ' - ' . $sub['term'] . ' - ' . $sub['assessment_type']); ?>
                                        </div>
                                        <div class="student-meta">
                                            👨‍🏫 Submitted by: <?php echo htmlspecialchars($sub['teacher_first_name'] . ' ' . $sub['teacher_last_name']); ?>
                                        </div>
                                        <div class="student-meta">
                                            ⏰ <?php echo date('M d, Y H:i', strtotime($sub['submitted_to_principal_at'])); ?>
                                        </div>
                                    </div>
                                    <span class="status-badge status-pending">Pending Review</span>
                                </div>

                                <?php if ($sub['class_teacher_comment']): ?>
                                    <div class="comment-box">
                                        <h4>📝 Class Teacher's Comment:</h4>
                                        <div class="comment-text"><?php echo nl2br(htmlspecialchars($sub['class_teacher_comment'])); ?></div>
                                    </div>
                                <?php endif; ?>

                                <div class="action-buttons">
                                    <a href="view_report_card.php?student_id=<?php echo $sub['student_id']; ?>&year=<?php echo $sub['academic_year']; ?>&term=<?php echo urlencode($sub['term']); ?>&assessment=<?php echo urlencode($sub['assessment_type']); ?>" 
                                       target="_blank"
                                       class="btn-view">
                                        📄 View Report Card
                                    </a>
                                    <button onclick="openReviewModal(<?php echo $sub['submission_id']; ?>, '<?php echo htmlspecialchars($sub['first_name'] . ' ' . $sub['last_name']); ?>', '<?php echo $sub['academic_year']; ?>', '<?php echo $sub['term']; ?>', '<?php echo $sub['assessment_type']; ?>')" 
                                            class="btn-review">
                                        ✅ Review & Release
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- RELEASED SUBMISSIONS TAB -->
            <div id="released-tab" class="tab-content">
                <?php if (empty($released_submissions)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📋</div>
                        <h3>No Released Reports Yet</h3>
                        <p>Released reports will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="submissions-grid">
                        <?php foreach ($released_submissions as $sub): ?>
                            <div class="submission-card released">
                                <div class="submission-header">
                                    <div class="student-info">
                                        <h3>
                                            <?php echo htmlspecialchars($sub['first_name'] . ' ' . $sub['last_name']); ?>
                                            <span class="curriculum-badge badge-<?php echo strtolower(str_replace(['-', ' '], '', $sub['curriculum_name'])); ?>">
                                                <?php echo htmlspecialchars($sub['curriculum_name']); ?>
                                            </span>
                                        </h3>
                                        <div class="student-meta">📝 Adm: <?php echo htmlspecialchars($sub['admission_number']); ?></div>
                                        <div class="student-meta">🎓 Class: <?php echo htmlspecialchars($sub['class_name']); ?></div>
                                        <div class="student-meta">
                                            📅 <?php echo htmlspecialchars($sub['academic_year'] . ' - ' . $sub['term'] . ' - ' . $sub['assessment_type']); ?>
                                        </div>
                                        <div class="student-meta">
                                            ✅ Released: <?php echo date('M d, Y H:i', strtotime($sub['released_to_students_at'])); ?>
                                        </div>
                                    </div>
                                    <span class="status-badge status-released">Released</span>
                                </div>

                                <div class="action-buttons">
                                    <a href="view_report_card.php?student_id=<?php echo $sub['student_id']; ?>&year=<?php echo $sub['academic_year']; ?>&term=<?php echo urlencode($sub['term']); ?>&assessment=<?php echo urlencode($sub['assessment_type']); ?>" 
                                       target="_blank"
                                       class="btn-view">
                                        📄 View Report Card
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- REVIEW MODAL -->
<div id="reviewModal" class="modal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeReviewModal()">&times;</button>
        <h2 style="color: var(--navy); margin-bottom: 20px;">📋 Review & Release Report</h2>
        
        <div id="modalStudentInfo" style="background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
            <!-- Student info will be populated by JavaScript -->
        </div>

        <form method="POST" onsubmit="return confirmRelease()">
            <input type="hidden" name="action" value="release">
            <input type="hidden" name="submission_id" id="modal_submission_id">
            
            <div class="principal-comment-form">
                <label>📝 Principal's Comment (Required):</label>
                <textarea name="principal_comment" 
                          placeholder="Enter your overall comments on this student's performance and progress..."
                          required></textarea>
                <small style="color: #666; display: block; margin-top: 5px;">
                    This comment will appear on the final report card visible to students and parents.
                </small>
            </div>

            <button type="submit" style="width: 100%;">
                ✅ Approve and Release to Student/Parent
            </button>
        </form>
    </div>
</div>

<script>
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    event.target.classList.add('active');
}

function openReviewModal(submissionId, studentName, year, term, assessment) {
    document.getElementById('modal_submission_id').value = submissionId;
    document.getElementById('modalStudentInfo').innerHTML = `
        <h3 style="color: var(--navy); margin-bottom: 10px;">${studentName}</h3>
        <p style="color: #666; margin: 5px 0;">📅 ${year} - ${term} - ${assessment}</p>
    `;
    document.getElementById('reviewModal').style.display = 'block';
}

function closeReviewModal() {
    document.getElementById('reviewModal').style.display = 'none';
}

function confirmRelease() {
    return confirm('⚠️ Are you sure you want to RELEASE this report card?\n\nOnce released:\n• Students and parents can view it immediately\n• You cannot edit the principal comment\n\nProceed with release?');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('reviewModal');
    if (event.target === modal) {
        closeReviewModal();
    }
}
</script>

</body>
</html>