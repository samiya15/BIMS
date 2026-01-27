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
        s.id as student_id,
        s.admission_number,
        s.first_name,
        s.last_name,
        s.gender,
        s.status,
        s.curriculum_type_id,
        s.class_level_id,
        s.phone_number,
        s.residential_area,
        s.date_of_birth,
        s.parent_phone,
        s.parent_email,
        s.year_of_enrollment
    FROM users u
    LEFT JOIN students s ON u.id = s.user_id
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student not found");
}

// FIX: Check if student row exists, if not we need to wait for POST to create it
if (empty($student['student_id'])) {
    // Student profile doesn't exist yet - show form to create it
    $student['admission_number'] = '';
    $student['first_name'] = '';
    $student['last_name'] = '';
    $student['gender'] = '';
    $student['status'] = 1;
    $student['curriculum_type_id'] = '';
    $student['class_level_id'] = '';
    $student['phone_number'] = '';
    $student['residential_area'] = '';
    $student['date_of_birth'] = '';
    $student['parent_phone'] = '';
    $student['parent_email'] = '';
    $student['year_of_enrollment'] = date('Y');
    $is_new_student = true;
} else {
    $is_new_student = false;
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

/* ---------- FETCH STUDENT'S SUBJECTS ---------- */
$student_subjects = [];
if (!$is_new_student) {
    $student_subjects_stmt = $pdo->prepare("
        SELECT subject_name FROM student_subjects WHERE student_id = ?
    ");
    $student_subjects_stmt->execute([$student['student_id']]);
    $student_subjects = $student_subjects_stmt->fetchAll(PDO::FETCH_COLUMN);
}

/* ---------- FETCH AVAILABLE SUBJECTS FOR STUDENT'S CURRICULUM ---------- */
$available_subjects = [];
if ($student['curriculum_type_id']) {
    $subj_stmt = $pdo->prepare("
        SELECT subject_name, is_core 
        FROM curriculum_subjects 
        WHERE curriculum_type_id = ? 
        ORDER BY is_core DESC, subject_name
    ");
    $subj_stmt->execute([$student['curriculum_type_id']]);
    $available_subjects = $subj_stmt->fetchAll(PDO::FETCH_ASSOC);
}

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
        // FIX: If this is a new student, create the student record first
        if ($is_new_student) {
            $createStmt = $pdo->prepare("
                INSERT INTO students (
                    user_id,
                    admission_number,
                    first_name,
                    last_name,
                    gender,
                    status,
                    curriculum_type_id,
                    class_level_id,
                    phone_number,
                    residential_area,
                    date_of_birth,
                    parent_phone,
                    parent_email,
                    year_of_enrollment
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $createStmt->execute([
                $user_id,
                $_POST['admission_number'],
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['gender'],
                isset($_POST['status']) ? 1 : 0,
                $_POST['curriculum_type_id'],
                $_POST['class_level_id'],
                $_POST['phone_number'] ?? null,
                $_POST['residential_area'] ?? null,
                $_POST['date_of_birth'] ?? null,
                $_POST['parent_phone'] ?? null,
                $_POST['parent_email'] ?? null,
                $_POST['year_of_enrollment'] ?? date('Y')
            ]);

            // Get the new student_id
            $student['student_id'] = $pdo->lastInsertId();
        } else {
            // Update existing student basic info
            $stmt = $pdo->prepare("
                UPDATE students SET
                    admission_number = ?,
                    first_name = ?,
                    last_name = ?,
                    gender = ?,
                    status = ?,
                    curriculum_type_id = ?,
                    class_level_id = ?,
                    phone_number = ?,
                    residential_area = ?,
                    date_of_birth = ?,
                    parent_phone = ?,
                    parent_email = ?,
                    year_of_enrollment = ?
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
                $_POST['phone_number'] ?? null,
                $_POST['residential_area'] ?? null,
                $_POST['date_of_birth'] ?? null,
                $_POST['parent_phone'] ?? null,
                $_POST['parent_email'] ?? null,
                $_POST['year_of_enrollment'] ?? null,
                $user_id
            ]);
        }
        
        // Update student subjects
        // Delete old subjects
        $pdo->prepare("DELETE FROM student_subjects WHERE student_id = ?")->execute([$student['student_id']]);
        
        // Insert new subjects
        if (!empty($_POST['subjects'])) {
            $insert_stmt = $pdo->prepare("INSERT INTO student_subjects (student_id, subject_name) VALUES (?, ?)");
            foreach ($_POST['subjects'] as $subject) {
                $insert_stmt->execute([$student['student_id'], $subject]);
            }
        }

        header("Location: update_student.php?id=$user_id&success=1");
        exit;
    } catch (PDOException $e) {
        $error = "Error updating student: " . $e->getMessage();
    }
}

// Get all subjects for the selected curriculum (for JavaScript)
$all_curriculum_subjects = [];
foreach ($curriculums as $curr) {
    $subj_stmt = $pdo->prepare("
        SELECT subject_name, is_core 
        FROM curriculum_subjects 
        WHERE curriculum_type_id = ? 
        ORDER BY is_core DESC, subject_name
    ");
    $subj_stmt->execute([$curr['id']]);
    $all_curriculum_subjects[$curr['id']] = $subj_stmt->fetchAll(PDO::FETCH_ASSOC);
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
        .subject-item.core {
            font-weight: 600;
            color: var(--navy);
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
        .new-student-notice {
            background: #e3f2fd;
            color: #1976d2;
            padding: 15px;
            border-left: 4px solid #2196f3;
            border-radius: 6px;
            margin-bottom: 20px;
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
            <h2><?php echo $is_new_student ? 'Create' : 'Update'; ?> Student Profile</h2>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?> <em>(Cannot be changed)</em></p>

            <?php if ($is_new_student): ?>
                <div class="new-student-notice">
                    ℹ️ This student account exists but has no profile yet. Please fill in the details below to create the profile.
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert-success">✅ Student <?php echo $is_new_student ? 'created' : 'updated'; ?> successfully.</div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <!-- BASIC INFO -->
                <div class="profile-section">
                    <h3>📋 Basic Information <?php echo !$is_new_student ? '(Cannot be changed after creation)' : ''; ?></h3>
                    
                    <label>Admission Number <?php echo !$is_new_student ? '<em>(Cannot be changed)</em>' : '<em style="color:red">*Required</em>'; ?></label>
                    <input type="text" name="admission_number" 
                           value="<?php echo htmlspecialchars($student['admission_number']); ?>" 
                           <?php echo !$is_new_student ? 'readonly style="background: #f0f0f0;"' : ''; ?>
                           required>

                    <label>First Name <?php echo !$is_new_student ? '<em>(Cannot be changed)</em>' : '<em style="color:red">*Required</em>'; ?></label>
                    <input type="text" name="first_name" 
                           value="<?php echo htmlspecialchars($student['first_name']); ?>" 
                           <?php echo !$is_new_student ? 'readonly style="background: #f0f0f0;"' : ''; ?>
                           required>

                    <label>Last Name <?php echo !$is_new_student ? '<em>(Cannot be changed)</em>' : '<em style="color:red">*Required</em>'; ?></label>
                    <input type="text" name="last_name" 
                           value="<?php echo htmlspecialchars($student['last_name']); ?>" 
                           <?php echo !$is_new_student ? 'readonly style="background: #f0f0f0;"' : ''; ?>
                           required>
                </div>

                <!-- EDITABLE PROFILE INFO -->
                <div class="profile-section">
                    <h3>✏️ Personal Details (Editable)</h3>
                    
                    <label>Gender <em style="color:red">*Required</em></label>
                    <select name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male" <?php echo ($student['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($student['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                    </select>

                    <label>Phone Number</label>
                    <input type="tel" name="phone_number" placeholder="+254..." value="<?php echo htmlspecialchars($student['phone_number'] ?? ''); ?>">

                    <label>Residential Area</label>
                    <input type="text" name="residential_area" placeholder="e.g., Nairobi, Westlands" value="<?php echo htmlspecialchars($student['residential_area'] ?? ''); ?>">

                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($student['date_of_birth'] ?? ''); ?>">

                    <label>Parent Phone Number</label>
                    <input type="tel" name="parent_phone" placeholder="+254..." value="<?php echo htmlspecialchars($student['parent_phone'] ?? ''); ?>">

                    <label>Parent Email</label>
                    <input type="email" name="parent_email" placeholder="parent@email.com" value="<?php echo htmlspecialchars($student['parent_email'] ?? ''); ?>">
                </div>

                <!-- ACADEMIC INFO -->
                <label>Curriculum <em style="color:red">*Required</em></label>
                <select name="curriculum_type_id" id="curriculum" onchange="updateClassesAndSubjects()" required>
                    <option value="">Select Curriculum</option>
                    <?php foreach ($curriculums as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($student['curriculum_type_id'] ?? '') == $c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Year of Enrollment <em style="color:red">*Required</em></label>
                <select name="year_of_enrollment" required>
                    <option value="">Select Year</option>
                    <?php 
                    $current_year = date('Y');
                    for ($year = $current_year; $year >= $current_year - 10; $year--): 
                    ?>
                        <option value="<?php echo $year; ?>" <?php echo ($student['year_of_enrollment'] ?? '') == $year ? 'selected' : ''; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <p class="help-text">The year this student first enrolled in the school.</p>

                <label>Class <em style="color:red">*Required</em></label>
                <select name="class_level_id" id="class_level" required>
                    <option value="">Select Class</option>
                </select>

                <!-- SUBJECTS -->
                <div id="subjectsSection" style="<?php echo empty($available_subjects) ? 'display: none;' : ''; ?>">
                    <label>Subjects</label>
                    <p class="help-text">Select the subjects this student is studying.</p>
                    <div id="subjectsGrid" class="subject-grid">
                        <!-- Subjects will be loaded here by JavaScript -->
                    </div>
                </div>

                <label style="display: flex; align-items: center; margin-top: 10px;">
                    <input type="checkbox" name="status" value="1" 
                        <?php echo ($student['status'] ?? 0) ? 'checked' : ''; ?> 
                        style="width: auto; margin-right: 10px;">
                    Active Student
                </label>

                <button type="submit" style="margin-top: 20px;">Save Changes</button>
            </form>

            <?php if (!$is_new_student && !empty($linked_parents)): ?>
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
            <?php elseif (!$is_new_student): ?>
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
const allCurriculumSubjects = <?php echo json_encode($all_curriculum_subjects); ?>;
const studentSubjects = <?php echo json_encode($student_subjects); ?>;

function updateClassesAndSubjects() {
    const curriculumId = document.getElementById('curriculum').value;
    const classSelect = document.getElementById('class_level');
    const subjectsSection = document.getElementById('subjectsSection');
    const subjectsGrid = document.getElementById('subjectsGrid');

    // Update classes
    classSelect.innerHTML = '<option value="">Select Class</option>';
    classLevels.forEach(cls => {
        if (cls.curriculum_type_id == curriculumId) {
            const opt = document.createElement('option');
            opt.value = cls.id;
            opt.textContent = cls.name;
            if (cls.id == selectedClass) opt.selected = true;
            classSelect.appendChild(opt);
        }
    });
    
    // Update subjects
    if (curriculumId && allCurriculumSubjects[curriculumId]) {
        subjectsSection.style.display = 'block';
        subjectsGrid.innerHTML = '';
        
        allCurriculumSubjects[curriculumId].forEach(subject => {
            const div = document.createElement('div');
            div.className = 'subject-item' + (subject.is_core ? ' core' : '');
            
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.name = 'subjects[]';
            checkbox.value = subject.subject_name;
            checkbox.id = 'subject_' + subject.subject_name.replace(/\s+/g, '_');
            checkbox.checked = studentSubjects.includes(subject.subject_name);
            
            const label = document.createElement('label');
            label.htmlFor = checkbox.id;
            label.style.margin = '0';
            label.textContent = subject.subject_name;
            
            if (subject.is_core) {
                const coreSpan = document.createElement('span');
                coreSpan.style.color = '#d32f2f';
                coreSpan.style.fontSize = '11px';
                coreSpan.textContent = ' (Core)';
                label.appendChild(coreSpan);
            }
            
            div.appendChild(checkbox);
            div.appendChild(label);
            subjectsGrid.appendChild(div);
        });
    } else {
        subjectsSection.style.display = 'none';
    }
}

// Initialize on page load
window.addEventListener('DOMContentLoaded', updateClassesAndSubjects);
</script>

</body>
</html>