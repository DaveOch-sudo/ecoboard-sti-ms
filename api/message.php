<?php
// Adds a message to the board display, or records a board interaction
require_once __DIR__ . '/../db.php';
$in = json_decode(file_get_contents('php://input'), true) ?: [];
try {
  if (!empty($in['interaction'])) {
    $t = in_array($in['interaction'], ['touch','voice','chat','qr'], true) ? $in['interaction'] : 'touch';
    db()->prepare("INSERT INTO interactions (type,detail) VALUES (?,?)")->execute([$t, mb_substr($in['detail'] ?? '', 0, 255)]);
    json_out(['ok'=>true]);
  }
  if (!is_staff()) json_out(['error'=>'Log in as staff to change board messages.'], 403);
  $m = strtoupper(trim($in['message'] ?? ''));
  if ($m === '' || mb_strlen($m) > 24 || !preg_match('/^[A-Z0-9 !?.\-]+$/', $m)) json_out(['error'=>'Use up to 24 letters, numbers or spaces.'], 422);
  db()->prepare("INSERT INTO board_messages (message) VALUES (?)")->execute([$m]);
  json_out(['ok'=>true]+stats());
} catch (Throwable $e) { json_out(['error'=>'Could not save.'], 500); }
