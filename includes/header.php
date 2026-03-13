<?php
require_once "includes/auth.php";

if (!isset($pageTitle)) {
  $pageTitle = "E-Commerce Analytics";
}

// Build a base path
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), "/\\");
if ($basePath === "." || $basePath === "\\" || $basePath === "/") {
  $basePath = "";
}


$currentPage = basename($_SERVER["PHP_SELF"]);
$bodyClass   = pathinfo($currentPage, PATHINFO_FILENAME);
?>

<link rel="stylesheet" href="<?php echo $basePath; ?>/styles.css">

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title><?php echo htmlspecialchars($pageTitle); ?></title>


</head>

<body>

<?php if (basename($_SERVER['PHP_SELF']) !== 'login.php'): ?>
    <header class="site-header">

        <div class="header-left">
            <h1 class="site-title">📊 Ecommerce Data Warehouse</h1>
        </div>

        <nav class="header-nav">          
          <?php if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'admin'): ?>
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="admin_database_view.php">Databases</a>
            <a href="admin_data_warehouse_view.php">Warehouse</a>
            <a href="admin_users.php">Add Users</a>
          <?php endif; ?>

          <?php if (isset($_SESSION['role']) && strtolower($_SESSION['role']) === 'analyst'): ?>
            <a href="analyst_dashboard.php">Dashboard</a>
            <a href="analyst_database_view.php">Databases</a>
            <a href="analyst_data_warehouse_view.php">Warehouse</a>
          <?php endif; ?>


        </nav>

        <div class="header-right">
            <span class="user-badge">
                <?php echo htmlspecialchars($_SESSION['role']); ?>
            </span>
            <a class="logout" href="logout.php">Logout</a>
        </div>

    </header>
<?php endif; ?>

<div class="container">

<?php
// Container for normal pages (keep DB pages full width)
if ($currentPage !== "database_view.php" && $currentPage !== "data_warehouse_view.php") {
  echo '<div class="container">';
}
?>
