<?php
require_once __DIR__ . '/db.php';
$u = need(); $staff = is_staff();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf(); $id = (int)($_POST['id'] ?? 0);
  if (($_POST['act'] ?? '') === 'update' && $staff && in_array($_POST['status'] ?? '', ['open','in_review','resolved','rejected'], true)) {
    $st = $_POST['status'];
    q("UPDATE reports SET status=?, response=?, handled_by=?, resolved_at=IF(?='resolved', NOW(), NULL) WHERE id=?", [$st, mb_substr(trim($_POST['response'] ?? ''),0,1000), $u['id'], $st, $id]);
    $ra = q("SELECT asset_id FROM reports WHERE id=?", [$id])->fetchColumn(); $ds = $_POST['device_status'] ?? '';
    if ($ra && in_array($ds, ['in_use','needs_repair','in_repair','stored','donated_reused','recycled','disposed'], true)) {
      q("UPDATE assets SET status=? WHERE id=?", [$ds, $ra]); $cs = (int)q("SELECT condition_score FROM assets WHERE id=?", [$ra])->fetchColumn();
      q("INSERT INTO asset_events (asset_id,status,condition_score,note,user_id) VALUES (?,?,?,?,?)", [$ra,$ds,$cs,'From report #'.$id.' ('.$st.')',$u['id']]);
    }
    flash('Report updated.');
  } elseif (($_POST['act'] ?? '') === 'feedback') {
    $rt = (int)($_POST['rating'] ?? 0);
    $own = q("SELECT 1 FROM reports WHERE id=? AND user_id=? AND status='resolved'", [$id, $u['id']])->fetch();
    if ($own && $rt >= 1 && $rt <= 5) { q("INSERT IGNORE INTO feedback (report_id,user_id,rating,comment) VALUES (?,?,?,?)", [$id,$u['id'],$rt,mb_substr(trim($_POST['comment'] ?? ''),0,500)]); flash('Thank you for your feedback.'); }
  }
  header('Location: reports.php'); exit;
}
$w = []; $p = [];
if (!$staff) { $w[] = 'r.user_id=?'; $p[] = $u['id']; }
if (in_array($_GET['status'] ?? '', ['open','in_review','resolved','rejected'], true)) { $w[] = 'r.status=?'; $p[] = $_GET['status']; }
if (in_array($_GET['type'] ?? '', ['ewaste','issue','plastic'], true)) { $w[] = 'r.type=?'; $p[] = $_GET['type']; }
$rows = q("SELECT r.*, u.name uname, l.name lab, a.tag, f.rating, f.comment fcomment FROM reports r JOIN users u ON u.id=r.user_id
  LEFT JOIN labs l ON l.id=r.lab_id LEFT JOIN assets a ON a.id=r.asset_id LEFT JOIN feedback f ON f.report_id=r.id ".($w ? 'WHERE '.implode(' AND ', $w) : '')." ORDER BY r.created_at DESC LIMIT 200", $p)->fetchAll();
page_start($staff ? 'All reports' : 'My reports');
?>
<form class="inline" method="get">
  <label class="sr" for="fs">Status</label><select id="fs" name="status"><option value="">Any status</option><?php foreach (['open','in_review','resolved','rejected'] as $s) echo '<option value="'.$s.'"'.(($_GET['status'] ?? '') === $s ? ' selected' : '').'>'.label($s).'</option>'; ?></select>
  <label class="sr" for="ft">Type</label><select id="ft" name="type"><option value="">Any type</option><?php foreach (['ewaste'=>'E-waste','issue'=>'Issue','plastic'=>'Plastic'] as $k => $v) echo '<option value="'.$k.'"'.(($_GET['type'] ?? '') === $k ? ' selected' : '').'>'.$v.'</option>'; ?></select>
  <button>Filter</button>
</form>
<?php if (!$rows) echo '<p class="hint">No reports yet. <a href="report_new.php">Send the first one.</a></p>';
foreach ($rows as $r): ?>
<article class="card" id="r<?= $r['id'] ?>">
  <h3><?= h($r['title']) ?> <span class="badge b-<?= $r['status'] ?>"><?= label($r['status']) ?></span></h3>
  <p class="hint"><?= h(label($r['type'])) ?>, qty <?= $r['quantity'] ?><?= $r['lab'] ? ', '.h($r['lab']) : '' ?><?= $r['tag'] ? ', device <a href="asset.php?id='.(int)$r['asset_id'].'">'.h($r['tag']).'</a>' : '' ?> | by <?= h($r['uname']) ?> on <?= h(substr($r['created_at'],0,10)) ?></p>
  <?php if ($r['description']) echo '<p>'.nl2br(h($r['description'])).'</p>'; ?>
  <?php if ($r['response']) echo '<p class="reply"><b>Response:</b> '.nl2br(h($r['response'])).'</p>'; ?>
  <?php if ($staff): ?>
    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="act" value="update"><input type="hidden" name="id" value="<?= $r['id'] ?>">
      <select name="status" aria-label="Status"><?php foreach (['open','in_review','resolved','rejected'] as $s) echo '<option value="'.$s.'"'.($r['status'] === $s ? ' selected' : '').'>'.label($s).'</option>'; ?></select>
      <?php if ($r['asset_id']): ?><select name="device_status" aria-label="Set device status"><option value="">Device: no change</option><?php foreach (['in_use','needs_repair','in_repair','stored','donated_reused','recycled','disposed'] as $s) echo '<option value="'.$s.'">Device: '.label($s).'</option>'; ?></select><?php endif; ?>
      <input name="response" value="<?= h($r['response']) ?>" placeholder="Reply to the student" aria-label="Response">
      <button>Save</button></form>
    <?php if ($r['rating']) echo '<p class="hint">Feedback: '.str_repeat('★', $r['rating']).str_repeat('☆', 5-$r['rating']).' '.h($r['fcomment']).'</p>'; ?>
  <?php elseif ($r['status'] === 'resolved' && !$r['rating']): ?>
    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="act" value="feedback"><input type="hidden" name="id" value="<?= $r['id'] ?>">
      <select name="rating" aria-label="Rating"><option value="5">5 - Very helpful</option><option value="4">4</option><option value="3">3</option><option value="2">2</option><option value="1">1 - Not helpful</option></select>
      <input name="comment" maxlength="500" placeholder="How was the response?" aria-label="Comment"><button>Send feedback</button></form>
  <?php elseif ($r['rating']): ?><p class="hint">Your feedback: <?= str_repeat('★', $r['rating']).str_repeat('☆', 5-$r['rating']) ?></p>
  <?php endif; ?>
</article>
<?php endforeach; page_end();
