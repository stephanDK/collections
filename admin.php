<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/layout.php';
require_admin();

// ── Handle POST actions ───────────────────────────────────────────────────────

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// --- User actions ---
if ($action === 'add_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $uname = trim($_POST['username'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $adm   = isset($_POST['is_admin']) ? 1 : 0;
    if ($uname && strlen($pass) >= 4) {
        try {
            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, is_admin) VALUES (?,?,?)');
            $stmt->execute([$uname, password_hash($pass, PASSWORD_BCRYPT), $adm]);
            flash('success', "User '$uname' created.");
        } catch (PDOException $e) {
            flash('error', 'Username already exists.');
        }
    } else {
        flash('error', 'Username required and password must be at least 4 characters.');
    }
    redirect(BASE_URL . '/admin.php');
}

if ($action === 'delete_user' && isset($_POST['user_id'])) {
    $uid = (int)$_POST['user_id'];
    if ($uid === (int)$_SESSION['user_id']) {
        flash('error', 'You cannot delete yourself.');
    } else {
        $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
        flash('success', 'User deleted.');
    }
    redirect(BASE_URL . '/admin.php');
}

if ($action === 'toggle_admin' && isset($_POST['user_id'])) {
    $uid = (int)$_POST['user_id'];
    $pdo->prepare('UPDATE users SET is_admin = 1 - is_admin WHERE id = ?')->execute([$uid]);
    flash('success', 'Admin status updated.');
    redirect(BASE_URL . '/admin.php');
}

// --- Collection actions ---
if ($action === 'add_collection' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = trim($_POST['coll_name'] ?? '');
    $desc   = trim($_POST['coll_desc'] ?? '');
    $images = isset($_POST['has_images']) ? 1 : 0;

    // field arrays
    $fnames = $_POST['field_name']  ?? [];
    $ftypes = $_POST['field_type']  ?? [];

    if ($name) {
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO collections (name, description, has_images) VALUES (?,?,?)')
            ->execute([$name, $desc, $images]);
        $cid = $pdo->lastInsertId();
        foreach ($fnames as $i => $fn) {
            $fn = trim($fn);
            if ($fn === '') continue;
            $ft = in_array($ftypes[$i] ?? '', ['text','number','boolean']) ? $ftypes[$i] : 'text';
            $pdo->prepare('INSERT INTO collection_fields (collection_id, field_name, field_type, sort_order) VALUES (?,?,?,?)')
                ->execute([$cid, $fn, $ft, $i]);
        }
        $pdo->commit();
        flash('success', "Collection '$name' created.");
    } else {
        flash('error', 'Collection name is required.');
    }
    redirect(BASE_URL . '/admin.php');
}

if ($action === 'delete_collection' && isset($_POST['coll_id'])) {
    $cid = (int)$_POST['coll_id'];
    $pdo->prepare('DELETE FROM collections WHERE id = ?')->execute([$cid]);
    flash('success', 'Collection deleted.');
    redirect(BASE_URL . '/admin.php');
}

if ($action === 'edit_collection' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid    = (int)($_POST['coll_id'] ?? 0);
    $name   = trim($_POST['coll_name'] ?? '');
    $desc   = trim($_POST['coll_desc'] ?? '');
    $images = isset($_POST['has_images']) ? 1 : 0;
    $fnames = $_POST['field_name']  ?? [];
    $ftypes = $_POST['field_type']  ?? [];
    $fids   = $_POST['field_id']    ?? [];

    if ($cid && $name) {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE collections SET name=?, description=?, has_images=? WHERE id=?')
            ->execute([$name, $desc, $images, $cid]);

        // keep track of submitted field ids to detect deletions
        $submittedIds = [];
        foreach ($fnames as $i => $fn) {
            $fn = trim($fn);
            if ($fn === '') continue;
            $ft  = in_array($ftypes[$i] ?? '', ['text','number','boolean']) ? $ftypes[$i] : 'text';
            $fid = (int)($fids[$i] ?? 0);
            if ($fid) {
                $pdo->prepare('UPDATE collection_fields SET field_name=?, field_type=?, sort_order=? WHERE id=? AND collection_id=?')
                    ->execute([$fn, $ft, $i, $fid, $cid]);
                $submittedIds[] = $fid;
            } else {
                $pdo->prepare('INSERT INTO collection_fields (collection_id, field_name, field_type, sort_order) VALUES (?,?,?,?)')
                    ->execute([$cid, $fn, $ft, $i]);
                $submittedIds[] = (int)$pdo->lastInsertId();
            }
        }
        // delete removed fields
        if ($submittedIds) {
            $placeholders = implode(',', array_fill(0, count($submittedIds), '?'));
            $pdo->prepare("DELETE FROM collection_fields WHERE collection_id=? AND id NOT IN ($placeholders)")
                ->execute(array_merge([$cid], $submittedIds));
        } else {
            $pdo->prepare('DELETE FROM collection_fields WHERE collection_id=?')->execute([$cid]);
        }
        $pdo->commit();
        flash('success', 'Collection updated.');
    }
    redirect(BASE_URL . '/admin.php');
}


// --- Global tag actions (admin only) ---
if ($action === 'add_global_tag' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['tag_name'] ?? '');
    if ($name) {
        $img = upload_image('tag_image', 'tags');
        try {
            $pdo->prepare('INSERT INTO tags (name, collection_id, image_path, created_by) VALUES (?, NULL, ?, ?)')
                ->execute([$name, $img, $_SESSION['user_id']]);
            flash('success', "Global tag '$name' created.");
        } catch (PDOException $e) {
            flash('error', 'A global tag with that name already exists.');
        }
    } else {
        flash('error', 'Tag name is required.');
    }
    redirect(BASE_URL . '/admin.php#global-tags');
}

if ($action === 'delete_global_tag' && isset($_POST['tag_id'])) {
    $tid = (int)$_POST['tag_id'];
    $tag = $pdo->prepare('SELECT image_path, collection_id FROM tags WHERE id=?');
    $tag->execute([$tid]);
    $tag = $tag->fetch();
    if ($tag && $tag['collection_id'] === null) { // only global tags
        if ($tag['image_path']) {
            $f = UPLOAD_DIR . $tag['image_path'];
            if (file_exists($f)) unlink($f);
        }
        $pdo->prepare('DELETE FROM tags WHERE id=?')->execute([$tid]);
        flash('success', 'Global tag deleted.');
    }
    redirect(BASE_URL . '/admin.php#global-tags');
}

// ── Fetch data ────────────────────────────────────────────────────────────────
$users       = $pdo->query('SELECT * FROM users ORDER BY username')->fetchAll();
$collections = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM items i WHERE i.collection_id=c.id) AS item_count FROM collections c ORDER BY c.name')->fetchAll();

// For editing: load a single collection
$edit_coll        = null;
$edit_fields      = [];
$edit_field_counts = []; // how many items have a value for each field
if (isset($_GET['edit_coll'])) {
    $ecid = (int)$_GET['edit_coll'];
    $edit_coll = $pdo->prepare('SELECT * FROM collections WHERE id=?');
    $edit_coll->execute([$ecid]);
    $edit_coll = $edit_coll->fetch();
    if ($edit_coll) {
        $ef = $pdo->prepare('SELECT * FROM collection_fields WHERE collection_id=? ORDER BY sort_order');
        $ef->execute([$ecid]);
        $edit_fields = $ef->fetchAll();

        // Count non-empty values per field
        foreach ($edit_fields as $ef_row) {
            $cnt = $pdo->prepare(
                "SELECT COUNT(*) FROM item_values
                 WHERE field_id=? AND value IS NOT NULL AND value != '' AND value != '0'"
            );
            $cnt->execute([$ef_row['id']]);
            $edit_field_counts[$ef_row['id']] = (int)$cnt->fetchColumn();
        }
    }
}


// Global tags
$global_tags = $pdo->query(
    'SELECT t.*, u.username,
            (SELECT COUNT(*) FROM item_tags it WHERE it.tag_id=t.id) AS item_count
     FROM tags t LEFT JOIN users u ON u.id=t.created_by
     WHERE t.collection_id IS NULL
     ORDER BY t.name'
)->fetchAll();

// ── Render ───────────────────────────────────────────────────────────────────
page_header('Admin', 'admin');
?>

<h1 class="page-title">Admin Panel</h1>

<div class="admin-grid">

  <!-- ── Users ───────────────────────────────────────────── -->
  <div>
    <div class="card">
      <h2 style="font-family:var(--font-head);margin-bottom:16px">Users</h2>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Username</th><th>Role</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td><?= h($u['username']) ?></td>
            <td><?= $u['is_admin'] ? '<span class="bool-yes">Admin</span>' : '<span class="bool-no">User</span>' ?></td>
            <td class="actions">
              <?php if ($u['id'] != $_SESSION['user_id']): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="action" value="toggle_admin">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button class="btn btn-ghost btn-sm"><?= $u['is_admin'] ? 'Remove admin' : 'Make admin' ?></button>
              </form>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete user <?= h($u['username']) ?>?')">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                <button class="btn btn-danger btn-sm">Delete</button>
              </form>
              <?php else: ?>
              <span class="text-muted">(you)</span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <h3 style="font-family:var(--font-head);margin:20px 0 12px">Add User</h3>
      <form method="post">
        <input type="hidden" name="action" value="add_user">
        <div class="form-group">
          <label>Username</label>
          <input type="text" name="username" class="form-control" required>
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" class="form-control" required minlength="4">
        </div>
        <div class="form-group" style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" name="is_admin" id="chk_admin">
          <label for="chk_admin" style="margin:0;text-transform:none;font-size:.95rem;font-weight:400">Make admin</label>
        </div>
        <button class="btn btn-primary">Create User</button>
      </form>
    </div>
  </div>

  <!-- ── Collections ─────────────────────────────────────── -->
  <div>
    <div class="card">
      <h2 style="font-family:var(--font-head);margin-bottom:16px">Collections</h2>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Name</th><th>Items</th><th>Images</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($collections as $c): ?>
          <tr>
            <td><strong><?= h($c['name']) ?></strong></td>
            <td><?= $c['item_count'] ?></td>
            <td><?= $c['has_images'] ? '<span class="bool-yes">Yes</span>' : '<span class="bool-no">No</span>' ?></td>
            <td class="actions">
              <a href="?edit_coll=<?= $c['id'] ?>" class="btn btn-ghost btn-sm">Edit</a>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete collection and all its items?')">
                <input type="hidden" name="action" value="delete_collection">
                <input type="hidden" name="coll_id" value="<?= $c['id'] ?>">
                <button class="btn btn-danger btn-sm">Delete</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Collection form (add or edit) -->
    <div class="card mt-16">
      <?php if ($edit_coll): ?>
        <h3 style="font-family:var(--font-head);margin-bottom:16px">Edit Collection</h3>
        <form method="post">
          <input type="hidden" name="action"  value="edit_collection">
          <input type="hidden" name="coll_id" value="<?= $edit_coll['id'] ?>">
      <?php else: ?>
        <h3 style="font-family:var(--font-head);margin-bottom:16px">New Collection</h3>
        <form method="post">
          <input type="hidden" name="action" value="add_collection">
      <?php endif; ?>

        <div class="form-group">
          <label>Collection Name</label>
          <input type="text" name="coll_name" class="form-control" required
                 value="<?= h($edit_coll['name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label>Description</label>
          <textarea name="coll_desc" class="form-control"><?= h($edit_coll['description'] ?? '') ?></textarea>
        </div>
        <div class="form-group" style="display:flex;align-items:center;gap:8px">
          <input type="checkbox" name="has_images" id="chk_images" <?= !empty($edit_coll['has_images']) ? 'checked' : '' ?>>
          <label for="chk_images" style="margin:0;text-transform:none;font-size:.95rem;font-weight:400">
            Items have images
          </label>
        </div>

        <h4 style="margin:16px 0 8px;font-family:var(--font-head)">Fields</h4>
        <ul id="fields-list">
          <?php foreach ($edit_fields as $i => $ef):
                $count = $edit_field_counts[$ef['id']] ?? 0;
          ?>
          <li data-field-id="<?= $ef['id'] ?>"
              data-field-name="<?= h($ef['field_name']) ?>"
              data-field-type="<?= $ef['field_type'] ?>"
              data-value-count="<?= $count ?>">
            <input type="hidden" name="field_id[]" value="<?= $ef['id'] ?>">
            <input type="text" name="field_name[]" class="form-control field-name" placeholder="Field name"
                   value="<?= h($ef['field_name']) ?>" required>
            <select name="field_type[]" class="form-control field-type" style="width:120px"
                    data-original-type="<?= $ef['field_type'] ?>"
                    onchange="warnTypeChange(this, <?= $count ?>, '<?= h($ef['field_name']) ?>')">
              <?php foreach (['text','number','boolean'] as $t): ?>
              <option value="<?= $t ?>" <?= $ef['field_type']===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if ($count > 0): ?>
            <span class="field-count-badge" title="<?= $count ?> item<?= $count!=1?'s have':'has' ?> a value here">
              <?= $count ?>
            </span>
            <?php endif; ?>
            <button type="button" class="btn btn-danger btn-sm"
                    onclick="confirmDeleteField(this, <?= $ef['id'] ?>, '<?= h($ef['field_name']) ?>', <?= $count ?>)">✕</button>
            <?php if ($count > 0): ?>
            <div class="field-warning" id="warn-<?= $ef['id'] ?>" style="display:none"></div>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
        <button type="button" class="btn btn-ghost btn-sm" onclick="addField()">+ Add Field</button>

        <div style="margin-top:20px;display:flex;gap:10px">
          <button class="btn btn-primary"><?= $edit_coll ? 'Save Changes' : 'Create Collection' ?></button>
          <?php if ($edit_coll): ?>
          <a href="admin.php" class="btn btn-ghost">Cancel</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

</div><!-- /admin-grid -->

<style>
#fields-list li {
  flex-wrap: wrap;
  row-gap: 6px;
}
.field-count-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: #e8a87c;
  color: #5a2800;
  font-size: .75rem;
  font-weight: 700;
  min-width: 22px;
  height: 22px;
  border-radius: 11px;
  padding: 0 6px;
  cursor: default;
}
.field-warning {
  width: 100%;
  background: #fdf3e7;
  border: 1px solid #e8a87c;
  border-radius: 4px;
  padding: 7px 10px;
  font-size: .83rem;
  color: #7a3800;
  margin-top: 2px;
}
.field-warning strong { color: #b5451b; }
</style>

<script>
function addField() {
  const li = document.createElement('li');
  li.innerHTML = `
    <input type="hidden" name="field_id[]" value="0">
    <input type="text" name="field_name[]" class="form-control field-name" placeholder="Field name" required>
    <select name="field_type[]" class="form-control field-type" style="width:120px">
      <option value="text">Text</option>
      <option value="number">Number</option>
      <option value="boolean">Boolean</option>
    </select>
    <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('li').remove()">✕</button>
  `;
  document.getElementById('fields-list').appendChild(li);
}

function confirmDeleteField(btn, fieldId, fieldName, valueCount) {
  if (valueCount > 0) {
    const msg = `⚠️ "${fieldName}" has values in ${valueCount} item${valueCount !== 1 ? 's' : ''}.\n\nDeleting this field will permanently erase all those values.\n\nAre you sure?`;
    if (!confirm(msg)) return;
  }
  btn.closest('li').remove();
}

function warnTypeChange(select, valueCount, fieldName) {
  if (valueCount === 0) return;
  const li      = select.closest('li');
  const fieldId = li.dataset.fieldId;
  const origType = select.dataset.originalType;
  const newType  = select.value;
  let warnDiv = document.getElementById('warn-' + fieldId);

  if (!warnDiv) {
    warnDiv = document.createElement('div');
    warnDiv.className = 'field-warning';
    warnDiv.id = 'warn-' + fieldId;
    li.appendChild(warnDiv);
  }

  if (newType === origType) {
    warnDiv.style.display = 'none';
    return;
  }

  let msg = '';
  if (origType === 'boolean' && newType !== 'boolean') {
    msg = `<strong>Note:</strong> "${fieldName}" has ${valueCount} item${valueCount!==1?'s':''} with boolean values (0/1). Changing to <em>${newType}</em> will keep the raw data but display it as text.`;
  } else if (newType === 'boolean') {
    msg = `<strong>Note:</strong> Changing "${fieldName}" to <em>Boolean</em> — existing text/number values will be treated as true/false (empty or "0" = No, anything else = Yes).`;
  } else if (origType === 'text' && newType === 'number') {
    msg = `<strong>Note:</strong> "${fieldName}" has ${valueCount} item${valueCount!==1?'s':''} with text values. Items where the value is not a valid number will show an empty field in forms.`;
  } else {
    msg = `<strong>Note:</strong> "${fieldName}" has ${valueCount} item${valueCount!==1?'s':''} with existing values. The data is preserved but may display differently as <em>${newType}</em>.`;
  }

  warnDiv.innerHTML = msg;
  warnDiv.style.display = 'block';
}
</script>


<!-- ── Global Tags ─────────────────────────────────────────────────────── -->
<div class="card mt-24" id="global-tags">
  <h2 style="font-family:var(--font-head);margin-bottom:16px">Global Tags
    <span class="text-muted" style="font-size:.9rem;font-weight:400"> — visible across all collections</span>
  </h2>

  <div style="display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start">
    <div>
      <?php if (empty($global_tags)): ?>
        <p class="text-muted">No global tags yet.</p>
      <?php else: ?>
      <div class="table-wrap">
        <table class="data-table">
          <thead><tr><th>Tag</th><th>Used in</th><th>Created by</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($global_tags as $t): ?>
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:8px">
                <?php if ($t['image_path']): ?>
                  <img src="<?= UPLOAD_URL . h($t['image_path']) ?>"
                       style="width:28px;height:28px;border-radius:50%;object-fit:cover;border:1px solid var(--border)">
                <?php else: ?>
                  <span style="width:28px;height:28px;background:#e8e0d0;border-radius:50%;display:inline-block"></span>
                <?php endif; ?>
                <strong><?= h($t['name']) ?></strong>
              </div>
            </td>
            <td class="text-muted"><?= $t['item_count'] ?> item<?= $t['item_count']!=1?'s':'' ?></td>
            <td class="text-muted"><?= h($t['username'] ?? '—') ?></td>
            <td class="actions">
              <a href="tag_view.php?id=<?= $t['id'] ?>" class="btn btn-ghost btn-sm">View</a>
              <form method="post" onsubmit="return confirm('Delete global tag <?= h($t['name']) ?>? It will be removed from all items.')">
                <input type="hidden" name="action" value="delete_global_tag">
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

    <div class="card" style="background:#f5f0e8">
      <h3 style="font-family:var(--font-head);margin-bottom:14px">Add Global Tag</h3>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_global_tag">
        <div class="form-group">
          <label>Tag Name</label>
          <input type="text" name="tag_name" class="form-control" required placeholder="e.g. Rare, Favourite">
        </div>
        <div class="form-group">
          <label>Image (optional)</label>
          <input type="file" name="tag_image" class="form-control" accept="image/*">
        </div>
        <button class="btn btn-primary">Create Global Tag</button>
      </form>
    </div>
  </div>
</div>

<?php page_footer(); ?>
