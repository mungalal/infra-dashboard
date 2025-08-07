-- Cognizant Host Inventory Dashboard SQL Schema

-- Users table (for login)
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL
);

-- Hosts table
CREATE TABLE hosts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  hostname VARCHAR(128) NOT NULL UNIQUE,
  os_family VARCHAR(32) NOT NULL,
  os_version VARCHAR(32) NOT NULL,
  ip VARCHAR(64) NOT NULL,
  last_seen DATETIME NOT NULL,
  uptime VARCHAR(32),
  environment VARCHAR(32),
  unpatched_pct FLOAT DEFAULT 0,
  patches_available INT DEFAULT 0
);

-- Packages table
CREATE TABLE packages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  host_id INT NOT NULL,
  name VARCHAR(128) NOT NULL,
  version VARCHAR(64) NOT NULL,
  upgradable TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (host_id) REFERENCES hosts(id) ON DELETE CASCADE
);

-- Indexes for performance
CREATE INDEX idx_hosts_last_seen ON hosts(last_seen);
CREATE INDEX idx_packages_host_id ON packages(host_id);
CREATE INDEX idx_packages_upgradable ON packages(upgradable);
