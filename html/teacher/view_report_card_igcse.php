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
$assessment = $_GET['assessment'] ?? 'Mid-Term';

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

if ($student['curriculum_name'] !== 'IGCSE') {
    die("This report card is only for IGCSE curriculum students");
}

/* ---------- GET STUDENT'S SUBJECTS ---------- */
$student_subjects_stmt = $pdo->prepare("SELECT subject_name FROM student_subjects WHERE student_id = ? ORDER BY subject_name");
$student_subjects_stmt->execute([$student_id]);
$student_subjects = $student_subjects_stmt->fetchAll(PDO::FETCH_COLUMN);

/* ---------- GET GRADES FOR SPECIFIC ASSESSMENT ---------- */
$grades_stmt = $pdo->prepare("
    SELECT 
        g.subject_name, g.final_score, g.grade, g.grade_points, g.paper_scores, g.rats_score, g.teacher_comment,
        t.first_name as teacher_first_name, t.last_name as teacher_last_name
    FROM grades g
    LEFT JOIN teachers t ON g.teacher_id = t.id
    WHERE g.student_id = ? AND g.academic_year = ? AND g.term = ? AND g.assessment_type = ?
    ORDER BY g.subject_name
");
$grades_stmt->execute([$student_id, $academic_year, $term, $assessment]);
$grades_raw = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize by subject
$grades_by_subject = [];
foreach ($grades_raw as $grade) {
    $paper_scores = $grade['paper_scores'] ? json_decode($grade['paper_scores'], true) : [];
    
    $grades_by_subject[$grade['subject_name']] = [
        'final_score' => $grade['final_score'],
        'grade' => $grade['grade'],
        'points' => $grade['grade_points'],
        'paper_scores' => $paper_scores,
        'rats_score' => $grade['rats_score'],
        'teacher_name' => $grade['teacher_first_name'] && $grade['teacher_last_name'] 
            ? 'Tr. ' . $grade['teacher_first_name']
            : '-',
        'comment' => $grade['teacher_comment'] ?? ''
    ];
}

/* ---------- CALCULATE OVERALL STATS ---------- */
$total_points = 0;
$subjects_with_grades = 0;

foreach ($grades_by_subject as $subject => $data) {
    if ($data['points'] !== null && is_numeric($data['points'])) {
        $total_points += $data['points'];
        $subjects_with_grades++;
    }
}

$mean_score = $subjects_with_grades > 0 ? round($total_points / $subjects_with_grades, 0) : 0;
$class_position = '-'; // Implement ranking if needed
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
            max-width: 1100px;
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
            background: #9c27b0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
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
            font-size: 14px;
            font-weight: bold;
        }
        
        .student-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 6px;
        }
        
        .info-item {
            font-size: 12px;
        }
        
        .info-label {
            font-weight: 600;
            color: #0b1c2d;
        }
        
        .summary-section {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 20px;
            padding: 15px;
            background: #fff3e0;
            border-radius: 6px;
        }
        
        .summary-item {
            font-size: 13px;
            font-weight: 600;
        }
        
        .grades-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 11px;
        }
        
        .grades-table th {
            background: #0b1c2d;
            color: white;
            padding: 10px 6px;
            text-align: center;
            font-weight: 600;
            border: 1px solid #333;
        }
        
        .grades-table td {
            padding: 8px 6px;
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
            padding-left: 10px !important;
        }
        
        .grade-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 13px;
        }
        
        .grade-9 { background: #1b5e20; color: white; }
        .grade-8 { background: #2e7d32; color: white; }
        .grade-7 { background: #388e3c; color: white; }
        .grade-6 { background: #66bb6a; color: white; }
        .grade-5 { background: #fbc02d; color: white; }
        .grade-4 { background: #ff9800; color: white; }
        .grade-3 { background: #ff5722; color: white; }
        .grade-2 { background: #d32f2f; color: white; }
        .grade-1 { background: #c62828; color: white; }
        .grade-U { background: #757575; color: white; }
        
        .grading-scale {
            font-size: 10px;
            padding: 10px;
            background: #f0f0f0;
            border-radius: 4px;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .comments-section {
            margin: 20px 0;
        }
        
        .comment-box {
            border: 1px solid #ddd;
            padding: 15px;
            min-height: 70px;
            margin-bottom: 15px;
            border-radius: 6px;
        }
        
        .comment-box h4 {
            color: #0b1c2d;
            margin-bottom: 10px;
            font-size: 12px;
        }
        
        .signature-section {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 40px;
            margin-top: 30px;
        }
        
        .signature-line {
            border-top: 2px solid #000;
            margin-top: 40px;
            padding-top: 5px;
            font-size: 11px;
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
            background: #9c27b0;
            color: white;
        }
        
        .btn-back {
            background: #ffd54f;
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
                <div class="school-subtitle">PEARSON - EDEXCEL (IGCSE)</div>
                <div class="school-subtitle" style="font-style: italic; color: #9c27b0; font-weight: 600;">SIMPLIFY, INSPIRE, TRANSFORM</div>
            </div>
        </div>
        <div class="school-info">
            Address: South C, Mugoya Estate, Nairobi | P.O.Box 1953 - 00100<br>
            web: www.nla.sc.ke | Email: info@nla.sc.ke
        </div>
    </div>

    <div class="report-title">
        <?php echo strtoupper($term); ?> <?php echo strtoupper($assessment); ?> EXAM <?php echo $academic_year; ?>/<?php echo $academic_year + 1; ?>
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
            <span class="info-label">ACADEMIC YEAR:</span> <?php echo $academic_year; ?>/<?php echo $academic_year + 1; ?>
        </div>
    </div>

    <!-- SUMMARY -->
    <div class="summary-section">
        <div class="summary-item">
            <span class="info-label">MEAN SCORE:</span> <?php echo $mean_score; ?>
        </div>
        <div class="summary-item">
            <span class="info-label">MEAN POINTS:</span> <?php echo $mean_score; ?>
        </div>
        <div class="summary-item">
            <span class="info-label">CLASS POSITION:</span> <?php echo $class_position; ?>
        </div>
        <div class="summary-item">
            <span class="info-label">CLASS OUT OF:</span> -
        </div>
    </div>

    <!-- GRADING SCALE -->
    <div class="grading-scale">
        <strong>GRADING SCALE:</strong> 
        9 (90-100) | 8 (80-89) | 7 (70-79) | 6 (60-69) | 5 (50-59) | 4 (40-49) | 3 (30-39) | 2 (25-29) | 1 (20-24) | U (0-19)
    </div>

    <!-- GRADES TABLE -->
    <table class="grades-table">
        <thead>
            <tr>
                <th style="width: 25%;">SUBJECT</th>
                <th>RAT<br>(30%)</th>
                <th>ET<br>(70%)</th>
                <th>TOTAL</th>
                <th>POINTS</th>
                <th style="width: 30%;">COMMENTS</th>
                <th>TEACHER</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($student_subjects as $subject): ?>
                <?php 
                $grade_data = $grades_by_subject[$subject] ?? null;
                if (!$grade_data) continue;
                
                $papers = $grade_data['paper_scores'];
                $exam_total = 0;
                foreach ($papers as $score) {
                    if (is_numeric($score)) $exam_total += $score;
                }
                ?>
                <tr>
                    <td class="subject-name"><?php echo strtoupper(htmlspecialchars($subject)); ?></td>
                    <td><?php echo round($grade_data['rats_score'] ?? 0); ?></td>
                    <td><?php echo round($exam_total); ?></td>
                    <td><strong><?php echo round($grade_data['final_score']); ?></strong></td>
                    <td>
                        <span class="grade-badge grade-<?php echo $grade_data['grade']; ?>">
                            <?php echo $grade_data['grade']; ?>
                        </span>
                    </td>
                    <td style="font-size: 9px; text-align: left; padding-left: 8px;">
                        <?php echo htmlspecialchars($grade_data['comment']); ?>
                    </td>
                    <td style="font-size: 10px;"><?php echo htmlspecialchars($grade_data['teacher_name']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- COMMENTS -->
    <div class="comments-section">
        <div class="comment-box">
            <h4>CLASS TEACHER'S COMMENTS:</h4>
            <div style="min-height: 50px; color: #888; font-style: italic; font-size: 11px;">
                [Comments to be added by class teacher]
            </div>
        </div>
        
        <div class="comment-box">
            <h4>D. PRINCIPAL'S COMMENTS:</h4>
            <div style="min-height: 50px; color: #888; font-style: italic; font-size: 11px;">
                [Comments to be added by principal]
            </div>
        </div>
    </div>

    <!-- SIGNATURES -->
    <div class="signature-section">
        <div>
            <div class="signature-line">Signature</div>
        </div>
        <div>
            <div class="signature-line">Stamp</div>
        </div>
    </div>

    <!-- GRADING LEGEND -->
    <div style="margin-top: 20px; font-size: 10px; text-align: center; color: #666;">
        <strong>Range of Marks %:</strong> 9 8 7 6 5 4 3 2 1 U
    </div>
</div>

</body>
</html>