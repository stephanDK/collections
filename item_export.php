<?php
require_once __DIR__ . '/config.php';
require_login();

$coll_id = (int)($_GET['coll'] ?? 0);
if (!$coll_id) redirect(BASE_URL . '/collections.php');

$coll = $pdo->prepare('SELECT * FROM collections WHERE id=?');
$coll->execute([$coll_id]);
$coll = $coll->fetch();
if (!$coll) redirect(BASE_URL . '/collections.php');

// Fields
$fields_stmt = $pdo->prepare('SELECT * FROM collection_fields WHERE collection_id=? ORDER BY sort_order');
$fields_stmt->execute([$coll_id]);
$fields = $fields_stmt->fetchAll();

// Items
$items_stmt = $pdo->prepare('SELECT id FROM items WHERE collection_id=? ORDER BY id');
$items_stmt->execute([$coll_id]);
$items = $items_stmt->fetchAll();

// Send CSV headers
$filename = preg_replace('/[^a-z0-9_-]/i', '_', $coll['name']) . '_export.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// UTF-8 BOM so Excel opens it correctly
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Header row — sep= hint for Excel, then column headers
// Write sep= as first line so Excel auto-detects semicolon
fwrite($out, "sep=;\n");

$header = ['id'];
foreach ($fields as $f) $header[] = $f['field_name'];
$header[] = 'tags';
fputcsv($out, $header, ';');

// Data rows
foreach ($items as $item) {
    $row = [$item['id']];

    // Field values
    foreach ($fields as $f) {
        $val = $pdo->prepare('SELECT value FROM item_values WHERE item_id=? AND field_id=?');
        $val->execute([$item['id'], $f['id']]);
        $row[] = $val->fetchColumn() ?? '';
    }

    // Tags (comma-separated within the cell)
    $tags_stmt = $pdo->prepare(
        'SELECT t.name FROM tags t
         JOIN item_tags it ON it.tag_id=t.id
         WHERE it.item_id=? ORDER BY t.name'
    );
    $tags_stmt->execute([$item['id']]);
    $tag_names = array_column($tags_stmt->fetchAll(), 'name');
    $row[] = implode(',', $tag_names);

    fputcsv($out, $row, ';');
}

fclose($out);
exit;
