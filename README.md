# MiniBlog - 无数据库博客系统

一个简单、安全的博客系统，不需要数据库。所有文章都以 Markdown 文件形式存储。

## 功能特性

- **无数据库**：所有内容以 Markdown 文件形式存储在 `documents/` 目录中
- **安全**：内置防护 SQL 注入、XSS、文件遍历等攻击
- **管理面板**：完整的文章增删改查功能
- **导入/导出**：单篇文章导入/导出功能
- **备份/恢复**：完整的系统备份和恢复
- **图片支持**：上传并显示文章图片
- **搜索**：全文搜索所有文章
- **响应式设计**：适配所有设备

## 安装

1. 将所有文件上传到您的 Web 服务器
2. 确保以下目录可写：
   - `documents/`
   - `backups/`
   - `includes/`
3. 在浏览器中访问 `http://yourdomain.com/install.php`
4. 设置管理员用户名和密码
5. 安装完成后删除 `install.php`（推荐）

## 目录结构

```
miniblog/
├── admin/              # 管理面板
│   ├── login.php       # 管理员登录页面
│   ├── logout.php      # 管理员登出
│   ├── dashboard.php   # 文章管理
│   └── backup.php      # 备份/恢复 & 导入/导出
├── documents/          # 文章存储（Markdown 文件）
├── backups/            # 备份文件
├── includes/           # 核心类
│   ├── Security.php    # 安全工具
│   ├── ArticleManager.php
│   └── BackupManager.php
├── assets/             # 静态资源（如需要）
├── config.php          # 主配置文件
├── index.php           # 首页
├── article.php         # 单篇文章视图
├── image.php           # 图片处理器
├── api.php             # REST API
├── install.php         # 安装脚本
└── .htaccess           # Apache 配置
```

## 使用方法

### 创建文章

1. 在 `http://yourdomain.com/admin/login.php` 登录管理面板
2. 点击"新建文章"
3. 输入标题和内容（支持 Markdown）
4. 点击"保存文章"

### 管理文章

- **编辑**：在列表中点击任意文章的"编辑"
- **删除**：点击"删除"移除文章
- **上传图片**：编辑时，上传图片将保存为 `filename.jpg/png/gif`

### 导入/导出

- **导出**：选择一篇文章并导出为 Markdown 文件
- **导入**：上传 Markdown 文件创建新文章

### 备份/恢复

- **创建备份**：创建所有文章的 ZIP 压缩包
- **恢复**：从之前的备份恢复（将覆盖当前内容）
- 自动保留最多 10 个备份

## 安全特性

- **CSRF 防护**：所有表单都包含 CSRF 令牌
- **速率限制**：登录尝试受到速率限制
- **会话超时**：管理员会话 30 分钟后过期
- **输入清理**：所有用户输入都经过清理
- **文件验证**：上传的文件会验证类型和内容
- **路径遍历防护**：文件访问限制在允许的目录内
- **XSS 防护**：输出正确转义
- **安全日志**：安全事件记录到 `backups/security.log`

## 重置管理员凭据

如果您需要重置管理员凭据：

1. 打开 `includes/admin_credentials.php`
2. 删除该文件中的所有内容
3. 再次访问 `http://yourdomain.com/install.php`
4. 设置新的凭据

## API 接口

### 公开接口

- `GET /api/articles` - 列出所有文章
- `GET /api/articles?q=search` - 搜索文章
- `GET /api/articles/{title}` - 获取单篇文章

### 管理员接口（需要认证）

- `POST /api/admin/articles` - 创建文章
- `PUT /api/admin/articles/{title}` - 更新文章
- `DELETE /api/admin/articles/{title}` - 删除文章
- `GET /api/admin/backups` - 列出备份
- `POST /api/admin/backups` - 创建备份
- `POST /api/admin/backups/{name}` - 恢复/删除备份
- `POST /api/admin/import` - 导入文章
- `GET /api/admin/export/{title}` - 导出文章

## 系统要求

- PHP 8.0 或更高版本
- Apache Web 服务器，启用 mod_rewrite
- `documents/`、`backups/` 和 `includes/` 目录的写入权限

## 部分截图
<img width="1919" height="911" alt="主页" src="https://github.com/user-attachments/assets/ef429cec-22cb-4cc3-a47f-3436033110ed" />
<img width="1914" height="903" alt="创建账号" src="https://github.com/user-attachments/assets/1fa42ddd-8225-4f1c-933f-d071251b584f" />
<img width="1916" height="900" alt="后台" src="https://github.com/user-attachments/assets/dd0465d6-03d1-413f-be32-807d6268933d" />
<img width="1915" height="905" alt="备份恢复" src="https://github.com/user-attachments/assets/c66d4fc5-90eb-4a40-b2d5-78bff58b2d6a" />


## 许可证

可自由使用和修改。
