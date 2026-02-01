<?php
define('DOCUMENTS_DIR', dirname(__DIR__) . '/documents');
define('BACKUPS_DIR', dirname(__DIR__) . '/backups');
define('CONFIG_FILE', __DIR__ . '/config.php');
define('ADMIN_CREDENTIALS_FILE', __DIR__ . '/admin_credentials.php');
define('SITE_CONFIG_FILE', __DIR__ . '/site_config.php');

$siteConfig = [];
if (file_exists(SITE_CONFIG_FILE)) {
    $siteConfig = json_decode(file_get_contents(SITE_CONFIG_FILE), true) ?? [];
}

define('SITE_NAME', $siteConfig['site_name'] ?? 'MiniBlog');
define('SITE_DESCRIPTION', $siteConfig['site_description'] ?? 'A simple database-less blog');

session_start();
