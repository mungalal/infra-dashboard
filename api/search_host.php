<?php
session_start();
if (!isset($_SESSION['user'])) {
    echo '<div style="color:#e74c3c;text-align:center;margin-top:40px;font-size:1.2rem;">Session expired or not logged in. <a href="login.php">Please login again</a>.</div>';
    exit;
}
$config = require __DIR__ . '/config/config.php';
$db = $config['db'];
$brand = $config['brand'];
$mysqli = new mysqli($db['host'], $db['user'], $db['pass'], $db['name']);

// Function to format uptime in human-readable format
function format_uptime($seconds) {
    // Check if input is numeric
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

// Initialize search/filter variables and pagination
$hosts = [];
$search = '';
$os_filter = $_POST['os_family'] ?? '';
$status_filter = $_POST['patch_status'] ?? '';
$page_size = isset($_POST['page_size']) ? intval($_POST['page_size']) : 10;
$page_size = in_array($page_size, [10,25,50,100]) ? $page_size : 10;
$page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
$offset = ($page-1)*$page_size;
$export = isset($_POST['export']) && $_POST['export'] === 'csv';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $search = trim($_POST['hostname'] ?? '');
    $params = [];
    $wheres = [];
    if ($search !== '') {
        $like = '%' . $mysqli->real_escape_string($search) . '%';
        $wheres[] = "(LOWER(hostname) LIKE LOWER(?) OR ip LIKE ? OR environment LIKE ? OR SOUNDEX(hostname) = SOUNDEX(?) )";
        $params = array_merge($params, [$like, $like, $like, $search]);
    }
    if ($os_filter !== '') {
        $wheres[] = "os_family = ?";
        $params[] = $os_filter;
    }
    if ($status_filter !== '') {
        if ($status_filter === 'patched') {
            $wheres[] = "unpatched_pct = 0";
        } elseif ($status_filter === 'pending') {
            $wheres[] = "unpatched_pct > 0 AND unpatched_pct < 100";
        } elseif ($status_filter === 'reboot') {
            $wheres[] = "unpatched_pct = 100";
        }
    }
    $where = $wheres ? ('WHERE ' . implode(' AND ', $wheres)) : '';
    $sql = "SELECT * FROM hosts $where ORDER BY last_seen DESC ".($export ? '' : "LIMIT $page_size OFFSET $offset");
    $stmt = $mysqli->prepare($sql);
    if ($params) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $hosts[] = $row;
    }
    $stmt->close();
    // CSV Export
    if ($export && count($hosts) > 0) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="host_search_export.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, array_keys($hosts[0]));
        foreach ($hosts as $h) fputcsv($out, $h);
        fclose($out);
        exit;
    }
}
// For pagination controls
$total_count = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$export) {
    $params = [];
    $wheres = [];
    if ($search !== '') {
        $like = '%' . $mysqli->real_escape_string($search) . '%';
        $wheres[] = "(LOWER(hostname) LIKE LOWER(?) OR ip LIKE ? OR environment LIKE ? OR SOUNDEX(hostname) = SOUNDEX(?) )";
        $params = array_merge($params, [$like, $like, $like, $search]);
    }
    if ($os_filter !== '') {
        $wheres[] = "os_family = ?";
        $params[] = $os_filter;
    }
    if ($status_filter !== '') {
        if ($status_filter === 'patched') {
            $wheres[] = "unpatched_pct = 0";
        } elseif ($status_filter === 'pending') {
            $wheres[] = "unpatched_pct > 0 AND unpatched_pct < 100";
        } elseif ($status_filter === 'reboot') {
            $wheres[] = "unpatched_pct = 100";
        }
    }
    $where = $wheres ? ('WHERE ' . implode(' AND ', $wheres)) : '';
    $sql = "SELECT COUNT(*) as c FROM hosts $where";
    $stmt = $mysqli->prepare($sql);
    if ($params) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $total_count = $result->fetch_assoc()['c'];
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($brand['name']) ?> - Host Search</title>
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
    <span class="brand-logo" style="background-image:url('<?= $brand['logo'] ?>');"></span>
    <h1><?= htmlspecialchars($brand['header']) ?></h1>
    <button class="dark-toggle" onclick="toggleDarkMode()">🌙</button>
  </div>
  <div class="dashboard-container">
    <h2>Search Host</h2>
    <form method="post" style="margin-bottom: 24px;display:flex;flex-wrap:wrap;gap:12px;align-items:center;">
      <input type="text" name="hostname" placeholder="Hostname, IP, Env..." value="<?= htmlspecialchars($search) ?>" style="padding:10px;font-size:1rem;border-radius:8px;border:1px solid #d1d9e6;width:200px;">
      <select name="os_family" style="padding:9px;font-size:1rem;border-radius:8px;border:1px solid #d1d9e6;">
        <option value="">Any OS</option>
        <option value="RedHat" <?= $os_filter==='RedHat'?'selected':'' ?>>RedHat</option>
        <option value="Debian" <?= $os_filter==='Debian'?'selected':'' ?>>Debian</option>
        <option value="OracleLinux" <?= $os_filter==='OracleLinux'?'selected':'' ?>>OracleLinux</option>
        <option value="Ubuntu" <?= $os_filter==='Ubuntu'?'selected':'' ?>>Ubuntu</option>
      </select>
      <select name="patch_status" style="padding:9px;font-size:1rem;border-radius:8px;border:1px solid #d1d9e6;">
        <option value="">Any Status</option>
        <option value="patched" <?= $status_filter==='patched'?'selected':'' ?>>Patched</option>
        <option value="pending" <?= $status_filter==='pending'?'selected':'' ?>>Pending</option>
        <option value="reboot" <?= $status_filter==='reboot'?'selected':'' ?>>Reboot Required</option>
      </select>
      <select name="page_size" style="padding:9px;font-size:1rem;border-radius:8px;border:1px solid #d1d9e6;">
        <option value="10" <?= $page_size==10?'selected':'' ?>>10/page</option>
        <option value="25" <?= $page_size==25?'selected':'' ?>>25/page</option>
        <option value="50" <?= $page_size==50?'selected':'' ?>>50/page</option>
        <option value="100" <?= $page_size==100?'selected':'' ?>>100/page</option>
      </select>
      <input type="number" name="page" min="1" value="<?= $page ?>" style="width:60px;padding:8px;border-radius:8px;border:1px solid #d1d9e6;" title="Jump to page">
      <button class="btn" type="submit">Search</button>
      <button class="btn" type="submit" name="export" value="csv" style="background:#27ae60;">Export CSV</button>
    </form>
    <?php if ($search !== '' || $os_filter !== '' || $status_filter !== '' || $total_count > 0): ?>
      <div style="margin-bottom:10px;font-weight:600;">Total Results: <?= $total_count ?></div>
    <?php endif; ?>

    <?php if ($search !== ''): ?>
      <?php if (count($hosts) > 0): ?>
        <table class="table">
          <thead>
            <tr>
              <th>Hostname</th>
              <th>OS</th>
              <th>IP</th>
              <th>Uptime</th>
              <th>Environment</th>
              <th>Unpatched (%)</th>
              <th>Patches</th>
              <th>Last Seen</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($hosts as $host): ?>
              <tr>
                <td><?= htmlspecialchars($host['hostname']) ?></td>
                <td><?= htmlspecialchars($host['os_family']) ?> <?= htmlspecialchars($host['os_version']) ?></td>
                <td><?= htmlspecialchars($host['ip']) ?></td>
                <td><?= isset($host['uptime']) && $host['uptime'] !== '' ? format_uptime($host['uptime']) : '-' ?></td>
                <td><?= htmlspecialchars($host['environment'] !== null && $host['environment'] !== '' ? $host['environment'] : '-') ?></td>
                <td><?= round($host['unpatched_pct'], 2) ?></td>
                <td><?php
                  $host_id = $host['id'];
                  $count = $mysqli->query("SELECT COUNT(*) AS c FROM packages WHERE host_id=$host_id AND upgradable=1")->fetch_assoc()['c'];
                  echo $count;
                ?></td>
                <td><?= htmlspecialchars($host['last_seen']) ?></td>
                <td><a href="host.php?id=<?= $host['id'] ?>" class="btn">Details</a></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php if ($total_count > $page_size): ?>
        <div style="text-align:center;margin:18px 0;">
          <a href="#" onclick="jumpPage(-1);return false;" class="btn" style="margin:0 8px;<?= $page <= 1 ? 'pointer-events:none;opacity:0.5;' : '' ?>">Prev</a>
          <span>Page <?= $page ?> / <?= ceil($total_count/$page_size) ?></span>
          <a href="#" onclick="jumpPage(1);return false;" class="btn" style="margin:0 8px;<?= $page >= ceil($total_count/$page_size) ? 'pointer-events:none;opacity:0.5;' : '' ?>">Next</a>
        </div>
        <script>
          function jumpPage(delta) {
            var f = document.forms[0];
            var p = parseInt(f.page.value || 1) + delta;
            p = Math.max(1, Math.min(p, <?= ceil($total_count/$page_size) ?>));
            f.page.value = p;
            f.submit();
          }
        </script>
        <?php endif; ?>
      <?php else: ?>
        <div style="color:#e74c3c;font-weight:600;">No hosts found matching your search.</div>
      <?php endif; ?>
    <?php endif; ?>
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
