(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const root = document.documentElement;
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const store = {
    get: k => { try { return localStorage.getItem(k); } catch (e) { return null; } },
    set: (k, v) => { try { localStorage.setItem(k, v); } catch (e) {} }
  };
  const icons = () => { if (window.lucide) lucide.createIcons(); };
  const css = n => getComputedStyle(root).getPropertyValue(n).trim();
  const plain = s => s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  icons();

  /* ---------- Thème clair / sombre ---------- */
  $('#themeToggle')?.addEventListener('click', () => {
    const light = root.classList.toggle('light');
    store.set('prime-theme', light ? 'light' : 'dark');
    themeCharts();
  });

  /* ---------- Menu : mobile (tiroir) et desktop (compact) ---------- */
  const sidebar = $('#sidebar'), backdrop = $('#backdrop'), menuBtn = $('#menuToggle');
  const closeMenu = () => { sidebar?.classList.remove('open'); backdrop?.classList.remove('show'); menuBtn?.setAttribute('aria-expanded', 'false'); };
  menuBtn?.addEventListener('click', () => {
    if (innerWidth <= 860) {
      const open = sidebar.classList.toggle('open');
      backdrop?.classList.toggle('show', open);
      menuBtn.setAttribute('aria-expanded', String(open));
    } else {
      const compact = root.classList.toggle('compact');
      store.set('prime-compact', compact ? '1' : '0');
      menuBtn.setAttribute('aria-expanded', String(!compact));
    }
  });
  backdrop?.addEventListener('click', closeMenu);
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMenu(); });

  /* ---------- Recherche rapide de pages (Ctrl/Cmd + K) ---------- */
  const qs = $('#quickSearch'), qr = $('#quickResults');
  if (qs && qr) {
    const pages = $$('.navigation a.nav-item').map(a => ({ label: a.textContent.trim(), href: a.href }));
    const render = () => {
      const v = plain(qs.value.trim());
      qr.replaceChildren();
      if (!v) { qr.hidden = true; return; }
      const found = pages.filter(p => plain(p.label).includes(v)).slice(0, 6);
      if (!found.length) { const p = document.createElement('p'); p.textContent = 'Aucune page trouvée.'; qr.append(p); }
      found.forEach(p => { const a = document.createElement('a'); a.href = p.href; a.textContent = p.label; qr.append(a); });
      qr.hidden = false;
    };
    qs.addEventListener('input', render);
    qs.addEventListener('keydown', e => {
      if (e.key === 'Enter') { const first = $('a', qr); if (first) { e.preventDefault(); location.href = first.href; } }
      if (e.key === 'Escape') { qs.value = ''; render(); qs.blur(); }
    });
    document.addEventListener('keydown', e => { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); qs.focus(); qs.select(); } });
    document.addEventListener('click', e => { if (!e.target.closest('.quick-search')) qr.hidden = true; });
  }

  /* ---------- Effet ripple sur les boutons ---------- */
  document.addEventListener('pointerdown', e => {
    const b = e.target.closest('button');
    if (!b || b.disabled || reduce || b.classList.contains('icon-button') || b.closest('.side-profile')) return;
    const r = b.getBoundingClientRect(), size = Math.max(r.width, r.height);
    const s = document.createElement('span');
    s.className = 'ripple';
    s.style.cssText = `width:${size}px;height:${size}px;left:${e.clientX - r.left - size / 2}px;top:${e.clientY - r.top - size / 2}px`;
    b.append(s);
    s.addEventListener('animationend', () => s.remove());
  });

  /* ---------- Anti double-envoi + état de chargement ---------- */
  document.addEventListener('submit', e => {
    const f = e.target;
    if (!(f instanceof HTMLFormElement) || f.method.toLowerCase() !== 'post' || e.defaultPrevented) return;
    const btn = $('button:not([type=button])', f);
    if (!btn) return;
    setTimeout(() => { btn.classList.add('is-loading'); btn.disabled = true; }, 0);
  });
  addEventListener('pageshow', () => $$('button.is-loading').forEach(b => { b.classList.remove('is-loading'); b.disabled = false; }));

  /* ---------- Compteurs animés (count-up) ---------- */
  const NUM = /^(-?\d[\d\s\u00a0\u202f]*)(?:,(\d+))?(\s*[A-Za-z%€].*)?$/;
  const countTargets = '.stat-value,.cash-total,.cash-methods strong,.finance-summary strong,.chart-summary strong,.donut-center strong,.hero-panel>strong,.legend strong';
  const runCount = el => {
    const original = el.textContent, m = original.trim().match(NUM);
    if (!m || reduce) return;
    const dec = (m[2] || '').length, target = parseFloat(m[1].replace(/[\s\u00a0\u202f]/g, '') + (dec ? '.' + m[2] : ''));
    if (!isFinite(target) || target === 0) return;
    const suffix = m[3] || '', t0 = performance.now(), dur = 900;
    const fmt = v => v.toLocaleString('fr-FR', { minimumFractionDigits: dec, maximumFractionDigits: dec }) + suffix;
    const tick = now => {
      const p = Math.min((now - t0) / dur, 1), eased = 1 - Math.pow(1 - p, 4);
      el.textContent = p < 1 ? fmt(target * eased) : original;
      if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  };
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver(entries => entries.forEach(en => { if (en.isIntersecting) { io.unobserve(en.target); runCount(en.target); } }), { threshold: .3 });
    $$(countTargets).forEach(el => io.observe(el));
  }

  /* ---------- Tableaux : filtre instantané, tri, export CSV ---------- */
  const cellValue = c => c.textContent.trim().replace(/\s+/g, ' ');
  const sortKey = txt => {
    const d = txt.match(/^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(?:à\s+)?(\d{2}):(\d{2}))?/);
    if (d) return Number(d[3] + d[2] + d[1] + (d[4] || '00') + (d[5] || '00'));
    const n = txt.match(/^-?\d[\d\s\u00a0\u202f]*(?:,\d+)?/);
    if (n && /^[\d\s\u00a0\u202f,.\-]*(?:\s*[A-Za-z%]{0,4})?$/.test(txt.replace(/\s*(DT|kg|cm|%)$/, ''))) return parseFloat(n[0].replace(/[\s\u00a0\u202f]/g, '').replace(',', '.'));
    return txt.toLowerCase();
  };
  const csvSafe = v => (/^[\s\x00-\x1F]*[=+@\-\t\r]/.test(v) ? "'" + v : v).replace(/"/g, '""');
  $$('.management-table table').forEach(table => {
    const body = table.tBodies[0];
    if (!body) return;
    const dataRows = () => Array.from(body.rows).filter(r => !r.querySelector('td.empty'));
    if (dataRows().length < 2) return;
    const heads = Array.from(table.tHead.rows[0].cells);
    const skip = heads.map(h => /^(ACTIONS?|REÇU)$/i.test(h.textContent.trim()));
    const scroller = table.closest('.table-scroll');

    // Filtre
    const tools = document.createElement('div');
    tools.className = 'table-tools';
    const input = document.createElement('input');
    input.type = 'search'; input.placeholder = 'Filtrer cette page…'; input.setAttribute('aria-label', 'Filtrer les lignes du tableau');
    input.addEventListener('input', () => {
      const v = plain(input.value.trim());
      dataRows().forEach(r => { r.hidden = v !== '' && !plain(r.textContent).includes(v); });
    });
    const exp = document.createElement('button');
    exp.type = 'button'; exp.className = 'btn btn-outline';
    exp.innerHTML = '<i data-lucide="download"></i><span>Exporter</span>';
    exp.addEventListener('click', () => {
      const cols = heads.map((h, i) => i).filter(i => !skip[i]);
      const lines = [cols.map(i => '"' + csvSafe(cellValue(heads[i])) + '"').join(';')];
      dataRows().filter(r => !r.hidden).forEach(r => lines.push(cols.map(i => '"' + csvSafe(cellValue(r.cells[i])) + '"').join(';')));
      const blob = new Blob(['\ufeff' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
      const a = document.createElement('a');
      const name = (table.closest('.card')?.querySelector('h3')?.textContent || 'export').trim().toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
      a.href = URL.createObjectURL(blob); a.download = (name || 'export') + '.csv';
      document.body.append(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    });
    tools.append(input, exp);
    scroller.before(tools);

    // Tri
    heads.forEach((th, i) => {
      if (skip[i]) return;
      th.classList.add('sortable'); th.tabIndex = 0; th.setAttribute('aria-sort', 'none');
      const sort = () => {
        const asc = th.getAttribute('aria-sort') !== 'ascending';
        heads.forEach(h => h.setAttribute('aria-sort', 'none'));
        th.setAttribute('aria-sort', asc ? 'ascending' : 'descending');
        const rows = dataRows().map(r => ({ r, k: sortKey(cellValue(r.cells[i])) }));
        rows.sort((a, b) => {
          const bothNum = typeof a.k === 'number' && typeof b.k === 'number';
          const c = bothNum ? a.k - b.k : String(a.k).localeCompare(String(b.k), 'fr', { numeric: true });
          return asc ? c : -c;
        });
        rows.forEach(x => body.append(x.r));
      };
      th.addEventListener('click', sort);
      th.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); sort(); } });
    });
  });
  icons();

  /* ---------- Graphiques ---------- */
  const charts = [];
  const lineColor = () => root.classList.contains('light') ? '#A07C00' : '#FFD400';
  const palette = ['#FFD400', '#EF4444', '#9CA3AF', '#22C55E'];
  function themeCharts() {
    if (!window.Chart) return;
    Chart.defaults.color = css('--muted');
    charts.forEach(c => {
      const y = c.options.scales && c.options.scales.y;
      if (y && y.grid) y.grid.color = css('--border');
      if (c.config.type === 'line') { const ds = c.data.datasets[0]; ds.borderColor = lineColor(); ds.pointBorderColor = lineColor(); if (ds.pointBackgroundColor !== '#0A0A0A') ds.pointBackgroundColor = lineColor(); }
      c.update('none');
    });
  }
  const clearSkeleton = () => $$('.skeleton').forEach(el => el.classList.remove('skeleton'));
  setTimeout(clearSkeleton, 4000);
  if (!window.Chart) return;
  Chart.defaults.color = css('--muted');
  Chart.defaults.font.family = "'Barlow', sans-serif";
  Chart.defaults.font.size = 11;
  Chart.defaults.animation = reduce ? false : { duration: 1000, easing: 'easeOutQuart' };
  const tooltip = { backgroundColor: 'rgba(10,10,10,.96)', titleColor: '#fff', bodyColor: '#CBD5E1', borderColor: 'rgba(148,163,184,.3)', borderWidth: 1, padding: 12, cornerRadius: 10, displayColors: false };

  if (window.fitnessData && $('#fitnessChart')) {
    const d = window.fitnessData, ctx = $('#fitnessChart').getContext('2d');
    const g = ctx.createLinearGradient(0, 0, 0, 220); g.addColorStop(0, 'rgba(255,212,0,.3)'); g.addColorStop(1, 'rgba(255,212,0,0)');
    charts.push(new Chart(ctx, { type: 'line', data: { labels: d.labels, datasets: [{ label: 'Poids (kg)', data: d.values, borderColor: lineColor(), backgroundColor: g, fill: true, tension: .35, pointRadius: 3, pointBackgroundColor: lineColor(), borderWidth: 2.5 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip }, scales: { y: { beginAtZero: false, grid: { color: css('--border') }, border: { display: false } }, x: { grid: { display: false }, border: { display: false } } } } }));
  }

  if (window.reportData && $('#reportChart')) {
    const d = window.reportData;
    charts.push(new Chart($('#reportChart'), {type:'bar', data:{labels:d.labels,datasets:[{label:'Encaissements',data:d.income,backgroundColor:'#FFD400',borderRadius:3},{label:'Dépenses',data:d.expense,backgroundColor:'#EF4444',borderRadius:3}]}, options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:true},tooltip},scales:{x:{grid:{display:false},border:{display:false}},y:{beginAtZero:true,grid:{color:css('--border')},border:{display:false},ticks:{callback:v=>v+' DT'}}}}}));
  }

  if (window.dashboardData && $('#revenueChart')) {
    const d = window.dashboardData, ctx = $('#revenueChart').getContext('2d');
    const g = ctx.createLinearGradient(0, 0, 0, 220); g.addColorStop(0, 'rgba(255,212,0,.32)'); g.addColorStop(1, 'rgba(255,212,0,0)');
    charts.push(new Chart(ctx, { type: 'line', data: { labels: d.labels, datasets: [{ data: d.values, borderColor: lineColor(), backgroundColor: g, fill: true, tension: .4, pointRadius: 4, pointHoverRadius: 6, pointBackgroundColor: '#0A0A0A', pointBorderColor: lineColor(), pointBorderWidth: 2, borderWidth: 2.5 }] },
      options: { responsive: true, maintainAspectRatio: false, interaction: { intersect: false, mode: 'index' },
        plugins: { legend: { display: false }, tooltip: { ...tooltip, callbacks: { label: c => c.parsed.y.toLocaleString('fr-FR', { minimumFractionDigits: 3 }) + ' DT' } } },
        scales: { x: { grid: { display: false }, border: { display: false } }, y: { beginAtZero: true, grid: { color: css('--border') }, border: { display: false }, ticks: { maxTicksLimit: 4, callback: v => v + ' DT' } } } } }));
  }
  if (window.dashboardData && $('#planChart')) {
    const d = window.dashboardData;
    const has = d.planValues.some(Boolean);
    charts.push(new Chart($('#planChart'), { type: 'doughnut', data: { labels: d.planLabels, datasets: [{ data: has ? d.planValues : [1], backgroundColor: has ? palette : ['#3A3A3A'], borderWidth: 0, spacing: 4, borderRadius: 8, hoverOffset: 6 }] },
      options: { responsive: true, maintainAspectRatio: false, cutout: '78%', plugins: { legend: { display: false }, tooltip: { ...tooltip, enabled: has } } } }));
  }
  clearSkeleton();
})();