<?php
require_once '../includes/config.php';
require_once '../includes/Security.php';

Security::checkAdminSession();
Security::checkSessionTimeout();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!Security::validateCsrfToken($csrfToken)) {
        $message = 'CSRF令牌验证失败';
        $messageType = 'error';
    } else {
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
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 40px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { font-size: 24px; }
        .nav { display: flex; gap: 20px; }
        .nav a { color: white; text-decoration: none; padding: 8px 16px; border-radius: 6px; transition: background 0.3s; }
        .nav a:hover, .nav a.active { background: rgba(255,255,255,0.2); }
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
            .container { padding: 20px 15px; }
            .card { padding: 20px; }
            .preview .site-name { font-size: 24px; }
        }
        
        @media (max-width: 480px) {
            .header h1 { font-size: 18px; }
            .nav a { padding: 5px 10px; font-size: 12px; }
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
            <a href="logout.php">退出登录</a>
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
                    <label for="site_name">站点名称</label>
                    <input type="text" id="site_name" name="site_name" value="<?php echo htmlspecialchars(SITE_NAME); ?>" required>
                    <p class="help-text">显示在页面标题和首页顶部的博客名称</p>
                </div>
                
                <div class="form-group">
                    <label for="site_description">站点描述</label>
                    <input type="text" id="site_description" name="site_description" value="<?php echo htmlspecialchars(SITE_DESCRIPTION); ?>" required>
                    <p class="help-text">显示在站点名称下方的简短描述</p>
                </div>
                
                <div class="preview">
                    <h3>预览效果</h3>
                    <div class="site-name" id="preview-name"><?php echo htmlspecialchars(SITE_NAME); ?></div>
                    <div class="site-desc" id="preview-desc"><?php echo htmlspecialchars(SITE_DESCRIPTION); ?></div>
                </div>
                
                <div style="margin-top: 30px;">
                    <button type="submit" class="btn btn-primary">保存设置</button>
                    <a href="dashboard.php" class="btn btn-secondary">取消</a>
                </div>
                
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            </form>
        </div>
    </div>
    
    <script>
        const siteNameInput = document.getElementById('site_name');
        const siteDescInput = document.getElementById('site_description');
        const previewName = document.getElementById('preview-name');
        const previewDesc = document.getElementById('preview-desc');
        
        siteNameInput.addEventListener('input', function() {
            previewName.textContent = this.value || '站点名称';
        });
        
        siteDescInput.addEventListener('input', function() {
            previewDesc.textContent = this.value || '站点描述';
        });
    </script>
</body>
</html>
