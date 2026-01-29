<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Teacher') {
    header("Location: ../login.php");
    exit;
}

$student_id = (int)($_GET['student_id'] ?? 0);

/* ---------- IGCSE SUBJECT PAPER STRUCTURE (Year 11 End-Term SETS ONLY) ---------- */
$year11_sets_papers = [
    'Mathematics' => ['papers' => [['name' => 'Paper 1', 'max' => 100], ['name' => 'Paper 2', 'max' => 100]], 'total' => 200],
    'English' => ['papers' => [['name' => 'Paper 1', 'max' => 100]], 'total' => 100],
    'History' => ['papers' => [['name' => 'Paper 1', 'max' => 60], ['name' => 'Paper 2', 'max' => 60]], 'total' => 120],
    'Biology' => ['papers' => [['name' => 'Paper 1', 'max' => 110], ['name' => 'Paper 2', 'max' => 70]], 'total' => 180],
    'Chemistry' => ['papers' => [['name' => 'Paper 1', 'max' => 110], ['name' => 'Paper 2', 'max' => 70]], 'total' => 180],
    'Physics' => ['papers' => [['name' => 'Paper 1', 'max' => 110], ['name' => 'Paper 2', 'max' => 70]], 'total' => 180],
    'Kiswahili' => ['papers' => [['name' => 'Paper 1', 'max' => 80], ['name' => 'Paper 2', 'max' => 40]], 'total' => 120],
    'Geography' => ['papers' => [['name' => 'Paper 1', 'max' => 100], ['name' => 'Paper 2', 'max' => 75]], 'total' => 175],
    'Islamiyat' => ['papers' => [['name' => 'Paper 1', 'max' => 90]], 'total' => 90],
    'Business Studies' => ['papers' => [['name' => 'Paper 1', 'max' => 80], ['name' => 'Paper 2', 'max' => 80]], 'total' => 160],
    'ICT' => ['papers' => [['name' => 'Paper 1', 'max' => 100], ['name' => 'Paper 2', 'max' => 100]], 'total' => 200]
];

/* ---------- GET TEACHER INFO ---------- */
$teacher_stmt = $pdo->prepare("SELECT id, category FROM teachers WHERE user_id = ?");
$teacher_stmt->execute([$_SESSION['user_id']]);
$teacher = $teacher_stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    die("Teacher profile not found");
}

/* ---------- GET STUDENT INFO ---------- */
$student_stmt = $pdo->prepare("
    SELECT 
        s.id, s.admission_number, s.first_name, s.last_name, s.year_of_enrollment,
        cl.name as class_name, ct.name as curriculum_name
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

if ($student['curriculum_name'] !== 'IGCSE') {
    die("This page is only for IGCSE curriculum students");
}

/* ---------- GET TEACHER'S SUBJECTS ---------- */
$teacher_subjects_stmt = $pdo->prepare("SELECT DISTINCT subject_name FROM teacher_subjects WHERE teacher_id = ?");
$teacher_subjects_stmt->execute([$teacher['id']]);
$teacher_subjects = $teacher_subjects_stmt->fetchAll(PDO::FETCH_COLUMN);

/* ---------- GET STUDENT'S SUBJECTS ---------- */
$student_subjects_stmt = $pdo->prepare("SELECT subject_name FROM student_subjects WHERE student_id = ? ORDER BY subject_name");
$student_subjects_stmt->execute([$student_id]);
$student_subjects = $student_subjects_stmt->fetchAll(PDO::FETCH_COLUMN);

/* ---------- GET ALL EXISTING GRADES ---------- */
$grades_stmt = $pdo->prepare("
    SELECT subject_name, grade, final_score, paper_scores, rats_score, grade_boundaries, assessment_type, term, academic_year, is_locked, teacher_comment
    FROM grades WHERE student_id = ?
");
$grades_stmt->execute([$student_id]);
$all_grades = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);

$grades_organized = [];
$boundaries_stored = [];
foreach ($all_grades as $g) {
    $grades_organized[$g['academic_year']][$g['term']][$g['assessment_type']][$g['subject_name']] = [
        'final_score' => $g['final_score'],
        'grade' => $g['grade'],
        'is_locked' => $g['is_locked'],
        'comment' => $g['teacher_comment'] ?? '',
        'paper_scores' => $g['paper_scores'] ? json_decode($g['paper_scores'], true) : [],
        'rats_score' => $g['rats_score'],
        'grade_boundaries' => $g['grade_boundaries'] ? json_decode($g['grade_boundaries'], true) : null
    ];
    
    if ($g['grade_boundaries']) {
        $boundaries_stored[$g['academic_year']][$g['term']][$g['assessment_type']][$g['subject_name']] = json_decode($g['grade_boundaries'], true);
    }
}

/* ---------- HANDLE GRADE SUBMISSION ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $selected_year = (int)$_POST['academic_year'];
    $selected_term = $_POST['term'];
    $selected_assessment = $_POST['assessment_type'];
    $grades_data = $_POST['grades'] ?? [];
    $rats_data = $_POST['rats'] ?? [];
    $comments_data = $_POST['comments'] ?? [];
    $boundaries_data = $_POST['boundaries'] ?? [];
    $lock_submission = isset($_POST['lock_submission']) ? 1 : 0;
    
    try {
        $pdo->beginTransaction();
        
        foreach ($grades_data as $subject => $paper_data) {
            if (empty($paper_data) || !is_array($paper_data)) continue;
            
            // Calculate total from papers
            $paper_total = 0;
            $papers_done = [];
            
            foreach ($paper_data as $paper_name => $score) {
                if ($score !== '' && $score !== null) {
                    $paper_total += (float)$score;
                    $papers_done[$paper_name] = (float)$score;
                }
            }
            
            if (empty($papers_done)) continue;
            
            // Get RATs score
            $rats_score = isset($rats_data[$subject]) ? (float)$rats_data[$subject] : 0;
            
            // Get max possible score
            $is_year11_endterm = ($student['class_name'] == 'Year 11' && $selected_assessment == 'End-Term');
            $max_score = 100; // Default for single paper
            
            if ($is_year11_endterm && isset($year11_sets_papers[$subject])) {
                $max_score = $year11_sets_papers[$subject]['total'];
            }
            
            // Calculate final score: Papers (80%) + RATs (20%)
            $paper_weighted = $paper_total * 0.7; // 70%
            $rats_weighted = $rats_score * 0.3; // 30%
            $final_score = round($paper_weighted + $rats_weighted, 1);
            
            // Determine grade based on boundaries
            $grade = 'U';
            $grade_boundaries = null;
            
            if ($is_year11_endterm && isset($boundaries_data[$subject])) {
                // Use custom boundaries for Year 11 End-Term
                $boundaries = $boundaries_data[$subject];
                $grade_boundaries = $boundaries;
                
                // Sort boundaries from highest to lowest
                $boundary_grades = ['9', '8', '7', '6', '5', '4', '3', '2', '1'];
                foreach ($boundary_grades as $g) {
                    if (isset($boundaries[$g]) && $final_score >= (float)$boundaries[$g]) {
                        $grade = $g;
                        break;
                    }
                }
            } else {
                // Use percentage-based boundaries for Year 9/10 and Year 11 Mid-Term
                $percentage = ($final_score / $max_score) * 100;
                
                if ($percentage >= 90) $grade = '9';
                elseif ($percentage >= 80) $grade = '8';
                elseif ($percentage >= 70) $grade = '7';
                elseif ($percentage >= 60) $grade = '6';
                elseif ($percentage >= 50) $grade = '5';
                elseif ($percentage >= 40) $grade = '4';
                elseif ($percentage >= 30) $grade = '3';
                elseif ($percentage >= 25) $grade = '2';
                elseif ($percentage >= 20) $grade = '1';
                else $grade = 'U';
            }
            
            // Calculate grade points (9=9pts, 8=8pts, etc., U=0pts)
            $grade_points = is_numeric($grade) ? (int)$grade : 0;
            
            $teacher_comment = isset($comments_data[$subject]) ? trim($comments_data[$subject]) : null;
            $paper_scores_json = json_encode($papers_done);
            $boundaries_json = $grade_boundaries ? json_encode($grade_boundaries) : null;
            
            $check_stmt = $pdo->prepare("SELECT id FROM grades WHERE student_id = ? AND academic_year = ? AND term = ? AND assessment_type = ? AND subject_name = ?");
            $check_stmt->execute([$student_id, $selected_year, $selected_term, $selected_assessment, $subject]);
            $existing = $check_stmt->fetch();
            
            if ($existing) {
                $update_stmt = $pdo->prepare("
                    UPDATE grades SET 
                        grade = ?, 
                        final_score = ?, 
                        grade_points = ?, 
                        paper_scores = ?,
                        rats_score = ?,
                        grade_boundaries = ?,
                        teacher_id = ?, 
                        is_locked = ?, 
                        teacher_comment = ?, 
                        updated_at = NOW() 
                    WHERE id = ?
                ");
                $update_stmt->execute([$grade, $final_score, $grade_points, $paper_scores_json, $rats_score, $boundaries_json, $teacher['id'], $lock_submission, $teacher_comment, $existing['id']]);
            } else {
                $insert_stmt = $pdo->prepare("
                    INSERT INTO grades 
                    (student_id, subject_name, grade, final_score, grade_points, paper_scores, rats_score, grade_boundaries, term, assessment_type, academic_year, teacher_id, is_locked, teacher_comment) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insert_stmt->execute([$student_id, $subject, $grade, $final_score, $grade_points, $paper_scores_json, $rats_score, $boundaries_json, $selected_term, $selected_assessment, $selected_year, $teacher['id'], $lock_submission, $teacher_comment]);
            }
        }
        
        if ($lock_submission) {
            $check_sub = $pdo->prepare("SELECT id FROM grade_submissions WHERE student_id = ? AND academic_year = ? AND term = ? AND assessment_type = ?");
            $check_sub->execute([$student_id, $selected_year, $selected_term, $selected_assessment]);
            $existing_sub = $check_sub->fetch();
            
            if ($existing_sub) {
                $update_sub = $pdo->prepare("UPDATE grade_submissions SET is_locked = 1, teacher_id = ?, submitted_at = NOW() WHERE id = ?");
                $update_sub->execute([$teacher['id'], $existing_sub['id']]);
            } else {
                $insert_sub = $pdo->prepare("INSERT INTO grade_submissions (student_id, teacher_id, academic_year, term, assessment_type, is_locked) VALUES (?, ?, ?, ?, ?, 1)");
                $insert_sub->execute([$student_id, $teacher['id'], $selected_year, $selected_term, $selected_assessment]);
            }
        }
        
        $pdo->commit();
        header("Location: update_grades_igcse.php?student_id=$student_id&success=1");
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

$current_year = (int)date('Y');
$years = range($student['year_of_enrollment'], $current_year);
$terms = ['Term 1', 'Term 2', 'Term 3'];
$assessments = ['Mid-Term', 'End-Term'];

function areGradesLocked($student_id, $year, $term, $assessment, $pdo) {
    $check = $pdo->prepare("SELECT is_locked FROM grade_submissions WHERE student_id = ? AND academic_year = ? AND term = ? AND assessment_type = ?");
    $check->execute([$student_id, $year, $term, $assessment]);
    $result = $check->fetch();
    return $result ? (bool)$result['is_locked'] : false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Grades - IGCSE</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/teacher.css">
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
        .toggle-icon {
            font-size: 20px;
            transition: transform 0.3s;
        }
        .collapsed .toggle-icon {
            transform: rotate(-90deg);
        }
        .collapsible-content {
            max-height: 5000px;
            overflow: hidden;
            transition: max-height 0.4s ease-out;
        }
        .collapsible-content.collapsed {
            max-height: 0;
        }
        .student-banner {
            background: linear-gradient(135deg, var(--navy) 0%, #1a3a52 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .student-banner h3 {
            color: var(--yellow);
        }
        .boundaries-table {
            background: #fff9e6;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid var(--yellow);
        }
        .boundaries-table h4 {
            color: var(--navy);
            margin-bottom: 10px;
        }
        .boundaries-grid {
            display: grid;
            grid-template-columns: repeat(10, 1fr);
            gap: 5px;
            margin-top: 10px;
        }
        .boundary-item {
            text-align: center;
        }
        .boundary-item label {
            display: block;
            font-weight: 600;
            font-size: 12px;
            color: var(--navy);
            margin-bottom: 5px;
        }
        .boundary-item input {
            width: 100%;
            padding: 8px 4px;
            border: 2px solid var(--yellow);
            border-radius: 4px;
            font-size: 13px;
            text-align: center;
        }
        .subject-card {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid var(--navy);
            margin-bottom: 15px;
        }
        .subject-card h4 {
            color: var(--navy);
            margin-bottom: 10px;
        }
        .paper-inputs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
            padding: 10px;
            background: white;
            border-radius: 4px;
        }
        .paper-input-item {
            display: flex;
            flex-direction: column;
        }
        .paper-input-item label {
            font-size: 12px;
            color: #666;
            margin-bottom: 5px;
        }
        .paper-input-item input {
            padding: 8px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .rats-section {
            margin-top: 10px;
            padding: 10px;
            background: #fff3cd;
            border-radius: 4px;
            border-left: 4px solid #ff9800;
        }
        .rats-section label {
            font-size: 13px;
            color: #ff9800;
            font-weight: 600;
        }
        .calculated-info {
            background: #e3f2fd;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            font-size: 12px;
            color: #1976d2;
        }
        .term-header {
            background: #f0f0f0;
            color: var(--navy);
            padding: 12px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            margin-bottom: 10px;
        }
        .assessment-header {
            background: #fff9e6;
            color: var(--navy);
            padding: 10px;
            border-radius: 4px;
            font-weight: 600;
            cursor: pointer;
            margin: 10px 0;
            border-left: 4px solid var(--yellow);
        }
        .view-report-btn {
            background: #4caf50;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 12px;
            margin-left: 15px;
            display: inline-block;
            transition: all 0.3s;
        }
        .view-report-btn:hover {
            background: #45a049;
            transform: translateY(-1px);
        }
        .locked-badge {
            background: #f44336;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 10px;
        }
        .lock-checkbox {
            display: flex;
            align-items: center;
            background: #fff3cd;
            padding: 12px;
            border-radius: 4px;
            margin: 15px 0;
        }
        .lock-checkbox input {
            width: auto;
            margin-right: 10px;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>BIMS Teacher</h2>
    <a href="../teacher_dashboard.php">Dashboard</a>
    <a href="my_profile.php">My Profile</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main-content">
    <div class="container">
        <div class="card">
            <h2>📝 Manage Student Grades (IGCSE System)</h2>

            <div class="student-banner">
                <h3><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h3>
                <p><strong>Admission No:</strong> <?php echo htmlspecialchars($student['admission_number']); ?></p>
                <p><strong>Class:</strong> <?php echo htmlspecialchars($student['curriculum_name'] . ' - ' . $student['class_name']); ?></p>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert-success">✅ Grades saved successfully!</div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (empty($student_subjects)): ?>
                <div class="alert-error">⚠️ No subjects assigned to this student.</div>
            <?php else: ?>
                <div style="margin-bottom: 15px;">
                    <a href="view_report_card_igcse.php?student_id=<?php echo $student_id; ?>" class="button" style="background: var(--yellow); color: var(--black);">
                        📄 View Report Card
                    </a>
                </div>

                <?php foreach (array_reverse($years) as $year_index => $year): ?>
                    <div class="year-section">
                        <div class="collapsible-header <?php echo $year_index > 0 ? 'collapsed' : ''; ?>" onclick="toggleSection(this)">
                            <span><strong><?php echo $year; ?></strong> Academic Year</span>
                            <span class="toggle-icon">▼</span>
                        </div>
                        
                        <div class="collapsible-content <?php echo $year_index > 0 ? 'collapsed' : ''; ?>">
                            <?php foreach ($terms as $term): ?>
                                <div class="collapsible-header collapsed" onclick="toggleSection(this)">
                                    <span><?php echo $term; ?></span>
                                    <span class="toggle-icon">▼</span>
                                </div>
                                
                                <div class="collapsible-content collapsed">
                                    <?php foreach ($assessments as $assessment): ?>
                                        <?php
                                        $is_locked = areGradesLocked($student_id, $year, $term, $assessment, $pdo);
                                        $is_year11_endterm = ($student['class_name'] == 'Year 11' && $assessment == 'End-Term');
                                        ?>
                                        
                                        <div class="collapsible-header collapsed" onclick="toggleSection(this)">
                                            <span>
                                                <?php echo $assessment; ?>
                                                <?php if ($is_locked): ?>
                                                    <span class="locked-badge">🔒 Locked</span>
                                                <?php endif; ?>
                                                <?php if ($is_year11_endterm): ?>
                                                    <span style="background: #ff9800; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px; margin-left: 10px;">📋 SETS</span>
                                                <?php endif; ?>
                                                <a href="view_report_card_igcse.php?student_id=<?php echo $student_id; ?>&year=<?php echo $year; ?>&term=<?php echo urlencode($term); ?>&assessment=<?php echo urlencode($assessment); ?>" 
                                                   class="view-report-btn" 
                                                   onclick="event.stopPropagation();">
                                                    📄 View Report Card
                                                </a>
                                            </span>
                                            <span class="toggle-icon">▼</span>
                                        </div>
                                        
                                        <div class="collapsible-content collapsed">
                                            <form method="POST" onsubmit="return confirmSubmit(this)">
                                                <input type="hidden" name="academic_year" value="<?php echo $year; ?>">
                                                <input type="hidden" name="term" value="<?php echo $term; ?>">
                                                <input type="hidden" name="assessment_type" value="<?php echo $assessment; ?>">
                                                
                                                <?php if ($is_year11_endterm): ?>
                                                    <div style="background: #fff3e0; padding: 15px; border-radius: 6px; margin-bottom: 20px; border-left: 4px solid #ff9800;">
                                                        <h4 style="color: #e65100; margin-bottom: 10px;">⚠️ Year 11 End-Term SETS</h4>
                                                        <p style="font-size: 13px; color: #666;">
                                                            This is a SETS examination. Set custom grade boundaries for each subject below before entering marks.
                                                        </p>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php 
                                                // Filter subjects based on teacher category
                                                $subjects_to_grade = $student_subjects;
                                                if ($teacher['category'] == 'Subject Teacher' && !empty($teacher_subjects)) {
                                                    $subjects_to_grade = array_intersect($student_subjects, $teacher_subjects);
                                                }
                                                
                                                foreach ($subjects_to_grade as $subject):
                                                    $grade_data = $grades_organized[$year][$term][$assessment][$subject] ?? [];
                                                    $existing_papers = $grade_data['paper_scores'] ?? [];
                                                    $existing_rats = $grade_data['rats_score'] ?? '';
                                                    $existing_comment = $grade_data['comment'] ?? '';
                                                    $existing_boundaries = $boundaries_stored[$year][$term][$assessment][$subject] ?? [];
                                                    
                                                    $has_multiple_papers = ($is_year11_endterm && isset($year11_sets_papers[$subject]));
                                                    $max_score = 100;
                                                    if ($has_multiple_papers) {
                                                        $max_score = $year11_sets_papers[$subject]['total'];
                                                    }
                                                ?>
                                                    <div class="subject-card">
                                                        <h4><?php echo htmlspecialchars($subject); ?></h4>
                                                        
                                                    <?php if ($is_year11_endterm && !$is_locked): ?>
                                                            <!-- Grade Boundaries Table -->
                                                            <div class="boundaries-table">
                                                                <h4>📊 Set Grade Boundaries (Total: <?php echo $max_score; ?> marks)</h4>
                                                                <p style="font-size: 11px; color: #666; margin-bottom: 10px;">
                                                                    Enter the minimum score required for each grade:
                                                                </p>
                                                                <div class="boundaries-grid">
                                                                    <?php 
                                                                    $grades = ['9', '8', '7', '6', '5', '4', '3', '2', '1', 'U'];
                                                                    foreach ($grades as $g): 
                                                                    ?>
                                                                        <div class="boundary-item">
                                                                            <label><?php echo $g; ?></label>
                                                                            <input type="number" 
                                                                                   name="boundaries[<?php echo htmlspecialchars($subject); ?>][<?php echo $g; ?>]"
                                                                                   min="0"
                                                                                   max="<?php echo $max_score; ?>"
                                                                                   step="1"
                                                                                   value="<?php echo $existing_boundaries[$g] ?? ''; ?>"
                                                                                   placeholder="<?php echo $g == 'U' ? '0' : ''; ?>"
                                                                                   <?php echo $g == 'U' ? 'readonly' : ''; ?>
                                                                                   <?php echo $g == 'U' ? 'style="background:#f0f0f0;"' : ''; ?>>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        <?php elseif ($is_year11_endterm && $is_locked && !empty($existing_boundaries)): ?>
                                                            <!-- Display locked boundaries -->
                                                            <div class="boundaries-table" style="background: #f0f0f0;">
                                                                <h4>📊 Grade Boundaries (Locked)</h4>
                                                                <div style="font-size: 12px; color: #666;">
                                                                    <?php foreach (['9', '8', '7', '6', '5', '4', '3', '2', '1'] as $g): ?>
                                                                        <span style="margin-right: 10px;"><strong><?php echo $g; ?>:</strong> <?php echo $existing_boundaries[$g] ?? '-'; ?></span>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <!-- Paper Inputs -->
                                                        <?php if ($has_multiple_papers): ?>
                                                            <p style="font-size: 13px; color: #666; margin-bottom: 10px;">
                                                                📝 Enter marks for each paper:
                                                            </p>
                                                            <div class="paper-inputs">
                                                                <?php foreach ($year11_sets_papers[$subject]['papers'] as $paper): ?>
                                                                    <div class="paper-input-item">
                                                                        <label>
                                                                            <?php echo $paper['name']; ?> 
                                                                            <span style="color: #999;">(out of <?php echo $paper['max']; ?>)</span>
                                                                        </label>
                                                                        <input type="number" 
                                                                               name="grades[<?php echo htmlspecialchars($subject); ?>][<?php echo $paper['name']; ?>]"
                                                                               min="0" 
                                                                               max="<?php echo $paper['max']; ?>"
                                                                               step="0.5"
                                                                               value="<?php echo $existing_papers[$paper['name']] ?? ''; ?>"
                                                                               placeholder="0"
                                                                               <?php echo $is_locked ? 'disabled' : ''; ?>>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php else: ?>
                                                            <p style="font-size: 13px; color: #666; margin-bottom: 10px;">
                                                                📝 Enter exam score:
                                                            </p>
                                                            <div class="paper-input-item">
                                                                <label>Exam Score (out of 100)</label>
                                                                <input type="number" 
                                                                       name="grades[<?php echo htmlspecialchars($subject); ?>][Total]"
                                                                       min="0" 
                                                                       max="100"
                                                                       step="0.5"
                                                                       value="<?php echo $existing_papers['Total'] ?? ''; ?>"
                                                                       placeholder="0"
                                                                       <?php echo $is_locked ? 'disabled' : ''; ?>>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <!-- RATs Section -->
                                                        <div class="rats-section">
                                                            <label>RATs Score (Continuous Assessment)</label>
                                                            <input type="number" 
                                                                   name="rats[<?php echo htmlspecialchars($subject); ?>]"
                                                                   min="0" 
                                                                   max="100"
                                                                   step="0.5"
                                                                   placeholder="RATs out of 100"
                                                                   value="<?php echo $existing_rats; ?>"
                                                                   style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px;"
                                                                   <?php echo $is_locked ? 'disabled' : ''; ?>>
                                                            <small style="display: block; margin-top: 5px; color: #666;">
                                                                RATs (Continuous Assessment) - Enter score out of 100
                                                            </small>
                                                        </div>
                                                        
                                                        <div class="calculated-info">
                                                            ℹ️ <strong>Final Calculation:</strong> Exam = 70% | RATs = 30%
                                                        </div>
                                                        
                                                        <!-- Teacher Comment -->
                                                        <div style="margin-top: 10px;">
                                                            <label style="font-size: 13px; color: #666;">Teacher's Comment</label>
                                                            <textarea name="comments[<?php echo htmlspecialchars($subject); ?>]" 
                                                                      rows="2"
                                                                      placeholder="Optional comment about student's performance"
                                                                      style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; font-size: 13px; resize: vertical;"
                                                                      <?php echo $is_locked ? 'disabled' : ''; ?>><?php echo htmlspecialchars($existing_comment); ?></textarea>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                                
                                                <?php if (!$is_locked): ?>
                                                    <div class="lock-checkbox">
                                                        <input type="checkbox" name="lock_submission" value="1" id="lock_<?php echo $year . '_' . $term . '_' . $assessment; ?>">
                                                        <label for="lock_<?php echo $year . '_' . $term . '_' . $assessment; ?>">
                                                            🔒 Lock these grades after submission
                                                        </label>
                                                    </div>
                                                    <button type="submit" class="button">💾 Save Grades</button>
                                                <?php else: ?>
                                                    <p style="color: #f44336; font-weight: 600;">🔒 These grades are locked. Contact admin to unlock.</p>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <a href="../teacher_dashboard.php" class="button button-yellow" style="margin-top: 20px;">← Back to Dashboard</a>
        </div>
    </div>
</div>

<script>
function toggleSection(header) {
    const content = header.nextElementSibling;
    header.classList.toggle('collapsed');
    content.classList.toggle('collapsed');
}

function confirmSubmit(form) {
    const lockCheckbox = form.querySelector('input[name="lock_submission"]');
    if (lockCheckbox && lockCheckbox.checked) {
        return confirm('⚠️ WARNING: You are about to LOCK these grades.\n\nOnce locked:\n• You cannot edit them\n• Only admin can unlock them\n\nAre you absolutely sure?');
    }
    return true;
}

// Collapse all by default except current year
window.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.collapsible-content').forEach((content, index) => {
        if (index > 0) {
            content.classList.add('collapsed');
            content.previousElementSibling.classList.add('collapsed');
        }
    });
});
</script>

</body>
</html>