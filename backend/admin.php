<?php
require_once __DIR__ . '/api/config.php';

define('ADMIN_PASSWORD', 'HUManity@Admin2026');
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) $_SESSION['admin'] = true;
    else $error = 'Wrong password';
}
if (isset($_POST['logout'])) { session_destroy(); header('Location: ' . $_SERVER['PHP_SELF']); exit; }
if (!isset($_SESSION['admin'])) { ?>
<!DOCTYPE html><html><head><title>Admin Login</title>
<style>
  body { font-family:sans-serif; display:flex; align-items:center; justify-content:center; height:100vh; margin:0; background:#0f172a; }
  .box { background:#1e293b; padding:40px; border-radius:12px; width:320px; }
  h2 { color:#ffac00; margin:0 0 24px; text-align:center; }
  input[type=password] { width:100%; padding:10px; border-radius:6px; border:1px solid #334155; background:#0f172a; color:#fff; font-size:15px; box-sizing:border-box; }
  button { width:100%; margin-top:14px; padding:11px; background:#ffac00; border:none; border-radius:6px; font-size:15px; font-weight:700; cursor:pointer; }
  .err { color:#f87171; font-size:13px; margin-top:8px; text-align:center; }
</style></head>
<body><div class="box">
  <h2>HUManity Admin</h2>
  <form method="POST">
    <input type="password" name="password" placeholder="Enter password" autofocus>
    <button type="submit">Login</button>
  </form>
  <?php if (isset($error)) echo "<p class='err'>$error</p>"; ?>
</div></body></html>
<?php exit; }

$allowed_tables = ['donations','volunteer_applications','contact_messages','corporate_partnerships','partnership_inquiries','ceo_contacts'];
$db = getDB();

// ── Delete selected rows ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ids'], $_POST['delete_table'])) {
    $table = $_POST['delete_table'];
    $ids   = $_POST['delete_ids'];
    if (in_array($table, $allowed_tables) && is_array($ids) && count($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $db->prepare("DELETE FROM `$table` WHERE id IN ($placeholders)")->execute($ids);
    }
    header('Location: ?tab=' . $table); exit;
}

// ── Delete all ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all_table'])) {
    $table = $_POST['delete_all_table'];
    if (in_array($table, $allowed_tables)) $db->exec("DELETE FROM `$table`");
    header('Location: ?tab=' . $table); exit;
}

// ── CSV Export ───────────────────────────────────────────────────────────────
if (isset($_GET['export'])) {
    $table = $_GET['export'];
    if (!in_array($table, $allowed_tables)) exit('Invalid');
    $rows = $db->query("SELECT * FROM `$table` ORDER BY created_at DESC")->fetchAll();
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $table . '_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    if ($rows) { fputcsv($out, array_keys($rows[0])); foreach ($rows as $r) fputcsv($out, $r); }
    fclose($out); exit;
}

// ── Fetch data ───────────────────────────────────────────────────────────────
$active = $_GET['tab'] ?? 'donations';
if (!in_array($active, $allowed_tables)) $active = 'donations';

$tables = [
    'donations'              => 'Donations',
    'volunteer_applications' => 'Volunteers',
    'contact_messages'       => 'Contact',
    'corporate_partnerships' => 'Corporate',
    'partnership_inquiries'  => 'Partnerships',
    'ceo_contacts'           => 'CEO Contacts',
];

$counts = [];
foreach ($tables as $t => $l) $counts[$t] = $db->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();

$rows = $db->query("SELECT * FROM `$active` ORDER BY created_at DESC")->fetchAll();
$cols = $rows ? array_keys($rows[0]) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>HUManity Admin</title>
<style>
  * { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif; background:#0f172a; color:#e2e8f0; min-height:100vh; }
  header { background:#1e293b; padding:16px 28px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid #334155; }
  header h1 { color:#ffac00; font-size:20px; }
  .logout-btn { background:#334155; color:#e2e8f0; border:none; padding:6px 14px; border-radius:6px; cursor:pointer; font-size:13px; }
  nav { display:flex; gap:6px; padding:16px 28px; flex-wrap:wrap; }
  nav a { padding:8px 16px; border-radius:8px; text-decoration:none; font-size:13px; font-weight:600; background:#1e293b; color:#94a3b8; transition:all .15s; }
  nav a.active, nav a:hover { background:#ffac00; color:#000; }
  .badge { display:inline-block; background:#334155; color:#94a3b8; border-radius:20px; padding:1px 7px; font-size:11px; margin-left:5px; }
  nav a.active .badge { background:rgba(0,0,0,.2); color:#000; }
  .container { padding:0 28px 40px; }
  .toolbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; flex-wrap:wrap; gap:10px; }
  .toolbar-left { font-size:13px; color:#64748b; }
  .toolbar-right { display:flex; gap:8px; align-items:center; }
  .btn { padding:7px 14px; border-radius:7px; font-size:13px; font-weight:600; cursor:pointer; border:none; text-decoration:none; display:inline-flex; align-items:center; gap:5px; }
  .btn-export { background:#166534; color:#4ade80; }
  .btn-export:hover { background:#15803d; }
  .btn-delete-sel { background:#450a0a; color:#f87171; }
  .btn-delete-sel:hover { background:#7f1d1d; }
  .btn-delete-all { background:#3b0764; color:#c084fc; }
  .btn-delete-all:hover { background:#581c87; }
  .btn-refresh { background:#1e293b; color:#94a3b8; }
  .btn-refresh:hover { background:#334155; }
  .btn-confirm-del { background:#dc2626; color:#fff; display:none; }
  .btn-cancel-sel { background:#334155; color:#e2e8f0; display:none; }
  .table-wrap { overflow-x:auto; border-radius:10px; border:1px solid #1e293b; }
  table { width:100%; border-collapse:collapse; font-size:13px; }
  thead tr { background:#1e293b; }
  thead th { padding:11px 14px; text-align:left; color:#94a3b8; font-weight:600; white-space:nowrap; border-bottom:1px solid #334155; }
  th.sno, td.sno { width:50px; text-align:center; color:#475569; }
  th.chk, td.chk { width:40px; text-align:center; display:none; }
  body.selecting th.chk, body.selecting td.chk { display:table-cell; }
  tbody tr { border-bottom:1px solid #1e293b; transition:background .1s; }
  tbody tr:hover { background:#1e293b; }
  tbody tr.selected { background:#1c1917; outline:1px solid #f87171; }
  tbody td { padding:10px 14px; color:#cbd5e1; max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
  .status-completed { color:#4ade80; font-weight:600; }
  .status-pending   { color:#fbbf24; font-weight:600; }
  .status-failed    { color:#f87171; font-weight:600; }
  .empty { text-align:center; padding:60px; color:#475569; }
  input[type=checkbox] { width:15px; height:15px; accent-color:#f87171; cursor:pointer; }
  /* Modal */
  .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.7); z-index:100; align-items:center; justify-content:center; }
  .modal-overlay.show { display:flex; }
  .modal { background:#1e293b; border-radius:12px; padding:32px; max-width:380px; width:90%; text-align:center; }
  .modal h3 { color:#f87171; margin-bottom:12px; font-size:18px; }
  .modal p { color:#94a3b8; font-size:14px; margin-bottom:24px; line-height:1.6; }
  .modal-btns { display:flex; gap:10px; justify-content:center; }
  .modal-btns button { padding:9px 22px; border-radius:7px; font-size:14px; font-weight:600; cursor:pointer; border:none; }
  .m-cancel { background:#334155; color:#e2e8f0; }
  .m-confirm { background:#dc2626; color:#fff; }
</style>
</head>
<body>
<header>
  <h1>🌟 HUManity Admin Dashboard</h1>
  <div style="display:flex;align-items:center;gap:16px;">
    <span style="color:#94a3b8;font-size:13px;">Auto-refreshes every 60s</span>
    <form method="POST" style="display:inline"><button class="logout-btn" name="logout" value="1">Logout</button></form>
  </div>
</header>

<nav>
  <?php foreach ($tables as $t => $label): ?>
    <a href="?tab=<?= $t ?>" class="<?= $active === $t ? 'active' : '' ?>">
      <?= $label ?><span class="badge"><?= $counts[$t] ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<div class="container">
  <div class="toolbar">
    <div class="toolbar-left">
      Showing <strong><?= count($rows) ?></strong> <?= $tables[$active] ?> records &nbsp;·&nbsp; <?= date('d M Y, H:i:s') ?>
    </div>
    <div class="toolbar-right">
      <a href="?tab=<?= $active ?>" class="btn btn-refresh">↻ Refresh</a>
      <a href="?export=<?= $active ?>" class="btn btn-export">⬇ Export CSV</a>
      <?php if (!empty($rows)): ?>
        <button class="btn btn-delete-sel" id="btnStartSelect" onclick="startSelect()">🗑 Delete Selected</button>
        <button class="btn btn-confirm-del" id="btnConfirmDel" onclick="confirmDeleteSelected()">Delete (<span id="selCount">0</span>)</button>
        <button class="btn btn-cancel-sel"  id="btnCancelSel"  onclick="cancelSelect()">Cancel</button>
        <button class="btn btn-delete-all" onclick="document.getElementById('deleteAllModal').classList.add('show')">✕ Delete All</button>
      <?php endif; ?>
    </div>
  </div>

  <?php if (empty($rows)): ?>
    <div class="empty">No records yet</div>
  <?php else: ?>
    <form method="POST" id="deleteForm">
      <input type="hidden" name="delete_table" value="<?= $active ?>">
      <div class="table-wrap">
        <table>
          <thead><tr>
            <th class="chk"><input type="checkbox" id="checkAll" onclick="toggleAll(this)"></th>
            <th class="sno">S.No</th>
            <?php foreach ($cols as $col): ?>
              <th><?= htmlspecialchars($col) ?></th>
            <?php endforeach; ?>
          </tr></thead>
          <tbody>
            <?php foreach ($rows as $i => $row): ?>
              <tr id="row-<?= htmlspecialchars($row['id']) ?>">
                <td class="chk">
                  <input type="checkbox" name="delete_ids[]" value="<?= htmlspecialchars($row['id']) ?>" class="row-chk" onchange="updateCount()">
                </td>
                <td class="sno"><?= $i + 1 ?></td>
                <?php foreach ($row as $key => $val): ?>
                  <td title="<?= htmlspecialchars((string)$val) ?>">
                    <?php if ($key === 'status'): ?>
                      <span class="status-<?= htmlspecialchars($val) ?>"><?= htmlspecialchars((string)$val) ?></span>
                    <?php else: ?>
                      <?= htmlspecialchars((string)$val) ?>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </form>
  <?php endif; ?>
</div>

<!-- Delete All Modal -->
<div class="modal-overlay" id="deleteAllModal">
  <div class="modal">
    <h3>⚠️ Delete All Records?</h3>
    <p>This will permanently delete all <strong><?= count($rows) ?></strong> <?= $tables[$active] ?> records.<br>This cannot be undone.</p>
    <div class="modal-btns">
      <button class="m-cancel" onclick="document.getElementById('deleteAllModal').classList.remove('show')">Cancel</button>
      <form method="POST" style="display:inline">
        <input type="hidden" name="delete_all_table" value="<?= $active ?>">
        <button type="submit" class="m-confirm">Yes, Delete All</button>
      </form>
    </div>
  </div>
</div>

<!-- Delete Selected Modal -->
<div class="modal-overlay" id="deleteSelModal">
  <div class="modal">
    <h3>🗑 Delete Selected?</h3>
    <p>You are about to delete <strong id="modalSelCount">0</strong> selected record(s).<br>This cannot be undone.</p>
    <div class="modal-btns">
      <button class="m-cancel" onclick="document.getElementById('deleteSelModal').classList.remove('show')">Cancel</button>
      <button class="m-confirm" onclick="document.getElementById('deleteForm').submit()">Yes, Delete</button>
    </div>
  </div>
</div>

<script>
  function startSelect() {
    document.body.classList.add('selecting');
    document.getElementById('btnStartSelect').style.display = 'none';
    document.getElementById('btnConfirmDel').style.display = 'inline-flex';
    document.getElementById('btnCancelSel').style.display  = 'inline-flex';
  }
  function cancelSelect() {
    document.body.classList.remove('selecting');
    document.querySelectorAll('.row-chk').forEach(c => { c.checked = false; });
    document.getElementById('checkAll').checked = false;
    document.getElementById('btnStartSelect').style.display = 'inline-flex';
    document.getElementById('btnConfirmDel').style.display  = 'none';
    document.getElementById('btnCancelSel').style.display   = 'none';
    updateCount();
  }
  function toggleAll(master) {
    document.querySelectorAll('.row-chk').forEach(c => c.checked = master.checked);
    updateCount();
  }
  function updateCount() {
    const n = document.querySelectorAll('.row-chk:checked').length;
    document.getElementById('selCount').textContent = n;
    document.getElementById('modalSelCount').textContent = n;
  }
  function confirmDeleteSelected() {
    const n = document.querySelectorAll('.row-chk:checked').length;
    if (n === 0) { alert('No rows selected.'); return; }
    document.getElementById('deleteSelModal').classList.add('show');
  }
  setTimeout(() => location.reload(), 60000);
</script>
</body>
</html>
