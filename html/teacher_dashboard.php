
<?php
session_start();

// Only allow this role, otherwise redirect to login
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== "teacher"){ // Change 2 for your role
    header("Location: login.php");
    exit();
}
?>
<?php
print "welcome teacher";
?>