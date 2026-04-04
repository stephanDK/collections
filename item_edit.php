<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/layout.php';
require_login();

// Determine mode: edit existing (id=) or add new (coll=)
$item_id = (int)($_GET['id'] ?? 0);
$coll_id = 0;
$item    = null;
$item_vals = [];
$item_tags = [];

if ($item_id) {
    $item = $pdo->prepare('SELECT * FROM items WHERE id = ?');
    $item->execute([$item_id]);
    $item = $item->fetch();
    if (!$item) redirect(BASE_URL . '/collections.php');
    $coll_id = $item['collection_id'];

    // existing values
    $vs = $pdo->prepare('SELECT field_id, value FROM item_values WHERE item_id = ?');
    $vs->execute([$item_id]);
    foreach ($vs->fetchAll() as $v) $item_vals[$v['field_id']] = $v['value'];

    // existing tags
    $ts = $pdo->prepare('SELECT tag_id FROM item_tags WHERE item_id = ?');
    $ts->execute([$item_id]);
    $item_tags = array_column($ts->fetchAll(), 'tag_id');
} else {
    $coll_id = (int)($_GET['coll'] ?? 0);
    if (!$coll_id) redirect(BASE_URL . '/collections.php');
}

$coll = $pdo->prepare('SELECT * FROM collections WHERE id = ?');
$coll->execute([$coll_id]);
$coll = $coll->fetch();
if (!$coll) redirect(BASE_URL . '/collections.php');

$fields_stmt = $pdo->prepare('SELECT * FROM collection_fields WHERE collection_id = ? ORDER BY sort_order');
$fields_stmt->execute([$coll_id]);
$fields = $fields_stmt->fetchAll();

// All tags for the checkbox list
$all_tags = $pdo->query('SELECT * FROM tags ORDER BY name')->fetchAll();

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();

    // Upload image if present
    $image_path = $item['image_path'] ?? null;
    if ($coll['has_images'] && !empty($_FILES['item_image']['tmp_name'])) {
        $up = upload_image('item_image', 'items');
        if ($up) $image_path = $up;
    }

    if ($item_id) {
        // Update
        $pdo->prepare('UPDATE items SET image_path=?, updated_at=NOW() WHERE id=?')
            ->execute([$image_path, $item_id]);
    } else {
        // Insert
        $pdo->prepare('INSERT INTO items (collection_id, created_by, image_path) VALUES (?,?,?)')
            ->execute([$coll_id, $_SESSION['user_id'], $image_path]);
        $item_id = (int)$pdo->lastInsertId();
    }

    // Save field values
    foreach ($fields as $f) {
        $key = 'field_' . $f['id'];
        if ($f['field_type'] === 'boolean') {
            $val = isset($_POST[$key]) ? '1' : '0';
        } else {
            $val = trim($_POST[$key] ?? '');
        }
        $pdo->prepare(
            'INSERT INTO item_values (item_id, field_id, value) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)'
        )->execute([$item_id, $f['id'], $val]);
    }

    // Save tags
    $pdo->prepare('DELETE FROM item_tags WHERE item_id = ?')->execute([$item_id]);
    $selected_tags = $_POST['tags'] ?? [];
    foreach ($selected_tags as $tid) {
        $tid = (int)$tid;
        if ($tid > 0) {
            $pdo->prepare('INSERT IGNORE INTO item_tags (item_id, tag_id) VALUES (?,?)')->execute([$item_id, $tid]);
        }
    }

    $pdo->commit();
    flash('success', 'Item saved.');
    redirect(BASE_URL . '/items.php?coll=' . $coll_id);
}

$is_edit = (bool)($item['id'] ?? false);
page_header(($is_edit ? 'Edit' : 'Add') . ' Item – ' . $coll['name'], 'collections');
?>

<div class="flex align-center justify-between" style="margin-bottom:24px;flex-wrap:wrap;gap:12px">
  <h1 class="page-title mt-0" style="border:none;padding:0;margin:0">
    <?= $is_edit ? 'Edit Item' : 'Add Item' ?>
    <small><?= h($coll['name']) ?></small>
  </h1>
  <a href="items.php?coll=<?= $coll_id ?>" class="btn btn-ghost btn-sm">← Back</a>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start">
  <!-- Main form -->
  <div class="card">
    <form method="post" enctype="multipart/form-data">

      <?php if ($coll['has_images']): ?>
      <div class="form-group">
        <label>Image</label>
        <?php if (!empty($item['image_path'])): ?>
          <div style="margin-bottom:8px">
            <img src="<?= UPLOAD_URL . h($item['image_path']) ?>" alt="" class="item-image-lg">
          </div>
        <?php endif; ?>
        <input type="file" name="item_image" class="form-control" accept="image/*">
        <div class="text-muted" style="font-size:.8rem;margin-top:4px">JPEG, PNG, GIF or WebP — max 5 MB</div>
      </div>
      <?php endif; ?>

      <?php foreach ($fields as $f):
            $key = 'field_' . $f['id'];
            $val = $_POST[$key] ?? ($item_vals[$f['id']] ?? '');
      ?>
      <div class="form-group">
        <label><?= h($f['field_name']) ?> <span class="text-muted">(<?= $f['field_type'] ?>)</span></label>
        <?php if ($f['field_type'] === 'boolean'): ?>
          <div style="display:flex;align-items:center;gap:8px;padding:4px 0">
            <input type="checkbox" name="<?= $key ?>" id="<?= $key ?>" <?= $val ? 'checked' : '' ?>>
            <label for="<?= $key ?>" style="margin:0;text-transform:none;font-size:.95rem;font-weight:400">
              <?= h($f['field_name']) ?>
            </label>
          </div>
        <?php elseif ($f['field_type'] === 'number'): ?>
          <input type="number" step="any" name="<?= $key ?>" class="form-control"
                 value="<?= h((string)$val) ?>">
        <?php else: ?>
          <input type="text" name="<?= $key ?>" class="form-control"
                 value="<?= h((string)$val) ?>">
        <?php endif; ?>
      </div>
      <?php endforeach; ?>

      <div style="margin-top:24px;display:flex;gap:10px">
        <button class="btn btn-primary"><?= $is_edit ? 'Save Changes' : 'Create Item' ?></button>
        <a href="items.php?coll=<?= $coll_id ?>" class="btn btn-ghost">Cancel</a>
      </div>
    </form>
  </div>

  <!-- Tags sidebar -->
  <div class="card">
    <h3 style="font-family:var(--font-head);margin-bottom:16px">Tags</h3>

    <?php if (empty($all_tags)): ?>
      <p class="text-muted">No tags yet. <a href="tags.php">Create some tags</a>.</p>
    <?php else: ?>
      <div style="max-height:400px;overflow-y:auto;display:flex;flex-direction:column;gap:8px">
        <?php foreach ($all_tags as $t): ?>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:4px">
          <input type="checkbox" form="tag-form" name="tags[]" value="<?= $t['id'] ?>"
                 <?= in_array($t['id'], $item_tags) ? 'checked' : '' ?>>
          <?php if ($t['image_path']): ?>
            <img src="<?= UPLOAD_URL . h($t['image_path']) ?>" alt="" style="width:20px;height:20px;border-radius:50%;object-fit:cover">
          <?php endif; ?>
          <?= h($t['name']) ?>
        </label>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div style="margin-top:16px">
      <a href="tags.php?return=item&id=<?= $item_id ?>&coll=<?= $coll_id ?>" class="btn btn-ghost btn-sm">
        + Create new tag
      </a>
    </div>
  </div>
</div>

<!-- Hidden form that carries tag checkboxes — ties into the main form submit -->
<form id="tag-form" method="post" enctype="multipart/form-data" style="display:none"></form>
<script>
// Merge tag-form into main form on submit
document.querySelector('form').addEventListener('submit', function(e) {
  const main = this;
  document.querySelectorAll('#tag-form input[type=checkbox]').forEach(cb => {
    const h = document.createElement('input');
    h.type  = 'hidden';
    h.name  = cb.name;
    h.value = cb.value;
    if (cb.checked) main.appendChild(h);
  });
});
</script>

<?php page_footer(); ?>
