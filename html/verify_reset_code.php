<?php
session_start();
require_once __DIR__ . "/../database/db_connect.php";

$error = '';
$success = '';

// Check if email is set in session (from forgot_password.php)
if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit;
}

$email = $_SESSION['reset_email'];

/* ---------- HANDLE CODE VERIFICATION ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $code = trim($_POST['code']);
    
    if (empty($code)) {
        $error = "Please enter the verification code.";
    } else {
        try {
            // Get user ID from email
            $user_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $user_stmt->execute([$email]);
            $user = $user_stmt->fetch();
            
            if (!$user) {
                $error = "Invalid session. Please start over.";
            } else {
                // Check if code is valid and not expired
                $stmt = $pdo->prepare("
                    SELECT id, token, expires_at 
                    FROM password_resets 
                    WHERE user_id = ? 
                    AND token = ? 
                    AND expires_at > NOW()
                    AND used = 0
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $stmt->execute([$user['id'], $code]);
                $reset = $stmt->fetch();
                
                if ($reset) {
                    // Code is valid - store reset ID in session and redirect to new password page
                    $_SESSION['reset_id'] = $reset['id'];
                    $_SESSION['reset_user_id'] = $user['id'];
                    header("Location: reset_password.php");
                    exit;
                } else {
                    // Check if code exists but is expired
                    $expired_stmt = $pdo->prepare("
                        SELECT id 
                        FROM password_resets 
                        WHERE user_id = ? 
                        AND token = ?
                        ORDER BY created_at DESC 
                        LIMIT 1
                    ");
                    $expired_stmt->execute([$user['id'], $code]);
                    
                    if ($expired_stmt->fetch()) {
                        $error = "This code has expired. Please request a new one.";
                    } else {
                        $error = "Invalid verification code. Please check and try again.";
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Database error. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code - BIMS</title>
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
        
        .verify-container {
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
        
        .verify-container::before {
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
        
        .email-display {
            background: #f0f0f0;
            padding: 12px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
            font-weight: 600;
            color: #0b1c2d;
            border: 2px dashed #ddd;
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
        
        .code-input {
            width: 100%;
            padding: 16px;
            border: 2px solid #e5e5e5;
            border-radius: 10px;
            font-size: 24px;
            font-family: 'Courier New', monospace;
            text-align: center;
            letter-spacing: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            background: #fafafa;
        }
        
        .code-input:focus {
            outline: none;
            border-color: #f4c430;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(244, 196, 48, 0.1);
        }
        
        .code-input::placeholder {
            letter-spacing: normal;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
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
        
        .timer {
            text-align: center;
            margin-top: 15px;
            font-size: 13px;
            color: #666;
        }
        
        .timer.expired {
            color: #c33;
            font-weight: 600;
        }
        
        @media (max-width: 480px) {
            .verify-container {
                padding: 40px 30px;
            }
            
            h1 {
                font-size: 22px;
            }
            
            .code-input {
                font-size: 20px;
                letter-spacing: 6px;
            }
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="header">
            <div class="icon">🔐</div>
            <h1>Verify Reset Code</h1>
            <p class="subtitle">We sent a 6-digit code to your email</p>
        </div>
        
        <div class="email-display">
            <?php echo htmlspecialchars($email); ?>
        </div>
        
        <?php if (isset($_SESSION['reset_code_display'])): ?>
            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; border-radius: 8px;">
                <strong>⚠️ Email Delivery Failed - Development Mode</strong><br>
                <p style="margin: 10px 0; font-size: 14px;">Your reset code is: <strong style="font-size: 24px; color: #0b1c2d; font-family: monospace;"><?php echo $_SESSION['reset_code_display']; unset($_SESSION['reset_code_display']); ?></strong></p>
                <p style="font-size: 12px; color: #666;">In production, this would be sent via email. Check /logs/email_log.txt for details.</p>
            </div>
        <?php endif; ?>
        
        <div class="divider"></div>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label>Enter 6-Digit Code</label>
                <input type="text" 
                       name="code" 
                       class="code-input"
                       placeholder="000000"
                       maxlength="6"
                       pattern="[0-9]{6}"
                       inputmode="numeric"
                       required
                       autofocus>
            </div>
            
            <div class="timer" id="timer">
                Code expires in <span id="countdown">10:00</span>
            </div>
            
            <button type="submit">Verify Code</button>
        </form>
        
        <div class="help-text">
            Didn't receive the code? <a href="forgot_password.php">Request new code</a><br>
            <a href="login.php">← Back to Login</a>
        </div>
    </div>
    
    <script>
        // Auto-format code input (digits only, max 6)
        const codeInput = document.querySelector('.code-input');
        codeInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
        });
        
        // Countdown timer (10 minutes)
        let timeLeft = 600; // 10 minutes in seconds
        const countdownEl = document.getElementById('countdown');
        const timerEl = document.getElementById('timer');
        
        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            countdownEl.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                timerEl.innerHTML = '<span style="color: #c33; font-weight: 600;">⚠️ Code has expired. Please request a new one.</span>';
                clearInterval(timerInterval);
            }
            
            timeLeft--;
        }
        
        updateTimer();
        const timerInterval = setInterval(updateTimer, 1000);
    </script>
</body>
</html>