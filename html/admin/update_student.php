<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    header("Location: ../login.php");
    exit;
}

$user_id = (int)($_GET['id'] ?? 0);

/* ---------- FETCH STUDENT ---------- */
$stmt = $pdo->prepare("
    SELECT 
        u.email,
        s.admission_number,
        s.first_name,
        s.last_name,
        s.gender,
        s.status,
        s.curriculum_type_id,
        s.class_level_id
    FROM users u
    LEFT JOIN students s ON u.id = s.user_id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student not found");
}

/* ---------- FETCH CURRICULUM TYPES ---------- */
$curriculums = $pdo->query("
    SELECT id, name FROM curriculum_types ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);

/* ---------- FETCH CLASS LEVELS ---------- */
$classLevels = $pdo->query("
    SELECT id, curriculum_type_id, name 
    FROM classes_levels 
    ORDER BY level_order
")->fetchAll(PDO::FETCH_ASSOC);

/* ---------- GET LINKED PARENTS ---------- */
$linked_parents = [];
if (!empty($student['admission_number'])) {
    $parents_stmt = $pdo->prepare("
        SELECT parents.first_name, parents.last_name, users.email, parents.linked_students
        FROM parents
        JOIN users ON parents.user_id = users.id
        WHERE parents.linked_students LIKE ?
    ");
    $parents_stmt->execute(['%' . $student['admission_number'] . '%']);
    $linked_parents = $parents_stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ---------- UPDATE ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        // Check if student already exists
        $check = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
        $check->execute([$user_id]);
        $exists = $check->fetch();

        if ($exists) {
            // UPDATE existing student
            $stmt = $pdo->prepare("
                UPDATE students SET
                    admission_number = ?,
                    first_name = ?,
                    last_name = ?,
                    gender = ?,
                    status = ?,
                    curriculum_type_id = ?,
                    class_level_id = ?
                WHERE user_id = ?
            ");

            $stmt->execute([
                $_POST['admission_number'],
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['gender'],
                isset($_POST['status']) ? 1 : 0,
                $_POST['curriculum_type_id'],
                $_POST['class_level_id'],
                $user_id
            ]);

        } else {
            // INSERT new student
            $stmt = $pdo->prepare("
                INSERT INTO students (
                    user_id,
                    admission_number,
                    first_name,
                    last_name,
                    gender,
                    status,
                    curriculum_type_id,
                    class_level_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $user_id,
                $_POST['admission_number'],
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['gender'],
                isset($_POST['status']) ? 1 : 0,
                $_POST['curriculum_type_id'],
                $_POST['class_level_id']
            ]);
        }

        header("Location: update_student.php?id=$user_id&success=1");
        exit;

    } catch (PDOException $e) {
        $error = "Error updating student: " . $e->getMessage();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Student</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/update_profile.css">
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
            <h2>Update Student Profile</h2>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert-success">✅ Student updated successfully.</div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

           <form method="POST">
    <label>Admission Number</label>
    <input type="text" name="admission_number" value="<?php echo htmlspecialchars($student['admission_number'] ?? ''); ?>" required>

    <label>First Name</label>
    <input type="text" name="first_name" value="<?php echo htmlspecialchars($student['first_name'] ?? ''); ?>" required>

    <label>Last Name</label>
    <input type="text" name="last_name" value="<?php echo htmlspecialchars($student['last_name'] ?? ''); ?>" required>

    <label>Gender</label>
    <select name="gender" required>
        <option value="">Select Gender</option>
        <option value="Male" <?php echo ($student['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
        <option value="Female" <?php echo ($student['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
    </select>

    <label>Curriculum</label>
    <select name="curriculum_type_id" id="curriculum" onchange="updateClasses()" required>
        <option value="">Select Curriculum</option>
        <?php foreach ($curriculums as $c): ?>
            <option value="<?php echo $c['id']; ?>" <?php echo ($student['curriculum_type_id'] ?? '') == $c['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($c['name']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>Class</label>
    <select name="class_level_id" id="class_level" required>
        <option value="">Select Class</option>
    </select>

    <label style="display: flex; align-items: center; margin-top: 10px;">
        <input type="checkbox" name="status" value="1" 
            <?php echo ($student['status'] ?? 0) ? 'checked' : ''; ?> 
            style="width: auto; margin-right: 10px;">
        Active Student
    </label>

    <button type="submit" style="margin-top: 20px;">Save Changes</button>
</form>

            <?php if (!empty($linked_parents)): ?>
                <div class="linked-info">
                    <h3>👨‍👩‍👧 Linked Parents/Guardians</h3>
                    <ul>
                        <?php foreach ($linked_parents as $parent): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?></strong>
                                <span class="parent-email"><?php echo htmlspecialchars($parent['email']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <div class="linked-info no-parents">
                    <p>⚠️ No parents linked to this student yet.</p>
                </div>
            <?php endif; ?>

            <a href="list_users.php" class="button button-yellow" style="margin-top: 20px;">← Back to Users</a>
        </div>
    </div>
</div>
<script>
const classLevels = <?php echo json_encode($classLevels); ?>;
const selectedClass = <?php echo json_encode($student['class_level_id']); ?>;

console.log('Class Levels:', classLevels);
console.log('Selected Class:', selectedClass);

function updateClasses() {
    const curriculumId = document.getElementById('curriculum').value;
    const classSelect = document.getElementById('class_level');

    console.log('Selected Curriculum ID:', curriculumId);

    // Clear current options
    classSelect.innerHTML = '<option value="">Select Class</option>';

    // Filter and add classes for selected curriculum
    let count = 0;
    classLevels.forEach(cls => {
        console.log('Checking class:', cls.name, 'Curriculum:', cls.curriculum_type_id);
        
        if (cls.curriculum_type_id == curriculumId) {
            const opt = document.createElement('option');
            opt.value = cls.id;
            opt.textContent = cls.name;
            
            // If this was previously selected, select it again
            if (cls.id == selectedClass) {
                opt.selected = true;
            }
            
            classSelect.appendChild(opt);
            count++;
        }
    });

    console.log('Added', count, 'classes to dropdown');
}

// Initialize on page load
window.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded, initializing classes...');
    updateClasses();
});
</script>
</body>
</html>