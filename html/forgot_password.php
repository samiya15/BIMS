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
            // Generate BOTH code and token
            $code = sprintf("%06d", rand(0, 999999)); // 6-digit code with leading zeros
            $token = bin2hex(random_bytes(32)); // Unique token for link
            
            // IMPORTANT: 30 minutes from NOW
            $expires = date("Y-m-d H:i:s", strtotime("+30 minutes"));
            
            // DELETE old unused resets for this user
            $pdo->prepare("DELETE FROM password_resets WHERE user_id = ? AND used = 0")->execute([$user['id']]);
            
            // INSERT new reset with BOTH code and token
            $insert_stmt = $pdo->prepare("
                INSERT INTO password_resets (user_id, token, code, expires_at, used, created_at)
                VALUES (?, ?, ?, ?, 0, NOW())
            ");
            $insert_stmt->execute([$user['id'], $token, $code, $expires]);
            
            // Verify it was inserted
            $verify = $pdo->prepare("SELECT id, code, token FROM password_resets WHERE user_id = ? AND used = 0 ORDER BY created_at DESC LIMIT 1");
            $verify->execute([$user['id']]);
            $inserted = $verify->fetch();
            
            if (!$inserted) {
                error_log("CRITICAL: Failed to insert password reset for user {$user['id']}");
                throw new Exception("Failed to create reset request");
            }
            
            // Log the insert
            error_log("Password reset created: User={$user['id']}, Code={$code}, Expires={$expires}");

            // Create reset link
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;

            // Email body (PLAIN TEXT VERSION)
            $email_body = "Hello,

You requested a password reset for your BIMS account.

You have TWO ways to reset your password:

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
OPTION 1: Use the 6-Digit Code
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Your verification code is: $code

Go to the verification page and enter this code.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
OPTION 2: Click the Reset Link
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Click here to reset your password directly:
$reset_link

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

This link and code will expire in 30 minutes.

If you did not request this password reset, please ignore this email.

---
Nairobi Leadership Academy
BIMS - School Management System";

            // HTML VERSION
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
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1 style='color: #0b1c2d; margin: 0;'>Password Reset Request</h1>
            <p style='color: #666; margin-top: 10px;'>Nairobi Leadership Academy</p>
        </div>
        
        <p>Hello,</p>
        <p>You requested a password reset for your BIMS account. You have <strong>TWO ways</strong> to reset your password:</p>
        
        <h3 style='color: #0b1c2d;'>Option 1: Use the 6-Digit Code</h3>
        <div class='code-box'>
            <p style='margin: 0 0 10px 0; color: #0b1c2d; font-weight: 600;'>Your Verification Code:</p>
            <div class='code-number'>$code</div>
            <p style='margin: 10px 0 0 0; font-size: 12px; color: #333;'>Enter this code on the verification page</p>
        </div>
        
        <h3 style='color: #0b1c2d;'>Option 2: Click the Reset Link</h3>
        <div class='link-box'>
            <p style='margin: 0 0 15px 0;'>Click the button below for instant password reset:</p>
            <a href='$reset_link' class='btn'>Reset Password Now</a>
        </div>
        
        <p style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;'>
            ⏰ <strong>Important:</strong> This code and link will expire in <strong>30 minutes</strong>.
        </p>
        
        <p style='color: #666; font-size: 13px;'>If you did not request this password reset, please ignore this email.</p>
        
        <div class='footer'>
            Nairobi Leadership Academy<br>
            BIMS - School Management System
        </div>
    </div>
</body>
</html>
";
            
            // Send email using email_helper.php
            $email_sent = sendEmail($email, "Password Reset - Code & Link", $email_body, $email_html);
            
            // Store email in session
            $_SESSION['reset_email'] = $email;
            
            // If email failed, store BOTH for display (DEV MODE)
            if (!$email_sent) {
                $_SESSION['reset_code_display'] = $code;
                $_SESSION['reset_link_display'] = $reset_link;
                error_log("Email failed but stored in session: Code=$code, Link=$reset_link");
            }
            
            header("Location: verify_reset_code.php");
            exit;
        } else {
            // Don't reveal if email exists or not (security)
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
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0b1c2d 0%, #1a3a52 50%, #0b1c2d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .forgot-container {
            background: rgba(255, 255, 255, 0.98);
            padding: 50px 45px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            width: 100%;
            max-width: 440px;
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
        }
        h1 {
            font-family: 'Playfair Display', serif;
            color: #0b1c2d;
            text-align: center;
            font-size: 26px;
            margin-bottom: 10px;
        }
        .subtitle { text-align: center; color: #666; font-size: 14px; }
        .divider { height: 1px; background: #e0e0e0; margin: 30px 0; }
        .error {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 10px;
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
        label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; }
        input[type="email"] {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e5e5e5;
            border-radius: 10px;
            font-size: 15px;
            margin-bottom: 25px;
        }
        input[type="email"]:focus {
            outline: none;
            border-color: #f4c430;
            box-shadow: 0 0 0 4px rgba(244, 196, 48, 0.1);
        }
        button {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #0b1c2d 0%, #1a3a52 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        button:hover { transform: translateY(-2px); }
        .help-text {
            text-align: center;
            font-size: 13px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }
        .help-text a {
            color: #0b1c2d;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="icon">🔒</div>
        <h1>Forgot Password?</h1>
        <p class="subtitle">No worries! We'll send you reset options</p>
        
        <div class="divider"></div>
        
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