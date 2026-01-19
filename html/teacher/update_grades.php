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

/* ---------- DETERMINE AVAILABLE TERMS ---------- */
/* ---------- DETERMINE AVAILABLE TERMS ---------- */
function getAvailableTerms($curriculum_name, $year_of_enrollment) {
    $current_year = (int)date('Y');
    $current_month = (int)date('n');
    
    $available_terms = [];
    
    if ($curriculum_name == 'CBE' || $curriculum_name == '8-4-4') {
        // Academic year: January to November
        // Term 1: Jan-Apr, Term 2: May-Aug, Term 3: Sep-Nov, Dec is break
        
        // Determine which terms are COMPLETED (not current)
        $last_completed_year = $current_year;
        $last_completed_term = 0;
        
        if ($current_month >= 1 && $current_month <= 3) {
            // Jan-Mar: Currently in Term 1, last completed is Term 3 of previous year
            $last_completed_year = $current_year - 1;
            $last_completed_term = 3;
        } elseif ($current_month == 4) {
            // April: Term 1 just ended, can grade it
            $last_completed_year = $current_year;
            $last_completed_term = 1;
        } elseif ($current_month >= 5 && $current_month <= 7) {
            // May-Jul: Currently in Term 2, Term 1 is completed
            $last_completed_year = $current_year;
            $last_completed_term = 1;
        } elseif ($current_month == 8) {
            // August: Term 2 just ended
            $last_completed_year = $current_year;
            $last_completed_term = 2;
        } elseif ($current_month >= 9 && $current_month <= 10) {
            // Sep-Oct: Currently in Term 3, Terms 1 & 2 completed
            $last_completed_year = $current_year;
            $last_completed_term = 2;
        } elseif ($current_month == 11) {
            // November: Term 3 just ended
            $last_completed_year = $current_year;
            $last_completed_term = 3;
        } elseif ($current_month == 12) {
            // December: All terms of current year completed
            $last_completed_year = $current_year;
            $last_completed_term = 3;
        }
        
        // Add all completed terms from enrollment year to last completed
        for ($year = $year_of_enrollment; $year <= $last_completed_year; $year++) {
            if ($year < $last_completed_year) {
                // All past years - add all 3 terms
                for ($term = 1; $term <= 3; $term++) {
                    $available_terms[] = [
                        'year' => $year,
                        'term' => "Term $term",
                        'label' => "$year - Term $term"
                    ];
                }
            } else {
                // Last completed year - only add up to last completed term
                for ($term = 1; $term <= $last_completed_term; $term++) {
                    $available_terms[] = [
                        'year' => $year,
                        'term' => "Term $term",
                        'label' => "$year - Term $term"
                    ];
                }
            }
        }
        
    } elseif ($curriculum_name == 'IGCSE') {
        // Academic year: September to August (next year)
        // Term 1: Sep-Dec, Term 2: Jan-Apr, Term 3: May-Aug
        
        $last_completed_academic_year = $current_year;
        $last_completed_term = 0;
        
        if ($current_month >= 9 && $current_month <= 11) {
            // Sep-Nov: Currently in Term 1 of new academic year
            // Last completed is Term 3 of previous academic year
            $last_completed_academic_year = $current_year - 1;
            $last_completed_term = 3;
        } elseif ($current_month == 12) {
            // December: Term 1 of current academic year just ended
            $last_completed_academic_year = $current_year;
            $last_completed_term = 1;
        } elseif ($current_month >= 1 && $current_month <= 3) {
            // Jan-Mar: Currently in Term 2 (academic year started last Sep)
            $last_completed_academic_year = $current_year - 1;
            $last_completed_term = 1;
        } elseif ($current_month == 4) {
            // April: Term 2 just ended
            $last_completed_academic_year = $current_year - 1;
            $last_completed_term = 2;
        } elseif ($current_month >= 5 && $current_month <= 7) {
            // May-Jul: Currently in Term 3
            $last_completed_academic_year = $current_year - 1;
            $last_completed_term = 2;
        } elseif ($current_month == 8) {
            // August: Term 3 just ended, academic year complete
            $last_completed_academic_year = $current_year - 1;
            $last_completed_term = 3;
        }
        
        // Add all completed terms from enrollment
        for ($year = $year_of_enrollment; $year <= $last_completed_academic_year; $year++) {
            if ($year < $last_completed_academic_year) {
                // All past academic years - add all 3 terms
                for ($term = 1; $term <= 3; $term++) {
                    $available_terms[] = [
                        'year' => $year,
                        'term' => "Term $term",
                        'label' => "$year/" . ($year + 1) . " - Term $term"
                    ];
                }
            } else {
                // Last completed academic year - only add completed terms
                for ($term = 1; $term <= $last_completed_term; $term++) {
                    $available_terms[] = [
                        'year' => $year,
                        'term' => "Term $term",
                        'label' => "$year/" . ($year + 1) . " - Term $term"
                    ];
                }
            }
        }
    }
    
    return array_reverse($available_terms); // Most recent first
}
$available_terms = getAvailableTerms($student['curriculum_name'], $student['year_of_enrollment']);

/* ---------- GET EXISTING GRADES ---------- */
$grades_stmt = $pdo->prepare("
    SELECT subject_name, grade, term, academic_year
    FROM grades
    WHERE student_id = ?
");
$grades_stmt->execute([$student_id]);
$existing_grades = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize existing grades by year-term-subject
$grades_map = [];
foreach ($existing_grades as $g) {
    $key = $g['academic_year'] . '_' . $g['term'] . '_' . $g['subject_name'];
    $grades_map[$key] = $g['grade'];
}

/* ---------- HANDLE GRADE SUBMISSION ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $selected_year = (int)$_POST['academic_year'];
    $selected_term = $_POST['term'];
    $grades_data = $_POST['grades'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        foreach ($grades_data as $subject => $grade) {
            if (empty($grade)) continue;
            
            // Check if grade exists
            $check_stmt = $pdo->prepare("
                SELECT id FROM grades 
                WHERE student_id = ? AND academic_year = ? AND term = ? AND subject_name = ?
            ");
            $check_stmt->execute([$student_id, $selected_year, $selected_term, $subject]);
            $existing = $check_stmt->fetch();
            
            if ($existing) {
                // Update
                $update_stmt = $pdo->prepare("
                    UPDATE grades 
                    SET grade = ?, teacher_id = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $update_stmt->execute([$grade, $teacher['id'], $existing['id']]);
            } else {
                // Insert
                $insert_stmt = $pdo->prepare("
                    INSERT INTO grades (student_id, subject_name, grade, term, academic_year, teacher_id)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $insert_stmt->execute([$student_id, $subject, $grade, $selected_term, $selected_year, $teacher['id']]);
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
    <title>Update Grades</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/teacher.css">
    <style>
        .grade-input-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
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
        .grade-input-item input {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
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
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
            color: #856404;
        }
        .no-terms-message {
            background: #ffebee;
            border-left: 4px solid #f44336;
            padding: 20px;
            border-radius: 4px;
            color: #c62828;
            text-align: center;
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
            <h2>Update Student Grades</h2>

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
                    <p>This student enrolled in <?php echo $student['year_of_enrollment']; ?>, but no terms have been completed yet.</p>
                    <p>Grades can only be entered for completed terms.</p>
                </div>
            <?php elseif (empty($student_subjects)): ?>
                <div class="no-terms-message">
                    <h3>⚠️ No Subjects Assigned</h3>
                    <p>This student has no subjects assigned yet. Please contact the class teacher to assign subjects.</p>
                </div>
            <?php else: ?>
                <?php if ($teacher['category'] == 'Subject Teacher' && !empty($teacher_subjects)): ?>
                    <div class="warning-box">
                        <strong>📝 Note:</strong> As a Subject Teacher, you can only update grades for: 
                        <strong><?php echo implode(', ', $teacher_subjects); ?></strong>
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
                    
                    <select id="term_combined" onchange="loadGradesForTerm()" required>
                        <option value="">Select Term to Grade</option>
                        <?php foreach ($available_terms as $term): ?>
                            <option value="<?php echo $term['year'] . '|' . $term['term']; ?>">
                                <?php echo htmlspecialchars($term['label']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <div id="gradesSection" style="display: none;">
                        <h3 style="color: var(--navy); margin-top: 30px;">Enter Grades</h3>
                        <div class="grade-input-grid" id="gradesGrid">
                            <!-- Grades will be loaded here -->
                        </div>

                        <button type="submit" style="margin-top: 30px;">Save Grades</button>
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

function loadGradesForTerm() {
    const combined = document.getElementById('term_combined').value;
    if (!combined) {
        document.getElementById('gradesSection').style.display = 'none';
        return;
    }
    
    const [year, term] = combined.split('|');
    document.getElementById('academic_year').value = year;
    document.getElementById('term_select').value = term;
    
    const gradesGrid = document.getElementById('gradesGrid');
    gradesGrid.innerHTML = '';
    
    // Determine which subjects to show
    let subjectsToGrade = studentSubjects;
    if (teacherCategory === 'Subject Teacher' && teacherSubjects.length > 0) {
        // Filter to only subjects this teacher teaches
        subjectsToGrade = studentSubjects.filter(s => teacherSubjects.includes(s));
    }
    
    if (subjectsToGrade.length === 0) {
        gradesGrid.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: #999;">You are not assigned to teach any of this student\'s subjects.</p>';
        document.getElementById('gradesSection').style.display = 'block';
        return;
    }
    
    subjectsToGrade.forEach(subject => {
        const key = year + '_' + term + '_' + subject;
        const existingGrade = existingGrades[key] || '';
        
        const div = document.createElement('div');
        div.className = 'grade-input-item';
        div.innerHTML = `
            <label>${subject}</label>
            <input type="text" 
                   name="grades[${subject}]" 
                   placeholder="e.g., A, B+, 85"
                   value="${existingGrade}">
        `;
        gradesGrid.appendChild(div);
    });
    
    document.getElementById('gradesSection').style.display = 'block';
}
</script>

</body>
</html>