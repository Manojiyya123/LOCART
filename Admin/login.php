<?php
// Database connection settings
$servername = "localhost";
$username_db = "root";
$password_db = ""; // Set your MySQL password
$dbname = "locart_db";

// Start session to store login state
session_start();

$message = "";

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
  session_unset();
  session_destroy();
  header('Location: login.php');
  exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST["username"];
    $password = $_POST["password"];

    // Create connection
    $conn = new mysqli($servername, $username_db, $password_db, $dbname);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Prepare and bind
    $stmt = $conn->prepare("SELECT * FROM admin WHERE username = ? AND password = ?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

  if ($result->num_rows > 0) {
    // fetch the row and verify hashed password if stored as hash
    $row = $result->fetch_assoc();
    $stored = $row['password'];
    $ok = false;
    // If stored password looks like a bcrypt hash (starts with $2y$ or $2b$) use password_verify
    if (strpos($stored, '$2y$') === 0 || strpos($stored, '$2b$') === 0 || strpos($stored, '$argon') === 0) {
      if (password_verify($password, $stored)) {
        $ok = true;
      }
    } else {
      // Fallback: plain-text comparison (not recommended) — keep for backward compatibility
      if ($password === $stored) {
        $ok = true;
      }
    }

    if ($ok) {
      $_SESSION["admin_logged_in"] = true;
      // Redirect to PHP dashboard which enforces session
      header("Location: dashboard.php");
      exit();
    } else {
      $message = "❌ Invalid username or password";
    }
  } else {
    $message = "❌ Invalid username or password";
  }
    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login - LOCART</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body class="bg-light d-flex justify-content-center align-items-center vh-100">
  <div class="card shadow p-4" style="width: 350px;">
    <h3 class="text-center mb-3">Admin Login</h3>
    <form method="POST" action="">
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>
    <p class="text-center mt-3 text-danger"><?php echo $message; ?></p>
  </div>
</body>
</html>
