<?php
session_start();
require_once __DIR__ . "/../database/db_connect.php";
require_once __DIR__ . "/email_helper.php";

$message = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);
    
    // Check if email exists
    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        try {
            // Generate secure token
            $token = bin2hex(random_bytes(32)); // 64 character token
            $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            
            // Store token in database
            $insert = $pdo->prepare("
                INSERT INTO password_reset_tokens (user_id, email, token, expires_at)
                VALUES (?, ?, ?, ?)
            ");
            $insert->execute([$user['id'], $email, $token, $expires_at]);
            
            // Create reset link
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;
            
            // Generate a shorter code for manual entry (6 digits from token)
            $token_code = strtoupper(substr($token, 0, 6));
            
            // Send email (also logs to file automatically)
            $email_sent = sendPasswordResetEmail($email, $reset_link, $token_code);
            
            $message = "✅ Password reset instructions have been sent. Check the file: " . sys_get_temp_dir() . DIRECTORY_SEPARATOR . "password_reset_emails.log";
            
        } catch (PDOException $e) {
            $error = "Error processing request. Please try again.";
        }
    } else {
        // Don't reveal if email exists or not (security)
        $message = "✅ If an account exists with that email, password reset instructions have been sent.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - BIMS</title>
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
        .forgot-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 450px;
        }
        h2 {
            text-align: center;
            color: #0b1c2d;
            margin-bottom: 10px;
        }
        .subtitle {
            text-align: center;
            color: #666;
            font-size: 14px;
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
        input[type="email"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input[type="email"]:focus {
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
        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #28a745;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #dc3545;
        }
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: bold;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
        .info-box {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #0d47a1;
            border-left: 4px solid #2196f3;
        }
        .info-box ul {
            margin-left: 20px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <h2>🔐 Forgot Password?</h2>
        <p class="subtitle">Enter your email address and we'll send you reset instructions</p>
        
        <?php if ($message): ?>
            <div class="success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!$message): ?>
            <form method="POST">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="your-email@school.com" autofocus>
                </div>
                
                <button type="submit">Send Reset Instructions</button>
            </form>
            
            <div class="info-box">
                <strong>ℹ️ What happens next?</strong>
                <ul>
                    <li>You'll receive an email with a reset link</li>
                    <li>The link expires in 30 minutes</li>
                    <li>You can also use the 6-digit code provided</li>
                </ul>
            </div>
        <?php else: ?>
            <div class="info-box">
                <strong>📧 Check your email!</strong>
                <p style="margin-top: 10px;">
                    If you don't see the email in your inbox, please check your spam/junk folder.
                </p>
            </div>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="login.php">← Back to Login</a>
        </div>
    </div>
</body>
</html>