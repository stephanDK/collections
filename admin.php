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

// ── Fetch data ────────────────────────────────────────────────────────────────
$users       = $pdo->query('SELECT * FROM users ORDER BY username')->fetchAll();
$collections = $pdo->query('SELECT c.*, (SELECT COUNT(*) FROM items i WHERE i.collection_id=c.id) AS item_count FROM collections c ORDER BY c.name')->fetchAll();

// For editing: load a single collection
$edit_coll   = null;
$edit_fields = [];
if (isset($_GET['edit_coll'])) {
    $ecid = (int)$_GET['edit_coll'];
    $edit_coll = $pdo->prepare('SELECT * FROM collections WHERE id=?');
    $edit_coll->execute([$ecid]);
    $edit_coll = $edit_coll->fetch();
    if ($edit_coll) {
        $ef = $pdo->prepare('SELECT * FROM collection_fields WHERE collection_id=? ORDER BY sort_order');
        $ef->execute([$ecid]);
        $edit_fields = $ef->fetchAll();
    }
}

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
          <?php foreach ($edit_fields as $i => $ef): ?>
          <li>
            <input type="hidden" name="field_id[]" value="<?= $ef['id'] ?>">
            <input type="text" name="field_name[]" class="form-control field-name" placeholder="Field name"
                   value="<?= h($ef['field_name']) ?>" required>
            <select name="field_type[]" class="form-control" style="width:120px">
              <?php foreach (['text','number','boolean'] as $t): ?>
              <option value="<?= $t ?>" <?= $ef['field_type']===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
              <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('li').remove()">✕</button>
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

<script>
function addField() {
  const li = document.createElement('li');
  li.innerHTML = `
    <input type="hidden" name="field_id[]" value="0">
    <input type="text" name="field_name[]" class="form-control field-name" placeholder="Field name" required>
    <select name="field_type[]" class="form-control" style="width:120px">
      <option value="text">Text</option>
      <option value="number">Number</option>
      <option value="boolean">Boolean</option>
    </select>
    <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('li').remove()">✕</button>
  `;
  document.getElementById('fields-list').appendChild(li);
}
</script>

<?php page_footer(); ?>
