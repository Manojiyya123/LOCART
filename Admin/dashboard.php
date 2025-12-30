<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "locart_db";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);
if ($conn->connect_error) {
    // If DB connection fails, set counts to 0 and capture error
    $shops = 0;
    $customers = 0;
    $missing = ['database connection failed: ' . $conn->connect_error];
} else {
    $shops = 0;
    $customers = 0;

  function table_exists($conn, $table) {
    $tbl = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '" . $tbl . "'");
    return ($res && $res->num_rows > 0);
  }

  $missing = [];
  $pendingRequests = 0;
  $totalReports = 0;
  // Accept either 'shop' or 'shops'
  if (table_exists($conn, 'shops')) {
    $r = $conn->query("SELECT COUNT(*) AS c FROM shops");
    if ($r) { $row = $r->fetch_assoc(); $shops = $row['c']; }
  } elseif (table_exists($conn, 'shop')) {
    $r = $conn->query("SELECT COUNT(*) AS c FROM shop");
    if ($r) { $row = $r->fetch_assoc(); $shops = $row['c']; }
  } else { $missing[] = 'shop(s)'; }

  // Accept either 'customer' or 'customers'
  if (table_exists($conn, 'customers')) {
    $r = $conn->query("SELECT COUNT(*) AS c FROM customers");
    if ($r) { $row = $r->fetch_assoc(); $customers = $row['c']; }
  } elseif (table_exists($conn, 'customer')) {
    $r = $conn->query("SELECT COUNT(*) AS c FROM customer");
    if ($r) { $row = $r->fetch_assoc(); $customers = $row['c']; }
  } else { $missing[] = 'customer(s)'; }

  // Calculate pending requests from shoprequest/shoprequests if available (prefer explicit requests table)
  if (table_exists($conn, 'shoprequest') || table_exists($conn, 'shoprequests')) {
    $rtbl = table_exists($conn, 'shoprequest') ? 'shoprequest' : 'shoprequests';
    // Count only requests with status = 'not permitted' (also tolerate 'not-permitted')
    $r = $conn->query("SELECT COUNT(*) AS c FROM `".$rtbl."` WHERE `status` IN ('not permitted','not-permitted')");
    if ($r) { $row = $r->fetch_assoc(); $pendingRequests = $row['c']; }
  } else {
    // Fallback: count shops with status not 'active'
    if (table_exists($conn, 'shop') || table_exists($conn, 'shops')) {
      $tbl = table_exists($conn, 'shop') ? 'shop' : 'shops';
      $r = $conn->query("SELECT COUNT(*) AS c FROM `".$tbl."` WHERE `status` IS NOT NULL AND `status` <> 'active'");
      if ($r) { $row = $r->fetch_assoc(); $pendingRequests = $row['c']; }
    }
  }

  // Calculate total reports
  if (table_exists($conn, 'report') || table_exists($conn, 'reports')) {
    $rtbl = table_exists($conn, 'report') ? 'report' : 'reports';
    $r = $conn->query("SELECT COUNT(*) AS c FROM `".$rtbl."`");
    if ($r) { $row = $r->fetch_assoc(); $totalReports = $row['c']; }
  }

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LOCART Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
  <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- Sidebar (Desktop) -->
<div class="sidebar d-none d-md-block">
  <h5><span class="logo">L0c<span class="accent">ArT</span></span> .admin</h5>
  <a href="dashboard.php" class="active">📊 Dashboard</a>
  <a href="shop-requests.php">🛒 Shop Requests</a>
  <a href="customers.php">👤 Customers</a>
  <a href="shops.php">🏬 Shops</a>
  <a href="reports.php">⚠️ Reports</a>
</div>

<!-- Mobile Navbar -->
<nav class="navbar navbar-dark d-md-none w-100">
  <div class="container-fluid" style="background-color:#212121;">
    <button class="btn btn-outline-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
      ☰ Menu
    </button>
    <span class="navbar-brand ms-2">
      <span class="logo">L0c<span class="accent">ArT</span></span> .admin
    </span>
  </div>
</nav>

<!-- Main Content -->
<div class="main-content">
  <div class="topbar d-flex justify-content-between align-items-center mb-4">
    <h4>Welcome, Admin!</h4>
    <a href="login.php?action=logout" class="btn btn-outline-primary btn-sm">Logout</a>
  </div>

  <!-- Alert -->
  <div class="alert alert-info alert-dismissible fade show" role="alert">
    🔔 You have <b><?php echo htmlspecialchars($pendingRequests); ?> new shop request<?php echo $pendingRequests==1? '':'s'; ?></b> pending approval!
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>

  <!-- Stats Cards -->
  <!-- Stats Cards -->
<div class="row mb-4">
  <div class="col-12 col-sm-6 col-md-3 mb-3">
    <div class="card p-3 text-center shadow-sm">
      <i class="bi bi-shop fs-1 text-primary"></i>
      <h5>Total Shops</h5>
  <h2 id="totalShops"><?php echo htmlspecialchars($shops); ?></h2>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-md-3 mb-3">
    <div class="card p-3 text-center shadow-sm">
      <i class="bi bi-people fs-1 text-success"></i>
      <h5>Total Customers</h5>
  <h2 id="totalCustomers"><?php echo htmlspecialchars($customers); ?></h2>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-md-3 mb-3">
    <div class="card p-3 text-center shadow-sm">
      <i class="bi bi-hourglass-split fs-1 text-warning"></i>
      <h5>Pending Requests</h5>
  <h2 id="pendingRequests"><?php echo htmlspecialchars($pendingRequests); ?></h2>
    </div>
  </div>
  <div class="col-12 col-sm-6 col-md-3 mb-3">
    <div class="card p-3 text-center shadow-sm">
      <i class="bi bi-flag fs-1 text-danger"></i>
      <h5>Total Reports</h5>
  <h2 id="totalReports"><?php echo htmlspecialchars($totalReports); ?></h2>
    </div>
  </div>
</div>


  <!-- Chart and Recent Activity side by side -->
 <div class="row mb-4">
  <!-- Chart Column -->
  <div class="col-12 col-md-6 mb-3" style="max-width:550px;">
    <div class="card p-3">
      <h5>Statistics Chart</h5>
      <canvas id="myChart"></canvas>
    </div>
  </div>

  <!-- Recent Activity Column -->
  <div class="col-12 col-md-6 mb-3">
    <div class="card p-3">
      <h5>Recent Activity</h5>
    <ul class="list-group list-group-flush" id="recent-activity-list">
<?php
// Handle inline 'Close actions' POST (prune to latest 5 safely)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_actions']) && $_POST['close_actions'] === '1') {
  $pruned = 0;
  $maxActions = 5;
  // prune until <= maxActions or no deletable rows
  while (true) {
    $cntRes = $conn->query("SELECT COUNT(*) AS cnt FROM action");
    $cntRow = $cntRes ? $cntRes->fetch_assoc() : null;
    $total = $cntRow ? (int)$cntRow['cnt'] : 0;
    if ($total <= $maxActions) break;
    $oldRes = $conn->query("SELECT actionid FROM action WHERE actionid NOT IN (SELECT DISTINCT action_id FROM report WHERE action_id IS NOT NULL) ORDER BY date ASC, actionid ASC LIMIT 1");
    if (!$oldRes || $oldRes->num_rows === 0) break;
    $oldId = (int)$oldRes->fetch_assoc()['actionid'];
    $conn->query("DELETE FROM action WHERE actionid = " . $oldId);
    $pruned++;
  }
  $close_message = $pruned > 0 ? "$pruned action(s) removed" : "No actions could be removed (referenced)";
}

// Fetch all actions newest first
$allActions = [];
$actRes = $conn->query("SELECT actionid, action_name, reason, date FROM action ORDER BY date DESC, actionid DESC");
if ($actRes && $actRes->num_rows > 0) {
  while ($act = $actRes->fetch_assoc()) {
    $allActions[] = $act;
  }
}

if (empty($allActions)) {
  echo "<li class='list-group-item'>L0cArT's actions</li>\n";
} else {
  // show first 5, rest hidden in collapse
  $shown = 5;
  for ($i = 0; $i < count($allActions); $i++) {
    $act = $allActions[$i];
    $name = htmlspecialchars($act['action_name']);
    $reason = htmlspecialchars($act['reason']);
    $date = htmlspecialchars($act['date']);
    $title = $reason ? " data-bs-toggle=\"tooltip\" title=\"" . $reason . "\"" : '';
    if ($i < $shown) {
      echo "<li class='list-group-item'{$title}>" . $name . " <small class='text-muted d-block'>" . $date . "</small></li>\n";
    } else {
      // hidden items inside collapse
      if ($i === $shown) echo "<div class='collapse' id='actionsCollapse'>\n";
      echo "<li class='list-group-item'{$title}>" . $name . " <small class='text-muted d-block'>" . $date . "</small></li>\n";
      if ($i === count($allActions) - 1) echo "</div>\n";
    }
  }
  // See more controls (collapse) — show only, no Close button
  echo "</ul>"; // close the list temporarily to add controls
  echo "<div class='mt-2'>";
  if (count($allActions) > $shown) {
    echo "<a id='actionsToggle' class='btn btn-link p-0' data-bs-toggle='collapse' href='#actionsCollapse' role='button' aria-expanded='false' aria-controls='actionsCollapse'>See more</a>";
  }
  echo "</div>\n";
}
?>
    </ul>
    </div>
  </div>
</div>

<?php
// Close DB connection after all queries are done
if (isset($conn) && $conn) {
    $conn->close();
}
?>


<!-- Mobile Offcanvas Sidebar -->
<div class="offcanvas offcanvas-start" id="mobileSidebar">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title"><span class="logo">L0c<span class="accent">ArT</span></span> .admin</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body sidebar-links">
    <a href="dashboard.php" class="active">📊 Dashboard</a>
  <a href="shop-requests.php">🛒 Shop Requests</a>
  <a href="customers.php">👤 Customers</a>
    <a href="shops.html">🏬 Shops</a>
  <a href="reports.php">⚠️ Reports</a>
  </div>
</div>

<?php if (!empty($missing)): ?>
  <div class="container mt-3">
    <div class="alert alert-warning">
      <strong>Warning:</strong> The following database tables are missing: <?php echo htmlspecialchars(implode(', ', $missing)); ?>.
      <br>
      Quick SQL to create sample tables (adjust types as needed):
      <pre style="background:#f8f9fa;padding:8px;border-radius:4px">CREATE TABLE shops (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255));
CREATE TABLE customers (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255));</pre>
      After creating tables, reload this page.
    </div>
  </div>
<?php endif; ?>

<!-- Chart JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Use server-side computed stats (no Node backend required)
(function(){
  const stats = <?php echo json_encode([
    'shops' => (int)$shops,
    'customers' => (int)$customers,
    'pending' => (int)$pendingRequests,
    'reports' => (int)$totalReports
  ]); ?>;

  document.getElementById('totalShops').textContent = stats.shops;
  document.getElementById('totalCustomers').textContent = stats.customers;
  document.getElementById('pendingRequests').textContent = stats.pending;
  document.getElementById('totalReports').textContent = stats.reports;

  // Update chart using PHP-provided numbers
  const ctx = document.getElementById('myChart');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['Shops', 'Customers', 'Requests', 'Reports'],
      datasets: [{
        data: [stats.shops, stats.customers, stats.pending, stats.reports],
        backgroundColor: ['#007bff','#28a745','#ffc107','#dc3545']
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true } }
    }
  });
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Initialize Bootstrap tooltips for recent activity reasons
  document.addEventListener('DOMContentLoaded', function () {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl)
    })
    // Toggle See more / See less text
    var actionsToggle = document.getElementById('actionsToggle');
    var actionsCollapse = document.getElementById('actionsCollapse');
    if (actionsToggle && actionsCollapse) {
      actionsCollapse.addEventListener('shown.bs.collapse', function () { actionsToggle.textContent = 'See less'; });
      actionsCollapse.addEventListener('hidden.bs.collapse', function () { actionsToggle.textContent = 'See more'; });
    }
  });
</script>
</body>
</html>
