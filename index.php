<?php
require_once "includes/config.php";
require_once "includes/auth.php";
session_start();
checkAuth();

$pageTitle = "Dashboard";
include "includes/header.php";
?>

<div class="dashboard-container">

  <!-- HEADER -->
  <h1>Welcome to the E-Commerce Data Warehouse Dashboard</h1>
  <p class="muted">Overview of system performance and business analytics.</p>


  <!-- KEY METRICS -->
  <div class="metrics-section">
    <h2>Key Metrics</h2>

    <div class="metrics-grid">
      <div class="metric-card">
        <h3>Total Orders</h3>
        <p class="metric-value">--</p>
      </div>

      <div class="metric-card">
        <h3>Total Revenue</h3>
        <p class="metric-value">$ --</p>
      </div>

      <div class="metric-card">
        <h3>Active Customers</h3>
        <p class="metric-value">--</p>
      </div>

      <div class="metric-card">
        <h3>Products Sold</h3>
        <p class="metric-value">--</p>
      </div>
    </div>
  </div>


  <!-- GRAPHS -->
  <div class="graphs-section">
    <h2>Analytics & Visualizations</h2>

    <div class="graph-placeholder">
      <p>Sales Trend Graph (Coming Soon)</p>
    </div>

    <div class="graph-placeholder">
      <p>Customer Growth Chart (Coming Soon)</p>
    </div>
  </div>


  <!-- DATA ACCESS LINKS -->
  <div class="data-access-section">
    <h2>Data Access</h2>

    <div class="data-grid">
      <div class="data-card">
        <h3>Operational Databases</h3>
        <a class="btn" href="database_view.php">View Databases</a>
      </div>

      <div class="data-card">
        <h3>Data Warehouse</h3>
        <a class="btn" href="data_warehouse_view.php">View Warehouse</a>
      </div>
    </div>
  </div>

  <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
  <div class="data-access-section" style="margin-top: 40px;">
    <h2>Administration</h2>
    <div class="data-grid">
      <div class="data-card" style="border: 2px solid rgba(124,58,237,.20);">
        <h3>User Management</h3>
        <p class="muted" style="margin-bottom: 15px;">Add, view, and remove access for dashboard users.</p>
        <a class="btn" style="background: linear-gradient(180deg, #7c3aed, #6d28d9);" href="admin_users.php">Manage Users</a>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <a class="logout-link" href="logout.php">Logout</a>

</div>

<?php include "includes/footer.php"; ?>
