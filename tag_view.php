<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/layout.php';
require_login();

$tag_id = (int)($_GET['id'] ?? 0);
if (!$tag_id) redirect(BASE_URL . '/tags.php');

$tag = $pdo->prepare(
    'SELECT t.*, u.username, c.name AS coll_name
     FROM tags t
     LEFT JOIN users u ON u.id=t.created_by
     LEFT JOIN collections c ON c.id=t.collection_id
     WHERE t.id=?'
);
$tag->execute([$tag_id]);
$tag = $tag->fetch();
if (!$tag) redirect(BASE_URL . '/tags.php');

// Items with this tag
$items_stmt = $pdo->prepare(
    'SELECT i.id, i.image_path, i.created_at,
            c.id AS coll_id, c.name AS coll_name,
            (SELECT GROUP_CONCAT(cf.field_name, ": ", iv.value ORDER BY cf.sort_order SEPARATOR "  ·  ")
             FROM item_values iv
             JOIN collection_fields cf ON cf.id = iv.field_id
             WHERE iv.item_id = i.id AND iv.value != "" AND iv.value != "0") AS summary
     FROM items i
     JOIN item_tags it ON it.item_id = i.id
     JOIN collections c ON c.id = i.collection_id
     WHERE it.tag_id = ?
     ORDER BY c.name, i.created_at DESC'
);
$items_stmt->execute([$tag_id]);
$items = $items_stmt->fetchAll();

// Count per collection
$by_coll = [];
foreach ($items as $it) {
    $by_coll[$it['coll_id']]['name']    = $it['coll_name'];
    $by_coll[$it['coll_id']]['items'][] = $it;
}

page_header('Tag: ' . $tag['name'], 'tags');
?>

<div class="flex align-center justify-between" style="margin-bottom:20px;flex-wrap:wrap;gap:10px">
  <a href="tags.php" class="btn btn-ghost btn-sm">← All Tags</a>
  <?php if ($tag['collection_id'] === null && is_admin()): ?>
  <form method="post" action="admin.php" onsubmit="return confirm('Delete global tag «<?= h($tag['name']) ?>»?')">
    <input type="hidden" name="action" value="delete_global_tag">
    <input type="hidden" name="tag_id" value="<?= $tag_id ?>">
    <button class="btn btn-danger btn-sm">Delete tag</button>
  </form>
  <?php elseif ($tag['collection_id']): ?>
  <form method="post" action="collection_tags.php" onsubmit="return confirm('Delete tag «<?= h($tag['name']) ?>»?')">
    <input type="hidden" name="action" value="delete_tag">
    <input type="hidden" name="tag_id" value="<?= $tag_id ?>">
    <input type="hidden" name="coll" value="<?= $tag['collection_id'] ?>">
    <button class="btn btn-danger btn-sm">Delete tag</button>
  </form>
  <?php endif; ?>
</div>

<!-- Tag hero -->
<div class="card" style="margin-bottom:24px">
  <div style="display:flex;align-items:center;gap:24px;flex-wrap:wrap">
    <?php if ($tag['image_path']): ?>
    <img src="<?= UPLOAD_URL . h($tag['image_path']) ?>"
         style="width:120px;height:120px;object-fit:cover;border-radius:8px;border:1px solid var(--border);flex-shrink:0">
    <?php else: ?>
    <div style="width:120px;height:120px;background:#e8e0d0;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
      <span style="font-size:2.5rem;color:var(--muted)">🏷</span>
    </div>
    <?php endif; ?>
    <div>
      <h1 style="font-family:var(--font-head);font-size:2rem;margin-bottom:6px"><?= h($tag['name']) ?></h1>
      <div style="margin-bottom:8px">
        <?php if ($tag['collection_id'] === null): ?>
          <span style="background:#2d6a4f;color:#fff;font-size:.75rem;font-weight:700;padding:3px 10px;border-radius:12px;letter-spacing:.04em">🌐 Global tag</span>
        <?php else: ?>
          <span style="background:#b5451b;color:#fff;font-size:.75rem;font-weight:700;padding:3px 10px;border-radius:12px;letter-spacing:.04em">📁 <?= h($tag['coll_name']) ?></span>
        <?php endif; ?>
      </div>
      <div class="text-muted">
        <?= count($items) ?> item<?= count($items)!=1?'s':'' ?>
        <?php if ($tag['username']): ?> · Created by <?= h($tag['username']) ?><?php endif; ?>
        · <?= date('j. F Y', strtotime($tag['created_at'])) ?>
      </div>
    </div>
  </div>
</div>

<?php if (empty($items)): ?>
  <div class="card" style="text-align:center;padding:40px">
    <p class="text-muted">No items have this tag yet.</p>
  </div>
<?php else: ?>
  <?php foreach ($by_coll as $cid => $group): ?>
  <div style="margin-bottom:28px">
    <div class="flex align-center justify-between" style="margin-bottom:10px">
      <h2 style="font-family:var(--font-head);font-size:1.1rem">
        <a href="items.php?coll=<?= $cid ?>&tag=<?= $tag_id ?>" style="color:inherit;text-decoration:none">
          <?= h($group['name']) ?>
        </a>
      </h2>
      <span class="text-muted"><?= count($group['items']) ?> item<?= count($group['items'])!=1?'s':'' ?></span>
    </div>

    <div class="tag-items-grid">
      <?php foreach ($group['items'] as $it): ?>
      <a href="item_view.php?id=<?= $it['id'] ?>" class="tag-item-card">
        <?php if ($it['image_path']): ?>
          <div class="tag-item-card__img">
            <img src="<?= UPLOAD_URL . h($it['image_path']) ?>" alt="">
          </div>
        <?php else: ?>
          <div class="tag-item-card__img tag-item-card__img--empty">
            <span>📦</span>
          </div>
        <?php endif; ?>
        <div class="tag-item-card__body">
          <?php if ($it['summary']): ?>
            <div style="font-size:.85rem;line-height:1.4"><?= h($it['summary']) ?></div>
          <?php else: ?>
            <div class="text-muted" style="font-size:.85rem">Item #<?= $it['id'] ?></div>
          <?php endif; ?>
          <div class="text-muted" style="font-size:.75rem;margin-top:4px">
            <?= date('d M Y', strtotime($it['created_at'])) ?>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<style>
.tag-items-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 12px;
}
.tag-item-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  text-decoration: none;
  color: var(--text);
  box-shadow: var(--shadow);
  transition: transform .15s, box-shadow .15s;
  display: flex;
  flex-direction: column;
}
.tag-item-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(0,0,0,.1);
}
.tag-item-card__img {
  width: 100%;
  aspect-ratio: 1;
  overflow: hidden;
  background: #e8e0d0;
}
.tag-item-card__img img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.tag-item-card__img--empty {
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 2rem;
}
.tag-item-card__body {
  padding: 10px 12px;
}
</style>

<?php page_footer(); ?>
