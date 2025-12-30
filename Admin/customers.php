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
    die('DB connection failed: ' . $conn->connect_error);
}

// Totals
$totalCustomers = 0;
$activeCustomers = 0;
$inactiveCustomers = 0;
$customers = [];

$r = $conn->query("SELECT COUNT(*) AS c FROM customer");
if ($r) { $row = $r->fetch_assoc(); $totalCustomers = (int)$row['c']; }
// The customer table does not have an explicit status column in this schema.
// For now treat all customers as active unless you have a status field to check.
$activeCustomers = $totalCustomers;
$inactiveCustomers = max(0, $totalCustomers - $activeCustomers);

// Fetch customers (limit to 500 to avoid huge pages)
$res = $conn->query("SELECT customer_id, first_name, last_name, phone, email, city, pincode FROM customer ORDER BY customer_id DESC LIMIT 500");
if ($res) {
    while ($c = $res->fetch_assoc()) {
        $customers[] = $c;
    }
}

$conn->close();

function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customers - LOCART Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container-fluid">
  <div class="row">
    <!-- Mobile Navbar -->
    <nav class="navbar navbar-dark d-md-none w-100" style="background-color:#212121;">
      <div class="container-fluid">
        <button class="btn btn-outline-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
          ☰ Menu
        </button>
        <span class="navbar-brand ms-2">
          <span class="logo">L0c<span class="accent">ArT</span></span> .admin
        </span>
      </div>
    </nav>

    <!-- Desktop Sidebar -->
    <div class="col-md-2 d-none d-md-block sidebar position-fixed vh-100">
      <h5><span class="logo">L0c<span class="accent">ArT</span></span> .admin</h5>
      <a href="dashboard.php">📊 Dashboard</a>
      <a href="shop-requests.php">🛒 Shop Requests</a>
      <a href="customers.php" class="active">👤 Customers</a>
      <a href="shops.php">🏬 Shops</a>
      <a href="reports.php">⚠️ Reports</a>
    </div>

    <!-- Main Content -->
    <div class="col-md-10 offset-md-2 p-4">
      <!-- Topbar -->
      <div class="topbar d-flex justify-content-between align-items-center mb-4">
        <h4>Customers List</h4>
      </div>

      <!-- Stats Cards -->
      <div class="row mb-4">
        <div class="col-6 col-md-3 mb-3">
          <div class="card p-3 text-center shadow-sm">
            <i class="bi bi-people fs-1 text-success"></i>
            <h5>Total Customers</h5>
            <h2><?php echo h($totalCustomers); ?></h2>
          </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
          <div class="card p-3 text-center shadow-sm">
            <i class="bi bi-person-check fs-1 text-primary"></i>
            <h5>Active Customers</h5>
            <h2><?php echo h($activeCustomers); ?></h2>
          </div>
        </div>
        <div class="col-6 col-md-3 mb-3">
          <div class="card p-3 text-center shadow-sm">
            <i class="bi bi-person-x fs-1 text-warning"></i>
            <h5>Inactive Customers</h5>
            <h2><?php echo h($inactiveCustomers); ?></h2>
          </div>
        </div>
      </div>

      <!-- Customers Table -->
      <div class="table-responsive">
        <table class="table table-bordered table-hover">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Customer Name</th>
              <th>Email</th>
              <th>Phone</th>
              <th>Joined On</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $i = 0;
            foreach ($customers as $c) {
                $i++;
                $name = trim((($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')));
                $email = $c['email'] ?? '';
                $phone = $c['phone'] ?? '';
                // no created/join date in schema, leave blank or show placeholder
                $joined = '';
                $statusLabel = 'Active';
                $statusClass = 'bg-success';
                echo '<tr>';
                echo '<td>'.h($i).'</td>';
                echo '<td>'.h($name).'</td>';
                echo '<td>'.h($email).'</td>';
                echo '<td>'.h($phone).'</td>';
                echo '<td>'.h($joined).'</td>';
                echo '<td><span class="badge '.h($statusClass).'">'.h($statusLabel).'</span></td>';
                echo '<td>';
                echo '<button class="btn btn-info btn-sm">View</button> ';
                echo '<button class="btn btn-warning btn-sm">Send Alert</button> ';
                echo '<button class="btn btn-secondary btn-sm">Disable</button> ';
                echo '<button class="btn btn-danger btn-sm">Delete</button>';
                echo '</td>';
                echo '</tr>';
            }
            if ($i === 0) {
                echo '<tr><td colspan="7" class="text-center">No customers found.</td></tr>';
            }
            ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Mobile Offcanvas Sidebar -->
<div class="offcanvas offcanvas-start text-white bg-dark" id="mobileSidebar">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title"><span class="logo">L0c<span class="accent">ArT</span></span> .admin</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body sidebar-links">
    <a href="dashboard.php">📊 Dashboard</a>
    <a href="shop-requests.php">🛒 Shop Requests</a>
    <a href="customers.php" class="active">👤 Customers</a>
    <a href="shops.php">🏬 Shops</a>
    <a href="reports.php">⚠️ Reports</a>
  </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Live update: poll API every 10 seconds and refresh counts and table rows
async function fetchCustomers() {
  try {
    const res = await fetch('api/customers.php');
    if (!res.ok) return;
    const data = await res.json();
    if (data && data.counts) {
      document.querySelectorAll('.card h2')[0].textContent = data.counts.total;
      document.querySelectorAll('.card h2')[1].textContent = data.counts.active;
      document.querySelectorAll('.card h2')[2].textContent = data.counts.inactive;
    }
    if (data && Array.isArray(data.rows)) {
      const tbody = document.querySelector('table.table tbody');
      tbody.innerHTML = '';
      let i = 0;
      data.rows.forEach(c => {
        i++;
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${i}</td>
          <td>${(c.first_name||'') + ' ' + (c.last_name||'')}</td>
          <td>${c.email||''}</td>
          <td>${c.phone||''}</td>
          <td></td>
          <td><span class="badge bg-success">Active</span></td>
          <td>
            <button class="btn btn-info btn-sm">View</button>
            <button class="btn btn-warning btn-sm">Send Alert</button>
            <button class="btn btn-secondary btn-sm">Disable</button>
            <button class="btn btn-danger btn-sm">Delete</button>
          </td>
        `;
        tbody.appendChild(tr);
      });
      if (i === 0) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td colspan="7" class="text-center">No customers found.</td>';
        tbody.appendChild(tr);
      }
    }
  } catch (e) {
    console.error('fetchCustomers error', e);
  }
}
fetchCustomers();
setInterval(fetchCustomers, 10000);
</script>
</body>
</html>
