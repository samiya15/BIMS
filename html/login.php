<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

ob_start();
session_start();

// Use your existing database connection
require_once __DIR__ . "/../database/db_connect.php";

$error = '';
$success = '';

// Handle login
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email']) && isset($_POST['password'])) {
    
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    try {
        // Get user from database
        $stmt = $pdo->prepare("
            SELECT users.id, users.password, roles.name AS role
            FROM users
            JOIN roles ON users.role_id = roles.id
            WHERE users.email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug - show what we got
        if (!$user) {
            $error = "No user found with that email";
        } elseif (!password_verify($password, $user['password'])) {
            $error = "Wrong password";
        } else {
            // Login successful!
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            
            $success = "Login successful! Role: " . $user['role'];
            
            // Try redirect
            ob_end_clean();
            
            switch ($user['role']) {
                case 'Admin':
                    header("Location: admin_dashboard.php");
                    exit();
                case 'Teacher':
                    header("Location: teacher_dashboard.php");
                    exit();
                case 'Student':
                    header("Location: student_dashboard.php");
                    exit();
                case 'Parent':
                    header("Location: parent_dashboard.php");
                    exit();
                default:
                    $error = "Unknown role: " . $user['role'];
            }
        }
    } catch(PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: bold;
        }
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }
        button:hover {
            background: #5568d3;
        }
        .error {
            background: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .success {
            background: #efe;
            color: #3c3;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        .debug {
            background: #f0f0f0;
            padding: 10px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 12px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>School Management System</h2>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">
                <?php echo htmlspecialchars($success); ?>
                <br>If you're seeing this, the redirect failed.
                <br><a href="admin_dashboard.php">Click here to go to dashboard</a>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required value="admin@school.com">
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            
            <button type="submit">Login</button>
        </form>
       
    </div>
</body>
</html>