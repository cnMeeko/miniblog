<?php
require_once __DIR__ . '/Security.php';

class ArticleManager {
    
    private $documentsDir;
    private $categoriesFile;
    
    public function __construct($documentsDir) {
        $this->documentsDir = rtrim($documentsDir, '/');
        $this->categoriesFile = $this->documentsDir . '/categories.json';
        
        if (!is_dir($this->documentsDir)) {
            mkdir($this->documentsDir, 0755, true);
        }
        
        // 确保分类文件存在
        if (!file_exists($this->categoriesFile)) {
            file_put_contents($this->categoriesFile, json_encode([]));
        }
    }
    
    public function getAllArticles() {
        $articles = [];
        
        // 处理新的存储方式（文章目录中的MD文件）- 优先读取
        $dirs = glob($this->documentsDir . '/*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $dirFiles = glob($dir . '/*.md');
            foreach ($dirFiles as $file) {
                $article = $this->parseArticleFile($file);
                if ($article) {
                    $articles[] = $article;
                }
            }
        }
        
        // 处理旧的存储方式（根目录中的MD文件）
        $rootFiles = glob($this->documentsDir . '/*.md');
        foreach ($rootFiles as $file) {
            $article = $this->parseArticleFile($file);
            if ($article) {
                $articles[] = $article;
            }
        }
        
        // 去重，确保同一篇文章只添加一次
        $uniqueArticles = [];
        $articleTitles = [];
        foreach ($articles as $article) {
            if (!in_array($article['filename'], $articleTitles)) {
                $articleTitles[] = $article['filename'];
                $uniqueArticles[] = $article;
            }
        }
        
        usort($uniqueArticles, function($a, $b) {
            if ($b['created'] == $a['created']) {
                return 0;
            }
            return $b['created'] > $a['created'] ? 1 : -1;
        });
        
        return $uniqueArticles;
    }
    
    public function getArticle($title) {
        $sanitizedTitle = Security::sanitizeFilename($title);
        
        // 检查新的存储方式（文章目录中的文件）
        $articleDir = $this->documentsDir . '/' . $sanitizedTitle;
        $filePath = $articleDir . '/' . $sanitizedTitle . '.md';
        
        if (file_exists($filePath)) {
            return $this->parseArticleFile($filePath);
        }
        
        // 检查旧的存储方式（根目录中的文件）
        $filePath = $this->documentsDir . '/' . $sanitizedTitle . '.md';
        if (file_exists($filePath)) {
            return $this->parseArticleFile($filePath);
        }
        
        return null;
    }
    
    private function parseArticleFile($filePath) {
        if (!Security::validatePath($filePath, $this->documentsDir)) {
            return null;
        }
        
        $content = $this->getFileContentWithEncoding($filePath);
        if ($content === false) {
            return null;
        }
        
        $filename = basename($filePath, '.md');
        $title = $filename;
        $category = '';
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
        
        if (isset($frontMatter['category'])) {
            $category = $frontMatter['category'];
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
        if (function_exists('mb_strlen')) {
            if (mb_strlen($excerpt) > 200) {
                $excerpt = mb_substr($excerpt, 0, 200) . '...';
            }
        } else {
            if (strlen($excerpt) > 200) {
                $excerpt = substr($excerpt, 0, 200) . '...';
            }
        }
        
        return [
            'filename' => $filename,
            'title' => $title,
            'category' => $category,
            'content' => $body,
            'excerpt' => $excerpt,
            'created' => $created,
            'modified' => $modified,
            'created_date' => date('Y-m-d H:i:s', $created),
            'modified_date' => date('Y-m-d H:i:s', $modified)
        ];
    }
    
    public function createArticle($title, $content, $category = '') {
        $sanitizedTitle = Security::sanitizeFilename($title);
        $sanitizedCategory = Security::sanitizeInput($category);
        
        if (empty($sanitizedTitle)) {
            return ['success' => false, 'message' => '标题不能为空'];
        }
        
        if (!Security::validateContentType($content)) {
            return ['success' => false, 'message' => '内容包含非法字符或过大'];
        }
        
        $sanitizedContent = Security::sanitizeMarkdown($content);
        
        // 创建文章目录
        $articleDir = $this->documentsDir . '/' . $sanitizedTitle;
        if (!is_dir($articleDir)) {
            mkdir($articleDir, 0755, true);
        }
        
        $filePath = $articleDir . '/' . $sanitizedTitle . '.md';
        
        if (file_exists($filePath)) {
            return ['success' => false, 'message' => '文章已存在'];
        }
        
        $frontMatter = "---\ntitle: {$title}\n";
        if (!empty($sanitizedCategory)) {
            $frontMatter .= "category: {$sanitizedCategory}\n";
        }
        $frontMatter .= "---\n\n";
        $fullContent = $frontMatter . $sanitizedContent;
        
        if (file_put_contents($filePath, $fullContent, LOCK_EX) === false) {
            return ['success' => false, 'message' => '无法保存文章'];
        }
        
        return ['success' => true, 'message' => '文章创建成功', 'filename' => $sanitizedTitle];
    }
    
    public function updateArticle($oldTitle, $newTitle, $content, $category = '') {
        $sanitizedOldTitle = Security::sanitizeFilename($oldTitle);
        $sanitizedNewTitle = Security::sanitizeFilename($newTitle);
        $sanitizedCategory = Security::sanitizeInput($category);
        
        if (empty($sanitizedNewTitle)) {
            return ['success' => false, 'message' => '标题不能为空'];
        }
        
        if (!Security::validateContentType($content)) {
            return ['success' => false, 'message' => '内容包含非法字符或过大'];
        }
        
        $sanitizedContent = Security::sanitizeMarkdown($content);
        
        // 检查旧文章路径（兼容新旧存储方式）
        $oldFilePath = $this->documentsDir . '/' . $sanitizedOldTitle . '.md';
        $oldArticleDir = $this->documentsDir . '/' . $sanitizedOldTitle;
        $oldArticleFilePath = $oldArticleDir . '/' . $sanitizedOldTitle . '.md';
        
        if (!file_exists($oldFilePath) && !file_exists($oldArticleFilePath)) {
            return ['success' => false, 'message' => '文章不存在'];
        }
        
        // 确定实际的旧文件路径
        $actualOldFilePath = file_exists($oldArticleFilePath) ? $oldArticleFilePath : $oldFilePath;
        
        // 准备新文章路径（使用新的存储方式）
        $newArticleDir = $this->documentsDir . '/' . $sanitizedNewTitle;
        $newFilePath = $newArticleDir . '/' . $sanitizedNewTitle . '.md';
        
        if ($sanitizedOldTitle !== $sanitizedNewTitle && file_exists($newFilePath)) {
            return ['success' => false, 'message' => '新标题对应的文章已存在'];
        }
        
        $frontMatter = "---\ntitle: {$newTitle}\n";
        if (!empty($sanitizedCategory)) {
            $frontMatter .= "category: {$sanitizedCategory}\n";
        }
        $frontMatter .= "---\n\n";
        $fullContent = $frontMatter . $sanitizedContent;
        
        if ($sanitizedOldTitle !== $sanitizedNewTitle) {
            $this->renameArticleImages($sanitizedOldTitle, $sanitizedNewTitle);
        }
        
        // 创建新文章目录
        if (!is_dir($newArticleDir)) {
            mkdir($newArticleDir, 0755, true);
        }
        
        if (file_put_contents($newFilePath, $fullContent, LOCK_EX) === false) {
            return ['success' => false, 'message' => '无法保存文章'];
        }
        
        // 删除旧文件
        if (file_exists($actualOldFilePath)) {
            // 只有当旧文件路径和新文件路径不同时才删除
            if ($actualOldFilePath !== $newFilePath) {
                unlink($actualOldFilePath);
                // 如果是旧的存储方式，删除可能存在的图片文件
                if ($actualOldFilePath === $oldFilePath) {
                    $images = glob($this->documentsDir . '/' . $sanitizedOldTitle . '.*');
                    foreach ($images as $imagePath) {
                        if (pathinfo($imagePath, PATHINFO_EXTENSION) !== 'md') {
                            unlink($imagePath);
                        }
                    }
                }
            }
        }
        
        return ['success' => true, 'message' => '文章更新成功', 'filename' => $sanitizedNewTitle];
    }
    
    private function renameArticleImages($oldTitle, $newTitle) {
        $sanitizedOldTitle = Security::sanitizeFilename($oldTitle);
        $sanitizedNewTitle = Security::sanitizeFilename($newTitle);
        
        // 创建新文章目录
        $newArticleDir = $this->documentsDir . '/' . $sanitizedNewTitle;
        if (!is_dir($newArticleDir)) {
            mkdir($newArticleDir, 0755, true);
        }
        
        // 处理旧的存储方式（根目录中的图片）
        $oldImages = glob($this->documentsDir . '/' . $sanitizedOldTitle . '.*');
        foreach ($oldImages as $imagePath) {
            $ext = pathinfo($imagePath, PATHINFO_EXTENSION);
            if ($ext !== 'md') {
                $newImagePath = $newArticleDir . '/' . basename($imagePath);
                if (!file_exists($newImagePath)) {
                    rename($imagePath, $newImagePath);
                }
            }
        }
        
        // 处理新的存储方式（文章目录中的图片）
        $oldArticleDir = $this->documentsDir . '/' . $sanitizedOldTitle;
        if (is_dir($oldArticleDir)) {
            $dirImages = glob($oldArticleDir . '/*');
            foreach ($dirImages as $imagePath) {
                if (is_file($imagePath) && pathinfo($imagePath, PATHINFO_EXTENSION) !== 'md') {
                    $newImagePath = $newArticleDir . '/' . basename($imagePath);
                    if (!file_exists($newImagePath)) {
                        rename($imagePath, $newImagePath);
                    }
                }
            }
        }
    }
    
    public function deleteArticle($title) {
        $sanitizedTitle = Security::sanitizeFilename($title);
        
        // 检查文章路径（兼容新旧存储方式）
        $filePath = $this->documentsDir . '/' . $sanitizedTitle . '.md';
        $articleDir = $this->documentsDir . '/' . $sanitizedTitle;
        $articleFilePath = $articleDir . '/' . $sanitizedTitle . '.md';
        
        if (!file_exists($filePath) && !file_exists($articleFilePath)) {
            return ['success' => false, 'message' => '文章不存在'];
        }
        
        // 删除旧的存储方式的文件
        if (file_exists($filePath)) {
            if (!Security::validatePath($filePath, $this->documentsDir)) {
                return ['success' => false, 'message' => '非法路径'];
            }
            
            // 获取所有相关文件，排除MD文件
            $files = glob($this->documentsDir . '/' . $sanitizedTitle . '.*');
            foreach ($files as $fPath) {
                if (pathinfo($fPath, PATHINFO_EXTENSION) !== 'md' && is_file($fPath)) {
                    unlink($fPath);
                }
            }
            
            unlink($filePath);
        }
        
        // 删除新的存储方式的文件和目录
        if (is_dir($articleDir)) {
            if (!Security::validatePath($articleDir, $this->documentsDir)) {
                return ['success' => false, 'message' => '非法路径'];
            }
            
            // 删除目录中的所有文件
            $dirFiles = glob($articleDir . '/*');
            foreach ($dirFiles as $fPath) {
                if (is_file($fPath)) {
                    unlink($fPath);
                }
            }
            
            // 删除目录
            rmdir($articleDir);
        }
        
        return ['success' => true, 'message' => '文章删除成功'];
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
                strpos(strtolower($article['excerpt']), $query) !== false ||
                strpos(strtolower($article['category']), $query) !== false) {
                $results[] = $article;
            }
        }
        
        return $results;
    }
    
    public function getAllCategories() {
        $categories = json_decode(file_get_contents($this->categoriesFile), true);
        return is_array($categories) ? $categories : [];
    }
    
    public function addCategory($category) {
        $sanitizedCategory = Security::sanitizeInput($category);
        
        if (empty($sanitizedCategory)) {
            return ['success' => false, 'message' => '分类名称不能为空'];
        }
        
        $categories = $this->getAllCategories();
        
        if (!in_array($sanitizedCategory, $categories)) {
            $categories[] = $sanitizedCategory;
            if (file_put_contents($this->categoriesFile, json_encode($categories, JSON_UNESCAPED_UNICODE)) === false) {
                return ['success' => false, 'message' => '无法添加分类'];
            }
        }
        
        return ['success' => true, 'message' => '分类添加成功'];
    }
    
    public function deleteCategory($category) {
        $sanitizedCategory = Security::sanitizeInput($category);
        
        $categories = $this->getAllCategories();
        $key = array_search($sanitizedCategory, $categories);
        
        if ($key !== false) {
            unset($categories[$key]);
            $categories = array_values($categories); // 重新索引数组
            if (file_put_contents($this->categoriesFile, json_encode($categories, JSON_UNESCAPED_UNICODE)) === false) {
                return ['success' => false, 'message' => '无法删除分类'];
            }
        }
        
        // 更新该分类下的所有文章
        $articlesInCategory = $this->getArticlesByCategory($sanitizedCategory);
        if (!empty($articlesInCategory)) {
            foreach ($articlesInCategory as $article) {
                $this->updateArticle($article['filename'], $article['title'], $article['content'], '');
            }
        }
        
        return ['success' => true, 'message' => '分类删除成功'];
    }
    
    public function getArticlesByCategory($category) {
        $articles = $this->getAllArticles();
        $results = [];
        
        foreach ($articles as $article) {
            if ($article['category'] === $category) {
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
        
        // 首先在文章目录中查找图片
        $articleDir = $this->documentsDir . '/' . $sanitizedTitle;
        $imagePath = $articleDir . '/' . $extension;
        
        if (!Security::validatePath($imagePath, $this->documentsDir)) {
            return null;
        }
        
        if (!file_exists($imagePath)) {
            // 兼容旧的存储方式，在根目录中查找
            $imagePath = $this->documentsDir . '/' . $sanitizedTitle . '.' . $sanitizedExt;
            if (!Security::validatePath($imagePath, $this->documentsDir) || !file_exists($imagePath)) {
                return null;
            }
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
        
        // 创建文章目录
        $articleDir = $this->documentsDir . '/' . $sanitizedTitle;
        if (!is_dir($articleDir)) {
            mkdir($articleDir, 0755, true);
        }
        
        $imagePath = $articleDir . '/' . basename($file['name']);
        
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
        
        if (move_uploaded_file($file['tmp_name'], $imagePath)) {
            return ['success' => true, 'message' => '图片上传成功', 'path' => $imagePath];
        }
        
        return ['success' => false, 'message' => '无法保存图片'];
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
}