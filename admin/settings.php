<?php
require_once '../includes/config.php';
require_once '../includes/Security.php';
require_once '../includes/ArticleManager.php';

Security::checkAdminSession();
Security::checkSessionTimeout();

$currentUsername = '';
if (file_exists(ADMIN_CREDENTIALS_FILE) && filesize(ADMIN_CREDENTIALS_FILE) > 0) {
    require ADMIN_CREDENTIALS_FILE;
    if (isset($admin_username)) {
        $currentUsername = $admin_username;
    }
}

$message = '';
$messageType = '';

// 初始化ArticleManager
$articleManager = new ArticleManager(DOCUMENTS_DIR);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!Security::validateCsrfToken($csrfToken)) {
        $message = 'CSRF令牌验证失败';
        $messageType = 'error';
    } else {
        // 处理分类操作
        if (isset($_POST['action']) && $_POST['action'] === 'manage_category') {
            $categoryAction = $_POST['category_action'] ?? '';
            
            switch ($categoryAction) {
                case 'add':
                    $newCategory = Security::sanitizeInput($_POST['new_category'] ?? '');
                    if (!empty($newCategory)) {
                        $result = $articleManager->addCategory($newCategory);
                        $message = $result['message'];
                        $messageType = $result['success'] ? 'success' : 'error';
                    } else {
                        $message = '分类名称不能为空';
                        $messageType = 'error';
                    }
                    break;
                
                case 'delete':
                    $categoryToDelete = Security::sanitizeInput($_POST['category_to_delete'] ?? '');
                    if (!empty($categoryToDelete)) {
                        $result = $articleManager->deleteCategory($categoryToDelete);
                        $message = $result['message'];
                        $messageType = $result['success'] ? 'success' : 'error';
                    } else {
                        $message = '请选择要删除的分类';
                        $messageType = 'error';
                    }
                    break;
            }
        } else {
            // 处理站点设置
            $siteName = Security::sanitizeInput($_POST['site_name'] ?? '');
            $siteDescription = Security::sanitizeInput($_POST['site_description'] ?? '');
            
            if (empty($siteName) || empty($siteDescription)) {
                $message = '站点名称和描述不能为空';
                $messageType = 'error';
            } else {
                $config = [
                    'site_name' => $siteName,
                    'site_description' => $siteDescription
                ];
                
                if (file_put_contents(SITE_CONFIG_FILE, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
                    $message = '设置保存成功';
                    $messageType = 'success';
                } else {
                    $message = '设置保存失败，请检查文件权限';
                    $messageType = 'error';
                }
            }
        }
    }
}

// 获取所有分类
$categories = $articleManager->getAllCategories();

$csrfToken = Security::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>站点设置 - <?php echo SITE_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; position: relative; }
        .header h1 { font-size: 24px; }
        .nav { display: flex; gap: 20px; align-items: center; }
        .nav a { color: white; text-decoration: none; padding: 8px 16px; border-radius: 6px; transition: background 0.3s; }
        .nav a:hover, .nav a.active { background: rgba(255,255,255,0.2); }
        .account-dropdown { position: relative; }
        .account-btn { color: white; text-decoration: none; padding: 8px 16px; border-radius: 6px; transition: background 0.3s; cursor: pointer; display: flex; align-items: center; gap: 5px; }
        .account-btn:hover { background: rgba(255,255,255,0.2); }
        .account-btn::after { content: '▼'; font-size: 10px; transition: transform 0.3s; }
        .account-dropdown.active .account-btn::after { transform: rotate(180deg); }
        .dropdown-menu { position: absolute; top: 100%; right: 0; background: white; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); min-width: 160px; overflow: hidden; display: none; z-index: 1000; margin-top: 5px; }
        .account-dropdown.active .dropdown-menu { display: block; }
        .dropdown-item { display: block; padding: 12px 20px; color: #333; text-decoration: none; transition: background 0.2s; font-size: 14px; }
        .dropdown-item:hover { background: #f5f5f5; }
        .dropdown-item:first-child { border-radius: 8px 8px 0 0; }
        .dropdown-item:last-child { border-radius: 0 0 8px 8px; }
        .dropdown-divider { height: 1px; background: #eee; margin: 0; }
        .container { max-width: 800px; margin: 30px auto; padding: 0 20px; }
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 30px; margin-bottom: 20px; }
        .alert { padding: 12px 20px; border-radius: 6px; margin-bottom: 20px; }
        .alert.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
        .alert.error { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }
        .btn { padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.3s; text-decoration: none; display: inline-block; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; }
        .btn-secondary { background: #78909c; color: white; }
        .btn-secondary:hover { background: #607d8b; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; color: #555; }
        input[type="text"], textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; font-family: inherit; }
        input:focus, textarea:focus { outline: none; border-color: #667eea; }
        .help-text { color: #888; font-size: 13px; margin-top: 5px; }
        .preview { background: #f8f9fa; padding: 20px; border-radius: 6px; margin-top: 20px; border-left: 4px solid #667eea; }
        .preview h3 { font-size: 14px; color: #666; margin-bottom: 15px; }
        .preview .site-name { font-size: 28px; font-weight: bold; color: #333; margin-bottom: 5px; }
        .preview .site-desc { font-size: 14px; color: #666; }
        
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
            .preview .site-name { font-size: 24px; }
        }
        
        @media (max-width: 480px) {
            .header h1 { font-size: 18px; }
            .nav a { padding: 5px 10px; font-size: 12px; }
            .account-btn { padding: 5px 10px; font-size: 12px; }
            .card { padding: 15px; }
            .preview .site-name { font-size: 20px; }
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
            <a href="settings.php" class="active">站点设置</a>
            <a href="backup.php">备份恢复</a>
            <div class="account-dropdown" id="accountDropdown">
                <div class="account-btn" onclick="toggleDropdown()">账户：<?php echo htmlspecialchars($currentUsername); ?></div>
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
        
        <div class="card">
            <h2 style="margin-bottom: 20px;">站点设置</h2>
            <form method="POST">
                <div class="form-group">
                    <label>站点名称</label>
                    <input type="text" name="site_name" value="<?php echo htmlspecialchars(SITE_NAME); ?>" required>
                </div>
                <div class="form-group">
                    <label>站点描述</label>
                    <textarea name="site_description" rows="3" required><?php echo htmlspecialchars(SITE_DESCRIPTION); ?></textarea>
                </div>
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <button type="submit" class="btn btn-primary">保存设置</button>
            </form>
            
            <div class="preview">
                <h3>预览效果</h3>
                <div class="site-name"><?php echo htmlspecialchars(SITE_NAME); ?></div>
                <div class="site-desc"><?php echo htmlspecialchars(SITE_DESCRIPTION); ?></div>
            </div>
        </div>
        
        <div class="card">
            <h2 style="margin-bottom: 20px;">分类管理</h2>
            <div class="form-group">
                <label>添加新分类</label>
                <form method="POST" style="display: flex; gap: 10px; align-items: flex-start;">
                    <input type="text" name="new_category" placeholder="输入分类名称" required style="flex: 1;">
                    <input type="hidden" name="action" value="manage_category">
                    <input type="hidden" name="category_action" value="add">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <button type="submit" class="btn btn-primary">添加</button>
                </form>
            </div>
            
            <div class="form-group">
                <label>现有分类</label>
                <?php if (empty($categories)): ?>
                    <p style="color: #999;">暂无分类</p>
                <?php else: ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                        <?php foreach ($categories as $category): ?>
                            <div style="background: #f5f5f5; padding: 8px 15px; border-radius: 20px; display: flex; align-items: center; gap: 10px;">
                                <span><?php echo htmlspecialchars($category); ?></span>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="manage_category">
                                    <input type="hidden" name="category_action" value="delete">
                                    <input type="hidden" name="category_to_delete" value="<?php echo htmlspecialchars($category); ?>">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <button type="submit" class="btn" style="padding: 2px 8px; font-size: 12px; background: #ef5350; color: white;" onclick="return confirm('确定要删除分类「<?php echo htmlspecialchars($category); ?>」吗？');">×</button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function toggleDropdown() {
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
