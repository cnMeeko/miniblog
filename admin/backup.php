<?php
require_once '../includes/config.php';
require_once '../includes/Security.php';
require_once '../includes/ArticleManager.php';
require_once '../includes/BackupManager.php';

Security::checkAdminSession();
Security::checkSessionTimeout();

$articleManager = new ArticleManager(DOCUMENTS_DIR);
$backupManager = new BackupManager(BACKUPS_DIR, DOCUMENTS_DIR);

$action = $_GET['action'] ?? 'list';
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!Security::validateCsrfToken($csrfToken)) {
        $message = 'CSRF令牌验证失败';
        $messageType = 'error';
    } else {
        switch ($_POST['action'] ?? '') {
            case 'create_backup':
                $result = $backupManager->createBackup();
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                
                if (isset($_POST['ajax'])) {
                    header('Content-Type: application/json');
                    echo json_encode($result);
                    exit;
                }
                break;
                
            case 'restore_backup':
                $backupName = Security::sanitizeInput($_POST['backup_name'] ?? '');
                $result = $backupManager->restoreBackup($backupName);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                break;
                
            case 'delete_backup':
                $backupName = Security::sanitizeInput($_POST['backup_name'] ?? '');
                $result = $backupManager->deleteBackup($backupName);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                break;
                
            case 'export_article':
                $title = Security::sanitizeInput($_POST['title'] ?? '');
                $result = $backupManager->exportArticle($title);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                if ($result['success']) {
                    header('Content-Type: application/octet-stream');
                    header('Content-Disposition: attachment; filename="' . $result['export_name'] . '"');
                    header('Content-Length: ' . filesize($result['export_path']));
                    readfile($result['export_path']);
                    unlink($result['export_path']);
                    exit;
                }
                break;
                
            case 'import_article':
                if (isset($_FILES['import_file'])) {
                    $result = $backupManager->importArticle($_FILES['import_file']);
                    $message = $result['message'];
                    $messageType = $result['success'] ? 'success' : 'error';
                }
                break;
        }
    }
}

$csrfToken = Security::generateCsrfToken();

if (isset($_GET['action']) && $_GET['action'] === 'download' && isset($_GET['backup'])) {
    $backupName = Security::sanitizeInput($_GET['backup']);
    $sanitizedBackupName = Security::sanitizeFilename($backupName);
    $backupPath = BACKUPS_DIR . '/' . $sanitizedBackupName;
    
    if (file_exists($backupPath) && pathinfo($backupPath, PATHINFO_EXTENSION) === 'zip') {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $sanitizedBackupName . '"');
        header('Content-Length: ' . filesize($backupPath));
        header('Pragma: public');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        readfile($backupPath);
        exit;
    } else {
        $message = '备份文件不存在';
        $messageType = 'error';
    }
}

$backups = $backupManager->getBackupList();
$articles = $articleManager->getAllArticles();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>备份恢复 - <?php echo SITE_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; }
        .nav { display: flex; gap: 20px; }
        .nav a { color: white; text-decoration: none; padding: 8px 16px; border-radius: 6px; transition: background 0.3s; }
        .nav a:hover, .nav a.active { background: rgba(255,255,255,0.2); }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 30px; margin-bottom: 20px; }
        .alert { padding: 12px 20px; border-radius: 6px; margin-bottom: 20px; }
        .alert.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
        .alert.error { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }
        .btn { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.3s; text-decoration: none; display: inline-block; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; }
        .btn-danger { background: #ef5350; color: white; }
        .btn-danger:hover { background: #e53935; }
        .btn-success { background: #66bb6a; color: white; }
        .btn-success:hover { background: #4caf50; }
        .btn-secondary { background: #78909c; color: white; }
        .btn-secondary:hover { background: #607d8b; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; color: #555; }
        tr:hover { background: #f8f9fa; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; color: #555; }
        input[type="text"], input[type="file"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        input:focus { outline: none; border-color: #667eea; }
        .actions { display: flex; gap: 10px; }
        .section-title { font-size: 18px; font-weight: 600; margin-bottom: 20px; color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) {
            .grid { grid-template-columns: 1fr; }
        }
        .meta { color: #888; font-size: 13px; }
        .info-box { background: #e3f2fd; border-left: 4px solid #2196f3; padding: 15px; margin-bottom: 20px; font-size: 14px; color: #1565c0; }
        
        @media (max-width: 768px) {
            .header { padding: 15px 20px; }
            .header h1 { font-size: 20px; }
            .nav { gap: 10px; flex-wrap: wrap; justify-content: center; }
            .nav a { padding: 6px 12px; font-size: 13px; }
            .container { padding: 20px 15px; }
            .card { padding: 20px; }
            table { font-size: 13px; }
            th, td { padding: 10px 8px; }
            .actions { flex-direction: column; }
            .btn { width: 100%; text-align: center; }
            .section-title { font-size: 16px; }
            .info-box { font-size: 13px; padding: 12px; }
        }
        
        @media (max-width: 480px) {
            .header h1 { font-size: 18px; }
            .nav a { padding: 5px 10px; font-size: 12px; }
            .card { padding: 15px; }
            table { display: block; overflow-x: auto; }
            th, td { padding: 8px 5px; }
            .meta { font-size: 11px; }
            .section-title { font-size: 15px; }
            .info-box { font-size: 12px; padding: 10px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo SITE_NAME; ?> 管理后台</h1>
        <nav class="nav">
            <a href="../index.php">返回主页</a>
            <a href="dashboard.php">文章列表</a>
            <a href="dashboard.php?action=create">新建文章</a>
            <a href="backup.php" class="active">备份恢复</a>
            <a href="logout.php">退出登录</a>
        </nav>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div class="grid">
            <div class="card">
                <h2 class="section-title">备份管理</h2>
                <div class="info-box">
                    备份功能会将所有文章打包成一个ZIP文件，保存在backups目录中。最多保留10个备份文件。
                </div>
                
                <form method="POST" style="margin-bottom: 30px;">
                    <input type="hidden" name="action" value="create_backup">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <button type="submit" class="btn btn-primary">创建新备份</button>
                    <button type="button" onclick="downloadBackup()" class="btn btn-success">下载所有文章</button>
                </form>
                
                <script>
                function downloadBackup() {
                    if (confirm('确定要下载所有文章的备份吗？')) {
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', 'backup.php', true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.onload = function() {
                            if (xhr.status === 200) {
                                var response = JSON.parse(xhr.responseText);
                                if (response.success) {
                                    window.location.href = 'backup.php?action=download&backup=' + encodeURIComponent(response.backup_name);
                                } else {
                                    alert('创建备份失败：' + response.message);
                                }
                            }
                        };
                        xhr.send('action=create_backup&csrf_token=<?php echo $csrfToken; ?>&ajax=1');
                    }
                }
                </script>
                
                <?php if (empty($backups)): ?>
                    <p style="color: #999; text-align: center; padding: 20px;">暂无备份文件</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>备份名称</th>
                                <th>文章数</th>
                                <th>大小</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $backup): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($backup['name']); ?></td>
                                    <td><?php echo $backup['article_count']; ?></td>
                                    <td><?php echo $backup['size']; ?></td>
                                    <td class="meta"><?php echo $backup['modified_date']; ?></td>
                                    <td>
                                        <div class="actions">
                                            <a href="?action=download&backup=<?php echo urlencode($backup['name']); ?>" class="btn btn-primary">下载</a>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="restore_backup">
                                                <input type="hidden" name="backup_name" value="<?php echo htmlspecialchars($backup['name']); ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                <button type="submit" class="btn btn-success" onclick="return confirm('恢复备份将覆盖当前所有文章，确定要继续吗？');">恢复</button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_backup">
                                                <input type="hidden" name="backup_name" value="<?php echo htmlspecialchars($backup['name']); ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                <button type="submit" class="btn btn-danger" onclick="return confirm('确定要删除这个备份吗？');">删除</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h2 class="section-title">文章导入导出</h2>
                <div class="info-box">
                    导入导出功能用于单篇文章的备份和恢复。导出的文件为标准Markdown格式。
                </div>
                
                <h3 style="margin-bottom: 15px; font-size: 16px; color: #555;">导出文章</h3>
                <form method="POST" style="margin-bottom: 30px;">
                    <div class="form-group">
                        <label>选择要导出的文章</label>
                        <select name="title" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px;">
                            <?php foreach ($articles as $article): ?>
                                <option value="<?php echo htmlspecialchars($article['filename']); ?>">
                                    <?php echo htmlspecialchars($article['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="hidden" name="action" value="export_article">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <button type="submit" class="btn btn-primary">导出文章</button>
                </form>
                
                <h3 style="margin-bottom: 15px; font-size: 16px; color: #555;">导入文章</h3>
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>选择要导入的Markdown文件</label>
                        <input type="file" name="import_file" accept=".md" required>
                    </div>
                    <input type="hidden" name="action" value="import_article">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <button type="submit" class="btn btn-success">导入文章</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
