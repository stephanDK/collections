<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/layout.php';
require_login();

$collections = $pdo->query(
    'SELECT c.*,
            (SELECT COUNT(*) FROM items i WHERE i.collection_id = c.id) AS item_count
     FROM collections c
     ORDER BY c.name'
)->fetchAll();

page_header('Collections', 'collections');
?>

<div class="flex align-center justify-between" style="margin-bottom:24px">
  <h1 class="page-title mt-0" style="border:none;padding:0;margin:0">My Collections</h1>
</div>

<?php if (empty($collections)): ?>
  <div class="card" style="text-align:center;padding:48px">
    <p style="color:var(--muted);font-size:1.1rem">No collections yet.</p>
    <?php if (is_admin()): ?>
    <a href="admin.php" class="btn btn-primary mt-16">Create a collection</a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <div class="collection-grid">
    <?php foreach ($collections as $c): ?>
    <a href="items.php?coll=<?= $c['id'] ?>" class="coll-card">
      <div class="coll-card__name"><?= h($c['name']) ?></div>
      <?php if ($c['description']): ?>
        <div class="text-muted" style="font-size:.88rem"><?= h($c['description']) ?></div>
      <?php endif; ?>
      <div class="coll-card__meta"><?= $c['item_count'] ?> item<?= $c['item_count']!=1?'s':'' ?></div>
      <?php if ($c['has_images']): ?>
        <span class="coll-card__badge">&#128247; Images</span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php page_footer(); ?>
