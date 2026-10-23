<?php
require_once __DIR__ . '/db.php';
$u = need(['admin','technician']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  check_csrf(); $qty = (int)($_POST['qty'] ?? 0);
  if (in_array($_POST['item'] ?? '', ['bottle_caps','bottles','other'], true) && $qty > 0) {
    q("INSERT INTO plastic_log (item,qty,weight_kg,lab_id,note,user_id) VALUES (?,?,?,?,?,?)", [$_POST['item'],$qty,max(0,(float)$_POST['weight_kg']),(int)$_POST['lab_id'] ?: null,mb_substr(trim($_POST['note'] ?? ''),0,150),$u['id']]);
    flash('Plastic collection logged.');
  } else flash('Enter an item type and a quantity of 1 or more.');
  header('Location: plastics.php'); exit;
}
$t = q("SELECT item, SUM(qty) qty, SUM(weight_kg) kg FROM plastic_log GROUP BY item")->fetchAll();
$rows = q("SELECT p.*, l.name lab FROM plastic_log p LEFT JOIN labs l ON l.id=p.lab_id ORDER BY p.created_at DESC LIMIT 30")->fetchAll();
page_start('Plastic waste log');
$kg = array_sum(array_column($t, 'kg'));
echo '<div class="kpis"><div class="kpi"><b>'.round($kg,2).'</b><span>kg collected</span></div><div class="kpi"><b>'.round($kg*PLASTIC_CO2E_PER_KG,1).'</b><span>kg CO2e avoided (estimate)</span></div></div>';
?>
<form method="post" class="card grid"><?= csrf_field() ?>
  <label>Item <select name="item"><option value="bottle_caps">Bottle caps</option><option value="bottles">Bottles</option><option value="other">Other plastic</option></select></label>
  <label>Count <input type="number" name="qty" min="1" value="50" required></label>
  <label>Weight (kg) <input type="number" step="0.001" name="weight_kg" min="0" value="0"></label>
  <label>Lab or place <select name="lab_id"><option value="">Campus-wide</option><?php foreach (labs() as $l) echo '<option value="'.$l['id'].'">'.h($l['name']).'</option>'; ?></select></label>
  <label class="wide">Note <input name="note" maxlength="150"></label>
  <button class="wide">Log collection</button>
</form>
<h2>Recent collections</h2>
<div class="scroll"><table><thead><tr><th>Date</th><th>Item</th><th>Count</th><th>kg</th><th>Where</th><th>Note</th></tr></thead><tbody>
<?php foreach ($rows as $r) echo '<tr><td>'.h(substr($r['created_at'],0,10)).'</td><td>'.h(label($r['item'])).'</td><td>'.$r['qty'].'</td><td>'.$r['weight_kg'].'</td><td>'.h($r['lab'] ?: 'Campus-wide').'</td><td>'.h($r['note']).'</td></tr>'; ?>
</tbody></table></div>
<?php page_end();
