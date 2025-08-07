<?php
// Start session and enforce authentication for dashboard access
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

$hosts = $mysqli->query("
    SELECT h.*, 
           (SELECT COUNT(*) FROM packages p WHERE p.host_id=h.id AND p.upgradable=1) AS patches_available
    FROM hosts h
    ORDER BY last_seen DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($brand['name']) ?></title>
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
<div class="container mt-4">
  <div class="d-flex justify-content-end mb-3">
    <a href="logout.php" class="btn">Logout</a>
  </div>
  <h1>Host Inventory Dashboard</h1>
  <table class="table table-striped table-responsive">
    <thead>
      <tr>
        <th>Hostname</th>
        <th>OS</th>
        <th>IP</th>
        <th>Status</th>
        <th>Uptime (s)</th>
        <th>Environment</th>
        <th>Unpatched (%)</th>
        <th>Patches Available</th>
        <th>Last Seen</th>
        <th>Details</th>
      </tr>
    </thead>
    <tbody>
    <?php while($row = $hosts->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($row['hostname']) ?></td>
        <td><?= htmlspecialchars($row['os_family']) ?> <?= htmlspecialchars($row['os_version']) ?></td>
        <td><?= htmlspecialchars($row['ip']) ?></td>
        <td>
          <?php
            if ($row['unpatched_pct'] == 0) {
              echo '<span class="badge-patched"><svg class="status-icon" viewBox="0 0 20 20"><circle cx="10" cy="10" r="10" fill="#27ae60"/><polyline points="6,11 9,14 14,7" fill="none" stroke="#fff" stroke-width="2.2"/></svg>Patched</span>';
            } elseif ($row['patches_available'] > 0) {
              echo '<span class="badge-pending"><svg class="status-icon" viewBox="0 0 20 20"><circle cx="10" cy="10" r="10" fill="#f39c12"/><circle cx="10" cy="10" r="5" fill="#fff"/></svg>Pending</span>';
            } else {
              echo '<span class="badge-reboot"><svg class="status-icon" viewBox="0 0 20 20"><circle cx="10" cy="10" r="10" fill="#e74c3c"/><text x="10" y="15" text-anchor="middle" font-size="12" fill="#fff">!</text></svg>Reboot Required</span>';
            }
          ?>
        </td>
        <td class="uptime"><?= isset($row['uptime']) && $row['uptime'] !== '' ? format_uptime($row['uptime']) : '-' ?></td>
        <td class="env"><?= htmlspecialchars($row['environment'] !== null && $row['environment'] !== '' ? $row['environment'] : '-') ?></td>
        <td><?= round($row['unpatched_pct'], 2) ?></td>
        <td><?= $row['patches_available'] ?></td>
        <td><?= htmlspecialchars($row['last_seen']) ?></td>
        <td><a href="host.php?id=<?= $row['id'] ?>" class="btn">View</a></td>
      </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
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
