<?php
session_start();

if (!empty($_SESSION['user_id'])) {

    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
        exit();
    }

    if ($_SESSION['role'] === 'analyst') {
        header("Location: analyst_dashboard.php");
        exit();
    }
}

header("Location: login.php");
exit();
?>