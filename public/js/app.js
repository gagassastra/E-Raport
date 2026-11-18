/* E-Raport PKBM AL-QUDWAH — interaksi antarmuka (tanpa dependensi, kecuali Chart.js untuk grafik) */
(function () {
  'use strict';
  const $ = (s, c = document) => c.querySelector(s);
  const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));

  // Sidebar mobile
  $$('[data-nav-toggle]').forEach(b => b.addEventListener('click', () => document.body.classList.toggle('nav-open')));
  $('.overlay') && $('.overlay').addEventListener('click', () => document.body.classList.remove('nav-open'));

  // Dropdown
  $$('[data-dropdown]').forEach(d => {
    d.querySelector('[data-dropdown-btn]').addEventListener('click', e => {
      e.stopPropagation();
      $$('[data-dropdown].open').forEach(o => o !== d && o.classList.remove('open'));
      d.classList.toggle('open');
    });
  });
  document.addEventListener('click', () => $$('[data-dropdown].open').forEach(o => o.classList.remove('open')));

  // Toast
  $$('.toast').forEach(t => {
    t.querySelector('button').addEventListener('click', () => t.remove());
    setTimeout(() => t.remove(), t.classList.contains('danger') ? 12000 : 6000);
  });

  // Modal konfirmasi
  const modal = $('#confirm-modal');
  let pending = null;
  function openConfirm(form, btn) {
    pending = { form, btn };
    const src = btn || form;
    $('#confirm-title').textContent = src.dataset.confirmTitle || 'Konfirmasi';
    $('#confirm-text').textContent = src.dataset.confirm;
    const okBtn = $('#confirm-ok');
    okBtn.className = 'btn ' + (src.dataset.confirmClass || 'red');
    okBtn.textContent = src.dataset.confirmOk || 'Ya, lanjutkan';
    const wrap = $('#confirm-prompt-wrap'), ta = $('#confirm-prompt');
    if (src.dataset.prompt) { wrap.hidden = false; $('#confirm-prompt-label').textContent = src.dataset.prompt; ta.value = ''; ta.required = true; }
    else { wrap.hidden = true; ta.required = false; }
    $('#confirm-err').hidden = true;
    modal.classList.add('open');
    (src.dataset.prompt ? ta : okBtn).focus();
  }
  function closeConfirm() { modal.classList.remove('open'); pending = null; }
  if (modal) {
    $('#confirm-cancel').addEventListener('click', closeConfirm);
    modal.addEventListener('click', e => { if (e.target === modal) closeConfirm(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeConfirm(); });
    $('#confirm-ok').addEventListener('click', () => {
      if (!pending) return;
      const { form, btn } = pending, src = btn || form, ta = $('#confirm-prompt');
      if (src.dataset.prompt) {
        if (!ta.value.trim()) { $('#confirm-err').hidden = false; ta.focus(); return; }
        let h = form.querySelector('input[name="' + (src.dataset.promptName || 'alasan') + '"][data-injected]');
        if (!h) { h = document.createElement('input'); h.type = 'hidden'; h.name = src.dataset.promptName || 'alasan'; h.dataset.injected = '1'; form.appendChild(h); }
        h.value = ta.value.trim();
      }
      form.dataset.confirmed = '1';
      closeConfirm();
      if (btn && btn.name) { const hh = document.createElement('input'); hh.type = 'hidden'; hh.name = btn.name; hh.value = btn.value; form.appendChild(hh); }
      if (btn && btn.formAction) form.action = btn.formAction;
      form.requestSubmit ? form.submit() : form.submit();
    });
  }
  document.addEventListener('click', e => {
    const btn = e.target.closest('button[data-confirm],input[type=submit][data-confirm]');
    if (btn && btn.form) { e.preventDefault(); openConfirm(btn.form, btn); }
  });
  document.addEventListener('submit', e => {
    const f = e.target;
    if (f.dataset.confirm && !f.dataset.confirmed) { e.preventDefault(); openConfirm(f, null); return; }
    // loading state
    $$('button[type=submit].btn, button:not([type]).btn', f).forEach(b => { if (!b.dataset.noload) { setTimeout(() => b.classList.add('loading'), 0); } });
  });
  window.addEventListener('pageshow', () => $$('.btn.loading').forEach(b => b.classList.remove('loading')));

  // Tampilkan/sembunyikan kata sandi
  $$('[data-pw-toggle]').forEach(b => b.addEventListener('click', () => { const i = b.parentElement.querySelector('input'); i.type = i.type === 'password' ? 'text' : 'password'; }));

  // Auto-submit filter
  $$('[data-autosubmit]').forEach(el => el.addEventListener('change', () => el.form.submit()));

  // Pilihan tingkat mengikuti program (Paket B: 7-9, Paket C: 10-12)
  $$('select[data-tingkat-target]').forEach(sel => {
    const target = document.querySelector(sel.dataset.tingkatTarget);
    if (!target) return;
    const sync = () => {
      const opt = sel.options[sel.selectedIndex];
      const awal = parseInt(opt && opt.dataset.awal), akhir = parseInt(opt && opt.dataset.akhir);
      const cur = target.value; target.innerHTML = '';
      if (!awal) { target.add(new Option('— pilih program dahulu —', '')); return; }
      target.add(new Option('— pilih tingkat —', ''));
      for (let i = awal; i <= akhir; i++) { const o = new Option('Kelas ' + i, i); if (String(i) === cur || String(i) === target.dataset.selected) o.selected = true; target.add(o); }
      target.dataset.selected = '';
    };
    sel.addEventListener('change', sync); sync();
  });

  // Pilih semua checkbox
  $$('[data-check-all]').forEach(c => c.addEventListener('change', () => $$(c.dataset.checkAll).forEach(x => { if (!x.disabled) x.checked = c.checked; })));

  // Grafik
  if (window.Chart) {
    Chart.defaults.font.family = '"Segoe UI", system-ui, sans-serif';
    Chart.defaults.color = '#6b7686';
    $$('canvas[data-chart]').forEach(c => {
      const cfg = JSON.parse(c.dataset.chart);
      cfg.options = Object.assign({ responsive: true, maintainAspectRatio: false }, cfg.options || {});
      new Chart(c, cfg);
    });
  }

  // Input nilai: hitung Nilai Akhir langsung (nilai kosong bukan nol)
  $$('table[data-score-table]').forEach(tbl => {
    const mode = tbl.dataset.mode, w = [parseFloat(tbl.dataset.ws), parseFloat(tbl.dataset.wa), parseFloat(tbl.dataset.wb)];
    const recalc = tr => {
      const vals = $$('input.sc', tr).map(i => i.value.trim() === '' ? null : parseFloat(i.value.replace(',', '.')));
      let ok = true;
      $$('input.sc', tr).forEach((i, k) => { const v = vals[k]; const bad = v !== null && (isNaN(v) || v < 0 || v > 100); i.classList.toggle('bad', bad); if (bad) ok = false; });
      const out = $('.final', tr), st = $('.stt', tr);
      if (vals.includes(null) || !ok) { out.textContent = '—'; if (st) { const any = vals.some(v => v !== null); st.className = 'badge stt ' + (any ? 'amber' : ''); st.textContent = any ? 'Belum lengkap' : 'Kosong'; } return; }
      const r = mode === 'bobot' ? (vals[0] * w[0] + vals[1] * w[1] + vals[2] * w[2]) / 100 : (vals[0] + vals[1] + vals[2]) / 3;
      out.textContent = Math.round(r);
      if (st) { st.className = 'badge stt green'; st.textContent = 'Lengkap'; }
    };
    $$('tbody tr[data-row]', tbl).forEach(tr => { $$('input.sc', tr).forEach(i => i.addEventListener('input', () => recalc(tr))); recalc(tr); });
    // Navigasi Enter -> baris berikutnya
    tbl.addEventListener('keydown', e => {
      if (e.key !== 'Enter' || !e.target.classList.contains('sc')) return;
      e.preventDefault();
      const col = $$('input.sc', e.target.closest('tr')).indexOf(e.target);
      const next = e.target.closest('tr').nextElementSibling;
      if (next) { const ins = $$('input.sc', next); ins[col] && ins[col].focus(); }
    });
    $$('[data-fill-desc]').forEach(b => b.addEventListener('click', () => {
      const ta = document.getElementById(b.dataset.fillDesc); if (ta) { ta.value = b.dataset.text; ta.focus(); }
    }));
  });

  // Tanda tangan digital: gambar dengan mouse / layar sentuh -> SVG vektor
  $$('[data-signature-pad]').forEach(box => {
    const canvas = $('canvas', box), ctx = canvas.getContext('2d'), out = $('input[name=svg]', box.closest('form'));
    const ratio = window.devicePixelRatio || 1;
    const W = 560, H = 200;
    canvas.width = W * ratio; canvas.height = H * ratio; ctx.scale(ratio, ratio);
    ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0b1b3a';
    let strokes = [], cur = null;
    const pos = e => { const r = canvas.getBoundingClientRect(); return [Math.round((e.clientX - r.left) * W / r.width * 10) / 10, Math.round((e.clientY - r.top) * H / r.height * 10) / 10]; };
    canvas.addEventListener('pointerdown', e => { e.preventDefault(); canvas.setPointerCapture(e.pointerId); cur = [pos(e)]; ctx.beginPath(); ctx.moveTo(...cur[0]); });
    canvas.addEventListener('pointermove', e => { if (!cur) return; const p = pos(e); cur.push(p); ctx.lineTo(...p); ctx.stroke(); });
    const end = () => { if (cur && cur.length) { if (cur.length === 1) { ctx.lineTo(cur[0][0] + .1, cur[0][1]); ctx.stroke(); } strokes.push(cur); } cur = null; build(); };
    canvas.addEventListener('pointerup', end); canvas.addEventListener('pointercancel', end);
    function build() {
      if (!strokes.length) { out.value = ''; return; }
      const d = strokes.map(s => 'M' + s.map(p => p.join(' ')).join(' L')).join(' ');
      out.value = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + W + ' ' + H + '" width="' + W + '" height="' + H + '"><path d="' + d + '" fill="none" stroke="#0b1b3a" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }
    $('[data-sig-clear]', box).addEventListener('click', () => { ctx.clearRect(0, 0, W, H); strokes = []; build(); });
    box.closest('form').addEventListener('submit', e => { if (!out.value && !(box.closest('form').querySelector('input[type=file]') || {}).value) { e.preventDefault(); alert('Silakan gambar tanda tangan terlebih dahulu.'); document.querySelectorAll('.btn.loading').forEach(b => b.classList.remove('loading')); } });
  });

  // Prompt cetak otomatis
  if (document.body.dataset.autoprint) setTimeout(() => window.print(), 600);
})();
