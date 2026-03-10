<?php
require_once "includes/config.php";
require_once "includes/auth.php";
session_start();
checkAuth();
requireRole("analyst"); // only analysts

$pageTitle = "Analyst Database View";
include "includes/header.php";

// Utility function
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
$db1_tables = ["Customer","Product","Purchases"];
$db2_tables = ["Customers","CustomerInfo","Item","Purchase"];

$dbChoice = $_POST["db_choice"] ?? "db1";
$sqlQuery = $_POST["sql_query"] ?? "";

// Run query if submitted
$queryOut = [];
if ($_SERVER["REQUEST_METHOD"] === "POST" && !empty($_POST["run_sql"])) {
    $connSelected = ($dbChoice === "db2") ? $conn_db2 : $conn_db1;
    $queryOut = renderQueryResult($connSelected, $sqlQuery);
}

?>

<div class="page database-page">
    <div class="topbar">
        <h1>Analyst Database View</h1>
        <div>
            <a class="btn secondary" href="analyst_index.php">Back</a>
            <a class="btn" href="logout.php">Logout</a>
        </div>
    </div>

    <!-- SQL Query Box -->
    <section class="panel">
        <form method="POST">
            <div style="margin-bottom:10px;">
                <label>Database:</label>
                <select name="db_choice">
                    <option value="db1" <?= ($dbChoice==="db1")?"selected":"" ?>>Database 1</option>
                    <option value="db2" <?= ($dbChoice==="db2")?"selected":"" ?>>Database 2</option>
                </select>
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