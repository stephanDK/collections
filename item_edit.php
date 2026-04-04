<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/layout.php';
require_login();

$item_id   = (int)($_GET['id']    ?? 0);
$coll_id   = 0;
$item      = null;
$item_vals = [];
$item_tags = [];

if ($item_id) {
    $item = $pdo->prepare('SELECT * FROM items WHERE id = ?');
    $item->execute([$item_id]);
    $item = $item->fetch();
    if (!$item) redirect(BASE_URL . '/collections.php');
    $coll_id = $item['collection_id'];

    $vs = $pdo->prepare('SELECT field_id, value FROM item_values WHERE item_id = ?');
    $vs->execute([$item_id]);
    foreach ($vs->fetchAll() as $v) $item_vals[$v['field_id']] = $v['value'];

    $ts = $pdo->prepare('SELECT tag_id FROM item_tags WHERE item_id = ?');
    $ts->execute([$item_id]);
    $item_tags = array_column($ts->fetchAll(), 'tag_id');
} else {
    $coll_id = (int)($_GET['coll'] ?? (int)($_POST['coll_id'] ?? 0));
    if (!$coll_id) redirect(BASE_URL . '/collections.php');
}

$coll = $pdo->prepare('SELECT * FROM collections WHERE id = ?');
$coll->execute([$coll_id]);
$coll = $coll->fetch();
if (!$coll) redirect(BASE_URL . '/collections.php');

$fields_stmt = $pdo->prepare('SELECT * FROM collection_fields WHERE collection_id = ? ORDER BY sort_order');
$fields_stmt->execute([$coll_id]);
$fields = $fields_stmt->fetchAll();

// Tags: global + collection-specific
$global_tags = $pdo->prepare(
    'SELECT * FROM tags WHERE collection_id IS NULL ORDER BY name'
);
$global_tags->execute();
$global_tags = $global_tags->fetchAll();

$coll_tags = $pdo->prepare(
    'SELECT * FROM tags WHERE collection_id = ? ORDER BY name'
);
$coll_tags->execute([$coll_id]);
$coll_tags = $coll_tags->fetchAll();

$all_tags = array_merge($global_tags, $coll_tags);

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->beginTransaction();

    $image_path = $item['image_path'] ?? null;
    if ($coll['has_images'] && !empty($_FILES['item_image']['tmp_name'])) {
        $up = upload_image('item_image', 'items');
        if ($up) $image_path = $up;
    }

    if ($item_id) {
        $pdo->prepare('UPDATE items SET image_path=?, updated_at=NOW() WHERE id=?')
            ->execute([$image_path, $item_id]);
    } else {
        $pdo->prepare('INSERT INTO items (collection_id, created_by, image_path) VALUES (?,?,?)')
            ->execute([$coll_id, $_SESSION['user_id'], $image_path]);
        $item_id = (int)$pdo->lastInsertId();
    }

    foreach ($fields as $f) {
        $key = 'field_' . $f['id'];
        $val = $f['field_type'] === 'boolean'
            ? (isset($_POST[$key]) ? '1' : '0')
            : trim($_POST[$key] ?? '');
        $pdo->prepare(
            'INSERT INTO item_values (item_id, field_id, value) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)'
        )->execute([$item_id, $f['id'], $val]);
    }

    // Tags are now inside the same <form> — no JS merging needed
    $pdo->prepare('DELETE FROM item_tags WHERE item_id = ?')->execute([$item_id]);
    foreach ($_POST['tags'] ?? [] as $tid) {
        $tid = (int)$tid;
        if ($tid > 0) {
            $pdo->prepare('INSERT IGNORE INTO item_tags (item_id, tag_id) VALUES (?,?)')
                ->execute([$item_id, $tid]);
        }
    }

    $pdo->commit();

    if (isset($_POST['quick_add'])) {
        flash('success', 'Item saved — add another:');
        redirect(BASE_URL . '/item_edit.php?coll=' . $coll_id . '&quick=1');
    }

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
  <a href="items.php?coll=<?= $coll_id ?>" class="btn btn-ghost btn-sm">← Back to list</a>
</div>

<!-- Single form — tags included directly, no JS merging needed -->
<form method="post" enctype="multipart/form-data">
  <input type="hidden" name="coll_id" value="<?= $coll_id ?>">

  <div class="item-edit-grid">

    <!-- Main fields -->
    <div class="card">

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

      <div style="margin-top:24px;display:flex;gap:10px;flex-wrap:wrap">
        <button type="submit" name="save" value="1" class="btn btn-primary">
          <?= $is_edit ? 'Save Changes' : 'Create Item' ?>
        </button>
        <?php if (!$is_edit): ?>
        <button type="submit" name="quick_add" value="1" class="btn btn-success">
          ✚ Save &amp; Add Another
        </button>
        <?php endif; ?>
        <a href="items.php?coll=<?= $coll_id ?>" class="btn btn-ghost">Cancel</a>
      </div>
    </div>

    <!-- Tags sidebar -->
    <div class="card">
      <h3 style="font-family:var(--font-head);margin-bottom:12px">Tags</h3>

      <?php if (empty($all_tags)): ?>
        <p class="text-muted">No tags yet.</p>
      <?php else: ?>
        <input type="text" id="tag-search" class="form-control"
               placeholder="Filter tags…" style="margin-bottom:10px" autocomplete="off">
        <div id="tag-list" style="max-height:360px;overflow-y:auto;display:flex;flex-direction:column;gap:4px">

          <?php if ($global_tags): ?>
          <div class="tag-group-label">🌐 Global</div>
          <?php foreach ($global_tags as $t): ?>
          <label class="tag-check-row">
            <input type="checkbox" name="tags[]" value="<?= $t['id'] ?>"
                   <?= in_array($t['id'], $item_tags) ? 'checked' : '' ?>>
            <?php if ($t['image_path']): ?>
              <img src="<?= UPLOAD_URL . h($t['image_path']) ?>"
                   style="width:22px;height:22px;border-radius:50%;object-fit:cover;flex-shrink:0">
            <?php endif; ?>
            <span class="tag-label"><?= h($t['name']) ?></span>
          </label>
          <?php endforeach; ?>
          <?php endif; ?>

          <?php if ($coll_tags): ?>
          <div class="tag-group-label" style="margin-top:<?= $global_tags ? '8px' : '0' ?>">
            📁 <?= h($coll['name']) ?>
          </div>
          <?php foreach ($coll_tags as $t): ?>
          <label class="tag-check-row">
            <input type="checkbox" name="tags[]" value="<?= $t['id'] ?>"
                   <?= in_array($t['id'], $item_tags) ? 'checked' : '' ?>>
            <?php if ($t['image_path']): ?>
              <img src="<?= UPLOAD_URL . h($t['image_path']) ?>"
                   style="width:22px;height:22px;border-radius:50%;object-fit:cover;flex-shrink:0">
            <?php endif; ?>
            <span class="tag-label"><?= h($t['name']) ?></span>
          </label>
          <?php endforeach; ?>
          <?php endif; ?>

        </div>
      <?php endif; ?>

      <div style="margin-top:14px">
        <a href="collection_tags.php?coll=<?= $coll_id ?>"
           class="btn btn-ghost btn-sm">🏷 Manage collection tags</a>
      </div>
    </div>

  </div>
</form>

<style>
.item-edit-grid {
  display: grid;
  grid-template-columns: 1fr 300px;
  gap: 24px;
  align-items: start;
}
@media (max-width: 700px) {
  .item-edit-grid { grid-template-columns: 1fr; }
}
.tag-group-label {
  font-size: .72rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .07em;
  color: var(--muted);
  padding: 4px 2px 2px;
}
.tag-check-row {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  padding: 5px 6px;
  border-radius: 4px;
  font-size: .92rem;
}
.tag-check-row:hover { background: #f0ebe0; }
</style>

<script>
document.getElementById('tag-search')?.addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.tag-check-row').forEach(row => {
    const name = row.querySelector('.tag-label').textContent.toLowerCase();
    row.style.display = name.includes(q) ? '' : 'none';
  });
});
</script>

<?php page_footer(); ?>
