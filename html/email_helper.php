<?php
/**
 * Simple Email Helper for Password Reset
 * 
 * NOTE: This uses PHP's built-in mail() function which may not work on all servers.
 * For production, consider using PHPMailer or similar library with SMTP.
 */

function sendPasswordResetEmail($to_email, $reset_link, $token_code) {
    $subject = "Password Reset Request - BIMS";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0b1c2d; color: white; padding: 20px; text-align: center; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 8px; margin-top: 20px; }
            .button { display: inline-block; background: #f4c430; color: #0b1c2d; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 20px 0; }
            .code-box { background: white; padding: 15px; border-left: 4px solid #f4c430; font-size: 24px; font-weight: bold; text-align: center; margin: 20px 0; }
            .footer { text-align: center; color: #666; font-size: 12px; margin-top: 30px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>BIMS - Password Reset</h1>
            </div>
            <div class='content'>
                <h2>Password Reset Request</h2>
                <p>Hello,</p>
                <p>We received a request to reset your password for your BIMS account (<strong>" . htmlspecialchars($to_email) . "</strong>).</p>
                
                <p><strong>Option 1: Click the link below to reset your password:</strong></p>
                <p style='text-align: center;'>
                    <a href='" . htmlspecialchars($reset_link) . "' class='button'>Reset Password</a>
                </p>
                <p style='font-size: 12px; color: #666;'>Link: " . htmlspecialchars($reset_link) . "</p>
                
                <p><strong>Option 2: Use this reset code:</strong></p>
                <div class='code-box'>" . htmlspecialchars($token_code) . "</div>
                
                <p><strong>⚠️ Important:</strong></p>
                <ul>
                    <li>This link and code expire in <strong>30 minutes</strong></li>
                    <li>If you didn't request this reset, please ignore this email</li>
                    <li>Your password won't change until you complete the reset process</li>
                </ul>
            </div>
            <div class='footer'>
                <p>This is an automated email from BIMS. Please do not reply.</p>
                <p>&copy; " . date('Y') . " BIMS - School Management System</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    // Headers for HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: BIMS <noreply@school.com>" . "\r\n";
    
    // ALWAYS log to file regardless of email success
    logPasswordResetEmail($to_email, $reset_link, $token_code);
    
    // Try to send email (will likely fail on most servers)
    $result = @mail($to_email, $subject, $message, $headers);
    
    return $result;
}

/**
 * Alternative: Log email to file for testing
 * Use this if mail() doesn't work on your server
 */
function logPasswordResetEmail($to_email, $reset_link, $token_code) {
    // Use system temp directory (works on Windows and Linux)
    $log_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'password_reset_emails.log';
    
    // No need to create directory, temp always exists
    
    $log_entry = sprintf(
        "[%s] Password Reset Email\nTo: %s\nReset Link: %s\nReset Code: %s\n%s\n",
        date('Y-m-d H:i:s'),
        $to_email,
        $reset_link,
        $token_code,
        str_repeat('-', 80)
    );
    
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    
    return true;
}