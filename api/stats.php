<?php
require_once __DIR__ . '/../db.php';
try { json_out(stats()); } catch (Throwable $e) { json_out(['error'=>'Database unavailable. Run schema.sql and check config.php.'], 500); }
