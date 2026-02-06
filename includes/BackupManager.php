<?php
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/ArticleManager.php';

class BackupManager {
    
    private $backupsDir;
    private $documentsDir;
    private $articleManager;
    
    public function __construct($backupsDir, $documentsDir) {
        $this->backupsDir = rtrim($backupsDir, '/\\');
        $this->documentsDir = rtrim($documentsDir, '/\\');
        $this->articleManager = new ArticleManager($documentsDir);
        
        if (!is_dir($this->backupsDir)) {
            mkdir($this->backupsDir, 0755, true);
        }
    }
    
    public function createBackup() {
        $timestamp = date('Y-m-d_H-i-s');
        $backupName = 'backup_' . $timestamp . '.zip';
        $backupPath = $this->backupsDir . '/' . $backupName;
        
        $zip = new ZipArchive();
        
        if ($zip->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return ['success' => false, 'message' => '无法创建备份文件'];
        }
        
        $fileCount = 0;
        
        // 递归添加所有文件
        $this->addDirectoryToZip($zip, $this->documentsDir, '');
        
        // 统计实际备份的文件数
        $fileCount = $zip->numFiles - 1; // 减去 metadata.json
        
        $metadata = [
            'created' => time(),
            'created_date' => date('Y-m-d H:i:s'),
            'article_count' => $fileCount,
            'version' => '1.0'
        ];
        
        $zip->addFromString('metadata.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        if ($zip->close() === false) {
            return ['success' => false, 'message' => '无法保存备份文件'];
        }
        
        $this->cleanupOldBackups();
        
        return ['success' => true, 'message' => '备份创建成功', 'backup_name' => $backupName];
    }
    
    private function addDirectoryToZip($zip, $dir, $prefix) {
        $files = glob($dir . '/*');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $filename = basename($file);
                $arcname = $prefix . $filename;
                $zip->addFile($file, $arcname);
            } elseif (is_dir($file)) {
                $dirname = basename($file);
                $newPrefix = $prefix . $dirname . '/';
                $this->addDirectoryToZip($zip, $file, $newPrefix);
            }
        }
    }
    
    private function restoreFilesFromDirectory($sourceDir, $destDir, &$restoredCount) {
        $files = glob($sourceDir . '/*');
        
        foreach ($files as $file) {
            $filename = basename($file);
            
            if ($filename === 'metadata.json') {
                continue;
            }
            
            if (!Security::validatePath($file, $sourceDir)) {
                continue;
            }
            
            $destPath = $destDir . '/' . $filename;
            
            if (is_file($file)) {
                $content = file_get_contents($file);
                if ($content !== false && Security::validateContentType($content)) {
                    if (file_put_contents($destPath, $content, LOCK_EX) !== false) {
                        $restoredCount++;
                    }
                }
            } elseif (is_dir($file)) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
                $this->restoreFilesFromDirectory($file, $destPath, $restoredCount);
            }
        }
    }
    
    public function restoreBackup($backupName) {
        $sanitizedBackupName = Security::sanitizeFilename($backupName);
        $backupPath = $this->backupsDir . '/' . $sanitizedBackupName;
        
        if (!file_exists($backupPath)) {
            return ['success' => false, 'message' => '备份文件不存在'];
        }
        
        if (!Security::validatePath($backupPath, $this->backupsDir)) {
            return ['success' => false, 'message' => '非法备份路径'];
        }
        
        if (!Security::validateFileExtension($backupName)) {
            return ['success' => false, 'message' => '不支持的备份文件类型'];
        }
        
        $zip = new ZipArchive();
        
        if ($zip->open($backupPath) !== true) {
            return ['success' => false, 'message' => '无法打开备份文件'];
        }
        
        $metadata = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if ($filename === 'metadata.json') {
                $metadata = json_decode($zip->getFromIndex($i), true);
                break;
            }
        }
        
        if ($metadata === null) {
            $zip->close();
            return ['success' => false, 'message' => '备份文件格式错误'];
        }
        
        $tempDir = $this->backupsDir . '/temp_restore_' . time();
        if (!mkdir($tempDir, 0755, true)) {
            $zip->close();
            return ['success' => false, 'message' => '无法创建临时目录'];
        }
        
        if ($zip->extractTo($tempDir) === false) {
            $zip->close();
            $this->cleanupDirectory($tempDir);
            return ['success' => false, 'message' => '无法解压备份文件'];
        }
        
        $zip->close();
        
        $restoredCount = 0;
        
        // 递归恢复所有文件
        $this->restoreFilesFromDirectory($tempDir, $this->documentsDir, $restoredCount);
        
        $this->cleanupDirectory($tempDir);
        
        return [
            'success' => true,
            'message' => "恢复成功，共恢复 {$restoredCount} 个文件",
            'restored_count' => $restoredCount
        ];
    }
    
    public function getBackupList() {
        $backups = [];
        $files = glob($this->backupsDir . '/backup_*.zip');
        
        foreach ($files as $file) {
            $filename = basename($file);
            $size = filesize($file);
            $modified = filemtime($file);
            
            $zip = new ZipArchive();
            if ($zip->open($file) === true) {
                $articleCount = 0;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $name = $zip->getNameIndex($i);
                    if ($name !== 'metadata.json' && pathinfo($name, PATHINFO_EXTENSION) === 'md') {
                        $articleCount++;
                    }
                }
                $zip->close();
                
                $backups[] = [
                    'name' => $filename,
                    'size' => $this->formatFileSize($size),
                    'size_bytes' => $size,
                    'modified' => $modified,
                    'modified_date' => date('Y-m-d H:i:s', $modified),
                    'article_count' => $articleCount
                ];
            }
        }
        
        usort($backups, function($a, $b) {
            return $b['modified'] <=> $a['modified'];
        });
        
        return $backups;
    }
    
    public function deleteBackup($backupName) {
        $sanitizedBackupName = Security::sanitizeFilename($backupName);
        $backupPath = $this->backupsDir . '/' . $sanitizedBackupName;
        
        if (!file_exists($backupPath)) {
            return ['success' => false, 'message' => '备份文件不存在'];
        }
        
        if (!Security::validatePath($backupPath, $this->backupsDir)) {
            return ['success' => false, 'message' => '非法备份路径'];
        }
        
        if (unlink($backupPath)) {
            return ['success' => true, 'message' => '备份删除成功'];
        }
        
        return ['success' => false, 'message' => '无法删除备份'];
    }
    
    public function exportArticle($title) {
        $article = $this->articleManager->getArticle($title);
        
        if ($article === null) {
            return ['success' => false, 'message' => '文章不存在'];
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $exportName = $article['filename'] . '_' . $timestamp . '.md';
        $exportPath = $this->backupsDir . '/' . $exportName;
        
        $content = "---\n";
        $content .= "title: {$article['title']}\n";
        if (!empty($article['category'])) {
            $content .= "category: {$article['category']}\n";
        }
        $content .= "created: {$article['created_date']}\n";
        $content .= "modified: {$article['modified_date']}\n";
        $content .= "---\n\n";
        $content .= $article['content'];
        
        if (file_put_contents($exportPath, $content, LOCK_EX) === false) {
            return ['success' => false, 'message' => '无法导出文章'];
        }
        
        return [
            'success' => true,
            'message' => '文章导出成功',
            'export_name' => $exportName,
            'export_path' => $exportPath
        ];
    }
    
    public function importArticle($file, $importCategory = '') {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => '无效的文件上传'];
        }
        
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (strtolower($ext) !== 'md') {
            return ['success' => false, 'message' => '只支持 .md 文件'];
        }
        
        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'message' => '文件大小不能超过5MB'];
        }
        
        $content = $this->getFileContentWithEncoding($file['tmp_name']);
        if ($content === false) {
            return ['success' => false, 'message' => '无法读取文件'];
        }
        
        if (!Security::validateContentType($content)) {
            return ['success' => false, 'message' => '文件内容包含非法字符'];
        }
        
        $lines = explode("\n", $content);
        $title = '';
        $body = '';
        $inFrontMatter = false;
        $frontMatter = [];
        
        foreach ($lines as $i => $line) {
            if (trim($line) === '---') {
                if (!$inFrontMatter && $i === 0) {
                    $inFrontMatter = true;
                    continue;
                } elseif ($inFrontMatter) {
                    $inFrontMatter = false;
                    $body = implode("\n", array_slice($lines, $i + 1));
                    break;
                }
            }
            
            if ($inFrontMatter) {
                if (strpos($line, ':') !== false) {
                    list($key, $value) = explode(':', $line, 2);
                    $frontMatter[trim($key)] = trim($value);
                }
            }
        }
        
        if (isset($frontMatter['title'])) {
            $title = $frontMatter['title'];
        } else {
            $title = pathinfo($file['name'], PATHINFO_FILENAME);
        }
        
        $category = isset($frontMatter['category']) ? $frontMatter['category'] : '';
        
        // 如果用户指定了分类，则覆盖文件中的分类
        if (!empty($importCategory)) {
            $category = $importCategory;
        }
        
        if (empty($body)) {
            $body = $content;
        }
        
        $result = $this->articleManager->createArticle($title, $body, $category);
        
        return $result;
    }
    
    private function cleanupOldBackups($keepCount = 10) {
        $backups = glob($this->backupsDir . '/backup_*.zip');
        
        if (count($backups) <= $keepCount) {
            return;
        }
        
        usort($backups, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        $toDelete = count($backups) - $keepCount;
        for ($i = 0; $i < $toDelete; $i++) {
            if (file_exists($backups[$i])) {
                unlink($backups[$i]);
            }
        }
    }
    
    private function cleanupDirectory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            } elseif (is_dir($file)) {
                $this->cleanupDirectory($file);
                rmdir($file);
            }
        }
        
        rmdir($dir);
    }
    
    private function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    private function getFileContentWithEncoding($filePath) {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return false;
        }
        
        // 检测并处理BOM（字节顺序标记）
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        } elseif (substr($content, 0, 2) === "\xFF\xFE") {
            $content = substr($content, 2);
            // 简单处理UTF-16LE，移除奇数字节
            $content = preg_replace('/(.)./', '$1', $content);
        } elseif (substr($content, 0, 2) === "\xFE\xFF") {
            $content = substr($content, 2);
            // 简单处理UTF-16BE，移除奇数字节
            $content = preg_replace('/(.)./', '$1', $content);
        }
        
        // 标准化行尾格式为Unix风格
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        
        return $content;
    }
    
    public function uploadAndRestoreBackup($file) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => '无效的文件上传'];
        }
        
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (strtolower($ext) !== 'zip') {
            return ['success' => false, 'message' => '只支持 .zip 文件'];
        }
        
        $maxSize = 20 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'message' => '文件大小不能超过20MB'];
        }
        
        $zip = new ZipArchive();
        
        if ($zip->open($file['tmp_name']) !== true) {
            return ['success' => false, 'message' => '无法打开备份文件'];
        }
        
        $metadata = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if ($filename === 'metadata.json') {
                $metadata = json_decode($zip->getFromIndex($i), true);
                break;
            }
        }
        
        if ($metadata === null) {
            $zip->close();
            return ['success' => false, 'message' => '备份文件格式错误'];
        }
        
        $tempDir = $this->backupsDir . '/temp_upload_' . time();
        if (!mkdir($tempDir, 0755, true)) {
            $zip->close();
            return ['success' => false, 'message' => '无法创建临时目录'];
        }
        
        if ($zip->extractTo($tempDir) === false) {
            $zip->close();
            $this->cleanupDirectory($tempDir);
            return ['success' => false, 'message' => '无法解压备份文件'];
        }
        
        $zip->close();
        
        $restoredCount = 0;
        
        // 递归恢复所有文件
        $this->restoreFilesFromDirectory($tempDir, $this->documentsDir, $restoredCount);
        
        $this->cleanupDirectory($tempDir);
        
        return [
            'success' => true,
            'message' => "恢复成功，共恢复 {$restoredCount} 个文件",
            'restored_count' => $restoredCount
        ];
    }
}
