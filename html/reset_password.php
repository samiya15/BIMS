<?php
session_start();
require_once __DIR__ . "/../database/db_connect.php";

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$valid_token = false;
$user_email = '';

// Verify token
if ($token) {
    $stmt = $pdo->prepare("
        SELECT prt.*, u.email
        FROM password_reset_tokens prt
        JOIN users u ON prt.user_id = u.id
        WHERE prt.token = ? 
            AND prt.used = 0 
            AND prt.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($token_data) {
        $valid_token = true;
        $user_email = $token_data['email'];
    } else {
        $error = "Invalid or expired reset token. Please request a new password reset.";
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && $valid_token) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate passwords
    if (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update->execute([$hashed_password, $token_data['user_id']]);
            
            // Mark token as used
            $mark_used = $pdo->prepare("UPDATE password_reset_tokens SET used = 1, used_at = NOW() WHERE id = ?");
            $mark_used->execute([$token_data['id']]);
            
            $pdo->commit();
            
            $success = true;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error updating password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - BIMS</title>
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
        .reset-container {
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
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.3s;
        }
        button:hover {
            background: #218838;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
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
            background: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        .user-badge {
            background: #e3f2fd;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            margin-bottom: 20px;
            color: #0d47a1;
            font-weight: bold;
        }
        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .password-strength {
            height: 5px;
            background: #ddd;
            border-radius: 3px;
            margin-top: 5px;
            overflow: hidden;
        }
        .password-strength-bar {
            height: 100%;
            transition: width 0.3s, background 0.3s;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <?php if ($success): ?>
            <h2>✅ Password Reset Successful!</h2>
            <div class="success">
                <p style="font-size: 18px; margin-bottom: 10px;">Your password has been updated!</p>
                <p>You can now login with your new password.</p>
            </div>
            <div class="back-link">
                <a href="login.php">→ Go to Login Page</a>
            </div>
        
        <?php elseif (!$valid_token): ?>
            <h2>⚠️ Invalid Reset Link</h2>
            <div class="error">
                <?php echo $error ?: "This password reset link is invalid or has expired."; ?>
            </div>
            <div class="info-box">
                <strong>What can you do?</strong>
                <p style="margin-top: 10px;">Request a new password reset link using the forgot password page.</p>
            </div>
            <div class="back-link">
                <a href="forgot_password.php">← Request New Reset Link</a>
            </div>
        
        <?php else: ?>
            <h2>🔐 Reset Your Password</h2>
            <p class="subtitle">Choose a strong password for your account</p>
            
            <div class="user-badge">
                📧 <?php echo htmlspecialchars($user_email); ?>
            </div>
            
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" onsubmit="return validateForm()">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" id="new_password" required 
                           placeholder="Enter new password" 
                           minlength="6"
                           oninput="checkPasswordStrength()">
                    <div class="password-strength">
                        <div class="password-strength-bar" id="strength-bar"></div>
                    </div>
                    <div class="password-requirements">
                        Minimum 6 characters. Use a mix of letters, numbers, and symbols for better security.
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" required 
                           placeholder="Re-enter password"
                           oninput="checkPasswordMatch()">
                    <div id="match-message" style="font-size: 12px; margin-top: 5px;"></div>
                </div>
                
                <button type="submit">Reset Password</button>
            </form>
            
            <div class="back-link">
                <a href="login.php">← Back to Login</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function checkPasswordStrength() {
            const password = document.getElementById('new_password').value;
            const strengthBar = document.getElementById('strength-bar');
            
            let strength = 0;
            if (password.length >= 6) strength += 25;
            if (password.length >= 10) strength += 25;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 15;
            if (/[^a-zA-Z0-9]/.test(password)) strength += 10;
            
            strength = Math.min(strength, 100);
            
            strengthBar.style.width = strength + '%';
            
            if (strength < 40) {
                strengthBar.style.background = '#dc3545';
            } else if (strength < 70) {
                strengthBar.style.background = '#ffc107';
            } else {
                strengthBar.style.background = '#28a745';
            }
        }
        
        function checkPasswordMatch() {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            const message = document.getElementById('match-message');
            
            if (confirm.length === 0) {
                message.textContent = '';
                return;
            }
            
            if (password === confirm) {
                message.textContent = '✓ Passwords match';
                message.style.color = '#28a745';
            } else {
                message.textContent = '✗ Passwords do not match';
                message.style.color = '#dc3545';
            }
        }
        
        function validateForm() {
            const password = document.getElementById('new_password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password.length < 6) {
                alert('Password must be at least 6 characters long.');
                return false;
            }
            
            if (password !== confirm) {
                alert('Passwords do not match. Please try again.');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>