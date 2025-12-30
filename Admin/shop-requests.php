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

// simple logger for diagnostics
function log_request_msg($msg) {
  $file = __DIR__ . '/shop-requests-errors.log';
  @file_put_contents($file, date('[c] ') . $msg . "\n", FILE_APPEND);
}

// Handle accept/reject POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'], $_POST['request_id'])) {
  $actionType = $_POST['action_type']; // 'accept' or 'reject'
  $requestId = (int)$_POST['request_id'];

  // Diagnostic log of incoming POST
  log_request_msg("POST received action={$actionType} request_id={$requestId} reason=" . (isset($_POST['reason']) ? $_POST['reason'] : '[none]'));

  // Fetch the request row for details
  $stmt = $conn->prepare('SELECT * FROM shoprequest WHERE request_id = ?');
  $stmt->bind_param('i', $requestId);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();

  if ($row) {
    $shopName = $row['name'];
    $shopid = $row['shopid'];
    $adminReason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

    // If no reason provided, use default messages per action
        if ($actionType === 'reject') {
      if ($adminReason === '') {
        $adminReason = "the admin rejected the {$shopName}";
      }
            // Update status to 'rejected' and insert action
            $upd = $conn->prepare("UPDATE shoprequest SET status = 'rejected' WHERE request_id = ?");
            $upd->bind_param('i', $requestId);
            $upd->execute();
            if ($upd->error) { log_request_msg("UPDATE rejected error: " . $upd->error); }
            $upd->close();

  // insert action with timestamp and reason (person_id omitted)
  $act = $conn->prepare('INSERT INTO action (action_name, date, reason) VALUES (?, NOW(), ?)');
  $an = "request {$requestId} rejected";
  $act->bind_param('ss', $an, $adminReason);
      $ok = $act->execute();
      if (!$ok) { log_request_msg("action insert (reject) failed: " . $act->error . " - connErr:" . $conn->error); }
      $act->close();
      // Prune only when action count exceeds 50: delete oldest unreferenced actions until <=50
      $maxActions = 50;
      $cntRes = $conn->query("SELECT COUNT(*) AS cnt FROM action");
      $cntRow = $cntRes ? $cntRes->fetch_assoc() : null;
      $total = $cntRow ? (int)$cntRow['cnt'] : 0;
      if ($total > $maxActions) {
        while (true) {
          $cntRes = $conn->query("SELECT COUNT(*) AS cnt FROM action");
          $cntRow = $cntRes ? $cntRes->fetch_assoc() : null;
          $total = $cntRow ? (int)$cntRow['cnt'] : 0;
          if ($total <= $maxActions) break;
          // Find the oldest action that is NOT referenced by report.action_id
          $oldRes = $conn->query("SELECT actionid FROM action WHERE actionid NOT IN (SELECT DISTINCT action_id FROM report WHERE action_id IS NOT NULL) ORDER BY date ASC, actionid ASC LIMIT 1");
          if (!$oldRes || $oldRes->num_rows === 0) break; // nothing deletable without breaking FK
          $oldId = (int)$oldRes->fetch_assoc()['actionid'];
          $conn->query("DELETE FROM action WHERE actionid = " . $oldId);
        }
      }
    } elseif ($actionType === 'accept') {
      // Accept flow: update request status, insert into action, insert into shop
      // Use transaction
      $conn->begin_transaction();
      try {
        $upd = $conn->prepare("UPDATE shoprequest SET status = 'accepted' WHERE request_id = ?");
        $upd->bind_param('i', $requestId);
        $upd->execute();
        if ($upd->error) { log_request_msg("UPDATE accepted error: " . $upd->error); }
        $upd->close();

        if ($adminReason === '') {
          $adminReason = "The admin accepted the request from {$shopName}";
        }
        // insert action with timestamp and reason (person_id omitted)
        $act = $conn->prepare('INSERT INTO action (action_name, date, reason) VALUES (?, NOW(), ?)');
        $an = "request {$requestId} accepted";
        $act->bind_param('ss', $an, $adminReason);
        $ok = $act->execute();
        if (!$ok) { log_request_msg("action insert (accept) failed: " . $act->error . " - connErr:" . $conn->error); }
        $act->close();
        // Prune actions if necessary (existing logic)
        $maxActions = 50;
        $cntRes = $conn->query("SELECT COUNT(*) AS cnt FROM action");
        $cntRow = $cntRes ? $cntRes->fetch_assoc() : null;
        $total = $cntRow ? (int)$cntRow['cnt'] : 0;
        if ($total > $maxActions) {
          while (true) {
            $cntRes = $conn->query("SELECT COUNT(*) AS cnt FROM action");
            $cntRow = $cntRes ? $cntRes->fetch_assoc() : null;
            $total = $cntRow ? (int)$cntRow['cnt'] : 0;
            if ($total <= $maxActions) break;
            $oldRes = $conn->query("SELECT actionid FROM action WHERE actionid NOT IN (SELECT DISTINCT action_id FROM report WHERE action_id IS NOT NULL) ORDER BY date ASC, actionid ASC LIMIT 1");
            if (!$oldRes || $oldRes->num_rows === 0) break;
            $oldId = (int)$oldRes->fetch_assoc()['actionid'];
            $conn->query("DELETE FROM action WHERE actionid = " . $oldId);
          }
        }

        // Prepare shop insert: validate ownerid and verification_id first
        $ownerid_to_insert = isset($row['ownerid']) && $row['ownerid'] !== null ? (int)$row['ownerid'] : null;
        if ($ownerid_to_insert !== null) {
          $chk = $conn->prepare('SELECT ownerid FROM owner WHERE ownerid = ?');
          $chk->bind_param('i', $ownerid_to_insert);
          $chk->execute();
          $chkRes = $chk->get_result();
          if (!$chkRes || $chkRes->num_rows === 0) {
            // owner missing -> insert NULL instead
            log_request_msg("ownerid {$ownerid_to_insert} not found; inserting shop with NULL ownerid for request {$requestId}");
            $ownerid_to_insert = null;
          }
          $chk->close();
        }

        $verification_to_insert = isset($row['verification_id']) && $row['verification_id'] !== null ? (int)$row['verification_id'] : null;
        if ($verification_to_insert !== null) {
          $chk2 = $conn->prepare('SELECT verify_id FROM verification WHERE verify_id = ?');
          $chk2->bind_param('i', $verification_to_insert);
          $chk2->execute();
          $chkRes2 = $chk2->get_result();
          if (!$chkRes2 || $chkRes2->num_rows === 0) {
            log_request_msg("verification_id {$verification_to_insert} not found; inserting NULL for request {$requestId}");
            $verification_to_insert = null;
          }
          $chk2->close();
        }

        // Insert or update shop using provided shopid if available
        $status = 'active';
        $password = $row['password'] ?? null;
        $about = $row['about'] ?? null;
        $appDate = $row['request_received_date'] ?? null;
        $desired_shopid = isset($row['shopid']) ? (int)$row['shopid'] : null;
        if ($desired_shopid !== null) {
          // Check if shopid already exists
          $chkShop = $conn->prepare('SELECT shopid FROM shop WHERE shopid = ?');
          $chkShop->bind_param('i', $desired_shopid);
          $chkShop->execute();
          $chkRes = $chkShop->get_result();
          $chkShop->close();
          if ($chkRes && $chkRes->num_rows > 0) {
            // Update existing shop record
            $updShop = $conn->prepare('UPDATE shop SET name = ?, ownerid = ?, type = ?, contact_no1 = ?, contact_no2 = ?, verification_id = ?, city = ?, pincode = ?, status = ?, password = ?, about = ?, application_date = ?, access_given_date = NOW() WHERE shopid = ?');
            $updShop->bind_param('sisssissssssi', $row['name'], $ownerid_to_insert, $row['type'], $row['contact_no1'], $row['contact_no2'], $verification_to_insert, $row['city'], $row['pincode'], $status, $password, $about, $appDate, $desired_shopid);
            $updShop->execute();
            if ($updShop->error) { log_request_msg("shop update error for shopid {$desired_shopid}: " . $updShop->error); $conn->rollback(); $_SESSION['shop_req_error'] = "Accept failed: database error while updating shop (see server log)."; header('Location: shop-requests.php'); exit(); }
            $updShop->close();
          } else {
            // Insert specifying shopid
            $ins = $conn->prepare('INSERT INTO shop (shopid, name, ownerid, type, contact_no1, contact_no2, verification_id, city, pincode, status, password, about, application_date, access_given_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $ins->bind_param('isisssissssss', $desired_shopid, $row['name'], $ownerid_to_insert, $row['type'], $row['contact_no1'], $row['contact_no2'], $verification_to_insert, $row['city'], $row['pincode'], $status, $password, $about, $appDate);
            $ins->execute();
            if ($ins->error) { log_request_msg("shop insert error for desired shopid {$desired_shopid}: " . $ins->error); $conn->rollback(); $_SESSION['shop_req_error'] = "Accept failed: database error while creating shop (see server log)."; header('Location: shop-requests.php'); exit(); }
            $ins->close();
          }
        } else {
          // No desired shopid provided: insert new shop (auto-generated id)
          $ins = $conn->prepare('INSERT INTO shop (name, ownerid, type, contact_no1, contact_no2, verification_id, city, pincode, status, password, about, application_date, access_given_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
          $ins->bind_param('sisssiisssss', $row['name'], $ownerid_to_insert, $row['type'], $row['contact_no1'], $row['contact_no2'], $verification_to_insert, $row['city'], $row['pincode'], $status, $password, $about, $appDate);
          $ins->execute();
          if ($ins->error) { log_request_msg("shop insert error (auto id) for request {$requestId}: " . $ins->error); $conn->rollback(); $_SESSION['shop_req_error'] = "Accept failed: database error while creating shop (see server log)."; header('Location: shop-requests.php'); exit(); }
          $ins->close();
        }

        if (!$conn->commit()) { log_request_msg("commit failed: " . $conn->error); $_SESSION['shop_req_error'] = "Accept failed: commit error (see server log)."; $conn->rollback(); header('Location: shop-requests.php'); exit(); }
        // success
        $_SESSION['shop_req_success'] = "Request {$requestId} accepted and shop created.";
      } catch (Exception $e) {
        log_request_msg("exception during accept flow for request {$requestId}: " . $e->getMessage());
        $conn->rollback();
        $_SESSION['shop_req_error'] = "Accept failed: exception occurred (see server log).";
        header('Location: shop-requests.php');
        exit();
      }
    }
    }

    header('Location: shop-requests.php');
    exit();
}

// Fetch requests: pending (status NOT IN ('accepted','rejected')) first (first-come first-serve), then completed
$res = $conn->query("SELECT s.*, (SELECT IFNULL(MAX(date), s.request_received_date) FROM action WHERE action_name LIKE CONCAT('request ', s.request_id, '%')) AS last_action_date FROM shoprequest s ORDER BY (s.status NOT IN ('accepted','rejected')) DESC, IF((s.status NOT IN ('accepted','rejected')), s.request_received_date, NULL) ASC, IF((s.status NOT IN ('accepted','rejected')), NULL, last_action_date) DESC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Shop Owner Requests - LOCART Admin</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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
      <a href="shop-requests.php" class="active">🛒 Shop Requests</a>
      <a href="customers.php">👤 Customers</a>
      <a href="shops.php">🏬 Shops</a>
      <a href="reports.php">⚠️ Reports</a>
    </div>

    <!-- Main Content -->
    <div class="col-md-10 offset-md-2 p-3">

      <!-- Topbar -->
      <div class="topbar d-flex justify-content-between align-items-center mb-4">
        <h4>Shop Requests</h4>
      </div>

      <?php if (!empty($_SESSION['shop_req_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['shop_req_error']); ?></div>
        <?php unset($_SESSION['shop_req_error']); ?>
      <?php endif; ?>
      <?php if (!empty($_SESSION['shop_req_success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['shop_req_success']); ?></div>
        <?php unset($_SESSION['shop_req_success']); ?>
      <?php endif; ?>

      <!-- Shop Requests Table -->
      <div class="content">
  <h3 class="mb-4 text-primary">Pending Shop Owner Requests</h3>
        <div class="table-responsive">
          <table class="table table-hover bg-white shadow-sm rounded">
           <thead class="table-dark">
              <tr>
                <th>Request ID</th>
                <th>Shop ID</th>
                <th>Shop Name</th>
                <th>Location</th>
                <th>Received</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
            <?php while ($r = $res->fetch_assoc()): ?>
              <tr class="request-row" style="cursor:pointer;">
                <td><?php echo htmlspecialchars($r['request_id']); ?></td>
                <td><?php echo htmlspecialchars($r['shopid']); ?></td>
                <td><?php echo htmlspecialchars($r['name']); ?></td>
                <td><?php echo htmlspecialchars($r['city']); ?></td>
                <td><?php echo htmlspecialchars($r['request_received_date']); ?></td>
                <td class="action-cell no-toggle">
                <?php if (!in_array($r['status'], ['accepted','rejected'], true)): ?>
                  <form method="post" action="shop-requests.php" style="display:inline-block; margin-right:6px;">
                    <input type="hidden" name="request_id" value="<?php echo (int)$r['request_id']; ?>">
                    <input type="hidden" name="action_type" value="accept">
                    <input type="text" name="reason" placeholder="Optional reason" class="form-control form-control-sm mb-1">
                    <button type="submit" class="btn btn-success btn-sm">Accept</button>
                  </form>
                  <form method="post" action="shop-requests.php" style="display:inline-block">
                    <input type="hidden" name="request_id" value="<?php echo (int)$r['request_id']; ?>">
                    <input type="hidden" name="action_type" value="reject">
                    <input type="text" name="reason" placeholder="Optional reason" class="form-control form-control-sm mb-1">
                    <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                  </form>
                <?php else: ?>
                  <?php if ($r['status'] === 'accepted'): ?>
                    <span class="badge bg-success">Accepted</span>
                  <?php elseif ($r['status'] === 'rejected'): ?>
                    <span class="badge bg-danger">Rejected</span>
                  <?php else: ?>
                    <span class="badge bg-secondary"><?php echo htmlspecialchars($r['status']); ?></span>
                  <?php endif; ?>
                <?php endif; ?>
                </td>
              </tr>
              <tr class="details-row d-none">
                <td colspan="6" class="small text-muted bg-light">
                  <?php
                    // Build a details list excluding sensitive fields like password
                    $detailFields = [
                      'ownerid' => 'Owner ID',
                      'type' => 'Type',
                      'contact_no1' => 'Contact 1',
                      'contact_no2' => 'Contact 2',
                      'verification_id' => 'Verification ID',
                      'pincode' => 'Pincode',
                      'about' => 'About',
                      'status' => 'Status'
                    ];
                    $parts = [];
                    foreach ($detailFields as $col => $label) {
                      if (isset($r[$col]) && $r[$col] !== null && $r[$col] !== '') {
                        $parts[] = '<div><strong>' . $label . ':</strong> ' . htmlspecialchars($r[$col]) . '</div>';
                      }
                    }
                    // If no extra details available, show a placeholder
                    if (empty($parts)) {
                      echo '<div>No additional details available.</div>';
                    } else {
                      echo implode("\n", $parts);
                    }
                  ?>
                </td>
              </tr>
            <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Toggle details row when clicking on a request row, but ignore clicks on action controls
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('tr.request-row').forEach(function (row) {
      row.addEventListener('click', function (e) {
        // If click happened inside an element that should not toggle (forms, buttons, inputs), ignore
        if (e.target.closest('.no-toggle') || e.target.closest('form') || e.target.closest('button') || e.target.closest('input')) {
          return;
        }
        // Find the next sibling details row and toggle
        var details = row.nextElementSibling;
        if (details && details.classList.contains('details-row')) {
          details.classList.toggle('d-none');
        }
      });
    });
    // Ensure clicks inside the action cell/forms do not bubble to the row
    document.querySelectorAll('.action-cell form, .action-cell button, .action-cell input').forEach(function(el){
      el.addEventListener('click', function(ev){ ev.stopPropagation(); });
    });
  });
</script>
<!-- Offcanvas Sidebar (Mobile) -->
<div class="offcanvas offcanvas-start" id="mobileSidebar">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title"><span class="logo">L0c<span class="accent">ArT</span></span> .admin</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body sidebar-links">
    <a href="dashboard.php">📊 Dashboard</a>
    <a href="shop-requests.php" class="active">🛒 Shop Requests</a>
    <a href="customers.php">👤 Customers</a>
    <a href="shops.php">🏬 Shops</a>
    <a href="reports.php">⚠️ Reports</a>
  </div>
</div>

</body>
</html>
