<?php
require_once "includes/config.php";

// Only start a session if doesn't exist
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the user is logged in
function checkAuth() {
    if(!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
}

// Check if the user has correct permissions for page
function requireRole($allowedRole) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $allowedRole) {
        die("Access Denied: You do not have permission to view this page.");
    }
}
?>
