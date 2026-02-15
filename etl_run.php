<?php
// etl_run.php (creates warehouse DB/tables if possible, then loads data)
require_once "includes/config.php";
require_once "includes/auth.php";

session_start();
checkAuth();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$pageTitle = "Run ETL";
include "includes/header.php";

function e($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
$log = [];
function logLine(&$log, $msg){ $log[] = $msg; }

function runQuery(mysqli $conn, string $sql, &$log){
  if (!$conn->query($sql)) {
    logLine($log, "❌ " . $conn->error . " | " . $sql);
    return false;
  }
  return true;
}

// ------------------
// Warehouse settings
// ------------------
// IMPORTANT: On student MyWeb, you may not be allowed to CREATE DATABASE.
// If CREATE DATABASE fails, create the DB using MyWeb control panel/phpMyAdmin,
// then set $WAREHOUSE_DB to that name.
$WAREHOUSE_DB = "keenanl_datawarehouse";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["run_etl"])) {

  // 1) Connect WITHOUT selecting a DB (so we can create/select it)
  logLine($log, "Connecting to MySQL...");
  $dw = new mysqli($db1_host, $db1_user, $db1_pass);
  if ($dw->connect_error) {
    logLine($log, "❌ Warehouse server connection failed: " . $dw->connect_error);
  } else {



    // 3) Select DB
    if (!$dw->select_db($WAREHOUSE_DB)) {
      logLine($log, "❌ Could not select warehouse DB '$WAREHOUSE_DB': " . $dw->error);
    } else {

      // 4) Create tables if missing
      logLine($log, "Ensuring warehouse tables exist...");

      $schema = [
        "CREATE TABLE IF NOT EXISTS BranchDim (
          BranchID INT AUTO_INCREMENT PRIMARY KEY,
          City VARCHAR(100) NOT NULL,
          Province VARCHAR(100) NOT NULL,
          Country VARCHAR(100) NOT NULL
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS CustomerDim (
          CustomerID INT AUTO_INCREMENT PRIMARY KEY,
          FullName VARCHAR(255) NOT NULL,
          Phone VARCHAR(50),
          Email VARCHAR(255)
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS ProductDim (
          ProductID INT AUTO_INCREMENT PRIMARY KEY,
          ProductName VARCHAR(255) NOT NULL,
          Category VARCHAR(120),
          UnitPrice DECIMAL(10,2) NOT NULL DEFAULT 0.00
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS DatetimeDim (
          DateID INT AUTO_INCREMENT PRIMARY KEY,
          FullDate DATE NOT NULL,
          UNIQUE KEY uq_full_date (FullDate)
        ) ENGINE=InnoDB",

        "CREATE TABLE IF NOT EXISTS FactSales (
          FactID INT AUTO_INCREMENT PRIMARY KEY,
          ProductID INT NOT NULL,
          CustomerID INT NOT NULL,
          DateID INT NOT NULL,
          BranchID INT NOT NULL,
          Quantity INT NOT NULL DEFAULT 0,
          Revenue DECIMAL(12,2) NOT NULL DEFAULT 0.00,
          KEY idx_prod (ProductID),
          KEY idx_cust (CustomerID),
          KEY idx_date (DateID),
          KEY idx_branch (BranchID),
          CONSTRAINT fk_fact_prod   FOREIGN KEY (ProductID)  REFERENCES ProductDim(ProductID),
          CONSTRAINT fk_fact_cust   FOREIGN KEY (CustomerID) REFERENCES CustomerDim(CustomerID),
          CONSTRAINT fk_fact_date   FOREIGN KEY (DateID)     REFERENCES DatetimeDim(DateID),
          CONSTRAINT fk_fact_branch FOREIGN KEY (BranchID)   REFERENCES BranchDim(BranchID)
        ) ENGINE=InnoDB",
      ];

      foreach ($schema as $sql) runQuery($dw, $sql, $log);

      // 5) Reset warehouse (truncate in FK-safe order)
      logLine($log, "Resetting warehouse data...");
      runQuery($dw, "SET FOREIGN_KEY_CHECKS=0", $log);
      foreach (["FactSales","CustomerDim","ProductDim","DatetimeDim","BranchDim"] as $t) {
        runQuery($dw, "TRUNCATE TABLE $t", $log);
      }
      runQuery($dw, "SET FOREIGN_KEY_CHECKS=1", $log);

      // 6) Load BranchDim
      logLine($log, "Loading BranchDim...");
      runQuery($dw, "INSERT INTO BranchDim (City, Province, Country) VALUES
        ('Branch1City','Province1','Country1'),
        ('Branch2City','Province2','Country2')", $log);

      // 7) Load CustomerDim (DB1 + DB2)
      logLine($log, "Loading CustomerDim...");
      $res1 = $conn_db1->query("SELECT Cname, Phone, Email FROM Customer");
      while($row = $res1->fetch_assoc()){
        $stmt = $dw->prepare("INSERT INTO CustomerDim (FullName, Phone, Email) VALUES (?,?,?)");
        $stmt->bind_param("sss", $row["Cname"], $row["Phone"], $row["Email"]);
        $stmt->execute();
        $stmt->close();
      }

      $res2 = $conn_db2->query("SELECT Cname, PhoneNum, Email FROM CustomerInfo");
      while($row = $res2->fetch_assoc()){
        $stmt = $dw->prepare("INSERT INTO CustomerDim (FullName, Phone, Email) VALUES (?,?,?)");
        $stmt->bind_param("sss", $row["Cname"], $row["PhoneNum"], $row["Email"]);
        $stmt->execute();
        $stmt->close();
      }

      // 8) Load ProductDim (DB1 + DB2)
      logLine($log, "Loading ProductDim...");
      $p1 = $conn_db1->query("SELECT Pname, Category, Price FROM Product");
      while($row = $p1->fetch_assoc()){
        $stmt = $dw->prepare("INSERT INTO ProductDim (ProductName, Category, UnitPrice) VALUES (?,?,?)");
        $stmt->bind_param("ssd", $row["Pname"], $row["Category"], $row["Price"]);
        $stmt->execute();
        $stmt->close();
      }

      $p2 = $conn_db2->query("SELECT Pname, Type, Price FROM Item");
      while($row = $p2->fetch_assoc()){
        $stmt = $dw->prepare("INSERT INTO ProductDim (ProductName, Category, UnitPrice) VALUES (?,?,?)");
        $stmt->bind_param("ssd", $row["Pname"], $row["Type"], $row["Price"]);
        $stmt->execute();
        $stmt->close();
      }

      // 9) Load DatetimeDim
      logLine($log, "Loading DatetimeDim...");
      $dates = [];

      $d1 = $conn_db1->query("SELECT DISTINCT PurchaseDate FROM Purchases");
      while($r = $d1->fetch_assoc()) $dates[] = $r["PurchaseDate"];

      $d2 = $conn_db2->query("SELECT DISTINCT PurchaseDate FROM Purchase");
      while($r = $d2->fetch_assoc()) $dates[] = $r["PurchaseDate"];

      $dates = array_values(array_unique($dates));
      sort($dates);

      foreach($dates as $dt){
        $stmt = $dw->prepare("INSERT INTO DatetimeDim (FullDate) VALUES (?)");
        $stmt->bind_param("s", $dt);
        $stmt->execute();
        $stmt->close();
      }

      // 10) Build key maps
      logLine($log, "Building key maps...");
      $custMap = [];
      $c = $dw->query("SELECT CustomerID, FullName FROM CustomerDim");
      while($r = $c->fetch_assoc()) $custMap[$r["FullName"]] = (int)$r["CustomerID"];

      $prodMap = [];
      $p = $dw->query("SELECT ProductID, ProductName FROM ProductDim");
      while($r = $p->fetch_assoc()) $prodMap[$r["ProductName"]] = (int)$r["ProductID"];

      $dateMap = [];
      $d = $dw->query("SELECT DateID, FullDate FROM DatetimeDim");
      while($r = $d->fetch_assoc()) $dateMap[$r["FullDate"]] = (int)$r["DateID"];

      // 11) Load FactSales
      logLine($log, "Loading FactSales...");

      // DB1 -> BranchID 1
      $f1 = $conn_db1->query("SELECT Cid, Pid, Quantity, TotalAmount, PurchaseDate FROM Purchases");
      while($r = $f1->fetch_assoc()){
        $cid = (int)$r["Cid"];
        $pid = (int)$r["Pid"];
        $cn = $conn_db1->query("SELECT Cname FROM Customer WHERE Cid=$cid")->fetch_assoc()["Cname"];
        $pn = $conn_db1->query("SELECT Pname FROM Product WHERE Pid=$pid")->fetch_assoc()["Pname"];

        $stmt = $dw->prepare("INSERT INTO FactSales (ProductID, CustomerID, DateID, BranchID, Quantity, Revenue)
                              VALUES (?,?,?,?,?,?)");
        $productID  = $prodMap[$pn];
        $customerID = $custMap[$cn];
        $dateID     = $dateMap[$r["PurchaseDate"]];
        $branchID   = 1;
        $qty        = (int)$r["Quantity"];
        $rev        = (float)$r["TotalAmount"];
        $stmt->bind_param("iiiiid", $productID, $customerID, $dateID, $branchID, $qty, $rev);
        $stmt->execute();
        $stmt->close();
      }

      // DB2 -> BranchID 2
      $f2 = $conn_db2->query("SELECT Cid, Pid, Qty, Amount, PurchaseDate FROM Purchase");
      while($r = $f2->fetch_assoc()){
        $cid = (int)$r["Cid"];
        $pid = (int)$r["Pid"];
        $cn = $conn_db2->query("SELECT Cname FROM CustomerInfo WHERE Cid=$cid")->fetch_assoc()["Cname"];
        $pn = $conn_db2->query("SELECT Pname FROM Item WHERE Pid=$pid")->fetch_assoc()["Pname"];

        $stmt = $dw->prepare("INSERT INTO FactSales (ProductID, CustomerID, DateID, BranchID, Quantity, Revenue)
                              VALUES (?,?,?,?,?,?)");
        $productID  = $prodMap[$pn];
        $customerID = $custMap[$cn];
        $dateID     = $dateMap[$r["PurchaseDate"]];
        $branchID   = 2;
        $qty        = (int)$r["Qty"];
        $rev        = (float)$r["Amount"];
        $stmt->bind_param("iiiiid", $productID, $customerID, $dateID, $branchID, $qty, $rev);
        $stmt->execute();
        $stmt->close();
      }

      logLine($log, "✅ ETL COMPLETE!");
    }
  }
}
?>

<div class="page database-page">
  <div class="topbar">
    <div class="topbar-left">
      <h1>Run ETL</h1>
      <p class="muted">Creates the warehouse (if permitted) and loads DB1 + DB2 into it.</p>
    </div>
    <div class="topbar-right">
      <a class="btn secondary" href="index.php">Back to Dashboard</a>
      <a class="btn" href="logout.php">Logout</a>
    </div>
  </div>

  <section class="panel" style="padding:16px;">
    <form method="POST">
      <button class="btn" type="submit" name="run_etl" value="1">Run ETL Now</button>
    </form>

    <?php if (!empty($log)): ?>
      <div style="margin-top:14px;" class="table-wrap">
        <table class="data-table" style="min-width:unset;">
          <thead><tr><th>ETL Log</th></tr></thead>
          <tbody>
            <?php foreach($log as $line): ?>
              <tr><td><?= e($line) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php include "includes/footer.php"; ?>
