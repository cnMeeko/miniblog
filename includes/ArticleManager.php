<?php
require_once __DIR__ . '/Security.php';

class ArticleManager {
    
    private $documentsDir;
    private $security;
    
    public function __construct($documentsDir) {
        $this->documentsDir = rtrim($documentsDir, '/\\');
        $this->security = new Security();
        
        if (!is_dir($this->documentsDir)) {
            mkdir($this->documentsDir, 0755, true);
        }
    }
    
    public function getAllArticles() {
        $articles = [];
        $files = glob($this->documentsDir . '/*.md');
        
        foreach ($files as $file) {
            $article = $this->parseArticleFile($file);
            if ($article) {
                $articles[] = $article;
            }
        }
        
        usort($articles, function($a, $b) {
            return $b['created'] <=> $a['created'];
        });
        
        return $articles;
    }
    
    public function getArticle($title) {
        $sanitizedTitle = Security::sanitizeFilename($title);
        $filePath = $this->documentsDir . '/' . $sanitizedTitle . '.md';
        
        if (!file_exists($filePath)) {
            return null;
        }
        
        return $this->parseArticleFile($filePath);
    }
    
    private function parseArticleFile($filePath) {
        if (!$this->security->validatePath($filePath, $this->documentsDir)) {
            return null;
        }
        
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }
        
        $filename = basename($filePath, '.md');
        $title = $filename;
        $excerpt = '';
        $body = $content;
        $created = filectime($filePath);
        $modified = filemtime($filePath);
        
        $lines = explode("\n", $content);
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
        }
        
        $bodyLines = explode("\n", $body);
        $excerptLines = [];
        $inCodeBlock = false;
        
        foreach ($bodyLines as $line) {
            if (preg_match('/^```/', $line)) {
                $inCodeBlock = !$inCodeBlock;
                continue;
            }
            
            if (!$inCodeBlock && trim($line) !== '' && !preg_match('/^#+\s/', $line)) {
                $excerptLines[] = $line;
                if (count($excerptLines) >= 3) {
                    break;
                }
            }
        }
        
        $excerpt = implode(' ', $excerptLines);
        if (mb_strlen($excerpt) > 200) {
            $excerpt = mb_substr($excerpt, 0, 200) . '...';
        }
        
        return [
            'filename' => $filename,
            'title' => $title,
            'content' => $body,
            'excerpt' => $excerpt,
            'created' => $created,
            'modified' => $modified,
            'created_date' => date('Y-m-d H:i:s', $created),
            'modified_date' => date('Y-m-d H:i:s', $modified)
        ];
    }
    
    public function createArticle($title, $content) {
        $sanitizedTitle = Security::sanitizeFilename($title);
        
        if (empty($sanitizedTitle)) {
            return ['success' => false, 'message' => '标题不能为空'];
        }
        
        if (!$this->security->validateContentType($content)) {
            return ['success' => false, 'message' => '内容包含非法字符或过大'];
        }
        
        $sanitizedContent = Security::sanitizeMarkdown($content);
        
        $filePath = $this->documentsDir . '/' . $sanitizedTitle . '.md';
        
        if (file_exists($filePath)) {
            return ['success' => false, 'message' => '文章已存在'];
        }
        
        $frontMatter = "---\ntitle: {$title}\n---\n\n";
        $fullContent = $frontMatter . $sanitizedContent;
        
        if (file_put_contents($filePath, $fullContent, LOCK_EX) === false) {
            return ['success' => false, 'message' => '无法保存文章'];
        }
        
        return ['success' => true, 'message' => '文章创建成功', 'filename' => $sanitizedTitle];
    }
    
    public function updateArticle($oldTitle, $newTitle, $content) {
        $sanitizedOldTitle = Security::sanitizeFilename($oldTitle);
        $sanitizedNewTitle = Security::sanitizeFilename($newTitle);
        
        if (empty($sanitizedNewTitle)) {
            return ['success' => false, 'message' => '标题不能为空'];
        }
        
        if (!$this->security->validateContentType($content)) {
            return ['success' => false, 'message' => '内容包含非法字符或过大'];
        }
        
        $sanitizedContent = Security::sanitizeMarkdown($content);
        
        $oldFilePath = $this->documentsDir . '/' . $sanitizedOldTitle . '.md';
        $newFilePath = $this->documentsDir . '/' . $sanitizedNewTitle . '.md';
        
        if (!file_exists($oldFilePath)) {
            return ['success' => false, 'message' => '文章不存在'];
        }
        
        if ($sanitizedOldTitle !== $sanitizedNewTitle && file_exists($newFilePath)) {
            return ['success' => false, 'message' => '新标题对应的文章已存在'];
        }
        
        $frontMatter = "---\ntitle: {$newTitle}\n---\n\n";
        $fullContent = $frontMatter . $sanitizedContent;
        
        if ($sanitizedOldTitle !== $sanitizedNewTitle) {
            $this->renameArticleImages($sanitizedOldTitle, $sanitizedNewTitle);
        }
        
        if (file_put_contents($newFilePath, $fullContent, LOCK_EX) === false) {
            return ['success' => false, 'message' => '无法保存文章'];
        }
        
        if ($sanitizedOldTitle !== $sanitizedNewTitle && file_exists($oldFilePath)) {
            unlink($oldFilePath);
        }
        
        return ['success' => true, 'message' => '文章更新成功', 'filename' => $sanitizedNewTitle];
    }
    
    private function renameArticleImages($oldTitle, $newTitle) {
        $images = glob($this->documentsDir . '/' . $oldTitle . '.*');
        
        foreach ($images as $imagePath) {
            $ext = pathinfo($imagePath, PATHINFO_EXTENSION);
            $newImagePath = $this->documentsDir . '/' . $newTitle . '.' . $ext;
            
            if (!file_exists($newImagePath)) {
                rename($imagePath, $newImagePath);
            }
        }
    }
    
    public function deleteArticle($title) {
        $sanitizedTitle = Security::sanitizeFilename($title);
        $filePath = $this->documentsDir . '/' . $sanitizedTitle . '.md';
        
        if (!file_exists($filePath)) {
            return ['success' => false, 'message' => '文章不存在'];
        }
        
        if (!$this->security->validatePath($filePath, $this->documentsDir)) {
            return ['success' => false, 'message' => '非法路径'];
        }
        
        $images = glob($this->documentsDir . '/' . $sanitizedTitle . '.*');
        
        foreach ($images as $imagePath) {
            if (is_file($imagePath)) {
                unlink($imagePath);
            }
        }
        
        if (unlink($filePath)) {
            return ['success' => true, 'message' => '文章删除成功'];
        }
        
        return ['success' => false, 'message' => '无法删除文章'];
    }
    
    public function searchArticles($query) {
        $articles = $this->getAllArticles();
        $results = [];
        
        if (empty($query)) {
            return $articles;
        }
        
        $query = strtolower($query);
        
        foreach ($articles as $article) {
            if (strpos(strtolower($article['title']), $query) !== false ||
                strpos(strtolower($article['content']), $query) !== false ||
                strpos(strtolower($article['excerpt']), $query) !== false) {
                $results[] = $article;
            }
        }
        
        return $results;
    }
    
    public function getArticleImage($title, $extension) {
        $sanitizedTitle = Security::sanitizeFilename($title);
        $sanitizedExt = Security::sanitizeFilename($extension);
        
        $allowedImageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
        if (!in_array(strtolower($sanitizedExt), $allowedImageExts)) {
            return null;
        }
        
        $imagePath = $this->documentsDir . '/' . $sanitizedTitle . '.' . $sanitizedExt;
        
        if (!$this->security->validatePath($imagePath, $this->documentsDir)) {
            return null;
        }
        
        if (!file_exists($imagePath)) {
            return null;
        }
        
        return $imagePath;
    }
    
    public function uploadArticleImage($title, $file) {
        $sanitizedTitle = Security::sanitizeFilename($title);
        
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['success' => false, 'message' => '无效的文件上传'];
        }
        
        $fileInfo = getimagesize($file['tmp_name']);
        if ($fileInfo === false) {
            return ['success' => false, 'message' => '不是有效的图片文件'];
        }
        
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($fileInfo['mime'], $allowedTypes)) {
            return ['success' => false, 'message' => '不支持的图片类型'];
        }
        
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $ext = strtolower($ext);
        
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowedExts)) {
            return ['success' => false, 'message' => '不支持的文件扩展名'];
        }
        
        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'message' => '图片大小不能超过5MB'];
        }
        
        $imagePath = $this->documentsDir . '/' . $sanitizedTitle . '.' . $ext;
        
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
        
        if (move_uploaded_file($file['tmp_name'], $imagePath)) {
            return ['success' => true, 'message' => '图片上传成功', 'path' => $imagePath];
        }
        
        return ['success' => false, 'message' => '无法保存图片'];
    }
}
