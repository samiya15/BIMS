<?php
/**
 * Email Helper Function
 * Sends emails using PHP's mail() function with logging fallback
 */

function sendMail($to, $subject, $message) {
    $headers = "From: BIMS <noreply@school.com>\r\n";
    $headers .= "Reply-To: noreply@school.com\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // Try to send email
    $sent = @mail($to, $subject, $message, $headers);
    
    // Log the email attempt (useful for debugging)
    $log_dir = __DIR__ . '/../logs';
    if (!file_exists($log_dir)) {
        @mkdir($log_dir, 0777, true);
    }
    
    $log_file = $log_dir . '/email_log.txt';
    $log_entry = sprintf(
        "[%s] To: %s | Subject: %s | Sent: %s\nMessage: %s\n%s\n",
        date('Y-m-d H:i:s'),
        $to,
        $subject,
        $sent ? 'YES' : 'NO',
        $message,
        str_repeat('-', 80)
    );
    
    @file_put_contents($log_file, $log_entry, FILE_APPEND);
    
    // If email fails, also write to a separate "failed" log
    if (!$sent) {
        $failed_log = $log_dir . '/email_failed.txt';
        @file_put_contents($failed_log, $log_entry, FILE_APPEND);
        
        // Display the code in the failed log for manual retrieval
        error_log("Email failed to send. Check: " . $failed_log);
    }
    
    return $sent;
}

/**
 * Alternative: Get the latest reset code for a user (for testing/debugging)
 * This should NOT be exposed in production
 */
function getLatestResetCode($pdo, $email) {
    try {
        $stmt = $pdo->prepare("
            SELECT pr.token, pr.expires_at
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.id
            WHERE u.email = ?
            ORDER BY pr.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
}