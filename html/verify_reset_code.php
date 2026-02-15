<?php
session_start();
require_once __DIR__ . "/../database/db_connect.php";

$error = '';

if (!isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit;
}

$email = $_SESSION['reset_email'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $code = trim($_POST['code']);
    
    if (empty($code)) {
        $error = "Please enter the verification code.";
    } else {
        try {
            $user_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $user_stmt->execute([$email]);
            $user = $user_stmt->fetch();
            
            if (!$user) {
                $error = "Invalid session. Please start over.";
            } else {
                $stmt = $pdo->prepare("
                    SELECT id, token
                    FROM password_resets 
                    WHERE user_id = ? 
                    AND code = ? 
                    AND expires_at > NOW()
                    AND used = 0
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $stmt->execute([$user['id'], $code]);
                $reset = $stmt->fetch();
                
                if ($reset) {
                    $_SESSION['reset_id'] = $reset['id'];
                    $_SESSION['reset_user_id'] = $user['id'];
                    $_SESSION['reset_token'] = $reset['token'];
                    header("Location: reset_password.php");
                    exit;
                } else {
                    $error = "Invalid or expired code. Please try again.";
                }
            }
        } catch (PDOException $e) {
            error_log("Verify code error: " . $e->getMessage());
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
        .subtitle { text-align: center; color: #666; margin-bottom: 20px; font-size: 14px; }
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
        .dev-mode-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
        }
        .code-display {
            background: white;
            padding: 15px;
            border-radius: 6px;
            margin: 10px 0;
            text-align: center;
        }
        .code-number {
            font-size: 32px;
            font-weight: bold;
            color: #0b1c2d;
            font-family: 'Courier New', monospace;
            letter-spacing: 4px;
        }
        .link-display {
            background: white;
            padding: 12px;
            border-radius: 6px;
            margin: 10px 0;
            word-break: break-all;
        }
        .link-display a {
            color: #2196f3;
            text-decoration: none;
            font-size: 13px;
        }
        .error {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            border-left: 4px solid #c33;
        }
        label { display: block; margin-bottom: 8px; font-weight: 600; }
        .code-input {
            width: 100%;
            padding: 16px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 24px;
            font-family: 'Courier New', monospace;
            text-align: center;
            letter-spacing: 8px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .code-input:focus {
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
        <h1>🔐 Check Your Email</h1>
        <p class="subtitle">We sent you reset options</p>
        
        <div class="email-display">
            <?php echo htmlspecialchars($email); ?>
        </div>
        
        <?php if (isset($_SESSION['reset_code_display']) || isset($_SESSION['reset_link_display'])): ?>
            <div class="dev-mode-box">
                <strong>⚠️ Development Mode - Email Delivery Failed</strong>
                <p style="font-size: 12px; color: #666; margin-bottom: 10px;">
                    In production, you would receive an email. Here are your reset options:
                </p>
                
                <?php if (isset($_SESSION['reset_code_display'])): ?>
                    <div class="code-display">
                        <div style="font-size: 12px; color: #666; margin-bottom: 5px;">6-Digit Code:</div>
                        <div class="code-number"><?php echo $_SESSION['reset_code_display']; unset($_SESSION['reset_code_display']); ?></div>
                        <div style="font-size: 11px; color: #999; margin-top: 5px;">Enter this below ↓</div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['reset_link_display'])): ?>
                    <div class="link-display">
                        <div style="font-size: 12px; color: #666; margin-bottom: 5px;">Or click this link:</div>
                        <a href="<?php echo $_SESSION['reset_link_display']; ?>" target="_blank">
                            <?php echo $_SESSION['reset_link_display']; unset($_SESSION['reset_link_display']); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <label>Enter 6-Digit Code</label>
            <input type="text" 
                   name="code" 
                   class="code-input"
                   placeholder="000000"
                   maxlength="6"
                   pattern="[0-9]{6}"
                   required
                   autofocus>
            
            <button type="submit">Verify Code</button>
        </form>
        
        <div class="help-text">
            Didn't receive the email? <a href="forgot_password.php">Request new code</a><br>
            <a href="login.php">← Back to Login</a>
        </div>
    </div>
    
    <script>
        const codeInput = document.querySelector('.code-input');
        codeInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
        });
    </script>
</body>
</html>