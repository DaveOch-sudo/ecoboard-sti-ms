<?php
require_once __DIR__ . '/db.php';
$u = need(); $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  $type = $_POST['type'] ?? ''; $title = trim($_POST['title'] ?? ''); $qty = (int)($_POST['quantity'] ?? 1);
  $lab = (int)($_POST['lab_id'] ?? 0) ?: null; $asset = (int)($_POST['asset_id'] ?? 0) ?: null;
  if (!in_array($type, ['ewaste','issue','plastic'], true) || $title === '' || $qty < 1 || $qty > 10000) $err = 'Choose a type, add a short title and a quantity of 1 or more.';
  else {
    q("INSERT INTO reports (user_id,type,title,lab_id,asset_id,quantity,description) VALUES (?,?,?,?,?,?,?)", [$u['id'],$type,mb_substr($title,0,120),$lab,$asset,$qty,mb_substr(trim($_POST['description'] ?? ''),0,2000)]);
    $rid = db()->lastInsertId();
    if ($asset && q("SELECT 1 FROM assets WHERE id=?", [$asset])->fetch()) {
      if ($type === 'issue') q("UPDATE assets SET status='needs_repair' WHERE id=? AND status='in_use'", [$asset]);
      $cur = q("SELECT status,condition_score FROM assets WHERE id=?", [$asset])->fetch();
      q("INSERT INTO asset_events (asset_id,status,condition_score,note,user_id) VALUES (?,?,?,?,?)", [$asset,$cur['status'],$cur['condition_score'],mb_substr('Report #'.$rid.': '.$title,0,255),$u['id']]);
    }
    flash('Report sent. You will see its status under My reports.'); header('Location: reports.php'); exit;
  }
}
page_start('New report');
$assets = q("SELECT a.id,a.tag,a.type,l.name lab FROM assets a LEFT JOIN labs l ON l.id=a.lab_id WHERE a.status NOT IN('recycled','disposed','donated_reused') ORDER BY a.tag")->fetchAll();
?>
<form method="post" class="card form"><?= csrf_field() ?>
  <label>What are you reporting?
    <select name="type"><option value="ewaste"<?= ($_GET['type'] ?? '') === 'ewaste' ? ' selected' : '' ?>>E-waste (broken or unused hardware)</option><option value="issue"<?= ($_GET['type'] ?? '') === 'issue' ? ' selected' : '' ?>>Faulty device or lab issue</option><option value="plastic"<?= ($_GET['type'] ?? '') === 'plastic' ? ' selected' : '' ?>>Plastic waste (caps, bottles)</option></select></label>
  <label>Short title <input name="title" required maxlength="120" placeholder="Keyboard with missing keys, Lab 1"></label>
  <label>Lab <select name="lab_id"><option value="">Not sure</option><?php foreach (labs() as $l) echo '<option value="'.$l['id'].'">'.h($l['name']).'</option>'; ?></select></label>
  <label>Device tag, if it has one <select name="asset_id"><option value="">None</option><?php foreach ($assets as $a) echo '<option value="'.$a['id'].'"'.((int)($_GET['asset'] ?? 0) === (int)$a['id'] ? ' selected' : '').'>'.h($a['tag'].' ('.$a['type'].', '.($a['lab'] ?: 'no lab').')').'</option>'; ?></select></label>
  <label>How many items <input type="number" name="quantity" value="1" min="1"></label>
  <label>Details <textarea name="description" rows="4" maxlength="2000"></textarea></label>
  <button>Send report</button><p class="err"><?= h($err) ?></p>
</form>
<?php page_end();
