<?php
require_once "includes/config.php";
require_once "includes/auth.php";
session_start();
checkAuth();
requireRole("admin");

$pageTitle = "Admin Dashboard";
include "includes/header.php";
?>

<div class="center-card">

  <h1>Welcome to the E-Commerce Data Warehouse Dashboard</h1>
  <p>You are logged in as <strong>Administrator</strong>.</p>

  <div class="dashboard-section">
    <h2>Key Metrics</h2>
    <div class="metrics-container">
        <div class="metric-box">Total Orders: --</div>
        <div class="metric-box">Total Revenue: --</div>
        <div class="metric-box">Total Customers: --</div>
    </div>

    <div class="graph-placeholder">
        <p>📊 Graphs and analytics will appear here</p>
    </div>
  </div>

  <div class="dashboard-section">
    <h2>Operational Databases</h2>
    <a class="btn" href="admin_database_view.php">Manage Database</a>
  </div>

  <div class="dashboard-section">
    <h2>Data Warehouse</h2>
    <a class="btn" href="admin_data_warehouse_view.php">Manage Warehouse</a>
  </div>

  <a class="logout-link" href="logout.php">Logout</a>

</div>

<?php include "includes/footer.php"; ?>