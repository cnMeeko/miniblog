<?php
require_once '../includes/config.php';
require_once '../includes/Security.php';

$error = '';
$timeout = isset($_GET['timeout']) ? '会话已超时，请重新登录' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::rateLimitCheck()) {
        $error = '登录尝试次数过多，请稍后再试';
    } else {
        $username = Security::sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $csrfToken = $_POST['csrf_token'] ?? '';
        
        if (!Security::validateCsrfToken($csrfToken)) {
            $error = 'CSRF令牌验证失败';
        } elseif (empty($username) || empty($password)) {
            $error = '请输入用户名和密码';
        } else {
            if (file_exists(ADMIN_CREDENTIALS_FILE)) {
                require ADMIN_CREDENTIALS_FILE;
                
                if (isset($admin_username) && isset($admin_password_hash)) {
                    if ($username === $admin_username && password_verify($password, $admin_password_hash)) {
                        Security::setAdminSession();
                        Security::clearRateLimit();
                        Security::logSecurityEvent('admin_login_success', ['username' => $username]);
                        header('Location: dashboard.php');
                        exit;
                    } else {
                        $error = '用户名或密码错误';
                        Security::logSecurityEvent('admin_login_failed', ['username' => $username]);
                    }
                } else {
                    $error = '配置文件损坏，请重新安装';
                }
            } else {
                header('Location: ../install.php');
                exit;
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
    <title>登录 - <?php echo SITE_NAME; ?> 管理后台</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .login-container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); width: 100%; max-width: 400px; }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo h1 { color: #333; font-size: 28px; margin-bottom: 8px; }
        .logo p { color: #888; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; color: #555; font-size: 14px; }
        input[type="text"], input[type="password"] { width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: border-color 0.3s; }
        input:focus { outline: none; border-color: #667eea; }
        button { width: 100%; padding: 14px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; }
        button:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4); }
        button:active { transform: translateY(0); }
        .error { background: #fee; color: #c33; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; border-left: 4px solid #c33; }
        .success { background: #efe; color: #3c3; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; border-left: 4px solid #3c3; }
        .info { background: #e3f2fd; color: #1565c0; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; line-height: 1.6; border-left: 4px solid #1565c0; }
        .footer { text-align: center; margin-top: 24px; color: #999; font-size: 13px; }
        .footer a { color: #667eea; text-decoration: none; }
        .footer a:hover { text-decoration: underline; }
        
        @media (max-width: 768px) {
            body { padding: 15px; }
            .login-container { padding: 30px 25px; max-width: 100%; }
            .logo h1 { font-size: 24px; }
            .logo p { font-size: 13px; }
            label { font-size: 13px; }
            input[type="text"], input[type="password"] { padding: 11px 14px; font-size: 14px; }
            button { padding: 12px; font-size: 15px; }
            .error, .success, .info { font-size: 13px; padding: 10px; }
        }
        
        @media (max-width: 480px) {
            .login-container { padding: 25px 20px; }
            .logo h1 { font-size: 22px; }
            .logo p { font-size: 12px; }
            input[type="text"], input[type="password"] { padding: 10px 12px; font-size: 13px; }
            button { padding: 11px; font-size: 14px; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1><?php echo SITE_NAME; ?></h1>
            <p>管理后台登录</p>
        </div>
        
        <?php if ($timeout): ?>
            <div class="success"><?php echo htmlspecialchars($timeout); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" required>
            </div>
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <button type="submit">登录</button>
        </form>
        
        <div class="footer">
            <a href="../index.php">返回主页</a> · <a href="../install.php">重新安装系统</a>
        </div>
    </div>
</body>
</html>
