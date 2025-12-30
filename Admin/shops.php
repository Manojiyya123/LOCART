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
  <title>Shops - LOCART Admin</title>
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

    <!-- Desktop Sidebar -->
    <div class="col-md-2 d-none d-md-block sidebar position-fixed vh-100">
      <h5><span class="logo">L0c<span class="accent">ArT</span></span> .admin</h5>
      <a href="dashboard.php">📊 Dashboard</a>
      <a href="shop-requests.php">🛒 Shop Requests</a>
      <a href="customers.php">👤 Customers</a>
      <a href="shops.php" class="active">🏬 Shops</a>
      <a href="reports.php">⚠️ Reports</a>
    </div>

    <!-- Main Content -->
    <div class="col-12 col-md-10 offset-md-2 p-4">
      <div class="topbar d-flex justify-content-between align-items-center mb-4">
        <h4>Registered Shops</h4>
      </div>

      <div class="row mb-4" id="shopCards">
        <!-- cards populated by JS -->
      </div>

      <!-- Table Wrapper -->
      <div class="table-responsive">
        <table class="table table-bordered table-hover bg-white shadow-sm rounded" id="shopsTable">
          <thead class="table-dark">
            <tr>
              <th>#</th>
              <th>Shop Name</th>
              <th>Owner</th>
              <th>Contact</th>
              <th>Category</th>
              <th>Joined On</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr><td colspan="8">Loading...</td></tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- Modals (kept static placeholders) -->
...existing code...

<!-- Offcanvas Sidebar (Mobile) -->
<div class="offcanvas offcanvas-start" id="mobileSidebar">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title"><span class="logo">L0c<span class="accent">ArT</span></span> .admin</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body sidebar-links">
    <a href="dashboard.php">📊 Dashboard</a>
    <a href="shop-requests.php">🛒 Shop Requests</a>
    <a href="customers.php">👤 Customers</a>
    <a href="shops.php" class="active">🏬 Shops</a>
    <a href="reports.php">⚠️ Reports</a>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  async function loadShops(){
    try{
      const res = await fetch('api/shops.php', { credentials: 'same-origin' });
      const json = await res.json();
      if (json.error){
        document.querySelector('#shopsTable tbody').innerHTML = `<tr><td colspan="8">${json.error}</td></tr>`;
        return;
      }
      // populate cards
      const cards = document.getElementById('shopCards');
      cards.innerHTML = `
        <div class="col-6 col-md-6 col-lg-3 mb-3">
          <div class="card p-3 text-center shadow-sm h-100">
            <h5>Total Shops</h5>
            <h2>${json.counts.total}</h2>
          </div>
        </div>
        <div class="col-6 col-md-6 col-lg-3 mb-3">
          <div class="card p-3 text-center shadow-sm h-100">
            <h5>Verified Shops</h5>
            <h2>${json.counts.verified}</h2>
          </div>
        </div>
        <div class="col-6 col-md-6 col-lg-3 mb-3">
          <div class="card p-3 text-center shadow-sm h-100">
            <h5>Suspended Shops</h5>
            <h2>${json.counts.suspended}</h2>
          </div>
        </div>
        <div class="col-6 col-md-6 col-lg-3 mb-3">
          <div class="card p-3 text-center shadow-sm h-100">
            <h5>Unverified Shops</h5>
            <h2>${json.counts.unverified}</h2>
          </div>
        </div>`;

      // populate table
      const tbody = document.querySelector('#shopsTable tbody');
      if (!json.shops || json.shops.length === 0){
        tbody.innerHTML = '<tr><td colspan="8">No shops found</td></tr>';
        return;
      }
      tbody.innerHTML = json.shops.map(s => `
        <tr>
          <td>${s.shopid}</td>
          <td>${escapeHtml(s.name)}</td>
          <td>${escapeHtml(s.owner_name || 'Owner #' + (s.ownerid||''))}</td>
          <td>${escapeHtml(s.contact_no1 || '')}</td>
          <td>${escapeHtml('') /* category not stored */}</td>
          <td>${s.application_date || ''}</td>
          <td><span class="badge ${s.status === 'active' ? 'bg-success' : (s.status === 'disable' ? 'bg-danger' : 'bg-secondary')}">${s.status}</span></td>
          <td>
            <button class="btn btn-info btn-sm">View Posts</button>
            <button class="btn btn-warning btn-sm">Send Alert</button>
            <button class="btn btn-secondary btn-sm">Disable</button>
            <button class="btn btn-danger btn-sm">Delete</button>
          </td>
        </tr>
      `).join('');
    }catch(e){
      document.querySelector('#shopsTable tbody').innerHTML = '<tr><td colspan="8">Fetch error</td></tr>';
    }
  }

  function escapeHtml(s){ if(!s) return ''; return s.replace(/[&<>\"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }

  document.addEventListener('DOMContentLoaded', function(){ loadShops(); setInterval(loadShops, 10000); });
</script>
</body>
</html>
