
<?php
session_start();

// Only allow this role, otherwise redirect to login
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== "student"){ // Change 3 for your role
    header("Location: login.php");
    exit();
}
?>
<?php
print "welcome student";
?>