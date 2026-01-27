# MiniBlog - Database-less Blog System

A simple, secure blog system that doesn't require a database. All articles are stored as Markdown files.

## Features

- **Database-less**: All content stored as Markdown files in the `documents/` directory
- **Secure**: Built-in protection against SQL injection, XSS, file traversal, and other attacks
- **Admin Panel**: Full CRUD operations for articles
- **Import/Export**: Single article import/export functionality
- **Backup/Restore**: Full system backup and restore
- **Image Support**: Upload and display images with articles
- **Search**: Full-text search across all articles
- **Responsive Design**: Works on all devices

## Installation

1. Upload all files to your web server
2. Ensure the following directories are writable:
   - `documents/`
   - `backups/`
   - `includes/`
3. Visit `http://yourdomain.com/install.php` in your browser
4. Set up your admin username and password
5. Delete `install.php` after installation (recommended)

## Directory Structure

```
miniblog/
├── admin/              # Admin panel
│   ├── login.php       # Admin login page
│   ├── logout.php      # Admin logout
│   ├── dashboard.php   # Article management
│   └── backup.php      # Backup/restore & import/export
├── documents/          # Article storage (Markdown files)
├── backups/            # Backup files
├── includes/           # Core classes
│   ├── Security.php    # Security utilities
│   ├── ArticleManager.php
│   └── BackupManager.php
├── assets/             # Static assets (if needed)
├── config.php          # Main configuration
├── index.php           # Homepage
├── article.php         # Single article view
├── image.php           # Image handler
├── api.php             # REST API
├── install.php         # Installation script
└── .htaccess           # Apache configuration
```

## Usage

### Creating Articles

1. Login to admin panel at `http://yourdomain.com/admin/login.php`
2. Click "New Article"
3. Enter title and content (supports Markdown)
4. Click "Save Article"

### Managing Articles

- **Edit**: Click "Edit" on any article in the list
- **Delete**: Click "Delete" to remove an article
- **Upload Images**: While editing, upload images that will be saved as `filename.jpg/png/gif`

### Import/Export

- **Export**: Select an article and export it as a Markdown file
- **Import**: Upload a Markdown file to create a new article

### Backup/Restore

- **Create Backup**: Creates a ZIP file of all articles
- **Restore**: Restore from a previous backup (overwrites current content)
- Maximum 10 backups are kept automatically

## Security Features

- **CSRF Protection**: All forms include CSRF tokens
- **Rate Limiting**: Login attempts are rate-limited
- **Session Timeout**: Admin sessions expire after 30 minutes
- **Input Sanitization**: All user input is sanitized
- **File Validation**: Uploaded files are validated for type and content
- **Path Traversal Protection**: File access is restricted to allowed directories
- **XSS Protection**: Output is properly escaped
- **Security Logging**: Security events are logged to `backups/security.log`

## Reset Admin Credentials

If you need to reset the admin credentials:

1. Open `includes/admin_credentials.php`
2. Delete all content in that file
3. Visit `http://yourdomain.com/install.php` again
4. Set up new credentials

## API Endpoints

### Public Endpoints

- `GET /api/articles` - List all articles
- `GET /api/articles?q=search` - Search articles
- `GET /api/articles/{title}` - Get single article

### Admin Endpoints (Requires authentication)

- `POST /api/admin/articles` - Create article
- `PUT /api/admin/articles/{title}` - Update article
- `DELETE /api/admin/articles/{title}` - Delete article
- `GET /api/admin/backups` - List backups
- `POST /api/admin/backups` - Create backup
- `POST /api/admin/backups/{name}` - Restore/delete backup
- `POST /api/admin/import` - Import article
- `GET /api/admin/export/{title}` - Export article

## Requirements

- PHP 8.0 or higher
- Apache web server with mod_rewrite enabled
- Write permissions for `documents/`, `backups/`, and `includes/` directories

## License

Free to use and modify.
