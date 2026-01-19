<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

$user_id = (int) $_GET['id'];

/* ---------- GET TEACHER DATA ---------- */
try {
    $stmt = $pdo->prepare("
        SELECT users.email, teachers.id as teacher_id, teachers.first_name, 
               teachers.last_name, teachers.category, teachers.assigned_class_id,
               teachers.phone_number, teachers.residential_area, 
               teachers.date_of_birth, teachers.national_id
        FROM users
        LEFT JOIN teachers ON users.id = teachers.user_id
        WHERE users.id = ?
    ");
    $stmt->execute([$user_id]);
    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$teacher) {
        header("Location: list_users.php");
        exit;
    }
    
    // Get teacher's subjects grouped by curriculum
    $subjects_stmt = $pdo->query("
        SELECT ts.curriculum_type_id, ct.name as curriculum_name, ts.subject_name
        FROM teacher_subjects ts
        JOIN curriculum_types ct ON ts.curriculum_type_id = ct.id
        WHERE ts.teacher_id = {$teacher['teacher_id']}
        ORDER BY ct.id, ts.subject_name
    ");
    $teacher_subjects_raw = $subjects_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize by curriculum
    $teacher_subjects_by_curriculum = [];
    foreach ($teacher_subjects_raw as $row) {
        $teacher_subjects_by_curriculum[$row['curriculum_type_id']][] = $row['subject_name'];
    }
    
    // Get all curriculums
    $curriculums = $pdo->query("SELECT id, name FROM curriculum_types ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get subjects for each curriculum
    $subjects_by_curriculum = [];
    foreach ($curriculums as $curr) {
        $subj_stmt = $pdo->prepare("
            SELECT subject_name, is_core 
            FROM curriculum_subjects 
            WHERE curriculum_type_id = ? 
            ORDER BY is_core DESC, subject_name
        ");
        $subj_stmt->execute([$curr['id']]);
        $subjects_by_curriculum[$curr['id']] = $subj_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get all available classes
    $classes = $pdo->query("
        SELECT cl.id, cl.name, ct.name as curriculum_name
        FROM classes_levels cl
        JOIN curriculum_types ct ON cl.curriculum_type_id = ct.id
        ORDER BY ct.id, cl.level_order
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

/* ---------- HANDLE UPDATE ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $category = $_POST['category'];
    $assigned_class_id = (!empty($_POST['assigned_class_id']) && $category == 'Class Teacher') ? (int)$_POST['assigned_class_id'] : null;
    $phone_number = trim($_POST['phone_number'] ?? '');
    $residential_area = trim($_POST['residential_area'] ?? '');
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $national_id = trim($_POST['national_id'] ?? '');
    
    try {
        // Update teacher info
        $stmt = $pdo->prepare("
            UPDATE teachers 
            SET first_name = ?, last_name = ?, category = ?, assigned_class_id = ?,
                phone_number = ?, residential_area = ?, date_of_birth = ?, national_id = ?
            WHERE user_id = ?
        ");
        $stmt->execute([
            $first_name, $last_name, $category, $assigned_class_id,
            $phone_number, $residential_area, $date_of_birth, $national_id,
            $user_id
        ]);
        
        // Update subjects (only for Subject Teachers and Class Teachers)
        if ($category != 'Head Teacher') {
            // Delete old subjects
            $pdo->prepare("DELETE FROM teacher_subjects WHERE teacher_id = ?")->execute([$teacher['teacher_id']]);
            
            // Insert new subjects - organized by curriculum
            if (!empty($_POST['curriculum_subjects'])) {
                $insert_stmt = $pdo->prepare("
                    INSERT INTO teacher_subjects (teacher_id, curriculum_type_id, subject_name) 
                    VALUES (?, ?, ?)
                ");
                
                foreach ($_POST['curriculum_subjects'] as $curriculum_id => $subjects) {
                    if (!empty($subjects)) {
                        foreach ($subjects as $subject) {
                            $insert_stmt->execute([$teacher['teacher_id'], $curriculum_id, $subject]);
                        }
                    }
                }
            }
        }
        
        header("Location: update_teacher.php?id=" . $user_id . "&success=1");
        exit;
        
    } catch (PDOException $e) {
        $error = "Error updating profile: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Teacher Profile</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/update_profile.css">
    <style>
        .curriculum-section {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid var(--navy);
        }
        .curriculum-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .curriculum-header input[type="checkbox"] {
            width: auto;
            margin-right: 10px;
        }
        .curriculum-header label {
            font-size: 18px;
            font-weight: 600;
            color: var(--navy);
            margin: 0;
        }
        .subject-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
            padding: 15px;
            background: white;
            border-radius: 6px;
            display: none;
        }
        .subject-grid.active {
            display: grid;
        }
        .subject-item {
            display: flex;
            align-items: center;
        }
        .subject-item input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
        }
        .subject-item.core {
            font-weight: 600;
            color: var(--navy);
        }
        .category-info {
            background: #e3f2fd;
            padding: 12px;
            border-left: 4px solid #2196f3;
            margin: 15px 0;
            border-radius: 4px;
        }
        .hidden {
            display: none;
        }
        .profile-section {
            background: #fff9e6;
            padding: 15px;
            border-left: 4px solid var(--yellow);
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .profile-section h3 {
            color: var(--navy);
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>BIMS Admin</h2>
    <a href="../admin_dashboard.php">Dashboard</a>
    <a href="create_user.php">Create User</a>
    <a href="list_users.php">List Users</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main-content">
    <div class="container">
        <div class="card">
            <h2>Update Teacher Profile</h2>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($teacher['email']); ?> <em>(Cannot be changed)</em></p>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert-success">✅ Profile updated successfully.</div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" id="teacherForm">
                <!-- BASIC INFO (Cannot change) -->
                <div class="profile-section">
                    <h3>📋 Basic Information (Required)</h3>
                    
                    <label>First Name <em>(Cannot be changed after creation)</em></label>
                    <input type="text" name="first_name" required value="<?php echo htmlspecialchars($teacher['first_name'] ?? ''); ?>">

                    <label>Last Name <em>(Cannot be changed after creation)</em></label>
                    <input type="text" name="last_name" required value="<?php echo htmlspecialchars($teacher['last_name'] ?? ''); ?>" >
                </div>

                <!-- EDITABLE PROFILE INFO -->
                <div class="profile-section">
                    <h3>✏️ Personal Details (Editable)</h3>
                    
                    <label>Phone Number</label>
                    <input type="tel" name="phone_number" placeholder="+254..." value="<?php echo htmlspecialchars($teacher['phone_number'] ?? ''); ?>">

                    <label>Residential Area</label>
                    <input type="text" name="residential_area" placeholder="e.g., Nairobi, Westlands" value="<?php echo htmlspecialchars($teacher['residential_area'] ?? ''); ?>">

                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($teacher['date_of_birth'] ?? ''); ?>">

                    <label>National ID / Passport Number</label>
                    <input type="text" name="national_id" placeholder="12345678" value="<?php echo htmlspecialchars($teacher['national_id'] ?? ''); ?>">
                </div>

                <!-- TEACHER CATEGORY -->
                <label>Teacher Category</label>
                <select name="category" id="category" required onchange="updateCategoryOptions()">
                    <option value="">Select Category</option>
                    <option value="Subject Teacher" <?php echo ($teacher['category'] ?? '') == 'Subject Teacher' ? 'selected' : ''; ?>>Subject Teacher</option>
                    <option value="Class Teacher" <?php echo ($teacher['category'] ?? '') == 'Class Teacher' ? 'selected' : ''; ?>>Class Teacher</option>
                    <option value="Head Teacher" <?php echo ($teacher['category'] ?? '') == 'Head Teacher' ? 'selected' : ''; ?>>Head Teacher</option>
                </select>

                <div id="categoryInfo" class="category-info hidden">
                    <strong>ℹ️ Category Info:</strong>
                    <p id="categoryDescription"></p>
                </div>

                <!-- Assigned Class (for Class Teachers only) -->
                <div id="assignedClassSection" class="hidden">
                    <label>Assigned Class</label>
                    <select name="assigned_class_id" id="assigned_class_id">
                        <option value="">Select Class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo ($teacher['assigned_class_id'] ?? '') == $class['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['curriculum_name'] . ' - ' . $class['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Curriculum & Subjects (for Subject Teachers and Class Teachers) -->
                <div id="curriculumSubjectsSection" class="hidden">
                    <h3>📚 Curriculum & Subjects Teaching</h3>
                    <p class="help-text">Select the curriculum(s) you teach, then choose subjects for each curriculum.</p>
                    
                    <?php foreach ($curriculums as $curr): ?>
                        <div class="curriculum-section">
                            <div class="curriculum-header">
                                <input type="checkbox" 
                                       id="curriculum_<?php echo $curr['id']; ?>" 
                                       onchange="toggleCurriculumSubjects(<?php echo $curr['id']; ?>)"
                                       <?php echo isset($teacher_subjects_by_curriculum[$curr['id']]) ? 'checked' : ''; ?>>
                                <label for="curriculum_<?php echo $curr['id']; ?>">
                                    <?php echo htmlspecialchars($curr['name']); ?> Curriculum
                                </label>
                            </div>
                            
                            <div id="subjects_<?php echo $curr['id']; ?>" class="subject-grid <?php echo isset($teacher_subjects_by_curriculum[$curr['id']]) ? 'active' : ''; ?>">
                                <?php foreach ($subjects_by_curriculum[$curr['id']] as $subject): ?>
                                    <div class="subject-item <?php echo $subject['is_core'] ? 'core' : ''; ?>">
                                        <input type="checkbox" 
                                               name="curriculum_subjects[<?php echo $curr['id']; ?>][]" 
                                               value="<?php echo htmlspecialchars($subject['subject_name']); ?>"
                                               id="subject_<?php echo $curr['id']; ?>_<?php echo str_replace(' ', '_', $subject['subject_name']); ?>"
                                               <?php echo isset($teacher_subjects_by_curriculum[$curr['id']]) && in_array($subject['subject_name'], $teacher_subjects_by_curriculum[$curr['id']]) ? 'checked' : ''; ?>>
                                        <label for="subject_<?php echo $curr['id']; ?>_<?php echo str_replace(' ', '_', $subject['subject_name']); ?>" style="margin: 0;">
                                            <?php echo htmlspecialchars($subject['subject_name']); ?>
                                            <?php if ($subject['is_core']): ?>
                                                <span style="color: #d32f2f; font-size: 11px;">(Core)</span>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="submit" style="margin-top: 20px;">Update Profile</button>
            </form>

            <a href="list_users.php" class="button button-yellow" style="margin-top: 20px;">← Back to Users</a>
        </div>
    </div>
</div>

<script>
const categoryDescriptions = {
    'Subject Teacher': 'Can teach specific subjects across different curriculums and update grades only for those subjects.',
    'Class Teacher': 'Assigned to a specific class. Can view and manage all subjects for students in their class, and can edit student subject selections.',
    'Head Teacher': 'Has overview access to view reports for all classes and students. Cannot update grades directly.'
};

function updateCategoryOptions() {
    const category = document.getElementById('category').value;
    const assignedClassSection = document.getElementById('assignedClassSection');
    const curriculumSubjectsSection = document.getElementById('curriculumSubjectsSection');
    const categoryInfo = document.getElementById('categoryInfo');
    const categoryDescription = document.getElementById('categoryDescription');
    
    // Hide all sections first
    assignedClassSection.classList.add('hidden');
    curriculumSubjectsSection.classList.add('hidden');
    categoryInfo.classList.add('hidden');
    
    if (category) {
        // Show category info
        categoryInfo.classList.remove('hidden');
        categoryDescription.textContent = categoryDescriptions[category];
        
        // Show relevant sections based on category
        if (category === 'Class Teacher') {
            assignedClassSection.classList.remove('hidden');
            curriculumSubjectsSection.classList.remove('hidden');
            document.getElementById('assigned_class_id').required = true;
        } else if (category === 'Subject Teacher') {
            curriculumSubjectsSection.classList.remove('hidden');
            document.getElementById('assigned_class_id').required = false;
        } else if (category === 'Head Teacher') {
            document.getElementById('assigned_class_id').required = false;
        }
    }
}

function toggleCurriculumSubjects(curriculumId) {
    const checkbox = document.getElementById('curriculum_' + curriculumId);
    const subjectsGrid = document.getElementById('subjects_' + curriculumId);
    
    if (checkbox.checked) {
        subjectsGrid.classList.add('active');
    } else {
        subjectsGrid.classList.remove('active');
        // Uncheck all subjects in this curriculum
        const subjectCheckboxes = subjectsGrid.querySelectorAll('input[type="checkbox"]');
        subjectCheckboxes.forEach(cb => cb.checked = false);
    }
}

// Initialize on page load
window.addEventListener('DOMContentLoaded', updateCategoryOptions);
</script>

</body>
</html>