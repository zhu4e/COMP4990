<?php
require_once "includes/config.php";
require_once "includes/auth.php";
session_start();
checkAuth();
requireRole("analyst");

$pageTitle = "Analyst Dashboard";
include "includes/header.php";
?>

<div class="center-card">

  <h1>Welcome to the E-Commerce Data Warehouse Dashboard</h1>
  <p>You are logged in as <strong>Analyst</strong>.</p>

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
    <a class="btn" href="analyst_database_view.php">Query Database</a>
  </div>

  <div class="dashboard-section">
    <h2>Data Warehouse</h2>
    <a class="btn" href="analyst_data_warehouse_view.php">Query Warehouse</a>
  </div>

  <a class="logout-link" href="logout.php">Logout</a>

</div>

<?php include "includes/footer.php"; ?>