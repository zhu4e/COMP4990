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

function renderTable($result) {
  if (is_array($result) && isset($result["__error"])) {
    echo "<div class='alert error'><strong>Error:</strong> " . e($result["__error"]) . "</div>";
    return;
  }

  $fields = $result->fetch_fields();

  echo "<div class='table-wrap'>";
  echo "<table class='data-table'><thead><tr>";
  foreach ($fields as $f) echo "<th>" . e($f->name) . "</th>";
  echo "</tr></thead><tbody>";

  while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    foreach ($fields as $f) echo "<td>" . e($row[$f->name] ?? "") . "</td>";
    echo "</tr>";
  }

  echo "</tbody></table></div>";
}

$db1_tables = ["Customer", "Product", "Purchases"];
$db2_tables = ["Customers", "CustomerInfo", "Item", "Purchase"];
?>

<div class="page database-page">
  <div class="topbar">
    <div class="topbar-left">
      <h1>Admin Database View</h1>
      <p class="muted">Full database browsing access.</p>
    </div>
    <div class="topbar-right">
      <a class="btn secondary" href="admin_index.php">Back to Dashboard</a>
      <a class="btn" href="logout.php">Logout</a>
    </div>
  </div>

  <div class="grid-2 wide">

    <section class="panel">
      <h2>Database 1</h2>
      <?php foreach ($db1_tables as $t): ?>
        <h3><?= e($t) ?></h3>
        <?php renderTable(queryAll($conn_db1, $t)); ?>
      <?php endforeach; ?>
    </section>

    <section class="panel">
      <h2>Database 2</h2>
      <?php foreach ($db2_tables as $t): ?>
        <h3><?= e($t) ?></h3>
        <?php renderTable(queryAll($conn_db2, $t)); ?>
      <?php endforeach; ?>
    </section>

  </div>
</div>

<?php include "includes/footer.php"; ?>