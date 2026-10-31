(() => {
  if (window.lucide) lucide.createIcons();
  const toggle = document.getElementById('themeToggle');
  if (localStorage.getItem('prime-theme') === 'light') document.body.classList.add('light');
  toggle?.addEventListener('click', () => { document.body.classList.toggle('light'); localStorage.setItem('prime-theme', document.body.classList.contains('light') ? 'light' : 'dark'); });
  document.getElementById('menuToggle')?.addEventListener('click', () => document.getElementById('sidebar').classList.toggle('open'));
  document.addEventListener('click', e => { if (innerWidth <= 760 && !e.target.closest('#sidebar') && !e.target.closest('#menuToggle')) document.getElementById('sidebar')?.classList.remove('open'); });
  if (!window.Chart || !window.dashboardData) return;
  const d = window.dashboardData;
  Chart.defaults.color = '#899589'; Chart.defaults.font.family = 'DM Sans'; Chart.defaults.font.size = 10;
  const ctx = document.getElementById('revenueChart').getContext('2d');
  const gradient = ctx.createLinearGradient(0, 0, 0, 180); gradient.addColorStop(0, 'rgba(216,187,112,.24)'); gradient.addColorStop(1, 'rgba(216,187,112,0)');
  new Chart(ctx, {type:'line',data:{labels:d.labels,datasets:[{data:d.values,borderColor:'#d8bb70',backgroundColor:gradient,fill:true,tension:.4,pointRadius:3,pointBackgroundColor:'#d8bb70',borderWidth:2}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{grid:{display:false},border:{display:false}},y:{beginAtZero:true,grid:{color:'rgba(140,155,140,.1)'},border:{display:false},ticks:{maxTicksLimit:4,callback:v=>v+' DT'}}}}});
  new Chart(document.getElementById('planChart'),{type:'doughnut',data:{labels:d.planLabels,datasets:[{data:d.planValues.some(Boolean)?d.planValues:[1],backgroundColor:d.planValues.some(Boolean)?['#d6b966','#719e91','#7789ae','#bd8e77']:['#353d35'],borderWidth:0,spacing:3,borderRadius:4}]},options:{responsive:true,maintainAspectRatio:false,cutout:'76%',plugins:{legend:{display:false},tooltip:{enabled:d.planValues.some(Boolean)}}}});
})();
