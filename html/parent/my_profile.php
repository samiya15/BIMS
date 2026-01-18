<?php
session_start();
require_once __DIR__ . "/../../database/db_connect.php";

/* ---------- ACCESS CONTROL ---------- */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Parent') {
    header("Location: ../login.php");
    exit;
}

/* ---------- GET PARENT DATA ---------- */
$stmt = $pdo->prepare("
    SELECT 
        u.email,
        p.id as parent_id,
        p.first_name,
        p.last_name,
        p.phone_number,
        p.residential_area,
        p.relationship,
        p.linked_students
    FROM users u
    JOIN parents p ON u.id = p.user_id
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$parent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$parent) {
    die("Profile not found");
}

/* ---------- GET LINKED STUDENTS ---------- */
$linked_students = [];
if (!empty($parent['linked_students'])) {
    $admission_numbers = explode(',', $parent['linked_students']);
    $placeholders = implode(',', array_fill(0, count($admission_numbers), '?'));
    
    $students_stmt = $pdo->prepare("
        SELECT 
            s.id,
            s.admission_number,
            s.first_name,
            s.last_name,
            s.gender,
            cl.name as class_name,
            ct.name as curriculum_name,
            s.status
        FROM students s
        LEFT JOIN classes_levels cl ON s.class_level_id = cl.id
        LEFT JOIN curriculum_types ct ON cl.curriculum_type_id = ct.id
        WHERE s.admission_number IN ($placeholders)
    ");
    $students_stmt->execute($admission_numbers);
    $linked_students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* ---------- HANDLE UPDATE ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $phone_number = trim($_POST['phone_number'] ?? '');
    $residential_area = trim($_POST['residential_area'] ?? '');
    $relationship = trim($_POST['relationship'] ?? '');
    
    try {
        $stmt = $pdo->prepare("
            UPDATE parents 
            SET phone_number = ?, residential_area = ?, relationship = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$phone_number, $residential_area, $relationship, $_SESSION['user_id']]);
        
        header("Location: my_profile.php?success=1");
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
    <title>My Profile</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/update_profile.css">
</head>
<body>

<div class="sidebar">
    <h2>BIMS Parent</h2>
    <a href="../parent_dashboard.php">Dashboard</a>
    <a href="my_profile.php" class="active">My Profile</a>
    <a href="my_children.php">My Children</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="main-content">
    <div class="container">
        <div class="card">
            <h2>My Profile</h2>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert-success">✅ Profile updated successfully.</div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- READ-ONLY INFO -->
            <div class="profile-section" style="background: #f0f0f0; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3>📋 Basic Information (Cannot be changed)</h3>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
                    <div>
                        <strong>Email:</strong><br>
                        <span style="color: var(--navy);"><?php echo htmlspecialchars($parent['email']); ?></span>
                    </div>
                    <div>
                        <strong>First Name:</strong><br>
                        <span style="color: var(--navy);"><?php echo htmlspecialchars($parent['first_name']); ?></span>
                    </div>
                    <div>
                        <strong>Last Name:</strong><br>
                        <span style="color: var(--navy);"><?php echo htmlspecialchars($parent['last_name']); ?></span>
                    </div>
                </div>
            </div>

            <!-- EDITABLE INFO -->
            <form method="POST">
                <h3 style="color: var(--navy); margin-bottom: 15px;">✏️ Editable Information</h3>
                
                <label>Phone Number</label>
                <input type="tel" name="phone_number" placeholder="+254..." value="<?php echo htmlspecialchars($parent['phone_number'] ?? ''); ?>">

                <label>Residential Area</label>
                <input type="text" name="residential_area" placeholder="e.g., Nairobi, Westlands" value="<?php echo htmlspecialchars($parent['residential_area'] ?? ''); ?>">

                <label>Relationship to Student</label>
                <select name="relationship">
                    <option value="">Select Relationship</option>
                    <option value="Father" <?php echo ($parent['relationship'] ?? '') == 'Father' ? 'selected' : ''; ?>>Father</option>
                    <option value="Mother" <?php echo ($parent['relationship'] ?? '') == 'Mother' ? 'selected' : ''; ?>>Mother</option>
                    <option value="Guardian" <?php echo ($parent['relationship'] ?? '') == 'Guardian' ? 'selected' : ''; ?>>Guardian</option>
                    <option value="Other" <?php echo ($parent['relationship'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                </select>

                <button type="submit" style="margin-top: 20px;">Update Profile</button>
            </form>

            <!-- LINKED STUDENTS -->
            <?php if (!empty($linked_students)): ?>
                <div class="linked-info" style="margin-top: 30px;">
                    <h3>👨‍👩‍👧 My Children/Wards</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; margin-top: 15px;">
                        <?php foreach ($linked_students as $student): ?>
                            <div style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid var(--yellow);">
                                <h4 style="color: var(--navy); margin-bottom: 10px;">
                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                </h4>
                                <p style="margin: 5px 0; color: #666;">
                                    <strong>Admission No:</strong> <?php echo htmlspecialchars($student['admission_number']); ?>
                                </p>
                                <p style="margin: 5px 0; color: #666;">
                                    <strong>Class:</strong> <?php echo htmlspecialchars($student['curriculum_name'] . ' - ' . $student['class_name']); ?>
                                </p>
                                <p style="margin: 5px 0; color: #666;">
                                    <strong>Gender:</strong> <?php echo htmlspecialchars($student['gender']); ?>
                                </p>
                                <p style="margin:5px 0;">
                                <span style="display: inline-block; background: <?php echo $student['status'] ? '#4caf50' : '#f44336'; ?>; color: white; padding: 4px 10px; border-radius: 12px; font-size: 12px;">
<?php echo $student['status'] ? 'Active' : 'Inactive'; ?>
</span>
</p>
</div>
<?php endforeach; ?>
</div>
</div>
<?php else: ?>
<div class="linked-info no-parents" style="margin-top: 30px;">
<p>⚠️ No children linked yet. Please contact the school admin to link your child's admission number.</p>
</div>
<?php endif; ?>
</div>
</div>
</div>
</body>
</html>
