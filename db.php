<?php
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) { session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax']); session_start(); }
set_exception_handler(function ($e) { http_response_code(500); echo 'Something went wrong. Check config.php and that schema.sql was imported.'; error_log($e->getMessage()); });

function db(): PDO {
  static $pdo = null;
  if (!$pdo) $pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
  return $pdo;
}
function q(string $sql, array $p = []): PDOStatement { $s = db()->prepare($sql); $s->execute($p); return $s; }
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function json_out($data, int $code = 200): void { http_response_code($code); header('Content-Type: application/json'); echo json_encode($data); exit; }
function labs(): array { return q("SELECT id,name FROM labs ORDER BY name")->fetchAll(); }
function label(string $s): string { return ucfirst(str_replace('_', ' ', $s)); }

// ---- auth ----
function me(): ?array {
  static $u = false;
  if ($u === false) { $u = null; if (!empty($_SESSION['uid'])) $u = q("SELECT id,name,email,role FROM users WHERE id=? AND active=1", [$_SESSION['uid']])->fetch() ?: null; }
  return $u;
}
function is_staff(): bool { $u = me(); return $u && in_array($u['role'], ['admin','technician'], true); }
function need(array $roles = []): array {
  $u = me();
  if (!$u) { header('Location: login.php'); exit; }
  if ($roles && !in_array($u['role'], $roles, true)) {
    http_response_code(403); page_start('No access'); echo '<p class="err">Your role cannot open this page.</p>'; page_end(); exit;
  }
  return $u;
}
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.csrf().'">'; }
function check_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); exit('Session expired. Go back, refresh the page and try again.'); } }
function flash(string $m): void { $_SESSION['flash'] = $m; }

// ---- layout ----
function page_start(string $title): void {
  $u = me();
  echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'.h($title).' | EcoBoard STI</title><link rel="stylesheet" href="assets/style.css"></head><body>';
  echo '<nav class="nav"><a class="brand" href="board.php">EcoBoard STI</a><div class="links">';
  $l = [['board.php','Board']];
  if ($u) {
    $l[] = ['dashboard.php','Home']; $l[] = ['report_new.php','New report']; $l[] = ['reports.php', is_staff() ? 'All reports' : 'My reports'];
    if (is_staff()) { $l[] = ['assets.php','Devices']; $l[] = ['plastics.php','Plastics']; $l[] = ['analytics.php','Analytics']; }
    if ($u['role'] === 'admin') $l[] = ['users.php','Users'];
  }
  foreach ($l as $x) echo '<a href="'.$x[0].'"'.(basename($_SERVER['SCRIPT_NAME']) === $x[0] ? ' aria-current="page"' : '').'>'.$x[1].'</a>';
  echo '</div><div class="user">';
  echo $u ? '<span class="chip">'.h($u['name']).' <em>'.h($u['role']).'</em></span><a href="logout.php">Log out</a>' : '<a href="login.php">Log in</a><a class="solid" href="register.php">Sign up</a>';
  echo '</div></nav><main class="wrap"><h1 class="pt">'.h($title).'</h1>';
  if (!empty($_SESSION['flash'])) { echo '<p class="ok" role="status">'.h($_SESSION['flash']).'</p>'; unset($_SESSION['flash']); }
}
function page_end(): void { echo '</main><footer class="wrap">EcoBoard STI: e-waste and plastics management for school labs.</footer></body></html>'; }
function bars(array $pairs, string $unit = ''): string {
  if (!$pairs) return '<p class="hint">No data yet.</p>';
  $max = max(1, max($pairs)); $o = '';
  foreach ($pairs as $k => $v) $o .= '<div class="bar"><span>'.h($k).'</span><i style="width:'.round($v/$max*100).'%"></i><span>'.h(round($v,1).$unit).'</span></div>';
  return $o;
}

// ---- carbon + lifecycle ----
function asset_carbon(array $a): array {
  $emb = $a['embodied_kg_co2e'] !== null ? (float)$a['embodied_kg_co2e'] : (EMBODIED_KG[$a['type']] ?? EMBODIED_KG['other']);
  $age = $a['purchase_date'] ? max(0, (time() - strtotime($a['purchase_date'])) / 31557600) : 0;
  $opy = (float)$a['power_w'] * (float)$a['hours_per_day'] * SCHOOL_DAYS / 1000 * GRID_KG_PER_KWH;
  $avoid = $a['status'] === 'donated_reused' ? $emb*REUSE_CREDIT : ($a['status'] === 'recycled' ? $emb*RECYCLE_CREDIT : 0);
  $life = (float)$a['lifespan_years'];
  return ['embodied'=>$emb,'age'=>$age,'op_year'=>$opy,'total'=>$emb + $opy*$age,'avoided'=>$avoid,'remaining'=>$life-$age,'eol'=>$life > 0 && $age >= 0.8*$life];
}
function carbon_summary(): array {
  $s = ['embodied'=>0,'op_year'=>0,'lifecycle'=>0,'avoided_ewaste'=>0,'eol_count'=>0];
  foreach (q("SELECT * FROM assets")->fetchAll() as $a) {
    $c = asset_carbon($a);
    if (!in_array($a['status'], ['recycled','disposed','donated_reused'], true)) $s['op_year'] += $c['op_year'];
    $s['embodied'] += $c['embodied']; $s['lifecycle'] += $c['total']; $s['avoided_ewaste'] += $c['avoided'];
    if ($c['eol'] && in_array($a['status'], ['in_use','needs_repair','in_repair'], true)) $s['eol_count']++;
  }
  $p = (float)q("SELECT COALESCE(SUM(weight_kg),0) FROM plastic_log")->fetchColumn();
  $s['plastic_kg'] = $p; $s['avoided_plastic'] = $p * PLASTIC_CO2E_PER_KG; $s['avoided_total'] = $s['avoided_ewaste'] + $s['avoided_plastic'];
  return array_map(fn($v) => round($v, 1), $s);
}
function kpis(): array {
  $a = q("SELECT COUNT(*) total, COALESCE(SUM(status='in_use'),0) in_use, COALESCE(SUM(status IN('needs_repair','in_repair')),0) repair,
          COALESCE(SUM(status IN('recycled','donated_reused')),0) diverted, COALESCE(SUM(CASE WHEN status IN('recycled','donated_reused') THEN weight_kg END),0) ewaste_kg FROM assets")->fetch();
  $r = q("SELECT COALESCE(SUM(status='open'),0) open_r, COALESCE(SUM(status='in_review'),0) review_r, COUNT(*) total_r FROM reports")->fetch();
  return $a + $r + ['carbon'=>carbon_summary()];
}

// ---- public stats for the board page ----
function stats(): array {
  $rows = q("SELECT type category, COUNT(*) qty, ROUND(SUM(weight_kg)*1000) grams FROM assets WHERE status IN('stored','recycled','donated_reused','disposed') GROUP BY type")->fetchAll();
  $p = q("SELECT COALESCE(SUM(qty),0) qty, COALESCE(SUM(weight_kg),0) kg FROM plastic_log")->fetch();
  if ($p['qty']) $rows[] = ['category'=>'plastic_items','qty'=>$p['qty'],'grams'=>round($p['kg']*1000)];
  $items = 0; $grams = 0; foreach ($rows as $r) { $items += $r['qty']; $grams += $r['grams']; }
  return ['by_category'=>$rows,'totals'=>['items'=>$items,'grams'=>$grams],
    'by_phase'=>q("SELECT status phase, COUNT(*) qty FROM assets GROUP BY status")->fetchAll(),
    'messages'=>q("SELECT message FROM board_messages WHERE active=1 ORDER BY id")->fetchAll(PDO::FETCH_COLUMN)];
}
