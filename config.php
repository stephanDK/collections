<?php
// ============================================================
// config.php  –  Edit these values before uploading
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'collections_db');
define('DB_USER', 'your_db_username');   // <-- change
define('DB_PASS', 'your_db_password');   // <-- change
define('DB_CHARSET', 'utf8mb4');

// Base URL of the site (no trailing slash)
define('BASE_URL', 'https://yourdomain.dk/collections');  // <-- change

// Folder where uploaded images are stored (must be writable)
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', BASE_URL . '/uploads/');

// Max upload size in bytes (default 5 MB)
define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);

// Session name
define('SESSION_NAME', 'collections_sess');

// ============================================================
// Bootstrap – do not edit below
// ============================================================
session_name(SESSION_NAME);
session_start();

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die('<p style="font-family:monospace;color:red">Database connection failed: '
        . htmlspecialchars($e->getMessage()) . '</p>');
}

// ---- helpers ------------------------------------------------

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function is_admin(): bool {
    return !empty($_SESSION['is_admin']);
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        header('Location: ' . BASE_URL . '/collections.php');
        exit;
    }
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function get_flash(): ?array {
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function upload_image(string $file_key, string $subfolder = ''): ?string {
    if (empty($_FILES[$file_key]['tmp_name'])) return null;
    $f = $_FILES[$file_key];
    if ($f['error'] !== UPLOAD_ERR_OK) return null;
    if ($f['size'] > MAX_UPLOAD_BYTES) return null;
    $mime = mime_content_type($f['tmp_name']);
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime, $allowed)) return null;
    $ext  = pathinfo($f['name'], PATHINFO_EXTENSION);
    $name = uniqid('img_', true) . '.' . strtolower($ext);
    $dir  = UPLOAD_DIR . ($subfolder ? $subfolder . '/' : '');
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    move_uploaded_file($f['tmp_name'], $dir . $name);
    return ($subfolder ? $subfolder . '/' : '') . $name;
}
