<?php
require_once __DIR__ . '/db.php';
page_start('The EcoBoard');
?>
<p class="hint">Bottle caps and computer parts, turned into a signboard that talks back. <?= me() ? '' : '<a href="login.php">Log in</a> to report waste or manage the system.' ?></p>
<section class="board-wrap">
    <div id="board" class="board" role="img" aria-label="Cap-pixel display"></div>
    <div class="board-controls">
      <button id="nightBtn" type="button" aria-pressed="false" class="ghost">Night mode (LEDs on)</button>
      <button id="touchBtn" type="button" class="ghost">Press a keyboard key</button>
      <?php if (is_staff()): ?><form id="msgForm" class="inline"><label for="msgInput" class="sr">New board message</label><input id="msgInput" maxlength="24" placeholder="New slogan, up to 24 characters"><button>Show on board</button></form><?php endif; ?>
    </div><p id="msgErr" class="err" role="alert"></p>
  </section>
<div class="cols">
  <section class="panel"><h2>Ask the board</h2>
    <div id="chatLog" class="chatlog" aria-live="polite"></div>
    <form id="chatForm" class="inline"><label for="chatInput" class="sr">Your question</label><input id="chatInput" placeholder="How much waste have we diverted?"><button>Send</button><button type="button" id="micBtn" aria-pressed="false">Speak</button></form>
    <label class="check"><input type="checkbox" id="speakToggle" checked> Read answers aloud</label><p id="voiceNote" class="hint"></p></section>
  <section class="panel"><h2>Waste kept out of landfill</h2>
    <div class="totals"><div><b id="tItems">0</b><span>items</span></div><div><b id="tKg">0</b><span>kilograms</span></div></div>
    <div id="bars" class="bars"></div><p id="phases" class="hint"></p></section>
</div>
<script src="assets/app.js"></script>
<?php page_end();
