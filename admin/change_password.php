<?php
require_once '../includes/config.php';
require_once '../includes/Security.php';

Security::checkAdminSession();
Security::checkSessionTimeout();

$message = '';
$messageType = '';
$currentUsername = '';

// 读取当前用户名
if (file_exists(ADMIN_CREDENTIALS_FILE) && filesize(ADMIN_CREDENTIALS_FILE) > 0) {
    require ADMIN_CREDENTIALS_FILE;
    if (isset($admin_username)) {
        $currentUsername = $admin_username;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!Security::validateCsrfToken($csrfToken)) {
        $message = 'CSRF令牌验证失败';
        $messageType = 'error';
    } else {
        $oldPassword = $_POST['old_password'] ?? '';
        $newUsername = $_POST['new_username'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($oldPassword)) {
            $message = '请填写当前密码';
            $messageType = 'error';
        } else {
            if (file_exists(ADMIN_CREDENTIALS_FILE) && filesize(ADMIN_CREDENTIALS_FILE) > 0) {
                require ADMIN_CREDENTIALS_FILE;
                
                if (isset($admin_password_hash)) {
                    if (password_verify($oldPassword, $admin_password_hash)) {
                        // 处理用户名修改
                        if (!empty($newUsername) && $newUsername !== $admin_username) {
                            if (strlen($newUsername) < 3) {
                                $message = '用户名长度至少为3个字符';
                                $messageType = 'error';
                            } else {
                                $admin_username = $newUsername;
                            }
                        }
                        
                        // 处理密码修改
                        if (!empty($newPassword) || !empty($confirmPassword)) {
                            if (empty($newPassword) || empty($confirmPassword)) {
                                $message = '请填写所有密码字段';
                                $messageType = 'error';
                            } elseif (strlen($newPassword) < 6) {
                                $message = '新密码长度至少为6个字符';
                                $messageType = 'error';
                            } elseif ($newPassword !== $confirmPassword) {
                                $message = '两次输入的新密码不一致';
                                $messageType = 'error';
                            } else {
                                $admin_password_hash = password_hash($newPassword, PASSWORD_DEFAULT);
                            }
                        }
                        
                        if (empty($message)) {
                            $content = "<?php\n\$admin_username = '$admin_username';\n\$admin_password_hash = '$admin_password_hash';\n";
                            
                            if (file_put_contents(ADMIN_CREDENTIALS_FILE, $content)) {
                                Security::logSecurityEvent('account_updated');
                                $message = '账户信息修改成功，下次登录请使用新的账户信息';
                                $messageType = 'success';
                                // 更新当前用户名变量
                                $currentUsername = $admin_username;
                            } else {
                                $message = '账户信息修改失败，请检查文件权限';
                                $messageType = 'error';
                            }
                        }
                    } else {
                        $message = '旧密码错误';
                        $messageType = 'error';
                        Security::logSecurityEvent('password_change_failed', ['reason' => 'old_password_incorrect']);
                    }
                } else {
                    $message = '配置文件损坏，请重新安装';
                    $messageType = 'error';
                }
            } else {
                $message = '配置文件不存在，请重新安装';
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
    <title>修改密码 - <?php echo SITE_NAME; ?> 管理后台</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); width: 100%; max-width: 450px; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { color: #333; font-size: 24px; margin-bottom: 8px; }
        .header p { color: #888; font-size: 14px; }
        .alert { padding: 12px 20px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; }
        .alert.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
        .alert.error { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; color: #555; font-size: 14px; }
        input[type="text"], input[type="password"] { width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: border-color 0.3s; }
        input:focus { outline: none; border-color: #667eea; }
        .btn { padding: 12px 24px; border: none; border-radius: 8px; cursor: pointer; font-size: 15px; font-weight: 600; transition: all 0.3s; text-decoration: none; display: inline-block; text-align: center; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; width: 100%; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4); }
        .btn-secondary { background: #78909c; color: white; width: 100%; margin-top: 10px; }
        .btn-secondary:hover { background: #607d8b; }
        .help-text { color: #888; font-size: 12px; margin-top: 5px; }
        
        @media (max-width: 768px) {
            body { padding: 15px; }
            .container { padding: 30px 25px; max-width: 100%; }
            .header h1 { font-size: 22px; }
            .header p { font-size: 13px; }
            input[type="password"] { padding: 11px 14px; font-size: 14px; }
            .btn { padding: 11px 20px; font-size: 14px; }
        }
        
        @media (max-width: 480px) {
            .container { padding: 25px 20px; }
            .header h1 { font-size: 20px; }
            .header p { font-size: 12px; }
            input[type="password"] { padding: 10px 12px; font-size: 13px; }
            .btn { padding: 10px 18px; font-size: 13px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>修改密码</h1>
            <p>为了账户安全，请定期更换密码</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert <?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="new_username">用户名</label>
                <input type="text" id="new_username" name="new_username" value="<?php echo htmlspecialchars($currentUsername); ?>" placeholder="请输入新用户名">
                <p class="help-text">用户名长度至少为3个字符（可选修改）</p>
            </div>
            
            <div class="form-group">
                <label for="old_password">当前密码</label>
                <input type="password" id="old_password" name="old_password" required>
                <p class="help-text">请输入当前登录密码以验证身份</p>
            </div>
            
            <div class="form-group">
                <label for="new_password">新密码</label>
                <input type="password" id="new_password" name="new_password" placeholder="请输入新密码">
                <p class="help-text">密码长度至少为6个字符（可选修改）</p>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">确认新密码</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="请再次输入新密码">
                <p class="help-text">请再次输入新密码以确认（可选修改）</p>
            </div>
            
            <button type="submit" class="btn btn-primary">保存修改</button>
            <a href="dashboard.php" class="btn btn-secondary">返回</a>
            
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
        </form>
    </div>
</body>
</html>
