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
               teachers.last_name, teachers.category, teachers.assigned_class_id
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
    
    // Get teacher's subjects
    $subjects_stmt = $pdo->prepare("SELECT subject_name FROM teacher_subjects WHERE teacher_id = ?");
    $subjects_stmt->execute([$teacher['teacher_id']]);
    $teacher_subjects = $subjects_stmt->fetchAll(PDO::FETCH_COLUMN);
    
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
    $subjects = isset($_POST['subjects']) ? $_POST['subjects'] : [];
    
    try {
        // Update teacher info
        $stmt = $pdo->prepare("
            UPDATE teachers 
            SET first_name = ?, last_name = ?, category = ?, assigned_class_id = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$first_name, $last_name, $category, $assigned_class_id, $user_id]);
        
        // Update subjects (only for Subject Teachers and Class Teachers)
        if ($category != 'Head Teacher') {
            // Delete old subjects
            $pdo->prepare("DELETE FROM teacher_subjects WHERE teacher_id = ?")->execute([$teacher['teacher_id']]);
            
            // Insert new subjects
            if (!empty($subjects)) {
                $insert_stmt = $pdo->prepare("INSERT INTO teacher_subjects (teacher_id, subject_name) VALUES (?, ?)");
                foreach ($subjects as $subject) {
                    $insert_stmt->execute([$teacher['teacher_id'], $subject]);
                }
            }
        }
        
        header("Location: update_teacher.php?id=" . $user_id . "&success=1");
        exit;
        
    } catch (PDOException $e) {
        $error = "Error updating profile: " . $e->getMessage();
    }
}

// Available subjects list
$available_subjects = [
    'Mathematics', 'English', 'Kiswahili', 'Science', 'Social Studies',
    'CRE', 'IRE', 'HRE', 'Agriculture', 'Business Studies',
    'Home Science', 'Art & Design', 'Music', 'Physical Education'
];
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
        .subject-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 6px;
        }
        .subject-item {
            display: flex;
            align-items: center;
        }
        .subject-item input[type="checkbox"] {
            width: auto;
            margin-right: 8px;
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
            <p><strong>Email:</strong> <?php echo htmlspecialchars($teacher['email']); ?></p>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert-success">✅ Profile updated successfully.</div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" id="teacherForm">
                <label>First Name</label>
                <input type="text" name="first_name" required value="<?php echo htmlspecialchars($teacher['first_name'] ?? ''); ?>">

                <label>Last Name</label>
                <input type="text" name="last_name" required value="<?php echo htmlspecialchars($teacher['last_name'] ?? ''); ?>">

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

                <!-- Subjects (for Subject Teachers and Class Teachers) -->
                <div id="subjectsSection" class="hidden">
                    <label>Subjects Teaching</label>
                    <div class="subject-grid">
                        <?php foreach ($available_subjects as $subject): ?>
                            <div class="subject-item">
                                <input type="checkbox" 
                                       name="subjects[]" 
                                       value="<?php echo htmlspecialchars($subject); ?>"
                                       id="subject_<?php echo str_replace(' ', '_', $subject); ?>"
                                       <?php echo in_array($subject, $teacher_subjects) ? 'checked' : ''; ?>>
                                <label for="subject_<?php echo str_replace(' ', '_', $subject); ?>" style="margin: 0;">
                                    <?php echo htmlspecialchars($subject); ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" style="margin-top: 20px;">Update Profile</button>
            </form>

            <a href="list_users.php" class="button button-yellow" style="margin-top: 20px;">← Back to Users</a>
        </div>
    </div>
</div>

<script>
const categoryDescriptions = {
    'Subject Teacher': 'Can teach specific subjects and update grades only for those subjects.',
    'Class Teacher': 'Assigned to a specific class. Can view and manage all subjects for students in their class.',
    'Head Teacher': 'Has overview access to view reports for all classes and students. Cannot update grades.'
};

function updateCategoryOptions() {
    const category = document.getElementById('category').value;
    const assignedClassSection = document.getElementById('assignedClassSection');
    const subjectsSection = document.getElementById('subjectsSection');
    const categoryInfo = document.getElementById('categoryInfo');
    const categoryDescription = document.getElementById('categoryDescription');
    
    // Hide all sections first
    assignedClassSection.classList.add('hidden');
    subjectsSection.classList.add('hidden');
    categoryInfo.classList.add('hidden');
    
    if (category) {
        // Show category info
        categoryInfo.classList.remove('hidden');
        categoryDescription.textContent = categoryDescriptions[category];
        
        // Show relevant sections based on category
        if (category === 'Class Teacher') {
            assignedClassSection.classList.remove('hidden');
            subjectsSection.classList.remove('hidden');
            document.getElementById('assigned_class_id').required = true;
        } else if (category === 'Subject Teacher') {
            subjectsSection.classList.remove('hidden');
            document.getElementById('assigned_class_id').required = false;
        } else if (category === 'Head Teacher') {
            document.getElementById('assigned_class_id').required = false;
        }
    }
}

// Initialize on page load
window.addEventListener('DOMContentLoaded', updateCategoryOptions);
</script>

</body>
</html>