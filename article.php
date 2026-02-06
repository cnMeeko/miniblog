<?php
require_once 'includes/config.php';
require_once 'includes/Security.php';
require_once 'includes/ArticleManager.php';

$articleManager = new ArticleManager(DOCUMENTS_DIR);

$title = Security::sanitizeInput($_GET['title'] ?? '');
$article = $articleManager->getArticle($title);

if (!$article) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>文章不存在</title><style>body{font-family:sans-serif;text-align:center;padding:50px;}</style></head><body><h1>404 - 文章不存在</h1><p>抱歉，您访问的文章不存在。</p><a href="index.php">返回首页</a></body></html>';
    exit;
}

function parseMarkdown($text) {
    // 先提取代码块，避免被htmlspecialchars处理
    $codeBlocks = array();
    $text = preg_replace_callback('/```(\w*)\n([\s\S]*?)```/', function($matches) use (&$codeBlocks) {
        $index = count($codeBlocks);
        $codeBlocks[$index] = array(
            'lang' => $matches[1],
            'content' => $matches[2]
        );
        return "{{CODE_BLOCK_$index}}";
    }, $text);
    
    // 处理其他内容
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $text = preg_replace('/^### (.*$)/m', '<h3>$1</h3>', $text);
    $text = preg_replace('/^## (.*$)/m', '<h2>$1</h2>', $text);
    $text = preg_replace('/^# (.*$)/m', '<h1>$1</h1>', $text);
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/`([^`]+)`/', '<code class="inline-code">$1</code>', $text);
    
    $text = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1" class="article-image">', $text);
    $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $text);
    
    $text = preg_replace('/^\- (.*$)/m', '<li>$1</li>', $text);
    $text = preg_replace('/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $text);
    
    $text = preg_replace('/^\d+\. (.*$)/m', '<li>$1</li>', $text);
    $text = preg_replace('/(<li>.*<\/li>\n?)+/', '<ol>$0</ol>', $text);
    
    // 恢复代码块
    foreach ($codeBlocks as $index => $block) {
        $codeContent = htmlspecialchars($block['content'], ENT_QUOTES, 'UTF-8');
        $codeHtml = '<div class="code-container"><div class="code-header"><span class="code-lang">' . htmlspecialchars($block['lang']) . '</span><button class="copy-button" onclick="copyCode(this)">📋</button></div><pre><code class="code-block">' . $codeContent . '</code></pre></div>';
        $text = str_replace("{{CODE_BLOCK_$index}}", $codeHtml, $text);
    }
    
    $text = preg_replace('/\n\n/', '</p><p>', $text);
    $text = '<p>' . $text . '</p>';
    $text = str_replace('<p></p>', '', $text);
    $text = str_replace('<p><div class="code-container">', '<div class="code-container">', $text);
    $text = str_replace('</div></p>', '</div>', $text);
    $text = str_replace('<p><pre>', '<pre>', $text);
    $text = str_replace('</pre></p>', '</pre>', $text);
    $text = str_replace('<p><ul>', '<ul>', $text);
    $text = str_replace('</ul></p>', '</ul>', $text);
    $text = str_replace('<p><ol>', '<ol>', $text);
    $text = str_replace('</ol></p>', '</ol>', $text);
    $text = str_replace('<p><h1>', '<h1>', $text);
    $text = str_replace('</h1></p>', '</h1>', $text);
    $text = str_replace('<p><h2>', '<h2>', $text);
    $text = str_replace('</h2></p>', '</h2>', $text);
    $text = str_replace('<p><h3>', '<h3>', $text);
    $text = str_replace('</h3></p>', '</h3>', $text);
    
    return $text;
}

$images = glob(DOCUMENTS_DIR . '/' . $article['filename'] . '.*');
$imageFiles = array_filter($images, function($img) {
    return pathinfo($img, PATHINFO_EXTENSION) !== 'md';
});
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($article['title']); ?> - <?php echo SITE_NAME; ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($article['excerpt']); ?>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; line-height: 1.8; color: #333; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; text-align: center; width: 100%; }
        .header h1 { font-size: 28px; margin-bottom: 10px; }
        .header p { font-size: 14px; opacity: 0.9; }
        .container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 40px 20px; display: flex; gap: 30px; }
        .sidebar { width: 200px; flex-shrink: 0; }
        .sidebar h3 { font-size: 18px; margin-bottom: 15px; color: #333; }
        .category-list { list-style: none; }
        .category-list li { margin-bottom: 10px; }
        .category-list li a { color: #666; text-decoration: none; transition: color 0.3s; display: block; padding: 8px 12px; border-radius: 6px; }
        .category-list li a:hover { color: #667eea; background: #f0f0f0; }
        .content { flex: 1; }
        .article { background: white; border-radius: 12px; padding: 40px; box-shadow: 0 2px 15px rgba(0,0,0,0.08); }
        .article-title { font-size: 32px; margin-bottom: 20px; color: #333; line-height: 1.4; }
        .article-meta { color: #888; font-size: 14px; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
        .article-content { font-size: 16px; line-height: 1.8; overflow: hidden; }
        .article-content h1 { font-size: 28px; margin: 30px 0 15px; color: #333; }
        .article-content h2 { font-size: 24px; margin: 25px 0 15px; color: #333; }
        .article-content h3 { font-size: 20px; margin: 20px 0 10px; color: #333; }
        .article-content p { margin-bottom: 15px; }
        .article-content ul, .article-content ol { margin: 15px 0; padding-left: 30px; }
        .article-content li { margin-bottom: 8px; }
        .article-content a { color: #667eea; text-decoration: none; }
        .article-content a:hover { text-decoration: underline; }
        .article-content .inline-code { background: #f5f5f5; padding: 2px 6px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 0.9em; }
        .article-content .code-container { margin: 15px 0; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
        .article-content .code-header { background: #333; color: white; padding: 8px 12px; display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 500; }
        .article-content .code-lang { font-weight: 500; color: #f8f8f2; }
        .article-content .copy-button { background: transparent; border: none; color: #f8f8f2; cursor: pointer; font-size: 14px; padding: 4px 8px; border-radius: 4px; transition: background 0.2s; }
        .article-content .copy-button:hover { background: rgba(255,255,255,0.1); }
        .article-content .copy-button:active { background: rgba(255,255,255,0.2); }
        .article-content .code-block { background: #2d2d2d; color: #f8f8f2; padding: 16px; font-family: 'Courier New', monospace; font-size: 14px; line-height: 1.5; overflow-x: auto; display: block; margin: 0; border-radius: 0; }
        .article-content .article-image { max-width: 100%; height: auto; border-radius: 8px; margin: 20px 0; }
        .back-link { display: inline-block; margin-top: 30px; color: #667eea; text-decoration: none; font-weight: 500; }
        .back-link:hover { text-decoration: underline; }
        .article-images { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
        .article-images h3 { font-size: 18px; margin-bottom: 15px; color: #555; }
        .image-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; }
        .image-gallery img { width: 100%; border-radius: 8px; border: 1px solid #ddd; cursor: pointer; transition: transform 0.3s; }
        .image-gallery img:hover { transform: scale(1.05); }
        
        @media (max-width: 768px) {
            .header { padding: 30px 15px; }
            .header h1 { font-size: 24px; }
            .header p { font-size: 13px; }
            .container { padding: 20px 15px; flex-direction: column; }
            .sidebar { width: 100%; margin-bottom: 20px; }
            .sidebar h3 { font-size: 16px; margin-bottom: 12px; }
            .category-list { display: flex; gap: 8px; flex-wrap: wrap; }
            .category-list li { margin-bottom: 0; }
            .category-list li a { padding: 6px 10px; font-size: 13px; background: #f0f0f0; border-radius: 4px; }
            .article { padding: 25px; border-radius: 8px; }
            .article-title { font-size: 24px; }
            .article-meta { font-size: 12px; margin-bottom: 20px; padding-bottom: 15px; }
            .article-content { font-size: 15px; }
            .article-content h1 { font-size: 24px; margin: 25px 0 12px; }
            .article-content h2 { font-size: 20px; margin: 20px 0 12px; }
            .article-content h3 { font-size: 18px; margin: 15px 0 8px; }
            .article-content .code-block { padding: 15px; font-size: 13px; overflow-x: scroll; }
            .article-content ul, .article-content ol { padding-left: 20px; }
            .article-images h3 { font-size: 16px; }
            .image-gallery { grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 10px; }
        }
        
        @media (max-width: 480px) {
            .header { padding: 25px 12px; }
            .header h1 { font-size: 20px; }
            .article { padding: 20px 15px; }
            .article-title { font-size: 20px; line-height: 1.3; }
            .article-content { font-size: 14px; }
            .article-content h1 { font-size: 20px; }
            .article-content h2 { font-size: 18px; }
            .article-content h3 { font-size: 16px; }
            .article-content .code-block { padding: 12px; font-size: 12px; }
            .image-gallery { grid-template-columns: repeat(2, 1fr); gap: 8px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo SITE_NAME; ?></h1>
        <p><?php echo SITE_DESCRIPTION; ?></p>
    </div>
    
    <div class="container">
        <div class="sidebar">
            <h3>分类</h3>
            <ul class="category-list">
                <li><a href="index.php">所有分类</a></li>
                <?php $categories = $articleManager->getAllCategories(); ?>
                <?php if (!empty($categories)): ?>
                    <?php foreach ($categories as $category): ?>
                        <li><a href="?category=<?php echo urlencode($category); ?>"><?php echo htmlspecialchars($category); ?></a></li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>暂无分类</li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="content">
            <article class="article">
                <h1 class="article-title"><?php echo htmlspecialchars($article['title']); ?></h1>
                <div class="article-meta">
                    发布于 <?php echo $article['created_date']; ?>
                    <?php if ($article['modified'] !== $article['created']): ?>
                        · 最后更新于 <?php echo $article['modified_date']; ?>
                    <?php endif; ?>
                </div>
                <div class="article-content">
                    <?php echo parseMarkdown($article['content']); ?>
                </div>
                
                <?php if (!empty($imageFiles)): ?>
                    <div class="article-images">
                        <h3>文章图片</h3>
                        <div class="image-gallery">
                            <?php foreach ($imageFiles as $image): ?>
                                <img src="image.php?title=<?php echo urlencode($article['filename']); ?>&ext=<?php echo pathinfo($image, PATHINFO_EXTENSION); ?>" 
                                     alt="<?php echo htmlspecialchars($article['title']); ?>"
                                     onclick="window.open(this.src)">
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </article>
            
            <a href="index.php" class="back-link">&larr; 返回文章列表</a>
        </div>
    </div>
    
    <script>
        function copyCode(button) {
            try {
                // 找到代码块
                const codeContainer = button.closest('.code-container');
                if (!codeContainer) {
                    console.error('找不到代码容器');
                    button.textContent = '✗';
                    setTimeout(() => {
                        button.textContent = '📋';
                    }, 2000);
                    return;
                }
                
                const codeBlock = codeContainer.querySelector('.code-block');
                if (!codeBlock) {
                    console.error('找不到代码块');
                    button.textContent = '✗';
                    setTimeout(() => {
                        button.textContent = '📋';
                    }, 2000);
                    return;
                }
                
                const text = codeBlock.textContent;
                
                // 尝试使用 Clipboard API
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(() => {
                        showCopySuccess(button);
                    }).catch(err => {
                        console.error('Clipboard API 失败:', err);
                        fallbackCopyTextToClipboard(text, button);
                    });
                } else {
                    // 备用方案
                    fallbackCopyTextToClipboard(text, button);
                }
            } catch (err) {
                console.error('复制函数错误:', err);
                button.textContent = '✗';
                setTimeout(() => {
                    button.textContent = '📋';
                }, 2000);
            }
        }
        
        function fallbackCopyTextToClipboard(text, button) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            
            // 避免滚动到元素
            textArea.style.top = '0';
            textArea.style.left = '0';
            textArea.style.position = 'fixed';
            textArea.style.width = '2em';
            textArea.style.height = '2em';
            textArea.style.padding = '0';
            textArea.style.border = 'none';
            textArea.style.outline = 'none';
            textArea.style.boxShadow = 'none';
            textArea.style.background = 'transparent';
            
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    showCopySuccess(button);
                } else {
                    throw new Error('execCommand 失败');
                }
            } catch (err) {
                console.error('备用方案失败:', err);
                button.textContent = '✗';
                setTimeout(() => {
                    button.textContent = '📋';
                }, 2000);
            } finally {
                document.body.removeChild(textArea);
            }
        }
        
        function showCopySuccess(button) {
            const originalText = button.textContent;
            button.textContent = '✓';
            setTimeout(() => {
                button.textContent = originalText;
            }, 2000);
        }
    </script>
</body>
</html>
