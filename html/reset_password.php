<?php
require_once __DIR__ . "/../../database/db_connect.php";

require_once "../includes/mailer.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = $_POST['email'];

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        $token = rand(100000, 999999);
        $expires = date("Y-m-d H:i:s", strtotime("+10 minutes"));

        $pdo->prepare("
            INSERT INTO password_resets (user_id, token, expires_at)
            VALUES (?, ?, ?)
        ")->execute([$user['id'], $token, $expires]);

        sendMail($email, "Password Reset Code", "Your code is: $token");
    }

    echo "If email exists, a reset code was sent.";
}
?>

<form method="POST">
    <input type="email" name="email" required>
    <button>Send Reset Code</button>
</form>
