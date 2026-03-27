<?php
require_once "includes/config.php";
require_once "includes/auth.php";
session_start();
checkAuth();
requireRole("analyst");

$pageTitle = "Analyst Data Warehouse View";
include "includes/header.php";

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// Safety check for SELECT queries
function isSafeSelectQuery(string $q): bool {
    $q = trim($q);
    $lower = strtolower($q);
    if (!str_starts_with($lower, "select") && !str_starts_with($lower, "with")) return false;
    if (strpos($q, ";") !== false) return false;
    $blocked = ["insert","update","delete","drop","alter","truncate","create","replace","rename","grant","revoke"];
    foreach ($blocked as $b) if (strpos($lower, $b) !== false) return false;
    return true;
}

// Render SQL query results
function renderQueryResult(mysqli $conn, string $sql): array {
    if (!isSafeSelectQuery($sql)) return ["error"=>"Only SELECT queries allowed."];
    $res = $conn->query($sql . " LIMIT 200");
    if (!$res) return ["error"=>$conn->error];

    $fields = $res->fetch_fields();
    ob_start();
    echo "<div class='table-wrap'><table class='data-table'><thead><tr>";
    foreach ($fields as $f) echo "<th>" . e($f->name) . "</th>";
    echo "</tr></thead><tbody>";
    while ($row = $res->fetch_assoc()) {
        echo "<tr>";
        foreach ($fields as $f) echo "<td>" . e($row[$f->name] ?? "") . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table></div>";
    return ["html"=>ob_get_clean()];
}

function renderTable(mysqli $conn, string $table) {
    $res = $conn->query("SELECT * FROM `$table` LIMIT 50");
    if (!$res) {
        echo "<div class='alert error'><strong>Error:</strong> " . e($conn->error) . "</div>";
        return;
    }

    echo "<details open><summary><strong>" . e($table) . "</strong></summary>";
    echo "<div class='table-wrap'><table class='data-table'><thead><tr>";
    foreach ($res->fetch_fields() as $f) echo "<th>" . e($f->name) . "</th>";
    echo "</tr></thead><tbody>";
    $res->data_seek(0);
    while ($row = $res->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $v) echo "<td>" . e($v) . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table></div></details>";
}
 
// Fetch warehouse tables dynamically
$dw_tables = [];
$res = $conn_dw->query("SHOW TABLES");
if ($res) while ($row = $res->fetch_array()) $dw_tables[] = $row[0];
?>

<div class="page database-page">
    <div class="topbar">
        <h1>Analyst Data Warehouse View</h1>
        <div>
            <a class="btn secondary" href="analyst_dashboard.php">Back</a>
            <a class="btn" href="logout.php">Logout</a>
        </div>
    </div>

    <!-- SQL Query Box -->
    <section class="panel">
        <form method="POST">
            <div style="margin-bottom:10px;">
                <label>Warehouse Query</label>
            </div>
            <textarea name="sql_query" rows="6" style="width:100%;"><?= e($sqlQuery) ?></textarea>
            <button class="btn" type="submit" name="run_sql">Run Query</button>
        </form>

        <?php if (!empty($queryOut["error"])): ?>
            <div class="alert error" style="margin-top:10px;"><?= e($queryOut["error"]) ?></div>
        <?php endif; ?>

        <?php if (!empty($queryOut["html"])): ?>
            <div style="margin-top:10px;"><?= $queryOut["html"] ?></div>
        <?php endif; ?>
    </section>

    <div class="grid-2 wide" style="margin-top:20px; display:grid; grid-template-columns: 1fr 1fr; gap:20px;">
        <?php
        if (empty($dw_tables)) echo "<p>No tables found in the warehouse.</p>";

        foreach ($dw_tables as $t) {
            echo "<section class='panel'>";
            renderTable($conn_dw, $t);
            echo "</section>";
        }
        ?>
    </div>
</div>

<?php include "includes/footer.php"; ?>
