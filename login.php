<?php
require_once __DIR__ . '/db.php';
if ((int)q("SELECT COUNT(*) FROM users")->fetchColumn() === 0) { header('Location: install.php'); exit; }
if (me()) { header('Location: dashboard.php'); exit; }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  if (time() - ($_SESSION['t0'] ?? 0) >= 300) $_SESSION['tries'] = 0;
  if (($_SESSION['tries'] ?? 0) >= 5) $err = 'Too many attempts. Wait 5 minutes and try again.';
  else {
    $u = q("SELECT * FROM users WHERE email=? AND active=1", [strtolower(trim($_POST['email'] ?? ''))])->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) { session_regenerate_id(true); $_SESSION['uid'] = $u['id']; $_SESSION['tries'] = 0; header('Location: dashboard.php'); exit; }
    $_SESSION['tries'] = ($_SESSION['tries'] ?? 0) + 1; $_SESSION['t0'] = time(); $err = 'Email or password is wrong, or the account is switched off.';
  }
}
page_start('Log in');
?>
<form method="post" class="card form"><?= csrf_field() ?>
  <label>Email <input name="email" type="email" required autofocus></label>
  <label>Password <input name="password" type="password" required></label>
  <button>Log in</button><p class="err"><?= h($err) ?></p>
  <p class="hint">New student? <a href="register.php">Create an account</a></p>
</form>
<?php page_end();
