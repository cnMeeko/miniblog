<?php
class Security {
    
    private static $allowedExtensions = ['md', 'txt', 'json', 'zip'];
    private static $blockedPatterns = [
        '/\.\./',
        '/\0/',
        '/<\?php/i',
        '/<script/i',
        '/eval\s*\(/i',
        '/exec\s*\(/i',
        '/system\s*\(/i',
        '/passthru\s*\(/i',
        '/shell_exec\s*\(/i',
        '/base64_decode\s*\(/i',
        '/assert\s*\(/i',
        '/create_function\s*\(/i',
        '/file_get_contents\s*\(/i',
        '/file_put_contents\s*\(/i',
        '/fopen\s*\(/i',
        '/fwrite\s*\(/i',
        '/include\s*\(/i',
        '/require\s*\(/i',
    ];
    
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
    
    public static function sanitizeFilename($filename) {
        $filename = basename($filename);
        $filename = preg_replace('/[^a-zA-Z0-9_\-\.\p{Han}]/u', '', $filename);
        $filename = preg_replace('/\.+/', '.', $filename);
        return $filename;
    }
    
    public static function validatePath($path, $baseDir) {
        $realPath = realpath($path);
        $realBaseDir = realpath($baseDir);
        
        if ($realPath === false || $realBaseDir === false) {
            return false;
        }
        
        return strpos($realPath, $realBaseDir) === 0;
    }
    
    public static function detectMaliciousContent($content) {
        foreach (self::$blockedPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        return false;
    }
    
    public static function validateFileExtension($filename) {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, self::$allowedExtensions);
    }
    
    public static function generateCsrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validateCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function regenerateCsrfToken() {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }
    
    public static function escapeHtml($string) {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
    
    public static function escapeJs($string) {
        return json_encode($string, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
    
    public static function validateContentType($content) {
        $maxSize = 10 * 1024 * 1024;
        if (strlen($content) > $maxSize) {
            return false;
        }
        
        // 移除可能的控制字符，但保留换行符和制表符
        $cleanContent = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $content);
        
        // 检测恶意内容前，先处理代码块，避免误判
        $cleanContent = self::processCodeBlocks($cleanContent);
        
        if (self::detectMaliciousContent($cleanContent)) {
            return false;
        }
        
        return true;
    }
    
    private static function processCodeBlocks($content) {
        // 匹配代码块（```开头和结尾）
        $content = preg_replace_callback('/```[\s\S]*?```/', function($matches) {
            // 对于代码块内容，只保留空格和可见字符
            return preg_replace('/[^\s\x20-\x7E]/', '', $matches[0]);
        }, $content);
        
        // 匹配行内代码（`开头和结尾）
        $content = preg_replace_callback('/`[^`]*`/', function($matches) {
            // 对于行内代码内容，只保留空格和可见字符
            return preg_replace('/[^\s\x20-\x7E]/', '', $matches[0]);
        }, $content);
        
        return $content;
    }
    
    public static function rateLimitCheck($identifier = null, $maxAttempts = 5, $timeWindow = 300) {
        if ($identifier === null) {
            $identifier = $_SERVER['REMOTE_ADDR'];
        }
        
        $key = 'rate_limit_' . md5($identifier);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['attempts' => 0, 'first_attempt' => time()];
        }
        
        $data = $_SESSION[$key];
        $elapsed = time() - $data['first_attempt'];
        
        if ($elapsed > $timeWindow) {
            $_SESSION[$key] = ['attempts' => 1, 'first_attempt' => time()];
            return true;
        }
        
        if ($data['attempts'] >= $maxAttempts) {
            return false;
        }
        
        $_SESSION[$key]['attempts']++;
        return true;
    }
    
    public static function clearRateLimit($identifier = null) {
        if ($identifier === null) {
            $identifier = $_SERVER['REMOTE_ADDR'];
        }
        $key = 'rate_limit_' . md5($identifier);
        unset($_SESSION[$key]);
    }
    
    public static function checkAdminSession() {
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            header('Location: login.php');
            exit;
        }
    }
    
    public static function isAdminLoggedIn() {
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }
    
    public static function setAdminSession() {
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_login_time'] = time();
    }
    
    public static function destroyAdminSession() {
        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();
    }
    
    public static function checkSessionTimeout($timeout = 1800) {
        if (isset($_SESSION['admin_login_time'])) {
            $elapsed = time() - $_SESSION['admin_login_time'];
            if ($elapsed > $timeout) {
                self::destroyAdminSession();
                header('Location: login.php?timeout=1');
                exit;
            }
            $_SESSION['admin_login_time'] = time();
        }
    }
    
    public static function sanitizeMarkdown($markdown) {
        $markdown = preg_replace('/<\?php/i', '&lt;?php', $markdown);
        $markdown = preg_replace('/<script/i', '&lt;script', $markdown);
        $markdown = preg_replace('/javascript:/i', 'javascript&#58;', $markdown);
        $markdown = preg_replace('/on\w+\s*=/i', 'data-disabled=', $markdown);
        return $markdown;
    }
    
    public static function logSecurityEvent($event, $details = []) {
        $logFile = __DIR__ . '/../backups/security.log';
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $logEntry = sprintf(
            "[%s] [%s] %s %s\n",
            $timestamp,
            $ip,
            $event,
            json_encode($details, JSON_UNESCAPED_UNICODE)
        );
        
        $logs = glob(__DIR__ . '/../backups/security_*.log');
        if (count($logs) > 10) {
            usort($logs, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            unlink($logs[0]);
        }
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
