<?php
require_once __DIR__ . '/db.php';
$me = need(['admin']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf(); $id = (int)$_POST['id'];
  if ($id === (int)$me['id']) flash('You cannot change your own role or status.');
  elseif (in_array($_POST['role'] ?? '', ['admin','technician','student'], true)) { q("UPDATE users SET role=?, active=? WHERE id=?", [$_POST['role'], isset($_POST['active']) ? 1 : 0, $id]); flash('User updated.'); }
  header('Location: users.php'); exit;
}
page_start('Users and roles');
?>
<p class="hint">Students report and rate. Technicians manage assets, plastics and reports. Admins also manage users.</p>
<div class="scroll"><table><thead><tr><th>Name</th><th>Email</th><th>Student no.</th><th>Role and status</th></tr></thead><tbody>
<?php foreach (q("SELECT * FROM users ORDER BY role, name")->fetchAll() as $x): ?>
<tr><td><?= h($x['name']) ?></td><td><?= h($x['email']) ?></td><td><?= h($x['student_no']) ?></td>
<td><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $x['id'] ?>">
  <select name="role" aria-label="Role"><?php foreach (['student','technician','admin'] as $r) echo '<option'.($x['role'] === $r ? ' selected' : '').'>'.$r.'</option>'; ?></select>
  <label><input type="checkbox" name="active" <?= $x['active'] ? 'checked' : '' ?>> Active</label><button>Save</button></form></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php page_end();
