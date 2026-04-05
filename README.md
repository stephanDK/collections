# Collections Manager

A lightweight, self-hosted web application for managing personal collections — built with PHP and MySQL. No frameworks, no dependencies, just upload and run.

![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)

---

## Features

- **Login-gated** — nothing is visible without authentication
- **Admin panel**
  - Create and delete users, toggle admin/guest role
  - Edit users (username, password, role)
  - Create and delete collections with custom names and descriptions
  - Define custom fields per collection (text, number, or boolean)
  - Toggle image support per collection
  - Manage global tags (visible across all collections)
- **Items**
  - Add, edit and delete items in any collection
  - Sortable, searchable table with pagination
  - Configurable items per page (10, 50, 100, or all)
  - Click any row to see a full item view with prev/next navigation
  - Upload an image per item (JPEG, PNG, GIF, WebP)
  - "Save & Add Another" for rapid data entry
- **Bulk Edit (CSV import/export)**
  - Download current items as a semicolon-separated CSV (opens directly in Danish Excel)
  - Upload edited CSV to create or update items in bulk
  - Preview before committing — shows new, updated, unchanged and skipped rows
  - Tags can be included as comma-separated values in the CSV
  - Unknown tags are auto-created on import
- **Tags — two scopes**
  - **Global tags** (admin only) — apply across all collections
  - **Collection tags** (all users) — specific to one collection
  - Tags can have an optional image
  - Click a tag to see all items with that tag in a visual grid
- **Guest access**
  - Admin can create guest users with read-only access
  - Guests see all collections, items and tags but cannot create, edit or delete anything
  - All write operations are blocked server-side for guests
- **Responsive** — works on desktop and mobile
- **Warm editorial design** — Playfair Display + Source Sans 3, paper-toned palette

---

## Requirements

- PHP 8.0 or higher
- MySQL 5.7 or higher (or MariaDB equivalent)
- A web server with Apache (mod_rewrite or equivalent)
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

This creates all tables. Note: does **not** create the admin user — see step 6.

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

Upload all files to your web server. `config.php` and `setup.php` are excluded from Git — never commit them.

### 6. Create the admin user

Go to `https://yourdomain.com/collections/setup.php` in your browser.

This generates a secure bcrypt password hash on your own server and inserts the admin user.

> ⚠️ **Delete `setup.php` from the server immediately after** — it must never be left accessible.

### 7. Log in and change the password

Go to your site and log in with `admin` / `admin`.

Go to **Admin → Users**, create a new admin user with a strong password, and delete the default `admin` account.

---

## Bulk Edit (CSV)

Each collection has a **⇅ Bulk Edit** button that opens a two-step import/export page.

**Download:** exports all items as a `;`-separated CSV with a UTF-8 BOM — opens directly in Danish/European Excel without any import wizard.

**Upload:** parse the CSV and shows a preview before saving:
- Rows with an empty `id` column are created as new items
- Rows with an existing `id` are updated if changed, skipped if identical
- Tags are written as comma-separated names in the `tags` column
- Unknown tags are automatically created as collection tags

---

## Guest Access

Admin can create guest users in **Admin → New User** by checking "Guest (read-only)".

Guests can:
- Browse all collections, items and tags
- See item details and tag views
- Export CSV

Guests cannot:
- Create, edit or delete items
- Create, edit or delete tags
- Access the admin panel or bulk import

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
├── layout.php             # Shared header/footer + lightbox
├── style.css
├── collections.php        # Collection overview
├── items.php              # Item list with search, sort, filter, pagination
├── item_edit.php          # Add / edit item
├── item_view.php          # Single item view with prev/next
├── item_delete.php        # Delete handler
├── item_export.php        # CSV export
├── item_import.php        # CSV import with preview
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
- Guest write operations are blocked server-side, not just in the UI

---

## License

MIT
