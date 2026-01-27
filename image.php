<?php
require_once 'includes/config.php';
require_once 'includes/Security.php';
require_once 'includes/ArticleManager.php';

$articleManager = new ArticleManager(DOCUMENTS_DIR);

$title = Security::sanitizeInput($_GET['title'] ?? '');
$ext = Security::sanitizeInput($_GET['ext'] ?? '');

$imagePath = $articleManager->getArticleImage($title, $ext);

if (!$imagePath) {
    http_response_code(404);
    header('Content-Type: image/png');
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    exit;
}

$imageInfo = getimagesize($imagePath);
if ($imageInfo === false) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $imageInfo['mime']);
header('Content-Length: ' . filesize($imagePath));
header('Cache-Control: public, max-age=31536000');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');

readfile($imagePath);
exit;
