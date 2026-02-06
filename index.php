<?php
require_once 'includes/config.php';
require_once 'includes/Security.php';
require_once 'includes/ArticleManager.php';

$articleManager = new ArticleManager(DOCUMENTS_DIR);
$adminUrl = Security::isAdminLoggedIn() ? 'admin/dashboard.php' : 'admin/login.php';

// 获取所有分类
$categories = $articleManager->getAllCategories();

$query = Security::sanitizeInput(isset($_GET['q']) ? $_GET['q'] : '');
$category = Security::sanitizeInput(isset($_GET['category']) ? $_GET['category'] : '');
$page = max(1, intval(isset($_GET['page']) ? $_GET['page'] : 1));
$perPage = 10;

if ($query) {
    $articles = $articleManager->searchArticles($query);
} elseif ($category) {
    $articles = $articleManager->getArticlesByCategory($category);
} else {
    $articles = $articleManager->getAllArticles();
}

$totalArticles = count($articles);
$totalPages = ceil($totalArticles / $perPage);
$offset = ($page - 1) * $perPage;
$articles = array_slice($articles, $offset, $perPage);

function parseMarkdown($text) {
    $text = preg_replace('/^### (.*$)/m', '<h3>$1</h3>', $text);
    $text = preg_replace('/^## (.*$)/m', '<h2>$1</h2>', $text);
    $text = preg_replace('/^# (.*$)/m', '<h1>$1</h1>', $text);
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
    $text = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1" style="max-width:100%;">', $text);
    $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $text);
    $text = preg_replace('/\n\n/', '</p><p>', $text);
    $text = '<p>' . $text . '</p>';
    $text = str_replace('<p></p>', '', $text);
    return $text;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $query ? "搜索: {$query} - " : ''; ?><?php echo SITE_NAME; ?></title>
    <meta name="description" content="<?php echo SITE_DESCRIPTION; ?>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8f9fa; line-height: 1.6; color: #333; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 20px; text-align: center; width: 100%; }
        .header h1 { font-size: 36px; margin-bottom: 10px; }
        .header p { font-size: 16px; opacity: 0.9; margin-bottom: 15px; }
        .container { width: 100%; max-width: 1200px; margin: 0 auto; padding: 40px 20px; display: flex; gap: 30px; }
        .sidebar { width: 200px; flex-shrink: 0; }
        .sidebar h3 { font-size: 18px; margin-bottom: 15px; color: #333; }
        .category-list { list-style: none; }
        .category-list li { margin-bottom: 10px; }
        .category-list li a { color: #666; text-decoration: none; transition: color 0.3s; display: block; padding: 8px 12px; border-radius: 6px; }
        .category-list li a:hover { color: #667eea; background: #f0f0f0; }
        .content { flex: 1; }
        .search-box { margin-bottom: 30px; }
        .search-box input { width: 100%; padding: 15px 20px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 16px; transition: border-color 0.3s; }
        .search-box input:focus { outline: none; border-color: #667eea; }
        .article-card { background: white; border-radius: 12px; padding: 30px; margin-bottom: 25px; box-shadow: 0 2px 15px rgba(0,0,0,0.08); transition: transform 0.3s, box-shadow 0.3s; overflow: hidden; }
        .article-card:hover { transform: translateY(-3px); box-shadow: 0 5px 25px rgba(0,0,0,0.12); }
        .article-card h2 { font-size: 24px; margin-bottom: 15px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .article-card h2 a { color: #333; text-decoration: none; transition: color 0.3s; }
        .article-card h2 a:hover { color: #667eea; }
        .article-meta { color: #888; font-size: 14px; margin-bottom: 15px; }
        .article-excerpt { color: #666; font-size: 15px; line-height: 1.8; margin-bottom: 20px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; }
        .read-more { display: inline-block; color: #667eea; text-decoration: none; font-weight: 500; transition: color 0.3s; }
        .read-more:hover { color: #5568d3; }
        .pagination { display: flex; justify-content: center; gap: 10px; margin-top: 40px; }
        .pagination a, .pagination span { padding: 10px 20px; border-radius: 6px; text-decoration: none; }
        .pagination a { background: white; color: #667eea; border: 1px solid #667eea; transition: all 0.3s; }
        .pagination a:hover { background: #667eea; color: white; }
        .pagination span.current { background: #667eea; color: white; }
        .pagination span.disabled { color: #ccc; }
        .empty-state { text-align: center; padding: 60px 20px; color: #999; }
        .empty-state h3 { font-size: 20px; margin-bottom: 10px; color: #666; }
        .footer { text-align: center; padding: 30px; color: #888; font-size: 14px; }
        .footer a { color: #667eea; text-decoration: none; }
        .footer a:hover { text-decoration: underline; }
        
        @media (max-width: 768px) {
            .header { padding: 30px 15px; width: 100%; box-sizing: border-box; }
            .header h1 { font-size: 24px; margin-bottom: 8px; }
            .header p { font-size: 13px; margin-bottom: 0; }
            .container { padding: 20px 15px; flex-direction: column; }
            .sidebar { width: 100%; margin-bottom: 20px; }
            .sidebar h3 { font-size: 16px; margin-bottom: 12px; }
            .category-list { display: flex; gap: 8px; flex-wrap: wrap; }
            .category-list li { margin-bottom: 0; }
            .category-list li a { padding: 6px 10px; font-size: 13px; background: #f0f0f0; border-radius: 4px; }
            .search-box { margin-bottom: 20px; }
            .search-box input { padding: 12px 15px; font-size: 14px; width: 100%; box-sizing: border-box; }
            .article-card { padding: 20px; border-radius: 8px; margin-bottom: 15px; }
            .article-card h2 { font-size: 18px; margin-bottom: 12px; }
            .article-meta { font-size: 12px; margin-bottom: 15px; }
            .article-excerpt { font-size: 14px; line-height: 1.6; }
            .pagination { gap: 5px; flex-wrap: wrap; margin-top: 20px; }
            .pagination a, .pagination span { padding: 8px 12px; font-size: 14px; }
            .footer { padding: 20px 15px; font-size: 12px; text-align: center; }
        }
        
        @media (max-width: 480px) {
            .header { padding: 25px 12px; }
            .header h1 { font-size: 22px; }
            .header p { font-size: 12px; }
            .container { padding: 15px 12px; }
            .sidebar h3 { font-size: 15px; }
            .category-list li a { padding: 5px 8px; font-size: 12px; }
            .search-box input { padding: 10px 12px; font-size: 13px; }
            .article-card { padding: 15px; }
            .article-card h2 { font-size: 17px; }
            .article-meta { font-size: 11px; }
            .article-excerpt { font-size: 13px; }
            .pagination a, .pagination span { padding: 6px 10px; font-size: 13px; }
            .footer { padding: 15px 12px; font-size: 11px; }
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
        <div class="search-box">
            <form method="GET">
                <input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" placeholder="搜索文章..." autocomplete="off">
            </form>
        </div>
        
        <?php if (empty($articles)): ?>
            <div class="empty-state">
                <h3><?php echo $query ? '没有找到相关文章' : '暂无文章'; ?></h3>
                <p><?php echo $query ? '尝试使用其他关键词搜索' : '敬请期待更多精彩内容'; ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($articles as $article): ?>
                <div class="article-card">
                    <h2><a href="article.php?title=<?php echo urlencode($article['filename']); ?>"><?php echo htmlspecialchars($article['title']); ?></a></h2>
                    <div class="article-meta">
                        发布于 <?php echo $article['created_date']; ?>
                        <?php if ($article['modified'] !== $article['created']): ?>
                            · 更新于 <?php echo $article['modified_date']; ?>
                        <?php endif; ?>
                    </div>
                    <div class="article-excerpt">
                        <?php echo htmlspecialchars($article['excerpt']); ?>
                    </div>
                    <a href="article.php?title=<?php echo urlencode($article['filename']); ?>" class="read-more">阅读全文 &rarr;</a>
                </div>
            <?php endforeach; ?>
            
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $query ? '&q=' . urlencode($query) : ''; ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?>">上一页</a>
                    <?php else: ?>
                        <span class="disabled">上一页</span>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    if ($startPage > 1) {
                        $params = '';
                        if ($query) $params .= '&q=' . urlencode($query);
                        if ($category) $params .= '&category=' . urlencode($category);
                        echo '<a href="?page=1' . $params . '">1</a>';
                        if ($startPage > 2) echo '<span>...</span>';
                    }
                    
                    for ($i = $startPage; $i <= $endPage; $i++) {
                        if ($i == $page) {
                            echo '<span class="current">' . $i . '</span>';
                        } else {
                            $params = '';
                            if ($query) $params .= '&q=' . urlencode($query);
                            if ($category) $params .= '&category=' . urlencode($category);
                            echo '<a href="?page=' . $i . $params . '">' . $i . '</a>';
                        }
                    }
                    
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) echo '<span>...</span>';
                        $params = '';
                        if ($query) $params .= '&q=' . urlencode($query);
                        if ($category) $params .= '&category=' . urlencode($category);
                        echo '<a href="?page=' . $totalPages . $params . '">' . $totalPages . '</a>';
                    }
                    ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $query ? '&q=' . urlencode($query) : ''; ?><?php echo $category ? '&category=' . urlencode($category) : ''; ?>">下一页</a>
                    <?php else: ?>
                        <span class="disabled">下一页</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    </div>
    
    <div class="footer">
        <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> · <a href="<?php echo $adminUrl; ?>">管理后台</a></p>
    </div>
</body>
</html>
