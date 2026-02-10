<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

ob_start();
session_start();

require_once __DIR__ . "/../database/db_connect.php";

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email']) && isset($_POST['password'])) {
    
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT users.id, users.password, roles.name AS role
            FROM users
            JOIN roles ON users.role_id = roles.id
            WHERE users.email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $error = "No user found with that email";
        } elseif (!password_verify($password, $user['password'])) {
            $error = "Incorrect password";
        } else {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            
            $success = "Login successful! Role: " . $user['role'];
            
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
    <title>BIMS - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0b1c2d 0%, #1a3a52 50%, #0b1c2d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        /* Elegant background pattern */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(244, 196, 48, 0.03) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(244, 196, 48, 0.03) 0%, transparent 50%);
            pointer-events: none;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            padding: 50px 45px;
            border-radius: 20px;
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.4),
                0 0 0 1px rgba(255, 255, 255, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
            width: 100%;
            max-width: 440px;
            position: relative;
            animation: slideUp 0.6s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Gold accent line */
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, #f4c430, #ddb300, #f4c430);
            border-radius: 0 0 2px 2px;
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 35px;
        }
        
        .logo {
            width: 70px;
            height: 70px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #0b1c2d, #1a3a52);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #f4c430;
            font-weight: 700;
            font-size: 28px;
            font-family: 'Playfair Display', serif;
            box-shadow: 
                0 8px 20px rgba(11, 28, 45, 0.3),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            position: relative;
        }
        
        .logo::after {
            content: '';
            position: absolute;
            inset: -3px;
            border-radius: 50%;
            border: 2px solid transparent;
            background: linear-gradient(45deg, #f4c430, transparent, #f4c430) border-box;
            -webkit-mask: linear-gradient(#fff 0 0) padding-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            opacity: 0.4;
        }
        
        .school-name {
            font-family: 'Playfair Display', serif;
            color: #0b1c2d;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }
        
        .school-subtitle {
            color: #666;
            font-size: 13px;
            font-weight: 400;
            letter-spacing: 2px;
            text-transform: uppercase;
        }
        
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e0e0e0, transparent);
            margin: 30px 0;
        }
        
        .welcome-text {
            text-align: center;
            color: #333;
            font-size: 15px;
            margin-bottom: 30px;
            font-weight: 400;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
            letter-spacing: 0.3px;
        }
        
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e5e5e5;
            border-radius: 10px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
            background: #fafafa;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #f4c430;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(244, 196, 48, 0.1);
        }
        
        button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #0b1c2d 0%, #1a3a52 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            font-size: 14px;
            box-shadow: 0 4px 15px rgba(11, 28, 45, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(244, 196, 48, 0.3), transparent);
            transition: left 0.5s ease;
        }
        
        button:hover::before {
            left: 100%;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(11, 28, 45, 0.4);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .error {
            background: linear-gradient(135deg, #fee, #fdd);
            color: #c33;
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            border-left: 4px solid #c33;
            animation: shake 0.5s ease;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .success {
            background: linear-gradient(135deg, #efe, #dfd);
            color: #3c3;
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            border-left: 4px solid #3c3;
        }
        
        footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #999;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }
        
        .forgot-password {
            text-align: right;
            margin-top: 10px;
        }
        
        .forgot-password a {
            color: #0b1c2d;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .forgot-password a:hover {
            color: #f4c430;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                padding: 40px 30px;
                max-width: 100%;
            }
            
            .school-name {
                font-size: 22px;
            }
            
            .logo {
                width: 60px;
                height: 60px;
                font-size: 24px;
            }
        }
        
        @media (max-width: 360px) {
            .login-container {
                padding: 35px 25px;
            }
            
            input[type="email"],
            input[type="password"] {
                padding: 12px 15px;
                font-size: 14px;
            }
            
            button {
                padding: 13px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-section">
            <div class="logo">NLA</div>
            <h1 class="school-name">Nairobi Leadership Academy</h1>
            <p class="school-subtitle">Building Leaders of Tomorrow</p>
        </div>
        
        <div class="divider"></div>
        
        <p class="welcome-text">Please sign in to access your account</p>
        
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
                <label>Email Address</label>
                <input type="email" name="email" required value="admin@school.com" placeholder="your.email@school.com">
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Enter your password">
            </div>
            
            <div class="forgot-password">
                <a href="forgot_password.php">Forgot Password?</a>
            </div>
            
            <button type="submit">Sign In</button>
        </form>
        
        <footer>
            &copy; <?php echo date("Y"); ?> Nairobi Leadership Academy. All rights reserved.
        </footer>
    </div>
</body>
</html>