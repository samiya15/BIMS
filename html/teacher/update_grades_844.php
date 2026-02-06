<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Teacher') {
    header("Location: ../login.php");
    exit;
}

$student_id = (int)($_GET['student_id'] ?? 0);

/* ---------- 8-4-4 SUBJECT PAPER STRUCTURE ---------- */
$subject_papers = [
    'Mathematics' => ['papers' => [['name' => 'Paper 1', 'max' => 100], ['name' => 'Paper 2', 'max' => 100]], 'divisor' => 2],
    'Chemistry' => ['papers' => [['name' => 'Paper 1', 'max' => 80], ['name' => 'Paper 2', 'max' => 80], ['name' => 'Paper 3', 'max' => 40]], 'divisor' => 2],
    'Biology' => ['papers' => [['name' => 'Paper 1', 'max' => 80], ['name' => 'Paper 2', 'max' => 80], ['name' => 'Paper 3', 'max' => 40]], 'divisor' => 2],
    'Physics' => ['papers' => [['name' => 'Paper 1', 'max' => 80], ['name' => 'Paper 2', 'max' => 80], ['name' => 'Paper 3', 'max' => 40]], 'divisor' => 2],
    'English' => ['papers' => [['name' => 'Paper 1', 'max' => 60], ['name' => 'Paper 2', 'max' => 80], ['name' => 'Paper 3', 'max' => 60]], 'divisor' => 2],
    'Kiswahili' => ['papers' => [['name' => 'Paper 1', 'max' => 80], ['name' => 'Paper 2', 'max' => 60], ['name' => 'Paper 3', 'max' => 80]], 'divisor' => 2.2],
    'Geography' => ['papers' => [['name' => 'Paper 1', 'max' => 100], ['name' => 'Paper 2', 'max' => 100]], 'divisor' => 2],
    'History' => ['papers' => [['name' => 'Paper 1', 'max' => 100], ['name' => 'Paper 2', 'max' => 100]], 'divisor' => 2],
    'Arabic' => ['papers' => [['name' => 'Paper 1', 'max' => 80], ['name' => 'Paper 2', 'max' => 80], ['name' => 'Paper 3', 'max' => 40]], 'divisor' => 2],
    'Computer Studies' => ['papers' => [['name' => 'Paper 1', 'max' => 100], ['name' => 'Paper 2', 'max' => 100]], 'divisor' => 2],
    'Business Studies' => ['papers' => [['name' => 'Paper 1', 'max' => 100], ['name' => 'Paper 2', 'max' => 100]], 'divisor' => 2]
];

/* ---------- GET TEACHER INFO ---------- */
$teacher_stmt = $pdo->prepare("SELECT id, category FROM teachers WHERE user_id = ?");
$teacher_stmt->execute([$_SESSION['user_id']]);
$teacher = $teacher_stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    die("Teacher profile not found");
}

/* ---------- GET TEACHER'S SUBJECTS ---------- */
$teacher_subjects_stmt = $pdo->prepare("SELECT DISTINCT subject_name FROM teacher_subjects WHERE teacher_id = ?");
$teacher_subjects_stmt->execute([$teacher['id']]);
$teacher_subjects = $teacher_subjects_stmt->fetchAll(PDO::FETCH_COLUMN);

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

if ($student['curriculum_name'] !== '8-4-4') {
    die("This page is only for 8-4-4 curriculum students");
}

/* ---------- GET STUDENT'S SUBJECTS ---------- */
$student_subjects_stmt = $pdo->prepare("SELECT subject_name FROM student_subjects WHERE student_id = ? ORDER BY subject_name");
$student_subjects_stmt->execute([$student_id]);
$student_subjects = $student_subjects_stmt->fetchAll(PDO::FETCH_COLUMN);

/* ---------- GET ALL EXISTING GRADES ---------- */
$grades_stmt = $pdo->prepare("
    SELECT subject_name, grade, final_score, paper_scores, rats_score, exam_type, assessment_type, term, academic_year, is_locked, teacher_comment
    FROM grades WHERE student_id = ?
");
$grades_stmt->execute([$student_id]);
$all_grades = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);

$grades_organized = [];
$exam_types_stored = []; // Track exam type per assessment
foreach ($all_grades as $g) {
    $grades_organized[$g['academic_year']][$g['term']][$g['assessment_type']][$g['subject_name']] = [
        'final_score' => $g['final_score'],
        'grade' => $g['grade'],
        'is_locked' => $g['is_locked'],
        'comment' => $g['teacher_comment'] ?? '',
        'paper_scores' => $g['paper_scores'] ? json_decode($g['paper_scores'], true) : [],
        'rats_score' => $g['rats_score'],
        'exam_type' => $g['exam_type'] ?? 'full_papers'
    ];
    
    // Store exam type for this assessment period
    $exam_types_stored[$g['academic_year']][$g['term']][$g['assessment_type']] = $g['exam_type'] ?? 'full_papers';
}

/* ---------- CHECK PERMISSIONS ---------- */
function isUploadEnabled($year, $term, $assessment, $curriculum, $pdo) {
    $check = $pdo->prepare("SELECT is_enabled FROM grade_upload_permissions WHERE academic_year = ? AND term = ? AND assessment_type = ? AND curriculum_name = ?");
    $check->execute([$year, $term, $assessment, $curriculum]);
    $result = $check->fetch();
    return $result ? (bool)$result['is_enabled'] : false;
}

function areGradesLocked($student_id, $year, $term, $assessment, $pdo) {
    $check = $pdo->prepare("SELECT is_locked FROM grade_submissions WHERE student_id = ? AND academic_year = ? AND term = ? AND assessment_type = ?");
    $check->execute([$student_id, $year, $term, $assessment]);
    $result = $check->fetch();
    return $result ? (bool)$result['is_locked'] : false;
}

/* ---------- HANDLE GRADE SUBMISSION ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $selected_year = (int)$_POST['academic_year'];
    $selected_term = $_POST['term'];
    $selected_assessment = $_POST['assessment_type'];
    $exam_type = $_POST['exam_type'] ?? 'full_papers'; // full_papers or single_paper
    $grades_data = $_POST['grades'] ?? [];
    $rats_data = $_POST['rats'] ?? [];
    $comments_data = $_POST['comments'] ?? [];
    $lock_submission = isset($_POST['lock_submission']) ? 1 : 0;
    
    $is_locked = areGradesLocked($student_id, $selected_year, $selected_term, $selected_assessment, $pdo);
    
    if ($is_locked) {
        $error = "These grades are locked and cannot be edited.";
    } else {
        $current_year = (int)date('Y');
        $can_upload = false;
        
        if ($teacher['category'] == 'Class Teacher') {
            if ($selected_year == $current_year || isUploadEnabled($selected_year, $selected_term, $selected_assessment, $student['curriculum_name'], $pdo)) {
                $can_upload = true;
            }
        } elseif ($teacher['category'] == 'Subject Teacher' && $selected_year == $current_year) {
            $can_upload = true;
        }
        
        if (!$can_upload) {
            $error = "You do not have permission to upload grades for this period.";
        } else {
            try {
                $pdo->beginTransaction();
                
                foreach ($grades_data as $subject => $paper_data) {
                    if (empty($paper_data) || !is_array($paper_data)) continue;
                    
                    // Calculate total from papers
                    $total_raw = 0;
                    $papers_done = [];
                    
                    foreach ($paper_data as $paper_name => $score) {
                        if ($score !== '' && $score !== null) {
                            $total_raw += (float)$score;
                            $papers_done[$paper_name] = (float)$score;
                        }
                    }
                    
                    if (empty($papers_done)) continue;
                    
                    // Calculate paper score out of 100
                    $paper_score = 0;
                    
                    if ($exam_type == 'single_paper') {
                        // Single paper mode - score is already out of 100
                        $paper_score = $total_raw;
                    } else {
                        // Full papers mode - calculate using divisor
                        if (isset($subject_papers[$subject])) {
                            $paper_score = round($total_raw / $subject_papers[$subject]['divisor'], 1);
                        } else {
                            $paper_score = $total_raw;
                        }
                    }
                    
                    if ($paper_score > 100) $paper_score = 100;
                    
                    // Handle RATs for Mid-Term and End-Term
                    $rats_score = null;
                    $final_score = $paper_score;
                    
                    if ($selected_assessment != 'Opener') {
                        // Mid-Term and End-Term have RATs
                        $rats_score = isset($rats_data[$subject]) ? (float)$rats_data[$subject] : 0;
                        // Paper is 80%, RATs is 20%
                        $final_score = round(($paper_score * 0.8) + ($rats_score * 0.2), 1);
                    }
                    
                    if ($final_score > 100) $final_score = 100;
                    
                    // Calculate grade (8-4-4 system)
                    $grade = '';
                    if ($final_score >= 80) $grade = 'A';
                    elseif ($final_score >= 75) $grade = 'A-';
                    elseif ($final_score >= 70) $grade = 'B+';
                    elseif ($final_score >= 65) $grade = 'B';
                    elseif ($final_score >= 60) $grade = 'B-';
                    elseif ($final_score >= 55) $grade = 'C+';
                    elseif ($final_score >= 50) $grade = 'C';
                    elseif ($final_score >= 45) $grade = 'C-';
                    elseif ($final_score >= 40) $grade = 'D+';
                    elseif ($final_score >= 35) $grade = 'D';
                    elseif ($final_score >= 30) $grade = 'D-';
                    else $grade = 'E';
                    
                    $grade_points_map = [
                        'A' => 12, 'A-' => 11, 'B+' => 10, 'B' => 9, 'B-' => 8,
                        'C+' => 7, 'C' => 6, 'C-' => 5, 'D+' => 4, 'D' => 3, 'D-' => 2, 'E' => 1
                    ];
                    $grade_points = $grade_points_map[$grade] ?? 0;
                    
                    $teacher_comment = isset($comments_data[$subject]) ? trim($comments_data[$subject]) : null;
                    $paper_scores_json = json_encode($papers_done);
                    
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
                                exam_type = ?,
                                teacher_id = ?, 
                                is_locked = ?, 
                                teacher_comment = ?, 
                                updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $update_stmt->execute([$grade, $final_score, $grade_points, $paper_scores_json, $rats_score, $exam_type, $teacher['id'], $lock_submission, $teacher_comment, $existing['id']]);
                    } else {
                        $insert_stmt = $pdo->prepare("
                            INSERT INTO grades 
                            (student_id, subject_name, grade, final_score, grade_points, paper_scores, rats_score, exam_type, term, assessment_type, academic_year, teacher_id, is_locked, teacher_comment) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $insert_stmt->execute([$student_id, $subject, $grade, $final_score, $grade_points, $paper_scores_json, $rats_score, $exam_type, $selected_term, $selected_assessment, $selected_year, $teacher['id'], $lock_submission, $teacher_comment]);
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
                header("Location: update_grades_844.php?student_id=$student_id&success=1");
                exit;
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

$current_year = (int)date('Y');
$years = range($student['year_of_enrollment'], $current_year);
$terms = ['Term 1', 'Term 2', 'Term 3'];
$assessments = ['Opener', 'Mid-Term', 'End-Term'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Grades - 8-4-4</title>
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
        .exam-type-selector {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid #2196f3;
        }
        .exam-type-selector label {
            font-weight: 600;
            color: #1976d2;
            display: block;
            margin-bottom: 10px;
        }
        .exam-type-selector select {
            width: 100%;
            padding: 10px;
            border: 2px solid #2196f3;
            border-radius: 4px;
            font-size: 15px;
        }
        .subject-card {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #2196f3;
            margin-bottom: 15px;
        }
        .subject-card h4 {
            color: var(--navy);
            margin-bottom: 10px;
        }
        .paper-inputs {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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
        .paper-input-item input:focus {
            border-color: var(--yellow);
            outline: none;
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
        .locked-badge {
            background: #f44336;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 10px;
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
            <h2>📝 Manage Student Grades (8-4-4 System)</h2>

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
                    <a href="view_report_card_844.php?student_id=<?php echo $student_id; ?>" class="button" style="background: var(--yellow); color: var(--black);">
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
                                <div class="term-header collapsible-header collapsed" onclick="toggleSection(this)">
                                    <span><?php echo $term; ?></span>
                                    <span class="toggle-icon">▼</span>
                                </div>
                                
                                <div class="collapsible-content collapsed">
                                    <?php foreach ($assessments as $assessment): ?>
                                        <?php
                                        $is_locked = areGradesLocked($student_id, $year, $term, $assessment, $pdo);
                                        $has_rats = ($assessment != 'Opener');
                                        $stored_exam_type = $exam_types_stored[$year][$term][$assessment] ?? 'full_papers';
                                        ?>
                                        
                                        <div class="assessment-header collapsible-header collapsed" onclick="toggleSection(this)">
                                            <span>
                                                <?php echo $assessment; ?>
                                                <?php if ($is_locked): ?>
                                                    <span class="locked-badge">🔒 Locked</span>
                                                <?php endif; ?>
                                                <a href="view_report_card_844.php?student_id=<?php echo $student_id; ?>&year=<?php echo $year; ?>&term=<?php echo urlencode($term); ?>&assessment=<?php echo urlencode($assessment); ?>" 
                                                   class="view-report-btn" 
                                                   onclick="event.stopPropagation();">
                                                    📄 View Report Card
                                                </a>
                                            </span>
                                            <span class="toggle-icon">▼</span>
                                        </div>
                                        
                                        <div class="collapsible-content collapsed">
                                            <form method="POST" onsubmit="return confirmSubmit(this)" id="form_<?php echo $year . '_' . $term . '_' . $assessment; ?>">
                                                <input type="hidden" name="academic_year" value="<?php echo $year; ?>">
                                                <input type="hidden" name="term" value="<?php echo $term; ?>">
                                                <input type="hidden" name="assessment_type" value="<?php echo $assessment; ?>">
                                                
                                                <!-- Exam Type Selector for ALL assessments -->
                                                <?php if (!$is_locked): ?>
                                                    <div class="exam-type-selector">
                                                        <label>📝 Exam Type</label>
                                                        <select name="exam_type" 
                                                                id="exam_type_<?php echo $year . '_' . $term . '_' . $assessment; ?>" 
                                                                onchange="toggleExamType(this, '<?php echo $year . '_' . $term . '_' . $assessment; ?>')">
                                                            <option value="full_papers" <?php echo $stored_exam_type == 'full_papers' ? 'selected' : ''; ?>>Full Papers (All Papers Done)</option>
                                                            <option value="single_paper" <?php echo $stored_exam_type == 'single_paper' ? 'selected' : ''; ?>>Single Paper (One Paper Only)</option>
                                                        </select>
                                                        <small style="display: block; margin-top: 5px; color: #666;">
                                                            Choose whether students did all papers or just one paper for this exam.
                                                        </small>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- Show exam type but disabled when locked -->
                                                    <div class="exam-type-selector" style="background: #f0f0f0;">
                                                        <label>📝 Exam Type (Locked)</label>
                                                        <select disabled style="background: #f0f0f0;">
                                                            <option><?php echo $stored_exam_type == 'single_paper' ? 'Single Paper (One Paper Only)' : 'Full Papers (All Papers Done)'; ?></option>
                                                        </select>
                                                    </div>
                                                    <input type="hidden" name="exam_type" value="<?php echo $stored_exam_type; ?>">
                                                <?php endif; ?>
                                                
                                                <div id="subjects_container_<?php echo $year . '_' . $term . '_' . $assessment; ?>">
                                                    <?php
                                                    $subjects_to_grade = $student_subjects;
                                                    if ($teacher['category'] == 'Subject Teacher' && !empty($teacher_subjects)) {
                                                        $subjects_to_grade = array_intersect($student_subjects, $teacher_subjects);
                                                    }
                                                    
                                                    foreach ($subjects_to_grade as $subject):
                                                        $grade_data = $grades_organized[$year][$term][$assessment][$subject] ?? [];
                                                        $existing_papers = $grade_data['paper_scores'] ?? [];
                                                        $existing_rats = $grade_data['rats_score'] ?? '';
                                                        $existing_comment = $grade_data['comment'] ?? '';
                                                        $is_multi_paper = isset($subject_papers[$subject]);
                                                    ?>
                                                        <div class="subject-card">
                                                            <h4><?php echo htmlspecialchars($subject); ?></h4>
                                                            
                                                            <!-- Full Papers Input -->
                                                            <div class="full-papers-input" data-form-id="<?php echo $year . '_' . $term . '_' . $assessment; ?>" style="<?php echo $stored_exam_type == 'single_paper' ? 'display:none;' : ''; ?>">
                                                                <?php if ($is_multi_paper): ?>
                                                                    <p style="font-size: 13px; color: #666; margin-bottom: 10px;">
                                                                        📝 Enter marks for each paper:
                                                                    </p>
                                                                    <div class="paper-inputs">
                                                                        <?php foreach ($subject_papers[$subject]['papers'] as $paper): ?>
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
                                                                                       class="full-paper-input"
                                                                                       <?php echo $is_locked ? 'disabled' : ''; ?>>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <div class="paper-input-item">
                                                                        <label>Score (out of 100)</label>
                                                                        <input type="number" 
                                                                               name="grades[<?php echo htmlspecialchars($subject); ?>][Total]"
                                                                               min="0" 
                                                                               max="100"
                                                                               step="0.5"
                                                                               value="<?php echo $existing_papers['Total'] ?? ''; ?>"
                                                                               placeholder="0"
                                                                               class="full-paper-input"
                                                                               <?php echo $is_locked ? 'disabled' : ''; ?>>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            
                                                            <!-- Single Paper Input -->
                                                            <div class="single-paper-input" data-form-id="<?php echo $year . '_' . $term . '_' . $assessment; ?>" style="<?php echo $stored_exam_type == 'full_papers' ? 'display:none;' : ''; ?>">
                                                                <p style="font-size: 13px; color: #666; margin-bottom: 10px;">
                                                                    📝 Single paper exam - enter score out of 100:
                                                                </p>
                                                                <div class="paper-input-item">
                                                                    <label>Exam Score (out of 100)</label>
                                                                    <input type="number" 
                                                                           name="grades[<?php echo htmlspecialchars($subject); ?>][SinglePaper]"
                                                                           min="0" 
                                                                           max="100"
                                                                           step="0.5"
                                                                           value="<?php echo $existing_papers['SinglePaper'] ?? ''; ?>"
                                                                           placeholder="0"
                                                                           class="single-paper-input"
                                                                           <?php echo $is_locked ? 'disabled' : ''; ?>>
                                                                </div>
                                                            </div>
                                                            
                                                            <?php if ($has_rats): ?>
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
                                                                        RATs (Continuous Assessment Tests) - Enter score out of 100
                                                                    </small>
                                                                </div>
                                                                <div class="calculated-info">
                                                                    ℹ️ <strong>Final Calculation:</strong> Papers = 80% | RATs = 20%
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="calculated-info">
                                                                    ℹ️ <strong>Opener Exam:</strong> Only paper scores count (no RATs)
                                                                </div>
                                                            <?php endif; ?>
                                                            
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
                                                </div>
                                                
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

function toggleExamType(selectElement, formId) {
    const examType = selectElement.value;
    
    // Get all subject cards in this form
    const fullPaperInputs = document.querySelectorAll(`.full-papers-input[data-form-id="${formId}"]`);
    const singlePaperInputs = document.querySelectorAll(`.single-paper-input[data-form-id="${formId}"]`);
    
    if (examType === 'full_papers') {
        // Show full papers, hide single paper
        fullPaperInputs.forEach(elem => elem.style.display = 'block');
        singlePaperInputs.forEach(elem => elem.style.display = 'none');
        
        // Disable single paper inputs, enable full paper inputs
        document.querySelectorAll(`.single-paper-input[data-form-id="${formId}"] input`).forEach(inp => {
            inp.disabled = true;
            inp.value = '';
        });
        document.querySelectorAll(`.full-papers-input[data-form-id="${formId}"] .full-paper-input`).forEach(inp => {
            inp.disabled = false;
        });
    } else {
        // Show single paper, hide full papers
        fullPaperInputs.forEach(elem => elem.style.display = 'none');
        singlePaperInputs.forEach(elem => elem.style.display = 'block');
        
        // Disable full paper inputs, enable single paper inputs
        document.querySelectorAll(`.full-papers-input[data-form-id="${formId}"] .full-paper-input`).forEach(inp => {
            inp.disabled = true;
            inp.value = '';
        });
        document.querySelectorAll(`.single-paper-input[data-form-id="${formId}"] input`).forEach(inp => {
            inp.disabled = false;
        });
    }
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