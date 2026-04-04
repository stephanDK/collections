<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/layout.php';
require_login();

$coll_id = (int)($_GET['coll'] ?? 0);
if (!$coll_id) redirect(BASE_URL . '/collections.php');

$coll = $pdo->prepare('SELECT * FROM collections WHERE id = ?');
$coll->execute([$coll_id]);
$coll = $coll->fetch();
if (!$coll) redirect(BASE_URL . '/collections.php');

// Fields for this collection
$fields_stmt = $pdo->prepare('SELECT * FROM collection_fields WHERE collection_id = ? ORDER BY sort_order');
$fields_stmt->execute([$coll_id]);
$fields = $fields_stmt->fetchAll();

// ── Build query with search / filter ─────────────────────────────────────────
$search   = trim($_GET['q'] ?? '');
$tag_id   = (int)($_GET['tag'] ?? 0);
$sort_col = $_GET['sort'] ?? 'created_at';
$sort_dir = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
$page     = max(1, (int)($_GET['p'] ?? 1));
$per_page = 40;

// Validate sort column
$valid_sort_cols = ['created_at'];
foreach ($fields as $f) $valid_sort_cols[] = 'f_' . $f['id'];
if (!in_array($sort_col, $valid_sort_cols)) $sort_col = 'created_at';

// Build base query – we join item_values as a pivot
$value_joins = '';
$order_expr  = 'i.created_at';
foreach ($fields as $f) {
    $alias = 'v' . $f['id'];
    $value_joins .= " LEFT JOIN item_values $alias ON $alias.item_id = i.id AND $alias.field_id = {$f['id']}";
    if ($sort_col === 'f_' . $f['id']) {
        $order_expr = ($f['field_type'] === 'number') ? "CAST($alias.value AS DECIMAL(20,6))" : "$alias.value";
    }
}

$where   = 'WHERE i.collection_id = ?';
$params  = [$coll_id];

if ($search !== '') {
    // Search across all text/number fields
    $or_parts = [];
    foreach ($fields as $f) {
        $alias = 'v' . $f['id'];
        $or_parts[] = "$alias.value LIKE ?";
        $params[]   = '%' . $search . '%';
    }
    if ($or_parts) $where .= ' AND (' . implode(' OR ', $or_parts) . ')';
}

if ($tag_id) {
    $where  .= ' AND EXISTS (SELECT 1 FROM item_tags it WHERE it.item_id=i.id AND it.tag_id=?)';
    $params[] = $tag_id;
}

// Count
$count_sql  = "SELECT COUNT(DISTINCT i.id) FROM items i $value_joins $where";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();
$pages = max(1, (int)ceil($total / $per_page));
$page  = min($page, $pages);

$offset = ($page - 1) * $per_page;

// Fetch items
$sql  = "SELECT DISTINCT i.id, i.image_path, i.created_at, u.username FROM items i $value_joins";
$sql .= " LEFT JOIN users u ON u.id = i.created_by";
$sql .= " $where ORDER BY $order_expr $sort_dir LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// For each item load values and tags
$items_data = [];
foreach ($items as $item) {
    // values
    $vs = $pdo->prepare('SELECT field_id, value FROM item_values WHERE item_id = ?');
    $vs->execute([$item['id']]);
    $vals = [];
    foreach ($vs->fetchAll() as $v) $vals[$v['field_id']] = $v['value'];

    // tags
    $ts = $pdo->prepare(
        'SELECT t.id, t.name, t.image_path FROM tags t
         JOIN item_tags it ON it.tag_id = t.id WHERE it.item_id = ? ORDER BY t.name'
    );
    $ts->execute([$item['id']]);

    $items_data[$item['id']] = [
        'row'  => $item,
        'vals' => $vals,
        'tags' => $ts->fetchAll(),
    ];
}

// Active tag filter name
$filter_tag = null;
if ($tag_id) {
    $ft = $pdo->prepare('SELECT name FROM tags WHERE id=?');
    $ft->execute([$tag_id]);
    $filter_tag = $ft->fetchColumn();
}

// ── URL helper ───────────────────────────────────────────────────────────────
function items_url(array $overrides = []): string {
    global $coll_id, $search, $tag_id, $sort_col, $sort_dir, $page;
    $params = ['coll' => $coll_id, 'q' => $search, 'tag' => $tag_id,
               'sort' => $sort_col, 'dir' => $sort_dir, 'p' => $page];
    return BASE_URL . '/items.php?' . http_build_query(array_merge($params, $overrides));
}

function sort_link(array $field_or_meta): string {
    global $sort_col, $sort_dir;
    $key   = $field_or_meta['key'];
    $label = $field_or_meta['label'];
    $active = ($sort_col === $key);
    $newdir = ($active && $sort_dir === 'ASC') ? 'DESC' : 'ASC';
    $icon  = $active ? ($sort_dir === 'ASC' ? ' ▲' : ' ▼') : '';
    return '<a href="' . items_url(['sort' => $key, 'dir' => $newdir, 'p' => 1]) . '">' . h($label) . $icon . '</a>';
}

page_header(h($coll['name']), 'collections');
?>

<div class="flex align-center justify-between" style="margin-bottom:8px;flex-wrap:wrap;gap:12px">
  <h1 class="page-title mt-0" style="border:none;padding:0;margin:0">
    <?= h($coll['name']) ?>
    <small><?= $total ?> item<?= $total!=1?'s':'' ?></small>
  </h1>
  <div class="flex gap-8">
    <a href="collections.php" class="btn btn-ghost btn-sm">← Collections</a>
    <a href="item_edit.php?coll=<?= $coll_id ?>" class="btn btn-primary btn-sm">+ Add Item</a>
  </div>
</div>

<!-- Search / filter bar -->
<form method="get" action="" class="toolbar">
  <input type="hidden" name="coll"  value="<?= $coll_id ?>">
  <input type="hidden" name="sort"  value="<?= h($sort_col) ?>">
  <input type="hidden" name="dir"   value="<?= h($sort_dir) ?>">
  <input type="text" name="q" class="form-control search-input" placeholder="Search…"
         value="<?= h($search) ?>">
  <button class="btn btn-secondary btn-sm">Search</button>
  <?php if ($search || $tag_id): ?>
    <a href="items.php?coll=<?= $coll_id ?>" class="btn btn-ghost btn-sm">Clear</a>
  <?php endif; ?>
</form>

<?php if ($filter_tag): ?>
  <div class="flex align-center gap-8" style="margin-bottom:16px">
    <span class="text-muted">Filtering by tag:</span>
    <span class="tag-pill"><?= h($filter_tag) ?></span>
    <a href="items.php?coll=<?= $coll_id ?>&q=<?= urlencode($search) ?>" class="btn btn-ghost btn-sm">Remove filter</a>
  </div>
<?php endif; ?>

<?php if (empty($items_data)): ?>
  <div class="card" style="text-align:center;padding:40px">
    <p class="text-muted">No items found.</p>
  </div>
<?php else: ?>
<div class="card" style="padding:0;overflow:hidden">
  <div class="table-wrap">
    <table class="data-table">
      <thead>
        <tr>
          <?php if ($coll['has_images']): ?><th>Image</th><?php endif; ?>
          <?php foreach ($fields as $f): ?>
            <th><?= sort_link(['key' => 'f_'.$f['id'], 'label' => $f['field_name']]) ?></th>
          <?php endforeach; ?>
          <th>Tags</th>
          <th><?= sort_link(['key' => 'created_at', 'label' => 'Added']) ?></th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items_data as $id => $d):
              $row = $d['row']; ?>
        <tr>
          <?php if ($coll['has_images']): ?>
          <td>
            <?php if ($row['image_path']): ?>
              <img src="<?= UPLOAD_URL . h($row['image_path']) ?>" alt="" class="item-thumb">
            <?php else: ?>
              <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
          <?php endif; ?>

          <?php foreach ($fields as $f):
                $val = $d['vals'][$f['id']] ?? ''; ?>
          <td>
            <?php if ($f['field_type'] === 'boolean'): ?>
              <?= $val ? '<span class="bool-yes">✓</span>' : '<span class="bool-no">–</span>' ?>
            <?php else: ?>
              <?= h($val) ?>
            <?php endif; ?>
          </td>
          <?php endforeach; ?>

          <td>
            <div class="tags-wrap">
              <?php foreach ($d['tags'] as $t): ?>
                <a href="<?= items_url(['tag' => $t['id'], 'p' => 1]) ?>" class="tag-pill">
                  <?php if ($t['image_path']): ?>
                    <img src="<?= UPLOAD_URL . h($t['image_path']) ?>" alt="">
                  <?php endif; ?>
                  <?= h($t['name']) ?>
                </a>
              <?php endforeach; ?>
            </div>
          </td>

          <td class="text-muted" style="font-size:.8rem;white-space:nowrap">
            <?= date('d M Y', strtotime($row['created_at'])) ?>
          </td>

          <td class="actions">
            <a href="item_edit.php?id=<?= $id ?>" class="btn btn-ghost btn-sm">Edit</a>
            <form method="post" action="item_delete.php" style="display:inline"
                  onsubmit="return confirm('Delete this item?')">
              <input type="hidden" name="id"   value="<?= $id ?>">
              <input type="hidden" name="coll" value="<?= $coll_id ?>">
              <button class="btn btn-danger btn-sm">Delete</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pagination -->
<?php if ($pages > 1): ?>
<div class="pagination">
  <?php if ($page > 1): ?>
    <a href="<?= items_url(['p' => $page-1]) ?>">‹ Prev</a>
  <?php endif; ?>
  <?php for ($pg = max(1,$page-3); $pg <= min($pages,$page+3); $pg++): ?>
    <?php if ($pg === $page): ?>
      <span class="current"><?= $pg ?></span>
    <?php else: ?>
      <a href="<?= items_url(['p' => $pg]) ?>"><?= $pg ?></a>
    <?php endif; ?>
  <?php endfor; ?>
  <?php if ($page < $pages): ?>
    <a href="<?= items_url(['p' => $page+1]) ?>">Next ›</a>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php page_footer(); ?>
