<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Student') {
    header("Location: ../login.php");
    exit;
}

/* ---------- GET STUDENT DATA ---------- */
$stmt = $pdo->prepare("
    SELECT 
        u.email,
        s.id as student_id,
        s.admission_number,
        s.first_name,
        s.last_name,
        s.gender,
        s.date_of_birth,
        s.phone_number,
        s.residential_area,
        s.parent_phone,
        s.parent_email,
        s.year_of_enrollment,
        s.status,
        cl.name as class_name,
        ct.name as curriculum_name
    FROM users u
    JOIN students s ON u.id = s.user_id
    LEFT JOIN classes_levels cl ON s.class_level_id = cl.id
    LEFT JOIN curriculum_types ct ON cl.curriculum_type_id = ct.id
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student profile not found");
}

/* ---------- GET STUDENT'S SUBJECTS ---------- */
$subjects_stmt = $pdo->prepare("SELECT subject_name FROM student_subjects WHERE student_id = ? ORDER BY subject_name");
$subjects_stmt->execute([$student['student_id']]);
$student_subjects = $subjects_stmt->fetchAll(PDO::FETCH_COLUMN);

/* ---------- GET LINKED PARENTS ---------- */
$linked_parents = [];
if (!empty($student['admission_number'])) {
    $parents_stmt = $pdo->prepare("
        SELECT p.first_name, p.last_name, p.phone_number, p.relationship, u.email
        FROM parents p
        JOIN users u ON p.user_id = u.id
        WHERE p.linked_students LIKE ?
    ");
    $parents_stmt->execute(['%' . $student['admission_number'] . '%']);
    $linked_parents = $parents_stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ---------- GET ACADEMIC STATS ---------- */
$stats = [
    'total_subjects' => count($student_subjects),
    'years_enrolled' => date('Y') - ($student['year_of_enrollment'] ?? date('Y')),
    'total_assessments' => 0,
    'latest_mean_points' => '-'
];

try {
    // Count total assessments
    $assessments_stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT CONCAT(academic_year, term, assessment_type)) as total
        FROM grades
        WHERE student_id = ?
    ");
    $assessments_stmt->execute([$student['student_id']]);
    $stats['total_assessments'] = $assessments_stmt->fetchColumn();
    
    // Get latest mean points
    $latest_stmt = $pdo->prepare("
        SELECT AVG(grade_points) as mean_points
        FROM grades
        WHERE student_id = ?
        AND CONCAT(academic_year, term, assessment_type) = (
            SELECT CONCAT(academic_year, term, assessment_type)
            FROM grades
            WHERE student_id = ?
            ORDER BY academic_year DESC, 
                CASE term 
                    WHEN 'Term 3' THEN 3 
                    WHEN 'Term 2' THEN 2 
                    WHEN 'Term 1' THEN 1 
                END DESC,
                CASE assessment_type
                    WHEN 'End-Term' THEN 3
                    WHEN 'Mid-Term' THEN 2
                    WHEN 'Opener' THEN 1
                END DESC
            LIMIT 1
        )
    ");
    $latest_stmt->execute([$student['student_id'], $student['student_id']]);
    $mean = $latest_stmt->fetchColumn();
    if ($mean !== null && $mean !== false) {
        $stats['latest_mean_points'] = number_format($mean, 2);
    }
} catch (PDOException $e) {
    // Keep default values
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/student.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, var(--navy) 0%, #1a3a52 100%);
            color: white;
            padding: 40px;
            border-radius: 12px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(244, 196, 48, 0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--yellow), #ddb300);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: 700;
            color: var(--navy);
            margin: 0 auto 20px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            border: 5px solid rgba(255, 255, 255, 0.2);
        }
        
        .profile-name {
            text-align: center;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .profile-title {
            text-align: center;
            font-size: 16px;
            color: var(--yellow);
            margin-bottom: 30px;
        }
        
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .quick-stat {
            text-align: center;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            backdrop-filter: blur(10px);
        }
        
        .quick-stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--yellow);
            margin-bottom: 5px;
        }
        
        .quick-stat-label {
            font-size: 12px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 25px;
            border-left: 5px solid var(--yellow);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .section-title {
            color: var(--navy);
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-icon {
            font-size: 24px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            padding: 15px;
            background: #f9f9f9;
            border-radius: 8px;
            border-left: 3px solid var(--navy);
        }
        
        .info-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .info-value {
            font-size: 16px;
            color: var(--navy);
            font-weight: 600;
        }
        
        .subjects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        
        .subject-badge {
            background: linear-gradient(135deg, #f9f9f9, #fff);
            padding: 15px 20px;
            border-radius: 10px;
            text-align: center;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
            cursor: default;
        }
        
        .subject-badge:hover {
            border-color: var(--yellow);
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(244, 196, 48, 0.2);
        }
        
        .subject-icon {
            font-size: 28px;
            margin-bottom: 8px;
        }
        
        .subject-name {
            font-weight: 600;
            color: var(--navy);
            font-size: 14px;
        }
        
        .parent-card {
            background: linear-gradient(135deg, #f9f9f9, #fff);
            padding: 20px;
            border-radius: 10px;
            border-left: 4px solid var(--navy);
            display: flex;
            gap: 20px;
            align-items: center;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        
        .parent-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .parent-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--navy), #1a3a52);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--yellow);
            flex-shrink: 0;
        }
        
        .parent-info h4 {
            color: var(--navy);
            margin-bottom: 5px;
            font-size: 16px;
        }
        
        .parent-relationship {
            color: #666;
            font-size: 13px;
            font-style: italic;
            margin-bottom: 8px;
        }
        
        .parent-contact {
            font-size: 13px;
            color: #555;
            margin: 3px 0;
        }
        
        .parent-contact strong {
            color: var(--navy);
        }
        
        .status-active {
            display: inline-block;
            background: #4caf50;
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .status-inactive {
            display: inline-block;
            background: #f44336;
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-style: italic;
            background: #f9f9f9;
            border-radius: 10px;
            border: 2px dashed #ddd;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .btn-primary {
            background: var(--navy);
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            border: 2px solid var(--navy);
        }
        
        .btn-primary:hover {
            background: var(--black);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(11, 28, 45, 0.3);
        }
        
        .btn-secondary {
            background: white;
            color: var(--navy);
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            border: 2px solid var(--navy);
        }
        
        .btn-secondary:hover {
            background: var(--navy);
            color: white;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .profile-header {
                padding: 30px 20px;
            }
            
            .profile-avatar {
                width: 100px;
                height: 100px;
                font-size: 40px;
            }
            
            .profile-name {
                font-size: 24px;
            }
            
            .quick-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .subjects-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
            
            .parent-card {
                flex-direction: column;
                text-align: center;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-primary,
            .btn-secondary {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>BIMS Student</h2>
    <a href="../student_dashboard.php">Dashboard</a>
    <a href="my_profile.php" class="active">My Profile</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main-content">
    <div class="container">
        <!-- PROFILE HEADER -->
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
            </div>
            <div class="profile-name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
            <div class="profile-title"><?php echo htmlspecialchars($student['curriculum_name'] ?? 'Student'); ?> - <?php echo htmlspecialchars($student['class_name'] ?? 'Not Assigned'); ?></div>
            
            <div class="quick-stats">
                <div class="quick-stat">
                    <div class="quick-stat-value"><?php echo $stats['total_subjects']; ?></div>
                    <div class="quick-stat-label">Subjects</div>
                </div>
                <div class="quick-stat">
                    <div class="quick-stat-value"><?php echo $stats['years_enrolled']; ?></div>
                    <div class="quick-stat-label">Years Enrolled</div>
                </div>
                <div class="quick-stat">
                    <div class="quick-stat-value"><?php echo $stats['total_assessments']; ?></div>
                    <div class="quick-stat-label">Assessments</div>
                </div>
                <div class="quick-stat">
                    <div class="quick-stat-value"><?php echo $stats['latest_mean_points']; ?></div>
                    <div class="quick-stat-label">Latest Mean</div>
                </div>
            </div>
        </div>

        <!-- BASIC INFORMATION -->
        <div class="info-section">
            <h2 class="section-title">
                <span class="section-icon">📋</span>
                Basic Information
            </h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Admission Number</div>
                    <div class="info-value"><?php echo htmlspecialchars($student['admission_number'] ?? '-'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?php echo htmlspecialchars($student['email']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Gender</div>
                    <div class="info-value"><?php echo htmlspecialchars($student['gender'] ?? '-'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Date of Birth</div>
                    <div class="info-value">
                        <?php 
                        if ($student['date_of_birth']) {
                            echo date('F j, Y', strtotime($student['date_of_birth']));
                        } else {
                            echo '-';
                        }
                        ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Phone Number</div>
                    <div class="info-value"><?php echo htmlspecialchars($student['phone_number'] ?? '-'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Residential Area</div>
                    <div class="info-value"><?php echo htmlspecialchars($student['residential_area'] ?? '-'); ?></div>
                </div>
            </div>
        </div>

        <!-- ACADEMIC INFORMATION -->
        <div class="info-section">
            <h2 class="section-title">
                <span class="section-icon">🎓</span>
                Academic Information
            </h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Curriculum</div>
                    <div class="info-value"><?php echo htmlspecialchars($student['curriculum_name'] ?? 'Not Assigned'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Current Class</div>
                    <div class="info-value"><?php echo htmlspecialchars($student['class_name'] ?? 'Not Assigned'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Year of Enrollment</div>
                    <div class="info-value"><?php echo $student['year_of_enrollment'] ?? '-'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Account Status</div>
                    <div class="info-value">
                        <?php if ($student['status']): ?>
                            <span class="status-active">✓ Active</span>
                        <?php else: ?>
                            <span class="status-inactive">✗ Inactive</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- MY SUBJECTS -->
        <div class="info-section">
            <h2 class="section-title">
                <span class="section-icon">📚</span>
                My Subjects (<?php echo count($student_subjects); ?>)
            </h2>
            
            <?php if (!empty($student_subjects)): ?>
                <div class="subjects-grid">
                    <?php foreach ($student_subjects as $subject): ?>
                        <div class="subject-badge">
                            <div class="subject-icon">📖</div>
                            <div class="subject-name"><?php echo htmlspecialchars($subject); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <p>📚 No subjects assigned yet.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- PARENT INFORMATION -->
        <div class="info-section">
            <h2 class="section-title">
                <span class="section-icon">👨‍👩‍👧</span>
                Parent/Guardian Information
            </h2>
            
            <?php if (!empty($linked_parents)): ?>
                <?php foreach ($linked_parents as $parent): ?>
                    <div class="parent-card">
                        <div class="parent-avatar">👤</div>
                        <div class="parent-info">
                            <h4><?php echo htmlspecialchars(($parent['first_name'] ?? '') . ' ' . ($parent['last_name'] ?? '')); ?></h4>
                            <?php if ($parent['relationship']): ?>
                                <p class="parent-relationship"><?php echo htmlspecialchars($parent['relationship']); ?></p>
                            <?php endif; ?>
                            <p class="parent-contact"><strong>Email:</strong> <?php echo htmlspecialchars($parent['email'] ?? '-'); ?></p>
                            <?php if ($parent['phone_number']): ?>
                                <p class="parent-contact"><strong>Phone:</strong> <?php echo htmlspecialchars($parent['phone_number']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-data">
                    <p>👨‍👩‍👧 No parent/guardian information available.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- EMERGENCY CONTACT -->
        <?php if ($student['parent_phone'] || $student['parent_email']): ?>
            <div class="info-section">
                <h2 class="section-title">
                    <span class="section-icon">🚨</span>
                    Emergency Contact
                </h2>
                <div class="info-grid">
                    <?php if ($student['parent_phone']): ?>
                        <div class="info-item">
                            <div class="info-label">Parent Phone</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['parent_phone']); ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($student['parent_email']): ?>
                        <div class="info-item">
                            <div class="info-label">Parent Email</div>
                            <div class="info-value"><?php echo htmlspecialchars($student['parent_email']); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ACTION BUTTONS -->
        <div class="action-buttons">
            <a href="../student_dashboard.php" class="btn-primary">
                <span>📊</span> View My Report Cards
            </a>
            <a href="../student_dashboard.php" class="btn-secondary">
                <span>←</span> Back to Dashboard
            </a>
        </div>
    </div>
</div>

</body>
</html>