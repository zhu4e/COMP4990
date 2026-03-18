<?php
require_once "includes/config.php";
require_once "includes/auth.php";
session_start();
checkAuth();
requireRole("admin");

$pageTitle = "Admin Dashboard";
include "includes/header.php";

// =======================
// Initialize metrics
// =======================
$totalOrders = 0;
$totalRevenue = 0;
$totalCustomers = 0;
$lowStockItems = 0;

// =======================
// FactSales Metrics
// =======================
$query_sales = "
    SELECT 
        COUNT(DISTINCT SourceOrderID) AS total_orders,
        SUM(Revenue) AS total_revenue,
        COUNT(DISTINCT CustomerKey) AS total_customers
    FROM FactSales
";

$result_sales = $conn_dw->query($query_sales);

if ($result_sales && $row = $result_sales->fetch_assoc()) {
    $totalOrders = $row['total_orders'] ?? 0;
    $totalRevenue = $row['total_revenue'] ?? 0;
    $totalCustomers = $row['total_customers'] ?? 0;
}

// =======================
// FactInventory Metric
// =======================
$query_inventory = "
    SELECT COUNT(*) AS low_stock_items
    FROM FactInventory
    WHERE CurrentStock <= RestockThreshold
";

$result_inventory = $conn_dw->query($query_inventory);

if ($result_inventory && $row = $result_inventory->fetch_assoc()) {
    $lowStockItems = $row['low_stock_items'] ?? 0;
}

// =======================
// Revenue Over Time (for chart)
// =======================
$dates = [];
$revenues = [];

$query_chart = "
    SELECT d.FullDate, SUM(f.Revenue) AS daily_revenue
    FROM FactSales f
    JOIN DatetimeDim d ON f.DateKey = d.DateKey
    GROUP BY d.FullDate
    ORDER BY d.FullDate
";

$result_chart = $conn_dw->query($query_chart);

if ($result_chart) {
    while ($row = $result_chart->fetch_assoc()) {
        $dates[] = $row['FullDate'];
        $revenues[] = $row['daily_revenue'];
    }
}
?>

<div class="center-card">

  <h1>Welcome to the E-Commerce Data Warehouse Dashboard</h1>
  <p>You are logged in as <strong>Administrator</strong>.</p>

  <div class="dashboard-section">
    <h2>Key Metrics</h2>

    <!-- 2x2 GRID -->
    <div style="
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-top: 20px;
    ">

        <div style="background:#f5f5f5;padding:20px;border-radius:10px;text-align:center;font-weight:bold;">
            Total Orders: <?php echo number_format($totalOrders); ?>
        </div>

        <div style="background:#f5f5f5;padding:20px;border-radius:10px;text-align:center;font-weight:bold;">
            Total Revenue: $<?php echo number_format($totalRevenue, 2); ?>
        </div>

        <div style="background:#f5f5f5;padding:20px;border-radius:10px;text-align:center;font-weight:bold;">
            Total Customers: <?php echo number_format($totalCustomers); ?>
        </div>

        <div style="background:#f5f5f5;padding:20px;border-radius:10px;text-align:center;font-weight:bold;
            color: <?php echo ($lowStockItems > 0) ? 'red' : 'black'; ?>">
            Low Stock Items: <?php echo number_format($lowStockItems); ?>
        </div>

    </div>

    <!-- ✅ CHART -->
    <div style="margin-top:40px;">
        <h2>Revenue Over Time</h2>
        <canvas id="revenueChart"></canvas>
    </div>

  </div>

  <div class="dashboard-section">
    <h2>Operational Databases</h2>
    <a class="btn" href="admin_database_view.php">Manage Database</a>
  </div>

  <div class="dashboard-section">
    <h2>Data Warehouse</h2>
    <a class="btn" href="admin_data_warehouse_view.php">Manage Warehouse</a>
  </div>

  <div class="dashboard-section">
    <h2>Add Users</h2>
    <a class="btn" href="admin_users.php">Manage Users</a>
  </div>

  <a class="logout-link" href="logout.php">Logout</a>

</div>

<!-- ✅ Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
const ctx = document.getElementById('revenueChart').getContext('2d');

const revenueChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($dates); ?>,
        datasets: [{
            label: 'Revenue',
            data: <?php echo json_encode($revenues); ?>,
            borderWidth: 2,
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: true
            }
        }
    }
});
</script>

<?php include "includes/footer.php"; ?>
