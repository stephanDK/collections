<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/layout.php';
require_login();

$action = $_POST['action'] ?? '';

// ── Create tag ────────────────────────────────────────────────────────────────
if ($action === 'add_tag' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['tag_name'] ?? '');
    if ($name) {
        $img = upload_image('tag_image', 'tags');
        try {
            $pdo->prepare('INSERT INTO tags (name, image_path, created_by) VALUES (?,?,?)')
                ->execute([$name, $img, $_SESSION['user_id']]);
            flash('success', "Tag '$name' created.");
        } catch (PDOException $e) {
            flash('error', 'A tag with that name already exists.');
        }
    } else {
        flash('error', 'Tag name is required.');
    }

    // Return back to item edit if requested
    $return_item = (int)($_POST['return_item'] ?? 0);
    $return_coll = (int)($_POST['return_coll'] ?? 0);
    if ($return_item) {
        redirect(BASE_URL . '/item_edit.php?id=' . $return_item);
    } elseif ($return_coll) {
        redirect(BASE_URL . '/item_edit.php?coll=' . $return_coll);
    }
    redirect(BASE_URL . '/tags.php');
}

// ── Delete tag ────────────────────────────────────────────────────────────────
if ($action === 'delete_tag' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $tid = (int)($_POST['tag_id'] ?? 0);
    if ($tid) {
        $tag = $pdo->prepare('SELECT image_path FROM tags WHERE id=?');
        $tag->execute([$tid]);
        $tag = $tag->fetch();
        if ($tag && $tag['image_path']) {
            $f = UPLOAD_DIR . $tag['image_path'];
            if (file_exists($f)) unlink($f);
        }
        $pdo->prepare('DELETE FROM tags WHERE id=?')->execute([$tid]);
        flash('success', 'Tag deleted.');
    }
    redirect(BASE_URL . '/tags.php');
}

// ── Search ────────────────────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$selected_tag = (int)($_GET['tag'] ?? 0);

if ($search !== '') {
    $tags_stmt = $pdo->prepare(
        'SELECT t.*, u.username,
                (SELECT COUNT(*) FROM item_tags it WHERE it.tag_id=t.id) AS item_count
         FROM tags t LEFT JOIN users u ON u.id=t.created_by
         WHERE t.name LIKE ? ORDER BY t.name'
    );
    $tags_stmt->execute(['%' . $search . '%']);
} else {
    $tags_stmt = $pdo->prepare(
        'SELECT t.*, u.username,
                (SELECT COUNT(*) FROM item_tags it WHERE it.tag_id=t.id) AS item_count
         FROM tags t LEFT JOIN users u ON u.id=t.created_by
         ORDER BY t.name'
    );
    $tags_stmt->execute();
}
$tags = $tags_stmt->fetchAll();

// Items for a selected tag
$tag_items = [];
$selected_tag_name = '';
if ($selected_tag) {
    $tn = $pdo->prepare('SELECT name FROM tags WHERE id=?');
    $tn->execute([$selected_tag]);
    $selected_tag_name = $tn->fetchColumn();

    $ti_stmt = $pdo->prepare(
        'SELECT i.id, i.image_path, c.id AS coll_id, c.name AS coll_name,
                (SELECT GROUP_CONCAT(cf.field_name, ": ", iv.value ORDER BY cf.sort_order SEPARATOR " | ")
                 FROM item_values iv JOIN collection_fields cf ON cf.id=iv.field_id
                 WHERE iv.item_id=i.id AND iv.value != "" AND iv.value != "0") AS summary
         FROM items i
         JOIN item_tags it ON it.item_id=i.id
         JOIN collections c ON c.id=i.collection_id
         WHERE it.tag_id=?
         ORDER BY c.name, i.created_at DESC'
    );
    $ti_stmt->execute([$selected_tag]);
    $tag_items = $ti_stmt->fetchAll();
}

// Return-to params (from item_edit)
$return_item = (int)($_GET['id']     ?? 0);
$return_coll = (int)($_GET['coll']   ?? 0);
$return_mode = $_GET['return'] ?? '';

page_header('Tags', 'tags');
?>

<div class="flex align-center justify-between" style="margin-bottom:24px;flex-wrap:wrap;gap:12px">
  <h1 class="page-title mt-0" style="border:none;padding:0;margin:0">
    Tags <small><?= count($tags) ?></small>
  </h1>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start">

  <!-- Left: tag list + search -->
  <div>
    <!-- Search bar -->
    <form method="get" action="" class="toolbar">
      <input type="text" name="q" class="form-control search-input"
             placeholder="Search tags…" value="<?= h($search) ?>">
      <?php if ($selected_tag): ?>
        <input type="hidden" name="tag" value="<?= $selected_tag ?>">
      <?php endif; ?>
      <button class="btn btn-secondary btn-sm">Search</button>
      <?php if ($search): ?>
        <a href="tags.php<?= $selected_tag ? '?tag='.$selected_tag : '' ?>" class="btn btn-ghost btn-sm">Clear</a>
      <?php endif; ?>
    </form>

    <?php if (empty($tags)): ?>
      <div class="card" style="text-align:center;padding:40px">
        <p class="text-muted">No tags found.</p>
      </div>
    <?php else: ?>
    <div class="card" style="padding:0;overflow:hidden">
      <table class="data-table">
        <thead><tr><th>Tag</th><th>Items</th><th>Created by</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($tags as $t): ?>
        <tr class="<?= $t['id'] == $selected_tag ? 'selected-row' : '' ?>">
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <?php if ($t['image_path']): ?>
                <img src="<?= UPLOAD_URL . h($t['image_path']) ?>"
                     style="width:28px;height:28px;border-radius:50%;object-fit:cover;border:1px solid var(--border)">
              <?php endif; ?>
              <strong><?= h($t['name']) ?></strong>
            </div>
          </td>
          <td>
            <a href="tags.php?tag=<?= $t['id'] ?>&q=<?= urlencode($search) ?>" class="btn btn-ghost btn-sm">
              <?= $t['item_count'] ?> item<?= $t['item_count']!=1?'s':'' ?>
            </a>
          </td>
          <td class="text-muted"><?= h($t['username'] ?? '—') ?></td>
          <td class="actions">
            <form method="post" onsubmit="return confirm('Delete tag <?= h($t['name']) ?>?')">
              <input type="hidden" name="action"  value="delete_tag">
              <input type="hidden" name="tag_id"  value="<?= $t['id'] ?>">
              <button class="btn btn-danger btn-sm">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <!-- Items for selected tag -->
    <?php if ($selected_tag && $selected_tag_name): ?>
    <div class="card mt-16">
      <h3 style="font-family:var(--font-head);margin-bottom:16px">
        Items tagged "<?= h($selected_tag_name) ?>"
        <span class="text-muted" style="font-size:.9rem">(<?= count($tag_items) ?>)</span>
      </h3>
      <?php if (empty($tag_items)): ?>
        <p class="text-muted">No items with this tag.</p>
      <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:8px">
        <?php foreach ($tag_items as $ti): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:8px;background:#f5f0e8;border-radius:4px">
          <?php if ($ti['image_path']): ?>
            <img src="<?= UPLOAD_URL . h($ti['image_path']) ?>" alt="" class="item-thumb">
          <?php else: ?>
            <div style="width:56px;height:56px;background:#e0d8c8;border-radius:4px;flex-shrink:0"></div>
          <?php endif; ?>
          <div style="flex:1;min-width:0">
            <div class="text-muted" style="font-size:.78rem"><?= h($ti['coll_name']) ?></div>
            <div style="font-size:.88rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
              <?= h($ti['summary'] ?? '—') ?>
            </div>
          </div>
          <a href="items.php?coll=<?= $ti['coll_id'] ?>&tag=<?= $selected_tag ?>"
             class="btn btn-ghost btn-sm" style="flex-shrink:0">View</a>
          <a href="item_edit.php?id=<?= $ti['id'] ?>" class="btn btn-ghost btn-sm" style="flex-shrink:0">Edit</a>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right: create tag -->
  <div class="card">
    <h3 style="font-family:var(--font-head);margin-bottom:16px">Create Tag</h3>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="action"      value="add_tag">
      <input type="hidden" name="return_item" value="<?= $return_item ?>">
      <input type="hidden" name="return_coll" value="<?= $return_coll ?>">
      <div class="form-group">
        <label>Tag Name</label>
        <input type="text" name="tag_name" class="form-control" required placeholder="e.g. Rare, Mint, 1980s">
      </div>
      <div class="form-group">
        <label>Image (optional)</label>
        <input type="file" name="tag_image" class="form-control" accept="image/*">
      </div>
      <button class="btn btn-primary">Create Tag</button>
      <?php if ($return_item || $return_coll): ?>
        <a href="item_edit.php?<?= $return_item ? 'id='.$return_item : 'coll='.$return_coll ?>"
           class="btn btn-ghost" style="margin-left:8px">← Back to Item</a>
      <?php endif; ?>
    </form>
  </div>

</div>

<style>
tr.selected-row td { background: #f0ebe0; }
</style>

<?php page_footer(); ?>
