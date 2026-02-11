<?php
/**
 * Email Helper for BIMS - NLA School
 * Uses noreply.nla.sc.ke with Gmail SMTP
 * 
 * SETUP:
 * 1. Get Gmail App Password: https://myaccount.google.com/apppasswords
 * 2. Replace 'YOUR-APP-PASSWORD-HERE' below with your 16-character app password
 * 3. Make sure PHPMailer is installed: composer require phpmailer/phpmailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send an email
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Plain text body
 * @param string|null $html HTML body (optional)
 * @return bool True if sent successfully, false otherwise
 */
function sendEmail($to, $subject, $body, $html = null) {
    // Create logs directory if it doesn't exist
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

    
    $log_file = $log_dir . '/email_log.txt';
    $failed_log = $log_dir . '/email_failed.txt';
    $timestamp = date('Y-m-d H:i:s');
    
    try {
        // Try to use PHPMailer if available
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
            
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'noreply@nla.sc.ke'; // Your Gmail address
            $mail->Password = 'cldhnlopjpiodxgv'; // ← CHANGE THIS to your 16-char app password!
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            // Sender and recipient
            $mail->setFrom('noreply@nla.sc.ke', 'NLA BIMS');
            $mail->addAddress($to);
            $mail->addReplyTo('info@nla.sc.ke', 'NLA Support');
            
            // Content
            $mail->isHTML($html !== null);
            $mail->Subject = $subject;
            
            if ($html !== null) {
                $mail->Body = $html;
                $mail->AltBody = $body; // Plain text fallback
            } else {
                $mail->Body = $body;
            }
            
            // Send
            $mail->send();
            
            // Log success
            $log_message = "[$timestamp] SUCCESS ✅\n";
            $log_message .= "  To: $to\n";
            $log_message .= "  Subject: $subject\n";
            $log_message .= "  Method: PHPMailer/SMTP\n\n";
            file_put_contents($log_file, $log_message, FILE_APPEND);
            
            return true;
            
        } else {
            // Fallback to basic PHP mail() if PHPMailer not available
            $headers = "From: NLA BIMS <noreply.nla.sc.ke@gmail.com>\r\n";
            $headers .= "Reply-To: support@nla.sc.ke\r\n";
            
            if ($html !== null) {
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
                $result = mail($to, $subject, $html, $headers);
            } else {
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $result = mail($to, $subject, $body, $headers);
            }
            
            if ($result) {
                // Log success
                $log_message = "[$timestamp] SUCCESS ✅\n";
                $log_message .= "  To: $to\n";
                $log_message .= "  Subject: $subject\n";
                $log_message .= "  Method: PHP mail()\n\n";
                file_put_contents($log_file, $log_message, FILE_APPEND);
                return true;
            } else {
                // Log failure
                $log_message = "[$timestamp] FAILED ❌\n";
                $log_message .= "  To: $to\n";
                $log_message .= "  Subject: $subject\n";
                $log_message .= "  Method: PHP mail()\n";
                $log_message .= "  Error: mail() returned false\n\n";
                file_put_contents($failed_log, $log_message, FILE_APPEND);
                return false;
            }
        }
        
    } catch (Exception $e) {
        // Log exception
        $log_message = "[$timestamp] FAILED ❌\n";
        $log_message .= "  To: $to\n";
        $log_message .= "  Subject: $subject\n";
        $log_message .= "  Error: {$e->getMessage()}\n\n";
        file_put_contents($failed_log, $log_message, FILE_APPEND);
        
        // Also log to PHP error log
        error_log("Email failed to $to: " . $e->getMessage());
        
        return false;
    }
}

/**
 * Send a test email to verify configuration
 * 
 * @param string $to Test recipient email
 * @return bool True if sent successfully
 */
function sendTestEmail($to) {
    $subject = "BIMS Email Test";
    $body = "This is a test email from BIMS. If you received this, email is working correctly!";
    
    $html = "
    <html>
    <body style='font-family: Arial, sans-serif; padding: 20px;'>
        <h2 style='color: #0b1c2d;'>BIMS Email Test</h2>
        <p>This is a test email from the Nairobi Leadership Academy BIMS system.</p>
        <p><strong>If you received this email, your email configuration is working correctly! ✅</strong></p>
        <hr>
        <p style='font-size: 12px; color: #666;'>
            Sent: " . date('Y-m-d H:i:s') . "<br>
            From: NLA BIMS<br>
            noreply.nla.sc.ke
        </p>
    </body>
    </html>
    ";
    
    return sendEmail($to, $subject, $body, $html);
}