# Collections Manager

A lightweight, self-hosted web application for managing personal collections — built with PHP and MySQL. No frameworks, no dependencies, just upload and run.

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)

---

## Features

- **Login-gated** — nothing is visible without authentication
- **Admin panel**
  - Create and delete users, toggle admin role
  - Create and delete collections with custom names and descriptions
  - Define custom fields per collection (text, number, or boolean)
  - Toggle image support per collection
- **Items**
  - Add, edit and delete items in any collection
  - Sortable, searchable table with pagination
  - Upload an image per item (JPEG, PNG, GIF, WebP)
- **Tags**
  - Create tags with an optional image
  - Assign multiple tags to each item
  - Search tags and browse all items with a given tag
  - Filter item lists by tag
- **Responsive** — works on desktop and mobile
- **Warm editorial design** — Playfair Display + Source Sans 3, paper-toned palette

---

## Requirements

- PHP 8.0 or higher
- MySQL 5.7 or higher (or MariaDB equivalent)
- A web server with mod_rewrite (Apache) or equivalent
- Writable `uploads/` directory

---

## Installation

### 1. Clone or download

```bash
git clone https://github.com/yourusername/collections.git
```

### 2. Create the database

In phpMyAdmin (or via CLI), create a new database with `utf8mb4_unicode_ci` collation, then run the install script:

```
phpMyAdmin → select your database → SQL tab → paste contents of install.sql → execute
```

This creates all tables and inserts a default admin user (`admin` / `admin`).

### 3. Configure

Copy the example config and fill in your values:

```bash
cp config.example.php config.php
```

Edit `config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');
define('BASE_URL', 'https://yourdomain.com/collections');
```

### 4. Create the uploads folder

```bash
mkdir uploads
chmod 755 uploads
```

### 5. Upload via FTP

Upload all files to your web server. `config.php` is excluded from Git — never commit it.

### 6. Log in and change the password

Go to your site URL, log in with `admin` / `admin`, and immediately create a new admin user with a strong password, then delete the default one.

---

## File structure

```
collections/
├── config.example.php   # Copy to config.php and fill in your credentials
├── install.sql          # Database schema + default admin user
├── .htaccess            # Blocks direct access to config.php
├── index.php            # Login page
├── logout.php
├── layout.php           # Shared header/footer
├── style.css
├── collections.php      # Collection overview
├── items.php            # Item list with search, sort, filter
├── item_edit.php        # Add / edit item
├── item_delete.php      # Delete handler
├── admin.php            # Admin panel
├── tags.php             # Tag management
└── uploads/             # Image uploads (not in Git)
```

---

## Security notes

- `config.php` is blocked by `.htaccess` and excluded from Git via `.gitignore`
- `uploads/` is excluded from Git — back it up separately
- PHP execution is disabled in the `uploads/` folder via `.htaccess`
- Passwords are hashed with `password_hash()` (bcrypt)
- All database queries use PDO prepared statements
- All output is escaped with `htmlspecialchars()`

---

## License

MIT
