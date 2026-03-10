<?php
require_once "includes/config.php";
require_once "includes/auth.php";
session_start();
checkAuth();
requireRole("admin");

$pageTitle = "Admin Data Warehouse View";
include "includes/header.php";

function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
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
        <h1>Admin Data Warehouse View</h1>
        <div>
            <a class="btn secondary" href="admin_index.php">Back</a>
            <a class="btn" href="logout.php">Logout</a>
        </div>
    </div>

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