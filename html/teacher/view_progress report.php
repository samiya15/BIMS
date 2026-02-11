<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Teacher') {
    header("Location: ../login.php");
    exit;
}

$student_id = (int)($_GET['student_id'] ?? 0);
$current_year = (int)($_GET['current_year'] ?? date('Y'));
$current_term = $_GET['current_term'] ?? 'Term 1';
$current_assessment = $_GET['current_assessment'] ?? 'Mid-Term';

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

/* ---------- SMART PREVIOUS ASSESSMENT DETECTION ---------- */
function getPreviousAssessment($year, $term, $assessment) {
    $assessment_order = ['Opener' => 1, 'Mid-Term' => 2, 'End-Term' => 3];
    $term_order = ['Term 1' => 1, 'Term 2' => 2, 'Term 3' => 3];
    
    $current_order = $assessment_order[$assessment] ?? 0;
    $current_term_order = $term_order[$term] ?? 0;
    
    // Same term, previous assessment
    if ($current_order > 1) {
        $prev_assessments = array_flip($assessment_order);
        return [
            'year' => $year,
            'term' => $term,
            'assessment' => $prev_assessments[$current_order - 1]
        ];
    }
    
    // Different term, get end-term of previous term
    if ($current_term_order > 1) {
        $prev_terms = array_flip($term_order);
        return [
            'year' => $year,
            'term' => $prev_terms[$current_term_order - 1],
            'assessment' => 'End-Term'
        ];
    }
    
    // Previous year, term 3 end-term
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

// Organize previous grades by subject
$previous_grades = [];
foreach ($previous_grades_raw as $grade) {
    $previous_grades[$grade['subject_name']] = $grade;
}

/* ---------- CALCULATE PROGRESS ---------- */
$progress_data = [];
$total_improved = 0;
$total_declined = 0;
$total_stable = 0;
$most_improved = ['subject' => '', 'change' => -999];
$most_declined = ['subject' => '', 'change' => 999];

foreach ($current_grades as $current) {
    $subject = $current['subject_name'];
    $prev = $previous_grades[$subject] ?? null;
    
    $change = 0;
    $trend = 'new';
    
    if ($prev) {
        $change = $current['grade_points'] - $prev['grade_points'];
        
        if ($change > 0) {
            $trend = 'improved';
            $total_improved++;
            if ($change > $most_improved['change']) {
                $most_improved = ['subject' => $subject, 'change' => $change];
            }
        } elseif ($change < 0) {
            $trend = 'declined';
            $total_declined++;
            if ($change < $most_declined['change']) {
                $most_declined = ['subject' => $subject, 'change' => $change];
            }
        } else {
            $trend = 'stable';
            $total_stable++;
        }
    }
    
    $progress_data[] = [
        'subject' => $subject,
        'previous_grade' => $prev['grade'] ?? '-',
        'previous_points' => $prev['grade_points'] ?? 0,
        'current_grade' => $current['grade'],
        'current_points' => $current['grade_points'],
        'change' => $change,
        'trend' => $trend,
        'comment' => $current['teacher_comment'] ?? ''
    ];
}

/* ---------- CALCULATE OVERALL STATISTICS ---------- */
$prev_mean = 0;
$curr_mean = 0;

if (!empty($previous_grades)) {
    $prev_total = array_sum(array_column($previous_grades_raw, 'grade_points'));
    $prev_mean = count($previous_grades_raw) > 0 ? $prev_total / count($previous_grades_raw) : 0;
}

if (!empty($current_grades)) {
    $curr_total = array_sum(array_column($current_grades, 'grade_points'));
    $curr_mean = count($current_grades) > 0 ? $curr_total / count($current_grades) : 0;
}

$overall_change = $curr_mean - $prev_mean;

// Get overall grades
function getOverallGrade($mean_points) {
    if ($mean_points >= 7.5) return 'EE1';
    if ($mean_points >= 6.5) return 'EE2';
    if ($mean_points >= 5.5) return 'ME1';
    if ($mean_points >= 4.5) return 'ME2';
    if ($mean_points >= 3.5) return 'AE1';
    if ($mean_points >= 2.5) return 'AE2';
    if ($mean_points >= 1.5) return 'BE1';
    return 'BE2';
}

$prev_overall_grade = getOverallGrade($prev_mean);
$curr_overall_grade = getOverallGrade($curr_mean);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Report - <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></title>
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
        
        .progress-report {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #0b1c2d;
            padding-bottom: 20px;
            margin-bottom: 30px;
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
        }
        
        .report-title {
            background: linear-gradient(135deg, #0b1c2d, #1a3a52);
            color: white;
            padding: 15px;
            margin: 30px 0;
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            border-radius: 8px;
        }
        
        .student-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .info-item {
            font-size: 14px;
        }
        
        .info-label {
            font-weight: 600;
            color: #0b1c2d;
        }
        
        .comparison-info {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 6px;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #4caf50, #45a049);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
        }
        
        .summary-card.declined {
            background: linear-gradient(135deg, #f44336, #d32f2f);
        }
        
        .summary-card.stable {
            background: linear-gradient(135deg, #2196f3, #1976d2);
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 20px;
        }
        
        .summary-item {
            text-align: center;
            background: rgba(255,255,255,0.2);
            padding: 15px;
            border-radius: 8px;
        }
        
        .summary-label {
            font-size: 12px;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .summary-value {
            font-size: 28px;
            font-weight: bold;
        }
        
        .change-indicator {
            font-size: 18px;
            margin-top: 10px;
        }
        
        .progress-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .progress-table thead {
            background: #0b1c2d;
            color: white;
        }
        
        .progress-table th {
            padding: 12px;
            text-align: left;
            font-size: 13px;
        }
        
        .progress-table td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 14px;
        }
        
        .progress-table tbody tr:hover {
            background: #f9f9f9;
        }
        
        .trend-improved {
            color: #4caf50;
            font-weight: bold;
        }
        
        .trend-declined {
            color: #f44336;
            font-weight: bold;
        }
        
        .trend-stable {
            color: #757575;
        }
        
        .trend-new {
            color: #2196f3;
            font-style: italic;
        }
        
        .grade-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 12px;
        }
        
        .grade-EE1, .grade-EE2 { background: #4caf50; color: white; }
        .grade-ME1, .grade-ME2 { background: #2196f3; color: white; }
        .grade-AE1, .grade-AE2 { background: #ff9800; color: white; }
        .grade-BE1, .grade-BE2 { background: #f44336; color: white; }
        
        .insights-section {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 20px;
            margin: 30px 0;
            border-radius: 8px;
        }
        
        .insight-item {
            padding: 10px 0;
            font-size: 15px;
        }
        
        .insight-icon {
            font-size: 20px;
            margin-right: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: white;
            border: 2px solid #e0e0e0;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #0b1c2d;
        }
        
        .stat-label {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
        
        .action-buttons {
            margin: 20px 0;
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 12px 24px;
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
    <button onclick="window.print()" class="btn btn-print">🖨️ Print Progress Report</button>
    <a href="javascript:history.back()" class="btn btn-back">← Back</a>
</div>

<div class="progress-report">
    <!-- HEADER -->
    <div class="header">
        <div class="logo-section">
            <div class="logo">NLA</div>
            <div>
                <div class="school-name">NAIROBI LEADERSHIP ACADEMY</div>
                <div style="font-size: 14px; color: #666;">JUNIOR SCHOOL</div>
            </div>
        </div>
    </div>

    <div class="report-title">
        📊 STUDENT PROGRESS REPORT
    </div>

    <!-- STUDENT INFO -->
    <div class="student-info">
        <div class="info-item">
            <span class="info-label">NAME:</span> <?php echo strtoupper(htmlspecialchars($student['first_name'] . ' ' . $student['last_name'])); ?>
        </div>
        <div class="info-item">
            <span class="info-label">ADMISSION NO:</span> <?php echo htmlspecialchars($student['admission_number']); ?>
        </div>
        <div class="info-item">
            <span class="info-label">CLASS:</span> <?php echo htmlspecialchars($student['class_name']); ?>
        </div>
    </div>

    <!-- COMPARISON PERIOD INFO -->
    <div class="comparison-info">
        <strong>📅 Comparison Period:</strong><br>
        <strong>Current:</strong> <?php echo $current_year; ?> - <?php echo $current_term; ?> - <?php echo $current_assessment; ?><br>
        <strong>Previous:</strong> <?php echo $previous['year']; ?> - <?php echo $previous['term']; ?> - <?php echo $previous['assessment']; ?>
    </div>

    <!-- OVERALL PERFORMANCE SUMMARY -->
    <?php
    $summary_class = $overall_change > 0 ? '' : ($overall_change < 0 ? 'declined' : 'stable');
    ?>
    <div class="summary-card <?php echo $summary_class; ?>">
        <h2 style="margin-bottom: 20px;">🎯 OVERALL PERFORMANCE SUMMARY</h2>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">PREVIOUS</div>
                <div class="summary-value"><?php echo number_format($prev_mean, 2); ?> (<?php echo $prev_overall_grade; ?>)</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">CURRENT</div>
                <div class="summary-value"><?php echo number_format($curr_mean, 2); ?> (<?php echo $curr_overall_grade; ?>)</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">CHANGE</div>
                <div class="summary-value">
                    <?php 
                    echo ($overall_change >= 0 ? '+' : '') . number_format($overall_change, 2);
                    if ($overall_change > 0) echo ' ↗️';
                    elseif ($overall_change < 0) echo ' ↘️';
                    else echo ' →';
                    ?>
                </div>
                <div class="change-indicator">
                    <?php
                    if ($overall_change > 0) echo 'IMPROVED';
                    elseif ($overall_change < 0) echo 'DECLINED';
                    else echo 'STABLE';
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- PROGRESS STATISTICS -->
    <div class="stats-grid">
        <div class="stat-card" style="border-color: #4caf50;">
            <div class="stat-number" style="color: #4caf50;"><?php echo $total_improved; ?></div>
            <div class="stat-label">Subjects Improved</div>
        </div>
        <div class="stat-card" style="border-color: #757575;">
            <div class="stat-number" style="color: #757575;"><?php echo $total_stable; ?></div>
            <div class="stat-label">Subjects Stable</div>
        </div>
        <div class="stat-card" style="border-color: #f44336;">
            <div class="stat-number" style="color: #f44336;"><?php echo $total_declined; ?></div>
            <div class="stat-label">Subjects Declined</div>
        </div>
    </div>

    <!-- SMART INSIGHTS -->
    <?php if ($total_improved > 0 || $total_declined > 0): ?>
        <div class="insights-section">
            <h3 style="color: #0b1c2d; margin-bottom: 15px;">💡 Key Insights</h3>
            
            <?php if ($total_improved > 0): ?>
                <div class="insight-item">
                    <span class="insight-icon">✅</span>
                    <strong>Great progress!</strong> You improved in <?php echo $total_improved; ?> out of <?php echo count($current_grades); ?> subjects.
                </div>
            <?php endif; ?>
            
            <?php if ($most_improved['change'] > 0): ?>
                <div class="insight-item">
                    <span class="insight-icon">🏆</span>
                    <strong>Most Improved:</strong> <?php echo htmlspecialchars($most_improved['subject']); ?> 
                    (+<?php echo $most_improved['change']; ?> points)
                </div>
            <?php endif; ?>
            
            <?php if ($most_declined['change'] < 0): ?>
                <div class="insight-item">
                    <span class="insight-icon">⚠️</span>
                    <strong>Needs Attention:</strong> <?php echo htmlspecialchars($most_declined['subject']); ?> 
                    (<?php echo $most_declined['change']; ?> points)
                </div>
            <?php endif; ?>
            
            <?php if ($overall_change > 1): ?>
                <div class="insight-item">
                    <span class="insight-icon">🎉</span>
                    <strong>Excellent!</strong> Your overall performance improved significantly!
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- SUBJECT PROGRESS TABLE -->
    <h3 style="margin: 30px 0 15px; color: #0b1c2d;">📚 Subject-by-Subject Progress</h3>
    <table class="progress-table">
        <thead>
            <tr>
                <th>SUBJECT</th>
                <th>PREVIOUS</th>
                <th>CURRENT</th>
                <th>CHANGE</th>
                <th>TREND</th>
                <th>COMMENT</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($progress_data as $data): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($data['subject']); ?></strong></td>
                    <td>
                        <span class="grade-badge grade-<?php echo $data['previous_grade']; ?>">
                            <?php echo $data['previous_grade']; ?>
                        </span>
                        (<?php echo $data['previous_points']; ?>)
                    </td>
                    <td>
                        <span class="grade-badge grade-<?php echo $data['current_grade']; ?>">
                            <?php echo $data['current_grade']; ?>
                        </span>
                        (<?php echo $data['current_points']; ?>)
                    </td>
                    <td class="trend-<?php echo $data['trend']; ?>">
                        <?php 
                        if ($data['change'] > 0) echo '+' . $data['change'];
                        elseif ($data['change'] < 0) echo $data['change'];
                        else echo '0';
                        ?>
                    </td>
                    <td class="trend-<?php echo $data['trend']; ?>">
                        <?php 
                        if ($data['trend'] == 'improved') echo '↗️ Improved';
                        elseif ($data['trend'] == 'declined') echo '↘️ Declined';
                        elseif ($data['trend'] == 'stable') echo '→ Stable';
                        else echo '✨ New';
                        ?>
                    </td>
                    <td style="font-size: 11px; max-width: 200px;">
                        <?php echo htmlspecialchars($data['comment']); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- TEACHER COMMENTS SECTION -->
    <div style="margin-top: 40px; padding: 20px; background: #f9f9f9; border-radius: 8px;">
        <h3 style="color: #0b1c2d; margin-bottom: 15px;">👨‍🏫 Teacher's Overall Comments</h3>
        <div style="min-height: 100px; padding: 15px; background: white; border-radius: 6px;">
            <em>[Space for teacher's overall progress comments]</em>
        </div>
        
        <div style="margin-top: 30px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 30px;">
            <div>
                <div style="border-top: 2px solid #000; margin-top: 40px; padding-top: 5px; text-align: center;">
                    <strong>Class Teacher</strong><br>
                    <span style="font-size: 12px;">Date: _______________</span>
                </div>
            </div>
            <div>
                <div style="border-top: 2px solid #000; margin-top: 40px; padding-top: 5px; text-align: center;">
                    <strong>Parent/Guardian</strong><br>
                    <span style="font-size: 12px;">Date: _______________</span>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>