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
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($pageTitle); ?></title>

  <!-- Always-correct CSS path + cache-buster -->
  <link rel="stylesheet" href="<?php echo $basePath; ?>/styles.css?v=<?php echo time(); ?>">
</head>

<body class="<?php echo htmlspecialchars($bodyClass); ?>">

<?php
// Container for normal pages (keep DB pages full width)
if ($currentPage !== "database_view.php" && $currentPage !== "data_warehouse_view.php") {
  echo '<div class="container">';
}
?>