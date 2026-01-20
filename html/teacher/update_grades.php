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

/* ---------- GET CBE GRADING SCALE ---------- */
$cbc_grades = [];
if ($student['curriculum_name'] == 'CBE') {
    $cbc_grades = $pdo->query("
        SELECT grade_code, grade_name, points 
        FROM cbc_grading_scale 
        ORDER BY display_order
    ")->fetchAll(PDO::FETCH_ASSOC);
}

/* ---------- DETERMINE AVAILABLE TERMS ---------- */
function getAvailableTerms($curriculum_name, $year_of_enrollment) {
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
    
    return array_reverse($available_terms);
}

$available_terms = getAvailableTerms($student['curriculum_name'], $student['year_of_enrollment']);

/* ---------- GET EXISTING GRADES ---------- */
$grades_stmt = $pdo->prepare("
    SELECT subject_name, grade, assessment_type, term, academic_year
    FROM grades
    WHERE student_id = ?
");
$grades_stmt->execute([$student_id]);
$existing_grades_raw = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize: year_term_assessment_subject => grade
$grades_map = [];
foreach ($existing_grades_raw as $g) {
    $key = $g['academic_year'] . '_' . $g['term'] . '_' . ($g['assessment_type'] ?? 'final') . '_' . $g['subject_name'];
    $grades_map[$key] = $g['grade'];
}

/* ---------- HANDLE GRADE SUBMISSION ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $selected_year = (int)$_POST['academic_year'];
    $selected_term = $_POST['term'];
    $selected_assessment = $_POST['assessment_type'] ?? null;
    $grades_data = $_POST['grades'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        foreach ($grades_data as $subject => $grade) {
            if (empty($grade)) continue;
            
            // Get grade points for CBE
            $grade_points = null;
            if ($student['curriculum_name'] == 'CBE') {
                $points_stmt = $pdo->prepare("SELECT points FROM cbc_grading_scale WHERE grade_code = ?");
                $points_stmt->execute([$grade]);
                $points_row = $points_stmt->fetch();
                $grade_points = $points_row ? $points_row['points'] : null;
            }
            
            // Check if grade exists
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
                    SET grade = ?, grade_points = ?, teacher_id = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $update_stmt->execute([$grade, $grade_points, $teacher['id'], $existing['id']]);
            } else {
                $insert_stmt = $pdo->prepare("
                    INSERT INTO grades (student_id, subject_name, grade, grade_points, term, assessment_type, academic_year, teacher_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insert_stmt->execute([
                    $student_id, $subject, $grade, $grade_points, 
                    $selected_term, $selected_assessment, $selected_year, $teacher['id']
                ]);
            }
        }
        
        $pdo->commit();
        header("Location: update_grades.php?student_id=$student_id&success=1");
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error saving grades: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Grades - CBE</title>
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
        .grade-input-item select,
        .grade-input-item input {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 15px;
        }
        .grade-input-item select:focus,
        .grade-input-item input:focus {
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
            color: var(--yellow);
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
            color: var(--navy);
            margin-bottom: 10px;
        }
        .cbc-badge {
            display: inline-block;
            background: #4caf50;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }
        .grade-legend {
            background: #fff9e6;
            padding: 15px;
            border-left: 4px solid var(--yellow);
            border-radius: 4px;
            margin: 20px 0;
        }
        .grade-legend h4 {
            color: var(--navy);
            margin-bottom: 10px;
        }
        .grade-legend-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 8px;
            font-size: 13px;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>BIMS Teacher</h2>
    <a href="../teacher_dashboard.php">Dashboard</a>
    <a href="my_profile.php">My Profile</a>
    <a href="manage_grades.php">Manage Grades</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main-content">
    <div class="container">
        <div class="card">
            <h2>Update Student Grades <?php if ($student['curriculum_name'] == 'CBE'): ?><span class="cbc-badge">CBE</span><?php endif; ?></h2>

            <div class="student-info-banner">
                <h3><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h3>
                <p><strong>Admission Number:</strong> <?php echo htmlspecialchars($student['admission_number']); ?></p>
                <p><strong>Class:</strong> <?php echo htmlspecialchars($student['curriculum_name'] . ' - ' . $student['class_name']); ?></p>
                <p><strong>Enrolled Since:</strong> <?php echo htmlspecialchars($student['year_of_enrollment']); ?></p>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert-success">✅ Grades saved successfully.</div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (empty($available_terms)): ?>
                <div class="no-terms-message">
                    <h3>⚠️ No Terms Available for Grading</h3>
                    <p>No completed terms found for grading.</p>
                </div>
            <?php elseif (empty($student_subjects)): ?>
                <div class="no-terms-message">
                    <h3>⚠️ No Subjects Assigned</h3>
                    <p>This student has no subjects assigned yet.</p>
                </div>
            <?php else: ?>
                <?php if ($student['curriculum_name'] == 'CBE' && !empty($cbc_grades)): ?>
                    <div class="grade-legend">
                        <h4>📊 CBE Grading Scale</h4>
                        <div class="grade-legend-grid">
                            <?php foreach ($cbc_grades as $g): ?>
                                <div><strong><?php echo $g['grade_code']; ?>:</strong> <?php echo $g['grade_name']; ?> (<?php echo $g['points']; ?> pts)</div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <label>Select Term</label>
                    <select name="academic_year" id="academic_year" required style="display: none;">
                        <option value="">Year</option>
                    </select>
                    <select name="term" id="term_select" required style="display: none;">
                        <option value="">Term</option>
                    </select>
                    
                    <select id="term_combined" onchange="loadTermDetails()" required>
                        <option value="">Select Term</option>
                        <?php foreach ($available_terms as $term): ?>
                            <option value="<?php echo $term['year'] . '|' . $term['term']; ?>">
                                <?php echo htmlspecialchars($term['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if ($student['curriculum_name'] == 'CBE'): ?>
                        <div id="assessmentSection" class="assessment-selector" style="display: none;">
                            <h4>Select Assessment Type</h4>
                            <select name="assessment_type" id="assessment_type" required onchange="loadGradesForAssessment()">
                                <option value="">Select Assessment</option>
                                <option value="Opener">Opener</option>
                                <option value="Mid-Term">Mid-Term</option>
                                <option value="End-Term">End-Term</option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div id="gradesSection" style="display: none;">
                        <h3 style="color: var(--navy); margin-top: 30px;">Enter Grades</h3>
                        <div class="grade-input-grid" id="gradesGrid">
                            <!-- Grades will be loaded here -->
                        </div>

                        <button type="submit" style="margin-top: 30px;">💾 Save Grades</button>
                    </div>
                </form>
            <?php endif; ?>

            <a href="../teacher_dashboard.php" class="button button-yellow" style="margin-top: 20px;">← Back to Dashboard</a>
        </div>
    </div>
</div>

<script>
const studentSubjects = <?php echo json_encode($student_subjects); ?>;
const teacherSubjects = <?php echo json_encode($teacher_subjects); ?>;
const teacherCategory = "<?php echo $teacher['category']; ?>";
const existingGrades = <?php echo json_encode($grades_map); ?>;
const isCBC = <?php echo $student['curriculum_name'] == 'CBE' ? 'true' : 'false'; ?>;
const cbcGrades = <?php echo json_encode($cbc_grades); ?>;

function loadTermDetails() {
    const combined = document.getElementById('term_combined').value;
    if (!combined) {
        document.getElementById('assessmentSection')?.style.setProperty('display', 'none');
        document.getElementById('gradesSection').style.display = 'none';
        return;
    }
    
    const [year, term] = combined.split('|');
    document.getElementById('academic_year').value = year;
    document.getElementById('term_select').value = term;
    
    if (isCBC) {
        document.getElementById('assessmentSection').style.display = 'block';
        document.getElementById('gradesSection').style.display = 'none';
    } else {
        loadGradesForAssessment();
    }
}

function loadGradesForAssessment() {
    const year = document.getElementById('academic_year').value;
    const term = document.getElementById('term_select').value;
    const assessment = isCBC ? document.getElementById('assessment_type').value : 'final';
    
    if (!year || !term || (isCBC && !assessment)) {
        document.getElementById('gradesSection').style.display = 'none';
        return;
    }
    
    const gradesGrid = document.getElementById('gradesGrid');
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
    
    subjectsToGrade.forEach(subject => {
        const key = year + '_' + term + '_' + assessment + '_' + subject;
        const existingGrade = existingGrades[key] || '';
        
        const div = document.createElement('div');
        div.className = 'grade-input-item';
        
        if (isCBC) {
            // Dropdown for CBE
            let options = '<option value="">Select Grade</option>';
            cbcGrades.forEach(g => {
                const selected = existingGrade == g.grade_code ? 'selected' : '';
                options += `<option value="${g.grade_code}" ${selected}>${g.grade_code} - ${g.grade_name} (${g.points} pts)</option>`;
            });
            
            div.innerHTML = `
                <label>${subject}</label>
                <select name="grades[${subject}]" required>
                    ${options}
                </select>
            `;
        } else {
            // Text input for other curricula
            div.innerHTML = `
                <label>${subject}</label>
                <input type="text" 
                       name="grades[${subject}]" 
                       placeholder="e.g., A, B+, 85"
                       value="${existingGrade}"
                       required>
            `;
        }
        
        gradesGrid.appendChild(div);
    });
    
    document.getElementById('gradesSection').style.display = 'block';
}
</script>

</body>
</html>