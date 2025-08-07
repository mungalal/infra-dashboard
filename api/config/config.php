<?php
// Centralized configuration for database, branding, and API security
return [
    'db' => [ // MySQL connection settings
        'host' => 'localhost',
        'user' => 'root',
        'pass' => '123Apart@',
        'name' => 'patch',
    ],
    'brand' => [ // Branding and UI
        'name' => 'Cognizant Inventory',
        'logo' => '/api/images/logo.png',
        'header' => 'Cognizant Host Inventory Dashboard',
        'footer' => '&copy; 2025 Cognizant &mdash; All Rights Reserved',
    ],
    'api_key' => 'your_secret_api_key', // Used by Ansible and ingest API for security
];
