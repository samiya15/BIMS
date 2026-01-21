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
$teacher_stmt = $pdo->prepare("SELECT id, category FROM teachers WHERE user_id = ?");
$teacher_stmt->execute([$_SESSION['user_id']]);
$teacher = $teacher_stmt->fetch(PDO::FETCH_ASSOC);

if (!$teacher) {
    die("Teacher profile not found");
}

/* ---------- GET TEACHER'S SUBJECTS ---------- */
$teacher_subjects_stmt = $pdo->prepare("
    SELECT DISTINCT subject_name 
    FROM teacher_subjects 
    WHERE teacher_id = ?
");
$teacher_subjects_stmt->execute([$teacher['id']]);
$teacher_subjects = $teacher_subjects_stmt->fetchAll(PDO::FETCH_COLUMN);

/* ---------- GET STUDENT INFO ---------- */
$student_stmt = $pdo->prepare("
    SELECT 
        s.id,
        s.admission_number,
        s.first_name,
        s.last_name,
        s.year_of_enrollment,
        cl.name as class_name,
        ct.id as curriculum_type_id,
        ct.name as curriculum_name
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
$student_subjects_stmt = $pdo->prepare("
    SELECT subject_name FROM student_subjects WHERE student_id = ? ORDER BY subject_name
");
$student_subjects_stmt->execute([$student_id]);
$student_subjects = $student_subjects_stmt->fetchAll(PDO::FETCH_COLUMN);

/* ---------- GET CBE/8-4-4 GRADING SCALE ---------- */
$grading_scale = [];
if ($student['curriculum_name'] == 'CBE' || $student['curriculum_name'] == '8-4-4') {
    $grading_scale = $pdo->query("
        SELECT grade_code, grade_name, points 
        FROM cbc_grading_scale 
        ORDER BY display_order
    ")->fetchAll(PDO::FETCH_ASSOC);
}

/* ---------- DETERMINE AVAILABLE TERMS ---------- */
function getAvailableTerms($curriculum_name, $year_of_enrollment, $teacher_category, $pdo) {
    $current_year = (int)date('Y');
    $current_month = (int)date('n');
    
    $available_terms = [];
    
    if ($curriculum_name == 'CBE' || $curriculum_name == '8-4-4') {
        $last_completed_year = $current_year;
        $last_completed_term = 0;
        
        if ($current_month >= 1 && $current_month <= 3) {
            $last_completed_year = $current_year - 1;
            $last_completed_term = 3;
        } elseif ($current_month == 4) {
            $last_completed_year = $current_year;
            $last_completed_term = 1;
        } elseif ($current_month >= 5 && $current_month <= 7) {
            $last_completed_year = $current_year;
            $last_completed_term = 1;
        } elseif ($current_month == 8) {
            $last_completed_year = $current_year;
            $last_completed_term = 2;
        } elseif ($current_month >= 9 && $current_month <= 10) {
            $last_completed_year = $current_year;
            $last_completed_term = 2;
        } elseif ($current_month >= 11 || $current_month == 12) {
            $last_completed_year = $current_year;
            $last_completed_term = 3;
        }
        
        // If Subject Teacher, only show current ongoing term
        if ($teacher_category == 'Subject Teacher') {
            // Only current year's current term
            if ($last_completed_term > 0) {
                $available_terms[] = [
                    'year' => $last_completed_year,
                    'term' => "Term $last_completed_term",
                    'label' => "$last_completed_year - Term $last_completed_term (Current)"
                ];
            }
        } else {
            // Class Teacher: Show all terms (check permissions from admin)
            for ($year = $year_of_enrollment; $year <= $last_completed_year; $year++) {
                if ($year < $last_completed_year) {
                    for ($term = 1; $term <= 3; $term++) {
                        $available_terms[] = [
                            'year' => $year,
                            'term' => "Term $term",
                            'label' => "$year - Term $term"
                        ];
                    }
                } else {
                    for ($term = 1; $term <= $last_completed_term; $term++) {
                        $available_terms[] = [
                            'year' => $year,
                            'term' => "Term $term",
                            'label' => "$year - Term $term"
                        ];
                    }
                }
            }
        }
    }
    
    return array_reverse($available_terms);
}

$available_terms = getAvailableTerms($student['curriculum_name'], $student['year_of_enrollment'], $teacher['category'], $pdo);

/* ---------- CHECK IF TERM/ASSESSMENT IS ENABLED ---------- */
function isUploadEnabled($year, $term, $assessment, $curriculum, $pdo) {
    $check = $pdo->prepare("
        SELECT is_enabled FROM grade_upload_permissions 
        WHERE academic_year = ? AND term = ? AND assessment_type = ? AND curriculum_name = ?
    ");
    $check->execute([$year, $term, $assessment, $curriculum]);
    $result = $check->fetch();
    return $result ? (bool)$result['is_enabled'] : false;
}

/* ---------- CHECK IF GRADES ARE LOCKED ---------- */
function areGradesLocked($student_id, $year, $term, $assessment, $pdo) {
    $check = $pdo->prepare("
        SELECT is_locked FROM grade_submissions 
        WHERE student_id = ? AND academic_year = ? AND term = ? AND assessment_type = ?
    ");
    $check->execute([$student_id, $year, $term, $assessment]);
    $result = $check->fetch();
    return $result ? (bool)$result['is_locked'] : false;
}

/* ---------- GET EXISTING GRADES ---------- */
$grades_stmt = $pdo->prepare("
    SELECT subject_name, grade, assessment_type, term, academic_year, is_locked
    FROM grades
    WHERE student_id = ?
");
$grades_stmt->execute([$student_id]);
$existing_grades_raw = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);

$grades_map = [];
foreach ($existing_grades_raw as $g) {
    $key = $g['academic_year'] . '_' . $g['term'] . '_' . $g['assessment_type'] . '_' . $g['subject_name'];
    $grades_map[$key] = [
        'grade' => $g['grade'],
        'is_locked' => $g['is_locked']
    ];
}

/* ---------- HANDLE GRADE SUBMISSION ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $selected_year = (int)$_POST['academic_year'];
    $selected_term = $_POST['term'];
    $selected_assessment = $_POST['assessment_type'];
    $grades_data = $_POST['grades'] ?? [];
    $lock_submission = isset($_POST['lock_submission']) ? 1 : 0;
    
    // Check if grades are already locked
    if (areGradesLocked($student_id, $selected_year, $selected_term, $selected_assessment, $pdo)) {
        $error = "These grades are locked and cannot be edited. Please request admin to unlock them.";
    } 
    // Check if class teacher or if upload is enabled
    elseif ($teacher['category'] != 'Class Teacher' || 
            isUploadEnabled($selected_year, $selected_term, $selected_assessment, $student['curriculum_name'], $pdo) ||
            ($selected_year == (int)date('Y'))) { // Current year always allowed
        
        try {
            $pdo->beginTransaction();
            
            foreach ($grades_data as $subject => $grade) {
                if (empty($grade)) continue;
                
                // Get grade points
                $grade_points = null;
                if (!empty($grading_scale)) {
                    $points_stmt = $pdo->prepare("SELECT points FROM cbc_grading_scale WHERE grade_code = ?");
                    $points_stmt->execute([$grade]);
                    $points_row = $points_stmt->fetch();
                    $grade_points = $points_row ? $points_row['points'] : null;
                }
                
                $check_stmt = $pdo->prepare("
                    SELECT id FROM grades 
                    WHERE student_id = ? AND academic_year = ? AND term = ? 
                    AND assessment_type = ? AND subject_name = ?
                ");
                $check_stmt->execute([$student_id, $selected_year, $selected_term, $selected_assessment, $subject]);
                $existing = $check_stmt->fetch();
                
                if ($existing) {
                    $update_stmt = $pdo->prepare("
                        UPDATE grades 
                        SET grade = ?, grade_points = ?, teacher_id = ?, is_locked = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $update_stmt->execute([$grade, $grade_points, $teacher['id'], $lock_submission, $existing['id']]);
                } else {
                    $insert_stmt = $pdo->prepare("
                        INSERT INTO grades (student_id, subject_name, grade, grade_points, term, assessment_type, academic_year, teacher_id, is_locked)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $insert_stmt->execute([
                        $student_id, $subject, $grade, $grade_points, 
                        $selected_term, $selected_assessment, $selected_year, $teacher['id'], $lock_submission
                    ]);
                }
            }
            
            // Record submission if locked
            if ($lock_submission) {
                $check_sub = $pdo->prepare("
                    SELECT id FROM grade_submissions 
                    WHERE student_id = ? AND academic_year = ? AND term = ? AND assessment_type = ?
                ");
                $check_sub->execute([$student_id, $selected_year, $selected_term, $selected_assessment]);
                $existing_sub = $check_sub->fetch();
                
                if ($existing_sub) {
                    $update_sub = $pdo->prepare("
                        UPDATE grade_submissions 
                        SET is_locked = 1, teacher_id = ?, submitted_at = NOW()
                        WHERE id = ?
                    ");
                    $update_sub->execute([$teacher['id'], $existing_sub['id']]);
                } else {
                    $insert_sub = $pdo->prepare("
                        INSERT INTO grade_submissions (student_id, teacher_id, academic_year, term, assessment_type, is_locked)
                        VALUES (?, ?, ?, ?, ?, 1)
                    ");
                    $insert_sub->execute([$student_id, $teacher['id'], $selected_year, $selected_term, $selected_assessment]);
                }
            }
            
            $pdo->commit();
            $success_msg = $lock_submission ? "Grades saved and locked successfully!" : "Grades saved successfully!";
            header("Location: update_grades.php?student_id=$student_id&success=" . urlencode($success_msg));
            exit;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error saving grades: " . $e->getMessage();
        }
    } else {
        $error = "You do not have permission to upload grades for this term. Contact admin.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Grades</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/teacher.css">
    <style>
        .grade-input-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .grade-input-item {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid var(--navy);
        }
        .grade-input-item label {
            display: block;
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 8px;
        }
        .grade-input-item select {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 15px;
        }
        .grade-input-item select:focus {
            border-color: var(--yellow);
            outline: none;
        }
        .student-info-banner {
            background: linear-gradient(135deg, var(--navy) 0%, #1a3a52 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            }
.student-info-banner h3 {
color: var(–yellow);
margin-bottom: 10px;
}
.assessment-selector {
background: #e3f2fd;
padding: 15px;
border-left: 4px solid #2196f3;
border-radius: 4px;
margin: 20px 0;
}
.assessment-selector h4 {
color: var(–navy);
margin-bottom: 10px;
}
.grade-legend {
background: #fff9e6;
padding: 15px;
border-left: 4px solid var(–yellow);
border-radius: 4px;
margin: 20px 0;
}
.grade-legend h4 {
color: var(–navy);
margin-bottom: 10px;
}
.grade-legend-grid {
display: grid;
grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
gap: 8px;
font-size: 13px;
}
.lock-warning {
background: #ffebee;
border-left: 4px solid #f44336;
padding: 15px;
border-radius: 4px;
margin: 20px 0;
color: #c62828;
}
.lock-checkbox {
display: flex;
align-items: center;
background: #fff3cd;
padding: 15px;
border-radius: 6px;
margin: 20px 0;
border-left: 4px solid #ffc107;
}
.lock-checkbox input {
width: auto;
margin-right: 10px;
}
.lock-checkbox label {
font-weight: 600;
color: #856404;
}
</style>
</head>
<body><div class="sidebar">
    <h2>BIMS Teacher</h2>
    <a href="../teacher_dashboard.php">Dashboard</a>
    <a href="my_profile.php">My Profile</a>
    <a href="manage_grades.php">Manage Grades</a>
    <a href="../logout.php">Logout</a>
</div><div class="main-content">
    <div class="container">
        <div class="card">
            <h2>Update Student Grades</h2>
            <div class="student-info-banner">
            <h3><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h3>
            <p><strong>Admission Number:</strong> <?php echo htmlspecialchars($student['admission_number']); ?></p>
            <p><strong>Class:</strong> <?php echo htmlspecialchars($student['curriculum_name'] . ' - ' . $student['class_name']); ?></p>
            <p><strong>Enrolled Since:</strong> <?php echo htmlspecialchars($student['year_of_enrollment']); ?></p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert-success">✅ <?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (empty($available_terms)): ?>
            <div class="no-terms-message">
                <h3>⚠️ No Terms Available for Grading</h3>
                <p>No terms available for grading at this time.</p>
            </div>
        <?php elseif (empty($student_subjects)): ?>
            <div class="no-terms-message">
                <h3>⚠️ No Subjects Assigned</h3>
                <p>This student has no subjects assigned yet.</p>
            </div>
        <?php else: ?>
            <?php if (!empty($grading_scale)): ?>
                <div class="grade-legend">
                    <h4>📊 Grading Scale (<?php echo $student['curriculum_name']; ?>)</h4>
                    <div class="grade-legend-grid">
                        <?php foreach ($grading_scale as $g): ?>
                            <div><strong><?php echo $g['grade_code']; ?>:</strong> <?php echo $g['grade_name']; ?> (<?php echo $g['points']; ?> pts)</div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" id="gradesForm">
                <label>Select Term</label>
                <input type="hidden" name="academic_year" id="academic_year">
                <input type="hidden" name="term" id="term_select">
                
                <select id="term_combined" onchange="loadTermDetails()" required>
                    <option value="">Select Term</option>
                    <?php foreach ($available_terms as $term): ?>
                        <option value="<?php echo $term['year'] . '|' . $term['term']; ?>">
                            <?php echo htmlspecialchars($term['label']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div id="assessmentSection" class="assessment-selector" style="display: none;">
                    <h4>Select Assessment Type</h4>
                    <select name="assessment_type" id="assessment_type" required onchange="loadGradesForAssessment()">
                        <option value="">Select Assessment</option>
                        <option value="Opener">Opener</option>
                        <option value="Mid-Term">Mid-Term</option>
                        <option value="End-Term">End-Term</option>
                    </select>
                </div>

                <div id="gradesSection" style="display: none;">
                    <div id="lockedMessage" class="lock-warning" style="display: none;">
                        🔒 These grades are locked and cannot be edited. Please contact admin to unlock them.
                    </div>

                    <h3 style="color: var(--navy); margin-top: 30px;">Enter Grades</h3>
                    <div class="grade-input-grid" id="gradesGrid"></div>

                    <div id="lockCheckboxSection" class="lock-checkbox" style="display: none;">
                        <input type="checkbox" name="lock_submission" id="lock_submission" value="1">
                        <label for="lock_submission">
                            🔒 Lock these grades after submission (Cannot be edited without admin approval)
                        </label>
                    </div>

                    <button type="submit" id="submitBtn" style="margin-top: 30px;">💾 Save Grades</button>
                </div>
            </form>
        <?php endif; ?>

        <a href="../teacher_dashboard.php" class="button button-yellow" style="margin-top: 20px;">← Back to Dashboard</a>
    </div>
</div>
</div><script>
const studentSubjects = <?php echo json_encode($student_subjects); ?>;
const teacherSubjects = <?php echo json_encode($teacher_subjects); ?>;
const teacherCategory = "<?php echo $teacher['category']; ?>";
const existingGrades = <?php echo json_encode($grades_map); ?>;
const gradingScale = <?php echo json_encode($grading_scale); ?>;
const curriculum = "<?php echo $student['curriculum_name']; ?>";

function loadTermDetails() {
    const combined = document.getElementById('term_combined').value;
    if (!combined) {
        document.getElementById('assessmentSection').style.display = 'none';
        document.getElementById('gradesSection').style.display = 'none';
        return;
    }
    
    const [year, term] = combined.split('|');
    document.getElementById('academic_year').value = year;
    document.getElementById('term_select').value = term;
    
    document.getElementById('assessmentSection').style.display = 'block';
    document.getElementById('gradesSection').style.display = 'none';
}

function loadGradesForAssessment() {
    const year = document.getElementById('academic_year').value;
    const term = document.getElementById('term_select').value;
    const assessment = document.getElementById('assessment_type').value;
    
    if (!year || !term || !assessment) {
        document.getElementById('gradesSection').style.display = 'none';
        return;
    }
    
    const gradesGrid = document.getElementById('gradesGrid');
    const lockedMessage = document.getElementById('lockedMessage');
    const submitBtn = document.getElementById('submitBtn');
    const lockCheckbox = document.getElementById('lockCheckboxSection');
    
    gradesGrid.innerHTML = '';
    
    let subjectsToGrade = studentSubjects;
    if (teacherCategory === 'Subject Teacher' && teacherSubjects.length > 0) {
        subjectsToGrade = studentSubjects.filter(s => teacherSubjects.includes(s));
    }
    
    if (subjectsToGrade.length === 0) {
        gradesGrid.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: #999;">You are not assigned to teach any of this student\'s subjects.</p>';
        document.getElementById('gradesSection').style.display = 'block';
        return;
    }
    
    let isLocked = false;
    
    subjectsToGrade.forEach(subject => {
        const key = year + '_' + term + '_' + assessment + '_' + subject;
        const gradeData = existingGrades[key] || {};
        const existingGrade = gradeData.grade || '';
        
        if (gradeData.is_locked) {
            isLocked = true;
        }
        
        const div = document.createElement('div');
        div.className = 'grade-input-item';
        
        let options = '<option value="">Select Grade</option>';
        gradingScale.forEach(g => {
            const selected = existingGrade == g.grade_code ? 'selected' : '';
            options += `<option value="${g.grade_code}" ${selected}>${g.grade_code} - ${g.grade_name} (${g.points} pts)</option>`;
        });
        
        div.innerHTML = `
            <label>${subject}</label>
            <select name="grades[${subject}]" ${isLocked ? 'disabled' : 'required'}>
                ${options}
            </select>
        `;
        
        gradesGrid.appendChild(div);
    });
    
    if (isLocked) {
        lockedMessage.style.display = 'block';
        submitBtn.style.display = 'none';
        lockCheckbox.style.display = 'none';
    } else {
        lockedMessage.style.display = 'none';
        submitBtn.style.display = 'block';
        lockCheckbox.style.display = 'flex';
    }
    
    document.getElementById('gradesSection').style.display = 'block';
}

// Confirmation before submission
document.getElementById('gradesForm')?.addEventListener('submit', function(e) {
    const lockCheckbox = document.getElementById('lock_submission');
    if (lockCheckbox && lockCheckbox.checked) {
        if (!confirm('⚠️ WARNING: You are about to LOCK these grades. Once locked, you cannot edit them without admin approval. Are you absolutely sure?')) {
            e.preventDefault();
        }
    }
});
</script></body>
</html>