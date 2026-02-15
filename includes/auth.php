<?php
require_once "includes/config.php";
session_start();

function checkAuth() {
    if(!isset($_SESSION['user'])) {
        header('Location: login.php');
        exit();
    }
}
