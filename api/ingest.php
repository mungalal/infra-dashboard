<?php
// api/ingest.php
// Load configuration and connect to database
$config = require __DIR__ . '/config/config.php';
$db = $config['db'];
$mysqli = new mysqli($db['host'], $db['user'], $db['pass'], $db['name']);

// Security: Require valid API key for ingestion
// This check ensures that only authorized requests can ingest data into the system.
// The API key is validated against the one stored in the configuration file.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SERVER['HTTP_X_API_KEY']) || $_SERVER['HTTP_X_API_KEY'] !== $config['api_key']) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized: Invalid API key']);
    exit;
}

// Parse JSON input from Ansible or other sources
// This section reads the JSON input from the request body and decodes it into a PHP array.
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

// Sanitize and extract host data for DB update/insert
// This section extracts the host data from the input array and sanitizes it using the mysqli real_escape_string method.
// This prevents SQL injection attacks by escaping any special characters in the input data.
$hostname = $mysqli->real_escape_string($input['hostname'] ?? '');
$os_family = $mysqli->real_escape_string($input['os_family'] ?? '');
$os_version = $mysqli->real_escape_string($input['os_version'] ?? '');
$ip = $mysqli->real_escape_string($input['ip'] ?? '');
$uptime = isset($input['uptime']) ? $mysqli->real_escape_string($input['uptime']) : null;
$environment = isset($input['environment']) ? $mysqli->real_escape_string($input['environment']) : null;
$packages = $input['packages'];
$upgradable = $input['upgradable'];

// Database connection
// This section establishes a connection to the database using the mysqli extension.
// If the connection fails, it returns a 500 error with a message indicating the failure.
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$hostname = $mysqli->real_escape_string($data['hostname']);
$os_family = $mysqli->real_escape_string($data['os_family']);
$os_version = $mysqli->real_escape_string($data['os_version']);
$ip = $mysqli->real_escape_string($data['ip']);
$uptime = isset($data['uptime']) ? $mysqli->real_escape_string($data['uptime']) : null;
$environment = isset($data['environment']) ? $mysqli->real_escape_string($data['environment']) : null;
$packages = $data['packages'];
$upgradable = $data['upgradable'];

// Insert or update host
$mysqli->query("INSERT INTO hosts (hostname, os_family, os_version, ip, last_seen, uptime, environment) VALUES
    ('$hostname', '$os_family', '$os_version', '$ip', NOW(), '$uptime', '$environment')
    ON DUPLICATE KEY UPDATE os_family='$os_family', os_version='$os_version', ip='$ip', last_seen=NOW(), uptime='$uptime', environment='$environment'");

// Get host_id
$host_id = $mysqli->insert_id ?: $mysqli->query("SELECT id FROM hosts WHERE hostname='$hostname'")->fetch_assoc()['id'];

// Calculate unpatched percentage
$total_pkgs = 0;
$upgradable_pkgs = 0;
$upgradable_pkgs_names = [];

// Normalize upgradable list for both OS types
if (is_array($upgradable)) {
    foreach ($upgradable as $up_pkg) {
        if (is_array($up_pkg) && isset($up_pkg['name'])) {
            $upgradable_pkgs_names[] = $up_pkg['name']; // RedHat structured
        } elseif (is_string($up_pkg)) {
            $parts = preg_split('/[ \/]/', $up_pkg);
            if (isset($parts[0])) $upgradable_pkgs_names[] = $parts[0]; // Debian line
        }
    }
}

// Delete only upgradable packages for this host
$mysqli->query("DELETE FROM packages WHERE host_id=$host_id AND upgradable=1;");
if (is_array($packages)) {
    foreach ($packages as $pkg_name => $pkg_versions) {
        // Handle Ansible package_facts: list of dicts (with 'version' key)
        if (is_array($pkg_versions) && isset($pkg_versions[0]['version'])) {
            $version = $pkg_versions[0]['version'];
        } elseif (is_array($pkg_versions)) {
            $version = implode(',', $pkg_versions); // fallback for older style
        } else {
            $version = $pkg_versions;
        }
        $is_upgradable = in_array($pkg_name, $upgradable_pkgs_names) ? 1 : 0;
        $name = $mysqli->real_escape_string($pkg_name);
        $ver = $mysqli->real_escape_string($version);
        $mysqli->query("INSERT INTO packages (host_id, name, version, upgradable) VALUES
            ($host_id, '$name', '$ver', $is_upgradable)");
        $total_pkgs++;
        if ($is_upgradable) $upgradable_pkgs++;
    }
}

// Update unpatched percentage
$unpatched_pct = $total_pkgs ? ($upgradable_pkgs / $total_pkgs) * 100 : 0;
$mysqli->query("UPDATE hosts SET unpatched_pct=$unpatched_pct WHERE id=$host_id");

echo json_encode(['status' => 'success']);
