<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports - LOCART Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@700&display=swap" rel="stylesheet">
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

    <!-- Sidebar (Desktop) -->
    <div class="col-md-2 sidebar d-none d-md-block position-fixed h-100">
      <h5 class="offcanvas-title"><span class="logo">L0c<span class="accent">ArT</span></span> .admin</h5>
      <a href="dashboard.php">📊 Dashboard</a>
      <a href="shop-requests.php">🛒 Shop Requests</a>
      <a href="customers.php">👤 Customers</a>
      <a href="shops.php">🏬 Shops</a>
      <a href="reports.php" class="active">⚠️ Reports</a>
    </div>

    <!-- Main Content -->
    <div class="col-md-10 offset-md-2 p-3">

      <!-- Topbar -->
      <div class="topbar d-flex justify-content-between align-items-center mb-4">
        <h4>Reports Management</h4>
      </div>

      <!-- Table -->
      <div class="table-responsive">
        <table class="table table-striped table-hover" id="reportsTable">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Report Type</th>
              <th>Reported Profile</th>
              <th>Reason</th>
              <th>Reported By</th>
              <th>Reported On</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr><td colspan="7">Loading...</td></tr>
          </tbody>
        </table>
      </div>

    </div>
  </div>
</div>

<!-- Mobile Sidebar -->
<div class="offcanvas offcanvas-start" id="mobileSidebar">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title"><span class="logo">L0c<span class="accent">ArT</span></span> .admin</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body sidebar-links">
    <a href="dashboard.php">📊 Dashboard</a>
    <a href="shop-requests.php">🛒 Shop Requests</a>
    <a href="customers.php">👤 Customers</a>
    <a href="shops.php">🏬 Shops</a>
    <a href="reports.php" class="active">⚠️ Reports</a>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  async function loadReports() {
    try {
      const res = await fetch('api/reports.php', { credentials: 'same-origin' });
      const json = await res.json();
      const tbody = document.querySelector('#reportsTable tbody');
      if (json.error) {
        tbody.innerHTML = `<tr><td colspan="7">Error: ${json.error}</td></tr>`;
        return;
      }
      const reports = json.reports || [];
      if (reports.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7">No reports</td></tr>';
        return;
      }
      tbody.innerHTML = reports.map(r => {
        return `<tr>
          <td>${r.report_id}</td>
          <td>${r.type}</td>
          <td>${escapeHtml(r.reported_profile)}</td>
          <td>${escapeHtml(r.content)}</td>
          <td>${escapeHtml(r.reporter)}</td>
          <td>${r.date}</td>
          <td>
            <button class="btn btn-info btn-sm" onclick="viewReport(${r.report_id})">View</button>
            <button class="btn btn-warning btn-sm" onclick="ignoreReport(${r.report_id})">Ignore</button>
            <button class="btn btn-success btn-sm" onclick="replyReport(${r.report_id})">Reply</button>
            <button class="btn btn-danger btn-sm" onclick="suspendReported(${r.report_id}, '${r.type}')">Suspend</button>
          </td>
        </tr>`;
      }).join('\n');
    } catch (e) {
      const tbody = document.querySelector('#reportsTable tbody');
      tbody.innerHTML = `<tr><td colspan="7">Fetch error</td></tr>`;
    }
  }

  function escapeHtml(s) {
    if (!s) return '';
    return s.replace(/[&<>\"]/g, function(c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; });
  }

  function viewReport(id) { alert('Open view for report ' + id); }
  function ignoreReport(id) { alert('Ignore report ' + id); }
  function replyReport(id) { alert('Reply to report ' + id); }
  function suspendReported(id, type) { alert('Suspend ' + type + ' for report ' + id); }

  document.addEventListener('DOMContentLoaded', function(){
    loadReports();
    setInterval(loadReports, 10000);
  });
</script>
</body>
</html>
