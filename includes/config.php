<?php
// ===== SITE SETTINGS =====
define('SITE_NAME', 'Database System');

// ===== DATABASE 1 =====
$db1_host = "localhost";
$db1_user = "keenanl_database1";      
$db1_pass = "admin123";          
$db1_name = "keenanl_database1";  

$conn_db1 = new mysqli($db1_host, $db1_user, $db1_pass, $db1_name);

if ($conn_db1->connect_error) {
    die("Database 1 Connection Failed: " . $conn_db1->connect_error);
}


// ===== DATABASE 2 =====
$db2_host = "localhost";
$db2_user = "keenanl_database2";
$db2_pass = "admin123";
$db2_name = "keenanl_database2";  

$conn_db2 = new mysqli($db2_host, $db2_user, $db2_pass, $db2_name);

if ($conn_db2->connect_error) {
    die("Database 2 Connection Failed: " . $conn_db2->connect_error);
}

// ===== WAREHOUSE DATABASE CONNECTION =====
$dw_host = "localhost";               
$dw_user = "keenanl_datawarehouse";                 
$dw_pass = "admin123";                    
$dw_name = "keenanl_datawarehouse";          

$conn_dw = new mysqli($dw_host, $dw_user, $dw_pass, $dw_name);

if ($conn_dw->connect_error) {
    die("Warehouse connection failed: " . $conn_dw->connect_error);
}

// ===== GENERAL SETTINGS =====
date_default_timezone_set("America/Toronto");
?>