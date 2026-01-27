<?php
require_once 'includes/config.php';
require_once 'includes/Security.php';
require_once 'includes/ArticleManager.php';
require_once 'includes/BackupManager.php';

header('Content-Type: application/json; charset=utf-8');

$articleManager = new ArticleManager(DOCUMENTS_DIR);
$backupManager = new BackupManager(BACKUPS_DIR, DOCUMENTS_DIR);

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '/';
$path = rtrim($path, '/');
$segments = explode('/', trim($path, '/'));

function sendJson($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function sendError($message, $statusCode = 400) {
    sendJson(['success' => false, 'message' => $message], $statusCode);
}

try {
    if ($path === '/articles' || $path === '') {
        if ($method === 'GET') {
            $query = Security::sanitizeInput($_GET['q'] ?? '');
            if ($query) {
                $articles = $articleManager->searchArticles($query);
            } else {
                $articles = $articleManager->getAllArticles();
            }
            sendJson(['success' => true, 'data' => $articles]);
        } else {
            sendError('Method not allowed', 405);
        }
    } elseif (preg_match('#^/articles/([^/]+)$#', $path, $matches)) {
        $title = urldecode($matches[1]);
        
        if ($method === 'GET') {
            $article = $articleManager->getArticle($title);
            if ($article) {
                sendJson(['success' => true, 'data' => $article]);
            } else {
                sendError('Article not found', 404);
            }
        } else {
            sendError('Method not allowed', 405);
        }
    } elseif ($path === '/admin/articles') {
        Security::checkAdminSession();
        
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $title = Security::sanitizeInput($input['title'] ?? '');
            $content = $input['content'] ?? '';
            
            $result = $articleManager->createArticle($title, $content);
            sendJson($result, $result['success'] ? 201 : 400);
        } else {
            sendError('Method not allowed', 405);
        }
    } elseif (preg_match('#^/admin/articles/([^/]+)$#', $path, $matches)) {
        Security::checkAdminSession();
        
        $title = urldecode($matches[1]);
        
        if ($method === 'PUT') {
            $input = json_decode(file_get_contents('php://input'), true);
            $newTitle = Security::sanitizeInput($input['title'] ?? $title);
            $content = $input['content'] ?? '';
            
            $result = $articleManager->updateArticle($title, $newTitle, $content);
            sendJson($result, $result['success'] ? 200 : 400);
        } elseif ($method === 'DELETE') {
            $result = $articleManager->deleteArticle($title);
            sendJson($result, $result['success'] ? 200 : 400);
        } else {
            sendError('Method not allowed', 405);
        }
    } elseif ($path === '/admin/backups') {
        Security::checkAdminSession();
        
        if ($method === 'GET') {
            $backups = $backupManager->getBackupList();
            sendJson(['success' => true, 'data' => $backups]);
        } elseif ($method === 'POST') {
            $result = $backupManager->createBackup();
            sendJson($result, $result['success'] ? 201 : 400);
        } else {
            sendError('Method not allowed', 405);
        }
    } elseif (preg_match('#^/admin/backups/([^/]+)$#', $path, $matches)) {
        Security::checkAdminSession();
        
        $backupName = urldecode($matches[1]);
        
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $action = $input['action'] ?? '';
            
            if ($action === 'restore') {
                $result = $backupManager->restoreBackup($backupName);
                sendJson($result, $result['success'] ? 200 : 400);
            } elseif ($action === 'delete') {
                $result = $backupManager->deleteBackup($backupName);
                sendJson($result, $result['success'] ? 200 : 400);
            } else {
                sendError('Invalid action', 400);
            }
        } else {
            sendError('Method not allowed', 405);
        }
    } elseif ($path === '/admin/import') {
        Security::checkAdminSession();
        
        if ($method === 'POST') {
            if (!isset($_FILES['file'])) {
                sendError('No file uploaded', 400);
            }
            
            $result = $backupManager->importArticle($_FILES['file']);
            sendJson($result, $result['success'] ? 201 : 400);
        } else {
            sendError('Method not allowed', 405);
        }
    } elseif (preg_match('#^/admin/export/([^/]+)$#', $path, $matches)) {
        Security::checkAdminSession();
        
        $title = urldecode($matches[1]);
        
        if ($method === 'GET') {
            $result = $backupManager->exportArticle($title);
            if ($result['success']) {
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $result['export_name'] . '"');
                header('Content-Length: ' . filesize($result['export_path']));
                readfile($result['export_path']);
                unlink($result['export_path']);
                exit;
            } else {
                sendError($result['message'], 400);
            }
        } else {
            sendError('Method not allowed', 405);
        }
    } else {
        sendError('Not found', 404);
    }
} catch (Exception $e) {
    Security::logSecurityEvent('api_error', ['error' => $e->getMessage()]);
    sendError('Internal server error', 500);
}
