<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

header('Content-Type: application/json');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Teacher') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$student_id = (int)($_GET['student_id'] ?? 0);
$year = (int)($_GET['year'] ?? 0);
$term = $_GET['term'] ?? '';
$assessment = $_GET['assessment'] ?? '';

try {
    $grades_stmt = $pdo->prepare("
        SELECT subject_name, grade, grade_points, final_score
        FROM grades
        WHERE student_id = ? AND academic_year = ? AND term = ? AND assessment_type = ?
        ORDER BY subject_name
    ");
    $grades_stmt->execute([$student_id, $year, $term, $assessment]);
    $grades = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_points = 0;
    $subjects_count = 0;
    foreach ($grades as $g) {
        if (is_numeric($g['grade_points'])) {
            $total_points += $g['grade_points'];
            $subjects_count++;
        }
    }
    
    $mean_points = $subjects_count > 0 ? round($total_points / $subjects_count, 2) : 0;
    
    echo json_encode([
        'success' => true,
        'grades' => $grades,
        'total_points' => $total_points,
        'mean_points' => $mean_points
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}