<?php
require_once '../includes/config.php';
require_once '../includes/Security.php';
require_once '../includes/ArticleManager.php';
require_once '../includes/BackupManager.php';

Security::checkAdminSession();
Security::checkSessionTimeout();

$articleManager = new ArticleManager(DOCUMENTS_DIR);
$backupManager = new BackupManager(BACKUPS_DIR, DOCUMENTS_DIR);

$currentUsername = '';
if (file_exists(ADMIN_CREDENTIALS_FILE) && filesize(ADMIN_CREDENTIALS_FILE) > 0) {
    require ADMIN_CREDENTIALS_FILE;
    if (isset($admin_username)) {
        $currentUsername = $admin_username;
    }
}

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
            case 'create':
                $title = Security::sanitizeInput($_POST['title'] ?? '');
                $content = $_POST['content'] ?? '';
                $category = Security::sanitizeInput($_POST['category'] ?? '');
                $result = $articleManager->createArticle($title, $content, $category);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                if ($result['success']) {
                    $action = 'list';
                }
                break;
                
            case 'update':
                $oldTitle = Security::sanitizeInput($_POST['old_title'] ?? '');
                $newTitle = Security::sanitizeInput($_POST['title'] ?? '');
                $content = $_POST['content'] ?? '';
                $category = Security::sanitizeInput($_POST['category'] ?? '');
                $result = $articleManager->updateArticle($oldTitle, $newTitle, $content, $category);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                if ($result['success']) {
                    $action = 'list';
                }
                break;
                
            case 'delete':
                $title = Security::sanitizeInput($_POST['title'] ?? '');
                $result = $articleManager->deleteArticle($title);
                $message = $result['message'];
                $messageType = $result['success'] ? 'success' : 'error';
                break;
                
            case 'upload_image':
                $title = Security::sanitizeInput($_POST['title'] ?? '');
                if (isset($_FILES['image'])) {
                    $result = $articleManager->uploadArticleImage($title, $_FILES['image']);
                    $message = $result['message'];
                    $messageType = $result['success'] ? 'success' : 'error';
                }
                break;
        }
    }
}

$csrfToken = Security::generateCsrfToken();

$articles = $articleManager->getAllArticles();
$categories = $articleManager->getAllCategories();
$article = null;
if ($action === 'edit' && isset($_GET['title'])) {
    $article = $articleManager->getArticle($_GET['title']);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action === 'list' ? '文章列表' : ($action === 'create' ? '新建文章' : '编辑文章'); ?> - <?php echo SITE_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; position: relative; }
        .header h1 { font-size: 24px; }
        .nav { display: flex; gap: 20px; align-items: center; }
        .nav a { color: white; text-decoration: none; padding: 8px 16px; border-radius: 6px; transition: background 0.3s; }
        .nav a:hover, .nav a.active { background: rgba(255,255,255,0.2); }
        .account-dropdown { position: relative; z-index: 1000; }
        .account-btn { color: white; text-decoration: none; padding: 8px 16px; border-radius: 6px; transition: background 0.3s; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; }
        .account-btn:hover { background: rgba(255,255,255,0.2); }
        .account-btn::after { content: '▼'; font-size: 10px; transition: transform 0.3s; margin-left: 5px; }
        .account-dropdown.active .account-btn::after { transform: rotate(180deg); }
        .dropdown-menu { position: absolute; top: 100%; right: 0; background: white; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); min-width: 160px; max-width: 200px; overflow: visible; display: none; z-index: 1100; margin-top: 5px; padding: 5px 0; }
        .account-dropdown.active .dropdown-menu { display: block; }
        .dropdown-item { display: block; padding: 12px 20px; color: #000 !important; text-decoration: none; transition: background 0.2s; font-size: 14px; font-weight: 500; }
        .dropdown-item:hover { background: #f5f5f5; }
        .dropdown-item:first-child { border-radius: 8px 8px 0 0; }
        .dropdown-item:last-child { border-radius: 0 0 8px 8px; }
        .dropdown-divider { height: 1px; background-color: #e0e0e0; margin: 5px 0; }
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
        input[type="text"], textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; font-family: inherit; }
        textarea { min-height: 400px; resize: vertical; }
        input:focus, textarea:focus { outline: none; border-color: #667eea; }
        .actions { display: flex; gap: 10px; }
        .empty-state { text-align: center; padding: 60px 20px; color: #999; }
        .empty-state h3 { font-size: 18px; margin-bottom: 10px; color: #666; }
        .meta { color: #888; font-size: 13px; }
        .excerpt { color: #666; font-size: 14px; max-width: 500px; }
        
        @media (max-width: 768px) {
            .header { padding: 15px 20px; }
            .header h1 { font-size: 20px; }
            .nav { gap: 10px; flex-wrap: wrap; justify-content: center; }
            .nav a { padding: 6px 12px; font-size: 13px; }
            .account-btn { padding: 6px 12px; font-size: 13px; }
            .dropdown-menu { right: 0; min-width: 140px; }
            .dropdown-item { padding: 10px 16px; font-size: 13px; }
            .container { padding: 20px 15px; }
            .card { padding: 20px; }
            table { font-size: 13px; }
            th, td { padding: 10px 8px; }
            .excerpt { max-width: 200px; font-size: 12px; }
            .actions { flex-direction: column; }
            .btn { width: 100%; text-align: center; }
            input[type="text"], textarea { font-size: 14px; }
            textarea { min-height: 300px; }
            .empty-state { padding: 40px 20px; }
        }
        
        @media (max-width: 480px) {
            .header h1 { font-size: 18px; }
            .nav a { padding: 5px 10px; font-size: 12px; }
            .account-btn { padding: 5px 10px; font-size: 12px; }
            .card { padding: 15px; }
            table { display: block; overflow-x: auto; }
            th, td { padding: 8px 5px; }
            .excerpt { display: none; }
            .meta { font-size: 11px; }
            textarea { min-height: 250px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo SITE_NAME; ?> 管理后台</h1>
        <nav class="nav">
            <a href="../index.php">返回主页</a>
            <a href="dashboard.php" class="<?php echo $action === 'list' ? 'active' : ''; ?>">文章列表</a>
            <a href="dashboard.php?action=create" class="<?php echo $action === 'create' ? 'active' : ''; ?>">新建文章</a>
            <a href="settings.php">站点设置</a>
            <a href="backup.php">备份恢复</a>
            <div class="account-dropdown" id="accountDropdown">
                <div class="account-btn" onclick="toggleDropdown(event)">账户：<?php echo htmlspecialchars($currentUsername); ?></div>
                <div class="dropdown-menu">
                    <a href="change_password.php" class="dropdown-item">修改密码</a>
                    <div class="dropdown-divider"></div>
                    <a href="logout.php" class="dropdown-item">退出登录</a>
                </div>
            </div>
        </nav>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="alert <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($action === 'list'): ?>
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>文章列表</h2>
                    <a href="dashboard.php?action=create" class="btn btn-primary">新建文章</a>
                </div>
                
                <?php if (empty($articles)): ?>
                    <div class="empty-state">
                        <h3>暂无文章</h3>
                        <p>点击"新建文章"开始创建您的第一篇文章</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>标题</th>
                                <th>分类</th>
                                <th>摘要</th>
                                <th>创建时间</th>
                                <th>修改时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($articles as $article): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($article['title']); ?></strong></td>
                                    <td class="meta"><?php echo !empty($article['category']) ? htmlspecialchars($article['category']) : '-'; ?></td>
                                    <td class="excerpt"><?php echo htmlspecialchars($article['excerpt']); ?></td>
                                    <td class="meta"><?php echo $article['created_date']; ?></td>
                                    <td class="meta"><?php echo $article['modified_date']; ?></td>
                                    <td>
                                        <div class="actions">
                                            <a href="dashboard.php?action=edit&title=<?php echo urlencode($article['filename']); ?>" class="btn btn-primary">编辑</a>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="title" value="<?php echo htmlspecialchars($article['filename']); ?>">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                <button type="submit" class="btn btn-danger" onclick="return confirm('确定要删除这篇文章吗？');">删除</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
        <?php elseif ($action === 'create'): ?>
            <div class="card">
                <h2 style="margin-bottom: 20px;">新建文章</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>文章标题</label>
                        <input type="text" name="title" required>
                    </div>
                    <div class="form-group">
                        <label>文章分类（可选）</label>
                        <select name="category" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            <option value="">请选择分类</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p style="color: #888; font-size: 13px; margin-top: 5px;">分类用于对文章进行归类，例如：技术、生活、笔记等</p>
                    </div>
                    <div class="form-group">
                        <label>文章内容（支持Markdown语法）</label>
                        <textarea name="content" required></textarea>
                    </div>
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <div class="actions">
                        <button type="submit" class="btn btn-primary">保存文章</button>
                        <a href="dashboard.php" class="btn btn-secondary">取消</a>
                    </div>
                </form>
            </div>
            
        <?php elseif ($action === 'edit' && $article): ?>
            <div class="card">
                <h2 style="margin-bottom: 20px;">编辑文章</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>文章标题</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($article['title']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>文章分类（可选）</label>
                        <select name="category" style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
                            <option value="">请选择分类</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $article['category'] === $cat ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p style="color: #888; font-size: 13px; margin-top: 5px;">分类用于对文章进行归类，例如：技术、生活、笔记等</p>
                    </div>
                    <div class="form-group">
                        <label>文章内容（支持Markdown语法）</label>
                        <textarea name="content" required><?php echo htmlspecialchars($article['content']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>上传图片（可选）</label>
                        <input type="file" name="image" accept="image/*">
                        <p style="color: #888; font-size: 13px; margin-top: 5px;">图片将保存为：<?php echo htmlspecialchars($article['filename']); ?>.jpg/png/gif</p>
                    </div>
                    <input type="hidden" name="old_title" value="<?php echo htmlspecialchars($article['filename']); ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <div class="actions">
                        <button type="submit" class="btn btn-primary">更新文章</button>
                        <a href="dashboard.php" class="btn btn-secondary">取消</a>
                    </div>
                </form>
            </div>
            
            <?php
            $images = glob(DOCUMENTS_DIR . '/' . $article['filename'] . '.*');
            $imageFiles = array_filter($images, function($img) {
                return pathinfo($img, PATHINFO_EXTENSION) !== 'md';
            });
            if (!empty($imageFiles)):
            ?>
            <div class="card">
                <h3 style="margin-bottom: 15px;">文章图片</h3>
                <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                    <?php foreach ($imageFiles as $image): ?>
                        <div style="text-align: center;">
                            <img src="../image.php?title=<?php echo urlencode($article['filename']); ?>&ext=<?php echo pathinfo($image, PATHINFO_EXTENSION); ?>" style="max-width: 200px; border-radius: 4px; border: 1px solid #ddd;">
                            <p style="font-size: 12px; color: #888; margin-top: 5px;"><?php echo pathinfo($image, PATHINFO_EXTENSION); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="card">
                <div class="empty-state">
                    <h3>文章不存在</h3>
                    <a href="dashboard.php" class="btn btn-primary">返回文章列表</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function toggleDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('accountDropdown');
            dropdown.classList.toggle('active');
        }
        
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('accountDropdown');
            if (!dropdown.contains(event.target)) {
                dropdown.classList.remove('active');
            }
        });
    </script>
</body>
</html>
