<?php
require_once __DIR__ . '/config.php';
require_editor();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/collections.php');
}

$id      = (int)($_POST['id']   ?? 0);
$coll_id = (int)($_POST['coll'] ?? 0);

if ($id) {
    // Verify item exists
    $item = $pdo->prepare('SELECT image_path, collection_id FROM items WHERE id = ?');
    $item->execute([$id]);
    $item = $item->fetch();
    if ($item) {
        $coll_id = $item['collection_id'];
        // Delete image file if present
        if ($item['image_path']) {
            $file = UPLOAD_DIR . $item['image_path'];
            if (file_exists($file)) unlink($file);
        }
        $pdo->prepare('DELETE FROM items WHERE id = ?')->execute([$id]);
        flash('success', 'Item deleted.');
    }
}

redirect(BASE_URL . '/items.php?coll=' . $coll_id);
