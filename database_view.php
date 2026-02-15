<?php
require_once "includes/config.php";
require_once "includes/auth.php";
checkAuth();

$pageTitle = "Database View";
include "includes/header.php";

function e($v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function isSafeSelectQuery(string $q): bool {
  $q = trim($q);
  $lower = strtolower(ltrim($q));

  // allow SELECT or WITH ... SELECT
  $startsOk = str_starts_with($lower, "select") || str_starts_with($lower, "with");
  if (!$startsOk) return false;

  // block multi-statements
  if (strpos($q, ";") !== false) return false;

  // block destructive keywords (basic safety)
  $blocked = ["insert", "update", "delete", "drop", "alter", "truncate", "create", "replace", "rename", "grant", "revoke", "outfile", "dumpfile"];
  foreach ($blocked as $b) {
    if (strpos($lower, $b) !== false) return false;
  }
  return true;
}

function queryAll(mysqli $conn, string $table) {
  $sql = "SELECT * FROM `$table`";
  $res = $conn->query($sql);
  if (!$res) return ["__error" => $conn->error, "__sql" => $sql];
  return $res;
}

function renderTable($result, string $tableId) {
  if (is_array($result) && isset($result["__error"])) {
    echo "<div class='alert error'><strong>Error:</strong> " . e($result["__error"]) . "<br><code>" . e($result["__sql"]) . "</code></div>";
    return;
  }

  /** @var mysqli_result $result */
  $fields = $result->fetch_fields();

  echo "<div class='table-toolbar'>";
  echo "  <span class='pill'>" . (int)$result->num_rows . " rows</span>";
  echo "</div>";

  echo "<div class='table-wrap'>";
  echo "<table id='" . e($tableId) . "' class='data-table'>";
  echo "<thead><tr>";
  foreach ($fields as $f) echo "<th>" . e($f->name) . "</th>";
  echo "</tr></thead><tbody>";

  while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    foreach ($fields as $f) {
      $val = $row[$f->name] ?? "";
      echo "<td>" . e($val) . "</td>";
    }
    echo "</tr>";
  }

  echo "</tbody></table></div>";
}

function renderQueryResult(mysqli $conn, string $sql, int $limit = 200): array {
  $q = trim($sql);

  if ($q === "") return ["error" => "Please enter a SQL query."];
  if (!isSafeSelectQuery($q)) return ["error" => "Only single-statement read-only SELECT queries are allowed."];

  $info = "";
  if (stripos($q, " limit ") === false) {
    $q .= " LIMIT " . $limit;
    $info = "No LIMIT detected — automatically added: LIMIT $limit";
  }

  try {
  $res = $conn->query($q);
} catch (mysqli_sql_exception $ex) {
  return ["error" => $ex->getMessage()];
}

if ($res === true) {
  // just in case something slips through
  return ["info" => "Query executed (no result set).", "html" => ""];
}
if ($res === false) {
  return ["error" => $conn->error];
}
  // build HTML table
  $fields = $res->fetch_fields();
  ob_start();
  echo "<div class='pill'>Returned " . (int)$res->num_rows . " row(s)</div><div style='height:12px;'></div>";
  echo "<div class='table-wrap'><table class='data-table'><thead><tr>";
  foreach ($fields as $f) echo "<th>" . e($f->name) . "</th>";
  echo "</tr></thead><tbody>";
  while ($row = $res->fetch_assoc()) {
    echo "<tr>";
    foreach ($fields as $f) echo "<td>" . e($row[$f->name] ?? "") . "</td>";
    echo "</tr>";
  }
  echo "</tbody></table></div>";
  $html = ob_get_clean();

  return ["info" => $info, "html" => $html];
}

// Tables (your current setup)
$db1_tables = ["Customer", "Product", "Purchases"];
$db2_tables = ["Customers", "CustomerInfo", "Item", "Purchase"];

// SQL box state
$dbChoice = $_POST["db_choice"] ?? "db1";
$defaultSql = ($dbChoice === "db2") ? "SELECT * FROM Customers" : "SELECT * FROM Customer";
$sqlQuery = $_POST["sql_query"] ?? $defaultSql;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["run_sql"])) {
  $connSelected = ($dbChoice === "db2") ? $conn_db2 : $conn_db1;
  $queryOut = renderQueryResult($connSelected, $sqlQuery);
}
?>

<div class="page database-page">
  <div class="topbar">
    <div class="topbar-left">
      <h1>Database View</h1>
      <p class="muted">View database tables and run read-only SELECT queries on DB1 or DB2.</p>
    </div>
    <div class="topbar-right">
      <a class="btn secondary" href="index.php">Back to Dashboard</a>
      <a class="btn" href="logout.php">Logout</a>
    </div>
  </div>

  <!-- SQL BOX (NEW) -->
  <section class="panel" style="padding:16px; margin-bottom:18px;">
    <h2 style="margin-top:0;">SQL (SELECT only)</h2>

    <form method="POST">
      <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:10px;">
        <label class="muted" style="font-size:12px;">Database</label>
        <select name="db_choice" class="sql-select">
          <option value="db1" <?= ($dbChoice==="db1") ? "selected" : "" ?>>Database 1</option>
          <option value="db2" <?= ($dbChoice==="db2") ? "selected" : "" ?>>Database 2</option>
        </select>
        <span class="muted" style="font-size:12px;">Tip: LIMIT is added automatically.</span>
      </div>

      <textarea name="sql_query" class="sql-textarea" rows="6" spellcheck="false"><?= e($sqlQuery) ?></textarea>

      <div style="display:flex; gap:10px; align-items:center; margin-top:10px; flex-wrap:wrap;">
        <button class="btn" type="submit" name="run_sql" value="1">Run Query</button>
      </div>
    </form>

    <?php if ($queryOut && !empty($queryOut["error"])): ?>
      <div class="alert error" style="margin-top:14px;"><strong>Error:</strong> <?= e($queryOut["error"]) ?></div>
    <?php endif; ?>

    <?php if ($queryOut && !empty($queryOut["info"])): ?>
      <p class="muted" style="margin-top:14px;"><?= e($queryOut["info"]) ?></p>
    <?php endif; ?>

    <?php if ($queryOut && !empty($queryOut["html"])): ?>
      <div style="margin-top:14px;"><?= $queryOut["html"] ?></div>
    <?php endif; ?>
  </section>

  <!-- TABLE VIEW (AS-IS) -->
  <div class="grid-2 wide">
    <section class="panel">
      <div class="panel-head">
        <h2>Database 1</h2>
        <span class="tag">Customer • Product • Purchases</span>
      </div>

      <?php foreach ($db1_tables as $t): ?>
        <details class="accordion" open>
          <summary>
            <span class="acc-title"><?= e($t) ?></span>
            <span class="acc-sub">Click to collapse/expand</span>
          </summary>
          <?php renderTable(queryAll($conn_db1, $t), "db1_" . strtolower($t)); ?>
        </details>
      <?php endforeach; ?>
    </section>

    <section class="panel">
      <div class="panel-head">
        <h2>Database 2</h2>
        <span class="tag">Customers • CustomerInfo • Item • Purchase</span>
      </div>

      <?php foreach ($db2_tables as $t): ?>
        <details class="accordion" open>
          <summary>
            <span class="acc-title"><?= e($t) ?></span>
            <span class="acc-sub">Click to collapse/expand</span>
          </summary>
          <?php renderTable(queryAll($conn_db2, $t), "db2_" . strtolower($t)); ?>
        </details>
      <?php endforeach; ?>
    </section>
  </div>
</div>

<?php include "includes/footer.php"; ?>
