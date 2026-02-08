<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

ob_start();
session_start();

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
        
        if (!$user) {
            $error = "Invalid email or password";
        } elseif (!password_verify($password, $user['password'])) {
            $error = "Invalid email or password";
        } else {
            // Login successful!
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            
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
    <title>Login - BIMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #0b1c2d 0%, #1a3a52 50%, #0b1c2d 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        /* Animated background particles */
        body::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 50%, rgba(244, 196, 48, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(244, 196, 48, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 20%, rgba(244, 196, 48, 0.05) 0%, transparent 50%);
            animation: particle-animation 15s ease-in-out infinite;
        }
        
        @keyframes particle-animation {
            0%, 100% { opacity: 1; transform: translateY(0px); }
            50% { opacity: 0.8; transform: translateY(-20px); }
        }
        
        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 450px;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.98);
            padding: 50px 40px;
            border-radius: 20px;
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.3),
                0 0 100px rgba(244, 196, 48, 0.1);
            backdrop-filter: blur(10px);
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
        
        .logo-section {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo-circle {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #0b1c2d 0%, #1a3a52 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 30px rgba(11, 28, 45, 0.3);
            position: relative;
            overflow: hidden;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .logo-circle::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, transparent 0%, rgba(244, 196, 48, 0.2) 100%);
            animation: rotate 3s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .logo-text {
            position: relative;
            z-index: 1;
            color: #f4c430;
            font-weight: 700;
            font-size: 36px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        h1 {
            color: #0b1c2d;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #666;
            font-size: 14px;
            font-weight: 400;
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            color: #0b1c2d;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
            transition: color 0.3s;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 18px;
            transition: color 0.3s;
        }
        
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #f4c430;
            background: white;
            box-shadow: 0 0 0 4px rgba(244, 196, 48, 0.1);
        }
        
        input[type="email"]:focus + .input-icon,
        input[type="password"]:focus + .input-icon {
            color: #f4c430;
        }
        
        .forgot-password {
            text-align: right;
            margin: -10px 0 20px 0;
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
        
        button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #f4c430 0%, #e6b800 100%);
            color: #0b1c2d;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(244, 196, 48, 0.3);
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
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }
        
        button:hover::before {
            left: 100%;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(244, 196, 48, 0.4);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.4s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border-left: 4px solid #c33;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
            border-left: 4px solid #3c3;
        }
        
        .divider {
            text-align: center;
            margin: 30px 0;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e0e0e0;
        }
        
        .divider span {
            background: white;
            padding: 0 15px;
            color: #999;
            font-size: 13px;
            position: relative;
            z-index: 1;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .footer p {
            color: #999;
            font-size: 12px;
            margin: 5px 0;
        }
        
        .footer strong {
            color: #0b1c2d;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                padding: 40px 25px;
            }
            
            h1 {
                font-size: 24px;
            }
            
            .logo-circle {
                width: 80px;
                height: 80px;
            }
            
            .logo-text {
                font-size: 28px;
            }
        }
        
        /* Loading animation for button */
        button.loading {
            pointer-events: none;
            opacity: 0.7;
        }
        
        button.loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            top: 50%;
            left: 50%;
            margin-left: -8px;
            margin-top: -8px;
            border: 2px solid #0b1c2d;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spinner 0.6s linear infinite;
        }
        
        @keyframes spinner {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <div class="logo-section">
                <div class="logo-circle">
                    <span class="logo-text">BIMS</span>
                </div>
                <h1>Welcome Back</h1>
                <p class="subtitle">Sign in to access your dashboard</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <span>⚠️</span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <span>✅</span>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <input type="email" 
                               id="email" 
                               name="email" 
                               required 
                               placeholder="your-email@school.com" 
                               autofocus>
                        <span class="input-icon">📧</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               required 
                               placeholder="Enter your password">
                        <span class="input-icon">🔒</span>
                    </div>
                </div>
                
                <div class="forgot-password">
                    <a href="forgot_password.php">Forgot Password?</a>
                </div>
                
                <button type="submit" id="loginBtn">
                    Sign In
                </button>
            </form>
            
            <div class="footer">
                <p><strong>BIMS</strong> - Integrated Management System</p>
                <p>&copy; <?php echo date('Y'); ?> All rights reserved</p>
            </div>
        </div>
    </div>
    
    <script>
        // Add loading state to button on submit
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.classList.add('loading');
            btn.textContent = '';
        });
        
        // Add floating label effect
        const inputs = document.querySelectorAll('input[type="email"], input[type="password"]');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.parentElement.querySelector('label').style.color = '#f4c430';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.parentElement.querySelector('label').style.color = '#0b1c2d';
            });
        });
    </script>
</body>
</html>