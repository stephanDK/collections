<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/layout.php';
require_login();

$item_id = (int)($_GET['id'] ?? 0);
if (!$item_id) redirect(BASE_URL . '/collections.php');

$item = $pdo->prepare('SELECT i.*, u.username, c.name AS coll_name, c.id AS coll_id, c.has_images
                       FROM items i
                       JOIN collections c ON c.id = i.collection_id
                       LEFT JOIN users u ON u.id = i.created_by
                       WHERE i.id = ?');
$item->execute([$item_id]);
$item = $item->fetch();
if (!$item) redirect(BASE_URL . '/collections.php');

// Fields + values
$fields_stmt = $pdo->prepare(
    'SELECT cf.*, iv.value
     FROM collection_fields cf
     LEFT JOIN item_values iv ON iv.field_id = cf.id AND iv.item_id = ?
     WHERE cf.collection_id = ?
     ORDER BY cf.sort_order'
);
$fields_stmt->execute([$item_id, $item['coll_id']]);
$fields = $fields_stmt->fetchAll();

// Tags
$tags_stmt = $pdo->prepare(
    'SELECT t.* FROM tags t
     JOIN item_tags it ON it.tag_id = t.id
     WHERE it.item_id = ?
     ORDER BY t.name'
);
$tags_stmt->execute([$item_id]);
$tags = $tags_stmt->fetchAll();

// Prev / next item in collection (by created_at)
$prev = $pdo->prepare('SELECT id FROM items WHERE collection_id=? AND id < ? ORDER BY id DESC LIMIT 1');
$prev->execute([$item['coll_id'], $item_id]);
$prev = $prev->fetchColumn();

$next = $pdo->prepare('SELECT id FROM items WHERE collection_id=? AND id > ? ORDER BY id ASC LIMIT 1');
$next->execute([$item['coll_id'], $item_id]);
$next = $next->fetchColumn();

page_header($item['coll_name'] . ' – Item', 'collections');
?>

<div class="flex align-center justify-between" style="margin-bottom:20px;flex-wrap:wrap;gap:10px">
  <a href="items.php?coll=<?= $item['coll_id'] ?>" class="btn btn-ghost btn-sm">← <?= h($item['coll_name']) ?></a>
  <div class="flex gap-8">
    <?php if ($prev): ?>
      <a href="item_view.php?id=<?= $prev ?>" class="btn btn-ghost btn-sm">‹ Prev</a>
    <?php endif; ?>
    <?php if ($next): ?>
      <a href="item_view.php?id=<?= $next ?>" class="btn btn-ghost btn-sm">Next ›</a>
    <?php endif; ?>
    <a href="item_edit.php?id=<?= $item_id ?>" class="btn btn-primary btn-sm">Edit</a>
  </div>
</div>

<div class="item-view-grid">

  <!-- Image -->
  <?php if ($item['has_images'] && $item['image_path']): ?>
  <div class="card" style="padding:16px;text-align:center">
    <img src="<?= UPLOAD_URL . h($item['image_path']) ?>"
         style="max-width:100%;max-height:500px;object-fit:contain;border-radius:4px">
  </div>
  <?php endif; ?>

  <!-- Details -->
  <div>
    <div class="card">
      <h2 style="font-family:var(--font-head);font-size:1.4rem;margin-bottom:20px">
        Item details
        <span class="text-muted" style="font-size:.8rem;font-weight:400;margin-left:8px">
          #<?= $item_id ?>
        </span>
      </h2>

      <dl class="item-dl">
        <?php foreach ($fields as $f): ?>
        <dt><?= h($f['field_name']) ?></dt>
        <dd>
          <?php if ($f['field_type'] === 'boolean'): ?>
            <?= $f['value'] ? '<span class="bool-yes">✓ Yes</span>' : '<span class="bool-no">– No</span>' ?>
          <?php else: ?>
            <?= $f['value'] !== null && $f['value'] !== '' ? h($f['value']) : '<span class="text-muted">—</span>' ?>
          <?php endif; ?>
        </dd>
        <?php endforeach; ?>
      </dl>

      <?php if ($tags): ?>
      <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <div class="text-muted" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px">Tags</div>
        <div class="tags-wrap">
          <?php foreach ($tags as $t): ?>
          <a href="tag_view.php?id=<?= $t['id'] ?>" class="tag-pill">
            <?php if ($t['image_path']): ?>
              <img src="<?= UPLOAD_URL . h($t['image_path']) ?>" alt="">
            <?php endif; ?>
            <?= h($t['name']) ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border);font-size:.8rem;color:var(--muted)">
        Added <?= date('j. F Y', strtotime($item['created_at'])) ?>
        by <?= h($item['username'] ?? '—') ?>
      </div>
    </div>

    <div style="margin-top:12px;display:flex;gap:10px">
      <a href="item_edit.php?id=<?= $item_id ?>" class="btn btn-primary btn-sm">Edit item</a>
      <form method="post" action="item_delete.php" onsubmit="return confirm('Delete this item?')">
        <input type="hidden" name="id"   value="<?= $item_id ?>">
        <input type="hidden" name="coll" value="<?= $item['coll_id'] ?>">
        <button class="btn btn-danger btn-sm">Delete item</button>
      </form>
    </div>
  </div>

</div>

<style>
.item-view-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
  align-items: start;
}
/* If no image, span full width */
.item-view-grid > .card:only-child,
.item-view-grid > div:only-child {
  grid-column: 1 / -1;
}
@media (max-width: 700px) {
  .item-view-grid { grid-template-columns: 1fr; }
}
.item-dl {
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 8px 20px;
  align-items: baseline;
}
.item-dl dt {
  font-size: .82rem;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: .05em;
  white-space: nowrap;
}
.item-dl dd { color: var(--text); }
</style>

<?php page_footer(); ?>
