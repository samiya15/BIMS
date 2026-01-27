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
    'Mathematics' => [
        'papers' => [
            ['name' => 'Paper 1', 'max' => 100],
            ['name' => 'Paper 2', 'max' => 100]
        ],
        'divisor' => 2
    ],
    'Chemistry' => [
        'papers' => [
            ['name' => 'Paper 1', 'max' => 80],
            ['name' => 'Paper 2', 'max' => 80],
            ['name' => 'Paper 3', 'max' => 40]
        ],
        'divisor' => 2
    ],
    'Biology' => [
        'papers' => [
            ['name' => 'Paper 1', 'max' => 80],
            ['name' => 'Paper 2', 'max' => 80],
            ['name' => 'Paper 3', 'max' => 40]
        ],
        'divisor' => 2
    ],
    'Physics' => [
        'papers' => [
            ['name' => 'Paper 1', 'max' => 80],
            ['name' => 'Paper 2', 'max' => 80],
            ['name' => 'Paper 3', 'max' => 40]
        ],
        'divisor' => 2
    ],
    'English' => [
        'papers' => [
            ['name' => 'Paper 1', 'max' => 60],
            ['name' => 'Paper 2', 'max' => 80],
            ['name' => 'Paper 3', 'max' => 60]
        ],
        'divisor' => 2
    ],
    'Kiswahili' => [
        'papers' => [
            ['name' => 'Paper 1', 'max' => 80],
            ['name' => 'Paper 2', 'max' => 60],
            ['name' => 'Paper 3', 'max' => 80]
        ],
        'divisor' => 2.2
    ],
    'Geography' => [
        'papers' => [
            ['name' => 'Paper 1', 'max' => 100],
            ['name' => 'Paper 2', 'max' => 100]
        ],
        'divisor' => 2
    ],
    'History' => [
        'papers' => [
            ['name' => 'Paper 1', 'max' => 100],
            ['name' => 'Paper 2', 'max' => 100]
        ],
        'divisor' => 2
    ],
    'Arabic' => [
        'papers' => [
            ['name' => 'Paper 1', 'max' => 80],
            ['name' => 'Paper 2', 'max' => 80],
            ['name' => 'Paper 3', 'max' => 40]
        ],
        'divisor' => 2
    ],
    'Computer Studies' => [
        'papers' => [
            ['name' => 'Paper 1', 'max' => 100],
            ['name' => 'Paper 2', 'max' => 100]
        ],
        'divisor' => 2
    ],
    'Business Studies' => [
        'papers' => [
            ['name' => 'Paper 1', 'max' => 100],
            ['name' => 'Paper 2', 'max' => 100]
        ],
        'divisor' => 2
    ]
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

// Only allow 8-4-4 students
if ($student['curriculum_name'] !== '8-4-4') {
    die("This page is only for 8-4-4 curriculum students");
}

/* ---------- GET STUDENT'S SUBJECTS ---------- */
$student_subjects_stmt = $pdo->prepare("SELECT subject_name FROM student_subjects WHERE student_id = ? ORDER BY subject_name");
$student_subjects_stmt->execute([$student_id]);
$student_subjects = $student_subjects_stmt->fetchAll(PDO::FETCH_COLUMN);

/* ---------- GET EXISTING GRADES ---------- */
$grades_stmt = $pdo->prepare("
    SELECT subject_name, grade, final_score, paper_scores, assessment_type, term, academic_year, is_locked, teacher_comment
    FROM grades WHERE student_id = ?
");
$grades_stmt->execute([$student_id]);
$all_grades = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);

$grades_organized = [];
foreach ($all_grades as $g) {
    $grades_organized[$g['academic_year']][$g['term']][$g['assessment_type']][$g['subject_name']] = [
        'final_score' => $g['final_score'],
        'grade' => $g['grade'],
        'is_locked' => $g['is_locked'],
        'comment' => $g['teacher_comment'] ?? '',
        'paper_scores' => $g['paper_scores'] ? json_decode($g['paper_scores'], true) : []
    ];
}

/* ---------- HANDLE GRADE SUBMISSION ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $selected_year = (int)$_POST['academic_year'];
    $selected_term = $_POST['term'];
    $selected_assessment = $_POST['assessment_type'];
    $grades_data = $_POST['grades'] ?? [];
    $comments_data = $_POST['comments'] ?? [];
    $lock_submission = isset($_POST['lock_submission']) ? 1 : 0;
    
    try {
        $pdo->beginTransaction();
        
        foreach ($grades_data as $subject => $paper_data) {
            // Skip if no papers entered
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
            
            // Skip if no papers entered
            if (empty($papers_done)) continue;
            
            // Calculate final score
            $final_score = 0;
            if (isset($subject_papers[$subject])) {
                // Multi-paper subject
                $final_score = round($total_raw / $subject_papers[$subject]['divisor'], 1);
            } else {
                // Single paper or unknown subject - assume out of 100
                $final_score = $total_raw;
            }
            
            // Cap at 100
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
            
            // Calculate grade points (for mean calculation)
            $grade_points_map = [
                'A' => 12, 'A-' => 11, 'B+' => 10, 'B' => 9, 'B-' => 8,
                'C+' => 7, 'C' => 6, 'C-' => 5, 'D+' => 4, 'D' => 3, 'D-' => 2, 'E' => 1
            ];
            $grade_points = $grade_points_map[$grade] ?? 0;
            
            $teacher_comment = isset($comments_data[$subject]) ? trim($comments_data[$subject]) : null;
            $paper_scores_json = json_encode($papers_done);
            
            // Check if grade exists
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
                        teacher_id = ?, 
                        is_locked = ?, 
                        teacher_comment = ?, 
                        updated_at = NOW() 
                    WHERE id = ?
                ");
                $update_stmt->execute([$grade, $final_score, $grade_points, $paper_scores_json, $teacher['id'], $lock_submission, $teacher_comment, $existing['id']]);
            } else {
                $insert_stmt = $pdo->prepare("
                    INSERT INTO grades 
                    (student_id, subject_name, grade, final_score, grade_points, paper_scores, term, assessment_type, academic_year, teacher_id, is_locked, teacher_comment) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insert_stmt->execute([$student_id, $subject, $grade, $final_score, $grade_points, $paper_scores_json, $selected_term, $selected_assessment, $selected_year, $teacher['id'], $lock_submission, $teacher_comment]);
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
        .calculated-total {
            background: #e3f2fd;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            font-weight: 600;
            color: #1976d2;
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
                                        <div class="collapsible-header collapsed" onclick="toggleSection(this)">
                                            <span><?php echo $assessment; ?></span>
                                            <span class="toggle-icon">▼</span>
                                        </div>
                                        
                                        <div class="collapsible-content collapsed">
                                            <form method="POST" onsubmit="return confirmSubmit(this)">
                                                <input type="hidden" name="academic_year" value="<?php echo $year; ?>">
                                                <input type="hidden" name="term" value="<?php echo $term; ?>">
                                                <input type="hidden" name="assessment_type" value="<?php echo $assessment; ?>">
                                                
                                                <?php foreach ($student_subjects as $subject):
                                                    $grade_data = $grades_organized[$year][$term][$assessment][$subject] ?? [];
                                                    $existing_papers = $grade_data['paper_scores'] ?? [];
                                                    $is_multi_paper = isset($subject_papers[$subject]);
                                                ?>
                                                    <div class="subject-card">
                                                        <h4><?php echo htmlspecialchars($subject); ?></h4>
                                                        
                                                        <?php if ($is_multi_paper): ?>
                                                            <p style="font-size: 13px; color: #666; margin-bottom: 10px;">
                                                                Enter marks for each paper done in this exam:
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
                                                                               placeholder="Leave blank if not done">
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                            <div class="calculated-total">
                                                                ℹ️ Final score will be automatically calculated out of 100
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
                                                                       placeholder="Enter total score">
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <div style="margin-top: 10px;">
                                                            <label style="font-size: 13px; color: #666;">Teacher's Comment</label>
                                                            <textarea name="comments[<?php echo htmlspecialchars($subject); ?>]" 
                                                                      rows="2"
                                                                      placeholder="Optional comment"
                                                                      style="width: 100%; padding: 8px; border: 2px solid #ddd; border-radius: 4px; font-size: 13px; resize: vertical;"><?php echo htmlspecialchars($grade_data['comment'] ?? ''); ?></textarea>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                                
                                                <div style="display: flex; align-items: center; background: #fff3cd; padding: 12px; border-radius: 4px; margin: 15px 0;">
                                                    <input type="checkbox" name="lock_submission" value="1" style="width: auto; margin-right: 10px;">
                                                    <label>🔒 Lock these grades after submission</label>
                                                </div>
                                                
                                                <button type="submit" class="button">💾 Save Grades</button>
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
</script>

</body>
</html>