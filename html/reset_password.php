<?php
session_start();
require_once __DIR__ . "/../database/db_connect.php";

$error = '';
$success = '';
$valid = false;
$user_id = null;
$reset_id = null;

// Check if coming from CODE method (via session)
if (isset($_SESSION['reset_id']) && isset($_SESSION['reset_user_id'])) {
    $valid = true;
    $user_id = $_SESSION['reset_user_id'];
    $reset_id = $_SESSION['reset_id'];
}
// Check if coming from LINK method (via URL token)
elseif (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, user_id
            FROM password_resets 
            WHERE token = ? 
            AND expires_at > NOW()
            AND used = 0
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();
        
        if ($reset) {
            $valid = true;
            $user_id = $reset['user_id'];
            $reset_id = $reset['id'];
            
            // Store in session for form submission
            $_SESSION['reset_id'] = $reset_id;
            $_SESSION['reset_user_id'] = $user_id;
        } else {
            $error = "This reset link has expired or is invalid. Please request a new one.";
        }
    } catch (PDOException $e) {
        $error = "Database error. Please try again.";
    }
}
else {
    $error = "Invalid access. Please start the password reset process again.";
}

// Handle password reset submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && $valid) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all fields.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password
            $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $update_stmt->execute([$hashed_password, $user_id]);
            
            // Mark reset as used
            $mark_used = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?");
            $mark_used->execute([$reset_id]);
            
            // Clear session
            unset($_SESSION['reset_id']);
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_token']);
            
            $success = "Password reset successful! Redirecting to login...";
            header("refresh:2;url=login.php");
        } catch (PDOException $e) {
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
        .success {
            background: #efe;
            color: #3c3;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #3c3;
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
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
            margin-bottom: 20px;
        }
        input[type="password"]:focus {
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
        .password-strength {
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        .strength-weak { background: #f44336; width: 33%; }
        .strength-medium { background: #ff9800; width: 66%; }
        .strength-strong { background: #4caf50; width: 100%; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔑 Reset Your Password</h1>
        <p class="subtitle">Choose a strong new password</p>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <div class="help-text">
                <a href="forgot_password.php">Request a new reset link</a><br>
                <a href="login.php">← Back to Login</a>
            </div>
        <?php elseif ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php elseif ($valid): ?>
            <div class="info-box">
                📝 Password Requirements:<br>
                • At least 6 characters long<br>
                • Mix of letters and numbers recommended
            </div>
            
            <form method="POST">
                <label>New Password</label>
                <input type="password" 
                       name="new_password" 
                       id="new_password"
                       placeholder="Enter new password"
                       required 
                       minlength="6"
                       autofocus>
                <div class="password-strength">
                    <div class="password-strength-bar" id="strength-bar"></div>
                </div>
                
                <label>Confirm New Password</label>
                <input type="password" 
                       name="confirm_password" 
                       id="confirm_password"
                       placeholder="Re-enter new password"
                       required 
                       minlength="6">
                
                <button type="submit">Reset Password</button>
            </form>
            
            <div class="help-text">
                <a href="login.php">← Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        const passwordInput = document.getElementById('new_password');
        const strengthBar = document.getElementById('strength-bar');
        
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                if (password.length >= 6) strength++;
                if (password.length >= 10) strength++;
                if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                if (/[^a-zA-Z0-9]/.test(password)) strength++;
                
                strengthBar.className = 'password-strength-bar';
                
                if (strength <= 2) {
                    strengthBar.classList.add('strength-weak');
                } else if (strength <= 3) {
                    strengthBar.classList.add('strength-medium');
                } else {
                    strengthBar.classList.add('strength-strong');
                }
            });
            
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const password = document.getElementById('new_password').value;
                const confirm = document.getElementById('confirm_password').value;
                
                if (password !== confirm) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                }
            });
        }
    </script>
</body>
</html>