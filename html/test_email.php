<?php
/**
 * Test Email Configuration
 * Run this to test if your email setup is working
 * 
 * Usage:
 * 1. Upload this file to /var/www/html/
 * 2. Visit: http://localhost:8080/test_email.php
 * 3. Enter your email and click "Send Test Email"
 */

require_once __DIR__ . "/email_helper.php";

$result = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $test_email = trim($_POST['email']);
    
    if (!filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $sent = sendTestEmail($test_email);
        
        if ($sent) {
            $result = "✅ Test email sent successfully to $test_email! Check your inbox (and spam folder).";
        } else {
            $error = "❌ Failed to send email. Check the logs at /var/www/logs/email_failed.txt for details.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Configuration Test - BIMS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #0b1c2d 0%, #1a3a52 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .test-container {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
            width: 100%;
            max-width: 500px;
        }
        
        h1 {
            color: #0b1c2d;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .info-box {
            background: #f0f8ff;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 25px;
            border-radius: 6px;
            font-size: 13px;
        }
        
        .info-box strong {
            display: block;
            margin-bottom: 8px;
            color: #0b1c2d;
        }
        
        .info-box ul {
            margin-left: 20px;
            margin-top: 8px;
        }
        
        .info-box li {
            margin: 4px 0;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        
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
            box-shadow: 0 0 0 4px rgba(244, 196, 48, 0.1);
        }
        
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #0b1c2d 0%, #1a3a52 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(11, 28, 45, 0.4);
        }
        
        .logs-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f0;
        }
        
        .logs-section h3 {
            color: #0b1c2d;
            margin-bottom: 10px;
        }
        
        .logs-section code {
            display: block;
            background: #f5f5f5;
            padding: 10px;
            border-radius: 6px;
            font-size: 12px;
            margin: 5px 0;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>📧 Email Test</h1>
        <p class="subtitle">Test your BIMS email configuration</p>
        
        <div class="info-box">
            <strong>Before testing, make sure:</strong>
            <ul>
                <li>PHPMailer is installed: <code>composer require phpmailer/phpmailer</code></li>
                <li>email_helper.php has your Gmail app password</li>
                <li>Gmail email is: <code>noreply@nla.sc.ke</code></li>
            </ul>
        </div>
        
        <?php if ($result): ?>
            <div class="success"><?php echo $result; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <label>Send Test Email To:</label>
            <input type="email" 
                   name="email" 
                   placeholder="your.email@example.com"
                   required
                   autofocus>
            
            <button type="submit">📨 Send Test Email</button>
        </form>
        
        <div class="logs-section">
            <h3>📋 Check Logs</h3>
            <p style="font-size: 13px; color: #666; margin-bottom: 10px;">
                If the test fails, check these log files:
            </p>
            <code>docker exec bims_web cat /var/www/logs/email_log.txt</code>
            <code>docker exec bims_web cat /var/www/logs/email_failed.txt</code>
        </div>
    </div>
</body>
</html>