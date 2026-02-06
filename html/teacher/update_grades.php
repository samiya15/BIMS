<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Teacher') {
    header("Location: ../login.php");
    exit;
}

$student_id = (int)($_GET['student_id'] ?? 0);

/* ---------- GET TEACHER INFO ---------- */
$teacher_stmt = $pdo->prepare("SELECT id, category, assigned_class_id FROM teachers WHERE user_id = ?");
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
        s.class_level_id, cl.name as class_name, ct.name as curriculum_name
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

/* ---------- GET STUDENT'S SUBJECTS ---------- */
$student_subjects_stmt = $pdo->prepare("SELECT subject_name FROM student_subjects WHERE student_id = ? ORDER BY subject_name");
$student_subjects_stmt->execute([$student_id]);
$student_subjects = $student_subjects_stmt->fetchAll(PDO::FETCH_COLUMN);

/* ---------- GET ALL EXISTING GRADES ---------- */
$grades_stmt = $pdo->prepare("
    SELECT subject_name, grade, score, rats_score, final_score, assessment_type, term, academic_year, is_locked, teacher_comment
    FROM grades WHERE student_id = ?
");
$grades_stmt->execute([$student_id]);
$all_grades = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize: year -> term -> assessment -> subject -> data
$grades_organized = [];
foreach ($all_grades as $g) {
    $grades_organized[$g['academic_year']][$g['term']][$g['assessment_type']][$g['subject_name']] = [
        'score' => $g['score'],
        'rats_score' => $g['rats_score'],
        'final_score' => $g['final_score'],
        'grade' => $g['grade'],
        'is_locked' => $g['is_locked'],
        'comment' => $g['teacher_comment'] ?? ''
    ];
}

/* ---------- GET ALL PCI DATA ---------- */
$pci_stmt = $pdo->prepare("SELECT * FROM pci_assessments WHERE student_id = ?");
$pci_stmt->execute([$student_id]);
$all_pci = $pci_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize by year -> term
$pci_organized = [];
foreach ($all_pci as $pci) {
    $pci_organized[$pci['academic_year']][$pci['term']] = $pci;
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
    $grades_data = $_POST['grades'] ?? [];
    $rats_data = $_POST['rats'] ?? [];
    $comments_data = $_POST['comments'] ?? [];
    $lock_submission = isset($_POST['lock_submission']) ? 1 : 0;
    $class_teacher_comment = isset($_POST['class_teacher_comment']) ? trim($_POST['class_teacher_comment']) : null;
    $pci_data = $_POST['pci'] ?? []; // PCI data for End-Term
    
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
                
                foreach ($grades_data as $subject => $score) {
                    if ($score === '' || $score === null) continue;
                    
                    $score = (int)$score;
                    $rats_score = isset($rats_data[$subject]) ? (int)$rats_data[$subject] : null;
                    $teacher_comment = isset($comments_data[$subject]) ? trim($comments_data[$subject]) : null;
                    
                    if ($selected_assessment == 'Opener') {
                        $final_score = $score;
                    } else {
                        $final_score = $rats_score !== null ? $score + $rats_score : $score;
                    }
                    
                    $grade = '';
                    if ($final_score >= 90) $grade = 'EE1';
                    elseif ($final_score >= 75) $grade = 'EE2';
                    elseif ($final_score >= 58) $grade = 'ME1';
                    elseif ($final_score >= 41) $grade = 'ME2';
                    elseif ($final_score >= 31) $grade = 'AE1';
                    elseif ($final_score >= 21) $grade = 'AE2';
                    elseif ($final_score >= 11) $grade = 'BE1';
                    else $grade = 'BE2';
                    
                    $grade_points = null;
                    $points_stmt = $pdo->prepare("SELECT points FROM cbe_grading_scale WHERE grade_code = ?");
                    $points_stmt->execute([$grade]);
                    $points_row = $points_stmt->fetch();
                    $grade_points = $points_row ? $points_row['points'] : null;
                    
                    $check_stmt = $pdo->prepare("SELECT id, teacher_id FROM grades WHERE student_id = ? AND academic_year = ? AND term = ? AND assessment_type = ? AND subject_name = ?");
                    $check_stmt->execute([$student_id, $selected_year, $selected_term, $selected_assessment, $subject]);
                    $existing = $check_stmt->fetch();
                    
                    if ($existing) {
                        // Preserve original teacher_id if class teacher is just reviewing
                        $teacher_id_to_use = $existing['teacher_id'];
                        
                        // Only change teacher_id if:
                        // 1. Current teacher is a Subject Teacher (they're entering their own grades)
                        // 2. OR there was no teacher_id before (first time entry)
                        if ($teacher['category'] == 'Subject Teacher' || empty($existing['teacher_id'])) {
                            $teacher_id_to_use = $teacher['id'];
                        }
                        
                        $update_stmt = $pdo->prepare("UPDATE grades SET grade = ?, score = ?, rats_score = ?, final_score = ?, grade_points = ?, teacher_id = ?, is_locked = ?, teacher_comment = ?, updated_at = NOW() WHERE id = ?");
                        $update_stmt->execute([$grade, $score, $rats_score, $final_score, $grade_points, $teacher_id_to_use, $lock_submission, $teacher_comment, $existing['id']]);
                    } else {
                        // New grade entry - use current teacher's id
                        $insert_stmt = $pdo->prepare("INSERT INTO grades (student_id, subject_name, grade, score, rats_score, final_score, grade_points, term, assessment_type, academic_year, teacher_id, is_locked, teacher_comment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $insert_stmt->execute([$student_id, $subject, $grade, $score, $rats_score, $final_score, $grade_points, $selected_term, $selected_assessment, $selected_year, $teacher['id'], $lock_submission, $teacher_comment]);
                    }
                }
                
                if ($lock_submission) {
                    // Get class teacher comment if provided (only for class teachers)
                    $class_teacher_comment = isset($_POST['class_teacher_comment']) ? trim($_POST['class_teacher_comment']) : null;
                    
                    $check_sub = $pdo->prepare("SELECT id FROM grade_submissions WHERE student_id = ? AND academic_year = ? AND term = ? AND assessment_type = ?");
                    $check_sub->execute([$student_id, $selected_year, $selected_term, $selected_assessment]);
                    $existing_sub = $check_sub->fetch();
                    
                    // Only Class Teachers submit to principal with status
                    if ($teacher['category'] == 'Class Teacher') {
                        $submission_status = 'AWAITING_PRINCIPAL';
                        $submitted_to_principal_at = date('Y-m-d H:i:s');
                        
                        if ($existing_sub) {
                            $update_sub = $pdo->prepare("UPDATE grade_submissions SET is_locked = 1, teacher_id = ?, submitted_at = NOW(), status = ?, class_teacher_comment = ?, submitted_to_principal_at = ? WHERE id = ?");
                            $update_sub->execute([$teacher['id'], $submission_status, $class_teacher_comment, $submitted_to_principal_at, $existing_sub['id']]);
                        } else {
                            $insert_sub = $pdo->prepare("INSERT INTO grade_submissions (student_id, teacher_id, academic_year, term, assessment_type, is_locked, status, class_teacher_comment, submitted_to_principal_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)");
                            $insert_sub->execute([$student_id, $teacher['id'], $selected_year, $selected_term, $selected_assessment, $submission_status, $class_teacher_comment, $submitted_to_principal_at]);
                        }
                    } else {
                        // Subject Teachers just lock, no status
                        if ($existing_sub) {
                            $update_sub = $pdo->prepare("UPDATE grade_submissions SET is_locked = 1, teacher_id = ?, submitted_at = NOW() WHERE id = ?");
                            $update_sub->execute([$teacher['id'], $existing_sub['id']]);
                        } else {
                            $insert_sub = $pdo->prepare("INSERT INTO grade_submissions (student_id, teacher_id, academic_year, term, assessment_type, is_locked) VALUES (?, ?, ?, ?, ?, 1)");
                            $insert_sub->execute([$student_id, $teacher['id'], $selected_year, $selected_term, $selected_assessment]);
                        }
                    }
                }
                
                // Save PCI data for End-Term assessments (Class Teacher only)
                if ($selected_assessment == 'End-Term' && !empty($pci_data) && $teacher['category'] == 'Class Teacher') {
                    // Check if PCI record exists
                    $pci_check = $pdo->prepare("SELECT id FROM pci_assessments WHERE student_id = ? AND academic_year = ? AND term = ?");
                    $pci_check->execute([$student_id, $selected_year, $selected_term]);
                    $existing_pci = $pci_check->fetch();
                    
                    if ($existing_pci) {
                        // Update existing PCI
                        $update_pci = $pdo->prepare("
                            UPDATE pci_assessments SET
                                communication_collaboration = ?,
                                self_efficacy = ?,
                                critical_thinking = ?,
                                creativity_imagination = ?,
                                citizenship = ?,
                                digital_literacy = ?,
                                learning_to_learn = ?,
                                love = ?,
                                respect = ?,
                                responsibility = ?,
                                unity = ?,
                                peace = ?,
                                integrity = ?,
                                discipline = ?,
                                organization = ?,
                                tidiness = ?,
                                projects_manipulative_skills = ?,
                                extended_activities = ?,
                                teacher_id = ?,
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $update_pci->execute([
                            $pci_data['communication_collaboration'] ?? null,
                            $pci_data['self_efficacy'] ?? null,
                            $pci_data['critical_thinking'] ?? null,
                            $pci_data['creativity_imagination'] ?? null,
                            $pci_data['citizenship'] ?? null,
                            $pci_data['digital_literacy'] ?? null,
                            $pci_data['learning_to_learn'] ?? null,
                            $pci_data['love'] ?? null,
                            $pci_data['respect'] ?? null,
                            $pci_data['responsibility'] ?? null,
                            $pci_data['unity'] ?? null,
                            $pci_data['peace'] ?? null,
                            $pci_data['integrity'] ?? null,
                            $pci_data['discipline'] ?? null,
                            $pci_data['organization'] ?? null,
                            $pci_data['tidiness'] ?? null,
                            $pci_data['projects_manipulative_skills'] ?? null,
                            $pci_data['extended_activities'] ?? null,
                            $teacher['id'],
                            $existing_pci['id']
                        ]);
                    } else {
                        // Insert new PCI
                        $insert_pci = $pdo->prepare("
                            INSERT INTO pci_assessments (
                                student_id, academic_year, term,
                                communication_collaboration, self_efficacy, critical_thinking,
                                creativity_imagination, citizenship, digital_literacy, learning_to_learn,
                                love, respect, responsibility, unity, peace, integrity,
                                discipline, organization, tidiness, projects_manipulative_skills, extended_activities,
                                teacher_id
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $insert_pci->execute([
                            $student_id, $selected_year, $selected_term,
                            $pci_data['communication_collaboration'] ?? null,
                            $pci_data['self_efficacy'] ?? null,
                            $pci_data['critical_thinking'] ?? null,
                            $pci_data['creativity_imagination'] ?? null,
                            $pci_data['citizenship'] ?? null,
                            $pci_data['digital_literacy'] ?? null,
                            $pci_data['learning_to_learn'] ?? null,
                            $pci_data['love'] ?? null,
                            $pci_data['respect'] ?? null,
                            $pci_data['responsibility'] ?? null,
                            $pci_data['unity'] ?? null,
                            $pci_data['peace'] ?? null,
                            $pci_data['integrity'] ?? null,
                            $pci_data['discipline'] ?? null,
                            $pci_data['organization'] ?? null,
                            $pci_data['tidiness'] ?? null,
                            $pci_data['projects_manipulative_skills'] ?? null,
                            $pci_data['extended_activities'] ?? null,
                            $teacher['id']
                        ]);
                    }
                }
                
                $pdo->commit();
                header("Location: update_grades.php?student_id=$student_id&success=1");
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
    <title>Manage Grades</title>
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
        .collapsible-header .toggle-icon {
            font-size: 20px;
            transition: transform 0.3s;
        }
        .collapsible-header.collapsed .toggle-icon {
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
        .term-header:hover {
            background: #e0e0e0;
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
        .assessment-header:hover {
            background: #fff3cd;
        }
        .grade-input-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }
        .grade-input-item {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid var(--navy);
        }
        .grade-input-item.has-rats {
            border-left-color: #ff9800;
        }
        .grade-input-item label {
            display: block;
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 8px;
        }
        .grade-input-item input {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 15px;
        }
        .grade-input-item input:focus {
            border-color: var(--yellow);
            outline: none;
        }
        .rats-input {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #ccc;
        }
        .rats-input label {
            color: #ff9800;
            font-size: 13px;
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
        .locked-badge {
            background: #f44336;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 10px;
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
        .class-teacher-comment-box {
            margin: 20px 0;
            padding: 15px;
            background: #fff9e6;
            border-left: 4px solid var(--yellow);
            border-radius: 6px;
        }
        .class-teacher-comment-box label {
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 10px;
            display: block;
        }
        .class-teacher-comment-box textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            resize: vertical;
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
            <h2>📝 Manage Student Grades</h2>

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
                    <a href="view_report_card.php?student_id=<?php echo $student_id; ?>" class="button" style="background: var(--yellow); color: var(--black);">
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
                            <?php foreach ($terms as $term_index => $term): ?>
                                <div class="term-header collapsible-header collapsed" onclick="toggleSection(this)">
                                    <span><?php echo $term; ?></span>
                                    <span class="toggle-icon">▼</span>
                                </div>
                                
                                <div class="collapsible-content collapsed">
                                    <?php foreach ($assessments as $assessment): ?>
                                        <?php
                                        $is_locked = areGradesLocked($student_id, $year, $term, $assessment, $pdo);
                                        $has_rats = ($assessment != 'Opener');
                                        ?>
                                        
                                        <div class="assessment-header collapsible-header collapsed" onclick="toggleSection(this)">
                                            <span>
                                                <?php echo $assessment; ?>
                                                <?php if ($is_locked): ?>
                                                    <span class="locked-badge">🔒 Locked</span>
                                                <?php endif; ?>
                                                <a href="view_report_card.php?student_id=<?php echo $student_id; ?>&year=<?php echo $year; ?>&term=<?php echo urlencode($term); ?>&assessment=<?php echo urlencode($assessment); ?>" 
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
                                                
                                                <div class="grade-input-grid">
                                                    <?php
                                                    // Determine which subjects this teacher can grade
                                                    $subjects_to_grade = $student_subjects;
                                                    $is_my_assigned_class = ($teacher['category'] == 'Class Teacher' && $student['class_level_id'] == $teacher['assigned_class_id']);
                                                    
                                                    if ($teacher['category'] == 'Subject Teacher' && !empty($teacher_subjects)) {
                                                        // Subject teachers only grade their subjects
                                                        $subjects_to_grade = array_intersect($student_subjects, $teacher_subjects);
                                                    } elseif ($teacher['category'] == 'Class Teacher' && !$is_my_assigned_class && !empty($teacher_subjects)) {
                                                        // Class teachers for OTHER classes only grade their subjects
                                                        $subjects_to_grade = array_intersect($student_subjects, $teacher_subjects);
                                                    }
                                                    // If it's class teacher's assigned class, they see ALL subjects (no filter)
                                                    
                                                    foreach ($subjects_to_grade as $subject):
                                                        $grade_data = $grades_organized[$year][$term][$assessment][$subject] ?? [];
                                                        $existing_score = $grade_data['score'] ?? '';
                                                        $existing_rats = $grade_data['rats_score'] ?? '';
                                                        $existing_comment = $grade_data['comment'] ?? '';
                                                    ?>
                                                        <div class="grade-input-item <?php echo $has_rats ? 'has-rats' : ''; ?>">
                                                            <label><?php echo htmlspecialchars($subject); ?></label>
                                                            <input type="number" 
                                                                   name="grades[<?php echo htmlspecialchars($subject); ?>]" 
                                                                   placeholder="<?php echo $has_rats ? 'Score /80' : 'Score /100'; ?>"
                                                                   min="0" 
                                                                   max="<?php echo $has_rats ? '80' : '100'; ?>"
                                                                   value="<?php echo $existing_score; ?>"
                                                                   <?php echo $is_locked ? 'disabled' : ''; ?>>
                                                            <small><?php echo $has_rats ? 'Exam score (out of 80)' : 'Total score (out of 100)'; ?></small>
                                                            
                                                            <?php if ($has_rats): ?>
                                                                <div class="rats-input">
                                                                    <label>RATs Score</label>
                                                                    <input type="number" 
                                                                           name="rats[<?php echo htmlspecialchars($subject); ?>]" 
                                                                           placeholder="RATs /20"
                                                                           min="0" 
                                                                           max="20"
                                                                           value="<?php echo $existing_rats; ?>"
                                                                           <?php echo $is_locked ? 'disabled' : ''; ?>>
                                                                    <small>RATs score (out of 20)</small>
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
                                                    <?php if ($is_my_assigned_class): ?>
                                                        <div class="class-teacher-comment-box">
                                                            <label>📝 Class Teacher's Overall Comment:</label>
                                                            <textarea name="class_teacher_comment" 
                                                                      rows="4"
                                                                      placeholder="Enter your overall assessment of this student's performance across all subjects..."
                                                                      required></textarea>
                                                            <small style="color: #666; display: block; margin-top: 5px;">
                                                                This comment will be reviewed by the Head Teacher and included in the final report card.
                                                            </small>
                                                        </div>
                                                        
                                                        <?php if ($assessment == 'End-Term'): ?>
                                                            <!-- PCI ASSESSMENT BUTTON -->
                                                            <div style="margin: 20px 0; padding: 15px; background: #e3f2fd; border-left: 4px solid #2196f3; border-radius: 6px;">
                                                                <button type="button" onclick="togglePCI()" class="button" style="background: #2196f3; width: auto;">
                                                                    📋 Fill PCI Assessment (Core Competencies, Values & Others)
                                                                </button>
                                                                <small style="display: block; margin-top: 10px; color: #666;">
                                                                    Required for End-Term reports only
                                                                </small>
                                                            </div>
                                                            
                                                            <!-- PCI FORM (Hidden by default) -->
                                                            <div id="pci-form-<?php echo $year . '_' . $term . '_' . $assessment; ?>" style="display: none; margin: 20px 0; padding: 20px; background: #f9f9f9; border-radius: 8px; border: 2px solid #2196f3;">
                                                                <h3 style="color: #2196f3; margin-bottom: 15px;">📋 PCI Assessment - End-Term</h3>
                                                                
                                                                <?php
                                                                // Get existing PCI data for this year/term
                                                                $existing_pci = $pci_organized[$year][$term] ?? [];
                                                                ?>
                                                                
                                                                <!-- CORE COMPETENCIES -->
                                                                <div style="margin-bottom: 20px;">
                                                                    <h4 style="color: var(--navy); border-bottom: 2px solid var(--yellow); padding-bottom: 5px;">Core Competencies</h4>
                                                                    <div class="grade-input-grid">
                                                                        <div class="grade-input-item">
                                                                            <label>Communication & Collaboration (CC)</label>
                                                                            <select name="pci[communication_collaboration]" <?php echo $is_locked ? 'disabled' : ''; ?>>
                                                                                <option value="">Select Grade</option>
                                                                                <?php
                                                                                $grades_options = ['EE1', 'EE2', 'ME1', 'ME2', 'AE1', 'AE2', 'BE1', 'BE2'];
                                                                                foreach ($grades_options as $grade_opt) {
                                                                                    $selected = ($existing_pci['communication_collaboration'] ?? '') == $grade_opt ? 'selected' : '';
                                                                                    echo "<option value=\"$grade_opt\" $selected>$grade_opt</option>";
                                                                                }
                                                                                ?>
                                                                            </select>
                                                                        </div>
                                                                        
                                                                        <div class="grade-input-item">
                                                                            <label>Self Efficacy (SE)</label>
                                                                            <select name="pci[self_efficacy]">
                                                                                <option value="">Select Grade</option>
                                                                                <option value="EE1">EE1</option>
                                                                                <option value="EE2">EE2</option>
                                                                                <option value="ME1">ME1</option>
                                                                                <option value="ME2">ME2</option>
                                                                                <option value="AE1">AE1</option>
                                                                                <option value="AE2">AE2</option>
                                                                                <option value="BE1">BE1</option>
                                                                                <option value="BE2">BE2</option>
                                                                            </select>
                                                                        </div>
                                                                        
                                                                        <div class="grade-input-item">
                                                                            <label>Critical Thinking & Problem Solving (CT)</label>
                                                                            <select name="pci[critical_thinking]">
                                                                                <option value="">Select Grade</option>
                                                                                <option value="EE1">EE1</option>
                                                                                <option value="EE2">EE2</option>
                                                                                <option value="ME1">ME1</option>
                                                                                <option value="ME2">ME2</option>
                                                                                <option value="AE1">AE1</option>
                                                                                <option value="AE2">AE2</option>
                                                                                <option value="BE1">BE1</option>
                                                                                <option value="BE2">BE2</option>
                                                                            </select>
                                                                        </div>
                                                                        
                                                                        <div class="grade-input-item">
                                                                            <label>Creativity & Imagination (CI)</label>
                                                                            <select name="pci[creativity_imagination]">
                                                                                <option value="">Select Grade</option>
                                                                                <option value="EE1">EE1</option>
                                                                                <option value="EE2">EE2</option>
                                                                                <option value="ME1">ME1</option>
                                                                                <option value="ME2">ME2</option>
                                                                                <option value="AE1">AE1</option>
                                                                                <option value="AE2">AE2</option>
                                                                                <option value="BE1">BE1</option>
                                                                                <option value="BE2">BE2</option>
                                                                            </select>
                                                                        </div>
                                                                        
                                                                        <div class="grade-input-item">
                                                                            <label>Citizenship (CZ)</label>
                                                                            <select name="pci[citizenship]">
                                                                                <option value="">Select Grade</option>
                                                                                <option value="EE1">EE1</option>
                                                                                <option value="EE2">EE2</option>
                                                                                <option value="ME1">ME1</option>
                                                                                <option value="ME2">ME2</option>
                                                                                <option value="AE1">AE1</option>
                                                                                <option value="AE2">AE2</option>
                                                                                <option value="BE1">BE1</option>
                                                                                <option value="BE2">BE2</option>
                                                                            </select>
                                                                        </div>
                                                                        
                                                                        <div class="grade-input-item">
                                                                            <label>Digital Literacy (DL)</label>
                                                                            <select name="pci[digital_literacy]">
                                                                                <option value="">Select Grade</option>
                                                                                <option value="EE1">EE1</option>
                                                                                <option value="EE2">EE2</option>
                                                                                <option value="ME1">ME1</option>
                                                                                <option value="ME2">ME2</option>
                                                                                <option value="AE1">AE1</option>
                                                                                <option value="AE2">AE2</option>
                                                                                <option value="BE1">BE1</option>
                                                                                <option value="BE2">BE2</option>
                                                                            </select>
                                                                        </div>
                                                                        
                                                                        <div class="grade-input-item">
                                                                            <label>Learning to Learn (L&L)</label>
                                                                            <select name="pci[learning_to_learn]">
                                                                                <option value="">Select Grade</option>
                                                                                <option value="EE1">EE1</option>
                                                                                <option value="EE2">EE2</option>
                                                                                <option value="ME1">ME1</option>
                                                                                <option value="ME2">ME2</option>
                                                                                <option value="AE1">AE1</option>
                                                                                <option value="AE2">AE2</option>
                                                                                <option value="BE1">BE1</option>
                                                                                <option value="BE2">BE2</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                
                                                                <!-- VALUES -->
                                                                <div style="margin-bottom: 20px;">
                                                                    <h4 style="color: var(--navy); border-bottom: 2px solid var(--yellow); padding-bottom: 5px;">Values</h4>
                                                                    <div class="grade-input-grid">
                                                                        <div class="grade-input-item">
                                                                            <label>Love</label>
                                                                            <select name="pci[love]">
                                                                                <option value="">Select Grade</option>
                                                                                <option value="EE1">EE1</option>
                                                                                <option value="EE2">EE2</option>
                                                                                <option value="ME1">ME1</option>
                                                                                <option value="ME2">ME2</option>
                                                                                <option value="AE1">AE1</option>
                                                                                <option value="AE2">AE2</option>
                                                                                <option value="BE1">BE1</option>
                                                                                <option value="BE2">BE2</option>
                                                                            </select>
                                                                        </div>
                                                                        
                                                                        <div class="grade-input-item">
                                                                            <label>Respect (RST)</label>
                                                                            <select name="pci[respect]">
                                                                                <option value="">Select Grade</option>
                                                                                <option value="EE1">EE1</option>
                                                                                <option value="EE2">EE2</option>
                                                                                <option value="ME1">ME1</option>
                                                                                <option value="ME2">ME2</option>
                                                                                <option value="AE1">AE1</option>
                                                                                <option value="AE2">AE2</option>
                                                                                <option value="BE1">BE1</option>
                                                                                <option value="BE2">BE2</option>
                                                                            </select>
                                                                        </div>
                                                                        
                                                                        <div class="grade-input-item">
                                                                            <label>Responsibility (RTY)</label>
                                                                            <select name="pci[responsibility]">
                                                                                <option value="">Select Grade</option>
                                                                                <option value="EE1">EE1</option>
                                                                                <option value="EE2">EE2</option>
                                                                                <option value="ME1">ME1</option>
                                                                                <option value="ME2">ME2</option>
                                                                                <option value="AE1">AE1</option>
                                                                                <option value="AE2">AE2</option>
                                                                                <option value="BE1">BE1</option>
                                                                                <option value="BE2">BE2</option>
                                                                            </select>
                                                                        </div>
                                                                        
                                                                        <div class="grade-input-item">
                                                                            <label>Unity</label>
                                                                            <select name="pci[unity]">
                                                                                <option value="">Select Grade</option>
                                                                                <option value="EE1">EE1</option>
                                                                                <option value="EE2">EE2</option>
                                                                                <option value="ME1">ME1</option>
                                                                                <option value="ME2">ME2</option>
                                                                                <option value="AE1">AE1</option>
                                                                                <option value="AE2">AE2</option>
                                                                                <option value="BE1">BE1</option>
                                                                                <option value="BE2">BE2</option>
                                                                            </select>
                                                                        </div>
                                                                        
                                                                        <div class="grade-input-item">
                                                                            <label>Peace (PC)</label>
                                                                            <select name="pci[peace]">
                                                                                <option value="">Select Grade</option>
                                                                                <option value="EE1">EE1</option>
                                                                                <option value="EE2">EE2</option>
                                                                                <option value="ME1">ME1</option>
                                                                                <option value="ME2">ME2</option>
                                                                                <option value="AE1">AE1</option>
                                                                                <option value="AE2">AE2</option>
                                                                                <option value="BE1">BE1</option>
                                                                                <option value="BE2">BE2</option>
                                                                            </select>
                                                                        </div>
                                                                        
                                                                        <div class="grade-input-item">
                                                                            <label>Integrity (ITY)</label>
                                                                            <select name="pci[integrity]">
                                                                                <option value="">Select Grade</option>
                                                                                <option value="EE1">EE1</option>
                                                                                <option value="EE2">EE2</option>
                                                                                <option value="ME1">ME1</option>
                                                                                <option value="ME2">ME2</option>
                                                                                <option value="AE1">AE1</option>
                                                                                <option value="AE2">AE2</option>
                                                                                <option value="BE1">BE1</option>
                                                                                <option value="BE2">BE2</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                
                                                                <!-- DISCIPLINE & OTHERS -->
                                                                <div style="margin-bottom: 20px;">
                                                                    <h4 style="color: var(--navy); border-bottom: 2px solid var(--yellow); padding-bottom: 5px;">Discipline & Others</h4>
                                                                    <div class="grade-input-grid">
                                                                        <div class="grade-input-item">
                                                                            <label>Discipline (DNE)</label>
                                                                            <select name="pci[discipline]">
                                                                                <option value="">Select Grade</option>
                                                                                <option value="EE1">EE1</option>
                                                                                <option value="EE2">EE2</option>
                                                                                <option value="ME1">ME1</option>
                                                                                <option value="ME2">ME2</option>
                                                                                <option value="AE1">AE1</option>
                                                                                <option value="AE2">AE2</option>
                                                                                <option value="BE1">BE1</option>
                                                                                <option value="BE2">BE2</option>
                                                                            </select>
                                                                        </div>
                                                                        
                                                                        <div class="grade-input-item">
                                                                            <label>Organization (ORG)</label>
                                                                            <select name="pci[organization]">
                                                                                <option value="">Select Grade</option>
                                                                                <option value="EE1">EE1</option>
                                                                                <option value="EE2">EE2</option>
                                                                                <option value="ME1">ME1</option>
                                                                                <option value="ME2">ME2</option>
                                                                                <option value="AE1">AE1</option>
                                                                                <option value="AE2">AE2</option>
                                                                                <option value="BE1">BE1</option>
                                                                                <option value="BE2">BE2</option>
                                                                            </select>
                                                                        </div>
                                                                        
                                                                        <div class="grade-input-item">
                                                                            <label>Tidiness (TID)</label>
                                                                            <select name="pci[tidiness]">
                                                                                <option value="">Select Grade</option>
                                                                                <option value="EE1">EE1</option>
                                                                                <option value="EE2">EE2</option>
                                                                                <option value="ME1">ME1</option>
                                                                                <option value="ME2">ME2</option>
                                                                                <option value="AE1">AE1</option>
                                                                                <option value="AE2">AE2</option>
                                                                                <option value="BE1">BE1</option>
                                                                                <option value="BE2">BE2</option>
                                                                            </select>
                                                                        </div>
                                                                        
                                                                        <div class="grade-input-item">
                                                                            <label>Projects & Manipulative Skills (P&MS)</label>
                                                                            <select name="pci[projects_manipulative_skills]">
                                                                                <option value="">Select Grade</option>
                                                                                <option value="EE1">EE1</option>
                                                                                <option value="EE2">EE2</option>
                                                                                <option value="ME1">ME1</option>
                                                                                <option value="ME2">ME2</option>
                                                                                <option value="AE1">AE1</option>
                                                                                <option value="AE2">AE2</option>
                                                                                <option value="BE1">BE1</option>
                                                                                <option value="BE2">BE2</option>
                                                                            </select>
                                                                        </div>
                                                                        
                                                                        <div class="grade-input-item">
                                                                            <label>Extended Activities (EA)</label>
                                                                            <select name="pci[extended_activities]">
                                                                                <option value="">Select Grade</option>
                                                                                <option value="EE1">EE1</option>
                                                                                <option value="EE2">EE2</option>
                                                                                <option value="ME1">ME1</option>
                                                                                <option value="ME2">ME2</option>
                                                                                <option value="AE1">AE1</option>
                                                                                <option value="AE2">AE2</option>
                                                                                <option value="BE1">BE1</option>
                                                                                <option value="BE2">BE2</option>
                                                                            </select>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    
                                                    <div class="lock-checkbox">
                                                        <input type="checkbox" name="lock_submission" value="1" id="lock_<?php echo $year . '_' . $term . '_' . $assessment; ?>">
                                                        <label for="lock_<?php echo $year . '_' . $term . '_' . $assessment; ?>">
                                                            <?php if ($is_my_assigned_class): ?>
                                                                🔒 Lock and Submit to Head Teacher for Review
                                                            <?php else: ?>
                                                                🔒 Lock these grades after submission
                                                            <?php endif; ?>
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
        <?php if ($teacher['category'] == 'Class Teacher'): ?>
            const comment = form.querySelector('textarea[name="class_teacher_comment"]');
            if (!comment || !comment.value.trim()) {
                alert('⚠️ Please enter a class teacher comment before submitting to the Head Teacher.');
                return false;
            }
            return confirm('⚠️ WARNING: You are about to SUBMIT these grades to the Head Teacher for review.\n\nOnce submitted:\n• You cannot edit them until reviewed\n• The Head Teacher will review and release to parents\n\nAre you absolutely sure?');
        <?php else: ?>
            return confirm('⚠️ WARNING: You are about to LOCK these grades.\n\nOnce locked:\n• You cannot edit them\n• Only admin can unlock them\n\nAre you absolutely sure?');
        <?php endif; ?>
    }
    return true;
}

function togglePCI() {
    // Find all PCI forms and toggle the one that's in view
    const pciForms = document.querySelectorAll('[id^="pci-form-"]');
    pciForms.forEach(form => {
        const parent = form.closest('.collapsible-content');
        if (parent && !parent.classList.contains('collapsed')) {
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
    });
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