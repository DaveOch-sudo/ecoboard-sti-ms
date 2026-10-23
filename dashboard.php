<?php
require_once __DIR__ . '/db.php';
$u = need();
page_start('Welcome, '.$u['name']);
if (is_staff()) {
  $k = kpis(); $c = $k['carbon'];
  $cards = [['Assets tracked',$k['total']],['In use',$k['in_use']],['Need repair',$k['repair']],['Open reports',$k['open_r']],
    ['E-waste diverted (kg)',round($k['ewaste_kg'],1)],['Plastic collected (kg)',$c['plastic_kg']],['CO2e avoided (kg)',$c['avoided_total']],['Devices near end of life',$c['eol_count']]];
  echo '<div class="kpis">'; foreach ($cards as $x) echo '<div class="kpi"><b>'.h($x[1]).'</b><span>'.h($x[0]).'</span></div>'; echo '</div>';
  echo '<p><a class="btn" href="assets.php?add=1#add">Add devices</a> <a class="btn ghost" href="assets.php">Monitor devices</a> <a class="btn ghost" href="plastics.php">Log plastics</a></p>';
  echo '<h2>Reports waiting for action</h2>';
  $rows = q("SELECT r.id,r.title,r.type,r.status,r.created_at,u.name FROM reports r JOIN users u ON u.id=r.user_id WHERE r.status IN('open','in_review') ORDER BY r.created_at LIMIT 8")->fetchAll();
  echo $rows ? '<ul class="list">' : '<p class="hint">Nothing waiting.</p>';
  foreach ($rows as $r) echo '<li><a href="reports.php?status='.$r['status'].'#r'.$r['id'].'">'.h($r['title']).'</a> <span class="badge b-'.$r['status'].'">'.label($r['status']).'</span> <span class="hint">'.h(label($r['type'])).' by '.h($r['name']).', '.h(substr($r['created_at'],0,10)).'</span></li>';
  echo $rows ? '</ul>' : '';
} else {
  $s = q("SELECT COUNT(*) t, COALESCE(SUM(status='open'),0) o, COALESCE(SUM(status='resolved'),0) r FROM reports WHERE user_id=?", [$u['id']])->fetch();
  $fb = (int)q("SELECT COUNT(*) FROM reports r LEFT JOIN feedback f ON f.report_id=r.id WHERE r.user_id=? AND r.status='resolved' AND f.id IS NULL", [$u['id']])->fetchColumn();
  echo '<div class="kpis"><div class="kpi"><b>'.$s['t'].'</b><span>Reports sent</span></div><div class="kpi"><b>'.$s['o'].'</b><span>Waiting</span></div><div class="kpi"><b>'.$s['r'].'</b><span>Resolved</span></div></div>';
  echo '<p><a class="btn" href="report_new.php">Report e-waste, a faulty device or plastic waste</a></p>';
  if ($fb) echo '<p class="ok">'.$fb.' resolved report(s) need your feedback. <a href="reports.php?status=resolved">Rate them</a></p>';
  $c = carbon_summary();
  echo '<h2>What the school has achieved so far</h2><p>'.h($c['plastic_kg']).' kg of plastic collected and about '.h($c['avoided_total']).' kg CO2e avoided through reuse and recycling.</p>';
}
page_end();
