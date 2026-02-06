<?php
require_once 'includes/config.php';

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1) {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($username) || strlen($username) < 3) {
            $error = '用户名至少需要3个字符';
        } elseif (empty($password) || strlen($password) < 6) {
            $error = '密码至少需要6个字符';
        } elseif ($password !== $confirm_password) {
            $error = '两次输入的密码不一致';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $config_content = "<?php\n\$admin_username = '" . addslashes($username) . "';\n\$admin_password_hash = '" . $hashed_password . "';\n";
            
            if (file_put_contents(ADMIN_CREDENTIALS_FILE, $config_content)) {
                $success = '管理员账号设置成功！';
                header('Location: install.php?step=2');
                exit;
            } else {
                $error = '无法写入配置文件，请检查目录权限';
            }
        }
    }
}

$installed = file_exists(ADMIN_CREDENTIALS_FILE) && filesize(ADMIN_CREDENTIALS_FILE) > 0;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装 - <?php echo SITE_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 500px; margin: 50px auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { text-align: center; margin-bottom: 30px; color: #333; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; color: #555; }
        input[type="text"], input[type="password"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        input:focus { outline: none; border-color: #4CAF50; }
        button { width: 100%; padding: 12px; background: #4CAF50; color: white; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
        button:hover { background: #45a049; }
        .error { background: #ffebee; color: #c62828; padding: 12px; border-radius: 4px; margin-bottom: 20px; }
        .success { background: #e8f5e9; color: #2e7d32; padding: 12px; border-radius: 4px; margin-bottom: 20px; }
        .info { background: #e3f2fd; color: #1565c0; padding: 12px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; line-height: 1.6; }
        .step-indicator { text-align: center; margin-bottom: 20px; color: #888; }
        
        @media (max-width: 768px) {
            body { padding: 15px; }
            .container { padding: 30px 25px; max-width: 100%; }
            h1 { font-size: 24px; }
            label { font-size: 13px; }
            input[type="text"], input[type="password"] { padding: 11px 14px; font-size: 14px; }
            button { padding: 12px; font-size: 15px; }
            .error, .success, .info { font-size: 13px; padding: 10px; }
        }
        
        @media (max-width: 480px) {
            .container { padding: 25px 20px; }
            h1 { font-size: 22px; }
            input[type="text"], input[type="password"] { padding: 10px 12px; font-size: 13px; }
            button { padding: 11px; font-size: 14px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>安装 <?php echo SITE_NAME; ?></h1>
        
        <?php if ($installed): ?>
            <div class="success">
                安装已完成！
            </div>
            <div class="info">
                <strong>重要提示：</strong><br><br>
                如需恢复出厂设置（重置管理员账号），请删除以下文件中的内容：<br><br>
                文件：<code>includes/admin_credentials.php</code><br><br>
                删除该文件中的所有内容后，重新访问此页面即可重新设置管理员账号。
            </div>
            <p style="text-align: center; margin-top: 20px;">
                <a href="admin/login.php" style="color: #4CAF50; text-decoration: none;">前往管理后台 &rarr;</a>
            </p>
        <?php else: ?>
            <div class="step-indicator">步骤 <?php echo $step; ?> / 1</div>
            
            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($step == 1): ?>
                <form method="POST">
                    <div class="form-group">
                        <label>管理员用户名</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>管理员密码（至少6个字符）</label>
                        <input type="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label>确认密码</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    <button type="submit">完成安装</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
