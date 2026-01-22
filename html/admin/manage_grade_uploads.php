<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

/* ---------- HANDLE TOGGLE UPDATE ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] == 'toggle') {
    $year = (int)$_POST['year'];
    $term = $_POST['term'];
    $assessment = $_POST['assessment'];
    $curriculum = $_POST['curriculum'];
    $is_enabled = (int)$_POST['is_enabled'];
    
    try {
        $check = $pdo->prepare("
            SELECT id FROM grade_upload_permissions 
            WHERE academic_year = ? AND term = ? AND assessment_type = ? AND curriculum_name = ?
        ");
        $check->execute([$year, $term, $assessment, $curriculum]);
        $exists = $check->fetch();
        
        if ($exists) {
            $update = $pdo->prepare("
                UPDATE grade_upload_permissions 
                SET is_enabled = ? 
                WHERE id = ?
            ");
            $update->execute([$is_enabled, $exists['id']]);
        } else {
            $insert = $pdo->prepare("
                INSERT INTO grade_upload_permissions (academic_year, term, assessment_type, curriculum_name, is_enabled)
                VALUES (?, ?, ?, ?, ?)
            ");
            $insert->execute([$year, $term, $assessment, $curriculum, $is_enabled]);
        }
        
        echo json_encode(['success' => true]);
        exit;
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

/* ---------- HANDLE UNLOCK REQUEST ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action']) && $_POST['action'] == 'unlock') {
    $student_id = (int)$_POST['student_id'];
    $year = (int)$_POST['year'];
    $term = $_POST['term'];
    $assessment = $_POST['assessment'];
    
    try {
        $unlock = $pdo->prepare("
            UPDATE grade_submissions 
            SET is_locked = 0, unlocked_by = ?, unlocked_at = NOW()
            WHERE student_id = ? AND academic_year = ? AND term = ? AND assessment_type = ?
        ");
        $unlock->execute([$_SESSION['user_id'], $student_id, $year, $term, $assessment]);
        
        $unlock_grades = $pdo->prepare("
            UPDATE grades 
            SET is_locked = 0
            WHERE student_id = ? AND academic_year = ? AND term = ? AND assessment_type = ?
        ");
        $unlock_grades->execute([$student_id, $year, $term, $assessment]);
        
        header("Location: manage_grade_uploads.php?success=unlocked");
        exit;
    } catch (PDOException $e) {
        $error = "Error unlocking grades: " . $e->getMessage();
    }
}

/* ---------- GET CURRENT PERMISSIONS ---------- */
$current_year = (int)date('Y');
$years = range(2021, $current_year);
$terms = ['Term 1', 'Term 2', 'Term 3'];
$assessments = ['Opener', 'Mid-Term', 'End-Term']; // Both CBE
// and 8-4-4 use same assessments
$curriculums = ['CBE', '8-4-4'];

$permissions_query = $pdo->query("SELECT * FROM grade_upload_permissions");
$permissions_raw = $permissions_query->fetchAll(PDO::FETCH_ASSOC);

$permissions = [];
foreach ($permissions_raw as $perm) {
    $key = $perm['academic_year'] . '_' . $perm['term'] . '_' . $perm['assessment_type'] . '_' . $perm['curriculum_name'];
    $permissions[$key] = $perm['is_enabled'];
}

/* ---------- GET UNLOCK REQUESTS ---------- */
$unlock_requests = $pdo->query("
    SELECT 
        gs.id,
        gs.student_id,
        gs.academic_year,
        gs.term,
        gs.assessment_type,
        s.first_name,
        s.last_name,
        s.admission_number,
        t.first_name as teacher_first_name,
        t.last_name as teacher_last_name
    FROM grade_submissions gs
    JOIN students s ON gs.student_id = s.id
    JOIN teachers t ON gs.teacher_id = t.id
    WHERE gs.is_locked = 1
    ORDER BY gs.submitted_at DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Grade Uploads</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .permissions-grid {
            display: grid;
            gap: 20px;
            margin-top: 20px;
        }
        .year-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border-left: 6px solid var(--navy);
        }
        .year-header {
            color: var(--navy);
            font-size: 24px;
            margin-bottom: 0;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 6px;
            cursor: pointer;
            user-select: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s;
        }
        .year-header:hover {
            background: #e8e8e8;
        }
        .year-header .toggle-icon {
            font-size: 20px;
            transition: transform 0.3s;
        }
        .year-header.collapsed .toggle-icon {
            transform: rotate(-90deg);
        }
        .year-content {
            max-height: 5000px;
            overflow: hidden;
            transition: max-height 0.4s ease-out;
            margin-top: 15px;
        }
        .year-content.collapsed {
            max-height: 0;
            margin-top: 0;
        }
        .curriculum-section {
            margin-bottom: 20px;
        }
        .curriculum-title {
            background: var(--navy);
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .term-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        .term-card {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 6px;
            border: 2px solid #e0e0e0;
        }
        .term-card h4 {
            color: var(--navy);
            margin-bottom: 10px;
        }
        .assessment-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .assessment-row:last-child {
            border-bottom: none;
        }
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 24px;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }
        input:checked + .toggle-slider {
            background-color: #4caf50;
        }
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        .unlock-section {
            background: #fff3cd;
            padding: 20px;
            border-radius: 8px;
            border-left: 6px solid #ffc107;
            margin-top: 30px;
        }
        .unlock-table {
            width: 100%;
            margin-top: 15px;
            border-collapse: collapse;
            background: white;
        }
        .unlock-table th {
            background: var(--navy);
            color: white;
            padding: 10px;
            text-align: left;
        }
        .unlock-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .btn-unlock {
            background: #f44336;
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }
        .btn-unlock:hover {
            background: #d32f2f;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>BIMS Admin</h2>
    <a href="../admin_dashboard.php">Dashboard</a>
    <a href="create_user.php">Create User</a>
    <a href="list_users.php">List Users</a>
    <a href="manage_grade_uploads.php" class="active">Manage Grade Uploads</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main-content">
    <div class="container">
        <div class="card">
            <h1>📊 Manage Grade Upload Permissions</h1>
            <p>Control which academic periods are open for grade entry by class teachers.</p>
            <p style="color: #666; font-size: 14px; margin-top: 10px;">
                ℹ️ Click on a year to expand/collapse. Toggle switches ON to allow class teachers to upload grades.
            </p>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert-success">✅ Grades unlocked successfully.</div>
            <?php endif; ?>

            <div class="permissions-grid">
                <?php foreach (array_reverse($years) as $index => $year): ?>
                    <div class="year-section">
                        <div class="year-header <?php echo $index > 0 ? 'collapsed' : ''; ?>" onclick="toggleYear(this)">
                            <span><?php echo $year; ?> Academic Year</span>
                            <span class="toggle-icon">▼</span>
                        </div>
                        
                        <div class="year-content <?php echo $index > 0 ? 'collapsed' : ''; ?>">
                            <?php foreach ($curriculums as $curriculum): ?>
                                <div class="curriculum-section">
                                    <div class="curriculum-title">
                                        <?php echo $curriculum; ?> Curriculum
                                    </div>
                                    
                                    <div class="term-grid">
                                        <?php foreach ($terms as $term): ?>
                                            <div class="term-card">
                                                <h4><?php echo $term; ?></h4>
                                                
                                                <?php foreach ($assessments as $assessment): ?>
                                                    <div class="assessment-row">
                                                        <span><?php echo $assessment; ?></span>
                                                        <label class="toggle-switch">
                                                            <?php
                                                            $key = $year . '_' . $term . '_' . $assessment . '_' . $curriculum;
                                                            $is_checked = isset($permissions[$key]) && $permissions[$key] ? 'checked' : '';
                                                            ?>
                                                            <input type="checkbox" 
                                                                   <?php echo $is_checked; ?>
                                                                   onchange="togglePermission(<?php echo $year; ?>, '<?php echo $term; ?>', '<?php echo $assessment; ?>', '<?php echo $curriculum; ?>', this.checked)">
                                                            <span class="toggle-slider"></span>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- UNLOCK REQUESTS SECTION -->
            <?php if (!empty($unlock_requests)): ?>
                <div class="unlock-section">
                    <h2>🔓 Locked Grade Submissions (Recent 50)</h2>
                    <p>These grades have been submitted and locked. Click "Unlock" to allow the class teacher to make changes.</p>
                    
                    <table class="unlock-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Admission No.</th>
                                <th>Year</th>
                                <th>Term</th>
                                <th>Assessment</th>
                                <th>Teacher</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($unlock_requests as $req): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($req['admission_number']); ?></td>
                                    <td><?php echo $req['academic_year']; ?></td>
                                    <td><?php echo htmlspecialchars($req['term']); ?></td>
                                    <td><?php echo htmlspecialchars($req['assessment_type'] ?? 'Final'); ?></td>
                                    <td><?php echo htmlspecialchars($req['teacher_first_name'] . ' ' . $req['teacher_last_name']); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to unlock this submission? The teacher will be able to edit the grades.');">
                                            <input type="hidden" name="action" value="unlock">
                                            <input type="hidden" name="student_id" value="<?php echo $req['student_id']; ?>">
                                            <input type="hidden" name="year" value="<?php echo $req['academic_year']; ?>">
                                            <input type="hidden" name="term" value="<?php echo $req['term']; ?>">
                                            <input type="hidden" name="assessment" value="<?php echo $req['assessment_type']; ?>">
                                            <button type="submit" class="btn-unlock">🔓 Unlock</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleYear(header) {
    const content = header.nextElementSibling;
    header.classList.toggle('collapsed');
    content.classList.toggle('collapsed');
}

function togglePermission(year, term, assessment, curriculum, isEnabled) {
    const formData = new FormData();
    formData.append('action', 'toggle');
    formData.append('year', year);
    formData.append('term', term);
    formData.append('assessment', assessment);
    formData.append('curriculum', curriculum);
    formData.append('is_enabled', isEnabled ? 1 : 0);
    
    fetch('manage_grade_uploads.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Permission updated successfully');
        } else {
            alert('Error updating permission: ' + data.error);
        }
    })
    .catch(error => {
        alert('Error: ' + error);
    });
}
</script>

</body>
</html>