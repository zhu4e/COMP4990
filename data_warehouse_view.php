<?php
// data_warehouse_view.php (Warehouse viewer + read-only query runner)

require_once "includes/config.php";
require_once "includes/auth.php";
session_start();
checkAuth();

$pageTitle = "Data Warehouse View";
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

  // block destructive keywords
  $blocked = ["insert", "update", "delete", "drop", "alter", "truncate", "create", "replace", "rename", "grant", "revoke", "outfile", "dumpfile"];
  foreach ($blocked as $b) {
    if (strpos($lower, $b) !== false) return false;
  }
  return true;
}

function renderResult(mysqli_result $res) {
  $fields = $res->fetch_fields();

  echo "<div class='table-wrap'>";
  echo "<table class='data-table'>";
  echo "<thead><tr>";
  foreach ($fields as $f) echo "<th>" . e($f->name) . "</th>";
  echo "</tr></thead><tbody>";

  while ($row = $res->fetch_assoc()) {
    echo "<tr>";
    foreach ($fields as $f) {
      $val = $row[$f->name] ?? "";
      echo "<td>" . e($val) . "</td>";
    }
    echo "</tr>";
  }

  echo "</tbody></table></div>";
}

// NOTE: Add $conn_dw in includes/config.php (see steps below).
$conn = $conn_dw ?? null;

$error = "";
$info = "";
$query = $_POST["sql_query"] ?? "SELECT * FROM FactSales";
$resultHtml = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  if (!$conn) {
    $error = "Warehouse connection (\$conn_dw) is not configured in includes/config.php.";
  } else {
    $q = trim($query);

    if ($q === "") {
      $error = "Please enter a SQL query.";
    } elseif (!isSafeSelectQuery($q)) {
      $error = "Only single-statement read-only SELECT queries are allowed.";
    } else {
      $limit = 200;
      if (stripos($q, " limit ") === false) {
        $q .= " LIMIT " . $limit;
        $info = "No LIMIT detected — automatically added: LIMIT $limit";
      }

      $res = $conn->query($q);
      if (!$res) {
        $error = $conn->error;
      } else {
        $rows = $res->num_rows;
        $resultHtml = "<div class='pill'>Returned $rows row(s)</div><div style='height:12px;'></div>";
        ob_start();
        renderResult($res);
        $resultHtml .= ob_get_clean();
      }
    }
  }
}

$tables = ["FactSales", "CustomerDim", "ProductDim", "DatetimeDim", "BranchDim"];
?>

<div class="page database-page">
  <div class="topbar">
    <div class="topbar-left">
      <h1>Data Warehouse</h1>
      <p class="muted">Run read-only SELECT queries against your warehouse and view tables.</p>
    </div>
    <div class="topbar-right">
      <a class="btn secondary" href="index.php">Back to Dashboard</a>
      <a class="btn" href="logout.php">Logout</a>
    </div>
  </div>

  <section class="panel" style="padding:16px; margin-bottom:18px;">
    <h2 style="margin-top:0;">Warehouse SQL (SELECT only)</h2>
    <form method="POST">
      <textarea name="sql_query" class="sql-textarea" rows="6" spellcheck="false"><?= e($query) ?></textarea>
      <div style="display:flex; gap:10px; align-items:center; margin-top:10px; flex-wrap:wrap;">
        <button type="submit" class="btn">Run Query</button>
        <span class="muted" style="font-size:12px;">Tip: LIMIT is added automatically.</span>
      </div>
    </form>

    <?php if ($error): ?>
      <div class="alert error" style="margin-top:14px;"><strong>Error:</strong> <?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($info): ?>
      <p class="muted" style="margin-top:14px;"><?= e($info) ?></p>
    <?php endif; ?>

    <?php if ($resultHtml): ?>
      <div style="margin-top:14px;"><?= $resultHtml ?></div>
    <?php endif; ?>
  </section>

  <div class="grid-2 wide">
    <section class="panel">
      <div class="panel-head">
        <h2>Warehouse Tables</h2>
        <span class="tag">Fact + Dimensions</span>
      </div>

      <?php foreach ($tables as $t): ?>
        <details class="accordion" open>
          <summary>
            <span class="acc-title"><?= e($t) ?></span>
            <span class="acc-sub">Click to collapse/expand</span>
          </summary>
          <div style="padding: 0 16px 16px;">
            <a class="btn secondary" href="#"
               onclick="event.preventDefault(); document.querySelector('textarea[name=sql_query]').value='SELECT * FROM <?= e($t) ?>'; document.querySelector('form').submit();">
              View <?= e($t) ?>
            </a>
          </div>
        </details>
      <?php endforeach; ?>
    </section>

    <section class="panel">
      <div class="panel-head">
        <h2>Common Reports</h2>
        <span class="tag">Ready-to-run queries</span>
      </div>

      <details class="accordion" open>
        <summary><span class="acc-title">Revenue by Product</span><span class="acc-sub">Top products by revenue</span></summary>
        <div style="padding: 0 16px 16px;">
          <a class="btn secondary" href="#"
             onclick="event.preventDefault(); document.querySelector('textarea[name=sql_query]').value=
`SELECT p.ProductName, SUM(f.Revenue) AS TotalRevenue
 FROM FactSales f
 JOIN ProductDim p ON p.ProductID = f.ProductID
 GROUP BY p.ProductName
 ORDER BY TotalRevenue DESC`; document.querySelector('form').submit();">
            Run
          </a>
        </div>
      </details>

      <details class="accordion">
        <summary><span class="acc-title">Revenue by Date</span><span class="acc-sub">Daily revenue trend</span></summary>
        <div style="padding: 0 16px 16px;">
          <a class="btn secondary" href="#"
             onclick="event.preventDefault(); document.querySelector('textarea[name=sql_query]').value=
`SELECT d.FullDate, SUM(f.Revenue) AS TotalRevenue
 FROM FactSales f
 JOIN DatetimeDim d ON d.DateID = f.DateID
 GROUP BY d.FullDate
 ORDER BY d.FullDate`; document.querySelector('form').submit();">
            Run
          </a>
        </div>
      </details>

      <details class="accordion">
        <summary><span class="acc-title">Revenue by Branch</span><span class="acc-sub">Compare DB1 vs DB2</span></summary>
        <div style="padding: 0 16px 16px;">
          <a class="btn secondary" href="#"
             onclick="event.preventDefault(); document.querySelector('textarea[name=sql_query]').value=
`SELECT b.City, b.Province, b.Country, SUM(f.Revenue) AS TotalRevenue
 FROM FactSales f
 JOIN BranchDim b ON b.BranchID = f.BranchID
 GROUP BY b.BranchID, b.City, b.Province, b.Country
 ORDER BY TotalRevenue DESC`; document.querySelector('form').submit();">
            Run
          </a>
        </div>
      </details>
    </section>
  </div>
</div>

<?php include "includes/footer.php"; ?>
