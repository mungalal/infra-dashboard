<?php
// Start session and enforce authentication for host details page
session_start();
if (!isset($_SESSION['user'])) {
    // User not authenticated; show error and link to login
    echo '<div style="color:#e74c3c;text-align:center;margin-top:40px;font-size:1.2rem;">Session expired or not logged in. <a href="login.php">Please login again</a>.</div>';
    exit;
}
// Load configuration and connect to database
$config = require __DIR__ . '/config/config.php';
$db = $config['db'];
$brand = $config['brand'];
$mysqli = new mysqli($db['host'], $db['user'], $db['pass'], $db['name']);

// Helper: Convert uptime in seconds to human-readable format
function format_uptime($seconds) {
    // Check if input is numeric to prevent potential errors
    if (!is_numeric($seconds)) return '-';
    $seconds = (int)$seconds;
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    $parts = [];
    if ($days > 0) $parts[] = "{$days}d";
    if ($hours > 0) $parts[] = "{$hours}h";
    if ($minutes > 0) $parts[] = "{$minutes}m";
    if ($secs > 0 || empty($parts)) $parts[] = "{$secs}s";
    return implode(' ', $parts);
}

$id = intval($_GET['id']);
$host = $mysqli->query("SELECT * FROM hosts WHERE id=$id")->fetch_assoc();
$pkgs = $mysqli->query("SELECT * FROM packages WHERE host_id=$id AND upgradable=1 ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($brand['name']) ?> - <?= htmlspecialchars($host['hostname']) ?></title>
  <link rel="stylesheet" href="/api/css/style.css">

</head>
<body>
  <div class="sidebar">
    <a href="index.php" title="Dashboard" class="sidebar-icon light-link">
      <span style="font-size:2.1em;vertical-align:middle;">🏠</span><div style="font-size:0.92em;">Dashboard</div>
    </a>
    <a href="search_host.php" title="Search Hosts" class="sidebar-icon light-link">
      <span style="font-size:2.1em;vertical-align:middle;">🔎</span><div style="font-size:0.92em;">Search Hosts</div>
    </a>
    <a href="logout.php" title="Logout" class="sidebar-icon light-link">
      <span style="font-size:2.1em;vertical-align:middle;">🚪</span><div style="font-size:0.92em;">Logout</div>
    </a>
    <div class="sidebar-avatar" title="Profile">
      <img src="/api/images/logo.png" alt="User Avatar">
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
  <div class="dashboard-container">
    <h2>Host: <?= htmlspecialchars($host['hostname']) ?></h2>
    <div style="margin-bottom:12px;">
      <span class="badge-patched">OS: <?= htmlspecialchars($host['os_family']) ?> <?= htmlspecialchars($host['os_version']) ?></span>
      <span class="badge-pending">IP: <?= htmlspecialchars($host['ip']) ?></span>
      <span class="badge-reboot">Uptime: <?= isset($host['uptime']) && $host['uptime'] !== '' ? format_uptime($host['uptime']) : '-' ?></span>
      <span class="badge-patched">Env: <?= htmlspecialchars($host['environment'] !== null && $host['environment'] !== '' ? $host['environment'] : '-') ?></span>
      <span class="badge-pending">Unpatched: <?= round($host['unpatched_pct'], 2) ?>%</span>
    </div>
    <div class="upgradable-table-container">
      <table class="table">
        <thead>
          <tr>
            <th>Package</th>
            <th>Version</th>
          </tr>
        </thead>
        <tbody>
        <?php while($pkg = $pkgs->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($pkg['name']) ?></td>
            <td><?= htmlspecialchars($pkg['version']) ?></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    <a href="index.php" class="btn">Back</a>
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