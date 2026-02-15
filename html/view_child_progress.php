<?php
session_start();
require_once __DIR__ . "/../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Parent') {
    header("Location: login.php");
    exit;
}

/* ---------- GET PARENT INFO ---------- */
$parent_stmt = $pdo->prepare("
    SELECT 
        p.id,
        p.first_name,
        p.last_name,
        p.phone_number,
        p.residential_area,
        p.relationship,
        p.linked_students,
        u.email
    FROM parents p
    JOIN users u ON p.user_id = u.id
    WHERE p.user_id = ?
");
$parent_stmt->execute([$_SESSION['user_id']]);
$parent = $parent_stmt->fetch(PDO::FETCH_ASSOC);

if (!$parent) {
    die("Parent record not found");
}

$student_id = (int)($_GET['student_id'] ?? 0);
$current_year = (int)($_GET['current_year'] ?? date('Y'));
$current_term = $_GET['current_term'] ?? 'Term 1';
$current_assessment = $_GET['current_assessment'] ?? 'Mid-Term';

/* ---------- VERIFY PARENT OWNS THIS STUDENT ---------- */
// Get student's admission number
$student_check_stmt = $pdo->prepare("SELECT admission_number FROM students WHERE id = ?");
$student_check_stmt->execute([$student_id]);
$student_check = $student_check_stmt->fetch();

if (!$student_check) {
    die("Student not found");
}

// Check if admission number is in parent's linked_students
$admission_numbers = explode(',', $parent['linked_students'] ?? '');
$admission_numbers = array_map('trim', $admission_numbers);

if (!in_array($student_check['admission_number'], $admission_numbers)) {
    die("Access denied: This student is not associated with your account");
}

/* ---------- GET STUDENT INFO ---------- */
$student_stmt = $pdo->prepare("
    SELECT 
        s.id, s.admission_number, s.first_name, s.last_name, s.gender,
        cl.name as class_name, ct.name as curriculum_name, cl.id as class_level_id
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

/* ---------- GET AVAILABLE PERIODS ---------- */
$periods_stmt = $pdo->prepare("
    SELECT DISTINCT academic_year, term, assessment_type
    FROM grades
    WHERE student_id = ?
    ORDER BY academic_year DESC, 
             FIELD(term, 'Term 3', 'Term 2', 'Term 1') DESC,
             FIELD(assessment_type, 'End-Term', 'Mid-Term', 'Opener') DESC
");
$periods_stmt->execute([$student_id]);
$available_periods = $periods_stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------- SMART PREVIOUS ASSESSMENT DETECTION ---------- */
function getPreviousAssessment($year, $term, $assessment) {
    $assessment_order = ['Opener' => 1, 'Mid-Term' => 2, 'End-Term' => 3];
    $term_order = ['Term 1' => 1, 'Term 2' => 2, 'Term 3' => 3];
    
    $current_order = $assessment_order[$assessment] ?? 0;
    $current_term_order = $term_order[$term] ?? 0;
    
    if ($current_order > 1) {
        $prev_assessments = array_flip($assessment_order);
        return [
            'year' => $year,
            'term' => $term,
            'assessment' => $prev_assessments[$current_order - 1]
        ];
    }
    
    if ($current_term_order > 1) {
        $prev_terms = array_flip($term_order);
        return [
            'year' => $year,
            'term' => $prev_terms[$current_term_order - 1],
            'assessment' => 'End-Term'
        ];
    }
    
    return [
        'year' => $year - 1,
        'term' => 'Term 3',
        'assessment' => 'End-Term'
    ];
}

$previous = getPreviousAssessment($current_year, $current_term, $current_assessment);

/* ---------- GET CURRENT GRADES ---------- */
$current_grades_stmt = $pdo->prepare("
    SELECT subject_name, grade, grade_points, final_score, teacher_comment
    FROM grades
    WHERE student_id = ? AND academic_year = ? AND term = ? AND assessment_type = ?
    ORDER BY subject_name
");
$current_grades_stmt->execute([$student_id, $current_year, $current_term, $current_assessment]);
$current_grades = $current_grades_stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------- GET PREVIOUS GRADES ---------- */
$previous_grades_stmt = $pdo->prepare("
    SELECT subject_name, grade, grade_points, final_score
    FROM grades
    WHERE student_id = ? AND academic_year = ? AND term = ? AND assessment_type = ?
    ORDER BY subject_name
");
$previous_grades_stmt->execute([$student_id, $previous['year'], $previous['term'], $previous['assessment']]);
$previous_grades_raw = $previous_grades_stmt->fetchAll(PDO::FETCH_ASSOC);

$previous_grades = [];
foreach ($previous_grades_raw as $grade) {
    $previous_grades[$grade['subject_name']] = $grade;
}

/* ---------- CALCULATE PROGRESS ---------- */
$progress_data = [];
$total_improved = 0;
$total_declined = 0;
$total_stable = 0;
$most_improved = ['subject' => '', 'change' => 0];
$most_declined = ['subject' => '', 'change' => 0];

$current_total_points = 0;
$previous_total_points = 0;
$subjects_count = count($current_grades);

foreach ($current_grades as $current) {
    $subject = $current['subject_name'];
    $current_points = $current['grade_points'];
    $current_total_points += $current_points;
    
    $previous_points = isset($previous_grades[$subject]) ? $previous_grades[$subject]['grade_points'] : null;
    
    if ($previous_points !== null) {
        $previous_total_points += $previous_points;
        $change = $current_points - $previous_points;
        
        if ($change > 0) {
            $total_improved++;
            $trend = 'improved';
            if ($change > $most_improved['change']) {
                $most_improved = ['subject' => $subject, 'change' => $change];
            }
        } elseif ($change < 0) {
            $total_declined++;
            $trend = 'declined';
            if ($change < $most_declined['change']) {
                $most_declined = ['subject' => $subject, 'change' => $change];
            }
        } else {
            $total_stable++;
            $trend = 'stable';
        }
    } else {
        $trend = 'new';
    }
    
    $progress_data[] = [
        'subject' => $subject,
        'current_grade' => $current['grade'],
        'current_points' => $current_points,
        'previous_grade' => $previous_grades[$subject]['grade'] ?? 'N/A',
        'previous_points' => $previous_points,
        'change' => isset($previous_points) ? ($current_points - $previous_points) : null,
        'trend' => $trend,
        'comment' => $current['teacher_comment']
    ];
}

$current_mean = $subjects_count > 0 ? round($current_total_points / $subjects_count, 2) : 0;
$previous_mean = $subjects_count > 0 ? round($previous_total_points / $subjects_count, 2) : 0;
$mean_change = $current_mean - $previous_mean;

/* ---------- GET CLASS POSITION ---------- */
$position_stmt = $pdo->prepare("
    SELECT 
        student_id,
        AVG(grade_points) as mean_points,
        COUNT(*) as subjects_count
    FROM grades
    WHERE academic_year = ? AND term = ? AND assessment_type = ?
    AND student_id IN (
        SELECT id FROM students WHERE class_level_id = ?
    )
    GROUP BY student_id
    ORDER BY mean_points DESC
");
$position_stmt->execute([$current_year, $current_term, $current_assessment, $student['class_level_id']]);
$rankings = $position_stmt->fetchAll(PDO::FETCH_ASSOC);

$current_position = 0;
foreach ($rankings as $index => $rank) {
    if ($rank['student_id'] == $student_id) {
        $current_position = $index + 1;
        break;
    }
}
$total_students = count($rankings);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Report - <?php echo htmlspecialchars($student['first_name']); ?></title>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .container { box-shadow: none !important; }
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .header-section {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #667eea;
        }
        
        .school-name {
            font-size: 28px;
            font-weight: bold;
            color: #0b1c2d;
            margin-bottom: 5px;
        }
        
        .report-title {
            font-size: 20px;
            color: #667eea;
            margin-top: 10px;
        }
        
        .student-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .student-info div {
            font-size: 14px;
        }
        
        .student-info strong {
            color: #0b1c2d;
        }
        
        .period-selector {
            margin-bottom: 30px;
            padding: 20px;
            background: #e8eaf6;
            border-radius: 8px;
        }
        
        .period-selector form {
            display: grid;
            grid-template-columns: repeat(3, 1fr) auto;
            gap: 15px;
            align-items: end;
        }
        
        .period-selector select {
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .period-selector button {
            padding: 10px 20px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .summary-card.improved {
            background: #d4edda;
            border-left: 4px solid #28a745;
        }
        
        .summary-card.declined {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        
        .summary-card.stable {
            background: #e2e3e5;
            border-left: 4px solid #6c757d;
        }
        
        .summary-card h3 {
            font-size: 32px;
            margin-bottom: 5px;
        }
        
        .summary-card p {
            font-size: 14px;
            color: #666;
        }
        
        .progress-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
        }
        
        .progress-table th {
            background: #667eea;
            color: white;
            padding: 12px;
            text-align: left;
            font-size: 14px;
        }
        
        .progress-table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            font-size: 14px;
        }
        
        .progress-table tr:hover {
            background: #f8f9fa;
        }
        
        .grade-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 12px;
        }
        
        .grade-ee { background: #d4edda; color: #155724; }
        .grade-me { background: #cce5ff; color: #004085; }
        .grade-ae { background: #fff3cd; color: #856404; }
        .grade-be { background: #f8d7da; color: #721c24; }
        
        .trend-icon {
            font-size: 18px;
        }
        
        .actions {
            margin: 30px 0;
            text-align: center;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 0 10px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        
        @media (max-width: 768px) {
            .summary-cards {
                grid-template-columns: 1fr;
            }
            
            .student-info {
                grid-template-columns: 1fr;
            }
            
            .period-selector form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-section">
            <div class="school-name">NAIROBI LEADERSHIP ACADEMY</div>
            <div class="report-title">📊 STUDENT PROGRESS REPORT</div>
        </div>
        
        <div class="student-info">
            <div><strong>Student Name:</strong> <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
            <div><strong>Admission No:</strong> <?php echo htmlspecialchars($student['admission_number']); ?></div>
            <div><strong>Class:</strong> <?php echo htmlspecialchars($student['class_name']); ?></div>
            <div><strong>Curriculum:</strong> <?php echo htmlspecialchars($student['curriculum_name']); ?></div>
        </div>
        
        <div class="period-selector no-print">
            <form method="GET">
                <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                
                <div>
                    <label>Academic Year:</label>
                    <select name="current_year">
                        <?php
                        $years = range(date('Y') - 2, date('Y') + 1);
                        foreach ($years as $y) {
                            $selected = ($y == $current_year) ? 'selected' : '';
                            echo "<option value='$y' $selected>$y</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div>
                    <label>Term:</label>
                    <select name="current_term">
                        <?php
                        $terms = ['Term 1', 'Term 2', 'Term 3'];
                        foreach ($terms as $t) {
                            $selected = ($t == $current_term) ? 'selected' : '';
                            echo "<option value='$t' $selected>$t</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <div>
                    <label>Assessment:</label>
                    <select name="current_assessment">
                        <?php
                        $assessments = ['Opener', 'Mid-Term', 'End-Term'];
                        foreach ($assessments as $a) {
                            $selected = ($a == $current_assessment) ? 'selected' : '';
                            echo "<option value='$a' $selected>$a</option>";
                        }
                        ?>
                    </select>
                </div>
                
                <button type="submit">View Report</button>
            </form>
        </div>
        
        <?php if (empty($current_grades)): ?>
            <div style="text-align: center; padding: 60px 20px; background: #f8f9fa; border-radius: 8px;">
                <div style="font-size: 48px; margin-bottom: 20px;">📝</div>
                <h3 style="color: #0b1c2d; margin-bottom: 10px;">No Grades Available</h3>
                <p style="color: #666;">No grades have been recorded for this assessment period yet.</p>
            </div>
        <?php else: ?>
            <h3 style="color: #0b1c2d; margin-bottom: 15px;">
                Performance Summary - <?php echo "$current_year $current_term $current_assessment"; ?>
            </h3>
            
            <div class="summary-cards">
                <div class="summary-card improved">
                    <h3><?php echo $total_improved; ?></h3>
                    <p>Subjects Improved</p>
                </div>
                
                <div class="summary-card stable">
                    <h3><?php echo $total_stable; ?></h3>
                    <p>Subjects Stable</p>
                </div>
                
                <div class="summary-card declined">
                    <h3><?php echo $total_declined; ?></h3>
                    <p>Subjects Declined</p>
                </div>
            </div>
            
            <div style="padding: 20px; background: #fff3cd; border-radius: 8px; margin: 20px 0;">
                <h4 style="color: #856404; margin-bottom: 10px;">📈 Overall Performance</h4>
                <p style="margin: 5px 0;"><strong>Current Mean:</strong> <?php echo $current_mean; ?> points 
                   (Position: <?php echo $current_position; ?>/<?php echo $total_students; ?>)</p>
                <p style="margin: 5px 0;"><strong>Previous Mean:</strong> <?php echo $previous_mean; ?> points</p>
                <p style="margin: 5px 0;"><strong>Change:</strong> 
                    <span style="color: <?php echo $mean_change > 0 ? '#28a745' : ($mean_change < 0 ? '#dc3545' : '#6c757d'); ?>">
                        <?php echo $mean_change > 0 ? '+' : ''; ?><?php echo $mean_change; ?> points
                    </span>
                </p>
            </div>
            
            <table class="progress-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Previous</th>
                        <th>Current</th>
                        <th>Change</th>
                        <th>Trend</th>
                        <th>Teacher Comment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($progress_data as $item): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($item['subject']); ?></strong></td>
                            <td>
                                <?php if ($item['previous_grade'] !== 'N/A'): ?>
                                    <span class="grade-badge grade-<?php echo strtolower(substr($item['previous_grade'], 0, 2)); ?>">
                                        <?php echo htmlspecialchars($item['previous_grade']); ?>
                                    </span>
                                    (<?php echo $item['previous_points']; ?>)
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="grade-badge grade-<?php echo strtolower(substr($item['current_grade'], 0, 2)); ?>">
                                    <?php echo htmlspecialchars($item['current_grade']); ?>
                                </span>
                                (<?php echo $item['current_points']; ?>)
                            </td>
                            <td>
                                <?php if ($item['change'] !== null): ?>
                                    <span style="color: <?php echo $item['change'] > 0 ? '#28a745' : ($item['change'] < 0 ? '#dc3545' : '#6c757d'); ?>">
                                        <?php echo $item['change'] > 0 ? '+' : ''; ?><?php echo $item['change']; ?>
                                    </span>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td class="trend-icon">
                                <?php
                                $icons = [
                                    'improved' => '↗️',
                                    'declined' => '↘️',
                                    'stable' => '→',
                                    'new' => '✨'
                                ];
                                echo $icons[$item['trend']];
                                ?>
                            </td>
                            <td style="font-size: 13px; color: #666;">
                                <?php echo htmlspecialchars($item['comment'] ?: 'No comment'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div class="actions no-print">
            <a href="parent_progress_reports.php" class="btn btn-secondary">← Back to Students</a>
            <a href="javascript:window.print()" class="btn btn-primary">🖨️ Print Report</a>
        </div>
    </div>
</body>
</html>