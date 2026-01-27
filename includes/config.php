<?php
define('SITE_NAME', 'MiniBlog');
define('SITE_DESCRIPTION', 'A simple database-less blog');
define('DOCUMENTS_DIR', dirname(__DIR__) . '/documents');
define('BACKUPS_DIR', dirname(__DIR__) . '/backups');
define('CONFIG_FILE', __DIR__ . '/config.php');
define('ADMIN_CREDENTIALS_FILE', __DIR__ . '/admin_credentials.php');

session_start();
