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

/* ---------- GET PARENT'S CHILDREN ---------- */
$children = [];

// Get linked students from parent record (contains admission numbers)
if (!empty($parent['linked_students'])) {
    $admission_numbers = explode(',', $parent['linked_students']);
    $admission_numbers = array_map('trim', $admission_numbers); // Remove spaces
    $admission_numbers = array_filter($admission_numbers); // Remove empty
    
    if (!empty($admission_numbers)) {
        $placeholders = str_repeat('?,', count($admission_numbers) - 1) . '?';
        
        $children_stmt = $pdo->prepare("
            SELECT 
                s.id, s.admission_number, s.first_name, s.last_name,
                cl.name as class_name, ct.name as curriculum_name
            FROM students s
            JOIN classes_levels cl ON s.class_level_id = cl.id
            JOIN curriculum_types ct ON cl.curriculum_type_id = ct.id
            WHERE s.admission_number IN ($placeholders)
            ORDER BY s.first_name, s.last_name
        ");
        $children_stmt->execute($admission_numbers);
        $children = $children_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Progress Reports - Parent Portal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #0b1c2d 0%, #1a3a52 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
        }
        
        .header h1 {
            color: #0b1c2d;
            margin-bottom: 10px;
            font-size: 32px;
        }
        
        .header p {
            color: #666;
            font-size: 16px;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 15px;
            color: #f4c430;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .back-link:hover {
            color: #0b1c2d;
        }
        
        .students-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }
        
        .student-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
            transition: all 0.3s;
            border-top: 4px solid #f4c430;
        }
        
        .student-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(244, 196, 48, 0.3);
        }
        
        .student-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0b1c2d 0%, #1a3a52 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin: 0 auto 20px;
            box-shadow: 0 4px 15px rgba(11, 28, 45, 0.3);
        }
        
        .student-name {
            text-align: center;
            font-size: 22px;
            font-weight: 600;
            color: #0b1c2d;
            margin-bottom: 10px;
        }
        
        .student-details {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .student-details div {
            margin: 5px 0;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin: 0 5px;
        }
        
        .badge-cbe {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-844 {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-igcse {
            background: #cce5ff;
            color: #004085;
        }
        
        .view-progress-btn {
            display: block;
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #0b1c2d 0%, #1a3a52 100%);
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            border: 2px solid transparent;
        }
        
        .view-progress-btn:hover {
            background: white;
            color: #0b1c2d;
            border-color: #f4c430;
            box-shadow: 0 4px 15px rgba(244, 196, 48, 0.4);
        }
        
        .no-children {
            background: white;
            padding: 60px 40px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
            border-top: 4px solid #f4c430;
        }
        
        .no-children-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .no-children h2 {
            color: #0b1c2d;
            margin-bottom: 10px;
        }
        
        .no-children p {
            color: #666;
        }
        
        .debug-info {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            border-left: 4px solid #ffc107;
        }
        
        @media (max-width: 768px) {
            .students-grid {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="parent_dashboard.php" class="back-link">← Back to Dashboard</a>
            <h1>📊 Student Progress Reports</h1>
            <p>View detailed progress reports for your children</p>
        </div>
        
        <?php if (empty($children)): ?>
            <div class="no-children">
                <div class="no-children-icon">👨‍👩‍👧‍👦</div>
                <h2>No Students Found</h2>
                <p>No student records are linked to your account yet.</p>
                <p style="margin-top: 10px; font-size: 14px;">Please contact the school administration for assistance.</p>
                
                <?php if (!empty($parent)): ?>
                    <div class="debug-info" style="margin-top: 20px; text-align: left;">
                        <strong>🔍 Debug Information (for admin):</strong><br>
                        Parent ID: <?php echo $parent['id']; ?><br>
                        Available columns: <?php echo implode(', ', array_keys($parent)); ?><br>
                        <small>This info helps identify how children are linked in your database.</small>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="students-grid">
                <?php foreach ($children as $child): ?>
                    <div class="student-card">
                        <div class="student-icon">
                            👤
                        </div>
                        
                        <div class="student-name">
                            <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                        </div>
                        
                        <div class="student-details">
                            <div>
                                <strong>Admission No:</strong> <?php echo htmlspecialchars($child['admission_number']); ?>
                            </div>
                            <div>
                                <strong>Class:</strong> <?php echo htmlspecialchars($child['class_name']); ?>
                            </div>
                            <div>
                                <?php
                                $curriculum = $child['curriculum_name'];
                                $badge_class = 'badge-cbe';
                                if ($curriculum == '8-4-4') $badge_class = 'badge-844';
                                if ($curriculum == 'IGCSE') $badge_class = 'badge-igcse';
                                ?>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo htmlspecialchars($curriculum); ?>
                                </span>
                            </div>
                        </div>
                        
                        <a href="view_child_progress.php?student_id=<?php echo $child['id']; ?>" 
                           class="view-progress-btn">
                            View Progress Report
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>