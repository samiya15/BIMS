<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Teacher') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$student_id = (int)($_GET['student_id'] ?? 0);
$year = (int)($_GET['year'] ?? 0);
$term = $_GET['term'] ?? '';
$assessment = $_GET['assessment'] ?? '';

if (!$student_id || !$year || !$term || !$assessment) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

try {
    /* ---------- GET GRADES ---------- */
    $grades_stmt = $pdo->prepare("
        SELECT 
            subject_name,
            score,
            rats_score,
            final_score,
            grade,
            grade_points,
            teacher_comment
        FROM grades
        WHERE student_id = ? 
            AND academic_year = ? 
            AND term = ? 
            AND assessment_type = ?
        ORDER BY subject_name
    ");
    $grades_stmt->execute([$student_id, $year, $term, $assessment]);
    $grades = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($grades)) {
        echo json_encode(['success' => false, 'error' => 'No grades found']);
        exit;
    }
    
    /* ---------- CALCULATE STATISTICS ---------- */
    $total_points = 0;
    $subjects_with_points = 0;
    
    foreach ($grades as $grade) {
        if ($grade['grade_points'] !== null) {
            $total_points += $grade['grade_points'];
            $subjects_with_points++;
        }
    }
    
    $mean_points = $subjects_with_points > 0 ? round($total_points / $subjects_with_points, 2) : 0;
    
    /* ---------- DETERMINE OVERALL GRADE ---------- */
    $overall_grade = '';
    if ($mean_points >= 7.5) $overall_grade = 'EE1';
    elseif ($mean_points >= 6.5) $overall_grade = 'EE2';
    elseif ($mean_points >= 5.5) $overall_grade = 'ME1';
    elseif ($mean_points >= 4.5) $overall_grade = 'ME2';
    elseif ($mean_points >= 3.5) $overall_grade = 'AE1';
    elseif ($mean_points >= 2.5) $overall_grade = 'AE2';
    elseif ($mean_points >= 1.5) $overall_grade = 'BE1';
    else $overall_grade = 'BE2';
    
    echo json_encode([
        'success' => true,
        'grades' => $grades,
        'total_points' => $total_points,
        'mean_points' => $mean_points,
        'overall_grade' => $overall_grade,
        'subjects_count' => count($grades)
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}