# MiniBlog - Database-Free Blog System

A simple, secure blog system that doesn't require a database. All articles are stored as Markdown files.

## Features

- **Database-Free**: All content is stored as Markdown files in the `documents/` directory
- **Smart Login**: First access to admin panel automatically redirects to admin setup page, no need to manually delete config files
- **Complete Admin Panel**: Article management, site settings, category management, backup/restore
- **Advanced Backup System**: Supports recursive backup of all articles (including subdirectories), supports uploading local backup files for restoration
- **Import/Export**: Single article import/export functionality
- **Image Support**: Upload and display article images
- **Search**: Full-text search for all articles
- **Category System**: Article category management and filtering
- **Responsive Design**: Adapts to all devices (PC, tablet, mobile)
- **Security**: Built-in protection against SQL injection, XSS, directory traversal, etc.

## Installation

1. Upload all files to your web server
2. Ensure the following directories are writable:
   - `documents/`
   - `backups/`
   - `includes/`
3. Access `http://yourdomain.com/admin/` or `http://yourdomain.com/admin/login.php` in your browser
4. First access will automatically redirect to admin setup page, set your username and password
5. Delete `install.php` after installation (recommended)

## Directory Structure

```
miniblog/
├── admin/              # Admin panel
│   ├── login.php       # Admin login page
│   ├── logout.php      # Admin logout
│   ├── dashboard.php   # Article management
│   ├── settings.php    # Site settings & category management
│   ├── change_password.php # Change password
│   └── backup.php      # Backup/restore & import/export
├── documents/          # Article storage (Markdown files)
├── backups/            # Backup files
├── includes/           # Core classes
│   ├── Security.php    # Security tools
│   ├── ArticleManager.php
│   ├── BackupManager.php
│   └── admin_credentials.php # Admin credentials
├── config.php          # Main configuration file
├── index.php           # Homepage
├── article.php         # Single article view
├── image.php           # Image handler
├── install.php         # Installation script
└── .htaccess           # Apache configuration
```

## Usage

### Creating Articles

1. Log in to the admin panel at `http://yourdomain.com/admin/login.php`
2. Click "New Article"
3. Enter title and content (Markdown supported)
4. Select category (optional)
5. Click "Save Article"

### Managing Articles

- **Edit**: Click "Edit" on any article in the list
- **Delete**: Click "Delete" to remove articles
- **Upload Images**: When editing, images will be saved as `filename.jpg/png/gif`
- **Category Filtering**: Use category navigation to filter articles

### Site Settings

1. Log in to the admin panel
2. Click "Site Settings"
3. Modify site name and description
4. Manage article categories (add/delete categories)
5. Click "Save Settings"

### Changing Password

1. Log in to the admin panel
2. Click the account dropdown menu in the top right
3. Select "Change Password"
4. Enter current password and new password
5. Click "Save Changes"

### Import/Export

- **Export**: Select an article and export as Markdown file
- **Import**: Upload Markdown file to create new article, category selection available

### Backup/Restore

- **Create Backup**: Create ZIP archive of all articles (including subdirectories)
- **Download Backup**: Download backup files to local computer
- **Restore Backup**: Restore from previous backup (will overwrite current content)
- **Upload Restore**: Upload local backup files for restoration
- Automatically keeps up to 10 backups

## Security Features

- **CSRF Protection**: All forms include CSRF tokens
- **Rate Limiting**: Login attempts are rate limited
- **Session Timeout**: Admin sessions expire after 30 minutes
- **Input Sanitization**: All user input is sanitized
- **File Validation**: Uploaded files are validated for type and content
- **Directory Traversal Protection**: File access restricted to allowed directories
- **XSS Protection**: Output properly escaped
- **Security Logging**: Security events logged

## Resetting Admin Credentials

If you need to reset admin credentials:

1. Open `includes/admin_credentials.php`
2. Delete all content in the file
3. Access `http://yourdomain.com/admin/` again
4. System will automatically guide you to reset admin credentials

## System Requirements

- PHP 8.0 or higher
- Apache Web Server with mod_rewrite enabled
- Write permissions for `documents/`, `backups/`, and `includes/` directories

## License

Free to use and modify.