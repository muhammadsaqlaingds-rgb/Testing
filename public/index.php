<?php require __DIR__ . '/bootstrap.php'; ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#4f46e5">
<meta name="apple-mobile-web-app-capable" content="yes">
<title>Attendance</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="top">
  <div class="top-in">
    <div class="brand">
      <span class="logo">🕒</span>
      <div><h1>Attendance</h1><small id="subtitle">Daily records</small></div>
    </div>
    <div class="datebar">
      <button class="icon-btn" id="prev" aria-label="Previous day">‹</button>
      <input type="date" id="date" value="<?= date('Y-m-d') ?>">
      <button class="icon-btn" id="next" aria-label="Next day">›</button>
      <button class="chip-btn" id="today">Today</button>
    </div>
    <label class="search">
      <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
      <input type="search" id="q" placeholder="Search employee code or name" autocomplete="off">
    </label>
  </div>
</header>

<main>
  <section class="stats" id="stats">
    <div class="stat s-total"><b id="stTotal">0</b><span>Total</span></div>
    <div class="stat s-present"><b id="stPresent">0</b><span>Present</span></div>
    <div class="stat s-absent"><b id="stAbsent">0</b><span>Absent</span></div>
    <div class="stat s-warn"><b id="stWarn">0</b><span>Incomplete</span></div>
  </section>

  <div class="cols"><span>Employee</span><span>Check in</span><span>Check out</span><span>Punches</span><span>Status</span><span></span></div>
  <div id="list" class="list"></div>
</main>

<!-- Edit sheet -->
<dialog id="modal">
  <div class="grab"></div>
  <div class="sheet-head">
    <div class="avatar" id="mAvatar"></div>
    <div class="sh-txt"><h2 id="mName"></h2><p id="mMeta"></p></div>
    <button class="icon-btn" id="close" aria-label="Close">✕</button>
  </div>

  <div class="tabs" role="tablist">
    <button class="tab on" data-tab="shift">Shift record</button>
    <button class="tab" data-tab="punch">Device punches <i id="pCount">0</i></button>
  </div>

  <section class="pane on" id="pane-shift">
    <div class="field-row">
      <label class="field in"><span>Check in</span><input type="datetime-local" step="1" id="mIn"></label>
      <label class="field out"><span>Check out</span><input type="datetime-local" step="1" id="mOut"></label>
    </div>
    <div class="field"><span>Status</span>
      <div class="seg" id="mStatus">
        <button data-v="Present">Present</button><button data-v="Absent">Absent</button><button data-v="Leave">Leave</button>
      </div>
    </div>
    <p class="hint">Saved with <code>manuallyEdited: false</code></p>
    <div class="btns">
      <button class="btn ghost" id="recalc">↻ Recalculate from punches</button>
      <button class="btn primary" id="saveShift">Save shift</button>
    </div>
  </section>

  <section class="pane" id="pane-punch">
    <div id="punchList" class="punch-list"></div>
    <div class="add-punch">
      <input type="datetime-local" step="1" id="newPunch">
      <button class="btn primary sm" id="addPunch">＋ Add</button>
    </div>
    <p class="hint">Edits here change <code>attendanceevents</code>. Press “Recalculate” on the Shift tab to refresh check in/out.</p>
  </section>
</dialog>

<div id="toast"></div>
<script>window.CSRF = <?= json_encode($_SESSION['csrf']) ?>;</script>
<script src="assets/js/app.js"></script>
</body>
</html>
