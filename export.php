<?php
require_once __DIR__ . '/db.php';
need(['admin','technician']);
function safe($v) { $v = (string)$v; return ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) ? "'".$v : $v; }
$what = $_GET['what'] ?? 'assets';
header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="ecoboard-'.($what === 'reports' ? 'reports' : 'assets').'.csv"');
$o = fopen('php://output', 'w');
if ($what === 'reports') {
  fputcsv($o, ['id','date','type','title','lab','device','quantity','status','reporter','response']);
  foreach (q("SELECT r.*, u.name uname, l.name lab, a.tag FROM reports r JOIN users u ON u.id=r.user_id LEFT JOIN labs l ON l.id=r.lab_id LEFT JOIN assets a ON a.id=r.asset_id ORDER BY r.id")->fetchAll() as $r)
    fputcsv($o, array_map('safe', [$r['id'],$r['created_at'],$r['type'],$r['title'],$r['lab'],$r['tag'],$r['quantity'],$r['status'],$r['uname'],$r['response']]));
} else {
  fputcsv($o, ['tag','type','model','lab','status','condition','purchase_date','lifespan_yrs','age_yrs','embodied_kgCO2e','operating_kgCO2e_per_yr','lifetime_kgCO2e','avoided_kgCO2e']);
  foreach (q("SELECT a.*, l.name lab FROM assets a LEFT JOIN labs l ON l.id=a.lab_id ORDER BY a.tag")->fetchAll() as $a) {
    $c = asset_carbon($a);
    fputcsv($o, array_map('safe', [$a['tag'],$a['type'],$a['make_model'],$a['lab'],$a['status'],$a['condition_score'],$a['purchase_date'],$a['lifespan_years'],round($c['age'],1),round($c['embodied']),round($c['op_year']),round($c['total']),round($c['avoided'])]));
  }
}
