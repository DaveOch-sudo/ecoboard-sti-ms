<?php
require_once __DIR__ . '/db.php';
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  $n = trim($_POST['name'] ?? ''); $e = strtolower(trim($_POST['email'] ?? '')); $p = $_POST['password'] ?? ''; $s = trim($_POST['student_no'] ?? '');
  if ($n === '' || !filter_var($e, FILTER_VALIDATE_EMAIL) || strlen($p) < 8) $err = 'Enter your name, a valid email and a password of 8 or more characters.';
  elseif (q("SELECT 1 FROM users WHERE email=?", [$e])->fetch()) $err = 'That email already has an account. Log in instead.';
  else { q("INSERT INTO users (name,email,student_no,password_hash,role) VALUES (?,?,?,?, 'student')", [mb_substr($n,0,80),$e,mb_substr($s,0,40),password_hash($p,PASSWORD_DEFAULT)]); flash('Account created. Log in.'); header('Location: login.php'); exit; }
}
page_start('Create a student account');
?>
<form method="post" class="card form"><?= csrf_field() ?>
  <label>Full name <input name="name" required maxlength="80"></label>
  <label>Student number <input name="student_no" maxlength="40"></label>
  <label>Email <input name="email" type="email" required></label>
  <label>Password (8+ characters) <input name="password" type="password" minlength="8" required></label>
  <button>Create account</button><p class="err"><?= h($err) ?></p>
</form>
<?php page_end();
