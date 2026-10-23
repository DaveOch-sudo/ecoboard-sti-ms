<?php
require_once __DIR__ . '/db.php';
if ((int)q("SELECT COUNT(*) FROM users")->fetchColumn() > 0) { header('Location: login.php'); exit; }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  $n = trim($_POST['name'] ?? ''); $e = strtolower(trim($_POST['email'] ?? '')); $p = $_POST['password'] ?? '';
  if ($n === '' || !filter_var($e, FILTER_VALIDATE_EMAIL) || strlen($p) < 8) $err = 'Enter a name, a valid email and a password of 8 or more characters.';
  else { q("INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,'admin')", [$n,$e,password_hash($p,PASSWORD_DEFAULT)]); flash('Admin created. Log in.'); header('Location: login.php'); exit; }
}
page_start('Create the first admin');
?>
<form method="post" class="card form"><?= csrf_field() ?>
  <label>Full name <input name="name" required></label>
  <label>Email <input name="email" type="email" required></label>
  <label>Password (8+ characters) <input name="password" type="password" minlength="8" required></label>
  <button>Create admin</button><p class="err"><?= h($err) ?></p>
</form>
<?php page_end();
