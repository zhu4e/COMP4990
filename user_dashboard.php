<?php
require_once "includes/config.php";
require_once "includes/auth.php";
session_start();
checkAuth();

// make sure user is normal user
if ($_SESSION['role'] != 'user') {
    die("You don't have access.");
}

$pageTitle = "User Dashboard";
include "includes/header.php";

// check view mode
if (isset($_GET['view'])) {
    $view = $_GET['view'];
} else {
    $view = 'sales';
}

// get filter values from url
$cat = isset($_GET['category']) ? $_GET['category'] : '';
$branch = isset($_GET['branch']) ? $_GET['branch'] : '';

// fetch dropdowns so we don't hardcode them
$cat_res = $conn_dw->query("SELECT DISTINCT Category FROM ProductDim WHERE Category IS NOT NULL");
$branch_res = $conn_dw->query("SELECT BranchKey, City FROM BranchDim");

$params = array(); 
$types = "";

if ($view == 'inventory') {
    // build inventory query
    $low = isset($_GET['low_stock']) ? true : false;

    $sql = "SELECT p.ProductName, p.Category, b.City as Branch, i.CurrentStock, i.RestockThreshold 
            FROM FactInventory i
            JOIN ProductDim p ON i.ProductKey = p.ProductKey
            JOIN BranchDim b ON i.BranchKey = b.BranchKey
            WHERE 1=1 ";

    if ($cat != '') { 
        $sql .= " AND p.Category = ? "; 
        $params[] = $cat; 
        $types .= "s"; 
    }
    if ($branch != '') { 
        $sql .= " AND i.BranchKey = ? "; 
        $params[] = $branch; 
        $types .= "i"; 
    }
    
    // low stock filter
    if ($low) { 
        $sql .= " AND i.CurrentStock < i.RestockThreshold "; 
    }

    $sql .= " ORDER BY i.CurrentStock ASC";

} else {
    // build sales query
    $year = isset($_GET['year']) ? $_GET['year'] : '';
    $min = isset($_GET['min_rev']) ? $_GET['min_rev'] : '';
    $max = isset($_GET['max_rev']) ? $_GET['max_rev'] : '';
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'rev_desc';
    
    $year_res = $conn_dw->query("SELECT DISTINCT Year FROM DatetimeDim ORDER BY Year DESC");

    $sql = "SELECT p.ProductName, p.Category, b.City as Branch, d.Year, SUM(f.Quantity) as TotalSold, SUM(f.Revenue) as TotalRevenue
            FROM FactSales f
            JOIN ProductDim p ON f.ProductKey = p.ProductKey
            JOIN BranchDim b ON f.BranchKey = b.BranchKey
            JOIN DatetimeDim d ON f.DateKey = d.DateKey
            WHERE 1=1 ";

    // normal where clauses
    if ($cat != '') { 
        $sql .= " AND p.Category = ? "; 
        $params[] = $cat; 
        $types .= "s"; 
    }
    if ($branch != '') { 
        $sql .= " AND f.BranchKey = ? "; 
        $params[] = $branch; 
        $types .= "i"; 
    }
    if ($year != '') { 
        $sql .= " AND d.Year = ? "; 
        $params[] = $year; 
        $types .= "i"; 
    }

    $sql .= " GROUP BY p.ProductName, p.Category, b.City, d.Year HAVING 1=1 ";

    // having clauses for aggregated math
    if ($min != '') { 
        $sql .= " AND SUM(f.Revenue) >= ? "; 
        $params[] = $min; 
        $types .= "d"; 
    } 
    if ($max != '') { 
        $sql .= " AND SUM(f.Revenue) <= ? "; 
        $params[] = $max; 
        $types .= "d"; 
    }

    if ($sort == 'rev_asc') { 
        $sql .= " ORDER BY TotalRevenue ASC "; 
    } elseif ($sort == 'qty_desc') { 
        $sql .= " ORDER BY TotalSold DESC "; 
    } else { 
        $sql .= " ORDER BY TotalRevenue DESC "; 
    }
}

// run it securely
$stmt = $conn_dw->prepare($sql);
if (!empty($params)) { 
    $stmt->bind_param($types, ...$params); 
}
$stmt->execute();
$results = $stmt->get_result();
?>

<div class="page database-page">
  <div class="topbar">
      <div class="topbar-left">
          <h1>User Data Explorer</h1>
          <p class="muted">Explore warehouse data.</p>
      </div>
      <div class="topbar-right">
          <a href="user_dashboard.php?view=sales" class="btn <?php if($view != 'sales') echo 'secondary'; ?>">Sales Data</a>
          <a href="user_dashboard.php?view=inventory" class="btn <?php if($view == 'sales') echo 'secondary'; ?>">Inventory Health</a>
      </div>
  </div>

  <div class="grid-2 wide" style="margin-top:20px; display:grid; grid-template-columns: 250px 1fr; gap:20px;">
    
    <section class="panel" style="padding: 15px;">
        <h3>Filters</h3>
        <form method="GET" action="user_dashboard.php">
            <input type="hidden" name="view" value="<?php echo htmlspecialchars($view); ?>">
            
            <p><b>Category</b></p>
            <select name="category" style="width:100%; padding:5px;">
                <option value="">-- All --</option>
                <?php while($c = $cat_res->fetch_assoc()) { ?>
                    <option value="<?php echo htmlspecialchars($c['Category']); ?>" <?php if($cat == $c['Category']) echo 'selected'; ?>><?php echo htmlspecialchars($c['Category']); ?></option>
                <?php } ?>
            </select>

            <p><b>Branch</b></p>
            <select name="branch" style="width:100%; padding:5px;">
                <option value="">-- All --</option>
                <?php while($b = $branch_res->fetch_assoc()) { ?>
                    <option value="<?php echo $b['BranchKey']; ?>" <?php if($branch == $b['BranchKey']) echo 'selected'; ?>><?php echo htmlspecialchars($b['City']); ?></option>
                <?php } ?>
            </select>

            <?php if ($view == 'sales') { ?>
                <p><b>Year</b></p>
                <select name="year" style="width:100%; padding:5px;">
                    <option value="">-- All --</option>
                    <?php while($y = $year_res->fetch_assoc()) { ?>
                        <option value="<?php echo $y['Year']; ?>" <?php if($year == $y['Year']) echo 'selected'; ?>><?php echo $y['Year']; ?></option>
                    <?php } ?>
                </select>

                <br><hr><br>

                <p><b>Min Revenue</b></p>
                <input type="number" step="0.01" name="min_rev" value="<?php echo htmlspecialchars($min); ?>" style="width:100%; padding:5px;">

                <p><b>Max Revenue</b></p>
                <input type="number" step="0.01" name="max_rev" value="<?php echo htmlspecialchars($max); ?>" style="width:100%; padding:5px;">

                <br><hr><br>

                <p><b>Sort By</b></p>
                <select name="sort" style="width:100%; padding:5px;">
                    <option value="rev_desc" <?php if($sort == 'rev_desc') echo 'selected'; ?>>Highest Revenue</option>
                    <option value="rev_asc" <?php if($sort == 'rev_asc') echo 'selected'; ?>>Lowest Revenue</option>
                    <option value="qty_desc" <?php if($sort == 'qty_desc') echo 'selected'; ?>>Most Units Sold</option>
                </select>
                
            <?php } else { ?>
                <br><hr><br>
                <p>
                    <input type="checkbox" name="low_stock" value="1" <?php if($low) echo 'checked'; ?>>
                    <b>Show Low Stock Only</b>
                </p>
            <?php } ?>

            <br><br>
            <button type="submit" class="btn" style="width:100%;">Apply</button>
            <a href="user_dashboard.php?view=<?php echo htmlspecialchars($view); ?>" style="display:block; text-align:center; margin-top:10px;">Clear</a>
        </form>
    </section>

    <section class="panel">
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Product</th>
              <th>Category</th>
              <th>Branch</th>
              <?php if ($view == 'sales') { ?>
                  <th>Year</th>
                  <th>Units Sold</th>
                  <th>Revenue</th>
              <?php } else { ?>
                  <th>Stock</th>
                  <th>Threshold</th>
                  <th>Status</th>
              <?php } ?>
            </tr>
          </thead>
          <tbody>
            <?php if($results->num_rows > 0) { ?>
                <?php while ($row = $results->fetch_assoc()) { ?>
                  <tr>
                    <td><?php echo htmlspecialchars($row['ProductName']); ?></td>
                    <td><?php echo htmlspecialchars($row['Category']); ?></td>
                    <td><?php echo htmlspecialchars($row['Branch']); ?></td>
                    
                    <?php if ($view == 'sales') { ?>
                        <td><?php echo $row['Year']; ?></td>
                        <td><?php echo $row['TotalSold']; ?></td>
                        <td>$<?php echo number_format($row['TotalRevenue'], 2); ?></td>
                    <?php } else { ?>
                        <td><?php echo $row['CurrentStock']; ?></td>
                        <td><?php echo $row['RestockThreshold']; ?></td>
                        <td>
                            <?php if ($row['CurrentStock'] < $row['RestockThreshold']) { ?>
                                <span style="color: red; font-weight: bold;">Low Stock</span>
                            <?php } else { ?>
                                <span style="color: green;">OK</span>
                            <?php } ?>
                        </td>
                    <?php } ?>
                  </tr>
                <?php } ?>
            <?php } else { ?>
                <tr><td colspan="6" style="text-align: center; padding: 20px;">No data found.</td></tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</div>

<?php include "includes/footer.php"; ?>
