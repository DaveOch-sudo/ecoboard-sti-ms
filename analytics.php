<?php
require_once __DIR__ . '/db.php';
need(['admin','technician']);
$k = kpis(); $c = $k['carbon'];
$kv = fn($sql) => array_column(q($sql)->fetchAll(PDO::FETCH_NUM), 1, 0);
$byStatus = $kv("SELECT status, COUNT(*) FROM assets GROUP BY status");
$byType = $kv("SELECT type, COUNT(*) FROM assets GROUP BY type");
$condLab = $kv("SELECT l.name, ROUND(AVG(a.condition_score),1) FROM assets a JOIN labs l ON l.id=a.lab_id GROUP BY l.id ORDER BY l.name");
$months = array_reverse($kv("SELECT DATE_FORMAT(created_at,'%Y-%m') m, COUNT(*) FROM reports GROUP BY m ORDER BY m DESC LIMIT 6"), true);
$byRType = $kv("SELECT type, COUNT(*) FROM reports GROUP BY type");
$res = q("SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR,created_at,resolved_at))/24,1) FROM reports WHERE resolved_at IS NOT NULL")->fetchColumn();
$rate = q("SELECT ROUND(AVG(rating),1), COUNT(*) FROM feedback")->fetch(PDO::FETCH_NUM);
$eol = [];
foreach (q("SELECT a.*, l.name lab FROM assets a LEFT JOIN labs l ON l.id=a.lab_id WHERE a.status IN('in_use','needs_repair','in_repair')")->fetchAll() as $a) { $x = asset_carbon($a); if ($x['eol']) $eol[] = [$a, $x]; }
page_start('Analytics and reports');
?>
<p><a class="btn" href="export.php?what=assets">Assets CSV</a> <a class="btn" href="export.php?what=reports">Reports CSV</a></p>
<div class="kpis">
  <div class="kpi"><b><?= $k['total'] ?></b><span>Assets tracked</span></div>
  <div class="kpi"><b><?= $k['diverted'] ?></b><span>Devices recycled or reused</span></div>
  <div class="kpi"><b><?= h($res ?: '-') ?></b><span>Avg days to resolve a report</span></div>
  <div class="kpi"><b><?= h($rate[0] ?: '-') ?></b><span>Avg feedback rating (<?= (int)$rate[1] ?>)</span></div>
</div>
<div class="cols">
  <section class="panel"><h2>Assets by status</h2><?= bars($byStatus) ?></section>
  <section class="panel"><h2>Assets by type</h2><?= bars($byType) ?></section>
  <section class="panel"><h2>Average device condition by lab (5 is best)</h2><?= bars($condLab) ?></section>
  <section class="panel"><h2>Reports per month</h2><?= bars($months) ?><h2>By type</h2><?= bars($byRType) ?></section>
  <section class="panel"><h2>Carbon footprint (kg CO2e)</h2>
    <table><tbody>
      <tr><td>Manufacturing carbon of all devices</td><td><?= $c['embodied'] ?></td></tr>
      <tr><td>Electricity use per year (current devices)</td><td><?= $c['op_year'] ?></td></tr>
      <tr><td>Lifetime total so far (made + used)</td><td><?= $c['lifecycle'] ?></td></tr>
      <tr><td>Avoided by reuse and recycling of devices</td><td><?= $c['avoided_ewaste'] ?></td></tr>
      <tr><td>Avoided through <?= $c['plastic_kg'] ?> kg of plastic kept in use</td><td><?= $c['avoided_plastic'] ?></td></tr>
      <tr><th>Total avoided</th><th><?= $c['avoided_total'] ?></th></tr></tbody></table>
    <p class="hint">Values come from the editable factors in config.php. They are estimates, so cite your own sources in your report.</p></section>
  <section class="panel"><h2>Devices near end of life (80% of expected life used)</h2>
    <?php if (!$eol) echo '<p class="hint">None right now.</p>'; foreach ($eol as [$a, $x]) echo '<p>'.h($a['tag']).' ('.h(label($a['type'])).', '.h($a['lab'] ?: 'no lab').'): '.round($x['age'],1).' of '.(float)$a['lifespan_years'].' years. Plan repair, reuse or recycling.</p>'; ?></section>
</div>
<?php page_end();
