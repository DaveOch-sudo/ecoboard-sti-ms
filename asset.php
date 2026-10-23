<?php
require_once __DIR__ . '/db.php';
$u = need(['admin','technician']);
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$a = q("SELECT a.*, l.name lab FROM assets a LEFT JOIN labs l ON l.id=a.lab_id WHERE a.id=?", [$id])->fetch();
if (!$a) { header('Location: assets.php'); exit; }
$sts = ['in_use','needs_repair','in_repair','stored','donated_reused','recycled','disposed'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf();
  if (($_POST['act'] ?? '') === 'update' && in_array($_POST['status'] ?? '', $sts, true)) {
    $cs = min(5, max(1, (int)$_POST['condition_score']));
    q("UPDATE assets SET status=?, condition_score=? WHERE id=?", [$_POST['status'], $cs, $id]);
    q("INSERT INTO asset_events (asset_id,status,condition_score,note,user_id) VALUES (?,?,?,?,?)", [$id,$_POST['status'],$cs,mb_substr(trim($_POST['note'] ?? ''),0,255),$u['id']]);
    flash('Device updated.');
  } elseif (($_POST['act'] ?? '') === 'edit') {
    q("UPDATE assets SET make_model=?, lab_id=?, lifespan_years=?, power_w=?, hours_per_day=?, weight_kg=?, purchase_date=? WHERE id=?",
      [mb_substr(trim($_POST['make_model'] ?? ''),0,100),(int)$_POST['lab_id'] ?: null,max(1,(float)$_POST['lifespan_years']),max(0,(float)$_POST['power_w']),max(0,(float)$_POST['hours_per_day']),max(0,(float)$_POST['weight_kg']),$_POST['purchase_date'] ?: null,$id]);
    flash('Device details saved.');
  }
  header('Location: asset.php?id='.$id); exit;
}
$c = asset_carbon($a);
$events = q("SELECT e.*, u.name uname FROM asset_events e LEFT JOIN users u ON u.id=e.user_id WHERE e.asset_id=? ORDER BY e.created_at DESC, e.id DESC", [$id])->fetchAll();
$reps = q("SELECT r.id,r.title,r.type,r.status,r.created_at,u.name FROM reports r JOIN users u ON u.id=r.user_id WHERE r.asset_id=? ORDER BY r.created_at DESC", [$id])->fetchAll();
$open = count(array_filter($reps, fn($r) => in_array($r['status'], ['open','in_review'], true)));
page_start($a['tag'].': '.label($a['type']));
?>
<p><a href="assets.php">&larr; All devices</a> &nbsp; <a class="btn" href="report_new.php?asset=<?= $id ?>&type=issue">Report a fault on this device</a></p>
<div class="kpis">
  <div class="kpi"><b><span class="badge b-<?= $a['status'] ?>"><?= label($a['status']) ?></span></b><span>Status<?= $a['lab'] ? ' in '.h($a['lab']) : '' ?></span></div>
  <div class="kpi"><b><?= (int)$a['condition_score'] ?>/5</b><span>Condition</span></div>
  <div class="kpi"><b><?= round($c['age'],1) ?> / <?= (float)$a['lifespan_years'] ?></b><span>Years used / expected<?= $c['eol'] ? ' (near end of life)' : '' ?></span></div>
  <div class="kpi"><b><?= $open ?></b><span>Open reports</span></div>
  <div class="kpi"><b><?= round($c['total']) ?></b><span>Lifetime kg CO2e (made <?= round($c['embodied']) ?>, avoided <?= round($c['avoided']) ?>)</span></div>
</div>
<div class="cols">
  <section class="panel"><h2>Update status</h2>
    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="act" value="update"><input type="hidden" name="id" value="<?= $id ?>">
      <select name="status" aria-label="Status"><?php foreach ($sts as $s) echo '<option value="'.$s.'"'.($a['status'] === $s ? ' selected' : '').'>'.label($s).'</option>'; ?></select>
      <select name="condition_score" aria-label="Condition"><?php for ($i = 5; $i >= 1; $i--) echo '<option value="'.$i.'"'.((int)$a['condition_score'] === $i ? ' selected' : '').'>Condition '.$i.'</option>'; ?></select>
      <input name="note" placeholder="What changed?" aria-label="Note"><button>Save</button></form>
    <h2>Reports on this device</h2>
    <?php if (!$reps) echo '<p class="hint">No reports yet.</p>'; foreach ($reps as $r) echo '<p><a href="reports.php#r'.$r['id'].'">'.h($r['title']).'</a> <span class="badge b-'.$r['status'].'">'.label($r['status']).'</span> <span class="hint">'.h($r['name']).', '.h(substr($r['created_at'],0,10)).'</span></p>'; ?>
  </section>
  <section class="panel"><h2>History</h2>
    <?php foreach ($events as $e) echo '<p><b>'.h(substr($e['created_at'],0,10)).'</b> <span class="badge b-'.h($e['status']).'">'.label((string)$e['status']).'</span> condition '.(int)$e['condition_score'].($e['note'] ? ': '.h($e['note']) : '').' <span class="hint">'.h($e['uname'] ?: 'system').'</span></p>'; ?>
  </section>
</div>
<details class="card"><summary>Edit device details</summary>
<form method="post" class="grid"><?= csrf_field() ?><input type="hidden" name="act" value="edit"><input type="hidden" name="id" value="<?= $id ?>">
  <label>Make and model <input name="make_model" value="<?= h($a['make_model']) ?>" maxlength="100"></label>
  <label>Lab <select name="lab_id"><option value="">None</option><?php foreach (labs() as $l) echo '<option value="'.$l['id'].'"'.((int)$a['lab_id'] === (int)$l['id'] ? ' selected' : '').'>'.h($l['name']).'</option>'; ?></select></label>
  <label>Purchase date <input type="date" name="purchase_date" value="<?= h($a['purchase_date']) ?>"></label>
  <label>Expected life (years) <input type="number" step="0.5" min="1" name="lifespan_years" value="<?= h($a['lifespan_years']) ?>"></label>
  <label>Power (watts) <input type="number" step="0.1" min="0" name="power_w" value="<?= h($a['power_w']) ?>"></label>
  <label>Hours per day <input type="number" step="0.5" min="0" name="hours_per_day" value="<?= h($a['hours_per_day']) ?>"></label>
  <label>Weight (kg) <input type="number" step="0.01" min="0" name="weight_kg" value="<?= h($a['weight_kg']) ?>"></label>
  <button class="wide">Save details</button></form></details>
<?php page_end();
