<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Parent') {
    header("Location: ../login.php");
    exit;
}

$student_id = (int)($_GET['student_id'] ?? 0);

/* ---------- GET PARENT INFO ---------- */
$parent_stmt = $pdo->prepare("SELECT id, linked_students FROM parents WHERE user_id = ?");
$parent_stmt->execute([$_SESSION['user_id']]);
$parent = $parent_stmt->fetch(PDO::FETCH_ASSOC);

if (!$parent) {
    die("Parent profile not found");
}

/* ---------- GET STUDENT INFO ---------- */
$student_stmt = $pdo->prepare("
    SELECT 
        s.id, s.admission_number, s.first_name, s.last_name, s.gender,
        s.year_of_enrollment, cl.name as class_name, ct.name as curriculum_name
    FROM students s
    LEFT JOIN classes_levels cl ON s.class_level_id = cl.id
    LEFT JOIN curriculum_types ct ON cl.curriculum_type_id = ct.id
    WHERE s.id = ?
");
$student_stmt->execute([$student_id]);
$student = $student_stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student not found");
}

/* ---------- VERIFY PARENT HAS ACCESS TO THIS STUDENT ---------- */
$linked_numbers = explode(',', $parent['linked_students']);
$linked_numbers = array_map('trim', $linked_numbers);

if (!in_array($student['admission_number'], $linked_numbers)) {
    die("You do not have permission to view this student's grades.");
}

/* ---------- HANDLE PARENT COMMENT SUBMISSION ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] === 'save_comment') {
    $academic_year = (int)$_POST['year'];
    $term = $_POST['term'];
    $assessment = $_POST['assessment'];
    $parent_comment = trim($_POST['parent_comment']);
    
    try {
        // Check if comment exists
        $check = $pdo->prepare("
            SELECT id FROM parent_comments 
            WHERE student_id = ? AND parent_id = ? AND academic_year = ? AND term = ? AND assessment_type = ?
        ");
        $check->execute([$student_id, $parent['id'], $academic_year, $term, $assessment]);
        $existing = $check->fetch();
        
        if ($existing) {
            // Update existing comment
            $update = $pdo->prepare("
                UPDATE parent_comments 
                SET comment = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $update->execute([$parent_comment, $existing['id']]);
        } else {
            // Insert new comment
            $insert = $pdo->prepare("
                INSERT INTO parent_comments (student_id, parent_id, academic_year, term, assessment_type, comment)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insert->execute([$student_id, $parent['id'], $academic_year, $term, $assessment, $parent_comment]);
        }
        
        $success = "Comment saved successfully!";
    } catch (PDOException $e) {
        $error = "Error saving comment: " . $e->getMessage();
    }
}

/* ---------- GET ALL AVAILABLE REPORT PERIODS ---------- */
$current_year = (int)date('Y');
$years = range($student['year_of_enrollment'], $current_year);
$terms = ['Term 1', 'Term 2', 'Term 3'];
$assessments = ['Opener', 'Mid-Term', 'End-Term'];

// Get all periods with grades
$periods_stmt = $pdo->prepare("
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
$periods_stmt->execute([$student_id]);
$periods_raw = $periods_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize periods by year
$periods_by_year = [];
foreach ($periods_raw as $period) {
    $periods_by_year[$period['academic_year']][$period['term']][] = $period;
}

/* ---------- GET PARENT COMMENTS ---------- */
$comments_stmt = $pdo->prepare("
    SELECT academic_year, term, assessment_type, comment, updated_at
    FROM parent_comments
    WHERE student_id = ? AND parent_id = ?
");
$comments_stmt->execute([$student_id, $parent['id']]);
$parent_comments_raw = $comments_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize comments
$parent_comments = [];
foreach ($parent_comments_raw as $comment) {
    $key = $comment['academic_year'] . '_' . $comment['term'] . '_' . $comment['assessment_type'];
    $parent_comments[$key] = $comment;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Grades - <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/parent.css">
    <style>
        .grade-period {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 6px solid var(--navy);
        }
        
        .period-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            user-select: none;
            padding: 15px;
            background: #f0f0f0;
            border-radius: 6px;
            margin-bottom: 15px;
            transition: background 0.3s;
        }
        
        .period-header:hover {
            background: #e0e0e0;
        }
        
        .period-header .toggle-icon {
            font-size: 20px;
            transition: transform 0.3s;
        }
        
        .period-header.collapsed .toggle-icon {
            transform: rotate(-90deg);
        }
        
        .period-content {
            max-height: 3000px;
            overflow: hidden;
            transition: max-height 0.4s ease-out;
        }
        
        .period-content.collapsed {
            max-height: 0;
        }
        
        .assessment-section {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid var(--yellow);
        }
        
        .assessment-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .grades-table {
            width: 100%;
            background: white;
            border-collapse: collapse;
            border-radius: 6px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .grades-table thead {
            background: var(--navy);
            color: white;
        }
        
        .grades-table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }
        
        .grades-table td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .grades-table tr:hover {
            background: #f5f5f5;
        }
        
        .grade-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .grade-EE1, .grade-EE2 { background: #4caf50; color: white; }
        .grade-ME1, .grade-ME2 { background: #2196f3; color: white; }
        .grade-AE1, .grade-AE2 { background: #ff9800; color: white; }
        .grade-BE1, .grade-BE2 { background: #f44336; color: white; }
        
        .comment-section {
            background: #fff9e6;
            padding: 20px;
            border-radius: 6px;
            border-left: 4px solid var(--yellow);
            margin-top: 20px;
        }
        
        .comment-section h4 {
            color: var(--navy);
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .comment-textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: 'Segoe UI', Tahoma, sans-serif;
            min-height: 100px;
            resize: vertical;
        }
        
        .comment-textarea:focus {
            outline: none;
            border-color: var(--yellow);
        }
        
        .comment-saved {
            background: #d4edda;
            color: #155724;
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 13px;
            margin-top: 10px;
            display: inline-block;
        }
        
        .btn-save-comment {
            background: var(--yellow);
            color: var(--black);
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
            transition: all 0.3s;
        }
        
        .btn-save-comment:hover {
            background: #ddb300;
            transform: translateY(-2px);
        }
        
        .btn-view-report {
            background: var(--navy);
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-view-report:hover {
            background: var(--black);
            transform: translateY(-2px);
        }
        
        .btn-save-pdf {
            background: #f44336;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            margin-left: 10px;
        }
        
        .btn-save-pdf:hover {
            background: #d32f2f;
            transform: translateY(-2px);
        }
        
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-box {
            background: white;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            border-left: 4px solid var(--yellow);
        }
        
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--navy);
        }
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .grade-period {
                padding: 15px;
            }
            
            .period-header {
                padding: 12px;
            }
            
            .assessment-section {
                padding: 15px;
            }
            
            .assessment-title {
                font-size: 16px;
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .grades-table {
                font-size: 13px;
            }
            
            .grades-table th,
            .grades-table td {
                padding: 10px 8px;
                font-size: 12px;
            }
            
            .summary-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            
            .stat-value {
                font-size: 20px;
            }
            
            .btn-view-report,
            .btn-save-pdf {
                width: 100%;
                justify-content: center;
                margin: 5px 0;
            }
            
            .comment-section {
                padding: 15px;
            }
        }
        
        @media (max-width: 480px) {
            .summary-stats {
                grid-template-columns: 1fr;
            }
            
            .grades-table th,
            .grades-table td {
                padding: 8px 6px;
                font-size: 11px;
            }
            
            .grade-badge {
                font-size: 12px;
                padding: 4px 8px;
            }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>BIMS Parent</h2>
    <a href="../parent_dashboard.php">Dashboard</a>
    <a href="my_profile.php">My Profile</a>
    <a href="my_children.php">My Children</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main-content">
    <div class="container">
        <!-- STUDENT INFO CARD -->
        <div class="card welcome-card">
            <h1>📊 <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h1>
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
                    <span class="info-label">Gender:</span>
                    <span class="info-value"><?php echo htmlspecialchars($student['gender']); ?></span>
                </div>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- GRADES BY PERIOD -->
        <?php if (empty($periods_by_year)): ?>
            <div class="card">
                <div class="no-data">
                    <p>No grades available yet. Grades will appear here once teachers upload them.</p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach (array_reverse($years, true) as $year_index => $year): ?>
                <?php if (isset($periods_by_year[$year])): ?>
                    <div class="grade-period">
                        <div class="period-header <?php echo $year_index > 0 ? 'collapsed' : ''; ?>" onclick="togglePeriod(this)">
                            <span><strong><?php echo $year; ?></strong> Academic Year</span>
                            <span class="toggle-icon">▼</span>
                        </div>
                        
                        <div class="period-content <?php echo $year_index > 0 ? 'collapsed' : ''; ?>">
                            <?php foreach ($terms as $term): ?>
                                <?php if (isset($periods_by_year[$year][$term])): ?>
                                    <h3 style="color: var(--navy); margin: 20px 0 15px; font-size: 20px;"><?php echo $term; ?></h3>
                                    
                                    <?php foreach ($periods_by_year[$year][$term] as $period): ?>
                                        <?php
                                        $assessment = $period['assessment_type'];
                                        
                                        // Get grades for this period
                                        $grades_stmt = $pdo->prepare("
                                            SELECT subject_name, score, rats_score, final_score, grade, grade_points, teacher_comment
                                            FROM grades
                                            WHERE student_id = ? AND academic_year = ? AND term = ? AND assessment_type = ?
                                            ORDER BY subject_name
                                        ");
                                        $grades_stmt->execute([$student_id, $year, $term, $assessment]);
                                        $grades = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);
                                        
                                        // Calculate stats
                                        $total_points = 0;
                                        $subjects_count = 0;
                                        foreach ($grades as $g) {
                                            if ($g['grade_points'] !== null) {
                                                $total_points += $g['grade_points'];
                                                $subjects_count++;
                                            }
                                        }
                                        $mean_points = $subjects_count > 0 ? round($total_points / $subjects_count, 2) : 0;
                                        
                                        // Get parent comment
                                        $comment_key = $year . '_' . $term . '_' . $assessment;
                                        $existing_comment = $parent_comments[$comment_key]['comment'] ?? '';
                                        ?>
                                        
                                        <div class="assessment-section">
                                            <div class="assessment-title">
                                                <span>📋 <?php echo htmlspecialchars($assessment); ?> Assessment</span>
                                                <div>
                                                    <a href="../teacher/view_report_card.php?student_id=<?php echo $student_id; ?>&year=<?php echo $year; ?>&term=<?php echo urlencode($term); ?>&assessment=<?php echo urlencode($assessment); ?>" 
                                                       class="btn-view-report" 
                                                       target="_blank">
                                                        📄 View Report Card
                                                    </a>
                                                    <a href="../teacher/view_report_card.php?student_id=<?php echo $student_id; ?>&year=<?php echo $year; ?>&term=<?php echo urlencode($term); ?>&assessment=<?php echo urlencode($assessment); ?>" 
                                                       class="btn-save-pdf" 
                                                       target="_blank"
                                                       onclick="setTimeout(() => window.print(), 1000); return true;">
                                                        💾 Save as PDF
                                                    </a>
                                                </div>
                                            </div>
                                            
                                            <!-- Summary Stats -->
                                            <div class="summary-stats">
                                                <div class="stat-box">
                                                    <div class="stat-label">Subjects</div>
                                                    <div class="stat-value"><?php echo $subjects_count; ?></div>
                                                </div>
                                                <div class="stat-box">
                                                    <div class="stat-label">Mean Points</div>
                                                    <div class="stat-value"><?php echo number_format($mean_points, 2); ?></div>
                                                </div>
                                                <div class="stat-box">
                                                    <div class="stat-label">Total Points</div>
                                                    <div class="stat-value"><?php echo $total_points; ?></div>
                                                </div>
                                            </div>
                                            
                                            <!-- Grades Table -->
                                            <div style="overflow-x: auto;">
                                                <table class="grades-table">
                                                    <thead>
                                                        <tr>
                                                            <th>Subject</th>
                                                            <th>Score</th>
                                                            <?php if ($assessment != 'Opener'): ?>
                                                                <th>RATs</th>
                                                            <?php endif; ?>
                                                            <th>Final</th>
                                                            <th>Grade</th>
                                                            <th>Points</th>
                                                            <th>Teacher Comment</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($grades as $grade): ?>
                                                            <tr>
                                                                <td><strong><?php echo htmlspecialchars($grade['subject_name']); ?></strong></td>
                                                                <td><?php echo htmlspecialchars($grade['score']); ?></td>
                                                             
                                                                <td><strong><?php echo htmlspecialchars($grade['final_score']); ?></strong></td>
                                                                <td>
                                                                    <span class="grade-badge grade-<?php echo $grade['grade']; ?>">
                                                                        <?php echo htmlspecialchars($grade['grade']); ?>
                                                                    </span>
                                                                </td>
                                                                <td><strong><?php echo htmlspecialchars($grade['grade_points']); ?></strong></td>
                                                                <td style="font-size: 12px;"><?php echo htmlspecialchars($grade['teacher_comment'] ?? '-'); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            
                                            <!-- Parent Comment Section -->
                                            <div class="comment-section">
                                                <h4>💬 Your Comment (Parent/Guardian)</h4>
                                                <form method="POST" action="">
                                                    <input type="hidden" name="action" value="save_comment">
                                                    <input type="hidden" name="year" value="<?php echo $year; ?>">
                                                    <input type="hidden" name="term" value="<?php echo $term; ?>">
                                                    <input type="hidden" name="assessment" value="<?php echo $assessment; ?>">
                                                    
                                                    <textarea name="parent_comment" 
                                                              class="comment-textarea" 
                                                              placeholder="Add your comment about your child's performance..."><?php echo htmlspecialchars($existing_comment); ?></textarea>
                                                    
                                                    <button type="submit" class="btn-save-comment">💾 Save Comment</button>
                                                    
                                                    <?php if (!empty($existing_comment)): ?>
                                                        <div class="comment-saved">
                                                            ✓ Last updated: <?php echo date('M d, Y g:i A', strtotime($parent_comments[$comment_key]['updated_at'])); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <a href="../parent_dashboard.php" class="button button-yellow" style="margin-top: 20px;">← Back to Dashboard</a>
    </div>
</div>

<script>
function togglePeriod(header) {
    const content = header.nextElementSibling;
    header.classList.toggle('collapsed');
    content.classList.toggle('collapsed');
}
</script>

</body>
</html>