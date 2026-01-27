<?php
require_once '../includes/config.php';
require_once '../includes/Security.php';

Security::logSecurityEvent('admin_logout');
Security::destroyAdminSession();

header('Location: login.php');
exit;
