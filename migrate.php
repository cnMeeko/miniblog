<?php
require_once 'includes/config.php';
require_once 'includes/Security.php';
require_once 'includes/ArticleManager.php';

$articleManager = new ArticleManager(DOCUMENTS_DIR);

// 获取所有文章
$articles = $articleManager->getAllArticles();

echo "开始迁移文章到新的存储结构...\n";
echo "找到 " . count($articles) . " 篇文章\n\n";

$migratedCount = 0;
$errorCount = 0;

foreach ($articles as $article) {
    $title = $article['title'];
    $filename = $article['filename'];
    
    echo "处理文章: {$title}...\n";
    
    // 检查是否已经是新的存储方式
    $articleDir = DOCUMENTS_DIR . '/' . $filename;
    $articleFilePath = $articleDir . '/' . $filename . '.md';
    
    if (is_dir($articleDir) && file_exists($articleFilePath)) {
        echo "  文章已经在新的存储结构中，跳过\n";
        continue;
    }
    
    // 检查旧的存储方式
    $oldFilePath = DOCUMENTS_DIR . '/' . $filename . '.md';
    if (!file_exists($oldFilePath)) {
        echo "  错误: 文章文件不存在\n";
        $errorCount++;
        continue;
    }
    
    // 创建文章目录
    if (!is_dir($articleDir)) {
        if (mkdir($articleDir, 0755, true)) {
            echo "  创建文章目录成功\n";
        } else {
            echo "  错误: 无法创建文章目录\n";
            $errorCount++;
            continue;
        }
    }
    
    // 移动文章文件
    $newFilePath = $articleDir . '/' . $filename . '.md';
    if (rename($oldFilePath, $newFilePath)) {
        echo "  移动文章文件成功\n";
    } else {
        echo "  错误: 无法移动文章文件\n";
        $errorCount++;
        continue;
    }
    
    // 移动相关图片文件
    $oldImages = glob(DOCUMENTS_DIR . '/' . $filename . '.*');
    foreach ($oldImages as $imagePath) {
        $ext = pathinfo($imagePath, PATHINFO_EXTENSION);
        if ($ext !== 'md') {
            $newImagePath = $articleDir . '/' . basename($imagePath);
            if (rename($imagePath, $newImagePath)) {
                echo "  移动图片文件: " . basename($imagePath) . " 成功\n";
            } else {
                echo "  错误: 无法移动图片文件: " . basename($imagePath) . "\n";
            }
        }
    }
    
    $migratedCount++;
    echo "  迁移完成\n\n";
}

echo "迁移完成!\n";
echo "成功迁移: {$migratedCount} 篇文章\n";
echo "错误: {$errorCount} 篇文章\n";

if ($migratedCount > 0) {
    echo "\n请删除此迁移脚本，因为它已经完成了任务。\n";
} else {
    echo "\n没有需要迁移的文章，所有文章已经在新的存储结构中。\n";
}
?>