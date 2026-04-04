<?php
// ============================================================
// setup.php – Run once after install.sql to create admin user
// DELETE THIS FILE from the server afterwards!
// ============================================================
require_once __DIR__ . '/config.php';

// Safety check – refuse to run if admin already exists
$existing = $pdo->query("SELECT COUNT(*) FROM users WHERE username = 'admin'")->fetchColumn();
if ($existing) {
    die('<p style="font-family:monospace">Admin user already exists. Delete this file from the server.</p>');
}

$hash = password_hash('admin', PASSWORD_BCRYPT);
$pdo->prepare('INSERT INTO users (username, password_hash, is_admin) VALUES (?, ?, 1)')
    ->execute(['admin', $hash]);

echo '<p style="font-family:monospace;color:green">
    ✓ Admin user created successfully.<br><br>
    Login: <strong>admin</strong> / <strong>admin</strong><br><br>
    <strong>Delete this file from the server now!</strong><br>
    Then go to: <a href="index.php">index.php</a>
</p>';
