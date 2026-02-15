<?php
session_start();
require_once __DIR__ . "/../database/db_connect.php";
require_once __DIR__ . "/email_helper.php";

$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST['email']);

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate BOTH code and token
            $code = sprintf("%06d", rand(0, 999999));
            $token = bin2hex(random_bytes(32));
            $expires = date("Y-m-d H:i:s", strtotime("+30 minutes"));
            
            // Delete old resets
            $pdo->prepare("DELETE FROM password_resets WHERE user_id = ? AND used = 0")->execute([$user['id']]);
            
            // Insert new reset
            $pdo->prepare("
                INSERT INTO password_resets (user_id, token, code, expires_at, used, created_at)
                VALUES (?, ?, ?, ?, 0, NOW())
            ")->execute([$user['id'], $token, $code, $expires]);

            // Create reset link
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;

            // Email body
            $email_body = "Hello,

You requested a password reset for your BIMS account.

You have TWO ways to reset your password:

OPTION 1: Use the 6-Digit Code
Your verification code is: $code
Go to the verification page and enter this code.

OPTION 2: Click the Reset Link
Click here to reset your password directly:
$reset_link

This link and code will expire in 30 minutes.

---
Nairobi Leadership Academy
BIMS - School Management System";

            // HTML version
            $email_html = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
        .header { text-align: center; border-bottom: 3px solid #0b1c2d; padding-bottom: 20px; margin-bottom: 30px; }
        .code-box { background: #f4c430; padding: 20px; border-radius: 8px; text-align: center; margin: 20px 0; }
        .code-number { font-size: 36px; font-weight: bold; letter-spacing: 8px; color: #0b1c2d; }
        .link-box { background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; }
        .btn { display: inline-block; background: #0b1c2d; color: white; padding: 15px 30px; text-decoration: none; border-radius: 6px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1 style='color: #0b1c2d; margin: 0;'>Password Reset Request</h1>
            <p style='color: #666; margin-top: 10px;'>Nairobi Leadership Academy</p>
        </div>
        
        <p>Hello,</p>
        <p>You requested a password reset. You have <strong>TWO ways</strong> to reset your password:</p>
        
        <h3 style='color: #0b1c2d;'>Option 1: Use the 6-Digit Code</h3>
        <div class='code-box'>
            <p style='margin: 0 0 10px 0; color: #0b1c2d; font-weight: 600;'>Your Verification Code:</p>
            <div class='code-number'>$code</div>
        </div>
        
        <h3 style='color: #0b1c2d;'>Option 2: Click the Reset Link</h3>
        <div class='link-box'>
            <p style='margin: 0 0 15px 0;'>Click the button below for instant password reset:</p>
            <a href='$reset_link' class='btn'>Reset Password Now</a>
        </div>
        
        <p style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;'>
            ⏰ <strong>Important:</strong> This code and link will expire in <strong>30 minutes</strong>.
        </p>
    </div>
</body>
</html>
";
            
            // Send email
            $email_sent = sendEmail($email, "Password Reset - Code & Link", $email_body, $email_html);
            
            // Store in session
            $_SESSION['reset_email'] = $email;
            
            // Dev mode: show code/link if email fails
            if (!$email_sent) {
                $_SESSION['reset_code_display'] = $code;
                $_SESSION['reset_link_display'] = $reset_link;
            }
            
            header("Location: verify_reset_code.php");
            exit;
        } else {
            $_SESSION['reset_email'] = $email;
            header("Location: verify_reset_code.php");
            exit;
        }
    } catch (Exception $e) {
        error_log("Password reset error: " . $e->getMessage());
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #0b1c2d 0%, #1a3a52 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            width: 100%;
            max-width: 420px;
        }
        h1 { color: #0b1c2d; text-align: center; margin-bottom: 10px; }
        .subtitle { text-align: center; color: #666; margin-bottom: 30px; font-size: 14px; }
        .error {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #c33;
        }
        .info-box {
            background: #f0f8ff;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 6px;
            font-size: 13px;
        }
        label { display: block; margin-bottom: 8px; font-weight: 600; }
        input[type="email"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            margin-bottom: 20px;
        }
        input[type="email"]:focus {
            outline: none;
            border-color: #f4c430;
        }
        button {
            width: 100%;
            padding: 14px;
            background: #0b1c2d;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        button:hover { background: #1a3a52; }
        .help-text {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
        }
        .help-text a { color: #0b1c2d; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔒 Forgot Password?</h1>
        <p class="subtitle">No worries! We'll send you reset options</p>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="info-box">
            📧 You'll receive an email with:<br>
            • A 6-digit code you can enter<br>
            • A clickable link for instant reset
        </div>
        
        <form method="POST">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="your.email@school.com" required autofocus>
            <button type="submit">Send Reset Options</button>
        </form>
        
        <div class="help-text">
            Remember your password? <a href="login.php">Sign in</a>
        </div>
    </div>
</body>
</html>