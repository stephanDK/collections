<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/layout.php';
require_editor();

$coll_id = (int)($_GET['coll'] ?? 0);
if (!$coll_id) redirect(BASE_URL . '/collections.php');

$coll = $pdo->prepare('SELECT * FROM collections WHERE id=?');
$coll->execute([$coll_id]);
$coll = $coll->fetch();
if (!$coll) redirect(BASE_URL . '/collections.php');

$fields_stmt = $pdo->prepare('SELECT * FROM collection_fields WHERE collection_id=? ORDER BY sort_order');
$fields_stmt->execute([$coll_id]);
$fields = $fields_stmt->fetchAll();

$step    = 'upload';   // upload → preview → done
$preview = null;
$results = null;
$error   = null;

// ── Helper: parse CSV file → array of row data ────────────────────────────────
function parse_csv(string $tmp, array $fields, int $coll_id, $pdo): array {
    $raw = file_get_contents($tmp);
    $raw = ltrim($raw, "\xEF\xBB\xBF");
    if (str_starts_with($raw, 'sep=')) {
        $raw = preg_replace('/^sep=.*\n/i', '', $raw);
    }
    $lines = $raw;
    $tmp2  = tempnam(sys_get_temp_dir(), 'csv_');
    file_put_contents($tmp2, $lines);

    $handle     = fopen($tmp2, 'r');
    $first_line = fgets($handle);
    rewind($handle);
    $sep    = (substr_count($first_line, ';') >= substr_count($first_line, ',')) ? ';' : ',';
    $header = fgetcsv($handle, 0, $sep);
    $header = array_map(fn($h) => mb_strtolower(trim($h)), $header);

    $field_names = [];
    foreach ($fields as $f) $field_names[mb_strtolower($f['field_name'])] = $f;

    $rows        = [];
    $line_num    = 1;
    while (($row = fgetcsv($handle, 0, $sep)) !== false) {
        $line_num++;
        if (!$row || $row === [null]) continue;
        $data = [];
        foreach ($header as $i => $col) $data[$col] = trim($row[$i] ?? '');

        $item_id = isset($data['id']) && $data['id'] !== '' ? (int)$data['id'] : null;

        // Collect field values from CSV
        $field_vals = [];
        foreach ($fields as $f) {
            $key = mb_strtolower($f['field_name']);
            $field_vals[$f['id']] = $data[$key] ?? '';
        }

        // Tags from CSV
        $tag_names = [];
        if (isset($data['tags']) && $data['tags'] !== '') {
            $tag_names = array_values(array_filter(array_map('trim', explode(',', $data['tags']))));
        }

        // Check if id exists and whether anything has actually changed
        $status = 'new';
        if ($item_id !== null) {
            $check = $pdo->prepare('SELECT id FROM items WHERE id=? AND collection_id=?');
            $check->execute([$item_id, $coll_id]);
            if (!$check->fetchColumn()) {
                $status = 'unknown_id';
            } else {
                // Compare field values with database
                $changed = false;
                foreach ($fields as $f) {
                    $db_val = $pdo->prepare('SELECT value FROM item_values WHERE item_id=? AND field_id=?');
                    $db_val->execute([$item_id, $f['id']]);
                    $db_val = (string)($db_val->fetchColumn() ?? '');
                    $csv_val = (string)($field_vals[$f['id']] ?? '');
                    if ($db_val !== $csv_val) { $changed = true; break; }
                }
                // Compare tags with database
                if (!$changed) {
                    $db_tags = $pdo->prepare(
                        'SELECT t.name FROM tags t JOIN item_tags it ON it.tag_id=t.id
                         WHERE it.item_id=? ORDER BY t.name'
                    );
                    $db_tags->execute([$item_id]);
                    $db_tag_names = array_column($db_tags->fetchAll(), 'name');
                    $csv_sorted   = $tag_names;
                    sort($csv_sorted);
                    sort($db_tag_names);
                    if ($csv_sorted !== $db_tag_names) $changed = true;
                }
                $status = $changed ? 'update' : 'unchanged';
            }
        }

        $rows[] = [
            'line'       => $line_num,
            'item_id'    => $item_id,
            'status'     => $status,
            'field_vals' => $field_vals,
            'tag_names'  => $tag_names,
        ];
    }
    fclose($handle);
    unlink($tmp2);
    return $rows;
}

// ── STEP 1: Parse uploaded file → store in session, show preview ──────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'preview') {
    if (empty($_FILES['csv_file']['tmp_name'])) {
        $error = 'Please select a CSV file.';
        $step  = 'upload';
    } else {
        $rows = parse_csv($_FILES['csv_file']['tmp_name'], $fields, $coll_id, $pdo);
        if (empty($rows)) {
            $error = 'No data rows found in the CSV file.';
            $step  = 'upload';
        } else {
            // Store parsed rows in session
            $_SESSION['import_preview'] = [
                'coll_id' => $coll_id,
                'rows'    => $rows,
            ];

            $preview = [
                'rows'      => $rows,
                'new'       => count(array_filter($rows, fn($r) => $r['status'] === 'new')),
                'update'    => count(array_filter($rows, fn($r) => $r['status'] === 'update')),
                'unchanged' => count(array_filter($rows, fn($r) => $r['status'] === 'unchanged')),
                'unknown'   => count(array_filter($rows, fn($r) => $r['status'] === 'unknown_id')),
            ];
            $step = 'preview';
        }
    }
}

// ── STEP 2: Commit ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'commit') {
    $stored = $_SESSION['import_preview'] ?? null;
    if (!$stored || $stored['coll_id'] !== $coll_id) {
        $error = 'Session expired — please upload the file again.';
        $step  = 'upload';
    } else {
        $rows    = $stored['rows'];
        $created = 0; $updated = 0; $skipped = 0; $errors = [];

        $pdo->beginTransaction();
        try {
            foreach ($rows as $r) {
                if ($r['status'] === 'unknown_id') {
                    $errors[] = "Line {$r['line']}: id {$r['item_id']} not found — skipped.";
                    $skipped++;
                    continue;
                }
                if ($r['status'] === 'unchanged') {
                    $skipped++;
                    continue;
                }

                if ($r['status'] === 'new') {
                    $pdo->prepare('INSERT INTO items (collection_id, created_by) VALUES (?,?)')
                        ->execute([$coll_id, $_SESSION['user_id']]);
                    $item_id = (int)$pdo->lastInsertId();
                    $created++;
                } else {
                    $item_id = $r['item_id'];
                    $pdo->prepare('UPDATE items SET updated_at=NOW() WHERE id=?')->execute([$item_id]);
                    $updated++;
                }

                // Field values
                foreach ($fields as $f) {
                    $val = $r['field_vals'][$f['id']] ?? '';
                    if ($f['field_type'] === 'boolean') {
                        $val = in_array(strtolower($val), ['1','true','yes','ja','x']) ? '1' : '0';
                    } elseif ($f['field_type'] === 'number') {
                        $val = is_numeric($val) ? $val : '';
                    }
                    $pdo->prepare(
                        'INSERT INTO item_values (item_id, field_id, value) VALUES (?,?,?)
                         ON DUPLICATE KEY UPDATE value=VALUES(value)'
                    )->execute([$item_id, $f['id'], $val]);
                }

                // Tags
                $pdo->prepare('DELETE FROM item_tags WHERE item_id=?')->execute([$item_id]);
                foreach ($r['tag_names'] as $tname) {
                    $tag = $pdo->prepare('SELECT id FROM tags WHERE name=? AND (collection_id IS NULL OR collection_id=?)');
                    $tag->execute([$tname, $coll_id]);
                    $tid = $tag->fetchColumn();
                    if (!$tid) {
                        $pdo->prepare('INSERT IGNORE INTO tags (name, collection_id, created_by) VALUES (?,?,?)')
                            ->execute([$tname, $coll_id, $_SESSION['user_id']]);
                        $tid = (int)$pdo->lastInsertId();
                    }
                    if ($tid) {
                        $pdo->prepare('INSERT IGNORE INTO item_tags (item_id, tag_id) VALUES (?,?)')
                            ->execute([$item_id, $tid]);
                    }
                }
            }
            $pdo->commit();
            unset($_SESSION['import_preview']);
            $results = compact('created', 'updated', 'skipped', 'errors');
            $step    = 'done';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Import failed: ' . $e->getMessage();
            $step  = 'upload';
        }
    }
}

// ── Cancel: clear session ─────────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'cancel') {
    unset($_SESSION['import_preview']);
    redirect(BASE_URL . '/item_import.php?coll=' . $coll_id);
}

page_header('Bulk Edit – ' . $coll['name'], 'collections');
?>

<div class="flex align-center justify-between" style="margin-bottom:24px;flex-wrap:wrap;gap:10px">
  <h1 class="page-title mt-0" style="border:none;padding:0;margin:0">
    Bulk Edit <small><?= h($coll['name']) ?></small>
  </h1>
  <a href="items.php?coll=<?= $coll_id ?>" class="btn btn-ghost btn-sm">← Back to items</a>
</div>

<?php if ($error): ?>
  <div class="flash flash--error"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($step === 'done' && $results): ?>
<!-- ── Done ── -->
<div class="card" style="border-left:4px solid var(--accent2);max-width:560px">
  <h3 style="font-family:var(--font-head);margin-bottom:16px">Import complete</h3>
  <div style="display:flex;flex-direction:column;gap:6px;font-size:.95rem">
    <div>✅ <strong><?= $results['created'] ?></strong> item<?= $results['created']!=1?'s':'' ?> created</div>
    <div>✏️ <strong><?= $results['updated'] ?></strong> item<?= $results['updated']!=1?'s':'' ?> updated</div>
    <?php if ($results['skipped']): ?>
    <div>⚠️ <strong><?= $results['skipped'] ?></strong> row<?= $results['skipped']!=1?'s':'' ?> skipped</div>
    <?php endif; ?>
  </div>
  <?php if ($results['errors']): ?>
  <ul style="margin-top:10px;color:var(--accent);font-size:.83rem;padding-left:18px">
    <?php foreach ($results['errors'] as $err): ?>
      <li><?= h($err) ?></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <div style="margin-top:20px;display:flex;gap:10px">
    <a href="items.php?coll=<?= $coll_id ?>" class="btn btn-primary">View items</a>
    <a href="item_import.php?coll=<?= $coll_id ?>" class="btn btn-ghost">Import another file</a>
  </div>
</div>

<?php elseif ($step === 'preview' && $preview): ?>
<!-- ── Preview ── -->
<div class="card" style="margin-bottom:20px;border-left:4px solid #e8a030">
  <h3 style="font-family:var(--font-head);margin-bottom:14px">Preview — nothing has been saved yet</h3>
  <div style="display:flex;gap:24px;flex-wrap:wrap;margin-bottom:20px">
    <div class="preview-stat preview-stat--new">
      <span class="preview-stat__num"><?= $preview['new'] ?></span>
      <span class="preview-stat__label">New items</span>
    </div>
    <div class="preview-stat preview-stat--update">
      <span class="preview-stat__num"><?= $preview['update'] ?></span>
      <span class="preview-stat__label">Updates</span>
    </div>
    <div class="preview-stat preview-stat--unchanged">
      <span class="preview-stat__num"><?= $preview['unchanged'] ?></span>
      <span class="preview-stat__label">Unchanged</span>
    </div>
    <?php if ($preview['unknown']): ?>
    <div class="preview-stat preview-stat--error">
      <span class="preview-stat__num"><?= $preview['unknown'] ?></span>
      <span class="preview-stat__label">Unknown IDs (will be skipped)</span>
    </div>
    <?php endif; ?>
  </div>

  <!-- Row table -->
  <div class="table-wrap" style="max-height:340px;overflow-y:auto;margin-bottom:20px">
    <table class="data-table" style="font-size:.82rem">
      <thead>
        <tr>
          <th>Line</th>
          <th>Status</th>
          <?php foreach ($fields as $f): ?>
            <th><?= h($f['field_name']) ?></th>
          <?php endforeach; ?>
          <th>Tags</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($preview['rows'] as $r): ?>
        <tr>
          <td class="text-muted"><?= $r['line'] ?></td>
          <td>
            <?php if ($r['status'] === 'new'): ?>
              <span class="status-pill status-pill--new">+ New</span>
            <?php elseif ($r['status'] === 'update'): ?>
              <span class="status-pill status-pill--update">✏ Update #<?= $r['item_id'] ?></span>
            <?php elseif ($r['status'] === 'unchanged'): ?>
              <span class="status-pill status-pill--unchanged">– Unchanged</span>
            <?php else: ?>
              <span class="status-pill status-pill--error">⚠ Skip #<?= $r['item_id'] ?></span>
            <?php endif; ?>
          </td>
          <?php foreach ($fields as $f): ?>
            <td><?= h($r['field_vals'][$f['id']] ?? '') ?></td>
          <?php endforeach; ?>
          <td><?= h(implode(', ', $r['tag_names'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <form method="post" style="display:flex;gap:10px;flex-wrap:wrap">
    <button type="submit" name="action" value="commit" class="btn btn-primary">
      ✓ Commit import
    </button>
    <button type="submit" name="action" value="cancel" class="btn btn-ghost">
      ✕ Cancel
    </button>
  </form>
</div>

<?php else: ?>
<!-- ── Upload form + format reference ── -->
<div class="bulk-grid">

  <div class="card">
    <div class="step-badge">Step 1</div>
    <h2 style="font-family:var(--font-head);font-size:1.3rem;margin:10px 0 6px">Download CSV</h2>
    <p class="text-muted" style="font-size:.9rem;margin-bottom:20px">
      Download the current items as a CSV file, open it in Excel, add or edit rows, then upload it in Step 2.
    </p>
    <a href="item_export.php?coll=<?= $coll_id ?>" class="btn btn-secondary">↓ Download CSV</a>

    <div style="margin-top:24px;padding-top:20px;border-top:1px solid var(--border)">
      <h4 style="font-family:var(--font-head);margin-bottom:10px">File format</h4>
      <p class="text-muted" style="font-size:.83rem;margin-bottom:8px">Columns for <strong><?= h($coll['name']) ?></strong>:</p>
      <code class="code-block">id;<?= implode(';', array_map(fn($f) => h($f['field_name']), $fields)) ?>;tags</code>

      <p class="text-muted" style="font-size:.83rem;margin:12px 0 6px">Example rows:</p>
      <code class="code-block">
        ;1988-04;;1988<br>
        ;1988-05;;1988,Sjælden<br>
        42;1988-06;Fin stand;1988
      </code>

      <ul style="font-size:.82rem;color:var(--muted);line-height:1.9;padding-left:18px;margin-top:14px">
        <li>Column separator: <code>;</code></li>
        <li>Tag separator (within tags cell): <code>,</code></li>
        <li>Empty <code>id</code> = create new item</li>
        <li>Filled <code>id</code> = update existing item</li>
        <li>Boolean: <code>1</code>, <code>true</code>, <code>yes</code>, <code>ja</code> or <code>x</code> = Yes</li>
        <li>Unknown tags are auto-created as collection tags</li>
        <li>Images are not included — upload per item manually</li>
      </ul>
    </div>
  </div>

  <div class="card">
    <div class="step-badge">Step 2</div>
    <h2 style="font-family:var(--font-head);font-size:1.3rem;margin:10px 0 6px">Upload CSV</h2>
    <p class="text-muted" style="font-size:.9rem;margin-bottom:20px">
      Upload your edited file. You will see a preview before anything is saved.
    </p>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="preview">
      <div class="form-group">
        <label>CSV File</label>
        <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv" required>
      </div>
      <button class="btn btn-primary">→ Preview import</button>
    </form>
  </div>

</div>
<?php endif; ?>

<style>
.bulk-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
}
.bulk-grid > .card { margin-top: 0 !important; }
@media (max-width: 700px) { .bulk-grid { grid-template-columns: 1fr; } }

.step-badge {
  display: inline-block;
  background: var(--text);
  color: #f5f0e8;
  font-size: .72rem;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
  padding: 3px 10px;
  border-radius: 12px;
}
.code-block {
  display: block;
  background: #f0ebe0;
  padding: 10px 12px;
  border-radius: 4px;
  font-size: .8rem;
  line-height: 1.8;
  word-break: break-all;
}

/* Preview stats */
.preview-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 12px 20px;
  border-radius: var(--radius);
  min-width: 100px;
}
.preview-stat__num {
  font-family: var(--font-head);
  font-size: 2rem;
  font-weight: 700;
  line-height: 1;
}
.preview-stat__label { font-size: .78rem; margin-top: 4px; color: var(--muted); }
.preview-stat--new       { background: #eafaf2; }
.preview-stat--update    { background: #edf4ff; }
.preview-stat--unchanged { background: #f0ebe0; }
.preview-stat--error     { background: #fdf0ed; }
.preview-stat--new       .preview-stat__num { color: var(--accent2); }
.preview-stat--update    .preview-stat__num { color: #2a5baa; }
.preview-stat--unchanged .preview-stat__num { color: var(--muted); }
.preview-stat--error     .preview-stat__num { color: var(--accent); }

/* Status pills */
.status-pill {
  display: inline-block;
  font-size: .75rem;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 10px;
  white-space: nowrap;
}
.status-pill--new       { background: #eafaf2; color: var(--accent2); }
.status-pill--update    { background: #edf4ff; color: #2a5baa; }
.status-pill--unchanged { background: #f0ebe0; color: var(--muted); }
.status-pill--error     { background: #fdf0ed; color: var(--accent); }
</style>

<?php page_footer(); ?>
