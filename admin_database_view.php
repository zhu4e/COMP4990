<?php
require_once "includes/config.php";
require_once "includes/auth.php";
session_start();
checkAuth();
requireRole("admin");

$pageTitle = "Admin Database View";
include "includes/header.php";

function e($v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function queryAll(mysqli $conn, string $table) {
  $sql = "SELECT * FROM `$table`";
  $res = $conn->query($sql);
  if (!$res) return ["__error" => $conn->error, "__sql" => $sql];
  return $res;
}

// Function to render all tables
function renderAllTables(mysqli $conn, array $tables) {
    foreach ($tables as $t) {
        $res = $conn->query("SELECT * FROM `$t` LIMIT 50");
        if (!$res) {
            echo "<div class='alert error'><strong>Error:</strong> " . e($conn->error) . "</div>";
            continue;
        }
        echo "<h3>" . e($t) . "</h3>";
        echo "<div class='table-wrap'><table class='data-table'><thead><tr>";
        foreach ($res->fetch_fields() as $f) echo "<th>" . e($f->name) . "</th>";
        echo "</tr></thead><tbody>";
        $res->data_seek(0);
        while ($row = $res->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $v) echo "<td>" . e($v) . "</td>";
            echo "</tr>";
        }
        echo "</tbody></table></div><br>";
    }
}

// Tables for each database
$db1_tables = ["Customers","Products","Orders","Order_Items","Stock","StockLog"];
$db2_tables = ["Customers","Products","Orders","Order_Items","Stock","StockLog"];
?>

<div class="page database-page">
  <div class="topbar">
    <div class="topbar-left">
      <h1>Admin Database View</h1>
      <p class="muted">Full database browsing access.</p>
    </div>
    <div class="topbar-right">
      <a class="btn secondary" href="admin_dashboard.php">Back to Dashboard</a>
      <a class="btn" href="logout.php">Logout</a>
    </div>
  </div>

  <!-- Tables side by side -->
    <div class="grid-2 wide" style="margin-top:20px;">
        <section class="panel">
            <div class="panel-head"><h2>Database 1</h2></div>
            <?php renderAllTables($conn_db1, $db1_tables); ?>
        </section>

        <section class="panel">
            <div class="panel-head"><h2>Database 2</h2></div>
            <?php renderAllTables($conn_db2, $db2_tables); ?>
        </section>
    </div>
</div>

<?php include "includes/footer.php"; ?>
