(() => {
const $ = id => document.getElementById(id);
const COLORS = ['#e0533d','#f2b632','#3aa56b','#3b82c4'];
const LABELS = {desktop:'Desktops',laptop:'Laptops',monitor:'Monitors',keyboard:'Keyboards',mouse:'Mice',printer:'Printers',network:'Network gear',projector:'Projectors',plastic_items:'Plastic items',bottle_caps:'Bottle caps',keyboard_keys:'Keyboard keys',ram:'RAM',circuit_boards:'Circuit boards',wires:'Wires',leds:'LEDs',other:'Other'};
// 5x7 pixel font, each row is a 5-bit string
const F = {A:'01110,10001,10001,11111,10001,10001,10001',B:'11110,10001,10001,11110,10001,10001,11110',C:'01111,10000,10000,10000,10000,10000,01111',D:'11110,10001,10001,10001,10001,10001,11110',E:'11111,10000,10000,11110,10000,10000,11111',F:'11111,10000,10000,11110,10000,10000,10000',G:'01111,10000,10000,10011,10001,10001,01111',H:'10001,10001,10001,11111,10001,10001,10001',I:'11111,00100,00100,00100,00100,00100,11111',J:'00111,00010,00010,00010,00010,10010,01100',K:'10001,10010,10100,11000,10100,10010,10001',L:'10000,10000,10000,10000,10000,10000,11111',M:'10001,11011,10101,10101,10001,10001,10001',N:'10001,11001,10101,10011,10001,10001,10001',O:'01110,10001,10001,10001,10001,10001,01110',P:'11110,10001,10001,11110,10000,10000,10000',Q:'01110,10001,10001,10001,10101,10010,01101',R:'11110,10001,10001,11110,10100,10010,10001',S:'01111,10000,10000,01110,00001,00001,11110',T:'11111,00100,00100,00100,00100,00100,00100',U:'10001,10001,10001,10001,10001,10001,01110',V:'10001,10001,10001,10001,10001,01010,00100',W:'10001,10001,10001,10101,10101,11011,10001',X:'10001,10001,01010,00100,01010,10001,10001',Y:'10001,10001,01010,00100,00100,00100,00100',Z:'11111,00001,00010,00100,01000,10000,11111',
'0':'01110,10011,10101,10101,11001,10001,01110','1':'00100,01100,00100,00100,00100,00100,01110','2':'01110,10001,00001,00110,01000,10000,11111','3':'11110,00001,00001,01110,00001,00001,11110','4':'00010,00110,01010,10010,11111,00010,00010','5':'11111,10000,11110,00001,00001,10001,01110','6':'00110,01000,10000,11110,10001,10001,01110','7':'11111,00001,00010,00100,01000,01000,01000','8':'01110,10001,10001,01110,10001,10001,01110','9':'01110,10001,10001,01111,00001,00010,01100','!':'00100,00100,00100,00100,00100,00000,00100','?':'01110,10001,00001,00110,00100,00000,00100','.':'00000,00000,00000,00000,00000,01100,01100','-':'00000,00000,00000,11111,00000,00000,00000'};

let messages = ['ECOBOARD STI'], idx = 0, timer = null, history = [];

function api(path, body){
  return fetch('api/'+path, body ? {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)} : {})
    .then(r => r.json().then(d => { if(!r.ok) throw new Error(d.error || 'Request failed'); return d; }));
}

// ---------- Board ----------
let current = '';
function wrapText(t){ const L = []; let cur = ''; for (const w of t.split(' ')) { if (cur && (cur+' '+w).length > 12) { L.push(cur); cur = w; } else cur = cur ? cur+' '+w : w; } if (cur) L.push(cur); return L.slice(0,3); }
function draw(text){
  current = text; const b = $('board'); b.textContent = ''; b.setAttribute('aria-label', 'Board shows: ' + text);
  const lines = wrapText(text), G = 3;
  const cols = Math.max(...lines.map(l => [...l].reduce((n, c) => n + (F[c] ? 5 : 3) + 1, 0) - 1));
  const cs = getComputedStyle(b), avail = b.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
  const d = Math.max(4, Math.min(30, Math.floor((avail + G) / cols) - G));
  b.style.setProperty('--d', d + 'px'); b.style.setProperty('--g', G + 'px');
  let k = 0;
  lines.forEach(line => {
    const row = document.createElement('div'); row.className = 'ln'; row.setAttribute('aria-hidden','true');
    [...line].forEach(ch => {
      const g = document.createElement('div'); const rows = F[ch];
      if (!rows) { g.className = 'sp'; row.appendChild(g); return; }
      g.className = 'ch';
      rows.split(',').forEach(r => [...r].forEach((p, x) => {
        const c = document.createElement('button');
        c.type = 'button'; c.tabIndex = -1; c.className = 'cap' + (p === '1' ? ' on' : '');
        c.style.setProperty('--c', COLORS[(k + x) % 4]);
        c.addEventListener('click', () => c.classList.toggle('on'));
        g.appendChild(c);
      })); k++;
      row.appendChild(g);
    });
    b.appendChild(row);
  });
}
let rs; window.addEventListener('resize', () => { clearTimeout(rs); rs = setTimeout(() => current && draw(current), 120); });
function rotate(){ clearInterval(timer); draw(messages[idx % messages.length]); timer = setInterval(() => { idx++; draw(messages[idx % messages.length]); }, 4000); }

$('nightBtn').onclick = e => { const on = $('board').classList.toggle('night'); e.currentTarget.setAttribute('aria-pressed', on); };
$('touchBtn').onclick = () => {
  // Simulates the keyboard-switch circuit: flash caps and beep
  const b = $('board'); b.classList.add('night');
  try { const a = new (window.AudioContext||window.webkitAudioContext)(), o = a.createOscillator(); o.frequency.value = 660; o.connect(a.destination); o.start(); o.stop(a.currentTime+.15); } catch(e){}
  idx++; draw(messages[idx % messages.length]);
  api('message.php', {interaction:'touch', detail:'key pressed'}).catch(()=>{});
};
if ($('msgForm')) $('msgForm').onsubmit = e => {
  e.preventDefault(); $('msgErr').textContent = '';
  api('message.php', {message: $('msgInput').value}).then(d => { $('msgInput').value=''; messages = d.messages; idx = messages.length-1; rotate(); })
    .catch(err => $('msgErr').textContent = err.message);
};

// ---------- Dashboard ----------
function render(s){
  $('tItems').textContent = Number(s.totals.items).toLocaleString();
  $('tKg').textContent = (s.totals.grams/1000).toFixed(2);
  const max = Math.max(1, ...s.by_category.map(r => +r.qty));
  $('bars').innerHTML = s.by_category.map(r => `<div class="bar"><span>${LABELS[r.category]||r.category}</span><i style="width:${(r.qty/max)*100}%"></i><span>${r.qty}</span></div>`).join('') || '<p class="hint">No entries yet. Log your first collection.</p>';
  $('phases').textContent = s.by_phase.map(p => `${p.phase}: ${p.qty}`).join('  |  ');
  if (s.messages && s.messages.length) { const changed = s.messages.join() !== messages.join(); messages = s.messages; if (changed || !timer) rotate(); }
}
function load(){ api('stats.php').then(render).catch(err => { $('bars').innerHTML = `<p class="err">${err.message}</p>`; rotate(); }); }

// ---------- Chat + voice ----------
function say(role, text){
  const m = document.createElement('div'); m.className = 'm ' + (role==='user'?'u':'a'); m.textContent = text;
  $('chatLog').appendChild(m); $('chatLog').scrollTop = 1e9;
}
function speak(text){
  if (!$('speakToggle').checked || !('speechSynthesis' in window)) return;
  speechSynthesis.cancel(); const u = new SpeechSynthesisUtterance(text); u.lang = 'en-US'; speechSynthesis.speak(u);
}
function ask(q, viaVoice){
  q = q.trim(); if(!q) return;
  say('user', q); $('chatInput').value = '';
  api('chat.php', {message:q, history}).then(d => {
    say('a', d.reply); speak(d.reply);
    history.push({role:'user',content:q},{role:'assistant',content:d.reply});
    api('message.php', {interaction: viaVoice?'voice':'chat', detail:q.slice(0,200)}).catch(()=>{});
  }).catch(err => say('a', err.message));
}
$('chatForm').onsubmit = e => { e.preventDefault(); ask($('chatInput').value, false); };

const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
if (!SR) { $('micBtn').disabled = true; $('voiceNote').textContent = 'Voice input needs Chrome, Edge or Safari. You can still type your question.'; }
else {
  const rec = new SR(); rec.lang = 'en-US'; rec.interimResults = false;
  rec.onresult = e => ask(e.results[0][0].transcript, true);
  rec.onend = () => $('micBtn').setAttribute('aria-pressed','false');
  rec.onerror = e => { $('voiceNote').textContent = e.error === 'not-allowed' ? 'Microphone blocked. Allow it in the browser address bar. Voice also needs https or localhost.' : 'Could not hear you. Try again.'; };
  $('micBtn').onclick = () => { if ($('micBtn').getAttribute('aria-pressed')==='true') return rec.stop(); speechSynthesis.cancel(); $('voiceNote').textContent=''; $('micBtn').setAttribute('aria-pressed','true'); rec.start(); };
}
say('a', 'Hi, I am the EcoBoard assistant. Ask me about the build, safety, or how much waste we have diverted.');
load();
})();
