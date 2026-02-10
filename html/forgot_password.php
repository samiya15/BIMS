<?php
session_start();
require_once __DIR__ . "/../database/db_connect.php";
require_once __DIR__ . "/email_helper.php";

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = rand(100000, 999999);
            $expires = date("Y-m-d H:i:s", strtotime("+10 minutes"));

            $pdo->prepare("
                INSERT INTO password_resets (user_id, token, expires_at)
                VALUES (?, ?, ?)
            ")->execute([$user['id'], $token, $expires]);

            $email_body = "Your password reset code is: $token\n\nThis code will expire in 10 minutes.\n\nIf you did not request this, please ignore this email.";
            $email_sent = sendMail($email, "Password Reset Code", $email_body);
            
            // Store email in session
            $_SESSION['reset_email'] = $email;
            
            // If email failed, store code in session for display (DEV MODE ONLY)
            if (!$email_sent) {
                $_SESSION['reset_code_display'] = $token;
                error_log("Email failed. Code: $token for $email");
            }
            
            header("Location: verify_reset_code.php");
            exit;
        } else {
            // Don't reveal if email exists or not (security best practice)
            // Still redirect to verify page
            $_SESSION['reset_email'] = $email;
            header("Location: verify_reset_code.php");
            exit;
        }
    } catch (PDOException $e) {
        $error = "An error occurred. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - BIMS</title>
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
        
        .forgot-container {
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
        
        .forgot-container::before {
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
        
        .header {
            text-align: center;
            margin-bottom: 35px;
        }
        
        .icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, #0b1c2d, #1a3a52);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            box-shadow: 0 8px 20px rgba(11, 28, 45, 0.3);
        }
        
        h1 {
            font-family: 'Playfair Display', serif;
            color: #0b1c2d;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e0e0e0, transparent);
            margin: 30px 0;
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
        
        input[type="email"] {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e5e5e5;
            border-radius: 10px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
            background: #fafafa;
        }
        
        input[type="email"]:focus {
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
        
        .info-box {
            background: #f0f8ff;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 6px;
            font-size: 13px;
            color: #333;
        }
        
        .help-text {
            text-align: center;
            font-size: 13px;
            color: #666;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }
        
        .help-text a {
            color: #0b1c2d;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }
        
        .help-text a:hover {
            color: #f4c430;
        }
        
        @media (max-width: 480px) {
            .forgot-container {
                padding: 40px 30px;
            }
            
            h1 {
                font-size: 22px;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="header">
            <div class="icon">🔒</div>
            <h1>Forgot Password?</h1>
            <p class="subtitle">No worries! We'll send you a reset code</p>
        </div>
        
        <div class="divider"></div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="info-box">
            📧 Enter your email address and we'll send you a 6-digit verification code to reset your password.
        </div>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" 
                       name="email" 
                       placeholder="your.email@school.com"
                       required
                       autofocus>
            </div>
            
            <button type="submit">Send Reset Code</button>
        </form>
        
        <div class="help-text">
            Remember your password? <a href="login.php">Sign in</a>
        </div>
    </div>
</body>
</html>