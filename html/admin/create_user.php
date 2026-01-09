<?php
require_once "../includes/db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role_id = $_POST['role_id']; // admin, teacher, student

    $stmt = $pdo->prepare("
        INSERT INTO users (email, password, role_id)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$email, $password, $role_id]);

    $user_id = $pdo->lastInsertId();

    // Create profile based on role
    if ($role_id == 2) { // teacher
        $pdo->prepare("INSERT INTO teachers (user_id) VALUES (?)")
            ->execute([$user_id]);
    }

    if ($role_id == 3) { // student
        $pdo->prepare("INSERT INTO students (user_id) VALUES (?)")
            ->execute([$user_id]);
    }

    echo "User created successfully";
}
?>

<form method="POST">
    <input type="email" name="email" required>
    <input type="password" name="password" required>
    <select name="role_id">
        <option value="1">Admin</option>
        <option value="2">Teacher</option>
        <option value="3">Student</option>
    </select>
    <button>Create User</button>
</form>
