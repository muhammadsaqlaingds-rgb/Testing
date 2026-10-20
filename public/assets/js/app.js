(() => {
  const $ = id => document.getElementById(id);
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const COLORS = ['#4f46e5','#0ea5e9','#059669','#d97706','#e11d48','#7c3aed','#0d9488','#c026d3'];
  const hue = s => COLORS[[...String(s)].reduce((a, c) => a + c.charCodeAt(0), 0) % COLORS.length];
  const initials = r => (r.name || r.empCode || '?').split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();

  const time12 = t => {            // "2026-10-01T21:40:17" -> "9:40 PM"
    if (!t) return null;
    const [h, m] = t.slice(11, 16).split(':').map(Number);
    return `${(h % 12) || 12}:${String(m).padStart(2, '0')} ${h >= 12 ? 'PM' : 'AM'}`;
  };
  const dayShift = (inT, outT) => inT && outT && outT.slice(0, 10) > inT.slice(0, 10) ? '<sup>+1</sup>' : '';
  const shiftDate = (d, n) => { const x = new Date(d + 'T00:00:00'); x.setDate(x.getDate() + n); return x.toLocaleDateString('en-CA'); };
  const niceDate = d => new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' });

  let rows = [], current = null, status = 'Present', timer;

  async function api(action, body = {}, method = 'POST') {
    const opt = { method, headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF } };
    let url = 'api.php?action=' + action;
    if (method === 'GET') url += '&' + new URLSearchParams(body);
    else opt.body = JSON.stringify({ action, ...body });
    const json = await (await fetch(url, opt)).json();
    if (!json.ok) throw new Error(json.error);
    return json.data;
  }
  function toast(msg, type = 'ok') {
    const t = $('toast'); t.textContent = msg; t.className = 'show ' + type;
    clearTimeout(t._h); t._h = setTimeout(() => t.className = '', 2200);
  }
  const guard = fn => async (...a) => { try { await fn(...a); } catch (e) { toast(e.message, 'err'); } };
  const busy = (btn, on) => btn && (btn.disabled = on);

  /* ---------- list ---------- */
  const loadList = guard(async () => {
    $('subtitle').textContent = niceDate($('date').value);
    $('list').innerHTML = '<div class="skel"></div><div class="skel"></div><div class="skel"></div>';
    rows = await api('list', { date: $('date').value, q: $('q').value }, 'GET');
    render();
  });

  function render() {
    const q = $('q').value.trim().toLowerCase();
    const shown = rows.filter(r => !q || r.empCode.toLowerCase().includes(q) || r.name.toLowerCase().includes(q));
    $('stTotal').textContent = shown.length;
    $('stPresent').textContent = shown.filter(r => r.status === 'Present').length;
    $('stAbsent').textContent = shown.filter(r => r.status === 'Absent').length;
    $('stWarn').textContent = shown.filter(r => (r.checkIn && !r.checkOut) || (!r.checkIn && r.checkOut)).length;

    $('list').innerHTML = shown.length ? shown.map(r => {
      const i = time12(r.checkIn), o = time12(r.checkOut);
      return `<article class="item" data-id="${r.id}" style="animation-delay:${Math.min(rows.indexOf(r), 12) * 25}ms">
        <div class="lead">
          <div class="avatar" style="--av:${hue(r.empCode)}">${esc(initials(r))}</div>
          <div class="who"><b>${esc(r.name || 'Employee')}</b><small>${esc(r.empCode)}</small><span class="shift">${esc(r.shift || '-')}</span></div>
        </div>
        <span class="badge ${esc(r.status)}">${esc(r.status || '—')}</span>
        <div class="times">
          <div class="t ${i ? 'in' : 'none'}"><small>In</small><b>${i || '—'}</b></div>
          <div class="t ${o ? 'out' : 'none'}"><small>Out</small><b>${o || '—'}${dayShift(r.checkIn, r.checkOut)}</b></div>
          <div class="pc">${r.totalPunches}<small>punch</small></div>
        </div>
        <span class="go">›</span>
      </article>`;
    }).join('') : '<div class="empty"><big>📭</big>No attendance records for this date.</div>';
  }

  /* ---------- sheet ---------- */
  function setTab(name) {
    document.querySelectorAll('.tab').forEach(t => t.classList.toggle('on', t.dataset.tab === name));
    $('pane-shift').classList.toggle('on', name === 'shift');
    $('pane-punch').classList.toggle('on', name === 'punch');
  }
  function setStatus(v) {
    status = v;
    document.querySelectorAll('#mStatus button').forEach(b => b.classList.toggle('on', b.dataset.v === v));
  }
  function fillShift(r) {
    $('mIn').value = r.checkIn || ''; $('mOut').value = r.checkOut || ''; setStatus(r.status || 'Present');
  }
  function openSheet(r) {
    current = r;
    const av = $('mAvatar'); av.textContent = initials(r); av.style.setProperty('--av', hue(r.empCode));
    $('mName').textContent = r.name || 'Employee';
    $('mMeta').textContent = `${r.empCode} · Shift ${r.shift || '-'} · ${r.date}`;
    $('newPunch').value = `${r.date}T09:00:00`;
    fillShift(r); setTab('shift'); $('modal').showModal(); loadPunches();
  }
  async function refreshShift() {
    rows = await api('list', { date: $('date').value, q: '' }, 'GET'); render();
    const fresh = rows.find(x => x.id === current.id);
    if (fresh) { current = fresh; fillShift(fresh); }
  }

  const loadPunches = guard(async () => {
    const p = await api('punches', { empCode: current.empCode, date: current.date }, 'GET');
    $('pCount').textContent = p.length;
    $('punchList').innerHTML = p.length ? p.map(x => `
      <div class="pr" data-id="${x.id}">
        <input type="datetime-local" step="1" value="${x.time}">
        <button class="sq ok" data-act="save" aria-label="Save">✓</button>
        <button class="sq del" data-act="del" aria-label="Delete">🗑</button>
        <span class="src">${x.manual ? '✍️ Added manually' : '📟 Device ' + esc(x.deviceIp)}</span>
      </div>`).join('') : '<div class="empty" style="padding:24px">No punches in this business day<br><small>(09:00 → next day 08:00)</small></div>';
  });

  /* ---------- events ---------- */
  $('list').onclick = e => { const a = e.target.closest('.item'); if (a) openSheet(rows.find(r => r.id === a.dataset.id)); };
  $('date').onchange = loadList;
  $('prev').onclick = () => { $('date').value = shiftDate($('date').value, -1); loadList(); };
  $('next').onclick = () => { $('date').value = shiftDate($('date').value, 1); loadList(); };
  $('today').onclick = () => { $('date').value = new Date().toLocaleDateString('en-CA'); loadList(); };
  $('q').oninput = () => { clearTimeout(timer); timer = setTimeout(render, 150); };

  $('close').onclick = () => $('modal').close();
  $('modal').addEventListener('click', e => { if (e.target === $('modal')) $('modal').close(); });
  document.querySelectorAll('.tab').forEach(t => t.onclick = () => setTab(t.dataset.tab));
  $('mStatus').onclick = e => { const b = e.target.closest('button'); if (b) setStatus(b.dataset.v); };

  $('saveShift').onclick = guard(async function () {
    const b = $('saveShift'); busy(b, true);
    try {
      await api('saveShift', { id: current.id, checkIn: $('mIn').value, checkOut: $('mOut').value, status });
      toast('Shift saved ✓'); await refreshShift();
    } finally { busy(b, false); }
  });
  $('recalc').onclick = guard(async () => {
    const b = $('recalc'); busy(b, true);
    try { await api('recalc', { id: current.id }); toast('Recalculated from punches'); await refreshShift(); }
    finally { busy(b, false); }
  });
  $('addPunch').onclick = guard(async () => {
    await api('addPunch', { empCode: current.empCode, time: $('newPunch').value });
    toast('Punch added'); await loadPunches();
  });
  $('punchList').onclick = guard(async e => {
    const b = e.target.closest('button[data-act]'); if (!b) return;
    const row = b.closest('.pr'), id = row.dataset.id;
    if (b.dataset.act === 'save') { await api('savePunch', { id, time: row.querySelector('input').value }); toast('Punch saved ✓'); }
    else if (confirm('Delete this punch?')) { await api('deletePunch', { id }); toast('Punch deleted'); }
    else return;
    await loadPunches();
  });

  loadList();
})();
