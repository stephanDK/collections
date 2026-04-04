<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/layout.php';
require_login();

$coll_id = (int)($_GET['coll'] ?? 0);
if (!$coll_id) redirect(BASE_URL . '/collections.php');

$coll = $pdo->prepare('SELECT * FROM collections WHERE id=?');
$coll->execute([$coll_id]);
$coll = $coll->fetch();
if (!$coll) redirect(BASE_URL . '/collections.php');

$action = $_POST['action'] ?? '';

// ── Add tag ───────────────────────────────────────────────────────────────────
if ($action === 'add_tag' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['tag_name'] ?? '');
    if ($name) {
        $img = upload_image('tag_image', 'tags');
        try {
            $pdo->prepare('INSERT INTO tags (name, collection_id, image_path, created_by) VALUES (?,?,?,?)')
                ->execute([$name, $coll_id, $img, $_SESSION['user_id']]);
            flash('success', "Tag '$name' created.");
        } catch (PDOException $e) {
            flash('error', "A tag named '$name' already exists in this collection.");
        }
    } else {
        flash('error', 'Tag name is required.');
    }
    redirect(BASE_URL . '/collection_tags.php?coll=' . $coll_id);
}

// ── Delete tag ────────────────────────────────────────────────────────────────
if ($action === 'delete_tag' && isset($_POST['tag_id'])) {
    $tid = (int)$_POST['tag_id'];
    // Verify tag belongs to this collection
    $tag = $pdo->prepare('SELECT image_path FROM tags WHERE id=? AND collection_id=?');
    $tag->execute([$tid, $coll_id]);
    $tag = $tag->fetch();
    if ($tag) {
        if ($tag['image_path']) {
            $f = UPLOAD_DIR . $tag['image_path'];
            if (file_exists($f)) unlink($f);
        }
        $pdo->prepare('DELETE FROM tags WHERE id=?')->execute([$tid]);
        flash('success', 'Tag deleted.');
    }
    redirect(BASE_URL . '/collection_tags.php?coll=' . $coll_id);
}

// ── Edit tag name/image ───────────────────────────────────────────────────────
if ($action === 'edit_tag' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $tid  = (int)($_POST['tag_id'] ?? 0);
    $name = trim($_POST['tag_name'] ?? '');
    if ($tid && $name) {
        // Verify ownership
        $tag = $pdo->prepare('SELECT image_path FROM tags WHERE id=? AND collection_id=?');
        $tag->execute([$tid, $coll_id]);
        $tag = $tag->fetch();
        if ($tag) {
            $img = $tag['image_path'];
            if (!empty($_FILES['tag_image']['tmp_name'])) {
                $up = upload_image('tag_image', 'tags');
                if ($up) {
                    // Remove old image
                    if ($img && file_exists(UPLOAD_DIR . $img)) unlink(UPLOAD_DIR . $img);
                    $img = $up;
                }
            }
            try {
                $pdo->prepare('UPDATE tags SET name=?, image_path=? WHERE id=?')
                    ->execute([$name, $img, $tid]);
                flash('success', 'Tag updated.');
            } catch (PDOException $e) {
                flash('error', "A tag named '$name' already exists in this collection.");
            }
        }
    }
    redirect(BASE_URL . '/collection_tags.php?coll=' . $coll_id);
}

// ── Fetch tags for this collection ───────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
if ($search) {
    $tags_stmt = $pdo->prepare(
        'SELECT t.*, u.username,
                (SELECT COUNT(*) FROM item_tags it WHERE it.tag_id=t.id) AS item_count
         FROM tags t LEFT JOIN users u ON u.id=t.created_by
         WHERE t.collection_id=? AND t.name LIKE ?
         ORDER BY t.name'
    );
    $tags_stmt->execute([$coll_id, '%' . $search . '%']);
} else {
    $tags_stmt = $pdo->prepare(
        'SELECT t.*, u.username,
                (SELECT COUNT(*) FROM item_tags it WHERE it.tag_id=t.id) AS item_count
         FROM tags t LEFT JOIN users u ON u.id=t.created_by
         WHERE t.collection_id=?
         ORDER BY t.name'
    );
    $tags_stmt->execute([$coll_id]);
}
$tags = $tags_stmt->fetchAll();

// Tag being edited
$edit_tag = null;
if (isset($_GET['edit_tag'])) {
    $et = $pdo->prepare('SELECT * FROM tags WHERE id=? AND collection_id=?');
    $et->execute([(int)$_GET['edit_tag'], $coll_id]);
    $edit_tag = $et->fetch();
}

page_header('Tags – ' . $coll['name'], 'collections');
?>

<div class="flex align-center justify-between" style="margin-bottom:20px;flex-wrap:wrap;gap:10px">
  <h1 class="page-title mt-0" style="border:none;padding:0;margin:0">
    Tags
    <small><?= h($coll['name']) ?></small>
  </h1>
  <a href="items.php?coll=<?= $coll_id ?>" class="btn btn-ghost btn-sm">← Back to items</a>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start">

  <!-- Tag list -->
  <div>
    <form method="get" class="toolbar" style="margin-bottom:16px">
      <input type="hidden" name="coll" value="<?= $coll_id ?>">
      <input type="text" name="q" class="form-control search-input"
             placeholder="Search tags…" value="<?= h($search) ?>">
      <button class="btn btn-secondary btn-sm">Search</button>
      <?php if ($search): ?>
        <a href="collection_tags.php?coll=<?= $coll_id ?>" class="btn btn-ghost btn-sm">Clear</a>
      <?php endif; ?>
    </form>

    <?php if (empty($tags)): ?>
      <div class="card" style="text-align:center;padding:32px">
        <p class="text-muted">No tags yet for this collection.</p>
      </div>
    <?php else: ?>
    <div class="card" style="padding:0;overflow:hidden">
      <table class="data-table">
        <thead><tr><th>Tag</th><th>Used in</th><th>Created by</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($tags as $t): ?>
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:8px">
              <?php if ($t['image_path']): ?>
                <img src="<?= UPLOAD_URL . h($t['image_path']) ?>"
                     style="width:28px;height:28px;border-radius:50%;object-fit:cover;border:1px solid var(--border)">
              <?php else: ?>
                <span style="width:28px;height:28px;background:#e8e0d0;border-radius:50%;display:inline-block;flex-shrink:0"></span>
              <?php endif; ?>
              <strong><?= h($t['name']) ?></strong>
            </div>
          </td>
          <td>
            <a href="items.php?coll=<?= $coll_id ?>&tag=<?= $t['id'] ?>" class="btn btn-ghost btn-sm">
              <?= $t['item_count'] ?> item<?= $t['item_count']!=1?'s':'' ?>
            </a>
          </td>
          <td class="text-muted"><?= h($t['username'] ?? '—') ?></td>
          <td class="actions">
            <a href="?coll=<?= $coll_id ?>&edit_tag=<?= $t['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
            <form method="post" onsubmit="return confirm('Delete tag \'<?= h($t['name']) ?>\'?<?= $t['item_count'] > 0 ? ' It is used in ' . $t['item_count'] . ' item(s).' : '' ?>')">
              <input type="hidden" name="action" value="delete_tag">
              <input type="hidden" name="tag_id" value="<?= $t['id'] ?>">
              <button class="btn btn-danger btn-sm">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Add / Edit form -->
  <div class="card">
    <?php if ($edit_tag): ?>
      <h3 style="font-family:var(--font-head);margin-bottom:14px">Edit Tag</h3>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="edit_tag">
        <input type="hidden" name="tag_id" value="<?= $edit_tag['id'] ?>">
        <div class="form-group">
          <label>Tag Name</label>
          <input type="text" name="tag_name" class="form-control" required
                 value="<?= h($edit_tag['name']) ?>">
        </div>
        <div class="form-group">
          <label>Image</label>
          <?php if ($edit_tag['image_path']): ?>
            <div style="margin-bottom:8px">
              <img src="<?= UPLOAD_URL . h($edit_tag['image_path']) ?>"
                   style="width:60px;height:60px;border-radius:50%;object-fit:cover;border:1px solid var(--border)">
            </div>
          <?php endif; ?>
          <input type="file" name="tag_image" class="form-control" accept="image/*">
          <div class="text-muted" style="font-size:.78rem;margin-top:4px">Leave empty to keep current image</div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn btn-primary">Save Changes</button>
          <a href="collection_tags.php?coll=<?= $coll_id ?>" class="btn btn-ghost">Cancel</a>
        </div>
      </form>
    <?php else: ?>
      <h3 style="font-family:var(--font-head);margin-bottom:14px">Add Tag</h3>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_tag">
        <div class="form-group">
          <label>Tag Name</label>
          <input type="text" name="tag_name" class="form-control" required
                 placeholder="e.g. Mint, 1980s, Signed">
        </div>
        <div class="form-group">
          <label>Image (optional)</label>
          <input type="file" name="tag_image" class="form-control" accept="image/*">
        </div>
        <button class="btn btn-primary">Create Tag</button>
      </form>
    <?php endif; ?>
  </div>

</div>

<style>
@media (max-width: 700px) {
  div[style*="grid-template-columns:1fr 320px"] {
    grid-template-columns: 1fr !important;
  }
}
</style>

<?php page_footer(); ?>
