<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'Teacher' && $_SESSION['role'] !== 'Student' && $_SESSION['role'] !== 'Parent')) {
    header("Location: ../login.php");
    exit;
}

$student_id = (int)($_GET['student_id'] ?? 0);
$academic_year = (int)($_GET['year'] ?? date('Y'));
$term = $_GET['term'] ?? 'Term 1';
$assessment = $_GET['assessment'] ?? 'Opener';

/* ---------- CHECK IF REPORT IS RELEASED (For Students/Parents) ---------- */
if ($_SESSION['role'] === 'Student' || $_SESSION['role'] === 'Parent') {
    $release_check = $pdo->prepare("
        SELECT status 
        FROM grade_submissions 
        WHERE student_id = ? 
            AND academic_year = ? 
            AND term = ? 
            AND assessment_type = ?
    ");
    $release_check->execute([$student_id, $academic_year, $term, $assessment]);
    $submission = $release_check->fetch(PDO::FETCH_ASSOC);
    
    if (!$submission || $submission['status'] !== 'RELEASED') {
        die("
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Report Not Available</title>
            <link rel='stylesheet' href='../assets/css/admin.css'>
        </head>
        <body>
            <div style='max-width: 600px; margin: 100px auto; text-align: center; padding: 40px; background: white; border-radius: 10px;'>
                <h2 style='color: #f44336;'>⚠️ Report Card Not Available</h2>
                <p style='color: #666; margin-top: 20px;'>This report card has not been released yet. Please wait for your Head Teacher to approve and release it.</p>
                <a href='javascript:history.back()' style='display: inline-block; margin-top: 30px; padding: 12px 24px; background: var(--navy); color: white; text-decoration: none; border-radius: 6px;'>← Go Back</a>
            </div>
        </body>
        </html>
        ");
    }
}

/* ---------- GET STUDENT INFO ---------- */
$student_stmt = $pdo->prepare("
    SELECT 
        s.id, s.admission_number, s.first_name, s.last_name, s.gender,
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

/* ---------- GET STUDENT'S SUBJECTS ---------- */
$student_subjects_stmt = $pdo->prepare("SELECT subject_name FROM student_subjects WHERE student_id = ? ORDER BY subject_name");
$student_subjects_stmt->execute([$student_id]);
$student_subjects = $student_subjects_stmt->fetchAll(PDO::FETCH_COLUMN);

/* ---------- GET GRADES FOR SPECIFIC ASSESSMENT ---------- */
$grades_stmt = $pdo->prepare("
    SELECT 
        g.subject_name, g.score, g.rats_score, g.final_score, g.grade, g.grade_points, g.teacher_comment,
        t.first_name as teacher_first_name, t.last_name as teacher_last_name
    FROM grades g
    LEFT JOIN teachers t ON g.teacher_id = t.id
    WHERE g.student_id = ? AND g.academic_year = ? AND g.term = ? AND g.assessment_type = ?
    ORDER BY g.subject_name
");
$grades_stmt->execute([$student_id, $academic_year, $term, $assessment]);
$grades_raw = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize by subject - KEEP INDIVIDUAL TEACHER NAMES
$grades_by_subject = [];
foreach ($grades_raw as $grade) {
    $teacher_name = '-';
    if ($grade['teacher_first_name'] && $grade['teacher_last_name']) {
        $teacher_name = 'Tr. ' . $grade['teacher_first_name'] . ' ' . substr($grade['teacher_last_name'], 0, 1) . '.';
    }
    
    $grades_by_subject[$grade['subject_name']] = [
        'score' => $grade['score'],
        'rats_score' => $grade['rats_score'],
        'final_score' => $grade['final_score'],
        'grade' => $grade['grade'],
        'points' => $grade['grade_points'],
        'teacher_name' => $teacher_name,
        'comment' => $grade['teacher_comment'] ?? ''
    ];    
}

/* ---------- GET COMMENTS ---------- */
$comments_stmt = $pdo->prepare("
    SELECT class_teacher_comment, principal_comment 
    FROM grade_submissions 
    WHERE student_id = ? 
        AND academic_year = ? 
        AND term = ? 
        AND assessment_type = ?
");
$comments_stmt->execute([$student_id, $academic_year, $term, $assessment]);
$comments = $comments_stmt->fetch(PDO::FETCH_ASSOC);

$class_teacher_comment = $comments['class_teacher_comment'] ?? '';
$principal_comment = $comments['principal_comment'] ?? '';

/* ---------- CALCULATE OVERALL STATS ---------- */
$total_points = 0;
$subjects_with_grades = 0;

foreach ($grades_by_subject as $subject => $data) {
    if ($data['points'] !== null) {
        $total_points += $data['points'];
        $subjects_with_grades++;
    }
}

$mean_grade_points = $subjects_with_grades > 0 ? round($total_points / $subjects_with_grades, 2) : 0;

// Determine overall grade for 8-4-4
if ($mean_grade_points >= 11.5) $overall_grade = 'A';
elseif ($mean_grade_points >= 10.5) $overall_grade = 'A-';
elseif ($mean_grade_points >= 9.5) $overall_grade = 'B+';
elseif ($mean_grade_points >= 8.5) $overall_grade = 'B';
elseif ($mean_grade_points >= 7.5) $overall_grade = 'B-';
elseif ($mean_grade_points >= 6.5) $overall_grade = 'C+';
elseif ($mean_grade_points >= 5.5) $overall_grade = 'C';
elseif ($mean_grade_points >= 4.5) $overall_grade = 'C-';
elseif ($mean_grade_points >= 3.5) $overall_grade = 'D+';
elseif ($mean_grade_points >= 2.5) $overall_grade = 'D';
elseif ($mean_grade_points >= 1.5) $overall_grade = 'D-';
else $overall_grade = 'E';

/* ---------- CALCULATE CLASS POSITION ---------- */
$position_stmt = $pdo->prepare("
    SELECT 
        s.id,
        AVG(g.grade_points) as avg_points
    FROM students s
    JOIN grades g ON s.id = g.student_id
    WHERE s.class_level_id = (SELECT class_level_id FROM students WHERE id = ?)
        AND g.academic_year = ?
        AND g.term = ?
        AND g.assessment_type = ?
    GROUP BY s.id
    ORDER BY avg_points DESC
");
$position_stmt->execute([$student_id, $academic_year, $term, $assessment]);
$rankings = $position_stmt->fetchAll(PDO::FETCH_ASSOC);

$class_position = '-';
$position = 1;
foreach ($rankings as $rank) {
    if ($rank['id'] == $student_id) {
        $class_position = $position;
        break;
    }
    $position++;
}

$total_students = count($rankings);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Card - <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></title>
    <style>
        @media print {
            .no-print { display: none; }
            body { margin: 0; }
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        
        .report-card {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #0b1c2d;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: #0b1c2d;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f4c430;
            font-weight: bold;
            font-size: 24px;
        }
        
        .school-name {
            color: #0b1c2d;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .school-subtitle {
            color: #666;
            font-size: 14px;
            margin-bottom: 3px;
        }
        
        .school-info {
            font-size: 11px;
            color: #888;
        }
        
        .report-title {
            background: #0b1c2d;
            color: white;
            padding: 10px;
            margin: 20px 0;
            text-align: center;
            font-size: 16px;
            font-weight: bold;
        }
        
        .student-info {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 6px;
        }
        
        .info-item {
            font-size: 13px;
        }
        
        .info-label {
            font-weight: 600;
            color: #0b1c2d;
        }
        
        .grades-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 13px;
        }
        
        .grades-table th {
            background: #0b1c2d;
            color: white;
            padding: 12px 10px;
            text-align: center;
            font-weight: 600;
            border: 1px solid #333;
        }
        
        .grades-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: center;
        }
        
        .grades-table tbody tr:nth-child(even) {
            background: #f9f9f9;
        }
        
        .subject-name {
            text-align: left;
            font-weight: 600;
            color: #0b1c2d;
        }
        
        .grade-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: 600;
        }
        
        .grade-A { background: #4caf50; color: white; }
        .grade-A- { background: #66bb6a; color: white; }
        .grade-B\+ { background: #2196f3; color: white; }
        .grade-B { background: #42a5f5; color: white; }
        .grade-B- { background: #64b5f6; color: white; }
        .grade-C\+ { background: #ff9800; color: white; }
        .grade-C { background: #ffa726; color: white; }
        .grade-C- { background: #ffb74d; color: white; }
        .grade-D\+ { background: #f44336; color: white; }
        .grade-D { background: #ef5350; color: white; }
        .grade-D- { background: #e57373; color: white; }
        .grade-E { background: #9e9e9e; color: white; }
        
        .overall-summary {
            display: flex;
            justify-content: space-around;
            padding: 20px;
            background: #f4c430;
            border-radius: 6px;
            margin: 20px 0;
        }
        
        .overall-item {
            text-align: center;
        }
        
        .overall-label {
            font-size: 13px;
            color: #666;
        }
        
        .overall-value {
            font-size: 28px;
            font-weight: bold;
            color: #0b1c2d;
        }
        
        .rubric-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin: 20px 0;
            font-size: 11px;
        }
        
        .rubric-item {
            padding: 8px;
            text-align: center;
            border-radius: 4px;
            font-weight: 600;
        }
        
        .comments-section {
            margin: 20px 0;
        }
        
        .comment-box {
            border: 1px solid #ddd;
            padding: 15px;
            min-height: 80px;
            margin-bottom: 15px;
            border-radius: 6px;
        }
        
        .comment-box h4 {
            color: #0b1c2d;
            margin-bottom: 10px;
            font-size: 13px;
        }
        
        .signature-section {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 30px;
        }
        
        .signature-line {
            border-top: 2px solid #000;
            margin-top: 40px;
            padding-top: 5px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
        }
        
        .action-buttons {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-print {
            background: #0b1c2d;
            color: white;
        }
        
        .btn-back {
            background: #f4c430;
            color: #0b1c2d;
        }
    </style>
</head>
<body>

<div class="action-buttons no-print">
    <button onclick="window.print()" class="btn btn-print">🖨️ Print Report Card</button>
    <a href="javascript:history.back()" class="btn btn-back">← Back</a>
</div>

<div class="report-card">
    <!-- HEADER -->
    <div class="header">
        <div class="logo-section">
            <div class="logo">NLA</div>
            <div>
                <div class="school-name">THE NAIROBI LEADERSHIP ACADEMY</div>
                <div class="school-subtitle">JUNIOR SCHOOL</div>
                <div class="school-subtitle" style="font-style: italic; color: #f4c430; font-weight: 600;">SIMPLIFY, INSPIRE, TRANSFORM</div>
            </div>
        </div>
        <div class="school-info">
            Address: South C, Mugoya Estate, Nairobi | P.O.Box 1953 - 00100<br>
            web: www.nla.sc.ke | Email: info@nla.sc.ke
        </div>
    </div>

    <div class="report-title">
        <?php echo strtoupper($student['class_name']); ?> - <?php echo strtoupper($term); ?> <?php echo strtoupper($assessment); ?> ASSESSMENT <?php echo $academic_year; ?>
    </div>

    <!-- STUDENT INFO -->
    <div class="student-info">
        <div class="info-item">
            <span class="info-label">NAME:</span> <?php echo strtoupper(htmlspecialchars($student['first_name'] . ' ' . $student['last_name'])); ?>
        </div>
        <div class="info-item">
            <span class="info-label">ADM NO:</span> <?php echo htmlspecialchars($student['admission_number']); ?>
        </div>
        <div class="info-item">
            <span class="info-label">CLASS:</span> <?php echo htmlspecialchars($student['class_name']); ?>
        </div>
        <div class="info-item">
            <span class="info-label">ACADEMIC YEAR:</span> <?php echo $academic_year; ?>
        </div>
        <div class="info-item">
            <span class="info-label">MEAN POINTS:</span> <?php echo number_format($mean_grade_points, 2); ?> / 12
        </div>
        <div class="info-item">
            <span class="info-label">CLASS POSITION:</span> <?php echo $class_position; ?><?php if ($class_position != '-') echo ' / ' . $total_students; ?>
        </div>
    </div>

    <!-- GRADING SCALE INFO -->
    <div style="margin-bottom: 15px; font-size: 11px; padding: 10px; background: #f0f0f0; border-radius: 4px;">
        <strong>GRADING SCALE (8-4-4 SYSTEM):</strong> 
        A (80-100) = 12pts | A- (75-79) = 11pts | B+ (70-74) = 10pts | B (65-69) = 9pts | B- (60-64) = 8pts | 
        C+ (55-59) = 7pts | C (50-54) = 6pts | C- (45-49) = 5pts | D+ (40-44) = 4pts | D (35-39) = 3pts | D- (30-34) = 2pts | E (0-29) = 1pt
    </div>

    <!-- GRADES TABLE -->
    <table class="grades-table">
        <thead>
            <tr>
                <th>LEARNING AREAS</th>
                <th><?php echo strtoupper($assessment); ?><br>SCORE (100%)</th>
                <th>GRADE</th>
                <th>POINTS /8</th>
                <th>TEACHER</th>
                <th>COMMENTS</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($student_subjects as $subject): ?>
                <?php 
                $grade_data = $grades_by_subject[$subject] ?? null;
                if (!$grade_data) continue;
                ?>
                <tr>
                    <td class="subject-name"><?php echo strtoupper(htmlspecialchars($subject)); ?></td>
                    <td><strong><?php echo $grade_data['final_score']; ?></strong></td>
                    <td><span class="grade-badge grade-<?php echo $grade_data['grade']; ?>"><?php echo $grade_data['grade']; ?></span></td>
                    <td><strong><?php echo $grade_data['points']; ?></strong></td>
                    <td style="font-size: 11px;"><?php echo htmlspecialchars($grade_data['teacher_name']); ?></td>
                    <td style="font-size: 10px; text-align: left;"><?php echo htmlspecialchars($grade_data['comment']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- OVERALL SUMMARY -->
    <div class="overall-summary">
        <div class="overall-item">
            <div class="overall-label">MEAN GRADE POINTS</div>
            <div class="overall-value"><?php echo number_format($mean_grade_points, 2); ?> / 8</div>
        </div>
        <div class="overall-item">
            <div class="overall-label">OVERALL GRADE</div>
            <div class="overall-value">
                <span class="grade-badge grade-<?php echo $overall_grade; ?>" style="font-size: 24px; padding: 8px 16px;">
                    <?php echo $overall_grade; ?>
                </span>
            </div>
        </div>
        <div class="overall-item">
            <div class="overall-label">CLASS POSITION</div>
            <div class="overall-value"><?php echo $class_position; ?><?php if ($class_position != '-') echo ' / ' . $total_students; ?></div>
        </div>
    </div>

    <!-- ASSESSMENT RUBRIC -->
    <div class="rubric-grid">
        <div class="rubric-item" style="background: #4caf50; color: white;">EE1 (8 pts)</div>
        <div class="rubric-item" style="background: #4caf50; color: white;">EE2 (7 pts)</div>
        <div class="rubric-item" style="background: #2196f3; color: white;">ME1 (6 pts)</div>
        <div class="rubric-item" style="background: #2196f3; color: white;">ME2 (5 pts)</div>
        <div class="rubric-item" style="background: #ff9800; color: white;">AE1 (4 pts)</div>
        <div class="rubric-item" style="background: #ff9800; color: white;">AE2 (3 pts)</div>
        <div class="rubric-item" style="background: #f44336; color: white;">BE1 (2 pts)</div>
        <div class="rubric-item" style="background: #f44336; color: white;">BE2 (1 pt)</div>
    </div>

    <!-- COMMENTS -->
    <div class="comments-section">
        <div class="comment-box">
            <h4>CLASS TEACHER'S COMMENTS:</h4>
            <div style="min-height: 60px;">
                <?php echo $class_teacher_comment ? htmlspecialchars($class_teacher_comment) : '<span style="color: #888; font-style: italic;">[Comments to be added by class teacher]</span>'; ?>
            </div>
        </div>
        
        <div class="comment-box">
            <h4>PRINCIPAL'S COMMENTS:</h4>
            <div style="min-height: 60px;">
                <?php echo $principal_comment ? htmlspecialchars($principal_comment) : '<span style="color: #888; font-style: italic;">[Comments to be added by principal]</span>'; ?>
            </div>
        </div>
        
        <div class="comment-box">
            <h4>PARENT'S COMMENT:</h4>
            <div style="min-height: 60px; color: #888; font-style: italic;">
                [Comments to be added by parent]
            </div>
        </div>
    </div>

    <!-- SIGNATURES -->
    <div class="signature-section">
        <div>
            <div class="signature-line">Class Teacher</div>
            <div style="font-size: 11px; margin-top: 5px; text-align: center;">Date: _______________</div>
        </div>
        <div>
            <div class="signature-line">Principal</div>
            <div style="font-size: 11px; margin-top: 5px; text-align: center;">Date: _______________</div>
        </div>
        <div>
            <div class="signature-line">Parent</div>
            <div style="font-size: 11px; margin-top: 5px; text-align: center;">Date: _______________</div>
        </div>
    </div>

    <!-- TERM DATES -->
    <div style="text-align: center; margin-top: 20px; font-size: 12px; color: #666;">
        <strong>Opening Date:</strong> _______________  &nbsp;&nbsp;&nbsp;
        <strong>Closing Date:</strong> _______________
    </div>
</div>
</body>
</html>