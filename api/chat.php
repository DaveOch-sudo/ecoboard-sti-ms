<?php
require_once __DIR__ . '/../db.php';
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$q = mb_substr(trim($in['message'] ?? ''), 0, 500);
if ($q === '') json_out(['error'=>'Say or type a question first.'], 422);
try { $s = stats(); } catch (Throwable $e) { $s = ['totals'=>['items'=>0,'grams'=>0],'by_category'=>[],'by_phase'=>[],'messages'=>[]]; }

$facts = "Project: EcoBoard STI, an upcycled signboard built from bottle caps and e-waste (keyboard keys, RAM, circuit boards, wires, LEDs) for a school computer lab. "
 ."It has LED backlighting, a touch/button feature, and a QR code linking to this dashboard. "
 ."Build phases: 1 collection drives, 2 sorting and testing salvage, 3 fabrication and circuit testing, 4 installation and awareness campaign. "
 ."Safety: separate swollen batteries, never open CRT monitors or power supplies, wear gloves, keep lead and mercury parts out of general waste. "
 ."Live data: ".json_encode($s + ["carbon"=>carbon_summary()]);

// Built-in answers used when no API key is set or the AI call fails
function local_answer(string $q, array $s): string {
  $q = strtolower($q); $t = $s['totals'];
  if (preg_match('/how (many|much)|total|diverted|stats/', $q)) return "So far we have logged {$t['items']} items, about ".round($t['grams']/1000,2)." kilograms, kept out of landfill.";
  if (preg_match('/led|light|solar|circuit/', $q)) return "Scavenged LEDs from old PC towers backlight the board. Test each one with a coin cell or a 5 volt supply and a resistor before mounting.";
  if (preg_match('/safe|hazard|battery|lead|mercury/', $q)) return "Separate swollen batteries first, wear gloves, and never open monitors or power supplies. Lead and mercury parts must not go in general waste.";
  if (preg_match('/phase|step|plan|how do|build/', $q)) return "There are four phases: collect, sort and test, fabricate and wire the circuit, then install the board with an awareness campaign.";
  if (preg_match('/cap|plastic|bottle/', $q)) return "Bottle caps are small and durable, so they escape recycling. We sort them by color and use them as pixels to spell out slogans.";
  return "I can answer questions about the board, waste totals, safety, or build steps. Try asking how much waste we have diverted.";
}
if (!ANTHROPIC_API_KEY) json_out(['reply'=>local_answer($q,$s),'source'=>'local']);

$hist = array_map(fn($m)=>['role'=>($m['role']??'')==='user'?'user':'assistant','content'=>(string)($m['content']??'')], array_slice($in['history'] ?? [], -6));
$payload = ['model'=>AI_MODEL,'max_tokens'=>300,
  'system'=>"You are the voice assistant of EcoBoard STI. Answer in 1 to 3 short spoken sentences, plain text, no markdown. Use only these facts and say so when something is not covered: ".$facts,
  'messages'=>array_merge($hist, [['role'=>'user','content'=>$q]])];
$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_POST=>true, CURLOPT_TIMEOUT=>20,
  CURLOPT_HTTPHEADER=>['content-type: application/json','x-api-key: '.ANTHROPIC_API_KEY,'anthropic-version: 2023-06-01'],
  CURLOPT_POSTFIELDS=>json_encode($payload)]);
$res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
$text = (json_decode((string)$res, true)['content'][0]['text'] ?? null);
if ($code !== 200 || !$text) json_out(['reply'=>local_answer($q,$s),'source'=>'local']);
json_out(['reply'=>$text,'source'=>'ai']);
