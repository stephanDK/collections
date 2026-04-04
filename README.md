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
  - Manage global tags (visible across all collections)
- **Items**
  - Add, edit and delete items in any collection
  - Sortable, searchable table with pagination
  - Click any row to see a full item view
  - Upload an image per item (JPEG, PNG, GIF, WebP)
  - "Save & Add Another" for rapid data entry
- **Tags — two scopes**
  - **Global tags** (admin only) — apply across all collections
  - **Collection tags** (all users) — specific to one collection
  - Tags can have an optional image
  - Click a tag to see all items with that tag
- **Responsive** — works on desktop and mobile

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

In phpMyAdmin (or via CLI):
1. Create a new database — name it whatever you like, e.g. `collections_db`
2. Set collation to `utf8mb4_unicode_ci`
3. Select the new database, go to the **SQL tab**, paste the contents of `install.sql` and execute

### 3. Configure

Copy the example config and fill in your values:

```bash
cp config.example.php config.php
```

Edit `config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');  // the name you chose above
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');
define('BASE_URL', 'https://yourdomain.com/collections');  // no trailing slash
```

### 4. Create the uploads folder

```bash
mkdir uploads
chmod 755 uploads
```

### 5. Upload via FTP

Upload all files to your web server. `config.php` and `setup.php` are excluded from Git — see Security notes below.

### 6. Create the admin user

Go to `https://yourdomain.com/collections/setup.php` in your browser.

This generates a secure password hash on your own server and inserts the admin user into the database. You will see a confirmation message when it succeeds.

> ⚠️ **Delete `setup.php` from the server immediately after** — it should never be left accessible.

### 7. Log in and change the password

Go to `https://yourdomain.com/collections/` and log in with `admin` / `admin`.

Immediately go to **Admin → Users**, create a new admin user with a strong password, and delete the default `admin` account.

---

## File structure

```
collections/
├── config.example.php     # Copy to config.php and fill in your credentials
├── install.sql            # Database schema
├── setup.php              # Run once to create admin user — DELETE AFTER USE
├── .htaccess              # Blocks direct access to config.php
├── index.php              # Login page
├── logout.php
├── layout.php             # Shared header/footer
├── style.css
├── collections.php        # Collection overview
├── items.php              # Item list with search, sort, filter
├── item_edit.php          # Add / edit item
├── item_view.php          # Single item view
├── item_delete.php        # Delete handler
├── collection_tags.php    # Tag management per collection
├── tag_view.php           # Single tag view
├── admin.php              # Admin panel (users, collections, global tags)
└── uploads/               # Image uploads (not in Git)
```

---

## Security notes

- `config.php` is blocked by `.htaccess` and excluded from Git via `.gitignore`
- `setup.php` and `reset.php` are excluded from Git — never leave them on the server after use
- `uploads/` is excluded from Git — back it up separately
- PHP execution is disabled in the `uploads/` folder via `.htaccess`
- Passwords are hashed with `password_hash()` (bcrypt)
- All database queries use PDO prepared statements
- All output is escaped with `htmlspecialchars()`

---

## License

MIT
