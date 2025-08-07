<?php
// Start session for login flow
session_start();
// DO NOT check for $_SESSION['user'] here! Login page must always be accessible.

// Load configuration and connect to database
$config = require __DIR__ . '/config/config.php';
$db = $config['db'];
$brand = $config['brand'];
$mysqli = new mysqli($db['host'], $db['user'], $db['pass'], $db['name']);
$error = '';
// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    // Fetch user row by username
    $stmt = $mysqli->prepare("SELECT * FROM users WHERE username=?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    // Secure password check using password_verify (bcrypt)
    if ($user && password_verify($password, $user['password_hash'])) {
        // Set session variable for authentication
        $_SESSION['user'] = $user['username'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($brand['name']) ?> Login</title>
    <link rel="stylesheet" href="/api/css/style.css">

</head>
<body>
  <div class="sidebar">
    <div class="sidebar-avatar" title="Brand Logo" style="margin-top:18px;">
      <img src="/api/images/logo.png" alt="Brand Logo">
    </div>
  </div>
  <div class="header">
    <div class="hamburger" onclick="toggleSidebar()">
      <span></span><span></span><span></span>
    </div>
    <span class="brand-logo" style="background-image:url('{$brand['logo']}');"></span>
    <h1><?= htmlspecialchars($brand['header']) ?></h1>
    <button class="dark-toggle" onclick="toggleDarkMode()">🌙</button>
  </div>
  <div class="login-container">
    <h2>Login</h2>
    <form method="post">
        <input name="username" placeholder="Username" type="text" required>
        <input name="password" type="password" placeholder="Password" required>
        <button class="btn" type="submit">Login</button>
        <?php if (!empty($error)) echo "<div class='alert alert-danger mt-2'>$error</div>"; ?>
    </form>
  </div>
  <div class="footer">
    <?= $brand['footer'] ?>
  </div>
  <script>
    function toggleDarkMode() {
      document.body.classList.toggle('dark');
      localStorage.setItem('darkMode', document.body.classList.contains('dark'));
    }
    function toggleSidebar() {
      document.querySelector('.sidebar').classList.toggle('open');
    }
    if (localStorage.getItem('darkMode') === 'true') {
      document.body.classList.add('dark');
    }
  </script>
</body>
</html>
