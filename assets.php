<?php
require_once __DIR__ . '/db.php';
$u = need(['admin','technician']);
$types = array_keys(EMBODIED_KG); $sts = ['in_use','needs_repair','in_repair','stored','donated_reused','recycled','disposed'];

function add_asset(array $d, int $uid): bool {
  global $types, $sts;
  $t = $d['type'] ?? ''; $tag = strtoupper(trim($d['tag'] ?? '')); $st = $d['status'] ?? 'in_use';
  if (!in_array($t, $types, true) || $tag === '' || !in_array($st, $sts, true) || q("SELECT 1 FROM assets WHERE tag=?", [$tag])->fetch()) return false;
  $pw = ($d['power_w'] ?? '') !== '' ? (float)$d['power_w'] : (DEFAULT_POWER[$t] ?? 0);
  $cs = min(5, max(1, (int)($d['condition_score'] ?? 5)));
  q("INSERT INTO assets (tag,type,make_model,lab_id,status,condition_score,purchase_date,lifespan_years,power_w,hours_per_day,weight_kg) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
    [mb_substr($tag,0,40),$t,mb_substr(trim($d['make_model'] ?? ''),0,100),(int)($d['lab_id'] ?? 0) ?: null,$st,$cs,($d['purchase_date'] ?? '') ?: null,max(1,(float)($d['lifespan_years'] ?? 5)),$pw,max(0,(float)($d['hours_per_day'] ?? 4)),max(0,(float)($d['weight_kg'] ?? 0))]);
  q("INSERT INTO asset_events (asset_id,status,condition_score,note,user_id) VALUES (?,?,?,?,?)", [db()->lastInsertId(),$st,$cs,'Registered',$uid]);
  return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf(); $act = $_POST['act'] ?? '';
  if ($act === 'add') {
    $base = strtoupper(trim($_POST['tag'] ?? '')); $n = max(1, min(200, (int)($_POST['count'] ?? 1))); $ok = 0;
    for ($i = 1; $i <= $n; $i++) { $d = $_POST; $d['tag'] = $n > 1 ? $base.'-'.str_pad((string)$i, 3, '0', STR_PAD_LEFT) : $base; if (add_asset($d, (int)$u['id'])) $ok++; }
    flash($ok ? "$ok device(s) added. You can now update or monitor them below." : 'Nothing added: the tag already exists or the type or status is invalid.');
  } elseif ($act === 'import' && !empty($_FILES['csv']['tmp_name'])) {
    $f = fopen($_FILES['csv']['tmp_name'], 'r'); $ok = 0; $skip = 0; $first = true;
    while (($r = fgetcsv($f)) !== false) {
      if ($first) { $first = false; if (strtolower(trim($r[0] ?? '')) === 'tag') continue; }
      if (count($r) < 2) { $skip++; continue; }
      $labId = null; $ln = trim($r[3] ?? '');
      if ($ln !== '') { $labId = q("SELECT id FROM labs WHERE name=?", [$ln])->fetchColumn(); if (!$labId) { q("INSERT INTO labs (name) VALUES (?)", [mb_substr($ln,0,80)]); $labId = db()->lastInsertId(); } }
      $d = ['tag'=>$r[0],'type'=>strtolower(trim($r[1])),'make_model'=>$r[2] ?? '','lab_id'=>$labId,'status'=>strtolower(trim($r[4] ?? '')) ?: 'in_use','condition_score'=>$r[5] ?? 5,'purchase_date'=>$r[6] ?? '','lifespan_years'=>($r[7] ?? '') ?: 5];
      add_asset($d, (int)$u['id']) ? $ok++ : $skip++;
    }
    fclose($f); flash("Import finished: $ok added, $skip skipped (duplicate tag or invalid row).");
  } elseif ($act === 'update' && in_array($_POST['status'] ?? '', $sts, true)) {
    $cs = min(5, max(1, (int)$_POST['condition_score']));
    q("UPDATE assets SET status=?, condition_score=? WHERE id=?", [$_POST['status'], $cs, (int)$_POST['id']]);
    q("INSERT INTO asset_events (asset_id,status,condition_score,note,user_id) VALUES (?,?,?,?,?)", [(int)$_POST['id'],$_POST['status'],$cs,mb_substr(trim($_POST['note'] ?? ''),0,255),$u['id']]);
    flash('Device updated and added to its history.');
  }
  header('Location: assets.php'); exit;
}
$w = []; $p = [];
if (in_array($_GET['status'] ?? '', $sts, true)) { $w[] = 'a.status=?'; $p[] = $_GET['status']; }
if ((int)($_GET['lab'] ?? 0)) { $w[] = 'a.lab_id=?'; $p[] = (int)$_GET['lab']; }
$rows = q("SELECT a.*, l.name lab, (SELECT COUNT(*) FROM reports r WHERE r.asset_id=a.id AND r.status IN('open','in_review')) open_r FROM assets a LEFT JOIN labs l ON l.id=a.lab_id ".($w ? 'WHERE '.implode(' AND ', $w) : '')." ORDER BY a.tag", $p)->fetchAll();
page_start('Devices and lifecycle');
?>
<details class="card" id="add" <?= !$rows || isset($_GET['add']) ? 'open' : '' ?>><summary>Add devices (single or many at once)</summary>
<p class="hint">Register every computer, monitor, keyboard and other device you own. Once a device is listed, students can pick it when they report a fault, and you can follow its condition, reports and carbon footprint here.</p>
<form method="post" class="grid"><?= csrf_field() ?><input type="hidden" name="act" value="add">
  <label>Tag or tag prefix <input name="tag" required maxlength="30" placeholder="PC-LAB1"></label>
  <label>How many (more than 1 numbers them PC-LAB1-001, 002, ...) <input type="number" name="count" min="1" max="200" value="1"></label>
  <label>Type <select name="type"><?php foreach ($types as $t) echo '<option value="'.$t.'">'.label($t).'</option>'; ?></select></label>
  <label>Make and model <input name="make_model" maxlength="100"></label>
  <label>Lab <select name="lab_id"><option value="">None</option><?php foreach (labs() as $l) echo '<option value="'.$l['id'].'">'.h($l['name']).'</option>'; ?></select></label>
  <label>Status <select name="status"><?php foreach ($sts as $s) echo '<option value="'.$s.'">'.label($s).'</option>'; ?></select></label>
  <label>Condition (1 poor, 5 new) <input type="number" name="condition_score" min="1" max="5" value="5"></label>
  <label>Purchase date <input type="date" name="purchase_date"></label>
  <label>Expected life (years) <input type="number" step="0.5" name="lifespan_years" value="5" min="1"></label>
  <label>Power in watts (blank = default) <input type="number" step="0.1" name="power_w"></label>
  <label>Hours used per day <input type="number" step="0.5" name="hours_per_day" value="4" min="0"></label>
  <label>Weight in kg <input type="number" step="0.01" name="weight_kg" value="0" min="0"></label>
  <button class="wide">Add device(s)</button>
</form>
<hr style="border:0;border-top:1px solid var(--line);margin:1rem 0"><h3>Import many devices from a spreadsheet</h3>
<p class="hint">Save your inventory as CSV with columns: tag, type, make_model, lab, status, condition, purchase_date, lifespan_years. A new lab name is created automatically. <a href="sample_devices.csv" download>Download a sample file</a>.</p>
<form method="post" enctype="multipart/form-data" class="inline"><?= csrf_field() ?><input type="hidden" name="act" value="import"><input type="file" name="csv" accept=".csv" required><button>Import CSV</button></form>
</details>

<form class="inline" method="get">
  <select name="status" aria-label="Status"><option value="">Any status</option><?php foreach ($sts as $s) echo '<option value="'.$s.'"'.(($_GET['status'] ?? '') === $s ? ' selected' : '').'>'.label($s).'</option>'; ?></select>
  <select name="lab" aria-label="Lab"><option value="">Any lab</option><?php foreach (labs() as $l) echo '<option value="'.$l['id'].'"'.((int)($_GET['lab'] ?? 0) === (int)$l['id'] ? ' selected' : '').'>'.h($l['name']).'</option>'; ?></select>
  <button>Filter</button> <a class="btn ghost" href="export.php?what=assets">Download CSV</a>
</form>
<div class="scroll"><table>
<thead><tr><th>Tag</th><th>Device</th><th>Lab</th><th>Age / life</th><th>Carbon (kg CO2e)</th><th>Update status and condition</th></tr></thead><tbody>
<?php foreach ($rows as $a): $c = asset_carbon($a); ?>
<tr>
  <td><a href="asset.php?id=<?= $a['id'] ?>"><b><?= h($a['tag']) ?></b></a><br><span class="badge b-<?= $a['status'] ?>"><?= label($a['status']) ?></span><?= $a['open_r'] ? '<br><span class="badge b-open">'.$a['open_r'].' open report(s)</span>' : '' ?></td>
  <td><?= h(label($a['type'])) ?><br><span class="hint"><?= h($a['make_model']) ?></span></td>
  <td><?= h($a['lab'] ?: '-') ?></td>
  <td><?= round($c['age'],1) ?> of <?= (float)$a['lifespan_years'] ?> yrs<?= $c['eol'] ? '<br><span class="badge b-open">Near end of life</span>' : '' ?></td>
  <td>Made: <?= round($c['embodied']) ?><br>Lifetime: <?= round($c['total']) ?><br>Avoided: <?= round($c['avoided']) ?></td>
  <td><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="act" value="update"><input type="hidden" name="id" value="<?= $a['id'] ?>">
    <select name="status" aria-label="Status"><?php foreach ($sts as $s) echo '<option value="'.$s.'"'.($a['status'] === $s ? ' selected' : '').'>'.label($s).'</option>'; ?></select>
    <select name="condition_score" aria-label="Condition 1 to 5"><?php for ($i = 5; $i >= 1; $i--) echo '<option value="'.$i.'"'.((int)$a['condition_score'] === $i ? ' selected' : '').'>Condition '.$i.'</option>'; ?></select>
    <input name="note" placeholder="Note (optional)" aria-label="Note"><button>Save</button></form></td>
</tr>
<?php endforeach; ?></tbody></table></div>
<?php if (!$rows) echo '<p class="hint">No devices yet. Use the Add devices section above.</p>'; ?>
<?php page_end();
